<?php
/**
 * Shared rewrite-state snapshot for the test suites that turn on pretty permalinks.
 *
 * The filename deliberately does not match `class-test-*.php`, so PHPUnit does
 * not collect this file as a test case. It is required once from
 * `test/unit/php/bootstrap.php` and consumed with `use Rewrite_State;` inside a
 * test class.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

/**
 * Trait Rewrite_State.
 *
 * `$wp_rewrite` is a global object, not an option, so nothing it holds is undone
 * by the per-test transaction rollback. Two things leak from a test that turns
 * on pretty permalinks, and only the first is obvious.
 *
 * `permalink_structure` is the obvious one, and `WP_Rewrite::init()` puts it
 * back. `extra_permastructs` is not: turning on a pretty structure means
 * re-running `init`, which re-registers every post type *while* that structure
 * is in place, and core's `WP_Rewrite::init()` clears `extra_rules`,
 * `extra_rules_top` and `endpoints` but **not** `extra_permastructs` (measured
 * against `wp-includes/class-wp-rewrite.php`). So
 * `get_extra_permastruct( 'gatherpress_event' )` keeps answering for the rest of
 * the process and every later test's event permalinks silently become pretty
 * ones. That is a defect which surfaces in whichever file happens to run next,
 * not in the file that caused it.
 *
 * Three suites do the same sequence. Restoring in only one of them leaves the
 * other two escaping by execution order alone.
 *
 * @since 0.36.0
 */
trait Rewrite_State {

	/**
	 * The rewrite state the consuming test found, restored on the way out.
	 *
	 * @since 0.36.0
	 *
	 * @var array<string, mixed>
	 */
	protected array $rewrite_state = array();

	/**
	 * Record the rewrite state before a test disturbs it.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function snapshot_rewrite_state(): void {
		global $wp_rewrite;

		$this->rewrite_state = array(
			'structure'    => $wp_rewrite->permalink_structure,
			'permastructs' => $wp_rewrite->extra_permastructs,
		);
	}

	/**
	 * Put the rewrite state back the way the test found it.
	 *
	 * Safe to call when nothing was snapshotted, so a consuming class does not
	 * have to pair the two calls across an early failure.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function restore_rewrite_state(): void {
		global $wp_rewrite;

		if ( array() === $this->rewrite_state ) {
			return;
		}

		$wp_rewrite->extra_permastructs = $this->rewrite_state['permastructs'];

		update_option( 'permalink_structure', $this->rewrite_state['structure'] );
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();

		$this->rewrite_state = array();
	}
}
