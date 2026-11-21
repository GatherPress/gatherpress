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
 * the lock functions do nothing, and the code runs without a lock.
 *
 * @since TBD
 */
final class Lock {

	/**
	 * Seconds to wait for the lock.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const TIMEOUT = 10;

	/**
	 * Event post IDs whose lock this request holds.
	 *
	 * Shared by every instance, since one request can create several for the
	 * same event and a nested run must not wait on its own lock.
	 *
	 * @since TBD
	 *
	 * @var array<int, true>
	 */
	private static array $held = array();

	/**
	 * The lock's name.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public readonly string $name;

	/**
	 * Class constructor.
	 *
	 * Named locks are shared by every database on the server, so the table
	 * prefix keeps sites apart. MySQL allows 64 characters, so a long prefix
	 * is cut from the front, keeping the post ID.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 */
	public function __construct( private readonly int $post_id ) {
		global $wpdb;

		$this->name = substr( sprintf( '%sgatherpress_rsvp_%d', $wpdb->prefix, $post_id ), -64 );
	}

	/**
	 * Runs a callback while holding the event's lock.
	 *
	 * When the lock is not granted in time, the fallback runs instead, so the
	 * callback never runs unlocked.
	 *
	 * @since TBD
	 *
	 * @param callable      $callback The code to run.
	 * @param callable|null $fallback Optional. What to run when the lock is not granted.
	 *
	 * @return mixed The callback's return value, or the fallback's.
	 */
	public function run( callable $callback, ?callable $fallback = null ): mixed {
		global $wpdb;

		// Re-entrant, so code holding the lock can call code that takes it.
		if ( isset( self::$held[ $this->post_id ] ) ) {
			return $callback();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock, not data.
		$granted = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $this->name, self::TIMEOUT ) );

		// MySQL returns 1 when granted, 0 on timeout and NULL on error. SQLite's
		// stand-in returns a non-numeric string, which counts as granted.
		if ( null === $granted || '0' === (string) $granted ) {
			return $fallback ? $fallback() : null;
		}

		self::$held[ $this->post_id ] = true;

		try {
			return $callback();
		} finally {
			unset( self::$held[ $this->post_id ] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock, not data.
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $this->name ) );
		}
	}
}
