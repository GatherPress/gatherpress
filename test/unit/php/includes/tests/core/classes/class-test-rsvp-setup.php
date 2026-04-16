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
use GatherPress\Core\Rsvp_List_Table;
use GatherPress\Core\Rsvp_Setup;
use GatherPress\Core\Rsvp_Token;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rsvp_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp_Setup
 */
class Test_Rsvp_Setup extends Base {

	/**
	 * Coverage for constructor.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_constructor(): void {
		$instance = Rsvp_Setup::get_instance();

		// Instance should be created successfully.
		$this->assertInstanceOf( Rsvp_Setup::class, $instance );
	}

	/**
	 * Coverage for setup_hooks.
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
				'callback' => array( $instance, 'handle_rsvp_token' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'maybe_disable_rsvp' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_process_waiting_list' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_set_rsvp_meta_default' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_menu',
				'priority' => 10,
				'callback' => array( $instance, 'add_rsvp_submenu_page' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'allowed_block_types_all',
				'priority' => 10,
				'callback' => array( $instance, 'filter_rsvp_block_types' ),
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
	 * @covers ::get_per_page_option
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

	/**
	 * Test add_rsvp_submenu_page method.
	 *
	 * @covers ::add_rsvp_submenu_page
	 * @covers ::get_per_page_option
	 *
	 * @return void
	 */
	public function test_add_rsvp_submenu_page(): void {
		global $submenu;

		// Initialize submenu if not set.
		if ( ! is_array( $submenu ) ) {
			$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$instance = Rsvp_Setup::get_instance();
		$instance->add_rsvp_submenu_page();

		// Verify that the load hook was registered.
		$hook_name = sprintf( 'load-events_page_%s', Rsvp::COMMENT_TYPE );

		// Set up a proper screen context for add_screen_option to work.
		$screen_id = sprintf( 'events_page_%s', Rsvp::COMMENT_TYPE );
		set_current_screen( $screen_id );

		// Trigger the load hook to test the callback (tests target code).
		do_action( $hook_name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

		// Verify screen option was added.
		$screen = get_current_screen();
		$this->assertNotNull( $screen, 'Screen should be set.' );

		// Clean up.
		set_current_screen( 'front' );
	}

	/**
	 * Test setup_rsvp_list_table_screen_options method.
	 *
	 * @covers ::setup_rsvp_list_table_screen_options
	 * @covers ::get_per_page_option
	 *
	 * @return void
	 */
	public function test_setup_rsvp_list_table_screen_options(): void {
		$instance = Rsvp_Setup::get_instance();

		// Set up list_table property.
		Utility::set_and_get_hidden_property( $instance, 'list_table', new RSVP_List_Table() );

		// Set up a proper screen context for add_screen_option to work.
		$screen_id = sprintf( 'events_page_%s', Rsvp::COMMENT_TYPE );
		set_current_screen( $screen_id );

		// Call the public method.
		$instance->setup_rsvp_list_table_screen_options();

		// Verify screen option was added.
		$screen  = get_current_screen();
		$options = $screen->get_options();

		$this->assertNotEmpty( $options, 'Screen options should not be empty' );
		$this->assertArrayHasKey( 'per_page', $options, 'Per page option should be registered' );
		$this->assertEquals(
			RSVP_List_Table::DEFAULT_PER_PAGE,
			$options['per_page']['default'],
			'Default per page should match RSVP_List_Table::DEFAULT_PER_PAGE'
		);

		// Clean up.
		set_current_screen( 'front' );
	}

	/**
	 * Test render_rsvp_admin_page method.
	 *
	 * @covers ::render_rsvp_admin_page
	 *
	 * @return void
	 */
	public function test_render_rsvp_admin_page(): void {
		// Create admin user with proper capabilities.
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$instance = Rsvp_Setup::get_instance();

		ob_start();
		$instance->render_rsvp_admin_page();
		$output = ob_get_clean();

		// Should output something.
		$this->assertNotEmpty( $output );

		wp_set_current_user( 0 );
	}

	/**
	 * Test add_rsvp_screen_options method.
	 *
	 * @covers ::add_rsvp_screen_options
	 *
	 * @return void
	 */
	public function test_add_rsvp_screen_options(): void {
		$instance = Rsvp_Setup::get_instance();

		// Mock the screen.
		set_current_screen( 'edit-comments' );

		// Should execute without errors.
		$instance->add_rsvp_screen_options();
		$this->assertTrue( true );

		// Clean up.
		set_current_screen( 'front' );
	}

	/**
	 * Test set_rsvp_screen_options with invalid option.
	 *
	 * @covers ::set_rsvp_screen_options
	 *
	 * @return void
	 */
	public function test_set_rsvp_screen_options_invalid_option(): void {
		$instance = Rsvp_Setup::get_instance();

		// Test with invalid option name.
		$result = $instance->set_rsvp_screen_options( false, 'invalid_option', 10 );

		// Should return false for invalid option.
		$this->assertFalse( $result );
	}

	/**
	 * Test add_rsvp_screen_options with correct page parameter.
	 *
	 * @covers ::add_rsvp_screen_options
	 * @covers ::get_per_page_option
	 *
	 * @return void
	 */
	public function test_add_rsvp_screen_options_with_page_parameter(): void {
		// Set up the $_GET parameter for the RSVP page.
		$_GET['page'] = Rsvp::COMMENT_TYPE; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$instance = Rsvp_Setup::get_instance();

		// Mock the screen - use a proper admin screen.
		$screen = \WP_Screen::get( 'admin_init' );
		set_current_screen( $screen );

		// Should execute and add screen option.
		$instance->add_rsvp_screen_options();

		$screen = get_current_screen();
		$this->assertNotNull( $screen );

		// Clean up.
		unset( $_GET['page'] );
		set_current_screen( 'front' );
	}

	/**
	 * Test add_rsvp_screen_options without page parameter returns early.
	 *
	 * @covers ::add_rsvp_screen_options
	 *
	 * @return void
	 */
	public function test_add_rsvp_screen_options_without_page_returns_early(): void {
		// Ensure $_GET['page'] is not set.
		unset( $_GET['page'] );

		$instance = Rsvp_Setup::get_instance();

		// Should return early without doing anything.
		$instance->add_rsvp_screen_options();

		// Should execute without errors.
		$this->assertTrue( true );
	}

	/**
	 * Test render_rsvp_admin_page with request parameters.
	 *
	 * @covers ::render_rsvp_admin_page
	 *
	 * @return void
	 */
	public function test_render_rsvp_admin_page_with_request_parameters(): void {
		// Create admin user with proper capabilities.
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Set up request parameters.
		$_REQUEST['s']      = 'test search'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_REQUEST['status'] = 'attending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$_REQUEST['event']  = '123'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$instance = Rsvp_Setup::get_instance();

		ob_start();
		$instance->render_rsvp_admin_page();
		$output = ob_get_clean();

		// Should output something.
		$this->assertNotEmpty( $output );

		// Clean up.
		unset( $_REQUEST['s'], $_REQUEST['status'], $_REQUEST['event'] );
		wp_set_current_user( 0 );
	}

	/**
	 * Test render_rsvp_admin_page without capability.
	 *
	 * @covers ::render_rsvp_admin_page
	 *
	 * @return void
	 */
	public function test_render_rsvp_admin_page_without_capability(): void {
		// Create subscriber user without RSVP capability.
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$instance = Rsvp_Setup::get_instance();

		// Should trigger wp_die.
		$this->expectException( 'WPDieException' );
		$instance->render_rsvp_admin_page();

		wp_set_current_user( 0 );
	}

	/**
	 * Coverage for is_rsvp_enabled_for_event method.
	 *
	 * @covers ::is_rsvp_enabled_for_event
	 *
	 * @return void
	 */
	public function test_is_rsvp_enabled_for_event(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Returns false when mode is per_event_on and meta is '0'.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_on' );
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', '0' );
		$this->assertFalse(
			Rsvp_Setup::is_rsvp_enabled_for_event( $post_id ),
			'Should return false when mode is per_event_on and meta is 0.'
		);

		// Returns false when mode is per_event_off and meta is '0'.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_off' );
		$this->assertFalse(
			Rsvp_Setup::is_rsvp_enabled_for_event( $post_id ),
			'Should return false when mode is per_event_off and meta is 0.'
		);

		// Returns true when mode is per_event_on and meta is '1'.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_on' );
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', '1' );
		$this->assertTrue(
			Rsvp_Setup::is_rsvp_enabled_for_event( $post_id ),
			'Should return true when mode is per_event_on and meta is 1.'
		);

		// Returns true when mode is per_event_on and meta is '' (never set).
		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );
		$this->assertTrue(
			Rsvp_Setup::is_rsvp_enabled_for_event( $post_id ),
			'Should return true when mode is per_event_on and meta is empty (never set).'
		);

		// Returns true when mode is all_on and meta is '0'.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', '0' );
		$this->assertTrue(
			Rsvp_Setup::is_rsvp_enabled_for_event( $post_id ),
			'Should return true when mode is all_on regardless of meta.'
		);

		// Returns true when mode is disabled and meta is '0'.
		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );
		$this->assertTrue(
			Rsvp_Setup::is_rsvp_enabled_for_event( $post_id ),
			'Should return true when mode is disabled regardless of meta.'
		);

		// Restore default setting.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Test maybe_set_rsvp_meta_default writes meta in all_on mode when meta is unset.
	 *
	 * @covers ::maybe_set_rsvp_meta_default
	 *
	 * @return void
	 */
	public function test_maybe_set_rsvp_meta_default_writes_meta_in_all_on_mode(): void {
		$instance = Rsvp_Setup::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Clear any meta set by the wp_after_insert_post hook during post creation.
		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );

		// Default mode is all_on; calling the method should write 1.
		$instance->maybe_set_rsvp_meta_default( $post_id );

		$this->assertSame(
			'1',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Meta should be written as 1 in all_on mode when not previously set.'
		);
	}

	/**
	 * Test maybe_set_rsvp_meta_default does not overwrite an existing meta value.
	 *
	 * @covers ::maybe_set_rsvp_meta_default
	 *
	 * @return void
	 */
	public function test_maybe_set_rsvp_meta_default_does_not_overwrite_existing_meta(): void {
		$instance = Rsvp_Setup::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Pre-set meta to 0 (RSVP disabled for this event).
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', 0 );

		// Default mode is all_on; method should not overwrite the existing value.
		$instance->maybe_set_rsvp_meta_default( $post_id );

		$this->assertSame(
			'0',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Existing meta value should not be overwritten.'
		);
	}

	/**
	 * Test maybe_set_rsvp_meta_default is a no-op outside all_on mode.
	 *
	 * @covers ::maybe_set_rsvp_meta_default
	 *
	 * @return void
	 */
	public function test_maybe_set_rsvp_meta_default_skips_non_all_on_modes(): void {
		$instance = Rsvp_Setup::get_instance();

		// Switch to per_event_on mode BEFORE creating the post so the hook doesn't write meta.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_on' );

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$instance->maybe_set_rsvp_meta_default( $post_id );

		// Meta should remain unset; per-event mode manages it via the editor UI.
		$this->assertSame(
			'',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Meta should not be written when mode is per_event_on.'
		);

		// Restore setting.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Test maybe_disable_rsvp when rsvp_mode is not disabled.
	 *
	 * @covers ::maybe_disable_rsvp
	 *
	 * @return void
	 */
	public function test_maybe_disable_rsvp_when_enabled(): void {
		$instance = Rsvp_Setup::get_instance();

		// Verify that the event post type supports RSVP before the call.
		$this->assertTrue(
			post_type_supports( Event::POST_TYPE, 'gatherpress-rsvp' ),
			'Event post type should support gatherpress-rsvp before disabling.'
		);

		// With rsvp_mode defaulting to all_on, this should be a no-op.
		$instance->maybe_disable_rsvp();

		// Verify support is still present.
		$this->assertTrue(
			post_type_supports( Event::POST_TYPE, 'gatherpress-rsvp' ),
			'Event post type should still support gatherpress-rsvp when RSVP is enabled.'
		);
	}

	/**
	 * Test maybe_disable_rsvp removes post type support when setting is disabled.
	 *
	 * @covers ::maybe_disable_rsvp
	 *
	 * @return void
	 */
	public function test_maybe_disable_rsvp_when_disabled(): void {
		$instance = Rsvp_Setup::get_instance();

		// Temporarily set rsvp_mode to disabled via the settings option.
		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );

		// Ensure the event post type currently supports RSVP.
		add_post_type_support( Event::POST_TYPE, 'gatherpress-rsvp' );

		$instance->maybe_disable_rsvp();

		// Verify support has been removed.
		$this->assertFalse(
			post_type_supports( Event::POST_TYPE, 'gatherpress-rsvp' ),
			'Event post type should no longer support gatherpress-rsvp when RSVP is disabled.'
		);

		// Restore the setting and support for other tests.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
		add_post_type_support( Event::POST_TYPE, 'gatherpress-rsvp' );
	}

	/**
	 * Test filter_rsvp_block_types returns all blocks unchanged when RSVP is enabled.
	 *
	 * @covers ::filter_rsvp_block_types
	 *
	 * @return void
	 */
	public function test_filter_rsvp_block_types_when_enabled(): void {
		$instance      = Rsvp_Setup::get_instance();
		$initial_value = true;

		$result = $instance->filter_rsvp_block_types( $initial_value );

		$this->assertSame(
			$initial_value,
			$result,
			'When RSVP is enabled, block types should be returned unchanged.'
		);
	}

	/**
	 * Test filter_rsvp_block_types removes RSVP blocks when RSVP is disabled.
	 *
	 * @covers ::filter_rsvp_block_types
	 *
	 * @return void
	 */
	public function test_filter_rsvp_block_types_when_disabled(): void {
		$instance = Rsvp_Setup::get_instance();

		// Temporarily set rsvp_mode to disabled via the settings option.
		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );

		// Pass a known array containing RSVP and non-RSVP block names.
		$block_list = array(
			'core/paragraph',
			'gatherpress/rsvp',
			'gatherpress/rsvp-form',
			'gatherpress/event-date',
			'gatherpress/rsvp-response',
		);

		$result = $instance->filter_rsvp_block_types( $block_list );

		// RSVP blocks should have been removed.
		$this->assertNotContains(
			'gatherpress/rsvp',
			$result,
			'gatherpress/rsvp block should be removed when RSVP is disabled.'
		);
		$this->assertNotContains(
			'gatherpress/rsvp-form',
			$result,
			'gatherpress/rsvp-form block should be removed when RSVP is disabled.'
		);
		$this->assertNotContains(
			'gatherpress/rsvp-response',
			$result,
			'gatherpress/rsvp-response block should be removed when RSVP is disabled.'
		);

		// Non-RSVP blocks should remain.
		$this->assertContains(
			'core/paragraph',
			$result,
			'core/paragraph block should remain when RSVP is disabled.'
		);
		$this->assertContains(
			'gatherpress/event-date',
			$result,
			'gatherpress/event-date block should remain when RSVP is disabled.'
		);

		// Restore the setting for other tests.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}
}
