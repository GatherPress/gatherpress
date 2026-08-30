<?php
/**
 * Exposes GatherPress events through the WordPress Abilities API.
 *
 * Registers a read-only ability so clients that speak the Abilities API — the
 * REST endpoints under `wp-abilities/v1`, WP-CLI, and AI tooling — can ask what
 * is coming up without hard-coding GatherPress's post types or meta keys.
 *
 * @package GatherPress\Core\Event
 * @since 0.36.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Abilities.
 *
 * Registers the event abilities with the WordPress Abilities API.
 *
 * @since 0.36.0
 *
 * @phpstan-type EventUpcomingEvent array{id:int, title:string, url:string, start:string, end:string, timezone:string}
 */
final class Abilities {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Slug of the ability category GatherPress registers its abilities under.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const CATEGORY = 'gatherpress';

	/**
	 * Largest number of events a single call may ask for.
	 *
	 * @since 0.36.0
	 *
	 * @var int
	 */
	public const MAX_EVENTS = 50;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Register the GatherPress ability category.
	 *
	 * The category is shared with the RSVP abilities, and either subsystem can
	 * be the one that boots first, so both register it and whichever runs
	 * second stands down rather than registering it twice.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function register_category(): void {
		if ( wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'GatherPress', 'gatherpress' ),
				'description' => __( 'Read event schedules and RSVP tallies managed by GatherPress.', 'gatherpress' ),
			)
		);
	}

	/**
	 * Register the event abilities.
	 *
	 * The ability is read-only and idempotent, so it is safe for a client to
	 * call speculatively.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		wp_register_ability(
			'gatherpress/get-upcoming-events',
			array(
				'label'               => __( 'Get upcoming events', 'gatherpress' ),
				'description'         => __(
					'List the events that have not started yet, soonest first.',
					'gatherpress'
				),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'count' => array(
							'type'        => 'integer',
							'description' => __( 'How many events to return.', 'gatherpress' ),
							'minimum'     => 1,
							'maximum'     => self::MAX_EVENTS,
							'default'     => 5,
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'       => array( 'type' => 'integer' ),
							'title'    => array( 'type' => 'string' ),
							'url'      => array( 'type' => 'string' ),
							'start'    => array( 'type' => 'string' ),
							'end'      => array( 'type' => 'string' ),
							'timezone' => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'get_upcoming_events' ),
				// The listing is the event archive in another shape: the query runs
				// unauthenticated and WP_Query hands back only what the current
				// viewer could already read, so the ability adds no gate of its own.
				'permission_callback' => '__return_true',
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * List upcoming events.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $input Input passed to the ability.
	 *
	 * @return array<int, EventUpcomingEvent> Upcoming events, soonest first.
	 */
	public function get_upcoming_events( $input = null ): array {
		$count  = is_array( $input ) ? (int) ( $input['count'] ?? 5 ) : 5;
		$count  = min( max( $count, 1 ), self::MAX_EVENTS );
		$events = array();

		// The event query runs with `fields => ids`, so these are post IDs.
		foreach ( Query::get_instance()->get_upcoming_events( $count )->posts as $post_id ) {
			if ( ! is_int( $post_id ) ) {
				continue;
			}

			$datetime = ( new Event( $post_id ) )->get_datetime();

			$events[] = array(
				'id'       => $post_id,
				'title'    => get_the_title( $post_id ),
				'url'      => (string) get_permalink( $post_id ),
				'start'    => $datetime['datetime_start_gmt'],
				'end'      => $datetime['datetime_end_gmt'],
				'timezone' => $datetime['timezone'],
			);
		}

		return $events;
	}
}
