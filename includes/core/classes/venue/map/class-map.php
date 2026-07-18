<?php
/**
 * Static-map orchestrator for the venue subsystem.
 *
 * Owns the lifecycle of pre-rendered PNG files: which (zoom, width, height)
 * combos exist for a venue, where they live on disk, when they're stale,
 * and how the front end resolves them for a given post context. The
 * provider-specific work — fetching tiles, compositing, marker stamping,
 * or whatever a Google/Mapbox provider would do — is delegated to the
 * active {@see Provider\Base} resolved through {@see Manager}.
 *
 * Post-meta shape is provider-keyed:
 *
 *     [
 *         'osm' => [ '15-800-300' => [url, url_2x, hash, ...], ... ],
 *         'google' => [ ... ],
 *     ]
 *
 * so PNG files from different providers coexist on disk and the render path
 * can fall back to whichever variant is available when a site switches
 * `map_platform` mid-flight.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.34.0
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Setup;
use GatherPress\Core\Venue\Venue;
use GdImage;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Map.
 *
 * Singleton orchestrator. Routes lifecycle events (save/delete/regenerate
 * /prewarm) through the active provider, persists descriptors per
 * provider, and serves them back to render paths with a fallback chain
 * when the active provider hasn't rendered a given combo yet.
 *
 * @since 0.34.0
 *
 * @phpstan-type Descriptor array{url: string, url_2x: string, hash: string, zoom: int, width: int, height: int}
 * @phpstan-type DescriptorMap array<string, Descriptor>
 * @phpstan-type ProviderDescriptorMap array<string, DescriptorMap>
 */
class Map {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Default `renderMode` attribute for the venue-map block.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const DEFAULT_RENDER_MODE = 'interactive';

	/**
	 * Default zoom level. Used by the generator and the block.
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const DEFAULT_ZOOM = 16;

	/**
	 * Default height (in pixels). Used by the generator and the block.
	 *
	 * @since 0.34.0
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
	 * @since 0.34.0
	 * @var int
	 */
	const ZOOM_MIN = 1;

	/**
	 * See self::ZOOM_MIN.
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const ZOOM_MAX = 20;

	/**
	 * Bounds for the pixel height. Mirrors the block's RangeControl so
	 * out-of-range Settings / filter values can't produce an absurd canvas
	 * (a 10,000-px-tall image would allocate gigabytes of GD memory and
	 * fetch hundreds of tiles).
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const HEIGHT_MIN = 100;

	/**
	 * See self::HEIGHT_MIN.
	 *
	 * @since 0.34.0
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
	 * @since 0.34.0
	 * @var int
	 */
	const WIDTH_MIN = 100;

	/**
	 * See self::WIDTH_MIN.
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const WIDTH_MAX = 4000;

	/**
	 * Default aspect ratio string used when the block's `aspectRatio`
	 * attribute is empty or unparsable. Format matches the CSS
	 * `aspect-ratio` property so the same value can drive the server-side
	 * width derivation and the client-side CSS on the interactive wrapper.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const DEFAULT_ASPECT_RATIO = '2/1';

	/**
	 * Default `scale` (CSS `object-fit`) applied to the static map image.
	 * `cover` crops the PNG to fill the wrapper without distortion — same
	 * behavior the block shipped with before the attribute was exposed.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const DEFAULT_SCALE = 'cover';

	/**
	 * Allow-listed values for the `scale` block attribute — mirrors the
	 * three `object-fit` keywords the block's Inspector exposes. Any other
	 * value falls back to `DEFAULT_SCALE` at render time so a hand-edited
	 * block attribute can't smuggle arbitrary CSS into the inline style.
	 *
	 * @since 0.34.0
	 * @var string[]
	 */
	const SCALE_OPTIONS = array( 'cover', 'contain', 'fill' );

	/**
	 * Anchored regex matching a CSS-style aspect-ratio value (e.g. `16/9`
	 * or `4:3`). Used by both the REST `aspect_ratio` validator and the
	 * render template so the two can never drift apart. Requires at least
	 * one non-zero digit on each side so a degenerate `0/9` / `9/0` — which
	 * CSS would treat as `auto` — can't slip through.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const ASPECT_RATIO_PATTERN = '#\A\s*[1-9][0-9]*\s*[/:]\s*[1-9][0-9]*\s*\z#';

	/**
	 * Default `type` attribute for the venue-map block.
	 *
	 * Google Maps–specific (roadmap / satellite / hybrid / terrain). The OSM
	 * provider only renders `roadmap`; future Google provider will respect
	 * the full set per `Provider\Base::supports_map_type()`.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const DEFAULT_MAP_TYPE = 'roadmap';

	/**
	 * Pixel-density multiplier for the retina (2×) static-map variant.
	 *
	 * Drives the `@{density}x` filename suffix and the second pass through
	 * the active provider's `render()` for retina variants. Providers that
	 * can't satisfy a given (zoom, density) combo return null and the
	 * orchestrator drops the 2× variant for that combo.
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const RETINA_DENSITY = 2;

	/**
	 * Densities the orchestrator asks providers to render at. Only
	 * power-of-two values give a whole-number tile-zoom offset for tile-
	 * based providers (OSM); other providers (Google) ignore the
	 * constraint and just pass through to `scale={n}`.
	 *
	 * @since 0.34.0
	 * @var int[]
	 */
	const SUPPORTED_DENSITIES = array( 1, self::RETINA_DENSITY );

	/**
	 * PNG aspect ratio (width ÷ height).
	 *
	 * The block exposes height but not width (blocks render 100% of their
	 * container). We still need *some* pixel width for the PNG, so the
	 * orchestrator derives it from the block's chosen height at this ratio —
	 * 2:1 by default, which gives a comfortably landscape map without the
	 * venue marker feeling tight against the edges.
	 *
	 * @since 0.34.0
	 * @var float
	 */
	const IMAGE_ASPECT_RATIO = 2.0;

	/**
	 * Post meta key under which the static-map descriptors are stored.
	 *
	 * Stored value is provider-keyed:
	 *
	 *     [
	 *         '<provider_slug>' => [
	 *             '<zoom>x<width>x<height>' => array{
	 *                 url: string, url_2x: string, hash: string,
	 *                 zoom: int, width: int, height: int,
	 *             },
	 *             ...
	 *         ],
	 *         ...
	 *     ]
	 *
	 * The provider-key layer lets a site keep older OSM PNG files around as
	 * fallbacks while a new Google provider's PNG files render in the
	 * background after a `map_platform` switch. `url_2x` is empty string
	 * when the retina variant failed or the provider can't produce one
	 * for the given combo.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const META_KEY = 'gatherpress_static_map';

	/**
	 * Subdirectory of `wp-content/uploads` where generated PNG files are written.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const UPLOADS_SUBDIR = 'gatherpress/static-maps';

	/**
	 * Class constructor — wires hooks.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register the save- and delete-side hooks that own the static map lifecycle.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Priority 11 — just after the default-10 batch, so any other
		// wp_after_insert_post callback touching venue meta during the
		// same save has already run. We read the individual venue meta
		// keys directly, so there's no hard dependency on any specific
		// hook, but trailing the default batch is the cheapest guard
		// against future ordering surprises.
		add_action( 'wp_after_insert_post', array( $this, 'maybe_generate' ), 11 );
		add_action( 'registered_post_type', array( $this, 'maybe_register_delete_hook' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'block_type_metadata', array( $this, 'apply_block_attribute_defaults' ) );
		// React to provider changes so a `map_platform` switch schedules
		// the same prewarm pass that runs on theme switches.
		add_action( 'update_option_gatherpress_settings', array( $this, 'maybe_handle_settings_change' ), 10, 2 );
	}

	/**
	 * Schedule a re-prewarm sweep when `map_platform` changes value.
	 *
	 * Called by the `update_option_gatherpress_settings` action whenever
	 * the GatherPress settings option is written. Only the provider switch
	 * matters here — non-provider edits to the option are no-ops. Existing
	 * PNG files stay on disk during the transition: the front-end's fallback
	 * chain in {@see self::get_descriptor_for_post()} keeps showing the
	 * old-provider image until the new provider's PNG lands via prewarm.
	 *
	 * @since 0.34.0
	 *
	 * @param array|mixed $old_value Previous settings option value.
	 * @param array|mixed $new_value New settings option value.
	 *
	 * @return void
	 */
	public function maybe_handle_settings_change( $old_value, $new_value ): void {
		$old_platform = is_array( $old_value ) ? (string) ( $old_value['map_platform'] ?? '' ) : '';
		$new_platform = is_array( $new_value ) ? (string) ( $new_value['map_platform'] ?? '' ) : '';

		if ( $old_platform === $new_platform ) {
			return;
		}

		// Defer to a one-shot cron tick rather than running the full
		// template + venue rescan inline. A site with thousands of
		// venues × combos would otherwise fan out a huge number of cron
		// events synchronously inside the admin save that triggered us.
		// Wrapped so a missing `Prewarm` (tests that haven't booted the
		// venue subsystem) doesn't fatal a plain settings save.
		if ( class_exists( Prewarm::class ) ) {
			Prewarm::get_instance()->schedule_full_sweep();
		}
	}

	/**
	 * Register REST routes for the venue-map block.
	 *
	 * Today there's a single endpoint that forces the static PNG files for a
	 * venue to regenerate — used by the "Regenerate Map" button in the
	 * block editor when a tile provider changes or a render gets out of
	 * sync with the venue's current inputs.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$permission = static function ( WP_REST_Request $request ): bool {
			$post_id = (int) $request['id'];

			// Permission scope is the venue post itself — anyone who can
			// edit the venue can force its map to regenerate. Admins with
			// edit_others_posts go through the same check.
			return current_user_can( 'edit_post', $post_id );
		};

		$id_arg = array(
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
		);

		$combo_args = Rest_Combo::route_args();

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/venue/(?P<id>\d+)/regenerate-map',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_regenerate' ),
				'permission_callback' => $permission,
				'args'                => array_merge(
					array( 'id' => $id_arg ),
					$combo_args
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
	 * @since 0.34.0
	 *
	 * @param array $metadata Parsed `block.json` metadata for the block being registered.
	 *
	 * @return array The metadata array, potentially with updated attribute defaults.
	 */
	public function apply_block_attribute_defaults( array $metadata ): array {
		if ( 'gatherpress/venue-map' !== ( $metadata['name'] ?? '' ) ) {
			return $metadata;
		}

		$settings = Settings::get_instance();

		// Height is absent on purpose: it lives in the block's
		// `style.dimensions` (core dimensions support), which has no
		// attribute default to stamp — the Settings value reaches rendering
		// through the dimension-resolution paths instead (render.php and
		// the editor's getFromSettings channel). Width has no default at
		// all: the block always fills its container.
		$defaults = array(
			'renderMode'  => (string) $settings->get( 'venue_map_default_render_mode' ),
			'zoom'        => (int) $settings->get( 'venue_map_default_zoom' ),
			'aspectRatio' => (string) $settings->get( 'venue_map_default_aspect_ratio' ),
			'scale'       => (string) $settings->get( 'venue_map_default_scale' ),
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
			'aspectRatio' => function ( $value ): bool {
				return is_string( $value )
					&& null !== $this->parse_aspect_ratio( $value );
			},
			'scale'       => static function ( $value ): bool {
				return in_array( $value, self::SCALE_OPTIONS, true );
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
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type that was just registered.
	 *
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
	 * @since 0.34.0
	 *
	 * @param int $post_id The post ID being saved.
	 *
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

		// Regenerate every (zoom, width, height, map_type) combo the venue is
		// already cached at so a content change (new address/coords) cascades
		// to all cached variants — each preserving its own map type. For a
		// fresh venue with nothing stored, seed the default combo that
		// newly-inserted venue-map blocks will actually request — otherwise
		// the editor falls back to the interactive preview on the very first
		// save because the default cache entry is missing.
		$combos = $this->get_cached_combos( $post_id );
		if ( empty( $combos ) ) {
			$default = $this->resolve_dimensions(
				0,
				$this->get_height(),
				self::DEFAULT_ASPECT_RATIO
			);
			$combos  = array(
				array(
					'zoom'     => $this->get_zoom(),
					'width'    => $default['width'],
					'height'   => $default['height'],
					'map_type' => $this->normalize_map_type( '' ),
				),
			);
		}

		foreach ( $combos as $combo ) {
			$this->ensure_descriptor_for_combo(
				$post_id,
				$info,
				$combo['zoom'],
				$combo['width'],
				$combo['height'],
				$this->normalize_map_type( (string) $combo['map_type'] )
			);
		}
	}

	/**
	 * Delete every stored static-map PNG (at any zoom) and clear the meta.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id The venue post ID.
	 *
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
	 * Clears every cached descriptor + PNG, then recreates images for each
	 * combo the venue was previously cached at — preserving each variant's
	 * own map type so distinct types at the same dimensions aren't collapsed.
	 * When the caller supplies an extra `(zoom, height)` — typically the combo
	 * the block editor is currently displaying — that combo is added to the
	 * list, tagged with the requested `$map_type`, so a "Generate" click from
	 * the placeholder produces a PNG for it even if the combo has never been
	 * cached before. For a venue that has no cached combos and no
	 * caller-supplied one, the site-default combo seeds the run.
	 *
	 * Returns the fresh descriptor map so the caller — typically the block
	 * editor's "Regenerate Map" REST endpoint — can hand the new URLs
	 * straight back to the client without a second DB round-trip.
	 *
	 * @since 0.34.0
	 *
	 * @param int        $post_id     The venue post ID.
	 * @param array|null $extra_combo Optional extra combo to include, in the
	 *                                {@see Rest_Combo::parse_request()} shape:
	 *                                `zoom`, `width` (0 = auto), `height`
	 *                                (0 = auto), `aspect_ratio`, `map_type`.
	 *
	 * @return ProviderDescriptorMap
	 */
	public function regenerate( int $post_id, ?array $extra_combo = null ): array {
		// Cache the combos we want to rebuild before wiping meta —
		// delete_stored_image() clears META_KEY, which is where
		// get_cached_combos() reads from.
		$combos   = $this->get_cached_combos( $post_id );
		$map_type = $this->normalize_map_type( (string) ( $extra_combo['map_type'] ?? '' ) );

		// Merge the caller-supplied combo in, tagged with the requested map
		// type, resolving dims when either width or height is left as "auto".
		if ( null !== ( $extra_combo['zoom'] ?? null ) ) {
			$resolved = $this->resolve_dimensions(
				(int) ( $extra_combo['width'] ?? 0 ),
				(int) ( $extra_combo['height'] ?? 0 ),
				(string) ( $extra_combo['aspect_ratio'] ?? '' )
			);
			$combos[] = array(
				'zoom'     => (int) $extra_combo['zoom'],
				'width'    => $resolved['width'],
				'height'   => $resolved['height'],
				'map_type' => $map_type,
			);
		}

		// Dedupe on the full (zoom, width, height, map_type) key so distinct
		// map-type variants at the same dimensions each survive the rebuild.
		$seen   = array();
		$unique = array();
		foreach ( $combos as $combo ) {
			$combo_map_type = $this->normalize_map_type( (string) $combo['map_type'] );
			$key            = $this->combo_key(
				$this->clamp_zoom( (int) $combo['zoom'] ),
				$this->clamp_width( (int) $combo['width'] ),
				$this->clamp_height( (int) $combo['height'] ),
				$combo_map_type
			);

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ]      = true;
			$combo['map_type'] = $combo_map_type;
			$unique[]          = $combo;
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
					'zoom'     => $this->get_zoom(),
					'width'    => $default['width'],
					'height'   => $default['height'],
					'map_type' => $map_type,
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
				$combo['height'],
				$this->normalize_map_type( (string) $combo['map_type'] )
			);
		}

		return $this->get_all_descriptors( $post_id );
	}

	/**
	 * Ensure a single combo exists for a venue and return the full map.
	 *
	 * Additive counterpart to {@see self::regenerate()}: generates only the
	 * requested combo when it isn't cached yet, leaving every other cached
	 * variant untouched. Returns the venue's full descriptor map on success
	 * and an empty array when the combo could not be produced — the same
	 * shape the REST handler surfaces as `generation_failed`.
	 *
	 * @since 0.35.0
	 *
	 * @param int   $post_id The venue post ID.
	 * @param array $combo   Combo in the {@see Rest_Combo::parse_request()}
	 *                       shape: `zoom`, `width` (0 = auto), `height`
	 *                       (0 = auto), `aspect_ratio`, `map_type`.
	 *
	 * @return ProviderDescriptorMap
	 */
	public function ensure_combo( int $post_id, array $combo ): array {
		$descriptor = $this->get_descriptor_for_post(
			$post_id,
			(string) get_post_type( $post_id ),
			$combo['zoom'] ?? null,
			$combo['width'] ?? null,
			$combo['height'] ?? null,
			(string) ( $combo['aspect_ratio'] ?? '' ),
			(string) ( $combo['map_type'] ?? '' )
		);

		return null === $descriptor ? array() : $this->get_all_descriptors( $post_id );
	}

	/**
	 * REST handler for `POST /venue/{id}/regenerate-map`.
	 *
	 * Returns the fresh descriptor map on success. When the venue has no
	 * usable coordinates yet (address hasn't geocoded), returns an empty
	 * descriptors array and a structured `reason` so the client can show
	 * the right placeholder instead of a generic error.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_regenerate( WP_REST_Request $request ): WP_REST_Response {
		nocache_headers();

		$post_id     = (int) $request['id'];
		$venue       = new Venue( $post_id );
		$info        = $venue->get_information();
		$latitude    = $this->parse_coord( $info['latitude'] );
		$longitude   = $this->parse_coord( $info['longitude'] );
		$has_address = '' !== trim( (string) $info['address'] );

		if ( ! $has_address || null === $latitude || null === $longitude ) {
			return new WP_REST_Response(
				array(
					'descriptors' => (object) array(),
					'reason'      => $has_address ? 'awaiting_geocode' : 'no_address',
				),
				200
			);
		}

		$combo       = Rest_Combo::parse_request( $request );
		$ensure_only = rest_sanitize_boolean( $request['ensure_only'] ?? false );

		$descriptors = $ensure_only
			? $this->ensure_combo( $post_id, $combo )
			: $this->regenerate( $post_id, $combo );

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
	 * Resolve the static-map descriptor for any GatherPress post context.
	 *
	 * Accepts either a venue post ID (supports `gatherpress-venue-information`)
	 * or an event post ID (supports `gatherpress-venue`) and returns the full
	 * descriptor — both 1× and 2× URLs plus the bookkeeping fields — for the
	 * corresponding venue at the requested zoom and height. When the cache
	 * doesn't yet have an image for that combo, the method generates one
	 * synchronously; first render pays the cost, subsequent renders hit the
	 * cache. Returns null when the post isn't venue-related, the venue has
	 * no coordinates, or generation failed.
	 *
	 * @since 0.34.0
	 *
	 * @param int      $post_id      Event or venue post ID.
	 * @param string   $post_type    The post type of `$post_id`.
	 * @param int|null $zoom         Desired zoom level. Null falls back to the default.
	 * @param int|null $width        Desired pixel width (0/null = auto).
	 * @param int|null $height       Desired pixel height (0/null = auto).
	 * @param string   $aspect_ratio Aspect-ratio hint used to derive any auto dimension.
	 * @param string   $map_type     Map type slug from the block attribute.
	 *
	 * @return array{url: string, url_2x: string, hash: string, zoom: int, width: int, height: int}|null
	 */
	public function get_descriptor_for_post(
		int $post_id,
		string $post_type,
		?int $zoom = null,
		?int $width = null,
		?int $height = null,
		string $aspect_ratio = '',
		string $map_type = ''
	): ?array {
		$venue_post_id = 0;

		if ( post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			$venue_post_id = $post_id;
		} elseif ( post_type_supports( $post_type, 'gatherpress-venue' ) ) {
			$venue_post = Setup::get_instance()->get_venue_post_from_event_post_id( $post_id );

			if ( $venue_post instanceof WP_Post ) {
				$venue_post_id = $venue_post->ID;
			}
		}

		if ( 0 === $venue_post_id ) {
			return null;
		}

		$info = ( new Venue( $venue_post_id ) )->get_information();

		if ( null === $this->parse_coord( $info['latitude'] ) ||
			null === $this->parse_coord( $info['longitude'] ) ) {
			return null;
		}

		$resolved      = $this->resolve_dimensions(
			(int) ( $width ?? 0 ),
			(int) ( $height ?? $this->get_height() ),
			'' !== $aspect_ratio ? $aspect_ratio : self::DEFAULT_ASPECT_RATIO
		);
		$resolved_zoom = $zoom ?? $this->get_zoom();
		$map_type      = $this->normalize_map_type( $map_type );

		$active_descriptor = $this->ensure_descriptor_for_combo(
			$venue_post_id,
			$info,
			$resolved_zoom,
			$resolved['width'],
			$resolved['height'],
			$map_type
		);

		if ( null !== $active_descriptor ) {
			return $active_descriptor;
		}

		// Active provider couldn't render this combo (e.g. tile host
		// unreachable, GD missing, brand-new provider not yet warmed). Walk
		// other providers' stored entries for the same combo so a switch
		// from OSM to Google still shows the OSM PNG until the new one
		// lands. Take the first matching entry; ordering follows whatever
		// post meta gives us (last-saved-wins by storage order).
		$key         = $this->combo_key(
			$this->clamp_zoom( $resolved_zoom ),
			$this->clamp_width( $resolved['width'] ),
			$this->clamp_height( $resolved['height'] ),
			$map_type
		);
		$all         = $this->get_all_descriptors( $venue_post_id );
		$active_slug = Manager::get_instance()->get_active_slug();
		$fallback    = null;

		foreach ( $all as $slug => $combos ) {
			if ( $slug !== $active_slug && isset( $combos[ $key ] ) ) {
				$fallback = $combos[ $key ];
				break;
			}
		}

		return $fallback;
	}

	/**
	 * Resolve the static-map URL for any GatherPress post context.
	 *
	 * Thin wrapper around {@see self::get_descriptor_for_post()} that returns
	 * just the 1× URL, preserved for callers that only need the baseline
	 * image. For render paths that want the retina variant too, call
	 * `get_descriptor_for_post()` directly.
	 *
	 * @since 0.34.0
	 *
	 * @param int      $post_id      Event or venue post ID.
	 * @param string   $post_type    The post type of `$post_id`.
	 * @param int|null $zoom         Desired zoom level. Null falls back to the default.
	 * @param int|null $width        Desired pixel width (0/null = auto).
	 * @param int|null $height       Desired pixel height (0/null = auto).
	 * @param string   $aspect_ratio Aspect-ratio hint used to derive any auto dimension.
	 * @param string   $map_type     Map type slug from the block attribute.
	 *
	 * @return string Static map URL, or '' when unavailable.
	 */
	public function get_url_for_post(
		int $post_id,
		string $post_type,
		?int $zoom = null,
		?int $width = null,
		?int $height = null,
		string $aspect_ratio = '',
		string $map_type = ''
	): string {
		$descriptor = $this->get_descriptor_for_post(
			$post_id,
			$post_type,
			$zoom,
			$width,
			$height,
			$aspect_ratio,
			$map_type
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
	 * @since 0.34.0
	 *
	 * @param int    $post_id      Venue post ID.
	 * @param int    $zoom         Zoom level.
	 * @param int    $width        Pixel width (0 = auto).
	 * @param int    $height       Pixel height (0 = auto).
	 * @param string $aspect_ratio Aspect-ratio string (e.g. "16/9").
	 * @param string $map_type     Map type slug (defaults to site setting).
	 *
	 * @return array{url: string, url_2x: string, hash: string, zoom: int, width: int, height: int}|null
	 */
	public function warm(
		int $post_id,
		int $zoom,
		int $width,
		int $height,
		string $aspect_ratio = '',
		string $map_type = ''
	): ?array {
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
			$resolved['height'],
			$map_type
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
	 * @since 0.34.0
	 *
	 * @param int $post_id The venue post ID.
	 *
	 * @return array{url: string, url_2x: string, hash: string, zoom: int, width: int, height: int}|null
	 */
	public function get_stored_descriptor( int $post_id ): ?array {
		$all      = $this->get_all_descriptors( $post_id );
		$slug     = Manager::get_instance()->get_active_slug();
		$defaults = $this->resolve_dimensions(
			0,
			$this->get_height(),
			self::DEFAULT_ASPECT_RATIO
		);

		$key = $this->combo_key(
			$this->get_zoom(),
			$defaults['width'],
			$defaults['height'],
			$this->normalize_map_type( '' )
		);

		return $all[ $slug ][ $key ] ?? null;
	}

	/**
	 * Return every stored descriptor for the venue, keyed by provider slug
	 * then by `{zoom}x{width}x{height}x{map_type}`.
	 *
	 * Filters out malformed entries so callers can iterate without defensive
	 * shape checks. The read path itself does not persist the cleaned map —
	 * writing on every front-end render would invalidate the post-meta cache
	 * and churn the DB. Dropped entries are removed opportunistically the
	 * next time a descriptor is saved, because `ensure_descriptor_for_combo()`
	 * reuses the filtered map as the basis for its `update_post_meta()` call.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id The venue post ID.
	 *
	 * @return ProviderDescriptorMap
	 */
	public function get_all_descriptors( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$descriptors = array();

		foreach ( $raw as $provider_slug => $combos ) {
			if ( ! is_string( $provider_slug ) || '' === $provider_slug || ! is_array( $combos ) ) {
				continue;
			}

			foreach ( $combos as $key => $entry ) {
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

				$descriptors[ $provider_slug ][ (string) $key ] = array(
					'url'    => (string) $entry['url'],
					'url_2x' => isset( $entry['url_2x'] ) ? (string) $entry['url_2x'] : '',
					'hash'   => (string) $entry['hash'],
					'zoom'   => $zoom,
					'width'  => $width,
					'height' => $height,
				);
			}
		}

		/**
		 * Filters the parsed descriptor map for a venue.
		 *
		 * Companion plugins, multi-locale setups, or storage-layer overrides
		 * can use this to drop entries they consider stale, add synthetic
		 * descriptors (e.g. pre-rendered PNG files in a CDN), or rewrite URLs.
		 * Outer key is provider slug, inner key is `{zoom}x{width}x{height}x{map_type}`.
		 * Callers of this method already tolerate empty maps, so returning
		 * `[]` is a valid "suppress all" escape hatch.
		 *
		 * @since 0.34.0
		 *
		 * @param array<string, array<string, array<string, mixed>>> $descriptors Provider-keyed descriptor map.
		 * @param int                                                $post_id     Venue post ID.
		 */
		return (array) apply_filters( 'gatherpress_static_map_descriptors', $descriptors, $post_id );
	}

	/**
	 * Parse stored meta into the deduped list of (zoom, width, height, map_type)
	 * combos any provider has ever rendered for this venue.
	 *
	 * Used by {@see self::maybe_generate()} to cascade content changes (new
	 * address, new coordinates) across every variant a venue has ever been
	 * rendered at, regardless of which provider rendered it. After a
	 * `map_platform` switch, the active provider gets re-rendered for every
	 * combo any earlier provider had — so blocks pointing at non-default
	 * combos aren't stranded.
	 *
	 * Each combo carries the map type it was rendered with so callers can
	 * rebuild every `(zoom, width, height, map_type)` variant rather than
	 * collapsing distinct types onto the site default.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id Venue post ID.
	 *
	 * @return array<int, array{zoom: int, width: int, height: int, map_type: string}>
	 */
	public function get_cached_combos( int $post_id ): array {
		$seen   = array();
		$combos = array();

		foreach ( $this->get_all_descriptors( $post_id ) as $provider_combos ) {
			foreach ( $provider_combos as $stored_key => $descriptor ) {
				$zoom   = (int) $descriptor['zoom'];
				$width  = (int) $descriptor['width'];
				$height = (int) $descriptor['height'];

				// Recover the map type from the stored key. The key is
				// `{zoom}x{width}x{height}x{map_type}`; legacy keys predating
				// map-type support have no suffix and fall back to the default.
				$prefix   = sprintf( '%dx%dx%dx', $zoom, $width, $height );
				$map_type = str_starts_with( (string) $stored_key, $prefix )
					? substr( (string) $stored_key, strlen( $prefix ) )
					: '';
				$map_type = $this->normalize_map_type( $map_type );

				$key = $this->combo_key( $zoom, $width, $height, $map_type );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$combos[]     = array(
					'zoom'     => $zoom,
					'width'    => $width,
					'height'   => $height,
					'map_type' => $map_type,
				);
			}
		}

		return $combos;
	}

	/**
	 * Build the meta-storage key for a (zoom, width, height, map_type) combo.
	 *
	 * @since 0.34.0
	 *
	 * @param int    $zoom     Zoom level.
	 * @param int    $width    Pixel width.
	 * @param int    $height   Pixel height.
	 * @param string $map_type Map type slug.
	 *
	 * @return string
	 */
	protected function combo_key(
		int $zoom,
		int $width,
		int $height,
		string $map_type = self::DEFAULT_MAP_TYPE
	): string {
		return sprintf(
			'%dx%dx%dx%s',
			$zoom,
			$width,
			$height,
			$this->normalize_map_type( $map_type )
		);
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
	 * @since 0.34.0
	 *
	 * @param int    $post_id Venue post ID.
	 * @param array  $info    Parsed venue information.
	 * @param int    $zoom     Zoom level to render at.
	 * @param int    $width    Pixel width of the PNG.
	 * @param int    $height   Pixel height of the PNG.
	 * @param string $map_type Map type slug for the render.
	 *
	 * @return array{url: string, url_2x: string, hash: string, zoom: int, width: int, height: int}|null
	 */
	protected function ensure_descriptor_for_combo(
		int $post_id,
		array $info,
		int $zoom,
		int $width,
		int $height,
		string $map_type = ''
	): ?array {
		$provider = Manager::get_instance()->get_active();
		if ( null === $provider ) {
			return null;
		}

		$map_type = $this->normalize_map_type( $map_type );

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

		$slug    = $provider->get_slug();
		$address = (string) ( $info['address'] ?? '' );
		$hash    = $this->hash_for( $info, $zoom, $width, $height, $slug, $map_type );
		$key     = $this->combo_key( $zoom, $width, $height, $map_type );

		$all      = $this->get_all_descriptors( $post_id );
		$existing = $all[ $slug ][ $key ] ?? null;

		$retina_enabled = $this->should_generate_retina();

		$expected_url = $this->build_image_url( $address, $zoom, $width, $height, $slug, 1, $map_type );
		$has_valid_1x = null !== $existing
			&& $existing['hash'] === $hash
			&& $existing['url'] === $expected_url;
		$needs_retina = $retina_enabled && ( null === $existing || '' === $existing['url_2x'] );

		if ( $has_valid_1x && ! $needs_retina ) {
			return $existing;
		}

		// Only re-render the 1× when it's genuinely missing or stale.
		// Legacy descriptors (pre-retina) land on the `has_valid_1x &&
		// needs_retina` path — their 1× PNG is still valid so we skip the
		// redundant render and only fill in the retina sibling.
		$url = null;
		if ( $has_valid_1x ) {
			$url = $existing['url'];
		} else {
			$image = $provider->render( $latitude, $longitude, $zoom, $width, $height, 1, $map_type );

			// Provider returned null — GD missing, network failure, or the
			// provider can't satisfy this combo. Surface as "no descriptor"
			// rather than persisting a half-baked entry.
			if ( null === $image ) {
				return null;
			}

			$url = $this->save_image( $image, $address, $zoom, $width, $height, 1, $slug, $map_type );
			imagedestroy( $image );

			if ( null === $url ) {
				return null;
			}
		}

		// Retina variant: ask the provider for a density-2 render. Failure
		// here is non-fatal — we keep the 1× URL and just leave `url_2x`
		// empty so the front-end falls back to a plain `src`. Reuses the
		// same hash: the 2× is fully derived from the same inputs.
		$url_2x = '';
		if ( $retina_enabled ) {
			$image_2x = $provider->render(
				$latitude,
				$longitude,
				$zoom,
				$width,
				$height,
				self::RETINA_DENSITY,
				$map_type
			);
			if ( null !== $image_2x ) {
				$saved_2x = $this->save_image(
					$image_2x,
					$address,
					$zoom,
					$width,
					$height,
					self::RETINA_DENSITY,
					$slug,
					$map_type
				);
				imagedestroy( $image_2x );
				if ( null !== $saved_2x ) {
					$url_2x = $saved_2x;
				}
			}
		}

		$all[ $slug ][ $key ] = array(
			'url'    => $url,
			'url_2x' => $url_2x,
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
			|| ! isset( $stored[ $slug ][ $key ]['url'] )
			|| $stored[ $slug ][ $key ]['url'] !== $url
		) {
			return null;
		}

		return $all[ $slug ][ $key ];
	}

	/**
	 * Whether the compositor should emit a retina (2×) variant alongside the 1× image.
	 *
	 * Default on — site owners who want to save disk can return false from
	 * the filter. Called per-combo so a filter can even target specific
	 * contexts (`is_admin()`, per-post, per-current-user).
	 *
	 * @since 0.34.0
	 *
	 * @return bool True when the retina variant should be generated.
	 */
	protected function should_generate_retina(): bool {
		/**
		 * Filter whether to generate the retina (2×) static-map variant.
		 *
		 * Disabling this halves the on-disk footprint at the cost of
		 * losing true retina sharpness on HiDPI displays — the browser
		 * will still upscale the 1× PNG, but labels and road lines will
		 * look softer than a native 2× render. Default true.
		 *
		 * @since 0.34.0
		 *
		 * @param bool $enabled Whether to generate the 2× variant.
		 */
		return (bool) apply_filters( 'gatherpress_static_map_generate_2x', true );
	}

	/**
	 * Compute a stable hash from the inputs that determine the rendered PNG.
	 *
	 * Any change to address, coords, zoom, width, height, or tile provider
	 * invalidates the previous image. Unrelated venue edits (title, excerpt,
	 * other meta) keep the same hash and skip regeneration.
	 *
	 * @since 0.34.0
	 *
	 * @param array  $info     Parsed venue information.
	 * @param int    $zoom     Map zoom level.
	 * @param int    $width    Output width.
	 * @param int    $height   Output height.
	 * @param string $provider Provider slug (e.g. `osm`).
	 * @param string $map_type Map type slug.
	 *
	 * @return string MD5 hex digest.
	 */
	public function hash_for(
		array $info,
		int $zoom,
		int $width,
		int $height,
		string $provider,
		string $map_type = self::DEFAULT_MAP_TYPE
	): string {
		$map_type = $this->normalize_map_type( $map_type );
		// md5() here is a non-cryptographic cache-key discriminator, matching class-geocoding.php.
		// CAUTION: the order and types of the values composed below define the
		// cache key for every static-map PNG on disk. Reordering or changing a
		// type (e.g. casting one of these to int) silently invalidates every
		// existing image and forces a full regeneration on the next save.
		return md5( // NOSONAR.
			implode(
				'|',
				array(
					(string) ( $info['address'] ?? '' ),
					(string) ( $info['latitude'] ?? '' ),
					(string) ( $info['longitude'] ?? '' ),
					(string) $zoom,
					(string) $width,
					(string) $height,
					$provider,
					$map_type,
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
	 * URL at matching dimensions — intentional dedupe. `$density > 1` appends
	 * the `@{density}x` suffix used by the retina variants.
	 *
	 * @since 0.34.0
	 *
	 * @param string $address  Venue address string.
	 * @param int    $zoom     Map zoom level.
	 * @param int    $width    Output width (at density 1).
	 * @param int    $height   Output height (at density 1).
	 * @param string $provider Provider slug (e.g. `osm`).
	 * @param int    $density  Pixel-density multiplier. 1 = standard, 2 = retina.
	 * @param string $map_type Map type slug.
	 *
	 * @return string Full public URL for the PNG.
	 */
	protected function build_image_url(
		string $address,
		int $zoom,
		int $width,
		int $height,
		string $provider,
		int $density = 1,
		string $map_type = self::DEFAULT_MAP_TYPE
	): string {
		$dirs     = wp_get_upload_dir();
		$base_url = trailingslashit( $dirs['baseurl'] ) . self::UPLOADS_SUBDIR;
		$filename = $this->filename_for( $address, $zoom, $width, $height, $provider, $density, $map_type );

		return trailingslashit( $base_url ) . $filename;
	}

	/**
	 * Compose a filesystem-safe filename from the address + dimensions.
	 *
	 * Address gets slugified via `sanitize_title()` and capped at 150 chars
	 * so the full filename stays comfortably under the 255-byte cap common
	 * to most filesystems. An empty or all-special-character address falls
	 * back to `venue` so there's always something before the dimension
	 * suffix. `$density > 1` appends the `@{density}x` suffix used by the
	 * retina variants, e.g. `venue-12-800-300@2x.png`.
	 *
	 * @since 0.34.0
	 *
	 * @param string $address  Venue address.
	 * @param int    $zoom     Map zoom level.
	 * @param int    $width    Output width (at density 1).
	 * @param int    $height   Output height (at density 1).
	 * @param string $provider Provider slug (e.g. `osm`) — namespaces the file
	 *                         so OSM and Google PNG files can coexist on disk.
	 * @param int    $density  Pixel-density multiplier. 1 = standard, 2 = retina.
	 * @param string $map_type Map type slug.
	 *
	 * @return string Filename including the `.png` extension.
	 */
	protected function filename_for(
		string $address,
		int $zoom,
		int $width,
		int $height,
		string $provider,
		int $density = 1,
		string $map_type = self::DEFAULT_MAP_TYPE
	): string {
		$map_type = $this->normalize_map_type( $map_type );
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

		$suffix = $density > 1 ? sprintf( '@%dx', $density ) : '';

		return sprintf( '%s-%s-%s-%d-%d-%d%s.png', $slug, $provider, $map_type, $zoom, $width, $height, $suffix );
	}

	/**
	 * Save a finished GD image to the uploads directory and return its URL.
	 *
	 * Filename is derived from `(address, provider, zoom, width, height)` so
	 * different providers' PNG files coexist on disk and two venues at the same
	 * address share one file at matching dimensions. `imagepng` overwrites
	 * in place, which is fine for the regenerate flow since the inputs
	 * that'd change visible output also change the hash in the descriptor.
	 *
	 * @since 0.34.0
	 *
	 * @param GdImage|resource $image    Finished image from a provider's `render()`.
	 * @param string           $address  Venue address (slugified for the filename).
	 * @param int              $zoom     Map zoom level.
	 * @param int              $width    Output width (at density 1).
	 * @param int              $height   Output height (at density 1).
	 * @param int              $density  Pixel-density multiplier. 1 = standard, 2 = retina.
	 * @param string           $provider Provider slug.
	 * @param string           $map_type Map type slug.
	 *
	 * @return string|null Public URL of the saved file, or null on failure.
	 */
	public function save_image(
		$image,
		string $address,
		int $zoom,
		int $width,
		int $height,
		int $density,
		string $provider,
		string $map_type = self::DEFAULT_MAP_TYPE
	): ?string {
		$dirs = wp_get_upload_dir();

		if ( ! empty( $dirs['error'] ) ) {
			return null;
		}

		$base_dir = trailingslashit( $dirs['basedir'] ) . self::UPLOADS_SUBDIR;
		$base_url = trailingslashit( $dirs['baseurl'] ) . self::UPLOADS_SUBDIR;
		$filename = $this->filename_for( $address, $zoom, $width, $height, $provider, $density, $map_type );
		$path     = trailingslashit( $base_dir ) . $filename;

		// Filesystem failure modes — uploads/ not writable, disk full, or
		// directory loses write permission between mkdir and imagepng. Neither
		// branch is reliably reproducible in a unit test without breaking the
		// test filesystem; the `||` short-circuits on the happy path.
		if ( ! wp_mkdir_p( $base_dir ) || ! imagepng( $image, $path ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		return trailingslashit( $base_url ) . $filename;
	}

	/**
	 * Coerce a stored coordinate string to a float, or null for non-numeric input.
	 *
	 * Stored coordinates are strings ("40.7128"); empty string / "null" / text
	 * should not generate a bogus (0, 0) map off the west coast of Africa.
	 *
	 * @since 0.34.0
	 *
	 * @param mixed $raw Raw coordinate from venue information.
	 *
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
	 * `gatherpress_map_zoom` filter so code-level overrides
	 * (themes, site-specific plugins) can still take precedence.
	 *
	 * @since 0.34.0
	 *
	 * @return int
	 */
	protected function get_zoom(): int {
		$setting = (int) Settings::get_instance()->get( 'venue_map_default_zoom' );
		$default = $setting > 0 ? $setting : self::DEFAULT_ZOOM;

		/**
		 * Filter the zoom level used when rendering the static venue map.
		 *
		 * @since 0.34.0
		 *
		 * @param int $zoom Default zoom level.
		 */
		$zoom = (int) apply_filters( 'gatherpress_map_zoom', $default );

		return $this->clamp_zoom( $zoom );
	}

	/**
	 * Height (in pixels) used by the generator and by the block.
	 *
	 * Mirrors {@see self::get_zoom()} — Settings value wins, falls back to
	 * {@see self::DEFAULT_HEIGHT}, then runs through
	 * `gatherpress_map_height` for code-level overrides. Because the
	 * PNG is rendered at exactly this height (no oversampling), the generator
	 * and the block see the same value and Leaflet's zoom matches the
	 * static map's zoom visually.
	 *
	 * @since 0.34.0
	 *
	 * @return int
	 */
	protected function get_height(): int {
		$setting = (int) Settings::get_instance()->get( 'venue_map_default_height' );
		$default = $setting > 0 ? $setting : self::DEFAULT_HEIGHT;

		/**
		 * Filter the height used when rendering the static venue map.
		 *
		 * @since 0.34.0
		 *
		 * @param int $height Default height in pixels.
		 */
		$height = (int) apply_filters( 'gatherpress_map_height', $default );

		return $this->clamp_height( $height );
	}

	/**
	 * Normalize a map type slug for static-map cache keys, hashes, and filenames.
	 *
	 * Lowercases and trims the input, falls back to the site-wide default
	 * from Settings when empty, and coerces any unrecognized slug to
	 * `roadmap`. Provider capability is intentionally NOT enforced here: the
	 * cache key records the *requested* type so a later platform switch
	 * (e.g. OSM → Google) still resolves to the correct stored entry.
	 * Providers self-guard at render time — Google coerces types it can't
	 * satisfy to `roadmap` internally, and OSM ignores the type entirely.
	 *
	 * @since 0.35.0
	 *
	 * @param string $map_type Raw map type from block attrs or REST.
	 *
	 * @return string
	 */
	public function normalize_map_type( string $map_type ): string {
		$map_type = strtolower( trim( $map_type ) );

		if ( '' === $map_type ) {
			$map_type = (string) Settings::get_instance()->get( 'venue_map_default_type' );
		}

		if ( ! in_array( $map_type, array( 'roadmap', 'satellite', 'hybrid', 'terrain' ), true ) ) {
			$map_type = self::DEFAULT_MAP_TYPE;
		}

		return $map_type;
	}

	/**
	 * Clamp a zoom level to the supported range.
	 *
	 * @since 0.34.0
	 *
	 * @param int $zoom Raw zoom value.
	 *
	 * @return int
	 */
	protected function clamp_zoom( int $zoom ): int {
		return max( self::ZOOM_MIN, min( self::ZOOM_MAX, $zoom ) );
	}

	/**
	 * Clamp a pixel height to the supported range.
	 *
	 * @since 0.34.0
	 *
	 * @param int $height Raw height value.
	 *
	 * @return int
	 */
	protected function clamp_height( int $height ): int {
		return max( self::HEIGHT_MIN, min( self::HEIGHT_MAX, $height ) );
	}

	/**
	 * Clamp a pixel width to the supported range.
	 *
	 * @since 0.34.0
	 *
	 * @param int $width Raw width value.
	 *
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
	 * @since 0.34.0
	 *
	 * @param string $ratio Raw aspect-ratio string.
	 *
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
	 * @since 0.34.0
	 *
	 * @param int    $width   Block width (0 = auto).
	 * @param int    $height  Block height (0 = auto).
	 * @param string $ratio   Aspect-ratio string (e.g. "16/9").
	 *
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
}
