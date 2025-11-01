<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Admin_Page.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\Admin_Page;
use GatherPress\Tests\Base;

/**
 * Class Test_Admin_Page.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Admin_Page
 */
class Test_Admin_Page extends Base {
	/**
	 * Coverage for singleton pattern.
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_get_instance(): void {
		$instance1 = Admin_Page::get_instance();
		$instance2 = Admin_Page::get_instance();

		$this->assertSame( $instance1, $instance2, 'Failed to assert singleton pattern works.' );
		$this->assertInstanceOf( Admin_Page::class, $instance1, 'Failed to assert instance is Admin_Page.' );
	}
}
