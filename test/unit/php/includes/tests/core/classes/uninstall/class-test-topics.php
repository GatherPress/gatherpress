<?php
/**
 * Unit tests for the topic uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Topic;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Uninstall\Topics;
use GatherPress\Tests\Base;

/**
 * Class Test_Topics.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Topics
 */
class Test_Topics extends Base {

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
	 * How many rows the topic taxonomy still has.
	 *
	 * @since 0.36.0
	 *
	 * @return int The number of term_taxonomy rows.
	 */
	protected function count_taxonomy_rows(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", Topic::TAXONOMY )
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
			( new Topics() )->applies(),
			'Removing topics must wait for an explicit opt-in.'
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
		Preferences::save( array( Preferences::TASK_TOPICS => true ) );

		$this->assertTrue( ( new Topics() )->applies(), 'The opt-in turns the task on.' );
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
			( new Topics() )->invalidates_cache(),
			'A task that deletes rows with SQL has to tell the registry to flush.'
		);
	}

	/**
	 * Topics survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_topics_alone_without_opt_in(): void {
		self::factory()->term->create( array( 'taxonomy' => Topic::TAXONOMY ) );

		( new Topics() )->run();

		$this->assertGreaterThan(
			0,
			$this->count_taxonomy_rows(),
			'A task that was never opted in to must remove nothing.'
		);
	}

	/**
	 * Topics go once opted in to.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_topics(): void {
		global $wpdb;

		Preferences::save( array( Preferences::TASK_TOPICS => true ) );

		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Topic::TAXONOMY ) );

		add_term_meta( $term_id, 'gatherpress_topic_note', 'kept' );

		( new Topics() )->run();

		$this->assertSame( 0, $this->count_taxonomy_rows(), 'The topic taxonomy is emptied.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$term_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $term_id )
		);

		$this->assertSame( 0, $term_rows, 'The term row goes when no other taxonomy uses it.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$meta_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE term_id = %d", $term_id )
		);

		$this->assertSame( 0, $meta_rows, 'Term meta goes with the term row.' );
	}
}
