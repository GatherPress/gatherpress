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
			' AND ( wp_term_relationships.term_taxonomy_id IN (' . $term->term_id . ') )',
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
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_empty_type(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();
		$expected = array( 'comment', 'pingback', 'trackback' );

		$query->query_vars['type']     = '';
		$query->query_vars['type__in'] = '';

		$instance->exclude_rsvp_from_comment_query( $query );

		$this->assertEquals(
			$expected,
			$query->query_vars['type'],
			'Default comment types should be set when type is empty'
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
}
