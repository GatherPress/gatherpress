<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GatherPress
 * @subpackage Tests
 * @since 1.0.0
 */

// phpcs:disable Squiz.Commenting.FileComment.Missing

// Enable WP-CLI stub so CLI code paths are covered in tests.
if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}

$gatherpress_bootstrap_instance = PMC\Unit_Test\Bootstrap::get_instance();

// Load the vendored Action Scheduler library on `plugins_loaded` at a
// negative priority so its own priority-0 / priority-1 self-registration
// hooks get added before the action steps through those priorities. In
// production `wp-settings.php` loads the plugin file before firing
// `plugins_loaded`, and the `Action_Scheduler` class's constructor
// (invoked from `Setup::instantiate_classes()`) handles the require.
// The WP test framework defers plugin loading to `plugins_loaded`
// priority 10, past AS's registration window — so we load the library
// here at priority -1 to replicate the production timing.
tests_add_filter(
	'plugins_loaded',
	static function () {
		require_once __DIR__ . '/../../../includes/libraries/action-scheduler/action-scheduler.php';
	},
	-1
);

tests_add_filter(
	'plugins_loaded',
	static function () {
		// Manually load our plugin without having to setup the development folder in the correct plugin folder.
		require_once __DIR__ . '/../../../gatherpress.php';
	}
);

tests_add_filter(
	'gatherpress_autoloader',
	static function ( array $namespaces ): array {
		$namespaces['GatherPress\Tests'] = __DIR__;

		return $namespaces;
	}
);

$gatherpress_bootstrap_instance->start();
