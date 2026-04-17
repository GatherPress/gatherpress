<?php
/**
 * Per-post instance class for a single Venue.
 *
 * A `Venue` is constructed around a specific venue post ID and exposes accessors
 * for that venue's stored information (address, coordinates, contact details).
 * WordPress-level registration (post type, taxonomy, save hooks, template
 * seeding) lives on {@see Venue_Setup}.
 *
 * Class constants (`POST_TYPE`, `TAXONOMY`) and pure static utilities
 * (`get_taxonomy()`, `get_venue_post_type()`, `get_venue_post_type_map()`,
 * `get_localized_post_type_slug()`) also live here because they are intrinsic
 * to "what a venue is" rather than "how WordPress registers it."
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Venue.
 *
 * Instance anchored to a specific venue post ID. Pair with {@see Venue_Setup}
 * for the WordPress integration layer.
 *
 * @since 1.0.0
 */
class Venue {
	/**
	 * Default venue post type slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const POST_TYPE = 'gatherpress_venue';

	/**
	 * Default venue taxonomy slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TAXONOMY = '_gatherpress_venue';

	/**
	 * Venue post ID this instance wraps.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	protected int $post_id;

	/**
	 * Construct a Venue around a specific post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The ID of the venue post.
	 */
	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * Returns the venue post ID this instance wraps.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_post_id(): int {
		return $this->post_id;
	}

	/**
	 * Returns the parsed venue information for this venue.
	 *
	 * Reads the `gatherpress_venue_information` JSON meta and returns its
	 * components with empty-string fallbacks for any missing keys, so callers
	 * can treat the array shape as stable.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     fullAddress: string,
	 *     phoneNumber: string,
	 *     website: string,
	 *     latitude: string,
	 *     longitude: string
	 * }
	 */
	public function get_information(): array {
		$raw    = (string) get_post_meta( $this->post_id, 'gatherpress_venue_information', true );
		$parsed = json_decode( $raw, true );

		if ( ! is_array( $parsed ) ) {
			$parsed = array();
		}

		return array(
			'fullAddress' => isset( $parsed['fullAddress'] ) ? (string) $parsed['fullAddress'] : '',
			'phoneNumber' => isset( $parsed['phoneNumber'] ) ? (string) $parsed['phoneNumber'] : '',
			'website'     => isset( $parsed['website'] ) ? (string) $parsed['website'] : '',
			'latitude'    => isset( $parsed['latitude'] ) ? (string) $parsed['latitude'] : '',
			'longitude'   => isset( $parsed['longitude'] ) ? (string) $parsed['longitude'] : '',
		);
	}

	/**
	 * Returns the taxonomy slug for a given venue post type.
	 *
	 * The taxonomy slug is always derived by prepending an underscore to the venue
	 * post type slug — for example, 'gatherpress_venue' uses '_gatherpress_venue'.
	 * Custom venue post types follow the same convention automatically.
	 *
	 * @since 1.0.0
	 *
	 * @param string $venue_post_type The venue post type slug. Defaults to the built-in venue post type.
	 * @return string The taxonomy slug for the given venue post type.
	 */
	public static function get_taxonomy( string $venue_post_type = '' ): string {
		if ( ! $venue_post_type ) {
			$venue_post_type = self::POST_TYPE;
		}

		return '_' . $venue_post_type;
	}

	/**
	 * Get the venue post type slug for a given event post type.
	 *
	 * Applies the 'gatherpress_venue_post_type' filter so developers can map
	 * custom event post types to their own venue post types.
	 *
	 * Results are cached in a static array for the lifetime of the request to
	 * avoid repeated filter invocations. If a plugin adds or removes the
	 * 'gatherpress_venue_post_type' filter after this method has already been
	 * called for a given event post type, the cached value will be returned
	 * rather than the updated filter result. This is an unlikely edge case in
	 * normal WordPress request flow, where filters are registered before any
	 * post-type lookups occur.
	 *
	 * @since 1.0.0
	 *
	 * @param string $event_post_type The event post type requesting a venue post type.
	 * @return string The venue post type slug.
	 */
	public static function get_venue_post_type( string $event_post_type = '' ): string {
		static $cache = array();

		if ( isset( $cache[ $event_post_type ] ) ) {
			return $cache[ $event_post_type ];
		}

		/**
		 * Filters the post type used as the venue.
		 *
		 * @since 1.0.0
		 *
		 * @param string $post_type       The venue post type slug. Default 'gatherpress_venue'.
		 * @param string $event_post_type The event post type requesting a venue post type.
		 */
		$cache[ $event_post_type ] = (string) apply_filters(
			'gatherpress_venue_post_type',
			self::POST_TYPE,
			$event_post_type
		);

		return $cache[ $event_post_type ];
	}

	/**
	 * Returns a map of event post types to their corresponding venue post types.
	 *
	 * Iterates over all post types that support 'gatherpress-venue' and resolves
	 * the venue post type for each via get_venue_post_type(). This map is used
	 * to expose the per-event-type venue post type to the block editor.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of event post type slug to venue post type slug.
	 */
	public static function get_venue_post_type_map(): array {
		$map = array();

		foreach ( get_post_types_by_support( 'gatherpress-venue' ) as $event_post_type ) {
			$map[ $event_post_type ] = self::get_venue_post_type( $event_post_type );
		}

		return $map;
	}

	/**
	 * Returns the post type slug localized for the site language and sanitized as URL part.
	 *
	 * Do not use this directly, use get( 'venues_url' ) instead.
	 *
	 * This method switches to the sites default language and gets the translation of 'venues' for the loaded locale.
	 * After that, the method sanitizes the string to be safely used within an URL,
	 * by removing accents, replacing special characters and replacing whitespace with dashes.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_localized_post_type_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );
		$slug            = _x( 'Venue', 'Admin menu and post type singular name', 'gatherpress' );
		$slug            = sanitize_title( $slug );

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		return $slug;
	}
}
