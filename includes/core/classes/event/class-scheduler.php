<?php
/**
 * Event scheduler for event end actions, cache invalidation, and optional tracking.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_Post;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Scheduler.
 *
 * Manages WP-Cron scheduling for event end times. When an event concludes,
 * triggers cache invalidation and custom action hooks. Also optionally tracks
 * upcoming event IDs per post type and runs a daily fallback check.
 *
 * @since TBD
 */
final class Scheduler {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Action hook fired when an event has ended.
	 *
	 * @since TBD
	 * @var string
	 */
	const ACTION_HOOK = 'gatherpress_event_ended';

	/**
	 * WP-Cron hook name for single event end action.
	 *
	 * @since TBD
	 * @var string
	 */
	const CRON_HOOK = 'gatherpress_event_ended_cron';

	/**
	 * WP-Cron hook name for daily validation fallback check.
	 *
	 * @since TBD
	 * @var string
	 */
	const VALIDATE_CRON_HOOK = 'gatherpress_validate_events_ended';

	/**
	 * Post meta key storing the GMT end datetime.
	 *
	 * @since TBD
	 * @var string
	 */
	const POST_META_KEY = 'gatherpress_datetime_end_gmt';

	/**
	 * Prefix for the per-post-type upcoming tracking options.
	 *
	 * @since TBD
	 * @var string
	 */
	const OPTION_KEY_PREFIX = 'upcoming_';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Registers action hooks for scheduling, post transitions, and cron events.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'transition_post_status', array( $this, 'handle_transition_post_status' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'handle_updated_postmeta' ), 10, 3 );
		add_action( 'updated_postmeta', array( $this, 'handle_updated_postmeta' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'handle_before_delete_post' ) );
		add_action( self::CRON_HOOK, array( $this, 'handle_event_ended_cron' ) );
		add_action( self::ACTION_HOOK, array( $this, 'invalidate_caches' ) );
		add_action( self::ACTION_HOOK, array( $this, 'clear_scheduled_cron' ) );
		add_action( self::ACTION_HOOK, array( $this, 'remove_from_tracking' ) );
		add_action( self::VALIDATE_CRON_HOOK, array( $this, 'validate_events_ended' ) );
		// Priority 11: the event post types register on init at 10, and the check reads them.
		add_action( 'init', array( $this, 'schedule_validation' ), 11 );
	}

	/**
	 * Schedules the daily check for ended events, when a post type tracks upcoming events.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function schedule_validation(): void {
		if ( wp_next_scheduled( self::VALIDATE_CRON_HOOK ) || ! $this->any_post_type_enabled() ) {
			return;
		}

		wp_schedule_event( time(), 'daily', self::VALIDATE_CRON_HOOK );
	}

	/**
	 * Handles post status transitions to manage event end scheduling.
	 *
	 * @since TBD
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       The post object.
	 *
	 * @return void
	 */
	public function handle_transition_post_status( string $new_status, string $old_status, WP_Post $post ): void {
		// Only publishing or unpublishing an event changes whether its end is scheduled.
		if (
			$new_status === $old_status
			|| ! in_array( 'publish', array( $new_status, $old_status ), true )
			|| ! post_type_supports( $post->post_type, Event::SUPPORT )
		) {
			return;
		}

		$this->sync_event( $post->ID, $new_status );
	}

	/**
	 * Handles post meta additions and updates for event end datetime.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP post meta hook signature.
	 *
	 * @param int    $meta_id   ID of metadata entry.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Metadata key.
	 *
	 * @return void
	 */
	public function handle_updated_postmeta( int $meta_id, int $object_id, string $meta_key ): void {
		if ( self::POST_META_KEY !== $meta_key || ! $this->is_event( $object_id ) ) {
			return;
		}

		$this->sync_event( $object_id, (string) get_post_status( $object_id ) );
	}

	/**
	 * Schedules and tracks the end of a published upcoming event, or clears them otherwise.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $status  The event's post status.
	 *
	 * @return void
	 */
	private function sync_event( int $post_id, string $status ): void {
		if ( 'publish' === $status && $this->is_valid_future_event( $post_id ) ) {
			$this->add_scheduled_cron( $post_id );
			$this->add_to_tracking( $post_id );

			return;
		}

		$this->clear_scheduled_cron( $post_id );
		$this->remove_from_tracking( $post_id );
		$this->invalidate_caches( $post_id );
	}

	/**
	 * Cleans up scheduled cron and tracking before post deletion.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID being deleted.
	 *
	 * @return void
	 */
	public function handle_before_delete_post( int $post_id ): void {
		if ( ! $this->is_event( $post_id ) ) {
			return;
		}

		$this->clear_scheduled_cron( $post_id );
		$this->remove_from_tracking( $post_id );
	}

	/**
	 * Schedules a single WP-Cron event for when an event reaches its end time.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID to schedule.
	 *
	 * @return void
	 */
	public function add_scheduled_cron( int $post_id ): void {
		$end_date      = get_post_meta( $post_id, self::POST_META_KEY, true );
		$end_timestamp = is_string( $end_date ) ? strtotime( $end_date ) : false;

		if ( false === $end_timestamp || $end_timestamp <= time() ) {
			return;
		}

		$this->clear_scheduled_cron( $post_id );

		wp_schedule_single_event( $end_timestamp, self::CRON_HOOK, array( $post_id ) );
	}

	/**
	 * Clears any scheduled event end cron job for a given event.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID to clear scheduling for.
	 *
	 * @return void
	 */
	public function clear_scheduled_cron( int $post_id ): void {
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $post_id ) );
	}

	/**
	 * Handles the WP-Cron execution when an event's end time arrives.
	 *
	 * Validates that the event has ended and fires the main action hook.
	 *
	 * @since TBD
	 *
	 * @param int $event_id The ID of the event that ended.
	 *
	 * @return void
	 */
	public function handle_event_ended_cron( int $event_id ): void {
		if ( ! $this->is_valid_past_event( $event_id ) ) {
			return;
		}

		/**
		 * Fires when an event reaches its end time.
		 *
		 * Central hook for event end processing and the transition from upcoming to past.
		 * Triggered automatically via WP-Cron when an event reaches its scheduled end time,
		 * or via the daily fallback tracking validator for any missed events.
		 *
		 * Common use cases:
		 * - Purging custom caches (object caches, external full-page or CDN/Varnish caches).
		 * - Sending post-event follow-up emails, thank-you notes, or attendee surveys.
		 * - Syncing event completion with external calendar services, CRM, or attendance trackers.
		 * - Adjusting post metadata, archiving event assets, or updating summary statistics.
		 *
		 * @example
		 * ```php
		 * // Purge external cache and trigger post-event workflows when an event concludes:
		 * add_action(
		 *     'gatherpress_event_ended',
		 *     static function ( int $event_id ): void {
		 *         wp_cache_delete( "my_custom_cache_{$event_id}", 'my_plugin' );
		 *     }
		 * );
		 * ```
		 *
		 * @since TBD
		 *
		 * @param int $event_id The post ID of the event that ended.
		 */
		do_action( 'gatherpress_event_ended', $event_id );
	}

	/**
	 * Invalidates object caches and WordPress core post cache for an ended event.
	 *
	 * @since TBD
	 *
	 * @param int $event_id The ID of the event to invalidate caches for.
	 *
	 * @return void
	 */
	public function invalidate_caches( int $event_id ): void {
		$default_keys = array(
			"gatherpress_event_{$event_id}",
			'gatherpress_upcoming_events',
			'gatherpress_past_events',
		);

		/**
		 * Filters cache keys to invalidate when an event ends.
		 *
		 * @since TBD
		 *
		 * @param array<string>|mixed $cache_keys Array of cache keys to invalidate.
		 * @param int           $event_id   The event post ID.
		 */
		$cache_keys = apply_filters(
			'gatherpress_event_end_cache_keys',
			$default_keys,
			$event_id
		);

		$cache_keys = array_filter(
			is_array( $cache_keys ) ? $cache_keys : $default_keys,
			static fn( mixed $key ): bool => is_string( $key ) && '' !== $key
		);

		foreach ( $cache_keys as $key ) {
			wp_cache_delete( $key, 'gatherpress' );
		}

		clean_post_cache( $event_id );
	}

	/**
	 * Checks if upcoming events tracking is enabled for a given post type.
	 *
	 * When tracking is enabled, an option named `upcoming_{$post_type}s` is maintained
	 * (e.g. `upcoming_gatherpress_events` for the default `gatherpress_event` post type).
	 *
	 * The option stores an array of integer post IDs (`int[]`) representing currently
	 * published upcoming events. When an event concludes or is unpublished, its ID is
	 * automatically removed from the option.
	 *
	 * Developers can use this option to perform high-performance queries for upcoming
	 * events using `post__in` without needing complex or heavy `meta_query` datetime lookups.
	 *
	 * @example
	 * ```php
	 * // Enable tracking for all supporting post types:
	 * add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );
	 *
	 * // Query upcoming events using the tracked option:
	 * $upcoming_ids = get_option( 'upcoming_gatherpress_events', array() );
	 * if ( ! empty( $upcoming_ids ) ) {
	 *     $query = new WP_Query(
	 *         array(
	 *             'post_type' => 'gatherpress_event',
	 *             'post__in'  => $upcoming_ids,
	 *         )
	 *     );
	 * }
	 * ```
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type slug to check.
	 *
	 * @return bool True if tracking is enabled, false otherwise.
	 */
	public function is_tracker_enabled( string $post_type ): bool {
		/**
		 * Filter whether upcoming events tracking is enabled globally.
		 *
		 * When true, every post type declaring `gatherpress-event-date` support is tracked
		 * in its respective `upcoming_{$post_type}s` option (e.g. `upcoming_gatherpress_events`).
		 *
		 * @example
		 * ```php
		 * add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );
		 * ```
		 *
		 * @since TBD
		 *
		 * @param bool $enabled Whether tracking is enabled. Default false.
		 */
		if ( apply_filters( 'gatherpress_upcoming_tracker_enabled', false ) ) {
			return true;
		}

		/**
		 * Filter whether upcoming events tracking is enabled for a specific post type.
		 *
		 * When true for `$post_type`, an option named `upcoming_{$post_type}s` (e.g.
		 * `upcoming_gatherpress_events` or `upcoming_my_cpts`) is populated with
		 * upcoming event post IDs (`int[]`) and validated via daily cron.
		 *
		 * @example
		 * ```php
		 * // Enable tracking specifically for 'gatherpress_event':
		 * add_filter( 'gatherpress_event_upcoming_tracker_enabled', '__return_true' );
		 * ```
		 *
		 * @since TBD
		 *
		 * @param bool   $enabled   Whether tracking is enabled for this post type. Default false.
		 * @param string $post_type The post type currently being evaluated.
		 */
		return (bool) apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			"{$post_type}_upcoming_tracker_enabled",
			false,
			$post_type
		);
	}

	/**
	 * Checks whether at least one supporting post type has tracking enabled.
	 *
	 * @since TBD
	 *
	 * @return bool True if at least one post type has tracking enabled.
	 */
	public function any_post_type_enabled(): bool {
		foreach ( get_post_types_by_support( Event::SUPPORT ) as $post_type ) {
			if ( $this->is_tracker_enabled( $post_type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the wp_option key for tracking upcoming IDs of a given post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return string The option key.
	 */
	public function option_key_for( string $post_type ): string {
		return self::OPTION_KEY_PREFIX . $post_type . 's';
	}

	/**
	 * Adds an event post ID to the upcoming tracking option for its post type.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID to track.
	 *
	 * @return void
	 */
	public function add_to_tracking( int $post_id ): void {
		$post_type = $this->tracked_post_type( $post_id );

		if ( '' === $post_type ) {
			return;
		}

		$tracked_ids   = $this->get_tracked_ids( $post_type );
		$tracked_ids[] = $post_id;
		$tracked_ids   = array_values( array_unique( $tracked_ids ) );

		update_option( $this->option_key_for( $post_type ), $tracked_ids, false );
		$this->schedule_validation();
	}

	/**
	 * Removes an event post ID from the upcoming tracking option for its post type.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID to remove from tracking.
	 *
	 * @return void
	 */
	public function remove_from_tracking( int $post_id ): void {
		$post_type = $this->tracked_post_type( $post_id );

		if ( '' === $post_type ) {
			return;
		}

		$this->remove_id_from_option( $post_id, $post_type );
	}

	/**
	 * Returns the post type of an event whose upcoming IDs are tracked.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string The post type, or an empty string when the post is not a tracked event.
	 */
	private function tracked_post_type( int $post_id ): string {
		$post_type = (string) get_post_type( $post_id );

		return $this->is_event( $post_id ) && $this->is_tracker_enabled( $post_type ) ? $post_type : '';
	}

	/**
	 * Removes a post ID from the tracking option for a specific post type.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id   The post ID to remove.
	 * @param string $post_type The post type slug.
	 *
	 * @return void
	 */
	public function remove_id_from_option( int $post_id, string $post_type ): void {
		$tracked_ids = $this->get_tracked_ids( $post_type );
		$tracked_ids = array_values( array_diff( $tracked_ids, array( $post_id ) ) );

		update_option( $this->option_key_for( $post_type ), $tracked_ids, false );
	}

	/**
	 * Retrieves and normalizes the tracked IDs for a given post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return array<int> Array of positive integer post IDs.
	 */
	public function get_tracked_ids( string $post_type ): array {
		$raw = get_option( $this->option_key_for( $post_type ), array() );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( mixed $value ): int {
						return is_scalar( $value ) ? (int) $value : 0;
					},
					$raw
				),
				static fn( int $id ): bool => $id > 0
			)
		);
	}

	/**
	 * Daily fallback check to detect events whose end time passed without being processed.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function validate_events_ended(): void {
		$post_types = array_filter(
			get_post_types_by_support( Event::SUPPORT ),
			array( $this, 'is_tracker_enabled' )
		);

		foreach ( $post_types as $post_type ) {
			foreach ( $this->get_tracked_ids( $post_type ) as $post_id ) {
				$this->validate_tracked_id( $post_id, $post_type );
			}
		}
	}

	/**
	 * Drops a tracked ID that no longer belongs to its post type, or ends it if its event is past.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id   The tracked post ID.
	 * @param string $post_type The post type it is tracked under.
	 *
	 * @return void
	 */
	private function validate_tracked_id( int $post_id, string $post_type ): void {
		if ( get_post_type( $post_id ) !== $post_type ) {
			$this->remove_id_from_option( $post_id, $post_type );

			return;
		}

		if ( ( new Event( $post_id ) )->has_event_past() ) {
			/** This action is documented in includes/core/classes/event/class-scheduler.php */
			do_action( 'gatherpress_event_ended', $post_id );
		}
	}

	/**
	 * Checks whether a post ID belongs to a post type with event dates.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID to check.
	 *
	 * @return bool True if the post is an event, false otherwise.
	 */
	public function is_event( int $post_id ): bool {
		return post_type_supports( (string) get_post_type( $post_id ), Event::SUPPORT );
	}

	/**
	 * Validates whether a post ID belongs to an existing future event.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID to check.
	 *
	 * @return bool True if post is a valid future event, false otherwise.
	 */
	public function is_valid_future_event( int $post_id ): bool {
		return $this->is_event( $post_id ) && ! ( new Event( $post_id ) )->has_event_past();
	}

	/**
	 * Validates whether a post ID belongs to an existing past event.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID to check.
	 *
	 * @return bool True if post is a valid past event, false otherwise.
	 */
	public function is_valid_past_event( int $post_id ): bool {
		return $this->is_event( $post_id ) && ( new Event( $post_id ) )->has_event_past();
	}
}
