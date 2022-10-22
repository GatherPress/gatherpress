<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         GatherPress adds event management and more to WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.10
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
if ( ! require_once GATHERPRESS_CORE_PATH . '/core/preflight.php' ) {
	return;
}

require_once GATHERPRESS_CORE_PATH . '/core/classes/class-autoloader.php';

GatherPress\Core\Autoloader::register();
GatherPress\Core\Setup::get_instance();
GatherPress\BuddyPress\Setup::get_instance();

add_action( 'init', 'gatherpress_different_blocks_init' );
/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function gatherpress_different_blocks_init() {
	register_block_type(
		__DIR__ . '/assets/build/blocks/add-to-calendar'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/initial-time'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/end-time'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/event-date'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/time-template'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/venue'
	);
}
