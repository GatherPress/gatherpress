<?php
/**
 * Class handles unit tests for the PHPUnit bootstrap's build-freshness guard.
 *
 * The guard itself lives at `test/unit/php/build-freshness.php` and runs before
 * WordPress loads, so it cannot be exercised end to end from inside a test,
 * because it calls `exit( 1 )`. Its two directory-walking helpers are pure, though, and
 * they are where the "is this build complete" decision is actually made, so
 * they are what these tests drive against real temporary directories.
 *
 * @package GatherPress
 * @subpackage Tests
 * @since 0.36.0
 */

namespace GatherPress\Tests;

use ReflectionFunction;

// The guard walks real directories, so the fixtures have to build real ones.
// WordPress is loaded here, but `WP_Filesystem` writes through a credentialed
// abstraction meant for plugin-managed paths, not for a scratch tree under
// `sys_get_temp_dir()` that this test creates and removes within one method.
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_touch
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_unlink

/**
 * Class Test_Build_Freshness.
 *
 * @since 0.36.0
 */
class Test_Build_Freshness extends Base {

	/**
	 * Root of the temporary tree this test case builds, or '' when none exists.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	protected string $tree = '';

	/**
	 * Remove the temporary tree between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		if ( '' !== $this->tree && is_dir( $this->tree ) ) {
			array_map( 'unlink', (array) glob( $this->tree . '/nested/*' ) );
			array_map( 'unlink', (array) glob( $this->tree . '/*.txt' ) );
			rmdir( $this->tree . '/nested' );
			rmdir( $this->tree );
		}

		$this->tree = '';

		parent::tearDown();
	}

	/**
	 * Build a two-level directory holding files with the given mtimes.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, int> $files Relative file name mapped to its mtime.
	 *
	 * @return string Absolute path to the created directory.
	 */
	protected function make_tree( array $files ): string {
		$this->tree = sys_get_temp_dir() . '/gatherpress-freshness-' . uniqid();

		mkdir( $this->tree );
		mkdir( $this->tree . '/nested' );

		foreach ( $files as $name => $mtime ) {
			$path = $this->tree . '/' . $name;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $path, 'x' );
			touch( $path, $mtime );
		}

		// Both directories are stamped older than every file so the two
		// readings differ only in what they say about the files themselves.
		touch( $this->tree . '/nested', 1000 );
		touch( $this->tree, 1000 );

		return $this->tree;
	}

	/**
	 * The partial-stale build the newest-versus-newest comparison certified:
	 * one freshly emitted artifact alongside a stale one. The oldest-file
	 * reading is what makes that state fail.
	 *
	 * @return void
	 */
	public function test_oldest_file_mtime_reports_the_stale_artifact(): void {
		$tree = $this->make_tree(
			array(
				'fresh.txt'        => 3000,
				'nested/stale.txt' => 1500,
			)
		);

		$this->assertSame( 1500, gatherpress_oldest_file_mtime( $tree ) );
		$this->assertSame(
			3000,
			gatherpress_newest_mtime( $tree ),
			'The newest reading is what a partial build can satisfy on its own.'
		);
	}

	/**
	 * Directories are excluded from the oldest reading. A rebuild that
	 * rewrites file contents without changing the file list leaves directory
	 * timestamps behind, and counting them would fail the guard for no reason.
	 *
	 * @return void
	 */
	public function test_oldest_file_mtime_ignores_directories(): void {
		$tree = $this->make_tree( array( 'nested/only.txt' => 3000 ) );

		// `make_tree()` stamps both directories at 1000, older than the only
		// file, so a reading that counted them would return 1000 here.
		$this->assertSame( 3000, gatherpress_oldest_file_mtime( $tree ) );
	}

	/**
	 * The skip fragment excludes the coverage report, which
	 * `npm run test:unit:php` writes into `build/` and which is therefore the
	 * one tree in there that says nothing about whether the bundle is current.
	 *
	 * @return void
	 */
	public function test_oldest_file_mtime_honors_the_skip_fragment(): void {
		$tree = $this->make_tree(
			array(
				'artifact.txt'        => 3000,
				'nested/coverage.txt' => 1000,
			)
		);

		$this->assertSame( 3000, gatherpress_oldest_file_mtime( $tree, '/nested' ) );
		$this->assertSame( 1000, gatherpress_oldest_file_mtime( $tree ) );
	}

	/**
	 * The newest reading takes no skip fragment, and excludes nothing.
	 *
	 * The exclusion exists for one tree, `build/coverage-report`, and that
	 * tree only ever appears on the build side of the comparison. A skip
	 * parameter on this half would be a branch nothing could reach, so the
	 * signature carries one argument and a `coverage`-shaped path under the
	 * source tree still counts toward the reading.
	 *
	 * @return void
	 */
	public function test_newest_mtime_takes_no_skip_fragment(): void {
		$tree = $this->make_tree(
			array(
				'artifact.txt'        => 1000,
				'nested/coverage.txt' => 3000,
			)
		);

		$this->assertSame(
			1,
			( new ReflectionFunction( 'gatherpress_newest_mtime' ) )->getNumberOfParameters(),
			'Failed to assert that the newest reading takes the directory alone.'
		);
		$this->assertSame(
			3000,
			gatherpress_newest_mtime( $tree ),
			'Failed to assert that the newest reading counted a coverage-shaped path.'
		);
	}

	/**
	 * A directory holding no files at all reads as 0, which the guard treats
	 * the same way it treats a missing build directory.
	 *
	 * @return void
	 */
	public function test_oldest_file_mtime_returns_zero_for_a_fileless_directory(): void {
		$tree = $this->make_tree( array() );

		$this->assertSame( 0, gatherpress_oldest_file_mtime( $tree ) );
	}
}
