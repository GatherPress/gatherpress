<?php
/**
 * PHPUnit bootstrap file
 *
 * Bootstrap file for plugin automated tests.
 *
 * @package GatherPress
 * @subpackage Tests
 * @since 1.0.0
 */

require_once __DIR__ . '/../vendor/autoload.php';

$gatherpress_bootstrap_instance = PMC\Unit_Test\Bootstrap::get_instance();

tests_add_filter(
	'plugins_loaded',
	function() {
		// Manually load our plugin without having to setup the development folder in the correct plugin folder.
		require_once __DIR__ . '/../gatherpress.php';
	}
);

$gatherpress_bootstrap_instance->start();
