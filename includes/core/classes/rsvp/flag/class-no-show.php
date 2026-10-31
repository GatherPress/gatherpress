<?php
/**
 * Records which RSVPs did not turn up.
 *
 * This file defines the `No_Show` flag. With `Check_In` it gives an RSVP one of
 * three check-in states: checked in, a no-show, or not recorded when it has
 * neither flag. Leaving unrecorded RSVPs out keeps the no-show rate honest when
 * an organizer did not record every attendee.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Core\Rsvp\Flag;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class No_Show.
 *
 * The `no-show` flag. An RSVP is never both checked in and a no-show: adding
 * this flag removes the `checked-in` flag.
 *
 * @since TBD
 */
final class No_Show extends Base {

	/**
	 * The no-show flag slug.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const SLUG = 'no-show';

	/**
	 * Marks the RSVP as a no-show, and removes its check-in.
	 *
	 * @since TBD
	 *
	 * @return bool True when the RSVP is a no-show and not checked in.
	 */
	public function add(): bool {
		return parent::add() && ( new Check_In( $this->rsvp_id ) )->remove();
	}
}
