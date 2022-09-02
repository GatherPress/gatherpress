<?php
/**
 * Load all files.
 *
 * @package    GatherPress
 * @subpackage Core
 */

$gatherpress_core_files = array(
	'/core/classes/traits/trait-singleton.php',
	'/core/classes/class-assets.php',
	'/core/classes/class-attendee.php',
	'/core/classes/class-block.php',
	'/core/classes/class-cli.php',
	'/core/classes/class-event.php',
	'/core/classes/class-query.php',
	'/core/classes/class-rest-api.php',
	'/core/classes/class-settings.php',
	'/core/classes/class-setup.php',
	'/core/classes/class-utility.php',
	'/core/classes/class-venue.php',
);

foreach ( $gatherpress_core_files as $gatherpress_core_file ) {
	include_once GATHERPRESS_CORE_PATH . $gatherpress_core_file;
}

GatherPress\Core\Setup::get_instance();
