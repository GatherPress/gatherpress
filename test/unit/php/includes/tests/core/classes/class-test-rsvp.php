<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;

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
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp    = new Rsvp( $post->ID );
		$user_id = $this->factory->user->create();
		$status  = 'attending';

		$this->assertEmpty( $rsvp->get( 0 ) );
		$this->assertEquals( 0, $rsvp->get( $user_id )['comment_id'] );

		$rsvp->save( $user_id, $status );

		$data = $rsvp->get( $user_id );

		$this->assertSame( $post->ID, intval( $data['post_id'] ) );
		$this->assertSame( $user_id, intval( $data['user_id'] ) );
		$this->assertSame( $status, $data['status'] );
		$this->assertIsInt( strtotime( $data['timestamp'] ) );
		$this->assertNotEmpty( $data['comment_id'] );
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
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp    = new Rsvp( $post->ID );
		$user_id = $this->factory->user->create();
		$status  = 'attending';

		$this->assertSame( $status, $rsvp->save( $user_id, $status )['status'], 'Failed to assert user is attending.' );

		$status = 'not_attending';

		$this->assertSame(
			$status,
			$rsvp->save( $user_id, $status )['status'],
			'Failed to assert user is not attending.'
		);

		$this->assertSame(
			'no_status',
			$rsvp->save( 0, $status )['status'],
			'Failed to assert no_status due to invalid user ID.'
		);

		$status = 'unittest';

		$this->assertSame(
			'no_status',
			$rsvp->save( $user_id, $status )['status'],
			'Failed to assert no_status due to invalid status.'
		);

		$rsvp = new Rsvp( $post->ID );

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$status    = 'attending';

		$this->assertSame(
			'attending',
			$rsvp->save( $user_1_id, $status )['status'],
			'Failed to assert that user 1 is attending.'
		);
		$this->assertSame(
			'waiting_list',
			$rsvp->save( $user_2_id, $status )['status'],
			'Failed to assert that user 2 is on waiting list.'
		);

		$user_1_id = $this->factory->user->create();

		// Enable anonymous RSVP for this test.
		update_post_meta( $post->ID, 'gatherpress_enable_anonymous_rsvp', true );

		// When not_attending and anonymous, user record should be removed and marked no_status.
		$this->assertSame(
			'waiting_list',
			$rsvp->save( $user_1_id, 'attending', 1 )['status'],
			'Failed to assert that user 1 is attending'
		);
		$this->assertSame(
			'no_status',
			$rsvp->save( $user_1_id, 'not_attending', 1 )['status'],
			'Failed to assert that user 1 is no_status.'
		);

		$user_2_id = $this->factory->user->create();

		$this->assertSame(
			'no_status',
			$rsvp->save( $user_2_id, 'no_status' )['status'],
			'Failed to assert that user 2 is no_status.'
		);

		$post      = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_meta' => array(
					'gatherpress_max_guest_limit' => 2,
				),
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$user_1_id = $this->factory->user->create();
		$this->assertSame(
			2,
			$rsvp->save( $user_1_id, 'attending', 0, 3 )['guests'],
			'Failed to assert that user 1 can only bring 2 guests at most.'
		);

		// Simulate error saving RSVP.
		add_filter( 'query', '__return_false' );

		$result   = $rsvp->save( $user_1_id, 'attending' );
		$expected = array(
			'comment_id' => 0,
			'post_id'    => 0,
			'user_id'    => 0,
			'timestamp'  => '0000-00-00 00:00:00',
			'status'     => 'no_status',
			'guests'     => 0,
			'anonymous'  => 0,
		);

		$this->assertEquals( $expected, $result );

		remove_filter( 'query', '__return_false' );
	}

	/**
	 * Test check waiting list with no attendees.
	 *
	 * @since  1.0.0
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_with_no_attendees(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp     = new Rsvp( $event_id );

		$this->assertEquals(
			0,
			$rsvp->check_waiting_list(),
			'Should return 0 when there are no attendees'
		);
	}

	/**
	 * Test check waiting list with unlimited attendance.
	 *
	 * @since  1.0.0
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_with_unlimited_attendance(): void {
		$event_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp      = new Rsvp( $event_id );
		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$user_3_id = $this->factory->user->create();

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		// Fill the one spot.
		$rsvp->save( $user_1_id, 'attending' );

		// These should go to waiting list.
		$rsvp->save( $user_2_id, 'attending' );
		$rsvp->save( $user_3_id, 'attending' );

		// Now remove the limit.
		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 0 );

		$this->assertEquals(
			2,
			$rsvp->check_waiting_list(),
			'Should move all waiting list members to attending when no limit'
		);
	}

	/**
	 * Test check waiting list with limited attendance.
	 *
	 * @since  1.0.0
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_with_limited_attendance(): void {
		$event_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp      = new Rsvp( $event_id );
		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		$rsvp->save( $user_1_id, 'waiting_list' );
		$rsvp->save( $user_2_id, 'waiting_list' );

		$this->assertEquals(
			0,
			$rsvp->check_waiting_list(),
			'Should not move anyone to attending when limit is 1'
		);

		$this->assertSame(
			'attending',
			$rsvp->get( $user_1_id )['status'],
			'First user should have moved to attending'
		);

		$this->assertSame(
			'waiting_list',
			$rsvp->get( $user_2_id )['status'],
			'Second user should remain on waiting list'
		);
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
				'post_type' => Event::POST_TYPE,
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

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 0 );

		$this->assertFalse(
			$rsvp->attending_limit_reached( $current_response, 1 ),
			'Failed to assert that limit has not been reached.'
		);

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

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
				'post_type' => Event::POST_TYPE,
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
			$responses['attending']['records'][0]['userId'],
			'Failed to assert user ID matches.'
		);
		$this->assertEquals(
			$user_id_2,
			$responses['not_attending']['records'][0]['userId'],
			'Failed to assert user ID matches.'
		);

		wp_delete_user( $user_id_2 );

		// User will remain while cached until it expires.
		wp_cache_delete( sprintf( Rsvp::CACHE_KEY, $post->ID ), GATHERPRESS_CACHE_GROUP );

		$responses = $rsvp->responses();

		$this->assertEmpty(
			$responses['not_attending']['records'],
			'Failed not_attending responses are empty after $user_id_2 was deleted.'
		);

		$post      = $this->mock->post(
			array(
				'post_type' => 'post',
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$responses = $rsvp->responses();

		$this->assertEmpty(
			$responses['all']['records'],
			'Failed to assert all responses empty with non-event post type.'
		);
		$this->assertEquals( 0, $responses['count'], 'Failed to assert count is 0 with non-event post type.' );

		$this->mock->user( 'subscriber' );

		$post      = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_meta' => array(
					'gatherpress_enable_anonymous_rsvp' => true,
				),
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$user_id_3 = $this->factory->user->create();

		$rsvp->save( $user_id_3, 'attending', 1 );

		$responses = $rsvp->responses();

		$this->assertEquals(
			0,
			$responses['all']['records'][0]['id'],
			'Failed to assert user ID matches 0.'
		);
		$this->assertEquals(
			0,
			$responses['attending']['records'][0]['id'],
			'Failed to assert user ID matches 0.'
		);
		$this->assertEmpty(
			$responses['all']['records'][0]['profile'],
			'Failed to assert profile is empty.'
		);
		$this->assertEmpty(
			$responses['attending']['records'][0]['profile'],
			'Failed to assert profile is empty.'
		);
		$this->assertSame(
			'Anonymous',
			$responses['all']['records'][0]['name'],
			'Failed to assert user display name is Anonymous.'
		);
		$this->assertSame(
			'Anonymous',
			$responses['attending']['records'][0]['name'],
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
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp  = new Rsvp( $post->ID );
		$newer = array( 'timestamp' => '2023-05-11 08:30:00' );
		$older = array( 'timestamp' => '2022-05-11 08:30:00' );

		$this->assertSame(
			-1,
			$rsvp->sort_by_timestamp( $older, $newer ),
			'Failed to assert that it returns a negative number while the first response\'s timestamp is earlier.'
		);

		$this->assertSame(
			1,
			$rsvp->sort_by_timestamp( $newer, $older ),
			'Failed to assert that it returns a positive number while the second response\'s timestamp is earlier.'
		);

		$this->assertSame(
			0,
			$rsvp->sort_by_timestamp( $newer, $newer ),
			'Failed to assert that it returns 0 while both response\'s timestamps are equal.'
		);
	}
}
