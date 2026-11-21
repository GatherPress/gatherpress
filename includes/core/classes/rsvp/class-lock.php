<?php
/**
 * Serializes RSVP capacity changes for an event.
 *
 * @package GatherPress\Core\Rsvp
 * @since TBD
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Runs code that reads an event's attendee count and then changes an RSVP's status one request at a time.
 *
 * Uses the database's named lock, which the database releases when the
 * request ends, even if it fails. On SQLite, such as WordPress Playground,
 * the lock functions do nothing, and the code runs unlocked.
 *
 * @since TBD
 */
final class Lock {

	/**
	 * Seconds to wait for the lock before running anyway.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const TIMEOUT = 10;

	/**
	 * Event post IDs whose lock this request holds.
	 *
	 * @since TBD
	 *
	 * @var array<int, true>
	 */
	private static array $held = array();

	/**
	 * Runs a callback while holding an event's lock.
	 *
	 * @since TBD
	 *
	 * @param int      $post_id  The event post ID.
	 * @param callable $callback The code to run.
	 *
	 * @return mixed The callback's return value.
	 */
	public static function run( int $post_id, callable $callback ): mixed {
		global $wpdb;

		// Re-entrant, so code holding the lock can call code that takes it.
		if ( isset( self::$held[ $post_id ] ) ) {
			return $callback();
		}

		$name = self::get_name( $post_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock, not data.
		$wpdb->query( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $name, self::TIMEOUT ) );

		self::$held[ $post_id ] = true;

		try {
			return $callback();
		} finally {
			unset( self::$held[ $post_id ] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock, not data.
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) );
		}
	}

	/**
	 * Gets the name of an event's lock.
	 *
	 * Named locks are shared by every database on the server, so the table
	 * prefix keeps sites apart. MySQL allows 64 characters, so a long prefix
	 * is cut from the front, keeping the post ID.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return string The lock name.
	 */
	public static function get_name( int $post_id ): string {
		global $wpdb;

		return substr( sprintf( '%sgatherpress_rsvp_%d', $wpdb->prefix, $post_id ), -64 );
	}
}
