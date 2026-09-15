<?php
/**
 * Class handles the `RRULE` round-trip: serialize a rule, re-parse the
 * serialized text with an independent reader, and prove both produce the same
 * occurrence set.
 *
 * The reader below is written from RFC 5545 rather than from
 * `Rule::to_rrule_string()`. That separation is the whole point: a round-trip
 * test whose two halves share an implementation passes whenever they share a
 * bug. This one reads `UNTIL` the way the RFC defines it, as a UTC date-time
 * whose value type must match `DTSTART`'s, so a serializer that emits a bare
 * `DATE`, or that stamps a `Z` onto a local wall clock, produces a different
 * end date here and therefore a different occurrence set.
 *
 * Every fixture is chosen so that the right answer and the wrong answer differ.
 * `test_fixture_sets_are_multi_valued_and_distinct()` is the guard that keeps it
 * that way.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event\Recurrence\Expander;
use GatherPress\Core\Event\Recurrence\Rule;
use GatherPress\Tests\Base;

/**
 * Class Test_Rrule_Round_Trip.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Rule
 *
 * @since 0.36.0
 */
class Test_Rrule_Round_Trip extends Base {

	/**
	 * RFC 5545 two-letter weekday codes, declared here rather than read from
	 * `Rule::WEEKDAY_CODES`.
	 *
	 * Sharing the constant would make a reordering of it invisible: the
	 * serializer and the reader would agree on the wrong letters and the
	 * round trip would still close.
	 *
	 * @since 0.36.0
	 *
	 * @var array<string, int>
	 */
	const WEEKDAY_INDEX = array(
		'SU' => 0,
		'MO' => 1,
		'TU' => 2,
		'WE' => 3,
		'TH' => 4,
		'FR' => 5,
		'SA' => 6,
	);

	/**
	 * The fixture table.
	 *
	 * Each entry states its own expected occurrence set, so the round-trip
	 * comparison cannot pass by both halves being equally wrong. The `catches`
	 * key records what the fixture is aimed at.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<string, mixed>> Fixtures keyed by name.
	 */
	public function fixtures(): array {
		return array(
			// A far-eastern zone whose evening wall clock falls on the previous
			// UTC date. Serializing `UNTIL` by stamping a `Z` onto the local
			// wall clock reads back as 2026-11-14 in Auckland and adds a sixth
			// occurrence; emitting a bare `Ymd` DATE fails the value-type
			// assertion outright.
			'until_evening_in_a_far_eastern_zone' => array(
				'catches'  => 'A local-wall-clock `UNTIL` stamped with `Z`, and a DATE-typed `UNTIL`.',
				'values'   => array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'until',
					'until'     => '2026-11-13',
				),
				'timezone' => 'Pacific/Auckland',
				'anchor'   => '2026-11-10 22:00:00',
				'through'  => '2026-11-30 23:59:59',
				'expected' => array(
					'2026-11-10 22:00:00+13:00 / 20261110T090000Z',
					'2026-11-11 22:00:00+13:00 / 20261111T090000Z',
					'2026-11-12 22:00:00+13:00 / 20261112T090000Z',
					'2026-11-13 22:00:00+13:00 / 20261113T090000Z',
				),
				'rrule'    => 'FREQ=DAILY;UNTIL=20261113T090000Z',
			),
			// The end date sits on the far side of a daylight saving change
			// from the anchor, and the wall clock is just after midnight.
			// Resolving `UNTIL`'s offset from the anchor's date rather than
			// from the end date loses an hour, which rolls the end date back to
			// 2026-11-03 and drops the last occurrence.
			'until_after_a_dst_change_at_00_30'   => array(
				'catches'  => 'An `UNTIL` offset taken from the anchor date rather than the end date.',
				'values'   => array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'until',
					'until'     => '2026-11-04',
				),
				'timezone' => 'America/Los_Angeles',
				'anchor'   => '2026-10-29 00:30:00',
				'through'  => '2026-11-30 23:59:59',
				'expected' => array(
					'2026-10-29 00:30:00-07:00 / 20261029T073000Z',
					'2026-10-30 00:30:00-07:00 / 20261030T073000Z',
					'2026-10-31 00:30:00-07:00 / 20261031T073000Z',
					'2026-11-01 00:30:00-07:00 / 20261101T073000Z',
					'2026-11-02 00:30:00-08:00 / 20261102T083000Z',
					'2026-11-03 00:30:00-08:00 / 20261103T083000Z',
					'2026-11-04 00:30:00-08:00 / 20261104T083000Z',
				),
				'rrule'    => 'FREQ=DAILY;UNTIL=20261104T083000Z',
			),
			// The guide's reference rule. A `BYDAY` list that loses its order,
			// its interval, or a weekday produces a different set of dates,
			// and the anchor is a Thursday so the Monday-start week bucket is
			// load-bearing.
			'count_bounded_weekly_byday'          => array(
				'catches'  => 'A dropped `INTERVAL`, a reordered `BYDAY`, or a `COUNT` lost to an end bound.',
				'values'   => array(
					'frequency' => 'weekly',
					'interval'  => 2,
					'weekdays'  => array( 2, 4 ),
					'end_type'  => 'count',
					'count'     => 5,
				),
				'timezone' => 'America/New_York',
				'anchor'   => '2026-09-03 18:00:00',
				'through'  => '2026-12-31 23:59:59',
				'expected' => array(
					'2026-09-03 18:00:00-04:00 / 20260903T220000Z',
					'2026-09-15 18:00:00-04:00 / 20260915T220000Z',
					'2026-09-17 18:00:00-04:00 / 20260917T220000Z',
					'2026-09-29 18:00:00-04:00 / 20260929T220000Z',
					'2026-10-01 18:00:00-04:00 / 20261001T220000Z',
				),
				'rrule'    => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=TU,TH;COUNT=5',
			),
			// Open-ended, so the serialized text must carry no end bound at
			// all. A spurious `UNTIL` or `COUNT` truncates the set. The range
			// also crosses Berlin's spring-forward, so the UTC instants move by
			// an hour while the wall clock does not.
			'open_ended_weekly_across_dst'        => array(
				'catches'  => 'A spurious end bound on a never-ending rule, and a UTC-anchored expansion.',
				'values'   => array(
					'frequency' => 'weekly',
					'interval'  => 1,
					'weekdays'  => array( 1, 3 ),
					'end_type'  => 'never',
				),
				'timezone' => 'Europe/Berlin',
				'anchor'   => '2026-03-23 19:00:00',
				'through'  => '2026-04-15 23:59:59',
				'expected' => array(
					'2026-03-23 19:00:00+01:00 / 20260323T180000Z',
					'2026-03-25 19:00:00+01:00 / 20260325T180000Z',
					'2026-03-30 19:00:00+02:00 / 20260330T170000Z',
					'2026-04-01 19:00:00+02:00 / 20260401T170000Z',
					'2026-04-06 19:00:00+02:00 / 20260406T170000Z',
					'2026-04-08 19:00:00+02:00 / 20260408T170000Z',
					'2026-04-13 19:00:00+02:00 / 20260413T170000Z',
					'2026-04-15 19:00:00+02:00 / 20260415T170000Z',
				),
				'rrule'    => 'FREQ=WEEKLY;BYDAY=MO,WE',
			),
			// "The last Wednesday of the month", asserted as the six exact
			// dates rather than as "a Wednesday": 2026-04-29 and 2026-05-27 are
			// the fifth Wednesday of their months, so an ordinal that decays to
			// `4WE` lands on the 22nd and the 20th instead.
			'monthly_last_weekday'                => array(
				'catches'  => 'An ordinal lost from `BYDAY`, or `-1WE` read as `4WE`.',
				'values'   => array(
					'frequency'       => 'monthly',
					'interval'        => 1,
					'monthly_mode'    => 'nth_weekday',
					'monthly_ordinal' => -1,
					'monthly_weekday' => 3,
					'end_type'        => 'count',
					'count'           => 6,
				),
				'timezone' => 'America/New_York',
				'anchor'   => '2026-01-28 19:00:00',
				'through'  => '2026-12-31 23:59:59',
				'expected' => array(
					'2026-01-28 19:00:00-05:00 / 20260129T000000Z',
					'2026-02-25 19:00:00-05:00 / 20260226T000000Z',
					'2026-03-25 19:00:00-04:00 / 20260325T230000Z',
					'2026-04-29 19:00:00-04:00 / 20260429T230000Z',
					'2026-05-27 19:00:00-04:00 / 20260527T230000Z',
					'2026-06-24 19:00:00-04:00 / 20260624T230000Z',
				),
				'rrule'    => 'FREQ=MONTHLY;BYDAY=-1WE;COUNT=6',
			),
			// A daylight saving change inside the range, with the wall clock
			// held and the UTC instant moving. 2026-03-01 is 14:00Z and
			// 2026-03-08 is 13:00Z: an expansion anchored to UTC would keep
			// 14:00Z and drift the local time to 10:00.
			'weekly_across_a_spring_forward'      => array(
				'catches'  => 'A UTC-anchored expansion, which holds the instant and drifts the wall clock.',
				'values'   => array(
					'frequency' => 'weekly',
					'interval'  => 1,
					'weekdays'  => array( 0 ),
					'end_type'  => 'count',
					'count'     => 4,
				),
				'timezone' => 'America/New_York',
				'anchor'   => '2026-03-01 09:00:00',
				'through'  => '2026-12-31 23:59:59',
				'expected' => array(
					'2026-03-01 09:00:00-05:00 / 20260301T140000Z',
					'2026-03-08 09:00:00-04:00 / 20260308T130000Z',
					'2026-03-15 09:00:00-04:00 / 20260315T130000Z',
					'2026-03-22 09:00:00-04:00 / 20260322T130000Z',
				),
				'rrule'    => 'FREQ=WEEKLY;BYDAY=SU;COUNT=4',
			),
		);
	}

	/**
	 * Read an `RRULE` value back into the decomposed value array, from RFC 5545.
	 *
	 * Deliberately not the inverse of `to_rrule_string()`'s implementation.
	 * See the file docblock. `UNTIL` is required to be a UTC date-time,
	 * because the component this rule is attached to emits a `DATE-TIME`
	 * `DTSTART` and RFC 5545 section 3.3.10 requires the two value types to
	 * match. It is then converted into the series timezone, which is where the
	 * inclusive end *date* the expander compares against lives.
	 *
	 * @since 0.36.0
	 *
	 * @param string       $rrule    The `RRULE` value, without the property name.
	 * @param DateTimeZone $timezone Series timezone.
	 *
	 * @return array The decomposed values, shaped for `Rule::from_array()`.
	 */
	protected function parse_rrule( string $rrule, DateTimeZone $timezone ): array {
		$parts = array();

		foreach ( explode( ';', $rrule ) as $pair ) {
			$split              = array_pad( explode( '=', $pair, 2 ), 2, '' );
			$parts[ $split[0] ] = $split[1];
		}

		$values = array(
			'frequency' => strtolower( (string) ( $parts['FREQ'] ?? '' ) ),
			'interval'  => (int) ( $parts['INTERVAL'] ?? 1 ),
			'weekdays'  => array(),
			'end_type'  => 'never',
		);

		if ( 'weekly' === $values['frequency'] && isset( $parts['BYDAY'] ) ) {
			foreach ( explode( ',', $parts['BYDAY'] ) as $code ) {
				$this->assertArrayHasKey(
					$code,
					self::WEEKDAY_INDEX,
					sprintf( 'BYDAY carried "%s", which is not an RFC 5545 weekday code.', $code )
				);

				$values['weekdays'][] = self::WEEKDAY_INDEX[ $code ];
			}
		}

		if ( 'monthly' === $values['frequency'] ) {
			$values = array_merge( $values, $this->parse_monthly_parts( $parts ) );
		}

		return array_merge( $values, $this->parse_end_parts( $parts, $timezone ) );
	}

	/**
	 * Read the monthly-specific parts of an `RRULE`.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, string> $parts The `RRULE`'s name/value pairs.
	 *
	 * @return array The monthly keys of the decomposed value array.
	 */
	protected function parse_monthly_parts( array $parts ): array {
		if ( isset( $parts['BYMONTHDAY'] ) ) {
			return array(
				'monthly_mode' => 'day_of_month',
				'monthly_day'  => (int) $parts['BYMONTHDAY'],
			);
		}

		$this->assertArrayHasKey(
			'BYDAY',
			$parts,
			'A monthly rule must carry BYMONTHDAY or BYDAY.'
		);
		$this->assertMatchesRegularExpression(
			'/^(-?[0-9]+)(SU|MO|TU|WE|TH|FR|SA)$/',
			$parts['BYDAY'],
			'A monthly BYDAY must carry an ordinal and a weekday code.'
		);

		preg_match( '/^(-?[0-9]+)(SU|MO|TU|WE|TH|FR|SA)$/', $parts['BYDAY'], $matches );

		return array(
			'monthly_mode'    => 'nth_weekday',
			'monthly_ordinal' => (int) $matches[1],
			'monthly_weekday' => self::WEEKDAY_INDEX[ $matches[2] ],
		);
	}

	/**
	 * Read the end-of-series parts of an `RRULE`.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, string> $parts    The `RRULE`'s name/value pairs.
	 * @param DateTimeZone          $timezone Series timezone.
	 *
	 * @return array The end-of-series keys of the decomposed value array.
	 */
	protected function parse_end_parts( array $parts, DateTimeZone $timezone ): array {
		$this->assertFalse(
			isset( $parts['UNTIL'] ) && isset( $parts['COUNT'] ),
			'RFC 5545 section 3.3.10 forbids UNTIL and COUNT in the same RRULE.'
		);

		if ( isset( $parts['COUNT'] ) ) {
			return array(
				'end_type' => 'count',
				'count'    => (int) $parts['COUNT'],
			);
		}

		if ( ! isset( $parts['UNTIL'] ) ) {
			return array( 'end_type' => 'never' );
		}

		// RFC 5545 section 3.3.10: the value type of UNTIL must match
		// DTSTART's, and this rule is attached to a TZID-qualified DATE-TIME
		// DTSTART, so a bare Ymd DATE is invalid here. The RFC further requires
		// that such an UNTIL be specified as a UTC time.
		$this->assertMatchesRegularExpression(
			'/^[0-9]{8}T[0-9]{6}Z$/',
			$parts['UNTIL'],
			'UNTIL must be a UTC date-time when DTSTART is a TZID-qualified date-time.'
		);

		$until = DateTimeImmutable::createFromFormat(
			'Ymd\THis\Z',
			$parts['UNTIL'],
			new DateTimeZone( 'UTC' )
		);

		return array(
			'end_type' => 'until',
			'until'    => $until->setTimezone( $timezone )->format( 'Y-m-d' ),
		);
	}

	/**
	 * Render an occurrence set as comparable strings.
	 *
	 * Carries the local wall clock, its UTC offset, and the absolute instant,
	 * so a set that holds the right wall clock at the wrong offset does not
	 * compare equal. That is the signature of a UTC-anchored expansion.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable[] $occurrences The expanded occurrences.
	 *
	 * @return string[] One string per occurrence.
	 */
	protected function render_set( array $occurrences ): array {
		return array_map(
			static function ( DateTimeImmutable $occurrence ): string {
				return $occurrence->format( 'Y-m-d H:i:sP' )
					. ' / '
					. $occurrence->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
			},
			$occurrences
		);
	}

	/**
	 * Expand one fixture's rule.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule  $rule    Rule to expand.
	 * @param array $fixture The fixture entry.
	 *
	 * @return string[] The rendered occurrence set.
	 */
	protected function expand_fixture( Rule $rule, array $fixture ): array {
		$timezone = new DateTimeZone( $fixture['timezone'] );

		return $this->render_set(
			( new Expander() )->expand(
				$rule,
				new DateTimeImmutable( $fixture['anchor'], $timezone ),
				$timezone,
				new DateTimeImmutable( $fixture['through'], $timezone )
			)
		);
	}

	/**
	 * Every fixture's stated set has more than one entry, and no two fixtures
	 * state the same set.
	 *
	 * A round-trip comparison over a one-element set is very nearly free to
	 * satisfy, and two fixtures with identical sets means one of them is not
	 * testing what its name says. This is the guard that keeps the table from
	 * decaying into either.
	 *
	 * @return void
	 */
	public function test_fixture_sets_are_multi_valued_and_distinct(): void {
		$rendered = array();

		foreach ( $this->fixtures() as $name => $fixture ) {
			$this->assertGreaterThan(
				1,
				count( $fixture['expected'] ),
				sprintf( 'Fixture "%s" states a set of one, which a round trip cannot fail on.', $name )
			);

			$rendered[ $name ] = wp_json_encode( $fixture['expected'] );
		}

		$this->assertSame(
			count( $rendered ),
			count( array_unique( $rendered ) ),
			'Two fixtures state the same occurrence set, so one of them is not testing what it names.'
		);
	}

	/**
	 * Each fixture serializes to the exact `RRULE` text it states.
	 *
	 * The stated text is the specification, asserted before the round trip so a
	 * serializer and reader that agree on something wrong still fail here.
	 *
	 * @covers ::to_rrule_string
	 *
	 * @return void
	 */
	public function test_each_fixture_serializes_to_its_stated_rrule(): void {
		foreach ( $this->fixtures() as $name => $fixture ) {
			$rule     = Rule::from_array( $fixture['values'] );
			$timezone = new DateTimeZone( $fixture['timezone'] );

			$this->assertInstanceOf( Rule::class, $rule, sprintf( 'Fixture "%s" failed to build.', $name ) );
			$this->assertSame(
				$fixture['rrule'],
				$rule->to_rrule_string( new DateTimeImmutable( $fixture['anchor'], $timezone ), $timezone ),
				sprintf(
					'Fixture "%s" did not serialize to its stated RRULE. It catches: %s',
					$name,
					$fixture['catches']
				)
			);
		}
	}

	/**
	 * Each fixture expands to the exact occurrence set it states, and the set
	 * produced by re-parsing its serialized `RRULE` is the same one.
	 *
	 * @covers ::to_rrule_string
	 *
	 * @return void
	 */
	public function test_each_fixture_round_trips_to_the_same_occurrence_set(): void {
		foreach ( $this->fixtures() as $name => $fixture ) {
			$rule     = Rule::from_array( $fixture['values'] );
			$timezone = new DateTimeZone( $fixture['timezone'] );

			$this->assertInstanceOf( Rule::class, $rule, sprintf( 'Fixture "%s" failed to build.', $name ) );

			$original = $this->expand_fixture( $rule, $fixture );

			$this->assertSame(
				$fixture['expected'],
				$original,
				sprintf( 'Fixture "%s" did not expand to its stated occurrence set.', $name )
			);

			$rrule    = $rule->to_rrule_string(
				new DateTimeImmutable( $fixture['anchor'], $timezone ),
				$timezone
			);
			$reparsed = Rule::from_array( $this->parse_rrule( $rrule, $timezone ) );

			$this->assertInstanceOf(
				Rule::class,
				$reparsed,
				sprintf( 'Fixture "%s" serialized to an RRULE that does not read back as a rule: %s', $name, $rrule )
			);
			$this->assertSame(
				$fixture['expected'],
				$this->expand_fixture( $reparsed, $fixture ),
				sprintf(
					'Fixture "%s" did not round-trip. RRULE was "%s". It catches: %s',
					$name,
					$rrule,
					$fixture['catches']
				)
			);
		}
	}

	/**
	 * A `BYMONTHDAY` rule reads back through the monthly branch the fixture
	 * table's nth-weekday entry does not reach.
	 *
	 * @covers ::to_rrule_string
	 *
	 * @return void
	 */
	public function test_day_of_month_rule_round_trips(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = new DateTimeImmutable( '2026-01-15 19:00:00', $timezone );
		$through  = new DateTimeImmutable( '2026-12-31 23:59:59', $timezone );
		$rule     = Rule::from_array(
			array(
				'frequency'    => 'monthly',
				'interval'     => 1,
				'monthly_mode' => 'day_of_month',
				'monthly_day'  => 15,
				'end_type'     => 'until',
				'until'        => '2026-04-15',
			)
		);

		$this->assertInstanceOf( Rule::class, $rule );

		$rrule = $rule->to_rrule_string( $anchor, $timezone );

		$this->assertSame( 'FREQ=MONTHLY;BYMONTHDAY=15;UNTIL=20260415T230000Z', $rrule );

		$reparsed = Rule::from_array( $this->parse_rrule( $rrule, $timezone ) );

		$this->assertInstanceOf( Rule::class, $reparsed );

		$expander = new Expander();

		$this->assertSame(
			array(
				'2026-01-15 19:00:00-05:00 / 20260116T000000Z',
				'2026-02-15 19:00:00-05:00 / 20260216T000000Z',
				'2026-03-15 19:00:00-04:00 / 20260315T230000Z',
				'2026-04-15 19:00:00-04:00 / 20260415T230000Z',
			),
			$this->render_set( $expander->expand( $reparsed, $anchor, $timezone, $through ) ),
			'A BYMONTHDAY rule whose UNTIL falls after a daylight saving change must keep its last occurrence.'
		);
	}
}
