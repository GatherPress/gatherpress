<?php
/**
 * Unit tests for the saved static map image helpers.
 *
 * @package GatherPress\Core\Venue\Map
 * @since TBD
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Map\Saved_Image;
use GatherPress\Tests\Base;

/**
 * Class Test_Saved_Image.
 *
 * @since TBD
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Saved_Image
 */
class Test_Saved_Image extends Base {

	/**
	 * Venue information shared by the tests.
	 *
	 * @since TBD
	 * @var array<string, string>
	 */
	private const INFO = array(
		'address'   => '1 Infinite Loop',
		'latitude'  => '37.3318',
		'longitude' => '-122.0312',
	);

	/**
	 * Zoom, width, height and map type shared by the tests.
	 *
	 * @since TBD
	 * @var array{0: int, 1: int, 2: int, 3: string}
	 */
	private const COMBO = array( 15, 800, 400, 'roadmap' );

	/**
	 * Combo key of the shared combo.
	 *
	 * @since TBD
	 * @var string
	 */
	private const KEY = '15x800x400xroadmap';

	/**
	 * Write an empty PNG file at a static map URL.
	 *
	 * @since TBD
	 *
	 * @param string $url Static map URL.
	 *
	 * @return string Path of the file.
	 */
	private function touch_image( string $url ): string {
		$dirs = wp_get_upload_dir();
		$path = str_replace( $dirs['baseurl'], $dirs['basedir'], $url );

		wp_mkdir_p( dirname( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Empty stand-in PNG in uploads.
		file_put_contents( $path, '' );

		return $path;
	}

	/**
	 * A saved descriptor with the given fields.
	 *
	 * @since TBD
	 *
	 * @param array<string, string> $fields Fields to set.
	 *
	 * @return array{url: string, url_2x: string, hash: string, location?: string, zoom: int, width: int, height: int}
	 */
	private static function entry( array $fields ): array {
		return array_merge(
			array(
				'url'    => '',
				'url_2x' => '',
				'hash'   => 'saved',
				'zoom'   => 15,
				'width'  => 800,
				'height' => 400,
			),
			$fields
		);
	}

	/**
	 * A saved descriptor with the given PNG URLs.
	 *
	 * @since TBD
	 *
	 * @param string $url    URL of the 1x PNG.
	 * @param string $url_2x URL of the 2x PNG.
	 *
	 * @return array{url: string, url_2x: string, hash: string, location?: string, zoom: int, width: int, height: int}
	 */
	private static function files( string $url, string $url_2x = '' ): array {
		return self::entry(
			array(
				'url'    => $url,
				'url_2x' => $url_2x,
			)
		);
	}

	/**
	 * URL of the shared combo without a hash.
	 *
	 * @since TBD
	 *
	 * @return string The URL.
	 */
	private function legacy_url(): string {
		return Saved_Image::url(
			Saved_Image::filename( '1 Infinite Loop', 37.3318, -122.0312, 15, 800, 400, 'osm', 1, 'roadmap' )
		);
	}

	/**
	 * The file name carries the address, coordinates, provider and size.
	 *
	 * @since TBD
	 *
	 * @covers ::filename
	 *
	 * @return void
	 */
	public function test_filename(): void {
		$this->assertSame(
			'1-infinite-loop-37.331800_-122.031200-osm-roadmap-15-800-400.png',
			Saved_Image::filename( '1 Infinite Loop', 37.3318, -122.0312, 15, 800, 400, 'osm', 1, 'roadmap' )
		);
		$this->assertSame(
			'venue-1.000000_2.000000-osm-roadmap-15-800-400@2x.png',
			Saved_Image::filename( '!!!', 1.0, 2.0, 15, 800, 400, 'osm', 2, 'roadmap' ),
			'Failed to assert the fallback slug and retina suffix.'
		);
		$this->assertStringStartsWith(
			str_repeat( 'a', 150 ) . '-',
			Saved_Image::filename( str_repeat( 'a', 200 ), 1.0, 2.0, 15, 800, 400, 'osm', 1, 'roadmap' ),
			'Failed to assert a long address is cut to 150 characters.'
		);
	}

	/**
	 * The hash goes before the retina suffix.
	 *
	 * @since TBD
	 *
	 * @covers ::versioned
	 *
	 * @return void
	 */
	public function test_versioned(): void {
		$hash = '1a2b3c4d5e6f';

		$this->assertSame( 'map-800-400-1a2b3c4d.png', Saved_Image::versioned( 'map-800-400.png', $hash ) );
		$this->assertSame( 'map-800-400-1a2b3c4d@2x.png', Saved_Image::versioned( 'map-800-400@2x.png', $hash ) );
	}

	/**
	 * A saved image is found by provider and combo.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::find
	 *
	 * @return void
	 */
	public function test_find(): void {
		$entry = self::entry(
			array(
				'url'      => 'https://cdn.example.test/map.png',
				'location' => Saved_Image::location( self::INFO ),
			)
		);
		$found = Saved_Image::find( array( 'osm' => array( self::KEY => $entry ) ), 'osm', self::KEY );

		$this->assertSame( $entry, $found?->usable( self::INFO, self::COMBO ) );
		$this->assertNull( Saved_Image::find( array(), 'osm', self::KEY ), 'Failed to assert a missing entry.' );
	}

	/**
	 * The 2x PNG counts when its URL is set and the file is there, or the
	 * URL is outside the static maps folder.
	 *
	 * @since TBD
	 *
	 * @covers ::has_2x
	 * @covers ::file_exists
	 * @covers ::without_query
	 * @covers ::path
	 * @covers ::url
	 *
	 * @return void
	 */
	public function test_has_2x(): void {
		$url   = Saved_Image::url( 'qa-saved-image-test@2x.png' );
		$image = static function ( string $url_2x ): Saved_Image {
			return new Saved_Image( self::entry( array( 'url_2x' => $url_2x ) ), 'osm', self::KEY );
		};

		$this->assertStringEndsWith( '/' . Map::UPLOADS_SUBDIR . '/qa-saved-image-test@2x.png', $url );
		$this->assertFalse( $image( '' )->has_2x(), 'Failed to assert an empty 2x URL is reported.' );
		$this->assertFalse( $image( $url )->has_2x(), 'Failed to assert a missing PNG is reported.' );

		$path = $this->touch_image( $url );

		$this->assertTrue( $image( $url )->has_2x(), 'Failed to assert a saved PNG is found.' );
		$this->assertTrue( $image( $url . '?v=2' )->has_2x(), 'Failed to assert a query string is ignored.' );

		wp_delete_file( $path );

		$this->assertTrue( $image( 'https://cdn.example.test/map@2x.png' )->has_2x(), 'Failed to assert a CDN URL.' );
		$this->assertTrue(
			$image( Saved_Image::url( '../missing-outside.png' ) )->has_2x(),
			'Failed to assert a URL that leaves the folder is not checked.'
		);
	}

	/**
	 * The image is current when the hash matches, the URL is the hashed or
	 * plain one, and the 1x PNG exists.
	 *
	 * @since TBD
	 *
	 * @covers ::is_current
	 *
	 * @return void
	 */
	public function test_is_current(): void {
		$plain      = $this->legacy_url();
		$hashed     = Saved_Image::versioned( $plain, 'abcdef123456' );
		$paths      = array( $this->touch_image( $plain ), $this->touch_image( $hashed ) );
		$image      = static function ( string $url, string $hash = 'abcdef123456' ): Saved_Image {
			return new Saved_Image(
				self::entry(
					array(
						'url'  => $url,
						'hash' => $hash,
					)
				),
				'osm',
				self::KEY
			);
		};
		$is_current = static function ( Saved_Image $image ) use ( $plain ): bool {
			return $image->is_current( 'abcdef123456', $plain );
		};

		$this->assertTrue( $is_current( $image( $hashed ) ), 'Failed to assert the hashed URL is current.' );
		$this->assertTrue( $is_current( $image( $plain ) ), 'Failed to assert the plain URL is current.' );
		$this->assertTrue( $is_current( $image( $plain . '?v=2' ) ), 'Failed to assert a query string is ignored.' );
		$this->assertFalse( $is_current( $image( $hashed, 'stale' ) ), 'Failed to assert another hash.' );
		$this->assertFalse(
			$is_current( $image( Saved_Image::url( 'qa-other.png' ) ) ),
			'Failed to assert another URL is not current.'
		);

		array_map( 'wp_delete_file', $paths );

		$this->assertFalse( $is_current( $image( $hashed ) ), 'Failed to assert a missing PNG is not current.' );
	}

	/**
	 * The location changes with the address or coordinates.
	 *
	 * @since TBD
	 *
	 * @covers ::location
	 *
	 * @return void
	 */
	public function test_location(): void {
		$moved = array_merge( self::INFO, array( 'latitude' => '37.3400' ) );

		$this->assertSame( Saved_Image::location( self::INFO ), Saved_Image::location( self::INFO ) );
		$this->assertNotSame( Saved_Image::location( self::INFO ), Saved_Image::location( $moved ) );
	}

	/**
	 * A saved image is usable for the same location when its PNG exists,
	 * matched on `location` when stored and on the URL otherwise. A missing
	 * 2x PNG is left out.
	 *
	 * @since TBD
	 *
	 * @covers ::usable
	 *
	 * @return void
	 */
	public function test_usable(): void {
		$url    = $this->legacy_url();
		$path   = $this->touch_image( $url );
		$moved  = array_merge( self::INFO, array( 'latitude' => '37.3400' ) );
		$usable = static function ( array $entry, array $info ): ?array {
			return ( new Saved_Image( $entry, 'osm', self::KEY ) )->usable( $info, self::COMBO );
		};

		$legacy = self::entry( array( 'url' => $url ) );

		$this->assertSame( $legacy, $usable( $legacy, self::INFO ) );
		$this->assertNull( $usable( $legacy, $moved ), 'Failed to assert a URL for another location is not usable.' );

		$queried = self::entry( array( 'url' => $url . '?v=2' ) );

		$this->assertSame( $queried, $usable( $queried, self::INFO ), 'Failed to assert a query string is ignored.' );

		$entry = self::entry(
			array(
				'url'      => 'https://cdn.example.test/map.png',
				'url_2x'   => 'https://cdn.example.test/map@2x.png',
				'location' => Saved_Image::location( self::INFO ),
			)
		);

		$this->assertSame( $entry, $usable( $entry, self::INFO ) );
		$this->assertNull(
			$usable( $entry, $moved ),
			'Failed to assert a stored location for another place is not usable.'
		);

		$missing_2x = self::entry(
			array(
				'url'    => $url,
				'url_2x' => Saved_Image::url( 'qa-missing@2x.png' ),
			)
		);

		$this->assertSame(
			'',
			$usable( $missing_2x, self::INFO )['url_2x'] ?? null,
			'Failed to assert a missing 2x PNG is left out.'
		);

		wp_delete_file( $path );

		$this->assertNull( $usable( $legacy, self::INFO ), 'Failed to assert a missing PNG is not usable.' );
	}

	/**
	 * A PNG no venue points at is deleted, and one a venue still uses, or
	 * the caller keeps, stays.
	 *
	 * @since TBD
	 *
	 * @covers ::delete_all_unused
	 * @covers ::delete_unused
	 *
	 * @return void
	 */
	public function test_delete_unused(): void {
		$used     = Saved_Image::url( 'qa-used-map.png' );
		$unused   = Saved_Image::url( 'qa-unused-map.png' );
		$used_2x  = Saved_Image::url( 'qa-used-map@2x.png' );
		$paths    = array( $this->touch_image( $used ), $this->touch_image( $unused ), $this->touch_image( $used_2x ) );
		$venue_id = $this->factory->post->create();
		$delete   = static function ( string $url, string $url_2x = '', array $keep = array() ): void {
			( new Saved_Image( self::files( $url, $url_2x ), 'osm', self::KEY ) )->delete_unused( $keep );
		};

		update_post_meta(
			$venue_id,
			Map::META_KEY,
			array( 'osm' => array( self::KEY => self::files( $used, $used_2x ) ) )
		);

		Saved_Image::delete_all_unused( array( 'osm' => array( self::KEY => self::files( $used, $unused ) ) ) );
		$delete( 'https://cdn.example.test/map.png' );
		$delete( Saved_Image::url( 'qa-never-saved.png' ) );

		$dirs    = wp_get_upload_dir();
		$outside = trailingslashit( $dirs['basedir'] ) . 'gatherpress/qa-outside.png';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Stand-in file outside the folder.
		file_put_contents( $outside, '' );
		$delete( Saved_Image::url( '../qa-outside.png' ) );
		$delete( Saved_Image::url( '..' ) );

		$this->assertFileExists( $outside, 'Failed to assert a URL that leaves the folder deletes nothing.' );

		wp_delete_file( $outside );

		$this->assertFileExists( $paths[0], 'Failed to assert a PNG a venue uses is kept.' );
		$this->assertFileDoesNotExist( $paths[1], 'Failed to assert an unused PNG is deleted.' );

		update_post_meta(
			$venue_id,
			Map::META_KEY,
			array(
				'osm' => array(
					self::KEY => self::files(
						Saved_Image::url( 'big-qa-used-map.png' ),
						Saved_Image::url( 'qa-used-map@2x.png.old' )
					),
				),
			)
		);
		$delete( $used, $used_2x, array( $used_2x ) );

		$this->assertFileDoesNotExist( $paths[0], 'Failed to assert a longer name ending the same way keeps no PNG.' );
		$this->assertFileExists( $paths[2], 'Failed to assert a PNG the caller keeps stays.' );

		$delete( $used, $used_2x );

		$this->assertFileDoesNotExist( $paths[2], 'Failed to assert a longer name with the same start keeps no PNG.' );

		$this->touch_image( $used );
		update_post_meta(
			$venue_id,
			Map::META_KEY,
			array( 'osm' => array( self::KEY => self::files( $used . '?v=2' ) ) )
		);
		$delete( $used );

		$this->assertFileExists( $paths[0], 'Failed to assert a PNG a venue uses with a query string is kept.' );

		delete_post_meta( $venue_id, Map::META_KEY );
		$delete( $used . '?v=2' );

		$this->assertFileDoesNotExist( $paths[0], 'Failed to assert an unused PNG with a query string is deleted.' );
	}

	/**
	 * A usable saved image is put back into the meta, next to what is
	 * there, and one for another location is left out.
	 *
	 * @since TBD
	 *
	 * @covers ::restore
	 *
	 * @return void
	 */
	public function test_restore(): void {
		$post_id = $this->factory->post->create();
		$entry   = self::entry(
			array(
				'url'      => 'https://cdn.example.test/map.png',
				'location' => Saved_Image::location( self::INFO ),
			)
		);

		( new Saved_Image( array_merge( $entry, array( 'location' => 'elsewhere' ) ), 'osm', self::KEY ) )
			->restore( $post_id, self::INFO, self::COMBO );

		$this->assertEmpty(
			get_post_meta( $post_id, Map::META_KEY, true ),
			'Failed to assert another location is left out.'
		);

		( new Saved_Image( $entry, 'osm', self::KEY ) )->restore( $post_id, self::INFO, self::COMBO );

		$this->assertSame(
			array( 'osm' => array( self::KEY => $entry ) ),
			get_post_meta( $post_id, Map::META_KEY, true )
		);

		( new Saved_Image( $entry, 'google', self::KEY ) )->restore( $post_id, self::INFO, self::COMBO );

		$this->assertSame(
			array(
				'osm'    => array( self::KEY => $entry ),
				'google' => array( self::KEY => $entry ),
			),
			get_post_meta( $post_id, Map::META_KEY, true ),
			'Failed to assert the image is added next to what is there.'
		);
	}
}
