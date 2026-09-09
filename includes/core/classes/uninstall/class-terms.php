<?php
/**
 * Uninstall task that removes the plugin's taxonomies and their terms.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;

/**
 * Class Terms.
 *
 * Removes every term the plugin's four taxonomies own, together with their
 * term meta and their relationships.
 *
 * Scoped by taxonomy throughout. Two of the four taxonomies are registered
 * against comments rather than posts, so `term_relationships.object_id`
 * holds comment IDs for those and post IDs for the others; joining through
 * `term_taxonomy` is what keeps the two apart.
 *
 * The shared `terms` table is only touched for rows no remaining taxonomy
 * uses, because a term row can be shared by several taxonomies and deleting
 * it on sight would remove a category or tag that has nothing to do with
 * this plugin.
 *
 * The four placeholders are written out in each statement rather than built
 * from `taxonomies()`, so the query text is literal and reviewable and the
 * prepared-statement sniffs can count the arguments. `test_taxonomy_count`
 * fails if a fifth taxonomy is added without updating the statements.
 *
 * @since 0.36.0
 */
final class Terms extends Base {

	/**
	 * Whether the administrator opted in to removing terms.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_TERMS );
	}

	/**
	 * The taxonomies this task owns.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The taxonomy names.
	 */
	public static function taxonomies(): array {
		return array(
			Topic::TAXONOMY,
			Venue::TAXONOMY,
			Status::TAXONOMY,
			Provider::TAXONOMY,
		);
	}

	/**
	 * Remove the current site's plugin-owned terms.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		global $wpdb;

		// Relationships, joined through term_taxonomy so only this plugin's
		// taxonomies are matched whatever object type the ID refers to.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE tr FROM %i AS tr'
				. ' INNER JOIN %i AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
				. ' WHERE tt.taxonomy IN ( %s, %s, %s, %s )',
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
				Topic::TAXONOMY,
				Venue::TAXONOMY,
				Status::TAXONOMY,
				Provider::TAXONOMY
			)
		);

		// Term meta for terms this plugin's taxonomies own, under the same
		// condition the term row delete below uses. Meta hangs off the term
		// row, so a term row that survives because another taxonomy still
		// uses it must keep its meta with it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE tm FROM %i AS tm'
				. ' INNER JOIN %i AS tt ON tt.term_id = tm.term_id'
				. ' WHERE tt.taxonomy IN ( %s, %s, %s, %s )'
				. ' AND NOT EXISTS ('
				. ' SELECT 1 FROM ( SELECT term_id, taxonomy FROM %i ) AS other'
				. ' WHERE other.term_id = tm.term_id'
				. ' AND other.taxonomy NOT IN ( %s, %s, %s, %s ) )',
				$wpdb->termmeta,
				$wpdb->term_taxonomy,
				Topic::TAXONOMY,
				Venue::TAXONOMY,
				Status::TAXONOMY,
				Provider::TAXONOMY,
				$wpdb->term_taxonomy,
				Topic::TAXONOMY,
				Venue::TAXONOMY,
				Status::TAXONOMY,
				Provider::TAXONOMY
			)
		);

		// Term rows, but only where no taxonomy outside this plugin still
		// uses them. A term row is shared, so an unconditional delete here
		// would take a category or tag that happens to share the term_id.
		// The inner SELECT is wrapped in a derived table because MySQL will
		// not read from the table being deleted from in a bare subquery.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE t FROM %i AS t'
				. ' INNER JOIN %i AS tt ON tt.term_id = t.term_id'
				. ' WHERE tt.taxonomy IN ( %s, %s, %s, %s )'
				. ' AND NOT EXISTS ('
				. ' SELECT 1 FROM ( SELECT term_id, taxonomy FROM %i ) AS other'
				. ' WHERE other.term_id = t.term_id'
				. ' AND other.taxonomy NOT IN ( %s, %s, %s, %s ) )',
				$wpdb->terms,
				$wpdb->term_taxonomy,
				Topic::TAXONOMY,
				Venue::TAXONOMY,
				Status::TAXONOMY,
				Provider::TAXONOMY,
				$wpdb->term_taxonomy,
				Topic::TAXONOMY,
				Venue::TAXONOMY,
				Status::TAXONOMY,
				Provider::TAXONOMY
			)
		);

		// The taxonomy rows themselves, last: every statement above joins
		// through this table to decide what it owns.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE taxonomy IN ( %s, %s, %s, %s )',
				$wpdb->term_taxonomy,
				Topic::TAXONOMY,
				Venue::TAXONOMY,
				Status::TAXONOMY,
				Provider::TAXONOMY
			)
		);
	}
}
