<?php
/**
 * Unit tests for the shared taxonomy removal used by the uninstall tasks.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Topic;
use GatherPress\Core\Uninstall\Taxonomy_Cleanup;
use GatherPress\Tests\Base;

/**
 * Class Test_Taxonomy_Cleanup.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Taxonomy_Cleanup
 */
class Test_Taxonomy_Cleanup extends Base {

	/**
	 * How many rows a table holds for a term.
	 *
	 * @since 0.36.0
	 *
	 * @param string $table   The table property name on `$wpdb`.
	 * @param int    $term_id The term ID.
	 *
	 * @return int The number of rows.
	 */
	protected function count_rows( string $table, int $term_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows in a test.
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE term_id = %d', $wpdb->{$table}, $term_id )
		);
	}

	/**
	 * A taxonomy's terms, meta and relationships all go.
	 *
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_removes_a_taxonomy_completely(): void {
		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Topic::TAXONOMY ) );
		$post_id = (int) self::factory()->post->create();

		add_term_meta( $term_id, 'gatherpress_note', 'stored' );
		wp_set_object_terms( $post_id, array( $term_id ), Topic::TAXONOMY );

		Taxonomy_Cleanup::remove( Topic::TAXONOMY );

		$this->assertSame( 0, $this->count_rows( 'terms', $term_id ), 'The term row goes.' );
		$this->assertSame( 0, $this->count_rows( 'termmeta', $term_id ), 'The term meta goes.' );
		$this->assertSame( 0, $this->count_rows( 'term_taxonomy', $term_id ), 'The taxonomy row goes.' );

		$this->assertEmpty(
			wp_get_object_terms( $post_id, Topic::TAXONOMY ),
			'The relationship goes with the taxonomy.'
		);
		$this->assertNotEmpty(
			wp_get_object_terms( $post_id, 'category' ),
			'The post keeps the terms that belong to taxonomies this plugin does not own.'
		);
	}

	/**
	 * A term row another taxonomy still uses is spared, and keeps its meta.
	 *
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_spares_a_term_row_another_taxonomy_uses(): void {
		global $wpdb;

		$term_id = (int) self::factory()->term->create( array( 'taxonomy' => Topic::TAXONOMY ) );

		add_term_meta( $term_id, 'gatherpress_note', 'stored' );

		// A term row can be shared between taxonomies. Adding the second row
		// directly is how that state is reached, since wp_insert_term() makes
		// a new term row for a name it has not seen in the target taxonomy.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Building the fixture.
		$wpdb->insert(
			$wpdb->term_taxonomy,
			array(
				'term_id'     => $term_id,
				'taxonomy'    => 'category',
				'description' => '',
				'parent'      => 0,
				'count'       => 0,
			),
			array( '%d', '%s', '%s', '%d', '%d' )
		);

		Taxonomy_Cleanup::remove( Topic::TAXONOMY );

		$this->assertSame(
			1,
			$this->count_rows( 'terms', $term_id ),
			'Deleting a shared term row would take a category that has nothing to do with this plugin.'
		);
		$this->assertSame(
			1,
			$this->count_rows( 'termmeta', $term_id ),
			'Meta hangs off the term row, so it stays as long as the row does.'
		);
		$this->assertSame(
			1,
			$this->count_rows( 'term_taxonomy', $term_id ),
			'Only the removed taxonomy loses its row.'
		);
	}

	/**
	 * Another taxonomy's rows are left alone.
	 *
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_leaves_other_taxonomies_alone(): void {
		$topic_id    = (int) self::factory()->term->create( array( 'taxonomy' => Topic::TAXONOMY ) );
		$category_id = (int) self::factory()->term->create( array( 'taxonomy' => 'category' ) );

		Taxonomy_Cleanup::remove( Topic::TAXONOMY );

		$this->assertSame( 0, $this->count_rows( 'term_taxonomy', $topic_id ), 'The named taxonomy goes.' );
		$this->assertSame( 1, $this->count_rows( 'term_taxonomy', $category_id ), 'Nothing else does.' );
	}

	/**
	 * The hierarchy cache core keeps for the taxonomy goes with it.
	 *
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_removes_the_term_hierarchy_option(): void {
		$parent = (int) self::factory()->term->create( array( 'taxonomy' => Topic::TAXONOMY ) );

		self::factory()->term->create(
			array(
				'taxonomy' => Topic::TAXONOMY,
				'parent'   => $parent,
			)
		);

		// Core writes the option the first time it walks the hierarchy.
		_get_term_hierarchy( Topic::TAXONOMY );

		$this->assertNotFalse(
			get_option( sprintf( '%s_children', Topic::TAXONOMY ) ),
			'Pre-condition: core is keeping a hierarchy cache for this taxonomy.'
		);

		Taxonomy_Cleanup::remove( Topic::TAXONOMY );

		$this->assertFalse(
			get_option( sprintf( '%s_children', Topic::TAXONOMY ) ),
			'An option describing terms that no longer exist is litter.'
		);
	}
}
