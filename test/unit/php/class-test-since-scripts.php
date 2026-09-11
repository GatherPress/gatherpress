<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName
/**
 * Tests the @since automation scripts.
 *
 * @package GatherPress
 * @since TBD
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.unlink_unlink
 */

use GatherPress\Tests\Base;

require_once dirname( __DIR__, 3 ) . '/.github/scripts/resolve-since.php';
require_once dirname( __DIR__, 3 ) . '/.github/scripts/check-since.php';

/**
 * Tests the @since automation scripts.
 */
class GatherPress_Test_Since_Scripts extends Base {

	/**
	 * Tests stable version extraction.
	 *
	 * @return void
	 */
	public function test_stable_version(): void {
		$this->assertSame( '0.36.0', gatherpress_since_stable_version( '0.36.0' ) );
		$this->assertSame( '0.36.0', gatherpress_since_stable_version( '0.36.0-alpha.2' ) );
		$this->assertSame( '0.36.0', gatherpress_since_stable_version( '0.36.0-beta.1' ) );
		$this->assertSame( '0.36.0', gatherpress_since_stable_version( '0.36.0-rc.3' ) );
	}

	/**
	 * Tests invalid version handling.
	 *
	 * @return void
	 */
	public function test_invalid_version(): void {
		$this->expectException( InvalidArgumentException::class );
		gatherpress_since_stable_version( '0.36.0-dev' );
	}

	/**
	 * Tests replacing tags and leaving resolved tags unchanged.
	 *
	 * @return void
	 */
	public function test_resolve_since_file(): void {
		$file = wp_tempnam( 'since-test' );
		file_put_contents( $file, "@since TBD\n@since 0.35.0\n" );

		$this->assertTrue( gatherpress_resolve_since_file( $file, '0.36.0' ) );
		$this->assertSame( "@since 0.36.0\n@since 0.35.0\n", file_get_contents( $file ) );
		$this->assertFalse( gatherpress_resolve_since_file( $file, '0.36.0' ) );

		unlink( $file );
	}

	/**
	 * Tests that only supported source files are scanned.
	 *
	 * @return void
	 */
	public function test_resolve_since_directories(): void {
		$directory = trailingslashit( sys_get_temp_dir() . '/gatherpress-since-' . uniqid() );
		mkdir( $directory );
		file_put_contents( $directory . 'source.php', '@since TBD' );
		file_put_contents( $directory . 'source.js', '@since TBD' );
		file_put_contents( $directory . 'source.txt', '@since TBD' );

		$this->assertSame( 2, gatherpress_resolve_since( array( $directory, $directory . 'missing' ), '0.36.0' ) );
		$this->assertSame( '@since 0.36.0', file_get_contents( $directory . 'source.php' ) );
		$this->assertSame( '@since 0.36.0', file_get_contents( $directory . 'source.js' ) );
		$this->assertSame( '@since TBD', file_get_contents( $directory . 'source.txt' ) );

		unlink( $directory . 'source.php' );
		unlink( $directory . 'source.js' );
		unlink( $directory . 'source.txt' );
		rmdir( $directory );
	}

	/**
	 * Tests added-line validation.
	 *
	 * @return void
	 */
	public function test_since_diff_violations(): void {
		$diff = <<<'DIFF'
diff --git a/src/new.js b/src/new.js
--- a/src/new.js
+++ b/src/new.js
@@ -1,2 +1,2 @@
- * @since 0.35.0
+ * @since TBD
diff --git a/includes/new.php b/includes/new.php
--- a/includes/new.php
+++ b/includes/new.php
@@ -1,2 +1,3 @@
 /**
+ * @since 0.36.0
DIFF;

		$this->assertSame(
			array( 'includes/new.php: * @since 0.36.0' ),
			gatherpress_since_diff_violations( $diff )
		);
	}

	/**
	 * Tests that replacing an existing tag counts as a correction.
	 *
	 * @return void
	 */
	public function test_since_diff_allows_correction(): void {
		$diff = <<<'DIFF'
diff --git a/includes/old.php b/includes/old.php
--- a/includes/old.php
+++ b/includes/old.php
@@ -1,2 +1,2 @@
- * @since 0.35.0
+ * @since 0.36.0
diff --git a/src/extra.js b/src/extra.js
--- a/src/extra.js
+++ b/src/extra.js
@@ -1,2 +1,2 @@
- * @since 0.35.0
+ * @since 0.36.0
+ * @since 0.36.0
DIFF;

		$this->assertSame(
			array( 'src/extra.js: * @since 0.36.0' ),
			gatherpress_since_diff_violations( $diff )
		);
	}

	/**
	 * Tests that a resolver PR replacing TBD passes the check.
	 *
	 * @return void
	 */
	public function test_since_diff_allows_tbd_replacement(): void {
		$diff = <<<'DIFF'
diff --git a/includes/new.php b/includes/new.php
--- a/includes/new.php
+++ b/includes/new.php
@@ -1,2 +1,2 @@
- * @since TBD
+ * @since 0.36.0
DIFF;

		$this->assertSame( array(), gatherpress_since_diff_violations( $diff ) );
	}

	/**
	 * Tests that non-source and unchanged lines are ignored.
	 *
	 * @return void
	 */
	public function test_since_diff_ignores_context(): void {
		$diff = <<<'DIFF'
diff --git a/docs/example.md b/docs/example.md
--- a/docs/example.md
+++ b/docs/example.md
@@ -1 +1 @@
+ * @since 0.36.0
diff --git a/src/existing.js b/src/existing.js
--- a/src/existing.js
+++ b/src/existing.js
@@ -1 +1 @@
 * @since 0.36.0
DIFF;

		$this->assertSame( array(), gatherpress_since_diff_violations( $diff ) );
	}

	/**
	 * Tests that the checker requires a base reference.
	 *
	 * @runInSeparateProcess
	 *
	 * @return void
	 */
	public function test_check_requires_base_ref(): void {
		putenv( 'GITHUB_BASE_REF' );
		$this->assertSame( 1, gatherpress_run_since_check() );
	}

	/**
	 * Tests that the checker rejects an invalid base reference.
	 *
	 * @runInSeparateProcess
	 *
	 * @return void
	 */
	public function test_check_rejects_invalid_base_ref(): void {
		putenv( 'GITHUB_BASE_REF=bad ref' );
		$this->assertSame( 1, gatherpress_run_since_check() );
	}

	/**
	 * Tests a successful resolver run.
	 *
	 * @return void
	 */
	public function test_run_since_resolver_succeeds(): void {
		$root = trailingslashit( sys_get_temp_dir() . '/gatherpress-since-root-' . uniqid() );
		mkdir( $root . 'includes', 0777, true );
		mkdir( $root . 'src', 0777, true );
		file_put_contents( $root . 'package.json', '{"version":"0.36.0-alpha.2"}' );
		file_put_contents( $root . 'includes/example.php', '@since TBD' );

		$this->assertSame( 0, gatherpress_run_since_resolver( $root ) );
		$this->assertSame( '@since 0.36.0', file_get_contents( $root . 'includes/example.php' ) );

		unlink( $root . 'package.json' );
		unlink( $root . 'includes/example.php' );
		rmdir( $root . 'includes' );
		rmdir( $root . 'src' );
		rmdir( $root );
	}

	/**
	 * Tests invalid package handling.
	 *
	 * @return void
	 */
	public function test_run_since_resolver_rejects_invalid_package(): void {
		$root = trailingslashit( sys_get_temp_dir() . '/gatherpress-since-invalid-' . uniqid() );
		mkdir( $root );
		file_put_contents( $root . 'package.json', '{"version":"invalid"}' );

		$this->assertSame( 1, gatherpress_run_since_resolver( $root ) );

		unlink( $root . 'package.json' );
		rmdir( $root );
	}
}
