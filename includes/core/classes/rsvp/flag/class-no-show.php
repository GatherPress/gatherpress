<?php
/**
 * Records which RSVPs did not turn up.
 *
 * This file defines the `No_Show` check-in state. An RSVP without a check-in
 * state is not recorded rather than a no-show, which keeps the no-show rate
 * honest when an organizer did not record every attendee.
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
 * The `no-show` flag: the person had an RSVP but did not come.
 *
 * @since TBD
 */
final class No_Show extends Check_In_State {

	/**
	 * The no-show flag slug.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const SLUG = 'no-show';

	/**
	 * Gets the name of the state.
	 *
	 * @since TBD
	 *
	 * @return string The translated name.
	 */
	public function label(): string {
		return __( 'No-show', 'gatherpress' );
	}

	/**
	 * Gets the name of the action that sets the state.
	 *
	 * @since TBD
	 *
	 * @return string The translated action name.
	 */
	public function action_label(): string {
		return __( 'Mark as no-show', 'gatherpress' );
	}

	/**
	 * Whether the state means the person attended.
	 *
	 * @since TBD
	 *
	 * @return bool False: a no-show did not attend.
	 */
	public function attended(): bool {
		return false;
	}
}
