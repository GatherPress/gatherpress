<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Calendar;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use WP_Block;

/**
 * Class Test_Calendar.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar
 */
class Test_Calendar extends Base {

	/**
	 * Use Monday as the start of the week, like the WordPress default.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		update_option( 'start_of_week', 1 );
	}

	/**
	 * Get the markup of a Query Loop with a calendar, like the Event Calendar pattern.
	 *
	 * @param string $calendar_attrs JSON attributes of the calendar block.
	 *
	 * @return string Block markup.
	 */
	public static function get_calendar_markup( string $calendar_attrs = '{"selectedDate":"2026-10-15"}' ): string {
		return '<!-- wp:query {"queryId":3,"query":{"postType":"gatherpress_event","perPage":10,"inherit":false,'
			. '"gatherpress_event_query":"all"}} --><div class="wp-block-query">'
			. '<!-- wp:gatherpress/calendar ' . $calendar_attrs . ' -->'
			. '<!-- wp:gatherpress/calendar-week -->'
			. '<!-- wp:gatherpress/calendar-day -->'
			. '<!-- wp:paragraph {"className":"gatherpress-calendar__day-number","metadata":{"bindings":'
			. '{"content":{"source":"gatherpress/calendar-day"}}}} --><p class="gatherpress-calendar__day-number"></p>'
			. '<!-- /wp:paragraph -->'
			. '<!-- wp:gatherpress/calendar-entries --><!-- wp:post-title {"isLink":true} /-->'
			. '<!-- /wp:gatherpress/calendar-entries -->'
			. '<!-- /wp:gatherpress/calendar-day -->'
			. '<!-- /wp:gatherpress/calendar-week -->'
			. '<!-- /wp:gatherpress/calendar -->'
			. '</div><!-- /wp:query -->';
	}

	/**
	 * Create an event with the given UTC datetimes.
	 *
	 * @param string $title Event title.
	 * @param string $start Start datetime (Y-m-d H:i:s).
	 * @param string $end   End datetime (Y-m-d H:i:s).
	 *
	 * @return int Event post ID.
	 */
	public function create_event( string $title, string $start, string $end ): int {
		$post = $this->mock->post(
			array(
				'post_title' => $title,
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'UTC',
			)
		);

		return $post->ID;
	}

	/**
	 * A calendar in a Query Loop renders a month table with the events of the month.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_month(): void {
		$this->create_event( 'Calendar Smoke Test Event', '2026-10-15 10:00:00', '2026-10-15 12:00:00' );

		$output = do_blocks( self::get_calendar_markup() );

		$this->assertStringContainsString( 'wp-block-gatherpress-calendar', $output, 'Failed to assert the calendar wrapper.' );
		$this->assertStringContainsString( '<caption class="gatherpress--screen-reader-text">October 2026</caption>', $output );
		$this->assertStringContainsString( 'data-wp-key="day-2026-10-15"', $output, 'Failed to assert the day cell.' );
		$this->assertStringContainsString( 'Calendar Smoke Test Event', $output, 'Failed to assert the event entry.' );
		$this->assertStringContainsString( 'gatherpress-calendar__entry', $output );
	}

	/**
	 * A calendar outside a Query Loop renders nothing.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_outside_query_loop(): void {
		$block = new WP_Block(
			array(
				'blockName' => Calendar::BLOCK_NAME,
				'attrs'     => array(),
			)
		);

		$this->assertSame( '', Calendar::render( array(), $block ) );
	}
}
