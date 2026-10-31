<?php
/**
 * Event reminder emails.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Reminder.
 *
 * Emails attendees a reminder before an event starts. Scheduler runs
 * `gatherpress_event_starting` at the lead time this class sets, and the
 * reminder is queued on Mailer from there.
 *
 * @since TBD
 *
 * @phpstan-import-type Recipient from Mailer
 * @phpstan-import-type Payload from Mailer
 */
final class Reminder {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The Mailer type for reminder emails.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const MAIL_TYPE = 'event_reminder';

	/**
	 * Post meta key holding the start time the last reminder was queued for.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const SENT_META_KEY = 'gatherpress_event_reminder_sent';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
		$this->register_mail_type();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'gatherpress_event_starting_lead_time', array( $this, 'get_lead_time' ) );
		add_action( Scheduler::STARTING_ACTION, array( $this, 'queue_reminder' ) );
		add_action(
			sprintf( 'add_option_%s', Settings::OPTION_NAME ),
			array( $this, 'maybe_reschedule_on_add' ),
			10,
			2
		);
		add_action(
			sprintf( 'update_option_%s', Settings::OPTION_NAME ),
			array( $this, 'maybe_reschedule' ),
			10,
			2
		);
	}

	/**
	 * Tell Mailer who reminders go to and what they say.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	private function register_mail_type(): void {
		Mailer::get_instance()->register(
			self::MAIL_TYPE,
			array(
				'context'    => Mailer::CONTEXT_REMINDER,
				'recipients' => $this->get_recipients( ... ),
				'compose'    => $this->compose( ... ),
			)
		);
	}

	/**
	 * Whether reminder emails are turned on for the site.
	 *
	 * @since TBD
	 *
	 * @return bool True when reminders are on.
	 */
	public function is_enabled(): bool {
		return (bool) Settings::get_instance()->get( 'enable_event_reminders' );
	}

	/**
	 * Move the starting action to the reminder time while reminders are on.
	 *
	 * @since TBD
	 *
	 * @param int $lead_time Seconds before the start.
	 *
	 * @return int Seconds before the start.
	 */
	public function get_lead_time( int $lead_time ): int {
		if ( ! $this->is_enabled() ) {
			return $lead_time;
		}

		return max( 1, (int) Settings::get_instance()->get( 'event_reminder_hours' ) ) * HOUR_IN_SECONDS;
	}

	/**
	 * Reschedule upcoming events when the reminder settings change.
	 *
	 * @since TBD
	 *
	 * @param mixed $old_value The previous settings.
	 * @param mixed $new_value The updated settings.
	 *
	 * @return void
	 */
	public function maybe_reschedule( mixed $old_value, mixed $new_value ): void {
		$old_value = (array) $old_value;
		$new_value = (array) $new_value;

		foreach ( array( 'enable_event_reminders', 'event_reminder_hours' ) as $key ) {
			if ( ( $old_value[ $key ] ?? null ) !== ( $new_value[ $key ] ?? null ) ) {
				Scheduler::get_instance()->reschedule_upcoming_starts();

				return;
			}
		}
	}

	/**
	 * Reschedule upcoming events when the first settings save turns reminders on.
	 *
	 * A site that never saved its settings adds the option rather than
	 * updating it, which fires a different hook.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param string $option The option name, required by the add_option hook signature.
	 * @param mixed  $value  The new settings.
	 *
	 * @return void
	 */
	public function maybe_reschedule_on_add( string $option, mixed $value ): void {
		$this->maybe_reschedule( array(), $value );
	}

	/**
	 * Queue the reminder for an event about to start.
	 *
	 * The start time it was queued for is stored, so a second cron run for
	 * the same start sends nothing, while a moved start can send again. A
	 * cron run that only happens after the start, on a quiet site, sends
	 * nothing either.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Event post ID.
	 *
	 * @return void
	 */
	public function queue_reminder( int $post_id ): void {
		$start = (string) get_post_meta( $post_id, Scheduler::START_META_KEY, true );

		if (
			! $this->is_enabled()
			|| ! ( new Rsvp( $post_id ) )->is_enabled()
			|| (int) strtotime( $start ) <= time()
			|| get_post_meta( $post_id, self::SENT_META_KEY, true ) === $start
		) {
			return;
		}

		if ( Mailer::get_instance()->queue( self::MAIL_TYPE, array( 'post_id' => $post_id ) ) ) {
			update_post_meta( $post_id, self::SENT_META_KEY, $start );
		}
	}

	/**
	 * Get the attendees a queued reminder goes to.
	 *
	 * Looked up when the job runs, so an RSVP changed after the reminder was
	 * queued is respected.
	 *
	 * @since TBD
	 *
	 * @param array $payload The queued email: its `post_id`.
	 * @phpstan-param Payload $payload
	 *
	 * @return array<int, Recipient> Recipient rows for the attending RSVPs.
	 */
	private function get_recipients( array $payload ): array {
		return Email::get_instance()->get_recipients(
			array(
				'all'           => false,
				'attending'     => true,
				'waiting_list'  => false,
				'not_attending' => false,
			),
			(int) ( $payload['post_id'] ?? 0 )
		);
	}

	/**
	 * Build a reminder for one attendee.
	 *
	 * Mailer calls this while the attendee's language and timezone are
	 * active, so the subject and the event time are shown for them.
	 *
	 * @since TBD
	 *
	 * @param array $recipient The attendee.
	 * @param array $payload   The queued email: its `post_id`.
	 * @phpstan-param Recipient $recipient
	 * @phpstan-param Payload $payload
	 *
	 * @return array{subject: string, body: string, headers: array<int, string>} The email.
	 */
	private function compose( array $recipient, array $payload ): array {
		$post_id = (int) ( $payload['post_id'] ?? 0 );

		/**
		 * Filters the subject of an event reminder email.
		 *
		 * @since TBD
		 *
		 * @param string $subject   Email subject line.
		 * @param int    $post_id   Event post ID.
		 * @param array  $recipient The attendee the reminder goes to.
		 *
		 * @return string Email subject line.
		 */
		$subject = (string) apply_filters(
			'gatherpress_event_reminder_subject',
			sprintf(
				/* translators: %s: Event title. */
				_x( 'Reminder: %s', 'Event reminder email subject', 'gatherpress' ),
				get_the_title( $post_id )
			),
			$post_id,
			$recipient
		);

		/**
		 * Filters the body of an event reminder email.
		 *
		 * @since TBD
		 *
		 * @param string $body      Rendered HTML email body.
		 * @param int    $post_id   Event post ID.
		 * @param array  $recipient The attendee the reminder goes to.
		 *
		 * @return string Rendered HTML email body.
		 */
		$body = (string) apply_filters(
			'gatherpress_event_reminder_body',
			Utility::render_template(
				sprintf( '%s/includes/templates/admin/emails/event-reminder.php', GATHERPRESS_CORE_PATH ),
				array( 'event_id' => $post_id )
			),
			$post_id,
			$recipient
		);

		return array(
			'subject' => $subject,
			'body'    => $body,
			'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
		);
	}
}
