#!/usr/bin/env php
<?php
/**
 * Turn the PR-number markers in CHANGELOG.md into inline links.
 *
 * `changelogger write --add-pr-num` appends plain `[#123]` markers to change
 * entries but never links them (see issue #1896; the appended string is
 * hard-coded in jetpack-changelogger's Utils.php), and GitHub does not
 * auto-link `#123` in rendered Markdown files. This script rewrites every
 * unlinked marker as an inline link:
 *
 *     [#123](https://github.com/GatherPress/gatherpress/pull/123)
 *
 * Inline (rather than reference-style) links keep each entry self-contained:
 * the release workflow extracts a version's section out of CHANGELOG.md as
 * the GitHub Release body, and any links relying on definitions elsewhere in
 * the file would break in every such extraction.
 *
 * Runs as the last step of `composer changelog:write` and is idempotent —
 * already-linked markers are left untouched. Entry content round-trips
 * verbatim through changelogger's parser, so the links survive future
 * release rewrites.
 *
 * @package GatherPress
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
 */

$changelog_file = dirname( __DIR__, 2 ) . '/CHANGELOG.md';
$contents       = file_get_contents( $changelog_file );

if ( false === $contents ) {
	fwrite( STDERR, "Unable to read {$changelog_file}\n" );
	exit( 1 );
}

// Match `[#123]` not already followed by a link (`(`) and not a reference
// definition (`:`).
$updated = preg_replace(
	'/\[#(\d+)\](?![(:])/',
	'[#$1](https://github.com/GatherPress/gatherpress/pull/$1)',
	$contents,
	-1,
	$count
);

if ( null === $updated ) {
	fwrite( STDERR, "Failed to process {$changelog_file}\n" );
	exit( 1 );
}

if ( 0 === $count ) {
	echo "All PR references in CHANGELOG.md are already linked.\n";
	exit( 0 );
}

file_put_contents( $changelog_file, $updated );

echo "Linked {$count} PR reference(s) in CHANGELOG.md.\n";
