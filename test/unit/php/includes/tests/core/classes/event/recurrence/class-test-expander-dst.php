<?php
/**
 * Class handles daylight saving unit tests for GatherPress\Core\Event\Recurrence\Expander.
 *
 * Every case runs in `America/New_York`, whose 2026 transitions are 2026-03-08
 * (spring forward, 02:00 to 03:00) and 2026-11-01 (fall back, 02:00 to 01:00).
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
 * Class Test_Expander_Dst.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Expander
 */
class Test_Expander_Dst extends Base {

	/**
	 * Build a rule, failing the test when the fixture values do not produce one.
	 *
	 * This used to markTestSkipped() while `Rule::from_array()` was an
	 * unimplemented skeleton. The implementation ships in this same PR, so a
	 * null here now means `Rule::from_array()` rejected fixture values it must
	 * accept. Skipping would report that regression as five invisible green
	 * dots with exit 0; asserting makes it a red.
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
			'Failed to assert the fixture rule values build a Rule.'
		);

		return $rule;
	}

	/**
	 * Reduce an expansion to a list of local and UTC datetimes.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable[] $occurrences Expanded occurrences.
	 *
	 * @return string[] One `local | utc` string per occurrence.
	 */
	protected function to_local_and_utc( array $occurrences ): array {
		return array_map(
			static function ( DateTimeImmutable $occurrence ): string {
				return sprintf(
					'%s %s | %s',
					$occurrence->format( 'Y-m-d H:i:s' ),
					$occurrence->format( 'P' ),
					$occurrence->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
				);
			},
			$occurrences
		);
	}

	/**
	 * A weekly 18:00 series keeps its wall clock across the November transition.
	 *
	 * The UTC values step from 22:00 to 23:00 while the local time never moves,
	 * which is what proves the time is applied in the series timezone rather
	 * than carried as an offset.
	 *
	 * @covers ::expand
	 * @covers ::materialize
	 *
	 * @return void
	 */
	public function test_weekly_eighteen_hundred_preserves_wall_clock_across_november(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency' => Rule::FREQUENCY_WEEKLY,
					'interval'  => 1,
					'weekdays'  => array( 0 ),
					'end_type'  => Rule::END_TYPE_COUNT,
					'count'     => 4,
				)
			),
			new DateTimeImmutable( '2026-10-18 18:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2027-10-18 18:00:00', $timezone )
		);

		$this->assertSame(
			array(
				'2026-10-18 18:00:00 -04:00 | 2026-10-18 22:00:00',
				'2026-10-25 18:00:00 -04:00 | 2026-10-25 22:00:00',
				'2026-11-01 18:00:00 -05:00 | 2026-11-01 23:00:00',
				'2026-11-08 18:00:00 -05:00 | 2026-11-08 23:00:00',
			),
			$this->to_local_and_utc( $occurrences ),
			'Failed to assert that the wall clock holds at 18:00 while the UTC value shifts by an hour.'
		);
	}

	/**
	 * A daily 02:30 series skips the spring-forward date entirely.
	 *
	 * RFC 5545 section 3.3.10: the local time 2026-03-08 02:30
	 * does not exist, so it is not an occurrence. PHP would otherwise normalize
	 * it forward to 03:30.
	 *
	 * @covers ::expand
	 * @covers ::materialize
	 *
	 * @return void
	 */
	public function test_daily_zero_two_thirty_skips_spring_forward_date(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency' => Rule::FREQUENCY_DAILY,
					'interval'  => 1,
					'end_type'  => Rule::END_TYPE_UNTIL,
					'until'     => '2026-03-09',
				)
			),
			new DateTimeImmutable( '2026-03-07 02:30:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2026-03-31 02:30:00', $timezone )
		);

		$this->assertSame(
			array(
				'2026-03-07 02:30:00 -05:00 | 2026-03-07 07:30:00',
				'2026-03-09 02:30:00 -04:00 | 2026-03-09 06:30:00',
			),
			$this->to_local_and_utc( $occurrences ),
			'Failed to assert that the nonexistent 2026-03-08 02:30 local time is skipped.'
		);
	}

	/**
	 * A skipped spring-forward candidate does not consume the `COUNT` budget.
	 *
	 * The count decrements on appending a result, never on
	 * consuming a candidate, so the series still delivers three occurrences.
	 *
	 * @covers ::expand
	 *
	 * @return void
	 */
	public function test_count_not_consumed_by_skipped_spring_forward_candidate(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency' => Rule::FREQUENCY_DAILY,
					'interval'  => 1,
					'end_type'  => Rule::END_TYPE_COUNT,
					'count'     => 3,
				)
			),
			new DateTimeImmutable( '2026-03-07 02:30:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2026-03-31 02:30:00', $timezone )
		);

		$this->assertCount(
			3,
			$occurrences,
			'Failed to assert that a skipped candidate leaves the COUNT budget intact.'
		);
		$this->assertSame(
			array(
				'2026-03-07 02:30:00 -05:00 | 2026-03-07 07:30:00',
				'2026-03-09 02:30:00 -04:00 | 2026-03-09 06:30:00',
				'2026-03-10 02:30:00 -04:00 | 2026-03-10 06:30:00',
			),
			$this->to_local_and_utc( $occurrences ),
			'Failed to assert that the series runs a day later to deliver its third occurrence.'
		);
	}

	/**
	 * A calendar date the zone never had is skipped, and does not consume the count.
	 *
	 * Samoa moved west of the date line at the end of 2011, so 2011-12-30 never
	 * existed in `Pacific/Apia`. PHP normalizes 09:00 that day to 09:00 on the
	 * 31st. The clock time survives intact, so a round trip on the time alone
	 * accepts it, emits a duplicate of the following day, and spends a `COUNT`
	 * slot doing it. The round trip is on the whole local datetime for that
	 * reason.
	 *
	 * @covers ::expand
	 * @covers ::materialize
	 *
	 * @return void
	 */
	public function test_daily_skips_a_calendar_date_the_zone_never_had(): void {
		$timezone = new DateTimeZone( 'Pacific/Apia' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency' => Rule::FREQUENCY_DAILY,
					'interval'  => 1,
					'end_type'  => Rule::END_TYPE_COUNT,
					'count'     => 3,
				)
			),
			new DateTimeImmutable( '2011-12-29 09:00:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2012-01-31 09:00:00', $timezone )
		);

		$this->assertCount(
			3,
			$occurrences,
			'Failed to assert that a skipped calendar date leaves the COUNT budget intact.'
		);
		$this->assertSame(
			array(
				'2011-12-29 09:00:00 -10:00 | 2011-12-29 19:00:00',
				'2011-12-31 09:00:00 +14:00 | 2011-12-30 19:00:00',
				'2012-01-01 09:00:00 +14:00 | 2011-12-31 19:00:00',
			),
			$this->to_local_and_utc( $occurrences ),
			'Failed to assert that 2011-12-30 is absent and is not duplicated by the 31st.'
		);
	}

	/**
	 * An ambiguous fall-back local time pins to the earlier, still-daylight instant.
	 *
	 * RFC 5545 says nothing about ambiguous local times, so this records the
	 * behavior PHP gives rather than asserting a theory: 2026-11-01 01:30 occurs
	 * twice in `America/New_York`, and `DateTimeImmutable::createFromFormat()`
	 * resolves it to the first instance, at -04:00.
	 *
	 * @covers ::expand
	 * @covers ::materialize
	 *
	 * @return void
	 */
	public function test_expand_pins_fall_back_ambiguous_time(): void {
		$timezone = new DateTimeZone( 'America/New_York' );

		$occurrences = ( new Expander() )->expand(
			$this->make_rule(
				array(
					'frequency' => Rule::FREQUENCY_DAILY,
					'interval'  => 1,
					'end_type'  => Rule::END_TYPE_COUNT,
					'count'     => 3,
				)
			),
			new DateTimeImmutable( '2026-10-31 01:30:00', $timezone ),
			$timezone,
			new DateTimeImmutable( '2026-11-30 01:30:00', $timezone )
		);

		$this->assertSame(
			array(
				'2026-10-31 01:30:00 -04:00 | 2026-10-31 05:30:00',
				'2026-11-01 01:30:00 -04:00 | 2026-11-01 05:30:00',
				'2026-11-02 01:30:00 -05:00 | 2026-11-02 06:30:00',
			),
			$this->to_local_and_utc( $occurrences ),
			'Failed to assert that the ambiguous 2026-11-01 01:30 local time pins to the earlier instant.'
		);
	}
}
