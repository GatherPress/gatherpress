<?php
/**
 * Plugin Name:       GatherPress
 * Plugin URI:        https://github.com/GatherPress/gatherpress
 * Description:       Powering Communities with WordPress.
 * Author:            The GatherPress Community
 * Author URI:        https://gatherpress.org/
 * Version:           0.33.0-alpha.1
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

// Debug: Output that the main plugin file is being loaded.
echo 'GatherPress DEBUG: Main plugin file loaded - gatherpress.php' . PHP_EOL;

// Ensure no other versions of GatherPress are currently running.
echo 'GatherPress DEBUG: Checking for duplicate installations' . PHP_EOL;
$duplicate_check_result = require_once __DIR__ . '/includes/core/duplicate-check.php';
echo 'GatherPress DEBUG: Duplicate check result: ' . ( $duplicate_check_result ? 'true (duplicate found - exiting)' : 'false (no duplicate)' ) . PHP_EOL;
if ( $duplicate_check_result ) {
	echo 'GatherPress DEBUG: Exiting due to duplicate installation' . PHP_EOL;
	return;
}

// Constants.
echo 'GatherPress DEBUG: Defining constants' . PHP_EOL;
echo 'GatherPress DEBUG: GATHERPRESS_VERSION already defined: ' . ( defined( 'GATHERPRESS_VERSION' ) ? 'YES' : 'NO' ) . PHP_EOL;
define( 'GATHERPRESS_CACHE_GROUP', 'gatherpress_cache' );
define( 'GATHERPRESS_CORE_FILE', __FILE__ );
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_DIR_NAME', dirname( plugin_basename( __FILE__ ) ) );
define( 'GATHERPRESS_REQUIRES_PHP', current( get_file_data( __FILE__, array( 'Requires PHP' ), 'plugin' ) ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );
define( 'GATHERPRESS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
echo 'GatherPress DEBUG: GATHERPRESS_VERSION now defined as: ' . GATHERPRESS_VERSION . PHP_EOL;

// Check if the minimum plugin requirements are not met and prevent further execution if necessary.
if ( ! require_once GATHERPRESS_CORE_PATH . '/includes/core/requirements-check.php' ) {
	return;
}

// Include and register the autoloader class for automatic loading of plugin classes.
require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/class-autoloader.php';
GatherPress\Core\Autoloader::register();

// Ensure the Singleton trait is loaded before initializing Setup class.
if ( ! trait_exists( 'GatherPress\Core\Traits\Singleton' ) ) {
	require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/traits/class-singleton.php';
}

// Debug: Output before Setup initialization.
echo 'GatherPress DEBUG: About to initialize Setup class' . PHP_EOL;

// Initialize setups.
GatherPress\Core\Setup::get_instance();

// Debug: Output after Setup initialization.
echo 'GatherPress DEBUG: Setup class initialized successfully' . PHP_EOL;
