<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Email.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use DateTime;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Email;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Comment;

/**
 * Class Test_Email.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Email
 */
class Test_Email extends Base {

	/**
	 * Emails sent during a test, as recipient and subject.
	 *
	 * @var array<int, array{0: string, 1: string, 2: string}>
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
	 * Remove the mail capture and any queued jobs.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ) );
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
		$this->sent[] = array( $atts['to'], $atts['subject'], $atts['message'] );

		return true;
	}

	/**
	 * Check the hooks the event email class listens on.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Email::get_instance();

		$this->assert_hooks(
			array(
				array(
					'type'     => 'action',
					'name'     => 'gatherpress_send_emails',
					'priority' => 10,
					'callback' => array( $instance, 'handle_email_send_action' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'gatherpress_rsvp_waiting_list_promoted',
					'priority' => 10,
					'callback' => array( $instance, 'queue_promotion_email' ),
				),
			),
			$instance
		);
	}

	/**
	 * Check that send_emails() queues an event update job with Mailer.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_queues_a_mailer_job(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$send    = array( 'attending' => true );

		$this->assertTrue( Email::get_instance()->send_emails( $post_id, $send, 'Hello', 'Hi' ) );
		$this->assertNotFalse(
			wp_next_scheduled(
				Mailer::JOB_HOOK,
				array(
					Email::EVENT_TYPE,
					array(
						'post_id' => $post_id,
						'send'    => $send,
						'message' => 'Hello',
						'subject' => 'Hi',
					),
					Mailer::FIRST_BATCH,
				)
			)
		);
		$this->assertFalse(
			Email::get_instance()->send_emails( $this->factory->post->create(), $send, 'Hello' ),
			'A post without RSVPs should not be queued.'
		);
	}

	/**
	 * Check that a job on the old hook is queued again with Mailer.
	 *
	 * @covers ::handle_email_send_action
	 *
	 * @return void
	 */
	public function test_handle_email_send_action(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$send    = array( 'all' => true );

		Email::get_instance()->handle_email_send_action( $post_id, $send, 'Hello', 'Hi' );

		$this->assertNotFalse(
			wp_next_scheduled(
				Mailer::JOB_HOOK,
				array(
					Email::EVENT_TYPE,
					array(
						'post_id' => $post_id,
						'send'    => $send,
						'message' => 'Hello',
						'subject' => 'Hi',
					),
					Mailer::FIRST_BATCH,
				)
			)
		);
	}

	/**
	 * Check that an attendee moved off the waiting list gets a confirmation, opted out or not.
	 *
	 * @covers ::queue_promotion_email
	 * @covers ::get_promoted_attendee
	 * @covers ::compose_promotion_email
	 *
	 * @return void
	 */
	public function test_promotion_email_reaches_the_promoted_attendee(): void {
		$event  = $this->event_with_waiting_list( 'Board game night' );
		$waiter = $event['waiter'];

		update_user_meta( $waiter, 'gatherpress_event_updates_opt_in', '0' );
		( new Rsvp( $event['id'] ) )->save( $event['attendee'], 'not_attending' );
		$this->run_mail_jobs();

		$this->assertCount( 1, $this->sent );
		$this->assertSame( get_userdata( $waiter )->user_email, $this->sent[0][0] );
		$this->assertSame( 'Your RSVP for Board game night is confirmed', $this->sent[0][1] );
		$this->assertStringContainsString( 'your RSVP for Board game night is now confirmed', $this->sent[0][2] );
		$this->assertStringNotContainsString( 'RSVP Now', $this->sent[0][2], 'The attendee already holds a spot.' );
	}

	/**
	 * Check that no promotion email goes out when the attendee cancels before the job runs.
	 *
	 * @covers ::get_promoted_attendee
	 *
	 * @return void
	 */
	public function test_promotion_email_skips_an_attendee_who_cancelled(): void {
		$event = $this->event_with_waiting_list( 'Book club' );

		( new Rsvp( $event['id'] ) )->save( $event['attendee'], 'not_attending' );
		( new Rsvp( $event['id'] ) )->save( $event['waiter'], 'not_attending' );
		$this->run_mail_jobs();

		$this->assertSame( array(), $this->sent );
	}

	/**
	 * Check that only the promoted RSVP is picked, and only while it is attending.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called by short delegation.
	 *
	 * @covers ::get_promoted_attendee
	 *
	 * @return void
	 */
	public function test_get_promoted_attendee(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();
		$other_id = $this->factory->user->create();
		$rsvp     = new Rsvp( $post_id );

		$rsvp->save( $user_id, 'attending' );
		$rsvp->save( $other_id, 'attending' );

		$comment_id = (int) $rsvp->find( $user_id )->comment->comment_ID;
		$attendee   = static fn( int $id ): array => Utility::invoke_hidden_method(
			Email::get_instance(),
			'get_promoted_attendee',
			array(
				array(
					'post_id'    => $post_id,
					'comment_id' => $id,
				),
			)
		);

		$this->assertSame( array( $comment_id ), wp_list_pluck( $attendee( $comment_id ), 'comment_id' ) );

		$rsvp->save( $user_id, 'not_attending' );

		$this->assertSame( array(), $attendee( $comment_id ) );
	}

	/**
	 * Check the promotion email's subject, message and missing RSVP button.
	 *
	 * @covers ::compose_promotion_email
	 *
	 * @return void
	 */
	public function test_compose_promotion_email(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Trivia',
			)
		);
		$email   = Utility::invoke_hidden_method(
			Email::get_instance(),
			'compose_promotion_email',
			array( array(), array( 'post_id' => $post_id ) )
		);

		$this->assertSame( 'Your RSVP for Trivia is confirmed', $email['subject'] );
		$this->assertStringContainsString( 'your RSVP for Trivia is now confirmed', $email['body'] );
		$this->assertStringNotContainsString( 'RSVP Now', $email['body'] );
	}

	/**
	 * Check that get_recipients() builds the list for an event update.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		$instance = Email::get_instance();

		$this->assertSame(
			array( 'guest@example.test' ),
			wp_list_pluck( $instance->get_recipients( array( 'attending' => true ), $post_id ), 'email' )
		);
		$this->assertContains(
			'guest@example.test',
			wp_list_pluck(
				$instance->get_recipients(
					array(
						'all'       => true,
						'attending' => true,
					),
					$post_id
				),
				'email'
			)
		);
	}

	/**
	 * Check that event and promotion emails register their mail types with Mailer.
	 *
	 * @covers ::__construct
	 * @covers ::register_mail_types
	 *
	 * @return void
	 */
	public function test_register_mail_types(): void {
		// The constructor registers at boot, before coverage records, so register again here.
		Utility::invoke_hidden_method( Email::get_instance(), 'register_mail_types' );

		$types = Utility::get_hidden_property( Mailer::get_instance(), 'types' );

		$this->assertArrayHasKey( Email::EVENT_TYPE, $types );
		$this->assertSame( Mailer::CONTEXT_EVENT, $types[ Email::EVENT_TYPE ]['context'] );
		$this->assertArrayHasKey( 'users', $types[ Email::EVENT_TYPE ] );
		$this->assertArrayHasKey( 'recipients', $types[ Email::EVENT_TYPE ] );
		$this->assertArrayHasKey( 'compose', $types[ Email::EVENT_TYPE ] );
		$this->assertArrayHasKey( Email::PROMOTION_TYPE, $types );
		$this->assertSame(
			Mailer::CONTEXT_RSVP,
			$types[ Email::PROMOTION_TYPE ]['context'],
			'A promotion is about the attendee\'s own RSVP, so it should skip the opt-in.'
		);
		$this->assertArrayNotHasKey( 'users', $types[ Email::PROMOTION_TYPE ] );
	}

	/**
	 * Check that every user gets an event email only when "all" is picked.
	 *
	 * @covers ::get_event_email_users
	 *
	 * @return void
	 */
	public function test_get_event_email_users(): void {
		$instance = Email::get_instance();

		$this->assertSame(
			array(),
			Utility::invoke_hidden_method(
				$instance,
				'get_event_email_users',
				array( array( 'send' => array( 'all' => true ) ) )
			)
		);
		$this->assertNull(
			Utility::invoke_hidden_method(
				$instance,
				'get_event_email_users',
				array( array( 'send' => array( 'attending' => true ) ) )
			)
		);
	}

	/**
	 * Check that RSVPs from users are left out when every user already gets the email.
	 *
	 * @covers ::get_event_email_rsvps
	 * @covers ::get_rsvp_recipients
	 *
	 * @return void
	 */
	public function test_get_event_email_rsvps(): void {
		$instance = Email::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();
		$rsvp     = new Rsvp( $post_id );

		$rsvp->save( $user_id, 'attending' );
		$rsvp->save( 'guest@example.test', 'attending', 0, 0 );

		$emails = static fn( array $send ): array => wp_list_pluck(
			Utility::invoke_hidden_method(
				$instance,
				'get_event_email_rsvps',
				array(
					array(
						'post_id' => $post_id,
						'send'    => $send,
					),
				)
			),
			'email'
		);

		$attending = $emails( array( 'attending' => true ) );

		sort( $attending );

		$expected = array( get_userdata( $user_id )->user_email, 'guest@example.test' );

		sort( $expected );

		$this->assertSame( $expected, $attending );
		$this->assertSame(
			array( 'guest@example.test' ),
			$emails(
				array(
					'all'       => true,
					'attending' => true,
				)
			)
		);
	}

	/**
	 * Check that no RSVPs are looked up when no status was picked.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called by short delegation.
	 *
	 * @covers ::get_rsvp_recipients
	 *
	 * @return void
	 */
	public function test_get_rsvp_recipients_without_statuses(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		$this->assertSame(
			array(),
			Utility::invoke_hidden_method(
				Email::get_instance(),
				'get_rsvp_recipients',
				array( array( 'all' => true ), $post_id )
			)
		);
	}

	/**
	 * Check that rows the RSVP query returns that are not comments are skipped.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called by short delegation.
	 *
	 * @covers ::get_rsvp_recipients
	 *
	 * @return void
	 */
	public function test_get_rsvp_recipients_skips_non_comment_rows(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$injector = static function ( $comments, $query ) {
			if ( ! empty( $query->query_vars['comment__in'] ) ) {
				$comments[] = PHP_INT_MAX;
			}

			return $comments;
		};

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );
		add_filter( 'the_comments', $injector, 10, 2 );

		$recipients = Utility::invoke_hidden_method(
			Email::get_instance(),
			'get_rsvp_recipients',
			array( array( 'attending' => true ), $post_id )
		);

		remove_filter( 'the_comments', $injector, 10 );

		$this->assertSame( array( 'guest@example.test' ), wp_list_pluck( $recipients, 'email' ) );
	}

	/**
	 * Check that a queued event email is built from its payload.
	 *
	 * @covers ::compose_event_email
	 *
	 * @return void
	 */
	public function test_compose_event_email(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$email   = Utility::invoke_hidden_method(
			Email::get_instance(),
			'compose_event_email',
			array(
				array(),
				array(
					'post_id' => $post_id,
					'message' => 'See you there.',
					'subject' => 'Reminder',
				),
			)
		);

		$this->assertSame( 'Reminder', $email['subject'] );
		$this->assertStringContainsString( 'See you there.', $email['body'] );
	}

	/**
	 * Future-event emails include the RSVP Now CTA.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::build_event_email
	 *
	 * @return void
	 */
	public function test_build_event_email_includes_rsvp_cta_for_future_event(): void {
		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$event    = new Event( $event_id );
		$start    = new DateTime( 'now' );
		$end      = new DateTime( 'now' );

		$start->modify( '+1 day' );
		$end->modify( '+1 day +2 hours' );
		$event->save_datetimes(
			array(
				'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
				'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			)
		);

		$captured_body = Utility::invoke_hidden_method(
			$instance,
			'build_event_email',
			array( $event_id, 'Upcoming reminder.', '' )
		)['body'];

		$this->assertStringContainsString(
			'RSVP Now',
			$captured_body,
			'Future-event email should include the RSVP Now CTA.'
		);
	}

	/**
	 * Past-event emails omit the RSVP Now CTA — registration is closed.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::build_event_email
	 *
	 * @return void
	 */
	public function test_build_event_email_omits_rsvp_cta_for_past_event(): void {
		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$event    = new Event( $event_id );
		$start    = new DateTime( 'now' );
		$end      = new DateTime( 'now' );

		$start->modify( '-2 days' );
		$end->modify( '-2 days +2 hours' );
		$event->save_datetimes(
			array(
				'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
				'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			)
		);

		$captured_body = Utility::invoke_hidden_method(
			$instance,
			'build_event_email',
			array( $event_id, 'Thanks for coming.', '' )
		)['body'];

		$this->assertNotEmpty( $captured_body, 'Past-event email body should still be sent.' );
		$this->assertStringContainsString(
			'Thanks for coming.',
			$captured_body,
			'Past-event email should still include the custom message.'
		);
		$this->assertStringNotContainsString(
			'RSVP Now',
			$captured_body,
			'Past-event email must not include the RSVP Now CTA.'
		);
	}

	/**
	 * Emails omit the RSVP Now CTA when RSVP is disabled for the event.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::build_event_email
	 *
	 * @return void
	 */
	public function test_build_event_email_omits_rsvp_cta_when_rsvp_disabled(): void {
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_enabled' );

		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$event    = new Event( $event_id );
		$start    = new DateTime( 'now' );
		$end      = new DateTime( 'now' );

		$start->modify( '+1 day' );
		$end->modify( '+1 day +2 hours' );
		$event->save_datetimes(
			array(
				'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
				'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			)
		);
		update_post_meta( $event_id, 'gatherpress_enable_rsvp', '0' );

		$captured_body = Utility::invoke_hidden_method(
			$instance,
			'build_event_email',
			array( $event_id, 'Event update.', '' )
		)['body'];

		$this->assertNotEmpty( $captured_body, 'Email body should still be sent.' );
		$this->assertStringNotContainsString(
			'RSVP Now',
			$captured_body,
			'Email must not include the RSVP Now CTA when RSVP is disabled.'
		);
	}

	/**
	 * Direct-invoke coverage for `build_event_email`: a caller-supplied
	 * subject skips the default branch.
	 *
	 * Reflection-invoked because xdebug doesn't reliably trace lines inside
	 * same-class private helpers called from a closure.
	 *
	 * @covers ::build_event_email
	 *
	 * @return void
	 */
	public function test_build_event_email_keeps_supplied_subject(): void {
		$instance = Email::get_instance();
		$event_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Supplied Title',
			)
		);

		$email = Utility::invoke_hidden_method(
			$instance,
			'build_event_email',
			array( $event_id, 'Hello there.', 'Custom subject' )
		);

		$this->assertIsArray( $email );
		$this->assertSame( 'Custom subject', $email['subject'] );
		$this->assertStringContainsString( 'Hello there.', $email['body'] );
		$this->assertSame( array( 'Content-Type: text/html; charset=UTF-8' ), $email['headers'] );
	}

	/**
	 * Direct-invoke coverage for `build_event_email` — an empty subject falls
	 * back to the `📅 {title}` default built from the event title.
	 *
	 * @covers ::build_event_email
	 *
	 * @return void
	 */
	public function test_build_event_email_builds_default_subject_from_title(): void {
		$instance = Email::get_instance();
		$event_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Default Title Event',
			)
		);

		$email = Utility::invoke_hidden_method(
			$instance,
			'build_event_email',
			array( $event_id, '', '' )
		);

		$this->assertIsArray( $email );
		$this->assertStringContainsString( 'Default Title Event', $email['subject'] );
		$this->assertStringContainsString( '📅', $email['subject'] );
	}

	/**
	 * Direct-invoke coverage for `build_comment_recipient` (extracted from
	 * `get_recipients`) — happy path with a registered user pulls email and
	 * display name from the user record rather than the comment fields.
	 *
	 * Reflection-invoked because xdebug doesn't reliably trace lines inside
	 * same-class protected helpers called from a foreach in the parent.
	 *
	 * @covers ::build_comment_recipient
	 *
	 * @return void
	 */
	public function test_build_comment_recipient_uses_user_data_when_user_id_present(): void {
		$instance = Email::get_instance();
		$user_id  = $this->factory->user->create(
			array(
				'user_email'   => 'user-recipient@example.test',
				'display_name' => 'User Recipient',
			)
		);

		$comment                       = new \stdClass();
		$comment->comment_ID           = 42;
		$comment->user_id              = $user_id;
		$comment->comment_author_email = 'comment-fallback@example.test';
		$comment->comment_author       = 'Comment Author';

		$recipient = Utility::invoke_hidden_method(
			$instance,
			'build_comment_recipient',
			array( new WP_Comment( $comment ) )
		);

		$this->assertIsArray( $recipient );
		$this->assertTrue( $recipient['is_user'] );
		$this->assertSame( $user_id, $recipient['user_id'] );
		$this->assertSame( 42, $recipient['comment_id'] );
		// User data wins over comment-author fields when user_id resolves.
		$this->assertSame( 'user-recipient@example.test', $recipient['email'] );
		$this->assertSame( 'User Recipient', $recipient['name'] );
	}

	/**
	 * Direct-invoke coverage for `build_comment_recipient` — anonymous-RSVP
	 * branch (no user_id) keeps the comment's own author email/name.
	 *
	 * @covers ::build_comment_recipient
	 *
	 * @return void
	 */
	public function test_build_comment_recipient_falls_back_to_comment_fields(): void {
		$instance = Email::get_instance();

		$comment                       = new \stdClass();
		$comment->comment_ID           = 7;
		$comment->user_id              = 0;
		$comment->comment_author_email = 'anon@example.test';
		$comment->comment_author       = 'Anon';

		$recipient = Utility::invoke_hidden_method(
			$instance,
			'build_comment_recipient',
			array( new WP_Comment( $comment ) )
		);

		$this->assertIsArray( $recipient );
		$this->assertFalse( $recipient['is_user'] );
		$this->assertSame( 0, $recipient['user_id'] );
		$this->assertSame( 'anon@example.test', $recipient['email'] );
		$this->assertSame( 'Anon', $recipient['name'] );
	}

	/**
	 * Direct-invoke coverage for `build_comment_recipient` — returns null
	 * for a comment with no valid email on file so the caller can skip it.
	 *
	 * @covers ::build_comment_recipient
	 *
	 * @return void
	 */
	public function test_build_comment_recipient_returns_null_without_email(): void {
		$instance = Email::get_instance();

		$comment                       = new \stdClass();
		$comment->comment_ID           = 1;
		$comment->user_id              = 0;
		$comment->comment_author_email = '';
		$comment->comment_author       = 'No Email';

		$recipient = Utility::invoke_hidden_method(
			$instance,
			'build_comment_recipient',
			array( new WP_Comment( $comment ) )
		);

		$this->assertNull( $recipient );

		$comment->comment_author_email = 'jane@example';

		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'build_comment_recipient', array( new WP_Comment( $comment ) ) ),
			'A malformed address should be skipped too.'
		);
	}

	/**
	 * Create an event with one spot taken and one attendee on the waiting list.
	 *
	 * @param string $title Event title.
	 *
	 * @return array{id: int, attendee: int, waiter: int} The event and the two users.
	 */
	private function event_with_waiting_list( string $title ): array {
		$event_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => $title,
			)
		);
		$attendee = $this->factory->user->create();
		$waiter   = $this->factory->user->create();

		update_post_meta( $event_id, 'gatherpress_capacity', 1 );
		( new Rsvp( $event_id ) )->save( $attendee, 'attending' );
		( new Rsvp( $event_id ) )->save( $waiter, 'attending' );

		return array(
			'id'       => $event_id,
			'attendee' => $attendee,
			'waiter'   => $waiter,
		);
	}
}
