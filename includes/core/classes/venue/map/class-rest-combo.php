<?php
/**
 * REST helpers for venue static-map combo requests.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.35.0
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_REST_Request;

/**
 * Class Rest_Combo.
 *
 * Shared REST argument definitions and request parsing for the venue-map
 * regenerate endpoint.
 *
 * @since 0.35.0
 */
class Rest_Combo {

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
}
