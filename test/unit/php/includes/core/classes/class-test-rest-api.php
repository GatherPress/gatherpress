<?php
/**
 * Class handles unit tests for GatherPress\Core\Query.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rest_Api;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class Test_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Rest_Api
 */
class Test_Rest_Api extends Base {
	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Rest_Api::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'rest_api_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_endpoints' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_send_emails',
				'priority' => 10,
				'callback' => array( $instance, 'send_emails' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'rest_prepare_%s', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'prepare_event_data' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_endpoints method.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_register_endpoints(): void {
		$instance = Rest_Api::get_instance();

		$instance->register_endpoints();

		$rest_server = rest_get_server();
		$namespace   = Utility::get_hidden_property(
			$rest_server,
			'namespaces'
		)[ sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ) ];

		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert general event endpoint is registered'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/datetime', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert datetime endpoint is registered'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/email', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert email endpoint is registered'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/rsvp', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert rsvp endpoint is registered'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/events-list', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert events-list endpoint is registered'
		);
	}

	/**
	 * Coverage for get_event_routes method.
	 *
	 * @covers ::get_event_routes
	 * @covers ::datetime_route
	 * @covers ::email_route
	 * @covers ::rsvp_route
	 * @covers ::events_list_route
	 *
	 * @return void
	 */
	public function test_get_event_routes(): void {
		$instance = Rest_Api::get_instance();
		$routes   = Utility::invoke_hidden_method( $instance, 'get_event_routes' );

		$this->assertSame( 'datetime', $routes[0]['route'], 'Failed to assert route is datetime.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[0]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'email', $routes[1]['route'], 'Failed to assert route is email.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[1]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'rsvp', $routes[2]['route'], 'Failed to assert route is rsvp.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[2]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'events-list', $routes[3]['route'], 'Failed to assert route is rsvp.' );
		$this->assertSame(
			WP_REST_Server::READABLE,
			$routes[3]['args']['methods'],
			'Failed to assert methods is GET.'
		);
	}

	/**
	 * Coverage for validate_rsvp_status method.
	 *
	 * @covers ::validate_rsvp_status
	 *
	 * @return void
	 */
	public function test_validate_rsvp_status(): void {
		$instance = Rest_Api::get_instance();

		$this->assertTrue(
			$instance->validate_rsvp_status( 'attending' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertTrue(
			$instance->validate_rsvp_status( 'not_attending' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertFalse(
			$instance->validate_rsvp_status( 'attend' ),
			'Failed to assert invalid attendance status.'
		);
		$this->assertFalse(
			$instance->validate_rsvp_status( 'wait_list' ),
			'Failed to assert invalid attendance status.'
		);
	}

	/**
	 * Data provider for validate_send test.
	 *
	 * @return array[]
	 */
	public function data_validate_send(): array {
		return array(
			array(
				array(
					'all'           => true,
					'attending'     => false,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				true,
			),
			array(
				array(
					'unit_test' => true,
				),
				false,
			),
			array(
				null,
				false,
			),
			array(
				'unit-test',
				false,
			),
			array(
				array(
					'all'           => null,
					'attending'     => false,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				false,
			),
		);
	}

	/**
	 * Coverage for validate_send method.
	 *
	 * @dataProvider data_validate_send
	 *
	 * @covers ::validate_send
	 *
	 * @param mixed $params  The parameters to send for validation.
	 * @param bool  $expects Expected response.
	 *
	 * @return void
	 */
	public function test_validate_send( $params, bool $expects ): void {
		$instance = Rest_Api::get_instance();

		$this->assertSame( $expects, $instance->validate_send( $params ) );
	}

	/**
	 * Coverage for validate_event_post_id method.
	 *
	 * @covers ::validate_event_post_id
	 * @covers ::validate_number
	 *
	 * @return void
	 */
	public function test_validate_event_post_id(): void {
		$instance = Rest_Api::get_instance();
		$post     = $this->mock->post()->get();
		$event    = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertFalse(
			$instance->validate_event_post_id( -4 ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			$instance->validate_event_post_id( 0 ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			$instance->validate_event_post_id( 'unit-test' ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			$instance->validate_event_post_id( $post->ID ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertTrue(
			$instance->validate_event_post_id( $event->ID ),
			'Failed to assert valid event post ID.'
		);
	}

	/**
	 * Coverage for validate_event_list_type method.
	 *
	 * @covers ::validate_event_list_type
	 *
	 * @return void
	 */
	public function test_validate_event_list_type(): void {
		$instance = Rest_Api::get_instance();

		$this->assertTrue(
			$instance->validate_event_list_type( 'upcoming' ),
			'Failed to assert valid event list type.'
		);
		$this->assertTrue(
			$instance->validate_event_list_type( 'past' ),
			'Failed to assert valid event list type.'
		);
		$this->assertFalse(
			$instance->validate_event_list_type( 'unit-test' ),
			'Failed to assert not a valid event list type.'
		);
	}

	/**
	 * Coverage for validate_datetime method.
	 *
	 * @covers ::validate_datetime
	 *
	 * @return void
	 */
	public function test_validate_datetime(): void {
		$instance = Rest_Api::get_instance();

		$this->assertFalse(
			$instance->validate_datetime( 'unit-test' ),
			'Failed to assert invalid datetime.'
		);
		$this->assertTrue(
			$instance->validate_datetime( '2023-05-11 08:30:00' ),
			'Failed to assert valid datatime.'
		);
	}

	/**
	 * Coverage for validate_timezone method.
	 *
	 * @covers ::validate_timezone
	 *
	 * @return void
	 */
	public function test_validate_timezone(): void {
		$instance = Rest_Api::get_instance();
		$this->assertFalse(
			$instance->validate_timezone( 'unit-test' ),
			'Failed to assert invalid timezone.'
		);

		$this->assertTrue(
			$instance->validate_timezone( 'America/New_York' ),
			'Failed to assert valid timezone.'
		);
	}

	/**
	 * Coverage for update_datetime method.
	 *
	 * @covers ::update_datetime
	 *
	 * @return void
	 */
	public function test_update_datetime(): void {
		$instance = Rest_Api::get_instance();

		$request  = new WP_REST_Request( 'POST' );
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		$request->set_query_params(
			array(
				'datetime_end'   => '2023-09-13 20:00:00',
				'datetime_start' => '2023-09-13 19:00:00',
				'post_id'        => $event_id,
				'timezone'       => 'America/New_York',
			)
		);

		$response = $instance->update_datetime( $request );

		$this->assertEquals( 1, $response->data['success'], 'Failed to assert that success was true.' );

		$event = new Event( $event_id );

		$this->assertSame(
			'Wednesday, September 13, 2023 7:00 PM to 8:00 PM EDT',
			$event->get_display_datetime(),
			'Failed to assert datetime display matches.'
		);
	}

	/**
	 * Coverage for email method.
	 *
	 * @covers ::email
	 *
	 * @return void
	 */
	public function test_email(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Rest_Api::get_instance();
		$request  = new WP_REST_Request( 'POST' );
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		$request->set_query_params(
			array(
				'post_id' => $event_id,
				'message' => 'Unit test',
				'send'    => array(
					'all'           => false,
					'attending'     => false,
					'waiting_list'  => false,
					'not_attending' => true,
				),
			)
		);

		$response = $instance->email( $request );

		$this->assertEquals( 1, $response->data['success'], 'Failed to assert that success was true.' );
	}

	/**
	 * Coverage for send_emails method.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_email(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Rest_Api::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$post_id  = $this->mock->post(
			array( 'post_type' => 'post' )
		)->get()->ID;
		$user_id  = $this->mock->user()->get()->ID;
		$message  = 'Unit test.';
		$send     = array(
			'all'           => false,
			'attending'     => true,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$event = new Event( $event_id );
		$event->rsvp->save( $user_id, 'attending' );

		$this->assertFalse(
			$instance->send_emails( $post_id, $send, $message ),
			'Failed to assert false for sending email.'
		);
		$this->assertTrue(
			$instance->send_emails( $event_id, $send, $message ),
			'Failed to assert true for sending email.'
		);
	}

	/**
	 * Coverage for get_members method.
	 *
	 * @covers ::get_members
	 *
	 * @return void
	 */
	public function test_get_members(): void {
		$instance = Rest_Api::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$send     = array(
			'all'           => false,
			'attending'     => false,
			'waiting_list'  => false,
			'not_attending' => false,
		);
		$event    = new Event( $event_id );
		$members  = $instance->get_members( $send, $event_id );

		$this->assertEmpty( $members );

		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$user_3_id = $this->factory->user->create();
		$user_4_id = $this->factory->user->create();

		$event->rsvp->save( $user_1_id, 'attending' );
		$event->rsvp->save( $user_2_id, 'not_attending' );
		$event->rsvp->save( $user_3_id, 'attending' );
		$event->rsvp->save( $user_4_id, 'waiting_list' );

		$send['all'] = true;
		$members     = $instance->get_members( $send, $event_id );
		$member_ids  = $this->get_member_ids( $members );

		$this->assertContains( $user_1_id, $member_ids, 'Failed to assert user ID is in array.' );
		$this->assertContains( $user_2_id, $member_ids, 'Failed to assert user ID is in array.' );
		$this->assertContains( $user_3_id, $member_ids, 'Failed to assert user ID is in array.' );
		$this->assertContains( $user_4_id, $member_ids, 'Failed to assert user ID is in array.' );

		$send['all']       = false;
		$send['attending'] = true;
		$members           = $instance->get_members( $send, $event_id );
		$member_ids        = $this->get_member_ids( $members );

		$this->assertContains( $user_1_id, $member_ids, 'Failed to assert user ID is in array.' );
		$this->assertNotContains( $user_2_id, $member_ids, 'Failed to assert user ID is not in array.' );
		$this->assertContains( $user_3_id, $member_ids, 'Failed to assert user ID is in array.' );
		$this->assertNotContains( $user_4_id, $member_ids, 'Failed to assert user ID is not in array.' );

		$send['attending']    = false;
		$send['waiting_list'] = true;
		$members              = $instance->get_members( $send, $event_id );
		$member_ids           = $this->get_member_ids( $members );

		$this->assertNotContains( $user_1_id, $member_ids, 'Failed to assert user ID is not in array.' );
		$this->assertNotContains( $user_2_id, $member_ids, 'Failed to assert user ID is not in array.' );
		$this->assertNotContains( $user_3_id, $member_ids, 'Failed to assert user ID is not in array.' );
		$this->assertContains( $user_4_id, $member_ids, 'Failed to assert user ID is in array.' );

		$send['not_attending'] = true;
		$send['waiting_list']  = true;
		$members               = $instance->get_members( $send, $event_id );
		$member_ids            = $this->get_member_ids( $members );

		$this->assertNotContains( $user_1_id, $member_ids, 'Failed to assert user ID is not in array.' );
		$this->assertContains( $user_2_id, $member_ids, 'Failed to assert user ID is in array.' );
		$this->assertNotContains( $user_3_id, $member_ids, 'Failed to assert user ID is not in array.' );
		$this->assertContains( $user_4_id, $member_ids, 'Failed to assert user ID is in array.' );
	}

	/**
	 * Helper to get members IDs for test.
	 *
	 * @param array $members Array of user objects.
	 *
	 * @return array
	 */
	protected function get_member_ids( array $members ): array {
		return array_map(
			static function( $member ): int {
				return $member->ID;
			},
			$members
		);
	}

	/**
	 * Coverage for events_list method.
	 *
	 * @covers ::events_list
	 *
	 * @return void
	 */
	public function test_events_list(): void {
		$instance          = Rest_Api::get_instance();
		$request           = new WP_REST_Request( 'POST' );
		$upcoming_event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$past_event_id     = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$upcoming_event    = new Event( $upcoming_event_id );
		$past_event        = new Event( $past_event_id );

		$request->set_query_params(
			array(
				'event_list_type' => 'upcoming',
			)
		);

		$upcoming_event->save_datetimes(
			array(
				'datetime_start' => gmdate( Event::DATETIME_FORMAT, strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( Event::DATETIME_FORMAT, strtotime( '+2 day' ) ),
				'timezone'       => 'America/New_York',
			)
		);

		$past_event->save_datetimes(
			array(
				'datetime_start' => gmdate( Event::DATETIME_FORMAT, strtotime( '-2 day' ) ),
				'datetime_end'   => gmdate( Event::DATETIME_FORMAT, strtotime( '-1 day' ) ),
				'timezone'       => 'America/New_York',
			)
		);

		$response  = $instance->events_list( $request );
		$event_ids = $this->get_event_ids( $response->data );

		$this->assertContains( $upcoming_event_id, $event_ids, 'Failed to assert event ID is in array.' );
		$this->assertNotContains( $past_event_id, $event_ids, 'Failed to asssert event ID is not in array.' );

		$request->set_query_params(
			array(
				'event_list_type' => 'past',
			)
		);

		$response  = $instance->events_list( $request );
		$event_ids = $this->get_event_ids( $response->data );

		$this->assertContains( $past_event_id, $event_ids, 'Failed to assert event ID is in array.' );
		$this->assertNotContains( $upcoming_event_id, $event_ids, 'Failed to assert event ID is not in array.' );
	}

	/**
	 * Helper to get members IDs for test.
	 *
	 * @param array $events Response from events_list.
	 *
	 * @return array
	 */
	protected function get_event_ids( array $events ): array {
		return array_map(
			static function( $event ): int {
				return $event['ID'];
			},
			$events
		);
	}

	/**
	 * Coverage for max_number method.
	 *
	 * @covers ::max_number
	 *
	 * @return void
	 */
	public function test_max_number(): void {
		$instance = Rest_Api::get_instance();

		$this->assertEquals(
			5,
			Utility::invoke_hidden_method( $instance, 'max_number', array( 6, 5 ) ),
			'Failed to assert that numbers are equal.'
		);
		$this->assertEquals(
			3,
			Utility::invoke_hidden_method( $instance, 'max_number', array( 3, 5 ) ),
			'Failed to assert that numbers are equal.'
		);
	}

	/**
	 * Coverage for update_rsvp method.
	 *
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_update_rsvp(): void {
		$instance = Rest_Api::get_instance();
		$request  = new WP_REST_Request( 'POST' );
		$user_id  = $this->mock->user( true, 'admin' )->get()->ID;
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$event    = new Event( $event_id );

		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( Event::DATETIME_FORMAT, strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( Event::DATETIME_FORMAT, strtotime( '+2 day' ) ),
				'timezone'       => 'America/New_York',
			)
		);

		$request->set_query_params(
			array(
				'user_id' => $user_id,
				'post_id' => $event_id,
				'status'  => 'attending',
				'guests'  => 0,
			)
		);

		$response = $instance->update_rsvp( $request );

		$this->assertEquals( 0, $response->data['guests'] );
		$this->assertSame(
			$user_id,
			$response->data['responses']['attending']['responses'][0]['id'],
			'Failed to assert that user ID matches.'
		);
		$this->assertSame(
			$event_id,
			$response->data['event_id'],
			'Failed to assert that event ID matches.'
		);
	}
}
