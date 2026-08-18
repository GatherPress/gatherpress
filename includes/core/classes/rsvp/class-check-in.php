<?php
/**
 * Records which RSVPs actually turned up.
 *
 * This file defines the `Check_In` class. An RSVP says who intended to come;
 * check-in says who arrived. The two are separate on purpose: the gap between
 * them is the no-show rate, which is what tells an organizer whether a venue
 * is the right size.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.36.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Comment;

/**
 * Records which RSVPs actually turned up.
 *
 * State lives in a single comment meta key holding the GMT timestamp of the
 * check-in, so an absent value means "not checked in" and existing RSVPs need
 * no backfill. The timestamp rather than a boolean because arrival times are
 * free to keep and impossible to reconstruct afterwards.
 *
 * @since 0.36.0
 */
final class Check_In {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Comment meta key holding the GMT check-in timestamp.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	const META_KEY = 'gatherpress_checked_in';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'deleted_comment', array( $this, 'delete_check_in' ) );
	}

	/**
	 * Record that an RSVP turned up.
	 *
	 * Repeating a check-in keeps the first arrival time rather than moving it:
	 * a second scan at the door is a duplicate, not a later arrival.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP is checked in, false when it is not an RSVP.
	 */
	public function check_in( int $rsvp_id ): bool {
		if ( ! $this->is_rsvp( $rsvp_id ) ) {
			return false;
		}

		if ( $this->is_checked_in( $rsvp_id ) ) {
			return true;
		}

		$timestamp = current_time( 'mysql', true );

		update_comment_meta( $rsvp_id, self::META_KEY, $timestamp );

		/**
		 * Fires after an RSVP has been checked in.
		 *
		 * @since 0.36.0
		 *
		 * @param int    $rsvp_id   The RSVP comment ID.
		 * @param string $timestamp The GMT timestamp recorded for the check-in.
		 *
		 * @return void
		 */
		do_action( 'gatherpress_rsvp_checked_in', $rsvp_id, $timestamp );

		return true;
	}

	/**
	 * Undo a check-in.
	 *
	 * Wrong person, wrong row, or an accidental tap at the door: the arrival time is
	 * removed rather than kept alongside a flag, so the meta stays the single
	 * answer to whether someone turned up.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP is no longer checked in, false when it is not an RSVP.
	 */
	public function clear( int $rsvp_id ): bool {
		if ( ! $this->is_rsvp( $rsvp_id ) ) {
			return false;
		}

		delete_comment_meta( $rsvp_id, self::META_KEY );

		/**
		 * Fires after an RSVP's check-in has been cleared.
		 *
		 * @since 0.36.0
		 *
		 * @param int $rsvp_id The RSVP comment ID.
		 *
		 * @return void
		 */
		do_action( 'gatherpress_rsvp_check_in_cleared', $rsvp_id );

		return true;
	}

	/**
	 * Whether an RSVP has been checked in.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when a check-in time is recorded.
	 */
	public function is_checked_in( int $rsvp_id ): bool {
		return '' !== (string) get_comment_meta( $rsvp_id, self::META_KEY, true );
	}

	/**
	 * The GMT timestamp an RSVP was checked in.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return string The GMT timestamp, or an empty string when not checked in.
	 */
	public function get_check_in_time( int $rsvp_id ): string {
		return (string) get_comment_meta( $rsvp_id, self::META_KEY, true );
	}

	/**
	 * How many RSVPs turned up for an event.
	 *
	 * Counts approved RSVPs only, matching what the attendee list shows: a
	 * held or spammed RSVP is not part of the event's audience even if someone
	 * checked it in before it was moderated.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return int Number of checked-in RSVPs.
	 */
	public function count_checked_in( int $post_id ): int {
		$count = get_comments(
			array(
				'post_id'    => $post_id,
				'type'       => Rsvp::COMMENT_TYPE,
				'status'     => 'approve',
				'count'      => true,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return (int) $count;
	}

	/**
	 * Drop the check-in when its RSVP is deleted.
	 *
	 * WordPress clears comment meta for deleted comments, so this exists for
	 * the case where a filter or a direct query removed the comment row
	 * without taking its meta: an orphaned arrival time would otherwise be
	 * counted against whatever comment ID got reused.
	 *
	 * @since 0.36.0
	 *
	 * @param int|string $comment_id The deleted comment ID.
	 *
	 * @return void
	 */
	public function delete_check_in( $comment_id ): void {
		delete_comment_meta( (int) $comment_id, self::META_KEY );
	}

	/**
	 * Whether a comment ID belongs to an RSVP.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The comment ID to test.
	 *
	 * @return bool True when the comment exists and is an RSVP.
	 */
	private function is_rsvp( int $rsvp_id ): bool {
		$comment = get_comment( $rsvp_id );

		return $comment instanceof WP_Comment && Rsvp::COMMENT_TYPE === $comment->comment_type;
	}
}
