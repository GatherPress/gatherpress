<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Query.
 *
 * @package GatherPress\Core\Event
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Event;

use DateTime;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Query;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Query;

/**
 * Class Test_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Query
 */
class Test_Query extends Base {

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Query::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $instance, 'prepare_event_query_before_execution' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'posts_clauses',
				'priority' => 9,
				'callback' => array( $instance, 'adjust_admin_event_sorting' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for get_upcoming_events method.
	 *
	 * @covers ::get_upcoming_events
	 * @covers ::adjust_sorting_for_upcoming_events
	 * @covers ::get_events_list
	 *
	 * @return void
	 */
	public function test_get_upcoming_events(): void {
		$instance = Query::get_instance();
		$response = $instance->get_upcoming_events();

		$this->assertEmpty( $response->posts, 'Failed to assert that posts array is empty.' );
		$this->assertSame( 5, $response->query['posts_per_page'], 'Failed to assert post per page limit.' );

		$post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$event = new Event( $post->ID );
		$date  = new \DateTime( 'tomorrow' );

		$params = array(
			'datetime_start' => $date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $date->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		$response = $instance->get_upcoming_events( 1 );

		$this->assertSame( $response->posts[0], $post->ID, 'Failed to assert that event ID is in array.' );
		$this->assertSame( 1, $response->query['posts_per_page'], 'Failed to assert post per page limit.' );
		$this->assertSame(
			'upcoming',
			$response->query['gatherpress_event_query'],
			'Failed to assert query is upcoming.'
		);
		$this->assertContains(
			'gatherpress_event',
			(array) $response->query['post_type'],
			'Failed to assert post type includes gatherpress_event.'
		);
	}

	/**
	 * Coverage for get_past_events method.
	 *
	 * @covers ::get_past_events
	 * @covers ::adjust_sorting_for_past_events
	 * @covers ::get_events_list
	 *
	 * @return void
	 */
	public function test_get_past_events(): void {
		$instance = Query::get_instance();
		$response = $instance->get_past_events();

		$this->assertEmpty( $response->posts, 'Failed to assert that posts array is empty.' );
		$this->assertSame( 5, $response->query['posts_per_page'], 'Failed to assert post per page limit.' );

		$post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$event = new Event( $post->ID );
		$date  = new DateTime( 'yesterday' );

		$params = array(
			'datetime_start' => $date->modify( '-1 day' )->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $date->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		$response = $instance->get_past_events( 1 );

		$this->assertSame( $response->posts[0], $post->ID, 'Failed to assert that event ID is in array.' );
		$this->assertSame( 1, $response->query['posts_per_page'], 'Failed to assert post per page limit.' );
		$this->assertSame(
			'past',
			$response->query['gatherpress_event_query'],
			'Failed to assert query is past.'
		);
		$this->assertContains(
			'gatherpress_event',
			(array) $response->query['post_type'],
			'Failed to assert post type includes gatherpress_event.'
		);
	}

	/**
	 * Coverage for get_events_list method.
	 *
	 * @covers ::get_events_list
	 *
	 * @return void
	 */
	public function test_get_events_list(): void {
		$instance = Query::get_instance();
		$post_1   = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$post_2   = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$event_1  = new Event( $post_1->ID );
		$event_2  = new Event( $post_2->ID );
		$date     = new DateTime( 'yesterday' );
		$params   = array(
			'datetime_start' => $date->modify( '-1 day' )->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $date->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);
		$event_1->save_datetimes( $params );
		$event_2->save_datetimes( $params );

		wp_insert_term(
			'Unit Test Venue',
			Venue::TAXONOMY,
			array(
				'slug' => '_unit-test-venue',
			)
		);
		$topic = wp_insert_term(
			'Unit Test Topic',
			Topic::TAXONOMY,
			array(
				'slug' => 'unit-test-topic',
			)
		);

		wp_set_post_terms( $post_1->ID, $topic['term_id'], Topic::TAXONOMY );
		wp_set_post_terms( $post_1->ID, '_unit-test-venue', Venue::TAXONOMY );

		$results = $instance->get_events_list(
			'past',
			2,
			array( 'unit-test-topic' ),
			array( '_unit-test-venue' )
		);

		$this->assertContains( $post_1->ID, $results->posts, 'Failed to assert that post ID was in array.' );
		$this->assertNotContains( $post_2->ID, $results->posts, 'Failed to assert that post ID was not in array.' );

		$results = $instance->get_events_list(
			'past',
			2,
			array( 'unit-test-topic' )
		);

		$this->assertContains( $post_1->ID, $results->posts, 'Failed to assert that post ID was in array.' );
		$this->assertNotContains( $post_2->ID, $results->posts, 'Failed to assert that post ID was not in array.' );

		$results = $instance->get_events_list(
			'past',
			2,
			array(),
			array( '_unit-test-venue' )
		);

		$this->assertContains( $post_1->ID, $results->posts, 'Failed to assert that post ID was in array.' );
		$this->assertNotContains( $post_2->ID, $results->posts, 'Failed to assert that post ID was not in array.' );
	}

	/**
	 * Test query adjusted for upcoming events.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_for_upcoming_events(): void {
		$instance = Query::get_instance();
		$query    = new WP_Query();

		$query->set( 'post_type', 'gatherpress_event' );
		$query->set( 'gatherpress_event_query', 'upcoming' );
		$instance->prepare_event_query_before_execution( $query );

		$this->assertEquals(
			10,
			has_filter( 'posts_clauses', array( $instance, 'adjust_sorting_for_upcoming_events' ) ),
			'Should add filter for upcoming events sorting'
		);

		$this->assertFalse(
			has_filter( 'posts_clauses', array( $instance, 'adjust_sorting_for_past_events' ) ),
			'Should remove filter for past events sorting'
		);
	}

	/**
	 * Test query adjusted for past events.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_for_past_events(): void {
		$instance = Query::get_instance();
		$query    = new WP_Query();

		$query->set( 'post_type', 'gatherpress_event' );
		$query->set( 'gatherpress_event_query', 'past' );
		$instance->prepare_event_query_before_execution( $query );

		$this->assertEquals(
			10,
			has_filter( 'posts_clauses', array( $instance, 'adjust_sorting_for_past_events' ) ),
			'Should add filter for past events sorting'
		);

		$this->assertFalse(
			has_filter( 'posts_clauses', array( $instance, 'adjust_sorting_for_upcoming_events' ) ),
			'Should remove filter for upcoming events sorting'
		);
	}

	/**
	 * Test query adjusted for archive page.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_for_archive_page(): void {
		$instance = Query::get_instance();

		// Mock WP_Query with necessary properties.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		$query->is_main_query     = true;
		$query->queried_object_id = 123;

		// Mock main query check.
		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		$query->expects( $this->any() )
			->method( 'get' )
			->willReturnCallback(
				static function ( $key ) {
					if ( 'page_id' === $key ) {
						return 123;
					}
					return 'gatherpress_event_query' === $key ? 'past' : null;
				}
			);

		$page_data = array(
			'past_events' => wp_json_encode(
				array(
					(object) array( 'id' => 123 ),
				)
			),
		);

		add_option( 'gatherpress_settings', $page_data );

		$instance->prepare_event_query_before_execution( $query );

		$this->assertTrue(
			$query->is_archive,
			'Should set is_archive to true'
		);

		$this->assertTrue(
			$query->is_post_type_archive,
			'Should set is_post_type_archive to true'
		);

		$this->assertFalse(
			$query->is_page,
			'Should set is_page to false'
		);

		$this->assertFalse(
			$query->is_singular,
			'Should set is_singular to false'
		);

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Test that prepare_event_query_before_execution resolves pages by pagename.
	 *
	 * Covers the get_page_by_path code path when page_id is not set
	 * but pagename query var is present.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_resolves_page_by_pagename(): void {
		$instance = Query::get_instance();

		// Create a real page with a known slug.
		$page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_name'  => 'test-archive',
				'post_title' => 'Test Archive',
			)
		);

		$page_data = array(
			'past_events' => wp_json_encode(
				array(
					(object) array( 'id' => $page_id ),
				)
			),
		);

		add_option( 'gatherpress_settings', $page_data );

		// Mock WP_Query with pagename instead of page_id.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		$query->is_main_query     = true;
		$query->queried_object_id = $page_id;

		// Mock main query check.
		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		// Return 0 for page_id to trigger pagename fallback.
		$query->expects( $this->any() )
			->method( 'get' )
			->willReturnCallback(
				static function ( $key ) {
					if ( 'page_id' === $key ) {
						return 0;
					}
					if ( 'pagename' === $key ) {
						return 'test-archive';
					}
					return 'gatherpress_event_query' === $key ? 'past' : null;
				}
			);

		$instance->prepare_event_query_before_execution( $query );

		$this->assertTrue(
			$query->is_archive,
			'Should set is_archive to true when resolved via pagename.'
		);

		$this->assertTrue(
			$query->is_post_type_archive,
			'Should set is_post_type_archive to true when resolved via pagename.'
		);

		$this->assertFalse(
			$query->is_page,
			'Should set is_page to false when resolved via pagename.'
		);

		$this->assertFalse(
			$query->is_singular,
			'Should set is_singular to false when resolved via pagename.'
		);

		delete_option( 'gatherpress_settings' );
		wp_delete_post( $page_id, true );
	}

	/**
	 * Test query with invalid general options.
	 *
	 * Early return when $general is not an array.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_with_invalid_general_options(): void {
		$instance = Query::get_instance();

		// Mock WP_Query with necessary properties.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		// Mock is_main_query to return true.
		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		// Set invalid general option (not an array).
		add_option( 'gatherpress_settings', 'invalid' );

		$instance->prepare_event_query_before_execution( $query );

		// Query should not have been modified due to early return.
		$this->assertTrue(
			true,
			'Method should return early when general option is not an array.'
		);

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Test query with empty pages configuration.
	 *
	 * Early return when $pages is empty or not an array.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_with_empty_pages(): void {
		$instance = Query::get_instance();

		// Mock WP_Query with necessary properties.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		// Mock is_main_query to return true.
		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		// Test with empty pages.
		add_option( 'gatherpress_settings', '' );

		$instance->prepare_event_query_before_execution( $query );

		// Query should not have been modified due to early return.
		$this->assertTrue(
			true,
			'Method should return early when pages is empty'
		);

		delete_option( 'gatherpress_settings' );

		// Test with pages not being an array.
		add_option( 'gatherpress_settings', 'not-an-array' );

		$instance->prepare_event_query_before_execution( $query );

		// Query should not have been modified due to early return.
		$this->assertTrue(
			true,
			'Method should return early when pages is not an array'
		);

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Test pre_option filter callbacks for archive pages.
	 *
	 * Covers pre_option filter for page_for_posts and show_on_front.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_pre_option_filters(): void {
		$instance = Query::get_instance();

		// Create a page to use as the archive page.
		$page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Upcoming Events Page',
			)
		);

		// Mock WP_Query with necessary properties.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		$query->is_main_query     = true;
		$query->queried_object_id = $page_id;

		// Mock main query check.
		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		$query->expects( $this->any() )
			->method( 'get' )
			->willReturnCallback(
				static function ( $key ) use ( $page_id ) {
					if ( 'page_id' === $key ) {
						return $page_id;
					}
					return 'gatherpress_event_query' === $key ? '' : null;
				}
			);

		$page_data = array(
			'upcoming_events' => wp_json_encode(
				array(
					(object) array( 'id' => $page_id ),
				)
			),
		);

		add_option( 'gatherpress_settings', $page_data );

		$instance->prepare_event_query_before_execution( $query );

		// Verify the pre_option filters are working for specific options.
		$this->assertEquals( -1, get_option( 'page_for_posts' ), 'page_for_posts should be -1' );
		$this->assertEquals( 'page', get_option( 'show_on_front' ), 'show_on_front should be page' );

		// Verify default return for other options.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$other_option = apply_filters( 'pre_option', 'original_value', 'some_other_option' );
		$this->assertEquals( 'original_value', $other_option, 'Other options should return original pre value' );

		delete_option( 'gatherpress_settings' );
		wp_delete_post( $page_id, true );
	}

	/**
	 * Test get_the_archive_title filter callback.
	 *
	 * Covers get_the_archive_title filter callback.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_archive_title_filter(): void {
		$instance = Query::get_instance();

		// Create a page to use as the archive page.
		$page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Custom Archive Title',
			)
		);

		// Mock WP_Query with necessary properties.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		$query->is_main_query     = true;
		$query->queried_object_id = $page_id;

		// Mock main query check.
		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		$query->expects( $this->any() )
			->method( 'get' )
			->willReturnCallback(
				static function ( $key ) use ( $page_id ) {
					if ( 'page_id' === $key ) {
						return $page_id;
					}
					return 'gatherpress_event_query' === $key ? '' : null;
				}
			);

		$page_data = array(
			'past_events' => wp_json_encode(
				array(
					(object) array( 'id' => $page_id ),
				)
			),
		);

		add_option( 'gatherpress_settings', $page_data );

		$instance->prepare_event_query_before_execution( $query );

		// Verify the get_the_archive_title filter returns the page title.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$archive_title = apply_filters( 'get_the_archive_title', 'Default Title' );
		$this->assertEquals( 'Custom Archive Title', $archive_title, 'Archive title should be the page title' );

		delete_option( 'gatherpress_settings' );
		wp_delete_post( $page_id, true );
	}

	/**
	 * Test query with no event type.
	 *
	 * @since  0.34.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_with_no_event_type(): void {
		$instance = Query::get_instance();
		$query    = new WP_Query();

		$instance->prepare_event_query_before_execution( $query );

		$this->assertFalse(
			has_filter( 'posts_clauses', array( $instance, 'adjust_sorting_for_past_events' ) ),
			'Should remove past events filter'
		);

		$this->assertFalse(
			has_filter( 'posts_clauses', array( $instance, 'adjust_sorting_for_upcoming_events' ) ),
			'Should remove upcoming events filter'
		);
	}

	/**
	 * Coverage for adjust_admin_event_sorting method.
	 *
	 * @covers ::adjust_admin_event_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_event_sorting(): void {
		$instance = Query::get_instance();
		$wp_query = new WP_Query();

		$this->mock->user( false, 'admin' );
		$response = $instance->adjust_admin_event_sorting( array(), $wp_query );
		$this->assertEmpty( $response, 'Failed to assert the array, containing pieces of the SQL query, is empty' );

		$this->mock->user( true, 'admin' );
		set_current_screen( 'edit-gatherpress_event' );

		// Set 'post_type' to match the current screen (the function bails if
		// they differ, to avoid altering secondary queries fired from the
		// same admin page).
		$wp_query->set( 'post_type', Event::POST_TYPE );

		// Set 'orderby' admin query to 'datetime'.
		$wp_query->set( 'orderby', 'datetime' );

		// Run function with empty array passed as 'pieces' argument.
		$response = $instance->adjust_admin_event_sorting( array(), $wp_query );

		// Assert that an array was generated from the adjustsql argument. todo: make this test more meaningful.
		$this->assertNotEmpty(
			$response,
			'Failed to assert the array, containing pieces of the SQL query, is not empty'
		);

		$wp_query = new WP_Query();
		$wp_query->set( 'post_type', Event::POST_TYPE );

		$wp_query->set( 'gatherpress_event_query', 'upcoming' );
		$response = $instance->adjust_admin_event_sorting( array(), $wp_query );
		$this->assertNotEmpty(
			$response,
			'Failed to assert the array, containing pieces of the SQL query, is not empty'
		);

		$wp_query->set( 'gatherpress_event_query', 'past' );
		$response = $instance->adjust_admin_event_sorting( array(), $wp_query );
		$this->assertNotEmpty(
			$response,
			'Failed to assert the array, containing pieces of the SQL query, is not empty'
		);
	}

	/**
	 * Test adjust_admin_event_sorting with wrong screen.
	 *
	 * Covers Early return when current_screen is not 'edit-gatherpress_event'.
	 *
	 * @covers ::adjust_admin_event_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_event_sorting_wrong_screen(): void {
		$instance     = Query::get_instance();
		$wp_query     = new WP_Query();
		$query_pieces = array( 'orderby' => 'post_date' );

		$this->mock->user( true, 'admin' );

		// Set a different screen (not edit-gatherpress_event).
		set_current_screen( 'edit-post' );

		// Set 'orderby' admin query to 'datetime'.
		$wp_query->set( 'orderby', 'datetime' );

		// Should return query_pieces unchanged.
		$response = $instance->adjust_admin_event_sorting( $query_pieces, $wp_query );

		$this->assertSame(
			$query_pieces,
			$response,
			'Should return query_pieces unchanged when screen is not edit-gatherpress_event'
		);
	}

	/**
	 * Reproduces #1610: clicking the "Event date & time" column header on
	 * the admin list of a custom event-supporting post type (e.g.
	 * `production`) must apply the datetime sort. Before the fix the
	 * function bailed early because the screen check was hardcoded to
	 * `edit-gatherpress_event`, leaving the click a no-op on every other
	 * post type that declares `gatherpress-event-date` support.
	 *
	 * @covers ::adjust_admin_event_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_event_sorting_runs_for_event_supporting_post_type(): void {
		$instance = Query::get_instance();
		$test_pt  = 'production';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Productions',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		$this->mock->user( true, 'admin' );
		set_current_screen( 'edit-' . $test_pt );

		$wp_query = new WP_Query();
		$wp_query->set( 'post_type', $test_pt );
		$wp_query->set( 'orderby', 'datetime' );
		$wp_query->set( 'order', 'asc' );

		$response = $instance->adjust_admin_event_sorting( array(), $wp_query );

		set_current_screen( 'front' );
		unregister_post_type( $test_pt );

		$this->assertNotEmpty(
			$response,
			'Should produce a non-empty pieces array for an event-supporting custom post type.'
		);
		$this->assertStringContainsString(
			'datetime_start_gmt ASC',
			$response['orderby'] ?? '',
			'Should sort by the events table datetime_start_gmt column.'
		);
	}

	/**
	 * Guards against secondary queries on the admin list table — a
	 * `WP_Query` whose `post_type` does not match the screen's post
	 * type (for example, a sidebar widget or related-content lookup
	 * fired from the admin list page) must not get the datetime sort
	 * grafted onto its SQL.
	 *
	 * @covers ::adjust_admin_event_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_event_sorting_skips_when_post_type_mismatches_screen(): void {
		$instance     = Query::get_instance();
		$query_pieces = array( 'orderby' => 'post_date' );

		$this->mock->user( true, 'admin' );
		set_current_screen( 'edit-' . Event::POST_TYPE );

		$wp_query = new WP_Query();
		// Different post type than the screen — a secondary query.
		$wp_query->set( 'post_type', 'post' );
		$wp_query->set( 'orderby', 'datetime' );

		$response = $instance->adjust_admin_event_sorting( $query_pieces, $wp_query );

		set_current_screen( 'front' );

		$this->assertSame(
			$query_pieces,
			$response,
			'Secondary queries with a different post_type than the screen should pass through untouched.'
		);
	}

	/**
	 * Coverage for adjust_event_sql method.
	 *
	 * @covers ::adjust_event_sql
	 *
	 * @return void
	 */
	public function test_adjust_event_sql(): void {
		global $wpdb;

		$instance = Query::get_instance();

		$table  = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$retval = $instance->adjust_event_sql( array(), 'all', 'DESC' );

		$this->assertStringContainsString( '.datetime_start_gmt DESC', $retval['orderby'] );
		$this->assertEmpty( $retval['where'] );

		$retval = $instance->adjust_event_sql( array(), 'past', 'desc' ); // inclusive will be TRUE by default.

		$this->assertStringContainsString( '.datetime_start_gmt DESC', $retval['orderby'] );
		$this->assertStringContainsString( "AND `{$table}`.`datetime_start_gmt` <", $retval['where'] );

		$retval = $instance->adjust_event_sql( array(), 'past', 'desc', 'datetime', false );

		$this->assertStringContainsString( '.datetime_start_gmt DESC', $retval['orderby'] );
		$this->assertStringContainsString( "AND `{$table}`.`datetime_end_gmt` <", $retval['where'] );

		$retval = $instance->adjust_event_sql( array(), 'upcoming', 'ASC' );

		$this->assertStringContainsString( '.datetime_start_gmt ASC', $retval['orderby'] );
		$this->assertStringContainsString( "AND `{$table}`.`datetime_end_gmt` >=", $retval['where'] );

		$retval = $instance->adjust_event_sql( array(), 'past', 'desc', 'id', false );

		$this->assertStringContainsString( '.ID DESC', $retval['orderby'] );

		$retval = $instance->adjust_event_sql( array(), 'past', 'desc', 'title', false );

		$this->assertStringContainsString( '.post_name DESC', $retval['orderby'] );

		$retval = $instance->adjust_event_sql( array(), 'past', 'desc', 'modified', false );

		$this->assertStringContainsString( '.post_modified_gmt DESC', $retval['orderby'] );

		$retval = $instance->adjust_event_sql( array(), 'upcoming', 'desc', 'rand', false );

		$this->assertStringContainsString( 'RAND()', $retval['orderby'] );

		// Test default case: unrecognized orderby should not override the orderby clause.
		$retval = $instance->adjust_event_sql( array(), 'all', 'ASC', 'rsvps' );

		$this->assertEmpty( $retval['orderby'], 'Unrecognized orderby should not set orderby clause.' );
	}

	/**
	 * Coverage for build_venue_tax_query method.
	 *
	 * @covers ::build_venue_tax_query
	 *
	 * @return void
	 */
	public function test_build_venue_tax_query(): void {
		$instance = Query::get_instance();
		$venues   = array( '_unit-test-venue', '_another-venue' );

		$tax_query = Utility::invoke_hidden_method( $instance, 'build_venue_tax_query', array( $venues ) );

		$this->assertIsArray(
			$tax_query,
			'Failed to assert that tax query is an array.'
		);
		$this->assertSame(
			'OR',
			$tax_query['relation'],
			'Failed to assert OR relation in venue tax query.'
		);

		// Expect one entry per registered venue post type (plus the relation key).
		$venue_post_types = get_post_types_by_support( 'gatherpress-venue-information' );
		$expected_count   = count( $venue_post_types ) + 1;
		$this->assertCount(
			$expected_count,
			$tax_query,
			'Failed to assert the correct count of entries in the venue tax query.'
		);

		// Verify the first condition uses the correct taxonomy, field, and terms.
		$first_condition = $tax_query[0];
		$this->assertSame(
			'slug',
			$first_condition['field'],
			'Failed to assert that the field is slug.'
		);
		$this->assertSame(
			$venues,
			$first_condition['terms'],
			'Failed to assert that terms match the provided venues.'
		);
		$this->assertSame(
			Setup::get_instance()->get_taxonomy( Venue::POST_TYPE ),
			$first_condition['taxonomy'],
			'Failed to assert the taxonomy matches the built-in venue taxonomy.'
		);
	}

	/**
	 * Coverage for get_datetime_comparison_column method.
	 *
	 * @covers ::get_datetime_comparison_column
	 *
	 * @return void
	 */
	public function test_get_datetime_comparison_column(): void {
		$instance = Query::get_instance();

		$this->assertSame(
			'datetime_end_gmt',
			Utility::invoke_hidden_method( $instance, 'get_datetime_comparison_column', array( 'upcoming', true ) ),
			'Failed to assert, that inclusive, upcoming events should be ordered by datetime_end_gmt.'
		);
		$this->assertSame(
			'datetime_start_gmt',
			Utility::invoke_hidden_method( $instance, 'get_datetime_comparison_column', array( 'upcoming', false ) ),
			'Failed to assert, that non-inclusive, upcoming events should be ordered by datetime_start_gmt.'
		);

		$this->assertSame(
			'datetime_start_gmt',
			Utility::invoke_hidden_method( $instance, 'get_datetime_comparison_column', array( 'past', true ) ),
			'Failed to assert, that inclusive, past events should be ordered by datetime_start_gmt.'
		);
		$this->assertSame(
			'datetime_end_gmt',
			Utility::invoke_hidden_method( $instance, 'get_datetime_comparison_column', array( 'past', false ) ),
			'Failed to assert, that non-inclusive, past events should be ordered by datetime_end_gmt.'
		);
	}

	/**
	 * Test that past events are ordered correctly (most recent first).
	 *
	 * @covers ::get_past_events
	 * @covers ::get_events_list
	 *
	 * @return void
	 */
	public function test_past_events_order(): void {
		$instance = Query::get_instance();

		// Create multiple past events with different dates.
		$oldest_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$oldest_event = new Event( $oldest_post->ID );
		$oldest_date  = new DateTime( '-10 days' );

		$params = array(
			'datetime_start' => $oldest_date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $oldest_date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);
		$oldest_event->save_datetimes( $params );

		$middle_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$middle_event = new Event( $middle_post->ID );
		$middle_date  = new DateTime( '-5 days' );

		$params = array(
			'datetime_start' => $middle_date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $middle_date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);
		$middle_event->save_datetimes( $params );

		$recent_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$recent_event = new Event( $recent_post->ID );
		$recent_date  = new DateTime( '-2 days' );

		$params = array(
			'datetime_start' => $recent_date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $recent_date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);
		$recent_event->save_datetimes( $params );

		// Get past events.
		$past_events = $instance->get_past_events( 10 );

		// Verify the order is DESC (most recent first).
		$this->assertSame( 'DESC', $past_events->query['order'], 'Past events should be ordered DESC' );

		// The posts should be in order: recent, middle, oldest.
		$posts = $past_events->posts;
		$this->assertSame(
			$recent_post->ID,
			$posts[0],
			'Most recent past event should be first'
		);
		$this->assertSame(
			$middle_post->ID,
			$posts[1],
			'Middle past event should be second'
		);
		$this->assertSame(
			$oldest_post->ID,
			$posts[2],
			'Oldest past event should be last'
		);
	}

	/**
	 * Test that upcoming events are ordered correctly (soonest first).
	 *
	 * @covers ::get_upcoming_events
	 * @covers ::get_events_list
	 *
	 * @return void
	 */
	public function test_upcoming_events_order(): void {
		$instance = Query::get_instance();

		// Create multiple upcoming events with different dates.
		$soon_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$soon_event = new Event( $soon_post->ID );
		$soon_date  = new DateTime( '+2 days' );

		$params = array(
			'datetime_start' => $soon_date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $soon_date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);
		$soon_event->save_datetimes( $params );

		$middle_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$middle_event = new Event( $middle_post->ID );
		$middle_date  = new DateTime( '+5 days' );

		$params = array(
			'datetime_start' => $middle_date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $middle_date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);
		$middle_event->save_datetimes( $params );

		$far_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$far_event = new Event( $far_post->ID );
		$far_date  = new DateTime( '+10 days' );

		$params = array(
			'datetime_start' => $far_date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $far_date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);
		$far_event->save_datetimes( $params );

		// Get upcoming events.
		$upcoming_events = $instance->get_upcoming_events( 10 );

		// Verify the order is ASC (soonest first).
		$this->assertSame( 'ASC', $upcoming_events->query['order'], 'Upcoming events should be ordered ASC' );

		// The posts should be in order: soon, middle, far.
		$posts = $upcoming_events->posts;
		$this->assertSame(
			$soon_post->ID,
			$posts[0],
			'Soonest upcoming event should be first'
		);
		$this->assertSame(
			$middle_post->ID,
			$posts[1],
			'Middle upcoming event should be second'
		);
		$this->assertSame(
			$far_post->ID,
			$posts[2],
			'Farthest upcoming event should be last'
		);
	}

	/**
	 * Test that include_unfinished parameter works correctly for past events.
	 *
	 * @covers ::adjust_sorting_for_past_events
	 *
	 * @return void
	 */
	public function test_include_unfinished_parameter_for_past_events(): void {
		$instance = Query::get_instance();

		// Create a currently running event.
		$running_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$running_event = new Event( $running_post->ID );
		$date          = new DateTime( 'now' );

		$params = array(
			'datetime_start' => $date->modify( '-1 day' )->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $date->modify( '+2 days' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$running_event->save_datetimes( $params );

		// Default behavior: currently running events should NOT appear in past events.
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'past',
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertNotContains(
			$running_post->ID,
			$query->posts,
			'Currently running event should NOT appear in past events by default'
		);

		// With include_unfinished=true: currently running events SHOULD appear.
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'past',
				'include_unfinished'     => true,
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertContains(
			$running_post->ID,
			$query->posts,
			'Currently running event SHOULD appear in past events when include_unfinished=true'
		);
	}

	/**
	 * Test that include_unfinished parameter handles integer values correctly.
	 *
	 * This test specifically prevents regression of the array_filter bug that
	 * removed integer 0 values from query parameters.
	 *
	 * @covers ::adjust_sorting_for_upcoming_events
	 * @covers ::adjust_sorting_for_past_events
	 *
	 * @return void
	 */
	public function test_include_unfinished_integer_values(): void {
		$instance = Query::get_instance();

		// Create a currently running event (using exact same pattern as working test).
		$running_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$running_event = new Event( $running_post->ID );
		$date          = new DateTime( 'now' );

		$params = array(
			'datetime_start' => $date->modify( '-1 day' )->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $date->modify( '+2 days' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$running_event->save_datetimes( $params );

		// First verify that boolean true works (like the original test).
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'past',
				'include_unfinished'     => true, // Boolean true.
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertContains(
			$running_post->ID,
			$query->posts,
			'Boolean true should include currently running events in past events (baseline test)'
		);

		// Now test integer 1 (should work the same as boolean true).
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'past',
				'include_unfinished'     => 1, // Integer 1.
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertContains(
			$running_post->ID,
			$query->posts,
			sprintf(
				'Integer 1 should include currently running events in past events. Found posts: %s, Looking for: %d',
				implode( ', ', $query->posts ),
				$running_post->ID
			)
		);

		// Test integer 0 value (exclude unfinished).
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'past',
				'include_unfinished'     => 0, // Integer 0.
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertNotContains(
			$running_post->ID,
			$query->posts,
			'Integer 0 should exclude currently running events from past events'
		);

		// Test for upcoming events with integer 0 (should exclude).
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'upcoming',
				'include_unfinished'     => 0, // Integer 0.
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertNotContains(
			$running_post->ID,
			$query->posts,
			'Integer 0 should exclude currently running events from upcoming events'
		);

		// Test for upcoming events with integer 1 (should include).
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'upcoming',
				'include_unfinished'     => 1, // Integer 1.
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertContains(
			$running_post->ID,
			$query->posts,
			'Integer 1 should include currently running events in upcoming events'
		);
	}

	/**
	 * Test default behavior for include_unfinished parameter.
	 *
	 * Upcoming events should include currently running events by default.
	 * Past events should exclude currently running events by default.
	 *
	 * @covers ::adjust_sorting_for_upcoming_events
	 * @covers ::adjust_sorting_for_past_events
	 *
	 * @return void
	 */
	public function test_include_unfinished_defaults(): void {
		$instance = Query::get_instance();

		// Create a currently running event.
		$running_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$running_event = new Event( $running_post->ID );

		// Set event to be currently running (started 2 hours ago, ends 2 hours from now).
		$params = array(
			'datetime_start' => gmdate( 'Y-m-d H:i:s', time() - ( 2 * HOUR_IN_SECONDS ) ), // Started 2 hours ago.
			'datetime_end'   => gmdate( 'Y-m-d H:i:s', time() + ( 2 * HOUR_IN_SECONDS ) ), // Ends 2 hours from now.
			'timezone'       => 'America/New_York',
		);
		$running_event->save_datetimes( $params );

		// Test upcoming events default behavior (should include currently running).
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'upcoming',
				// No include_unfinished parameter - test default.
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertContains(
			$running_post->ID,
			$query->posts,
			'Upcoming events should include currently running events by default'
		);

		// Test past events default behavior (should exclude currently running).
		$query = new WP_Query(
			array(
				'post_type'              => Event::POST_TYPE,
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				Query::EVENT_QUERY_PARAM => 'past',
				// No include_unfinished parameter - test default.
			)
		);
		$instance->prepare_event_query_before_execution( $query );
		$query->query( $query->query_vars );

		$this->assertNotContains(
			$running_post->ID,
			$query->posts,
			'Past events should exclude currently running events by default'
		);
	}

	/**
	 * Events with no row in the events table fall out of upcoming/past
	 * (they only show under the admin All view).
	 *
	 * Previously the admin variant of `adjust_event_sql` accepted an
	 * `include_no_date` flag that wove `OR post_id IS NULL` into the
	 * upcoming WHERE clause. That clause has been removed — the LEFT
	 * JOIN's NULL row naturally fails the `datetime_end_gmt >= NOW()`
	 * comparison, so no-date events are excluded from both buckets.
	 *
	 * @covers ::adjust_event_sql
	 *
	 * @return void
	 */
	public function test_no_date_events_excluded_from_upcoming_and_past(): void {
		$instance = Query::get_instance();

		$retval = $instance->adjust_event_sql(
			array(),
			'upcoming',
			'ASC',
			'datetime',
			true
		);
		$this->assertStringNotContainsString(
			'IS NULL',
			$retval['where'],
			'Upcoming query should not have an IS NULL escape hatch for no-date events.'
		);

		$retval = $instance->adjust_event_sql(
			array(),
			'past',
			'DESC',
			'datetime',
			true
		);
		$this->assertStringNotContainsString(
			'IS NOT NULL',
			$retval['where'],
			'Past query should not have an IS NOT NULL guard — no-date events are already excluded by the join.'
		);
	}

	/**
	 * Test that currently running events appear in upcoming query by default.
	 *
	 * @covers ::adjust_sorting_for_upcoming_events
	 * @covers ::adjust_sorting_for_past_events
	 * @covers ::get_upcoming_events
	 * @covers ::get_past_events
	 *
	 * @return void
	 */
	public function test_currently_running_events_in_upcoming_query(): void {
		$instance = Query::get_instance();

		// Create an event that started yesterday and ends tomorrow (currently running).
		$running_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$running_event = new Event( $running_post->ID );
		$date          = new DateTime( 'now' );

		$params = array(
			'datetime_start' => $date->modify( '-1 day' )->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $date->modify( '+2 days' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$running_event->save_datetimes( $params );

		// Create a truly future event.
		$future_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$future_event = new Event( $future_post->ID );
		$future_date  = new DateTime( 'tomorrow' );

		$params = array(
			'datetime_start' => $future_date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $future_date->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$future_event->save_datetimes( $params );

		// Create a truly past event.
		$past_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$past_event = new Event( $past_post->ID );
		$past_date  = new DateTime( 'yesterday' );

		$params = array(
			'datetime_start' => $past_date->modify( '-2 days' )->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $past_date->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$past_event->save_datetimes( $params );

		// The currently running event should appear in upcoming events.
		$upcoming = $instance->get_upcoming_events( 10 );
		$this->assertContains(
			$running_post->ID,
			$upcoming->posts,
			'Currently running event should appear in upcoming events query'
		);
		$this->assertContains(
			$future_post->ID,
			$upcoming->posts,
			'Future event should appear in upcoming events query'
		);
		$this->assertNotContains(
			$past_post->ID,
			$upcoming->posts,
			'Completely past event should not appear in upcoming events query'
		);

		// With the default inclusive=false for past events, currently running
		// events should NOT appear in past queries - only truly finished events.
		$past = $instance->get_past_events( 10 );
		$this->assertNotContains(
			$running_post->ID,
			$past->posts,
			'Currently running event should NOT appear in past events query by default'
		);
		$this->assertContains(
			$past_post->ID,
			$past->posts,
			'Completely past event should appear in past events query'
		);
		$this->assertNotContains(
			$future_post->ID,
			$past->posts,
			'Future event should not appear in past events query'
		);
	}

	/**
	 * Test venue filter sets tax_query when on a venue singular page.
	 *
	 * Covers the shadow_filter block when shadow_filter is non-empty,
	 * is_singular returns true for a venue post type, the queried object
	 * is a WP_Post, and no existing tax_query is present.
	 *
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_shadow_filter_sets_tax_query(): void {
		$instance = Query::get_instance();

		// Create a venue post to establish proper singular context.
		$venue_post_id = $this->factory->post->create(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'test-venue',
			)
		);

		// Navigate to the venue page so is_singular() and get_queried_object() work correctly.
		$this->go_to( get_permalink( $venue_post_id ) );

		// Build a mock query with shadow_filter set and no existing tax_query.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		$captured_tax_query = null;

		$query->expects( $this->any() )
			->method( 'get' )
			->willReturnCallback(
				static function ( $key ) {
					if ( 'shadow_filter' === $key ) {
						return true;
					}
					// Return null for tax_query so existing_tax_query is initialized as an empty array.
					return null;
				}
			);

		$query->expects( $this->any() )
			->method( 'set' )
			->willReturnCallback(
				static function ( $key, $value ) use ( &$captured_tax_query ) {
					if ( 'tax_query' === $key ) {
						$captured_tax_query = $value;
					}
				}
			);

		$instance->prepare_event_query_before_execution( $query );

		// Assert that set() was called with tax_query containing the venue term.
		$this->assertIsArray( $captured_tax_query, 'tax_query should have been set as an array.' );
		$this->assertCount( 1, $captured_tax_query, 'tax_query should contain exactly one entry.' );
		$this->assertSame(
			Venue::TAXONOMY,
			$captured_tax_query[0]['taxonomy'],
			'tax_query taxonomy should be the venue taxonomy.'
		);
		$this->assertSame( 'slug', $captured_tax_query[0]['field'], 'tax_query field should be slug.' );
		$this->assertContains(
			'_test-venue',
			$captured_tax_query[0]['terms'],
			'tax_query terms should contain the venue term slug.'
		);

		wp_delete_post( $venue_post_id, true );
	}

	/**
	 * Test venue filter merges with an existing tax_query on a venue singular page.
	 *
	 * Covers the branch where $existing_tax_query is already an array so that the
	 * venue entry is appended rather than replacing it.
	 *
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_shadow_filter_merges_with_existing_tax_query(): void {
		$instance = Query::get_instance();

		// Create a venue post to establish proper singular context.
		$venue_post_id = $this->factory->post->create(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'merge-venue',
			)
		);

		// Navigate to the venue page so is_singular() and get_queried_object() work correctly.
		$this->go_to( get_permalink( $venue_post_id ) );

		// Pre-existing tax_query entry to verify merging behavior.
		$pre_existing_entry = array(
			'taxonomy' => 'category',
			'field'    => 'slug',
			'terms'    => array( 'existing-term' ),
		);

		// Build a mock query with shadow_filter set and a pre-existing tax_query.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		$captured_tax_query = null;

		$query->expects( $this->any() )
			->method( 'get' )
			->willReturnCallback(
				static function ( $key ) use ( $pre_existing_entry ) {
					if ( 'shadow_filter' === $key ) {
						return true;
					}
					if ( 'tax_query' === $key ) {
						// Return an existing tax_query array to trigger the merge path.
						return array( $pre_existing_entry );
					}
					return null;
				}
			);

		$query->expects( $this->any() )
			->method( 'set' )
			->willReturnCallback(
				static function ( $key, $value ) use ( &$captured_tax_query ) {
					if ( 'tax_query' === $key ) {
						$captured_tax_query = $value;
					}
				}
			);

		$instance->prepare_event_query_before_execution( $query );

		// Assert that both the pre-existing entry and the new venue entry are present.
		$this->assertIsArray( $captured_tax_query, 'tax_query should have been set as an array.' );
		$this->assertCount(
			2,
			$captured_tax_query,
			'tax_query should contain the pre-existing entry plus the venue entry.'
		);
		$this->assertSame(
			'category',
			$captured_tax_query[0]['taxonomy'],
			'First tax_query entry should be the pre-existing one.'
		);
		$this->assertSame(
			Venue::TAXONOMY,
			$captured_tax_query[1]['taxonomy'],
			'Second tax_query entry should be the venue taxonomy.'
		);
		$this->assertSame( 'slug', $captured_tax_query[1]['field'], 'Venue tax_query field should be slug.' );
		$this->assertContains(
			'_merge-venue',
			$captured_tax_query[1]['terms'],
			'Venue tax_query terms should contain the expected term slug.'
		);

		wp_delete_post( $venue_post_id, true );
	}

	/**
	 * Test venue filter is skipped when shadow_filter query var is empty.
	 *
	 * Covers the early-exit branch of the venue filter block when shadow_filter
	 * is not set so that tax_query is never modified.
	 *
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_shadow_filter_skipped_when_empty(): void {
		$instance = Query::get_instance();

		// Create a venue post to establish proper singular context.
		$venue_post_id = $this->factory->post->create(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'skip-venue',
			)
		);

		// Navigate to the venue page so the singular context is active.
		$this->go_to( get_permalink( $venue_post_id ) );

		// Build a mock query with shadow_filter intentionally absent.
		$query = $this->getMockBuilder( 'WP_Query' )
			->setMethods( array( 'is_main_query', 'get', 'set' ) )
			->getMock();

		$query->expects( $this->any() )
			->method( 'is_main_query' )
			->willReturn( true );

		$query->expects( $this->any() )
			->method( 'get' )
			->willReturn( null );

		$set_called_with_tax_query = false;

		$query->expects( $this->any() )
			->method( 'set' )
			->willReturnCallback(
				static function ( $key, $value ) use ( &$set_called_with_tax_query ) {
					unset( $value );
					if ( 'tax_query' === $key ) {
						$set_called_with_tax_query = true;
					}
				}
			);

		$instance->prepare_event_query_before_execution( $query );

		// Assert that tax_query was never touched because the shadow_filter is empty.
		$this->assertFalse( $set_called_with_tax_query, 'tax_query should not be set when shadow_filter is empty.' );

		wp_delete_post( $venue_post_id, true );
	}
}
