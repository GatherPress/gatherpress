<?php
/**
 * Unit tests for the taxonomy-term uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Topic;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Uninstall\Terms;
use GatherPress\Tests\Base;

/**
 * Class Test_Terms.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Terms
 */
class Test_Terms extends Base {

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
	 * Count the term_taxonomy rows for a taxonomy.
	 *
	 * @since 0.36.0
	 *
	 * @param string $taxonomy The taxonomy name.
	 *
	 * @return int The row count.
	 */
	protected function taxonomy_rows( string $taxonomy ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows directly.
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy )
		);
	}

	/**
	 * Every taxonomy the task owns is listed.
	 *
	 * @covers ::taxonomies
	 *
	 * @return void
	 */
	public function test_taxonomy_count(): void {
		$this->assertCount(
			4,
			Terms::taxonomies(),
			'The statements name four placeholders each; adding a taxonomy means updating them.'
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
			( new Terms() )->applies(),
			'Removing terms must wait for an explicit opt-in.'
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
		Preferences::save( array( Preferences::TASK_TERMS => true ) );

		$this->assertTrue( ( new Terms() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * Terms survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_terms_alone_without_opt_in(): void {
		wp_insert_term( 'Keep Me', Topic::TAXONOMY );

		( new Terms() )->run();

		$this->assertGreaterThan(
			0,
			$this->taxonomy_rows( Topic::TAXONOMY ),
			'A task that was never opted in to must remove nothing.'
		);
	}

	/**
	 * Plugin terms and their relationships go.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_plugin_terms_and_relationships(): void {
		Preferences::save( array( Preferences::TASK_TERMS => true ) );

		$term    = wp_insert_term( 'Board Games', Topic::TAXONOMY );
		$term_id = (int) $term['term_id'];
		$post_id = (int) self::factory()->post->create();

		wp_set_object_terms( $post_id, $term_id, Topic::TAXONOMY );
		add_term_meta( $term_id, 'gatherpress_test_meta', 'value' );

		global $wpdb;

		// Capture the exact relationship row before the taxonomy rows that
		// identify it are deleted. A plain object_id count would also catch
		// the default category the post factory assigns, which is meant to
		// survive.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Building the fixture.
		$term_taxonomy_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
				$term_id,
				Topic::TAXONOMY
			)
		);

		( new Terms() )->run();

		$this->assertSame(
			0,
			$this->taxonomy_rows( Topic::TAXONOMY ),
			'The taxonomy rows are removed.'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$relationships = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id = %d",
				$post_id,
				$term_taxonomy_id
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$meta_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE term_id = %d", $term_id )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$term_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $term_id )
		);

		$this->assertSame( 0, $relationships, 'The relationship is removed.' );
		$this->assertSame( 0, $meta_rows, 'The term meta is removed.' );
		$this->assertSame( 0, $term_rows, 'The term row is removed.' );
	}

	/**
	 * Categories and tags are untouched.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_core_taxonomies_alone(): void {
		Preferences::save( array( Preferences::TASK_TERMS => true ) );

		$category = wp_insert_term( 'Announcements', 'category' );
		$term_id  = (int) $category['term_id'];

		wp_insert_term( 'Board Games', Topic::TAXONOMY );

		( new Terms() )->run();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the row survived.
		$rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $term_id )
		);

		$this->assertSame( 1, $rows, 'A category must survive.' );
		$this->assertGreaterThan(
			0,
			$this->taxonomy_rows( 'category' ),
			'The category taxonomy rows must survive.'
		);
	}

	/**
	 * A term row shared with another taxonomy is kept.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_keeps_term_row_shared_with_another_taxonomy(): void {
		Preferences::save( array( Preferences::TASK_TERMS => true ) );

		global $wpdb;

		$topic   = wp_insert_term( 'Shared Name', Topic::TAXONOMY );
		$term_id = (int) $topic['term_id'];

		// Attach the same term row to a taxonomy this plugin does not own.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Building the shared-row fixture.
		$wpdb->insert(
			$wpdb->term_taxonomy,
			array(
				'term_id'     => $term_id,
				'taxonomy'    => 'category',
				'description' => '',
				'parent'      => 0,
				'count'       => 0,
			)
		);

		( new Terms() )->run();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the row survived.
		$rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE term_id = %d", $term_id )
		);

		$this->assertSame(
			1,
			$rows,
			'A term row another taxonomy still uses must not be deleted.'
		);
	}
}
