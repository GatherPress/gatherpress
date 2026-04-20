<?php
/**
 * Wrapper for the vendored Action Scheduler library.
 *
 * Action Scheduler is installed as a Composer dependency
 * (`woocommerce/action-scheduler`) and routed to
 * `includes/libraries/action-scheduler/` via `composer/installers`. This
 * class loads its entry file during plugin bootstrap so the rest of
 * GatherPress can call `as_enqueue_async_action()` (and friends) to
 * schedule async / recurring jobs through a persistent queue instead of
 * WP-Cron. Heavy lifting — loading the entry file, hooking the
 * missing-library admin notice — lives on {@see Base}; this subclass
 * only declares what's specific to Action Scheduler.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Libraries;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Action_Scheduler.
 *
 * Thin wrapper around the vendored Action Scheduler library. Uses the
 * Singleton trait so the bootstrap `admin_notices` hook stays registered
 * exactly once no matter how many call sites reach for the wrapper.
 *
 * @since 1.0.0
 */
class Action_Scheduler extends Base {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Entry-file path relative to `includes/libraries/`.
	 *
	 * Base prepends the shared `includes/libraries/` prefix so subclasses
	 * only declare the per-library portion.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LIBRARY_ENTRY = 'action-scheduler/action-scheduler.php';

	/**
	 * Human-readable library name for the missing-library admin notice.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LIBRARY_NAME = 'Action Scheduler';

	/**
	 * {@inheritDoc}
	 */
	protected function get_library_entry(): string {
		return self::LIBRARY_ENTRY;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_library_name(): string {
		return self::LIBRARY_NAME;
	}

	/**
	 * Whether Action Scheduler is loaded and ready to accept jobs.
	 *
	 * Checks for `as_enqueue_async_action` — the most commonly-used
	 * public function in the AS API. When AS's `plugins_loaded`
	 * priority-1 init has run, this symbol is defined.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'as_enqueue_async_action' );
	}
}
