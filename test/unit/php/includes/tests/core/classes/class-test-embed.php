<?php
/**
 * Test class for Embed.
 *
 * @package GatherPress\Tests\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Embed;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Status;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Embed.
 *
 * @since TBD
 *
 * @coversDefaultClass \GatherPress\Core\Embed
 */
class Test_Embed extends Base {

	/**
	 * Custom post type that only declares event-date support.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const CUSTOM_EVENT_POST_TYPE = 'gp_test_embed_event';

	/**
	 * Test instance of Embed.
	 *
	 * @since TBD
	 *
	 * @var Embed
	 */
	protected Embed $instance;

	/**
	 * Set up test environment.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->instance = Embed::get_instance();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unregister_post_type( self::CUSTOM_EVENT_POST_TYPE );
		wp_dequeue_style( 'gatherpress-embed-style' );
		parent::tearDown();
	}

	/**
	 * Creates an event with a start date a given number of days from now.
	 *
	 * @since TBD
	 *
	 * @param int    $days      Days from now; negative for a past event.
	 * @param string $post_type The event post type.
	 *
	 * @return Event The event.
	 */
	private function create_event( int $days, string $post_type = Event::POST_TYPE ): Event {
		$post  = $this->mock->post( array( 'post_type' => $post_type ) )->get();
		$event = new Event( $post->ID );
		$start = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );

		$event->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', strtotime( $start ) + HOUR_IN_SECONDS ),
				'timezone'       => 'America/New_York',
			)
		);

		return $event;
	}

	/**
	 * Creates a venue with an address and attaches it to an event.
	 *
	 * @since TBD
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return int The venue post ID.
	 */
	private function attach_venue( int $event_id ): int {
		$venue = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Embed Test Venue',
				'post_name'  => 'embed-test-venue',
			)
		)->get();

		update_post_meta( $venue->ID, 'gatherpress_address', '123 Main Street, Montclair, NJ' );
		wp_set_post_terms( $event_id, '_embed-test-venue', Venue::TAXONOMY );

		return $venue->ID;
	}

	/**
	 * Test the hooks are registered.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$hooks = array(
			array(
				'type'     => 'action',
				'name'     => 'embed_content',
				'priority' => 10,
				'callback' => array( $this->instance, 'render_details' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_embed_scripts',
				'priority' => 10,
				'callback' => array( $this->instance, 'enqueue_style' ),
			),
		);

		$this->assert_hooks( $hooks, $this->instance );
	}

	/**
	 * Returns nothing without a post, for a regular post, and for a password-protected event.
	 *
	 * @since TBD
	 *
	 * @covers ::get_details_markup
	 *
	 * @return void
	 */
	public function test_get_details_markup_returns_empty_for_unsupported_posts(): void {
		$this->assertSame( '', $this->instance->get_details_markup( null ), 'No post gives no markup.' );

		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$this->assertSame( '', $this->instance->get_details_markup( $post ), 'A regular post gives no markup.' );

		$event = $this->create_event( 7 );
		wp_update_post(
			array(
				'ID'            => $event->post->ID,
				'post_password' => 'secret',
			)
		);
		$this->assertSame(
			'',
			$this->instance->get_details_markup( get_post( $event->post->ID ) ),
			'A password-protected event gives no markup.'
		);
	}

	/**
	 * An upcoming event shows its date, venue, online note and RSVP link.
	 *
	 * @since TBD
	 *
	 * @covers ::get_details_markup
	 *
	 * @return void
	 */
	public function test_get_details_markup_for_upcoming_event(): void {
		$event = $this->create_event( 7 );
		$post  = get_post( $event->post->ID );

		$this->attach_venue( $post->ID );
		$this->assertTrue( $event->set_online( true ), 'Failed to mark the event online.' );

		$markup = $this->instance->get_details_markup( $post );

		$this->assertStringContainsString( 'gatherpress-embed--event', $markup );
		$this->assertStringContainsString( esc_html( $event->get_display_datetime() ), $markup );
		$this->assertStringContainsString( 'Embed Test Venue', $markup );
		$this->assertStringContainsString( '123 Main Street, Montclair, NJ', $markup );
		$this->assertStringContainsString( 'Online event', $markup );
		$this->assertStringContainsString( esc_url( get_permalink( $post ) ), $markup );
		$this->assertStringContainsString( 'target="_top"', $markup );
		$this->assertStringNotContainsString( 'gatherpress-embed__status', $markup );
	}

	/**
	 * An online-only event does not show the online term name as a venue.
	 *
	 * @since TBD
	 *
	 * @covers ::get_details_markup
	 *
	 * @return void
	 */
	public function test_get_details_markup_for_online_only_event(): void {
		$event = $this->create_event( 7 );
		$this->assertTrue( $event->set_online( true ), 'Failed to mark the event online.' );

		$markup = $this->instance->get_details_markup( get_post( $event->post->ID ) );

		$this->assertStringContainsString( 'Online event', $markup );
		$this->assertStringNotContainsString( 'gatherpress-embed__venue', $markup );
	}

	/**
	 * A password-protected venue keeps its name and address out of the event embed.
	 *
	 * @since TBD
	 *
	 * @covers ::get_details_markup
	 *
	 * @return void
	 */
	public function test_get_details_markup_hides_protected_venue(): void {
		$event    = $this->create_event( 7 );
		$venue_id = $this->attach_venue( $event->post->ID );

		wp_update_post(
			array(
				'ID'            => $venue_id,
				'post_password' => 'secret',
			)
		);

		$markup = $this->instance->get_details_markup( get_post( $event->post->ID ) );

		$this->assertStringNotContainsString( 'Embed Test Venue', $markup );
		$this->assertStringNotContainsString( '123 Main Street', $markup );
	}

	/**
	 * A past event says so and has no RSVP link.
	 *
	 * @since TBD
	 *
	 * @covers ::get_details_markup
	 *
	 * @return void
	 */
	public function test_get_details_markup_for_past_event(): void {
		$event  = $this->create_event( -7 );
		$markup = $this->instance->get_details_markup( get_post( $event->post->ID ) );

		$this->assertStringContainsString( 'This event has passed.', $markup );
		$this->assertStringNotContainsString( 'gatherpress-embed__rsvp', $markup );
		$this->assertStringNotContainsString( 'Online event', $markup );
	}

	/**
	 * A canceled event says so and has no RSVP link.
	 *
	 * @since TBD
	 *
	 * @covers ::get_details_markup
	 *
	 * @return void
	 */
	public function test_get_details_markup_for_canceled_event(): void {
		$event = $this->create_event( 7 );
		$this->assertTrue(
			( new Status( $event->post->ID ) )->set( Status::STATUS_CANCELED ),
			'Failed to cancel the event.'
		);

		$markup = $this->instance->get_details_markup( get_post( $event->post->ID ) );

		$this->assertStringContainsString( 'This event is canceled.', $markup );
		$this->assertStringNotContainsString( 'gatherpress-embed__rsvp', $markup );
	}

	/**
	 * A custom post type with event-date support gets the event embed, without RSVP when unsupported.
	 *
	 * @since TBD
	 *
	 * @covers ::get_details_markup
	 *
	 * @return void
	 */
	public function test_get_details_markup_for_custom_event_post_type(): void {
		register_post_type(
			self::CUSTOM_EVENT_POST_TYPE,
			array(
				'public'   => true,
				'supports' => array( 'title', Event::SUPPORT ),
			)
		);

		$event  = $this->create_event( 7, self::CUSTOM_EVENT_POST_TYPE );
		$markup = $this->instance->get_details_markup( get_post( $event->post->ID ) );

		$this->assertStringContainsString( 'gatherpress-embed--event', $markup );
		$this->assertStringNotContainsString( 'gatherpress-embed__rsvp', $markup );
	}

	/**
	 * A venue shows its address, phone and website.
	 *
	 * @since TBD
	 *
	 * @covers ::get_details_markup
	 *
	 * @return void
	 */
	public function test_get_details_markup_for_venue(): void {
		$venue = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		$this->assertSame(
			'',
			trim( $this->instance->get_details_markup( $venue ) ),
			'A venue without details gives no markup.'
		);

		update_post_meta( $venue->ID, 'gatherpress_address', '123 Main Street, Montclair, NJ' );

		$markup = $this->instance->get_details_markup( $venue );

		$this->assertStringContainsString( '123 Main Street, Montclair, NJ', $markup );
		$this->assertStringNotContainsString( 'gatherpress-embed__phone', $markup, 'An empty phone gets no row.' );
		$this->assertStringNotContainsString( 'gatherpress-embed__website', $markup, 'An empty website gets no row.' );

		delete_post_meta( $venue->ID, 'gatherpress_address' );
		update_post_meta( $venue->ID, 'gatherpress_phone', '(123) 123-1234' );
		update_post_meta( $venue->ID, 'gatherpress_website', 'https://gatherpress.org/' );

		$markup = $this->instance->get_details_markup( $venue );

		$this->assertStringContainsString( 'gatherpress-embed--venue', $markup );
		$this->assertStringNotContainsString( 'gatherpress-embed__address', $markup, 'An empty address gets no row.' );
		$this->assertStringContainsString( '(123) 123-1234', $markup );
		$this->assertStringContainsString( 'https://gatherpress.org/', $markup );
		$this->assertStringNotContainsString( '<a ', $markup, 'The website is plain text.' );

		delete_post_meta( $venue->ID, 'gatherpress_phone' );

		$markup = $this->instance->get_details_markup( $venue );

		$this->assertStringContainsString( 'https://gatherpress.org/', $markup, 'A website alone still renders.' );
		$this->assertStringNotContainsString( 'gatherpress-embed__phone', $markup );
	}

	/**
	 * Prints the markup for the current post.
	 *
	 * @since TBD
	 *
	 * @covers ::render_details
	 *
	 * @return void
	 */
	public function test_render_details(): void {
		$event = $this->create_event( 7 );
		$this->go_to( get_permalink( $event->post->ID ) );

		ob_start();
		$this->instance->render_details();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'gatherpress-embed--event', $output );
	}

	/**
	 * Enqueues the stylesheet only for events and venues.
	 *
	 * @since TBD
	 *
	 * @covers ::enqueue_style
	 *
	 * @return void
	 */
	public function test_enqueue_style(): void {
		$this->go_to( '/?p=' . PHP_INT_MAX );
		$this->assertNull( get_post(), 'A missing post leaves no global post.' );
		$this->instance->enqueue_style();
		$this->assertFalse( wp_style_is( 'gatherpress-embed-style' ), 'No post enqueues nothing.' );

		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$this->go_to( get_permalink( $post->ID ) );
		$this->instance->enqueue_style();
		$this->assertFalse( wp_style_is( 'gatherpress-embed-style' ), 'A regular post enqueues nothing.' );

		$venue = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$this->go_to( get_permalink( $venue->ID ) );
		$this->instance->enqueue_style();
		$this->assertTrue( wp_style_is( 'gatherpress-embed-style' ), 'A venue enqueues the stylesheet.' );

		wp_dequeue_style( 'gatherpress-embed-style' );

		$event = $this->create_event( 7 );
		$this->go_to( get_permalink( $event->post->ID ) );
		$this->instance->enqueue_style();
		$this->assertTrue( wp_style_is( 'gatherpress-embed-style' ), 'An event enqueues the stylesheet.' );
	}
}
