<?php
/**
 * Uninstall task that removes RSVP records.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Flag\Base as Flag;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Status;

/**
 * Class Rsvps.
 *
 * Removes the comment rows the plugin stores RSVPs in, the comment meta the
 * custom fields write, and the three taxonomies that classify them: the
 * response status, the identity provider, and the yes/no flags.
 *
 * Runs when RSVPs were opted in to, and also whenever events were, because
 * an RSVP whose event has been deleted is an orphan rather than data anyone
 * kept on purpose. The settings screen hides the RSVP choice once events
 * are selected for that reason.
 *
 * @since 0.36.0
 */
final class Rsvps extends Base {

	/**
	 * The taxonomies this task owns.
	 *
	 * All three are registered against comments rather than posts.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The taxonomy names.
	 */
	public static function taxonomies(): array {
		return array(
			Status::TAXONOMY,
			Provider::TAXONOMY,
			Flag::TAXONOMY,
		);
	}

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
	 * Whether RSVPs are being removed.
	 *
	 * True when the administrator opted in directly, and true when events
	 * are going: every RSVP on a deleted event would otherwise be left
	 * pointing at a post that no longer exists.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_RSVPS )
			|| Preferences::is_enabled( Preferences::TASK_EVENTS );
	}

	/**
	 * Remove the current site's RSVPs and everything hanging off them.
	 *
	 * Ordered so each statement can still find its rows: the comment counts
	 * are corrected and the comment meta cleared while the comment rows they
	 * are selected through still exist, and the comments themselves go next.
	 * The taxonomies are scoped by taxonomy name, so they can go last.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		global $wpdb;

		$this->decrement_comment_counts();

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

		foreach ( self::taxonomies() as $taxonomy ) {
			Taxonomy_Cleanup::remove( $taxonomy );
		}
	}

	/**
	 * Take the RSVPs back out of their posts' comment counts.
	 *
	 * `posts.comment_count` counts approved comments of every type, so an
	 * RSVP adds to it. Deleting the comment rows in SQL does not maintain
	 * the column, and this task runs on its own when someone removes RSVPs
	 * but keeps their events, so the posts that survive would otherwise
	 * report comments they no longer have.
	 *
	 * Runs before the comments are deleted, while the rows to subtract can
	 * still be counted, and clamps at zero in case the stored count was
	 * already low.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function decrement_comment_counts(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall bookkeeping; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i AS p'
				. ' INNER JOIN ('
				. ' SELECT comment_post_ID AS post_id, COUNT(*) AS total FROM %i'
				. ' WHERE comment_type = %s AND comment_approved = %s'
				. ' GROUP BY comment_post_ID ) AS rsvps ON rsvps.post_id = p.ID'
				. ' SET p.comment_count = GREATEST( p.comment_count - rsvps.total, 0 )',
				$wpdb->posts,
				$wpdb->comments,
				Rsvp::COMMENT_TYPE,
				'1'
			)
		);
	}
}
