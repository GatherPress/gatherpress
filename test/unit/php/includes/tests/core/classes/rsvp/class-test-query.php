<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Query.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.30.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Query;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;
use WP_Comment;
use WP_Comment_Query;
use WP_REST_Response;

/**
 * Class Test_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Query
 */
class Test_Query extends Base {

	/**
	 * Coverage for __construct and setup_hooks.
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
				'type'     => 'filter',
				'name'     => 'get_comment',
				'priority' => 10,
				'callback' => array( $instance, 'prepare_rsvp_comment' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'rest_prepare_comment',
				'priority' => 10,
				'callback' => array( $instance, 'mask_anonymous_rsvp_rest_author' ),
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
		$instance = Query::get_instance();
		$user_id  = $this->factory->user->create();
		$post     = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$clauses  = array(
			'join'  => '',
			'where' => '',
		);
		$event    = new Event( $post->ID );

		( new Rsvp( $post->ID ) )->save( $user_id, 'attending' );

		$comment_query = new WP_Comment_Query(
			array(
				'post_id'   => $event->post->ID,
				'user_id'   => $user_id,
				'tax_query' => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Status::TAXONOMY,
						'terms'    => 'attending',
						'field'    => 'slug',
					),
				),
			)
		);

		$pieces = $instance->taxonomy_query( $clauses, $comment_query );
		$term   = get_term_by( 'slug', 'attending', Status::TAXONOMY );

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
		$instance = Query::get_instance();

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
		$instance = Query::get_instance();
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
		$instance = Query::get_instance();
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
	 * With no `type` set, the query var stays empty and the RSVP type lands in
	 * `type__not_in`, so a site whose only stored comment type is the RSVP type
	 * still excludes it (#2282).
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_empty_type(): void {
		$instance = Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']         = '';
		$query->query_vars['type__in']     = '';
		$query->query_vars['type__not_in'] = '';

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertSame(
			'',
			$query->query_vars['type'],
			'Type should stay empty rather than being rebuilt from the stored comment types.'
		);
		$this->assertSame(
			array( Rsvp::COMMENT_TYPE ),
			$query->query_vars['type__not_in'],
			'RSVP type should be excluded through type__not_in.'
		);
	}

	/**
	 * A caller asking for `all` keeps that, and the RSVP type is still excluded.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_type_all(): void {
		$instance = Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = 'all';
		$query->query_vars['type__in'] = '';

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertSame( 'all', $query->query_vars['type'], 'Type should be left as the caller set it.' );
		$this->assertSame(
			array( Rsvp::COMMENT_TYPE ),
			$query->query_vars['type__not_in'],
			'RSVP type should be excluded through type__not_in.'
		);
	}

	/**
	 * The RSVP type is appended to whatever `type__not_in` the caller passed,
	 * string or array, without duplicating it.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_merges_into_existing_type_not_in(): void {
		$instance = Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']         = '';
		$query->query_vars['type__in']     = '';
		$query->query_vars['type__not_in'] = 'pingback';

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertSame(
			array( 'pingback', Rsvp::COMMENT_TYPE ),
			$query->query_vars['type__not_in'],
			'A string type__not_in should become an array that keeps the caller type.'
		);

		$query = new WP_Comment_Query();

		$query->query_vars['type']         = '';
		$query->query_vars['type__in']     = '';
		$query->query_vars['type__not_in'] = array( 'trackback', Rsvp::COMMENT_TYPE );

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertSame(
			array( 'trackback', Rsvp::COMMENT_TYPE ),
			$query->query_vars['type__not_in'],
			'An RSVP type already in type__not_in should not be duplicated.'
		);
	}

	/**
	 * A query that only sets `type__in` keeps `type` empty, so core's merge of
	 * the two does not widen it to every stored comment type (#1637).
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_keeps_type_in_only_query_narrow(): void {
		Query::get_instance();

		$post_id = $this->factory->post->create();

		$this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );
		$this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);
		$custom_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'custom_type',
			)
		);

		$query = new WP_Comment_Query(
			array(
				'post_id'  => $post_id,
				'type__in' => array( 'custom_type' ),
				'fields'   => 'ids',
			)
		);

		$this->assertSame( '', $query->query_vars['type'], 'Type should not be rebuilt when only type__in is set.' );
		$this->assertSame(
			array( $custom_id ),
			array_map( 'intval', $query->comments ),
			'Only the type__in comment should be returned.'
		);
	}

	/**
	 * Reproduces #2282: with the RSVP type as the only comment type stored, the
	 * comments block's query still comes back empty, and returns only the
	 * ordinary comment once one exists.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_rsvp_is_excluded_when_it_is_the_only_comment_type(): void {
		Query::get_instance();

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$user_id = $this->factory->user->create();

		( new Rsvp( $post_id ) )->save( $user_id, 'attending' );

		$block          = new WP_Block( array( 'blockName' => 'core/comments' ) );
		$block->context = array( 'postId' => $post_id );

		$query = new WP_Comment_Query( build_comment_query_vars_from_block( $block ) );

		$this->assertContains(
			Rsvp::COMMENT_TYPE,
			$query->query_vars['type__not_in'],
			'The RSVP type should be excluded through type__not_in.'
		);
		$this->assertSame( array(), $query->comments, 'The RSVP should not surface as a comment.' );

		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$query = new WP_Comment_Query( build_comment_query_vars_from_block( $block ) );

		// Threaded results are keyed by comment ID, so compare values only.
		$this->assertSame(
			array( $comment_id ),
			array_values(
				array_map( static fn ( WP_Comment $comment ): int => (int) $comment->comment_ID, $query->comments )
			),
			'Only the ordinary comment should be returned.'
		);
	}

	/**
	 * Direct coverage for the `type` / `type__in` normalizer: arrays come back
	 * reindexed without the RSVP type, an RSVP-only string becomes empty, and
	 * any other string is left alone.
	 *
	 * @covers ::remove_rsvp_type
	 *
	 * @return void
	 */
	public function test_remove_rsvp_type(): void {
		$instance = Query::get_instance();

		$this->assertSame(
			array( 'comment', 'pingback' ),
			Utility::invoke_hidden_method(
				$instance,
				'remove_rsvp_type',
				array( array( 'comment', Rsvp::COMMENT_TYPE, 'pingback' ) )
			),
			'An array should be reindexed without the RSVP type.'
		);
		$this->assertSame(
			'',
			Utility::invoke_hidden_method( $instance, 'remove_rsvp_type', array( Rsvp::COMMENT_TYPE ) ),
			'An RSVP-only string should become empty.'
		);
		$this->assertSame(
			'comment',
			Utility::invoke_hidden_method( $instance, 'remove_rsvp_type', array( 'comment' ) ),
			'Any other string should be left alone.'
		);
	}


	/**
	 * Test excluding RSVP from type__in array removes RSVP and reindexes array.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_type_in_array(): void {
		$instance = Query::get_instance();
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
		$instance = Query::get_instance();
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
		$instance = Query::get_instance();
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
	 * Test the gatherpress_rsvp_comment_query_exclusion filter short-circuits
	 * the exclusion when an integration returns false, leaving both `type` and
	 * `type__in` untouched so the caller's original query vars survive.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_filter_can_short_circuit(): void {
		$instance = Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = array( 'comment', Rsvp::COMMENT_TYPE, 'pingback' );
		$query->query_vars['type__in'] = array( 'comment', Rsvp::COMMENT_TYPE );

		add_filter( 'gatherpress_rsvp_comment_query_exclusion', '__return_false' );
		$instance->exclude_rsvp_from_comment_query( $query );
		remove_filter( 'gatherpress_rsvp_comment_query_exclusion', '__return_false' );

		$this->assertEquals(
			array( 'comment', Rsvp::COMMENT_TYPE, 'pingback' ),
			$query->query_vars['type'],
			'Type array should be untouched when the filter returns false'
		);
		$this->assertEquals(
			array( 'comment', Rsvp::COMMENT_TYPE ),
			$query->query_vars['type__in'],
			'Type__in array should be untouched when the filter returns false'
		);
	}

	/**
	 * Test the gatherpress_rsvp_comment_query_exclusion filter receives the
	 * live WP_Comment_Query as its second argument, which integrations rely on
	 * to scope the opt-out to specific queries.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_filter_receives_query_argument(): void {
		$instance       = Query::get_instance();
		$query          = new WP_Comment_Query();
		$captured_query = null;

		$query->query_vars['type']     = array( 'comment', Rsvp::COMMENT_TYPE );
		$query->query_vars['type__in'] = '';

		$callback = static function ( bool $exclude, WP_Comment_Query $passed_query ) use ( &$captured_query ): bool {
			$captured_query = $passed_query;
			return $exclude;
		};

		add_filter( 'gatherpress_rsvp_comment_query_exclusion', $callback, 10, 2 );
		$instance->exclude_rsvp_from_comment_query( $query );
		remove_filter( 'gatherpress_rsvp_comment_query_exclusion', $callback, 10 );

		$this->assertSame(
			$query,
			$captured_query,
			'Filter should receive the live WP_Comment_Query as its second argument'
		);
	}

	/**
	 * Test the default value of gatherpress_rsvp_comment_query_exclusion is
	 * true so the exclusion stays in effect for sites that do not hook the
	 * filter, and that an integration explicitly returning true preserves the
	 * existing behavior.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_filter_default_preserves_exclusion(): void {
		$instance = Query::get_instance();
		$query    = new WP_Comment_Query();

		$query->query_vars['type']     = array( 'comment', Rsvp::COMMENT_TYPE );
		$query->query_vars['type__in'] = '';

		add_filter( 'gatherpress_rsvp_comment_query_exclusion', '__return_true' );
		$instance->exclude_rsvp_from_comment_query( $query );
		remove_filter( 'gatherpress_rsvp_comment_query_exclusion', '__return_true' );

		$this->assertEquals(
			array( 'comment' ),
			$query->query_vars['type'],
			'Returning true from the filter should preserve the existing exclusion behavior'
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
		$instance = Query::get_instance();
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
	 * `get_rsvps()` honors a caller-provided `post_type` so per-post-type
	 * callers (like the RSVPs admin pages) can narrow results, while still
	 * defaulting to every RSVP-supporting post type (#1849).
	 *
	 * @covers ::get_rsvps
	 *
	 * @return void
	 */
	public function test_get_rsvps_honors_post_type_argument(): void {
		register_post_type(
			'gatherpress_probe',
			array(
				'public'   => true,
				'supports' => array( 'title', 'gatherpress-rsvp' ),
			)
		);

		$instance = Query::get_instance();
		$event    = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$probe_id = $this->factory->post->create(
			array(
				'post_type'   => 'gatherpress_probe',
				'post_status' => 'publish',
			)
		);

		wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);
		wp_insert_comment(
			array(
				'comment_post_ID' => $probe_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$this->assertSame(
			1,
			$instance->get_rsvps(
				array(
					'count'     => true,
					'post_type' => 'gatherpress_probe',
				)
			),
			'A provided post_type should narrow results to that post type.'
		);

		$this->assertSame(
			2,
			$instance->get_rsvps( array( 'count' => true ) ),
			'Without a post_type, every RSVP-supporting post type is included.'
		);

		unregister_post_type( 'gatherpress_probe' );
	}

	/**
	 * Test get_rsvps with count parameter returns integer.
	 *
	 * @covers ::get_rsvps
	 *
	 * @return void
	 */
	public function test_get_rsvps_with_count(): void {
		$instance = Query::get_instance();
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
		$instance = Query::get_instance();
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
	 * Builds an RSVP comment, optionally flagged anonymous.
	 *
	 * @param bool $anonymous Whether the responder asked to stay anonymous.
	 *
	 * @return WP_Comment The RSVP comment.
	 */
	protected function make_rsvp_comment( bool $anonymous ): WP_Comment {
		$comment_id = $this->factory->comment->create(
			array(
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'Real Name',
				'comment_author_email' => 'real@example.test',
				'comment_author_url'   => 'https://example.test/real',
			)
		);

		if ( $anonymous ) {
			update_comment_meta( $comment_id, Rsvp::ANONYMOUS_META_KEY, 1 );
		}

		// WP_Comment::get_instance() skips the `get_comment` filter, so this
		// returns the stored comment rather than one the mask already touched.
		return WP_Comment::get_instance( $comment_id );
	}

	/**
	 * An anonymous responder's identity is withheld from a viewer who cannot
	 * edit posts, and the stored comment keeps its real values.
	 *
	 * @covers ::prepare_rsvp_comment
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_comment_withholds_for_public(): void {
		$instance = Query::get_instance();

		wp_set_current_user( 0 );

		$comment = $this->make_rsvp_comment( true );
		$masked  = $instance->prepare_rsvp_comment( $comment );

		$this->assertSame( __( 'Anonymous', 'gatherpress' ), $masked->comment_author );
		$this->assertSame( '', $masked->comment_author_email );
		$this->assertSame( '', $masked->comment_author_url );
		$this->assertSame(
			'Real Name',
			$comment->comment_author,
			'The mask is applied to a clone, so the original keeps its values.'
		);
	}

	/**
	 * The identity is left alone for anything that is not an anonymous RSVP
	 * being read by an unprivileged viewer.
	 *
	 * @covers ::prepare_rsvp_comment
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_comment_leaves_others_alone(): void {
		$instance = Query::get_instance();

		wp_set_current_user( 0 );

		$this->assertNull(
			$instance->prepare_rsvp_comment( null ),
			'A value that is not a comment passes through untouched.'
		);

		$regular = get_comment( $this->factory->comment->create( array( 'comment_author' => 'Real Name' ) ) );
		$this->assertSame(
			'Real Name',
			$instance->prepare_rsvp_comment( $regular )->comment_author,
			'A comment that is not an RSVP is never masked.'
		);

		$named = $this->make_rsvp_comment( false );
		$this->assertSame(
			'Real Name',
			$instance->prepare_rsvp_comment( $named )->comment_author,
			'An RSVP that is not anonymous keeps its identity.'
		);

		$anonymous = $this->make_rsvp_comment( true );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame(
			'Real Name',
			$instance->prepare_rsvp_comment( $anonymous )->comment_author,
			'A viewer who can edit posts still sees the responder.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * The REST response withholds the author ID for a masked responder, and
	 * leaves every other response untouched.
	 *
	 * @covers ::mask_anonymous_rsvp_rest_author
	 *
	 * @return void
	 */
	public function test_mask_anonymous_rsvp_rest_author(): void {
		$instance = Query::get_instance();

		wp_set_current_user( 0 );

		$masked = $instance->prepare_rsvp_comment( $this->make_rsvp_comment( true ) );
		$this->assertSame(
			0,
			$instance->mask_anonymous_rsvp_rest_author(
				new WP_REST_Response( array( 'author' => 7 ) ),
				$masked
			)->get_data()['author'],
			'A masked responder has no author ID in the response.'
		);

		$this->assertArrayNotHasKey(
			'author',
			$instance->mask_anonymous_rsvp_rest_author(
				new WP_REST_Response( array( 'id' => 1 ) ),
				$masked
			)->get_data(),
			'A response without an author field is left as it is.'
		);

		$this->assertSame(
			7,
			$instance->mask_anonymous_rsvp_rest_author(
				new WP_REST_Response( array( 'author' => 7 ) ),
				$this->make_rsvp_comment( false )
			)->get_data()['author'],
			'An RSVP that is not masked keeps its author ID.'
		);

		$regular = get_comment(
			$this->factory->comment->create(
				array( 'comment_author' => __( 'Anonymous', 'gatherpress' ) )
			)
		);
		$this->assertSame(
			7,
			$instance->mask_anonymous_rsvp_rest_author(
				new WP_REST_Response( array( 'author' => 7 ) ),
				$regular
			)->get_data()['author'],
			'A non-RSVP comment named Anonymous is not treated as masked.'
		);
	}

	/**
	 * Anonymity yields only to the capability that manages RSVPs, so roles that
	 * can write posts without moderating them still see a masked responder.
	 *
	 * @covers ::prepare_rsvp_comment
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_comment_pivots_on_rsvp_capability(): void {
		$instance = Query::get_instance();
		$comment  = $this->make_rsvp_comment( true );
		$expected = array(
			'subscriber'    => __( 'Anonymous', 'gatherpress' ),
			'contributor'   => __( 'Anonymous', 'gatherpress' ),
			'author'        => __( 'Anonymous', 'gatherpress' ),
			'editor'        => 'Real Name',
			'administrator' => 'Real Name',
		);

		foreach ( $expected as $role => $author ) {
			wp_set_current_user( $this->factory->user->create( array( 'role' => $role ) ) );

			$this->assertSame(
				$author,
				$instance->prepare_rsvp_comment( $comment )->comment_author,
				sprintf( 'A %s sees the wrong responder.', $role )
			);
		}

		wp_set_current_user( 0 );
	}

	/**
	 * A response saved through the store carries no author URL, so the
	 * responder's profile is resolved from their account -- unless a URL was
	 * already saved with it, or the responder is withheld.
	 *
	 * @covers ::prepare_rsvp_comment
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_comment_resolves_author_url(): void {
		$instance = Query::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( 0 );

		$comment_id = $this->factory->comment->create(
			array(
				'comment_type'       => Rsvp::COMMENT_TYPE,
				'user_id'            => $user_id,
				'comment_author_url' => '',
			)
		);
		$comment    = WP_Comment::get_instance( $comment_id );

		$this->assertSame(
			get_author_posts_url( $user_id ),
			$instance->prepare_rsvp_comment( $comment )->comment_author_url,
			'An empty author URL resolves to the responder profile.'
		);

		$saved_id = $this->factory->comment->create(
			array(
				'comment_type'       => Rsvp::COMMENT_TYPE,
				'user_id'            => $user_id,
				'comment_author_url' => 'https://example.test/saved',
			)
		);

		$this->assertSame(
			'https://example.test/saved',
			$instance->prepare_rsvp_comment( WP_Comment::get_instance( $saved_id ) )->comment_author_url,
			'A URL saved with the response is kept, so a URL identity is never rewritten.'
		);

		$guest_id = $this->factory->comment->create(
			array(
				'comment_type'       => Rsvp::COMMENT_TYPE,
				'user_id'            => 0,
				'comment_author_url' => '',
			)
		);

		$this->assertSame(
			'',
			$instance->prepare_rsvp_comment( WP_Comment::get_instance( $guest_id ) )->comment_author_url,
			'A response with no account behind it resolves to nothing.'
		);

		$anonymous_id = $this->factory->comment->create(
			array(
				'comment_type'       => Rsvp::COMMENT_TYPE,
				'user_id'            => $user_id,
				'comment_author_url' => '',
			)
		);
		update_comment_meta( $anonymous_id, Rsvp::ANONYMOUS_META_KEY, 1 );

		$this->assertSame(
			'',
			$instance->prepare_rsvp_comment( WP_Comment::get_instance( $anonymous_id ) )->comment_author_url,
			'A withheld responder is never linked.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame(
			get_author_posts_url( $user_id ),
			$instance->prepare_rsvp_comment( WP_Comment::get_instance( $anonymous_id ) )->comment_author_url,
			'A reader who already sees the responder gets the link too.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * A response saved before the store stopped writing an address into the
	 * display-name column still carries one, so it is withheld on read.
	 *
	 * @covers ::prepare_rsvp_comment
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_comment_withholds_a_stored_address(): void {
		$instance = Query::get_instance();
		$email    = 'legacy-row@example.test';
		$comment  = WP_Comment::get_instance(
			$this->factory->comment->create(
				array(
					'comment_type'         => Rsvp::COMMENT_TYPE,
					'comment_author'       => $email,
					'comment_author_email' => $email,
					'user_id'              => 0,
				)
			)
		);

		wp_set_current_user( 0 );
		$this->assertSame(
			__( 'Attendee', 'gatherpress' ),
			$instance->prepare_rsvp_comment( $comment )->comment_author,
			'An address standing in for a name is withheld.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame(
			$email,
			$instance->prepare_rsvp_comment( $comment )->comment_author,
			'Whoever manages RSVPs still sees what was saved.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * An account whose display name is an address keeps it, because that is
	 * what core publishes for anyone who registered with one.
	 *
	 * @covers ::prepare_rsvp_comment
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_comment_keeps_a_display_name_that_is_an_address(): void {
		$instance = Query::get_instance();
		$user_id  = $this->factory->user->create(
			array(
				'display_name' => 'login@example.test',
				'user_email'   => 'account@example.test',
			)
		);
		$comment  = WP_Comment::get_instance(
			$this->factory->comment->create(
				array(
					'comment_type'         => Rsvp::COMMENT_TYPE,
					'comment_author'       => 'login@example.test',
					'comment_author_email' => 'account@example.test',
					'user_id'              => $user_id,
				)
			)
		);

		wp_set_current_user( 0 );

		$this->assertSame(
			'login@example.test',
			$instance->prepare_rsvp_comment( $comment )->comment_author,
			'A name that merely looks like an address is not the legacy write, so it stands.'
		);
	}

	/**
	 * A response that both carries a stored address and lacks a URL gets both
	 * treatments, because the two conditions are independent.
	 *
	 * @covers ::prepare_rsvp_comment
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_comment_withholds_an_address_and_still_resolves_the_url(): void {
		$instance = Query::get_instance();
		$email    = 'both@example.test';
		$user_id  = $this->factory->user->create( array( 'user_email' => $email ) );
		$comment  = WP_Comment::get_instance(
			$this->factory->comment->create(
				array(
					'comment_type'         => Rsvp::COMMENT_TYPE,
					'comment_author'       => $email,
					'comment_author_email' => $email,
					'comment_author_url'   => '',
					'user_id'              => $user_id,
				)
			)
		);

		wp_set_current_user( 0 );

		$prepared = $instance->prepare_rsvp_comment( $comment );

		$this->assertSame(
			__( 'Attendee', 'gatherpress' ),
			$prepared->comment_author,
			'The stored address is still withheld.'
		);
		$this->assertSame(
			get_author_posts_url( $user_id ),
			$prepared->comment_author_url,
			'And the link the store never wrote is still resolved.'
		);
	}

	/**
	 * Two columns that match on something other than an address are left
	 * alone, since there is no address to keep back.
	 *
	 * @covers ::prepare_rsvp_comment
	 *
	 * @return void
	 */
	public function test_prepare_rsvp_comment_keeps_matching_columns_that_are_not_an_address(): void {
		global $wpdb;

		$instance   = Query::get_instance();
		$comment_id = $this->factory->comment->create(
			array(
				'comment_type'   => Rsvp::COMMENT_TYPE,
				'comment_author' => 'Bob Smith',
				'user_id'        => 0,
			)
		);

		// Written directly because the comment API sanitizes the address
		// column; only an import or a migration reaches this shape.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->comments,
			array( 'comment_author_email' => 'Bob Smith' ),
			array( 'comment_ID' => $comment_id )
		);
		clean_comment_cache( $comment_id );

		wp_set_current_user( 0 );

		$this->assertSame(
			'Bob Smith',
			$instance->prepare_rsvp_comment( WP_Comment::get_instance( $comment_id ) )->comment_author,
			'Matching columns holding a name rather than an address are not renamed.'
		);
	}
}
