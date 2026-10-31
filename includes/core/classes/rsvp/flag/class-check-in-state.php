<?php
/**
 * The check-in state of an RSVP.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Core\Rsvp\Flag;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Enum Check_In_State.
 *
 * Whether an RSVP was checked in, was a no-show, or has no record. The first
 * two are the `Check_In` and `No_Show` flags; an RSVP with neither is not
 * recorded. Reports should leave unrecorded RSVPs out rather than count them
 * as either.
 *
 * @since TBD
 */
enum Check_In_State: string {

	case CHECKED_IN   = 'checked-in';
	case NO_SHOW      = 'no-show';
	case NOT_RECORDED = 'not-recorded';

	/**
	 * Gets the check-in state of an RSVP.
	 *
	 * @since TBD
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return self The state.
	 */
	public static function for_rsvp( int $rsvp_id ): self {
		if ( ( new Check_In( $rsvp_id ) )->has() ) {
			return self::CHECKED_IN;
		}

		return ( new No_Show( $rsvp_id ) )->has() ? self::NO_SHOW : self::NOT_RECORDED;
	}

	/**
	 * Gets the label of the state.
	 *
	 * @since TBD
	 *
	 * @return string The translated label.
	 */
	public function label(): string {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Enum instance methods have $this; the sniff reads this enum's first non-static method as a plain function.
		return match ( $this ) {
			self::CHECKED_IN   => __( 'Checked in', 'gatherpress' ),
			self::NO_SHOW      => __( 'No-show', 'gatherpress' ),
			self::NOT_RECORDED => __( 'Not recorded', 'gatherpress' ),
		};
	}

	/**
	 * Sets an RSVP to this state.
	 *
	 * @since TBD
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP is in this state.
	 */
	public function apply( int $rsvp_id ): bool {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Enum instance methods have $this.
		return match ( $this ) {
			self::CHECKED_IN   => ( new Check_In( $rsvp_id ) )->add(),
			self::NO_SHOW      => ( new No_Show( $rsvp_id ) )->add(),
			self::NOT_RECORDED => ( new Check_In( $rsvp_id ) )->remove() && ( new No_Show( $rsvp_id ) )->remove(),
		};
	}
}
