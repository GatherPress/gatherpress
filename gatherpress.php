<?php
/**
 * Plugin Name: GatherPress
 * Plugin URI:  https://gatherpress.org/
 * Description: GatherPress adds event management to WordPress.
 * Author:      The GatherPress Community
 * Author URI:  https://gatherpess.org/
 * Version:     0.1.0
 * Text Domain: gatherpress
 * License:     GPLv2 or later (license.txt)
 *
 * @package GatherPress
 */

// Constants.
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_THEME_VERSION', '0.1.0' );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

require_once GATHERPRESS_CORE_PATH . '/core/loader.php';

// Let's go!
GatherPress\Core\Setup::get_instance();
