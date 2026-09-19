<?php
/**
 * Unit tests for the event and venue post uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Uninstall\Posts;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Posts.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Posts
 */
class Test_Posts extends Base {

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
	 * The task stays off until it is opted in to.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_does_not_apply_by_default(): void {
		$this->assertFalse(
			( new Posts() )->applies(),
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
		Preferences::save( array( Preferences::TASK_POSTS => true ) );

		$this->assertTrue( ( new Posts() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * Events survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_posts_alone_without_opt_in(): void {
		$event_id = (int) self::factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		( new Posts() )->run();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the row survived.
		$rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $event_id )
		);

		$this->assertSame( 1, $rows, 'A task that was never opted in to must remove nothing.' );
	}

	/**
	 * Events and venues go, with their meta.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_events_venues_and_meta(): void {
		Preferences::save( array( Preferences::TASK_POSTS => true ) );

		$event_id = (int) self::factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);
		$venue_id = (int) self::factory()->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);

		update_post_meta( $event_id, 'gatherpress_timezone', 'America/New_York' );

		( new Posts() )->run();

		global $wpdb;

		// Assert against the rows, not through get_post(): the task deletes
		// with SQL, so the object cache still holds the posts until
		// Setup::run() flushes it at the end of a real uninstall.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$post_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID IN ( %d, %d )", $event_id, $venue_id )
		);

		$this->assertSame( 0, $post_rows, 'The event and the venue are removed.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$meta_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $event_id )
		);

		$this->assertSame( 0, $meta_rows, 'The event meta is removed.' );
	}

	/**
	 * Ordinary posts are left alone.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_ordinary_posts_alone(): void {
		Preferences::save( array( Preferences::TASK_POSTS => true ) );

		$post_id = (int) self::factory()->post->create();

		self::factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );

		( new Posts() )->run();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the row survived.
		$rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $post_id )
		);

		$this->assertSame( 1, $rows, 'A post that is not an event or a venue must survive.' );
	}

	/**
	 * Comments on a removed event go with it, so no row is orphaned.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_comments_on_removed_posts(): void {
		Preferences::save( array( Preferences::TASK_POSTS => true ) );

		$event_id   = (int) self::factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);
		$comment_id = (int) self::factory()->comment->create(
			array(
				'comment_post_ID' => $event_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		update_comment_meta( $comment_id, 'gatherpress_custom_dietary', 'vegan' );

		( new Posts() )->run();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$comment_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_ID = %d", $comment_id )
		);

		$this->assertSame(
			0,
			$comment_rows,
			'An RSVP on a removed event goes with it, whatever the comment opt-in says.'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$meta_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE comment_id = %d", $comment_id )
		);

		$this->assertSame( 0, $meta_rows, 'The comment meta goes too.' );
	}

	/**
	 * Revisions of a removed event go with it.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_revisions_of_removed_posts(): void {
		Preferences::save( array( Preferences::TASK_POSTS => true ) );

		$event_id    = (int) self::factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);
		$revision_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_parent' => $event_id,
			)
		);

		( new Posts() )->run();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $revision_id )
		);

		$this->assertSame( 0, $rows, 'The revision goes with its parent.' );
	}
}
