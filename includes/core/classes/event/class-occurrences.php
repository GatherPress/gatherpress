<?php
/**
 * Works out the dates of an event.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Utility;

/**
 * Class Occurrences.
 *
 * The one place callers ask for an event's dates. Every event is a series of
 * one today, read from the events table. Repeat rules plug in later through
 * the filters here, so callers don't change when they do.
 *
 * @since TBD
 */
final class Occurrences {
	/**
	 * Key format for an occurrence with a time: its local start, as an
	 * RFC 5545 `RECURRENCE-ID`.
	 *
	 * @since TBD
	 * @var string
	 */
	const KEY_FORMAT = 'Ymd\THis';

	/**
	 * Key format for an all-day occurrence: its local date.
	 *
	 * @since TBD
	 * @var string
	 */
	const ALL_DAY_KEY_FORMAT = 'Ymd';

	/**
	 * The key of an occurrence within its series.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $start   The occurrence's start, in the event's timezone.
	 * @param bool              $all_day Whether the occurrence lasts all day.
	 *
	 * @return string The key, such as `20261211T200000`, or `20261211` for an all-day occurrence.
	 */
	public static function recurrence_id( DateTimeInterface $start, bool $all_day = false ): string {
		return $start->format( $all_day ? self::ALL_DAY_KEY_FORMAT : self::KEY_FORMAT );
	}

	/**
	 * An event's occurrences that overlap a range, in start order.
	 *
	 * @since TBD
	 *
	 * @param int               $post_id The event post ID.
	 * @param DateTimeInterface $from    Start of the range.
	 * @param DateTimeInterface $to      End of the range, exclusive.
	 *
	 * @return Occurrence[] The occurrences.
	 */
	public static function in_range( int $post_id, DateTimeInterface $from, DateTimeInterface $to ): array {
		$single      = self::single( $post_id );
		$occurrences = ( $single && $single->start < $to && $single->end >= $from ) ? array( $single ) : array();

		/**
		 * Filters an event's occurrences within a range.
		 *
		 * @since TBD
		 *
		 * @param Occurrence[]      $occurrences The occurrences. Without repeat rules, the event's one date
		 *                                       if it overlaps the range.
		 * @param int               $post_id     The event post ID.
		 * @param DateTimeInterface $from        Start of the range.
		 * @param DateTimeInterface $to          End of the range, exclusive.
		 */
		$occurrences = apply_filters( 'gatherpress_event_occurrences', $occurrences, $post_id, $from, $to );

		usort(
			$occurrences,
			static function ( Occurrence $a, Occurrence $b ): int {
				return $a->start <=> $b->start;
			}
		);

		return $occurrences;
	}

	/**
	 * One occurrence of an event, by its key.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id       The event post ID.
	 * @param string $recurrence_id The occurrence's key.
	 *
	 * @return Occurrence|null The occurrence, or null if the event has none with that key.
	 */
	public static function find( int $post_id, string $recurrence_id ): ?Occurrence {
		$single     = self::single( $post_id );
		$occurrence = ( $single && $single->recurrence_id === $recurrence_id ) ? $single : null;

		/**
		 * Filters the occurrence found for a key.
		 *
		 * @since TBD
		 *
		 * @param Occurrence|null $occurrence    The occurrence. Without repeat rules, the event's one date
		 *                                       if its key matches.
		 * @param int             $post_id       The event post ID.
		 * @param string          $recurrence_id The occurrence's key.
		 */
		$occurrence = apply_filters( 'gatherpress_event_occurrence', $occurrence, $post_id, $recurrence_id );

		return $occurrence instanceof Occurrence ? $occurrence : null;
	}

	/**
	 * The one occurrence of an event without repeat rules, from the events table.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return Occurrence|null The occurrence, or null if the post is not an event or has no start.
	 *
	 * @throws Exception If a stored datetime cannot be parsed.
	 */
	protected static function single( int $post_id ): ?Occurrence {
		$event = new Event( $post_id );

		if ( ! $event->post ) {
			return null;
		}

		$datetime = $event->get_datetime();

		if ( empty( $datetime['datetime_start_gmt'] ) ) {
			return null;
		}

		$utc      = new DateTimeZone( 'UTC' );
		$timezone = new DateTimeZone( Utility::normalize_timezone_string( (string) $datetime['timezone'] ) );
		$start    = ( new DateTimeImmutable( $datetime['datetime_start_gmt'], $utc ) )->setTimezone( $timezone );
		$end      = empty( $datetime['datetime_end_gmt'] )
			? $start
			: ( new DateTimeImmutable( $datetime['datetime_end_gmt'], $utc ) )->setTimezone( $timezone );
		$all_day  = $event->is_all_day();

		return new Occurrence( $post_id, self::recurrence_id( $start, $all_day ), $start, $end, $all_day );
	}
}
