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
 * Fold pending credits/unreleased.json entries into the version entry.
 *
 * The credits-sync automation (#1828) accumulates newly-merged contributors
 * in credits/unreleased.json between releases. At bump time they move into
 * the target version's file (which stays the source of truth) and the
 * staging file is emptied — both writes land in the version-bump commit.
 *
 * The optional `noteworthy` array in the same file lets a lead queue a
 * promotion when they decide it, rather than having to remember it at the
 * next bump. It is hand-edited: the sync automation only ever appends to
 * `contributors`, because who belongs in noteworthy is a judgment call the
 * leads make and no tool can see. A queued name already sitting in
 * `contributors` moves groups rather than appearing twice, and anyone
 * already in `leads` is left alone.
 *
 * @param array  $entry        The decoded credits entry for the version.
 * @param string $credits_file Absolute path to the version's credits file.
 * @param string $version      The plugin version (for messages).
 * @return array The entry with pending credits folded in.
 */
function fold_unreleased_credits( $entry, $credits_file, $version ) {
	$unreleased_file = SCRIPT_ROOT . '/credits/unreleased.json';

	if ( ! file_exists( $unreleased_file ) ) {
		return $entry;
	}

	$unreleased = json_decode( file_get_contents( $unreleased_file ), true );
	$pending    = isset( $unreleased['contributors'] ) && is_array( $unreleased['contributors'] )
		? $unreleased['contributors']
		: array();
	$promoting  = isset( $unreleased['noteworthy'] ) && is_array( $unreleased['noteworthy'] )
		? $unreleased['noteworthy']
		: array();

	$leads    = isset( $entry['leads'] ) ? $entry['leads'] : array();
	$promoted = array_values(
		array_diff(
			array_unique( $promoting ),
			$leads,
			isset( $entry['noteworthy'] ) ? $entry['noteworthy'] : array()
		)
	);

	if ( ! empty( $promoted ) ) {
		$entry['noteworthy'] = array_merge(
			isset( $entry['noteworthy'] ) ? $entry['noteworthy'] : array(),
			$promoted
		);

		// A promoted name credited for this release as a contributor moves
		// groups instead of being listed in both.
		if ( isset( $entry['contributors'] ) ) {
			$entry['contributors'] = array_values( array_diff( $entry['contributors'], $promoted ) );
		}
	}

	// Already-credited people (any group) don't get re-added.
	$credited = array_merge(
		$leads,
		isset( $entry['noteworthy'] ) ? $entry['noteworthy'] : array(),
		isset( $entry['contributors'] ) ? $entry['contributors'] : array()
	);
	$new      = array_values( array_diff( array_unique( $pending ), $credited ) );

	if ( ! empty( $new ) ) {
		$entry['contributors'] = array_merge(
			isset( $entry['contributors'] ) ? $entry['contributors'] : array(),
			$new
		);
	}

	if ( empty( $new ) && empty( $promoted ) ) {
		return $entry;
	}

	$json_flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

	if ( file_put_contents( $credits_file, json_encode( $entry, $json_flags ) . "\n" ) === false ) {
		fail( "Failed to fold unreleased credits into credits/{$version}.json." );
	}

	$reset_json = json_encode(
		array(
			'contributors' => array(),
			'noteworthy'   => array(),
		),
		$json_flags
	) . "\n";

	if ( file_put_contents( $unreleased_file, $reset_json ) === false ) {
		fail( 'Failed to reset credits/unreleased.json.' );
	}

	if ( ! empty( $promoted ) ) {
		success(
			'Promoted ' . count( $promoted ) . " contributor(s) to noteworthy in credits/{$version}.json: "
			. implode( ', ', $promoted ) . '.'
		);
	}

	if ( ! empty( $new ) ) {
		success(
			'Folded ' . count( $new ) . " unreleased contributor(s) into credits/{$version}.json: "
			. implode( ', ', $new ) . '.'
		);
	}

	return $entry;
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

	$entry = fold_unreleased_credits( $entry, $credits_file, $version );

	$data['version'] = $version;
	$contributors    = array();

	// Fixed group order: leads and noteworthy drive readme.txt's Contributors
	// line and the credits page ordering regardless of file key order.
	foreach ( array( 'leads', 'noteworthy', 'contributors' ) as $group ) {
		$users = isset( $entry[ $group ] ) && is_array( $entry[ $group ] ) ? $entry[ $group ] : array();

		if ( 'contributors' === $group ) {
			sort( $users );
		}

		// Only leads + noteworthy land in the wp.org plugin header's
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
 * Resolve `@since TBD` docblocks to the version being released.
 *
 * New symbols are authored with `@since TBD`, because the version they will
 * ship in is not knowable while the work is in flight. A fix written on
 * develop may go out in the next minor, or be cherry-picked into a patch
 * release first, and only the branch being versioned knows which.
 *
 * Resolving here rather than on merge is what makes the patch flow correct:
 * a cherry-picked fix carries `TBD` onto the `version-X.Y.N` branch, and
 * this run stamps it with the patch version it is actually shipping in.
 *
 * Pre-releases are skipped. An alpha is not the version a symbol shipped in,
 * and stamping one would freeze the answer before a patch release could
 * claim it.
 *
 * @param string $version The version being generated.
 * @return void
 */
function resolve_since_tags( $version ) {
	if ( preg_match( '/-(alpha|beta|rc)\./', $version ) ) {
		warning( "Leaving @since TBD alone for the {$version} pre-release; only a stable version can answer it." );
		return;
	}

	$files   = since_source_files();
	$updated = 0;
	$total   = 0;

	foreach ( $files as $file ) {
		$contents = file_get_contents( $file );

		if ( $contents === false || strpos( $contents, 'TBD' ) === false ) {
			continue;
		}

		$count        = 0;
		$new_contents = preg_replace( '/(@since\s+)TBD\b/', '${1}' . $version, $contents, -1, $count );

		if ( $count === 0 ) {
			continue;
		}

		if ( file_put_contents( $file, $new_contents ) === false ) {
			fail( "Failed to write {$file} while resolving @since TBD." );
		}

		++$updated;
		$total += $count;
	}

	if ( $total === 0 ) {
		success( 'No @since TBD tags to resolve.' );
		return;
	}

	success( "Resolved {$total} @since TBD tag(s) to {$version} across {$updated} file(s)." );

	// The release must not ship a docblock that still says TBD, so the write
	// is verified rather than assumed.
	$leftovers = array();

	foreach ( since_source_files() as $file ) {
		$contents = file_get_contents( $file );

		if ( $contents !== false && preg_match( '/@since\s+TBD\b/', $contents ) ) {
			$leftovers[] = str_replace( REPO_ROOT . '/', '', $file );
		}
	}

	if ( ! empty( $leftovers ) ) {
		fail( 'Unresolved @since TBD remains in: ' . implode( ', ', $leftovers ) );
	}
}

/**
 * Source files that may carry a docblock.
 *
 * Only the shipped source is walked. Generated hook docs pick the resolved
 * values up on their own regen, and `build/` is rebuilt from `src/`.
 *
 * @return string[] Absolute paths.
 */
function since_source_files() {
	$files = array();

	foreach ( array( '/includes', '/src' ) as $relative ) {
		$root = REPO_ROOT . $relative;

		if ( ! is_dir( $root ) ) {
			continue;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			if ( ! in_array( strtolower( $file->getExtension() ), array( 'php', 'js' ), true ) ) {
				continue;
			}

			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	return $files;
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

// Docblocks authored as `@since TBD` become the version now being released.
resolve_since_tags( $version );

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
