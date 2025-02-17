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
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

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
	 * Coverage for adjust_admin_event_sorting method.
	 *
	 * @covers ::adjust_admin_event_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_event_sorting(): void {
		$instance = Event_Query::get_instance();
		global $wp_query;

		$this->mock->user( false, 'admin' );
		$response = $instance->adjust_admin_event_sorting( array(), $wp_query );
		$this->assertEmpty( $response, 'Failed to assert array is not empty' );

		$this->mock->user( true, 'admin' );

		// Set 'orderby' admin query to 'datetime'.
		$wp_query->set( 'orderby', 'datetime' );

		// Run function with empty array passed as 'pieces' argument.
		$response = $instance->adjust_admin_event_sorting( array(), $wp_query );

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
}
