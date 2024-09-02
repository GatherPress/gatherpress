<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Rsvp;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rsvp.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp
 */
class Test_Rsvp extends Base {
	/**
	 * Coverage for get method.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get(): void {
		$post    = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$rsvp    = new Rsvp( $post->ID );
		$user_id = $this->factory->user->create();
		$status  = 'attending';

		$this->assertEmpty( $rsvp->get( 0 ) );
		$this->assertEquals( 0, $rsvp->get( $user_id )['id'] );

		$rsvp->save( $user_id, $status );

		$data = $rsvp->get( $user_id );

		$this->assertSame( $post->ID, intval( $data['post_id'] ) );
		$this->assertSame( $user_id, intval( $data['user_id'] ) );
		$this->assertSame( $status, $data['status'] );
		$this->assertIsInt( strtotime( $data['timestamp'] ) );
		$this->assertNotEmpty( $data['id'] );
	}

	/**
	 * Coverage for save method.
	 *
	 * @covers ::save
	 * @covers ::__construct
	 */
	public function test_save(): void {
		$post    = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$rsvp    = new Rsvp( $post->ID );
		$user_id = $this->factory->user->create();
		$status  = 'attending';

		$this->assertSame( $status, $rsvp->save( $user_id, $status )['status'], 'Failed to assert user is attending.' );

		$status = 'not_attending';

		$this->assertSame( $status, $rsvp->save( $user_id, $status )['status'], 'Failed to assert user is not attending.' );

		$this->assertSame( 'no_status', $rsvp->save( 0, $status )['status'], 'Failed to assert no_status due to invalid user ID.' );

		$status = 'unittest';

		$this->assertSame( 'no_status', $rsvp->save( $user_id, $status )['status'], 'Failed to assert no_status due to invalid status.' );

		$rsvp = new Rsvp( $post->ID );

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$status    = 'attending';

		$this->assertSame( 'attending', $rsvp->save( $user_1_id, $status )['status'], 'Failed to assert that user 1 is attending.' );
		$this->assertSame( 'waiting_list', $rsvp->save( $user_2_id, $status )['status'], 'Failed to assert that user 2 is on waiting list.' );

		$user_1_id = $this->factory->user->create();

		// When not_attending and anonymous, user record should be removed and marked no_status.
		$this->assertSame( 'waiting_list', $rsvp->save( $user_1_id, 'attending', 1 )['status'], 'Failed to assert that user 1 is attending' );
		$this->assertSame( 'no_status', $rsvp->save( $user_1_id, 'not_attending', 1 )['status'], 'Failed to assert that user 1 is no_status.' );

		$user_2_id = $this->factory->user->create();

		$this->assertSame( 'no_status', $rsvp->save( $user_2_id, 'no_status' )['status'], 'Failed to assert that user 2 is no_status.' );

		$post      = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
				'post_meta' => array(
					'gatherpress_max_guest_limit' => 2,
				),
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$user_1_id = $this->factory->user->create();
		$this->assertSame( 2, $rsvp->save( $user_1_id, 'attending', 0, 3 )['guests'], 'Failed to assert that user 1 can only bring 2 guests at most.' );
	}

	/**
	 * Coverage for check_waiting_list method.
	 *
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list(): void {
		$event_id = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get()->ID;
		$rsvp     = new Rsvp( $event_id );

		$this->assertEquals( 0, $rsvp->check_waiting_list(), 'Failed to assert expected waiting list value.' );

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 2 );

		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$user_3_id = $this->factory->user->create();
		$user_4_id = $this->factory->user->create();

		$rsvp->save( $user_1_id, 'attending' );
		$rsvp->save( $user_2_id, 'attending' );
		$rsvp->save( $user_3_id, 'attending' );
		$rsvp->save( $user_4_id, 'attending' );

		$this->assertSame( 'attending', $rsvp->get( $user_1_id )['status'], 'Failed to assert user 1 is attending.' );
		$this->assertSame( 'attending', $rsvp->get( $user_2_id )['status'], 'Failed to assert user 2 is attending.' );
		$this->assertSame( 'waiting_list', $rsvp->get( $user_3_id )['status'], 'Failed to assert user 3 is on waiting list.' );
		$this->assertSame( 'waiting_list', $rsvp->get( $user_3_id )['status'], 'Failed to assert user 4 is on waiting list.' );
		$this->assertEquals( 0, $rsvp->check_waiting_list(), 'Failed to assert expected waiting list value.' );

		$rsvp->save( $user_1_id, 'not_attending' );

		// Give it a slight delay to move member from waiting_list to attending (w/o test sometimes fails).
		sleep( 1 );

		$this->assertSame( 'attending', $rsvp->get( $user_3_id )['status'], 'Failed to assert user 3 is on attending.' );
		$this->assertSame( 0, $rsvp->check_waiting_list(), 'Failed to assert expected waiting list value.' );

		$rsvp->save( $user_1_id, 'attending' );

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 5 );

		$this->assertEquals( 2, $rsvp->check_waiting_list(), 'Failed to assert expected waiting list value.' );

		$this->assertSame( 'attending', $rsvp->get( $user_1_id )['status'], 'Failed to assert user 1 is attending.' );
		$this->assertSame( 'attending', $rsvp->get( $user_2_id )['status'], 'Failed to assert user 2 is attending.' );
		$this->assertSame( 'attending', $rsvp->get( $user_3_id )['status'], 'Failed to assert user 3 is attending.' );
		$this->assertSame( 'attending', $rsvp->get( $user_3_id )['status'], 'Failed to assert user 4 is attending.' );
	}

	/**
	 * Coverage for attending_limit_reached method.
	 *
	 * @covers ::attending_limit_reached
	 *
	 * @return void
	 */
	public function test_attending_limit_reached(): void {
		$post = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$rsvp = new Rsvp( $post->ID );

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		$current_response = array(
			'status' => 'waiting_list',
			'guests' => 0,
		);

		$this->assertFalse(
			$rsvp->attending_limit_reached( $current_response ),
			'Failed to assert that limit has not been reached.'
		);

		$user_id = $this->factory->user->create();

		$rsvp->save( $user_id, 'attending' );

		$current_response = $rsvp->get( $user_id );

		$this->assertTrue(
			$rsvp->attending_limit_reached( $current_response, 1 ),
			'Failed to assert that limit has been reached.'
		);

		$this->assertFalse(
			$rsvp->attending_limit_reached( $current_response ),
			'Failed to assert that limit has not been reached.'
		);
	}

	/**
	 * Coverages for responses method.
	 *
	 * @covers ::responses
	 * @covers ::sort_by_role
	 *
	 * @return void
	 */
	public function test_responses(): void {
		$post      = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$user_id_1 = $this->factory->user->create();
		$user_id_2 = $this->factory->user->create();

		$rsvp->save( $user_id_1, 'attending' );
		$rsvp->save( $user_id_2, 'not_attending' );

		$responses = $rsvp->responses();

		$this->assertEquals( 2, $responses['all']['count'], 'Failed to assert that count is 2.' );
		$this->assertEquals(
			$user_id_1,
			$responses['attending']['responses'][0]['id'],
			'Failed to assert user ID matches.'
		);
		$this->assertEquals(
			$user_id_2,
			$responses['not_attending']['responses'][0]['id'],
			'Failed to assert user ID matches.'
		);

		wp_delete_user( $user_id_2 );

		$responses = $rsvp->responses();

		$this->assertEmpty(
			$responses['not_attending']['responses'],
			'Failed not_attending responses are empty after $user_id_2 was deleted.'
		);

		$post      = $this->mock->post(
			array(
				'post_type' => 'post',
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$responses = $rsvp->responses();

		$this->assertEmpty( $responses['all']['responses'], 'Failed to assert all responses empty with non-event post type.' );
		$this->assertEquals( 0, $responses['count'], 'Failed to assert count is 0 with non-event post type.' );

		$this->mock->user( 'subscriber' );

		$post      = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$user_id_3 = $this->factory->user->create();

		$rsvp->save( $user_id_3, 'attending', 1 );

		$responses = $rsvp->responses();

		$this->assertEquals(
			0,
			$responses['all']['responses'][0]['id'],
			'Failed to assert user ID matches 0.'
		);
		$this->assertEquals(
			0,
			$responses['attending']['responses'][0]['id'],
			'Failed to assert user ID matches 0.'
		);
		$this->assertEmpty(
			$responses['all']['responses'][0]['profile'],
			'Failed to assert profile is empty.'
		);
		$this->assertEmpty(
			$responses['attending']['responses'][0]['profile'],
			'Failed to assert profile is empty.'
		);
		$this->assertSame(
			'Anonymous',
			$responses['all']['responses'][0]['name'],
			'Failed to assert user display name is Anonymous.'
		);
		$this->assertSame(
			'Anonymous',
			$responses['attending']['responses'][0]['name'],
			'Failed to assert user display name is Anonymous.'
		);
	}

	/**
	 * Coverage for sort_by_timestamp method.
	 *
	 * @covers ::sort_by_timestamp
	 *
	 * @return void
	 */
	public function test_sort_by_timestamp(): void {
		$post  = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$rsvp  = new Rsvp( $post->ID );
		$newer = array( 'timestamp' => '2023-05-11 08:30:00' );
		$older = array( 'timestamp' => '2022-05-11 08:30:00' );

		$this->assertTrue(
			$rsvp->sort_by_timestamp( $newer, $older ),
			'Failed to assert correct sorting of timestamp.'
		);
		$this->assertFalse(
			$rsvp->sort_by_timestamp( $older, $newer ),
			'Failed to assert correct sorting of timestamp.'
		);
	}
}
