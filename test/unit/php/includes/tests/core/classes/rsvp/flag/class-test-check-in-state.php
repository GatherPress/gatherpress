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
use GatherPress\Core\Rsvp\Flag\Check_In_State;
use GatherPress\Core\Rsvp\Flag\No_Show;
use GatherPress\Core\Rsvp\Flag\Setup;
use GatherPress\Tests\Base as Base_Unit_Test;

/**
 * Class Test_Check_In_State.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag\Check_In_State
 */
class Test_Check_In_State extends Base_Unit_Test {

	/**
	 * Set up the test environment before each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Setup::get_instance()->register_taxonomy();
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
	 * The flag states use the slugs of their flags.
	 *
	 * @coversNothing
	 *
	 * @return void
	 */
	public function test_values_match_the_flag_slugs(): void {
		$this->assertSame( Check_In::SLUG, Check_In_State::CHECKED_IN->value );
		$this->assertSame( No_Show::SLUG, Check_In_State::NO_SHOW->value );
	}

	/**
	 * Each state sets an RSVP to itself, and reads back from it.
	 *
	 * @covers ::apply
	 * @covers ::for_rsvp
	 *
	 * @return void
	 */
	public function test_apply_and_for_rsvp(): void {
		$rsvp_id = $this->make_rsvp();

		$this->assertSame(
			Check_In_State::NOT_RECORDED,
			Check_In_State::for_rsvp( $rsvp_id ),
			'A new RSVP should not be recorded.'
		);

		$states = array( Check_In_State::CHECKED_IN, Check_In_State::NO_SHOW, Check_In_State::NOT_RECORDED );

		foreach ( $states as $state ) {
			$this->assertTrue( $state->apply( $rsvp_id ), sprintf( 'Setting %s should succeed.', $state->value ) );
			$this->assertSame(
				$state,
				Check_In_State::for_rsvp( $rsvp_id ),
				sprintf( 'The RSVP should be %s.', $state->value )
			);
		}

		$this->assertSame(
			array(),
			Setup::get_instance()->get_flags( $rsvp_id ),
			'Not recorded should remove both flags.'
		);
	}

	/**
	 * Each state has a label.
	 *
	 * @covers ::label
	 *
	 * @return void
	 */
	public function test_label(): void {
		$this->assertSame( 'Checked in', Check_In_State::CHECKED_IN->label() );
		$this->assertSame( 'No-show', Check_In_State::NO_SHOW->label() );
		$this->assertSame( 'Not recorded', Check_In_State::NOT_RECORDED->label() );
	}
}
