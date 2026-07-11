<?php
/**
 * RSVP Visibility.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * RSVP Visibility.
 *
 * @since 0.35.0
 */
enum Visibility: string {
	/**
	 * Visibility levels an RSVP response can have.
	 *
	 * @since 0.35.0
	 */
	case PUBLIC    = '0';
	case ANONYMOUS = '1';
	case ATTENDEES = '2';
	case ADMIN     = '3';
	case ORGANIZER = '4';

	/**
	 * Constant representing the RSVP Taxonomy.
	 * This constant defines the visibility taxonomy for RSVP comment type.
	 *
	 * @since 0.35.0
	 *
	 * @var string
	 */
	public const TAXONOMY = '_gatherpress_rsvp_visibility';

	/**
	 * Get all valid values.
	 *
	 * @since 0.35.0
	 *
	 * @return array List of all valid visibility values.
	 */
	public static function values(): array {
		$values = array();

		foreach ( self::cases() as $case ) {
			$values[] = $case->value;
		}

		return $values;
	}
}
