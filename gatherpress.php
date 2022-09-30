<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         GatherPress adds event management and more to WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.9
 * Minimum PHP Version: 7.3
 * Text Domain:         gatherpress
 * License:             GPLv2 or later (license.txt)
 *
 * @package GatherPress
 */

// Constants.
define( 'GATHERPRESS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_MINIMUM_PHP_VERSION', current( get_file_data( __FILE__, array( 'Minimum PHP Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

// Bail if things do not meet minimum plugin requirements.
if ( ! require_once GATHERPRESS_CORE_PATH . '/core/preflight.php' ) {
	return;
}

require_once GATHERPRESS_CORE_PATH . '/core/classes/class-autoloader.php';

GatherPress\Core\Autoloader::register();
GatherPress\Core\Setup::get_instance();
GatherPress\BuddyPress\Setup::get_instance();

add_action( 'init', 'create_new_gatherpress_blocks' );
function create_new_gatherpress_blocks() {
	register_block_type( __DIR__ . '/build/blocks/event-header' );
	register_block_type( __DIR__ . '/build/blocks/map-embed' );
}