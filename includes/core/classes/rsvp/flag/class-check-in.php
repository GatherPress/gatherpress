<?php
/**
 * Records which RSVPs actually turned up.
 *
 * This file defines the `Check_In` flag. An RSVP says who intended to come;
 * check-in says who arrived. The two are separate on purpose: the gap between
 * them is the no-show rate, which is what tells an organizer whether a venue
 * is the right size.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since 0.36.0
 */

namespace GatherPress\Core\Rsvp\Flag;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Check_In.
 *
 * The `checked-in` flag. An absent flag means "not checked in", so existing
 * RSVPs need no backfill.
 *
 * @since 0.36.0
 */
final class Check_In extends Base {

	/**
	 * The check-in flag slug.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const SLUG = 'checked-in';

	/**
	 * Announce a check-in.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function after_add(): void {
		/**
		 * Fires after an RSVP has been checked in.
		 *
		 * @since 0.36.0
		 *
		 * @param int $rsvp_id The RSVP comment ID.
		 *
		 * @return void
		 */
		do_action( 'gatherpress_rsvp_checked_in', $this->rsvp_id );
	}

	/**
	 * Announce that a check-in was removed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function after_remove(): void {
		/**
		 * Fires after an RSVP's check-in has been removed.
		 *
		 * @since 0.36.0
		 *
		 * @param int $rsvp_id The RSVP comment ID.
		 *
		 * @return void
		 */
		do_action( 'gatherpress_rsvp_unchecked_in', $this->rsvp_id );
	}
}
