<?php
/**
 * Class handles unit tests for GatherPress\Core\Event.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use DateTime;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Venue;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;
use WP_Post;

/**
 * Class Test_Event.
 *
 * @coversDefaultClass \GatherPress\Core\Event
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
	 * Coverage for get_post_type_registration_args method.
	 *
	 * @covers ::get_post_type_registration_args
	 *
	 * @return void
	 */
	public function test_get_post_type_registration_args(): void {
		$args = Event::get_post_type_registration_args();

		$this->assertIsArray( $args['labels'], 'Failed to assert that labels are an array.' );
		$this->assertTrue( $args['show_in_rest'], 'Failed to assert that show_in_rest is true.' );
		$this->assertTrue( $args['public'], 'Failed to assert that public is true.' );
		$this->assertSame( 'dashicons-nametag', $args['menu_icon'], 'Failed to assert that menu_icon is nametag.' );
		$this->assertSame( 'event', $args['rewrite']['slug'], 'Failed to assert that slug is events.' );
	}

	/**
	 * Coverage for get_post_meta_registration_args method.
	 *
	 * @covers ::get_post_meta_registration_args
	 *
	 * @return void
	 */
	public function test_get_post_meta_registration_args(): void {
		$args = Event::get_post_meta_registration_args();

		$this->assertIsArray(
			$args['_online_event_link'],
			'Failed to assert that _online_event_link is an array.'
		);

		$this->mock->user( 'subscriber' );
		$this->assertFalse(
			$args['_online_event_link']['auth_callback'](),
			'Failed to assert that user cannot edit posts.'
		);

		$this->mock->user( 'admin' );
		$this->assertTrue(
			$args['_online_event_link']['auth_callback'](),
			'Failed to assert that user can edit posts.'
		);
	}

	/**
	 * Coverage for get_taxonomy_registration_args method.
	 *
	 * @covers ::get_taxonomy_registration_args
	 *
	 * @return void
	 */
	public function test_get_taxonomy_registration_args(): void {
		$args = Event::get_taxonomy_registration_args();

		$this->assertIsArray( $args['labels'], 'Failed to assert that labels are an array.' );
		$this->assertTrue( $args['public'], 'Failed to assert that public is true.' );
		$this->assertTrue( $args['show_ui'], 'Failed to assert that show_ui is true.' );
		$this->assertTrue( $args['hierarchical'], 'Failed to assert that hierarchical is true.' );
		$this->assertSame( 'topic', $args['rewrite']['slug'], 'Failed to assert that slug is topic.' );
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
				'expects' => 'Monday, May 11, 2020, 3:00 PM to Tuesday, May 12, 2020, 5:00 PM EDT',
			),
			array(
				'params'  => array(),
				'expects' => '—',
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
	 * @covers ::save_datetimes
	 * @covers ::is_same_date
	 * @covers ::get_gmt_datetime
	 *
	 * @return void
	 */
	public function test_get_display_datetime( array $params, string $expects ): void {
		$post  = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gp_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event = new Event( $post->ID );

		if ( ! empty( $params ) ) {
			$output = $event->save_datetimes( $params );

			$this->assertTrue( $output, 'Failed to assert that datetimes saved.' );
		}

		$this->assertSame(
			$expects,
			$event->get_display_datetime(),
			'Failed to assert display date times match.'
		);
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
				'post_type'    => 'gp_event',
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

		Utility::set_and_get_hidden_property( $event, 'event', (object) array( 'ID' => 0 ) );

		$this->assertFalse(
			$event->save_datetimes( $params ),
			'Failed to assert false due to post ID less than 1.'
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
				'post_type'    => 'gp_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event = new Event( $post->ID );

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
				'post_type'    => 'gp_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event    = new Event( $post->ID );
		$timezone = new DateTimeZone( 'America/New_York' );
		$this->assertSame(
			'0000-00-00 00:00:00',
			Utility::invoke_hidden_method( $event, 'get_gmt_datetime', array( 'unit-test', $timezone ) ),
			'Failed to assert that gmt datetime matches.'
		);
	}

	/**
	 * Data provider for maybe_convert_offset test.
	 *
	 * @return array
	 */
	public function data_maybe_convert_offset(): array {
		return array(
			array(
				'America/New_York',
				'America/New_York',
			),
			array(
				'UTC',
				'UTC',
			),
			array(
				'UTC+9.5',
				'+09:30',
			),
			array(
				'UTC-7.25',
				'-07:15',
			),
			array(
				'UTC-5.75',
				'-05:45',
			),
			array(
				'UTC+1',
				'+01:00',
			),
		);
	}

	/**
	 * Coverage for maybe_convert_offset method.
	 *
	 * @dataProvider data_maybe_convert_offset
	 *
	 * @covers ::maybe_convert_offset
	 *
	 * @param string $input   Value to pass to method.
	 * @param string $expects Expected response.
	 *
	 * @return void
	 */
	public function test_maybe_convert_offset( $input, $expects ): void {
		$this->assertSame(
			$expects,
			Event::maybe_convert_offset( $input ),
			'Failed to assert that conversion matches.'
		);
	}

	/**
	 * Coverage for list_identifiers method.
	 *
	 * @covers ::list_identifiers
	 *
	 * @return void
	 */
	public function test_list_identifiers(): void {
		$list      = Event::list_identifiers();
		$timezones = array(
			'America/Belem',
			'Asia/Chita',
			'Europe/Vilnius',
			'UTC',
			'-12:00',
			'-00:30',
			'+09:30',
			'+13:45',
		);
		foreach ( $timezones as $timezone ) {
			$this->assertContains( $timezone, $list, 'Failed to assert timezone is in list.' );
		}
	}

	/**
	 * Coverage for get_venue_information method.
	 *
	 * @covers ::get_venue_information
	 *
	 * @return void
	 */
	public function test_get_venue_information(): void {
		$venue      = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Unit Test Venue',
				'post_name'  => 'unit-test-venue',
			)
		)->get();
		$event_id   = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get()->ID;
		$event      = new Event( $event_id );
		$venue_info = '{"fullAddress":"123 Main Street, Montclair, NJ 07042","phoneNumber":"(123) 123-1234","website":"https://gatherpress.org/"}';

		update_post_meta( $venue->ID, '_venue_information', $venue_info );
		wp_set_post_terms( $event_id, '_unit-test-venue', Venue::TAXONOMY );

		$response = $event->get_venue_information();

		$this->assertSame(
			'Unit Test Venue',
			$response['name'],
			'Failed to assert that name matches.'
		);
		$this->assertSame(
			'123 Main Street, Montclair, NJ 07042',
			$response['full_address'],
			'Failed to assert that full address matches.'
		);
		$this->assertSame(
			'(123) 123-1234',
			$response['phone_number'],
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
		$this->assertEmpty(
			$response['is_online_event'],
			'Failed to assert that is online event is false.'
		);

		wp_set_post_terms( $event_id, 'Online event', Venue::TAXONOMY );

		$response = $event->get_venue_information();

		$this->assertSame(
			'Online event',
			$response['name'],
			'Failed to assert that name matches.'
		);

		$this->assertEmpty(
			$response['full_address'],
			'Failed to assert that full address is empty.'
		);

		$this->assertEmpty(
			$response['phone_number'],
			'Failed to assert that phone number is empty.'
		);

		$this->assertEmpty(
			$response['website'],
			'Failed to assert that website is empty.'
		);

		$this->assertTrue(
			$response['is_online_event'],
			'Failed to assert that is online event is true.'
		);
	}

	/**
	 * Coverage for get_calendar_links method.
	 *
	 * @covers ::get_calendar_links
	 * @covers ::get_google_calendar_link
	 * @covers ::get_ics_calendar_download
	 * @covers ::get_yahoo_calendar_link
	 * @covers ::get_calendar_description
	 *
	 * @return void
	 */
	public function test_get_calendar_links(): void {
		$post        = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gp_event',
				'post_content' => 'Unit Test description.',
				'post_date'    => '2020-05-11 00:00:00',
			)
		)->get();
		$venue       = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Unit Test Venue',
				'post_name'  => 'unit-test-venue',
			)
		)->get();
		$venue_info  = '{"fullAddress":"123 Main Street, Montclair, NJ 07042","phoneNumber":"(123) 123-1234","website":"https://gatherpress.org/"}';
		$event       = new Event( $post->ID );
		$description = sanitize_text_field( sprintf( 'For details go to %s', get_the_permalink( $post ) ) );
		$params      = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-11 17:00:00',
		);

		update_post_meta( $venue->ID, '_venue_information', $venue_info );
		wp_set_post_terms( $post->ID, '_unit-test-venue', Venue::TAXONOMY );

		$event->save_datetimes( $params );

		$output  = $event->get_calendar_links();
		$expects = array(
			'google'  => array(
				'name' => 'Google Calendar',
				'link' => 'https://www.google.com/calendar/event?action=TEMPLATE&text=Unit Test Event&dates=20200511T150000Z/20200511T170000Z&details=' . $description . '&location=Unit Test Venue, 123 Main Street, Montclair, NJ 07042&sprop=name:',
			),
			'ical'    => array(
				'name'     => 'iCal',
				'download' => 'data:text/calendar;charset=utf8,BEGIN:VCALENDAR%0AVERSION:2.0%0APRODID:-//GatherPress//RemoteApi//EN%0ABEGIN:VEVENT%0AURL:' . home_url( '/' ) . '?gp_event=unit-test-event%0ADTSTART:20200511T150000Z%0ADTEND:20200511T170000Z%0ADTSTAMP:20200511T000000Z%0ASUMMARY:Unit Test Event%0ADESCRIPTION:' . $description . '%0ALOCATION:Unit Test Venue, 123 Main Street, Montclair, NJ 07042%0AUID:gatherpress_' . $post->ID . '%0AEND:VEVENT%0AEND:VCALENDAR',
			),
			'outlook' => array(
				'name'     => 'Outlook',
				'download' => 'data:text/calendar;charset=utf8,BEGIN:VCALENDAR%0AVERSION:2.0%0APRODID:-//GatherPress//RemoteApi//EN%0ABEGIN:VEVENT%0AURL:' . home_url( '/' ) . '?gp_event=unit-test-event%0ADTSTART:20200511T150000Z%0ADTEND:20200511T170000Z%0ADTSTAMP:20200511T000000Z%0ASUMMARY:Unit Test Event%0ADESCRIPTION:' . $description . '%0ALOCATION:Unit Test Venue, 123 Main Street, Montclair, NJ 07042%0AUID:gatherpress_' . $post->ID . '%0AEND:VEVENT%0AEND:VCALENDAR',
			),
			'yahoo'   => array(
				'name' => 'Yahoo Calendar',
				'link' => 'https://calendar.yahoo.com/?v=60&view=d&type=20&title=Unit Test Event&st=20200511T150000Z&dur=0200&desc=' . $description . '&in_loc=Unit Test Venue, 123 Main Street, Montclair, NJ 07042',
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
				'post_type' => 'gp_event',
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
				'post_type' => 'gp_event',
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
				'post_type' => 'gp_event',
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
				'post_type' => 'gp_event',
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

		update_post_meta( $event_id, '_online_event_link', $link );

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

		Utility::set_and_get_hidden_property( $event, 'rsvp', null );

		$this->assertEmpty(
			$event->maybe_get_online_event_link(),
			'Failed to assert empty string due to RSVP being set to null.'
		);
	}
}
