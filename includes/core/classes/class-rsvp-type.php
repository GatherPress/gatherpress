<?php
/**
 * Abstract RSVP Type base class.
 *
 * This abstract class defines the interface for RSVP types.  Each RSVP type
 * (user, email, activitypub, etc.) must extend this class and implement
 * the abstract methods to handle type-specific logic.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Abstract class Rsvp_Type.
 *
 * Defines the contract for RSVP type handlers. Each type is responsible for
 * validating identifiers, providing display names, avatars, and describing
 * its capabilities.
 *
 * @since 1.0.0
 */
abstract class Rsvp_Type {
    use Singleton;

	/**
	 * Get the unique slug for this RSVP type.
	 *
	 * Examples: 'user', 'email', 'activitypub'
	 *
	 * @since 1.0.0
	 *
	 * @return string The unique type slug.
	 */
	abstract public function get_slug(): string;

	/**
	 * Get the human-readable label for this RSVP type.
	 *
	 * Used in admin UI and frontend displays.
	 *
	 * @since 1.0.0
	 *
	 * @return string The label for this type.
	 */
	abstract public function get_label(): string;

	/**
	 * Get the display name for an identifier of this type.
	 *
	 * For 'user' type, converts user ID to user's display name.
	 * For 'email' type, returns the email address.
	 * For 'activitypub' type, returns the actor's preferred username.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $identifier The identifier (user ID, email, actor URI, etc.).
	 *
	 * @return string The display name for the identifier.
	 */
	abstract public function get_display_name( $identifier ): string;

	/**
	 * Get the avatar URL for an identifier of this type.
	 *
	 * Should return a Gravatar URL for email-based types, or a custom
	 * avatar URL for types like ActivityPub.
	 *
	 * @since 1. 0.0
	 *
	 * @param mixed $identifier The identifier (user ID, email, actor URI, etc.).
	 *
	 * @return string|null The avatar URL, or null if not available.
	 */
	abstract public function get_avatar_url( $identifier ): ?string;

	/**
	 * Get the description for this RSVP type.
	 *
	 * Optional method. Can be overridden in subclasses.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description.
	 */
	public function get_description(): string {
		return '';
	}

	/**
	 * Get the icon for this RSVP type.
	 *
	 * Optional method. Can be overridden in subclasses.
	 * Should return an emoji or icon name.
	 *
	 * @since 1.0. 0
	 *
	 * @return string The icon.
	 */
	public function get_icon(): string {
		return '📋';
	}

	/**
	 * Get the profile URL for an identifier of this type.
	 *
	 * Optional method. Can be overridden in subclasses.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $identifier The identifier (user ID, email, actor URI, etc.).
	 *
	 * @return string|null The profile URL, or null if not available.
	 */
	public function get_profile_url( $identifier ): ? string {
		return null;
	}

	/**
	 * Whether this RSVP type supports guest count.
	 *
	 * Optional method. Can be overridden in subclasses.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if guests are supported, false otherwise.
	 */
	public function supports_guests(): bool {
		return true;
	}

	/**
	 * Whether this RSVP type supports anonymous RSVPs.
	 *
	 * Optional method. Can be overridden in subclasses.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if anonymous RSVPs are supported, false otherwise.
	 */
	public function supports_anonymous(): bool {
		return true;
	}

	/**
	 * Validate an identifier for this RSVP type.
	 *
	 * Should check if the identifier is valid for this type before processing.
	 * For example, verify it's a valid user ID or email address.
	 *
	 * Optional method. Can be overridden in subclasses.
	 * Default implementation checks if identifier is not empty.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $identifier The identifier to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid_identifier( $identifier ): bool {
		return ! empty( $identifier );
	}

	/**
	 * Filter by this RSVP type in comment queries.
	 *
	 * This method returns the taxonomy slug and term to filter comments
	 * by this RSVP type. Used internally for querying RSVPs of specific types.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     @type string $taxonomy The taxonomy slug.
	 *     @type string $term     The term slug.
	 * }
	 */
	public function get_filter_terms(): array {
		return array(
			'taxonomy' => '_gatherpress_rsvp_type',
			'term'     => $this->get_slug(),
		);
	}
}