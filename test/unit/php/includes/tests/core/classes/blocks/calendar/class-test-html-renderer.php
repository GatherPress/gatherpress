<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Html_Renderer.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use GatherPress\Core\Blocks\Calendar\Html_Renderer;
use GatherPress\Tests\Base;
use WP_Block;

/**
 * Class Test_Html_Renderer.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar\Html_Renderer
 */
class Test_Html_Renderer extends Base {

	/**
	 * Coverage for get_inner_template_block.
	 *
	 * @covers ::get_inner_template_block
	 *
	 * @return void
	 */
	public function test_get_inner_template_block(): void {
		$block = new WP_Block(
			parse_blocks(
				'<!-- wp:gatherpress/calendar --><!-- wp:gatherpress/calendar-week {"dayStartHour":9} -->'
					. '<!-- /wp:gatherpress/calendar-week --><!-- /wp:gatherpress/calendar -->'
			)[0]
		);

		$week = Html_Renderer::get_inner_template_block( $block, 'gatherpress/calendar-week' );

		$this->assertSame( 'gatherpress/calendar-week', $week['blockName'] );
		$this->assertSame( 9, $week['attrs']['dayStartHour'] );

		$fallback = Html_Renderer::get_inner_template_block( $block, 'gatherpress/calendar-day' );

		$this->assertSame(
			'gatherpress/calendar-day',
			$fallback['blockName'],
			'Failed to assert a fallback template.'
		);
		$this->assertSame( array(), $fallback['attrs'] );
		$this->assertSame( 'gatherpress/calendar-week', $fallback['innerBlocks'][0]['blockName'] );

		$empty = new WP_Block( array( 'blockName' => 'gatherpress/calendar' ) );

		$this->assertSame(
			array(
				'blockName'    => 'gatherpress/calendar-week',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			Html_Renderer::get_inner_template_block( $empty, 'gatherpress/calendar-week' )
		);
	}

	/**
	 * Coverage for generate_calendar_html with a time grid without a quarter count.
	 *
	 * @covers ::__construct
	 * @covers ::generate_calendar_html
	 * @covers ::build_table_style
	 * @covers ::build_wrapper_attributes
	 * @covers ::render_unit_table
	 *
	 * @return void
	 */
	public function test_generate_calendar_html(): void {
		$renderer = new Html_Renderer( new WP_Block( array( 'blockName' => 'gatherpress/calendar' ) ) );
		$output   = $renderer->generate_calendar_html(
			array( 'showWeekends' => true ),
			array(
				'heading'    => '',
				'day_names'  => array( 'Thu' ),
				'weeks'      => array(),
				'view_type'  => 'day',
				'unit_count' => 1,
				'units'      => array(
					array(
						'caption'   => '',
						'day_names' => array( 'Thu' ),
						'weeks'     => array(),
					),
				),
			),
			array(
				'enabled'        => true,
				'total_quarters' => 'many',
			)
		);

		$this->assertStringContainsString( '--gatherpress-total-quarter-slots: 48', $output );
		$this->assertStringContainsString(
			'data-wp-key="calendar-"',
			$output,
			'Failed to assert the key without a start date.'
		);
		$this->assertStringNotContainsString( '<caption', $output, 'Failed to assert an empty caption is left out.' );
	}
}
