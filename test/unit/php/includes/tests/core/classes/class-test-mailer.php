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

/**
 * Class Test_Mailer.
 *
 * @coversDefaultClass \GatherPress\Core\Mailer
 */
class Test_Mailer extends Base {

	/**
	 * Check that recipients without email addresses are rejected.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_rejects_empty_email(): void {
		$recipient = $this->recipient( array( 'email' => '' ) );

		$this->assertFalse( Mailer::get_instance()->is_eligible( $recipient ) );
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
			Mailer::get_instance()->is_eligible(
				$this->recipient(
					array(
						'user_id' => $user_id,
						'email'   => get_userdata( $user_id )->user_email,
					)
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
			Mailer::get_instance()->is_eligible(
				$this->recipient( array( 'user_id' => $user_id ) )
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
			Mailer::get_instance()->is_eligible(
				$this->recipient(
					array(
						'is_user'    => false,
						'comment_id' => $comment_id,
						'email'      => 'anonymous@example.test',
					)
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
			Mailer::get_instance()->is_eligible(
				$this->recipient(
					array(
						'is_user' => false,
						'email'   => 'anonymous@example.test',
					)
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
			Mailer::get_instance()->switch_context( $this->recipient( array( 'is_user' => false ) ) )
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
			Mailer::get_instance()->switch_context( $this->recipient( array( 'user_id' => $user_id ) ) )
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

		Mailer::get_instance()->restore_context( false, get_userdata( $sender_id ) );

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

		$result = Mailer::get_instance()->deliver(
			'recipient@example.test',
			"Tom &amp; Jerry\\'s update",
			'<p>Message</p>',
			array( 'Content-Type: text/html; charset=UTF-8' )
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
		$result = Mailer::get_instance()->deliver( 'recipient@example.test', 'Subject', 'Body' );
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
			Mailer::get_instance()->has_consent(
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
			Mailer::get_instance()->has_consent(
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

		add_filter( 'gatherpress_mail_recipient_consent', '__return_true' );

		$consent = Mailer::get_instance()->has_consent(
			$this->recipient( array( 'user_id' => $user_id ) ),
			'event'
		);

		remove_all_filters( 'gatherpress_mail_recipient_consent' );

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
		add_filter( 'gatherpress_mail_recipient_consent', '__return_true' );

		$consent = Mailer::get_instance()->has_consent(
			$this->recipient( array( 'email' => '' ) ),
			'event'
		);

		remove_all_filters( 'gatherpress_mail_recipient_consent' );

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
			'gatherpress_mail_recipient_consent',
			static function ( $consent, $recipient, $context ) use ( &$captured ) {
				$captured = array( $consent, $context );

				return false;
			},
			10,
			3
		);

		$consent = Mailer::get_instance()->has_consent(
			$this->recipient( array( 'user_id' => $user_id ) ),
			'site_message'
		);

		remove_all_filters( 'gatherpress_mail_recipient_consent' );

		$this->assertFalse( $consent );
		$this->assertSame( array( true, 'site_message' ), $captured );
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
		add_filter( 'gatherpress_mail_recipient_consent', '__return_false' );

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

		remove_all_filters( 'gatherpress_mail_recipient_consent' );

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

		$result = Mailer::get_instance()->schedule( 'gatherpress_test_mail_job', $args );

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

		$result = Mailer::get_instance()->schedule( 'gatherpress_test_mail_job', $args );

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
			'gatherpress_mail_pre_enqueue_job',
			static function ( $short_circuit, $hook, $args ) use ( &$captured ) {
				$captured = array( $short_circuit, $hook, $args );

				return true;
			},
			10,
			3
		);

		$result = Mailer::get_instance()->schedule( 'gatherpress_test_mail_job', array( 'A', 'B', 9 ) );

		remove_all_filters( 'gatherpress_mail_pre_enqueue_job' );

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

		$result = Mailer::get_instance()->schedule( 'gatherpress_test_mail_job', array( 'A', 'B', 10 ) );

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

		$result = Mailer::get_instance()->schedule( 'gatherpress_test_mail_job', array( 'A', 'B', 11 ) );

		remove_all_filters( 'pre_schedule_event' );

		$this->assertTrue( $result );
		$this->assertSame( 2, $attempts );
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
