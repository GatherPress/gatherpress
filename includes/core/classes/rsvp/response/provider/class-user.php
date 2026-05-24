<?php
/**
 * User RSVP Type handler.
 *
 * Handles RSVP logic for registered WordPress users.
 *
 * @package GatherPress\Core\Rsvp_Types
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response\Provider;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use WP_User;

/**
 * Class User_Type.
 *
 * Handles RSVP logic for registered WordPress users.
 *
 * @since 1. 0.0
 */
final class User extends Provider {
	/**
	 * Return the slug.
	 *
	 * @return string
	 */
	public static function get_slug(): string {
		return 'user';
	}

	/**
	 * Get the unique slug for this RSVP type.
	 *
	 * @since 0.35.0
	 *
	 * @return Identity_Type
	 */
	public static function get_identity_type(): Identity_Type {
		return Identity_Type::WP_USER_ID;
	}

	/**
	 * Get the human-readable label for this RSVP type.
	 *
	 * @since 0.35.0
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'User', 'gatherpress' );
	}

	/**
	 * Get the icon for this RSVP type.
	 *
	 * @since 0.35.0
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return '👤';
	}

	/**
	 * Get the display name for a WordPress user.
	 *
	 * @since 0.35.0
	 *
	 * @param Identity $identity The identity.
	 * @return string The user's display name, or empty string if user not found.
	 */
	public function get_display_name( Identity $identity ): string {
		$user = get_user_by( 'id', \intval( $identity->value ) );

		if ( ! $user instanceof WP_User ) {
			return '';
		}

		return $user->display_name;
	}

	/**
	 * Get the profile URL for a WordPress user.
	 *
	 * @since 1. 0.0
	 *
	 * @param Identity $identity The identity.
	 * @return string The author posts URL.
	 */
	public function get_url( Identity $identity ): string {
		return get_author_posts_url( $identity->value );
	}
}
