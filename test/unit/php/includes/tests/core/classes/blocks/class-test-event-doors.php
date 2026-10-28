<?php
/**
 * Class handles unit tests for the Event Doors block.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Doors.
 *
 * The block renders from `render.php`, which has no class to cover.
 *
 * @coversNothing
 */
class Test_Event_Doors extends Base {

	/**
	 * Create an event with doors times.
	 *
	 * @since TBD
	 *
	 * @param string               $doors_open  Doors open time, or an empty string.
	 * @param string               $doors_close Doors close time, or an empty string.
	 * @param array<string, mixed> $post_args   Extra post arguments.
	 *
	 * @return int The event post ID.
	 */
	private function create_event( string $doors_open, string $doors_close, array $post_args = array() ): int {
		$post_id = $this->factory->post->create(
			array_merge( array( 'post_type' => Event::POST_TYPE ), $post_args )
		);

		update_post_meta( $post_id, 'gatherpress_doors_open', $doors_open );
		update_post_meta( $post_id, 'gatherpress_doors_close', $doors_close );

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2026-11-20 20:00:00',
				'datetime_end'   => '2026-11-20 23:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		return $post_id;
	}

	/**
	 * Render the block for a post.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string The rendered block.
	 */
	private function render_doors( int $post_id ): string {
		return do_blocks( sprintf( '<!-- wp:gatherpress/event-doors {"postId":%d} /-->', $post_id ) );
	}

	/**
	 * Both doors times render with a machine-readable datetime.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_renders_both_times(): void {
		update_option( 'time_format', 'g:i a' );

		$output = $this->render_doors( $this->create_event( '19:00', '23:30' ) );

		$this->assertStringContainsString(
			'<span class="gatherpress-event-doors__open">Doors open '
				. '<time datetime="2026-11-20T19:00:00-05:00">7:00 pm</time></span>',
			$output,
			'Failed to assert the doors open time renders.'
		);
		$this->assertStringContainsString(
			'<span class="gatherpress-event-doors__close">Doors close '
				. '<time datetime="2026-11-20T23:30:00-05:00">11:30 pm</time></span>',
			$output,
			'Failed to assert the doors close time renders.'
		);
	}

	/**
	 * A time that is not set is left out.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_renders_only_the_set_time(): void {
		$output = $this->render_doors( $this->create_event( '19:00', '' ) );

		$this->assertStringContainsString(
			'gatherpress-event-doors__open',
			$output,
			'Failed to assert the set time renders.'
		);
		$this->assertStringNotContainsString(
			'gatherpress-event-doors__close',
			$output,
			'Failed to assert the unset time is left out.'
		);
	}

	/**
	 * An event without doors times renders nothing.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_renders_nothing_without_times(): void {
		$this->assertSame(
			'',
			trim( $this->render_doors( $this->create_event( '', '' ) ) ),
			'Failed to assert an event without doors times renders nothing.'
		);
	}

	/**
	 * The block renders nothing where the event date would not show either.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_renders_nothing_where_the_event_is_hidden(): void {
		wp_set_current_user( 0 );

		$this->assertSame(
			'',
			trim( $this->render_doors( $this->create_event( '19:00', '', array( 'post_password' => 'secret' ) ) ) ),
			'Failed to assert a password-protected event renders nothing.'
		);
		$this->assertSame(
			'',
			trim( $this->render_doors( $this->create_event( '19:00', '', array( 'post_status' => 'private' ) ) ) ),
			'Failed to assert a private event renders nothing for an anonymous viewer.'
		);
		$this->assertSame(
			'',
			trim( $this->render_doors( $this->factory->post->create() ) ),
			'Failed to assert a post that is not an event renders nothing.'
		);
	}
}
