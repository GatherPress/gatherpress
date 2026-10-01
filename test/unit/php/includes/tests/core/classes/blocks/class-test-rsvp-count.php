<?php
/**
 * Class handles unit tests for the RSVP Count block.
 *
 * @package GatherPress\Core
 * @since 0.35.4
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Rsvp_Count.
 *
 * The block renders from `render.php`, which has no class to cover.
 *
 * @coversNothing
 */
class Test_Rsvp_Count extends Base {

	/**
	 * Render the block for an event.
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return string The rendered block.
	 */
	private function render_count( int $post_id ): string {
		return do_blocks( sprintf( '<!-- wp:gatherpress/rsvp-count {"postId":%d} /-->', $post_id ) );
	}

	/**
	 * A published event's count renders for anyone.
	 *
	 * @return void
	 */
	public function test_renders_for_a_published_event(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		wp_set_current_user( 0 );

		$this->assertStringContainsString(
			'gatherpress-rsvp-count__text',
			$this->render_count( $event_id ),
			'Failed to assert a published event renders its count.'
		);
	}

	/**
	 * An event the viewer could not open renders nothing, and renders for a
	 * viewer who can read it.
	 *
	 * @return void
	 */
	public function test_follows_event_visibility(): void {
		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'private',
			)
		);

		wp_set_current_user( 0 );
		$anonymous = $this->render_count( $event_id );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$reader = $this->render_count( $event_id );

		wp_set_current_user( 0 );

		$this->assertSame(
			'',
			trim( $anonymous ),
			'Failed to assert a private event renders nothing for an anonymous viewer.'
		);
		$this->assertStringContainsString(
			'gatherpress-rsvp-count__text',
			$reader,
			'Failed to assert a private event renders for a viewer who can read it.'
		);
	}

	/**
	 * A post that does not take RSVPs renders nothing.
	 *
	 * @return void
	 */
	public function test_renders_nothing_for_a_post_without_rsvps(): void {
		$post_id = $this->factory->post->create();

		$this->assertSame( '', trim( $this->render_count( $post_id ) ) );
	}
}
