<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Leadership.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Leadership;
use GatherPress\Core\Settings;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Settings.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Leadership
 */
class Test_Leadership extends Base {

	/**
	 * Coverage for get_user_roles method.
	 *
	 * @covers ::get_user_roles
	 *
	 * @return void
	 */
	public function test_get_user_roles(): void {
		$instance   = Leadership::get_instance();
		$user_roles = $instance->get_user_roles();

		$this->assertIsArray( $user_roles['organizer'], 'Failed to assert user role is an array.' );
	}

}
