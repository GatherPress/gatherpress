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
	const ENDED_ACTION = 'gatherpress_event_ended';

	/**
	 * WP-Cron hook name for single event end action.
	 *
	 * @since TBD
	 * @var string
	 */
	const CRON_ACTION = 'gatherpress_event_end_run';

	/**
	 * WP-Cron hook name for daily validation fallback check.
	 *
	 * @since TBD
	 * @var string
	 */
	const SWEEP_ACTION = 'gatherpress_event_end_sweep';

	/**
	 * Post meta key storing the GMT end datetime.
	 *
	 * @since TBD
	 * @var string
	 */
	const POST_META_KEY = 'gatherpress_datetime_end_gmt';

	/**
	 * Option holding the tracked upcoming event IDs, keyed by post type.
	 *
	 * @since TBD
	 * @var string
	 */
	const OPTION_NAME = 'gatherpress_event_upcoming_ids';

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
		add_action( 'added_post_meta', array( $this, 'handle_end_meta_change' ), 10, 3 );
		add_action( 'updated_postmeta', array( $this, 'handle_end_meta_change' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'handle_before_delete_post' ) );
		add_action( self::CRON_ACTION, array( $this, 'handle_end_run' ) );
		add_action( self::ENDED_ACTION, array( $this, 'invalidate_caches' ) );
		add_action( self::ENDED_ACTION, array( $this, 'clear_scheduled_cron' ) );
		add_action( self::ENDED_ACTION, array( $this, 'remove_from_tracking' ) );
		add_action( self::SWEEP_ACTION, array( $this, 'sweep_ended_events' ) );
		// Priority 11: the event post types register on init at 10, and the check reads them.
		add_action( 'init', array( $this, 'schedule_sweep' ), 11 );
	}

	/**
	 * Schedules the daily sweep for missed event ends, when a post type tracks upcoming events.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function schedule_sweep(): void {
		if ( wp_next_scheduled( self::SWEEP_ACTION ) || ! $this->any_post_type_enabled() ) {
			return;
		}

		wp_schedule_event( time(), 'daily', self::SWEEP_ACTION );
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
	public function handle_end_meta_change( int $meta_id, int $object_id, string $meta_key ): void {
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

		// One second late: Event::has_event_past() needs time() to be strictly after the end.
		wp_schedule_single_event( $end_timestamp + 1, self::CRON_ACTION, array( $post_id ) );
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
		wp_clear_scheduled_hook( self::CRON_ACTION, array( $post_id ) );
	}

	/**
	 * Handles the WP-Cron execution when an event's end time arrives.
	 *
	 * Validates that the event has ended and fires the main action hook.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The ID of the event that ended.
	 *
	 * @return void
	 */
	public function handle_end_run( int $post_id ): void {
		if ( ! $this->is_valid_past_event( $post_id ) ) {
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
		 *     static function ( int $post_id ): void {
		 *         wp_cache_delete( "my_custom_cache_{$post_id}", 'my_plugin' );
		 *     }
		 * );
		 * ```
		 *
		 * @since TBD
		 *
		 * @param int $post_id The post ID of the event that ended.
		 */
		do_action( 'gatherpress_event_ended', $post_id );
	}

	/**
	 * Invalidates object caches and WordPress core post cache for an ended event.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The ID of the event to invalidate caches for.
	 *
	 * @return void
	 */
	public function invalidate_caches( int $post_id ): void {
		$default_keys = array(
			"gatherpress_event_{$post_id}",
			'gatherpress_upcoming_events',
			'gatherpress_past_events',
		);

		/**
		 * Filters cache keys to invalidate when an event ends.
		 *
		 * @since TBD
		 *
		 * @param array<string>|mixed $cache_keys Array of cache keys to invalidate.
		 * @param int                 $post_id    The event post ID.
		 */
		$cache_keys = apply_filters(
			'gatherpress_event_end_cache_keys',
			$default_keys,
			$post_id
		);

		$cache_keys = array_filter(
			is_array( $cache_keys ) ? $cache_keys : $default_keys,
			static fn( mixed $key ): bool => is_string( $key ) && '' !== $key
		);

		foreach ( $cache_keys as $key ) {
			wp_cache_delete( $key, 'gatherpress' );
		}

		clean_post_cache( $post_id );
	}

	/**
	 * Checks if upcoming events tracking is enabled for a given post type.
	 *
	 * Tracked post types keep their published upcoming event IDs in the
	 * `gatherpress_event_upcoming_ids` option, keyed by post type. An ID is
	 * removed when its event ends, is unpublished or is deleted, so the list
	 * can feed a `post__in` query instead of a `meta_query` on the dates.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type slug to check.
	 *
	 * @return bool True if tracking is enabled, false otherwise.
	 */
	public function is_tracker_enabled( string $post_type ): bool {
		/**
		 * Filters whether the upcoming event IDs of every post type with event dates are tracked.
		 *
		 * Tracked IDs are stored in the `gatherpress_event_upcoming_ids` option as
		 * `array<string, int[]>`, keyed by post type, and checked by a daily sweep
		 * that fires `gatherpress_event_ended` for any end that WP-Cron missed.
		 * Read them with `Scheduler::get_tracked_ids()`. To track a single post type,
		 * use `gatherpress_event_upcoming_tracking_enabled_{$post_type}` instead.
		 *
		 * @example
		 * ```php
		 * add_filter( 'gatherpress_event_upcoming_tracking_enabled', '__return_true' );
		 *
		 * // Query the upcoming events without a meta_query:
		 * $upcoming_ids = \GatherPress\Core\Event\Scheduler::get_instance()->get_tracked_ids( 'gatherpress_event' );
		 *
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
		 * @param bool $enabled Whether tracking is enabled. Default false.
		 */
		if ( apply_filters( 'gatherpress_event_upcoming_tracking_enabled', false ) ) {
			return true;
		}

		/**
		 * Filters whether the upcoming event IDs of one post type are tracked.
		 *
		 * The dynamic part of the name is the post type slug. A tracked post type gets
		 * its own `gatherpress_event_upcoming_ids` entry, keyed by its slug, read with
		 * `Scheduler::get_tracked_ids( $post_type )`.
		 *
		 * @example
		 * ```php
		 * // Track only the default event post type:
		 * add_filter( 'gatherpress_event_upcoming_tracking_enabled_gatherpress_event', '__return_true' );
		 * ```
		 *
		 * @since TBD
		 *
		 * @param bool   $enabled   Whether tracking is enabled for this post type. Default false.
		 * @param string $post_type The post type being checked.
		 */
		return (bool) apply_filters(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			"gatherpress_event_upcoming_tracking_enabled_{$post_type}",
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
	 * Adds an event post ID to the upcoming tracking for its post type.
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

		$this->set_tracked_ids( $post_type, array_unique( $tracked_ids ) );
		$this->schedule_sweep();
	}

	/**
	 * Removes an event post ID from the upcoming tracking for its post type.
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

		$this->remove_tracked_id( $post_id, $post_type );
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
	 * Removes a post ID from the tracked IDs of a post type.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id   The post ID to remove.
	 * @param string $post_type The post type slug.
	 *
	 * @return void
	 */
	public function remove_tracked_id( int $post_id, string $post_type ): void {
		$this->set_tracked_ids(
			$post_type,
			array_diff( $this->get_tracked_ids( $post_type ), array( $post_id ) )
		);
	}

	/**
	 * Retrieves and normalizes the tracked upcoming event IDs of a post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type slug.
	 *
	 * @return array<int> Array of positive integer post IDs.
	 */
	public function get_tracked_ids( string $post_type ): array {
		$raw = $this->get_tracking()[ $post_type ] ?? array();

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
	 * Stores the tracked upcoming event IDs of a post type.
	 *
	 * @since TBD
	 *
	 * @param string     $post_type The post type slug.
	 * @param array<int> $ids       The post IDs.
	 *
	 * @return void
	 */
	private function set_tracked_ids( string $post_type, array $ids ): void {
		$tracking               = $this->get_tracking();
		$tracking[ $post_type ] = array_values( $ids );

		update_option( self::OPTION_NAME, $tracking, false );
	}

	/**
	 * Returns the stored tracking, keyed by post type.
	 *
	 * @since TBD
	 *
	 * @return array<mixed> The option value, or an empty array when it is not an array.
	 */
	private function get_tracking(): array {
		$tracking = get_option( self::OPTION_NAME, array() );

		return is_array( $tracking ) ? $tracking : array();
	}

	/**
	 * Daily fallback check to detect events whose end time passed without being processed.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function sweep_ended_events(): void {
		$post_types = array_filter(
			get_post_types_by_support( Event::SUPPORT ),
			array( $this, 'is_tracker_enabled' )
		);

		foreach ( $post_types as $post_type ) {
			foreach ( $this->get_tracked_ids( $post_type ) as $post_id ) {
				$this->sweep_tracked_id( $post_id, $post_type );
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
	private function sweep_tracked_id( int $post_id, string $post_type ): void {
		if ( get_post_type( $post_id ) !== $post_type ) {
			$this->remove_tracked_id( $post_id, $post_type );

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
