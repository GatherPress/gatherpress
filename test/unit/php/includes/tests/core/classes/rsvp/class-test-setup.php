<?php
/**
 * Class file for Test_Setup.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.30.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event\Event;
use GatherPress\Core\Rsvp\Cleanup;
use GatherPress\Core\Rsvp\Form;
use GatherPress\Core\Rsvp\List_Table;
use GatherPress\Core\Rsvp\Query;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp\Token;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Setup
 */
class Test_Setup extends Base {

	/**
	 * Rsvp\Setup now owns the instantiation of the Rsvp\* sibling
	 * singletons (Cleanup, Form, Query) so the outer
	 * `Setup::instantiate_classes()` can hand off with a single
	 * `Rsvp\Setup::get_instance()` call. Per-sibling proof-of-construction
	 * via their `setup_hooks()`-registered hooks — catches the case where
	 * a sibling silently drops out of `Rsvp\Setup::instantiate_classes()`.
	 *
	 * @covers ::__construct
	 * @covers ::instantiate_classes
	 *
	 * @return void
	 */
	public function test_instantiate_classes_registers_siblings(): void {
		// Force the method to run inside the test's coverage window —
		// Setup is a singleton cached during plugin bootstrap, so
		// `get_instance()` here returns the cached instance and doesn't
		// re-fire the constructor.
		Utility::invoke_hidden_method( Setup::get_instance(), 'instantiate_classes' );

		$expected_hooks = array(
			Cleanup::class => array(
				'gatherpress_rsvp_cleanup',
				array( Cleanup::get_instance(), 'rsvp_cleanup' ),
			),
			Form::class    => array(
				'init',
				array( Form::get_instance(), 'initialize_rsvp_form_handling' ),
			),
			Query::class   => array(
				'pre_get_comments',
				array( Query::get_instance(), 'exclude_rsvp_from_comment_query' ),
			),
		);

		foreach ( $expected_hooks as $class_name => $expected ) {
			list( $hook, $callback ) = $expected;
			$this->assertSame(
				10,
				has_action( $hook, $callback ),
				sprintf( '%s must be instantiated so its %s hook registers.', $class_name, $hook )
			);
		}
	}

	/**
	 * Coverage for constructor.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_constructor(): void {
		$instance = Setup::get_instance();

		// Instance should be created successfully.
		$this->assertInstanceOf( Setup::class, $instance );
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
		$instance = Setup::get_instance();
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
		$instance = Setup::get_instance();
		$instance->register_taxonomy();

		$this->assertTrue( taxonomy_exists( Status::TAXONOMY ) );
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

		$instance = Setup::get_instance();
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

		$instance = Setup::get_instance();
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

		$instance = Setup::get_instance();
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
		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();
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
		$token = new Token( $comment_id );
		$token->generate_token();
		$token_value  = $token->get_token();
		$token_string = sprintf( '%d_%s', $comment_id, $token_value );

		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) use ( $token_string ) {
				if ( INPUT_GET === $type && Token::NAME === $var_name ) {
					return $token_string;
				}
				return null;
			},
			10,
			3
		);

		$instance = Setup::get_instance();
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

		$instance = Setup::get_instance();

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
		$token = new Token( $comment_id );
		$token->generate_token();
		$token_value  = $token->get_token();
		$token_string = sprintf( '%d_%s', $comment_id, $token_value );

		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) use ( $token_string ) {
				if ( INPUT_GET === $type && Token::NAME === $var_name ) {
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
		$instance = Setup::get_instance();
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
		$instance = Setup::get_instance();

		// Should not throw error when no token is present.
		$instance->handle_rsvp_token();
		$this->assertTrue( true );
	}

	/**
	 * Whenever the token query var is present the handler must queue
	 * `nocache_headers()` onto WP's `send_headers` action so the magic-
	 * link URL is treated as per-user by any host-level page cache.
	 * Without this, an aggressive page cache either leaks one user's
	 * authenticated view to another or pins a stale RSVP-list render to
	 * the URL (#1626). The deferred dispatch is what makes this
	 * observable in tests — `nocache_headers()` called inline early-
	 * returns under `headers_sent()`, which is always true in the
	 * PHPUnit runner.
	 *
	 * @covers ::handle_rsvp_token
	 *
	 * @return void
	 */
	public function test_handle_rsvp_token_defers_nocache_headers(): void {
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
				'comment_approved'     => 0,
			)
		);

		$token = new Token( $comment_id );
		$token->generate_token();
		$token_string = sprintf( '%d_%s', $comment_id, $token->get_token() );

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $token_string ) {
				if ( INPUT_GET === $type && Token::NAME === $var_name ) {
					return $token_string;
				}
				return null;
			},
			10,
			3
		);

		// Ensure baseline: no nocache_headers wiring before the call.
		remove_action( 'send_headers', 'nocache_headers' );

		Setup::get_instance()->handle_rsvp_token();

		$has_action = has_action( 'send_headers', 'nocache_headers' );

		remove_action( 'send_headers', 'nocache_headers' );
		remove_all_filters( 'gatherpress_pre_get_http_input' );

		$this->assertNotFalse(
			$has_action,
			'`send_headers` must be wired to `nocache_headers` on every token-bearing request.'
		);
	}

	/**
	 * Without a token in the URL the handler must NOT queue no-cache
	 * headers — otherwise every page request would become uncacheable,
	 * defeating the host's page cache for the vast majority of traffic.
	 *
	 * @covers ::handle_rsvp_token
	 *
	 * @return void
	 */
	public function test_handle_rsvp_token_skips_nocache_headers_when_token_absent(): void {
		// Ensure baseline: no nocache_headers wiring before the call.
		remove_action( 'send_headers', 'nocache_headers' );

		Setup::get_instance()->handle_rsvp_token();

		$has_action = has_action( 'send_headers', 'nocache_headers' );

		$this->assertFalse(
			$has_action,
			'`send_headers` must not be wired to `nocache_headers` on requests that carry no RSVP token.'
		);
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

		$instance = Setup::get_instance();
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

		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();

		// Test with different page.
		$plugin_page = 'some_other_page'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$result      = $instance->highlight_admin_menu( 'index.php' );

		$this->assertSame( 'index.php', $result );

		$plugin_page = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * The highlighted parent follows the RSVP-supporting post type whose
	 * menu the current RSVPs page lives under (#1849).
	 *
	 * @covers ::highlight_admin_menu
	 *
	 * @return void
	 */
	public function test_highlight_admin_menu_follows_supporting_post_type(): void {
		global $plugin_page, $typenow;

		register_post_type(
			'gatherpress_probe',
			array(
				'public'   => true,
				'supports' => array( 'title', 'gatherpress-rsvp' ),
			)
		);

		$instance = Setup::get_instance();

		$plugin_page = Rsvp::COMMENT_TYPE; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$typenow     = 'gatherpress_probe'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame(
			'edit.php?post_type=gatherpress_probe',
			$instance->highlight_admin_menu( 'index.php' ),
			'Highlighted parent should follow the supporting post type.'
		);

		// A non-supporting post type falls back to the event post type.
		$typenow = 'post'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame(
			sprintf( 'edit.php?post_type=%s', Event::POST_TYPE ),
			$instance->highlight_admin_menu( 'index.php' ),
			'Highlighted parent should fall back to the event post type.'
		);

		// Clean up.
		remove_filter( 'submenu_file', array( $instance, 'set_submenu_file' ) );
		unregister_post_type( 'gatherpress_probe' );
		$plugin_page = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$typenow     = ''; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Coverage for set_submenu_file method.
	 *
	 * @covers ::set_submenu_file
	 *
	 * @return void
	 */
	public function test_set_submenu_file(): void {
		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();
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
	 * Returns whether the RSVPs submenu is registered under a post type's menu.
	 *
	 * @param string $post_type Post type slug.
	 *
	 * @return bool True when the RSVPs submenu exists for the post type.
	 */
	protected function has_rsvp_submenu_for_post_type( string $post_type ): bool {
		global $submenu;

		$parent = sprintf( 'edit.php?post_type=%s', $post_type );

		foreach ( $submenu[ $parent ] ?? array() as $item ) {
			if ( Rsvp::COMMENT_TYPE === ( $item[2] ?? '' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The RSVPs submenu is not added when no post type supports
	 * `gatherpress-rsvp` — e.g. a companion plugin removed the support
	 * from the event post type (#1849).
	 *
	 * @covers ::add_rsvp_submenu_page
	 *
	 * @return void
	 */
	public function test_add_rsvp_submenu_page_bails_without_supporting_post_type(): void {
		global $submenu;

		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$instance = Setup::get_instance();

		remove_post_type_support( Event::POST_TYPE, 'gatherpress-rsvp' );

		$instance->add_rsvp_submenu_page();

		$this->assertFalse(
			$this->has_rsvp_submenu_for_post_type( Event::POST_TYPE ),
			'No RSVPs submenu should be added when no post type supports gatherpress-rsvp.'
		);

		// Restore the support for subsequent tests.
		add_post_type_support( Event::POST_TYPE, 'gatherpress-rsvp' );

		$instance->add_rsvp_submenu_page();

		$this->assertTrue(
			$this->has_rsvp_submenu_for_post_type( Event::POST_TYPE ),
			'RSVPs submenu should be added once a post type supports gatherpress-rsvp again.'
		);
	}

	/**
	 * Every post type declaring `gatherpress-rsvp` support gets its own
	 * RSVPs submenu (#1849).
	 *
	 * @covers ::add_rsvp_submenu_page
	 *
	 * @return void
	 */
	public function test_add_rsvp_submenu_page_adds_menu_for_each_supporting_post_type(): void {
		global $submenu;

		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		register_post_type(
			'gatherpress_probe',
			array(
				'public'   => true,
				'supports' => array( 'title', 'gatherpress-rsvp' ),
			)
		);

		Setup::get_instance()->add_rsvp_submenu_page();

		$this->assertTrue(
			$this->has_rsvp_submenu_for_post_type( Event::POST_TYPE ),
			'RSVPs submenu should be added under the event post type menu.'
		);
		$this->assertTrue(
			$this->has_rsvp_submenu_for_post_type( 'gatherpress_probe' ),
			'RSVPs submenu should be added under every other supporting post type menu.'
		);

		unregister_post_type( 'gatherpress_probe' );
	}

	/**
	 * The load-hook handler scopes the list table to the current screen's
	 * post type, falling back to the event post type for screens without a
	 * supporting post type (#1849).
	 *
	 * @covers ::prepare_rsvp_admin_page
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_admin_page_scopes_list_table_to_screen_post_type(): void {
		register_post_type(
			'gatherpress_probe',
			array(
				'public'   => true,
				'supports' => array( 'title', 'gatherpress-rsvp' ),
			)
		);

		$instance = Setup::get_instance();

		set_current_screen( sprintf( 'probes_page_%s', Rsvp::COMMENT_TYPE ) );
		get_current_screen()->post_type = 'gatherpress_probe';

		$instance->prepare_rsvp_admin_page();

		$list_table = Utility::get_hidden_property( $instance, 'list_table' );

		$this->assertSame(
			'gatherpress_probe',
			Utility::get_hidden_property( $list_table, 'post_type' ),
			'List table should be scoped to the screen post type.'
		);

		// A screen without a supporting post type falls back to the event post type.
		get_current_screen()->post_type = 'post';

		$instance->prepare_rsvp_admin_page();

		$list_table = Utility::get_hidden_property( $instance, 'list_table' );

		$this->assertSame(
			Event::POST_TYPE,
			Utility::get_hidden_property( $list_table, 'post_type' ),
			'List table should fall back to the event post type for non-supporting screens.'
		);

		unregister_post_type( 'gatherpress_probe' );
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
		$instance = Setup::get_instance();

		// Set up list_table property.
		Utility::set_and_get_hidden_property( $instance, 'list_table', new List_Table() );

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
			List_Table::DEFAULT_PER_PAGE,
			$options['per_page']['default'],
			'Default per page should match List_Table::DEFAULT_PER_PAGE'
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

		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();

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

		$instance = Setup::get_instance();

		// Should trigger wp_die.
		$this->expectException( 'WPDieException' );
		$instance->render_rsvp_admin_page();

		wp_set_current_user( 0 );
	}

	/**
	 * Test maybe_set_rsvp_meta_default delegates to Rsvp::initialize_enabled.
	 *
	 * @covers ::maybe_set_rsvp_meta_default
	 *
	 * @return void
	 */
	public function test_maybe_set_rsvp_meta_default_delegates_to_rsvp(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Clear any meta set by the wp_after_insert_post hook during post creation.
		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );

		// Default mode is all_on; the delegation should write 1.
		Setup::get_instance()->maybe_set_rsvp_meta_default( $post_id );

		$this->assertSame(
			'1',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Delegation to Rsvp::initialize_enabled should write meta as 1 in all_on mode.'
		);
	}

	/**
	 * Test maybe_set_rsvp_meta_default skips non-event post types.
	 *
	 * @covers ::maybe_set_rsvp_meta_default
	 *
	 * @return void
	 */
	public function test_maybe_set_rsvp_meta_default_skips_non_event_post_type(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		// Standard post type does not support gatherpress-rsvp; meta should not be written.
		Setup::get_instance()->maybe_set_rsvp_meta_default( $post_id );

		$this->assertSame(
			'',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Meta should not be written for non-event post types.'
		);
	}

	/**
	 * Test maybe_disable_rsvp when rsvp_mode is not disabled.
	 *
	 * @covers ::maybe_disable_rsvp
	 *
	 * @return void
	 */
	public function test_maybe_disable_rsvp_when_enabled(): void {
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

		// Both rsvp_mode and enable_open_rsvp must be active for no filtering to occur.
		Settings::get_instance()->set( 'enable_open_rsvp', true );

		$initial_value = true;
		$result        = $instance->filter_rsvp_block_types( $initial_value );

		$this->assertSame(
			$initial_value,
			$result,
			'When both RSVP mode and open RSVP are enabled, block types should be returned unchanged.'
		);

		// Restore setting.
		Settings::get_instance()->set( 'enable_open_rsvp', false );
	}

	/**
	 * Test filter_rsvp_block_types removes RSVP blocks when RSVP is disabled.
	 *
	 * @covers ::filter_rsvp_block_types
	 *
	 * @return void
	 */
	public function test_filter_rsvp_block_types_when_disabled(): void {
		$instance = Setup::get_instance();

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

	/**
	 * Tests filter_rsvp_block_types expands true to array and removes RSVP blocks when disabled.
	 *
	 * @covers ::filter_rsvp_block_types
	 *
	 * @return void
	 */
	public function test_filter_rsvp_block_types_expands_true_when_disabled(): void {
		$instance = Setup::get_instance();

		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );

		// Pass true (all blocks allowed) — should expand and filter out RSVP blocks.
		$result = $instance->filter_rsvp_block_types( true );

		$this->assertIsArray( $result, 'Result should be an array when all blocks were allowed.' );
		$this->assertNotContains(
			'gatherpress/rsvp',
			$result,
			'gatherpress/rsvp block should be removed when RSVP is disabled.'
		);

		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Tests filter_rsvp_block_types returns non-array value unchanged when disabled.
	 *
	 * @covers ::filter_rsvp_block_types
	 *
	 * @return void
	 */
	public function test_filter_rsvp_block_types_returns_non_array_unchanged_when_disabled(): void {
		$instance = Setup::get_instance();

		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );

		// Pass false (not true, not array) — should be returned as-is.
		$result = $instance->filter_rsvp_block_types( false );

		$this->assertFalse( $result, 'Non-array, non-true value should be returned unchanged.' );

		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Tests filter_rsvp_block_types removes only rsvp-form when open RSVP is disabled.
	 *
	 * @covers ::filter_rsvp_block_types
	 *
	 * @return void
	 */
	public function test_filter_rsvp_block_types_removes_rsvp_form_when_open_rsvp_disabled(): void {
		$instance = Setup::get_instance();

		Settings::get_instance()->set( 'enable_open_rsvp', false );

		$block_list = array(
			'core/paragraph',
			'gatherpress/rsvp',
			'gatherpress/rsvp-form',
			'gatherpress/rsvp-response',
			'gatherpress/event-date',
		);

		$result = $instance->filter_rsvp_block_types( $block_list );

		// Only gatherpress/rsvp-form should be removed.
		$this->assertNotContains(
			'gatherpress/rsvp-form',
			$result,
			'gatherpress/rsvp-form should be removed when open RSVP is disabled.'
		);

		// Other RSVP blocks should remain.
		$this->assertContains(
			'gatherpress/rsvp',
			$result,
			'gatherpress/rsvp block should remain when only open RSVP is disabled.'
		);
		$this->assertContains(
			'gatherpress/rsvp-response',
			$result,
			'gatherpress/rsvp-response block should remain when only open RSVP is disabled.'
		);
		$this->assertContains(
			'core/paragraph',
			$result,
			'core/paragraph block should remain when only open RSVP is disabled.'
		);

		// Restore setting.
		Settings::get_instance()->set( 'enable_open_rsvp', true );
	}

	/**
	 * Tests filter_rsvp_block_types expands true to array and removes only rsvp-form when open RSVP is disabled.
	 *
	 * @covers ::filter_rsvp_block_types
	 *
	 * @return void
	 */
	public function test_filter_rsvp_block_types_expands_true_when_open_rsvp_disabled(): void {
		$instance = Setup::get_instance();

		Settings::get_instance()->set( 'enable_open_rsvp', false );

		$result = $instance->filter_rsvp_block_types( true );

		$this->assertIsArray( $result, 'Result should be an array when all blocks were allowed.' );
		$this->assertNotContains(
			'gatherpress/rsvp-form',
			$result,
			'gatherpress/rsvp-form should be removed when open RSVP is disabled.'
		);
		$this->assertContains(
			'gatherpress/rsvp',
			$result,
			'gatherpress/rsvp block should remain when only open RSVP is disabled.'
		);

		// Restore setting.
		Settings::get_instance()->set( 'enable_open_rsvp', true );
	}

	/**
	 * Tests filter_rsvp_block_types returns non-array value unchanged when only open RSVP is disabled.
	 *
	 * @covers ::filter_rsvp_block_types
	 *
	 * @return void
	 */
	public function test_filter_rsvp_block_types_returns_non_array_unchanged_when_open_rsvp_disabled(): void {
		$instance = Setup::get_instance();

		Settings::get_instance()->set( 'enable_open_rsvp', false );

		$result = $instance->filter_rsvp_block_types( false );

		$this->assertFalse( $result, 'Non-array, non-true value should be returned unchanged.' );

		// Restore setting.
		Settings::get_instance()->set( 'enable_open_rsvp', true );
	}

	/**
	 * Tests add_rsvp_submenu_page returns early when RSVP is globally disabled.
	 *
	 * @covers ::add_rsvp_submenu_page
	 *
	 * @return void
	 */
	public function test_add_rsvp_submenu_page_skips_when_disabled(): void {
		global $submenu;

		if ( ! is_array( $submenu ) ) {
			$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$submenu_before = $submenu;

		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );

		Setup::get_instance()->add_rsvp_submenu_page();

		// Submenu should be unchanged since the method returns early.
		$this->assertSame(
			$submenu_before,
			$submenu,
			'No submenu page should be added when RSVP is globally disabled.'
		);

		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}
}
