<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Flag\Check_In_Registry.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Flag\Check_In;
use GatherPress\Core\Rsvp\Flag\Check_In_Registry;
use GatherPress\Core\Rsvp\Flag\Check_In_State;
use GatherPress\Core\Rsvp\Flag\No_Show;
use GatherPress\Core\Rsvp\Flag\Setup;
use GatherPress\Tests\Base as Base_Unit_Test;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Check_In_Registry.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag\Check_In_Registry
 */
class Test_Check_In_Registry extends Base_Unit_Test {

	/**
	 * The registered states before the test.
	 *
	 * @var array<string, string>
	 */
	private array $registered = array();

	/**
	 * Register the taxonomy and remember the registered states.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Setup::get_instance()->register_taxonomy();

		$this->registered = Check_In_Registry::get_instance()->get_all();
	}

	/**
	 * Restore the registered states.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		Utility::set_and_get_hidden_property( Check_In_Registry::get_instance(), 'states', $this->registered );
		parent::tear_down();
	}

	/**
	 * Create an approved RSVP.
	 *
	 * @return int The RSVP ID.
	 */
	private function make_rsvp(): int {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		return (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '1',
				'user_id'          => 1,
			)
		);
	}

	/**
	 * The registry hooks the registration action, and core registers its states.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 * @covers ::register_core_states
	 * @covers ::get_all
	 *
	 * @return void
	 */
	public function test_core_states_are_registered(): void {
		$this->assert_hooks(
			array(
				array(
					'type'     => 'action',
					'name'     => 'gatherpress_loaded',
					'priority' => PHP_INT_MIN,
					'callback' => array( Check_In_Registry::get_instance(), 'register_check_in_states' ),
				),
			),
			Check_In_Registry::get_instance()
		);

		$this->assertSame(
			array(
				'checked-in' => Check_In::class,
				'no-show'    => No_Show::class,
			),
			$this->registered
		);
	}

	/**
	 * Only a check-in state with a valid, unused slug registers.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register(): void {
		$registry = Check_In_Registry::get_instance();

		$this->assertFalse( $registry->register( self::class ), 'A class that is not a check-in state is refused.' );
		$this->assertFalse( $registry->register( Check_In::class ), 'A registered slug is refused.' );
		$this->assertTrue( $registry->register( Test_Checked_In_Online::class ) );
		$this->assertSame( Test_Checked_In_Online::class, $registry->get_all()['checked-in-online'] );
		$this->assertFalse( $registry->register( Test_Checked_In_Online::class ), 'A second registration is refused.' );
	}

	/**
	 * A state cannot take the slug that stands for not recorded.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_refuses_the_not_recorded_slug(): void {
		$state = new class( 0 ) extends Check_In_State {
			/**
			 * The slug that stands for not recorded.
			 *
			 * @var string
			 */
			public const SLUG = 'not-recorded';

			/**
			 * Gets the name of the state.
			 *
			 * @return string The name.
			 */
			public function label(): string {
				return 'Not recorded';
			}

			/**
			 * Gets the name of the action that sets the state.
			 *
			 * @return string The action name.
			 */
			public function action_label(): string {
				return 'Mark as not recorded';
			}

			/**
			 * Whether the state means the person attended.
			 *
			 * @return bool False.
			 */
			public function attended(): bool {
				return false;
			}
		};

		$this->assertFalse( Check_In_Registry::get_instance()->register( $state::class ) );
	}

	/**
	 * The registry reads and clears the state of an RSVP.
	 *
	 * @covers ::get_state
	 * @covers ::clear
	 *
	 * @return void
	 */
	public function test_get_state_and_clear(): void {
		$registry = Check_In_Registry::get_instance();
		$rsvp_id  = $this->make_rsvp();

		$this->assertNull( $registry->get_state( $rsvp_id ), 'A new RSVP is not recorded.' );

		( new No_Show( $rsvp_id ) )->add();

		$this->assertInstanceOf( No_Show::class, $registry->get_state( $rsvp_id ) );
		$this->assertTrue( $registry->clear( $rsvp_id ) );
		$this->assertNull( $registry->get_state( $rsvp_id ), 'A cleared RSVP is not recorded.' );
	}

	/**
	 * Plugins register their states on the registration action.
	 *
	 * @covers ::register_check_in_states
	 *
	 * @return void
	 */
	public function test_register_check_in_states_fires_the_action(): void {
		$callback = static function ( Check_In_Registry $registry ): void {
			$registry->register( Test_Checked_In_Online::class );
		};

		add_action( 'gatherpress_register_rsvp_check_in_states', $callback );
		Check_In_Registry::get_instance()->register_check_in_states();
		remove_action( 'gatherpress_register_rsvp_check_in_states', $callback );

		$this->assertArrayHasKey( 'checked-in-online', Check_In_Registry::get_instance()->get_all() );
	}
}
