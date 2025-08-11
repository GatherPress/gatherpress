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
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

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
			Utility::buffer_and_return( array( $instance, 'maybe_process_waiting_list' ), array( $post_id ) ),
			'Failed to assert method returns empty string.'
		);

		// Testing the logic of `check_waiting_list` happens in another test.
		// This is more for coverage with and without valid ID.
		$event_id = $this->factory->post->create( array( 'post_type' => 'gatherpress_event' ) );

		$this->assertEmpty(
			Utility::buffer_and_return( array( $instance, 'maybe_process_waiting_list' ), array( $event_id ) ),
			'Failed to assert method returns empty string.'
		);
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
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Mock filter_input for testing since it doesn't work with $_POST in test environment.
		$this->set_fn_return(
			'filter_input',
			function ( $type, $var_name ) {
				if ( INPUT_POST === $type && 'gatherpress_rsvp' === $var_name ) {
					return '1';
				}
				if ( INPUT_POST === $type && 'gatherpress_rsvp_form_id' === $var_name ) {
					return 'gatherpress_rsvp_12345';
				}
				return null;
			}
		);

		// Mock wp_get_referer to return our test referer URL.
		$GLOBALS['gatherpress_test_wp_get_referer_mock'] = function () {
			return 'https://example.com/events/test-event/';
		};

		// Simulate RSVP form submission environment.
		$_SERVER['REQUEST_METHOD']         = 'POST';
		$_SERVER['HTTP_REFERER']           = 'https://example.com/events/test-event/';
		$_SERVER['REQUEST_URI']            = '/wp-comments-post.php'; // Different from referer.
		$_POST['gatherpress_rsvp']         = '1';
		$_POST['gatherpress_rsvp_form_id'] = 'gatherpress_rsvp_12345';
		$_POST['_wp_http_referer']         = 'https://example.com/events/test-event/';

		// Trigger the form handling setup.
		$instance = Rsvp_Setup::get_instance();
		$instance->initialize_rsvp_form_handling();

		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => '',
			'comment_type'         => Rsvp::COMMENT_TYPE,
			'comment_author'       => 'Test User',
			'comment_author_email' => 'test@example.com',
		);

		$comment_id = wp_insert_comment( $comment_data );
		$comment    = get_comment( $comment_id );

		$original_location = 'https://example.com/wp-comments-post.php';
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$filtered_location = apply_filters( 'comment_post_redirect', $original_location, $comment );

		$this->assertStringContainsString( 'gatherpress_rsvp_success=true', $filtered_location );
		$this->assertStringContainsString( '#gatherpress_rsvp_12345', $filtered_location );
		$this->assertStringStartsWith( 'https://example.com/events/test-event/', $filtered_location );

		// Clean up.
		$this->unset_fn_return( 'filter_input' );
		unset( $GLOBALS['gatherpress_test_wp_get_referer_mock'] );
		unset( $_SERVER['REQUEST_METHOD'] );
		unset( $_SERVER['HTTP_REFERER'] );
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_POST['gatherpress_rsvp'] );
		unset( $_POST['gatherpress_rsvp_form_id'] );
		unset( $_POST['_wp_http_referer'] );
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
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Mock filter_input for testing since it doesn't work with $_POST in test environment.
		$this->set_fn_return(
			'filter_input',
			function ( $type, $var_name ) {
				if ( INPUT_POST === $type ) {
					switch ( $var_name ) {
						case 'gatherpress_rsvp':
							return '1';
						case 'gatherpress_rsvp_form_id':
							return null; // No form ID for this test.
						default:
							return null;
					}
				}
				return null;
			}
		);

		// Mock wp_get_referer to return our test referer URL.
		$GLOBALS['gatherpress_test_wp_get_referer_mock'] = function () {
			return 'https://example.com/events/test-event/';
		};

		// Simulate RSVP form submission environment.
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['HTTP_REFERER']   = 'https://example.com/events/test-event/';
		$_SERVER['REQUEST_URI']    = '/wp-comments-post.php'; // Different from referer.
		$_POST['gatherpress_rsvp'] = '1';
		$_POST['_wp_http_referer'] = 'https://example.com/events/test-event/';
		unset( $_POST['gatherpress_rsvp_form_id'] );

		// Trigger the form handling setup.
		$instance = Rsvp_Setup::get_instance();
		$instance->initialize_rsvp_form_handling();

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

		$this->assertStringContainsString( 'gatherpress_rsvp_success=true', $filtered_location );
		$this->assertStringNotContainsString( '#gatherpress_rsvp_', $filtered_location );
		$this->assertStringStartsWith( 'https://example.com/events/test-event/', $filtered_location );

		// Clean up.
		$this->unset_fn_return( 'filter_input' );
		unset( $GLOBALS['gatherpress_test_wp_get_referer_mock'] );
		unset( $_SERVER['REQUEST_METHOD'] );
		unset( $_SERVER['HTTP_REFERER'] );
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_POST['gatherpress_rsvp'] );
		unset( $_POST['_wp_http_referer'] );
	}
}
