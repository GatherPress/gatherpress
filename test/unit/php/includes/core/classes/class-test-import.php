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

}
