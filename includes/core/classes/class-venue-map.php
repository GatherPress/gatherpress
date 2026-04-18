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
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

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
	 * Bounds for the zoom level.
	 *
	 * Matches what the block's editor RangeControl exposes. Enforced
	 * server-side as well so a filter override, hand-edited block attribute,
	 * or bad Settings row can't drive the generator to render a useless
	 * world-view (zoom 0) or crash out on a value the tile provider won't
	 * serve (zoom 30).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const ZOOM_MIN = 1;

	/**
	 * See self::ZOOM_MIN.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const ZOOM_MAX = 20;

	/**
	 * Bounds for the pixel height. Mirrors the block's RangeControl so
	 * out-of-range Settings / filter values can't produce an absurd canvas
	 * (a 10,000-px-tall image would allocate gigabytes of GD memory and
	 * fetch hundreds of tiles).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const HEIGHT_MIN = 100;

	/**
	 * See self::HEIGHT_MIN.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const HEIGHT_MAX = 800;

	/**
	 * Total wall-clock budget (in seconds) for a single composite_image()
	 * call. CartoDB typically serves tiles in well under 500ms, but a slow
	 * tile host, a DNS hiccup, or a bad proxy can chain wp_safe_remote_get()
	 * timeouts together and pin a save for tens of seconds. When the
	 * budget is exceeded mid-composite, remaining tiles are skipped and the
	 * gray background shows through instead — degraded, not hung.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const COMPOSITE_TIME_BUDGET = 10;

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
	 * Meta key for the per-venue filename salt.
	 *
	 * Underscore prefix marks it as a protected WordPress meta key —
	 * not exposed through REST by default and not writable from the
	 * admin Custom Fields box.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SALT_META_KEY = '_gatherpress_venue_map_salt';

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
		// Priority 11 — just after the default-10 batch, so any other
		// wp_after_insert_post callback touching venue meta during the
		// same save has already run. We read `gatherpress_venue_information`
		// directly, so there's no hard dependency on any specific hook, but
		// trailing the default batch is the cheapest guard against future
		// ordering surprises.
		add_action( 'wp_after_insert_post', array( $this, 'maybe_generate' ), 11 );
		add_action( 'registered_post_type', array( $this, 'maybe_register_delete_hook' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'block_type_metadata', array( $this, 'apply_block_attribute_defaults' ) );
	}

	/**
	 * Register REST routes for the venue-map block.
	 *
	 * Today there's a single endpoint that forces the static PNGs for a
	 * venue to regenerate — used by the "Regenerate Map" button in the
	 * block editor when a tile provider changes or a render gets out of
	 * sync with the venue's current inputs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/venue/(?P<id>\d+)/regenerate-map',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_regenerate' ),
				'permission_callback' => static function ( WP_REST_Request $request ): bool {
					$post_id = (int) $request['id'];

					// Permission scope is the venue post itself — anyone who can
					// edit the venue can force its map to regenerate. Admins with
					// edit_others_posts go through the same check.
					return current_user_can( 'edit_post', $post_id );
				},
				'args'                => array(
					'id'     => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $value ): bool {
							$post_id = (int) $value;

							return $post_id > 0
								&& post_type_supports(
									(string) get_post_type( $post_id ),
									'gatherpress-venue-information'
								);
						},
					),
					// Optional: the combo the client is currently displaying. If
					// provided, that combo is added to the regenerate list so a
					// "Generate" click from the block placeholder produces a PNG
					// for the active (zoom, height) even when that combo has never
					// been rendered before.
					'zoom'   => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'height' => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
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

		// Per-attribute validators so a never-written Settings row (empty
		// strings, zero ints) or a value that's since become invalid (e.g.
		// an out-of-range zoom left over from before the clamp was added)
		// falls through to the block.json default rather than stamping on
		// garbage. A blanket `'' === $v || 0 === $v` guard was too loose —
		// it would have accepted e.g. `renderMode = '0'`. All closures are
		// static because none of them capture `$this`.
		$validators = array(
			'renderMode' => static function ( $value ): bool {
				return in_array( $value, array( 'interactive', 'static' ), true );
			},
			'zoom'       => static function ( $value ): bool {
				return is_int( $value )
					&& $value >= self::ZOOM_MIN
					&& $value <= self::ZOOM_MAX;
			},
			'height'     => static function ( $value ): bool {
				return is_int( $value )
					&& $value >= self::HEIGHT_MIN
					&& $value <= self::HEIGHT_MAX;
			},
			'type'       => static function ( $value ): bool {
				return in_array(
					$value,
					array( 'roadmap', 'satellite', 'hybrid', 'terrain' ),
					true
				);
			},
		);

		// Iterate $validators (the authoritative list) rather than $defaults,
		// so adding a new key to $defaults without a matching validator just
		// falls through instead of triggering an undefined-offset warning.
		// The reverse case (validator without default) is guarded by the
		// static shape of $defaults above — PHPStan treats the array literal
		// as a fixed key set.
		foreach ( $validators as $attr => $validator ) {
			if ( ! $validator( $defaults[ $attr ] ) ) {
				continue;
			}

			if ( isset( $metadata['attributes'][ $attr ] ) ) {
				$metadata['attributes'][ $attr ]['default'] = $defaults[ $attr ];
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
			// No usable coordinates — the address was edited to something
			// un-geocodable, or cleared entirely. Purge any previously
			// generated PNG files so stale images don't keep serving on
			// the front end; the placeholder will surface the "no address"
			// state until geocoding succeeds again.
			$this->delete_stored_image( $post_id );
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
	 * Force-regenerate the static maps for a venue.
	 *
	 * Clears every cached descriptor + PNG, then runs the normal
	 * `maybe_generate()` flow to recreate images for each combo the venue
	 * was previously cached at. When the caller supplies an extra
	 * `(zoom, height)` — typically the combo the block editor is currently
	 * displaying — that combo is added to the list so a "Generate" click
	 * from the placeholder produces a PNG for it even if the combo has
	 * never been cached before. For a venue that has no cached combos
	 * and no caller-supplied one, the site-default combo seeds the run.
	 *
	 * Returns the fresh descriptor map so the caller — typically the block
	 * editor's "Regenerate Map" REST endpoint — can hand the new URLs
	 * straight back to the client without a second DB round-trip.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id      The venue post ID.
	 * @param int|null $extra_zoom   Optional extra zoom to include.
	 * @param int|null $extra_height Optional extra height to include.
	 * @return array<string, array{url: string, hash: string, zoom: int, height: int}>
	 */
	public function regenerate( int $post_id, ?int $extra_zoom = null, ?int $extra_height = null ): array {
		// Cache the combos we want to rebuild before wiping meta —
		// delete_stored_image() clears META_KEY, which is where
		// get_cached_combos() reads from.
		$combos = $this->get_cached_combos( $post_id );

		// Merge the caller-supplied combo in, dedup by combo_key.
		if ( null !== $extra_zoom && null !== $extra_height ) {
			$combos[] = array(
				'zoom'   => $extra_zoom,
				'height' => $extra_height,
			);
		}

		$seen   = array();
		$unique = array();
		foreach ( $combos as $combo ) {
			$key = $this->combo_key(
				$this->clamp_zoom( (int) $combo['zoom'] ),
				$this->clamp_height( (int) $combo['height'] )
			);

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$unique[]     = $combo;
		}
		$combos = $unique;

		$this->delete_stored_image( $post_id );

		if ( empty( $combos ) ) {
			// Seed the site-default combo so a never-rendered venue still
			// ends up with a PNG after an explicit regenerate click.
			$combos = array(
				array(
					'zoom'   => $this->get_zoom(),
					'height' => $this->get_height(),
				),
			);
		}

		$venue = new Venue( $post_id );
		$info  = $venue->get_information();

		if ( null === $this->parse_coord( $info['latitude'] ) ||
			null === $this->parse_coord( $info['longitude'] ) ) {
			return array();
		}

		foreach ( $combos as $combo ) {
			$this->ensure_descriptor_for_combo(
				$post_id,
				$info,
				$combo['zoom'],
				$combo['height']
			);
		}

		return $this->get_all_descriptors( $post_id );
	}

	/**
	 * REST handler for `POST /venue/{id}/regenerate-map`.
	 *
	 * Returns the fresh descriptor map on success. When the venue has no
	 * usable coordinates yet (address hasn't geocoded), returns an empty
	 * descriptors array and a structured `reason` so the client can show
	 * the right placeholder instead of a generic error.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response
	 */
	public function rest_regenerate( WP_REST_Request $request ): WP_REST_Response {
		nocache_headers();

		$post_id     = (int) $request['id'];
		$venue       = new Venue( $post_id );
		$info        = $venue->get_information();
		$latitude    = $this->parse_coord( $info['latitude'] );
		$longitude   = $this->parse_coord( $info['longitude'] );
		$has_address = '' !== trim( (string) $info['fullAddress'] );

		if ( ! $has_address || null === $latitude || null === $longitude ) {
			return new WP_REST_Response(
				array(
					'descriptors' => (object) array(),
					'reason'      => $has_address ? 'awaiting_geocode' : 'no_address',
				),
				200
			);
		}

		// Pass the block's current combo through so its PNG is generated
		// even when the venue has never been rendered at those dimensions.
		$raw_zoom     = $request['zoom'] ?? null;
		$raw_height   = $request['height'] ?? null;
		$extra_zoom   = ( null !== $raw_zoom && (int) $raw_zoom > 0 ) ? (int) $raw_zoom : null;
		$extra_height = ( null !== $raw_height && (int) $raw_height > 0 ) ? (int) $raw_height : null;

		$descriptors = $this->regenerate( $post_id, $extra_zoom, $extra_height );

		return new WP_REST_Response(
			array(
				'descriptors' => empty( $descriptors ) ? (object) array() : $descriptors,
				'reason'      => '',
			),
			200
		);
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
	 * Convenience for the common "has this venue been rendered at the site
	 * defaults yet?" question — production render paths resolve a specific
	 * combo through {@see self::get_url_for_post()}, and code iterating
	 * every cached variant should use {@see self::get_all_descriptors()}.
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
	 * Filters out malformed entries so callers can iterate without defensive
	 * shape checks. The read path itself does not persist the cleaned map —
	 * writing on every front-end render would invalidate the post-meta cache
	 * and churn the DB. Dropped entries are removed opportunistically the
	 * next time a descriptor is saved, because `ensure_descriptor_for_combo()`
	 * reuses the filtered map as the basis for its `update_post_meta()` call.
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

		// Clamp caller-provided values before they influence the cache key
		// or the canvas. Block attributes come from the editor already
		// within range, but a hand-edited block, a filter that mutates the
		// attribute, or bad meta could sneak an out-of-range value in.
		$zoom   = $this->clamp_zoom( $zoom );
		$height = $this->clamp_height( $height );

		$tiles = $this->get_tile_url_template();
		$hash  = $this->hash_for( $post_id, $info, $zoom, $height, $tiles );
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

		// Verify the write landed. update_post_meta() returns an ambiguous
		// false ("not changed" vs. "write failed"), so read back and check
		// the URL we just saved is what ended up in the meta. If the write
		// was dropped — object-cache outage, an unexpected filter, a foreign
		// process racing us — unlink the orphan PNG so we don't strand the
		// file on disk.
		$stored = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $stored )
			|| ! isset( $stored[ $key ]['url'] )
			|| $stored[ $key ]['url'] !== $url
		) {
			$this->delete_file_by_url( $url );
			return null;
		}

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
	 * A per-venue salt is mixed into the digest so filenames in the public
	 * uploads dir aren't predictable from the venue's address alone — otherwise
	 * anyone who guesses a draft venue's ID and street address could fetch
	 * its map PNG before the venue is published.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Venue post ID (used to look up the per-venue salt).
	 * @param array  $info    Parsed venue information.
	 * @param int    $zoom    Map zoom level.
	 * @param int    $height  Output height.
	 * @param string $tiles   Tile URL template.
	 * @return string MD5 hex digest.
	 */
	public function hash_for( int $post_id, array $info, int $zoom, int $height, string $tiles ): string {
		// md5() here is a non-cryptographic cache-key discriminator, matching class-geocoding.php.
		return md5( // NOSONAR.
			implode(
				'|',
				array(
					$this->salt_for( $post_id ),
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
	 * Return the per-venue filename salt, generating one on first access.
	 *
	 * The salt is a fixed per-post random string; it never rotates so cached
	 * filenames stay stable across saves. Deleting the venue removes the meta
	 * (along with every other post meta key) as usual.
	 *
	 * **Existing-venue migration:** venues that were rendered before the salt
	 * was introduced will get a salt generated here on first access; the hash
	 * — and therefore the PNG filename — changes, which causes
	 * `ensure_descriptor_for_combo()` to regenerate the image and unlink the
	 * pre-salt PNG via its existing `$existing['url'] !== $url` cleanup path.
	 * No backfill step is required.
	 *
	 * Two concurrent first-touch requests are reconciled with an `add`-style
	 * unique-meta insert: whichever request writes first wins, and the other
	 * re-reads the now-populated value instead of racing a second generation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Venue post ID.
	 * @return string 32-character salt.
	 */
	protected function salt_for( int $post_id ): string {
		$salt = (string) get_post_meta( $post_id, self::SALT_META_KEY, true );

		if ( '' === $salt ) {
			$candidate = wp_generate_password( 32, false );

			// add_post_meta() with $unique = true returns false when another
			// request already populated the row between our read and write.
			// In that case, re-read to pick up the winner's value so every
			// caller agrees on the salt.
			if ( false !== add_post_meta( $post_id, self::SALT_META_KEY, $candidate, true ) ) {
				$salt = $candidate;
			} else {
				$salt = (string) get_post_meta( $post_id, self::SALT_META_KEY, true );
			}
		}

		return $salt;
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

		/**
		 * Filter the wall-clock budget (in seconds) for a single
		 * composite_image() call. When the deadline is exceeded mid-loop,
		 * remaining tiles are skipped and the gray background shows
		 * through. Accepts int or float.
		 *
		 * @since 1.0.0
		 *
		 * @param float $budget Default budget from COMPOSITE_TIME_BUDGET.
		 */
		$budget   = (float) apply_filters(
			'gatherpress_venue_map_composite_time_budget',
			self::COMPOSITE_TIME_BUDGET
		);
		$deadline = microtime( true ) + $budget;

		for ( $tx = $left_tile; $tx <= $right_tile; $tx++ ) {
			for ( $ty = $top_tile; $ty <= $bottom_tile; $ty++ ) {
				// Budget guard: once the total wall-clock time for this
				// composite exceeds COMPOSITE_TIME_BUDGET, stop fetching
				// and let the gray background show through for any tiles
				// we haven't filled in yet. Bounds the worst-case stall
				// from a slow tile host.
				if ( microtime( true ) >= $deadline ) {
					break 2;
				}

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

		// Restrict the relative portion to filenames this class actually
		// generates: `{post_id}-{md5-hex}.png`. The prefix check above only
		// guards the URL origin — without this allow-list a value like
		// `…/static-maps/../../wp-config.php`, if it ever slipped into the
		// meta (direct update_post_meta, import, a future filter), would
		// concatenate through to a path outside the uploads subdir and
		// hand `wp_delete_file()` a target it shouldn't touch.
		if ( ! preg_match( '#\A\d+-[a-f0-9]{32}\.png\z#', $relative ) ) {
			return null;
		}

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
		$zoom = (int) apply_filters( 'gatherpress_venue_map_zoom', $default );

		return $this->clamp_zoom( $zoom );
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
		$height = (int) apply_filters( 'gatherpress_venue_map_height', $default );

		return $this->clamp_height( $height );
	}

	/**
	 * Clamp a zoom level to the supported range.
	 *
	 * @since 1.0.0
	 *
	 * @param int $zoom Raw zoom value.
	 * @return int
	 */
	protected function clamp_zoom( int $zoom ): int {
		return max( self::ZOOM_MIN, min( self::ZOOM_MAX, $zoom ) );
	}

	/**
	 * Clamp a pixel height to the supported range.
	 *
	 * @since 1.0.0
	 *
	 * @param int $height Raw height value.
	 * @return int
	 */
	protected function clamp_height( int $height ): int {
		return max( self::HEIGHT_MIN, min( self::HEIGHT_MAX, $height ) );
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
