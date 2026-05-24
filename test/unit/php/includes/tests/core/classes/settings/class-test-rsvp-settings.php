<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Rsvp_Settings.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Rsvp_Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rsvp_Settings.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Rsvp_Settings
 */
class Test_Rsvp_Settings extends Base {

	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Rsvp_Settings::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'rsvp_settings', $slug, 'Failed to assert slug is rsvp_settings.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = Rsvp_Settings::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'RSVP', $name, 'Failed to assert name is RSVP.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = Rsvp_Settings::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( 2, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for get_sections method.
	 *
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_get_sections(): void {
		$instance = Rsvp_Settings::get_instance();

		$section = Utility::invoke_hidden_method( $instance, 'get_sections' );
		$this->assertSame(
			'RSVP Defaults',
			$section['rsvp_defaults']['name'],
			'Failed to assert name is RSVP Defaults.'
		);
		$this->assertIsArray(
			$section['rsvp_cleanup'],
			'Failed to assert rsvp_cleanup is an array.'
		);
	}
}
