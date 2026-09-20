<?php
/**
 * Uninstall task that removes the plugin's options.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Calendar\Cache;
use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Network;

/**
 * Class Options.
 *
 * Removes the settings an administrator configured, the network settings,
 * the version marker the upgrade routine keeps, and the calendar cache
 * stamp. Every option the plugin writes under its own name belongs here,
 * except the options `Notices` owns: the shared dismissal record and
 * anything a notice declares through `get_options()`.
 *
 * The choices made on the Uninstall screen are part of those settings, so
 * they go with them. `Preferences` reads them once and remembers the
 * answer, so removing the settings here cannot change whether a task
 * ordered after this one runs.
 *
 * @since 0.36.0
 */
final class Options extends Base {

	/**
	 * Option holding the schema/upgrade version marker.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const VERSION_OPTION = 'gatherpress_version';

	/**
	 * The preference that gates this task.
	 *
	 * @since 0.36.0
	 *
	 * @return string The task key.
	 */
	protected function preference(): string {
		return Preferences::TASK_OPTIONS;
	}

	/**
	 * Remove the per-site options.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		delete_option( Settings::OPTION_NAME );
		delete_option( self::VERSION_OPTION );
		delete_option( Cache::LAST_MODIFIED_OPTION );
	}

	/**
	 * Remove the network options.
	 *
	 * Settings can be stored network-wide, and the inheritance config that
	 * says which of them a subsite takes from the network is network-wide
	 * by definition, so both are cleared once for the network.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_network(): void {
		delete_site_option( Settings::OPTION_NAME );
		delete_site_option( Network::OPTION_NAME );
	}
}
