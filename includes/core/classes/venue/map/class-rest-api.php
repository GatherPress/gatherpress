<?php
/**
 * Handles the registration of venue-map REST API endpoints.
 *
 * This file contains the Rest_Api class, which owns the REST surface of the
 * venue map subsystem: route registration, the shared combo argument
 * definitions, request parsing, and the regenerate endpoint handler. The
 * map domain operations themselves (generation, caching, descriptors) stay
 * on {@see Map} — this class is the thin HTTP adapter in front of them.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.35.0
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Venue;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Rest_Api.
 *
 * Registers and handles the venue-map REST API endpoints. Mirrors the
 * shape of `Event\Rest_Api` — one class per subsystem owning its whole
 * REST surface.
 *
 * @since 0.35.0
 */
final class Rest_Api {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.35.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register REST endpoints for the venue-map block.
	 *
	 * Today there's a single endpoint that forces the static PNG files for a
	 * venue to regenerate — used by the "Regenerate Map" button in the
	 * block editor when a tile provider changes or a render gets out of
	 * sync with the venue's current inputs.
	 *
	 * @since 0.34.0
	 * @since 0.35.0 Moved from `Map::register_rest_routes()`.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
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

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/venue/(?P<id>\d+)/regenerate-map',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_regenerate' ),
				'permission_callback' => $permission,
				'args'                => array_merge(
					array( 'id' => $id_arg ),
					self::route_args()
				),
			)
		);
	}

	/**
	 * Shared REST argument definitions for map combo requests.
	 *
	 * @since 0.35.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function route_args(): array {
		return array(
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
						Map::ASPECT_RATIO_PATTERN,
						(string) $value
					);
				},
			),
			'map_type'     => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => static function ( $value ): bool {
					if ( '' === $value || null === $value ) {
						return true;
					}
					return in_array(
						(string) $value,
						array( 'roadmap', 'satellite', 'hybrid', 'terrain' ),
						true
					);
				},
			),
			'ensure_only'  => array(
				'required'          => false,
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			),
		);
	}

	/**
	 * Parse combo fields from a venue-map REST request body.
	 *
	 * @since 0.35.0
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return array{zoom: int|null, width: int|null, height: int|null, aspect_ratio: string, map_type: string}
	 */
	public static function parse_request( WP_REST_Request $request ): array {
		// Pass the block's current combo through so its PNG is generated
		// even when the venue has never been rendered at those dimensions.
		// `width` and `height` may be 0 / omitted (meaning "auto") — the
		// aspect-ratio hint then drives whichever dimension is missing.
		$raw_zoom   = $request['zoom'] ?? null;
		$raw_width  = $request['width'] ?? null;
		$raw_height = $request['height'] ?? null;

		return array(
			'zoom'         => ( null !== $raw_zoom && (int) $raw_zoom > 0 ) ? (int) $raw_zoom : null,
			'width'        => ( null !== $raw_width && (int) $raw_width >= 0 ) ? (int) $raw_width : null,
			'height'       => ( null !== $raw_height && (int) $raw_height >= 0 ) ? (int) $raw_height : null,
			'aspect_ratio' => (string) ( $request['aspect_ratio'] ?? '' ),
			'map_type'     => (string) ( $request['map_type'] ?? '' ),
		);
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
	 * @since 0.35.0 Moved from `Map::rest_regenerate()`.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response
	 */
	public function rest_regenerate( WP_REST_Request $request ): WP_REST_Response {
		nocache_headers();

		$map         = Map::get_instance();
		$post_id     = (int) $request['id'];
		$venue       = new Venue( $post_id );
		$info        = $venue->get_information();
		$latitude    = $map->parse_coord( $info['latitude'] );
		$longitude   = $map->parse_coord( $info['longitude'] );
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

		$combo       = self::parse_request( $request );
		$ensure_only = rest_sanitize_boolean( $request['ensure_only'] ?? false );

		$descriptors = $ensure_only
			? $map->ensure_combo( $post_id, $combo )
			: $map->regenerate( $post_id, $combo );

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
}
