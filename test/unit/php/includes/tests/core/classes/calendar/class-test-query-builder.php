<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Query_Builder.
 *
 * @package GatherPress\Core
 * @since 0.37.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Event\Occurrence_Index;
use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Query_Builder;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;
use WP_Query;

/**
 * Class Test_Query_Builder.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Query_Builder
 */
class Test_Query_Builder extends Base {

	/**
	 * Name of a block type that reads the Query Loop context, like the calendar block.
	 *
	 * @var string
	 */
	const TEST_BLOCK = 'gatherpress-test/calendar';

	/**
	 * Register the test block type.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		register_block_type(
			self::TEST_BLOCK,
			array(
				'uses_context' => array( 'query', 'queryId' ),
				'attributes'   => array(
					'showScheduled' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
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
	 * Build a test block inside a Query Loop context.
	 *
	 * @param array<string, mixed> $query          Query attribute of the Query Loop.
	 * @param bool                 $show_scheduled Whether the block shows scheduled posts.
	 *
	 * @return WP_Block
	 */
	private function get_block( array $query, bool $show_scheduled = false ): WP_Block {
		return new WP_Block(
			array(
				'blockName' => self::TEST_BLOCK,
				'attrs'     => array( 'showScheduled' => $show_scheduled ),
			),
			array(
				'query'   => $query,
				'queryId' => 3,
			)
		);
	}

	/**
	 * Coverage for get_posts_per_page.
	 *
	 * @covers ::get_posts_per_page
	 *
	 * @return void
	 */
	public function test_get_posts_per_page(): void {
		$this->assertSame( 500, Query_Builder::get_posts_per_page() );

		$fifty = static function () {
			return 50;
		};
		add_filter( 'gatherpress_calendar_posts_per_page', $fifty );
		$this->assertSame( 50, Query_Builder::get_posts_per_page() );
		remove_filter( 'gatherpress_calendar_posts_per_page', $fifty );

		$zero = static function () {
			return 0;
		};
		add_filter( 'gatherpress_calendar_posts_per_page', $zero );
		$this->assertSame( 1, Query_Builder::get_posts_per_page(), 'Failed to assert a minimum of 1.' );
		remove_filter( 'gatherpress_calendar_posts_per_page', $zero );
	}

	/**
	 * Coverage for get_requested_page.
	 *
	 * @covers ::get_requested_page
	 *
	 * @return void
	 */
	public function test_get_requested_page(): void {
		$this->assertSame( 1, Query_Builder::get_requested_page( 3 ), 'Failed to assert page 1 without a parameter.' );

		$this->set_url_params( array( 'query-3-page' => '4' ) );
		$this->assertSame( 4, Query_Builder::get_requested_page( 3 ) );
		$this->assertSame( 4, Query_Builder::get_requested_page( '3' ) );

		$this->set_url_params( array( 'query-page' => '2' ) );
		$this->assertSame(
			2,
			Query_Builder::get_requested_page( null ),
			'Failed to assert the key without a queryId.'
		);

		$this->set_url_params( array( 'query-3-page' => '999999' ) );
		$this->assertSame( Query_Builder::MAX_PAGE, Query_Builder::get_requested_page( 3 ) );

		$this->set_url_params( array( 'query-3-page' => '-5' ) );
		$this->assertSame( 1, Query_Builder::get_requested_page( 3 ) );

		$this->set_url_params( array( 'query-3-page' => false ) );
		$this->assertSame( 1, Query_Builder::get_requested_page( 3 ), 'Failed to assert an array is ignored.' );
	}

	/**
	 * A calendar does not follow the page parameter of another Query Loop.
	 *
	 * @covers ::get_requested_page
	 *
	 * @return void
	 */
	public function test_get_requested_page_ignores_other_query_loops(): void {
		$this->set_url_params( array( 'query-1-page' => '3' ) );

		$this->assertSame( 1, Query_Builder::get_requested_page( 6 ) );
	}

	/**
	 * Coverage for is_event_post_type.
	 *
	 * @covers ::is_event_post_type
	 *
	 * @return void
	 */
	public function test_is_event_post_type(): void {
		$this->assertTrue( Query_Builder::is_event_post_type( Event::POST_TYPE ) );
		$this->assertFalse( Query_Builder::is_event_post_type( 'post' ) );
		$this->assertTrue( Query_Builder::is_event_post_type( array( Event::POST_TYPE ) ) );
		$this->assertFalse( Query_Builder::is_event_post_type( array( Event::POST_TYPE, 'post' ) ) );
		$this->assertFalse( Query_Builder::is_event_post_type( array( 5 ) ) );
		$this->assertFalse( Query_Builder::is_event_post_type( array() ) );
		$this->assertFalse( Query_Builder::is_event_post_type( 5 ) );
	}

	/**
	 * Coverage for get_args with an event post type.
	 *
	 * @covers ::__construct
	 * @covers ::get_args
	 *
	 * @return void
	 */
	public function test_get_args_for_events(): void {
		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'month',
				'selectedDate' => '2026-10-15',
			)
		);
		$block = $this->get_block(
			array(
				'postType' => Event::POST_TYPE,
				'perPage'  => 5,
				'offset'   => 2,
			)
		);
		$args  = ( new Query_Builder( $block, $range ) )->get_args();

		$this->assertSame( 'all', $args[ Event\Query::EVENT_QUERY_PARAM ] );
		$this->assertTrue( $args[ Query_Builder::CALENDAR_QUERY_PARAM ] );
		$this->assertSame( 'month', $args[ Query_Builder::CALENDAR_QUERY_VIEW_TYPE ] );
		$this->assertSame( '2026-10-01', $args[ Query_Builder::CALENDAR_QUERY_START_DATE ] );
		$this->assertSame( '2026-10-31', $args[ Query_Builder::CALENDAR_QUERY_END_DATE ] );
		$this->assertSame( 500, $args['posts_per_page'] );
		$this->assertArrayNotHasKey( 'offset', $args, 'Failed to assert page 1 ignores the offset.' );
		$this->assertArrayNotHasKey( 'post_status', $args, 'Failed to assert scheduled posts are off by default.' );
		$this->assertSame(
			array(
				array(
					'after'     => '2026-10-01 00:00:00',
					'before'    => '2026-10-31 23:59:59',
					'inclusive' => true,
					'column'    => 'datetime_start',
				),
			),
			$args['date_query']
		);

		$this->set_url_params( array( 'query-3-page' => '2' ) );
		$args = ( new Query_Builder( $block, $range ) )->get_args();

		$this->assertArrayNotHasKey( 'offset', $args, 'Failed to assert page 2 ignores the offset.' );
	}

	/**
	 * Coverage for get_args with posts.
	 *
	 * @covers ::get_args
	 *
	 * @return void
	 */
	public function test_get_args_for_posts(): void {
		$range = Date_Range::from_attributes(
			array(
				'viewType'     => 'day',
				'selectedDate' => '2026-10-15',
			)
		);
		$args  = ( new Query_Builder( $this->get_block( array( 'postType' => 'post' ) ), $range ) )->get_args();

		$this->assertSame(
			array(
				array(
					'after'     => '2026-10-15 00:00:00',
					'before'    => '2026-10-15 23:59:59',
					'inclusive' => true,
				),
			),
			$args['date_query'],
			'Failed to assert posts are filtered by their publish date.'
		);
	}

	/**
	 * Coverage for get_args and include_scheduled_posts.
	 *
	 * @covers ::get_args
	 * @covers ::include_scheduled_posts
	 *
	 * @return void
	 */
	public function test_get_args_with_scheduled_posts(): void {
		$range   = Date_Range::from_attributes( array( 'selectedDate' => '2026-10-15' ) );
		$builder = new Query_Builder( $this->get_block( array( 'postType' => 'post' ), true ), $range );

		wp_set_current_user( 0 );
		$args = $builder->get_args();

		$this->assertSame(
			array( 'publish', 'future' ),
			$args['post_status'],
			'Failed to assert no private posts for viewers.'
		);
		$this->assertSame( 'readable', $args['perm'] );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$args = $builder->get_args();

		$this->assertSame( array( 'publish', 'future', 'private' ), $args['post_status'] );
		$this->assertArrayNotHasKey( 'perm', $args, 'Failed to assert an administrator reads all private posts.' );

		$args = Utility::invoke_hidden_method(
			$builder,
			'include_scheduled_posts',
			array( array( 'post_type' => array( 5 ) ) )
		);

		$this->assertSame( 'readable', $args['perm'], 'Failed to assert an unknown post type limits private posts.' );

		wp_set_current_user( 0 );
	}

	/**
	 * Coverage for get_entries.
	 *
	 * @covers ::get_entries
	 *
	 * @return void
	 */
	public function test_get_entries(): void {
		$post_id = $this->mock->post(
			array(
				'post_title' => 'Zyxcalendarids',
				'post_date'  => '2026-10-15 10:00:00',
			)
		)->get()->ID;
		$range   = Date_Range::from_attributes( array( 'selectedDate' => '2026-10-15' ) );
		$vars    = array();
		$capture = static function ( WP_Query $query ) use ( &$vars ): void {
			$vars = $query->query_vars;
		};
		add_action( 'pre_get_posts', $capture );

		$query   = array(
			'postType' => 'post',
			'search'   => 'Zyxcalendarids',
		);
		$entries = ( new Query_Builder( $this->get_block( $query ), $range ) )->get_entries();

		remove_action( 'pre_get_posts', $capture );

		$this->assertSame(
			array(
				array(
					'post_id'    => $post_id,
					'occurrence' => null,
				),
			),
			$entries,
			'A post has no date of its own.'
		);
		$this->assertTrue( $vars[ Event\Query::OCCURRENCES_PARAM ], 'The query joins the date index.' );
		$this->assertTrue( $vars['no_found_rows'], 'Failed to assert the query counts no rows.' );

		$query['search'] = 'Zyxcalendarnothing';

		$this->assertSame(
			array(),
			( new Query_Builder( $this->get_block( $query ), $range ) )->get_entries(),
			'Failed to assert an empty query gives no entries.'
		);
	}

	/**
	 * A repeating event comes back once per date in the calendar's range.
	 *
	 * @since TBD
	 *
	 * @covers ::get_entries
	 *
	 * @return void
	 */
	public function test_get_entries_for_a_repeating_event(): void {
		update_option( Occurrence_Index::SCHEMA_OPTION, Occurrence_Index::SCHEMA_VERSION );

		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$month   = new \DateTimeImmutable( 'first day of next month', new \DateTimeZone( 'UTC' ) );

		( new Event( $post_id ) )->rules()->replace(
			array(
				array(
					'starts_on' => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
					'rrule'     => 'FREQ=DAILY',
					'times'     => array( '12:00' ),
					'timezone'  => 'UTC',
					'duration'  => 60,
				),
			)
		);
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$range   = Date_Range::from_attributes( array( 'selectedDate' => $month->format( 'Y-m-d' ) ) );
		$block   = $this->get_block( array( 'postType' => Event::POST_TYPE ) );
		$entries = ( new Query_Builder( $block, $range ) )->get_entries();
		$days    = array_unique(
			array_map(
				static fn( array $entry ): string => substr( $entry['occurrence']['datetime_start'], 0, 10 ),
				array_filter( $entries, static fn( array $entry ): bool => $post_id === $entry['post_id'] )
			)
		);

		$this->assertGreaterThanOrEqual( 28, count( $days ), 'A daily event fills the month, a row per day.' );
	}
}
