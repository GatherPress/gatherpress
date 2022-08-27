<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         GatherPress adds event management and more to WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.5
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

/**
 * Check version of PHP before loading plugin.
 */
if ( version_compare( PHP_VERSION_ID, GATHERPRESS_MINIMUM_PHP_VERSION, '<' ) ) {
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo sprintf(
						/* translators: %1$s: minimum PHP version, %2$s current PHP version. */
						esc_html__(
							'GatherPress requires PHP Version %1$s or greater. You are currently running PHP %2$s. Please upgrade.',
							'gatherpress'
						),
						esc_html( GATHERPRESS_MINIMUM_PHP_VERSION ),
						esc_html( PHP_VERSION_ID )
					);
					?>
				</p>
			</div>
			<?php
		}
	);

	return;
}

require_once GATHERPRESS_CORE_PATH . '/core/loader.php';
require_once GATHERPRESS_CORE_PATH . '/buddypress/loader.php';


// Move the "example_cpt" Custom-Post-Type to be a submenu of the "example_parent_page_id" admin page.
add_action('admin_menu', 'fix_admin_menu_submenu', 11);
function fix_admin_menu_submenu() {

    // Add "Example CPT" Custom-Post-Type as submenu of the "Example Parent Page" page
    add_submenu_page('gp_event', 'Venue', 'Venue', 'edit_pages' , 'edit.php?post_type=gp_venue');
}

// Set the "example_parent_page_id" submenu as active/current when creating/editing a "example_cpt" post
add_filter('parent_file', 'fix_admin_parent_file');
function fix_admin_parent_file($parent_file){
    global $submenu_file, $current_screen;

    // Set correct active/current menu and submenu in the WordPress Admin menu for the "example_cpt" Add-New/Edit/List
    if($current_screen->post_type == 'gp_venue') {
        $submenu_file = 'edit.php?post_type=gp_venue';
        $parent_file = 'gp_event';
    }
    return $parent_file;
}
