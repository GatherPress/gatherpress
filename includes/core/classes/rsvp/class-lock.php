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
 * WordPress supports. Its value is the time it was taken and a key only the
 * request that took it knows, so a request only ever removes its own lock or
 * the exact expired lock it read. It is re-entrant within a request.
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
	const EXPIRES = 10;

	/**
	 * Microseconds to wait between attempts.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const WAIT = 100000;

	/**
	 * Attempts before giving up, enough to outlast an expired lock.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const ATTEMPTS = 120;

	/**
	 * Lock values this request holds, keyed by event post ID.
	 *
	 * @since TBD
	 *
	 * @var array<int, string>
	 */
	private static array $held = array();

	/**
	 * Runs a callback while holding an event's lock.
	 *
	 * When the lock cannot be taken, the callback does not run and the
	 * fallback is returned, so nothing changes without the lock.
	 *
	 * @since TBD
	 *
	 * @param int      $post_id  The event post ID.
	 * @param callable $callback The code to run.
	 * @param mixed    $fallback Optional. What to return when the lock cannot be taken.
	 * @param int      $attempts Optional. How many times to try for the lock.
	 *
	 * @return mixed The callback's return value, or the fallback.
	 */
	public static function run(
		int $post_id,
		callable $callback,
		mixed $fallback = null,
		int $attempts = self::ATTEMPTS
	): mixed {
		if ( isset( self::$held[ $post_id ] ) ) {
			return $callback();
		}

		$value = self::acquire( $post_id, $attempts );

		if ( null === $value ) {
			return $fallback;
		}

		self::$held[ $post_id ] = $value;

		try {
			return $callback();
		} finally {
			unset( self::$held[ $post_id ] );
			self::delete( $post_id, $value );
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
	 * @return string|null The lock value, or null when the lock was not taken.
	 */
	private static function acquire( int $post_id, int $attempts ): ?string {
		global $wpdb;

		$name = sprintf( self::OPTION_NAME, $post_id );
		$key  = wp_generate_uuid4();

		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			// Stamped on each attempt, so a lock taken after waiting is not already expired.
			$value = sprintf( '%d:%s', time(), $key );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The insert is the lock, so it cannot go through the options cache.
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO %i ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )",
					$wpdb->options,
					$name,
					$value
				)
			);

			if ( $inserted ) {
				return $value;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the lock row another request wrote.
			$current = (string) $wpdb->get_var(
				$wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $name )
			);

			// A request that failed while holding the lock left it behind. Only
			// the expired value just read is removed, never a newer lock.
			if ( (int) $current < time() - self::EXPIRES ) {
				self::delete( $post_id, $current );
			} elseif ( $attempt < $attempts ) {
				usleep( self::WAIT );
			}
		}

		return null;
	}

	/**
	 * Removes an event's lock if it still holds the given value.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $value   The lock value to remove.
	 *
	 * @return void
	 */
	private static function delete( int $post_id, string $value ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removes the lock row; it never enters the options cache.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name = %s AND option_value = %s',
				$wpdb->options,
				sprintf( self::OPTION_NAME, $post_id ),
				$value
			)
		);
	}
}
