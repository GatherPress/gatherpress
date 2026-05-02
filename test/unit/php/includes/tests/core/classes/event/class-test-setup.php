<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Setup.
 *
 * @package GatherPress\Core\Event
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Admin_List;
use GatherPress\Core\Event\Meta;
use GatherPress\Core\Event\Query;
use GatherPress\Core\Event\Rest_Api;
use GatherPress\Core\Event\Setup;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use stdClass;
use WP;
use WP_Block;
use WP_Block_Patterns_Registry;
use WP_REST_Request;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Setup
 */
class Test_Setup extends Base {
	/**
	 * Event\Setup now owns the instantiation of the Event\* sibling
	 * singletons (Admin_List, Query, Rest_Api) so the outer
	 * `Setup::instantiate_classes()` can hand off with a single
	 * `Event\Setup::get_instance()` call. Per-sibling proof-of-construction
	 * via their `setup_hooks()`-registered hooks — catches the case where
	 * a sibling silently drops out of `Event\Setup::instantiate_classes()`.
	 *
	 * @covers ::__construct
	 * @covers ::instantiate_classes
	 *
	 * @return void
	 */
	public function test_instantiate_classes_registers_siblings(): void {
		// Force the method to run inside the test's coverage window —
		// Setup is a singleton cached during plugin bootstrap, so
		// `get_instance()` here returns the cached instance and doesn't
		// re-fire the constructor.
		Utility::invoke_hidden_method( Setup::get_instance(), 'instantiate_classes' );

		$expected_hooks = array(
			Admin_List::class => array(
				'query_vars',
				array( Admin_List::get_instance(), 'query_vars' ),
			),
			Meta::class       => array(
				'registered_post_type',
				array( Meta::get_instance(), 'register' ),
			),
			Query::class      => array(
				'pre_get_posts',
				array( Query::get_instance(), 'prepare_event_query_before_execution' ),
			),
			Rest_Api::class   => array(
				'rest_api_init',
				array( Rest_Api::get_instance(), 'register_endpoints' ),
			),
		);

		foreach ( $expected_hooks as $class_name => $expected ) {
			list( $hook, $callback ) = $expected;
			$this->assertSame(
				10,
				has_action( $hook, $callback ),
				sprintf( '%s must be instantiated so its %s hook registers.', $class_name, $hook )
			);
		}
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Setup::get_instance();
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
				'callback' => array( $instance, 'register_calendar_rewrite_rule' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'register_starter_pattern' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'parse_request',
				'priority' => 10,
				'callback' => array( $instance, 'handle_calendar_ics_request' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'template_redirect',
				'priority' => 10,
				'callback' => array( $instance, 'handle_event_archive_redirect' ),
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
				'name'     => 'save_post',
				'priority' => 10,
				'callback' => array( $instance, 'check_waiting_list' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'redirect_canonical',
				'priority' => 10,
				'callback' => array( $instance, 'disable_ics_canonical_redirect' ),
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
				'name'     => 'render_block_core/post-date',
				'priority' => 10,
				'callback' => array( $instance, 'render_event_post_date_block' ),
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
	 * Test that the calendar rewrite rule is registered correctly with default slug.
	 *
	 * @covers ::register_calendar_rewrite_rule
	 *
	 * @return void
	 */
	public function test_register_calendar_rewrite_rule(): void {
		$instance = Setup::get_instance();
		$settings = Settings::get_instance();

		// Get the dynamic slug from settings (default is 'events' plural).
		$rewrite_slug = $settings->get( 'events_url' );

		$instance->register_calendar_rewrite_rule();

		// Test that add_rewrite_rule was called with correct arguments.
		global $wp_rewrite;

		// Flush rewrite rules to ensure they're generated.
		$wp_rewrite->flush_rules();

		// Get the current rewrite rules.
		$rules = $wp_rewrite->rewrite_rules();

		// Build the expected rule pattern using dynamic slug.
		$expected_rule_pattern     = sprintf( '^%s/([^/]+)\.ics$', $rewrite_slug );
		$expected_rule_replacement = sprintf(
			'index.php?post_type=%s&name=$matches[1]&gatherpress_ics=1',
			Event::POST_TYPE
		);

		// Check that our specific rule pattern exists.
		$this->assertArrayHasKey(
			$expected_rule_pattern,
			$rules,
			sprintf(
				"Expected rewrite rule pattern '^%s/([^/]+)\\.ics$' was not found in WordPress rewrite rules",
				$rewrite_slug
			)
		);

		// Check that the rule maps to the correct replacement.
		$this->assertEquals(
			$expected_rule_replacement,
			$rules[ $expected_rule_pattern ],
			'The rewrite rule replacement does not match the expected format -
			should map event .ics requests to the correct query vars'
		);

		// Verify that the gatherpress_ics parameter appears in the rules.
		$this->assertStringContainsString(
			'gatherpress_ics=1',
			implode( '', $wp_rewrite->rules ),
			"The 'gatherpress_ics' query parameter was not found in any rewrite rule"
		);
	}

	/**
	 * Registers the user-facing starter pattern scoped to core/post-content
	 * and every post type declaring `gatherpress-event-date` so the starter
	 * pattern modal surfaces it on new event posts.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern(): void {
		$instance = Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( 'gatherpress/event-with-rsvp' ) ) {
			$registry->unregister( 'gatherpress/event-with-rsvp' );
		}

		$instance->register_starter_pattern();

		$this->assertTrue(
			$registry->is_registered( 'gatherpress/event-with-rsvp' ),
			'Starter pattern should be registered.'
		);

		$pattern = $registry->get_registered( 'gatherpress/event-with-rsvp' );

		$this->assertContains(
			'core/post-content',
			$pattern['blockTypes'],
			'Starter pattern must scope to core/post-content so the chooser modal surfaces it.'
		);
		$this->assertContains(
			Event::POST_TYPE,
			$pattern['postTypes'],
			'Starter pattern must scope to gatherpress_event post type.'
		);
		$this->assertStringContainsString(
			'gatherpress/event-date',
			$pattern['content'],
			'Starter pattern body must seed the event-date block.'
		);
		$this->assertStringContainsString(
			'"patternPicked":true',
			$pattern['content'],
			'Wrapper-div blocks must be flagged pattern-picked so the nested pickers stay suppressed.'
		);

		$registry->unregister( 'gatherpress/event-with-rsvp' );
	}

	/**
	 * Third parties can append their own pattern definitions via the
	 * `gatherpress_event_starter_patterns` filter without having to
	 * call `register_block_pattern()` themselves.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern_filter_extends(): void {
		$instance = Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( 'unit-test/extra-event-pattern' ) ) {
			$registry->unregister( 'unit-test/extra-event-pattern' );
		}

		$append_pattern = static function ( array $patterns ): array {
			$patterns[] = array(
				'name'        => 'unit-test/extra-event-pattern',
				'title'       => 'Extra Event Pattern',
				'description' => 'Added through the filter.',
				'content'     => '<!-- wp:paragraph --><p>Extra</p><!-- /wp:paragraph -->',
			);
			return $patterns;
		};

		add_filter( 'gatherpress_event_starter_patterns', $append_pattern );

		$instance->register_starter_pattern();

		remove_filter( 'gatherpress_event_starter_patterns', $append_pattern );

		$this->assertTrue(
			$registry->is_registered( 'unit-test/extra-event-pattern' ),
			'Patterns appended via the filter must be registered alongside the bundled defaults.'
		);

		$registry->unregister( 'unit-test/extra-event-pattern' );
	}

	/**
	 * Filter callbacks may return entries that aren't valid pattern
	 * definitions (missing `name`, non-array values). The registration
	 * loop must skip those gracefully so one bad entry from a
	 * third-party filter doesn't bring down the rest of the chooser.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern_skips_malformed_filter_entries(): void {
		$instance = Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( 'gatherpress/event-with-rsvp' ) ) {
			$registry->unregister( 'gatherpress/event-with-rsvp' );
		}

		$inject_garbage = static function ( array $patterns ): array {
			$patterns[] = array( 'title' => 'No name key — must be skipped.' );
			$patterns[] = 'not-an-array — must be skipped.';
			return $patterns;
		};

		add_filter( 'gatherpress_event_starter_patterns', $inject_garbage );

		$instance->register_starter_pattern();

		remove_filter( 'gatherpress_event_starter_patterns', $inject_garbage );

		$this->assertTrue(
			$registry->is_registered( 'gatherpress/event-with-rsvp' ),
			'Bundled pattern should still register when filter entries before/after it are malformed.'
		);

		$registry->unregister( 'gatherpress/event-with-rsvp' );
	}

	/**
	 * Bails before registering when no post type declares
	 * `gatherpress-event-date` support. Without the guard,
	 * `register_block_pattern` would be called with an empty
	 * `postTypes` array and the chooser modal would have no
	 * post-type scope to match against.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern_bails_without_supported_post_types(): void {
		$instance = Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( 'gatherpress/event-with-rsvp' ) ) {
			$registry->unregister( 'gatherpress/event-with-rsvp' );
		}

		// Strip the support from every post type that currently declares
		// it so `get_post_types_by_support()` returns an empty array.
		$supported = get_post_types_by_support( 'gatherpress-event-date' );
		foreach ( $supported as $post_type ) {
			remove_post_type_support( $post_type, 'gatherpress-event-date' );
		}

		$instance->register_starter_pattern();

		$this->assertFalse(
			$registry->is_registered( 'gatherpress/event-with-rsvp' ),
			'Starter pattern must not be registered when no post type declares the event-date support.'
		);

		// Restore support.
		foreach ( $supported as $post_type ) {
			add_post_type_support( $post_type, 'gatherpress-event-date' );
		}
	}

	/**
	 * Tests for the ics canonical redirect prevention.
	 *
	 * @covers ::disable_ics_canonical_redirect
	 *
	 * @return void
	 */
	public function test_disable_ics_canonical_redirect(): void {
		$instance     = Setup::get_instance();
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
		$instance = Setup::get_instance();

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
			Setup::get_localized_post_type_slug(),
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
			Setup::get_localized_post_type_slug(),
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
			Setup::get_localized_post_type_slug(),
			'Failed to assert the post type slug is "unit-test".'
		);

		remove_filter( 'gettext_with_context_gatherpress', $filter );
	}


	/**
	 * Coverage for set_datetimes method.
	 *
	 * @covers ::set_datetimes
	 *
	 * @return void
	 */
	public function test_set_datetimes(): void {
		$instance = Setup::get_instance();
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
					'gatherpress_datetime' => '{"dateTimeStart":"2019-09-18 18:00:00",
					"dateTimeEnd":"2019-09-18 20:00:00","timezone":"America/New_York"}',
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

	/**
	 * Coverage for check_waiting_list method.
	 *
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Method should execute without error.
		$instance->check_waiting_list( $post_id );
		$this->assertTrue( true, 'check_waiting_list executed without error.' );
	}

	/**
	 * Coverage for check_waiting_list method with non-rsvp post type.
	 *
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_skips_non_rsvp_post_type(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post()->get()->ID;

		// A plain post does not support gatherpress-rsvp so the method returns early.
		$instance->check_waiting_list( $post_id );
		$this->assertTrue( true, 'check_waiting_list returned early for non-rsvp post type without error.' );
	}

	/**
	 * Coverage for delete_event method with non-event post.
	 *
	 * @covers ::delete_event
	 *
	 * @return void
	 */
	public function test_delete_event_non_event_post(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post()->get()->ID;

		// Should return early for non-event posts.
		$instance->delete_event( $post_id );
		$this->assertTrue( true, 'delete_event executed without error for non-event post.' );
	}

	/**
	 * Coverage for delete_event method with event post.
	 *
	 * @covers ::delete_event
	 *
	 * @return void
	 */
	public function test_delete_event(): void {
		global $wpdb;

		$instance = Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create event with datetime to populate custom table.
		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-06-15 10:00:00',
				'datetime_end'   => '2025-06-15 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Verify record exists in custom table.
		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_id = %d', $table, $post_id ) );
		$this->assertGreaterThan( 0, $count, 'Event record should exist in custom table.' );

		// Delete event.
		$instance->delete_event( $post_id );

		// Verify record was deleted from custom table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_id = %d', $table, $post_id ) );
		$this->assertEquals( 0, $count, 'Event record should be deleted from custom table.' );
	}

	/**
	 * Coverage for get_the_event_date method when event date should be used.
	 *
	 * @covers ::get_the_event_date
	 *
	 * @return void
	 */
	public function test_get_the_event_date_use_event_date(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-06-15 10:00:00',
				'datetime_end'   => '2025-06-15 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Set setting to use event date.
		update_option(
			'gatherpress_settings',
			array(
				'post_or_event_date' => '1',
			)
		);

		// Set global post.
		$this->go_to( get_permalink( $post_id ) );

		$post   = get_post( $post_id );
		$result = $instance->get_the_event_date( 'June 15, 2025', '', $post );

		$this->assertStringContainsString( 'June', $result, 'Should return event date.' );
	}

	/**
	 * Coverage for get_the_event_date with ISO 8601 format for Post Date block compatibility.
	 *
	 * @covers ::get_the_event_date
	 *
	 * @return void
	 */
	public function test_get_the_event_date_iso_format(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-06-15 10:00:00',
				'datetime_end'   => '2025-06-15 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Set setting to use event date.
		update_option(
			'gatherpress_settings',
			array(
				'post_or_event_date' => '1',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$post   = get_post( $post_id );
		$result = $instance->get_the_event_date( '2025-06-15T10:00:00-04:00', 'c', $post );

		// Should return ISO 8601 formatted event start datetime.
		$this->assertStringContainsString( '2025-06-15', $result, 'Should return ISO 8601 formatted event date.' );
		$this->assertStringContainsString( 'T', $result, 'Should contain time separator for ISO 8601 format.' );
	}

	/**
	 * Coverage for get_the_event_date method when post date should be used.
	 *
	 * @covers ::get_the_event_date
	 *
	 * @return void
	 */
	public function test_get_the_event_date_use_post_date(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Set setting to use post date.
		update_option(
			'gatherpress_settings',
			array(
				'post_or_event_date' => '0',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$post          = get_post( $post_id );
		$original_date = 'June 15, 2025';
		$result        = $instance->get_the_event_date( $original_date, '', $post );

		$this->assertSame( $original_date, $result, 'Should return original post date.' );
	}

	/**
	 * Coverage for get_the_event_date method for non-event post.
	 *
	 * @covers ::get_the_event_date
	 *
	 * @return void
	 */
	public function test_get_the_event_date_non_event(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post()->get()->ID;

		$this->go_to( get_permalink( $post_id ) );

		$post          = get_post( $post_id );
		$original_date = 'June 15, 2025';
		$result        = $instance->get_the_event_date( $original_date, '', $post );

		$this->assertSame( $original_date, $result, 'Should return original date for non-event posts.' );
	}

	/**
	 * Coverage for render_event_post_date_block method with event post and setting enabled.
	 *
	 * @covers ::render_event_post_date_block
	 *
	 * @return void
	 */
	public function test_render_event_post_date_block_with_event(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-06-15 10:00:00',
				'datetime_end'   => '2025-06-15 14:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Set setting to use event date.
		update_option(
			'gatherpress_settings',
			array(
				'post_or_event_date' => '1',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		// phpcs:ignore Generic.Files.LineLength.TooLong -- Block HTML fixture.
		$block_content = '<div class="wp-block-post-date"><time datetime="2025-03-26T12:00:00+00:00">March 26, 2025</time></div>';
		$block         = array(
			'blockName' => 'core/post-date',
			'attrs'     => array(),
		);
		$wp_block      = new WP_Block(
			$block,
			array( 'postId' => $post_id )
		);

		$result = $instance->render_event_post_date_block( $block_content, $block, $wp_block );

		$this->assertStringContainsString( 'June', $result, 'Should contain event date month.' );
		$this->assertStringContainsString(
			'2025-06-15',
			$result,
			'Should contain ISO event date in datetime attribute.'
		);
	}

	/**
	 * Coverage for render_event_post_date_block method with setting disabled.
	 *
	 * @covers ::render_event_post_date_block
	 *
	 * @return void
	 */
	public function test_render_event_post_date_block_setting_disabled(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Set setting to use post date (disabled).
		update_option(
			'gatherpress_settings',
			array(
				'post_or_event_date' => '0',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		// phpcs:ignore Generic.Files.LineLength.TooLong -- Block HTML fixture.
		$block_content = '<div class="wp-block-post-date"><time datetime="2025-03-26T12:00:00+00:00">March 26, 2025</time></div>';
		$block         = array(
			'blockName' => 'core/post-date',
			'attrs'     => array(),
		);
		$wp_block      = new WP_Block(
			$block,
			array( 'postId' => $post_id )
		);

		$result = $instance->render_event_post_date_block( $block_content, $block, $wp_block );

		$this->assertSame( $block_content, $result, 'Should return original block content when setting is disabled.' );
	}

	/**
	 * Coverage for render_event_post_date_block method with non-event post.
	 *
	 * @covers ::render_event_post_date_block
	 *
	 * @return void
	 */
	public function test_render_event_post_date_block_non_event(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post()->get()->ID;

		$this->go_to( get_permalink( $post_id ) );

		// phpcs:ignore Generic.Files.LineLength.TooLong -- Block HTML fixture.
		$block_content = '<div class="wp-block-post-date"><time datetime="2025-03-26T12:00:00+00:00">March 26, 2025</time></div>';
		$block         = array(
			'blockName' => 'core/post-date',
			'attrs'     => array(),
		);
		$wp_block      = new WP_Block(
			$block,
			array( 'postId' => $post_id )
		);

		$result = $instance->render_event_post_date_block( $block_content, $block, $wp_block );

		$this->assertSame( $block_content, $result, 'Should return original block content for non-event posts.' );
	}

	/**
	 * Coverage for render_event_post_date_block with event that has no datetime.
	 *
	 * @covers ::render_event_post_date_block
	 *
	 * @return void
	 */
	public function test_render_event_post_date_block_empty_datetime(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Save empty/zero datetimes to trigger the em dash display.
		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '0000-00-00 00:00:00',
				'datetime_end'   => '0000-00-00 00:00:00',
				'timezone'       => 'UTC',
			)
		);

		// Set setting to use event date.
		update_option(
			'gatherpress_settings',
			array(
				'post_or_event_date' => '1',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		// phpcs:ignore Generic.Files.LineLength.TooLong -- Block HTML fixture.
		$block_content = '<div class="wp-block-post-date"><time datetime="2025-03-26T12:00:00+00:00">March 26, 2025</time></div>';
		$block         = array(
			'blockName' => 'core/post-date',
			'attrs'     => array(),
		);
		$wp_block      = new WP_Block(
			$block,
			array( 'postId' => $post_id )
		);

		$result = $instance->render_event_post_date_block( $block_content, $block, $wp_block );

		$this->assertSame(
			$block_content,
			$result,
			'Should return original block content when event datetime is empty.'
		);

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for set_event_archive_labels method with no pages set.
	 *
	 * @covers ::set_event_archive_labels
	 *
	 * @return void
	 */
	public function test_set_event_archive_labels_no_pages(): void {
		$instance = Setup::get_instance();

		delete_option( 'gatherpress_settings' );

		$post        = $this->mock->post()->get();
		$post_states = array( 'publish' => 'Published' );

		$result = $instance->set_event_archive_labels( $post_states, $post );

		$this->assertEquals( $post_states, $result, 'Should return unchanged post states when no pages set.' );
	}

	/**
	 * Coverage for set_event_archive_labels method with empty pages.
	 *
	 * @covers ::set_event_archive_labels
	 *
	 * @return void
	 */
	public function test_set_event_archive_labels_empty_pages(): void {
		$instance = Setup::get_instance();

		update_option(
			'gatherpress_settings',
			array(
				'pages' => '',
			)
		);

		$post        = $this->mock->post()->get();
		$post_states = array( 'publish' => 'Published' );

		$result = $instance->set_event_archive_labels( $post_states, $post );

		$this->assertEquals( $post_states, $result, 'Should return unchanged post states when pages empty.' );
	}

	/**
	 * Coverage for set_event_archive_labels method with upcoming events page.
	 *
	 * @covers ::set_event_archive_labels
	 *
	 * @return void
	 */
	public function test_set_event_archive_labels_upcoming_events(): void {
		$instance = Setup::get_instance();

		$page_id = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;

		update_option(
			'gatherpress_settings',
			array(
				'upcoming_events' => wp_json_encode(
					array(
						(object) array(
							'id'    => $page_id,
							'value' => 'Upcoming Events',
						),
					)
				),
			)
		);

		$post        = get_post( $page_id );
		$post_states = array();

		$result = $instance->set_event_archive_labels( $post_states, $post );

		$this->assertArrayHasKey( 'gatherpress_upcoming_events', $result, 'Should have upcoming events label.' );
		$this->assertStringContainsString(
			'GatherPress',
			$result['gatherpress_upcoming_events'],
			'Label should contain GatherPress.'
		);
	}

	/**
	 * Coverage for set_event_archive_labels method with past events page.
	 *
	 * @covers ::set_event_archive_labels
	 *
	 * @return void
	 */
	public function test_set_event_archive_labels_past_events(): void {
		$instance = Setup::get_instance();

		$page_id = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;

		update_option(
			'gatherpress_settings',
			array(
				'past_events' => wp_json_encode(
					array(
						(object) array(
							'id'    => $page_id,
							'value' => 'Past Events',
						),
					)
				),
			)
		);

		$post        = get_post( $page_id );
		$post_states = array();

		$result = $instance->set_event_archive_labels( $post_states, $post );

		$this->assertArrayHasKey( 'gatherpress_past_events', $result, 'Should have past events label.' );
		$this->assertStringContainsString(
			'GatherPress',
			$result['gatherpress_past_events'],
			'Label should contain GatherPress.'
		);
	}

	/**
	 * Coverage for set_datetimes method with non-event post.
	 *
	 * @covers ::set_datetimes
	 *
	 * @return void
	 */
	public function test_set_datetimes_non_event_post(): void {
		$instance = Setup::get_instance();
		$post_id  = $this->mock->post()->get()->ID;

		// Should return early for non-event posts.
		$instance->set_datetimes( $post_id );

		$this->assertEmpty(
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Should not set datetime meta for non-event posts.'
		);
	}

	/**
	 * Tests that restore_previous_locale() is called in get_localized_post_type_slug().
	 *
	 * Covers: restore_previous_locale when locale is switched.
	 *
	 * @covers ::get_localized_post_type_slug
	 * @return void
	 */
	public function test_get_localized_post_type_slug_restores_locale(): void {
		// Create a scenario where get_locale() returns a different value.
		// than the current global locale by using the locale filter.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Intentionally overriding locale.
		$locale_filter = static function ( $locale ) {
			return 'de_DE';
		};

		add_filter( 'locale', $locale_filter );

		// Now get_locale() will return 'de_DE', triggering switch and restore.
		$slug = Setup::get_localized_post_type_slug();

		// If no error occurs, restore_previous_locale was called correctly.
		$this->assertIsString( $slug, 'Should return a string slug' );

		remove_filter( 'locale', $locale_filter );
	}


	/**
	 * Tests handle_calendar_ics_request with event not found.
	 *
	 * @covers ::handle_calendar_ics_request
	 * @return void
	 */
	public function test_handle_calendar_ics_request_event_not_found(): void {
		$wp             = new WP();
		$wp->query_vars = array(
			'gatherpress_ics' => true,
			'name'            => 'non-existent-event',
		);

		$instance = Setup::get_instance();

		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'Event not found.' );

		$instance->handle_calendar_ics_request( $wp );
	}


	/**
	 * Tests handle_calendar_ics_request with ICS generation.
	 *
	 * @covers ::handle_calendar_ics_request
	 * @return void
	 */
	public function test_handle_calendar_ics_request_with_ics_output(): void {
		// Create event post directly with wp_insert_post.
		$post_id = wp_insert_post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_name'   => 'test-ics-download',
				'post_title'  => 'Test ICS Download',
				'post_status' => 'publish',
			),
			true
		);

		$this->assertIsInt( $post_id, 'Post should be created successfully' );

		// Set event datetime so ICS has valid content.
		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2025-12-25 10:00:00',
				'datetime_end'   => '2025-12-25 12:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		// Set up global WP object with proper query_vars.
		global $wp;

		if ( ! ( $wp instanceof WP ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for testing ICS request handling.
			$wp = new WP();
		}

		$wp->query_vars = array(
			'gatherpress_ics' => true,
			'name'            => 'test-ics-download',
		);

		$instance = Setup::get_instance();

		// Buffer output to capture ICS content without displaying it.
		$output = Utility::buffer_and_return(
			static function () use ( $instance, $wp ) {
				$instance->handle_calendar_ics_request( $wp );
			}
		);

		// Verify ICS content is generated correctly.
		$this->assertStringContainsString( 'BEGIN:VCALENDAR', $output );
		$this->assertStringContainsString( 'VERSION:2.0', $output );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $output );
		$this->assertStringContainsString( 'END:VEVENT', $output );
		$this->assertStringContainsString( 'END:VCALENDAR', $output );
	}


	/**
	 * Tests handle_event_archive_redirect returns early when not on post type archive.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @return void
	 */
	public function test_handle_event_archive_redirect_not_archive(): void {
		$instance = Setup::get_instance();

		// Create an event and go to its single page.
		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$this->go_to( get_permalink( $post_id ) );

		// This should return early without setting 404.
		$instance->handle_event_archive_redirect();

		$this->assertFalse( is_404(), 'Should not be 404 when on single event page.' );
	}

	/**
	 * Tests handle_event_archive_redirect serves page when page exists with same slug.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @return void
	 */
	public function test_handle_event_archive_redirect_with_page(): void {
		global $wp_query;

		$instance = Setup::get_instance();

		// Get the rewrite slug from settings.
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'events_url' );

		// Create a page with the same slug as the events rewrite slug.
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_name'   => $rewrite_slug,
				'post_title'  => 'Events Page',
				'post_status' => 'publish',
			)
		);

		$this->assertIsInt( $page_id, 'Page should be created successfully.' );

		// Mock being on the post type archive by setting WP_Query properties directly.
		$wp_query->is_post_type_archive = true;
		$wp_query->set( 'post_type', Event::POST_TYPE );

		$instance->handle_event_archive_redirect();

		// Verify the query was modified to serve the page.
		$this->assertTrue( $wp_query->is_page, 'Should be a page query.' );
		$this->assertTrue( $wp_query->is_singular, 'Should be a singular query.' );
		$this->assertFalse( $wp_query->is_post_type_archive, 'Should not be a post type archive.' );
		$this->assertFalse( $wp_query->is_archive, 'Should not be an archive.' );
		$this->assertSame( $page_id, $wp_query->queried_object_id, 'Queried object should be the page.' );

		// Clean up.
		wp_delete_post( $page_id, true );
	}

	/**
	 * Tests handle_event_archive_redirect sets 404 when no page exists.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @return void
	 */
	public function test_handle_event_archive_redirect_no_page_404(): void {
		global $wp_query;

		$instance = Setup::get_instance();

		// Get the rewrite slug from settings.
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'events_url' );

		// Make sure no page exists with this slug.
		$existing_page = get_page_by_path( $rewrite_slug );
		if ( $existing_page ) {
			wp_delete_post( $existing_page->ID, true );
		}

		// Mock being on the post type archive by setting WP_Query properties directly.
		$wp_query->is_post_type_archive = true;
		$wp_query->set( 'post_type', Event::POST_TYPE );

		// Call the method.
		$instance->handle_event_archive_redirect();

		// Verify 404 was set.
		$this->assertTrue( $wp_query->is_404(), 'Should be 404 when no page exists with the same slug.' );
	}

	/**
	 * Tests handle_event_archive_redirect does not redirect to draft page.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @return void
	 */
	public function test_handle_event_archive_redirect_draft_page_404(): void {
		global $wp_query;

		$instance = Setup::get_instance();

		// Get the rewrite slug from settings.
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'events_url' );

		// Create a draft page with the same slug.
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_name'   => $rewrite_slug,
				'post_title'  => 'Events Page Draft',
				'post_status' => 'draft',
			)
		);

		$this->assertIsInt( $page_id, 'Page should be created successfully.' );

		// Mock being on the post type archive by setting WP_Query properties directly.
		$wp_query->is_post_type_archive = true;
		$wp_query->set( 'post_type', Event::POST_TYPE );

		// Call the method.
		$instance->handle_event_archive_redirect();

		// Verify 404 was set (draft page should not trigger redirect).
		$this->assertTrue( $wp_query->is_404(), 'Should be 404 when page exists but is not published.' );

		// Clean up.
		wp_delete_post( $page_id, true );
	}

	/**
	 * Tests handle_event_archive_redirect does not interfere with feed requests.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @return void
	 */
	public function test_handle_event_archive_redirect_feed_not_affected(): void {
		global $wp_query;

		$instance = Setup::get_instance();

		// Mock being on the post type archive feed.
		$wp_query->is_post_type_archive = true;
		$wp_query->is_feed              = true;
		$wp_query->set( 'post_type', Event::POST_TYPE );

		// Call the method.
		$instance->handle_event_archive_redirect();

		// Verify the archive state was not changed (feeds should pass through).
		$this->assertTrue( $wp_query->is_post_type_archive, 'Should still be a post type archive.' );
		$this->assertFalse( $wp_query->is_404(), 'Should not be 404 for feed requests.' );
	}

	/**
	 * Tests handle_event_archive_redirect skips when event-query already assigned archive.
	 *
	 * @covers ::handle_event_archive_redirect
	 *
	 * @return void
	 */
	public function test_handle_event_archive_redirect_skips_event_query_param(): void {
		global $wp_query;

		$instance = Setup::get_instance();

		// Mock being on post type archive with EVENT_QUERY_PARAM set.
		$wp_query->is_post_type_archive = true;
		$wp_query->set( 'post_type', Event::POST_TYPE );
		$wp_query->set( Query::EVENT_QUERY_PARAM, 'past' );

		$instance->handle_event_archive_redirect();

		// Should return early — archive state preserved, no 404.
		$this->assertTrue( $wp_query->is_post_type_archive, 'Should still be a post type archive.' );
		$this->assertFalse( $wp_query->is_404(), 'Should not be 404 when event-query param is set.' );
	}

	/**
	 * Tests handle_event_archive_redirect converts to archive when page is designated.
	 *
	 * @covers ::handle_event_archive_redirect
	 *
	 * @return void
	 */
	public function test_handle_event_archive_redirect_designated_archive_page(): void {
		global $wp_query;

		$instance = Setup::get_instance();

		// Get the rewrite slug from settings.
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'events_url' );

		// Create a page with the same slug as the events rewrite slug.
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_name'   => $rewrite_slug,
				'post_title'  => 'Past Events',
				'post_status' => 'publish',
			)
		);

		// Designate it as the past events archive.
		$json = wp_json_encode(
			array(
				array(
					'id'    => $page_id,
					'slug'  => $rewrite_slug,
					'value' => 'Past Events',
				),
			)
		);

		update_option( 'gatherpress_settings', array( 'past_events' => $json ) );

		// Mock being on the post type archive.
		$wp_query->is_post_type_archive = true;
		$wp_query->set( 'post_type', Event::POST_TYPE );

		$instance->handle_event_archive_redirect();

		// Verify it was converted to an event archive.
		$this->assertTrue( $wp_query->is_archive, 'Should be an archive.' );
		$this->assertTrue( $wp_query->is_post_type_archive, 'Should be a post type archive.' );
		$this->assertFalse( $wp_query->is_page, 'Should not be a page.' );
		$this->assertSame(
			'past',
			$wp_query->get( Query::EVENT_QUERY_PARAM ),
			'Should have event query param set to past.'
		);
		$this->assertSame(
			$page_id,
			$wp_query->queried_object_id,
			'Should preserve page as queried object.'
		);

		// Verify archive title is set via the filter method.
		$this->assertSame(
			'Past Events',
			$instance->filter_archive_title(),
			'Archive title should be the page title.'
		);

		// Verify the query re-executed with the correct post type.
		$this->assertSame(
			Event::POST_TYPE,
			$wp_query->get( 'post_type' ),
			'Query should target the event post type.'
		);

		// Clean up.
		wp_delete_post( $page_id, true );
		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Tests handle_event_archive_redirect preserves pagination for archive pages.
	 *
	 * @covers ::handle_event_archive_redirect
	 *
	 * @return void
	 */
	public function test_handle_event_archive_redirect_preserves_pagination(): void {
		global $wp_query;

		$instance = Setup::get_instance();

		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'events_url' );

		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_name'   => $rewrite_slug,
				'post_title'  => 'Past Events',
				'post_status' => 'publish',
			)
		);

		$json = wp_json_encode(
			array(
				array(
					'id'    => $page_id,
					'slug'  => $rewrite_slug,
					'value' => 'Past Events',
				),
			)
		);

		update_option( 'gatherpress_settings', array( 'past_events' => $json ) );

		// Mock being on page 2 of the post type archive.
		$wp_query->is_post_type_archive = true;
		$wp_query->set( 'post_type', Event::POST_TYPE );
		set_query_var( 'paged', 2 );

		$instance->handle_event_archive_redirect();

		// Verify pagination is preserved in the re-query.
		$this->assertSame(
			2,
			(int) $wp_query->get( 'paged' ),
			'Pagination should be preserved after archive redirect.'
		);

		// Clean up.
		set_query_var( 'paged', 0 );
		wp_delete_post( $page_id, true );
		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Tests filter_archive_title returns the stored archive title.
	 *
	 * @covers ::filter_archive_title
	 *
	 * @return void
	 */
	public function test_filter_archive_title(): void {
		$instance = Setup::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'archive_title', 'Test Title' );

		$this->assertSame(
			'Test Title',
			$instance->filter_archive_title(),
			'Archive title should return the stored value.'
		);

		// Clean up.
		Utility::set_and_get_hidden_property( $instance, 'archive_title', '' );
	}
}
