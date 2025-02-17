<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Rest_Api.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Rest_Api;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class Test_Event_Rest_Api.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Rest_Api
 */
class Test_Event_Rest_Api extends Base {
	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Event_Rest_Api::get_instance();
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
				'callback' => array( $instance, 'handle_email_send_action' ),
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
		$instance = Event_Rest_Api::get_instance();

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
	 * @covers ::email_route
	 * @covers ::rsvp_route
	 * @covers ::events_list_route
	 *
	 * @return void
	 */
	public function test_get_event_routes(): void {
		$instance = Event_Rest_Api::get_instance();
		$routes   = Utility::invoke_hidden_method( $instance, 'get_event_routes' );

		$this->assertSame( 'email', $routes[0]['route'], 'Failed to assert route is email.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[0]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'rsvp', $routes[1]['route'], 'Failed to assert route is rsvp.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[1]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'rsvp-status-html', $routes[2]['route'], 'Failed to assert route is rsvp-status-html.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[2]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'rsvp-responses', $routes[3]['route'], 'Failed to assert route is rsvp-responses.' );
		$this->assertSame(
			WP_REST_Server::READABLE,
			$routes[3]['args']['methods'],
			'Failed to assert methods is GET.'
		);
		$this->assertSame( 'events-list', $routes[4]['route'], 'Failed to assert route is events-list.' );
		$this->assertSame(
			WP_REST_Server::READABLE,
			$routes[4]['args']['methods'],
			'Failed to assert methods is GET.'
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

		$instance = Event_Rest_Api::get_instance();
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

		$instance = Event_Rest_Api::get_instance();
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
		$instance = Event_Rest_Api::get_instance();
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

		Utility::set_and_get_hidden_property( $event->rsvp, 'max_attendance_limit', 2 );

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
			static function ( $member ): int {
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
		$instance          = Event_Rest_Api::get_instance();
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
		$this->assertNotContains( $past_event_id, $event_ids, 'Failed to assert event ID is not in array.' );

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
			static function ( $event ): int {
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
		$instance = Event_Rest_Api::get_instance();

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
		$instance = Event_Rest_Api::get_instance();
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
			$response->data['responses']['attending']['records'][0]['id'],
			'Failed to assert that user ID matches.'
		);
		$this->assertSame(
			$event_id,
			$response->data['event_id'],
			'Failed to assert that event ID matches.'
		);
	}
}
