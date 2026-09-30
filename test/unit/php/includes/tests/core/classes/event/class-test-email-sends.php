<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Email_Sends.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Email_Sends;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Tests\Base;

/**
 * Class Test_Email_Sends.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Email_Sends
 */
class Test_Email_Sends extends Base {

	/**
	 * Clear any promotion email the tests queued, so one test's schedule does
	 * not survive into the next.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_clear_scheduled_hook( Email_Sends::PROMOTION_CRON_HOOK );

		parent::tearDown();
	}

	/**
	 * Coverage for the email hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Email_Sends::get_instance();

		$this->assertSame(
			10,
			has_action( 'gatherpress_send_emails', array( $instance, 'handle_email_send_action' ) ),
			'Failed to assert the event email action is registered.'
		);
		$this->assertSame(
			10,
			has_action(
				'gatherpress_rsvp_waiting_list_promoted',
				array( $instance, 'schedule_waiting_list_promotion_email' )
			),
			'Failed to assert the waiting list promotion scheduler is registered.'
		);
		$this->assertSame(
			10,
			has_action(
				Email_Sends::PROMOTION_CRON_HOOK,
				array( $instance, 'send_waiting_list_promotion_email' )
			),
			'Failed to assert the promotion email cron handler is registered.'
		);
		$this->assertSame(
			1,
			count( $GLOBALS['wp_filter']['gatherpress_send_emails']->callbacks[10] ?? array() ),
			'Failed to assert the email action is registered once.'
		);
	}

	/**
	 * Directly exercises the hook registration method.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks_directly(): void {
		$property = ( new \ReflectionClass( Email_Sends::class ) )->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null );
		$instance    = Email_Sends::get_instance();
		$constructor = new \ReflectionMethod( Email_Sends::class, '__construct' );
		$constructor->setAccessible( true );
		$constructor->invoke( $instance );
		$method = new \ReflectionMethod( Email_Sends::class, 'setup_hooks' );
		$method->setAccessible( true );
		$method->invoke( $instance );

		$this->assertSame(
			10,
			has_action( 'gatherpress_send_emails', array( $instance, 'handle_email_send_action' ) ),
			'Failed to assert the event email action is registered.'
		);
	}

	/**
	 * Invalid post IDs do not dispatch event emails.
	 *
	 * @covers ::handle_email_send_action
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_rejects_non_event_posts(): void {
		$instance = Email_Sends::get_instance();
		$post_id  = $this->factory->post->create();

		$this->assertFalse(
			$instance->send_emails(
				$post_id,
				array(
					'all'           => false,
					'attending'     => false,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				'Test message'
			)
		);
	}

	/**
	 * Valid events dispatch emails to selected recipients.
	 *
	 * @covers ::handle_email_send_action
	 * @covers ::send_emails
	 *
	 * @return void
	 */
	public function test_send_emails_dispatches_to_recipients(): void {
		$captured = array();
		$this->capture_mail( $captured );
		$instance = Email_Sends::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create(
			array( 'user_email' => 'attendee@example.test' )
		);
		$rsvp     = new Rsvp( $event_id );
		$rsvp->save( $user_id, 'attending' );
		$send = array(
			'all'           => false,
			'attending'     => true,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$instance->handle_email_send_action( $event_id, $send, 'Test message' );

		$this->assertSame( 'attendee@example.test', $captured['to'] );
	}

	/**
	 * A promotion queues a cron event instead of mailing inside the request.
	 *
	 * @covers ::schedule_waiting_list_promotion_email
	 *
	 * @return void
	 */
	public function test_schedule_waiting_list_promotion_email(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$state   = $this->create_attending_state( $post_id );

		Email_Sends::get_instance()->schedule_waiting_list_promotion_email( $post_id, $state );

		$this->assertNotFalse(
			wp_next_scheduled(
				Email_Sends::PROMOTION_CRON_HOOK,
				array( $post_id, (int) $state->comment->comment_ID )
			),
			'Failed to assert the promotion email is scheduled on cron with the event and RSVP comment.'
		);
	}

	/**
	 * A promoted member receives the confirmation email from the cron handler.
	 *
	 * @covers ::send_waiting_list_promotion_email
	 *
	 * @return void
	 */
	public function test_send_waiting_list_promotion_email_for_user_identity(): void {
		$captured = array();
		$this->capture_mail( $captured );
		$post_id    = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'User Promotion',
			)
		);
		$state      = $this->create_attending_state( $post_id, $this->create_user( 'waiter@example.test' ) );
		$comment_id = (int) $state->comment->comment_ID;

		Email_Sends::get_instance()->send_waiting_list_promotion_email( $post_id, $comment_id );

		$this->assertSame( 'waiter@example.test', $captured['to'] );
		$this->assertStringContainsString( 'User Promotion', $captured['subject'] );
		$this->assertStringContainsString( 'now confirmed', $captured['message'] );
	}

	/**
	 * A promoted open RSVP with an email address is mailed too.
	 *
	 * @covers ::send_waiting_list_promotion_email
	 *
	 * @return void
	 */
	public function test_send_waiting_list_promotion_email_for_email_identity(): void {
		$captured = array();
		$this->capture_mail( $captured );
		$post_id    = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Open RSVP Promotion',
			)
		);
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'Open Guest',
				'comment_author_email' => 'open-rsvp@example.test',
				'comment_approved'     => 1,
			)
		);

		Email_Sends::get_instance()->send_waiting_list_promotion_email( $post_id, $comment_id );

		$this->assertSame( 'open-rsvp@example.test', $captured['to'] );
		$this->assertStringContainsString( 'Open RSVP Promotion', $captured['subject'] );
	}

	/**
	 * The promotion email hides the RSVP button the member no longer needs.
	 *
	 * @covers ::send_waiting_list_promotion_email
	 *
	 * @return void
	 */
	public function test_promotion_email_hides_rsvp_button(): void {
		$captured = array();
		$this->capture_mail( $captured );
		$post_id    = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$state      = $this->create_attending_state( $post_id, $this->create_user( 'button@example.test' ) );
		$comment_id = (int) $state->comment->comment_ID;

		Email_Sends::get_instance()->send_waiting_list_promotion_email( $post_id, $comment_id );

		$this->assertStringNotContainsString(
			'RSVP Now',
			$captured['message'],
			'Failed to assert the promotion email omits the RSVP button.'
		);
	}

	/**
	 * The bulk event email keeps the RSVP button.
	 *
	 * @covers ::send_event_email_to_recipient
	 *
	 * @return void
	 */
	public function test_bulk_email_keeps_rsvp_button(): void {
		$captured = array();
		$this->capture_mail( $captured );
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id = $this->create_user( 'bulk@example.test' );

		Email_Sends::get_instance()->send_event_email_to_recipient(
			array(
				'is_user'    => true,
				'user_id'    => $user_id,
				'comment_id' => 0,
				'email'      => 'bulk@example.test',
				'name'       => 'Bulk',
			),
			$post_id,
			'Test message',
			wp_get_current_user()
		);

		$this->assertStringContainsString(
			'RSVP Now',
			$captured['message'],
			'Failed to assert the bulk email still renders the RSVP button.'
		);
	}

	/**
	 * A missing comment is ignored rather than mailed.
	 *
	 * @covers ::send_waiting_list_promotion_email
	 *
	 * @return void
	 */
	public function test_send_waiting_list_promotion_email_skips_missing_comment(): void {
		$captured = array();
		$this->capture_mail( $captured );
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		Email_Sends::get_instance()->send_waiting_list_promotion_email( $post_id, 999999 );

		$this->assertArrayNotHasKey( 'to', $captured );
	}

	/**
	 * A comment with no email address is ignored rather than mailed.
	 *
	 * @covers ::send_waiting_list_promotion_email
	 *
	 * @return void
	 */
	public function test_send_waiting_list_promotion_email_skips_missing_email(): void {
		$captured = array();
		$this->capture_mail( $captured );
		$post_id    = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => 'No Address',
				'comment_author_email' => '',
				'comment_approved'     => 1,
			)
		);

		Email_Sends::get_instance()->send_waiting_list_promotion_email( $post_id, $comment_id );

		$this->assertArrayNotHasKey( 'to', $captured );
	}

	/**
	 * Opted-out recipients and incomplete recipients are skipped.
	 *
	 * @covers ::send_event_email_to_recipient
	 *
	 * @return void
	 */
	public function test_send_event_email_to_recipient_skips_opt_out_and_missing_email(): void {
		$captured = array();
		$this->capture_mail( $captured );
		$instance = Email_Sends::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '0' );

		$instance->send_event_email_to_recipient(
			array(
				'is_user'    => true,
				'user_id'    => $user_id,
				'comment_id' => 0,
				'email'      => 'opted-out@example.test',
				'name'       => 'Opted Out',
			),
			$post_id,
			'Test message',
			wp_get_current_user()
		);

		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_author'   => 'Anonymous',
				'comment_approved' => 1,
			)
		);
		update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', '0' );

		$instance->send_event_email_to_recipient(
			array(
				'is_user'    => false,
				'user_id'    => 0,
				'comment_id' => $comment_id,
				'email'      => 'anon@example.test',
				'name'       => 'Anonymous',
			),
			$post_id,
			'Test message',
			wp_get_current_user()
		);

		$instance->send_event_email_to_recipient(
			array(
				'is_user'    => false,
				'user_id'    => 0,
				'comment_id' => 0,
				'email'      => '',
				'name'       => '',
			),
			$post_id,
			'Test message',
			wp_get_current_user()
		);

		$this->assertArrayNotHasKey( 'to', $captured );
	}

	/**
	 * Comment recipients preserve anonymous comment details and reject empty email.
	 *
	 * @covers ::build_comment_recipient
	 *
	 * @return void
	 */
	public function test_build_comment_recipient_paths(): void {
		$instance                      = Email_Sends::get_instance();
		$comment                       = new \stdClass();
		$comment->comment_ID           = 1;
		$comment->user_id              = 0;
		$comment->comment_author_email = 'anon@example.test';
		$comment->comment_author       = 'Anonymous';
		$recipient                     = $instance->build_comment_recipient( $comment );

		$this->assertSame( 'anon@example.test', $recipient['email'] );
		$this->assertSame( 'Anonymous', $recipient['name'] );

		$comment->comment_author_email = '';

		$this->assertNull( $instance->build_comment_recipient( $comment ) );

		$user_id                       = $this->factory->user->create(
			array(
				'user_email'   => 'user-recipient@example.test',
				'display_name' => 'User Recipient',
			)
		);
		$comment->user_id              = $user_id;
		$comment->comment_author_email = 'comment@example.test';
		$recipient                     = $instance->build_comment_recipient( $comment );

		$this->assertSame( 'user-recipient@example.test', $recipient['email'] );
		$this->assertSame( 'User Recipient', $recipient['name'] );
	}

	/**
	 * Recipient selection covers all-user, status, and empty selections.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients_paths(): void {
		$instance = Email_Sends::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$empty    = array(
			'all'           => false,
			'attending'     => false,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$this->assertEmpty( $instance->get_recipients( $empty, $event_id ) );

		$user_id = $this->factory->user->create();
		$rsvp    = new Rsvp( $event_id );
		$rsvp->save( $user_id, 'attending' );

		$attending  = array(
			'all'           => false,
			'attending'     => true,
			'waiting_list'  => false,
			'not_attending' => false,
		);
		$recipients = $instance->get_recipients( $attending, $event_id );

		$this->assertNotEmpty( $recipients );

		$all = array(
			'all'           => true,
			'attending'     => false,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$this->assertNotEmpty( $instance->get_recipients( $all, $event_id ) );
	}

	/**
	 * Capture the arguments handed to wp_mail for the duration of a test.
	 *
	 * @param array $captured Mail attributes, filled by reference when wp_mail
	 *                         runs, so a test can assert against the last
	 *                         message and against no message at all.
	 *
	 * @return void
	 */
	private function capture_mail( array &$captured ): void {
		$captured = array();

		add_filter(
			'pre_wp_mail',
			static function ( $previous, $attributes ) use ( &$captured ): bool {
				$captured = $attributes;
				return true;
			},
			10,
			2
		);
	}

	/**
	 * Create a user with a known email address.
	 *
	 * @param string $email User email address.
	 *
	 * @return int User ID.
	 */
	private function create_user( string $email ): int {
		return (int) $this->factory->user->create( array( 'user_email' => $email ) );
	}

	/**
	 * Build an attending RSVP state for a member.
	 *
	 * Saving through `Rsvp` keeps the stored comment consistent with the
	 * status and provider terms a hydrated state reads back.
	 *
	 * @param int      $post_id Event post ID.
	 * @param int|null $user_id Optional. User to RSVP as. Defaults to a new user.
	 *
	 * @return State The attending state.
	 */
	private function create_attending_state( int $post_id, ?int $user_id = null ): State {
		$user_id = $user_id ?? $this->create_user( 'member@example.test' );
		$rsvp    = new Rsvp( $post_id );
		$record  = $rsvp->save( $user_id, 'attending' );
		$state   = ( new Rsvp( $post_id ) )->find( $user_id );

		$this->assertInstanceOf(
			State::class,
			$state,
			'Failed to save an attending RSVP for the promotion test.'
		);
		$this->assertSame( $record['comment_id'], (int) $state->comment->comment_ID );

		return $state;
	}
}
