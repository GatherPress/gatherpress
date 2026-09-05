<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Setup.
 *
 * @package GatherPress\Core\Blocks
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block_Pattern_Categories_Registry;
use WP_Block_Patterns_Registry;
use WP_Block_Type_Registry;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Setup
 * @group              blocks
 */
class Test_Setup extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'register_block_type_args',
				'priority' => 10,
				'callback' => array( $instance, 'enable_context_for_core_query_block' ),
			),
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
			array(
				'type'     => 'filter',
				'name'     => 'render_block',
				'priority' => 10,
				'callback' => array( $instance, 'announce_new_tab_links' ),
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
		$instance            = Setup::get_instance();
		$blocks              = array(
			'gatherpress/add-to-calendar',
			'gatherpress/dropdown',
			'gatherpress/dropdown-item',
			'gatherpress/event-date',
			'gatherpress/form-field',
			'gatherpress/modal',
			'gatherpress/modal-content',
			'gatherpress/modal-manager',
			'gatherpress/online-event',
			'gatherpress/online-event-link',
			'gatherpress/rsvp',
			'gatherpress/rsvp-count',
			'gatherpress/rsvp-form',
			'gatherpress/rsvp-guest-count-display',
			'gatherpress/rsvp-response',
			'gatherpress/rsvp-response-toggle',
			'gatherpress/rsvp-template',
			'gatherpress/venue',
			'gatherpress/venue-detail',
			'gatherpress/venue-map',
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
	 * Coverage for register_block_patterns.
	 *
	 * @covers ::register_block_patterns
	 *
	 * @return void
	 */
	public function test_register_block_patterns(): void {
		$instance               = Setup::get_instance();
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
	 * Coverage for pattern category registration.
	 *
	 * The `gatherpress-event-query` category is what scopes the Event Query
	 * Loop variation's starter patterns to core/query's placeholder modal —
	 * the slug must match the variation namespace declared in
	 * src/variations/core/query/index.js.
	 *
	 * @covers ::register_block_patterns
	 *
	 * @return void
	 */
	public function test_register_block_pattern_categories(): void {
		$instance                = Setup::get_instance();
		$category_registry       = WP_Block_Pattern_Categories_Registry::get_instance();
		$expected_category_slugs = array(
			'gatherpress',
			'gatherpress-event-query',
		);

		// Clear out registered pattern categories so the assertion sees only what register_block_patterns adds.
		Utility::set_and_get_hidden_property( $category_registry, 'registered_categories', array() );

		$instance->register_block_patterns();

		$registered_slugs = wp_list_pluck( $category_registry->get_all_registered(), 'name' );

		foreach ( $expected_category_slugs as $slug ) {
			$this->assertContains(
				$slug,
				$registered_slugs,
				sprintf( 'Expected pattern category "%s" to be registered.', $slug )
			);
		}
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
		$instance = Setup::get_instance();

		// Just ensure the method runs without errors.
		// Block class instances are singletons, so they'll already be instantiated.
		$instance->register_block_classes();

		// Verify that calling it again doesn't cause issues (singletons should handle this).
		$instance->register_block_classes();

		$this->assertTrue( true );
	}

	/**
	 * Coverage for hook_blocks_into_patterns with event template pattern.
	 *
	 * @covers ::hook_blocks_into_patterns
	 *
	 * @return void
	 */
	public function test_hook_blocks_into_patterns_event_template(): void {
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

		$context = array(
			'name' => 'gatherpress/venue-template',
		);

		// Venue is now directly in the pattern content, not hooked.
		$hooked_blocks = $instance->hook_blocks_into_patterns(
			array(),
			'after',
			'gatherpress/venue',
			$context
		);

		$this->assertNotContains( 'gatherpress/venue', $hooked_blocks );
	}

	/**
	 * Coverage for hook_blocks_into_patterns with venue details pattern.
	 *
	 * @covers ::hook_blocks_into_patterns
	 *
	 * @return void
	 */
	public function test_hook_blocks_into_patterns_venue_details(): void {
		$instance = Setup::get_instance();

		$context = array(
			'name' => 'gatherpress/venue-details',
		);

		$hooked_blocks = $instance->hook_blocks_into_patterns(
			array(),
			'after',
			'core/post-title',
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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
	 * Coverage for get_post_id with postId in attributes.
	 *
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_get_post_id_with_post_id_attribute(): void {
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();
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
		$instance = Setup::get_instance();
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
		$instance = Setup::get_instance();
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

	/**
	 * Coverage for enable_context_for_core_query_block when uses_context is missing.
	 *
	 * @covers ::enable_context_for_core_query_block
	 *
	 * @return void
	 */
	public function test_adds_context_to_core_query_block_when_uses_context_missing(): void {
		$instance = Setup::get_instance();
		$args     = array();

		$result = $instance->enable_context_for_core_query_block(
			$args,
			'core/query'
		);

		$this->assertSame(
			array( 'postType', 'postId' ),
			$result['uses_context']
		);
	}

	/**
	 * Coverage for enable_context_for_core_query_block when uses_context exists already.
	 *
	 * @covers ::enable_context_for_core_query_block
	 *
	 * @return void
	 */
	public function test_preserves_existing_context_for_core_query_block(): void {
		$instance = Setup::get_instance();
		$args     = array(
			'uses_context' => array( 'queryId', 'layout' ),
		);

		$result = $instance->enable_context_for_core_query_block(
			$args,
			'core/query'
		);

		$this->assertSame(
			array( 'queryId', 'layout', 'postType', 'postId' ),
			$result['uses_context']
		);
	}

	/**
	 * Coverage for enable_context_for_core_query_block with non-query block.
	 *
	 * @covers ::enable_context_for_core_query_block
	 *
	 * @return void
	 */
	public function test_does_not_modify_non_query_blocks(): void {
		$instance = Setup::get_instance();
		$args     = array(
			'uses_context' => array( 'queryId' ),
		);

		$result = $instance->enable_context_for_core_query_block(
			$args,
			'core/paragraph'
		);

		$this->assertSame( $args, $result );
	}

	/**
	 * Data provider for new-tab announcements.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function data_new_tab_links(): array {
		return array(
			'a new-tab link is announced'         => array(
				'<a href="/x" target="_blank">Site</a>',
				1,
			),
			'a same-tab link is left alone'       => array(
				'<a href="/x">Site</a>',
				0,
			),
			'an unquoted target still counts'     => array(
				'<a href="/x" target=_blank>Site</a>',
				1,
			),
			'attribute order does not matter'     => array(
				'<a target="_blank" rel="noopener" href="/x">Site</a>',
				1,
			),
			'only the new-tab link is announced'  => array(
				'<a href="/a" target="_blank">A</a><a href="/b">B</a>',
				1,
			),
			'every new-tab link is announced'     => array(
				'<a href="/a" target="_blank">A</a><a href="/b" target="_blank">B</a>',
				2,
			),
			'the word in link text is not a tag'  => array(
				'<a href="/x">say _blank</a>',
				0,
			),
			'a link wrapping only an image'       => array(
				'<a href="/x" target="_blank"><img src="/m.png" alt="Map"></a>',
				1,
			),
			'an unclosed anchor is left as it is' => array(
				'<a href="/x" target="_blank">Site',
				0,
			),
			'an uppercase target still counts'    => array(
				'<a href="/x" target="_BLANK">Site</a>',
				1,
			),
			'a mixed-case target still counts'    => array(
				'<a href="/x" target="_Blank">Site</a>',
				1,
			),
		);
	}

	/**
	 * Coverage for announce_new_tab_links.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::announce_new_tab_links
	 * @covers ::insert_new_tab_notices
	 *
	 * @dataProvider data_new_tab_links
	 *
	 * @param string $content  Markup handed to the filter.
	 * @param int    $expected Number of notices expected.
	 *
	 * @return void
	 */
	public function test_announce_new_tab_links( string $content, int $expected ): void {
		$instance = Setup::get_instance();
		$result   = $instance->announce_new_tab_links(
			$content,
			array( 'blockName' => 'gatherpress/venue-detail' )
		);

		$this->assertSame(
			$expected,
			substr_count( $result, Setup::NEW_TAB_CLASS ),
			'Failed to assert the expected number of new-tab notices.'
		);
		$this->assertStringNotContainsString(
			Setup::NEW_TAB_ATTRIBUTE,
			$result,
			'Failed to assert the marker attribute was removed.'
		);
	}

	/**
	 * The notice sits inside the link, after its text.
	 *
	 * A link's accessible name is its text content, so the notice has to be a
	 * child of the anchor and follow the label rather than lead it.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::announce_new_tab_links
	 * @covers ::insert_new_tab_notices
	 *
	 * @return void
	 */
	public function test_announce_new_tab_links_places_the_notice_inside_the_link(): void {
		$instance = Setup::get_instance();
		$result   = $instance->announce_new_tab_links(
			'<a href="/x" target="_blank">Site</a>',
			array( 'blockName' => 'gatherpress/venue-detail' )
		);

		$expected = sprintf(
			'<a href="/x" target="_blank">Site<span class="screen-reader-text %s"> %s</span></a>',
			Setup::NEW_TAB_CLASS,
			'(opens in a new tab)'
		);

		$this->assertSame(
			$expected,
			$result,
			'Failed to assert the notice follows the link text inside the anchor.'
		);
	}

	/**
	 * Blocks from other plugins are never touched.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::announce_new_tab_links
	 *
	 * @return void
	 */
	public function test_announce_new_tab_links_ignores_other_blocks(): void {
		$instance = Setup::get_instance();
		$content  = '<a href="/x" target="_blank">Site</a>';

		foreach ( array( 'core/paragraph', 'acme/thing', '' ) as $block_name ) {
			$this->assertSame(
				$content,
				$instance->announce_new_tab_links( $content, array( 'blockName' => $block_name ) ),
				'Failed to assert a non-GatherPress block is left alone.'
			);
		}

		$this->assertSame(
			$content,
			$instance->announce_new_tab_links( $content, array() ),
			'Failed to assert a block with no name is left alone.'
		);
	}

	/**
	 * Running twice does not announce twice.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::announce_new_tab_links
	 * @covers ::insert_new_tab_notices
	 *
	 * @return void
	 */
	public function test_announce_new_tab_links_is_idempotent(): void {
		$instance = Setup::get_instance();
		$block    = array( 'blockName' => 'gatherpress/venue-detail' );
		$once     = $instance->announce_new_tab_links( '<a href="/x" target="_blank">Site</a>', $block );
		$twice    = $instance->announce_new_tab_links( $once, $block );

		$this->assertSame(
			$once,
			$twice,
			'Failed to assert a second pass leaves the markup unchanged.'
		);
	}

	/**
	 * A second link is still announced when the first already carries a notice.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::announce_new_tab_links
	 * @covers ::insert_new_tab_notices
	 *
	 * @return void
	 */
	public function test_announce_new_tab_links_announces_a_link_added_beside_an_announced_one(): void {
		$instance = Setup::get_instance();
		$block    = array( 'blockName' => 'gatherpress/venue-detail' );
		$first    = $instance->announce_new_tab_links( '<a href="/a" target="_blank">A</a>', $block );
		$result   = $instance->announce_new_tab_links(
			$first . '<a href="/b" target="_blank">B</a>',
			$block
		);

		$this->assertSame(
			2,
			substr_count( $result, Setup::NEW_TAB_CLASS ),
			'Failed to assert both links carry exactly one notice.'
		);
	}

	/**
	 * Other screen-reader text in the link does not count as a notice.
	 *
	 * The tooltip format puts its own `.screen-reader-text` inside the trigger,
	 * which can sit inside a link. The marker class is what tells our notice
	 * apart, so the guard against announcing twice does not mistake a tooltip
	 * for one and leave the link silent.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::announce_new_tab_links
	 * @covers ::insert_new_tab_notices
	 *
	 * @return void
	 */
	public function test_announce_new_tab_links_looks_past_other_screen_reader_text(): void {
		$instance = Setup::get_instance();
		$result   = $instance->announce_new_tab_links(
			'<a href="/x" target="_blank">Join<span class="gatherpress-tooltip">'
			. '<span class="screen-reader-text">Opens Zoom</span></span></a>',
			array( 'blockName' => 'gatherpress/online-event-link' )
		);

		$this->assertSame(
			1,
			substr_count( $result, Setup::NEW_TAB_CLASS ),
			'Failed to assert a link carrying a tooltip is still announced.'
		);
		$this->assertStringEndsWith(
			sprintf( '<span class="screen-reader-text %s"> (opens in a new tab)</span></a>', Setup::NEW_TAB_CLASS ),
			$result,
			'Failed to assert the notice is the last thing inside the link.'
		);
	}

	/**
	 * A marker left in authored content does not misplace a notice.
	 *
	 * The marker is private to the two passes below, but nothing stops a
	 * block's content carrying the same attribute. It is cleared from
	 * anything that is not a qualifying link so the splice cannot land on
	 * the wrong element.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::announce_new_tab_links
	 * @covers ::insert_new_tab_notices
	 *
	 * @return void
	 */
	public function test_announce_new_tab_links_ignores_a_marker_in_the_content(): void {
		$instance = Setup::get_instance();
		$result   = $instance->announce_new_tab_links(
			sprintf(
				'<a href="/same" %s="1">Same tab</a><a href="/new" target="_blank">New tab</a>',
				Setup::NEW_TAB_ATTRIBUTE
			),
			array( 'blockName' => 'gatherpress/venue-detail' )
		);

		$this->assertSame(
			1,
			substr_count( $result, Setup::NEW_TAB_CLASS ),
			'Failed to assert only the new-tab link is announced.'
		);
		$this->assertStringContainsString(
			'New tab<span',
			$result,
			'Failed to assert the notice landed on the new-tab link.'
		);
		$this->assertStringNotContainsString(
			Setup::NEW_TAB_ATTRIBUTE,
			$result,
			'Failed to assert no marker attribute survives.'
		);
	}
}
