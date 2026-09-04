<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Event_Date.
 *
 * @package GatherPress\Core
 * @since 0.33.0
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
	 * @since 0.33.0
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

	/**
	 * Coverage for the rendered block with the isLink attribute enabled.
	 *
	 * Mirrors core/post-date's isLink behavior: the datetime output is
	 * wrapped in a link to the event.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_links_datetime_to_event_when_islink_set(): void {
		$event_post = $this->mock->post(
			array(
				'post_title' => 'Linked Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks( '<!-- wp:gatherpress/event-date {"isLink":true} /-->' );

		$this->assertStringContainsString(
			sprintf( '<a href="%s">', esc_url( get_permalink( $event_post->ID ) ) ),
			$output,
			'isLink should wrap the datetime in a link to the event.'
		);
	}

	/**
	 * Coverage for the rendered block without the isLink attribute.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function test_render_does_not_link_datetime_by_default(): void {
		$event_post = $this->mock->post(
			array(
				'post_title' => 'Unlinked Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks( '<!-- wp:gatherpress/event-date /-->' );

		$this->assertStringNotContainsString(
			'<a href=',
			$output,
			'The datetime should not be linked when isLink is not set.'
		);
	}

	/**
	 * Render the block for an event fixed at 18:00 to 20:00 New York time.
	 *
	 * @since 0.36.0
	 *
	 * @param string $title      Post title, so each test gets its own event.
	 * @param array  $attributes Block attributes to render with.
	 *
	 * @return string The rendered block.
	 */
	private function render_viewer_time_block( string $title, array $attributes ): string {
		$event_post = $this->mock->post(
			array(
				'post_title' => $title,
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$event = new Event( $event_post->ID );
		$event->save_datetimes(
			array(
				'datetime_start' => '2030-06-15 18:00:00',
				'datetime_end'   => '2030-06-15 20:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$this->go_to( get_permalink( $event_post->ID ) );

		return do_blocks(
			sprintf(
				'<!-- wp:gatherpress/event-date %s /-->',
				wp_json_encode( $attributes )
			)
		);
	}

	/**
	 * Read back the Interactivity API context the placeholder carries.
	 *
	 * @since 0.36.0
	 *
	 * @param string $output Rendered block.
	 *
	 * @return array|null The decoded context, or null when no placeholder was rendered.
	 */
	private function get_viewer_time_context( string $output ): ?array {
		if ( ! preg_match( '/data-wp-context=\'([^\']*)\'/', $output, $matches ) ) {
			return null;
		}

		return json_decode( html_entity_decode( $matches[1] ), true );
	}

	/**
	 * The showViewerTime attribute emits the placeholder the view module fills,
	 * carrying the event's GMT datetimes and its own timezone.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_render_emits_viewer_time_placeholder(): void {
		$output = $this->render_viewer_time_block(
			'Viewer Time Unit Test Event',
			array( 'showViewerTime' => true )
		);

		$this->assertStringContainsString(
			'gatherpress-event-date__viewer-time',
			$output,
			'The viewer time placeholder should be rendered when the attribute is set.'
		);
		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output,
			'The placeholder should join the gatherpress interactivity store.'
		);
		$this->assertStringContainsString(
			'data-wp-text="state.viewerTimeLabel"',
			$output,
			'The label should be bound to derived state so it survives a client-side page change.'
		);
		$context = $this->get_viewer_time_context( $output );

		$this->assertSame(
			'2030-06-15 22:00:00',
			$context['startGmt'] ?? null,
			'The placeholder should carry the GMT start so the browser can convert it.'
		);
		$this->assertSame(
			'2030-06-16 00:00:00',
			$context['endGmt'] ?? null,
			'The placeholder should carry the GMT end.'
		);
		$this->assertSame(
			'America/New_York',
			$context['eventTimezone'] ?? null,
			'The placeholder should carry the event timezone to compare against.'
		);
		$this->assertSame(
			'%1$s to %2$s your time',
			$context['rangeFormat'] ?? null,
			'The sentence is translated server-side because a script module cannot import @wordpress/i18n.'
		);
		$this->assertSame(
			'%s your time',
			$context['singleFormat'] ?? null,
			'The start-only sentence is translated server-side too.'
		);
		$this->assertMatchesRegularExpression(
			'/<span\s[^>]*class="gatherpress-event-date__viewer-time"[^>]*\shidden\s*>/',
			$output,
			'The placeholder should start hidden so no-JS readers see no empty note.'
		);
	}

	/**
	 * A block showing only the start says only the start in local time too,
	 * rather than announcing a range the block itself never displays.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_render_viewer_time_omits_end_when_display_type_is_start(): void {
		$output = $this->render_viewer_time_block(
			'Viewer Time Start Only Unit Test Event',
			array(
				'showViewerTime' => true,
				'displayType'    => 'start',
			)
		);

		$context = $this->get_viewer_time_context( $output );

		$this->assertSame(
			'2030-06-15 22:00:00',
			$context['startGmt'] ?? null,
			'The placeholder should still carry the GMT start.'
		);
		$this->assertSame(
			'',
			$context['endGmt'] ?? null,
			'The placeholder should carry no end when the block does not display one.'
		);
	}

	/**
	 * A block showing only the end converts that end.
	 *
	 * The end is the only time such a block displays, so it is the one the
	 * reader needs converting. Mirrors get_display_datetime(), which shows the
	 * end alone for this display type rather than showing nothing.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_render_viewer_time_converts_end_when_display_type_is_end(): void {
		$output = $this->render_viewer_time_block(
			'Viewer Time End Only Unit Test Event',
			array(
				'showViewerTime' => true,
				'displayType'    => 'end',
			)
		);

		$this->assertStringContainsString(
			'gatherpress-event-date__viewer-time',
			$output,
			'The viewer time placeholder should be rendered for an end-only block.'
		);

		$context = $this->get_viewer_time_context( $output );

		$this->assertSame(
			'',
			$context['startGmt'] ?? null,
			'The placeholder should carry no start when the block does not display one.'
		);
		$this->assertSame(
			'2030-06-16 00:00:00',
			$context['endGmt'] ?? null,
			'The placeholder should carry the GMT end for the browser to convert.'
		);
	}

	/**
	 * No placeholder without the attribute, so nothing changes for the blocks
	 * already out there.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_placeholder_by_default(): void {
		$event_post = $this->mock->post(
			array(
				'post_title' => 'No Viewer Time Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks( '<!-- wp:gatherpress/event-date /-->' );

		$this->assertStringNotContainsString(
			'gatherpress-event-date__viewer-time',
			$output,
			'The viewer time placeholder should be absent by default.'
		);
	}
}
