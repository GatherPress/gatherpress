<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Event_Query.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Event_Query;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Event_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Event_Query
 */
class Test_Event_Query extends Base {

	/**
	 * Reset the per-request event-query type map between tests.
	 *
	 * The map is request-scoped singleton state in production, but the
	 * singleton persists across test methods, so clear it to keep the
	 * `query_loop_block_query_vars` tests isolated from each other.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Utility::set_and_get_hidden_property( Event_Query::get_instance(), 'event_query_types', array() );
	}

	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for Event_Query.
	 *
	 * @since 0.33.0
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
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_register_event_date_rest_hooks' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => PHP_INT_MAX,
				'callback' => array( $instance, 'register_existing_event_date_post_types' ),
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
	 * Coverage for maybe_register_event_date_rest_hooks method.
	 *
	 * Verifies that REST filters are registered when a post type declares
	 * gatherpress-event-date support and skipped otherwise.
	 *
	 * @since 0.34.0
	 * @covers ::maybe_register_event_date_rest_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_event_date_rest_hooks(): void {
		$instance = Event_Query::get_instance();

		// Remove any existing filters first.
		remove_all_filters( sprintf( 'rest_%s_query', Event::POST_TYPE ) );
		remove_all_filters( sprintf( 'rest_%s_collection_params', Event::POST_TYPE ) );

		$instance->maybe_register_event_date_rest_hooks( Event::POST_TYPE );

		$this->assertSame(
			10,
			has_filter(
				sprintf( 'rest_%s_query', Event::POST_TYPE ),
				array( $instance, 'rest_query' )
			),
			'Failed to assert rest_query filter is registered for event post type.'
		);

		$this->assertSame(
			10,
			has_filter(
				sprintf( 'rest_%s_collection_params', Event::POST_TYPE ),
				array( $instance, 'rest_collection_params' )
			),
			'Failed to assert rest_collection_params filter is registered for event post type.'
		);
	}

	/**
	 * Bails when the post type does not declare event-date support.
	 *
	 * @since 0.34.0
	 * @covers ::maybe_register_event_date_rest_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_event_date_rest_hooks_skips_unsupported_post_type(): void {
		$instance = Event_Query::get_instance();

		$instance->maybe_register_event_date_rest_hooks( 'post' );

		$this->assertFalse(
			has_filter( 'rest_post_query', array( $instance, 'rest_query' ) ),
			'Failed to assert that no REST filters are registered for a post type without event-date support.'
		);
	}

	/**
	 * Sweep registers REST filters for every event-supporting post type
	 * already in the registry.
	 *
	 * Reproduces #1608: post types registered by other plugins before
	 * `Event_Query` boots (e.g. `gatherpress-productions`) miss the
	 * `registered_post_type` listener, so without this sweep their REST
	 * endpoints lack `orderby=datetime`, `gatherpress_event_query`, and
	 * `include_unfinished` and the editor 400s when the Query Loop block
	 * tries to fetch them.
	 *
	 * @since 0.34.0
	 * @covers ::register_existing_event_date_post_types
	 *
	 * @return void
	 */
	public function test_register_existing_event_date_post_types_sweeps_registry(): void {
		$instance  = Event_Query::get_instance();
		$post_type = 'shindig';

		register_post_type(
			$post_type,
			array(
				'label'    => 'Test Productions',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		// Strip any filters so we observe the sweep installing them, not
		// a pre-existing registration from the `registered_post_type`
		// listener that fired during `register_post_type()`.
		remove_all_filters( sprintf( 'rest_%s_query', $post_type ) );
		remove_all_filters( sprintf( 'rest_%s_collection_params', $post_type ) );

		$instance->register_existing_event_date_post_types();

		$this->assertSame(
			10,
			has_filter(
				sprintf( 'rest_%s_query', $post_type ),
				array( $instance, 'rest_query' )
			),
			'Sweep should install rest_query filter for every event-supporting post type.'
		);
		$this->assertSame(
			10,
			has_filter(
				sprintf( 'rest_%s_collection_params', $post_type ),
				array( $instance, 'rest_collection_params' )
			),
			'Sweep should install rest_collection_params filter for every event-supporting post type.'
		);

		unregister_post_type( $post_type );
	}

	/**
	 * Test that array_filter preserves integer 0 values in REST API.
	 *
	 * This test specifically prevents regression of the array_filter bug that
	 * removed integer 0 values from filtered query args.
	 *
	 * @since 0.33.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_preserves_zero_values(): void {
		$instance = Event_Query::get_instance();

		// Create mock WP_REST_Request.
		$request = $this->createMock( \WP_REST_Request::class );

		// Event post type reads six event-only params; shadow-source-only
		// params are not queried when the requested post type is not a
		// shadow source.
		$param_map = array(
			array( 'include', null ),
			array( 'gatherpress_event_query', 'past' ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', 0 ), // Integer 0 - the critical test case.
			array( 'orderby', 'datetime' ),
			array( 'shadow_filter', null ),
		);

		$request->expects( $this->exactly( 6 ) )
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
	 * Collection params for an event-date post type include the event-only
	 * params and exclude the shadow-source-only activity params.
	 *
	 * @since 0.33.0
	 * @covers ::add_gatherpress_collection_params
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

		$result = $instance->add_gatherpress_collection_params( $base_params, Event::POST_TYPE );

		// Verify include_unfinished parameter is registered.
		$this->assertArrayHasKey( 'include_unfinished', $result );
		$this->assertSame( 'integer', $result['include_unfinished']['type'] );
		$this->assertSame( array( 0, 1 ), $result['include_unfinished']['enum'] );

		// Verify gatherpress_event_query parameter is registered.
		$this->assertArrayHasKey( 'gatherpress_event_query', $result );
		$this->assertSame( 'string', $result['gatherpress_event_query']['type'] );
		$this->assertSame( array( 'upcoming', 'past', 'all' ), $result['gatherpress_event_query']['enum'] );

		// Verify exclude_current parameter is registered.
		$this->assertArrayHasKey( 'exclude_current', $result );
		$this->assertSame( 'integer', $result['exclude_current']['type'] );

		// Shadow-source activity params must not leak into an event schema
		// because the matching REST query would 400 on them.
		$this->assertArrayNotHasKey( 'has_events_filter', $result );
		$this->assertArrayNotHasKey( 'upcoming_events_only', $result );

		// Verify original orderby enum is preserved and extended.
		$this->assertContains( 'date', $result['orderby']['enum'] );
		$this->assertContains( 'title', $result['orderby']['enum'] );
		$this->assertContains( 'rand', $result['orderby']['enum'] );
		$this->assertContains( 'datetime', $result['orderby']['enum'] );

		// Regression for #1753: the post-ID params must coerce a non-numeric
		// value to 0 rather than rejecting it. Inside a block template the
		// editor preview sends the template identifier (e.g.
		// "twentytwentyfive//single-gatherpress_event") for these, and a strict
		// integer rejection 400s the request and leaves the Query Loop spinning.
		$template_id = 'twentytwentyfive//single-gatherpress_event';
		$this->assertSame(
			'absint',
			$result['exclude_current']['sanitize_callback'],
			'exclude_current should sanitize to a non-negative int.'
		);
		$this->assertTrue(
			call_user_func( $result['exclude_current']['validate_callback'], $template_id ),
			'exclude_current should accept a non-numeric template identifier (no 400).'
		);
		$this->assertSame(
			0,
			call_user_func( $result['exclude_current']['sanitize_callback'], $template_id ),
			'exclude_current should coerce a non-numeric template identifier to 0 (no context).'
		);
	}

	/**
	 * Collection params for a shadow-source post type include the
	 * activity-only params and exclude the event-only params.
	 *
	 * @since 0.36.0
	 * @covers ::add_gatherpress_collection_params
	 *
	 * @return void
	 */
	public function test_rest_collection_params_for_shadow_source_post_type(): void {
		$instance = Event_Query::get_instance();

		$base_params = array(
			'orderby' => array(
				'enum' => array( 'date', 'title' ),
			),
		);

		$result = $instance->add_gatherpress_collection_params( $base_params, 'gatherpress_venue' );

		$this->assertArrayHasKey( 'has_events_filter', $result );
		$this->assertSame( 'integer', $result['has_events_filter']['type'] );
		$this->assertSame( array( 0, 1 ), $result['has_events_filter']['enum'] );

		$this->assertArrayHasKey( 'upcoming_events_only', $result );
		$this->assertSame( 'integer', $result['upcoming_events_only']['type'] );
		$this->assertSame( array( 0, 1 ), $result['upcoming_events_only']['enum'] );
		$this->assertSame( 1, $result['upcoming_events_only']['default'], 'Upcoming-only should default to on.' );

		$this->assertArrayHasKey( 'gatherpress_shadow_source_post_id', $result );
		$this->assertArrayHasKey( 'gatherpress_shadow_source_post_type', $result );

		// Event-only params must not leak into a shadow-source schema.
		$this->assertArrayNotHasKey( 'include_unfinished', $result );
		$this->assertArrayNotHasKey( 'gatherpress_event_query', $result );
		$this->assertArrayNotHasKey( 'exclude_current', $result );
		$this->assertArrayNotHasKey( 'shadow_filter', $result );
		$this->assertNotContains( 'datetime', $result['orderby']['enum'] );
		$this->assertNotContains( 'rand', $result['orderby']['enum'] );
	}

	/**
	 * Collection params for an unrelated post type are returned unchanged.
	 *
	 * @since 0.36.0
	 * @covers ::add_gatherpress_collection_params
	 *
	 * @return void
	 */
	public function test_rest_collection_params_for_unrelated_post_type(): void {
		$instance = Event_Query::get_instance();

		$base_params = array(
			'orderby' => array(
				'enum' => array( 'date', 'title' ),
			),
		);

		$result = $instance->add_gatherpress_collection_params( $base_params, 'post' );

		$this->assertSame( $base_params, $result );
	}

	/**
	 * Test rest_collection_params derives the post type from the live WP filter name.
	 *
	 * Guards the dynamic callback path (current_filter()) which the
	 * add_gatherpress_collection_params tests bypass — a future edit that
	 * mistypes the substr offsets would strip every GatherPress param from
	 * every registered post type's REST schema, and the unrelated test
	 * alone would not catch it.
	 *
	 * @since 0.36.0
	 * @covers ::rest_collection_params
	 *
	 * @return void
	 */
	public function test_rest_collection_params_derives_post_type_from_current_filter(): void {
		$instance = Event_Query::get_instance();

		$base_params = array( 'orderby' => array( 'enum' => array( 'date' ) ) );

		// Drive the WP filter directly so current_filter() resolves to
		// 'rest_gatherpress_venue_collection_params' inside the callback.
		$result = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Test the exact REST hook name used by WordPress.
			'rest_gatherpress_venue_collection_params',
			$base_params
		);

		// Shadow-source post types should expose the activity-filter params
		// (has_events_filter, upcoming_events_only) and the editor-preview
		// context, but NOT the event-only orderby enum members.
		$this->assertArrayHasKey( 'has_events_filter', $result );
		$this->assertArrayHasKey( 'upcoming_events_only', $result );
		$this->assertNotContains( 'datetime', $result['orderby']['enum'] );
		$this->assertNotContains( 'rand', $result['orderby']['enum'] );

		// And the same callback for an event-date post type should add the
		// event-only orderby enum members and shadow_filter, not the
		// activity-filter params.
		$event_result = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Test the exact REST hook name used by WordPress.
			sprintf( 'rest_%s_collection_params', Event::POST_TYPE ),
			array( 'orderby' => array( 'enum' => array( 'date' ) ) )
		);

		$this->assertContains( 'datetime', $event_result['orderby']['enum'] );
		$this->assertContains( 'rand', $event_result['orderby']['enum'] );
		$this->assertArrayHasKey( 'shadow_filter', $event_result );
		$this->assertArrayNotHasKey( 'has_events_filter', $event_result );
	}

	/**
	 * Test pre_render_block returns null for non-event-query blocks.
	 *
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * Test pre_render_block records the default 'upcoming' type for a block
	 * whose saved query omits gatherpress_event_query.
	 *
	 * Regression coverage for #1806: such a block rendered every event on the
	 * front end because no event type reached the query. The recorded default
	 * is what lets the front-end filter scope it to upcoming events.
	 *
	 * @since 0.34.0
	 * @covers ::pre_render_block
	 *
	 * @return void
	 */
	public function test_pre_render_block_records_default_event_query_type(): void {
		$instance = Event_Query::get_instance();

		remove_all_filters( 'query_loop_block_query_vars' );

		$parsed_block = array(
			'attrs' => array(
				'namespace' => Event_Query::BLOCK_NAME,
				'queryId'   => 6,
				'query'     => array(
					'inherit'  => false,
					'postType' => Event::POST_TYPE,
					'order'    => 'desc',
					'orderBy'  => 'date',
				),
			),
		);

		$instance->pre_render_block( null, $parsed_block );

		$types = Utility::get_hidden_property( $instance, 'event_query_types' );

		$this->assertSame(
			'upcoming',
			$types[6] ?? null,
			'A block whose saved query omits gatherpress_event_query should default to upcoming.'
		);

		remove_filter( 'query_loop_block_query_vars', array( $instance, 'query_loop_block_query_vars' ) );
	}

	/**
	 * Test query_loop_block_query_vars applies the recorded 'upcoming' default
	 * when the block context omits gatherpress_event_query.
	 *
	 * Mirrors the #1806 front-end path: pre_render_block records the default
	 * keyed by queryId, then the descendant post-template context (which lacks
	 * gatherpress_event_query) resolves to that default instead of leaving the
	 * query unfiltered.
	 *
	 * @since 0.34.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_applies_registered_default(): void {
		$instance = Event_Query::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'event_query_types', array( 6 => 'upcoming' ) );

		$query = array( 'posts_per_page' => 5 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'queryId' => 6,
			'query'   => array(
				'postType' => Event::POST_TYPE,
				'order'    => 'desc',
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame(
			'upcoming',
			$result['gatherpress_event_query'],
			'A registered query without an explicit type should default to upcoming.'
		);
		$this->assertSame( Event::POST_TYPE, $result['post_type'] );
	}

	/**
	 * Test query_loop_block_query_vars leaves an unregistered sibling query
	 * untouched.
	 *
	 * A plain Query Loop rendered after an event-query block can hit this
	 * still-attached filter. Without a recorded type and without an explicit
	 * gatherpress_event_query, its query must pass through unmodified so it is
	 * not accidentally turned into an event query (#1806).
	 *
	 * @since 0.34.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_skips_unregistered_query(): void {
		$instance = Event_Query::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'event_query_types', array( 6 => 'upcoming' ) );

		$query = array( 'posts_per_page' => 5 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'queryId' => 99,
			'query'   => array(
				'postType' => 'post',
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame(
			$query,
			$result,
			'A sibling query with a different queryId and no explicit type should be untouched.'
		);
	}

	/**
	 * Test query_loop_block_query_vars builds proper query args.
	 *
	 * @since 0.33.0
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

		$this->assertContains( Event::POST_TYPE, $result['post_type'] );
		$this->assertSame( 'upcoming', $result['gatherpress_event_query'] );
		$this->assertContains( 123, $result['post__not_in'] );
		$this->assertSame( 1, $result['include_unfinished'] );
		$this->assertSame( array( 'datetime' ), $result['orderby'] );
		$this->assertSame( 'ASC', $result['order'] );
	}

	/**
	 * The block's selected `postType` is honored as the `post_type` query
	 * arg when present. Without this, a Query Loop pinned to a specific
	 * event-supporting post type (e.g. `production`) would leak posts
	 * from every event-supporting post type on the site (#1609).
	 *
	 * @since 0.34.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_honors_block_post_type(): void {
		$instance  = Event_Query::get_instance();
		$post_type = 'production';
		$block     = $this->createMock( \WP_Block::class );

		register_post_type(
			$post_type,
			array(
				'label'    => 'Productions',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		$block->context = array(
			'query' => array(
				'gatherpress_event_query' => 'upcoming',
				'postType'                => 'production',
			),
		);

		$result = $instance->query_loop_block_query_vars( array(), $block );

		$this->assertSame(
			$post_type,
			$result['post_type'],
			'Should pass the block-selected post type through verbatim, not the union of every event-supporting type.'
		);

		unregister_post_type( $post_type );
	}

	/**
	 * Test query_loop_block_query_vars with DESC order.
	 *
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * Inside a block template `exclude_current` holds the template identifier
	 * (non-numeric), which casts to 0. On a singular event the "current" post
	 * is the queried object, so it must be excluded by the queried event id
	 * rather than the unusable template slug (#1753).
	 *
	 * @since 0.34.0
	 * @covers ::query_loop_block_query_vars
	 * @covers ::get_exclude_ids
	 *
	 * @return void
	 */
	public function test_get_exclude_ids_resolves_current_event_in_template_context(): void {
		$instance = Event_Query::get_instance();

		$event_id = $this->factory->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);
		$this->go_to( get_permalink( $event_id ) );

		$block          = $this->createMock( \WP_Block::class );
		$block->context = array(
			'query' => array(
				'gatherpress_event_query' => 'upcoming',
				'exclude_current'         => 'twentytwentyfive//single-gatherpress_event',
			),
		);

		$result = $instance->query_loop_block_query_vars( array(), $block );

		$this->assertContains(
			$event_id,
			$result['post__not_in'],
			'A non-numeric template identifier should resolve to the queried event id on a singular page.'
		);
		$this->assertNotContains(
			'twentytwentyfive//single-gatherpress_event',
			$result['post__not_in'],
			'The raw template identifier must not be pushed into post__not_in.'
		);

		wp_delete_post( $event_id, true );
	}

	/**
	 * A baked template identifier in `exclude_current` is dropped (not
	 * excluded) when there is no singular post to resolve it against — e.g. an
	 * archive or the REST editor preview. This keeps the editor best-effort
	 * unfiltered rather than excluding a bogus id (#1753).
	 *
	 * @since 0.34.0
	 * @covers ::query_loop_block_query_vars
	 * @covers ::get_exclude_ids
	 *
	 * @return void
	 */
	public function test_get_exclude_ids_drops_template_identifier_without_singular(): void {
		$instance = Event_Query::get_instance();

		$this->go_to( '/' );

		$block          = $this->createMock( \WP_Block::class );
		$block->context = array(
			'query' => array(
				'gatherpress_event_query' => 'upcoming',
				'exclude_current'         => 'twentytwentyfive//single-gatherpress_event',
			),
		);

		$result = $instance->query_loop_block_query_vars( array(), $block );

		$this->assertArrayNotHasKey(
			'post__not_in',
			$result,
			'A non-numeric template identifier with no singular context should not produce an exclusion.'
		);
	}

	/**
	 * Test rest_query with exclude_current parameter.
	 *
	 * @since 0.33.0
	 * @covers ::rest_query
	 * @covers ::get_exclude_ids
	 *
	 * @return void
	 */
	public function test_rest_query_with_exclude_current(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'gatherpress_event_query', 'upcoming' ),
			array( 'exclude_current', 456 ),
			array( 'include_unfinished', null ),
			array( 'orderby', 'datetime' ),
			array( 'shadow_filter', null ),
		);

		$request->expects( $this->exactly( 6 ) )
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
	 * @since 0.33.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_without_exclude_current(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'gatherpress_event_query', 'past' ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', 1 ),
			array( 'orderby', 'date' ),
			array( 'shadow_filter', null ),
		);

		$request->expects( $this->exactly( 6 ) )
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
	 * @since 0.33.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_filters_null_values(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'gatherpress_event_query', null ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', null ),
			array( 'orderby', null ),
			array( 'shadow_filter', null ),
		);

		$request->expects( $this->exactly( 6 ) )
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.33.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_filters_empty_strings(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'gatherpress_event_query', '' ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', null ),
			array( 'orderby', '' ),
			array( 'shadow_filter', null ),
		);

		$request->expects( $this->exactly( 6 ) )
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

	/**
	 * Test query_loop_block_query_vars passes shadow_filter through to query args.
	 *
	 * @since 0.34.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_with_shadow_filter(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 10 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'query' => array(
				'gatherpress_event_query' => 'upcoming',
				'postType'                => Event::POST_TYPE,
				'shadow_filter'           => 1,
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame( 1, $result['shadow_filter'], 'Should pass shadow_filter through to query args.' );
	}

	/**
	 * Test rest_query passes shadow_filter through to custom args.
	 *
	 * @since 0.34.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_with_shadow_filter(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'gatherpress_event_query', 'upcoming' ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', null ),
			array( 'orderby', null ),
			array( 'shadow_filter', 1 ),
		);

		$request->expects( $this->exactly( 6 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn(
				array(
					'gatherpress_event_query' => 'upcoming',
					'shadow_filter'           => 1,
				)
			);

		$initial_args = array(
			'post_type' => Event::POST_TYPE,
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertSame( 1, $result['shadow_filter'], 'Should pass shadow_filter through to custom args.' );
	}

	/**
	 * Test query_loop_block_query_vars passes shadow-source context attrs through to query args.
	 *
	 * Covers the editor-preview parity path — when the block's contextual toggle is on
	 * the editor writes the current post id + post type into the block's query attribute
	 * and we thread them onto the WP_Query so `Shadow_Source::resolve_post_from_query_context()`
	 * can scope by them.
	 *
	 * @since 0.34.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_with_shadow_source_context(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 10 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'query' => array(
				'gatherpress_event_query'             => 'upcoming',
				'postType'                            => 'gatherpress_venue',
				'shadow_filter'                       => 1,
				'gatherpress_shadow_source_post_id'   => 42,
				'gatherpress_shadow_source_post_type' => 'production',
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame(
			42,
			$result['gatherpress_shadow_source_post_id'],
			'Should pass the shadow-source post id through to query args.'
		);
		$this->assertSame(
			'production',
			$result['gatherpress_shadow_source_post_type'],
			'Should pass the shadow-source post type through to query args.'
		);
	}

	/**
	 * Test rest_query passes shadow-source context params through to custom args.
	 *
	 * @since 0.34.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_with_shadow_source_context(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'has_events_filter', null ),
			array( 'upcoming_events_only', null ),
			array( 'gatherpress_shadow_source_post_id', 42 ),
			array( 'gatherpress_shadow_source_post_type', 'production' ),
		);

		$request->expects( $this->exactly( 5 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn(
				array(
					'gatherpress_event_query'             => 'upcoming',
					'shadow_filter'                       => 1,
					'gatherpress_shadow_source_post_id'   => 42,
					'gatherpress_shadow_source_post_type' => 'production',
				)
			);

		$initial_args = array(
			'post_type' => 'gatherpress_venue',
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertSame(
			42,
			$result['gatherpress_shadow_source_post_id'],
			'Should pass shadow-source post id through to custom args.'
		);
		$this->assertSame(
			'production',
			$result['gatherpress_shadow_source_post_type'],
			'Should pass shadow-source post type through to custom args.'
		);
	}

	/**
	 * Test rest_query short-circuits when `include` is in the request.
	 *
	 * ID-based REST lookups should bypass the upcoming/past date filter so
	 * they can resolve past events too — without this, blocks that look up
	 * an override target via the collection endpoint silently get an empty
	 * array for past events and stay dimmed.
	 *
	 * @since 0.34.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_bypasses_filter_when_include_param_is_set(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		// Only `include` should be read — none of the date/orderby params
		// because the bypass returns before reaching them.
		$request->expects( $this->once() )
			->method( 'get_param' )
			->with( 'include' )
			->willReturn( array( 42 ) );

		$request->expects( $this->never() )
			->method( 'get_params' );

		$initial_args = array(
			'post_type'      => Event::POST_TYPE,
			'posts_per_page' => 1,
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertSame(
			$initial_args,
			$result,
			'Args should be returned unchanged when `include` is present.'
		);
	}

	/**
	 * Test aql_query_vars passes shadow_filter through to query args.
	 *
	 * @since 0.34.0
	 * @covers ::aql_query_vars
	 *
	 * @return void
	 */
	public function test_aql_query_vars_with_shadow_filter(): void {
		$instance = Event_Query::get_instance();

		$query_args  = array( 'posts_per_page' => 10 );
		$block_query = array(
			'postType'      => 'gatherpress_event',
			'shadow_filter' => 1,
		);

		$result = $instance->aql_query_vars( $query_args, $block_query, false );

		$this->assertSame( 1, $result['shadow_filter'], 'Should pass shadow_filter through to query args.' );
	}

	/**
	 * Coverage for maybe_register_event_date_rest_hooks on a shadow-source
	 * post type — REST filters must also register for post types that declare
	 * `gatherpress-shadow-source` so venue/production queries accept the new
	 * `has_events_filter` / `upcoming_events_only` collection params.
	 *
	 * @since 0.36.0
	 * @covers ::maybe_register_event_date_rest_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_event_date_rest_hooks_on_shadow_source_post_type(): void {
		$instance  = Event_Query::get_instance();
		$post_type = 'gatherpress_venue';

		remove_all_filters( sprintf( 'rest_%s_query', $post_type ) );
		remove_all_filters( sprintf( 'rest_%s_collection_params', $post_type ) );

		$instance->maybe_register_event_date_rest_hooks( $post_type );

		$this->assertSame(
			10,
			has_filter(
				sprintf( 'rest_%s_query', $post_type ),
				array( $instance, 'rest_query' )
			),
			'Failed to assert rest_query filter is registered for a shadow-source post type.'
		);
		$this->assertSame(
			10,
			has_filter(
				sprintf( 'rest_%s_collection_params', $post_type ),
				array( $instance, 'rest_collection_params' )
			),
			'Failed to assert rest_collection_params filter is registered for a shadow-source post type.'
		);
	}

	/**
	 * Passes the shadow-source activity params through rest_query to custom args.
	 *
	 * @since 0.36.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_with_has_events_filter(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'has_events_filter', 1 ),
			array( 'upcoming_events_only', 0 ),
			array( 'gatherpress_shadow_source_post_id', null ),
			array( 'gatherpress_shadow_source_post_type', null ),
		);

		$request->expects( $this->exactly( 5 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn(
				array(
					'has_events_filter'    => 1,
					'upcoming_events_only' => 0,
				)
			);

		$initial_args = array(
			'post_type' => 'gatherpress_venue',
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertSame(
			1,
			$result['has_events_filter'],
			'Should pass has_events_filter through to custom args.'
		);
		$this->assertSame(
			0,
			$result['upcoming_events_only'],
			'Should pass upcoming_events_only through to custom args.'
		);
	}

	/**
	 * Passes the shadow-source activity params through
	 * query_loop_block_query_vars to query args.
	 *
	 * @since 0.36.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_with_has_events_filter(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 10 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'query' => array(
				'gatherpress_event_query' => 'upcoming',
				'postType'                => 'gatherpress_venue',
				'has_events_filter'       => 1,
				'upcoming_events_only'    => 0,
			),
		);

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame(
			1,
			$result['has_events_filter'],
			'Should pass has_events_filter through to query args.'
		);
		$this->assertSame(
			0,
			$result['upcoming_events_only'],
			'Should pass upcoming_events_only through to query args.'
		);
	}

	/**
	 * Passes the shadow-source activity params through aql_query_vars to
	 * query args.
	 *
	 * @since 0.36.0
	 * @covers ::aql_query_vars
	 *
	 * @return void
	 */
	public function test_aql_query_vars_with_has_events_filter(): void {
		$instance = Event_Query::get_instance();

		$query_args  = array( 'posts_per_page' => 10 );
		$block_query = array(
			'postType'             => 'gatherpress_venue',
			'has_events_filter'    => 1,
			'upcoming_events_only' => 0,
		);

		$result = $instance->aql_query_vars( $query_args, $block_query, false );

		$this->assertSame(
			1,
			$result['has_events_filter'],
			'Should pass has_events_filter through to query args.'
		);
		$this->assertSame(
			0,
			$result['upcoming_events_only'],
			'Should pass upcoming_events_only through to query args.'
		);
	}

	/**
	 * Direct coverage for the protected helper that the xdebug same-class
	 * gap leaves untraced when it is only called from within its own class.
	 *
	 * @since 0.36.0
	 * @covers ::get_query_rest_post_types
	 *
	 * @return void
	 */
	public function test_get_query_rest_post_types_returns_event_and_shadow_source_types(): void {
		$instance = Event_Query::get_instance();

		register_post_type(
			'probe_evt',
			array(
				'label'    => 'Probe Event',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);
		register_post_type(
			'probe_shadow',
			array(
				'label'    => 'Probe Shadow',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-shadow-source' ),
			)
		);

		$types = Utility::invoke_hidden_method( $instance, 'get_query_rest_post_types', array() );

		$this->assertContains( 'probe_evt', $types, 'Event-supporting types should be listed.' );
		$this->assertContains( 'probe_shadow', $types, 'Shadow-source types should be listed.' );
		$this->assertNotContains( 'post', $types, 'Unrelated types should not be listed.' );

		unregister_post_type( 'probe_evt' );
		unregister_post_type( 'probe_shadow' );
	}

	/**
	 * Direct coverage for get_requested_post_types_from_args across all
	 * supported input shapes (array, CSV string, missing, empty).
	 *
	 * @since 0.36.0
	 * @covers ::get_requested_post_types_from_args
	 *
	 * @return void
	 */
	public function test_get_requested_post_types_from_args_handles_array(): void {
		$instance = Event_Query::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'get_requested_post_types_from_args',
			array(
				array(
					'post_type' => array( 'gatherpress_event', 'gatherpress_venue', 0 ),
				),
			)
		);

		$this->assertSame(
			array( 'gatherpress_event', 'gatherpress_venue' ),
			$result,
			'Should return array of valid post type slugs.'
		);
	}

	/**
	 * Comma-separated post_type input should be split into individual slugs.
	 *
	 * @since 0.36.0
	 * @covers ::get_requested_post_types_from_args
	 *
	 * @return void
	 */
	public function test_get_requested_post_types_from_args_handles_csv_string(): void {
		$instance = Event_Query::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'get_requested_post_types_from_args',
			array(
				array(
					'post_type' => ' gatherpress_event , gatherpress_venue , ',
				),
			)
		);

		$this->assertSame(
			array( 'gatherpress_event', 'gatherpress_venue' ),
			$result,
			'Should split CSV string and trim whitespace.'
		);
	}

	/**
	 * Missing post_type arg should produce an empty list rather than error.
	 *
	 * @since 0.36.0
	 * @covers ::get_requested_post_types_from_args
	 *
	 * @return void
	 */
	public function test_get_requested_post_types_from_args_handles_missing(): void {
		$instance = Event_Query::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'get_requested_post_types_from_args',
			array( array() )
		);

		$this->assertSame(
			array(),
			$result,
			'Missing post_type should resolve to empty list.'
		);
	}

	/**
	 * Empty post_type (any of array, CSV, or string) should produce an
	 * empty list so the caller falls through to support-agnostic behavior.
	 *
	 * @since 0.36.0
	 * @covers ::get_requested_post_types_from_args
	 *
	 * @return void
	 */
	public function test_get_requested_post_types_from_args_handles_empty(): void {
		$instance = Event_Query::get_instance();

		$empty_array  = Utility::invoke_hidden_method(
			$instance,
			'get_requested_post_types_from_args',
			array(
				array(
					'post_type' => array(),
				),
			)
		);
		$empty_string = Utility::invoke_hidden_method(
			$instance,
			'get_requested_post_types_from_args',
			array(
				array(
					'post_type' => '',
				),
			)
		);

		$this->assertSame( array(), $empty_array, 'Empty array post_type should yield empty list.' );
		$this->assertSame( array(), $empty_string, 'Empty string post_type should yield empty list.' );
	}

	/**
	 * Mixed post type requests should route to both event and shadow-source
	 * param branches in rest_query.
	 *
	 * @since 0.36.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_routes_mixed_post_types_to_both_branches(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'gatherpress_event_query', 'past' ),
			array( 'exclude_current', null ),
			array( 'include_unfinished', 1 ),
			array( 'orderby', 'datetime' ),
			array( 'shadow_filter', 1 ),
			array( 'has_events_filter', 1 ),
			array( 'upcoming_events_only', 0 ),
			array( 'gatherpress_shadow_source_post_id', null ),
			array( 'gatherpress_shadow_source_post_type', null ),
		);

		$request->expects( $this->exactly( 10 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn(
				array(
					'gatherpress_event_query' => 'past',
					'has_events_filter'       => 1,
				)
			);

		$initial_args = array(
			'post_type' => array( 'gatherpress_event', 'gatherpress_venue' ),
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertSame( 'past', $result['gatherpress_event_query'], 'Event query should be set.' );
		$this->assertSame( 1, $result['has_events_filter'], 'Activity filter should be set.' );
		$this->assertSame( 0, $result['upcoming_events_only'], 'Upcoming-only flag should be set.' );
	}

	/**
	 * When a shadow-source-only post type is queried, only shadow-source
	 * params should be read; event-only params should not be requested
	 * from the REST request (would 400 with rest_invalid_param).
	 *
	 * @since 0.36.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_uses_shadow_only_params_for_shadow_post_type(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$param_map = array(
			array( 'include', null ),
			array( 'has_events_filter', 1 ),
			array( 'upcoming_events_only', 1 ),
			array( 'gatherpress_shadow_source_post_id', null ),
			array( 'gatherpress_shadow_source_post_type', null ),
		);

		$request->expects( $this->exactly( 5 ) )
			->method( 'get_param' )
			->willReturnMap( $param_map );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn( array( 'has_events_filter' => 1 ) );

		$initial_args = array(
			'post_type' => 'gatherpress_venue',
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertArrayNotHasKey(
			'gatherpress_event_query',
			$result,
			'Event-only params should not appear in shadow-source result.'
		);
		$this->assertArrayNotHasKey(
			'include_unfinished',
			$result,
			'Event-only params should not appear in shadow-source result.'
		);
		$this->assertArrayNotHasKey(
			'shadow_filter',
			$result,
			'Event-only shadow_filter should not appear in shadow-source result.'
		);
		$this->assertSame( 1, $result['has_events_filter'], 'Activity filter should pass through.' );
	}

	/**
	 * When an unrelated post type is queried, neither event nor shadow-source
	 * params should be requested.
	 *
	 * @since 0.36.0
	 * @covers ::rest_query
	 *
	 * @return void
	 */
	public function test_rest_query_skips_both_branches_for_unrelated_post_type(): void {
		$instance = Event_Query::get_instance();

		$request = $this->createMock( \WP_REST_Request::class );

		$request->expects( $this->once() )
			->method( 'get_param' )
			->with( 'include' )
			->willReturn( null );

		$request->expects( $this->once() )
			->method( 'get_params' )
			->willReturn( array() );

		$initial_args = array(
			'post_type' => 'post',
		);

		$result = $instance->rest_query( $initial_args, $request );

		$this->assertArrayNotHasKey(
			'gatherpress_event_query',
			$result,
			'Unrelated post types should not have event params.'
		);
		$this->assertArrayNotHasKey(
			'has_events_filter',
			$result,
			'Unrelated post types should not have activity params.'
		);
	}

	/**
	 * Shadow-source-only Query Loop context should propagate activity
	 * params without pulling in event-only vars.
	 *
	 * @since 0.36.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_shadow_only_path(): void {
		$instance = Event_Query::get_instance();

		$query = array( 'posts_per_page' => 5 );
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'queryId' => 99,
			'query'   => array(
				'postType'             => 'gatherpress_venue',
				'has_events_filter'    => 1,
				'upcoming_events_only' => 0,
			),
		);

		// Clear any pre-recorded types so the block's own query is honored.
		Utility::set_and_get_hidden_property( $instance, 'event_query_types', array() );

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame( 1, $result['has_events_filter'], 'Activity filter should pass through.' );
		$this->assertSame( 0, $result['upcoming_events_only'], 'Upcoming-only should pass through.' );
		$this->assertArrayNotHasKey(
			'gatherpress_event_query',
			$result,
			'Event-only vars should not leak into shadow-only loops.'
		);
		$this->assertArrayNotHasKey(
			'shadow_filter',
			$result,
			'Event-only shadow_filter should not appear in shadow-source-only loops.'
		);
	}

	/**
	 * A plain Query Loop on an unrelated post type should pass through
	 * untouched (regression for #1806 / Playground "plain venue loop
	 * returns zero results" report).
	 *
	 * @since 0.36.0
	 * @covers ::query_loop_block_query_vars
	 *
	 * @return void
	 */
	public function test_query_loop_block_query_vars_unrelated_post_type_passthrough(): void {
		$instance = Event_Query::get_instance();

		$query = array(
			'posts_per_page' => 10,
			'post_type'      => 'post',
		);
		$block = $this->createMock( \WP_Block::class );

		$block->context = array(
			'queryId' => 77,
			'query'   => array(
				'postType' => 'post',
			),
		);

		Utility::set_and_get_hidden_property( $instance, 'event_query_types', array() );

		$result = $instance->query_loop_block_query_vars( $query, $block );

		$this->assertSame( $query, $result, 'Unrelated post type should pass through unchanged.' );
	}
}
