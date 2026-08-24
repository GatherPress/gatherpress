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
	 * @since 0.36.0
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
	 * @since 0.36.0
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
			'linked'             => array(
				array(
					'displayType' => 'both',
					'isLink'      => true,
				),
				array( 'both' ),
			),
			'custom separator'   => array(
				array(
					'displayType' => 'both',
					'separator'   => ' — ',
				),
				array( 'both', '', '', ' — ' ),
			),
		);

		foreach ( $combinations as $label => $data ) {
			list( $attributes, $method_args ) = $data;
			$block                            = sprintf(
				'<!-- wp:gatherpress/event-date %s /-->',
				wp_json_encode( $attributes )
			);
			$render                           = do_blocks( $block );

			// Strip <time>/<a> markup to compare the human text only.
			$rendered_text = trim( wp_strip_all_tags( $render ) );

			$expected = $event->get_display_datetime(
				$method_args[0] ?? '',
				$method_args[1] ?? '',
				$method_args[2] ?? '',
				$method_args[3] ?? '',
				$method_args[4] ?? ''
			);

			$this->assertSame(
				$expected,
				$rendered_text,
				sprintf( 'Block text should match get_display_datetime for "%s".', $label )
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
	 * An event without saved datetimes renders the em-dash placeholder with no
	 * <time datetime> element.
	 *
	 * @since 0.36.0
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
}
