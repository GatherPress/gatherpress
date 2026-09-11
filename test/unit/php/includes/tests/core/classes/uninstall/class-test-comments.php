<?php
/**
 * Unit tests for the RSVP-comment uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Uninstall\Comments;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;

/**
 * Class Test_Comments.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Comments
 */
class Test_Comments extends Base {

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
	 * Create an RSVP comment with meta and a status term.
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
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		update_comment_meta( $comment_id, 'gatherpress_custom_dietary', 'vegan' );
		wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );

		return $comment_id;
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
			( new Comments() )->applies(),
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
		Preferences::save( array( Preferences::TASK_COMMENTS => true ) );

		$this->assertTrue( ( new Comments() )->applies(), 'The opt-in turns the task on.' );
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

		( new Comments() )->run();

		$this->assertInstanceOf(
			\WP_Comment::class,
			get_comment( $comment_id ),
			'A task that was never opted in to must remove nothing.'
		);
	}

	/**
	 * RSVP comments, their meta, and their terms all go.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_rsvp_comments_meta_and_terms(): void {
		Preferences::save( array( Preferences::TASK_COMMENTS => true ) );

		$post_id    = (int) self::factory()->post->create();
		$comment_id = $this->make_rsvp( $post_id );

		$this->assertSame(
			'vegan',
			get_comment_meta( $comment_id, 'gatherpress_custom_dietary', true ),
			'Pre-condition: the custom field answer is stored.'
		);

		( new Comments() )->run();

		global $wpdb;

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$term_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $comment_id )
		);

		$this->assertSame( 0, $meta_rows, 'The comment meta is removed.' );
		$this->assertSame( 0, $term_rows, 'The RSVP status relationship is removed.' );
	}

	/**
	 * Ordinary comments are left alone.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_ordinary_comments_alone(): void {
		Preferences::save( array( Preferences::TASK_COMMENTS => true ) );

		$post_id  = (int) self::factory()->post->create();
		$ordinary = (int) self::factory()->comment->create(
			array( 'comment_post_ID' => $post_id )
		);

		$this->make_rsvp( $post_id );

		( new Comments() )->run();

		global $wpdb;

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
		Preferences::save( array( Preferences::TASK_COMMENTS => true ) );

		$post_id    = (int) self::factory()->post->create();
		$comment_id = $this->make_rsvp( $post_id );

		// Give the post whose ID matches the comment ID a category, so an
		// object_id-only delete would take it.
		wp_set_object_terms( $comment_id, 'uncategorized', 'category' );

		( new Comments() )->run();

		$this->assertNotEmpty(
			wp_get_object_terms( $comment_id, 'category' ),
			'Scoping by taxonomy keeps a post relationship with a matching object_id.'
		);
	}
}
