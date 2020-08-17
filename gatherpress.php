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
define( 'GP_CORE_PATH', __DIR__ );
define( 'GP_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GP_THEME_VERSION', '0.1.0' );
define( 'GP_REST_NAMESPACE', 'gatherpress/v1' );

// Required files.
require_once GP_CORE_PATH . '/inc/helpers/autoloader.php';
require_once GP_CORE_PATH . '/inc/template-tags.php';

// Kick things off!
\GatherPress\Inc\Setup::get_instance();
