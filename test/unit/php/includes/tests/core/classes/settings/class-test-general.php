<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\General.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\General;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_General.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\General
 */
class Test_General extends Base {
	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = General::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'general', $slug, 'Failed to assert slug is general.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = General::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'General', $name, 'Failed to assert name is General.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = General::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( PHP_INT_MIN, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for get_sections method.
	 *
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_get_sections(): void {
		$instance = General::get_instance();

		$section = Utility::invoke_hidden_method( $instance, 'get_sections' );
		$this->assertSame(
			'General Settings',
			$section['general']['name'],
			'Failed to assert name is General Settings.'
		);
		$this->assertIsArray(
			$section['pages'],
			'Failed to assert sections is an array.'
		);
	}
}
