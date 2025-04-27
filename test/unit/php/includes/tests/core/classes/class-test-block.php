<?php
/**
 * Class handles unit tests for GatherPress\Core\Block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Block;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block_Patterns_Registry;
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
				'priority' => 10,
				'callback' => array( $instance, 'register_block_classes' ),
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
			array(
				'type'     => 'filter',
				'name'     => 'hooked_block_types',
				'priority' => 9,
				'callback' => array( $instance, 'hook_blocks_into_patterns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'hooked_block_core/paragraph',
				'priority' => 9,
				'callback' => array( $instance, 'modify_hooked_blocks_in_patterns' ),
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
			'gatherpress/dropdown',
			'gatherpress/dropdown-item',
			'gatherpress/event-date',
			'gatherpress/events-list',
			'gatherpress/icon',
			'gatherpress/modal',
			'gatherpress/modal-content',
			'gatherpress/modal-manager',
			'gatherpress/online-event',
			'gatherpress/rsvp',
			'gatherpress/rsvp-anonymous-checkbox',
			'gatherpress/rsvp-guest-count-display',
			'gatherpress/rsvp-guest-count-input',
			'gatherpress/rsvp-response',
			'gatherpress/rsvp-response-toggle',
			'gatherpress/rsvp-template',
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
			array(),
			$instance->get_block_variations(),
			'Failed to assert, to get all block variations from the "/src" directory.'
		);
	}

	/**
	 * Coverage for get_classname_from_foldername.
	 *
	 * @covers ::get_classname_from_foldername
	 *
	 * @return void
	 */
	public function test_get_classname_from_foldername(): void {
		$instance = Block::get_instance();

		$this->assertSame(
			'Unit_Test',
			Utility::invoke_hidden_method( $instance, 'get_classname_from_foldername', array( '/src/variations/unit-test' ) ),
			'Failed to assert, to get class name from foldername.'
		);
	}

	/**
	 * Coverage for register_block_patterns.
	 *
	 * @covers ::register_block_patterns
	 *
	 * @return void
	 */
	public function test_register_block_patterns(): void {
		$instance               = Block::get_instance();
		$block_patterns         = array(
			'gatherpress/event-template',
			'gatherpress/venue-template',
			'gatherpress/venue-details',
		);
		$block_pattern_registry = WP_Block_Patterns_Registry::get_instance();

		// Clear out registered block patterns.
		Utility::set_and_get_hidden_property( $block_pattern_registry, 'registered_patterns', array() );

		// Register our block patterns.
		$instance->register_block_patterns();

		$expected = wp_list_pluck( $block_pattern_registry->get_all_registered(), 'name' );

		$this->assertSame( $block_patterns, $expected );
	}

	/**
	 * Coverage for existence of pattern slugs in developer docs.
	 *
	 * @return void
	 */
	public function test_docs_contain_patterns(): void {

		$doc_file = file_get_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			sprintf(
				'%s/docs/%s',
				GATHERPRESS_CORE_PATH,
				'developer/blocks/hookable-patterns/README.md'
			)
		);

		$this->assertStringContainsString( '`gatherpress/event-template`', $doc_file );
		$this->assertStringContainsString( '`gatherpress/venue-template`', $doc_file );
	}
}
