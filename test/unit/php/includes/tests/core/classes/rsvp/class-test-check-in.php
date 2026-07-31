<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Check_In.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Check_In;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Tests\Base;

/**
 * Class Test_Check_In.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Check_In
 */
class Test_Check_In extends Base {

	/**
	 * Create an event and an approved RSVP against it.
	 *
	 * @param string $status Comment approval status: 1, 0, or spam.
	 *
	 * @return array{event_id:int, rsvp_id:int} The event and RSVP IDs.
	 */
	private function make_rsvp( string $status = '1' ): array {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$rsvp_id  = wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => $status,
				'user_id'          => 1,
			)
		);

		return array(
			'event_id' => $event_id,
			'rsvp_id'  => (int) $rsvp_id,
		);
	}

	/**
	 * Coverage for __construct.
	 *
	 * The instance is built during plugin bootstrap, so the constructor only
	 * runs inside a test once the stored instance is cleared.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_builds_the_instance(): void {
		$reflection = new \ReflectionClass( Check_In::class );
		$property   = $reflection->getProperty( 'instance' );

		$property->setAccessible( true );
		$property->setValue( null, null );

		$this->assertInstanceOf(
			Check_In::class,
			Check_In::get_instance(),
			'Failed to assert that the constructor returns a Check_In instance.'
		);
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Check_In::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'deleted_comment',
				'priority' => 10,
				'callback' => array( $instance, 'delete_check_in' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for check_in and is_checked_in on an RSVP.
	 *
	 * @covers ::check_in
	 * @covers ::is_checked_in
	 * @covers ::get_check_in_time
	 * @covers ::is_rsvp
	 *
	 * @return void
	 */
	public function test_check_in_records_an_arrival(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		$this->assertFalse(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'A fresh RSVP should not be checked in.'
		);
		$this->assertSame(
			'',
			$instance->get_check_in_time( $rsvp['rsvp_id'] ),
			'A fresh RSVP should have no arrival time.'
		);
		$this->assertTrue(
			$instance->check_in( $rsvp['rsvp_id'] ),
			'Checking in an RSVP should succeed.'
		);
		$this->assertTrue(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'The RSVP should read as checked in afterwards.'
		);
		$this->assertNotEmpty(
			$instance->get_check_in_time( $rsvp['rsvp_id'] ),
			'A GMT arrival time should be recorded.'
		);
	}

	/**
	 * Coverage for check_in on an already checked-in RSVP: the first arrival
	 * time stands rather than being overwritten by a second scan.
	 *
	 * @covers ::check_in
	 *
	 * @return void
	 */
	public function test_check_in_keeps_the_first_arrival_time(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->check_in( $rsvp['rsvp_id'] );

		$first = $instance->get_check_in_time( $rsvp['rsvp_id'] );

		$this->assertTrue(
			$instance->check_in( $rsvp['rsvp_id'] ),
			'A repeated check-in should report success.'
		);
		$this->assertSame(
			$first,
			$instance->get_check_in_time( $rsvp['rsvp_id'] ),
			'A repeated check-in should not move the arrival time.'
		);
	}

	/**
	 * Coverage for clear.
	 *
	 * @covers ::clear
	 *
	 * @return void
	 */
	public function test_clear_undoes_a_check_in(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->check_in( $rsvp['rsvp_id'] );

		$this->assertTrue(
			$instance->clear( $rsvp['rsvp_id'] ),
			'Clearing a check-in should succeed.'
		);
		$this->assertFalse(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'The RSVP should no longer read as checked in.'
		);
	}

	/**
	 * Coverage for the is_rsvp guard on both writers: a comment that is not an
	 * RSVP, and a comment ID that does not exist.
	 *
	 * @covers ::check_in
	 * @covers ::clear
	 * @covers ::is_rsvp
	 *
	 * @return void
	 */
	public function test_check_in_refuses_comments_that_are_not_rsvps(): void {
		$instance   = Check_In::get_instance();
		$post_id    = $this->mock->post()->get()->ID;
		$comment_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		$this->assertFalse(
			$instance->check_in( $comment_id ),
			'A plain comment should not be checkable.'
		);
		$this->assertFalse(
			$instance->clear( $comment_id ),
			'A plain comment should not be clearable.'
		);
		$this->assertFalse(
			$instance->check_in( 0 ),
			'A comment ID that does not exist should not be checkable.'
		);
		$this->assertFalse(
			$instance->is_checked_in( $comment_id ),
			'A plain comment should never read as checked in.'
		);
	}

	/**
	 * Coverage for count_checked_in, including the approved-only scoping.
	 *
	 * @covers ::count_checked_in
	 *
	 * @return void
	 */
	public function test_count_checked_in_counts_approved_rsvps_only(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();
		$event_id = $rsvp['event_id'];

		$this->assertSame(
			0,
			$instance->count_checked_in( $event_id ),
			'An event with no check-ins should count zero.'
		);

		$instance->check_in( $rsvp['rsvp_id'] );

		$this->assertSame(
			1,
			$instance->count_checked_in( $event_id ),
			'A checked-in approved RSVP should be counted.'
		);

		$pending_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '0',
				'user_id'          => 2,
			)
		);

		$instance->check_in( $pending_id );

		$this->assertSame(
			1,
			$instance->count_checked_in( $event_id ),
			'A checked-in RSVP awaiting moderation should not be counted.'
		);
	}

	/**
	 * Coverage for delete_check_in, the orphan sweep on comment deletion.
	 *
	 * @covers ::delete_check_in
	 *
	 * @return void
	 */
	public function test_delete_check_in_removes_the_arrival_time(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->check_in( $rsvp['rsvp_id'] );
		$instance->delete_check_in( $rsvp['rsvp_id'] );

		$this->assertFalse(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'Deleting an RSVP should take its arrival time with it.'
		);
	}

	/**
	 * Coverage for the check-in actions, which report the RSVP to consumers.
	 *
	 * @covers ::check_in
	 * @covers ::clear
	 *
	 * @return void
	 */
	public function test_check_in_fires_actions(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();
		$fired    = array();

		add_action(
			'gatherpress_rsvp_checked_in',
			static function ( int $rsvp_id, string $timestamp ) use ( &$fired ): void {
				$fired['checked_in'] = array( $rsvp_id, '' !== $timestamp );
			},
			10,
			2
		);
		add_action(
			'gatherpress_rsvp_check_in_cleared',
			static function ( int $rsvp_id ) use ( &$fired ): void {
				$fired['cleared'] = $rsvp_id;
			}
		);

		$instance->check_in( $rsvp['rsvp_id'] );
		$instance->clear( $rsvp['rsvp_id'] );

		remove_all_actions( 'gatherpress_rsvp_checked_in' );
		remove_all_actions( 'gatherpress_rsvp_check_in_cleared' );

		$this->assertSame(
			array( $rsvp['rsvp_id'], true ),
			$fired['checked_in'],
			'The check-in action should report the RSVP and a timestamp.'
		);
		$this->assertSame(
			$rsvp['rsvp_id'],
			$fired['cleared'],
			'The cleared action should report the RSVP.'
		);
	}
}
