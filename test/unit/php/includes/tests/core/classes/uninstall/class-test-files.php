<?php
/**
 * Unit tests for the generated file uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Uninstall\Files;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Venue\Map\Map;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Files.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Files
 */
class Test_Files extends Base {

	/**
	 * Reset the opt-in map between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->remove_directory( $this->plugin_directory() );

		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();

		parent::tearDown();
	}

	/**
	 * The plugin's directory under uploads.
	 *
	 * @since 0.36.0
	 *
	 * @return string Absolute path.
	 */
	protected function plugin_directory(): string {
		$uploads = wp_get_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . Files::DIRECTORY;
	}

	/**
	 * The directory the static map writer uses.
	 *
	 * @since 0.36.0
	 *
	 * @return string Absolute path.
	 */
	protected function map_directory(): string {
		$uploads = wp_get_upload_dir();

		return trailingslashit( $uploads['basedir'] ) . Map::UPLOADS_SUBDIR;
	}

	/**
	 * Write a file, creating the directories above it.
	 *
	 * @since 0.36.0
	 *
	 * @param string $path Absolute path to the file.
	 *
	 * @return void
	 */
	protected function write_file( string $path ): void {
		wp_mkdir_p( dirname( $path ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $path, 'png' );
	}

	/**
	 * Remove a directory tree left over from a test.
	 *
	 * @since 0.36.0
	 *
	 * @param string $directory Absolute path to the directory.
	 *
	 * @return void
	 */
	protected function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir
		foreach ( array_diff( (array) scandir( $directory ), array( '.', '..' ) ) as $entry ) {
			$path = trailingslashit( $directory ) . $entry;

			if ( is_dir( $path ) ) {
				$this->remove_directory( $path );

				continue;
			}

			wp_delete_file( $path );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $directory );
	}

	/**
	 * Every writer keeps its files inside the directory this task removes.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_owns_every_generated_file(): void {
		$this->assertStringStartsWith(
			Files::DIRECTORY . '/',
			Map::UPLOADS_SUBDIR,
			'A writer that puts files outside this directory leaves them behind at uninstall.'
		);
	}

	/**
	 * The task stays off until it is opted in to.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_does_not_apply_by_default(): void {
		$this->assertFalse(
			( new Files() )->applies(),
			'Deleting generated files must wait for an explicit opt-in.'
		);
	}

	/**
	 * The task applies once its preference is on.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_when_opted_in(): void {
		Preferences::save( array( Preferences::TASK_FILES => true ) );

		$this->assertTrue( ( new Files() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * Generated files survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_files_alone_without_opt_in(): void {
		$file = trailingslashit( $this->map_directory() ) . 'venue-osm-map-15-600-400.png';

		$this->write_file( $file );

		( new Files() )->run();

		$this->assertFileExists( $file, 'A task that was never opted in to must remove nothing.' );
	}

	/**
	 * The plugin's whole uploads directory goes once opted in to.
	 *
	 * @covers ::uninstall_site
	 * @covers ::delete_directory
	 * @covers ::entries
	 *
	 * @return void
	 */
	public function test_removes_the_plugin_directory(): void {
		Preferences::save( array( Preferences::TASK_FILES => true ) );

		$map    = trailingslashit( $this->map_directory() ) . 'venue-osm-map-15-600-400.png';
		$nested = trailingslashit( $this->map_directory() ) . 'retina/venue-osm-map-15-600-400@2x.png';
		$other  = trailingslashit( $this->plugin_directory() ) . 'something-else/generated.txt';

		$this->write_file( $map );
		$this->write_file( $nested );
		$this->write_file( $other );

		( new Files() )->run();

		$this->assertFileDoesNotExist( $map, 'The generated image is deleted.' );
		$this->assertFileDoesNotExist(
			$other,
			'Anything else the plugin generates lives here too, and goes on the same switch.'
		);
		$this->assertDirectoryDoesNotExist(
			$this->plugin_directory(),
			'The directory goes with its contents.'
		);
	}

	/**
	 * Other people's uploads are left alone.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_the_rest_of_uploads_alone(): void {
		Preferences::save( array( Preferences::TASK_FILES => true ) );

		$uploads   = wp_get_upload_dir();
		$elsewhere = trailingslashit( $uploads['basedir'] ) . 'not-ours.txt';

		$this->write_file( trailingslashit( $this->map_directory() ) . 'venue-osm-map.png' );
		$this->write_file( $elsewhere );

		( new Files() )->run();

		$this->assertFileExists( $elsewhere, 'Only the plugin\'s own directory is in scope.' );

		wp_delete_file( $elsewhere );
	}

	/**
	 * Nothing happens when the directory was never created.
	 *
	 * @covers ::uninstall_site
	 * @covers ::delete_directory
	 *
	 * @return void
	 */
	public function test_tolerates_a_missing_directory(): void {
		Preferences::save( array( Preferences::TASK_FILES => true ) );

		$this->remove_directory( $this->plugin_directory() );

		( new Files() )->run();

		$this->assertDirectoryDoesNotExist(
			$this->plugin_directory(),
			'A site that never generated a file has nothing to delete.'
		);
	}

	/**
	 * An uploads directory that cannot be resolved is left alone.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_stops_when_uploads_reports_an_error(): void {
		Preferences::save( array( Preferences::TASK_FILES => true ) );

		$file = trailingslashit( $this->map_directory() ) . 'venue-osm-map-15-600-400.png';

		$this->write_file( $file );

		$filter = static function ( array $uploads ): array {
			$uploads['error'] = 'Unable to create directory.';

			return $uploads;
		};

		add_filter( 'upload_dir', $filter );
		( new Files() )->run();
		remove_filter( 'upload_dir', $filter );

		$this->assertFileExists(
			$file,
			'Guessing at a path when uploads cannot answer would delete the wrong thing.'
		);
	}

	/**
	 * A path that is not a directory is not scanned.
	 *
	 * @covers ::entries
	 *
	 * @return void
	 */
	public function test_entries_is_empty_for_a_missing_directory(): void {
		$this->assertSame(
			array(),
			Utility::invoke_hidden_method(
				new Files(),
				'entries',
				array( trailingslashit( $this->plugin_directory() ) . 'not-here' )
			),
			'A directory that is not there has no entries.'
		);
	}
}
