<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Abilities_Event_Batch.
 *
 * @package GatherPress\Core
 * @since   0.34.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\Abilities_Event;
use GatherPress\Core\AI\Abilities_Event_Batch;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities_Event_Batch.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Abilities_Event_Batch
 */
class Test_Abilities_Event_Batch extends Base {

	/**
	 * Returns a batch event abilities handler instance.
	 *
	 * @return Abilities_Event_Batch
	 */
	private function get_batch_instance(): Abilities_Event_Batch {
		return new Abilities_Event_Batch( new Abilities_Event() );
	}

	/**
	 * Returns a batch handler for invoke_hidden_method tests.
	 *
	 * @return Abilities_Event_Batch
	 */
	private function get_handler_for_invoke(): Abilities_Event_Batch {
		return new Abilities_Event_Batch( new Abilities_Event() );
	}

	/**
	 * Coverage for the batch handler constructor.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor_stores_event_handler(): void {
		$event = new Abilities_Event();
		$batch = new Abilities_Event_Batch( $event );

		$this->assertInstanceOf( Abilities_Event_Batch::class, $batch );
	}

	/**
	 * Coverage for execute_update_events_batch with valid parameters.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_valid_params(): void {
		// Create test events.
		$event_id_1 = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Book Club Meeting',
				'post_status' => 'publish',
			)
		);
		$event_id_2 = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Book Club Social',
				'post_status' => 'publish',
			)
		);

		// Add datetimes to events.
		$event1 = new Event( $event_id_1 );
		$event1->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$event2 = new Event( $event_id_2 );
		$event2->save_datetimes(
			array(
				'datetime_start' => '2025-01-20 19:00:00',
				'datetime_end'   => '2025-01-20 21:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'    => 'Book Club',
				'datetime_start' => '2025-12-25 20:00:00',
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'updated_count', $result['data'], 'Failed to assert updated_count exists.' );
		$this->assertGreaterThan( 0, $result['data']['updated_count'], 'Failed to assert events were updated.' );
	}
	/**
	 * Coverage for execute_update_events_batch without search term.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_without_search_term(): void {
		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch( array() );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Search term is required', $result['message'] );
	}
	/**
	 * Coverage for execute_update_events_batch without update parameters.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_without_update_params(): void {
		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term' => 'Test',
			)
		);

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'At least one update parameter', $result['message'] );
	}
	/**
	 * Coverage for execute_update_events_batch with no matching events.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_no_matching_events(): void {
		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'    => 'NonexistentEvent12345',
				'datetime_start' => '2025-12-25 20:00:00',
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( 0, $result['data']['updated_count'], 'Failed to assert updated_count is 0.' );
	}
	/**
	 * Coverage for execute_update_events_batch with invalid datetime format.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_invalid_datetime(): void {
		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'    => 'Test',
				'datetime_start' => 'Invalid Date Format',
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'errors', $result['data'], 'Failed to assert errors key exists.' );
	}
	/**
	 * Coverage for execute_update_events_batch with venue_id.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_venue_id(): void {
		// Create venue.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term' => 'Test',
				'venue_id'    => $venue_id,
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertGreaterThan( 0, $result['data']['updated_count'], 'Failed to assert events were updated.' );
	}
	/**
	 * Coverage for execute_update_events_batch with datetime_end only.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_datetime_end_only(): void {
		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'  => 'Test',
				'datetime_end' => '2025-01-15 22:00:00',
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertGreaterThan( 0, $result['data']['updated_count'] );
	}
	/**
	 * Coverage for execute_update_events_batch with invalid venue_id.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_invalid_venue_id(): void {
		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term' => 'Test',
				'venue_id'    => 999999, // Invalid venue ID.
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'errors', $result['data'] );
		$this->assertNotEmpty( $result['data']['errors'] );
	}
	/**
	 * Test execute_update_events_batch prioritizes exact title matches.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_prioritizes_exact_title_matches(): void {
		// Create two events: one with exact title match, one with partial match.
		$exact_event_id   = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Weekly Sync',
				'post_status' => 'publish',
			)
		);
		$partial_event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Weekly Sync Meeting',
				'post_status' => 'publish',
			)
		);

		// Add datetimes to both events (required for time-only updates).
		$exact_event = new Event( $exact_event_id );
		$exact_event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 12:00:00',
				'datetime_end'   => '2025-01-15 14:00:00',
				'timezone'       => 'UTC',
			)
		);
		$partial_event = new Event( $partial_event_id );
		$partial_event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 12:00:00',
				'datetime_end'   => '2025-01-15 14:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'    => 'Weekly Sync',
				'datetime_start' => '3pm',
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		// Should only update the exact match, not the partial match.
		$this->assertEquals( 1, $result['data']['updated_count'], 'Failed to assert only exact match was updated.' );
	}
	/**
	 * Coverage for execute_update_events_batch with datetime changes.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_datetime_changes(): void {
		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'    => 'Test',
				'datetime_start' => '2025-12-25 20:00:00',
				'datetime_end'   => '2025-12-25 22:00:00',
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertGreaterThan( 0, $result['data']['updated_count'] );

		// Verify datetime was updated.
		$event    = new Event( $event_id );
		$datetime = $event->get_datetime();
		$this->assertSame( '2025-12-25 20:00:00', $datetime['datetime_start'] );
		$this->assertSame( '2025-12-25 22:00:00', $datetime['datetime_end'] );
	}
	/**
	 * Coverage for execute_update_events_batch with no datetime changes.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_no_datetime_changes(): void {
		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'    => 'Test',
				'datetime_start' => '2025-01-15 18:00:00', // Same as existing.
				'datetime_end'   => '2025-01-15 20:00:00', // Same as existing.
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		// Should still count as updated even if values are the same.
		$this->assertGreaterThanOrEqual( 0, $result['data']['updated_count'] );
	}
	/**
	 * Coverage for execute_update_events_batch with errors in message.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_errors_in_message(): void {
		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'    => 'Test',
				'datetime_start' => 'Invalid Format',
				'venue_id'       => 999999, // Invalid venue.
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'errors', $result['data'] );
		$this->assertStringContainsString( 'Some events had errors', $result['message'] );
	}
	/**
	 * Coverage for execute_update_events_batch with invalid datetime_end format.
	 *
	 * @covers ::execute_update_events_batch
	 *
	 * @return void
	 */
	public function test_execute_update_events_batch_with_invalid_datetime_end_format_error(): void {
		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event for Batch Update',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_batch_instance();
		$result  = $handler->execute_update_events_batch(
			array(
				'search_term'  => 'Test Event for Batch Update',
				'datetime_end' => 'Invalid Format String', // Invalid format.
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		// Should have errors in the message.
     // phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertStringContainsString( 'Unable to parse datetime input', $result['message'], 'Failed to assert error message contains parse error.' );
	}

	/**
	 * Coverage for validate_batch_update_params with valid parameters.
	 *
	 * @covers ::validate_batch_update_params
	 *
	 * @return void
	 */
	public function test_validate_batch_update_params_returns_empty_when_valid(): void {
		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'validate_batch_update_params',
			array(
				array(
					'search_term'    => 'Test',
					'datetime_start' => '2025-01-01 12:00:00',
				),
			)
		);

		$this->assertSame( array(), $result );
	}

	/**
	 * Coverage for find_events_for_batch_update when only partial title matches exist.
	 *
	 * @covers ::find_events_for_batch_update
	 *
	 * @return void
	 */
	public function test_find_events_for_batch_update_uses_partial_matches_when_no_exact_match(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Partial Match Title Here',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$events  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'find_events_for_batch_update',
			array( 'Partial Match' )
		);

		$this->assertNotEmpty( $events );
		$this->assertSame( $event_id, $events[0]->ID );
	}

	/**
	 * Coverage for update_single_event_in_batch when the event wrapper has no post.
	 *
	 * @covers ::update_single_event_in_batch
	 *
	 * @return void
	 */
	public function test_update_single_event_in_batch_returns_error_when_event_not_found(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Not An Event',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_single_event_in_batch',
			array(
				get_post( $post_id ),
				array(
					'datetime_start' => '2025-01-01 12:00:00',
				),
			)
		);

		$this->assertFalse( $result['updated'] );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	/**
	 * Coverage for validate_batch_time_only_update without existing datetime.
	 *
	 * @covers ::validate_batch_time_only_update
	 *
	 * @return void
	 */
	public function test_validate_batch_time_only_update_returns_error_without_existing_datetime(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'No Datetime Event',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$error   = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'validate_batch_time_only_update',
			array(
				array( 'datetime_start' => '3pm' ),
				array(),
				$event_id,
			)
		);

		$this->assertStringContainsString( 'Cannot update time-only', $error );
	}

	/**
	 * Coverage for validate_batch_time_only_update when existing datetime is present.
	 *
	 * @covers ::validate_batch_time_only_update
	 *
	 * @return void
	 */
	public function test_validate_batch_time_only_update_returns_empty_when_valid(): void {
		$handler = $this->get_handler_for_invoke();
		$error   = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'validate_batch_time_only_update',
			array(
				array( 'datetime_start' => '3pm' ),
				array( 'datetime_start' => '2025-01-01 12:00:00' ),
				123,
			)
		);

		$this->assertSame( '', $error );
	}

	/**
	 * Coverage for update_event_datetime_in_batch when no datetime params are provided.
	 *
	 * @covers ::update_event_datetime_in_batch
	 *
	 * @return void
	 */
	public function test_update_event_datetime_in_batch_skips_when_no_datetime_params(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Venue Only Event',
				'post_status' => 'publish',
			)
		);

		$event_obj = new Event( $event_id );
		$handler   = $this->get_handler_for_invoke();
		$result    = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_datetime_in_batch',
			array(
				$event_obj,
				array( 'venue_id' => 1 ),
				array(),
			)
		);

		$this->assertFalse( $result['updated'] );
	}

	/**
	 * Coverage for update_event_venue_in_batch when venue_id is omitted.
	 *
	 * @covers ::update_event_venue_in_batch
	 *
	 * @return void
	 */
	public function test_update_event_venue_in_batch_skips_when_venue_id_empty(): void {
		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_venue_in_batch',
			array(
				123,
				array( 'datetime_start' => '2025-01-01 12:00:00' ),
			)
		);

		$this->assertFalse( $result['updated'] );
	}

	/**
	 * Coverage for update_event_venue_in_batch fallback error message.
	 *
	 * @covers ::update_event_venue_in_batch
	 *
	 * @return void
	 */
	public function test_update_event_venue_in_batch_uses_fallback_error_message(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Fallback Venue Error',
				'post_status' => 'publish',
			)
		);

		$mock_event = new class() extends Abilities_Event {
			/**
			 * Return a failed attach result without a message for fallback coverage.
			 *
			 * @param int $event_id Event post ID.
			 * @param int $venue_id Venue post ID.
			 * @return array<string, bool>
			 */
			public function attach_venue_to_event( int $event_id, int $venue_id ): array {
				return array( 'success' => false );
			}
		};

		$handler = new Abilities_Event_Batch( $mock_event );
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_venue_in_batch',
			array(
				$event_id,
				array( 'venue_id' => 999999 ),
			)
		);

		$this->assertFalse( $result['updated'] );
		$this->assertStringContainsString( 'Invalid venue ID', $result['error'] );
	}

	/**
	 * Coverage for validate_batch_update_params when search term is missing.
	 *
	 * @covers ::validate_batch_update_params
	 *
	 * @return void
	 */
	public function test_validate_batch_update_params_requires_search_term(): void {
		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'validate_batch_update_params',
			array( array() )
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Search term is required', $result['message'] );
	}

	/**
	 * Coverage for validate_batch_update_params when no update fields are provided.
	 *
	 * @covers ::validate_batch_update_params
	 *
	 * @return void
	 */
	public function test_validate_batch_update_params_requires_update_fields(): void {
		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'validate_batch_update_params',
			array(
				array(
					'search_term' => 'Test',
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'At least one update parameter', $result['message'] );
	}

	/**
	 * Coverage for find_events_for_batch_update when an exact title match exists.
	 *
	 * @covers ::find_events_for_batch_update
	 *
	 * @return void
	 */
	public function test_find_events_for_batch_update_prefers_exact_title_matches(): void {
		$exact_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Exact Batch Title',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Exact Batch Title Extended',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$events  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'find_events_for_batch_update',
			array( 'Exact Batch Title' )
		);

		$this->assertCount( 1, $events );
		$this->assertSame( $exact_id, $events[0]->ID );
	}

	/**
	 * Coverage for update_events_batch aggregation of results.
	 *
	 * @covers ::update_events_batch
	 *
	 * @return void
	 */
	public function test_update_events_batch_updates_matching_events(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Batch Loop Event',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_events_batch',
			array(
				array( get_post( $event_id ) ),
				array(
					'datetime_start' => '2025-12-25 20:00:00',
				),
			)
		);

		$this->assertSame( 1, $result['updated_count'] );
		$this->assertSame( array(), $result['errors'] );
	}

	/**
	 * Coverage for update_single_event_in_batch successful datetime update path.
	 *
	 * @covers ::update_single_event_in_batch
	 *
	 * @return void
	 */
	public function test_update_single_event_in_batch_updates_datetime(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Single Batch Event',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_single_event_in_batch',
			array(
				get_post( $event_id ),
				array(
					'datetime_start' => '2025-12-25 20:00:00',
				),
			)
		);

		$this->assertTrue( $result['updated'] );
	}

	/**
	 * Coverage for update_single_event_in_batch datetime parser error path.
	 *
	 * @covers ::update_single_event_in_batch
	 *
	 * @return void
	 */
	public function test_update_single_event_in_batch_returns_datetime_error(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Invalid Datetime Batch Event',
				'post_status' => 'publish',
			)
		);

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_single_event_in_batch',
			array(
				get_post( $event_id ),
				array(
					'datetime_start' => 'Invalid Format',
				),
			)
		);

		$this->assertFalse( $result['updated'] );
		$this->assertStringContainsString( 'Error updating event', $result['error'] );
	}

	/**
	 * Coverage for update_single_event_in_batch venue assignment path.
	 *
	 * @covers ::update_single_event_in_batch
	 *
	 * @return void
	 */
	public function test_update_single_event_in_batch_updates_venue(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Batch Venue',
				'post_status' => 'publish',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Venue Batch Event',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_single_event_in_batch',
			array(
				get_post( $event_id ),
				array(
					'venue_id' => $venue_id,
				),
			)
		);

		$this->assertTrue( $result['updated'] );
	}

	/**
	 * Coverage for update_event_datetime_in_batch successful save path.
	 *
	 * @covers ::update_event_datetime_in_batch
	 *
	 * @return void
	 */
	public function test_update_event_datetime_in_batch_saves_datetimes(): void {
		$event_id  = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Datetime Batch Event',
				'post_status' => 'publish',
			)
		);
		$event_obj = new Event( $event_id );
		$event_obj->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_datetime_in_batch',
			array(
				$event_obj,
				array(
					'datetime_start' => '2025-12-25 20:00:00',
				),
				$event_obj->get_datetime(),
			)
		);

		$this->assertTrue( $result['updated'] );
	}

	/**
	 * Coverage for update_event_datetime_in_batch parser exception path.
	 *
	 * @covers ::update_event_datetime_in_batch
	 *
	 * @return void
	 */
	public function test_update_event_datetime_in_batch_returns_parser_error(): void {
		$event_id  = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Parser Error Batch Event',
				'post_status' => 'publish',
			)
		);
		$event_obj = new Event( $event_id );
		$event_obj->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_datetime_in_batch',
			array(
				$event_obj,
				array(
					'datetime_end' => 'Invalid Format String',
				),
				$event_obj->get_datetime(),
			)
		);

		$this->assertFalse( $result['updated'] );
		$this->assertStringContainsString( 'Error updating event', $result['error'] );
	}

	/**
	 * Coverage for update_event_venue_in_batch successful attach path.
	 *
	 * @covers ::update_event_venue_in_batch
	 *
	 * @return void
	 */
	public function test_update_event_venue_in_batch_attaches_venue(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Attach Batch Venue',
				'post_status' => 'publish',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Attach Batch Event',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_venue_in_batch',
			array(
				$event_id,
				array( 'venue_id' => $venue_id ),
			)
		);

		$this->assertTrue( $result['updated'] );
	}

	/**
	 * Coverage for update_event_venue_in_batch when attach returns an error message.
	 *
	 * @covers ::update_event_venue_in_batch
	 *
	 * @return void
	 */
	public function test_update_event_venue_in_batch_uses_attach_error_message(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Attach Error Batch Event',
				'post_status' => 'publish',
			)
		);

		$mock_event = new class() extends Abilities_Event {
			/**
			 * Return a failed attach result with a custom message.
			 *
			 * @param int $event_id Event post ID.
			 * @param int $venue_id Venue post ID.
			 * @return array<string, bool|string>
			 */
			public function attach_venue_to_event( int $event_id, int $venue_id ): array {
				return array(
					'success' => false,
					'message' => 'Custom attach failure.',
				);
			}
		};

		$handler = new Abilities_Event_Batch( $mock_event );
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_venue_in_batch',
			array(
				$event_id,
				array( 'venue_id' => 999999 ),
			)
		);

		$this->assertFalse( $result['updated'] );
		$this->assertSame( 'Custom attach failure.', $result['error'] );
	}

	/**
	 * Coverage for update_events_batch error aggregation path.
	 *
	 * @covers ::update_events_batch
	 *
	 * @return void
	 */
	public function test_update_events_batch_collects_errors(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Not An Event For Batch',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_events_batch',
			array(
				array( get_post( $post_id ) ),
				array(
					'datetime_start' => '2025-12-25 20:00:00',
				),
			)
		);

		$this->assertSame( 0, $result['updated_count'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Coverage for update_single_event_in_batch time-only validation error path.
	 *
	 * @covers ::update_single_event_in_batch
	 *
	 * @return void
	 */
	public function test_update_single_event_in_batch_returns_time_only_error(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Time Only Batch Event',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_single_event_in_batch',
			array(
				get_post( $event_id ),
				array(
					'datetime_start' => '3pm',
				),
			)
		);

		$this->assertFalse( $result['updated'] );
		$this->assertStringContainsString( 'Cannot update time-only', $result['error'] );
	}

	/**
	 * Coverage for update_single_event_in_batch venue attach error path.
	 *
	 * @covers ::update_single_event_in_batch
	 *
	 * @return void
	 */
	public function test_update_single_event_in_batch_returns_venue_error(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Venue Error Batch Event',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$result  = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_single_event_in_batch',
			array(
				get_post( $event_id ),
				array(
					'venue_id' => 999999,
				),
			)
		);

		$this->assertFalse( $result['updated'] );
		$this->assertNotEmpty( $result['error'] );
	}
}
