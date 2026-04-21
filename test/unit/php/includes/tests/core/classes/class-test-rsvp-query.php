<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp_Query.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Query;
use GatherPress\Tests\Base;
use stdClass;
use WP_Comment;
use WP_Comment_Query;

/**
 * Class Test_Rsvp_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp_Query
 */
class Test_Rsvp_Query extends Base {
	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Rsvp_Query::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'pre_get_comments',
				'priority' => 10,
				'callback' => array( $instance, 'exclude_rsvp_from_comment_query' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'comments_clauses',
				'priority' => 10,
				'callback' => array( $instance, 'taxonomy_query' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_insert_comment',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_invalidate_comment_types_cache' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for taxonomy_query method.
	 *
	 * @covers ::taxonomy_query
	 *
	 * @return void
	 */
	public function test_taxonomy_query(): void {
		$instance = Rsvp_Query::get_instance();
		$user_id  = $this->factory->user->create();
		$post     = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$clauses  = array(
			'join'  => '',
			'where' => '',
		);
		$event    = new Event( $post->ID );

		$event->rsvp->save( $user_id, 'attending' );

		$comment_query = new WP_Comment_Query(
			array(
				'post_id'   => $event->ID,
				'user_id'   => $user_id,
				'tax_query' => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Rsvp::TAXONOMY,
						'terms'    => 'attending',
						'field'    => 'slug',
					),
				),
			)
		);

		$pieces = $instance->taxonomy_query( $clauses, $comment_query );
		$term   = get_term_by( 'slug', 'attending', Rsvp::TAXONOMY );

		$this->assertSame(
			' LEFT JOIN wp_term_relationships ON (wp_comments.comment_ID = wp_term_relationships.object_id)',
			$pieces['join'],
			'Failed to assert that join is the same.'
		);
		$this->assertSame(
			' AND ( wp_term_relationships.term_taxonomy_id IN (' . $term->term_taxonomy_id . ') )',
			preg_replace( '/\s+/', ' ', $pieces['where'] ),
			'Failed to assert where is the same.'
		);
	}

	/**
	 * Coverage for get_rsvp and get_rsvps method.
	 *
	 * @covers ::get_rsvp
	 * @covers ::get_rsvps
	 *
	 * @return void
	 */
	public function test_get_rsvps(): void {
		$instance = Rsvp_Query::get_instance();

		$this->assertNull(
			$instance->get_rsvp( array() ),
			'Failed to assert null.'
		);

		$user_id_1 = $this->factory->user->create();
		$user_id_2 = $this->factory->user->create();
		$event     = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$rsvp_1    = wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
				'user_id'         => $user_id_1,
			)
		);
		$rsvp_2    = wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
				'user_id'         => $user_id_2,
			)
		);

		wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'comment_content' => 'Test comment 1',
				'user_id'         => $user_id_1,
			)
		);

		wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'comment_content' => 'Test comment 2',
				'user_id'         => $user_id_2,
			)
		);

		$rsvp = $instance->get_rsvp(
			array(
				'post_id' => $event->ID,
				'user_id' => $user_id_2,
			),
		);

		$this->assertEquals( $rsvp_2, $rsvp->comment_ID );

		$rsvp = $instance->get_rsvp(
			array(
				'post_id' => $event->ID,
				'user_id' => $user_id_1,
			),
		);

		$this->assertEquals( $rsvp_1, (int) $rsvp->comment_ID );

		$this->assertEquals(
			2,
			count(
				$instance->get_rsvps(
					array( 'post_id' => $event->ID ),
				)
			),
			'Failed to assert 2 RSVPs to event.'
		);
	}

	/**
	 * Test excluding RSVP from type array removes RSVP and reindexes array.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_type_array(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = array( 'comment', Rsvp::COMMENT_TYPE, 'pingback' );
		$query->query_vars['type__in'] = '';

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertEquals(
			array( 'comment', 'pingback' ),
			$query->query_vars['type'],
			'RSVP comment type should be removed from type array and array should be reindexed'
		);
	}

	/**
	 * Test excluding RSVP when type is a single RSVP string sets type to empty.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_type_string(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = Rsvp::COMMENT_TYPE;
		$query->query_vars['type__in'] = '';

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertEquals(
			'',
			$query->query_vars['type'],
			'Type should be set to empty string when only RSVP comment type is present'
		);
	}

	/**
	 * Test excluding RSVP from empty type sets default comment types.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 * @covers ::get_all_comment_types
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_empty_type(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();

		// Clear any existing transient.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );

		// Create a custom comment type to test dynamic fetching.
		$this->factory->comment->create(
			array(
				'comment_type' => 'custom_type',
			)
		);

		$query->query_vars['type']     = '';
		$query->query_vars['type__in'] = '';

		$instance->exclude_rsvp_from_comment_query( $query );

		// Should include all types except RSVP.
		$this->assertNotContains(
			'gatherpress_rsvp',
			$query->query_vars['type'],
			'RSVP type should be excluded'
		);

		// Should include the custom type.
		$this->assertContains(
			'custom_type',
			$query->query_vars['type'],
			'Custom comment type should be included'
		);

		// Clean up.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );
	}


	/**
	 * Test excluding RSVP from type__in array removes RSVP and reindexes array.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_type_in_array(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = '';
		$query->query_vars['type__in'] = array( 'comment', Rsvp::COMMENT_TYPE, 'custom' );

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertEquals(
			array( 'comment', 'custom' ),
			$query->query_vars['type__in'],
			'RSVP comment type should be removed from type__in array and array should be reindexed'
		);
	}

	/**
	 * Test excluding RSVP when type__in is a single RSVP string sets type__in to empty.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_type_in_string(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = '';
		$query->query_vars['type__in'] = Rsvp::COMMENT_TYPE;

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertEquals(
			'',
			$query->query_vars['type__in'],
			'Type__in should be set to empty string when only RSVP comment type is present'
		);
	}

	/**
	 * Test excluding RSVP from both type and type__in variables simultaneously.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_handles_both_type_vars(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = array( 'comment', Rsvp::COMMENT_TYPE );
		$query->query_vars['type__in'] = array( 'pingback', Rsvp::COMMENT_TYPE );

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertEquals(
			array( 'comment' ),
			$query->query_vars['type'],
			'RSVP should be removed from type array'
		);
		$this->assertEquals(
			array( 'pingback' ),
			$query->query_vars['type__in'],
			'RSVP should be removed from type__in array'
		);
	}

	/**
	 * Test excluding RSVP makes no changes when RSVP is not present in arrays.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_no_change_when_not_present(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = array( 'comment', 'pingback' );
		$query->query_vars['type__in'] = array( 'custom', 'review' );

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertEquals(
			array( 'comment', 'pingback' ),
			$query->query_vars['type'],
			'Type array should remain unchanged when RSVP is not present'
		);
		$this->assertEquals(
			array( 'custom', 'review' ),
			$query->query_vars['type__in'],
			'Type__in array should remain unchanged when RSVP is not present'
		);
	}

	/**
	 * Test get_all_comment_types method caches results.
	 *
	 * @covers ::get_all_comment_types
	 *
	 * @return void
	 */
	public function test_get_all_comment_types_caching(): void {
		$instance = Rsvp_Query::get_instance();

		// Clear any existing transient.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );

		// Temporarily remove the cache invalidation hook for this test.
		remove_action( 'wp_insert_comment', array( $instance, 'maybe_invalidate_comment_types_cache' ), 10 );

		// Create some test comment types.
		$this->factory->comment->create(
			array(
				'comment_type' => 'test_type_1',
			)
		);
		$this->factory->comment->create(
			array(
				'comment_type' => 'test_type_2',
			)
		);

		// Use reflection to access protected method.
		$reflection = new \ReflectionClass( $instance );
		$method     = $reflection->getMethod( 'get_all_comment_types' );
		$method->setAccessible( true );

		// First call should hit the database.
		$types = $method->invoke( $instance );

		// Should include our test types.
		$this->assertContains( 'test_type_1', $types, 'First test type should be included' );
		$this->assertContains( 'test_type_2', $types, 'Second test type should be included' );

		// Verify transient was set.
		$cached_types = get_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );
		$this->assertEquals( $types, $cached_types, 'Transient should store the types' );

		// Create another type after caching.
		$this->factory->comment->create(
			array(
				'comment_type' => 'test_type_3',
			)
		);

		// Second call should use cache and NOT include the new type.
		$cached_result = $method->invoke( $instance );
		$this->assertNotContains( 'test_type_3', $cached_result, 'New type should not be in cached result' );

		// Clear transient and verify new type is included.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );
		$fresh_result = $method->invoke( $instance );
		$this->assertContains( 'test_type_3', $fresh_result, 'New type should be included after cache clear' );

		// Clean up and restore hook.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );
		add_action( 'wp_insert_comment', array( $instance, 'maybe_invalidate_comment_types_cache' ), 10, 2 );
	}

	/**
	 * Test cache invalidation when new comment type is added.
	 *
	 * @covers ::maybe_invalidate_comment_types_cache
	 *
	 * @return void
	 */
	public function test_maybe_invalidate_comment_types_cache(): void {
		$instance = Rsvp_Query::get_instance();

		// Clear any existing transient.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );

		// Set up initial cache with known types.
		set_transient(
			Rsvp_Query::COMMENT_TYPES_CACHE_KEY,
			array( 'comment', 'pingback', 'trackback' ),
			Rsvp_Query::CACHE_EXPIRATION
		);

		// Create a comment with existing type (should not invalidate).
		$comment_id = $this->factory->comment->create(
			array(
				'comment_type' => 'pingback',
			)
		);
		$comment    = get_comment( $comment_id );
		$instance->maybe_invalidate_comment_types_cache( $comment_id, $comment );

		// Cache should still exist.
		$this->assertNotFalse(
			get_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY ),
			'Cache should not be invalidated for existing comment type'
		);

		// Create a comment with new type (should invalidate).
		$comment_id = $this->factory->comment->create(
			array(
				'comment_type' => 'new_custom_type',
			)
		);
		$comment    = get_comment( $comment_id );
		$instance->maybe_invalidate_comment_types_cache( $comment_id, $comment );

		// Cache should be cleared.
		$this->assertFalse(
			get_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY ),
			'Cache should be invalidated when new comment type is added'
		);

		// Clean up.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );
	}

	/**
	 * Test maybe_invalidate_comment_types_cache with empty comment type.
	 *
	 * Tests the early return path when comment_type is empty (regular comment).
	 *
	 * @covers ::maybe_invalidate_comment_types_cache
	 *
	 * @return void
	 */
	public function test_maybe_invalidate_comment_types_cache_with_empty_type(): void {
		$instance = Rsvp_Query::get_instance();

		// Set up cache.
		set_transient(
			Rsvp_Query::COMMENT_TYPES_CACHE_KEY,
			array( 'pingback', 'trackback' ),
			Rsvp_Query::CACHE_EXPIRATION
		);

		// Create a mock WP_Comment object with empty string comment_type.
		$comment               = new WP_Comment( new stdClass() );
		$comment->comment_ID   = 998;
		$comment->comment_type = '';

		// Verify comment_type is actually empty.
		$this->assertEmpty(
			$comment->comment_type,
			'Comment type should be empty for this test'
		);

		$instance->maybe_invalidate_comment_types_cache( 998, $comment );

		// Cache should still exist (method returns early for empty types).
		$this->assertNotFalse(
			get_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY ),
			'Cache should not be invalidated for empty comment type'
		);

		// Clean up.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );
	}

	/**
	 * Test maybe_invalidate_comment_types_cache with null comment type.
	 *
	 * @covers ::maybe_invalidate_comment_types_cache
	 *
	 * @return void
	 */
	public function test_maybe_invalidate_comment_types_cache_with_null_type(): void {
		$instance = Rsvp_Query::get_instance();

		// Set up cache.
		set_transient(
			Rsvp_Query::COMMENT_TYPES_CACHE_KEY,
			array( 'comment', 'pingback' ),
			Rsvp_Query::CACHE_EXPIRATION
		);

		// Create a mock WP_Comment object with null comment_type.
		$comment               = new WP_Comment( new stdClass() );
		$comment->comment_ID   = 999;
		$comment->comment_type = null;

		$instance->maybe_invalidate_comment_types_cache( 999, $comment );

		// Cache should still exist (method returns early for empty types).
		$this->assertNotFalse(
			get_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY ),
			'Cache should not be invalidated for null comment type'
		);

		// Clean up.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );
	}

	/**
	 * Test maybe_invalidate_comment_types_cache when cache doesn't exist.
	 *
	 * @covers ::maybe_invalidate_comment_types_cache
	 *
	 * @return void
	 */
	public function test_maybe_invalidate_comment_types_cache_no_cache(): void {
		$instance = Rsvp_Query::get_instance();

		// Clear any existing transient.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );

		// Create a comment with custom type when cache doesn't exist.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_type' => 'custom_type',
			)
		);
		$comment    = get_comment( $comment_id );

		// Should not error and should do nothing since cache doesn't exist.
		$instance->maybe_invalidate_comment_types_cache( $comment_id, $comment );

		// Verify cache still doesn't exist.
		$this->assertFalse(
			get_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY ),
			'Cache should remain non-existent when it was not set'
		);
	}

	/**
	 * Test get_rsvps with count parameter returns integer.
	 *
	 * @covers ::get_rsvps
	 *
	 * @return void
	 */
	public function test_get_rsvps_with_count(): void {
		$instance = Rsvp_Query::get_instance();
		$event    = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		// Create multiple RSVPs.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->factory->comment->create(
				array(
					'comment_post_ID' => $event->ID,
					'comment_type'    => Rsvp::COMMENT_TYPE,
				)
			);
		}

		$count = $instance->get_rsvps(
			array(
				'post_id' => $event->ID,
				'count'   => true,
			)
		);

		$this->assertIsInt( $count, 'Count should return an integer' );
		$this->assertEquals( 3, $count, 'Should return count of 3 RSVPs' );
	}

	/**
	 * Test taxonomy_query with empty tax_query.
	 *
	 * @covers ::taxonomy_query
	 *
	 * @return void
	 */
	public function test_taxonomy_query_with_empty_tax_query(): void {
		$instance = Rsvp_Query::get_instance();
		$clauses  = array(
			'join'  => ' ORIGINAL JOIN',
			'where' => ' ORIGINAL WHERE',
		);

		$comment_query = new WP_Comment_Query();

		// No tax_query set.
		$result = $instance->taxonomy_query( $clauses, $comment_query );

		// Clauses should be unchanged.
		$this->assertSame(
			' ORIGINAL JOIN',
			$result['join'],
			'Join should remain unchanged when tax_query is empty'
		);
		$this->assertSame(
			' ORIGINAL WHERE',
			$result['where'],
			'Where should remain unchanged when tax_query is empty'
		);
	}

	/**
	 * Test get_all_comment_types returns defaults when database query fails.
	 *
	 * @covers ::get_all_comment_types
	 *
	 * @return void
	 */
	public function test_get_all_comment_types_with_empty_db_result(): void {
		global $wpdb;

		$instance = Rsvp_Query::get_instance();

		// Clear any existing transient.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );

		// Suppress database errors to avoid output during test.
		$wpdb->suppress_errors( true );

		// Temporarily replace the comments table name to force empty result.
		$original_comments = $wpdb->comments;
		$wpdb->comments    = 'nonexistent_table_xyz';

		// Use reflection to access protected method.
		$reflection = new \ReflectionClass( $instance );
		$method     = $reflection->getMethod( 'get_all_comment_types' );
		$method->setAccessible( true );

		// Should return defaults when query fails.
		$types = $method->invoke( $instance );

		// Restore original table name and error settings.
		$wpdb->comments = $original_comments;
		$wpdb->suppress_errors( false );

		// Should return default types.
		$this->assertContains( 'comment', $types, 'Should include default comment type' );
		$this->assertContains( 'pingback', $types, 'Should include default pingback type' );
		$this->assertContains( 'trackback', $types, 'Should include default trackback type' );

		// Clean up.
		delete_transient( Rsvp_Query::COMMENT_TYPES_CACHE_KEY );
	}
}
