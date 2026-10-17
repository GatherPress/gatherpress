<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Email.
 *
 * @package GatherPress\Core\Admin
 * @since TBD
 */

namespace GatherPress\Tests\Core\Admin;

use GatherPress\Core\Admin\Email;
use GatherPress\Core\Event;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Core\User;
use GatherPress\Core\Utility as Core_Utility;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Test_Email.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Email
 */
class Test_Email extends Base {

	/**
	 * The audience a message goes to when none is picked.
	 *
	 * @var array<string, string>
	 */
	private const ALL_USERS = array( 'type' => 'all' );

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Email::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_menu',
				'priority' => 10,
				'callback' => array( $instance, 'add_submenu_page' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'submenu_file',
				'priority' => 10,
				'callback' => array( $instance, 'select_submenu' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'rest_api_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_endpoints' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_user_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_clear_count_cache' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_user_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_clear_count_cache' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_user_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_clear_count_cache' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'user_register',
				'priority' => 10,
				'callback' => array( $instance, 'clear_count_cache' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_user',
				'priority' => 10,
				'callback' => array( $instance, 'clear_count_cache' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'add_user_to_blog',
				'priority' => 10,
				'callback' => array( $instance, 'clear_count_cache' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'remove_user_from_blog',
				'priority' => 10,
				'callback' => array( $instance, 'clear_count_cache' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Check that the Email page is added to the Events menu with its assets hook.
	 *
	 * @covers ::add_submenu_page
	 *
	 * @return void
	 */
	public function test_add_submenu_page(): void {
		global $submenu;

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		Email::get_instance()->add_submenu_page();

		$slugs = wp_list_pluck( $submenu[ Settings::PARENT_SLUG ] ?? array(), 2 );
		$hook  = get_plugin_page_hookname( Email::PAGE_SLUG, Settings::PARENT_SLUG );

		$this->assertContains( Email::PAGE_SLUG, $slugs );
		$this->assertSame(
			10,
			has_action( sprintf( 'load-%s', $hook ), array( Email::get_instance(), 'enqueue_assets' ) )
		);

		remove_all_actions( sprintf( 'load-%s', $hook ) );
		unset( $submenu[ Settings::PARENT_SLUG ] );
	}

	/**
	 * Check that no page or assets hook is added for a user who cannot send.
	 *
	 * @covers ::add_submenu_page
	 *
	 * @return void
	 */
	public function test_add_submenu_page_skips_users_who_cannot_send(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		Email::get_instance()->add_submenu_page();

		$hook = get_plugin_page_hookname( Email::PAGE_SLUG, Settings::PARENT_SLUG );

		$this->assertFalse(
			has_action( sprintf( 'load-%s', $hook ), array( Email::get_instance(), 'enqueue_assets' ) )
		);
	}

	/**
	 * Check that the Email item is highlighted only on the Email page.
	 *
	 * @covers ::select_submenu
	 *
	 * @return void
	 */
	public function test_select_submenu(): void {
		global $plugin_page;

		$instance    = Email::get_instance();
		$plugin_page = Email::PAGE_SLUG; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( Email::PAGE_SLUG, $instance->select_submenu( null ) );

		$plugin_page = 'gatherpress_rsvp'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( 'gatherpress_rsvp', $instance->select_submenu( 'gatherpress_rsvp' ) );
		$this->assertNull( $instance->select_submenu( null ) );

		$plugin_page = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Check that the Email page renders the send form.
	 *
	 * @covers ::render_page
	 *
	 * @return void
	 */
	public function test_render_page(): void {
		$response = Utility::buffer_and_return( array( Email::get_instance(), 'render_page' ) );

		$this->assertStringContainsString( '<h1>Email</h1>', $response );
		$this->assertStringContainsString( 'value="active"', $response );
		$this->assertStringContainsString( 'id="gatherpress-message-days"', $response );
		$this->assertStringContainsString( 'value="editor"', $response );
		$this->assertStringContainsString( 'data-gatherpress_roles="all"', $response );
		$this->assertStringContainsString( 'Send Email To Users', $response );
		$this->assertStringContainsString( 'gatherpress-message-body', $response );
	}

	/**
	 * Check that the Active users fields are left out while RSVPs are off.
	 *
	 * @covers ::render_page
	 *
	 * @return void
	 */
	public function test_render_page_leaves_out_active_users_while_rsvps_are_off(): void {
		update_option( Settings::OPTION_NAME, array( 'rsvp_mode' => 'disabled' ) );

		$response = Utility::buffer_and_return( array( Email::get_instance(), 'render_page' ) );

		delete_option( Settings::OPTION_NAME );

		$this->assertStringNotContainsString( 'value="active"', $response );
		$this->assertStringNotContainsString( 'id="gatherpress-message-days"', $response );
	}

	/**
	 * Check that the Email page loads its script and the utility styles.
	 *
	 * @covers ::enqueue_assets
	 *
	 * @return void
	 */
	public function test_enqueue_assets(): void {
		Email::get_instance()->enqueue_assets();

		$this->assertTrue( wp_script_is( 'gatherpress-email', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'gatherpress-utility-style', 'enqueued' ) );

		wp_dequeue_script( 'gatherpress-email' );
		wp_dequeue_style( 'gatherpress-utility-style' );
	}

	/**
	 * Check that the email template handles its count branches.
	 *
	 * @return void
	 */
	public function test_email_template_handles_recipient_count(): void {
		$template = GATHERPRESS_CORE_PATH . '/includes/templates/admin/email.php';

		$this->assertEmpty( Core_Utility::render_template( $template ) );

		$singular = Core_Utility::render_template( $template, array( 'recipient_count' => 1 ) );
		$plural   = Core_Utility::render_template( $template, array( 'recipient_count' => 2 ) );

		$this->assertStringContainsString( 'This will email 1 user.', $singular );
		$this->assertStringContainsString( 'This will email 2 users.', $plural );
		$this->assertStringNotContainsString( '<style', $plural, 'Styles belong in the settings stylesheet.' );
		$this->assertStringNotContainsString( '<script', $plural, 'Scripts belong in the settings bundle.' );
	}

	/**
	 * Check that the site message route is registered for administrators only.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_register_endpoints(): void {
		$instance = Email::get_instance();

		// Creating the server fires rest_api_init, so registering again here is allowed.
		$server = rest_get_server();
		$instance->register_endpoints();

		$route = $server->get_routes()[ sprintf( '/%s/site-message', GATHERPRESS_REST_NAMESPACE ) ][0];

		$this->assertSame( array( 'POST' => true ), $route['methods'] );
		$this->assertSame( array( $instance, 'queue_message' ), $route['callback'] );
		$this->assertSame( 'sanitize_text_field', $route['args']['subject']['sanitize_callback'] );
		$this->assertSame( 'sanitize_textarea_field', $route['args']['message']['sanitize_callback'] );
		$this->assertFalse( $route['args']['subject']['required'] );
		$this->assertSame( Email::AUDIENCES, $route['args']['audience']['enum'] );

		$count = $server->get_routes()[ sprintf( '/%s/site-message/count', GATHERPRESS_REST_NAMESPACE ) ][0];

		$this->assertSame( array( 'GET' => true ), $count['methods'] );
		$this->assertSame( array( $instance, 'count_message_recipients' ), $count['callback'] );
		$this->assertSame( Email::ACTIVE_DAYS, $count['args']['days']['enum'] );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse(
			call_user_func( $route['permission_callback'] ),
			'Users without manage_options should not be able to send.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( call_user_func( $route['permission_callback'] ), 'Administrators should be able to send.' );
	}

	/**
	 * Check that no audience means every user.
	 *
	 * @covers ::resolve_audience
	 *
	 * @return void
	 */
	public function test_resolve_audience_defaults_to_all_users(): void {
		$this->assertSame( self::ALL_USERS, $this->resolve_audience( array() ) );
		$this->assertSame( self::ALL_USERS, $this->resolve_audience( array( 'audience' => 'made_up' ) ) );
	}

	/**
	 * Check that only roles the site has are kept, and that at least one is needed.
	 *
	 * @covers ::resolve_audience
	 *
	 * @return void
	 */
	public function test_resolve_audience_keeps_only_roles_the_site_has(): void {
		$this->assertSame(
			array(
				'type'  => 'role',
				'roles' => array( 'editor' ),
			),
			$this->resolve_audience(
				array(
					'audience' => 'role',
					'roles'    => array( 'editor', 'made_up' ),
				)
			)
		);

		$error = $this->resolve_audience(
			array(
				'audience' => 'role',
				'roles'    => array( 'made_up' ),
			)
		);

		$this->assertWPError( $error );
		$this->assertSame( 'Choose at least one role.', $error->get_error_message() );
		$this->assertSame( 400, $error->get_error_data()['status'] );
	}

	/**
	 * Check that the active window is read in days.
	 *
	 * @covers ::resolve_audience
	 *
	 * @return void
	 */
	public function test_resolve_audience_reads_active_days(): void {
		$this->assertSame(
			array(
				'type' => 'active',
				'days' => 180,
			),
			$this->resolve_audience(
				array(
					'audience' => 'active',
					'days'     => 180,
				)
			)
		);
	}

	/**
	 * Check that the active window choices can be filtered, and stay usable.
	 *
	 * @covers ::get_active_days
	 * @covers ::get_default_active_days
	 *
	 * @return void
	 */
	public function test_get_active_days(): void {
		$instance = Email::get_instance();
		$days     = static fn(): array => Utility::invoke_hidden_method( $instance, 'get_active_days' );
		$default  = static fn(): int => Utility::invoke_hidden_method( $instance, 'get_default_active_days' );

		$this->assertSame( array( 30, 90, 180, 365 ), $days() );
		$this->assertSame( 90, $default() );

		$weekly = static fn(): array => array( 7, '14', 7, 0, -5 );

		add_filter( 'gatherpress_site_message_active_days', $weekly );

		$this->assertSame( array( 7, 14 ), $days(), 'Choices should be positive, unique whole days.' );
		$this->assertSame( 7, $default(), 'Without 90 days on offer, the first choice is the default.' );

		remove_filter( 'gatherpress_site_message_active_days', $weekly );
		add_filter( 'gatherpress_site_message_active_days', '__return_empty_array' );

		$this->assertSame( Email::ACTIVE_DAYS, $days(), 'An empty list should fall back to the defaults.' );

		remove_filter( 'gatherpress_site_message_active_days', '__return_empty_array' );
	}

	/**
	 * Check that active users cannot be picked while RSVPs are turned off.
	 *
	 * @covers ::resolve_audience
	 * @covers ::is_rsvp_enabled
	 *
	 * @return void
	 */
	public function test_resolve_audience_rejects_active_while_rsvps_are_off(): void {
		update_option( Settings::OPTION_NAME, array( 'rsvp_mode' => 'disabled' ) );

		$error = $this->resolve_audience( array( 'audience' => 'active' ) );

		delete_option( Settings::OPTION_NAME );

		$this->assertWPError( $error );
		$this->assertSame( 400, $error->get_error_data()['status'] );
	}

	/**
	 * Check that active users are only offered while RSVPs are turned on.
	 *
	 * @covers ::get_audience_choices
	 * @covers ::is_rsvp_enabled
	 *
	 * @return void
	 */
	public function test_get_audience_choices(): void {
		$choices = static function (): array {
			return array_keys( Utility::invoke_hidden_method( Email::get_instance(), 'get_audience_choices' ) );
		};

		$this->assertSame( array( 'all', 'role', 'active' ), $choices() );

		update_option( Settings::OPTION_NAME, array( 'rsvp_mode' => 'disabled' ) );

		$this->assertSame( array( 'all', 'role' ), $choices() );
		$this->assertFalse( Utility::invoke_hidden_method( Email::get_instance(), 'is_rsvp_enabled' ) );

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Check the user query arguments each audience adds.
	 *
	 * @covers ::get_audience_query_args
	 *
	 * @return void
	 */
	public function test_get_audience_query_args(): void {
		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();

		$rsvp_id = $this->rsvp( $event_id, $user_id );

		$args = static function ( array $audience ) use ( $instance ): array {
			return Utility::invoke_hidden_method(
				$instance,
				'get_audience_query_args',
				array( array( 'audience' => $audience ) )
			);
		};

		$this->assertSame( array(), $args( self::ALL_USERS ) );
		$this->assertSame(
			array(),
			Utility::invoke_hidden_method( $instance, 'get_audience_query_args', array( array() ) ),
			'A payload without an audience should go to every user.'
		);
		$this->assertSame(
			array( 'role__in' => array( 'editor' ) ),
			$args(
				array(
					'type'  => 'role',
					'roles' => array( 'editor' ),
				)
			)
		);
		$this->assertSame(
			array( 'include' => array( $user_id ) ),
			$args(
				array(
					'type' => 'active',
					'days' => 30,
				)
			)
		);
		wp_delete_comment( $rsvp_id, true );

		$this->assertSame(
			array( 'include' => array( 0 ) ),
			$args(
				array(
					'type' => 'active',
					'days' => 30,
				)
			),
			'With no active users, nobody should match.'
		);
	}

	/**
	 * Check that active users are those with a confirmed RSVP inside the window.
	 *
	 * @covers ::get_active_user_ids
	 * @covers ::get_rsvp_user_ids
	 *
	 * @return void
	 */
	public function test_get_active_user_ids(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$recent   = $this->factory->user->create();
		$older    = $this->factory->user->create();
		$pending  = $this->factory->user->create();

		$this->rsvp( $event_id, $recent );
		$this->rsvp( $event_id, $recent );
		$this->rsvp( $event_id, $older, 1, '-8 months' );
		$this->rsvp( $event_id, $pending, 0 );
		$this->rsvp( $event_id, 0 );

		$ids = static function ( int $days ): array {
			$active = Utility::invoke_hidden_method( Email::get_instance(), 'get_active_user_ids', array( $days ) );

			sort( $active );

			return $active;
		};

		$this->assertSame( array( $recent ), $ids( 180 ) );
		$this->assertSame( array( $recent, $older ), $ids( 365 ) );
	}

	/**
	 * Check that RSVPs are reduced to the users behind them.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called by short delegation.
	 *
	 * @covers ::get_rsvp_user_ids
	 *
	 * @return void
	 */
	public function test_get_rsvp_user_ids(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();

		$this->rsvp( $event_id, $user_id );
		$this->rsvp( $event_id, 0 );

		$this->assertSame(
			array( $user_id ),
			Utility::invoke_hidden_method(
				Email::get_instance(),
				'get_rsvp_user_ids',
				array( array( 'post_id' => $event_id ) )
			)
		);
	}

	/**
	 * Check the audience arguments both site message routes share.
	 *
	 * @covers ::get_audience_args
	 *
	 * @return void
	 */
	public function test_get_audience_args(): void {
		$args = Utility::invoke_hidden_method( Email::get_instance(), 'get_audience_args' );

		$this->assertSame( array( 'audience', 'roles', 'days' ), array_keys( $args ) );
		$this->assertSame( 'all', $args['audience']['default'] );
		$this->assertSame( 90, $args['days']['default'] );
	}

	/**
	 * Check that each audience narrows who a message counts and goes to.
	 *
	 * @covers ::get_audience_query_args
	 *
	 * @return void
	 */
	public function test_count_by_audience(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$editor   = $this->factory->user->create( array( 'role' => 'editor' ) );
		$author   = $this->factory->user->create( array( 'role' => 'author' ) );
		$count    = static fn( array $audience ): int => Mailer::get_instance()->count(
			Email::MAIL_TYPE,
			array( 'audience' => $audience )
		);

		$this->rsvp( $event_id, $editor );

		$this->assertSame(
			2,
			$count(
				array(
					'type'  => 'role',
					'roles' => array( 'editor', 'author' ),
				)
			)
		);
		$this->assertSame(
			1,
			$count(
				array(
					'type' => 'active',
					'days' => 30,
				)
			)
		);
		update_user_meta( $author, 'gatherpress_event_updates_opt_in', '0' );

		$this->assertSame(
			1,
			$count(
				array(
					'type'  => 'role',
					'roles' => array( 'editor', 'author' ),
				)
			),
			'Users who turned event updates off should still be skipped.'
		);
	}

	/**
	 * Check that the count route reports the audience's user count.
	 *
	 * @covers ::count_message_recipients
	 *
	 * @return void
	 */
	public function test_count_message_recipients(): void {
		$this->factory->user->create( array( 'role' => 'editor' ) );

		$request = new WP_REST_Request( 'GET', sprintf( '/%s/site-message/count', GATHERPRESS_REST_NAMESPACE ) );
		$request->set_param( 'audience', 'role' );
		$request->set_param( 'roles', array( 'editor' ) );

		$response = Email::get_instance()->count_message_recipients( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 1, $response->get_data()['count'] );
		$this->assertSame( 'This will email 1 user.', $response->get_data()['message'] );

		$request->set_param( 'roles', array() );

		$this->assertWPError( Email::get_instance()->count_message_recipients( $request ) );
	}

	/**
	 * Check that a message for an incomplete audience is not queued.
	 *
	 * @covers ::queue_message
	 *
	 * @return void
	 */
	public function test_queue_message_rejects_incomplete_audience(): void {
		$request = $this->site_message_request( 'Subject', 'Message body' );
		$request->set_param( 'audience', 'role' );

		$response = Email::get_instance()->queue_message( $request );

		$this->assertWPError( $response );
		$this->assertSame( 'gatherpress_site_message_audience', $response->get_error_code() );
	}

	/**
	 * Check that the picked audience travels with the queued message.
	 *
	 * @covers ::queue_message
	 *
	 * @return void
	 */
	public function test_queue_message_queues_the_audience(): void {
		$this->factory->user->create( array( 'role' => 'editor' ) );

		$request = $this->site_message_request( 'Subject', 'Message body' );
		$request->set_param( 'audience', 'role' );
		$request->set_param( 'roles', array( 'editor' ) );

		$response = Email::get_instance()->queue_message( $request );

		$this->assertSame( 'Queued for 1 user.', $response->get_data()['message'] );
		$this->assertNotFalse(
			wp_next_scheduled(
				Mailer::JOB_HOOK,
				array(
					Email::MAIL_TYPE,
					array(
						'subject'  => 'Subject',
						'message'  => 'Message body',
						'audience' => array(
							'type'  => 'role',
							'roles' => array( 'editor' ),
						),
					),
					Mailer::FIRST_BATCH,
				)
			)
		);
		wp_unschedule_hook( Mailer::JOB_HOOK );
	}

	/**
	 * Check that the Email page registers its mail type with Mailer.
	 *
	 * @covers ::__construct
	 * @covers ::register_mail_type
	 *
	 * @return void
	 */
	public function test_register_mail_type(): void {
		// The constructor registers at boot, before coverage records, so register again here.
		Utility::invoke_hidden_method( Email::get_instance(), 'register_mail_type' );

		$types = Utility::get_hidden_property( Mailer::get_instance(), 'types' );

		$this->assertArrayHasKey( Email::MAIL_TYPE, $types );
		$this->assertSame( 'site_message', $types[ Email::MAIL_TYPE ]['context'] );
		$this->assertArrayHasKey( 'users', $types[ Email::MAIL_TYPE ] );
		$this->assertArrayHasKey( 'compose', $types[ Email::MAIL_TYPE ] );
	}

	/**
	 * Check that a queued message is built from its subject and message.
	 *
	 * @covers ::compose_message
	 *
	 * @return void
	 */
	public function test_compose_message(): void {
		$email = Utility::invoke_hidden_method(
			Email::get_instance(),
			'compose_message',
			array(
				array(),
				array(
					'subject' => '',
					'message' => 'Hello everyone',
				),
			)
		);

		$this->assertSame(
			sprintf( 'Message from %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			$email['subject']
		);
		$this->assertStringContainsString( 'Hello everyone', $email['body'] );
		$this->assertSame( array( 'Content-Type: text/html; charset=UTF-8' ), $email['headers'] );
	}

	/**
	 * Check that a queued message reaches the chosen users, and only them.
	 *
	 * @covers ::queue_message
	 * @covers ::compose_message
	 *
	 * @return void
	 */
	public function test_queued_message_reaches_the_chosen_users(): void {
		$editors = $this->factory->user->create_many( 2, array( 'role' => 'editor' ) );
		$sent    = array();

		$this->factory->user->create( array( 'role' => 'author' ) );
		add_filter( 'gatherpress_mail_batch_size', array( $this, 'one_per_batch' ) );
		add_filter(
			'pre_wp_mail',
			static function ( $previous, array $atts ) use ( &$sent ): bool {
				$sent[] = array( $atts['to'], $atts['subject'] );

				return true;
			},
			10,
			2
		);

		$request = $this->site_message_request( 'Board update', 'See you soon.' );
		$request->set_param( 'audience', 'role' );
		$request->set_param( 'roles', array( 'editor' ) );

		Email::get_instance()->queue_message( $request );
		$this->run_mail_jobs();

		remove_all_filters( 'pre_wp_mail' );
		remove_filter( 'gatherpress_mail_batch_size', array( $this, 'one_per_batch' ) );

		$this->assertSame(
			array_map(
				static fn( int $user_id ): array => array( get_userdata( $user_id )->user_email, 'Board update' ),
				$editors
			),
			$sent
		);
	}

	/**
	 * Check that an empty message is rejected.
	 *
	 * @covers ::queue_message
	 *
	 * @return void
	 */
	public function test_queue_message_rejects_empty_message(): void {
		$response = Email::get_instance()->queue_message( $this->site_message_request( 'Subject', '   ' ) );

		$this->assertWPError( $response );
		$this->assertSame( 'gatherpress_site_message_empty', $response->get_error_code() );
		$this->assertSame( 400, $response->get_error_data()['status'] );
	}

	/**
	 * Check that a message is rejected when no users are eligible.
	 *
	 * @covers ::queue_message
	 *
	 * @return void
	 */
	public function test_queue_message_rejects_empty_recipient_list(): void {
		foreach ( get_users() as $user ) {
			update_user_meta( $user->ID, 'gatherpress_event_updates_opt_in', '0' );
		}

		$response = Email::get_instance()->queue_message( $this->site_message_request( 'Subject', 'Message' ) );

		$this->assertWPError( $response );
		$this->assertSame( 'gatherpress_site_message_no_recipients', $response->get_error_code() );
		$this->assertSame( 400, $response->get_error_data()['status'] );
	}

	/**
	 * Check that a valid message is queued.
	 *
	 * @covers ::queue_message
	 *
	 * @return void
	 */
	public function test_queue_message_queues_message(): void {
		$this->factory->user->create();

		$response = Email::get_instance()->queue_message(
			$this->site_message_request( 'Subject', 'Message body' )
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertStringContainsString( 'Queued for', $response->get_data()['message'] );
		$this->assertNotFalse(
			wp_next_scheduled(
				Mailer::JOB_HOOK,
				array(
					Email::MAIL_TYPE,
					array(
						'subject'  => 'Subject',
						'message'  => 'Message body',
						'audience' => self::ALL_USERS,
					),
					Mailer::FIRST_BATCH,
				)
			)
		);
		wp_unschedule_hook( Mailer::JOB_HOOK );
	}

	/**
	 * Check that a message that cannot be queued reports a server error.
	 *
	 * @covers ::queue_message
	 *
	 * @return void
	 */
	public function test_queue_message_reports_schedule_failure(): void {
		$this->factory->user->create();
		add_filter( 'pre_schedule_event', '__return_false' );

		$response = Email::get_instance()->queue_message(
			$this->site_message_request( 'Subject', 'Message body' )
		);

		remove_filter( 'pre_schedule_event', '__return_false' );

		$this->assertWPError( $response );
		$this->assertSame( 'gatherpress_site_message_not_queued', $response->get_error_code() );
		$this->assertSame( 500, $response->get_error_data()['status'] );
	}

	/**
	 * Check that resolve_subject handles defaults, custom subjects, and filters.
	 *
	 * @covers ::resolve_subject
	 *
	 * @return void
	 */
	public function test_resolve_subject(): void {
		$instance = Email::get_instance();
		$default  = Utility::invoke_hidden_method( $instance, 'resolve_subject', array( '' ) );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $default );
		$this->assertSame( 'Custom', Utility::invoke_hidden_method( $instance, 'resolve_subject', array( 'Custom' ) ) );

		add_filter(
			'gatherpress_site_message_subject',
			static function ( $subject ) {
				return $subject . ' filtered';
			}
		);
		$filtered = Utility::invoke_hidden_method( $instance, 'resolve_subject', array( 'Custom' ) );
		remove_all_filters( 'gatherpress_site_message_subject' );

		$this->assertSame( 'Custom filtered', $filtered );
	}

	/**
	 * Check that the count cache helper drops the transient.
	 *
	 * @covers ::clear_count_cache
	 *
	 * @return void
	 */
	public function test_clear_count_cache_drops_transient(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		set_transient( $cache_key, 4, HOUR_IN_SECONDS );

		Email::get_instance()->clear_count_cache();

		$this->assertFalse( get_transient( $cache_key ), 'Count cache helper should clear the transient.' );
	}

	/**
	 * Check that registering a user clears the cached count.
	 *
	 * @return void
	 */
	public function test_user_register_clears_count_cache(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		set_transient( $cache_key, 4, HOUR_IN_SECONDS );

		$this->factory->user->create();

		$this->assertFalse( get_transient( $cache_key ), 'Registering a user should drop the cached count.' );
	}

	/**
	 * Check that deleting a user clears the cached count.
	 *
	 * @return void
	 */
	public function test_deleted_user_clears_count_cache(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		$user_id   = $this->factory->user->create();
		set_transient( $cache_key, 4, HOUR_IN_SECONDS );

		wp_delete_user( $user_id );

		$this->assertFalse( get_transient( $cache_key ), 'Deleting a user should drop the cached count.' );
	}

	/**
	 * Check that adding or removing a site's user on multisite clears the cached count.
	 *
	 * Those actions fire without `user_register` or `deleted_user`, so they
	 * are fired directly here, which also runs on a single-site test run.
	 *
	 * @covers ::clear_count_cache
	 *
	 * @return void
	 */
	public function test_site_membership_changes_clear_count_cache(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		$user_id   = $this->factory->user->create();

		set_transient( $cache_key, 4, HOUR_IN_SECONDS );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'add_user_to_blog', $user_id, 'subscriber', get_current_blog_id() );

		$this->assertFalse( get_transient( $cache_key ), 'Adding a user to the site should drop the cached count.' );

		set_transient( $cache_key, 4, HOUR_IN_SECONDS );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'remove_user_from_blog', $user_id, get_current_blog_id(), 0 );

		$this->assertFalse( get_transient( $cache_key ), 'Removing a site user should drop the cached count.' );
	}

	/**
	 * Check that an opt-in change clears the cached count.
	 *
	 * @covers ::maybe_clear_count_cache
	 *
	 * @return void
	 */
	public function test_maybe_clear_count_cache_clears_on_opt_in_change(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		set_transient( $cache_key, 3, HOUR_IN_SECONDS );

		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '0' );

		$this->assertFalse( get_transient( $cache_key ), 'Opt-in write should drop the cached count.' );
	}

	/**
	 * Check that an unrelated meta change leaves the cached count alone.
	 *
	 * @covers ::maybe_clear_count_cache
	 *
	 * @return void
	 */
	public function test_maybe_clear_count_cache_ignores_other_meta(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		$user_id   = $this->factory->user->create();
		set_transient( $cache_key, 3, HOUR_IN_SECONDS );

		update_user_meta( $user_id, 'gatherpress_time_format', User::HOUR_12 );

		$this->assertSame( 3, get_transient( $cache_key ), 'Unrelated meta should not clear the cache.' );

		delete_transient( $cache_key );
	}

	/**
	 * Check that the cached count helper fills the cache when it is empty.
	 *
	 * Invoked directly rather than through the Email page because xdebug
	 * does not trace same-class protected helpers called by short delegation.
	 *
	 * @covers ::get_cached_recipient_count
	 *
	 * @return void
	 */
	public function test_get_cached_recipient_count_fills_empty_cache(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		delete_transient( $cache_key );

		$count = Utility::invoke_hidden_method( Email::get_instance(), 'get_cached_recipient_count' );

		$cached = get_transient( $cache_key );
		delete_transient( $cache_key );

		$this->assertSame( $cached, $count, 'Helper should store the count it computed.' );
		$this->assertIsInt( $count );
	}

	/**
	 * Check that the cached count helper serves the stored value.
	 *
	 * @covers ::get_cached_recipient_count
	 *
	 * @return void
	 */
	public function test_get_cached_recipient_count_serves_existing_cache(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		set_transient( $cache_key, 5, HOUR_IN_SECONDS );

		$count = Utility::invoke_hidden_method( Email::get_instance(), 'get_cached_recipient_count' );

		delete_transient( $cache_key );

		$this->assertSame( 5, $count, 'Helper should return the cached count without recounting.' );
	}

	/**
	 * Check that the Email page count is served from a transient cache.
	 *
	 * @covers ::get_cached_recipient_count
	 *
	 * @return void
	 */
	public function test_email_page_count_is_cached(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		delete_transient( $cache_key );
		$instance = Email::get_instance();

		// Seed a sentinel the page could only show if it read the transient.
		set_transient( $cache_key, 4242, HOUR_IN_SECONDS );
		$page = Utility::buffer_and_return( array( $instance, 'render_page' ) );

		$this->assertStringContainsString( 'This will email 4242 users.', $page );

		delete_transient( $cache_key );
	}

	/**
	 * Limit each mail job to one recipient.
	 *
	 * @return int Batch size.
	 */
	public function one_per_batch(): int {
		return 1;
	}

	/**
	 * Resolve the audience a set of REST params picks.
	 *
	 * @param array<string, mixed> $params REST params.
	 *
	 * @return array<string, mixed>|\WP_Error The audience, or why it cannot be used.
	 */
	private function resolve_audience( array $params ) {
		$request = new WP_REST_Request( 'GET', sprintf( '/%s/site-message/count', GATHERPRESS_REST_NAMESPACE ) );

		$request->set_default_params(
			array(
				'audience' => 'all',
				'roles'    => array(),
				'days'     => 90,
			)
		);

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return Utility::invoke_hidden_method( Email::get_instance(), 'resolve_audience', array( $request ) );
	}

	/**
	 * Add an RSVP to an event.
	 *
	 * @param int    $event_id Event post ID.
	 * @param int    $user_id  User who RSVP'd, or 0 for someone without an account.
	 * @param int    $approved 1 for a confirmed RSVP, 0 for one awaiting confirmation.
	 * @param string $when     When the RSVP was made, relative to now.
	 *
	 * @return int The RSVP comment ID.
	 */
	private function rsvp( int $event_id, int $user_id, int $approved = 1, string $when = 'now' ): int {
		$date = gmdate( 'Y-m-d H:i:s', (int) strtotime( $when ) );

		return (int) wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_approved'     => $approved,
				'comment_author_email' => 'rsvp@example.test',
				'user_id'              => $user_id,
				'comment_date'         => $date,
				'comment_date_gmt'     => $date,
			)
		);
	}

	/**
	 * Build a site message REST request.
	 *
	 * @param string $subject Subject param.
	 * @param string $message Message param.
	 *
	 * @return WP_REST_Request The request.
	 */
	private function site_message_request( string $subject, string $message ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', sprintf( '/%s/site-message', GATHERPRESS_REST_NAMESPACE ) );

		$request->set_param( 'subject', $subject );
		$request->set_param( 'message', $message );

		return $request;
	}
}
