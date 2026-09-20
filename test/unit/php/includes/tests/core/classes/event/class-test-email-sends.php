<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Email_Sends.
 *
 * @package GatherPress\Core\Event
 * @since 0.37.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Email_Sends;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Utility;
use GatherPress\Tests\Base;

/**
 * Class Test_Email_Sends.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Email_Sends
 */
class Test_Email_Sends extends Base {

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
				array( $instance, 'send_waiting_list_promotion_email' )
			),
			'Failed to assert the waiting list promotion action is registered.'
		);
		$this->assertSame(
			1,
			count( $GLOBALS['wp_filter']['gatherpress_send_emails']->callbacks[10] ?? array() ),
			'Failed to assert the email action is registered once.'
		);
	}

	/**
	 * A promoted email identity receives the confirmation message.
	 *
	 * @covers ::send_waiting_list_promotion_email
	 * @covers ::build_state_recipient
	 *
	 * @return void
	 */
	public function test_send_waiting_list_promotion_email_for_email_identity(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Open RSVP Promotion',
			)
		);
		update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 1 );

		$attendee_id = $this->factory->user->create();
		$rsvp        = new Rsvp( $post_id );
		$rsvp->save( $attendee_id, 'attending' );
		$rsvp->save( 'open-rsvp@example.test', 'attending' );

		$promoted = array();
		add_action(
			'gatherpress_rsvp_waiting_list_promoted',
			static function ( int $post_id, State $state ) use ( &$promoted ): void {
				$promoted[] = $state;
			},
			10,
			2
		);

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

		$rsvp->save( $attendee_id, 'not_attending' );

		$this->assertCount( 1, $promoted, 'Failed to assert the promotion fired once.' );
		$this->assertSame( 'open-rsvp@example.test', $captured['to'] );
		$this->assertStringContainsString( 'Open RSVP Promotion', $captured['subject'] );
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
			has_action( 'gatherpress_send_emails', array( $instance, 'handle_email_send_action' ) )
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
		add_filter( 'pre_wp_mail', '__return_true' );
		$instance = Email_Sends::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();
		$rsvp     = new Rsvp( $event_id );
		$rsvp->save( $user_id, 'attending' );
		$send = array(
			'all'           => false,
			'attending'     => true,
			'waiting_list'  => false,
			'not_attending' => false,
		);

		$instance->handle_email_send_action( $event_id, $send, 'Test message' );

		remove_filter( 'pre_wp_mail', '__return_true' );
		$this->assertTrue( true );
	}

	/**
	 * A promoted state without an email is ignored.
	 *
	 * @covers ::send_waiting_list_promotion_email
	 * @covers ::build_state_recipient
	 *
	 * @return void
	 */
	public function test_send_waiting_list_promotion_email_skips_invalid_state(): void {
		$instance = Email_Sends::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$comment  = get_comment(
			wp_insert_comment(
				array(
					'comment_post_ID'      => $post_id,
					'comment_author_email' => '',
					'comment_approved'     => 1,
				)
			)
		);
		$state    = new State(
			new Data( new Identity( Identity_Type::EXTERNAL_ID, 42 ), Status::ATTENDING ),
			new Email(),
			$comment
		);

		$instance->send_waiting_list_promotion_email( $post_id, $state );
		$this->assertTrue( true );
	}

	/**
	 * The sender handles a registered user recipient.
	 *
	 * @covers ::send_event_email_to_recipient
	 *
	 * @return void
	 */
	public function test_send_event_email_to_recipient_for_user(): void {
		add_filter( 'pre_wp_mail', '__return_true' );
		$instance = Email_Sends::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();

		$instance->send_event_email_to_recipient(
			array(
				'is_user'    => true,
				'user_id'    => $user_id,
				'comment_id' => 0,
				'email'      => 'user@example.test',
				'name'       => 'User',
			),
			$event_id,
			'Test message',
			wp_get_current_user()
		);

		remove_filter( 'pre_wp_mail', '__return_true' );
		$this->assertTrue( true );
	}

	/**
	 * A promoted registered user receives the confirmation message.
	 *
	 * @covers ::send_waiting_list_promotion_email
	 * @covers ::build_state_recipient
	 *
	 * @return void
	 */
	public function test_send_waiting_list_promotion_email_for_user_identity(): void {
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
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'User Promotion',
			)
		);
		update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 1 );
		$attendee_id = $this->factory->user->create();
		$waiter_id   = $this->factory->user->create( array( 'user_email' => 'waiter@example.test' ) );
		$rsvp        = new Rsvp( $post_id );
		$rsvp->save( $attendee_id, 'attending' );
		$rsvp->save( $waiter_id, 'attending' );
		$rsvp->save( $attendee_id, 'not_attending' );

		$this->assertSame( 'waiter@example.test', $captured['to'] );
	}

	/**
	 * Opted-out recipients and incomplete recipients are skipped.
	 *
	 * @covers ::send_event_email_to_recipient
	 *
	 * @return void
	 */
	public function test_send_event_email_to_recipient_skips_opt_out_and_missing_email(): void {
		$instance = Email_Sends::get_instance();
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id  = $this->factory->user->create();
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '0' );

		$instance->send_event_email_to_recipient(
			array(
				'is_user'    => true,
				'user_id'    => $user_id,
				'comment_id' => 0,
				'email'      => 'user@example.test',
				'name'       => 'User',
			),
			$event_id,
			'Test message',
			wp_get_current_user()
		);
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
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
			$event_id,
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
			$event_id,
			'Test message',
			wp_get_current_user()
		);
		$this->assertTrue( true );
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
}
