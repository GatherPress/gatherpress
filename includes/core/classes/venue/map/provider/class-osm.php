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
 * @since 1.0.0
 */

namespace GatherPress\Core\Venue\Map\Provider;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Venue\Map;
use GdImage;
use Throwable;

/**
 * Class OSM.
 *
 * Tile-composite static map provider. Reads CartoDB's keyless OSM
 * basemap by default; the tile-URL template is filterable so a site can
 * point at a different XYZ tile server (Stamen, Maptiler, self-hosted)
 * without changing code.
 *
 * @since 1.0.0
 */
class OSM extends Base {

	/**
	 * Default XYZ tile URL template. CartoDB's "light_all" basemap —
	 * keyless, fast CDN, fits the GatherPress aesthetic.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_TILE_URL = 'https://cartodb-basemaps-a.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png';

	/**
	 * Tile dimension in pixels (Web Mercator standard).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TILE_SIZE = 256;

	/**
	 * Wall-clock budget in seconds for a single render() call. When the
	 * deadline is exceeded mid-loop, remaining tiles are skipped and the
	 * gray canvas background shows through.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const COMPOSITE_TIME_BUDGET = 10;

	/**
	 * Provider slug used in post meta keys, filenames, and the
	 * `map_platform` setting.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'OpenStreetMap', 'gatherpress' );
	}

	/**
	 * Composite OSM tiles around the venue, stamp a marker at the canvas
	 * center, and return the finished GD image. Returns null when GD is
	 * unavailable, when the requested density is unsupported, or when the
	 * retina variant would require a tile zoom past `Map::ZOOM_MAX`.
	 *
	 * Return type is intentionally untyped at the PHP signature level for
	 * PHP 7.4 compatibility (GD returns a `resource` there, not a
	 * `GdImage`).
	 *
	 * @since 1.0.0
	 *
	 * @param float $latitude  Venue latitude in decimal degrees.
	 * @param float $longitude Venue longitude in decimal degrees.
	 * @param int   $zoom      Map zoom level (already clamped by the orchestrator).
	 * @param int   $width     Logical pixel width (at density 1).
	 * @param int   $height    Logical pixel height (at density 1).
	 * @param int   $density   Pixel-density multiplier. 1 = standard, 2 = retina.
	 * @return GdImage|resource|null Finished image, or null on failure.
	 */
	public function render(
		float $latitude,
		float $longitude,
		int $zoom,
		int $width,
		int $height,
		int $density = 1
	) {
		// PHP built without the GD extension. Can't simulate in a unit test without making the runtime itself broken.
		if ( ! function_exists( 'imagecreatetruecolor' ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		// Coerce unsupported densities back to 1. Allowing, say, density=3
		// with `(int) log(3, 2) === 1` would paint a 3×-sized canvas using
		// only 2× tiles — a cheap upscale that wastes disk for no gain.
		if ( ! in_array( $density, Map::SUPPORTED_DENSITIES, true ) ) {
			$density = 1;
		}

		// Tile zoom climbs with density so the retina variant renders true
		// zoom+1 detail. If that would exceed the provider's serveable
		// zoom range, skip — orchestrator treats null as "no retina at
		// this combo" and moves on with just the 1× image.
		$tile_zoom = $zoom + (int) log( $density, 2 );

		if ( $tile_zoom > Map::ZOOM_MAX ) {
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
		$bg = imagecolorallocate( $canvas, 238, 238, 238 );
		imagefilledrectangle( $canvas, 0, 0, $canvas_width - 1, $canvas_height - 1, $bg );

		$tiles = $this->get_tile_url_template();

		/**
		 * Filter the wall-clock budget (in seconds) for a single OSM
		 * render() call. When the deadline is exceeded mid-loop, remaining
		 * tiles are skipped and the gray background shows through.
		 *
		 * @since 1.0.0
		 *
		 * @param float $budget Default budget from COMPOSITE_TIME_BUDGET.
		 */
		$budget   = (float) apply_filters(
			'gatherpress_static_map_composite_time_budget',
			self::COMPOSITE_TIME_BUDGET
		);
		$deadline = microtime( true ) + $budget;

		for ( $tx = $left_tile; $tx <= $right_tile; $tx++ ) {
			for ( $ty = $top_tile; $ty <= $bottom_tile; $ty++ ) {
				if ( microtime( true ) >= $deadline ) {
					break 2;
				}

				$tile_png = $this->fetch_tile( $tile_zoom, $tx, $ty, $tiles );

				if ( null === $tile_png ) {
					continue;
				}

				$tile = $this->decode_tile( $tile_png );

				if ( false === $tile ) {
					continue;
				}

				$dst_x = $tx * self::TILE_SIZE - $left_pixel;
				$dst_y = $ty * self::TILE_SIZE - $top_pixel;

				imagecopy( $canvas, $tile, $dst_x, $dst_y, 0, 0, self::TILE_SIZE, self::TILE_SIZE );
				imagedestroy( $tile );
			}
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
	 * Required attribution for OSM + CartoDB. Front end displays this
	 * alongside the static map image.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @param string $bytes Raw PNG bytes from `fetch_tile()`.
	 * @return GdImage|resource|false Decoded image, or false when the bytes don't decode.
	 */
	protected function decode_tile( string $bytes ) {
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
	 * @since 1.0.0
	 *
	 * @param int    $zoom  Tile zoom.
	 * @param int    $x     Tile x coordinate.
	 * @param int    $y     Tile y coordinate.
	 * @param string $tiles Tile URL template containing `{z}`, `{x}`, `{y}`.
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

		$response = wp_safe_remote_get( $url, array( 'timeout' => 5 ) );

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
	 * @since 1.0.0
	 *
	 * @param GdImage|resource $canvas Destination canvas.
	 * @param int              $x      Pixel X position (marker center).
	 * @param int              $y      Pixel Y position (marker center).
	 * @param float            $scale  Multiplier applied to the marker radii.
	 * @return void
	 */
	public function stamp_marker( $canvas, int $x, int $y, float $scale = 1.0 ): void {
		$white = imagecolorallocate( $canvas, 255, 255, 255 );
		$red   = imagecolorallocate( $canvas, 220, 53, 69 );
		$dark  = imagecolorallocate( $canvas, 30, 30, 30 );

		$outer = max( 1, (int) round( 20 * $scale ) );
		$inner = max( 1, (int) round( 16 * $scale ) );
		$dot   = max( 1, (int) round( 6 * $scale ) );

		imagefilledellipse( $canvas, $x, $y, $outer, $outer, $white );
		imagefilledellipse( $canvas, $x, $y, $inner, $inner, $red );
		imageellipse( $canvas, $x, $y, $inner, $inner, $dark );
		imagefilledellipse( $canvas, $x, $y, $dot, $dot, $white );
	}

	/**
	 * Tile URL template. Filterable so site owners can repoint at a
	 * different XYZ provider (self-hosted, Stamen, Maptiler, etc.) without
	 * forking the provider class.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_tile_url_template(): string {
		/**
		 * Filter the tile URL template used by the OSM static map provider.
		 *
		 * @since 1.0.0
		 *
		 * @param string $template Tile URL with `{z}`, `{x}`, `{y}` placeholders.
		 */
		return (string) apply_filters( 'gatherpress_static_map_tile_url', self::DEFAULT_TILE_URL );
	}

	/**
	 * Convert longitude to absolute pixel X in world pixel space at `$zoom`.
	 *
	 * @since 1.0.0
	 *
	 * @param float $lng  Longitude in degrees.
	 * @param int   $zoom Zoom level.
	 * @return float
	 */
	protected function lng_to_world_pixel( float $lng, int $zoom ): float {
		return ( ( $lng + 180.0 ) / 360.0 ) * self::TILE_SIZE * ( 2 ** $zoom );
	}

	/**
	 * Convert latitude to absolute pixel Y in world pixel space at `$zoom`.
	 *
	 * @since 1.0.0
	 *
	 * @param float $lat  Latitude in degrees.
	 * @param int   $zoom Zoom level.
	 * @return float
	 */
	protected function lat_to_world_pixel( float $lat, int $zoom ): float {
		$rad = deg2rad( $lat );

		return (
			1.0 - log( tan( $rad ) + 1.0 / cos( $rad ) ) / M_PI
		) / 2.0 * self::TILE_SIZE * ( 2 ** $zoom );
	}
}
