<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar_Day.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Calendar_Day;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;

/**
 * Class Test_Calendar_Day.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar_Day
 */
class Test_Calendar_Day extends Base {

	/**
	 * Day template with a bound day number and an entries block.
	 *
	 * @var string
	 */
	const TEMPLATE = '<!-- wp:gatherpress/calendar-day -->'
		. '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"gatherpress/calendar-day"}}}} -->'
		. '<p></p><!-- /wp:paragraph -->'
		. '<!-- wp:gatherpress/calendar-entries --><!-- wp:post-title /--><!-- /wp:gatherpress/calendar-entries -->'
		. '<!-- /wp:gatherpress/calendar-day -->';

	/**
	 * Build a day block with the given context.
	 *
	 * @param array<string, mixed> $context  Day context.
	 * @param string               $template Block markup of the day.
	 *
	 * @return WP_Block
	 */
	private function get_block( array $context, string $template = self::TEMPLATE ): WP_Block {
		$block          = new WP_Block( parse_blocks( $template )[0] );
		$block->context = $context;

		return $block;
	}

	/**
	 * Render a day block with the given context.
	 *
	 * @param array<string, mixed> $context  Day context.
	 * @param string               $template Block markup of the day.
	 *
	 * @return string
	 */
	private function render_day( array $context, string $template = self::TEMPLATE ): string {
		return ( new Calendar_Day( $this->get_block( $context, $template ) ) )->render();
	}

	/**
	 * A day block outside a calendar week renders nothing.
	 *
	 * @covers ::__construct
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_outside_week(): void {
		$this->assertSame( '', $this->render_day( array() ) );
	}

	/**
	 * An empty padding cell has no content and no interactivity.
	 *
	 * @covers ::render
	 * @covers ::build_cell_attributes
	 * @covers ::get_day_cell_classes
	 *
	 * @return void
	 */
	public function test_render_empty_cell(): void {
		$output = $this->render_day(
			array(
				'gatherpress/isEmpty' => true,
				'gatherpress/weekday' => 'monday',
			)
		);

		$this->assertStringStartsWith( '<td role="cell" ', $output );
		$this->assertStringContainsString( 'gatherpress--is-empty', $output );
		$this->assertStringContainsString( 'gatherpress--is-monday', $output );
		$this->assertStringContainsString( 'data-wp-key="empty-monday"', $output );
		$this->assertStringNotContainsString( 'data-wp-interactive', $output );
		$this->assertStringEndsWith( '></td>', $output, 'Failed to assert an empty cell has no content.' );

		$output = $this->render_day( array( 'gatherpress/isEmpty' => true ) );

		$this->assertMatchesRegularExpression(
			'/data-wp-key="empty-[0-9a-f]{13}"/',
			$output,
			'Failed to assert a unique key.'
		);
	}

	/**
	 * A past day shows its number and gets the interactivity directives.
	 *
	 * @covers ::render
	 * @covers ::build_cell_attributes
	 * @covers ::get_day_cell_classes
	 * @covers ::resolve_weekday_and_weekend
	 * @covers ::get_temporal_classes
	 * @covers ::find_day_inner_blocks
	 * @covers ::is_day_number_block
	 * @covers ::render_day_cell_content
	 *
	 * @return void
	 */
	public function test_render_past_day(): void {
		$output = $this->render_day(
			array(
				'gatherpress/isEmpty'   => false,
				'gatherpress/dayNumber' => 15,
				'gatherpress/dayDate'   => '2020-01-15',
				'gatherpress/weekday'   => 'wednesday',
			)
		);

		$this->assertStringContainsString( 'gatherpress-calendar__day', $output );
		$this->assertStringContainsString( 'gatherpress--is-wednesday', $output );
		$this->assertStringContainsString( 'gatherpress--is-past', $output );
		$this->assertStringContainsString( 'data-wp-key="day-2020-01-15"', $output );
		$this->assertStringContainsString( 'data-wp-interactive="gatherpress/calendar-day"', $output );
		$this->assertStringContainsString( 'data-wp-context="{&quot;date&quot;:&quot;2020-01-15&quot;}"', $output );
		$this->assertStringContainsString( 'data-wp-bind--aria-current="state.ariaCurrent"', $output );
		$this->assertStringNotContainsString( 'aria-current="date"', $output );
		$this->assertStringContainsString( '>15</p>', $output, 'Failed to assert the bound day number.' );
		$this->assertStringNotContainsString( 'gatherpress--has-posts', $output );
	}

	/**
	 * Today gets aria-current, and a day without a weekday gets it from its date.
	 *
	 * @covers ::render
	 * @covers ::build_cell_attributes
	 * @covers ::resolve_weekday_and_weekend
	 * @covers ::get_temporal_classes
	 *
	 * @return void
	 */
	public function test_render_today(): void {
		$today  = wp_date( 'Y-m-d' );
		$output = $this->render_day(
			array(
				'gatherpress/isEmpty'   => false,
				'gatherpress/dayNumber' => (int) wp_date( 'j' ),
				'gatherpress/dayDate'   => $today,
			)
		);

		$this->assertStringContainsString( 'aria-current="date"', $output );
		$this->assertStringContainsString( 'gatherpress--is-today', $output );
		$this->assertStringContainsString(
			'is-' . strtolower( gmdate( 'l', (int) strtotime( $today ) ) ),
			$output,
			'Failed to assert the weekday from the date.'
		);
	}

	/**
	 * The server render keeps the today and past markers when it processes the cell directives.
	 *
	 * @covers ::build_cell_attributes
	 * @covers ::register_day_state
	 * @covers ::compare_context_date
	 *
	 * @return void
	 */
	public function test_server_directives_keep_day_markers(): void {
		$cell = function ( string $date ): string {
			return wp_interactivity_process_directives(
				$this->render_day(
					array(
						'gatherpress/isEmpty'   => false,
						'gatherpress/dayNumber' => 1,
						'gatherpress/dayDate'   => $date,
					)
				)
			);
		};

		$today = $cell( wp_date( 'Y-m-d' ) );
		$this->assertMatchesRegularExpression( '#<td [^>]*class="[^"]*\bis-today\b#', $today );
		$this->assertStringContainsString(
			'aria-current="date"',
			$today,
			'Failed to assert today keeps aria-current.'
		);

		// For aria-* attributes the processor writes false as "false", as the client does.
		$past = $cell( '2020-01-15' );
		$this->assertMatchesRegularExpression( '#<td [^>]*class="[^"]*\bis-past\b#', $past );
		$this->assertStringContainsString( 'aria-current="false"', $past );

		$this->assertMatchesRegularExpression( '#<td [^>]*class="[^"]*\bis-future\b#', $cell( '2099-10-17' ) );

		// An element in the day namespace without a date is not today.
		$this->assertDoesNotMatchRegularExpression(
			'#class="[^"]*gatherpress--is-today#',
			wp_interactivity_process_directives(
				'<div data-wp-interactive="gatherpress/calendar-day" '
					. 'data-wp-class--gatherpress--is-today="state.isToday" class="gatherpress--is-today"></div>'
			),
			'Failed to assert no comparison without a date.'
		);
	}

	/**
	 * A day in a time grid gets the Ribbon class, once, as in the editor.
	 *
	 * @covers ::render
	 * @covers ::get_time_grid_classes
	 *
	 * @return void
	 */
	public function test_time_grid_day_gets_ribbon_class(): void {
		$context = array(
			'gatherpress/isEmpty'        => false,
			'gatherpress/dayNumber'      => 15,
			'gatherpress/dayDate'        => '2020-01-15',
			'gatherpress/timeGridConfig' => new Time_Grid( true ),
		);

		// A day whose author picked the Ribbon style is in Test_Calendar: only a real render adds its class.
		$this->assertStringContainsString( 'is-style-ribbon', $this->render_day( $context ) );

		$context['gatherpress/timeGridConfig'] = new Time_Grid();
		$this->assertStringNotContainsString( 'is-style-ribbon', $this->render_day( $context ) );
	}

	/**
	 * A future weekend day with a multi-day event gets the matching classes.
	 *
	 * @covers ::render
	 * @covers ::get_day_cell_classes
	 * @covers ::has_multi_day_event
	 * @covers ::resolve_weekday_and_weekend
	 * @covers ::get_temporal_classes
	 *
	 * @return void
	 */
	public function test_render_future_weekend_with_multi_day_event(): void {
		$context = array(
			'gatherpress/isEmpty'   => false,
			'gatherpress/dayNumber' => 17,
			'gatherpress/dayDate'   => '2099-10-17',
			'gatherpress/dayPosts'  => array( 'not an entry', array( 'total_days' => 3 ) ),
		);
		$output  = $this->render_day( $context );

		$this->assertStringContainsString( 'gatherpress--has-posts', $output );
		$this->assertStringContainsString( 'gatherpress--has-multi-day-event', $output );
		$this->assertStringContainsString(
			'gatherpress--is-weekend',
			$output,
			'Failed to assert Saturday 2099-10-17 is a weekend day.'
		);
		$this->assertStringContainsString( 'gatherpress--is-saturday', $output );
		$this->assertStringContainsString( 'gatherpress--is-future', $output );

		$context['gatherpress/dayPosts'] = array( array( 'is_all_day' => true ) );
		$this->assertStringContainsString(
			'gatherpress--has-multi-day-event',
			$this->render_day( $context )
		);

		$context['gatherpress/dayPosts'] = array( array( 'total_days' => 1 ) );
		$this->assertStringNotContainsString(
			'gatherpress--has-multi-day-event',
			$this->render_day( $context )
		);
	}

	/**
	 * A day template without a bound day number gets the plain day number.
	 *
	 * @covers ::render
	 * @covers ::find_day_inner_blocks
	 * @covers ::is_day_number_block
	 * @covers ::render_day_cell_content
	 *
	 * @return void
	 */
	public function test_render_without_bound_day_number(): void {
		$template = '<!-- wp:gatherpress/calendar-day --><!-- wp:paragraph --><p>Not bound</p><!-- /wp:paragraph -->'
			. '<!-- /wp:gatherpress/calendar-day -->';
		$output   = $this->render_day(
			array(
				'gatherpress/isEmpty'   => false,
				'gatherpress/dayNumber' => 9,
				'gatherpress/dayDate'   => '2020-01-09',
				'gatherpress/weekday'   => 'thursday',
			),
			$template
		);

		$this->assertStringContainsString( '<p class="gatherpress-calendar__day-number">9</p>', $output );
		$this->assertStringNotContainsString(
			'Not bound',
			$output,
			'Failed to assert only the day number and entries render.'
		);
	}

	/**
	 * A day without a date gets no temporal classes, and only parsed blocks count as inner blocks.
	 *
	 * @covers ::get_day_cell_classes
	 * @covers ::get_temporal_classes
	 * @covers ::find_day_inner_blocks
	 *
	 * @return void
	 */
	public function test_day_without_date_and_odd_inner_blocks(): void {
		$output = $this->render_day( array( 'gatherpress/isEmpty' => false ) );

		$this->assertStringNotContainsString( 'past', $output );
		$this->assertStringNotContainsString( 'future', $output );
		$this->assertStringNotContainsString( 'gatherpress--is-today', $output );

		$block                              = $this->get_block( array() );
		$block->parsed_block['innerBlocks'] = array( 'not a block' );

		$found = Utility::invoke_hidden_method( new Calendar_Day( $block ), 'find_day_inner_blocks' );

		$this->assertSame(
			array(
				'day_number' => null,
				'entries'    => null,
			),
			$found
		);

		$block->parsed_block['innerBlocks'] = 'not a list';

		$this->assertSame(
			$found,
			Utility::invoke_hidden_method( new Calendar_Day( $block ), 'find_day_inner_blocks' ),
			'Failed to assert inner blocks that are not a list count as none.'
		);
	}
}
