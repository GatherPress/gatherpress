<?php
/**
 * Uninstall task that removes event and venue posts.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Venue;

/**
 * Class Posts.
 *
 * Removes the event and venue posts, their meta, their revisions, and the
 * rows that point at them from the comment and term-relationship tables.
 *
 * The statements run in dependency order and delete the posts last, because
 * every other statement selects its rows by joining back to the post table.
 *
 * This task cleans the comments on the posts it removes whether or not the
 * RSVP comment task was opted in to. That is deliberate: leaving comment
 * rows whose `comment_post_ID` points at a deleted post is corruption, not
 * conservation. Opting in to removing events therefore removes the RSVPs on
 * those events, and the settings screen says so.
 *
 * Attachments are left alone. Their rows keep a `post_parent` pointing at a
 * deleted event, which core tolerates and treats as unattached, and removing
 * them here would orphan files on disk that this task cannot clean up.
 *
 * @since 0.36.0
 */
final class Posts extends Base {

	/**
	 * Whether the administrator opted in to removing events and venues.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_POSTS );
	}

	/**
	 * Remove the current site's event and venue posts.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		global $wpdb;

		// Comment meta for every comment on a post that is about to go.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE cm FROM %i AS cm'
				. ' INNER JOIN %i AS c ON c.comment_ID = cm.comment_id'
				. ' INNER JOIN %i AS p ON p.ID = c.comment_post_ID'
				. ' WHERE p.post_type IN ( %s, %s )',
				$wpdb->commentmeta,
				$wpdb->comments,
				$wpdb->posts,
				Event::POST_TYPE,
				Venue::POST_TYPE
			)
		);

		// Comments on those posts.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE c FROM %i AS c'
				. ' INNER JOIN %i AS p ON p.ID = c.comment_post_ID'
				. ' WHERE p.post_type IN ( %s, %s )',
				$wpdb->comments,
				$wpdb->posts,
				Event::POST_TYPE,
				Venue::POST_TYPE
			)
		);

		// Post meta, for the posts and for their revisions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE pm FROM %i AS pm'
				. ' INNER JOIN %i AS p ON p.ID = pm.post_id'
				. ' LEFT JOIN %i AS parent ON parent.ID = p.post_parent'
				. ' WHERE p.post_type IN ( %s, %s )'
				. ' OR ( p.post_type = %s AND parent.post_type IN ( %s, %s ) )',
				$wpdb->postmeta,
				$wpdb->posts,
				$wpdb->posts,
				Event::POST_TYPE,
				Venue::POST_TYPE,
				'revision',
				Event::POST_TYPE,
				Venue::POST_TYPE
			)
		);

		// Term relationships for those posts, across every taxonomy: a venue
		// or event may also carry core terms, and those rows go with the post.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE tr FROM %i AS tr'
				. ' INNER JOIN %i AS p ON p.ID = tr.object_id'
				. ' WHERE p.post_type IN ( %s, %s )',
				$wpdb->term_relationships,
				$wpdb->posts,
				Event::POST_TYPE,
				Venue::POST_TYPE
			)
		);

		// Revisions, while their parent still identifies them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE r FROM %i AS r'
				. ' INNER JOIN %i AS parent ON parent.ID = r.post_parent'
				. ' WHERE r.post_type = %s AND parent.post_type IN ( %s, %s )',
				$wpdb->posts,
				$wpdb->posts,
				'revision',
				Event::POST_TYPE,
				Venue::POST_TYPE
			)
		);

		// The posts themselves.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE post_type IN ( %s, %s )',
				$wpdb->posts,
				Event::POST_TYPE,
				Venue::POST_TYPE
			)
		);
	}
}
