<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Flag\Check_In.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Flag\Base;
use GatherPress\Core\Rsvp\Flag\Check_In;
use GatherPress\Core\Rsvp\Flag\Setup;
use GatherPress\Tests\Base as Base_Unit_Test;

/**
 * Class Test_Check_In.
 *
 * The flag mechanics are covered in Test_Base; these tests cover what the
 * check-in flag adds on top of them.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag\Check_In
 */
class Test_Check_In extends Base_Unit_Test {

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
	 * Create an event and an approved RSVP against it.
	 *
	 * @return array{event_id:int, rsvp_id:int} The event and RSVP IDs.
	 */
	private function make_rsvp(): array {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$rsvp_id  = wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '1',
				'user_id'          => 1,
			)
		);

		return array(
			'event_id' => $event_id,
			'rsvp_id'  => (int) $rsvp_id,
		);
	}

	/**
	 * Collect the check-in actions that fire while a callback runs.
	 *
	 * @param callable $callback The code to run.
	 *
	 * @return array<int, array{0: string, 1: int}> Each action name with the RSVP it reported.
	 */
	private function capture_check_in_actions( callable $callback ): array {
		$fired        = array();
		$checked_in   = static function ( int $rsvp_id ) use ( &$fired ): void {
			$fired[] = array( 'checked_in', $rsvp_id );
		};
		$unchecked_in = static function ( int $rsvp_id ) use ( &$fired ): void {
			$fired[] = array( 'unchecked_in', $rsvp_id );
		};

		add_action( 'gatherpress_rsvp_checked_in', $checked_in );
		add_action( 'gatherpress_rsvp_unchecked_in', $unchecked_in );

		$callback();

		remove_action( 'gatherpress_rsvp_checked_in', $checked_in );
		remove_action( 'gatherpress_rsvp_unchecked_in', $unchecked_in );

		return $fired;
	}

	/**
	 * Check-in is the `checked-in` flag: that is the term stored and the one
	 * counted.
	 *
	 * @coversNothing
	 *
	 * @return void
	 */
	public function test_check_in_is_the_checked_in_flag(): void {
		$rsvp     = $this->make_rsvp();
		$check_in = new Check_In( $rsvp['rsvp_id'] );

		$this->assertSame( 'checked-in', Check_In::SLUG, 'The check-in flag slug should be checked-in.' );
		$this->assertInstanceOf( Base::class, $check_in, 'Check-in should be a flag.' );

		$check_in->add();

		$this->assertSame(
			array( 'checked-in' ),
			Setup::get_instance()->get_flags( $rsvp['rsvp_id'] ),
			'Checking in should store the checked-in flag.'
		);
		$this->assertSame( 1, Check_In::count( $rsvp['event_id'] ), 'The check-in should be counted.' );
	}

	/**
	 * Coverage for after_add and after_remove: each check-in action fires once
	 * per real change and not on a repeat.
	 *
	 * @covers ::after_add
	 * @covers ::after_remove
	 *
	 * @return void
	 */
	public function test_check_in_actions_fire_once_per_change(): void {
		$rsvp_id  = $this->make_rsvp()['rsvp_id'];
		$check_in = new Check_In( $rsvp_id );

		$fired = $this->capture_check_in_actions(
			static function () use ( $check_in ): void {
				$check_in->add();
				$check_in->add();
				$check_in->remove();
				$check_in->remove();
			}
		);

		$this->assertSame(
			array(
				array( 'checked_in', $rsvp_id ),
				array( 'unchecked_in', $rsvp_id ),
			),
			$fired,
			'Each check-in action should fire once, with the RSVP, only when the state changes.'
		);
	}

	/**
	 * Coverage for the check-in actions staying quiet for other flags and for
	 * a write that failed.
	 *
	 * @covers ::after_add
	 * @covers ::after_remove
	 *
	 * @return void
	 */
	public function test_check_in_actions_do_not_fire_for_other_flags_or_failed_writes(): void {
		$rsvp_id  = $this->make_rsvp()['rsvp_id'];
		$check_in = new Check_In( $rsvp_id );
		$other    = new Test_Base_Concrete( $rsvp_id );

		$fired = $this->capture_check_in_actions(
			static function () use ( $check_in, $other ): void {
				$other->add();
				$other->remove();

				unregister_taxonomy( Base::TAXONOMY );
				$check_in->add();
				Setup::get_instance()->register_taxonomy();
			}
		);

		$this->assertSame( array(), $fired, 'No check-in action should fire.' );
		$this->assertFalse( $check_in->has(), 'The failed check-in should not have been stored.' );
	}
}
