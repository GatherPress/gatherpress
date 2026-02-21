<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Online_Event.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Online_Event;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use WP_Block;

/**
 * Class Test_Online_Event.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Online_Event
 */
class Test_Online_Event extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Online_Event block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Online_Event::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Online_Event::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'render_block' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Tests BLOCK_NAME constant value.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_block_name_constant(): void {
		$this->assertSame(
			'gatherpress/online-event-v2',
			Online_Event::BLOCK_NAME,
			'BLOCK_NAME constant should be gatherpress/online-event-v2.'
		);
	}

	/**
	 * Tests render_block returns empty string when block_content is null.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_null_content(): void {
		$instance = Online_Event::get_instance();

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$result = $instance->render_block( null, array( 'attrs' => array() ), $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when block_content is null.'
		);
	}

	/**
	 * Tests render_block returns content when block array is null.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 *
	 * @return void
	 */
	public function test_render_block_returns_content_for_null_block(): void {
		$instance = Online_Event::get_instance();

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$result = $instance->render_block( 'test content', null, $block_instance );

		$this->assertSame(
			'test content',
			$result,
			'Should return block_content as-is when block array is null.'
		);
	}

	/**
	 * Tests render_block returns empty when both content and block are null.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_both_null(): void {
		$instance = Online_Event::get_instance();

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$result = $instance->render_block( null, null, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when both block_content and block are null.'
		);
	}

	/**
	 * Tests render_block returns empty when event has no online-event term.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_event_without_online_term(): void {
		$instance = Online_Event::get_instance();

		// Create an event post without the online-event term.
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="online-event-content">Test</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when event has no online-event term.'
		);
	}

	/**
	 * Tests render_block returns content for non-event post types.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 *
	 * @return void
	 */
	public function test_render_block_returns_content_for_non_event_post(): void {
		$instance = Online_Event::get_instance();

		// Create a regular page (not an event).
		$post_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $post_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="online-event-content">Test</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			$block_content,
			$result,
			'Should return block content unchanged for non-event post types.'
		);
	}

	/**
	 * Tests render_block returns content when event has online-event term.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 *
	 * @return void
	 */
	public function test_render_block_returns_content_for_event_with_online_term(): void {
		$instance = Online_Event::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create the online-event term if it doesn't exist.
		$term = term_exists( 'online-event', Venue::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( 'Online Event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}
		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		// Set the event as online.
		wp_set_object_terms( $event_id, (int) $term_id, Venue::TAXONOMY );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="online-event-content">Test</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			$block_content,
			$result,
			'Should return block content when event has online-event term and no postId override.'
		);
	}

	/**
	 * Tests render_block with postId attribute renders with context.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 * @covers ::render_with_post_context
	 *
	 * @return void
	 */
	public function test_render_block_with_post_id_override(): void {
		$instance = Online_Event::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Test Online Event',
			)
		);

		// Create the online-event term if it doesn't exist.
		$term = term_exists( 'online-event', Venue::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( 'Online Event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}
		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		// Set the event as online.
		wp_set_object_terms( $event_id, (int) $term_id, Venue::TAXONOMY );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array( 'postId' => $event_id ),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Inner content</p>',
						'innerContent' => array( '<p>Inner content</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$block_content = '<div class="online-event-content">Test</div>';
		$block         = array( 'attrs' => array( 'postId' => $event_id ) );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertStringContainsString(
			'Inner content',
			$result,
			'Should render inner blocks with post context when postId attribute is set.'
		);
	}

	/**
	 * Tests render_block returns empty for override event without online term.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_override_without_online_term(): void {
		$instance = Online_Event::get_instance();

		// Create an event post without online-event term.
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array( 'postId' => $event_id ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="online-event-content">Test</div>';
		$block         = array( 'attrs' => array( 'postId' => $event_id ) );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when override event does not have online-event term.'
		);
	}

	/**
	 * Tests has_online_event_term returns false when event has other venue terms.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_event_with_other_venue_term(): void {
		$instance = Online_Event::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create a different venue term (not online-event).
		$term    = wp_insert_term(
			'Physical Venue',
			Venue::TAXONOMY,
			array( 'slug' => '_venue-physical-test' )
		);
		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		// Set the event with a physical venue term.
		wp_set_object_terms( $event_id, (int) $term_id, Venue::TAXONOMY );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="online-event-content">Test</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when event has venue term but not online-event.'
		);
	}

	/**
	 * Tests render_with_post_context applies correct block context.
	 *
	 * This test verifies that the postId is correctly passed to inner blocks
	 * and that the render_block_context filter is properly applied.
	 *
	 * @since 1.0.0
	 * @covers ::render_with_post_context
	 *
	 * @return void
	 */
	public function test_render_with_post_context_applies_correct_context(): void {
		$instance = Online_Event::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Context Test Online Event',
			)
		);

		// Create the online-event term.
		$term = term_exists( 'online-event', Venue::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( 'Online Event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}
		$term_id = is_array( $term ) ? $term['term_id'] : $term;
		wp_set_object_terms( $event_id, (int) $term_id, Venue::TAXONOMY );

		// Track if context filter was called with correct postId.
		$captured_context = null;
		$capture_filter   = function ( $context ) use ( &$captured_context ) {
			$captured_context = $context;
			return $context;
		};
		add_filter( 'render_block_context', $capture_filter, PHP_INT_MAX );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array( 'postId' => $event_id ),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Test paragraph</p>',
						'innerContent' => array( '<p>Test paragraph</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$block = array( 'attrs' => array( 'postId' => $event_id ) );

		$result = $instance->render_block( '<div>Test</div>', $block, $block_instance );

		remove_filter( 'render_block_context', $capture_filter, PHP_INT_MAX );

		// Verify the context was passed with the correct postId.
		$this->assertNotNull(
			$captured_context,
			'render_block_context filter should have been called.'
		);
		$this->assertSame(
			$event_id,
			$captured_context['postId'],
			'Context should contain the correct postId.'
		);

		// Verify inner blocks were rendered.
		$this->assertStringContainsString(
			'Test paragraph',
			$result,
			'Inner blocks should be rendered.'
		);
	}

	/**
	 * Tests render_block returns content for override with non-event post type.
	 *
	 * When the postId attribute points to a non-event post type,
	 * has_online_event_term returns true, so it should render with context.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 * @covers ::render_with_post_context
	 *
	 * @return void
	 */
	public function test_render_block_with_non_event_override_renders_content(): void {
		$instance = Online_Event::get_instance();

		// Create a page (not an event).
		$page_id = $this->factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Test Page For Override',
			)
		);

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array( 'postId' => $page_id ),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Page inner content</p>',
						'innerContent' => array( '<p>Page inner content</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$block_content = '<div class="online-event-content">Test</div>';
		$block         = array( 'attrs' => array( 'postId' => $page_id ) );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertStringContainsString(
			'Page inner content',
			$result,
			'Should render inner blocks for non-event post type override.'
		);
	}

	/**
	 * Tests render_block with event that has both online-event and other venue terms.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 *
	 * @return void
	 */
	public function test_render_block_returns_content_for_event_with_multiple_terms(): void {
		$instance = Online_Event::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Create online-event term.
		$online_term = term_exists( 'online-event', Venue::TAXONOMY );
		if ( ! $online_term ) {
			$online_term = wp_insert_term( 'Online Event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}
		$online_term_id = is_array( $online_term ) ? $online_term['term_id'] : $online_term;

		// Create another venue term.
		$venue_term    = wp_insert_term(
			'Hybrid Venue',
			Venue::TAXONOMY,
			array( 'slug' => '_venue-hybrid-test' )
		);
		$venue_term_id = is_array( $venue_term ) ? $venue_term['term_id'] : $venue_term;

		// Set both terms on the event.
		wp_set_object_terms( $event_id, array( (int) $online_term_id, (int) $venue_term_id ), Venue::TAXONOMY );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="online-event-content">Test</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			$block_content,
			$result,
			'Should return block content when event has online-event term among multiple terms.'
		);
	}

	/**
	 * Tests get_the_ID is used when no postId override.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::has_online_event_term
	 *
	 * @return void
	 */
	public function test_render_block_uses_current_post_id_when_no_override(): void {
		$instance = Online_Event::get_instance();

		// Create two events - one with and one without online term.
		$online_event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$term = term_exists( 'online-event', Venue::TAXONOMY );
		if ( ! $term ) {
			$term = wp_insert_term( 'Online Event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}
		$term_id = is_array( $term ) ? $term['term_id'] : $term;
		wp_set_object_terms( $online_event_id, (int) $term_id, Venue::TAXONOMY );

		// Navigate to the online event.
		$this->go_to( get_permalink( $online_event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/online-event-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="online-event-content">Current Event</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			$block_content,
			$result,
			'Should render content using get_the_ID() when no postId override is set.'
		);
	}
}
