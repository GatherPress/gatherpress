<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Registration.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use GatherPress\Core\Blocks\Calendar\Query_Integration;
use GatherPress\Core\Blocks\Calendar\Registration;
use GatherPress\Tests\Base;
use WP_Block;

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
		);

		$this->assert_hooks( $hooks, $instance );
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

		$settings = Registration::get_instance()->block_editor_settings_all( array() );

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
					Query_Integration::CALENDAR_QUERY_START_DATE => '2026-10-01',
					Query_Integration::CALENDAR_QUERY_END_DATE   => '2026-10-31',
					Query_Integration::CALENDAR_QUERY_HEADING    => 'October 2026',
				),
			)
		);

		$this->assertSame( 'October 2026', $instance->get_heading_binding_value( array(), $block, 'content' ) );
	}
}
