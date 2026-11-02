<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Scheduler.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Scheduler;
use GatherPress\Tests\Base;
use WP_Post;

/**
 * Class Test_Scheduler.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Scheduler
 */
class Test_Scheduler extends Base {

	/**
	 * Tear down after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		wp_clear_scheduled_hook( Scheduler::CRON_ACTION );
		wp_unschedule_hook( Scheduler::START_CRON_ACTION );
		wp_clear_scheduled_hook( Scheduler::SWEEP_ACTION );
		remove_all_filters( 'gatherpress_event_end_cache_keys' );
		remove_all_filters( 'gatherpress_event_upcoming_tracking_enabled' );
		remove_all_filters( 'gatherpress_event_upcoming_tracking_enabled_' . Event::POST_TYPE );
		delete_option( Scheduler::OPTION_NAME );

		parent::tear_down();
	}

	/**
	 * Helper to create a test event post.
	 *
	 * @param array $args Optional post arguments.
	 * @return int Created post ID.
	 */
	protected function create_test_event( array $args = array() ): int {
		$defaults = array(
			'post_type'   => Event::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => 'Test Event',
		);

		return $this->factory()->post->create( array_merge( $defaults, $args ) );
	}

	/**
	 * Test setup_hooks registers expected action hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Scheduler::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'transition_post_status',
				'priority' => 10,
				'callback' => array( $instance, 'handle_transition_post_status' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'handle_datetime_meta_change' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_postmeta',
				'priority' => 10,
				'callback' => array( $instance, 'handle_datetime_meta_change' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'before_delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'handle_before_delete_post' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::START_CRON_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'handle_start_run' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::CRON_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'handle_end_run' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::ENDED_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'invalidate_caches' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::ENDED_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'clear_scheduled_cron' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::ENDED_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'remove_from_tracking' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::SWEEP_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'sweep_ended_events' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'schedule_sweep' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test schedule_sweep schedules the daily check once, only while tracking is enabled.
	 *
	 * @covers ::schedule_sweep
	 *
	 * @return void
	 */
	public function test_schedule_sweep(): void {
		$instance = Scheduler::get_instance();
		wp_clear_scheduled_hook( Scheduler::SWEEP_ACTION );

		$instance->schedule_sweep();
		$this->assertFalse( wp_next_scheduled( Scheduler::SWEEP_ACTION ), 'Not scheduled while off.' );

		add_filter( 'gatherpress_event_upcoming_tracking_enabled', '__return_true' );

		$instance->schedule_sweep();
		$timestamp = wp_next_scheduled( Scheduler::SWEEP_ACTION );
		$this->assertNotEmpty( $timestamp );

		$instance->schedule_sweep();
		$this->assertSame( $timestamp, wp_next_scheduled( Scheduler::SWEEP_ACTION ), 'The schedule is kept.' );

		wp_clear_scheduled_hook( Scheduler::SWEEP_ACTION );
	}

	/**
	 * Test handle_transition_post_status ignores unsupported post types and non-publish transitions.
	 *
	 * @covers ::handle_transition_post_status
	 * @covers ::sync_event
	 *
	 * @return void
	 */
	public function test_handle_transition_post_status_ignores_unsupported_and_non_publish(): void {
		$instance = Scheduler::get_instance();

		// Unsupported post type.
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );
		$post    = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$instance->handle_transition_post_status( 'publish', 'draft', $post );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );

		// Event post transitioning between draft and pending (neither is publish).
		$event_id = $this->create_test_event( array( 'post_status' => 'draft' ) );
		$event    = get_post( $event_id );
		$this->assertInstanceOf( WP_Post::class, $event );

		$instance->handle_transition_post_status( 'pending', 'draft', $event );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $event_id ) ) );
	}

	/**
	 * Test handle_transition_post_status schedules future events when published.
	 *
	 * @covers ::handle_transition_post_status
	 * @covers ::sync_event
	 *
	 * @return void
	 */
	public function test_handle_transition_post_status_publishes_future_event(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event( array( 'post_status' => 'draft' ) );

		// Set future end date.
		$future_end = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		update_post_meta( $post_id, Scheduler::POST_META_KEY, $future_end );

		$post = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$instance->handle_transition_post_status( 'publish', 'draft', $post );

		$this->assertSame(
			strtotime( $future_end ) + 1,
			wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ),
			'The cron runs one second after the end, once the event counts as past.'
		);
	}

	/**
	 * Test handle_transition_post_status does not schedule past events when published.
	 *
	 * @covers ::handle_transition_post_status
	 * @covers ::sync_event
	 *
	 * @return void
	 */
	public function test_handle_transition_post_status_does_not_schedule_past_event(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event( array( 'post_status' => 'draft' ) );

		// Set past end date.
		$past_end = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		update_post_meta( $post_id, Scheduler::POST_META_KEY, $past_end );

		$post = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$instance->handle_transition_post_status( 'publish', 'draft', $post );

		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );
	}

	/**
	 * Test handle_transition_post_status clears schedule when unpublishing an event.
	 *
	 * @covers ::handle_transition_post_status
	 * @covers ::sync_event
	 *
	 * @return void
	 */
	public function test_handle_transition_post_status_unpublishes_event(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event( array( 'post_status' => 'publish' ) );

		$future_end = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		update_post_meta( $post_id, Scheduler::POST_META_KEY, $future_end );

		$instance->add_scheduled_cron( $post_id );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );

		$post = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$instance->handle_transition_post_status( 'draft', 'publish', $post );

		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );
	}

	/**
	 * Test handle_datetime_meta_change schedules future event and unschedules past event.
	 *
	 * @covers ::handle_datetime_meta_change
	 * @covers ::sync_event
	 *
	 * @return void
	 */
	public function test_handle_datetime_meta_change(): void {
		$instance = Scheduler::get_instance();

		// Ignores other meta keys.
		$instance->handle_datetime_meta_change( 1, 1, 'some_other_key' );

		// Ignores non-existing or unsupported posts.
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );
		$instance->handle_datetime_meta_change( 1, $post_id, Scheduler::POST_META_KEY );

		// Ignores non-published events.
		$draft_id = $this->create_test_event( array( 'post_status' => 'draft' ) );
		$instance->handle_datetime_meta_change( 1, $draft_id, Scheduler::POST_META_KEY );

		// Published event with future end date schedules cron.
		$event_id   = $this->create_test_event( array( 'post_status' => 'publish' ) );
		$future_end = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		update_post_meta( $event_id, Scheduler::POST_META_KEY, $future_end );

		$instance->handle_datetime_meta_change( 1, $event_id, Scheduler::POST_META_KEY );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_ACTION, array( $event_id ) ) );

		// Published event updated with past end date clears schedule.
		$past_end = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		update_post_meta( $event_id, Scheduler::POST_META_KEY, $past_end );

		$instance->handle_datetime_meta_change( 1, $event_id, Scheduler::POST_META_KEY );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $event_id ) ) );
	}

	/**
	 * Test handle_before_delete_post clears scheduled cron and tracking.
	 *
	 * @covers ::handle_before_delete_post
	 * @covers ::is_event
	 *
	 * @return void
	 */
	public function test_handle_before_delete_post(): void {
		$instance = Scheduler::get_instance();

		// Unsupported post type is ignored.
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );
		$instance->handle_before_delete_post( $post_id );

		// Supported event clears cron.
		$event_id   = $this->create_test_event();
		$future_end = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		update_post_meta( $event_id, Scheduler::POST_META_KEY, $future_end );

		$instance->add_scheduled_cron( $event_id );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_ACTION, array( $event_id ) ) );

		$instance->handle_before_delete_post( $event_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $event_id ) ) );
	}

	/**
	 * Test add_scheduled_cron validation for invalid or past timestamps.
	 *
	 * @covers ::add_scheduled_cron
	 *
	 * @return void
	 */
	public function test_add_scheduled_cron_validation(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event();

		// Empty end date.
		delete_post_meta( $post_id, Scheduler::POST_META_KEY );
		$instance->add_scheduled_cron( $post_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );

		// Invalid date string.
		update_post_meta( $post_id, Scheduler::POST_META_KEY, 'not-a-valid-date' );
		$instance->add_scheduled_cron( $post_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );

		// Past date timestamp.
		update_post_meta( $post_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() - 100 ) );
		$instance->add_scheduled_cron( $post_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );

		// Future date timestamp schedules cron.
		$future_end = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		update_post_meta( $post_id, Scheduler::POST_META_KEY, $future_end );
		$instance->add_scheduled_cron( $post_id );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );
	}

	/**
	 * Test clear_scheduled_cron handles scheduled and non-scheduled states.
	 *
	 * @covers ::clear_scheduled_cron
	 *
	 * @return void
	 */
	public function test_clear_scheduled_cron(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event();

		// No scheduled event - no error.
		$instance->clear_scheduled_cron( $post_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );

		// Scheduled event unscheduled.
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, Scheduler::CRON_ACTION, array( $post_id ) );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );

		$instance->clear_scheduled_cron( $post_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_ACTION, array( $post_id ) ) );
	}

	/**
	 * Test handle_end_run verifies event is past before firing action.
	 *
	 * @covers ::handle_end_run
	 *
	 * @return void
	 */
	public function test_handle_end_run(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event();

		$fired = false;
		add_action(
			Scheduler::ENDED_ACTION,
			static function ( int $event_id ) use ( &$fired, $post_id ): void {
				if ( $event_id === $post_id ) {
					$fired = true;
				}
			}
		);

		// Event with future end date does not fire action.
		update_post_meta( $post_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );
		$instance->handle_end_run( $post_id );
		$this->assertFalse( $fired );

		// Event with past end date fires action.
		update_post_meta( $post_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		$instance->handle_end_run( $post_id );
		$this->assertTrue( $fired );
	}

	/**
	 * Test invalidate_caches deletes cache keys and core post cache.
	 *
	 * @covers ::invalidate_caches
	 *
	 * @return void
	 */
	public function test_invalidate_caches(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event();

		wp_cache_set( "gatherpress_event_{$post_id}", 'cached_data', 'gatherpress' );
		wp_cache_set( 'gatherpress_upcoming_events', array( $post_id ), 'gatherpress' );

		$this->assertSame( 'cached_data', wp_cache_get( "gatherpress_event_{$post_id}", 'gatherpress' ) );

		$instance->invalidate_caches( $post_id );

		$this->assertFalse( wp_cache_get( "gatherpress_event_{$post_id}", 'gatherpress' ) );
		$this->assertFalse( wp_cache_get( 'gatherpress_upcoming_events', 'gatherpress' ) );
	}

	/**
	 * Test invalidate_caches filter handling with custom and invalid keys.
	 *
	 * @covers ::invalidate_caches
	 *
	 * @return void
	 */
	public function test_invalidate_caches_filter_handling(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event();

		// Custom valid and invalid keys.
		wp_cache_set( 'my_custom_cache_key', 'custom_value', 'gatherpress' );

		add_filter(
			'gatherpress_event_end_cache_keys',
			static function (): array {
				return array( 'my_custom_cache_key', '', 123, null );
			}
		);

		$instance->invalidate_caches( $post_id );
		$this->assertFalse( wp_cache_get( 'my_custom_cache_key', 'gatherpress' ) );

		remove_all_filters( 'gatherpress_event_end_cache_keys' );

		// Non-array return falls back to default keys.
		add_filter(
			'gatherpress_event_end_cache_keys',
			static function () {
				return 'invalid_non_array';
			}
		);

		wp_cache_set( "gatherpress_event_{$post_id}", 'cached_fallback', 'gatherpress' );
		$instance->invalidate_caches( $post_id );
		$this->assertFalse( wp_cache_get( "gatherpress_event_{$post_id}", 'gatherpress' ) );
	}

	/**
	 * Test is_tracker_enabled filter options.
	 *
	 * @covers ::is_tracker_enabled
	 * @covers ::any_post_type_enabled
	 *
	 * @return void
	 */
	public function test_is_tracker_enabled_filters(): void {
		$instance = Scheduler::get_instance();

		$this->assertFalse( $instance->is_tracker_enabled( Event::POST_TYPE ) );
		$this->assertFalse( $instance->any_post_type_enabled() );

		// Every post type.
		add_filter( 'gatherpress_event_upcoming_tracking_enabled', '__return_true' );
		$this->assertTrue( $instance->is_tracker_enabled( Event::POST_TYPE ) );
		$this->assertTrue( $instance->any_post_type_enabled() );
		remove_all_filters( 'gatherpress_event_upcoming_tracking_enabled' );

		// One post type, which receives its slug.
		$received = '';
		add_filter(
			'gatherpress_event_upcoming_tracking_enabled_' . Event::POST_TYPE,
			static function ( bool $enabled, string $type ) use ( &$received ): bool {
				$received = $type;

				return true;
			},
			10,
			2
		);
		$this->assertTrue( $instance->is_tracker_enabled( Event::POST_TYPE ) );
		$this->assertSame( Event::POST_TYPE, $received );
		$this->assertFalse( $instance->is_tracker_enabled( 'post' ) );
		remove_all_filters( 'gatherpress_event_upcoming_tracking_enabled_' . Event::POST_TYPE );
	}

	/**
	 * Test add_to_tracking and remove_from_tracking operations.
	 *
	 * @covers ::add_to_tracking
	 * @covers ::remove_from_tracking
	 * @covers ::tracked_post_type
	 * @covers ::set_tracked_ids
	 * @covers ::get_tracked_ids
	 *
	 * @return void
	 */
	public function test_tracking_add_and_remove(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event();

		// When tracker disabled, nothing is tracked.
		$instance->add_to_tracking( $post_id );
		$this->assertEmpty( $instance->get_tracked_ids( Event::POST_TYPE ) );

		$instance->remove_from_tracking( $post_id );
		$this->assertEmpty( $instance->get_tracked_ids( Event::POST_TYPE ) );

		// Enable tracker.
		add_filter( 'gatherpress_event_upcoming_tracking_enabled', '__return_true' );

		// Non-existent post ID returns early.
		$instance->add_to_tracking( 9999999 );
		$this->assertEmpty( $instance->get_tracked_ids( Event::POST_TYPE ) );

		$instance->remove_from_tracking( 9999999 );
		$this->assertEmpty( $instance->get_tracked_ids( Event::POST_TYPE ) );

		// Add post to tracking.
		$instance->add_to_tracking( $post_id );
		$this->assertSame( array( $post_id ), $instance->get_tracked_ids( Event::POST_TYPE ) );

		// Adding again does not duplicate ID.
		$instance->add_to_tracking( $post_id );
		$this->assertSame( array( $post_id ), $instance->get_tracked_ids( Event::POST_TYPE ) );

		// Remove from tracking.
		$instance->remove_from_tracking( $post_id );
		$this->assertEmpty( $instance->get_tracked_ids( Event::POST_TYPE ) );
	}

	/**
	 * Test get_tracked_ids sanitizes non-array and invalid entries.
	 *
	 * @covers ::get_tracked_ids
	 * @covers ::get_tracking
	 *
	 * @return void
	 */
	public function test_get_tracked_ids_sanitizes_option_data(): void {
		$instance = Scheduler::get_instance();

		// Non-array option returns empty array.
		update_option( Scheduler::OPTION_NAME, 'not_an_array' );
		$this->assertEmpty( $instance->get_tracked_ids( Event::POST_TYPE ) );

		// Non-array post type entry returns empty array.
		update_option( Scheduler::OPTION_NAME, array( Event::POST_TYPE => 'not_an_array' ) );
		$this->assertEmpty( $instance->get_tracked_ids( Event::POST_TYPE ) );

		// Array with strings, zeroes, and non-scalar data.
		update_option( Scheduler::OPTION_NAME, array( Event::POST_TYPE => array( '12', 0, -5, array(), 'abc', 45 ) ) );
		$this->assertSame( array( 12, 45 ), $instance->get_tracked_ids( Event::POST_TYPE ) );
	}

	/**
	 * Test remove_tracked_id removes target ID from option array.
	 *
	 * @covers ::remove_tracked_id
	 * @covers ::set_tracked_ids
	 *
	 * @return void
	 */
	public function test_remove_tracked_id(): void {
		$instance = Scheduler::get_instance();

		update_option(
			Scheduler::OPTION_NAME,
			array(
				Event::POST_TYPE => array( 10, 20, 30 ),
				'other_event'    => array( 20 ),
			),
			false
		);
		$instance->remove_tracked_id( 20, Event::POST_TYPE );

		$this->assertSame(
			array(
				Event::POST_TYPE => array( 10, 30 ),
				'other_event'    => array( 20 ),
			),
			get_option( Scheduler::OPTION_NAME ),
			'Only the given post type changes.'
		);
	}

	/**
	 * Test sweep_ended_events daily cron checks.
	 *
	 * @covers ::sweep_ended_events
	 * @covers ::sweep_tracked_id
	 * @covers ::remove_tracked_id
	 *
	 * @return void
	 */
	public function test_sweep_ended_events(): void {
		$instance = Scheduler::get_instance();

		// When tracker disabled, does nothing.
		$instance->sweep_ended_events();

		add_filter( 'gatherpress_event_upcoming_tracking_enabled', '__return_true' );

		// Create past event and future event.
		$past_id   = $this->create_test_event();
		$future_id = $this->create_test_event();

		update_post_meta( $past_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		update_post_meta( $future_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );

		// Include an invalid/deleted post ID 88888.
		update_option( Scheduler::OPTION_NAME, array( Event::POST_TYPE => array( $past_id, $future_id, 88888 ) ) );

		$ended_posts = array();
		add_action(
			Scheduler::ENDED_ACTION,
			static function ( int $post_id ) use ( &$ended_posts ): void {
				$ended_posts[] = $post_id;
			}
		);

		$instance->sweep_ended_events();

		// Past event fired action.
		$this->assertContains( $past_id, $ended_posts );
		// Future event did not fire action.
		$this->assertNotContains( $future_id, $ended_posts );
		// Deleted post 88888 was removed from tracking.
		$this->assertNotContains( 88888, $instance->get_tracked_ids( Event::POST_TYPE ) );
	}

	/**
	 * Test is_valid_future_event and is_valid_past_event validators.
	 *
	 * @covers ::is_valid_future_event
	 * @covers ::is_valid_past_event
	 * @covers ::is_event
	 *
	 * @return void
	 */
	public function test_event_validators(): void {
		$instance = Scheduler::get_instance();

		// Non-existent post ID.
		$this->assertFalse( $instance->is_valid_future_event( 9999999 ) );
		$this->assertFalse( $instance->is_valid_past_event( 9999999 ) );

		// Non-event post type.
		$standard_post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );
		$this->assertFalse( $instance->is_valid_future_event( $standard_post_id ) );
		$this->assertFalse( $instance->is_valid_past_event( $standard_post_id ) );

		// Future event.
		$future_id = $this->create_test_event();
		update_post_meta( $future_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );
		$this->assertTrue( $instance->is_valid_future_event( $future_id ) );
		$this->assertFalse( $instance->is_valid_past_event( $future_id ) );

		// Past event.
		$past_id = $this->create_test_event();
		update_post_meta( $past_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		$this->assertFalse( $instance->is_valid_future_event( $past_id ) );
		$this->assertTrue( $instance->is_valid_past_event( $past_id ) );
	}

	/**
	 * Test handle_datetime_meta_change schedules the start run when the start changes.
	 *
	 * @covers ::handle_datetime_meta_change
	 * @covers ::sync_event
	 *
	 * @return void
	 */
	public function test_handle_datetime_meta_change_schedules_start(): void {
		$instance = Scheduler::get_instance();
		$event_id = $this->create_test_event();
		$start    = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );

		update_post_meta( $event_id, Scheduler::START_META_KEY, $start );
		$instance->handle_datetime_meta_change( 1, $event_id, Scheduler::START_META_KEY );

		$this->assertSame(
			strtotime( $start ),
			wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) )
		);
	}

	/**
	 * Test add_start_cron schedules the run at the start.
	 *
	 * @covers ::add_start_cron
	 * @covers ::clear_start_cron
	 *
	 * @return void
	 */
	public function test_add_start_cron(): void {
		$instance = Scheduler::get_instance();
		$event_id = $this->create_test_event();
		$start    = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );

		// No start date, nothing scheduled.
		$instance->add_start_cron( $event_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ) );

		update_post_meta( $event_id, Scheduler::START_META_KEY, $start );

		$instance->add_start_cron( $event_id );
		$this->assertSame(
			strtotime( $start ),
			wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) )
		);

		$instance->clear_start_cron( $event_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ) );
	}

	/**
	 * Test add_start_cron clears the run once the start has passed.
	 *
	 * @covers ::add_start_cron
	 *
	 * @return void
	 */
	public function test_add_start_cron_clears_when_time_has_passed(): void {
		$instance = Scheduler::get_instance();
		$event_id = $this->create_test_event();

		update_post_meta( $event_id, Scheduler::START_META_KEY, gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ) );

		update_post_meta( $event_id, Scheduler::START_META_KEY, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		$instance->add_start_cron( $event_id );

		$this->assertFalse( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ) );
	}

	/**
	 * Test unpublishing, deleting and trashing an event clear its start run.
	 *
	 * @covers ::sync_event
	 * @covers ::handle_before_delete_post
	 *
	 * @return void
	 */
	public function test_unpublish_and_delete_clear_start_run(): void {
		$instance = Scheduler::get_instance();
		$event_id = $this->create_test_event();

		update_post_meta( $event_id, Scheduler::START_META_KEY, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ) );

		$post = get_post( $event_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$instance->handle_transition_post_status( 'draft', 'publish', $post );
		$this->assertFalse(
			wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ),
			'Unpublish clears.'
		);

		$instance->add_start_cron( $event_id );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ) );

		$instance->handle_before_delete_post( $event_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ), 'Delete clears.' );

		// Trashing runs through transition_post_status like any unpublish.
		$instance->add_start_cron( $event_id );
		wp_trash_post( $event_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::START_CRON_ACTION, array( $event_id ) ), 'Trash clears.' );
	}

	/**
	 * Test handle_start_run fires the starting action only for published upcoming events.
	 *
	 * @covers ::handle_start_run
	 *
	 * @return void
	 */
	public function test_handle_start_run(): void {
		$instance = Scheduler::get_instance();
		$fired    = array();
		$listener = static function ( int $post_id ) use ( &$fired ): void {
			$fired[] = $post_id;
		};

		add_action( Scheduler::STARTING_ACTION, $listener );

		$upcoming = $this->create_test_event();
		$draft    = $this->create_test_event( array( 'post_status' => 'draft' ) );
		$post_id  = $this->factory()->post->create();

		$instance->handle_start_run( $upcoming );
		$instance->handle_start_run( $draft );
		$instance->handle_start_run( $post_id );

		remove_action( Scheduler::STARTING_ACTION, $listener );

		$this->assertSame( array( $upcoming ), $fired );
	}
}
