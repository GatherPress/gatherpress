<?php
/**
 * Manages RSVP caches.
 *
 * This class is responsible for caching RSVP information.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class of RSVP caches.
 *
 * This class is responsible for caching RSVP information.
 *
 * @since 1.0.0
 */
class Cache {
	/**
	 * Cache key format for RSVPs.
	 *
	 * @since 1.0.0
	 * @var string $CACHE_KEY
	 */
	const CACHE_KEY = 'gatherpress_rsvp_%d';

	/**
	 * Get the RSVP cache for an Event by the events WordPress post ID.
	 *
	 * @param int $post_id The WordPress post ID of the event.
	 * @return array|null
	 */
	public static function get( int $post_id ) {
		$value = wp_cache_get( self::cache_key( $post_id ), GATHERPRESS_CACHE_GROUP );

		if ( empty( $value ) || ! \is_array( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Set a GatherPress RSVP cache.
	 *
	 * @param int   $post_id The WordPress post ID of the event.
	 * @param mixed $value   The cache value to set.
	 * @return void
	 */
	public static function set( int $post_id, $value ) {
		wp_cache_set( self::cache_key( $post_id ), $value, GATHERPRESS_CACHE_GROUP, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Delete an RSVP cache for an event.
	 *
	 * @param int $post_id The WordPress post ID of the event.
	 * @return void
	 */
	public static function delete( int $post_id ) {
		wp_cache_delete( self::cache_key( $post_id ), GATHERPRESS_CACHE_GROUP );
	}

	/**
	 * Get the cache key.
	 *
	 * @param mixed $post_id The WordPress post ID of the event.
	 * @return string
	 */
	private static function cache_key( $post_id ) {
		return \sprintf( self::CACHE_KEY, $post_id );
	}
}
