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
				'type'     => 'filter',
				'name'     => 'posts_clauses',
				'priority' => 10,
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
		$this->assertSame( 'upcoming', $response->query['gatherpress_events_query'], 'Failed to assert query is upcoming.' );
		$this->assertSame( 'gatherpress_event', $response->query['post_type'], 'Failed to assert post type is gatherpress_event.' );
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
		$this->assertSame( 'past', $response->query['gatherpress_events_query'], 'Failed to assert query is past.' );
		$this->assertSame( 'gatherpress_event', $response->query['post_type'], 'Failed to assert post type is gatherpress_event.' );
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

		$query->set( 'gatherpress_events_query', 'upcoming' );
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

		$query->set( 'gatherpress_events_query', 'past' );
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
				function ( $key ) {
					return 'gatherpress_events_query' === $key ? 'past' : null;
				}
			);

		$page_data = array(
			'pages' => array(
				'past_events' => wp_json_encode(
					array(
						(object) array( 'id' => 123 ),
					)
				),
			),
		);

		add_option( 'gatherpress_general', $page_data );

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

		delete_option( 'gatherpress_general' );
	}

	/**
	 * Test query with invalid general options.
	 *
	 * @since  1.0.0
	 * @covers ::prepare_event_query_before_execution
	 *
	 * @return void
	 */
	public function test_prepare_query_with_invalid_general_options(): void {
		$instance             = Event_Query::get_instance();
		$query                = new WP_Query();
		$query->is_main_query = true;

		add_option( 'gatherpress_general', 'invalid' );

		$instance->prepare_event_query_before_execution( $query );

		$this->assertEmpty(
			$query->get( 'post_type' ),
			'Should not modify query when general option is invalid'
		);

		delete_option( 'gatherpress_general' );
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
		global $wp_query;

		$instance = Event_Query::get_instance();

		$this->mock->user( false, 'admin' );
		$response = $instance->adjust_admin_event_sorting( array() );
		$this->assertEmpty( $response, 'Failed to assert array is not empty' );

		$this->mock->user( true, 'admin' );

		// Set 'orderby' admin query to 'datetime'.
		$wp_query->set( 'orderby', 'datetime' );

		// Run function with empty array passed as 'pieces' argument.
		$response = $instance->adjust_admin_event_sorting( array() );

		// Assert that an array was generated from the adjustsql argument. todo: make this test more meaningful.
		$this->assertNotEmpty( $response, 'Failed to assert array is empty' );
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

		$this->assertStringContainsString( 'DESC', $retval['orderby'] );
		$this->assertEmpty( $retval['where'] );

		$retval = $instance->adjust_event_sql( array(), 'past', 'desc' );

		$this->assertStringContainsString( 'DESC', $retval['orderby'] );
		$this->assertStringContainsString( "AND `{$table}`.datetime_end_gmt <", $retval['where'] );

		$retval = $instance->adjust_event_sql( array(), 'upcoming', 'ASC' );

		$this->assertStringContainsString( 'ASC', $retval['orderby'] );
		$this->assertStringContainsString( "AND `{$table}`.datetime_end_gmt >=", $retval['where'] );
	}
}
