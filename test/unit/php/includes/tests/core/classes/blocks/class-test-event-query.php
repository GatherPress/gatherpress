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
				'type'     => 'filter',
				'name'     => sprintf( 'rest_%s_query', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'rest_query' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'rest_%s_collection_params', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'rest_collection_params' ),
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
			array( 'gatherpress_include_unfinished', 0 ), // Integer 0 - the critical test case.
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
					'gatherpress_event_query'        => 'past',
					'gatherpress_include_unfinished' => 0,
					'orderby'                        => 'datetime',
				)
			);

		// Mock the apply_filters call.
		add_filter(
			'gatherpress_query_vars',
			function ( $custom_args ) {
				// Ensure the integer 0 value is preserved through the filter.
				$this->assertArrayHasKey( 'gatherpress_include_unfinished', $custom_args );
				$this->assertSame( 0, $custom_args['gatherpress_include_unfinished'] );
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
		$this->assertArrayHasKey( 'gatherpress_include_unfinished', $result );
		$this->assertSame( 0, $result['gatherpress_include_unfinished'] );

		// Verify other expected values.
		$this->assertSame( 'past', $result['gatherpress_event_query'] );
		$this->assertSame( 'datetime', $result['orderby'] );

		// Clean up filter.
		remove_all_filters( 'gatherpress_query_vars' );
	}

	/**
	 * Test that REST API collection params include gatherpress_include_unfinished.
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

		// Verify gatherpress_include_unfinished parameter is registered.
		$this->assertArrayHasKey( 'gatherpress_include_unfinished', $result );
		$this->assertSame( 'integer', $result['gatherpress_include_unfinished']['type'] );
		$this->assertSame( array( 0, 1 ), $result['gatherpress_include_unfinished']['enum'] );

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
}
