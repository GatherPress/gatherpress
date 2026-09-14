#!/usr/bin/env php
<?php
/**
 * Resolve @since TBD tags to the stable development version.
 *
 * @package GatherPress
 *
 * phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, Universal.Operators.DisallowStandalonePostIncrementDecrement
 */

/**
 * Return a stable version without a pre-release suffix.
 *
 * @param string $version Version string.
 *
 * @return string Stable version.
 * @throws InvalidArgumentException If the version is invalid.
 */
function gatherpress_since_stable_version( string $version ): string {
	if ( ! preg_match( '/^(\d+\.\d+\.\d+)(?:-(?:alpha|beta|rc)\.\d+)?$/', $version, $matches ) ) {
		throw new InvalidArgumentException( "Invalid version: {$version}" );
	}

	return $matches[1];
}

/**
 * Resolve @since TBD tags in one file.
 *
 * @param string $file    File path.
 * @param string $version Stable version.
 *
 * @return bool Whether the file changed.
 * @throws RuntimeException If the file cannot be read, processed, or written.
 */
function gatherpress_resolve_since_file( string $file, string $version ): bool {
	$contents = file_get_contents( $file );

	if ( false === $contents ) {
		throw new RuntimeException( "Unable to read {$file}" );
	}

	$resolved = preg_replace( '/(@since\s+)TBD\b/', '${1}' . $version, $contents );

	if ( null === $resolved ) {
		throw new RuntimeException( "Unable to process {$file}" );
	}

	if ( $resolved === $contents ) {
		return false;
	}

	if ( false === file_put_contents( $file, $resolved ) ) {
		throw new RuntimeException( "Unable to write {$file}" );
	}

	return true;
}

/**
 * Resolve @since TBD tags below the supplied directories.
 *
 * @param string[] $directories Directories to scan.
 * @param string   $version     Stable version.
 *
 * @return int Number of changed files.
 */
function gatherpress_resolve_since( array $directories, string $version ): int {
	$changed_files = 0;

	foreach ( $directories as $directory ) {
		if ( ! is_dir( $directory ) ) {
			continue;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || ! preg_match( '/\.(?:php|js)$/', $file->getFilename() ) ) {
				continue;
			}

			if ( gatherpress_resolve_since_file( $file->getPathname(), $version ) ) {
				$changed_files++;
			}
		}
	}

	return $changed_files;
}

/**
 * Run the resolver from the repository root.
 *
 * @param string|null $root Optional repository root.
 *
 * @return int Exit status.
 */
function gatherpress_run_since_resolver( ?string $root = null ): int {
	$root         = $root ?? dirname( __DIR__, 2 );
	$package_file = $root . '/package.json';
	$package      = json_decode( (string) file_get_contents( $package_file ), true );

	if ( ! is_array( $package ) || empty( $package['version'] ) ) {
		fwrite( STDERR, "Unable to read a version from package.json.\n" );
		return 1;
	}

	try {
		$version = gatherpress_since_stable_version( $package['version'] );
		$count   = gatherpress_resolve_since( array( $root . '/includes', $root . '/src' ), $version );
	} catch ( Throwable $error ) {
		fwrite( STDERR, $error->getMessage() . "\n" );
		return 1;
	}

	printf( "Resolved %d file(s) to %s.\n", $count, $version );

	return 0;
}

// Only run when executed directly, not when required from the test suite.
if ( 'cli' === PHP_SAPI && isset( $argv ) && realpath( $argv[0] ) === __FILE__ ) {
	exit( gatherpress_run_since_resolver() );
}
