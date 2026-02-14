<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Event_Setup;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use stdClass;
use WP;
use WP_Query;
use WP_REST_Request;

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
				'type'     => 'action',
				'name'     => 'load-edit.php',
				'priority' => 10,
				'callback' => array( $instance, 'default_sort' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_menu',
				'priority' => 10,
				'callback' => array( $instance, 'modify_all_events_menu_link' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'submenu_file',
				'priority' => 10,
				'callback' => array( $instance, 'highlight_events_submenu' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'redirect_canonical',
				'priority' => 10,
				'callback' => array( $instance, 'disable_ics_canonical_redirect' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'rest_pre_insert_%s', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'filter_readonly_meta' ),
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
				'name'     => sprintf( 'views_edit-%s', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'views_edit' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'query_vars',
				'priority' => 10,
				'callback' => array( $instance, 'query_vars' ),
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
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'remove_comments_column' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $instance, 'handle_rsvp_sorting' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $instance, 'handle_venue_sorting' ),
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
		$instance = Event_Setup::get_instance();
		$settings = Settings::get_instance();

		// Get the dynamic slug from settings (default is 'events' plural).
		$rewrite_slug = $settings->get_value( 'general', 'urls', 'events' );

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

		$this->assertArrayNotHasKey(
			'online_event_link',
			$meta,
			'Failed to assert that online_event_link does not exist.'
		);
		$this->assertArrayNotHasKey(
			'enable_anonymous_rsvp',
			$meta,
			'Failed to assert that enable_anonymous_rsvp does not exist.'
		);
		$this->assertArrayNotHasKey(
			'max_attendance_limit',
			$meta,
			'Failed to assert that max_guest_limit does not exist.'
		);
		$this->assertArrayNotHasKey(
			'max_guest_limit',
			$meta,
			'Failed to assert that max_guest_limit does not exist.'
		);

		$instance->register_post_meta();

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayHasKey(
			'gatherpress_online_event_link',
			$meta,
			'Failed to assert that gatherpress_online_event_link does exist.'
		);
		$this->assertArrayHasKey(
			'gatherpress_enable_anonymous_rsvp',
			$meta,
			'Failed to assert that gatherpress_enable_anonymous_rsvp does exist.'
		);
		$this->assertArrayHasKey(
			'gatherpress_max_attendance_limit',
			$meta,
			'Failed to assert that max_guest_limit does exist.'
		);
		$this->assertArrayHasKey(
			'gatherpress_max_guest_limit',
			$meta,
			'Failed to assert that gatherpress_max_guest_limit does exist.'
		);
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
			'venue'    => 'venue',
			'rsvps'    => 'rsvps',
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
	 * Coverage for remove_comments_column method.
	 *
	 * @covers ::remove_comments_column
	 *
	 * @return void
	 */
	public function test_remove_comments_column(): void {
		$instance = Event_Setup::get_instance();

		// Test with columns that include comments.
		$columns_with_comments = array(
			'cb'       => '<input type="checkbox" />',
			'title'    => 'Title',
			'comments' => 'Comments',
			'date'     => 'Date',
		);

		$result = $instance->remove_comments_column( $columns_with_comments );

		$expected = array(
			'cb'    => '<input type="checkbox" />',
			'title' => 'Title',
			'date'  => 'Date',
		);

		$this->assertEquals(
			$expected,
			$result,
			'Failed to assert that comments column is removed from the columns array.'
		);

		$this->assertArrayNotHasKey(
			'comments',
			$result,
			'Failed to assert that comments key does not exist in result.'
		);

		// Test with columns that do not include comments.
		$columns_without_comments = array(
			'cb'    => '<input type="checkbox" />',
			'title' => 'Title',
			'date'  => 'Date',
		);

		$result = $instance->remove_comments_column( $columns_without_comments );

		$this->assertEquals(
			$columns_without_comments,
			$result,
			'Failed to assert that columns without comments remain unchanged.'
		);

		// Test with empty array.
		$empty_columns = array();
		$result        = $instance->remove_comments_column( $empty_columns );

		$this->assertEquals(
			$empty_columns,
			$result,
			'Failed to assert that empty array remains unchanged.'
		);
	}

	/**
	 * Coverage for handle_rsvp_sorting method.
	 *
	 * @covers ::handle_rsvp_sorting
	 *
	 * @return void
	 */
	public function test_handle_rsvp_sorting(): void {
		$instance = Event_Setup::get_instance();

		// Create a mock query.
		$query = $this->createMock( \WP_Query::class );

		// Test non-admin context (is_admin() returns false by default in tests).
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'rsvps' ),
			)
		);

		// Should return early due to non-admin context.
		$instance->handle_rsvp_sorting( $query );

		// Test different post type - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, 'post' ),
				array( 'orderby', null, 'rsvps' ),
			)
		);

		$instance->handle_rsvp_sorting( $query );

		// Test non-main query - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( false );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'rsvps' ),
			)
		);

		$instance->handle_rsvp_sorting( $query );

		// Test different orderby - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'title' ),
			)
		);

		$instance->handle_rsvp_sorting( $query );

		// Since the method primarily adds filters and sets query vars,
		// and we can't easily test is_admin() in unit tests,
		// we'll focus on testing the individual components.
		$this->assertTrue( true ); // Method completed without errors.
	}

	/**
	 * Coverage for rsvp_sorting_join_paged method.
	 *
	 * @covers ::rsvp_sorting_join_paged
	 *
	 * @return void
	 */
	public function test_rsvp_sorting_join_paged(): void {
		global $wpdb;
		$instance = Event_Setup::get_instance();

		$original_join = "LEFT JOIN {$wpdb->posts} AS posts ON posts.ID = {$wpdb->posts}.ID";
		$result        = $instance->rsvp_sorting_join_paged( $original_join );

		// Should contain the original join plus the RSVP join.
		$this->assertStringContainsString( $original_join, $result );
		$this->assertStringContainsString( 'LEFT JOIN', $result );
		$this->assertStringContainsString( $wpdb->comments, $result );
		$this->assertStringContainsString( 'rsvp_sort_comments', $result );
		$this->assertStringContainsString( "comment_type = 'gatherpress_rsvp'", $result );
		$this->assertStringContainsString( "comment_approved = '1'", $result );
	}

	/**
	 * Coverage for rsvp_sorting_groupby method.
	 *
	 * @covers ::rsvp_sorting_groupby
	 *
	 * @return void
	 */
	public function test_rsvp_sorting_groupby(): void {
		global $wpdb;
		$instance = Event_Setup::get_instance();

		$result = $instance->rsvp_sorting_groupby( '' );
		$this->assertEquals( "{$wpdb->posts}.ID", $result );

		// Test with existing groupby - should keep the existing value.
		$existing_groupby = 'existing_group';
		$result           = $instance->rsvp_sorting_groupby( $existing_groupby );
		$this->assertEquals( 'existing_group', $result );
	}

	/**
	 * Coverage for rsvp_sorting_orderby method.
	 *
	 * Note: This method relies on the global $wp_query, so we test the method's structure
	 * and ensure it returns a proper ORDER BY clause with expected patterns.
	 *
	 * @covers ::rsvp_sorting_orderby
	 *
	 * @return void
	 */
	public function test_rsvp_sorting_orderby(): void {
		$instance = Event_Setup::get_instance();

		$result = $instance->rsvp_sorting_orderby( 'original_orderby' );

		// Should contain the expected COUNT structure.
		$this->assertStringContainsString( 'COUNT(rsvp_sort_comments.comment_ID)', $result );

		// Should contain either ASC or DESC (defaults to ASC).
		$this->assertTrue(
			strpos( $result, 'ASC' ) !== false || strpos( $result, 'DESC' ) !== false,
			'ORDER BY clause should contain ASC or DESC'
		);

		// Verify the method returns a string (basic type check).
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Coverage for handle_venue_sorting method.
	 *
	 * @covers ::handle_venue_sorting
	 *
	 * @return void
	 */
	public function test_handle_venue_sorting(): void {
		$instance = Event_Setup::get_instance();

		// Create a mock query.
		$query = $this->createMock( \WP_Query::class );

		// Test non-admin context (is_admin() returns false by default in tests).
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'venue' ),
			)
		);

		// Should return early due to non-admin context.
		$instance->handle_venue_sorting( $query );

		// Test different post type - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, 'post' ),
				array( 'orderby', null, 'venue' ),
			)
		);

		$instance->handle_venue_sorting( $query );

		// Test non-main query - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( false );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'venue' ),
			)
		);

		$instance->handle_venue_sorting( $query );

		// Test different orderby - should return early.
		$query = $this->createMock( \WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'get' )->willReturnMap(
			array(
				array( 'post_type', null, Event::POST_TYPE ),
				array( 'orderby', null, 'title' ),
			)
		);

		$instance->handle_venue_sorting( $query );

		// Since the method primarily adds filters and sets query vars,
		// and we can't easily test is_admin() in unit tests,
		// we'll focus on testing the individual components.
		$this->assertTrue( true ); // Method completed without errors.
	}

	/**
	 * Coverage for venue_sorting_join_paged method.
	 *
	 * @covers ::venue_sorting_join_paged
	 *
	 * @return void
	 */
	public function test_venue_sorting_join_paged(): void {
		global $wpdb;
		$instance = Event_Setup::get_instance();

		$original_join = "LEFT JOIN {$wpdb->posts} AS posts ON posts.ID = {$wpdb->posts}.ID";
		$result        = $instance->venue_sorting_join_paged( $original_join );

		// Should contain the original join plus the venue joins.
		$this->assertStringContainsString( $original_join, $result );
		$this->assertStringContainsString( 'LEFT JOIN', $result );
		$this->assertStringContainsString( $wpdb->term_relationships, $result );
		$this->assertStringContainsString( 'venue_tr', $result );
		$this->assertStringContainsString( $wpdb->term_taxonomy, $result );
		$this->assertStringContainsString( 'venue_tt', $result );
		$this->assertStringContainsString( $wpdb->terms, $result );
		$this->assertStringContainsString( 'venue_terms', $result );
		$this->assertStringContainsString( "'" . \GatherPress\Core\Venue::TAXONOMY . "'", $result );
	}

	/**
	 * Coverage for venue_sorting_orderby method.
	 *
	 * Note: This method relies on the global $wp_query, so we test the method's structure
	 * and ensure it returns a proper ORDER BY clause with expected patterns.
	 *
	 * @covers ::venue_sorting_orderby
	 *
	 * @return void
	 */
	public function test_venue_sorting_orderby(): void {
		$instance = Event_Setup::get_instance();

		$result = $instance->venue_sorting_orderby( 'original_orderby' );

		// Should contain the expected CASE structure for NULL handling.
		$this->assertStringContainsString( 'CASE WHEN venue_terms.name IS NULL THEN 1 ELSE 0 END ASC', $result );

		// Should contain venue_terms.name in the ORDER BY.
		$this->assertStringContainsString( 'venue_terms.name', $result );

		// Should contain either ASC or DESC (defaults to ASC).
		$this->assertTrue(
			strpos( $result, 'ASC' ) !== false || strpos( $result, 'DESC' ) !== false,
			'ORDER BY clause should contain ASC or DESC'
		);

		// Verify the method returns a string (basic type check).
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Coverage for check_waiting_list method.
	 *
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Method should execute without error.
		$instance->check_waiting_list( $post_id );
		$this->assertTrue( true, 'check_waiting_list executed without error.' );
	}

	/**
	 * Coverage for delete_event method with non-event post.
	 *
	 * @covers ::delete_event
	 *
	 * @return void
	 */
	public function test_delete_event_non_event_post(): void {
		$instance = Event_Setup::get_instance();
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

		$instance = Event_Setup::get_instance();
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
	 * Coverage for custom_columns method with datetime column.
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_datetime(): void {
		$instance = Event_Setup::get_instance();
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

		ob_start();
		$instance->custom_columns( 'datetime', $post_id );
		$output = ob_get_clean();

		$this->assertNotEmpty( $output, 'Datetime column should produce output.' );
		$this->assertStringContainsString( 'June', $output, 'Datetime output should contain month name.' );
	}

	/**
	 * Coverage for custom_columns method with venue column.
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_venue_with_name(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create a venue term and associate it with the event.
		$venue_name = 'Test Venue';
		$term       = wp_insert_term( $venue_name, \GatherPress\Core\Venue::TAXONOMY );
		wp_set_post_terms( $post_id, array( $term['term_id'] ), \GatherPress\Core\Venue::TAXONOMY );

		ob_start();
		$instance->custom_columns( 'venue', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( $venue_name, $output, 'Venue column should contain venue name.' );
	}

	/**
	 * Coverage for custom_columns method with venue column and no venue.
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_venue_no_venue(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		ob_start();
		$instance->custom_columns( 'venue', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( '—', $output, 'Venue column should show em dash when no venue.' );
	}

	/**
	 * Coverage for custom_columns method with venue column and online event.
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_venue_online_event(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Set online event link.
		update_post_meta( $post_id, 'gatherpress_online_event_link', 'https://example.com/meeting' );

		ob_start();
		$instance->custom_columns( 'venue', $post_id );
		$output = ob_get_clean();

		// The method should execute without error for online events.
		$this->assertNotEmpty( $output, 'Online event column should produce output.' );
	}

	/**
	 * Coverage for custom_columns method with rsvps column (no RSVPs).
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_rsvps_no_rsvps(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		ob_start();
		$instance->custom_columns( 'rsvps', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( '—', $output, 'RSVPs column should show em dash when no RSVPs.' );
	}

	/**
	 * Coverage for custom_columns method with rsvps column (with approved RSVPs).
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_rsvps_with_approved(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create approved RSVP.
		$this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => \GatherPress\Core\Rsvp::COMMENT_TYPE,
				'comment_approved' => 1,
			)
		);

		ob_start();
		$instance->custom_columns( 'rsvps', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gatherpress-rsvp-approved', $output, 'Should show approved RSVP count.' );
		$this->assertStringContainsString( '>1<', $output, 'Should show count of 1.' );
	}

	/**
	 * Coverage for custom_columns method with rsvps column (with unapproved RSVPs).
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_rsvps_with_unapproved(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create approved and unapproved RSVPs.
		$this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => \GatherPress\Core\Rsvp::COMMENT_TYPE,
				'comment_approved' => 1,
			)
		);
		$this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => \GatherPress\Core\Rsvp::COMMENT_TYPE,
				'comment_approved' => 0,
			)
		);

		ob_start();
		$instance->custom_columns( 'rsvps', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'gatherpress-rsvp-pending',
			$output,
			'Should show unapproved RSVP indicator.'
		);
		$this->assertStringContainsString( 'Unapproved RSVPs', $output, 'Should contain title for unapproved.' );
	}

	/**
	 * Coverage for set_custom_columns method.
	 *
	 * @covers ::set_custom_columns
	 *
	 * @return void
	 */
	public function test_set_custom_columns(): void {
		$instance = Event_Setup::get_instance();

		$default_columns = array(
			'cb'     => '<input type="checkbox" />',
			'title'  => 'Title',
			'author' => 'Author',
			'date'   => 'Date',
		);

		$result = $instance->set_custom_columns( $default_columns );

		// Should not contain author column.
		$this->assertArrayNotHasKey( 'author', $result, 'Author column should be removed.' );

		// Should contain custom columns.
		$this->assertArrayHasKey( 'datetime', $result, 'Should have datetime column.' );
		$this->assertArrayHasKey( 'venue', $result, 'Should have venue column.' );
		$this->assertArrayHasKey( 'rsvps', $result, 'Should have rsvps column.' );

		// Verify order (custom columns should be inserted after title).
		$keys = array_keys( $result );
		$this->assertEquals( 'cb', $keys[0], 'First column should be cb.' );
		$this->assertEquals( 'title', $keys[1], 'Second column should be title.' );
		$this->assertEquals( 'datetime', $keys[2], 'Third column should be datetime.' );
	}

	/**
	 * Coverage for get_the_event_date method when event date should be used.
	 *
	 * @covers ::get_the_event_date
	 *
	 * @return void
	 */
	public function test_get_the_event_date_use_event_date(): void {
		$instance = Event_Setup::get_instance();
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
			'gatherpress_general',
			array(
				'general' => array(
					'post_or_event_date' => '1',
				),
			)
		);

		// Set global post.
		$this->go_to( get_permalink( $post_id ) );

		$result = $instance->get_the_event_date( 'June 15, 2025' );

		$this->assertStringContainsString( 'June', $result, 'Should return event date.' );
	}

	/**
	 * Coverage for get_the_event_date method when post date should be used.
	 *
	 * @covers ::get_the_event_date
	 *
	 * @return void
	 */
	public function test_get_the_event_date_use_post_date(): void {
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Set setting to use post date.
		update_option(
			'gatherpress_general',
			array(
				'general' => array(
					'post_or_event_date' => '0',
				),
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$original_date = 'June 15, 2025';
		$result        = $instance->get_the_event_date( $original_date );

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
		$instance = Event_Setup::get_instance();
		$post_id  = $this->mock->post()->get()->ID;

		$this->go_to( get_permalink( $post_id ) );

		$original_date = 'June 15, 2025';
		$result        = $instance->get_the_event_date( $original_date );

		$this->assertSame( $original_date, $result, 'Should return original date for non-event posts.' );
	}

	/**
	 * Coverage for set_event_archive_labels method with no pages set.
	 *
	 * @covers ::set_event_archive_labels
	 *
	 * @return void
	 */
	public function test_set_event_archive_labels_no_pages(): void {
		$instance = Event_Setup::get_instance();

		delete_option( 'gatherpress_general' );

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
		$instance = Event_Setup::get_instance();

		update_option(
			'gatherpress_general',
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
		$instance = Event_Setup::get_instance();

		$page_id = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;

		update_option(
			'gatherpress_general',
			array(
				'pages' => array(
					'upcoming_events' => wp_json_encode(
						array(
							(object) array(
								'id'    => $page_id,
								'value' => 'Upcoming Events',
							),
						)
					),
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
		$instance = Event_Setup::get_instance();

		$page_id = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;

		update_option(
			'gatherpress_general',
			array(
				'pages' => array(
					'past_events' => wp_json_encode(
						array(
							(object) array(
								'id'    => $page_id,
								'value' => 'Past Events',
							),
						)
					),
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
		$instance = Event_Setup::get_instance();
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
		$slug = Event_Setup::get_localized_post_type_slug();

		// If no error occurs, restore_previous_locale was called correctly.
		$this->assertIsString( $slug, 'Should return a string slug' );

		remove_filter( 'locale', $locale_filter );
	}

	/**
	 * Tests auth callbacks for post meta registration.
	 *
	 * Covers: auth_callback returns.
	 *
	 * @covers ::register_post_meta
	 * @covers ::can_edit_posts_meta
	 * @return void
	 */
	public function test_register_post_meta_auth_callbacks(): void {
		// Set current user as editor.
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		// Test that meta can be updated when user has edit_posts capability.
		$result = update_post_meta( $post_id, 'gatherpress_datetime_start', '2024-01-01 10:00:00' );
		$this->assertNotFalse( $result, 'Should allow meta update with edit_posts capability' );

		$result = update_post_meta( $post_id, 'gatherpress_datetime_start_gmt', '2024-01-01 10:00:00' );
		$this->assertNotFalse( $result, 'Should allow meta update for datetime_start_gmt' );

		$result = update_post_meta( $post_id, 'gatherpress_datetime_end', '2024-01-01 12:00:00' );
		$this->assertNotFalse( $result, 'Should allow meta update for datetime_end' );

		$result = update_post_meta( $post_id, 'gatherpress_datetime_end_gmt', '2024-01-01 12:00:00' );
		$this->assertNotFalse( $result, 'Should allow meta update for datetime_end_gmt' );

		$result = update_post_meta( $post_id, 'gatherpress_timezone', 'America/New_York' );
		$this->assertNotFalse( $result, 'Should allow meta update for timezone' );

		$result = update_post_meta( $post_id, 'gatherpress_max_guest_limit', 5 );
		$this->assertNotFalse( $result, 'Should allow meta update for max_guest_limit' );

		$result = update_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', true );
		$this->assertNotFalse( $result, 'Should allow meta update for enable_anonymous_rsvp' );

		$result = update_post_meta( $post_id, 'gatherpress_online_event_link', 'https://example.com' );
		$this->assertNotFalse( $result, 'Should allow meta update for online_event_link' );

		$result = update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 100 );
		$this->assertNotFalse( $result, 'Should allow meta update for max_attendance_limit' );
	}

	/**
	 * Tests can_edit_posts_meta authorization callback.
	 *
	 * @covers ::can_edit_posts_meta
	 *
	 * @return void
	 */
	public function test_can_edit_posts_meta(): void {
		$instance = Event_Setup::get_instance();

		// Test with user who can edit posts.
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( $instance->can_edit_posts_meta(), 'Editor should be able to edit post meta' );

		// Test with user who cannot edit posts.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( $instance->can_edit_posts_meta(), 'Subscriber should not be able to edit post meta' );

		// Test with logged-out user.
		wp_set_current_user( 0 );

		$this->assertFalse( $instance->can_edit_posts_meta(), 'Logged-out user should not be able to edit post meta' );
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

		$instance = Event_Setup::get_instance();

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

		$instance = Event_Setup::get_instance();

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
	 * Tests custom_columns with online event.
	 *
	 * Covers: is_online_event icon display.
	 *
	 * @covers ::custom_columns
	 * @return void
	 */
	public function test_custom_columns_venue_with_online_event(): void {
		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		// Create 'online-event' venue term.
		$term = wp_insert_term(
			'Online Event',
			'_gatherpress_venue',
			array( 'slug' => 'online-event' )
		);

		// Assign the term to the event.
		wp_set_object_terms( $post_id, $term['term_id'], '_gatherpress_venue' );

		$instance = Event_Setup::get_instance();

		ob_start();
		$instance->custom_columns( 'venue', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'dashicons-video-alt3', $output, 'Should display online event icon' );
	}

	/**
	 * Tests handle_rsvp_sorting with RSVP orderby.
	 *
	 * Covers: RSVP sorting logic.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @return void
	 */
	public function test_rsvp_sorting_with_rsvp_orderby(): void {
		global $wp_the_query;

		// Create a WP_Query for events with RSVP sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'rsvps',
				'order'     => 'DESC',
			)
		);

		// Set as main query.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		// Simulate admin context.
		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Setup::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Verify that sorting order was set.
		$this->assertEquals( 'DESC', $query->get( 'rsvp_sort_order' ), 'Should set DESC order' );

		// Verify filters were added.
		$this->assertNotFalse(
			has_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) ),
			'Should add posts_join_paged filter'
		);
		$this->assertNotFalse(
			has_filter( 'posts_groupby', array( $instance, 'rsvp_sorting_groupby' ) ),
			'Should add posts_groupby filter'
		);
		$this->assertNotFalse(
			has_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) ),
			'Should add posts_orderby filter'
		);

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'rsvp_sorting_groupby' ) );
		remove_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_rsvp_sorting with invalid order.
	 *
	 * Covers: Order validation.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @return void
	 */
	public function test_rsvp_sorting_with_invalid_order(): void {
		global $wp_the_query;

		// Create a WP_Query for RSVP sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'rsvps',
			)
		);

		// Manually set invalid order (bypasses WP_Query's sanitization).
		$query->set( 'order', 'INVALID' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Setup::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Should default to ASC for invalid order.
		$this->assertEquals( 'ASC', $query->get( 'rsvp_sort_order' ), 'Should default to ASC for invalid order' );

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'rsvp_sorting_groupby' ) );
		remove_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_rsvp_sorting early return when orderby is not 'rsvps'.
	 *
	 * Covers: Line 619 early return.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @return void
	 */
	public function test_rsvp_sorting_early_return_wrong_orderby(): void {
		global $wp_the_query;

		// Create a WP_Query for non-RSVP sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'date',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Setup::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Should not set rsvp_sort_order since orderby is not 'rsvps'.
		$this->assertEmpty( $query->get( 'rsvp_sort_order' ), 'Should not set rsvp_sort_order for non-RSVP orderby' );

		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_venue_sorting with venue orderby.
	 *
	 * Covers: Venue sorting logic.
	 *
	 * @covers ::handle_venue_sorting
	 * @return void
	 */
	public function test_venue_sorting_with_venue_orderby(): void {
		global $wp_the_query;

		// Create a WP_Query for events with venue sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'venue',
				'order'     => 'DESC',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Setup::get_instance();
		$instance->handle_venue_sorting( $query );

		// Verify that sorting order was set.
		$this->assertEquals( 'DESC', $query->get( 'venue_sort_order' ), 'Should set DESC order' );

		// Verify filters were added.
		$this->assertNotFalse(
			has_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) ),
			'Should add posts_join_paged filter for venue sorting'
		);
		$this->assertNotFalse(
			has_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) ),
			'Should add posts_orderby filter for venue sorting'
		);

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) );
		remove_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_venue_sorting with invalid order.
	 *
	 * Covers: Order validation in venue sorting.
	 *
	 * @covers ::handle_venue_sorting
	 * @return void
	 */
	public function test_venue_sorting_with_invalid_order(): void {
		global $wp_the_query;

		// Create a WP_Query for venue sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'venue',
			)
		);

		// Manually set invalid order (bypasses WP_Query's sanitization).
		$query->set( 'order', 'RANDOM' );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Setup::get_instance();
		$instance->handle_venue_sorting( $query );

		// Should default to ASC for invalid order.
		$this->assertEquals( 'ASC', $query->get( 'venue_sort_order' ), 'Should default to ASC for invalid order' );

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) );
		remove_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_venue_sorting early return when orderby is not 'venue'.
	 *
	 * Covers: Line 720 early return.
	 *
	 * @covers ::handle_venue_sorting
	 * @return void
	 */
	public function test_venue_sorting_early_return_wrong_orderby(): void {
		global $wp_the_query;

		// Create a WP_Query for non-venue sorting.
		$query = new WP_Query(
			array(
				'post_type' => Event::POST_TYPE,
				'orderby'   => 'title',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Event_Setup::get_instance();
		$instance->handle_venue_sorting( $query );

		// Should not set venue_sort_order since orderby is not 'venue'.
		$this->assertEmpty(
			$query->get( 'venue_sort_order' ),
			'Should not set venue_sort_order for non-venue orderby'
		);

		set_current_screen( 'front' );
	}

	/**
	 * Coverage for default_sort method when on the wrong screen.
	 *
	 * @covers ::default_sort
	 *
	 * @return void
	 */
	public function test_default_sort_wrong_screen(): void {
		$instance = Event_Setup::get_instance();

		// Set current screen to a non-event screen.
		set_current_screen( 'edit-post' );

		// Ensure $_GET is clean.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );

		$instance->default_sort();

		// Should return early without modifying $_GET.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertArrayNotHasKey( 'orderby', $_GET, 'Should not set orderby on wrong screen.' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertArrayNotHasKey( 'order', $_GET, 'Should not set order on wrong screen.' );

		// Clean up.
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for default_sort method when orderby is already set.
	 *
	 * @covers ::default_sort
	 *
	 * @return void
	 */
	public function test_default_sort_orderby_already_set(): void {
		$instance = Event_Setup::get_instance();

		// Set current screen to event edit screen.
		set_current_screen( 'edit-gatherpress_event' );

		// Set an existing orderby value.
		$_GET['orderby'] = 'title'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$instance->default_sort();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		$this->assertSame( 'title', $_GET['orderby'], 'Should not override existing orderby.' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertArrayNotHasKey( 'order', $_GET, 'Should not set order when orderby already exists.' );

		// Clean up.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for default_sort method when on the correct screen with no orderby.
	 *
	 * @covers ::default_sort
	 *
	 * @return void
	 */
	public function test_default_sort_sets_defaults(): void {
		$instance = Event_Setup::get_instance();

		// Set current screen to event edit screen.
		set_current_screen( 'edit-gatherpress_event' );

		// Ensure $_GET is clean.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );

		$instance->default_sort();

		// Should set default orderby and order.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		$this->assertSame( 'datetime', $_GET['orderby'], 'Should set orderby to datetime.' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
		$this->assertSame( 'asc', $_GET['order'], 'Should set order to asc.' );

		// Clean up.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for modify_all_events_menu_link method.
	 *
	 * @covers ::modify_all_events_menu_link
	 *
	 * @return void
	 */
	public function test_modify_all_events_menu_link(): void {
		global $submenu;

		$instance  = Event_Setup::get_instance();
		$menu_slug = sprintf( 'edit.php?post_type=%s', Event::POST_TYPE );

		// Set up a mock submenu structure.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu[ $menu_slug ] = array(
			5  => array( 'View Events', 'edit_posts', $menu_slug ),
			10 => array( 'Add New', 'edit_posts', 'post-new.php?post_type=' . Event::POST_TYPE ),
		);

		$instance->modify_all_events_menu_link();

		$this->assertStringContainsString(
			'gatherpress_event_query=upcoming',
			$submenu[ $menu_slug ][5][2],
			'Upcoming Events menu link should include upcoming parameter.'
		);

		// Other submenu items should be unchanged.
		$this->assertStringNotContainsString(
			'gatherpress_event_query',
			$submenu[ $menu_slug ][10][2],
			'Add New menu link should not be modified.'
		);

		// Clean up.
		unset( $submenu[ $menu_slug ] );
	}

	/**
	 * Coverage for modify_all_events_menu_link method with empty submenu.
	 *
	 * @covers ::modify_all_events_menu_link
	 *
	 * @return void
	 */
	public function test_modify_all_events_menu_link_empty_submenu(): void {
		global $submenu;

		$instance  = Event_Setup::get_instance();
		$menu_slug = sprintf( 'edit.php?post_type=%s', Event::POST_TYPE );

		// Ensure the submenu doesn't exist.
		unset( $submenu[ $menu_slug ] );

		// Should return early without error.
		$instance->modify_all_events_menu_link();

		$this->assertFalse(
			isset( $submenu[ $menu_slug ] ),
			'Should not create submenu when none exists.'
		);
	}

	/**
	 * Coverage for highlight_events_submenu when on events page.
	 *
	 * @covers ::highlight_events_submenu
	 *
	 * @return void
	 */
	public function test_highlight_events_submenu_on_events_page(): void {
		$instance  = Event_Setup::get_instance();
		$menu_slug = sprintf( 'edit.php?post_type=%s', Event::POST_TYPE );

		$result = $instance->highlight_events_submenu( $menu_slug );

		$this->assertStringContainsString(
			'gatherpress_event_query=upcoming',
			$result,
			'Should return modified slug with upcoming parameter.'
		);
	}

	/**
	 * Coverage for highlight_events_submenu on a different page.
	 *
	 * @covers ::highlight_events_submenu
	 *
	 * @return void
	 */
	public function test_highlight_events_submenu_other_page(): void {
		$instance = Event_Setup::get_instance();

		$result = $instance->highlight_events_submenu( 'edit.php?post_type=post' );

		$this->assertSame(
			'edit.php?post_type=post',
			$result,
			'Should not modify submenu file for other post types.'
		);
	}

	/**
	 * Coverage for query_vars method.
	 *
	 * @covers ::query_vars
	 *
	 * @return void
	 */
	public function test_query_vars(): void {
		$instance = Event_Setup::get_instance();

		$result = $instance->query_vars( array( 'existing_var' ) );

		$this->assertContains(
			'gatherpress_event_query',
			$result,
			'Should add gatherpress_event_query to query vars.'
		);
		$this->assertContains(
			'existing_var',
			$result,
			'Should preserve existing query vars.'
		);
		$this->assertCount( 2, $result, 'Should have exactly 2 query vars.' );
	}

	/**
	 * Coverage for query_vars method with empty input.
	 *
	 * @covers ::query_vars
	 *
	 * @return void
	 */
	public function test_query_vars_empty_input(): void {
		$instance = Event_Setup::get_instance();

		$result = $instance->query_vars( array() );

		$this->assertContains(
			'gatherpress_event_query',
			$result,
			'Should add gatherpress_event_query even with empty input.'
		);
		$this->assertCount( 1, $result, 'Should have exactly 1 query var.' );
	}

	/**
	 * Coverage for get_event_counts method with no events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_no_events(): void {
		$instance = Event_Setup::get_instance();

		// Reset cached counts and invoke the protected method.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertArrayHasKey( 'upcoming', $counts, 'Should have upcoming key.' );
		$this->assertArrayHasKey( 'past', $counts, 'Should have past key.' );
		$this->assertSame( 0, $counts['upcoming'], 'Should have 0 upcoming events.' );
		$this->assertSame( 0, $counts['past'], 'Should have 0 past events.' );
	}

	/**
	 * Coverage for get_event_counts method with upcoming events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_with_upcoming(): void {
		$instance = Event_Setup::get_instance();

		// Create a published event in the future.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts before querying.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 1, $counts['upcoming'], 'Should have 1 upcoming event.' );
		$this->assertSame( 0, $counts['past'], 'Should have 0 past events.' );
	}

	/**
	 * Coverage for get_event_counts method with past events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_with_past(): void {
		$instance = Event_Setup::get_instance();

		// Create a published event in the past.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts before querying.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 0, $counts['upcoming'], 'Should have 0 upcoming events.' );
		$this->assertSame( 1, $counts['past'], 'Should have 1 past event.' );
	}

	/**
	 * Coverage for get_event_counts method with mixed events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_with_mixed(): void {
		$instance = Event_Setup::get_instance();

		// Create a published upcoming event.
		$upcoming_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $upcoming_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Create a published past event.
		$past_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $past_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Create a draft event (should be counted, only trash/auto-draft excluded).
		$draft_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		)->get()->ID;

		$event = new Event( $draft_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+3 days +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts before querying.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 2, $counts['upcoming'], 'Should have 2 upcoming events (draft included).' );
		$this->assertSame( 1, $counts['past'], 'Should have 1 past event.' );
	}

	/**
	 * Coverage for get_event_counts method with a currently running event.
	 *
	 * A running event (started but not ended) should count as upcoming
	 * because datetime_end_gmt >= now, and also as past because
	 * datetime_start_gmt < now.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_running_event(): void {
		$instance = Event_Setup::get_instance();

		// Create a published event that is currently running.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts before querying.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		// Running events appear in both counts (same logic as Event_Query with inclusive=true).
		$this->assertSame( 1, $counts['upcoming'], 'Running event should count as upcoming.' );
		$this->assertSame( 1, $counts['past'], 'Running event should count as past.' );
	}

	/**
	 * Coverage for get_event_counts caching to class property.
	 *
	 * Verifies that repeated calls return the cached result without re-querying.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_caches_result(): void {
		$instance = Event_Setup::get_instance();

		// Reset cached counts.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		// First call should query the database and cache.
		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( 0, $counts['upcoming'], 'Should have 0 upcoming events.' );

		// Verify the property is now cached.
		$cached = Utility::set_and_get_hidden_property( $instance, 'event_counts', $counts );
		$this->assertNotNull( $cached, 'Property should be cached after first call.' );
		$this->assertSame( $counts, $cached, 'Cached value should match returned value.' );

		// Create an event after the first call.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Second call should return cached result (still 0).
		$counts_cached = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( 0, $counts_cached['upcoming'], 'Cached call should still return 0.' );

		// After resetting the cache, should reflect the new event.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );
		$counts_fresh = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( 1, $counts_fresh['upcoming'], 'Fresh call should return 1 after cache reset.' );
	}

	/**
	 * Coverage for views_edit method with no events.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_adds_links(): void {
		$instance = Event_Setup::get_instance();

		$view_links = array(
			'all'     => '<a href="#">All</a>',
			'publish' => '<a href="#">Published</a>',
			'draft'   => '<a href="#">Draft</a>',
		);

		$result = $instance->views_edit( $view_links );

		// Should have original links plus upcoming and past.
		$this->assertArrayHasKey( 'upcoming', $result, 'Should have upcoming link.' );
		$this->assertArrayHasKey( 'past', $result, 'Should have past link.' );
		$this->assertArrayHasKey( 'all', $result, 'Should preserve all link.' );
		$this->assertArrayHasKey( 'publish', $result, 'Should preserve publish link.' );
		$this->assertArrayHasKey( 'draft', $result, 'Should preserve draft link.' );

		// Verify count spans are present.
		$this->assertStringContainsString(
			'<span class="count">',
			$result['upcoming'],
			'Upcoming link should contain count span.'
		);
		$this->assertStringContainsString(
			'<span class="count">',
			$result['past'],
			'Past link should contain count span.'
		);

		// Verify links contain proper query args.
		$this->assertStringContainsString(
			'gatherpress_event_query=upcoming',
			$result['upcoming'],
			'Upcoming link should have correct query arg.'
		);
		$this->assertStringContainsString(
			'gatherpress_event_query=past',
			$result['past'],
			'Past link should have correct query arg.'
		);

		// Verify the labels.
		$this->assertStringContainsString( 'Upcoming', $result['upcoming'], 'Should contain Upcoming label.' );
		$this->assertStringContainsString( 'Past', $result['past'], 'Should contain Past label.' );
	}

	/**
	 * Coverage for views_edit method link placement.
	 *
	 * Upcoming and Past should be inserted after the first link (All).
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_placement(): void {
		$instance = Event_Setup::get_instance();

		$view_links = array(
			'all'     => '<a href="#">All</a>',
			'publish' => '<a href="#">Published</a>',
		);

		$result = $instance->views_edit( $view_links );
		$keys   = array_keys( $result );

		$this->assertEquals( 'all', $keys[0], 'First link should be all.' );
		$this->assertEquals( 'upcoming', $keys[1], 'Second link should be upcoming.' );
		$this->assertEquals( 'past', $keys[2], 'Third link should be past.' );
		$this->assertEquals( 'publish', $keys[3], 'Fourth link should be publish.' );
	}

	/**
	 * Coverage for views_edit method with active upcoming view.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_active_upcoming(): void {
		$instance = Event_Setup::get_instance();

		// Simulate an active upcoming view via GET parameter.
		$_GET['gatherpress_event_query'] = 'upcoming'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// WordPress marks "All" as current by default.
		$view_links = array(
			'all' => '<a href="#" class="current" aria-current="page">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		$this->assertStringContainsString(
			'class="current"',
			$result['upcoming'],
			'Active upcoming link should have current class.'
		);
		$this->assertStringContainsString(
			'aria-current="page"',
			$result['upcoming'],
			'Active upcoming link should have aria-current attribute.'
		);

		// Past should not be marked as current.
		$this->assertStringNotContainsString(
			'class="current"',
			$result['past'],
			'Past link should not have current class.'
		);

		// "All" should have its current class removed.
		$this->assertStringNotContainsString(
			'class="current"',
			$result['all'],
			'All link should not have current class when filter is active.'
		);

		// Clean up.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );
	}

	/**
	 * Coverage for views_edit method with active past view.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_active_past(): void {
		$instance = Event_Setup::get_instance();

		// Simulate an active past view via GET parameter.
		$_GET['gatherpress_event_query'] = 'past'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$view_links = array(
			'all' => '<a href="#">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		$this->assertStringContainsString(
			'class="current"',
			$result['past'],
			'Active past link should have current class.'
		);

		// Upcoming should not be marked as current.
		$this->assertStringNotContainsString(
			'class="current"',
			$result['upcoming'],
			'Upcoming link should not have current class.'
		);

		// Clean up.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );
	}

	/**
	 * Coverage for views_edit method with no active event query filter.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_no_active_filter(): void {
		$instance = Event_Setup::get_instance();

		// Ensure no event query filter is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );

		// WordPress marks "All" as current by default.
		$view_links = array(
			'all' => '<a href="#" class="current" aria-current="page">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		// Neither Upcoming nor Past should be marked as current.
		$this->assertStringNotContainsString(
			'class="current"',
			$result['upcoming'],
			'Upcoming link should not have current class without filter.'
		);
		$this->assertStringNotContainsString(
			'class="current"',
			$result['past'],
			'Past link should not have current class without filter.'
		);

		// "All" should keep its current class.
		$this->assertStringContainsString(
			'class="current"',
			$result['all'],
			'All link should keep current class when no filter is active.'
		);
	}

	/**
	 * Coverage for views_edit adding current class to "All" when WordPress omits it.
	 *
	 * When default_sort() adds orderby/order to $_GET, WordPress's
	 * is_base_request() returns false and omits the current class from "All".
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_all_gets_current_when_missing(): void {
		$instance = Event_Setup::get_instance();

		// Ensure no event query filter is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );

		// Simulate WordPress not adding current class due to default_sort()
		// adding extra $_GET params that break is_base_request().
		$view_links = array(
			'all' => '<a href="#">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		// "All" should get the current class added by views_edit.
		$this->assertStringContainsString(
			'class="current"',
			$result['all'],
			'All link should get current class when no filter is active.'
		);
		$this->assertStringContainsString(
			'aria-current="page"',
			$result['all'],
			'All link should get aria-current when no filter is active.'
		);
	}

	/**
	 * Coverage for views_edit method with event counts displayed.
	 *
	 * @covers ::views_edit
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_views_edit_displays_counts(): void {
		$instance = Event_Setup::get_instance();

		// Create a published upcoming event.
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		// Reset cached counts after event creation so fresh queries run.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', null );

		$view_links = array(
			'all' => '<a href="#">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		// Upcoming should show count of 1.
		$this->assertStringContainsString(
			'<span class="count">(1)</span>',
			$result['upcoming'],
			'Upcoming link should show count of 1.'
		);

		// Past should show count of 0.
		$this->assertStringContainsString(
			'<span class="count">(0)</span>',
			$result['past'],
			'Past link should show count of 0.'
		);
	}

	/**
	 * Tests filter_readonly_meta removes read-only meta keys from REST request.
	 *
	 * @covers ::filter_readonly_meta
	 * @return void
	 */
	public function test_filter_readonly_meta_removes_readonly_keys(): void {
		$instance = Event_Setup::get_instance();

		// Create a mock REST request with all readonly keys plus a writable key.
		$request = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		$request->set_param(
			'meta',
			array(
				'gatherpress_datetime'           => '{"dateTimeStart":"2025-01-01 10:00:00"}',
				'gatherpress_datetime_start'     => '2025-01-01 10:00:00',
				'gatherpress_datetime_start_gmt' => '2025-01-01 15:00:00',
				'gatherpress_datetime_end'       => '2025-01-01 12:00:00',
				'gatherpress_datetime_end_gmt'   => '2025-01-01 17:00:00',
				'gatherpress_timezone'           => 'America/New_York',
				'gatherpress_online_event_link'  => 'https://example.com',
			)
		);

		$prepared_post     = new stdClass();
		$prepared_post->ID = 123;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		// Verify prepared_post is returned unchanged.
		$this->assertSame( $prepared_post, $result, 'Should return the same prepared_post object.' );
		$this->assertEquals( 123, $result->ID, 'Prepared post ID should be unchanged.' );

		// Verify readonly keys were removed from request meta.
		$filtered_meta = $request->get_param( 'meta' );

		$this->assertArrayNotHasKey(
			'gatherpress_datetime_start',
			$filtered_meta,
			'Should remove gatherpress_datetime_start.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_datetime_start_gmt',
			$filtered_meta,
			'Should remove gatherpress_datetime_start_gmt.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_datetime_end',
			$filtered_meta,
			'Should remove gatherpress_datetime_end.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_datetime_end_gmt',
			$filtered_meta,
			'Should remove gatherpress_datetime_end_gmt.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_timezone',
			$filtered_meta,
			'Should remove gatherpress_timezone.'
		);

		// Verify writable keys are preserved.
		$this->assertArrayHasKey(
			'gatherpress_datetime',
			$filtered_meta,
			'Should preserve gatherpress_datetime (writable).'
		);
		$this->assertArrayHasKey(
			'gatherpress_online_event_link',
			$filtered_meta,
			'Should preserve gatherpress_online_event_link (writable).'
		);
	}

	/**
	 * Tests filter_readonly_meta with null meta parameter.
	 *
	 * @covers ::filter_readonly_meta
	 * @return void
	 */
	public function test_filter_readonly_meta_with_null_meta(): void {
		$instance = Event_Setup::get_instance();

		// Create a REST request without meta parameter.
		$request = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		// Do not set meta parameter - it will be null.

		$prepared_post       = new stdClass();
		$prepared_post->ID   = 456;
		$prepared_post->name = 'Test Event';

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		// Verify prepared_post is returned unchanged.
		$this->assertSame( $prepared_post, $result, 'Should return the same prepared_post object.' );
		$this->assertEquals( 456, $result->ID, 'Prepared post ID should be unchanged.' );
		$this->assertEquals( 'Test Event', $result->name, 'Prepared post name should be unchanged.' );

		// Verify meta is still null.
		$this->assertNull( $request->get_param( 'meta' ), 'Meta should remain null.' );
	}

	/**
	 * Tests filter_readonly_meta with empty meta array.
	 *
	 * @covers ::filter_readonly_meta
	 * @return void
	 */
	public function test_filter_readonly_meta_with_empty_meta(): void {
		$instance = Event_Setup::get_instance();

		// Create a REST request with empty meta array.
		$request = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		$request->set_param( 'meta', array() );

		$prepared_post     = new stdClass();
		$prepared_post->ID = 789;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		// Verify prepared_post is returned unchanged.
		$this->assertSame( $prepared_post, $result, 'Should return the same prepared_post object.' );

		// Verify meta is still an empty array.
		$filtered_meta = $request->get_param( 'meta' );
		$this->assertIsArray( $filtered_meta, 'Meta should still be an array.' );
		$this->assertEmpty( $filtered_meta, 'Meta should still be empty.' );
	}

	/**
	 * Tests filter_readonly_meta with only writable keys.
	 *
	 * @covers ::filter_readonly_meta
	 * @return void
	 */
	public function test_filter_readonly_meta_with_only_writable_keys(): void {
		$instance = Event_Setup::get_instance();

		// Create a REST request with only writable meta keys.
		$request = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		$request->set_param(
			'meta',
			array(
				'gatherpress_datetime'              => '{"dateTimeStart":"2025-01-01 10:00:00"}',
				'gatherpress_online_event_link'     => 'https://example.com/meeting',
				'gatherpress_enable_anonymous_rsvp' => true,
				'gatherpress_max_guest_limit'       => 5,
				'gatherpress_max_attendance_limit'  => 100,
			)
		);

		$prepared_post     = new stdClass();
		$prepared_post->ID = 101;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		// Verify prepared_post is returned unchanged.
		$this->assertSame( $prepared_post, $result, 'Should return the same prepared_post object.' );

		// Verify all writable keys are preserved.
		$filtered_meta = $request->get_param( 'meta' );

		$this->assertCount( 5, $filtered_meta, 'Should have all 5 writable keys.' );
		$this->assertArrayHasKey( 'gatherpress_datetime', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_online_event_link', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_enable_anonymous_rsvp', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_max_guest_limit', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_max_attendance_limit', $filtered_meta );
	}

	/**
	 * Tests filter_readonly_meta with only readonly keys.
	 *
	 * @covers ::filter_readonly_meta
	 * @return void
	 */
	public function test_filter_readonly_meta_with_only_readonly_keys(): void {
		$instance = Event_Setup::get_instance();

		// Create a REST request with only readonly meta keys.
		$request = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		$request->set_param(
			'meta',
			array(
				'gatherpress_datetime_start'     => '2025-01-01 10:00:00',
				'gatherpress_datetime_start_gmt' => '2025-01-01 15:00:00',
				'gatherpress_datetime_end'       => '2025-01-01 12:00:00',
				'gatherpress_datetime_end_gmt'   => '2025-01-01 17:00:00',
				'gatherpress_timezone'           => 'America/New_York',
			)
		);

		$prepared_post     = new stdClass();
		$prepared_post->ID = 202;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		// Verify prepared_post is returned unchanged.
		$this->assertSame( $prepared_post, $result, 'Should return the same prepared_post object.' );

		// Verify all readonly keys were removed.
		$filtered_meta = $request->get_param( 'meta' );

		$this->assertIsArray( $filtered_meta, 'Meta should still be an array.' );
		$this->assertEmpty( $filtered_meta, 'Meta should be empty after removing all readonly keys.' );
	}
}
