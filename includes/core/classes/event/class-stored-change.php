<?php
/**
 * A change to an event that attendees have not been told about.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GatherPress\Core\Utility;

/**
 * Class Stored_Change.
 *
 * What changed in the logistics of an event since attendees were last told,
 * with the logistics before the first change and after the last one. `Change`
 * keeps it in post meta until the message is sent.
 *
 * @since TBD
 */
final class Stored_Change {

	/**
	 * The keys that are about the date or the time.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const DATETIME_KEYS = array( 'datetime_start', 'datetime_end', 'timezone' );

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param string[]  $keys        What changed, in the order of `Logistics::KEYS`.
	 * @param Logistics $before      The logistics attendees were last told about.
	 * @param Logistics $after       The logistics now.
	 * @param bool      $show_notice Whether the editor offers to tell attendees.
	 */
	public function __construct(
		public readonly array $keys,
		public readonly Logistics $before,
		public readonly Logistics $after,
		public readonly bool $show_notice = true
	) {}

	/**
	 * Build the change from the post meta `to_meta()` returns.
	 *
	 * @since TBD
	 *
	 * @param mixed $meta The post meta value.
	 *
	 * @return self|null The change, or null when the value holds no change.
	 */
	public static function from_meta( mixed $meta ): ?self {
		if (
			! is_array( $meta )
			|| empty( $meta['keys'] )
			|| ! is_array( $meta['before'] ?? null )
			|| ! is_array( $meta['after'] ?? null )
		) {
			return null;
		}

		return new self(
			array_values( array_map( 'strval', (array) $meta['keys'] ) ),
			Logistics::from_array( $meta['before'] ),
			Logistics::from_array( $meta['after'] ),
			! empty( $meta['show_notice'] )
		);
	}

	/**
	 * Get the change as an array, to store in post meta.
	 *
	 * @since TBD
	 *
	 * @return array<string, mixed> The keys, the logistics before and after, and whether to show the notice.
	 */
	public function to_meta(): array {
		return array(
			'keys'        => $this->keys,
			'before'      => $this->before->to_array(),
			'after'       => $this->after->to_array(),
			'show_notice' => $this->show_notice,
		);
	}

	/**
	 * Whether the change includes the date, the time, or the time zone.
	 *
	 * @since TBD
	 *
	 * @return bool True when a date key changed.
	 */
	public function has_datetime_change(): bool {
		return ! empty( array_intersect( $this->keys, self::DATETIME_KEYS ) );
	}

	/**
	 * One sentence that says what changed.
	 *
	 * @since TBD
	 *
	 * @return string The sentence.
	 */
	public function headline(): string {
		if ( 1 < count( $this->keys ) ) {
			return __( 'Several event details changed.', 'gatherpress' );
		}

		return match ( $this->keys[0] ?? '' ) {
			'datetime_start' => __( 'The start date or time changed.', 'gatherpress' ),
			'datetime_end'   => __( 'The end date or time changed.', 'gatherpress' ),
			'timezone'       => __( 'The time zone changed.', 'gatherpress' ),
			'venue'          => __( 'The venue changed.', 'gatherpress' ),
			default          => sprintf(
				/* translators: %s: Event status, for example "Canceled". */
				__( 'The event status changed to %s.', 'gatherpress' ),
				Status::label( $this->after->status[0] ?? '' )
			),
		};
	}

	/**
	 * The message that says what changed, with the old and the new value.
	 *
	 * @since TBD
	 *
	 * @return string The message, one change per line.
	 */
	public function message(): string {
		$before = $this->before;
		$after  = $this->after;
		$lines  = array();

		foreach ( $this->keys as $key ) {
			$lines[] = match ( $key ) {
				'datetime_start' => sprintf(
					/* translators: 1: Old start date and time. 2: New start date and time. */
					__( 'Start: was %1$s, now %2$s.', 'gatherpress' ),
					$this->format_datetime( $before->datetime_start, $before->timezone ),
					$this->format_datetime( $after->datetime_start, $after->timezone )
				),
				'datetime_end'   => sprintf(
					/* translators: 1: Old end date and time. 2: New end date and time. */
					__( 'End: was %1$s, now %2$s.', 'gatherpress' ),
					$this->format_datetime( $before->datetime_end, $before->timezone ),
					$this->format_datetime( $after->datetime_end, $after->timezone )
				),
				'timezone'       => sprintf(
					/* translators: 1: Old time zone. 2: New time zone. */
					__( 'Time zone: was %1$s, now %2$s.', 'gatherpress' ),
					$before->timezone,
					$after->timezone
				),
				'venue'          => sprintf(
					/* translators: 1: Old venue. 2: New venue. */
					__( 'Venue: was %1$s, now %2$s.', 'gatherpress' ),
					$this->format_venue( $before->venue ),
					$this->format_venue( $after->venue )
				),
				default          => sprintf(
					/* translators: 1: Old event status. 2: New event status. */
					__( 'Status: was %1$s, now %2$s.', 'gatherpress' ),
					Status::label( $before->status[0] ?? '' ),
					Status::label( $after->status[0] ?? '' )
				),
			};
		}

		return implode( "\n", $lines );
	}

	/**
	 * Format a stored local date and time in the site's formats.
	 *
	 * @since TBD
	 *
	 * @param string $datetime The local date and time, `Y-m-d H:i:s`.
	 * @param string $timezone The time zone it is in.
	 *
	 * @return string The formatted date and time, or "not set".
	 */
	private function format_datetime( string $datetime, string $timezone ): string {
		if ( '' === $datetime ) {
			return __( 'not set', 'gatherpress' );
		}

		try {
			$timezone = '' === $timezone ? wp_timezone_string() : $timezone;
			$zone     = new DateTimeZone( Utility::normalize_timezone_string( $timezone ) );
			$date     = new DateTimeImmutable( $datetime, $zone );
		} catch ( Exception ) {
			return $datetime;
		}

		return (string) wp_date(
			sprintf( '%s %s T', get_option( 'date_format' ), get_option( 'time_format' ) ),
			$date->getTimestamp(),
			$zone
		);
	}

	/**
	 * Format the venue terms of an event.
	 *
	 * @since TBD
	 *
	 * @param array<string, string> $venue Term names keyed by slug.
	 *
	 * @return string The names, or "no venue".
	 */
	private function format_venue( array $venue ): string {
		if ( empty( $venue ) ) {
			return __( 'no venue', 'gatherpress' );
		}

		// Term names are stored with entities, and the message is plain text.
		return html_entity_decode( implode( ', ', $venue ), ENT_QUOTES, 'UTF-8' );
	}
}
