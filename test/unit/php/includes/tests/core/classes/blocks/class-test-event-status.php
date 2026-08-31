<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Event_Status.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Event_Status;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Status.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Event_Status
 */
class Test_Event_Status extends Base {

	/**
	 * Tests the setup_hooks method.
	 *
	 * @since 0.36.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Event_Status::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Event_Status::BLOCK_NAME );
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
	 * @since 0.36.0
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_valid_event(): void {
		$instance   = Event_Status::get_instance();
		$event_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$block      = array(
			'blockName' => Event_Status::BLOCK_NAME,
			'attrs'     => array( 'postId' => $event_post->ID ),
		);

		$content = '<div class="wp-block-gatherpress-event-status">Cancelled</div>';
		$result  = $instance->validate_event( $content, $block );

		$this->assertSame( $content, $result );
	}

	/**
	 * Test validate_event with an invalid post type.
	 *
	 * @since 0.36.0
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_invalid_post(): void {
		$instance     = Event_Status::get_instance();
		$regular_post = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$block        = array(
			'blockName' => Event_Status::BLOCK_NAME,
			'attrs'     => array( 'postId' => $regular_post->ID ),
		);

		$content = '<div class="wp-block-gatherpress-event-status">Cancelled</div>';
		$result  = $instance->validate_event( $content, $block );

		$this->assertSame( '', $result );
	}
}
