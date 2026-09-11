<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Timezone_Component.
 *
 * The behavior of this class through a real request is covered in
 * `Test_Calendar_Recurrence`, which is where the requirement lives: a feed
 * defines every timezone its components reference. This file exists for the
 * branches xdebug will not trace through a same-class delegation, since every
 * method below `render()` is private and reached only from it, plus the two
 * degenerate transition lists a real tz database never hands back.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Timezone_Component;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Timezone_Component.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Timezone_Component
 *
 * @since 0.36.0
 */
class Test_Timezone_Component extends Base {

	/**
	 * A transition list entry in the shape `DateTimeZone::getTransitions()` returns.
	 *
	 * @since 0.36.0
	 *
	 * @param string $moment UTC moment of the change, parseable by `strtotime()`.
	 * @param int    $offset Offset in force after the change, in seconds.
	 * @param bool   $isdst  Whether the offset after the change is a daylight one.
	 * @param string $abbr   Abbreviation in force after the change.
	 *
	 * @return array The entry.
	 */
	protected function transition( string $moment, int $offset, bool $isdst, string $abbr ): array {
		return array(
			'ts'     => strtotime( $moment . ' UTC' ),
			'time'   => $moment,
			'offset' => $offset,
			'isdst'  => $isdst,
			'abbr'   => $abbr,
		);
	}

	/**
	 * An identifier that names no zone produces no definition at all.
	 *
	 * A fixed UTC offset is never written into a `TZID` parameter, so it never
	 * reaches here in production. But an empty `VTIMEZONE` would be worse than
	 * none, and `new DateTimeZone( 'UTC+5' )` throws.
	 *
	 * @covers ::build
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_an_unnamed_timezone_produces_no_definition(): void {
		$instance = Timezone_Component::get_instance();

		$this->assertSame(
			'',
			Utility::invoke_hidden_method( $instance, 'build', array( 'UTC+5', time(), time() ) ),
			'A fixed UTC offset cannot be named in a TZID and so defines nothing.'
		);
		$this->assertSame(
			'',
			Utility::invoke_hidden_method( $instance, 'build', array( 'Mars/Olympus_Mons', time(), time() ) ),
			'An identifier absent from the tz database defines nothing either.'
		);
		$this->assertSame(
			'',
			$instance->render( 'Mars/Olympus_Mons' ),
			'And the memoizing entry point answers the same way.'
		);
	}

	/**
	 * A named identifier is wrapped in the component that declares it.
	 *
	 * @covers ::build
	 *
	 * @return void
	 */
	public function test_a_named_timezone_is_wrapped_and_declared(): void {
		$component = Utility::invoke_hidden_method(
			Timezone_Component::get_instance(),
			'build',
			array( 'Europe/Berlin', time(), time() )
		);

		$this->assertStringStartsWith( "BEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\n", $component );
		$this->assertStringEndsWith( "\r\nEND:VTIMEZONE", $component );
	}

	/**
	 * The second and later calls for one identifier and range are served from
	 * the memo, and a different range is not.
	 *
	 * A feed commonly carries many events in one zone; the transition list is
	 * the same for all of them and reading it is the expensive part. It is only
	 * the same for the same range, though, which is why the range is part of
	 * the memo key: serving a definition built for 2026 to a body reaching back
	 * to 2020 is the defect the range exists to fix, reintroduced by the cache.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_a_definition_is_built_once_per_identifier_and_range(): void {
		$instance = Timezone_Component::get_instance();
		$early    = strtotime( '2020-01-15 00:00:00 UTC' );
		$late     = strtotime( '2026-01-15 00:00:00 UTC' );
		$first    = $instance->render( 'Europe/Berlin', $early, $late );

		Utility::set_and_get_hidden_property(
			$instance,
			'rendered',
			array( sprintf( 'Europe/Berlin|%d|%d', $early, $late ) => 'SENTINEL' )
		);

		$this->assertNotSame( '', $first, 'The first call must build something for the memo to hold.' );
		$this->assertSame(
			'SENTINEL',
			$instance->render( 'Europe/Berlin', $early, $late ),
			'A second call for the same identifier and range must be served from the memo rather than rebuilt.'
		);
		$this->assertNotSame(
			'SENTINEL',
			$instance->render( 'Europe/Berlin', $late, $late ),
			'A different range is a different definition and must not be served from the same entry.'
		);

		Utility::set_and_get_hidden_property( $instance, 'rendered', array() );
	}

	/**
	 * A rule with no last instant extends the range to the open-ended horizon.
	 *
	 * An open-ended series names no final date, so the only concrete moments in
	 * its component are its own start and end, which would bound the window to
	 * a single day and leave the definition unable to describe the dates the
	 * rule goes on producing. Both arms of the check matter: an `UNTIL`-bounded
	 * rule must *not* extend the window, or every feed on the site pays for
	 * transitions nothing refers to.
	 *
	 * `COUNT` is on the open-ended side, which is the correction here. The
	 * window is assembled from the date-time literals the body contains, and
	 * `COUNT` writes none, so it tells the window nothing while still naming
	 * occurrences years out. See the dedicated test below.
	 *
	 * Invoked directly because xdebug does not trace a private helper reached
	 * through a same-class delegation.
	 *
	 * @covers ::has_open_ended_rule
	 * @covers ::range_of
	 *
	 * @return void
	 */
	public function test_an_open_ended_rule_extends_the_range_forward(): void {
		$instance = Timezone_Component::get_instance();
		$bounded  = "BEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:20200115T140000\r\n"
			. "RRULE:FREQ=WEEKLY;COUNT=5\r\nEND:VEVENT";
		$open     = "BEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:20200115T140000\r\n"
			. "RRULE:FREQ=WEEKLY\r\nEND:VEVENT";

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'has_open_ended_rule', array( $bounded ) ),
			'A COUNT bound writes no date-time literal, so it cannot bound the window.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'has_open_ended_rule', array( $open ) ),
			'A rule carrying no UNTIL does not name a last instant in the body.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'has_open_ended_rule',
				array( "BEGIN:VEVENT\r\nRRULE:FREQ=WEEKLY;UNTIL=20301231T000000Z\r\nEND:VEVENT" )
			),
			'An UNTIL bound names it too.'
		);

		$until = "BEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:20200115T140000\r\n"
			. "RRULE:FREQ=WEEKLY;UNTIL=20200219T130000Z\r\nEND:VEVENT";

		[ , $bounded_end ] = Utility::invoke_hidden_method( $instance, 'range_of', array( $until ) );
		[ , $open_end ]    = Utility::invoke_hidden_method( $instance, 'range_of', array( $open ) );

		$this->assertGreaterThan(
			$bounded_end,
			$open_end,
			'An open-ended rule must reach past the last concrete date written beside it.'
		);
		$this->assertGreaterThan(
			time() + ( 70 * YEAR_IN_SECONDS ),
			$open_end,
			'And it must reach decades past the tz database\'s enumerated knowledge, not a sampling window.'
		);
	}

	/**
	 * A `COUNT` bound does not shorten the window to before its own occurrences.
	 *
	 * `COUNT` names a number of occurrences, never a date, so unlike `UNTIL` it
	 * puts no date-time literal in the body for the window to be built from.
	 * Counting it as a bound ended the window at `max( DTSTART, DTEND, now )`
	 * plus three years while the rule went on naming occurrences well past it,
	 * and every one of those resolved against a terminal `RRULE` inferred from
	 * two samples rather than against a described transition.
	 *
	 * `Rule::MAX_COUNT` is 730, so this is not a corner case: a weekly rule at
	 * the cap runs fourteen years. Measured on the committed class over
	 * `FREQ=WEEKLY;COUNT=730`, 44 of 730 occurrences resolved an hour wrong in
	 * `Asia/Gaza`, first at 2035-03-24.
	 *
	 * Invoked directly because xdebug does not trace a private helper reached
	 * through a same-class delegation.
	 *
	 * @covers ::has_open_ended_rule
	 * @covers ::range_of
	 *
	 * @return void
	 */
	public function test_a_count_bound_does_not_shorten_the_window(): void {
		$instance = Timezone_Component::get_instance();
		$anchor   = '20260101T180000';
		$body     = "BEGIN:VEVENT\r\nDTSTART;TZID=Asia/Gaza:{$anchor}\r\n"
			. "RRULE:FREQ=WEEKLY;COUNT=730\r\nEND:VEVENT";

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'has_open_ended_rule', array( $body ) ),
			'Failed to assert a COUNT rule is treated as reaching past the literals in its body.'
		);

		[ , $end ] = Utility::invoke_hidden_method( $instance, 'range_of', array( $body ) );

		// 730 weekly occurrences counting the anchor as the first puts the last
		// one 729 weeks out, a little over fourteen years.
		$last = strtotime( '2026-01-01 18:00:00 +729 weeks' );

		$this->assertGreaterThan(
			$last,
			$end,
			'Failed to assert the definition window covers the last occurrence a COUNT rule names.'
		);
	}

	/**
	 * An unparsable date-time token in a body is skipped rather than counted.
	 *
	 * @covers ::range_of
	 *
	 * @return void
	 */
	public function test_an_unparsable_moment_does_not_widen_the_range(): void {
		$instance = Timezone_Component::get_instance();

		[ $start, $end ] = Utility::invoke_hidden_method(
			$instance,
			'range_of',
			array( "BEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:99999999T999999\r\nEND:VEVENT" )
		);

		$this->assertSame(
			$start,
			$end,
			'A body with no readable moment collapses to the present rather than to a nonsense span.'
		);
	}

	/**
	 * No property value can push the window past the open-ended horizon.
	 *
	 * The horizon is the widest span the class itself claims a definition can
	 * need. A value past it, whatever wrote it, buys the same definition at a
	 * `getTransitions()` cost that grows with the requested span, so the
	 * window is clamped rather than trusted. A far-future value inside the
	 * horizon still widens the window as designed, because the definition has
	 * to cover it.
	 *
	 * @covers ::range_of
	 *
	 * @return void
	 */
	public function test_the_range_is_clamped_to_the_open_ended_horizon(): void {
		$instance = Timezone_Component::get_instance();

		[ , $legitimate_end ] = Utility::invoke_hidden_method(
			$instance,
			'range_of',
			array(
				sprintf(
					"BEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:%s\r\nEND:VEVENT",
					gmdate( 'Ymd\THis', time() + ( 40 * YEAR_IN_SECONDS ) )
				),
			)
		);

		$this->assertEqualsWithDelta(
			time() + ( 40 * YEAR_IN_SECONDS ),
			$legitimate_end,
			5,
			'A far-future instant inside the horizon must still widen the window as designed.'
		);

		[ , $clamped_end ] = Utility::invoke_hidden_method(
			$instance,
			'range_of',
			array( "BEGIN:VEVENT\r\nDTSTART;TZID=Europe/Berlin:99991231T235959\r\nEND:VEVENT" )
		);

		$this->assertEqualsWithDelta(
			time() + ( 75 * YEAR_IN_SECONDS ),
			$clamped_end,
			5,
			'An instant past the horizon is clamped to it rather than handed to getTransitions().'
		);
	}

	/**
	 * A definition window crossing year 10000 still collapses to a rule.
	 *
	 * `gmdate( 'Ymd\THis' )` writes a five-digit year past 9999, so a parse
	 * that indexes the local onset at fixed byte offsets reads garbage for
	 * every transition after the boundary, the terminal-rule walk breaks on
	 * its first candidate, and roughly sixteen thousand transitions are
	 * written out individually instead of collapsing behind two ruled
	 * sub-components. Measured as a cliff at exactly the four-digit-year
	 * boundary: 342 bytes at end year 9996, 1,674,990 bytes at end year
	 * 10121. The positional reads have to carry the day and the month length
	 * as numbers, so the two renders below stay byte-identical: the wider
	 * window only adds transitions the terminal rule already generates.
	 *
	 * @covers ::render
	 * @covers ::terminal_rule
	 * @covers ::day_of
	 * @covers ::in_last_week
	 *
	 * @return void
	 */
	public function test_a_window_crossing_year_ten_thousand_still_collapses_to_a_rule(): void {
		$instance = Timezone_Component::get_instance();
		$now      = time();
		$bounded  = $instance->render( 'America/New_York', $now, $now + ( 7000 * YEAR_IN_SECONDS ) );
		$crossing = $instance->render( 'America/New_York', $now, $now + ( 8100 * YEAR_IN_SECONDS ) );

		$this->assertSame(
			2,
			substr_count( $bounded, 'RRULE:' ),
			'Failed to collapse the four-digit-year render, so the comparison below would prove nothing.'
		);
		$this->assertSame(
			$bounded,
			$crossing,
			'A window past year 10000 adds only transitions the terminal rule already generates, so'
			. ' the definition must not change, let alone enumerate them.'
		);
	}

	/**
	 * An empty transition list still yields a usable standard sub-component.
	 *
	 * `getTransitions()` always returns at least the entry describing the state
	 * at the range start, so this is the defensive arm rather than an authored
	 * one. The fallback still has to name an offset, and a `VTIMEZONE` carrying
	 * no sub-component at all is invalid.
	 *
	 * @covers ::sub_components
	 *
	 * @return void
	 */
	public function test_an_empty_transition_list_falls_back_to_utc(): void {
		$this->assertSame(
			array(
				'BEGIN:STANDARD',
				'TZOFFSETFROM:+0000',
				'TZOFFSETTO:+0000',
				'TZNAME:UTC',
				'DTSTART:19700101T000000',
				'END:STANDARD',
			),
			Utility::invoke_hidden_method(
				Timezone_Component::get_instance(),
				'sub_components',
				array( array() )
			),
			'With nothing to describe, the definition still declares an offset rather than being empty.'
		);
	}

	/**
	 * A list holding only the range-start entry describes one unchanging offset.
	 *
	 * @covers ::sub_components
	 *
	 * @return void
	 */
	public function test_a_list_with_no_changes_describes_one_unchanging_offset(): void {
		$this->assertSame(
			array(
				'BEGIN:STANDARD',
				'TZOFFSETFROM:+0530',
				'TZOFFSETTO:+0530',
				'TZNAME:IST',
				'DTSTART:19700101T000000',
				'END:STANDARD',
			),
			Utility::invoke_hidden_method(
				Timezone_Component::get_instance(),
				'sub_components',
				array( array( $this->transition( '2026-01-01 00:00:00', 19800, false, 'IST' ) ) )
			),
			'A zone that never changes moves from its own offset to itself, with no rule.'
		);
	}

	/**
	 * Standard precedes daylight whichever the range happened to open in.
	 *
	 * The window starts wherever "now" falls, so a zone read in July opens on a
	 * daylight offset and the same zone read in January opens on a standard one.
	 * The emitted definition must not depend on that.
	 *
	 * @covers ::sub_components
	 *
	 * @return void
	 */
	public function test_standard_precedes_daylight_whichever_the_window_opens_in(): void {
		$opening_in_daylight = array(
			$this->transition( '2026-07-01 00:00:00', -14400, true, 'EDT' ),
			$this->transition( '2026-11-01 06:00:00', -18000, false, 'EST' ),
			$this->transition( '2027-03-14 07:00:00', -14400, true, 'EDT' ),
		);

		$lines = Utility::invoke_hidden_method(
			Timezone_Component::get_instance(),
			'sub_components',
			array( $opening_in_daylight )
		);

		$this->assertSame( 'BEGIN:STANDARD', $lines[0], 'The standard sub-component is written first.' );
		$this->assertContains( 'BEGIN:DAYLIGHT', $lines, 'And the daylight one after it.' );
		$this->assertLessThan(
			array_search( 'BEGIN:DAYLIGHT', $lines, true ),
			array_search( 'BEGIN:STANDARD', $lines, true ),
			'Their order is fixed rather than following the order the transitions arrived in.'
		);
	}

	/**
	 * A transition is placed by the offset it leaves, and named by its position
	 * in the month.
	 *
	 * @covers ::describe_transition
	 *
	 * @return void
	 */
	public function test_describe_transition_places_and_names_a_change(): void {
		$instance = Timezone_Component::get_instance();

		// The European autumn change: 01:00 UTC, leaving CEST (+0200), on the
		// last Sunday of October.
		$this->assertSame(
			array(
				'from'          => 7200,
				'to'            => 3600,
				'abbr'          => 'CET',
				'local'         => '20261025T030000',
				'day'           => 25,
				'days_in_month' => 31,
				'month'         => 10,
				'byday'         => '-1SU',
			),
			Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( '2026-10-25 01:00:00', 3600, false, 'CET' ), 7200 )
			),
			'A change in the last seven days of its month is the last weekday, timed in the offset it leaves.'
		);

		// The US spring change: 07:00 UTC, leaving EST (-0500), on the second
		// Sunday of March.
		$this->assertSame(
			array(
				'from'          => -18000,
				'to'            => -14400,
				'abbr'          => 'EDT',
				'local'         => '20260308T020000',
				'day'           => 8,
				'days_in_month' => 31,
				'month'         => 3,
				'byday'         => '2SU',
			),
			Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( '2026-03-08 07:00:00', -14400, true, 'EDT' ), -18000 )
			),
			'A change earlier in its month counts forward from the first weekday.'
		);
	}

	/**
	 * Nothing to describe produces no sub-component rather than an empty one.
	 *
	 * The arm a zone that has abolished daylight saving lands on: it has
	 * standard transitions in the window and no daylight ones.
	 *
	 * @covers ::sub_component
	 *
	 * @return void
	 */
	public function test_a_type_with_no_transitions_emits_nothing(): void {
		$this->assertSame(
			array(),
			Utility::invoke_hidden_method(
				Timezone_Component::get_instance(),
				'sub_component',
				array( 'DAYLIGHT', array() )
			)
		);
	}

	/**
	 * Irregular transitions are enumerated rather than given a rule that would
	 * misdescribe them.
	 *
	 * The zone this stands in for is a real one: a jurisdiction that moves its
	 * change date, or adopts and then drops daylight saving mid-window. Writing
	 * an unbounded yearly `RRULE` from the first of those would claim a rule the
	 * zone does not follow, for every year after the window. That is worse than
	 * enumerating, which is merely incomplete.
	 *
	 * @covers ::sub_component
	 * @covers ::terminal_rule
	 *
	 * @return void
	 */
	public function test_irregular_transitions_are_enumerated_without_a_rule(): void {
		$instance  = Timezone_Component::get_instance();
		$irregular = array(
			Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( '2026-03-08 07:00:00', -14400, true, 'EDT' ), -18000 )
			),
			// The following year, a month later and on a different ordinal.
			Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( '2027-04-11 07:00:00', -14400, true, 'EDT' ), -18000 )
			),
		);

		$lines = Utility::invoke_hidden_method( $instance, 'sub_component', array( 'DAYLIGHT', $irregular ) );

		$this->assertSame(
			2,
			count( array_keys( $lines, 'BEGIN:DAYLIGHT', true ) ),
			'Two transitions that do not agree are written out as two sub-components.'
		);
		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$lines,
					static function ( string $line ): bool {
						return str_starts_with( $line, 'RRULE:' );
					}
				)
			),
			'Neither carries a rule, because no single rule describes both.'
		);
	}

	/**
	 * Transitions that agree collapse to one sub-component carrying an unbounded
	 * yearly rule.
	 *
	 * This is what lets a definition read three years of transitions and still
	 * describe an open-ended series correctly: the rule extends the pattern in
	 * both directions, where an enumeration would run out.
	 *
	 * @covers ::sub_component
	 * @covers ::terminal_rule
	 *
	 * @return void
	 */
	public function test_regular_transitions_collapse_to_one_sub_component_with_a_rule(): void {
		$instance = Timezone_Component::get_instance();
		$regular  = array(
			Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( '2026-03-08 07:00:00', -14400, true, 'EDT' ), -18000 )
			),
			Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( '2027-03-14 07:00:00', -14400, true, 'EDT' ), -18000 )
			),
		);

		$this->assertSame(
			array(
				'BEGIN:DAYLIGHT',
				'TZOFFSETFROM:-0500',
				'TZOFFSETTO:-0400',
				'TZNAME:EDT',
				'DTSTART:20260308T020000',
				'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
				'END:DAYLIGHT',
			),
			Utility::invoke_hidden_method( $instance, 'sub_component', array( 'DAYLIGHT', $regular ) ),
			'Two second-Sundays-of-March become one sub-component ruled to every second Sunday of March.'
		);
	}

	/**
	 * One observation is not a pattern.
	 *
	 * @covers ::terminal_rule
	 *
	 * @return void
	 */
	public function test_regularity_needs_more_than_one_observation(): void {
		$instance   = Timezone_Component::get_instance();
		$transition = Utility::invoke_hidden_method(
			$instance,
			'describe_transition',
			array( $this->transition( '2026-03-08 07:00:00', -14400, true, 'EDT' ), -18000 )
		);

		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'terminal_rule', array( array( $transition ) ) ),
			'A single transition cannot establish a rule that will be written as unbounded.'
		);
		$this->assertSame(
			array(
				'index' => 0,
				'byday' => '2SU',
			),
			Utility::invoke_hidden_method( $instance, 'terminal_rule', array( array( $transition, $transition ) ) ),
			'Two that agree on month, ordinal and wall clock do, from the first of them.'
		);
	}

	/**
	 * An irregular prefix is enumerated and a regular tail becomes the rule.
	 *
	 * The shape an open-ended series in a settling zone produces: some years
	 * are decided one by one, then a stable policy takes over. The prefix is
	 * written out, the tail collapses to one unbounded rule anchored at its
	 * first onset, and nothing after the anchor is emitted, because the rule
	 * generates it.
	 *
	 * @covers ::sub_component
	 * @covers ::terminal_rule
	 *
	 * @return void
	 */
	public function test_an_irregular_prefix_yields_to_a_terminal_rule(): void {
		$instance    = Timezone_Component::get_instance();
		$describe    = function ( string $moment ) use ( $instance ): array {
			return Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( $moment, -14400, true, 'EDT' ), -18000 )
			);
		};
		$transitions = array(
			// One off-pattern year, then the second-Sunday-of-March policy.
			$describe( '2027-04-11 07:00:00' ),
			$describe( '2028-03-12 07:00:00' ),
			$describe( '2029-03-11 07:00:00' ),
		);

		$this->assertSame(
			array(
				'BEGIN:DAYLIGHT',
				'TZOFFSETFROM:-0500',
				'TZOFFSETTO:-0400',
				'TZNAME:EDT',
				'DTSTART:20270411T020000',
				'END:DAYLIGHT',
				'BEGIN:DAYLIGHT',
				'TZOFFSETFROM:-0500',
				'TZOFFSETTO:-0400',
				'TZNAME:EDT',
				'DTSTART:20280312T020000',
				'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU',
				'END:DAYLIGHT',
			),
			Utility::invoke_hidden_method( $instance, 'sub_component', array( 'DAYLIGHT', $transitions ) ),
			'The off-pattern year is written out; the settled policy is one rule from its first onset.'
		);
	}

	/**
	 * A rule that drifts between the fourth and the last week still reads as
	 * one run, and a broken drift does not.
	 *
	 * A "fourth Saturday" rule lands on days 22 through 28, which straddles
	 * the last-seven-days boundary of a 31-day month, so per-transition
	 * ordinal labels flap between `4SA` and `-1SA` for onsets the zone decides
	 * by one rule. The run has to hold while either consistent reading holds,
	 * and emit the last-week form when that is the consistent one.
	 *
	 * @covers ::terminal_rule
	 *
	 * @return void
	 */
	public function test_a_terminal_rule_resolves_its_ordinal_across_the_run(): void {
		$instance = Timezone_Component::get_instance();
		$describe = function ( string $moment ) use ( $instance ): array {
			return Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( $moment, 10800, true, 'EEST' ), 7200 )
			);
		};

		// Fourth Saturdays of March: day 27 (2027), day 25 (2028), day 24
		// (2029). The first two are also in the last week; the third is not,
		// so only the counted-forward reading survives the whole run.
		$fourth = array(
			$describe( '2027-03-26 22:00:00' ),
			$describe( '2028-03-24 22:00:00' ),
			$describe( '2029-03-23 22:00:00' ),
		);

		$this->assertSame(
			array(
				'index' => 0,
				'byday' => '4SA',
			),
			Utility::invoke_hidden_method( $instance, 'terminal_rule', array( $fourth ) ),
			'A run consistent as "the fourth Saturday" is one rule despite per-year label flapping.'
		);

		// Last Sundays of October: day 25 (2026), day 31 (2027). Different
		// forward ordinals, so only the last-week reading holds.
		$last = array(
			$describe( '2026-10-24 22:00:00' ),
			$describe( '2027-10-30 22:00:00' ),
		);

		$this->assertSame(
			array(
				'index' => 0,
				'byday' => '-1SU',
			),
			Utility::invoke_hidden_method( $instance, 'terminal_rule', array( $last ) ),
			'A run consistent as "the last Sunday" emits the last-week form.'
		);

		// Day 7 and day 26: same weekday, but neither the counted-forward nor
		// the last-week reading survives, so no rule.
		$broken = array(
			$describe( '2027-03-06 22:00:00' ),
			$describe( '2028-03-25 22:00:00' ),
		);

		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'terminal_rule', array( $broken ) ),
			'Onsets agreeing on nothing but the weekday are not a rule.'
		);
	}

	/**
	 * A month change breaks the run even where the tail agrees with itself.
	 *
	 * @covers ::terminal_rule
	 *
	 * @return void
	 */
	public function test_a_terminal_rule_starts_where_the_signature_settles(): void {
		$instance = Timezone_Component::get_instance();
		$describe = function ( string $moment ) use ( $instance ): array {
			return Utility::invoke_hidden_method(
				$instance,
				'describe_transition',
				array( $this->transition( $moment, -14400, true, 'EDT' ), -18000 )
			);
		};

		$this->assertSame(
			1,
			Utility::invoke_hidden_method(
				$instance,
				'terminal_rule',
				array(
					array(
						$describe( '2027-04-11 07:00:00' ),
						$describe( '2028-03-12 07:00:00' ),
						$describe( '2029-03-11 07:00:00' ),
					),
				)
			)['index'],
			'The run reaches back only as far as the settled signature does.'
		);
	}

	/**
	 * The positional helpers read a transition the way the run logic needs.
	 *
	 * Invoked directly because xdebug does not trace private helpers reached
	 * through a same-class loop.
	 *
	 * @covers ::base_signature
	 * @covers ::day_of
	 * @covers ::in_last_week
	 *
	 * @return void
	 */
	public function test_the_positional_helpers_read_a_transition(): void {
		$instance   = Timezone_Component::get_instance();
		$transition = Utility::invoke_hidden_method(
			$instance,
			'describe_transition',
			array( $this->transition( '2026-10-25 01:00:00', 3600, false, 'CET' ), 7200 )
		);

		$this->assertSame(
			'10:SU:030000:7200:3600:CET',
			Utility::invoke_hidden_method( $instance, 'base_signature', array( $transition ) ),
			'The signature carries position, wall clock, both offsets and the name, without the ordinal.'
		);
		$this->assertSame(
			25,
			Utility::invoke_hidden_method( $instance, 'day_of', array( $transition ) ),
			'The day is read off the local onset.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'in_last_week', array( $transition ) ),
			'The 25th of October is inside its final seven days.'
		);

		$early = Utility::invoke_hidden_method(
			$instance,
			'describe_transition',
			array( $this->transition( '2026-10-04 01:00:00', 3600, false, 'CET' ), 7200 )
		);

		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'in_last_week', array( $early ) ),
			'The 4th is not.'
		);
	}

	/**
	 * Offsets are rendered in the RFC 5545 `UTC-OFFSET` form on both sides of
	 * zero, and to the minute.
	 *
	 * @covers ::as_utc_offset
	 *
	 * @return void
	 */
	public function test_offsets_render_on_both_sides_of_zero_and_to_the_minute(): void {
		$instance = Timezone_Component::get_instance();

		$this->assertSame(
			array( '-0500', '+0100', '+0000', '+0530', '-0930', '+1400' ),
			array_map(
				static function ( int $seconds ) use ( $instance ): string {
					return Utility::invoke_hidden_method( $instance, 'as_utc_offset', array( $seconds ) );
				},
				array( -18000, 3600, 0, 19800, -34200, 50400 )
			),
			'Zones west of UTC are negative, zero is positive by convention, and half-hour zones keep their minutes.'
		);
	}
}
