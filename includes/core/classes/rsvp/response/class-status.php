<?php
/**
 * RSVP Status.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * RSVP Status.
 *
 * @since 0.35.0
 */
enum Status: string {

	/**
	 * Statuses an RSVP response can have.
	 *
	 * @since 0.35.0
	 */
	case ATTENDING     = 'attending';
	case NOT_ATTENDING = 'not_attending';
	case WAITING_LIST  = 'waiting_list';
	case NO_STATUS     = 'no_status';

	/**
	 * Constant representing the RSVP Taxonomy.
	 * This constant defines the status taxonomy for RSVP comment type.
	 *
	 * @since 0.35.0
	 *
	 * @var string
	 */
	public const TAXONOMY = '_gatherpress_rsvp_status';

	/**
	 * Get the corresponding Status enum instance. If no match is found Status::NO_STATUS is returned.
	 *
	 * @since 0.35.0
	 *
	 * @param string $status The status value.
	 *
	 * @return Status The matching status, or Status::NO_STATUS if no match is found.
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
	 * @since 0.35.0
	 *
	 * @return string[] List of all valid status values.
	 */
	public static function values(): array {
		$values = array();

		foreach ( self::cases() as $case ) {
			$values[] = $case->value;
		}

		return $values;
	}

	/**
	 * The human-readable name for this status.
	 *
	 * Lives here rather than at each display site so the admin list table's
	 * Response column and its filter cannot drift apart, and so a new case
	 * arrives with its label attached.
	 *
	 * @since 0.36.0
	 *
	 * @return string The translated label.
	 */
	public function label(): string {
		// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext -- Enum instance methods have $this; the sniff reads this enum's first non-static method as a plain function.
		return match ( $this ) {
			self::ATTENDING     => __( 'Attending', 'gatherpress' ),
			self::NOT_ATTENDING => __( 'Not Attending', 'gatherpress' ),
			self::WAITING_LIST  => __( 'Waiting List', 'gatherpress' ),
			self::NO_STATUS     => __( 'No Response', 'gatherpress' ),
		};
	}

	/**
	 * The statuses worth offering as a filter.
	 *
	 * `NO_STATUS` is excluded: it is the absence of a response rather than
	 * one, so it carries no term to filter on.
	 *
	 * @since 0.36.0
	 *
	 * @return self[] The filterable cases.
	 */
	public static function filterable(): array {
		return array_filter(
			self::cases(),
			static fn( self $status ): bool => self::NO_STATUS !== $status
		);
	}
}
