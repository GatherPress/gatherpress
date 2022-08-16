<?php
/**
 * Plugin Name: GatherPress
 * Plugin URI:  https://gatherpress.org/
 * Description: GatherPress adds event management and more to WordPress.
 * Author:      The GatherPress Community
 * Author URI:  https://gatherpess.org/
 * Version:     0.3
 * Text Domain: gatherpress
 * License:     GPLv2 or later (license.txt)
 *
 * @package GatherPress
 */

// Constants.
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_VERSION', current( get_file_data( __FILE__ , [ 'Version' ], 'plugin' ) ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

require_once GATHERPRESS_CORE_PATH . '/core/loader.php';
require_once GATHERPRESS_CORE_PATH . '/buddypress/loader.php';
