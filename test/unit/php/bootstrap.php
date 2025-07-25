<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package GatherPress
 * @subpackage Tests
 * @since 1.0.0
 */

// phpcs:disable Squiz.Commenting.FileComment.Missing

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

$gatherpress_bootstrap_instance->start();
