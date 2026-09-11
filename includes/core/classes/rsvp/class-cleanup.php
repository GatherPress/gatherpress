<?php
/**
 * Class responsible for scheduling rsvp cleanup cron jobs.
 *
 * @since 0.34.0
 *
 * @package GatherPress\Core\Rsvp
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use WP_Comment;

/**
 * Class Cleanup.
 *
 * @since 0.34.0
 *
 * This class manages rsvp cleanup events.
 */
final class Cleanup {

	use Singleton;

	/**
	 * Initializes hooks needed for the cleanup cron event.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Sets up WordPress action hooks for managing cron scheduling and cleanup operations.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	private function setup_hooks(): void {
		add_action( 'init', array( $this, 'schedule_cleanup_cron' ) );
		add_action( 'gatherpress_rsvp_cleanup', array( $this, 'rsvp_cleanup' ), 10, 0 );
		add_action( 'update_option_gatherpress_settings', array( $this, 'reschedule_cleanup_cron' ), 10, 2 );
		add_action( 'delete_comment', array( $this, 'delete_term_relationships' ), 10, 2 );
	}

	/**
	 * Remove an RSVP's term relationships before its comment row disappears.
	 *
	 * `wp_delete_comment()` deletes the comment and its meta but never its term
	 * relationships, so every hard delete has been leaving orphaned
	 * `_gatherpress_rsvp_status` and `_gatherpress_rsvp_provider` rows behind.
	 * Those rows keep inflating term counts and nothing will ever collect them,
	 * because the object ID they point at is gone. This predates recurrence
	 * entirely; the occurrence taxonomy would simply have been the third
	 * leaking one.
	 *
	 * `delete_comment` fires before the row is removed, which is what keeps the
	 * object resolvable here. The three real hard-delete sites are the cleanup
	 * cron above, the RSVP list table, and WordPress emptying its own trash.
	 * `Rsvp\Storage::save()` calls `wp_delete_comment()` without the force
	 * flag, so it trashes rather than deletes and never reaches this.
	 *
	 * The delete also invalidates the RSVP caches the row was counted in.
	 * Neither hard-delete site reaches `Rsvp\Storage::save()`, which is where
	 * every other write invalidates, so without this the deleted responder
	 * stayed on every warm roster for the length of `Cache::CACHE_EXPIRATION`,
	 * shared across all visitors under a persistent object cache. The
	 * occurrence identity is read from the comment's own term, and it has to be
	 * read here, before the relationships below are removed; the hook fires
	 * with no ambient occurrence context to fall back on.
	 *
	 * All three taxonomies are always named, `_gatherpress_occurrence`
	 * included. The `gatherpress_has_recurring_events` option describes the
	 * current occurrence-table state, not whether this comment carries an
	 * occurrence relationship: a site can remove its last recurrence, flip the
	 * option to `0`, and still hold historical RSVP occurrence terms, and
	 * gating the destructive cleanup on the option would orphan those rows
	 * forever. The taxonomy itself is registered unconditionally on `init` by
	 * `Rsvp\Setup`, and core skips any taxonomy that is somehow unregistered
	 * rather than failing.
	 *
	 * @since 0.36.0
	 *
	 * @param string|int      $comment_id The comment ID, as WordPress passes it.
	 * @param WP_Comment|null $comment    The comment being deleted.
	 *
	 * @return void
	 */
	public function delete_term_relationships( $comment_id, $comment = null ): void {
		if ( ! $comment instanceof WP_Comment || Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return;
		}

		$occurrence = Rsvp_Occurrence::occurrence_for_comment( (int) $comment_id );

		wp_delete_object_term_relationships(
			(int) $comment_id,
			array( Status::TAXONOMY, Provider::TAXONOMY, Rsvp_Occurrence::TAXONOMY )
		);

		$post_id = (int) $comment->comment_post_ID;

		if ( null === $occurrence ) {
			// A series-wide RSVP is counted only under the series key. There
			// is no ambient occurrence context on any real hard-delete path,
			// so the null identifier resolves to nothing and only that key
			// drops; over-invalidating an occurrence key would be harmless
			// anyway.
			Cache::delete( $post_id );

			return;
		}

		// The term's own series post is the one the row was counted under,
		// and after a forward split it can legitimately differ from the post
		// the comment names, so both are dropped when they disagree.
		Cache::delete( (int) $occurrence['series_post_id'], (string) $occurrence['recurrence_id'] );

		if ( (int) $occurrence['series_post_id'] !== $post_id ) {
			Cache::delete( $post_id );
		}
	}

	/**
	 * Cleans up old RSVP entries by removing comments and their associated metadata.
	 *
	 * This method performs the following steps:
	 * 1. Retrieves all RSVPs with a comment status of 'hold' and a date before today.
	 * 2. Filters the retrieved RSVPs to include only those that are more than 24 hours old.
	 * 3. Deletes the filtered RSVP comments along with their associated metadata.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function rsvp_cleanup(): void {
		// Perform cleanup.
		$rsvp_query = Query::get_instance();
		$rsvps      = $rsvp_query->get_rsvps(
			array(
				// 1. Find all rsvps with comment approved = 0.
				'status'     => 'hold',
				'date_query' => array(
					'before' => array(
						'year'  => gmdate( 'Y' ),
						'month' => gmdate( 'm' ),
						'day'   => gmdate( 'd' ),
					),
				),
			)
		);

		// 2. Further filter by those that are more than 24hrs old.
		$rsvps = array_filter(
			$rsvps,
			static function ( $rsvp ): bool {
				$diff = strtotime( 'now' ) - strtotime( $rsvp->comment_date );
				return $diff >= HOUR_IN_SECONDS * 24;
			}
		);

		// 3. Delete RSVP comment + associated meta.
		//
		// Term counting is deferred across the whole loop. Each hard delete now
		// drops term relationships in up to three taxonomies via
		// `delete_term_relationships()`, and every one of those recounts its
		// terms immediately, so a sweep clearing n stale RSVPs paid 3n recount
		// queries. Deferring collapses them into one recount per taxonomy at the
		// end, which is exactly what this deferral exists for in core's own bulk
		// paths.
		//
		// The loop is wrapped so the deferral is closed on every exit. It runs
		// arbitrary third-party code: `delete_comment`, `deleted_comment` and
		// the comment-meta hooks all fire inside it, and any of those throwing
		// used to skip the restore and leave term counting deferred for the
		// rest of the request. Nothing recounts after that, so every term count
		// written for the remainder of the request would be stale, and this is
		// cron, where the throw is swallowed and nobody sees it.
		wp_defer_term_counting( true );

		try {
			foreach ( $rsvps as $rsvp ) {
				$meta_keys = array_keys( get_comment_meta( $rsvp->comment_ID ) );

				foreach ( $meta_keys as $meta_key ) {
					// A numeric meta key comes back from `array_keys()` as an int.
					delete_comment_meta( $rsvp->comment_ID, (string) $meta_key );
				}

				wp_delete_comment( $rsvp->comment_ID, true );
			}
		} finally {
			wp_defer_term_counting( false );
		}

		// Schedule the next event.
		wp_clear_scheduled_hook( 'gatherpress_rsvp_cleanup' );
		$this->schedule_cleanup_cron();
	}

	/**
	 * Determines if rsvp cleanup is enabled and schedules the next cleanup event.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function schedule_cleanup_cron(): void {
		$settings = Settings::get_instance();
		$switch   = $settings->get( 'rsvp_cleanup_switch' );

		if ( 'enabled' === $switch && ! wp_next_scheduled( 'gatherpress_rsvp_cleanup' ) ) {
			$frequency       = $settings->get( 'rsvp_cleanup_frequency' );
			$interval        = $settings->get( 'rsvp_cleanup_interval' );
			$time_in_seconds = $this->convert_to_seconds( $frequency, $interval );

			wp_schedule_single_event( time() + $time_in_seconds, 'gatherpress_rsvp_cleanup' );
		}
	}

	/**
	 * Converts a frequency and interval into the equivalent number of seconds.
	 *
	 * This method calculates the total seconds for a given frequency and
	 * multiplier interval. Supported frequencies include 'hourly', 'daily',
	 * 'weekly', 'monthly', and 'yearly'.
	 *
	 * @since 0.34.0
	 *
	 * @param string $frequency The recurrence frequency (e.g., 'hourly', 'daily').
	 * @param int    $interval The interval multiplier for the frequency.
	 *
	 * @return int The total number of seconds, or 0 if the frequency is not recognized.
	 */
	private function convert_to_seconds( string $frequency, int $interval ): int {
		// Assign per-arm and return once so the dispatch isn't a five-arm
		// return chain.
		$multiplier = match ( $frequency ) {
			'daily'   => DAY_IN_SECONDS,
			'weekly'  => WEEK_IN_SECONDS,
			'monthly' => MONTH_IN_SECONDS,
			'yearly'  => YEAR_IN_SECONDS,
			// 'hourly' and any unrecognized frequency fall back to hourly.
			default   => HOUR_IN_SECONDS,
		};

		return $interval * $multiplier;
	}

	/**
	 * Reschedules the RSVP cleanup cron job if the interval or frequency has changed.
	 *
	 * This method checks the old and new RSVP cleanup settings for changes in the interval
	 * or frequency. If a change is detected, it clears the existing scheduled cron job
	 * and schedules a new one with the updated settings.
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, bool|int|string> $old_value The previous RSVP cleanup settings including interval and
	 *                                                  frequency.
	 * @param array<string, bool|int|string> $new_value The updated RSVP cleanup settings including interval and
	 *                                                  frequency.
	 *
	 * @return void
	 */
	public function reschedule_cleanup_cron( $old_value, $new_value ): void {
		$old_interval  = $old_value['rsvp_cleanup_interval'] ?? null;
		$new_interval  = $new_value['rsvp_cleanup_interval'] ?? null;
		$old_frequency = $old_value['rsvp_cleanup_frequency'] ?? null;
		$new_frequency = $new_value['rsvp_cleanup_frequency'] ?? null;

		if ( $old_interval !== $new_interval || $old_frequency !== $new_frequency ) {
			wp_clear_scheduled_hook( 'gatherpress_rsvp_cleanup' );
			$this->schedule_cleanup_cron();
		}
	}
}
