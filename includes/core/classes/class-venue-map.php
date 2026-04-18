<?php
/**
 * Home for venue map server-side concerns.
 *
 * Today this singleton owns the pre-rendered static-map PNG pipeline:
 * fetching a small set of CartoDB basemap tiles around the venue's
 * coordinates, compositing them into a single PNG with a marker stamped at
 * the venue's exact position, and storing the result under
 * `wp-content/uploads/gatherpress/static-maps/`. The file URL and an
 * input-hash are persisted (per zoom+height combo) in venue post meta so
 * the front-end can serve the image directly and so subsequent saves
 * regenerate only when inputs (address, coordinates, tile URL, size)
 * actually change.
 *
 * The class is named for its broader role — venue-map — so future
 * map-related additions (REST endpoint for the editor preview, interactive
 * map server-side bits, async regeneration, etc.) have a natural home.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Venue_Map.
 *
 * Singleton hosting the venue map server-side pipeline. Currently owns the
 * static PNG cache keyed by (zoom, height) combo; structured so that
 * additional map-related methods (REST, async jobs, etc.) can land here
 * without proliferating classes.
 *
 * @since 1.0.0
 */
class Venue_Map {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Default `renderMode` attribute for the venue-map block.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_RENDER_MODE = 'interactive';

	/**
	 * Default zoom level. Used by the generator and the block.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_ZOOM = 18;

	/**
	 * Default height (in pixels). Used by the generator and the block.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const DEFAULT_HEIGHT = 300;

	/**
	 * Default `type` attribute for the venue-map block.
	 *
	 * Google Maps–specific (roadmap / satellite / hybrid / terrain). Ignored by
	 * the static renderer and by Leaflet/OSM.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_MAP_TYPE = 'roadmap';

	/**
	 * Slippy-tile dimension in pixels. CartoDB basemaps use 256×256.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TILE_SIZE = 256;

	/**
	 * PNG aspect ratio (width ÷ height).
	 *
	 * The block exposes height but not width (blocks render 100% of their
	 * container). We still need *some* pixel width for the PNG, so the
	 * generator derives it from the block's chosen height at this ratio —
	 * 2:1 by default, which gives a comfortably landscape map without the
	 * venue marker feeling tight against the edges.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	const IMAGE_ASPECT_RATIO = 2.0;

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
	 * Subdirectory of `wp-content/uploads` where generated PNG files are written.
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
		add_filter( 'block_type_metadata', array( $this, 'apply_block_attribute_defaults' ) );
	}

	/**
	 * Override the venue-map block's attribute defaults with user-chosen values
	 * from Settings → Venues → Maps.
	 *
	 * Runs once per block registration. Because Gutenberg only consults the
	 * defaults when an attribute isn't present on a saved block, this affects
	 * newly inserted blocks and preserves explicit customizations on existing
	 * ones — changing the setting won't retroactively rewrite old content.
	 *
	 * @since 1.0.0
	 *
	 * @param array $metadata Parsed `block.json` metadata for the block being registered.
	 * @return array The metadata array, potentially with updated attribute defaults.
	 */
	public function apply_block_attribute_defaults( array $metadata ): array {
		if ( 'gatherpress/venue-map' !== ( $metadata['name'] ?? '' ) ) {
			return $metadata;
		}

		$settings = Settings::get_instance();
		$defaults = array(
			'renderMode' => (string) $settings->get( 'venue_map_default_render_mode' ),
			'zoom'       => (int) $settings->get( 'venue_map_default_zoom' ),
			'height'     => (int) $settings->get( 'venue_map_default_height' ),
			'type'       => (string) $settings->get( 'venue_map_default_type' ),
		);

		foreach ( $defaults as $attr => $value ) {
			// Guard against a Settings row that's never been written — keep
			// the block.json default rather than stamping on an empty string
			// or zero that the UI would silently accept.
			if ( '' === $value || 0 === $value ) {
				continue;
			}

			if ( isset( $metadata['attributes'][ $attr ] ) ) {
				$metadata['attributes'][ $attr ]['default'] = $value;
			}
		}

		return $metadata;
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

		// Regenerate every (zoom, height) combo the venue is already cached
		// at so a content change (new address/coords) cascades to all cached
		// variants. For a fresh venue with nothing stored, seed the default
		// combo that newly-inserted venue-map blocks will actually request —
		// otherwise the editor falls back to the interactive preview on the
		// very first save because the default cache entry is missing.
		$combos = $this->get_cached_combos( $post_id );
		if ( empty( $combos ) ) {
			$combos = array(
				array(
					'zoom'   => $this->get_zoom(),
					'height' => $this->get_height(),
				),
			);
		}

		foreach ( $combos as $combo ) {
			$this->ensure_descriptor_for_combo( $post_id, $info, $combo['zoom'], $combo['height'] );
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
	 * zoom and height. When the cache doesn't yet have an image for that
	 * combo, the method generates one synchronously — first render pays the
	 * cost, subsequent renders hit the cache.
	 *
	 * Returns '' when the post isn't venue-related, the venue has no
	 * coordinates, or the on-demand generation failed.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id   Event or venue post ID.
	 * @param string   $post_type The post type of `$post_id`.
	 * @param int|null $zoom      Desired zoom level. Null falls back to the default.
	 * @param int|null $height    Desired pixel height. Null falls back to the default.
	 * @return string Static map URL, or '' when unavailable.
	 */
	public function get_url_for_post(
		int $post_id,
		string $post_type,
		?int $zoom = null,
		?int $height = null
	): string {
		$venue_post_id = 0;

		if ( post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			$venue_post_id = $post_id;
		} elseif ( post_type_supports( $post_type, 'gatherpress-venue' ) ) {
			$venue_post = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $post_id );

			if ( $venue_post instanceof WP_Post ) {
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

		$descriptor = $this->ensure_descriptor_for_combo(
			$venue_post_id,
			$info,
			$zoom ?? $this->get_zoom(),
			$height ?? $this->get_height()
		);

		return null === $descriptor ? '' : $descriptor['url'];
	}

	/**
	 * Return the descriptor for the default (zoom, height) combo, or null if
	 * nothing is stored.
	 *
	 * Kept for callers that only care about "has this venue been rendered at
	 * all?" — the richer per-combo map lives behind
	 * {@see self::get_all_descriptors()}.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The venue post ID.
	 * @return array{url: string, hash: string, zoom: int, height: int}|null
	 */
	public function get_stored_descriptor( int $post_id ): ?array {
		$all = $this->get_all_descriptors( $post_id );

		return $all[ $this->combo_key( $this->get_zoom(), $this->get_height() ) ] ?? null;
	}

	/**
	 * Return every stored descriptor for the venue, keyed by `{zoom}x{height}`.
	 *
	 * Silently filters out malformed entries so callers can iterate without
	 * defensive shape checks.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The venue post ID.
	 * @return array<string, array{url: string, hash: string, zoom: int, height: int}>
	 */
	public function get_all_descriptors( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$descriptors = array();

		foreach ( $raw as $key => $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['url'] ) || empty( $entry['hash'] ) ) {
				continue;
			}

			$zoom   = isset( $entry['zoom'] ) ? (int) $entry['zoom'] : 0;
			$height = isset( $entry['height'] ) ? (int) $entry['height'] : 0;

			// Without a zoom/height on the entry there's no way to know which
			// block configuration the file belongs to — skip rather than guess.
			if ( $zoom <= 0 || $height <= 0 ) {
				continue;
			}

			$descriptors[ (string) $key ] = array(
				'url'    => (string) $entry['url'],
				'hash'   => (string) $entry['hash'],
				'zoom'   => $zoom,
				'height' => $height,
			);
		}

		return $descriptors;
	}

	/**
	 * Parse stored meta into the list of (zoom, height) combos already cached.
	 *
	 * Used by {@see self::maybe_generate()} to cascade content changes (new
	 * address, new coordinates) across every variant a venue has ever been
	 * rendered at, so blocks pointing at a non-default combo aren't stranded
	 * with a stale image.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Venue post ID.
	 * @return array<int, array{zoom: int, height: int}>
	 */
	public function get_cached_combos( int $post_id ): array {
		$combos = array();

		foreach ( $this->get_all_descriptors( $post_id ) as $descriptor ) {
			$combos[] = array(
				'zoom'   => $descriptor['zoom'],
				'height' => $descriptor['height'],
			);
		}

		return $combos;
	}

	/**
	 * Build the meta-storage key for a (zoom, height) combo.
	 *
	 * @since 1.0.0
	 *
	 * @param int $zoom   Zoom level.
	 * @param int $height Pixel height.
	 * @return string
	 */
	protected function combo_key( int $zoom, int $height ): string {
		return sprintf( '%dx%d', $zoom, $height );
	}

	/**
	 * Ensure a descriptor exists for the `($zoom, $height)` combo and return it.
	 *
	 * Hits the filesystem cache when the stored hash already matches the
	 * current inputs and the PNG is still on disk; otherwise composites a
	 * fresh image, saves it, updates the meta, and removes the old PNG for
	 * that combo (if any).
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id Venue post ID.
	 * @param array $info    Parsed venue information.
	 * @param int   $zoom    Zoom level to render at.
	 * @param int   $height  Pixel height of the PNG. Width is derived via IMAGE_ASPECT_RATIO.
	 * @return array{url: string, hash: string, zoom: int, height: int}|null
	 */
	protected function ensure_descriptor_for_combo( int $post_id, array $info, int $zoom, int $height ): ?array {
		// Callers (maybe_generate, get_url_for_post) must have validated the
		// coordinates via parse_coord() already — cast directly.
		$latitude  = (float) $info['latitude'];
		$longitude = (float) $info['longitude'];

		$tiles = $this->get_tile_url_template();
		$hash  = $this->hash_for( $info, $zoom, $height, $tiles );
		$key   = $this->combo_key( $zoom, $height );

		$all      = $this->get_all_descriptors( $post_id );
		$existing = $all[ $key ] ?? null;

		if ( null !== $existing && $existing['hash'] === $hash ) {
			$path = $this->url_to_path( $existing['url'] );
			if ( null !== $path && file_exists( $path ) ) {
				return $existing;
			}
		}

		$image = $this->composite_image( $latitude, $longitude, $zoom, $height, $tiles );

		// Downstream of the GD-missing branch in composite_image(); only
		// reached on PHP builds without the GD extension.
		if ( null === $image ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		$width = (int) round( $height * self::IMAGE_ASPECT_RATIO );
		$this->stamp_marker( $image, (int) round( $width / 2 ), (int) round( $height / 2 ) );

		$url = $this->save_image( $image, $post_id, $hash );
		imagedestroy( $image );

		if ( null === $url ) {
			return null;
		}

		if ( null !== $existing && $existing['url'] !== $url ) {
			$this->delete_file_by_url( $existing['url'] );
		}

		$all[ $key ] = array(
			'url'    => $url,
			'hash'   => $hash,
			'zoom'   => $zoom,
			'height' => $height,
		);

		update_post_meta( $post_id, self::META_KEY, $all );

		return $all[ $key ];
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
	 * Any change to address, coords, zoom, height, or tile provider invalidates
	 * the previous image. Width is fully derived from height via
	 * {@see self::IMAGE_ASPECT_RATIO} so it doesn't need to go into the hash.
	 * Unrelated venue edits (title, excerpt, other meta) keep the same hash
	 * and skip regeneration.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $info   Parsed venue information.
	 * @param int    $zoom   Map zoom level.
	 * @param int    $height Output height.
	 * @param string $tiles  Tile URL template.
	 * @return string MD5 hex digest.
	 */
	public function hash_for( array $info, int $zoom, int $height, string $tiles ): string {
		// md5() here is a non-cryptographic cache-key discriminator, matching class-geocoding.php.
		return md5( // NOSONAR.
			implode(
				'|',
				array(
					(string) ( $info['fullAddress'] ?? '' ),
					(string) ( $info['latitude'] ?? '' ),
					(string) ( $info['longitude'] ?? '' ),
					(string) $zoom,
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
	 * @param int    $zoom   Zoom level (same zoom Leaflet would use for the same CSS viewport).
	 * @param int    $height Output pixel height. Width is derived via IMAGE_ASPECT_RATIO.
	 * @param string $tiles  Tile URL template.
	 * @return \GdImage|resource|null Composited image, or null when GD is unavailable.
	 *                                GdImage on PHP 8+, resource on PHP 7.4.
	 */
	public function composite_image(
		float $lat,
		float $lng,
		int $zoom,
		int $height,
		string $tiles
	) {
		// PHP built without the GD extension. Can't simulate in a unit test without making the runtime itself broken.
		if ( ! function_exists( 'imagecreatetruecolor' ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		$width = (int) round( $height * self::IMAGE_ASPECT_RATIO );

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

				if ( false === $tile ) {
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
	 * @param \GdImage|resource $canvas Destination canvas (GdImage on PHP 8+, resource on PHP 7.4).
	 * @param int               $x      Pixel X position (marker center).
	 * @param int               $y      Pixel Y position (marker center).
	 * @return void
	 */
	public function stamp_marker( $canvas, int $x, int $y ): void {
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
	 * @param \GdImage|resource $image   The finished composite (GdImage on PHP 8+, resource on PHP 7.4).
	 * @param int               $post_id The venue post ID (used in the filename).
	 * @param string            $hash    Input hash (used in the filename).
	 * @return string|null Public URL of the saved file, or null on failure.
	 */
	public function save_image( $image, int $post_id, string $hash ): ?string {
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
	 * Zoom level used by the generator and by the block.
	 *
	 * Prefers the site-wide setting; falls back to {@see self::DEFAULT_ZOOM}
	 * when the setting is unset or zero. Result is piped through the
	 * `gatherpress_venue_map_zoom` filter so code-level overrides
	 * (themes, site-specific plugins) can still take precedence.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function get_zoom(): int {
		$setting = (int) Settings::get_instance()->get( 'venue_map_default_zoom' );
		$default = $setting > 0 ? $setting : self::DEFAULT_ZOOM;

		/**
		 * Filter the zoom level used when rendering the static venue map.
		 *
		 * @since 1.0.0
		 *
		 * @param int $zoom Default zoom level.
		 */
		return (int) apply_filters( 'gatherpress_venue_map_zoom', $default );
	}

	/**
	 * Height (in pixels) used by the generator and by the block.
	 *
	 * Mirrors {@see self::get_zoom()} — Settings value wins, falls back to
	 * {@see self::DEFAULT_HEIGHT}, then runs through
	 * `gatherpress_venue_map_height` for code-level overrides. Because the
	 * PNG is rendered at exactly this height (no oversampling), the generator
	 * and the block see the same value and Leaflet's zoom matches the
	 * static map's zoom visually.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	protected function get_height(): int {
		$setting = (int) Settings::get_instance()->get( 'venue_map_default_height' );
		$default = $setting > 0 ? $setting : self::DEFAULT_HEIGHT;

		/**
		 * Filter the height used when rendering the static venue map.
		 *
		 * @since 1.0.0
		 *
		 * @param int $height Default height in pixels.
		 */
		return (int) apply_filters( 'gatherpress_venue_map_height', $default );
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
		return (string) apply_filters( 'gatherpress_venue_map_tile_url', self::DEFAULT_TILE_URL );
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
