<?php
/**
 * A check-in state of an RSVP.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Core\Rsvp\Flag;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Check_In_State.
 *
 * A flag that records what happened when the event took place, for example
 * `Check_In` or `No_Show`. An RSVP has at most one check-in state: adding one
 * removes the others. An RSVP with none is not recorded. Register a state with
 * `Check_In_Registry` so the RSVP screen offers it.
 *
 * @since TBD
 */
abstract class Check_In_State extends Base {

	/**
	 * Gets the name of the state, for example "Checked in".
	 *
	 * @since TBD
	 *
	 * @return string The translated name.
	 */
	abstract public function label(): string;

	/**
	 * Gets the name of the action that sets the state, for example "Check in".
	 *
	 * @since TBD
	 *
	 * @return string The translated action name.
	 */
	abstract public function action_label(): string;

	/**
	 * Whether the state means the person attended.
	 *
	 * Reports count the attended states as present and the others as absent,
	 * and leave out RSVPs that are not recorded.
	 *
	 * @since TBD
	 *
	 * @return bool True when the person attended.
	 */
	abstract public function attended(): bool;

	/**
	 * Gets the icon of the state, shown with its name on the RSVP screen.
	 *
	 * SVG markup of a 24 by 24 icon, drawn like the `@wordpress/icons` set. It
	 * goes through `Check_In_Registry::kses_icon()`, which keeps `svg`, `path`,
	 * `g`, `circle` and `rect`.
	 *
	 * @since TBD
	 *
	 * @return string The SVG markup.
	 */
	abstract public function icon(): string;

	/**
	 * Sets the RSVP to this state, and removes its other check-in states.
	 *
	 * @since TBD
	 *
	 * @return bool True when the RSVP has this state and no other.
	 */
	public function add(): bool {
		if ( ! parent::add() ) {
			return false;
		}

		$cleared = true;

		foreach ( Check_In_Registry::get_instance()->get_all() as $state_class ) {
			if ( static::class !== $state_class ) {
				$cleared = ( new $state_class( $this->rsvp_id ) )->remove() && $cleared;
			}
		}

		return $cleared;
	}
}
