<?php
/**
 * RSVP Status.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * RSVP Status.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */
enum Visibility: string {
	case PUBLIC    = '0';
	case ANONYMOUS = '1';
	case ATTENDEES = '2';
	case ADMIN     = '3';
	case ORGANIZER = '4';

	/**
	 * Constant representing the RSVP Taxonomy.
	 * This constant defines the status taxonomy for RSVP comment type.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const TAXONOMY = '_gatherpress_rsvp_visibility';

	/**
	 * Get all valid values.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function values(): array {
		$values = array();

		foreach ( self::cases() as $case ) {
			$values[] = $case->value;
		}

		return $values;
	}
}
