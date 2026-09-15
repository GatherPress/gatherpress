<?php
/**
 * Uninstall task that removes RSVP comments.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Status;

/**
 * Class Comments.
 *
 * Removes the comment rows the plugin stores RSVPs in, together with the
 * comment meta the custom fields write and the term relationships the RSVP
 * status and provider taxonomies attach.
 *
 * Both RSVP taxonomies are registered against the `comment` object type, so
 * their rows in `term_relationships` hold comment IDs where the rest of the
 * table holds post IDs. Nothing in the schema distinguishes the two, so the
 * cleanup is scoped by `term_taxonomy_id` and never by `object_id` alone.
 *
 * @since 0.36.0
 */
final class Comments extends Base {

	/**
	 * Whether the administrator opted in to removing RSVPs.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_COMMENTS );
	}

	/**
	 * Remove the current site's RSVP comments and everything hanging off them.
	 *
	 * Ordered so each statement can still find its rows: the dependent
	 * tables are cleared while the comment rows they are selected through
	 * still exist, and the comments themselves go last.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		global $wpdb;

		// Term relationships, scoped to the two comment taxonomies so no post
		// relationship can be caught by a matching object_id.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE tr FROM %i AS tr'
				. ' INNER JOIN %i AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
				. ' INNER JOIN %i AS c ON c.comment_ID = tr.object_id'
				. ' WHERE tt.taxonomy IN ( %s, %s ) AND c.comment_type = %s',
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
				$wpdb->comments,
				Status::TAXONOMY,
				Provider::TAXONOMY,
				Rsvp::COMMENT_TYPE
			)
		);

		// Comment meta, including the `gatherpress_custom_*` custom fields.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE cm FROM %i AS cm'
				. ' INNER JOIN %i AS c ON c.comment_ID = cm.comment_id'
				. ' WHERE c.comment_type = %s',
				$wpdb->commentmeta,
				$wpdb->comments,
				Rsvp::COMMENT_TYPE
			)
		);

		// The comments themselves.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE comment_type = %s',
				$wpdb->comments,
				Rsvp::COMMENT_TYPE
			)
		);
	}
}
