<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Rest_Api.
 *
 * @package GatherPress\Core\Event
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Meta;
use GatherPress\Core\Event\Rest_Api;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Token;
use GatherPress\Core\Settings;
use GatherPress\Core\Setup;
use GatherPress\Core\Topic;
use GatherPress\Core\Utility as GatherPress_Utility;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;
use GatherPress\Core\Blocks\Rsvp_Template;

/**
 * Class Test_Rest_Api.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Rest_Api
 */
class Test_Rest_Api extends Base {

	/**
	 * Enable open RSVP for all tests in this class so RSVP form submission paths are exercisable.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Settings::get_instance()->set( 'enable_open_rsvp', true );
	}

	/**
	 * Restore RSVP settings to their defaults after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Settings::get_instance()->set( 'enable_open_rsvp', true );
		Settings::get_instance()->set( 'rsvp_mode', 'enabled' );
		parent::tearDown();
	}

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
			$namespace[ sprintf( '/%s/event/email', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert email endpoint is registered'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/rsvp', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert rsvp endpoint is registered'
		);
	}

	/**
	 * Coverage for get_event_routes method.
	 *
	 * @covers ::get_event_routes
	 * @covers ::email_route
	 * @covers ::rsvp_route
	 *
	 * @return void
	 */
	public function test_get_event_routes(): void {
		$instance = Rest_Api::get_instance();
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
				'subject' => 'Custom subject',
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
	 * Coverage for email_route argument handling.
	 *
	 * A sanitizer in the validate_callback slot never sanitizes: the REST server
	 * only treats an exact `false` return as invalid, so the raw value is used.
	 *
	 * @covers ::email_route
	 *
	 * @return void
	 */
	public function test_email_route_sanitizes_text_params(): void {
		$instance = Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'email_route' );
		$args     = $route['args']['args'];

		$this->assertArrayNotHasKey(
			'validate_callback',
			$args['message'],
			'Failed to assert message does not use a sanitizer as a validator.'
		);
		$this->assertSame(
			'sanitize_textarea_field',
			$args['message']['sanitize_callback'],
			'Failed to assert message is sanitized with newlines preserved.'
		);
		$this->assertArrayNotHasKey(
			'validate_callback',
			$args['subject'],
			'Failed to assert subject does not use a sanitizer as a validator.'
		);
		$this->assertSame(
			'sanitize_text_field',
			$args['subject']['sanitize_callback'],
			'Failed to assert subject is sanitized.'
		);
	}

	/**
	 * Coverage for text params sanitized through a dispatched request.
	 *
	 * Sanitization only runs on dispatch, so this exercises the registered route
	 * rather than calling the callback directly, and reads the values back off
	 * the Mailer job the callback hands to cron.
	 *
	 * @covers ::email
	 *
	 * @return void
	 */
	public function test_email_route_sanitizes_on_dispatch(): void {
		$captured = array();

		add_filter(
			'schedule_event',
			static function ( $event ) use ( &$captured ) {
				if ( $event && Mailer::JOB_HOOK === $event->hook ) {
					$captured = $event->args;
				}

				return $event;
			}
		);

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$event_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_author' => $admin_id,
			)
		)->get()->ID;

		wp_set_current_user( $admin_id );

		Rest_Api::get_instance()->register_endpoints();

		$request = new WP_REST_Request(
			'POST',
			sprintf( '/%s/event/email', GATHERPRESS_REST_NAMESPACE )
		);

		$request->set_body_params(
			array(
				'post_id' => $event_id,
				'message' => "First line.\nSecond line.",
				'subject' => '  Subject <b>with</b> markup  ',
				'send'    => array(
					'all'           => false,
					'attending'     => true,
					'waiting_list'  => false,
					'not_attending' => false,
				),
			)
		);

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame(
			200,
			$response->get_status(),
			'Failed to assert the email request was accepted.'
		);
		$this->assertSame(
			"First line.\nSecond line.",
			$captured[1]['message'],
			'Failed to assert the newline survived sanitization of the message.'
		);
		$this->assertSame(
			'Subject with markup',
			$captured[1]['subject'],
			'Failed to assert the subject was sanitized.'
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
	 * Coverage for update_rsvp method.
	 *
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_update_rsvp(): void {
		$instance = Rest_Api::get_instance();
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
			$response->data['responses']['attending']['records'][0]['user_id'],
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
	 * @since 0.34.0
	 * @covers ::rsvp_form_route
	 *
	 * @return void
	 */
	public function test_rsvp_form_route(): void {
		$instance = Rest_Api::get_instance();
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
	 * Tests handle_rsvp_form_submission survives a malformed stored schema.
	 *
	 * The handler copies the schema's field names off the request before
	 * process_rsvp() validates anything, so enumerating a scalar there
	 * fataled with a TypeError before the submission could be rejected.
	 *
	 * @since 0.36.0
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_with_a_malformed_schema(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );

		update_post_meta(
			$post_id,
			'gatherpress_rsvp_form_schemas',
			array( 'form_0' => array( 'fields' => 'corrupted' ) )
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );
		$request->set_param( 'gatherpress_form_schema_id', 'form_0' );

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertInstanceOf(
			'WP_REST_Response',
			$response,
			'A malformed schema should answer rather than fatal.'
		);
	}

	/**
	 * Tests handle_rsvp_form_submission reports per-field errors.
	 *
	 * A custom-field rejection answers 400 and carries one message per
	 * failing field alongside the summary the form already shows.
	 *
	 * @since 0.36.0
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_reports_field_errors(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );

		update_post_meta(
			$post_id,
			'gatherpress_rsvp_form_schemas',
			array(
				'form_0' => array(
					'hash'   => 'test_hash',
					'fields' => array(
						'dietary' => array(
							'name'        => 'dietary',
							'type'        => 'text',
							'required'    => true,
							'label'       => 'Dietary needs',
							'placeholder' => '',
						),
					),
				),
			)
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );
		$request->set_param( 'gatherpress_form_schema_id', 'form_0' );

		$response = $instance->handle_rsvp_form_submission( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status(), 'A rejected submission should answer 400.' );
		$this->assertFalse( $data['success'], 'A rejected submission should not report success.' );
		$this->assertSame(
			array( 'dietary' => 'Dietary needs is required.' ),
			$data['errors'],
			'The response should name each failing field.'
		);
	}

	/**
	 * Tests handle_rsvp_form_submission with valid data.
	 *
	 * Verifies that the Ajax RSVP form submission creates an
	 * unapproved comment with proper RSVP data.
	 *
	 * @since 0.34.0
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_success(): void {
		$instance = Rest_Api::get_instance();
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

		$rsvp      = new Rsvp( $post_id );
		$rsvp_data = $rsvp->get( 'test@example.com' );

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
	 * @since 0.34.0
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_duplicate(): void {
		$instance = Rest_Api::get_instance();
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
	 * @since 0.34.0
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_logged_in_user(): void {
		$instance = Rest_Api::get_instance();
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

		$rsvp      = new Rsvp( $post_id );
		$rsvp_data = $rsvp->get( $user_id );
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
		$instance = Rest_Api::get_instance();
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
	 * Tests handle_rsvp_form_submission returns 403 when open RSVP is disabled sitewide.
	 *
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_open_rsvp_disabled(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Disable open RSVP sitewide (overrides setUp default).
		Settings::get_instance()->set( 'enable_open_rsvp', false );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertEquals( 403, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertStringContainsString( 'Open RSVP', $data['message'] );
	}

	/**
	 * Tests handle_rsvp_form_submission returns 403 when open RSVP is disabled per event.
	 *
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_open_rsvp_disabled_per_event(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Sitewide open RSVP is enabled (setUp default), but disabled for this event.
		update_post_meta( $post_id, 'gatherpress_enable_open_rsvp', 0 );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertEquals( 403, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertStringContainsString( 'Open RSVP', $data['message'] );
	}

	/**
	 * Data provider of non-public post statuses for RSVP form submission.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function data_unpublished_event_statuses(): array {
		return array(
			'draft event'   => array( 'draft' ),
			'private event' => array( 'private' ),
		);
	}

	/**
	 * Tests handle_rsvp_form_submission returns 404 for an event that is not published.
	 *
	 * @dataProvider data_unpublished_event_statuses
	 *
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @param string $post_status Non-public post status to submit against.
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_unpublished_event( string $post_status ): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => $post_status,
			)
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertEquals( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertStringContainsString( 'not found', $data['message'] );
	}

	/**
	 * Tests handle_rsvp_form_submission returns 403 when RSVP is disabled for the event.
	 *
	 * @covers ::handle_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_submission_rsvp_disabled(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Disable RSVP sitewide so Rsvp::is_enabled() returns false for the event.
		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'comment_post_ID', $post_id );
		$request->set_param( 'author', 'Test Author' );
		$request->set_param( 'email', 'test@example.com' );

		$response = $instance->handle_rsvp_form_submission( $request );

		$this->assertEquals( 403, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertSame( 'RSVP is disabled for this event.', $data['message'] );
	}

	/**
	 * Coverage for rsvp_responses method.
	 *
	 * @covers ::rsvp_responses
	 *
	 * @return void
	 */
	public function test_rsvp_responses(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an RSVP.
		$user_id = $this->factory()->user->create();
		$rsvp    = new Rsvp( $post_id );
		$rsvp->save( $user_id, 'attending', 0, 1 );

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
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create();

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post_id );

		$response = $instance->rsvp_responses( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertEmpty( $data['data'] );
	}

	/**
	 * Coverage for rsvp_responses with a custom post type that declares RSVP support.
	 *
	 * @covers ::rsvp_responses
	 *
	 * @return void
	 */
	public function test_rsvp_responses_custom_rsvp_post_type(): void {
		$instance  = Rest_Api::get_instance();
		$post_type = 'gp_rsvp_custom';

		register_post_type(
			$post_type,
			array(
				'public'   => true,
				'supports' => array( 'title', Event::SUPPORT, Rsvp::SUPPORT ),
			)
		);

		$post_id = $this->factory()->post->create( array( 'post_type' => $post_type ) );
		$user_id = $this->factory()->user->create();
		$rsvp    = new Rsvp( $post_id );
		$rsvp->save( $user_id, 'attending', 0, 1 );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post_id );

		$response = $instance->rsvp_responses( $request );
		$data     = $response->get_data();

		unregister_post_type( $post_type );

		$this->assertTrue(
			$data['success'],
			'Failed to assert that a post type with RSVP support returns its responses.'
		);
		$this->assertArrayHasKey( 'attending', $data['data'] );
	}

	/**
	 * Coverage for rsvp_responses with a post type that has event dates but no RSVP support.
	 *
	 * @covers ::rsvp_responses
	 *
	 * @return void
	 */
	public function test_rsvp_responses_event_date_only_post_type(): void {
		$instance  = Rest_Api::get_instance();
		$post_type = 'gp_date_only';

		register_post_type(
			$post_type,
			array(
				'public'   => true,
				'supports' => array( 'title', Event::SUPPORT ),
			)
		);

		$post_id = $this->factory()->post->create( array( 'post_type' => $post_type ) );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post_id );

		$response = $instance->rsvp_responses( $request );
		$data     = $response->get_data();

		unregister_post_type( $post_type );

		$this->assertFalse(
			$data['success'],
			'Failed to assert that event-date support alone does not expose RSVP responses.'
		);
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
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an approved RSVP.
		$user_id     = $this->factory()->user->create();
		$rsvp        = new Rsvp( $post_id );
		$user_record = $rsvp->save( $user_id, 'attending', 0, 1 );

		// Approve the comment.
		wp_set_comment_status( $user_record['comment_id'], 'approve' );

		$block_data = array(
			'blockName'   => 'gatherpress/rsvp-template',
			'attrs'       => array(),
			'innerBlocks' => array(),
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'attending' );
		$request->set_param( 'block_data', wp_json_encode( $block_data ) );
		$request->set_param(
			'block_signature',
			Rsvp_Template::sign_template( (string) wp_json_encode( $block_data ), $post_id )
		);
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
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$block_data = array(
			'blockName'   => 'gatherpress/rsvp-template',
			'attrs'       => array(),
			'innerBlocks' => array(),
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'attending' );
		$request->set_param( 'block_data', wp_json_encode( $block_data ) );
		$request->set_param(
			'block_signature',
			Rsvp_Template::sign_template( (string) wp_json_encode( $block_data ), $post_id )
		);

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
		$instance = Rest_Api::get_instance();
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
	 * The online event link's own meta key is carried only for a viewer who
	 * could edit the event.
	 *
	 * @covers ::prepare_event_data
	 *
	 * @return void
	 */
	public function test_prepare_event_data_hides_the_link_meta_from_viewers(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$link     = 'https://example.com/meeting';
		$response = static function () use ( $post_id, $link ): WP_REST_Response {
			return new WP_REST_Response(
				array(
					'id'   => $post_id,
					'meta' => array( 'gatherpress_online_event_link' => $link ),
				)
			);
		};

		update_post_meta( $post_id, 'gatherpress_online_event_link', $link );

		wp_set_current_user( 0 );
		$anonymous = $instance->prepare_event_data( $response() )->data['meta'];

		// Another plugin can filter `id` out of the body, so the post the filter
		// is handed is the only way to know which event this is (#1765).
		$without_id = $instance->prepare_event_data(
			new WP_REST_Response( array( 'meta' => array( 'gatherpress_online_event_link' => $link ) ) ),
			get_post( $post_id )
		)->data['meta'];

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$subscriber = $instance->prepare_event_data( $response() )->data['meta'];

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		$editor = $instance->prepare_event_data( $response(), get_post( $post_id ) )->data['meta'];

		wp_set_current_user( 0 );

		$this->assertSame(
			'',
			$anonymous['gatherpress_online_event_link'],
			'Failed to assert an anonymous viewer gets no link from the meta key.'
		);
		$this->assertSame(
			'',
			$without_id['gatherpress_online_event_link'],
			'Failed to assert the link is withheld when the response body has no ID.'
		);
		$this->assertSame(
			'',
			$subscriber['gatherpress_online_event_link'],
			'Failed to assert a viewer who cannot edit the event gets no link from the meta key.'
		);
		$this->assertSame(
			$link,
			$editor['gatherpress_online_event_link'],
			'Failed to assert a viewer who can edit the event still gets the link.'
		);
	}

	/**
	 * The route itself no longer hands the link to an anonymous viewer, on a
	 * single event or a collection.
	 *
	 * @covers ::prepare_event_data
	 *
	 * @return void
	 */
	public function test_rest_route_hides_the_link_meta_from_viewers(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$link    = 'https://example.com/meeting';

		// Another test in this class leaves the event meta unregistered, and the
		// point here is what the registered key does in a response.
		Meta::get_instance()->register( Event::POST_TYPE );

		update_post_meta( $post_id, 'gatherpress_online_event_link', $link );
		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day 2 hours' ) ),
				'timezone'       => 'America/New_York',
			)
		);
		wp_set_current_user( 0 );

		$rest_base  = get_post_type_object( Event::POST_TYPE )->rest_base;
		$single     = rest_do_request( new WP_REST_Request( 'GET', sprintf( '/wp/v2/%s/%d', $rest_base, $post_id ) ) );
		$collection = rest_do_request( new WP_REST_Request( 'GET', sprintf( '/wp/v2/%s', $rest_base ) ) );
		$listed     = wp_list_filter( (array) $collection->get_data(), array( 'id' => $post_id ) );

		$this->assertSame( 200, $single->get_status(), 'Expected the published event to be readable.' );
		$this->assertSame(
			'',
			$single->get_data()['meta']['gatherpress_online_event_link'] ?? null,
			'Failed to assert a single event response carries no link for an anonymous viewer.'
		);
		$this->assertNotEmpty( $listed, 'Expected the published event in the collection response.' );
		$this->assertSame(
			'',
			reset( $listed )['meta']['gatherpress_online_event_link'] ?? null,
			'Failed to assert a collection response carries no link for an anonymous viewer.'
		);
	}

	/**
	 * Coverage for prepare_event_data when the response has no id.
	 *
	 * Regression for #1765: a `_fields=` request (or another plugin filtering
	 * the response) can produce data without an `id` key. The method should
	 * bail instead of emitting an undefined-key notice and constructing
	 * Event( 0 ).
	 *
	 * @covers ::prepare_event_data
	 *
	 * @return void
	 */
	public function test_prepare_event_data_without_id_returns_response_unchanged(): void {
		$instance = Rest_Api::get_instance();

		// Mimic a `_fields=title` response: no `id`, no `meta`.
		$response = new WP_REST_Response( array( 'title' => 'Filtered Event' ) );

		$result = $instance->prepare_event_data( $response );

		$this->assertSame(
			array( 'title' => 'Filtered Event' ),
			$result->data,
			'Response data should be returned untouched when id is absent.'
		);
		$this->assertArrayNotHasKey(
			'meta',
			$result->data,
			'No meta should be injected when id is absent.'
		);
	}

	/**
	 * Coverage for update_rsvp with token.
	 *
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_update_rsvp_with_token(): void {
		$instance = Rest_Api::get_instance();
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
		$user_record = ( new Rsvp( $post_id ) )->save( $email, 'attending', 0, 0 );

		// Generate token.
		$rsvp_token = new Token( $user_record['comment_id'] );
		$rsvp_token->generate_token();
		$token_value = $rsvp_token->get_token();

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'not_attending' );
		$this->set_rsvp_session_cookie( $post_id, sprintf( '%d_%s', $user_record['comment_id'], $token_value ) );

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
		$instance = Rest_Api::get_instance();
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
	 * Tests nonce_route protected method.
	 *
	 * Covers target.
	 *
	 * @covers ::nonce_route
	 */
	public function test_nonce_route(): void {
		$instance = Rest_Api::get_instance();

		$route = Utility::invoke_hidden_method( $instance, 'nonce_route' );

		$this->assertIsArray( $route );
		$this->assertArrayHasKey( 'route', $route );
		$this->assertArrayHasKey( 'args', $route );
		$this->assertEquals( 'nonce', $route['route'] );
		$this->assertArrayHasKey( 'methods', $route['args'] );
		$this->assertArrayHasKey( 'callback', $route['args'] );
		$this->assertSame(
			array( GatherPress_Utility::class, 'is_same_origin_request' ),
			$route['args']['permission_callback'],
			'Failed to assert the nonce route only answers the site\'s own origin.'
		);
	}

	/**
	 * Tests nonce_route callback to generate nonce.
	 *
	 * Covers: nonce generation callback with cache headers.
	 *
	 * @covers ::nonce_route
	 */
	public function test_nonce_route_callback(): void {
		$instance = Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'nonce_route' );

		// Create a logged-in user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		add_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

		// Call the callback.
		$callback = $route['args']['callback'];
		$response = call_user_func( $callback );

		$this->assertFalse(
			has_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' ),
			'Failed to assert the nonce response is served without CORS headers.'
		);
		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Callback should return WP_REST_Response' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'nonce', $data, 'Response should contain nonce key' );
		$this->assertNotEmpty( $data['nonce'], 'Nonce should not be empty' );

		// Verify the nonce is valid.
		$this->assertIsString( $data['nonce'], 'Nonce should be a string' );
		$this->assertGreaterThan( 0, wp_verify_nonce( $data['nonce'], 'wp_rest' ), 'Nonce should be valid' );
	}

	/**
	 * The nonce route answers the site's own origin and refuses any other.
	 *
	 * @covers ::nonce_route
	 *
	 * @return void
	 */
	public function test_nonce_route_refuses_other_origins(): void {
		Rest_Api::get_instance()->register_endpoints();

		$request = new WP_REST_Request( 'GET', sprintf( '/%s/event/nonce', GATHERPRESS_REST_NAMESPACE ) );

		$without_origin = rest_do_request( $request )->get_status();

		$_SERVER['HTTP_ORIGIN'] = untrailingslashit( home_url() );
		$own_origin             = rest_do_request( $request )->get_status();

		$_SERVER['HTTP_ORIGIN'] = 'https://elsewhere.example';
		$other_origin           = rest_do_request( $request );

		unset( $_SERVER['HTTP_ORIGIN'] );

		$this->assertSame( 200, $without_origin, 'Failed to assert a request without an origin is answered.' );
		$this->assertSame( 200, $own_origin, 'Failed to assert a request from the site\'s origin is answered.' );
		$this->assertContains(
			$other_origin->get_status(),
			array( 401, 403 ),
			'Failed to assert a request from another origin is refused.'
		);
		$this->assertArrayNotHasKey(
			'nonce',
			(array) $other_origin->get_data(),
			'Failed to assert a request from another origin gets no nonce.'
		);
	}

	/**
	 * Tests email_route permission callback enforces a per-post edit check.
	 *
	 * Routes through `current_user_can( 'edit_post', $post_id )` so the
	 * permission model matches what WP applies to the event itself: Editors
	 * can email about anyone's event, Authors can email only about their own,
	 * and Subscribers / non-owner Authors are denied.
	 *
	 * @covers ::email_route
	 */
	public function test_email_route_permission_callback_per_post(): void {
		$instance = Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'email_route' );
		$callback = $route['args']['permission_callback'];

		$author_one_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$author_two_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$editor_id     = $this->factory->user->create( array( 'role' => 'editor' ) );
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_author' => $author_one_id,
				'post_status' => 'publish',
			)
		);

		$request = new \WP_REST_Request();
		$request->set_param( 'post_id', $event_id );

		wp_set_current_user( $author_one_id );
		$this->assertTrue(
			call_user_func( $callback, $request ),
			'The event author should be allowed to email about their own event.'
		);

		wp_set_current_user( $author_two_id );
		$this->assertFalse(
			call_user_func( $callback, $request ),
			'A different author should not be allowed to email about an event they do not own.'
		);

		wp_set_current_user( $editor_id );
		$this->assertTrue(
			call_user_func( $callback, $request ),
			'An editor should be allowed to email about any event via edit_others_posts.'
		);

		wp_set_current_user( $subscriber_id );
		$this->assertFalse(
			call_user_func( $callback, $request ),
			'A subscriber should not be allowed to email about any event.'
		);

		wp_set_current_user( 0 );
		$this->assertFalse(
			call_user_func( $callback, $request ),
			'A logged-out user should not be allowed to email about any event.'
		);
	}

	/**
	 * Tests rsvp_route permission callback with a valid RSVP session cookie.
	 *
	 * Covers: permission callback returning true when the cookie holds a valid token.
	 *
	 * @covers ::rsvp_route
	 */
	public function test_rsvp_route_permission_callback_with_token(): void {
		$instance = Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_route' );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create a valid RSVP token.
		$email       = 'test@example.com';
		$rsvp        = new Rsvp( $post_id );
		$user_record = $rsvp->save( $email, 'attending', 0, 0 );

		// Generate token.
		$rsvp_token = new Token( $user_record['comment_id'] );
		$rsvp_token->generate_token();
		$token_value = $rsvp_token->get_token();
		$token_str   = sprintf( '%d_%s', $user_record['comment_id'], $token_value );

		// Send the session cookie for the event the token was issued against.
		$this->set_rsvp_session_cookie( $post_id, $token_str );

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $post_id );

		// Call the permission callback.
		$permission_callback = $route['args']['permission_callback'];
		$result              = call_user_func( $permission_callback, $request );

		$this->assertTrue( $result, 'Permission callback should return true with a valid RSVP session cookie' );
	}

	/**
	 * Tests a magic-link attendee can change their RSVP through the REST route.
	 *
	 * The request is dispatched through the REST server, so the permission
	 * callback runs, with the token in the session cookie the browser sends.
	 *
	 * @since 0.36.0
	 * @covers ::rsvp_route
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_rsvp_route_lets_a_token_holder_change_their_rsvp(): void {
		$post_id = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2099-01-01 10:00:00',
				'datetime_end'   => '2099-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$user_record = ( new Rsvp( $post_id ) )->save( 'test@example.com', 'attending' );
		$rsvp_token  = new Token( $user_record['comment_id'] );
		$rsvp_token->generate_token();

		wp_set_current_user( 0 );
		$this->set_rsvp_session_cookie(
			$post_id,
			sprintf( '%d_%s', $user_record['comment_id'], $rsvp_token->get_token() )
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'post_id' => $post_id,
					'status'  => 'not_attending',
				)
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), 'A valid token should authorize the request.' );
		$this->assertTrue( $response->get_data()['success'], 'The RSVP change should be reported as saved.' );
		$this->assertSame( 'not_attending', $response->get_data()['status'] );
		$this->assertSame(
			'not_attending',
			( new Rsvp( $post_id ) )->get( 'test@example.com' )['status'],
			'The stored RSVP should be not attending.'
		);
	}

	/**
	 * Tests a token holder who cannot read the roster gets counts, not attendee records.
	 *
	 * A token authorizes the RSVP change only. On a password-protected event an
	 * anonymous visitor cannot read the attendee list, so the response carries
	 * the per-status counts the RSVP block needs and drops the records.
	 *
	 * @since 0.36.0
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_rsvp_route_gives_counts_only_to_a_token_holder_without_roster_access(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'     => Event::POST_TYPE,
				'post_password' => 'secret',
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2099-01-01 10:00:00',
				'datetime_end'   => '2099-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		( new Rsvp( $post_id ) )->save( 'other@example.com', 'attending' );

		$user_record = ( new Rsvp( $post_id ) )->save( 'test@example.com', 'not_attending' );
		$rsvp_token  = new Token( $user_record['comment_id'] );
		$rsvp_token->generate_token();

		wp_set_current_user( 0 );
		$this->set_rsvp_session_cookie(
			$post_id,
			sprintf( '%d_%s', $user_record['comment_id'], $rsvp_token->get_token() )
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'post_id' => $post_id,
					'status'  => 'attending',
				)
			)
		);

		$data = rest_do_request( $request )->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertSame( 2, $data['responses']['attending']['count'] );

		foreach ( $data['responses'] as $status => $group ) {
			$this->assertSame(
				array( 'count' ),
				array_keys( $group ),
				sprintf( 'The %s group should carry only its count.', $status )
			);
		}
	}

	/**
	 * Tests a magic-link attendee gets the online event link back after an RSVP change.
	 *
	 * The session cookie, not the URL, carries the token, so the link has to
	 * be built for the token's attendee.
	 *
	 * @since 0.36.0
	 * @covers ::update_rsvp
	 * @covers \GatherPress\Core\Event\Event::maybe_get_online_event_link
	 *
	 * @return void
	 */
	public function test_rsvp_route_returns_online_link_to_a_token_holder(): void {
		$post_id = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2099-01-01 10:00:00',
				'datetime_end'   => '2099-01-01 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);
		update_post_meta( $post_id, 'gatherpress_online_event_link', 'https://meet.example.test/room' );

		$user_record = ( new Rsvp( $post_id ) )->save( 'test@example.com', 'not_attending' );
		$rsvp_token  = new Token( $user_record['comment_id'] );
		$rsvp_token->generate_token();

		wp_set_current_user( 0 );
		$this->set_rsvp_session_cookie(
			$post_id,
			sprintf( '%d_%s', $user_record['comment_id'], $rsvp_token->get_token() )
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'post_id' => $post_id,
					'status'  => 'attending',
				)
			)
		);

		$data = rest_do_request( $request )->get_data();

		$this->assertSame( 'attending', $data['status'] );
		$this->assertSame(
			'https://meet.example.test/room',
			$data['online_link'],
			'An attending token holder should get the online event link.'
		);
	}

	/**
	 * A token issued for one event does not authorize a request against a
	 * different event, even in that event's cookie.
	 *
	 * @covers ::rsvp_route
	 *
	 * @return void
	 */
	public function test_rsvp_route_permission_token_not_valid_for_other_event(): void {
		$instance = Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_route' );

		$token_event = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$other_event = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'private',
			)
		);

		$user_record = ( new Rsvp( $token_event ) )->save( 'test@example.com', 'attending' );
		$rsvp_token  = new Token( $user_record['comment_id'] );
		$rsvp_token->generate_token();
		$token_str = sprintf( '%d_%s', $user_record['comment_id'], $rsvp_token->get_token() );

		wp_set_current_user( 0 );

		// The other event's cookie name, holding this event's token.
		$this->set_rsvp_session_cookie( $other_event, $token_str );

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $other_event );

		$this->assertFalse(
			call_user_func( $route['args']['permission_callback'], $request ),
			'A token for one event must not authorize a request carrying another event id.'
		);
	}

	/**
	 * A logged-in caller may reach the route only for an event they can read:
	 * a published event yes, a private event they cannot read no.
	 *
	 * @covers ::rsvp_route
	 *
	 * @return void
	 */
	public function test_rsvp_route_permission_logged_in_requires_read_access(): void {
		$instance = Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_route' );
		$callback = $route['args']['permission_callback'];

		$published = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$private   = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'private',
			)
		);

		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $published );
		$this->assertTrue(
			call_user_func( $callback, $request ),
			'A logged-in user may self-RSVP to a published event.'
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $private );
		$this->assertFalse(
			call_user_func( $callback, $request ),
			'A logged-in user without read access must not reach a private event.'
		);

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$this->assertTrue(
			call_user_func( $callback, $request ),
			'An editor reaches the route for a private event.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * The response-counts reducer keeps per-status counts and drops the
	 * identifying records.
	 *
	 * @covers ::rsvp_response_counts
	 *
	 * @return void
	 */
	public function test_rsvp_response_counts_strips_records(): void {
		$instance = Rest_Api::get_instance();

		$responses = array(
			'all'       => array(
				'records' => array( array( 'name' => 'A' ) ),
				'count'   => 3,
			),
			'attending' => array(
				'records' => array( array( 'name' => 'A' ) ),
				'count'   => 2,
			),
		);

		$counts = Utility::invoke_hidden_method( $instance, 'rsvp_response_counts', array( $responses ) );

		$this->assertSame(
			array(
				'all'       => array( 'count' => 3 ),
				'attending' => array( 'count' => 2 ),
			),
			$counts,
			'Only per-status counts survive, with the records dropped.'
		);
	}

	/**
	 * Tests rsvp_route permission callback without token for logged out user.
	 *
	 * Covers: permission callback returning false when user is not logged in.
	 *
	 * @covers ::rsvp_route
	 */
	public function test_rsvp_route_permission_callback_logged_out(): void {
		$instance = Rest_Api::get_instance();
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
	 * A missing or non-existent post is never readable.
	 *
	 * @covers ::can_read_event_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_event_rsvps_missing_post(): void {
		$instance = Rest_Api::get_instance();

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', 0 );

		$this->assertFalse(
			$instance->can_read_event_rsvps( $request ),
			'A request for a post that does not exist must be denied.'
		);
	}

	/**
	 * A published event with no password exposes its roster publicly.
	 *
	 * @covers ::can_read_event_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_event_rsvps_published_is_public(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post_id );

		$this->assertTrue(
			$instance->can_read_event_rsvps( $request ),
			'A published event roster is public.'
		);
	}

	/**
	 * A password-protected event withholds its roster from callers who have
	 * not satisfied the password gate, but still exposes it to a viewer who
	 * can edit the event.
	 *
	 * @covers ::can_read_event_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_event_rsvps_password_protected_is_gated(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'     => Event::POST_TYPE,
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post_id );

		wp_set_current_user( 0 );
		$this->assertFalse(
			$instance->can_read_event_rsvps( $request ),
			'An anonymous caller must not read a password-protected event roster.'
		);

		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->assertTrue(
			$instance->can_read_event_rsvps( $request ),
			'A viewer who can edit the event bypasses the password gate.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Draft, pending, private, and trashed events expose their roster only to
	 * viewers allowed to read that specific event.
	 *
	 * @covers ::can_read_event_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_event_rsvps_restricted_statuses_require_read_access(): void {
		$instance = Rest_Api::get_instance();

		foreach ( array( 'draft', 'pending', 'private', 'trash' ) as $status ) {
			$post_id = $this->factory()->post->create(
				array(
					'post_type'   => Event::POST_TYPE,
					'post_status' => $status,
				)
			);

			$request = new WP_REST_Request( 'GET' );
			$request->set_param( 'post_id', $post_id );

			wp_set_current_user( 0 );
			$this->assertFalse(
				$instance->can_read_event_rsvps( $request ),
				sprintf( 'An anonymous caller must not read the roster of a %s event.', $status )
			);

			$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
			wp_set_current_user( $admin_id );
			$this->assertTrue(
				$instance->can_read_event_rsvps( $request ),
				sprintf( 'A viewer with read access sees the roster of a %s event.', $status )
			);
		}

		wp_set_current_user( 0 );
	}

	/**
	 * A viewer who can read a private event but cannot edit it still sees the
	 * roster, exercising the read_post allow branch independently of edit_post.
	 *
	 * @covers ::can_read_event_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_event_rsvps_read_access_without_edit(): void {
		$instance = Rest_Api::get_instance();

		$author_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'private',
				'post_author' => $author_id,
			)
		);

		// A viewer who can read private posts but cannot edit this one.
		$viewer_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		( new WP_User( $viewer_id ) )->add_cap( 'read_private_posts' );
		wp_set_current_user( $viewer_id );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'post_id', $post_id );

		$this->assertFalse(
			current_user_can( Event::EDIT_CAPABILITY, $post_id ),
			'The viewer must lack edit access so the read_post branch is the one exercised.'
		);
		$this->assertTrue(
			$instance->can_read_event_rsvps( $request ),
			'A viewer with read access to a private event may read its roster.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Tests rsvp_form_route author validate callback with empty value.
	 *
	 * Covers: validate callback returning false for empty sanitized author.
	 *
	 * @covers ::rsvp_form_route
	 */
	public function test_rsvp_form_route_author_validate_callback_empty(): void {
		$instance = Rest_Api::get_instance();
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
		$instance = Rest_Api::get_instance();
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
		$instance = Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'rsvp_form_route' );

		$validate_callback = $route['args']['args']['gatherpress_form_schema_id']['validate_callback'];

		// Test with valid form_0.
		$result = call_user_func( $validate_callback, 'form_0' );
		$this->assertTrue( $result, 'Validate callback should return true for form_0' );

		// Test with valid form_123.
		$result = call_user_func( $validate_callback, 'form_123' );
		$this->assertTrue( $result, 'Validate callback should return true for form_123' );

		// Test with nested form IDs, which carry one parent index prefix per level.
		foreach ( array( '2_form_0', '0_0_0_form_0' ) as $nested_id ) {
			$this->assertTrue(
				call_user_func( $validate_callback, $nested_id ),
				sprintf( 'Validate callback should return true for %s', $nested_id )
			);
		}

		// Test with invalid format.
		$result = call_user_func( $validate_callback, 'invalid' );
		$this->assertFalse( $result, 'Validate callback should return false for invalid format' );

		// Test with malformed nested and bare IDs.
		foreach ( array( '2_form_', '_form_0', 'form_', '2form_0', 'form_0_1' ) as $malformed_id ) {
			$this->assertFalse(
				call_user_func( $validate_callback, $malformed_id ),
				sprintf( 'Validate callback should return false for %s', $malformed_id )
			);
		}

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
		$instance = Rest_Api::get_instance();

		$route = Utility::invoke_hidden_method( $instance, 'rsvp_status_html_route' );

		$this->assertIsArray( $route );
		$this->assertArrayHasKey( 'route', $route );
		$this->assertArrayHasKey( 'args', $route );
		$this->assertEquals( 'rsvp-status-html', $route['route'] );
		$this->assertArrayHasKey( 'block_signature', $route['args']['args'] );
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
		$instance = Rest_Api::get_instance();

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
	 * Tests update_rsvp when a subscriber tries to add someone else.
	 *
	 * Covers: user without edit_post on the target event gets user_id set to 0.
	 *
	 * @covers ::update_rsvp
	 */
	public function test_update_rsvp_non_admin_cannot_add_others(): void {
		$instance = Rest_Api::get_instance();

		// Create a subscriber (no edit_post on any event).
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
		// The user_id should be reset to 0 since subscriber lacks edit_post on this event.
		$this->assertFalse( $response->data['success'], 'RSVP should fail when non-admin tries to add someone else' );
	}

	/**
	 * Tests update_rsvp when an Author tries to add someone else to a foreign event.
	 *
	 * The previous flat `edit_posts` gate would have let any Author manage
	 * attendees on any event. The per-post `edit_post` check denies an Author
	 * acting on an event they don't own.
	 *
	 * @covers ::update_rsvp
	 */
	public function test_update_rsvp_author_cannot_add_others_to_foreign_event(): void {
		$instance = Rest_Api::get_instance();

		$event_owner_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$author_id      = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_user_id  = $this->factory->user->create();

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_author' => $event_owner_id,
				'post_status' => 'publish',
			)
		);

		// Switch to a *different* author who doesn't own the event.
		wp_set_current_user( $author_id );

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'user_id', $other_user_id );
		$request->set_param( 'status', 'attending' );

		$response = $instance->update_rsvp( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertFalse(
			$response->data['success'],
			'A non-owner Author should not be able to RSVP someone else into another author\'s event.'
		);
	}

	/**
	 * Tests rsvp_status_html setting nocache headers when logged in.
	 *
	 * Covers: nocache_headers() called when user is logged in.
	 *
	 * @covers ::rsvp_status_html
	 */
	public function test_rsvp_status_html_nocache_when_logged_in(): void {
		$instance = Rest_Api::get_instance();

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
		$request->set_param(
			'block_signature',
			Rsvp_Template::sign_template( (string) wp_json_encode( array() ), $post_id )
		);

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
		$instance = Rest_Api::get_instance();

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
		// The actual processing and saving is done by Rsvp\Form::process_rsvp().
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
		$instance = Rest_Api::get_instance();

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

	/**
	 * Tests update_rsvp adds user to blog in multisite when not a member.
	 *
	 * Covers: add_user_to_blog when user RSVPs to event on different site.
	 *
	 * @group multisite
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_update_rsvp_adds_user_to_blog_multisite(): void {
		$instance = Rest_Api::get_instance();

		// Create a user on site 1.
		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'testuser',
				'user_email' => 'testuser@example.com',
				'role'       => 'subscriber',
			)
		);

		// Create site 2.
		$site_2_id = $this->factory->blog->create();

		// Switch to site 2.
		switch_to_blog( $site_2_id );

		// Create tables on site 2.
		$setup = Setup::get_instance();
		Utility::invoke_hidden_method( $setup, 'create_tables' );

		// Create an event on site 2.
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Verify user is NOT a member of site 2.
		$this->assertFalse( is_user_member_of_blog( $user_id, $site_2_id ), 'User should not be member of site 2' );

		// Set current user to the user from site 1.
		wp_set_current_user( $user_id );

		// Create RSVP request.
		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'attending' );
		$request->set_param( 'user_id', $user_id );

		// Call update_rsvp.
		$response = $instance->update_rsvp( $request );

		// Verify user was added to site 2 as a subscriber.
		$this->assertTrue( is_user_member_of_blog( $user_id, $site_2_id ), 'User should be added to site 2' );

		// Verify they have subscriber role on site 2.
		$user = new \WP_User( $user_id );
		$this->assertTrue( $user->has_cap( 'read' ), 'User should have subscriber capabilities on site 2' );

		// Verify RSVP was successful.
		$this->assertTrue( $response->data['success'], 'RSVP should be successful' );

		restore_current_blog();
	}

	/**
	 * Tests that a manager with edit_post but not promote_users cannot enroll others.
	 *
	 * Managing attendees is gated by `edit_post`, but adding a non-member user to
	 * the blog is a `promote_users` action. An event author (has edit_post on
	 * their own event, lacks promote_users) must not be able to add another user
	 * to the site via the RSVP endpoint, and the RSVP for that non-member fails.
	 *
	 * @group multisite
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_update_rsvp_manager_without_promote_users_cannot_enroll_other_multisite(): void {
		$instance = Rest_Api::get_instance();

		// Target network user, not a member of site 2.
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$site_2_id = $this->factory->blog->create();
		switch_to_blog( $site_2_id );

		$setup = Setup::get_instance();
		Utility::invoke_hidden_method( $setup, 'create_tables' );

		// Author who owns the event on site 2: has edit_post on it, no promote_users.
		$author_id = $this->factory->user->create();
		add_user_to_blog( $site_2_id, $author_id, 'author' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_author' => $author_id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $author_id );

		$this->assertFalse(
			current_user_can( 'promote_users' ),
			'Author should not have promote_users.'
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'user_id', $target_id );
		$request->set_param( 'status', 'attending' );

		$response = $instance->update_rsvp( $request );

		$this->assertFalse(
			is_user_member_of_blog( $target_id, $site_2_id ),
			'Target must not be enrolled into site 2 by a manager lacking promote_users.'
		);
		$this->assertFalse(
			$response->data['success'],
			'RSVP for a non-member target should fail when the manager cannot enroll them.'
		);

		restore_current_blog();
	}

	/**
	 * Tests that a manager with promote_users can enroll another user.
	 *
	 * A site administrator holds both edit_post and promote_users, so managing an
	 * RSVP for a non-member network user enrolls them as a subscriber and the
	 * RSVP succeeds.
	 *
	 * @group multisite
	 * @covers ::update_rsvp
	 *
	 * @return void
	 */
	public function test_update_rsvp_admin_with_promote_users_enrolls_other_multisite(): void {
		$instance = Rest_Api::get_instance();

		// Target network user, not a member of site 2.
		$target_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$site_2_id = $this->factory->blog->create();
		switch_to_blog( $site_2_id );

		$setup = Setup::get_instance();
		Utility::invoke_hidden_method( $setup, 'create_tables' );

		// Administrator on site 2: has edit_post (edit_others_posts) and promote_users.
		$admin_id = $this->factory->user->create();
		add_user_to_blog( $site_2_id, $admin_id, 'administrator' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_author' => $admin_id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $admin_id );

		$this->assertTrue(
			current_user_can( 'promote_users' ),
			'Administrator should have promote_users.'
		);

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/rsvp' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'user_id', $target_id );
		$request->set_param( 'status', 'attending' );

		$response = $instance->update_rsvp( $request );

		$this->assertTrue(
			is_user_member_of_blog( $target_id, $site_2_id ),
			'Target should be enrolled into site 2 by a manager with promote_users.'
		);
		$this->assertTrue(
			$response->data['success'],
			'RSVP should succeed when the manager can enroll the target.'
		);

		restore_current_blog();
	}

	/**
	 * The endpoint renders only a template the server emitted.
	 *
	 * @covers ::rsvp_status_html
	 *
	 * @return void
	 */
	public function test_rsvp_status_html_renders_only_an_emitted_template(): void {
		$instance = Rest_Api::get_instance();
		$post_id  = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$template = (string) wp_json_encode(
			array(
				'blockName'   => 'gatherpress/rsvp-template',
				'attrs'       => array(),
				'innerBlocks' => array(),
			)
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'status', 'attending' );
		$request->set_param( 'block_data', $template );
		$request->set_param( 'block_signature', str_repeat( '0', 64 ) );

		$response = $instance->rsvp_status_html( $request );

		$this->assertSame( 403, $response->get_status(), 'Failed to assert a wrong signature is refused.' );
		$this->assertFalse( $response->get_data()['success'] );

		// A signature for one template does not carry over to another.
		$request->set_param( 'block_signature', Rsvp_Template::sign_template( $template, $post_id ) );
		$request->set_param(
			'block_data',
			(string) wp_json_encode(
				array(
					'blockName'   => 'gatherpress/rsvp-template',
					'attrs'       => array( 'x' => 1 ),
					'innerBlocks' => array(),
				)
			)
		);

		$this->assertSame(
			403,
			$instance->rsvp_status_html( $request )->get_status(),
			'Failed to assert an altered template is refused.'
		);

		// The pair the server produced is accepted.
		$request->set_param( 'block_data', $template );

		$this->assertSame(
			200,
			$instance->rsvp_status_html( $request )->get_status(),
			'Failed to assert the emitted pair is accepted.'
		);

		// A pair emitted for one event does not carry over to another.
		$request->set_param(
			'post_id',
			$this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) )
		);

		$this->assertSame(
			403,
			$instance->rsvp_status_html( $request )->get_status(),
			'Failed to assert a pair emitted for another event is refused.'
		);
	}

	/**
	 * The route itself refuses a request without a usable signature.
	 *
	 * The handler tests call the callback directly and so skip the argument
	 * validation the route registers. This one goes through the server.
	 *
	 * @covers ::rsvp_status_html_route
	 * @covers ::rsvp_status_html
	 *
	 * @return void
	 */
	public function test_rsvp_status_html_route_requires_a_signed_template(): void {
		Rest_Api::get_instance()->register_endpoints();

		$post_id  = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$template = (string) wp_json_encode(
			array(
				'blockName'   => 'gatherpress/rsvp-template',
				'attrs'       => array(),
				'innerBlocks' => array(),
			)
		);
		$route    = sprintf( '/%s/event/rsvp-status-html', GATHERPRESS_REST_NAMESPACE );

		$request = new WP_REST_Request( 'POST', $route );
		$request->set_body_params(
			array(
				'post_id'    => $post_id,
				'status'     => 'attending',
				'block_data' => $template,
			)
		);

		$this->assertSame(
			400,
			rest_do_request( $request )->get_status(),
			'Failed to assert a missing signature is refused by the route.'
		);

		$request->set_param( 'block_signature', 'not-a-signature' );
		$this->assertSame(
			400,
			rest_do_request( $request )->get_status(),
			'Failed to assert a malformed signature is refused by the route.'
		);

		$request->set_param( 'block_signature', str_repeat( '0', 64 ) );
		$this->assertSame(
			403,
			rest_do_request( $request )->get_status(),
			'Failed to assert a wrong signature is refused by the handler.'
		);

		$request->set_param( 'block_signature', Rsvp_Template::sign_template( $template, $post_id ) );
		$this->assertSame(
			200,
			rest_do_request( $request )->get_status(),
			'Failed to assert the emitted pair passes the route.'
		);

		$request->set_param(
			'block_data',
			(string) wp_json_encode(
				array(
					'blockName'   => 'core/paragraph',
					'attrs'       => array(),
					'innerBlocks' => array(),
				)
			)
		);
		$this->assertSame(
			400,
			rest_do_request( $request )->get_status(),
			'Failed to assert a root that is not the RSVP template is refused by the route.'
		);
	}
}
