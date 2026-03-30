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
use ReflectionClass;

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
		// Reset the singleton static property so constructor re-runs.
		$reflection = new ReflectionClass( Cli::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		$instance = Cli::get_instance();

		$this->assertInstanceOf( Cli::class, $instance, 'Failed to assert Cli instance.' );
	}
}
