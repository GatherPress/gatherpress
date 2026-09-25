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
 * Per-recipient email transport shared by every GatherPress mail path.
 *
 * @since TBD
 *
 * @phpstan-type Recipient array{is_user: bool, user_id: int, comment_id: int, email: string, name: string}
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
}
