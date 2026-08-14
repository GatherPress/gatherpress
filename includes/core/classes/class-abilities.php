<?php
/**
 * Exposes GatherPress data through the WordPress Abilities API.
 *
 * Registers a small set of read-only abilities so clients that speak the
 * Abilities API — the REST endpoints under `wp-abilities/v1`, WP-CLI, and AI
 * tooling — can ask what is coming up and how many people have replied,
 * without hard-coding GatherPress's post types, meta keys, or comment storage.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Abilities.
 *
 * Registers GatherPress abilities with the WordPress Abilities API.
 *
 * @since 0.36.0
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
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'GatherPress', 'gatherpress' ),
				'description' => __( 'Read event schedules and RSVP tallies managed by GatherPress.', 'gatherpress' ),
			)
		);
	}

	/**
	 * Register the GatherPress abilities.
	 *
	 * Both abilities are read-only and idempotent, so they are safe for a client
	 * to call speculatively.
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
				'permission_callback' => array( $this, 'can_read' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					// `show_in_rest` rather than the `public` flag: `public` is new in
					// WordPress 7.1 and GatherPress still supports 7.0, where it is ignored.
					'show_in_rest' => true,
				),
			)
		);

		wp_register_ability(
			'gatherpress/get-event-rsvp-counts',
			array(
				'label'               => __( 'Get RSVP counts for an event', 'gatherpress' ),
				'description'         => __(
					'Count the responses to one event, broken down by RSVP status.',
					'gatherpress'
				),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'event_id' => array(
							'type'        => 'integer',
							'description' => __( 'The event to count responses for.', 'gatherpress' ),
						),
					),
					'required'   => array( 'event_id' ),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => array( 'type' => 'integer' ),
				),
				'execute_callback'    => array( $this, 'get_event_rsvp_counts' ),
				'permission_callback' => array( $this, 'can_read_event' ),
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
	 * Whether the current user may read site content.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the current user can read.
	 */
	public function can_read(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * Whether the current user may read the requested event.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $input Input passed to the ability.
	 *
	 * @return bool True when the event exists, supports event dates, and its RSVPs are readable.
	 */
	public function can_read_event( $input = null ): bool {
		$post_id = is_array( $input ) ? (int) ( $input['event_id'] ?? 0 ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || ! post_type_supports( $post->post_type, 'gatherpress-event-date' ) ) {
			return false;
		}

		// The ability reports RSVP counts, so it answers to the same rule the roster
		// does rather than to read access on the post alone: a published event still
		// withholds its responses behind a password.
		return Event::can_read_rsvps( $post_id );
	}

	/**
	 * List upcoming events.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $input Input passed to the ability.
	 *
	 * @return array[] Upcoming events, soonest first.
	 */
	public function get_upcoming_events( $input = null ): array {
		$count  = is_array( $input ) ? (int) ( $input['count'] ?? 5 ) : 5;
		$count  = min( max( $count, 1 ), self::MAX_EVENTS );
		$events = array();

		// The event query runs with `fields => ids`, so these are post IDs.
		foreach ( Event_Query::get_instance()->get_upcoming_events( $count )->posts as $post_id ) {
			$post_id  = (int) $post_id;
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

	/**
	 * Count the responses to one event by RSVP status.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $input Input passed to the ability.
	 *
	 * @return int[] Response counts keyed by status, including an `all` total.
	 */
	public function get_event_rsvp_counts( $input = null ): array {
		$post_id = is_array( $input ) ? (int) ( $input['event_id'] ?? 0 ) : 0;
		$counts  = array();

		// The permission callback already rejected anything without a real event,
		// but the ability is a public entry point, so do not build an RSVP object
		// around a post that does not exist.
		if ( ! $post_id ) {
			return $counts;
		}

		foreach ( ( new Rsvp( $post_id ) )->responses() as $status => $response ) {
			$counts[ $status ] = (int) ( $response['count'] ?? 0 );
		}

		return $counts;
	}
}
