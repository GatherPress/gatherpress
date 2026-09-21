<?php
/**
 * Opt-in preferences that gate the destructive uninstall tasks.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Network;

/**
 * Class Preferences.
 *
 * Owns the per-task opt-in that `Base::applies()` reads. Every destructive
 * task is off until an administrator turns it on, so deleting the plugin
 * removes nothing a reinstall would want back unless that was asked for.
 *
 * The values are ordinary GatherPress settings, declared by
 * `Settings\Uninstall` and stored in `gatherpress_settings` with everything
 * else. They are declared `'exportable' => false`, which keeps them out of
 * a settings export and makes an import refuse them: every other setting
 * shows its effect as soon as it is wrong, while these do nothing until the
 * plugin is deleted, and what they remove is gone.
 *
 * **Resolved per site, the way the network told it to be.** Read here
 * rather than through `Settings::get()` because that exempts the network
 * admin screen from inheritance so super admins can edit network values,
 * and an uninstall started from the network Plugins screen runs in exactly
 * that context. The exemption is about editing, not about what a site's
 * data should do, so the inheritance question is asked directly.
 *
 * @since 0.36.0
 */
final class Preferences {

	/**
	 * Prefix the settings keys share.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const OPTION_PREFIX = 'uninstall_';

	/**
	 * Task key: event posts and the event date table.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_EVENTS = 'events';

	/**
	 * Task key: RSVP records and the taxonomies that classify them.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_RSVPS = 'rsvps';

	/**
	 * Task key: the topic taxonomy and its terms.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_TOPICS = 'topics';

	/**
	 * Task key: venue posts and the venue terms that shadow them.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_VENUES = 'venues';

	/**
	 * Task key: the files the plugin generated under uploads.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_FILES = 'files';

	/**
	 * Task key: scheduled events.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_CRON = 'cron';

	/**
	 * Task key: per-user preferences.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_USERS = 'users';

	/**
	 * Task key: plugin options.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_OPTIONS = 'options';

	/**
	 * Resolved preferences, keyed by the site they were resolved for.
	 *
	 * The uninstall run deletes the settings these values come from, so each
	 * answer is read once and reused. Without this, a task ordered after
	 * `Options` would read a deleted option and silently decline to run.
	 * Zero is the network's own answer, which no site can hold.
	 *
	 * @since 0.36.0
	 * @var array<int, array<string, bool>>
	 */
	protected static array $cache = array();

	/**
	 * Every task key this class gates.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The task keys.
	 */
	public static function task_keys(): array {
		return array(
			self::TASK_EVENTS,
			self::TASK_RSVPS,
			self::TASK_TOPICS,
			self::TASK_VENUES,
			self::TASK_FILES,
			self::TASK_CRON,
			self::TASK_USERS,
			self::TASK_OPTIONS,
		);
	}

	/**
	 * The settings key a task is stored under.
	 *
	 * @since 0.36.0
	 *
	 * @param string $task The task key.
	 *
	 * @return string The settings option key.
	 */
	public static function option_key( string $task ): string {
		return self::OPTION_PREFIX . $task;
	}

	/**
	 * The preferences that govern the current site.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, bool> Task key to opt-in state.
	 */
	public static function all(): array {
		return self::resolve( get_current_blog_id() );
	}

	/**
	 * The preferences the network set for itself.
	 *
	 * What governs the things no single site owns: the network settings and
	 * the shared user table. A network that leaves these off keeps them,
	 * whatever its sites decided for their own data.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, bool> Task key to opt-in state.
	 */
	public static function all_for_network(): array {
		return self::resolve( 0 );
	}

	/**
	 * Whether a task has been opted in to for the current site.
	 *
	 * An unknown key is always false, so a task that has not been added to
	 * `task_keys()` cannot run by accident.
	 *
	 * @since 0.36.0
	 *
	 * @param string $task The task key.
	 *
	 * @return bool True when the task should run.
	 */
	public static function is_enabled( string $task ): bool {
		$all = self::all();

		return ! empty( $all[ $task ] );
	}

	/**
	 * Whether a task has been opted in to at the network level.
	 *
	 * @since 0.36.0
	 *
	 * @param string $task The task key.
	 *
	 * @return bool True when the task should run.
	 */
	public static function is_enabled_for_network( string $task ): bool {
		$all = self::all_for_network();

		return ! empty( $all[ $task ] );
	}

	/**
	 * Read and remember the preferences for one site, or for the network.
	 *
	 * @since 0.36.0
	 *
	 * @param int $blog_id The site to resolve for, or 0 for the network itself.
	 *
	 * @return array<string, bool> Task key to opt-in state.
	 */
	protected static function resolve( int $blog_id ): array {
		if ( isset( self::$cache[ $blog_id ] ) ) {
			return self::$cache[ $blog_id ];
		}

		$site = self::stored_options( false );

		// Without a network there is only one set of values, and only one
		// answer to give for both the site and the network question.
		$network  = is_multisite() ? self::stored_options( true ) : $site;
		$config   = is_multisite() ? Network::get_config() : array();
		$resolved = array();

		foreach ( self::task_keys() as $task ) {
			$key       = self::option_key( $task );
			$inherited = (array) ( $config['inherited'] ?? array() );
			$inherits  = 0 === $blog_id
				|| ( ! empty( $config['enabled'] ) && in_array( $key, $inherited, true ) );
			$values    = $inherits ? $network : $site;

			$resolved[ $task ] = ! empty( $values[ $key ] );
		}

		self::$cache[ $blog_id ] = $resolved;

		return $resolved;
	}

	/**
	 * The stored GatherPress settings, from the network or from this site.
	 *
	 * @since 0.36.0
	 *
	 * @param bool $network Whether to read the network-wide values. Only
	 *                      asked on multisite, where the two differ.
	 *
	 * @return array<string, mixed> The stored settings.
	 */
	protected static function stored_options( bool $network ): array {
		$stored = $network
			? get_site_option( Settings::OPTION_NAME, array() )
			: get_option( Settings::OPTION_NAME, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Drop the resolved preferences so the next read hits storage.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}
}
