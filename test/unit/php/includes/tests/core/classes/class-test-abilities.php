<?php
/**
 * Test class for Abilities.
 *
 * @package GatherPress\Tests\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core;

use DateTime;
use GatherPress\Core\Abilities;
use GatherPress\Core\Event\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities.
 *
 * @since 0.36.0
 *
 * @coversDefaultClass \GatherPress\Core\Abilities
 */
class Test_Abilities extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Abilities::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_categories_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_category' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_abilities_api_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_abilities' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_category.
	 *
	 * @covers ::register_category
	 *
	 * @return void
	 */
	public function test_register_category(): void {
		wp_unregister_ability_category( Abilities::CATEGORY );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_categories_init' );

		$category = wp_get_ability_category( Abilities::CATEGORY );

		$this->assertNotNull( $category, 'Failed to assert that the GatherPress ability category is registered.' );
		$this->assertSame(
			'GatherPress',
			$category->get_label(),
			'Failed to assert the ability category label.'
		);
	}

	/**
	 * Coverage for register_abilities.
	 *
	 * @covers ::register_abilities
	 *
	 * @return void
	 */
	public function test_register_abilities(): void {
		// The plugin already registered these while WordPress booted, so clear them
		// first: re-firing the action over a populated registry is a duplicate
		// registration, which the Abilities API rightly reports as incorrect usage.
		wp_unregister_ability( 'gatherpress/get-upcoming-events' );
		wp_unregister_ability( 'gatherpress/get-event-rsvp-counts' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_init' );

		$events = wp_get_ability( 'gatherpress/get-upcoming-events' );
		$counts = wp_get_ability( 'gatherpress/get-event-rsvp-counts' );

		$this->assertNotNull( $events, 'Failed to assert that the upcoming events ability is registered.' );
		$this->assertNotNull( $counts, 'Failed to assert that the RSVP counts ability is registered.' );

		$meta = $events->get_meta();

		$this->assertTrue(
			$meta['show_in_rest'],
			'Failed to assert that the upcoming events ability is exposed over REST.'
		);
		$this->assertTrue(
			$meta['annotations']['readonly'],
			'Failed to assert that the upcoming events ability is annotated read-only.'
		);
		$this->assertSame(
			Abilities::CATEGORY,
			$events->get_category(),
			'Failed to assert the ability category.'
		);
	}

	/**
	 * Coverage for can_read.
	 *
	 * @covers ::can_read
	 *
	 * @return void
	 */
	public function test_can_read(): void {
		$instance = Abilities::get_instance();

		wp_set_current_user( 0 );

		$this->assertFalse(
			$instance->can_read(),
			'Failed to assert that a logged out user cannot read.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertTrue(
			$instance->can_read(),
			'Failed to assert that a subscriber can read.'
		);
	}

	/**
	 * Coverage for can_read_event with input that names no event.
	 *
	 * @covers ::can_read_event
	 *
	 * @return void
	 */
	public function test_can_read_event_rejects_input_without_an_event(): void {
		$instance = Abilities::get_instance();

		$this->assertFalse(
			$instance->can_read_event(),
			'Failed to assert that a null input is rejected.'
		);
		$this->assertFalse(
			$instance->can_read_event( 'not-an-array' ),
			'Failed to assert that a non-array input is rejected.'
		);
		$this->assertFalse(
			$instance->can_read_event( array() ),
			'Failed to assert that input without an event_id is rejected.'
		);
		$this->assertFalse(
			$instance->can_read_event( array( 'event_id' => PHP_INT_MAX ) ),
			'Failed to assert that a non-existent post is rejected.'
		);
	}

	/**
	 * Coverage for can_read_event against a post that is not an event.
	 *
	 * @covers ::can_read_event
	 *
	 * @return void
	 */
	public function test_can_read_event_rejects_a_non_event_post(): void {
		$instance = Abilities::get_instance();
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertFalse(
			$instance->can_read_event( array( 'event_id' => $post->ID ) ),
			'Failed to assert that a post without event date support is rejected.'
		);
	}

	/**
	 * Coverage for can_read_event against a readable event.
	 *
	 * @covers ::can_read_event
	 *
	 * @return void
	 */
	public function test_can_read_event_allows_a_readable_event(): void {
		$instance = Abilities::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_status' => 'publish',
			)
		)->get();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue(
			$instance->can_read_event( array( 'event_id' => $post->ID ) ),
			'Failed to assert that a published event is readable.'
		);
	}

	/**
	 * Coverage for get_upcoming_events when nothing is scheduled.
	 *
	 * @covers ::get_upcoming_events
	 *
	 * @return void
	 */
	public function test_get_upcoming_events_returns_empty_without_events(): void {
		$instance = Abilities::get_instance();

		$this->assertSame(
			array(),
			$instance->get_upcoming_events(),
			'Failed to assert that no events yields an empty list.'
		);
	}

	/**
	 * Coverage for get_upcoming_events with a scheduled event.
	 *
	 * @covers ::get_upcoming_events
	 *
	 * @return void
	 */
	public function test_get_upcoming_events_describes_the_event(): void {
		$instance = Abilities::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_title'  => 'Unit Test Meetup',
				'post_status' => 'publish',
			)
		)->get();
		$event    = new Event( $post->ID );
		$date     = new DateTime( 'tomorrow' );

		$event->save_datetimes(
			array(
				'datetime_start' => $date->format( 'Y-m-d H:i:s' ),
				'datetime_end'   => $date->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
				'timezone'       => 'America/New_York',
			)
		);

		$events = $instance->get_upcoming_events( array( 'count' => 1 ) );

		$this->assertCount( 1, $events, 'Failed to assert that one upcoming event is returned.' );
		$this->assertSame( $post->ID, $events[0]['id'], 'Failed to assert the event ID.' );
		$this->assertSame( 'Unit Test Meetup', $events[0]['title'], 'Failed to assert the event title.' );
		$this->assertSame(
			get_permalink( $post ),
			$events[0]['url'],
			'Failed to assert the event URL.'
		);
		$this->assertNotEmpty( $events[0]['start'], 'Failed to assert that a start datetime is present.' );
		$this->assertNotEmpty( $events[0]['timezone'], 'Failed to assert that a timezone is present.' );
	}

	/**
	 * Coverage for the count clamp in get_upcoming_events.
	 *
	 * @covers ::get_upcoming_events
	 *
	 * @return void
	 */
	public function test_get_upcoming_events_clamps_the_count(): void {
		$instance = Abilities::get_instance();

		// Both branches of the clamp, plus the non-array input path. An out of
		// range count must not reach the query as-is.
		$this->assertSame(
			array(),
			$instance->get_upcoming_events( array( 'count' => 0 ) ),
			'Failed to assert that a count below the minimum is handled.'
		);
		$this->assertSame(
			array(),
			$instance->get_upcoming_events( array( 'count' => PHP_INT_MAX ) ),
			'Failed to assert that a count above the maximum is handled.'
		);
		$this->assertSame(
			array(),
			$instance->get_upcoming_events( 'not-an-array' ),
			'Failed to assert that a non-array input falls back to the default.'
		);
	}

	/**
	 * Coverage for get_event_rsvp_counts without a usable event.
	 *
	 * @covers ::get_event_rsvp_counts
	 *
	 * @return void
	 */
	public function test_get_event_rsvp_counts_returns_empty_without_an_event(): void {
		$instance = Abilities::get_instance();

		$this->assertSame(
			array(),
			$instance->get_event_rsvp_counts(),
			'Failed to assert that a null input yields no counts.'
		);
		$this->assertSame(
			array(),
			$instance->get_event_rsvp_counts( array( 'event_id' => 0 ) ),
			'Failed to assert that a zero event ID yields no counts.'
		);
	}

	/**
	 * Coverage for get_event_rsvp_counts against a real event.
	 *
	 * @covers ::get_event_rsvp_counts
	 *
	 * @return void
	 */
	public function test_get_event_rsvp_counts_counts_responses(): void {
		$instance = Abilities::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_status' => 'publish',
			)
		)->get();

		$counts = $instance->get_event_rsvp_counts( array( 'event_id' => $post->ID ) );

		$this->assertArrayHasKey( 'all', $counts, 'Failed to assert that a total is present.' );
		$this->assertArrayHasKey( 'attending', $counts, 'Failed to assert that attending is counted.' );
		$this->assertIsInt( $counts['attending'], 'Failed to assert that counts are integers.' );
		$this->assertSame( 0, $counts['attending'], 'Failed to assert that a fresh event has no attendees.' );
	}
}
