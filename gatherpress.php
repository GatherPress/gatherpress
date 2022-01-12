<?php
/**
 * Plugin Name: GatherPress
 * Plugin URI:  https://gatherpress.org/
 * Description: GatherPress adds event management and more to WordPress.
 * Author:      The GatherPress Community
 * Author URI:  https://gatherpess.org/
 * Version:     0.1.4
 * Text Domain: gatherpress
 * License:     GPLv2 or later (license.txt)
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package GatherPress
 */

defined( 'ABSPATH' ) || die( 'File cannot be accessed directly' );

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// Define Plugin Constants.
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_VERSION', get_plugin_data( __FILE__ )['Version'] );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

require_once GATHERPRESS_CORE_PATH . '/core/loader.php';
require_once GATHERPRESS_CORE_PATH . '/buddypress/loader.php';

register_activation_hook( __FILE__, 'activate_gatherpress_plugin' );
/**
 * Activate GatherPress plugin.
 *
 * @return void
 */
function activate_gatherpress_plugin() {
	GatherPress\Core\Setup::register_post_types();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'deactivate_gatherpress_plugin' );
/**
 * Activate GatherPress plugin.
 *
 * @return void
 */
function deactivate_gatherpress_plugin() {
	flush_rewrite_rules();
}
