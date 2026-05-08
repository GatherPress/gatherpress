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
	 * Resets the singleton static property so the constructor
	 * re-executes with coverage tracking.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor(): void {
		// Reset the static singleton so constructor runs again.
		Utility::set_and_get_hidden_static_property( Cli::class, 'instance', null );

		$instance = Cli::get_instance();

		$this->assertInstanceOf( Cli::class, $instance, 'Failed to assert Cli instance.' );
	}
}
