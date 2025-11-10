<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp_Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Setup;
use GatherPress\Core\Rsvp_Token;
use GatherPress\Core\Utility;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility as PMC_Utility;

/**
 * Class Test_Rsvp_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp_Setup
 */
class Test_Rsvp_Setup extends Base {
	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Rsvp_Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_taxonomy' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'initialize_rsvp_form_handling' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'handle_rsvp_token' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_process_waiting_list' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_menu',
				'priority' => 10,
				'callback' => array( $instance, 'add_rsvp_submenu_page' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'comment_notification_recipients',
				'priority' => 10,
				'callback' => array( $instance, 'remove_rsvp_notification_emails' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'set_screen_option_%s_per_page', Rsvp::COMMENT_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'set_rsvp_screen_options' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'parent_file',
				'priority' => 10,
				'callback' => array( $instance, 'highlight_admin_menu' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'get_comments_number',
				'priority' => 10,
				'callback' => array( $instance, 'adjust_comments_number' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'comment_text',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_hide_rsvp_comment_content' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}


	/**
	 * Coverage for register_taxonomy method.
	 *
	 * @covers ::register_taxonomy
	 *
	 * @return void
	 */
	public function test_register_taxonomy(): void {
		$instance = Rsvp_Setup::get_instance();

		unregister_taxonomy( Rsvp::TAXONOMY );

		$this->assertFalse( taxonomy_exists( Rsvp::TAXONOMY ), 'Failed to assert that taxonomy does not exist.' );

		$instance->register_taxonomy();

		$this->assertTrue( taxonomy_exists( Rsvp::TAXONOMY ), 'Failed to assert that taxonomy exists.' );
	}

	/**
	 * Coverage for adjust_comments_number method.
	 *
	 * @covers ::adjust_comments_number
	 *
	 * @return void
	 */
	public function test_adjust_comments_number(): void {
		$instance = Rsvp_Setup::get_instance();
		$post     = $this->mock->post()->get();
		$user     = $this->mock->user()->get();

		$this->assertEquals(
			2,
			$instance->adjust_comments_number( 2, $post->ID ),
			'Failed to assert the comments do not equal 2.'
		);

		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'user_id'         => $user->ID,
				'comment_content' => 'Test comment',
			)
		);

		wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
				'user_id'         => $user->ID,
			)
		);

		$this->assertEquals(
			1,
			$instance->adjust_comments_number( 2, $event->ID ),
			'Failed to assert the comments do not equal 1.'
		);
	}

	/**
	 * Tests remove_rsvp_notification_emails method.
	 *
	 * Verifies that RSVP comments don't send notification emails
	 * while regular comments pass through unchanged.
	 *
	 * @since 1.0.0
	 * @covers ::remove_rsvp_notification_emails
	 *
	 * @return void
	 */
	public function test_remove_rsvp_notification_emails(): void {
		$instance = Rsvp_Setup::get_instance();
		$event_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Test with regular comment - should return emails unchanged.
		$regular_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $event_id,
				'comment_content' => 'This is a regular comment',
				'comment_type'    => '',
			)
		);

		$test_emails = array( 'test@example.com', 'admin@example.com' );
		$result      = $instance->remove_rsvp_notification_emails( $test_emails, (string) $regular_comment_id );

		$this->assertEquals(
			$test_emails,
			$result,
			'Failed to assert that regular comment emails are preserved.'
		);

		// Test with RSVP comment - should return empty array.
		$rsvp_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $event_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
				'comment_content' => '',
			)
		);

		$result = $instance->remove_rsvp_notification_emails( $test_emails, (string) $rsvp_comment_id );

		$this->assertEmpty(
			$result,
			'Failed to assert that RSVP comment emails are removed.'
		);

		// Test with another custom comment type - should return emails unchanged.
		$custom_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $event_id,
				'comment_type'    => 'custom_type',
				'comment_content' => '',
			)
		);

		$result = $instance->remove_rsvp_notification_emails( $test_emails, (string) $custom_comment_id );

		$this->assertEquals(
			$test_emails,
			$result,
			'Failed to assert that custom comment type emails are preserved.'
		);

		// Test with empty emails array for RSVP - should still return empty.
		$result = $instance->remove_rsvp_notification_emails( array(), (string) $rsvp_comment_id );

		$this->assertEmpty(
			$result,
			'Failed to assert that empty array stays empty for RSVP comments.'
		);
	}

	/**
	 * Coverage for maybe_process_waiting_list method.
	 *
	 * @covers ::maybe_process_waiting_list
	 *
	 * @return void
	 */
	public function test_maybe_process_waiting_list(): void {
		$instance = Rsvp_Setup::get_instance();
		$post_id  = $this->factory->post->create();

		$this->assertEmpty(
			PMC_Utility::buffer_and_return( array( $instance, 'maybe_process_waiting_list' ), array( $post_id ) ),
			'Failed to assert method returns empty string.'
		);

		// Testing the logic of `check_waiting_list` happens in another test.
		// This is more for coverage with and without valid ID.
		$event_id = $this->factory->post->create( array( 'post_type' => 'gatherpress_event' ) );

		$this->assertEmpty(
			PMC_Utility::buffer_and_return( array( $instance, 'maybe_process_waiting_list' ), array( $event_id ) ),
			'Failed to assert method returns empty string.'
		);
	}


	/**
	 * Tests that comment_post_redirect filter ignores non-RSVP comments.
	 *
	 * Verifies that regular comments are not affected by the RSVP redirect logic.
	 *
	 * @since 1.0.0
	 * @covers ::initialize_rsvp_form_handling
	 *
	 * @return void
	 */
	public function test_comment_post_redirect_ignores_non_rsvp_comments(): void {
		$post_id = $this->factory()->post->create();

		$comment_data = array(
			'comment_post_ID' => $post_id,
			'comment_content' => 'This is a regular comment',
			'comment_type'    => '',
		);

		$comment_id = wp_insert_comment( $comment_data );
		$comment    = get_comment( $comment_id );

		$original_location = 'https://example.com/wp-comments-post.php';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$filtered_location = apply_filters( 'comment_post_redirect', $original_location, $comment );

		$this->assertEquals( $original_location, $filtered_location );
	}

	/**
	 * Tests comment_post_redirect filter without referer.
	 *
	 * Verifies that when there's no HTTP_REFERER, the filter returns the original location.
	 *
	 * @since 1.0.0
	 * @covers ::initialize_rsvp_form_handling
	 *
	 * @return void
	 */
	public function test_comment_post_redirect_without_referer(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		unset( $_SERVER['HTTP_REFERER'] );

		$comment_data = array(
			'comment_post_ID' => $post_id,
			'comment_content' => '',
			'comment_type'    => Rsvp::COMMENT_TYPE,
		);

		$comment_id = wp_insert_comment( $comment_data );
		$comment    = get_comment( $comment_id );

		$original_location = 'https://example.com/wp-comments-post.php';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$filtered_location = apply_filters( 'comment_post_redirect', $original_location, $comment );

		$this->assertEquals( $original_location, $filtered_location );
	}


	/**
	 * Coverage for get_user_identifier method.
	 *
	 * @covers ::get_user_identifier
	 *
	 * @return void
	 */
	public function test_get_user_identifier(): void {
		$instance = Rsvp_Setup::get_instance();

		// Test with logged-in user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$identifier = $instance->get_user_identifier();
		$this->assertEquals( $user_id, $identifier );

		// Test with no logged-in user and no token.
		wp_set_current_user( 0 );
		$identifier = $instance->get_user_identifier();
		$this->assertEquals( 0, $identifier );

		// Clean up.
		wp_set_current_user( 0 );
	}

	/**
	 * Tests that comment_post_redirect filter redirects to referer for RSVP comments.
	 *
	 * Verifies that when an RSVP comment is submitted, the user is redirected
	 * back to the page they came from with success parameters.
	 *
	 * @since 1.0.0
	 * @covers ::initialize_rsvp_form_handling
	 *
	 * @return void
	 */
	public function test_comment_post_redirect_for_rsvp_comment(): void {
		// Set up environment to simulate RSVP form submission.
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// Initialize the class and specifically set up the form handling.
		$instance = Rsvp_Setup::get_instance();

		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_data = array(
			'comment_post_ID' => $post_id,
			'comment_content' => '',
			'comment_type'    => Rsvp::COMMENT_TYPE,
		);

		$comment_id = wp_insert_comment( $comment_data );
		$comment    = get_comment( $comment_id );

		// Mock HTTP_REFERER and form schema ID.
		$original_referer        = 'https://example.com/event-page/';
		$_SERVER['HTTP_REFERER'] = $original_referer;

		// Mock wp_get_referer() to return our test referer.
		add_filter(
			'gatherpress_pre_get_wp_referer',
			static function () use ( $original_referer ) {
				return $original_referer;
			}
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_id' === $var_name ) {
					return 'form_0';
				}
				if ( INPUT_POST === $type && Rsvp::COMMENT_TYPE === $var_name ) {
					return '1'; // Required for is_rsvp_form_submission().
				}
				return null;
			},
			10,
			3
		);

		// Now initialize the form handling (after setting up mocks).
		$instance->initialize_rsvp_form_handling();

		$original_location = 'https://example.com/wp-comments-post.php';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$filtered_location = apply_filters( 'comment_post_redirect', $original_location, $comment );

		// Should redirect to referer with success parameter and form fragment.
		$expected_url = add_query_arg( 'gatherpress_rsvp_success', 'true', $original_referer ) . '#form_0';

		$this->assertEquals( $expected_url, $filtered_location );

		// Clean up.
		unset( $_SERVER['HTTP_REFERER'] );
		unset( $_SERVER['REQUEST_METHOD'] );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
		remove_all_filters( 'gatherpress_pre_get_wp_referer' );
	}

	/**
	 * Tests comment_post_redirect filter without form ID.
	 *
	 * Verifies that the redirect works without a form ID, just adding the success parameter.
	 *
	 * @since 1.0.0
	 * @covers ::initialize_rsvp_form_handling
	 *
	 * @return void
	 */
	public function test_comment_post_redirect_without_form_id(): void {
		// Set up environment to simulate RSVP form submission.
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// Initialize the class and specifically set up the form handling.
		$instance = Rsvp_Setup::get_instance();

		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_data = array(
			'comment_post_ID' => $post_id,
			'comment_content' => '',
			'comment_type'    => Rsvp::COMMENT_TYPE,
		);

		$comment_id = wp_insert_comment( $comment_data );
		$comment    = get_comment( $comment_id );

		// Mock HTTP_REFERER without form schema ID.
		$original_referer        = 'https://example.com/event-page/';
		$_SERVER['HTTP_REFERER'] = $original_referer;

		// Mock wp_get_referer() to return our test referer.
		add_filter(
			'gatherpress_pre_get_wp_referer',
			static function () use ( $original_referer ) {
				return $original_referer;
			}
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) {
				if ( INPUT_POST === $type && Rsvp::COMMENT_TYPE === $var_name ) {
					return '1'; // Required for is_rsvp_form_submission().
				}
				return null; // No form schema ID.
			},
			10,
			3
		);

		// Now initialize the form handling (after setting up mocks).
		$instance->initialize_rsvp_form_handling();

		$original_location = 'https://example.com/wp-comments-post.php';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$filtered_location = apply_filters( 'comment_post_redirect', $original_location, $comment );

		// Should redirect to referer with only success parameter.
		$expected_url = add_query_arg( 'gatherpress_rsvp_success', 'true', $original_referer );
		$this->assertEquals( $expected_url, $filtered_location );

		// Clean up.
		unset( $_SERVER['HTTP_REFERER'] );
		unset( $_SERVER['REQUEST_METHOD'] );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
		remove_all_filters( 'gatherpress_pre_get_wp_referer' );
	}

	/**
	 * Coverage for handle_rsvp_token method.
	 *
	 * @covers ::handle_rsvp_token
	 *
	 * @return void
	 */
	public function test_handle_rsvp_token(): void {
		$user_id = $this->factory->user->create();
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an RSVP comment that's initially unapproved.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'user_id'          => $user_id,
				'comment_approved' => '0',
			)
		);

		// Generate a token.
		$token_instance = new Rsvp_Token( $comment_id );
		$token_instance->generate_token();
		$token        = $token_instance->get_token();
		$token_string = sprintf( '%d_%s', $comment_id, $token );

		// Mock the token parameter.
		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $token_string ) {
				if ( INPUT_GET === $type && Rsvp_Token::NAME === $var_name ) {
					return $token_string;
				}
				return null;
			},
			10,
			3
		);

		$instance = Rsvp_Setup::get_instance();

		// Verify comment is initially unapproved.
		$comment = get_comment( $comment_id );
		$this->assertEquals( '0', $comment->comment_approved );

		// Call the method (it should process the token and approve the comment).
		$instance->handle_rsvp_token();

		// Verify that the comment was approved based on the token.
		$comment = get_comment( $comment_id );
		$this->assertEquals( '1', $comment->comment_approved );

		// Clean up.
		wp_set_current_user( 0 );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for get_user_identifier with logged-in user and valid token.
	 *
	 * Tests that token takes precedence over logged-in user when present.
	 *
	 * @covers ::get_user_identifier
	 *
	 * @return void
	 */
	public function test_get_user_identifier_with_user_and_token(): void {
		$logged_in_user_id = $this->factory->user->create();
		$token_user_id     = $this->factory->user->create();
		$post_id           = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Set a logged-in user.
		wp_set_current_user( $logged_in_user_id );

		// Get the token user's email for comparison.
		$token_user       = get_user_by( 'id', $token_user_id );
		$token_user_email = $token_user->user_email;

		// Create an RSVP comment for a different user.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'user_id'              => $token_user_id,
				'comment_author_email' => $token_user_email,
			)
		);

		// Generate a token.
		$token_instance = new Rsvp_Token( $comment_id );
		$token_instance->generate_token();
		$token        = $token_instance->get_token();
		$token_string = sprintf( '%d_%s', $comment_id, $token );

		// Mock the token parameter.
		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $token_string ) {
				if ( INPUT_GET === $type && Rsvp_Token::NAME === $var_name ) {
					return $token_string;
				}
				return null;
			},
			10,
			3
		);

		$instance = Rsvp_Setup::get_instance();

		// The identifier should return the token user's email, not the logged-in user ID.
		$identifier = $instance->get_user_identifier();
		$this->assertEquals( $token_user_email, $identifier );

		// Clean up.
		wp_set_current_user( 0 );
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests Utility::get_http_input method with mocked data.
	 *
	 * Verifies that the wrapper correctly retrieves and sanitizes HTTP input.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_http_input_wrapper(): void {
		// Set up mock data using pre_ filter.
		$mock_data = array(
			INPUT_POST => array(
				'test_field'  => 'test_value',
				'email_field' => 'test@example.com',
			),
			INPUT_GET  => array(
				'success' => 'true',
			),
		);

		// Enable mocking via pre_ filter.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) use ( $mock_data ) {
				return $mock_data[ $type ][ $var_name ] ?? null;
			},
			10,
			3
		);

		// Test text sanitization (default).
		$result = Utility::get_http_input( INPUT_POST, 'test_field' );
		$this->assertEquals( 'test_value', $result );

		// Test email sanitization.
		$result = Utility::get_http_input( INPUT_POST, 'email_field', 'sanitize_email' );
		$this->assertEquals( 'test@example.com', $result );

		// Test GET parameter.
		$result = Utility::get_http_input( INPUT_GET, 'success' );
		$this->assertEquals( 'true', $result );

		// Test non-existent field.
		$result = Utility::get_http_input( INPUT_POST, 'nonexistent' );
		$this->assertEquals( '', $result );

		// Clean up filters.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}
}
