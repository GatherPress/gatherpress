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
		add_action( 'gatherpress_site_message_send', array( $this, 'process_message' ), 10, 2 );
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
			array( 'recipient_count' => count( $this->get_recipients() ) ),
			true
		);
	}

	/**
	 * Get the members a site-wide message would go out to.
	 *
	 * Every user on the current site whose event updates preference is on is a
	 * recipient. Users without that preference set are included by default,
	 * which mirrors the behavior of the event update email.
	 *
	 * @since TBD
	 *
	 * @return array<int, Recipient> An array of recipient rows.
	 */
	public function get_recipients(): array {
		$mailer     = Mailer::get_instance();
		$recipients = array();

		foreach ( get_users( array( 'fields' => array( 'ID', 'user_email', 'display_name' ) ) ) as $user ) {
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
		 * Filters the recipients of a site-wide member message.
		 *
		 * Lets a site narrow or extend the audience beyond the opted-in
		 * members of the current site.
		 *
		 * @since TBD
		 *
		 * @param array<int, Recipient> $recipients Recipient rows to email.
		 */
		return apply_filters( 'gatherpress_site_message_recipients', $recipients );
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

		$recipients = $this->get_recipients();

		if ( empty( $recipients ) ) {
			wp_send_json_error(
				array( 'message' => __( 'There are no members to send this message to.', 'gatherpress' ) )
			);
		}

		$scheduled = wp_schedule_single_event(
			time(),
			'gatherpress_site_message_send',
			array( $subject, $message, wp_generate_uuid4() )
		);

		if ( false === $scheduled ) {
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
						count( $recipients ),
						'gatherpress'
					),
					count( $recipients )
				),
			)
		);
	}

	/**
	 * Deliver the queued site-wide message to every recipient.
	 *
	 * Runs from cron so a large membership does not hold up the admin request
	 * that queued it. Recipients are resolved again here, so anyone who opted
	 * out between queuing and delivery is skipped.
	 *
	 * @since TBD
	 *
	 * @param string $subject Optional subject line. Empty falls back to a
	 *                        translatable default built in the recipient's locale.
	 * @param string $message Message body.
	 *
	 * @return void
	 */
	public function process_message( string $subject, string $message ): void {
		$recipients = $this->get_recipients();

		if ( empty( $recipients ) ) {
			return;
		}

		$mailer = Mailer::get_instance();
		// Cron runs without a logged-in user, so the "sender" to restore to is
		// the anonymous user this request already runs as.
		$sender  = wp_get_current_user();
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		foreach ( $recipients as $recipient ) {
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
