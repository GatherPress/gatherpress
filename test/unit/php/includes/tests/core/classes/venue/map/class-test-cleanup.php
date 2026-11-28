<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Cleanup.
 *
 * @package GatherPress\Core\Venue\Map
 * @since TBD
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Settings;
use GatherPress\Core\Venue\Map\Cleanup;
use GatherPress\Core\Venue\Map\Map;
use GatherPress\Core\Venue\Map\Saved_Image;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

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
			'vondelpark-amsterdam-52.362100_4.882900-osm-roadmap-16-600-300.png',
			'vondelpark-amsterdam-52.362100_4.882900-osm-roadmap-16-600-300@2x.png',
			'venue-37.774929_-122.419416-google-satellite-12-600-300.png',
			'venue-37.774929_-122.419416-google-satellite-12-600-300@2x.png',
			'vondelpark-amsterdam-52.362100_4.882900-osm-roadmap-16-600-300-fa55badd.png',
			'vondelpark-amsterdam-52.362100_4.882900-osm-roadmap-16-600-300-fa55badd@2x.png',
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
			'venue-osm-roadmap-15-800-400-1a2b3c4z.png',
			'venue-osm-roadmap-15-800-400-1a2b3c4.png',
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

		$this->assertFalse( $result['success'], 'An uploads error should fail the sweep.' );
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
		add_filter( 'gatherpress_venue_map_cleanup_scan_batch_size', $batch_filter );

		$venue1   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$venue2   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$base_url = 'https://example.com/wp-content/uploads/gatherpress/static-maps/';

		add_post_meta( $venue1, Map::META_KEY, array( 'url' => $base_url . 'batch-1-osm-roadmap-15-800-400.png' ) );
		add_post_meta( $venue2, Map::META_KEY, array( 'url' => $base_url . 'batch-2-osm-roadmap-15-800-400.png' ) );

		$in_use = Cleanup::get_instance()->get_in_use_basenames();

		remove_filter( 'gatherpress_venue_map_cleanup_scan_batch_size', $batch_filter );

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
	 * Tests sweep fails when the directory exists but cannot be opened.
	 *
	 * Uses a stream wrapper that reports a directory and refuses to open it, because
	 * file permissions do not stop a test suite running as root.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_fails_when_dir_cannot_be_opened(): void {
		$wrapper = new class() {
			/**
			 * Stream context, set by PHP.
			 *
			 * @var resource|null
			 */
			public $context;

			/**
			 * Reports every path as a directory.
			 *
			 * @return array<string, int>
			 */
			public function url_stat(): array {
				return array( 'mode' => 040755 );
			}

			/**
			 * Refuses to open any directory.
			 *
			 * @return bool
			 */
			public function dir_opendir(): bool {
				return false;
			}
		};
		$filter  = static function ( array $dirs ): array {
			$dirs['basedir'] = 'gpunreadable://uploads';

			return $dirs;
		};

		stream_wrapper_register( 'gpunreadable', get_class( $wrapper ) );
		add_filter( 'upload_dir', $filter );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static fn() => true );

		try {
			$result = Cleanup::get_instance()->sweep();
		} finally {
			restore_error_handler();
			remove_filter( 'upload_dir', $filter );
			stream_wrapper_unregister( 'gpunreadable' );
		}

		$this->assertFalse( $result['success'] );
		$this->assertSame( 0, $result['scanned'] );
	}

	/**
	 * Tests a missing static-maps directory is a clean, successful sweep.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_succeeds_when_dir_is_missing(): void {
		$filter = static function ( array $dirs ): array {
			$dirs['basedir'] = $dirs['basedir'] . '/gatherpress-missing-' . wp_generate_password( 8, false );

			return $dirs;
		};
		add_filter( 'upload_dir', $filter );
		$result = Cleanup::get_instance()->sweep();
		remove_filter( 'upload_dir', $filter );

		$this->assertTrue( $result['success'] );
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
		$subdir  = $dir . '/dir-15-800-400.png';

		wp_mkdir_p( $subdir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		touch( $subdir, time() - 600 );

		try {
			$result = $cleanup->sweep();
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			rmdir( $subdir );
		}

		$this->assertSame( 0, $result['scanned'] );
		$this->assertSame( 0, $result['orphans'] );
	}

	/**
	 * Tests sweep skips non-static-map files in directory.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_skips_invalid_filenames(): void {
		$cleanup = Cleanup::get_instance();
		$dir     = $cleanup->get_uploads_dir();
		$file    = $dir . '/invalid-file.txt';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, 'test' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		touch( $file, time() - 600 );
		$result = $cleanup->sweep( true );
		wp_delete_file( $file );

		$this->assertSame( 0, $result['scanned'] );
		$this->assertSame( 0, $result['orphans'] );
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

	/**
	 * Tests a file that cannot be deleted is counted as failed, not deleted.
	 *
	 * @since TBD
	 *
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_counts_failed_deletions(): void {
		$path = $this->create_map_file( 'undeletable-osm-roadmap-15-800-400.png', time() - 600 );

		add_filter( 'wp_delete_file', '__return_empty_string' );
		$result = Cleanup::get_instance()->sweep( false );
		remove_filter( 'wp_delete_file', '__return_empty_string' );

		$this->assertFileExists( $path );
		$this->assertSame( 1, $result['orphans'] );
		$this->assertSame( 1, $result['failed'] );
		$this->assertSame( 0, $result['deleted'] );
		$this->assertSame( array(), $result['files'], 'A file that was not deleted is not listed.' );
	}

	/**
	 * Tests the constructor wires the schedule and the cron callback.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Cleanup::get_instance();

		Utility::invoke_hidden_method( $instance, '__construct' );

		$this->assertSame( 10, has_action( 'init', array( $instance, 'schedule' ) ) );
		$this->assertSame( 10, has_action( Cleanup::CRON_ACTION, array( $instance, 'run_scheduled' ) ) );
	}

	/**
	 * Tests the cleanup follows the frequency setting.
	 *
	 * @since TBD
	 *
	 * @covers ::schedule
	 *
	 * @return void
	 */
	public function test_schedule_follows_setting(): void {
		$settings = Settings::get_instance();
		$cleanup  = Cleanup::get_instance();

		wp_clear_scheduled_hook( Cleanup::CRON_ACTION );

		$cleanup->schedule();
		$first = wp_next_scheduled( Cleanup::CRON_ACTION );
		$this->assertSame( 'weekly', wp_get_schedule( Cleanup::CRON_ACTION ), 'Weekly is the default.' );

		$cleanup->schedule();
		$this->assertSame( $first, wp_next_scheduled( Cleanup::CRON_ACTION ), 'An unchanged setting keeps the event.' );

		$settings->set( 'static_map_cleanup', 'daily' );
		$cleanup->schedule();
		$this->assertSame( 'daily', wp_get_schedule( Cleanup::CRON_ACTION ) );

		$settings->set( 'static_map_cleanup', 'never' );
		$cleanup->schedule();
		$this->assertFalse( wp_next_scheduled( Cleanup::CRON_ACTION ), 'Never should remove the event.' );

		$cleanup->schedule();
		$this->assertFalse( wp_next_scheduled( Cleanup::CRON_ACTION ), 'Never should stay unscheduled.' );

		$settings->set( 'static_map_cleanup', 'weekly' );
		wp_clear_scheduled_hook( Cleanup::CRON_ACTION );
	}

	/**
	 * Tests the scheduled run deletes orphaned images.
	 *
	 * @since TBD
	 *
	 * @covers ::run_scheduled
	 *
	 * @return void
	 */
	public function test_run_scheduled_deletes_orphans(): void {
		$path = $this->create_map_file( 'scheduled-osm-roadmap-15-800-400.png', time() - 600 );

		Cleanup::get_instance()->run_scheduled();

		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Tests a replaced image waiting in a delayed delete event is kept until that event runs.
	 *
	 * @since TBD
	 *
	 * @covers ::get_in_use_basenames
	 * @covers ::sweep
	 *
	 * @return void
	 */
	public function test_sweep_keeps_images_queued_for_delayed_delete(): void {
		$name = 'replaced-0.000000_0.000000-osm-roadmap-15-800-400-1a2b3c4d.png';
		$path = $this->create_map_file( $name, time() - 600 );
		$url  = Saved_Image::url( $name );
		$when = time() + WEEK_IN_SECONDS;

		wp_schedule_single_event( $when, Saved_Image::DELETE_CRON_ACTION, array( array( $url ), $when ) );

		try {
			$this->assertArrayHasKey( $name, Cleanup::get_instance()->get_in_use_basenames() );

			$result = Cleanup::get_instance()->sweep( false );

			$this->assertFileExists( $path );
			$this->assertSame( 0, $result['orphans'] );
		} finally {
			wp_clear_scheduled_hook( Saved_Image::DELETE_CRON_ACTION, array( array( $url ), $when ) );
		}

		$result = Cleanup::get_instance()->sweep( false );

		$this->assertFileDoesNotExist( $path, 'Once the event is gone, the image is an orphan.' );
		$this->assertSame( 1, $result['deleted'] );
	}

	/**
	 * Tests queued images still count as in use when no post type supports venues.
	 *
	 * @since TBD
	 *
	 * @covers ::get_in_use_basenames
	 *
	 * @return void
	 */
	public function test_get_in_use_basenames_keeps_queued_without_venue_support(): void {
		$name  = 'queued-0.000000_0.000000-osm-roadmap-15-800-400-1a2b3c4d.png';
		$url   = Saved_Image::url( $name );
		$when  = time() + WEEK_IN_SECONDS;
		$types = get_post_types_by_support( Venue::SUPPORT );

		wp_schedule_single_event( $when, Saved_Image::DELETE_CRON_ACTION, array( array( $url ), $when ) );

		foreach ( $types as $type ) {
			remove_post_type_support( $type, Venue::SUPPORT );
		}

		try {
			$basenames = Cleanup::get_instance()->get_in_use_basenames();
		} finally {
			foreach ( $types as $type ) {
				add_post_type_support( $type, Venue::SUPPORT );
			}
			wp_clear_scheduled_hook( Saved_Image::DELETE_CRON_ACTION, array( array( $url ), $when ) );
		}

		$this->assertSame( array( $name => true ), $basenames );
	}
}
