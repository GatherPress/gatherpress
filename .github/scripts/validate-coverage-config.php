#!/usr/bin/env php
<?php
/**
 * Validate that coverage configuration is consistent across tools.
 *
 * This script ensures that the centralized coverage configuration in
 * .github/coverage-config.json is properly reflected in:
 * - phpunit.xml.dist (PHP unit tests)
 * - sonar-project.properties (SonarCloud analysis)
 *
 * @package GatherPress
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
		echo "❌ Error: Invalid JSON in coverage config: " . json_last_error_msg() . "\n";
		exit( 1 );
	}

	return $config;
}

/**
 * Extract exclude patterns from phpunit.xml.dist.
 *
 * @return array List of exclude patterns.
 */
function get_phpunit_excludes(): array {
	$phpunit_file = __DIR__ . '/../../phpunit.xml.dist';

	if ( ! file_exists( $phpunit_file ) ) {
		echo "❌ Error: phpunit.xml.dist not found at {$phpunit_file}\n";
		exit( 1 );
	}

	$xml = simplexml_load_file( $phpunit_file );

	if ( ! $xml ) {
		echo "❌ Error: Failed to parse phpunit.xml.dist\n";
		exit( 1 );
	}

	$excludes = array();

	// Extract exclude patterns from coverage filter.
	foreach ( $xml->xpath( '//coverage/exclude/*' ) as $exclude ) {
		$excludes[] = (string) $exclude;
	}

	return $excludes;
}

/**
 * Extract exclude patterns from sonar-project.properties.
 *
 * @return array List of exclude patterns.
 */
function get_sonar_excludes(): array {
	$sonar_file = __DIR__ . '/../../sonar-project.properties';

	if ( ! file_exists( $sonar_file ) ) {
		echo "⚠️  Warning: sonar-project.properties not found\n";
		return array();
	}

	$content  = file_get_contents( $sonar_file );
	$excludes = array();

	// Extract coverage exclusions.
	if ( preg_match( '/sonar\.coverage\.exclusions\s*=\s*(.+)/', $content, $matches ) ) {
		$patterns = explode( ',', $matches[1] );
		foreach ( $patterns as $pattern ) {
			$excludes[] = trim( $pattern );
		}
	}

	return $excludes;
}

/**
 * Normalize a path pattern for comparison.
 *
 * @param string $pattern Path pattern to normalize.
 * @return string Normalized pattern.
 */
function normalize_pattern( string $pattern ): string {
	// Remove leading/trailing slashes and wildcards for comparison.
	$normalized = trim( $pattern, '/*' );

	// Convert different wildcard formats to common format.
	$normalized = str_replace( '**', '*', $normalized );

	return $normalized;
}

/**
 * Check if two patterns are equivalent.
 *
 * @param string $pattern1 First pattern.
 * @param string $pattern2 Second pattern.
 * @return bool True if patterns are equivalent.
 */
function patterns_match( string $pattern1, string $pattern2 ): bool {
	$norm1 = normalize_pattern( $pattern1 );
	$norm2 = normalize_pattern( $pattern2 );

	// Direct match.
	if ( $norm1 === $norm2 ) {
		return true;
	}

	// Check if one contains the other (e.g., "vendor" matches "*/vendor/*").
	if ( strpos( $norm1, $norm2 ) !== false || strpos( $norm2, $norm1 ) !== false ) {
		return true;
	}

	return false;
}

/**
 * Find matching pattern in a list.
 *
 * @param string $pattern Pattern to find.
 * @param array  $list    List of patterns to search.
 * @return string|null Matching pattern or null.
 */
function find_matching_pattern( string $pattern, array $list ): ?string {
	foreach ( $list as $item ) {
		if ( patterns_match( $pattern, $item ) ) {
			return $item;
		}
	}

	return null;
}

/**
 * Main execution function.
 */
function main(): void {
	echo "=== Coverage Configuration Validation ===\n\n";

	// Load centralized configuration.
	$config           = load_coverage_config();
	$config_patterns  = $config['excludePatterns'] ?? array();
	$phpunit_excludes = get_phpunit_excludes();
	$sonar_excludes   = get_sonar_excludes();

	echo "Centralized config patterns: " . count( $config_patterns ) . "\n";
	echo "PHPUnit exclusions: " . count( $phpunit_excludes ) . "\n";
	echo "SonarCloud exclusions: " . count( $sonar_excludes ) . "\n\n";

	$errors   = array();
	$warnings = array();

	// Check that all centralized patterns are in PHPUnit config.
	echo "Checking PHPUnit configuration...\n";
	foreach ( $config_patterns as $pattern ) {
		$match = find_matching_pattern( $pattern, $phpunit_excludes );

		if ( $match === null ) {
			$errors[] = "Pattern '{$pattern}' from config is missing in phpunit.xml.dist";
		} else {
			echo "  ✅ {$pattern} → {$match}\n";
		}
	}

	// Check that all centralized patterns are in SonarCloud config.
	if ( ! empty( $sonar_excludes ) ) {
		echo "\nChecking SonarCloud configuration...\n";
		foreach ( $config_patterns as $pattern ) {
			$match = find_matching_pattern( $pattern, $sonar_excludes );

			if ( $match === null ) {
				$warnings[] = "Pattern '{$pattern}' from config is missing in sonar-project.properties";
			} else {
				echo "  ✅ {$pattern} → {$match}\n";
			}
		}
	}

	// Check for patterns in PHPUnit that aren't in centralized config.
	echo "\nChecking for PHPUnit patterns not in centralized config...\n";
	foreach ( $phpunit_excludes as $pattern ) {
		$match = find_matching_pattern( $pattern, $config_patterns );

		if ( $match === null ) {
			$warnings[] = "Pattern '{$pattern}' in phpunit.xml.dist is not in centralized config";
		}
	}

	// Report results.
	echo "\n=== Validation Results ===\n";

	if ( empty( $errors ) && empty( $warnings ) ) {
		echo "✅ All coverage configurations are consistent!\n";
		exit( 0 );
	}

	if ( ! empty( $errors ) ) {
		echo "\n❌ Errors:\n";
		foreach ( $errors as $error ) {
			echo "  - {$error}\n";
		}
	}

	if ( ! empty( $warnings ) ) {
		echo "\n⚠️  Warnings:\n";
		foreach ( $warnings as $warning ) {
			echo "  - {$warning}\n";
		}
	}

	if ( ! empty( $errors ) ) {
		echo "\nPlease update the configurations to match .github/coverage-config.json\n";
		exit( 1 );
	}

	exit( 0 );
}

// Run the script.
main();
