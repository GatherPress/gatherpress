<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar_Week.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Calendar_Week;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;

/**
 * Class Test_Calendar_Week.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar_Week
 */
class Test_Calendar_Week extends Base {

	/**
	 * Week template with a day template.
	 *
	 * @var string
	 */
	const TEMPLATE = '<!-- wp:gatherpress/calendar-week --><!-- wp:gatherpress/calendar-day -->'
		. '<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"gatherpress/calendar-day"}}}} -->'
		. '<p></p><!-- /wp:paragraph -->'
		. '<!-- /wp:gatherpress/calendar-day --><!-- /wp:gatherpress/calendar-week -->';

	/**
	 * Build a week block with the given context.
	 *
	 * @param array<string, mixed> $context Week context.
	 *
	 * @return WP_Block
	 */
	private function get_block( array $context ): WP_Block {
		$block          = new WP_Block( parse_blocks( self::TEMPLATE )[0] );
		$block->context = $context;

		return $block;
	}

	/**
	 * Build a day entry like Structure_Builder makes.
	 *
	 * @param int          $day   Day of the month.
	 * @param array<mixed> $posts Posts of the day.
	 *
	 * @return array<string, mixed>
	 */
	private function day( int $day, array $posts = array() ): array {
		return array(
			'day'       => $day,
			'date'      => sprintf( '2026-10-%02d', $day ),
			'posts'     => $posts,
			'isEmpty'   => false,
			'dayOfWeek' => 4,
			'weekday'   => 'thursday',
			'isWeekend' => false,
		);
	}

	/**
	 * A week renders one row with a cell per day and a hidden week number.
	 *
	 * @covers ::render
	 * @covers ::calculate_max_ribbon_slot
	 * @covers ::render_days
	 * @covers ::build_day_context
	 * @covers ::get_row_key
	 * @covers ::get_ribbon_rows_style
	 *
	 * @return void
	 */
	public function test_render(): void {
		$output = Calendar_Week::render(
			$this->get_block(
				array(
					'gatherpress/year'       => 2026,
					'gatherpress/month'      => 10,
					'gatherpress/weekIndex'  => 2,
					'gatherpress/weekNumber' => 42,
					'gatherpress/weekDays'   => array(
						array(
							'isEmpty' => true,
							'weekday' => 'wednesday',
						),
						$this->day(
							15,
							array(
								array( 'slot' => 2 ),
								array(
									'slot'     => 5,
									'is_timed' => true,
								),
								'not an entry',
							)
						),
						'not a day',
					),
				)
			)
		);

		$this->assertStringStartsWith( '<tr ', $output );
		$this->assertStringContainsString( 'gatherpress-calendar__week', $output );
		$this->assertStringContainsString( 'data-wp-key="week-2026-10-2"', $output );
		$this->assertStringContainsString(
			'style="--gatherpress-num-ribbon-slots: 3;--gatherpress-ribbon-rows: repeat(3, 24px)"',
			$output,
			'Failed to assert the highest ribbon slot, without timed events, and its rows for the time grid.'
		);
		$this->assertStringContainsString(
			'<th scope="row" class="gatherpress--screen-reader-text">Week 42</th>',
			$output
		);
		$this->assertSame( 2, substr_count( $output, '<td ' ), 'Failed to assert one cell per day entry.' );
		$this->assertStringContainsString( '>15</p>', $output );
		$this->assertStringNotContainsString( 'gatherpress-calendar__time-gutter', $output );
	}

	/**
	 * A week with the time grid gets the time gutter and no week number.
	 *
	 * @covers ::render
	 * @covers ::render_time_gutter
	 * @covers ::get_row_key
	 *
	 * @return void
	 */
	public function test_render_time_grid(): void {
		$output = Calendar_Week::render(
			$this->get_block(
				array(
					'gatherpress/weekDays'       => array( $this->day( 15 ) ),
					'gatherpress/timeGridConfig' => array(
						'enabled' => true,
						'slots'   => array( '09:00', '10:00', array( 'not a label' ) ),
					),
				)
			)
		);

		$this->assertStringContainsString( 'has-time-grid', $output );
		$this->assertStringContainsString(
			'style="--gatherpress-num-ribbon-slots: 0;--gatherpress-ribbon-rows: initial"',
			$output
		);
		$this->assertStringContainsString( 'style="padding-top: calc(28px + 0 * 24px);"', $output );
		$this->assertStringContainsString( '<div class="gatherpress-calendar__time-slot-label">09:00</div>', $output );
		$this->assertStringContainsString( '<div class="gatherpress-calendar__time-slot-label"></div>', $output );
		$this->assertStringContainsString(
			'data-wp-key="week-0-0-0"',
			$output,
			'Failed to assert the key without context.'
		);
		$this->assertStringNotContainsString( 'Week ', $output );
	}

	/**
	 * A week without days renders an empty row. Its ribbon rows are `initial`, not repeat(0, …),
	 * which is not valid CSS; `initial` also stops an outer week's rows from passing down.
	 *
	 * @covers ::render
	 * @covers ::get_ribbon_rows_style
	 *
	 * @return void
	 */
	public function test_render_without_days(): void {
		$output = Calendar_Week::render( $this->get_block( array() ) );

		$this->assertStringContainsString(
			'style="--gatherpress-num-ribbon-slots: 0;--gatherpress-ribbon-rows: initial"',
			$output
		);
		$this->assertStringNotContainsString( 'repeat(0', $output );
		$this->assertStringEndsWith( '></tr>', $output );
	}

	/**
	 * Multi-day entries learn where the row cuts them.
	 *
	 * @covers ::annotate_day_posts
	 * @covers ::annotate_post_entry
	 * @covers ::count_remaining_active_columns
	 *
	 * @return void
	 */
	public function test_annotate_day_posts(): void {
		$week  = array(
			array( 'isEmpty' => true ),
			$this->day( 1 ),
			$this->day( 2 ),
			$this->day( 3 ),
			array( 'isEmpty' => true ),
		);
		$posts = array(
			array(
				'day_index'  => 2,
				'total_days' => 5,
			),
			array( 'total_days' => 1 ),
			'not an entry',
		);

		$first = Utility::invoke_hidden_static_method(
			Calendar_Week::class,
			'annotate_day_posts',
			array( $posts, 1, $week, count( $week ) )
		);

		$this->assertTrue( $first[0]['is_continued'], 'Failed to assert the day after an empty cell starts the row.' );
		$this->assertFalse( $first[0]['is_continues'] );
		$this->assertSame( 3, $first[0]['row_span'], 'Failed to assert the span stops at the end of the row.' );
		$this->assertSame( 1, $first[1]['row_span'] );
		$this->assertArrayNotHasKey( 'is_continued', $first[1] );
		$this->assertSame( 'not an entry', $first[2] );

		$last = Utility::invoke_hidden_static_method(
			Calendar_Week::class,
			'annotate_day_posts',
			array( array( $posts[0] ), 3, $week, count( $week ) )
		);

		$this->assertFalse( $last[0]['is_continued'] );
		$this->assertTrue( $last[0]['is_continues'], 'Failed to assert the day before an empty cell ends the row.' );
		$this->assertSame( 1, $last[0]['row_span'] );

		$end = Utility::invoke_hidden_static_method(
			Calendar_Week::class,
			'annotate_post_entry',
			array(
				array(
					'day_index'  => 'x',
					'total_days' => 2,
				),
				true,
				true,
				4,
			)
		);

		$this->assertFalse( $end['is_continued'], 'Failed to assert a day index that is not a number is day 1.' );
		$this->assertTrue( $end['is_continues'] );
		$this->assertSame( 2, $end['row_span'] );
	}
}
