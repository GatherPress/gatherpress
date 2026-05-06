<?php
/**
 * RSVP Identity Value Object.
 *
 * Represents a unique RSVP identity consisting of a type and identifier.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit;

/**
 * RSVP Identity Value Object.
 *
 * Represents a unique RSVP identity consisting of a type and identifier.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */
enum Identity_Type: string {
	case EMAIL       = 'email';
	case WP_USER_ID  = 'wp_user_id';
	case URL         = 'url';
	case EXTERNAL_ID = 'external_id';
}
