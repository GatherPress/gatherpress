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
use PMC\Unit_Test\Utility;
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
		wp_clear_scheduled_hook( Scheduler::CRON_HOOK );
		wp_clear_scheduled_hook( Scheduler::VALIDATE_CRON_HOOK );
		remove_all_filters( 'gatherpress_event_end_cache_keys' );
		remove_all_filters( 'gatherpress_upcoming_events_option_tracker_enabled' );
		remove_all_filters( 'gatherpress_upcoming_tracker_enabled' );
		remove_all_filters( Event::POST_TYPE . '_upcoming_tracker_enabled' );
		delete_option( 'upcoming_' . Event::POST_TYPE . 's' );

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
				'callback' => array( $instance, 'handle_updated_postmeta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_postmeta',
				'priority' => 10,
				'callback' => array( $instance, 'handle_updated_postmeta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'before_delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'handle_before_delete_post' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::CRON_HOOK,
				'priority' => 10,
				'callback' => array( $instance, 'handle_event_ended_cron' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::ACTION_HOOK,
				'priority' => 10,
				'callback' => array( $instance, 'invalidate_caches' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::ACTION_HOOK,
				'priority' => 10,
				'callback' => array( $instance, 'clear_scheduled_cron' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::ACTION_HOOK,
				'priority' => 10,
				'callback' => array( $instance, 'remove_from_tracking' ),
			),
			array(
				'type'     => 'action',
				'name'     => Scheduler::VALIDATE_CRON_HOOK,
				'priority' => 10,
				'callback' => array( $instance, 'validate_events_ended' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test setup_hooks schedules daily validate cron when tracking is enabled.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks_schedules_daily_cron_when_enabled(): void {
		$instance = Scheduler::get_instance();
		wp_clear_scheduled_hook( Scheduler::VALIDATE_CRON_HOOK );

		add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );

		Utility::invoke_hidden_method( $instance, 'setup_hooks' );

		$this->assertNotEmpty( wp_next_scheduled( Scheduler::VALIDATE_CRON_HOOK ) );
	}

	/**
	 * Test handle_transition_post_status ignores unsupported post types and non-publish transitions.
	 *
	 * @covers ::handle_transition_post_status
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
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );

		// Event post transitioning between draft and pending (neither is publish).
		$event_id = $this->create_test_event( array( 'post_status' => 'draft' ) );
		$event    = get_post( $event_id );
		$this->assertInstanceOf( WP_Post::class, $event );

		$instance->handle_transition_post_status( 'pending', 'draft', $event );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $event_id ) ) );
	}

	/**
	 * Test handle_transition_post_status schedules future events when published.
	 *
	 * @covers ::handle_transition_post_status
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

		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );
	}

	/**
	 * Test handle_transition_post_status does not schedule past events when published.
	 *
	 * @covers ::handle_transition_post_status
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

		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );
	}

	/**
	 * Test handle_transition_post_status clears schedule when unpublishing an event.
	 *
	 * @covers ::handle_transition_post_status
	 *
	 * @return void
	 */
	public function test_handle_transition_post_status_unpublishes_event(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event( array( 'post_status' => 'publish' ) );

		$future_end = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		update_post_meta( $post_id, Scheduler::POST_META_KEY, $future_end );

		$instance->add_scheduled_cron( $post_id );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );

		$post = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );

		$instance->handle_transition_post_status( 'draft', 'publish', $post );

		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );
	}

	/**
	 * Test handle_updated_postmeta schedules future event and unschedules past event.
	 *
	 * @covers ::handle_updated_postmeta
	 *
	 * @return void
	 */
	public function test_handle_updated_postmeta(): void {
		$instance = Scheduler::get_instance();

		// Ignores other meta keys.
		$instance->handle_updated_postmeta( 1, 1, 'some_other_key' );

		// Ignores non-existing or unsupported posts.
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );
		$instance->handle_updated_postmeta( 1, $post_id, Scheduler::POST_META_KEY );

		// Ignores non-published events.
		$draft_id = $this->create_test_event( array( 'post_status' => 'draft' ) );
		$instance->handle_updated_postmeta( 1, $draft_id, Scheduler::POST_META_KEY );

		// Published event with future end date schedules cron.
		$event_id   = $this->create_test_event( array( 'post_status' => 'publish' ) );
		$future_end = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		update_post_meta( $event_id, Scheduler::POST_META_KEY, $future_end );

		$instance->handle_updated_postmeta( 1, $event_id, Scheduler::POST_META_KEY );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_HOOK, array( $event_id ) ) );

		// Published event updated with past end date clears schedule.
		$past_end = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		update_post_meta( $event_id, Scheduler::POST_META_KEY, $past_end );

		$instance->handle_updated_postmeta( 1, $event_id, Scheduler::POST_META_KEY );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $event_id ) ) );
	}

	/**
	 * Test handle_before_delete_post clears scheduled cron and tracking.
	 *
	 * @covers ::handle_before_delete_post
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
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_HOOK, array( $event_id ) ) );

		$instance->handle_before_delete_post( $event_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $event_id ) ) );
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
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );

		// Invalid date string.
		update_post_meta( $post_id, Scheduler::POST_META_KEY, 'not-a-valid-date' );
		$instance->add_scheduled_cron( $post_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );

		// Past date timestamp.
		update_post_meta( $post_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() - 100 ) );
		$instance->add_scheduled_cron( $post_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );

		// Future date timestamp schedules cron.
		$future_end = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		update_post_meta( $post_id, Scheduler::POST_META_KEY, $future_end );
		$instance->add_scheduled_cron( $post_id );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );
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
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );

		// Scheduled event unscheduled.
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, Scheduler::CRON_HOOK, array( $post_id ) );
		$this->assertNotEmpty( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );

		$instance->clear_scheduled_cron( $post_id );
		$this->assertFalse( wp_next_scheduled( Scheduler::CRON_HOOK, array( $post_id ) ) );
	}

	/**
	 * Test handle_event_ended_cron verifies event is past before firing action.
	 *
	 * @covers ::handle_event_ended_cron
	 *
	 * @return void
	 */
	public function test_handle_event_ended_cron(): void {
		$instance = Scheduler::get_instance();
		$post_id  = $this->create_test_event();

		$fired = false;
		add_action(
			Scheduler::ACTION_HOOK,
			static function ( int $event_id ) use ( &$fired, $post_id ): void {
				if ( $event_id === $post_id ) {
					$fired = true;
				}
			}
		);

		// Event with future end date does not fire action.
		update_post_meta( $post_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );
		$instance->handle_event_ended_cron( $post_id );
		$this->assertFalse( $fired );

		// Event with past end date fires action.
		update_post_meta( $post_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		$instance->handle_event_ended_cron( $post_id );
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
	 * @covers ::option_key_for
	 *
	 * @return void
	 */
	public function test_is_tracker_enabled_filters(): void {
		$instance = Scheduler::get_instance();

		$this->assertFalse( $instance->is_tracker_enabled( Event::POST_TYPE ) );
		$this->assertFalse( $instance->any_post_type_enabled() );
		$this->assertSame( 'upcoming_' . Event::POST_TYPE . 's', $instance->option_key_for( Event::POST_TYPE ) );

		// Backwards-compatible filter.
		add_filter( 'gatherpress_upcoming_events_option_tracker_enabled', '__return_true' );
		$this->assertTrue( $instance->is_tracker_enabled( Event::POST_TYPE ) );
		$this->assertTrue( $instance->any_post_type_enabled() );
		remove_all_filters( 'gatherpress_upcoming_events_option_tracker_enabled' );

		// Global filter.
		add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );
		$this->assertTrue( $instance->is_tracker_enabled( Event::POST_TYPE ) );
		remove_all_filters( 'gatherpress_upcoming_tracker_enabled' );

		// Per-post-type filter.
		add_filter( Event::POST_TYPE . '_upcoming_tracker_enabled', '__return_true' );
		$this->assertTrue( $instance->is_tracker_enabled( Event::POST_TYPE ) );
		remove_all_filters( Event::POST_TYPE . '_upcoming_tracker_enabled' );
	}

	/**
	 * Test add_to_tracking and remove_from_tracking operations.
	 *
	 * @covers ::add_to_tracking
	 * @covers ::remove_from_tracking
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
		add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );

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
	 *
	 * @return void
	 */
	public function test_get_tracked_ids_sanitizes_option_data(): void {
		$instance   = Scheduler::get_instance();
		$option_key = $instance->option_key_for( Event::POST_TYPE );

		// Non-array value returns empty array.
		update_option( $option_key, 'not_an_array' );
		$this->assertEmpty( $instance->get_tracked_ids( Event::POST_TYPE ) );

		// Array with strings, zeroes, and non-scalar data.
		update_option( $option_key, array( '12', 0, -5, array(), 'abc', 45 ) );
		$this->assertSame( array( 12, 45 ), $instance->get_tracked_ids( Event::POST_TYPE ) );
	}

	/**
	 * Test remove_id_from_option removes target ID from option array.
	 *
	 * @covers ::remove_id_from_option
	 *
	 * @return void
	 */
	public function test_remove_id_from_option(): void {
		$instance   = Scheduler::get_instance();
		$option_key = 'upcoming_' . Event::POST_TYPE . 's';

		update_option( $option_key, array( 10, 20, 30 ), false );
		$instance->remove_id_from_option( 20, Event::POST_TYPE );

		$this->assertSame( array( 10, 30 ), get_option( $option_key ) );
	}

	/**
	 * Test validate_events_ended daily cron checks.
	 *
	 * @covers ::validate_events_ended
	 * @covers ::remove_id_from_option
	 *
	 * @return void
	 */
	public function test_validate_events_ended(): void {
		$instance = Scheduler::get_instance();

		// When tracker disabled, does nothing.
		$instance->validate_events_ended();

		add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );

		// Create past event and future event.
		$past_id   = $this->create_test_event();
		$future_id = $this->create_test_event();

		update_post_meta( $past_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
		update_post_meta( $future_id, Scheduler::POST_META_KEY, gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ) );

		$option_key = $instance->option_key_for( Event::POST_TYPE );
		// Include an invalid/deleted post ID 88888.
		update_option( $option_key, array( $past_id, $future_id, 88888 ) );

		$ended_posts = array();
		add_action(
			Scheduler::ACTION_HOOK,
			static function ( int $post_id ) use ( &$ended_posts ): void {
				$ended_posts[] = $post_id;
			}
		);

		$instance->validate_events_ended();

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
}
