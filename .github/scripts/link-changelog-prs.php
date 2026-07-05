#!/usr/bin/env php
<?php
/**
 * Append GitHub PR link definitions to CHANGELOG.md.
 *
 * `changelogger write --add-pr-num` appends plain `[#123]` markers to change
 * entries but never emits matching Markdown link definitions, so the markers
 * render as literal bracketed text (see issue #1896; the appended string is
 * hard-coded in jetpack-changelogger's Utils.php). This script adds a
 * `[#123]: https://github.com/GatherPress/gatherpress/pull/123` reference
 * definition at the end of the file for every marker that lacks one, which
 * turns every `[#123]` in the document into a link.
 *
 * Runs as the last step of `composer changelog:write`. Changelogger's own
 * parser preserves the added definitions across future rewrites: link
 * definitions not consumed by version headings round-trip through the
 * changelog epilogue (KeepAChangelogParser::parse).
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

// Every `[#123]` marker in the document...
preg_match_all( '/\[#(\d+)\]/', $contents, $used );
// ...and every `[#123]:` link definition that already exists.
preg_match_all( '/^\[#(\d+)\]:/m', $contents, $defined );

$missing = array_diff( array_unique( $used[1] ), $defined[1] );

if ( empty( $missing ) ) {
	echo "All PR references in CHANGELOG.md already have link definitions.\n";
	exit( 0 );
}

// Newest first, matching the version links above them.
rsort( $missing, SORT_NUMERIC );

$definitions = '';

foreach ( $missing as $number ) {
	$definitions .= "[#{$number}]: https://github.com/GatherPress/gatherpress/pull/{$number}\n";
}

file_put_contents( $changelog_file, rtrim( $contents, "\n" ) . "\n" . $definitions );

echo 'Added ' . count( $missing ) . " PR link definition(s) to CHANGELOG.md.\n";
