<?php
/**
 * Plugin Name:       GatherPress
 * Plugin URI:        https://github.com/GatherPress/gatherpress
 * Description:       Powering Communities with WordPress.
 * Author:            The GatherPress Community
 * Author URI:        https://gatherpress.org/
 * Version:           0.34.0-alpha.2
 * Requires PHP:      8.1
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

/**
 * Fires once GatherPress has finished bootstrapping its core classes.
 *
 * Subsystems or third party plugins can use this to run setup work that
 * depends on other GatherPress classes already being instantiated.
 *
 * @since 1.0.0
 */
do_action( 'gatherpress_loaded' );
