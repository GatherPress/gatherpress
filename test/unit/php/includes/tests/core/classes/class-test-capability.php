<?php
/**
 * Class handles unit tests for GatherPress\Core\Capability.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Capability;
use GatherPress\Tests\Base;

/**
 * Class Test_Capability.
 *
 * @coversDefaultClass \GatherPress\Core\Capability
 */
class Test_Capability extends Base {

	/**
	 * Verify the constants hold WordPress's post meta capabilities.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_constants(): void {
		$this->assertSame( 'read_post', Capability::READ_POST );
		$this->assertSame( 'edit_post', Capability::EDIT_POST );
	}
}
