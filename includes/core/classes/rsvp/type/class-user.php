<?php
/**
 * User RSVP Type handler.
 *
 * Handles RSVP logic for registered WordPress users.
 *
 * @package GatherPress\Core\Rsvp_Types
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp\Type;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp_Type;

/**
 * Class User_Type.
 *
 * Handles RSVP logic for registered WordPress users.
 *
 * @since 1. 0.0
 */
class User extends Base {

	/**
	 * Get the unique slug for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'user';
	}

	/**
	 * Get the human-readable label for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Registered User', 'gatherpress' );
	}

	/**
	 * Get the description for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'WordPress user RSVP', 'gatherpress' );
	}

	/**
	 * Get the icon for this RSVP type.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return '👤';
	}

	/**
	 * Get the display name for a WordPress user.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $identifier The user ID.
	 *
	 * @return string The user's display name, or empty string if user not found.
	 */
	public function get_display_name( $identifier ): string {
		$user = get_user_by( 'id', intval( $identifier ) );

		if ( ! $user instanceof \WP_User ) {
			return '';
		}

		return $user->display_name;
	}

	/**
	 * Filter the RSVP-Comment-Query for a RSVP_Type.
	 *
	 * @param array $args       The present args.
	 * @param mixed $identifier The identifier of the RSVP.
	 * @return array
	 */
	protected function add_identifier_to_comment_query( $args, $identifier ): array {
		$args['user_id'] = $identifier;
		return $args;
	}

	/**
	 * Filter the save RSVP-Comment-Query for a RSVP_Type.
	 *
	 * @param array $args       The present args.
	 * @param mixed $identifier The identifier of the RSVP.
	 * @return array
	 */
	public function filter_query_save( $args, $identifier ): array {
		$args['user_id']            = \intval( $identifier );
		$args['comment_author_url'] = get_author_posts_url( $identifier );
		return $args;
	}

	/**
	 * Get the avatar URL for a WordPress user.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $identifier The user ID.
	 * @return string|null The avatar URL, or null if user not found.
	 */
	public function get_avatar_url( $identifier ): ?string {
		$user = get_user_by( 'id', \intval( $identifier ) );

		if ( ! $user instanceof \WP_User ) {
			return null;
		}

		return get_avatar_url( $user->user_email );
	}

	/**
	 * Get the profile URL for a WordPress user.
	 *
	 * @since 1. 0.0
	 *
	 * @param mixed $identifier The user ID.
	 *
	 * @return string|null The author posts URL, or null if user not found.
	 */
	public function get_attendee_url( $identifier ): ?string {
		$user = get_user_by( 'id', intval( $identifier ) );

		if ( ! $user instanceof \WP_User ) {
			return null;
		}

		return get_author_posts_url( $user->ID );
	}

	/**
	 * Validate if the identifier is a valid user ID.
	 *
	 * @since 1.0. 0
	 *
	 * @param mixed $identifier The user ID to validate.
	 *
	 * @return bool True if valid user ID, false otherwise.
	 */
	public function is_valid_identifier( $identifier ): bool {
		$user = get_user_by( 'id', intval( $identifier ) );

		return $user instanceof \WP_User;
	}
}
