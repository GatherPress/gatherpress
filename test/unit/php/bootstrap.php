<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GatherPress
 * @subpackage Tests
 * @since 0.27.0
 */

// phpcs:disable Squiz.Commenting.FileComment.Missing

// Enable WP-CLI stub so CLI code paths are covered in tests.
if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
}

$gatherpress_bootstrap_instance = PMC\Unit_Test\Bootstrap::get_instance();

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

// Shared fixtures used by more than one test class. PHPUnit collects test
// files by their `class-test-` prefix and the autoloader's two layouts do not
// reach into the tests directory, so a helper that is neither gets required.
require_once __DIR__ . '/includes/tests/core/classes/uninstall/class-preferences-fixture.php';

$gatherpress_bootstrap_instance->start();
