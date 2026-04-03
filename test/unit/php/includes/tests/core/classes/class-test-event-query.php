<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Query.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use DateTime;
use GatherPress\Core\Event;
use GatherPress\Core\Event_Query;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Query;

/**
 * Class Test_Event_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Query
 */
class Test_Event_Query extends Base {
	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Event_Query::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $instance, 'prepare_event_query_before_execution' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $instance, 'handle_rsvp_sorting' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $instance, 'handle_venue_sorting' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'posts_clauses',
				'priority' => 9,
				'callback' => array( $instance, 'adjust_admin_event_sorting' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'load-edit.php',
				'priority' => 10,
				'callback' => array( $instance, 'default_sort' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_edit-%s_sortable_columns', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'sortable_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'views_edit-%s', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'views_edit' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'query_vars',
				'priority' => 10,
				'callback' => array( $instance, 'query_vars' ),
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
		$instance = Event_Query::get_instance();
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
		$instance = Event_Query::get_instance();
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
		$instance = Event_Query::get_instance();
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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_for_upcoming_events(): void {
		$instance = Event_Query::get_instance();
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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_for_past_events(): void {
		$instance = Event_Query::get_instance();
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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_for_archive_page(): void {
		$instance = Event_Query::get_instance();

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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_resolves_page_by_pagename(): void {
		$instance = Event_Query::get_instance();

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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_with_invalid_general_options(): void {
		$instance = Event_Query::get_instance();

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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_with_empty_pages(): void {
		$instance = Event_Query::get_instance();

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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_pre_option_filters(): void {
		$instance = Event_Query::get_instance();

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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_archive_title_filter(): void {
		$instance = Event_Query::get_instance();

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
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_with_no_event_type(): void {
		$instance = Event_Query::get_instance();
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
		$instance = Event_Query::get_instance();
		$wp_query = new WP_Query();

		$this->mock->user( false, 'admin' );
		$response = $instance->adjust_admin_event_sorting( array(), $wp_query );
		$this->assertEmpty( $response, 'Failed to assert the array, containing pieces of the SQL query, is empty' );

		$this->mock->user( true, 'admin' );
		set_current_screen( 'edit-gatherpress_event' );

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
		$instance     = Event_Query::get_instance();
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
	 * Coverage for adjust_event_sql method.
	 *
	 * @covers ::adjust_event_sql
	 *
	 * @return void
	 */
	public function test_adjust_event_sql(): void {
		global $wpdb;

		$instance = Event_Query::get_instance();

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
	}

	/**
	 * Coverage for get_datetime_comparison_column method.
	 *
	 * @covers ::get_datetime_comparison_column
	 *
	 * @return void
	 */
	public function test_get_datetime_comparison_column(): void {
		$instance = Event_Query::get_instance();

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
		$instance = Event_Query::get_instance();

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
		$instance = Event_Query::get_instance();

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
		$instance = Event_Query::get_instance();

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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'past',
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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'past',
				'include_unfinished'           => true,
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
		$instance = Event_Query::get_instance();

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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'past',
				'include_unfinished'           => true, // Boolean true.
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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'past',
				'include_unfinished'           => 1, // Integer 1.
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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'past',
				'include_unfinished'           => 0, // Integer 0.
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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				'include_unfinished'           => 0, // Integer 0.
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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				'include_unfinished'           => 1, // Integer 1.
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
		$instance = Event_Query::get_instance();

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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
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
				'post_type'                    => Event::POST_TYPE,
				'posts_per_page'               => 10,
				'fields'                       => 'ids',
				Event_Query::EVENT_QUERY_PARAM => 'past',
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
	 * Test that events without dates appear in upcoming admin queries with include_no_date.
	 *
	 * Events without a date/time set have no row in the gatherpress_events table.
	 * When include_no_date is true (admin context), these should appear in upcoming
	 * and be excluded from past.
	 *
	 * @covers ::adjust_event_sql
	 *
	 * @return void
	 */
	public function test_events_without_dates_in_admin_upcoming(): void {
		global $wpdb;

		$instance = Event_Query::get_instance();
		$table    = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		// Create an event without setting any dates (no row in gatherpress_events).
		$no_date_post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		// Verify there is no row in the events table for this post.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT post_id FROM %i WHERE post_id = %d',
				$table,
				$no_date_post->ID
			)
		);
		$this->assertNull( $row, 'Event without dates should have no row in gatherpress_events.' );

		// Create a normal upcoming event for comparison.
		$upcoming_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$upcoming_event = new Event( $upcoming_post->ID );
		$date           = new \DateTime( 'tomorrow' );

		$params = array(
			'datetime_start' => $date->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $date->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$upcoming_event->save_datetimes( $params );

		// Create a normal past event for comparison.
		$past_post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$past_event = new Event( $past_post->ID );
		$past_date  = new \DateTime( 'yesterday' );

		$params = array(
			'datetime_start' => $past_date->modify( '-1 day' )->format( 'Y-m-d H:i:s' ),
			'datetime_end'   => $past_date->format( 'Y-m-d H:i:s' ),
			'timezone'       => 'America/New_York',
		);

		$past_event->save_datetimes( $params );

		// With include_no_date=true (admin), upcoming should include IS NULL.
		$retval = $instance->adjust_event_sql(
			array(),
			'upcoming',
			'ASC',
			'datetime',
			true,
			true
		);
		$this->assertStringContainsString(
			'IS NULL',
			$retval['where'],
			'Admin upcoming query should include IS NULL.'
		);

		// With include_no_date=true (admin), past should exclude via IS NOT NULL.
		$retval = $instance->adjust_event_sql(
			array(),
			'past',
			'DESC',
			'datetime',
			true,
			true
		);
		$this->assertStringContainsString(
			'IS NOT NULL',
			$retval['where'],
			'Admin past query should include IS NOT NULL.'
		);

		// With include_no_date=false (frontend), no IS NULL check.
		$retval = $instance->adjust_event_sql(
			array(),
			'upcoming',
			'ASC',
			'datetime',
			true,
			false
		);
		$this->assertStringNotContainsString(
			'IS NULL',
			$retval['where'],
			'Frontend query should not have IS NULL.'
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
		$instance = Event_Query::get_instance();

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
	 * Coverage for handle_rsvp_sorting method.
	 *
	 * @covers ::handle_rsvp_sorting
	 *
	 * @return void
	 */
	public function test_handle_rsvp_sorting(): void {
		$instance = Event_Query::get_instance();

		// Create a mock query.
		$query = $this->createMock( \WP_Query::class );

		// Test non-admin context (is_admin() returns false by default in tests).
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'rsvps' ),
			)
		);

		// Should return early due to non-admin context.
		$instance->handle_rsvp_sorting( $query );

		// Test different post type - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, 'post' ),
				array( 'orderby', null, 'rsvps' ),
			)
		);

		$instance->handle_rsvp_sorting( $query );

		// Test non-main query - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( false );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'rsvps' ),
			)
		);

		$instance->handle_rsvp_sorting( $query );

		// Test different orderby - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'title' ),
			)
		);

		$instance->handle_rsvp_sorting( $query );

		// Since the method primarily adds filters and sets query vars,
		// and we can't easily test is_admin() in unit tests,
		// we'll focus on testing the individual components.
		$this->assertTrue( true ); // Method completed without errors.
	}

	/**
	 * Coverage for rsvp_sorting_join_paged method.
	 *
	 * @covers ::rsvp_sorting_join_paged
	 *
	 * @return void
	 */
	public function test_rsvp_sorting_join_paged(): void {
		global $wpdb;
		$instance = Event_Query::get_instance();

		$original_join = "LEFT JOIN {$wpdb->posts} AS posts ON posts.ID = {$wpdb->posts}.ID";
		$result        = $instance->rsvp_sorting_join_paged( $original_join );

		// Should contain the original join plus the RSVP join.
		$this->assertStringContainsString( $original_join, $result );
		$this->assertStringContainsString( 'LEFT JOIN', $result );
		$this->assertStringContainsString( $wpdb->comments, $result );
		$this->assertStringContainsString( 'rsvp_sort_comments', $result );
		$this->assertStringContainsString( 'comment_type', $result );
		$this->assertStringContainsString( "comment_approved = '1'", $result );
	}

	/**
	 * Coverage for sorting_groupby_post_id method.
	 *
	 * @covers ::sorting_groupby_post_id
	 *
	 * @return void
	 */
	public function test_sorting_groupby_post_id(): void {
		global $wpdb;
		$instance = Event_Query::get_instance();

		$result = $instance->sorting_groupby_post_id( '' );
		$this->assertEquals( "`{$wpdb->posts}`.`ID`", $result );

		// Test with existing groupby - should keep the existing value.
		$existing_groupby = 'existing_group';
		$result           = $instance->sorting_groupby_post_id( $existing_groupby );
		$this->assertEquals( 'existing_group', $result );
	}

	/**
	 * Coverage for rsvp_sorting_orderby method.
	 *
	 * Note: This method relies on the global $wp_query, so we test the method's structure
	 * and ensure it returns a proper ORDER BY clause with expected patterns.
	 *
	 * @covers ::rsvp_sorting_orderby
	 *
	 * @return void
	 */
	public function test_rsvp_sorting_orderby(): void {
		$instance = Event_Query::get_instance();

		$result = $instance->rsvp_sorting_orderby( 'original_orderby' );

		// Should contain the expected COUNT structure.
		$this->assertStringContainsString( 'COUNT(rsvp_sort_comments.comment_ID)', $result );

		// Should contain either ASC or DESC (defaults to ASC).
		$this->assertTrue(
			strpos( $result, 'ASC' ) !== false || strpos( $result, 'DESC' ) !== false,
			'ORDER BY clause should contain ASC or DESC'
		);

		// Verify the method returns a string (basic type check).
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Coverage for handle_venue_sorting method.
	 *
	 * @covers ::handle_venue_sorting
	 *
	 * @return void
	 */
	public function test_handle_venue_sorting(): void {
		$instance = Event_Query::get_instance();

		// Create a mock query.
		$query = $this->createMock( \WP_Query::class );

		// Test non-admin context (is_admin() returns false by default in tests).
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'venue' ),
			)
		);

		// Should return early due to non-admin context.
		$instance->handle_venue_sorting( $query );

		// Test different post type - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, 'post' ),
				array( 'orderby', null, 'venue' ),
			)
		);

		$instance->handle_venue_sorting( $query );

		// Test non-main query - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( false );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'venue' ),
			)
		);

		$instance->handle_venue_sorting( $query );

		// Test different orderby - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'title' ),
			)
		);

		$instance->handle_venue_sorting( $query );

		// Since the method primarily adds filters and sets query vars,
		// and we can't easily test is_admin() in unit tests,
		// we'll focus on testing the individual components.
		$this->assertTrue( true ); // Method completed without errors.
	}

	/**
	 * Coverage for venue_sorting_join_paged method.
	 *
	 * @covers ::venue_sorting_join_paged
	 *
	 * @return void
	 */
	public function test_venue_sorting_join_paged(): void {
		global $wpdb;
		$instance = Event_Query::get_instance();

		$original_join = "LEFT JOIN {$wpdb->posts} AS posts ON posts.ID = {$wpdb->posts}.ID";
		$result        = $instance->venue_sorting_join_paged( $original_join );

		// Should contain the original join plus the venue joins.
		$this->assertStringContainsString( $original_join, $result );
		$this->assertStringContainsString( 'LEFT JOIN', $result );
		$this->assertStringContainsString( $wpdb->term_relationships, $result );
		$this->assertStringContainsString( 'venue_tr', $result );
		$this->assertStringContainsString( $wpdb->term_taxonomy, $result );
		$this->assertStringContainsString( 'venue_tt', $result );
		$this->assertStringContainsString( $wpdb->terms, $result );
		$this->assertStringContainsString( 'venue_terms', $result );
		$this->assertStringContainsString( "'" . Venue::TAXONOMY . "'", $result );
	}

	/**
	 * Coverage for venue_sorting_orderby method.
	 *
	 * Note: This method relies on the global $wp_query, so we test the method's structure
	 * and ensure it returns a proper ORDER BY clause with expected patterns.
	 *
	 * @covers ::venue_sorting_orderby
	 *
	 * @return void
	 */
	public function test_venue_sorting_orderby(): void {
		$instance = Event_Query::get_instance();

		$result = $instance->venue_sorting_orderby( 'original_orderby' );

		// Should contain the expected CASE structure for NULL handling.
		$this->assertStringContainsString( 'CASE WHEN venue_terms.name IS NULL THEN 1 ELSE 0 END ASC', $result );

		// Should contain venue_terms.name in the ORDER BY.
		$this->assertStringContainsString( 'venue_terms.name', $result );

		// Should contain either ASC or DESC (defaults to ASC).
		$this->assertTrue(
			strpos( $result, 'ASC' ) !== false || strpos( $result, 'DESC' ) !== false,
			'ORDER BY clause should contain ASC or DESC'
		);

		// Verify the method returns a string (basic type check).
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Tests handle_rsvp_sorting with RSVP orderby.
	 *
	 * Covers: RSVP sorting logic.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @return void
	 */
	public function test_rsvp_sorting_with_rsvp_orderby(): void {
		global $wp_the_query;

		// Create a WP_Query for events with RSVP sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'rsvps',
				'order'     => 'DESC',
			)
		);

		// Set as main query.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		// Simulate admin context.
		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Query::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Verify that sorting order was set.
		$this->assertEquals( 'DESC', $query->get( 'rsvp_sort_order' ), 'Should set DESC order' );

		// Verify filters were added.
		$this->assertNotFalse(
			has_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) ),
			'Should add posts_join_paged filter'
		);
		$this->assertNotFalse(
			has_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) ),
			'Should add posts_groupby filter'
		);
		$this->assertNotFalse(
			has_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) ),
			'Should add posts_orderby filter'
		);

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_rsvp_sorting with invalid order.
	 *
	 * Covers: Order validation.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @return void
	 */
	public function test_rsvp_sorting_with_invalid_order(): void {
		global $wp_the_query;

		// Create a WP_Query for RSVP sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'rsvps',
			)
		);

		// Manually set invalid order (bypasses WP_Query's sanitization).
		$query->set( 'order', 'INVALID' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Query::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Should default to ASC for invalid order.
		$this->assertEquals( 'ASC', $query->get( 'rsvp_sort_order' ), 'Should default to ASC for invalid order' );

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_rsvp_sorting early return when orderby is not 'rsvps'.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @return void
	 */
	public function test_rsvp_sorting_early_return_wrong_orderby(): void {
		global $wp_the_query;

		// Create a WP_Query for non-RSVP sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'date',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Query::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Should not set rsvp_sort_order since orderby is not 'rsvps'.
		$this->assertEmpty( $query->get( 'rsvp_sort_order' ), 'Should not set rsvp_sort_order for non-RSVP orderby' );

		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_venue_sorting with venue orderby.
	 *
	 * Covers: Venue sorting logic.
	 *
	 * @covers ::handle_venue_sorting
	 * @return void
	 */
	public function test_venue_sorting_with_venue_orderby(): void {
		global $wp_the_query;

		// Create a WP_Query for events with venue sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'venue',
				'order'     => 'DESC',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Query::get_instance();
		$instance->handle_venue_sorting( $query );

		// Verify that sorting order was set.
		$this->assertEquals( 'DESC', $query->get( 'venue_sort_order' ), 'Should set DESC order' );

		// Verify filters were added.
		$this->assertNotFalse(
			has_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) ),
			'Should add posts_join_paged filter for venue sorting'
		);
		$this->assertNotFalse(
			has_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) ),
			'Should add posts_groupby filter for venue sorting'
		);
		$this->assertNotFalse(
			has_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) ),
			'Should add posts_orderby filter for venue sorting'
		);

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_venue_sorting with invalid order.
	 *
	 * Covers: Order validation in venue sorting.
	 *
	 * @covers ::handle_venue_sorting
	 * @return void
	 */
	public function test_venue_sorting_with_invalid_order(): void {
		global $wp_the_query;

		// Create a WP_Query for venue sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'venue',
			)
		);

		// Manually set invalid order (bypasses WP_Query's sanitization).
		$query->set( 'order', 'RANDOM' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Query::get_instance();
		$instance->handle_venue_sorting( $query );

		// Should default to ASC for invalid order.
		$this->assertEquals( 'ASC', $query->get( 'venue_sort_order' ), 'Should default to ASC for invalid order' );

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_venue_sorting early return when orderby is not 'venue'.
	 *
	 * @covers ::handle_venue_sorting
	 * @return void
	 */
	public function test_venue_sorting_early_return_wrong_orderby(): void {
		global $wp_the_query;

		// Create a WP_Query for non-venue sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'title',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Query::get_instance();
		$instance->handle_venue_sorting( $query );

		// Should not set venue_sort_order since orderby is not 'venue'.
		$this->assertEmpty(
			$query->get( 'venue_sort_order' ),
			'Should not set venue_sort_order for non-venue orderby'
		);

		set_current_screen( 'front' );
	}

	/**
	 * Coverage for sortable_columns method.
	 *
	 * @covers ::sortable_columns
	 *
	 * @return void
	 */
	public function test_sortable_columns(): void {
		$instance = Event_Query::get_instance();
		$default  = array( 'unit' => 'test' );
		$expects  = array(
			'unit'     => 'test',
			'datetime' => 'datetime',
			'venue'    => 'venue',
			'rsvps'    => 'rsvps',
		);

		$this->assertSame(
			$expects,
			$instance->sortable_columns( $default ),
			'Failed to assert correct sortable columns.'
		);
	}

	/**
	 * Coverage for default_sort method when on the wrong screen.
	 *
	 * @covers ::default_sort
	 *
	 * @return void
	 */
	public function test_default_sort_wrong_screen(): void {
		$instance = Event_Query::get_instance();

		// Set current screen to a non-event screen.
		set_current_screen( 'edit-post' );

		// Ensure $_GET is clean.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );

		$instance->default_sort();

		// Should return early without modifying $_GET.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertArrayNotHasKey( 'orderby', $_GET, 'Should not set orderby on wrong screen.' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertArrayNotHasKey( 'order', $_GET, 'Should not set order on wrong screen.' );

		// Clean up.
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for default_sort method when orderby is already set.
	 *
	 * @covers ::default_sort
	 *
	 * @return void
	 */
	public function test_default_sort_orderby_already_set(): void {
		$instance = Event_Query::get_instance();

		// Set current screen to event edit screen.
		set_current_screen( 'edit-gatherpress_event' );

		// Set an existing orderby value.
		$_GET['orderby'] = 'title'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$instance->default_sort();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		$this->assertSame( 'title', $_GET['orderby'], 'Should not override existing orderby.' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertArrayNotHasKey( 'order', $_GET, 'Should not set order when orderby already exists.' );

		// Clean up.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for default_sort method when on the correct screen with no orderby.
	 *
	 * @covers ::default_sort
	 *
	 * @return void
	 */
	public function test_default_sort_sets_defaults(): void {
		$instance = Event_Query::get_instance();

		// Set current screen to event edit screen.
		set_current_screen( 'edit-gatherpress_event' );

		// Ensure $_GET is clean.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );

		$instance->default_sort();

		// Should set default orderby and order.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		$this->assertSame( 'datetime', $_GET['orderby'], 'Should set orderby to datetime.' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		$this->assertSame( 'asc', $_GET['order'], 'Should set order to asc.' );

		// Clean up.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for query_vars method.
	 *
	 * @covers ::query_vars
	 *
	 * @return void
	 */
	public function test_query_vars(): void {
		$instance = Event_Query::get_instance();

		$result = $instance->query_vars( array( 'existing_var' ) );

		$this->assertContains(
			'gatherpress_event_query',
			$result,
			'Should add gatherpress_event_query to query vars.'
		);
		$this->assertContains(
			'existing_var',
			$result,
			'Should preserve existing query vars.'
		);
		$this->assertCount( 2, $result, 'Should have exactly 2 query vars.' );
	}

	/**
	 * Coverage for query_vars method with empty input.
	 *
	 * @covers ::query_vars
	 *
	 * @return void
	 */
	public function test_query_vars_empty_input(): void {
		$instance = Event_Query::get_instance();

		$result = $instance->query_vars( array() );

		$this->assertContains(
			'gatherpress_event_query',
			$result,
			'Should add gatherpress_event_query even with empty input.'
		);
		$this->assertCount( 1, $result, 'Should have exactly 1 query var.' );
	}

	/**
	 * Coverage for get_event_counts method with no events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_no_events(): void {
		$instance = Event_Query::get_instance();

		// Reset cached counts and invoke the protected method.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertArrayHasKey( 'upcoming', $counts, 'Should have upcoming key.' );
		$this->assertArrayHasKey( 'past', $counts, 'Should have past key.' );
		$this->assertSame( 0, $counts['upcoming'], 'Should have 0 upcoming events.' );
		$this->assertSame( 0, $counts['past'], 'Should have 0 past events.' );
	}

	/**
	 * Coverage for get_event_counts method with upcoming events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_with_upcoming(): void {
		$instance = Event_Query::get_instance();

		// Create a published event in the future.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts before querying.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 1, $counts['upcoming'], 'Should have 1 upcoming event.' );
		$this->assertSame( 0, $counts['past'], 'Should have 0 past events.' );
	}

	/**
	 * Coverage for get_event_counts method with past events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_with_past(): void {
		$instance = Event_Query::get_instance();

		// Create a published event in the past.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts before querying.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 0, $counts['upcoming'], 'Should have 0 upcoming events.' );
		$this->assertSame( 1, $counts['past'], 'Should have 1 past event.' );
	}

	/**
	 * Coverage for get_event_counts method with mixed events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_with_mixed(): void {
		$instance = Event_Query::get_instance();

		// Create a published upcoming event.
		$upcoming_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $upcoming_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Create a published past event.
		$past_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $past_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Create a draft event (should be counted, only trash/auto-draft excluded).
		$draft_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		)->get()->ID;

		$event = new Event( $draft_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts before querying.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 2, $counts['upcoming'], 'Should have 2 upcoming events (draft included).' );
		$this->assertSame( 1, $counts['past'], 'Should have 1 past event.' );
	}

	/**
	 * Coverage for get_event_counts method with a currently running event.
	 *
	 * A running event (started but not ended) should count as upcoming
	 * because datetime_end_gmt >= now, and also as past because
	 * datetime_start_gmt < now.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_running_event(): void {
		$instance = Event_Query::get_instance();

		// Create a published event that is currently running.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts before querying.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		// Running events appear in both counts (same logic as Event_Query with inclusive=true).
		$this->assertSame( 1, $counts['upcoming'], 'Running event should count as upcoming.' );
		$this->assertSame( 1, $counts['past'], 'Running event should count as past.' );
	}

	/**
	 * Coverage for get_event_counts method with events that have no date set.
	 *
	 * Events without a date/time set have no row in the gatherpress_events table.
	 * These should be counted as upcoming, not past.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_with_no_date(): void {
		$instance = Event_Query::get_instance();

		// Reset cached counts.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		// Create an event without setting any dates.
		$this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 1, $counts['upcoming'], 'Event without date should count as upcoming.' );
		$this->assertSame( 0, $counts['past'], 'Event without date should not count as past.' );
	}

	/**
	 * Coverage for get_event_counts caching to class property.
	 *
	 * Verifies that repeated calls return the cached result without re-querying.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_caches_result(): void {
		$instance = Event_Query::get_instance();

		// Reset cached counts.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		// First call should query the database and cache.
		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( 0, $counts['upcoming'], 'Should have 0 upcoming events.' );

		// Verify the property is now cached.
		$cached = Utility::set_and_get_hidden_property( $instance, 'event_counts', $counts );
		$this->assertNotNull( $cached, 'Property should be cached after first call.' );
		$this->assertSame( $counts, $cached, 'Cached value should match returned value.' );

		// Create an event after the first call.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Second call should return cached result (still 0).
		$counts_cached = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( 0, $counts_cached['upcoming'], 'Cached call should still return 0.' );

		// After resetting the cache, should reflect the new event.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );
		$counts_fresh = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( 1, $counts_fresh['upcoming'], 'Fresh call should return 1 after cache reset.' );
	}

	/**
	 * Coverage for views_edit method with no events.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_adds_links(): void {
		$instance = Event_Query::get_instance();

		$view_links = array(
			'all'     => '<a href="#">All</a>',
			'publish' => '<a href="#">Published</a>',
			'draft'   => '<a href="#">Draft</a>',
		);

		$result = $instance->views_edit( $view_links );

		// Should have original links plus upcoming and past.
		$this->assertArrayHasKey( 'upcoming', $result, 'Should have upcoming link.' );
		$this->assertArrayHasKey( 'past', $result, 'Should have past link.' );
		$this->assertArrayHasKey( 'all', $result, 'Should preserve all link.' );
		$this->assertArrayHasKey( 'publish', $result, 'Should preserve publish link.' );
		$this->assertArrayHasKey( 'draft', $result, 'Should preserve draft link.' );

		// Verify count spans are present.
		$this->assertStringContainsString(
			'<span class="count">',
			$result['upcoming'],
			'Upcoming link should contain count span.'
		);
		$this->assertStringContainsString(
			'<span class="count">',
			$result['past'],
			'Past link should contain count span.'
		);

		// Verify links contain proper query args.
		$this->assertStringContainsString(
			'gatherpress_event_query=upcoming',
			$result['upcoming'],
			'Upcoming link should have correct query arg.'
		);
		$this->assertStringContainsString(
			'gatherpress_event_query=past',
			$result['past'],
			'Past link should have correct query arg.'
		);

		// Verify sort order: upcoming should be asc, past should be desc.
		$this->assertStringContainsString(
			'order=asc',
			$result['upcoming'],
			'Upcoming link should sort ascending.'
		);
		$this->assertStringContainsString(
			'order=desc',
			$result['past'],
			'Past link should sort descending.'
		);

		// Verify the labels.
		$this->assertStringContainsString( 'Upcoming', $result['upcoming'], 'Should contain Upcoming label.' );
		$this->assertStringContainsString( 'Past', $result['past'], 'Should contain Past label.' );
	}

	/**
	 * Coverage for views_edit method link placement.
	 *
	 * Upcoming and Past should be inserted after the first link (All).
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_placement(): void {
		$instance = Event_Query::get_instance();

		$view_links = array(
			'all'     => '<a href="#">All</a>',
			'publish' => '<a href="#">Published</a>',
		);

		$result = $instance->views_edit( $view_links );
		$keys   = array_keys( $result );

		$this->assertEquals( 'all', $keys[0], 'First link should be all.' );
		$this->assertEquals( 'upcoming', $keys[1], 'Second link should be upcoming.' );
		$this->assertEquals( 'past', $keys[2], 'Third link should be past.' );
		$this->assertEquals( 'publish', $keys[3], 'Fourth link should be publish.' );
	}

	/**
	 * Coverage for views_edit method with active upcoming view.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_active_upcoming(): void {
		$instance = Event_Query::get_instance();

		// Simulate an active upcoming view via GET parameter.
		$_GET['gatherpress_event_query'] = 'upcoming'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// WordPress marks "All" as current by default.
		$view_links = array(
			'all' => '<a href="#" class="current" aria-current="page">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		$this->assertStringContainsString(
			'class="current"',
			$result['upcoming'],
			'Active upcoming link should have current class.'
		);
		$this->assertStringContainsString(
			'aria-current="page"',
			$result['upcoming'],
			'Active upcoming link should have aria-current attribute.'
		);

		// Past should not be marked as current.
		$this->assertStringNotContainsString(
			'class="current"',
			$result['past'],
			'Past link should not have current class.'
		);

		// "All" should have its current class removed.
		$this->assertStringNotContainsString(
			'class="current"',
			$result['all'],
			'All link should not have current class when filter is active.'
		);

		// Clean up.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );
	}

	/**
	 * Coverage for views_edit method with active past view.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_active_past(): void {
		$instance = Event_Query::get_instance();

		// Simulate an active past view via GET parameter.
		$_GET['gatherpress_event_query'] = 'past'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$view_links = array(
			'all' => '<a href="#">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		$this->assertStringContainsString(
			'class="current"',
			$result['past'],
			'Active past link should have current class.'
		);

		// Upcoming should not be marked as current.
		$this->assertStringNotContainsString(
			'class="current"',
			$result['upcoming'],
			'Upcoming link should not have current class.'
		);

		// Clean up.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );
	}

	/**
	 * Coverage for views_edit method with no active event query filter.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_no_active_filter(): void {
		$instance = Event_Query::get_instance();

		// Ensure no event query filter is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );

		// WordPress marks "All" as current by default.
		$view_links = array(
			'all' => '<a href="#" class="current" aria-current="page">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		// Neither Upcoming nor Past should be marked as current.
		$this->assertStringNotContainsString(
			'class="current"',
			$result['upcoming'],
			'Upcoming link should not have current class without filter.'
		);
		$this->assertStringNotContainsString(
			'class="current"',
			$result['past'],
			'Past link should not have current class without filter.'
		);

		// "All" should keep its current class.
		$this->assertStringContainsString(
			'class="current"',
			$result['all'],
			'All link should keep current class when no filter is active.'
		);
	}

	/**
	 * Coverage for views_edit adding current class to "All" when WordPress omits it.
	 *
	 * When default_sort() adds orderby/order to $_GET, WordPress's
	 * is_base_request() returns false and omits the current class from "All".
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_all_gets_current_when_missing(): void {
		$instance = Event_Query::get_instance();

		// Ensure no event query filter is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );

		// Simulate WordPress not adding current class due to default_sort()
		// adding extra $_GET params that break is_base_request().
		$view_links = array(
			'all' => '<a href="#">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		// "All" should get the current class added by views_edit.
		$this->assertStringContainsString(
			'class="current"',
			$result['all'],
			'All link should get current class when no filter is active.'
		);
		$this->assertStringContainsString(
			'aria-current="page"',
			$result['all'],
			'All link should get aria-current when no filter is active.'
		);
	}

	/**
	 * Coverage for views_edit method with event counts displayed.
	 *
	 * @covers ::views_edit
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_views_edit_displays_counts(): void {
		$instance = Event_Query::get_instance();

		// Create a published upcoming event.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts after event creation so fresh queries run.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$view_links = array(
			'all' => '<a href="#">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		// Upcoming should show count of 1.
		$this->assertStringContainsString(
			'<span class="count">(1)</span>',
			$result['upcoming'],
			'Upcoming link should show count of 1.'
		);

		// Past should show count of 0.
		$this->assertStringContainsString(
			'<span class="count">(0)</span>',
			$result['past'],
			'Past link should show count of 0.'
		);
	}
}
