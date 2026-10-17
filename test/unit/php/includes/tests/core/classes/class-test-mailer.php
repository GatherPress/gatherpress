<?php
/**
 * Class handles unit tests for GatherPress\Core\Mailer.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core;

use Exception;
use GatherPress\Core\Mailer;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Mailer.
 *
 * @coversDefaultClass \GatherPress\Core\Mailer
 */
class Test_Mailer extends Base {

	/**
	 * Mail types registered by a test, removed again after it.
	 *
	 * @var string[]
	 */
	private array $registered = array();

	/**
	 * Emails sent during a test, by recipient address.
	 *
	 * @var string[]
	 */
	private array $sent = array();

	/**
	 * Capture outgoing mail instead of sending it.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->sent = array();

		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	/**
	 * Remove the test's mail types, mail capture and queued jobs.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$mailer = Mailer::get_instance();
		$types  = Utility::get_hidden_property( $mailer, 'types' );

		foreach ( $this->registered as $type ) {
			unset( $types[ $type ] );
		}

		Utility::set_and_get_hidden_property( $mailer, 'types', $types );
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ) );
		remove_all_filters( 'gatherpress_email_batch_size' );
		remove_all_filters( 'gatherpress_email_recipients' );
		wp_unschedule_hook( Mailer::JOB_HOOK );

		parent::tear_down();
	}

	/**
	 * Record an outgoing email and stop it from being sent.
	 *
	 * @param mixed                $previous Short-circuit value.
	 * @param array<string, mixed> $atts     The wp_mail() arguments.
	 *
	 * @return bool True, so wp_mail() reports success.
	 */
	public function capture_mail( $previous, array $atts ): bool {
		$this->sent[] = $atts['to'];

		return true;
	}

	/**
	 * Check that users are eligible by default.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_accepts_user_by_default(): void {
		$user_id = $this->factory->user->create();

		$this->assertTrue(
			Utility::invoke_hidden_method(
				Mailer::get_instance(),
				'is_eligible',
				array(
					$this->recipient(
						array(
							'user_id' => $user_id,
							'email'   => get_userdata( $user_id )->user_email,
						)
					),
				)
			)
		);
	}

	/**
	 * Check that opted-out users are rejected.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_rejects_opted_out_user(): void {
		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '0' );

		$this->assertFalse(
			Utility::invoke_hidden_method(
				Mailer::get_instance(),
				'is_eligible',
				array( $this->recipient( array( 'user_id' => $user_id ) ) )
			)
		);
	}

	/**
	 * Check that anonymous RSVP recipients honor comment opt-out meta.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_rejects_opted_out_comment(): void {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $this->factory->post->create(),
				'comment_author'       => 'Anonymous',
				'comment_author_email' => 'anonymous@example.test',
				'comment_approved'     => 1,
			)
		);
		update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', '0' );

		$this->assertFalse(
			Utility::invoke_hidden_method(
				Mailer::get_instance(),
				'is_eligible',
				array(
					$this->recipient(
						array(
							'is_user'    => false,
							'comment_id' => $comment_id,
							'email'      => 'anonymous@example.test',
						)
					),
				)
			)
		);
	}

	/**
	 * Check that anonymous RSVP recipients without opt-out meta are eligible.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_accepts_comment_without_opt_out(): void {
		$this->assertTrue(
			Utility::invoke_hidden_method(
				Mailer::get_instance(),
				'is_eligible',
				array(
					$this->recipient(
						array(
							'is_user' => false,
							'email'   => 'anonymous@example.test',
						)
					),
				)
			)
		);
	}

	/**
	 * Check that non-user recipients keep the current context.
	 *
	 * @covers ::switch_context
	 *
	 * @return void
	 */
	public function test_switch_context_skips_non_user(): void {
		$before = get_current_user_id();

		$this->assertFalse(
			Utility::invoke_hidden_method(
				Mailer::get_instance(),
				'switch_context',
				array( $this->recipient( array( 'is_user' => false ) ) )
			)
		);
		$this->assertSame( $before, get_current_user_id() );
	}

	/**
	 * Check that user recipients become the current user.
	 *
	 * @covers ::switch_context
	 *
	 * @return void
	 */
	public function test_switch_context_sets_user(): void {
		$user_id = $this->factory->user->create();

		$this->assertFalse(
			Utility::invoke_hidden_method(
				Mailer::get_instance(),
				'switch_context',
				array( $this->recipient( array( 'user_id' => $user_id ) ) )
			)
		);
		$this->assertSame( $user_id, get_current_user_id() );
	}

	/**
	 * Check that restoring context makes the sender current again.
	 *
	 * @covers ::restore_context
	 *
	 * @return void
	 */
	public function test_restore_context_sets_sender(): void {
		$sender_id = $this->factory->user->create();
		$target_id = $this->factory->user->create();
		wp_set_current_user( $target_id );

		Utility::invoke_hidden_method(
			Mailer::get_instance(),
			'restore_context',
			array( false, get_userdata( $sender_id ) )
		);

		$this->assertSame( $sender_id, get_current_user_id() );
	}

	/**
	 * Check that delivery normalizes the subject and passes headers to wp_mail.
	 *
	 * @covers ::deliver
	 *
	 * @return void
	 */
	public function test_deliver_normalizes_subject_and_headers(): void {
		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $previous, $atts ) use ( &$captured ): bool {
				$captured = $atts;
				return true;
			},
			10,
			2
		);

		$result = Utility::invoke_hidden_method(
			Mailer::get_instance(),
			'deliver',
			array(
				'recipient@example.test',
				"Tom &amp; Jerry\\'s update",
				'<p>Message</p>',
				array( 'Content-Type: text/html; charset=UTF-8' ),
			)
		);

		remove_all_filters( 'pre_wp_mail' );

		$this->assertTrue( $result );
		$this->assertSame( "Tom & Jerry's update", $captured['subject'] );
		$this->assertSame( array( 'Content-Type: text/html; charset=UTF-8' ), $captured['headers'] );
	}

	/**
	 * Check that delivery returns false when wp_mail rejects the message.
	 *
	 * @covers ::deliver
	 *
	 * @return void
	 */
	public function test_deliver_returns_false_when_mail_fails(): void {
		add_filter( 'pre_wp_mail', '__return_false' );
		$result = Utility::invoke_hidden_method(
			Mailer::get_instance(),
			'deliver',
			array( 'recipient@example.test', 'Subject', 'Body' )
		);
		remove_filter( 'pre_wp_mail', '__return_false' );

		$this->assertFalse( $result );
	}

	/**
	 * Check that consent defaults to the recipient's opt-in.
	 *
	 * @covers ::has_consent
	 *
	 * @return void
	 */
	public function test_has_consent_defaults_to_eligibility(): void {
		$user_id = $this->factory->user->create();

		$this->assertTrue(
			$this->has_consent(
				$this->recipient( array( 'user_id' => $user_id ) ),
				'site_message'
			)
		);
	}

	/**
	 * Check that consent returns false when the recipient is not eligible.
	 *
	 * @covers ::has_consent
	 *
	 * @return void
	 */
	public function test_has_consent_follows_ineligible_recipient(): void {
		$this->assertFalse(
			$this->has_consent(
				$this->recipient( array( 'email' => '' ) ),
				'site_message'
			)
		);
	}

	/**
	 * Check that the consent filter can grant consent to a recipient who opted out.
	 *
	 * @covers ::has_consent
	 *
	 * @return void
	 */
	public function test_has_consent_filter_can_grant(): void {
		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '0' );

		add_filter( 'gatherpress_email_recipient_consent', '__return_true' );

		$consent = $this->has_consent(
			$this->recipient( array( 'user_id' => $user_id ) ),
			'event'
		);

		remove_all_filters( 'gatherpress_email_recipient_consent' );

		$this->assertTrue( $consent );
	}

	/**
	 * Check that the consent filter cannot grant consent to a recipient without an email.
	 *
	 * @covers ::has_consent
	 *
	 * @return void
	 */
	public function test_has_consent_filter_cannot_grant_without_email(): void {
		add_filter( 'gatherpress_email_recipient_consent', '__return_true' );

		$consent = $this->has_consent(
			$this->recipient( array( 'email' => '' ) ),
			'event'
		);

		remove_all_filters( 'gatherpress_email_recipient_consent' );

		$this->assertFalse( $consent );
	}

	/**
	 * Check that the consent filter cannot grant consent to a malformed address.
	 *
	 * @covers ::has_consent
	 *
	 * @return void
	 */
	public function test_has_consent_filter_cannot_grant_malformed_email(): void {
		add_filter( 'gatherpress_email_recipient_consent', '__return_true' );

		$consent = $this->has_consent(
			$this->recipient( array( 'email' => 'jane@example' ) ),
			'event'
		);

		remove_all_filters( 'gatherpress_email_recipient_consent' );

		$this->assertFalse( $consent );
	}

	/**
	 * Check that the consent filter can withhold consent from an eligible recipient.
	 *
	 * @covers ::has_consent
	 *
	 * @return void
	 */
	public function test_has_consent_filter_can_withhold(): void {
		$user_id  = $this->factory->user->create();
		$captured = array();
		add_filter(
			'gatherpress_email_recipient_consent',
			static function ( $consent, $recipient, $context ) use ( &$captured ) {
				$captured = array( $consent, $context );

				return false;
			},
			10,
			3
		);

		$consent = $this->has_consent(
			$this->recipient( array( 'user_id' => $user_id ) ),
			'site_message'
		);

		remove_all_filters( 'gatherpress_email_recipient_consent' );

		$this->assertFalse( $consent );
		$this->assertSame( array( true, 'site_message' ), $captured );
	}

	/**
	 * Check that emails about a recipient's own RSVP skip the opt-in.
	 *
	 * @covers ::has_consent
	 *
	 * @return void
	 */
	public function test_has_consent_rsvp_skips_opt_in(): void {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $this->factory->post->create(),
				'comment_author'       => 'Anonymous',
				'comment_author_email' => 'anonymous@example.test',
			)
		);
		update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', '0' );

		$recipient = $this->recipient(
			array(
				'is_user'    => false,
				'comment_id' => $comment_id,
				'email'      => 'anonymous@example.test',
			)
		);

		$this->assertTrue( $this->has_consent( $recipient, 'rsvp' ), 'RSVP emails should skip the opt-in.' );
		$this->assertFalse( $this->has_consent( $recipient, 'event' ), 'Other emails should follow the opt-in.' );
		$this->assertFalse(
			$this->has_consent( $this->recipient( array( 'email' => '' ) ), 'rsvp' ),
			'RSVP emails should still need an address.'
		);
	}

	/**
	 * Check that the consent filter can withhold an RSVP email.
	 *
	 * @covers ::has_consent
	 *
	 * @return void
	 */
	public function test_has_consent_filter_can_withhold_rsvp(): void {
		$user_id  = $this->factory->user->create();
		$captured = null;
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '0' );
		add_filter(
			'gatherpress_email_recipient_consent',
			static function ( $consent ) use ( &$captured ) {
				$captured = $consent;

				return false;
			}
		);

		$consent = $this->has_consent(
			$this->recipient( array( 'user_id' => $user_id ) ),
			'rsvp'
		);

		remove_all_filters( 'gatherpress_email_recipient_consent' );

		$this->assertFalse( $consent );
		$this->assertTrue( $captured, 'The filter should receive consent for an RSVP email.' );
	}

	/**
	 * Check that bulk_send sends to each recipient who consents.
	 *
	 * @covers ::bulk_send
	 *
	 * @return void
	 */
	public function test_bulk_send(): void {
		$opted_in  = $this->factory->user->create();
		$opted_out = $this->factory->user->create();
		$composed  = array();
		$sent      = array();

		update_user_meta( $opted_out, 'gatherpress_event_updates_opt_in', '0' );
		add_filter(
			'pre_wp_mail',
			static function ( $previous, $atts ) use ( &$sent ): bool {
				$sent[] = $atts['to'];

				return true;
			},
			10,
			2
		);

		$count = Mailer::get_instance()->bulk_send(
			array(
				$this->recipient(
					array(
						'user_id' => $opted_in,
						'email'   => 'in@example.test',
					)
				),
				$this->recipient(
					array(
						'user_id' => $opted_out,
						'email'   => 'out@example.test',
					)
				),
			),
			static function ( array $recipient ) use ( &$composed ): array {
				$composed[] = $recipient['email'];

				return array(
					'subject' => 'Subject',
					'body'    => 'Body',
				);
			}
		);

		remove_all_filters( 'pre_wp_mail' );

		$this->assertSame( 1, $count );
		$this->assertSame( array( 'in@example.test' ), $sent );
		$this->assertSame( array( 'in@example.test' ), $composed, 'Only consenting recipients get an email built.' );
	}

	/**
	 * Check that send composes and delivers for an eligible recipient.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_composes_and_delivers(): void {
		$user_id  = $this->factory->user->create();
		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $previous, $atts ) use ( &$captured ): bool {
				$captured = $atts;
				return true;
			},
			10,
			2
		);

		$result = Mailer::get_instance()->send(
			$this->recipient( array( 'user_id' => $user_id ) ),
			static fn(): array => array(
				'subject' => 'Hello',
				'body'    => '<p>Body</p>',
			)
		);

		remove_all_filters( 'pre_wp_mail' );

		$this->assertTrue( $result );
		$this->assertSame( 'recipient@example.test', $captured['to'] );
		$this->assertSame( 'Hello', $captured['subject'] );
	}

	/**
	 * Check that send passes composed headers through to wp_mail.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_forwards_composed_headers(): void {
		$user_id  = $this->factory->user->create();
		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $previous, $atts ) use ( &$captured ): bool {
				$captured = $atts;
				return true;
			},
			10,
			2
		);

		$result = Mailer::get_instance()->send(
			$this->recipient( array( 'user_id' => $user_id ) ),
			static fn(): array => array(
				'subject' => 'Hello',
				'body'    => '<p>Body</p>',
				'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
			)
		);

		remove_all_filters( 'pre_wp_mail' );

		$this->assertTrue( $result );
		$this->assertSame(
			array( 'Content-Type: text/html; charset=UTF-8' ),
			$captured['headers']
		);
	}

	/**
	 * Check that send returns false without composing when consent is withheld.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_skips_when_consent_withheld(): void {
		$user_id  = $this->factory->user->create();
		$composed = false;
		add_filter( 'gatherpress_email_recipient_consent', '__return_false' );

		$result = Mailer::get_instance()->send(
			$this->recipient( array( 'user_id' => $user_id ) ),
			static function () use ( &$composed ): array {
				$composed = true;

				return array(
					'subject' => 'Hello',
					'body'    => 'Body',
				);
			}
		);

		remove_all_filters( 'gatherpress_email_recipient_consent' );

		$this->assertFalse( $result );
		$this->assertFalse( $composed );
	}

	/**
	 * Check that send restores the sender when the compose callback throws.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_restores_context_when_compose_throws(): void {
		$sender_id = $this->factory->user->create();
		$target_id = $this->factory->user->create();
		wp_set_current_user( $sender_id );

		try {
			Mailer::get_instance()->send(
				$this->recipient( array( 'user_id' => $target_id ) ),
				static function (): array {
					throw new Exception( 'Compose failed.' );
				}
			);
			$this->fail( 'Expected the compose exception to propagate.' );
		} catch ( Exception $exception ) {
			$this->assertSame( 'Compose failed.', $exception->getMessage() );
		}

		$this->assertSame( $sender_id, get_current_user_id() );
	}

	/**
	 * Check that schedule queues a single event when none is queued.
	 *
	 * @covers ::schedule
	 *
	 * @return void
	 */
	public function test_schedule_queues_job(): void {
		$args = array( 'Subject', 'Message', 7 );

		$result = $this->schedule( 'gatherpress_test_mail_job', $args );

		$this->assertTrue( $result );
		$this->assertNotFalse( wp_next_scheduled( 'gatherpress_test_mail_job', $args ) );
	}

	/**
	 * Check that schedule does not stack a duplicate job for the same args.
	 *
	 * @covers ::schedule
	 *
	 * @return void
	 */
	public function test_schedule_deduplicates(): void {
		$args = array( 'Subject', 'Message', 8 );
		wp_schedule_single_event( time() + 60, 'gatherpress_test_mail_job', $args );
		$scheduled_at = wp_next_scheduled( 'gatherpress_test_mail_job', $args );

		$result = $this->schedule( 'gatherpress_test_mail_job', $args );

		$this->assertTrue( $result );
		$this->assertSame( $scheduled_at, wp_next_scheduled( 'gatherpress_test_mail_job', $args ) );
	}

	/**
	 * Check that the pre-enqueue filter short-circuits scheduling.
	 *
	 * @covers ::schedule
	 *
	 * @return void
	 */
	public function test_schedule_honors_short_circuit_filter(): void {
		$captured = array();
		add_filter(
			'gatherpress_email_pre_enqueue_job',
			static function ( $short_circuit, $hook, $args ) use ( &$captured ) {
				$captured = array( $short_circuit, $hook, $args );

				return true;
			},
			10,
			3
		);

		$result = $this->schedule( 'gatherpress_test_mail_job', array( 'A', 'B', 9 ) );

		remove_all_filters( 'gatherpress_email_pre_enqueue_job' );

		$this->assertTrue( $result );
		$this->assertSame( array( null, 'gatherpress_test_mail_job', array( 'A', 'B', 9 ) ), $captured );
		$this->assertFalse( wp_next_scheduled( 'gatherpress_test_mail_job', array( 'A', 'B', 9 ) ) );
	}

	/**
	 * Check that schedule returns false when both native attempts fail.
	 *
	 * @covers ::schedule
	 *
	 * @return void
	 */
	public function test_schedule_returns_false_when_enqueue_fails(): void {
		add_filter( 'pre_schedule_event', '__return_false' );

		$result = $this->schedule( 'gatherpress_test_mail_job', array( 'A', 'B', 10 ) );

		remove_all_filters( 'pre_schedule_event' );

		$this->assertFalse( $result );
	}

	/**
	 * Check that schedule retries once when the first enqueue fails.
	 *
	 * @covers ::schedule
	 *
	 * @return void
	 */
	public function test_schedule_retries_once(): void {
		$attempts = 0;
		$filter   = static function ( $pre ) use ( &$attempts ) {
			++$attempts;

			return 1 === $attempts ? false : $pre;
		};
		add_filter( 'pre_schedule_event', $filter );

		$result = $this->schedule( 'gatherpress_test_mail_job', array( 'A', 'B', 11 ) );

		remove_all_filters( 'pre_schedule_event' );

		$this->assertTrue( $result );
		$this->assertSame( 2, $attempts );
	}

	/**
	 * Check that the job hook runs the job handler.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Mailer::get_instance();

		$this->assert_hooks(
			array(
				array(
					'type'     => 'action',
					'name'     => Mailer::JOB_HOOK,
					'priority' => 10,
					'callback' => array( $instance, 'process_job' ),
				),
			),
			$instance
		);
	}

	/**
	 * Check that queue() schedules the first batch of a registered type only.
	 *
	 * @covers ::register
	 * @covers ::queue
	 *
	 * @return void
	 */
	public function test_queue(): void {
		$type    = $this->register_type();
		$payload = array( 'message' => 'Hello' );

		$this->assertTrue( Mailer::get_instance()->queue( $type, $payload ) );
		$this->assertNotFalse( wp_next_scheduled( Mailer::JOB_HOOK, array( $type, $payload, Mailer::FIRST_BATCH ) ) );
		$this->assertFalse(
			Mailer::get_instance()->queue( 'gatherpress_unknown_type', $payload ),
			'An unknown type should not be queued.'
		);
	}

	/**
	 * Check that count() walks every batch and only counts recipients who consent.
	 *
	 * @covers ::count
	 * @covers ::get_batch
	 *
	 * @return void
	 */
	public function test_count(): void {
		$users = $this->factory->user->create_many( 3 );
		$guest = $this->recipient(
			array(
				'is_user'    => false,
				'comment_id' => 1,
				'email'      => 'guest@example.test',
			)
		);

		update_user_meta( $users[1], 'gatherpress_event_updates_opt_in', '0' );
		add_filter( 'gatherpress_email_batch_size', array( $this, 'one_per_batch' ) );

		$type = $this->register_type(
			array(
				'users'      => static fn(): array => array( 'include' => $users ),
				'recipients' => static fn(): array => array( $guest ),
			)
		);

		$this->assertSame( 3, Mailer::get_instance()->count( $type, array() ) );
		$this->assertSame( 0, Mailer::get_instance()->count( 'gatherpress_unknown_type', array() ) );
	}

	/**
	 * Check that a job sends one batch of users and queues the batch after it.
	 *
	 * @covers ::process_job
	 * @covers ::get_batch
	 *
	 * @return void
	 */
	public function test_process_job_sends_one_batch_and_queues_the_next(): void {
		$users = $this->factory->user->create_many( 2 );

		add_filter( 'gatherpress_email_batch_size', array( $this, 'one_per_batch' ) );

		$type = $this->register_type(
			array( 'users' => static fn(): array => array( 'include' => $users ) )
		);

		Mailer::get_instance()->process_job( $type, array() );

		$this->assertSame( array( get_userdata( $users[0] )->user_email ), $this->sent );
		$this->assertNotFalse(
			wp_next_scheduled(
				Mailer::JOB_HOOK,
				array(
					$type,
					array(),
					array(
						'source'  => 'users',
						'last_id' => $users[0],
					),
				)
			)
		);
	}

	/**
	 * Check that a job pages through the recipient list and stops after it.
	 *
	 * @covers ::process_job
	 * @covers ::get_batch
	 *
	 * @return void
	 */
	public function test_process_job_pages_through_the_recipient_list(): void {
		$list = array(
			$this->recipient(
				array(
					'is_user'    => false,
					'comment_id' => 1,
					'email'      => 'one@example.test',
				)
			),
			$this->recipient(
				array(
					'is_user'    => false,
					'comment_id' => 2,
					'email'      => 'two@example.test',
				)
			),
			$this->recipient(
				array(
					'is_user'    => false,
					'comment_id' => 3,
					'email'      => 'three@example.test',
				)
			),
		);
		$next = array(
			'source'  => 'list',
			'last_id' => 2,
		);

		add_filter( 'gatherpress_email_batch_size', static fn(): int => 2 );

		$type = $this->register_type( array( 'recipients' => static fn(): array => $list ) );

		Mailer::get_instance()->process_job( $type, array() );

		$this->assertSame( array( 'one@example.test', 'two@example.test' ), $this->sent );
		$this->assertNotFalse( wp_next_scheduled( Mailer::JOB_HOOK, array( $type, array(), $next ) ) );

		wp_unschedule_hook( Mailer::JOB_HOOK );
		Mailer::get_instance()->process_job( $type, array(), $next );

		$this->assertSame( array( 'one@example.test', 'two@example.test', 'three@example.test' ), $this->sent );
		$this->assertFalse( wp_next_scheduled( Mailer::JOB_HOOK, array( $type, array(), $next ) ) );
		$this->assertEmpty( $this->queued_jobs(), 'The last batch should not queue another.' );
	}

	/**
	 * Check that a queued email reaches every user and then the recipient list.
	 *
	 * @covers ::queue
	 * @covers ::process_job
	 * @covers ::get_batch
	 * @covers ::get_list_start
	 *
	 * @return void
	 */
	public function test_queued_email_reaches_users_then_the_list(): void {
		$users = $this->factory->user->create_many( 3 );

		add_filter( 'gatherpress_email_batch_size', array( $this, 'one_per_batch' ) );

		$type = $this->register_type(
			array(
				'users'      => static fn(): array => array( 'include' => $users ),
				'recipients' => static fn(): array => array(
					array(
						'is_user'    => false,
						'user_id'    => 0,
						'comment_id' => 1,
						'email'      => 'guest@example.test',
						'name'       => 'Guest',
					),
				),
			)
		);

		Mailer::get_instance()->queue( $type, array() );
		$this->run_mail_jobs();

		$expected = array_map( static fn( int $user_id ): string => get_userdata( $user_id )->user_email, $users );

		$expected[] = 'guest@example.test';

		$this->assertSame( $expected, $this->sent );
	}

	/**
	 * Check that list rows are paged by comment ID, so changes between batches do not shift them.
	 *
	 * @covers ::process_job
	 * @covers ::get_batch
	 *
	 * @return void
	 */
	public function test_list_cursor_survives_rows_changing_between_batches(): void {
		$row     = fn( int $comment_id ): array => $this->recipient(
			array(
				'is_user'    => false,
				'comment_id' => $comment_id,
				'email'      => sprintf( 'rsvp%d@example.test', $comment_id ),
			)
		);
		$current = array( $row( 30 ), $row( 10 ), $row( 20 ) );

		add_filter( 'gatherpress_email_batch_size', static fn(): int => 2 );

		$type = $this->register_type(
			array(
				'recipients' => static function () use ( &$current ): array {
					return $current;
				},
			)
		);

		Mailer::get_instance()->process_job( $type, array() );

		$this->assertSame( array( 'rsvp10@example.test', 'rsvp20@example.test' ), $this->sent );

		// The first row leaves and a new RSVP arrives before the next batch runs.
		$current = array( $row( 20 ), $row( 30 ), $row( 40 ) );

		wp_unschedule_hook( Mailer::JOB_HOOK );
		Mailer::get_instance()->process_job(
			$type,
			array(),
			array(
				'source'  => 'list',
				'last_id' => 20,
			)
		);

		$this->assertSame(
			array( 'rsvp10@example.test', 'rsvp20@example.test', 'rsvp30@example.test', 'rsvp40@example.test' ),
			$this->sent,
			'Nobody should be skipped or mailed twice.'
		);
	}

	/**
	 * Check that count() looks a type's sources up once, while each job looks them up again.
	 *
	 * @covers ::count
	 * @covers ::process_job
	 *
	 * @return void
	 */
	public function test_count_looks_sources_up_once(): void {
		$users   = $this->factory->user->create_many( 3 );
		$lookups = 0;

		add_filter( 'gatherpress_email_batch_size', array( $this, 'one_per_batch' ) );

		$type = $this->register_type(
			array(
				'users' => static function () use ( $users, &$lookups ): array {
					++$lookups;

					return array( 'include' => $users );
				},
			)
		);

		$this->assertSame( 3, Mailer::get_instance()->count( $type, array() ) );
		$this->assertSame( 1, $lookups );

		Mailer::get_instance()->process_job( $type, array() );

		$this->assertSame( 2, $lookups, 'A job should look its recipients up fresh.' );
	}

	/**
	 * Check that resolved sources answer from one lookup.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called by short delegation.
	 *
	 * @covers ::resolve_sources
	 *
	 * @return void
	 */
	public function test_resolve_sources(): void {
		$lookups    = 0;
		$definition = Utility::invoke_hidden_method(
			Mailer::get_instance(),
			'resolve_sources',
			array(
				array(
					'context'    => 'event',
					'compose'    => '__return_empty_array',
					'users'      => static function () use ( &$lookups ): array {
						++$lookups;

						return array( 'role__in' => array( 'editor' ) );
					},
					'recipients' => static function () use ( &$lookups ): array {
						++$lookups;

						return array();
					},
				),
				array(),
			)
		);

		$this->assertSame( 2, $lookups );
		$this->assertSame( array( 'role__in' => array( 'editor' ) ), $definition['users']() );
		$this->assertSame( array(), $definition['recipients']() );
		$this->assertSame( 2, $lookups, 'The resolved sources should not look up again.' );

		$plain = array(
			'context' => 'event',
			'compose' => '__return_empty_array',
		);

		$this->assertSame(
			$plain,
			Utility::invoke_hidden_method( Mailer::get_instance(), 'resolve_sources', array( $plain, array() ) ),
			'A type without sources should come back as it was.'
		);
	}

	/**
	 * Check that a job for an unknown type does nothing.
	 *
	 * @covers ::process_job
	 *
	 * @return void
	 */
	public function test_process_job_skips_an_unknown_type(): void {
		Mailer::get_instance()->process_job( 'gatherpress_unknown_type', array() );

		$this->assertEmpty( $this->sent );
		$this->assertEmpty( $this->queued_jobs() );
	}

	/**
	 * Check that a filter emptying one batch does not end the job.
	 *
	 * @covers ::process_job
	 * @covers ::get_batch
	 *
	 * @return void
	 */
	public function test_process_job_keeps_going_when_a_filter_empties_a_batch(): void {
		$users    = $this->factory->user->create_many( 2 );
		$captured = array();

		add_filter( 'gatherpress_email_batch_size', array( $this, 'one_per_batch' ) );
		add_filter(
			'gatherpress_email_recipients',
			static function ( array $recipients, string $type, array $payload ) use ( &$captured ): array {
				$captured = array( $type, $payload );

				return array();
			},
			10,
			3
		);

		$type = $this->register_type(
			array( 'users' => static fn(): array => array( 'include' => $users ) )
		);

		Mailer::get_instance()->process_job( $type, array( 'message' => 'Hi' ) );

		$this->assertEmpty( $this->sent );
		$this->assertSame( array( $type, array( 'message' => 'Hi' ) ), $captured );
		$this->assertCount( 1, $this->queued_jobs(), 'The next batch should still be queued.' );
	}

	/**
	 * Check that a recipient a filter adds is still held to consent.
	 *
	 * @covers ::process_job
	 *
	 * @return void
	 */
	public function test_process_job_skips_a_recipient_without_consent(): void {
		$opted_out = $this->factory->user->create();

		update_user_meta( $opted_out, 'gatherpress_event_updates_opt_in', '0' );
		add_filter(
			'gatherpress_email_recipients',
			fn(): array => array(
				$this->recipient(
					array(
						'user_id' => $opted_out,
						'email'   => 'out@example.test',
					)
				),
			)
		);

		$type = $this->register_type( array( 'recipients' => static fn(): array => array() ) );

		Mailer::get_instance()->process_job( $type, array() );

		$this->assertEmpty( $this->sent );
	}

	/**
	 * Check that users are paged by ID, so pages do not shift between batches.
	 *
	 * @covers ::get_user_batch
	 *
	 * @return void
	 */
	public function test_get_user_batch(): void {
		$users = $this->factory->user->create_many( 3 );
		$page  = static fn( int $last_id ): array => Utility::invoke_hidden_method(
			Mailer::get_instance(),
			'get_user_batch',
			array( array( 'include' => $users ), $last_id, 2 )
		);

		$first = $page( 0 );

		$this->assertSame( array( $users[0], $users[1] ), wp_list_pluck( $first['recipients'], 'user_id' ) );
		$this->assertFalse( $first['complete'] );
		$this->assertSame( $users[1], $first['last_id'] );

		// A user leaving after the first page does not shift the second.
		wp_delete_user( $users[0] );

		$second = $page( $first['last_id'] );

		$this->assertSame( array( $users[2] ), wp_list_pluck( $second['recipients'], 'user_id' ) );
		$this->assertTrue( $second['complete'] );

		$after = $page( $users[2] );

		$this->assertSame( array(), $after['recipients'] );
		$this->assertSame( $users[2], $after['last_id'], 'An empty page keeps the cursor.' );
		$this->assertTrue( $after['complete'] );
	}

	/**
	 * Check that a batch starting in the list skips the users.
	 *
	 * @covers ::get_batch
	 *
	 * @return void
	 */
	public function test_get_batch_from_the_list(): void {
		$row  = $this->recipient(
			array(
				'comment_id' => 5,
				'email'      => 'list@example.test',
			)
		);
		$type = $this->register_type(
			array(
				'users'      => static fn(): array => array(),
				'recipients' => static fn(): array => array( $row ),
			)
		);

		$batch = Utility::invoke_hidden_method(
			Mailer::get_instance(),
			'get_batch',
			array(
				$type,
				Utility::get_hidden_property( Mailer::get_instance(), 'types' )[ $type ],
				array(),
				array(
					'source'  => 'list',
					'last_id' => 0,
				),
			)
		);

		$this->assertSame( array( $row ), $batch['recipients'] );
		$this->assertNull( $batch['next'] );
	}

	/**
	 * Check where a type's recipient list starts.
	 *
	 * @covers ::get_list_start
	 *
	 * @return void
	 */
	public function test_get_list_start(): void {
		$mailer = Mailer::get_instance();

		$this->assertSame(
			array(
				'source'  => 'list',
				'last_id' => 0,
			),
			Utility::invoke_hidden_method(
				$mailer,
				'get_list_start',
				array( array( 'recipients' => '__return_empty_array' ) )
			)
		);
		$this->assertNull( Utility::invoke_hidden_method( $mailer, 'get_list_start', array( array() ) ) );
	}

	/**
	 * Check the batch size default and filter.
	 *
	 * @covers ::get_batch_size
	 *
	 * @return void
	 */
	public function test_get_batch_size(): void {
		$mailer   = Mailer::get_instance();
		$captured = '';

		$this->assertSame( 50, Utility::invoke_hidden_method( $mailer, 'get_batch_size', array( 'event' ) ) );

		add_filter(
			'gatherpress_email_batch_size',
			static function ( $size, string $type ) use ( &$captured ): int {
				$captured = $type;

				return 0;
			},
			10,
			2
		);

		$this->assertSame(
			1,
			Utility::invoke_hidden_method( $mailer, 'get_batch_size', array( 'site_message' ) ),
			'A batch should never be smaller than one.'
		);
		$this->assertSame( 'site_message', $captured );
	}

	/**
	 * Register a mail type for one test.
	 *
	 * @param array<string, mixed> $definition Type settings over a plain default.
	 *
	 * @return string The type name.
	 */
	private function register_type( array $definition = array() ): string {
		$type = sprintf( 'gatherpress_test_mail_%d', count( $this->registered ) );

		$this->registered[] = $type;

		Mailer::get_instance()->register(
			$type,
			array_merge(
				array(
					'context' => 'event',
					'compose' => static fn(): array => array(
						'subject' => 'Subject',
						'body'    => 'Body',
					),
				),
				$definition
			)
		);

		return $type;
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
	 * Get the queued Mailer jobs.
	 *
	 * @return array<int, array<mixed>> Each job's args.
	 */
	private function queued_jobs(): array {
		$jobs = array();

		foreach ( (array) _get_cron_array() as $hooks ) {
			foreach ( $hooks[ Mailer::JOB_HOOK ] ?? array() as $event ) {
				$jobs[] = $event['args'];
			}
		}

		return $jobs;
	}

	/**
	 * Call the private consent check.
	 *
	 * @param array<string, mixed> $recipient Recipient row.
	 * @param string               $context   Mail path asking for consent.
	 *
	 * @return bool Whether the recipient consents.
	 */
	private function has_consent( array $recipient, string $context ): bool {
		return Utility::invoke_hidden_method( Mailer::get_instance(), 'has_consent', array( $recipient, $context ) );
	}

	/**
	 * Call the private job scheduler.
	 *
	 * @param string       $hook  Action hook.
	 * @param array<mixed> $args  Hook args.
	 * @param int          $delay Seconds to wait.
	 *
	 * @return bool Whether the job is queued.
	 */
	private function schedule( string $hook, array $args, int $delay = 0 ): bool {
		return Utility::invoke_hidden_method( Mailer::get_instance(), 'schedule', array( $hook, $args, $delay ) );
	}

	/**
	 * Build a recipient row for the tests.
	 *
	 * @param array $overrides Recipient values to override.
	 *
	 * @return array<string, bool|int|string> Recipient row.
	 */
	private function recipient( array $overrides = array() ): array {
		return array_merge(
			array(
				'is_user'    => true,
				'user_id'    => 0,
				'comment_id' => 0,
				'email'      => 'recipient@example.test',
				'name'       => 'Recipient',
			),
			$overrides
		);
	}
}
