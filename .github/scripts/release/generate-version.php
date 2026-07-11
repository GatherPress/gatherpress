#!/usr/bin/env php
<?php
/**
 * Bump a GatherPress version: credits, version strings, SECURITY.md.
 *
 * Standalone replacement for the `wp gatherpress develop generate_version`
 * WP-CLI command from the retired GatherPress/gatherpress-develop repo
 * (#1827). No WordPress required — the only network dependency is the
 * profiles.wordpress.org REST API used to resolve credit usernames.
 *
 * Usage (via the npm wrapper, or directly):
 *   npm run version:bump -- --version=0.35.0
 *   php .github/scripts/release/generate-version.php --version=0.35.0
 *
 * Requires a credits file for the target version at
 * .github/scripts/release/credits/<version>.json (add it there first — a
 * stable version's file is a copy of its latest pre-release file).
 *
 * Unlike the old tooling, README.md and readme.txt are hand-edited files —
 * this script only patches the strings that change per release, in place:
 *   - includes/data/credits.php   regenerated (do not hand-edit)
 *   - gatherpress.php             Version: header
 *   - package.json                version field (refresh the lockfile after:
 *                                 `npm i --package-lock-only`)
 *   - README.md                   version badge
 *   - readme.txt                  Stable tag: and Contributors: lines
 *   - SECURITY.md                 supported-versions table (core + alpha)
 *   - ../gatherpress-alpha/gatherpress-alpha.php  lockstep Version: header
 *                                 (skipped with a warning when not checked out)
 *
 * @package GatherPress
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.YodaConditions, WordPress.PHP.DevelopmentFunctions, Universal.Operators.DisallowShortTernary
 */

define( 'REPO_ROOT', dirname( __DIR__, 3 ) );
define( 'SCRIPT_ROOT', __DIR__ );

/**
 * Print a success line, mirroring WP-CLI's output shape.
 *
 * @param string $message The message.
 * @return void
 */
function success( $message ) {
	echo "Success: {$message}\n";
}

/**
 * Print a warning line to STDERR without aborting.
 *
 * @param string $message The message.
 * @return void
 */
function warning( $message ) {
	fwrite( STDERR, "Warning: {$message}\n" );
}

/**
 * Print an error line to STDERR and exit non-zero.
 *
 * @param string $message The message.
 * @return void
 */
function fail( $message ) {
	fwrite( STDERR, "Error: {$message}\n" );
	exit( 1 );
}

/**
 * Fetch a wp.org user profile as a decoded array.
 *
 * Plain-HTTP replacement for the old wp_remote_request() call. Fails loudly
 * on network errors or non-JSON payloads so a typo'd username can't silently
 * produce an empty credit entry.
 *
 * @param string $username The wp.org username.
 * @return array The decoded profile data.
 */
function fetch_wporg_profile( $username ) {
	$url     = sprintf( 'https://profiles.wordpress.org/wp-json/wporg/v1/users/%s', rawurlencode( $username ) );
	$context = stream_context_create(
		array(
			'http' => array(
				'timeout'       => 30,
				'ignore_errors' => true,
				'user_agent'    => 'GatherPress-release-tooling',
			),
		)
	);
	$body    = file_get_contents( $url, false, $context );

	if ( false === $body ) {
		fail( "Could not reach profiles.wordpress.org for user '{$username}'." );
	}

	$data = json_decode( $body, true );

	if ( ! is_array( $data ) || empty( $data['slug'] ) ) {
		fail(
			"profiles.wordpress.org returned no usable profile for '{$username}' — check the credits file."
		);
	}

	return $data;
}

/**
 * Generate includes/data/credits.php from the source credits entry.
 *
 * @param string $version The plugin version.
 * @return string Comma-separated leads + team usernames for readme.txt's Contributors line.
 */
function generate_credits( $version ) {
	$credits_file = SCRIPT_ROOT . "/credits/{$version}.json";
	$latest       = REPO_ROOT . '/includes/data/credits.php';
	$data         = array();

	if ( ! file_exists( $credits_file ) ) {
		fail( "No credits file for {$version} — add .github/scripts/release/credits/{$version}.json first." );
	}

	$entry = json_decode( file_get_contents( $credits_file ), true );

	if ( ! is_array( $entry ) ) {
		fail( "credits/{$version}.json is not valid JSON." );
	}

	$data['version'] = $version;
	$contributors    = array();

	// Fixed group order: leads and team drive readme.txt's Contributors
	// line and the credits page ordering regardless of file key order.
	foreach ( array( 'project-leaders', 'gatherpress-team', 'contributors' ) as $group ) {
		$users = isset( $entry[ $group ] ) && is_array( $entry[ $group ] ) ? $entry[ $group ] : array();

		if ( 'contributors' === $group ) {
			sort( $users );
		}

		// Only leads + team land in the wp.org plugin header's
		// `Contributors:` line. The contributors group still appears on
		// the credits page (via $data below), but it gets churn-y as more
		// people land single-PR contributions, and wp.org's plugin
		// directory lists those as "Contributors" with a level of billing
		// that doesn't match the actual involvement.
		if ( 'contributors' !== $group ) {
			$contributors = array_merge( $contributors, $users );
		}

		$data[ $group ] = array();

		foreach ( $users as $user ) {
			$user_data = fetch_wporg_profile( $user );

			// Remove unsecure data (eg http) and data we do not need.
			unset( $user_data['description'], $user_data['url'], $user_data['meta'], $user_data['_links'] );

			$data[ $group ][] = $user_data;
		}
	}

	$output  = "<?php\n\n";
	$output .= "// Exit if accessed directly.\n";
	$output .= "defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore\n\n";
	$output .= 'return ' . var_export( $data, true ) . ";\n";

	if ( file_put_contents( $latest, $output ) === false ) {
		fail( 'Failed to write includes/data/credits.php.' );
	}

	success( 'New credits.php file has been generated.' );

	return implode( ', ', $contributors );
}

/**
 * Apply a regex replacement to a file, failing loudly when nothing matches.
 *
 * @param string $file        Absolute file path.
 * @param string $pattern     Regex whose match gets replaced.
 * @param string $replacement Replacement (may use capture-group refs).
 * @param string $label       Human-readable description for messages.
 * @return void
 */
function patch_file( $file, $pattern, $replacement, $label ) {
	if ( ! file_exists( $file ) ) {
		fail( "File not found while updating {$label}: {$file}" );
	}

	$contents = file_get_contents( $file );

	if ( ! preg_match( $pattern, $contents ) ) {
		fail( "Could not find {$label} in " . basename( $file ) . ' — has the file changed shape?' );
	}

	$new_contents = preg_replace( $pattern, $replacement, $contents );

	if ( file_put_contents( $file, $new_contents ) === false ) {
		fail( "Failed to write {$file}." );
	}

	success( "Updated {$label}." );
}

/**
 * Patch the supported-versions table in a SECURITY.md file.
 *
 * @param string $file        Absolute path to the SECURITY.md.
 * @param string $major_minor The major.minor version (e.g. "0.35").
 * @param string $label       Label for messages ("core" / "alpha").
 * @return void
 */
function patch_security_table( $file, $major_minor, $label ) {
	if ( ! file_exists( $file ) ) {
		warning( "{$label} SECURITY.md not found; skipping." );

		return;
	}

	patch_file(
		$file,
		'/^\|\s*\d+\.\d+\.x\s*\|/m',
		"| {$major_minor}.x  |",
		"supported version row ({$label} SECURITY.md)"
	);
	patch_file(
		$file,
		'/^\|\s*<\s*\d+\.\d+\s*\|/m',
		"| < {$major_minor}  |",
		"unsupported version row ({$label} SECURITY.md)"
	);
}

// ---------------------------------------------------------------------------
// Main.
// ---------------------------------------------------------------------------

$options = getopt( '', array( 'version:' ) );

if (
	empty( $options['version'] )
	|| ! preg_match( '/^\d+\.\d+\.\d+(-(alpha|beta|rc)\.\d+)?$/', $options['version'] )
) {
	fail( 'Usage: npm run version:bump -- --version=X.Y.Z[-alpha.N|-beta.N|-rc.N]' );
}

$version = $options['version'];

if ( ! preg_match( '/^(\d+\.\d+)/', $version, $mm_matches ) ) {
	fail( "Could not derive major.minor from version: {$version}" );
}

$major_minor = $mm_matches[1];

// Generated credits file (and the Contributors line for readme.txt).
$contributors = generate_credits( $version );

// Version strings, patched in place. README.md and readme.txt are
// hand-edited files — only these strings belong to the tooling.
patch_file(
	REPO_ROOT . '/gatherpress.php',
	'/^(\s*\*\s*Version:\s*)([\w\.-]+)$/mi',
	'${1}' . $version,
	'plugin Version header'
);
patch_file(
	REPO_ROOT . '/package.json',
	'/^(\s*"version": ")([\w\.-]+)(",)$/mi',
	'${1}' . $version . '${3}',
	'package.json version'
);
patch_file(
	REPO_ROOT . '/README.md',
	'/(!\[Version\]\(https:\/\/img\.shields\.io\/static\/v1\?label=version&message=)[^&]+(&color=blue\))/',
	'${1}' . rawurlencode( $version ) . '${2}',
	'README.md version badge'
);
patch_file(
	REPO_ROOT . '/readme.txt',
	'/^(Stable tag:\s*)([\w\.-]+)$/mi',
	'${1}' . $version,
	'readme.txt Stable tag'
);
patch_file(
	REPO_ROOT . '/readme.txt',
	'/^(Contributors:\s*)(.+)$/mi',
	'${1}' . $contributors,
	'readme.txt Contributors line'
);

patch_security_table( REPO_ROOT . '/SECURITY.md', $major_minor, 'core' );

// GatherPress Alpha is versioned in lockstep; sync it when checked out.
$alpha_dir = dirname( REPO_ROOT ) . '/gatherpress-alpha';

if ( is_dir( $alpha_dir ) ) {
	patch_file(
		$alpha_dir . '/gatherpress-alpha.php',
		'/^(\s*\*\s*Version:\s*)([\w\.-]+)$/mi',
		'${1}' . $version,
		'GatherPress Alpha Version header'
	);
	patch_security_table( $alpha_dir . '/SECURITY.md', $major_minor, 'alpha' );
} else {
	warning(
		'GatherPress Alpha plugin not found alongside core; skipping alpha version sync. '
		. 'Open its lockstep version PR separately.'
	);
}

echo "\n";
echo "Next step (on a machine with Node):\n";
echo "  npm i --package-lock-only\n";
echo "\n";
echo "That refreshes package-lock.json to match the new package.json version.\n";
