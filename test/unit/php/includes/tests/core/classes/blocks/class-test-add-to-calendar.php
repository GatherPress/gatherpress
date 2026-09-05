<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Add_To_Calendar.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Add_To_Calendar;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Add_To_Calendar.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Add_To_Calendar
 */
class Test_Add_To_Calendar extends Base {

	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Add_To_Calendar block.
	 *
	 * @since 0.33.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Add_To_Calendar::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Add_To_Calendar::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'replace_calendar_placeholders' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 11,
				'callback' => array( $instance, 'inject_new_tab_notices' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test replace_calendar_placeholders with all calendar links present.
	 *
	 * @covers ::replace_calendar_placeholders
	 *
	 * @return void
	 */
	public function test_replace_calendar_placeholders_with_all_links(): void {
		$instance = Add_To_Calendar::get_instance();
		$venue    = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Unit Test Venue',
				'post_name'  => 'unit-test-venue',
			)
		)->get();
		$post     = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
				'post_date'    => '2020-05-11 00:00:00',
			)
		)->get();
		$event    = new Event( $post->ID );
		$params   = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-11 17:00:00',
		);

		update_post_meta( $venue->ID, 'gatherpress_address', '123 Main Street, Montclair, NJ 07042' );
		update_post_meta( $venue->ID, 'gatherpress_phone', '(123) 123-1234' );
		update_post_meta( $venue->ID, 'gatherpress_website', 'https://gatherpress.org/' );
		wp_set_post_terms( $post->ID, '_unit-test-venue', Venue::TAXONOMY );

		$event->save_datetimes( $params );

		$block_content = '
			<div class="wp-block-gatherpress-add-to-calendar">
				<a href="#gatherpress-google-calendar">Google Calendar</a>
				<a href="#gatherpress-ical-calendar">iCal</a>
				<a href="#gatherpress-outlook-calendar">Outlook</a>
				<a href="#gatherpress-yahoo-calendar">Yahoo Calendar</a>
			</div>
		';
		$block         = array( 'blockName' => 'gatherpress/add-to-calendar' );
		$result        = $instance->replace_calendar_placeholders( $block_content, $block );

		$this->assertStringContainsString(
			'gatherpress_calendar=google-calendar',
			$result,
			'Generated calendar link content is missing the google-calendar endpoint query var.'
		);
		$this->assertStringContainsString(
			'gatherpress_calendar=ical',
			$result,
			'Generated calendar link content is missing the iCal endpoint query var.'
		);
		$this->assertStringContainsString(
			'gatherpress_calendar=outlook',
			$result,
			'Generated calendar link content is missing the Outlook endpoint query var.'
		);
		$this->assertStringContainsString(
			'gatherpress_calendar=yahoo-calendar',
			$result,
			'Generated calendar link content is missing the yahoo-calendar endpoint query var.'
		);
		$this->assertStringContainsString(
			'gatherpress_event=unit-test-event',
			$result,
			'Generated calendar link content is missing the event slug query var.'
		);
	}

	/**
	 * Test replace_calendar_placeholders with non-event post.
	 *
	 * Verifies that the method returns an empty string when called
	 * on a post that is not an event post type.
	 *
	 * @covers ::replace_calendar_placeholders
	 *
	 * @return void
	 */
	public function test_replace_calendar_placeholders_with_non_event_post(): void {
		$instance = Add_To_Calendar::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type' => 'post',
			)
		)->get();

		$block_content = '
			<div class="wp-block-gatherpress-add-to-calendar">
				<a href="#gatherpress-google-calendar">Google Calendar</a>
				<a href="#gatherpress-ical-calendar">iCal</a>
			</div>
		';
		$block         = array(
			'blockName' => 'gatherpress/add-to-calendar',
			'attrs'     => array(
				'postId' => $post->ID,
			),
		);

		$result = $instance->replace_calendar_placeholders( $block_content, $block );

		// Tests: return ''; (when post is not an event).
		$this->assertSame(
			'',
			$result,
			'Should return empty string for non-event post.'
		);
	}

	/**
	 * Test the screen-reader new-tab notice is injected into a _blank link.
	 *
	 * Verifies that an anchor opening in a new tab receives the visually hidden
	 * new-tab notice, while an anchor without target="_blank" is left alone.
	 *
	 * @covers ::inject_new_tab_notices
	 *
	 * @return void
	 */
	public function test_inject_new_tab_notices_adds_notice_to_blank_links(): void {
		$instance = Add_To_Calendar::get_instance();
		$content  = '<a href="https://example.test" target="_blank">Google</a>' .
			'<a href="https://example.test/ical">iCal</a>';

		$result = $instance->inject_new_tab_notices( $content );

		// _blank anchor gains the notice and stays a single anchor.
		$this->assertStringContainsString(
			'gatherpress-new-tab-notice',
			$result,
			'New-tab anchor is missing the screen-reader notice marker.'
		);
		$this->assertStringContainsString(
			'(opens in a new tab)',
			$result,
			'New-tab anchor is missing the screen-reader notice text.'
		);
		// Non-blank anchor (iCal style) must remain untouched.
		$this->assertSame(
			1,
			substr_count( $result, 'gatherpress-new-tab-notice' ),
			'Non-blank anchor must not receive the new-tab notice.'
		);
	}

	/**
	 * Test the notice is still injected when the marker text appears in the href.
	 *
	 * The idempotency check must only match the marker as a class token in the
	 * anchor content, never arbitrary text in the anchor HTML. A calendar URL
	 * carrying the marker text (e.g. a query string) used to suppress the
	 * required warning.
	 *
	 * @covers ::inject_new_tab_notices
	 *
	 * @return void
	 */
	public function test_inject_new_tab_notices_marker_text_in_href_does_not_skip_notice(): void {
		$instance = Add_To_Calendar::get_instance();
		$content  = '<a href="https://example.test/?text=gatherpress-new-tab-notice" target="_blank">Google</a>';

		$result = $instance->inject_new_tab_notices( $content );

		$this->assertStringContainsString(
			'(opens in a new tab)',
			$result,
			'An anchor whose href carries the marker text must still receive the notice.'
		);
		// The notice span must appear exactly once: the href occurrence is raw
		// URL text, not the injected class token.
		$this->assertSame(
			1,
			substr_count( $result, 'class="screen-reader-text gatherpress-new-tab-notice"' ),
			'Exactly one notice span must be injected when the marker text sits in the href.'
		);
	}

	/**
	 * Test the notice is still injected when the marker text appears in link text.
	 *
	 * The idempotency check must only match the marker as the exact class
	 * attribute this plugin injects, never arbitrary text in the anchor HTML.
	 * Visible link text carrying the marker text used to suppress the required
	 * warning.
	 *
	 * @covers ::inject_new_tab_notices
	 *
	 * @return void
	 */
	public function test_inject_new_tab_notices_marker_text_in_link_text_does_not_skip_notice(): void {
		$instance = Add_To_Calendar::get_instance();
		$content  = '<a href="https://example.test" target="_blank">gatherpress-new-tab-notice</a>';

		$result = $instance->inject_new_tab_notices( $content );

		$this->assertStringContainsString(
			'(opens in a new tab)',
			$result,
			'An anchor whose visible link text reads the marker must still receive the notice.'
		);
		// The notice span must appear exactly once: the link-text occurrence is
		// raw visible text, not the injected class attribute.
		$this->assertSame(
			1,
			substr_count( $result, 'class="screen-reader-text gatherpress-new-tab-notice"' ),
			'Exactly one notice span must be injected when the marker text sits in the link text.'
		);
	}

	/**
	 * Test the new-tab notice is not injected twice into the same anchor.
	 *
	 * Verifies the transform is idempotent: an anchor that already carries the
	 * marker class is left unchanged on a second pass.
	 *
	 * @covers ::inject_new_tab_notices
	 *
	 * @return void
	 */
	public function test_inject_new_tab_notices_is_idempotent(): void {
		$instance = Add_To_Calendar::get_instance();
		$content  = '<a href="https://example.test" target="_blank">Google' .
			'<span class="screen-reader-text gatherpress-new-tab-notice"> (opens in a new tab)</span></a>';

		$once  = $instance->inject_new_tab_notices( $content );
		$twice = $instance->inject_new_tab_notices( $once );

		$this->assertSame(
			1,
			substr_count( $twice, 'gatherpress-new-tab-notice' ),
			'Notice must not be injected more than once into the same anchor.'
		);
	}

	/**
	 * Test the notice is injected into an unquoted target="_blank" anchor.
	 *
	 * The bare target=_blank form is valid HTML that a text-substring regex
	 * misses because it looks for quoted `_blank`. The tag parser matches the
	 * underlying attribute value regardless of quoting.
	 *
	 * @covers ::inject_new_tab_notices
	 *
	 * @return void
	 */
	public function test_inject_new_tab_notices_matches_unquoted_blank_target(): void {
		$instance = Add_To_Calendar::get_instance();
		$content  = '<a href="https://example.test" target=_blank>Google</a>';

		$result = $instance->inject_new_tab_notices( $content );

		$this->assertStringContainsString(
			'(opens in a new tab)',
			$result,
			'An anchor with an unquoted target=_blank must receive the notice.'
		);
	}

	/**
	 * Test an attribute value that only mimics _blank is not a new-tab link.
	 *
	 * Reported as a regex false positive: `data-x='target="_blank"'` matched
	 * the pattern even though the anchor never targets a new tab. The tag
	 * parser reads the real target attribute, so this anchor stays untouched.
	 *
	 * @covers ::inject_new_tab_notices
	 *
	 * @return void
	 */
	public function test_inject_new_tab_notices_ignores_mimic_blank_in_other_attribute(): void {
		$instance = Add_To_Calendar::get_instance();
		$content  = '<a href="https://example.test" data-x=\'target="_blank"\'>Google</a>';

		$result = $instance->inject_new_tab_notices( $content );

		$this->assertStringNotContainsString(
			'gatherpress-new-tab-notice',
			$result,
			'An anchor that only carries the marker text in a non-target attribute must not get the notice.'
		);
	}

	/**
	 * Test the notice is not duplicated when the existing marker class is
	 * reordered or single-quoted.
	 *
	 * The idempotency check runs through the class list rather than comparing
	 * one canonical class attribute, so any quoting or ordering a theme or a
	 * prior pass produced still suppresses a second injection.
	 *
	 * @covers ::inject_new_tab_notices
	 *
	 * @return void
	 */
	public function test_inject_new_tab_notices_is_idempotent_across_class_variants(): void {
		$instance = Add_To_Calendar::get_instance();
		$content  = '<a href="https://example.test" target="_blank">Google' .
			"<span class='gatherpress-new-tab-notice screen-reader-text'> (opens in a new tab)</span></a>";

		$result = $instance->inject_new_tab_notices( $content );

		$this->assertSame(
			1,
			substr_count( $result, 'gatherpress-new-tab-notice' ),
			'Notice must not be injected a second time when the marker span is single-quoted or reordered.'
		);
	}
}
