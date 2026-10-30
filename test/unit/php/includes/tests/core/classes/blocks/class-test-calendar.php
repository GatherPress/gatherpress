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
use PMC\Unit_Test\Utility;
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
	 * @param string $query_extra    More JSON members of the query attribute, each with a leading comma.
	 *
	 * @return string Block markup.
	 */
	public static function get_calendar_markup(
		string $calendar_attrs = '{"selectedDate":"2026-10-15"}',
		string $query_extra = ''
	): string {
		return '<!-- wp:query {"queryId":3,"query":{"postType":"gatherpress_event","perPage":10,"inherit":false,'
			. '"gatherpress_event_query":"all"' . $query_extra . '}} --><div class="wp-block-query">'
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

		$this->assertStringContainsString(
			'wp-block-gatherpress-calendar',
			$output,
			'Failed to assert the calendar wrapper.'
		);
		$this->assertStringContainsString(
			'<caption class="gatherpress--screen-reader-text">October 2026</caption>',
			$output
		);
		$this->assertStringContainsString( 'data-wp-key="day-2026-10-15"', $output, 'Failed to assert the day cell.' );
		$this->assertStringContainsString( 'Calendar Smoke Test Event', $output, 'Failed to assert the event entry.' );
		$this->assertStringContainsString( 'gatherpress-calendar__entry', $output );
	}

	/**
	 * The week markup is escaped, with the allowlist widened only while the table renders.
	 *
	 * @covers ::render
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::render_unit_table
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::allow_block_markup
	 *
	 * @return void
	 */
	public function test_render_escapes_the_weeks(): void {
		$this->create_event( 'Escaped Entry', '2026-10-15 10:00:00', '2026-10-15 12:00:00' );

		$markup = str_replace(
			'<!-- wp:post-title {"isLink":true} /-->',
			'<!-- wp:html --><div role="dialog" aria-modal="true"><script>alert(1)</script>'
				. '<input type="text" name="field" onfocus="alert(2)"></div><!-- /wp:html -->',
			self::get_calendar_markup()
		);

		$output = do_blocks( $markup );

		$this->assertStringContainsString( 'aria-modal="true"', $output, 'Failed to assert aria-modal is kept.' );
		$this->assertStringContainsString( '<input type="text" name="field">', $output );
		$this->assertStringNotContainsString( '<script>', $output, 'Failed to assert a script is removed.' );
		$this->assertStringNotContainsString( 'onfocus', $output, 'Failed to assert an event handler is removed.' );
		$this->assertArrayNotHasKey(
			'aria-modal',
			wp_kses_allowed_html( 'post' )['div'],
			'Failed to assert the allowlist is back to normal after the render.'
		);
	}

	/**
	 * A two-week view gets week numbers; weekdays can be hidden on screen.
	 *
	 * @covers ::render
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::generate_calendar_html
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::render_unit_table
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::render_header_cells
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::render_calendar_weeks
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::build_wrapper_attributes
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::build_table_style
	 *
	 * @return void
	 */
	public function test_render_two_weeks(): void {
		$output = do_blocks(
			self::get_calendar_markup(
				'{"viewType":"week","unitCount":2,"selectedDate":"2026-10-29","showWeekdays":false}'
			)
		);

		$this->assertStringContainsString( 'is-view-week', $output );
		$this->assertStringContainsString( 'has-multiple-units', $output );
		$this->assertStringContainsString( '<thead class="gatherpress--screen-reader-text">', $output );
		$this->assertStringContainsString(
			'<th scope="col" class="gatherpress--screen-reader-text">Week</th>',
			$output
		);
		$this->assertStringContainsString( 'Week 44</th>', $output );
		$this->assertStringContainsString( 'Week 45</th>', $output );
		$this->assertStringContainsString( 'data-wp-key="calendar-2026-10-26"', $output );
		$this->assertStringContainsString( '--gatherpress-calendar-columns: 7', $output );
	}

	/**
	 * A day view has no week numbers, and the gap attribute styles the table.
	 *
	 * @covers ::render
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::generate_calendar_html
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::build_table_style
	 *
	 * @return void
	 */
	public function test_render_days_with_gap(): void {
		$output = do_blocks(
			self::get_calendar_markup(
				'{"viewType":"day","unitCount":3,"selectedDate":"2026-10-15",'
					. '"style":{"spacing":{"blockGap":{"top":"4px","left":"var:preset|spacing|20"}}}}'
			)
		);

		$this->assertStringContainsString( 'is-view-day', $output );
		$this->assertStringNotContainsString( 'Week', $output );
		$this->assertStringContainsString(
			'<th scope="col">Thu</th><th scope="col">Fri</th><th scope="col">Sat</th>',
			$output
		);
		$this->assertStringContainsString( 'gap: 4px var(--wp--preset--spacing--20)', $output );
		$this->assertStringContainsString(
			'--gatherpress-calendar-column-gap: var(--wp--preset--spacing--20)',
			$output
		);
		$this->assertStringContainsString( '--gatherpress-calendar-columns: 3', $output );
	}

	/**
	 * A week view sorted by event date can show the time grid.
	 *
	 * @covers ::render
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::render_header_cells
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::build_table_style
	 *
	 * @return void
	 */
	public function test_render_time_grid(): void {
		$output = do_blocks(
			self::get_calendar_markup(
				'{"viewType":"week","selectedDate":"2026-10-15","enableTimeGrid":true}',
				',"orderBy":"datetime"'
			)
		);

		$this->assertStringContainsString( 'has-time-grid', $output );
		$this->assertStringContainsString( 'gatherpress-calendar__time-gutter-header', $output );
		$this->assertStringContainsString( '--gatherpress-total-quarter-slots: 48', $output );
		$this->assertStringContainsString(
			'--gatherpress-calendar-columns: 8',
			$output,
			'Failed to assert the gutter column.'
		);
		$this->assertStringContainsString(
			'--gatherpress-calendar-slot-height: 48px',
			$output,
			'Failed to assert the default slot height.'
		);
		$this->assertMatchesRegularExpression(
			'#<td [^>]*class="gatherpress-calendar__day [^"]*is-style-ribbon#',
			$output,
			'Failed to assert a Classic day in a time grid gets the Ribbon class, as in the editor.'
		);
	}

	/**
	 * The time grid takes the slot height of the day block, within the range of its control.
	 *
	 * @covers ::render
	 * @covers ::get_slot_height
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::build_table_style
	 * @covers \GatherPress\Core\Blocks\Calendar_Day::get_time_grid_classes
	 *
	 * @return void
	 */
	public function test_render_time_grid_slot_height(): void {
		$markup   = self::get_calendar_markup(
			'{"viewType":"week","selectedDate":"2026-10-15","enableTimeGrid":true}',
			',"orderBy":"datetime"'
		);
		$with_day = static function ( string $day_attrs ) use ( $markup ): string {
			return do_blocks(
				str_replace(
					'<!-- wp:gatherpress/calendar-day -->',
					'<!-- wp:gatherpress/calendar-day ' . $day_attrs . ' -->',
					$markup
				)
			);
		};

		$this->assertStringContainsString(
			'--gatherpress-calendar-slot-height: 64px',
			$with_day( '{"slotHeight":64}' )
		);
		$this->assertStringContainsString(
			'--gatherpress-calendar-slot-height: 120px',
			$with_day( '{"slotHeight":500}' ),
			'Failed to assert the largest slot height of the control.'
		);

		$output = $with_day( '{"className":"is-style-ribbon"}' );
		$this->assertStringContainsString( 'is-style-ribbon', $output );
		$this->assertDoesNotMatchRegularExpression(
			'#is-style-ribbon[^"]*is-style-ribbon#',
			$output,
			'Failed to assert a Ribbon day gets the class once.'
		);

		$calendar = new Calendar( array(), new WP_Block( array( 'blockName' => Calendar::BLOCK_NAME ) ) );

		$this->assertSame(
			48,
			Utility::invoke_hidden_method( $calendar, 'get_slot_height', array( array() ) ),
			'Failed to assert the default without a day block.'
		);

		$week = array(
			'innerBlocks' => array(
				'not a block',
				array( 'blockName' => 'core/paragraph' ),
				array(
					'blockName' => 'gatherpress/calendar-day',
					'attrs'     => array( 'slotHeight' => 'tall' ),
				),
			),
		);
		$this->assertSame(
			48,
			Utility::invoke_hidden_method( $calendar, 'get_slot_height', array( $week ) ),
			'Failed to assert other blocks are skipped and a height that is not a number gives the default.'
		);
	}

	/**
	 * Each entry template renders once: WordPress does not render the calendar templates first.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_entry_template_renders_once_per_entry(): void {
		$this->create_event( 'Rendered Once', '2026-10-15 10:00:00', '2026-10-15 12:00:00' );

		$count    = 0;
		$callback = static function () use ( &$count ) {
			++$count;
		};

		add_action( 'gatherpress_calendar_entry_render', $callback );
		do_blocks( self::get_calendar_markup() );
		remove_action( 'gatherpress_calendar_entry_render', $callback );

		$this->assertSame( 1, $count, 'Failed to assert one render for one entry.' );
	}

	/**
	 * Two months without weekends render two tables of five columns.
	 *
	 * @covers ::render
	 * @covers \GatherPress\Core\Blocks\Calendar\Html_Renderer::generate_calendar_html
	 *
	 * @return void
	 */
	public function test_render_two_months_without_weekends(): void {
		$output = do_blocks(
			self::get_calendar_markup( '{"unitCount":2,"selectedDate":"2026-10-15","showWeekends":false}' )
		);

		$this->assertSame( 2, substr_count( $output, '<table ' ) );
		$this->assertStringContainsString(
			'<caption class="gatherpress--screen-reader-text">November 2026</caption>',
			$output
		);
		$this->assertStringContainsString( '--gatherpress-calendar-columns: 5', $output );
		$this->assertStringNotContainsString( '<th scope="col">Sat</th>', $output );
	}

	/**
	 * A calendar outside a Query Loop renders nothing.
	 *
	 * @covers ::__construct
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

		$this->assertSame( '', ( new Calendar( array(), $block ) )->render() );
	}
}
