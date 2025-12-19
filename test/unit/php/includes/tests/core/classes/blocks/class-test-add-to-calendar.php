<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Add_To_Calendar.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Add_To_Calendar;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Add_To_Calendar.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Add_To_Calendar
 */
class Test_Add_To_Calendar extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Add_To_Calendar block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Add_To_Calendar::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Add_To_Calendar::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'replace_calendar_placeholders' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test replace_calendar_placeholders with all calendar links present.
	 *
	 * @covers ::replace_calendar_placeholders
	 *
	 * @return void
	 */
	public function test_replace_calendar_placeholders_with_all_links(): void {
		$instance   = Add_To_Calendar::get_instance();
		$venue      = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Unit Test Venue',
				'post_name'  => 'unit-test-venue',
			)
		)->get();
		$post       = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
				'post_date'    => '2020-05-11 00:00:00',
			)
		)->get();
		$venue_info = '{"fullAddress":"123 Main Street, Montclair, NJ 07042",' .
			'"phoneNumber":"(123) 123-1234","website":"https://gatherpress.org/"}';
		$event      = new Event( $post->ID );
		$params     = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-11 17:00:00',
		);

		update_post_meta( $venue->ID, 'gatherpress_venue_information', $venue_info );
		wp_set_post_terms( $post->ID, '_unit-test-venue', Venue::TAXONOMY );

		$event->save_datetimes( $params );

		$block_content = '
			<div class="wp-block-gatherpress-add-to-calendar">
				<a href="#gatherpress-google-calendar">Google Calendar</a>
				<a href="#gatherpress-ical-calendar">iCal</a>
				<a href="#gatherpress-outlook-calendar">Outlook</a>
				<a href="#gatherpress-yahoo-calendar">Yahoo Calendar</a>
			</div>
		';
		$block         = array( 'blockName' => 'gatherpress/add-to-calendar' );
		$result        = $instance->replace_calendar_placeholders( $block_content, $block );

		$this->assertStringContainsString(
			'google.com',
			$result,
			"Generated calendar link content is missing expected Google Calendar URL component 'google.com'"
		);

		$this->assertStringContainsString(
			'unit-test-event.ics',
			$result,
			"Generated calendar link content is missing expected iCal file name 'unit-test-event.ics'"
		);

		$this->assertStringContainsString(
			'calendar.yahoo.com',
			$result,
			"Generated calendar link content is missing expected Yahoo Calendar URL component 'calendar.yahoo.com'"
		);
	}
}
