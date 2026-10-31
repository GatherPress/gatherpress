<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Flag\No_Show.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Flag\Check_In;
use GatherPress\Core\Rsvp\Flag\No_Show;
use GatherPress\Core\Rsvp\Flag\Setup;
use GatherPress\Tests\Base as Base_Unit_Test;

/**
 * Class Test_No_Show.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag\No_Show
 */
class Test_No_Show extends Base_Unit_Test {

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
	 * A no-show is the `no-show` flag, and it replaces a check-in.
	 *
	 * @covers ::add
	 *
	 * @return void
	 */
	public function test_add_replaces_a_check_in(): void {
		$rsvp_id = $this->make_rsvp();

		$this->assertSame( 'no-show', No_Show::SLUG, 'The no-show flag slug should be no-show.' );

		( new Check_In( $rsvp_id ) )->add();

		$this->assertTrue( ( new No_Show( $rsvp_id ) )->add(), 'Marking a no-show should succeed.' );
		$this->assertSame(
			array( 'no-show' ),
			Setup::get_instance()->get_flags( $rsvp_id ),
			'A no-show should replace the check-in.'
		);
	}

	/**
	 * A check-in replaces a no-show.
	 *
	 * @covers \GatherPress\Core\Rsvp\Flag\Check_In::add
	 *
	 * @return void
	 */
	public function test_check_in_replaces_a_no_show(): void {
		$rsvp_id = $this->make_rsvp();

		( new No_Show( $rsvp_id ) )->add();

		$this->assertTrue( ( new Check_In( $rsvp_id ) )->add(), 'Checking in should succeed.' );
		$this->assertSame(
			array( 'checked-in' ),
			Setup::get_instance()->get_flags( $rsvp_id ),
			'A check-in should replace the no-show.'
		);
	}

	/**
	 * A flag that cannot be written leaves the other flag alone.
	 *
	 * @covers ::add
	 *
	 * @return void
	 */
	public function test_add_fails_without_an_rsvp(): void {
		$this->assertFalse( ( new No_Show( 0 ) )->add(), 'A no-show needs an RSVP.' );
	}
}
