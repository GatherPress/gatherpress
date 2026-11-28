<?php
/**
 * Class handles static-map garbage collection.
 *
 * Scans on-disk venue map images and removes orphaned files that are no
 * longer referenced by any venue descriptor in post metadata.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Map\Map;
use GatherPress\Core\Venue\Venue;

/**
 * Class Cleanup.
 *
 * Core sweeper that handles detecting and deleting unreferenced venue static-map images.
 *
 * @since TBD
 */
class Cleanup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cron action for the scheduled cleanup.
	 *
	 * @since TBD
	 * @var string
	 */
	const CRON_ACTION = 'gatherpress_venue_map_cleanup';

	/**
	 * Transient key used to prevent concurrent cleanup passes.
	 *
	 * @since TBD
	 * @var string
	 */
	const LOCK_TRANSIENT = 'gatherpress_venue_map_cleanup_lock';

	/**
	 * Time-to-live for the concurrency lock in seconds.
	 *
	 * @since TBD
	 * @var int
	 */
	const LOCK_TTL = 60;

	/**
	 * Race-guard window in seconds. Files modified within this window are skipped.
	 *
	 * @since TBD
	 * @var int
	 */
	const RACE_GUARD_SECONDS = 300;

	/**
	 * Default maximum number of files to unlink in a single execution.
	 *
	 * @since TBD
	 * @var int
	 */
	const DEFAULT_LIMIT = 1000;

	/**
	 * Default number of posts to inspect per page when scanning venue metadata.
	 *
	 * @since TBD
	 * @var int
	 */
	const SCAN_BATCH_SIZE = 100;

	/**
	 * Allow-list regex for valid static map filenames.
	 *
	 * @since TBD
	 * @var string
	 */
	const FILENAME_REGEX = '/^[a-z0-9_.-]+-\d+-\d+-\d+(-[0-9a-f]{8})?(@\d+x)?\.png$/i';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for the scheduled cleanup.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::CRON_ACTION, array( $this, 'run_scheduled' ) );
	}

	/**
	 * Keep the cleanup scheduled at the frequency set under Venues > Maps.
	 *
	 * Reschedules when the setting changes, and removes the event when it is
	 * set to never.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function schedule(): void {
		$frequency = (string) Settings::get_instance()->get( 'static_map_cleanup' );
		$scheduled = wp_get_schedule( self::CRON_ACTION );

		if ( $frequency === $scheduled ) {
			return;
		}

		if ( false !== $scheduled ) {
			wp_unschedule_hook( self::CRON_ACTION );
		}

		if ( in_array( $frequency, array( 'daily', 'weekly' ), true ) ) {
			wp_schedule_event( time(), $frequency, self::CRON_ACTION );
		}
	}

	/**
	 * Delete orphaned static map images on the cleanup schedule.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function run_scheduled(): void {
		$this->sweep( false );
	}

	/**
	 * Check if a filename matches the static map naming convention.
	 *
	 * Only filenames conforming to `{slug}-{zoom}-{width}-{height}.png`
	 * (including provider, map type segments, the optional eight-character
	 * map hash from `Saved_Image::versioned()`, and an optional retina `@2x`
	 * suffix) are considered valid static map images.
	 *
	 * @since TBD
	 *
	 * @param string $filename Filename to test.
	 *
	 * @return bool True if valid static map filename, false otherwise.
	 */
	public function is_valid_filename( string $filename ): bool {
		return 1 === preg_match( self::FILENAME_REGEX, $filename );
	}

	/**
	 * Get absolute path to the static maps uploads directory.
	 *
	 * @since TBD
	 *
	 * @return string|null Absolute directory path, or null if uploads directory cannot be resolved.
	 */
	public function get_uploads_dir(): ?string {
		$dirs = wp_get_upload_dir();

		if ( ! empty( $dirs['error'] ) || empty( $dirs['basedir'] ) ) {
			return null;
		}

		return trailingslashit( $dirs['basedir'] ) . Map::UPLOADS_SUBDIR;
	}

	/**
	 * Collect basenames of all static map images currently referenced in venue metadata.
	 *
	 * Scans all post types declaring `gatherpress-venue-information` support across
	 * all post statuses, collecting image URLs stored under `Map::META_KEY` and
	 * legacy `gatherpress_venue_static_map` meta. Images queued in a delayed
	 * `Saved_Image::DELETE_CRON_ACTION` event also count as in use, so they stay
	 * for the full delay.
	 *
	 * @since TBD
	 *
	 * @return array<string, bool> Map of in-use image basenames to true.
	 */
	public function get_in_use_basenames(): array {
		// A replaced image waits a week in a delete event for cached pages, so keep it until then.
		$in_use = array_fill_keys( array_map( 'wp_basename', Saved_Image::queued_urls() ), true );
		$types  = get_post_types_by_support( Venue::SUPPORT );

		if ( empty( $types ) ) {
			return $in_use;
		}

		/**
		 * Filters how many venues have their map data read per batch when finding the static maps in use.
		 *
		 * @since TBD
		 *
		 * @param int $batch_size Number of venues read per batch.
		 */
		$batch_size = (int) apply_filters( 'gatherpress_venue_map_cleanup_scan_batch_size', self::SCAN_BATCH_SIZE );
		$batch_size = max( 1, $batch_size );

		// Every ID up front, in any status, so a venue deleted mid-scan cannot shift a page.
		$all_ids = get_posts(
			array(
				'post_type'              => $types,
				'post_status'            => array_keys( get_post_stati() ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( array_chunk( $all_ids, $batch_size ) as $post_ids ) {
			update_meta_cache( 'post', $post_ids );

			foreach ( $post_ids as $post_id ) {
				$raw = get_post_meta( (int) $post_id, Map::META_KEY, true );

				if ( empty( $raw ) ) {
					$raw = get_post_meta( (int) $post_id, 'gatherpress_venue_static_map', true );
				}

				if ( is_array( $raw ) ) {
					array_walk_recursive(
						$raw,
						static function ( $val ) use ( &$in_use ) {
							$path = is_string( $val ) ? (string) wp_parse_url( $val, PHP_URL_PATH ) : '';

							if ( str_ends_with( $path, '.png' ) ) {
								$in_use[ wp_basename( $path ) ] = true;
							}
						}
					);
				}
			}
		}

		/**
		 * Filters the list of in-use static-map image filenames before garbage collection.
		 *
		 * @since TBD
		 *
		 * @param array<string, bool> $in_use Map of in-use image basenames.
		 */
		return (array) apply_filters( 'gatherpress_venue_map_cleanup_in_use_files', $in_use );
	}

	/**
	 * Determine whether an on-disk directory entry is an orphaned static map candidate.
	 *
	 * @since TBD
	 *
	 * @param string              $entry  Directory entry name.
	 * @param string              $dir    Uploads directory path.
	 * @param array<string, bool> $in_use Map of in-use image basenames.
	 * @param int                 $now    Current Unix timestamp.
	 *
	 * @return bool True if entry is an unreferenced static map image past the race guard window.
	 */
	public function is_candidate( string $entry, string $dir, array $in_use, int $now ): bool {
		$full_path = trailingslashit( $dir ) . $entry;

		if ( isset( $in_use[ $entry ] ) || ! is_file( $full_path ) ) {
			return false;
		}

		$mtime = filemtime( $full_path );

		return false !== $mtime && $mtime < ( $now - self::RACE_GUARD_SECONDS );
	}

	/**
	 * Unlink an orphaned static map image file.
	 *
	 * @since TBD
	 *
	 * @param string $full_path Absolute path to the file.
	 *
	 * @return bool True if file was deleted, false otherwise.
	 */
	public function unlink_file( string $full_path ): bool {
		$deleted = wp_delete_file( $full_path );

		return $deleted || ! file_exists( $full_path );
	}

	/**
	 * Perform garbage-collection sweep of unused static-map images.
	 *
	 * @since TBD
	 *
	 * @param bool $dry_run Whether to execute actual deletions or only report candidates.
	 * @param int  $limit   Maximum number of files to delete in one run.
	 * @param bool $force   Whether to bypass transient lock.
	 *
	 * @return array{
	 *     success: bool,
	 *     locked: bool,
	 *     scanned: int,
	 *     orphans: int,
	 *     deleted: int,
	 *     failed: int,
	 *     bytes_reclaimed: int,
	 *     files: string[],
	 *     failed_files: string[],
	 *     hit_limit: bool
	 * } Sweep summary statistics.
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	public function sweep( bool $dry_run = true, int $limit = self::DEFAULT_LIMIT, bool $force = false ): array {
		$result = array(
			'success'         => true,
			'locked'          => false,
			'scanned'         => 0,
			'orphans'         => 0,
			'deleted'         => 0,
			'failed'          => 0,
			'bytes_reclaimed' => 0,
			'files'           => array(),
			'failed_files'    => array(),
			'hit_limit'       => false,
		);

		if ( ! $force && (bool) get_transient( self::LOCK_TRANSIENT ) ) {
			$result['success'] = false;
			$result['locked']  = true;

			return $result;
		}

		set_transient( self::LOCK_TRANSIENT, time(), self::LOCK_TTL );

		try {
			$dir = $this->get_uploads_dir();

			if ( null === $dir ) {
				$result['success'] = false;

				return $result;
			}

			// No directory yet means no maps have been saved, which is a clean state.
			if ( ! is_dir( $dir ) ) {
				return $result;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_opendir -- Streaming dir handle avoids glob() memory spikes.
			$handle = opendir( $dir );

			if ( false === $handle ) {
				$result['success'] = false;

				return $result;
			}

			$in_use = $this->get_in_use_basenames();
			$limit  = max( 1, $limit );
			$now    = time();

			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition, WordPress.WP.AlternativeFunctions.file_system_operations_readdir -- Streaming dir handle avoids glob() memory spikes.
			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}

				$full_path = trailingslashit( $dir ) . $entry;

				if ( is_dir( $full_path ) ) {
					continue;
				}

				if ( ! $this->is_valid_filename( $entry ) ) {
					continue;
				}

				++$result['scanned'];

				if ( ! $this->is_candidate( $entry, $dir, $in_use, $now ) ) {
					continue;
				}

				$size = wp_filesize( $full_path );
				++$result['orphans'];

				if ( ! $dry_run ) {
					if ( ! $this->unlink_file( $full_path ) ) {
						++$result['failed'];
						$result['failed_files'][] = $entry;
						continue;
					}

					++$result['deleted'];
					$result['files'][]          = $entry;
					$result['bytes_reclaimed'] += $size;

					if ( $result['deleted'] >= $limit ) {
						$result['hit_limit'] = true;
						break;
					}
				} else {
					$result['files'][]          = $entry;
					$result['bytes_reclaimed'] += $size;

					if ( count( $result['files'] ) >= $limit ) {
						$result['hit_limit'] = true;
						break;
					}
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_closedir -- Streaming dir handle.
			closedir( $handle );
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}

		return $result;
	}
}
