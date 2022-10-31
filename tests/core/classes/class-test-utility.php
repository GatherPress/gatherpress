<?php
/**
 * Class handles unit tests for GatherPress\Includes\Utility.
 *
 * @package GatherPress
 * @subpackage Includes
 * @since 1.0.0
 */

namespace GatherPress\Tests\Includes;

use GatherPress\Includes\Utility;
use PMC\Unit_Test\Base;

/**
 * Class Test_Utility.
 *
 * @coversDefaultClass \GatherPress\Includes\Utility
 */
class Test_Utility extends Base {

	/**
	 * Cover for prefix_key method.
	 *
	 * @covers ::prefix_key
	 *
	 * @return void
	 */
	public function test_prefix_key() {
		$this->assertSame( 'gp_unittest', Utility::prefix_key( 'unittest' ) );
	}

	/**
	 * Cover for unprefix_key method.
	 *
	 * @covers ::unprefix_key
	 *
	 * @return void
	 */
	public function test_unprefix_key() {
		$this->assertSame( 'unittest', Utility::unprefix_key( 'gp_unittest' ) );
	}

}
