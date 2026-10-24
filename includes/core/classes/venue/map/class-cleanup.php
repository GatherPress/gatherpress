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
	 * Transient key used to prevent concurrent cleanup passes.
	 *
	 * @since TBD
	 * @var string
	 */
	const LOCK_TRANSIENT = 'gatherpress_venue_map_gc_lock';

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
	 * Transient key storing the resume filename after hitting per-run cap.
	 *
	 * @since TBD
	 * @var string
	 */
	const RESUME_TRANSIENT = 'gatherpress_venue_map_gc_resume';

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
	const FILENAME_REGEX = '/^[a-z0-9_-]+-\d+-\d+-\d+(@\d+x)?\.png$/i';

	/**
	 * Check if a filename matches the static map naming convention.
	 *
	 * Only filenames conforming to `{slug}-{zoom}-{width}-{height}.png`
	 * (including provider, map type segments, and optional retina `@2x` suffix)
	 * are considered valid static map images.
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
	 * legacy `gatherpress_venue_static_map` meta.
	 *
	 * @since TBD
	 *
	 * @return array<string, bool> Map of in-use image basenames to true.
	 */
	public function get_in_use_basenames(): array {
		$types = get_post_types_by_support( Venue::SUPPORT );

		if ( empty( $types ) ) {
			return array();
		}

		$in_use     = array();
		$batch_size = (int) apply_filters( 'gatherpress_venue_map_gc_scan_batch_size', self::SCAN_BATCH_SIZE );
		$page       = 1;

		while ( true ) {
			$post_ids = get_posts(
				array(
					'post_type'              => $types,
					'post_status'            => array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' ),
					'posts_per_page'         => $batch_size,
					'paged'                  => $page,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( empty( $post_ids ) ) {
				break;
			}

			foreach ( $post_ids as $post_id ) {
				$raw = get_post_meta( (int) $post_id, Map::META_KEY, true );

				if ( empty( $raw ) ) {
					$raw = get_post_meta( (int) $post_id, 'gatherpress_venue_static_map', true );
				}

				if ( is_array( $raw ) ) {
					array_walk_recursive(
						$raw,
						static function ( $val ) use ( &$in_use ) {
							if ( is_string( $val ) && str_ends_with( $val, '.png' ) ) {
								$basename = wp_basename( (string) wp_parse_url( $val, PHP_URL_PATH ) );

								if ( '' !== $basename ) {
									$in_use[ $basename ] = true;
								}
							}
						}
					);
				}
			}

			if ( count( $post_ids ) < $batch_size ) {
				break;
			}

			++$page;
		}

		/**
		 * Filters the list of in-use static-map image filenames before garbage collection.
		 *
		 * @since TBD
		 *
		 * @param array<string, bool> $in_use Map of in-use image basenames.
		 */
		return (array) apply_filters( 'gatherpress_venue_map_gc_in_use_files', $in_use );
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
		if ( '.' === $entry || '..' === $entry || ! $this->is_valid_filename( $entry ) ) {
			return false;
		}

		$full_path = trailingslashit( $dir ) . $entry;

		if ( is_dir( $full_path ) || isset( $in_use[ $entry ] ) ) {
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
	protected function unlink_file( string $full_path ): bool {
		if ( function_exists( 'wp_delete_file' ) ) {
			$deleted = wp_delete_file( $full_path );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			$deleted = unlink( $full_path );
		}

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
	 *     bytes_reclaimed: int,
	 *     files: string[],
	 *     resumed: bool,
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
			'bytes_reclaimed' => 0,
			'files'           => array(),
			'resumed'         => false,
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

			if ( null === $dir || ! is_dir( $dir ) ) {
				return $result;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_opendir -- Streaming dir handle avoids glob() memory spikes.
			$handle = opendir( $dir );

			if ( false === $handle ) {
				return $result;
			}

			$in_use = $this->get_in_use_basenames();
			$limit  = max( 1, $limit );
			$now    = time();

			$resume_file        = $dry_run ? '' : (string) get_transient( self::RESUME_TRANSIENT );
			$skip_until_resumed = ( '' !== $resume_file );
			$result['resumed']  = $skip_until_resumed;

			// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition, WordPress.WP.AlternativeFunctions.file_system_operations_readdir -- Streaming dir handle avoids glob() memory spikes.
			while ( false !== ( $entry = readdir( $handle ) ) ) {
				if ( '.' === $entry || '..' === $entry || ! $this->is_valid_filename( $entry ) ) {
					continue;
				}

				$full_path = trailingslashit( $dir ) . $entry;

				if ( is_dir( $full_path ) ) {
					continue;
				}

				++$result['scanned'];

				if ( $skip_until_resumed ) {
					if ( $entry === $resume_file ) {
						$skip_until_resumed = false;
					}
					continue;
				}

				if ( ! $this->is_candidate( $entry, $dir, $in_use, $now ) ) {
					continue;
				}

				$size = (int) filesize( $full_path );
				++$result['orphans'];
				$result['files'][] = $entry;

				if ( ! $dry_run ) {
					if ( $this->unlink_file( $full_path ) ) {
						++$result['deleted'];
						$result['bytes_reclaimed'] += $size;

						if ( $result['deleted'] >= $limit ) {
							$result['hit_limit'] = true;
							set_transient( self::RESUME_TRANSIENT, $entry, HOUR_IN_SECONDS );
							break;
						}
					}
				} else {
					$result['bytes_reclaimed'] += $size;

					if ( count( $result['files'] ) >= $limit ) {
						$result['hit_limit'] = true;
						break;
					}
				}
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_closedir -- Streaming dir handle.
			closedir( $handle );

			if ( ! $dry_run && ! $result['hit_limit'] ) {
				delete_transient( self::RESUME_TRANSIENT );
			}
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}

		return $result;
	}
}
