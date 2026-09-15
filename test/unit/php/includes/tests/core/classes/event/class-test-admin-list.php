<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Admin_List.
 *
 * @package GatherPress\Core\Event
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Event;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Admin_List;
use GatherPress\Core\Event\Recurrence\Context as Recurrence_Context;
use GatherPress\Core\Event\Recurrence\Meta as Recurrence_Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use stdClass;
use WP_Query;

/**
 * Class Test_Admin_List.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Admin_List
 */
class Test_Admin_List extends Base {

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Admin_List::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $instance, 'handle_rsvp_sorting' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'query_vars',
				'priority' => 10,
				'callback' => array( $instance, 'query_vars' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'disable_months_dropdown',
				'priority' => 10,
				'callback' => array( $instance, 'disable_months_dropdown' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'restrict_manage_posts',
				'priority' => 10,
				'callback' => array( $instance, 'render_date_filters' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'restrict_manage_posts',
				'priority' => 10,
				'callback' => array( $instance, 'render_taxonomy_filters' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_register_post_type_hooks' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for maybe_register_post_type_hooks method.
	 *
	 * @covers ::maybe_register_post_type_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_post_type_hooks(): void {
		$instance = Admin_List::get_instance();
		$test_pt  = 'test_event_admin';

		// Register a temporary post type with gatherpress-event-date support.
		register_post_type(
			$test_pt,
			array(
				'label'    => 'Test Events',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		// Call the method directly (normally fired via the registered_post_type action).
		$instance->maybe_register_post_type_hooks( $test_pt );

		// Verify per-post-type hooks were registered for the test post type.
		$this->assertNotFalse(
			has_filter( sprintf( 'manage_edit-%s_sortable_columns', $test_pt ), array( $instance, 'sortable_columns' ) ), // phpcs:ignore Generic.Files.LineLength.TooLong
			'Should register sortable_columns filter for event post type.'
		);
		$this->assertNotFalse(
			has_filter( sprintf( 'views_edit-%s', $test_pt ), array( $instance, 'views_edit' ) ),
			'Should register views_edit filter for event post type.'
		);
		$this->assertNotFalse(
			has_action( sprintf( 'manage_%s_posts_custom_column', $test_pt ), array( $instance, 'custom_columns' ) ),
			'Should register custom_columns action for event post type.'
		);
		$this->assertNotFalse(
			has_filter( sprintf( 'manage_%s_posts_columns', $test_pt ), array( $instance, 'set_custom_columns' ) ),
			'Should register set_custom_columns filter for event post type.'
		);
		$this->assertNotFalse(
			has_filter( sprintf( 'manage_%s_posts_columns', $test_pt ), array( $instance, 'remove_comments_column' ) ),
			'Should register remove_comments_column filter for event post type.'
		);

		// Clean up the temporary post type.
		unregister_post_type( $test_pt );
	}

	/**
	 * Bails when the post type does not declare gatherpress-event-date support.
	 *
	 * @covers ::maybe_register_post_type_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_post_type_hooks_skips_unsupported_post_type(): void {
		$instance = Admin_List::get_instance();

		// Standard 'post' does not declare gatherpress-event-date support.
		$instance->maybe_register_post_type_hooks( 'post' );

		$this->assertFalse(
			has_filter( 'manage_edit-post_sortable_columns', array( $instance, 'sortable_columns' ) ),
			'Should not register event-admin hooks for post types without gatherpress-event-date support.'
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
		$instance = Admin_List::get_instance();
		$default  = array( 'unit' => 'test' );
		$expects  = array(
			'unit'     => 'test',
			'datetime' => 'datetime',
			'rsvps'    => 'rsvps',
		);

		$this->assertSame(
			$expects,
			$instance->sortable_columns( $default ),
			'Failed to assert correct sortable columns.'
		);
	}

	/**
	 * Coverage for views_edit method when no screen is available.
	 *
	 * Exercises the ! $screen early return, which can occur in multisite contexts
	 * where get_current_screen() returns null before any screen is set.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_no_screen(): void {
		$instance = Admin_List::get_instance();

		// Ensure no screen is set so get_current_screen() returns null.
		unset( $GLOBALS['current_screen'] );

		$view_links = array( 'all' => '<a href="#">All</a>' );
		$result     = $instance->views_edit( $view_links );

		$this->assertSame( $view_links, $result, 'Should return view_links unchanged when no screen.' );
	}

	/**
	 * Coverage for views_edit method with no events.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_adds_links(): void {
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		$view_links = array(
			'all'     => '<a href="#">All</a>',
			'publish' => '<a href="#">Published</a>',
			'draft'   => '<a href="#">Draft</a>',
		);

		$result = $instance->views_edit( $view_links );

		set_current_screen( 'front' );

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

		// Verify sort order: upcoming should be asc, past should be desc.
		$this->assertStringContainsString(
			'order=asc',
			$result['upcoming'],
			'Upcoming link should sort ascending.'
		);
		$this->assertStringContainsString(
			'order=desc',
			$result['past'],
			'Past link should sort descending.'
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
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

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

		set_current_screen( 'front' );
	}

	/**
	 * Coverage for views_edit method with active upcoming view.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_active_upcoming(): void {
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

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
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for views_edit method with active past view.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_active_past(): void {
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

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
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for views_edit method with no active event query filter.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_no_active_filter(): void {
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

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

		set_current_screen( 'front' );
	}

	/**
	 * Coverage for views_edit adding current class to "All" when WordPress omits it.
	 *
	 * When extra $_GET params are present, WordPress core's
	 * `is_base_request()` returns false and the "All" view link loses its
	 * `current` class — `views_edit()` defensively re-adds it.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_all_gets_current_when_missing(): void {
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		// Ensure no event query filter is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );

		// Simulate WordPress not adding current class because extra $_GET
		// params break is_base_request().
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

		set_current_screen( 'front' );
	}

	/**
	 * `views_edit()` must not stamp "current" on "All" when WordPress
	 * core has already marked another built-in view (Published, Draft,
	 * Trash) as current. Without the guard, both "All" and the actual
	 * status filter would render bold simultaneously.
	 *
	 * @covers ::views_edit
	 *
	 * @return void
	 */
	public function test_views_edit_does_not_mark_all_when_status_filter_is_current(): void {
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['gatherpress_event_query'] );

		// Simulate WordPress's view-link output when the user is on the
		// Published filter: "All" is plain, "publish" carries `current`.
		$view_links = array(
			'all'     => '<a href="#all">All</a>',
			'publish' => '<a href="#publish" class="current" aria-current="page">Published</a>',
		);

		$result = $instance->views_edit( $view_links );

		$this->assertStringNotContainsString(
			'class="current"',
			$result['all'],
			'All link must stay un-marked when another status filter is current.'
		);
		$this->assertStringContainsString(
			'class="current"',
			$result['publish'],
			'Published link should retain its current class.'
		);

		set_current_screen( 'front' );
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
		$instance = Admin_List::get_instance();

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
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

		set_current_screen( 'edit-' . Event::POST_TYPE );

		$view_links = array(
			'all' => '<a href="#">All</a>',
		);

		$result = $instance->views_edit( $view_links );

		set_current_screen( 'front' );

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
	 * Coverage for get_event_counts method with no events.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_no_events(): void {
		$instance = Admin_List::get_instance();

		// Reset cached counts and invoke the protected method.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

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
		$instance = Admin_List::get_instance();

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
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

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
		$instance = Admin_List::get_instance();

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
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

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
		$instance = Admin_List::get_instance();

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
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 2, $counts['upcoming'], 'Should have 2 upcoming events (draft included).' );
		$this->assertSame( 1, $counts['past'], 'Should have 1 past event.' );
	}

	/**
	 * Coverage for get_event_counts method with a currently running event.
	 *
	 * Running events count as upcoming (datetime_end_gmt is still in the
	 * future) and not as past — the buckets pivot on datetime_end_gmt so
	 * a running event lives only in upcoming until it actually ends.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_running_event(): void {
		$instance = Admin_List::get_instance();

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
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		// Running events count only as upcoming — buckets pivot on datetime_end_gmt.
		$this->assertSame( 1, $counts['upcoming'], 'Running event should count as upcoming.' );
		$this->assertSame( 0, $counts['past'], 'Running event should not count as past.' );
	}

	/**
	 * Events without a date set don't appear in the upcoming/past view counts.
	 *
	 * Events with no row in the `gatherpress_events` table show up only
	 * under the All view — they're neither upcoming nor past, since
	 * there's no datetime to compare against. The counts mirror the
	 * filter in `Query::adjust_admin_event_sorting()`, which uses an
	 * INNER JOIN that drops these rows. Was previously counted as
	 * upcoming via `OR post_id IS NULL`, removed when the admin
	 * defaulted to the All view (see #1610).
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_for_an_event_created_without_dates(): void {
		$instance = Admin_List::get_instance();

		// Reset cached counts.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

		// An event created without dates is seeded with the editor's default,
		// which is tomorrow, so it counts as upcoming rather than falling out
		// of both buckets the way a datetime-less event used to (#2054). The
		// seed runs at shutdown so late-arriving meta wins over it (#2116).
		$this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		Event_Setup::get_instance()->resolve_pending_datetimes();

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 1, $counts['upcoming'], 'Event created without dates should count as upcoming.' );
		$this->assertSame( 0, $counts['past'], 'Event created without dates should not count as past.' );
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
		$instance = Admin_List::get_instance();

		// Reset cached counts.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

		// First call should query the database and cache.
		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( 0, $counts['upcoming'], 'Should have 0 upcoming events.' );

		// Verify caching: a second call should return the same result without re-querying.
		$counts_second = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( $counts['upcoming'], $counts_second['upcoming'], 'Second call should return the cached value.' ); // phpcs:ignore Generic.Files.LineLength.TooLong

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
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );
		$counts_fresh = Utility::invoke_hidden_method( $instance, 'get_event_counts' );
		$this->assertSame( 1, $counts_fresh['upcoming'], 'Fresh call should return 1 after cache reset.' );
	}

	/**
	 * Coverage for query_vars method.
	 *
	 * @covers ::query_vars
	 *
	 * @return void
	 */
	public function test_query_vars(): void {
		$instance = Admin_List::get_instance();

		$result = $instance->query_vars( array( 'existing_var' ) );

		$this->assertContains(
			'gatherpress_event_query',
			$result,
			'Should add gatherpress_event_query to query vars.'
		);
		$this->assertContains(
			'gatherpress_event_date',
			$result,
			'Should add gatherpress_event_date to query vars.'
		);
		$this->assertContains(
			'existing_var',
			$result,
			'Should preserve existing query vars.'
		);
		$this->assertCount( 3, $result, 'Should have exactly 3 query vars.' );
	}

	/**
	 * Coverage for query_vars method with empty input.
	 *
	 * @covers ::query_vars
	 *
	 * @return void
	 */
	public function test_query_vars_empty_input(): void {
		$instance = Admin_List::get_instance();

		$result = $instance->query_vars( array() );

		$this->assertContains(
			'gatherpress_event_query',
			$result,
			'Should add gatherpress_event_query even with empty input.'
		);
		$this->assertCount( 2, $result, 'Should have exactly 2 query vars.' );
	}

	/**
	 * Coverage for handle_rsvp_sorting method.
	 *
	 * @covers ::handle_rsvp_sorting
	 *
	 * @return void
	 */
	public function test_handle_rsvp_sorting(): void {
		$instance = Admin_List::get_instance();

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
		$instance = Admin_List::get_instance();

		$original_join = "LEFT JOIN {$wpdb->posts} AS posts ON posts.ID = {$wpdb->posts}.ID";
		$result        = $instance->rsvp_sorting_join_paged( $original_join );

		// Should contain the original join plus the RSVP join.
		$this->assertStringContainsString( $original_join, $result );
		$this->assertStringContainsString( 'LEFT JOIN', $result );
		$this->assertStringContainsString( $wpdb->comments, $result );
		$this->assertStringContainsString( 'rsvp_sort_comments', $result );
		$this->assertStringContainsString( 'comment_type', $result );
		$this->assertStringContainsString( "comment_approved = '1'", $result );
	}

	/**
	 * Coverage for sorting_groupby_post_id method.
	 *
	 * @covers ::sorting_groupby_post_id
	 *
	 * @return void
	 */
	public function test_sorting_groupby_post_id(): void {
		global $wpdb;
		$instance = Admin_List::get_instance();

		$result = $instance->sorting_groupby_post_id( '' );
		$this->assertEquals( "`{$wpdb->posts}`.`ID`", $result );

		// Test with existing groupby - should keep the existing value.
		$existing_groupby = 'existing_group';
		$result           = $instance->sorting_groupby_post_id( $existing_groupby );
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
		$instance = Admin_List::get_instance();

		$result = $instance->rsvp_sorting_orderby( 'original_orderby' );

		// Should contain the expected COUNT structure.
		$this->assertStringContainsString( 'COUNT(rsvp_sort_comments.comment_ID)', $result );

		// Should contain either ASC or DESC (defaults to ASC).
		$this->assertTrue(
			str_contains( $result, 'ASC' ) || str_contains( $result, 'DESC' ),
			'ORDER BY clause should contain ASC or DESC'
		);

		// Verify the method returns a string (basic type check).
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Tests handle_rsvp_sorting with RSVP orderby.
	 *
	 * Covers: RSVP sorting logic.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @covers ::handle_column_sorting
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

		$instance = Admin_List::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Verify that sorting order was set.
		$this->assertEquals( 'DESC', $query->get( 'rsvp_sort_order' ), 'Should set DESC order' );

		// Verify filters were added.
		$this->assertNotFalse(
			has_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) ),
			'Should add posts_join_paged filter'
		);
		$this->assertNotFalse(
			has_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) ),
			'Should add posts_groupby filter'
		);
		$this->assertNotFalse(
			has_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) ),
			'Should add posts_orderby filter'
		);

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_rsvp_sorting with invalid order.
	 *
	 * Covers: Order validation.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @covers ::handle_column_sorting
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

		$instance = Admin_List::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Should default to ASC for invalid order.
		$this->assertEquals( 'ASC', $query->get( 'rsvp_sort_order' ), 'Should default to ASC for invalid order' );

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_rsvp_sorting early return when post_type is an array.
	 *
	 * Exercises the is_array($post_type) guard in handle_column_sorting(),
	 * which prevents sorting logic from running on multi-post-type queries.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @covers ::handle_column_sorting
	 * @return void
	 */
	public function test_rsvp_sorting_early_return_array_post_type(): void {
		global $wp_the_query;

		// Create a WP_Query with multiple post types (array).
		$query = new WP_Query(
			array(
				'post_type' => array( Event::POST_TYPE, 'post' ),
				'orderby'   => 'rsvps',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Necessary for testing query modifications.
		$wp_the_query = $query;

		set_current_screen( 'edit-gatherpress_event' );

		$instance = Admin_List::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Should return early — rsvp_sort_order must not be set.
		$this->assertEmpty( $query->get( 'rsvp_sort_order' ), 'Should not set rsvp_sort_order for array post_type.' );

		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_rsvp_sorting early return when orderby is not 'rsvps'.
	 *
	 * @covers ::handle_rsvp_sorting
	 * @covers ::handle_column_sorting
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

		$instance = Admin_List::get_instance();
		$instance->handle_rsvp_sorting( $query );

		// Should not set rsvp_sort_order since orderby is not 'rsvps'.
		$this->assertEmpty( $query->get( 'rsvp_sort_order' ), 'Should not set rsvp_sort_order for non-RSVP orderby' );

		set_current_screen( 'front' );
	}


	/**
	 * Coverage for custom_columns method with datetime column.
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_datetime(): void {
		$instance = Admin_List::get_instance();
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
	 * Coverage for custom_columns method with rsvps column (no RSVPs).
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_rsvps_no_rsvps(): void {
		$instance = Admin_List::get_instance();
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
		$instance = Admin_List::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create approved RSVP.
		$this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => 1,
			)
		);

		ob_start();
		$instance->custom_columns( 'rsvps', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'gatherpress-rsvp-approved', $output, 'Should show approved RSVP count.' );
		$this->assertStringContainsString( '>1<', $output, 'Should show count of 1.' );
		$this->assertStringContainsString(
			'<span class="screen-reader-text"> approved RSVP</span>',
			$output,
			'Should append the visually hidden accessible-name suffix.'
		);
	}

	/**
	 * Coverage for custom_columns method with rsvps column (with unapproved RSVPs).
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_rsvps_with_unapproved(): void {
		$instance = Admin_List::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create approved and unapproved RSVPs.
		$this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => 1,
			)
		);
		$this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
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
		$this->assertStringContainsString(
			'<span class="screen-reader-text"> unapproved RSVP</span>',
			$output,
			'Should append the visually hidden accessible-name suffix.'
		);
	}

	/**
	 * Coverage for set_custom_columns method.
	 *
	 * @covers ::set_custom_columns
	 *
	 * @return void
	 */
	public function test_set_custom_columns(): void {
		$instance = Admin_List::get_instance();

		// Pin the screen to the event edit screen — production calls go
		// through the `manage_<post_type>_posts_columns` filter, which only
		// fires when that screen is active. Setting it here also exercises
		// the screen-driven `$screen->post_type` branch in
		// `set_custom_columns()`'s venue-taxonomy resolution.
		set_current_screen( 'edit-' . Event::POST_TYPE );

		// Mirror what WP core hands the filter on the events list screen:
		// title + auto-injected taxonomy columns + author + date. Venue
		// taxonomy goes first so the test asserts the venue-first order
		// the production method enforces.
		$default_columns = array(
			'cb'                          => '<input type="checkbox" />',
			'title'                       => 'Title',
			'taxonomy-_gatherpress_venue' => 'Venues',
			'taxonomy-gatherpress_topic'  => 'Topics',
			'author'                      => 'Author',
			'date'                        => 'Date',
		);

		$result = $instance->set_custom_columns( $default_columns );

		set_current_screen( 'front' );

		// Author column is dropped.
		$this->assertArrayNotHasKey( 'author', $result, 'Author column should be removed.' );

		// Custom columns + the auto-injected taxonomy columns survive.
		$this->assertArrayHasKey( 'datetime', $result );
		$this->assertArrayHasKey( 'rsvps', $result );
		$this->assertArrayHasKey( 'taxonomy-_gatherpress_venue', $result );
		$this->assertArrayHasKey( 'taxonomy-gatherpress_topic', $result );

		// Order: cb, title, datetime, rsvps, venue taxonomy, other
		// taxonomies, then trailing built-ins.
		$this->assertSame(
			array(
				'cb',
				'title',
				'datetime',
				'rsvps',
				'taxonomy-_gatherpress_venue',
				'taxonomy-gatherpress_topic',
				'date',
			),
			array_keys( $result ),
			'Sortable custom columns must precede taxonomy columns; venue taxonomy must come first.'
		);
	}

	/**
	 * Coverage for `set_custom_columns()`'s no-screen fallback.
	 *
	 * When `get_current_screen()` returns null (or its `post_type` is
	 * empty) the venue taxonomy resolves through `Event::POST_TYPE`
	 * rather than `$screen->post_type`. Exercises the falsy ternary leg
	 * left uncovered when a screen is set.
	 *
	 * @covers ::set_custom_columns
	 *
	 * @return void
	 */
	public function test_set_custom_columns_falls_back_to_event_post_type_without_screen(): void {
		$instance = Admin_List::get_instance();

		// Ensure no screen is set so the production code falls through
		// to the `Event::POST_TYPE` default.
		unset( $GLOBALS['current_screen'] );

		$default_columns = array(
			'cb'                          => '<input type="checkbox" />',
			'title'                       => 'Title',
			'taxonomy-_gatherpress_venue' => 'Venues',
			'date'                        => 'Date',
		);

		$result = $instance->set_custom_columns( $default_columns );

		$this->assertSame(
			array(
				'cb',
				'title',
				'datetime',
				'rsvps',
				'taxonomy-_gatherpress_venue',
				'date',
			),
			array_keys( $result ),
			'Without a screen, the venue taxonomy still routes through Event::POST_TYPE and lands in the same slot.'
		);
	}

	/**
	 * The `gatherpress_event_datetime_label` filter relabels the
	 * datetime column header without changing the column key, and receives
	 * the screen's post type so consumers can vary the label per post type.
	 *
	 * @covers ::set_custom_columns
	 *
	 * @return void
	 */
	public function test_set_custom_columns_filters_datetime_label(): void {
		$instance        = Admin_List::get_instance();
		$captured_pt_arg = null;

		set_current_screen( 'edit-' . Event::POST_TYPE );

		$relabel = static function ( string $label, string $post_type ) use ( &$captured_pt_arg ): string {
			$captured_pt_arg = $post_type;
			return 'Premiere date';
		};

		add_filter( 'gatherpress_event_datetime_label', $relabel, 10, 2 );

		$result = $instance->set_custom_columns(
			array(
				'cb'    => '<input type="checkbox" />',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		remove_filter( 'gatherpress_event_datetime_label', $relabel, 10 );
		set_current_screen( 'front' );

		$this->assertSame(
			'Premiere date',
			$result['datetime'],
			'Datetime column header must reflect the filtered label.'
		);
		$this->assertSame(
			Event::POST_TYPE,
			$captured_pt_arg,
			'Filter must receive the screen post type as its second argument.'
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
		$instance = Admin_List::get_instance();

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
	 * Reproduces gatherpress#1591: a post type that declares
	 * `gatherpress-event-date` support but not `gatherpress-rsvp` must not
	 * advertise the RSVPs sortable column.
	 *
	 * @covers ::sortable_columns
	 *
	 * @return void
	 */
	public function test_sortable_columns_omits_rsvps_for_event_date_only_post_type(): void {
		$instance = Admin_List::get_instance();
		$test_pt  = 'production';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Productions',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		set_current_screen( 'edit-' . $test_pt );

		$result = $instance->sortable_columns( array( 'unit' => 'test' ) );

		set_current_screen( 'front' );
		unregister_post_type( $test_pt );

		$this->assertSame(
			array(
				'unit'     => 'test',
				'datetime' => 'datetime',
			),
			$result,
			'Event-date-only post types should keep datetime sortable but not rsvps.'
		);
	}

	/**
	 * Reproduces gatherpress#1591: the `set_custom_columns` filter must not
	 * inject the RSVPs column header on post types that lack
	 * `gatherpress-rsvp` support.
	 *
	 * @covers ::set_custom_columns
	 *
	 * @return void
	 */
	public function test_set_custom_columns_omits_rsvps_for_event_date_only_post_type(): void {
		$instance = Admin_List::get_instance();
		$test_pt  = 'production';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Productions',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		set_current_screen( 'edit-' . $test_pt );

		$default_columns = array(
			'cb'    => '<input type="checkbox" />',
			'title' => 'Title',
			'date'  => 'Date',
		);

		$result = $instance->set_custom_columns( $default_columns );

		set_current_screen( 'front' );
		unregister_post_type( $test_pt );

		$this->assertArrayHasKey(
			'datetime',
			$result,
			'Event-date-only post types still get the datetime column.'
		);
		$this->assertArrayNotHasKey(
			'rsvps',
			$result,
			'Event-date-only post types should not get the RSVPs column.'
		);
	}

	/**
	 * Reproduces gatherpress#1591: `remove_comments_column` strips the comments
	 * column to avoid confusion with RSVP comment counts. Post types without
	 * `gatherpress-rsvp` support have no such conflict and must keep their
	 * standard comments column.
	 *
	 * @covers ::remove_comments_column
	 *
	 * @return void
	 */
	public function test_remove_comments_column_keeps_column_for_event_date_only_post_type(): void {
		$instance = Admin_List::get_instance();
		$test_pt  = 'production';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Productions',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		set_current_screen( 'edit-' . $test_pt );

		$columns_with_comments = array(
			'cb'       => '<input type="checkbox" />',
			'title'    => 'Title',
			'comments' => 'Comments',
			'date'     => 'Date',
		);

		$result = $instance->remove_comments_column( $columns_with_comments );

		set_current_screen( 'front' );
		unregister_post_type( $test_pt );

		$this->assertSame(
			$columns_with_comments,
			$result,
			'Event-date-only post types must retain their standard comments column.'
		);
	}

	/**
	 * Reproduces gatherpress#1591: `handle_rsvp_sorting` must short-circuit
	 * before registering join/groupby/orderby filters when the queried post
	 * type lacks `gatherpress-rsvp` support, even if a malicious or stale
	 * `?orderby=rsvps` query var is present.
	 *
	 * @covers ::handle_rsvp_sorting
	 *
	 * @return void
	 */
	public function test_handle_rsvp_sorting_skips_event_date_only_post_type(): void {
		global $wp_the_query;

		$instance = Admin_List::get_instance();
		$test_pt  = 'production';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Productions',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		// Make sure no leftover filters are present from a prior test.
		remove_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) );

		// Real WP_Query (not a mock) so `$query->get( 'post_type' )` returns the
		// string we registered — a createMock() with willReturnMap() silently
		// returns null when the method's optional `$default` arg defaults aren't
		// matched by the map, which lets the sorting handler bypass the new
		// rsvp-support guard for the wrong reason.
		$query = new WP_Query(
			array(
				'post_type' => $test_pt,
				'orderby'   => 'rsvps',
			)
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required to satisfy is_main_query() inside handle_column_sorting.
		$wp_the_query = $query;

		set_current_screen( 'edit-' . $test_pt );

		$instance->handle_rsvp_sorting( $query );

		set_current_screen( 'front' );
		unregister_post_type( $test_pt );

		$this->assertEmpty(
			$query->get( 'rsvp_sort_order' ),
			'rsvp_sort_order must not be set for event-date-only post types.'
		);
		$this->assertFalse(
			has_filter( 'posts_join_paged', array( $instance, 'rsvp_sorting_join_paged' ) ),
			'No RSVP join filter should be added for event-date-only post types.'
		);
		$this->assertFalse(
			has_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) ),
			'No RSVP groupby filter should be added for event-date-only post types.'
		);
		$this->assertFalse(
			has_filter( 'posts_orderby', array( $instance, 'rsvp_sorting_orderby' ) ),
			'No RSVP orderby filter should be added for event-date-only post types.'
		);
	}

	/**
	 * Build the recurring fixture the date-column tests share.
	 *
	 * The anchor is deliberately **in the past** and the rule is deliberately
	 * long enough to still be running. That separation is the whole point of
	 * the fixture: the series anchor and the occurrence the column is supposed
	 * to show are provably different dates, so a column that renders the anchor
	 * and a column that renders the next occurrence cannot produce the same
	 * string. A fixture anchored on its own next occurrence would pass against
	 * both the fixed code and the broken code it replaced.
	 *
	 * The five-hour offset keeps the chosen occurrence off "right now", so the
	 * assertion never races the clock across a `LIMIT 1` boundary.
	 *
	 * Everything is UTC so an occurrence's local columns and its GMT columns
	 * read identically and a formatting assertion has one timezone to reason
	 * about.
	 *
	 * @since 0.36.0
	 *
	 * @param int $count Number of daily occurrences to project.
	 *
	 * @return array<string, mixed> The post ID, the anchor start and the occurrence starts.
	 */
	protected function create_daily_series_fixture( int $count ): array {
		gatherpress_reset_custom_tables();

		$now    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$anchor = $now->modify( '-40 days +5 hours' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $anchor->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $anchor->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );

		add_post_meta(
			$post_id,
			Recurrence_Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => $count,
				)
			)
		);

		Recurrence_Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return array(
			'post_id' => (int) $post_id,
			'anchor'  => $anchor,
			// Occurrence 40 of a daily series anchored forty days ago starts
			// five hours from now, the first one that has not finished.
			'next'    => $anchor->modify( '+40 days' ),
			'last'    => $anchor->modify( sprintf( '+%d days', $count - 1 ) ),
		);
	}

	/**
	 * Format a datetime the way the event date column does.
	 *
	 * Reads the same two settings `Event::get_display_datetime()` reads, so the
	 * expectation tracks a site that has changed its date format rather than
	 * hard-coding one.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $datetime Datetime to format, in UTC.
	 *
	 * @return string The formatted date and time.
	 */
	protected function format_column_datetime( DateTimeImmutable $datetime ): string {
		$settings = Settings::get_instance();

		return $datetime->format(
			sprintf( '%s %s', $settings->get( 'date_format' ), $settings->get( 'time_format' ) )
		);
	}

	/**
	 * Create and project a daily series anchored at the given instant.
	 *
	 * The same shape `create_daily_series_fixture()` builds, without clearing
	 * the occurrence table, so a test can stand two series side by side.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $anchor Anchor start, in UTC.
	 * @param int               $count  Occurrences to project.
	 *
	 * @return int The projected post ID.
	 */
	protected function create_series_anchored_at( DateTimeImmutable $anchor, int $count ): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $anchor->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $anchor->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );

		add_post_meta(
			$post_id,
			Recurrence_Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => $count,
				)
			)
		);

		Recurrence_Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return (int) $post_id;
	}

	/**
	 * Create a plain, non-recurring event with the given datetime bounds.
	 *
	 * @since 0.36.0
	 *
	 * @param string $start Relative start, e.g. `+1 day`.
	 * @param string $end   Relative end, e.g. `+1 day +2 hours`.
	 *
	 * @return int The created post ID.
	 */
	protected function create_plain_event( string $start, string $end ): int {
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( $start ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( $end ) ),
				'timezone'       => 'UTC',
			)
		);

		return (int) $post_id;
	}

	/**
	 * Run one admin Upcoming/Past view query through the production wiring.
	 *
	 * @since 0.36.0
	 *
	 * @param string $bucket Either `upcoming` or `past`.
	 *
	 * @return int[] The result IDs.
	 */
	protected function run_admin_view_query( string $bucket ): array {
		set_current_screen( 'edit-' . Event::POST_TYPE );

		$query = new WP_Query(
			array(
				'post_type'               => Event::POST_TYPE,
				'post_status'             => 'publish',
				'gatherpress_event_query' => $bucket,
				'orderby'                 => 'datetime',
				'order'                   => 'upcoming' === $bucket ? 'ASC' : 'DESC',
				'posts_per_page'          => -1,
				'fields'                  => 'ids',
				'no_found_rows'           => true,
			)
		);

		set_current_screen( 'front' );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * The view counts bucket a running series by its chosen occurrence.
	 *
	 * The date column and the sort read the occurrence the series is next
	 * doing, so counts still reading the series anchor would advertise an
	 * Upcoming view that omits an actively recurring series. The fixture's
	 * series anchor elapsed forty days ago while its next occurrence is five
	 * hours out, so anchor counting and occurrence counting provably disagree,
	 * and the two bracketing one-off events prove the plain buckets are
	 * untouched.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_buckets_a_running_series_by_its_next_occurrence(): void {
		$instance = Admin_List::get_instance();
		$this->create_daily_series_fixture( 60 );

		$this->create_plain_event( '+1 day', '+1 day +2 hours' );
		$this->create_plain_event( '-2 days', '-2 days +2 hours' );

		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame(
			2,
			$counts['upcoming'],
			'A running series must count toward Upcoming alongside the plain future event.'
		);
		$this->assertSame(
			1,
			$counts['past'],
			'Only the plain past event may count toward Past.'
		);
	}

	/**
	 * The view counts match the rows the corresponding view query returns.
	 *
	 * The counts and the list are produced by two different pieces of SQL, and
	 * a reader treats the count as a promise about the list. Both are driven
	 * here for the same fixture, so a divergence between the shared derived
	 * occurrence relation and either consumer fails this test by number.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_match_the_rows_each_view_returns(): void {
		$instance = Admin_List::get_instance();
		$fixture  = $this->create_daily_series_fixture( 60 );

		$this->create_plain_event( '+1 day', '+1 day +2 hours' );
		$this->create_plain_event( '-2 days', '-2 days +2 hours' );

		$upcoming_rows = $this->run_admin_view_query( 'upcoming' );
		$past_rows     = $this->run_admin_view_query( 'past' );

		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertContains(
			$fixture['post_id'],
			$upcoming_rows,
			'A running series belongs to the Upcoming view rows.'
		);
		$this->assertNotContains(
			$fixture['post_id'],
			$past_rows,
			'A running series must not appear in the Past view rows.'
		);
		$this->assertSame(
			count( $upcoming_rows ),
			$counts['upcoming'],
			'The Upcoming count must equal the number of rows the Upcoming view returns.'
		);
		$this->assertSame(
			count( $past_rows ),
			$counts['past'],
			'The Past count must equal the number of rows the Past view returns.'
		);
	}

	/**
	 * The date a row displays agrees with the view that listed it.
	 *
	 * This is the coherence B3 was filed for: a row whose displayed date
	 * contradicts the filter that selected it is not a shippable state. The
	 * running series must be listed under Upcoming, and the very date its
	 * column renders must be the upcoming occurrence that put it there.
	 *
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_row_displayed_date_agrees_with_the_view_that_lists_it(): void {
		$fixture = $this->create_daily_series_fixture( 60 );

		$this->assertContains(
			$fixture['post_id'],
			$this->run_admin_view_query( 'upcoming' ),
			'The running series must be listed by the Upcoming view.'
		);

		ob_start();
		Admin_List::get_instance()->custom_columns( 'datetime', $fixture['post_id'] );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			$this->format_column_datetime( $fixture['next'] ),
			$output,
			'The listed row must display the upcoming occurrence that placed it in the view.'
		);
	}

	/**
	 * A running series dates its row from its next occurrence, not its anchor.
	 *
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_custom_columns_datetime_shows_next_occurrence_for_running_series(): void {
		$fixture = $this->create_daily_series_fixture( 60 );

		ob_start();
		Admin_List::get_instance()->custom_columns( 'datetime', $fixture['post_id'] );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			$this->format_column_datetime( $fixture['next'] ),
			$output,
			'Failed to assert that the column shows the next upcoming occurrence.'
		);
		$this->assertStringNotContainsString(
			$this->format_column_datetime( $fixture['anchor'] ),
			$output,
			'Failed to assert that the column no longer shows the series anchor.'
		);
		$this->assertStringContainsString(
			'gatherpress-recurring-badge',
			$output,
			'Failed to assert that a recurring series is marked as recurring.'
		);
	}

	/**
	 * A series whose occurrences have all elapsed dates its row from the last one.
	 *
	 * The anchor is the *first* occurrence, so showing it for a finished series
	 * would report a date the series stopped using thirty-six days before it
	 * ended. The most recent occurrence is what a reader means by "when was
	 * this".
	 *
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_custom_columns_datetime_shows_last_occurrence_for_elapsed_series(): void {
		$fixture = $this->create_daily_series_fixture( 5 );

		ob_start();
		Admin_List::get_instance()->custom_columns( 'datetime', $fixture['post_id'] );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			$this->format_column_datetime( $fixture['last'] ),
			$output,
			'Failed to assert that a fully elapsed series shows its most recent occurrence.'
		);
		$this->assertStringNotContainsString(
			$this->format_column_datetime( $fixture['anchor'] ),
			$output,
			'Failed to assert that a fully elapsed series does not show its anchor.'
		);
		$this->assertStringContainsString(
			'gatherpress-recurring-badge',
			$output,
			'Failed to assert that an elapsed series is still marked as recurring.'
		);
	}

	/**
	 * A series with a rule but no scheduled rows keeps its marker.
	 *
	 * Canceling every occurrence leaves the series with nothing to date the
	 * row from, so the anchor is the only date available. It is still a
	 * recurring event, and dropping the marker would tell the reader it is a
	 * one-off.
	 *
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_custom_columns_datetime_marks_series_with_no_scheduled_occurrences(): void {
		$fixture     = $this->create_daily_series_fixture( 60 );
		$occurrences = Occurrences::get_instance();

		foreach ( $occurrences->select_for_series( array( $fixture['post_id'] ) ) as $occurrence ) {
			$occurrences->set_status(
				$fixture['post_id'],
				(string) $occurrence['recurrence_id'],
				Occurrences::STATUS_CANCELED
			);
		}

		ob_start();
		Admin_List::get_instance()->custom_columns( 'datetime', $fixture['post_id'] );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			$this->format_column_datetime( $fixture['anchor'] ),
			$output,
			'Failed to assert that a fully canceled series falls back to its anchor.'
		);
		$this->assertStringContainsString(
			'gatherpress-recurring-badge',
			$output,
			'Failed to assert that a fully canceled series is still marked as recurring.'
		);
	}

	/**
	 * A non-recurring event is untouched by any of the above.
	 *
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_custom_columns_datetime_leaves_non_recurring_event_alone(): void {
		gatherpress_reset_custom_tables();

		$start   = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( '+3 days' );
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $start->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );

		ob_start();
		Admin_List::get_instance()->custom_columns( 'datetime', (int) $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			$this->format_column_datetime( $start ),
			$output,
			'Failed to assert that a non-recurring event still shows its own datetime.'
		);
		$this->assertStringNotContainsString(
			'gatherpress-recurring-badge',
			$output,
			'Failed to assert that a non-recurring event is not marked as recurring.'
		);
	}

	/**
	 * An ordinary event's date column issues no occurrence-table queries.
	 *
	 * On a site with one recurring series, every other row of the admin list
	 * is an ordinary event whose occurrence probes cannot match anything: a
	 * twenty-row page would pay up to forty uncached `LIMIT 1` queries for
	 * nothing. The rule check is a post-meta-cache read, so the guard costs
	 * no query of its own.
	 *
	 * The fixture deliberately satisfies every *other* guard on the path:
	 * the recurring series flips the site-level flag on, the table exists,
	 * and the resolved post ID list is non-empty, so a zero count can only
	 * come from the per-post rule check.
	 *
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_custom_columns_datetime_queries_no_occurrences_for_ordinary_event(): void {
		global $wpdb;

		// A real recurring series elsewhere on the site turns the site flag on.
		$this->create_daily_series_fixture( 5 );

		$this->assertTrue(
			Recurrence_Query::site_has_recurring_events(),
			'Fixture failure: the site-level recurring flag must be on for this guard test to mean anything.'
		);

		$start   = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( '+3 days' );
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $start->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );

		// Prime the meta cache the way the admin list's main query does, so
		// the counted window reflects a real page render rather than a cold
		// cache paying a meta query the list would not.
		get_post_meta( $post_id );

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$seen  = 0;

		$counter = static function ( string $query ) use ( &$seen, $table ): string {
			if ( str_contains( $query, $table ) ) {
				++$seen;
			}

			return $query;
		};

		add_filter( 'query', $counter );

		ob_start();
		Admin_List::get_instance()->custom_columns( 'datetime', (int) $post_id );
		$output = ob_get_clean();

		remove_filter( 'query', $counter );

		$this->assertSame(
			0,
			$seen,
			'An ordinary event with no recurrence rule must not probe the occurrence table at all.'
		);
		$this->assertStringContainsString(
			$this->format_column_datetime( $start ),
			$output,
			'The guard must not change what the column renders for an ordinary event.'
		);
	}

	/**
	 * A throw during rendering must not leak this row's occurrence context.
	 *
	 * The column enters occurrence context before formatting and restores
	 * the previous context after. `Event` initialization and
	 * `get_display_datetime()` are both documented to throw, and a plugin's
	 * `gatherpress_date_format` filter can throw too; if an outer list-table
	 * extension catches the exception and continues, the next render would
	 * inherit this row's context and read the wrong occurrence's values.
	 *
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_render_datetime_column_restores_context_when_rendering_throws(): void {
		$fixture = $this->create_daily_series_fixture( 5 );
		$context = Recurrence_Context::get_instance();

		// A context some other surface established and expects to keep. The
		// first projected row differs from the display occurrence the column
		// would enter (the next unfinished one), so a leak is detectable.
		$standing = Occurrences::get_instance()->select_for_series(
			array( $fixture['post_id'] )
		)[0];

		$context->restore( $standing );

		$thrower = static function (): string {
			throw new Exception( 'plugin formatting failure' );
		};

		add_filter( 'gatherpress_date_format', $thrower );

		$caught = null;

		ob_start();

		try {
			Admin_List::get_instance()->custom_columns( 'datetime', $fixture['post_id'] );
		} catch ( Exception $thrown ) {
			// An outer list-table extension catching the throw and moving on.
			$caught = $thrown->getMessage();
		} finally {
			ob_end_clean();
			remove_filter( 'gatherpress_date_format', $thrower );
		}

		$this->assertSame(
			'plugin formatting failure',
			$caught,
			'Fixture failure: the render must actually throw for this test to mean anything.'
		);
		$this->assertSame(
			$standing,
			$context->current(),
			'A throw during rendering must leave the previously standing occurrence context in place.'
		);

		$context->restore( null );
	}

	/**
	 * Each row is dated from its own post, never from a sibling of the series.
	 *
	 * N2: the sort and the bucket predicate read a one-row-per-post relation,
	 * because SQL cannot consult the `gatherpress_series_post_ids` PHP filter
	 * that defines the sibling set. If the column resolved across the whole
	 * series it would disagree with both, and every sibling row of a split
	 * series would print the same date, so the list would stop distinguishing
	 * them.
	 *
	 * Three dates are in play and all three differ, so no two mechanisms can
	 * produce the same expected value: the sibling owns the earliest upcoming
	 * occurrence in the series (two hours out), the row's own post owns the
	 * next one after that (five hours out), and the row's post is anchored two
	 * days back. A series-wide resolution picks the sibling's row, which
	 * `Context::resolve()` refuses to serve to a post that does not own it, so
	 * the column falls back to the anchor: a third value, and the one the
	 * assertions below rule out.
	 *
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_render_datetime_column_dates_each_row_from_its_own_post(): void {
		gatherpress_reset_custom_tables();

		$now     = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$anchor  = $now->modify( '-2 days +5 hours' );
		$sibling = $this->create_series_anchored_at( $now->modify( '+2 hours' ), 3 );
		$post_id = $this->create_series_anchored_at( $anchor, 10 );

		$filter = static function ( array $post_ids, int $requested ) use ( $post_id, $sibling ): array {
			return in_array( $requested, array( $post_id, $sibling ), true )
				? array( $post_id, $sibling )
				: $post_ids;
		};

		add_filter( 'gatherpress_series_post_ids', $filter, 10, 2 );

		ob_start();
		Admin_List::get_instance()->custom_columns( 'datetime', $post_id );
		$output = ob_get_clean();

		remove_filter( 'gatherpress_series_post_ids', $filter, 10 );

		$this->assertStringContainsString(
			$this->format_column_datetime( $now->modify( '+5 hours' ) ),
			$output,
			'The row must show the earliest upcoming occurrence its own post owns.'
		);
		$this->assertStringNotContainsString(
			$this->format_column_datetime( $now->modify( '+2 hours' ) ),
			$output,
			"The row must not show a sibling post's occurrence, which the sort and the bucket cannot see."
		);
		$this->assertStringNotContainsString(
			$this->format_column_datetime( $anchor ),
			$output,
			'The row must not fall back to its anchor, which is what a refused sibling occurrence leaves behind.'
		);
	}

	/**
	 * Drive every SQL-emitting decision the admin events list makes.
	 *
	 * The real entry points, not the bodies behind them: the view links (which
	 * is what calls `get_event_counts()`), both bucketed list queries, and the
	 * date column render for every row each returns. A capture taken inside
	 * one filter only observes the queries that already reach that filter,
	 * which is how a "performs no extra queries" test passes over an unguarded
	 * entry point.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function drive_admin_list_surface(): void {
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );
		$instance->views_edit( array( 'all' => '<a href="#">All</a>' ) );

		foreach ( array( 'upcoming', 'past' ) as $bucket ) {
			foreach ( $this->run_admin_view_query( $bucket ) as $post_id ) {
				set_current_screen( 'edit-' . Event::POST_TYPE );

				ob_start();
				$instance->custom_columns( 'datetime', $post_id );
				ob_end_clean();
			}
		}

		set_current_screen( 'front' );
	}

	/**
	 * Capture the statements one pass over the admin list surface emits.
	 *
	 * The surface is driven once before the capture starts, because the first
	 * run of any request primes the object cache and the second therefore
	 * issues fewer statements: comparing a cold capture against a warm one
	 * measures the cache, not the code. The interpolated GMT timestamps are
	 * normalized away, because two captures taken microseconds apart can
	 * straddle a second boundary. Nothing else is touched, so an added join,
	 * an added column or an extra statement still fails the comparison.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The statements, in execution order.
	 */
	protected function capture_admin_list_queries(): array {
		global $wpdb;

		$this->drive_admin_list_surface();

		$previous_queries   = $wpdb->queries;
		$previous_save      = $wpdb->save_queries;
		$wpdb->queries      = array();
		$wpdb->save_queries = true;

		$this->drive_admin_list_surface();

		$captured = array_map(
			static function ( array $entry ): string {
				return (string) preg_replace(
					'/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
					'{datetime}',
					(string) $entry[0]
				);
			},
			$wpdb->queries
		);

		$wpdb->queries      = $previous_queries;
		$wpdb->save_queries = $previous_save;

		return $captured;
	}

	/**
	 * REQ-16: the admin events list is untouched on a site with no recurring events.
	 *
	 * Three admin-list decisions now read the occurrence relation: the date
	 * column, the date ordering, and the Upcoming/Past bucket predicate and
	 * its counts. Each is a place a site that has never authored a recurring
	 * event could start paying for a join it has no rows for, and the counts
	 * are the newest of the three.
	 *
	 * Both halves of the requirement are asserted. Nothing in the capture may
	 * name the occurrence table or the relation's alias, which is what covers
	 * `get_event_counts()` and the column, neither of which is a hook and so
	 * neither of which a detach can silence. And the whole capture must be
	 * byte-identical with the recurrence clause filters detached, which is
	 * what covers the filter that rewrites the list query itself.
	 *
	 * @covers ::get_event_counts
	 * @covers ::views_edit
	 * @covers ::custom_columns
	 * @covers ::render_datetime_column
	 *
	 * @return void
	 */
	public function test_admin_list_runs_identical_sql_without_recurring_events(): void {
		global $wpdb;

		gatherpress_reset_custom_tables();

		$this->create_plain_event( '+1 day', '+1 day +2 hours' );
		$this->create_plain_event( '+3 days', '+3 days +2 hours' );
		$this->create_plain_event( '-2 days', '-2 days +2 hours' );

		$this->assertFalse(
			Recurrence_Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$with = $this->capture_admin_list_queries();

		$this->assertNotEmpty(
			$with,
			'Failed to assert the capture actually observed the admin list surface.'
		);

		$occurrences_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		foreach ( $with as $statement ) {
			$this->assertStringNotContainsString(
				$occurrences_table,
				$statement,
				'A site with no recurring events must never read the occurrence table from the admin list.'
			);
			$this->assertStringNotContainsString(
				Recurrence_Query::ADMIN_SORT_ALIAS,
				$statement,
				'A site with no recurring events must never join the occurrence display relation.'
			);
		}

		$recurrence = Recurrence_Query::get_instance();

		remove_filter( 'posts_clauses', array( $recurrence, 'adjust_admin_occurrence_sorting' ), 11 );
		remove_filter( 'posts_clauses', array( $recurrence, 'expand_event_clauses' ), 11 );
		remove_filter( 'the_posts', array( $recurrence, 'attach_occurrences' ), 10 );

		$without = $this->capture_admin_list_queries();

		add_filter( 'posts_clauses', array( $recurrence, 'adjust_admin_occurrence_sorting' ), 11, 2 );
		add_filter( 'posts_clauses', array( $recurrence, 'expand_event_clauses' ), 11, 2 );
		add_filter( 'the_posts', array( $recurrence, 'attach_occurrences' ), 10, 2 );

		$this->assertSame(
			$without,
			$with,
			'Failed to assert a flag-off site runs byte-identical admin list SQL with and without the recurrence'
				. ' clause filters attached.'
		);
	}
}
