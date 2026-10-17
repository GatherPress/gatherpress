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
use GatherPress\Core\Rsvp\Response\Status;
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
	 * @since TBD
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
	 * @since TBD
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
	 * @since TBD
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
	 * Coverage for send_emails method.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_email(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Email::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$post_id  = $this->mock->post(
			array( 'post_type' => 'post' )
		)->get()->ID;
		$user_id  = $this->mock->user()->get()->ID;
		$message  = 'Unit test.';
		$send     = array(
			'all'           => false,
			'attending'     => true,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$rsvp = new Rsvp( $event_id );
		$rsvp->save( $user_id, 'attending' );

		$this->assertFalse(
			$instance->send_emails( $post_id, $send, $message ),
			'Failed to assert false for sending email.'
		);
		$this->assertTrue(
			$instance->send_emails( $event_id, $send, $message ),
			'Failed to assert true for sending email.'
		);
	}

	/**
	 * Coverage for get_recipients method with no send options selected.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_with_no_send_options(): void {
		$instance = Email::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		$send = array(
			'all'           => false,
			'attending'     => false,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$recipients = $instance->get_recipients( $send, $event_id );

		$this->assertEmpty( $recipients, 'Failed to assert empty recipients when no send options are selected.' );
	}

	/**
	 * Coverage for get_recipients method with 'all' option for users only.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_with_all_users_only(): void {
		$instance = Email::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create WordPress users - 'all' gets ALL site users, not just those who RSVP'd.
		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$user_3_id = $this->factory->user->create();

		$send = array(
			'all'           => true,
			'attending'     => false,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$recipients    = $instance->get_recipients( $send, $event_id );
		$recipient_ids = $this->get_recipient_user_ids( $recipients );

		// Should include all site users (including the 3 we created plus any existing users).
		$this->assertContains( $user_1_id, $recipient_ids, 'Failed to assert user 1 is included in all recipients.' );
		$this->assertContains( $user_2_id, $recipient_ids, 'Failed to assert user 2 is included in all recipients.' );
		$this->assertContains( $user_3_id, $recipient_ids, 'Failed to assert user 3 is included in all recipients.' );
		$this->assertGreaterThanOrEqual(
			3,
			count( $recipients ),
			'Failed to assert minimum recipient count for all users.'
		);
	}

	/**
	 * Coverage for get_recipients method with 'attending' status filter.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_with_attending_filter(): void {
		$instance = Email::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$rsvp     = new Rsvp( $event_id );

		// Create users and save RSVPs using the RSVP system.
		$attending_user_id     = $this->factory->user->create();
		$not_attending_user_id = $this->factory->user->create();

		$rsvp->save( $attending_user_id, 'attending' );
		$rsvp->save( $not_attending_user_id, 'not_attending' );

		// Create anonymous attending RSVP.
		$rsvp->save( 'attendee@example.com', 'attending', 1 );

		$send = array(
			'all'           => false,
			'attending'     => true,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$recipients = $instance->get_recipients( $send, $event_id );

		$this->assertCount( 2, $recipients, 'Failed to assert correct count for attending recipients.' );

		// Check that we have both user and anonymous attending.
		$user_recipients      = array_filter(
			$recipients,
			static function ( $recipient ) {
				return $recipient['is_user'];
			}
		);
		$anonymous_recipients = array_filter(
			$recipients,
			static function ( $recipient ) {
				return ! $recipient['is_user'];
			}
		);

		$this->assertCount( 1, $user_recipients, 'Failed to assert one attending user recipient.' );
		$this->assertCount( 1, $anonymous_recipients, 'Failed to assert one attending anonymous recipient.' );

		$user_recipient      = reset( $user_recipients );
		$anonymous_recipient = reset( $anonymous_recipients );

		$this->assertEquals(
			$attending_user_id,
			$user_recipient['user_id'],
			'Failed to assert correct attending user ID.'
		);
		$this->assertEquals(
			'attendee@example.com',
			$anonymous_recipient['email'],
			'Failed to assert correct anonymous attendee email.'
		);
	}

	/**
	 * Coverage for get_recipients method with mixed user and non-user RSVPs.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_with_mixed_recipients(): void {
		$instance = Email::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$rsvp     = new Rsvp( $event_id );

		// Force no attendance so responses remain on waiting list.
		Utility::set_and_get_hidden_property( $rsvp, 'capacity', -1 );

		// Create user RSVP.
		$user_id = $this->factory->user->create(
			array(
				'user_email'   => 'user@example.com',
				'display_name' => 'User Name',
			)
		);
		$rsvp->save( $user_id, 'waiting_list' );

		// Create anonymous RSVP using wp_insert_comment for better control.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'Anonymous Person',
				'comment_author_email' => 'anonymous@example.com',
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		$rsvp->save( 'anonymous@example.com', 'waiting_list' );

		$send = array(
			'all'           => false,
			'attending'     => false,
			'waiting_list'  => true,
			'not_attending' => false,
		);

		$recipients = $instance->get_recipients( $send, $event_id );

		$this->assertCount( 2, $recipients, 'Failed to assert correct count for mixed recipients.' );

		// Verify recipient structure for user.
		$user_recipient = array_filter(
			$recipients,
			static function ( $recipient ) {
				return $recipient['is_user'];
			}
		);
		$user_recipient = reset( $user_recipient );

		$this->assertTrue( $user_recipient['is_user'], 'Failed to assert user recipient is marked as user.' );
		$this->assertEquals( $user_id, $user_recipient['user_id'], 'Failed to assert correct user ID.' );
		$this->assertEquals( 'user@example.com', $user_recipient['email'], 'Failed to assert correct user email.' );
		$this->assertEquals( 'User Name', $user_recipient['name'], 'Failed to assert correct user name.' );

		// Verify recipient structure for anonymous.
		$anonymous_recipient = array_filter(
			$recipients,
			static function ( $recipient ) {
				return ! $recipient['is_user'];
			}
		);
		$anonymous_recipient = reset( $anonymous_recipient );

		$this->assertFalse(
			$anonymous_recipient['is_user'],
			'Failed to assert anonymous recipient is not marked as user.'
		);
		$this->assertEquals(
			0,
			$anonymous_recipient['user_id'],
			'Failed to assert anonymous recipient has zero user ID.'
		);
		$this->assertEquals( $comment_id, $anonymous_recipient['comment_id'], 'Failed to assert correct comment ID.' );
		$this->assertEquals(
			'anonymous@example.com',
			$anonymous_recipient['email'],
			'Failed to assert correct anonymous email.'
		);
		$this->assertEquals(
			'Anonymous Person',
			$anonymous_recipient['name'],
			'Failed to assert correct anonymous name.'
		);
	}

	/**
	 * Coverage for send_emails with non-event post.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_non_event_post(): void {
		$instance = Email::get_instance();
		$post_id  = $this->factory()->post->create();

		$result = $instance->send_emails( $post_id, array( 'all' => true ), '' );

		$this->assertFalse( $result );
	}

	/**
	 * Coverage for send_emails with a custom post type that declares RSVP support.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_custom_rsvp_post_type(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance  = Email::get_instance();
		$post_type = 'gp_rsvp_custom';

		register_post_type(
			$post_type,
			array(
				'public'   => true,
				'supports' => array( 'title', Event::SUPPORT, Rsvp::SUPPORT ),
			)
		);

		$post_id = $this->factory()->post->create( array( 'post_type' => $post_type ) );
		$user_id = $this->factory()->user->create();
		$rsvp    = new Rsvp( $post_id );
		$rsvp->save( $user_id, 'attending' );

		$result = $instance->send_emails( $post_id, array( 'attending' => true ), 'Test message' );

		unregister_post_type( $post_type );
		remove_filter( 'pre_wp_mail', '__return_false' );

		$this->assertTrue(
			$result,
			'Failed to assert that a post type with RSVP support can send event emails.'
		);
	}

	/**
	 * Test send_emails with user who opted out of event updates.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_opted_out_user(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();

		// User opts out of event updates.
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', 0 );

		$rsvp = new Rsvp( $event_id );
		$rsvp->save( $user_id, 'attending' );

		$send = array(
			'attending' => true,
		);

		// Should still return true but skip the opted-out user.
		$result = $instance->send_emails( $event_id, $send, 'Test message' );
		$this->assertTrue( $result );

		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Test send_emails with non-user RSVP who opted out.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_opted_out_non_user(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create anonymous RSVP.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'Anonymous User',
				'comment_author_email' => 'anonymous@example.com',
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		// Set RSVP status.
		wp_set_object_terms( $comment_id, 'attending', Status::TAXONOMY );

		// Opt out of event updates.
		update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', 0 );

		$send = array(
			'attending' => true,
		);

		// Should still return true but skip the opted-out RSVP.
		$result = $instance->send_emails( $event_id, $send, 'Test message' );
		$this->assertTrue( $result );

		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Test send_emails with recipient who has no email.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_no_email(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create RSVP with no email.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'No Email User',
				'comment_author_email' => '', // Empty email.
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		wp_set_object_terms( $comment_id, 'attending', Status::TAXONOMY );

		$send = array(
			'attending' => true,
		);

		// Should return true but skip recipient with no email.
		$result = $instance->send_emails( $event_id, $send, 'Test message' );
		$this->assertTrue( $result );

		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Test get_recipients skips RSVPs with empty email.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_skips_empty_email(): void {
		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create RSVP with empty email.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'No Email User',
				'comment_author_email' => '', // Empty email.
				'comment_approved'     => 1,
				'user_id'              => 0,
			)
		);

		wp_set_object_terms( $comment_id, 'attending', Status::TAXONOMY );

		$send = array(
			'attending' => true,
		);

		$recipients = $instance->get_recipients( $send, $event_id );

		// Should not include the RSVP with empty email.
		$this->assertEmpty( $recipients );
	}

	/**
	 * Test get_recipients skips rows the RSVP query returns that are not comments.
	 *
	 * @since TBD
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_skips_non_comment_rows(): void {
		$instance = Email::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$rsvp     = new Rsvp( $event_id );

		$rsvp->save( 'attendee@example.com', 'attending', 1 );

		// Append an unresolvable row to the RSVP lookup so a non-comment entry
		// reaches the recipient loop.
		$injector = static function ( $comments, $query ) {
			if ( ! empty( $query->query_vars['comment__in'] ) ) {
				$comments[] = PHP_INT_MAX;
			}

			return $comments;
		};

		add_filter( 'the_comments', $injector, 10, 2 );

		$recipients = $instance->get_recipients( array( 'attending' => true ), $event_id );

		remove_filter( 'the_comments', $injector, 10 );

		$this->assertCount(
			1,
			$recipients,
			'Failed to assert the non-comment row was skipped.'
		);
		$this->assertSame(
			'attendee@example.com',
			$recipients[0]['email'],
			'Failed to assert the surviving recipient is the real RSVP.'
		);
	}

	/**
	 * Test send_emails with locale switching for user.
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_locale_switching(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$instance = Email::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();

		// Opt in to updates.
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', 1 );

		$rsvp = new Rsvp( $event_id );
		$rsvp->save( $user_id, 'attending' );

		$send = array(
			'attending' => true,
		);

		// Should handle locale switching for user.
		$result = $instance->send_emails( $event_id, $send, 'Test message' );
		$this->assertTrue( $result );

		remove_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Tests send_emails with non-user RSVP that has opt-out.
	 *
	 * Covers: skip sending email when comment meta opt-in is '0'.
	 *
	 * @covers ::send_emails
	 */
	public function test_send_emails_skip_non_user_opt_out(): void {
		$instance = Email::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$rsvp = new Rsvp( $post_id );

		// Create non-user RSVP using email address.
		$email   = 'nonuser@example.com';
		$comment = $rsvp->save( $email, 'attending', 0, 0 );

		// Set opt-in to '0' (opted out).
		update_comment_meta( $comment['comment_id'], 'gatherpress_event_updates_opt_in', '0' );

		$send = array( 'attending' => true );

		// Mock wp_mail to track if it was called.
		$mail_called = false;
		add_filter(
			'pre_wp_mail',
			function () use ( &$mail_called ) {
				$mail_called = true;
				return true; // Prevent actual email sending.
			}
		);

		$result = $instance->send_emails( $post_id, $send, 'Test message' );
		$this->run_mail_jobs();

		$this->assertTrue( $result, 'send_emails should return true' );
		$this->assertFalse( $mail_called, 'wp_mail should not be called for opted-out non-user RSVP' );
	}

	/**
	 * `send_emails` honors a custom subject supplied by the caller (#827).
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_custom_subject(): void {
		$instance = Email::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Custom Subject Event',
			)
		);

		$rsvp  = new Rsvp( $post_id );
		$email = 'recipient@example.test';
		$rsvp->save( $email, 'attending', 0, 0 );

		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $prev, $atts ) use ( &$captured ): bool {
				$captured = $atts;

				return (bool) $prev;
			},
			10,
			2
		);

		$result = $instance->send_emails(
			$post_id,
			array( 'attending' => true ),
			'Test message',
			'Reminder: doors at 7'
		);
		$this->run_mail_jobs();

		$this->assertTrue( $result, 'send_emails should return true.' );
		$this->assertSame(
			'Reminder: doors at 7',
			$captured['subject'],
			'Subject should match the supplied custom value.'
		);
		$this->assertStringNotContainsString(
			'Custom Subject Event',
			$captured['subject'],
			'Custom subject should bypass the default title template.'
		);
	}

	/**
	 * `send_emails` falls back to the default `📅 {title}` template when no subject is supplied (#827).
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_with_default_subject(): void {
		$instance = Email::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Default Subject Event',
			)
		);

		$rsvp  = new Rsvp( $post_id );
		$email = 'recipient@example.test';
		$rsvp->save( $email, 'attending', 0, 0 );

		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $prev, $atts ) use ( &$captured ): bool {
				$captured = $atts;

				return (bool) $prev;
			},
			10,
			2
		);

		$instance->send_emails(
			$post_id,
			array( 'attending' => true ),
			'Test message'
		);
		$this->run_mail_jobs();

		$this->assertStringContainsString(
			'Default Subject Event',
			$captured['subject'],
			'Default subject should interpolate the event title.'
		);
		$this->assertStringContainsString(
			'📅',
			$captured['subject'],
			'Default subject should keep the calendar emoji prefix.'
		);
	}

	/**
	 * `gatherpress_email_subject` filter lets developers customize the default subject (#827).
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_subject_filter(): void {
		$instance = Email::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Filter Event',
			)
		);

		$rsvp = new Rsvp( $post_id );
		$rsvp->save( 'recipient@example.test', 'attending', 0, 0 );

		add_filter(
			'gatherpress_email_subject',
			static function ( string $subject ): string {
				return '[Community] ' . $subject;
			}
		);

		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $prev, $atts ) use ( &$captured ): bool {
				$captured = $atts;

				return (bool) $prev;
			},
			10,
			2
		);

		$instance->send_emails(
			$post_id,
			array( 'attending' => true ),
			'Test message'
		);
		$this->run_mail_jobs();

		$this->assertStringStartsWith(
			'[Community] ',
			$captured['subject'],
			'Filter should prepend the community prefix to the default subject.'
		);
		$this->assertStringContainsString(
			'Filter Event',
			$captured['subject'],
			'Filter should preserve the upstream subject value.'
		);
	}

	/**
	 * `gatherpress_email_subject` filter applies to a custom subject too (#827).
	 *
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_subject_filter_with_custom_subject(): void {
		$instance = Email::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$rsvp = new Rsvp( $post_id );
		$rsvp->save( 'recipient@example.test', 'attending', 0, 0 );

		add_filter(
			'gatherpress_email_subject',
			static function ( string $subject ): string {
				return $subject . ' [verified]';
			}
		);

		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $prev, $atts ) use ( &$captured ): bool {
				$captured = $atts;

				return (bool) $prev;
			},
			10,
			2
		);

		$instance->send_emails(
			$post_id,
			array( 'attending' => true ),
			'Test message',
			'Custom subject'
		);
		$this->run_mail_jobs();

		$this->assertSame(
			'Custom subject [verified]',
			$captured['subject'],
			'Filter should apply to the supplied subject as well.'
		);
	}

	/**
	 * Tests send_emails with locale switching for user.
	 *
	 * Covers: restore_previous_locale when switched_locale is true.
	 *
	 * @covers ::send_emails
	 */
	public function test_send_emails_restore_locale(): void {
		$instance = Email::get_instance();

		$user_id = $this->factory->user->create(
			array(
				'user_email' => 'user@example.com',
			)
		);

		// Set user locale different from site locale.
		update_user_meta( $user_id, 'locale', 'de_DE' );

		// Enable opt-in.
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '1' );

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$rsvp = new Rsvp( $post_id );

		// Create RSVP for the user.
		$rsvp->save( $user_id, 'attending' );

		$send = array( 'attending' => true );

		// Set a current user (as the editor sending the email).
		wp_set_current_user( $this->factory->user->create() );

		// Mock wp_mail.
		add_filter(
			'pre_wp_mail',
			function () {
				return true;
			}
		);

		$result = $instance->send_emails( $post_id, $send, 'Test message' );
		$this->run_mail_jobs();

		$this->assertTrue( $result, 'send_emails should return true' );
		// If this test runs without errors, restore_previous_locale was called correctly.
	}

	/**
	 * Tests get_recipients skipping RSVP with empty email.
	 *
	 * Covers: skip recipient when email is empty.
	 *
	 * @covers ::get_recipients
	 */
	public function test_get_recipients_skip_empty_email(): void {
		$instance = Email::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$rsvp = new Rsvp( $post_id );

		// Create RSVP with valid email first.
		$email   = 'test@example.com';
		$comment = $rsvp->save( $email, 'attending', 0, 0 );

		// Now remove the email to test the skip logic.
		wp_update_comment(
			array(
				'comment_ID'           => $comment['comment_id'],
				'comment_author_email' => '',
			)
		);

		$send = array( 'attending' => true );

		$recipients = $instance->get_recipients( $send, $post_id );

		$this->assertEmpty( $recipients, 'Recipients should be empty when email is empty' );
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

	/**
	 * Helper to get user IDs from recipients array for users only.
	 *
	 * @param array $recipients Array of recipient objects.
	 *
	 * @return array
	 */
	private function get_recipient_user_ids( array $recipients ): array {
		return array_map(
			static function ( $recipient ): int {
				return $recipient['user_id'];
			},
			array_filter(
				$recipients,
				static function ( $recipient ) {
					return $recipient['is_user'];
				}
			)
		);
	}
}
