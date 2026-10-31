<?php
/**
 * Test fixture for a check-in state that a companion plugin adds.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Rsvp\Flag\Check_In_State;

/**
 * Class Test_Checked_In_Online.
 *
 * A check-in state for people who joined online, as a companion plugin
 * would register it.
 *
 * @since TBD
 */
final class Test_Checked_In_Online extends Check_In_State {

	/**
	 * The flag slug.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const SLUG = 'checked-in-online';

	/**
	 * Gets the name of the state.
	 *
	 * @since TBD
	 *
	 * @return string The name.
	 */
	public function label(): string {
		return 'Checked in online';
	}

	/**
	 * Gets the name of the action that sets the state.
	 *
	 * @since TBD
	 *
	 * @return string The action name.
	 */
	public function action_label(): string {
		return 'Check in online';
	}

	/**
	 * Whether the state means the person attended.
	 *
	 * @since TBD
	 *
	 * @return bool True.
	 */
	public function attended(): bool {
		return true;
	}
}
