<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Abilities_Integration.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\Abilities_Integration;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities_Integration.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Abilities_Integration
 */
class Test_Abilities_Integration extends Base {
	/**
	 * Coverage for execute_list_venues method with no venues.
	 *
	 * @covers ::execute_list_venues
	 *
	 * @return void
	 */
	public function test_execute_list_venues_with_no_venues(): void {
		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_venues();

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertEmpty( $result['data'], 'Failed to assert data is empty.' );
		$this->assertStringContainsString( 'Found 0 venue', $result['message'], 'Failed to assert message contains count.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}

	/**
	 * Coverage for execute_list_venues method with venues.
	 *
	 * @covers ::execute_list_venues
	 *
	 * @return void
	 */
	public function test_execute_list_venues_with_venues(): void {
		// Create test venues.
		$venue_id_1 = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Downtown Library',
				'post_status' => 'publish',
			)
		);
		$venue_id_2 = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Community Center',
				'post_status' => 'publish',
			)
		);

		// Add venue information.
		$venue_info = array(
			'fullAddress' => '123 Main St',
			'phoneNumber' => '555-1234',
			'website'     => 'https://example.com',
			'latitude'    => '40.7128',
			'longitude'   => '-74.0060',
		);
		update_post_meta( $venue_id_1, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_venues();

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertCount( 2, $result['data'], 'Failed to assert data has 2 venues.' );

		// Find Downtown Library in results (order not guaranteed).
		$library = null;
		foreach ( $result['data'] as $venue ) {
			if ( 'Downtown Library' === $venue['name'] ) {
				$library = $venue;
				break;
			}
		}

		$this->assertNotNull( $library, 'Failed to find Downtown Library in results.' );
		$this->assertSame( '123 Main St', $library['address'], 'Failed to assert venue address.' );
		$this->assertSame( '555-1234', $library['phone'], 'Failed to assert venue phone.' );
	}

	/**
	 * Coverage for execute_list_events method with no events.
	 *
	 * @covers ::execute_list_events
	 *
	 * @return void
	 */
	public function test_execute_list_events_with_no_events(): void {
		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_events( array( 'max_number' => 10 ) );

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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_events( array( 'max_number' => 3 ) );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertCount( 3, $result['data']['events'], 'Failed to assert data has 3 events.' );
	}

	/**
	 * Coverage for execute_create_venue method with valid parameters.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_with_valid_params(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'name'    => 'Test Venue',
			'address' => '456 Test St',
			'phone'   => '555-9999',
			'website' => 'https://test.com',
		);
		$result   = $instance->execute_create_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsInt( $result['venue_id'], 'Failed to assert venue_id is an integer.' );
		$this->assertStringContainsString( 'Test Venue', $result['message'], 'Failed to assert message contains venue name.' ); // phpcs:ignore Generic.Files.LineLength.TooLong

		// Verify venue was created.
		$venue_post = get_post( $result['venue_id'] );
		$this->assertSame( Venue::POST_TYPE, $venue_post->post_type, 'Failed to assert post type is venue.' );
		$this->assertSame( 'Test Venue', $venue_post->post_title, 'Failed to assert venue title.' );
		$this->assertStringContainsString( 'gatherpress/venue-template', $venue_post->post_content, 'Failed to assert venue template pattern.' ); // phpcs:ignore Generic.Files.LineLength.TooLong

		// Verify venue information.
		$venue_info = json_decode( get_post_meta( $result['venue_id'], 'gatherpress_venue_information', true ), true );
		$this->assertSame( '456 Test St', $venue_info['fullAddress'], 'Failed to assert venue address saved.' );
		$this->assertSame( '555-9999', $venue_info['phoneNumber'], 'Failed to assert venue phone saved.' );
		$this->assertSame( 'https://test.com', $venue_info['website'], 'Failed to assert venue website saved.' );
	}

	/**
	 * Coverage for geocoding functionality in execute_create_venue.
	 *
	 * @covers ::execute_create_venue
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_execute_create_venue_geocodes_address(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'name'    => 'Test Geocoded Venue',
			'address' => '1600 Amphitheater Parkway, Mountain View, CA',
		);
		$result   = $instance->execute_create_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify venue information includes geocoded coordinates.
		$venue_info = json_decode( get_post_meta( $result['venue_id'], 'gatherpress_venue_information', true ), true );
		$this->assertArrayHasKey( 'latitude', $venue_info, 'Failed to assert latitude exists.' );
		$this->assertArrayHasKey( 'longitude', $venue_info, 'Failed to assert longitude exists.' );

		// Verify coordinates are not the default '0' values (which would indicate geocoding failed).
		// Note: We can't test for exact coordinates since the API might return slightly different values,
		// but we can verify they're numeric and not zero.
		$this->assertIsString( $venue_info['latitude'], 'Failed to assert latitude is a string.' );
		$this->assertIsString( $venue_info['longitude'], 'Failed to assert longitude is a string.' );

		// If geocoding succeeded, coordinates should be non-zero numeric strings.
		// If it failed, they should be '0'.
		if ( '0' !== $venue_info['latitude'] && '0' !== $venue_info['longitude'] ) {
			$this->assertIsNumeric( $venue_info['latitude'], 'Failed to assert latitude is numeric.' );
			$this->assertIsNumeric( $venue_info['longitude'], 'Failed to assert longitude is numeric.' );
		}
	}

	/**
	 * Coverage for execute_create_venue method without required name.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_without_name(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'address' => '456 Test St',
		);
		$result   = $instance->execute_create_venue( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'name is required', $result['message'], 'Failed to assert error message.' );
	}

	/**
	 * Coverage for execute_create_venue method without required address.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_without_address(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'name' => 'Test Venue',
		);
		$result   = $instance->execute_create_venue( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'address is required', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}

	/**
	 * Coverage for execute_create_event method with valid parameters.
	 *
	 * @covers ::execute_create_event
	 *
	 * @return void
	 */
	public function test_execute_create_event_with_valid_params(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'datetime_end'   => '2025-12-25 21:00:00',
			'description'    => 'Test event description',
		);
		$result   = $instance->execute_create_event( $params );

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
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
		);
		$result   = $instance->execute_create_event( $params );

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
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'post_status'    => 'publish',
		);
		$result   = $instance->execute_create_event( $params );

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
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'datetime_start' => '2025-12-25 19:00:00',
		);
		$result   = $instance->execute_create_event( $params );

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
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'title' => 'Test Event',
		);
		$result   = $instance->execute_create_event( $params );

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
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'title'          => 'Test Event',
			'datetime_start' => 'Jan 25, 2025 7pm',
		);
		$result   = $instance->execute_create_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Invalid start date/time format', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
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

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'title'          => 'Test Event',
			'datetime_start' => '2025-12-25 19:00:00',
			'venue_id'       => $venue_id,
		);
		$result   = $instance->execute_create_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify venue was associated.
		$terms = get_the_terms( $result['event_id'], Venue::TAXONOMY );
		$this->assertNotEmpty( $terms, 'Failed to assert venue terms exist.' );
	}

	/**
	 * Coverage for execute_update_venue method with valid parameters.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_valid_params(): void {
		// Create a venue first.
		$venue_id   = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Original Name',
				'post_status' => 'publish',
			)
		);
		$venue_info = array(
			'fullAddress' => 'Original Address',
			'phoneNumber' => '555-1111',
		);
		update_post_meta( $venue_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'venue_id' => $venue_id,
			'name'     => 'Updated Name',
			'phone'    => '555-9999',
		);
		$result   = $instance->execute_update_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( $venue_id, $result['venue_id'], 'Failed to assert venue_id matches.' );

		// Verify title was updated.
		$venue_post = get_post( $venue_id );
		$this->assertSame( 'Updated Name', $venue_post->post_title, 'Failed to assert title updated.' );

		// Verify venue information was updated.
		$updated_info = json_decode( get_post_meta( $venue_id, 'gatherpress_venue_information', true ), true );
		$this->assertSame( 'Original Address', $updated_info['fullAddress'], 'Failed to assert address unchanged.' );
		$this->assertSame( '555-9999', $updated_info['phoneNumber'], 'Failed to assert phone updated.' );
	}

	/**
	 * Coverage for execute_update_venue method without venue_id.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_without_venue_id(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'name' => 'Updated Name',
		);
		$result   = $instance->execute_update_venue( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Venue ID is required', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}

	/**
	 * Coverage for execute_update_venue method with invalid venue_id.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_invalid_id(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'venue_id' => 999999,
			'name'     => 'Updated Name',
		);
		$result   = $instance->execute_update_venue( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Venue not found', $result['message'], 'Failed to assert error message.' );
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
		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-01-15 18:00:00',
				'datetime_end'   => '2025-01-15 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'event_id'       => $event_id,
			'title'          => 'Updated Title',
			'datetime_start' => '2025-12-31 20:00:00',
		);
		$result   = $instance->execute_update_event( $params );

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
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'title' => 'Updated Title',
		);
		$result   = $instance->execute_update_event( $params );

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
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'event_id' => 999999,
			'title'    => 'Updated Title',
		);
		$result   = $instance->execute_update_event( $params );

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

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'event_id'       => $event_id,
			'datetime_start' => 'Invalid Date',
		);
		$result   = $instance->execute_update_event( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Invalid start date/time format', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
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

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'event_id'    => $event_id,
			'post_status' => 'publish',
		);
		$result   = $instance->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( 'publish', $result['post_status'], 'Failed to assert post_status returned.' );

		// Verify status was updated.
		$event_post = get_post( $event_id );
		$this->assertSame( 'publish', $event_post->post_status, 'Failed to assert event is published.' );
	}

	/**
	 * Coverage for execute_list_topics method with no topics.
	 *
	 * @covers ::execute_list_topics
	 *
	 * @return void
	 */
	public function test_execute_list_topics_with_no_topics(): void {
		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_topics();

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertEmpty( $result['data'], 'Failed to assert data is empty.' );
	}

	/**
	 * Coverage for execute_list_topics method with topics.
	 *
	 * @covers ::execute_list_topics
	 *
	 * @return void
	 */
	public function test_execute_list_topics_with_topics(): void {
		// Create test topics.
		wp_insert_term( 'Workshop', 'gatherpress_topic' );
		wp_insert_term( 'Social', 'gatherpress_topic' );

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_topics();

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertCount( 2, $result['data'], 'Failed to assert data has 2 topics.' );

		// Find the Workshop topic in the results.
		$workshop = null;
		foreach ( $result['data'] as $topic ) {
			if ( 'Workshop' === $topic['name'] ) {
				$workshop = $topic;
				break;
			}
		}

		$this->assertNotNull( $workshop, 'Failed to find Workshop topic.' );
		$this->assertArrayHasKey( 'id', $workshop, 'Failed to assert topic has id.' );
		$this->assertSame( 'Workshop', $workshop['name'], 'Failed to assert topic name.' );
	}

	/**
	 * Coverage for execute_create_topic method with valid parameters.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_with_valid_params(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'name'        => 'Book Club',
			'description' => 'Events for book club meetings',
		);
		$result   = $instance->execute_create_topic( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'topic_id', $result, 'Failed to assert topic_id exists.' );
		$this->assertSame( 'Book Club', $result['name'], 'Failed to assert topic name.' );

		// Verify topic was created.
		$topic = get_term( $result['topic_id'], 'gatherpress_topic' );
		$this->assertNotNull( $topic, 'Failed to assert topic exists.' );
		$this->assertSame( 'Book Club', $topic->name, 'Failed to assert topic name matches.' );
	}

	/**
	 * Coverage for execute_create_topic method without name.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_without_name(): void {
		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_create_topic( array() );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'required', $result['message'], 'Failed to assert error message.' );
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

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'title'          => 'Test Event with Topic',
			'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) ),
			'topic_ids'      => array( $topic_id ),
		);
		$result   = $instance->execute_create_event( $params );

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

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'event_id'  => $event_id,
			'topic_ids' => array( $topic1_id, $topic2_id ),
		);
		$result   = $instance->execute_update_event( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify topics were assigned.
		$event_topics = wp_get_post_terms( $event_id, 'gatherpress_topic', array( 'fields' => 'ids' ) );
		$this->assertContains( $topic1_id, $event_topics, 'Failed to assert topic1 was assigned.' );
		$this->assertContains( $topic2_id, $event_topics, 'Failed to assert topic2 was assigned.' );
	}
}
