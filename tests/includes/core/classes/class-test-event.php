<?php
/**
 * Class handles unit tests for GatherPress\Core\Event.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Attendee;
use GatherPress\Core\Event;
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
		$this->assertNull( Utility::get_hidden_property( $event, 'attendee' ) );

		$post  = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$event = new Event( $post->ID );

		$this->assertInstanceOf( WP_Post::class, Utility::get_hidden_property( $event, 'event' ) );
		$this->assertInstanceOf( Attendee::class, Utility::get_hidden_property( $event, 'attendee' ) );
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

		$output = $event->save_datetimes( $params );

		$this->assertTrue( $output, 'Failed to assert that datetimes saved.' );
		$this->assertSame( $expects, $event->get_display_datetime() );
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
	 * Coverage for get_calendar_links method.
	 *
	 * @covers ::get_calendar_links
	 * @covers ::get_google_calendar_link
	 * @covers ::get_ics_calendar_download
	 * @covers ::get_yahoo_calendar_link
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
		$event       = new Event( $post->ID );
		$description = sanitize_text_field( sprintf( 'For details go to %s', get_the_permalink( $post ) ) );
		$params      = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-11 17:00:00',
		);

		$event->save_datetimes( $params );

		$output  = $event->get_calendar_links();
		$expects = array(
			'google'  => array(
				'name' => 'Google Calendar',
				'link' => 'https://www.google.com/calendar/event?action=TEMPLATE&text=Unit Test Event&dates=20200511T150000Z/20200511T170000Z&details=' . $description . '&location&sprop=name:',
			),
			'ical'    => array(
				'name'     => 'iCal',
				'download' => 'data:text/calendar;charset=utf8,BEGIN:VCALENDAR%0AVERSION:2.0%0APRODID:-//GatherPress//RemoteApi//EN%0ABEGIN:VEVENT%0AURL:' . home_url( '/' ) . '?gp_event=unit-test-event%0ADTSTART:20200511T150000Z%0ADTEND:20200511T170000Z%0ADTSTAMP:20200511T000000Z%0ASUMMARY:Unit Test Event%0ADESCRIPTION:' . $description . '%0ALOCATION:%0AUID:gatherpress_' . $post->ID . '%0AEND:VEVENT%0AEND:VCALENDAR',
			),
			'outlook' => array(
				'name'     => 'Outlook',
				'download' => 'data:text/calendar;charset=utf8,BEGIN:VCALENDAR%0AVERSION:2.0%0APRODID:-//GatherPress//RemoteApi//EN%0ABEGIN:VEVENT%0AURL:' . home_url( '/' ) . '?gp_event=unit-test-event%0ADTSTART:20200511T150000Z%0ADTEND:20200511T170000Z%0ADTSTAMP:20200511T000000Z%0ASUMMARY:Unit Test Event%0ADESCRIPTION:' . $description . '%0ALOCATION:%0AUID:gatherpress_' . $post->ID . '%0AEND:VEVENT%0AEND:VCALENDAR',
			),
			'yahoo'   => array(
				'name' => 'Yahoo Calendar',
				'link' => 'https://calendar.yahoo.com/?v=60&view=d&type=20&title=Unit Test Event&st=20200511T150000Z&dur=0200&desc=' . $description . '&in_loc',
			),
		);

		$this->assertSame( $expects, $output );
	}

	/**
	 * Cover for has_event_past method.
	 *
	 * @covers ::has_event_past
	 *
	 * @return void
	 */
	public function test_has_event_past(): void {
		$post   = $this->mock->post(
			array(
				'post_type' => 'gp_event',
			)
		)->get();
		$event  = new Event( $post->ID );
		$year   = gmdate( 'Y' );
		$params = array(
			'datetime_start' => sprintf( '%d-05-11 15:00:00', $year - 1 ),
			'datetime_end'   => sprintf( '%d-05-11 17:00:00', $year - 1 ),
		);

		$event->save_datetimes( $params );

		$output = $event->has_event_past();

		$this->assertTrue( $output );

		$params = array(
			'datetime_start' => sprintf( '%d-05-11 15:00:00', $year + 1 ),
			'datetime_end'   => sprintf( '%d-05-11 17:00:00', $year + 1 ),
		);

		$event->save_datetimes( $params );

		$output = $event->has_event_past();

		$this->assertFalse( $output );
	}

	/**
	 * Coverage for adjust_sql method.
	 *
	 * @covers ::adjust_sql
	 *
	 * @return void
	 */
	public function test_adjust_sql(): void {
		global $wpdb;

		$table  = sprintf( Event::TABLE_FORMAT, $wpdb->prefix, Event::POST_TYPE );
		$retval = Event::adjust_sql( array(), 'all', 'DESC' );

		$this->assertStringContainsString( 'DESC', $retval['orderby'] );
		$this->assertEmpty( $retval['where'] );

		$retval = Event::adjust_sql( array(), 'past', 'desc' );

		$this->assertStringContainsString( 'DESC', $retval['orderby'] );
		$this->assertStringContainsString( "AND {$table}.datetime_end_gmt <", $retval['where'] );

		$retval = Event::adjust_sql( array(), 'upcoming', 'ASC' );

		$this->assertStringContainsString( 'ASC', $retval['orderby'] );
		$this->assertStringContainsString( "AND {$table}.datetime_end_gmt >=", $retval['where'] );
	}

}
