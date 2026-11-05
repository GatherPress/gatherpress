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
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Saved_Image
 */
class Test_Saved_Image extends Base {

	/**
	 * Venue information shared by the tests.
	 *
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
	 * @var array{0: int, 1: int, 2: int, 3: string}
	 */
	private const COMBO = array( 15, 800, 400, 'roadmap' );

	/**
	 * Write an empty PNG file at a static map URL.
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
	 * URL of the shared combo without a hash.
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
	 * A URL in the static maps folder is checked on disk, and any other
	 * URL counts as present.
	 *
	 * @since TBD
	 *
	 * @covers ::url
	 * @covers ::exists
	 *
	 * @return void
	 */
	public function test_exists(): void {
		$url = Saved_Image::url( 'qa-saved-image-test.png' );

		$this->assertStringEndsWith( '/' . Map::UPLOADS_SUBDIR . '/qa-saved-image-test.png', $url );
		$this->assertFalse( Saved_Image::exists( $url ), 'Failed to assert a missing PNG is reported.' );

		$path = $this->touch_image( $url );

		$this->assertTrue( Saved_Image::exists( $url ), 'Failed to assert a saved PNG is found.' );

		wp_delete_file( $path );

		$this->assertTrue(
			Saved_Image::exists( 'https://cdn.example.test/map.png' ),
			'Failed to assert a CDN URL counts.'
		);
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
	 * matched on `location` when stored and on the URL otherwise.
	 *
	 * @since TBD
	 *
	 * @covers ::is_usable
	 *
	 * @return void
	 */
	public function test_is_usable(): void {
		$url   = $this->legacy_url();
		$path  = $this->touch_image( $url );
		$moved = array_merge( self::INFO, array( 'latitude' => '37.3400' ) );

		$this->assertTrue( Saved_Image::is_usable( array( 'url' => $url ), self::INFO, 'osm', self::COMBO ) );
		$this->assertFalse(
			Saved_Image::is_usable( array( 'url' => $url ), $moved, 'osm', self::COMBO ),
			'Failed to assert a URL for another location is not usable.'
		);

		$entry = array(
			'url'      => 'https://cdn.example.test/map.png',
			'location' => Saved_Image::location( self::INFO ),
		);

		$this->assertTrue( Saved_Image::is_usable( $entry, self::INFO, 'osm', self::COMBO ) );
		$this->assertFalse(
			Saved_Image::is_usable( $entry, $moved, 'osm', self::COMBO ),
			'Failed to assert a stored location for another place is not usable.'
		);

		wp_delete_file( $path );

		$this->assertFalse(
			Saved_Image::is_usable( array( 'url' => $url ), self::INFO, 'osm', self::COMBO ),
			'Failed to assert a missing PNG is not usable.'
		);
	}

	/**
	 * A usable saved image is put back into the meta, next to what is
	 * there, and anything else is left out.
	 *
	 * @since TBD
	 *
	 * @covers ::restore
	 *
	 * @return void
	 */
	public function test_restore(): void {
		$post_id  = $this->factory->post->create();
		$key      = '15x800x400xroadmap';
		$entry    = array(
			'url'      => 'https://cdn.example.test/map.png',
			'location' => Saved_Image::location( self::INFO ),
		);
		$previous = array( 'osm' => array( $key => $entry ) );

		Saved_Image::restore( $post_id, array(), self::INFO, 'osm', $key, self::COMBO );

		$this->assertEmpty( get_post_meta( $post_id, Map::META_KEY, true ), 'Failed to assert nothing is put back.' );

		Saved_Image::restore(
			$post_id,
			array( 'osm' => array( $key => array_merge( $entry, array( 'location' => 'elsewhere' ) ) ) ),
			self::INFO,
			'osm',
			$key,
			self::COMBO
		);

		$this->assertEmpty(
			get_post_meta( $post_id, Map::META_KEY, true ),
			'Failed to assert another location is left out.'
		);

		Saved_Image::restore( $post_id, $previous, self::INFO, 'osm', $key, self::COMBO );

		$this->assertSame( $previous, get_post_meta( $post_id, Map::META_KEY, true ) );

		Saved_Image::restore(
			$post_id,
			array( 'google' => array( $key => $entry ) ),
			self::INFO,
			'google',
			$key,
			self::COMBO
		);

		$this->assertSame(
			array(
				'osm'    => array( $key => $entry ),
				'google' => array( $key => $entry ),
			),
			get_post_meta( $post_id, Map::META_KEY, true ),
			'Failed to assert the image is added next to what is there.'
		);
	}
}
