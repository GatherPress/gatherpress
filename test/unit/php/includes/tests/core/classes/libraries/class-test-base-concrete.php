<?php
/**
 * Concrete test implementation of the libraries Base class.
 *
 * Lets the Base tests exercise code paths the live Action Scheduler
 * subclass can't reach in a healthy test environment — in particular
 * the "library missing" notice render and the `file_exists()` false
 * branch in `load_library()`.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Libraries;

use GatherPress\Core\Libraries\Base;

/**
 * Concrete library wrapper that reports itself as unavailable.
 *
 * Points at a library path that intentionally doesn't exist on disk so
 * `load_library()`'s `file_exists()` guard takes the false branch, and
 * hard-codes `is_available()` to false so `maybe_render_missing_notice()`
 * takes the render branch.
 */
class Test_Base_Concrete extends Base {
	/**
	 * {@inheritDoc}
	 */
	protected function get_library_entry(): string {
		return '__test_missing_library__/entry.php';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_library_name(): string {
		return 'Test Library';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function is_available(): bool {
		return false;
	}
}
