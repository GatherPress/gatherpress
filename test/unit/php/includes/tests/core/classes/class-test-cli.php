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
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor(): void {
		$instance = Cli::get_instance();

		$this->assertInstanceOf( Cli::class, $instance, 'Failed to assert Cli instance.' );
	}
}
