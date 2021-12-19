<?php
/**
 * Load all files.
 *
 * @package GatherPress
 * @subpackage BuddyPress
 */

if ( ! function_exists( 'buddypress' ) ) {
	return;
}

$gatherpress_buddypress_files = array(
	'/buddypress/classes/class-setup.php',
	'/buddypress/classes/class-email.php',
);

foreach ( $gatherpress_buddypress_files as $file ) {
	require_once GATHERPRESS_CORE_PATH . $file;
}

GatherPress\BuddyPress\Setup::get_instance();
