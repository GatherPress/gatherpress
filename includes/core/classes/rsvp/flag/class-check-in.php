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
 * The `checked-in` flag. An RSVP with neither this flag nor `no-show` is not
 * recorded, so existing RSVPs need no backfill. Adding this flag removes the
 * `no-show` flag. To react to a check-in, hook `gatherpress_rsvp_flag_added`
 * and compare the slug with `Check_In::SLUG`.
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
	 * Checks the RSVP in, and removes its no-show.
	 *
	 * @since TBD
	 *
	 * @return bool True when the RSVP is checked in and not a no-show.
	 */
	public function add(): bool {
		return parent::add() && ( new No_Show( $this->rsvp_id ) )->remove();
	}
}
