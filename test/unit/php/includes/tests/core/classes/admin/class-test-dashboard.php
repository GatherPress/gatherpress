<?php
/**
 * Unit tests for the "At a Glance" Dashboard class.
 *
 * @package GatherPress\Core\Admin
 * @since TBD
 */

namespace GatherPress\Tests\Core\Admin;

use GatherPress\Core\Admin\Dashboard;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Rsvp\Response\Status as Rsvp_Status;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Venue\Venue;
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
	 * Instance of Dashboard.
	 *
	 * @var Dashboard
	 */
	protected Dashboard $instance;

	/**
	 * Set up before each test.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->instance = Dashboard::get_instance();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->instance->delete_transients();

		parent::tearDown();
	}

	/**
	 * Verify hooks registered by setup_hooks.
	 *
	 * @covers ::setup_hooks
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$this->assert_hooks(
			array(
				array(
					'type'     => 'filter',
					'name'     => 'dashboard_glance_items',
					'priority' => 10,
					'callback' => array( $this->instance, 'add_glance_items' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'admin_head-index.php',
					'priority' => 10,
					'callback' => array( $this->instance, 'print_glance_styles' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'transition_post_status',
					'priority' => 10,
					'callback' => array( $this->instance, 'invalidate_on_post_change' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'set_object_terms',
					'priority' => 10,
					'callback' => array( $this->instance, 'invalidate_on_rsvp_terms' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'deleted_comment',
					'priority' => 10,
					'callback' => array( $this->instance, 'invalidate_on_rsvp_delete' ),
				),
			),
			$this->instance
		);
	}

	/**
	 * Verify dashicon codepoints resolution.
	 *
	 * @covers ::dashicon_codepoint
	 *
	 * @return void
	 */
	public function test_dashicon_codepoint(): void {
		$this->assertSame(
			'\\f484',
			Utility::invoke_hidden_method( $this->instance, 'dashicon_codepoint', array( 'dashicons-nametag' ) ),
			'Expected dashicons-nametag to resolve to \\f484.'
		);

		$this->assertSame(
			'\\f230',
			Utility::invoke_hidden_method( $this->instance, 'dashicon_codepoint', array( 'dashicons-location' ) ),
			'Expected dashicons-location to resolve to \\f230.'
		);

		$this->assertNull(
			Utility::invoke_hidden_method( $this->instance, 'dashicon_codepoint', array( '' ) ),
			'Empty icon should return null.'
		);

		$this->assertNull(
			Utility::invoke_hidden_method(
				$this->instance,
				'dashicon_codepoint',
				array( 'data:image/svg+xml;base64,...' )
			),
			'Data URI icon should return null.'
		);

		$this->assertNull(
			Utility::invoke_hidden_method(
				$this->instance,
				'dashicon_codepoint',
				array( 'https://example.com/icon.png' )
			),
			'External URL icon should return null.'
		);

		$this->assertNull(
			Utility::invoke_hidden_method( $this->instance, 'dashicon_codepoint', array( 'dashicons-unknown-icon' ) ),
			'Unknown dashicon should return null.'
		);
	}

	/**
	 * Verify make_item creates anchor or span tags appropriately.
	 *
	 * @covers ::make_item
	 *
	 * @return void
	 */
	public function test_make_item(): void {
		$linked = Utility::invoke_hidden_method(
			$this->instance,
			'make_item',
			array( '3 Events', 'https://example.com/admin', 'gp-glance-event' )
		);

		$this->assertSame(
			'<a href="https://example.com/admin" class="gp-glance-event">3 Events</a>',
			$linked
		);

		$unlinked = Utility::invoke_hidden_method(
			$this->instance,
			'make_item',
			array( '3 Events', null, 'gp-glance-event' )
		);

		$this->assertSame(
			'<span class="gp-glance-event">3 Events</span>',
			$unlinked
		);
	}

	/**
	 * Verify caching and transient invalidation.
	 *
	 * @covers ::get_cached_count
	 * @covers ::set_cached_count
	 * @covers ::transient_keys
	 * @covers ::delete_transients
	 *
	 * @return void
	 */
	public function test_cache_helpers_and_invalidation(): void {
		$key = 'gp_glance_gatherpress_event_upcoming';

		$this->assertFalse(
			Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) )
		);

		Utility::invoke_hidden_method( $this->instance, 'set_cached_count', array( $key, 42 ) );

		$this->assertSame(
			42,
			Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) )
		);

		$this->instance->delete_transients();

		$this->assertFalse(
			Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) )
		);
	}

	/**
	 * Verify invalidation on post status transition.
	 *
	 * @covers ::invalidate_on_post_change
	 *
	 * @return void
	 */
	public function test_invalidate_on_post_change(): void {
		$event_post = $this->factory()->post->create_and_get(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$key = 'gp_glance_gatherpress_event_upcoming';
		Utility::invoke_hidden_method( $this->instance, 'set_cached_count', array( $key, 10 ) );

		// Same status should not invalidate.
		$this->instance->invalidate_on_post_change( 'publish', 'publish', $event_post );
		$this->assertSame( 10, Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) ) );

		// Unrelated post type should not invalidate.
		$standard_post = $this->factory()->post->create_and_get(
			array(
				'post_type' => 'post',
			)
		);
		$this->instance->invalidate_on_post_change( 'publish', 'draft', $standard_post );
		$this->assertSame( 10, Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) ) );

		// Status change on event should invalidate.
		$this->instance->invalidate_on_post_change( 'publish', 'draft', $event_post );
		$this->assertFalse( Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) ) );
	}

	/**
	 * Verify invalidation on RSVP terms change.
	 *
	 * @covers ::invalidate_on_rsvp_terms
	 *
	 * @return void
	 */
	public function test_invalidate_on_rsvp_terms(): void {
		$key = 'gp_glance_gatherpress_event_upcoming';
		Utility::invoke_hidden_method( $this->instance, 'set_cached_count', array( $key, 5 ) );

		// Unrelated taxonomy should not invalidate.
		$this->instance->invalidate_on_rsvp_terms( 123, array( 'cat-1' ), array( 1 ), 'category' );
		$this->assertSame( 5, Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) ) );

		// RSVP taxonomy should invalidate.
		$this->instance->invalidate_on_rsvp_terms( 123, array( 'attending' ), array( 2 ), Rsvp_Status::TAXONOMY );
		$this->assertFalse( Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) ) );
	}

	/**
	 * Verify invalidation on RSVP deletion.
	 *
	 * @covers ::invalidate_on_rsvp_delete
	 *
	 * @return void
	 */
	public function test_invalidate_on_rsvp_delete(): void {
		$key = 'gp_glance_gatherpress_event_upcoming';
		Utility::invoke_hidden_method( $this->instance, 'set_cached_count', array( $key, 7 ) );

		$unrelated_comment               = new WP_Comment( (object) array() );
		$unrelated_comment->comment_type = 'comment';

		$this->instance->invalidate_on_rsvp_delete( 123, $unrelated_comment );
		$this->assertSame( 7, Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) ) );

		$rsvp_comment               = new WP_Comment( (object) array() );
		$rsvp_comment->comment_type = Rsvp::COMMENT_TYPE;

		$this->instance->invalidate_on_rsvp_delete( 123, $rsvp_comment );
		$this->assertFalse( Utility::invoke_hidden_method( $this->instance, 'get_cached_count', array( $key ) ) );
	}

	/**
	 * Verify print_glance_styles outputs style tag with icon rules.
	 *
	 * @covers ::print_glance_styles
	 *
	 * @return void
	 */
	public function test_print_glance_styles(): void {
		ob_start();
		$this->instance->print_glance_styles();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<style>', $output );
		$this->assertStringContainsString( '</style>', $output );
		$this->assertStringContainsString( '.gp-glance-gatherpress_event:before', $output );
		$this->assertStringContainsString( '.gp-glance-gatherpress_venue:before', $output );
		$this->assertStringContainsString( 'span.gp-glance-spacer', $output );
	}

	/**
	 * Verify add_glance_items appends GatherPress metrics.
	 *
	 * @covers ::add_glance_items
	 * @covers ::count_core_glance_items
	 * @covers ::event_date_items
	 * @covers ::count_events
	 * @covers ::venue_item
	 * @covers ::rsvp_items
	 * @covers ::count_rsvps
	 *
	 * @return void
	 */
	public function test_add_glance_items(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$items = $this->instance->add_glance_items( array() );

		$this->assertNotEmpty( $items );

		$joined = implode( ' ', $items );
		$this->assertStringContainsString( 'Past Event', $joined );
		$this->assertStringContainsString( 'Upcoming Event', $joined );
		$this->assertStringContainsString( 'Venue', $joined );
		$this->assertStringContainsString( 'Attending RSVP', $joined );
		$this->assertStringContainsString( 'on Waiting List', $joined );
	}
}
