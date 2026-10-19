<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Date_Calculator.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use DateTimeImmutable;
use GatherPress\Core\Blocks\Calendar\Date_Calculator;
use GatherPress\Core\Blocks\Calendar\Query_Integration;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use WP_Block;

/**
 * Class Test_Date_Calculator.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar\Date_Calculator
 */
class Test_Date_Calculator extends Base {

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
	 * Create an event with the given UTC datetimes.
	 *
	 * @param string $start   Start datetime (Y-m-d H:i:s).
	 * @param string $end     End datetime (Y-m-d H:i:s).
	 * @param bool   $all_day Whether the event is all day.
	 *
	 * @return int Event post ID.
	 */
	private function create_event( string $start, string $end, bool $all_day = false ): int {
		$post = $this->mock->post(
			array(
				'post_title' => 'Calendar Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		if ( $all_day ) {
			update_post_meta( $post->ID, 'gatherpress_is_all_day', true );
		}

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'UTC',
			)
		);

		return $post->ID;
	}

	/**
	 * Coverage for get_datetime_separator.
	 *
	 * @covers ::get_datetime_separator
	 *
	 * @return void
	 */
	public function test_get_datetime_separator(): void {
		$this->assertSame(
			'–',
			Date_Calculator::get_datetime_separator(),
			'Failed to assert the default separator.'
		);

		$received = array();
		$callback = static function ( $separator, $event ) use ( &$received ) {
			$received = array( $separator, $event );

			return ' / ';
		};
		add_filter( 'gatherpress_datetime_separator', $callback, 10, 2 );

		$this->assertSame( '/', Date_Calculator::get_datetime_separator(), 'Failed to assert a filtered separator.' );
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
			Date_Calculator::get_datetime_separator(),
			'Failed to assert a blank separator falls back.'
		);

		remove_filter( 'gatherpress_datetime_separator', $blank );
	}

	/**
	 * Coverage for calculate_date_range in month view.
	 *
	 * @covers ::calculate_date_range
	 * @covers ::resolve_base_date
	 * @covers ::calculate_view_bounds
	 * @covers ::build_range_payload
	 * @covers ::format_month_heading
	 *
	 * @return void
	 */
	public function test_calculate_date_range_month(): void {
		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10-15',
			)
		);

		$this->assertSame( 'month', $range['view_type'] );
		$this->assertSame( 1, $range['unit_count'] );
		$this->assertSame( '2026-10-01', $range['start_date'] );
		$this->assertSame( '2026-10-31', $range['end_date'] );
		$this->assertSame( '2026-10-01', $range['raw_week_start']->format( 'Y-m-d' ) );
		$this->assertSame( 2026, $range['year'] );
		$this->assertSame( 10, $range['month'] );
		$this->assertSame( 'October 2026', $range['heading'] );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10',
			),
			2
		);

		$this->assertSame( '2026-11-01', $range['start_date'], 'Failed to assert page 2 is the next month.' );
		$this->assertSame( '2026-11-30', $range['end_date'] );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10-31',
				'unitCount'    => 2,
				'dateModifier' => -1,
			)
		);

		$this->assertSame(
			'2026-09-01',
			$range['start_date'],
			'Failed to assert the modifier and no Oct 31 overflow.'
		);
		$this->assertSame( '2026-10-31', $range['end_date'] );
		$this->assertSame( 'September – October 2026', $range['heading'] );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-12-01',
				'unitCount'    => 2,
			)
		);

		$this->assertSame( 'Dec 2026 – Jan 2027', $range['heading'], 'Failed to assert a month range across years.' );
	}

	/**
	 * Coverage for calculate_date_range without a selected date.
	 *
	 * @covers ::calculate_date_range
	 * @covers ::resolve_base_date
	 *
	 * @return void
	 */
	public function test_calculate_date_range_defaults_to_current_month(): void {
		$range = Date_Calculator::calculate_date_range( array() );

		$this->assertSame( 'month', $range['view_type'] );
		$this->assertSame( current_datetime()->format( 'Y-m-01' ), $range['start_date'] );
	}

	/**
	 * Coverage for calculate_date_range in week view.
	 *
	 * @covers ::calculate_date_range
	 * @covers ::resolve_base_date
	 * @covers ::calculate_view_bounds
	 * @covers ::calculate_week_bounds
	 * @covers ::format_day_or_week_heading
	 *
	 * @return void
	 */
	public function test_calculate_date_range_week(): void {
		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-10-15',
			)
		);

		$this->assertSame( '2026-10-12', $range['start_date'], 'Failed to assert the week starts on Monday.' );
		$this->assertSame( '2026-10-18', $range['end_date'] );
		$this->assertSame( 'Oct 12 – 18, 2026', $range['heading'] );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-10-15',
				'showWeekends' => false,
			),
			2
		);

		$this->assertSame( '2026-10-19', $range['start_date'], 'Failed to assert page 2 is the next week.' );
		$this->assertSame( '2026-10-23', $range['end_date'], 'Failed to assert the week ends on Friday.' );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-10-29',
			)
		);

		$this->assertSame( 'Oct 26 – Nov 1, 2026', $range['heading'], 'Failed to assert a week across months.' );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-12-31',
			)
		);

		$this->assertSame( 'Dec 28, 2026 – Jan 3, 2027', $range['heading'], 'Failed to assert a week across years.' );
	}

	/**
	 * Coverage for calculate_week_bounds when every day is a hidden weekend day.
	 *
	 * @covers ::calculate_week_bounds
	 *
	 * @return void
	 */
	public function test_calculate_date_range_week_without_visible_days(): void {
		$all_days = static function () {
			return array( 0, 1, 2, 3, 4, 5, 6 );
		};
		add_filter( 'gatherpress_calendar_weekend_days', $all_days );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'week',
				'selectedDate' => '2026-10-15',
				'showWeekends' => false,
			)
		);

		remove_filter( 'gatherpress_calendar_weekend_days', $all_days );

		$this->assertSame( '2026-10-12', $range['start_date'] );
		$this->assertSame( '2026-10-18', $range['end_date'] );
	}

	/**
	 * Coverage for calculate_date_range in day view.
	 *
	 * @covers ::calculate_date_range
	 * @covers ::resolve_base_date
	 * @covers ::calculate_view_bounds
	 * @covers ::format_heading
	 *
	 * @return void
	 */
	public function test_calculate_date_range_day(): void {
		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'day',
				'selectedDate' => '2026-10-15',
			),
			3
		);

		$this->assertSame( '2026-10-17', $range['start_date'], 'Failed to assert page 3 is two days later.' );
		$this->assertSame( '2026-10-17', $range['end_date'] );
		$this->assertSame( 'Saturday, October 17, 2026', $range['heading'] );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'day',
				'selectedDate' => '2026-10-15',
				'unitCount'    => 3,
			)
		);

		$this->assertSame( '2026-10-17', $range['end_date'] );
		$this->assertSame( 'Oct 15 – 17, 2026', $range['heading'] );
	}

	/**
	 * Coverage for calculate_date_range with a source event.
	 *
	 * @covers ::calculate_date_range
	 *
	 * @return void
	 */
	public function test_calculate_date_range_from_event(): void {
		$event_id = $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' );
		$range    = Date_Calculator::calculate_date_range( array( 'viewType' => 'day' ), 1, $event_id );

		$this->assertSame( 5, $range['unit_count'], 'Failed to assert the range covers the 5 event days.' );
		$this->assertSame( '2026-10-16', $range['start_date'] );
		$this->assertSame( '2026-10-20', $range['end_date'] );

		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'        => 'day',
				'dateRangeSource' => 'selected',
				'postId'          => $event_id,
			)
		);

		$this->assertSame( '2026-10-16', $range['start_date'], 'Failed to assert the selected event is the source.' );
	}

	/**
	 * Coverage for get_post_event_dates.
	 *
	 * @covers ::get_post_event_dates
	 * @covers ::get_post_event_raw_datetimes
	 * @covers ::resolve_end_date
	 *
	 * @return void
	 */
	public function test_get_post_event_dates(): void {
		$this->assertNull( Date_Calculator::get_post_event_dates( 0 ), 'Failed to assert no dates for ID 0.' );

		$post_id = $this->mock->post()->get()->ID;
		$this->assertNull( Date_Calculator::get_post_event_dates( $post_id ), 'Failed to assert no dates for a post.' );

		$no_dates = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$this->assertNull(
			Date_Calculator::get_post_event_dates( $no_dates ),
			'Failed to assert no dates without a start.'
		);

		$event_id = $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' );
		$this->assertSame(
			array(
				'start' => '2026-10-16',
				'end'   => '2026-10-20',
			),
			Date_Calculator::get_post_event_dates( $event_id )
		);

		$midnight = $this->create_event( '2026-10-23 20:00:00', '2026-10-24 00:00:00' );
		$this->assertSame(
			array(
				'start' => '2026-10-23',
				'end'   => '2026-10-23',
			),
			Date_Calculator::get_post_event_dates( $midnight ),
			'Failed to assert an end at 00:00 ends on the day before.'
		);

		$all_day = $this->create_event( '2026-10-22 00:00:00', '2026-10-22 23:59:59', true );
		$this->assertSame(
			array(
				'start' => '2026-10-22',
				'end'   => '2026-10-22',
			),
			Date_Calculator::get_post_event_dates( $all_day ),
			'Failed to assert an all-day event keeps its day.'
		);

		$no_end = $this->create_event( '2026-10-22 10:00:00', '2026-10-22 12:00:00' );
		delete_post_meta( $no_end, 'gatherpress_datetime_end' );
		$this->assertSame( '2026-10-22', Date_Calculator::get_post_event_dates( $no_end )['end'] ?? '' );

		$backwards = $this->create_event( '2026-10-22 10:00:00', '2026-10-22 12:00:00' );
		update_post_meta( $backwards, 'gatherpress_datetime_end', '2026-10-20 12:00:00' );
		$this->assertSame(
			'2026-10-22',
			Date_Calculator::get_post_event_dates( $backwards )['end'] ?? '',
			'Failed to assert an end before the start is ignored.'
		);
	}

	/**
	 * Coverage for calculate_post_span_units.
	 *
	 * @covers ::calculate_post_span_units
	 * @covers ::parse_date_or_current
	 *
	 * @return void
	 */
	public function test_calculate_post_span_units(): void {
		$this->assertSame( 5, Date_Calculator::calculate_post_span_units( 'day', '2026-10-16', '2026-10-20' ) );
		$this->assertSame( 7, Date_Calculator::calculate_post_span_units( 'day', '2026-10-01', '2026-10-30' ) );
		$this->assertSame( 2, Date_Calculator::calculate_post_span_units( 'week', '2026-10-16', '2026-10-20' ) );
		$this->assertSame( 4, Date_Calculator::calculate_post_span_units( 'month', '2026-10-16', '2027-01-05' ) );
		$this->assertSame( 1, Date_Calculator::calculate_post_span_units( 'day', '', 'invalid' ) );
	}

	/**
	 * Coverage for resolve_source_post_id.
	 *
	 * @covers ::resolve_source_post_id
	 *
	 * @return void
	 */
	public function test_resolve_source_post_id(): void {
		$this->assertSame( 0, Date_Calculator::resolve_source_post_id( array() ) );
		$this->assertSame(
			42,
			Date_Calculator::resolve_source_post_id(
				array(
					'dateRangeSource' => 'selected',
					'postId'          => '42',
				)
			)
		);
		$this->assertSame( 0, Date_Calculator::resolve_source_post_id( array( 'dateRangeSource' => 'selected' ) ) );

		$block = new WP_Block(
			array(
				'blockName' => 'core/post-title',
				'attrs'     => array(),
			),
			array( 'postId' => 77 )
		);
		$this->assertSame(
			77,
			Date_Calculator::resolve_source_post_id( array( 'dateRangeSource' => 'context' ), $block ),
			'Failed to assert the block context post.'
		);

		unset( $GLOBALS['post'] );
		$this->assertSame(
			0,
			Date_Calculator::resolve_source_post_id( array( 'dateRangeSource' => 'context' ) ),
			'Failed to assert 0 without a current post.'
		);

		$post_id         = $this->mock->post()->get()->ID;
		$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame(
			$post_id,
			Date_Calculator::resolve_source_post_id( array( 'dateRangeSource' => 'context' ) ),
			'Failed to assert the current post.'
		);

		unset( $GLOBALS['post'] );
	}

	/**
	 * Coverage for the attribute resolvers.
	 *
	 * @covers ::resolve_view_type
	 * @covers ::resolve_unit_count
	 * @covers ::show_weekends
	 * @covers ::get_start_of_week
	 * @covers ::get_max_unit_count
	 *
	 * @return void
	 */
	public function test_attribute_resolvers(): void {
		$this->assertSame( 'week', Date_Calculator::resolve_view_type( array( 'viewType' => 'week' ) ) );
		$this->assertSame( 'month', Date_Calculator::resolve_view_type( array( 'viewType' => 'year' ) ) );
		$this->assertSame( 'month', Date_Calculator::resolve_view_type( array( 'viewType' => 5 ) ) );
		$this->assertSame( 'day', Date_Calculator::resolve_view_type( array(), 'day' ) );

		$this->assertSame( 3, Date_Calculator::resolve_unit_count( array( 'unitCount' => '3' ), 'day' ) );
		$this->assertSame( 5, Date_Calculator::resolve_unit_count( array( 'unitCount' => 99 ), 'week' ) );
		$this->assertSame( 1, Date_Calculator::resolve_unit_count( array( 'unitCount' => 0 ), 'day' ) );
		$this->assertSame( 1, Date_Calculator::resolve_unit_count( array(), 'month' ) );

		$this->assertTrue( Date_Calculator::show_weekends( array() ) );
		$this->assertTrue( Date_Calculator::show_weekends( array( 'showWeekends' => true ) ) );
		$this->assertFalse( Date_Calculator::show_weekends( array( 'showWeekends' => false ) ) );
		$this->assertFalse( Date_Calculator::show_weekends( array( 'showWeekends' => 'false' ) ) );

		$this->assertSame( 1, Date_Calculator::get_start_of_week() );

		$not_numeric = static function () {
			return 'abc';
		};
		add_filter( 'pre_option_start_of_week', $not_numeric );
		$this->assertSame(
			0,
			Date_Calculator::get_start_of_week(),
			'Failed to assert a non-numeric option is Sunday.'
		);
		remove_filter( 'pre_option_start_of_week', $not_numeric );

		$this->assertSame( 12, Date_Calculator::get_max_unit_count( 'month' ) );
		$this->assertSame( 5, Date_Calculator::get_max_unit_count( 'week' ) );
		$this->assertSame( 7, Date_Calculator::get_max_unit_count( 'day' ) );
		$this->assertSame( 12, Date_Calculator::get_max_unit_count( 'year' ) );
	}

	/**
	 * Coverage for get_range_from_query.
	 *
	 * @covers ::get_range_from_query
	 *
	 * @return void
	 */
	public function test_get_range_from_query(): void {
		$range = Date_Calculator::get_range_from_query(
			array(),
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10-15',
			)
		);

		$this->assertSame( '2026-10-01', $range['start_date'], 'Failed to assert the fallback to the attributes.' );

		$query = array(
			Query_Integration::CALENDAR_QUERY_VIEW_TYPE  => 'week',
			Query_Integration::CALENDAR_QUERY_START_DATE => '2026-10-14',
			Query_Integration::CALENDAR_QUERY_END_DATE   => '2026-10-20',
			Query_Integration::CALENDAR_QUERY_HEADING    => 'Custom heading',
		);
		$range = Date_Calculator::get_range_from_query( $query, array( 'unitCount' => 9 ) );

		$this->assertSame( 'week', $range['view_type'] );
		$this->assertSame( 5, $range['unit_count'], 'Failed to assert the unit count is clamped.' );
		$this->assertSame( '2026-10-14', $range['start_date'] );
		$this->assertSame( '2026-10-20', $range['end_date'] );
		$this->assertSame( '2026-10-12', $range['raw_week_start']->format( 'Y-m-d' ) );
		$this->assertSame( 'Custom heading', $range['heading'] );

		unset(
			$query[ Query_Integration::CALENDAR_QUERY_VIEW_TYPE ],
			$query[ Query_Integration::CALENDAR_QUERY_HEADING ]
		);
		$event_id = $this->create_event( '2026-10-14 10:00:00', '2026-10-16 10:00:00' );
		$range    = Date_Calculator::get_range_from_query( $query, array( 'viewType' => 'day' ), 1, $event_id );

		$this->assertSame( 'day', $range['view_type'], 'Failed to assert the view type of the attributes.' );
		$this->assertSame( 3, $range['unit_count'], 'Failed to assert the unit count of the event.' );
		$this->assertSame( '2026-10-14', $range['raw_week_start']->format( 'Y-m-d' ) );
		$this->assertSame( 'Oct 14 – 20, 2026', $range['heading'] );
	}

	/**
	 * Coverage for get_columns_count.
	 *
	 * @covers ::get_columns_count
	 *
	 * @return void
	 */
	public function test_get_columns_count(): void {
		$this->assertSame( 3, Date_Calculator::get_columns_count( 'day', true, 3 ) );
		$this->assertSame( 1, Date_Calculator::get_columns_count( 'day', true, 0 ) );
		$this->assertSame( 7, Date_Calculator::get_columns_count( 'month', true ) );
		$this->assertSame( 5, Date_Calculator::get_columns_count( 'month', false ) );
		$this->assertSame( 8, Date_Calculator::get_columns_count( 'week', true, 1, true ) );
		$this->assertSame( 7, Date_Calculator::get_columns_count( 'month', true, 1, true ) );
	}

	/**
	 * Coverage for the day name helpers.
	 *
	 * @covers ::get_view_day_names
	 * @covers ::get_day_names
	 * @covers ::get_weekday_slug
	 * @covers ::get_weekend_days
	 * @covers ::is_weekend_day
	 *
	 * @return void
	 */
	public function test_day_names(): void {
		$thursday = new DateTimeImmutable( '2026-10-15', wp_timezone() );

		$this->assertSame( array( 'Thu', 'Fri' ), Date_Calculator::get_view_day_names( 'day', $thursday, 1, true, 2 ) );
		$this->assertSame(
			array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ),
			Date_Calculator::get_view_day_names( 'month', $thursday, 1, true )
		);
		$this->assertSame(
			array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ),
			Date_Calculator::get_day_names( 0 )
		);
		$this->assertSame( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri' ), Date_Calculator::get_day_names( 1, false ) );

		$this->assertSame( 'wednesday', Date_Calculator::get_weekday_slug( 3 ) );
		$this->assertSame( '', Date_Calculator::get_weekday_slug( 9 ) );

		$this->assertSame( array( 0, 6 ), Date_Calculator::get_weekend_days() );
		$this->assertTrue( Date_Calculator::is_weekend_day( 6 ) );
		$this->assertFalse( Date_Calculator::is_weekend_day( 5 ) );

		$friday_saturday = static function () {
			return array( '5', 6 );
		};
		add_filter( 'gatherpress_calendar_weekend_days', $friday_saturday );

		$this->assertSame(
			array( 5, 6 ),
			Date_Calculator::get_weekend_days(),
			'Failed to assert filtered weekend days.'
		);
		$this->assertFalse( Date_Calculator::is_weekend_day( 0 ) );

		remove_filter( 'gatherpress_calendar_weekend_days', $friday_saturday );
	}

	/**
	 * Coverage for get_week_start and get_week_number.
	 *
	 * @covers ::get_week_start
	 * @covers ::get_week_number
	 *
	 * @return void
	 */
	public function test_week_helpers(): void {
		$thursday = new DateTimeImmutable( '2026-10-15 15:30:00', wp_timezone() );

		$this->assertSame(
			'2026-10-12 00:00:00',
			Date_Calculator::get_week_start( $thursday, 1 )->format( 'Y-m-d H:i:s' )
		);
		$this->assertSame( '2026-10-11', Date_Calculator::get_week_start( $thursday, 0 )->format( 'Y-m-d' ) );

		$week = array(
			'not a day',
			array( 'isEmpty' => true ),
			array( 'date' => '' ),
			array( 'date' => '2026-10-15' ),
		);
		$this->assertSame( 42, Date_Calculator::get_week_number( $week, 1 ) );
		$this->assertSame( 0, Date_Calculator::get_week_number( array( array( 'date' => 'not-a-date' ) ), 1 ) );
		$this->assertSame( 0, Date_Calculator::get_week_number( array(), 1 ) );
	}

	/**
	 * Coverage for format_heading.
	 *
	 * @covers ::format_heading
	 * @covers ::format_month_heading
	 *
	 * @return void
	 */
	public function test_format_heading(): void {
		$october = new DateTimeImmutable( '2026-10-15', wp_timezone() );

		$this->assertSame( 'Thursday, October 15, 2026', Date_Calculator::format_heading( 'day', $october, $october ) );
		$this->assertSame( 'October 2026', Date_Calculator::format_heading( 'month', $october, $october ) );
	}

	/**
	 * Coverage for get_today.
	 *
	 * @covers ::get_today
	 *
	 * @return void
	 */
	public function test_get_today(): void {
		$this->assertSame( wp_date( 'Y-m-d' ), Date_Calculator::get_today() );
	}

	/**
	 * Coverage for get_post_event_datetimes.
	 *
	 * @covers ::get_post_event_datetimes
	 *
	 * @return void
	 */
	public function test_get_post_event_datetimes(): void {
		$this->assertNull( Date_Calculator::get_post_event_datetimes( 0 ) );

		$event_id = $this->create_event( '2026-10-16 10:30:00', '2026-10-16 12:00:00' );
		$this->assertSame(
			array(
				'start_date' => '2026-10-16',
				'end_date'   => '2026-10-16',
				'start_min'  => 630,
				'end_min'    => 720,
			),
			Date_Calculator::get_post_event_datetimes( $event_id )
		);

		$multi_day = $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' );
		$this->assertSame(
			array(
				'start_date' => '2026-10-16',
				'end_date'   => '2026-10-20',
				'start_min'  => 600,
				'end_min'    => 960,
			),
			Date_Calculator::get_post_event_datetimes( $multi_day )
		);

		$midnight = $this->create_event( '2026-10-23 20:00:00', '2026-10-24 00:00:00' );
		$this->assertSame(
			1440,
			Date_Calculator::get_post_event_datetimes( $midnight )['end_min'] ?? 0,
			'Failed to assert an end at 00:00 runs to the end of the day.'
		);

		$short = $this->create_event( '2026-10-16 10:00:00', '2026-10-16 10:05:00' );
		$this->assertSame(
			615,
			Date_Calculator::get_post_event_datetimes( $short )['end_min'] ?? 0,
			'Failed to assert a minimum length of 15 minutes.'
		);

		$no_end = $this->create_event( '2026-10-16 10:00:00', '2026-10-16 12:00:00' );
		delete_post_meta( $no_end, 'gatherpress_datetime_end' );
		$this->assertSame(
			660,
			Date_Calculator::get_post_event_datetimes( $no_end )['end_min'] ?? 0,
			'Failed to assert an event without an end lasts one hour.'
		);

		$late = $this->create_event( '2026-10-16 23:30:00', '2026-10-16 23:45:00' );
		delete_post_meta( $late, 'gatherpress_datetime_end' );
		$this->assertSame(
			1440,
			Date_Calculator::get_post_event_datetimes( $late )['end_min'] ?? 0,
			'Failed to assert a default hour past midnight stops at the end of the day.'
		);
	}

	/**
	 * Coverage for get_time_grid_config.
	 *
	 * @covers ::get_time_grid_config
	 *
	 * @return void
	 */
	public function test_get_time_grid_config(): void {
		$disabled = Date_Calculator::get_time_grid_config(
			array(
				'viewType'       => 'month',
				'enableTimeGrid' => true,
			),
			array( 'orderBy' => 'datetime' )
		);
		$this->assertFalse( $disabled['enabled'], 'Failed to assert the time grid is off in month view.' );
		$this->assertSame( 48, $disabled['total_quarters'] );

		$attributes = array(
			'viewType'       => 'week',
			'enableTimeGrid' => true,
			'dayStartHour'   => 9,
			'dayEndHour'     => '17',
		);
		$config     = Date_Calculator::get_time_grid_config( $attributes, array( 'orderBy' => 'datetime' ) );

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 9, $config['start_hour'] );
		$this->assertSame( 17, $config['end_hour'] );
		$this->assertSame( 15, $config['quarter_mins'] );
		$this->assertSame( 32, $config['total_quarters'] );
		$this->assertSame(
			array( '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00' ),
			$config['slots']
		);

		$two_hours = static function () {
			return 2;
		};
		add_filter( 'gatherpress_calendar_time_slot_duration', $two_hours );

		$attributes['dayStartHour'] = 'early';
		$attributes['dayEndHour']   = 'late';
		$lowercase_query            = array( 'orderby' => 'datetime' );
		$config                     = Date_Calculator::get_time_grid_config( $attributes, $lowercase_query );

		remove_filter( 'gatherpress_calendar_time_slot_duration', $two_hours );

		$this->assertSame( 8, $config['start_hour'], 'Failed to assert the default start hour.' );
		$this->assertSame( 20, $config['end_hour'], 'Failed to assert the default end hour.' );
		$this->assertSame( 30, $config['quarter_mins'] );
		$this->assertSame( 24, $config['total_quarters'] );
		$this->assertSame( array( '08:00', '10:00', '12:00', '14:00', '16:00', '18:00' ), $config['slots'] );
	}

	/**
	 * Coverage for round_to_quarter_slot.
	 *
	 * @covers ::round_to_quarter_slot
	 *
	 * @return void
	 */
	public function test_round_to_quarter_slot(): void {
		$this->assertSame( 60, Date_Calculator::round_to_quarter_slot( 65, 15 ) );
		$this->assertSame( 75, Date_Calculator::round_to_quarter_slot( 65, 15, true ) );
		$this->assertSame( 65, Date_Calculator::round_to_quarter_slot( 65, 0 ) );
	}
}
