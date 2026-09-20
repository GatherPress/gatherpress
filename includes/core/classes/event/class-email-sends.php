<?php
/**
 * Sends event email notifications.
 *
 * @package GatherPress\Core\Event
 * @since 0.37.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\User;
use GatherPress\Core\Utility;
use WP_Comment;
use WP_User;

/**
 * Sends event update and RSVP notification emails.
 *
 * @since 0.37.0
 *
 * @phpstan-type SendOptions array{all: bool, attending: bool, waiting_list: bool, not_attending: bool}
 * @phpstan-type Recipient array{is_user: bool, user_id: int, comment_id: int, email: string, name: string}
 */
final class Email_Sends {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since 0.37.0
	 *
	 * @codeCoverageIgnore Constructor runs during plugin bootstrap before coverage starts.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up email hooks.
	 *
	 * @since 0.37.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_send_emails', array( $this, 'handle_email_send_action' ), 10, 4 );
		add_action(
			'gatherpress_rsvp_waiting_list_promoted',
			array( $this, 'send_waiting_list_promotion_email' ),
			10,
			2
		);
	}

	/**
	 * Handle the scheduled event email action.
	 *
	 * @since 0.37.0
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
	 * @since 0.37.0
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
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}

		$current_user = wp_get_current_user();

		foreach ( $this->get_recipients( $send, $post_id ) as $recipient ) {
			$this->send_event_email_to_recipient( $recipient, $post_id, $message, $current_user, $subject );
		}

		return true;
	}

	/**
	 * Send an email after a waiting-list member is promoted.
	 *
	 * @since 0.37.0
	 *
	 * @param int   $post_id Event post ID.
	 * @param State $state Promoted RSVP state.
	 *
	 * @return void
	 */
	public function send_waiting_list_promotion_email( int $post_id, State $state ): void {
		$recipient = $this->build_state_recipient( $state );

		if ( null === $recipient ) {
			return;
		}

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

		$this->send_event_email_to_recipient( $recipient, $post_id, $message, wp_get_current_user(), $subject );
	}

	/**
	 * Build a recipient from a promoted RSVP state.
	 *
	 * @since 0.37.0
	 *
	 * @param State $state Promoted RSVP state.
	 *
	 * @return Recipient|null Recipient data, or null when no email is available.
	 */
	protected function build_state_recipient( State $state ): ?array {
		$user_id = 0;
		$email   = '';
		$name    = $state->comment->comment_author;

		// Resolve by identity type rather than provider slug so providers
		// registered outside core reach their members too.
		if ( Identity_Type::WP_USER_ID === $state->data->identity->type ) {
			$user_id = (int) $state->data->identity->value;
			$user    = get_userdata( $user_id );

			if ( $user instanceof WP_User ) {
				$email = $user->user_email;
				$name  = $user->display_name;
			}
		} elseif ( Identity_Type::EMAIL === $state->data->identity->type ) {
			$email = (string) $state->data->identity->value;
		}

		if ( empty( $email ) ) {
			return null;
		}

		return array(
			'is_user'    => (bool) $user_id,
			'user_id'    => $user_id,
			'comment_id' => (int) $state->comment->comment_ID,
			'email'      => $email,
			'name'       => $name,
		);
	}

	/**
	 * Send one event email to a recipient.
	 *
	 * @since 0.37.0
	 *
	 * @param array   $recipient Recipient data.
	 * @param int     $post_id Event post ID.
	 * @param string  $message Message content.
	 * @param WP_User $current_user User to restore after rendering.
	 * @param string  $subject Subject line.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return void
	 */
	public function send_event_email_to_recipient(
		array $recipient,
		int $post_id,
		string $message,
		WP_User $current_user,
		string $subject = ''
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
		 * @since 0.37.0
		 *
		 * @param string $subject Email subject line.
		 * @param int    $post_id Event post ID.
		 */
		$subject = apply_filters( 'gatherpress_email_subject', $subject, $post_id );
		$body    = Utility::render_template(
			sprintf( '%s/includes/templates/admin/emails/event-email.php', GATHERPRESS_CORE_PATH ),
			array(
				'event_id' => $post_id,
				'message'  => $message,
			)
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
	 * @since 0.37.0
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
	 * @since 0.37.0
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
