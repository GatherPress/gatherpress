<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Rule.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event\Occurrence;
use GatherPress\Core\Event\Rule;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rule.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Rule
 */
class Test_Rule extends Base {

	/**
	 * A valid rule row, with fields replaced by the ones given.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $changes Fields to replace or add.
	 *
	 * @return array<string, mixed> The row.
	 */
	protected function row( array $changes = array() ): array {
		return array_merge(
			array(
				'change_id' => 'change-1',
				'starts_on' => '2026-12-11',
				'rrule'     => 'FREQ=MONTHLY;BYDAY=2FR',
				'times'     => array( '20:00' ),
				'timezone'  => 'America/New_York',
				'duration'  => 120,
			),
			$changes
		);
	}

	/**
	 * A rule from a row, failing the test if the row is invalid.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $changes Fields to replace or add.
	 *
	 * @return Rule The rule.
	 */
	protected function rule( array $changes = array() ): Rule {
		$rule = Rule::from_meta( $this->row( $changes ) );

		$this->assertInstanceOf( Rule::class, $rule, 'The row should be a valid rule.' );

		return $rule;
	}

	/**
	 * A moment in UTC.
	 *
	 * @since TBD
	 *
	 * @param string $datetime The moment.
	 *
	 * @return DateTimeImmutable The moment.
	 */
	protected function utc( string $datetime ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The keys of the occurrences a rule produces in a range.
	 *
	 * @since TBD
	 *
	 * @param Rule   $rule The rule.
	 * @param string $from Start of the range, in UTC.
	 * @param string $to   End of the range, in UTC.
	 *
	 * @return string[] The keys.
	 */
	protected function keys( Rule $rule, string $from, string $to ): array {
		return array_map(
			static fn( Occurrence $occurrence ): string => $occurrence->recurrence_id,
			$rule->occurrences( $this->utc( $from ), $this->utc( $to ), 7, 'scheduled' )
		);
	}

	/**
	 * A valid row becomes a rule with its fields, defaults and canonical pattern.
	 *
	 * @since TBD
	 *
	 * @covers ::from_meta
	 * @covers ::__construct
	 * @covers ::rrule
	 * @covers ::weekday
	 * @covers ::parse_row
	 * @covers ::with_defaults
	 *
	 * @return void
	 */
	public function test_from_meta_reads_a_valid_row(): void {
		$rule = $this->rule( array( 'times' => array( '20:00', '18:30', '20:00' ) ) );

		$this->assertSame( '2026-12-11', $rule->starts_on->format( 'Y-m-d' ) );
		$this->assertNull( $rule->ends_on );
		$this->assertSame( array( '18:30', '20:00' ), $rule->times, 'Times are unique and in order.' );
		$this->assertSame( 'America/New_York', $rule->timezone->getName() );
		$this->assertSame( 'change-1', $rule->change_id );
		$this->assertSame( 'FREQ=MONTHLY;BYDAY=2FR', $rule->rrule() );

		$this->assertSame(
			'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,FR',
			$this->rule( array( 'rrule' => 'freq=weekly;byday=FR,MO;interval=2' ) )->rrule(),
			'The canonical form has a fixed order and upper case.'
		);
		$this->assertSame(
			'FREQ=WEEKLY;BYDAY=FR',
			$this->rule( array( 'rrule' => 'FREQ=WEEKLY' ) )->rrule(),
			'A weekly pattern defaults to the start date weekday.'
		);
		$this->assertSame(
			'FREQ=MONTHLY;BYMONTHDAY=11',
			$this->rule( array( 'rrule' => 'FREQ=MONTHLY' ) )->rrule(),
			'A monthly pattern defaults to the start date day of the month.'
		);
		$this->assertSame( 'FREQ=YEARLY', $this->rule( array( 'rrule' => 'FREQ=YEARLY' ) )->rrule() );
		$this->assertNull( $this->rule( array( 'rrule' => null ) )->rrule(), 'No pattern is one date.' );
		$this->assertSame( '', $this->rule( array( 'change_id' => 5 ) )->change_id );
		$this->assertSame(
			'+05:30',
			$this->rule( array( 'timezone' => 'UTC+5.5' ) )->timezone->getName(),
			'The timezone is normalized.'
		);
	}

	/**
	 * Rows that aren't valid rules are refused.
	 *
	 * @since TBD
	 *
	 * @covers ::from_meta
	 * @covers ::parse_date
	 * @covers ::parse_times
	 * @covers ::can_repeat
	 * @covers ::next_pattern_date
	 * @covers ::parse_row
	 * @covers ::is_valid_length
	 *
	 * @return void
	 */
	public function test_from_meta_refuses_invalid_rows(): void {
		$cases = array(
			'not an array'          => 'nope',
			'no start'              => $this->row( array( 'starts_on' => null ) ),
			'start not a date'      => $this->row( array( 'starts_on' => '12/11/2026' ) ),
			'start overflows'       => $this->row( array( 'starts_on' => '2026-02-31' ) ),
			'end not a date'        => $this->row( array( 'ends_on' => 'soon' ) ),
			'end before start'      => $this->row( array( 'ends_on' => '2026-12-10' ) ),
			'bad timezone'          => $this->row( array( 'timezone' => 'Mars/Olympus' ) ),
			'timezone not a string' => $this->row( array( 'timezone' => array() ) ),
			'bad pattern'           => $this->row( array( 'rrule' => 'FREQ=HOURLY' ) ),
			'times not an array'    => $this->row( array( 'times' => '20:00' ) ),
			'bad time'              => $this->row( array( 'times' => array( '25:00' ) ) ),
			'time not a string'     => $this->row( array( 'times' => array( 2000 ) ) ),
			'no times, not all day' => $this->row( array( 'times' => array() ) ),
			'negative duration'     => $this->row( array( 'duration' => -5 ) ),
			'no duration'           => $this->row( array( 'duration' => null ) ),
			'all day, part days'    => $this->row(
				array(
					'is_all_day' => true,
					'duration'   => 1500,
				)
			),
			'all day, under a day'  => $this->row(
				array(
					'is_all_day' => true,
					'duration'   => 60,
				)
			),
			'lasts over a year'     => $this->row( array( 'duration' => Rule::MAX_DURATION + 1 ) ),
			'pattern never repeats' => $this->row(
				array(
					'starts_on' => '2026-02-10',
					'rrule'     => 'FREQ=MONTHLY;INTERVAL=12;BYMONTHDAY=30',
				)
			),
		);

		foreach ( $cases as $label => $row ) {
			$this->assertNull( Rule::from_meta( $row ), sprintf( 'Expected "%s" to be refused.', $label ) );
		}
	}

	/**
	 * Only the supported RRULE subset parses.
	 *
	 * @since TBD
	 *
	 * @covers \GatherPress\Core\Event\Rrule::parse
	 * @covers \GatherPress\Core\Event\Rrule::by_parts
	 *
	 * @return void
	 */
	public function test_rrule_subset(): void {
		$valid = array(
			''                           => null,
			'FREQ=DAILY'                 => 'FREQ=DAILY',
			'FREQ=DAILY;INTERVAL=3'      => 'FREQ=DAILY;INTERVAL=3',
			'FREQ=WEEKLY;BYDAY=TU,TH'    => 'FREQ=WEEKLY;BYDAY=TU,TH',
			'FREQ=MONTHLY;BYDAY=-1TU'    => 'FREQ=MONTHLY;BYDAY=-1TU',
			'FREQ=MONTHLY;BYMONTHDAY=31' => 'FREQ=MONTHLY;BYMONTHDAY=31',
			'FREQ=YEARLY;INTERVAL=1'     => 'FREQ=YEARLY',
		);

		foreach ( $valid as $rrule => $canonical ) {
			$this->assertSame( $canonical, $this->rule( array( 'rrule' => $rrule ) )->rrule(), $rrule );
		}

		$invalid = array(
			array( 'not a string' ),
			'FREQ=DAILY;FREQ=WEEKLY',
			'FREQ=DAILY;COUNT=5',
			'FREQ=DAILY;UNTIL=20270101T000000Z',
			'INTERVAL=2',
			'FREQ=WEEKLY;INTERVAL=x',
			'FREQ=WEEKLY;INTERVAL=0',
			'FREQ=YEARLY;INTERVAL=1001',
			'FREQ=YEARLY;INTERVAL=99999999999999999999',
			'FREQ=WEEKLY;BYDAY=XX',
			'FREQ=WEEKLY;BYDAY=MO,MO',
			'FREQ=WEEKLY;BYMONTHDAY=3',
			'FREQ=MONTHLY;BYDAY=5FR',
			'FREQ=MONTHLY;BYDAY=FR',
			'FREQ=MONTHLY;BYMONTHDAY=0',
			'FREQ=MONTHLY;BYMONTHDAY=32',
			'FREQ=MONTHLY;BYMONTHDAY=x',
			'FREQ=MONTHLY;BYDAY=1FR;BYMONTHDAY=3',
			'FREQ=DAILY;BYDAY=MO',
			'FREQ=YEARLY;BYMONTHDAY=3',
		);

		foreach ( $invalid as $rrule ) {
			$this->assertNull(
				Rule::from_meta( $this->row( array( 'rrule' => $rrule ) ) ),
				sprintf( 'Expected "%s" to be refused.', is_string( $rrule ) ? $rrule : gettype( $rrule ) )
			);
		}
	}

	/**
	 * Each frequency expands to the right dates, with the start date always counted.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences
	 * @covers ::occurrences_on
	 * @covers ::dates_from
	 * @covers ::next_date
	 * @covers ::next_pattern_date
	 * @covers ::period_of
	 * @covers ::period_limit
	 * @covers ::dates_in_period
	 * @covers ::ordinal_weekday_in
	 * @covers ::local_date
	 * @covers ::utc_date
	 * @covers ::monday
	 * @covers ::weekday
	 * @covers ::span_days
	 *
	 * @return void
	 */
	public function test_occurrences_per_frequency(): void {
		$this->assertSame(
			array( '20261211T200000', '20270108T200000', '20270212T200000', '20270312T200000' ),
			$this->keys( $this->rule(), '2026-12-01', '2027-04-01' ),
			'The second Friday of each month.'
		);
		$this->assertSame(
			array( '20261229T200000', '20270126T200000', '20270223T200000' ),
			$this->keys(
				$this->rule(
					array(
						'starts_on' => '2026-12-29',
						'rrule'     => 'FREQ=MONTHLY;BYDAY=-1TU',
					)
				),
				'2026-12-01',
				'2027-03-01'
			),
			'The last Tuesday of each month.'
		);
		$this->assertSame(
			array( '20270131T200000', '20270331T200000', '20270531T200000' ),
			$this->keys(
				$this->rule(
					array(
						'starts_on' => '2027-01-31',
						'rrule'     => 'FREQ=MONTHLY',
					)
				),
				'2027-01-01',
				'2027-07-01'
			),
			'Months without the 31st are skipped.'
		);
		$this->assertSame(
			array( '20261214T200000', '20261216T200000', '20261228T200000', '20261230T200000' ),
			$this->keys(
				$this->rule(
					array(
						'starts_on' => '2026-12-14',
						'rrule'     => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE',
					)
				),
				'2026-12-01',
				'2027-01-01'
			),
			'Every other week on Monday and Wednesday.'
		);
		$this->assertSame(
			array( '20261211T200000', '20261214T200000', '20261217T200000' ),
			$this->keys( $this->rule( array( 'rrule' => 'FREQ=DAILY;INTERVAL=3' ) ), '2026-12-01', '2026-12-19' ),
			'Every third day.'
		);
		$this->assertSame(
			array( '20280229T200000', '20320229T200000' ),
			$this->keys(
				$this->rule(
					array(
						'starts_on' => '2028-02-29',
						'rrule'     => 'FREQ=YEARLY',
					)
				),
				'2028-01-01',
				'2033-01-01'
			),
			'February 29th only falls in leap years.'
		);
		$this->assertSame(
			array( '20261210T200000', '20261211T200000' ),
			$this->keys(
				$this->rule(
					array(
						'starts_on' => '2026-12-10',
						'rrule'     => 'FREQ=WEEKLY;BYDAY=FR',
						'ends_on'   => '2026-12-11',
					)
				),
				'2026-12-01',
				'2027-01-01'
			),
			'The start date counts even when the pattern would not pick it, and the end date is the last.'
		);
		$this->assertSame(
			array( '20261211T200000' ),
			$this->keys( $this->rule( array( 'rrule' => null ) ), '2026-12-01', '2027-12-01' ),
			'A rule without a pattern is one date.'
		);
	}

	/**
	 * Occurrences carry their times, duration and status, and a range keeps only overlapping ones.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences
	 * @covers ::occurrences_on
	 *
	 * @return void
	 */
	public function test_occurrence_values_and_range_edges(): void {
		$rule        = $this->rule( array( 'times' => array( '18:00', '21:00' ) ) );
		$occurrences = $rule->occurrences( $this->utc( '2026-12-11' ), $this->utc( '2026-12-13' ), 7, 'canceled' );

		$this->assertCount( 2, $occurrences );
		$this->assertSame( 7, $occurrences[0]->series_post_id );
		$this->assertSame( '2026-12-11 18:00', $occurrences[0]->start->format( 'Y-m-d H:i' ) );
		$this->assertSame( '2026-12-11 20:00', $occurrences[0]->end->format( 'Y-m-d H:i' ) );
		$this->assertSame( 'America/New_York', $occurrences[0]->start->getTimezone()->getName() );
		$this->assertTrue( $occurrences[1]->is_canceled() );

		// The 21:00 showing runs to 23:00 New York, 04:00 UTC the next day.
		$this->assertSame(
			array( '20261211T210000' ),
			$this->keys( $rule, '2026-12-12 03:00', '2026-12-12 05:00' ),
			'An occurrence that started before the range but is still running is included.'
		);
		$this->assertSame(
			array(),
			$this->keys( $rule, '2026-12-11 12:00', '2026-12-11 23:00' ),
			'A range that ends as the first showing starts is empty.'
		);
	}

	/**
	 * All-day rules span whole local days and use a date key.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences_on
	 * @covers ::span_days
	 *
	 * @return void
	 */
	public function test_all_day_occurrences(): void {
		$rule = $this->rule(
			array(
				'starts_on'  => '2026-03-28',
				'rrule'      => 'FREQ=YEARLY',
				'is_all_day' => true,
				'times'      => array( '09:00' ),
				'timezone'   => 'Europe/Berlin',
				'duration'   => 2880,
			)
		);

		$this->assertSame( array(), $rule->times, 'An all-day rule has no times.' );

		$occurrences = $rule->occurrences( $this->utc( '2026-03-01' ), $this->utc( '2026-04-01' ), 7, 'scheduled' );

		$this->assertCount( 1, $occurrences );
		$this->assertSame( '20260328', $occurrences[0]->recurrence_id );
		$this->assertTrue( $occurrences[0]->is_all_day );
		$this->assertSame( '2026-03-28 00:00:00 CET', $occurrences[0]->start->format( 'Y-m-d H:i:s T' ) );
		// Two calendar days across the spring-forward night end at 23:59:59 local.
		$this->assertSame( '2026-03-29 23:59:59 CEST', $occurrences[0]->end->format( 'Y-m-d H:i:s T' ) );
	}

	/**
	 * A time in a spring-forward gap moves after it, keyed by the shifted start, and later dates don't drift.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences_on
	 * @covers ::find
	 *
	 * @return void
	 */
	public function test_daylight_saving_gap(): void {
		$rule = $this->rule(
			array(
				'starts_on' => '2026-03-01',
				'rrule'     => 'FREQ=DAILY',
				'times'     => array( '02:30' ),
				'duration'  => 60,
			)
		);

		$starts = array_map(
			static fn( Occurrence $o ): string => $o->recurrence_id . ' ' . $o->start->format( 'H:i T' ),
			$rule->occurrences( $this->utc( '2026-03-07 12:00' ), $this->utc( '2026-03-09 12:00' ), 7, 'scheduled' )
		);

		$this->assertSame( array( '20260308T033000 03:30 EDT', '20260309T023000 02:30 EDT' ), $starts );
		$this->assertSame(
			'2026-03-08 03:30',
			$rule->find( '20260308T033000', 7, 'scheduled' )->start->format( 'Y-m-d H:i' ),
			'The shifted key finds the shifted date.'
		);
	}

	/**
	 * Find returns the occurrence a key names, or null.
	 *
	 * @since TBD
	 *
	 * @covers ::find
	 *
	 * @return void
	 */
	public function test_find(): void {
		$rule = $this->rule( array( 'times' => array( '18:00', '20:00' ) ) );

		$this->assertSame( '20270108T200000', $rule->find( '20270108T200000', 7, 'scheduled' )->recurrence_id );
		$this->assertNull( $rule->find( '20270109T200000', 7, 'scheduled' ), 'A date the rule does not produce.' );
		$this->assertNull( $rule->find( '20270108T190000', 7, 'scheduled' ), 'A time the rule does not produce.' );
		$this->assertNull( $rule->find( 'nope', 7, 'scheduled' ), 'Not a key.' );
	}

	/**
	 * The next occurrence is the first that hasn't ended, and null once the rule is over.
	 *
	 * @since TBD
	 *
	 * @covers ::next_occurrence
	 *
	 * @return void
	 */
	public function test_next_occurrence(): void {
		$rule = $this->rule( array( 'ends_on' => '2027-02-12' ) );

		$this->assertSame(
			'20270108T200000',
			$rule->next_occurrence( $this->utc( '2026-12-20' ), 7, 'scheduled' )->recurrence_id
		);
		$this->assertSame(
			'20270108T200000',
			$rule->next_occurrence( $this->utc( '2027-01-09 02:00' ), 7, 'scheduled' )->recurrence_id,
			'A date still running has not ended.'
		);
		$this->assertNull(
			$rule->next_occurrence( $this->utc( '2027-03-01' ), 7, 'scheduled' ),
			'Every date has ended.'
		);
	}

	/**
	 * The previous occurrence is the last that ended, and null before the first.
	 *
	 * @since TBD
	 *
	 * @covers ::previous_occurrence
	 * @covers ::dates_before
	 * @covers ::previous_date
	 * @covers ::previous_pattern_date
	 *
	 * @return void
	 */
	public function test_previous_occurrence(): void {
		$rule = $this->rule( array( 'ends_on' => '2027-02-12' ) );

		$this->assertSame(
			'20270212T200000',
			$rule->previous_occurrence( $this->utc( '2027-06-01' ), 7, 'scheduled' )->recurrence_id,
			'After the end, the last date.'
		);
		$this->assertSame(
			'20261211T200000',
			$rule->previous_occurrence( $this->utc( '2027-01-08 12:00' ), 7, 'scheduled' )->recurrence_id
		);
		$this->assertNull(
			$rule->previous_occurrence( $this->utc( '2026-12-01' ), 7, 'scheduled' ),
			'Nothing has ended yet.'
		);
	}

	/**
	 * Date lookups respect the start and end dates.
	 *
	 * @since TBD
	 *
	 * @covers ::next_date
	 * @covers ::previous_date
	 * @covers ::next_pattern_date
	 * @covers ::previous_pattern_date
	 *
	 * @return void
	 */
	public function test_next_and_previous_date(): void {
		$weekly = $this->rule(
			array(
				'starts_on' => '2026-12-14',
				'rrule'     => 'FREQ=WEEKLY;BYDAY=WE',
			)
		);
		$single = $this->rule( array( 'rrule' => null ) );

		$this->assertSame( '2026-12-14', $weekly->next_date( $this->utc( '2026-12-01' ) )->format( 'Y-m-d' ) );
		$this->assertSame( '2026-12-16', $weekly->next_date( $this->utc( '2026-12-15' ) )->format( 'Y-m-d' ) );
		$this->assertNull( $single->next_date( $this->utc( '2026-12-12' ) ), 'A single date has nothing after it.' );

		$this->assertSame(
			'2026-12-14',
			$weekly->previous_date( $this->utc( '2026-12-15' ) )->format( 'Y-m-d' ),
			'Before the first pattern date, the start date.'
		);
		$this->assertSame( '2026-12-23', $weekly->previous_date( $this->utc( '2026-12-27' ) )->format( 'Y-m-d' ) );
		$this->assertNull( $weekly->previous_date( $this->utc( '2026-12-13' ) ), 'Before the start date.' );
		$this->assertSame( '2026-12-11', $single->previous_date( $this->utc( '2027-06-01' ) )->format( 'Y-m-d' ) );

		$ended = $this->rule(
			array(
				'starts_on' => '2026-12-14',
				'ends_on'   => '2026-12-20',
				'rrule'     => 'FREQ=WEEKLY;BYDAY=WE',
			)
		);

		$this->assertSame(
			'2026-12-16',
			$ended->previous_date( $this->utc( '2027-01-01' ) )->format( 'Y-m-d' ),
			'After the end date, the last date before it.'
		);
	}

	/**
	 * Weeks start on Monday, so a mid-week start counts its own week as the first.
	 *
	 * @since TBD
	 *
	 * @covers ::period_of
	 * @covers ::dates_in_period
	 *
	 * @return void
	 */
	public function test_weeks_start_on_monday(): void {
		$rule = $this->rule(
			array(
				'starts_on' => '2026-12-16',
				'rrule'     => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE,SU',
			)
		);

		// Wed Dec 16 is in the week of Mon Dec 14, which ends on Sun Dec 20, so
		// Dec 20 counts. A Sunday week start would put Dec 20 in the skipped week.
		$this->assertSame(
			array( '20261216T200000', '20261220T200000', '20261228T200000', '20261230T200000', '20270103T200000' ),
			$this->keys( $rule, '2026-12-01', '2027-01-05' )
		);
	}

	/**
	 * On a fall-back day, an ambiguous time uses its first instance and a duration counts elapsed time.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences_on
	 *
	 * @return void
	 */
	public function test_daylight_saving_fall_back(): void {
		$ambiguous = $this->rule(
			array(
				'starts_on' => '2026-11-01',
				'rrule'     => null,
				'times'     => array( '01:30' ),
				'duration'  => 30,
			)
		);
		$across    = $this->rule(
			array(
				'starts_on' => '2026-11-01',
				'rrule'     => null,
				'times'     => array( '00:30' ),
				'duration'  => 120,
			)
		);

		$first = $ambiguous->occurrences( $this->utc( '2026-10-31' ), $this->utc( '2026-11-02' ), 7, 'scheduled' )[0];
		$long  = $across->occurrences( $this->utc( '2026-10-31' ), $this->utc( '2026-11-02' ), 7, 'scheduled' )[0];

		$this->assertSame( '01:30 EDT', $first->start->format( 'H:i T' ), 'The first of the repeated hour.' );
		$this->assertSame( '01:00 EST', $first->end->format( 'H:i T' ), 'Thirty elapsed minutes later.' );
		$this->assertSame( '01:30 EST', $long->end->format( 'H:i T' ), 'Two elapsed hours, not wall-clock hours.' );
		$this->assertSame( 7200, $long->end->getTimestamp() - $long->start->getTimestamp() );
	}

	/**
	 * Two showtimes in a spring-forward gap land on one instant with one key.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences_on
	 *
	 * @return void
	 */
	public function test_spring_forward_gap_collision(): void {
		$rule = $this->rule(
			array(
				'starts_on' => '2026-03-08',
				'rrule'     => null,
				'times'     => array( '02:30', '03:30' ),
				'duration'  => 60,
			)
		);

		$this->assertSame(
			array( '20260308T033000' ),
			$this->keys( $rule, '2026-03-08', '2026-03-09' ),
			'The rule reports the shared instant once.'
		);
	}

	/**
	 * One expansion stops at MAX_DATES.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences
	 * @covers ::dates_from
	 *
	 * @return void
	 */
	public function test_occurrences_stop_at_max_dates(): void {
		$rule = $this->rule(
			array(
				'starts_on' => '2026-01-01',
				'rrule'     => 'FREQ=DAILY',
			)
		);

		$keys = $this->keys( $rule, '2026-01-01', '2030-01-01' );

		$this->assertCount( Rule::MAX_DATES, $keys );
		$this->assertSame( '20260101T200000', $keys[0], 'The earliest dates are kept.' );
		$this->assertSame( '20280926T200000', end( $keys ), 'The thousandth date, 999 days in.' );
	}

	/**
	 * Find handles all-day keys, the start date, and dates past the end.
	 *
	 * @since TBD
	 *
	 * @covers ::find
	 *
	 * @return void
	 */
	public function test_find_edges(): void {
		$all_day = $this->rule(
			array(
				'is_all_day' => true,
				'duration'   => 1440,
			)
		);
		$weekly  = $this->rule(
			array(
				'starts_on' => '2026-12-10',
				'ends_on'   => '2026-12-31',
				'rrule'     => 'FREQ=WEEKLY;BYDAY=FR',
			)
		);

		$this->assertSame( '20270108', $all_day->find( '20270108', 7, 'scheduled' )->recurrence_id );
		$this->assertNull( $all_day->find( '20270108T200000', 7, 'scheduled' ), 'A timed key on an all-day rule.' );
		$this->assertSame(
			'20261210T200000',
			$weekly->find( '20261210T200000', 7, 'scheduled' )->recurrence_id,
			'The start date, though the pattern would not pick a Thursday.'
		);
		$this->assertNull( $weekly->find( '20270101T200000', 7, 'scheduled' ), 'Past the end date.' );
	}

	/**
	 * Ranges wholly before the start or after the end are empty.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences
	 *
	 * @return void
	 */
	public function test_ranges_outside_the_rule(): void {
		$rule = $this->rule( array( 'ends_on' => '2027-02-12' ) );

		$this->assertSame( array(), $this->keys( $rule, '2026-01-01', '2026-12-01' ) );
		$this->assertSame( array(), $this->keys( $rule, '2027-03-01', '2028-01-01' ) );
	}

	/**
	 * Stored values read the way REST reads them, and a bad date or duration makes the row invalid without throwing.
	 *
	 * @since TBD
	 *
	 * @covers ::from_meta
	 * @covers ::parse_row
	 * @covers ::parse_date
	 *
	 * @return void
	 */
	public function test_stored_value_types(): void {
		$timed = $this->rule(
			array(
				'is_all_day' => 'false',
				'duration'   => 1440,
			)
		);

		$this->assertFalse( $timed->is_all_day, "The string 'false' is false, as REST reads it." );
		$this->assertSame( array( '20:00' ), $timed->times );
		$this->assertTrue(
			$this->rule(
				array(
					'is_all_day' => '1',
					'times'      => array(),
					'duration'   => '1440',
				)
			)->is_all_day
		);
		$this->assertSame( 120, $this->rule( array( 'duration' => '120' ) )->duration, 'A digit string counts.' );

		foreach ( array( '-0.5', 1.5, '12 minutes', null ) as $duration ) {
			$this->assertNull( Rule::from_meta( $this->row( array( 'duration' => $duration ) ) ), 'Not an integer.' );
		}

		$this->assertNull( Rule::from_meta( $this->row( array( 'starts_on' => '2026-13-01' ) ) ), 'Month 13.' );
		$this->assertNull( Rule::from_meta( $this->row( array( 'ends_on' => '2027-00-10' ) ) ), 'Month 0 is refused.' );
	}

	/**
	 * A rule whose next match is many intervals away within the Gregorian cycle is still found.
	 *
	 * @since TBD
	 *
	 * @covers ::period_limit
	 * @covers ::gcd
	 * @covers ::can_repeat
	 *
	 * @return void
	 */
	public function test_search_covers_the_cycle(): void {
		// Every 301 years from a leap day: 1705, 2006 and 2307 have no Feb 29; 2608 does.
		$rule = $this->rule(
			array(
				'starts_on' => '1404-02-29',
				'rrule'     => 'FREQ=YEARLY;INTERVAL=301',
			)
		);

		$this->assertSame( '2608-02-29', $rule->next_date( $this->utc( '1404-03-01' ) )->format( 'Y-m-d' ) );
		$this->assertSame( 401, Utility::invoke_hidden_method( $rule, 'period_limit' ), 'A full cycle of 400 years.' );
		$this->assertSame(
			3,
			Utility::invoke_hidden_method(
				$this->rule( array( 'rrule' => 'FREQ=YEARLY;INTERVAL=200' ) ),
				'period_limit'
			),
			'An interval sharing factors with the cycle repeats sooner.'
		);
		$this->assertSame( 4, Utility::invoke_hidden_method( $rule, 'gcd', array( 12, 8 ) ) );
	}

	/**
	 * A timed occurrence still running across a spring-forward change is found.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences
	 * @covers ::next_occurrence
	 * @covers ::lookback_days
	 *
	 * @return void
	 */
	public function test_running_occurrence_across_spring_forward(): void {
		// 23:30 EST on Mar 7 plus 24 elapsed hours ends at 00:30 EDT on Mar 9.
		$rule = $this->rule(
			array(
				'starts_on' => '2026-03-07',
				'rrule'     => 'FREQ=DAILY',
				'times'     => array( '23:30' ),
				'duration'  => 1440,
			)
		);
		$now  = $this->utc( '2026-03-09 04:15' );

		$this->assertSame( '20260307T233000', $rule->next_occurrence( $now, 7, 'scheduled' )->recurrence_id );
		$this->assertSame( '20260307T233000', $this->keys( $rule, '2026-03-09 04:15', '2026-03-09 04:20' )[0] );
	}

	/**
	 * A day's occurrences come in start order even when a gap moves an earlier time past a later one.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences_on
	 *
	 * @return void
	 */
	public function test_gap_day_order(): void {
		// 02:45 doesn't exist on Mar 8 and starts at 03:45, after 03:15.
		$rule = $this->rule(
			array(
				'starts_on' => '2026-03-08',
				'rrule'     => null,
				'times'     => array( '02:45', '03:15' ),
				'duration'  => 30,
			)
		);

		$this->assertSame(
			array( '20260308T031500', '20260308T034500' ),
			$this->keys( $rule, '2026-03-08', '2026-03-09' )
		);
	}

	/**
	 * A limit stops the expansion at the earliest occurrences.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences
	 *
	 * @return void
	 */
	public function test_occurrences_limit(): void {
		$rule = $this->rule(
			array(
				'rrule' => 'FREQ=DAILY',
				'times' => array( '09:00', '20:00' ),
			)
		);
		$keys = array_map(
			static fn( Occurrence $occurrence ): string => $occurrence->recurrence_id,
			$rule->occurrences( $this->utc( '2026-12-01' ), $this->utc( '2027-12-01' ), 7, 'scheduled', 3 )
		);

		$this->assertSame( array( '20261211T090000', '20261211T200000', '20261212T090000' ), $keys );
	}

	/**
	 * A gap-day collision counts once toward the limit, so the limit still returns that many dates.
	 *
	 * @since TBD
	 *
	 * @covers ::occurrences
	 * @covers ::occurrences_on
	 *
	 * @return void
	 */
	public function test_occurrences_limit_counts_a_collision_once(): void {
		// On Mar 8, 02:30 doesn't exist and starts at 03:30, the same instant as 03:30.
		$rule = $this->rule(
			array(
				'starts_on' => '2026-03-07',
				'rrule'     => 'FREQ=DAILY',
				'times'     => array( '02:30', '03:30', '04:00' ),
				'duration'  => 15,
			)
		);
		$keys = array_map(
			static fn( Occurrence $occurrence ): string => $occurrence->recurrence_id,
			$rule->occurrences( $this->utc( '2026-03-07' ), $this->utc( '2026-04-07' ), 7, 'scheduled', 5 )
		);

		$this->assertSame(
			array( '20260307T023000', '20260307T033000', '20260307T040000', '20260308T033000', '20260308T040000' ),
			$keys
		);
	}
}
