<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Html_Renderer.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use GatherPress\Core\Blocks\Calendar\Html_Renderer;
use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Time_Grid;
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
	 * Coverage for generate_calendar_html with a time grid.
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
		$renderer = new Html_Renderer(
			new WP_Block( array( 'blockName' => 'gatherpress/calendar' ) ),
			Date_Range::from_attributes(
				array(
					'viewType'     => 'day',
					'selectedDate' => '2026-10-15',
				)
			),
			new Time_Grid( true )
		);
		$output   = $renderer->generate_calendar_html(
			array(),
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
			60
		);

		$this->assertStringContainsString( '--gatherpress-total-quarter-slots: 48', $output );
		$this->assertStringContainsString( '--gatherpress-calendar-slot-height: 60px', $output );
		$this->assertStringContainsString(
			'--gatherpress-calendar-columns: 2',
			$output,
			'Failed to assert the time gutter column.'
		);
		$this->assertStringContainsString(
			'data-wp-key="calendar-"',
			$output,
			'Failed to assert the key without a start date.'
		);
		$this->assertStringNotContainsString( '<caption', $output, 'Failed to assert an empty caption is left out.' );
	}

	/**
	 * Coverage for allow_block_markup.
	 *
	 * @covers ::allow_block_markup
	 *
	 * @return void
	 */
	public function test_allow_block_markup(): void {
		$renderer = new Html_Renderer(
			new WP_Block( array( 'blockName' => 'gatherpress/calendar' ) ),
			Date_Range::from_attributes( array( 'selectedDate' => '2026-10-15' ) ),
			new Time_Grid( false )
		);
		$tags     = array( 'textarea' => array( 'cols' => true ) );

		$this->assertSame(
			$tags,
			$renderer->allow_block_markup( $tags, 'data' ),
			'Failed to assert other contexts are left alone.'
		);

		$allowed = $renderer->allow_block_markup( $tags, 'post' );

		$this->assertTrue( $allowed['div']['aria-modal'] );
		$this->assertTrue( $allowed['form']['action'] );
		$this->assertTrue( $allowed['input']['type'] );
		$this->assertTrue( $allowed['select']['name'] );
		$this->assertTrue( $allowed['option']['value'] );
		$this->assertTrue( $allowed['textarea']['cols'], 'Failed to assert existing textarea attributes are kept.' );
		$this->assertTrue( $allowed['textarea']['placeholder'] );
		$this->assertTrue( $allowed['svg']['viewbox'] );
		$this->assertTrue( $allowed['path']['d'] );
		$this->assertTrue( $allowed['iframe']['src'] );
		$this->assertArrayNotHasKey( 'script', $allowed );
	}
}
