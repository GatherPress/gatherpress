<?php
/**
 * OpenStreetMap-backed venue map provider.
 *
 * Renders static maps by fetching CartoDB-served OSM basemap tiles
 * (`{z}/{x}/{y}.png`), compositing them into a single canvas centered on
 * the venue's coordinates, and stamping a marker at the canvas center.
 * Retina (2×) variants render at `tile_zoom = zoom + 1` so labels and
 * road lines come from native higher-detail tiles rather than upscaled
 * 1× pixels.
 *
 * @package GatherPress\Core\Venue\Map\Provider
 * @since 0.34.0
 */

namespace GatherPress\Core\Venue\Map\Provider;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Venue\Map\Carto;
use GatherPress\Core\Venue\Map;
use GdImage;
use Throwable;

/**
 * Class OSM.
 *
 * Tile-composite static map provider. Reads CARTO's OSM basemap by
 * default; the tile-URL template is filterable so a site can
 * point at a different XYZ tile server (Stamen, Maptiler, self-hosted)
 * without changing code.
 *
 * @since 0.34.0
 */
final class OSM extends Base {

	/**
	 * Default XYZ tile URL template: CARTO's "light_all" basemap, the same
	 * one the interactive map uses.
	 *
	 * @since 0.34.0
	 * @since 0.36.2 Uses CARTO's `basemaps.cartocdn.com` host, as the interactive map does.
	 * @var string
	 */
	const DEFAULT_TILE_URL = Settings::MAP_TILE_URL;

	/**
	 * Tile dimension in pixels (Web Mercator standard).
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const TILE_SIZE = 256;

	/**
	 * Wall-clock budget in seconds for a single render() call. When the
	 * deadline is exceeded mid-loop, remaining tiles are skipped and the
	 * gray canvas background shows through.
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const COMPOSITE_TIME_BUDGET = 10;

	/**
	 * Prefix of the transient set when a render gets no tiles, which
	 * pauses rendering for `TILES_FAILED_PAUSE` seconds. The rest of the
	 * name is a hash of the tile URL and tile zoom, so a new key, a new tile
	 * server, or a zoom the server does serve does not wait for the pause.
	 *
	 * @since 0.36.2
	 * @var string
	 */
	const TILES_FAILED_TRANSIENT = 'gatherpress_static_map_tiles_failed_';

	/**
	 * Seconds to pause rendering after a render gets no tiles.
	 *
	 * A map is rendered on page view when it has no saved image, so without
	 * a pause a tile host that is down or rejecting the key would be asked
	 * again on every page view.
	 *
	 * @since 0.36.2
	 * @var int
	 */
	const TILES_FAILED_PAUSE = 5 * MINUTE_IN_SECONDS;

	/**
	 * Default colors of the static map marker, as hex colors.
	 *
	 * `border` is the outer ring, `fill` the body, `outline` the thin line
	 * around the body, and `center` the dot in the middle.
	 *
	 * @since TBD
	 * @var array<string, string>
	 */
	const DEFAULT_MARKER_COLORS = array(
		'border'  => '#ffffff',
		'fill'    => '#dc3545',
		'outline' => '#1e1e1e',
		'center'  => '#ffffff',
	);

	/**
	 * Provider slug used in post meta keys, filenames, and the
	 * `map_platform` setting.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'osm';
	}

	/**
	 * Human-readable label shown in the Settings → Venues → Maps platform
	 * dropdown.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'OpenStreetMap', 'gatherpress' );
	}

	/**
	 * Composite OSM tiles around the venue, stamp a marker at the canvas
	 * center, and return the finished GD image. Returns null when GD is
	 * unavailable, when the requested dimensions are not positive, when
	 * the retina variant would require a tile zoom past the highest one
	 * the tiles are served at, or when no tile loads.
	 *
	 * @since 0.34.0
	 * @since 0.36.2 Returns null when no tile loads, and for a pause after that.
	 *            Renders CARTO tiles at `Carto::ZOOM_MAX` at most.
	 *
	 * @param float  $latitude  Venue latitude in decimal degrees.
	 * @param float  $longitude Venue longitude in decimal degrees.
	 * @param int    $zoom      Map zoom level (already clamped by the orchestrator).
	 * @param int    $width     Logical pixel width (at density 1).
	 * @param int    $height    Logical pixel height (at density 1).
	 * @param int    $density   Pixel-density multiplier. 1 = standard, 2 = retina.
	 * @param string $map_type  Map type slug (OSM only renders roadmap tiles).
	 *
	 * @return GdImage|null Finished image, or null on failure.
	 */
	public function render(
		float $latitude,
		float $longitude,
		int $zoom,
		int $width,
		int $height,
		int $density = 1,
		string $map_type = 'roadmap'
	): ?GdImage {
		// OSM tiles are roadmap-only; $map_type is accepted for provider parity.
		// PHP built without the GD extension. Can't simulate in a unit test without making the runtime itself broken.
		if ( ! function_exists( 'imagecreatetruecolor' ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		if ( $width < 1 || $height < 1 ) {
			return null;
		}

		// Coerce unsupported densities back to 1. Allowing, say, density=3
		// with `(int) log(3, 2) === 1` would paint a 3×-sized canvas using
		// only 2× tiles — a cheap upscale that wastes disk for no gain.
		if ( ! in_array( $density, Map::SUPPORTED_DENSITIES, true ) ) {
			$density = 1;
		}

		$tiles = $this->get_tile_url_template();

		// CARTO serves raster tiles only up to its own limit, so a CARTO map
		// renders at that zoom at most, as the interactive map does.
		$max_zoom = Carto::is_tile_url( $tiles ) ? Carto::ZOOM_MAX : Map::ZOOM_MAX;
		$zoom     = min( $zoom, $max_zoom );

		// Tile zoom climbs with density so the retina variant renders true
		// zoom+1 detail. If that would exceed the provider's serveable
		// zoom range, skip — orchestrator treats null as "no retina at
		// this combo" and moves on with just the 1× image.
		$tile_zoom = $zoom + (int) log( $density, 2 );

		// md5() here only names the transient, matching class-map.php.
		$paused = self::TILES_FAILED_TRANSIENT . md5( $tiles . '|' . $tile_zoom ); // NOSONAR.

		if ( $tile_zoom > $max_zoom || false !== get_transient( $paused ) ) {
			return null;
		}

		$canvas_width  = $width * $density;
		$canvas_height = $height * $density;

		$venue_world_x = $this->lng_to_world_pixel( $longitude, $tile_zoom );
		$venue_world_y = $this->lat_to_world_pixel( $latitude, $tile_zoom );

		$left_pixel = (int) round( $venue_world_x - $canvas_width / 2 );
		$top_pixel  = (int) round( $venue_world_y - $canvas_height / 2 );

		$left_tile   = (int) floor( $left_pixel / self::TILE_SIZE );
		$top_tile    = (int) floor( $top_pixel / self::TILE_SIZE );
		$right_tile  = (int) floor( ( $left_pixel + $canvas_width - 1 ) / self::TILE_SIZE );
		$bottom_tile = (int) floor( ( $top_pixel + $canvas_height - 1 ) / self::TILE_SIZE );

		$canvas = imagecreatetruecolor( $canvas_width, $canvas_height );

		// Background: neutral gray so missing tiles blend rather than glaring black.
		$bg = (int) imagecolorallocate( $canvas, 238, 238, 238 );
		imagefilledrectangle( $canvas, 0, 0, $canvas_width - 1, $canvas_height - 1, $bg );

		/**
		 * Filter the wall-clock budget (in seconds) for a single OSM
		 * render() call. When the deadline is exceeded mid-loop, remaining
		 * tiles are skipped and the gray background shows through.
		 *
		 * @since 0.34.0
		 *
		 * @param float $budget Default budget from COMPOSITE_TIME_BUDGET.
		 */
		$budget   = (float) apply_filters(
			'gatherpress_static_map_composite_time_budget',
			self::COMPOSITE_TIME_BUDGET
		);
		$deadline = microtime( true ) + $budget;
		$painted  = 0;

		for ( $tx = $left_tile; $tx <= $right_tile; $tx++ ) {
			for ( $ty = $top_tile; $ty <= $bottom_tile; $ty++ ) {
				if ( microtime( true ) >= $deadline ) {
					break 2;
				}

				$painted += (int) $this->paint_tile( $canvas, $tx, $ty, $tile_zoom, $left_pixel, $top_pixel, $tiles );
			}
		}

		// A map with no tiles is only the gray background. It is not saved,
		// so a later render tries again once the pause ends.
		if ( 0 === $painted ) {
			set_transient( $paused, 1, self::TILES_FAILED_PAUSE );
			return null;
		}

		$this->stamp_marker(
			$canvas,
			(int) round( $canvas_width / 2 ),
			(int) round( $canvas_height / 2 ),
			(float) $density
		);

		return $canvas;
	}

	/**
	 * Fetch one OSM tile and stamp it onto the in-flight canvas at its
	 * world-pixel-aligned destination. Skips tiles that fail to fetch or
	 * decode, and the gray composite background shows through.
	 *
	 * Extracted from `render()` to keep that loop body shallow enough to
	 * stay under SonarCloud's cognitive-complexity threshold.
	 *
	 * @since 0.34.0
	 *
	 * @param GdImage $canvas     Canvas being composited into.
	 * @param int     $tx         X tile coordinate.
	 * @param int     $ty         Y tile coordinate.
	 * @param int     $tile_zoom  OSM zoom level for this tile.
	 * @param int     $left_pixel World-pixel x-offset of the canvas top-left.
	 * @param int     $top_pixel  World-pixel y-offset of the canvas top-left.
	 * @param string  $tiles      URL template (`{z}/{x}/{y}` placeholders).
	 *
	 * @return bool True when the tile was painted.
	 */
	private function paint_tile(
		$canvas,
		int $tx,
		int $ty,
		int $tile_zoom,
		int $left_pixel,
		int $top_pixel,
		string $tiles
	): bool {
		$tile_png = $this->fetch_tile( $tile_zoom, $tx, $ty, $tiles );
		$tile     = null === $tile_png ? false : $this->decode_tile( $tile_png );

		if ( false === $tile ) {
			return false;
		}

		$dst_x = $tx * self::TILE_SIZE - $left_pixel;
		$dst_y = $ty * self::TILE_SIZE - $top_pixel;

		imagecopy( $canvas, $tile, $dst_x, $dst_y, 0, 0, self::TILE_SIZE, self::TILE_SIZE );
		unset( $tile );

		return true;
	}

	/**
	 * Required attribution for OSM + CartoDB. Front end displays this
	 * alongside the static map image.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	public function attribution_html(): string {
		return sprintf(
			'© <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>'
			. ' | © <a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a>',
			esc_url( 'https://www.openstreetmap.org/copyright' ),
			esc_html__( 'OpenStreetMap contributors', 'gatherpress' ),
			esc_url( 'https://carto.com/' ),
			esc_html__( 'CARTO', 'gatherpress' )
		);
	}

	/**
	 * Decode raw PNG bytes into a GD image. Wraps `imagecreatefromstring()`
	 * so the calling composite never fatals on a bad tile.
	 *
	 * Failure modes by environment:
	 * - PHP 7.4 production: returns `false`, emits an `E_WARNING` on bad
	 *   input. No throw — the wrapper returns `false` straight from the
	 *   `try`, the catch doesn't fire.
	 * - PHP 7.4 under PHPUnit: the `E_WARNING` is converted to a
	 *   `PHPUnit\Framework\Error\Warning` (a `Throwable`) by the test
	 *   runner. The catch fires and returns `false`.
	 * - PHP 8.0+: throws `ValueError` for empty input or malformed
	 *   payloads. The catch fires and returns `false`.
	 *
	 * Either way, the caller sees `false` and treats it as "skip this
	 * tile".
	 *
	 * @since 0.34.0
	 *
	 * @param string $bytes Raw PNG bytes from `fetch_tile()`.
	 *
	 * @return GdImage|false Decoded image, or false when the bytes don't decode.
	 */
	protected function decode_tile( string $bytes ): GdImage|false {
		try {
			return imagecreatefromstring( $bytes );
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Fetch, decode, and return the raw PNG bytes for a tile at (z, x, y).
	 *
	 * Returns null on any HTTP failure so the calling render() can skip
	 * that tile without aborting the whole composite; the resulting image
	 * will simply have a blank patch where the fetch failed.
	 *
	 * @since 0.34.0
	 *
	 * @param int    $zoom  Tile zoom.
	 * @param int    $x     Tile x coordinate.
	 * @param int    $y     Tile y coordinate.
	 * @param string $tiles Tile URL template containing `{z}`, `{x}`, `{y}`.
	 *
	 * @return string|null PNG bytes, or null on failure.
	 */
	public function fetch_tile( int $zoom, int $x, int $y, string $tiles ): ?string {
		$url = strtr(
			$tiles,
			array(
				'{z}' => (string) $zoom,
				'{x}' => (string) $x,
				'{y}' => (string) $y,
			)
		);

		$args = array( 'timeout' => 5 );

		// A CARTO key restricted to the site's domain is checked against the
		// referer, which a server request does not send on its own.
		if ( Carto::is_tile_url( $url ) ) {
			$args['headers'] = array( 'Referer' => home_url( '/' ) );
		}

		$response = wp_safe_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = (string) wp_remote_retrieve_body( $response );

		return '' !== $body ? $body : null;
	}

	/**
	 * Draw a simple pin marker at the given pixel coordinates.
	 *
	 * Passing `$scale > 1` proportionally scales the marker's three
	 * concentric ellipses so a retina (2×) composite keeps the marker
	 * visually the same size as the 1× render rather than shrinking to
	 * a pinprick.
	 *
	 * @since 0.34.0
	 * @since TBD Colors come from `get_marker_colors()`.
	 *
	 * @param GdImage $canvas Destination canvas.
	 * @param int     $x      Pixel X position (marker center).
	 * @param int     $y      Pixel Y position (marker center).
	 * @param float   $scale  Multiplier applied to the marker radii.
	 *
	 * @return void
	 */
	public function stamp_marker( $canvas, int $x, int $y, float $scale = 1.0 ): void {
		$colors = array_map(
			fn( string $hex ): int => $this->allocate_hex_color( $canvas, $hex ),
			$this->get_marker_colors()
		);

		$outer = max( 1, (int) round( 20 * $scale ) );
		$inner = max( 1, (int) round( 16 * $scale ) );
		$dot   = max( 1, (int) round( 6 * $scale ) );

		imagefilledellipse( $canvas, $x, $y, $outer, $outer, $colors['border'] );
		imagefilledellipse( $canvas, $x, $y, $inner, $inner, $colors['fill'] );
		imageellipse( $canvas, $x, $y, $inner, $inner, $colors['outline'] );
		imagefilledellipse( $canvas, $x, $y, $dot, $dot, $colors['center'] );
	}

	/**
	 * Colors of the static map marker, filterable.
	 *
	 * A color that is missing or is not a valid hex color keeps its
	 * default, so a filter can change one color and leave the rest.
	 *
	 * @since TBD
	 *
	 * @return array<string, string> Lowercase hex colors keyed like `DEFAULT_MARKER_COLORS`.
	 */
	public function get_marker_colors(): array {
		/**
		 * Filter the colors of the marker drawn on static OpenStreetMap images.
		 *
		 * Static maps are rendered on the server, so CSS cannot change their
		 * marker. Saved maps render again when these colors change.
		 *
		 * @since TBD
		 *
		 * @param array<string, string> $colors {
		 *     Hex colors of the marker.
		 *
		 *     @type string $border  Outer ring. Default `#ffffff`.
		 *     @type string $fill    Body of the marker. Default `#dc3545`.
		 *     @type string $outline Thin line around the body. Default `#1e1e1e`.
		 *     @type string $center  Dot in the middle. Default `#ffffff`.
		 * }
		 */
		$filtered = (array) apply_filters( 'gatherpress_static_map_marker_colors', self::DEFAULT_MARKER_COLORS );
		$colors   = array();

		foreach ( self::DEFAULT_MARKER_COLORS as $key => $default ) {
			$color          = is_string( $filtered[ $key ] ?? null ) ? sanitize_hex_color( $filtered[ $key ] ) : '';
			$colors[ $key ] = $color ? strtolower( $color ) : $default;
		}

		return $colors;
	}

	/**
	 * Allocate a hex color such as `#dc3545` or `#fff` on a canvas.
	 *
	 * @since TBD
	 *
	 * @param GdImage $canvas Destination canvas.
	 * @param string  $hex    Valid hex color, from `get_marker_colors()`.
	 *
	 * @return int GD color identifier.
	 */
	private function allocate_hex_color( $canvas, string $hex ): int {
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		/**
		 * Red, green, and blue channels. Two hex digits each, so 0 to 255.
		 *
		 * @var array{int<0, 255>, int<0, 255>, int<0, 255>} $rgb
		 */
		$rgb = sscanf( $hex, '%02x%02x%02x' );

		return (int) imagecolorallocate( $canvas, $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Tile URL template, filterable and layered under `custom_map_tile_url`.
	 *
	 * @since 0.34.0
	 * @since 0.36.0 Layered under the `custom_map_tile_url` setting; resolves `{s}`.
	 *
	 * @return string
	 */
	protected function get_tile_url_template(): string {
		$custom  = trim( (string) Settings::get_instance()->get( 'custom_map_tile_url' ) );
		$default = '' !== $custom ? $custom : self::DEFAULT_TILE_URL;

		/**
		 * Filter the tile URL template used by the OSM static map provider.
		 *
		 * @since 0.34.0
		 *
		 * @param string $template Tile URL with `{z}`, `{x}`, `{y}` placeholders.
		 */
		$template = (string) apply_filters( 'gatherpress_static_map_tile_url', $default );

		// Static compositor makes direct requests, so `{s}` needs resolving.
		$template = str_replace( '{s}', 'a', $template );

		// The CARTO key belongs only to the built-in default: a custom URL
		// is documented as overriding CARTO entirely, even when it happens
		// to resolve to a CARTO-allowlisted host.
		if ( '' !== $custom ) {
			return $template;
		}

		return Settings::add_map_tile_key( $template );
	}

	/**
	 * Where the tiles come from, for the static map cache.
	 *
	 * Saved maps render again when the tile URL, the CARTO key, or the
	 * marker colors change. The key is replaced with a hash of it, so its
	 * value stays out of the cache hash.
	 *
	 * @since 0.36.2
	 * @since TBD Includes the marker colors when they are not the default.
	 *
	 * @return string The tile URL template, with the key hashed.
	 */
	public function get_cache_source(): string {
		$source = (string) preg_replace_callback(
			'/([?&]key=)([^&]*)/',
			// md5() here only keeps the key out of the cache hash.
			static fn( array $matches ): string => $matches[1] . md5( $matches[2] ), // NOSONAR.
			$this->get_tile_url_template()
		);

		// Left out for the default marker, so existing saved maps keep their hash.
		$colors = $this->get_marker_colors();

		if ( self::DEFAULT_MARKER_COLORS !== $colors ) {
			$source .= '|marker=' . implode( ',', $colors );
		}

		return $source;
	}

	/**
	 * Convert longitude to absolute pixel X in world pixel space at `$zoom`.
	 *
	 * @since 0.34.0
	 *
	 * @param float $lng  Longitude in degrees.
	 * @param int   $zoom Zoom level.
	 *
	 * @return float
	 */
	protected function lng_to_world_pixel( float $lng, int $zoom ): float {
		return ( ( $lng + 180.0 ) / 360.0 ) * self::TILE_SIZE * ( 2 ** $zoom );
	}

	/**
	 * Convert latitude to absolute pixel Y in world pixel space at `$zoom`.
	 *
	 * @since 0.34.0
	 *
	 * @param float $lat  Latitude in degrees.
	 * @param int   $zoom Zoom level.
	 *
	 * @return float
	 */
	protected function lat_to_world_pixel( float $lat, int $zoom ): float {
		$rad = deg2rad( $lat );

		return (
			1.0 - log( tan( $rad ) + 1.0 / cos( $rad ) ) / M_PI
		) / 2.0 * self::TILE_SIZE * ( 2 ** $zoom );
	}
}
