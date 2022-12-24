<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         Powering Communities with WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.13.0
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
define( 'GATHERPRESS_CORE_FILE', __FILE__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

// Bail if things do not meet minimum plugin requirements.
if ( ! require_once GATHERPRESS_CORE_PATH . '/includes/core/preflight.php' ) {
	return;
}

require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/class-autoloader.php';

GatherPress\Core\Autoloader::register();
GatherPress\Core\Setup::get_instance();
GatherPress\BuddyPress\Setup::get_instance();

add_action( 'init', 'gatherpress_gp_blocks_init' );

/**
 * Initialize GatherPress blocks.
 */
function gatherpress_gp_blocks_init() {
	register_block_type(
		__DIR__ . '/build/blocks/add-to-calendar'
	);
	register_block_type(
		__DIR__ . '/build/blocks/attendance-list'
	);
	register_block_type(
		__DIR__ . '/build/blocks/attendance-selector'
	);
//	register_block_type(
//		__DIR__ . '/build/blocks/event-date'
//	);
//	register_block_type(
//		__DIR__ . '/build/blocks/events-list'
//	);
//	register_block_type(
//		__DIR__ . '/build/blocks/venue'
//	);
//	register_block_type(
//		__DIR__ . '/build/blocks/venue-information'
//	);
}
