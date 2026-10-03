<?php
/**
 * Sends event email notifications.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\User;
use GatherPress\Core\Utility;
use WP_Comment;
use WP_User;

/**
 * Sends event update and RSVP notification emails.
 *
 * @since TBD
 *
 * @phpstan-type SendOptions array{all: bool, attending: bool, waiting_list: bool, not_attending: bool}
 * @phpstan-type Recipient array{is_user: bool, user_id: int, comment_id: int, email: string, name: string}
 */
final class Email_Sends {

	/**
	 * Cron hook that delivers the waiting list promotion email.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const PROMOTION_CRON_HOOK = 'gatherpress_send_waiting_list_promotion_email';

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 *
	 * @codeCoverageIgnore Constructor runs during plugin bootstrap before coverage starts.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up email hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_send_emails', array( $this, 'handle_email_send_action' ), 10, 4 );
		add_action(
			'gatherpress_rsvp_waiting_list_promoted',
			array( $this, 'schedule_waiting_list_promotion_email' ),
			10,
			2
		);
		add_action( self::PROMOTION_CRON_HOOK, array( $this, 'send_waiting_list_promotion_email' ), 10, 2 );
	}

	/**
	 * Handle the scheduled event email action.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $send Members to send the email to.
	 * @param string $message Optional message.
	 * @param string $subject Optional subject.
	 * @phpstan-param SendOptions $send
	 *
	 * @return void
	 */
	public function handle_email_send_action( int $post_id, array $send, string $message, string $subject = '' ): void {
		$this->send_emails( $post_id, $send, $message, $subject );
	}

	/**
	 * Send emails to selected members.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $send Members to send the email to.
	 * @param string $message Optional message.
	 * @param string $subject Optional subject.
	 * @phpstan-param SendOptions $send
	 *
	 * @return bool True when the event is valid and dispatch was attempted.
	 */
	public function send_emails( int $post_id, array $send, string $message, string $subject = '' ): bool {
		if ( ! post_type_supports( (string) get_post_type( $post_id ), Rsvp::SUPPORT ) ) {
			return false;
		}

		// Keep the currently logged-in user so per-recipient locale / user
		// switches inside the loop can restore back to it.
		$current_user = wp_get_current_user();

		foreach ( $this->get_recipients( $send, $post_id ) as $recipient ) {
			$this->send_event_email_to_recipient( $recipient, $post_id, $message, $current_user, $subject );
		}

		return true;
	}

	/**
	 * Queue the promotion email for a member promoted off the waiting list.
	 *
	 * Runs on cron like the bulk event mail, so the request that frees the
	 * spot, whether a guest cancelling or an editor raising the attendance
	 * limit, does not wait on the mail server.
	 *
	 * @since TBD
	 *
	 * @param int   $post_id Event post ID.
	 * @param State $state Promoted RSVP state.
	 *
	 * @return void
	 */
	public function schedule_waiting_list_promotion_email( int $post_id, State $state ): void {
		wp_schedule_single_event(
			time(),
			self::PROMOTION_CRON_HOOK,
			array( $post_id, (int) $state->comment->comment_ID )
		);
	}

	/**
	 * Send the waiting list promotion email to a promoted member.
	 *
	 * The recipient is resolved from the RSVP comment rather than passed
	 * through the schedule, which keeps every RSVP identity type working
	 * without serializing a state object into a cron argument.
	 *
	 * The RSVP status is re-checked here because the job runs after the
	 * promotion, so the member can cancel in between.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Event post ID.
	 * @param int $comment_id RSVP comment ID of the promoted member.
	 *
	 * @return void
	 */
	public function send_waiting_list_promotion_email( int $post_id, int $comment_id ): void {
		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof WP_Comment ) {
			return;
		}

		// The member can cancel between the promotion and this job.
		$status = wp_get_object_terms( $comment_id, Status::TAXONOMY, array( 'fields' => 'slugs' ) );

		if ( ! is_array( $status ) || ! in_array( Status::ATTENDING->value, $status, true ) ) {
			return;
		}

		$recipient = $this->build_comment_recipient( $comment );

		if ( null === $recipient ) {
			return;
		}

		// Build the strings while the recipient's locale is active, otherwise
		// a member gets a translated body under a subject in the language of
		// whoever freed the spot.
		$switched_locale = $recipient['is_user'] && switch_to_user_locale( $recipient['user_id'] );

		$message = sprintf(
			/* translators: %s: event title. */
			__( 'A spot has opened up and your RSVP for %s is now confirmed.', 'gatherpress' ),
			get_the_title( $post_id )
		);
		$subject = sprintf(
			/* translators: %s: event title. */
			_x( 'Your RSVP for %s is confirmed', 'Waiting list promotion email subject', 'gatherpress' ),
			get_the_title( $post_id )
		);

		// Cleanup branch only fires when `switch_to_user_locale()` actually
		// switched, which requires a non-stub `WP_Locale_Switcher` and is not
		// reachable from the test runner.
		if ( $switched_locale ) { // @codeCoverageIgnore
			restore_previous_locale(); // @codeCoverageIgnore
		}

		// The member already holds a spot, so the RSVP button would read as a
		// request to respond again.
		$this->send_event_email_to_recipient(
			$recipient,
			$post_id,
			$message,
			wp_get_current_user(),
			$subject,
			false
		);
	}

	/**
	 * Send one event email to a recipient.
	 *
	 * @since TBD
	 *
	 * @param array   $recipient Recipient data.
	 * @param int     $post_id Event post ID.
	 * @param string  $message Message content.
	 * @param WP_User $current_user User to restore after rendering.
	 * @param string  $subject Subject line.
	 * @param bool    $show_rsvp_button Whether to render the RSVP button.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return void
	 */
	public function send_event_email_to_recipient(
		array $recipient,
		int $post_id,
		string $message,
		WP_User $current_user,
		string $subject = '',
		bool $show_rsvp_button = true
	): void {
		if ( $recipient['is_user'] ) {
			if ( ! User::get_instance()->has_event_updates_opt_in( $recipient['user_id'] ) ) {
				return;
			}
		} elseif (
			'0' === get_comment_meta(
				$recipient['comment_id'],
				'gatherpress_event_updates_opt_in',
				true
			)
		) {
			return;
		}

		if ( ! $recipient['email'] ) {
			return;
		}

		$switched_locale = false;

		if ( $recipient['is_user'] ) {
			$switched_locale = switch_to_user_locale( $recipient['user_id'] );
			wp_set_current_user( $recipient['user_id'] );
		}

		if ( '' === $subject ) {
			$subject = sprintf(
				/* translators: %s: event title. */
				_x( '📅 %s', 'Email notification subject with event title', 'gatherpress' ),
				get_the_title( $post_id )
			);
		}

		/**
		 * Filters an event email subject.
		 *
		 * @since TBD
		 *
		 * @param string $subject Email subject line.
		 * @param int    $post_id Event post ID.
		 *
		 * @return string Filtered email subject line.
		 */
		$subject = apply_filters( 'gatherpress_email_subject', $subject, $post_id );
		$body    = Utility::render_template(
			sprintf( '%s/includes/templates/admin/emails/event-email.php', GATHERPRESS_CORE_PATH ),
			array(
				'event_id'         => $post_id,
				'message'          => $message,
				'show_rsvp_button' => $show_rsvp_button,
			),
		);
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$subject = stripslashes_deep( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) );

		wp_set_current_user( $current_user->ID );
		wp_mail( $recipient['email'], $subject, $body, $headers );

		if ( $switched_locale ) { // @codeCoverageIgnore
			restore_previous_locale(); // @codeCoverageIgnore
		}
	}

	/**
	 * Get recipients for an event email.
	 *
	 * @since TBD
	 *
	 * @param array $send Recipient groups.
	 * @param int   $post_id Event post ID.
	 * @phpstan-param SendOptions $send
	 *
	 * @return array<int, Recipient> Recipient data.
	 */
	public function get_recipients( array $send, int $post_id ): array {
		$recipients    = array();
		$all_responses = ( new Rsvp( $post_id ) )->responses();

		if ( ! empty( $send['all'] ) ) {
			$recipients = array_map(
				static function ( $user ): array {
					return array(
						'is_user'    => true,
						'user_id'    => $user->ID,
						'comment_id' => 0,
						'email'      => $user->user_email,
						'name'       => $user->display_name,
					);
				},
				get_users()
			);
		}

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

		$comments = Rsvp_Query::get_instance()->get_rsvps(
			array(
				'post_id'     => $post_id,
				'status'      => 'approve',
				'comment__in' => $comment_ids,
			)
		);

		foreach ( $comments as $comment ) {
				// WordPress returns WP_Comment objects here.
				// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
			// @codeCoverageIgnoreStart
			if ( ! $comment instanceof WP_Comment ) {
				continue;
			}
				// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
			// @codeCoverageIgnoreEnd

			$recipient = $this->build_comment_recipient( $comment );

			if ( null !== $recipient ) {
				$recipients[] = $recipient;
			}
		}

		return $recipients;
	}

	/**
	 * Build a recipient from an RSVP comment.
	 *
	 * @since TBD
	 *
	 * @param WP_Comment $comment RSVP comment.
	 *
	 * @return Recipient|null Recipient data, or null without an email.
	 */
	public function build_comment_recipient( $comment ): ?array {
		$user_id = (int) $comment->user_id;
		$email   = $comment->comment_author_email;
		$name    = $comment->comment_author;

		if ( $user_id ) {
			$user = get_userdata( $user_id );

			if ( $user instanceof WP_User ) {
				$email = $user->user_email;
				$name  = $user->display_name;
			}
		}

		if ( empty( $email ) ) {
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
