<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Event_Date.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Assets;
use GatherPress\Core\Blocks\Event_Date;
use GatherPress\Core\Event;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

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
	 * Escapes markup supplied through the separator attribute.
	 *
	 * The separator is human-readable text and must not become live markup,
	 * even though the final output allows the block's own anchor and time tags.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_escapes_separator_markup(): void {
		$event_post = $this->mock->post(
			array(
				'post_title' => 'Escaped Separator Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();
		$event      = new Event( $event_post->ID );
		$event->save_datetimes(
			array(
				'datetime_start' => '2020-05-11 15:00:00',
				'datetime_end'   => '2020-05-11 17:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$this->go_to( get_permalink( $event_post->ID ) );

		$separator = '<a href="https://attacker.example">to</a>';
		$render    = do_blocks(
			sprintf(
				'<!-- wp:gatherpress/event-date %s /-->',
				wp_json_encode(
					array(
						'displayType' => 'both',
						'separator'   => $separator,
					)
				)
			)
		);

		$this->assertStringContainsString( esc_html( $separator ), $render );
		$this->assertStringNotContainsString( 'href="https://attacker.example"', $render );
	}

	/**
	 * Parity between the rendered block text and Event::get_display_datetime().
	 *
	 * Strips the <time>/<a> markup out of the block render and asserts the
	 * remaining text matches get_display_datetime() invoked with the same
	 * attribute args. Locks the block render to the Event class so the two
	 * display-logic copies cannot silently drift.
	 *
	 * Covers the timezone override case (block showTimezone=yes with the global
	 * setting off) that previously diverged between the two.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_matches_get_display_datetime(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'l, F j, Y',
				'time_format'   => 'g:i A',
				'show_timezone' => false,
			)
		);

		$event_post = $this->mock->post(
			array(
				'post_title' => 'Parity Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();
		$event      = new Event( $event_post->ID );
		$event->save_datetimes(
			array(
				'datetime_start' => '2020-05-11 15:00:00',
				'datetime_end'   => '2020-05-11 17:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$this->go_to( get_permalink( $event_post->ID ) );

		$combinations = array(
			'both, no override'  => array(
				array( 'displayType' => 'both' ),
				array( 'both' ),
			),
			'both, override yes' => array(
				array(
					'displayType'  => 'both',
					'showTimezone' => 'yes',
				),
				array( 'both', '', '', '', 'yes' ),
			),
			'both, override no'  => array(
				array(
					'displayType'  => 'both',
					'showTimezone' => 'no',
				),
				array( 'both', '', '', '', 'no' ),
			),
			'start only'         => array(
				array( 'displayType' => 'start' ),
				array( 'start' ),
			),
			'end only'           => array(
				array( 'displayType' => 'end' ),
				array( 'end' ),
			),
		);

		foreach ( $combinations as $label => list( $block_attrs, $event_args ) ) {
			$render = do_blocks(
				sprintf(
					'<!-- wp:gatherpress/event-date %s /-->',
					wp_json_encode( $block_attrs )
				)
			);

			$expected = call_user_func_array(
				array( $event, 'get_display_datetime' ),
				$event_args
			);

			$this->assertSame(
				$expected,
				wp_strip_all_tags( $render ),
				sprintf( 'Rendered text diverged from get_display_datetime() for %s.', $label )
			);
		}

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for the machine-readable <time datetime> output.
	 *
	 * Asserts a <time datetime="..."> element is emitted for every shown
	 * endpoint and that the datetime attribute holds the full unfiltered ISO
	 * value, while the no-datetime placeholder emits none.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_emits_machine_readable_time_elements(): void {
		update_option(
			'gatherpress_settings',
			array(
				'date_format'   => 'l, F j, Y',
				'time_format'   => 'g:i A',
				'show_timezone' => true,
			)
		);

		$event_post = $this->mock->post(
			array(
				'post_title' => 'Machine Readable Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();
		$event      = new Event( $event_post->ID );
		$event->save_datetimes(
			array(
				'datetime_start' => '2020-05-11 15:00:00',
				'datetime_end'   => '2020-05-11 17:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$this->go_to( get_permalink( $event_post->ID ) );

		$render = do_blocks( '<!-- wp:gatherpress/event-date {"displayType":"both"} /-->' );

		$this->assertStringContainsString(
			sprintf( '<time datetime="%s">', esc_attr( $event->get_datetime_start_iso() ) ),
			$render,
			'Start datetime should be wrapped in a time element with an ISO datetime attribute.'
		);
		$this->assertStringContainsString(
			sprintf( '<time datetime="%s">', esc_attr( $event->get_datetime_end_iso() ) ),
			$render,
			'End datetime should be wrapped in a time element with an ISO datetime attribute.'
		);

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for the no-datetime placeholder.
	 *
	 * An event without saved datetimes renders the placeholder with no
	 * <time datetime> element.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_placeholder_has_no_time_element(): void {
		$event_post = $this->mock->post(
			array(
				'post_title' => 'No Date Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$render = do_blocks( '<!-- wp:gatherpress/event-date /-->' );

		$this->assertStringContainsString( esc_html( Event::DATETIME_PLACEHOLDER ), $render );
		$this->assertStringNotContainsString( '<time ', $render );
	}

	/**
	 * Render the block for an event fixed at 18:00 to 20:00 New York time.
	 *
	 * @since TBD
	 *
	 * @param string $title      Post title, so each test gets its own event.
	 * @param array  $attributes Block attributes to render with.
	 *
	 * @return string The rendered block.
	 */
	private function render_viewer_time_block( string $title, array $attributes ): string {
		Settings::get_instance()->set( 'show_viewer_timezone', true );

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
	 * Read back the Interactivity API context the block wrapper carries.
	 *
	 * @since TBD
	 *
	 * @param string $output Rendered block.
	 *
	 * @return array|null The decoded context, or null when no context was rendered.
	 */
	private function get_viewer_time_context( string $output ): ?array {
		if ( ! preg_match( '/data-wp-context=\'([^\']*)\'/', $output, $matches ) ) {
			return null;
		}

		return json_decode( html_entity_decode( $matches[1] ), true );
	}

	/**
	 * The showViewerTime attribute emits the tooltip markup and context for the
	 * view module, carrying the event's GMT datetimes and its own timezone.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_emits_viewer_time_tooltip(): void {
		$output = $this->render_viewer_time_block(
			'Viewer Time Unit Test Event',
			array( 'showViewerTime' => true )
		);

		$this->assertStringContainsString(
			'data-wp-class--gatherpress-tooltip="state.hasViewerTime"',
			$output,
			'The datetime element should bind the tooltip class.'
		);
		$this->assertStringContainsString(
			'data-wp-bind--data-gatherpress-tooltip="state.viewerTimeLabel"',
			$output,
			'The datetime element should bind the tooltip label.'
		);
		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output,
			'The wrapper should join the gatherpress interactivity store.'
		);
		$this->assertStringContainsString(
			'data-wp-text="state.viewerTimeSrLabel"',
			$output,
			'The screen reader label should be bound to derived state.'
		);

		$context = $this->get_viewer_time_context( $output );

		$this->assertSame(
			'2030-06-15 22:00:00',
			$context['startGmt'] ?? null,
			'The context should carry the GMT start so the browser can convert it.'
		);
		$this->assertSame(
			'2030-06-16 00:00:00',
			$context['endGmt'] ?? null,
			'The context should carry the GMT end.'
		);
		$this->assertSame(
			'America/New_York',
			$context['eventTimezone'] ?? null,
			'The context should carry the event timezone to compare against.'
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
	}

	/**
	 * The rangeFormat honors custom separator attributes and the gatherpress_datetime_separator filter.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_viewer_time_honors_custom_separator_and_filter(): void {
		$output = $this->render_viewer_time_block(
			'Viewer Time Custom Separator Event',
			array(
				'showViewerTime' => true,
				'separator'      => ' - ',
			)
		);

		$context = $this->get_viewer_time_context( $output );

		$this->assertSame(
			'%1$s - %2$s your time',
			$context['rangeFormat'] ?? null,
			'The rangeFormat should incorporate the custom separator attribute.'
		);

		$filter_callback = function () {
			return 'until';
		};
		add_filter( 'gatherpress_datetime_separator', $filter_callback );

		$output_filtered  = $this->render_viewer_time_block(
			'Viewer Time Filtered Separator Event',
			array( 'showViewerTime' => true )
		);
		$context_filtered = $this->get_viewer_time_context( $output_filtered );

		$this->assertSame(
			'%1$s until %2$s your time',
			$context_filtered['rangeFormat'] ?? null,
			'The rangeFormat should honor the gatherpress_datetime_separator filter.'
		);

		remove_filter( 'gatherpress_datetime_separator', $filter_callback );
	}

	/**
	 * A block showing only the start says only the start in local time too,
	 * rather than announcing a range the block itself never displays.
	 *
	 * @since TBD
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
			'The context should still carry the GMT start.'
		);
		$this->assertSame(
			'',
			$context['endGmt'] ?? null,
			'The context should carry no end when the block does not display one.'
		);
	}

	/**
	 * A block showing only the end converts that end.
	 *
	 * The end is the only time such a block displays, so it is the one the
	 * viewer needs converting. Mirrors get_display_datetime(), which shows the
	 * end alone for this display type rather than showing nothing.
	 *
	 * @since TBD
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
			'data-wp-class--gatherpress-tooltip="state.hasViewerTime"',
			$output,
			'The tooltip should be enabled for an end-only block.'
		);

		$context = $this->get_viewer_time_context( $output );

		$this->assertSame(
			'',
			$context['startGmt'] ?? null,
			'The context should carry no start when the block does not display one.'
		);
		$this->assertSame(
			'2030-06-16 00:00:00',
			$context['endGmt'] ?? null,
			'The context should carry the GMT end for the browser to convert.'
		);
	}

	/**
	 * No tooltip without the attribute, so nothing changes for the blocks
	 * already out there.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_tooltip_by_default(): void {
		$event_post = $this->mock->post(
			array(
				'post_title' => 'No Viewer Time Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks( '<!-- wp:gatherpress/event-date /-->' );

		$this->assertStringNotContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'The viewer time tooltip should be absent by default.'
		);
	}

	/**
	 * Disabling the global setting suppresses the tooltip context even when
	 * the block-level attribute is enabled.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_when_global_setting_is_disabled(): void {
		$output = $this->render_viewer_time_block(
			'Disabled Global Setting Event',
			array( 'showViewerTime' => true )
		);

		// Now disable the global setting.
		Settings::get_instance()->set( 'show_viewer_timezone', false );

		$event_post = $this->mock->post(
			array(
				'post_title' => 'Global Disabled Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks( '<!-- wp:gatherpress/event-date {"showViewerTime":true} /-->' );

		$this->assertStringNotContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'The viewer time tooltip should be absent when global setting is disabled.'
		);
	}

	/**
	 * Turning off timezone appending suppresses the tooltip.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_when_timezone_is_not_appended(): void {
		$output = $this->render_viewer_time_block(
			'No Timezone Appended Event',
			array(
				'showViewerTime' => true,
				'showTimezone'   => 'no',
			)
		);

		$this->assertStringNotContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'The viewer time tooltip should be absent when timezone is not appended.'
		);
	}

	/**
	 * When isLink is enabled alongside showViewerTime, the tooltip bindings
	 * attach directly to the anchor.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_with_is_link_carries_tooltip_on_anchor(): void {
		$output = $this->render_viewer_time_block(
			'Linked Event With Viewer Time',
			array(
				'showViewerTime' => true,
				'isLink'         => true,
			)
		);

		$this->assertMatchesRegularExpression(
			'/<a\s[^>]*data-wp-class--gatherpress-tooltip="state\.hasViewerTime"/',
			$output,
			'The anchor should carry the tooltip binding when isLink is set.'
		);
	}

	/**
	 * All-day events do not display viewer time tooltips.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_for_all_day_event(): void {
		$event_post = $this->mock->post(
			array(
				'post_title' => 'All Day Event With Viewer Time',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		update_post_meta( $event_post->ID, 'gatherpress_is_all_day', 1 );

		$output = $this->render_viewer_time_block(
			'All Day Event With Viewer Time',
			array(
				'showViewerTime' => true,
				'showTimezone'   => 'yes',
			)
		);

		$this->assertStringNotContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'All-day events should not carry the viewer time tooltip.'
		);
	}

	/**
	 * Clean up tooltip assets after tests.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tear_down(): void {
		wp_dequeue_style( 'gatherpress-utility-style' );
		wp_dequeue_script( 'gatherpress-tooltip-view' );
		Utility::set_and_get_hidden_property( Assets::get_instance(), 'tooltip_assets_enqueued', false );
		Settings::get_instance()->set( 'show_viewer_timezone', false );
		parent::tear_down();
	}
}
