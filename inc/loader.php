<?php
/**
 * Load all files.
 *
 * @package GatherPress
 */

$gatherpress_files = array(
	'/inc/classes/traits/trait-singleton.php',
	'/inc/classes/class-assets.php',
	'/inc/classes/class-attendee.php',
	'/inc/classes/class-block.php',
	'/inc/classes/class-buddypress.php',
	'/inc/classes/class-email.php',
	'/inc/classes/class-event.php',
	'/inc/classes/class-query.php',
	'/inc/classes/class-rest-api.php',
	'/inc/classes/class-role.php',
	'/inc/classes/class-setup.php',
	'/inc/classes/class-utility.php',
);

foreach ( $gatherpress_files as $gatherpress_file ) {
	require_once GATHERPRESS_CORE_PATH . $gatherpress_file;
}
