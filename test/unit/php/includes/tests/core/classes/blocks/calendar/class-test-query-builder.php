<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Query_Builder.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use GatherPress\Core\Blocks\Calendar\Date_Calculator;
use GatherPress\Core\Blocks\Calendar\Query_Builder;
use GatherPress\Core\Blocks\Calendar\Query_Integration;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;

/**
 * Class Test_Query_Builder.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar\Query_Builder
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
	 * Remove the test block type and the page parameters.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unregister_block_type( self::TEST_BLOCK );
		unset( $_GET['query-3-page'], $_GET['query-6-page'], $_GET['query-1-page'], $_GET['query-page'] );
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

		$_GET['query-3-page'] = '4';
		$this->assertSame( 4, Query_Builder::get_requested_page( 3 ) );
		$this->assertSame( 4, Query_Builder::get_requested_page( '3' ) );

		$_GET['query-page'] = '2';
		$this->assertSame(
			2,
			Query_Builder::get_requested_page( null ),
			'Failed to assert the key without a queryId.'
		);

		$_GET['query-3-page'] = '999999';
		$this->assertSame( Query_Builder::MAX_PAGE, Query_Builder::get_requested_page( 3 ) );

		$_GET['query-3-page'] = '-5';
		$this->assertSame( 1, Query_Builder::get_requested_page( 3 ) );

		$_GET['query-3-page'] = array( '2' );
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
		$_GET['query-1-page'] = '3';

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
	 * Coverage for build_query_args with an event post type.
	 *
	 * @covers ::build_query_args
	 *
	 * @return void
	 */
	public function test_build_query_args_for_events(): void {
		$range = Date_Calculator::calculate_date_range(
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
		$args  = Query_Builder::build_query_args( $block, $range );

		$this->assertSame( 'all', $args[ Event\Query::EVENT_QUERY_PARAM ] );
		$this->assertTrue( $args[ Query_Integration::CALENDAR_QUERY_PARAM ] );
		$this->assertSame( 'month', $args[ Query_Integration::CALENDAR_QUERY_VIEW_TYPE ] );
		$this->assertSame( '2026-10-01', $args[ Query_Integration::CALENDAR_QUERY_START_DATE ] );
		$this->assertSame( '2026-10-31', $args[ Query_Integration::CALENDAR_QUERY_END_DATE ] );
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

		$_GET['query-3-page'] = '2';
		$args                 = Query_Builder::build_query_args( $block, $range );

		$this->assertArrayNotHasKey( 'offset', $args, 'Failed to assert page 2 ignores the offset.' );
	}

	/**
	 * Coverage for build_query_args with posts.
	 *
	 * @covers ::build_query_args
	 *
	 * @return void
	 */
	public function test_build_query_args_for_posts(): void {
		$range = Date_Calculator::calculate_date_range(
			array(
				'viewType'     => 'day',
				'selectedDate' => '2026-10-15',
			)
		);
		$args  = Query_Builder::build_query_args( $this->get_block( array( 'postType' => 'post' ) ), $range );

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
	 * Coverage for build_query_args and include_scheduled_posts.
	 *
	 * @covers ::build_query_args
	 * @covers ::include_scheduled_posts
	 *
	 * @return void
	 */
	public function test_build_query_args_with_scheduled_posts(): void {
		$range = Date_Calculator::calculate_date_range( array( 'selectedDate' => '2026-10-15' ) );
		$block = $this->get_block( array( 'postType' => 'post' ), true );

		wp_set_current_user( 0 );
		$args = Query_Builder::build_query_args( $block, $range );

		$this->assertSame(
			array( 'publish', 'future' ),
			$args['post_status'],
			'Failed to assert no private posts for viewers.'
		);
		$this->assertSame( 'readable', $args['perm'] );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$args = Query_Builder::build_query_args( $block, $range );

		$this->assertSame( array( 'publish', 'future', 'private' ), $args['post_status'] );
		$this->assertArrayNotHasKey( 'perm', $args, 'Failed to assert an administrator reads all private posts.' );

		$args = Utility::invoke_hidden_static_method(
			Query_Builder::class,
			'include_scheduled_posts',
			array( array( 'post_type' => array( 5 ) ) )
		);

		$this->assertSame( 'readable', $args['perm'], 'Failed to assert an unknown post type limits private posts.' );

		wp_set_current_user( 0 );
	}
}
