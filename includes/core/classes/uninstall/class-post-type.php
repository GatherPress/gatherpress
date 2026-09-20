<?php
/**
 * Shared uninstall behavior for tasks that remove one post type.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Flag\Base as Flag;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Status;

/**
 * Class Post_Type.
 *
 * Removes every post of one post type together with the rows that only
 * exist because those posts do: their meta, their revisions, the comments
 * recorded against them, and their term relationships.
 *
 * The statements run in dependency order and delete the posts last, because
 * every other statement selects its rows by joining back to the post table.
 *
 * Comments go whether or not the RSVP task was opted in to. That is
 * deliberate: leaving comment rows whose `comment_post_ID` points at a
 * deleted post is corruption, not conservation. Opting in to removing
 * events therefore removes the RSVPs on those events, and the settings
 * screen says so.
 *
 * Attachments are left alone. Their rows keep a `post_parent` pointing at a
 * deleted post, which core tolerates and treats as unattached, and removing
 * them here would orphan files on disk that this task cannot clean up.
 *
 * @since 0.36.0
 */
abstract class Post_Type extends Base {

	/**
	 * This task deletes rows with SQL.
	 *
	 * @since 0.36.0
	 *
	 * @return bool Always true.
	 */
	public function invalidates_cache(): bool {
		return true;
	}

	/**
	 * The post type this task removes.
	 *
	 * @since 0.36.0
	 *
	 * @return string The post type name.
	 */
	abstract protected function post_type(): string;

	/**
	 * Remove the current site's posts of this type.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function remove_posts(): void {
		global $wpdb;

		$post_type = $this->post_type();
		$counts    = $this->count_published_relationships( $post_type );

		// Comment meta for every comment on a post that is about to go.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE cm FROM %i AS cm'
				. ' INNER JOIN %i AS c ON c.comment_ID = cm.comment_id'
				. ' INNER JOIN %i AS p ON p.ID = c.comment_post_ID'
				. ' WHERE p.post_type = %s',
				$wpdb->commentmeta,
				$wpdb->comments,
				$wpdb->posts,
				$post_type
			)
		);

		// Comments on those posts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE c FROM %i AS c'
				. ' INNER JOIN %i AS p ON p.ID = c.comment_post_ID'
				. ' WHERE p.post_type = %s',
				$wpdb->comments,
				$wpdb->posts,
				$post_type
			)
		);

		// Post meta, for the posts and for their revisions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE pm FROM %i AS pm'
				. ' INNER JOIN %i AS p ON p.ID = pm.post_id'
				. ' LEFT JOIN %i AS parent ON parent.ID = p.post_parent'
				. ' WHERE p.post_type = %s'
				. ' OR ( p.post_type = %s AND parent.post_type = %s )',
				$wpdb->postmeta,
				$wpdb->posts,
				$wpdb->posts,
				$post_type,
				'revision',
				$post_type
			)
		);

		$this->delete_term_relationships( $post_type );

		// Revisions, while their parent still identifies them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE r FROM %i AS r'
				. ' INNER JOIN %i AS parent ON parent.ID = r.post_parent'
				. ' WHERE r.post_type = %s AND parent.post_type = %s',
				$wpdb->posts,
				$wpdb->posts,
				'revision',
				$post_type
			)
		);

		// The posts themselves.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE post_type = %s',
				$wpdb->posts,
				$post_type
			)
		);

		$this->decrement_term_counts( $counts );
	}

	/**
	 * How many published posts of this type each term is counted for.
	 *
	 * Read before the relationships are deleted, so the counts can be
	 * corrected afterwards. Only published posts are counted, because that
	 * is what core's `_update_post_term_count()` puts in the column.
	 *
	 * A taxonomy registered against comments stores comment IDs in
	 * `term_relationships.object_id`, and a comment ID can equal a post ID,
	 * so the RSVP taxonomies are excluded here and in the delete below.
	 * Nothing in the schema distinguishes the two kinds of row.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $post_type The post type being removed.
	 *
	 * @return array<int, int> Map of term_taxonomy_id to the number of published posts.
	 */
	protected function count_published_relationships( string $post_type ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bookkeeping; not a read path.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT tr.term_taxonomy_id AS term_taxonomy_id, COUNT(*) AS total'
				. ' FROM %i AS tr'
				. ' INNER JOIN %i AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
				. ' INNER JOIN %i AS p ON p.ID = tr.object_id'
				. ' WHERE p.post_type = %s AND p.post_status = %s'
				. ' AND tt.taxonomy NOT IN ( %s, %s, %s )'
				. ' GROUP BY tr.term_taxonomy_id',
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
				$wpdb->posts,
				$post_type,
				'publish',
				Status::TAXONOMY,
				Provider::TAXONOMY,
				Flag::TAXONOMY
			)
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->term_taxonomy_id ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Remove the term relationships belonging to this post type's posts.
	 *
	 * Every taxonomy is in scope, not only this plugin's: a post may also
	 * carry core terms, and those rows go with the post. The RSVP comment
	 * taxonomies are the exception, for the object_id reason described in
	 * {@see self::count_published_relationships()}.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $post_type The post type being removed.
	 *
	 * @return void
	 */
	protected function delete_term_relationships( string $post_type ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE tr FROM %i AS tr'
				. ' INNER JOIN %i AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
				. ' INNER JOIN %i AS p ON p.ID = tr.object_id'
				. ' WHERE p.post_type = %s'
				. ' AND tt.taxonomy NOT IN ( %s, %s, %s )',
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
				$wpdb->posts,
				$post_type,
				Status::TAXONOMY,
				Provider::TAXONOMY,
				Flag::TAXONOMY
			)
		);
	}

	/**
	 * Take the removed posts back out of the term counts.
	 *
	 * `term_taxonomy.count` is maintained by core, not by the database, so
	 * deleting relationships in SQL leaves it reading high. Left alone, a
	 * category that had ten events still says ten on a site where
	 * GatherPress no longer exists.
	 *
	 * Counts are decremented rather than recalculated so nothing outside
	 * what this task deleted is touched, and clamped at zero in case the
	 * stored count was already low.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array<int, int> $counts Map of term_taxonomy_id to the number of published posts.
	 *
	 * @return void
	 */
	protected function decrement_term_counts( array $counts ): void {
		global $wpdb;

		foreach ( $counts as $term_taxonomy_id => $total ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bookkeeping; not a read path.
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET count = GREATEST( count - %d, 0 ) WHERE term_taxonomy_id = %d',
					$wpdb->term_taxonomy,
					$total,
					$term_taxonomy_id
				)
			);
		}
	}
}
