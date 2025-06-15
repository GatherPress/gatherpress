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
	 * Coverage for exclude_rsvp_from_comment_query method.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_exclude_rsvp_from_comment_query(): void {
		$instance = Rsvp_Query::get_instance();

		// Test with type parameter.
		$query                     = new WP_Comment_Query();
		$query->query_vars['type'] = array( 'comment', Rsvp::COMMENT_TYPE );
		$instance->exclude_rsvp_from_comment_query( $query );
		$this->assertEquals(
			array( 'comment' ),
			$query->query_vars['type'],
			'Failed to assert that RSVP type is excluded from type array.'
		);
		$this->assertContains(
			Rsvp::COMMENT_TYPE,
			$query->query_vars['type__not_in'],
			'Failed to assert that RSVP type is added to type__not_in.'
		);

		// Test with single type parameter.
		$query                     = new WP_Comment_Query();
		$query->query_vars['type'] = Rsvp::COMMENT_TYPE;
		$instance->exclude_rsvp_from_comment_query( $query );
		$this->assertEquals(
			'',
			$query->query_vars['type'],
			'Failed to assert that single RSVP type is set to empty string.'
		);
		$this->assertContains(
			Rsvp::COMMENT_TYPE,
			$query->query_vars['type__not_in'],
			'Failed to assert that RSVP type is added to type__not_in.'
		);

		// Test with type__in parameter.
		$query                         = new WP_Comment_Query();
		$query->query_vars['type']     = '';
		$query->query_vars['type__in'] = array( 'comment', Rsvp::COMMENT_TYPE );
		$instance->exclude_rsvp_from_comment_query( $query );
		$this->assertEquals(
			array( 'comment' ),
			$query->query_vars['type__in'],
			'Failed to assert that RSVP type is excluded from type__in array.'
		);
		$this->assertContains(
			Rsvp::COMMENT_TYPE,
			$query->query_vars['type__not_in'],
			'Failed to assert that RSVP type is added to type__not_in.'
		);

		// Test with empty type__in after removal.
		$query                         = new WP_Comment_Query();
		$query->query_vars['type']     = '';
		$query->query_vars['type__in'] = array( Rsvp::COMMENT_TYPE );
		$instance->exclude_rsvp_from_comment_query( $query );
		$this->assertEquals(
			array( 'comment', 'pingback', 'trackback' ),
			$query->query_vars['type__in'],
			'Failed to assert that default types are set when type__in becomes empty.'
		);
		$this->assertContains(
			Rsvp::COMMENT_TYPE,
			$query->query_vars['type__not_in'],
			'Failed to assert that RSVP type is added to type__not_in.'
		);

		// Test with default types.
		$query                         = new WP_Comment_Query();
		$query->query_vars['type']     = '';
		$query->query_vars['type__in'] = '';
		$instance->exclude_rsvp_from_comment_query( $query );
		$this->assertEquals(
			array( 'comment', 'pingback', 'trackback' ),
			$query->query_vars['type'],
			'Failed to assert that default types are set correctly.'
		);
		$this->assertContains(
			Rsvp::COMMENT_TYPE,
			$query->query_vars['type__not_in'],
			'Failed to assert that RSVP type is added to type__not_in.'
		);
	}

	/**
	 * Test SQL query modification.
	 *
	 * @covers ::exclude_rsvp_from_comment_query
	 *
	 * @return void
	 */
	public function test_sql_query_modification(): void {
		$instance = Rsvp_Query::get_instance();
		$query    = new WP_Comment_Query();

		// Test SQL modification for type__in.
		$query->query_vars['type__in'] = array( 'comment', Rsvp::COMMENT_TYPE );
		$instance->exclude_rsvp_from_comment_query( $query );
		$clauses = array(
			'where' => "comment_type IN ('comment', 'gatherpress_rsvp')",
		);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );
		$this->assertStringContainsString(
			"comment_type NOT IN ('rsvp','gatherpress_rsvp')",
			$clauses['where'],
			'Failed to assert that SQL query excludes RSVP types.'
		);
		remove_all_filters( 'comments_clauses' );

		// Test SQL modification for empty type.
		$query                         = new WP_Comment_Query();
		$query->query_vars['type']     = '';
		$query->query_vars['type__in'] = '';
		$instance->exclude_rsvp_from_comment_query( $query );
		$clauses = array(
			'where' => '',
		);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );
		$this->assertStringContainsString(
			"comment_type NOT IN ('rsvp','gatherpress_rsvp')",
			$clauses['where'],
			'Failed to assert that SQL query excludes RSVP types with empty types.'
		);
		remove_all_filters( 'comments_clauses' );

		// Test SQL modification for existing NOT IN clause.
		$query                             = new WP_Comment_Query();
		$query->query_vars['type__not_in'] = array( 'pingback' );
		$instance->exclude_rsvp_from_comment_query( $query );
		$clauses = array(
			'where' => "comment_type NOT IN ('pingback')",
		);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );
		$this->assertStringContainsString(
			"comment_type NOT IN ('rsvp','gatherpress_rsvp')",
			$clauses['where'],
			'Failed to assert that SQL query excludes RSVP types with existing NOT IN clause.'
		);
		remove_all_filters( 'comments_clauses' );

		// Test SQL modification for empty string in IN clause.
		$query                     = new WP_Comment_Query();
		$query->query_vars['type'] = '';
		$instance->exclude_rsvp_from_comment_query( $query );
		$clauses = array(
			'where' => "comment_type IN ('comment', '')",
		);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );
		$this->assertStringContainsString(
			"comment_type IN ('comment', 'pingback', 'trackback')",
			$clauses['where'],
			'Failed to assert that SQL query replaces empty string in IN clause.'
		);
		remove_all_filters( 'comments_clauses' );

		// Test SQL modification for both IN and NOT IN clauses.
		$query                             = new WP_Comment_Query();
		$query->query_vars['type']         = '';
		$query->query_vars['type__not_in'] = array( 'pingback' );
		$instance->exclude_rsvp_from_comment_query( $query );
		$clauses = array(
			'where' => "comment_type IN ('comment', '') AND comment_type NOT IN ('pingback')",
		);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );
		$this->assertStringContainsString(
			"comment_type IN ('comment', 'pingback', 'trackback')",
			$clauses['where'],
			'Failed to assert that SQL query replaces empty string in IN clause with both clauses present.'
		);
		$this->assertStringContainsString(
			"comment_type NOT IN ('rsvp','gatherpress_rsvp')",
			$clauses['where'],
			'Failed to assert that SQL query excludes RSVP types with both clauses present.'
		);
		remove_all_filters( 'comments_clauses' );

		// Simulate WordPress building the query.
		$clauses = array(
			'where' => "comment_type IN ('comment', 'pingback', 'trackback')",
		);

		// First call exclude_rsvp_from_comment_query to add the filter.
		$instance->exclude_rsvp_from_comment_query( $query );

		// Then apply the filter.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$modified_clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );

		// Verify that RSVP types are excluded.
		$this->assertStringContainsString(
			"comment_type NOT IN ('rsvp','gatherpress_rsvp')",
			$modified_clauses['where'],
			'SQL query should exclude RSVP types.'
		);
		remove_all_filters( 'comments_clauses' );

		// Test SQL modification for empty type and type__in.
		$query->query_vars = array(
			'type'     => '',
			'type__in' => array(),
		);

		// Simulate WordPress building the query.
		$clauses = array(
			'where' => "comment_type IN ('comment', 'pingback', 'trackback')",
		);

		// First call exclude_rsvp_from_comment_query to add the filter.
		$instance->exclude_rsvp_from_comment_query( $query );

		// Then apply the filter.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$modified_clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );

		// Verify that RSVP types are excluded.
		$this->assertStringContainsString(
			"comment_type NOT IN ('rsvp','gatherpress_rsvp')",
			$modified_clauses['where'],
			'SQL query should exclude RSVP types when type and type__in are empty.'
		);
		remove_all_filters( 'comments_clauses' );

		// Test SQL modification for existing NOT IN clause.
		$query->query_vars = array(
			'type__not_in' => array( 'custom_type' ),
		);

		// Simulate WordPress building the query.
		$clauses = array(
			'where' => "comment_type NOT IN ('custom_type')",
		);

		// First call exclude_rsvp_from_comment_query to add the filter.
		$instance->exclude_rsvp_from_comment_query( $query );

		// Then apply the filter.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$modified_clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );

		// Verify that RSVP types are added to the NOT IN clause.
		$this->assertStringContainsString(
			"comment_type NOT IN ('rsvp','gatherpress_rsvp')",
			$modified_clauses['where'],
			'SQL query should include RSVP types in NOT IN clause.'
		);
		remove_all_filters( 'comments_clauses' );

		// Test SQL modification for empty string in IN clause.
		$query->query_vars = array(
			'type'     => '',
			'type__in' => array( '' ),
		);

		// Simulate WordPress building the query.
		$clauses = array(
			'where' => "comment_type IN ('comment', 'pingback', 'trackback', '')",
		);

		// First call exclude_rsvp_from_comment_query to add the filter.
		$instance->exclude_rsvp_from_comment_query( $query );

		// Then apply the filter.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$modified_clauses = apply_filters_ref_array( 'comments_clauses', array( $clauses, $query ) );

		// Verify that empty string is replaced and RSVP types are excluded.
		$this->assertStringContainsString(
			"comment_type IN ('comment', 'pingback', 'trackback')",
			$modified_clauses['where'],
			'SQL query should replace empty string in IN clause.'
		);
		$this->assertStringContainsString(
			"comment_type NOT IN ('rsvp','gatherpress_rsvp')",
			$modified_clauses['where'],
			'SQL query should exclude RSVP types.'
		);
		remove_all_filters( 'comments_clauses' );
	}
}
