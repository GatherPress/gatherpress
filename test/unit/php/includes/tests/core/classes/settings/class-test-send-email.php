<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Send_Email.
 *
 * @package GatherPress\Core\Settings
 * @since TBD
 */

namespace GatherPress\Tests\Core\Settings;

use Closure;
use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Send_Email;
use GatherPress\Core\Utility as Core_Utility;
use PMC\Unit_Test\Base_Ajax;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Send_Email.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Send_Email
 */
class Test_Send_Email extends Base_Ajax {

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
	 * Check that the send email template handles its count branches.
	 *
	 * @return void
	 */
	public function test_send_email_template_handles_recipient_count(): void {
		$template = GATHERPRESS_CORE_PATH . '/includes/templates/admin/settings/send-email.php';

		$this->assertEmpty( Core_Utility::render_template( $template ) );

		$singular = Core_Utility::render_template( $template, array( 'recipient_count' => 1 ) );
		$plural   = Core_Utility::render_template( $template, array( 'recipient_count' => 2 ) );

		$this->assertStringContainsString( 'This will email 1 member.', $singular );
		$this->assertStringContainsString( 'This will email 2 members.', $plural );
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
	public function test_count_recipients_pages_and_filters_opted_out_members(): void {
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

		$count = Send_Email::get_instance()->count_recipients();
		remove_all_filters( 'gatherpress_site_message_recipients' );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		// The excluded member is dropped, so only the two opted-in users count.
		$this->assertSame( 2, $count );
		// Every page of one was visited, and the next cursor is the first user ID.
		$this->assertContains( 0, $last_ids );
		$this->assertContains( $included_id, $last_ids );
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
		$instance = Send_Email::get_instance();

		$first  = Utility::invoke_hidden_method( $instance, 'get_recipient_batch', array( $cursor ) );
		$second = Utility::invoke_hidden_method( $instance, 'get_recipient_batch', array( $first_id ) );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$this->assertSame( 1, $first['fetched'] );
		$this->assertFalse( $first['complete'] );
		$this->assertSame( $first_id, $first['last_id'] );
		$this->assertSame( $second_id, $second['recipients'][0]['user_id'] );
	}

	/**
	 * Check that membership changes between batches do not skip or duplicate users.
	 *
	 * @covers ::get_recipient_batch
	 *
	 * @return void
	 */
	public function test_recipient_batch_handles_membership_changes_with_cursor(): void {
		$cursor    = $this->max_user_id();
		$first_id  = $this->factory->user->create( array( 'display_name' => 'First' ) );
		$second_id = $this->factory->user->create( array( 'display_name' => 'Second' ) );
		add_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );
		$instance = Send_Email::get_instance();

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
			Send_Email::get_instance(),
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
			Send_Email::get_instance(),
			'get_recipient_batch',
			array( $cursor )
		);

		$this->assertSame( array(), $batch['recipients'] );
		$this->assertSame( 0, $batch['fetched'] );
		$this->assertTrue( $batch['complete'] );
		$this->assertSame( $cursor, $batch['last_id'] );
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
		$_POST['nonce'] = wp_create_nonce( 'gatherpress_send_email_nonce' );
		$this->mock_input( 'Subject', 'Message body' );

		$response = $this->do_ajax( 'gatherpress_send_site_message' );
		$this->clear_input_mock();

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
		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message body', 0 ) )
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
			'gatherpress_site_message_pre_enqueue_job',
			static function ( $short_circuit, $hook, $args ) use ( &$captured_args ) {
				$captured_args = array( $hook, $args );

				return 'action-scheduler-id';
			},
			10,
			3
		);

		$scheduled = Utility::invoke_hidden_method(
			Send_Email::get_instance(),
			'schedule_message_batch',
			array( 'Subject', 'Message', 0 )
		);
		remove_all_filters( 'gatherpress_site_message_pre_enqueue_job' );

		$this->assertTrue( $scheduled );
		$this->assertSame(
			array( 'gatherpress_site_message_send', array( 'Subject', 'Message', 0 ) ),
			$captured_args
		);
		$this->assertFalse( wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', 0 ) ) );
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
			Send_Email::get_instance(),
			'schedule_message_batch',
			array( 'Unique subject', 'Unique message', 99 )
		);

		$this->assertTrue( $result );
		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Unique subject', 'Unique message', 99 ) )
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
		$instance = Send_Email::get_instance();
		wp_schedule_single_event( time() + 60, 'gatherpress_site_message_send', array( 'Subject', 'Message', 0 ) );
		$scheduled_at = wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', 0 ) );

		$result = Utility::invoke_hidden_method(
			$instance,
			'schedule_message_batch',
			array( 'Subject', 'Message', 0 )
		);

		$this->assertTrue( $result );
		$this->assertSame(
			$scheduled_at,
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', 0 ) )
		);
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

		$instance = Send_Email::get_instance();
		$instance->process_message( '', 'A site message', $cursor );
		remove_all_filters( 'pre_wp_mail' );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$recipient_emails = array_column( $captured, 'to' );
		$this->assertCount( 1, $captured );
		$this->assertNotContains( get_userdata( $second_id )->user_email, $recipient_emails );
		$this->assertStringContainsString( 'A site message', $captured[0]['message'] );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $captured[0]['subject'] );
		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_site_message_send', array( '', 'A site message', $first_id ) )
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

		Send_Email::get_instance()->process_message( 'Subject', 'Message', $cursor );
		remove_filter( 'pre_schedule_event', $schedule_filter );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$this->assertSame( 2, $attempts );
		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', $first_id ) )
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

		Send_Email::get_instance()->process_message( 'Subject', 'Message', $cursor );
		remove_all_filters( 'pre_wp_mail' );

		$this->assertNotEmpty( $captured );
		$this->assertFalse(
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', $cursor ) )
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

		Send_Email::get_instance()->process_message( 'Subject', 'Message', $cursor );
		remove_all_filters( 'gatherpress_site_message_recipients' );
		remove_filter( 'gatherpress_site_message_batch_size', $this->batch_size_filter() );

		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_site_message_send', array( 'Subject', 'Message', $first_id ) )
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
	 * Get the highest existing user ID before creating test members.
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
	 * Return a shared closure that limits one cron job to a single member.
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
