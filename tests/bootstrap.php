<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Gatherpress
 */

require_once __DIR__ . '/vendor/autoload.php';

$instance = PMC\Unit_Test\Bootstrap::get_instance();

tests_add_filter( 'plugins_loaded', function() {
	// Manually load our plugin without having to setup the development folder in the correct plugin folder
	require_once __DIR__ . '/../gatherpress.php';
} );

$instance->start();
