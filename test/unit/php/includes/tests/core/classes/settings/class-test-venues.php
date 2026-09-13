<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Venues.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Geocoding;
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

		$this->assertSame( 'venues_settings', $slug, 'Failed to assert slug is venues_settings.' );
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
		$this->assertArrayHasKey(
			'google_maps_api_key',
			$section['maps']['options'],
			'Failed to assert google_maps_api_key option is present.'
		);
		$this->assertSame(
			'text',
			$section['maps']['options']['google_maps_api_key']['field']['type'],
			'Failed to assert google_maps_api_key uses text field.'
		);

		// New block-default settings feed the venue-map block.json defaults
		// via Venue\Map::apply_block_attribute_defaults().
		foreach ( array(
			'venue_map_default_render_mode' => 'interactive',
			'venue_map_default_zoom'        => 16,
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

		// Default Map Type only affects Google Maps rendering, so it is gated
		// behind the Google platform via show_if (#1760).
		$this->assertSame(
			array( 'map_platform' => 'google' ),
			$section['maps']['options']['venue_map_default_type']['show_if'],
			'Failed to assert Default Map Type is gated to the Google platform.'
		);

		// Custom tile URL / attribution (#1267): optional, gated to OSM.
		foreach ( array( 'map_tile_url_custom', 'map_tile_attribution_custom' ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$section['maps']['options'],
				sprintf( 'Failed to assert %s option is present.', $key )
			);
			$this->assertSame(
				'text',
				$section['maps']['options'][ $key ]['field']['type'],
				sprintf( 'Failed to assert %s uses text field.', $key )
			);
			$this->assertSame(
				array( 'map_platform' => 'osm' ),
				$section['maps']['options'][ $key ]['show_if'],
				sprintf( 'Failed to assert %s is gated to the OSM platform.', $key )
			);
		}

		// Geocoding (#1267): Photon URL + country filter.
		$this->assertSame(
			'Geocoding',
			$section['geocoding']['name'],
			'Failed to assert geocoding section name is Geocoding.'
		);
		$this->assertArrayHasKey(
			'geocoding_provider_url',
			$section['geocoding']['options'],
			'Failed to assert geocoding_provider_url option is present.'
		);
		$this->assertSame(
			Geocoding::PHOTON_API_URL,
			$section['geocoding']['options']['geocoding_provider_url']['field']['options']['default'],
			'Failed to assert geocoding_provider_url defaults to the public Photon API URL.'
		);
		$this->assertArrayHasKey(
			'geocoding_country_filter',
			$section['geocoding']['options'],
			'Failed to assert geocoding_country_filter option is present.'
		);
		$this->assertSame(
			'text',
			$section['geocoding']['options']['geocoding_country_filter']['field']['type'],
			'Failed to assert geocoding_country_filter uses text field.'
		);
	}
}
