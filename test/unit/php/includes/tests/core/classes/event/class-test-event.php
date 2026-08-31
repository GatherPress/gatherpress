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
// Deep import on purpose: test_prior_fqn_resolves_to_current_class asserts
// Event::class equals the real FQN, which the BC alias intentionally is not.
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Rsvp;
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

		$this->assertNull( Utility::get_hidden_property( $event, 'post' ) );

		$post  = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$event = new Event( $post->ID );

		$this->assertInstanceOf( WP_Post::class, Utility::get_hidden_property( $event, 'post' ) );
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
	 * Covers raw parts returned for a same-day event.
	 *
	 * @covers ::get_display_datetime_parts
	 *
	 * @return void
	 */
	public function test_get_display_datetime_parts(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'l, F j, Y',
				'time_format'   => 'g:i A',
				'show_timezone' => false,
			)
		);

		$post  = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$event = new Event( $post->ID );
		$event->save_datetimes(
			array(
				'datetime_start' => '2020-05-11 15:00:00',
				'datetime_end'   => '2020-05-11 17:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$parts = $event->get_display_datetime_parts( '', '', '', 'UNTIL', 'yes' );

		// parts['start'] is the formatted date/time without the timezone suffix;
		// the timezone lives in its own 'timezone' part so the template can wrap
		// each segment in a machine-readable <time> tag without nesting the
		// timezone string inside the start <time> element.
		$this->assertSame( 'Monday, May 11, 2020 3:00 PM', $parts['start'] );
		$this->assertSame( 'UNTIL', $parts['separator'] );
		$this->assertSame( '5:00 PM', $parts['end'] );
		$this->assertSame( 'EDT', $parts['timezone'] );
		// get_display_datetime() joins the non-empty parts and is what callers
		// that want a single human-readable string consume.
		$this->assertSame(
			'Monday, May 11, 2020 3:00 PM UNTIL 5:00 PM EDT',
			$event->get_display_datetime( '', '', '', 'UNTIL', 'yes' )
		);

		delete_option( 'gatherpress_settings' );
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

		Utility::set_and_get_hidden_property( $event, 'post', $post );

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

		Utility::set_and_get_hidden_property( $event, 'post', null );

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

		( new Rsvp( $event_id ) )->save( $user_id, 'attending' );

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
	 * Coverage for is_same_date resisting a corrupting datetime format filter.
	 *
	 * A filter that ignores its $format argument (returning a fixed
	 * `Y-m-d H:i`) would previously make a same-day event compare unequal,
	 * because is_same_date compared filter-passing formatted strings. It now
	 * compares the unfiltered ISO date portions.
	 *
	 * @since 0.36.0
	 * @covers ::is_same_date
	 *
	 * @return void
	 */
	public function test_is_same_date_ignores_datetime_format_filter(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$start = new DateTime( '2025-06-15 10:00:00' );
		$end   = new DateTime( '2025-06-15 14:00:00' );

		$event->save_datetimes(
			array(
				'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
				'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
				'timezone'       => 'America/New_York',
			)
		);

		$filter = static fn () => 'Y-m-d H:i';
		add_filter( 'gatherpress_datetime_format', $filter );

		$this->assertTrue(
			$event->is_same_date(),
			'Failed to assert that a same-day event stays same-day despite a corrupting datetime_format filter.'
		);

		remove_filter( 'gatherpress_datetime_format', $filter );
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
	 * Coverage for get_display_datetime honoring a block-level timezone override.
	 *
	 * With the global show_timezone setting off, a block that overrides
	 * showTimezone to yes must still show the timezone; without any override
	 * the timezone stays hidden.
	 *
	 * @since 0.36.0
	 * @covers ::get_display_datetime
	 *
	 * @return void
	 */
	public function test_get_display_datetime_timezone_override_with_global_off(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'l, F j, Y',
				'time_format'   => 'g:i A',
				'show_timezone' => false,
			)
		);

		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$event->save_datetimes(
			array(
				'datetime_start' => '2025-06-15 10:00:00',
				'datetime_end'   => '2025-06-15 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$this->assertStringContainsString(
			'EDT',
			$event->get_display_datetime( '', '', '', '', 'yes' ),
			'Failed to assert the timezone shows when the block overrides the global setting to yes.'
		);

		$this->assertStringNotContainsString(
			'EDT',
			$event->get_display_datetime(),
			'Failed to assert the timezone stays hidden when the global setting is off and there is no override.'
		);

		delete_option( 'gatherpress_settings' );
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

		$this->assertNull( $event->post, 'Failed to assert post is null for non-event post.' );
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

	/**
	 * An event is not all day unless it says so.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::is_all_day
	 *
	 * @return void
	 */
	public function test_is_all_day(): void {
		$post  = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$event = new Event( $post->ID );

		$this->assertFalse( $event->is_all_day(), 'Failed to assert an event is timed by default.' );

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		$this->assertTrue(
			( new Event( $post->ID ) )->is_all_day(),
			'Failed to assert the meta makes an event all day.'
		);
	}

	/**
	 * A post ID that is not an event is not an all-day one either.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::is_all_day
	 *
	 * @return void
	 */
	public function test_is_all_day_without_a_post(): void {
		$this->assertFalse(
			( new Event( 0 ) )->is_all_day(),
			'Failed to assert a missing post is not all day.'
		);
	}

	/**
	 * A datetime snaps to the beginning or the end of its own day.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::to_day_boundary
	 *
	 * @return void
	 */
	public function test_to_day_boundary(): void {
		$event = new Event( $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get()->ID );

		$this->assertSame(
			'2026-08-29 00:00:00',
			Utility::invoke_hidden_method( $event, 'to_day_boundary', array( '2026-08-29 14:30:00', 'start' ) ),
			'Failed to assert a start snaps to the beginning of its day.'
		);

		$this->assertSame(
			'2026-08-29 23:59:59',
			Utility::invoke_hidden_method( $event, 'to_day_boundary', array( '2026-08-29 14:30:00', 'end' ) ),
			'Failed to assert an end snaps to the end of its day.'
		);

		// A multi-day event keeps each end on its own day.
		$this->assertSame(
			'2026-08-31 23:59:59',
			Utility::invoke_hidden_method( $event, 'to_day_boundary', array( '2026-08-31 09:00:00', 'end' ) ),
			'Failed to assert each boundary belongs to its own date.'
		);

		$this->assertSame(
			'',
			Utility::invoke_hidden_method( $event, 'to_day_boundary', array( '', 'start' ) ),
			'Failed to assert nothing is snapped into a day nobody chose.'
		);
	}

	/**
	 * Snapping holds on its own, without a caller having converted first.
	 *
	 * The method finds the date rather than assuming where it sits, so it
	 * cannot silently slice ten characters off something in another shape.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::to_day_boundary
	 *
	 * @return void
	 */
	public function test_to_day_boundary_does_not_assume_a_format(): void {
		$event = new Event( $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get()->ID );

		$this->assertSame(
			'2026-08-29 00:00:00',
			Utility::invoke_hidden_method( $event, 'to_day_boundary', array( '2026-08-29T09:00:00', 'start' ) ),
			'Failed to assert an ISO datetime snaps.'
		);

		$this->assertSame(
			'2026-08-29 23:59:59',
			Utility::invoke_hidden_method( $event, 'to_day_boundary', array( '29 August 2026 9:00am', 'end' ) ),
			'Failed to assert a written-out date snaps.'
		);

		$this->assertSame(
			'',
			Utility::invoke_hidden_method( $event, 'to_day_boundary', array( 'not-a-date', 'start' ) ),
			'Failed to assert a non-date snaps to nothing.'
		);
	}

	/**
	 * Every shape the save path accepts converts to the one format.
	 *
	 * `get_gmt_datetime()` takes anything `date_create()` understands, but
	 * `get_datetime()` discards what it reads back in any other shape, so the
	 * conversion happens once on the way in.
	 *
	 * @since 0.36.0
	 *
	 * @dataProvider data_normalize_datetime
	 *
	 * @covers ::normalize_datetime
	 *
	 * @param string $datetime The datetime a caller wrote.
	 * @param string $expects  What it is stored as.
	 * @param string $message  What the case is proving.
	 *
	 * @return void
	 */
	public function test_normalize_datetime( string $datetime, string $expects, string $message ): void {
		$event = new Event( $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get()->ID );

		$this->assertSame(
			$expects,
			Utility::invoke_hidden_method(
				$event,
				'normalize_datetime',
				array( $datetime, new DateTimeZone( 'UTC' ) )
			),
			$message
		);
	}

	/**
	 * Data provider for datetime conversion.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<int, string>> Cases.
	 */
	public function data_normalize_datetime(): array {
		return array(
			'already in the format'  => array(
				'2026-08-29 14:30:00',
				'2026-08-29 14:30:00',
				'A datetime already in the format should be left as it is.',
			),
			'iso 8601'               => array(
				'2026-08-29T09:00:00',
				'2026-08-29 09:00:00',
				'An ISO datetime should convert.',
			),
			'iso 8601 in zulu'       => array(
				'2026-08-29T09:00:00Z',
				'2026-08-29 09:00:00',
				'A Zulu datetime should convert.',
			),
			'carrying an offset'     => array(
				// Read in +09:00, then moved into the event's zone, so the
				// local column and the GMT one derived from it agree.
				'2026-08-29T23:00:00+09:00',
				'2026-08-29 14:00:00',
				'An offset datetime should be moved into the event timezone.',
			),
			'no seconds'             => array(
				'2026-08-29 09:00',
				'2026-08-29 09:00:00',
				'A datetime with no seconds should gain them.',
			),
			'date only'              => array(
				'2026-08-29',
				'2026-08-29 00:00:00',
				'A bare date should become the start of that day.',
			),
			'slashes, month first'   => array(
				'08/29/2026 9:00 am',
				'2026-08-29 09:00:00',
				'A slashed date should convert.',
			),
			'written out'            => array(
				'29 August 2026 9:00am',
				'2026-08-29 09:00:00',
				'A written-out date should convert.',
			),
			'a day that rolls over'  => array(
				// PHP's own behavior, and what the rest of the class already
				// tolerates rather than something introduced here.
				'2026-02-30 09:00:00',
				'2026-03-02 09:00:00',
				'A day past the end of its month should roll over.',
			),
			'an hour that cannot be' => array(
				'2026-08-29 25:00:00',
				'',
				'An impossible hour should become nothing.',
			),
			'not a datetime'         => array(
				'not-a-date',
				'',
				'A non-date should become nothing.',
			),
			'empty'                  => array(
				// Parses to the current time rather than failing, so this is
				// caught before it is read.
				'',
				'',
				'An empty value should become nothing rather than now.',
			),
			'whitespace'             => array(
				'   ',
				'',
				'Whitespace should become nothing rather than now.',
			),
			'a mysql zero date'      => array(
				// Parses to a negative year instead of failing.
				'0000-00-00 00:00:00',
				'',
				'A zero date should become nothing.',
			),
		);
	}

	/**
	 * Saving an all-day event stores a span that covers the day.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::save_datetimes
	 *
	 * @return void
	 */
	public function test_save_datetimes_snaps_an_all_day_event(): void {
		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 18:00:00',
				'datetime_end'   => '2026-08-29 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$datetime = ( new Event( $post->ID ) )->get_datetime();

		$this->assertSame(
			'2026-08-29 00:00:00',
			$datetime['datetime_start'],
			'Failed to assert an all-day event starts at the beginning of its day.'
		);

		$this->assertSame(
			'2026-08-29 23:59:59',
			$datetime['datetime_end'],
			'Failed to assert an all-day event ends at the end of its day.'
		);
	}

	/**
	 * A timed event keeps the times it was given.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::save_datetimes
	 *
	 * @return void
	 */
	public function test_save_datetimes_leaves_a_timed_event_alone(): void {
		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 18:00:00',
				'datetime_end'   => '2026-08-29 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$this->assertSame(
			'2026-08-29 18:00:00',
			( new Event( $post->ID ) )->get_datetime()['datetime_start'],
			'Failed to assert a timed event keeps its time.'
		);
	}

	/**
	 * An all-day event renders its date and nothing else.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_display_datetime
	 * @covers ::get_display_formats
	 * @covers ::get_display_end
	 *
	 * @return void
	 */
	public function test_get_display_datetime_for_an_all_day_event(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'F j, Y',
				'time_format'   => 'g:i a',
				'show_timezone' => false,
			)
		);

		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 18:00:00',
				'datetime_end'   => '2026-08-29 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		$this->assertSame(
			'August 29, 2026 6:00 pm to 8:00 pm',
			( new Event( $post->ID ) )->get_display_datetime(),
			'Failed to assert a timed event renders its times.'
		);

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		// The end and the separator before it go with the time.
		$this->assertSame(
			'August 29, 2026',
			( new Event( $post->ID ) )->get_display_datetime(),
			'Failed to assert an all-day event renders only its date.'
		);
	}

	/**
	 * A one-day all-day event does not repeat its date as an end.
	 *
	 * There is no end time to render and the end date is the start date,
	 * which has already been said, so nothing follows it whatever format
	 * the block saved.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_display_datetime
	 * @covers ::get_display_formats
	 * @covers ::get_display_end
	 *
	 * @return void
	 */
	public function test_get_display_datetime_for_an_all_day_event_with_an_end_format(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'F j, Y',
				'time_format'   => 'g:i a',
				'show_timezone' => false,
			)
		);

		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 09:00:00',
				'datetime_end'   => '2026-08-29 17:00:00',
				'timezone'       => 'UTC',
			)
		);

		$this->assertSame(
			'August 29, 2026',
			( new Event( $post->ID ) )->get_display_datetime( '', '', 'F j, Y' ),
			'Failed to assert a one-day all-day event does not repeat its date.'
		);
	}

	/**
	 * A multi-day all-day event still honors a block's end format.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_display_end
	 *
	 * @return void
	 */
	public function test_get_display_datetime_for_a_multi_day_all_day_event_with_an_end_format(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'F j, Y',
				'time_format'   => 'g:i a',
				'show_timezone' => false,
			)
		);

		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 09:00:00',
				'datetime_end'   => '2026-08-31 17:00:00',
				'timezone'       => 'UTC',
			)
		);

		$this->assertSame(
			'August 29, 2026 to Aug 31',
			( new Event( $post->ID ) )->get_display_datetime( '', '', 'M j' ),
			'Failed to assert a multi-day all-day event uses the end format it was given.'
		);
	}

	/**
	 * An all-day event drops the time out of the formats it is given.
	 *
	 * Wanting a time on the face of it means the event is not all day. A
	 * format saved on the block before the toggle was flipped keeps its
	 * date and loses its time, rather than printing 12:00 am and 11:59 pm
	 * as though someone had chosen them.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_display_datetime
	 * @covers ::get_display_formats
	 * @covers ::remove_time_format_chars
	 *
	 * @dataProvider data_all_day_display_formats
	 *
	 * @param string $start_format The format the block saved for the start.
	 * @param string $end_format   The format the block saved for the end.
	 * @param string $datetime_end When the event ends.
	 * @param string $expected     What the event should render as.
	 *
	 * @return void
	 */
	public function test_get_display_datetime_strips_time_from_all_day_formats(
		string $start_format,
		string $end_format,
		string $datetime_end,
		string $expected
	): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'F j, Y',
				'time_format'   => 'g:i a',
				'show_timezone' => false,
			)
		);

		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 09:00:00',
				'datetime_end'   => $datetime_end,
				'timezone'       => 'UTC',
			)
		);

		$this->assertSame(
			$expected,
			( new Event( $post->ID ) )->get_display_datetime( '', $start_format, $end_format ),
			'Failed to assert an all-day event renders no time.'
		);
	}

	/**
	 * Data provider for all-day display formats.
	 *
	 * @since 0.36.0
	 *
	 * @return array[]
	 */
	public function data_all_day_display_formats(): array {
		return array(
			'a start format carrying a time keeps only its date' => array(
				'F j, Y g:i a',
				'',
				'2026-08-29 17:00:00',
				'August 29, 2026',
			),
			'a start format that is only a time falls back to the site format' => array(
				'g:i a',
				'',
				'2026-08-29 17:00:00',
				'August 29, 2026',
			),
			'a one-day end format carrying a time renders nothing' => array(
				'',
				'g:i a',
				'2026-08-29 17:00:00',
				'August 29, 2026',
			),
			'a multi-day end format keeps only its date'  => array(
				'',
				'M j g:i a',
				'2026-08-31 17:00:00',
				'August 29, 2026 to Aug 31',
			),
			'a multi-day end format that is only a time falls back' => array(
				'',
				'g:i a',
				'2026-08-31 17:00:00',
				'August 29, 2026 to August 31, 2026',
			),
			'a start format naming the timezone loses it' => array(
				'F j, Y T',
				'',
				'2026-08-29 17:00:00',
				'August 29, 2026',
			),
		);
	}

	/**
	 * A timed event still uses the formats it was given, time and all.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_display_datetime
	 * @covers ::get_display_formats
	 *
	 * @return void
	 */
	public function test_get_display_datetime_keeps_time_for_a_timed_event(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'F j, Y',
				'time_format'   => 'g:i a',
				'show_timezone' => false,
			)
		);

		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 09:00:00',
				'datetime_end'   => '2026-08-29 17:00:00',
				'timezone'       => 'UTC',
			)
		);

		$this->assertSame(
			'August 29, 2026 9:00 am to 5:00 pm',
			( new Event( $post->ID ) )->get_display_datetime( '', 'F j, Y g:i a', 'g:i a' ),
			'Failed to assert a timed event keeps the time in its formats.'
		);
	}

	/**
	 * An all-day event spanning days still says when it ends.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_display_end
	 *
	 * @return void
	 */
	public function test_get_display_datetime_for_a_multi_day_all_day_event(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'F j, Y',
				'time_format'   => 'g:i a',
				'show_timezone' => false,
			)
		);

		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 09:00:00',
				'datetime_end'   => '2026-08-31 17:00:00',
				'timezone'       => 'UTC',
			)
		);

		$this->assertSame(
			'August 29, 2026 to August 31, 2026',
			( new Event( $post->ID ) )->get_display_datetime(),
			'Failed to assert a multi-day all-day event names the day it ends on.'
		);
	}

	/**
	 * An all-day event's date does not move between timezones.
	 *
	 * The day is floating, the way a calendar's date value is: converting the
	 * stored GMT would land the day before or after depending on which side
	 * of the meridian the event sits.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_formatted_all_day
	 * @covers ::get_formatted_datetime
	 *
	 * @return void
	 */
	public function test_an_all_day_date_does_not_shift_across_timezones(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'F j, Y',
				'time_format'   => 'g:i a',
				'show_timezone' => false,
			)
		);

		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		// Tokyo is the case that breaks under conversion: a local midnight
		// start is the previous day in GMT.
		foreach ( array( 'UTC', 'America/New_York', 'Asia/Tokyo' ) as $timezone ) {
			( new Event( $post->ID ) )->save_datetimes(
				array(
					'post_id'        => $post->ID,
					'datetime_start' => '2026-08-29 09:00:00',
					'datetime_end'   => '2026-08-29 17:00:00',
					'timezone'       => $timezone,
				)
			);

			$event = new Event( $post->ID );

			$this->assertSame(
				'August 29, 2026',
				$event->get_display_datetime(),
				sprintf( 'Failed to assert the date holds in %s.', $timezone )
			);

			$this->assertSame(
				'August 29, 2026',
				$event->get_formatted_datetime( 'F j, Y', 'start', false ),
				sprintf( 'Failed to assert the date holds rendered as GMT in %s.', $timezone )
			);
		}
	}

	/**
	 * An all-day event with no stored datetime renders nothing.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_formatted_all_day
	 *
	 * @return void
	 */
	public function test_get_formatted_all_day_without_a_datetime(): void {
		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		$this->assertSame(
			'',
			( new Event( $post->ID ) )->get_formatted_datetime( 'F j, Y', 'start' ),
			'Failed to assert an event with no datetime renders nothing.'
		);
	}

	/**
	 * An overflowing datetime renders nothing rather than dying.
	 *
	 * `Validate::datetime()` accepts what `DateTime::createFromFormat()`
	 * accepts, which is wider than a real date, so a value like June 31st at
	 * 25:00 survives `get_datetime()`. Constructing a date from it throws.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_formatted_all_day
	 *
	 * @return void
	 */
	public function test_get_formatted_all_day_with_an_overflowing_datetime(): void {
		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		// Written straight to meta: `save_datetimes()` would convert it, and
		// the point is what happens to a value that never went through it.
		update_post_meta( $post->ID, 'gatherpress_datetime_start', '2030-06-31 25:00:00' );

		$this->assertSame(
			'',
			( new Event( $post->ID ) )->get_formatted_datetime( 'F j, Y', 'start' ),
			'Failed to assert an overflowing datetime renders nothing.'
		);
	}

	/**
	 * An unusable timezone still renders the day it was stored for.
	 *
	 * `get_datetime()` discards a stored timezone that does not validate, so
	 * the only way an unusable one reaches here is the `gatherpress_timezone`
	 * filter, which anything can set.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_formatted_all_day
	 *
	 * @return void
	 */
	public function test_get_formatted_all_day_with_an_unusable_timezone(): void {
		$post     = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();
		$unusable = static fn(): string => 'Not/AZone';

		update_post_meta( $post->ID, 'gatherpress_is_all_day', true );

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 09:00:00',
				'datetime_end'   => '2026-08-29 17:00:00',
				'timezone'       => 'UTC',
			)
		);

		add_filter( 'gatherpress_timezone', $unusable );

		$formatted = ( new Event( $post->ID ) )->get_formatted_datetime( 'F j, Y', 'start' );

		remove_filter( 'gatherpress_timezone', $unusable );

		$this->assertSame(
			'August 29, 2026',
			$formatted,
			'Failed to assert the date survives a timezone that cannot be used.'
		);
	}

	/**
	 * An event says whether it names its timezone, or leaves it to the block.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_timezone_preference
	 *
	 * @return void
	 */
	public function test_get_timezone_preference(): void {
		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		$this->assertSame(
			'',
			( new Event( $post->ID ) )->get_timezone_preference(),
			'Failed to assert an event leaves the timezone to the block by default.'
		);

		foreach ( array( 'always', 'never' ) as $preference ) {
			update_post_meta( $post->ID, 'gatherpress_show_timezone', $preference );

			$this->assertSame(
				$preference,
				( new Event( $post->ID ) )->get_timezone_preference(),
				sprintf( 'Failed to assert an event can say %s.', $preference )
			);
		}

		// Anything else is not an answer, so the block decides.
		update_post_meta( $post->ID, 'gatherpress_show_timezone', 'sometimes' );

		$this->assertSame(
			'',
			( new Event( $post->ID ) )->get_timezone_preference(),
			'Failed to assert an unknown preference falls back to the block.'
		);
	}

	/**
	 * A post ID that is not an event has no preference.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_timezone_preference
	 *
	 * @return void
	 */
	public function test_get_timezone_preference_without_a_post(): void {
		$this->assertSame(
			'',
			( new Event( 0 ) )->get_timezone_preference(),
			'Failed to assert a missing post has no preference.'
		);
	}

	/**
	 * The event decides about its timezone before the block does.
	 *
	 * A block in a site template renders every event, so it cannot answer
	 * this per event -- and an event rendered by such a template has no block
	 * of its own to configure. Always and never overrule the block. Saying
	 * nothing leaves the block to it, all day or not.
	 *
	 * @since 0.36.0
	 *
	 * @dataProvider data_timezone_precedence
	 *
	 * @covers ::get_display_datetime
	 *
	 * @param bool   $all_day    Whether the event is all day.
	 * @param string $preference What the event says about its timezone.
	 * @param bool   $expects    Whether the timezone should be named.
	 * @param string $message    What the case is proving.
	 *
	 * @return void
	 */
	public function test_timezone_precedence(
		bool $all_day,
		string $preference,
		bool $expects,
		string $message
	): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'F j, Y',
				'time_format'   => 'g:i a',
				'show_timezone' => true,
			)
		);

		$post = $this->mock->post( array( 'post_type' => 'gatherpress_event' ) )->get();

		update_post_meta( $post->ID, 'gatherpress_is_all_day', $all_day );
		update_post_meta( $post->ID, 'gatherpress_show_timezone', $preference );

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'post_id'        => $post->ID,
				'datetime_start' => '2026-08-29 09:00:00',
				'datetime_end'   => '2026-08-29 17:00:00',
				'timezone'       => 'UTC',
			)
		);

		$this->assertSame(
			$expects,
			str_contains( ( new Event( $post->ID ) )->get_display_datetime(), 'UTC' ),
			$message
		);
	}

	/**
	 * Data provider for timezone precedence.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<int, bool|string>> Cases.
	 */
	public function data_timezone_precedence(): array {
		return array(
			'timed, nothing said'   => array(
				false,
				'',
				true,
				'A timed event should follow the site setting.',
			),
			'timed, never'          => array(
				false,
				'never',
				false,
				'A timed event that refuses should not name its timezone.',
			),
			'timed, always'         => array(
				false,
				'always',
				true,
				'A timed event that insists should name its timezone.',
			),
			'all day, nothing said' => array(
				true,
				'',
				true,
				'An all-day event that says nothing should leave it to the block.',
			),
			'all day, always'       => array(
				true,
				'always',
				true,
				'An all-day event that insists should name its timezone.',
			),
			'all day, never'        => array(
				true,
				'never',
				false,
				'An all-day event that refuses should not name its timezone.',
			),
		);
	}
}
