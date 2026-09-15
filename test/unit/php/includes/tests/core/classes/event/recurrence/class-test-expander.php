<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Expander.
 *
 * Covers the calendar arithmetic: weekly week-bucket intervals, both monthly
 * modes, the `COUNT` budget, the horizon, and the derived iteration budget. The
 * daylight saving cases live in `class-test-expander-dst.php`.
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
use PMC\Unit_Test\Utility;

/**
 * Class Test_Expander.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Expander
 */
class Test_Expander extends Base {

	/**
	 * Shared occurrence fixtures.
	 */
	use Occurrence_Fixtures;

	/**
	 * Build a rule from valid values via the public boundary.
	 *
	 * Every caller of this helper passes fixture values that `is_valid()`
	 * accepts. Tests that need a rule shape `from_array()` itself rejects
	 * (an unrecognized frequency, an empty weekday list on a weekly rule, an
	 * unresolvable monthly ordinal) go through `build_rule_directly()` from
	 * `Occurrence_Fixtures` instead, which bypasses `from_array()`'s boundary
	 * guards entirely.
	 *
	 * @since 0.36.0
	 *
	 * @param array $values Decoded recurrence values.
	 *
	 * @return Rule The rule.
	 */
	protected function make_rule( array $values ): Rule {
		$rule = Rule::from_array( $values );

		$this->assertInstanceOf(
			Rule::class,
			$rule,
			'Failed to assert that the fixture values built a valid Rule.'
		);

		return $rule;
	}

	/**
	 * Get the reference bi-weekly Tuesday/Thursday rule values.
	 *
	 * @since 0.36.0
	 *
	 * @return array The rule values.
	 */
	protected function reference_rule_values(): array {
		return array(
			'frequency' => Rule::FREQUENCY_WEEKLY,
			'interval'  => 2,
			'weekdays'  => array( 2, 4 ),
			'end_type'  => Rule::END_TYPE_COUNT,
			'count'     => 5,
		);
	}

	/**
	 * Get the series anchor for the reference rule.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable Thursday 2026-09-03 18:00 in America/New_York.
	 */
	protected function reference_anchor(): DateTimeImmutable {
		return new DateTimeImmutable( $this->reference_anchor_start, new DateTimeZone( 'America/New_York' ) );
	}

	/**
	 * Reduce an expansion to a list of `Y-m-d H:i:s` local datetimes.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable[] $occurrences Expanded occurrences.
	 *
	 * @return string[] Local datetimes.
	 */
	protected function to_local_strings( array $occurrences ): array {
		return array_map(
			static function ( DateTimeImmutable $occurrence ): string {
				return $occurrence->format( 'Y-m-d H:i:s' );
			},
			$occurrences
		);
	}

	/**
	 * A bi-weekly Tuesday/Thursday rule bounded by a date yields the reference set.
	 *
	 * @covers ::expand
	 * @covers ::next_candidate_date
	 * @covers ::next_scanned_date
	 * @covers ::matches
	 * @covers ::matches_weekly
	 * @covers ::week_index
	 * @covers ::materialize
	 * @covers ::past_until
	 * @covers ::iteration_budget
	 * @covers ::day_scan_limit
	 * @covers ::date_only
	 *
	 * @return void
	 */
	public function test_weekly_interval_two_tuesday_thursday_until(): void {
		$values             = $this->reference_rule_values();
		$values['end_type'] = Rule::END_TYPE_UNTIL;
		$values['until']    = '2026-10-01';
		unset( $values['count'] );

		$timezone = new DateTimeZone( 'America/New_York' );
		$expected = array_column( $this->expected_weekly_set(), 'datetime_start' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule( $values ),
			$this->reference_anchor(),
			$timezone,
			new DateTimeImmutable( '2027-09-03 18:00:00', $timezone )
		);

		$this->assertSame(
			$expected,
			$this->to_local_strings( $occurrences ),
			'Failed to assert that the bi-weekly Tue/Thu rule yields the reference occurrence set.'
		);
	}

	/**
	 * A bi-weekly rule anchored on a Thursday skips the whole following week.
	 *
	 * The anchor falls late in its Monday-start week, which is the only shape
	 * that distinguishes week-bucket arithmetic from a seven-day delta.
	 *
	 * @covers ::expand
	 * @covers ::matches_weekly
	 * @covers ::week_index
	 *
	 * @return void
	 */
	public function test_biweekly_tuesday_thursday_anchored_on_thursday_skips_the_following_week(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule( $this->reference_rule_values() ),
			$this->reference_anchor(),
			$timezone,
			new DateTimeImmutable( '2027-09-03 18:00:00', $timezone )
		);

		$dates = array_map(
			static function ( DateTimeImmutable $occurrence ): string {
				return $occurrence->format( 'Y-m-d' );
			},
			$occurrences
		);

		$this->assertSame(
			array( '2026-09-03', '2026-09-15', '2026-09-17', '2026-09-29', '2026-10-01' ),
			$dates,
			'Failed to assert that the week following a Thursday anchor is empty and the week after yields both days.'
		);
		$this->assertNotContains(
			'2026-09-08',
			$dates,
			'Failed to assert that the Tuesday of the skipped week is absent.'
		);
		$this->assertNotContains(
			'2026-09-10',
			$dates,
			'Failed to assert that the Thursday of the skipped week is absent.'
		);
		$this->assertNotContains(
			'2026-09-01',
			$dates,
			'Failed to assert that the walk begins at the anchor rather than at the anchor week.'
		);
	}

	/**
	 * A day-of-month rule on the 15th yields twelve occurrences in a year.
	 *
	 * @covers ::expand
	 * @covers ::next_monthly_date
	 * @covers ::monthly_date_for_offset
	 * @covers ::day_of_month_date
	 * @covers ::months_apart
	 *
	 * @return void
	 */
	public function test_monthly_day_of_month_fifteenth_yields_twelve(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency'    => Rule::FREQUENCY_MONTHLY,
					'interval'     => 1,
					'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
					'monthly_day'  => 15,
					'end_type'     => Rule::END_TYPE_UNTIL,
					'until'        => '2026-12-31',
				)
			),
			new DateTimeImmutable( '2026-01-15 09:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2027-01-15 09:00:00', $timezone )
		);

		$this->assertCount(
			12,
			$occurrences,
			'Failed to assert that a day-of-month rule on the 15th yields twelve occurrences.'
		);
		$this->assertSame(
			'2026-12-15 09:00:00',
			end( $occurrences )->format( 'Y-m-d H:i:s' ),
			'Failed to assert that the final day-of-month occurrence falls in December.'
		);
	}

	/**
	 * A day-of-month rule on the 31st yields only the months that have a 31st.
	 *
	 * The date is skipped, never rolled forward to the 1st.
	 *
	 * @covers ::expand
	 * @covers ::next_monthly_date
	 * @covers ::monthly_date_for_offset
	 * @covers ::day_of_month_date
	 *
	 * @return void
	 */
	public function test_monthly_day_of_month_thirty_first_yields_seven_not_twelve(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency'    => Rule::FREQUENCY_MONTHLY,
					'interval'     => 1,
					'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
					'monthly_day'  => 31,
					'end_type'     => Rule::END_TYPE_UNTIL,
					'until'        => '2026-12-31',
				)
			),
			new DateTimeImmutable( '2026-01-31 09:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2027-01-31 09:00:00', $timezone )
		);

		$this->assertSame(
			array(
				'2026-01-31',
				'2026-03-31',
				'2026-05-31',
				'2026-07-31',
				'2026-08-31',
				'2026-10-31',
				'2026-12-31',
			),
			array_map(
				static function ( DateTimeImmutable $occurrence ): string {
					return $occurrence->format( 'Y-m-d' );
				},
				$occurrences
			),
			'Failed to assert that the 31st yields seven occurrences and never rolls forward to the 1st.'
		);
	}

	/**
	 * A day-of-month rule on the 31st at a wide interval still delivers its whole count.
	 *
	 * The monthly walk steps by the interval, so a bound expressed in absolute
	 * months would examine `bound / interval` candidates and stop early. At
	 * interval 43 the gap between the fifth and sixth occurrence is 258 months,
	 * which is six candidate months but more than two hundred calendar ones.
	 *
	 * @covers ::expand
	 * @covers ::next_monthly_date
	 *
	 * @return void
	 */
	public function test_monthly_day_of_month_thirty_first_at_interval_forty_three_yields_six(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency'    => Rule::FREQUENCY_MONTHLY,
					'interval'     => 43,
					'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
					'monthly_day'  => 31,
					'end_type'     => Rule::END_TYPE_COUNT,
					'count'        => 6,
				)
			),
			new DateTimeImmutable( '2024-03-31 09:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2025-03-31 09:00:00', $timezone )
		);

		$this->assertSame(
			array( '2024-03-31', '2027-10-31', '2031-05-31', '2034-12-31', '2038-07-31', '2060-01-31' ),
			array_map(
				static function ( DateTimeImmutable $occurrence ): string {
					return $occurrence->format( 'Y-m-d' );
				},
				$occurrences
			),
			'Failed to assert that a wide monthly interval delivers its whole count.'
		);
	}

	/**
	 * An anchor expressed in another timezone is read in the series timezone.
	 *
	 * The wall clock belongs to the series, so a UTC-expressed anchor an hour
	 * past midnight is a nine o'clock evening event in New York, not a one
	 * o'clock morning one.
	 *
	 * @covers ::expand
	 *
	 * @return void
	 */
	public function test_expand_normalizes_a_foreign_timezone_anchor_and_horizon(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$utc      = new DateTimeZone( 'UTC' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency' => Rule::FREQUENCY_DAILY,
					'interval'  => 1,
					'end_type'  => Rule::END_TYPE_NEVER,
				)
			),
			new DateTimeImmutable( '2026-09-04 01:00:00', $utc ),
			$timezone,
			new DateTimeImmutable( '2026-09-06 01:00:00', $utc )
		);

		$this->assertSame(
			array( '2026-09-03 21:00:00', '2026-09-04 21:00:00', '2026-09-05 21:00:00' ),
			$this->to_local_strings( $occurrences ),
			'Failed to assert that a UTC-expressed anchor and horizon are read in the series timezone.'
		);
	}

	/**
	 * An nth-weekday rule on the third Thursday tracks the weekday, not the date.
	 *
	 * @covers ::expand
	 * @covers ::monthly_date_for_offset
	 * @covers ::nth_weekday_of_month
	 *
	 * @return void
	 */
	public function test_monthly_nth_weekday_third_thursday(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency'       => Rule::FREQUENCY_MONTHLY,
					'interval'        => 1,
					'monthly_mode'    => Rule::MONTHLY_MODE_NTH_WEEKDAY,
					'monthly_ordinal' => 3,
					'monthly_weekday' => 4,
					'end_type'        => Rule::END_TYPE_UNTIL,
					'until'           => '2026-06-30',
				)
			),
			new DateTimeImmutable( '2026-01-15 19:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2027-01-15 19:00:00', $timezone )
		);

		$this->assertSame(
			array( '2026-01-15', '2026-02-19', '2026-03-19', '2026-04-16', '2026-05-21', '2026-06-18' ),
			array_map(
				static function ( DateTimeImmutable $occurrence ): string {
					return $occurrence->format( 'Y-m-d' );
				},
				$occurrences
			),
			'Failed to assert that the third Thursday is resolved per month.'
		);
	}

	/**
	 * A "last Wednesday" rule lands on the 4th in four-Wednesday months and the 5th in five-Wednesday months.
	 *
	 * January through March 2026 have four Wednesdays; April 2026 has five.
	 *
	 * @covers ::expand
	 * @covers ::nth_weekday_of_month
	 *
	 * @return void
	 */
	public function test_monthly_last_wednesday_across_four_and_five_wednesday_months(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency'       => Rule::FREQUENCY_MONTHLY,
					'interval'        => 1,
					'monthly_mode'    => Rule::MONTHLY_MODE_NTH_WEEKDAY,
					'monthly_ordinal' => -1,
					'monthly_weekday' => 3,
					'end_type'        => Rule::END_TYPE_UNTIL,
					'until'           => '2026-04-30',
				)
			),
			new DateTimeImmutable( '2026-01-28 19:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2027-01-28 19:00:00', $timezone )
		);

		$this->assertSame(
			array( '2026-01-28', '2026-02-25', '2026-03-25', '2026-04-29' ),
			array_map(
				static function ( DateTimeImmutable $occurrence ): string {
					return $occurrence->format( 'Y-m-d' );
				},
				$occurrences
			),
			'Failed to assert that "last Wednesday" tracks the final Wednesday of each month.'
		);
	}

	/**
	 * A count-bounded rule yields exactly the requested number of occurrences.
	 *
	 * @covers ::expand
	 *
	 * @return void
	 */
	public function test_count_yields_exactly_n(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule( $this->reference_rule_values() ),
			$this->reference_anchor(),
			$timezone,
			new DateTimeImmutable( '2027-09-03 18:00:00', $timezone )
		);

		$this->assertCount(
			5,
			$occurrences,
			'Failed to assert that a COUNT=5 rule yields exactly five occurrences.'
		);
		$this->assertSame(
			array_column( $this->expected_weekly_set(), 'datetime_start' ),
			$this->to_local_strings( $occurrences ),
			'Failed to assert that the COUNT=5 rule yields the reference occurrence set.'
		);
	}

	/**
	 * A rule that can never match terminates instead of running to the iteration cap.
	 *
	 * @covers ::expand
	 * @covers ::next_scanned_date
	 *
	 * @return void
	 */
	public function test_never_matching_rule_terminates_within_iteration_cap(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		// A weekly rule naming no weekday can never match `matches()`, but it
		// cannot be built through `from_array()`, because `is_valid()` already
		// rejects an empty weekday list on a weekly rule at the boundary.
		// `build_rule_directly()` bypasses that guard so `expand()`'s own
		// iteration-cap termination can be exercised directly.
		$occurrences = ( new Expander() )->expand(
			$this->build_rule_directly(
				array( Rule::FREQUENCY_WEEKLY, 1, array(), '', 0, 0, 0, Rule::END_TYPE_COUNT, null, 5 )
			),
			$this->reference_anchor(),
			$timezone,
			new DateTimeImmutable( '2027-09-03 18:00:00', $timezone )
		);

		$this->assertSame(
			array(),
			$occurrences,
			'Failed to assert that a rule matching no weekday yields no occurrences.'
		);
	}

	/**
	 * An open-ended rule stops at the horizon.
	 *
	 * @covers ::expand
	 * @covers ::matches_daily
	 *
	 * @return void
	 */
	public function test_open_ended_rule_stops_at_horizon(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency' => Rule::FREQUENCY_DAILY,
					'interval'  => 1,
					'end_type'  => Rule::END_TYPE_NEVER,
				)
			),
			$this->reference_anchor(),
			$timezone,
			new DateTimeImmutable( '2026-09-10 18:00:00', $timezone )
		);

		$this->assertCount(
			8,
			$occurrences,
			'Failed to assert that an open-ended daily rule stops at the horizon.'
		);
		$this->assertSame(
			'2026-09-10 18:00:00',
			end( $occurrences )->format( 'Y-m-d H:i:s' ),
			'Failed to assert that the final occurrence falls on the horizon date.'
		);
	}

	/**
	 * A monthly `COUNT=500` rule yields five hundred occurrences, which a flat iteration cap truncates.
	 *
	 * @covers ::expand
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_count_five_hundred_monthly_yields_exactly_five_hundred(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency'    => Rule::FREQUENCY_MONTHLY,
					'interval'     => 1,
					'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
					'monthly_day'  => 15,
					'end_type'     => Rule::END_TYPE_COUNT,
					'count'        => 500,
				)
			),
			new DateTimeImmutable( '2026-01-15 09:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2027-01-15 09:00:00', $timezone )
		);

		$this->assertCount(
			500,
			$occurrences,
			'Failed to assert that a monthly COUNT=500 rule yields five hundred occurrences.'
		);
		$this->assertSame(
			'2067-08-15',
			end( $occurrences )->format( 'Y-m-d' ),
			'Failed to assert that the five hundredth monthly occurrence is forty-one years out.'
		);
	}

	/**
	 * A weekly `COUNT=500` rule ignores a twelve-month horizon.
	 *
	 * @covers ::expand
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_count_five_hundred_weekly_ignores_twelve_month_horizon(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency' => Rule::FREQUENCY_WEEKLY,
					'interval'  => 1,
					'weekdays'  => array( 4 ),
					'end_type'  => Rule::END_TYPE_COUNT,
					'count'     => 500,
				)
			),
			$this->reference_anchor(),
			$timezone,
			new DateTimeImmutable( '2027-09-03 18:00:00', $timezone )
		);

		$this->assertCount(
			500,
			$occurrences,
			'Failed to assert that a weekly COUNT=500 rule ignores the horizon.'
		);
		$this->assertSame(
			'2036-03-27',
			end( $occurrences )->format( 'Y-m-d' ),
			'Failed to assert that the five hundredth weekly occurrence falls well beyond the horizon.'
		);
	}

	/**
	 * A non-count rule derives its budget from the horizon.
	 *
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_iteration_budget_for_horizon_bounded_rule(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$values   = $this->reference_rule_values();

		$values['end_type'] = Rule::END_TYPE_NEVER;
		unset( $values['count'] );

		$this->assertSame(
			11,
			Utility::invoke_hidden_method(
				new Expander(),
				'iteration_budget',
				array(
					$this->make_rule( $values ),
					$this->reference_anchor(),
					new DateTimeImmutable( '2026-09-13 18:00:00', $timezone ),
				)
			),
			'Failed to assert that a horizon-bounded budget is the day span plus one.'
		);
	}

	/**
	 * A count-bounded rule derives a budget large enough for the whole count.
	 *
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_iteration_budget_for_count_bounded_rule(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$this->assertSame(
			366 + ( 5 * 7 * 2 ),
			Utility::invoke_hidden_method(
				new Expander(),
				'iteration_budget',
				array(
					$this->make_rule( $this->reference_rule_values() ),
					$this->reference_anchor(),
					new DateTimeImmutable( '2026-09-13 18:00:00', $timezone ),
				)
			),
			'Failed to assert that a count-bounded budget covers the whole count.'
		);
	}

	/**
	 * The budget is clamped to `MAX_ITERATIONS`.
	 *
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_iteration_budget_is_clamped_to_max_iterations(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$values   = $this->reference_rule_values();

		$values['end_type'] = Rule::END_TYPE_NEVER;
		unset( $values['count'] );

		$this->assertSame(
			Expander::MAX_ITERATIONS,
			Utility::invoke_hidden_method(
				new Expander(),
				'iteration_budget',
				array(
					$this->make_rule( $values ),
					$this->reference_anchor(),
					new DateTimeImmutable( '4026-09-03 18:00:00', $timezone ),
				)
			),
			'Failed to assert that the derived budget is clamped to MAX_ITERATIONS.'
		);
	}

	/**
	 * Dispatching a daily rule returns the next daily date.
	 *
	 * @covers ::next_candidate_date
	 *
	 * @return void
	 */
	public function test_next_candidate_date_dispatches_daily(): void {
		$anchor = new DateTimeImmutable( '2026-09-03', new DateTimeZone( 'UTC' ) );
		$rule   = $this->make_rule(
			array(
				'frequency' => Rule::FREQUENCY_DAILY,
				'interval'  => 3,
				'end_type'  => Rule::END_TYPE_NEVER,
			)
		);

		$candidate = Utility::invoke_hidden_method(
			new Expander(),
			'next_candidate_date',
			array( $rule, $anchor->modify( '+1 day' ), $anchor )
		);

		$this->assertSame(
			'2026-09-06',
			$candidate->format( 'Y-m-d' ),
			'Failed to assert that a daily rule advances by its interval.'
		);
	}

	/**
	 * Dispatching a monthly rule returns the next monthly date.
	 *
	 * @covers ::next_candidate_date
	 * @covers ::next_monthly_date
	 *
	 * @return void
	 */
	public function test_next_candidate_date_dispatches_monthly(): void {
		$anchor = new DateTimeImmutable( '2026-01-15', new DateTimeZone( 'UTC' ) );
		$rule   = $this->make_rule(
			array(
				'frequency'    => Rule::FREQUENCY_MONTHLY,
				'interval'     => 2,
				'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
				'monthly_day'  => 15,
				'end_type'     => Rule::END_TYPE_NEVER,
			)
		);

		$candidate = Utility::invoke_hidden_method(
			new Expander(),
			'next_candidate_date',
			array( $rule, $anchor->modify( '+1 day' ), $anchor )
		);

		$this->assertSame(
			'2026-03-15',
			$candidate->format( 'Y-m-d' ),
			'Failed to assert that a monthly rule advances by its interval in months.'
		);
	}

	/**
	 * An unknown frequency yields no candidate rather than a fatal.
	 *
	 * @covers ::next_candidate_date
	 * @covers ::matches
	 * @covers ::day_scan_limit
	 *
	 * @return void
	 */
	public function test_next_candidate_date_returns_null_for_unknown_frequency(): void {
		$anchor = new DateTimeImmutable( '2026-09-03', new DateTimeZone( 'UTC' ) );

		// 'fortnightly' is not a recognized frequency, and `is_valid()` rejects
		// it at the `from_array()` boundary (see
		// `Test_Rule::test_from_array_rejects_unrecognized_frequency()`), so
		// this deliberately-invalid shape can only be built directly. The
		// fixture used to be 'yearly', which stopped being unrecognized when
		// the yearly frequency was added.
		$rule = $this->build_rule_directly(
			array( 'fortnightly', 1, array(), '', 0, 0, 0, Rule::END_TYPE_NEVER, null, 0 )
		);

		$expander = new Expander();

		$this->assertNull(
			Utility::invoke_hidden_method( $expander, 'next_candidate_date', array( $rule, $anchor, $anchor ) ),
			'Failed to assert that an unknown frequency yields no candidate.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $expander, 'matches', array( $rule, $anchor, $anchor ) ),
			'Failed to assert that an unknown frequency matches nothing.'
		);
		$this->assertSame(
			0,
			Utility::invoke_hidden_method( $expander, 'day_scan_limit', array( $rule ) ),
			'Failed to assert that an unknown frequency has no scan window.'
		);
	}

	/**
	 * The day scan returns null when nothing matches inside its window.
	 *
	 * @covers ::next_scanned_date
	 * @covers ::day_scan_limit
	 *
	 * @return void
	 */
	public function test_next_scanned_date_returns_null_when_window_is_exhausted(): void {
		$anchor = new DateTimeImmutable( '2026-09-03', new DateTimeZone( 'UTC' ) );

		// A weekly rule naming no weekday cannot pass `is_valid()`'s
		// non-empty-weekday check, so it is built directly to exercise the
		// scan window exhausting without a match.
		$rule = $this->build_rule_directly(
			array( Rule::FREQUENCY_WEEKLY, 1, array(), '', 0, 0, 0, Rule::END_TYPE_NEVER, null, 0 )
		);

		$this->assertNull(
			Utility::invoke_hidden_method(
				new Expander(),
				'next_scanned_date',
				array( $rule, $anchor, $anchor )
			),
			'Failed to assert that an unsatisfiable weekly rule exhausts its scan window.'
		);
		$this->assertSame(
			14,
			Utility::invoke_hidden_method( new Expander(), 'day_scan_limit', array( $rule ) ),
			'Failed to assert that a weekly scan window spans the interval plus one week.'
		);
	}

	/**
	 * The day scan returns the next matching date.
	 *
	 * @covers ::next_scanned_date
	 *
	 * @return void
	 */
	public function test_next_scanned_date_returns_the_next_matching_date(): void {
		$anchor = new DateTimeImmutable( '2026-09-03', new DateTimeZone( 'UTC' ) );

		$candidate = Utility::invoke_hidden_method(
			new Expander(),
			'next_scanned_date',
			array( $this->make_rule( $this->reference_rule_values() ), $anchor->modify( '+1 day' ), $anchor )
		);

		$this->assertSame(
			'2026-09-15',
			$candidate->format( 'Y-m-d' ),
			'Failed to assert that the day scan skips the intervening week and returns the next Tuesday.'
		);
	}

	/**
	 * The monthly walk returns the next matching date.
	 *
	 * @covers ::next_monthly_date
	 *
	 * @return void
	 */
	public function test_next_monthly_date_returns_the_next_matching_date(): void {
		$anchor = new DateTimeImmutable( '2026-01-31', new DateTimeZone( 'UTC' ) );
		$rule   = $this->make_rule(
			array(
				'frequency'    => Rule::FREQUENCY_MONTHLY,
				'interval'     => 1,
				'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
				'monthly_day'  => 31,
				'end_type'     => Rule::END_TYPE_NEVER,
			)
		);

		$candidate = Utility::invoke_hidden_method(
			new Expander(),
			'next_monthly_date',
			array( $rule, $anchor->modify( '+1 day' ), $anchor )
		);

		$this->assertSame(
			'2026-03-31',
			$candidate->format( 'Y-m-d' ),
			'Failed to assert that the monthly walk skips February and returns the next 31st.'
		);
	}

	/**
	 * The monthly walk returns null when no month in its window resolves a date.
	 *
	 * @covers ::next_monthly_date
	 * @covers ::monthly_date_for_offset
	 *
	 * @return void
	 */
	public function test_next_monthly_date_returns_null_when_no_month_resolves(): void {
		$anchor = new DateTimeImmutable( '2026-01-15', new DateTimeZone( 'UTC' ) );

		// Ordinal 6 is outside `is_valid_monthly_shape()`'s `[1, 2, 3, 4, -1]`
		// range, so this shape can only be built directly.
		$rule = $this->build_rule_directly(
			array(
				Rule::FREQUENCY_MONTHLY,
				1,
				array(),
				Rule::MONTHLY_MODE_NTH_WEEKDAY,
				0,
				6,
				4,
				Rule::END_TYPE_NEVER,
				null,
				0,
			)
		);

		$this->assertNull(
			Utility::invoke_hidden_method(
				new Expander(),
				'next_monthly_date',
				array( $rule, $anchor, $anchor )
			),
			'Failed to assert that an unresolvable ordinal yields no monthly candidate.'
		);
	}

	/**
	 * The daily predicate accepts interval multiples and rejects everything else.
	 *
	 * @covers ::matches_daily
	 *
	 * @return void
	 */
	public function test_matches_daily_covers_both_outcomes(): void {
		$anchor   = new DateTimeImmutable( '2026-09-03', new DateTimeZone( 'UTC' ) );
		$expander = new Expander();
		$rule     = $this->make_rule(
			array(
				'frequency' => Rule::FREQUENCY_DAILY,
				'interval'  => 3,
				'end_type'  => Rule::END_TYPE_NEVER,
			)
		);

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$expander,
				'matches_daily',
				array( $rule, $anchor->modify( '+6 days' ), $anchor )
			),
			'Failed to assert that a date an interval multiple away matches.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_daily',
				array( $rule, $anchor->modify( '+4 days' ), $anchor )
			),
			'Failed to assert that a date off the interval does not match.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_daily',
				array( $rule, $anchor->modify( '-3 days' ), $anchor )
			),
			'Failed to assert that a date before the anchor does not match.'
		);
	}

	/**
	 * The weekly predicate tests both the weekday list and the week bucket.
	 *
	 * @covers ::matches_weekly
	 * @covers ::week_index
	 *
	 * @return void
	 */
	public function test_matches_weekly_covers_both_outcomes(): void {
		$anchor   = new DateTimeImmutable( '2026-09-03', new DateTimeZone( 'UTC' ) );
		$expander = new Expander();
		$rule     = $this->make_rule( $this->reference_rule_values() );

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$expander,
				'matches_weekly',
				array( $rule, new DateTimeImmutable( '2026-09-15', new DateTimeZone( 'UTC' ) ), $anchor )
			),
			'Failed to assert that a Tuesday two week buckets on matches.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_weekly',
				array( $rule, new DateTimeImmutable( '2026-09-16', new DateTimeZone( 'UTC' ) ), $anchor )
			),
			'Failed to assert that a Wednesday does not match the weekday list.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_weekly',
				array( $rule, new DateTimeImmutable( '2026-09-08', new DateTimeZone( 'UTC' ) ), $anchor )
			),
			'Failed to assert that a Tuesday one week bucket on does not match.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_weekly',
				array( $rule, new DateTimeImmutable( '2026-08-18', new DateTimeZone( 'UTC' ) ), $anchor )
			),
			'Failed to assert that a Tuesday two week buckets before the anchor does not match.'
		);
	}

	/**
	 * The monthly predicate tests the month interval and the resolved date.
	 *
	 * @covers ::matches_monthly
	 * @covers ::months_apart
	 *
	 * @return void
	 */
	public function test_matches_monthly_covers_both_outcomes(): void {
		$utc      = new DateTimeZone( 'UTC' );
		$anchor   = new DateTimeImmutable( '2026-01-15', $utc );
		$expander = new Expander();
		$rule     = $this->make_rule(
			array(
				'frequency'    => Rule::FREQUENCY_MONTHLY,
				'interval'     => 2,
				'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
				'monthly_day'  => 15,
				'end_type'     => Rule::END_TYPE_NEVER,
			)
		);

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$expander,
				'matches_monthly',
				array( $rule, new DateTimeImmutable( '2026-03-15', $utc ), $anchor )
			),
			'Failed to assert that the 15th two months on matches.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_monthly',
				array( $rule, new DateTimeImmutable( '2026-02-15', $utc ), $anchor )
			),
			'Failed to assert that a month off the interval does not match.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_monthly',
				array( $rule, new DateTimeImmutable( '2026-03-16', $utc ), $anchor )
			),
			'Failed to assert that the wrong day of an on-interval month does not match.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_monthly',
				array( $rule, new DateTimeImmutable( '2025-11-15', $utc ), $anchor )
			),
			'Failed to assert that a month before the anchor does not match.'
		);
	}

	/**
	 * Dispatching `matches()` reaches every frequency branch.
	 *
	 * @covers ::matches
	 *
	 * @return void
	 */
	public function test_matches_dispatches_every_frequency(): void {
		$utc      = new DateTimeZone( 'UTC' );
		$expander = new Expander();
		$anchor   = new DateTimeImmutable( '2026-01-15', $utc );

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$expander,
				'matches',
				array(
					$this->make_rule(
						array(
							'frequency' => Rule::FREQUENCY_DAILY,
							'interval'  => 1,
							'end_type'  => Rule::END_TYPE_NEVER,
						)
					),
					$anchor->modify( '+1 day' ),
					$anchor,
				)
			),
			'Failed to assert that matches() dispatches a daily rule.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method(
				$expander,
				'matches',
				array(
					$this->make_rule( $this->reference_rule_values() ),
					new DateTimeImmutable( '2026-09-15', $utc ),
					new DateTimeImmutable( '2026-09-03', $utc ),
				)
			),
			'Failed to assert that matches() dispatches a weekly rule.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method(
				$expander,
				'matches',
				array(
					$this->make_rule(
						array(
							'frequency'    => Rule::FREQUENCY_MONTHLY,
							'interval'     => 1,
							'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
							'monthly_day'  => 15,
							'end_type'     => Rule::END_TYPE_NEVER,
						)
					),
					new DateTimeImmutable( '2026-02-15', $utc ),
					$anchor,
				)
			),
			'Failed to assert that matches() dispatches a monthly rule.'
		);
	}

	/**
	 * The month distance is signed.
	 *
	 * @covers ::months_apart
	 *
	 * @return void
	 */
	public function test_months_apart_is_signed(): void {
		$utc      = new DateTimeZone( 'UTC' );
		$expander = new Expander();

		$this->assertSame(
			14,
			Utility::invoke_hidden_method(
				$expander,
				'months_apart',
				array( new DateTimeImmutable( '2026-01-15', $utc ), new DateTimeImmutable( '2027-03-01', $utc ) )
			),
			'Failed to assert that a later month yields a positive distance.'
		);
		$this->assertSame(
			-2,
			Utility::invoke_hidden_method(
				$expander,
				'months_apart',
				array( new DateTimeImmutable( '2026-03-15', $utc ), new DateTimeImmutable( '2026-01-15', $utc ) )
			),
			'Failed to assert that an earlier month yields a negative distance.'
		);
	}

	/**
	 * The scan window is derived per frequency.
	 *
	 * @covers ::day_scan_limit
	 *
	 * @return void
	 */
	public function test_day_scan_limit_per_frequency(): void {
		$expander = new Expander();

		$this->assertSame(
			3,
			Utility::invoke_hidden_method(
				$expander,
				'day_scan_limit',
				array(
					$this->make_rule(
						array(
							'frequency' => Rule::FREQUENCY_DAILY,
							'interval'  => 3,
							'end_type'  => Rule::END_TYPE_NEVER,
						)
					),
				)
			),
			'Failed to assert that a daily scan window is the interval.'
		);
		$this->assertSame(
			21,
			Utility::invoke_hidden_method(
				$expander,
				'day_scan_limit',
				array( $this->make_rule( $this->reference_rule_values() ) )
			),
			'Failed to assert that a weekly scan window is the interval in days plus one week.'
		);
	}

	/**
	 * Resolving an nth weekday covers the ordinal, the "last" ordinal, and the missing case.
	 *
	 * @covers ::nth_weekday_of_month
	 *
	 * @return void
	 */
	public function test_nth_weekday_of_month_covers_every_return_path(): void {
		$expander = new Expander();

		$this->assertSame(
			'2026-02-19',
			Utility::invoke_hidden_method( $expander, 'nth_weekday_of_month', array( 2026, 2, 4, 3 ) ),
			'Failed to assert that the third Thursday of February 2026 resolves.'
		);
		$this->assertSame(
			'2026-04-29',
			Utility::invoke_hidden_method( $expander, 'nth_weekday_of_month', array( 2026, 4, 3, -1 ) ),
			'Failed to assert that the last Wednesday of a five-Wednesday month resolves.'
		);
		$this->assertSame(
			'2026-03-25',
			Utility::invoke_hidden_method( $expander, 'nth_weekday_of_month', array( 2026, 3, 3, -1 ) ),
			'Failed to assert that the last Wednesday of a four-Wednesday month resolves.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method( $expander, 'nth_weekday_of_month', array( 2026, 2, 4, 5 ) ),
			'Failed to assert that a month without a fifth Thursday resolves to null.'
		);
	}

	/**
	 * Resolving a day of the month covers the valid and invalid cases.
	 *
	 * @covers ::day_of_month_date
	 *
	 * @return void
	 */
	public function test_day_of_month_date_covers_both_return_paths(): void {
		$expander = new Expander();

		$this->assertSame(
			'2026-01-31',
			Utility::invoke_hidden_method( $expander, 'day_of_month_date', array( 2026, 1, 31 ) ),
			'Failed to assert that a day the month has resolves.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method( $expander, 'day_of_month_date', array( 2026, 2, 31 ) ),
			'Failed to assert that a day the month lacks resolves to null.'
		);
	}

	/**
	 * Resolving a monthly offset covers both modes and the missing case.
	 *
	 * @covers ::monthly_date_for_offset
	 *
	 * @return void
	 */
	public function test_monthly_date_for_offset_covers_every_mode(): void {
		$utc      = new DateTimeZone( 'UTC' );
		$anchor   = new DateTimeImmutable( '2026-01-31', $utc );
		$expander = new Expander();
		$by_day   = $this->make_rule(
			array(
				'frequency'    => Rule::FREQUENCY_MONTHLY,
				'interval'     => 1,
				'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
				'monthly_day'  => 31,
				'end_type'     => Rule::END_TYPE_NEVER,
			)
		);

		$this->assertSame(
			'2026-03-31',
			Utility::invoke_hidden_method(
				$expander,
				'monthly_date_for_offset',
				array( $by_day, $anchor, 2 )
			)->format( 'Y-m-d' ),
			'Failed to assert that a day-of-month offset resolves.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method( $expander, 'monthly_date_for_offset', array( $by_day, $anchor, 1 ) ),
			'Failed to assert that a day-of-month offset into February resolves to null.'
		);
		$this->assertSame(
			'2026-02-19',
			Utility::invoke_hidden_method(
				$expander,
				'monthly_date_for_offset',
				array(
					$this->make_rule(
						array(
							'frequency'       => Rule::FREQUENCY_MONTHLY,
							'interval'        => 1,
							'monthly_mode'    => Rule::MONTHLY_MODE_NTH_WEEKDAY,
							'monthly_ordinal' => 3,
							'monthly_weekday' => 4,
							'end_type'        => Rule::END_TYPE_NEVER,
						)
					),
					$anchor,
					1,
				)
			)->format( 'Y-m-d' ),
			'Failed to assert that an nth-weekday offset resolves.'
		);
	}

	/**
	 * Materializing covers the valid, unparsable, and nonexistent-time paths.
	 *
	 * @covers ::materialize
	 *
	 * @return void
	 */
	public function test_materialize_covers_every_return_path(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$expander = new Expander();

		$this->assertSame(
			'2026-09-03 18:00:00',
			Utility::invoke_hidden_method(
				$expander,
				'materialize',
				array( '2026-09-03', '18:00:00', $timezone )
			)->format( 'Y-m-d H:i:s' ),
			'Failed to assert that an existing local time materializes.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method(
				$expander,
				'materialize',
				array( 'not-a-date', '18:00:00', $timezone )
			),
			'Failed to assert that an unparsable date materializes to null.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method(
				$expander,
				'materialize',
				array( '2026-03-08', '02:30:00', $timezone )
			),
			'Failed to assert that a nonexistent local time materializes to null.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method(
				$expander,
				'materialize',
				array( '2011-12-30', '09:00:00', new DateTimeZone( 'Pacific/Apia' ) )
			),
			'Failed to assert that a date the zone never had materializes to null.'
		);
	}

	/**
	 * The week index buckets a Monday-start week.
	 *
	 * @covers ::week_index
	 *
	 * @return void
	 */
	public function test_week_index_buckets_monday_start_weeks(): void {
		$utc      = new DateTimeZone( 'UTC' );
		$expander = new Expander();
		$monday   = Utility::invoke_hidden_method(
			$expander,
			'week_index',
			array( new DateTimeImmutable( '2026-08-31', $utc ) )
		);
		$sunday   = Utility::invoke_hidden_method(
			$expander,
			'week_index',
			array( new DateTimeImmutable( '2026-09-06', $utc ) )
		);
		$next     = Utility::invoke_hidden_method(
			$expander,
			'week_index',
			array( new DateTimeImmutable( '2026-09-07', $utc ) )
		);

		$this->assertSame(
			$monday,
			$sunday,
			'Failed to assert that Monday and the following Sunday share a week bucket.'
		);
		$this->assertSame(
			$monday + 1,
			$next,
			'Failed to assert that the next Monday starts the next week bucket.'
		);
	}

	/**
	 * Reducing a datetime to a date drops the time and normalizes to UTC.
	 *
	 * @covers ::date_only
	 *
	 * @return void
	 */
	public function test_date_only_drops_the_time(): void {
		$date = Utility::invoke_hidden_method(
			new Expander(),
			'date_only',
			array( $this->reference_anchor() )
		);

		$this->assertSame(
			'2026-09-03 00:00:00 UTC',
			$date->format( 'Y-m-d H:i:s T' ),
			'Failed to assert that a datetime reduces to a UTC-midnight date.'
		);
	}

	/**
	 * The end-date guard covers a bounded rule on both sides and an unbounded rule.
	 *
	 * @covers ::past_until
	 *
	 * @return void
	 */
	public function test_past_until_covers_every_outcome(): void {
		$utc      = new DateTimeZone( 'UTC' );
		$expander = new Expander();
		$values   = $this->reference_rule_values();

		$values['end_type'] = Rule::END_TYPE_UNTIL;
		$values['until']    = '2026-10-01';
		unset( $values['count'] );

		$bounded = $this->make_rule( $values );

		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'past_until',
				array( $bounded, new DateTimeImmutable( '2026-10-01', $utc ) )
			),
			'Failed to assert that the end date itself is not past the end date.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method(
				$expander,
				'past_until',
				array( $bounded, new DateTimeImmutable( '2026-10-02', $utc ) )
			),
			'Failed to assert that the day after the end date is past it.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'past_until',
				array(
					$this->make_rule( $this->reference_rule_values() ),
					new DateTimeImmutable( '2099-01-01', $utc ),
				)
			),
			'Failed to assert that a rule without an end date is never past one.'
		);
	}

	/**
	 * Get the yearly rule values used by the plain (non-leap) yearly fixtures.
	 *
	 * @since 0.36.0
	 *
	 * @param int $interval Repeat interval in years.
	 *
	 * @return array The rule values.
	 */
	protected function yearly_rule_values( int $interval = 1 ): array {
		return array(
			'frequency' => Rule::FREQUENCY_YEARLY,
			'interval'  => $interval,
			'end_type'  => Rule::END_TYPE_NEVER,
		);
	}

	/**
	 * Get the leap-day series anchor, 29 February 2024 at 18:00 in New York.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The anchor.
	 */
	protected function leap_day_anchor(): DateTimeImmutable {
		return new DateTimeImmutable( '2024-02-29 18:00:00', new DateTimeZone( 'America/New_York' ) );
	}

	/**
	 * A yearly rule repeats on the anchor's own month and day.
	 *
	 * Yearly is "every N years", with the month and day taken from the series
	 * start. There is no mode switch and no `BYMONTH`.
	 *
	 * @covers ::expand
	 * @covers ::next_candidate_date
	 * @covers ::next_monthly_date
	 * @covers ::month_step
	 * @covers ::month_scan_steps
	 * @covers ::monthly_date_for_offset
	 * @covers ::day_of_month_date
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_yearly_interval_one_derives_month_and_day_from_the_anchor(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$this->assertSame(
			array(
				'2026-09-03 18:00:00',
				'2027-09-03 18:00:00',
				'2028-09-03 18:00:00',
				'2029-09-03 18:00:00',
			),
			$this->to_local_strings(
				( new Expander() )->expand(
					$this->make_rule( $this->yearly_rule_values() ),
					$this->reference_anchor(),
					$timezone,
					new DateTimeImmutable( '2029-12-31 23:59:59', $timezone )
				)
			),
			'Failed to assert that a yearly rule repeats on the anchor month and day.'
		);
	}

	/**
	 * A yearly rule at interval three lands on every third year.
	 *
	 * @covers ::expand
	 * @covers ::next_monthly_date
	 * @covers ::month_step
	 *
	 * @return void
	 */
	public function test_yearly_interval_three_steps_every_third_year(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$this->assertSame(
			array(
				'2026-09-03 18:00:00',
				'2029-09-03 18:00:00',
				'2032-09-03 18:00:00',
				'2035-09-03 18:00:00',
			),
			$this->to_local_strings(
				( new Expander() )->expand(
					$this->make_rule( $this->yearly_rule_values( 3 ) ),
					$this->reference_anchor(),
					$timezone,
					new DateTimeImmutable( '2035-12-31 23:59:59', $timezone )
				)
			),
			'Failed to assert that a yearly interval of three steps three years at a time.'
		);
	}

	/**
	 * A series anchored 29 February skips every year that has no 29 February.
	 *
	 * RFC 5545 section 3.3.10: "Recurrence rules may generate recurrence
	 * instances with an invalid date ... Such recurrence instances MUST be
	 * ignored and MUST NOT be counted as part of the recurrence set." So 2025,
	 * 2026 and 2027 produce nothing at all, neither 1 March nor 28 February.
	 *
	 * The expected set is written out literally rather than computed: a
	 * computed expectation would agree with a rolling
	 * implementation as readily as with a skipping one.
	 *
	 * @covers ::expand
	 * @covers ::next_monthly_date
	 * @covers ::monthly_date_for_offset
	 * @covers ::day_of_month_date
	 *
	 * @return void
	 */
	public function test_yearly_leap_day_anchor_skips_non_leap_years(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$this->assertSame(
			array(
				'2024-02-29 18:00:00',
				'2028-02-29 18:00:00',
				'2032-02-29 18:00:00',
			),
			$this->to_local_strings(
				( new Expander() )->expand(
					$this->make_rule( $this->yearly_rule_values() ),
					$this->leap_day_anchor(),
					$timezone,
					new DateTimeImmutable( '2035-12-31 23:59:59', $timezone )
				)
			),
			'Failed to assert that a leap-day series skips the three non-leap years between occurrences.'
		);
	}

	/**
	 * A leap-day series bounded by `COUNT=3` yields exactly three occurrences.
	 *
	 * The conformance case. A skipped 29 February must not consume a count
	 * budget, so a `COUNT=3` rule anchored on 2024-02-29 spans nine years and
	 * still delivers three occurrences. The plausible wrong implementation
	 * decrements the budget per candidate year and filters the invalid dates
	 * afterwards. That delivers one, and it looks entirely reasonable while
	 * doing it. The horizon is deliberately set inside the first gap so a count-bounded
	 * rule that leaked horizon logic would truncate here too.
	 *
	 * @covers ::expand
	 * @covers ::next_monthly_date
	 * @covers ::monthly_date_for_offset
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_yearly_leap_day_count_three_yields_exactly_three_occurrences(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$values   = $this->yearly_rule_values();

		$values['end_type'] = Rule::END_TYPE_COUNT;
		$values['count']    = 3;

		$occurrences = ( new Expander() )->expand(
			$this->make_rule( $values ),
			$this->leap_day_anchor(),
			$timezone,
			new DateTimeImmutable( '2025-12-31 23:59:59', $timezone )
		);

		$this->assertCount(
			3,
			$occurrences,
			'Failed to assert that skipped leap years do not consume the COUNT budget.'
		);
		$this->assertSame(
			array(
				'2024-02-29 18:00:00',
				'2028-02-29 18:00:00',
				'2032-02-29 18:00:00',
			),
			$this->to_local_strings( $occurrences ),
			'Failed to assert the literal leap-day occurrence set under COUNT.'
		);
	}

	/**
	 * A count-bounded yearly rule yields exactly its count.
	 *
	 * @covers ::expand
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_yearly_count_yields_exactly_n(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$values   = $this->yearly_rule_values();

		$values['end_type'] = Rule::END_TYPE_COUNT;
		$values['count']    = 4;

		$this->assertSame(
			array(
				'2026-09-03 18:00:00',
				'2027-09-03 18:00:00',
				'2028-09-03 18:00:00',
				'2029-09-03 18:00:00',
			),
			$this->to_local_strings(
				( new Expander() )->expand(
					$this->make_rule( $values ),
					$this->reference_anchor(),
					$timezone,
					new DateTimeImmutable( '2026-12-31 23:59:59', $timezone )
				)
			),
			'Failed to assert that a count-bounded yearly rule yields exactly its count.'
		);
	}

	/**
	 * A yearly `UNTIL` includes an occurrence landing on the end date itself.
	 *
	 * Asserted from both sides: moving the end date one day earlier drops the
	 * final occurrence, so the inclusive boundary cannot pass by accident.
	 *
	 * @covers ::expand
	 * @covers ::past_until
	 *
	 * @return void
	 */
	public function test_yearly_until_is_inclusive_of_its_own_day(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$horizon  = new DateTimeImmutable( '2040-12-31 23:59:59', $timezone );
		$values   = $this->yearly_rule_values();

		$values['end_type'] = Rule::END_TYPE_UNTIL;
		$values['until']    = '2029-09-03';

		$this->assertSame(
			array(
				'2026-09-03 18:00:00',
				'2027-09-03 18:00:00',
				'2028-09-03 18:00:00',
				'2029-09-03 18:00:00',
			),
			$this->to_local_strings(
				( new Expander() )->expand(
					$this->make_rule( $values ),
					$this->reference_anchor(),
					$timezone,
					$horizon
				)
			),
			'Failed to assert that UNTIL includes an occurrence on its own day.'
		);

		$values['until'] = '2029-09-02';

		$this->assertSame(
			array(
				'2026-09-03 18:00:00',
				'2027-09-03 18:00:00',
				'2028-09-03 18:00:00',
			),
			$this->to_local_strings(
				( new Expander() )->expand(
					$this->make_rule( $values ),
					$this->reference_anchor(),
					$timezone,
					$horizon
				)
			),
			'Failed to assert that UNTIL excludes the day after it.'
		);
	}

	/**
	 * A yearly series keeps its wall clock while its UTC offset moves.
	 *
	 * 1 November is the date that makes this visible in `America/New_York`:
	 * daylight saving ends on the first Sunday in November, which is 1 November
	 * in 2026 and later in every following year in the window. So the same 18:00
	 * wall clock is -05:00 in 2026 and -04:00 afterwards. The time of
	 * day belongs to the occurrence, and the offset is whatever the zone says on
	 * that date.
	 *
	 * @covers ::expand
	 * @covers ::materialize
	 *
	 * @return void
	 */
	public function test_yearly_preserves_wall_clock_across_dst(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$values   = $this->yearly_rule_values();

		$values['end_type'] = Rule::END_TYPE_COUNT;
		$values['count']    = 4;

		$occurrences = ( new Expander() )->expand(
			$this->make_rule( $values ),
			new DateTimeImmutable( '2026-11-01 18:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2027-01-01 00:00:00', $timezone )
		);

		$this->assertSame(
			array(
				'2026-11-01 18:00:00 -05:00',
				'2027-11-01 18:00:00 -04:00',
				'2028-11-01 18:00:00 -04:00',
				'2029-11-01 18:00:00 -04:00',
			),
			array_map(
				static function ( DateTimeImmutable $occurrence ): string {
					return $occurrence->format( 'Y-m-d H:i:s P' );
				},
				$occurrences
			),
			'Failed to assert that the wall clock holds while the UTC offset shifts.'
		);
	}

	/**
	 * The candidate step is the interval in months, or twelve times it for yearly.
	 *
	 * @covers ::month_step
	 *
	 * @return void
	 */
	public function test_month_step_is_the_interval_in_months(): void {
		$expander = new Expander();

		$this->assertSame(
			3,
			Utility::invoke_hidden_method(
				$expander,
				'month_step',
				array(
					$this->make_rule(
						array(
							'frequency'    => Rule::FREQUENCY_MONTHLY,
							'interval'     => 3,
							'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
							'monthly_day'  => 15,
							'end_type'     => Rule::END_TYPE_NEVER,
						)
					),
				)
			),
			'Failed to assert that a monthly rule steps its interval in months.'
		);
		$this->assertSame(
			36,
			Utility::invoke_hidden_method(
				$expander,
				'month_step',
				array( $this->make_rule( $this->yearly_rule_values( 3 ) ) )
			),
			'Failed to assert that a yearly rule steps twelve months per interval unit.'
		);
	}

	/**
	 * The scan bound is the monthly one, or the wider yearly one for yearly.
	 *
	 * @covers ::month_scan_steps
	 *
	 * @return void
	 */
	public function test_month_scan_steps_is_wider_for_yearly(): void {
		$expander = new Expander();

		$this->assertSame(
			Expander::MONTH_SCAN_STEPS,
			Utility::invoke_hidden_method(
				$expander,
				'month_scan_steps',
				array(
					$this->make_rule(
						array(
							'frequency'    => Rule::FREQUENCY_MONTHLY,
							'interval'     => 1,
							'monthly_mode' => Rule::MONTHLY_MODE_DAY_OF_MONTH,
							'monthly_day'  => 31,
							'end_type'     => Rule::END_TYPE_NEVER,
						)
					),
				)
			),
			'Failed to assert that a monthly rule uses the monthly scan bound.'
		);
		$this->assertSame(
			Expander::YEAR_SCAN_STEPS,
			Utility::invoke_hidden_method(
				$expander,
				'month_scan_steps',
				array( $this->make_rule( $this->yearly_rule_values() ) )
			),
			'Failed to assert that a yearly rule uses the yearly scan bound.'
		);
	}

	/**
	 * The yearly scan bound clears the worst run of unusable candidates.
	 *
	 * The bound is counted in candidate steps, not in absolute years, so a wide
	 * interval cannot collapse it. Its worst case is a 29 February anchor whose
	 * interval keeps landing on non-leap years: an exhaustive search over every
	 * interval from 1 to `Rule::MAX_INTERVAL` and every leap-day anchor from
	 * 1600 to 2400 puts that run at fifteen, at interval 25 from a 1600 anchor
	 * crossing the 1700 / 1800 / 1900 non-leap centuries. This re-runs that
	 * search rather than trusting the number, so a later change to
	 * `Rule::MAX_INTERVAL` cannot silently invalidate the bound.
	 *
	 * @return void
	 */
	public function test_year_scan_steps_clears_the_measured_worst_case(): void {
		$worst = 0;

		for ( $interval = 1; $interval <= Rule::MAX_INTERVAL; $interval++ ) {
			for ( $anchor = 1600; $anchor <= 2400; $anchor++ ) {
				if ( ! checkdate( 2, 29, $anchor ) ) {
					continue;
				}

				$run = 0;

				for ( $step = 1; $step <= 400; $step++ ) {
					if ( checkdate( 2, 29, $anchor + $interval * $step ) ) {
						$worst = max( $worst, $run );
						$run   = 0;
					} else {
						++$run;
					}
				}

				// A run still open when the search window closes is an artifact
				// of the window rather than a terminated gap, but folding it in
				// keeps the measurement conservative: the bound must clear it
				// either way, and discarding it could hide a longer gap that
				// simply straddled the edge.
				$worst = max( $worst, $run );
			}
		}

		$this->assertSame(
			15,
			$worst,
			'Failed to assert the measured worst run of unusable yearly candidates.'
		);
		$this->assertGreaterThan(
			$worst,
			Expander::YEAR_SCAN_STEPS,
			'Failed to assert that the yearly scan bound clears the worst measured run.'
		);
	}

	/**
	 * The month walk resolves a yearly rule by stepping whole years.
	 *
	 * @covers ::next_monthly_date
	 * @covers ::month_step
	 * @covers ::month_scan_steps
	 *
	 * @return void
	 */
	public function test_next_monthly_date_walks_years_for_a_yearly_rule(): void {
		$utc    = new DateTimeZone( 'UTC' );
		$anchor = new DateTimeImmutable( '2024-02-29', $utc );

		$found = Utility::invoke_hidden_method(
			new Expander(),
			'next_monthly_date',
			array(
				$this->make_rule( $this->yearly_rule_values() ),
				$anchor->modify( '+1 day' ),
				$anchor,
			)
		);

		$this->assertInstanceOf(
			DateTimeImmutable::class,
			$found,
			'Failed to assert that the month walk resolves a yearly candidate.'
		);
		$this->assertSame(
			'2028-02-29',
			$found->format( 'Y-m-d' ),
			'Failed to assert that the month walk skips past the non-leap years.'
		);
	}

	/**
	 * Resolving a month offset derives a yearly rule's day from the anchor.
	 *
	 * @covers ::monthly_date_for_offset
	 * @covers ::day_of_month_date
	 *
	 * @return void
	 */
	public function test_monthly_date_for_offset_derives_the_day_from_a_yearly_anchor(): void {
		$expander = new Expander();
		$anchor   = new DateTimeImmutable( '2024-02-29', new DateTimeZone( 'UTC' ) );
		$rule     = $this->make_rule( $this->yearly_rule_values() );

		$found = Utility::invoke_hidden_method(
			$expander,
			'monthly_date_for_offset',
			array( $rule, $anchor, 48 )
		);

		$this->assertInstanceOf(
			DateTimeImmutable::class,
			$found,
			'Failed to assert that a leap year four years out resolves.'
		);
		$this->assertSame(
			'2028-02-29',
			$found->format( 'Y-m-d' ),
			'Failed to assert that the yearly day comes from the anchor.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method(
				$expander,
				'monthly_date_for_offset',
				array( $rule, $anchor, 12 )
			),
			'Failed to assert that a non-leap year resolves to nothing rather than rolling.'
		);
	}

	/**
	 * The month-walk predicate answers a yearly rule on both outcomes.
	 *
	 * @covers ::matches
	 * @covers ::matches_monthly
	 * @covers ::month_step
	 * @covers ::months_apart
	 *
	 * @return void
	 */
	public function test_matches_monthly_covers_a_yearly_rule(): void {
		$utc      = new DateTimeZone( 'UTC' );
		$expander = new Expander();
		$anchor   = new DateTimeImmutable( '2024-02-29', $utc );
		$rule     = $this->make_rule( $this->yearly_rule_values() );

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$expander,
				'matches',
				array( $rule, new DateTimeImmutable( '2028-02-29', $utc ), $anchor )
			),
			'Failed to assert that matches() dispatches a yearly rule.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_monthly',
				array( $rule, new DateTimeImmutable( '2025-02-28', $utc ), $anchor )
			),
			'Failed to assert that a non-leap anniversary does not fall back to 28 February.'
		);
		// The off-interval case deliberately uses a non-leap anchor. Asserting it
		// from the leap-day anchor instead would pass for the wrong reason: an
		// implementation that took the step width as the raw interval rather than
		// twelve times it would let 2026-02-28 through the guard at offset 24,
		// then reject it anyway because 2026-02-29 does not exist. With a
		// 2026-09-03 anchor nothing downstream can rescue the assertion, since
		// offset 12 resolves to a real 2027-09-03. The interval guard is
		// therefore the only thing that can produce false, which is the behavior
		// under test.
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$expander,
				'matches_monthly',
				array(
					$this->make_rule( $this->yearly_rule_values( 3 ) ),
					new DateTimeImmutable( '2027-09-03', $utc ),
					new DateTimeImmutable( '2026-09-03', $utc ),
				)
			),
			'Failed to assert that an off-interval year does not match.'
		);
	}

	/**
	 * Dispatching a yearly rule returns the next anniversary.
	 *
	 * @covers ::next_candidate_date
	 * @covers ::day_scan_limit
	 *
	 * @return void
	 */
	public function test_next_candidate_date_dispatches_yearly(): void {
		$utc      = new DateTimeZone( 'UTC' );
		$expander = new Expander();
		$anchor   = new DateTimeImmutable( '2026-09-03', $utc );
		$rule     = $this->make_rule( $this->yearly_rule_values() );

		$candidate = Utility::invoke_hidden_method(
			$expander,
			'next_candidate_date',
			array( $rule, $anchor->modify( '+1 day' ), $anchor )
		);

		$this->assertInstanceOf(
			DateTimeImmutable::class,
			$candidate,
			'Failed to assert that a yearly rule yields a candidate.'
		);
		$this->assertSame(
			'2027-09-03',
			$candidate->format( 'Y-m-d' ),
			'Failed to assert that the yearly candidate is the next anniversary.'
		);
		$this->assertSame(
			0,
			Utility::invoke_hidden_method( $expander, 'day_scan_limit', array( $rule ) ),
			'Failed to assert that a yearly rule is month-walked rather than day-scanned.'
		);
	}

	/**
	 * A count-bounded yearly rule budgets 366 day-steps per occurrence.
	 *
	 * @covers ::iteration_budget
	 *
	 * @return void
	 */
	public function test_iteration_budget_for_a_count_bounded_yearly_rule(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$values   = $this->yearly_rule_values( 2 );

		$values['end_type'] = Rule::END_TYPE_COUNT;
		$values['count']    = 5;

		$this->assertSame(
			366 + ( 5 * 366 * 2 ),
			Utility::invoke_hidden_method(
				new Expander(),
				'iteration_budget',
				array(
					$this->make_rule( $values ),
					$this->reference_anchor(),
					new DateTimeImmutable( '2026-09-13 18:00:00', $timezone ),
				)
			),
			'Failed to assert that a yearly budget uses 366 days per occurrence.'
		);
	}
}
