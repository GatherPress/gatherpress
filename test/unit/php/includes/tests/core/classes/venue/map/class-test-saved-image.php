<?php
/**
 * Unit tests for the saved static map image helpers.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.36.2
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Map\Saved_Image;
use GatherPress\Tests\Base;

/**
 * Class Test_Saved_Image.
 *
 * @since 0.36.2
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Saved_Image
 */
class Test_Saved_Image extends Base {

	/**
	 * Venue information shared by the tests.
	 *
	 * @since 0.36.2
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
	 * @since 0.36.2
	 * @var array{0: int, 1: int, 2: int, 3: string}
	 */
	private const COMBO = array( 15, 800, 400, 'roadmap' );

	/**
	 * Combo key of the shared combo.
	 *
	 * @since 0.36.2
	 * @var string
	 */
	private const KEY = '15x800x400xroadmap';

	/**
	 * Delete unused files at once, so a test can check which files go.
	 *
	 * The delay itself is covered by `test_delete_unused_files_waits()`.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'gatherpress_static_map_delete_unused_delay', '__return_zero' );
	}

	/**
	 * Remove the delay filter and any queued delete.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'gatherpress_static_map_delete_unused_delay', '__return_zero' );
		wp_clear_scheduled_hook( Saved_Image::DELETE_CRON_ACTION );

		parent::tearDown();
	}

	/**
	 * Write an empty PNG file at a static map URL.
	 *
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * A replaced PNG stays on disk until the delete event runs.
	 *
	 * A page cache can still serve HTML with the old URL, so the file is
	 * deleted later, and only when no venue points at it again by then.
	 *
	 * @since TBD
	 *
	 * @covers ::delete_unused
	 * @covers ::delete_unused_files
	 * @covers ::delete_unused_now
	 *
	 * @return void
	 */
	public function test_delete_unused_files_waits(): void {
		remove_filter( 'gatherpress_static_map_delete_unused_delay', '__return_zero' );

		$old      = Saved_Image::url( 'qa-replaced-map.png' );
		$again    = Saved_Image::url( 'qa-used-again-map.png' );
		$paths    = array( $this->touch_image( $old ), $this->touch_image( $again ) );
		$venue_id = $this->factory->post->create();
		$before   = time();

		( new Saved_Image( self::files( $old, $again ), 'osm', self::KEY ) )->delete_unused();

		$this->assertFileExists( $paths[0], 'Failed to assert a replaced PNG stays until the event runs.' );
		$this->assertFileExists( $paths[1], 'Failed to assert a replaced 2x PNG stays until the event runs.' );

		$next = wp_next_scheduled( Saved_Image::DELETE_CRON_ACTION, array( array( $old, $again ) ) );

		$this->assertIsInt( $next, 'Failed to assert the delete is scheduled.' );
		$this->assertGreaterThanOrEqual( $before + Saved_Image::DEFAULT_DELETE_DELAY, $next );

		// A venue points at one of the files again before the event runs.
		update_post_meta(
			$venue_id,
			Map::META_KEY,
			array( 'osm' => array( self::KEY => self::files( $again ) ) )
		);

		// WP-Cron removes an event before it runs it.
		wp_unschedule_event( $next, Saved_Image::DELETE_CRON_ACTION, array( array( $old, $again ) ) );
		do_action( 'gatherpress_static_map_delete_unused_run', array( $old, $again ) );

		$this->assertFileDoesNotExist( $paths[0], 'Failed to assert an unused PNG is deleted when the event runs.' );
		$this->assertFileExists( $paths[1], 'Failed to assert a PNG a venue uses again is kept.' );

		Saved_Image::delete_unused_now( 'not a list' );
		Saved_Image::delete_unused_now( array( 'https://cdn.example.test/map.png' ) );

		$this->assertFileExists( $paths[1], 'Failed to assert other input deletes nothing.' );

		wp_delete_file( $paths[1] );
	}

	/**
	 * An earlier delete event leaves a file that a later event also holds.
	 *
	 * Two venues can share a PNG. When one queues it and the other queues it
	 * again days later, the first event must not delete it before the
	 * second event's delay ends.
	 *
	 * @since TBD
	 *
	 * @covers ::delete_unused_now
	 * @covers ::queued_urls
	 *
	 * @return void
	 */
	public function test_delete_unused_now_leaves_a_file_a_later_event_holds(): void {
		$shared = Saved_Image::url( 'qa-shared-map.png' );
		$path   = $this->touch_image( $shared );
		$first  = time() + DAY_IN_SECONDS;
		$later  = time() + WEEK_IN_SECONDS;

		wp_schedule_single_event( $first, Saved_Image::DELETE_CRON_ACTION, array( array( $shared ) ) );
		wp_schedule_single_event( $later, Saved_Image::DELETE_CRON_ACTION, array( array( $shared, 'x' ) ) );

		// The first event runs: WP-Cron removes it first, and the later one still holds the file.
		wp_unschedule_event( $first, Saved_Image::DELETE_CRON_ACTION, array( array( $shared ) ) );
		do_action( 'gatherpress_static_map_delete_unused_run', array( $shared ) );

		$this->assertFileExists( $path, 'Failed to assert the file waits for the later event.' );

		// The later event runs: no event holds the file any more.
		wp_unschedule_event( $later, Saved_Image::DELETE_CRON_ACTION, array( array( $shared, 'x' ) ) );
		do_action( 'gatherpress_static_map_delete_unused_run', array( $shared, 'x' ) );

		$this->assertFileDoesNotExist( $path, 'Failed to assert the later event deletes the unused file.' );
	}

	/**
	 * The delay filter sets when the files go; zero or less deletes at once.
	 *
	 * @since TBD
	 *
	 * @dataProvider data_delete_unused_delay
	 * @covers ::delete_unused_files
	 *
	 * @param int  $delay     Filtered delay in seconds.
	 * @param bool $scheduled Whether a delete event is expected.
	 *
	 * @return void
	 */
	public function test_delete_unused_files_honors_the_delay( int $delay, bool $scheduled ): void {
		remove_filter( 'gatherpress_static_map_delete_unused_delay', '__return_zero' );

		$url    = Saved_Image::url( 'qa-delay-map.png' );
		$path   = $this->touch_image( $url );
		$filter = static fn(): int => $delay;
		$before = time();

		add_filter( 'gatherpress_static_map_delete_unused_delay', $filter );
		( new Saved_Image( self::files( $url ), 'osm', self::KEY ) )->delete_unused();
		remove_filter( 'gatherpress_static_map_delete_unused_delay', $filter );

		$next = wp_next_scheduled( Saved_Image::DELETE_CRON_ACTION, array( array( $url ) ) );

		if ( $scheduled ) {
			$this->assertFileExists( $path, 'Failed to assert the file waits for the event.' );
			$this->assertGreaterThanOrEqual( $before + $delay, $next, 'Failed to assert the delay sets the time.' );
			$this->assertLessThanOrEqual( time() + $delay, $next );
			wp_delete_file( $path );
		} else {
			$this->assertFileDoesNotExist( $path, 'Failed to assert the file is deleted at once.' );
			$this->assertFalse( $next, 'Failed to assert no event is scheduled.' );
		}
	}

	/**
	 * Delays for test_delete_unused_files_honors_the_delay().
	 *
	 * @since TBD
	 *
	 * @return array<string, array{0: int, 1: bool}>
	 */
	public function data_delete_unused_delay(): array {
		return array(
			'custom delay'   => array( HOUR_IN_SECONDS, true ),
			'zero'           => array( 0, false ),
			'negative delay' => array( -60, false ),
		);
	}

	/**
	 * Nothing is scheduled when no file needs to go.
	 *
	 * @since TBD
	 *
	 * @covers ::delete_unused_files
	 *
	 * @return void
	 */
	public function test_delete_unused_files_schedules_nothing_to_delete(): void {
		remove_filter( 'gatherpress_static_map_delete_unused_delay', '__return_zero' );

		$kept = Saved_Image::url( 'qa-kept-map.png' );
		$path = $this->touch_image( $kept );

		$missing = Saved_Image::url( 'qa-not-on-disk.png' );

		( new Saved_Image( self::files( $kept ), 'osm', self::KEY ) )->delete_unused( array( $kept ) );
		( new Saved_Image( self::files( $missing ), 'osm', self::KEY ) )->delete_unused();

		$scheduled = 0;

		foreach ( (array) _get_cron_array() as $hooks ) {
			$scheduled += count( $hooks[ Saved_Image::DELETE_CRON_ACTION ] ?? array() );
		}

		$this->assertSame( 0, $scheduled, 'Failed to assert nothing is scheduled.' );

		wp_delete_file( $path );
	}

	/**
	 * A PNG no venue points at is deleted, and one a venue still uses, or
	 * the caller keeps, stays.
	 *
	 * @since 0.36.2
	 *
	 * @covers ::delete_all_unused
	 * @covers ::delete_unused
	 * @covers ::delete_unused_files
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

		$this->touch_image( $used );
		Saved_Image::delete_all_unused(
			array( 'osm' => array( self::KEY => self::files( $used . '?v=2' ) ) ),
			array( $used )
		);

		$this->assertFileExists( $paths[0], 'Failed to assert a PNG the caller keeps stays.' );

		wp_delete_file( $paths[0] );
	}

	/**
	 * The stored 1x and 2x URLs are listed as saved, without empty ones.
	 *
	 * @since 0.36.2
	 *
	 * @covers ::stored_urls
	 * @covers ::urls
	 *
	 * @return void
	 */
	public function test_stored_urls(): void {
		$post_id = $this->factory->post->create();

		$this->assertSame( array(), Saved_Image::stored_urls( $post_id ), 'Failed to assert no meta lists nothing.' );

		update_post_meta(
			$post_id,
			Map::META_KEY,
			array(
				'osm'    => array(
					self::KEY            => self::files( 'https://example.test/a.png', 'https://example.test/a2.png' ),
					'14x800x400xroadmap' => array( 'url' => 'https://example.test/b.png' ),
				),
				'broken' => 'not an array',
			)
		);

		$this->assertSame(
			array( 'https://example.test/a.png', 'https://example.test/a2.png', 'https://example.test/b.png' ),
			Saved_Image::stored_urls( $post_id )
		);
	}

	/**
	 * The files of a deleted static map value are deleted, and another meta
	 * key is ignored.
	 *
	 * @since 0.36.2
	 *
	 * @covers ::delete_deleted
	 * @covers ::urls
	 * @covers ::delete_unused_files
	 *
	 * @return void
	 */
	public function test_delete_deleted(): void {
		$url   = Saved_Image::url( 'qa-deleted-map.png' );
		$path  = $this->touch_image( $url );
		$value = array( 'osm' => array( self::KEY => self::files( $url ) ) );

		Saved_Image::delete_deleted( array(), 1, 'other_key', $value );

		$this->assertFileExists( $path, 'Failed to assert another meta key is ignored.' );

		Saved_Image::delete_deleted( array(), 1, Map::META_KEY, $value );

		$this->assertFileDoesNotExist( $path, 'Failed to assert the deleted map\'s file is deleted.' );
	}

	/**
	 * A usable saved image is put back into the meta, next to what is
	 * there, and one for another location is left out.
	 *
	 * @since 0.36.2
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
