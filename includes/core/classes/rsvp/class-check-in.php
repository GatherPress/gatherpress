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
 * Check-in state is tracked via a taxonomy term on the RSVP comment for fast
 * queries. An absent term means "not checked in", so existing RSVPs need
 * no backfill.
 *
 * @since 0.36.0
 */
final class Check_In {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Taxonomy for tracking RSVP flags.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const TAXONOMY = '_gatherpress_rsvp_flag';

	/**
	 * Term slug for checked-in status.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const TERM = 'checked-in';

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
	 * Assigning the term is idempotent: repeating a check-in maintains the
	 * existing state without duplicate term relationships.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP is checked in, false when it is not an RSVP.
	 */
	public function check_in( int $rsvp_id ): bool {
		if ( ! Rsvp::is_rsvp( $rsvp_id ) ) {
			return false;
		}

		if ( $this->is_checked_in( $rsvp_id ) ) {
			return true;
		}

		wp_set_object_terms( $rsvp_id, self::TERM, self::TAXONOMY, true );

		/**
		 * Fires after an RSVP has been checked in.
		 *
		 * @since 0.36.0
		 *
		 * @param int $rsvp_id The RSVP comment ID.
		 *
		 * @return void
		 */
		do_action( 'gatherpress_rsvp_checked_in', $rsvp_id );

		return true;
	}

	/**
	 * Undo a check-in.
	 *
	 * Wrong person, wrong row, or an accidental tap at the door: the check-in
	 * term is removed so the absence of the term remains the single answer
	 * to whether someone turned up.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP is no longer checked in, false when it is not an RSVP.
	 */
	public function clear( int $rsvp_id ): bool {
		if ( ! Rsvp::is_rsvp( $rsvp_id ) ) {
			return false;
		}

		wp_remove_object_terms( $rsvp_id, self::TERM, self::TAXONOMY );

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
	 * @return bool True when a check-in term is assigned.
	 */
	public function is_checked_in( int $rsvp_id ): bool {
		return true === is_object_in_term( $rsvp_id, self::TAXONOMY, self::TERM );
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
		$count = Query::get_instance()->get_rsvps(
			array(
				'post_id'   => $post_id,
				'status'    => 'approve',
				'count'     => true,
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'slug',
						'terms'    => self::TERM,
					),
				),
			)
		);

		return (int) $count;
	}

	/**
	 * Drop the check-in when its RSVP is deleted.
	 *
	 * WordPress core deletes commentmeta on wp_delete_comment(), but it never
	 * touches term relationships. This method cleans up the taxonomy term
	 * relationships for deleted RSVP comments so orphaned rows are not left
	 * behind in the term relationships table.
	 *
	 * @since 0.36.0
	 *
	 * @param int|string $comment_id The deleted comment ID.
	 *
	 * @return void
	 */
	public function delete_check_in( $comment_id ): void {
		wp_remove_object_terms( (int) $comment_id, self::TERM, self::TAXONOMY );
	}
}
