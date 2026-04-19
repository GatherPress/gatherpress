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
	const HEIGHT_MAX = 4000;

	/**
	 * Bounds for the pixel width. Mirror of HEIGHT_MIN / HEIGHT_MAX but
	 * scaled to the 2:1 default ratio — a venue map that's 800 tall can
	 * be up to 1600 wide. The block exposes both dimensions so users can
	 * pick any concrete width, but the generator still refuses values
	 * that would allocate gigabytes of GD memory or fetch hundreds of
	 * tiles.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const WIDTH_MIN = 100;

	/**
	 * See self::WIDTH_MIN.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const WIDTH_MAX = 4000;

	/**
	 * Default aspect ratio string used when the block's `aspectRatio`
	 * attribute is empty or unparsable. Format matches the CSS
	 * `aspect-ratio` property so the same value can drive the server-side
	 * width derivation and the client-side CSS on the interactive wrapper.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DEFAULT_ASPECT_RATIO = '2/1';

	/**
	 * Anchored regex matching a CSS-style aspect-ratio value (e.g. `16/9`
	 * or `4:3`). Used by both the REST `aspect_ratio` validator and the
	 * render template so the two can never drift apart. Requires at least
	 * one non-zero digit on each side so a degenerate `0/9` / `9/0` — which
	 * CSS would treat as `auto` — can't slip through.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ASPECT_RATIO_PATTERN = '#\A\s*[1-9][0-9]*\s*[/:]\s*[1-9][0-9]*\s*\z#';

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
	 * Today there's a single endpoint that forces the static PNG files for a
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
					'id'           => array(
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
					// for the active (zoom, width, height) combo even when it has
					// never been rendered before. `width` and `height` may be 0
					// ("auto") — the aspect-ratio hint then drives derivation.
					'zoom'         => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'width'        => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'height'       => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'aspect_ratio' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						// Accept empty ("use server default") or an
						// integer-separated pair matching the block's
						// aspect-ratio format. parse_aspect_ratio()
						// downstream still has to handle odd inputs for
						// the non-REST call sites, but surfacing a 400
						// here is cheaper than silently falling back.
						'validate_callback' => static function ( $value ): bool {
							if ( '' === $value || null === $value ) {
								return true;
							}
							return (bool) preg_match(
								self::ASPECT_RATIO_PATTERN,
								(string) $value
							);
						},
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

		// Width and height keep empty as a distinct "not set — fall through
		// to the block.json default" state; an explicit 0 still stamps as
		// "auto", so the validator below must reject null but accept 0.
		$raw_width  = $settings->get( 'venue_map_default_width' );
		$raw_height = $settings->get( 'venue_map_default_height' );

		$defaults = array(
			'renderMode'  => (string) $settings->get( 'venue_map_default_render_mode' ),
			'zoom'        => (int) $settings->get( 'venue_map_default_zoom' ),
			'width'       => '' === $raw_width ? null : (int) $raw_width,
			'height'      => '' === $raw_height ? null : (int) $raw_height,
			'aspectRatio' => (string) $settings->get( 'venue_map_default_aspect_ratio' ),
			'type'        => (string) $settings->get( 'venue_map_default_type' ),
		);

		// Per-attribute validators so a never-written Settings row (empty
		// strings, zero ints) or a value that's since become invalid (e.g.
		// an out-of-range zoom left over from before the clamp was added)
		// falls through to the block.json default rather than stamping on
		// garbage. A blanket `'' === $v || 0 === $v` guard was too loose —
		// it would have accepted e.g. `renderMode = '0'`. All closures are
		// static because none of them capture `$this`.
		$validators = array(
			'renderMode'  => static function ( $value ): bool {
				return in_array( $value, array( 'interactive', 'static' ), true );
			},
			'zoom'        => static function ( $value ): bool {
				return is_int( $value )
					&& $value >= self::ZOOM_MIN
					&& $value <= self::ZOOM_MAX;
			},
			'width'       => static function ( $value ): bool {
				// 0 means "auto" and is a valid default value.
				return is_int( $value )
					&& $value >= 0
					&& $value <= self::WIDTH_MAX;
			},
			'height'      => static function ( $value ): bool {
				// 0 means "auto" and is a valid default value.
				return is_int( $value )
					&& $value >= 0
					&& $value <= self::HEIGHT_MAX;
			},
			'aspectRatio' => function ( $value ): bool {
				return is_string( $value )
					&& null !== $this->parse_aspect_ratio( $value );
			},
			'type'        => static function ( $value ): bool {
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

		// Regenerate every (zoom, width, height) combo the venue is already
		// cached at so a content change (new address/coords) cascades to
		// all cached variants. For a fresh venue with nothing stored, seed
		// the default combo that newly-inserted venue-map blocks will
		// actually request — otherwise the editor falls back to the
		// interactive preview on the very first save because the default
		// cache entry is missing.
		$combos = $this->get_cached_combos( $post_id );
		if ( empty( $combos ) ) {
			$default = $this->resolve_dimensions(
				0,
				$this->get_height(),
				self::DEFAULT_ASPECT_RATIO
			);
			$combos  = array(
				array(
					'zoom'   => $this->get_zoom(),
					'width'  => $default['width'],
					'height' => $default['height'],
				),
			);
		}

		foreach ( $combos as $combo ) {
			$this->ensure_descriptor_for_combo(
				$post_id,
				$info,
				$combo['zoom'],
				$combo['width'],
				$combo['height']
			);
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
		// Filenames are derived from address + dims, so the same PNG on disk
		// may be shared with other venues at the same address. Clearing the
		// meta is enough — a follow-up GC pass can sweep truly orphaned
		// files when no venue's descriptor points at them any longer.
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
	 * @param int      $post_id            The venue post ID.
	 * @param int|null $extra_zoom         Optional extra zoom to include.
	 * @param int|null $extra_width        Optional extra width (0 = auto).
	 * @param int|null $extra_height       Optional extra height (0 = auto).
	 * @param string   $extra_aspect_ratio Optional aspect ratio hint for the extra combo.
	 * @return array<string, array{url: string, hash: string, zoom: int, width: int, height: int}>
	 */
	public function regenerate(
		int $post_id,
		?int $extra_zoom = null,
		?int $extra_width = null,
		?int $extra_height = null,
		string $extra_aspect_ratio = ''
	): array {
		// Cache the combos we want to rebuild before wiping meta —
		// delete_stored_image() clears META_KEY, which is where
		// get_cached_combos() reads from.
		$combos = $this->get_cached_combos( $post_id );

		// Merge the caller-supplied combo in, resolving dims when either
		// width or height is left as "auto".
		if ( null !== $extra_zoom ) {
			$resolved = $this->resolve_dimensions(
				(int) ( $extra_width ?? 0 ),
				(int) ( $extra_height ?? 0 ),
				$extra_aspect_ratio
			);
			$combos[] = array(
				'zoom'   => (int) $extra_zoom,
				'width'  => $resolved['width'],
				'height' => $resolved['height'],
			);
		}

		$seen   = array();
		$unique = array();
		foreach ( $combos as $combo ) {
			$key = $this->combo_key(
				$this->clamp_zoom( (int) $combo['zoom'] ),
				$this->clamp_width( (int) $combo['width'] ),
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
			$default = $this->resolve_dimensions(
				0,
				$this->get_height(),
				self::DEFAULT_ASPECT_RATIO
			);
			$combos  = array(
				array(
					'zoom'   => $this->get_zoom(),
					'width'  => $default['width'],
					'height' => $default['height'],
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
				$combo['width'],
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
		// `width` and `height` may be 0 / omitted (meaning "auto") — the
		// aspect-ratio hint then drives whichever dimension is missing.
		$raw_zoom     = $request['zoom'] ?? null;
		$raw_width    = $request['width'] ?? null;
		$raw_height   = $request['height'] ?? null;
		$extra_zoom   = ( null !== $raw_zoom && (int) $raw_zoom > 0 ) ? (int) $raw_zoom : null;
		$extra_width  = ( null !== $raw_width && (int) $raw_width >= 0 ) ? (int) $raw_width : null;
		$extra_height = ( null !== $raw_height && (int) $raw_height >= 0 ) ? (int) $raw_height : null;
		$aspect       = (string) ( $request['aspect_ratio'] ?? '' );

		$descriptors = $this->regenerate(
			$post_id,
			$extra_zoom,
			$extra_width,
			$extra_height,
			$aspect
		);

		// An empty descriptor map for a geocoded venue means every combo
		// failed — disk write error, GD missing, tile host unreachable past
		// COMPOSITE_TIME_BUDGET. Surface it with a distinct reason so the
		// UI can flag the failure instead of rendering as a silent success.
		if ( empty( $descriptors ) ) {
			return new WP_REST_Response(
				array(
					'descriptors' => (object) array(),
					'reason'      => 'generation_failed',
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'descriptors' => $descriptors,
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
	 * @param int      $post_id      Event or venue post ID.
	 * @param string   $post_type    The post type of `$post_id`.
	 * @param int|null $zoom         Desired zoom level. Null falls back to the default.
	 * @param int|null $width        Desired pixel width (0/null = auto).
	 * @param int|null $height       Desired pixel height (0/null = auto).
	 * @param string   $aspect_ratio Aspect-ratio hint used to derive any auto dimension.
	 * @return string Static map URL, or '' when unavailable.
	 */
	public function get_url_for_post(
		int $post_id,
		string $post_type,
		?int $zoom = null,
		?int $width = null,
		?int $height = null,
		string $aspect_ratio = ''
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

		$resolved = $this->resolve_dimensions(
			(int) ( $width ?? 0 ),
			(int) ( $height ?? $this->get_height() ),
			'' !== $aspect_ratio ? $aspect_ratio : self::DEFAULT_ASPECT_RATIO
		);

		$descriptor = $this->ensure_descriptor_for_combo(
			$venue_post_id,
			$info,
			$zoom ?? $this->get_zoom(),
			$resolved['width'],
			$resolved['height']
		);

		return null === $descriptor ? '' : $descriptor['url'];
	}

	/**
	 * Ensure a static-map descriptor exists for the given combo.
	 *
	 * Thin public wrapper around {@see self::ensure_descriptor_for_combo()} for
	 * prewarming callers (cron, CLI). Resolves venue info from the post,
	 * validates coordinates, derives any auto dimension via the aspect ratio,
	 * and returns the descriptor (or null when the venue has no usable
	 * coordinates yet or generation failed).
	 *
	 * Idempotent — when the hash matches an already-cached PNG the call is a
	 * no-op DB read, so it's safe to enqueue the same (venue, combo) job
	 * multiple times while a site is churning saves.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id      Venue post ID.
	 * @param int    $zoom         Zoom level.
	 * @param int    $width        Pixel width (0 = auto).
	 * @param int    $height       Pixel height (0 = auto).
	 * @param string $aspect_ratio Aspect-ratio string (e.g. "16/9").
	 * @return array{url: string, hash: string, zoom: int, width: int, height: int}|null
	 */
	public function warm( int $post_id, int $zoom, int $width, int $height, string $aspect_ratio = '' ): ?array {
		if ( 0 >= $post_id ) {
			return null;
		}

		$info = ( new Venue( $post_id ) )->get_information();

		if ( null === $this->parse_coord( $info['latitude'] ) ||
			null === $this->parse_coord( $info['longitude'] ) ) {
			return null;
		}

		$resolved = $this->resolve_dimensions(
			$width,
			$height,
			'' !== $aspect_ratio ? $aspect_ratio : self::DEFAULT_ASPECT_RATIO
		);

		return $this->ensure_descriptor_for_combo(
			$post_id,
			$info,
			$zoom,
			$resolved['width'],
			$resolved['height']
		);
	}

	/**
	 * Return the descriptor for the default (zoom, width, height) combo, or
	 * null if nothing is stored.
	 *
	 * Convenience for the common "has this venue been rendered at the site
	 * defaults yet?" question — production render paths resolve a specific
	 * combo through {@see self::get_url_for_post()}, and code iterating
	 * every cached variant should use {@see self::get_all_descriptors()}.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The venue post ID.
	 * @return array{url: string, hash: string, zoom: int, width: int, height: int}|null
	 */
	public function get_stored_descriptor( int $post_id ): ?array {
		$all      = $this->get_all_descriptors( $post_id );
		$defaults = $this->resolve_dimensions(
			0,
			$this->get_height(),
			self::DEFAULT_ASPECT_RATIO
		);

		return $all[ $this->combo_key(
			$this->get_zoom(),
			$defaults['width'],
			$defaults['height']
		) ] ?? null;
	}

	/**
	 * Return every stored descriptor for the venue, keyed by
	 * `{zoom}x{width}x{height}`.
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
	 * @return array<string, array{url: string, hash: string, zoom: int, width: int, height: int}>
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
			$width  = isset( $entry['width'] ) ? (int) $entry['width'] : 0;
			$height = isset( $entry['height'] ) ? (int) $entry['height'] : 0;

			// Without concrete zoom/width/height on the entry there's no way
			// to know which block configuration the file belongs to — skip
			// rather than guess.
			if ( $zoom <= 0 || $width <= 0 || $height <= 0 ) {
				continue;
			}

			$descriptors[ (string) $key ] = array(
				'url'    => (string) $entry['url'],
				'hash'   => (string) $entry['hash'],
				'zoom'   => $zoom,
				'width'  => $width,
				'height' => $height,
			);
		}

		/**
		 * Filters the parsed descriptor map for a venue.
		 *
		 * Companion plugins, multi-locale setups, or storage-layer overrides
		 * can use this to drop entries they consider stale, add synthetic
		 * descriptors (e.g. pre-rendered PNG files in a CDN), or rewrite URLs.
		 * Callers of this method already tolerate empty maps, so returning
		 * `[]` is a valid "suppress all" escape hatch.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, mixed>> $descriptors Parsed descriptor map keyed by combo.
		 * @param int                                 $post_id     Venue post ID.
		 */
		return (array) apply_filters( 'gatherpress_venue_map_descriptors', $descriptors, $post_id );
	}

	/**
	 * Parse stored meta into the list of (zoom, width, height) combos
	 * already cached.
	 *
	 * Used by {@see self::maybe_generate()} to cascade content changes (new
	 * address, new coordinates) across every variant a venue has ever been
	 * rendered at, so blocks pointing at a non-default combo aren't stranded
	 * with a stale image.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Venue post ID.
	 * @return array<int, array{zoom: int, width: int, height: int}>
	 */
	public function get_cached_combos( int $post_id ): array {
		$combos = array();

		foreach ( $this->get_all_descriptors( $post_id ) as $descriptor ) {
			$combos[] = array(
				'zoom'   => $descriptor['zoom'],
				'width'  => $descriptor['width'],
				'height' => $descriptor['height'],
			);
		}

		return $combos;
	}

	/**
	 * Build the meta-storage key for a (zoom, width, height) combo.
	 *
	 * @since 1.0.0
	 *
	 * @param int $zoom   Zoom level.
	 * @param int $width  Pixel width.
	 * @param int $height Pixel height.
	 * @return string
	 */
	protected function combo_key( int $zoom, int $width, int $height ): string {
		return sprintf( '%dx%dx%d', $zoom, $width, $height );
	}

	/**
	 * Ensure a descriptor exists for the `($zoom, $width, $height)` combo
	 * and return it.
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
	 * @param int   $width   Pixel width of the PNG.
	 * @param int   $height  Pixel height of the PNG.
	 * @return array{url: string, hash: string, zoom: int, width: int, height: int}|null
	 */
	protected function ensure_descriptor_for_combo(
		int $post_id,
		array $info,
		int $zoom,
		int $width,
		int $height
	): ?array {
		// Callers (maybe_generate, get_url_for_post) must have validated the
		// coordinates via parse_coord() already — cast directly.
		$latitude  = (float) $info['latitude'];
		$longitude = (float) $info['longitude'];

		// Clamp caller-provided values before they influence the cache key
		// or the canvas. Block attributes come from the editor already
		// within range, but a hand-edited block, a filter that mutates the
		// attribute, or bad meta could sneak an out-of-range value in.
		$zoom   = $this->clamp_zoom( $zoom );
		$width  = $this->clamp_width( $width );
		$height = $this->clamp_height( $height );

		$tiles   = $this->get_tile_url_template();
		$address = (string) ( $info['fullAddress'] ?? '' );
		$hash    = $this->hash_for( $info, $zoom, $width, $height, $tiles );
		$key     = $this->combo_key( $zoom, $width, $height );

		$all      = $this->get_all_descriptors( $post_id );
		$existing = $all[ $key ] ?? null;

		if ( null !== $existing && $existing['hash'] === $hash ) {
			// The URL itself is deterministic from (address, zoom, dims) so
			// existence on disk is the real validity signal.
			$expected_url = $this->build_image_url( $address, $zoom, $width, $height );
			if ( $existing['url'] === $expected_url ) {
				return $existing;
			}
		}

		$image = $this->composite_image( $latitude, $longitude, $zoom, $width, $height, $tiles );

		// Downstream of the GD-missing branch in composite_image(); only
		// reached on PHP builds without the GD extension.
		if ( null === $image ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		$this->stamp_marker( $image, (int) round( $width / 2 ), (int) round( $height / 2 ) );

		$url = $this->save_image( $image, $address, $zoom, $width, $height );
		imagedestroy( $image );

		if ( null === $url ) {
			return null;
		}

		$all[ $key ] = array(
			'url'    => $url,
			'hash'   => $hash,
			'zoom'   => $zoom,
			'width'  => $width,
			'height' => $height,
		);

		update_post_meta( $post_id, self::META_KEY, $all );

		// Verify the write landed. update_post_meta() returns an ambiguous
		// false ("not changed" vs. "write failed"), so read back and check
		// the URL we just saved is what ended up in the meta. Orphan files
		// (file on disk but no meta row pointing at it) are acceptable —
		// with address-based naming the same file may be shared with other
		// venues, so we don't proactively unlink on write-verify failure.
		$stored = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $stored )
			|| ! isset( $stored[ $key ]['url'] )
			|| $stored[ $key ]['url'] !== $url
		) {
			return null;
		}

		return $all[ $key ];
	}

	/**
	 * Compute a stable hash from the inputs that determine the rendered PNG.
	 *
	 * Any change to address, coords, zoom, width, height, or tile provider
	 * invalidates the previous image. Unrelated venue edits (title, excerpt,
	 * other meta) keep the same hash and skip regeneration.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $info  Parsed venue information.
	 * @param int    $zoom  Map zoom level.
	 * @param int    $width Output width.
	 * @param int    $height Output height.
	 * @param string $tiles Tile URL template.
	 * @return string MD5 hex digest.
	 */
	public function hash_for( array $info, int $zoom, int $width, int $height, string $tiles ): string {
		// md5() here is a non-cryptographic cache-key discriminator, matching class-geocoding.php.
		return md5( // NOSONAR.
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
	 * Build the deterministic URL for a given (address, zoom, width, height).
	 *
	 * Mirrors {@see self::save_image()}'s filename composition so callers can
	 * compare an existing descriptor's URL against the expected URL without
	 * touching the filesystem. Two venues at the same address share the same
	 * URL at matching dimensions — intentional dedupe.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address Venue address string.
	 * @param int    $zoom    Map zoom level.
	 * @param int    $width   Output width.
	 * @param int    $height  Output height.
	 * @return string Full public URL for the PNG.
	 */
	protected function build_image_url( string $address, int $zoom, int $width, int $height ): string {
		$dirs     = wp_get_upload_dir();
		$base_url = trailingslashit( $dirs['baseurl'] ) . self::UPLOADS_SUBDIR;

		return trailingslashit( $base_url ) . $this->filename_for( $address, $zoom, $width, $height );
	}

	/**
	 * Compose a filesystem-safe filename from the address + dimensions.
	 *
	 * Address gets slugified via `sanitize_title()` and capped at 150 chars
	 * so the full filename stays comfortably under the 255-byte cap common
	 * to most filesystems. An empty or all-special-character address falls
	 * back to `venue` so there's always something before the dimension
	 * suffix.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address Venue address.
	 * @param int    $zoom    Map zoom level.
	 * @param int    $width   Output width.
	 * @param int    $height  Output height.
	 * @return string Filename including the `.png` extension.
	 */
	protected function filename_for( string $address, int $zoom, int $width, int $height ): string {
		// `sanitize_title()` URL-slugifies; pass the result through
		// `sanitize_file_name()` as defense-in-depth so any path-sensitive
		// characters that slip past (or future changes to sanitize_title's
		// allowed set) can't escape the filename segment.
		$slug = sanitize_file_name( sanitize_title( $address ) );

		if ( '' === $slug ) {
			$slug = 'venue';
		}

		if ( 150 < strlen( $slug ) ) {
			$slug = substr( $slug, 0, 150 );
		}

		return sprintf( '%s-%d-%d-%d.png', $slug, $zoom, $width, $height );
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
	 * @param int    $width  Output pixel width.
	 * @param int    $height Output pixel height.
	 * @param string $tiles  Tile URL template.
	 * @return \GdImage|resource|null Composited image, or null when GD is unavailable.
	 *                                GdImage on PHP 8+, resource on PHP 7.4.
	 */
	public function composite_image(
		float $lat,
		float $lng,
		int $zoom,
		int $width,
		int $height,
		string $tiles
	) {
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
	 * Filename is derived from `(address, zoom, width, height)` — two venues
	 * that resolve to the same address slug at matching dimensions share one
	 * file on disk. `imagepng` overwrites in place, which is fine for our
	 * regenerate flow since the inputs that'd change visible output also
	 * change the hash in the descriptor meta.
	 *
	 * @since 1.0.0
	 *
	 * @param \GdImage|resource $image   The finished composite (GdImage on PHP 8+, resource on PHP 7.4).
	 * @param string            $address Venue address (slugified for the filename).
	 * @param int               $zoom    Map zoom level.
	 * @param int               $width   Output width.
	 * @param int               $height  Output height.
	 * @return string|null Public URL of the saved file, or null on failure.
	 */
	public function save_image( $image, string $address, int $zoom, int $width, int $height ): ?string {
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

		$filename = $this->filename_for( $address, $zoom, $width, $height );
		$path     = trailingslashit( $base_dir ) . $filename;
		$url      = trailingslashit( $base_url ) . $filename;

		// Disk fills up mid-write, or target dir lost write permission between the mkdir check and the imagepng call.
		if ( ! imagepng( $image, $path ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		return $url;
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
	 * Clamp a pixel width to the supported range.
	 *
	 * @since 1.0.0
	 *
	 * @param int $width Raw width value.
	 * @return int
	 */
	protected function clamp_width( int $width ): int {
		return max( self::WIDTH_MIN, min( self::WIDTH_MAX, $width ) );
	}

	/**
	 * Parse an aspect-ratio string (e.g. "16/9") into its float value.
	 *
	 * Accepts the CSS `aspect-ratio` format with either a slash or a
	 * colon separator. Returns null for unparsable / zero-denominator
	 * input so callers can fall back to the default.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ratio Raw aspect-ratio string.
	 * @return float|null
	 */
	protected function parse_aspect_ratio( string $ratio ): ?float {
		if ( ! preg_match( '#\A(\d+)\s*[/:]\s*(\d+)\z#', trim( $ratio ), $matches ) ) {
			return null;
		}

		$numerator   = (int) $matches[1];
		$denominator = (int) $matches[2];

		if ( $numerator <= 0 || $denominator <= 0 ) {
			return null;
		}

		return $numerator / $denominator;
	}

	/**
	 * Resolve a (width, height) pair from block attribute values.
	 *
	 * Either dimension can be passed as `0` meaning "auto" — in which
	 * case the method derives the missing side from the other side and
	 * the aspect-ratio string. When both are auto, the site default
	 * height drives the calculation. When both are explicit, the caller
	 * wins and the aspect-ratio hint is ignored (the rendered PNG just
	 * honors the literal pixel dimensions).
	 *
	 * Both returned values are clamped to the supported ranges so a
	 * hand-edited block attribute or filter override can't drive the
	 * generator outside of sane bounds.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $width   Block width (0 = auto).
	 * @param int    $height  Block height (0 = auto).
	 * @param string $ratio   Aspect-ratio string (e.g. "16/9").
	 * @return array{width: int, height: int}
	 */
	protected function resolve_dimensions( int $width, int $height, string $ratio ): array {
		$parsed = $this->parse_aspect_ratio( '' !== $ratio ? $ratio : self::DEFAULT_ASPECT_RATIO );

		if ( null === $parsed ) {
			$fallback = $this->parse_aspect_ratio( self::DEFAULT_ASPECT_RATIO );
			$parsed   = null !== $fallback ? $fallback : self::IMAGE_ASPECT_RATIO;
		}

		if ( $width <= 0 && $height <= 0 ) {
			$height = self::DEFAULT_HEIGHT;
			$width  = (int) round( $height * $parsed );
		} elseif ( $width <= 0 ) {
			$width = (int) round( $height * $parsed );
		} elseif ( $height <= 0 ) {
			$height = (int) round( $width / $parsed );
		}

		return array(
			'width'  => $this->clamp_width( $width ),
			'height' => $this->clamp_height( $height ),
		);
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
