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
	 * Delete static-map images that no venue uses anymore.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Delete the orphaned images. Without this flag, the command only reports them.
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
	 *    $ wp gatherpress venue-map cleanup
	 *
	 *    # Delete orphaned static map images.
	 *    $ wp gatherpress venue-map cleanup --apply
	 *
	 *    # Delete with verbose candidate output.
	 *    $ wp gatherpress venue-map cleanup --apply --verbose
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
	public function cleanup( array $args = array(), array $assoc_args = array() ): void {
		$apply   = filter_var( $assoc_args['apply'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$verbose = filter_var( $assoc_args['verbose'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$force   = filter_var( $assoc_args['force'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$limit   = Cleanup::DEFAULT_LIMIT;

		if ( isset( $assoc_args['limit'] ) ) {
			$raw_limit = (string) $assoc_args['limit'];

			if ( ! ctype_digit( $raw_limit ) || 0 === (int) $raw_limit ) {
				self::error( __( 'The --limit value must be a positive whole number.', 'gatherpress' ) );

				return; // @phpstan-ignore deadCode.unreachable
			}

			$limit = (int) $raw_limit;
		}

		$dry_run = ! $apply;
		$cleanup = Cleanup::get_instance();
		$result  = $cleanup->sweep( $dry_run, $limit, $force );

		if ( $result['locked'] ) {
			self::error( __( 'A cleanup sweep is currently in progress. Use --force to override.', 'gatherpress' ) );

			return; // @phpstan-ignore deadCode.unreachable
		}

		if ( ! $result['success'] ) {
			self::error( __( 'The static maps directory could not be read.', 'gatherpress' ) );

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
						'Dry run complete. Found %1$d orphaned file(s) (%2$s). Run with --apply to delete.',
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

		if ( $result['failed'] ) {
			self::warning(
				sprintf(
					/* translators: %d: Number of files that could not be deleted. */
					__( 'Could not delete %d file(s).', 'gatherpress' ),
					$result['failed']
				)
			);
		}

		if ( $result['hit_limit'] ) {
			self::warning(
				$dry_run
					? __( 'Stopped at the --limit value, so more orphaned files may exist.', 'gatherpress' )
					: __( 'Reached per-run deletion limit. Run the command again to continue sweeping.', 'gatherpress' )
			);
		}
	}
}
