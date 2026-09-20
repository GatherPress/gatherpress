<?php
/**
 * Uninstall task that removes the topic taxonomy.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Topic;

/**
 * Class Topics.
 *
 * Removes the topic taxonomy, its terms and their meta.
 *
 * Topics stand on their own. They describe events but are not derived from
 * them, so someone removing events can keep the vocabulary they built, and
 * someone keeping events can drop it.
 *
 * @since 0.36.0
 */
final class Topics extends Base {

	/**
	 * The preference that gates this task.
	 *
	 * @since 0.36.0
	 *
	 * @return string The task key.
	 */
	protected function preference(): string {
		return Preferences::TASK_TOPICS;
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
	 * Remove the current site's topics.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		Taxonomy_Cleanup::remove( Topic::TAXONOMY );
	}
}
