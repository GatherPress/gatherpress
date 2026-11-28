<?php
/**
 * Unit tests for GatherPress\Core\Commands\Venue_Map_Cli.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Commands;

use GatherPress\Core\Commands\Venue_Map_Cli;
use GatherPress\Core\Venue\Map\Cleanup;
use GatherPress\Core\Venue\Map\Map;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Venue_Map_Cli.
 *
 * @coversDefaultClass \GatherPress\Core\Commands\Venue_Map_Cli
 */
class Test_Venue_Map_Cli extends Base {

	/**
	 * Static maps uploads directory.
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
	 * Tear down files and transients after each test.
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
	 * Coverage for default dry-run cleanup command.
	 *
	 * @since TBD
	 *
	 * @covers ::cleanup
	 *
	 * @return void
	 */
	public function test_cleanup_dry_run_lists_summary(): void {
		$cli      = new Venue_Map_Cli();
		$filename = 'cli-dryrun-osm-roadmap-15-800-400.png';
		$path     = $this->create_map_file( $filename, time() - 600 );

		$output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array( array(), array() )
		);

		$this->assertStringContainsString( 'Dry run complete. Found 1 orphaned file(s)', $output );
		$this->assertStringContainsString( 'Run with --apply to delete.', $output );
		$this->assertFileExists( $path );
	}

	/**
	 * Coverage for cleanup command with --verbose flag.
	 *
	 * @since TBD
	 *
	 * @covers ::cleanup
	 *
	 * @return void
	 */
	public function test_cleanup_verbose_outputs_candidates(): void {
		$cli      = new Venue_Map_Cli();
		$filename = 'cli-verbose-osm-roadmap-15-800-400.png';
		$this->create_map_file( $filename, time() - 600 );

		$output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array( array(), array( 'verbose' => true ) )
		);

		$this->assertStringContainsString( sprintf( '[orphan] %s', $filename ), $output );
	}

	/**
	 * Coverage for cleanup command with --apply flag.
	 *
	 * @since TBD
	 *
	 * @covers ::cleanup
	 *
	 * @return void
	 */
	public function test_cleanup_apply_deletes_files(): void {
		$cli      = new Venue_Map_Cli();
		$filename = 'cli-apply-osm-roadmap-15-800-400.png';
		$path     = $this->create_map_file( $filename, time() - 600 );

		$output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array(
				array(),
				array(
					'apply'   => true,
					'verbose' => true,
				),
			)
		);

		$this->assertStringContainsString( sprintf( '[deleted] %s', $filename ), $output );
		$this->assertStringContainsString( 'Cleanup complete. Deleted 1 file(s)', $output );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Coverage for cleanup command when locked and with --force flag.
	 *
	 * @since TBD
	 *
	 * @covers ::cleanup
	 *
	 * @return void
	 */
	public function test_cleanup_locked_and_force(): void {
		$cli = new Venue_Map_Cli();
		set_transient( Cleanup::LOCK_TRANSIENT, time(), Cleanup::LOCK_TTL );

		$locked_output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array( array(), array() )
		);

		$this->assertStringContainsString( 'A cleanup sweep is currently in progress.', $locked_output );

		$force_output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array( array(), array( 'force' => true ) )
		);

		$this->assertStringContainsString( 'Dry run complete.', $force_output );
	}

	/**
	 * Coverage for cleanup command outputting warning when hit_limit is true.
	 *
	 * @since TBD
	 *
	 * @covers ::cleanup
	 *
	 * @return void
	 */
	public function test_cleanup_hit_limit_warning(): void {
		$cli = new Venue_Map_Cli();
		$this->create_map_file( 'cli-limit-1-osm-roadmap-15-800-400.png', time() - 600 );
		$this->create_map_file( 'cli-limit-2-osm-roadmap-15-800-400.png', time() - 600 );

		$output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array(
				array(),
				array(
					'limit' => 1,
					'apply' => true,
				),
			)
		);

		$this->assertStringContainsString( 'Reached per-run deletion limit.', $output );
	}

	/**
	 * Coverage for flag values that must not turn on deletion or a bogus limit.
	 *
	 * @since TBD
	 *
	 * @covers ::cleanup
	 *
	 * @return void
	 */
	public function test_cleanup_parses_flags_strictly(): void {
		$cli  = new Venue_Map_Cli();
		$path = $this->create_map_file( 'cli-strict-osm-roadmap-15-800-400.png', time() - 600 );

		$output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array( array(), array( 'apply' => 'false' ) )
		);

		$this->assertStringContainsString( 'Dry run complete.', $output );
		$this->assertFileExists( $path );

		foreach ( array( 'abc', '0', '-1', '1.5' ) as $limit ) {
			$output = Utility::buffer_and_return(
				array( $cli, 'cleanup' ),
				array( array(), array( 'limit' => $limit ) )
			);

			$this->assertStringContainsString( 'The --limit value must be a positive whole number.', $output, $limit );
			$this->assertStringNotContainsString( 'Dry run complete.', $output, $limit );
		}
	}

	/**
	 * Coverage for the dry-run message when the limit is reached.
	 *
	 * @since TBD
	 *
	 * @covers ::cleanup
	 *
	 * @return void
	 */
	public function test_cleanup_dry_run_hit_limit_warning(): void {
		$cli = new Venue_Map_Cli();
		$this->create_map_file( 'cli-drylimit-1-osm-roadmap-15-800-400.png', time() - 600 );
		$this->create_map_file( 'cli-drylimit-2-osm-roadmap-15-800-400.png', time() - 600 );

		$output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array( array(), array( 'limit' => '1' ) )
		);

		$this->assertStringContainsString( 'Stopped at the --limit value', $output );
		$this->assertStringNotContainsString( 'Run the command again', $output );
	}

	/**
	 * Coverage for files that could not be deleted and for an unreadable uploads directory.
	 *
	 * @since TBD
	 *
	 * @covers ::cleanup
	 *
	 * @return void
	 */
	public function test_cleanup_reports_failures(): void {
		$cli = new Venue_Map_Cli();
		$this->create_map_file( 'cli-undeletable-osm-roadmap-15-800-400.png', time() - 600 );

		add_filter( 'wp_delete_file', '__return_empty_string' );
		$output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array(
				array(),
				array(
					'apply'   => true,
					'verbose' => true,
				),
			)
		);
		remove_filter( 'wp_delete_file', '__return_empty_string' );

		$this->assertStringContainsString( 'Could not delete 1 file(s).', $output );
		$this->assertStringContainsString( '[failed] cli-undeletable-osm-roadmap-15-800-400.png', $output );
		$this->assertStringNotContainsString( '[deleted]', $output );

		$filter = static function ( array $dirs ): array {
			$dirs['error'] = 'Simulated upload dir failure.';

			return $dirs;
		};
		add_filter( 'upload_dir', $filter );
		$output = Utility::buffer_and_return(
			array( $cli, 'cleanup' ),
			array( array(), array() )
		);
		remove_filter( 'upload_dir', $filter );

		$this->assertStringContainsString( 'The static maps directory could not be read.', $output );
		$this->assertStringNotContainsString( 'Dry run complete.', $output );
	}
}
