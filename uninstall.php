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
 * cleanup concern, and a Setup registry that runs them. Today the only
 * task is the transient wipe; the #681 follow-up registers its
 * settings-gated tasks (options, tables, posts, terms, comments, cron)
 * on the same shape.
 *
 * @since 0.36.0
 *
 * @package GatherPress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// gatherpress.php defines this on a normal load; nothing has during uninstall.
defined( 'GATHERPRESS_CORE_PATH' ) || define( 'GATHERPRESS_CORE_PATH', __DIR__ );

require_once __DIR__ . '/includes/core/register-class-aliases.php';
require_once __DIR__ . '/includes/core/classes/class-autoloader.php';

GatherPress\Core\Autoloader::register();

GatherPress\Core\Uninstall\Setup::get_instance()->run();
