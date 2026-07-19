<?php
/**
 * Plugin Name:       GatherPress
 * Plugin URI:        https://github.com/GatherPress/gatherpress
 * Description:       Powering Communities with WordPress.
 * Author:            The GatherPress Community
 * Author URI:        https://gatherpress.org/
 * Version:           0.34.0
 * Requires PHP:      8.1
 * Requires at least: 7.0
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

// Announce that GatherPress is ready on `plugins_loaded`, after every
// active plugin's file has been included. Firing here rather than inline
// means a listener added at the top level of any plugin — regardless of
// its load order relative to GatherPress — is registered in time to catch
// the action. GatherPress's classes are already instantiated above, so
// they are fully available by the time this fires.
add_action(
	'plugins_loaded',
	static function (): void {
		/**
		 * Fires once GatherPress has finished bootstrapping its core classes.
		 *
		 * Subsystems and third party plugins use this to run setup work that
		 * depends on other GatherPress classes already being instantiated —
		 * for example, the RSVP provider registry consumes it to fire its own
		 * `gatherpress_register_rsvp_types` action.
		 *
		 * Fires on `plugins_loaded`, so any plugin can catch it. See the
		 * plugin lifecycle guide (`docs/developer/plugin-lifecycle.md`).
		 *
		 * @since 0.35.0
		 */
		do_action( 'gatherpress_loaded' );
	}
);
