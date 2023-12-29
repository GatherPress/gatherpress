<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\General.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\General;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_General.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\General
 */
class Test_General extends Base {
	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$instance = General::get_instance();

		Utility::invoke_hidden_method( $instance, '__construct' );

		$this->assertSame(
			'General',
			Utility::get_hidden_property( $instance, 'name' ),
			'Failed to assert name matches General.'
		);

		$this->assertSame(
			PHP_INT_MIN,
			Utility::get_hidden_property( $instance, 'priority' ),
			'Failed to assert priority matches PHP_INT_MIN.'
		);

		$this->assertSame(
			'general',
			Utility::get_hidden_property( $instance, 'slug' ),
			'Failed to assert slug matches general.'
		);
	}

	/**
	 * Coverage for get_section method.
	 *
	 * @covers ::get_section
	 *
	 * @return void
	 */
	public function test_get_section(): void {
		$instance = General::get_instance();

		$section = Utility::invoke_hidden_method( $instance, 'get_section' );
		$this->assertSame( 'General Settings', $section['general']['name'], 'Failed to assert name is General Settings.' );
		$this->assertIsArray(
			$section['pages'],
			'Failed to assert pages section is an array.'
		);
	}
}
