<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Structure_Builder.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Structure_Builder;
use GatherPress\Tests\Base;

/**
 * Class Test_Structure_Builder.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Structure_Builder
 */
class Test_Structure_Builder extends Base {

	/**
	 * Use Monday as the start of the week, like the WordPress default.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		update_option( 'start_of_week', 1 );
	}

	/**
	 * Get the day numbers of a week row, with null for an empty cell.
	 *
	 * @param array<int, array<string, mixed>> $week Week row.
	 *
	 * @return array<int, int|null>
	 */
	private function get_days( array $week ): array {
		return array_map(
			static function ( array $day ) {
				return $day['isEmpty'] ? null : $day['day'];
			},
			$week
		);
	}

	/**
	 * Coverage for build in month view.
	 *
	 * October 2026 starts on a Thursday and ends on a Saturday.
	 *
	 * @covers ::__construct
	 * @covers ::build
	 * @covers ::build_month_units
	 * @covers ::build_month_weeks
	 * @covers ::create_day_entry
	 * @covers ::create_empty_day_entry
	 *
	 * @return void
	 */
	public function test_build_month(): void {
		$range     = Date_Range::from_attributes(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10-15',
				'unitCount'    => 2,
			)
		);
		$structure = ( new Structure_Builder( $range ) )->build( array( '2026-10-15' => array( 11 ) ) );

		$this->assertSame( 'October – November 2026', $structure['heading'] );
		$this->assertSame( 'month', $structure['view_type'] );
		$this->assertSame( 2, $structure['unit_count'] );
		$this->assertCount( 2, $structure['units'] );
		$this->assertSame( 'October 2026', $structure['units'][0]['caption'] );
		$this->assertSame( 'November 2026', $structure['units'][1]['caption'] );
		$this->assertSame( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ), $structure['day_names'] );
		$this->assertSame( $structure['units'][0]['weeks'], $structure['weeks'] );

		$weeks = $structure['weeks'];
		$this->assertCount( 5, $weeks );
		$this->assertSame( array( null, null, null, 1, 2, 3, 4 ), $this->get_days( $weeks[0] ) );
		$this->assertSame( array( 26, 27, 28, 29, 30, 31, null ), $this->get_days( $weeks[4] ) );
		$this->assertSame(
			array(
				'isEmpty'   => true,
				'posts'     => array(),
				'dayOfWeek' => 1,
				'weekday'   => 'monday',
				'isWeekend' => false,
			),
			$weeks[0][0]
		);
		$this->assertSame(
			array(
				'day'       => 15,
				'date'      => '2026-10-15',
				'posts'     => array( 11 ),
				'isEmpty'   => false,
				'dayOfWeek' => 4,
				'weekday'   => 'thursday',
				'isWeekend' => false,
			),
			$weeks[2][3]
		);
		$this->assertTrue( $weeks[4][6]['isWeekend'], 'Failed to assert the empty Sunday is a weekend day.' );
	}

	/**
	 * Coverage for build_month_weeks with a month that fills its rows.
	 *
	 * February 2027 starts on a Monday and ends on a Sunday.
	 *
	 * @covers ::build_month_weeks
	 *
	 * @return void
	 */
	public function test_build_month_without_padding(): void {
		$range = Date_Range::from_attributes( array( 'selectedDate' => '2027-02' ) );
		$weeks = ( new Structure_Builder( $range ) )->build( array() )['weeks'];

		$this->assertCount( 4, $weeks );
		$this->assertSame( array( 1, 2, 3, 4, 5, 6, 7 ), $this->get_days( $weeks[0] ) );
		$this->assertSame( array( 22, 23, 24, 25, 26, 27, 28 ), $this->get_days( $weeks[3] ) );
	}

	/**
	 * Coverage for build_month_weeks without weekends.
	 *
	 * @covers ::build_month_weeks
	 *
	 * @return void
	 */
	public function test_build_month_without_weekends(): void {
		$range     = Date_Range::from_attributes(
			array(
				'selectedDate' => '2026-10',
				'showWeekends' => false,
			)
		);
		$structure = ( new Structure_Builder( $range ) )->build( array() );
		$weeks     = $structure['weeks'];

		$this->assertSame( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri' ), $structure['day_names'] );
		$this->assertCount( 5, $weeks );
		$this->assertSame( array( null, null, null, 1, 2 ), $this->get_days( $weeks[0] ) );
		$this->assertSame(
			array( 26, 27, 28, 29, 30 ),
			$this->get_days( $weeks[4] ),
			'Failed to assert Saturday 31 is left out.'
		);
	}

	/**
	 * Coverage for build_month_weeks when every day is a hidden weekend day.
	 *
	 * @covers ::build_month_weeks
	 *
	 * @return void
	 */
	public function test_build_month_without_visible_days(): void {
		$all_days = static function () {
			return array( 0, 1, 2, 3, 4, 5, 6 );
		};
		add_filter( 'gatherpress_calendar_weekend_days', $all_days );

		$range = Date_Range::from_attributes(
			array(
				'selectedDate' => '2026-10',
				'showWeekends' => false,
			)
		);
		$weeks = ( new Structure_Builder( $range ) )->build( array() )['weeks'];

		remove_filter( 'gatherpress_calendar_weekend_days', $all_days );

		$this->assertSame( array(), $weeks );
	}

	/**
	 * Coverage for build in week view.
	 *
	 * @covers ::build
	 * @covers ::build_consecutive_weeks
	 *
	 * @return void
	 */
	public function test_build_week(): void {
		$attributes = array(
			'viewType'     => 'week',
			'selectedDate' => '2026-10-29',
			'unitCount'    => 2,
		);
		$range      = Date_Range::from_attributes( $attributes );
		$structure  = ( new Structure_Builder( $range ) )->build( array( '2026-11-01' => array( 12 ) ) );

		$this->assertCount( 1, $structure['units'] );
		$this->assertSame( $range->heading, $structure['units'][0]['caption'] );
		$this->assertCount( 2, $structure['weeks'] );
		$this->assertSame( array( 26, 27, 28, 29, 30, 31, 1 ), $this->get_days( $structure['weeks'][0] ) );
		$this->assertSame( array( 2, 3, 4, 5, 6, 7, 8 ), $this->get_days( $structure['weeks'][1] ) );
		$this->assertSame( array( 12 ), $structure['weeks'][0][6]['posts'] );

		$attributes['showWeekends'] = false;
		$range                      = Date_Range::from_attributes( $attributes );
		$structure                  = ( new Structure_Builder( $range ) )->build( array() );

		$this->assertSame( array( 26, 27, 28, 29, 30 ), $this->get_days( $structure['weeks'][0] ) );
		$this->assertSame( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri' ), $structure['day_names'] );
	}

	/**
	 * Coverage for build in day view.
	 *
	 * @covers ::build
	 * @covers ::build_consecutive_days
	 *
	 * @return void
	 */
	public function test_build_day(): void {
		$range     = Date_Range::from_attributes(
			array(
				'viewType'     => 'day',
				'selectedDate' => '2026-10-15',
				'unitCount'    => 3,
			)
		);
		$structure = ( new Structure_Builder( $range ) )->build( array( '2026-10-16' => array( 13 ) ) );

		$this->assertSame( 'day', $structure['view_type'] );
		$this->assertSame( array( 'Thu', 'Fri', 'Sat' ), $structure['day_names'] );
		$this->assertCount( 1, $structure['weeks'] );
		$this->assertSame( array( 15, 16, 17 ), $this->get_days( $structure['weeks'][0] ) );
		$this->assertSame( array( 13 ), $structure['weeks'][0][1]['posts'] );
		$this->assertTrue( $structure['weeks'][0][2]['isWeekend'] );
	}
}
