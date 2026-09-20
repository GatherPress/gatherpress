<?php
/**
 * GatherPress uninstall bootstrap.
 *
 * WordPress includes this file when the user chooses "Delete" on the
 * plugin's row in wp-admin. The plugin itself is never loaded, so this
 * bootstrap re-creates the two things the task classes depend on: the
 * core path constant the autoloader reads, and the autoloader itself.
 * The class-alias shim loads first, matching gatherpress.php, so task
 * classes can import core classes by their short aliased names.
 *
 * The actual work lives in the `GatherPress\Core\Uninstall` namespace:
 * an abstract Base that owns the multisite fan-out, one small class per
 * cleanup concern, and a Setup registry that runs them. The transient wipe
 * and the admin notice bookkeeping always run. The destructive tasks
 * (events, venues, RSVPs, topics, files, cron, users, options) run only
 * where an administrator opted in on the Uninstall screen.
 *
 * @since 0.36.0
 *
 * @package GatherPress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/*
 * A sweep on a large site can outlive PHP's own limits. Raise them where the
 * host allows it, and keep working if the browser goes away, so the delete
 * does not stop part-way and leave half the data behind. Neither call is
 * guaranteed: a host can disable `set_time_limit()`, and a hard web server
 * timeout is outside PHP's control. The Uninstall screen recommends WP-CLI
 * for sites large enough for that to matter.
 */
if ( function_exists( 'set_time_limit' ) ) {
	set_time_limit( 0 );
}

ignore_user_abort( true );

// gatherpress.php defines this on a normal load; nothing has during uninstall.
defined( 'GATHERPRESS_CORE_PATH' ) || define( 'GATHERPRESS_CORE_PATH', __DIR__ );

require_once __DIR__ . '/includes/core/register-class-aliases.php';
require_once __DIR__ . '/includes/core/classes/class-autoloader.php';

GatherPress\Core\Autoloader::register();

GatherPress\Core\Uninstall\Setup::get_instance()->run();
