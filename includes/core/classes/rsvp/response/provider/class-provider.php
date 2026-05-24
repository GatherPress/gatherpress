<?php
/**
 * Abstract RSVP Response Type.
 *
 * Providers define WHAT an identity is, not HOW it is stored.
 *
 * @package GatherPress\Core\Rsvp\Type
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response\Provider;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;

/**
 * Class Base
 */
abstract class Provider {
	public const TAXONOMY = '_gatherpress_rsvp_provider';

	/**
	 * Get the slug of the provider.
	 *
	 * Should be unique and only contain lowercase and underscores.
	 *
	 * @return string
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get identity type.
	 *
	 * Defines how identity is stored within a WordPress comment.
	 *
	 * The types 'user', 'email', 'url' will lead to direct storage in the comment
	 * Any other valid string will lead to storage in Comment Meta.
	 *
	 * @since 0.35.0
	 *
	 * @return Identity_Type
	 */
	abstract public static function get_identity_type(): Identity_Type;

	/**
	 * Get the label for this Attendee type.
	 *
	 * @return string
	 */
	abstract public static function get_label(): string;

	/**
	 * Get the label for this Attendee type.
	 *
	 * @return string
	 */
	abstract public static function get_icon(): string;

	/**
	 * Get display name.
	 *
	 * @since 0.35.0
	 *
	 * @param Identity $identity The identity.
	 *
	 * @return string
	 */
	abstract public function get_display_name( Identity $identity ): string;

	/**
	 * Get the avatar URL for an email-based RSVP.
	 *
	 * Returns a Gravatar URL based on the email address.
	 *
	 * @since 0.35.0
	 *
	 * @param Identity $identity The identity.
	 *
	 * @return string|null The Gravatar URL, or null if email is invalid.
	 */
	public function get_avatar_url( Identity $identity ): ?string {
		if ( Identity_Type::WP_USER_ID === $identity->type || is_email( $identity->value ) ) {
			return get_avatar_url( $identity->value );
		}

		return null;
	}

	/**
	 * Get profile URL.
	 *
	 * @since 0.35.0
	 *
	 * @param Identity $identity The identity.
	 *
	 * @return string|null
	 */
	public function get_url( Identity $identity ): ?string {
		if ( false !== filter_var( $identity->value, FILTER_VALIDATE_URL ) ) {
			return $identity->value;
		}

		return null;
	}
}
