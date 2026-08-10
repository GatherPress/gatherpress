<?php
/**
 * Owns the venue post-meta surface.
 *
 * Registers the editor-writable, structured-address, and Map descriptor
 * post meta for any post type that declares `gatherpress-venue-information`
 * support, plus the matching map-display meta for `gatherpress-venue-map`.
 * Also owns the field-list constants and the REST readonly-strip filter.
 *
 * Sibling singleton to `Venue\Setup` — `Setup` keeps post-type / taxonomy
 * registration and lifecycle hooks, `Meta` keeps everything that touches
 * `register_post_meta()`.
 *
 * @package GatherPress\Core\Venue
 * @since 0.34.0
 */

namespace GatherPress\Core\Venue;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Validate;
use GatherPress\Core\Venue\Map;
use stdClass;
use WP_REST_Request;

/**
 * Class Meta.
 *
 * Singleton owning venue post-meta registration. Hooks
 * `registered_post_type` so any post type that declares the venue
 * supports — including companion-plugin types — picks up the same meta
 * shape without separate wiring.
 *
 * @since 0.34.0
 */
final class Meta {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Suffixes of the editor-writable venue-information meta keys.
	 *
	 * These five keys are written by the editor (and by trusted server code
	 * such as REST PATCH requests gated by `Utility::can_edit_post_meta()`).
	 * Stored here as the unprefixed "field" form so consumers can either
	 * iterate them directly (e.g. {@see Venue::get_information()}) or map
	 * through {@see Utility::prefix_key()} to get the full meta keys.
	 *
	 * Single source of truth for: meta registration in {@see self::register()}
	 * and the editor-writable half of `Venue::get_information()`. Pair with
	 * {@see self::STRUCTURED_ADDRESS_FIELDS} for the readonly Photon-derived
	 * counterpart; together the two arrays make up the full 13-field shape
	 * returned by `Venue::get_information()`.
	 *
	 * @since 0.34.0
	 * @var string[]
	 */
	public const EDITOR_WRITABLE_FIELDS = array(
		'address',
		'latitude',
		'longitude',
		'phone',
		'website',
	);

	/**
	 * Suffixes of the structured-address venue meta keys.
	 *
	 * These eight keys are derived from `gatherpress_address` by the async
	 * geocode cron handler — populated server-side from the Photon response,
	 * never written through the REST API. Stored here as the unprefixed
	 * "field" form so consumers can either iterate them directly (e.g.
	 * {@see Venue::get_information()}) or map through {@see Utility::prefix_key()}
	 * to get the full meta keys (e.g. registration in {@see self::register()}).
	 *
	 * Single source of truth for: meta registration, REST readonly stripping,
	 * cron handler write loop, and `Venue::get_information()` field list.
	 *
	 * @since 0.34.0
	 * @var string[]
	 */
	public const STRUCTURED_ADDRESS_FIELDS = array(
		'house_number',
		'street',
		'city',
		'county',
		'state',
		'postcode',
		'country',
		'country_code',
	);

	/**
	 * Class constructor.
	 *
	 * Wires the `registered_post_type` listener so any post type that
	 * declares venue support picks up the same meta shape without
	 * separate per-type registration calls.
	 *
	 * @since 0.34.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for venue meta registration.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'registered_post_type', array( $this, 'register' ) );
	}

	/**
	 * Sanitize callback for venue coordinate meta (latitude / longitude).
	 *
	 * Numeric values within the ±180 range are normalized to a string form via
	 * a float cast (trims whitespace, normalizes scientific notation). Anything
	 * else collapses to an empty string — the "no coords yet" sentinel the
	 * editor and `Map::parse_coord()` already treat as unset.
	 *
	 * @since 0.34.0
	 *
	 * @param mixed $value The submitted value.
	 *
	 * @return string Normalized coordinate string, or '' for invalid / unset input.
	 */
	public function sanitize_coordinate( $value ): string {
		return Validate::coordinate( $value ) ? (string) (float) $value : '';
	}

	/**
	 * Registers venue meta for any post type that declares the relevant
	 * supports. Two bands:
	 *
	 * - `gatherpress-venue-information` gets the editor-writable address /
	 *   contact keys, the read-only structured-address keys, the static-
	 *   map descriptor key, and the REST readonly-strip filter.
	 * - `gatherpress-venue-map` gets the map display-settings keys
	 *   (show / zoom / height).
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function register( string $post_type ): void {
		if ( post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			$this->register_venue_information_meta( $post_type );
		}

		if ( post_type_supports( $post_type, 'gatherpress-venue-map' ) ) {
			$this->register_venue_map_meta( $post_type );
		}
	}

	/**
	 * Registers the editor-writable venue keys, the structured-address
	 * keys, the static-map descriptor key, and the REST readonly-strip
	 * filter for a post type that declares `gatherpress-venue-information`
	 * support.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type to register against.
	 *
	 * @return void
	 */
	protected function register_venue_information_meta( string $post_type ): void {
		// Per-field sanitize_callback for the editor-writable venue-information
		// fields. Keys must match `self::EDITOR_WRITABLE_FIELDS` exactly so
		// the loop below can resolve a callback for each entry.
		$editor_writable_sanitizers = array(
			'address'   => 'sanitize_text_field',
			'latitude'  => array( $this, 'sanitize_coordinate' ),
			'longitude' => array( $this, 'sanitize_coordinate' ),
			'phone'     => 'sanitize_text_field',
			'website'   => 'sanitize_url',
		);

		// Map keeps a per-zoom descriptor map here: { "15": { url, hash },
		// ... }. Exposed read-only via REST so the block editor can preview
		// the cached static image when the user picks renderMode="static".
		// Writes are denied — the server-side pipeline is the only thing
		// allowed to populate this meta. Registered separately from the
		// editor-writable loop because it's neither editor-writable nor
		// share the standard string-meta args shape.
		$map_meta_args = array(
			'auth_callback' => '__return_false',
			'show_in_rest'  => array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'       => 'object',
						'properties' => array(
							'url'  => array( 'type' => 'string' ),
							'hash' => array( 'type' => 'string' ),
						),
					),
				),
			),
			'single'        => true,
			'type'          => 'object',
		);

		$supports_revisions = post_type_supports( $post_type, 'revisions' );

		// Editor-writable fields share a common args shape; only the
		// per-field sanitize_callback varies. Iterate the constant so
		// adding a new field is a single edit at the top of this class.
		foreach ( self::EDITOR_WRITABLE_FIELDS as $field ) {
			$args = array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => $editor_writable_sanitizers[ $field ],
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'revisions_enabled' => true,
			);

			// revisions_enabled is only valid when the post type supports revisions.
			// Silently drop it for venue post types that opt out (e.g. companion plugins
			// registering a minimal venue post type without revisions support).
			if ( ! $supports_revisions ) {
				unset( $args['revisions_enabled'] );
			}

			register_post_meta( $post_type, Utility::prefix_key( $field ), $args );
		}

		// Map descriptors register on their own — different shape (object
		// schema, no sanitize_callback) and not editor-writable.
		register_post_meta( $post_type, Map::META_KEY, $map_meta_args );

		// Structured-address meta share an identical args shape, so
		// register them in a tight loop rather than duplicating the
		// array literal eight times. `auth_callback` denies REST writes
		// — these are populated by the async geocode cron handler, not
		// the editor.
		$structured_args = array(
			'auth_callback'     => '__return_false',
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'default'           => '',
			'revisions_enabled' => true,
		);

		if ( ! $supports_revisions ) {
			unset( $structured_args['revisions_enabled'] );
		}

		// Structured-address pieces derived from `gatherpress_address` by an
		// async cron handler that fires only when the address actually
		// changes. Read access via REST stays open so JSON-LD / schema.org
		// emitters and downstream API consumers can read them; writes go
		// through `filter_readonly_meta()` which strips them before the
		// auth_callback would otherwise 403 the whole request.
		$structured_address_meta = array_map(
			array( Utility::class, 'prefix_key' ),
			self::STRUCTURED_ADDRESS_FIELDS
		);

		foreach ( $structured_address_meta as $meta_key ) {
			register_post_meta( $post_type, $meta_key, $structured_args );
		}

		// Strip read-only meta from REST requests so the editor can't write it directly.
		// Belt-and-suspenders with `auth_callback => __return_false`: the
		// strip filter runs in `rest_pre_insert_<post_type>` BEFORE the
		// auth_callback would 403 the whole request, so a co-submitted
		// PATCH with a writable field plus a readonly field succeeds for
		// the writable subset rather than failing the whole payload.
		// Both are required — removing either breaks the contract.
		add_filter(
			sprintf( 'rest_pre_insert_%s', $post_type ),
			array( $this, 'filter_readonly_meta' ),
			10,
			2
		);
	}

	/**
	 * Registers the venue-map display-settings keys (show / zoom /
	 * height) for a post type that declares `gatherpress-venue-map`
	 * support.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type to register against.
	 *
	 * @return void
	 */
	protected function register_venue_map_meta( string $post_type ): void {
		$venue_map_meta = array(
			'gatherpress_map_show'   => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => true,
			),
			'gatherpress_map_zoom'   => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 10,
			),
			'gatherpress_map_height' => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 300,
			),
		);

		foreach ( $venue_map_meta as $meta_key => $args ) {
			register_post_meta( $post_type, $meta_key, $args );
		}
	}

	/**
	 * Filter out read-only meta from REST API requests.
	 *
	 * Some venue meta keys are populated by server-side pipelines (e.g. the
	 * Map static map descriptors) and should never be written by the
	 * block editor. Values submitted for those keys are silently discarded
	 * rather than triggering a permission error from the __return_false auth
	 * callback.
	 *
	 * @since 0.34.0
	 *
	 * @param stdClass        $prepared_post An object representing a single post prepared for inserting or updating.
	 * @param WP_REST_Request $request       Request object.
	 *
	 * @return stdClass The prepared post object.
	 */
	public function filter_readonly_meta( stdClass $prepared_post, WP_REST_Request $request ): stdClass {
		// Structured-address fields are derived from `gatherpress_address`
		// by the async geocode cron handler. REST writes are stripped
		// rather than rejected so a client that PATCHes them alongside
		// editor-writable fields doesn't fail the whole request.
		$readonly_keys = array_merge(
			array( Map::META_KEY ),
			array_map(
				array( Utility::class, 'prefix_key' ),
				self::STRUCTURED_ADDRESS_FIELDS
			)
		);

		$meta = $request->get_param( 'meta' );

		if ( is_array( $meta ) ) {
			foreach ( $readonly_keys as $key ) {
				unset( $meta[ $key ] );
			}

			$request->set_param( 'meta', $meta );
		}

		return $prepared_post;
	}
}
