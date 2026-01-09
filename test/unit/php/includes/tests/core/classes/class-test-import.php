<?php
/**
 * Class handles unit tests for GatherPress\Core\Import.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Import;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Import.
 *
 * @coversDefaultClass \GatherPress\Core\Import
 * @group migrate
 */
class Test_Import extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Import::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'wp_import_post_data_raw',
				'priority' => 10,
				'callback' => array( $instance, 'prepare' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_import',
				'priority' => 10,
				'callback' => array( $instance, 'extend' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for setup_hooks with WXR_Importer class present.
	 *
	 * Tests that when the WXR_Importer class exists, the correct hook name is used.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks_with_wxr_importer(): void {
		// Mock the WXR_Importer class.
		if ( ! class_exists( 'WXR_Importer' ) ) {
			// Create a mock class using eval for testing purposes.
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Required for mocking WXR_Importer class in tests.
			eval( 'class WXR_Importer {}' );
		}

		// Create a new instance to trigger setup_hooks with WXR_Importer present.
		$reflection = new \ReflectionClass( Import::class );
		$instance   = $reflection->newInstanceWithoutConstructor();

		// Call setup_hooks via reflection.
		$method = new \ReflectionMethod( Import::class, 'setup_hooks' );
		$method->setAccessible( true );
		$method->invoke( $instance );

		/*
		 * Tests: $hook_name = 'wxr_importer.pre_process.post'.
		 */
		$this->assertNotFalse(
			has_filter( 'wxr_importer.pre_process.post', array( $instance, 'prepare' ) ),
			'Should use wxr_importer.pre_process.post hook when WXR_Importer class exists.'
		);
	}

	/**
	 * Coverage for prepare.
	 *
	 * @covers ::prepare
	 *
	 * @return void
	 */
	public function test_prepare(): void {
		$instance = Import::get_instance();

		$post_data_raw = array();
		$instance->prepare( $post_data_raw );

		$this->assertFinite(
			0,
			did_action( 'gatherpress_import' ),
			'Failed to assert that the import was not prepared for non-validating post data.'
		);

		$post_data_raw = array( 'post_type' => Event::POST_TYPE );
		$instance->prepare( $post_data_raw );

		$this->assertFinite(
			1,
			did_action( 'gatherpress_import' ),
			'Failed to assert that the import was prepared for valid post data.'
		);
	}

	/**
	 * Coverage for validate.
	 *
	 * @covers ::validate
	 *
	 * @return void
	 */
	public function test_validate(): void {
		$instance = Import::get_instance();

		$post_data_raw = array();
		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'validate', array( $post_data_raw ) ),
			'Failed to assert that validation fails for non-validating post data.'
		);

		$post_data_raw = array( 'post_type' => Event::POST_TYPE );
		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'validate', array( $post_data_raw ) ),
			'Failed to assert that validation passes for valid post data.'
		);
	}

	/**
	 * Coverage for extend.
	 *
	 * @covers ::extend
	 *
	 * @return void
	 */
	public function test_extend(): void {
		$instance = Import::get_instance();

		$this->assertFalse(
			has_filter( 'add_post_metadata', array( $instance, 'run' ) ),
			'Failed to assert that the "add_post_metadata" filter is not already added.'
		);

		$instance->extend();

		$this->assertSame(
			10,
			has_filter( 'add_post_metadata', array( $instance, 'run' ) ),
			'Failed to assert that the "add_post_metadata" filter was added.'
		);
	}

	/**
	 * Coverage for run.
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run(): void {
		$instance = Import::get_instance();

		// Defined for readability,
		// parameters are unrelated to the method under test.
		$check      = true;
		$object_id  = 0;
		$meta_value = 'data';
		$unique     = true;

		$this->assertNull(
			$instance->run( $check, $object_id, 'unit-test', $meta_value, $unique ),
			'Failed to assert that the import would not run for non-existing post_meta keys.'
		);

		$this->assertFalse(
			$instance->run( $check, $object_id, 'gatherpress_datetimes', $meta_value, $unique ),
			'Failed to assert that the import would run for existing, valid post_meta keys.'
		);
	}

	/**
	 * Coverage for run method when import_callback is not set or not callable.
	 *
	 * Tests that the method returns null when a pseudopostmeta entry
	 * exists but has no import_callback or a non-callable callback.
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run_with_no_import_callback(): void {
		$instance = Import::get_instance();

		// Add a filter to add a pseudopostmeta without import_callback.
		$filter = static function ( $pseudopostmetas ) {
			$pseudopostmetas['test_meta_no_callback'] = array(
				'export_callback' => '__return_empty_string',
			);
			return $pseudopostmetas;
		};
		add_filter( 'gatherpress_pseudopostmetas', $filter );

		// Defined for readability.
		$check      = true;
		$object_id  = 123;
		$meta_value = 'test_data';
		$unique     = true;

		// Tests: return null; (when import_callback is not set).
		$result = $instance->run( $check, $object_id, 'test_meta_no_callback', $meta_value, $unique );

		$this->assertNull(
			$result,
			'Should return null when import_callback is not set.'
		);

		remove_filter( 'gatherpress_pseudopostmetas', $filter );

		// Test with non-callable import_callback.
		$filter_noncallable = static function ( $pseudopostmetas ) {
			$pseudopostmetas['test_meta_noncallable'] = array(
				'import_callback' => 'this_function_does_not_exist',
			);
			return $pseudopostmetas;
		};
		add_filter( 'gatherpress_pseudopostmetas', $filter_noncallable );

		// Tests: return null; (when import_callback is not callable).
		$result = $instance->run( $check, $object_id, 'test_meta_noncallable', $meta_value, $unique );

		$this->assertNull(
			$result,
			'Should return null when import_callback is not callable.'
		);

		remove_filter( 'gatherpress_pseudopostmetas', $filter_noncallable );
	}

	/**
	 * Coverage for datetimes_callback.
	 *
	 * @covers ::datetimes_callback
	 *
	 * @return void
	 */
	public function test_datetimes_callback(): void {
		$instance = Import::get_instance();

		$post  = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event = new Event( $post->ID );

		$instance->datetimes_callback( $post->ID, 0 );
		$this->assertSame(
			array(
				'datetime_start'     => '',
				'datetime_start_gmt' => '',
				'datetime_end'       => '',
				'datetime_end_gmt'   => '',
				'timezone'           => '+00:00',
			),
			$event->get_datetime()
		);

		$meta_data_value = array(
			'post_id'        => $post->ID,
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-12 17:00:00',
			'timezone'       => 'America/New_York',
		);
		$instance->datetimes_callback( $post->ID, $meta_data_value );

		$expect = array(
			'datetime_start'     => '2020-05-11 15:00:00',
			'datetime_start_gmt' => '2020-05-11 19:00:00',
			'datetime_end'       => '2020-05-12 17:00:00',
			'datetime_end_gmt'   => '2020-05-12 21:00:00',
			'timezone'           => 'America/New_York',
		);

		$this->assertSame(
			$expect,
			$event->get_datetime()
		);
	}
}
