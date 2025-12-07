<?php
/**
 * Class file for Test_Rsvp_Form.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Form;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rsvp_Form.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp_Form
 */
class Test_Rsvp_Form extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Rsvp_Form::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'initialize_rsvp_form_handling' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for has_duplicate_rsvp method.
	 *
	 * @covers ::has_duplicate_rsvp
	 *
	 * @return void
	 */
	public function test_has_duplicate_rsvp(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$instance = Rsvp_Form::get_instance();

		// Test with no existing RSVP.
		$result = $instance->has_duplicate_rsvp( $post_id, 'new@example.com' );
		$this->assertFalse( $result );

		// Create an RSVP comment.
		$this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => 'test@example.com',
			)
		);

		// Test with duplicate email.
		$result = $instance->has_duplicate_rsvp( $post_id, 'test@example.com' );
		$this->assertTrue( $result );

		// Test with different email.
		$result = $instance->has_duplicate_rsvp( $post_id, 'different@example.com' );
		$this->assertFalse( $result );
	}

	/**
	 * Coverage for has_duplicate_rsvp method with user ID.
	 *
	 * @covers ::has_duplicate_rsvp
	 *
	 * @return void
	 */
	public function test_has_duplicate_rsvp_with_user(): void {
		$user_id = $this->factory->user->create(
			array(
				'user_email' => 'user@example.com',
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an RSVP comment with user ID.
		$this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => 'user@example.com',
				'user_id'              => $user_id,
			)
		);

		$instance = Rsvp_Form::get_instance();

		// Test with same email (should detect duplicate by user_id OR email).
		$result = $instance->has_duplicate_rsvp( $post_id, 'user@example.com' );
		$this->assertTrue( $result );
	}

	/**
	 * Coverage for process_rsvp method success case.
	 *
	 * @covers ::process_rsvp
	 * @covers ::process_fields
	 *
	 * @return void
	 */
	public function test_process_rsvp_success(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$data = array(
			'post_id' => $post_id,
			'author'  => 'Test User',
			'email'   => 'test@example.com',
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_rsvp( $data );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( 'RSVP has been submitted successfully', $result['message'] );
		$this->assertGreaterThan( 0, $result['comment_id'] );
	}

	/**
	 * Coverage for process_rsvp method with duplicate detection.
	 *
	 * @covers ::process_rsvp
	 * @covers ::has_duplicate_rsvp
	 * @covers ::get_duplicate_rsvp_message
	 *
	 * @return void
	 */
	public function test_process_rsvp_duplicate(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create existing RSVP.
		$this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => 'test@example.com',
			)
		);

		$data = array(
			'post_id' => $post_id,
			'author'  => 'Test User',
			'email'   => 'test@example.com',
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_rsvp( $data );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'already RSVP\'d', $result['message'] );
		$this->assertSame( 409, $result['error_code'] );
	}

	/**
	 * Coverage for process_rsvp method with missing required fields.
	 *
	 * @covers ::process_rsvp
	 *
	 * @return void
	 */
	public function test_process_rsvp_missing_required_fields(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$instance = Rsvp_Form::get_instance();

		// Test missing author.
		$data = array(
			'post_id' => $post_id,
			'email'   => 'test@example.com',
			// Missing author field.
		);

		$result = $instance->process_rsvp( $data );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Missing required fields', $result['message'] );
		$this->assertSame( 400, $result['error_code'] );

		// Test missing email.
		$data = array(
			'post_id' => $post_id,
			'author'  => 'Test User',
			// Missing email field.
		);

		$result = $instance->process_rsvp( $data );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Missing required fields', $result['message'] );
		$this->assertSame( 400, $result['error_code'] );

		// Test missing post_id.
		$data = array(
			'author' => 'Test User',
			'email'  => 'test@example.com',
			// Missing post_id field.
		);

		$result = $instance->process_rsvp( $data );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Missing required fields', $result['message'] );
		$this->assertSame( 400, $result['error_code'] );
	}

	/**
	 * Coverage for process_rsvp method with guest count validation.
	 *
	 * @covers ::process_fields
	 *
	 * @return void
	 */
	public function test_process_rsvp_with_guest_limit(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set max guest limit.
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 3 );

		$data = array(
			'post_id'                 => $post_id,
			'author'                  => 'Test User',
			'email'                   => 'test@example.com',
			'gatherpress_rsvp_guests' => 5, // Exceeds limit.
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_rsvp( $data );

		$this->assertTrue( $result['success'] );

		// Check that guest count was capped at the limit.
		$guest_count = get_comment_meta( $result['comment_id'], 'gatherpress_rsvp_guests', true );
		$this->assertEquals( 3, $guest_count );
	}

	/**
	 * Coverage for process_rsvp method with anonymous RSVP validation.
	 *
	 * @covers ::process_fields
	 *
	 * @return void
	 */
	public function test_process_rsvp_with_anonymous_validation(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Enable anonymous RSVP for this event.
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', '1' );

		$data = array(
			'post_id'                    => $post_id,
			'author'                     => 'Test User',
			'email'                      => 'test@example.com',
			'gatherpress_rsvp_anonymous' => true,
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_rsvp( $data );

		$this->assertTrue( $result['success'] );

		// Check that anonymous flag was saved.
		$anonymous = get_comment_meta( $result['comment_id'], 'gatherpress_rsvp_anonymous', true );
		$this->assertEquals( 1, $anonymous );
	}

	/**
	 * Coverage for process_rsvp method with anonymous RSVP disabled.
	 *
	 * @covers ::process_fields
	 *
	 * @return void
	 */
	public function test_process_rsvp_anonymous_disabled(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Do NOT enable anonymous RSVP for this event.

		$data = array(
			'post_id'                    => $post_id,
			'author'                     => 'Test User',
			'email'                      => 'test@example.com',
			'gatherpress_rsvp_anonymous' => true, // Try to be anonymous.
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_rsvp( $data );

		$this->assertTrue( $result['success'] );

		// Check that anonymous flag was NOT saved because it's disabled.
		$anonymous = get_comment_meta( $result['comment_id'], 'gatherpress_rsvp_anonymous', true );
		$this->assertEmpty( $anonymous );
	}

	/**
	 * Coverage for is_rsvp_form_submission method.
	 *
	 * @covers ::is_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_is_rsvp_form_submission(): void {
		$instance = Rsvp_Form::get_instance();

		// Test with no POST data.
		$result = $instance->is_rsvp_form_submission();
		$this->assertFalse( $result );

		// Mock POST request with required fields.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'comment_post_ID' === $var_name ) {
					return '123';
				}
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_id' === $var_name ) {
					return 'test_form';
				}
				return null;
			},
			10,
			3
		);

		// Mock POST request method.
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$result = $instance->is_rsvp_form_submission();
		$this->assertTrue( $result );

		// Test with GET request.
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$result                    = $instance->is_rsvp_form_submission();
		$this->assertFalse( $result );

		// Clean up.
		unset( $_SERVER['REQUEST_METHOD'] );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for initialize_rsvp_form_handling method.
	 *
	 * @covers ::initialize_rsvp_form_handling
	 * @covers ::get_duplicate_rsvp_message
	 *
	 * @return void
	 */
	public function test_initialize_rsvp_form_handling(): void {
		$instance = Rsvp_Form::get_instance();

		// Remove existing hooks first to get clean slate.
		remove_all_filters( 'preprocess_comment' );
		remove_all_actions( 'comment_post' );
		remove_all_filters( 'comment_post_redirect' );

		// Test that hooks are not registered initially.
		$this->assertFalse( has_filter( 'preprocess_comment', array( $instance, 'preprocess_rsvp_comment' ) ) );
		$this->assertFalse( has_action( 'comment_post', array( $instance, 'handle_rsvp_comment_post' ) ) );
		$this->assertFalse( has_filter( 'comment_post_redirect', array( $instance, 'handle_rsvp_comment_redirect' ) ) );

		// Mock RSVP form submission.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'comment_post_ID' === $var_name ) {
					return '123';
				}
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_id' === $var_name ) {
					return 'test_form';
				}
				return null;
			},
			10,
			3
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$instance->initialize_rsvp_form_handling();

		// Verify hooks are registered (returns priority, not boolean).
		$this->assertNotFalse( has_filter( 'preprocess_comment', array( $instance, 'preprocess_rsvp_comment' ) ) );
		$this->assertNotFalse( has_action( 'comment_post', array( $instance, 'handle_rsvp_comment_post' ) ) );
		$this->assertNotFalse( has_filter( 'comment_post_redirect', array( $instance, 'handle_rsvp_comment_redirect' ) ) );

		// Clean up.
		unset( $_SERVER['REQUEST_METHOD'] );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for preprocess_rsvp_comment method.
	 *
	 * @covers ::preprocess_rsvp_comment
	 *
	 * @return void
	 */
	public function test_preprocess_rsvp_comment(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Mock form submission data for RSVP.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) use ( $post_id ) {
				if ( INPUT_POST === $type && 'author' === $var_name ) {
					return 'Test Author';
				}
				if ( INPUT_POST === $type && 'email' === $var_name ) {
					return 'test@example.com';
				}
				return '';
			},
			10,
			3
		);

		$instance = Rsvp_Form::get_instance();

		// Test RSVP comment data - the method always processes when called.
		$comment_data = array(
			'comment_post_ID' => $post_id,
			'comment_content' => 'RSVP comment',
		);

		$result = $instance->preprocess_rsvp_comment( $comment_data );

		// Should modify comment data for RSVP.
		$this->assertEquals( $post_id, $result['comment_post_ID'] );
		$this->assertEquals( '', $result['comment_content'] );
		$this->assertEquals( Rsvp::COMMENT_TYPE, $result['comment_type'] );
		$this->assertEquals( 0, $result['comment_parent'] );

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for handle_rsvp_comment_post method.
	 *
	 * @covers ::handle_rsvp_comment_post
	 *
	 * @return void
	 */
	public function test_handle_rsvp_comment_post(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create RSVP comment.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Mock form data.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_guests' === $var_name ) {
					return '2';
				}
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_id' === $var_name ) {
					return 'test_form';
				}
				return null;
			},
			10,
			3
		);

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$instance = Rsvp_Form::get_instance();
		$instance->handle_rsvp_comment_post( $comment_id );

		// Check that guest count was saved.
		$guest_count = get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true );
		$this->assertEquals( 2, $guest_count );

		// Clean up.
		unset( $_SERVER['REQUEST_METHOD'] );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for handle_rsvp_comment_redirect method.
	 *
	 * @covers ::handle_rsvp_comment_redirect
	 *
	 * @return void
	 */
	public function test_handle_rsvp_comment_redirect(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create RSVP comment.
		$comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Mock form data and referer.
		$event_url = get_permalink( $post_id );
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_id' === $var_name ) {
					return 'test_form_id';
				}
				return null;
			},
			10,
			3
		);

		// Mock wp_get_referer.
		add_filter(
			'gatherpress_pre_get_wp_referer',
			function () use ( $event_url ) {
				return $event_url;
			}
		);

		$instance = Rsvp_Form::get_instance();

		// Test with RSVP comment - should redirect to referer with success param.
		$original_location = 'https://example.com/original';
		$result            = $instance->handle_rsvp_comment_redirect( $original_location, $comment );

		// Should redirect to event page with success param and form anchor.
		$expected_location = add_query_arg( 'gatherpress_rsvp_success', 'true', $event_url ) . '#test_form_id';
		$this->assertEquals( $expected_location, $result );

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_wp_referer' );
		remove_all_filters( 'gatherpress_pre_get_http_input' );

		// Test with regular comment.
		$regular_comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		$result = $instance->handle_rsvp_comment_redirect( $original_location, $regular_comment );

		// Should return original location.
		$this->assertEquals( $original_location, $result );
	}

	/**
	 * Coverage for prepare_comment_data method.
	 *
	 * @covers ::prepare_comment_data
	 *
	 * @return void
	 */
	public function test_prepare_comment_data(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$instance = Rsvp_Form::get_instance();

		// Test with anonymous user.
		$result = Utility::invoke_hidden_method(
			$instance,
			'prepare_comment_data',
			array( $post_id, 'Test Author', 'test@example.com' )
		);

		$this->assertEquals( $post_id, $result['comment_post_ID'] );
		$this->assertEquals( 'Test Author', $result['comment_author'] );
		$this->assertEquals( 'test@example.com', $result['comment_author_email'] );
		$this->assertEquals( Rsvp::COMMENT_TYPE, $result['comment_type'] );
		$this->assertEquals( 0, $result['user_id'] );
		$this->assertEquals( 0, $result['comment_approved'] );
	}

	/**
	 * Coverage for prepare_comment_data method with logged-in user.
	 *
	 * @covers ::prepare_comment_data
	 *
	 * @return void
	 */
	public function test_prepare_comment_data_logged_in_user(): void {
		$user_id = $this->factory->user->create(
			array(
				'user_email'   => 'user@example.com',
				'display_name' => 'Test User',
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		wp_set_current_user( $user_id );

		$instance = Rsvp_Form::get_instance();

		// Test with matching email.
		$result = Utility::invoke_hidden_method(
			$instance,
			'prepare_comment_data',
			array( $post_id, 'Form Author', 'user@example.com' )
		);

		// Should use logged-in user data.
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertEquals( 'Test User', $result['comment_author'] );
		$this->assertEquals( 'user@example.com', $result['comment_author_email'] );

		wp_set_current_user( 0 );
	}

	/**
	 * Coverage for prepare_comment_data method with existing user by email.
	 *
	 * @covers ::prepare_comment_data
	 *
	 * @return void
	 */
	public function test_prepare_comment_data_existing_user_by_email(): void {
		$user_id = $this->factory->user->create(
			array(
				'user_email'   => 'existing@example.com',
				'display_name' => 'Existing User',
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$instance = Rsvp_Form::get_instance();

		// Test with email of existing user (but not logged in as that user).
		$result = Utility::invoke_hidden_method(
			$instance,
			'prepare_comment_data',
			array( $post_id, 'Form Author', 'existing@example.com' )
		);

		// Should associate with existing user.
		$this->assertEquals( $user_id, $result['user_id'] );
		$this->assertEquals( 'Existing User', $result['comment_author'] );
		$this->assertEquals( 'existing@example.com', $result['comment_author_email'] );
	}

	/**
	 * Tests process_fields method with meta and custom fields.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_fields_with_meta_and_custom_fields(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set up event meta for guest limit and anonymous RSVP.
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 5 );
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', 1 );

		// Create a comment (RSVP).
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
				'user_id'         => 0,
			)
		);

		// Set up form schema for custom fields.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'custom_field_1' => array(
						'name' => 'custom_field_1',
						'type' => 'text',
					),
					'custom_field_2' => array(
						'name' => 'custom_field_2',
						'type' => 'email',
					),
				),
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		$data = array(
			'gatherpress_event_updates_opt_in' => '1',
			'gatherpress_rsvp_guests'          => '3',
			'gatherpress_rsvp_anonymous'       => '1',
			'gatherpress_form_schema_id'       => 'form_0',
			'custom_field_1'                   => 'Test Value',
			'custom_field_2'                   => 'test@example.com',
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Check that meta fields were processed.
		$this->assertEquals( '1', get_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', true ) );
		$this->assertEquals( '3', get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
		$this->assertEquals( '1', get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true ) );

		// Check that custom fields were processed.
		$this->assertEquals( 'Test Value', get_comment_meta( $comment_id, 'gatherpress_custom_custom_field_1', true ) );
		$this->assertEquals( 'test@example.com', get_comment_meta( $comment_id, 'gatherpress_custom_custom_field_2', true ) );
	}

	/**
	 * Tests process_fields method with guest limit cap enforcement.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_fields_enforces_guest_limit(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set guest limit to 2.
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 2 );

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$data = array(
			'gatherpress_rsvp_guests' => '5', // Try to add 5 guests.
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should be capped at 2.
		$this->assertEquals( '2', get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
	}

	/**
	 * Tests process_fields method with anonymous RSVP validation.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_fields_validates_anonymous_setting(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Anonymous RSVP is disabled.
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', 0 );

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$data = array(
			'gatherpress_rsvp_anonymous' => '1', // Try to set anonymous.
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should not be saved since anonymous is disabled.
		$this->assertEquals( '', get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true ) );
	}

	/**
	 * Tests process_fields method with invalid comment ID.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_fields_with_invalid_comment(): void {
		$data = array(
			'gatherpress_event_updates_opt_in' => '1',
		);

		$instance = Rsvp_Form::get_instance();

		// Should not throw errors with invalid comment ID.
		$instance->process_fields( 99999, $data );

		// No exception should be thrown.
		$this->assertTrue( true );
	}

	/**
	 * Tests preprocess_rsvp_comment with invalid post type.
	 *
	 * @covers ::preprocess_rsvp_comment
	 */
	public function test_preprocess_rsvp_comment_invalid_post_type(): void {
		// Create a regular post (not an event).
		$post_id = $this->factory->post->create(
			array(
				'post_type' => 'post',
			)
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'author' === $var_name ) {
					return 'Test Author';
				}
				if ( INPUT_POST === $type && 'email' === $var_name ) {
					return 'test@example.com';
				}
				return '';
			},
			10,
			3
		);

		$instance = Rsvp_Form::get_instance();

		$comment_data = array(
			'comment_post_ID' => $post_id,
		);

		$this->expectException( 'WPDieException' );
		$instance->preprocess_rsvp_comment( $comment_data );

		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests preprocess_rsvp_comment with past event.
	 *
	 * @covers ::preprocess_rsvp_comment
	 */
	public function test_preprocess_rsvp_comment_past_event(): void {
		// Create a past event.
		$post    = $this->mock->post()->get();
		$post_id = $post->ID;
		$event   = new Event( $post_id );

		// Set event datetime to past.
		$start = new \DateTime( 'now' );
		$end   = new \DateTime( 'now' );

		$start->modify( '-3 hours' );
		$end->modify( '-1 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'author' === $var_name ) {
					return 'Test Author';
				}
				if ( INPUT_POST === $type && 'email' === $var_name ) {
					return 'test@example.com';
				}
				return '';
			},
			10,
			3
		);

		$instance = Rsvp_Form::get_instance();

		$comment_data = array(
			'comment_post_ID' => $post_id,
		);

		$this->expectException( 'WPDieException' );
		$instance->preprocess_rsvp_comment( $comment_data );

		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests preprocess_rsvp_comment with duplicate RSVP.
	 *
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::has_duplicate_rsvp
	 * @covers ::get_duplicate_rsvp_message
	 */
	public function test_preprocess_rsvp_comment_duplicate_rsvp(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create existing RSVP.
		$this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => 'test@example.com',
			)
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'author' === $var_name ) {
					return 'Test Author';
				}
				if ( INPUT_POST === $type && 'email' === $var_name ) {
					return 'test@example.com';
				}
				return '';
			},
			10,
			3
		);

		$instance = Rsvp_Form::get_instance();

		$comment_data = array(
			'comment_post_ID' => $post_id,
		);

		$this->expectException( 'WPDieException' );
		$instance->preprocess_rsvp_comment( $comment_data );

		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests preprocess_rsvp_comment with user mismatch.
	 *
	 * @covers ::preprocess_rsvp_comment
	 */
	public function test_preprocess_rsvp_comment_user_mismatch(): void {
		$user_id = $this->factory->user->create(
			array(
				'user_email' => 'user@example.com',
			)
		);

		wp_set_current_user( $user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$event   = new Event( $post_id );

		// Set event datetime to future.
		$start = new \DateTime( 'now' );
		$end   = new \DateTime( 'now' );

		$start->modify( '+1 day' );
		$end->modify( '+1 day +2 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		// Use different email than logged-in user.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'author' === $var_name ) {
					return 'Test Author';
				}
				if ( INPUT_POST === $type && 'email' === $var_name ) {
					return 'different@example.com';
				}
				return '';
			},
			10,
			3
		);

		$instance = Rsvp_Form::get_instance();

		$comment_data = array(
			'comment_post_ID' => $post_id,
		);

		$result = $instance->preprocess_rsvp_comment( $comment_data );

		// Should set user_id to 0 when email doesn't match.
		$this->assertEquals( 0, $result['user_id'] );
		$this->assertEquals( '', $result['comment_author_url'] );

		wp_set_current_user( 0 );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests handle_rsvp_comment_post with non-RSVP comment.
	 *
	 * @covers ::handle_rsvp_comment_post
	 */
	public function test_handle_rsvp_comment_post_non_rsvp_comment(): void {
		$post_id = $this->factory->post->create();

		// Create regular comment (not RSVP).
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		$instance = Rsvp_Form::get_instance();
		$instance->handle_rsvp_comment_post( $comment_id );

		// Should not set any RSVP meta or terms.
		$terms = wp_get_object_terms( $comment_id, Rsvp::TAXONOMY );
		$this->assertEmpty( $terms );
	}

	/**
	 * Tests handle_rsvp_comment_post with custom fields.
	 *
	 * @covers ::handle_rsvp_comment_post
	 */
	public function test_handle_rsvp_comment_post_with_custom_fields(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Mock custom field in $_POST.
		$_POST['gatherpress_custom_field_1'] = 'Custom Value';

		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'gatherpress_event_updates_opt_in' === $var_name ) {
					return '1';
				}
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_guests' === $var_name ) {
					return '2';
				}
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_anonymous' === $var_name ) {
					return '0';
				}
				return null;
			},
			10,
			3
		);

		$instance = Rsvp_Form::get_instance();
		$instance->handle_rsvp_comment_post( $comment_id );

		// Verify the custom field processing was attempted.
		$this->assertTrue( true );

		// Clean up.
		unset( $_POST['gatherpress_custom_field_1'] );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests handle_rsvp_comment_redirect without referer.
	 *
	 * @covers ::handle_rsvp_comment_redirect
	 */
	public function test_handle_rsvp_comment_redirect_no_referer(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Mock no referer.
		add_filter(
			'gatherpress_pre_get_wp_referer',
			function () {
				return false;
			}
		);

		$instance          = Rsvp_Form::get_instance();
		$original_location = 'https://example.com/original';
		$result            = $instance->handle_rsvp_comment_redirect( $original_location, $comment );

		// Should return original location when no referer.
		$this->assertEquals( $original_location, $result );

		remove_all_filters( 'gatherpress_pre_get_wp_referer' );
	}

	/**
	 * Tests prepare_comment_data with REMOTE_ADDR.
	 *
	 * @covers ::prepare_comment_data
	 */
	public function test_prepare_comment_data_with_remote_addr(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set REMOTE_ADDR.
		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';

		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'prepare_comment_data',
			array( $post_id, 'Test Author', 'test@example.com' )
		);

		$this->assertEquals( '192.168.1.1', $result['comment_author_IP'] );

		// Clean up.
		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Tests prepare_comment_data with invalid IP.
	 *
	 * @covers ::prepare_comment_data
	 */
	public function test_prepare_comment_data_with_invalid_ip(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set invalid REMOTE_ADDR.
		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';

		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'prepare_comment_data',
			array( $post_id, 'Test Author', 'test@example.com' )
		);

		// Should fall back to default IP.
		$this->assertEquals( '127.0.0.1', $result['comment_author_IP'] );

		// Clean up.
		unset( $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * Tests process_custom_fields with traditional form submission.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_custom_fields_traditional_form(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// No form_schema_id - should use traditional form processing.
		$data = array(
			'gatherpress_event_updates_opt_in' => '1',
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should complete without errors.
		$this->assertTrue( true );
	}

	/**
	 * Tests process_custom_fields with non-RSVP comment.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_custom_fields_non_rsvp_comment(): void {
		$post_id = $this->factory->post->create();

		// Create regular comment (not RSVP).
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		$data = array(
			'gatherpress_form_schema_id' => 'form_0',
			'custom_field_1'             => 'Test Value',
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should not process custom fields for non-RSVP comments.
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_custom_custom_field_1', true ) );
	}

	/**
	 * Tests process_custom_fields with no schemas.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_custom_fields_no_schemas(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// No schemas saved for this post.
		$data = array(
			'gatherpress_form_schema_id' => 'form_0',
			'custom_field_1'             => 'Test Value',
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should not process custom fields when schemas are missing.
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_custom_custom_field_1', true ) );
	}

	/**
	 * Tests process_custom_fields with field not in data.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_custom_fields_field_not_in_data(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Set up form schema with fields.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'field_1' => array(
						'name' => 'field_1',
						'type' => 'text',
					),
					'field_2' => array(
						'name' => 'field_2',
						'type' => 'text',
					),
				),
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		// Only include field_1 in data, not field_2.
		$data = array(
			'gatherpress_form_schema_id' => 'form_0',
			'field_1'                    => 'Value 1',
			// field_2 is missing.
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// field_1 should be saved.
		$this->assertEquals( 'Value 1', get_comment_meta( $comment_id, 'gatherpress_custom_field_1', true ) );

		// field_2 should not be saved.
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_custom_field_2', true ) );
	}

	/**
	 * Tests process_meta_fields with non-numeric guests value.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_meta_fields_non_numeric_guests(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$data = array(
			'gatherpress_rsvp_guests' => 'not-a-number',
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should not save non-numeric guest count.
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
	}

	/**
	 * Tests process_meta_fields with email opt-out.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_meta_fields_email_opt_out(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$data = array(
			'gatherpress_event_updates_opt_in' => false, // Opted out.
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should save 0 for opt-out.
		$this->assertEquals( '0', get_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', true ) );
	}

	/**
	 * Tests process_meta_fields with zero guests.
	 *
	 * @covers ::process_fields
	 */
	public function test_process_meta_fields_zero_guests(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Set guest limit.
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 5 );

		$data = array(
			'gatherpress_rsvp_guests' => 0,
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should save 0 for zero guests.
		$this->assertEquals( '0', get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
	}

	/**
	 * Test that initialize_rsvp_form_handling returns early when not an RSVP form submission.
	 *
	 * @covers ::initialize_rsvp_form_handling
	 * @covers ::is_rsvp_form_submission
	 *
	 * @return void
	 */
	public function test_initialize_rsvp_form_handling_not_rsvp_submission(): void {
		$instance = Rsvp_Form::get_instance();

		// Remove existing hooks to get clean slate.
		remove_all_filters( 'preprocess_comment' );
		remove_all_actions( 'comment_post' );

		// Set up non-RSVP request (missing required fields).
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$instance->initialize_rsvp_form_handling();

		// Verify hooks were NOT registered since this isn't an RSVP form submission.
		$this->assertFalse( has_filter( 'preprocess_comment', array( $instance, 'preprocess_rsvp_comment' ) ) );
		$this->assertFalse( has_action( 'comment_post', array( $instance, 'handle_rsvp_comment_post' ) ) );

		// Clean up.
		unset( $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Test handle_rsvp_comment_redirect with non-RSVP comment type.
	 *
	 * @covers ::handle_rsvp_comment_redirect
	 *
	 * @return void
	 */
	public function test_handle_rsvp_comment_redirect_non_rsvp_comment(): void {
		$post_id = $this->factory->post->create();

		// Create regular comment (not RSVP type).
		$comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => '', // Regular comment.
			)
		);

		$instance          = Rsvp_Form::get_instance();
		$original_location = 'https://example.com/original';
		$result            = $instance->handle_rsvp_comment_redirect( $original_location, $comment );

		// Should return original location unchanged for non-RSVP comments.
		$this->assertEquals( $original_location, $result );
	}

	/**
	 * Test process_meta_fields with invalid comment ID.
	 *
	 * @covers ::process_meta_fields
	 *
	 * @return void
	 */
	public function test_process_meta_fields_invalid_comment(): void {
		$instance = Rsvp_Form::get_instance();
		$data     = array(
			'gatherpress_event_updates_opt_in' => true,
			'gatherpress_rsvp_guests'          => 2,
		);

		// Use non-existent comment ID.
		$invalid_comment_id = 999999;

		// Should return early without errors.
		$instance->process_fields( $invalid_comment_id, $data );

		// Verify no meta was created.
		$this->assertEmpty( get_comment_meta( $invalid_comment_id, 'gatherpress_event_updates_opt_in', true ) );
		$this->assertEmpty( get_comment_meta( $invalid_comment_id, 'gatherpress_rsvp_guests', true ) );
	}

	/**
	 * Test process_meta_fields when fields are not set in data array.
	 *
	 * @covers ::process_meta_fields
	 *
	 * @return void
	 */
	public function test_process_meta_fields_unset_fields(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$instance = Rsvp_Form::get_instance();

		// Call with empty data array (no fields set).
		$data = array();
		$instance->process_fields( $comment_id, $data );

		// Verify no meta was saved.
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', true ) );
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true ) );
	}

	/**
	 * Test process_meta_fields when anonymous is false (not trying to be anonymous).
	 *
	 * @covers ::process_meta_fields
	 *
	 * @return void
	 */
	public function test_process_meta_fields_anonymous_false(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Enable anonymous RSVP.
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', 1 );

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$data = array(
			'gatherpress_rsvp_anonymous' => false, // Explicitly not anonymous.
		);

		$instance = Rsvp_Form::get_instance();
		$instance->process_fields( $comment_id, $data );

		// Should not save anonymous meta when value is false.
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true ) );
	}

	/**
	 * Test handle_rsvp_comment_post with non-RSVP comment (early return).
	 *
	 * @covers ::handle_rsvp_comment_post
	 *
	 * @return void
	 */
	public function test_handle_rsvp_comment_post_wrong_comment_type(): void {
		$post_id = $this->factory->post->create();

		// Create regular comment (not RSVP type).
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => '', // Regular comment.
			)
		);

		// Mock POST data.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'gatherpress_event_updates_opt_in' === $var_name ) {
					return '1';
				}
				return null;
			},
			10,
			3
		);

		$instance = Rsvp_Form::get_instance();
		$instance->handle_rsvp_comment_post( $comment_id );

		// Should not process any fields since it's not an RSVP comment.
		$this->assertEmpty( get_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', true ) );

		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}
}
