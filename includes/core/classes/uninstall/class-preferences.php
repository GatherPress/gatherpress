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

/**
 * Class Preferences.
 *
 * Owns the per-task opt-in that `Base::applies()` reads. Every destructive
 * task is off until an administrator turns it on, so deleting the plugin
 * removes nothing a reinstall would want back unless that was asked for.
 *
 * Two decisions are load-bearing here.
 *
 * **Stored outside `gatherpress_settings`.** `Settings::import_settings()`
 * writes any key it finds in the field-type map built from the registered
 * settings pages, so a preference declared as an ordinary setting could be
 * switched on by importing a settings file from somewhere else. The most
 * destructive switch in the plugin should not be reachable that way, so it
 * lives in its own option that the importer never sees.
 *
 * **Network-scoped on multisite.** `Base::run()` calls `applies()` once,
 * before the `switch_to_blog()` fan-out, so a per-site value could not be
 * honored without changing the final `run()` contract. Reading a site
 * option keeps the check correct where it is made.
 *
 * @since 0.36.0
 */
final class Preferences {

	/**
	 * Option that stores the opt-in map.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const OPTION_NAME = 'gatherpress_uninstall';

	/**
	 * Task key: plugin options.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_OPTIONS = 'options';

	/**
	 * Task key: the custom events table.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_TABLES = 'tables';

	/**
	 * Task key: event and venue posts.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_POSTS = 'posts';

	/**
	 * Task key: plugin taxonomies and their terms.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_TERMS = 'terms';

	/**
	 * Task key: RSVP comments.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_COMMENTS = 'comments';

	/**
	 * Task key: scheduled events.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TASK_CRON = 'cron';

	/**
	 * Resolved preferences, or null before the first read.
	 *
	 * The uninstall run deletes the option that these values come from, so
	 * the map is read once and reused. Without this, a task ordered after
	 * `Options` would read a deleted option and silently decline to run.
	 *
	 * @since 0.36.0
	 * @var array<string, bool>|null
	 */
	protected static ?array $cache = null;

	/**
	 * Every task key this class gates.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The task keys.
	 */
	public static function task_keys(): array {
		return array(
			self::TASK_OPTIONS,
			self::TASK_TABLES,
			self::TASK_POSTS,
			self::TASK_TERMS,
			self::TASK_COMMENTS,
			self::TASK_CRON,
		);
	}

	/**
	 * The stored preferences, defaulting every task to off.
	 *
	 * Read once per request and cached, so the order tasks run in cannot
	 * change the answer.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, bool> Task key to opt-in state.
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = is_multisite()
			? get_site_option( self::OPTION_NAME, array() )
			: get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$resolved = array();

		foreach ( self::task_keys() as $key ) {
			$resolved[ $key ] = ! empty( $stored[ $key ] );
		}

		self::$cache = $resolved;

		return self::$cache;
	}

	/**
	 * Whether a task has been opted in to.
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
	 * Store the preferences.
	 *
	 * Only known keys are written, and every value is cast to a bool, so a
	 * caller cannot widen what uninstall removes by sending extra keys.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, mixed> $preferences Task key to opt-in state.
	 *
	 * @return void
	 */
	public static function save( array $preferences ): void {
		$clean = array();

		foreach ( self::task_keys() as $key ) {
			$clean[ $key ] = ! empty( $preferences[ $key ] );
		}

		if ( is_multisite() ) {
			update_site_option( self::OPTION_NAME, $clean );
		} else {
			update_option( self::OPTION_NAME, $clean );
		}

		self::flush_cache();
	}

	/**
	 * Drop the resolved preferences so the next read hits storage.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}
}
