<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         Powering Communities with WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpress.org/
 * Version:             0.26.0
 * Minimum PHP Version: 7.4
 * Text Domain:         gatherpress
 * Domain Path:         /languages
 * License:             GPLv2 or later (license.txt)
 *
 * This file serves as the main plugin file for GatherPress. It defines the plugin's basic information,
 * constants, and initializes the plugin.
 *
 * @package GatherPress
 */

// Constants.
define( 'GATHERPRESS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_MINIMUM_PHP_VERSION', current( get_file_data( __FILE__, array( 'Minimum PHP Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_FILE', __FILE__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

// Check if the minimum plugin requirements are not met and prevent further execution if necessary.
if ( ! require_once GATHERPRESS_CORE_PATH . '/includes/core/requirements-check.php' ) {
	return;
}

// Load the plugin textdomain 'gatherpress' and find language files in gatherpress/languages when missing in wp-content/languages/plugins/
function gatherpress_load_textdomain( $mofile, $domain ) {
	if ( 'gatherpress' === $domain && false !== strpos( $mofile, WP_LANG_DIR . '/plugins/' ) ) {
		$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
		$mofile = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/languages/' . $domain . '-' . $locale . '.mo';
	}
	return $mofile;
}

load_plugin_textdomain( 'gatherpress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

// Include and register the autoloader class for automatic loading of plugin classes.
require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/class-autoloader.php';
GatherPress\Core\Autoloader::register();

// Initialize setups.
GatherPress\Core\Setup::get_instance();
