<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Date_Query.
 *
 * @package GatherPress\Core\Event
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Date_Query;
use GatherPress\Tests\Base;

/**
 * Class Test_Date_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Date_Query
 */
class Test_Date_Query extends Base {

	/**
	 * Set the site to a timezone that is not UTC.
	 *
	 * Every expectation below is written as UTC, so a site running on UTC would
	 * pass whether or not the boundaries are reckoned in the site's timezone at
	 * all. New York keeps that mistake visible, and straddles a DST change
	 * between January and June.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		update_option( 'timezone_string', 'America/New_York' );
	}

	/**
	 * Coverage for resolve method.
	 *
	 * @dataProvider data_resolve
	 *
	 * @covers ::resolve
	 * @covers ::single_clause
	 * @covers ::resolve_calendar_span
	 * @covers ::to_site_datetime
	 * @covers ::site_datetime
	 * @covers ::to_string
	 *
	 * @param array<int|string, mixed>                                 $date_query The date query to resolve.
	 * @param array{start: ?string, end: ?string, column: string}|null $expects The window it should resolve to.
	 * @param string                                                   $message    Message to display on failure.
	 *
	 * @return void
	 */
	public function test_resolve( array $date_query, ?array $expects, string $message ): void {
		$this->assertSame( $expects, Date_Query::resolve( $date_query ), $message );
	}

	/**
	 * Data provider for resolve.
	 *
	 * @return array<int, array<int, mixed>>
	 */
	public function data_resolve(): array {
		return array(
			array(
				array( 'year' => 2026 ),
				array(
					'start'  => '2026-01-01 05:00:00',
					'end'    => '2027-01-01 04:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'A year should span the site\'s year, in UTC.',
			),
			array(
				array(
					'year'     => 2026,
					'monthnum' => 6,
				),
				array(
					'start'  => '2026-06-01 04:00:00',
					'end'    => '2026-07-01 03:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'A month should span the site\'s month, on daylight time.',
			),
			array(
				array(
					'year'  => 2026,
					'month' => 6,
				),
				array(
					'start'  => '2026-06-01 04:00:00',
					'end'    => '2026-07-01 03:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'`month` should be read the same way as `monthnum`.',
			),
			array(
				array(
					'year'     => 2026,
					'monthnum' => 6,
					'day'      => 15,
				),
				array(
					'start'  => '2026-06-15 04:00:00',
					'end'    => '2026-06-16 03:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'A day should span the site\'s day.',
			),
			array(
				array( array( 'year' => 2026 ) ),
				array(
					'start'  => '2026-01-01 05:00:00',
					'end'    => '2027-01-01 04:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'A clause wrapped in a list should be read the same as a bare one.',
			),
			array(
				array( 'after' => '2026-06-01' ),
				array(
					'start'  => '2026-06-01 04:00:01',
					'end'    => null,
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'`after` should be exclusive and leave the window open ended.',
			),
			array(
				array(
					'after'     => '2026-06-01',
					'inclusive' => true,
				),
				array(
					'start'  => '2026-06-01 04:00:00',
					'end'    => null,
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'`inclusive` should keep the boundary itself.',
			),
			array(
				array( 'before' => '2026-06-30' ),
				array(
					'start'  => null,
					'end'    => '2026-07-01 03:59:58',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'A bare `before` day should cover that whole day, minus its last second.',
			),
			array(
				array(
					'before'    => '2026-06-30',
					'inclusive' => true,
				),
				array(
					'start'  => null,
					'end'    => '2026-07-01 03:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'An inclusive `before` day should cover the whole day.',
			),
			array(
				array(
					'after'     => '2026-06-01 09:00:00',
					'before'    => '2026-06-30 17:00:00',
					'inclusive' => true,
				),
				array(
					'start'  => '2026-06-01 13:00:00',
					'end'    => '2026-06-30 21:00:00',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'A pair of times should resolve to both ends of the window.',
			),
			array(
				array(
					'after'     => array(
						'year'  => 2026,
						'month' => 6,
						'day'   => 1,
					),
					'inclusive' => true,
				),
				array(
					'start'  => '2026-06-01 04:00:00',
					'end'    => null,
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'`after` should also be readable as date parts.',
			),
			array(
				array(
					'year'   => 2026,
					'before' => '2026-06-30',
				),
				array(
					'start'  => '2026-01-01 05:00:00',
					'end'    => '2026-07-01 03:59:58',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'`before` should narrow the end of a calendar span.',
			),
			array(
				array(),
				null,
				'An empty date query should resolve to nothing.',
			),
			array(
				array( 'monthnum' => 6 ),
				null,
				'A month with no year has no anchor and should resolve to nothing.',
			),
			array(
				array( 'week' => 24 ),
				null,
				'A repeating slice of the calendar should be left alone.',
			),
			array(
				array( 'year' => 'soon' ),
				null,
				'A year that is not a number should resolve to nothing.',
			),
			array(
				array( 'after' => 'whenever' ),
				null,
				'A value that cannot be parsed should resolve to nothing.',
			),
			array(
				array( 'after' => '' ),
				null,
				'An empty value should resolve to nothing.',
			),
			array(
				array(
					'year'     => 2026,
					'monthnum' => 13,
				),
				null,
				'A month no calendar has should resolve to nothing.',
			),
			array(
				array(
					'year'   => 2026,
					'column' => 'datetime_start',
				),
				array(
					'start'  => '2026-01-01 00:00:00',
					'end'    => '2026-12-31 23:59:59',
					'column' => 'datetime_start',
				),
				'The local column should keep boundaries on the site\'s own clock.',
			),
			array(
				array(
					'year'   => 2026,
					'column' => 'post_date',
				),
				null,
				'A core column should be left for WordPress to resolve.',
			),
			array(
				array(
					'year' => 2026,
					'week' => 24,
				),
				null,
				'A clause carrying an argument that cannot be honored should resolve to nothing.',
			),
			array(
				array(
					'year'    => 2026,
					'compare' => '>',
				),
				null,
				'A comparison operator that cannot be honored should resolve to nothing.',
			),
			array(
				array(
					'relation' => 'AND',
					array( 'year' => 2026 ),
					array( 'year' => 2027 ),
				),
				null,
				'A relation between clauses should resolve to nothing.',
			),
			array(
				array(
					array( 'year' => 2026 ),
					array( 'year' => 2027 ),
				),
				null,
				'More than one clause should resolve to nothing.',
			),
			array(
				array(
					'year'  => 2026,
					'after' => '2025-12-01',
				),
				array(
					'start'  => '2026-01-01 05:00:00',
					'end'    => '2027-01-01 04:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'An `after` before the span opens should not widen it.',
			),
			array(
				array(
					'year'      => 2026,
					'after'     => '2026-06-15',
					'inclusive' => true,
				),
				array(
					'start'  => '2026-06-15 04:00:00',
					'end'    => '2027-01-01 04:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'An `after` inside the span should narrow its start.',
			),
			array(
				array(
					'year'   => 2026,
					'before' => '2027-06-30',
				),
				array(
					'start'  => '2026-01-01 05:00:00',
					'end'    => '2027-01-01 04:59:59',
					'column' => Date_Query::DEFAULT_COLUMN,
				),
				'A `before` after the span closes should not widen it.',
			),
		);
	}
}
