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

/**
 * Records which RSVPs actually turned up.
 *
 * Check-in is the `checked-in` flag, stored through `Flag`. An absent flag
 * means "not checked in", so existing RSVPs need no backfill.
 *
 * @since 0.36.0
 */
final class Check_In {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Flag slug for checked-in status.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const FLAG = 'checked-in';

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
	 * The check-in actions follow the flag actions, so they fire however the
	 * flag was written, whether through this class or through `Flag` directly.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_rsvp_flag_added', array( $this, 'announce_check_in' ), 10, 2 );
		add_action( 'gatherpress_rsvp_flag_removed', array( $this, 'announce_uncheck_in' ), 10, 2 );
	}

	/**
	 * Record that an RSVP turned up.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP is checked in, false when it is not an RSVP
	 *              or the flag could not be stored.
	 */
	public function check_in( int $rsvp_id ): bool {
		return Flag::get_instance()->add( $rsvp_id, self::FLAG );
	}

	/**
	 * Mark an RSVP as not checked in.
	 *
	 * Wrong person, wrong row, or an accidental tap at the door: removing the
	 * flag leaves its absence as the single answer to whether someone turned
	 * up. An RSVP that is already not checked in counts as done.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP is not checked in, false when it is not an RSVP
	 *              or the flag could not be removed.
	 */
	public function uncheck_in( int $rsvp_id ): bool {
		return Flag::get_instance()->remove( $rsvp_id, self::FLAG );
	}

	/**
	 * Whether an RSVP has been checked in.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the checked-in flag is on the RSVP.
	 */
	public function is_checked_in( int $rsvp_id ): bool {
		return Flag::get_instance()->has( $rsvp_id, self::FLAG );
	}

	/**
	 * How many RSVPs turned up for an event.
	 *
	 * Counts approved RSVPs only; see `Flag::count()`.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return int Number of checked-in RSVPs.
	 */
	public function count_checked_in( int $post_id ): int {
		return Flag::get_instance()->count( $post_id, self::FLAG );
	}

	/**
	 * Announce a check-in when the checked-in flag is added.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $rsvp_id The RSVP comment ID.
	 * @param string $flag    The flag slug that was added.
	 *
	 * @return void
	 */
	public function announce_check_in( int $rsvp_id, string $flag ): void {
		if ( self::FLAG !== $flag ) {
			return;
		}

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
	}

	/**
	 * Announce an uncheck-in when the checked-in flag is removed.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $rsvp_id The RSVP comment ID.
	 * @param string $flag    The flag slug that was removed.
	 *
	 * @return void
	 */
	public function announce_uncheck_in( int $rsvp_id, string $flag ): void {
		if ( self::FLAG !== $flag ) {
			return;
		}

		/**
		 * Fires after an RSVP's check-in has been removed.
		 *
		 * @since 0.36.0
		 *
		 * @param int $rsvp_id The RSVP comment ID.
		 *
		 * @return void
		 */
		do_action( 'gatherpress_rsvp_unchecked_in', $rsvp_id );
	}
}
