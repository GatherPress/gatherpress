<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Setup;
use GatherPress\Tests\Base;

/**
 * Class Test_Event_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Setup
 */
class Test_Event_Setup extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Event_Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_post_type' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_post_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_calendar_rewrite_rule' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'parse_request',
				'priority' => 10,
				'callback' => array( $instance, 'handle_calendar_ics_request' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'delete_event' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( $instance, 'set_datetimes' ),
			),
			array(
				'type'     => 'action',
				'name'     => sprintf( 'save_post_%s', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'check_waiting_list' ),
			),
			array(
				'type'     => 'action',
				'name'     => sprintf( 'manage_%s_posts_custom_column', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'custom_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'redirect_canonical',
				'priority' => 10,
				'callback' => array( $instance, 'disable_ics_canonical_redirect' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'set_custom_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_edit-%s_sortable_columns', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'sortable_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'get_the_date',
				'priority' => 10,
				'callback' => array( $instance, 'get_the_event_date' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'the_time',
				'priority' => 10,
				'callback' => array( $instance, 'get_the_event_date' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'display_post_states',
				'priority' => 10,
				'callback' => array( $instance, 'set_event_archive_labels' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test that the calendar rewrite rule is registered correctly.
	 *
	 * @covers ::register_calendar_rewrite_rule
	 *
	 * @return void
	 */
	public function test_register_calendar_rewrite_rule(): void {
		$instance = Event_Setup::get_instance();

		$instance->register_calendar_rewrite_rule();

		// Test that add_rewrite_rule was called with correct arguments.
		global $wp_rewrite;

		// Flush rewrite rules to ensure they're generated.
		$wp_rewrite->flush_rules();

		// Get the current rewrite rules.
		$rules = $wp_rewrite->rewrite_rules();

		// Build the expected rule pattern.
		$expected_rule_pattern     = '^event/([^/]+)\.ics$';
		$expected_rule_replacement = sprintf( 'index.php?post_type=%s&name=$matches[1]&gatherpress_ics=1', Event::POST_TYPE );

		// Check that our specific rule pattern exists.
		$this->assertArrayHasKey(
			$expected_rule_pattern,
			$rules,
			"Expected rewrite rule pattern '^event/([^/]+)\\.ics$' was not found in WordPress rewrite rules"
		);

		// Check that the rule maps to the correct replacement.
		$this->assertEquals(
			$expected_rule_replacement,
			$rules[ $expected_rule_pattern ],
			'The rewrite rule replacement does not match the expected format - should map event .ics requests to the correct query vars'
		);

		// Verify that the gatherpress_ics parameter appears in the rules.
		$this->assertStringContainsString(
			'gatherpress_ics=1',
			implode( '', $wp_rewrite->rules ),
			"The 'gatherpress_ics' query parameter was not found in any rewrite rule"
		);
	}

	/**
	 * Tests for the ics canonical redirect prevention.
	 *
	 * @covers ::disable_ics_canonical_redirect
	 *
	 * @return void
	 */
	public function test_disable_ics_canonical_redirect(): void {
		$instance     = Event_Setup::get_instance();
		$ics_url      = 'https://example.org/event/test-event.ics';
		$redirect_url = 'https://example.org/event/test-event.ics/';
		$result       = $instance->disable_ics_canonical_redirect( $redirect_url, $ics_url );

		$this->assertFalse(
			$result,
			"The method should return false to prevent redirects when the URL contains '.ics'"
		);

		// Test with a non-ics URL.
		$regular_url  = 'https://example.org/event/test-event';
		$redirect_url = 'https://example.org/event/test-event/';

		$result = $instance->disable_ics_canonical_redirect( $redirect_url, $regular_url );

		$this->assertEquals(
			$redirect_url,
			$result,
			"The method should return the original redirect URL when the URL does not contain '.ics'"
		);
	}

	/**
	 * Coverage for register_post_type method.
	 *
	 * @covers ::register_post_type
	 *
	 * @return void
	 */
	public function test_register_post_type(): void {
		$instance = Event_Setup::get_instance();

		unregister_post_type( Event::POST_TYPE );

		$this->assertFalse( post_type_exists( Event::POST_TYPE ), 'Failed to assert that post type does not exist.' );

		$instance->register_post_type();

		$this->assertTrue( post_type_exists( Event::POST_TYPE ), 'Failed to assert that post type exists.' );
	}

	/**
	 * Coverage for get_localized_post_type_slug method.
	 *
	 * @covers ::get_localized_post_type_slug
	 *
	 * @return void
	 */
	public function test_get_localized_post_type_slug(): void {

		$this->assertSame(
			'event',
			Event_Setup::get_localized_post_type_slug(),
			'Failed to assert English post type slug is "event".'
		);

		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'locale', 'es_ES' );
		switch_to_user_locale( $user_id );

		// @todo This assertion CAN NOT FAIL,
		// until real translations do exist in the wp-env instance.
		// Because WordPress doesn't have any translation files to load,
		// it will return the string in English.
		$this->assertSame(
			'event',
			Event_Setup::get_localized_post_type_slug(),
			'Failed to assert post type slug is "event", even the locale is not English anymore.'
		);
		// But at least the restoring of the user locale can be tested, without .po files.
		$this->assertSame(
			'es_ES',
			determine_locale(),
			'Failed to assert locale was reset to Spanish, after switching to ~ and restoring from English.'
		);

		// Restore default locale for following tests.
		switch_to_locale( 'en_US' );

		// This also checks that the post type is still registered with the same 'Post Type Singular Name' label,
		// which is used by the method under test and the test itself.
		$filter = static function ( string $translation, string $text, string $context ): string {
			if ( 'Event' !== $text || 'Post Type Singular Name' !== $context ) {
				return $translation;
			}
			return 'Ünit Tést';
		};

		/**
		 * Instead of loading additional languages into the unit test suite,
		 * we just filter the translated value, to mock different languages.
		 *
		 * Filters text with its translation based on context information for a domain.
		 *
		 * @param string $translation Translated text.
		 * @param string $text        Text to translate.
		 * @param string $context     Context information for the translators.
		 * @return string Translated text.
		 */
		add_filter( 'gettext_with_context_gatherpress', $filter, 10, 3 );

		$this->assertSame(
			'unit-test',
			Event_Setup::get_localized_post_type_slug(),
			'Failed to assert the post type slug is "unit-test".'
		);

		remove_filter( 'gettext_with_context_gatherpress', $filter );
	}

	/**
	 * Coverage for register_post_meta method.
	 *
	 * @covers ::register_post_meta
	 *
	 * @return void
	 */
	public function test_register_post_meta(): void {
		$instance = Event_Setup::get_instance();

		unregister_post_meta( Event::POST_TYPE, 'gatherpress_online_event_link' );
		unregister_post_meta( Event::POST_TYPE, 'gatherpress_enable_anonymous_rsvp' );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayNotHasKey( 'online_event_link', $meta, 'Failed to assert that online_event_link does not exist.' );
		$this->assertArrayNotHasKey( 'enable_anonymous_rsvp', $meta, 'Failed to assert that enable_anonymous_rsvp does not exist.' );
		$this->assertArrayNotHasKey( 'max_attendance_limit', $meta, 'Failed to assert that max_guest_limit does not exist.' );
		$this->assertArrayNotHasKey( 'max_guest_limit', $meta, 'Failed to assert that max_guest_limit does not exist.' );

		$instance->register_post_meta();

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayHasKey( 'gatherpress_online_event_link', $meta, 'Failed to assert that gatherpress_online_event_link does exist.' );
		$this->assertArrayHasKey( 'gatherpress_enable_anonymous_rsvp', $meta, 'Failed to assert that gatherpress_enable_anonymous_rsvp does exist.' );
		$this->assertArrayHasKey( 'gatherpress_max_attendance_limit', $meta, 'Failed to assert that max_guest_limit does exist.' );
		$this->assertArrayHasKey( 'gatherpress_max_guest_limit', $meta, 'Failed to assert that gatherpress_max_guest_limit does exist.' );
	}

	/**
	 * Coverage for sortable_columns method.
	 *
	 * @covers ::sortable_columns
	 *
	 * @return void
	 */
	public function test_sortable_columns(): void {
		$instance = Event_Setup::get_instance();
		$default  = array( 'unit' => 'test' );
		$expects  = array(
			'unit'     => 'test',
			'datetime' => 'datetime',
		);

		$this->assertSame(
			$expects,
			$instance->sortable_columns( $default ),
			'Failed to assert correct sortable columns.'
		);
	}

	/**
	 * Coverage for set_datetimes method.
	 *
	 * @covers ::set_datetimes
	 *
	 * @return void
	 */
	public function test_set_datetimes(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post()->get()->ID;

		$instance->set_datetimes( $post_id );
		$this->assertEmpty(
			get_post_meta( 'gatherpress_datetime_start' ),
			'Failed to assert that datetime start meta is empty.'
		);

		$post_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		$instance->set_datetimes( $post_id );
		$this->assertEmpty(
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that datetime start meta is empty.'
		);

		$post_id = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_meta' => array(
					'gatherpress_datetime' => '{"dateTimeStart":"2019-09-18 18:00:00","dateTimeEnd":"2019-09-18 20:00:00","timezone":"America/New_York"}',
				),
			)
		)->get()->ID;

		$instance->set_datetimes( $post_id );
		$this->assertSame(
			'2019-09-18 18:00:00',
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that datetime start is expected value.'
		);
		$this->assertSame(
			'2019-09-18 22:00:00',
			get_post_meta( $post_id, 'gatherpress_datetime_start_gmt', true ),
			'Failed to assert that datetime start gmt is expected value.'
		);
		$this->assertSame(
			'2019-09-18 20:00:00',
			get_post_meta( $post_id, 'gatherpress_datetime_end', true ),
			'Failed to assert that datetime end is expected value.'
		);
		$this->assertSame(
			'2019-09-19 00:00:00',
			get_post_meta( $post_id, 'gatherpress_datetime_end_gmt', true ),
			'Failed to assert that datetime end gmt is expected value.'
		);
		$this->assertSame(
			'America/New_York',
			get_post_meta( $post_id, 'gatherpress_timezone', true ),
			'Failed to assert that timezone is expected value.'
		);
	}
}
