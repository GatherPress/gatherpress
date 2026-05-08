<?php
/**
 * Hub for the venue map subsystem.
 *
 * Owns instantiation of the Map\* sibling singletons (Manager, Map,
 * Prewarm) so the outer `Venue\Setup::instantiate_classes()` can hand
 * off the whole map subsystem with a single line — same shape as
 * `Event\Setup`, `Rsvp\Setup`, `Venue\Setup`. Adding a new Map\* class
 * in the future lands as one line in `Map\Setup::instantiate_classes()`
 * rather than touching `Venue\Setup`.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 1.0.0
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Setup.
 *
 * Singleton hub for the venue map subsystem.
 *
 * @since 1.0.0
 */
class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * Instantiates the sibling Map\* singletons. The instances each wire
	 * their own hooks in their own `setup_hooks()` calls — this hub does
	 * nothing else.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->instantiate_classes();
	}

	/**
	 * Instantiate each Map\* sibling singleton.
	 *
	 * Manager registers core providers in its own constructor, so it
	 * must instantiate before Map and Prewarm — both of which call into
	 * the registry on later hooks. Sibling order after Manager is
	 * alphabetical; none of the others depend on each other.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Manager::get_instance();
		Map::get_instance();
		Prewarm::get_instance();
	}
}
