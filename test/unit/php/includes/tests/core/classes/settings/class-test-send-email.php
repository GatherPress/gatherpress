<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Send_Email.
 *
 * @package GatherPress\Core\Settings
 * @since TBD
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Send_Email;
use PMC\Unit_Test\Base_Ajax;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Send_Email.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Send_Email
 */
class Test_Send_Email extends Base_Ajax {

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Send_Email::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_settings_section',
				'priority' => 9,
				'callback' => array( $instance, 'settings_section' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_ajax_gatherpress_send_site_message',
				'priority' => 10,
				'callback' => array( $instance, 'ajax_send' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_site_message_send',
				'priority' => 10,
				'callback' => array( $instance, 'process_message' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$slug = Utility::invoke_hidden_method( Send_Email::get_instance(), 'get_slug' );

		$this->assertSame( 'send_email_settings', $slug );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$this->assertSame( 'Send Email', Utility::invoke_hidden_method( Send_Email::get_instance(), 'get_name' ) );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$priority = Utility::invoke_hidden_method( Send_Email::get_instance(), 'get_priority' );

		$this->assertSame( PHP_INT_MAX - 3, $priority );
	}

	/**
	 * Coverage for settings_section rendering the send email page.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_settings_section_renders_for_send_email_page(): void {
		$instance = Send_Email::get_instance();
		$response = Utility::buffer_and_return(
			array( $instance, 'settings_section' ),
			array( 'gatherpress_send_email_settings' )
		);

		$this->assertFalse(
			has_action( 'gatherpress_settings_section', array( Settings::get_instance(), 'render_settings_form' ) )
		);
		$this->assertStringContainsString( 'Send Email To Members', $response );
		$this->assertStringContainsString( 'gatherpress-message-body', $response );
	}

	/**
	 * Coverage for settings_section skipping another page.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_settings_section_skips_other_pages(): void {
		$response = Utility::buffer_and_return(
			array( Send_Email::get_instance(), 'settings_section' ),
			array( 'gatherpress_events' )
		);

		$this->assertEmpty( $response );
	}

	/**
	 * Check recipient filtering and the recipients filter.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_filters_opted_out_members(): void {
		$included_id = $this->factory->user->create( array( 'display_name' => 'Included' ) );
		$excluded_id = $this->factory->user->create( array( 'display_name' => 'Excluded' ) );
		update_user_meta( $excluded_id, 'gatherpress_event_updates_opt_in', '0' );
		$filtered = false;
		add_filter(
			'gatherpress_site_message_recipients',
			static function ( $recipients ) use ( &$filtered ) {
				$filtered = true;
				return $recipients;
			}
		);

		$recipients = Send_Email::get_instance()->get_recipients();
		remove_all_filters( 'gatherpress_site_message_recipients' );
		$ids = array_column( $recipients, 'user_id' );

		$this->assertTrue( $filtered );
		$this->assertContains( $included_id, $ids );
		$this->assertNotContains( $excluded_id, $ids );
	}

	/**
	 * Check that a user without manage_options is denied.
	 *
	 * @covers ::ajax_send
	 *
	 * @return void
	 */
	public function test_ajax_send_permission_denied(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->do_ajax( 'gatherpress_send_site_message' );

		$this->assertFalse( $response->success );
	}

	/**
	 * Check that an empty message is rejected.
	 *
	 * @covers ::ajax_send
	 *
	 * @return void
	 */
	public function test_ajax_send_rejects_empty_message(): void {
		$this->set_admin_user();
		$this->mock_input( '', '' );
		$response = $this->do_ajax( 'gatherpress_send_site_message' );
		$this->clear_input_mock();

		$this->assertFalse( $response->success );
	}

	/**
	 * Check that a message is rejected when no members are eligible.
	 *
	 * @covers ::ajax_send
	 *
	 * @return void
	 */
	public function test_ajax_send_rejects_empty_recipient_list(): void {
		$this->set_admin_user();
		$users = get_users();
		foreach ( $users as $user ) {
			update_user_meta( $user->ID, 'gatherpress_event_updates_opt_in', '0' );
		}
		$this->mock_input( 'Subject', 'Message' );
		$response = $this->do_ajax( 'gatherpress_send_site_message' );
		$this->clear_input_mock();

		$this->assertFalse( $response->success );
	}

	/**
	 * Check that a valid message is queued.
	 *
	 * @covers ::ajax_send
	 *
	 * @return void
	 */
	public function test_ajax_send_queues_message(): void {
		$this->set_admin_user();
		$this->mock_input( 'Subject', 'Message body' );
		$response = $this->do_ajax( 'gatherpress_send_site_message' );
		$this->clear_input_mock();

		$this->assertTrue( $response->success );
		$this->assertStringContainsString( 'Queued for', $response->data->message );
		wp_clear_scheduled_hook( 'gatherpress_site_message_send' );
	}

	/**
	 * Check that scheduling errors return a JSON error.
	 *
	 * @covers ::ajax_send
	 *
	 * @return void
	 */
	public function test_ajax_send_reports_schedule_failure(): void {
		$this->set_admin_user();
		$this->mock_input( 'Subject', 'Message body' );
		add_filter( 'pre_schedule_event', '__return_false' );
		$response = $this->do_ajax( 'gatherpress_send_site_message' );
		remove_filter( 'pre_schedule_event', '__return_false' );
		$this->clear_input_mock();

		$this->assertFalse( $response->success );
	}

	/**
	 * Check that process_message sends to eligible members and renders the body.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_sends_to_recipients(): void {
		$user_id  = $this->factory->user->create( array( 'display_name' => 'Recipient' ) );
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

		Send_Email::get_instance()->process_message( '', 'A site message' );
		remove_all_filters( 'pre_wp_mail' );

		$recipient_emails = array_column( $captured, 'to' );
		$this->assertContains( get_userdata( $user_id )->user_email, $recipient_emails );
		$this->assertStringContainsString( 'A site message', $captured[0]['message'] );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $captured[0]['subject'] );
	}

	/**
	 * Check that process_message exits when there is nobody to email.
	 *
	 * @covers ::process_message
	 *
	 * @return void
	 */
	public function test_process_message_returns_with_no_recipients(): void {
		add_filter( 'gatherpress_site_message_recipients', '__return_empty_array' );
		$mail_called = false;
		add_filter(
			'pre_wp_mail',
			static function () use ( &$mail_called ): bool {
				$mail_called = true;
				return true;
			}
		);

		Send_Email::get_instance()->process_message( 'Subject', 'Message' );
		remove_all_filters( 'pre_wp_mail' );
		remove_all_filters( 'gatherpress_site_message_recipients' );

		$this->assertFalse( $mail_called );
	}

	/**
	 * Check that process_message skips a recipient who is no longer eligible.
	 *
	 * The audience filter can inject rows that `get_recipients()` would not build
	 * itself, so delivery re-checks eligibility before rendering.
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

		Send_Email::get_instance()->process_message( 'Subject', 'Message' );
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
		$instance = Send_Email::get_instance();
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
	 * Set an administrator as the current user and provide a nonce.
	 *
	 * @return void
	 */
	private function set_admin_user(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );

		// The nonce is bound to the current user, so it must come after the switch.
		$_POST['nonce'] = wp_create_nonce( 'gatherpress_send_email_nonce' );
	}

	/**
	 * Mock subject and message HTTP input.
	 *
	 * @param string $subject Subject input.
	 * @param string $message Message input.
	 *
	 * @return void
	 */
	private function mock_input( string $subject, string $message ): void {
		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $subject, $message ): ?string {
				if ( INPUT_POST !== $type ) {
					return null;
				}
				return 'subject' === $var_name ? $subject : ( 'message' === $var_name ? $message : null );
			},
			10,
			3
		);
	}

	/**
	 * Remove mocked HTTP input and nonce data.
	 *
	 * @return void
	 */
	private function clear_input_mock(): void {
		remove_all_filters( 'gatherpress_pre_get_http_input' );
		unset( $_POST['nonce'] );
	}
}
