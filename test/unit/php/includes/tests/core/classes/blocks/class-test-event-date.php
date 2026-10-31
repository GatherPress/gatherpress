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
use GatherPress\Core\Blocks\Event_Date\Html_Renderer;
use GatherPress\Core\Event;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PasswordHash;
use PMC\Unit_Test\Utility;
use WP_Block;

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
	 * Test validate_event keeps a password-protected event's date behind its password.
	 *
	 * A block on another page can name the event through its postId, so the
	 * block has to ask for the password the event page would.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::validate_event
	 *
	 * @return void
	 */
	public function test_validate_event_with_password_protected_event(): void {
		$instance   = Event_Date::get_instance();
		$event_post = $this->mock->post(
			array(
				'post_title'    => 'Unit Test Event',
				'post_type'     => Event::POST_TYPE,
				'post_password' => 'secret',
			)
		)->get();

		$block_content = '<div class="wp-block-gatherpress-event-date">May 11, 2024</div>';
		$block         = array(
			'blockName' => Event_Date::BLOCK_NAME,
			'attrs'     => array(
				'postId' => $event_post->ID,
			),
		);

		$this->assertSame(
			'',
			$instance->validate_event( $block_content, $block ),
			'A password-protected event should keep its date from a viewer without the password.'
		);

		require_once ABSPATH . WPINC . '/class-phpass.php';

		// The cookie WordPress sets once a viewer enters the post password.
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = ( new PasswordHash( 8, true ) )->HashPassword( 'secret' );

		$result = $instance->validate_event( $block_content, $block );

		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		$this->assertSame(
			$block_content,
			$result,
			'A password-protected event should show its date to a viewer with the password.'
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @since 0.36.0
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @since 0.36.0
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @since 0.36.0
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @since 0.36.0
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @param string $title               Post title, so each test gets its own event.
	 * @param array  $attributes          Block attributes to render with.
	 * @param string $timezone_preference The event's own timezone preference: 'always', 'never' or ''.
	 *
	 * @return string The rendered block.
	 */
	private function render_viewer_time_block(
		string $title,
		array $attributes,
		string $timezone_preference = ''
	): string {
		Settings::get_instance()->set( 'show_viewer_timezone', true );

		$event_post = $this->mock->post(
			array(
				'post_title' => $title,
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		update_post_meta( $event_post->ID, 'gatherpress_show_timezone', $timezone_preference );

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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
	 *
	 * @return void
	 */
	public function test_render_viewer_time_honors_custom_separator_and_filter(): void {
		$output = $this->render_viewer_time_block(
			'Viewer Time Custom Separator Event',
			array(
				'showViewerTime' => true,
				'separator'      => '-',
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_when_global_setting_is_disabled(): void {
		$output = $this->render_viewer_time_block(
			'Disabled Global Setting Event',
			array( 'showViewerTime' => true )
		);

		$this->assertStringContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'The dated event should carry the tooltip while the global setting is on.'
		);

		// The same dated event, rendered again with the global setting off.
		Settings::get_instance()->set( 'show_viewer_timezone', false );

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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
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
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_for_all_day_event(): void {
		Settings::get_instance()->set( 'show_viewer_timezone', true );

		$event_post = $this->mock->post(
			array(
				'post_title' => 'All Day Event With Viewer Time',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		update_post_meta( $event_post->ID, 'gatherpress_is_all_day', 1 );

		$event = new Event( $event_post->ID );
		$event->save_datetimes(
			array(
				'datetime_start' => '2030-06-15 00:00:00',
				'datetime_end'   => '2030-06-15 23:59:59',
				'timezone'       => 'America/New_York',
			)
		);

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks(
			'<!-- wp:gatherpress/event-date {"showViewerTime":true,"showTimezone":"yes"} /-->'
		);

		$this->assertStringNotContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'All-day events should not carry the viewer time tooltip.'
		);
	}

	/**
	 * A viewer with a saved timezone gets no tooltip: the main line is already in that timezone.
	 *
	 * @since TBD
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_for_a_saved_timezone(): void {
		$user = $this->mock->user( true )->get();

		update_user_meta( $user->ID, 'gatherpress_timezone', 'Europe/London' );

		$output = $this->render_viewer_time_block(
			'Viewer Time Saved Timezone Event',
			array(
				'showViewerTime' => true,
				'showTimezone'   => 'yes',
			)
		);

		$this->assertStringNotContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'A saved timezone should leave out the viewer time tooltip.'
		);
	}

	/**
	 * The event's own timezone preference wins over the block's "Append time zone".
	 *
	 * @since TBD
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
	 *
	 * @return void
	 */
	public function test_render_viewer_time_follows_the_event_timezone_preference(): void {
		$output = $this->render_viewer_time_block(
			'Always Timezone Event',
			array(
				'showViewerTime' => true,
				'showTimezone'   => 'no',
			),
			'always'
		);

		$this->assertStringContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'An event that always shows its timezone should carry the tooltip.'
		);

		$output = $this->render_viewer_time_block(
			'Never Timezone Event',
			array(
				'showViewerTime' => true,
				'showTimezone'   => 'yes',
			),
			'never'
		);

		$this->assertStringNotContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'An event that never shows its timezone should leave out the tooltip.'
		);
	}

	/**
	 * An empty display type converts both the start and the end, as the block shows both.
	 *
	 * @since TBD
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
	 *
	 * @return void
	 */
	public function test_render_viewer_time_treats_an_empty_display_type_as_both(): void {
		$context = $this->get_viewer_time_context(
			$this->render_viewer_time_block(
				'Empty Display Type Event',
				array(
					'showViewerTime' => true,
					'displayType'    => '',
				)
			)
		);

		$this->assertSame( '2030-06-15 22:00:00', $context['startGmt'] ?? null );
		$this->assertSame( '2030-06-16 00:00:00', $context['endGmt'] ?? null );
	}

	/**
	 * An event without dates has no time to convert.
	 *
	 * @since TBD
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer
	 *
	 * @return void
	 */
	public function test_render_omits_viewer_time_without_dates(): void {
		Settings::get_instance()->set( 'show_viewer_timezone', true );

		$event_post = $this->mock->post(
			array(
				'post_title' => 'Undated Event With Viewer Time',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		$this->go_to( get_permalink( $event_post->ID ) );

		$output = do_blocks(
			'<!-- wp:gatherpress/event-date {"showViewerTime":true,"showTimezone":"yes"} /-->'
		);

		$this->assertStringNotContainsString(
			'data-wp-class--gatherpress-tooltip',
			$output,
			'An event without dates should leave out the tooltip.'
		);
	}

	/**
	 * A date without an ISO 8601 datetime is shown as plain text.
	 *
	 * @since TBD
	 * @covers \GatherPress\Core\Blocks\Event_Date\Html_Renderer::render_time
	 *
	 * @return void
	 */
	public function test_render_time_without_an_iso_datetime(): void {
		$renderer = new Html_Renderer(
			array(),
			new WP_Block( array( 'blockName' => Event_Date::BLOCK_NAME ) )
		);

		$this->assertSame(
			'Soon &amp; later',
			Utility::invoke_hidden_method( $renderer, 'render_time', array( 'Soon & later', '' ) )
		);
		$this->assertSame( '', Utility::invoke_hidden_method( $renderer, 'render_time', array( '', '' ) ) );
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
