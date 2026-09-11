<?php
/**
 * Expands a recurrence rule into concrete datetimes.
 *
 * Pure by contract: no database access, no globals, no side effects, so the
 * hardest piece of the subsystem is testable in isolation.
 *
 * The contract is datetime-valued, never date-valued, from the first
 * commit. The wall-clock time is read once from the anchor and applied
 * in the series timezone; interval arithmetic never runs on a wall-clock-bearing
 * datetime. Per RFC 5545 section 3.3.10, a nonexistent local time in a
 * spring-forward gap is skipped and does not consume a `COUNT` budget.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;

/**
 * Class Expander.
 *
 * Not a singleton and not stateful: an expansion is a function of its
 * arguments, which is what makes the DST and interval cases cheap to test.
 *
 * @since 0.36.0
 */
final class Expander {

	/**
	 * Absolute backstop on candidate steps.
	 *
	 * `iteration_budget()` computes the working bound. This constant exists only
	 * so a bug in that calculation cannot hang a request.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const MAX_ITERATIONS = 200000;

	/**
	 * Hard cap on an authored `COUNT`, rejected above this at the meta boundary.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const MAX_COUNT = 730;

	/**
	 * How many candidate months the monthly walk examines before giving up.
	 *
	 * Counted in candidates, not in absolute months: the walk steps by the rule's
	 * interval, so an absolute-month bound would collapse to `bound / interval`
	 * candidates and silently truncate a legal `COUNT` at a wide interval. An
	 * exhaustive search over every interval from 1 to `Rule::MAX_INTERVAL` and
	 * every day from the 28th to the 31st puts the worst run of unusable
	 * candidate months at seven, which a February 29th rule at interval 12
	 * produces, so this leaves a wide margin while still terminating an
	 * unsatisfiable rule at once.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const MONTH_SCAN_STEPS = 48;

	/**
	 * How many candidate years the yearly walk examines before giving up.
	 *
	 * Counted in candidates, not in absolute years, for the same reason
	 * `MONTH_SCAN_STEPS` is: the walk steps by the rule's interval, and an
	 * absolute-year bound would collapse to `bound / interval` candidates and
	 * truncate a legal `COUNT` at a wide interval.
	 *
	 * A yearly rule takes its month and day from the anchor, and the only
	 * anchor date that some years lack is 29 February. Every other month and
	 * day pair exists in every year, which an exhaustive check over all of them
	 * from 1583 to 2400 confirms. So the worst case is a leap-day anchor whose
	 * interval keeps landing on non-leap years. Searching every interval from 1
	 * to `Rule::MAX_INTERVAL` against every leap-day anchor from 1600 to 2400
	 * puts the worst run of unusable candidates at **fifteen**: interval 25 from
	 * a 1600 anchor, which crosses 1700, 1800 and 1900 before landing on 2000.
	 * None of those three centuries is a leap year. Interval 1 peaks at seven, over the
	 * same 1900 gap. `Test_Expander::test_year_scan_steps_clears_the_measured_worst_case()`
	 * re-runs that search, so the measurement cannot silently go stale.
	 *
	 * 64 leaves better than four times the measured worst run of headroom while
	 * still costing only a handful of `checkdate()` calls per candidate.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const YEAR_SCAN_STEPS = 64;

	/**
	 * Derive the candidate-step budget from the rule rather than guessing it.
	 *
	 * The walk is day-by-day, so the budget is expressed in days rather than in
	 * occurrences: a `COUNT=500` monthly rule needs roughly 15,200 day-steps.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule    Rule being expanded.
	 * @param DateTimeImmutable $anchor  Series anchor start.
	 * @param DateTimeImmutable $through Horizon the expansion runs to.
	 *
	 * @return int The lesser of the derived budget and `MAX_ITERATIONS`.
	 */
	protected function iteration_budget( Rule $rule, DateTimeImmutable $anchor, DateTimeImmutable $through ): int {
		$days = (int) $anchor->diff( $through )->days + 1;

		if ( Rule::END_TYPE_COUNT === $rule->end_type() ) {
			$per = match ( $rule->frequency() ) {
				Rule::FREQUENCY_DAILY  => 1,
				Rule::FREQUENCY_WEEKLY => 7,
				Rule::FREQUENCY_YEARLY => 366,
				default                => 31,
			};

			// A count-bounded rule outruns the horizon, so the budget covers the
			// whole count plus a year of slack for skipped candidates.
			$days = max( $days, $rule->count() * $per * $rule->interval() + 366 );
		}

		return min( $days, self::MAX_ITERATIONS );
	}

	/**
	 * Expand a rule into its occurrence datetimes.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule         Rule being expanded.
	 * @param DateTimeImmutable $anchor_start Series anchor start, source of the wall-clock time. Converted into
	 *                                        `$timezone` first, so a UTC-expressed anchor is not reinterpreted.
	 * @param DateTimeZone      $timezone     Series timezone, which must be a named identifier.
	 * @param DateTimeImmutable $through      Horizon, ignored by a count-bounded rule. Also read in `$timezone`.
	 *
	 * @return DateTimeImmutable[] Ordered ascending. Never contains a nonexistent local time.
	 */
	public function expand(
		Rule $rule,
		DateTimeImmutable $anchor_start,
		DateTimeZone $timezone,
		DateTimeImmutable $through
	): array {
		Timezone_Guard::assert_named( $timezone->getName() );

		// The wall clock belongs to the series timezone, not to whichever timezone
		// the caller's value happens to carry. Reading `H:i:s` off a UTC-expressed
		// anchor would shift every occurrence by the UTC offset and still look
		// like a working series.
		$anchor_start = $anchor_start->setTimezone( $timezone );

		// The wall-clock time is read from the anchor once, and the walk
		// itself runs on timezone-free dates so no interval arithmetic can land on
		// a datetime that carries an offset.
		$time       = $anchor_start->format( 'H:i:s' );
		$anchor     = $this->date_only( $anchor_start );
		$cursor     = $anchor;
		$horizon    = $through->setTimezone( $timezone )->format( 'Y-m-d' );
		$results    = array();
		$delivered  = 0;
		$iterations = 0;
		$is_count   = Rule::END_TYPE_COUNT === $rule->end_type();
		$budget     = $is_count ? $rule->count() : PHP_INT_MAX;
		$steps      = $this->iteration_budget( $rule, $anchor_start, $through );

		while ( $delivered < $budget && $iterations < $steps ) {
			++$iterations;
			$candidate = $this->next_candidate_date( $rule, $cursor, $anchor );

			// A count-bounded rule is bounded by its count, never by the horizon:
			// a weekly COUNT=500 spans roughly nine and a half years.
			if ( null === $candidate || ( ! $is_count && $candidate->format( 'Y-m-d' ) > $horizon ) ) {
				break;
			}

			$cursor = $candidate->modify( '+1 day' );

			if ( $this->past_until( $rule, $candidate ) ) {
				break;
			}

			$occurrence = $this->materialize( $candidate->format( 'Y-m-d' ), $time, $timezone );

			// RFC 5545 section 3.3.10: a nonexistent local time is ignored and does
			// not count toward the recurrence set, so the budget is spent here on
			// appending a result rather than earlier on consuming a candidate.
			if ( null !== $occurrence ) {
				$results[] = $occurrence;
				++$delivered;
			}
		}

		return $results;
	}

	/**
	 * Advance to the next date the rule could match.
	 *
	 * The anchor is a parameter because every interval is measured from the
	 * anchor rather than from the cursor.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule   Rule being expanded.
	 * @param DateTimeImmutable $cursor Date-only cursor to advance from.
	 * @param DateTimeImmutable $anchor Series anchor date, the origin of interval arithmetic.
	 *
	 * @return DateTimeImmutable|null The next candidate date, or null when the walk is exhausted.
	 */
	protected function next_candidate_date(
		Rule $rule,
		DateTimeImmutable $cursor,
		DateTimeImmutable $anchor
	): ?DateTimeImmutable {
		return match ( $rule->frequency() ) {
			// Yearly shares the month walk rather than getting a third pattern
			// of its own: it is the same "step whole months from the anchor and
			// resolve the date in that month" shape, twelve months per interval
			// unit. A day-by-day scan would step roughly 1,460 candidates per
			// occurrence at INTERVAL=4.
			Rule::FREQUENCY_MONTHLY, Rule::FREQUENCY_YEARLY => $this->next_monthly_date( $rule, $cursor, $anchor ),
			Rule::FREQUENCY_DAILY, Rule::FREQUENCY_WEEKLY   => $this->next_scanned_date( $rule, $cursor, $anchor ),
			// An unrecognized frequency yields no occurrences rather than a fatal.
			default                                         => null,
		};
	}

	/**
	 * Scan forward a day at a time for the next date a daily or weekly rule matches.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule   Rule being expanded.
	 * @param DateTimeImmutable $cursor Date-only cursor to scan from.
	 * @param DateTimeImmutable $anchor Series anchor date.
	 *
	 * @return DateTimeImmutable|null The next matching date, or null when the window is exhausted.
	 */
	protected function next_scanned_date(
		Rule $rule,
		DateTimeImmutable $cursor,
		DateTimeImmutable $anchor
	): ?DateTimeImmutable {
		$limit = $this->day_scan_limit( $rule );
		$date  = $cursor;
		$found = null;

		for ( $step = 0; $step <= $limit; $step++ ) {
			if ( $this->matches( $rule, $date, $anchor ) ) {
				$found = $date;
				break;
			}

			$date = $date->modify( '+1 day' );
		}

		return $found;
	}

	/**
	 * Walk months rather than days for the next date a month-walked rule matches.
	 *
	 * Serves the monthly and yearly frequencies alike. Yearly is the same walk
	 * with a twelve-times-wider step and a wider scan bound, which is why it does
	 * not get a pattern of its own. A day-by-day scan cannot serve either: a
	 * day-of-month rule on the 31st skips five months a year, and a 29 February
	 * rule waits years.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule   Rule being expanded.
	 * @param DateTimeImmutable $cursor Date-only cursor to walk from.
	 * @param DateTimeImmutable $anchor Series anchor date.
	 *
	 * @return DateTimeImmutable|null The next matching date, or null when no month in the window resolves one.
	 */
	protected function next_monthly_date(
		Rule $rule,
		DateTimeImmutable $cursor,
		DateTimeImmutable $anchor
	): ?DateTimeImmutable {
		$step  = $this->month_step( $rule );
		$scan  = $this->month_scan_steps( $rule );
		$start = max( 0, (int) ceil( $this->months_apart( $anchor, $cursor ) / $step ) ) * $step;
		$found = null;

		for ( $candidate = 0; $candidate <= $scan; $candidate++ ) {
			$date = $this->monthly_date_for_offset( $rule, $anchor, $start + $candidate * $step );

			if ( null !== $date && $date >= $cursor ) {
				$found = $date;
				break;
			}
		}

		return $found;
	}

	/**
	 * Get how many whole months one candidate step of the month walk spans.
	 *
	 * Yearly's interval is authored in years, so one candidate step is twelve
	 * months per interval unit. Bounding the walk in candidate steps rather than
	 * in absolute months is what keeps a wide interval from being truncated.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule $rule Rule being expanded.
	 *
	 * @return int Whole months per candidate step, never below one.
	 */
	protected function month_step( Rule $rule ): int {
		return Rule::FREQUENCY_YEARLY === $rule->frequency()
			? $rule->interval() * 12
			: $rule->interval();
	}

	/**
	 * Get how many candidate steps the month walk examines before giving up.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule $rule Rule being expanded.
	 *
	 * @return int Candidate steps, from `YEAR_SCAN_STEPS` or `MONTH_SCAN_STEPS`.
	 */
	protected function month_scan_steps( Rule $rule ): int {
		return Rule::FREQUENCY_YEARLY === $rule->frequency()
			? self::YEAR_SCAN_STEPS
			: self::MONTH_SCAN_STEPS;
	}

	/**
	 * Resolve the date a month-walked rule falls on a given number of months from the anchor.
	 *
	 * A yearly rule takes both its month and its day from the anchor, so the
	 * offset carries the month and the anchor carries the day. For a yearly
	 * rule that offset is always a multiple of twelve. It never consults `monthly_mode`, which a
	 * yearly rule does not have.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule   Rule being expanded.
	 * @param DateTimeImmutable $anchor Series anchor date.
	 * @param int               $offset Whole months from the anchor's month.
	 *
	 * @return DateTimeImmutable|null The date, or null when that month has no such date.
	 */
	protected function monthly_date_for_offset(
		Rule $rule,
		DateTimeImmutable $anchor,
		int $offset
	): ?DateTimeImmutable {
		$months = (int) $anchor->format( 'Y' ) * 12 + (int) $anchor->format( 'n' ) - 1 + $offset;
		$year   = intdiv( $months, 12 );
		$month  = $months % 12 + 1;

		$date = match ( true ) {
			Rule::FREQUENCY_YEARLY === $rule->frequency() => $this->day_of_month_date(
				$year,
				$month,
				(int) $anchor->format( 'j' )
			),
			Rule::MONTHLY_MODE_NTH_WEEKDAY === $rule->monthly_mode() => $this->nth_weekday_of_month(
				$year,
				$month,
				$rule->monthly_weekday(),
				$rule->monthly_ordinal()
			),
			default => $this->day_of_month_date( $year, $month, $rule->monthly_day() ),
		};

		return null === $date ? null : new DateTimeImmutable( $date, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Resolve a day number within a month, without rolling a missing day forward.
	 *
	 * The 31st of a thirty-day month is not an occurrence, and is never
	 * the 1st of the month after.
	 *
	 * @since 0.36.0
	 *
	 * @param int $year  Four-digit year.
	 * @param int $month Month number, 1 through 12.
	 * @param int $day   Day number, 1 through 31.
	 *
	 * @return string|null A `Y-m-d` date, or null when the month has no such day.
	 */
	protected function day_of_month_date( int $year, int $month, int $day ): ?string {
		return checkdate( $month, $day, $year ) ? sprintf( '%04d-%02d-%02d', $year, $month, $day ) : null;
	}

	/**
	 * Report whether a candidate date satisfies the rule.
	 *
	 * The anchor is a parameter because weekly interval arithmetic is relative
	 * to the anchor's Monday-start week, not to the cursor.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule      Rule being expanded.
	 * @param DateTimeImmutable $candidate Candidate date under test.
	 * @param DateTimeImmutable $anchor    Series anchor, the origin of interval arithmetic.
	 *
	 * @return bool True when the candidate is an occurrence date.
	 */
	protected function matches( Rule $rule, DateTimeImmutable $candidate, DateTimeImmutable $anchor ): bool {
		return match ( $rule->frequency() ) {
			Rule::FREQUENCY_DAILY  => $this->matches_daily( $rule, $candidate, $anchor ),
			Rule::FREQUENCY_WEEKLY => $this->matches_weekly( $rule, $candidate, $anchor ),
			// The walk sends monthly and yearly rules to `next_monthly_date()`
			// rather than through the day scan, so this arm answers direct callers
			// only. It is kept so the predicate is complete for every frequency the
			// rule can hold, rather than correct for the two the scan happens to use.
			Rule::FREQUENCY_MONTHLY, Rule::FREQUENCY_YEARLY => $this->matches_monthly(
				$rule,
				$candidate,
				$anchor
			),
			// An unrecognized frequency matches nothing rather than fataling.
			default => false,
		};
	}

	/**
	 * Report whether a candidate date is an interval multiple of days from the anchor.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule      Rule being expanded.
	 * @param DateTimeImmutable $candidate Candidate date under test.
	 * @param DateTimeImmutable $anchor    Series anchor date.
	 *
	 * @return bool True when the candidate is an occurrence date.
	 */
	protected function matches_daily( Rule $rule, DateTimeImmutable $candidate, DateTimeImmutable $anchor ): bool {
		return $candidate >= $anchor
			&& 0 === (int) $anchor->diff( $candidate )->days % $rule->interval();
	}

	/**
	 * Report whether a candidate date is on a selected weekday in an on-interval week.
	 *
	 * The interval is counted in Monday-start week buckets, not in
	 * seven-day deltas from the anchor: a Thursday anchor sits late in its own
	 * week, and a day-delta walk lands the next occurrence in the wrong bucket.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule      Rule being expanded.
	 * @param DateTimeImmutable $candidate Candidate date under test.
	 * @param DateTimeImmutable $anchor    Series anchor date.
	 *
	 * @return bool True when the candidate is an occurrence date.
	 */
	protected function matches_weekly( Rule $rule, DateTimeImmutable $candidate, DateTimeImmutable $anchor ): bool {
		$weeks_apart = $this->week_index( $candidate ) - $this->week_index( $anchor );

		// The anchor guard mirrors `matches_daily()`. The walk never offers a
		// candidate behind the anchor, but a direct caller can, and a negative
		// bucket delta divides evenly just as often as a positive one.
		return $candidate >= $anchor
			&& in_array( (int) $candidate->format( 'w' ), $rule->weekdays(), true )
			&& 0 === $weeks_apart % $rule->interval();
	}

	/**
	 * Report whether a candidate date is the rule's date in an on-interval month.
	 *
	 * Serves the yearly frequency too, whose step is twelve months per interval
	 * unit. The trailing date comparison is what carries the leap-day rule: for a
	 * 29 February anchor, 2025-02-28 sits an on-step twelve months out and clears
	 * every earlier guard, so only comparing the resolved date to the candidate
	 * rejects it. RFC 5545 section 3.3.10 forbids falling back to the 28th just
	 * as firmly as rolling on to 1 March.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule      Rule being expanded.
	 * @param DateTimeImmutable $candidate Candidate date under test.
	 * @param DateTimeImmutable $anchor    Series anchor date.
	 *
	 * @return bool True when the candidate is an occurrence date.
	 */
	protected function matches_monthly( Rule $rule, DateTimeImmutable $candidate, DateTimeImmutable $anchor ): bool {
		$offset = $this->months_apart( $anchor, $candidate );
		$date   = ( $offset >= 0 && 0 === $offset % $this->month_step( $rule ) )
			? $this->monthly_date_for_offset( $rule, $anchor, $offset )
			: null;

		return null !== $date && $date->format( 'Y-m-d' ) === $candidate->format( 'Y-m-d' );
	}

	/**
	 * Get the signed number of whole months between two dates.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $from Date to measure from.
	 * @param DateTimeImmutable $to   Date to measure to.
	 *
	 * @return int Months, negative when the second date is earlier.
	 */
	protected function months_apart( DateTimeImmutable $from, DateTimeImmutable $to ): int {
		return ( (int) $to->format( 'Y' ) * 12 + (int) $to->format( 'n' ) )
			- ( (int) $from->format( 'Y' ) * 12 + (int) $from->format( 'n' ) );
	}

	/**
	 * Get how many days forward the day-by-day scan looks before giving up.
	 *
	 * The window is the widest legal gap between consecutive dates. An
	 * unsatisfiable rule, such as a weekly rule with no weekdays selected,
	 * therefore terminates in one window rather than running to
	 * `MAX_ITERATIONS`.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule $rule Rule being expanded.
	 *
	 * @return int Days to scan, or 0 for a frequency the scan does not serve.
	 */
	protected function day_scan_limit( Rule $rule ): int {
		return match ( $rule->frequency() ) {
			Rule::FREQUENCY_DAILY  => $rule->interval(),
			Rule::FREQUENCY_WEEKLY => $rule->interval() * 7 + 7,
			// Monthly and yearly walk months, and an unrecognized frequency scans
			// nothing. `next_candidate_date()` already routes all three away from
			// the day scan, so this arm is defense in depth rather than a live path.
			default                => 0,
		};
	}

	/**
	 * Resolve the nth weekday of a month to a date.
	 *
	 * @since 0.36.0
	 *
	 * @param int $year    Four-digit year.
	 * @param int $month   Month number, 1 through 12.
	 * @param int $weekday Weekday number, 0 for Sunday through 6 for Saturday.
	 * @param int $ordinal Ordinal from 1 to 4, or -1 for "last".
	 *
	 * @return string|null A `Y-m-d` date, or null when the month has no such weekday.
	 */
	protected function nth_weekday_of_month( int $year, int $month, int $weekday, int $ordinal ): ?string {
		$first = new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), new DateTimeZone( 'UTC' ) );
		$span  = (int) $first->format( 't' );

		if ( -1 === $ordinal ) {
			$last = (int) $first->modify( 'last day of this month' )->format( 'w' );
			$day  = $span - ( ( $last - $weekday + 7 ) % 7 );
		} else {
			$lead = (int) $first->format( 'w' );
			$day  = 1 + ( ( $weekday - $lead + 7 ) % 7 ) + ( $ordinal - 1 ) * 7;
		}

		return ( $day >= 1 && $day <= $span ) ? sprintf( '%04d-%02d-%02d', $year, $month, $day ) : null;
	}

	/**
	 * Apply a wall-clock time to a date in the series timezone.
	 *
	 * PHP normalizes a nonexistent local time forward, turning 02:30 on a
	 * spring-forward date into 03:30. Detection is therefore a round trip:
	 * format the result back out and compare it to what went in.
	 *
	 * The comparison is on the whole local datetime rather than on the time
	 * alone, because a zone can skip an entire calendar date: Samoa's date-line
	 * crossing erased 2011-12-30, and `Pacific/Apia` normalizes 09:00 that day to
	 * 09:00 on the 31st. A time-only round trip accepts that, emits a duplicate of
	 * the following day, and spends a `COUNT` slot on it.
	 *
	 * @since 0.36.0
	 *
	 * @param string       $date     Date in `Y-m-d` form.
	 * @param string       $time     Wall-clock time in `H:i:s` form.
	 * @param DateTimeZone $timezone Series timezone.
	 *
	 * @return DateTimeImmutable|null The datetime, or null when the local datetime does not exist.
	 */
	protected function materialize( string $date, string $time, DateTimeZone $timezone ): ?DateTimeImmutable {
		$local    = $date . ' ' . $time;
		$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $local, $timezone );
		$errors   = DateTimeImmutable::getLastErrors();

		$exists = false !== $datetime
			&& ( ! is_array( $errors ) || 0 === $errors['warning_count'] )
			&& $datetime->format( 'Y-m-d H:i:s' ) === $local;

		return $exists ? $datetime : null;
	}

	/**
	 * Get the Monday-start week index for a date.
	 *
	 * A weekly rule matches when `week_index( $candidate ) - week_index( $anchor )`
	 * is divisible by the rule's interval.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $date Date to bucket.
	 *
	 * @return int The week bucket, which may be negative for pre-1970 dates.
	 */
	protected function week_index( DateTimeImmutable $date ): int {
		$monday = $date->modify( 'monday this week' );

		return (int) floor( $monday->getTimestamp() / ( 7 * DAY_IN_SECONDS ) );
	}

	/**
	 * Reduce a datetime to its date, held in UTC.
	 *
	 * The walk runs on UTC midnights for two reasons. `setTime( 0, 0, 0 )` is
	 * undefined in a zone whose transition lands on midnight, and both
	 * `America/Santiago` on 2026-09-06 and `Asia/Beirut` on 2026-03-29 return
	 * 01:00 for it. `diff()->days` on a timezone-aware pair also misreports a
	 * 23-hour day. Week bucketing is not the reason: `floor( $timestamp / 604800 )` breaks at
	 * Thursday 00:00 UTC, so a local Monday midnight sits in the bucket interior
	 * and never crosses under a daylight saving shift. The series timezone is
	 * reapplied in `materialize()` and nowhere else.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $datetime Datetime to reduce.
	 *
	 * @return DateTimeImmutable Midnight UTC on the datetime's own local date.
	 */
	protected function date_only( DateTimeImmutable $datetime ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime->format( 'Y-m-d' ), new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Report whether a candidate date falls after the rule's end date.
	 *
	 * `Rule::until()` is null unless the rule ends on a date, so this needs no
	 * separate end-type test. The comparison is by date, because `UNTIL` is
	 * inclusive of its own day whatever time of day the occurrence carries.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule      Rule being expanded.
	 * @param DateTimeImmutable $candidate Candidate date under test.
	 *
	 * @return bool True when the candidate is past the end date.
	 */
	protected function past_until( Rule $rule, DateTimeImmutable $candidate ): bool {
		$until = $rule->until();

		return null !== $until && $candidate->format( 'Y-m-d' ) > $until->format( 'Y-m-d' );
	}
}
