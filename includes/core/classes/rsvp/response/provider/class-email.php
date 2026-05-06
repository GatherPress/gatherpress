<?php
/**
 * Email RSVP Type handler. Also referred to as Open-RSVP.
 *
 * Handles RSVP logic for non-WordPress users via email address.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp\Response\Provider;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;

/**
 * Class Email_Type.
 *
 * Handles RSVP logic for email-based RSVPs (non-WordPress users).
 *
 * @since 1. 0.0
 */
class Email extends Base {
	/**
	 * Return the slug.
	 *
	 * @return string
	 */
	public static function get_slug(): string {
		return 'email';
	}

	/**
	 * Get the unique slug for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return Identity_Type
	 */
	public function get_identity_type(): Identity_Type {
		return Identity_Type::EMAIL;
	}

	/**
	 * Get the human-readable label for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'Email', 'gatherpress' );
	}

	/**
	 * Get the icon for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return '✉️';
	}

	/**
	 * Get the display name for an email-based RSVP.
	 *
	 * Returns the email address itself, sanitized.
	 *
	 * @since 1.0.0
	 *
	 * @param Identity $identity The identity.
	 * @return string The sanitized email address.
	 */
	public function get_display_name( Identity $identity ): string {
		return sanitize_email( $identity->value );
	}
}
