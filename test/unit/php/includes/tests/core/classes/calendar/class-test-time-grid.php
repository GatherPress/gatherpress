<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Time_Grid.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Post_Dates;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Time_Grid.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Time_Grid
 */
class Test_Time_Grid extends Base {

	/**
	 * Remove the slot duration filters of the tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'gatherpress_calendar_time_slot_duration' );
		parent::tearDown();
	}

	/**
	 * Get the dates of an event with the given UTC datetimes.
	 *
	 * @param string $start Start datetime (Y-m-d H:i:s).
	 * @param string $end   End datetime (Y-m-d H:i:s).
	 *
	 * @return Post_Dates
	 */
	private function get_event_dates( string $start, string $end ): Post_Dates {
		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'UTC',
			)
		);

		return new Post_Dates( $post_id );
	}

	/**
	 * Use rows of a number of hours.
	 *
	 * @param int $hours Hours per row.
	 *
	 * @return void
	 */
	private function set_slot_duration( int $hours ): void {
		add_filter(
			'gatherpress_calendar_time_slot_duration',
			static function () use ( $hours ): int {
				return $hours;
			}
		);
	}

	/**
	 * Coverage for the constructor.
	 *
	 * @covers ::__construct
	 * @covers ::build_slots
	 *
	 * @return void
	 */
	public function test_construct(): void {
		$disabled = new Time_Grid();

		$this->assertFalse( $disabled->enabled );
		$this->assertSame( 8, $disabled->start_hour );
		$this->assertSame( 20, $disabled->end_hour );
		$this->assertSame( 15, $disabled->quarter_mins );
		$this->assertSame( 48, $disabled->total_quarters );
		$this->assertSame( array(), $disabled->slots, 'Failed to assert a grid that is off has no rows.' );

		$this->set_slot_duration( 2 );

		$this->assertSame(
			1,
			( new Time_Grid() )->slot_hours,
			'Failed to assert a grid that is off ignores the slot duration.'
		);

		$grid = new Time_Grid( true, 9, 17 );

		$this->assertTrue( $grid->enabled );
		$this->assertSame( 2, $grid->slot_hours );
		$this->assertSame( 30, $grid->quarter_mins );
		$this->assertSame( 16, $grid->total_quarters );
		$this->assertSame( array( '09:00', '11:00', '13:00', '15:00' ), $grid->slots );

		$clamped = new Time_Grid( true, 30, -4 );

		$this->assertSame( 23, $clamped->start_hour, 'Failed to assert the start hour is at most 23.' );
		$this->assertSame( 24, $clamped->end_hour, 'Failed to assert the grid ends after its start.' );
	}

	/**
	 * Coverage for from_attributes.
	 *
	 * @covers ::from_attributes
	 *
	 * @return void
	 */
	public function test_from_attributes(): void {
		$attributes = array(
			'viewType'       => 'week',
			'enableTimeGrid' => true,
			'dayStartHour'   => 9,
			'dayEndHour'     => '17',
		);
		$by_date    = array( 'orderBy' => 'datetime' );

		$this->assertFalse(
			Time_Grid::from_attributes( array_merge( $attributes, array( 'viewType' => 'month' ) ), $by_date )->enabled,
			'Failed to assert the time grid is off in month view.'
		);
		$this->assertFalse(
			Time_Grid::from_attributes( $attributes, array( 'orderBy' => 'title' ) )->enabled,
			'Failed to assert the time grid is off without the event date order.'
		);
		$this->assertFalse(
			Time_Grid::from_attributes( array( 'enableTimeGrid' => false ) + $attributes, $by_date )->enabled,
			'Failed to assert the time grid is off when the calendar turns it off.'
		);

		$grid = Time_Grid::from_attributes( $attributes, $by_date );

		$this->assertTrue( $grid->enabled );
		$this->assertSame( 9, $grid->start_hour );
		$this->assertSame( 17, $grid->end_hour );
		$this->assertSame( 15, $grid->quarter_mins );
		$this->assertSame( 32, $grid->total_quarters );
		$this->assertSame(
			array( '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00' ),
			$grid->slots
		);

		$attributes['dayStartHour'] = 'early';
		$attributes['dayEndHour']   = 'late';
		$grid                       = Time_Grid::from_attributes( $attributes, array( 'orderby' => 'datetime' ) );

		$this->assertTrue( $grid->enabled, 'Failed to assert the lowercase orderby of a query.' );
		$this->assertSame( 8, $grid->start_hour, 'Failed to assert the default start hour.' );
		$this->assertSame( 20, $grid->end_hour, 'Failed to assert the default end hour.' );
	}

	/**
	 * Coverage for get_slot_duration.
	 *
	 * @covers ::get_slot_duration
	 *
	 * @return void
	 */
	public function test_get_slot_duration(): void {
		$this->assertSame( 1, Time_Grid::get_slot_duration(), 'Failed to assert the default of 1 hour.' );

		$slot_hours = 2;
		$callback   = static function () use ( &$slot_hours ) {
			return $slot_hours;
		};
		add_filter( 'gatherpress_calendar_time_slot_duration', $callback );

		$this->assertSame( 2, Time_Grid::get_slot_duration() );

		$slot_hours = '3';
		$this->assertSame( 3, Time_Grid::get_slot_duration(), 'Failed to assert a numeric string.' );

		$slot_hours = 0;
		$this->assertSame( 1, Time_Grid::get_slot_duration(), 'Failed to assert 0 becomes 1.' );
	}

	/**
	 * Coverage for get_tracks.
	 *
	 * @covers ::get_tracks
	 *
	 * @return void
	 */
	public function test_get_tracks(): void {
		$grid = new Time_Grid( true );

		$this->assertSame(
			array(
				'row_start' => 9,
				'row_end'   => 14,
			),
			$grid->get_tracks( $this->get_event_dates( '2026-10-16 10:00:00', '2026-10-16 11:10:00' ) ),
			'Failed to assert the default grid and a rounded-up end.'
		);
		$this->assertSame(
			array(
				'row_start' => 1,
				'row_end'   => 2,
			),
			$grid->get_tracks( $this->get_event_dates( '2026-10-16 06:00:00', '2026-10-16 07:00:00' ) ),
			'Failed to assert an event before the grid gets the first track.'
		);

		// The grid has 48 tracks, from line 1 (08:00) to line 49 (20:00).
		$this->assertSame(
			array(
				'row_start' => 48,
				'row_end'   => 49,
			),
			$grid->get_tracks( $this->get_event_dates( '2026-10-16 21:00:00', '2026-10-16 22:00:00' ) ),
			'Failed to assert an event after the grid gets the last track, not a row below the grid.'
		);
		$this->assertSame(
			array(
				'row_start' => 48,
				'row_end'   => 49,
			),
			$grid->get_tracks( $this->get_event_dates( '2026-10-16 20:00:00', '2026-10-16 20:30:00' ) ),
			'Failed to assert an event at the last hour gets the last track.'
		);

		// 3-hour rows give 45-minute steps. From 08:00 they are at 08:45 and 09:30, not at 09:00.
		$this->set_slot_duration( 3 );

		$short = $this->get_event_dates( '2026-10-16 08:00:00', '2026-10-16 08:50:00' );

		$this->assertSame(
			array(
				'row_start' => 1,
				'row_end'   => 3,
			),
			( new Time_Grid( true ) )->get_tracks( $short ),
			'Failed to assert the event ends at the step after 08:50 (09:30), not at 08:45.'
		);
	}

	/**
	 * Coverage for round_to_step.
	 *
	 * @covers ::round_to_step
	 *
	 * @return void
	 */
	public function test_round_to_step(): void {
		$from_midnight = new Time_Grid( true, 0, 24 );

		$this->assertSame( 60, Utility::invoke_hidden_method( $from_midnight, 'round_to_step', array( 65, false ) ) );
		$this->assertSame( 75, Utility::invoke_hidden_method( $from_midnight, 'round_to_step', array( 65, true ) ) );

		// 45-minute steps from 08:00 (480): 480, 525, 570. 08:50 (530) lies between 525 and 570.
		$this->set_slot_duration( 3 );

		$from_eight = new Time_Grid( true, 8, 20 );

		$this->assertSame( 525, Utility::invoke_hidden_method( $from_eight, 'round_to_step', array( 530, false ) ) );
		$this->assertSame( 570, Utility::invoke_hidden_method( $from_eight, 'round_to_step', array( 530, true ) ) );
	}
}
