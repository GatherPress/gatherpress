<?php
/**
 * `VTIMEZONE` generation for the iCalendar responses.
 *
 * This file defines the `Timezone_Component` class, which turns a tz-database
 * identifier into the RFC 5545 component that gives it meaning. GatherPress
 * emits `DTSTART;TZID=America/New_York:20300615T143000` rather than a bare UTC
 * instant, because an `RRULE` cannot be correctly attached to a UTC-anchored
 * start for anything but a fixed-offset series. RFC 5545 section 3.2.19
 * requires that every `TZID` parameter refer to a `VTIMEZONE` carried in the
 * same `VCALENDAR`. Without one the output is not merely under-specified, it is
 * invalid, and clients are free to reject or misplace the event.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeZone;
use GatherPress\Core\Event\Recurrence\Timezone_Guard;
use GatherPress\Core\Traits\Singleton;

/**
 * `VTIMEZONE` generation for the iCalendar responses.
 *
 * Definitions are derived from `DateTimeZone::getTransitions()` rather than
 * from a bundled table, so they track whatever tz database the host carries.
 * A zone's transitions are regular when they repeat the same offsets and name
 * on the same weekday ordinal of the same month at the same wall clock, year
 * after year. Where they are, one `STANDARD` and one `DAYLIGHT` sub-component
 * carry an `RRULE` and describe the zone for as long as that policy holds. Where they are not, each
 * transition in range is written out on its own.
 *
 * The range is the one the body covers, never the one the request happens to
 * fall in. RFC 5545 section 3.6.5 requires the definition to be valid for every
 * instant the components it serves refer to, and resolves an instant against
 * the observance with the last onset *before* it. A definition whose earliest
 * onset postdates a 2020 event therefore does not define that event's offset
 * at all, however correct it is about today.
 *
 * A body carrying an unbounded rule has no last instant, so its definition may
 * never simply stop: a client resolves occurrences arbitrarily far ahead, and
 * every one past the last emitted onset would silently take the last emitted
 * offset. The policy is therefore: the window extends to a horizon decades
 * past the tz database's year-by-year civil-time knowledge; where the
 * enumerated transitions settle into a repeating yearly pattern, the tail
 * collapses to one unbounded `RRULE` and the definition is as infinite as the
 * event rule it serves; where the zone never settles inside the horizon,
 * every transition the host tz database returns for the sampled range is
 * written out.
 *
 * @since 0.36.0
 */
final class Timezone_Component {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Years of transition history read behind the earliest instant in range.
	 *
	 * One year is enough to observe the offset in force before the first
	 * transition after it, which is what `TZOFFSETFROM` reports.
	 *
	 * @since 0.36.0
	 *
	 * @var int
	 */
	private const LOOKBEHIND_YEARS = 1;

	/**
	 * Years of transitions read ahead of the latest instant in range.
	 *
	 * Three is the smallest window that shows a yearly pattern repeating often
	 * enough to be called regular rather than coincidental. An open-ended rule
	 * does not rely on it: having no last instant of its own, it extends the
	 * range itself, to `OPEN_ENDED_HORIZON_YEARS`.
	 *
	 * @since 0.36.0
	 *
	 * @var int
	 */
	private const LOOKAHEAD_YEARS = 3;

	/**
	 * Years ahead of now an open-ended rule extends the definition window.
	 *
	 * Deep enough that the tz database's explicit year-by-year knowledge, not
	 * this number, is the binding constraint: the longest-running enumerated
	 * civil-time rules on record are written out into the 2080s, and past
	 * their end the database extrapolates the terminal policy, which the
	 * terminal-rule detection then collapses to one unbounded `RRULE`. The
	 * cost is bounded by the zone itself: a stable zone still collapses to
	 * two ruled sub-components however wide the window is.
	 *
	 * @since 0.36.0
	 *
	 * @var int
	 */
	private const OPEN_ENDED_HORIZON_YEARS = 75;

	/**
	 * The properties whose values may carry a date-time.
	 *
	 * `DTSTART`, `DTEND`, `EXDATE`, `RDATE` and `RECURRENCE-ID` carry the
	 * instants a client resolves against an observance; `RRULE` carries at
	 * most an `UNTIL` bound. Everything else in a body is either structural
	 * or author-supplied text, and author text must never be an input to the
	 * definition window: a date-time-shaped token in a post title otherwise
	 * inflates every public feed the event appears in.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	private const DATE_BEARING_PROPERTIES = 'DTSTART|DTEND|EXDATE|RDATE|RECURRENCE-ID|RRULE';

	/**
	 * Rendered definitions, keyed by identifier and the range they were built for.
	 *
	 * A feed commonly carries many events in one zone, and the transition list
	 * is the same for all of them, but only for the same range. That is why
	 * the range is part of the key rather than assumed away.
	 *
	 * @since 0.36.0
	 *
	 * @var array<string, string>
	 */
	private array $rendered = array();

	/**
	 * Every `VTIMEZONE` the components in an iCal body need to be valid.
	 *
	 * Derived from the body rather than from the events that produced it, so
	 * the invariant it exists to hold is true by construction rather than by two
	 * code paths agreeing. That invariant is that every `TZID` referenced is
	 * defined.
	 *
	 * The range each definition covers is derived from the same body, for the
	 * same reason: the instants a definition has to be valid for are the ones
	 * written into the components it accompanies.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body One or more assembled `VEVENT` components.
	 *
	 * @return string The definitions, in first-reference order, or '' when none are referenced.
	 */
	public function render_for_body( string $body ): string {
		// Unfolded so a folded property reads as one content line, and
		// anchored to the properties that can carry a `TZID` parameter:
		// `escape_ical_text()` leaves the literal `;TZID=` inside escaped
		// author text, and a scan of the whole body would define zones a
		// title names and nothing references.
		$unfolded = str_replace( array( "\r\n ", "\r\n\t" ), '', $body );

		preg_match_all(
			'/^(?:' . self::DATE_BEARING_PROPERTIES . ')[^:\r\n]*;TZID=([^:;\r\n]+)/m',
			$unfolded,
			$matches
		);

		[ $from, $to ] = $this->range_of( $unfolded );

		$components = array();

		foreach ( array_unique( $matches[1] ) as $tzid ) {
			$component = $this->render( $tzid, $from, $to );

			if ( '' !== $component ) {
				$components[] = $component;
			}
		}

		return implode( "\r\n", $components );
	}

	/**
	 * The span of instants an assembled body refers to.
	 *
	 * Every date-time value a date-bearing property carries counts: a
	 * `DTSTART`, the `RECURRENCE-ID` of a single-occurrence download, an
	 * `EXDATE` exclusion, and the `UNTIL` bound of a rule are all moments a
	 * client has to resolve against an observance. They are read as UTC
	 * regardless of the `TZID` qualifying them, which is wrong by at most a
	 * day's offset and irrelevant at the scale the window is measured in.
	 * Values are read only off content lines of those properties, anchored to
	 * the line start, so `SUMMARY`, `LOCATION`, `URL` and `DESCRIPTION` text
	 * is never an input to the window.
	 *
	 * Two floors and a ceiling are applied. The span always contains the
	 * present, so a feed of purely historical events still defines the zone a
	 * client is reading it in; a rule with neither `UNTIL` nor `COUNT` has no
	 * last instant, so it extends the span to the open-ended horizon rather
	 * than ending at whatever concrete date happened to be written next to
	 * it; and no value, however far ahead, pushes the span past that same
	 * horizon, because past it the tz database only extrapolates the terminal
	 * policy the rule detection already collapses.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body One or more assembled `VEVENT` components.
	 *
	 * @return array{0:int,1:int} The earliest and latest instants, as Unix timestamps.
	 */
	private function range_of( string $body ): array {
		$now = time();

		// Unfold first, so a value continued across folded lines reads as one
		// content line for the anchored scan below.
		$unfolded = str_replace( array( "\r\n ", "\r\n\t" ), '', $body );

		preg_match_all(
			'/^(?:' . self::DATE_BEARING_PROPERTIES . ')[^:\r\n]*:([^\r\n]*)/m',
			$unfolded,
			$lines
		);

		$instants = array( $now );

		foreach ( $lines[1] as $value ) {
			preg_match_all( '/(\d{8})T(\d{6})/', $value, $moments, PREG_SET_ORDER );

			foreach ( $moments as $moment ) {
				$instant = strtotime( $moment[1] . 'T' . $moment[2] . 'Z' );

				if ( false !== $instant ) {
					$instants[] = $instant;
				}
			}
		}

		$horizon = $now + ( self::OPEN_ENDED_HORIZON_YEARS * YEAR_IN_SECONDS );

		if ( $this->has_open_ended_rule( $unfolded ) ) {
			$instants[] = $horizon;
		}

		return array( min( $instants ), min( max( $instants ), $horizon ) );
	}

	/**
	 * Whether any rule in the body reaches past the date-time literals in it.
	 *
	 * Only `UNTIL` bounds the definition window, and the reason is that the
	 * window is built from the date-time literals the body actually contains:
	 * `DTSTART`, `DTEND`, `EXDATE`, `RDATE`, `RECURRENCE-ID`, and a rule's
	 * `UNTIL`. `COUNT` writes no literal at all, so it tells the window
	 * nothing while still naming occurrences arbitrarily far ahead:
	 * `Rule::MAX_COUNT` is 730, which is fourteen years for a weekly rule and
	 * sixty for a monthly one.
	 *
	 * Treating `COUNT` as a bound ended the window at `max( DTSTART, DTEND,
	 * now )` plus three years, and every occurrence past that point resolved
	 * against a terminal `RRULE` inferred from two samples. Measured on the
	 * committed class over `FREQ=WEEKLY;COUNT=730`, that was 44 of 730
	 * occurrences an hour wrong in `Asia/Gaza` and `Asia/Hebron`, first at
	 * 2035-03-24, and smaller counts in `Asia/Jerusalem` and
	 * `America/Santiago`.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body One or more assembled `VEVENT` components.
	 *
	 * @return bool True when a rule carries no `UNTIL`.
	 */
	private function has_open_ended_rule( string $body ): bool {
		preg_match_all( '/^RRULE:(.*)$/m', $body, $rules );

		foreach ( $rules[1] as $rule ) {
			if ( ! str_contains( $rule, 'UNTIL=' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The `VTIMEZONE` component defining one tz-database identifier.
	 *
	 * @since 0.36.0
	 *
	 * @param string   $tzid A tz-database identifier.
	 * @param int|null $from Earliest instant the definition must cover; defaults to now.
	 * @param int|null $to   Latest instant the definition must cover; defaults to now.
	 *
	 * @return string The component, or '' when the identifier names no known zone.
	 */
	public function render( string $tzid, ?int $from = null, ?int $to = null ): string {
		$from = $from ?? time();
		$to   = $to ?? time();
		$key  = sprintf( '%s|%d|%d', $tzid, $from, $to );

		if ( ! isset( $this->rendered[ $key ] ) ) {
			$this->rendered[ $key ] = $this->build( $tzid, $from, $to );
		}

		return $this->rendered[ $key ];
	}

	/**
	 * Build one identifier's definition from the tz database.
	 *
	 * @since 0.36.0
	 *
	 * @param string $tzid A tz-database identifier.
	 * @param int    $from Earliest instant the definition must cover.
	 * @param int    $to   Latest instant the definition must cover.
	 *
	 * @return string The component, or '' when the identifier names no known zone.
	 */
	private function build( string $tzid, int $from, int $to ): string {
		// `Timezone_Guard::is_named()` validates positively against
		// `timezone_identifiers_list()`, which is what makes the constructor
		// below unable to throw. A fixed offset never reaches here at all: it
		// is never written into a `TZID` parameter in the first place.
		if ( ! Timezone_Guard::is_named( $tzid ) ) {
			return '';
		}

		$zone = new DateTimeZone( $tzid );
		// The lookbehind is what puts an observance *before* the earliest
		// instant in range, which is the one that instant resolves against.
		$transitions = $zone->getTransitions(
			$from - ( self::LOOKBEHIND_YEARS * YEAR_IN_SECONDS ),
			$to + ( self::LOOKAHEAD_YEARS * YEAR_IN_SECONDS )
		);

		$lines = array_merge(
			array( 'BEGIN:VTIMEZONE', sprintf( 'TZID:%s', $tzid ) ),
			$this->sub_components( $transitions ),
			array( 'END:VTIMEZONE' )
		);

		return implode( "\r\n", $lines );
	}

	/**
	 * The `STANDARD` and `DAYLIGHT` sub-components a transition list produces.
	 *
	 * `STANDARD` is emitted before `DAYLIGHT` regardless of which the window
	 * happened to start in, so the output of a given zone does not depend on
	 * the time of year the feed was requested.
	 *
	 * @since 0.36.0
	 *
	 * @param array $transitions Entries as `DateTimeZone::getTransitions()` returns them.
	 *
	 * @return string[] The sub-component lines, flattened.
	 */
	private function sub_components( array $transitions ): array {
		$head    = $transitions[0] ?? array(
			'offset' => 0,
			'abbr'   => 'UTC',
			'isdst'  => false,
		);
		$changes = array_slice( $transitions, 1 );

		// A zone that never changes offset within the window has no transition
		// to describe, yet still needs a definition, because its
		// identifier is named in a `TZID` parameter. It is written as the
		// degenerate case RFC 5545 allows: one `STANDARD` moving from its own
		// offset to itself, effective from the start of the epoch, with no rule.
		if ( array() === $changes ) {
			return $this->sub_component(
				'STANDARD',
				array(
					array(
						'from'          => (int) $head['offset'],
						'to'            => (int) $head['offset'],
						'abbr'          => (string) $head['abbr'],
						'local'         => '19700101T000000',
						'day'           => 1,
						'days_in_month' => 31,
						'month'         => 1,
						'byday'         => '',
					),
				)
			);
		}

		$grouped  = array(
			'STANDARD' => array(),
			'DAYLIGHT' => array(),
		);
		$previous = (int) $head['offset'];

		foreach ( $changes as $change ) {
			$type               = $change['isdst'] ? 'DAYLIGHT' : 'STANDARD';
			$grouped[ $type ][] = $this->describe_transition( $change, $previous );
			$previous           = (int) $change['offset'];
		}

		return array_merge(
			$this->sub_component( 'STANDARD', $grouped['STANDARD'] ),
			$this->sub_component( 'DAYLIGHT', $grouped['DAYLIGHT'] )
		);
	}

	/**
	 * Describe one transition in the terms a sub-component is written from.
	 *
	 * The effective moment is the local wall clock *before* the change, per
	 * RFC 5545 section 3.6.5: the `DTSTART` of a sub-component is expressed in
	 * the offset the zone is leaving, which is `TZOFFSETFROM`.
	 *
	 * @since 0.36.0
	 *
	 * @param array $change   One entry from `DateTimeZone::getTransitions()`.
	 * @param int   $previous The offset in force immediately before it, in seconds.
	 *
	 * @return array The transition's offsets, abbreviation, local moment and yearly position.
	 */
	private function describe_transition( array $change, int $previous ): array {
		$local = (int) $change['ts'] + $previous;
		$day   = (int) gmdate( 'j', $local );
		$last  = (int) gmdate( 't', $local );

		// A transition inside the final seven days of its month is "the last
		// <weekday>", which is how the European rules are written and the only
		// form that survives a month whose length varies. Anything earlier
		// counts forward from the first.
		$ordinal = ( ( $last - $day ) < 7 ) ? -1 : ( intdiv( $day - 1, 7 ) + 1 );

		// The day and the month length ride along as numbers because `local`
		// is not fixed-width: past year 9999 the year takes five digits, and a
		// positional read of the formatted string goes quietly wrong at
		// exactly the point the window is widest.
		return array(
			'from'          => $previous,
			'to'            => (int) $change['offset'],
			'abbr'          => (string) $change['abbr'],
			'local'         => gmdate( 'Ymd\THis', $local ),
			'day'           => $day,
			'days_in_month' => $last,
			'month'         => (int) gmdate( 'n', $local ),
			'byday'         => sprintf( '%d%s', $ordinal, strtoupper( substr( gmdate( 'D', $local ), 0, 2 ) ) ),
		);
	}

	/**
	 * Render one sub-component type from the transitions belonging to it.
	 *
	 * A terminal run of transitions repeating one yearly pattern collapses to
	 * a single sub-component carrying an unbounded `RRULE`, which describes
	 * the zone from that run's first onset onward and forever. Where the whole
	 * list is the run, that one sub-component is the whole definition, which
	 * is what keeps an ordinary zone's component small over any window. An
	 * `RRULE` generates nothing before its own `DTSTART`, which is why the
	 * window is anchored to the range the body covers rather than to the
	 * request: a rule that starts after the event it is meant to explain
	 * leaves that event with no observance to resolve against. Transitions
	 * before the run, and every transition where no terminal run exists, are
	 * written out individually instead, because a rule that does not hold is
	 * worse than no rule.
	 *
	 * @since 0.36.0
	 *
	 * @param string $type        Either `STANDARD` or `DAYLIGHT`.
	 * @param array  $transitions Transitions as `describe_transition()` returns them.
	 *
	 * @return string[] The sub-component lines, flattened.
	 */
	private function sub_component( string $type, array $transitions ): array {
		if ( array() === $transitions ) {
			return array();
		}

		$terminal = $this->terminal_rule( $transitions );
		$lines    = array();

		foreach ( $transitions as $index => $transition ) {
			$ruled = ( null !== $terminal && $terminal['index'] === $index );

			$lines[] = sprintf( 'BEGIN:%s', $type );
			$lines[] = sprintf( 'TZOFFSETFROM:%s', $this->as_utc_offset( $transition['from'] ) );
			$lines[] = sprintf( 'TZOFFSETTO:%s', $this->as_utc_offset( $transition['to'] ) );
			$lines[] = sprintf( 'TZNAME:%s', $transition['abbr'] );
			$lines[] = sprintf( 'DTSTART:%s', $transition['local'] );

			if ( $ruled ) {
				$lines[] = sprintf(
					'RRULE:FREQ=YEARLY;BYMONTH=%d;BYDAY=%s',
					$transition['month'],
					$terminal['byday']
				);
			}

			$lines[] = sprintf( 'END:%s', $type );

			if ( $ruled ) {
				break;
			}
		}

		return $lines;
	}

	/**
	 * The terminal run of transitions one unbounded yearly rule describes.
	 *
	 * Walked backwards from the last transition, because only a run that
	 * reaches the end of the enumerated window may be extended forever: a
	 * pattern the zone later abandons is already contradicted inside the list.
	 * A run needs at least two members, since one observation cannot establish
	 * a pattern, and every member must agree on month, weekday, wall clock,
	 * **both offsets and name**.
	 *
	 * The offsets are the half that is easy to leave out and expensive to get
	 * wrong. A zone can keep transitioning on the same yearly position while
	 * changing what it transitions *to*, which is exactly what a jurisdiction
	 * abolishing daylight saving looks like in tzdata: two first-Sunday-of-
	 * November transitions at 02:00, the first to -0700 and the second to
	 * -0600. Comparing position alone calls that pair a rule and keeps moving
	 * clients to an offset the zone stopped using, an hour wrong for every
	 * later date. The abbreviation is included for the same reason one step
	 * earlier: a name change is a policy change even where the offsets happen
	 * to coincide.
	 *
	 * The ordinal is resolved across the run rather than per transition. A
	 * "fourth Saturday" rule drifts between the fourth and the last week of
	 * its month year by year, so labeling each transition alone flaps between
	 * `4SA` and `-1SA` and breaks the run the zone actually follows. The run
	 * holds while either reading stays consistent: the same counted-forward
	 * ordinal, or every onset inside its month's final seven days. When both
	 * hold, the last-week form is emitted, matching how such rules are
	 * written.
	 *
	 * @since 0.36.0
	 *
	 * @param array $transitions Transitions as `describe_transition()` returns them.
	 *
	 * @return array{index:int,byday:string}|null The run's first index and its
	 *                                            `BYDAY`, or null when no rule holds.
	 */
	private function terminal_rule( array $transitions ): ?array {
		$count = count( $transitions );

		if ( $count < 2 ) {
			return null;
		}

		$last      = $transitions[ $count - 1 ];
		$signature = $this->base_signature( $last );
		$ordinal   = intdiv( $this->day_of( $last ) - 1, 7 ) + 1;
		$forward   = true;
		$last_week = $this->in_last_week( $last );
		$start     = $count - 1;

		while ( 0 < $start ) {
			$candidate     = $transitions[ $start - 1 ];
			$still_forward = $forward
				&& ( intdiv( $this->day_of( $candidate ) - 1, 7 ) + 1 ) === $ordinal;
			$still_last    = $last_week && $this->in_last_week( $candidate );

			if (
				$this->base_signature( $candidate ) !== $signature
				|| ( ! $still_forward && ! $still_last )
			) {
				break;
			}

			$forward   = $still_forward;
			$last_week = $still_last;
			--$start;
		}

		if ( ( $count - $start ) < 2 ) {
			return null;
		}

		return array(
			'index' => $start,
			'byday' => sprintf(
				'%d%s',
				$last_week ? -1 : $ordinal,
				substr( (string) $last['byday'], -2 )
			),
		);
	}

	/**
	 * A transition's yearly position, excluding the week-of-month ordinal.
	 *
	 * Month, weekday, wall clock, both offsets and name: everything a run must
	 * agree on outright. The ordinal is left to `terminal_rule()`, which has
	 * to resolve it across the run rather than per transition.
	 *
	 * @since 0.36.0
	 *
	 * @param array $transition One transition as `describe_transition()` returns it.
	 *
	 * @return string The comparable signature.
	 */
	private function base_signature( array $transition ): string {
		return sprintf(
			'%d:%s:%s:%d:%d:%s',
			$transition['month'],
			substr( (string) $transition['byday'], -2 ),
			substr( (string) $transition['local'], -6 ),
			$transition['from'],
			$transition['to'],
			$transition['abbr']
		);
	}

	/**
	 * The day of the month a transition's local onset falls on.
	 *
	 * Read from the number `describe_transition()` carried rather than from a
	 * fixed offset into the formatted onset, whose year is not fixed-width.
	 *
	 * @since 0.36.0
	 *
	 * @param array $transition One transition as `describe_transition()` returns it.
	 *
	 * @return int The day, 1 through 31.
	 */
	private function day_of( array $transition ): int {
		return (int) $transition['day'];
	}

	/**
	 * Whether a transition's local onset falls in its month's final seven days.
	 *
	 * Both numbers were carried by `describe_transition()`, so no re-parse of
	 * the formatted onset is involved.
	 *
	 * @since 0.36.0
	 *
	 * @param array $transition One transition as `describe_transition()` returns it.
	 *
	 * @return bool True when a "last weekday of the month" reading holds.
	 */
	private function in_last_week( array $transition ): bool {
		return ( (int) $transition['days_in_month'] - $this->day_of( $transition ) ) < 7;
	}

	/**
	 * Render an offset in seconds as the RFC 5545 `UTC-OFFSET` form.
	 *
	 * @since 0.36.0
	 *
	 * @param int $seconds Offset from UTC, negative to the west.
	 *
	 * @return string The offset as `+HHMM` or `-HHMM`.
	 */
	private function as_utc_offset( int $seconds ): string {
		$absolute = abs( $seconds );

		return sprintf(
			'%s%02d%02d',
			( $seconds < 0 ) ? '-' : '+',
			intdiv( $absolute, HOUR_IN_SECONDS ),
			intdiv( $absolute % HOUR_IN_SECONDS, MINUTE_IN_SECONDS )
		);
	}
}
