#!/usr/bin/env php
<?php
/**
 * Generate a GatherPress version: credits, version strings, readmes, SECURITY.md.
 *
 * Standalone port of the `wp gatherpress develop generate_version` WP-CLI
 * command from the retired GatherPress/gatherpress-develop repo (#1827).
 * No WordPress required — the only network dependency is the
 * profiles.wordpress.org REST API used to resolve credit usernames.
 *
 * Usage:
 *   php .github/scripts/release/generate-version.php --version=0.35.0
 *
 * Requires a credits entry for the target version in
 * .github/scripts/release/data/credits.php (add it there first). Writes:
 *   - includes/data/credits.php (generated credits, do not hand-edit)
 *   - gatherpress.php           (Version: header)
 *   - package.json              (version field; refresh the lockfile after:
 *                                `npm i --package-lock-only`)
 *   - README.md / readme.txt    (assembled from parts/)
 *   - SECURITY.md               (core, and ../gatherpress-alpha when present)
 *   - ../gatherpress-alpha/gatherpress-alpha.php (lockstep Version: header,
 *                                skipped with a warning when not checked out)
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
 * Read a template part from the parts/ directory.
 *
 * @param string $relative_path Path relative to the parts directory.
 * @return string The file contents.
 */
function read_part( $relative_path ) {
	$file = SCRIPT_ROOT . '/parts/' . $relative_path;

	if ( ! file_exists( $file ) ) {
		fail( "Part file not found: {$relative_path}" );
	}

	return file_get_contents( $file );
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
			"profiles.wordpress.org returned no usable profile for '{$username}' — check data/credits.php."
		);
	}

	return $data;
}

/**
 * Generate includes/data/credits.php from the source credits entry.
 *
 * @param string $version The plugin version.
 * @return string Comma-separated leads + team usernames for readme headers.
 */
function generate_credits( $version ) {
	$credits = require SCRIPT_ROOT . '/data/credits.php';
	$latest  = REPO_ROOT . '/includes/data/credits.php';
	$data    = array();

	if ( empty( $credits[ $version ] ) ) {
		fail( "Version {$version} does not exist in data/credits.php — add its entry first." );
	}

	$data['version'] = $version;
	$contributors    = array();

	foreach ( $credits[ $version ] as $group => $users ) {
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
 * Replace the version in a file using a regex, mirroring the old CLI.
 *
 * @param string $file    Absolute file path.
 * @param string $pattern Regex with the version in capture group 2.
 * @param string $version The new version.
 * @param string $label   Human-readable label for messages.
 * @return void
 */
function update_version_in_file( $file, $pattern, $version, $label ) {
	if ( ! file_exists( $file ) ) {
		fail( "The {$label} file does not exist." );
	}

	$file_contents = file_get_contents( $file );

	if ( ! preg_match( $pattern, $file_contents ) ) {
		fail( "Version not found in the {$label} file." );
	}

	$new_contents = preg_replace( $pattern, '${1}' . $version . '${3}', $file_contents );

	if ( file_put_contents( $file, $new_contents ) === false ) {
		fail( "Failed to update the {$label} file." );
	}

	success( "Updated {$label} version to {$version}." );
}

/**
 * Read the current "Tested up to" value from readme.txt.
 *
 * @return string The tested up to WordPress version.
 */
function get_tested_up_to() {
	$readme_file = REPO_ROOT . '/readme.txt';

	if ( file_exists( $readme_file ) ) {
		$contents = file_get_contents( $readme_file );

		if ( preg_match( '/^Tested up to:\s*([\w\.-]+)\s*$/mi', $contents, $matches ) ) {
			return $matches[1];
		}
	}

	return '6.9';
}

/**
 * Build GitHub screenshot markdown with image references.
 *
 * @return string The screenshot section with images.
 */
function build_github_screenshots() {
	$screenshots = read_part( 'shared/screenshots.md' );
	$image_map   = array(
		1 => '.wordpress-org/screenshot-1.png',
		2 => '.wordpress-org/screenshot-2.png',
		3 => '.wordpress-org/screenshot-5.png',
	);

	$output = '';
	$lines  = explode( "\n", trim( $screenshots ) );

	foreach ( $lines as $line ) {
		if ( preg_match( '/^(\d+)\.\s+(.+)$/', $line, $matches ) ) {
			$num         = (int) $matches[1];
			$description = $matches[2];
			$output     .= "{$num}. {$description}\n";

			if ( isset( $image_map[ $num ] ) ) {
				$output .= "   ![screenshot-{$num}]({$image_map[ $num ]})\n";
			}
		}
	}

	return $output;
}

/**
 * Build the GitHub README.md content from parts.
 *
 * @param string $version The plugin version.
 * @return string The assembled README.md content.
 */
function build_github_readme( $version ) {
	$output  = "<!--\n";
	$output .= "This file is auto-generated by the GatherPress release tooling.\n";
	$output .= "Do not edit it directly: changes are overwritten when the README is regenerated.\n";
	$output .= "To update it, edit the source parts in .github/scripts/release/parts/\n";
	$output .= "or the assembly in .github/scripts/release/generate-version.php, then regenerate.\n";
	$output .= "-->\n\n";
	$output .= "# GatherPress\n\n";
	$output .= "<!-- markdownlint-disable-next-line MD045 -->\n";
	$output .= "![](.wordpress-org/banner-1544x500.jpg)\n\n";
	$output .= '**' . trim( read_part( 'shared/description.md' ) ) . "**\n\n";

	$version_encoded = rawurlencode( $version );
	$output         .= '[![Try it in WordPress Playground](https://img.shields.io/badge/'
		. 'Try_it-in_WordPress_Playground-blue?logo=wordpress&logoColor=%23fff&labelColor=%233858e9&color=%233858e9)]'
		. '(https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/'
		. 'GatherPress/gatherpress/develop/.wordpress-org/blueprints/blueprint-nightly.json) '
		. "![Version](https://img.shields.io/static/v1?label=version&message={$version_encoded}&color=blue)\n\n";
	$output         .= read_part( 'github/badges.md' ) . "\n";
	$output         .= "## Screenshots\n\n";
	$output         .= build_github_screenshots() . "\n";
	$output         .= "## Features\n\n";
	$output         .= read_part( 'shared/features.md' ) . "\n";
	$output         .= "## Getting Started\n\n";
	$output         .= read_part( 'github/quick-start.md' ) . "\n";
	$output         .= "## Get Involved\n\n";
	$output         .= read_part( 'github/get-involved.md' ) . "\n";
	$output         .= "## Third-Party Libraries\n\n";
	$output         .= read_part( 'shared/third-party-libraries.md' ) . "\n";
	$output         .= "## External Services\n\n";
	$output         .= read_part( 'github/external-services.md' ) . "\n";
	$output         .= "## More Information\n\n";
	$output         .= read_part( 'github/more-info.md' ) . "\n";
	$output         .= "---\n\n";
	$output         .= read_part( 'github/footer.md' );

	return $output;
}

/**
 * Build the WordPress.org readme.txt content from parts.
 *
 * @param string $version      The plugin version.
 * @param string $contributors Comma-separated list of contributor usernames.
 * @param string $tested_up_to The WordPress version tested up to.
 * @return string The assembled readme.txt content.
 */
function build_wporg_readme( $version, $contributors, $tested_up_to ) {
	$output  = "=== GatherPress ===\n";
	$output .= "Contributors: {$contributors}\n";
	$output .= "Tags: events, event, meetup, community\n";
	$output .= "Tested up to: {$tested_up_to}\n";
	$output .= "Stable tag: {$version}\n";
	$output .= "License: GPL v2 or later\n";
	$output .= "License URI: https://www.gnu.org/licenses/gpl-2.0.html\n\n";
	$output .= trim( read_part( 'shared/description.md' ) ) . "\n\n";
	$output .= "== Description ==\n\n";
	$output .= read_part( 'shared/features.md' ) . "\n";
	$output .= "== Installation ==\n\n";
	$output .= read_part( 'shared/installation.md' ) . "\n";
	$output .= "== Screenshots ==\n\n";
	$output .= read_part( 'shared/screenshots.md' ) . "\n";
	$output .= "== Changelog ==\n\n";
	$output .= 'For the full changelog, visit the [GitHub releases page]'
		. "(https://github.com/GatherPress/gatherpress/releases).\n\n";
	$output .= "== Frequently Asked Questions ==\n\n";
	$output .= 'Visit our [FAQ page](https://github.com/GatherPress/gatherpress/blob/main/docs/faq.md) '
		. "for answers to common questions.\n\n";
	$output .= "== External Services ==\n\n";
	$output .= read_part( 'wporg/external-services.md' );

	return $output;
}

/**
 * Generate README.md and readme.txt from parts.
 *
 * @param string $version      The plugin version.
 * @param string $contributors Comma-separated list of contributor usernames.
 * @return void
 */
function generate_readmes( $version, $contributors ) {
	$tested_up_to = get_tested_up_to();

	$readme_md = build_github_readme( $version );

	if ( file_put_contents( REPO_ROOT . '/README.md', $readme_md ) !== false ) {
		success( 'Generated README.md.' );
	} else {
		fail( 'Failed to generate README.md.' );
	}

	$readme_txt = build_wporg_readme( $version, $contributors, $tested_up_to );

	if ( file_put_contents( REPO_ROOT . '/readme.txt', $readme_txt ) !== false ) {
		success( 'Generated readme.txt.' );
	} else {
		fail( 'Failed to generate readme.txt.' );
	}
}

/**
 * Sync the GatherPress Alpha plugin's version header to match core.
 *
 * @param string $version The full plugin version.
 * @return void
 */
function update_alpha_version( $version ) {
	$alpha_file = dirname( REPO_ROOT ) . '/gatherpress-alpha/gatherpress-alpha.php';

	if ( ! file_exists( $alpha_file ) ) {
		warning(
			'GatherPress Alpha plugin not found alongside core; skipping alpha version sync. '
			. 'Open its lockstep version PR separately.'
		);

		return;
	}

	update_version_in_file(
		$alpha_file,
		'/^(\s*\*\s*Version:\s*)([\w\.-]+)(\s*)$/mi',
		$version,
		'GatherPress Alpha'
	);
}

/**
 * Generate SECURITY.md for core (and alpha when present) from the shared template.
 *
 * @param string $version The full plugin version.
 * @return void
 */
function generate_security( $version ) {
	if ( ! preg_match( '/^(\d+\.\d+)/', $version, $matches ) ) {
		fail( "Could not derive major.minor from version: {$version}" );
	}

	$major_minor = $matches[1];

	$table  = "| Version | Supported          |\n";
	$table .= "| ------- | ------------------ |\n";
	$table .= "| {$major_minor}.x  | :white_check_mark: |\n";
	$table .= "| < {$major_minor}  | :x:                |";

	$content = str_replace( '{{SUPPORTED_VERSIONS_TABLE}}', $table, read_part( 'shared/security.md' ) );

	$targets = array(
		'core'  => REPO_ROOT . '/SECURITY.md',
		'alpha' => dirname( REPO_ROOT ) . '/gatherpress-alpha/SECURITY.md',
	);

	foreach ( $targets as $label => $file ) {
		if ( ! is_dir( dirname( $file ) ) ) {
			warning( "{$label} plugin directory not found; skipping its SECURITY.md." );

			continue;
		}

		if ( file_put_contents( $file, $content ) !== false ) {
			success( "Generated SECURITY.md ({$label}) — supported {$major_minor}.x / < {$major_minor}." );
		} else {
			fail( "Failed to write SECURITY.md ({$label})." );
		}
	}
}

// ---------------------------------------------------------------------------
// Main.
// ---------------------------------------------------------------------------

$options = getopt( '', array( 'version:' ) );

if (
	empty( $options['version'] )
	|| ! preg_match( '/^\d+\.\d+\.\d+(-(alpha|beta|rc)\.\d+)?$/', $options['version'] )
) {
	fail( 'Usage: php .github/scripts/release/generate-version.php --version=X.Y.Z[-alpha.N|-beta.N|-rc.N]' );
}

$version = $options['version'];

$contributors = generate_credits( $version );

update_version_in_file(
	REPO_ROOT . '/gatherpress.php',
	'/^(\s*\*\s*Version:\s*)([\w\.-]+)(\s*)$/mi',
	$version,
	'plugin'
);

generate_readmes( $version, $contributors );

update_version_in_file(
	REPO_ROOT . '/package.json',
	'/^(\s*"version": ")([\w\.-]+)(",)$/mi',
	$version,
	'package.json'
);

echo "\n";
echo "Next step (on a machine with Node):\n";
echo "  npm i --package-lock-only\n";
echo "\n";
echo "That refreshes package-lock.json to match the new package.json version.\n";

update_alpha_version( $version );
generate_security( $version );
