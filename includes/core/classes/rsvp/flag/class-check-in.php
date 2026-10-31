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
 * The `checked-in` check-in state. An RSVP without a check-in state is not
 * recorded, so existing RSVPs need no backfill. To react to a check-in, hook
 * `gatherpress_rsvp_flag_added` and compare the slug with `Check_In::SLUG`.
 *
 * @since 0.36.0
 */
final class Check_In extends Check_In_State {

	/**
	 * The check-in flag slug.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const SLUG = 'checked-in';

	/**
	 * Gets the name of the state.
	 *
	 * @since TBD
	 *
	 * @return string The translated name.
	 */
	public function label(): string {
		return __( 'Checked in', 'gatherpress' );
	}

	/**
	 * Gets the name of the action that sets the state.
	 *
	 * @since TBD
	 *
	 * @return string The translated action name.
	 */
	public function action_label(): string {
		return __( 'Check in', 'gatherpress' );
	}

	/**
	 * Whether the state means the person attended.
	 *
	 * @since TBD
	 *
	 * @return bool True: a checked-in person attended.
	 */
	public function attended(): bool {
		return true;
	}
}
