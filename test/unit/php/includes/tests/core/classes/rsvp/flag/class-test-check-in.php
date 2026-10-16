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
 * Check-in declares only its slug; the flag mechanics are covered in Test_Base.
 * These tests pin what the slug means in storage, counts and hooks.
 *
 * @coversNothing
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
	 * Check-in is the `checked-in` flag: that is the term stored and the one
	 * counted.
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
	 * Checking in and removing it reach listeners through the flag actions,
	 * with the check-in slug, which is how code reacts to a check-in.
	 *
	 * @return void
	 */
	public function test_check_in_is_announced_through_the_flag_actions(): void {
		$rsvp_id  = $this->make_rsvp()['rsvp_id'];
		$check_in = new Check_In( $rsvp_id );
		$fired    = array();
		$added    = static function ( int $id, string $flag ) use ( &$fired ): void {
			$fired[] = array( 'added', $id, $flag );
		};
		$removed  = static function ( int $id, string $flag ) use ( &$fired ): void {
			$fired[] = array( 'removed', $id, $flag );
		};

		add_action( 'gatherpress_rsvp_flag_added', $added, 10, 2 );
		add_action( 'gatherpress_rsvp_flag_removed', $removed, 10, 2 );

		$check_in->add();
		$check_in->remove();

		remove_action( 'gatherpress_rsvp_flag_added', $added, 10 );
		remove_action( 'gatherpress_rsvp_flag_removed', $removed, 10 );

		$this->assertSame(
			array(
				array( 'added', $rsvp_id, Check_In::SLUG ),
				array( 'removed', $rsvp_id, Check_In::SLUG ),
			),
			$fired,
			'Checking in and removing it should each fire the flag action once, with the check-in slug.'
		);
	}
}
