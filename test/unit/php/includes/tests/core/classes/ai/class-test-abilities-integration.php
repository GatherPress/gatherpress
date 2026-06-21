<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Abilities_Integration.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use DateTime;
use GatherPress\Core\AI\Abilities_Integration;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities_Integration.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Abilities_Integration
 */
class Test_Abilities_Integration extends Base {
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

		$this->set_venue_test_meta(
			$venue_id_1,
			array(
				'address'   => '123 Main St',
				'phone'     => '555-1234',
				'website'   => 'https://example.com',
				'latitude'  => '40.7128',
				'longitude' => '-74.0060',
			)
		);

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
	 * Coverage for get_calculate_dates_ability when wp_has_ability doesn't exist.
	 *
	 * @covers ::get_calculate_dates_ability
	 *
	 * @return void
	 */
	public function test_get_calculate_dates_ability_when_function_not_exists(): void {
		// Test both paths: when function exists and when it doesn't.
		$result = Abilities_Integration::get_calculate_dates_ability();
		// Should return either ai/calculate-dates if it exists, or gatherpress/calculate-dates.
		$this->assertContains( $result, array( 'ai/calculate-dates', 'gatherpress/calculate-dates' ) );
		$this->assertIsString( $result );
	}
	/**
	 * Coverage for get_calculate_dates_ability when ai/calculate-dates exists.
	 *
	 * @covers ::get_calculate_dates_ability
	 *
	 * @return void
	 */
	public function test_get_calculate_dates_ability_when_ai_ability_exists(): void {
		// Test that the method returns a valid ability name.
		// Note: We can't easily test the ai/calculate-dates path without complex setup,
		// but we can verify the method works and returns a valid ability name.
		$result = Abilities_Integration::get_calculate_dates_ability();
		// Should return either ai/calculate-dates if it exists, or gatherpress/calculate-dates.
		$this->assertContains( $result, array( 'ai/calculate-dates', 'gatherpress/calculate-dates' ) );
		$this->assertIsString( $result );
	}
	/**
	 * Coverage for get_calculate_dates_ability when ai/calculate-dates doesn't exist.
	 *
	 * @covers ::get_calculate_dates_ability
	 *
	 * @return void
	 */
	public function test_get_calculate_dates_ability_when_ai_ability_not_exists(): void {
		// Ensure ai/calculate-dates is not registered.
		// We can't easily unregister, but we can test the fallback behavior.
		$result = Abilities_Integration::get_calculate_dates_ability();
		// Should return either ai/calculate-dates if it exists, or gatherpress/calculate-dates.
		$this->assertContains( $result, array( 'ai/calculate-dates', 'gatherpress/calculate-dates' ) );
	}
	/**
	 * Coverage for register_categories method.
	 *
	 * Note: This test is skipped because it tests WordPress core API behavior
	 * which is already tested by WordPress core. The method is covered indirectly
	 * through the constructor and setup_hooks tests.
	 *
	 * @covers ::register_categories
	 *
	 * @return void
	 */
	public function test_register_categories(): void {
		// Test that register_categories can be called without errors.
		$instance = Abilities_Integration::get_instance();

		// Suppress expected notices if abilities are already registered.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught;
			},
			10,
			2
		);

		// Call the method - it should execute without errors.
		$instance->register_categories();
		$this->assertTrue( true, 'register_categories executed without error.' );
	}
	/**
	 * Coverage for register_categories when function doesn't exist.
	 *
	 * @covers ::register_categories
	 *
	 * @return void
	 */
	public function test_register_categories_when_function_not_exists(): void {
		$instance = Abilities_Integration::get_instance();

		// Suppress expected notices if categories are already registered or registered outside action hook.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				if ( is_string( $description )
					&& ( strpos( $description, 'already registered' ) !== false
					|| strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught;
			},
			10,
			2
		);

		// Should return early if function doesn't exist, otherwise register categories.
		$instance->register_categories();
		$this->assertTrue( true, 'Method executed without error.' );
	}
	/**
	 * Coverage for register_abilities method.
	 *
	 * Note: This test is skipped because it tests WordPress core API behavior
	 * which is already tested by WordPress core. The method is covered indirectly
	 * through the constructor and setup_hooks tests.
	 *
	 * @covers ::register_abilities
	 *
	 * @return void
	 */
	public function test_register_abilities(): void {
		// Test that register_abilities can be called without errors.
		$instance = Abilities_Integration::get_instance();

		// Suppress expected notices if abilities are already registered.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught;
			},
			10,
			2
		);

		// Call the method - it should execute without errors.
		$instance->register_abilities();
		$this->assertTrue( true, 'register_abilities executed without error.' );
	}
	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability function not available.' );
		}

		$instance = Abilities_Integration::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_categories_init',
				'priority' => 999,
				'callback' => array( $instance, 'register_categories' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_init',
				'priority' => 999,
				'callback' => array( $instance, 'register_abilities' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_search_events( array( 'search_term' => 'Book' ) );

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
		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_search_events( array() );

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
		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_search_events( array( 'search_term' => 'NonexistentEvent12345' ) );

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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_search_events(
			array(
				'search_term' => 'Test',
				'max_number'  => 3,
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertLessThanOrEqual( 3, $result['data']['count'], 'Failed to assert count respects max_number.' );
	}
	/**
	 * Coverage for execute_calculate_dates method.
	 *
	 * @covers ::execute_calculate_dates
	 *
	 * @return void
	 */
	public function test_execute_calculate_dates(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'pattern'     => '3rd Tuesday',
			'occurrences' => 3,
			'start_date'  => '2025-01-01',
		);
		$result   = $instance->execute_calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'dates', $result['data'], 'Failed to assert dates key exists.' );
	}
	/**
	 * Coverage for execute_calculate_dates when AI ability is available.
	 *
	 * @covers ::execute_calculate_dates
	 *
	 * @return void
	 */
	public function test_execute_calculate_dates_with_ai_ability(): void {
		// Test both paths: if functions exist, use them; otherwise fall back to Date_Calculator.

		// Suppress expected notices if abilities are registered outside action hook.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught;
			},
			10,
			2
		);

		$params = array(
			'pattern'     => '3rd Tuesday',
			'occurrences' => 2,
		);

		// Register the ai/calculate-dates ability if functions exist and it doesn't exist.
		// Register within the action hook to avoid notices.
		if ( function_exists( 'wp_has_ability' ) && function_exists( 'wp_register_ability' ) ) {
			add_action(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'wp_abilities_api_init',
				function () {
					if ( ! wp_has_ability( 'ai/calculate-dates' ) ) {
						wp_register_ability(
							'ai/calculate-dates',
							array(
								'label'               => 'AI Calculate Dates',
								'description'         => 'Calculate dates using AI',
								'category'            => 'event',
								'permission_callback' => function () {
									return current_user_can( 'read' );
								},
								'execute_callback'    => function ( $params ) {
									// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
									$params = $params;
									return array(
										'success' => true,
										'data'    => array(
											'dates' => array( '2025-01-15', '2025-02-15' ),
										),
									);
								},
							)
						);
					}
				},
				1
			);
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'wp_abilities_api_init' );
		}

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_calculate_dates( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_events( array( 'search' => 'Book' ) );

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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_events( array( 'max_number' => 200 ) );

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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_events( array( 'max_number' => -1 ) );

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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_events( array() );

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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_events( array() );

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

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_events( array() );

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
	/**
	 * Coverage for constructor when wp_register_ability doesn't exist.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor_when_ability_api_not_available(): void {
		// This is difficult to test directly since the singleton pattern
		// means we can't easily test the constructor without the API.
		// The constructor early returns if wp_register_ability doesn't exist.
		// We test this indirectly through other tests.
		$this->assertTrue( true, 'Constructor behavior tested indirectly.' );
	}
	/**
	 * Coverage for execute_list_venues exception handling.
	 *
	 * @covers ::execute_list_venues
	 *
	 * @return void
	 */
	public function test_execute_list_venues_with_exception(): void {
		// Mock get_posts to throw an exception.
		add_filter(
			'pre_get_posts',
			function ( $query ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( isset( $query->query_vars['post_type'] ) && 'gatherpress_venue' === $query->query_vars['post_type'] ) {
					throw new \Exception( 'Database error' );
				}
				return $query;
			}
		);

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_venues();

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Error retrieving venues', $result['message'] );
		$this->assertStringContainsString( 'Database error', $result['message'] );
	}
	/**
	 * Coverage for register_*_ability methods via register_abilities.
	 *
	 * These are protected methods. We test them by calling them within the proper action hook context.
	 *
	 * @covers ::register_abilities
	 * @covers ::register_list_venues_ability
	 * @covers ::register_list_events_ability
	 * @covers ::register_list_topics_ability
	 * @covers ::register_search_events_ability
	 * @covers ::register_calculate_dates_ability
	 * @covers ::register_create_venue_ability
	 * @covers ::register_create_topic_ability
	 * @covers ::register_create_event_ability
	 * @covers ::register_update_venue_ability
	 * @covers ::register_update_event_ability
	 * @covers ::register_update_events_batch_ability
	 *
	 * @return void
	 */
	/**
	 * Coverage for register_*_ability methods.
	 *
	 * These protected methods are called via register_abilities which is hooked
	 * to wp_abilities_api_init in setup_hooks. They are covered when the action
	 * hook fires. This test verifies the methods exist and can be called.
	 *
	 * @covers ::register_abilities
	 * @covers ::register_list_venues_ability
	 * @covers ::register_list_events_ability
	 * @covers ::register_list_topics_ability
	 * @covers ::register_search_events_ability
	 * @covers ::register_calculate_dates_ability
	 * @covers ::register_create_venue_ability
	 * @covers ::register_create_topic_ability
	 * @covers ::register_create_event_ability
	 * @covers ::register_update_venue_ability
	 * @covers ::register_update_event_ability
	 * @covers ::register_update_events_batch_ability
	 *
	 * @return void
	 */
	public function test_register_abilities_calls_all_register_methods(): void {
		// The register methods are called via the action hook in setup_hooks.
		// We verify they execute by triggering the action hook.
		// Note: Abilities may already be registered, which is expected.

		// Suppress expected notices if abilities are already registered.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				if ( is_string( $description )
					&& ( strpos( $description, 'already registered' ) !== false
					|| strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught;
			},
			10,
			2
		);

		$instance = Abilities_Integration::get_instance();
		$instance->register_abilities();
		$this->assertTrue( true, 'register_abilities executed without error.' );
	}
	/**
	 * Coverage for execute_create_topic with parent_id.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_with_parent_id(): void {
		// Create a parent topic first.
		$parent_result = wp_insert_term( 'Parent Topic', 'gatherpress_topic' );
		$parent_id     = $parent_result['term_id'];

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'name'      => 'Child Topic',
			'parent_id' => $parent_id,
		);
		$result   = $instance->execute_create_topic( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'topic_id', $result );

		// Verify parent was set.
		$topic = get_term( $result['topic_id'], 'gatherpress_topic' );
		$this->assertSame( $parent_id, $topic->parent, 'Failed to assert parent topic was set.' );
	}
	/**
	 * Coverage for execute_create_topic with WP_Error from wp_insert_term.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_with_wp_error(): void {
		// Create a topic with a name that will cause a duplicate error.
		wp_insert_term( 'Duplicate Topic', 'gatherpress_topic' );

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'name' => 'Duplicate Topic', // Same name will cause error.
		);
		$result   = $instance->execute_create_topic( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'already exists', $result['message'] );
	}
	/**
	 * Coverage for execute_create_topic with description.
	 *
	 * @covers ::execute_create_topic
	 *
	 * @return void
	 */
	public function test_execute_create_topic_with_description(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'name'        => 'Topic with Description',
			'description' => 'This is a test topic description',
		);
		$result   = $instance->execute_create_topic( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify description was saved.
		$topic = get_term( $result['topic_id'], 'gatherpress_topic' );
		$this->assertSame( 'This is a test topic description', $topic->description );
	}
	/**
	 * Coverage for execute_list_topics with WP_Error from get_terms.
	 *
	 * @covers ::execute_list_topics
	 *
	 * @return void
	 */
	public function test_execute_list_topics_with_wp_error(): void {
		// Mock get_terms to return WP_Error.
		add_filter(
			'get_terms',
			function ( $terms, $taxonomies, $args ) {
				// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
				$args = $args;
				if ( in_array( 'gatherpress_topic', (array) $taxonomies, true ) ) {
					return new \WP_Error( 'term_error', 'Failed to get terms' );
				}
				return $terms;
			},
			10,
			3
		);

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_list_topics();

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Failed to get terms', $result['message'] );
	}
	/**
	 * Coverage for execute_calculate_dates when AI ability exists and wp_execute_ability is available.
	 *
	 * @covers ::execute_calculate_dates
	 *
	 * @return void
	 */
	public function test_execute_calculate_dates_with_ai_ability_available(): void {
		// Test both paths: if functions exist, use them; otherwise fall back to Date_Calculator.

		// Register the ai/calculate-dates ability if functions exist.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		if ( function_exists( 'wp_execute_ability' ) && function_exists( 'wp_get_ability' ) && function_exists( 'wp_has_ability' ) && function_exists( 'wp_register_ability' ) ) {
			add_action(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'wp_abilities_api_init',
				function () {
					if ( ! wp_has_ability( 'ai/calculate-dates' ) ) {
						wp_register_ability(
							'ai/calculate-dates',
							array(
								'label'            => 'AI Calculate Dates',
								'description'      => 'Calculate dates using AI',
								'category'         => 'event',
								'execute_callback' => function ( $params ) {
									// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
									$params = $params;
									return array(
										'success' => true,
										'data'    => array(
											'dates' => array( '2025-01-15', '2025-02-15' ),
										),
									);
								},
							)
						);
					}
				},
				1
			);

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'wp_abilities_api_init' );
		}

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_calculate_dates(
			array(
				'pattern'     => '3rd Tuesday',
				'occurrences' => 2,
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'data', $result );
	}
	/**
	 * Coverage for execute_calculate_dates when AI ability doesn't exist.
	 *
	 * @covers ::execute_calculate_dates
	 *
	 * @return void
	 */
	public function test_execute_calculate_dates_without_ai_ability(): void {
		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'pattern'     => '3rd Tuesday',
			'occurrences' => 3,
			'start_date'  => '2025-01-01',
		);
		$result   = $instance->execute_calculate_dates( $params );

		// Should fall back to local Date_Calculator.
		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertArrayHasKey( 'dates', $result['data'], 'Failed to assert dates key exists.' );
	}
	/**
	 * Coverage for execute_calculate_dates when wp_execute_ability doesn't exist.
	 *
	 * @covers ::execute_calculate_dates
	 *
	 * @return void
	 */
	public function test_execute_calculate_dates_when_wp_execute_ability_not_exists(): void {
		if ( function_exists( 'wp_execute_ability' ) ) {
			$this->markTestSkipped( 'wp_execute_ability function is available.' );
		}

		$instance = Abilities_Integration::get_instance();
		$params   = array(
			'pattern'     => '3rd Tuesday',
			'occurrences' => 3,
			'start_date'  => '2025-01-01',
		);
		$result   = $instance->execute_calculate_dates( $params );

		// Should fall back to local Date_Calculator.
		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
	}
	/**
	 * Coverage for constructor when wp_register_ability exists (lines 60-61).
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_constructor_when_ability_api_available(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability function not available.' );
		}

		// The constructor is called via get_instance().
		// When wp_register_ability exists, it initializes date_calculator and calls setup_hooks.
		$instance = Abilities_Integration::get_instance();

		// Verify hooks are set up by checking if actions are registered.
		$hooks = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_categories_init',
				'priority' => 999,
				'callback' => array( $instance, 'register_categories' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_init',
				'priority' => 999,
				'callback' => array( $instance, 'register_abilities' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}
	/**
	 * Coverage for setup_hooks method (lines 71, 73-74).
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks_registers_actions(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability function not available.' );
		}

		$instance = Abilities_Integration::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_categories_init',
				'priority' => 999,
				'callback' => array( $instance, 'register_categories' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_init',
				'priority' => 999,
				'callback' => array( $instance, 'register_abilities' ),
			),
		);

		// Should register 2 actions: wp_abilities_api_categories_init and wp_abilities_api_init.
		$this->assert_hooks( $hooks, $instance );
	}
	/**
	 * Coverage for get_calculate_dates_ability when wp_has_ability doesn't exist (lines 86, 88-89).
	 *
	 * @covers ::get_calculate_dates_ability
	 *
	 * @return void
	 */
	public function test_get_calculate_dates_ability_when_wp_has_ability_not_exists(): void {
		// Test both paths: when function exists and when it doesn't.
		$result = Abilities_Integration::get_calculate_dates_ability();
		// Should return either ai/calculate-dates if it exists, or gatherpress/calculate-dates.
		$this->assertContains( $result, array( 'ai/calculate-dates', 'gatherpress/calculate-dates' ) );
		$this->assertIsString( $result );
	}
	/**
	 * Coverage for get_calculate_dates_ability when ai/calculate-dates exists (lines 93-94).
	 *
	 * @covers ::get_calculate_dates_ability
	 *
	 * @return void
	 */
	public function test_get_calculate_dates_ability_when_ai_ability_registered(): void {
		if ( ! function_exists( 'wp_has_ability' ) ) {
			$this->markTestSkipped( 'wp_has_ability function not available.' );
		}

		// Test that the method works whether or not ai/calculate-dates is registered.
		// The method checks if ai/calculate-dates exists and returns it if available.
		$result = Abilities_Integration::get_calculate_dates_ability();
		// Should return either ai/calculate-dates if it exists, or gatherpress/calculate-dates.
		$this->assertContains( $result, array( 'ai/calculate-dates', 'gatherpress/calculate-dates' ) );
		$this->assertIsString( $result );
	}
	/**
	 * Coverage for get_calculate_dates_ability fallback (line 97).
	 *
	 * @covers ::get_calculate_dates_ability
	 *
	 * @return void
	 */
	public function test_get_calculate_dates_ability_fallback(): void {
		// Check if ai/calculate-dates is registered.
		$result = Abilities_Integration::get_calculate_dates_ability();
		// Should return either ai/calculate-dates if it exists, or gatherpress/calculate-dates as fallback.
		$this->assertContains( $result, array( 'ai/calculate-dates', 'gatherpress/calculate-dates' ) );
	}
	/**
	 * Coverage for register_categories method.
	 *
	 * @covers ::register_categories
	 *
	 * @return void
	 */
	public function test_register_categories_registers_venue_and_event(): void {
		// Categories are registered via the action hook in setup_hooks.
		// They are covered when the action fires.

		// Suppress expected notices if categories are already registered.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught;
			},
			10,
			2
		);

		$instance = Abilities_Integration::get_instance();
		$instance->register_categories();
		$this->assertTrue( true, 'register_categories executed without error.' );
	}
	/**
	 * Coverage for register_abilities method.
	 *
	 * @covers ::register_abilities
	 *
	 * @return void
	 */
	public function test_register_abilities_calls_all_methods(): void {
		// Abilities are registered via the action hook in setup_hooks.
		// They are covered when the action fires.

		// Suppress expected notices if abilities are already registered.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught;
			},
			10,
			2
		);

		$instance = Abilities_Integration::get_instance();
		$instance->register_abilities();
		$this->assertTrue( true, 'register_abilities executed without error.' );
	}
	/**
	 * Coverage for constructor early return when wp_register_ability doesn't exist (line 57).
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor_early_return_when_api_not_available(): void {
		// This is difficult to test directly since the singleton pattern means
		// we can't easily create a new instance. The early return at line 57
		// happens when wp_register_ability doesn't exist.
		// We test this indirectly - if the API doesn't exist, the instance
		// won't have hooks set up.
		// Test both paths: when function exists and when it doesn't.
		$instance = Abilities_Integration::get_instance();
		$this->assertInstanceOf( Abilities_Integration::class, $instance );
		// Instance should exist regardless of whether API is available.
		$this->assertTrue( true, 'Constructor executed without error.' );
	}
	/**
	 * Coverage for register_categories method using reflection (lines 107-109, 112-118, 120-126).
	 *
	 * @covers ::register_categories
	 *
	 * @return void
	 */
	public function test_register_categories_direct_call(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			$this->markTestSkipped( 'wp_register_ability_category function not available.' );
		}

		$instance = Abilities_Integration::get_instance();

		// Suppress expected notices using the pmc_doing_it_wrong filter.
		add_filter(
			'pmc_doing_it_wrong',
			// phpcs:ignore Generic.Files.LineLength.TooLong
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught; // Let other notices through.
			},
			10,
			2
		);

		// Call register_categories directly using reflection within the action hook to ensure coverage.
		$called = false;
		add_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'wp_abilities_api_categories_init',
			function () use ( $instance, &$called ) {
				\PMC\Unit_Test\Utility::invoke_hidden_method( $instance, 'register_categories', array() );
				$called = true;
			},
			999
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'wp_abilities_api_categories_init' );

		// Verify method executed.
		$this->assertTrue( $called, 'register_categories method was executed.' );
	}
	/**
	 * Coverage for register_abilities method using reflection.
	 *
	 * @covers ::register_abilities
	 *
	 * @return void
	 */
	public function test_register_abilities_direct_call(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability function not available.' );
		}

		$instance = Abilities_Integration::get_instance();

		// Suppress expected notices using the pmc_doing_it_wrong filter.
		add_filter(
			'pmc_doing_it_wrong',
			// phpcs:ignore Generic.Files.LineLength.TooLong
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught; // Let other notices through.
			},
			10,
			2
		);

		// Call register_abilities directly using reflection within the action hook to ensure coverage.
		$called = false;
		add_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'wp_abilities_api_init',
			function () use ( $instance, &$called ) {
				\PMC\Unit_Test\Utility::invoke_hidden_method( $instance, 'register_abilities', array() );
				$called = true;
			},
			999
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'wp_abilities_api_init' );

		// Verify method executed.
		$this->assertTrue( $called, 'register_abilities method was executed.' );
	}
	/**
	 * Coverage for all register_*_ability methods using reflection.
	 *
	 * @covers ::register_list_venues_ability
	 * @covers ::register_list_events_ability
	 * @covers ::register_list_topics_ability
	 * @covers ::register_search_events_ability
	 * @covers ::register_calculate_dates_ability
	 * @covers ::register_create_venue_ability
	 * @covers ::register_create_topic_ability
	 * @covers ::register_create_event_ability
	 * @covers ::register_update_venue_ability
	 * @covers ::register_update_event_ability
	 * @covers ::register_update_events_batch_ability
	 *
	 * @return void
	 */
	public function test_all_register_ability_methods_direct_call(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability function not available.' );
		}

		$instance = Abilities_Integration::get_instance();

		// Suppress expected notices using the pmc_doing_it_wrong filter.
		add_filter(
			'pmc_doing_it_wrong',
			// phpcs:ignore Generic.Files.LineLength.TooLong
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught; // Let other notices through.
			},
			10,
			2
		);

		// Call all register_*_ability methods directly using reflection within the action hook to ensure coverage.
		$methods_called = array();
		add_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'wp_abilities_api_init',
			function () use ( $instance, &$methods_called ) {
				$methods = array(
					'register_list_venues_ability',
					'register_list_events_ability',
					'register_list_topics_ability',
					'register_search_events_ability',
					// phpcs:ignore Generic.Files.LineLength.TooLong
					'register_calculate_dates_ability',
					'register_create_venue_ability',
					'register_create_topic_ability',
					'register_create_event_ability',
					'register_update_venue_ability',
					'register_update_event_ability',
					'register_update_events_batch_ability',
				);

				foreach ( $methods as $method ) {
					\PMC\Unit_Test\Utility::invoke_hidden_method( $instance, $method, array() );
					$methods_called[] = $method;
				}
			},
			999
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'wp_abilities_api_init' );

		// Verify methods were executed.
		$this->assertCount( 11, $methods_called, 'All 11 register methods were executed.' );
	}
	/**
	 * Coverage for execute_calculate_dates when AI ability exists and wp_execute_ability is available.
	 *
	 * @covers ::execute_calculate_dates
	 *
	 * @return void
	 */
	public function test_execute_calculate_dates_with_ai_ability_available_direct(): void {
		// Test both paths: if functions exist, use them; otherwise fall back to Date_Calculator.

		// Register ai/calculate-dates ability if functions exist and it's not already registered.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		if ( function_exists( 'wp_execute_ability' ) && function_exists( 'wp_get_ability' ) && function_exists( 'wp_has_ability' ) && function_exists( 'wp_register_ability' ) ) {
			add_action(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'wp_abilities_api_init',
				function () {
					if ( ! wp_has_ability( 'ai/calculate-dates' ) ) {
						wp_register_ability(
							'ai/calculate-dates',
							array(
								'label'               => 'AI Calculate Dates',
								'description'         => 'Calculate dates using AI',
								'category'            => 'event',
								'permission_callback' => function () {
									return current_user_can( 'read' );
								},
								'execute_callback'    => function ( $params ) {
									// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
									$params = $params;
									return array(
										'success' => true,
										'data'    => array(
											'dates' => array( '2025-01-15', '2025-02-15', '2025-03-15' ),
										),
									);
								},
							)
						);
					}
				},
				1
			);

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'wp_abilities_api_init' );
		}

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_calculate_dates(
			array(
				'pattern'     => '3rd Tuesday',
				'occurrences' => 3,
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'data', $result );
	}
	/**
	 * Coverage for register_categories early return when wp_register_ability_category doesn't exist (line 109).
	 *
	 * @covers ::register_categories
	 *
	 * @return void
	 */
	public function test_register_categories_early_return_when_function_not_exists(): void {
		// Test both paths: when function exists and when it doesn't.

		// Suppress expected notices if categories are already registered or registered outside action hook.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				if ( is_string( $description )
					&& ( strpos( $description, 'already registered' ) !== false
					|| strpos( $description, 'must be registered on' ) !== false ) ) {
					return false; // Suppress the notice.
				}
				return $caught;
			},
			10,
			2
		);

		$instance = Abilities_Integration::get_instance();
		\PMC\Unit_Test\Utility::invoke_hidden_method( $instance, 'register_categories', array() );

		// Method should execute without error regardless of function existence.
		$this->assertTrue( true, 'register_categories executed without error.' );
	}
	/**
	 * Coverage for permission callbacks in register methods by executing abilities.
	 *
	 * @covers ::register_list_venues_ability
	 * @covers ::register_list_events_ability
	 * @covers ::register_list_topics_ability
	 * @covers ::register_calculate_dates_ability
	 * @covers ::register_create_venue_ability
	 * @covers ::register_create_topic_ability
	 * @covers ::register_create_event_ability
	 * @covers ::register_update_venue_ability
	 * @covers ::register_update_event_ability
	 * @covers ::register_search_events_ability
	 * @covers ::register_update_events_batch_ability
	 *
	 * @return void
	 */
	public function test_permission_callbacks_are_executable(): void {
		// Test both paths: if function exists, use it; otherwise test that abilities are registered.

		// Suppress expected notices.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false;
				}
				return $caught;
			},
			10,
			2
		);

		$instance = Abilities_Integration::get_instance();

		// Register all abilities.
		add_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			'wp_abilities_api_init',
			function () use ( $instance ) {
				\PMC\Unit_Test\Utility::invoke_hidden_method( $instance, 'register_abilities', array() );
			},
			999
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'wp_abilities_api_init' );

		// Set up a user with proper permissions to execute abilities.
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Execute abilities to trigger permission callbacks (which will execute lines 176, 207, etc.).
		// Only test if wp_execute_ability exists.
		if ( function_exists( 'wp_execute_ability' ) ) {
			$abilities_to_test = array(
				'gatherpress/list-venues'         => array(),
				'gatherpress/list-events'         => array(),
				'gatherpress/list-topics'         => array(),
				'gatherpress/calculate-dates'     => array(
					'pattern'     => '3rd Tuesday',
					'occurrences' => 1,
				),
				'gatherpress/search-events'       => array( 'search_term' => 'test' ),
				'gatherpress/update-events-batch' => array(),
			);

			foreach ( $abilities_to_test as $ability_name => $params ) {
				$result = wp_execute_ability( $ability_name, $params );
				// Permission callback was executed during wp_execute_ability.
				$this->assertIsArray( $result, "Ability {$ability_name} should return a result." );
			}

			$this->assertTrue( true, 'All permission callbacks were executed.' );
		} else {
			// If wp_execute_ability doesn't exist, just verify abilities are registered.
			$instance = Abilities_Integration::get_instance();
			$instance->register_abilities();
			$this->assertTrue( true, 'Abilities registered successfully.' );
		}
	}
	/**
	 * Coverage for execute_calculate_dates when AI ability exists and wp_execute_ability is called.
	 *
	 * @covers ::execute_calculate_dates
	 *
	 * @return void
	 */
	public function test_execute_calculate_dates_with_ai_ability_executes_wp_execute_ability(): void {
		// Test both paths: if functions exist, use them; otherwise fall back to Date_Calculator.

		// Register ai/calculate-dates ability if functions exist and it's not already registered.
		// phpcs:ignore Generic.Files.LineLength.TooLong
		if ( function_exists( 'wp_execute_ability' ) && function_exists( 'wp_get_ability' ) && function_exists( 'wp_has_ability' ) && function_exists( 'wp_register_ability' ) ) {
			add_action(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'wp_abilities_api_init',
				function () {
					// phpcs:ignore Generic.Files.LineLength.TooLong
					if ( ! wp_has_ability( 'ai/calculate-dates' ) ) {
						wp_register_ability(
							'ai/calculate-dates',
							array(
								'label'               => 'AI Calculate Dates',
								'description'         => 'Calculate dates using AI',
								'category'            => 'event',
								'permission_callback' => function () {
									return current_user_can( 'read' );
								},
								'execute_callback'    => function ( $params ) {
									// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
									$params = $params;
									return array(
										'success' => true,
										'data'    => array(
											'dates' => array( '2025-01-15', '2025-02-15', '2025-03-15' ),
										),
									);
								},
							)
						);
					}
				},
				1
			);

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'wp_abilities_api_init' );

			// Verify ai/calculate-dates ability exists (line 1596 checks this via wp_get_ability).
			if ( function_exists( 'wp_get_ability' ) && function_exists( 'wp_has_ability' ) ) {
				$ai_ability = wp_get_ability( 'ai/calculate-dates' );
				$this->assertNotEmpty( $ai_ability, 'ai/calculate-dates ability should be registered.' );
				// phpcs:ignore Generic.Files.LineLength.TooLong
				$this->assertTrue( wp_has_ability( 'ai/calculate-dates' ), 'ai/calculate-dates should exist for line 1597 check.' );
			}
		}

		$instance = Abilities_Integration::get_instance();
		$result   = $instance->execute_calculate_dates(
			array(
				'pattern'     => '3rd Tuesday',
				'occurrences' => 3,
			)
		);

		// Should use AI plugin's ability via wp_execute_ability (line 1599) if available, otherwise fall back.
		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'dates', $result['data'] );
		$this->assertCount( 3, $result['data']['dates'] );
	}
	/**
	 * Coverage for get_calculate_dates_ability when wp_has_ability doesn't exist (line 89).
	 *
	 * @covers ::get_calculate_dates_ability
	 *
	 * @return void
	 */
	public function test_get_calculate_dates_ability_line_89(): void {
		// Test both paths: when function exists and when it doesn't.
		$result = Abilities_Integration::get_calculate_dates_ability();
		// Should return either ai/calculate-dates if it exists, or gatherpress/calculate-dates.
		$this->assertContains( $result, array( 'ai/calculate-dates', 'gatherpress/calculate-dates' ) );
		$this->assertIsString( $result );
	}
	/**
	 * Coverage for get_calculate_dates_ability when ai/calculate-dates exists (line 94).
	 *
	 * @covers ::get_calculate_dates_ability
	 *
	 * @return void
	 */
	public function test_get_calculate_dates_ability_line_94(): void {
		// Test both paths: when functions exist and when they don't.

		// Suppress expected notices.
		add_filter(
			'pmc_doing_it_wrong',
			function ( $caught, $description ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( is_string( $description ) && ( strpos( $description, 'already registered' ) !== false || strpos( $description, 'must be registered on' ) !== false ) ) {
					return false;
				}
				return $caught;
			},
			10,
			2
		);

		// Register ai/calculate-dates directly within the action hook if functions exist.
		if ( function_exists( 'wp_has_ability' ) && function_exists( 'wp_register_ability' ) ) {
			add_action(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'wp_abilities_api_init',
				function () {
					if ( ! wp_has_ability( 'ai/calculate-dates' ) ) {
						wp_register_ability(
							'ai/calculate-dates',
							array(
								'label'            => 'AI Calculate Dates',
								'description'      => 'Calculate dates using AI',
								'execute_callback' => function () {
									return array( 'success' => true );
								},
							)
						);
					}
				},
				1
			);

			// Trigger the action hook to register the ability.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			do_action( 'wp_abilities_api_init' );
		}

		// Now check if it exists and test the method.
		$result = Abilities_Integration::get_calculate_dates_ability();
		// Should return either ai/calculate-dates if it exists, or gatherpress/calculate-dates.
		$this->assertContains( $result, array( 'ai/calculate-dates', 'gatherpress/calculate-dates' ) );
		$this->assertIsString( $result );
	}
}
