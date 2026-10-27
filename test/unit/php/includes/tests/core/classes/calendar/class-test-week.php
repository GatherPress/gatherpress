<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Week.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar;

use DateTimeImmutable;
use GatherPress\Core\Calendar\Week;
use GatherPress\Tests\Base;

/**
 * Class Test_Week.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Week
 */
class Test_Week extends Base {

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
	 * Remove the weekend filters of the tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'gatherpress_calendar_weekend_days' );
		parent::tearDown();
	}

	/**
	 * Use the given weekend days.
	 *
	 * @param array<mixed> $days Filtered weekend days.
	 *
	 * @return void
	 */
	private function set_weekend_days( array $days ): void {
		add_filter(
			'gatherpress_calendar_weekend_days',
			static function () use ( $days ): array {
				return $days;
			}
		);
	}

	/**
	 * Coverage for the constructor and resolve_weekend_days.
	 *
	 * @covers ::__construct
	 * @covers ::resolve_weekend_days
	 *
	 * @return void
	 */
	public function test_construct(): void {
		$week = new Week();

		$this->assertTrue( $week->show_weekends );
		$this->assertSame( 1, $week->start_of_week );
		$this->assertSame( array( 0, 6 ), $week->weekend_days );

		$this->set_weekend_days( array( '5', 6, 6, 7, -1, 'abc' ) );

		$this->assertSame(
			array( 5, 6 ),
			( new Week() )->weekend_days,
			'Failed to assert repeated, out-of-range and non-numeric days are dropped.'
		);

		$not_numeric = static function () {
			return 'abc';
		};
		add_filter( 'pre_option_start_of_week', $not_numeric );

		$this->assertSame( 0, ( new Week() )->start_of_week, 'Failed to assert a non-numeric option is Sunday.' );

		remove_filter( 'pre_option_start_of_week', $not_numeric );
	}

	/**
	 * Coverage for from_attributes.
	 *
	 * @covers ::from_attributes
	 *
	 * @return void
	 */
	public function test_from_attributes(): void {
		$this->assertTrue( Week::from_attributes( array() )->show_weekends );
		$this->assertTrue( Week::from_attributes( array( 'showWeekends' => true ) )->show_weekends );
		$this->assertFalse( Week::from_attributes( array( 'showWeekends' => false ) )->show_weekends );
		$this->assertFalse( Week::from_attributes( array( 'showWeekends' => 'false' ) )->show_weekends );
	}

	/**
	 * Coverage for is_weekend, get_days and get_day_names.
	 *
	 * @covers ::is_weekend
	 * @covers ::get_days
	 * @covers ::get_day_names
	 *
	 * @return void
	 */
	public function test_days(): void {
		$week = new Week();

		$this->assertTrue( $week->is_weekend( 6 ) );
		$this->assertFalse( $week->is_weekend( 5 ) );
		$this->assertSame( array( 1, 2, 3, 4, 5, 6, 0 ), $week->get_days() );
		$this->assertSame( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ), $week->get_day_names() );

		$weekdays = new Week( false );

		$this->assertSame( array( 1, 2, 3, 4, 5 ), $weekdays->get_days() );
		$this->assertSame( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri' ), $weekdays->get_day_names() );

		update_option( 'start_of_week', 0 );

		$this->assertSame(
			array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ),
			( new Week() )->get_day_names()
		);

		$this->set_weekend_days( array( 5, 6 ) );
		$week = new Week( false );

		$this->assertFalse( $week->is_weekend( 0 ) );
		$this->assertSame(
			array( 0, 1, 2, 3, 4 ),
			$week->get_days(),
			'Failed to assert Friday and Saturday are hidden.'
		);
	}

	/**
	 * Coverage for get_start.
	 *
	 * @covers ::get_start
	 *
	 * @return void
	 */
	public function test_get_start(): void {
		$thursday = new DateTimeImmutable( '2026-10-15 15:30:00', wp_timezone() );

		$this->assertSame( '2026-10-12 00:00:00', ( new Week() )->get_start( $thursday )->format( 'Y-m-d H:i:s' ) );

		update_option( 'start_of_week', 0 );

		$this->assertSame( '2026-10-11', ( new Week() )->get_start( $thursday )->format( 'Y-m-d' ) );
	}

	/**
	 * Coverage for get_bounds.
	 *
	 * @covers ::get_bounds
	 *
	 * @return void
	 */
	public function test_get_bounds(): void {
		$thursday = new DateTimeImmutable( '2026-10-15', wp_timezone() );

		list( $start, $end ) = ( new Week() )->get_bounds( $thursday, 2 );

		$this->assertSame( '2026-10-12 00:00:00', $start->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-10-25 23:59:59', $end->format( 'Y-m-d H:i:s' ) );

		list( $start, $end ) = ( new Week( false ) )->get_bounds( $thursday, 1 );

		$this->assertSame( '2026-10-12', $start->format( 'Y-m-d' ) );
		$this->assertSame( '2026-10-16', $end->format( 'Y-m-d' ), 'Failed to assert the week ends on Friday.' );

		$this->set_weekend_days( array( 0, 1, 2, 3, 4, 5, 6 ) );

		list( $start, $end ) = ( new Week( false ) )->get_bounds( $thursday, 1 );

		$this->assertSame(
			'2026-10-12',
			$start->format( 'Y-m-d' ),
			'Failed to assert the full week without a visible day.'
		);
		$this->assertSame( '2026-10-18 23:59:59', $end->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Coverage for get_number.
	 *
	 * @covers ::get_number
	 *
	 * @return void
	 */
	public function test_get_number(): void {
		$week = new Week();
		$days = array(
			'not a day',
			array( 'isEmpty' => true ),
			array( 'date' => '' ),
			array( 'date' => '2026-10-15' ),
		);

		$this->assertSame( 42, $week->get_number( $days ) );
		$this->assertSame( 0, $week->get_number( array( array( 'date' => 'not-a-date' ) ) ) );
		$this->assertSame( 0, $week->get_number( array() ) );
	}
}
