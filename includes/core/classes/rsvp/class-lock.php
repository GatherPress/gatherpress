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
 * The lock is a row in the options table, inserted with INSERT IGNORE the
 * way WordPress core's upgrader lock is, so it works on every database
 * WordPress supports. It is re-entrant within a request.
 *
 * @since TBD
 */
final class Lock {

	/**
	 * Option name format for an event's lock.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const OPTION_NAME = 'gatherpress_rsvp_lock_%d';

	/**
	 * Seconds after which a lock left by a failed request is taken over.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const EXPIRES = 30;

	/**
	 * Microseconds to wait between attempts.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const WAIT = 100000;

	/**
	 * Attempts before running without the lock.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const ATTEMPTS = 50;

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
	 * If the lock stays busy past every attempt, the callback runs anyway, so
	 * an RSVP is never lost to a lock.
	 *
	 * @since TBD
	 *
	 * @param int      $post_id  The event post ID.
	 * @param callable $callback The code to run.
	 * @param int      $attempts Optional. How many times to try for the lock.
	 *
	 * @return mixed The callback's return value.
	 */
	public static function run( int $post_id, callable $callback, int $attempts = self::ATTEMPTS ): mixed {
		if ( isset( self::$held[ $post_id ] ) ) {
			return $callback();
		}

		$acquired = self::acquire( $post_id, $attempts );

		if ( $acquired ) {
			self::$held[ $post_id ] = true;
		}

		try {
			return $callback();
		} finally {
			if ( $acquired ) {
				unset( self::$held[ $post_id ] );
				self::release( $post_id );
			}
		}
	}

	/**
	 * Takes an event's lock.
	 *
	 * @since TBD
	 *
	 * @param int $post_id  The event post ID.
	 * @param int $attempts How many times to try.
	 *
	 * @return bool True when the lock was taken.
	 */
	private static function acquire( int $post_id, int $attempts ): bool {
		global $wpdb;

		$name = sprintf( self::OPTION_NAME, $post_id );

		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The insert is the lock, so it cannot go through the options cache.
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload )
					VALUES ( %s, %s, 'off' )",
					$name,
					(string) time()
				)
			);

			if ( $inserted ) {
				return true;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the lock row another request wrote.
			$locked_at = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name )
			);

			if ( $locked_at < time() - self::EXPIRES ) {
				// A request that failed while holding the lock left it behind.
				self::release( $post_id );
			} elseif ( $attempt < $attempts ) {
				usleep( self::WAIT );
			}
		}

		return false;
	}

	/**
	 * Releases an event's lock.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return void
	 */
	private static function release( int $post_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes the lock row; it never enters the options cache.
		$wpdb->delete( $wpdb->options, array( 'option_name' => sprintf( self::OPTION_NAME, $post_id ) ) );
	}
}
