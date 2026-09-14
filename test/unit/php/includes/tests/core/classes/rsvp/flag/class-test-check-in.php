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
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Tests\Base as Base_Unit_Test;
use PMC\Unit_Test\Utility;

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
		Rsvp_Setup::get_instance()->register_taxonomy();
	}

	/**
	 * Create an event and an approved RSVP against it.
	 *
	 * @return int The RSVP comment ID.
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
	 * Coverage for get_slug: check-in is stored as the `checked-in` flag.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Check_In::get_instance();
		$rsvp_id  = $this->make_rsvp();

		$this->assertSame(
			'checked-in',
			Utility::invoke_hidden_method( $instance, 'get_slug' ),
			'The check-in flag slug should be checked-in.'
		);

		$instance->add( $rsvp_id );

		$this->assertSame(
			array( 'checked-in' ),
			Setup::get_instance()->get_flags( $rsvp_id ),
			'Checking in should store the checked-in flag.'
		);
		$this->assertInstanceOf( Base::class, $instance, 'Check-in should be a flag.' );
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
		$instance = Check_In::get_instance();
		$rsvp_id  = $this->make_rsvp();

		$fired = $this->capture_check_in_actions(
			static function () use ( $instance, $rsvp_id ): void {
				$instance->add( $rsvp_id );
				$instance->add( $rsvp_id );
				$instance->remove( $rsvp_id );
				$instance->remove( $rsvp_id );
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
		$instance = Check_In::get_instance();
		$host     = new Test_Base_Concrete( 'host' );
		$rsvp_id  = $this->make_rsvp();

		$fired = $this->capture_check_in_actions(
			static function () use ( $instance, $host, $rsvp_id ): void {
				$host->add( $rsvp_id );
				$host->remove( $rsvp_id );

				unregister_taxonomy( Base::TAXONOMY );
				$instance->add( $rsvp_id );
				Rsvp_Setup::get_instance()->register_taxonomy();
			}
		);

		$this->assertSame( array(), $fired, 'No check-in action should fire.' );
		$this->assertFalse( $instance->has( $rsvp_id ), 'The failed check-in should not have been stored.' );
	}
}
