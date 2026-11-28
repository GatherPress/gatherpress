<?php
/**
 * One repeat rule of an event.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Generator;
use GatherPress\Core\Utility;
use GatherPress\Core\Validate;

/**
 * Class Rule.
 *
 * One row of an event's `gatherpress_rule` meta: a start date, an optional end
 * date, a date pattern from a strict subset of RFC 5545 `RRULE`, the times of
 * day, a timezone and a duration. It turns the pattern into occurrences.
 *
 * Calendar arithmetic runs on dates at midnight UTC, which has no daylight
 * saving, and each occurrence is built from its own local date and time, so a
 * clock change on one date never shifts the dates after it.
 *
 * @since TBD
 *
 * @phpstan-type RulePattern array{
 *     frequency: ?string,
 *     interval: int,
 *     weekdays: string[],
 *     ordinal_weekday: ?array{0: int, 1: string},
 *     month_day: ?int
 * }
 */
final class Rule {

	/**
	 * Date format for local dates in a rule.
	 *
	 * @since TBD
	 * @var string
	 */
	const DATE_FORMAT = 'Y-m-d';

	/**
	 * Years after which every Gregorian calendar pattern repeats.
	 *
	 * Searches for a pattern date stop after this span, so a pattern that can
	 * never match, such as the 30th of February, can't loop forever.
	 *
	 * @since TBD
	 * @var int
	 */
	const CYCLE_YEARS = 400;

	/**
	 * Most dates a single expansion returns.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_DATES = 1000;

	/**
	 * Longest an occurrence can last, in minutes: 366 days.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_DURATION = 527040;

	/**
	 * Largest `INTERVAL` a rule accepts.
	 *
	 * Keeps period arithmetic within integers: a huge interval would push a
	 * date past what PHP can represent.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_INTERVAL = 1000;

	/**
	 * The date pattern: `DAILY`, `WEEKLY`, `MONTHLY`, `YEARLY`, or null for one date.
	 *
	 * @since TBD
	 * @var string|null
	 */
	public readonly ?string $frequency;

	/**
	 * How many periods apart the dates are.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $interval;

	/**
	 * For `WEEKLY`, the weekdays (`MO` to `SU`), in week order.
	 *
	 * @since TBD
	 * @var string[]
	 */
	public readonly array $weekdays;

	/**
	 * For `MONTHLY`, an ordinal (1 to 4, or -1 for the last) and a weekday, such as `array( 2, 'FR' )`.
	 *
	 * @since TBD
	 * @var array{0: int, 1: string}|null
	 */
	public readonly ?array $ordinal_weekday;

	/**
	 * For `MONTHLY`, the day of the month.
	 *
	 * @since TBD
	 * @var int|null
	 */
	public readonly ?int $month_day;

	/**
	 * Build a rule. Use `from_meta()` to read one from a meta row.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable      $starts_on  The first date, at midnight UTC.
	 * @param DateTimeImmutable|null $ends_on    The last date it can produce, at midnight UTC, or null.
	 * @param array<string, mixed>   $pattern    The parsed date pattern, with its defaults filled in.
	 * @param bool                   $is_all_day Whether each occurrence lasts all day.
	 * @param string[]               $times      The local times of day, as `H:i`.
	 * @param DateTimeZone           $timezone   The timezone the dates and times are in.
	 * @param int                    $duration   Minutes, or whole days of 1440 minutes when all day.
	 * @param string                 $change_id  The schedule change that created the rule.
	 * @phpstan-param RulePattern $pattern
	 */
	public function __construct(
		public readonly DateTimeImmutable $starts_on,
		public readonly ?DateTimeImmutable $ends_on,
		array $pattern,
		public readonly bool $is_all_day,
		public readonly array $times,
		public readonly DateTimeZone $timezone,
		public readonly int $duration,
		public readonly string $change_id = ''
	) {
		$this->frequency       = $pattern['frequency'];
		$this->interval        = $pattern['interval'];
		$this->weekdays        = $pattern['weekdays'];
		$this->ordinal_weekday = $pattern['ordinal_weekday'];
		$this->month_day       = $pattern['month_day'];
	}

	/**
	 * Read a rule from one row of `gatherpress_rule` meta.
	 *
	 * @since TBD
	 *
	 * @param mixed $row The meta row.
	 *
	 * @return Rule|null The rule, or null if the row is not a valid rule.
	 */
	public static function from_meta( mixed $row ): ?Rule {
		$fields = is_array( $row ) ? self::parse_row( $row ) : null;

		if ( null === $fields ) {
			return null;
		}

		$rule = new self(
			$fields['starts_on'],
			$fields['ends_on'],
			self::with_defaults( $fields['pattern'], $fields['starts_on'] ),
			$fields['is_all_day'],
			$fields['times'],
			$fields['timezone'],
			$fields['duration'],
			is_string( $row['change_id'] ?? null ) ? $row['change_id'] : ''
		);

		return $rule->can_repeat() ? $rule : null;
	}

	/**
	 * The rule's pattern in canonical `RRULE` form, or null for a single date.
	 *
	 * Parts come in a fixed order, so two rules can be compared by this value.
	 *
	 * @since TBD
	 *
	 * @return string|null The pattern, such as `FREQ=MONTHLY;BYDAY=2FR`.
	 */
	public function rrule(): ?string {
		if ( null === $this->frequency ) {
			return null;
		}

		$parts = array( 'FREQ=' . $this->frequency );

		if ( 1 < $this->interval ) {
			$parts[] = 'INTERVAL=' . $this->interval;
		}

		if ( $this->weekdays ) {
			$parts[] = 'BYDAY=' . implode( ',', $this->weekdays );
		} elseif ( $this->ordinal_weekday ) {
			$parts[] = 'BYDAY=' . $this->ordinal_weekday[0] . $this->ordinal_weekday[1];
		} elseif ( null !== $this->month_day ) {
			$parts[] = 'BYMONTHDAY=' . $this->month_day;
		}

		return implode( ';', $parts );
	}

	/**
	 * The occurrences that overlap a range, in start order.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $from    Start of the range.
	 * @param DateTimeInterface $to      End of the range, exclusive.
	 * @param int               $post_id The event post ID.
	 * @param string            $status  The event status slug each occurrence takes.
	 *
	 * @return Occurrence[] The occurrences.
	 *
	 * @throws Exception If a date cannot be built.
	 */
	public function occurrences( DateTimeInterface $from, DateTimeInterface $to, int $post_id, string $status ): array {
		$occurrences = array();
		$last        = $this->local_date( $to );
		// An occurrence that started before the range can still overlap it.
		$first = $this->local_date( $from )->modify( sprintf( '-%d days', $this->span_days() ) );

		foreach ( $this->dates_from( $first ) as $date ) {
			if ( $date > $last ) {
				break;
			}

			foreach ( $this->occurrences_on( $date, $post_id, $status ) as $occurrence ) {
				if ( $occurrence->start < $to && $occurrence->end >= $from ) {
					$occurrences[] = $occurrence;
				}
			}
		}

		return $occurrences;
	}

	/**
	 * The occurrence a key names, or null if the rule doesn't produce it.
	 *
	 * @since TBD
	 *
	 * @param string $recurrence_id The key, `Ymd\THis` or `Ymd` when all day.
	 * @param int    $post_id       The event post ID.
	 * @param string $status        The event status slug the occurrence takes.
	 *
	 * @return Occurrence|null The occurrence.
	 *
	 * @throws Exception If a date cannot be built.
	 */
	public function find( string $recurrence_id, int $post_id, string $status ): ?Occurrence {
		$utc  = new DateTimeZone( 'UTC' );
		$date = DateTimeImmutable::createFromFormat( '!Ymd', substr( $recurrence_id, 0, 8 ), $utc );
		$day  = $date ? $date->format( self::DATE_FORMAT ) : '';

		if ( ! $date || $this->next_date( $date )?->format( self::DATE_FORMAT ) !== $day ) {
			return null;
		}

		foreach ( $this->occurrences_on( $date, $post_id, $status ) as $occurrence ) {
			if ( $occurrence->recurrence_id === $recurrence_id ) {
				return $occurrence;
			}
		}

		return null;
	}

	/**
	 * The first occurrence that hasn't ended by a moment.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $now     The moment.
	 * @param int               $post_id The event post ID.
	 * @param string            $status  The event status slug the occurrence takes.
	 *
	 * @return Occurrence|null The occurrence, or null if every occurrence has ended.
	 *
	 * @throws Exception If a date cannot be built.
	 */
	public function next_occurrence( DateTimeInterface $now, int $post_id, string $status ): ?Occurrence {
		$first = $this->local_date( $now )->modify( sprintf( '-%d days', $this->span_days() ) );

		foreach ( $this->dates_from( $first ) as $date ) {
			foreach ( $this->occurrences_on( $date, $post_id, $status ) as $occurrence ) {
				if ( $occurrence->end >= $now ) {
					return $occurrence;
				}
			}
		}

		return null;
	}

	/**
	 * The last occurrence that ended before a moment.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $now     The moment.
	 * @param int               $post_id The event post ID.
	 * @param string            $status  The event status slug the occurrence takes.
	 *
	 * @return Occurrence|null The occurrence, or null if none has ended.
	 *
	 * @throws Exception If a date cannot be built.
	 */
	public function previous_occurrence( DateTimeInterface $now, int $post_id, string $status ): ?Occurrence {
		foreach ( $this->dates_before( $this->local_date( $now ) ) as $date ) {
			foreach ( array_reverse( $this->occurrences_on( $date, $post_id, $status ) ) as $occurrence ) {
				if ( $occurrence->end < $now ) {
					return $occurrence;
				}
			}
		}

		return null;
	}

	/**
	 * The first date the rule produces on or after a date.
	 *
	 * The start date always counts, as RFC 5545 counts `DTSTART`, and the end
	 * date is the last that can.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date A date at midnight UTC.
	 *
	 * @return DateTimeImmutable|null The date at midnight UTC, or null if there is none.
	 */
	public function next_date( DateTimeImmutable $date ): ?DateTimeImmutable {
		if ( $date <= $this->starts_on ) {
			$next = $this->starts_on;
		} else {
			$next = $this->next_pattern_date( $date );
		}

		return ( $next && ( ! $this->ends_on || $next <= $this->ends_on ) ) ? $next : null;
	}

	/**
	 * The last date the rule produces on or before a date.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date A date at midnight UTC.
	 *
	 * @return DateTimeImmutable|null The date at midnight UTC, or null if there is none.
	 */
	public function previous_date( DateTimeImmutable $date ): ?DateTimeImmutable {
		if ( $this->ends_on && $date > $this->ends_on ) {
			$date = $this->ends_on;
		}

		if ( $date < $this->starts_on ) {
			return null;
		}

		$previous = $this->previous_pattern_date( $date );

		return ( $previous && $previous > $this->starts_on ) ? $previous : $this->starts_on;
	}

	/**
	 * The rule's dates from a date on, at most `MAX_DATES` of them.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date A date at midnight UTC.
	 *
	 * @return Generator<int, DateTimeImmutable> The dates at midnight UTC, in order.
	 */
	protected function dates_from( DateTimeImmutable $date ): Generator {
		for ( $i = 0; $i < self::MAX_DATES; $i++ ) {
			$date = $this->next_date( $date );

			if ( ! $date ) {
				return;
			}

			yield $date;

			$date = $date->modify( '+1 day' );
		}
	}

	/**
	 * The rule's dates from a date back, at most `MAX_DATES` of them.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date A date at midnight UTC.
	 *
	 * @return Generator<int, DateTimeImmutable> The dates at midnight UTC, latest first.
	 */
	protected function dates_before( DateTimeImmutable $date ): Generator {
		for ( $i = 0; $i < self::MAX_DATES; $i++ ) {
			$date = $this->previous_date( $date );

			if ( ! $date ) {
				return;
			}

			yield $date;

			$date = $date->modify( '-1 day' );
		}
	}

	/**
	 * Whether the pattern produces a date after the start date.
	 *
	 * A rule without a pattern is one date and always valid. One whose pattern
	 * finds nothing within a Gregorian cycle never will.
	 *
	 * @since TBD
	 *
	 * @return bool True when the rule is usable.
	 */
	protected function can_repeat(): bool {
		return null === $this->frequency || null !== $this->next_pattern_date( $this->starts_on->modify( '+1 day' ) );
	}

	/**
	 * The occurrences on one local date, in start order.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date    The date at midnight UTC.
	 * @param int               $post_id The event post ID.
	 * @param string            $status  The event status slug the occurrences take.
	 *
	 * @return Occurrence[] One per time of day, or one when all day.
	 *
	 * @throws Exception If a date cannot be built.
	 */
	protected function occurrences_on( DateTimeImmutable $date, int $post_id, string $status ): array {
		$day = $date->format( self::DATE_FORMAT );

		if ( $this->is_all_day ) {
			$start = new DateTimeImmutable( $day . ' 00:00:00', $this->timezone );
			$last  = $date->modify( sprintf( '+%d days', $this->span_days() - 1 ) )->format( self::DATE_FORMAT );
			$end   = new DateTimeImmutable( $last . ' 23:59:59', $this->timezone );

			return array(
				new Occurrence( $post_id, Occurrences::recurrence_id( $start, true ), $start, $end, true, $status ),
			);
		}

		$occurrences = array();

		foreach ( $this->times as $time ) {
			// Built from the local date and time, so PHP moves a time in a
			// spring-forward gap to after it and picks the first of a repeated hour.
			// Adding an interval counts elapsed time across a clock change, where
			// modify() would count wall-clock minutes. A spring-forward gap can
			// put two showtimes at one instant with one key; merging counts it once.
			$start         = new DateTimeImmutable( $day . ' ' . $time, $this->timezone );
			$end           = $start->add( new DateInterval( sprintf( 'PT%dM', $this->duration ) ) );
			$key           = Occurrences::recurrence_id( $start );
			$occurrences[] = new Occurrence( $post_id, $key, $start, $end, false, $status );
		}

		return $occurrences;
	}

	/**
	 * How many days an occurrence can reach back across, at least one.
	 *
	 * @since TBD
	 *
	 * @return int The days.
	 */
	protected function span_days(): int {
		return max( 1, (int) ceil( $this->duration / 1440 ) );
	}

	/**
	 * A moment's local date in the rule's timezone, at midnight UTC.
	 *
	 * @since TBD
	 *
	 * @param DateTimeInterface $moment The moment.
	 *
	 * @return DateTimeImmutable The date.
	 */
	protected function local_date( DateTimeInterface $moment ): DateTimeImmutable {
		$local = DateTimeImmutable::createFromInterface( $moment )->setTimezone( $this->timezone );

		return self::utc_date( $local->format( self::DATE_FORMAT ) );
	}

	/**
	 * The first pattern date on or after a date, ignoring the start and end dates.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date A date at midnight UTC, on or after the start date.
	 *
	 * @return DateTimeImmutable|null The date, or null if none falls within a Gregorian cycle.
	 */
	protected function next_pattern_date( DateTimeImmutable $date ): ?DateTimeImmutable {
		if ( null === $this->frequency ) {
			return null;
		}

		$period = $this->period_of( $date );
		$period = $period + ( ( $this->interval - ( $period % $this->interval ) ) % $this->interval );
		$limit  = $this->period_limit();

		for ( $i = 0; $i <= $limit; $i++, $period += $this->interval ) {
			foreach ( $this->dates_in_period( $period ) as $candidate ) {
				if ( $candidate >= $date ) {
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * The last pattern date on or before a date, ignoring the end date.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date A date at midnight UTC, on or after the start date.
	 *
	 * @return DateTimeImmutable|null The date, or null if the pattern has none since the start date.
	 */
	protected function previous_pattern_date( DateTimeImmutable $date ): ?DateTimeImmutable {
		if ( null === $this->frequency ) {
			return null;
		}

		$period = $this->period_of( $date );
		$period = $period - ( $period % $this->interval );
		$limit  = $this->period_limit();

		for ( $i = 0; $i <= $limit && 0 <= $period; $i++, $period -= $this->interval ) {
			foreach ( array_reverse( $this->dates_in_period( $period ) ) as $candidate ) {
				if ( $candidate <= $date ) {
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * Which period a date falls in, counted from the start date's period.
	 *
	 * Weeks start on Monday.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date A date at midnight UTC.
	 *
	 * @return int The period, zero for the start date's.
	 */
	protected function period_of( DateTimeImmutable $date ): int {
		$start = $this->starts_on;

		return match ( $this->frequency ) {
			'DAILY'   => (int) $start->diff( $date )->format( '%r%a' ),
			'WEEKLY'  => intdiv( (int) self::monday( $start )->diff( self::monday( $date ) )->format( '%r%a' ), 7 ),
			'MONTHLY' => ( (int) $date->format( 'Y' ) - (int) $start->format( 'Y' ) ) * 12
				+ (int) $date->format( 'n' ) - (int) $start->format( 'n' ),
			default   => (int) $date->format( 'Y' ) - (int) $start->format( 'Y' ),
		};
	}

	/**
	 * How many periods a Gregorian cycle spans, divided by the interval.
	 *
	 * @since TBD
	 *
	 * @return int The number of periods to search.
	 */
	protected function period_limit(): int {
		$per_year = match ( $this->frequency ) {
			'DAILY'   => 366,
			'WEEKLY'  => 53,
			'MONTHLY' => 12,
			default   => 1,
		};

		return intdiv( self::CYCLE_YEARS * $per_year, $this->interval ) + 1;
	}

	/**
	 * The pattern's dates within one period, in order.
	 *
	 * @since TBD
	 *
	 * @param int $period The period, counted from the start date's.
	 *
	 * @return DateTimeImmutable[] The dates at midnight UTC.
	 */
	protected function dates_in_period( int $period ): array {
		$start = $this->starts_on;

		switch ( $this->frequency ) {
			case 'DAILY':
				return array( $start->modify( sprintf( '%+d days', $period ) ) );

			case 'WEEKLY':
				$monday = self::monday( $start )->modify( sprintf( '%+d weeks', $period ) );
				$dates  = array();

				foreach ( Rules::WEEKDAYS as $offset => $weekday ) {
					if ( in_array( $weekday, $this->weekdays, true ) ) {
						$dates[] = $monday->modify( sprintf( '+%d days', $offset ) );
					}
				}

				return $dates;

			case 'MONTHLY':
				$first = $start->modify( 'first day of this month' )->modify( sprintf( '%+d months', $period ) );

				if ( $this->ordinal_weekday ) {
					list( $ordinal, $weekday ) = $this->ordinal_weekday;

					return array( self::ordinal_weekday_in( $first, $ordinal, $weekday ) );
				}

				$year  = (int) $first->format( 'Y' );
				$month = (int) $first->format( 'n' );

				return checkdate( $month, (int) $this->month_day, $year )
					? array( $first->setDate( $year, $month, (int) $this->month_day ) )
					: array();

			default:
				$year = (int) $start->format( 'Y' ) + $period;

				return checkdate( (int) $start->format( 'n' ), (int) $start->format( 'j' ), $year )
					? array( $start->setDate( $year, (int) $start->format( 'n' ), (int) $start->format( 'j' ) ) )
					: array();
		}
	}

	/**
	 * The nth weekday of a month, or the last when the ordinal is -1.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $first   The first day of the month, at midnight UTC.
	 * @param int               $ordinal 1 to 4, or -1.
	 * @param string            $weekday `MO` to `SU`.
	 *
	 * @return DateTimeImmutable The date. Every month has a 4th of each weekday by the 28th.
	 */
	protected static function ordinal_weekday_in(
		DateTimeImmutable $first,
		int $ordinal,
		string $weekday
	): DateTimeImmutable {
		$offset = (int) array_search( $weekday, Rules::WEEKDAYS, true );

		if ( -1 === $ordinal ) {
			$last = $first->modify( 'last day of this month' );
			$back = ( (int) $last->format( 'N' ) - 1 - $offset + 7 ) % 7;

			return $last->modify( sprintf( '-%d days', $back ) );
		}

		$ahead = ( $offset - ( (int) $first->format( 'N' ) - 1 ) + 7 ) % 7;

		return $first->modify( sprintf( '+%d days', $ahead + 7 * ( $ordinal - 1 ) ) );
	}

	/**
	 * Validate and parse the fields of a meta row.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $row The meta row.
	 *
	 * @return array<string, mixed>|null The parsed fields, or null if any is invalid.
	 * @phpstan-return array{
	 *     starts_on: DateTimeImmutable,
	 *     ends_on: ?DateTimeImmutable,
	 *     pattern: RulePattern,
	 *     is_all_day: bool,
	 *     times: string[],
	 *     timezone: DateTimeZone,
	 *     duration: int
	 * }|null
	 */
	protected static function parse_row( array $row ): ?array {
		$starts_on = self::parse_date( $row['starts_on'] ?? null );
		$has_end   = isset( $row['ends_on'] );
		$ends_on   = $has_end ? self::parse_date( $row['ends_on'] ) : null;
		$timezone  = is_string( $row['timezone'] ?? null ) ? $row['timezone'] : '';
		$pattern   = self::parse_rrule( $row['rrule'] ?? null );
		$times     = self::parse_times( $row['times'] ?? array() );

		if (
			! $starts_on
			|| ( $has_end && ( ! $ends_on || $ends_on < $starts_on ) )
			|| ! Validate::timezone( $timezone )
			|| null === $pattern
			|| null === $times
		) {
			return null;
		}

		$is_all_day = ! empty( $row['is_all_day'] );
		$duration   = is_numeric( $row['duration'] ?? null ) ? (int) $row['duration'] : -1;

		if ( ! self::is_valid_length( $is_all_day, $times, $duration ) ) {
			return null;
		}

		return array(
			'starts_on'  => $starts_on,
			'ends_on'    => $ends_on,
			'pattern'    => $pattern,
			'is_all_day' => $is_all_day,
			'times'      => $is_all_day ? array() : $times,
			'timezone'   => new DateTimeZone( Utility::normalize_timezone_string( $timezone ) ),
			'duration'   => $duration,
		);
	}

	/**
	 * Whether the times and duration fit the kind of rule.
	 *
	 * A timed rule needs at least one time and a duration of zero or more
	 * minutes. An all-day rule lasts a whole number of days. Neither lasts
	 * longer than `MAX_DURATION`, so a lookup never reaches back years.
	 *
	 * @since TBD
	 *
	 * @param bool     $is_all_day Whether the rule is all day.
	 * @param string[] $times      The times of day.
	 * @param int      $duration   The duration in minutes, -1 when missing.
	 *
	 * @return bool True when they fit.
	 */
	protected static function is_valid_length( bool $is_all_day, array $times, int $duration ): bool {
		if ( self::MAX_DURATION < $duration ) {
			return false;
		}

		if ( $is_all_day ) {
			return 1440 <= $duration && 0 === $duration % 1440;
		}

		return ! empty( $times ) && 0 <= $duration;
	}

	/**
	 * Fill in the parts a pattern leaves out from the start date, as RFC 5545 does.
	 *
	 * A weekly pattern repeats on the start date's weekday, and a monthly one
	 * on its day of the month.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $pattern   The parsed pattern.
	 * @param DateTimeImmutable    $starts_on The start date.
	 * @phpstan-param RulePattern $pattern
	 *
	 * @return array<string, mixed> The pattern with its defaults.
	 * @phpstan-return RulePattern
	 */
	protected static function with_defaults( array $pattern, DateTimeImmutable $starts_on ): array {
		if ( 'WEEKLY' === $pattern['frequency'] && empty( $pattern['weekdays'] ) ) {
			$pattern['weekdays'] = array( self::weekday( $starts_on ) );
		}

		$by_month_day = null === $pattern['month_day'] && null === $pattern['ordinal_weekday'];

		if ( 'MONTHLY' === $pattern['frequency'] && $by_month_day ) {
			$pattern['month_day'] = (int) $starts_on->format( 'j' );
		}

		return $pattern;
	}

	/**
	 * Parse the supported `RRULE` subset.
	 *
	 * @since TBD
	 *
	 * @param mixed $rrule The pattern, or null for a single date.
	 *
	 * @return array<string, mixed>|null The parts, or null if the pattern is not supported.
	 * @phpstan-return RulePattern|null
	 */
	protected static function parse_rrule( mixed $rrule ): ?array {
		$parts = array(
			'frequency'       => null,
			'interval'        => 1,
			'weekdays'        => array(),
			'ordinal_weekday' => null,
			'month_day'       => null,
		);

		if ( null === $rrule || '' === $rrule ) {
			return $parts;
		}

		if ( ! is_string( $rrule ) ) {
			return null;
		}

		$values = array();

		foreach ( explode( ';', strtoupper( $rrule ) ) as $pair ) {
			list( $name, $value ) = array_pad( explode( '=', $pair, 2 ), 2, '' );

			$supported = array( 'FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY' );

			if ( isset( $values[ $name ] ) || ! in_array( $name, $supported, true ) ) {
				return null;
			}

			$values[ $name ] = $value;
		}

		$parts['frequency'] = in_array( $values['FREQ'] ?? '', Rules::FREQUENCIES, true ) ? $values['FREQ'] : null;

		if ( isset( $values['INTERVAL'] ) ) {
			$parts['interval'] = ctype_digit( $values['INTERVAL'] ) ? (int) $values['INTERVAL'] : 0;
		}

		if ( ! $parts['frequency'] || 1 > $parts['interval'] || self::MAX_INTERVAL < $parts['interval'] ) {
			return null;
		}

		return self::parse_by_parts( $parts, $values );
	}

	/**
	 * Parse `BYDAY` and `BYMONTHDAY` for the frequencies that take them.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed>  $parts  The parts parsed so far.
	 * @param array<string, string> $values The raw `RRULE` values.
	 * @phpstan-param RulePattern $parts
	 *
	 * @return array<string, mixed>|null The parts, or null if `BYDAY` or `BYMONTHDAY` is not supported.
	 * @phpstan-return RulePattern|null
	 */
	protected static function parse_by_parts( array $parts, array $values ): ?array {
		$by_day       = $values['BYDAY'] ?? null;
		$by_month_day = $values['BYMONTHDAY'] ?? null;

		if ( 'WEEKLY' === $parts['frequency'] && null === $by_month_day ) {
			$weekdays = null === $by_day ? array() : explode( ',', $by_day );

			$repeated = count( $weekdays ) !== count( array_unique( $weekdays ) );

			if ( $repeated || array_diff( $weekdays, Rules::WEEKDAYS ) ) {
				return null;
			}

			// Kept in week order, so the canonical form doesn't depend on how it was written.
			$parts['weekdays'] = array_values( array_intersect( Rules::WEEKDAYS, $weekdays ) );

			return $parts;
		}

		if ( 'MONTHLY' === $parts['frequency'] && ( null === $by_day || null === $by_month_day ) ) {
			if ( null !== $by_day ) {
				if ( ! preg_match( '/^(-1|[1-4])(MO|TU|WE|TH|FR|SA|SU)$/', $by_day, $matches ) ) {
					return null;
				}

				$parts['ordinal_weekday'] = array( (int) $matches[1], $matches[2] );
			}

			if ( null !== $by_month_day ) {
				if ( ! ctype_digit( $by_month_day ) || 1 > (int) $by_month_day || 31 < (int) $by_month_day ) {
					return null;
				}

				$parts['month_day'] = (int) $by_month_day;
			}

			return $parts;
		}

		$takes_no_by_parts = in_array( $parts['frequency'], array( 'DAILY', 'YEARLY' ), true );

		return ( $takes_no_by_parts && null === $by_day && null === $by_month_day ) ? $parts : null;
	}

	/**
	 * Parse the times of day.
	 *
	 * @since TBD
	 *
	 * @param mixed $times The times, as `H:i` strings.
	 *
	 * @return string[]|null The unique times in order, or null if any is not a valid time.
	 */
	protected static function parse_times( mixed $times ): ?array {
		if ( ! is_array( $times ) ) {
			return null;
		}

		foreach ( $times as $time ) {
			if ( ! is_string( $time ) || ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
				return null;
			}
		}

		$times = array_values( array_unique( $times ) );
		sort( $times );

		return $times;
	}

	/**
	 * Parse a local date.
	 *
	 * @since TBD
	 *
	 * @param mixed $date The date, as `Y-m-d`.
	 *
	 * @return DateTimeImmutable|null The date at midnight UTC, or null if it is not a real date.
	 */
	protected static function parse_date( mixed $date ): ?DateTimeImmutable {
		if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}

		$parsed = self::utc_date( $date );

		// The round trip rejects overflowing dates such as 2026-02-31.
		return $parsed->format( self::DATE_FORMAT ) === $date ? $parsed : null;
	}

	/**
	 * A `Y-m-d` date at midnight UTC.
	 *
	 * @since TBD
	 *
	 * @param string $date The date.
	 *
	 * @return DateTimeImmutable The date.
	 *
	 * @throws Exception If the date cannot be parsed.
	 */
	protected static function utc_date( string $date ): DateTimeImmutable {
		return new DateTimeImmutable( $date . ' 00:00:00', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The Monday of a date's week.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date A date at midnight UTC.
	 *
	 * @return DateTimeImmutable The Monday.
	 */
	protected static function monday( DateTimeImmutable $date ): DateTimeImmutable {
		return $date->modify( sprintf( '-%d days', (int) $date->format( 'N' ) - 1 ) );
	}

	/**
	 * A date's weekday code.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date The date.
	 *
	 * @return string `MO` to `SU`.
	 */
	protected static function weekday( DateTimeImmutable $date ): string {
		return Rules::WEEKDAYS[ (int) $date->format( 'N' ) - 1 ];
	}
}
