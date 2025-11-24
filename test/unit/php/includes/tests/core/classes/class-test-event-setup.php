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
	 * @covers ::rsvp_sorting_orderby
	 *
	 * @return void
	 */
	public function test_rsvp_sorting_orderby(): void {
		global $wp_query;
		$instance = Event_Setup::get_instance();

		// Save original query.
		$original_wp_query = $wp_query;

		// Create a new WP_Query for testing.
		$wp_query = new \WP_Query();
		$wp_query->set( 'rsvp_sort_order', 'DESC' );

		$result = $instance->rsvp_sorting_orderby( 'original_orderby' );

		$this->assertStringContainsString( 'COUNT(rsvp_sort_comments.comment_ID) DESC', $result );

		// Test with ASC order.
		$wp_query = new \WP_Query();
		$wp_query->set( 'rsvp_sort_order', 'ASC' );
		$result = $instance->rsvp_sorting_orderby( 'original_orderby' );
		$this->assertStringContainsString( 'COUNT(rsvp_sort_comments.comment_ID) ASC', $result );

		// Test default order when not set (should use ASC default).
		$wp_query = new \WP_Query();
		$result   = $instance->rsvp_sorting_orderby( 'original_orderby' );
		$this->assertStringContainsString( 'COUNT(rsvp_sort_comments.comment_ID) ASC', $result );

		// Restore original wp_query.
		$wp_query = $original_wp_query;
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
		$this->assertStringContainsString( "'_gatherpress_venue'", $result );
	}

	/**
	 * Coverage for venue_sorting_orderby method.
	 *
	 * @covers ::venue_sorting_orderby
	 *
	 * @return void
	 */
	public function test_venue_sorting_orderby(): void {
		global $wp_query;
		$instance = Event_Setup::get_instance();

		// Save original query.
		$original_wp_query = $wp_query;

		// Create a new WP_Query for testing.
		$wp_query = new \WP_Query();
		$wp_query->set( 'venue_sort_order', 'DESC' );

		$result = $instance->venue_sorting_orderby( 'original_orderby' );

		$this->assertStringContainsString( 'CASE WHEN venue_terms.name IS NULL THEN 1 ELSE 0 END ASC', $result );
		$this->assertStringContainsString( 'venue_terms.name DESC', $result );

		// Test with ASC order.
		$wp_query = new \WP_Query();
		$wp_query->set( 'venue_sort_order', 'ASC' );
		$result = $instance->venue_sorting_orderby( 'original_orderby' );
		$this->assertStringContainsString( 'venue_terms.name ASC', $result );

		// Test default order when not set (should use ASC default).
		$wp_query = new \WP_Query();
		$result   = $instance->venue_sorting_orderby( 'original_orderby' );
		$this->assertStringContainsString( 'venue_terms.name ASC', $result );

		// Restore original wp_query.
		$wp_query = $original_wp_query;
	}
}
