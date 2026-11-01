<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Reminder.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Reminder;
use GatherPress\Core\Event\Scheduler;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
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
	 * Remove the mail capture, scheduled jobs and settings.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ) );
		wp_unschedule_hook( Mailer::JOB_HOOK );
		wp_unschedule_hook( Scheduler::START_CRON_ACTION );
		wp_unschedule_hook( Reminder::SWEEP_ACTION );
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
	 * Turn reminders on, sent at the given timings only.
	 *
	 * @param string[] $timings Timing names from Reminder::get_timings().
	 *
	 * @return void
	 */
	private function enable_reminders( array $timings = array( 'day_before' ) ): void {
		Settings::get_instance()->set( 'enable_event_reminders', true );

		foreach ( array_keys( Reminder::get_instance()->get_timings() ) as $timing ) {
			$checked = in_array( $timing, $timings, true );

			Settings::get_instance()->set( Reminder::get_timing_setting( $timing ), $checked );
		}
	}

	/**
	 * Turn reminders off, since they are on by default.
	 *
	 * @return void
	 */
	private function disable_reminders(): void {
		Settings::get_instance()->set( 'enable_event_reminders', false );
	}

	/**
	 * Create a published event lasting two hours.
	 *
	 * @param string $title        Event title.
	 * @param int    $start_offset Seconds from now to the start. Defaults to five hours, inside a day.
	 * @param string $status       Post status.
	 *
	 * @return int The event post ID.
	 */
	private function create_event(
		string $title = 'Meetup',
		int $start_offset = 5 * HOUR_IN_SECONDS,
		string $status = 'publish'
	): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => $title,
				'post_status' => $status,
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', time() + $start_offset ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', time() + $start_offset + 2 * HOUR_IN_SECONDS ),
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
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'schedule_sweep' ),
			),
			array(
				'type'     => 'action',
				'name'     => Reminder::SWEEP_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'sweep' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Check the default timings, their settings keys and the filter.
	 *
	 * @covers ::get_timings
	 * @covers ::get_timing_setting
	 *
	 * @return void
	 */
	public function test_get_timings(): void {
		$instance = Reminder::get_instance();
		$timings  = $instance->get_timings();

		$this->assertSame( array( 'day_before', 'hour_before' ), array_keys( $timings ) );
		$this->assertSame( DAY_IN_SECONDS, $timings['day_before']['seconds'] );
		$this->assertTrue( $timings['day_before']['default'], 'A day before is checked by default.' );
		$this->assertSame( HOUR_IN_SECONDS, $timings['hour_before']['seconds'] );
		$this->assertFalse( $timings['hour_before']['default'] );
		$this->assertSame( 'event_reminder_day_before', Reminder::get_timing_setting( 'day_before' ) );

		$filter = static function ( array $timings ): array {
			unset( $timings['hour_before'] );

			$timings['week_before'] = array(
				'label'   => 'A week before',
				'seconds' => WEEK_IN_SECONDS,
				'default' => false,
			);

			return $timings;
		};

		add_filter( 'gatherpress_event_reminder_timings', $filter );
		$this->assertSame( array( 'day_before', 'week_before' ), array_keys( $instance->get_timings() ) );
		remove_filter( 'gatherpress_event_reminder_timings', $filter );
	}

	/**
	 * Check that only checked timings count, a day before by default.
	 *
	 * @covers ::get_checked_timings
	 *
	 * @return void
	 */
	public function test_get_checked_timings(): void {
		$instance = Reminder::get_instance();

		$this->assertSame( array( 'day_before' => DAY_IN_SECONDS ), $instance->get_checked_timings() );

		$this->enable_reminders( array( 'day_before', 'hour_before' ) );
		$this->assertSame(
			array(
				'day_before'  => DAY_IN_SECONDS,
				'hour_before' => HOUR_IN_SECONDS,
			),
			$instance->get_checked_timings()
		);

		$this->enable_reminders( array() );
		$this->assertSame( array(), $instance->get_checked_timings() );
	}

	/**
	 * Check which checked timings are due for a start.
	 *
	 * Invoked directly because xdebug does not trace same-class helpers
	 * called from a closure.
	 *
	 * @covers ::get_due_timings
	 *
	 * @return void
	 */
	public function test_get_due_timings(): void {
		$this->enable_reminders( array( 'day_before', 'hour_before' ) );

		$due   = static fn( string $start, array $sent = array() ): array => Utility::invoke_hidden_method(
			Reminder::get_instance(),
			'get_due_timings',
			array( $start, $sent )
		);
		$soon  = gmdate( 'Y-m-d H:i:s', time() + 30 * MINUTE_IN_SECONDS );
		$later = gmdate( 'Y-m-d H:i:s', time() + 5 * HOUR_IN_SECONDS );

		$this->assertSame( array(), $due( gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) ), 'Started.' );
		$this->assertSame( array(), $due( gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ) ), 'Not due yet.' );
		$this->assertSame( array( 'day_before' ), $due( $later ) );
		$this->assertSame( array( 'day_before', 'hour_before' ), $due( $soon ) );
		$this->assertSame( array( 'hour_before' ), $due( $soon, array( 'day_before' => $soon ) ), 'Sent is skipped.' );
	}

	/**
	 * Check that the sweep does nothing while no timing is checked.
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_does_nothing_without_timings(): void {
		$this->enable_reminders( array() );

		$post_id = $this->create_event();

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		Reminder::get_instance()->sweep();
		$this->run_mail_jobs();

		$this->assertSame( array(), $this->sent );
	}

	/**
	 * Check that an event reminded a day before is reminded again an hour before.
	 *
	 * @covers ::queue_reminder
	 * @covers ::get_due_timings
	 *
	 * @return void
	 */
	public function test_reminds_a_day_and_an_hour_before(): void {
		$this->enable_reminders( array( 'day_before', 'hour_before' ) );

		$post_id = $this->create_event( 'Meetup', 5 * HOUR_IN_SECONDS );
		$start   = (string) get_post_meta( $post_id, Scheduler::START_META_KEY, true );

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();

		$this->assertCount( 1, $this->sent, 'Five hours out, only the day before is due.' );
		$this->assertSame( array( 'day_before' => $start ), get_post_meta( $post_id, Reminder::SENT_META_KEY, true ) );

		// Forty minutes out, as an hour later in the event's countdown would be.
		$soon = $this->create_event( 'Meetup', 40 * MINUTE_IN_SECONDS );

		( new Rsvp( $soon ) )->save( 'soon@example.test', 'attending', 0, 0 );
		update_post_meta(
			$soon,
			Reminder::SENT_META_KEY,
			array( 'day_before' => (string) get_post_meta( $soon, Scheduler::START_META_KEY, true ) )
		);

		Reminder::get_instance()->queue_reminder( $soon );
		$this->run_mail_jobs();

		$this->assertSame( 'soon@example.test', $this->sent[1][0], 'The hour before goes out once it is due.' );
		$this->assertArrayHasKey( 'hour_before', get_post_meta( $soon, Reminder::SENT_META_KEY, true ) );
	}

	/**
	 * Check that an event inside both windows gets one email, not two.
	 *
	 * @covers ::queue_reminder
	 * @covers ::get_due_timings
	 *
	 * @return void
	 */
	public function test_one_email_when_several_timings_are_due(): void {
		$this->enable_reminders( array( 'day_before', 'hour_before' ) );

		$post_id = $this->create_event( 'Meetup', 30 * MINUTE_IN_SECONDS );
		$start   = (string) get_post_meta( $post_id, Scheduler::START_META_KEY, true );

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		Reminder::get_instance()->queue_reminder( $post_id );
		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();

		$this->assertCount( 1, $this->sent );
		$this->assertSame(
			array(
				'day_before'  => $start,
				'hour_before' => $start,
			),
			get_post_meta( $post_id, Reminder::SENT_META_KEY, true )
		);
	}

	/**
	 * Check that an hour-before reminder only picks up events starting within the hour.
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_follows_the_timing(): void {
		$this->enable_reminders( array( 'hour_before' ) );

		$soon  = $this->create_event( 'Soon', 30 * MINUTE_IN_SECONDS );
		$later = $this->create_event( 'Later', 5 * HOUR_IN_SECONDS );

		foreach ( array( $soon, $later ) as $post_id ) {
			( new Rsvp( $post_id ) )->save( sprintf( 'guest-%d@example.test', $post_id ), 'attending', 0, 0 );
		}

		Reminder::get_instance()->sweep();
		$this->run_mail_jobs();

		$this->assertSame( array( sprintf( 'guest-%d@example.test', $soon ) ), array_column( $this->sent, 0 ) );
	}

	/**
	 * Check that the hourly sweep is scheduled only while reminders are on.
	 *
	 * @covers ::is_enabled
	 * @covers ::schedule_sweep
	 *
	 * @return void
	 */
	public function test_schedule_sweep(): void {
		$instance = Reminder::get_instance();

		$this->assertTrue( $instance->is_enabled(), 'Reminders are on by default.' );

		$this->disable_reminders();
		$instance->schedule_sweep();
		$this->assertFalse( wp_next_scheduled( Reminder::SWEEP_ACTION ), 'Not scheduled while off.' );

		$this->enable_reminders();
		$instance->schedule_sweep();

		$timestamp = wp_next_scheduled( Reminder::SWEEP_ACTION );

		$this->assertNotFalse( $timestamp );
		$this->assertSame( 'hourly', wp_get_schedule( Reminder::SWEEP_ACTION ) );

		$instance->schedule_sweep();
		$this->assertSame( $timestamp, wp_next_scheduled( Reminder::SWEEP_ACTION ), 'The schedule is kept.' );

		Settings::get_instance()->set( 'enable_event_reminders', false );
		$instance->schedule_sweep();
		$this->assertFalse( wp_next_scheduled( Reminder::SWEEP_ACTION ), 'Turning reminders off removes it.' );
	}

	/**
	 * Check that the sweep reminds only published events starting within the window, once.
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_reminds_events_in_the_window(): void {
		$this->enable_reminders();

		$soon    = $this->create_event( 'Soon', 5 * HOUR_IN_SECONDS );
		$later   = $this->create_event( 'Later', 3 * DAY_IN_SECONDS );
		$started = $this->create_event( 'Started', -HOUR_IN_SECONDS );
		$draft   = $this->create_event( 'Draft', 5 * HOUR_IN_SECONDS, 'draft' );

		foreach ( array( $soon, $later, $started, $draft ) as $post_id ) {
			( new Rsvp( $post_id ) )->save( sprintf( 'guest-%d@example.test', $post_id ), 'attending', 0, 0 );
		}

		Reminder::get_instance()->sweep();
		$this->run_mail_jobs();

		$this->assertSame( array( sprintf( 'guest-%d@example.test', $soon ) ), array_column( $this->sent, 0 ) );

		Reminder::get_instance()->sweep();
		$this->run_mail_jobs();

		$this->assertCount( 1, $this->sent, 'The next sweep sends nothing for the same start.' );
	}

	/**
	 * Check that the sweep does nothing while reminders are off.
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_does_nothing_while_off(): void {
		$this->disable_reminders();

		$post_id = $this->create_event( 'Soon', 5 * HOUR_IN_SECONDS );

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		Reminder::get_instance()->sweep();
		$this->run_mail_jobs();

		$this->assertSame( array(), $this->sent );
	}

	/**
	 * Check that the sweep reads past a full page of events.
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_reads_every_page(): void {
		$this->enable_reminders();

		$post_ids = array();

		for ( $i = 1; $i <= Reminder::BATCH_SIZE + 1; $i++ ) {
			$post_ids[] = $this->create_event( 'Event', HOUR_IN_SECONDS + $i * MINUTE_IN_SECONDS );
		}

		// Nothing to send, so the jobs are not run; the sent marker shows each one was queued.
		Reminder::get_instance()->sweep();

		$this->assertNotSame( '', get_post_meta( end( $post_ids ), Reminder::SENT_META_KEY, true ) );
	}

	/**
	 * Check that a reminder reaches attendees, in the format they will see.
	 *
	 * @covers ::queue_reminder
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
		$this->assertStringContainsString(
			'This is a reminder that you are attending Board game night',
			$this->sent[0][2]
		);
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
		$this->assertSame( array( 'day_before' => $start ), get_post_meta( $post_id, Reminder::SENT_META_KEY, true ) );

		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();
		$this->assertCount( 1, $this->sent, 'The same start is not reminded twice.' );

		update_post_meta( $post_id, Scheduler::START_META_KEY, gmdate( 'Y-m-d H:i:s', time() + 6 * HOUR_IN_SECONDS ) );

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
		$started = $this->create_event( 'Started', -HOUR_IN_SECONDS );

		( new Rsvp( $started ) )->save( 'late@example.test', 'attending', 0, 0 );
		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		$this->disable_reminders();
		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();
		$this->assertSame( array(), $this->sent, 'Reminders are off.' );

		$this->enable_reminders();

		Reminder::get_instance()->queue_reminder( $this->factory->post->create() );
		$this->run_mail_jobs();
		$this->assertSame( array(), $this->sent, 'A post without RSVPs gets nothing.' );

		Reminder::get_instance()->queue_reminder( $started );
		$this->run_mail_jobs();
		$this->assertSame( array(), $this->sent, 'An event that has started gets nothing.' );

		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );
		Reminder::get_instance()->queue_reminder( $post_id );
		$this->run_mail_jobs();
		$this->assertSame( array(), $this->sent, 'Nothing goes out while RSVP is off.' );
	}

	/**
	 * Check that a failed enqueue leaves the event unmarked, so a later sweep can retry.
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
	 * Check that a reminder queued before the start moved is not sent.
	 *
	 * @covers ::queue_reminder
	 *
	 * @return void
	 */
	public function test_queued_reminder_skips_a_moved_start(): void {
		$this->enable_reminders();

		$post_id = $this->create_event();

		( new Rsvp( $post_id ) )->save( 'guest@example.test', 'attending', 0, 0 );

		Reminder::get_instance()->queue_reminder( $post_id );
		update_post_meta( $post_id, Scheduler::START_META_KEY, gmdate( 'Y-m-d H:i:s', time() + WEEK_IN_SECONDS ) );
		$this->run_mail_jobs();

		$this->assertSame( array(), $this->sent );
	}
}
