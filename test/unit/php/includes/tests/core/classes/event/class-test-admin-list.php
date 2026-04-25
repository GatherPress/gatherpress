<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Admin_List.
 *
 * @package GatherPress\Core\Event
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Admin_List;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue_Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
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
				'name'     => 'load-edit.php',
				'priority' => 10,
				'callback' => array( $instance, 'default_sort' ),
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
			array(
				'type'     => 'filter',
				'name'     => 'query_vars',
				'priority' => 10,
				'callback' => array( $instance, 'query_vars' ),
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
	 * Coverage for default_sort method when no screen is available.
	 *
	 * Exercises the ! $screen early return, which can occur in multisite
	 * contexts where get_current_screen() returns null before any screen is set.
	 *
	 * @covers ::default_sort
	 *
	 * @return void
	 */
	public function test_default_sort_no_screen(): void {
		$instance = Admin_List::get_instance();

		// Ensure no screen is set so get_current_screen() returns null.
		unset( $GLOBALS['current_screen'] );

		// Ensure $_GET is clean.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['orderby'], $_GET['order'] );

		$instance->default_sort();

		// Should return early without modifying $_GET.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertArrayNotHasKey( 'orderby', $_GET, 'Should not set orderby when no screen.' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$this->assertArrayNotHasKey( 'order', $_GET, 'Should not set order when no screen.' );
	}

	/**
	 * Coverage for default_sort method when on the wrong screen.
	 *
	 * @covers ::default_sort
	 *
	 * @return void
	 */
	public function test_default_sort_wrong_screen(): void {
		$instance = Admin_List::get_instance();

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
		$instance = Admin_List::get_instance();

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
		$instance = Admin_List::get_instance();

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
	 * When default_sort() adds orderby/order to $_GET, WordPress's
	 * is_base_request() returns false and omits the current class from "All".
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
	 * A running event (started but not ended) should count as upcoming
	 * because datetime_end_gmt >= now, and also as past because
	 * datetime_start_gmt < now.
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

		// Running events appear in both counts (same logic as Event_Query with inclusive=true).
		$this->assertSame( 1, $counts['upcoming'], 'Running event should count as upcoming.' );
		$this->assertSame( 1, $counts['past'], 'Running event should count as past.' );
	}

	/**
	 * Coverage for get_event_counts method with events that have no date set.
	 *
	 * Events without a date/time set have no row in the gatherpress_events table.
	 * These should be counted as upcoming, not past.
	 *
	 * @covers ::get_event_counts
	 *
	 * @return void
	 */
	public function test_get_event_counts_with_no_date(): void {
		$instance = Admin_List::get_instance();

		// Reset cached counts.
		Utility::set_and_get_hidden_property( $instance, 'event_counts', array() );

		// Create an event without setting any dates.
		$this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		$counts = Utility::invoke_hidden_method( $instance, 'get_event_counts' );

		$this->assertSame( 1, $counts['upcoming'], 'Event without date should count as upcoming.' );
		$this->assertSame( 0, $counts['past'], 'Event without date should not count as past.' );
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
		$instance = Admin_List::get_instance();

		$result = $instance->query_vars( array() );

		$this->assertContains(
			'gatherpress_event_query',
			$result,
			'Should add gatherpress_event_query even with empty input.'
		);
		$this->assertCount( 1, $result, 'Should have exactly 1 query var.' );
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
	 * Coverage for handle_venue_sorting method.
	 *
	 * @covers ::handle_venue_sorting
	 *
	 * @return void
	 */
	public function test_handle_venue_sorting(): void {
		$instance = Admin_List::get_instance();

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
			strpos( $result, 'ASC' ) !== false || strpos( $result, 'DESC' ) !== false,
			'ORDER BY clause should contain ASC or DESC'
		);

		// Verify the method returns a string (basic type check).
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Coverage for venue_sorting_join_paged method with a current screen set.
	 *
	 * Exercises the $screen->post_type branch, where the venue taxonomy is derived
	 * from the currently active admin screen rather than falling back to the default.
	 *
	 * @covers ::venue_sorting_join_paged
	 *
	 * @return void
	 */
	public function test_venue_sorting_join_paged_with_screen(): void {
		global $wpdb;
		$instance = Admin_List::get_instance();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		$original_join = "LEFT JOIN {$wpdb->posts} AS posts ON posts.ID = {$wpdb->posts}.ID";
		$result        = $instance->venue_sorting_join_paged( $original_join );

		set_current_screen( 'front' );

		$this->assertStringContainsString( "'" . Venue::TAXONOMY . "'", $result );
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
		$instance = Admin_List::get_instance();

		// Ensure no screen is set so the method falls back to the default post type.
		unset( $GLOBALS['current_screen'] );

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
		$this->assertStringContainsString( "'" . Venue::TAXONOMY . "'", $result );
	}

	/**
	 * Coverage for venue_sorting_join_paged when the venue taxonomy is not registered.
	 *
	 * Verifies that the method returns the original $join string unchanged when
	 * taxonomy_exists() returns false for the derived taxonomy slug, avoiding
	 * invalid SQL from being appended.
	 *
	 * @covers ::venue_sorting_join_paged
	 *
	 * @return void
	 */
	public function test_venue_sorting_join_paged_unregistered_taxonomy(): void {
		$instance      = Admin_List::get_instance();
		$original_join = 'ORIGINAL_JOIN';

		// Temporarily unregister the venue taxonomy so taxonomy_exists() returns false.
		unregister_taxonomy( Venue::TAXONOMY );

		$result = $instance->venue_sorting_join_paged( $original_join );

		// Re-register the taxonomy so subsequent tests are not affected.
		Venue_Setup::get_instance()->register_taxonomy();

		$this->assertSame(
			$original_join,
			$result,
			'Failed to assert that venue_sorting_join_paged returns the original join.'
		);
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
		$instance = Admin_List::get_instance();

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
	 * Tests handle_venue_sorting with venue orderby.
	 *
	 * Covers: Venue sorting logic.
	 *
	 * @covers ::handle_venue_sorting
	 * @covers ::handle_column_sorting
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

		$instance = Admin_List::get_instance();
		$instance->handle_venue_sorting( $query );

		// Verify that sorting order was set.
		$this->assertEquals( 'DESC', $query->get( 'venue_sort_order' ), 'Should set DESC order' );

		// Verify filters were added.
		$this->assertNotFalse(
			has_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) ),
			'Should add posts_join_paged filter for venue sorting'
		);
		$this->assertNotFalse(
			has_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) ),
			'Should add posts_groupby filter for venue sorting'
		);
		$this->assertNotFalse(
			has_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) ),
			'Should add posts_orderby filter for venue sorting'
		);

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_venue_sorting with invalid order.
	 *
	 * Covers: Order validation in venue sorting.
	 *
	 * @covers ::handle_venue_sorting
	 * @covers ::handle_column_sorting
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

		$instance = Admin_List::get_instance();
		$instance->handle_venue_sorting( $query );

		// Should default to ASC for invalid order.
		$this->assertEquals( 'ASC', $query->get( 'venue_sort_order' ), 'Should default to ASC for invalid order' );

		// Clean up.
		remove_filter( 'posts_join_paged', array( $instance, 'venue_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $instance, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $instance, 'venue_sorting_orderby' ) );
		set_current_screen( 'front' );
	}

	/**
	 * Tests handle_venue_sorting early return when orderby is not 'venue'.
	 *
	 * @covers ::handle_venue_sorting
	 * @covers ::handle_column_sorting
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

		$instance = Admin_List::get_instance();
		$instance->handle_venue_sorting( $query );

		// Should not set venue_sort_order since orderby is not 'venue'.
		$this->assertEmpty(
			$query->get( 'venue_sort_order' ),
			'Should not set venue_sort_order for non-venue orderby'
		);

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
	 * Coverage for custom_columns method with venue column.
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_venue_with_name(): void {
		$instance = Admin_List::get_instance();
		$post_id  = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Create a venue term and associate it with the event.
		$venue_name = 'Test Venue';
		$term       = wp_insert_term( $venue_name, Venue::TAXONOMY );
		wp_set_post_terms( $post_id, array( $term['term_id'] ), Venue::TAXONOMY );

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
		$instance = Admin_List::get_instance();
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
		$instance = Admin_List::get_instance();
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

		$instance = Admin_List::get_instance();

		ob_start();
		$instance->custom_columns( 'venue', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'dashicons-video-alt3', $output, 'Should display online event icon' );
	}
}
