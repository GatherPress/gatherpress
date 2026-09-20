#!/usr/bin/env php
<?php
/**
 * Carry a release's resolved `@since` tags back to develop.
 *
 * A patch release is cut from `main` with fixes cherry-picked from
 * `develop`. Its bump resolves `@since TBD` to the patch version on the
 * patch branch, which is correct, but `develop` still carries `TBD` for the
 * same symbols. Left alone, the next minor resolves those to its own version
 * and claims a release the symbol did not first appear in.
 *
 * This finds the ones that can be matched with certainty and prints the
 * resulting file contents for the caller to commit. A docblock is matched
 * only when `develop`'s copy is byte-identical to the released one except
 * for `TBD` where the version now sits. Anything that does not match that
 * exactly is reported rather than guessed at.
 *
 * A minor release is a no-op: its bump lands on `develop` too, so there is
 * no `TBD` left there to match.
 *
 * Usage, from a checkout of the released tag with `develop` fetched:
 *   php .github/scripts/release/carry-since.php --version=0.35.5
 *
 * Prints JSON on stdout:
 *   { "additions": [ { "path": …, "contents": base64 } ], "unmatched": [ … ] }
 *
 * @package GatherPress
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.YodaConditions, WordPress.PHP.DevelopmentFunctions
 */

define( 'CARRY_REPO_ROOT', dirname( __DIR__, 3 ) );

/**
 * Read a path as it exists on develop.
 *
 * @param string $path Repository-relative path.
 * @return string|null The contents, or null when develop does not have it.
 */
function develop_contents( $path ) {
	$command = sprintf( 'git show origin/develop:%s 2>/dev/null', escapeshellarg( $path ) );
	$output  = shell_exec( $command );

	return ( $output === null || $output === '' ) ? null : $output;
}

/**
 * Every docblock in a string.
 *
 * @param string $contents File contents.
 * @return string[] The docblocks, in order, duplicates included.
 */
function docblocks( $contents ) {
	preg_match_all( '#/\*\*.*?\*/#s', $contents, $matches );

	return $matches[0];
}

/**
 * Every PHP and JS file the plugin owns, source and tests alike.
 *
 * Excludes rather than allowlists, so a new source directory is covered
 * the day it appears. Generated output, vendored code and tooling are
 * skipped.
 *
 * @return string[] Repository-relative paths.
 */
function carry_source_files() {
	$files = array();
	$skip  = array( 'build', 'node_modules', 'vendor' );

	$iterator = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( CARRY_REPO_ROOT, FilesystemIterator::SKIP_DOTS ),
			static function ( $current ) use ( $skip ) {
				if ( ! $current->isDir() ) {
					return true;
				}

				$name = $current->getFilename();

				// Generated output, vendored code and anything hidden. A
				// dot directory holds tooling rather than plugin source.
				return ! in_array( $name, $skip, true ) && ! str_starts_with( $name, '.' );
			}
		)
	);

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || ! in_array( strtolower( $file->getExtension() ), array( 'php', 'js' ), true ) ) {
			continue;
		}

		$files[] = str_replace( CARRY_REPO_ROOT . '/', '', $file->getPathname() );
	}

	sort( $files );

	return $files;
}

$options = getopt( '', array( 'version:' ) );

if (
	empty( $options['version'] )
	|| ! preg_match( '/^\d+\.\d+\.\d+$/', $options['version'] )
) {
	fwrite( STDERR, "Usage: carry-since.php --version=X.Y.Z (stable versions only)\n" );
	exit( 1 );
}

$version   = $options['version'];
$additions = array();
$unmatched = array();

foreach ( carry_source_files() as $source ) {
	$released = file_get_contents( CARRY_REPO_ROOT . '/' . $source );

	if ( $released === false ) {
		fwrite( STDERR, sprintf( "Could not read %s.\n", $source ) );
		exit( 1 );
	}

	if ( ! str_contains( $released, '@since ' . $version ) ) {
		continue;
	}

	$current = develop_contents( $source );

	if ( $current === null ) {
		$unmatched[] = sprintf( '%s (not on develop)', $source );
		continue;
	}

	$updated = $current;
	$changed = 0;

	foreach ( docblocks( $released ) as $block ) {
		if ( ! str_contains( $block, '@since ' . $version ) ) {
			continue;
		}

		// The same docblock as it would read before the release resolved it.
		// Matching on the whole block, byte for byte, is what makes this safe
		// to apply without review: a block that differs anywhere else is a
		// different state of the code and gets reported instead.
		$unresolved = str_replace( '@since ' . $version, '@since TBD', $block );

		$position = strpos( $updated, $unresolved );

		if ( $position === false ) {
			// A minor release resolves the tags on develop through its own
			// bump, so the released block is already there word for word and
			// there is nothing to carry. Reporting that as unmatched would
			// put a warning on every minor and teach everyone to ignore it.
			if ( str_contains( $updated, $block ) ) {
				continue;
			}

			$unmatched[] = sprintf( '%s (no identical TBD docblock on develop)', $source );
			continue;
		}
		$updated = substr_replace( $updated, $block, $position, strlen( $unresolved ) );

		++$changed;
	}

	if ( $changed === 0 ) {
		continue;
	}

	$additions[] = array(
		'path'     => $source,
		'contents' => base64_encode( $updated ),
		'resolved' => $changed,
	);
}

echo json_encode(
	array(
		'additions' => $additions,
		'unmatched' => array_values( array_unique( $unmatched ) ),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
