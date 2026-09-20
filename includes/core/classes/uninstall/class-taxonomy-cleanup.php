<?php
/**
 * Removal of a single taxonomy and everything hanging off it.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Taxonomy_Cleanup.
 *
 * Three uninstall tasks remove taxonomies: venues own the hidden taxonomy
 * that shadows them, RSVPs own the three that classify them, and topics own
 * theirs. The statements are the same each time, so they live here and take
 * the taxonomy name.
 *
 * One taxonomy per call, which keeps every statement's placeholders literal
 * and reviewable. A caller with several taxonomies calls this once each, and
 * the end state is the same: each call removes its own `term_taxonomy` rows
 * before the next call looks for other users of a shared term row.
 *
 * The shared `terms` table is only touched for rows no other taxonomy uses,
 * because a term row can be shared and deleting it on sight would remove a
 * category or tag that has nothing to do with this plugin.
 *
 * @since 0.36.0
 */
final class Taxonomy_Cleanup {

	/**
	 * Remove a taxonomy's relationships, term meta, terms and taxonomy rows.
	 *
	 * Scoped by taxonomy throughout. A taxonomy registered against comments
	 * stores comment IDs in `term_relationships.object_id` where the rest of
	 * the table stores post IDs, and nothing in the schema distinguishes the
	 * two, so joining through `term_taxonomy` is what keeps them apart.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $taxonomy The taxonomy to remove.
	 *
	 * @return void
	 */
	public static function remove( string $taxonomy ): void {
		global $wpdb;

		// Relationships, joined through term_taxonomy so only this taxonomy is
		// matched whatever object type the ID refers to.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE tr FROM %i AS tr'
				. ' INNER JOIN %i AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
				. ' WHERE tt.taxonomy = %s',
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
				$taxonomy
			)
		);

		// Term meta, under the same condition the term row delete below uses.
		// Meta hangs off the term row, so a term row that survives because
		// another taxonomy still uses it must keep its meta with it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE tm FROM %i AS tm'
				. ' INNER JOIN %i AS tt ON tt.term_id = tm.term_id'
				. ' WHERE tt.taxonomy = %s'
				. ' AND NOT EXISTS ('
				. ' SELECT 1 FROM ( SELECT term_id, taxonomy FROM %i ) AS other'
				. ' WHERE other.term_id = tm.term_id AND other.taxonomy <> %s )',
				$wpdb->termmeta,
				$wpdb->term_taxonomy,
				$taxonomy,
				$wpdb->term_taxonomy,
				$taxonomy
			)
		);

		// Term rows, but only where no other taxonomy still uses them. A term
		// row is shared, so an unconditional delete here would take a category
		// or tag that happens to share the term_id. The inner SELECT is wrapped
		// in a derived table because MySQL will not read from the table being
		// deleted from in a bare subquery.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE t FROM %i AS t'
				. ' INNER JOIN %i AS tt ON tt.term_id = t.term_id'
				. ' WHERE tt.taxonomy = %s'
				. ' AND NOT EXISTS ('
				. ' SELECT 1 FROM ( SELECT term_id, taxonomy FROM %i ) AS other'
				. ' WHERE other.term_id = t.term_id AND other.taxonomy <> %s )',
				$wpdb->terms,
				$wpdb->term_taxonomy,
				$taxonomy,
				$wpdb->term_taxonomy,
				$taxonomy
			)
		);

		// The taxonomy rows themselves, last: every statement above joins
		// through this table to decide what it owns.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE taxonomy = %s',
				$wpdb->term_taxonomy,
				$taxonomy
			)
		);
	}
}
