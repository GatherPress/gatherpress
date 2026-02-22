<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Venue_V2.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Venue_V2;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use WP_Block;

/**
 * Class Test_Venue_V2.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Venue_V2
 */
class Test_Venue_V2 extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Venue_V2 block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Venue_V2::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Venue_V2::BLOCK_NAME );
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
	 * Tests render_block returns empty string when block_content is null.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_null_content(): void {
		$instance = Venue_V2::get_instance();

		// Create a minimal WP_Block instance.
		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
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
	 * Tests render_block returns empty string when block array is null.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 *
	 * @return void
	 */
	public function test_render_block_returns_content_for_null_block(): void {
		$instance = Venue_V2::get_instance();

		// Create a minimal WP_Block instance.
		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
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
	 * Tests render_block returns empty string when no venue is found.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_when_no_venue(): void {
		$instance = Venue_V2::get_instance();

		// Create a regular post (not an event).
		$post_id = $this->factory->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test venue block</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when no venue is found.'
		);
	}

	/**
	 * Tests render_block returns empty string for non-event post without venue.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_non_event_post(): void {
		$instance = Venue_V2::get_instance();

		// Create a page (not an event).
		$post_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $post_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test venue block</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string for non-event posts without venue.'
		);
	}

	/**
	 * Tests render_block with manually selected venue post ID.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 * @covers ::render_with_venue_context
	 *
	 * @return void
	 */
	public function test_render_block_with_selected_post_id(): void {
		$instance = Venue_V2::get_instance();

		// Create a venue post.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'  => 'gatherpress_venue',
				'post_title' => 'Test Venue',
			)
		);

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array( 'selectedPostId' => $venue_id ),
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

		$block_content = '<div class="venue-content">Test venue block</div>';
		$block         = array( 'attrs' => array( 'selectedPostId' => $venue_id ) );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		// The render_with_venue_context should render inner blocks.
		$this->assertStringContainsString(
			'Inner content',
			$result,
			'Should render inner blocks with venue context.'
		);
	}

	/**
	 * Tests render_block returns empty when selected post ID is not a venue.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_non_venue_selected_post_id(): void {
		$instance = Venue_V2::get_instance();

		// Create a regular post (not a venue).
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );
		$this->go_to( get_permalink( $post_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array( 'selectedPostId' => $post_id ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test venue block</div>';
		$block         = array( 'attrs' => array( 'selectedPostId' => $post_id ) );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when selected post is not a venue.'
		);
	}

	/**
	 * Tests render_block returns empty for online-only event (no physical venue).
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_online_only_event(): void {
		$instance = Venue_V2::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create( array( 'post_type' => 'gatherpress_event' ) );

		// Register the venue taxonomy if not registered.
		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			register_taxonomy( '_gatherpress_venue', 'gatherpress_event' );
		}

		// Create the online-event term if it doesn't exist.
		$term = term_exists( 'online-event', '_gatherpress_venue' );
		if ( ! $term ) {
			$term = wp_insert_term( 'Online Event', '_gatherpress_venue', array( 'slug' => 'online-event' ) );
		}
		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		// Set the event as online-only.
		wp_set_object_terms( $event_id, (int) $term_id, '_gatherpress_venue' );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test venue block</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string for online-only event (no physical venue).'
		);
	}

	/**
	 * Tests render_block with event that has a venue assigned.
	 *
	 * This test verifies the code path where an event has a venue term
	 * but the venue lookup doesn't find a matching venue post. This covers
	 * the fallback path in get_venue_post.
	 *
	 * Note: Testing the full event-to-venue rendering path would require
	 * get_page_by_path to work in the test environment, which has limitations.
	 * The render_with_venue_context method is fully tested via selectedPostId tests.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_with_event_venue_term_no_post(): void {
		$instance = Venue_V2::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => 'gatherpress_event',
				'post_status' => 'publish',
			)
		);

		// Create a venue term that doesn't correspond to any venue post.
		// This will exercise the code path where venue lookup returns null.
		$term    = wp_insert_term(
			'Nonexistent Venue',
			Venue::TAXONOMY,
			array( 'slug' => '_nonexistent-venue-slug' )
		);
		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		// Associate the event with the venue term.
		wp_set_object_terms( $event_id, (int) $term_id, Venue::TAXONOMY );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test venue block</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		// Without a valid venue post, the venue block should not render for events.
		$this->assertSame(
			'',
			$result,
			'Should return empty string when venue term exists but no matching venue post.'
		);
	}

	/**
	 * Tests render_block returns empty when event has no venue terms.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_event_with_no_terms(): void {
		$instance = Venue_V2::get_instance();

		// Create an event post without any venue terms.
		$event_id = $this->factory->post->create( array( 'post_type' => 'gatherpress_event' ) );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		// With no venue terms, the event has no venue, so the block should not render.
		$this->assertSame(
			'',
			$result,
			'Should return empty string when event has no venue terms.'
		);
	}

	/**
	 * Tests render_block returns empty when event has multiple venue terms but no valid venue post.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_multiple_terms_no_venue_post(): void {
		$instance = Venue_V2::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create( array( 'post_type' => 'gatherpress_event' ) );

		// Register the venue taxonomy if not registered.
		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			register_taxonomy( '_gatherpress_venue', 'gatherpress_event' );
		}

		// Create online-event term.
		$online_term = term_exists( 'online-event', '_gatherpress_venue' );
		if ( ! $online_term ) {
			$online_term = wp_insert_term( 'Online Event', '_gatherpress_venue', array( 'slug' => 'online-event' ) );
		}
		$online_term_id = is_array( $online_term ) ? $online_term['term_id'] : $online_term;

		// Create another venue term.
		$venue_term    = wp_insert_term(
			'Physical Venue',
			'_gatherpress_venue',
			array( 'slug' => 'physical-venue-test' )
		);
		$venue_term_id = is_array( $venue_term ) ? $venue_term['term_id'] : $venue_term;

		// Set both terms on the event.
		wp_set_object_terms( $event_id, array( (int) $online_term_id, (int) $venue_term_id ), '_gatherpress_venue' );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		// With multiple terms, the event is not online-only.
		// Without a valid venue post, the venue block should not render.
		$this->assertSame(
			'',
			$result,
			'Should return empty string when event has multiple venue terms but no valid venue post.'
		);
	}

	/**
	 * Tests render_with_venue_context restores original post.
	 *
	 * @since 1.0.0
	 * @covers ::render_with_venue_context
	 *
	 * @return void
	 */
	public function test_render_with_venue_context_restores_post(): void {
		$instance = Venue_V2::get_instance();

		// Create original post and venue.
		$original_post_id = $this->factory->post->create( array( 'post_title' => 'Original Post' ) );
		$venue_id         = $this->factory->post->create(
			array(
				'post_type'  => 'gatherpress_venue',
				'post_title' => 'Test Venue for Restore',
			)
		);

		// Set up the original post context.
		$this->go_to( get_permalink( $original_post_id ) );

		// Store reference to original post.
		global $post;
		$original_post = $post;

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array( 'selectedPostId' => $venue_id ),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>Inner</p>',
						'innerContent' => array( '<p>Inner</p>' ),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$block = array( 'attrs' => array( 'selectedPostId' => $venue_id ) );

		$instance->render_block( '<div>Test</div>', $block, $block_instance );

		// Verify the global post is restored.
		$this->assertSame(
			$original_post,
			$post,
			'Global $post should be restored after render_with_venue_context.'
		);
	}

	/**
	 * Tests render_block applies correct block context.
	 *
	 * This test verifies that when rendering with venue context, the venue post
	 * becomes the context for inner blocks by checking filter behavior.
	 *
	 * @since 1.0.0
	 * @covers ::render_with_venue_context
	 *
	 * @return void
	 */
	public function test_render_with_venue_context_applies_correct_context(): void {
		$instance = Venue_V2::get_instance();

		// Create a venue post with specific title.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'  => 'gatherpress_venue',
				'post_title' => 'Context Test Venue Unique',
			)
		);

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array( 'selectedPostId' => $venue_id ),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/post-title',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$block = array( 'attrs' => array( 'selectedPostId' => $venue_id ) );

		$result = $instance->render_block( '<div>Test</div>', $block, $block_instance );

		// The core/post-title block should render with the venue's title.
		$this->assertStringContainsString(
			'Context Test Venue Unique',
			$result,
			'Inner blocks should render with venue context (venue title should appear).'
		);
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
			'gatherpress/venue-v2',
			Venue_V2::BLOCK_NAME,
			'BLOCK_NAME constant should be gatherpress/venue-v2.'
		);
	}

	/**
	 * Tests render_block returns empty when selectedPostId is not an integer.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_returns_empty_for_non_integer_selected_id(): void {
		$instance = Venue_V2::get_instance();

		// Create a regular post for context.
		$post_id = $this->factory->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array( 'selectedPostId' => 'not-an-integer' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test</div>';
		$block         = array( 'attrs' => array( 'selectedPostId' => 'not-an-integer' ) );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when selectedPostId is not an integer.'
		);
	}

	/**
	 * Tests render_block with event that has a properly linked venue.
	 *
	 * This test creates an event with a venue term that maps to a real venue post,
	 * covering the success path where get_venue_post_from_event_post_id returns
	 * a valid venue post.
	 *
	 * @since 1.0.0
	 * @covers ::render_block
	 * @covers ::get_venue_post
	 * @covers ::render_with_venue_context
	 *
	 * @return void
	 */
	public function test_render_block_with_event_linked_to_venue(): void {
		$instance = Venue_V2::get_instance();

		// Create a venue post.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'linked-venue-for-event-test',
				'post_title' => 'Linked Venue For Event Test',
			)
		);

		// Get the venue post to ensure we have the correct slug.
		$venue_post = get_post( $venue_id );

		// Create the venue term with the correct slug format.
		$term_slug = Venue::get_instance()->get_venue_term_slug( $venue_post->post_name );
		wp_insert_term(
			'Linked Venue For Event Test',
			Venue::TAXONOMY,
			array( 'slug' => $term_slug )
		);

		// Create an event post.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		// Associate the event with the venue term.
		wp_set_post_terms( $event_id, $term_slug, Venue::TAXONOMY );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/post-title',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$block_content = '<div class="venue-content">Test venue block</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		// The venue title should appear in the rendered output.
		$this->assertStringContainsString(
			'Linked Venue For Event Test',
			$result,
			'Should render inner blocks with venue context from event.'
		);
	}

	/**
	 * Tests render_block when event has venue term but no matching venue post.
	 *
	 * @since 1.0.0
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_render_block_event_with_non_venue_term(): void {
		$instance = Venue_V2::get_instance();

		// Create an event post.
		$event_id = $this->factory->post->create( array( 'post_type' => 'gatherpress_event' ) );

		// Register the venue taxonomy if not registered.
		if ( ! taxonomy_exists( '_gatherpress_venue' ) ) {
			register_taxonomy( '_gatherpress_venue', 'gatherpress_event' );
		}

		// Create a venue term that doesn't follow the _venue-{id} pattern.
		$term    = wp_insert_term( 'Random Venue', '_gatherpress_venue', array( 'slug' => 'random-venue-slug' ) );
		$term_id = is_array( $term ) ? $term['term_id'] : $term;

		// Associate the event with the venue term.
		wp_set_object_terms( $event_id, (int) $term_id, '_gatherpress_venue' );

		$this->go_to( get_permalink( $event_id ) );

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block_content = '<div class="venue-content">Test</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->render_block( $block_content, $block, $block_instance );

		// The venue term doesn't map to a venue post, so the venue block should not render.
		$this->assertSame(
			'',
			$result,
			'Should return empty string when venue term does not map to a venue post.'
		);
	}

	/**
	 * Tests render_with_venue_context applies align class.
	 *
	 * @since 1.0.0
	 * @covers ::render_with_venue_context
	 *
	 * @return void
	 */
	public function test_render_with_venue_context_applies_align_class(): void {
		$instance = Venue_V2::get_instance();

		// Create a venue post.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'  => 'gatherpress_venue',
				'post_title' => 'Align Test Venue',
			)
		);

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(
					'selectedPostId' => $venue_id,
					'align'          => 'wide',
				),
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

		$block = array( 'attrs' => array( 'selectedPostId' => $venue_id ) );

		$result = $instance->render_block( '<div>Test</div>', $block, $block_instance );

		$this->assertStringContainsString(
			'alignwide',
			$result,
			'Should apply align class to wrapper.'
		);
	}

	/**
	 * Tests render_with_venue_context applies className attribute.
	 *
	 * @since 1.0.0
	 * @covers ::render_with_venue_context
	 *
	 * @return void
	 */
	public function test_render_with_venue_context_applies_classname(): void {
		$instance = Venue_V2::get_instance();

		// Create a venue post.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'  => 'gatherpress_venue',
				'post_title' => 'ClassName Test Venue',
			)
		);

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(
					'selectedPostId' => $venue_id,
					'className'      => 'custom-class another-class',
				),
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

		$block = array( 'attrs' => array( 'selectedPostId' => $venue_id ) );

		$result = $instance->render_block( '<div>Test</div>', $block, $block_instance );

		$this->assertStringContainsString(
			'custom-class another-class',
			$result,
			'Should apply className attribute to wrapper.'
		);
	}

	/**
	 * Tests render_with_venue_context applies both align and className.
	 *
	 * @since 1.0.0
	 * @covers ::render_with_venue_context
	 *
	 * @return void
	 */
	public function test_render_with_venue_context_applies_align_and_classname(): void {
		$instance = Venue_V2::get_instance();

		// Create a venue post.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'  => 'gatherpress_venue',
				'post_title' => 'Combined Test Venue',
			)
		);

		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(
					'selectedPostId' => $venue_id,
					'align'          => 'full',
					'className'      => 'my-custom-class',
				),
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

		$block = array( 'attrs' => array( 'selectedPostId' => $venue_id ) );

		$result = $instance->render_block( '<div>Test</div>', $block, $block_instance );

		$this->assertStringContainsString(
			'wp-block-gatherpress-venue-v2',
			$result,
			'Should include base wrapper class.'
		);
		$this->assertStringContainsString(
			'alignfull',
			$result,
			'Should apply align class.'
		);
		$this->assertStringContainsString(
			'my-custom-class',
			$result,
			'Should apply className.'
		);
	}

	/**
	 * Tests get_venue_post with postId override attribute.
	 *
	 * This tests the Block::get_post_id() path where postId comes from
	 * block attributes (used for query loop context).
	 *
	 * @since 1.0.0
	 * @covers ::get_venue_post
	 * @covers ::render_with_venue_context
	 *
	 * @return void
	 */
	public function test_get_venue_post_with_postid_override(): void {
		$instance = Venue_V2::get_instance();

		// Create a venue post.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'override-venue-test',
				'post_title' => 'Override Venue Test',
			)
		);

		// Get the venue post to ensure we have the correct slug.
		$venue_post = get_post( $venue_id );

		// Create the venue term with the correct slug format.
		$term_slug = Venue::get_instance()->get_venue_term_slug( $venue_post->post_name );
		wp_insert_term(
			'Override Venue Test',
			Venue::TAXONOMY,
			array( 'slug' => $term_slug )
		);

		// Create an event post and link it to the venue.
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		wp_set_post_terms( $event_id, $term_slug, Venue::TAXONOMY );

		// Create a different post for global context (not an event).
		$other_post_id = $this->factory->post->create();
		$this->go_to( get_permalink( $other_post_id ) );

		// Block attributes include postId override (query loop context).
		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(
					'postId' => $event_id, // Override to event ID.
				),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/post-title',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array( null ),
			)
		);

		$block = array( 'attrs' => array( 'postId' => $event_id ) );

		$result = $instance->render_block( '<div>Test</div>', $block, $block_instance );

		// The venue title should appear in the rendered output.
		$this->assertStringContainsString(
			'Override Venue Test',
			$result,
			'Should use postId attribute to get event and render venue context.'
		);
	}

	/**
	 * Tests get_venue_post returns null when postId override points to non-event.
	 *
	 * @since 1.0.0
	 * @covers ::get_venue_post
	 *
	 * @return void
	 */
	public function test_get_venue_post_returns_null_for_non_event_postid_override(): void {
		$instance = Venue_V2::get_instance();

		// Create a regular post (not an event).
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		// Create an event as global context.
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$this->go_to( get_permalink( $event_id ) );

		// Block attributes include postId override pointing to non-event.
		$block_instance = new WP_Block(
			array(
				'blockName'    => 'gatherpress/venue-v2',
				'attrs'        => array(
					'postId' => $post_id, // Override to non-event post ID.
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$block = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $instance->render_block( '<div>Test</div>', $block, $block_instance );

		$this->assertSame(
			'',
			$result,
			'Should return empty when postId override points to non-event.'
		);
	}
}
