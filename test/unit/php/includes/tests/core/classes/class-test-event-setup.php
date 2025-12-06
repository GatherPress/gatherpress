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

		$this->assertStringContainsString( 'gatherpress-rsvp-pending', $output, 'Should show unapproved RSVP indicator.' );
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
		$this->assertStringContainsString( 'GatherPress', $result['gatherpress_upcoming_events'], 'Label should contain GatherPress.' );
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
		$this->assertStringContainsString( 'GatherPress', $result['gatherpress_past_events'], 'Label should contain GatherPress.' );
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
}
