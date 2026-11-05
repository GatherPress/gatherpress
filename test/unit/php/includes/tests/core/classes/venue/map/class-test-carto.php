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

	/**
	 * Data provider for tile URLs handed to use_basemaps_host.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<int, string>>
	 */
	public function data_basemaps_hosts(): array {
		return array(
			'an old host moves'              => array(
				'https://cartodb-basemaps-c.global.ssl.fastly.net/dark_all/{z}/{x}/{y}.png?foo=bar',
				'https://c.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png?foo=bar',
			),
			'the placeholder host moves too' => array(
				'https://cartodb-basemaps-{s}.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png',
				'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
			),
			'an explicit port moves too'     => array(
				'https://cartodb-basemaps-a.global.ssl.fastly.net:443/light_all/{z}/{x}/{y}.png',
				'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
			),
			'plain http moves to https'      => array(
				'http://cartodb-basemaps-a.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png',
				'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
			),
			'the current host is left alone' => array(
				Settings::MAP_TILE_URL,
				Settings::MAP_TILE_URL,
			),
			'another host is left alone'     => array(
				'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
				'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
			),
			'a lookalike host is left alone' => array(
				'https://cartodb-basemaps-a.global.ssl.fastly.net.evil.test/light_all/{z}/{x}/{y}.png',
				'https://cartodb-basemaps-a.global.ssl.fastly.net.evil.test/light_all/{z}/{x}/{y}.png',
			),
		);
	}

	/**
	 * Tile URLs on CARTO's old `fastly.net` hosts move to `basemaps.cartocdn.com`.
	 *
	 * @since TBD
	 *
	 * @covers ::use_basemaps_host
	 *
	 * @dataProvider data_basemaps_hosts
	 *
	 * @param string $url      Tile URL template.
	 * @param string $expected Template after the host is moved.
	 *
	 * @return void
	 */
	public function test_use_basemaps_host( string $url, string $expected ): void {
		$this->assertSame(
			$expected,
			Carto::use_basemaps_host( $url ),
			'Failed to assert the tile URL is on the expected host.'
		);
	}
}
