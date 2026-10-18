<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Email.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Email;
use GatherPress\Core\Rsvp\Token;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Email.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Email
 */
class Test_Email extends Base {

	/**
	 * Emails sent during a test, as recipient, subject and message.
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
	 * @param mixed $previous Short-circuit value from earlier filters.
	 * @param array $atts     The wp_mail() arguments.
	 *
	 * @return bool Always true, so wp_mail() reports a send.
	 */
	public function capture_mail( $previous, array $atts ): bool {
		$this->sent[] = array( $atts['to'], $atts['subject'], $atts['message'] );

		return true;
	}

	/**
	 * Check that the constructor registers the confirmation mail type.
	 *
	 * @covers ::__construct
	 * @covers ::register_mail_types
	 *
	 * @return void
	 */
	public function test_register_mail_types(): void {
		// The constructor runs at boot, before coverage records, so run it again here.
		Utility::invoke_hidden_method( Email::get_instance(), '__construct' );

		$types = Utility::get_hidden_property( Mailer::get_instance(), 'types' );

		$this->assertArrayHasKey( Email::CONFIRMATION_TYPE, $types );
		$this->assertSame(
			Mailer::CONTEXT_RSVP,
			$types[ Email::CONFIRMATION_TYPE ]['context'],
			'The link confirms the RSVP, so it should skip the opt-in.'
		);
		$this->assertArrayNotHasKey( 'users', $types[ Email::CONFIRMATION_TYPE ] );
	}

	/**
	 * Check that a queued confirmation reaches the person who RSVP'd, even with event updates off.
	 *
	 * @covers ::queue_confirmation
	 *
	 * @return void
	 */
	public function test_confirmation_email_reaches_the_person_who_rsvped(): void {
		$comment_id = $this->open_rsvp( 'Board game night' );

		update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', '0' );

		$this->assertTrue( Email::get_instance()->queue_confirmation( $comment_id ) );
		$this->assertSame( array(), $this->sent, 'The email waits for the job.' );
		$this->assertSame( '', ( new Token( $comment_id ) )->get_token(), 'The token waits for the email.' );

		$this->run_mail_jobs();

		$this->assertCount( 1, $this->sent );
		$this->assertSame( 'visitor@example.test', $this->sent[0][0] );
		$this->assertSame( 'Confirm your RSVP for Board game night', $this->sent[0][1] );
		$this->assertStringContainsString( esc_url( ( new Token( $comment_id ) )->generate_url() ), $this->sent[0][2] );
	}

	/**
	 * Check that the recipient is the RSVP's address, and nobody once the RSVP is gone.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called by short delegation.
	 *
	 * @covers ::get_confirmation_recipient
	 *
	 * @return void
	 */
	public function test_get_confirmation_recipient(): void {
		$comment_id = $this->open_rsvp( 'Book club' );
		$recipient  = static fn( int $id ): array => Utility::invoke_hidden_method(
			Email::get_instance(),
			'get_confirmation_recipient',
			array( array( 'comment_id' => $id ) )
		);

		$this->assertSame( array(), $recipient( 0 ), 'Without an RSVP there is nobody to send to.' );
		$this->assertSame(
			array(
				array(
					'is_user'    => false,
					'user_id'    => 0,
					'comment_id' => $comment_id,
					'email'      => 'visitor@example.test',
					'name'       => 'Visitor',
				),
			),
			$recipient( $comment_id )
		);
	}

	/**
	 * Check that the email names the event and carries a link issued as it is built.
	 *
	 * @covers ::compose_confirmation_email
	 *
	 * @return void
	 */
	public function test_compose_confirmation_email(): void {
		$comment_id = $this->open_rsvp( 'Trivia night' );
		$compose    = static fn( int $id ): array => Utility::invoke_hidden_method(
			Email::get_instance(),
			'compose_confirmation_email',
			array( array(), array( 'comment_id' => $id ) )
		);

		$email = $compose( $comment_id );
		$token = new Token( $comment_id );

		$this->assertSame( 'Confirm your RSVP for Trivia night', $email['subject'] );
		$this->assertStringContainsString( esc_url( $token->generate_url() ), $email['body'] );
		$this->assertEqualsWithDelta(
			time(),
			$token->get_issued_time(),
			5,
			'The link\'s 24 hours start as the email is built.'
		);
		$this->assertSame( array( 'Content-Type: text/html; charset=UTF-8' ), $email['headers'] );
		$this->assertSame(
			'Confirm your RSVP for this event',
			$compose( 0 )['subject'],
			'Without an event the subject falls back to a generic name.'
		);
	}

	/**
	 * Create an RSVP from someone without an account.
	 *
	 * @param string $title The event title.
	 *
	 * @return int The RSVP comment ID.
	 */
	private function open_rsvp( string $title ): int {
		return $this->factory->comment->create(
			array(
				'comment_post_ID'      => $this->factory->post->create(
					array(
						'post_type'  => Event::POST_TYPE,
						'post_title' => $title,
					)
				),
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'Visitor',
				'comment_author_email' => 'visitor@example.test',
				'comment_approved'     => 0,
			)
		);
	}
}
