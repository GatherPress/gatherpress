<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Date_Range.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar;

use DateTimeImmutable;
use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Post_Dates;
use GatherPress\Core\Calendar\Query_Builder;
use GatherPress\Core\Calendar\Week;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;

/**
 * Class Test_Date_Range.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Date_Range
 */
class Test_Date_Range extends Base {

	/**
	 * Name of a block type that reads the post context, like the calendar block.
	 *
	 * @var string
	 */
	const TEST_BLOCK = 'gatherpress-test/calendar';

	/**
	 * Use Monday as the start of the week, like the WordPress default, and register the test block type.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		update_option( 'start_of_week', 1 );
		register_block_type( self::TEST_BLOCK, array( 'uses_context' => array( 'postId' ) ) );
	}

	/**
	 * Remove the test block type.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unregister_block_type( self::TEST_BLOCK );
		parent::tearDown();
	}

	/**
	 * Build a test block with a post context.
	 *
	 * @param int $post_id Post ID of the context.
	 *
	 * @return WP_Block
	 */
	private function get_block( int $post_id ): WP_Block {
		return new WP_Block(
			array(
				'blockName' => self::TEST_BLOCK,
				'attrs'     => array(),
			),
			array( 'postId' => $post_id )
		);
	}

	/**
	 * Create an event with the given UTC datetimes.
	 *
	 * @param string $start Start datetime (Y-m-d H:i:s).
	 * @param string $end   End datetime (Y-m-d H:i:s).
	 *
	 * @return int Event post ID.
	 */
	private function create_event( string $start, string $end ): int {
		$post_id = $this->mock->post(
			array(
				'post_title' => 'Calendar Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get()->ID;

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'UTC',
			)
		);

		return $post_id;
	}

	/**
	 * Build a date range from its first and last day.
	 *
	 * @param string    $view_type  View type.
	 * @param int       $unit_count Number of units.
	 * @param string    $start      First day (Y-m-d).
	 * @param string    $end        Last day (Y-m-d).
	 * @param Week|null $week       The days of the week, all shown by default.
	 *
	 * @return Date_Range
	 */
	private function get_range(
		string $view_type,
		int $unit_count,
		string $start,
		string $end,
		?Week $week = null
	): Date_Range {
		return new Date_Range(
			$view_type,
			$unit_count,
			new DateTimeImmutable( $start, wp_timezone() ),
			new DateTimeImmutable( $end . ' 23:59:59', wp_timezone() ),
			$week ?? new Week()
		);
	}

	/**
	 * Coverage for get_separator.
	 *
	 * @covers ::get_separator
	 *
	 * @return void
	 */
	public function test_get_separator(): void {
		$range = $this->get_range( 'day', 2, '2026-10-15', '2026-10-16' );

		$this->assertSame(
			'–',
			Utility::invoke_hidden_method( $range, 'get_separator' ),
			'Failed to assert the default separator.'
		);

		$received = array();
		$callback = static function ( $separator, $event ) use ( &$received ) {
			$received = array( $separator, $event );

			return ' / ';
		};
		add_filter( 'gatherpress_datetime_separator', $callback, 10, 2 );

		$this->assertSame(
			'/',
			Utility::invoke_hidden_method( $range, 'get_separator' ),
			'Failed to assert a filtered separator.'
		);
		$this->assertSame(
			array( '–', null ),
			$received,
			'Failed to assert the filter gets the default and no event.'
		);

		remove_filter( 'gatherpress_datetime_separator', $callback, 10 );

		$blank = static function () {
			return '   ';
		};
		add_filter( 'gatherpress_datetime_separator', $blank );

		$this->assertSame(
			'–',
			Utility::invoke_hidden_method( $range, 'get_separator' ),
			'Failed to assert a blank separator falls back.'
		);

		remove_filter( 'gatherpress_datetime_separator', $blank );
	}

	/**
	 * Coverage for from_attributes in month view.
	 *
	 * @covers ::__construct
	 * @covers ::from_attributes
	 * @covers ::resolve_base_date
	 * @covers ::calculate_bounds
	 * @covers ::format_heading
	 * @covers ::get_month_range_formats
	 *
	 * @return void
	 */
	public function test_from_attributes_month(): void {
		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10-15',
			)
		);

		$this->assertSame( 'month', $range->view_type );
		$this->assertSame( 1, $range->unit_count );
		$this->assertSame( '2026-10-01', $range->start_date );
		$this->assertSame( '2026-10-31', $range->end_date );
		$this->assertSame( '2026-10-31 23:59:59', $range->end->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( 2026, $range->year );
		$this->assertSame( 10, $range->month );
		$this->assertSame( 'October 2026', $range->heading );
		$this->assertTrue( $range->week->show_weekends );

		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10',
			),
			2
		);

		$this->assertSame( '2026-11-01', $range->start_date, 'Failed to assert page 2 is the next month.' );
		$this->assertSame( '2026-11-30', $range->end_date );

		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10-31',
				'unitCount'    => 2,
				'dateModifier' => -1,
			)
		);

		$this->assertSame( '2026-09-01', $range->start_date, 'Failed to assert the modifier and no Oct 31 overflow.' );
		$this->assertSame( '2026-10-31', $range->end_date );
		$this->assertSame( 'September – October 2026', $range->heading );

		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-12-01',
				'unitCount'    => 2,
			)
		);

		$this->assertSame( 'Dec 2026 – Jan 2027', $range->heading, 'Failed to assert a month range across years.' );
	}

	/**
	 * Coverage for from_attributes without a selected date.
	 *
	 * @covers ::from_attributes
	 * @covers ::resolve_base_date
	 *
	 * @return void
	 */
	public function test_from_attributes_defaults_to_current_month(): void {
		$range = Date_Range::from_attributes( array() );

		$this->assertSame( 'month', $range->view_type );
		$this->assertSame( current_datetime()->format( 'Y-m-01' ), $range->start_date );
	}

	/**
	 * Coverage for from_attributes in week view.
	 *
	 * @covers ::from_attributes
	 * @covers ::calculate_bounds
	 * @covers ::format_heading
	 * @covers ::get_day_range_formats
	 *
	 * @return void
	 */
	public function test_from_attributes_week(): void {
		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-10-15',
			)
		);

		$this->assertSame( '2026-10-12', $range->start_date, 'Failed to assert the week starts on Monday.' );
		$this->assertSame( '2026-10-18', $range->end_date );
		$this->assertSame( 2026, $range->year );
		$this->assertSame( 10, $range->month );
		$this->assertSame( 'Oct 12 – 18, 2026', $range->heading );

		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-10-15',
				'showWeekends' => false,
			),
			2
		);

		$this->assertSame( '2026-10-19', $range->start_date, 'Failed to assert page 2 is the next week.' );
		$this->assertSame( '2026-10-23', $range->end_date, 'Failed to assert the week ends on Friday.' );
		$this->assertFalse( $range->week->show_weekends );

		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-10-29',
			)
		);

		$this->assertSame( 'Oct 26 – Nov 1, 2026', $range->heading, 'Failed to assert a week across months.' );

		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-12-31',
			)
		);

		$this->assertSame( 'Dec 28, 2026 – Jan 3, 2027', $range->heading, 'Failed to assert a week across years.' );
	}

	/**
	 * Coverage for from_attributes in day view.
	 *
	 * @covers ::from_attributes
	 * @covers ::calculate_bounds
	 * @covers ::format_heading
	 *
	 * @return void
	 */
	public function test_from_attributes_day(): void {
		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'day',
				'selectedDate' => '2026-10-15',
			),
			3
		);

		$this->assertSame( '2026-10-17', $range->start_date, 'Failed to assert page 3 is two days later.' );
		$this->assertSame( '2026-10-17', $range->end_date );
		$this->assertSame( 'Saturday, October 17, 2026', $range->heading );

		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'day',
				'selectedDate' => '2026-10-15',
				'unitCount'    => 3,
			)
		);

		$this->assertSame( '2026-10-17', $range->end_date );
		$this->assertSame( 'Oct 15 – 17, 2026', $range->heading );
	}

	/**
	 * Coverage for from_attributes with a source event.
	 *
	 * @covers ::from_attributes
	 * @covers ::get_source_post_dates
	 *
	 * @return void
	 */
	public function test_from_attributes_with_source_event(): void {
		$event_id = $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' );
		$range    = Date_Range::from_attributes(
			array(
				'viewType'        => 'day',
				'dateRangeSource' => 'selected',
				'postId'          => $event_id,
			)
		);

		$this->assertSame( 5, $range->unit_count, 'Failed to assert the range covers the 5 event days.' );
		$this->assertSame( '2026-10-16', $range->start_date );
		$this->assertSame( '2026-10-20', $range->end_date );

		unset( $GLOBALS['post'] );

		$range = Date_Range::from_attributes(
			array(
				'viewType'        => 'week',
				'dateRangeSource' => 'context',
			),
			1,
			$this->get_block( $event_id )
		);

		$this->assertSame( '2026-10-12', $range->start_date, 'Failed to assert the event of the block context.' );
		$this->assertSame( 2, $range->unit_count );
	}

	/**
	 * Coverage for get_source_post_dates.
	 *
	 * @covers ::get_source_post_dates
	 *
	 * @return void
	 */
	public function test_get_source_post_dates(): void {
		$post_id  = $this->mock->post( array( 'post_title' => 'Calendar Unit Test Post' ) )->get()->ID;
		$event_id = $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' );
		$get      = static function ( array $attributes ): ?Post_Dates {
			return Utility::invoke_hidden_static_method(
				Date_Range::class,
				'get_source_post_dates',
				array( $attributes, null )
			);
		};

		$this->assertNull( $get( array() ), 'Failed to assert the default source has no event.' );
		$this->assertSame(
			$event_id,
			$get(
				array(
					'dateRangeSource' => 'selected',
					'postId'          => (string) $event_id,
				)
			)->post_id
		);
		$this->assertNull( $get( array( 'dateRangeSource' => 'selected' ) ), 'Failed to assert no selected post.' );
		$this->assertNull(
			$get(
				array(
					'dateRangeSource' => 'selected',
					'postId'          => $post_id,
				)
			),
			'Failed to assert a post without event dates is no source.'
		);
	}

	/**
	 * Coverage for resolve_context_post_id.
	 *
	 * @covers ::resolve_context_post_id
	 *
	 * @return void
	 */
	public function test_resolve_context_post_id(): void {
		$this->assertSame(
			77,
			Utility::invoke_hidden_static_method(
				Date_Range::class,
				'resolve_context_post_id',
				array( $this->get_block( 77 ) )
			),
			'Failed to assert the block context post.'
		);

		unset( $GLOBALS['post'] );

		$this->assertSame(
			0,
			Utility::invoke_hidden_static_method( Date_Range::class, 'resolve_context_post_id', array( null ) ),
			'Failed to assert 0 without a current post.'
		);

		$post_id = $this->mock->post()->get()->ID;
		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame(
			$post_id,
			Utility::invoke_hidden_static_method( Date_Range::class, 'resolve_context_post_id', array( null ) ),
			'Failed to assert the current post.'
		);
	}

	/**
	 * Coverage for count_units.
	 *
	 * @covers ::count_units
	 * @covers ::parse_date_or_current
	 *
	 * @return void
	 */
	public function test_count_units(): void {
		$week  = new Week();
		$count = function ( string $view_type, string $start, string $end ) use ( $week ): int {
			return Utility::invoke_hidden_static_method(
				Date_Range::class,
				'count_units',
				array( $view_type, new Post_Dates( $this->create_event( $start, $end ) ), $week )
			);
		};

		$this->assertSame( 5, $count( 'day', '2026-10-16 10:00:00', '2026-10-20 16:00:00' ) );
		$this->assertSame(
			7,
			$count( 'day', '2026-10-01 10:00:00', '2026-10-30 16:00:00' ),
			'Failed to assert the day limit.'
		);
		$this->assertSame( 2, $count( 'week', '2026-10-16 10:00:00', '2026-10-20 16:00:00' ) );
		$this->assertSame( 4, $count( 'month', '2026-10-16 10:00:00', '2027-01-05 16:00:00' ) );
	}

	/**
	 * Coverage for resolve_view_type and resolve_unit_count.
	 *
	 * @covers ::resolve_view_type
	 * @covers ::resolve_unit_count
	 *
	 * @return void
	 */
	public function test_attribute_resolvers(): void {
		$view_type = static function ( $value ): string {
			return Utility::invoke_hidden_static_method( Date_Range::class, 'resolve_view_type', array( $value ) );
		};
		$units     = static function ( array $attributes, string $view_type ): int {
			return Utility::invoke_hidden_static_method(
				Date_Range::class,
				'resolve_unit_count',
				array( $attributes, $view_type )
			);
		};

		$this->assertSame( 'week', $view_type( 'week' ) );
		$this->assertSame( 'month', $view_type( 'year' ) );
		$this->assertSame( 'month', $view_type( 5 ) );
		$this->assertSame( 'month', $view_type( null ) );

		$this->assertSame( 3, $units( array( 'unitCount' => '3' ), 'day' ) );
		$this->assertSame( 5, $units( array( 'unitCount' => 99 ), 'week' ) );
		$this->assertSame( 1, $units( array( 'unitCount' => 0 ), 'day' ) );
		$this->assertSame( 1, $units( array(), 'month' ) );
	}

	/**
	 * Coverage for from_query.
	 *
	 * @covers ::from_query
	 * @covers ::parse_date_or_current
	 *
	 * @return void
	 */
	public function test_from_query(): void {
		$range = Date_Range::from_query(
			array(),
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10-15',
			)
		);

		$this->assertSame( '2026-10-01', $range->start_date, 'Failed to assert the fallback to the attributes.' );

		$query = array(
			Query_Builder::CALENDAR_QUERY_VIEW_TYPE  => 'week',
			Query_Builder::CALENDAR_QUERY_START_DATE => '2026-10-14',
			Query_Builder::CALENDAR_QUERY_END_DATE   => '2026-10-20',
			Query_Builder::CALENDAR_QUERY_HEADING    => 'Custom heading',
		);
		$range = Date_Range::from_query( $query, array( 'unitCount' => 9 ) );

		$this->assertSame( 'week', $range->view_type );
		$this->assertSame( 5, $range->unit_count, 'Failed to assert the unit count is clamped.' );
		$this->assertSame( '2026-10-14', $range->start_date );
		$this->assertSame( '2026-10-20', $range->end_date );
		$this->assertSame(
			'2026-10-20 23:59:59',
			$range->end->format( 'Y-m-d H:i:s' ),
			'Failed to assert the end of the last day.'
		);
		$this->assertSame( 2026, $range->year );
		$this->assertSame( 10, $range->month );
		$this->assertSame( 'Custom heading', $range->heading );

		unset(
			$query[ Query_Builder::CALENDAR_QUERY_VIEW_TYPE ],
			$query[ Query_Builder::CALENDAR_QUERY_HEADING ]
		);
		$event_id = $this->create_event( '2026-10-14 10:00:00', '2026-10-16 10:00:00' );
		$range    = Date_Range::from_query(
			$query,
			array(
				'viewType'        => 'day',
				'dateRangeSource' => 'selected',
				'postId'          => $event_id,
			)
		);

		$this->assertSame( 'day', $range->view_type, 'Failed to assert the view type of the attributes.' );
		$this->assertSame( 3, $range->unit_count, 'Failed to assert the unit count of the event.' );
		$this->assertSame( 'Oct 14 – 20, 2026', $range->heading );

		$query[ Query_Builder::CALENDAR_QUERY_START_DATE ] = 'not a date';

		$this->assertSame(
			current_datetime()->format( 'Y-m-d' ),
			Date_Range::from_query( $query )->start_date,
			'Failed to assert a date that is not valid is today.'
		);
	}

	/**
	 * Coverage for get_week_start.
	 *
	 * @covers ::get_week_start
	 *
	 * @return void
	 */
	public function test_get_week_start(): void {
		$this->assertSame(
			'2026-10-12 00:00:00',
			$this->get_range( 'week', 1, '2026-10-14', '2026-10-20' )->get_week_start()->format( 'Y-m-d H:i:s' )
		);
	}

	/**
	 * Coverage for get_months.
	 *
	 * @covers ::get_months
	 *
	 * @return void
	 */
	public function test_get_months(): void {
		$months = $this->get_range( 'month', 2, '2026-12-01', '2027-01-31' )->get_months();

		$this->assertCount( 2, $months );
		$this->assertSame( 'December 2026', $months[0]->heading );
		$this->assertSame( '2026-12-01', $months[0]->start_date );
		$this->assertSame( '2026-12-31', $months[0]->end_date );
		$this->assertSame( 'January 2027', $months[1]->heading );
		$this->assertSame( '2027-01-01', $months[1]->start_date );
		$this->assertSame( '2027-01-31 23:59:59', $months[1]->end->format( 'Y-m-d H:i:s' ) );

		$this->assertCount(
			1,
			$this->get_range( 'month', 0, '2026-12-01', '2026-12-31' )->get_months(),
			'Failed to assert at least one month.'
		);
	}

	/**
	 * Coverage for get_day_names.
	 *
	 * @covers ::get_day_names
	 *
	 * @return void
	 */
	public function test_get_day_names(): void {
		$this->assertSame(
			array( 'Thu', 'Fri' ),
			$this->get_range( 'day', 2, '2026-10-15', '2026-10-16' )->get_day_names()
		);
		$this->assertSame(
			array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ),
			$this->get_range( 'month', 1, '2026-10-01', '2026-10-31' )->get_day_names()
		);
	}

	/**
	 * Coverage for get_columns_count.
	 *
	 * @covers ::get_columns_count
	 *
	 * @return void
	 */
	public function test_get_columns_count(): void {
		$this->assertSame( 3, $this->get_range( 'day', 3, '2026-10-15', '2026-10-17' )->get_columns_count() );
		$this->assertSame( 1, $this->get_range( 'day', 0, '2026-10-15', '2026-10-15' )->get_columns_count() );
		$this->assertSame( 7, $this->get_range( 'month', 1, '2026-10-01', '2026-10-31' )->get_columns_count() );
		$this->assertSame(
			5,
			$this->get_range( 'month', 1, '2026-10-01', '2026-10-31', new Week( false ) )->get_columns_count()
		);
		$this->assertSame( 8, $this->get_range( 'week', 1, '2026-10-12', '2026-10-18' )->get_columns_count( true ) );
		$this->assertSame( 7, $this->get_range( 'month', 1, '2026-10-01', '2026-10-31' )->get_columns_count( true ) );
	}

	/**
	 * Coverage for the headings of a single day and month.
	 *
	 * @covers ::format_heading
	 *
	 * @return void
	 */
	public function test_format_heading(): void {
		$this->assertSame(
			'Thursday, October 15, 2026',
			$this->get_range( 'day', 1, '2026-10-15', '2026-10-15' )->heading
		);
		$this->assertSame( 'October 2026', $this->get_range( 'month', 1, '2026-10-01', '2026-10-31' )->heading );
	}
}
