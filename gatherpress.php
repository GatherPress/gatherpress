<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         Powering Communities with WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.14.3
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



add_action('admin_init', 'gatherpress_settings_page');
add_action('admin_menu', 'gatherpress_menu_item');
/**
 * Create the Admin Menu.
 */
function gatherpress_menu_item() {
	add_submenu_page(
		'edit.php?post_type=gp_event',
		'GatherPress Admin Page',
		'GatherPress Checkbox',
		'manage_options',
		'gatherpress-admin-page',
		'gatherpress_admin_page'
	);
}

/**
 * 
 */
function gatherpress_admin_page() {
  ?>
      <div class='wrap'>
         <h2>GatherPress</h2>
         <form method='post' action='options.php'>
            <?php
               settings_fields('gp_unique_settings_section');
 
               do_settings_sections('gatherpress_unique');
                 
               submit_button();
            ?>
         </form>
      </div>
   <?php
}

/**
 * 
 */
function gatherpress_settings_page() {
    add_settings_section(
		'gp_unique_settings_section',
		'GatherPress Section',
		null,
		'gatherpress_unique'
	);
    add_settings_field(
		'event_or_post_date',
		'GatherPress Checkbox',
		'gatherpress_checkbox_display',
		'gatherpress_unique',
		'gp_unique_settings_section'
	);  
    register_setting(
		'gp_unique_settings_section',
		'event_or_post_date'
	);
}

/**
 * 
 */
function gatherpress_checkbox_display() {
   ?>
        <!-- Here we are comparing stored value with 1. Stored value is 1 if user checks the checkbox otherwise empty string. -->
        <input type='checkbox' name='event_or_post_date' value='1' <?php checked(1, get_option('event_or_post_date'), true); ?> />
   <?php
}

add_filter( 'gatherpress_settings_sub_pages', 'gatherpress_admin_subpage' );
// add_filter( 'GatherPress\Core\gatherpress_settings_sub_pages', 'gatherpress_admin_subpage' );
/**
 * 
 */
function gatherpress_admin_subpage( $sub_pages ) {
	$sub_pages['sexond'] = array(
			'name'        => __( 'Sexond', 'gatherpress' ),
			'description' => __( 'Sexond Settings for GatherPress.', 'gatherpress' ),
			'priority'    => PHP_INT_MIN,
			'sections'    => array(
				'sexond_pages' => array(
					'name'        => __( 'Sexond Event Text', 'gatherpress' ),
					'description' => __( 'GatherPress allows you to set event archives to pages you have created.', 'gatherpress' ),
					'options'     => array(
						'sexond_upcoming_events' => array(
							'labels' => array(
								'name' => __( 'Sexond Events', 'gatherpress' ),
							),
							'field'  => array(
								'type'    => 'text',
								'options' => array(
									'type'  => 'radio',
									'label' => __( 'Add Sexond Taxt', 'gatherpress' ),
									'limit' => 1,
								),
							),
						),
						'sexond_checkbox'     => array(
							'labels' => array(
								'name' => __( 'Sexond Checkbox', 'gatherpress' ),
							),
							'field'  => array(
								'type'    => 'checkbox',
								'options' => array(
									'type'  => 'page',
									'label' => __( 'Select Sexond Page', 'gatherpress' ),
									'limit' => 1,
								),
							),
						),
					),
				),
			),
	);
	return $sub_pages;
}
