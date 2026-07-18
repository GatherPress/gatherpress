<?php
/**
 * RSVP Identity Type.
 *
 * Enumerates the identifier types an RSVP identity can be based on.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
/**
 * RSVP Identity Type.
 *
 * Enumerates the identifier types an RSVP identity can be based on.
 *
 * @since 0.35.0
 */
enum Identity_Type: string {
	/**
	 * Identifier types supported for RSVP identities.
	 *
	 * @since 0.35.0
	 */
	case EMAIL       = 'email';
	case WP_USER_ID  = 'wp_user_id';
	case URL         = 'url';
	case EXTERNAL_ID = 'external_id';

	/**
	 * Get all identity type values as an array of strings.
	 *
	 * Mirrors the `values()` helper on the sibling `Status` and
	 * `Visibility` enums.
	 *
	 * @since 0.35.0
	 *
	 * @return array<int, string> The backed values of every case.
	 */
	public static function values(): array {
		$values = array();

		foreach ( self::cases() as $case ) {
			$values[] = $case->value;
		}

		return $values;
	}
}
