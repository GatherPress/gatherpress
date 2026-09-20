<?php
/**
 * Unit tests for the RSVP uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Flag\Base as Flag;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Uninstall\Rsvps;
use GatherPress\Tests\Base;

/**
 * Class Test_Rsvps.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Rsvps
 */
class Test_Rsvps extends Base {

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
	 * Create an RSVP comment with meta and a term in each RSVP taxonomy.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event the RSVP belongs to.
	 *
	 * @return int The comment ID.
	 */
	protected function make_rsvp( int $post_id ): int {
		$comment_id = (int) self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '1',
			)
		);

		update_comment_meta( $comment_id, 'gatherpress_custom_dietary', 'vegan' );

		wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );
		wp_set_object_terms( $comment_id, 'wordpress-user', Provider::TAXONOMY );
		wp_set_object_terms( $comment_id, 'checked-in', Flag::TAXONOMY );

		return $comment_id;
	}

	/**
	 * How many term relationship rows an object still has.
	 *
	 * @since 0.36.0
	 *
	 * @param int $object_id The object ID.
	 *
	 * @return int The number of rows.
	 */
	protected function count_relationships( int $object_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $object_id )
		);
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
			( new Rsvps() )->applies(),
			'Removing RSVPs must wait for an explicit opt-in.'
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
		Preferences::save( array( Preferences::TASK_RSVPS => true ) );

		$this->assertTrue( ( new Rsvps() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * Removing events removes the RSVPs on them, whatever the RSVP choice says.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_when_events_are_going(): void {
		Preferences::save( array( Preferences::TASK_EVENTS => true ) );

		$this->assertTrue(
			( new Rsvps() )->applies(),
			'An RSVP whose event has been deleted is an orphan, so events carry RSVPs with them.'
		);
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
			( new Rsvps() )->invalidates_cache(),
			'A task that deletes rows with SQL has to tell the registry to flush.'
		);
	}

	/**
	 * Every taxonomy registered against an RSVP is owned by this task.
	 *
	 * @covers ::taxonomies
	 *
	 * @return void
	 */
	public function test_owns_every_rsvp_taxonomy(): void {
		$this->assertSame(
			array( Status::TAXONOMY, Provider::TAXONOMY, Flag::TAXONOMY ),
			Rsvps::taxonomies(),
			'A taxonomy left off this list survives the uninstall with no way to reach it.'
		);
	}

	/**
	 * RSVPs survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_rsvps_alone_without_opt_in(): void {
		$post_id    = (int) self::factory()->post->create();
		$comment_id = $this->make_rsvp( $post_id );

		( new Rsvps() )->run();

		$this->assertInstanceOf(
			\WP_Comment::class,
			get_comment( $comment_id ),
			'A task that was never opted in to must remove nothing.'
		);
	}

	/**
	 * RSVP comments, their meta, and all three taxonomies go.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_rsvps_meta_and_every_taxonomy(): void {
		global $wpdb;

		Preferences::save( array( Preferences::TASK_RSVPS => true ) );

		$post_id    = (int) self::factory()->post->create();
		$comment_id = $this->make_rsvp( $post_id );

		$this->assertSame(
			3,
			$this->count_relationships( $comment_id ),
			'Pre-condition: the RSVP carries a status, a provider and a flag.'
		);

		( new Rsvps() )->run();

		// Assert against the rows, not through get_comment(): the task
		// deletes with SQL, so the object cache still holds the comment
		// until Setup::run() flushes it at the end of a real uninstall.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$comment_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_ID = %d", $comment_id )
		);

		$this->assertSame( 0, $comment_rows, 'The RSVP comment row is removed.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$meta_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE comment_id = %d", $comment_id )
		);

		$this->assertSame( 0, $meta_rows, 'The comment meta is removed.' );
		$this->assertSame(
			0,
			$this->count_relationships( $comment_id ),
			'The status, provider and flag relationships all go.'
		);

		foreach ( Rsvps::taxonomies() as $taxonomy ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
			$taxonomy_rows = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy )
			);

			$this->assertSame(
				0,
				$taxonomy_rows,
				sprintf( 'The %s taxonomy is emptied.', $taxonomy )
			);
		}
	}

	/**
	 * Ordinary comments are left alone.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_ordinary_comments_alone(): void {
		global $wpdb;

		Preferences::save( array( Preferences::TASK_RSVPS => true ) );

		$post_id  = (int) self::factory()->post->create();
		$ordinary = (int) self::factory()->comment->create(
			array( 'comment_post_ID' => $post_id )
		);

		$this->make_rsvp( $post_id );

		( new Rsvps() )->run();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the row survived.
		$rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_ID = %d", $ordinary )
		);

		$this->assertSame( 1, $rows, 'A comment that is not an RSVP must survive.' );
	}

	/**
	 * A post's own term relationships are not caught by the comment sweep.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_does_not_touch_post_relationships_sharing_an_id(): void {
		Preferences::save( array( Preferences::TASK_RSVPS => true ) );

		$post_id    = (int) self::factory()->post->create();
		$comment_id = $this->make_rsvp( $post_id );

		// Give the post whose ID matches the comment ID a category, so an
		// object_id-only delete would take it.
		wp_set_object_terms( $comment_id, 'uncategorized', 'category' );

		( new Rsvps() )->run();

		$this->assertNotEmpty(
			wp_get_object_terms( $comment_id, 'category' ),
			'A comment ID can equal a post ID, so the sweep has to be scoped by taxonomy.'
		);
	}

	/**
	 * A surviving event stops counting the RSVPs it lost.
	 *
	 * @covers ::uninstall_site
	 * @covers ::decrement_comment_counts
	 *
	 * @return void
	 */
	public function test_takes_removed_rsvps_out_of_the_comment_count(): void {
		Preferences::save( array( Preferences::TASK_RSVPS => true ) );

		$post_id = (int) self::factory()->post->create();

		self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		$this->make_rsvp( $post_id );
		$this->make_rsvp( $post_id );

		wp_update_comment_count_now( $post_id );

		$this->assertSame(
			3,
			(int) get_post_field( 'comment_count', $post_id ),
			'Pre-condition: core counts the RSVPs alongside the ordinary comment.'
		);

		( new Rsvps() )->run();

		clean_post_cache( $post_id );

		$this->assertSame(
			1,
			(int) get_post_field( 'comment_count', $post_id ),
			'A post that keeps its event but loses its RSVPs must stop reporting them.'
		);
	}
}
