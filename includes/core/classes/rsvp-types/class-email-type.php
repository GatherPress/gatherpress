<?php
/**
 * Email RSVP Type handler.
 *
 * Handles RSVP logic for non-WordPress users via email address.
 *
 * @package GatherPress\Core\Rsvp_Types
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp_Types;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp_Type;

/**
 * Class Email_Type.
 *
 * Handles RSVP logic for email-based RSVPs (non-WordPress users).
 *
 * @since 1. 0.0
 */
class Email_Type extends Rsvp_Type {
	/**
	 * Get the unique slug for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'email';
	}

	/**
	 * Get the human-readable label for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Email RSVP', 'gatherpress' );
	}

	/**
	 * Get the description for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Non-user email RSVP', 'gatherpress' );
	}

	/**
	 * Get the icon for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return '✉️';
	}

	/**
	 * Get the display name for an email-based RSVP.
	 *
	 * Returns the email address itself, sanitized.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $identifier The email address.
	 *
	 * @return string The sanitized email address.
	 */
	public function get_display_name( $identifier ): string {
		return sanitize_email( $identifier );
	}

	/**
	 * Get the avatar URL for an email-based RSVP.
	 *
	 * Returns a Gravatar URL based on the email address.
	 *
	 * @since 1. 0.0
	 *
	 * @param mixed $identifier The email address.
	 *
	 * @return string|null The Gravatar URL, or null if email is invalid.
	 */
	public function get_avatar_url( $identifier ): ?string {
		if ( ! is_email( $identifier ) ) {
			return null;
		}

		return get_avatar_url( $identifier );
	}

	/**
	 * Email-based RSVPs don't have a profile URL.
	 *
	 * @since 1. 0.0
	 *
	 * @param mixed $identifier Unused.
	 *
	 * @return string|null Always returns null for email type.
	 */
	public function get_profile_url( $identifier ): ?string {
		return null;
	}

	/**
	 * Whether email-based RSVPs support guests.
	 *
	 * Email RSVPs do not support guest count.
	 *
	 * @since 1.0. 0
	 *
	 * @return bool Always false for email type.
	 */
	public function supports_guests(): bool {
		return false;
	}

	/**
	 * Whether email-based RSVPs support anonymous.
	 *
	 * Email RSVPs are already "anonymous" by nature (not tied to a WP user).
	 *
	 * @since 1. 0.0
	 *
	 * @return bool Always false for email type.
	 */
	public function supports_anonymous(): bool {
		return false;
	}

	/**
	 * Validate if the identifier is a valid email address.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $identifier The identifier to validate.
	 *
	 * @return bool True if valid email, false otherwise.
	 */
	public function is_valid_identifier( $identifier ): bool {
		return is_email( $identifier );
	}
}
