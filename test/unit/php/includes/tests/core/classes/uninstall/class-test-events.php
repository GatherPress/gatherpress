<?php
/**
 * Unit tests for the event uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Event;
use GatherPress\Core\Uninstall\Events;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Events.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Events
 */
class Test_Events extends Base {

	/**
	 * Queries captured during a run.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	protected array $captured = array();

	/**
	 * Reset the opt-in map between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();

		parent::tearDown();
	}

	/**
	 * Record every query, before the test harness rewrites it.
	 *
	 * `WP_UnitTestCase` filters `query` at the default priority to turn
	 * `DROP TABLE` into `DROP TEMPORARY TABLE`, so the real table is never
	 * dropped inside a test and the effect cannot be asserted. Capturing at
	 * priority 1 records what the task actually asked the database to do.
	 *
	 * @since 0.36.0
	 *
	 * @param string $query The query about to run.
	 *
	 * @return string The query, unchanged.
	 */
	public function capture_query( string $query ): string {
		$this->captured[] = $query;

		return $query;
	}

	/**
	 * Run the task with its queries captured.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The queries the task issued.
	 */
	protected function capture_run(): array {
		$this->captured = array();

		add_filter( 'query', array( $this, 'capture_query' ), 1 );
		( new Events() )->run();
		remove_filter( 'query', array( $this, 'capture_query' ), 1 );

		return $this->captured;
	}

	/**
	 * The drop statements among a set of captured queries.
	 *
	 * @since 0.36.0
	 *
	 * @param string[] $queries The captured queries.
	 *
	 * @return string[] The matching drop statements.
	 */
	protected function drop_statements( array $queries ): array {
		global $wpdb;

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		return array_values(
			array_filter(
				$queries,
				static function ( string $query ) use ( $table ): bool {
					return str_contains( $query, 'DROP TABLE' ) && str_contains( $query, $table );
				}
			)
		);
	}

	/**
	 * How many of the given post IDs are still in the posts table.
	 *
	 * Asserts against the rows, not through `get_post()`: the task deletes
	 * with SQL, so the object cache still holds the posts until
	 * `Setup::run()` flushes it at the end of a real uninstall.
	 *
	 * @since 0.36.0
	 *
	 * @param int ...$post_ids The post IDs to look for.
	 *
	 * @return int The number of rows found.
	 */
	protected function count_posts( int ...$post_ids ): int {
		global $wpdb;

		$found = 0;

		foreach ( $post_ids as $post_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows in a test.
			$found += (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $post_id )
			);
		}

		return $found;
	}

	/**
	 * The task stays off until it is opted in to.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_does_not_apply_by_default(): void {
		$this->assertFalse(
			( new Events() )->applies(),
			'Removing events must wait for an explicit opt-in.'
		);
	}

	/**
	 * The task applies once its preference is on.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_when_opted_in(): void {
		Preferences::save( array( Preferences::TASK_EVENTS => true ) );

		$this->assertTrue( ( new Events() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * The task reports that it writes past the object cache.
	 *
	 * @covers ::invalidates_cache
	 *
	 * @return void
	 */
	public function test_invalidates_cache(): void {
		$this->assertTrue(
			( new Events() )->invalidates_cache(),
			'A task that deletes rows with SQL has to tell the registry to flush.'
		);
	}

	/**
	 * The event post type is the one this task removes.
	 *
	 * @covers ::post_type
	 *
	 * @return void
	 */
	public function test_post_type_is_the_event_post_type(): void {
		$this->assertSame(
			Event::POST_TYPE,
			Utility::invoke_hidden_method( new Events(), 'post_type' ),
			'The task removes events and nothing else.'
		);
	}

	/**
	 * Events survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_events_alone_without_opt_in(): void {
		$event_id = (int) self::factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		$this->assertSame(
			array(),
			$this->drop_statements( $this->capture_run() ),
			'A task that was never opted in to must issue no drop at all.'
		);
		$this->assertSame(
			1,
			$this->count_posts( $event_id ),
			'A task that was never opted in to must remove nothing.'
		);
	}

	/**
	 * Events go with their meta, comments and revisions, and venues stay.
	 *
	 * @covers ::uninstall_site
	 * @covers ::remove_posts
	 *
	 * @return void
	 */
	public function test_removes_events_and_leaves_venues(): void {
		global $wpdb;

		Preferences::save( array( Preferences::TASK_EVENTS => true ) );

		$event_id = (int) self::factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);
		$venue_id = (int) self::factory()->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);

		$revision_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_parent' => $event_id,
			)
		);

		$comment_id = (int) self::factory()->comment->create(
			array( 'comment_post_ID' => $event_id )
		);

		update_post_meta( $event_id, 'gatherpress_timezone', 'America/New_York' );
		update_post_meta( $revision_id, 'gatherpress_timezone', 'America/New_York' );
		update_comment_meta( $comment_id, 'gatherpress_custom_note', 'kept a seat' );

		( new Events() )->run();

		$this->assertSame(
			0,
			$this->count_posts( $event_id, $revision_id ),
			'The event and its revision are removed.'
		);
		$this->assertSame(
			1,
			$this->count_posts( $venue_id ),
			'Removing events must leave venues where they are.'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$meta_rows = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id IN ( %d, %d )",
				$event_id,
				$revision_id
			)
		);

		$this->assertSame( 0, $meta_rows, 'Meta goes with the post and with its revisions.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$comment_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_ID = %d", $comment_id )
		);

		$this->assertSame( 0, $comment_rows, 'A comment on a deleted post would be corruption, so it goes too.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$comment_meta_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE comment_id = %d", $comment_id )
		);

		$this->assertSame( 0, $comment_meta_rows, 'Comment meta goes with the comment.' );
	}

	/**
	 * The event date table is dropped with the events.
	 *
	 * @covers ::uninstall_site
	 * @covers ::drop_table
	 *
	 * @return void
	 */
	public function test_drops_the_event_date_table(): void {
		global $wpdb;

		Preferences::save( array( Preferences::TASK_EVENTS => true ) );

		$drops = $this->drop_statements( $this->capture_run() );

		$this->assertCount( 1, $drops, 'Exactly one drop is issued for the event table.' );
		$this->assertStringContainsString(
			'IF EXISTS',
			$drops[0],
			'The drop tolerates a table that was already removed.'
		);
		$this->assertStringContainsString(
			sprintf( Event::TABLE_FORMAT, $wpdb->prefix ),
			$drops[0],
			'The drop names the event table for this site, not another one.'
		);
	}

	/**
	 * A surviving taxonomy stops counting the events it lost.
	 *
	 * @covers ::uninstall_site
	 * @covers ::remove_posts
	 * @covers ::count_published_relationships
	 * @covers ::delete_term_relationships
	 * @covers ::decrement_term_counts
	 *
	 * @return void
	 */
	public function test_takes_removed_events_out_of_term_counts(): void {
		Preferences::save( array( Preferences::TASK_EVENTS => true ) );

		register_taxonomy_for_object_type( 'category', Event::POST_TYPE );

		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => 'category' ) );

		$event_id = (int) self::factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$post_id  = (int) self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_set_object_terms( $event_id, array( $term_id ), 'category' );
		wp_set_object_terms( $post_id, array( $term_id ), 'category' );

		$this->assertSame( 2, (int) get_term( $term_id, 'category' )->count, 'Both posts are counted to begin with.' );

		( new Events() )->run();

		clean_term_cache( array( $term_id ), 'category' );

		$this->assertSame(
			1,
			(int) get_term( $term_id, 'category' )->count,
			'A category that loses an event must stop counting it, or it reads high forever.'
		);
	}

	/**
	 * A draft event was never counted, so nothing is subtracted for it.
	 *
	 * @covers ::count_published_relationships
	 * @covers ::decrement_term_counts
	 *
	 * @return void
	 */
	public function test_leaves_counts_alone_for_unpublished_events(): void {
		Preferences::save( array( Preferences::TASK_EVENTS => true ) );

		register_taxonomy_for_object_type( 'category', Event::POST_TYPE );

		$term_id  = (int) self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		$event_id = (int) self::factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$post_id  = (int) self::factory()->post->create( array( 'post_status' => 'publish' ) );

		wp_set_object_terms( $event_id, array( $term_id ), 'category' );
		wp_set_object_terms( $post_id, array( $term_id ), 'category' );

		$this->assertSame( 1, (int) get_term( $term_id, 'category' )->count, 'Only the published post is counted.' );

		( new Events() )->run();

		clean_term_cache( array( $term_id ), 'category' );

		$this->assertSame(
			1,
			(int) get_term( $term_id, 'category' )->count,
			'Subtracting a draft would push the count below what core stored.'
		);
	}
}
