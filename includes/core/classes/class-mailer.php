<?php
/**
 * Shared email delivery for GatherPress.
 *
 * This file contains the Mailer class, which owns the per-recipient transport
 * shared by the event update emails and the site-wide member message: opt-in
 * eligibility, recipient context switching, and the wp_mail call itself.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_User;

/**
 * Class Mailer.
 *
 * Owns how every GatherPress email is delivered, and how every email is
 * queued. Callers own who gets an email and what it says, the Mailer owns
 * the transport and the scheduling.
 *
 * @since TBD
 *
 * @phpstan-type Recipient array{is_user: bool, user_id: int, comment_id: int, email: string, name: string}
 * @phpstan-type ComposedEmail array{subject: string, body: string, headers?: array<int, string>}
 */
final class Mailer {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Check whether a recipient should receive an email.
	 *
	 * Honors the recipient's opt-in preference: user meta for WordPress users,
	 * RSVP comment meta for non-user recipients. A recipient without an email
	 * address is never eligible.
	 *
	 * @since TBD
	 *
	 * @param array $recipient Recipient row from a mail path.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return bool True when the recipient can be emailed.
	 */
	public function is_eligible( array $recipient ): bool {
		if ( empty( $recipient['email'] ) ) {
			return false;
		}

		if ( $recipient['is_user'] ) {
			return User::get_instance()->has_event_updates_opt_in( $recipient['user_id'] );
		}

		return '0' !== get_comment_meta(
			$recipient['comment_id'],
			'gatherpress_event_updates_opt_in',
			true
		);
	}

	/**
	 * Check whether a recipient consents to the kind of email being sent.
	 *
	 * Defaults to the recipient's opt-in, so a site that does not filter this
	 * behaves exactly as `is_eligible()` did. The filter lets a site tighten
	 * the rule for a specific context, for example a site-wide message that is
	 * not about events, without touching the other mail paths. A recipient
	 * without an email address never consents, whatever the filter returns.
	 *
	 * @since TBD
	 *
	 * @param array  $recipient Recipient row from a mail path.
	 * @param string $context   Mail path asking for consent, e.g. `event` or `site_message`.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return bool True when the recipient consents to this email.
	 */
	public function has_consent( array $recipient, string $context ): bool {
		// A missing address is not a consent question, so no filter can override it.
		if ( empty( $recipient['email'] ) ) {
			return false;
		}

		/**
		 * Filters whether a recipient consents to a GatherPress email.
		 *
		 * @since TBD
		 *
		 * @param bool   $consent   Whether the recipient consents. Defaults to the opt-in.
		 * @param array  $recipient Recipient row from a mail path.
		 * @param string $context   Mail path asking for consent.
		 * @phpstan-param Recipient $recipient
		 */
		return (bool) apply_filters(
			'gatherpress_mail_recipient_consent',
			$this->is_eligible( $recipient ),
			$recipient,
			$context
		);
	}

	/**
	 * Switch the request context over to the recipient.
	 *
	 * A WordPress user gets their locale and user context applied, so date and
	 * time formats, the user's timezone, and translations inside the rendered
	 * template match the recipient. Non-user recipients keep the current
	 * context.
	 *
	 * @since TBD
	 *
	 * @param array $recipient Recipient row from a mail path.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return bool Whether the locale was switched, for `restore_context()`.
	 */
	public function switch_context( array $recipient ): bool {
		if ( ! $recipient['is_user'] ) {
			return false;
		}

		$switched_locale = switch_to_user_locale( $recipient['user_id'] );

		// Set the current user to the member being mailed, so the GatherPress
		// filters for date and time format, as well as the user's timezone, are
		// recognized by the functions inside the template render.
		wp_set_current_user( $recipient['user_id'] );

		return $switched_locale;
	}

	/**
	 * Restore the context that was active before a recipient switch.
	 *
	 * @since TBD
	 *
	 * @param bool    $switched_locale Whether `switch_context()` switched the locale.
	 * @param WP_User $sender          User to make current again.
	 *
	 * @return void
	 */
	public function restore_context( bool $switched_locale, WP_User $sender ): void {
		// Reset the current user to the sender of the email.
		wp_set_current_user( $sender->ID );

		// Cleanup branch only fires when `switch_to_user_locale()` actually
		// switched, which requires a non-stub `WP_Locale_Switcher` and is not
		// reachable from the test runner.
		if ( $switched_locale ) { // @codeCoverageIgnore
			restore_previous_locale(); // @codeCoverageIgnore
		}
	}

	/**
	 * Send an email to a single recipient.
	 *
	 * @since TBD
	 *
	 * @param string             $email   Recipient email address.
	 * @param string             $subject Email subject line.
	 * @param string             $body    Rendered email body.
	 * @param array<int, string> $headers Optional wp_mail headers.
	 *
	 * @return bool True when WordPress accepted the email for sending.
	 */
	public function deliver( string $email, string $subject, string $body, array $headers = array() ): bool {
		// Subject lines arrive from translators and filters, so decode entities
		// and strip the slashes WordPress adds on input before handing them off.
		$subject = stripslashes_deep( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) );

		return wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * Send one email to one recipient while their context is active.
	 *
	 * Runs the whole per-recipient sequence in one place: consent check,
	 * context switch, compose, deliver, and context restore. Callers only own
	 * who the recipient is and what the email says, which they return from the
	 * `$compose` callback so the subject and body are built in the recipient's
	 * locale and user context.
	 *
	 * @since TBD
	 *
	 * @param array    $recipient Recipient row from a mail path.
	 * @param callable $compose   Returns `array{subject: string, body: string, headers?: array<int, string>}`
	 *                            built for the recipient.
	 * @param string   $context   Mail path asking for consent, e.g. `event` or `site_message`.
	 * @phpstan-param Recipient $recipient
	 * @phpstan-param callable(Recipient): ComposedEmail $compose
	 *
	 * @return bool True when the email was delivered, false when consent was withheld.
	 */
	public function send( array $recipient, callable $compose, string $context = 'event' ): bool {
		if ( ! $this->has_consent( $recipient, $context ) ) {
			return false;
		}

		// Cron runs without a logged-in user, so the sender to restore to is
		// whoever is current now: the editor for a request, the anonymous user
		// for a cron job.
		$sender          = wp_get_current_user();
		$switched_locale = $this->switch_context( $recipient );

		// Deliver while still in the recipient's context, so wp_mail filters
		// run with the recipient's locale active. A filter or the compose
		// callback that throws still has to hand the context back, or the next
		// recipient would be mailed in the wrong locale.
		try {
			$email = $compose( $recipient );

			return $this->deliver(
				$recipient['email'],
				$email['subject'],
				$email['body'],
				$email['headers'] ?? array()
			);
		} finally {
			$this->restore_context( $switched_locale, $sender );
		}
	}

	/**
	 * Queue a GatherPress mail job on cron.
	 *
	 * Every mail path queues through here so delivery is scheduled the same
	 * way. A companion plugin can take over scheduling by hooking the
	 * `gatherpress_mail_pre_enqueue_job` filter; the native WP-Cron path
	 * deduplicates on the hook and args, and retries once before giving up.
	 *
	 * @since TBD
	 *
	 * @param string $hook  Action hook fired when the job runs.
	 * @param array  $args  Args passed to the action hook.
	 * @param int    $delay Seconds to wait before the job may run. Default 0.
	 * @phpstan-param list<mixed> $args
	 *
	 * @return bool True when the job is queued or owned by an external scheduler.
	 */
	public function schedule( string $hook, array $args, int $delay = 0 ): bool {
		/**
		 * Filters the mail enqueue call to take over scheduling.
		 *
		 * Return any non-null value to suppress both the WP-Cron dedup check
		 * and the `wp_schedule_single_event()` call. A companion plugin that
		 * hooks this filter owns the full scheduling path end-to-end,
		 * including its own dedup since the jobs by-pass `wp_next_scheduled()`.
		 * Mirrors the core `pre_*` filter convention: `null` means "pass
		 * through to the default"; everything else, including falsy values
		 * like `false`, `0`, and `''`, short-circuits.
		 *
		 * @since TBD
		 *
		 * @param mixed  $short_circuit Non-null to suppress the default enqueue.
		 * @param string $hook          Action hook name fired when the job runs.
		 * @param array  $args          Args passed to the action hook.
		 * @phpstan-param list<mixed> $args
		 */
		$short_circuit = apply_filters( 'gatherpress_mail_pre_enqueue_job', null, $hook, $args );

		if ( null !== $short_circuit ) {
			return true;
		}

		// A job already queued for these args owns them, so do not stack a
		// duplicate.
		if ( false !== wp_next_scheduled( $hook, $args ) ) {
			return true;
		}

		$timestamp = time() + max( 0, $delay );
		$scheduled = wp_schedule_single_event( $timestamp, $hook, $args );

		// WP-Cron can fail transiently, so retry once before reporting that the
		// job was not queued.
		if ( false === $scheduled ) {
			$scheduled = wp_schedule_single_event( $timestamp, $hook, $args );
		}

		return false !== $scheduled;
	}
}
