<?php
/**
 * Class handles unit tests for GatherPress\Core\Cli.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Cli;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Cli.
 *
 * @coversDefaultClass \GatherPress\Core\Cli
 */
class Test_Cli extends Base {
	/**
	 * Coverage for constructor.
	 *
	 * Resets the singleton instance and re-creates it to ensure
	 * the constructor code path is executed and covered.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor(): void {
		// Reset the singleton so constructor re-runs.
		Utility::set_and_get_hidden_property( Cli::get_instance(), 'instance', null );

		$instance = Cli::get_instance();

		$this->assertInstanceOf( Cli::class, $instance, 'Failed to assert Cli instance.' );
	}
}
