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
				if ( INPUT_POST === $type && 'gatherpress_rsvp_guests' === $var_name ) {
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
}
