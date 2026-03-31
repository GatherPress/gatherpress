<?php
/**
 * Class handles unit tests for GatherPress\Integrations\Duplicate_Post\Setup.
 *
 * @package GatherPress\Integrations
 * @since 1.0.0
 */

namespace GatherPress\Tests\Integrations\Duplicate_Post;

use GatherPress\Core\Event;
use GatherPress\Integrations\Duplicate_Post\Setup;
use GatherPress\Tests\Base;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Integrations\Duplicate_Post\Setup
 */
class Test_Setup extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'duplicate_post_post_copy',
				'priority' => 10,
				'callback' => array( $instance, 'sync_duplicated_event' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for sync_duplicated_event with an event post.
	 *
	 * @covers ::sync_duplicated_event
	 *
	 * @return void
	 */
	public function test_sync_duplicated_event(): void {
		$instance = Setup::get_instance();

		// Create an event with datetime.
		$post_id = $this->factory->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-12-25 10:00:00',
				'datetime_end'   => '2025-12-25 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Create a "cloned" post with the same meta (simulating duplicate-post).
		$new_post_id = $this->factory->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		// Copy the meta keys to simulate what duplicate-post does.
		$meta_keys = array(
			'gatherpress_datetime_start',
			'gatherpress_datetime_start_gmt',
			'gatherpress_datetime_end',
			'gatherpress_datetime_end_gmt',
			'gatherpress_timezone',
		);

		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			update_post_meta( $new_post_id, $key, $value );
		}

		// Run the sync — this should populate the custom table.
		$instance->sync_duplicated_event( $new_post_id );

		// Verify the custom table was populated.
		$new_event    = new Event( $new_post_id );
		$new_datetime = $new_event->get_datetime();

		$this->assertSame(
			'2025-12-25 10:00:00',
			$new_datetime['datetime_start'],
			'Cloned event should have the same start datetime.'
		);
		$this->assertSame(
			'2025-12-25 14:00:00',
			$new_datetime['datetime_end'],
			'Cloned event should have the same end datetime.'
		);
		$this->assertSame(
			'America/New_York',
			$new_datetime['timezone'],
			'Cloned event should have the same timezone.'
		);
	}

	/**
	 * Coverage for sync_duplicated_event with a non-event post.
	 *
	 * @covers ::sync_duplicated_event
	 *
	 * @return void
	 */
	public function test_sync_duplicated_event_non_event(): void {
		$instance = Setup::get_instance();

		$post_id = $this->factory->post->create(
			array( 'post_type' => 'post' )
		);

		// Should return early without error.
		$instance->sync_duplicated_event( $post_id );

		$this->assertTrue( true, 'Should handle non-event posts gracefully.' );
	}

	/**
	 * Coverage for sync_duplicated_event with no datetime meta.
	 *
	 * @covers ::sync_duplicated_event
	 *
	 * @return void
	 */
	public function test_sync_duplicated_event_no_datetime(): void {
		$instance = Setup::get_instance();

		$post_id = $this->factory->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		// No datetime meta — should return early.
		$instance->sync_duplicated_event( $post_id );

		$event    = new Event( $post_id );
		$datetime = $event->get_datetime();

		$this->assertEmpty(
			$datetime['datetime_start'],
			'Event with no meta should have empty datetime.'
		);
	}
}
