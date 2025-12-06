<?php
/**
 * Class file for Test_Rsvp_Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Setup;
use GatherPress\Core\Rsvp_Token;
use GatherPress\Tests\Base;

/**
 * Class Test_Rsvp_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp_Setup
 */
class Test_Rsvp_Setup extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
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
		$instance->register_taxonomy();

		$this->assertTrue( taxonomy_exists( Rsvp::TAXONOMY ) );
	}

	/**
	 * Coverage for adjust_comments_number method.
	 *
	 * @covers ::adjust_comments_number
	 *
	 * @return void
	 */
	public function test_adjust_comments_number(): void {
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

		$instance = Rsvp_Setup::get_instance();
		$result   = $instance->adjust_comments_number( 5, $post_id );

		$this->assertIsInt( $result );
	}

	/**
	 * Coverage for remove_rsvp_notification_emails method.
	 *
	 * @covers ::remove_rsvp_notification_emails
	 *
	 * @return void
	 */
	public function test_remove_rsvp_notification_emails(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an RSVP comment.
		$rsvp_comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Create a regular comment.
		$regular_comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		$instance = Rsvp_Setup::get_instance();
		$emails   = array( 'test@example.com', 'admin@example.com' );

		// For RSVP comments, should return empty array.
		$result = $instance->remove_rsvp_notification_emails( $emails, (string) $rsvp_comment_id );
		$this->assertSame( array(), $result );

		// For regular comments, should return original emails.
		$result = $instance->remove_rsvp_notification_emails( $emails, (string) $regular_comment_id );
		$this->assertSame( $emails, $result );
	}

	/**
	 * Coverage for maybe_process_waiting_list method.
	 *
	 * @covers ::maybe_process_waiting_list
	 *
	 * @return void
	 */
	public function test_maybe_process_waiting_list(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$instance = Rsvp_Setup::get_instance();
		$instance->maybe_process_waiting_list( $post_id );

		// This method should run without errors for event posts.
		$this->assertTrue( true );
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

		// Test with no logged in user and no token.
		$result = $instance->get_user_identifier();
		$this->assertSame( 0, $result );
	}

	/**
	 * Coverage for get_user_identifier method with logged-in user and token.
	 *
	 * @covers ::get_user_identifier
	 *
	 * @return void
	 */
	public function test_get_user_identifier_with_user_and_token(): void {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$instance = Rsvp_Setup::get_instance();
		$result   = $instance->get_user_identifier();

		$this->assertSame( $user_id, $result );

		wp_set_current_user( 0 );
	}

	/**
	 * Coverage for get_user_identifier method with token in URL.
	 *
	 * @covers ::get_user_identifier
	 *
	 * @return void
	 */
	public function test_get_user_identifier_with_token(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => 'test@example.com',
			)
		);

		// Create a proper token and mock it via filter.
		$token = new Rsvp_Token( $comment_id );
		$token->generate_token();
		$token_value  = $token->get_token();
		$token_string = sprintf( '%d_%s', $comment_id, $token_value );

		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) use ( $token_string ) {
				if ( INPUT_GET === $type && Rsvp_Token::NAME === $var_name ) {
					return $token_string;
				}
				return null;
			},
			10,
			3
		);

		$instance = Rsvp_Setup::get_instance();
		$result   = $instance->get_user_identifier();

		// Should return the email from the token.
		$this->assertSame( 'test@example.com', $result );

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for maybe_hide_rsvp_comment_content method.
	 *
	 * @covers ::maybe_hide_rsvp_comment_content
	 *
	 * @return void
	 */
	public function test_maybe_hide_rsvp_comment_content(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$rsvp_comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
				'comment_content' => 'Private RSVP note',
			)
		);

		$regular_comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_content' => 'Regular comment',
			)
		);

		$instance = Rsvp_Setup::get_instance();

		// For RSVP comments, content should be hidden for non-moderators.
		$result = $instance->maybe_hide_rsvp_comment_content( 'Private RSVP note', $rsvp_comment );
		$this->assertSame( '', $result );

		// For regular comments, content should be preserved.
		$result = $instance->maybe_hide_rsvp_comment_content( 'Regular comment', $regular_comment );
		$this->assertSame( 'Regular comment', $result );
	}

	/**
	 * Coverage for handle_rsvp_token method.
	 *
	 * @covers ::handle_rsvp_token
	 *
	 * @return void
	 */
	public function test_handle_rsvp_token(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an unapproved RSVP comment.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => 'test@example.com',
				'comment_approved'     => 0,
			)
		);

		// Create a proper token and mock it via filter.
		$token = new Rsvp_Token( $comment_id );
		$token->generate_token();
		$token_value  = $token->get_token();
		$token_string = sprintf( '%d_%s', $comment_id, $token_value );

		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) use ( $token_string ) {
				if ( INPUT_GET === $type && Rsvp_Token::NAME === $var_name ) {
					return $token_string;
				}
				return null;
			},
			10,
			3
		);

		// Verify comment is initially unapproved.
		$comment = get_comment( $comment_id );
		$this->assertEquals( '0', $comment->comment_approved );

		// Call the method (it should process the token and approve the comment).
		$instance = Rsvp_Setup::get_instance();
		$instance->handle_rsvp_token();

		// Verify that the comment was approved based on the token.
		$comment = get_comment( $comment_id );
		$this->assertEquals( '1', $comment->comment_approved );

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for handle_rsvp_token method with no token.
	 *
	 * @covers ::handle_rsvp_token
	 *
	 * @return void
	 */
	public function test_handle_rsvp_token_with_no_token(): void {
		$instance = Rsvp_Setup::get_instance();

		// Should not throw error when no token is present.
		$instance->handle_rsvp_token();
		$this->assertTrue( true );
	}

	/**
	 * Coverage for adjust_comments_number with non-event post.
	 *
	 * @covers ::adjust_comments_number
	 *
	 * @return void
	 */
	public function test_adjust_comments_number_non_event_post(): void {
		$post_id = $this->factory->post->create();

		$this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		$instance = Rsvp_Setup::get_instance();
		$result   = $instance->adjust_comments_number( 5, $post_id );

		// Should return original count for non-event posts.
		$this->assertSame( 5, $result );
	}

	/**
	 * Coverage for maybe_process_waiting_list with non-event post.
	 *
	 * @covers ::maybe_process_waiting_list
	 *
	 * @return void
	 */
	public function test_maybe_process_waiting_list_non_event_post(): void {
		$post_id = $this->factory->post->create();

		$instance = Rsvp_Setup::get_instance();

		// Should not process waiting list for non-event posts.
		$instance->maybe_process_waiting_list( $post_id );
		$this->assertTrue( true );
	}

	/**
	 * Coverage for maybe_hide_rsvp_comment_content with null comment.
	 *
	 * @covers ::maybe_hide_rsvp_comment_content
	 *
	 * @return void
	 */
	public function test_maybe_hide_rsvp_comment_content_null_comment(): void {
		$instance = Rsvp_Setup::get_instance();

		$result = $instance->maybe_hide_rsvp_comment_content( 'Test content', null );
		$this->assertSame( 'Test content', $result );
	}

	/**
	 * Coverage for maybe_hide_rsvp_comment_content with moderator capability.
	 *
	 * @covers ::maybe_hide_rsvp_comment_content
	 *
	 * @return void
	 */
	public function test_maybe_hide_rsvp_comment_content_moderator(): void {
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$rsvp_comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
				'comment_content' => 'Private RSVP note',
			)
		);

		$instance = Rsvp_Setup::get_instance();

		// For RSVP comments, content should be visible for moderators.
		$result = $instance->maybe_hide_rsvp_comment_content( 'Private RSVP note', $rsvp_comment );
		$this->assertSame( 'Private RSVP note', $result );

		wp_set_current_user( 0 );
	}

	/**
	 * Coverage for set_rsvp_screen_options method.
	 *
	 * @covers ::set_rsvp_screen_options
	 *
	 * @return void
	 */
	public function test_set_rsvp_screen_options(): void {
		$instance = Rsvp_Setup::get_instance();

		// Test with correct option name.
		$result = $instance->set_rsvp_screen_options( false, sprintf( '%s_per_page', Rsvp::COMMENT_TYPE ), 20 );
		$this->assertSame( 20, $result );

		// Test with incorrect option name.
		$result = $instance->set_rsvp_screen_options( false, 'other_option', 20 );
		$this->assertSame( false, $result );
	}

	/**
	 * Coverage for highlight_admin_menu method.
	 *
	 * @covers ::highlight_admin_menu
	 *
	 * @return void
	 */
	public function test_highlight_admin_menu(): void {
		global $plugin_page;

		$instance = Rsvp_Setup::get_instance();

		// Test with RSVP page.
		$plugin_page = Rsvp::COMMENT_TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$result      = $instance->highlight_admin_menu( 'index.php' );

		$this->assertSame( sprintf( 'edit.php?post_type=%s', Event::POST_TYPE ), $result );
		$this->assertTrue( has_filter( 'submenu_file', array( $instance, 'set_submenu_file' ) ) !== false );

		// Clean up.
		remove_filter( 'submenu_file', array( $instance, 'set_submenu_file' ) );
		$plugin_page = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Coverage for highlight_admin_menu method with different page.
	 *
	 * @covers ::highlight_admin_menu
	 *
	 * @return void
	 */
	public function test_highlight_admin_menu_different_page(): void {
		global $plugin_page;

		$instance = Rsvp_Setup::get_instance();

		// Test with different page.
		$plugin_page = 'some_other_page'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$result      = $instance->highlight_admin_menu( 'index.php' );

		$this->assertSame( 'index.php', $result );

		$plugin_page = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Coverage for set_submenu_file method.
	 *
	 * @covers ::set_submenu_file
	 *
	 * @return void
	 */
	public function test_set_submenu_file(): void {
		$instance = Rsvp_Setup::get_instance();

		$result = $instance->set_submenu_file();
		$this->assertSame( Rsvp::COMMENT_TYPE, $result );
	}
}
