<?php
/**
 * Shared Geodata standard (`geo_*`) read-only meta registration, reused by
 * the venue and event `Meta` classes so the field list and registration
 * shape live in one place instead of being duplicated per domain.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Geo_Sync.
 *
 * @since TBD
 */
final class Geo_Sync {

	/**
	 * The Geodata standard meta keys, shared by the venue and event bands.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const FIELDS = array( 'geo_latitude', 'geo_longitude', 'geo_address', 'geo_public' );

	/**
	 * Registers the read-only Geodata standard meta for a post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type to register against.
	 *
	 * @return void
	 */
	public static function register_meta( string $post_type ): void {
		$string_args = array(
			'auth_callback'     => '__return_false',
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'default'           => '',
		);

		register_post_meta( $post_type, 'geo_latitude', $string_args );
		register_post_meta( $post_type, 'geo_longitude', $string_args );
		register_post_meta( $post_type, 'geo_address', $string_args );

		register_post_meta(
			$post_type,
			'geo_public',
			array(
				'auth_callback'     => '__return_false',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 0,
			)
		);
	}

	/**
	 * Builds the `geo_*` values from venue information.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $information Venue information; an empty array clears the location.
	 * @param bool                 $is_public   Whether the location may be shown publicly.
	 *
	 * @return array<string, string|int> The values keyed by meta key.
	 */
	public static function build_values( array $information, bool $is_public ): array {
		return array(
			'geo_latitude'  => (string) ( $information['latitude'] ?? '' ),
			'geo_longitude' => (string) ( $information['longitude'] ?? '' ),
			'geo_address'   => (string) ( $information['address'] ?? '' ),
			'geo_public'    => $is_public ? 1 : 0,
		);
	}

	/**
	 * Writes `geo_*` values to a post, skipping keys that are already current.
	 *
	 * @since TBD
	 *
	 * @param int                       $post_id The post ID.
	 * @param array<string, string|int> $values  Values from {@see self::build_values()}.
	 *
	 * @return bool Whether any value changed.
	 */
	public static function write( int $post_id, array $values ): bool {
		$changed = false;

		foreach ( $values as $key => $value ) {
			$current = get_post_meta( $post_id, $key, true );
			// get_post_meta() always returns a string, so cast geo_public to int.
			$current = 'geo_public' === $key ? (int) $current : (string) $current;

			// A missing key reads as ''/0, so check existence too or a 0/'' value is never stored.
			if ( $current !== $value || ! metadata_exists( 'post', $post_id, $key ) ) {
				update_post_meta( $post_id, $key, $value );
				$changed = true;
			}
		}

		return $changed;
	}
}
