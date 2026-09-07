<?php
/**
 * Vocabulary of the operational statuses an event can be in.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Status.
 *
 * One place that says what statuses exist and what each one means, so PHP,
 * the editor and the calendar all read the same list rather than keeping
 * their own copies in step.
 *
 * The defaults are the whole of Schema.org's EventStatusType vocabulary, with
 * `moved` generalized: an event can move to a new venue as readily as it can
 * move online, and where it moved to is the location's business rather than
 * the status's.
 *
 * @since 0.36.0
 */
final class Status {
	/**
	 * Status of an event that is going ahead as planned.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SCHEDULED = 'scheduled';

	/**
	 * Status of an event that will not take place.
	 *
	 * The slug keeps the doubled letter that Schema.org and RFC 5545 both
	 * use, so what is stored matches what is published.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const CANCELED = 'cancelled';

	/**
	 * Status of an event delayed to a date not yet decided.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const POSTPONED = 'postponed';

	/**
	 * Status of an event whose date or time has changed.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const RESCHEDULED = 'rescheduled';

	/**
	 * Status of an event that is happening somewhere else than announced.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const MOVED = 'moved';

	/**
	 * Schema.org value published for a status that names none of its own.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const DEFAULT_SCHEMA = 'EventScheduled';

	/**
	 * RFC 5545 value published for a status that names none of its own.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const DEFAULT_ICAL = 'CONFIRMED';

	/**
	 * Every status an event can be in, keyed by the slug that is stored.
	 *
	 * Each entry carries the words people read and the values the standards
	 * expect, so adding a status is one array entry rather than an edit in
	 * four places.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<string, string>> The statuses, keyed by slug.
	 */
	public static function all(): array {
		$statuses = array(
			self::SCHEDULED   => array(
				'label'       => __( 'Scheduled', 'gatherpress' ),
				'description' => __( 'Event is planned and confirmed to take place.', 'gatherpress' ),
				'schema'      => 'EventScheduled',
				'ical'        => 'CONFIRMED',
			),
			self::CANCELED    => array(
				'label'       => __( 'Canceled', 'gatherpress' ),
				'description' => __(
					'Event will not take place. Calendar feeds will mark it as canceled.',
					'gatherpress'
				),
				'schema'      => 'EventCancelled',
				'ical'        => 'CANCELLED',
			),
			self::POSTPONED   => array(
				'label'       => __( 'Postponed', 'gatherpress' ),
				'description' => __( 'Event is delayed to a future unconfirmed date.', 'gatherpress' ),
				'schema'      => 'EventPostponed',
				'ical'        => 'TENTATIVE',
			),
			self::RESCHEDULED => array(
				'label'       => __( 'Rescheduled', 'gatherpress' ),
				'description' => __( 'Event date and time have been changed.', 'gatherpress' ),
				'schema'      => 'EventRescheduled',
				'ical'        => 'TENTATIVE',
			),
			self::MOVED       => array(
				'label'       => __( 'Moved', 'gatherpress' ),
				'description' => __(
					'Event is taking place somewhere else, online or at another venue.',
					'gatherpress'
				),
				'schema'      => 'EventScheduled',
				'ical'        => 'CONFIRMED',
			),
		);

		/**
		 * Filters the operational statuses an event can be in.
		 *
		 * Each status is keyed by the slug stored against the event and holds
		 * the `label` and `description` people read, plus the `schema`
		 * (Schema.org EventStatusType) and `ical` (RFC 5545 STATUS) values
		 * published for it. A status that omits either falls back to
		 * `EventScheduled` and `CONFIRMED`, which say the event is going
		 * ahead, so an unrecognized status never tells a calendar client
		 * something untrue.
		 *
		 * Removing `scheduled` is not possible: it is what an event with no
		 * status of its own reports.
		 *
		 * @since 0.36.0
		 *
		 * @param array<string, array<string, string>> $statuses The statuses, keyed by slug.
		 */
		$statuses = (array) apply_filters( 'gatherpress_event_statuses', $statuses );

		if ( ! isset( $statuses[ self::SCHEDULED ] ) ) {
			$statuses[ self::SCHEDULED ] = array(
				'label'       => __( 'Scheduled', 'gatherpress' ),
				'description' => __(
					'Event is planned and confirmed to take place.',
					'gatherpress'
				),
				'schema'      => 'EventScheduled',
				'ical'        => 'CONFIRMED',
			);
		}

		return $statuses;
	}

	/**
	 * The slugs of every status, in the order they are offered.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The status slugs.
	 */
	public static function slugs(): array {
		return array_keys( self::all() );
	}

	/**
	 * Whether a slug names a status an event can be in.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The slug to check.
	 *
	 * @return bool True when the status exists.
	 */
	public static function exists( string $slug ): bool {
		return isset( self::all()[ $slug ] );
	}

	/**
	 * The words shown for a status.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The label, or the scheduled one for an unknown status.
	 */
	public static function label( string $slug ): string {
		return (string) ( self::get( $slug )['label'] ?? '' );
	}

	/**
	 * The Schema.org EventStatusType published for a status.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The Schema.org value.
	 */
	public static function schema( string $slug ): string {
		$schema = self::get( $slug )['schema'] ?? '';

		return '' === $schema ? self::DEFAULT_SCHEMA : (string) $schema;
	}

	/**
	 * The RFC 5545 STATUS published for a status.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return string The iCalendar value.
	 */
	public static function ical( string $slug ): string {
		$ical = self::get( $slug )['ical'] ?? '';

		return '' === $ical ? self::DEFAULT_ICAL : (string) $ical;
	}

	/**
	 * One status's definition, falling back to the scheduled one.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The status slug.
	 *
	 * @return array<string, mixed> The definition.
	 */
	private static function get( string $slug ): array {
		$statuses = self::all();

		return (array) ( $statuses[ $slug ] ?? $statuses[ self::SCHEDULED ] );
	}
}
