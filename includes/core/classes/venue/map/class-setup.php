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
 * @since 0.34.0
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Map\Provider\Google;

/**
 * Class Setup.
 *
 * Singleton hub for the venue map subsystem.
 *
 * @since 0.34.0
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
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->setup_hooks();
		$this->instantiate_classes();
	}

	/**
	 * Wire companion-style provider registration before siblings boot.
	 *
	 * Hooks `gatherpress_register_static_map_providers` so Google registers
	 * alongside third-party providers rather than inside
	 * `Manager::register_core_providers()`.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action(
			'gatherpress_register_static_map_providers',
			static function ( Manager $registry ): void {
				$registry->register( new Google() );
			}
		);
	}

	/**
	 * Instantiate each Map\* sibling singleton.
	 *
	 * Manager registers core providers in its own constructor, so it
	 * must instantiate before Map and Prewarm — both of which call into
	 * the registry on later hooks. Sibling order after Manager is
	 * alphabetical; none of the others depend on each other.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Manager::get_instance();
		Map::get_instance();
		Prewarm::get_instance();
	}
}
