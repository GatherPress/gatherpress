<?php
/**
 * Unit tests for the At a Glance dashboard items.
 *
 * @package GatherPress\Core\Admin
 * @since TBD
 */

namespace GatherPress\Tests\Core\Admin;

use GatherPress\Core\Admin\Dashboard;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Admin_List;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Comment;

/**
 * Class Test_Dashboard.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Dashboard
 */
class Test_Dashboard extends Base {

	/**
	 * Start each test with a cold cache.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_transient( Dashboard::CACHE_KEY );
		Utility::set_and_get_hidden_property( Admin_List::get_instance(), 'event_counts', array() );
	}

	/**
	 * Clean up after each test.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_transient( Dashboard::CACHE_KEY );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Log in as a user with the given role.
	 *
	 * @since TBD
	 *
	 * @param string $role Role name.
	 *
	 * @return void
	 */
	protected function log_in( string $role ): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * Create a published event that starts at the given time.
	 *
	 * @since TBD
	 *
	 * @param string $when Relative time the event starts, for `strtotime()`.
	 *
	 * @return int The event ID.
	 */
	protected function create_event( string $when ): int {
		$post_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get()->ID;

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', strtotime( $when ) ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( $when . ' +2 hours' ) ),
				'timezone'       => 'UTC',
			)
		);

		return $post_id;
	}

	/**
	 * Hooks are registered.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Dashboard::get_instance();

		$this->assert_hooks(
			array(
				array(
					'type'     => 'filter',
					'name'     => 'dashboard_glance_items',
					'priority' => 10,
					'callback' => array( $instance, 'add_glance_items' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'admin_head-index.php',
					'priority' => 10,
					'callback' => array( $instance, 'print_styles' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'clean_post_cache',
					'priority' => 10,
					'callback' => array( $instance, 'maybe_flush_for_post' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'set_object_terms',
					'priority' => 10,
					'callback' => array( $instance, 'maybe_flush_for_terms' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'transition_comment_status',
					'priority' => 10,
					'callback' => array( $instance, 'maybe_flush_for_comment' ),
				),
			),
			$instance
		);
	}

	/**
	 * Users who can open the screens get links to them.
	 *
	 * @covers ::add_glance_items
	 * @covers ::get_event_items
	 * @covers ::get_rsvp_items
	 * @covers ::get_venue_item
	 *
	 * @return void
	 */
	public function test_add_glance_items_links_for_admin(): void {
		$this->log_in( 'administrator' );
		$this->create_event( '+1 day' );
		$this->create_event( '+2 days' );
		$this->create_event( '-2 days' );
		$this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$items = Dashboard::get_instance()->add_glance_items( array( 'existing' ) );
		$html  = implode( "\n", $items );

		$this->assertSame( 'existing', $items[0], 'Items from other plugins should stay first.' );
		$this->assertStringContainsString( '2 Upcoming Events</a>', $html );
		$this->assertStringContainsString( '1 Past Event</a>', $html );
		$this->assertStringContainsString( 'gatherpress_event_query=upcoming', $html );
		$this->assertStringContainsString( 'gatherpress_event_query=past', $html );
		$this->assertStringContainsString( '0 Attending RSVPs (Event)</a>', $html );
		$this->assertStringContainsString( '0 on Waiting List (Event)</a>', $html );
		$this->assertStringContainsString( 'page=gatherpress_rsvp', $html );
		$this->assertStringContainsString( 'response=attending', $html );
		$this->assertStringContainsString( 'response=waiting_list', $html );
		$this->assertStringContainsString( '1 Published Venue</a>', $html );
		$this->assertStringContainsString( 'post_type=gatherpress_venue', $html );
		$this->assertStringNotContainsString( '<span class="gatherpress-glance-gatherpress_', $html );
		$this->assertStringNotContainsString( '<span class="gatherpress-glance-rsvp', $html );
	}

	/**
	 * Users who cannot open the screens get plain text.
	 *
	 * @covers ::add_glance_items
	 * @covers ::get_event_items
	 * @covers ::get_rsvp_items
	 * @covers ::get_venue_item
	 *
	 * @return void
	 */
	public function test_add_glance_items_plain_text_for_subscriber(): void {
		$this->log_in( 'subscriber' );

		$html = implode( "\n", Dashboard::get_instance()->add_glance_items( array() ) );

		$this->assertStringNotContainsString( '<a ', $html );
		$this->assertStringContainsString( '-gatherpress_event">0 Upcoming Events</span>', $html );
		$this->assertStringContainsString( '-rsvp">0 Attending RSVPs (Event)</span>', $html );
		$this->assertStringContainsString( '-gatherpress_venue">0 Published Venues</span>', $html );
	}

	/**
	 * A spacer is added only when the items before ours end in the left column.
	 *
	 * @covers ::add_glance_items
	 *
	 * @return void
	 */
	public function test_add_glance_items_spacer_parity(): void {
		$instance = Dashboard::get_instance();
		$spacer   = '<span class="gatherpress-glance-spacer" aria-hidden="true"></span>';
		$core     = Utility::invoke_hidden_method( $instance, 'count_core_items' );
		$even     = array_fill( 0, $core % 2, 'other' );
		$odd      = array_fill( 0, 1 - $core % 2, 'other' );

		$this->assertNotContains( $spacer, $instance->add_glance_items( $even ) );
		$this->assertSame( $spacer, $instance->add_glance_items( $odd )[ count( $odd ) ] );
	}

	/**
	 * Nothing is added, not even a spacer, when no post type declares a support.
	 *
	 * @covers ::add_glance_items
	 *
	 * @return void
	 */
	public function test_add_glance_items_without_supports(): void {
		$removed = array();

		foreach ( array( Event::SUPPORT, Rsvp::SUPPORT, Venue::SUPPORT ) as $support ) {
			foreach ( get_post_types_by_support( $support ) as $post_type ) {
				remove_post_type_support( $post_type, $support );
				$removed[] = array( $post_type, $support );
			}
		}

		$items = Dashboard::get_instance()->add_glance_items( array( 'other' ) );

		foreach ( $removed as list( $post_type, $support ) ) {
			add_post_type_support( $post_type, $support );
		}

		$this->assertSame( array( 'other' ), $items );
	}

	/**
	 * Post types missing from a cache filled before they existed read as zero.
	 *
	 * @covers ::add_glance_items
	 *
	 * @return void
	 */
	public function test_add_glance_items_with_stale_cache(): void {
		set_transient(
			Dashboard::CACHE_KEY,
			array(
				'events' => array(),
				'rsvps'  => array(),
			)
		);

		$html = implode( "\n", Dashboard::get_instance()->add_glance_items( array() ) );

		$this->assertStringContainsString( '0 Upcoming Events', $html );
		$this->assertStringContainsString( '0 Attending RSVPs', $html );
	}

	/**
	 * Icons are printed for known menu icons, RSVPs and the spacer.
	 *
	 * @covers ::print_styles
	 *
	 * @return void
	 */
	public function test_print_styles(): void {
		register_post_type(
			'gp_test_glance',
			array(
				'menu_icon' => 'dashicons-admin-site',
				'supports'  => array( Event::SUPPORT ),
			)
		);

		$output = Utility::buffer_and_return( array( Dashboard::get_instance(), 'print_styles' ) );

		unregister_post_type( 'gp_test_glance' );

		$this->assertStringContainsString( 'gatherpress_event:before{content:"\f484";content:"\f484" / ""}', $output );
		$this->assertStringContainsString( 'gatherpress_venue:before{content:"\f230";content:"\f230" / ""}', $output );
		$this->assertStringContainsString( '-rsvp:before{content:"\f101";content:"\f101" / ""}', $output );
		$this->assertStringContainsString( '.gatherpress-glance-spacer{visibility:hidden}', $output );
		// Unknown menu icons keep the default bullet.
		$this->assertStringNotContainsString( 'gp_test_glance', $output );
	}

	/**
	 * Post types that declare a support but were never registered are skipped.
	 *
	 * @covers ::get_post_types
	 *
	 * @return void
	 */
	public function test_get_post_types_skips_unregistered(): void {
		add_post_type_support( 'gp_test_unregistered', Event::SUPPORT );

		$post_types = Utility::invoke_hidden_method(
			Dashboard::get_instance(),
			'get_post_types',
			array( Event::SUPPORT, Venue::SUPPORT )
		);
		$names      = wp_list_pluck( $post_types, 'name' );

		remove_post_type_support( 'gp_test_unregistered', Event::SUPPORT );

		$this->assertContains( Event::POST_TYPE, $names );
		$this->assertContains( Venue::POST_TYPE, $names );
		$this->assertNotContains( 'gp_test_unregistered', $names );
	}

	/**
	 * Counts are computed on a miss and served from the transient after.
	 *
	 * @covers ::get_counts
	 * @covers ::count_rsvps
	 *
	 * @return void
	 */
	public function test_get_counts(): void {
		$instance = Dashboard::get_instance();
		$post_id  = $this->create_event( '+1 day' );
		$rsvp     = new Rsvp( $post_id );

		$rsvp->save( $this->factory->user->create(), Status::ATTENDING->value );
		$rsvp->save( $this->factory->user->create(), Status::ATTENDING->value );
		$waiting = $rsvp->save( $this->factory->user->create(), Status::ATTENDING->value );

		// Without a limit a waiting list RSVP is let straight in, so move one there by hand.
		wp_set_object_terms( $waiting['comment_id'], Status::WAITING_LIST->value, Status::TAXONOMY );

		$counts = Utility::invoke_hidden_method( $instance, 'get_counts' );

		$this->assertSame(
			array(
				'upcoming' => 1,
				'past'     => 0,
			),
			$counts['events'][ Event::POST_TYPE ]
		);
		$this->assertSame(
			array(
				'attending'    => 2,
				'waiting_list' => 1,
			),
			$counts['rsvps'][ Event::POST_TYPE ]
		);
		$this->assertSame( $counts, get_transient( Dashboard::CACHE_KEY ) );

		set_transient( Dashboard::CACHE_KEY, array( 'cached' ) );

		$this->assertSame( array( 'cached' ), Utility::invoke_hidden_method( $instance, 'get_counts' ) );
	}

	/**
	 * Missing counts, from a cache filled before a post type existed, read as zero.
	 *
	 * @covers ::get_event_items
	 * @covers ::get_rsvp_items
	 *
	 * @return void
	 */
	public function test_items_default_to_zero(): void {
		$instance  = Dashboard::get_instance();
		$post_type = get_post_type_object( Event::POST_TYPE );
		$html      = implode(
			"\n",
			array_merge(
				Utility::invoke_hidden_method( $instance, 'get_event_items', array( $post_type, array() ) ),
				Utility::invoke_hidden_method( $instance, 'get_rsvp_items', array( $post_type, array() ) )
			)
		);

		$this->assertStringContainsString( '0 Upcoming Events', $html );
		$this->assertStringContainsString( '0 Past Events', $html );
		$this->assertStringContainsString( '0 Attending RSVPs', $html );
		$this->assertStringContainsString( '0 on Waiting List', $html );
	}

	/**
	 * Core's visible items are counted the way core prints them.
	 *
	 * @covers ::count_core_items
	 *
	 * @return void
	 */
	public function test_count_core_items(): void {
		$instance = Dashboard::get_instance();

		$this->assertSame( 0, Utility::invoke_hidden_method( $instance, 'count_core_items' ) );

		$post_id = $this->factory->post->create();
		$this->factory->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( 2, Utility::invoke_hidden_method( $instance, 'count_core_items' ) );

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '0',
			)
		);

		// Core only clears the count cache when an approved comment is added.
		wp_cache_delete( 'comments-0', 'counts' );

		$this->assertSame( 4, Utility::invoke_hidden_method( $instance, 'count_core_items' ) );

		wp_set_comment_status( $comment_id, 'approve' );

		$this->assertSame( 3, Utility::invoke_hidden_method( $instance, 'count_core_items' ) );
	}

	/**
	 * Saving an event flushes the counts, saving anything else does not.
	 *
	 * @covers ::maybe_flush_for_post
	 *
	 * @return void
	 */
	public function test_maybe_flush_for_post(): void {
		$instance = Dashboard::get_instance();
		$post     = $this->factory->post->create_and_get();
		$event    = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		set_transient( Dashboard::CACHE_KEY, array() );
		$instance->maybe_flush_for_post( $post->ID, $post );

		$this->assertSame( array(), get_transient( Dashboard::CACHE_KEY ) );

		$instance->maybe_flush_for_post( $event->ID, $event );

		$this->assertFalse( get_transient( Dashboard::CACHE_KEY ) );
	}

	/**
	 * Setting an RSVP response flushes the counts, other terms do not.
	 *
	 * @covers ::maybe_flush_for_terms
	 *
	 * @return void
	 */
	public function test_maybe_flush_for_terms(): void {
		$instance = Dashboard::get_instance();

		set_transient( Dashboard::CACHE_KEY, array() );
		$instance->maybe_flush_for_terms( 1, array(), array(), 'category' );

		$this->assertSame( array(), get_transient( Dashboard::CACHE_KEY ) );

		$instance->maybe_flush_for_terms( 1, array(), array(), Status::TAXONOMY );

		$this->assertFalse( get_transient( Dashboard::CACHE_KEY ) );
	}

	/**
	 * An RSVP status change flushes the counts, other comments do not.
	 *
	 * @covers ::maybe_flush_for_comment
	 *
	 * @return void
	 */
	public function test_maybe_flush_for_comment(): void {
		$instance = Dashboard::get_instance();
		$comment  = new WP_Comment( (object) array( 'comment_type' => 'comment' ) );
		$rsvp     = new WP_Comment( (object) array( 'comment_type' => Rsvp::COMMENT_TYPE ) );

		set_transient( Dashboard::CACHE_KEY, array() );
		$instance->maybe_flush_for_comment( 'approved', 'unapproved', $comment );

		$this->assertSame( array(), get_transient( Dashboard::CACHE_KEY ) );

		$instance->maybe_flush_for_comment( 'approved', 'unapproved', $rsvp );

		$this->assertFalse( get_transient( Dashboard::CACHE_KEY ) );
	}

	/**
	 * The label follows the count.
	 *
	 * @covers ::get_label
	 *
	 * @return void
	 */
	public function test_get_label(): void {
		$instance  = Dashboard::get_instance();
		$post_type = get_post_type_object( Venue::POST_TYPE );

		$this->assertSame( 'Venue', Utility::invoke_hidden_method( $instance, 'get_label', array( $post_type, 1 ) ) );
		$this->assertSame( 'Venues', Utility::invoke_hidden_method( $instance, 'get_label', array( $post_type, 0 ) ) );
	}

	/**
	 * An item is a link when it has a URL and a span when it does not.
	 *
	 * @covers ::get_item
	 *
	 * @return void
	 */
	public function test_get_item(): void {
		$instance = Dashboard::get_instance();

		$this->assertSame(
			'<a class="x" href="https://example.org/">A &amp; B</a>',
			Utility::invoke_hidden_method( $instance, 'get_item', array( 'A & B', 'https://example.org/', 'x' ) )
		);
		$this->assertSame(
			'<span class="x">A &amp; B</span>',
			Utility::invoke_hidden_method( $instance, 'get_item', array( 'A & B', '', 'x' ) )
		);
	}
}
