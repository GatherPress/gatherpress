<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Add_To_Calendar.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Add_To_Calendar;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PasswordHash;

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
	 * @since 0.33.0
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
		$instance = Add_To_Calendar::get_instance();
		$venue    = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Unit Test Venue',
				'post_name'  => 'unit-test-venue',
			)
		)->get();
		$post     = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
				'post_date'    => '2020-05-11 00:00:00',
			)
		)->get();
		$event    = new Event( $post->ID );
		$params   = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-11 17:00:00',
		);

		update_post_meta( $venue->ID, 'gatherpress_address', '123 Main Street, Montclair, NJ 07042' );
		update_post_meta( $venue->ID, 'gatherpress_phone', '(123) 123-1234' );
		update_post_meta( $venue->ID, 'gatherpress_website', 'https://gatherpress.org/' );
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
			'gatherpress_calendar=google-calendar',
			$result,
			'Generated calendar link content is missing the google-calendar endpoint query var.'
		);
		$this->assertStringContainsString(
			'gatherpress_calendar=ical',
			$result,
			'Generated calendar link content is missing the iCal endpoint query var.'
		);
		$this->assertStringContainsString(
			'gatherpress_calendar=outlook',
			$result,
			'Generated calendar link content is missing the Outlook endpoint query var.'
		);
		$this->assertStringContainsString(
			'gatherpress_calendar=yahoo-calendar',
			$result,
			'Generated calendar link content is missing the yahoo-calendar endpoint query var.'
		);
		$this->assertStringContainsString(
			'gatherpress_event=unit-test-event',
			$result,
			'Generated calendar link content is missing the event slug query var.'
		);
	}

	/**
	 * Test replace_calendar_placeholders with non-event post.
	 *
	 * Verifies that the method returns an empty string when called
	 * on a post that is not an event post type.
	 *
	 * @covers ::replace_calendar_placeholders
	 *
	 * @return void
	 */
	public function test_replace_calendar_placeholders_with_non_event_post(): void {
		$instance = Add_To_Calendar::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type' => 'post',
			)
		)->get();

		$block_content = '
			<div class="wp-block-gatherpress-add-to-calendar">
				<a href="#gatherpress-google-calendar">Google Calendar</a>
				<a href="#gatherpress-ical-calendar">iCal</a>
			</div>
		';
		$block         = array(
			'blockName' => 'gatherpress/add-to-calendar',
			'attrs'     => array(
				'postId' => $post->ID,
			),
		);

		$result = $instance->replace_calendar_placeholders( $block_content, $block );

		// Tests: return ''; (when post is not an event).
		$this->assertSame(
			'',
			$result,
			'Should return empty string for non-event post.'
		);
	}

	/**
	 * Tests replace_calendar_placeholders keeps a password-protected event's links behind its password.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::replace_calendar_placeholders
	 *
	 * @return void
	 */
	public function test_replace_calendar_placeholders_with_password_protected_event(): void {
		$instance = Add_To_Calendar::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type'     => Event::POST_TYPE,
				'post_password' => 'secret',
			)
		)->get();

		$block_content = '<div class="wp-block-gatherpress-add-to-calendar">'
			. '<a href="#gatherpress-google-calendar">Google Calendar</a></div>';
		$block         = array(
			'blockName' => 'gatherpress/add-to-calendar',
			'attrs'     => array(
				'postId' => $post->ID,
			),
		);

		$this->assertSame(
			'',
			$instance->replace_calendar_placeholders( $block_content, $block ),
			'A password-protected event should keep its calendar links from a viewer without the password.'
		);

		require_once ABSPATH . WPINC . '/class-phpass.php';

		// The cookie WordPress sets once a viewer enters the post password.
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = ( new PasswordHash( 8, true ) )->HashPassword( 'secret' );

		$result = $instance->replace_calendar_placeholders( $block_content, $block );

		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		$this->assertStringContainsString(
			'gatherpress_calendar=google-calendar',
			$result,
			'A password-protected event should show its calendar links to a viewer with the password.'
		);
	}
}
