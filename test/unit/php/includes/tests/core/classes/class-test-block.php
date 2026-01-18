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
			'gatherpress/form-field',
			'gatherpress/icon',
			'gatherpress/modal',
			'gatherpress/modal-content',
			'gatherpress/modal-manager',
			'gatherpress/online-event',
			'gatherpress/rsvp',
			'gatherpress/rsvp-form',
			'gatherpress/rsvp-guest-count-display',
			'gatherpress/rsvp-response',
			'gatherpress/rsvp-response-toggle',
			'gatherpress/rsvp-template',
			'gatherpress/venue',
			'gatherpress/venue-v2',
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
				'query',
				'query-no-results',
				'query-pagination',
				'query-pagination-next',
				'query-pagination-numbers',
				'query-pagination-previous',
			),
			$instance->get_block_variations(),
			'Failed to assert, to get all block variations from the "/src" directory.'
		);
	}

	/**
	 * Coverage for get_block_variations when directory doesn't exist.
	 *
	 * Covers: Early return when variations directory doesn't exist.
	 *
	 * @covers ::get_block_variations
	 *
	 * @return void
	 */
	public function test_get_block_variations_directory_not_exists(): void {
		$instance            = Block::get_instance();
		$variations_dir      = sprintf( '%1$s/build/variations/core/', GATHERPRESS_CORE_PATH );
		$temp_renamed_dir    = sprintf( '%1$s/build/variations/core-temp-renamed/', GATHERPRESS_CORE_PATH );
		$variations_dir_base = sprintf( '%1$s/build/variations/', GATHERPRESS_CORE_PATH );

		// Temporarily rename the variations directory to simulate non-existence.
		if ( file_exists( $variations_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Necessary for testing.
			rename( $variations_dir, $temp_renamed_dir );
		}

		// Reset the cached property to force a fresh check.
		Utility::set_and_get_hidden_property( $instance, 'block_variation_names', array() );

		// Now the directory doesn't exist, should return empty array.
		$result = $instance->get_block_variations();

		$this->assertSame( array(), $result, 'Should return empty array when variations directory does not exist.' );

		// Restore the directory.
		if ( file_exists( $temp_renamed_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Necessary for testing.
			rename( $temp_renamed_dir, $variations_dir );
		}

		// Reset the cache again for other tests.
		Utility::set_and_get_hidden_property( $instance, 'block_variation_names', array() );
	}

	/**
	 * Coverage for get_block_variations caching behavior.
	 *
	 * Covers: Caching of block variation names.
	 *
	 * @covers ::get_block_variations
	 *
	 * @return void
	 */
	public function test_get_block_variations_caching(): void {
		$instance = Block::get_instance();

		// Reset the cache to ensure we're starting fresh.
		Utility::set_and_get_hidden_property( $instance, 'block_variation_names', array() );

		// Verify block_variation_names is empty initially.
		$cache_before = Utility::get_hidden_property( $instance, 'block_variation_names' );
		$this->assertEmpty( $cache_before );

		// First call should populate the cache.
		$first_result = $instance->get_block_variations();

		// Verify cache is now populated (target code executed).
		$cache_after_first = Utility::get_hidden_property( $instance, 'block_variation_names' );
		$this->assertNotEmpty( $cache_after_first );

		// Second call should use cached values (target code check causes early return from cache).
		$second_result = $instance->get_block_variations();

		// Verify cache wasn't modified by second call.
		$cache_after_second = Utility::get_hidden_property( $instance, 'block_variation_names' );
		$this->assertSame( $cache_after_first, $cache_after_second );

		// Both results should be identical.
		$this->assertSame( $first_result, $second_result, 'Should return cached variation names on subsequent calls.' );

		// Verify the final result matches the cached data after array_filter.
		$this->assertSame( array_filter( $cache_after_second ), $second_result );
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
			Utility::invoke_hidden_method(
				$instance,
				'get_classname_from_foldername',
				array( '/src/variations/unit-test' )
			),
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$doc_file = file_get_contents(
			sprintf(
				'%s/docs/%s',
				GATHERPRESS_CORE_PATH,
				'developer/blocks/hookable-patterns/README.md'
			)
		);

		$this->assertStringContainsString( '`gatherpress/event-template`', $doc_file );
		$this->assertStringContainsString( '`gatherpress/venue-template`', $doc_file );
	}

	/**
	 * Coverage for register_block_classes.
	 *
	 * @covers ::register_block_classes
	 *
	 * @return void
	 */
	public function test_register_block_classes(): void {
		$instance = Block::get_instance();

		// Just ensure the method runs without errors.
		// Block class instances are singletons, so they'll already be instantiated.
		$instance->register_block_classes();

		// Verify that calling it again doesn't cause issues (singletons should handle this).
		$instance->register_block_classes();

		$this->assertTrue( true );
	}

	/**
	 * Coverage for get_default_block_class.
	 *
	 * @covers ::get_default_block_class
	 *
	 * @return void
	 */
	public function test_get_default_block_class(): void {
		$instance = Block::get_instance();

		$this->assertSame(
			'wp-block-gatherpress-event-date',
			$instance->get_default_block_class( 'gatherpress/event-date' ),
			'Failed to generate correct block class for gatherpress/event-date.'
		);

		$this->assertSame(
			'wp-block-core-paragraph',
			$instance->get_default_block_class( 'core/paragraph' ),
			'Failed to generate correct block class for core/paragraph.'
		);

		$this->assertSame(
			'wp-block-my-plugin-custom-block',
			$instance->get_default_block_class( 'my-plugin/custom-block' ),
			'Failed to generate correct block class for custom block.'
		);
	}

	/**
	 * Coverage for hook_blocks_into_patterns with event template pattern.
	 *
	 * @covers ::hook_blocks_into_patterns
	 *
	 * @return void
	 */
	public function test_hook_blocks_into_patterns_event_template(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'gatherpress/event-template',
		);

		$hooked_blocks = $instance->hook_blocks_into_patterns(
			array(),
			'after',
			'gatherpress/event-date',
			$context
		);

		$this->assertContains( 'gatherpress/add-to-calendar', $hooked_blocks );
		$this->assertContains( 'gatherpress/venue', $hooked_blocks );
		$this->assertContains( 'gatherpress/rsvp', $hooked_blocks );
		$this->assertContains( 'core/paragraph', $hooked_blocks );
		$this->assertContains( 'gatherpress/rsvp-response', $hooked_blocks );
	}

	/**
	 * Coverage for hook_blocks_into_patterns with venue template pattern.
	 *
	 * @covers ::hook_blocks_into_patterns
	 *
	 * @return void
	 */
	public function test_hook_blocks_into_patterns_venue_template(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'gatherpress/venue-template',
		);

		$hooked_blocks = $instance->hook_blocks_into_patterns(
			array(),
			'after',
			'core/paragraph',
			$context
		);

		$this->assertContains( 'gatherpress/venue', $hooked_blocks );
	}

	/**
	 * Coverage for hook_blocks_into_patterns with non-array context.
	 *
	 * @covers ::hook_blocks_into_patterns
	 *
	 * @return void
	 */
	public function test_hook_blocks_into_patterns_non_array_context(): void {
		$instance = Block::get_instance();

		$hooked_blocks = $instance->hook_blocks_into_patterns(
			array( 'some-block' ),
			'after',
			'gatherpress/event-date',
			'not-an-array'
		);

		// Should return the original array unchanged.
		$this->assertSame( array( 'some-block' ), $hooked_blocks );
	}

	/**
	 * Coverage for hook_blocks_into_patterns with context missing name.
	 *
	 * @covers ::hook_blocks_into_patterns
	 *
	 * @return void
	 */
	public function test_hook_blocks_into_patterns_context_missing_name(): void {
		$instance = Block::get_instance();

		$hooked_blocks = $instance->hook_blocks_into_patterns(
			array( 'some-block' ),
			'after',
			'gatherpress/event-date',
			array( 'other' => 'data' )
		);

		// Should return the original array unchanged.
		$this->assertSame( array( 'some-block' ), $hooked_blocks );
	}

	/**
	 * Coverage for hook_blocks_into_patterns with wrong pattern name.
	 *
	 * @covers ::hook_blocks_into_patterns
	 *
	 * @return void
	 */
	public function test_hook_blocks_into_patterns_wrong_pattern(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'some-other/pattern',
		);

		$hooked_blocks = $instance->hook_blocks_into_patterns(
			array( 'some-block' ),
			'after',
			'gatherpress/event-date',
			$context
		);

		// Should return the original array unchanged.
		$this->assertSame( array( 'some-block' ), $hooked_blocks );
	}

	/**
	 * Coverage for hook_blocks_into_patterns with wrong relative position.
	 *
	 * @covers ::hook_blocks_into_patterns
	 *
	 * @return void
	 */
	public function test_hook_blocks_into_patterns_wrong_position(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'gatherpress/event-template',
		);

		$hooked_blocks = $instance->hook_blocks_into_patterns(
			array( 'some-block' ),
			'before',
			'gatherpress/event-date',
			$context
		);

		// Should return the original array unchanged.
		$this->assertSame( array( 'some-block' ), $hooked_blocks );
	}

	/**
	 * Coverage for modify_hooked_blocks_in_patterns with paragraph block.
	 *
	 * @covers ::modify_hooked_blocks_in_patterns
	 *
	 * @return void
	 */
	public function test_modify_hooked_blocks_in_patterns_paragraph(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'gatherpress/event-template',
		);

		$parsed_anchor_block = array(
			'blockName' => 'gatherpress/event-date',
		);

		$parsed_hooked_block = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);

		$result = $instance->modify_hooked_blocks_in_patterns(
			$parsed_hooked_block,
			'core/paragraph',
			'after',
			$parsed_anchor_block,
			$context
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'attrs', $result );
		$this->assertArrayHasKey( 'placeholder', $result['attrs'] );
		$this->assertStringContainsString( 'Add a description of the event', $result['attrs']['placeholder'] );
	}

	/**
	 * Coverage for modify_hooked_blocks_in_patterns when block is suppressed.
	 *
	 * @covers ::modify_hooked_blocks_in_patterns
	 *
	 * @return void
	 */
	public function test_modify_hooked_blocks_in_patterns_suppressed(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'gatherpress/event-template',
		);

		$parsed_anchor_block = array(
			'blockName' => 'gatherpress/event-date',
		);

		$result = $instance->modify_hooked_blocks_in_patterns(
			null,
			'core/paragraph',
			'after',
			$parsed_anchor_block,
			$context
		);

		// Should return null unchanged.
		$this->assertNull( $result );
	}

	/**
	 * Coverage for modify_hooked_blocks_in_patterns with non-array context.
	 *
	 * @covers ::modify_hooked_blocks_in_patterns
	 *
	 * @return void
	 */
	public function test_modify_hooked_blocks_in_patterns_non_array_context(): void {
		$instance = Block::get_instance();

		$parsed_anchor_block = array(
			'blockName' => 'gatherpress/event-date',
		);

		$parsed_hooked_block = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);

		$result = $instance->modify_hooked_blocks_in_patterns(
			$parsed_hooked_block,
			'core/paragraph',
			'after',
			$parsed_anchor_block,
			'not-an-array'
		);

		// Should return the original block unchanged.
		$this->assertSame( $parsed_hooked_block, $result );
	}

	/**
	 * Coverage for modify_hooked_blocks_in_patterns with context missing name.
	 *
	 * @covers ::modify_hooked_blocks_in_patterns
	 *
	 * @return void
	 */
	public function test_modify_hooked_blocks_in_patterns_context_missing_name(): void {
		$instance = Block::get_instance();

		$parsed_anchor_block = array(
			'blockName' => 'gatherpress/event-date',
		);

		$parsed_hooked_block = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);

		$result = $instance->modify_hooked_blocks_in_patterns(
			$parsed_hooked_block,
			'core/paragraph',
			'after',
			$parsed_anchor_block,
			array( 'other' => 'data' )
		);

		// Should return the original block unchanged.
		$this->assertSame( $parsed_hooked_block, $result );
	}

	/**
	 * Coverage for modify_hooked_blocks_in_patterns with wrong pattern name.
	 *
	 * @covers ::modify_hooked_blocks_in_patterns
	 *
	 * @return void
	 */
	public function test_modify_hooked_blocks_in_patterns_wrong_pattern(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'some-other/pattern',
		);

		$parsed_anchor_block = array(
			'blockName' => 'gatherpress/event-date',
		);

		$parsed_hooked_block = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);

		$result = $instance->modify_hooked_blocks_in_patterns(
			$parsed_hooked_block,
			'core/paragraph',
			'after',
			$parsed_anchor_block,
			$context
		);

		// Should return the original block unchanged.
		$this->assertSame( $parsed_hooked_block, $result );
	}

	/**
	 * Coverage for modify_hooked_blocks_in_patterns with wrong anchor block.
	 *
	 * @covers ::modify_hooked_blocks_in_patterns
	 *
	 * @return void
	 */
	public function test_modify_hooked_blocks_in_patterns_wrong_anchor(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'gatherpress/event-template',
		);

		$parsed_anchor_block = array(
			'blockName' => 'core/paragraph',
		);

		$parsed_hooked_block = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);

		$result = $instance->modify_hooked_blocks_in_patterns(
			$parsed_hooked_block,
			'core/paragraph',
			'after',
			$parsed_anchor_block,
			$context
		);

		// Should return the original block unchanged.
		$this->assertSame( $parsed_hooked_block, $result );
	}

	/**
	 * Coverage for modify_hooked_blocks_in_patterns with wrong position.
	 *
	 * @covers ::modify_hooked_blocks_in_patterns
	 *
	 * @return void
	 */
	public function test_modify_hooked_blocks_in_patterns_wrong_position(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'gatherpress/event-template',
		);

		$parsed_anchor_block = array(
			'blockName' => 'gatherpress/event-date',
		);

		$parsed_hooked_block = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);

		$result = $instance->modify_hooked_blocks_in_patterns(
			$parsed_hooked_block,
			'core/paragraph',
			'before',
			$parsed_anchor_block,
			$context
		);

		// Should return the original block unchanged.
		$this->assertSame( $parsed_hooked_block, $result );
	}

	/**
	 * Coverage for modify_hooked_blocks_in_patterns with non-paragraph block.
	 *
	 * @covers ::modify_hooked_blocks_in_patterns
	 *
	 * @return void
	 */
	public function test_modify_hooked_blocks_in_patterns_non_paragraph(): void {
		$instance = Block::get_instance();

		$context = array(
			'name' => 'gatherpress/event-template',
		);

		$parsed_anchor_block = array(
			'blockName' => 'gatherpress/event-date',
		);

		$parsed_hooked_block = array(
			'blockName' => 'gatherpress/rsvp',
			'attrs'     => array(),
		);

		$result = $instance->modify_hooked_blocks_in_patterns(
			$parsed_hooked_block,
			'gatherpress/rsvp',
			'after',
			$parsed_anchor_block,
			$context
		);

		// Should return the original block unchanged (no placeholder added for non-paragraph).
		$this->assertSame( $parsed_hooked_block, $result );
	}

	/**
	 * Coverage for get_block_names with simple block.
	 *
	 * @covers ::get_block_names
	 *
	 * @return void
	 */
	public function test_get_block_names_simple(): void {
		$instance = Block::get_instance();

		$blocks = array(
			'blockName' => 'core/paragraph',
		);

		$result = $instance->get_block_names( $blocks );

		$this->assertSame( array( 'core/paragraph' ), $result );
	}

	/**
	 * Coverage for get_block_names with nested blocks.
	 *
	 * @covers ::get_block_names
	 *
	 * @return void
	 */
	public function test_get_block_names_nested(): void {
		$instance = Block::get_instance();

		$blocks = array(
			'blockName'   => 'core/group',
			'innerBlocks' => array(
				array(
					'blockName' => 'core/paragraph',
				),
				array(
					'blockName' => 'gatherpress/event-date',
				),
			),
		);

		$result = $instance->get_block_names( $blocks );

		$this->assertSame(
			array( 'core/group', 'core/paragraph', 'gatherpress/event-date' ),
			$result
		);
	}

	/**
	 * Coverage for get_block_names with deeply nested blocks.
	 *
	 * @covers ::get_block_names
	 *
	 * @return void
	 */
	public function test_get_block_names_deeply_nested(): void {
		$instance = Block::get_instance();

		$blocks = array(
			'blockName'   => 'core/group',
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/columns',
					'innerBlocks' => array(
						array(
							'blockName'   => 'core/column',
							'innerBlocks' => array(
								array(
									'blockName' => 'core/paragraph',
								),
							),
						),
					),
				),
			),
		);

		$result = $instance->get_block_names( $blocks );

		$this->assertSame(
			array( 'core/group', 'core/columns', 'core/column', 'core/paragraph' ),
			$result
		);
	}

	/**
	 * Coverage for get_block_names with no blockName.
	 *
	 * @covers ::get_block_names
	 *
	 * @return void
	 */
	public function test_get_block_names_no_blockname(): void {
		$instance = Block::get_instance();

		$blocks = array(
			'attrs' => array(),
		);

		$result = $instance->get_block_names( $blocks );

		$this->assertSame( array(), $result );
	}

	/**
	 * Coverage for get_post_id with postId in attributes.
	 *
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_get_post_id_with_post_id_attribute(): void {
		$instance = Block::get_instance();

		$block = array(
			'attrs' => array(
				'postId' => 123,
			),
		);

		$result = $instance->get_post_id( $block );

		$this->assertSame( 123, $result );
	}

	/**
	 * Coverage for get_post_id without postId in attributes.
	 *
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_get_post_id_without_post_id_attribute(): void {
		$instance = Block::get_instance();
		$post     = $this->mock->post()->get();

		$this->go_to( get_permalink( $post->ID ) );

		$block = array(
			'attrs' => array(),
		);

		$result = $instance->get_post_id( $block );

		$this->assertSame( $post->ID, $result );
	}

	/**
	 * Coverage for get_post_id with zero postId.
	 *
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_get_post_id_with_zero_post_id(): void {
		$instance = Block::get_instance();
		$post     = $this->mock->post()->get();

		$this->go_to( get_permalink( $post->ID ) );

		$block = array(
			'attrs' => array(
				'postId' => 0,
			),
		);

		$result = $instance->get_post_id( $block );

		// Should fall back to get_the_ID() when postId is 0.
		$this->assertSame( $post->ID, $result );
	}

	/**
	 * Coverage for get_post_id with negative postId.
	 *
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_get_post_id_with_negative_post_id(): void {
		$instance = Block::get_instance();
		$post     = $this->mock->post()->get();

		$this->go_to( get_permalink( $post->ID ) );

		$block = array(
			'attrs' => array(
				'postId' => -5,
			),
		);

		$result = $instance->get_post_id( $block );

		// Should fall back to get_the_ID() when postId is negative.
		$this->assertSame( $post->ID, $result );
	}
}
