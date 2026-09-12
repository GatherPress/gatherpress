#!/usr/bin/env php
<?php
/**
 * Check newly-added @since tags in a pull request.
 *
 * @package GatherPress
 *
 * phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.PHP.DiscouragedPHPFunctions.system_calls, WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
 */

/**
 * Return violations from added lines in a unified diff.
 *
 * A hunk that removes an @since line and adds one back is a correction of an
 * existing tag (or the resolver replacing TBD), so the replacement is allowed.
 * Only added tags beyond what the hunk removes are treated as new.
 *
 * @param string $diff Unified diff.
 *
 * @return string[] Violations.
 */
function gatherpress_since_diff_violations( string $diff ): array {
	$violations        = array();
	$current_file      = '';
	$removed_since_tag = 0;

	foreach ( preg_split( '/\R/', $diff ) as $line ) {
		if ( str_starts_with( $line, '@@' ) ) {
			$removed_since_tag = 0;
			continue;
		}

		if ( preg_match( '/^\+\+\+ b\/(.+)$/', $line, $matches ) ) {
			$current_file = $matches[1];
			continue;
		}

		if ( ! str_starts_with( $line, '+' ) || str_starts_with( $line, '+++' ) ) {
			if ( str_starts_with( $line, '-' ) && ! str_starts_with( $line, '---' ) ) {
				$removed_since_tag += substr_count( $line, '@since' );
			}

			continue;
		}

		if ( ! preg_match( '/\.(?:php|js)$/', $current_file ) ) {
			continue;
		}

		if ( ! preg_match_all( '/@since\s+([^\s*]+)/', $line, $matches ) ) {
			continue;
		}

		foreach ( $matches[1] as $tag ) {
			if ( 'TBD' === $tag ) {
				continue;
			}

			if ( $removed_since_tag > 0 ) {
				// Replacing an existing tag counts as a correction, not a new tag.
				--$removed_since_tag;
				continue;
			}

			$violations[] = sprintf( '%s: %s', $current_file, trim( ltrim( $line, '+' ) ) );
		}
	}

	return $violations;
}

/**
 * Run the pull request check.
 *
 * @return int Exit status.
 */
function gatherpress_run_since_check(): int {
	$base_ref = getenv( 'GITHUB_BASE_REF' );

	if ( ! is_string( $base_ref ) || '' === $base_ref ) {
		fwrite( STDERR, "GITHUB_BASE_REF is required.\n" );
		return 1;
	}

	if ( ! preg_match( '/^[A-Za-z0-9._\/-]+$/', $base_ref ) ) {
		fwrite( STDERR, "Invalid GITHUB_BASE_REF.\n" );
		return 1;
	}

	$command = sprintf(
		'git diff --unified=0 origin/%s...HEAD -- includes src',
		escapeshellarg( $base_ref )
	);
	exec( $command, $output, $return_code );

	if ( 0 !== $return_code ) {
		fwrite( STDERR, "Unable to read the pull request diff.\n" );
		return 1;
	}

	$violations = gatherpress_since_diff_violations( implode( "\n", $output ) );

	if ( ! empty( $violations ) ) {
		fwrite( STDERR, "New @since tags must use TBD:\n" . implode( "\n", $violations ) . "\n" );
		return 1;
	}

	echo "All newly-added @since tags use TBD.\n";

	return 0;
}

// Only run when executed directly, not when required from the test suite.
if ( 'cli' === PHP_SAPI && isset( $argv ) && realpath( $argv[0] ) === __FILE__ ) {
	exit( gatherpress_run_since_check() );
}
