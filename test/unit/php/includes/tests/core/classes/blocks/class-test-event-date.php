<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Event_Date.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Event_Date;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Date.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Event_Date
 */
class Test_Event_Date extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Event Date block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Event_Date::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Event_Date::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'validate_event' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test validate_event with a valid event.
	 *
	 * Verifies that the block content is returned when the block is
	 * connected to a valid event post.
	 *
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_valid_event(): void {
		$instance   = Event_Date::get_instance();
		$event_post = $this->mock->post(
			array(
				'post_title' => 'Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$block_content = '<div class="wp-block-gatherpress-event-date">May 11, 2024</div>';
		$block         = array(
			'blockName' => Event_Date::BLOCK_NAME,
		);

		// Set post context by navigating to the post.
		$this->go_to( get_permalink( $event_post->ID ) );

		$result = $instance->validate_event( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'Block content should be returned when event is valid'
		);
	}

	/**
	 * Test validate_event with a non-event post.
	 *
	 * Verifies that an empty string is returned when the block is
	 * not connected to an event post type.
	 *
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_non_event_post(): void {
		$instance     = Event_Date::get_instance();
		$regular_post = $this->mock->post(
			array(
				'post_title' => 'Unit Test Regular Post',
				'post_type'  => 'post',
			)
		)->get();

		$block_content = '<div class="wp-block-gatherpress-event-date">May 11, 2024</div>';
		$block         = array(
			'blockName' => Event_Date::BLOCK_NAME,
		);

		// Set post context by navigating to the post.
		$this->go_to( get_permalink( $regular_post->ID ) );

		$result = $instance->validate_event( $block_content, $block );

		$this->assertSame(
			'',
			$result,
			'Empty string should be returned when post is not an event'
		);
	}

	/**
	 * Test validate_event with postId override attribute.
	 *
	 * Verifies that the block validates correctly when using a postId
	 * attribute to reference a different event.
	 *
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_post_id_override(): void {
		$instance   = Event_Date::get_instance();
		$event_post = $this->mock->post(
			array(
				'post_title' => 'Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$block_content = '<div class="wp-block-gatherpress-event-date">May 11, 2024</div>';
		$block         = array(
			'blockName' => Event_Date::BLOCK_NAME,
			'attrs'     => array(
				'postId' => $event_post->ID,
			),
		);

		$result = $instance->validate_event( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'Block content should be returned when postId references a valid event'
		);
	}

	/**
	 * Test validate_event with postId override for non-event.
	 *
	 * Verifies that an empty string is returned when the postId attribute
	 * references a non-event post.
	 *
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_non_event_post_id_override(): void {
		$instance = Event_Date::get_instance();
		$post     = $this->mock->post(
			array(
				'post_title' => 'Unit Test Regular Post',
				'post_type'  => 'post',
			)
		)->get();

		$block_content = '<div class="wp-block-gatherpress-event-date">May 11, 2024</div>';
		$block         = array(
			'blockName' => Event_Date::BLOCK_NAME,
			'attrs'     => array(
				'postId' => $post->ID,
			),
		);

		$result = $instance->validate_event( $block_content, $block );

		$this->assertSame(
			'',
			$result,
			'Empty string should be returned when postId references a non-event post'
		);
	}

	/**
	 * Test validate_event with no post context.
	 *
	 * Verifies that an empty string is returned when there is no
	 * post context available (e.g., on archive pages).
	 *
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_no_post_context(): void {
		$instance = Event_Date::get_instance();

		$block_content = '<div class="wp-block-gatherpress-event-date">May 11, 2024</div>';
		$block         = array(
			'blockName' => Event_Date::BLOCK_NAME,
		);

		// Navigate to home (no post context).
		$this->go_to( home_url() );

		$result = $instance->validate_event( $block_content, $block );

		$this->assertSame(
			'',
			$result,
			'Empty string should be returned when there is no post context'
		);
	}

	/**
	 * Test validate_event with empty block content.
	 *
	 * Verifies that the method handles empty block content gracefully,
	 * returning the empty content rather than processing it.
	 *
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_empty_content(): void {
		$instance   = Event_Date::get_instance();
		$event_post = $this->mock->post(
			array(
				'post_title' => 'Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$block_content = '';
		$block         = array(
			'blockName' => Event_Date::BLOCK_NAME,
		);

		// Set post context by navigating to the post.
		$this->go_to( get_permalink( $event_post->ID ) );

		$result = $instance->validate_event( $block_content, $block );

		$this->assertSame(
			'',
			$result,
			'Empty content should be returned as-is when block content is empty'
		);
	}
}
