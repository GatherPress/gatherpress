<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Flag\Check_In_State.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Flag\Check_In;
use GatherPress\Core\Rsvp\Flag\Check_In_Registry;
use GatherPress\Core\Rsvp\Flag\No_Show;
use GatherPress\Core\Rsvp\Flag\Setup;
use GatherPress\Tests\Base as Base_Unit_Test;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Check_In_State.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag\Check_In_State
 */
class Test_Check_In_State extends Base_Unit_Test {

	/**
	 * The registered states before the test.
	 *
	 * @var array<string, string>
	 */
	private array $registered = array();

	/**
	 * Register the taxonomy and a third state, as a companion plugin would.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Setup::get_instance()->register_taxonomy();

		$registry         = Check_In_Registry::get_instance();
		$this->registered = $registry->get_all();

		$registry->register( Test_Checked_In_Online::class );
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
	 * Adding a state removes every other registered state, a plugin's included.
	 *
	 * @covers ::add
	 *
	 * @return void
	 */
	public function test_add_removes_the_other_states(): void {
		$rsvp_id = $this->make_rsvp();
		$states  = array( Check_In::class, Test_Checked_In_Online::class, No_Show::class, Check_In::class );

		foreach ( $states as $state_class ) {
			$this->assertTrue(
				( new $state_class( $rsvp_id ) )->add(),
				sprintf( 'Adding %s should succeed.', $state_class )
			);
			$this->assertSame(
				array( $state_class::SLUG ),
				Setup::get_instance()->get_flags( $rsvp_id ),
				sprintf( '%s should be the only check-in state.', $state_class )
			);
		}
	}

	/**
	 * A state that cannot be written leaves the others alone.
	 *
	 * @covers ::add
	 *
	 * @return void
	 */
	public function test_add_fails_without_an_rsvp(): void {
		$this->assertFalse( ( new No_Show( 0 ) )->add(), 'A no-show needs an RSVP.' );
	}

	/**
	 * Core's states name themselves and say whether the person attended.
	 *
	 * @covers \GatherPress\Core\Rsvp\Flag\Check_In::label
	 * @covers \GatherPress\Core\Rsvp\Flag\Check_In::action_label
	 * @covers \GatherPress\Core\Rsvp\Flag\Check_In::attended
	 * @covers \GatherPress\Core\Rsvp\Flag\No_Show::label
	 * @covers \GatherPress\Core\Rsvp\Flag\No_Show::action_label
	 * @covers \GatherPress\Core\Rsvp\Flag\No_Show::attended
	 * @covers \GatherPress\Core\Rsvp\Flag\Check_In::icon
	 * @covers \GatherPress\Core\Rsvp\Flag\No_Show::icon
	 *
	 * @return void
	 */
	public function test_core_states(): void {
		$check_in = new Check_In( 0 );
		$no_show  = new No_Show( 0 );

		$this->assertSame( 'no-show', No_Show::SLUG );
		$this->assertSame( 'Checked in', $check_in->label() );
		$this->assertSame( 'Check in', $check_in->action_label() );
		$this->assertTrue( $check_in->attended() );
		$this->assertSame( 'No-show', $no_show->label() );
		$this->assertSame( 'Mark as no-show', $no_show->action_label() );
		$this->assertFalse( $no_show->attended() );
		$this->assertStringContainsString(
			'<path d="M16.5 7.5 10 13.9l-2.5-2.4-1 1 3.5 3.6 7.5-7.6z">',
			$check_in->icon()
		);
		$this->assertStringContainsString( '<path d="M12 13.06l3.712 3.713', $no_show->icon() );
	}
}
