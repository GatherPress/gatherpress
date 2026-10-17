<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Email.
 *
 * @package GatherPress\Core\Admin
 * @since TBD
 */

namespace GatherPress\Tests\Core\Admin;

use Closure;
use GatherPress\Core\Admin\Email;
use GatherPress\Core\Event;
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
	 * Reusable batch-size filter so the same closure can be removed again.
	 *
	 * @var Closure|null
	 */
	private ?Closure $batch_size_filter = null;

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
				'name'     => 'gatherpress_site_message_send',
				'priority' => 10,
				'callback' => array( $instance, 'process_message' ),
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
		$this->assertStringContainsString( 'id="gatherpress-message-months"', $response );
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
		$this->assertStringNotContainsString( 'id="gatherpress-message-months"', $response );
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
	 * Check that paging walks every user and the count only includes eligible ones.
	 *
	 * @covers ::get_recipient_batch
	 * @covers ::count_recipients
	 * @covers ::get_batch_size
	 *
	 * @return void
	 */
	public function test_count_recipients_pages_and_filters_opted_out_users(): void {
		$included_id = $this->factory->user->create( array( 'display_name' => 'Included' ) );
		$excluded_id = $this->factory->user->create( array( 'display_name' => 'Excluded' ) );
		$second_id   = $this->factory->user->create( array( 'display_name' => 'Second' ) );
		update_user_meta( $excluded_id, 'gatherpress_event_updates_opt_in', '0' );
		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		$last_ids = array();
		add_filter(
			'gatherpress_site_message_recipients',
			static function ( $recipients, $last_id ) use ( &$last_ids, $included_id, $second_id ) {
				$last_ids[] = $last_id;

				return array_values(
					array_filter(
						$recipients,
						static function ( $recipient ) use ( $included_id, $second_id ) {
							return in_array( $recipient['user_id'], array( $included_id, $second_id ), true );
						}
					)
				);
			},
			10,
			2
		);

		$count = Email::get_instance()->count_recipients();
		remove_all_filters( 'gatherpress_site_message_recipients' );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		// The excluded user is dropped, so only the two opted-in users count.
		$this->assertSame( 2, $count );
		// Every page of one was visited, and the next cursor is the first user ID.
		$this->assertContains( 0, $last_ids );
		$this->assertContains( $included_id, $last_ids );
	}

	/**
	 * Check that the consent filter narrows the site-message audience.
	 *
	 * @covers ::get_recipient_batch
	 *
	 * @return void
	 */
	public function test_recipient_batch_honors_consent_filter(): void {
		$kept_id       = $this->factory->user->create( array( 'display_name' => 'Kept' ) );
		$dropped_id    = $this->factory->user->create( array( 'display_name' => 'Dropped' ) );
		$contexts      = array();
		$consent_calls = static function ( $consent, $recipient, $context ) use ( &$contexts, $dropped_id ) {
			$contexts[] = $context;

			return (int) $recipient['user_id'] !== $dropped_id;
		};

		add_filter( 'gatherpress_mail_recipient_consent', $consent_calls, 10, 3 );

		$batch = Utility::invoke_hidden_method(
			Email::get_instance(),
			'get_recipient_batch',
			array( 0 )
		);

		remove_all_filters( 'gatherpress_mail_recipient_consent' );

		$recipient_ids = array_column( $batch['recipients'], 'user_id' );
		$this->assertContains( $kept_id, $recipient_ids );
		$this->assertNotContains( $dropped_id, $recipient_ids );
		$this->assertContains( 'site_message', $contexts );
	}

	/**
	 * Check that a full batch reports the list unfinished and advances the cursor.
	 *
	 * @covers ::get_recipient_batch
	 *
	 * @return void
	 */
	public function test_recipient_batch_reports_incomplete_and_advances_cursor(): void {
		$cursor    = $this->max_user_id();
		$first_id  = $this->factory->user->create( array( 'display_name' => 'First' ) );
		$second_id = $this->factory->user->create( array( 'display_name' => 'Second' ) );
		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		$instance = Email::get_instance();

		$first  = Utility::invoke_hidden_method( $instance, 'get_recipient_batch', array( $cursor ) );
		$second = Utility::invoke_hidden_method( $instance, 'get_recipient_batch', array( $first_id ) );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$this->assertSame( 1, $first['fetched'] );
		$this->assertFalse( $first['complete'] );
		$this->assertSame( $first_id, $first['last_id'] );
		$this->assertSame( $second_id, $second['recipients'][0]['user_id'] );
	}

	/**
	 * Check that users joining or leaving between batches are not skipped or duplicated.
	 *
	 * @covers ::get_recipient_batch
	 *
	 * @return void
	 */
	public function test_recipient_batch_handles_user_changes_with_cursor(): void {
		$cursor    = $this->max_user_id();
		$first_id  = $this->factory->user->create( array( 'display_name' => 'First' ) );
		$second_id = $this->factory->user->create( array( 'display_name' => 'Second' ) );
		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		$instance = Email::get_instance();

		$first = Utility::invoke_hidden_method( $instance, 'get_recipient_batch', array( $cursor ) );
		wp_delete_user( $second_id );
		$new_id = $this->factory->user->create( array( 'display_name' => 'Added' ) );
		$second = Utility::invoke_hidden_method( $instance, 'get_recipient_batch', array( $first_id ) );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$this->assertSame( array( $first_id ), array_column( $first['recipients'], 'user_id' ) );
		$this->assertSame( array( $new_id ), array_column( $second['recipients'], 'user_id' ) );
	}

	/**
	 * Check that a page smaller than the batch size reports the list complete.
	 *
	 * @covers ::get_recipient_batch
	 *
	 * @return void
	 */
	public function test_recipient_batch_marks_final_page_complete(): void {
		$this->factory->user->create( array( 'display_name' => 'Only' ) );

		$batch = Utility::invoke_hidden_method(
			Email::get_instance(),
			'get_recipient_batch',
			array( 0 )
		);

		$this->assertTrue( $batch['complete'] );
		$this->assertGreaterThan( 0, $batch['fetched'] );
		$this->assertLessThan( 50, $batch['fetched'] );
	}

	/**
	 * Check that a cursor past the last user returns an empty, complete page.
	 *
	 * @covers ::get_recipient_batch
	 *
	 * @return void
	 */
	public function test_recipient_batch_returns_empty_page_at_end(): void {
		$this->factory->user->create( array( 'display_name' => 'Only' ) );
		$cursor = PHP_INT_MAX;

		$batch = Utility::invoke_hidden_method(
			Email::get_instance(),
			'get_recipient_batch',
			array( $cursor )
		);

		$this->assertSame( array(), $batch['recipients'] );
		$this->assertSame( 0, $batch['fetched'] );
		$this->assertTrue( $batch['complete'] );
		$this->assertSame( $cursor, $batch['last_id'] );
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
		$this->assertSame( Email::ACTIVE_MONTHS, $count['args']['months']['enum'] );

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
	 * Check that the active window is read in months.
	 *
	 * @covers ::resolve_audience
	 *
	 * @return void
	 */
	public function test_resolve_audience_reads_active_months(): void {
		$this->assertSame(
			array(
				'type'   => 'active',
				'months' => 6,
			),
			$this->resolve_audience(
				array(
					'audience' => 'active',
					'months'   => 6,
				)
			)
		);
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
			return Utility::invoke_hidden_method( $instance, 'get_audience_query_args', array( $audience ) );
		};

		$this->assertSame( array(), $args( self::ALL_USERS ) );
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
					'type'   => 'active',
					'months' => 1,
				)
			)
		);
		wp_delete_comment( $rsvp_id, true );

		$this->assertSame(
			array( 'include' => array( 0 ) ),
			$args(
				array(
					'type'   => 'active',
					'months' => 1,
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

		$ids = static function ( int $months ): array {
			$active = Utility::invoke_hidden_method( Email::get_instance(), 'get_active_user_ids', array( $months ) );

			sort( $active );

			return $active;
		};

		$this->assertSame( array( $recent ), $ids( 6 ) );
		$this->assertSame( array( $recent, $older ), $ids( 12 ) );
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

		$this->assertSame( array( 'audience', 'roles', 'months' ), array_keys( $args ) );
		$this->assertSame( 'all', $args['audience']['default'] );
		$this->assertSame( 3, $args['months']['default'] );
	}

	/**
	 * Check that each audience narrows who a message counts and goes to.
	 *
	 * @covers ::count_recipients
	 * @covers ::get_recipient_batch
	 *
	 * @return void
	 */
	public function test_count_recipients_by_audience(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$editor   = $this->factory->user->create( array( 'role' => 'editor' ) );
		$author   = $this->factory->user->create( array( 'role' => 'author' ) );
		$instance = Email::get_instance();

		$this->rsvp( $event_id, $editor );

		$this->assertSame(
			2,
			$instance->count_recipients(
				array(
					'type'  => 'role',
					'roles' => array( 'editor', 'author' ),
				)
			)
		);
		$this->assertSame(
			1,
			$instance->count_recipients(
				array(
					'type'   => 'active',
					'months' => 1,
				)
			)
		);
		update_user_meta( $author, 'gatherpress_event_updates_opt_in', '0' );

		$this->assertSame(
			1,
			$instance->count_recipients(
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
	 * @covers ::schedule_message_batch
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
				'gatherpress_site_message_send',
				array(
					'Subject',
					'Message body',
					0,
					array(
						'type'  => 'role',
						'roles' => array( 'editor' ),
					),
				)
			)
		);
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
	}

	/**
	 * Check that the next batch keeps the audience of the one before it.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_carries_the_audience_to_the_next_batch(): void {
		$first    = $this->factory->user->create( array( 'role' => 'editor' ) );
		$audience = array(
			'type'  => 'role',
			'roles' => array( 'editor' ),
		);
		$sent     = array();

		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		add_filter(
			'pre_wp_mail',
			static function ( $previous, $atts ) use ( &$sent ): bool {
				$sent[] = $atts['to'];

				return true;
			},
			10,
			2
		);

		Email::get_instance()->process_message( 'Subject', 'Message', 0, $audience );

		remove_all_filters( 'pre_wp_mail' );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$this->assertSame( array( get_userdata( $first )->user_email ), $sent );
		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', $first, $audience ) )
		);
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
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
				'gatherpress_site_message_send',
				array( 'Subject', 'Message body', 0, self::ALL_USERS )
			)
		);
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
	}

	/**
	 * Check that the pre-enqueue filter can take over scheduling.
	 *
	 * @covers ::schedule_message_batch
	 *
	 * @return void
	 */
	public function test_schedule_message_batch_honors_short_circuit_filter(): void {
		$captured_args = array();
		add_filter(
			'gatherpress_mail_pre_enqueue_job',
			static function ( $short_circuit, $hook, $args ) use ( &$captured_args ) {
				$captured_args = array( $hook, $args );

				return 'action-scheduler-id';
			},
			10,
			3
		);

		$scheduled = Utility::invoke_hidden_method(
			Email::get_instance(),
			'schedule_message_batch',
			array( 'Subject', 'Message', 0 )
		);
		remove_all_filters( 'gatherpress_mail_pre_enqueue_job' );

		$this->assertTrue( $scheduled );
		$this->assertSame(
			array( 'gatherpress_site_message_send', array( 'Subject', 'Message', 0, self::ALL_USERS ) ),
			$captured_args
		);
		$this->assertFalse(
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', 0, self::ALL_USERS ) )
		);
	}

	/**
	 * Check that a new batch is scheduled with its cursor arguments.
	 *
	 * @covers ::schedule_message_batch
	 *
	 * @return void
	 */
	public function test_schedule_message_batch_queues_new_cursor(): void {
		$result = Utility::invoke_hidden_method(
			Email::get_instance(),
			'schedule_message_batch',
			array( 'Unique subject', 'Unique message', 99 )
		);

		$this->assertTrue( $result );
		$this->assertNotFalse(
			wp_next_scheduled(
				'gatherpress_site_message_send',
				array( 'Unique subject', 'Unique message', 99, self::ALL_USERS )
			)
		);
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
	}

	/**
	 * Check that a batch already queued for a cursor is not stacked twice.
	 *
	 * @covers ::schedule_message_batch
	 *
	 * @return void
	 */
	public function test_schedule_message_batch_skips_duplicate_cursor(): void {
		$instance = Email::get_instance();
		$args     = array( 'Subject', 'Message', 0, self::ALL_USERS );

		wp_schedule_single_event( time() + 60, 'gatherpress_site_message_send', $args );
		$scheduled_at = wp_next_scheduled( 'gatherpress_site_message_send', $args );

		$result = Utility::invoke_hidden_method(
			$instance,
			'schedule_message_batch',
			array( 'Subject', 'Message', 0 )
		);

		$this->assertTrue( $result );
		$this->assertSame(
			$scheduled_at,
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', 0, self::ALL_USERS ) )
		);
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
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
	 * Check that process_message sends one batch and queues the next cursor.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_sends_batch_and_queues_next_cursor(): void {
		$cursor = $this->max_user_id();
		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		$first_id  = $this->factory->user->create( array( 'display_name' => 'First' ) );
		$second_id = $this->factory->user->create( array( 'display_name' => 'Second' ) );
		$captured  = array();
		add_filter(
			'pre_wp_mail',
			static function ( $previous, $atts ) use ( &$captured ): bool {
				$captured[] = $atts;
				return true;
			},
			10,
			2
		);

		$instance = Email::get_instance();
		$instance->process_message( '', 'A site message', $cursor );
		remove_all_filters( 'pre_wp_mail' );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$recipient_emails = array_column( $captured, 'to' );
		$this->assertCount( 1, $captured );
		$this->assertNotContains( get_userdata( $second_id )->user_email, $recipient_emails );
		$this->assertStringContainsString( 'A site message', $captured[0]['message'] );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $captured[0]['subject'] );
		$this->assertNotFalse(
			wp_next_scheduled(
				'gatherpress_site_message_send',
				array( '', 'A site message', $first_id, self::ALL_USERS )
			)
		);
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
	}

	/**
	 * Check that a failed continuation enqueue is retried before delivery.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_retries_failed_continuation_enqueue(): void {
		$cursor = $this->max_user_id();
		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		$first_id = $this->factory->user->create( array( 'display_name' => 'First' ) );
		$this->factory->user->create( array( 'display_name' => 'Second' ) );
		$attempts        = 0;
		$schedule_filter = static function () use ( &$attempts ) {
			++$attempts;

			return 1 === $attempts ? false : null;
		};
		add_filter( 'pre_schedule_event', $schedule_filter );

		Email::get_instance()->process_message( 'Subject', 'Message', $cursor );
		remove_filter( 'pre_schedule_event', $schedule_filter );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$this->assertSame( 2, $attempts );
		$this->assertNotFalse(
			wp_next_scheduled(
				'gatherpress_site_message_send',
				array( 'Subject', 'Message', $first_id, self::ALL_USERS )
			)
		);
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
	}

	/**
	 * Check that the final batch sends without queuing another cursor.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_final_batch_does_not_reschedule(): void {
		$cursor = $this->max_user_id();
		$this->factory->user->create( array( 'display_name' => 'Only' ) );
		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $previous, $atts ) use ( &$captured ): bool {
				$captured[] = $atts;
				return true;
			},
			10,
			2
		);

		Email::get_instance()->process_message( 'Subject', 'Message', $cursor );
		remove_all_filters( 'pre_wp_mail' );

		$this->assertNotEmpty( $captured );
		$this->assertFalse(
			wp_next_scheduled( 'gatherpress_site_message_send' ),
			'No site message job should remain scheduled for any cursor after the final batch.'
		);
	}

	/**
	 * Check that an empty filtered batch still queues the next cursor.
	 *
	 * A filter can narrow one page away without ending the chain, so the next
	 * cursor is queued even when the current page has no recipients.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_continues_when_filter_empties_page(): void {
		$cursor   = $this->max_user_id();
		$first_id = $this->factory->user->create( array( 'display_name' => 'First' ) );
		$this->factory->user->create( array( 'display_name' => 'Second' ) );
		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		add_filter( 'gatherpress_site_message_recipients', '__return_empty_array' );

		Email::get_instance()->process_message( 'Subject', 'Message', $cursor );
		remove_all_filters( 'gatherpress_site_message_recipients' );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$this->assertNotFalse(
			wp_next_scheduled(
				'gatherpress_site_message_send',
				array( 'Subject', 'Message', $first_id, self::ALL_USERS )
			)
		);
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
	}

	/**
	 * Check that process_message skips a recipient who is no longer eligible.
	 *
	 * The audience filter can inject rows that `get_recipient_batch()` would not
	 * build itself, so delivery re-checks eligibility before rendering.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_skips_ineligible_recipient(): void {
		add_filter(
			'gatherpress_site_message_recipients',
			static function (): array {
				return array(
					array(
						'is_user'    => false,
						'user_id'    => 0,
						'comment_id' => 0,
						'email'      => '',
						'name'       => 'Empty Email',
					),
				);
			}
		);
		$mail_called = false;
		add_filter(
			'pre_wp_mail',
			static function () use ( &$mail_called ): bool {
				$mail_called = true;
				return true;
			}
		);

		Email::get_instance()->process_message( 'Subject', 'Message' );
		remove_all_filters( 'pre_wp_mail' );
		remove_all_filters( 'gatherpress_site_message_recipients' );

		$this->assertFalse( $mail_called );
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
	 * Check that the final batch clears the cached count.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_final_batch_clears_count_cache(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		$this->factory->user->create( array( 'display_name' => 'Only' ) );
		set_transient( $cache_key, 7, HOUR_IN_SECONDS );
		add_filter(
			'pre_wp_mail',
			static function (): bool {
				return true;
			}
		);

		Email::get_instance()->process_message( 'Subject', 'Message' );
		remove_all_filters( 'pre_wp_mail' );

		$this->assertFalse(
			get_transient( $cache_key ),
			'Final batch delivery should drop the cached count.'
		);
	}

	/**
	 * Check that the final batch keeps an unrelated cache untouched.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_keeps_cache_when_batch_continues(): void {
		$cache_key = 'gatherpress_site_message_count_' . get_current_blog_id();
		$cursor    = $this->max_user_id();
		$this->factory->user->create( array( 'display_name' => 'First' ) );
		$this->factory->user->create( array( 'display_name' => 'Second' ) );
		set_transient( $cache_key, 7, HOUR_IN_SECONDS );
		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		add_filter(
			'pre_wp_mail',
			static function (): bool {
				return true;
			}
		);

		Email::get_instance()->process_message( 'Subject', 'Message', $cursor );
		remove_all_filters( 'pre_wp_mail' );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );

		$this->assertSame(
			7,
			get_transient( $cache_key ),
			'A continued chain should leave the cached count alone.'
		);

		delete_transient( $cache_key );
	}

	/**
	 * Get the highest existing user ID before creating test users.
	 *
	 * @return int Highest existing user ID.
	 */
	private function max_user_id(): int {
		$users = get_users(
			array(
				'number'  => 1,
				'fields'  => array( 'ID' ),
				'orderby' => 'ID',
				'order'   => 'DESC',
			)
		);

		return empty( $users ) ? 0 : (int) $users[0]->ID;
	}

	/**
	 * Return a shared closure that limits one cron job to a single user.
	 *
	 * The same instance is returned on every call so the filter can be removed.
	 *
	 * @return Closure Batch size filter.
	 */
	private function batch_size_filter(): Closure {
		if ( null === $this->batch_size_filter ) {
			$this->batch_size_filter = static function (): int {
				return 1;
			};
		}

		return $this->batch_size_filter;
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
				'months'   => 3,
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
