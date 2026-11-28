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
 * The one place callers ask for an event's dates, through `Event::occurrences()`.
 * Every event is a series of one today, read from its saved dates. Repeat
 * rules plug in later through the filters here, so callers don't change when
 * they do.
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
	 * The widest UTC offset a timezone can have, in hours.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_UTC_OFFSET_HOURS = 14;

	/**
	 * The event's one occurrence without repeat rules, once worked out.
	 *
	 * False until it is, and null if the event has no start.
	 *
	 * @since TBD
	 * @var Occurrence|false|null
	 */
	protected Occurrence|false|null $single = false;

	/**
	 * Build the occurrences of an event.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 */
	public function __construct( protected readonly Event $event ) {}

	/**
	 * The key of an occurrence within its series.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $start   The occurrence's start, in the event's timezone.
	 * @param bool              $is_all_day Whether the occurrence lasts all day.
	 *
	 * @return string The key, such as `20261211T200000`, or `20261211` for an all-day occurrence.
	 */
	public static function recurrence_id( DateTimeInterface $start, bool $is_all_day = false ): string {
		return $start->format( $is_all_day ? self::ALL_DAY_KEY_FORMAT : self::KEY_FORMAT );
	}

	/**
	 * The event's occurrences that overlap a range, in start order.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $from Start of the range.
	 * @param DateTimeInterface $to   End of the range, exclusive.
	 *
	 * @return Occurrence[] The occurrences.
	 */
	public function in_range( DateTimeInterface $from, DateTimeInterface $to ): array {
		$single      = $this->single();
		$occurrences = ( $single && $single->start < $to && $single->end >= $from ) ? array( $single ) : array();

		/**
		 * Lets repeat rules supply an event's dates within a range.
		 *
		 * Calendars, lists and feeds ask for dates through here, so a plugin that
		 * expands repeat rules returns each date in the range as an occurrence.
		 *
		 * @since TBD
		 *
		 * @param Occurrence[]      $occurrences The occurrences. Without repeat rules, the event's one date
		 *                                       if it overlaps the range.
		 * @param int               $post_id     The event post ID.
		 * @param DateTimeInterface $from        Start of the range.
		 * @param DateTimeInterface $to          End of the range, exclusive.
		 *
		 * @return Occurrence[] The occurrences that overlap the range.
		 */
		$occurrences = apply_filters( 'gatherpress_event_occurrences', $occurrences, $this->post_id(), $from, $to );

		usort(
			$occurrences,
			static function ( Occurrence $a, Occurrence $b ): int {
				return $a->start <=> $b->start;
			}
		);

		return $occurrences;
	}

	/**
	 * One of the event's occurrences, by its key.
	 *
	 * @since TBD
	 *
	 * @param string $recurrence_id The occurrence's key.
	 *
	 * @return Occurrence|null The occurrence, or null if the event has none with that key.
	 */
	public function find( string $recurrence_id ): ?Occurrence {
		$single     = $this->single();
		$occurrence = ( $single && $single->recurrence_id === $recurrence_id ) ? $single : null;

		/**
		 * Lets repeat rules supply the occurrence for a key.
		 *
		 * Per-date pages and RSVPs look a date up by its key, so a plugin that
		 * expands repeat rules returns the occurrence that key names.
		 *
		 * @since TBD
		 *
		 * @param Occurrence|null $occurrence    The occurrence. Without repeat rules, the event's one date
		 *                                       if its key matches.
		 * @param int             $post_id       The event post ID.
		 * @param string          $recurrence_id The occurrence's key.
		 *
		 * @return Occurrence|null The occurrence, or null if the event has none with that key.
		 */
		$occurrence = apply_filters(
			'gatherpress_event_occurrence_by_key',
			$occurrence,
			$this->post_id(),
			$recurrence_id
		);

		return $occurrence instanceof Occurrence ? $occurrence : null;
	}

	/**
	 * The series' primary occurrence, the one it shows by default.
	 *
	 * That is the first occurrence that has not ended, preferring one that is
	 * not canceled, or the last occurrence when all have ended.
	 *
	 * @since TBD
	 *
	 * @return Occurrence|null The occurrence, or null if the event has none.
	 */
	public function primary(): ?Occurrence {
		$occurrence = $this->single();

		/**
		 * Lets repeat rules pick a series' primary occurrence.
		 *
		 * The event page and lists show this date for a series, so a plugin that
		 * expands repeat rules returns the next date that has not ended.
		 *
		 * @since TBD
		 *
		 * @param Occurrence|null $occurrence The occurrence. Without repeat rules, the event's one date.
		 * @param int             $post_id    The event post ID.
		 *
		 * @return Occurrence|null The primary occurrence, or null if the event has none.
		 */
		$occurrence = apply_filters( 'gatherpress_event_primary_occurrence', $occurrence, $this->post_id() );

		return $occurrence instanceof Occurrence ? $occurrence : null;
	}

	/**
	 * The first of the event's occurrences that starts on a date.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $day The date, as a local date in the event's timezone.
	 *
	 * @return Occurrence|null The occurrence, or null if none starts on that date.
	 */
	public function on_day( DateTimeInterface $day ): ?Occurrence {
		$date   = $day->format( self::ALL_DAY_KEY_FORMAT );
		$utc    = new DateTimeZone( 'UTC' );
		$offset = sprintf( '%d hours', self::MAX_UTC_OFFSET_HOURS );

		// Widen the range by the largest UTC offset so the date is covered in any timezone.
		$from = ( new DateTimeImmutable( $day->format( 'Y-m-d' ), $utc ) )->modify( '-' . $offset );
		$to   = ( new DateTimeImmutable( $day->format( 'Y-m-d' ), $utc ) )->modify( '+1 day +' . $offset );

		foreach ( $this->in_range( $from, $to ) as $occurrence ) {
			// The start, not the key: a moved occurrence keeps its original key.
			if ( $occurrence->start->format( self::ALL_DAY_KEY_FORMAT ) === $date ) {
				return $occurrence;
			}
		}

		return null;
	}

	/**
	 * The event's post ID.
	 *
	 * @since TBD
	 *
	 * @return int The post ID, or 0 if the post is not an event.
	 */
	protected function post_id(): int {
		return $this->event->post ? $this->event->post->ID : 0;
	}

	/**
	 * The event's one occurrence without repeat rules, from its saved dates.
	 *
	 * The key and local times use the event's own timezone, not a viewer's
	 * preference, so the same date always gets the same key.
	 *
	 * @since TBD
	 *
	 * @return Occurrence|null The occurrence, or null if the post is not an event or has no start.
	 *
	 * @throws Exception If a stored datetime cannot be parsed.
	 */
	protected function single(): ?Occurrence {
		if ( false !== $this->single ) {
			return $this->single;
		}

		$this->single = null;
		$datetime     = $this->event->get_datetime();

		if ( ! $this->event->post || empty( $datetime['datetime_start_gmt'] ) ) {
			return null;
		}

		$utc        = new DateTimeZone( 'UTC' );
		$timezone   = new DateTimeZone( Utility::normalize_timezone_string( $this->event->get_timezone() ) );
		$start      = ( new DateTimeImmutable( $datetime['datetime_start_gmt'], $utc ) )->setTimezone( $timezone );
		$end        = empty( $datetime['datetime_end_gmt'] )
			? $start
			: ( new DateTimeImmutable( $datetime['datetime_end_gmt'], $utc ) )->setTimezone( $timezone );
		$is_all_day = $this->event->is_all_day();

		$key          = self::recurrence_id( $start, $is_all_day );
		$status       = ( new Status( $this->post_id() ) )->get();
		$this->single = new Occurrence( $this->post_id(), $key, $start, $end, $is_all_day, $status );

		return $this->single;
	}
}
