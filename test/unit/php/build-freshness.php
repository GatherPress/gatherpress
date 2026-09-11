<?php
/**
 * Fails the PHP test run when `build/` is stale relative to `src/`.
 *
 * `build/` is gitignored and nothing regenerates it on a merge, a rebase or a
 * branch switch. `Blocks\Setup::register()` registers block types from
 * `build/blocks/`, never from `src/blocks/`, so a stale bundle means PHPUnit is
 * measuring the *previous* commit's block render templates while the working
 * tree shows the current one, so edits to a block's `render.php` under `src/`
 * are silently invisible to the suite until `build/` is regenerated.
 *
 * It is a purely local hazard, because CI builds before both the PHPUnit job
 * (`phpunit-tests.yml:67`) and the e2e job (`e2e-tests.yml:119`). That is why
 * the guard lives at the PHPUnit bootstrap rather than in a workflow. The
 * bootstrap is the only place that runs on *every* local invocation: an
 * `npm run pretest:*` hook is skipped by the `wp-env run … phpunit` form that
 * is typed directly, and a guard expressed as a test case
 * reports as one more red test among many, which is the mystery failure this is
 * meant to replace.
 *
 * @package GatherPress
 * @subpackage Tests
 * @since 0.36.0
 */

/**
 * Find the most recently modified path under a directory.
 *
 * Directories are stat'd alongside files, so a deletion or a rename registers
 * as a change even though no surviving file's own timestamp moved.
 *
 * Unlike its mirror below, this half takes no skip fragment. The exclusion
 * exists for `build/coverage-report`, which only ever needs excluding from the
 * build side of the comparison; the source tree has no equivalent, so a skip
 * parameter here would be a branch no caller can reach.
 *
 * @since 0.36.0
 *
 * @param string $directory Absolute directory to walk.
 *
 * @return int The newest modification time found, or 0 for an empty directory.
 */
function gatherpress_newest_mtime( string $directory ): int {
	$newest   = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $info ) {
		$newest = max( $newest, (int) $info->getMTime() );
	}

	return $newest;
}

/**
 * Find the least recently modified *file* under a directory.
 *
 * The mirror of `gatherpress_newest_mtime()`, and the half that makes the
 * guard a per-artifact statement rather than a per-tree one: comparing the two
 * trees' newest entries only proves that *something* in `build/` was written
 * after the last source edit. One freshly emitted artifact is enough to
 * satisfy that while every other artifact stays stale, which is exactly what a
 * partial or interrupted build leaves behind.
 *
 * Directories are excluded here even though `gatherpress_newest_mtime()`
 * includes them. A directory's mtime only moves when an entry is added or
 * removed, so a rebuild that rewrites file contents without changing the file
 * list would leave old directory timestamps behind and fail the guard for no
 * reason. On the source side that same property is the point, since it is how
 * a deletion registers, which is why only this side drops them.
 *
 * @since 0.36.0
 *
 * @param string $directory Absolute directory to walk.
 * @param string $skip      Path fragment to exclude, or '' to exclude nothing.
 *
 * @return int The oldest file modification time found, or 0 when the directory holds no files.
 */
function gatherpress_oldest_file_mtime( string $directory, string $skip = '' ): int {
	$oldest   = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $path => $info ) {
		if ( ! $info->isFile() || ( '' !== $skip && str_contains( (string) $path, $skip ) ) ) {
			continue;
		}

		$mtime  = (int) $info->getMTime();
		$oldest = ( 0 === $oldest ) ? $mtime : min( $oldest, $mtime );
	}

	return $oldest;
}

/**
 * Abort the run with an explanation of what to do about it.
 *
 * @since 0.36.0
 *
 * @param string $reason Why the build is not usable.
 *
 * @return void
 */
function gatherpress_fail_stale_build( string $reason ): void {
	// STDERR is a stream, not a file, and WordPress is not loaded yet at
	// bootstrap time, so WP_Filesystem does not exist to be used here.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fwrite(
		STDERR,
		PHP_EOL
			. 'GatherPress: refusing to run the PHP test suite against a stale build.' . PHP_EOL
			. $reason . PHP_EOL
			. 'PHPUnit registers blocks from build/blocks/, not src/blocks/, so every block-render'
			. ' assertion below would measure the previous build.' . PHP_EOL
			. 'Fix: npm run build' . PHP_EOL . PHP_EOL
	);

	exit( 1 );
}

/**
 * Refuse to run when `build/` is missing, empty, or partially older than `src/`.
 *
 * The comparison is `newest source` against the **oldest emitted artifact**,
 * not against the newest one. Comparing the two trees' newest entries relates
 * no input to its own output: source A can be edited at t=20, its stale
 * artifact can still read t=15, and one unrelated artifact written at t=30
 * satisfies `max(build) > max(src)` while PHPUnit goes on loading A's previous
 * output. Requiring every artifact to be newer than every source is the
 * per-pair statement expressed without having to model which source emits
 * which artifact. That mapping is webpack's, it changes with the entry
 * configuration, and this file has no honest way to reproduce it.
 *
 * `npm run build` rewrites the whole tree on every invocation, so the
 * conservative direction costs nothing on a complete build and fails exactly
 * the partial/interrupted one. An empty `build/` reads as oldest = 0 and fails
 * the same way a missing one does.
 *
 * `build/coverage-report` is excluded from the build side of the comparison
 * because `npm run test:unit:php` writes its HTML coverage report there. Left
 * in, it would make `build/` look newer than anything under the old
 * newest-versus-newest rule, and under this one it would instead be the only
 * genuinely fresh tree while the artifacts stayed stale. Both directions are
 * wrong, and silently so, which is the failure mode this guard exists to
 * prevent.
 *
 * @since 0.36.0
 *
 * @return void
 */
function gatherpress_assert_build_is_fresh(): void {
	$root  = dirname( __DIR__, 3 );
	$src   = $root . '/src';
	$build = $root . '/build';

	if ( ! is_dir( $build ) ) {
		gatherpress_fail_stale_build( sprintf( 'No build directory at %s.', $build ) );
	}

	$newest_src   = gatherpress_newest_mtime( $src );
	$oldest_build = gatherpress_oldest_file_mtime( $build, '/coverage-report' );

	if ( 0 === $oldest_build ) {
		gatherpress_fail_stale_build( sprintf( 'The build directory at %s holds no build output.', $build ) );
	}

	if ( $newest_src <= $oldest_build ) {
		return;
	}

	gatherpress_fail_stale_build(
		sprintf(
			'src/ was modified at %s, after the oldest build/ artifact was written at %s.',
			gmdate( 'Y-m-d H:i:s', $newest_src ),
			gmdate( 'Y-m-d H:i:s', $oldest_build )
		)
	);
}

gatherpress_assert_build_is_fresh();
