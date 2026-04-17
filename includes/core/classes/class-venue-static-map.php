<?php
/**
 * Generates and stores a pre-rendered static map image for a venue.
 *
 * Fetches a small set of CartoDB basemap tiles around the venue's coordinates,
 * composites them into a single PNG with a marker stamped at the venue's exact
 * position, and stores the result under `wp-content/uploads/gatherpress/static-maps/`.
 * The file path/URL and an input-hash are persisted in venue post meta so the
 * front-end can serve the image directly and so subsequent saves regenerate
 * only when inputs (address, coordinates, tile URL, size) actually change.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GdImage;

/**
 * Class Venue_Static_Map.
 *
 * Singleton responsible for (re)generating the per-venue static-map PNG, keyed
 * by a hash of the generator inputs so unchanged saves are cheap no-ops.
 *
 * @since 1.0.0
 */
class Venue_Static_Map {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Slippy-tile dimension in pixels. CartoDB basemaps use 256×256.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TILE_SIZE = 256;

	/**
	 * Default zoom level for generated static maps.
	 *
	 * Street-level context without zooming so far in that neighborhood
	 * landmarks fall outside the crop.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_ZOOM = 15;

	/**
	 * Default output image width in pixels.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_WIDTH = 600;

	/**
	 * Default output image height in pixels.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_HEIGHT = 400;

	/**
	 * CartoDB Positron tile URL template. `{z}`, `{x}`, `{y}` are substituted.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_TILE_URL = 'https://cartodb-basemaps-a.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png';

	/**
	 * Post meta key under which the static-map descriptor is stored.
	 *
	 * Value shape: `array{ url: string, hash: string }`.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY = 'gatherpress_venue_static_map';

	/**
	 * Subdirectory of `wp-content/uploads` where generated PNGs are written.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const UPLOADS_SUBDIR = 'gatherpress/static-maps';

	/**
	 * Class constructor — wires hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register the save- and delete-side hooks that own the static map lifecycle.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Priority 20 so Venue_Setup::set_geodata (priority 10) has already
		// derived geo_latitude/longitude from the venue information JSON.
		add_action( 'wp_after_insert_post', array( $this, 'maybe_generate' ), 20 );
		add_action( 'registered_post_type', array( $this, 'maybe_register_delete_hook' ) );
	}

	/**
	 * Wire `delete_post_{$type}` → `delete_stored_image` for venue post types.
	 *
	 * Mirrors the registered_post_type pattern used elsewhere so companion
	 * plugins declaring custom venue post types automatically get cleanup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type that was just registered.
	 * @return void
	 */
	public function maybe_register_delete_hook( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			return;
		}

		add_action(
			sprintf( 'delete_post_%s', $post_type ),
			array( $this, 'delete_stored_image' )
		);
	}

	/**
	 * Regenerate the static map when a venue save actually changes the inputs.
	 *
	 * Called on `wp_after_insert_post` for every post; bails early for
	 * revisions, autosaves, and non-venue post types. When the hash of the
	 * relevant inputs is unchanged from the last stored descriptor, the
	 * method no-ops — this is the "set it and forget it" guarantee.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function maybe_generate( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-venue-information' ) ) {
			return;
		}

		$venue = new Venue( $post_id );
		$info  = $venue->get_information();

		if ( null === $this->parse_coord( $info['latitude'] ) ||
			null === $this->parse_coord( $info['longitude'] ) ) {
			// No usable coordinates — leave any previously-generated images
			// alone and bail. The front-end placeholder will surface the
			// "no address" state for the user.
			return;
		}

		// Regenerate every zoom the venue is already known at, so a content
		// change (new address/coords) cascades to all cached variants. For a
		// fresh venue with nothing stored, seed the default zoom.
		$zooms = array_keys( $this->get_all_descriptors( $post_id ) );
		if ( empty( $zooms ) ) {
			$zooms = array( $this->get_zoom() );
		}

		foreach ( $zooms as $zoom ) {
			$this->ensure_descriptor_for_zoom( $post_id, $info, (int) $zoom );
		}
	}

	/**
	 * Delete every stored static-map PNG (at any zoom) and clear the meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The venue post ID.
	 * @return void
	 */
	public function delete_stored_image( int $post_id ): void {
		foreach ( $this->get_all_descriptors( $post_id ) as $descriptor ) {
			$this->delete_file_by_url( $descriptor['url'] );
		}

		delete_post_meta( $post_id, self::META_KEY );
	}

	/**
	 * Resolve the static map URL for any GatherPress post context.
	 *
	 * Accepts either a venue post ID (supports `gatherpress-venue-information`)
	 * or an event post ID (supports `gatherpress-venue`) and returns the URL
	 * of the stored static map for the corresponding venue at the requested
	 * zoom. When the cache doesn't yet have an image for that zoom, the method
	 * generates one synchronously — first render pays the cost, subsequent
	 * renders hit the cache.
	 *
	 * Returns '' when the post isn't venue-related, the venue has no
	 * coordinates, or the on-demand generation failed.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id   Event or venue post ID.
	 * @param string   $post_type The post type of `$post_id`.
	 * @param int|null $zoom      Desired zoom level. Null falls back to the default.
	 * @return string Static map URL, or '' when unavailable.
	 */
	public function get_url_for_post( int $post_id, string $post_type, ?int $zoom = null ): string {
		$venue_post_id = 0;

		if ( post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			$venue_post_id = $post_id;
		} elseif ( post_type_supports( $post_type, 'gatherpress-venue' ) ) {
			$venue_post = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $post_id );

			if ( $venue_post instanceof \WP_Post ) {
				$venue_post_id = $venue_post->ID;
			}
		}

		if ( 0 === $venue_post_id ) {
			return '';
		}

		$info = ( new Venue( $venue_post_id ) )->get_information();

		if ( null === $this->parse_coord( $info['latitude'] ) ||
			null === $this->parse_coord( $info['longitude'] ) ) {
			return '';
		}

		$descriptor = $this->ensure_descriptor_for_zoom( $venue_post_id, $info, $zoom ?? $this->get_zoom() );

		return null === $descriptor ? '' : $descriptor['url'];
	}

	/**
	 * Return the descriptor for the default zoom, or null if nothing is stored.
	 *
	 * Kept for callers that only care about "has this venue been rendered at
	 * all?" — the richer per-zoom map lives behind {@see self::get_all_descriptors()}.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The venue post ID.
	 * @return array{url: string, hash: string}|null
	 */
	public function get_stored_descriptor( int $post_id ): ?array {
		$all = $this->get_all_descriptors( $post_id );

		return $all[ $this->get_zoom() ] ?? null;
	}

	/**
	 * Return every stored descriptor for the venue, keyed by zoom level.
	 *
	 * Silently filters out malformed entries so callers can iterate without
	 * defensive shape checks.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The venue post ID.
	 * @return array<int, array{url: string, hash: string}>
	 */
	public function get_all_descriptors( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$descriptors = array();

		foreach ( $raw as $zoom => $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['url'] ) || empty( $entry['hash'] ) ) {
				continue;
			}

			$descriptors[ (int) $zoom ] = array(
				'url'  => (string) $entry['url'],
				'hash' => (string) $entry['hash'],
			);
		}

		return $descriptors;
	}

	/**
	 * Ensure a descriptor exists for `$zoom` and return it.
	 *
	 * Hits the filesystem cache when the stored hash already matches the
	 * current inputs and the PNG is still on disk; otherwise composites a
	 * fresh image, saves it, updates the meta, and removes the old PNG for
	 * that zoom (if any).
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id Venue post ID.
	 * @param array $info    Parsed venue information.
	 * @param int   $zoom    Zoom level to render at.
	 * @return array{url: string, hash: string}|null
	 */
	protected function ensure_descriptor_for_zoom( int $post_id, array $info, int $zoom ): ?array {
		// Callers (maybe_generate, get_url_for_post) must have validated the
		// coordinates via parse_coord() already — cast directly.
		$latitude  = (float) $info['latitude'];
		$longitude = (float) $info['longitude'];

		$width  = $this->get_width();
		$height = $this->get_height();
		$tiles  = $this->get_tile_url_template();
		$hash   = $this->hash_for( $info, $zoom, $width, $height, $tiles );

		$all      = $this->get_all_descriptors( $post_id );
		$existing = $all[ $zoom ] ?? null;

		if ( null !== $existing && $existing['hash'] === $hash ) {
			$path = $this->url_to_path( $existing['url'] );
			if ( null !== $path && file_exists( $path ) ) {
				return $existing;
			}
		}

		$image = $this->composite_image( $latitude, $longitude, $zoom, $width, $height, $tiles );

		// Downstream of the GD-missing branch in composite_image(); only
		// reached on PHP builds without the GD extension.
		if ( ! $image instanceof GdImage ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		$this->stamp_marker( $image, (int) round( $width / 2 ), (int) round( $height / 2 ) );

		$url = $this->save_image( $image, $post_id, $hash );
		imagedestroy( $image );

		if ( null === $url ) {
			return null;
		}

		if ( null !== $existing && $existing['url'] !== $url ) {
			$this->delete_file_by_url( $existing['url'] );
		}

		$all[ $zoom ]   = array(
			'url'  => $url,
			'hash' => $hash,
		);
		$sanitized_meta = array();
		foreach ( $all as $z => $descriptor ) {
			$sanitized_meta[ (string) $z ] = $descriptor;
		}

		update_post_meta( $post_id, self::META_KEY, $sanitized_meta );

		return $all[ $zoom ];
	}

	/**
	 * Delete a stored PNG by URL, no-op if the URL is out of scope or the
	 * file is already gone.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Stored URL.
	 * @return void
	 */
	protected function delete_file_by_url( string $url ): void {
		$path = $this->url_to_path( $url );

		if ( null !== $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Compute a stable hash from the inputs that determine the rendered PNG.
	 *
	 * Any change to address, coords, zoom, size, or tile provider invalidates
	 * the previous image. Unrelated venue edits (title, excerpt, other meta)
	 * keep the same hash and skip regeneration.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $info   Parsed venue information.
	 * @param int    $zoom   Map zoom level.
	 * @param int    $width  Output width.
	 * @param int    $height Output height.
	 * @param string $tiles  Tile URL template.
	 * @return string SHA-1 hex digest.
	 */
	public function hash_for( array $info, int $zoom, int $width, int $height, string $tiles ): string {
		return sha1(
			implode(
				'|',
				array(
					(string) ( $info['fullAddress'] ?? '' ),
					(string) ( $info['latitude'] ?? '' ),
					(string) ( $info['longitude'] ?? '' ),
					(string) $zoom,
					(string) $width,
					(string) $height,
					$tiles,
				)
			)
		);
	}

	/**
	 * Fetch, decode, and return the raw PNG bytes for a tile at (z, x, y).
	 *
	 * Returns null on any HTTP failure so callers can skip that tile without
	 * aborting the whole composite; the resulting image will simply have a
	 * blank patch where the fetch failed.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $zoom    Tile zoom.
	 * @param int    $x       Tile x coordinate.
	 * @param int    $y       Tile y coordinate.
	 * @param string $tiles   Tile URL template containing `{z}`, `{x}`, `{y}`.
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
	 * Composite the tiles needed to cover the output image centered on (lat, lng).
	 *
	 * Builds a blank canvas of the requested size, fetches every tile whose
	 * bounds intersect the canvas window, and copies each into its correct
	 * pixel offset. Caller is responsible for `imagedestroy()` on the result.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $lat    Latitude in degrees.
	 * @param float  $lng    Longitude in degrees.
	 * @param int    $zoom   Zoom level.
	 * @param int    $width  Output width in pixels.
	 * @param int    $height Output height in pixels.
	 * @param string $tiles  Tile URL template.
	 * @return GdImage|null Composited image, or null when GD is unavailable.
	 */
	public function composite_image(
		float $lat,
		float $lng,
		int $zoom,
		int $width,
		int $height,
		string $tiles
	): ?GdImage {
		// PHP built without the GD extension. Can't simulate in a unit test without making the runtime itself broken.
		if ( ! function_exists( 'imagecreatetruecolor' ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		$venue_world_x = $this->lng_to_world_pixel( $lng, $zoom );
		$venue_world_y = $this->lat_to_world_pixel( $lat, $zoom );

		$left_pixel = (int) round( $venue_world_x - $width / 2 );
		$top_pixel  = (int) round( $venue_world_y - $height / 2 );

		$left_tile   = (int) floor( $left_pixel / self::TILE_SIZE );
		$top_tile    = (int) floor( $top_pixel / self::TILE_SIZE );
		$right_tile  = (int) floor( ( $left_pixel + $width - 1 ) / self::TILE_SIZE );
		$bottom_tile = (int) floor( ( $top_pixel + $height - 1 ) / self::TILE_SIZE );

		$canvas = imagecreatetruecolor( $width, $height );

		// Background: neutral gray so missing tiles blend rather than glaring black.
		$bg = imagecolorallocate( $canvas, 238, 238, 238 );
		imagefilledrectangle( $canvas, 0, 0, $width - 1, $height - 1, $bg );

		for ( $tx = $left_tile; $tx <= $right_tile; $tx++ ) {
			for ( $ty = $top_tile; $ty <= $bottom_tile; $ty++ ) {
				$tile_png = $this->fetch_tile( $zoom, $tx, $ty, $tiles );

				if ( null === $tile_png ) {
					continue;
				}

				$tile = imagecreatefromstring( $tile_png );

				if ( ! $tile instanceof GdImage ) {
					continue;
				}

				$dst_x = $tx * self::TILE_SIZE - $left_pixel;
				$dst_y = $ty * self::TILE_SIZE - $top_pixel;

				imagecopy( $canvas, $tile, $dst_x, $dst_y, 0, 0, self::TILE_SIZE, self::TILE_SIZE );
				imagedestroy( $tile );
			}
		}

		return $canvas;
	}

	/**
	 * Draw a simple pin marker at the given pixel coordinates.
	 *
	 * @since 1.0.0
	 *
	 * @param GdImage $canvas Destination canvas.
	 * @param int     $x      Pixel X position (marker center).
	 * @param int     $y      Pixel Y position (marker center).
	 * @return void
	 */
	public function stamp_marker( GdImage $canvas, int $x, int $y ): void {
		$white = imagecolorallocate( $canvas, 255, 255, 255 );
		$red   = imagecolorallocate( $canvas, 220, 53, 69 );
		$dark  = imagecolorallocate( $canvas, 30, 30, 30 );

		imagefilledellipse( $canvas, $x, $y, 20, 20, $white );
		imagefilledellipse( $canvas, $x, $y, 16, 16, $red );
		imageellipse( $canvas, $x, $y, 16, 16, $dark );
		imagefilledellipse( $canvas, $x, $y, 6, 6, $white );
	}

	/**
	 * Save the composited image to the uploads directory and return its URL.
	 *
	 * @since 1.0.0
	 *
	 * @param GdImage $image   The finished composite.
	 * @param int     $post_id The venue post ID (used in the filename).
	 * @param string  $hash    Input hash (used in the filename).
	 * @return string|null Public URL of the saved file, or null on failure.
	 */
	public function save_image( GdImage $image, int $post_id, string $hash ): ?string {
		$dirs = wp_get_upload_dir();

		if ( ! empty( $dirs['error'] ) ) {
			return null;
		}

		$base_dir = trailingslashit( $dirs['basedir'] ) . self::UPLOADS_SUBDIR;
		$base_url = trailingslashit( $dirs['baseurl'] ) . self::UPLOADS_SUBDIR;

		// uploads/ not writable or disk full — can't reproduce in a unit test without breaking the test filesystem.
		if ( ! wp_mkdir_p( $base_dir ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		$filename = sprintf( '%d-%s.png', $post_id, $hash );
		$path     = trailingslashit( $base_dir ) . $filename;
		$url      = trailingslashit( $base_url ) . $filename;

		// Disk fills up mid-write, or target dir lost write permission between the mkdir check and the imagepng call.
		if ( ! imagepng( $image, $path ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		return $url;
	}

	/**
	 * Convert a stored URL back to its filesystem path for deletion.
	 *
	 * Returns null when the URL doesn't sit inside this plugin's uploads
	 * subdir — a safety check so we never interpret a filter-injected URL
	 * as a path to unlink.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to convert.
	 * @return string|null Absolute filesystem path, or null when out of scope.
	 */
	public function url_to_path( string $url ): ?string {
		$dirs     = wp_get_upload_dir();
		$base_url = trailingslashit( $dirs['baseurl'] ) . self::UPLOADS_SUBDIR . '/';

		if ( 0 !== strpos( $url, $base_url ) ) {
			return null;
		}

		$relative = substr( $url, strlen( $base_url ) );
		$base_dir = trailingslashit( $dirs['basedir'] ) . self::UPLOADS_SUBDIR . '/';

		return $base_dir . $relative;
	}

	/**
	 * Coerce a stored coordinate string to a float, or null for non-numeric input.
	 *
	 * Stored coordinates are strings ("40.7128"); empty string / "null" / text
	 * should not generate a bogus (0, 0) map off the west coast of Africa.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw Raw coordinate from venue information.
	 * @return float|null
	 */
	protected function parse_coord( $raw ): ?float {
		return is_numeric( $raw ) ? (float) $raw : null;
	}

	/**
	 * Zoom level to render at. Filterable.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function get_zoom(): int {
		/**
		 * Filter the zoom level used when rendering the static venue map.
		 *
		 * @since 1.0.0
		 *
		 * @param int $zoom Default zoom level.
		 */
		return (int) apply_filters( 'gatherpress_venue_static_map_zoom', self::DEFAULT_ZOOM );
	}

	/**
	 * Output image width in pixels. Filterable.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function get_width(): int {
		/**
		 * Filter the width of the rendered static venue map.
		 *
		 * @since 1.0.0
		 *
		 * @param int $width Default width in pixels.
		 */
		return (int) apply_filters( 'gatherpress_venue_static_map_width', self::DEFAULT_WIDTH );
	}

	/**
	 * Output image height in pixels. Filterable.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function get_height(): int {
		/**
		 * Filter the height of the rendered static venue map.
		 *
		 * @since 1.0.0
		 *
		 * @param int $height Default height in pixels.
		 */
		return (int) apply_filters( 'gatherpress_venue_static_map_height', self::DEFAULT_HEIGHT );
	}

	/**
	 * Tile URL template used by the static renderer. Filterable.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_tile_url_template(): string {
		/**
		 * Filter the tile URL template used by the static venue map.
		 *
		 * @since 1.0.0
		 *
		 * @param string $template Tile URL with `{z}`, `{x}`, `{y}` placeholders.
		 */
		return (string) apply_filters( 'gatherpress_venue_static_map_tile_url', self::DEFAULT_TILE_URL );
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

		return ( 1 - log( tan( $rad ) + 1 / cos( $rad ) ) / M_PI ) / 2 * self::TILE_SIZE * ( 2 ** $zoom );
	}
}
