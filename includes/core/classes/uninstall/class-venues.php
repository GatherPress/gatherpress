<?php
/**
 * Uninstall task that removes venue posts and the terms that shadow them.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Venue;

/**
 * Class Venues.
 *
 * Removes the venue posts and everything that only exists because they do,
 * then removes the hidden taxonomy that shadows them.
 *
 * The taxonomy goes with the posts rather than on a switch of its own. It
 * holds one term per venue, kept in lockstep with the post by
 * `Shadow_Source`, so a term left behind after its venue is deleted names a
 * venue that no longer exists. The taxonomy's `online-event` sentinel term
 * is not a shadow of any post, but it belongs to the same venue subsystem
 * and goes at the same time.
 *
 * @since 0.36.0
 */
final class Venues extends Post_Type {

	/**
	 * The preference that gates this task.
	 *
	 * @since 0.36.0
	 *
	 * @return string The task key.
	 */
	protected function preference(): string {
		return Preferences::TASK_VENUES;
	}

	/**
	 * The post type this task removes.
	 *
	 * @since 0.36.0
	 *
	 * @return string The venue post type name.
	 */
	protected function post_type(): string {
		return Venue::POST_TYPE;
	}

	/**
	 * Remove the current site's venues and their shadow terms.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		$this->remove_posts();

		Taxonomy_Cleanup::remove( Venue::TAXONOMY );
	}
}
