<?php
/**
 * Class handles unit tests for GatherPress\Core\Block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Block;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;
use WP_Block_Type_Registry;

/**
 * Class Test_Block.
 *
 * @coversDefaultClass \GatherPress\Core\Block
 * @group              blocks
 */
class Test_Block extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Block::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 9,
				'callback' => array( $instance, 'register_block_variations' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_block_patterns' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'register_blocks' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_blocks.
	 *
	 * @covers ::register_blocks
	 *
	 * @return void
	 */
	public function test_register_blocks(): void {
		$instance            = Block::get_instance();
		$blocks              = array(
			'gatherpress/add-to-calendar',
			'gatherpress/event-date',
			'gatherpress/events-list',
			'gatherpress/online-event',
			'gatherpress/rsvp',
			'gatherpress/rsvp-response',
			'gatherpress/venue',
		);
		$block_type_registry = WP_Block_Type_Registry::get_instance();

		// Clear out registered blocks.
		Utility::set_and_get_hidden_property( $block_type_registry, 'registered_block_types', array() );

		// Register our blocks.
		$instance->register_blocks();

		$expected = array_keys( Utility::get_hidden_property( $block_type_registry, 'registered_block_types' ) );

		$this->assertSame( $blocks, $expected );
	}

	/**
	 * Coverage for get_block_variations.
	 *
	 * @covers ::get_block_variations
	 *
	 * @return void
	 */
	public function test_get_block_variations(): void {
		$instance = Block::get_instance();

		$this->assertSame(
			array(
				'add-to-calendar'
			),
			Utility::invoke_hidden_method( $instance, 'get_block_variations' ),
			'Failed to assert, to get all block variations from the "/src" directory.'
		);
	}
}
