<?php
/**
 * Wires the recurrence subsystem.
 *
 * Instantiates the `Recurrence\*` sibling singletons so `Event\Setup` can hand
 * off the whole subsystem with a single `Recurrence\Setup::get_instance()`
 * line, the same shape `Rsvp\Setup` and `Venue\Setup` use.
 *
 * Recurrence belongs to the `gatherpress-event-date` post type support, not to
 * the `gatherpress_event` post type, so any hook this class grows must be
 * registered against `get_post_types_by_support( 'gatherpress-event-date' )`
 * rather than against a hardcoded post type slug.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Setup.
 *
 * Singleton owning recurrence subsystem wiring.
 *
 * @since 0.36.0
 */
final class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * Instantiates the sibling `Recurrence\*` singletons before wiring hooks, so
	 * adding a class to the subsystem lands as one line here rather than as an
	 * edit to `Event\Setup`.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->instantiate_classes();
		$this->setup_hooks();
	}

	/**
	 * Instantiate each `Recurrence\*` sibling singleton.
	 *
	 * Each sibling is a singleton, so repeat calls are safe.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Context::get_instance();
		Meta::get_instance();
		Occurrences::get_instance();
		Query::get_instance();
		Rest_Api::get_instance();
		Rewrite::get_instance();
		Rsvp_Occurrence::get_instance();
		Series::get_instance();
		Splitter::get_instance();
	}

	/**
	 * Set up hooks for the recurrence subsystem.
	 *
	 * Empty while the contract is frozen. Each downstream task adds its own
	 * hooks here or in the class that owns them.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
	}
}
