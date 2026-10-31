<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Reminder.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use DateTime;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Reminder;
use GatherPress\Core\Event\Scheduler;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Core\Utility as Core_Utility;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Reminder.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Reminder
 */
class Test_Reminder extends Base {

	/**
	 * Emails sent during a test, as recipient, subject and body.
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
	 * Remove the mail capture, queued jobs and filters.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ) );
		remove_all_filters( 'gatherpress_event_reminder_subject' );
		remove_all_filters( 'gatherpress_event_reminder_body' );
		wp_unschedule_hook( Mailer::JOB_HOOK );
		wp_unschedule_hook( Scheduler::START_CRON_ACTION );
		delete_option( Settings::OPTION_NAME );

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
	 * Turn reminders on, sent the given number of hours ahead.
	 *
	 * @param int $hours Hours before the start.
	 *
	 * @return void
	 */
	private function enable_reminders( int $hours = 24 ): void {
		Settings::get_instance()->set( 'enable_event_reminders', true );
		Settings::get_instance()->set( 'event_reminder_hours', $hours );
	}

	/**
	 * Create a published event starting two days from now.
	 *
	 * @param string $title Event title.
	 *
	 * @return int The event post ID.
	 */
	private function create_event( string $title = 'Meetup' ): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		$start   = new DateTime( '+2 days' );
		$end     = new DateTime( '+2 days +2 hours' );

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => $start->format( 'Y-m-d H:i:s' ),
				'datetime_end'   => $end->format( 'Y-m-d H:i:s' ),
				'timezone'       => 'UTC',
			)
		);

		return $post_id;
	}

	/**
	 * Check the hooks the reminder class listens on.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Reminder::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'gatherpress_event_starting_lead_time',
				'priority' => 10,
				'callback' => array( $instance, 'get_lead_time' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::STARTING_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'queue_reminder' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'add_option_' . Settings::OPTION_NAME,
				'priority' => 10,
				'callback' => array( $instance, 'maybe_reschedule_on_add' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'update_option_' . Settings::OPTION_NAME,
				'priority' => 10,
				'callback' => array( $instance, 'maybe_reschedule' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Check that the reminder mail type is registered with the reminder context.
	 *
	 * @covers ::register_mail_type
	 *
	 * @return void
	 */
	public function test_register_mail_type(): void {
		Utility::invoke_hidden_method( Reminder::get_instance(), 'register_mail_type' );

		$types = Utility::get_hidden_property( Mailer::get_instance(), 'types' );

		$this->assertSame( Mailer::CONTEXT_REMINDER, $types[ Reminder::MAIL_TYPE ]['context'] );
	}

	/**
	 * Check that the lead time only changes while reminders are on.
	 *
	 * @covers ::is_enabled
	 * @covers ::get_lead_time
	 *
	 * @return void
	 */
	public function test_get_lead_time(): void {
		$instance = Reminder::get_instance();

		$this->assertFalse( $instance->is_enabled(), 'Off by default.' );
		$this->assertSame( 0, $instance->get_lead_time( 0 ) );

		$this->enable_reminders( 3 );
		$this->assertTrue( $instance->is_enabled() );
		$this->assertSame( 3 * HOUR_IN_SECONDS, $instance->get_lead_time( 0 ) );

		Settings::get_instance()->set( 'event_reminder_hours', 0 );
		$this->assertSame( HOUR_IN_SECONDS, $instance->get_lead_time( 0 ), 'Never less than an hour.' );
	}

	/**
	 * Check that upcoming events move only when a reminder setting changes.
	 *
	 * @covers ::maybe_reschedule
	 * @covers ::maybe_reschedule_on_add
	 *
	 * @return void
	 */
	public function test_maybe_reschedule(): void {
		$instance = Reminder::get_instance();
		$post_id  = $this->create_event();
		$start    = strtotime( (string) get_post_meta( $post_id, Scheduler::START_META_KEY, true ) );

		$this->assertSame( $start, wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $post_id ) ) );

		// The first save adds the option, which fires add_option_gatherpress_settings.
		$this->enable_reminders( 24 );

		$this->assertSame(
			$start - DAY_IN_SECONDS,
			wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $post_id ) ),
			'Turning reminders on moves the run to the reminder time.'
		);

		// An unrelated setting leaves the run alone.
		wp_clear_scheduled_hook( Scheduler::START_CRON_ACTION, array( $post_id ) );
		$instance->maybe_reschedule( array( 'rsvp_mode' => 'a' ), array( 'rsvp_mode' => 'b' ) );

		$this->assertFalse( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $post_id ) ) );

		// Changing the hours once the option exists goes through update_option.
		Settings::get_instance()->set( 'event_reminder_hours', 2 );

		$this->assertSame(
			$start - 2 * HOUR_IN_SECONDS,
			wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $post_id ) )
		);
	}

	/**
	 * Check that a reminder reaches attendees, in the format they will see.
	 *
	 * @covers ::queue_reminder
	 * @covers ::compose
	 *
	 * @return void
	 */
	public function test_reminder_reaches_attendees_only(): void {
		$this->enable_reminders();

		$post_id   = $this->create_event( 'Board game night' );
		$attendee  = $this->factory->user->create();
		$declined  = $this->factory->user->create();
		$opted_out = $this->factory->user->create();
		$rsvp      = new Rsvp( $post_id );

		$rsvp->save( $attendee, 'attending' );
		$rsvp->save( $declined, 'not_attending' );
		$rsvp->save( $opted_out, 'attending' );
		$rsvp->save( 'guest@example.test', 'attending', 0, 0 );
		update_user_meta( $opted_out, 'gatherpress_event_reminders_opt_in', '0' );

		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();

		$to = array_column( $this->sent, 0 );
		sort( $to );

		$expected = array( get_userdata( $attendee )->user_email, 'guest@example.test' );
		sort( $expected );

		$this->assertSame( $expected, $to );
		$this->assertSame( 'Reminder: Board game night', $this->sent[0][1] );
		$this->assertStringContainsString( 'This is a reminder that you are attending', $this->sent[0][2] );
		$this->assertStringContainsString( 'Google Calendar', $this->sent[0][2] );
		$this->assertStringContainsString( 'View or change your RSVP', $this->sent[0][2] );
		$this->assertStringContainsString( esc_url( (string) get_permalink( $post_id ) ), $this->sent[0][2] );
	}

	/**
	 * Check that a second run for the same start sends nothing, and a new start sends again.
	 *
	 * @covers ::queue_reminder
	 *
	 * @return void
	 */
	public function test_queue_reminder_sends_once_per_start(): void {
		$this->enable_reminders();

		$post_id = $this->create_event();
		$start   = (string) get_post_meta( $post_id, Scheduler::START_META_KEY, true );

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();
		$this->assertCount( 1, $this->sent );
		$this->assertSame( $start, get_post_meta( $post_id, Reminder::SENT_META_KEY, true ) );

		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();
		$this->assertCount( 1, $this->sent, 'The same start is not reminded twice.' );

		update_post_meta( $post_id, Scheduler::START_META_KEY, gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ) );

		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();
		$this->assertCount( 2, $this->sent, 'A moved start is reminded again.' );
	}

	/**
	 * Check that nothing is queued while reminders are off, for a post without RSVPs, after the start,
	 * or while RSVP is off.
	 *
	 * @covers ::queue_reminder
	 *
	 * @return void
	 */
	public function test_queue_reminder_skips_when_off_or_unsupported(): void {
		$post_id = $this->create_event();
		$started = $this->create_event();

		update_post_meta( $started, Scheduler::START_META_KEY, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		( new Rsvp( $started ) )->save( 'late@example.test', 'attending', 0, 0 );

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();
		$this->assertSame( array(), $this->sent, 'Reminders are off.' );

		$this->enable_reminders();

		Reminder::get_instance()->queue_reminder( $this->factory->post->create() );
		$this->run_mail_jobs();
		$this->assertSame( array(), $this->sent, 'A post without RSVPs gets nothing.' );

		Reminder::get_instance()->queue_reminder( $started );
		$this->run_mail_jobs();
		$this->assertSame( array(), $this->sent, 'A cron run after the start sends nothing.' );

		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );
		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();
		$this->assertSame( array(), $this->sent, 'Nothing goes out while RSVP is off.' );
	}

	/**
	 * Check that a failed enqueue leaves the event unmarked, so a later run can retry.
	 *
	 * @covers ::queue_reminder
	 *
	 * @return void
	 */
	public function test_queue_reminder_does_not_mark_a_failed_enqueue(): void {
		$this->enable_reminders();

		$post_id = $this->create_event();

		// WP-Cron refuses the job, so Mailer reports it was not queued.
		add_filter( 'schedule_event', '__return_false' );

		Reminder::get_instance()->queue_reminder( $post_id );

		remove_filter( 'schedule_event', '__return_false' );

		$this->assertSame( '', get_post_meta( $post_id, Reminder::SENT_META_KEY, true ) );
	}

	/**
	 * Check that the subject and body filters change the email.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called from a closure.
	 *
	 * @covers ::compose
	 *
	 * @return void
	 */
	public function test_compose_filters(): void {
		$post_id = $this->create_event( 'Code review' );

		add_filter( 'gatherpress_event_reminder_subject', static fn( string $subject ): string => "[Site] {$subject}" );
		add_filter( 'gatherpress_event_reminder_body', static fn(): string => 'Custom body' );

		$email = Utility::invoke_hidden_method(
			Reminder::get_instance(),
			'compose',
			array(
				array(
					'is_user'    => false,
					'user_id'    => 0,
					'comment_id' => 1,
					'email'      => 'guest@example.test',
					'name'       => 'Guest',
				),
				array( 'post_id' => $post_id ),
			)
		);

		$this->assertSame( '[Site] Reminder: Code review', $email['subject'] );
		$this->assertSame( 'Custom body', $email['body'] );
		$this->assertSame( array( 'Content-Type: text/html; charset=UTF-8' ), $email['headers'] );
	}

	/**
	 * Check that only attending RSVPs are picked.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called from a closure.
	 *
	 * @covers ::get_recipients
	 *
	 * @return void
	 */
	public function test_get_recipients(): void {
		$post_id = $this->create_event();
		$rsvp    = new Rsvp( $post_id );

		$rsvp->save( 'yes@example.test', 'attending', 0, 0 );
		$rsvp->save( 'no@example.test', 'not_attending', 0, 0 );

		$recipients = Utility::invoke_hidden_method(
			Reminder::get_instance(),
			'get_recipients',
			array( array( 'post_id' => $post_id ) )
		);

		$this->assertSame( array( 'yes@example.test' ), array_column( $recipients, 'email' ) );
	}

	/**
	 * Check that the template shows the online link for an online event.
	 *
	 * @coversNothing
	 *
	 * @return void
	 */
	public function test_template_shows_online_link(): void {
		$post_id = $this->create_event();

		( new Event( $post_id ) )->set_online( true, 'https://meet.example.test/room' );

		$body = Core_Utility::render_template(
			GATHERPRESS_CORE_PATH . '/includes/templates/admin/emails/event-reminder.php',
			array( 'event_id' => $post_id )
		);

		$this->assertStringContainsString( 'https://meet.example.test/room', $body );
	}
}
