<?php
/**
 * Plugin Name:       GatherPress
 * Plugin URI:        https://github.com/GatherPress/gatherpress
 * Description:       Powering Communities with WordPress.
 * Author:            The GatherPress Community
 * Author URI:        https://gatherpress.org/
 * Version:           0.34.1
 * Requires PHP:      7.4
 * Requires at least: 6.7
 * Text Domain:       gatherpress
 * License:           GNU General Public License v2.0 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This file serves as the main plugin file for GatherPress. It defines the plugin's basic information,
 * constants, and initializes the plugin.
 *
 * @package GatherPress
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Ensure no other versions of GatherPress are currently running.
$gatherpress_duplicate_check_result = require_once __DIR__ . '/includes/core/duplicate-check.php';
if ( $gatherpress_duplicate_check_result ) {
	return;
}

// Constants.
define( 'GATHERPRESS_CACHE_GROUP', 'gatherpress_cache' );
define( 'GATHERPRESS_CORE_FILE', __FILE__ );
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_DIR_NAME', dirname( plugin_basename( __FILE__ ) ) );
define( 'GATHERPRESS_REQUIRES_PHP', current( get_file_data( __FILE__, array( 'Requires PHP' ), 'plugin' ) ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );
define( 'GATHERPRESS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );

// Check if the minimum plugin requirements are not met and prevent further execution if necessary.
if ( ! require_once GATHERPRESS_CORE_PATH . '/includes/core/requirements-check.php' ) {
	return;
}

// Register class aliases so prior fully-qualified names continue to resolve to their current locations.
require_once GATHERPRESS_CORE_PATH . '/includes/core/register-class-aliases.php';

// Include and register the autoloader class for automatic loading of plugin classes.
require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/class-autoloader.php';
GatherPress\Core\Autoloader::register();

// Ensure the Singleton trait is loaded before initializing Setup class.
if ( ! trait_exists( 'GatherPress\Core\Traits\Singleton' ) ) {
	require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/traits/class-singleton.php';
}

// Initialize setups.
GatherPress\Core\Setup::get_instance();

// Deferred to `plugins_loaded` so a listener in any plugin is registered
// in time to catch it, whatever its load order relative to GatherPress.
add_action(
	'plugins_loaded',
	static function (): void {
		/**
		 * Fires once GatherPress has finished bootstrapping its core classes.
		 *
		 * Subsystems and third party plugins use this to run setup work that
		 * depends on other GatherPress classes already being instantiated.
		 *
		 * Because this fires only after the requirements check passes, it is
		 * also the signal a companion plugin should boot from. The GATHERPRESS_*
		 * constants are defined *before* that check, so they mean "GatherPress
		 * began loading", not "GatherPress loaded successfully" — booting on
		 * those instead is what caused the fatal in #1982.
		 *
		 * Fires on `plugins_loaded`, so any plugin can catch it.
		 *
		 * @since 0.34.1
		 */
		do_action( 'gatherpress_loaded' );
	}
);
