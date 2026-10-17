<?php
/**
 * Event update and waiting list promotion emails.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Comment;

/**
 * Class Email.
 *
 * Decides who event emails go to and what they say: the update an editor
 * sends from an event, and the confirmation an attendee gets when a spot
 * opens up and they move off the waiting list. Mailer queues and sends them.
 *
 * @since TBD
 *
 * @phpstan-type SendOptions array{all: bool, attending: bool, waiting_list: bool, not_attending: bool}
 * @phpstan-import-type Recipient from Mailer
 * @phpstan-import-type Payload from Mailer
 */
final class Email {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The Mailer type for event update emails.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const EVENT_TYPE = 'event';

	/**
	 * The Mailer type for waiting list promotion emails.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const PROMOTION_TYPE = 'waiting_list_promotion';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
		$this->register_mail_types();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_send_emails', array( $this, 'handle_email_send_action' ), 10, 4 );
		add_action( 'gatherpress_rsvp_waiting_list_promoted', array( $this, 'queue_promotion_email' ), 10, 2 );
	}

	/**
	 * Tell Mailer who event emails go to and what they say.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	private function register_mail_types(): void {
		$mailer = Mailer::get_instance();

		$mailer->register(
			self::EVENT_TYPE,
			array(
				'context'    => Mailer::CONTEXT_EVENT,
				'users'      => $this->get_event_email_users( ... ),
				'recipients' => $this->get_event_email_rsvps( ... ),
				'compose'    => $this->compose_event_email( ... ),
			)
		);

		// A promotion is about the attendee's own RSVP, so it skips the opt-in.
		$mailer->register(
			self::PROMOTION_TYPE,
			array(
				'context'    => Mailer::CONTEXT_RSVP,
				'recipients' => $this->get_promoted_attendee( ... ),
				'compose'    => $this->compose_promotion_email( ... ),
			)
		);
	}

	/**
	 * Queue an event update email from a job on the old hook.
	 *
	 * Event update jobs went out on `gatherpress_send_emails` before Mailer
	 * owned the job, so one queued before an update still arrives here and is
	 * queued again with Mailer.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id Event post ID.
	 * @param array  $send    Who to send the email to.
	 * @param string $message Optional message to include in the email.
	 * @param string $subject Optional subject line.
	 * @phpstan-param SendOptions $send
	 *
	 * @return void
	 */
	public function handle_email_send_action( int $post_id, array $send, string $message, string $subject = '' ): void {
		$this->send_emails( $post_id, $send, $message, $subject );
	}

	/**
	 * Queue an event update email for the selected recipients.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id Event post ID.
	 * @param array  $send    Who to send the email to.
	 * @param string $message Optional message to include in the email.
	 * @param string $subject Optional subject line. Defaults to the `📅 {title}` template when empty.
	 * @phpstan-param SendOptions $send
	 *
	 * @return bool True when the email is queued, false otherwise.
	 */
	public function send_emails( int $post_id, array $send, string $message, string $subject = '' ): bool {
		return post_type_supports( (string) get_post_type( $post_id ), Rsvp::SUPPORT )
			&& Mailer::get_instance()->queue(
				self::EVENT_TYPE,
				array(
					'post_id' => $post_id,
					'send'    => $send,
					'message' => $message,
					'subject' => $subject,
				)
			);
	}

	/**
	 * Queue the email for an attendee promoted off the waiting list.
	 *
	 * Runs from the request that freed the spot, whether an attendee
	 * cancelling or an editor raising the limit, so the email is queued
	 * rather than sent while that request waits.
	 *
	 * @since TBD
	 *
	 * @param int   $post_id Event post ID.
	 * @param State $state   The promoted RSVP.
	 *
	 * @return void
	 */
	public function queue_promotion_email( int $post_id, State $state ): void {
		Mailer::get_instance()->queue(
			self::PROMOTION_TYPE,
			array(
				'post_id'    => $post_id,
				'comment_id' => (int) $state->comment->comment_ID,
			)
		);
	}

	/**
	 * Get the users a queued event email goes to.
	 *
	 * @since TBD
	 *
	 * @param array $payload The queued email: its `send` options.
	 * @phpstan-param Payload $payload
	 *
	 * @return array<string, mixed>|null `get_users()` arguments for every user when "all" was picked, otherwise null.
	 */
	private function get_event_email_users( array $payload ): ?array {
		return empty( $payload['send']['all'] ) ? null : array();
	}

	/**
	 * Get the RSVPs a queued event email goes to.
	 *
	 * When every user is already getting the email, RSVPs from users are left
	 * out, so nobody gets it twice.
	 *
	 * @since TBD
	 *
	 * @param array $payload The queued email: its `post_id` and `send` options.
	 * @phpstan-param Payload $payload
	 *
	 * @return array<int, Recipient> Recipient rows from the event's RSVPs.
	 */
	private function get_event_email_rsvps( array $payload ): array {
		$send       = (array) ( $payload['send'] ?? array() );
		$recipients = $this->get_rsvp_recipients( $send, (int) ( $payload['post_id'] ?? 0 ) );

		if ( ! empty( $send['all'] ) ) {
			$recipients = array_values(
				array_filter( $recipients, static fn( array $recipient ): bool => ! $recipient['is_user'] )
			);
		}

		return $recipients;
	}

	/**
	 * Get the attendee a queued promotion email goes to.
	 *
	 * Looked up when the job runs, and only while the RSVP is still attending,
	 * since the attendee can cancel between the promotion and the email.
	 *
	 * @since TBD
	 *
	 * @param array $payload The queued email: its `post_id` and the promoted RSVP's `comment_id`.
	 * @phpstan-param Payload $payload
	 *
	 * @return array<int, Recipient> The attendee, or nobody.
	 */
	private function get_promoted_attendee( array $payload ): array {
		$comment_id = (int) ( $payload['comment_id'] ?? 0 );

		return array_values(
			array_filter(
				$this->get_rsvp_recipients( array( 'attending' => true ), (int) ( $payload['post_id'] ?? 0 ) ),
				static fn( array $recipient ): bool => $comment_id === $recipient['comment_id']
			)
		);
	}

	/**
	 * Build a queued event update email for one recipient.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param array $recipient The recipient, required by Mailer's compose signature.
	 * @param array $payload   The queued email: its `post_id`, `message` and `subject`.
	 * @phpstan-param Recipient $recipient
	 * @phpstan-param Payload $payload
	 *
	 * @return array{subject: string, body: string, headers: array<int, string>} The email.
	 */
	private function compose_event_email( array $recipient, array $payload ): array {
		return $this->build_event_email(
			(int) ( $payload['post_id'] ?? 0 ),
			(string) ( $payload['message'] ?? '' ),
			(string) ( $payload['subject'] ?? '' )
		);
	}

	/**
	 * Build a queued promotion email for the promoted attendee.
	 *
	 * Mailer calls this while the attendee's language is active, so the
	 * subject and message are translated for them.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param array $recipient The attendee, required by Mailer's compose signature.
	 * @param array $payload   The queued email: its `post_id`.
	 * @phpstan-param Recipient $recipient
	 * @phpstan-param Payload $payload
	 *
	 * @return array{subject: string, body: string, headers: array<int, string>} The email.
	 */
	private function compose_promotion_email( array $recipient, array $payload ): array {
		$post_id = (int) ( $payload['post_id'] ?? 0 );
		$title   = get_the_title( $post_id );

		// The attendee already holds a spot, so the RSVP button would read as
		// asking them to respond again.
		return $this->build_event_email(
			$post_id,
			sprintf(
				/* translators: %s: Event title. */
				__( 'A spot has opened up and your RSVP for %s is now confirmed.', 'gatherpress' ),
				$title
			),
			sprintf(
				/* translators: %s: Event title. */
				_x( 'Your RSVP for %s is confirmed', 'Waiting list promotion email subject', 'gatherpress' ),
				$title
			),
			false
		);
	}

	/**
	 * Build the subject and body for an event email.
	 *
	 * Runs while the recipient's context is active so the default subject and
	 * the template are translated in the recipient's locale and timezone.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id          Event post ID.
	 * @param string $message          Optional message for the email body.
	 * @param string $subject          Optional subject line. Empty falls back to the default template.
	 * @param bool   $show_rsvp_button Whether the body offers the RSVP button. Default true.
	 *
	 * @return array{subject: string, body: string, headers: array<int, string>} Composed email.
	 */
	private function build_event_email(
		int $post_id,
		string $message,
		string $subject,
		bool $show_rsvp_button = true
	): array {
		$template = sprintf( '%s/includes/templates/admin/emails/event-email.php', GATHERPRESS_CORE_PATH );

		if ( '' === $subject ) {
			$subject = sprintf(
				// translators: %s: event title.
				_x( '📅 %s', 'Email notification subject with event title', 'gatherpress' ),
				get_the_title( $post_id )
			);
		}

		/**
		 * Filters the subject of an event email.
		 *
		 * Covers the update an editor sends from an event and the confirmation
		 * an attendee gets when they move off the waiting list. Lets a site
		 * brand or reword the subject, for example with a site prefix.
		 *
		 * @since 0.36.0
		 *
		 * @param string $subject Email subject line.
		 * @param int    $post_id Event post ID.
		 *
		 * @return string Email subject line.
		 */
		$subject = apply_filters( 'gatherpress_email_subject', $subject, $post_id );

		return array(
			'subject' => $subject,
			'body'    => Utility::render_template(
				$template,
				array(
					'event_id'         => $post_id,
					'message'          => $message,
					'show_rsvp_button' => $show_rsvp_button,
				),
			),
			'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
		);
	}

	/**
	 * Get the list of recipients for an event update email.
	 *
	 * Every user when "all" is picked, plus the RSVPs with the picked
	 * statuses, from users and people without an account alike.
	 *
	 * @since TBD
	 *
	 * @param array $send    Who to send the email to.
	 * @param int   $post_id The event post ID.
	 * @phpstan-param SendOptions $send
	 *
	 * @return array<int, Recipient> Recipient rows.
	 */
	public function get_recipients( array $send, int $post_id ): array {
		$recipients = array();

		if ( ! empty( $send['all'] ) ) {
			$recipients = array_map(
				static fn( $user ): array => array(
					'is_user'    => true,
					'user_id'    => $user->ID,
					'comment_id' => 0,
					'email'      => $user->user_email,
					'name'       => $user->display_name,
				),
				get_users()
			);
		}

		return array_merge( $recipients, $this->get_rsvp_recipients( $send, $post_id ) );
	}

	/**
	 * Get recipient rows for the RSVPs with the requested statuses.
	 *
	 * @since TBD
	 *
	 * @param array $send    Which RSVP statuses to send to.
	 * @param int   $post_id The event post ID.
	 * @phpstan-param array<string, mixed> $send
	 *
	 * @return array<int, Recipient> Recipient rows, one per RSVP with a valid email.
	 */
	private function get_rsvp_recipients( array $send, int $post_id ): array {
		$recipients    = array();
		$all_responses = ( new Rsvp( $post_id ) )->responses();

		// Collect comment IDs for the requested RSVP statuses — `array_column`
		// flattens each status's records to its commentId list in one pass,
		// avoiding the inner foreach.
		$comment_ids = array();
		foreach ( array( 'attending', 'waiting_list', 'not_attending' ) as $status ) {
			if ( ! empty( $send[ $status ] ) ) {
				$comment_ids = array_merge(
					$comment_ids,
					array_column( $all_responses[ $status ]['records'], 'comment_id' )
				);
			}
		}

		if ( empty( $comment_ids ) ) {
			return $recipients;
		}

		// Get full comment data for the RSVPs and build recipient rows.
		$comments = Rsvp_Query::get_instance()->get_rsvps(
			array(
				'post_id'     => $post_id,
				'status'      => 'approve',
				'comment__in' => $comment_ids,
			)
		);

		foreach ( $comments as $comment ) {
			// get_rsvps() is typed loosely enough to return counts, so only comment rows
			// are turned into recipients.
			if ( ! $comment instanceof WP_Comment ) {
				continue;
			}

			$recipient = $this->build_comment_recipient( $comment );

			if ( null !== $recipient ) {
				$recipients[] = $recipient;
			}
		}

		return $recipients;
	}

	/**
	 * Build a recipient row from an approved RSVP comment.
	 *
	 * Uses the account's email and display name when the RSVP belongs to a
	 * user, and returns null when no valid email is on file so the caller can
	 * skip the row.
	 *
	 * @since TBD
	 *
	 * @param WP_Comment $comment RSVP comment row from `Rsvp_Query::get_rsvps()`.
	 *
	 * @return Recipient|null Recipient row, or null when no valid email is on file.
	 */
	private function build_comment_recipient( WP_Comment $comment ): ?array {
		$user_id = intval( $comment->user_id );
		$email   = $comment->comment_author_email;
		$name    = $comment->comment_author;

		if ( $user_id ) {
			$user = get_userdata( $user_id );

			if ( $user ) {
				$email = $user->user_email;
				$name  = $user->display_name;
			}
		}

		if ( ! is_email( $email ) ) {
			return null;
		}

		return array(
			'is_user'    => (bool) $user_id,
			'user_id'    => $user_id,
			'comment_id' => (int) $comment->comment_ID,
			'email'      => $email,
			'name'       => $name,
		);
	}
}
