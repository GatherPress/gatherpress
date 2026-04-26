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

		// New block-default settings feed the venue-map block.json defaults
		// via Venue\Map::apply_block_attribute_defaults().
		foreach ( array(
			'venue_map_default_render_mode' => 'interactive',
			'venue_map_default_zoom'        => 18,
			'venue_map_default_height'      => '',
			'venue_map_default_scale'       => 'cover',
			'venue_map_default_type'        => 'roadmap',
		) as $key => $expected ) {
			$this->assertArrayHasKey(
				$key,
				$section['maps']['options'],
				sprintf( 'Failed to assert %s option is present.', $key )
			);
			$this->assertSame(
				$expected,
				$section['maps']['options'][ $key ]['field']['options']['default'],
				sprintf( 'Failed to assert default value for %s.', $key )
			);
		}
	}
}
