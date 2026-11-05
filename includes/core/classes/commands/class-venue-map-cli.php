<?php
/**
 * Class responsible for WP-CLI commands related to venue maps within GatherPress.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Commands;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Venue\Map\Cleanup;
use WP_CLI;

/**
 * WP-CLI commands for managing venue static maps within GatherPress.
 *
 * @package GatherPress\Core
 * @since TBD
 */
final class Venue_Map_Cli extends WP_CLI {

	/**
	 * Garbage collect unused venue static-map images.
	 *
	 * ## OPTIONS
	 *
	 * [--execute]
	 * : Actually delete orphaned static map images. Defaults to dry-run.
	 *
	 * [--verbose]
	 * : Output each candidate file being inspected or deleted.
	 *
	 * [--limit=<number>]
	 * : Maximum number of files to unlink in one run. Defaults to 1000.
	 *
	 * [--force]
	 * : Bypass concurrency lock.
	 *
	 * ## EXAMPLES
	 *
	 *    # Dry run: list orphaned static map files without deleting.
	 *    $ wp gatherpress venue-map gc
	 *
	 *    # Delete orphaned static map images.
	 *    $ wp gatherpress venue-map gc --execute
	 *
	 *    # Delete with verbose candidate output.
	 *    $ wp gatherpress venue-map gc --execute --verbose
	 *
	 * @since TBD
	 *
	 * @param string[]                   $args       Positional arguments.
	 * @param array<string, string|bool> $assoc_args Associative arguments.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function gc( array $args = array(), array $assoc_args = array() ): void {
		$execute = ! empty( $assoc_args['execute'] );
		$verbose = ! empty( $assoc_args['verbose'] );
		$force   = ! empty( $assoc_args['force'] );
		$limit   = ! empty( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : Cleanup::DEFAULT_LIMIT;

		$dry_run = ! $execute;
		$cleanup = Cleanup::get_instance();
		$result  = $cleanup->sweep( $dry_run, $limit, (bool) $force );

		if ( $result['locked'] ) {
			self::error( __( 'A cleanup sweep is currently in progress. Use --force to override.', 'gatherpress' ) );

			return; // @phpstan-ignore deadCode.unreachable
		}

		if ( $verbose && ! empty( $result['files'] ) ) {
			$prefix = $dry_run ? '[orphan]' : '[deleted]';

			foreach ( $result['files'] as $file ) {
				self::line( sprintf( '%s %s', $prefix, $file ) );
			}
		}

		$formatted_size = size_format( $result['bytes_reclaimed'] );

		if ( $dry_run ) {
			self::success(
				sprintf(
					/* translators: 1: Number of files, 2: Formatted reclaimed byte size. */
					__(
						'Dry run complete. Found %1$d orphaned file(s) (%2$s). Run with --execute to delete.',
						'gatherpress'
					),
					$result['orphans'],
					$formatted_size
				)
			);
		} else {
			self::success(
				sprintf(
					/* translators: 1: Number of files, 2: Formatted reclaimed byte size. */
					__( 'Cleanup complete. Deleted %1$d file(s) (%2$s reclaimed).', 'gatherpress' ),
					$result['deleted'],
					$formatted_size
				)
			);
		}

		if ( $result['hit_limit'] ) {
			self::warning(
				__( 'Reached per-run deletion limit. Run the command again to continue sweeping.', 'gatherpress' )
			);
		}
	}
}
