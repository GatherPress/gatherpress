<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Cleanup.
 *
 * @package GatherPress\Core\Venue\Map
 * @since TBD
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Venue\Map\Cleanup;
use GatherPress\Core\Venue\Map\Map;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Cleanup.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Cleanup
 */
class Test_Cleanup extends Base {

	/**
	 * Uploads directory path for tests.
	 *
	 * @var string
	 */
	protected string $static_dir = '';

	/**
	 * Setup uploads directory for tests.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$dirs             = wp_get_upload_dir();
		$this->static_dir = trailingslashit( $dirs['basedir'] ) . Map::UPLOADS_SUBDIR;

		if ( ! is_dir( $this->static_dir ) ) {
			wp_mkdir_p( $this->static_dir );
		}
	}

	/**
	 * Clean up created files and transients between tests.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_transient( Cleanup::LOCK_TRANSIENT );
		delete_transient( Cleanup::RESUME_TRANSIENT );

		if ( is_dir( $this->static_dir ) ) {
			$files = (array) glob( $this->static_dir . '/*' );

			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					unlink( $file );
				}
			}
		}

		parent::tear_down();
	}

	/**
	 * Helper to create a dummy static map file with custom modification time.
	 *
	 * @param string $filename Filename.
	 * @param int    $mtime    Modification timestamp.
	 *
	 * @return string Full file path.
	 */
	protected function create_map_file( string $filename, int $mtime ): string {
		$path = trailingslashit( $this->static_dir ) . $filename;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, 'dummy-png-bytes' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		touch( $path, $mtime );

		return $path;
	}

	/**
	 * Tests allow-list filename regex for valid patterns.
	 *
	 * @since TBD
	 *
	 * @covers ::is_valid_filename
	 *
	 * @return void
	 */
	public function test_is_valid_filename_accepts_valid_patterns(): void {
		$cleanup = Cleanup::get_instance();

		$valid = array(
			'venue-15-800-400.png',
			'venue-15-800-400@2x.png',
			'1-infinite-loop-15-800-400.png',
			'1-infinite-loop-15-800-400@2x.png',
			'venue-osm-roadmap-15-800-400.png',
			'venue-osm-roadmap-15-800-400@2x.png',
			'1-infinite-loop-osm-roadmap-15-800-400.png',
			'1-infinite-loop-osm-roadmap-15-800-400@2x.png',
			'my-venue-name-google-satellite-12-600-300.png',
		);

		foreach ( $valid as $filename ) {
			$this->assertTrue(
				$cleanup->is_valid_filename( $filename ),
				sprintf( 'Failed to assert "%s" is recognized as valid.', $filename )
			);
		}
	}

	/**
	 * Tests allow-list filename regex rejects non-static-map files.
	 *
	 * @since TBD
	 *
	 * @covers ::is_valid_filename
	 *
	 * @return void
	 */
	public function test_is_valid_filename_rejects_invalid_patterns(): void {
		$cleanup = Cleanup::get_instance();

		$invalid = array(
			'.htaccess',
			'index.php',
			'image.jpg',
			'avatar.png',
			'venue-15-800.png',
			'venue-abc.png',
			'../../wp-config.php',
			'something-else-15-800-400.jpg',
		);

		foreach ( $invalid as $filename ) {
			$this->assertFalse(
				$cleanup->is_valid_filename( $filename ),
				sprintf( 'Failed to assert "%s" is rejected.', $filename )
			);
		}
	}

	/**
	 * Tests get_uploads_dir returns valid static map directory path.
	 *
	 * @since TBD
	 *
	 * @covers ::get_uploads_dir
	 *
	 * @return void
	 */
	public function test_get_uploads_dir(): void {
		$cleanup = Cleanup::get_instance();
		$dir     = $cleanup->get_uploads_dir();

		$this->assertIsString( $dir );
		$this->assertStringEndsWith( 'gatherpress/static-maps', $dir );
	}

	/**
	 * Tests get_in_use_basenames returns empty when no venues exist.
	 *
	 * @since TBD
	 *
	 * @covers ::get_in_use_basenames
	 *
	 * @return void
	 */
	public function test_get_in_use_basenames_empty(): void {
		$cleanup = Cleanup::get_instance();
		$in_use  = $cleanup->get_in_use_basenames();

		$this->assertIsArray( $in_use );
		$this->assertEmpty( $in_use );
	}

	/**
	 * Tests get_in_use_basenames collects image URLs from Map::META_KEY descriptors.
	 *
	 * @since TBD
	 *
	 * @covers ::get_in_use_basenames
	 *
	 * @return void
	 */
	public function test_get_in_use_basenames_collects_urls(): void {
		$cleanup = Cleanup::get_instance();
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$base_url = 'https://example.com/wp-content/uploads/gatherpress/static-maps/';
		$meta     = array(
			'osm' => array(
				'15x800x400xroadmap' => array(
					'url'    => $base_url . 'venue-osm-roadmap-15-800-400.png',
					'url_2x' => $base_url . 'venue-osm-roadmap-15-800-400@2x.png',
					'hash'   => 'abc123',
					'zoom'   => 15,
					'width'  => 800,
					'height' => 400,
				),
			),
		);

		update_post_meta( $post_id, Map::META_KEY, $meta );

		$in_use = $cleanup->get_in_use_basenames();

		$this->assertArrayHasKey( 'venue-osm-roadmap-15-800-400.png', $in_use );
		$this->assertArrayHasKey( 'venue-osm-roadmap-15-800-400@2x.png', $in_use );
	}

	/**
	 * Tests get_in_use_basenames collects image URLs from legacy gatherpress_venue_static_map meta.
	 *
	 * @since TBD
	 *
	 * @covers ::get_in_use_basenames
	 *
	 * @return void
	 */
	public function test_get_in_use_basenames_collects_legacy_meta(): void {
		$cleanup = Cleanup::get_instance();
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$legacy = array(
			'url' => 'https://example.com/wp-content/uploads/gatherpress/static-maps/legacy-venue-15-800-400.png',
		);

		update_post_meta( $post_id, 'gatherpress_venue_static_map', $legacy );

		$in_use = $cleanup->get_in_use_basenames();

		$this->assertArrayHasKey( 'legacy-venue-15-800-400.png', $in_use );
	}

	/**
	 * Tests sweep skips execution when concurrency lock transient is active.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_skips_when_locked(): void {
		$cleanup = Cleanup::get_instance();
		set_transient( Cleanup::LOCK_TRANSIENT, time(), Cleanup::LOCK_TTL );

		$result = $cleanup->sweep( true );

		$this->assertFalse( $result['success'] );
		$this->assertTrue( $result['locked'] );
		$this->assertSame( 0, $result['scanned'] );
	}

	/**
	 * Tests sweep bypasses concurrency lock when force argument is true.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_respects_force_flag_when_locked(): void {
		$cleanup = Cleanup::get_instance();
		set_transient( Cleanup::LOCK_TRANSIENT, time(), Cleanup::LOCK_TTL );

		$result = $cleanup->sweep( true, 1000, true );

		$this->assertTrue( $result['success'] );
		$this->assertFalse( $result['locked'] );
	}

	/**
	 * Tests sweep race guard skips files modified within the last 5 minutes.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_race_guard_skips_recent_files(): void {
		$cleanup  = Cleanup::get_instance();
		$filename = 'recent-venue-osm-roadmap-15-800-400.png';
		$path     = $this->create_map_file( $filename, time() - 60 );

		$result = $cleanup->sweep( false );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['scanned'] );
		$this->assertSame( 0, $result['orphans'] );
		$this->assertSame( 0, $result['deleted'] );
		$this->assertFileExists( $path );
	}

	/**
	 * Tests sweep dry-run reports orphans without unlinking files.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_dry_run_does_not_delete(): void {
		$cleanup  = Cleanup::get_instance();
		$filename = 'orphan-venue-osm-roadmap-15-800-400.png';
		$path     = $this->create_map_file( $filename, time() - 600 );

		$result = $cleanup->sweep( true );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['scanned'] );
		$this->assertSame( 1, $result['orphans'] );
		$this->assertSame( 0, $result['deleted'] );
		$this->assertGreaterThan( 0, $result['bytes_reclaimed'] );
		$this->assertContains( $filename, $result['files'] );
		$this->assertFileExists( $path );
	}

	/**
	 * Tests sweep execute mode actually deletes orphaned files and reclaims bytes.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_execute_deletes_orphan(): void {
		$cleanup  = Cleanup::get_instance();
		$filename = 'delete-venue-osm-roadmap-15-800-400.png';
		$path     = $this->create_map_file( $filename, time() - 600 );

		$result = $cleanup->sweep( false );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['scanned'] );
		$this->assertSame( 1, $result['orphans'] );
		$this->assertSame( 1, $result['deleted'] );
		$this->assertGreaterThan( 0, $result['bytes_reclaimed'] );
		$this->assertContains( $filename, $result['files'] );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Tests sweep preserves files currently referenced by a venue.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_preserves_in_use_file(): void {
		$cleanup  = Cleanup::get_instance();
		$filename = 'inuse-venue-osm-roadmap-15-800-400.png';
		$path     = $this->create_map_file( $filename, time() - 600 );

		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$meta    = array(
			'osm' => array(
				'15x800x400xroadmap' => array(
					'url'    => 'https://example.com/wp-content/uploads/gatherpress/static-maps/' . $filename,
					'hash'   => 'hash1',
					'zoom'   => 15,
					'width'  => 800,
					'height' => 400,
				),
			),
		);
		update_post_meta( $post_id, Map::META_KEY, $meta );

		$result = $cleanup->sweep( false );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 1, $result['scanned'] );
		$this->assertSame( 0, $result['orphans'] );
		$this->assertSame( 0, $result['deleted'] );
		$this->assertFileExists( $path );
	}

	/**
	 * Tests sweep limits unlinks per run and handles sequential sweeps.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_limit_and_sequential_runs(): void {
		$cleanup = Cleanup::get_instance();

		$this->create_map_file( 'orphan-a-osm-roadmap-15-800-400.png', time() - 600 );
		$this->create_map_file( 'orphan-b-osm-roadmap-15-800-400.png', time() - 600 );
		$this->create_map_file( 'orphan-c-osm-roadmap-15-800-400.png', time() - 600 );

		// Run with cap of 2.
		$first_run = $cleanup->sweep( false, 2 );

		$this->assertTrue( $first_run['success'] );
		$this->assertSame( 2, $first_run['deleted'] );
		$this->assertTrue( $first_run['hit_limit'] );

		// Second run picks up remaining file.
		$second_run = $cleanup->sweep( false, 2 );

		$this->assertTrue( $second_run['success'] );
		$this->assertSame( 1, $second_run['deleted'] );
		$this->assertFalse( $second_run['hit_limit'] );
	}

	/**
	 * Tests get_uploads_dir returns null on upload directory error.
	 *
	 * @since TBD
	 *
	 * @covers ::get_uploads_dir
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_get_uploads_dir_and_sweep_null_on_error(): void {
		$filter = static function ( $dirs ) {
			$dirs['error'] = 'Simulated upload dir failure.';
			return $dirs;
		};
		add_filter( 'upload_dir', $filter );

		$cleanup = Cleanup::get_instance();
		$this->assertNull( $cleanup->get_uploads_dir() );

		$result = $cleanup->sweep();
		remove_filter( 'upload_dir', $filter );

		$this->assertSame( 0, $result['scanned'] );
		$this->assertSame( 0, $result['orphans'] );
	}

	/**
	 * Tests get_in_use_basenames returns empty array when no post types support venue.
	 *
	 * @since TBD
	 *
	 * @covers ::get_in_use_basenames
	 *
	 * @return void
	 */
	public function test_get_in_use_basenames_empty_when_no_support(): void {
		$types = get_post_types_by_support( Venue::SUPPORT );
		foreach ( $types as $type ) {
			remove_post_type_support( $type, Venue::SUPPORT );
		}

		$basenames = Cleanup::get_instance()->get_in_use_basenames();

		foreach ( $types as $type ) {
			add_post_type_support( $type, Venue::SUPPORT );
		}

		$this->assertSame( array(), $basenames );
	}

	/**
	 * Tests get_in_use_basenames pagination across multiple pages.
	 *
	 * @since TBD
	 *
	 * @covers ::get_in_use_basenames
	 *
	 * @return void
	 */
	public function test_get_in_use_basenames_multipage_scan(): void {
		$batch_filter = static fn() => 1;
		add_filter( 'gatherpress_venue_map_gc_scan_batch_size', $batch_filter );

		$venue1   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$venue2   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$base_url = 'https://example.com/wp-content/uploads/gatherpress/static-maps/';

		add_post_meta( $venue1, Map::META_KEY, array( 'url' => $base_url . 'batch-1-osm-roadmap-15-800-400.png' ) );
		add_post_meta( $venue2, Map::META_KEY, array( 'url' => $base_url . 'batch-2-osm-roadmap-15-800-400.png' ) );

		$in_use = Cleanup::get_instance()->get_in_use_basenames();

		remove_filter( 'gatherpress_venue_map_gc_scan_batch_size', $batch_filter );

		$this->assertArrayHasKey( 'batch-1-osm-roadmap-15-800-400.png', $in_use );
		$this->assertArrayHasKey( 'batch-2-osm-roadmap-15-800-400.png', $in_use );
	}

	/**
	 * Tests is_candidate and unlink_file helpers.
	 *
	 * @since TBD
	 *
	 * @covers ::is_candidate
	 * @covers ::unlink_file
	 *
	 * @return void
	 */
	public function test_is_candidate_and_unlink_file_helpers(): void {
		$cleanup = Cleanup::get_instance();
		$dir     = $cleanup->get_uploads_dir();
		$now     = time();

		$old_file   = 'candidate-old-osm-roadmap-15-800-400.png';
		$fresh_file = 'candidate-fresh-osm-roadmap-15-800-400.png';

		$path_old = $this->create_map_file( $old_file, $now - 600 );
		$this->create_map_file( $fresh_file, $now - 10 );

		$this->assertFalse( $cleanup->is_candidate( $old_file, $dir, array( $old_file => true ), $now ) );
		$this->assertFalse( $cleanup->is_candidate( $fresh_file, $dir, array(), $now ) );
		$this->assertTrue( $cleanup->is_candidate( $old_file, $dir, array(), $now ) );
		$this->assertFalse( $cleanup->is_candidate( 'nonexistent.png', $dir, array(), $now ) );

		$this->assertFileExists( $path_old );
		$this->assertTrue( $cleanup->unlink_file( $path_old ) );
		$this->assertFileDoesNotExist( $path_old );
	}

	/**
	 * Tests sweep returns early when directory handle cannot be opened.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_returns_early_when_dir_unreadable(): void {
		$cleanup = Cleanup::get_instance();
		$dir     = $cleanup->get_uploads_dir();

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static fn() => true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		chmod( $dir, 0000 );
		$result = $cleanup->sweep();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		chmod( $dir, 0755 );
		restore_error_handler();

		$this->assertSame( 0, $result['scanned'] );
	}

	/**
	 * Tests sweep ignores subdirectories inside static-maps folder.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_skips_subdirectories(): void {
		$cleanup = Cleanup::get_instance();
		$dir     = $cleanup->get_uploads_dir();
		$subdir  = $dir . '/test-subdir';

		wp_mkdir_p( $subdir );
		$result = $cleanup->sweep();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $subdir );

		$this->assertNotContains( 'test-subdir', $result['files'] );
	}

	/**
	 * Tests sweep dry run hit_limit detection.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_dry_run_hits_limit(): void {
		$cleanup = Cleanup::get_instance();
		$this->create_map_file( 'dryrun-limit-1-osm-roadmap-15-800-400.png', time() - 600 );
		$this->create_map_file( 'dryrun-limit-2-osm-roadmap-15-800-400.png', time() - 600 );

		$result = $cleanup->sweep( true, 1 );

		$this->assertTrue( $result['hit_limit'] );
		$this->assertCount( 1, $result['files'] );
	}
}
