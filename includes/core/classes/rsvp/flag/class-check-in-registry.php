<?php
/**
 * The check-in states an RSVP can have.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Core\Rsvp\Flag;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Check_In_Registry.
 *
 * Holds the check-in states, keyed by flag slug. Core registers `Check_In` and
 * `No_Show`; companion plugins add theirs on the
 * `gatherpress_register_rsvp_check_in_states` action. An RSVP with none of the
 * registered states is not recorded.
 *
 * @since TBD
 */
final class Check_In_Registry {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The filter value for an RSVP without a check-in state.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const NOT_RECORDED = 'not-recorded';

	/**
	 * The registered state classes, keyed by flag slug.
	 *
	 * @since TBD
	 *
	 * @var array<string, class-string<Check_In_State>>
	 */
	private array $states = array();

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
		$this->register_core_states();
	}

	/**
	 * Set up hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// PHP_INT_MIN so states register before any other gatherpress_loaded listener reads them.
		add_action( 'gatherpress_loaded', array( $this, 'register_check_in_states' ), PHP_INT_MIN );
	}

	/**
	 * Registers a check-in state.
	 *
	 * @since TBD
	 *
	 * @param string $state_class A class that extends `Check_In_State`.
	 *
	 * @return bool True when the state was registered, false when it is not a
	 *              check-in state, its slug is not valid or already taken.
	 */
	public function register( string $state_class ): bool {
		if ( ! is_subclass_of( $state_class, Check_In_State::class ) ) {
			return false;
		}

		$slug     = $state_class::SLUG;
		$is_valid = '' !== $slug && sanitize_key( $slug ) === $slug && self::NOT_RECORDED !== $slug;

		if ( ! $is_valid || isset( $this->states[ $slug ] ) ) {
			return false;
		}

		$this->states[ $slug ] = $state_class;

		return true;
	}

	/**
	 * Gets the registered states.
	 *
	 * @since TBD
	 *
	 * @return array<string, class-string<Check_In_State>> State classes, keyed by flag slug.
	 */
	public function get_all(): array {
		return $this->states;
	}

	/**
	 * Gets the check-in state of an RSVP.
	 *
	 * @since TBD
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return Check_In_State|null The state, or null when it is not recorded.
	 */
	public function get_state( int $rsvp_id ): ?Check_In_State {
		foreach ( $this->states as $state_class ) {
			$state = new $state_class( $rsvp_id );

			if ( $state->has() ) {
				return $state;
			}
		}

		return null;
	}

	/**
	 * Removes every check-in state from an RSVP, so it is not recorded.
	 *
	 * @since TBD
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP has no check-in state.
	 */
	public function clear( int $rsvp_id ): bool {
		$cleared = true;

		foreach ( $this->states as $state_class ) {
			$cleared = ( new $state_class( $rsvp_id ) )->remove() && $cleared;
		}

		return $cleared;
	}

	/**
	 * Registers the check-in states of core.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_core_states(): void {
		$this->register( Check_In::class );
		$this->register( No_Show::class );
	}

	/**
	 * Fires the action that lets plugins register check-in states.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_check_in_states(): void {
		/**
		 * Fires so plugins can register check-in states.
		 *
		 * A check-in state is a class that extends
		 * `GatherPress\Core\Rsvp\Flag\Check_In_State`, for example a check-in
		 * for people who joined online. The RSVP screen shows each registered
		 * state in its column, actions and filter. Core registers `Check_In`
		 * and `No_Show` before this fires.
		 *
		 * ```php
		 * add_action( 'gatherpress_register_rsvp_check_in_states', function ( $registry ) {
		 *     $registry->register( My_Plugin\Checked_In_Online::class );
		 * } );
		 * ```
		 *
		 * @since TBD
		 *
		 * @param Check_In_Registry $registry The check-in state registry.
		 */
		do_action( 'gatherpress_register_rsvp_check_in_states', $this );
	}
}
