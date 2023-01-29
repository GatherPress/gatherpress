<?php
/**
 * Checks requirements before loading plugin.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

$gatherpress_preflight_return = true;

/* Activate Hook Plugin */
register_activation_hook(__FILE__, 'gatherpress_plugin_updates');

/* call when plugin is activated */
function gatherpress_plugin_updates() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
        $fa_user_logins_table = $wpdb->prefix . "fa_user_logins";

    $sql = "CREATE TABLE $fa_user_logins_table (
            id int(11) NOT NULL AUTO_INCREMENT,
    user_id int(11) ,
    `time_login` datetime NOT NULL,
    `time_logout` datetime NOT NULL,
    `ip_address` varchar(20) NOT NULL,
    `browser` varchar(100) NOT NULL,
    `operating_system` varchar(100) NOT NULL,
    `country_name` varchar(100) NOT NULL,
    `country_code` varchar(20) NOT NULL   ,                             
    PRIMARY KEY (`id`)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
        update_option( 'fa_userloginhostory_version', '1.0' );
        gatherpress_plugin_updates_updating();
}


//call when plugin is updated. 
//Actually it gets called every time 
//but the sql query execute only when there is plugin version difference
function gatherpress_plugin_updates_updating() {
    global $wpdb;
        $oldVersion = get_option( 'fa_userloginhostory_version', '1.0' );
        $newVersion = '1.2';

        if ( !(version_compare( $oldVersion, $newVersion ) < 0) ) {
            return FALSE;
        }

    $charset_collate = $wpdb->get_charset_collate();
        $fa_user_logins_table = $wpdb->prefix . "fa_user_logins";

    $sql = "CREATE TABLE $fa_user_logins_table (
            id int(11) NOT NULL AUTO_INCREMENT,
    user_id int(11) ,
    `time_login` datetime NOT NULL,
    `time_logout` datetime NOT NULL,
    `time_last_seen` datetime NOT NULL,
    `ip_address` varchar(20) NOT NULL,
    `browser` varchar(100) NOT NULL,
    `operating_system` varchar(100) NOT NULL,
    `country_name` varchar(100) NOT NULL,
    `country_code` varchar(20) NOT NULL,
    PRIMARY KEY (`id`)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    update_option( 'fa_userloginhostory_version', $newVersion );
}

add_action('init', 'gatherpress_plugin_updates_updating');