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
	 * Most occurrences one range returns.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_OCCURRENCES = 1000;

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
	 * The event's valid repeat rules, once read.
	 *
	 * @since TBD
	 * @var Rule[]|null
	 */
	protected ?array $rules = null;

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
	 * A range with more dates than `MAX_OCCURRENCES`, or than one rule's
	 * `Rule::MAX_DATES`, returns the earliest ones, so callers paging through
	 * a long series should ask for shorter ranges.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $from Start of the range.
	 * @param DateTimeInterface $to   End of the range, exclusive.
	 *
	 * @return Occurrence[] The occurrences.
	 */
	public function in_range( DateTimeInterface $from, DateTimeInterface $to ): array {
		$rules = $this->rules();

		if ( $rules ) {
			$occurrences = array();

			foreach ( $rules as $rule ) {
				$from_rule   = $rule->occurrences( $from, $to, $this->post_id(), $this->status() );
				$occurrences = array_merge( $occurrences, $from_rule );
			}

			$occurrences = array_slice( self::merge( $occurrences ), 0, self::MAX_OCCURRENCES );
		} else {
			$single      = $this->single();
			$occurrences = ( $single && $single->start < $to && $single->end >= $from ) ? array( $single ) : array();
		}

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
		$occurrence = null;
		$rules      = $this->rules();

		foreach ( $rules as $rule ) {
			$occurrence = $rule->find( $recurrence_id, $this->post_id(), $this->status() );

			if ( $occurrence ) {
				break;
			}
		}

		if ( ! $rules ) {
			$single     = $this->single();
			$occurrence = ( $single && $single->recurrence_id === $recurrence_id ) ? $single : null;
		}

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
		$rules      = $this->rules();
		$occurrence = $rules ? $this->primary_from_rules( $rules ) : $this->single();

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
		return $this->event->post->ID ?? 0;
	}

	/**
	 * The primary occurrence of a series with rules.
	 *
	 * The earliest date that hasn't ended across the rules, or the latest one
	 * that has when every date has ended.
	 *
	 * @since TBD
	 *
	 * @param Rule[]                 $rules The event's rules.
	 * @param DateTimeImmutable|null $now   The moment to look from. Defaults to now.
	 *
	 * @return Occurrence|null The occurrence, or null if the rules produce none.
	 *
	 * @throws Exception If a date cannot be built.
	 */
	protected function primary_from_rules( array $rules, ?DateTimeImmutable $now = null ): ?Occurrence {
		$now      = $now ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$upcoming = array();
		$ended    = array();

		foreach ( $rules as $rule ) {
			$upcoming[] = $rule->next_occurrence( $now, $this->post_id(), $this->status() );
			$ended[]    = $rule->previous_occurrence( $now, $this->post_id(), $this->status() );
		}

		$upcoming = self::merge( array_filter( $upcoming ) );

		if ( $upcoming ) {
			return $upcoming[0];
		}

		$ended = self::merge( array_filter( $ended ) );

		return $ended ? end( $ended ) : null;
	}

	/**
	 * Sort occurrences by start and count one that two rules both produce once.
	 *
	 * Occurrences with the same key and the same start are the same date. The
	 * same key with different starts, from rules in different timezones, are
	 * two dates, so both are kept.
	 *
	 * @since TBD
	 *
	 * @param Occurrence[] $occurrences The occurrences.
	 *
	 * @return Occurrence[] The occurrences in start order.
	 */
	protected static function merge( array $occurrences ): array {
		$unique = array();

		foreach ( $occurrences as $occurrence ) {
			$unique[ $occurrence->recurrence_id . '|' . $occurrence->start->getTimestamp() ] ??= $occurrence;
		}

		$unique = array_values( $unique );

		usort(
			$unique,
			static function ( Occurrence $a, Occurrence $b ): int {
				return $a->start <=> $b->start;
			}
		);

		return $unique;
	}

	/**
	 * The event's valid repeat rules, read once per instance.
	 *
	 * @since TBD
	 *
	 * @return Rule[] The rules, empty when the event doesn't repeat.
	 */
	protected function rules(): array {
		$this->rules ??= $this->event->rules()->all();

		return $this->rules;
	}

	/**
	 * The status every occurrence takes for now: the event's status.
	 *
	 * @since TBD
	 *
	 * @return string The event status slug.
	 */
	protected function status(): string {
		return ( new Status( $this->post_id() ) )->get();
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
		$status       = $this->status();
		$this->single = new Occurrence( $this->post_id(), $key, $start, $end, $is_all_day, $status );

		return $this->single;
	}
}
