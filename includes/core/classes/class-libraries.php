<?php
/**
 * Bootstrap loader for GatherPress's vendored PHP libraries.
 *
 * Each Composer-installed library that ships under
 * `includes/libraries/` (via the `installer-paths` stanza in
 * `composer.json`) has a thin wrapper subclass in
 * `includes/core/classes/libraries/`. This class exists purely so
 * `Setup::instantiate_classes()` has a single place to hand off all
 * library loading to — adding a new vendored library means adding one
 * line to this class, not editing the Setup instantiation list.
 *
 * Mirrors the `Settings` / `Settings\*` class naming pattern.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Libraries\Action_Scheduler;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Libraries.
 *
 * Instantiates every vendored-library wrapper so the rest of the plugin
 * can rely on those libraries being loaded (or see the non-fatal admin
 * notice the wrappers surface when a library is missing).
 *
 * @since 1.0.0
 */
class Libraries {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constructor — loads every wrapped library.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->load_libraries();
	}

	/**
	 * Instantiate each vendored-library wrapper.
	 *
	 * Add a single `::get_instance()` line per new library. The wrapper
	 * class's constructor takes care of requiring the library's entry
	 * file and wiring the missing-library admin notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function load_libraries(): void {
		Action_Scheduler::get_instance();
	}
}
