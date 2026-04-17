<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Venues.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Venues;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Venues.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Venues
 */
class Test_Venues extends Base {
	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Venues::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'venues', $slug, 'Failed to assert slug is venues.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = Venues::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'Venues', $name, 'Failed to assert name is Venues.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = Venues::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( 1, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for get_sections method.
	 *
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_get_sections(): void {
		$instance = Venues::get_instance();

		$section = Utility::invoke_hidden_method( $instance, 'get_sections' );
		$this->assertSame(
			'Maps',
			$section['maps']['name'],
			'Failed to assert name is Maps (moved here from the removed Formatting tab).'
		);
		$this->assertArrayHasKey(
			'map_platform',
			$section['maps']['options'],
			'Failed to assert map_platform option is present.'
		);
		$this->assertSame(
			'osm',
			$section['maps']['options']['map_platform']['field']['options']['default'],
			'Failed to assert map_platform defaults to osm.'
		);
	}
}
