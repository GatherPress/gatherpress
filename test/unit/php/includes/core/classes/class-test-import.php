<?php
/**
 * Class handles unit tests for GatherPress\Core\Import.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Import;
use PMC\Unit_Test\Base;

/**
 * Class Test_Import.
 *
 * @coversDefaultClass \GatherPress\Core\Import
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
}
