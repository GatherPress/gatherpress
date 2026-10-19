<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Query_Integration.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use GatherPress\Core\Blocks\Calendar\Query_Integration;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;
use WP_Query;
use WP_REST_Request;

/**
 * Class Test_Query_Integration.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar\Query_Integration
 */
class Test_Query_Integration extends Base {

	/**
	 * Clear the per-request state of the singleton.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		update_option( 'start_of_week', 1 );
		Utility::set_and_get_hidden_property( Query_Integration::get_instance(), 'calendar_query_ids', array() );
	}

	/**
	 * Clear the per-request state of the singleton and the request parameters.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$instance = Query_Integration::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'calendar_query_ids', array() );
		remove_filter( 'the_posts', array( $instance, 'force_pagination_max_pages' ) );
		unset( $_REQUEST[ Query_Integration::CALENDAR_QUERY_PARAM ], $_GET['query-5-page'] );
		parent::tearDown();
	}

	/**
	 * Get the queryIds recorded as calendar queries.
	 *
	 * @return array<int, true>
	 */
	private function get_calendar_query_ids(): array {
		return Utility::get_hidden_property( Query_Integration::get_instance(), 'calendar_query_ids' );
	}

	/**
	 * Build a parsed Query block that contains a calendar inside a Group.
	 *
	 * @param array<string, mixed> $attrs Query block attributes.
	 *
	 * @return array<string, mixed>
	 */
	private function get_calendar_query_block( array $attrs ): array {
		return array(
			'blockName'   => 'core/query',
			'attrs'       => $attrs,
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/group',
					'attrs'       => array(),
					'innerBlocks' => array(
						array(
							'blockName' => 'gatherpress/calendar',
							'attrs'     => array( 'selectedDate' => '2026-10-15' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Build a block inside a Query Loop context.
	 *
	 * @param string               $block_name Block name, for its uses_context.
	 * @param array<string, mixed> $context    Available context.
	 *
	 * @return WP_Block
	 */
	private function get_block( string $block_name, array $context ): WP_Block {
		return new WP_Block(
			array(
				'blockName' => $block_name,
				'attrs'     => array(),
			),
			$context
		);
	}

	/**
	 * Tests the setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Query_Integration::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_register_rest_hooks' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => PHP_INT_MAX,
				'callback' => array( $instance, 'register_existing_post_types' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'render_block_data',
				'priority' => 10,
				'callback' => array( $instance, 'allow_core_pagination' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'render_block_core/query',
				'priority' => 10,
				'callback' => array( $instance, 'forget_calendar_query' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'render_block_context',
				'priority' => 10,
				'callback' => array( $instance, 'disable_query_pagination_numbers' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'query_vars',
				'priority' => 10,
				'callback' => array( $instance, 'query_vars' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'query_loop_block_query_vars',
				'priority' => 10,
				'callback' => array( $instance, 'query_loop_block_query_vars' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for get_calendar_post_types.
	 *
	 * @covers ::get_calendar_post_types
	 *
	 * @return void
	 */
	public function test_get_calendar_post_types(): void {
		$post_types = Query_Integration::get_calendar_post_types();

		$this->assertSame( 'post', $post_types[0] );
		$this->assertContains( Event::POST_TYPE, $post_types );
		$this->assertSame( array_values( array_unique( $post_types ) ), $post_types );
	}

	/**
	 * Coverage for register_existing_post_types and maybe_register_rest_hooks.
	 *
	 * @covers ::register_existing_post_types
	 * @covers ::maybe_register_rest_hooks
	 *
	 * @return void
	 */
	public function test_register_rest_hooks(): void {
		$instance = Query_Integration::get_instance();

		remove_filter( 'rest_post_query', array( $instance, 'rest_post_type_query' ), 20 );
		remove_filter( 'rest_post_collection_params', array( $instance, 'filter_rest_collection_params' ) );

		$instance->register_existing_post_types();

		$this->assertSame( 20, has_filter( 'rest_post_query', array( $instance, 'rest_post_type_query' ) ) );
		$this->assertSame(
			10,
			has_filter( 'rest_post_collection_params', array( $instance, 'filter_rest_collection_params' ) )
		);
		$this->assertSame(
			20,
			has_filter( sprintf( 'rest_%s_query', Event::POST_TYPE ), array( $instance, 'rest_post_type_query' ) )
		);

		$instance->maybe_register_rest_hooks( 'page' );

		$this->assertFalse( has_filter( 'rest_page_query', array( $instance, 'rest_post_type_query' ) ) );
	}

	/**
	 * Coverage for filter_rest_collection_params.
	 *
	 * @covers ::filter_rest_collection_params
	 *
	 * @return void
	 */
	public function test_filter_rest_collection_params(): void {
		$instance = Query_Integration::get_instance();
		$params   = array( 'per_page' => array( 'maximum' => 100 ) );

		$this->assertSame(
			$params,
			$instance->filter_rest_collection_params( $params ),
			'Failed to assert no change without the marker.'
		);

		$_REQUEST[ Query_Integration::CALENDAR_QUERY_PARAM ] = '1';

		$this->assertSame( 500, $instance->filter_rest_collection_params( $params )['per_page']['maximum'] );
		$this->assertSame( array(), $instance->filter_rest_collection_params( array() ) );
	}

	/**
	 * Coverage for rest_post_type_query.
	 *
	 * @covers ::rest_post_type_query
	 * @covers ::build_rest_date_clause
	 * @covers ::resolve_rest_date_param
	 *
	 * @return void
	 */
	public function test_rest_post_type_query(): void {
		$instance = Query_Integration::get_instance();
		$args     = array(
			'post_type'                    => Event::POST_TYPE,
			Event\Query::EVENT_QUERY_PARAM => 'upcoming',
			'date_query'                   => array( 'old', 'kept' ),
		);
		$request  = new WP_REST_Request( 'GET', '/wp/v2/gatherpress_event' );

		$this->assertSame(
			$args,
			$instance->rest_post_type_query( $args, $request ),
			'Failed to assert no change without the marker.'
		);

		$request->set_param( Query_Integration::CALENDAR_QUERY_PARAM, 1 );
		$request->set_param( 'start_date', '2026-10-01' );
		$request->set_param( 'end_date', '2026-10-31' );
		$result = $instance->rest_post_type_query( $args, $request );

		$this->assertArrayNotHasKey(
			Event\Query::EVENT_QUERY_PARAM,
			$result,
			'Failed to assert past events are allowed.'
		);
		$this->assertSame(
			array(
				array(
					'after'     => '2026-10-01 00:00:00',
					'before'    => '2026-10-31 23:59:59',
					'inclusive' => true,
					'column'    => 'datetime_start',
				),
				'kept',
			),
			$result['date_query']
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( Query_Integration::CALENDAR_QUERY_PARAM, 1 );
		$request->set_param( 'after', '2026-10-01' );
		$request->set_param( 'before', '2026-10-31' );
		$result = $instance->rest_post_type_query( array( 'post_type' => 'post' ), $request );

		$this->assertSame(
			array(
				array(
					'after'     => '2026-10-01 00:00:00',
					'before'    => '2026-10-31 23:59:59',
					'inclusive' => true,
				),
			),
			$result['date_query'],
			'Failed to assert the after and before parameters for posts.'
		);

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( Query_Integration::CALENDAR_QUERY_PARAM, 1 );
		$request->set_param( 'start_date', '2026-10-01' );
		$request->set_param( 'end_date', array( 'not', 'a', 'date' ) );

		$this->assertArrayNotHasKey(
			'date_query',
			$instance->rest_post_type_query( array(), $request ),
			'Failed to assert no date query without an end date.'
		);
	}

	/**
	 * Coverage for find_inner_block_attrs.
	 *
	 * @covers ::find_inner_block_attrs
	 *
	 * @return void
	 */
	public function test_find_inner_block_attrs(): void {
		$blocks = array(
			'not a block',
			array(
				'blockName'   => 'core/paragraph',
				'innerBlocks' => 'not blocks',
			),
			array(
				'blockName'   => 'core/group',
				'innerBlocks' => array(
					array( 'blockName' => 'core/heading' ),
					array(
						'blockName' => 'gatherpress/calendar',
						'attrs'     => array( 'viewType' => 'week' ),
					),
				),
			),
		);

		$this->assertSame(
			array( 'viewType' => 'week' ),
			Query_Integration::find_inner_block_attrs( 'gatherpress/calendar', $blocks )
		);
		$this->assertSame( array(), Query_Integration::find_inner_block_attrs( 'core/heading', $blocks ) );
		$this->assertNull( Query_Integration::find_inner_block_attrs( 'core/image', $blocks ) );
	}

	/**
	 * Coverage for allow_core_pagination with Query blocks.
	 *
	 * @covers ::allow_core_pagination
	 * @covers ::paginate_query_block
	 *
	 * @return void
	 */
	public function test_allow_core_pagination_for_query_blocks(): void {
		$instance = Query_Integration::get_instance();
		$plain    = array(
			'blockName'   => 'core/query',
			'attrs'       => array( 'query' => array( 'perPage' => 1 ) ),
			'innerBlocks' => array(),
		);

		$this->assertSame(
			$plain,
			$instance->allow_core_pagination( $plain, $plain ),
			'Failed to assert a plain Query Loop is unchanged.'
		);

		$no_query = $this->get_calendar_query_block( array( 'queryId' => 5 ) );
		$this->assertSame( $no_query, $instance->allow_core_pagination( $no_query, $no_query ) );
		$this->assertSame( array(), $this->get_calendar_query_ids() );

		$_GET['query-5-page'] = '2';
		$block                = $this->get_calendar_query_block(
			array(
				'queryId' => 5,
				'query'   => array( 'perPage' => 1 ),
			)
		);
		$query                = $instance->allow_core_pagination( $block, $block )['attrs']['query'];

		$this->assertTrue( $query[ Query_Integration::CALENDAR_QUERY_PARAM ] );
		$this->assertSame( 'month', $query[ Query_Integration::CALENDAR_QUERY_VIEW_TYPE ] );
		$this->assertSame(
			'2026-11-01',
			$query[ Query_Integration::CALENDAR_QUERY_START_DATE ],
			'Failed to assert page 2 is the next month.'
		);
		$this->assertSame( '2026-11-30', $query[ Query_Integration::CALENDAR_QUERY_END_DATE ] );
		$this->assertSame( 'November 2026', $query[ Query_Integration::CALENDAR_QUERY_HEADING ] );
		$this->assertSame( array( 5 => true ), $this->get_calendar_query_ids() );

		$block = $this->get_calendar_query_block( array( 'query' => array() ) );
		$query = $instance->allow_core_pagination( $block, $block )['attrs']['query'];

		$this->assertSame( '2026-10-01', $query[ Query_Integration::CALENDAR_QUERY_START_DATE ] );
		$this->assertSame(
			array( 5 => true ),
			$this->get_calendar_query_ids(),
			'Failed to assert a Query block without a queryId is not recorded.'
		);
	}

	/**
	 * Coverage for allow_core_pagination with calendar blocks.
	 *
	 * @covers ::allow_core_pagination
	 * @covers ::paginate_calendar_block
	 *
	 * @return void
	 */
	public function test_allow_core_pagination_for_calendar_blocks(): void {
		$instance = Query_Integration::get_instance();
		$calendar = array(
			'blockName' => 'gatherpress/calendar',
			'attrs'     => array( 'viewType' => 'day' ),
		);

		$this->assertSame(
			$calendar,
			$instance->allow_core_pagination( $calendar, $calendar ),
			'Failed to assert no change without a parent.'
		);

		$plain_parent = new WP_Block(
			array(
				'blockName' => 'core/query',
				'attrs'     => array( 'query' => array( 'perPage' => 1 ) ),
			)
		);
		$this->assertSame( $calendar, $instance->allow_core_pagination( $calendar, $calendar, $plain_parent ) );

		$parent = new WP_Block(
			array(
				'blockName' => 'core/query',
				'attrs'     => array(
					'query' => array(
						Query_Integration::CALENDAR_QUERY_START_DATE => '2026-11-01',
						Query_Integration::CALENDAR_QUERY_VIEW_TYPE  => 'week',
					),
				),
			)
		);
		$attrs  = $instance->allow_core_pagination( $calendar, $calendar, $parent )['attrs'];

		$this->assertSame( '2026-11-01', $attrs['selectedDate'] );
		$this->assertSame( 'week', $attrs['viewType'] );

		$parent = new WP_Block(
			array(
				'blockName' => 'core/query',
				'attrs'     => array(
					'query' => array( Query_Integration::CALENDAR_QUERY_START_DATE => '2026-11-01' ),
				),
			)
		);

		$this->assertSame(
			'day',
			$instance->allow_core_pagination( $calendar, $calendar, $parent )['attrs']['viewType'],
			'Failed to assert the view type of the block is kept.'
		);

		$no_attrs = array( 'blockName' => 'gatherpress/calendar' );
		$attrs    = $instance->allow_core_pagination( $no_attrs, $no_attrs, $parent )['attrs'];

		$this->assertSame( 'month', $attrs['viewType'] );

		$parent = new WP_Block(
			array(
				'blockName' => 'core/query',
				'attrs'     => array(
					'query' => array(
						Query_Integration::CALENDAR_QUERY_START_DATE => '2026-11-01',
						Query_Integration::CALENDAR_QUERY_VIEW_TYPE  => 5,
					),
				),
			)
		);

		$this->assertSame(
			'month',
			$instance->allow_core_pagination( $calendar, $calendar, $parent )['attrs']['viewType'],
			'Failed to assert a view type that is not a string becomes month.'
		);
	}

	/**
	 * Coverage for allow_core_pagination with the Next Page block.
	 *
	 * @covers ::allow_core_pagination
	 * @covers ::is_calendar_query_context
	 *
	 * @return void
	 */
	public function test_allow_core_pagination_for_next_page_blocks(): void {
		$instance = Query_Integration::get_instance();
		$next     = array(
			'blockName' => 'core/query-pagination-next',
			'attrs'     => array(),
		);
		$callback = array( $instance, 'force_pagination_max_pages' );
		$plain    = $this->get_block(
			'core/query-pagination',
			array(
				'queryId' => 5,
				'query'   => array( 'perPage' => 1 ),
			)
		);

		$instance->allow_core_pagination( $next, $next );
		$instance->allow_core_pagination( $next, $next, $plain );
		$this->assertFalse(
			has_filter( 'the_posts', $callback ),
			'Failed to assert a plain Query Loop is left alone.'
		);

		$marked = $this->get_block(
			'core/query-pagination',
			array( 'query' => array( Query_Integration::CALENDAR_QUERY_PARAM => true ) )
		);
		$instance->allow_core_pagination( $next, $next, $marked );
		$this->assertSame( 10, has_filter( 'the_posts', $callback ), 'Failed to assert the calendar marker.' );

		remove_filter( 'the_posts', $callback );
		Utility::set_and_get_hidden_property( $instance, 'calendar_query_ids', array( 5 => true ) );

		$instance->allow_core_pagination( $next, $next, $plain );
		$this->assertSame( 10, has_filter( 'the_posts', $callback ), 'Failed to assert the recorded queryId.' );

		$paragraph = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);
		$this->assertSame( $paragraph, $instance->allow_core_pagination( $paragraph, $paragraph, $plain ) );
	}

	/**
	 * Coverage for forget_calendar_query.
	 *
	 * @covers ::forget_calendar_query
	 *
	 * @return void
	 */
	public function test_forget_calendar_query(): void {
		$instance = Query_Integration::get_instance();
		$calendar = array(
			'blockName' => 'core/query',
			'attrs'     => array(
				'queryId' => 5,
				'query'   => array( Query_Integration::CALENDAR_QUERY_PARAM => true ),
			),
		);
		$plain    = array(
			'blockName' => 'core/query',
			'attrs'     => array(
				'queryId' => 5,
				'query'   => array( 'perPage' => 1 ),
			),
		);

		Utility::set_and_get_hidden_property( $instance, 'calendar_query_ids', array( 5 => true ) );

		$this->assertSame( '<div>plain</div>', $instance->forget_calendar_query( '<div>plain</div>', $plain ) );
		$this->assertSame(
			array( 5 => true ),
			$this->get_calendar_query_ids(),
			'Failed to assert a plain Query block does not forget a calendar query.'
		);
		$this->assertSame( '', $instance->forget_calendar_query( '', array( 'blockName' => 'core/query' ) ) );
		$this->assertSame( array( 5 => true ), $this->get_calendar_query_ids() );

		$this->assertSame( '<div>cal</div>', $instance->forget_calendar_query( '<div>cal</div>', $calendar ) );
		$this->assertSame(
			array(),
			$this->get_calendar_query_ids(),
			'Failed to assert the calendar query is forgotten.'
		);
	}

	/**
	 * Coverage for force_pagination_max_pages.
	 *
	 * @covers ::force_pagination_max_pages
	 *
	 * @return void
	 */
	public function test_force_pagination_max_pages(): void {
		$instance = Query_Integration::get_instance();
		$callback = array( $instance, 'force_pagination_max_pages' );
		$query    = new WP_Query();

		$query->max_num_pages = 1;
		$query->query         = array( Query_Integration::CALENDAR_QUERY_PARAM => true );
		add_filter( 'the_posts', $callback, 10, 2 );

		$this->assertSame( array( 3 ), $instance->force_pagination_max_pages( array( 3 ), $query ) );
		$this->assertSame( Query_Integration::MAX_PAGINATION_PAGES, $query->max_num_pages );
		$this->assertFalse( has_filter( 'the_posts', $callback ), 'Failed to assert the filter runs once.' );

		$query->max_num_pages = 1;
		$query->query         = array();
		add_filter( 'the_posts', $callback, 10, 2 );
		$instance->force_pagination_max_pages( array(), $query );

		$this->assertSame( 1, $query->max_num_pages, 'Failed to assert another query keeps its pages.' );
		$this->assertFalse(
			has_filter( 'the_posts', $callback ),
			'Failed to assert the filter is removed by any query.'
		);
	}

	/**
	 * Coverage for disable_query_pagination_numbers.
	 *
	 * @covers ::disable_query_pagination_numbers
	 *
	 * @return void
	 */
	public function test_disable_query_pagination_numbers(): void {
		$instance  = Query_Integration::get_instance();
		$numbers   = array( 'blockName' => 'core/query-pagination-numbers' );
		$paragraph = array( 'blockName' => 'core/paragraph' );
		$marked    = array(
			'queryId' => 5,
			'query'   => array(
				'perPage'                               => 3,
				Query_Integration::CALENDAR_QUERY_PARAM => true,
			),
		);

		$this->assertSame( $marked, $instance->disable_query_pagination_numbers( $marked, $paragraph ) );
		$this->assertSame( 0, $instance->disable_query_pagination_numbers( $marked, $numbers )['query']['perPage'] );

		$plain = array(
			'queryId' => 5,
			'query'   => array( 'perPage' => 3 ),
		);
		$this->assertSame(
			$plain,
			$instance->disable_query_pagination_numbers( $plain, $numbers ),
			'Failed to assert a plain Query Loop keeps its numbers.'
		);

		Utility::set_and_get_hidden_property( $instance, 'calendar_query_ids', array( 5 => true ) );

		$no_query = array( 'queryId' => 5 );
		$this->assertSame( 0, $instance->disable_query_pagination_numbers( $plain, $numbers )['query']['perPage'] );
		$this->assertSame( $no_query, $instance->disable_query_pagination_numbers( $no_query, $numbers ) );
	}

	/**
	 * Coverage for query_vars.
	 *
	 * @covers ::query_vars
	 *
	 * @return void
	 */
	public function test_query_vars(): void {
		$this->assertSame(
			array(
				'existing',
				Query_Integration::CALENDAR_QUERY_PARAM,
				Query_Integration::CALENDAR_QUERY_VIEW_TYPE,
				Query_Integration::CALENDAR_QUERY_START_DATE,
				Query_Integration::CALENDAR_QUERY_END_DATE,
			),
			Query_Integration::get_instance()->query_vars( array( 'existing' ) )
		);
	}

	/**
	 * Coverage for query_loop_block_query_vars.
	 *
	 * @covers ::query_loop_block_query_vars
	 * @covers ::is_calendar_query_context
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars(): void {
		$instance = Query_Integration::get_instance();
		$query    = array( 'post_type' => 'post' );
		$block    = $this->get_block(
			'core/post-template',
			array(
				'queryId' => 5,
				'query'   => array( 'perPage' => 3 ),
			)
		);

		$this->assertSame(
			$query,
			$instance->query_loop_block_query_vars( $query, $block ),
			'Failed to assert a plain Query Loop is unchanged.'
		);

		Utility::set_and_get_hidden_property( $instance, 'calendar_query_ids', array( 5 => true ) );

		$received = null;
		$callback = static function ( $query_args, $block_query ) use ( &$received ) {
			$received = $block_query;

			return array_merge( $query_args, array( 'extra' => 'value' ) );
		};
		add_filter( 'gatherpress_query_vars', $callback, 99, 2 );

		$result = $instance->query_loop_block_query_vars( $query, $block );

		remove_filter( 'gatherpress_query_vars', $callback, 99 );

		$this->assertSame( 'post', $result['post_type'] );
		$this->assertTrue( $result[ Query_Integration::CALENDAR_QUERY_PARAM ] );
		$this->assertSame( 'value', $result['extra'] );
		$this->assertSame( array( 'perPage' => 3 ), $received, 'Failed to assert the filter gets the block query.' );

		$broken = static function () {
			return 'not an array';
		};
		add_filter( 'gatherpress_query_vars', $broken, 99 );

		$no_query_block = $this->get_block( 'core/post-template', array( 'queryId' => 5 ) );
		$result         = $instance->query_loop_block_query_vars( $query, $no_query_block );

		remove_filter( 'gatherpress_query_vars', $broken, 99 );

		$this->assertSame(
			array(
				'post_type'                             => 'post',
				Query_Integration::CALENDAR_QUERY_PARAM => true,
			),
			$result
		);
	}

	/**
	 * A plain Query Loop after a calendar keeps its own pagination.
	 *
	 * The calendar's Query block is inside a Group, so its pagination blocks get their
	 * `query` context from attributes that WordPress read before `render_block_data` ran.
	 * The last plain Query Loop has the same queryId as the calendar, as in an archive that
	 * shows the content of several posts.
	 *
	 * @covers ::allow_core_pagination
	 * @covers ::forget_calendar_query
	 * @covers ::query_loop_block_query_vars
	 * @covers ::force_pagination_max_pages
	 *
	 * @return void
	 */
	public function test_rendered_pagination(): void {
		// Both Query Loops search for this one post, so a plain Query Loop has 1 page.
		$this->mock->post( array( 'post_title' => 'Zyxcalendarpagination' ) )->get();

		$query   = '"query":{"perPage":1,"postType":"post","search":"Zyxcalendarpagination","inherit":false}';
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:query {"queryId":8,' . $query . '} -->'
			. '<div class="wp-block-query"><!-- wp:gatherpress/calendar {"selectedDate":"2026-10-15"} /-->'
			. '<!-- wp:query-pagination --><!-- wp:query-pagination-next /--><!-- /wp:query-pagination -->'
			. '</div><!-- /wp:query --></div><!-- /wp:group -->'
			. '<!-- wp:query {"queryId":7,' . $query . '} -->'
			. '<div class="wp-block-query">'
			. '<!-- wp:query-pagination --><!-- wp:query-pagination-next /--><!-- /wp:query-pagination -->'
			. '</div><!-- /wp:query -->'
			. '<!-- wp:query {"queryId":8,' . $query . '} -->'
			. '<div class="wp-block-query">'
			. '<!-- wp:query-pagination --><!-- wp:query-pagination-next /--><!-- /wp:query-pagination -->'
			. '</div><!-- /wp:query -->';
		$output  = do_blocks( $content );

		$this->assertSame(
			1,
			substr_count( $output, 'query-8-page=2' ),
			'Failed to assert only the calendar with queryId 8 links to the next month.'
		);
		$this->assertStringNotContainsString(
			'query-7-page=2',
			$output,
			'Failed to assert the plain Query Loop has no next page.'
		);
		$this->assertSame(
			array(),
			$this->get_calendar_query_ids(),
			'Failed to assert no queryId is left after rendering.'
		);
		$this->assertFalse(
			has_filter( 'the_posts', array( Query_Integration::get_instance(), 'force_pagination_max_pages' ) ),
			'Failed to assert no filter is left after rendering.'
		);
	}
}
