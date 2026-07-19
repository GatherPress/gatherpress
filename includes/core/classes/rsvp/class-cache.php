<?php
/**
 * Manages RSVP caches.
 *
 * This class is responsible for caching RSVP information.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class of RSVP caches.
 *
 * This class is responsible for caching RSVP information.
 *
 * @since 0.35.0
 */
class Cache {

	/**
	 * Cache key format for RSVPs.
	 *
	 * @since 0.35.0
	 *
	 * @var string $CACHE_KEY
	 */
	const CACHE_KEY = 'gatherpress_rsvp_%d';

	/**
	 * Lifetime of an RSVP cache entry, in seconds.
	 *
	 * @since 0.35.0
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 15 * MINUTE_IN_SECONDS;

	/**
	 * Get the RSVP cache for an event by the event's WordPress post ID.
	 *
	 * Backed by a transient so the cache persists across requests on any
	 * site, and is transparently served from a persistent object cache
	 * (Redis / Memcached) when one is enabled.
	 *
	 * @since 0.35.0
	 *
	 * @param int $post_id The WordPress post ID of the event.
	 *
	 * @return array|null The cached RSVP data, or null when no valid cache exists.
	 */
	public static function get( int $post_id ) {
		$value = get_transient( self::cache_key( $post_id ) );

		if ( empty( $value ) || ! is_array( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Set a GatherPress RSVP cache.
	 *
	 * @since 0.35.0
	 *
	 * @param int   $post_id The WordPress post ID of the event.
	 * @param mixed $value   The cache value to set.
	 *
	 * @return void
	 */
	public static function set( int $post_id, $value ) {
		set_transient( self::cache_key( $post_id ), $value, self::CACHE_EXPIRATION );
	}

	/**
	 * Delete an RSVP cache for an event.
	 *
	 * @since 0.35.0
	 *
	 * @param int $post_id The WordPress post ID of the event.
	 *
	 * @return void
	 */
	public static function delete( int $post_id ) {
		delete_transient( self::cache_key( $post_id ) );
	}

	/**
	 * Get the cache key.
	 *
	 * @since 0.35.0
	 *
	 * @param mixed $post_id The WordPress post ID of the event.
	 *
	 * @return string The cache key for the given post ID.
	 */
	private static function cache_key( $post_id ) {
		return sprintf( self::CACHE_KEY, $post_id );
	}
}
