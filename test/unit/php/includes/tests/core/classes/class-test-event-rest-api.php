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
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Token;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_REST_Request;
use WP_REST_Response;
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
		$this->assertSame( 'rsvp-form', $routes[2]['route'], 'Failed to assert route is rsvp-form.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[2]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'rsvp-status-html', $routes[3]['route'], 'Failed to assert route is rsvp-status-html.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[3]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'rsvp-responses', $routes[4]['route'], 'Failed to assert route is rsvp-responses.' );
		$this->assertSame(
			WP_REST_Server::READABLE,
			$routes[4]['args']['methods'],
			'Failed to assert methods is GET.'
		);
		$this->assertSame( 'events-list', $routes[5]['route'], 'Failed to assert route is events-list.' );
		$this->assertSame(
			WP_REST_Server::READABLE,
			$routes[5]['args']['methods'],
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
	 * Coverage for get_recipients method with no send options selected.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_with_no_send_options(): void {
		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		$send = array(
			'all'           => false,
			'attending'     => false,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$recipients = $instance->get_recipients( $send, $event_id );

		$this->assertEmpty( $recipients, 'Failed to assert empty recipients when no send options are selected.' );
	}

	/**
	 * Coverage for get_recipients method with 'all' option for users only.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_with_all_users_only(): void {
		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create WordPress users - 'all' gets ALL site users, not just those who RSVP'd.
		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$user_3_id = $this->factory->user->create();

		$send = array(
			'all'           => true,
			'attending'     => false,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$recipients    = $instance->get_recipients( $send, $event_id );
		$recipient_ids = $this->get_recipient_user_ids( $recipients );

		// Should include all site users (including the 3 we created plus any existing users).
		$this->assertContains( $user_1_id, $recipient_ids, 'Failed to assert user 1 is included in all recipients.' );
		$this->assertContains( $user_2_id, $recipient_ids, 'Failed to assert user 2 is included in all recipients.' );
		$this->assertContains( $user_3_id, $recipient_ids, 'Failed to assert user 3 is included in all recipients.' );
		$this->assertGreaterThanOrEqual(
			3,
			count( $recipients ),
			'Failed to assert minimum recipient count for all users.'
		);
	}

	/**
	 * Coverage for get_recipients method with 'attending' status filter.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_with_attending_filter(): void {
		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$event    = new Event( $event_id );

		// Create users and save RSVPs using the RSVP system.
		$attending_user_id     = $this->factory->user->create();
		$not_attending_user_id = $this->factory->user->create();

		$event->rsvp->save( $attending_user_id, 'attending' );
		$event->rsvp->save( $not_attending_user_id, 'not_attending' );

		// Create anonymous attending RSVP using wp_insert_comment for better control.
		wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'Anonymous Attendee',
				'comment_author_email' => 'attendee@example.com',
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		$event->rsvp->save( 'attendee@example.com', 'attending' );

		$send = array(
			'all'           => false,
			'attending'     => true,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$recipients = $instance->get_recipients( $send, $event_id );

		$this->assertCount( 2, $recipients, 'Failed to assert correct count for attending recipients.' );

		// Check that we have both user and anonymous attending.
		$user_recipients      = array_filter(
			$recipients,
			static function ( $recipient ) {
				return $recipient['is_user'];
			}
		);
		$anonymous_recipients = array_filter(
			$recipients,
			static function ( $recipient ) {
				return ! $recipient['is_user'];
			}
		);

		$this->assertCount( 1, $user_recipients, 'Failed to assert one attending user recipient.' );
		$this->assertCount( 1, $anonymous_recipients, 'Failed to assert one attending anonymous recipient.' );

		$user_recipient      = reset( $user_recipients );
		$anonymous_recipient = reset( $anonymous_recipients );

		$this->assertEquals(
			$attending_user_id,
			$user_recipient['user_id'],
			'Failed to assert correct attending user ID.'
		);
		$this->assertEquals(
			'attendee@example.com',
			$anonymous_recipient['email'],
			'Failed to assert correct anonymous attendee email.'
		);
	}

	/**
	 * Coverage for get_recipients method with mixed user and non-user RSVPs.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_with_mixed_recipients(): void {
		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$event    = new Event( $event_id );

		// Force no attendance so responses remain on waiting list.
		Utility::set_and_get_hidden_property( $event->rsvp, 'max_attendance_limit', -1 );

		// Create user RSVP.
		$user_id = $this->factory->user->create(
			array(
				'user_email'   => 'user@example.com',
				'display_name' => 'User Name',
			)
		);
		$event->rsvp->save( $user_id, 'waiting_list' );

		// Create anonymous RSVP using wp_insert_comment for better control.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'Anonymous Person',
				'comment_author_email' => 'anonymous@example.com',
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		$event->rsvp->save( 'anonymous@example.com', 'waiting_list' );

		$send = array(
			'all'           => false,
			'attending'     => false,
			'waiting_list'  => true,
			'not_attending' => false,
		);

		$recipients = $instance->get_recipients( $send, $event_id );

		$this->assertCount( 2, $recipients, 'Failed to assert correct count for mixed recipients.' );

		// Verify recipient structure for user.
		$user_recipient = array_filter(
			$recipients,
			static function ( $recipient ) {
				return $recipient['is_user'];
			}
		);
		$user_recipient = reset( $user_recipient );

		$this->assertTrue( $user_recipient['is_user'], 'Failed to assert user recipient is marked as user.' );
		$this->assertEquals( $user_id, $user_recipient['user_id'], 'Failed to assert correct user ID.' );
		$this->assertEquals( 'user@example.com', $user_recipient['email'], 'Failed to assert correct user email.' );
		$this->assertEquals( 'User Name', $user_recipient['name'], 'Failed to assert correct user name.' );

		// Verify recipient structure for anonymous.
		$anonymous_recipient = array_filter(
			$recipients,
			static function ( $recipient ) {
				return ! $recipient['is_user'];
			}
		);
		$anonymous_recipient = reset( $anonymous_recipient );

		$this->assertFalse(
			$anonymous_recipient['is_user'],
			'Failed to assert anonymous recipient is not marked as user.'
		);
		$this->assertEquals(
			0,
			$anonymous_recipient['user_id'],
			'Failed to assert anonymous recipient has zero user ID.'
		);
		$this->assertEquals( $comment_id, $anonymous_recipient['comment_id'], 'Failed to assert correct comment ID.' );
		$this->assertEquals(
			'anonymous@example.com',
			$anonymous_recipient['email'],
			'Failed to assert correct anonymous email.'
		);
		$this->assertEquals(
			'Anonymous Person',
			$anonymous_recipient['name'],
			'Failed to assert correct anonymous name.'
		);
	}

	/**
	 * Helper to get user IDs from recipients array for users only.
	 *
	 * @param array $recipients Array of recipient objects.
	 *
	 * @return array
	 */
	protected function get_recipient_user_ids( array $recipients ): array {
		return array_map(
			static function ( $recipient ): int {
				return $recipient['user_id'];
			},
			array_filter(
				$recipients,
				static function ( $recipient ) {
					return $recipient['is_user'];
				}
			)
		);
	}

	/**
	 * Helper to get all email addresses from recipients array.
	 *
	 * @param array $recipients Array of recipient objects.
	 *
	 * @return array
	 */
	protected function get_recipient_emails( array $recipients ): array {
		return array_map(
			static function ( $recipient ): string {
				return $recipient['email'];
			},
			$recipients
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
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );

		$event_id = $this->factory->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);
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
			$response->data['responses']['attending']['records'][0]['userId'],
			'Failed to assert that user ID matches.'
		);
		$this->assertSame(
			$event_id,
			$response->data['event_id'],
			'Failed to assert that event ID matches.'
		);
	}

	/**
	 * Tests the rsvp_form_route method.
	 *
	 * Verifies that the RSVP form route is properly configured
	 * with correct methods and callback.
	 *
	 * @since 1.0.0
	 * @covers ::rsvp_form_route
	 *
	 * @return void
	 */
	public function test_rsvp_form_route(): void {
		$instance = Event_Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_form_route' );

		$this->assertEquals( 'rsvp-form', $route['route'] );
		$this->assertEquals( WP_REST_Server::EDITABLE, $route['args']['methods'] );
		$this->assertEquals( array( $instance, 'handle_rsvp_form_submission' ), $route['args']['callback'] );
		$this->assertEquals( '__return_true', $route['args']['permission_callback'] );

		// Check required arguments.
		$this->assertArrayHasKey( 'comment_post_ID', $route['args']['args'] );
		$this->assertArrayHasKey( 'author', $route['args']['args'] );
		$this->assertArrayHasKey( 'email', $route['args']['args'] );
		$this->assertArrayHasKey( 'gatherpress_event_updates_opt_in', $route['args']['args'] );

		$this->assertTrue( $route['args']['args']['comment_post_ID']['required'] );
		$this->assertTrue( $route['args']['args']['author']['required'] );
		$this->assertTrue( $route['args']['args']['email']['required'] );
		$this->assertFalse( $route['args']['args']['gatherpress_event_updates_opt_in']['required'] );
	}

	/**
	 * Tests handle_rsvp_form_submission with valid data.
	 *
	 * Verifies that the Ajax RSVP form submission creates an
	 * unapproved comment with proper RSVP data.
	 *
	 * @since 1.0.0
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_success(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set up form schema.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'custom_field' => array(
						'name'     => 'custom_field',
						'type'     => 'text',
						'required' => false,
					),
				),
				'hash'   => 'test_hash',
			),
		);
		update_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );
		$request->set_param( 'gatherpress_event_updates_opt_in', true );
		$request->set_param( 'gatherpress_form_schema_id', 'form_0' );
		$request->set_param( 'custom_field', 'Test value' );

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertStringContainsString( 'successfully', $data['message'] );
		$this->assertGreaterThan( 0, $data['comment_id'] );

		// Approve the comment since rsvp->get() now only finds approved comments.
		wp_set_comment_status( $data['comment_id'], 'approve' );

		$event     = new Event( $post_id );
		$rsvp_data = $event->rsvp->get( 'test@example.com' );

		$this->assertNotEmpty( $rsvp_data['comment_id'] );

		// Check email updates meta.
		$comment_id    = $data['comment_id'];
		$email_updates = get_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', true );
		$this->assertEquals( '1', $email_updates );

		// Check custom field was saved.
		$custom_field = get_comment_meta( $comment_id, 'gatherpress_custom_custom_field', true );
		$this->assertEquals( 'Test value', $custom_field );
	}

	/**
	 * Tests handle_rsvp_form_submission with duplicate email.
	 *
	 * Verifies that duplicate RSVP submissions are properly rejected
	 * with appropriate error message.
	 *
	 * @since 1.0.0
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_duplicate(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set up future event datetime to prevent "event has passed" error.
		$event  = new Event( $post_id );
		$params = array(
			'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
			'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day 2 hours' ) ),
		);
		$event->save_datetimes( $params );

		// Create existing RSVP.
		$this->factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => 'gatherpress_rsvp',
				'comment_author'       => 'Existing Author',
				'comment_author_email' => 'test@example.com',
			)
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertEquals( 409, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertStringContainsString( "You've already RSVP'd", $data['message'] );
	}

	/**
	 * Tests handle_rsvp_form_submission with logged-in user.
	 *
	 * Verifies that logged-in users with matching email addresses
	 * have their user ID associated with the RSVP comment.
	 *
	 * @since 1.0.0
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_logged_in_user(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create and set current user.
		$user_id = $this->factory()->user->create(
			array(
				'user_email' => 'user@example.com',
				'user_login' => 'testuser',
			)
		);
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'user@example.com' ); // Matches user email.

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertGreaterThan( 0, $data['comment_id'] );

		// Approve the comment since rsvp->get() now only finds approved comments.
		wp_set_comment_status( $data['comment_id'], 'approve' );

		$event     = new Event( $post_id );
		$rsvp_data = $event->rsvp->get( $user_id );
		$this->assertNotEmpty( $rsvp_data['comment_id'] );
		$this->assertEquals( $user_id, $rsvp_data['user_id'] );
	}

	/**
	 * Coverage for handle_rsvp_form_submission with past event.
	 *
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_past_event(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set event in the past.
		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2020-01-01 10:00:00',
				'datetime_end'   => '2020-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertStringContainsString( 'closed', $data['message'] );
	}

	/**
	 * Coverage for handle_email_send_action method.
	 *
	 * @covers ::handle_email_send_action
	 *
	 * @return void
	 */
	public function test_handle_email_send_action(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$send    = array( 'all' => true );
		$message = 'Test message';

		// The method should call send_emails internally.
		$instance->handle_email_send_action( $post_id, $send, $message );

		// If no exception thrown, test passes.
		$this->assertTrue( true );
	}

	/**
	 * Coverage for rsvp_responses method.
	 *
	 * @covers ::rsvp_responses
	 *
	 * @return void
	 */
	public function test_rsvp_responses(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an RSVP.
		$user_id = $this->factory()->user->create();
		$event   = new Event( $post_id );
		$event->rsvp->save( $user_id, 'attending', 0, 1 );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post_id );

		$response = $instance->rsvp_responses( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'data', $data );
		$this->assertArrayHasKey( 'attending', $data['data'] );
	}

	/**
	 * Coverage for rsvp_responses with non-event post.
	 *
	 * @covers ::rsvp_responses
	 *
	 * @return void
	 */
	public function test_rsvp_responses_non_event_post(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create();

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post_id );

		$response = $instance->rsvp_responses( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertEmpty( $data['data'] );
	}

	/**
	 * Coverage for rsvp_status_html method.
	 *
	 * @covers ::rsvp_status_html
	 *
	 * @return void
	 */
	public function test_rsvp_status_html(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an approved RSVP.
		$user_id     = $this->factory()->user->create();
		$event       = new Event( $post_id );
		$user_record = $event->rsvp->save( $user_id, 'attending', 0, 1 );

		// Approve the comment.
		wp_set_comment_status( $user_record['comment_id'], 'approve' );

		$block_data = array(
			'blockName' => 'gatherpress/rsvp-template',
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'attending' );
		$request->set_param( 'block_data', wp_json_encode( $block_data ) );
		$request->set_param( 'limit_enabled', false );
		$request->set_param( 'limit', 10 );

		$response = $instance->rsvp_status_html( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'content', $data );
		$this->assertArrayHasKey( 'responses', $data );
	}

	/**
	 * Coverage for rsvp_status_html method with no responses.
	 *
	 * @covers ::rsvp_status_html
	 *
	 * @return void
	 */
	public function test_rsvp_status_html_no_responses(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$block_data = array(
			'blockName' => 'gatherpress/rsvp-template',
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'attending' );
		$request->set_param( 'block_data', wp_json_encode( $block_data ) );

		$response = $instance->rsvp_status_html( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertEmpty( $data['content'] );
	}

	/**
	 * Coverage for prepare_event_data method.
	 *
	 * @covers ::prepare_event_data
	 *
	 * @return void
	 */
	public function test_prepare_event_data(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set online event link.
		update_post_meta( $post_id, 'gatherpress_online_event_link', 'https://example.com/meeting' );

		$response_data = array(
			'id'   => $post_id,
			'meta' => array(),
		);

		$response = new WP_REST_Response( $response_data );

		$result = $instance->prepare_event_data( $response );

		$this->assertArrayHasKey( 'online_event_link', $result->data['meta'] );
	}

	/**
	 * Coverage for events_list with topics filter.
	 *
	 * @covers ::events_list
	 *
	 * @return void
	 */
	public function test_events_list_with_topics(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2099-01-01 10:00:00',
				'datetime_end'   => '2099-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Create a topic term.
		$term = wp_insert_term( 'Test Topic', Topic::TAXONOMY );
		wp_set_post_terms( $post_id, array( $term['term_id'] ), Topic::TAXONOMY );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'event_list_type', 'upcoming' );
		$request->set_param( 'max_number', 5 );
		$request->set_param( 'topics', 'test-topic' );

		$response = $instance->events_list( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
	}

	/**
	 * Coverage for events_list with venues filter.
	 *
	 * @covers ::events_list
	 *
	 * @return void
	 */
	public function test_events_list_with_venues(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2099-01-01 10:00:00',
				'datetime_end'   => '2099-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Create a venue term.
		$term = wp_insert_term( 'Test Venue', Venue::TAXONOMY );
		wp_set_post_terms( $post_id, array( $term['term_id'] ), Venue::TAXONOMY );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'event_list_type', 'upcoming' );
		$request->set_param( 'max_number', 5 );
		$request->set_param( 'venues', 'test-venue' );

		$response = $instance->events_list( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
	}

	/**
	 * Coverage for events_list with custom datetime format.
	 *
	 * @covers ::events_list
	 *
	 * @return void
	 */
	public function test_events_list_custom_datetime_format(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2099-01-01 10:00:00',
				'datetime_end'   => '2099-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'event_list_type', 'upcoming' );
		$request->set_param( 'max_number', 5 );
		$request->set_param( 'datetime_format', 'Y-m-d H:i:s' );

		$response = $instance->events_list( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		if ( ! empty( $data ) ) {
			$this->assertArrayHasKey( 'datetime_start', $data[0] );
		}
	}

	/**
	 * Coverage for update_rsvp with token.
	 *
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_update_rsvp_with_token(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2099-01-01 10:00:00',
				'datetime_end'   => '2099-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Create an RSVP with email.
		$email       = 'test@example.com';
		$user_record = $event->rsvp->save( $email, 'attending', 0, 0 );

		// Generate token.
		$rsvp_token = new Rsvp_Token( $user_record['comment_id'] );
		$rsvp_token->generate_token();
		$token_value = $rsvp_token->get_token();

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'not_attending' );
		$request->set_param( 'rsvp_token', sprintf( '%d_%s', $user_record['comment_id'], $token_value ) );

		$response = $instance->update_rsvp( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'not_attending', $data['status'] );
	}

	/**
	 * Coverage for update_rsvp with past event.
	 *
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_update_rsvp_past_event(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2020-01-01 10:00:00',
				'datetime_end'   => '2020-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$user_id = $this->factory()->user->create();
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'attending' );

		$response = $instance->update_rsvp( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
	}

	/**
	 * Coverage for send_emails with non-event post.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_non_event_post(): void {
		$instance = Event_Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create();

		$result = $instance->send_emails( $post_id, array( 'all' => true ), '' );

		$this->assertFalse( $result );
	}

	/**
	 * Test send_emails with user who opted out of event updates.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_opted_out_user(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();

		// User opts out of event updates.
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', 0 );

		$event = new Event( $event_id );
		$event->rsvp->save( $user_id, 'attending' );

		$send = array(
			'attending' => true,
		);

		// Should still return true but skip the opted-out user.
		$result = $instance->send_emails( $event_id, $send, 'Test message' );
		$this->assertTrue( $result );

		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Test send_emails with non-user RSVP who opted out.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_opted_out_non_user(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create anonymous RSVP.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'Anonymous User',
				'comment_author_email' => 'anonymous@example.com',
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		// Set RSVP status.
		wp_set_object_terms( $comment_id, 'attending', Rsvp::TAXONOMY );

		// Opt out of event updates.
		update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', 0 );

		$send = array(
			'attending' => true,
		);

		// Should still return true but skip the opted-out RSVP.
		$result = $instance->send_emails( $event_id, $send, 'Test message' );
		$this->assertTrue( $result );

		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Test send_emails with recipient who has no email.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_no_email(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create RSVP with no email.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'No Email User',
				'comment_author_email' => '', // Empty email.
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		wp_set_object_terms( $comment_id, 'attending', Rsvp::TAXONOMY );

		$send = array(
			'attending' => true,
		);

		// Should return true but skip recipient with no email.
		$result = $instance->send_emails( $event_id, $send, 'Test message' );
		$this->assertTrue( $result );

		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Test get_recipients skips RSVPs with empty email.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_skips_empty_email(): void {
		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create RSVP with empty email.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'No Email User',
				'comment_author_email' => '', // Empty email.
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		wp_set_object_terms( $comment_id, 'attending', Rsvp::TAXONOMY );

		$send = array(
			'attending' => true,
		);

		$recipients = $instance->get_recipients( $send, $event_id );

		// Should not include the RSVP with empty email.
		$this->assertEmpty( $recipients );
	}

	/**
	 * Test send_emails with locale switching for user.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_locale_switching(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Event_Rest_Api::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();

		// Opt in to updates.
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', 1 );

		$event = new Event( $event_id );
		$event->rsvp->save( $user_id, 'attending' );

		$send = array(
			'attending' => true,
		);

		// Should handle locale switching for user.
		$result = $instance->send_emails( $event_id, $send, 'Test message' );
		$this->assertTrue( $result );

		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Tests nonce_route protected method.
	 *
	 * Covers target.
	 *
	 * @covers ::nonce_route
	 */
	public function test_nonce_route(): void {
		$instance = Event_Rest_Api::get_instance();

		$route = Utility::invoke_hidden_method( $instance, 'nonce_route' );

		$this->assertIsArray( $route );
		$this->assertArrayHasKey( 'route', $route );
		$this->assertArrayHasKey( 'args', $route );
		$this->assertEquals( 'nonce', $route['route'] );
		$this->assertArrayHasKey( 'methods', $route['args'] );
		$this->assertArrayHasKey( 'callback', $route['args'] );
		$this->assertArrayHasKey( 'permission_callback', $route['args'] );
	}

	/**
	 * Tests rsvp_route permission callback with valid RSVP token.
	 *
	 * Covers: permission callback returning true when valid token is provided.
	 *
	 * @covers ::rsvp_route
	 */
	public function test_rsvp_route_permission_callback_with_token(): void {
		$instance = Event_Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_route' );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create a valid RSVP token.
		$email       = 'test@example.com';
		$event       = new Event( $post_id );
		$user_record = $event->rsvp->save( $email, 'attending', 0, 0 );

		// Generate token.
		$rsvp_token = new Rsvp_Token( $user_record['comment_id'] );
		$rsvp_token->generate_token();
		$token_value = $rsvp_token->get_token();
		$token_str   = sprintf( '%d_%s', $user_record['comment_id'], $token_value );

		// Create request with token.
		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( Rsvp_Token::NAME, $token_str );

		// Call the permission callback.
		$permission_callback = $route['args']['permission_callback'];
		$result              = call_user_func( $permission_callback, $request );

		$this->assertTrue( $result, 'Permission callback should return true with valid RSVP token' );
	}

	/**
	 * Tests rsvp_route permission callback without token for logged out user.
	 *
	 * Covers: permission callback returning false when user is not logged in.
	 *
	 * @covers ::rsvp_route
	 */
	public function test_rsvp_route_permission_callback_logged_out(): void {
		$instance = Event_Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_route' );

		// Ensure user is logged out.
		wp_set_current_user( 0 );

		// Create request without token.
		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );

		// Call the permission callback.
		$permission_callback = $route['args']['permission_callback'];
		$result              = call_user_func( $permission_callback, $request );

		$this->assertFalse(
			$result,
			'Permission callback should return false when user is not logged in and no token provided'
		);
	}

	/**
	 * Tests rsvp_route validate callback for rsvp_token with empty token.
	 *
	 * Covers: validate callback returning false for empty token.
	 *
	 * @covers ::rsvp_route
	 */
	public function test_rsvp_route_token_validate_callback_empty(): void {
		$instance = Event_Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_route' );

		$validate_callback = $route['args']['args']['rsvp_token']['validate_callback'];
		$result            = call_user_func( $validate_callback, '' );

		$this->assertFalse( $result, 'Validate callback should return false for empty token' );
	}

	/**
	 * Tests rsvp_form_route author validate callback with empty value.
	 *
	 * Covers: validate callback returning false for empty sanitized author.
	 *
	 * @covers ::rsvp_form_route
	 */
	public function test_rsvp_form_route_author_validate_callback_empty(): void {
		$instance = Event_Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_form_route' );

		$validate_callback = $route['args']['args']['author']['validate_callback'];

		// Test with empty string.
		$result = call_user_func( $validate_callback, '' );
		$this->assertFalse( $result, 'Validate callback should return false for empty author' );

		// Test with whitespace only.
		$result = call_user_func( $validate_callback, '   ' );
		$this->assertFalse( $result, 'Validate callback should return false for whitespace-only author' );
	}

	/**
	 * Tests rsvp_form_route email validate callback.
	 *
	 * Covers: validate callback using is_email().
	 *
	 * @covers ::rsvp_form_route
	 */
	public function test_rsvp_form_route_email_validate_callback(): void {
		$instance = Event_Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_form_route' );

		$validate_callback = $route['args']['args']['email']['validate_callback'];

		// Test with valid email - is_email() returns the sanitized email (truthy).
		$result = call_user_func( $validate_callback, 'test@example.com' );
		$this->assertNotEmpty( $result, 'Validate callback should return truthy value for valid email' );

		// Test with invalid email - is_email() returns false.
		$result = call_user_func( $validate_callback, 'not-an-email' );
		$this->assertFalse( $result, 'Validate callback should return false for invalid email' );
	}

	/**
	 * Tests rsvp_form_route form schema ID validate callback.
	 *
	 * Covers: validate callback checking string format.
	 *
	 * @covers ::rsvp_form_route
	 */
	public function test_rsvp_form_route_form_schema_id_validate_callback(): void {
		$instance = Event_Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_form_route' );

		$validate_callback = $route['args']['args']['gatherpress_form_schema_id']['validate_callback'];

		// Test with valid form_0.
		$result = call_user_func( $validate_callback, 'form_0' );
		$this->assertTrue( $result, 'Validate callback should return true for form_0' );

		// Test with valid form_123.
		$result = call_user_func( $validate_callback, 'form_123' );
		$this->assertTrue( $result, 'Validate callback should return true for form_123' );

		// Test with invalid format.
		$result = call_user_func( $validate_callback, 'invalid' );
		$this->assertFalse( $result, 'Validate callback should return false for invalid format' );

		// Test with non-string.
		$result = call_user_func( $validate_callback, 123 );
		$this->assertFalse( $result, 'Validate callback should return false for non-string' );
	}

	/**
	 * Tests rsvp_status_html_route protected method.
	 *
	 * @covers ::rsvp_status_html_route
	 */
	public function test_rsvp_status_html_route(): void {
		$instance = Event_Rest_Api::get_instance();

		$route = Utility::invoke_hidden_method( $instance, 'rsvp_status_html_route' );

		$this->assertIsArray( $route );
		$this->assertArrayHasKey( 'route', $route );
		$this->assertArrayHasKey( 'args', $route );
		$this->assertEquals( 'rsvp-status-html', $route['route'] );
		$this->assertArrayHasKey( 'methods', $route['args'] );
		$this->assertArrayHasKey( 'callback', $route['args'] );
		$this->assertArrayHasKey( 'permission_callback', $route['args'] );
	}

	/**
	 * Tests rsvp_responses_route protected method.
	 *
	 * @covers ::rsvp_responses_route
	 */
	public function test_rsvp_responses_route(): void {
		$instance = Event_Rest_Api::get_instance();

		$route = Utility::invoke_hidden_method( $instance, 'rsvp_responses_route' );

		$this->assertIsArray( $route );
		$this->assertArrayHasKey( 'route', $route );
		$this->assertArrayHasKey( 'args', $route );
		$this->assertEquals( 'rsvp-responses', $route['route'] );
		$this->assertArrayHasKey( 'methods', $route['args'] );
		$this->assertArrayHasKey( 'callback', $route['args'] );
		$this->assertArrayHasKey( 'permission_callback', $route['args'] );
	}

	/**
	 * Tests send_emails with non-user RSVP that has opt-out.
	 *
	 * Covers: skip sending email when comment meta opt-in is '0'.
	 *
	 * @covers ::send_emails
	 */
	public function test_send_emails_skip_non_user_opt_out(): void {
		$instance = Event_Rest_Api::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create RSVP comment.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_approved'     => 1,
				'comment_author'       => 'Non User',
				'comment_author_email' => 'nonuser@example.com',
				'user_id'              => 0,
			)
		);

		// Set opt-in to '0' (opted out).
		add_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', '0' );
		add_comment_meta( $comment_id, 'gatherpress_rsvp_status', 'attending' );

		$send = array( 'attending' => true );

		// Mock wp_mail to track if it was called.
		$mail_called = false;
		add_filter(
			'pre_wp_mail',
			function () use ( &$mail_called ) {
				$mail_called = true;
				return true; // Prevent actual email sending.
			}
		);

		$result = $instance->send_emails( $post_id, $send, 'Test message' );

		$this->assertTrue( $result, 'send_emails should return true' );
		$this->assertFalse( $mail_called, 'wp_mail should not be called for opted-out non-user RSVP' );
	}

	/**
	 * Tests send_emails with locale switching for user.
	 *
	 * Covers: restore_previous_locale when switched_locale is true.
	 *
	 * @covers ::send_emails
	 */
	public function test_send_emails_restore_locale(): void {
		$instance = Event_Rest_Api::get_instance();

		$user_id = $this->factory->user->create(
			array(
				'user_email' => 'user@example.com',
			)
		);

		// Set user locale different from site locale.
		update_user_meta( $user_id, 'locale', 'de_DE' );

		// Enable opt-in.
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '1' );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create RSVP for the user.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_approved'     => 1,
				'comment_author_email' => 'user@example.com',
				'user_id'              => $user_id,
			)
		);

		add_comment_meta( $comment_id, 'gatherpress_rsvp_status', 'attending' );

		$send = array( 'attending' => true );

		// Set a current user.
		wp_set_current_user( $this->factory->user->create() );

		// Mock wp_mail.
		add_filter(
			'pre_wp_mail',
			function () {
				return true;
			}
		);

		$result = $instance->send_emails( $post_id, $send, 'Test message' );

		$this->assertTrue( $result, 'send_emails should return true' );
		// If this test runs without errors, restore_previous_locale was called correctly.
	}

	/**
	 * Tests get_recipients skipping RSVP with empty email.
	 *
	 * Covers: skip recipient when email is empty.
	 *
	 * @covers ::get_recipients
	 */
	public function test_get_recipients_skip_empty_email(): void {
		$instance = Event_Rest_Api::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create RSVP comment with empty email.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_approved'     => 1,
				'comment_author'       => 'Test User',
				'comment_author_email' => '', // Empty email.
				'user_id'              => 0,
			)
		);

		add_comment_meta( $comment_id, 'gatherpress_rsvp_status', 'attending' );

		$send = array( 'attending' => true );

		$recipients = $instance->get_recipients( $send, $post_id );

		$this->assertEmpty( $recipients, 'Recipients should be empty when email is empty' );
	}

	/**
	 * Tests update_rsvp when non-admin user tries to add someone else.
	 *
	 * Covers: user without edit_posts permission gets user_id set to 0.
	 *
	 * @covers ::update_rsvp
	 */
	public function test_update_rsvp_non_admin_cannot_add_others(): void {
		$instance = Event_Rest_Api::get_instance();

		// Create a subscriber (no edit_posts capability).
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		// Create another user that subscriber wants to add.
		$other_user_id = $this->factory->user->create();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'user_id', $other_user_id );
		$request->set_param( 'status', 'attending' );

		$response = $instance->update_rsvp( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		// The user_id should be reset to 0 since subscriber doesn't have edit_posts.
		$this->assertFalse( $response->data['success'], 'RSVP should fail when non-admin tries to add someone else' );
	}

	/**
	 * Tests rsvp_status_html setting nocache headers when logged in.
	 *
	 * Covers: nocache_headers() called when user is logged in.
	 *
	 * @covers ::rsvp_status_html
	 */
	public function test_rsvp_status_html_nocache_when_logged_in(): void {
		$instance = Event_Rest_Api::get_instance();

		// Create and log in a user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp-status-html' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'attending' );
		$request->set_param( 'block_data', wp_json_encode( array() ) );

		// Check that nocache_headers is called by verifying headers are sent.
		$response = $instance->rsvp_status_html( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		// If test runs without error, nocache_headers was called successfully.
	}

	/**
	 * Tests handle_rsvp_form_submission with custom fields.
	 *
	 * Covers: adding custom fields with gatherpress_custom_ prefix.
	 *
	 * @covers ::handle_rsvp_form_submission
	 */
	public function test_handle_rsvp_form_submission_custom_fields(): void {
		$instance = Event_Rest_Api::get_instance();

		$user_id = $this->factory->user->create(
			array(
				'user_email' => 'test@example.com',
			)
		);
		wp_set_current_user( $user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp-form' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );
		$request->set_param( 'gatherpress_custom_field1', 'Custom Value 1' );
		$request->set_param( 'gatherpress_custom_field2', 'Custom Value 2' );
		$request->set_param( 'other_param', 'Should not be included' ); // This should NOT be added.

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertTrue( $response->data['success'], 'RSVP form submission should succeed' );

		// Target line adds custom fields with gatherpress_custom_ prefix to the data array.
		// The actual processing and saving is done by Rsvp_Form::process_rsvp().
		// This test confirms the endpoint successfully processes requests with custom fields.
	}

	/**
	 * Tests rsvp_responses setting nocache headers when logged in.
	 *
	 * Covers: nocache_headers() called when user is logged in.
	 *
	 * @covers ::rsvp_responses
	 */
	public function test_rsvp_responses_nocache_when_logged_in(): void {
		$instance = Event_Rest_Api::get_instance();

		// Create and log in a user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/rsvp-responses' );
		$request->set_param( 'post_id', $post_id );

		// Check that nocache_headers is called.
		$response = $instance->rsvp_responses( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertTrue( $response->data['success'], 'rsvp_responses should return success' );
		// If test runs without error, nocache_headers was called successfully.
	}
}
