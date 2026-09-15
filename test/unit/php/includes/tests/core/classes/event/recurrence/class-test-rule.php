<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Rule.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Expander;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Rule;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rule.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Rule
 */
class Test_Rule extends Base {

	use Occurrence_Fixtures;

	/**
	 * `from_post()` returns null for a post carrying no recurrence rule.
	 *
	 * @covers ::from_post
	 *
	 * @return void
	 */
	public function test_from_post_returns_null_without_rule(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$this->assertNull( Rule::from_post( $post_id ) );
	}

	/**
	 * `from_post()` reconstructs a weekly rule from the derived mirrors, and
	 * every mirror holds the expected typed value after `set_recurrence()`.
	 *
	 * @covers ::from_post
	 * @covers ::from_array
	 * @covers ::__construct
	 * @covers ::frequency
	 * @covers ::interval
	 * @covers ::weekdays
	 * @covers ::monthly_mode
	 * @covers ::monthly_day
	 * @covers ::monthly_ordinal
	 * @covers ::monthly_weekday
	 * @covers ::end_type
	 * @covers ::until
	 * @covers ::count
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_from_post_round_trips_weekly_rule(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 2,
				'weekdays'  => array( 2, 4 ),
				'end_type'  => 'count',
				'count'     => 5,
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );

		$this->assertSame( 'weekly', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );
		$this->assertSame( '2', get_post_meta( $post_id, 'gatherpress_recurrence_interval', true ) );
		$this->assertSame( 'TU,TH', get_post_meta( $post_id, 'gatherpress_recurrence_byday', true ) );
		$this->assertSame( '', get_post_meta( $post_id, 'gatherpress_recurrence_monthly_mode', true ) );
		$this->assertSame( '0', get_post_meta( $post_id, 'gatherpress_recurrence_monthly_day', true ) );
		$this->assertSame( '0', get_post_meta( $post_id, 'gatherpress_recurrence_monthly_ordinal', true ) );
		$this->assertSame( '0', get_post_meta( $post_id, 'gatherpress_recurrence_monthly_weekday', true ) );
		$this->assertSame( 'count', get_post_meta( $post_id, 'gatherpress_recurrence_end_type', true ) );
		$this->assertSame( '', get_post_meta( $post_id, 'gatherpress_recurrence_until', true ) );
		$this->assertSame( '5', get_post_meta( $post_id, 'gatherpress_recurrence_count', true ) );

		$rule = Rule::from_post( $post_id );

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assertSame( 'weekly', $rule->frequency() );
		$this->assertSame( 2, $rule->interval() );
		$this->assertSame( array( 2, 4 ), $rule->weekdays() );
		$this->assertSame( 'count', $rule->end_type() );
		$this->assertSame( 5, $rule->count() );
		$this->assertNull( $rule->until() );
	}

	/**
	 * `from_post()` reconstructs a monthly day-of-month rule.
	 *
	 * @covers ::from_post
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_from_post_round_trips_monthly_day_of_month_rule(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency'    => 'monthly',
				'interval'     => 1,
				'monthly_mode' => 'day_of_month',
				'monthly_day'  => 15,
				'end_type'     => 'until',
				'until'        => '2027-06-15',
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );

		$rule = Rule::from_post( $post_id );

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assertSame( 'monthly', $rule->frequency() );
		$this->assertSame( 'day_of_month', $rule->monthly_mode() );
		$this->assertSame( 15, $rule->monthly_day() );
		$this->assertSame( 'until', $rule->end_type() );
		$this->assertInstanceOf( DateTimeImmutable::class, $rule->until() );
		$this->assertSame( '2027-06-15', $rule->until()->format( 'Y-m-d' ) );
		$this->assertSame( 0, $rule->count() );
	}

	/**
	 * `from_post()` reconstructs a monthly nth-weekday rule, including "last".
	 *
	 * @covers ::from_post
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_from_post_round_trips_monthly_nth_weekday_last_rule(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency'       => 'monthly',
				'interval'        => 1,
				'monthly_mode'    => 'nth_weekday',
				'monthly_ordinal' => -1,
				'monthly_weekday' => 3,
				'end_type'        => 'never',
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );

		$this->assertSame( '-1', get_post_meta( $post_id, 'gatherpress_recurrence_monthly_ordinal', true ) );

		$rule = Rule::from_post( $post_id );

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assertSame( 'nth_weekday', $rule->monthly_mode() );
		$this->assertSame( -1, $rule->monthly_ordinal() );
		$this->assertSame( 3, $rule->monthly_weekday() );
		$this->assertSame( 'never', $rule->end_type() );
	}

	/**
	 * A zero or negative interval clamps up to one.
	 *
	 * @covers ::from_array
	 * @covers ::interval
	 *
	 * @return void
	 */
	public function test_interval_zero_and_negative_clamp_to_one(): void {
		$zero     = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 0,
				'end_type'  => 'never',
			)
		);
		$negative = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => -5,
				'end_type'  => 'never',
			)
		);

		$this->assertInstanceOf( Rule::class, $zero );
		$this->assertSame( 1, $zero->interval() );
		$this->assertInstanceOf( Rule::class, $negative );
		$this->assertSame( 1, $negative->interval() );
	}

	/**
	 * `UNTIL` and `COUNT` are mutually exclusive; both present is rejected.
	 *
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_until_and_count_are_mutually_exclusive(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'until'     => '2026-01-01',
				'count'     => 5,
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A `COUNT` above `Rule::MAX_COUNT` is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_count_above_max_is_rejected(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => Rule::MAX_COUNT + 1,
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * An `INTERVAL` above `Rule::MAX_INTERVAL` is rejected outright, never clamped.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_interval_above_max_is_rejected(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => Rule::MAX_INTERVAL + 1,
				'end_type'  => 'count',
				'count'     => 1,
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A rule whose worst-case iteration budget exceeds `Expander::MAX_ITERATIONS`
	 * is rejected at the boundary, not silently truncated at expansion time.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_rule_whose_iteration_budget_exceeds_backstop_is_rejected(): void {
		$rule = Rule::from_array(
			array(
				'frequency'    => 'monthly',
				'interval'     => 10,
				'end_type'     => 'count',
				'count'        => 730,
				'monthly_mode' => 'day_of_month',
				'monthly_day'  => 1,
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A weekly rule with no weekdays is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_weekly_without_weekdays_is_rejected(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array(),
				'end_type'  => 'never',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A rule naming an unrecognized frequency is rejected at the boundary.
	 *
	 * `Expander`'s defensive `default => null` arms
	 * (`next_candidate_date()`, `matches()`, `day_scan_limit()`) exist so an
	 * unknown frequency yields zero occurrences rather than a fatal, in case
	 * one is ever handed to the expander directly. This asserts the boundary
	 * this rule is actually supposed to be enforced at: `from_array()` never
	 * lets an unrecognized frequency reach the expander in the first place.
	 *
	 * The fixture value used to be `yearly`, which was correct until the yearly
	 * frequency was added. It is now `fortnightly`, which is plausible but
	 * genuinely unsupported, so the boundary stays covered from the rejecting
	 * side while `test_from_array_accepts_yearly_frequency()` covers the
	 * accepting side.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_from_array_rejects_unrecognized_frequency(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'fortnightly',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);

		$this->assertNull(
			$rule,
			'Failed to assert that an unrecognized frequency is rejected.'
		);
	}

	/**
	 * A yearly rule is accepted at the boundary and needs no monthly fields.
	 *
	 * Yearly repeats every N years with the month and day derived from the
	 * series start, so it carries no `monthly_mode`,
	 * no weekday list and no mode switch of its own. `BYMONTH`, `BYYEARDAY`
	 * and `BYWEEKNO` are permanent non-goals.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 * @covers ::frequency
	 * @covers ::interval
	 *
	 * @return void
	 */
	public function test_from_array_accepts_yearly_frequency(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'yearly',
				'interval'  => 3,
				'end_type'  => 'never',
			)
		);

		$this->assertInstanceOf(
			Rule::class,
			$rule,
			'Failed to assert that a yearly rule is accepted at the boundary.'
		);
		$this->assertSame(
			Rule::FREQUENCY_YEARLY,
			$rule->frequency(),
			'Failed to assert that the yearly frequency round-trips.'
		);
		$this->assertSame(
			3,
			$rule->interval(),
			'Failed to assert that the yearly interval round-trips.'
		);
	}

	/**
	 * The series timezone every `to_rrule_string()` fixture in this file is
	 * serialized against.
	 *
	 * Named, and not UTC: `UNTIL` is emitted as a UTC date-time derived from the
	 * anchor's wall clock, so a fixture serialized in UTC could not tell a
	 * correct conversion apart from no conversion at all.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeZone The fixture timezone.
	 */
	protected static function rrule_timezone(): DateTimeZone {
		return new DateTimeZone( 'America/New_York' );
	}

	/**
	 * The series anchor every `to_rrule_string()` fixture in this file is
	 * serialized against.
	 *
	 * Pinned, deliberately: these are pure input-to-output fixtures whose
	 * expected text is the specification, and nothing here is compared against
	 * the clock.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The fixture anchor start.
	 */
	protected static function rrule_anchor(): DateTimeImmutable {
		return new DateTimeImmutable( '2026-06-15 19:00:00', self::rrule_timezone() );
	}

	/**
	 * A yearly rule serializes to `FREQ=YEARLY` with no `BY*` part.
	 *
	 * @covers ::to_rrule_string
	 *
	 * @return void
	 */
	public function test_to_rrule_string_emits_freq_yearly(): void {
		$fixtures = array(
			// Yearly, never-ending, interval 1, which is omitted at 1.
			array(
				'values'   => array(
					'frequency' => 'yearly',
					'interval'  => 1,
					'end_type'  => 'never',
				),
				'expected' => 'FREQ=YEARLY',
			),
			// Yearly, count-bounded, interval 3.
			array(
				'values'   => array(
					'frequency' => 'yearly',
					'interval'  => 3,
					'end_type'  => 'count',
					'count'     => 5,
				),
				'expected' => 'FREQ=YEARLY;INTERVAL=3;COUNT=5',
			),
			// Yearly, until-bounded, and never carrying BYMONTH.
			array(
				'values'   => array(
					'frequency' => 'yearly',
					'interval'  => 1,
					'end_type'  => 'until',
					'until'     => '2031-02-28',
				),
				'expected' => 'FREQ=YEARLY;UNTIL=20310301T000000Z',
			),
		);

		foreach ( $fixtures as $fixture ) {
			$rule = Rule::from_array( $fixture['values'] );

			$this->assertInstanceOf(
				Rule::class,
				$rule,
				'Fixture rule failed to build: ' . wp_json_encode( $fixture['values'] )
			);
			$this->assertSame(
				$fixture['expected'],
				$rule->to_rrule_string( self::rrule_anchor(), self::rrule_timezone() )
			);
		}
	}

	/**
	 * A yearly `COUNT` rule is rejected exactly where its budget crosses
	 * `Expander::MAX_ITERATIONS`, using 366 days per occurrence.
	 *
	 * The budget arithmetic is `count * per_frequency * interval + 366`, so at
	 * 366 days per yearly occurrence the largest accepted product of count and
	 * interval is 545: `545 * 366 * 1 + 366 = 199,836` fits inside the 200,000
	 * backstop, and one more occurrence does not: `546 * 366 + 366 = 200,202`.
	 * The interval side of the product is asserted independently so a
	 * multiplier that ignored `interval` could not pass.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_yearly_iteration_budget_is_enforced_at_the_meta_boundary(): void {
		// Tie the boundary to `Expander::MAX_ITERATIONS` rather than to the
		// literals 545 and 546: if the backstop ever moves, these two fail
		// loudly instead of leaving the rule assertions below silently
		// asserting the wrong boundary.
		$this->assertLessThanOrEqual(
			Expander::MAX_ITERATIONS,
			( 545 * 366 * 1 ) + 366,
			'Failed to assert that the accepted-side budget fits the backstop.'
		);
		$this->assertGreaterThan(
			Expander::MAX_ITERATIONS,
			( 546 * 366 * 1 ) + 366,
			'Failed to assert that the rejected-side budget exceeds the backstop.'
		);

		$this->assertInstanceOf(
			Rule::class,
			Rule::from_array(
				array(
					'frequency' => 'yearly',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => 545,
				)
			),
			'Failed to assert that a yearly rule inside the budget is accepted.'
		);
		$this->assertNull(
			Rule::from_array(
				array(
					'frequency' => 'yearly',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => 546,
				)
			),
			'Failed to assert that a yearly rule over the budget is rejected.'
		);

		// The same count either side of the interval boundary: 100 occurrences
		// at interval 5 fit, at interval 6 they do not.
		$this->assertInstanceOf(
			Rule::class,
			Rule::from_array(
				array(
					'frequency' => 'yearly',
					'interval'  => 5,
					'end_type'  => 'count',
					'count'     => 100,
				)
			),
			'Failed to assert that interval widens the budget rather than being ignored.'
		);
		$this->assertNull(
			Rule::from_array(
				array(
					'frequency' => 'yearly',
					'interval'  => 6,
					'end_type'  => 'count',
					'count'     => 100,
				)
			),
			'Failed to assert that a wider yearly interval is rejected on budget.'
		);
	}

	/**
	 * A weekly rule with a weekday outside 0-6 is rejected, whether the whole
	 * list is out of range or only one entry is. An unchecked value here
	 * would leave an undefined `WEEKDAY_CODES` lookup at write-mirror and
	 * RRULE-export time.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_weekly_with_out_of_range_weekday_is_rejected(): void {
		$too_high = Rule::from_array(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( 7 ),
				'end_type'  => 'never',
			)
		);
		$negative = Rule::from_array(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( -1 ),
				'end_type'  => 'never',
			)
		);
		$mixed    = Rule::from_array(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( -1, 3 ),
				'end_type'  => 'never',
			)
		);

		$this->assertNull( $too_high );
		$this->assertNull( $negative );
		$this->assertNull( $mixed );
	}

	/**
	 * A non-weekly rule carrying an out-of-range weekday is rejected too.
	 *
	 * The range check cannot be weekly-only, because its consumer is not:
	 * `Meta::write_mirrors()` maps `WEEKDAY_CODES` over `weekdays()` for every
	 * rule it writes, whatever the frequency. A daily rule carrying `7` used to
	 * pass validation and then warn on an undefined index while saving the
	 * post. The recurrence blob is `show_in_rest`, so the value is reachable by
	 * any user who can edit the post.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_non_weekly_with_out_of_range_weekday_is_rejected(): void {
		foreach ( array( 'daily', 'monthly', 'yearly' ) as $frequency ) {
			$this->assertNull(
				Rule::from_array(
					array(
						'frequency' => $frequency,
						'interval'  => 1,
						'weekdays'  => array( 7 ),
						'end_type'  => 'never',
					)
				),
				sprintf( 'A %s rule with weekday 7 should be rejected.', $frequency )
			);
			$this->assertNull(
				Rule::from_array(
					array(
						'frequency' => $frequency,
						'interval'  => 1,
						'weekdays'  => array( -1 ),
						'end_type'  => 'never',
					)
				),
				sprintf( 'A %s rule with weekday -1 should be rejected.', $frequency )
			);
		}

		// The tightened check must not start rejecting the shapes that were
		// always legal: a non-weekly rule naming no weekdays at all, and one
		// naming weekdays that are in range.
		$this->assertInstanceOf(
			Rule::class,
			Rule::from_array(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'weekdays'  => array(),
					'end_type'  => 'never',
				)
			),
			'A daily rule naming no weekdays stays valid.'
		);
		$this->assertInstanceOf(
			Rule::class,
			Rule::from_array(
				array(
					'frequency' => 'yearly',
					'interval'  => 1,
					'weekdays'  => array( 0, 6 ),
					'end_type'  => 'never',
				)
			),
			'A yearly rule naming in-range weekdays stays valid.'
		);
	}

	/**
	 * A monthly rule with neither a recognized monthly mode is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_monthly_with_unrecognized_mode_is_rejected(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'monthly',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A monthly day-of-month rule with a day outside 1-31 is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_monthly_day_of_month_out_of_range_is_rejected(): void {
		$too_low  = Rule::from_array(
			array(
				'frequency'    => 'monthly',
				'interval'     => 1,
				'monthly_mode' => 'day_of_month',
				'monthly_day'  => 0,
				'end_type'     => 'never',
			)
		);
		$too_high = Rule::from_array(
			array(
				'frequency'    => 'monthly',
				'interval'     => 1,
				'monthly_mode' => 'day_of_month',
				'monthly_day'  => 32,
				'end_type'     => 'never',
			)
		);

		$this->assertNull( $too_low );
		$this->assertNull( $too_high );
	}

	/**
	 * A monthly nth-weekday rule with an invalid ordinal or weekday is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_monthly_nth_weekday_out_of_range_is_rejected(): void {
		$bad_ordinal = Rule::from_array(
			array(
				'frequency'       => 'monthly',
				'interval'        => 1,
				'monthly_mode'    => 'nth_weekday',
				'monthly_ordinal' => 0,
				'monthly_weekday' => 2,
				'end_type'        => 'never',
			)
		);
		$bad_weekday = Rule::from_array(
			array(
				'frequency'       => 'monthly',
				'interval'        => 1,
				'monthly_mode'    => 'nth_weekday',
				'monthly_ordinal' => 1,
				'monthly_weekday' => 7,
				'end_type'        => 'never',
			)
		);

		$this->assertNull( $bad_ordinal );
		$this->assertNull( $bad_weekday );
	}

	/**
	 * An unrecognized `end_type` is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_unrecognized_end_type_is_rejected(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'bogus',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A `never`-ending rule carrying a stray `until` is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_never_end_type_rejects_stray_until(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
				'until'     => '2026-01-01',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A `never`-ending rule carrying a stray `count` is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_never_end_type_rejects_stray_count(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
				'count'     => 3,
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * An `until`-ending rule with no `until` value is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_until_end_type_without_until_value_is_rejected(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'until',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A malformed `until` string does not parse into a date and is rejected.
	 *
	 * @covers ::from_array
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_unparsable_until_is_rejected(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'until',
				'until'     => 'not-a-date',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * An arbitrary non-numeric interval string is rejected, never coerced.
	 *
	 * `(int) 'not-a-number'` is `0`, which the sub-one clamp would then turn
	 * into a valid interval of 1. That is a silent semantic change of the
	 * author's input, not a safe coercion, so the boundary must reject it.
	 *
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_from_array_rejects_non_numeric_interval_string(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'weekly',
				'interval'  => 'not-a-number',
				'weekdays'  => array( 2 ),
				'end_type'  => 'never',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * An arbitrary non-numeric weekday string is rejected, never coerced.
	 *
	 * `intval( 'not-a-weekday' )` is `0`, which silently turns an arbitrary
	 * word into Sunday. The whole rule is rejected rather than the element
	 * dropped, because dropping it would accept a different schedule than the
	 * one submitted.
	 *
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_from_array_rejects_non_numeric_weekday_string(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( 'not-a-weekday' ),
				'end_type'  => 'never',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * Booleans and floats in integer fields are rejected, never cast.
	 *
	 * `(int) true` is `1`, so a boolean interval or count would silently
	 * become a valid schedule. `(int) 2.5` is `2`, a different interval than
	 * the one submitted. Integer fields accept JSON integers only.
	 *
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_from_array_rejects_boolean_and_float_integer_fields(): void {
		$boolean_interval = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => true,
				'end_type'  => 'never',
			)
		);
		$boolean_count    = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => true,
			)
		);
		$float_interval   = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 2.5,
				'end_type'  => 'never',
			)
		);
		$boolean_weekday  = Rule::from_array(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( true ),
				'end_type'  => 'never',
			)
		);

		$this->assertNull( $boolean_interval, 'Failed to assert that a boolean interval is rejected.' );
		$this->assertNull( $boolean_count, 'Failed to assert that a boolean count is rejected.' );
		$this->assertNull( $float_interval, 'Failed to assert that a float interval is rejected.' );
		$this->assertNull( $boolean_weekday, 'Failed to assert that a boolean weekday is rejected.' );
	}

	/**
	 * Canonical integer strings remain accepted for integer fields.
	 *
	 * A form serialization or CLI client legitimately submits `"3"` where the
	 * editor submits `3`. Only a complete-match canonical integer string
	 * qualifies; `"007"` and `"2x"` do not.
	 *
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_from_array_accepts_canonical_integer_strings(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'weekly',
				'interval'  => '3',
				'weekdays'  => array( '2', '4' ),
				'end_type'  => 'count',
				'count'     => '5',
			)
		);

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assertSame( 3, $rule->interval() );
		$this->assertSame( array( 2, 4 ), $rule->weekdays() );
		$this->assertSame( 5, $rule->count() );

		$this->assertNull(
			Rule::from_array(
				array(
					'frequency' => 'daily',
					'interval'  => '007',
					'end_type'  => 'never',
				)
			),
			'Failed to assert that a non-canonical zero-padded integer string is rejected.'
		);
		$this->assertNull(
			Rule::from_array(
				array(
					'frequency' => 'daily',
					'interval'  => '2x',
					'end_type'  => 'never',
				)
			),
			'Failed to assert that a trailing-garbage integer string is rejected.'
		);
	}

	/**
	 * Direct coverage for `to_int()`'s three outcomes. It is called from
	 * loops inside `from_array()` in the same class, which xdebug does not
	 * trace into reliably.
	 *
	 * @covers ::to_int
	 *
	 * @return void
	 */
	public function test_to_int_covers_every_branch(): void {
		$to_int = new \ReflectionMethod( Rule::class, 'to_int' );
		$to_int->setAccessible( true );

		$this->assertSame( 3, $to_int->invoke( null, 3 ), 'Failed to assert that a real integer passes through.' );
		$this->assertSame(
			-1,
			$to_int->invoke( null, '-1' ),
			'Failed to assert that a canonical negative integer string is read.'
		);
		$this->assertSame(
			0,
			$to_int->invoke( null, '0' ),
			'Failed to assert that the canonical zero string is read.'
		);
		$this->assertNull( $to_int->invoke( null, '007' ), 'Failed to assert that a zero-padded string is rejected.' );
		$this->assertNull( $to_int->invoke( null, true ), 'Failed to assert that a boolean is rejected.' );
		$this->assertNull( $to_int->invoke( null, 2.5 ), 'Failed to assert that a float is rejected.' );
		$this->assertNull( $to_int->invoke( null, null ), 'Failed to assert that null is rejected.' );
	}

	/**
	 * A malformed nonempty `until` alongside a `count` is rejected, never
	 * silently resolved into a valid `COUNT` rule.
	 *
	 * The `UNTIL`/`COUNT` mutual exclusion has to run against the raw field's
	 * presence: erasing an unparsable `until` to null before the exclusion
	 * check would let a rule carrying both survive the very check the
	 * boundary exists to enforce.
	 *
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_from_array_rejects_malformed_until_alongside_count(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'until'     => 'not-a-date',
				'count'     => 5,
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * A malformed nonempty `until` on a `never` rule is rejected, never
	 * silently erased into a valid never-ending schedule.
	 *
	 * @covers ::from_array
	 *
	 * @return void
	 */
	public function test_from_array_rejects_malformed_until_on_a_never_rule(): void {
		$rule = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
				'until'     => 'not-a-date',
			)
		);

		$this->assertNull( $rule );
	}

	/**
	 * `is_valid()`'s `count`-arm `until` guard, unreachable through
	 * `from_array()` because its own pre-check already rejects any rule
	 * carrying both a count and an until, is exercised directly by
	 * constructing the object via reflection.
	 *
	 * @covers ::__construct
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_is_valid_rejects_count_end_type_carrying_until_when_built_directly(): void {
		$rule = $this->build_rule_directly(
			array(
				'daily',
				1,
				array(),
				'',
				0,
				0,
				0,
				'count',
				new DateTimeImmutable( '2026-01-01' ),
				5,
			)
		);

		$this->assertFalse( $rule->is_valid() );
	}

	/**
	 * `is_valid()`'s `until`-arm `count` guard, unreachable through
	 * `from_array()` for the same reason, is exercised directly by
	 * constructing the object via reflection.
	 *
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_is_valid_rejects_until_end_type_carrying_count_when_built_directly(): void {
		$rule = $this->build_rule_directly(
			array(
				'daily',
				1,
				array(),
				'',
				0,
				0,
				0,
				'until',
				new DateTimeImmutable( '2026-01-01' ),
				5,
			)
		);

		$this->assertFalse( $rule->is_valid() );
	}

	/**
	 * `to_array()` round-trips through `from_array()`.
	 *
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_to_array_round_trips_through_from_array(): void {
		$original = array(
			'frequency'       => 'monthly',
			'interval'        => 3,
			'weekdays'        => array(),
			'monthly_mode'    => 'nth_weekday',
			'monthly_day'     => 0,
			'monthly_ordinal' => 2,
			'monthly_weekday' => 5,
			'end_type'        => 'never',
			'until'           => '',
			'count'           => 0,
		);

		$rule = Rule::from_array( $original );

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assertSame( $original, $rule->to_array() );
	}

	/**
	 * `to_rrule_string()` matches a fixture table covering daily, weekly, and
	 * both monthly modes, each with a different end-of-series shape.
	 *
	 * @covers ::to_rrule_string
	 *
	 * @return void
	 */
	public function test_to_rrule_string_matches_fixture_table(): void {
		$fixtures = array(
			// Daily, count-bounded, interval 1, which is omitted at 1.
			array(
				'values'   => array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => 10,
				),
				'expected' => 'FREQ=DAILY;COUNT=10',
			),
			// Daily, until-bounded, interval 3.
			array(
				'values'   => array(
					'frequency' => 'daily',
					'interval'  => 3,
					'end_type'  => 'until',
					'until'     => '2026-12-31',
				),
				'expected' => 'FREQ=DAILY;INTERVAL=3;UNTIL=20270101T000000Z',
			),
			// Weekly, never-ending, multiple weekdays, interval 1.
			array(
				'values'   => array(
					'frequency' => 'weekly',
					'interval'  => 1,
					'weekdays'  => array( 1, 3, 5 ),
					'end_type'  => 'never',
				),
				'expected' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
			),
			// Weekly, count-bounded, biweekly, matching the reference rule in `Occurrence_Fixtures`.
			array(
				'values'   => array(
					'frequency' => 'weekly',
					'interval'  => 2,
					'weekdays'  => array( 2, 4 ),
					'end_type'  => 'count',
					'count'     => 5,
				),
				'expected' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=TU,TH;COUNT=5',
			),
			// Monthly day-of-month, until-bounded.
			array(
				'values'   => array(
					'frequency'    => 'monthly',
					'interval'     => 1,
					'monthly_mode' => 'day_of_month',
					'monthly_day'  => 15,
					'end_type'     => 'until',
					'until'        => '2027-06-15',
				),
				'expected' => 'FREQ=MONTHLY;BYMONTHDAY=15;UNTIL=20270615T230000Z',
			),
			// Monthly nth-weekday, "last", never-ending.
			array(
				'values'   => array(
					'frequency'       => 'monthly',
					'interval'        => 1,
					'monthly_mode'    => 'nth_weekday',
					'monthly_ordinal' => -1,
					'monthly_weekday' => 3,
					'end_type'        => 'never',
				),
				'expected' => 'FREQ=MONTHLY;BYDAY=-1WE',
			),
		);

		foreach ( $fixtures as $fixture ) {
			$rule = Rule::from_array( $fixture['values'] );

			$this->assertInstanceOf(
				Rule::class,
				$rule,
				'Fixture rule failed to build: ' . wp_json_encode( $fixture['values'] )
			);
			$this->assertSame(
				$fixture['expected'],
				$rule->to_rrule_string( self::rrule_anchor(), self::rrule_timezone() )
			);
		}
	}

	/**
	 * Direct `Utility::invoke_hidden_method()` coverage for every return path
	 * of `is_valid_monthly_shape()` and `is_valid_end_shape()`.
	 *
	 * `is_valid()` already exercises both helpers black-box through
	 * `from_array()` above, but xdebug does not reliably trace a private
	 * same-class helper invoked from a short delegation like `is_valid()`.
	 * See the "Extracted same-class helpers and xdebug coverage tracing" rule
	 * in AGENTS.md. Invoking each helper directly is the documented fix.
	 *
	 * @covers ::is_valid_monthly_shape
	 * @covers ::is_valid_end_shape
	 *
	 * @return void
	 */
	public function test_is_valid_helpers_direct_invoke_covers_every_branch(): void {
		$day_of_month_valid = $this->build_rule_directly(
			array( 'monthly', 1, array(), 'day_of_month', 15, 0, 0, 'never', null, 0 )
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $day_of_month_valid, 'is_valid_monthly_shape' )
		);

		$day_of_month_invalid = $this->build_rule_directly(
			array( 'monthly', 1, array(), 'day_of_month', 0, 0, 0, 'never', null, 0 )
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $day_of_month_invalid, 'is_valid_monthly_shape' )
		);

		$nth_weekday_valid = $this->build_rule_directly(
			array( 'monthly', 1, array(), 'nth_weekday', 0, 1, 3, 'never', null, 0 )
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $nth_weekday_valid, 'is_valid_monthly_shape' )
		);

		$nth_weekday_invalid = $this->build_rule_directly(
			array( 'monthly', 1, array(), 'nth_weekday', 0, 0, 3, 'never', null, 0 )
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $nth_weekday_invalid, 'is_valid_monthly_shape' )
		);

		$unrecognized_mode = $this->build_rule_directly(
			array( 'monthly', 1, array(), 'bogus', 0, 0, 0, 'never', null, 0 )
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $unrecognized_mode, 'is_valid_monthly_shape' )
		);

		$until_valid = $this->build_rule_directly(
			array( 'daily', 1, array(), '', 0, 0, 0, 'until', new DateTimeImmutable( '2026-01-01' ), 0 )
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $until_valid, 'is_valid_end_shape' )
		);

		$until_missing = $this->build_rule_directly(
			array( 'daily', 1, array(), '', 0, 0, 0, 'until', null, 0 )
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $until_missing, 'is_valid_end_shape' )
		);

		$count_valid = $this->build_rule_directly(
			array( 'daily', 1, array(), '', 0, 0, 0, 'count', null, 5 )
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $count_valid, 'is_valid_end_shape' )
		);

		$count_out_of_range = $this->build_rule_directly(
			array( 'daily', 1, array(), '', 0, 0, 0, 'count', null, 0 )
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $count_out_of_range, 'is_valid_end_shape' )
		);

		$count_over_budget = $this->build_rule_directly(
			array( 'monthly', 10, array(), '', 0, 0, 0, 'count', null, 730 )
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $count_over_budget, 'is_valid_end_shape' )
		);

		// A frequency outside FREQUENCY_* is unreachable through from_array(),
		// since is_valid()'s own top-level check rejects it before
		// is_valid_end_shape() ever runs. The per-frequency budget lookup still
		// carries a defensive `?? daily` fallback for it, so exercise that arm
		// directly.
		$count_with_unmapped_frequency = $this->build_rule_directly(
			array( 'bogus', 1, array(), '', 0, 0, 0, 'count', null, 5 )
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $count_with_unmapped_frequency, 'is_valid_end_shape' )
		);

		$never_valid = $this->build_rule_directly(
			array( 'daily', 1, array(), '', 0, 0, 0, 'never', null, 0 )
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $never_valid, 'is_valid_end_shape' )
		);

		$never_with_stray_until = $this->build_rule_directly(
			array( 'daily', 1, array(), '', 0, 0, 0, 'never', new DateTimeImmutable( '2026-01-01' ), 0 )
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $never_with_stray_until, 'is_valid_end_shape' )
		);

		$never_with_stray_count = $this->build_rule_directly(
			array( 'daily', 1, array(), '', 0, 0, 0, 'never', null, 3 )
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $never_with_stray_count, 'is_valid_end_shape' )
		);
	}

	/**
	 * Direct `Utility::invoke_hidden_method()` coverage for
	 * `until_as_utc_datetime()`, whose body xdebug does not trace through
	 * `to_rrule_string()`'s same-class delegation.
	 *
	 * The two cases differ only in which side of a daylight saving change the
	 * end date sits on, and they must produce different UTC clock times from
	 * the same wall clock. That is the whole reason the offset is resolved
	 * on the end date rather than on the anchor's.
	 *
	 * @covers ::until_as_utc_datetime
	 *
	 * @return void
	 */
	public function test_until_as_utc_datetime_direct_invoke_resolves_the_end_date_offset(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = new DateTimeImmutable( '2026-06-15 19:00:00', $timezone );
		$rule     = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'until',
				'until'     => '2026-06-30',
			)
		);

		$this->assertInstanceOf( Rule::class, $rule );
		$this->assertSame(
			'20260630T230000Z',
			Utility::invoke_hidden_method(
				$rule,
				'until_as_utc_datetime',
				array( new DateTimeImmutable( '2026-06-30' ), $anchor, $timezone )
			),
			'A summer end date takes the daylight saving offset, so 19:00 local is 23:00 UTC.'
		);
		$this->assertSame(
			'20261215T000000Z',
			Utility::invoke_hidden_method(
				$rule,
				'until_as_utc_datetime',
				array( new DateTimeImmutable( '2026-12-14' ), $anchor, $timezone )
			),
			'A winter end date takes the standard offset, so the same 19:00 local is midnight UTC the next day.'
		);
	}

	/**
	 * `until_as_utc_datetime()` normalizes an anchor that arrives in some other
	 * timezone before reading its wall clock.
	 *
	 * Every call site inside the plugin hands in an anchor already constructed
	 * in the series timezone, so the `setTimezone()` call is invisible to them:
	 * dropping it leaves the whole suite green. `to_rrule_string()` is public
	 * and takes an arbitrary `DateTimeImmutable`, though, and an anchor typed in
	 * UTC carries a different wall clock for the same instant, and that is the
	 * value `UNTIL` is built from. A UTC-typed anchor is therefore the fixture
	 * where "normalized" and "not normalized" give different answers.
	 *
	 * @covers ::until_as_utc_datetime
	 * @covers ::to_rrule_string
	 *
	 * @return void
	 */
	public function test_until_as_utc_datetime_normalizes_an_anchor_typed_in_another_zone(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$rule     = Rule::from_array(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'until',
				'until'     => '2026-11-04',
			)
		);

		$this->assertInstanceOf( Rule::class, $rule );

		// One instant, typed two ways. 08:30 in New York on 2026-07-04 is 12:30
		// UTC, daylight saving being in force; the end date sits after the
		// autumn transition, so its offset is an hour further out. Both halves
		// matter: an end date on the anchor's own date would make the two
		// readings agree whatever the code did, and an end date in the same
		// offset would too.
		$in_zone = new DateTimeImmutable( '2026-07-04 08:30:00', $timezone );
		$in_utc  = $in_zone->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertSame(
			'12:30',
			$in_utc->format( 'H:i' ),
			'The fixture only discriminates while the two typings disagree about the wall clock.'
		);
		$this->assertSame(
			'20261104T133000Z',
			Utility::invoke_hidden_method(
				$rule,
				'until_as_utc_datetime',
				array( new DateTimeImmutable( '2026-11-04' ), $in_zone, $timezone )
			),
			'An anchor already in the series timezone keeps its 08:30 wall clock into the end date.'
		);
		$this->assertSame(
			'20261104T133000Z',
			Utility::invoke_hidden_method(
				$rule,
				'until_as_utc_datetime',
				array( new DateTimeImmutable( '2026-11-04' ), $in_utc, $timezone )
			),
			'The same instant typed in UTC must resolve to the same UNTIL, not to its 12:30 UTC wall clock.'
		);
	}
}
