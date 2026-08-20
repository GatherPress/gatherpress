<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Event.
 *
 * @package GatherPress\Core\Event
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Event;

use DateTime;
use DateTimeZone;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use ReflectionClass;
use WP_Post;

/**
 * Class Test_Event.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Event
 */
class Test_Event extends Base {

	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$post  = $this->mock->post()->get();
		$event = new Event( $post->ID );

		$this->assertNull( Utility::get_hidden_property( $event, 'event' ) );
		$this->assertNull( Utility::get_hidden_property( $event, 'rsvp' ) );

		$post  = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$event = new Event( $post->ID );

		$this->assertInstanceOf( WP_Post::class, Utility::get_hidden_property( $event, 'event' ) );
		$this->assertInstanceOf( Rsvp::class, Utility::get_hidden_property( $event, 'rsvp' ) );
	}

	/**
	 * Asserts that the prior fully-qualified class name `GatherPress\Core\Event` continues
	 * to resolve to the current class `GatherPress\Core\Event\Event` via the alias map in
	 * `includes/core/register-class-aliases.php`. Removing the alias entry would silently
	 * break external consumers (other plugins, theme code) that reference the prior FQN —
	 * this test fails loudly first.
	 *
	 * @return void
	 */
	public function test_prior_fqn_resolves_to_current_class(): void {
		$prior_fqn = 'GatherPress\\Core\\Event';

		$this->assertTrue(
			class_exists( $prior_fqn ),
			'The prior fully-qualified class name should resolve via the alias map.'
		);

		$reflection = new ReflectionClass( $prior_fqn );
		$this->assertSame(
			Event::class,
			$reflection->getName(),
			'The prior FQN should resolve to the current Event class.'
		);

		// Read a class constant through the prior FQN to confirm runtime usability.
		$this->assertSame( Event::POST_TYPE, constant( $prior_fqn . '::POST_TYPE' ) );
	}

	/**
	 * Data provider for get_display_datetime test.
	 *
	 * @return array
	 */
	public function data_get_display_datetime(): array {
		return array(
			array(
				'params'  => array(
					'datetime_start' => '2020-05-11 15:00:00',
					'datetime_end'   => '2020-05-11 17:00:00',
				),
				'expects' => 'Monday, May 11, 2020 3:00 PM to 5:00 PM GMT+0000',
			),
			array(
				'params'  => array(
					'datetime_start' => '2020-05-11 15:00:00',
					'datetime_end'   => '2020-05-11 17:00:00',
					'timezone'       => 'America/New_York',
				),
				'expects' => 'Monday, May 11, 2020 3:00 PM to 5:00 PM EDT',
			),
			array(
				'params'  => array(
					'datetime_start' => '2020-05-11 15:00:00',
					'datetime_end'   => '2020-05-12 17:00:00',
					'timezone'       => 'America/New_York',
				),
				'expects' => 'Monday, May 11, 2020 3:00 PM to Tuesday, May 12, 2020 5:00 PM EDT',
			),
			array(
				'params'  => array(
					'datetime_start' => '2020-05-11 15:00:00',
					'datetime_end'   => '2020-05-11 17:00:00',
					'timezone'       => 'America/New_York',
					'type'           => 'both',
					'start_format'   => 'F j, Y g:ia',
					'end_format'     => 'F j, Y g:ia',
					'separator'      => 'UNTIL',
				),
				'expects' => 'May 11, 2020 3:00pm UNTIL 5:00pm EDT',
			),
			array(
				'params'  => array(
					'datetime_start' => '2020-05-11 15:00:00',
					'datetime_end'   => '2020-05-11 17:00:00',
					'timezone'       => 'America/New_York',
					'type'           => 'start',
					'start_format'   => 'F j, Y',
				),
				'expects' => 'May 11, 2020 EDT',
			),
			array(
				'params'  => array(
					'datetime_start' => '2020-05-11 15:00:00',
					'datetime_end'   => '2020-05-12 17:00:00',
					'timezone'       => 'America/New_York',
					'type'           => 'end',
					'start_format'   => 'F j, Y g:ia',
					'end_format'     => 'F j, Y g:ia',
					'show_timezone'  => 'no',
				),
				'expects' => 'May 12, 2020 5:00pm',
			),
		);
	}

	/**
	 * Coverage for get_display_datetime method.
	 *
	 * @param array  $params   Parameters for datetimes.
	 * @param string $expects  Expected formatted output.
	 *
	 * @dataProvider data_get_display_datetime
	 *
	 * @covers ::get_display_datetime
	 * @covers ::get_time_end
	 * @covers ::save_datetimes
	 * @covers ::is_same_date
	 * @covers ::get_gmt_datetime
	 *
	 * @return void
	 */
	public function test_get_display_datetime( array $params, string $expects ): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'l, F j, Y',
				'time_format'   => 'g:i A',
				'show_timezone' => true,
			)
		);

		$post  = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event = new Event( $post->ID );

		if ( ! empty( $params ) ) {
			$output = $event->save_datetimes( $params );

			$this->assertTrue( $output, 'Failed to assert that datetimes saved.' );
			$this->assertSame(
				$params['datetime_start'],
				get_post_meta( $post->ID, 'gatherpress_datetime_start', true ),
				'Failed to assert that datetime start matches parameter.'
			);
			$this->assertSame(
				$params['datetime_end'],
				get_post_meta( $post->ID, 'gatherpress_datetime_end', true ),
				'Failed to assert that datetime end matches parameter.'
			);
			if ( ! empty( $params['timezone'] ) ) {
				$this->assertSame(
					$params['timezone'],
					get_post_meta( $post->ID, 'gatherpress_timezone', true ),
					'Failed to assert that timezone matches parameter.'
				);
			}
		}

		// For empty params test, ensure no datetime data exists.
		// This needs to be done right before the assertion because
		// previous tests may have set datetime values for this post ID.
		if ( empty( $params ) ) {
			global $wpdb;
			$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $table, array( 'post_id' => $post->ID ), array( '%d' ) );
			delete_transient( sprintf( Event::DATETIME_CACHE_KEY, $post->ID ) );
		}

		$this->assertSame(
			$expects,
			$event->get_display_datetime(
				$params['type'] ?? '',
				$params['start_format'] ?? '',
				$params['end_format'] ?? '',
				$params['separator'] ?? '',
				$params['show_timezone'] ?? ''
			),
			'Failed to assert display date times match.'
		);

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for save_datetimes method.
	 *
	 * @covers ::save_datetimes
	 *
	 * @return void
	 */
	public function test_save_datetimes(): void {
		$post   = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event  = new Event( $post->ID );
		$params = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-11 17:00:00',
			'timezone'       => 'America/New_York',
		);

		$this->assertTrue(
			$event->save_datetimes( $params ),
			'Failed to insert date times.'
		);

		$params = array(
			'datetime_start' => '2020-05-11 16:00:00',
			'datetime_end'   => '2020-05-11 18:00:00',
			'timezone'       => 'America/New_York',
		);

		$this->assertTrue(
			$event->save_datetimes( $params ),
			'Failed to update date times.'
		);

		$post->ID = 0;

		Utility::set_and_get_hidden_property( $event, 'event', $post );

		$this->assertFalse(
			$event->save_datetimes( $params ),
			'Failed to assert false due to post ID less than 1.'
		);
	}

	/**
	 * A post that is not an event has nothing to attach datetimes to, so the
	 * save reports failure and writes no meta.
	 *
	 * @since 0.36.0
	 * @covers ::save_datetimes
	 *
	 * @return void
	 */
	public function test_save_datetimes_returns_false_without_post(): void {
		$post_id = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;
		$event   = new Event( $post_id );

		$this->assertFalse(
			$event->save_datetimes(
				array(
					'datetime_start' => '2020-05-11 15:00:00',
					'datetime_end'   => '2020-05-11 17:00:00',
					'timezone'       => 'America/New_York',
				)
			),
			'Failed to assert false due to the post not resolving to an event.'
		);
		$this->assertSame(
			'',
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert no datetime meta was written for a non-event post.'
		);
	}

	/**
	 * Coverage for get_datetime method.
	 *
	 * @covers ::get_datetime
	 * @covers ::get_datetime_start
	 * @covers ::get_datetime_end
	 * @covers ::get_formatted_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime(): void {
		$event = new Event( 0 );

		$this->assertSame(
			array(
				'datetime_start'     => '',
				'datetime_start_gmt' => '',
				'datetime_end'       => '',
				'datetime_end_gmt'   => '',
				'timezone'           => '+00:00',
			),
			$event->get_datetime()
		);

		$post  = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event = new Event( $post->ID );

		// A new event is seeded with the editor's default rather than left
		// datetime-less, so it always lands in the events table (#2054). The
		// seed is decided at shutdown, so meta written after the insert wins
		// over it (#2116).
		Event_Setup::get_instance()->resolve_pending_datetimes();

		$seeded = $event->get_datetime();

		$this->assertNotEmpty(
			$seeded['datetime_start'],
			'Failed to assert that a new event is seeded with a start datetime.'
		);
		$this->assertSame(
			2 * HOUR_IN_SECONDS,
			strtotime( $seeded['datetime_end'] ) - strtotime( $seeded['datetime_start'] ),
			'Failed to assert that the seeded default runs for two hours.'
		);

		$params = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-12 17:00:00',
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		$this->assertSame(
			array(
				'datetime_start'     => '2020-05-11 15:00:00',
				'datetime_start_gmt' => '2020-05-11 19:00:00',
				'datetime_end'       => '2020-05-12 17:00:00',
				'datetime_end_gmt'   => '2020-05-12 21:00:00',
				'timezone'           => 'America/New_York',
			),
			$event->get_datetime()
		);

		$this->assertSame( 'Mon, May 11, 2020, 3:00 pm EDT', $event->get_datetime_start() );
		$this->assertSame( '2020-05-11', $event->get_datetime_start( 'Y-m-d' ) );
		$this->assertSame( 'Tue, May 12, 5:00pm EDT', $event->get_datetime_end() );
		$this->assertSame( '2020-05-12', $event->get_datetime_end( 'Y-m-d' ) );

		$this->assertSame(
			'Mon, May 11, 3:00pm EDT',
			Utility::invoke_hidden_method( $event, 'get_formatted_datetime', array() )
		);
		$this->assertSame(
			'Tue, May 12, 5:00pm EDT',
			Utility::invoke_hidden_method( $event, 'get_formatted_datetime', array( 'D, F j, g:ia T', 'end' ) )
		);
		$this->assertSame(
			'Tue, May 12, 9:00pm GMT+0000',
			Utility::invoke_hidden_method( $event, 'get_formatted_datetime', array( 'D, F j, g:ia T', 'end', false ) )
		);
	}

	/**
	 * Coverage for get_datetime method with partially missing meta.
	 *
	 * Events created before #2054 seeded a default datetime, and events built
	 * outside the editor, can be missing some of the five datetime meta keys.
	 * Each absent key is skipped and keeps its empty default rather than
	 * poisoning the whole array.
	 *
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime_skips_missing_meta(): void {
		$post  = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$event = new Event( $post->ID );

		$event->save_datetimes(
			array(
				'datetime_start' => '2020-05-11 15:00:00',
				'datetime_end'   => '2020-05-12 17:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		delete_post_meta( $post->ID, 'gatherpress_datetime_end' );
		delete_post_meta( $post->ID, 'gatherpress_datetime_end_gmt' );

		// A fresh instance so the read is not served from datetime_cache.
		$datetime = ( new Event( $post->ID ) )->get_datetime();

		$this->assertSame(
			'2020-05-11 15:00:00',
			$datetime['datetime_start'],
			'Failed to assert that a present meta key still populates.'
		);
		$this->assertSame(
			'America/New_York',
			$datetime['timezone'],
			'Failed to assert that timezone survives a missing datetime key.'
		);
		$this->assertSame(
			'',
			$datetime['datetime_end'],
			'Failed to assert that a missing meta key keeps its empty default.'
		);
		$this->assertSame(
			'',
			$datetime['datetime_end_gmt'],
			'Failed to assert that a missing gmt meta key keeps its empty default.'
		);
	}

	/**
	 * Coverage for get_gmt_datetime method.
	 *
	 * @covers ::get_gmt_datetime
	 *
	 * @return void
	 */
	public function test_get_gmt_datetime(): void {
		$post     = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event    = new Event( $post->ID );
		$timezone = new DateTimeZone( 'America/New_York' );

		$this->assertEmpty(
			Utility::invoke_hidden_method( $event, 'get_gmt_datetime', array( 'unit-test', $timezone ) ),
			'Failed to assert that gmt datetime is empty.'
		);

		$this->assertEmpty(
			Utility::invoke_hidden_method( $event, 'get_gmt_datetime', array( '', $timezone ) ),
			'Failed to assert that gmt datetime is empty.'
		);
	}

	/**
	 * Coverage for get_venue_information method.
	 *
	 * @covers ::get_venue_information
	 *
	 * @return void
	 */
	public function test_get_venue_information(): void {
		$venue    = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Unit Test Venue',
				'post_name'  => 'unit-test-venue',
			)
		)->get();
		$event_id = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get()->ID;
		$event    = new Event( $event_id );

		update_post_meta( $venue->ID, 'gatherpress_address', '123 Main Street, Montclair, NJ 07042' );
		update_post_meta( $venue->ID, 'gatherpress_phone', '(123) 123-1234' );
		update_post_meta( $venue->ID, 'gatherpress_website', 'https://gatherpress.org/' );
		wp_set_post_terms( $event_id, '_unit-test-venue', Venue::TAXONOMY );

		$response = $event->get_venue_information();

		$this->assertSame(
			'Unit Test Venue',
			$response['name'],
			'Failed to assert that name matches.'
		);
		$this->assertSame(
			'123 Main Street, Montclair, NJ 07042',
			$response['address'],
			'Failed to assert that full address matches.'
		);
		$this->assertSame(
			'(123) 123-1234',
			$response['phone'],
			'Failed to assert that phone number matches.'
		);
		$this->assertSame(
			'https://gatherpress.org/',
			$response['website'],
			'Failed to assert that website matches.'
		);
		$this->assertSame(
			get_the_permalink( $venue->ID ),
			$response['permalink'],
			'Failed to assert that permalink matches.'
		);

		wp_set_post_terms( $event_id, 'Online event', Venue::TAXONOMY );

		$response = $event->get_venue_information();

		$this->assertSame(
			'Online event',
			$response['name'],
			'Failed to assert that name matches.'
		);

		$this->assertEmpty(
			$response['address'],
			'Failed to assert that full address is empty.'
		);

		$this->assertEmpty(
			$response['phone'],
			'Failed to assert that phone number is empty.'
		);

		$this->assertEmpty(
			$response['website'],
			'Failed to assert that website is empty.'
		);
	}

	/**
	 * Events with no venue term attached return the empty default shape.
	 *
	 * `get_the_terms()` returns `false` when no terms are assigned, which
	 * casts to `[ false ]`. The foreach must skip the non-WP_Term entry and
	 * leave `name` / `address` / etc. at their defaults.
	 *
	 * @covers ::get_venue_information
	 *
	 * @return void
	 */
	public function test_get_venue_information_returns_empty_shape_when_no_terms_attached(): void {
		$event_id = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get()->ID;

		$response = ( new Event( $event_id ) )->get_venue_information();

		$this->assertSame( '', $response['name'], 'Expected empty venue name when no term is attached.' );
		$this->assertSame( '', $response['address'], 'Expected empty address when no term is attached.' );
		$this->assertSame( '', $response['phone'], 'Expected empty phone when no term is attached.' );
		$this->assertSame( '', $response['website'], 'Expected empty website when no term is attached.' );
		$this->assertSame( '', $response['permalink'], 'Expected empty permalink when no term is attached.' );
	}

	/**
	 * Hybrid events with both a physical venue and the `online-event` sentinel
	 * surface the physical venue's name and address rather than the sentinel.
	 *
	 * @covers ::get_venue_information
	 *
	 * @return void
	 */
	public function test_get_venue_information_prefers_venue_term_over_sentinel(): void {
		$venue    = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Hybrid Venue',
				'post_name'  => 'hybrid-venue',
			)
		)->get();
		$event_id = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get()->ID;
		$event    = new Event( $event_id );

		update_post_meta( $venue->ID, 'gatherpress_address', '500 Hybrid Way' );

		// Attach BOTH the venue term and the online-event sentinel.
		wp_set_post_terms( $event_id, array( '_hybrid-venue', 'online-event' ), Venue::TAXONOMY );

		$response = $event->get_venue_information();

		$this->assertSame(
			'Hybrid Venue',
			$response['name'],
			'Hybrid event should surface the physical venue name, not the sentinel.'
		);
		$this->assertSame(
			'500 Hybrid Way',
			$response['address'],
			'Hybrid event should surface the physical venue address.'
		);
	}

	/**
	 * A post that is not an event has no venue to report, and must not fall
	 * through to the venue of whatever post is globally queried.
	 *
	 * @since 0.36.0
	 * @covers ::get_venue_information
	 *
	 * @return void
	 */
	public function test_get_venue_information_returns_empty_shape_without_post(): void {
		$post_id  = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;
		$venue    = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Queried Venue',
				'post_name'  => 'queried-venue',
			)
		)->get();
		$event_id = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get()->ID;

		update_post_meta( $venue->ID, 'gatherpress_address', '900 Queried Way' );
		wp_set_post_terms( $event_id, array( '_queried-venue' ), Venue::TAXONOMY );

		// Query the venue-bearing event so any fall-through would surface its venue.
		$this->go_to( get_permalink( $event_id ) );

		$response = ( new Event( $post_id ) )->get_venue_information();

		$this->assertSame(
			array(
				'address'   => '',
				'name'      => '',
				'permalink' => '',
				'phone'     => '',
				'website'   => '',
			),
			$response,
			'Failed to assert a non-event post reports the empty venue shape.'
		);
	}

	/**
	 * Coverage for get_calendar_links method.
	 *
	 * @covers ::get_calendar_links
	 * @covers ::get_calendar_description
	 *
	 * @return void
	 */
	public function test_get_calendar_links(): void {
		$post   = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
				'post_date'    => '2020-05-11 00:00:00',
			)
		)->get();
		$venue  = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Unit Test Venue',
				'post_name'  => 'unit-test-venue',
			)
		)->get();
		$event  = new Event( $post->ID );
		$params = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-11 17:00:00',
		);

		update_post_meta( $venue->ID, 'gatherpress_address', '123 Main Street, Montclair, NJ 07042' );
		update_post_meta( $venue->ID, 'gatherpress_phone', '(123) 123-1234' );
		update_post_meta( $venue->ID, 'gatherpress_website', 'https://gatherpress.org/' );
		wp_set_post_terms( $post->ID, '_unit-test-venue', Venue::TAXONOMY );

		$event->save_datetimes( $params );

		$output = $event->get_calendar_links();

		$slug                      = get_post_field( 'post_name', $post->ID );
		$google_query              = sprintf( '/?gatherpress_event=%s&gatherpress_calendar=google-calendar', $slug );
		$yahoo_query               = sprintf( '/?gatherpress_event=%s&gatherpress_calendar=yahoo-calendar', $slug );
		$ical_query                = sprintf( '/?gatherpress_event=%s&gatherpress_calendar=ical', $slug );
		$outlook_query             = sprintf( '/?gatherpress_event=%s&gatherpress_calendar=outlook', $slug );
		$expected_google_link      = home_url( $google_query );
		$expected_yahoo_link       = home_url( $yahoo_query );
		$expected_ical_download    = home_url( $ical_query );
		$expected_outlook_download = home_url( $outlook_query );
		$expects                   = array(
			'google'  => array(
				'name' => 'Google Calendar',
				'link' => $expected_google_link,
			),
			'ical'    => array(
				'name'     => 'iCal',
				'download' => $expected_ical_download,
			),
			'outlook' => array(
				'name'     => 'Outlook',
				'download' => $expected_outlook_download,
			),
			'yahoo'   => array(
				'name' => 'Yahoo Calendar',
				'link' => $expected_yahoo_link,
			),
		);

		$this->assertSame( $expects, $output );

		Utility::set_and_get_hidden_property( $event, 'event', null );

		$this->assertEmpty(
			$event->get_calendar_links(),
			'Failed to assert that calendar links are empty.'
		);
	}

	/**
	 * Cover for has_event_started method.
	 *
	 * @covers ::has_event_started
	 *
	 * @return void
	 */
	public function test_has_event_started(): void {
		$post  = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$event = new Event( $post->ID );
		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$end->modify( '+2 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$output = $event->has_event_started();

		$this->assertTrue(
			$output,
			'Failed to assert that event has started.'
		);

		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$start->modify( '+2 minutes' );
		$end->modify( '+2 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$output = $event->has_event_started();

		$this->assertFalse(
			$output,
			'Failed to assert that event has not started.'
		);

		$output = $event->has_event_started( -3 );

		$this->assertTrue(
			$output,
			'Failed to assert that event has started with offset.'
		);

		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$start->modify( '+1 hour' );
		$end->modify( '+3 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$output = $event->has_event_started();

		$this->assertFalse(
			$output,
			'Failed to assert that event has not started.'
		);
	}

	/**
	 * Cover for has_event_past method.
	 *
	 * @covers ::has_event_past
	 *
	 * @return void
	 */
	public function test_has_event_past(): void {
		$post  = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$event = new Event( $post->ID );
		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$start->modify( '-3 hours' );
		$end->modify( '-1 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$output = $event->has_event_past();

		$this->assertTrue(
			$output,
			'Failed to assert that event has past.'
		);

		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$start->modify( '+1 hours' );
		$end->modify( '+3 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$output = $event->has_event_past();

		$this->assertFalse( $output );

		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$start->modify( '-1 hour' );
		$end->modify( '-1 minute' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$output = $event->has_event_past();

		$this->assertTrue(
			$output,
			'Failed to assert that event has past.'
		);

		$output = $event->has_event_past( 5 );

		$this->assertFalse(
			$output,
			'Failed to assert that event has not past with offset.'
		);
	}

	/**
	 * Cover for is_event_happening method.
	 *
	 * @covers ::is_event_happening
	 *
	 * @return void
	 */
	public function test_is_event_happening(): void {
		$post  = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get();
		$event = new Event( $post->ID );
		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$end->modify( '+2 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$output = $event->is_event_happening();

		$this->assertTrue(
			$output,
			'Failed to assert that event is happening'
		);

		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$start->modify( '-3 hours' );
		$end->modify( '-1 hour' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$output = $event->is_event_happening();

		$this->assertFalse(
			$output,
			'Failed to assert event is not happening.'
		);
	}

	/**
	 * Coverage for maybe_get_online_event_link method.
	 *
	 * @covers ::maybe_get_online_event_link
	 *
	 * @return void
	 */
	public function test_maybe_get_online_event_link(): void {
		$event_id = $this->mock->post(
			array(
				'post_type' => 'gatherpress_event',
			)
		)->get()->ID;
		$event    = new Event( $event_id );
		$start    = new DateTime( 'now' );
		$end      = new DateTime( 'now' );

		$end->modify( '+2 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$user_id = $this->mock->user()->get()->ID;
		$link    = 'https:://unittest.com/video/';

		$event->save_datetimes( $params );

		update_post_meta( $event_id, 'gatherpress_online_event_link', $link );

		$this->assertEmpty(
			$event->maybe_get_online_event_link(),
			'Failed to assert online event link is empty.'
		);

		$event->rsvp->save( $user_id, 'attending' );

		$this->assertSame(
			$link,
			$event->maybe_get_online_event_link(),
			'Failed to assert online event link is present.'
		);

		$start = new DateTime( 'now' );
		$end   = new DateTime( 'now' );

		$start->modify( '-4 hours' );
		$end->modify( '-2 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);

		$event->save_datetimes( $params );

		$this->assertEmpty(
			$event->maybe_get_online_event_link(),
			'Failed to assert online event link is empty.'
		);

		Utility::set_and_get_hidden_property( $event, 'rsvp', null );

		$this->assertEmpty(
			$event->maybe_get_online_event_link(),
			'Failed to assert empty string due to RSVP being set to null.'
		);
	}

	/**
	 * A post that is not an event never surfaces an online event link, even
	 * when the meta happens to be present on the post.
	 *
	 * @since 0.36.0
	 * @covers ::maybe_get_online_event_link
	 *
	 * @return void
	 */
	public function test_maybe_get_online_event_link_returns_empty_without_post(): void {
		$post_id = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;

		update_post_meta( $post_id, 'gatherpress_online_event_link', 'https://unittest.com/video/' );

		$this->assertSame(
			'',
			( new Event( $post_id ) )->maybe_get_online_event_link(),
			'Failed to assert online event link is empty for a non-event post.'
		);
	}

	/**
	 * Coverage for is_same_date method.
	 *
	 * @covers ::is_same_date
	 *
	 * @return void
	 */
	public function test_is_same_date(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		// Test same day event.
		$start = new DateTime( '2025-06-15 10:00:00' );
		$end   = new DateTime( '2025-06-15 14:00:00' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		$this->assertTrue(
			$event->is_same_date(),
			'Failed to assert event starts and ends on the same day.'
		);

		// Test multi-day event.
		$start = new DateTime( '2025-06-15 22:00:00' );
		$end   = new DateTime( '2025-06-16 02:00:00' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		$this->assertFalse(
			$event->is_same_date(),
			'Failed to assert event spans multiple days.'
		);
	}

	/**
	 * Coverage for is_same_date method without datetimes.
	 *
	 * A post that is not an event resolves to no datetimes at all, so there is
	 * no date to compare and the answer is false rather than a spurious true
	 * from two empty strings matching each other.
	 *
	 * @covers ::is_same_date
	 *
	 * @return void
	 */
	public function test_is_same_date_is_false_without_datetimes(): void {
		$event = new Event( 0 );

		$this->assertFalse(
			$event->is_same_date(),
			'Failed to assert that an event with no datetimes is not on the same date.'
		);
	}

	/**
	 * Coverage for get_datetime_start method.
	 *
	 * @covers ::get_datetime_start
	 *
	 * @return void
	 */
	public function test_get_datetime_start(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$start = new DateTime( '2025-06-15 14:30:00' );
		$end   = new DateTime( '2025-06-15 16:30:00' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		$result = $event->get_datetime_start();

		$this->assertNotEmpty( $result, 'Failed to assert datetime start is not empty.' );
		$this->assertStringContainsString( '2025', $result );
	}

	/**
	 * Coverage for get_datetime_end method.
	 *
	 * @covers ::get_datetime_end
	 *
	 * @return void
	 */
	public function test_get_datetime_end(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$start = new DateTime( '2025-06-15 14:30:00' );
		$end   = new DateTime( '2025-06-15 16:30:00' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		$result = $event->get_datetime_end();

		$this->assertNotEmpty( $result, 'Failed to assert datetime end is not empty.' );
		$this->assertStringContainsString( 'June', $result );
	}

	/**
	 * Coverage for get_time_end method.
	 *
	 * @covers ::get_time_end
	 *
	 * @return void
	 */
	public function test_get_time_end(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$start = new DateTime( '2025-06-15 14:30:00' );
		$end   = new DateTime( '2025-06-15 16:30:00' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		// Test default format.
		$result = $event->get_time_end();

		$this->assertNotEmpty( $result, 'Failed to assert time end is not empty.' );

		// Test custom format.
		$result = $event->get_time_end( 'H:i' );

		$this->assertMatchesRegularExpression( '/^\d{2}:\d{2}$/', $result, 'Failed to assert custom time format.' );
	}

	/**
	 * Coverage for get_formatted_datetime method.
	 *
	 * @covers ::get_formatted_datetime
	 *
	 * @return void
	 */
	public function test_get_formatted_datetime(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$start = new DateTime( '2025-06-15 14:30:00' );
		$end   = new DateTime( '2025-06-15 16:30:00' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		// Test start datetime.
		$result = $event->get_formatted_datetime( 'start' );

		$this->assertNotEmpty( $result, 'Failed to assert formatted start datetime is not empty.' );

		// Test end datetime.
		$result = $event->get_formatted_datetime( 'end' );

		$this->assertNotEmpty( $result, 'Failed to assert formatted end datetime is not empty.' );
	}

	/**
	 * A stored datetime that validates but will not parse reports no datetime
	 * at all, bailing before the format filter rather than falling back to the
	 * epoch.
	 *
	 * @since 0.36.0
	 * @covers ::get_formatted_datetime
	 *
	 * @return void
	 */
	public function test_get_formatted_datetime_returns_empty_for_unparsable_value(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		// Validates as `Y-m-d H:i:s` but has an out-of-range day and hour, so strtotime() rejects it.
		update_post_meta( $event_id, 'gatherpress_datetime_start_gmt', '2030-06-31 25:00:00' );

		$formatted = 0;
		$counter   = static function ( $format ) use ( &$formatted ) {
			++$formatted;

			return $format;
		};

		add_filter( 'gatherpress_datetime_format', $counter );

		$result = ( new Event( $event_id ) )->get_formatted_datetime( 'Y-m-d', 'start', false );

		remove_filter( 'gatherpress_datetime_format', $counter );

		$this->assertSame(
			'',
			$result,
			'Failed to assert an unparsable stored datetime reports no datetime at all.'
		);
		$this->assertSame(
			0,
			$formatted,
			'Failed to assert an unparsable stored datetime bails before the datetime format filter runs.'
		);
	}

	/**
	 * Machine-readable datetime accessors bypass display-format filters.
	 *
	 * @covers ::get_datetime_start_iso
	 * @covers ::get_datetime_end_iso
	 */
	public function test_get_iso_datetime_accessors(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$event->save_datetimes(
			array(
				'datetime_start' => '2025-06-15 14:30:00',
				'datetime_end'   => '2025-06-15 16:30:00',
				'timezone'       => 'America/New_York',
			)
		);

		$empty_event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$empty_event    = new Event( $empty_event_id );
		$this->assertSame( '', $empty_event->get_datetime_start_iso() );
		$this->assertSame( '', $empty_event->get_datetime_end_iso() );

		$filter = static function (): string {
			return 'Y-m-d';
		};
		add_filter( 'gatherpress_datetime_format', $filter );

		try {
			$this->assertSame( '2025-06-15', $event->get_datetime_start( 'c' ) );
			$this->assertSame( '2025-06-15', $event->get_datetime_end( 'c' ) );
			$this->assertSame( '2025-06-15T14:30:00-04:00', $event->get_datetime_start_iso() );
			$this->assertSame( '2025-06-15T16:30:00-04:00', $event->get_datetime_end_iso() );
		} finally {
			remove_filter( 'gatherpress_datetime_format', $filter );
		}
	}

	/**
	 * Branch coverage for the private format_datetime helper.
	 *
	 * The format_datetime helper is only reachable through public wrappers,
	 * which the PMC test framework does not trace into coverage.xml, so each
	 * branch is invoked directly to record it. Exercises the local-timezone and
	 * filter path, the GMT path, and the unparsable-datetime bail path.
	 *
	 * @since 0.36.0
	 * @covers ::format_datetime
	 *
	 * @return void
	 */
	public function test_format_datetime_branches(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$event->save_datetimes(
			array(
				'datetime_start' => '2025-06-15 14:30:00',
				'datetime_end'   => '2025-06-15 16:30:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Local-timezone path with a filter applied.
		$filtered = 0;
		$filter   = static function ( $format ) use ( &$filtered ) {
			++$filtered;

			return $format;
		};
		add_filter( 'gatherpress_datetime_format', $filter );

		try {
			$result = Utility::invoke_hidden_method(
				$event,
				'format_datetime',
				array( 'c', 'start', true, true )
			);
		} finally {
			remove_filter( 'gatherpress_datetime_format', $filter );
		}

		$this->assertNotSame(
			0,
			$filtered,
			'Failed to assert format_datetime applies the format filter in local time.'
		);
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[45]:00$/',
			$result,
			'Failed to assert format_datetime returns a local-time timestamp.'
		);

		// GMT path with the filter disabled.
		$result = Utility::invoke_hidden_method(
			$event,
			'format_datetime',
			array( 'c', 'start', false, false )
		);

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
			$result,
			'Failed to assert format_datetime returns a GMT timestamp.'
		);

		// Unparsable stored datetime bails before the filter runs.
		update_post_meta( $event_id, 'gatherpress_datetime_start_gmt', '2030-06-31 25:00:00' );

		// New instance so it reads the updated meta instead of the cached datetimes.
		$event = new Event( $event_id );

		$formatted = 0;
		$counter   = static function ( $format ) use ( &$formatted ) {
			++$formatted;

			return $format;
		};
		add_filter( 'gatherpress_datetime_format', $counter );

		try {
			$result = Utility::invoke_hidden_method(
				$event,
				'format_datetime',
				array( 'Y-m-d', 'start', false, true )
			);
		} finally {
			remove_filter( 'gatherpress_datetime_format', $counter );
		}

		$this->assertSame(
			'',
			$result,
			'Failed to assert an unparsable stored datetime reports no datetime at all.'
		);
		$this->assertSame(
			0,
			$formatted,
			'Failed to assert an unparsable stored datetime bails before the format filter runs.'
		);
	}

	/**
	 * Coverage for get_calendar_description method.
	 *
	 * @covers ::get_calendar_description
	 *
	 * @return void
	 */
	public function test_get_calendar_description(): void {
		$event_id = $this->mock->post(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => 'Test Event',
				'post_excerpt' => 'This is a test event description.',
			)
		)->get()->ID;

		$event = new Event( $event_id );

		$start = new DateTime( '2025-06-15 14:30:00' );
		$end   = new DateTime( '2025-06-15 16:30:00' );

		$start_formatted = $start->format( Event::DATETIME_FORMAT );
		$end_formatted   = $end->format( Event::DATETIME_FORMAT );

		$params = array(
			'datetime_start' => $start_formatted,
			'datetime_end'   => $end_formatted,
			'timezone'       => 'America/New_York',
		);

		$event->save_datetimes( $params );

		$result = $event->get_calendar_description();

		// The method returns "For details go to {permalink}".
		$this->assertNotEmpty( $result, 'Failed to assert calendar description is not empty.' );
		$this->assertStringContainsString(
			'For details',
			$result,
			'Failed to assert calendar description contains standard text.'
		);

		// Test with no excerpt.
		$event_id = $this->mock->post(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Test Event No Excerpt',
			)
		)->get()->ID;

		$event = new Event( $event_id );
		$event->save_datetimes( $params );
		$result = $event->get_calendar_description();

		$this->assertNotEmpty( $result, 'Failed to assert calendar description is not empty even without excerpt.' );
	}

	/**
	 * A post that is not an event has no permalink worth pointing a calendar
	 * client at, so the description is empty rather than pointing at whatever
	 * post is globally queried.
	 *
	 * @since 0.36.0
	 * @covers ::get_calendar_description
	 *
	 * @return void
	 */
	public function test_get_calendar_description_returns_empty_without_post(): void {
		$post_id = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;

		$this->assertSame(
			'',
			( new Event( $post_id ) )->get_calendar_description(),
			'Failed to assert a non-event post has no calendar description.'
		);
	}

	/**
	 * Coverage for __construct with non-event post type.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_with_non_event_post(): void {
		$post_id = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;
		$event   = new Event( $post_id );

		$this->assertNull( $event->event, 'Failed to assert event is null for non-event post.' );
		$this->assertNull( $event->rsvp, 'Failed to assert rsvp is null for non-event post.' );
	}

	/**
	 * A published event renders for everyone, while an unpublished one renders
	 * only for viewers allowed to read it.
	 *
	 * @covers ::is_viewable
	 *
	 * @return void
	 */
	public function test_is_viewable(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( 0 );
		$this->assertTrue( Event::is_viewable( $post_id ), 'A published event renders for the public.' );

		foreach ( array( 'draft', 'pending', 'private' ) as $status ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $status,
				)
			);

			wp_set_current_user( 0 );
			$this->assertFalse(
				Event::is_viewable( $post_id ),
				sprintf( 'A %s event does not render for the public.', $status )
			);

			wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
			$this->assertTrue(
				Event::is_viewable( $post_id ),
				sprintf( 'A %s event renders for a viewer who can read it.', $status )
			);
		}

		wp_set_current_user( 0 );
	}

	/**
	 * The editor's preview renders an event that would otherwise be withheld.
	 *
	 * @covers ::is_viewable
	 *
	 * @return void
	 */
	public function test_is_viewable_in_preview(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		wp_set_current_user( 0 );
		$this->assertFalse( Event::is_viewable( $post_id ), 'The draft is withheld outside a preview.' );

		global $wp_query;
		$wp_query->is_preview        = true;
		$wp_query->queried_object    = get_post( $post_id );
		$wp_query->queried_object_id = $post_id;

		$this->assertTrue( Event::is_viewable( $post_id ), 'A preview renders the draft being previewed.' );

		// Previewing one post does not open every other event: block context
		// carries a post ID, so an unrelated draft must still be read-checked.
		$other_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'private',
			)
		);

		$this->assertFalse(
			Event::is_viewable( $other_id ),
			'A preview of one post does not render a different restricted event.'
		);

		$wp_query->is_preview        = false;
		$wp_query->queried_object    = null;
		$wp_query->queried_object_id = 0;
	}

	/**
	 * The roster follows the event: public once published, limited to viewers
	 * who can read it otherwise, and withheld from post types that take no
	 * RSVPs at all.
	 *
	 * @covers ::can_read_rsvps
	 *
	 * @return void
	 */
	public function test_can_read_rsvps(): void {
		wp_set_current_user( 0 );

		$this->assertFalse( Event::can_read_rsvps( 0 ), 'A post that does not exist has no roster.' );
		$this->assertFalse(
			Event::can_read_rsvps( $this->factory->post->create() ),
			'A post type that takes no RSVPs has no roster.'
		);

		// Exercised on its own: an administrator would clear every capability
		// check, so only the support guard can deny this.
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->assertFalse(
			Event::can_read_rsvps( $this->factory->post->create() ),
			'A post type that takes no RSVPs has no roster, whoever is asking.'
		);
		wp_set_current_user( 0 );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$this->assertTrue( Event::can_read_rsvps( $post_id ), 'A published event roster is public.' );

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'secret',
			)
		);
		$this->assertFalse(
			Event::can_read_rsvps( $post_id ),
			'A password-protected event withholds its roster until the gate is satisfied.'
		);

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => '',
				'post_status'   => 'private',
			)
		);
		$this->assertFalse( Event::can_read_rsvps( $post_id ), 'A private event withholds its roster.' );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( Event::can_read_rsvps( $post_id ), 'A viewer who can edit the event sees the roster.' );

		wp_set_current_user( 0 );
	}
}
