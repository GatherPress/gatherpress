<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Event_Query.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Event_Query;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Event_Query
 */
class Test_Event_Query extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for Event_Query.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Event_Query::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'pre_render_block',
				'priority' => 10,
				'callback' => array( $instance, 'pre_render_block' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 99,
				'callback' => array( $instance, 'register_event_date_rest_hooks' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'aql_query_vars',
				'priority' => 10,
				'callback' => array( $instance, 'aql_query_vars' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test that array_filter preserves integer 0 values in REST API.
	 *
	 * This test specifically prevents regression of the array_filter bug that
	 * removed integer 0 values from filtered query args.
	 *
	 * @since 1.0.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_preserves_zero_values(): void {
		$instance = Event_Query::get_instance();

		// Create mock WP_REST_Request.
		$request = $this->createMock( \WP_REST_Request::class );

		// Set up parameter map for get_param calls.
		$param_map = array(
			array( 'gatherpress_event_query', 'past' ),
			array( 'include_unfinished', 0 ), // Integer 0 - the critical test case.
			array( 'exclude_current', null ),
			array( 'orderby', 'datetime' ),
		);

		$request->expects( $this->exactly( 4 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn(
				array(
					'gatherpress_event_query' => 'past',
					'include_unfinished'      => 0,
					'orderby'                 => 'datetime',
				)
			);

		// Mock the apply_filters call.
		add_filter(
			'gatherpress_query_vars',
			function ( $custom_args ) {
				// Ensure the integer 0 value is preserved through the filter.
				$this->assertArrayHasKey( 'include_unfinished', $custom_args );
				$this->assertSame( 0, $custom_args['include_unfinished'] );
				return $custom_args;
			},
			10,
			1
		);

		$initial_args = array(
			'post_type'      => Event::POST_TYPE,
			'posts_per_page' => 10,
		);

		$result = $instance->rest_query( $initial_args, $request );

		// Verify that integer 0 value survives array_filter and is in final result.
		$this->assertArrayHasKey( 'include_unfinished', $result );
		$this->assertSame( 0, $result['include_unfinished'] );

		// Verify other expected values.
		$this->assertSame( 'past', $result['gatherpress_event_query'] );
		$this->assertSame( 'datetime', $result['orderby'] );

		// Clean up filter.
		remove_all_filters( 'gatherpress_query_vars' );
	}

	/**
	 * Test that REST API collection params include include_unfinished.
	 *
	 * @since 1.0.0
	 * @covers ::rest_collection_params
	 *
	 * @return void
	 */
	public function test_rest_collection_params_includes_gatherpress_parameters(): void {
		$instance = Event_Query::get_instance();

		$base_params = array(
			'orderby' => array(
				'enum' => array( 'date', 'title' ),
			),
		);

		$result = $instance->rest_collection_params( $base_params );

		// Verify include_unfinished parameter is registered.
		$this->assertArrayHasKey( 'include_unfinished', $result );
		$this->assertSame( 'integer', $result['include_unfinished']['type'] );
		$this->assertSame( array( 0, 1 ), $result['include_unfinished']['enum'] );

		// Verify gatherpress_event_query parameter is registered.
		$this->assertArrayHasKey( 'gatherpress_event_query', $result );
		$this->assertSame( 'string', $result['gatherpress_event_query']['type'] );
		$this->assertSame( array( 'upcoming', 'past' ), $result['gatherpress_event_query']['enum'] );

		// Verify exclude_current parameter is registered.
		$this->assertArrayHasKey( 'exclude_current', $result );
		$this->assertSame( 'integer', $result['exclude_current']['type'] );

		// Verify original orderby enum is preserved and extended.
		$this->assertContains( 'date', $result['orderby']['enum'] );
		$this->assertContains( 'title', $result['orderby']['enum'] );
		$this->assertContains( 'rand', $result['orderby']['enum'] );
		$this->assertContains( 'datetime', $result['orderby']['enum'] );
	}

	/**
	 * Test pre_render_block returns null for non-event-query blocks.
	 *
	 * @since 1.0.0
	 * @covers ::pre_render_block
	 *
	 * @return void
	 */
	public function test_pre_render_block_non_event_query(): void {
		$instance = Event_Query::get_instance();

		$parsed_block = array(
			'attrs' => array(
				'namespace' => 'some-other-block',
			),
		);

		$result = $instance->pre_render_block( null, $parsed_block );

		$this->assertNull( $result, 'Should return null for non-event-query blocks.' );
	}

	/**
	 * Test pre_render_block returns null for blocks without namespace.
	 *
	 * @since 1.0.0
	 * @covers ::pre_render_block
	 *
	 * @return void
	 */
	public function test_pre_render_block_no_namespace(): void {
		$instance = Event_Query::get_instance();

		$parsed_block = array(
			'attrs' => array(),
		);

		$result = $instance->pre_render_block( null, $parsed_block );

		$this->assertNull( $result, 'Should return null for blocks without namespace.' );
	}

	/**
	 * Test pre_render_block with inherit query modifies global wp_query.
	 *
	 * @since 1.0.0
	 * @covers ::pre_render_block
	 *
	 * @return void
	 */
	public function test_pre_render_block_with_inherit_query(): void {
		global $wp_query;

		$instance = Event_Query::get_instance();

		// Store original wp_query.
		$original_query = $wp_query;

		$parsed_block = array(
			'attrs' => array(
				'namespace' => Event_Query::BLOCK_NAME,
				'query'     => array(
					'inherit' => true,
					'perPage' => 5,
					'order'   => 'DESC',
					'orderBy' => 'date',
				),
			),
		);

		$result = $instance->pre_render_block( null, $parsed_block );

		// Should return null.
		$this->assertNull( $result );

		// Verify wp_query was modified.
		$this->assertNotSame( $original_query, $wp_query, 'Global wp_query should be modified.' );
		$this->assertSame( 5, $wp_query->query_vars['posts_per_page'] );
		$this->assertSame( 'DESC', $wp_query->query_vars['order'] );
		$this->assertSame( 'date', $wp_query->query_vars['orderby'] );

		// Restore original wp_query.
		$wp_query = $original_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Test pre_render_block without inherit query adds filter.
	 *
	 * @since 1.0.0
	 * @covers ::pre_render_block
	 *
	 * @return void
	 */
	public function test_pre_render_block_without_inherit_query(): void {
		$instance = Event_Query::get_instance();

		// Remove existing filter first to get clean state.
		remove_all_filters( 'query_loop_block_query_vars' );

		$parsed_block = array(
			'attrs' => array(
				'namespace' => Event_Query::BLOCK_NAME,
				'query'     => array(
					'inherit' => false,
					'perPage' => 10,
				),
			),
		);

		$result = $instance->pre_render_block( null, $parsed_block );

		// Should return null.
		$this->assertNull( $result );

		// Verify filter was added.
		$this->assertTrue(
			has_filter( 'query_loop_block_query_vars', array( $instance, 'query_loop_block_query_vars' ) ) !== false,
			'Filter should be added when inherit is false.'
		);

		// Clean up.
		remove_filter( 'query_loop_block_query_vars', array( $instance, 'query_loop_block_query_vars' ) );
	}

	/**
	 * Test query_loop_block_query_vars returns unchanged query for non-array block query.
	 *
	 * @since 1.0.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_non_array_context(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 10 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'query' => 'not-an-array',
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame( $query, $result, 'Should return unchanged query for non-array context.' );
	}

	/**
	 * Test query_loop_block_query_vars returns unchanged query without gatherpress_event_query.
	 *
	 * @since 1.0.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_no_event_query(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 10 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'query' => array(
				'perPage' => 5,
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame( $query, $result, 'Should return unchanged query without gatherpress_event_query.' );
	}

	/**
	 * Test query_loop_block_query_vars builds proper query args.
	 *
	 * @since 1.0.0
	 * @covers ::query_loop_block_query_vars
	 * @covers ::get_exclude_ids
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_with_event_query(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 10 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'query' => array(
				'gatherpress_event_query' => 'upcoming',
				'exclude_current'         => 123,
				'include_unfinished'      => 1,
				'orderBy'                 => 'datetime',
				'order'                   => 'asc',
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame( array( Event::POST_TYPE ), $result['post_type'] );
		$this->assertSame( 'upcoming', $result['gatherpress_event_query'] );
		$this->assertContains( 123, $result['post__not_in'] );
		$this->assertSame( 1, $result['include_unfinished'] );
		$this->assertSame( array( 'datetime' ), $result['orderby'] );
		$this->assertSame( 'ASC', $result['order'] );
	}

	/**
	 * Test query_loop_block_query_vars with DESC order.
	 *
	 * @since 1.0.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_desc_order(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 10 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'query' => array(
				'gatherpress_event_query' => 'past',
				'orderBy'                 => 'date',
				'order'                   => 'desc',
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame( 'DESC', $result['order'] );
	}

	/**
	 * Test query_loop_block_query_vars without exclude_current.
	 *
	 * @since 1.0.0
	 * @covers ::query_loop_block_query_vars
	 * @covers ::get_exclude_ids
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_no_exclusions(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 10 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'query' => array(
				'gatherpress_event_query' => 'upcoming',
				'orderBy'                 => 'datetime',
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertArrayNotHasKey( 'post__not_in', $result, 'Should not have post__not_in when no exclusions.' );
	}

	/**
	 * Test rest_query with exclude_current parameter.
	 *
	 * @since 1.0.0
	 * @covers ::rest_query
	 * @covers ::get_exclude_ids
	 *
	 * @return void
	 */
	public function test_rest_query_with_exclude_current(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'gatherpress_event_query', 'upcoming' ),
			array( 'exclude_current', 456 ),
			array( 'include_unfinished', null ),
			array( 'orderby', 'datetime' ),
		);

		$request->expects( $this->exactly( 4 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn(
				array(
					'gatherpress_event_query' => 'upcoming',
					'exclude_current'         => 456,
					'orderby'                 => 'datetime',
				)
			);

		$initial_args = array(
			'post_type' => Event::POST_TYPE,
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertContains( 456, $result['post__not_in'] );
		$this->assertSame( 'upcoming', $result['gatherpress_event_query'] );
	}

	/**
	 * Test rest_query without exclude_current parameter.
	 *
	 * @since 1.0.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_without_exclude_current(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'gatherpress_event_query', 'past' ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', 1 ),
			array( 'orderby', 'date' ),
		);

		$request->expects( $this->exactly( 4 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn(
				array(
					'gatherpress_event_query' => 'past',
					'include_unfinished'      => 1,
					'orderby'                 => 'date',
				)
			);

		$initial_args = array(
			'post_type' => Event::POST_TYPE,
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertArrayNotHasKey(
			'post__not_in',
			$result,
			'Should not have post__not_in when exclude_current is null.'
		);
		$this->assertSame( 1, $result['include_unfinished'] );
	}

	/**
	 * Test rest_query filters out null and empty values.
	 *
	 * @since 1.0.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_filters_null_values(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'gatherpress_event_query', null ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', null ),
			array( 'orderby', null ),
		);

		$request->expects( $this->exactly( 4 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn( array() );

		$initial_args = array(
			'post_type' => Event::POST_TYPE,
		);

		$result = $instance->rest_query( $initial_args, $request );

		// Should only contain initial args since all params are null.
		$this->assertSame( Event::POST_TYPE, $result['post_type'] );
		$this->assertArrayNotHasKey( 'gatherpress_event_query', $result );
		$this->assertArrayNotHasKey( 'include_unfinished', $result );
		$this->assertArrayNotHasKey( 'orderby', $result );
	}

	/**
	 * Test aql_query_vars returns unchanged query for non-event post types.
	 *
	 * @since 1.0.0
	 * @covers ::aql_query_vars
	 *
	 * @return void
	 */
	public function test_aql_query_vars_non_event_post_type(): void {
		$instance = Event_Query::get_instance();

		$query_args  = array( 'posts_per_page' => 10 );
		$block_query = array( 'postType' => 'post' );

		$result = $instance->aql_query_vars( $query_args, $block_query, false );

		$this->assertSame( $query_args, $result, 'Should return unchanged query for non-event post types.' );
	}

	/**
	 * Test aql_query_vars returns unchanged query when postType is missing.
	 *
	 * @since 1.0.0
	 * @covers ::aql_query_vars
	 *
	 * @return void
	 */
	public function test_aql_query_vars_missing_post_type(): void {
		$instance = Event_Query::get_instance();

		$query_args  = array( 'posts_per_page' => 10 );
		$block_query = array();

		$result = $instance->aql_query_vars( $query_args, $block_query, false );

		$this->assertSame( $query_args, $result, 'Should return unchanged query when postType is missing.' );
	}

	/**
	 * Test aql_query_vars passes through all GatherPress event params.
	 *
	 * @since 1.0.0
	 * @covers ::aql_query_vars
	 *
	 * @return void
	 */
	public function test_aql_query_vars_with_all_params(): void {
		$instance = Event_Query::get_instance();

		$query_args  = array( 'posts_per_page' => 10 );
		$block_query = array(
			'postType'                => 'gatherpress_event',
			'gatherpress_event_query' => 'upcoming',
			'include_unfinished'      => 1,
			'orderBy'                 => 'datetime',
			'order'                   => 'asc',
		);

		$result = $instance->aql_query_vars( $query_args, $block_query, false );

		$this->assertSame( 'upcoming', $result['gatherpress_event_query'], 'Should pass through event query type.' );
		$this->assertSame( 1, $result['include_unfinished'], 'Should pass through include_unfinished.' );
		$this->assertSame( array( 'datetime' ), $result['orderby'], 'Should pass through orderBy as array.' );
		$this->assertSame( 'ASC', $result['order'], 'Should uppercase the order direction.' );
		$this->assertSame( 10, $result['posts_per_page'], 'Should preserve original query args.' );
	}

	/**
	 * Test aql_query_vars with past events and descending order.
	 *
	 * @since 1.0.0
	 * @covers ::aql_query_vars
	 *
	 * @return void
	 */
	public function test_aql_query_vars_past_events(): void {
		$instance = Event_Query::get_instance();

		$query_args  = array( 'posts_per_page' => 5 );
		$block_query = array(
			'postType'                => 'gatherpress_event',
			'gatherpress_event_query' => 'past',
			'include_unfinished'      => 0,
			'orderBy'                 => 'datetime',
			'order'                   => 'desc',
		);

		$result = $instance->aql_query_vars( $query_args, $block_query, false );

		$this->assertSame( 'past', $result['gatherpress_event_query'], 'Should pass through past event query type.' );
		$this->assertSame( 0, $result['include_unfinished'], 'Should preserve integer 0 value.' );
		$this->assertSame( 'DESC', $result['order'], 'Should uppercase DESC.' );
	}

	/**
	 * Test aql_query_vars with minimal params only passes set values.
	 *
	 * @since 1.0.0
	 * @covers ::aql_query_vars
	 *
	 * @return void
	 */
	public function test_aql_query_vars_minimal_params(): void {
		$instance = Event_Query::get_instance();

		$query_args  = array( 'posts_per_page' => 10 );
		$block_query = array(
			'postType' => 'gatherpress_event',
		);

		$result = $instance->aql_query_vars( $query_args, $block_query, false );

		$this->assertArrayNotHasKey( 'gatherpress_event_query', $result, 'Should not set empty event query type.' );
		$this->assertArrayNotHasKey( 'include_unfinished', $result, 'Should not set unset include_unfinished.' );
		$this->assertArrayNotHasKey( 'orderby', $result, 'Should not set empty orderBy.' );
		$this->assertArrayNotHasKey( 'order', $result, 'Should not set empty order.' );
	}

	/**
	 * Test aql_query_vars with inherited query parameter.
	 *
	 * @since 1.0.0
	 * @covers ::aql_query_vars
	 *
	 * @return void
	 */
	public function test_aql_query_vars_inherited(): void {
		$instance = Event_Query::get_instance();

		$query_args  = array( 'posts_per_page' => 10 );
		$block_query = array(
			'postType'                => 'gatherpress_event',
			'gatherpress_event_query' => 'upcoming',
		);

		// The $inherited parameter is accepted but not used.
		$result = $instance->aql_query_vars( $query_args, $block_query, true );

		$this->assertSame( 'upcoming', $result['gatherpress_event_query'], 'Should work with inherited flag true.' );
	}

	/**
	 * Test rest_query filters out empty string values.
	 *
	 * @since 1.0.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_filters_empty_strings(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'gatherpress_event_query', '' ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', null ),
			array( 'orderby', '' ),
		);

		$request->expects( $this->exactly( 4 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn( array() );

		$initial_args = array(
			'post_type' => Event::POST_TYPE,
		);

		$result = $instance->rest_query( $initial_args, $request );

		// Empty strings should be filtered out.
		$this->assertArrayNotHasKey( 'gatherpress_event_query', $result );
		$this->assertArrayNotHasKey( 'orderby', $result );
	}
}
