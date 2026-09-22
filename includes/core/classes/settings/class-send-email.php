<?php
/**
 * Send Email settings page for GatherPress.
 *
 * This class handles the "Send Email" settings page in GatherPress, which lets
 * an administrator send a message to every member of the site who has opted in
 * to GatherPress emails, without going through a single event.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Mailer;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Send_Email.
 *
 * Handles the "Send Email" settings page for GatherPress.
 *
 * @since TBD
 *
 * @phpstan-import-type Recipient from Mailer
 * @phpstan-type RecipientBatch array{recipients: array<int, Recipient>, complete: bool, fetched: int}
 */
final class Send_Email extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		parent::setup_hooks();

		add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
		add_action( 'wp_ajax_gatherpress_send_site_message', array( $this, 'ajax_send' ) );
		add_action( 'gatherpress_site_message_send', array( $this, 'process_message' ), 10, 3 );
	}

	/**
	 * Get the slug for the send email settings page.
	 *
	 * @since TBD
	 *
	 * @return string The slug for the send email settings page.
	 */
	protected function get_slug(): string {
		return 'send_email_settings';
	}

	/**
	 * Get the name for the send email settings page.
	 *
	 * @since TBD
	 *
	 * @return string The localized name for the send email settings page.
	 */
	protected function get_name(): string {
		return __( 'Send Email', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the send email settings page.
	 *
	 * @since TBD
	 *
	 * @return int The priority for displaying the send email settings page.
	 */
	protected function get_priority(): int {
		return PHP_INT_MAX - 3;
	}

	/**
	 * Render the send email form instead of the default settings form.
	 *
	 * @since TBD
	 *
	 * @param string $page The current settings page slug.
	 *
	 * @return void
	 */
	public function settings_section( string $page ): void {
		if ( Utility::unprefix_key( $page ) !== $this->slug ) {
			return;
		}

		remove_action(
			'gatherpress_settings_section',
			array( Settings::get_instance(), 'render_settings_form' )
		);

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/send-email.php', GATHERPRESS_CORE_PATH ),
			array( 'recipient_count' => $this->count_recipients() ),
			true
		);
	}

	/**
	 * Get one page of members a site-wide message would go out to.
	 *
	 * @since TBD
	 *
	 * @param int $offset Number of users to skip.
	 *
	 * @return array{recipients: array<int, Recipient>, complete: bool, fetched: int} Recipient batch.
	 */
	protected function get_recipient_batch( int $offset = 0 ): array {
		$batch_size = $this->get_batch_size();
		$users      = get_users(
			array(
				'number' => $batch_size,
				'offset' => $offset,
				'fields' => array( 'ID', 'user_email', 'display_name' ),
			)
		);
		update_meta_cache(
			'user',
			array_map(
				static function ( $user ): int {
					return (int) $user->ID;
				},
				$users
			)
		);
		$mailer     = Mailer::get_instance();
		$recipients = array();

		foreach ( $users as $user ) {
			$recipient = array(
				'is_user'    => true,
				'user_id'    => (int) $user->ID,
				'comment_id' => 0,
				'email'      => $user->user_email,
				'name'       => $user->display_name,
			);

			if ( $mailer->is_eligible( $recipient ) ) {
				$recipients[] = $recipient;
			}
		}

		/**
		 * Filters the recipients of a site-wide member message batch.
		 *
		 * Lets a site narrow or extend the audience for the current page of
		 * opted-in members.
		 *
		 * @since TBD
		 *
		 * @param array<int, Recipient> $recipients Recipient rows to email.
		 * @param int                  $offset     Number of users skipped for this batch.
		 */
		$recipients = apply_filters( 'gatherpress_site_message_recipients', $recipients, $offset );

		return array(
			'recipients' => $recipients,
			'complete'   => count( $users ) < $batch_size,
			'fetched'    => count( $users ),
		);
	}

	/**
	 * Get the number of members currently eligible for a site-wide message.
	 *
	 * @since TBD
	 *
	 * @return int Number of eligible members.
	 */
	public function count_recipients(): int {
		$count  = 0;
		$offset = 0;

		do {
			$batch   = $this->get_recipient_batch( $offset );
			$count  += count( $batch['recipients'] );
			$offset += $batch['fetched'];
		} while ( ! $batch['complete'] );

		return $count;
	}

	/**
	 * Get the configured number of members processed by one cron job.
	 *
	 * @since TBD
	 *
	 * @return int Number of users in one batch.
	 */
	protected function get_batch_size(): int {
		/**
		 * Filters the number of users processed by one site-message cron job.
		 *
		 * @since TBD
		 *
		 * @param int $batch_size Number of users in one batch. Default 50.
		 */
		return max( 1, (int) apply_filters( 'gatherpress_site_message_batch_size', 50 ) );
	}

	/**
	 * AJAX handler for queuing a site-wide member message.
	 *
	 * Verifies permissions and nonce, then schedules the message for delivery
	 * on the next cron pass so the request returns without waiting on the
	 * mail server.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function ajax_send(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'gatherpress' ) )
			);
		}

		check_ajax_referer( 'gatherpress_send_email_nonce', 'nonce' );

		$subject = Utility::get_http_input( INPUT_POST, 'subject' );
		$message = Utility::get_http_input( INPUT_POST, 'message', 'sanitize_textarea_field' );

		if ( '' === trim( $message ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Please write a message before sending.', 'gatherpress' ) )
			);
		}

		$recipient_count = $this->count_recipients();

		if ( 0 === $recipient_count ) {
			wp_send_json_error(
				array( 'message' => __( 'There are no members to send this message to.', 'gatherpress' ) )
			);
		}

		if ( ! $this->schedule_message_batch( $subject, $message, 0 ) ) {
			wp_send_json_error(
				array( 'message' => __( 'The message could not be queued. Please try again.', 'gatherpress' ) )
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: Number of members the message was queued for. */
					_n(
						'Queued for %d member.',
						'Queued for %d members.',
						$recipient_count,
						'gatherpress'
					),
					$recipient_count
				),
			)
		);
	}

	/**
	 * Queue the cron job for one batch of a site-wide member message.
	 *
	 * The batches are chained rather than sent in one request: each job sends a
	 * bounded page of recipients and queues the next offset. A fatal error part
	 * way through a page therefore leaves the next page scheduled, so the
	 * remaining members still receive the message.
	 *
	 * @since TBD
	 *
	 * @param string $subject Message subject.
	 * @param string $message Message body.
	 * @param int    $offset  Number of users to skip for this batch.
	 *
	 * @return bool True when the batch is queued, false when it is not.
	 */
	protected function schedule_message_batch( string $subject, string $message, int $offset ): bool {
		$args = array( $subject, $message, $offset );

		/**
		 * Filter the site message enqueue call to take over scheduling.
		 *
		 * Return any non-null value from this filter to suppress both the
		 * WP-Cron dedup check and the `wp_schedule_single_event()` call. A
		 * companion plugin that hooks this filter (e.g. one that routes the
		 * batches through Action Scheduler) owns the full scheduling path
		 * end-to-end, including its own dedup since the batches by-pass
		 * `wp_next_scheduled()`. Mirrors the core `pre_*` filter convention:
		 * `null` means "pass through to the default"; everything else,
		 * including falsy values like `false`, `0`, and `''`, short-circuits.
		 *
		 * @since TBD
		 *
		 * @param mixed  $short_circuit Non-null to suppress the default enqueue.
		 * @param string $hook          Action hook name fired when the job runs.
		 * @param array  $args          Args passed to the action hook: `array( $subject, $message, $offset )`.
		 */
		$short_circuit = apply_filters(
			'gatherpress_site_message_pre_enqueue_job',
			null,
			'gatherpress_site_message_send',
			$args
		);

		if ( null !== $short_circuit ) {
			return true;
		}

		// A batch already queued for this offset owns it, so do not stack a
		// duplicate. The short delay keeps each batch from running in the same
		// cron pass as the batch that queued it.
		if ( false !== wp_next_scheduled( 'gatherpress_site_message_send', $args ) ) {
			return true;
		}

		return false !== wp_schedule_single_event( time() + 1, 'gatherpress_site_message_send', $args );
	}

	/**
	 * Deliver one queued batch of a site-wide message.
	 *
	 * Runs from cron so a large membership does not hold up the admin request
	 * that queued it, one bounded page per job, with the next page queued
	 * before this one is sent. Recipients are resolved again here, so anyone
	 * who opted out between queuing and delivery is skipped.
	 *
	 * @since TBD
	 *
	 * @param string $subject Optional subject line. Empty falls back to a
	 *                        translatable default built in the recipient's locale.
	 * @param string $message Message body.
	 * @param int    $offset  Number of users skipped before this batch.
	 *
	 * @return void
	 */
	public function process_message( string $subject, string $message, int $offset = 0 ): void {
		$batch = $this->get_recipient_batch( $offset );

		// Queue the next page before sending this one, so an error part way
		// through the batch cannot strand the recipients the offsets do not
		// cover yet. This runs even when the filtered page is empty, since a
		// filter can narrow one page without ending the chain.
		if ( ! $batch['complete'] ) {
			$this->schedule_message_batch( $subject, $message, $offset + $batch['fetched'] );
		}

		$mailer = Mailer::get_instance();
		// Cron runs without a logged-in user, so the "sender" to restore to is
		// the anonymous user this request already runs as.
		$sender  = wp_get_current_user();
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		foreach ( $batch['recipients'] as $recipient ) {
			if ( ! $mailer->is_eligible( $recipient ) ) {
				continue;
			}

			$switched_locale = $mailer->switch_context( $recipient );
			$subject_line    = $this->resolve_subject( $subject );
			$body            = Utility::render_template(
				sprintf( '%s/includes/templates/admin/emails/site-email.php', GATHERPRESS_CORE_PATH ),
				array( 'message' => $message ),
			);

			$mailer->restore_context( $switched_locale, $sender );
			$mailer->deliver( $recipient['email'], $subject_line, $body, $headers );
		}
	}

	/**
	 * Resolve the subject line for a site-wide member message.
	 *
	 * The default is built here rather than up front so it is translated in the
	 * recipient's locale, matching how the event update email builds its own
	 * default subject.
	 *
	 * @since TBD
	 *
	 * @param string $subject Optional subject line supplied by the sender.
	 *
	 * @return string The subject line to send.
	 */
	protected function resolve_subject( string $subject ): string {
		if ( '' === trim( $subject ) ) {
			$subject = sprintf(
				/* translators: %s: Site name. */
				__( 'Message from %s', 'gatherpress' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			);
		}

		/**
		 * Filters the subject line of a site-wide member message.
		 *
		 * @since TBD
		 *
		 * @param string $subject Email subject line.
		 */
		return apply_filters( 'gatherpress_site_message_subject', $subject );
	}
}
