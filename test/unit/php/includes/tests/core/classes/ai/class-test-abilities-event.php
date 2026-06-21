<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Abilities_Event.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\AI;

use DateTime;
use GatherPress\Core\AI\Abilities_Event;
use GatherPress\Core\AI\Abilities_Venue;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities_Event.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Abilities_Event
 */
class Test_Abilities_Event extends Base {
	/**
	 * Returns an event abilities handler instance.
	 *
	 * @return Abilities_Event
	 */
	private function get_event_instance(): Abilities_Event {
		return new Abilities_Event();
	}

	/**
	 * Set venue meta using the current GatherPress meta keys.
	 *
	 * @param int                   $venue_id Venue post ID.
	 * @param array<string, string> $fields   Field values keyed by unprefixed meta suffix.
	 * @return void
	 */
	private function set_venue_test_meta( int $venue_id, array $fields ): void {
		foreach ( $fields as $field => $value ) {
			update_post_meta( $venue_id, Utility::prefix_key( $field ), $value );
		}
	}

	/**
	 * Read venue meta using the current GatherPress Venue API.
	 *
	 * @param int $venue_id Venue post ID.
	 * @return array<string, string>
	 */
	private function get_venue_test_meta( int $venue_id ): array {
		return ( new Venue( $venue_id ) )->get_information();
	}

	/**
	 * Coverage for execute_create_event method with valid parameters.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_valid_params(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'datetime_end'   => '2025-12-25 21:00:00',
			'description'    => 'Test event description',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsInt( $result['event_id'], 'Failed to assert event_id is an integer.' );
		$this->assertSame( 'draft', $result['post_status'], 'Failed to assert post status is draft.' );
		$this->assertStringContainsString( 'Test Event', $result['message'], 'Failed to assert message contains event title.' ); // phpcs:ignore Generic.Files.LineLength.TooLong

		// Verify event was created.
		$event_post = get_post( $result['event_id'] );
		$this->assertSame( Event::POST_TYPE, $event_post->post_type, 'Failed to assert post type is event.' );
		$this->assertSame( 'Test Event', $event_post->post_title, 'Failed to assert event title.' );
		$this->assertSame( 'draft', $event_post->post_status, 'Failed to assert event is draft.' );

		// Verify datetime was saved by using the Event class.
		$event    = new Event( $result['event_id'] );
		$datetime = $event->get_datetime();

		$this->assertNotEmpty( $datetime, 'Failed to assert datetime exists.' );
		$this->assertSame( '2025-12-25 19:00:00', $datetime['datetime_start'], 'Failed to assert start datetime.' );
		$this->assertSame( '2025-12-25 21:00:00', $datetime['datetime_end'], 'Failed to assert end datetime.' );
	}
	/**
	 * Coverage for execute_create_event method defaults to draft status.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_defaults_to_draft(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( 'draft', $result['post_status'], 'Failed to assert defaults to draft.' );

		$event_post = get_post( $result['event_id'] );
		$this->assertSame( 'draft', $event_post->post_status, 'Failed to assert event is draft.' );
	}
	/**
	 * Coverage for execute_create_event method with publish status.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_publish_status(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'post_status'    => 'publish',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( 'publish', $result['post_status'], 'Failed to assert status is publish.' );

		$event_post = get_post( $result['event_id'] );
		$this->assertSame( 'publish', $event_post->post_status, 'Failed to assert event is published.' );
	}
	/**
	 * Coverage for execute_create_event method without required title.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_without_title(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'datetime_start' => '2025-12-25 19:00:00',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'title is required', $result['message'], 'Failed to assert error message.' );
	}
	/**
	 * Coverage for execute_create_event method without required datetime_start.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_without_datetime(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title' => 'Test Event',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'start date/time is required', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}
	/**
	 * Coverage for execute_create_event method with invalid datetime format.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_invalid_datetime(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			// Use a format that cannot be parsed by any parser (not a valid date/time).
			'datetime_start' => 'not-a-date-at-all-xyz123',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertStringContainsString( 'Invalid start date/time format', $result['message'], 'Failed to assert error message.' );
	}
	/**
	 * Coverage for execute_create_event method with venue_id.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_venue(): void {
		// Create a venue first.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'venue_id'       => $venue_id,
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertTrue( $result['venue_link']['success'], 'Failed to assert venue was linked.' );
		$this->assertSame( $venue_id, $result['venue_link']['venue_id'], 'Failed to assert linked venue ID.' );

		$linked_venue = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $result['event_id'] );
		$this->assertInstanceOf( \WP_Post::class, $linked_venue, 'Failed to assert linked venue post exists.' );
		$this->assertSame( $venue_id, $linked_venue->ID, 'Failed to assert event resolves to venue post.' );
	}
	/**
	 * Coverage for execute_create_event linking a venue created via create-venue.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_links_venue_created_via_create_venue(): void {
		$handler = $this->get_event_instance();
		$venue   = new Abilities_Venue();

		$venue_result = $venue->execute_create_venue(
			array(
				'name'    => 'AI Created Venue',
				'address' => '123 Main St, Buffalo, NY',
			)
		);

		$this->assertTrue( $venue_result['success'], 'Failed to assert venue creation succeeded.' );

		$event_result = $handler->execute_create_event(
			array(
				'title'          => 'AI Created Event',
				'datetime_start' => '2025-09-19 13:00:00',
				'venue_id'       => $venue_result['venue_id'],
			)
		);

		$this->assertTrue( $event_result['success'], 'Failed to assert event creation succeeded.' );
		$this->assertTrue( $event_result['venue_link']['success'], 'Failed to assert venue link succeeded.' );

		$linked_venue = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event_result['event_id'] );
		$this->assertInstanceOf( \WP_Post::class, $linked_venue, 'Failed to assert linked venue post exists.' );
		$this->assertSame(
			$venue_result['venue_id'],
			$linked_venue->ID,
			'Failed to assert create-venue then create-event links venue.'
		);
	}
	/**
	 * Coverage for execute_update_event method with valid parameters.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_valid_params(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Original Title',
				'post_status' => 'draft',
			)
		);

		// Add datetime using Event class method.
		$event_obj = new Event( $event_id );
		$event_obj->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'       => $event_id,
			'title'          => 'Updated Title',
			'datetime_start' => '2025-12-31 20:00:00',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( $event_id, $result['event_id'], 'Failed to assert event_id matches.' );

		// Verify title was updated.
		$event_post = get_post( $event_id );
		$this->assertSame( 'Updated Title', $event_post->post_title, 'Failed to assert title updated.' );

		// Verify datetime was updated by using the Event class.
		$event    = new Event( $event_id );
		$datetime = $event->get_datetime();

		$this->assertSame( '2025-12-31 20:00:00', $datetime['datetime_start'], 'Failed to assert datetime updated.' );
	}
	/**
	 * Coverage for execute_update_event method without event_id.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_without_event_id(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title' => 'Updated Title',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Event ID is required', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}
	/**
	 * Coverage for execute_update_event method with invalid event_id.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_invalid_id(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'event_id' => 999999,
			'title'    => 'Updated Title',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Event not found', $result['message'], 'Failed to assert error message.' );
	}
	/**
	 * Coverage for execute_update_event method with invalid datetime format.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_invalid_datetime(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'       => $event_id,
			'datetime_start' => 'Invalid Date',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		// "Invalid Date" doesn't start with date pattern, so it's treated as time-only and fails.
		$this->assertStringContainsString( 'Cannot update time-only without an existing event date', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}
	/**
	 * Coverage for execute_update_event method updating post_status.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_publish_status(): void {
		// Create a draft event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'    => $event_id,
			'post_status' => 'publish',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( 'publish', $result['post_status'], 'Failed to assert post_status returned.' );

		// Verify status was updated.
		$event_post = get_post( $event_id );
		$this->assertSame( 'publish', $event_post->post_status, 'Failed to assert event is published.' );
	}
	/**
	 * Coverage for execute_create_event with topic_ids.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_topics(): void {
		// Create a test topic.
		$topic_result = wp_insert_term( 'Meetup', 'gatherpress_topic' );
		$topic_id     = $topic_result['term_id'];

		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event with Topic',
			'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
			'topic_ids'      => array( $topic_id ),
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'event_id', $result, 'Failed to assert event_id exists.' );

		// Verify topics were assigned.
		$event_topics = wp_get_post_terms( $result['event_id'], 'gatherpress_topic', array( 'fields' => 'ids' ) );
		$this->assertContains( $topic_id, $event_topics, 'Failed to assert topic was assigned to event.' );
	}
	/**
	 * Coverage for execute_update_event with topic_ids.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_topics(): void {
		// Create test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'publish',
			)
		);

		// Create test topics.
		$topic1_result = wp_insert_term( 'Workshop', 'gatherpress_topic' );
		$topic2_result = wp_insert_term( 'Social', 'gatherpress_topic' );
		$topic1_id     = $topic1_result['term_id'];
		$topic2_id     = $topic2_result['term_id'];

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'  => $event_id,
			'topic_ids' => array( $topic1_id, $topic2_id ),
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify topics were assigned.
		$event_topics = wp_get_post_terms( $event_id, 'gatherpress_topic', array( 'fields' => 'ids' ) );
		$this->assertContains( $topic1_id, $event_topics, 'Failed to assert topic1 was assigned.' );
		$this->assertContains( $topic2_id, $event_topics, 'Failed to assert topic2 was assigned.' );
	}
	/**
	 * Coverage for get_default_event_content without description.
	 *
	 * @covers ::get_default_event_content
	 *
	 * @return void
	 */
	public function test_get_default_event_content_without_description(): void {
		$handler = $this->get_event_instance();
		$content = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'get_default_event_content',
			array( '' )
		);

		$this->assertIsString( $content );
		$this->assertStringContainsString( 'wp:gatherpress/event-date', $content );
		$this->assertStringContainsString( 'wp:gatherpress/add-to-calendar', $content );
		$this->assertStringContainsString( 'wp:gatherpress/venue', $content );
		$this->assertStringContainsString( '"patternPicked":true', $content );
		$this->assertStringContainsString( 'wp:gatherpress/rsvp', $content );
		$this->assertStringContainsString( 'wp:paragraph', $content );
		$this->assertStringContainsString( 'gp-ai-description', $content );
	}
	/**
	 * Coverage for get_default_event_content with description.
	 *
	 * @covers ::get_default_event_content
	 *
	 * @return void
	 */
	public function test_get_default_event_content_with_description(): void {
		$handler = $this->get_event_instance();
		$content = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'get_default_event_content',
			array( 'Test event description' )
		);

		$this->assertIsString( $content );
		$this->assertStringContainsString( 'Test event description', $content );
		$this->assertStringContainsString( 'wp:paragraph', $content );
		$this->assertStringContainsString( 'gp-ai-description', $content );
	}
	/**
	 * Coverage for execute_create_event with invalid post_status.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_invalid_post_status(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'post_status'    => 'invalid_status',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		// Should default to draft when invalid status is provided.
		$this->assertSame( 'draft', $result['post_status'], 'Failed to assert defaults to draft.' );
	}
	/**
	 * Coverage for execute_create_event with datetime_end calculation.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_calculates_datetime_end(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify end datetime was calculated (2 hours after start).
		$event    = new Event( $result['event_id'] );
		$datetime = $event->get_datetime();
		$this->assertNotEmpty( $datetime['datetime_end'], 'Failed to assert end datetime was calculated.' );
	}
	/**
	 * Coverage for execute_create_event with invalid datetime_end format.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_invalid_datetime_end(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'datetime_end'   => 'Invalid Format',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		// Should calculate end datetime when invalid format is provided.
		$event    = new Event( $result['event_id'] );
		$datetime = $event->get_datetime();
		$this->assertNotEmpty( $datetime['datetime_end'], 'Failed to assert end datetime was calculated.' );
	}
	/**
	 * Coverage for execute_create_event with WP_Error on insert.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_wp_error(): void {
		add_filter(
			'wp_insert_post_data',
			function ( $data, $postarr ) {
				if ( isset( $postarr['post_type'] ) && Event::POST_TYPE === $postarr['post_type'] ) {
					$data['post_status'] = str_repeat( 'x', 100 );
				}

				return $data;
			},
			10,
			2
		);

		$handler = $this->get_event_instance();
		$result  = $handler->execute_create_event(
			array(
				'title'          => 'Test Event',
				'datetime_start' => '2025-12-25 19:00:00',
			)
		);

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertNotEmpty( $result['message'], 'Failed to assert error message is present.' );
	}
	/**
	 * Coverage for execute_update_event with description update.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_description(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'    => $event_id,
			'description' => 'Updated description',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify description was updated.
		$event_post = get_post( $event_id );
		$this->assertStringContainsString( 'Updated description', $event_post->post_content );
	}
	/**
	 * Coverage for execute_update_event with datetime_end only.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_datetime_end_only(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
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

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'     => $event_id,
			'datetime_end' => '2025-01-15 22:00:00',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify end datetime was updated.
		$event    = new Event( $event_id );
		$datetime = $event->get_datetime();
		$this->assertSame( '2025-01-15 22:00:00', $datetime['datetime_end'] );
	}
	/**
	 * Coverage for execute_update_event with invalid datetime_end format.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_invalid_datetime_end(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'     => $event_id,
			'datetime_end' => 'Invalid Format',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		// "Invalid Format" doesn't start with date pattern, so it's treated as time-only and fails.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertStringContainsString( 'Cannot update time-only without an existing event date', $result['message'], 'Failed to assert error message.' );
	}
	/**
	 * Coverage for execute_update_event with WP_Error on update.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_wp_error(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		add_filter(
			'wp_insert_post_data',
			function ( $data, $postarr ) use ( $event_id ) {
				if ( isset( $postarr['ID'] ) && $event_id === (int) $postarr['ID'] ) {
					$data['post_status'] = str_repeat( 'x', 100 );
				}

				return $data;
			},
			10,
			2
		);

		$handler = $this->get_event_instance();
		$result  = $handler->execute_update_event(
			array(
				'event_id' => $event_id,
				'title'    => 'Updated Title',
			)
		);

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertNotEmpty( $result['message'], 'Failed to assert error message is present.' );
	}
	/**
	 * Coverage for execute_update_event with venue_id update.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_venue_id(): void {
		// Create venue.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		// Create event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id' => $event_id,
			'venue_id' => $venue_id,
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify venue was associated.
		$terms = get_the_terms( $event_id, Venue::TAXONOMY );
		$this->assertNotEmpty( $terms, 'Failed to assert venue terms exist.' );
	}
	/**
	 * Coverage for execute_update_event with invalid venue_id.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_invalid_venue_id(): void {
		// Create event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id' => $event_id,
			'venue_id' => 999999, // Non-existent venue.
		);
		$result  = $handler->execute_update_event( $params );

		// Should succeed but venue won't be associated.
		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
	}
	/**
	 * Coverage for execute_update_event with description update.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_description_update(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => 'Test Event',
				'post_status'  => 'draft',
				'post_content' => 'Original content',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'    => $event_id,
			'description' => 'Updated description content',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify description was updated.
		$event_post = get_post( $event_id );
		$this->assertStringContainsString( 'Updated description content', $event_post->post_content );
	}
	/**
	 * Coverage for execute_update_event with datetime_end validation error.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_invalid_datetime_end_format(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
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

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'     => $event_id,
			'datetime_end' => 'Invalid Format String',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertStringContainsString( 'Unable to parse datetime input', $result['message'], 'Failed to assert error message contains parse error.' );
	}
	/**
	 * Coverage for execute_update_event with venue_id update when venue doesn't exist.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_invalid_venue_id_update(): void {
		// Create event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id' => $event_id,
			'venue_id' => 999999, // Non-existent venue.
		);
		$result  = $handler->execute_update_event( $params );

		// Should succeed but venue won't be associated (lines 1182-1183 check fails).
		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
	}
	/**
	 * Coverage for execute_update_event with venue_id update when venue exists.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_valid_venue_id_update(): void {
		// Create venue.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		// Create event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id' => $event_id,
			'venue_id' => $venue_id,
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify venue was associated (lines 1182-1186).
		$terms = get_the_terms( $event_id, Venue::TAXONOMY );
		$this->assertNotEmpty( $terms, 'Failed to assert venue terms exist.' );
	}
	/**
	 * Coverage for execute_update_event with empty datetime_params.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_without_datetime_params(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id' => $event_id,
			'title'    => 'Updated Title',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify title was updated but datetime wasn't touched.
		$event_post = get_post( $event_id );
		$this->assertSame( 'Updated Title', $event_post->post_title );
	}
	/**
	 * Coverage for execute_create_event with invalid post_status (line 922).
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_invalid_post_status_value(): void {
		$handler = $this->get_event_instance();
		$params  = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'post_status'    => 'invalid_status_value',
		);
		$result  = $handler->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		// Should default to draft when invalid status is provided (line 922).
		$this->assertSame( 'draft', $result['post_status'], 'Failed to assert defaults to draft.' );
	}
	/**
	 * Test execute_update_event with time-only start datetime.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_time_only_start(): void {
		// Create an event with existing datetime.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$event_obj = new Event( $event_id );
		$event_obj->save_datetimes(
			array(
				'datetime_start' => '2025-01-04 12:00:00',
				'datetime_end'   => '2025-01-04 14:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'       => $event_id,
			'datetime_start' => '3pm',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify datetime was updated correctly (time merged with existing date).
		$updated_datetime = ( new Event( $event_id ) )->get_datetime();
		$this->assertEquals(
			'2025-01-04 15:00:00',
			$updated_datetime['datetime_start'],
			'Failed to assert start time was updated to 3pm on existing date.'
		);
		// End time should be recalculated (start + 2 hours), not preserved.
		$this->assertEquals(
			'2025-01-04 17:00:00',
			$updated_datetime['datetime_end'],
			'Failed to assert end datetime was recalculated (start + 2 hours).'
		);
	}
	/**
	 * Test execute_update_event with time-only end datetime.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_time_only_end(): void {
		// Create an event with existing datetime.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$event_obj = new Event( $event_id );
		$event_obj->save_datetimes(
			array(
				'datetime_start' => '2025-01-04 12:00:00',
				'datetime_end'   => '2025-01-04 14:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'     => $event_id,
			'datetime_end' => '5pm',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify datetime was updated correctly (time merged with existing date).
		$updated_datetime = ( new Event( $event_id ) )->get_datetime();
		$this->assertEquals(
			'2025-01-04 12:00:00',
			$updated_datetime['datetime_start'],
			'Failed to assert start datetime was preserved.'
		);
		$this->assertEquals(
			'2025-01-04 17:00:00',
			$updated_datetime['datetime_end'],
			'Failed to assert end time was updated to 5pm on existing date.'
		);
	}
	/**
	 * Test execute_update_event with time-only start when no existing datetime.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_time_only_start_no_existing(): void {
		// Create an event without datetime.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'       => $event_id,
			'datetime_start' => '3pm',
		);
		$result  = $handler->execute_update_event( $params );

		// Should fail when time-only is provided without existing datetime.
		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString(
			'Cannot update time-only without an existing event date',
			$result['message'],
			'Failed to assert error message is correct.'
		);
	}
	/**
	 * Test execute_update_event with time-only end when no existing datetime.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_time_only_end_no_existing(): void {
		// Create an event without datetime.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'     => $event_id,
			'datetime_end' => '5pm',
		);
		$result  = $handler->execute_update_event( $params );

		// Should fail when time-only is provided without existing datetime.
		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString(
			'Cannot update time-only without an existing event date',
			$result['message'],
			'Failed to assert error message is correct.'
		);
	}
	/**
	 * Test execute_update_event with invalid datetime input throws error.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_invalid_datetime_input(): void {
		// Create an event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'       => $event_id,
			'datetime_start' => 'completely invalid datetime input',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		// "completely invalid datetime input" doesn't start with date pattern, so it's treated as time-only and fails.
		$this->assertStringContainsString(
			'Cannot update time-only without an existing event date',
			$result['message'],
			'Failed to assert error message.'
		);
	}
	/**
	 * Test execute_update_event preserves existing datetime when updating other fields.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_preserves_datetime_when_updating_other_fields(): void {
		// Create an event with existing datetime.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$event_obj = new Event( $event_id );
		$event_obj->save_datetimes(
			array(
				'datetime_start' => '2025-01-04 12:00:00',
				'datetime_end'   => '2025-01-04 14:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id' => $event_id,
			'title'    => 'Updated Title',
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify datetime was preserved.
		$updated_datetime = ( new Event( $event_id ) )->get_datetime();
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 12:00:00', $updated_datetime['datetime_start'], 'Failed to assert start datetime was preserved.' );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertEquals( '2025-01-04 14:00:00', $updated_datetime['datetime_end'], 'Failed to assert end datetime was preserved.' );
	}
	/**
	 * Test update_event_description with existing gp-ai-description paragraph.
	 *
	 * @covers ::update_event_description
	 *
	 * @return void
	 */
	public function test_update_event_description_with_existing_paragraph(): void {
		$handler          = $this->get_event_instance();
		$existing_content = '<!-- wp:paragraph {"className":"gp-ai-description"} -->' . "\n"
			. '<p>Old description</p>' . "\n"
			. '<!-- /wp:paragraph -->';

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_description',
			array( $existing_content, 'New description' )
		);

		$this->assertStringContainsString( 'New description', $result );
		$this->assertStringContainsString( 'gp-ai-description', $result );
		$this->assertStringNotContainsString( 'Old description', $result );
	}
	/**
	 * Test update_event_description with empty content rebuilds structure.
	 *
	 * @covers ::update_event_description
	 *
	 * @return void
	 */
	public function test_update_event_description_with_empty_content(): void {
		$handler = $this->get_event_instance();

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_description',
			array( '', 'New description' )
		);

		$this->assertStringContainsString( 'New description', $result );
		$this->assertStringContainsString( 'gp-ai-description', $result );
		$this->assertStringContainsString( 'wp:gatherpress/event-date', $result );
	}
	/**
	 * Test update_event_description without gp-ai-description rebuilds.
	 *
	 * @covers ::update_event_description
	 *
	 * @return void
	 */
	public function test_update_event_description_without_marker_rebuilds(): void {
		$handler          = $this->get_event_instance();
		$existing_content = '<!-- wp:paragraph -->' . "\n"
			. '<p>Some other content</p>' . "\n"
			. '<!-- /wp:paragraph -->';

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$handler,
			'update_event_description',
			array( $existing_content, 'New description' )
		);

		// Should rebuild with default structure.
		$this->assertStringContainsString( 'New description', $result );
		$this->assertStringContainsString( 'gp-ai-description', $result );
		$this->assertStringContainsString( 'wp:gatherpress/event-date', $result );
	}
	/**
	 * Coverage for execute_update_event with thumbnail_id parameter.
	 *
	 * @covers ::execute_update_event
	 *
	 * @return void
	 */
	public function test_execute_update_event_with_thumbnail_id(): void {
		// Create an event first.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		// Create an image attachment.
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		// Get attachment file path.
		$attachment_file = get_attached_file( $attachment_id );
		if ( ! $attachment_file || ! file_exists( $attachment_file ) ) {
			// Create a minimal image file for the attachment.
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.jpg';
			// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
			$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
			file_put_contents( $temp_file, $jpeg_data );
			$file_path = $upload_dir['path'] . '/' . basename( $temp_file );
			if ( ! file_exists( $upload_dir['path'] ) ) {
				wp_mkdir_p( $upload_dir['path'] );
			}
			copy( $temp_file, $file_path );
			update_attached_file( $attachment_id, $file_path );
			// Clean up temp file.
			if ( file_exists( $temp_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test file cleanup.
				unlink( $temp_file );
			}
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_file = get_attached_file( $attachment_id );
		if ( $attach_file && file_exists( $attach_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attach_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		$handler = $this->get_event_instance();
		$params  = array(
			'event_id'     => $event_id,
			'thumbnail_id' => $attachment_id,
		);
		$result  = $handler->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify thumbnail was set.
		$thumbnail_id = get_post_thumbnail_id( $event_id );
		$this->assertSame( $attachment_id, $thumbnail_id, 'Failed to assert thumbnail was set.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Returns a handler for direct hidden-method coverage invokes.
	 *
	 * @return Abilities_Event
	 */
	private function get_handler_for_invoke(): Abilities_Event {
		return new Abilities_Event();
	}

	/**
	 * Coverage for validate_event_for_update when event_id is missing.
	 *
	 * @covers ::validate_event_for_update
	 *
	 * @return void
	 */
	public function test_validate_event_for_update_requires_event_id(): void {
		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'validate_event_for_update',
			array( array() )
		);

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Coverage for validate_event_for_update when the event is not found.
	 *
	 * @covers ::validate_event_for_update
	 *
	 * @return void
	 */
	public function test_validate_event_for_update_rejects_invalid_event(): void {
		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'validate_event_for_update',
			array( array( 'event_id' => 999999 ) )
		);

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Coverage for validate_event_for_update with a valid event.
	 *
	 * @covers ::validate_event_for_update
	 *
	 * @return void
	 */
	public function test_validate_event_for_update_returns_event_post(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Valid Event',
				'post_status' => 'draft',
			)
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'validate_event_for_update',
			array( array( 'event_id' => $event_id ) )
		);

		$this->assertSame( $event_id, $result['event_id'] );
		$this->assertSame( 'Valid Event', $result['event_post']->post_title );
	}

	/**
	 * Coverage for prepare_datetime_updates with all supported keys.
	 *
	 * @covers ::prepare_datetime_updates
	 *
	 * @return void
	 */
	public function test_prepare_datetime_updates_collects_all_fields(): void {
		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'prepare_datetime_updates',
			array(
				array(
					'datetime_start' => '2025-01-01 10:00:00',
					'datetime_end'   => '2025-01-01 12:00:00',
					'timezone'       => 'America/New_York',
				),
			)
		);

		$this->assertSame( '2025-01-01 10:00:00', $result['datetime_start'] );
		$this->assertSame( '2025-01-01 12:00:00', $result['datetime_end'] );
		$this->assertSame( 'America/New_York', $result['timezone'] );
	}

	/**
	 * Coverage for validate_time_only_datetime_update when no existing date exists.
	 *
	 * @covers ::validate_time_only_datetime_update
	 *
	 * @return void
	 */
	public function test_validate_time_only_datetime_update_rejects_missing_existing_date(): void {
		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'validate_time_only_datetime_update',
			array(
				array( 'datetime_start' => '3pm' ),
				array(),
			)
		);

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Coverage for validate_time_only_datetime_update when existing date is present.
	 *
	 * @covers ::validate_time_only_datetime_update
	 *
	 * @return void
	 */
	public function test_validate_time_only_datetime_update_allows_existing_date(): void {
		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'validate_time_only_datetime_update',
			array(
				array( 'datetime_start' => '3pm' ),
				array( 'datetime_start' => '2025-01-04 12:00:00' ),
			)
		);

		$this->assertSame( array(), $result );
	}

	/**
	 * Coverage for update_event_post_fields when wp_update_post returns WP_Error.
	 *
	 * @covers ::update_event_post_fields
	 *
	 * @return void
	 */
	public function test_update_event_post_fields_returns_wp_error(): void {
		$event_id   = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);
		$event_post = get_post( $event_id );

		add_filter(
			'wp_insert_post_data',
			function ( $data, $postarr ) use ( $event_id ) {
				if ( isset( $postarr['ID'] ) && $event_id === (int) $postarr['ID'] ) {
					$data['post_status'] = str_repeat( 'x', 100 );
				}

				return $data;
			},
			10,
			2
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_post_fields',
			array( $event_id, $event_post, array( 'title' => 'Updated Title' ) )
		);

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Coverage for update_event_datetime_fields when the parser throws.
	 *
	 * @covers ::update_event_datetime_fields
	 *
	 * @return void
	 */
	public function test_update_event_datetime_fields_returns_parser_error(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
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

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_datetime_fields',
			array(
				$event_id,
				array(
					'datetime_start' => '2025-01-15 20:00:00',
					'datetime_end'   => '2025-01-15 18:00:00',
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['message'] );
	}

	/**
	 * Coverage for update_event_datetime_fields with no datetime params.
	 *
	 * @covers ::update_event_datetime_fields
	 *
	 * @return void
	 */
	public function test_update_event_datetime_fields_noops_without_datetime_params(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_status' => 'draft',
			)
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_datetime_fields',
			array( $event_id, array( 'title' => 'Ignored' ) )
		);

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Coverage for update_event_venue delegating to attach_venue_to_event.
	 *
	 * @covers ::update_event_venue
	 *
	 * @return void
	 */
	public function test_update_event_venue_links_published_venue(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Linked Venue',
				'post_status' => 'publish',
				'post_name'   => 'linked-venue',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Venue Event',
				'post_status' => 'draft',
			)
		);

		\PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_venue',
			array( $event_id, array( 'venue_id' => $venue_id ) )
		);

		$this->assertSame( $venue_id, $this->get_handler_for_invoke()->get_event_venue_id( $event_id ) );
	}

	/**
	 * Coverage for update_event_topics assigning taxonomy terms.
	 *
	 * @covers ::update_event_topics
	 *
	 * @return void
	 */
	public function test_update_event_topics_assigns_terms(): void {
		$topic_id = $this->factory->term->create(
			array(
				'taxonomy' => Topic::TAXONOMY,
				'name'     => 'AI Topic',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Topic Event',
				'post_status' => 'draft',
			)
		);

		\PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_topics',
			array( $event_id, array( 'topic_ids' => array( $topic_id ) ) )
		);

		$terms = wp_get_post_terms( $event_id, Topic::TAXONOMY, array( 'fields' => 'ids' ) );
		$this->assertContains( $topic_id, $terms );
	}

	/**
	 * Coverage for update_event_thumbnail when set_post_thumbnail fails.
	 *
	 * @covers ::update_event_thumbnail
	 *
	 * @return void
	 */
	public function test_update_event_thumbnail_logs_when_set_post_thumbnail_fails(): void {
		$event_id      = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Thumbnail Event',
				'post_status' => 'draft',
			)
		);
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Thumb',
			)
		);

		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		$attachment_file = get_attached_file( $attachment_id );
		if ( ! $attachment_file || ! file_exists( $attachment_file ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.jpg';
			// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
			$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
			file_put_contents( $temp_file, $jpeg_data );
			$file_path = $upload_dir['path'] . '/' . basename( $temp_file );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy -- Test file creation.
			copy( $temp_file, $file_path );
			update_attached_file( $attachment_id, $file_path );
			wp_generate_attachment_metadata( $attachment_id, $file_path );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup.
			unlink( $temp_file );
		}

		add_filter(
			'update_post_metadata',
			static function ( $check, $object_id, $meta_key ) use ( $event_id ) {
				if ( $event_id === (int) $object_id && '_thumbnail_id' === $meta_key ) {
					return false;
				}

				return $check;
			},
			10,
			3
		);

		\PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_thumbnail',
			array( $event_id, array( 'thumbnail_id' => $attachment_id ) )
		);

		$this->assertSame( 0, (int) get_post_thumbnail_id( $event_id ) );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for get_event_venue_id when no venue is linked.
	 *
	 * @covers ::get_event_venue_id
	 *
	 * @return void
	 */
	public function test_get_event_venue_id_returns_zero_without_link(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Unlinked Event',
				'post_status' => 'draft',
			)
		);

		$this->assertSame( 0, $this->get_handler_for_invoke()->get_event_venue_id( $event_id ) );
	}

	/**
	 * Coverage for attach_venue_to_event when the venue post is missing.
	 *
	 * @covers ::attach_venue_to_event
	 *
	 * @return void
	 */
	public function test_attach_venue_to_event_rejects_missing_venue(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Event',
				'post_status' => 'draft',
			)
		);

		$result = $this->get_handler_for_invoke()->attach_venue_to_event( $event_id, 999999 );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'Venue not found', $result['message'] );
	}

	/**
	 * Coverage for attach_venue_to_event when the venue is not publish-ready.
	 *
	 * @covers ::attach_venue_to_event
	 *
	 * @return void
	 */
	public function test_attach_venue_to_event_rejects_unpublished_venue(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Draft Venue',
				'post_status' => 'draft',
				'post_name'   => '',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Event',
				'post_status' => 'draft',
			)
		);

		$result = $this->get_handler_for_invoke()->attach_venue_to_event( $event_id, $venue_id );

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Coverage for attach_venue_to_event linking a published venue.
	 *
	 * @covers ::attach_venue_to_event
	 *
	 * @return void
	 */
	public function test_attach_venue_to_event_links_published_venue(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Published Venue',
				'post_status' => 'publish',
				'post_name'   => 'published-venue',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Event',
				'post_status' => 'draft',
			)
		);

		$result = $this->get_handler_for_invoke()->attach_venue_to_event( $event_id, $venue_id );

		$this->assertTrue( $result['success'] );
		$this->assertSame( $venue_id, $result['venue_id'] );
	}

	/**
	 * Coverage for attach_venue_to_event preserving the online-event sentinel.
	 *
	 * @covers ::attach_venue_to_event
	 *
	 * @return void
	 */
	public function test_attach_venue_to_event_preserves_online_event_sentinel(): void {
		if ( ! term_exists( 'online-event', Venue::TAXONOMY ) ) {
			wp_insert_term( 'Online event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}

		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Hybrid Venue',
				'post_status' => 'publish',
				'post_name'   => 'hybrid-venue',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Hybrid Event',
				'post_status' => 'draft',
			)
		);

		wp_set_object_terms( $event_id, array( 'online-event' ), Venue::TAXONOMY );

		$result = $this->get_handler_for_invoke()->attach_venue_to_event( $event_id, $venue_id );

		$this->assertTrue( $result['success'] );
		$term_slugs = wp_list_pluck( get_the_terms( $event_id, Venue::TAXONOMY ), 'slug' );
		$this->assertContains( 'online-event', $term_slugs );
	}

	/**
	 * Coverage for attach_venue_to_event when wp_insert_term returns WP_Error.
	 *
	 * @covers ::attach_venue_to_event
	 *
	 * @return void
	 */
	public function test_attach_venue_to_event_returns_wp_error_from_term_insert(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Blocked Venue',
				'post_status' => 'publish',
				'post_name'   => 'blocked-venue-term',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Blocked Event',
				'post_status' => 'draft',
			)
		);

		$shadow_source = \GatherPress\Core\Shadow_Source::get_instance();
		$term_slug     = $shadow_source->term_slug_from_post_name( 'blocked-venue-term' );
		$existing_term = term_exists( $term_slug, Venue::TAXONOMY );
		if ( is_array( $existing_term ) ) {
			wp_delete_term( (int) $existing_term['term_id'], Venue::TAXONOMY );
		}

		add_filter(
			'pre_insert_term',
			static function ( $term, $taxonomy ) {
				if ( Venue::TAXONOMY === $taxonomy ) {
					return new \WP_Error( 'gp_test', 'Simulated term insert failure.' );
				}

				return $term;
			},
			10,
			2
		);

		$result = $this->get_handler_for_invoke()->attach_venue_to_event( $event_id, $venue_id );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Simulated term insert failure.', $result['message'] );
	}

	/**
	 * Coverage for update_event_post_fields updating description and post status.
	 *
	 * @covers ::update_event_post_fields
	 *
	 * @return void
	 */
	public function test_update_event_post_fields_updates_description_and_status(): void {
		$event_id   = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => 'Status Event',
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:paragraph {"className":"gp-ai-description"} -->' . "\n"
					. '<p>Old</p>' . "\n"
					. '<!-- /wp:paragraph -->',
			)
		);
		$event_post = get_post( $event_id );

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_post_fields',
			array(
				$event_id,
				$event_post,
				array(
					'description' => 'Updated via helper',
					'post_status' => 'publish',
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$updated_post = get_post( $event_id );
		$this->assertSame( 'publish', $updated_post->post_status );
		$this->assertStringContainsString( 'Updated via helper', $updated_post->post_content );
	}

	/**
	 * Coverage for update_event_post_fields when no post fields change.
	 *
	 * @covers ::update_event_post_fields
	 *
	 * @return void
	 */
	public function test_update_event_post_fields_noops_without_changes(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'No-op Event',
				'post_status' => 'draft',
			)
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_post_fields',
			array( $event_id, get_post( $event_id ), array() )
		);

		$this->assertTrue( $result['success'] );
	}

	/**
	 * Coverage for update_event_datetime_fields rejecting time-only without existing date.
	 *
	 * @covers ::update_event_datetime_fields
	 *
	 * @return void
	 */
	public function test_update_event_datetime_fields_rejects_time_only_without_existing_date(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Time-only Event',
				'post_status' => 'draft',
			)
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_datetime_fields',
			array(
				$event_id,
				array( 'datetime_start' => '3pm' ),
			)
		);

		$this->assertFalse( $result['success'] );
	}

	/**
	 * Coverage for update_event_datetime_fields applying a valid datetime update.
	 *
	 * @covers ::update_event_datetime_fields
	 *
	 * @return void
	 */
	public function test_update_event_datetime_fields_applies_valid_datetime_update(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Datetime Event',
				'post_status' => 'draft',
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

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_datetime_fields',
			array(
				$event_id,
				array( 'datetime_end' => '2025-01-15 21:00:00' ),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame(
			'2025-01-15 21:00:00',
			( new Event( $event_id ) )->get_datetime()['datetime_end']
		);
	}

	/**
	 * Coverage for update_event_venue when venue_id is absent.
	 *
	 * @covers ::update_event_venue
	 *
	 * @return void
	 */
	public function test_update_event_venue_noops_without_venue_id(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'No Venue Event',
				'post_status' => 'draft',
			)
		);

		\PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_venue',
			array( $event_id, array() )
		);

		$this->assertSame( 0, $this->get_handler_for_invoke()->get_event_venue_id( $event_id ) );
	}

	/**
	 * Coverage for update_event_topics when topic_ids is absent.
	 *
	 * @covers ::update_event_topics
	 *
	 * @return void
	 */
	public function test_update_event_topics_noops_without_topic_ids(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'No Topic Event',
				'post_status' => 'draft',
			)
		);

		\PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_topics',
			array( $event_id, array( 'topic_ids' => 'not-an-array' ) )
		);

		$this->assertSame( array(), wp_get_post_terms( $event_id, Topic::TAXONOMY ) );
	}

	/**
	 * Coverage for update_event_thumbnail when thumbnail_id is absent.
	 *
	 * @covers ::update_event_thumbnail
	 *
	 * @return void
	 */
	public function test_update_event_thumbnail_noops_without_thumbnail_id(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'No Thumbnail Event',
				'post_status' => 'draft',
			)
		);

		\PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_thumbnail',
			array( $event_id, array() )
		);

		$this->assertSame( 0, (int) get_post_thumbnail_id( $event_id ) );
	}

	/**
	 * Coverage for update_event_thumbnail with a non-image attachment.
	 *
	 * @covers ::update_event_thumbnail
	 *
	 * @return void
	 */
	public function test_update_event_thumbnail_skips_non_image_attachment(): void {
		$event_id      = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Bad Thumbnail Event',
				'post_status' => 'draft',
			)
		);
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'text/plain',
				'post_title'     => 'Not An Image',
			)
		);

		\PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'update_event_thumbnail',
			array( $event_id, array( 'thumbnail_id' => $attachment_id ) )
		);

		$this->assertSame( 0, (int) get_post_thumbnail_id( $event_id ) );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for get_event_venue_id when a venue is linked.
	 *
	 * @covers ::get_event_venue_id
	 *
	 * @return void
	 */
	public function test_get_event_venue_id_returns_linked_venue_id(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Resolved Venue',
				'post_status' => 'publish',
				'post_name'   => 'resolved-venue',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Resolved Event',
				'post_status' => 'draft',
			)
		);

		$handler = $this->get_handler_for_invoke();
		$attach  = $handler->attach_venue_to_event( $event_id, $venue_id );

		$this->assertTrue( $attach['success'] );
		$this->assertSame( $venue_id, $handler->get_event_venue_id( $event_id ) );
	}

	/**
	 * Coverage for attach_venue_to_event when verification fails after assignment.
	 *
	 * @covers ::attach_venue_to_event
	 *
	 * @return void
	 */
	public function test_attach_venue_to_event_returns_error_when_verification_fails(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Verify Fail Venue',
				'post_status' => 'publish',
				'post_name'   => 'verify-fail-venue',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Verify Fail Event',
				'post_status' => 'draft',
			)
		);

		$resolve_calls = 0;
		add_filter(
			'get_the_terms',
			static function ( $terms, $post_id, $taxonomy ) use ( $event_id, &$resolve_calls ) {
				if ( Venue::TAXONOMY !== $taxonomy || $event_id !== (int) $post_id ) {
					return $terms;
				}

				++$resolve_calls;
				if ( $resolve_calls > 1 ) {
					return false;
				}

				return $terms;
			},
			10,
			3
		);

		$result = $this->get_handler_for_invoke()->attach_venue_to_event( $event_id, $venue_id );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'could not be verified', $result['message'] );
	}

	/**
	 * Coverage for attach_venue_to_event when assign_venue_terms returns WP_Error.
	 *
	 * @covers ::attach_venue_to_event
	 *
	 * @return void
	 */
	public function test_attach_venue_to_event_returns_wp_error_from_term_assignment(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Assign Fail Venue',
				'post_status' => 'publish',
				'post_name'   => 'assign-fail-venue-2',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Assign Fail Event',
				'post_status' => 'draft',
			)
		);

		$handler = new class() extends Abilities_Event {
			/**
			 * Force a WP_Error so attach_venue_to_event covers the assignment failure branch.
			 *
			 * @param int   $event_id Event post ID.
			 * @param int[] $term_ids Term IDs to assign.
			 * @return \WP_Error
			 */
			protected function assign_venue_terms( int $event_id, array $term_ids ) {
				return new \WP_Error( 'gp_test', 'Simulated term assignment failure.' );
			}
		};

		$result = $handler->attach_venue_to_event( $event_id, $venue_id );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Simulated term assignment failure.', $result['message'] );
	}

	/**
	 * Coverage for assign_venue_terms delegating to wp_set_object_terms.
	 *
	 * @covers ::assign_venue_terms
	 *
	 * @return void
	 */
	public function test_assign_venue_terms_assigns_terms_to_event(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Assign Terms Venue',
				'post_status' => 'publish',
				'post_name'   => 'assign-terms-venue',
			)
		);
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Assign Terms Event',
				'post_status' => 'draft',
			)
		);

		$attach = $this->get_handler_for_invoke()->attach_venue_to_event( $event_id, $venue_id );
		$this->assertTrue( $attach['success'] );

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$this->get_handler_for_invoke(),
			'assign_venue_terms',
			array( $event_id, array( (int) $attach['term_id'] ) )
		);

		$this->assertIsArray( $result );
	}
	/**
	 * Coverage for execute_list_events method with no events.
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_with_no_events(): void {
		$handler = $this->get_event_instance();
		$result  = $handler->execute_list_events( array( 'max_number' => 10 ) );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertIsArray( $result['data']['events'], 'Failed to assert data events is an array.' );
		$this->assertEmpty( $result['data']['events'], 'Failed to assert data events is empty.' );
		$this->assertStringContainsString( 'Found 0 event', $result['message'], 'Failed to assert message contains count.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}
	/**
	 * Coverage for execute_list_events method respects max_number parameter.
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_respects_max_number(): void {
		// Create test events with future dates.
		for ( $i = 1; $i <= 5; $i++ ) {
			$event_id = $this->factory->post->create(
				array(
					'post_type'   => Event::POST_TYPE,
					'post_title'  => "Event $i",
					'post_status' => 'publish',
				)
			);

			// Add future datetime using Event class method.
			$event = new Event( $event_id );
			$event->save_datetimes(
				array(
					'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' days' ) ),
					'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' days +2 hours' ) ),
					'timezone'       => 'UTC',
				)
			);
		}

		$handler = $this->get_event_instance();
		$result  = $handler->execute_list_events( array( 'max_number' => 3 ) );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertCount( 3, $result['data']['events'], 'Failed to assert data has 3 events.' );
	}
	/**
	 * Coverage for execute_search_events with valid search term.
	 *
	 * @covers ::execute_search_events
	 *
	 * @return void
	 */
	public function test_execute_search_events_with_valid_term(): void {
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
				'post_title'  => 'Workshop Event',
				'post_status' => 'publish',
			)
		);

		// Add datetimes to event.
		$event1 = new Event( $event_id_1 );
		$event1->save_datetimes(
			array(
				'datetime_start' => '2025-12-25 19:00:00',
				'datetime_end'   => '2025-12-25 21:00:00',
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_event_instance();
		$result  = $handler->execute_search_events( array( 'search_term' => 'Book' ) );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'events', $result['data'], 'Failed to assert events key exists.' );
		$this->assertArrayHasKey( 'count', $result['data'], 'Failed to assert count key exists.' );

		// Verify datetime format is 12-hour (F j, Y, g:i a).
		if ( ! empty( $result['data']['events'] ) ) {
			$event_data = $result['data']['events'][0];
			$this->assertArrayHasKey( 'datetime_start', $event_data, 'Failed to assert datetime_start key exists.' );
			$this->assertArrayHasKey( 'datetime_end', $event_data, 'Failed to assert datetime_end key exists.' );
			// Check format contains am/pm (12-hour format).
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$this->assertMatchesRegularExpression( '/\b(am|pm)\b/i', $event_data['datetime_start'], 'Failed to assert datetime_start is in 12-hour format.' );
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$this->assertMatchesRegularExpression( '/\b(am|pm)\b/i', $event_data['datetime_end'], 'Failed to assert datetime_end is in 12-hour format.' );
		}
	}
	/**
	 * Coverage for execute_search_events without search term.
	 *
	 * @covers ::execute_search_events
	 *
	 * @return void
	 */
	public function test_execute_search_events_without_search_term(): void {
		$handler = $this->get_event_instance();
		$result  = $handler->execute_search_events( array() );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Search term is required', $result['message'] );
	}
	/**
	 * Coverage for execute_search_events with no results.
	 *
	 * @covers ::execute_search_events
	 *
	 * @return void
	 */
	public function test_execute_search_events_with_no_results(): void {
		$handler = $this->get_event_instance();
		$result  = $handler->execute_search_events( array( 'search_term' => 'NonexistentEvent12345' ) );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertEmpty( $result['data']['events'], 'Failed to assert events array is empty.' );
		$this->assertSame( 0, $result['data']['count'], 'Failed to assert count is 0.' );
	}
	/**
	 * Coverage for execute_search_events with max_number parameter.
	 *
	 * @covers ::execute_search_events
	 *
	 * @return void
	 */
	public function test_execute_search_events_with_max_number(): void {
		// Create multiple test events.
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->factory->post->create(
				array(
					'post_type'   => Event::POST_TYPE,
					'post_title'  => "Test Event $i",
					'post_status' => 'publish',
				)
			);
		}

		$handler = $this->get_event_instance();
		$result  = $handler->execute_search_events(
			array(
				'search_term' => 'Test',
				'max_number'  => 3,
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertLessThanOrEqual( 3, $result['data']['count'], 'Failed to assert count respects max_number.' );
	}
	/**
	 * Coverage for execute_list_events with search parameter.
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_with_search(): void {
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
				'post_title'  => 'Workshop Event',
				'post_status' => 'publish',
			)
		);

		// Add future datetimes.
		$event1 = new Event( $event_id_1 );
		$event1->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		$handler = $this->get_event_instance();
		$result  = $handler->execute_list_events( array( 'search' => 'Book' ) );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'events', $result['data'], 'Failed to assert events key exists.' );

		// Verify datetime format is 12-hour (F j, Y, g:i a).
		if ( ! empty( $result['data']['events'] ) ) {
			$event_data = $result['data']['events'][0];
			$this->assertArrayHasKey( 'datetime_start', $event_data, 'Failed to assert datetime_start key exists.' );
			$this->assertArrayHasKey( 'datetime_end', $event_data, 'Failed to assert datetime_end key exists.' );
			// Check format contains am/pm (12-hour format).
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$this->assertMatchesRegularExpression( '/\b(am|pm)\b/i', $event_data['datetime_start'], 'Failed to assert datetime_start is in 12-hour format.' );
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$this->assertMatchesRegularExpression( '/\b(am|pm)\b/i', $event_data['datetime_end'], 'Failed to assert datetime_end is in 12-hour format.' );
		}
	}
	/**
	 * Coverage for execute_list_events with max_number > 100.
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_with_max_number_over_limit(): void {
		// Create test events.
		for ( $i = 1; $i <= 5; $i++ ) {
			$event_id = $this->factory->post->create(
				array(
					'post_type'   => Event::POST_TYPE,
					'post_title'  => "Event $i",
					'post_status' => 'publish',
				)
			);
			$event    = new Event( $event_id );
			$event->save_datetimes(
				array(
					'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' days' ) ),
					'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' days +2 hours' ) ),
					'timezone'       => 'UTC',
				)
			);
		}

		$handler = $this->get_event_instance();
		$result  = $handler->execute_list_events( array( 'max_number' => 200 ) );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertLessThanOrEqual( 100, $result['data']['count'], 'Failed to assert count is capped at 100.' );
	}
	/**
	 * Coverage for execute_list_events with max_number = -1.
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_with_max_number_negative(): void {
		// Create test events.
		for ( $i = 1; $i <= 5; $i++ ) {
			$event_id = $this->factory->post->create(
				array(
					'post_type'   => Event::POST_TYPE,
					'post_title'  => "Event $i",
					'post_status' => 'publish',
				)
			);
			$event    = new Event( $event_id );
			$event->save_datetimes(
				array(
					'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' days' ) ),
					'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' days +2 hours' ) ),
					'timezone'       => 'UTC',
				)
			);
		}

		$handler = $this->get_event_instance();
		$result  = $handler->execute_list_events( array( 'max_number' => -1 ) );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertLessThanOrEqual( 100, $result['data']['count'], 'Failed to assert count is capped at 100.' );
	}
	/**
	 * Coverage for execute_list_events without search (default behavior).
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_without_search(): void {
		// Create test events.
		for ( $i = 1; $i <= 3; $i++ ) {
			$event_id = $this->factory->post->create(
				array(
					'post_type'   => Event::POST_TYPE,
					'post_title'  => "Event $i",
					'post_status' => 'publish',
				)
			);
			$event    = new Event( $event_id );
			$event->save_datetimes(
				array(
					'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' days' ) ),
					'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+' . $i . ' days +2 hours' ) ),
					'timezone'       => 'UTC',
				)
			);
		}

		$handler = $this->get_event_instance();
		$result  = $handler->execute_list_events( array() );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'events', $result['data'], 'Failed to assert events key exists.' );
		$this->assertArrayHasKey( 'count', $result['data'], 'Failed to assert count key exists.' );

		// Verify datetime format is 12-hour (F j, Y, g:i a).
		if ( ! empty( $result['data']['events'] ) ) {
			$event_data = $result['data']['events'][0];
			$this->assertArrayHasKey( 'datetime_start', $event_data, 'Failed to assert datetime_start key exists.' );
			$this->assertArrayHasKey( 'datetime_end', $event_data, 'Failed to assert datetime_end key exists.' );
			// Check format contains am/pm (12-hour format).
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$this->assertMatchesRegularExpression( '/\b(am|pm)\b/i', $event_data['datetime_start'], 'Failed to assert datetime_start is in 12-hour format.' );
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$this->assertMatchesRegularExpression( '/\b(am|pm)\b/i', $event_data['datetime_end'], 'Failed to assert datetime_end is in 12-hour format.' );
		}
	}
	/**
	 * Coverage for execute_list_events with events that have venues.
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_with_venues(): void {
		// Create venue.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		// Create event with venue.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Event with Venue',
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

		// Associate venue.
		$venue_slug = '_' . get_post( $venue_id )->post_name;
		wp_set_object_terms( $event_id, $venue_slug, Venue::TAXONOMY );

		$handler = $this->get_event_instance();
		$result  = $handler->execute_list_events( array() );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		// Find our event in the results.
		$found = false;
		foreach ( $result['data']['events'] as $event_data ) {
			if ( $event_data['id'] === $event_id ) {
				$found = true;
				$this->assertSame( 'Test Venue', $event_data['venue'] );
				break;
			}
		}
		$this->assertTrue( $found, 'Failed to find event in results.' );
	}
	/**
	 * Coverage for execute_list_events with events that have no venues.
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_without_venues(): void {
		// Create event without venue.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Event without Venue',
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

		$handler = $this->get_event_instance();
		$result  = $handler->execute_list_events( array() );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		// Find our event in the results.
		$found = false;
		foreach ( $result['data']['events'] as $event_data ) {
			if ( $event_data['id'] === $event_id ) {
				$found = true;
				// Venue might be null or empty string, both are acceptable.
				$this->assertTrue(
					null === $event_data['venue'] || '' === $event_data['venue'],
					'Failed to assert venue is null or empty.'
				);
				break;
			}
		}
		$this->assertTrue( $found, 'Failed to find event in results.' );
	}
}
