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
use PMC\Unit_Test\Base;
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
			did_action('gatherpress_import'),
			'Failed to assert that the import was not prepared for non-validating post data.'
		);

		$post_data_raw = array( 'post_type' => Event::POST_TYPE );
		$instance->prepare( $post_data_raw );

		$this->assertFinite(
			1,
			did_action('gatherpress_import'),
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
			has_filter( 'add_post_metadata', array( $instance, 'run') ),
			'Failed to assert that the "add_post_metadata" filter is not already added.'
		);

		$instance->extend();

		$this->assertSame(
			10,
			has_filter( 'add_post_metadata', array( $instance, 'run') ),
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

		// Defined for readablity,
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

}
