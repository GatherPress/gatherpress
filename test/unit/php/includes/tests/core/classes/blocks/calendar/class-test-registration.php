<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Registration.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use GatherPress\Core\Blocks\Calendar\Registration;
use GatherPress\Core\Calendar\Query_Builder;
use GatherPress\Tests\Base;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Class Test_Registration.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar\Registration
 */
class Test_Registration extends Base {

	/**
	 * Build a Paragraph block with the given context.
	 *
	 * @param array<string, mixed> $context Block context.
	 *
	 * @return WP_Block
	 */
	private function get_block( array $context ): WP_Block {
		$block          = new WP_Block(
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
			)
		);
		$block->context = $context;

		return $block;
	}

	/**
	 * Tests the setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Registration::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'register_binding_sources' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'block_editor_settings_all',
				'priority' => 10,
				'callback' => array( $instance, 'block_editor_settings_all' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'register_block_type_args',
				'priority' => 10,
				'callback' => array( $instance, 'skip_inner_blocks' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The four calendar blocks render their templates themselves, so WordPress does not render them first.
	 *
	 * @covers ::skip_inner_blocks
	 *
	 * @return void
	 */
	public function test_skip_inner_blocks(): void {
		$instance = Registration::get_instance();

		$this->assertSame(
			array(
				'title'             => 'Day',
				'skip_inner_blocks' => true,
			),
			$instance->skip_inner_blocks( array( 'title' => 'Day' ), 'gatherpress/calendar-day' )
		);
		$this->assertSame(
			array( 'title' => 'Paragraph' ),
			$instance->skip_inner_blocks( array( 'title' => 'Paragraph' ), 'core/paragraph' ),
			'Failed to assert other blocks do not change.'
		);

		foreach ( Registration::SELF_RENDERING_BLOCKS as $block_name ) {
			$this->assertTrue(
				WP_Block_Type_Registry::get_instance()->get_registered( $block_name )->skip_inner_blocks,
				sprintf( 'Failed to assert %s is registered with skip_inner_blocks.', $block_name )
			);
		}
	}

	/**
	 * Coverage for register_binding_sources.
	 *
	 * @covers ::register_binding_sources
	 *
	 * @return void
	 */
	public function test_register_binding_sources(): void {
		unregister_block_bindings_source( 'gatherpress/calendar-day' );
		unregister_block_bindings_source( 'gatherpress/calendar-heading' );

		Registration::get_instance()->register_binding_sources();

		$day     = get_block_bindings_source( 'gatherpress/calendar-day' );
		$heading = get_block_bindings_source( 'gatherpress/calendar-heading' );

		$this->assertNotNull( $day );
		$this->assertNotNull( $heading );
		$this->assertSame(
			array( 'gatherpress/dayNumber', 'gatherpress/dayDate', 'gatherpress/isEmpty' ),
			$day->uses_context
		);
		$this->assertSame(
			array( 'query', 'queryId', 'postId', 'postType' ),
			$heading->uses_context,
			'Failed to assert the heading reads the page of its own Query block.'
		);
	}

	/**
	 * Coverage for block_editor_settings_all.
	 *
	 * @covers ::block_editor_settings_all
	 *
	 * @return void
	 */
	public function test_block_editor_settings_all(): void {
		update_option( 'start_of_week', 3 );

		$settings = Registration::get_instance()->block_editor_settings_all(
			array( 'gatherpress' => array( 'config' => 'kept' ) )
		);

		$this->assertSame( 'kept', $settings['gatherpress']['config'], 'Failed to assert other values stay.' );
		$this->assertSame( array( 0, 6 ), $settings['gatherpress']['weekendDays'] );
		$this->assertSame( 500, $settings['gatherpress']['postsPerPage'] );
		$this->assertSame( 3, $settings['gatherpress']['startOfWeek'] );
		$this->assertSame( 1, $settings['gatherpress']['timeSlotDuration'] );

		$two_hours = static function () {
			return 2;
		};
		add_filter( 'gatherpress_calendar_time_slot_duration', $two_hours );
		$settings = Registration::get_instance()->block_editor_settings_all( array() );
		remove_filter( 'gatherpress_calendar_time_slot_duration', $two_hours );

		$this->assertSame(
			2,
			$settings['gatherpress']['timeSlotDuration'],
			'Failed to assert the editor gets the filtered hours per time grid row.'
		);

		$this->assertSame(
			3,
			$settings['gatherpress']['startOfWeek'],
			'Failed to assert the settings without a gatherpress key.'
		);
	}

	/**
	 * Coverage for get_day_number_binding_value.
	 *
	 * @covers ::get_day_number_binding_value
	 *
	 * @return void
	 */
	public function test_get_day_number_binding_value(): void {
		$instance = Registration::get_instance();
		$day      = $this->get_block(
			array(
				'gatherpress/dayNumber' => 15,
				'gatherpress/dayDate'   => '2026-10-15',
			)
		);

		$this->assertNull( $instance->get_day_number_binding_value( array(), $day, 'url' ) );
		$this->assertSame( '15', $instance->get_day_number_binding_value( array(), $day, 'content' ) );
		$this->assertSame(
			'15th',
			$instance->get_day_number_binding_value( array( 'format' => 'jS' ), $day, 'content' ),
			'Failed to assert the format argument.'
		);
		$this->assertSame(
			'',
			$instance->get_day_number_binding_value(
				array(),
				$this->get_block( array( 'gatherpress/isEmpty' => true ) ),
				'content'
			),
			'Failed to assert an empty cell has no number.'
		);
		$this->assertNull( $instance->get_day_number_binding_value( array(), $this->get_block( array() ), 'content' ) );

		$bad_date = $this->get_block(
			array(
				'gatherpress/dayNumber' => 4,
				'gatherpress/dayDate'   => 'not-a-date',
			)
		);
		$this->assertSame(
			'4',
			$instance->get_day_number_binding_value( array( 'format' => 'j' ), $bad_date, 'content' )
		);
	}

	/**
	 * Coverage for get_heading_binding_value.
	 *
	 * @covers ::get_heading_binding_value
	 *
	 * @return void
	 */
	public function test_get_heading_binding_value(): void {
		$instance = Registration::get_instance();

		$this->assertNull( $instance->get_heading_binding_value( array(), $this->get_block( array() ), 'url' ) );
		$this->assertSame(
			wp_date( 'F Y' ),
			$instance->get_heading_binding_value( array(), $this->get_block( array() ), 'content' ),
			'Failed to assert the current month outside a Query Loop.'
		);

		$block = $this->get_block(
			array(
				'queryId' => 3,
				'query'   => array(
					Query_Builder::CALENDAR_QUERY_START_DATE => '2026-10-01',
					Query_Builder::CALENDAR_QUERY_END_DATE => '2026-10-31',
					Query_Builder::CALENDAR_QUERY_HEADING  => 'October 2026',
				),
			)
		);

		$this->assertSame( 'October 2026', $instance->get_heading_binding_value( array(), $block, 'content' ) );
	}
}
