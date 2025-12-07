#!/usr/bin/env php
<?php
/**
 * Check test coverage for files changed in a Pull Request.
 *
 * This script:
 * 1. Reads centralized coverage configuration from .github/coverage-config.json
 * 2. Gets list of changed PHP files in the PR (from git diff)
 * 3. Parses coverage.xml to check coverage for those files
 * 4. Shows uncovered line numbers for files that don't meet minimum coverage
 * 5. Exits with error code if any changed files don't meet minimum coverage
 *
 * @package GatherPress
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.YodaConditions, Universal.Operators.DisallowShortTernary
 */

/**
 * Load centralized coverage configuration.
 *
 * @return array Configuration array.
 */
function load_coverage_config(): array {
	$config_file = __DIR__ . '/../coverage-config.json';

	if ( ! file_exists( $config_file ) ) {
		echo "❌ Error: Coverage config file not found at {$config_file}\n";
		exit( 1 );
	}

	$config = json_decode( file_get_contents( $config_file ), true );

	if ( json_last_error() !== JSON_ERROR_NONE ) {
		echo '❌ Error: Invalid JSON in coverage config: ' . json_last_error_msg() . "\n";
		exit( 1 );
	}

	return $config;
}

/**
 * Check if a file matches any of the exclude patterns.
 *
 * @param string $file            File path to check.
 * @param array  $exclude_patterns Array of glob patterns to exclude.
 * @return bool True if file should be excluded.
 */
function should_exclude_file( string $file, array $exclude_patterns ): bool {
	foreach ( $exclude_patterns as $pattern ) {
		// Convert glob pattern to regex.
		$regex = str_replace(
			array( '\\*', '\\?' ),
			array( '.*', '.' ),
			preg_quote( $pattern, '/' )
		);

		if ( preg_match( "/^{$regex}$/", $file ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if a file is allowed to have low coverage.
 *
 * @param string $file              File path to check.
 * @param array  $allowed_low_files Array of files allowed to have low coverage.
 * @return bool True if file is allowed to have low coverage.
 */
function is_low_coverage_allowed( string $file, array $allowed_low_files ): bool {
	foreach ( $allowed_low_files as $allowed_file ) {
		if ( str_ends_with( $file, $allowed_file ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Get list of PHP files changed in the PR.
 *
 * @param string $base_ref Base branch (e.g., 'develop').
 * @return array List of changed PHP file paths.
 */
function get_changed_php_files( string $base_ref ): array {
	// Get list of changed files from git diff.
	exec( "git diff --name-only origin/{$base_ref}...HEAD", $output, $return_code );

	if ( $return_code !== 0 ) {
		echo "❌ Error: Failed to get changed files from git\n";
		exit( 1 );
	}

	// Filter to only PHP files.
	$php_files = array_filter(
		$output,
		function ( $file ) {
			return str_ends_with( $file, '.php' );
		}
	);

	return array_values( $php_files );
}

/**
 * Parse coverage XML and extract coverage data for a specific file.
 *
 * @param SimpleXMLElement $coverage_xml Coverage XML data.
 * @param string           $file_path    File path to get coverage for.
 * @return array|null Coverage data with 'covered', 'total', 'percentage', 'uncovered_lines'.
 */
function get_file_coverage( SimpleXMLElement $coverage_xml, string $file_path ): ?array {
	// Normalize file path for comparison.
	$normalized_path = ltrim( $file_path, '/' );

	// Search for the file in the coverage XML.
	foreach ( $coverage_xml->xpath( '//file' ) as $file ) {
		$xml_path = (string) $file['name'];

		// Check if this is the file we're looking for.
		if ( str_ends_with( $xml_path, $normalized_path ) ) {
			$metrics = $file->metrics[0];

			if ( ! $metrics ) {
				continue;
			}

			$total   = (int) $metrics['elements'];
			$covered = (int) $metrics['coveredelements'];

			if ( $total === 0 ) {
				return null;
			}

			$percentage = ( $covered / $total ) * 100;

			// Extract uncovered line numbers.
			$uncovered_lines = array();
			foreach ( $file->line as $line ) {
				$line_num = (int) $line['num'];
				$count    = (int) $line['count'];

				// Line is uncovered if count is 0.
				if ( $count === 0 ) {
					$uncovered_lines[] = $line_num;
				}
			}

			return array(
				'covered'         => $covered,
				'total'           => $total,
				'percentage'      => $percentage,
				'uncovered_lines' => $uncovered_lines,
			);
		}
	}

	return null;
}

/**
 * Format uncovered line numbers into ranges.
 *
 * Converts [1, 2, 3, 5, 7, 8, 9] to "1-3, 5, 7-9".
 *
 * @param array $lines Array of line numbers.
 * @return string Formatted string of line ranges.
 */
function format_line_ranges( array $lines ): string {
	if ( empty( $lines ) ) {
		return '';
	}

	sort( $lines );
	$ranges     = array();
	$start      = $lines[0];
	$end        = $lines[0];
	$line_count = count( $lines );

	for ( $i = 1; $i < $line_count; $i++ ) {
		if ( $lines[ $i ] === $end + 1 ) {
			// Consecutive line, extend the range.
			$end = $lines[ $i ];
		} else {
			// Gap found, save current range and start new one.
			$ranges[] = ( $start === $end ) ? (string) $start : "{$start}-{$end}";
			$start    = $lines[ $i ];
			$end      = $lines[ $i ];
		}
	}

	// Add final range.
	$ranges[] = ( $start === $end ) ? (string) $start : "{$start}-{$end}";

	return implode( ', ', $ranges );
}

/**
 * Main execution function.
 */
function main(): void {
	echo "=== PR Coverage Check ===\n\n";

	// Load configuration.
	$config = load_coverage_config();

	$min_coverage     = $config['minCoverage'] ?? 80;
	$exclude_patterns = $config['excludePatterns'] ?? array();
	$allow_low_files  = $config['allowLowCoverage'] ?? array();

	echo "Configuration loaded:\n";
	echo "  Minimum coverage: {$min_coverage}%\n";
	echo '  Exclude patterns: ' . count( $exclude_patterns ) . " patterns\n";
	echo '  Low coverage exceptions: ' . count( $allow_low_files ) . " files\n\n";

	// Get base branch from environment variable or default to 'develop'.
	$base_ref = getenv( 'GITHUB_BASE_REF' ) ?: 'develop';

	echo "Checking coverage for files changed from origin/{$base_ref}...\n\n";

	// Get changed PHP files.
	$changed_files = get_changed_php_files( $base_ref );

	if ( empty( $changed_files ) ) {
		echo "✅ No PHP files changed in this PR.\n";
		exit( 0 );
	}

	echo 'Found ' . count( $changed_files ) . " changed PHP file(s):\n";
	foreach ( $changed_files as $file ) {
		echo "  - {$file}\n";
	}
	echo "\n";

	// Filter out excluded files before loading coverage.
	$files_to_check = array_filter(
		$changed_files,
		function ( $file ) use ( $exclude_patterns ) {
			return ! should_exclude_file( $file, $exclude_patterns );
		}
	);

	if ( empty( $files_to_check ) ) {
		echo "✅ All changed files are excluded from coverage checks.\n";
		echo "Changed files are in excluded directories (test, vendor, .github, etc.)\n";
		exit( 0 );
	}

	echo 'Files requiring coverage check: ' . count( $files_to_check ) . "\n";
	foreach ( $files_to_check as $file ) {
		echo "  - {$file}\n";
	}
	echo "\n";

	// Load coverage XML.
	$coverage_file = __DIR__ . '/../../coverage.xml';

	if ( ! file_exists( $coverage_file ) ) {
		echo "❌ Error: Coverage file not found at {$coverage_file}\n";
		echo "Please run 'npm run test:unit:php' first to generate coverage data.\n";
		exit( 1 );
	}

	$coverage_xml = simplexml_load_file( $coverage_file );

	if ( ! $coverage_xml ) {
		echo "❌ Error: Failed to parse coverage XML\n";
		exit( 1 );
	}

	// Check coverage for each file that needs checking.
	$failed_files = array();
	$passed_files = array();

	foreach ( $files_to_check as $file ) {

		// Get coverage data for this file.
		$coverage = get_file_coverage( $coverage_xml, $file );

		if ( $coverage === null ) {
			// File not in coverage report (might be new file with no tests yet).
			if ( is_low_coverage_allowed( $file, $allow_low_files ) ) {
				echo "⚠️  {$file} - Not in coverage report (exception allowed)\n";
			} else {
				$failed_files[] = array(
					'file'       => $file,
					'percentage' => 0,
					'reason'     => 'No coverage data found',
				);
				echo "❌ {$file} - No coverage data found\n";
			}
			continue;
		}

		$percentage = round( $coverage['percentage'], 2 );
		$covered    = $coverage['covered'];
		$total      = $coverage['total'];

		// Check if coverage meets minimum.
		if ( $percentage < $min_coverage ) {
			if ( is_low_coverage_allowed( $file, $allow_low_files ) ) {
				echo "⚠️  {$file} - {$percentage}% coverage ({$covered}/{$total} lines) - Exception allowed\n";
			} else {
				$failed_files[] = array(
					'file'            => $file,
					'percentage'      => $percentage,
					'covered'         => $covered,
					'total'           => $total,
					'uncovered_lines' => $coverage['uncovered_lines'],
				);

				$line_ranges = format_line_ranges( $coverage['uncovered_lines'] );

				echo "❌ {$file} - {$percentage}% coverage ({$covered}/{$total} lines)\n";
				if ( ! empty( $line_ranges ) ) {
					echo "   Uncovered lines: {$line_ranges}\n";
				}
			}
		} else {
			$passed_files[] = $file;
			echo "✅ {$file} - {$percentage}% coverage ({$covered}/{$total} lines)\n";
		}
	}

	// Count excluded files.
	$excluded_count = count( $changed_files ) - count( $files_to_check );

	// Summary.
	echo "\n=== Summary ===\n";
	echo '✅ Passed: ' . count( $passed_files ) . " file(s)\n";
	echo '⏭️  Excluded: ' . $excluded_count . " file(s)\n";
	echo '❌ Failed: ' . count( $failed_files ) . " file(s)\n\n";

	// If any files failed, provide detailed information and exit with error.
	if ( ! empty( $failed_files ) ) {
		echo "=== Files Requiring Additional Test Coverage ===\n\n";

		foreach ( $failed_files as $failed ) {
			echo "File: {$failed['file']}\n";

			if ( isset( $failed['percentage'] ) && $failed['percentage'] > 0 ) {
				echo "  Coverage: {$failed['percentage']}% ({$failed['covered']}/{$failed['total']} lines)\n";
				echo "  Required: {$min_coverage}%\n";

				if ( ! empty( $failed['uncovered_lines'] ) ) {
					$line_ranges = format_line_ranges( $failed['uncovered_lines'] );
					echo "  Uncovered lines: {$line_ranges}\n";
					echo '  Total uncovered: ' . count( $failed['uncovered_lines'] ) . " lines\n";
				}
			} else {
				echo "  Issue: {$failed['reason']}\n";
			}

			echo "\n";
		}

		echo "Please add tests to cover the missing lines in the files listed above.\n";
		echo "Minimum required coverage: {$min_coverage}%\n";

		exit( 1 );
	}

	echo "✅ All changed files meet the minimum coverage requirement of {$min_coverage}%\n";
	exit( 0 );
}

// Run the script.
main();
