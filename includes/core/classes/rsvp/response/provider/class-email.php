<?php
/**
 * Email RSVP Type handler. Also referred to as Open-RSVP.
 *
 * Handles RSVP logic for non-WordPress users via email address.
 *
 * @package GatherPress\Core\Rsvp\Response\Provider
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response\Provider;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;

/**
 * Class Email.
 *
 * Handles RSVP logic for email-based RSVPs (non-WordPress users).
 *
 * @since 0.35.0
 */
final class Email extends Base {
	/**
	 * Return the slug.
	 *
	 * @since 0.35.0
	 *
	 * @return string The unique provider slug.
	 */
	public static function get_slug(): string {
		return 'email';
	}

	/**
	 * Get the identity type for this RSVP type.
	 *
	 * @since 0.35.0
	 *
	 * @return Identity_Type The email identity type.
	 */
	public static function get_identity_type(): Identity_Type {
		return Identity_Type::EMAIL;
	}

	/**
	 * Get the human-readable label for this RSVP type.
	 *
	 * @since 0.35.0
	 *
	 * @return string The human-readable label.
	 */
	public static function get_label(): string {
		return __( 'Email', 'gatherpress' );
	}

	/**
	 * Get the icon for this RSVP type.
	 *
	 * @since 0.35.0
	 *
	 * @return string The icon.
	 */
	public static function get_icon(): string {
		return '✉️';
	}

	/**
	 * Get the display name for an email-based RSVP.
	 *
	 * The address is the best displayable name an email identity has.
	 * No sanitization is needed here: the Identity constructor already
	 * rejected anything that is not a valid address, and sanitizing on
	 * read could silently return a modified string that diverges from
	 * the stored identity value.
	 *
	 * @since 0.35.0
	 *
	 * @param Identity $identity The identity.
	 *
	 * @return string The email address.
	 */
	public function get_display_name( Identity $identity ): string {
		return (string) $identity->value;
	}
}
