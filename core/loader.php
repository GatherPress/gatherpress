<?php
/**
 * Load all files.
 *
 * @package GatherPress
 */

$gatherpress_files = array(
	'/core/classes/traits/trait-singleton.php',
	'/core/classes/class-assets.php',
	'/core/classes/class-attendee.php',
	'/core/classes/class-block.php',
	'/core/classes/class-buddypress.php',
	'/core/classes/class-email.php',
	'/core/classes/class-event.php',
	'/core/classes/class-query.php',
	'/core/classes/class-rest-api.php',
	'/core/classes/class-role.php',
	'/core/classes/class-setup.php',
	'/core/classes/class-utility.php',
);

foreach ( $gatherpress_files as $gatherpress_file ) {
	require_once GATHERPRESS_CORE_PATH . $gatherpress_file;
}
