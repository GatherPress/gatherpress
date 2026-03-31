<?php
/**
 * Class handles unit tests for GatherPress\Integrations\Setup.
 *
 * @package GatherPress\Integrations
 * @since 1.0.0
 */

namespace GatherPress\Tests\Integrations;

use GatherPress\Integrations\Setup;
use GatherPress\Tests\Base;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Integrations\Setup
 */
class Test_Setup extends Base {
	/**
	 * Coverage for constructor.
	 *
	 * @covers ::__construct
	 * @covers ::instantiate_classes
	 *
	 * @return void
	 */
	public function test_constructor(): void {
		$instance = Setup::get_instance();

		$this->assertInstanceOf( Setup::class, $instance, 'Failed to assert Integrations Setup instance.' );
	}
}
