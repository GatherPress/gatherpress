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
 * except the dismissed-notice bookkeeping, which `Notices` owns.
 *
 * The opt-in map itself goes last, in the network pass. `Preferences` reads
 * its option once and caches it, so removing it here cannot change whether
 * a task ordered after this one runs.
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
	 * Whether the administrator opted in to removing options.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_OPTIONS );
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
	 * Settings can be stored network-wide, and the opt-in map is always a
	 * site option on multisite, so both are cleared once for the network.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_network(): void {
		delete_site_option( Settings::OPTION_NAME );
		delete_site_option( Network::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		delete_option( Preferences::OPTION_NAME );
	}
}
