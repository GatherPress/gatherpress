<?php
/**
 * Unit tests for the CARTO basemap host helpers.
 *
 * @package GatherPress\Core\Venue\Map
 * @since TBD
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Settings;
use GatherPress\Core\Venue\Map\Carto;
use GatherPress\Tests\Base;

/**
 * Class Test_Carto.
 *
 * @since TBD
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Carto
 */
class Test_Carto extends Base {

	/**
	 * Data provider for tile URLs handed to is_tile_url.
	 *
	 * @since TBD
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public function data_tile_urls(): array {
		return array(
			'the placeholder host'       => array( Settings::MAP_TILE_URL, true ),
			'a lettered host'            => array( 'https://b.basemaps.cartocdn.com/light_all/1/2/3.png', true ),
			'the bare host'              => array( 'https://basemaps.cartocdn.com/light_all/1/2/3.png', true ),
			'an uppercase host'          => array( 'https://A.BASEMAPS.CARTOCDN.COM/light_all/1/2/3.png', true ),
			'an old host'                => array( 'https://cartodb-basemaps-a.global.ssl.fastly.net/x.png', false ),
			'another host'               => array( 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', false ),
			'a lookalike host'           => array( 'https://basemaps.cartocdn.com.evil.test/1/2/3.png', false ),
			'a string that is not a URL' => array( 'not a url', false ),
		);
	}

	/**
	 * Only CARTO's basemap hosts count as CARTO tile URLs.
	 *
	 * @since TBD
	 *
	 * @covers ::is_tile_url
	 *
	 * @dataProvider data_tile_urls
	 *
	 * @param string $url      Tile URL template.
	 * @param bool   $expected Whether the URL is CARTO's.
	 *
	 * @return void
	 */
	public function test_is_tile_url( string $url, bool $expected ): void {
		$this->assertSame( $expected, Carto::is_tile_url( $url ), 'Failed to assert the host check.' );
	}
}
