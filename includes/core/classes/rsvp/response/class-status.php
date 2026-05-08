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
enum Status: string {
	case ATTENDING     = 'attending';
	case NOT_ATTENDING = 'not_attending';
	case WAITING_LIST  = 'waiting_list';
	case NO_STATUS     = 'no_status';

	/**
	 * Constant representing the RSVP Taxonomy.
	 * This constant defines the status taxonomy for RSVP comment type.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const TAXONOMY = '_gatherpress_rsvp_status';

	/**
	 * Get the corresponding Status enum instance. If no match is found Status::NO_STATUS is returned.
	 *
	 * @since 1.0.0
	 * @param string $status The status value.
	 * @return Status
	 */
	public static function try_from( string $status ): Status {
		$status = Status::tryFrom( $status );

		if ( null === $status ) {
			return Status::NO_STATUS;
		}

		return $status;
	}

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
