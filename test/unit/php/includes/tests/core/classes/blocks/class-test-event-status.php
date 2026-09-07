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

		$content = '<div class="wp-block-gatherpress-event-status">Canceled</div>';
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

		$content = '<div class="wp-block-gatherpress-event-status">Canceled</div>';
		$result  = $instance->validate_event( $content, $block );

		$this->assertSame( '', $result );
	}

	/**
	 * An event the visitor may not read renders nothing.
	 *
	 * The regular-post case above leaves at the support check and never
	 * reaches the viewability one, so this covers the second arm.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_unviewable_event(): void {
		$instance   = Event_Status::get_instance();
		$event_post = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		)->get();

		$this->mock->user( 'subscriber' );

		$block = array(
			'blockName' => Event_Status::BLOCK_NAME,
			'attrs'     => array( 'postId' => $event_post->ID ),
		);

		$this->assertSame(
			'',
			$instance->validate_event( '<div>Canceled</div>', $block ),
			'Failed to assert an unreadable event renders nothing.'
		);
	}

	/**
	 * A scheduled event says nothing unless asked to.
	 *
	 * Most events are simply going ahead, so the block stays out of the way
	 * by default and only speaks when something has changed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_render_hides_a_scheduled_event_by_default(): void {
		$event_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$this->assertSame(
			'',
			trim( do_blocks( '<!-- wp:gatherpress/event-status /-->' ) ),
			'Failed to assert a scheduled event renders nothing by default.'
		);
	}

	/**
	 * A scheduled event renders once the author turns the hiding off.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_render_shows_a_scheduled_event_when_asked(): void {
		$event_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks( '<!-- wp:gatherpress/event-status {"hideScheduled":false} /-->' );

		$this->assertStringContainsString(
			'Scheduled',
			$output,
			'Failed to assert the status is named when hiding is turned off.'
		);
		$this->assertStringContainsString(
			'gatherpress-event-status--is-scheduled',
			$output,
			'Failed to assert the rendered block carries its status class.'
		);
	}

	/**
	 * An event that is no longer going ahead names its status.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_render_names_a_changed_status(): void {
		$event_post = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		( new Event( $event_post->ID ) )->set_status( Event::STATUS_CANCELED );

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks( '<!-- wp:gatherpress/event-status /-->' );

		$this->assertStringContainsString(
			'Canceled',
			$output,
			'Failed to assert a canceled event is announced.'
		);
		$this->assertStringContainsString(
			'gatherpress-event-status--is-cancelled',
			$output,
			'Failed to assert the rendered block carries the canceled class.'
		);
	}
}
