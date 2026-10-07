<?php
/**
 * Test class for Event Abilities.
 *
 * @package GatherPress\Tests\Core\Event
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event;

use DateTime;
use GatherPress\Core\Event\Abilities;
use GatherPress\Core\Event\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities.
 *
 * @since 0.36.0
 *
 * @coversDefaultClass \GatherPress\Core\Event\Abilities
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
	 * Coverage for register_category when the RSVP side already registered it.
	 *
	 * @covers ::register_category
	 *
	 * @return void
	 */
	public function test_register_category_stands_down_when_already_registered(): void {
		wp_unregister_ability_category( Abilities::CATEGORY );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_categories_init' );

		// The sibling class listens on the same action, and a second pass runs
		// over a populated registry. Either would be a duplicate registration,
		// which the Abilities API reports as incorrect usage.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_categories_init' );

		$this->assertNotNull(
			wp_get_ability_category( Abilities::CATEGORY ),
			'Failed to assert that the category survives a second registration pass.'
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
		// The plugin already registered these while WordPress booted, so clear
		// them first: re-firing the action over a populated registry is a
		// duplicate registration, which the Abilities API rightly reports as
		// incorrect usage.
		wp_unregister_ability( 'gatherpress/get-upcoming-events' );
		wp_unregister_ability( 'gatherpress/get-rsvp-counts' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_init' );

		$events = wp_get_ability( 'gatherpress/get-upcoming-events' );

		$this->assertNotNull( $events, 'Failed to assert that the upcoming events ability is registered.' );

		$meta = $events->get_meta();

		$this->assertTrue(
			$meta['show_in_rest'],
			'Failed to assert that the upcoming events ability is exposed over REST.'
		);
		$this->assertTrue(
			$meta['public'],
			'Failed to assert that the upcoming events ability is public.'
		);
		$this->assertTrue(
			$meta['mcp']['public'],
			'Failed to assert that the upcoming events ability is exposed to MCP.'
		);
		$this->assertSame(
			'tool',
			$meta['mcp']['type'],
			'Failed to assert that the upcoming events ability MCP type is tool.'
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
	 * The upcoming events ability is readable without an account.
	 *
	 * @covers ::register_abilities
	 *
	 * @return void
	 */
	public function test_upcoming_events_is_readable_when_logged_out(): void {
		wp_unregister_ability( 'gatherpress/get-upcoming-events' );
		wp_unregister_ability( 'gatherpress/get-rsvp-counts' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, not a GatherPress one.
		do_action( 'wp_abilities_api_init' );

		wp_set_current_user( 0 );

		$this->assertTrue(
			wp_get_ability( 'gatherpress/get-upcoming-events' )->check_permissions(),
			'Failed to assert that a logged out visitor may list upcoming events.'
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
	 * Test that get_upcoming_events excludes past events and only returns future ones.
	 *
	 * @covers ::get_upcoming_events
	 *
	 * @return void
	 */
	public function test_get_upcoming_events_excludes_past_events(): void {
		$instance = Abilities::get_instance();

		$past_post  = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_title'  => 'Past Meetup',
				'post_status' => 'publish',
			)
		)->get();
		$past_event = new Event( $past_post->ID );
		$past_date  = new DateTime( '3 days ago' );
		$past_event->save_datetimes(
			array(
				'datetime_start' => $past_date->format( 'Y-m-d H:i:s' ),
				'datetime_end'   => $past_date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
				'timezone'       => 'UTC',
			)
		);

		$future_post  = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_title'  => 'Future Meetup',
				'post_status' => 'publish',
			)
		)->get();
		$future_event = new Event( $future_post->ID );
		$future_date  = new DateTime( '+3 days' );
		$future_event->save_datetimes(
			array(
				'datetime_start' => $future_date->format( 'Y-m-d H:i:s' ),
				'datetime_end'   => $future_date->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
				'timezone'       => 'UTC',
			)
		);

		$events = $instance->get_upcoming_events( array( 'count' => 10 ) );

		$this->assertCount( 1, $events, 'Failed to assert that past events are excluded.' );
		$this->assertSame( $future_post->ID, $events[0]['id'], 'Failed to assert that the future event is returned.' );
		$this->assertSame( 'Future Meetup', $events[0]['title'], 'Failed to assert the event title.' );
	}

	/**
	 * Test that get_upcoming_events returns events ordered soonest first.
	 *
	 * @covers ::get_upcoming_events
	 *
	 * @return void
	 */
	public function test_get_upcoming_events_orders_chronologically(): void {
		$instance = Abilities::get_instance();

		$later_post  = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_title'  => 'Later Meetup',
				'post_status' => 'publish',
			)
		)->get();
		$later_event = new Event( $later_post->ID );
		$later_date  = new DateTime( '+5 days' );
		$later_event->save_datetimes(
			array(
				'datetime_start' => $later_date->format( 'Y-m-d H:i:s' ),
				'datetime_end'   => $later_date->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
				'timezone'       => 'UTC',
			)
		);

		$sooner_post  = $this->mock->post(
			array(
				'post_type'   => 'gatherpress_event',
				'post_title'  => 'Sooner Meetup',
				'post_status' => 'publish',
			)
		)->get();
		$sooner_event = new Event( $sooner_post->ID );
		$sooner_date  = new DateTime( '+1 day' );
		$sooner_event->save_datetimes(
			array(
				'datetime_start' => $sooner_date->format( 'Y-m-d H:i:s' ),
				'datetime_end'   => $sooner_date->modify( '+1 day' )->format( 'Y-m-d H:i:s' ),
				'timezone'       => 'UTC',
			)
		);

		$events = $instance->get_upcoming_events( array( 'count' => 10 ) );

		$this->assertCount( 2, $events, 'Failed to assert that both upcoming events are returned.' );
		$this->assertSame(
			$sooner_post->ID,
			$events[0]['id'],
			'Failed to assert that the sooner event is listed first.'
		);
		$this->assertSame(
			$later_post->ID,
			$events[1]['id'],
			'Failed to assert that the later event is listed second.'
		);
	}

	/**
	 * Test that get_upcoming_events respects the count parameter when multiple events exist.
	 *
	 * @covers ::get_upcoming_events
	 *
	 * @return void
	 */
	public function test_get_upcoming_events_respects_count_limit(): void {
		$instance = Abilities::get_instance();

		for ( $i = 1; $i <= 3; $i++ ) {
			$post  = $this->mock->post(
				array(
					'post_type'   => 'gatherpress_event',
					'post_title'  => "Meetup {$i}",
					'post_status' => 'publish',
				)
			)->get();
			$event = new Event( $post->ID );
			$date  = new DateTime( "+{$i} days" );
			$event->save_datetimes(
				array(
					'datetime_start' => $date->format( 'Y-m-d H:i:s' ),
					'datetime_end'   => $date->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
					'timezone'       => 'UTC',
				)
			);
		}

		$events = $instance->get_upcoming_events( array( 'count' => 2 ) );

		$this->assertCount( 2, $events, 'Failed to assert that count limits the number of events returned.' );
	}

	/**
	 * Test that ability schemas define expected input and output structures.
	 *
	 * @covers ::register_abilities
	 *
	 * @return void
	 */
	public function test_register_abilities_schema_definitions(): void {
		$ability = wp_get_ability( 'gatherpress/get-upcoming-events' );

		$this->assertNotNull( $ability, 'Failed to assert that upcoming events ability exists.' );

		$input_schema  = $ability->get_input_schema();
		$output_schema = $ability->get_output_schema();

		$this->assertSame( 'object', $input_schema['type'], 'Failed to assert input schema type is object.' );
		$this->assertArrayHasKey(
			'count',
			$input_schema['properties'],
			'Failed to assert count property in input schema.'
		);
		$this->assertSame(
			1,
			$input_schema['properties']['count']['minimum'],
			'Failed to assert minimum count is 1.'
		);
		$this->assertSame(
			Abilities::MAX_EVENTS,
			$input_schema['properties']['count']['maximum'],
			'Failed to assert maximum count is MAX_EVENTS.'
		);
		$this->assertSame( 5, $input_schema['properties']['count']['default'], 'Failed to assert default count is 5.' );

		$this->assertSame( 'array', $output_schema['type'], 'Failed to assert output schema type is array.' );
		$this->assertArrayHasKey( 'items', $output_schema, 'Failed to assert items key in output schema.' );
		$item_props = $output_schema['items']['properties'];
		$this->assertArrayHasKey( 'id', $item_props, 'Failed to assert id in output item properties.' );
		$this->assertArrayHasKey( 'title', $item_props, 'Failed to assert title in output item properties.' );
		$this->assertArrayHasKey( 'url', $item_props, 'Failed to assert url in output item properties.' );
		$this->assertArrayHasKey( 'start', $item_props, 'Failed to assert start in output item properties.' );
		$this->assertArrayHasKey( 'end', $item_props, 'Failed to assert end in output item properties.' );
		$this->assertArrayHasKey( 'timezone', $item_props, 'Failed to assert timezone in output item properties.' );
	}
}
