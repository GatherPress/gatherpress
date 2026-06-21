<?php
/**
 * Handles integration with the WordPress Abilities API.
 *
 * This class registers GatherPress abilities with the Abilities API, allowing
 * AI assistants and other tools to discover and execute GatherPress functionality.
 * The integration is optional and only activates when the Abilities API is available.
 *
 * @package GatherPress\Core\AI
 * @since 1.0.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\AI\Date_Calculator;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Topic;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue;
use WP_Error;

/**
 * Class Abilities_Integration.
 *
 * Registers GatherPress abilities with the WordPress Abilities API for AI and automation tools.
 *
 * @since 1.0.0
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Abilities_Integration {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Date Calculator instance.
	 *
	 * @since 1.0.0
	 * @var Date_Calculator
	 */
	private $date_calculator;

	/**
	 * Venue abilities handler.
	 *
	 * @since 0.34.0
	 * @var Abilities_Venue
	 */
	private $venue;

	/**
	 * Event abilities handler.
	 *
	 * @since 0.34.0
	 * @var Abilities_Event
	 */
	private $event;

	/**
	 * Batch event abilities handler.
	 *
	 * @since 0.34.0
	 * @var Abilities_Event_Batch
	 */
	private $batch;

	/**
	 * Class constructor.
	 *
	 * Initializes the Abilities API integration if the API is available.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		// Only proceed if Abilities API is available.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->date_calculator = new Date_Calculator();
		$this->venue           = new Abilities_Venue();
		$this->event           = new Abilities_Event();
		$this->batch           = new Abilities_Event_Batch( $this->event );
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for registering abilities.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Use priority 999 to register after other plugins (like AI plugin).
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ), 999 );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ), 999 );
	}

	/**
	 * Get the calculate-dates ability name.
	 *
	 * Returns GatherPress's calculate-dates ability name.
	 *
	 * @since 1.0.0
	 *
	 * @return string The ability name ('gatherpress/calculate-dates').
	 */
	public static function get_calculate_dates_ability(): string {
		// Default to GatherPress's own implementation.
		if ( ! function_exists( 'wp_has_ability' ) ) {
			return 'gatherpress/calculate-dates';
		}

		// If external AI plugin's ability is registered, use it.
		if ( wp_has_ability( 'ai/calculate-dates' ) ) {
			return 'ai/calculate-dates';
		}

		return 'gatherpress/calculate-dates';
	}

	/**
	 * Register ability categories.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_categories(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'venue',
			array(
				'label'       => __( 'Venues', 'gatherpress' ),
				'description' => __( 'Abilities related to event venues.', 'gatherpress' ),
			)
		);

		wp_register_ability_category(
			'event',
			array(
				'label'       => __( 'Events', 'gatherpress' ),
				'description' => __( 'Abilities related to events.', 'gatherpress' ),
			)
		);
	}

	/**
	 * Get all GatherPress ability names.
	 *
	 * Returns a list of all ability names that GatherPress registers.
	 * This provides a single source of truth for ability names.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string> Array of ability names.
	 */
	public static function get_all_ability_names(): array {
		return array(
			'gatherpress/list-venues',
			'gatherpress/list-events',
			'gatherpress/list-topics',
			'gatherpress/search-events',
			'gatherpress/calculate-dates',
			'gatherpress/create-venue',
			'gatherpress/create-topic',
			'gatherpress/create-event',
			'gatherpress/update-venue',
			'gatherpress/update-event',
			'gatherpress/update-events-batch',
		);
	}

	/**
	 * Register all GatherPress abilities.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		$this->register_list_venues_ability();
		$this->register_list_events_ability();
		$this->register_list_topics_ability();
		$this->register_search_events_ability();
		$this->register_calculate_dates_ability();
		$this->register_create_venue_ability();
		$this->register_create_topic_ability();
		$this->register_create_event_ability();
		$this->register_update_venue_ability();
		$this->register_update_event_ability();
		$this->register_update_events_batch_ability();
	}

	/**
	 * Register the list-venues ability.
	 *
	 * Allows retrieving all published venues with their details.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_list_venues_ability(): void {
		wp_register_ability(
			'gatherpress/list-venues',
			array(
				'label'               => __( 'List Venues', 'gatherpress' ),
				'description'         => __(
					'Retrieve a list of all available event venues with their addresses and details.',
					'gatherpress'
				),
				'category'            => 'venue',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'execute_callback'    => array( $this, 'execute_list_venues' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'read' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the list-events ability.
	 *
	 * Allows retrieving upcoming events.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_list_events_ability(): void {
		wp_register_ability(
			'gatherpress/list-events',
			array(
				'label'               => __( 'List Events', 'gatherpress' ),
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'description'         => __( 'Retrieve a list of events with their dates, venues, and details. IMPORTANT: When searching for events by name, use the search parameter to find all events (not just upcoming).', 'gatherpress' ),
				'category'            => 'event',
				'execute_callback'    => array( $this, 'execute_list_events' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'read' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'max_number' => array(
							'type'        => 'integer',
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'description' => __( 'Maximum number of events to return (default: 50, maximum: 100). Use -1 or a large number to get more events.', 'gatherpress' ),
							'default'     => 50,
						),
						'search'     => array(
							'type'        => 'string',
							'description' => __(
								'Search term to find specific events by title or content',
								'gatherpress'
							),
						),
					),
					'required'   => array(),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the list-topics ability.
	 *
	 * Allows retrieving all published topics.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_list_topics_ability(): void {
		wp_register_ability(
			'gatherpress/list-topics',
			array(
				'label'               => __( 'List Topics', 'gatherpress' ),
				'description'         => __( 'Retrieve a list of all available event topics.', 'gatherpress' ),
				'category'            => 'event',
				'execute_callback'    => array( $this, 'execute_list_topics' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'read' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the create-venue ability.
	 *
	 * Allows creating a new venue from an address.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_create_venue_ability(): void {
		wp_register_ability(
			'gatherpress/create-venue',
			array(
				'label'               => __( 'Create Venue', 'gatherpress' ),
				'description'         => __( 'Create a new event venue with an address and details.', 'gatherpress' ),
				'category'            => 'venue',
				'execute_callback'    => array( $this->venue, 'execute_create_venue' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array(
							'type'        => 'string',
							'description' => __( 'Name of the venue', 'gatherpress' ),
						),
						'address' => array(
							'type'        => 'string',
							'description' => __( 'Full address of the venue', 'gatherpress' ),
						),
						'phone'   => array(
							'type'        => 'string',
							'description' => __( 'Phone number for the venue', 'gatherpress' ),
						),
						'website' => array(
							'type'        => 'string',
							'description' => __( 'Website URL for the venue', 'gatherpress' ),
						),
					),
					'required'   => array( 'name', 'address' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => false,
					),
				),
			)
		);
	}

	/**
	 * Register the create-topic ability.
	 *
	 * Allows creating a new event topic.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_create_topic_ability(): void {
		wp_register_ability(
			'gatherpress/create-topic',
			array(
				'label'               => __( 'Create Topic', 'gatherpress' ),
				'description'         => __( 'Create a new event topic for categorizing events.', 'gatherpress' ),
				'category'            => 'event',
				'execute_callback'    => array( $this, 'execute_create_topic' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_categories' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'        => array(
							'type'        => 'string',
							'description' => __( 'Name of the topic', 'gatherpress' ),
						),
						'description' => array(
							'type'        => 'string',
							'description' => __( 'Description of the topic', 'gatherpress' ),
						),
						'parent_id'   => array(
							'type'        => 'integer',
							'description' => __( 'Parent topic ID for hierarchical topics', 'gatherpress' ),
						),
					),
					'required'   => array( 'name' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => false,
					),
				),
			)
		);
	}

	/**
	 * Register the create-event ability.
	 *
	 * Allows creating a new event, defaulting to draft status for safety.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_create_event_ability(): void {
		wp_register_ability(
			'gatherpress/create-event',
			array(
				'label'               => __( 'Create Event', 'gatherpress' ),
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'description'         => __( 'Create a new event with a title, date/time, and optional venue. Events are created as drafts by default for review before publishing.', 'gatherpress' ),
				'category'            => 'event',
				'execute_callback'    => array( $this->event, 'execute_create_event' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'          => array(
							'type'        => 'string',
							'description' => __( 'Title of the event', 'gatherpress' ),
						),
						'datetime_start' => array(
							'type'        => 'string',
							'description' => __( 'Event start date and time in Y-m-d H:i:s format', 'gatherpress' ),
						),
						'datetime_end'   => array(
							'type'        => 'string',
							'description' => __( 'Event end date and time in Y-m-d H:i:s format', 'gatherpress' ),
						),
						'venue_id'       => array(
							'type'        => 'integer',
							'description' => __(
								'Venue post ID. Required when linking a venue created in the same conversation.',
								'gatherpress'
							),
						),
						'description'    => array(
							'type'        => 'string',
							'description' => __( 'Event description/content', 'gatherpress' ),
						),
						'post_status'    => array(
							'type'        => 'string',
							'description' => __( 'Post status (draft or publish)', 'gatherpress' ),
							'default'     => 'draft',
							'enum'        => array( 'draft', 'publish' ),
						),
						'topic_ids'      => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( 'Array of topic IDs to assign to this event', 'gatherpress' ),
						),
					),
					'required'   => array( 'title', 'datetime_start' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => false,
					),
				),
			)
		);
	}

	/**
	 * Register the calculate-dates ability.
	 *
	 * Calculates recurring dates based on patterns like "3rd Tuesday" or "every Monday".
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_calculate_dates_ability(): void {
		wp_register_ability(
			'gatherpress/calculate-dates',
			array(
				'label'               => __( 'Calculate Recurring Dates', 'gatherpress' ),
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'description'         => __( 'Calculate recurring dates based on a pattern. Use this BEFORE creating recurring events to get accurate dates. PATTERN TYPES: 1) "Nth weekday" (e.g., "3rd Tuesday", "first Friday") - calculates Nth occurrence of weekday in each month. 2) "Every weekday" (e.g., "every Monday") - calculates weekly recurring dates. 3) "X weeks from weekday" (e.g., "3 weeks from Thursday") - calculates ONE specific date that is X weeks from the next occurrence of that weekday. 4) "Relative dates" (e.g., "this Sunday", "next Tuesday", "tomorrow") - calculates relative dates. IMPORTANT: "this [weekday]" means the upcoming occurrence in the current week (e.g., "this Sunday" on Friday = this week\'s Sunday, not next week\'s). "next [weekday]" means next week\'s occurrence. 5) "Interval patterns" (e.g., "every 2 weeks") - calculates recurring dates at intervals. IMPORTANT: "X weeks from weekday" patterns should ALWAYS use occurrences=1 as they calculate a single specific date, not multiple recurring dates.', 'gatherpress' ),
				'category'            => 'event',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'pattern'     => array(
							'type'        => 'string',
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'description' => __( 'The date pattern to calculate (e.g., "3rd Tuesday", "every Monday", "this Sunday", "next Thursday", "3 weeks from Friday"). IMPORTANT: Use "this [weekday]" for the upcoming occurrence in the current week, not "next [weekday]".', 'gatherpress' ),
						),
						'occurrences' => array(
							'type'        => 'integer',
							'description' => __(
								'Number of dates to calculate (minimum 1)',
								'gatherpress'
							),
							'minimum'     => 1,
						),
						'start_date'  => array(
							'type'        => 'string',
							'format'      => 'date',
							'description' => __(
								'Start date in Y-m-d format (defaults to today)',
								'gatherpress'
							),
						),
					),
					'required'   => array( 'pattern', 'occurrences' ),
				),
				'execute_callback'    => array( $this, 'execute_calculate_dates' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'read' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the update-venue ability.
	 *
	 * Allows updating an existing venue's information.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_update_venue_ability(): void {
		wp_register_ability(
			'gatherpress/update-venue',
			array(
				'label'               => __( 'Update Venue', 'gatherpress' ),
				'description'         => __(
					'Update an existing venue\'s information including name, address, and contact details.',
					'gatherpress'
				),
				'category'            => 'venue',
				'execute_callback'    => array( $this->venue, 'execute_update_venue' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'venue_id'     => array(
							'type'        => 'integer',
							'description' => __( 'ID of the venue to update', 'gatherpress' ),
						),
						'name'         => array(
							'type'        => 'string',
							'description' => __( 'Name of the venue', 'gatherpress' ),
						),
						'address'      => array(
							'type'        => 'string',
							'description' => __( 'Full address of the venue', 'gatherpress' ),
						),
						'phone'        => array(
							'type'        => 'string',
							'description' => __( 'Phone number for the venue', 'gatherpress' ),
						),
						'website'      => array(
							'type'        => 'string',
							'description' => __( 'Website URL for the venue', 'gatherpress' ),
						),
						'thumbnail_id' => array(
							'type'        => 'integer',
							'description' => __(
								'Attachment ID of the image to set as the featured image for this venue',
								'gatherpress'
							),
						),
					),
					'required'   => array( 'venue_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => false,
					),
				),
			)
		);
	}

	/**
	 * Register the update-event ability.
	 *
	 * Allows updating an existing event's information.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function register_update_event_ability(): void {
		wp_register_ability(
			'gatherpress/update-event',
			array(
				'label'               => __( 'Update Event', 'gatherpress' ),
				'description'         => __(
					'Update an existing event\'s details including title, date/time, venue, and description.',
					'gatherpress'
				),
				'category'            => 'event',
				'execute_callback'    => array( $this->event, 'execute_update_event' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'event_id'       => array(
							'type'        => 'integer',
							'description' => __( 'ID of the event to update', 'gatherpress' ),
						),
						'title'          => array(
							'type'        => 'string',
							'description' => __( 'Title of the event', 'gatherpress' ),
						),
						'datetime_start' => array(
							'type'        => 'string',
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'description' => __( 'Event start date and time. Accepts full datetime (Y-m-d H:i:s) or time-only (e.g., "3pm", "15:00", "3:00 PM"). Time-only will merge with existing event date.', 'gatherpress' ),
						),
						'datetime_end'   => array(
							'type'        => 'string',
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'description' => __( 'Event end date and time. Accepts full datetime (Y-m-d H:i:s) or time-only (e.g., "5pm", "17:00", "5:00 PM"). Time-only will merge with existing event date.', 'gatherpress' ),
						),
						'timezone'       => array(
							'type'        => 'string',
							'description' => __(
								'Timezone for datetime parsing (e.g., "America/New_York"). Defaults to site timezone.',
								'gatherpress'
							),
						),
						'venue_id'       => array(
							'type'        => 'integer',
							'description' => __( 'ID of the venue for this event', 'gatherpress' ),
						),
						'description'    => array(
							'type'        => 'string',
							'description' => __( 'Event description/content', 'gatherpress' ),
						),
						'post_status'    => array(
							'type'        => 'string',
							'description' => __( 'Post status (draft or publish)', 'gatherpress' ),
							'enum'        => array( 'draft', 'publish' ),
						),
						'topic_ids'      => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( 'Array of topic IDs to assign to this event', 'gatherpress' ),
						),
						'thumbnail_id'   => array(
							'type'        => 'integer',
							'description' => __(
								'Attachment ID of the image to set as the featured image for this event',
								'gatherpress'
							),
						),
					),
					'required'   => array( 'event_id' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'category' => 'event',
						'safe'     => false,
					),
				),
			)
		);
	}

	/**
	 * Execute the list-venues ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $_params Optional parameters (currently unused).
	 * @return array Response with venue list or error.
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,Squiz.Commenting.FunctionComment.Missing
	public function execute_list_venues( array $_params = array() ): array {
		try {
			$venues = get_posts(
				array(
					'post_type'      => Venue::POST_TYPE,
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			$venue_list = array();

			foreach ( $venues as $venue_post ) {
				$venue_info = ( new Venue( $venue_post->ID ) )->get_information();
				$edit_url   = get_edit_post_link( $venue_post->ID, 'raw' );
				$permalink  = get_permalink( $venue_post->ID );

				$venue_list[] = array(
					'id'        => (int) $venue_post->ID,
					'name'      => ! empty( $venue_post->post_title ) ? $venue_post->post_title : '',
					'address'   => $venue_info['address'],
					'phone'     => $venue_info['phone'],
					'website'   => $venue_info['website'],
					'latitude'  => $venue_info['latitude'],
					'longitude' => $venue_info['longitude'],
					'edit_url'  => ! empty( $edit_url ) ? $edit_url : '',
					'permalink' => ! empty( $permalink ) ? $permalink : '',
				);
			}

			$venue_count = count( $venue_list );

			return array(
				'success' => true,
				'data'    => $venue_list,
				'count'   => $venue_count,
				'message' => sprintf(
					/* translators: %d: number of venues */
					__( 'Found %d venue(s)', 'gatherpress' ),
					$venue_count
				),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'data'    => array(),
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Error retrieving venues: %s', 'gatherpress' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Execute the list-events ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including max_number.
	 * @return array Response with event list or error.
	 */
	public function execute_list_events( array $params = array() ): array {
		$max_number = isset( $params['max_number'] ) ? intval( $params['max_number'] ) : 50;
		// If max_number is -1 or very large, get all events (but cap at 100 for performance).
		if ( $max_number <= 0 || $max_number > 100 ) {
			$max_number = 100;
		}

		// If search term is provided, search all events instead of just upcoming.
		if ( ! empty( $params['search'] ) ) {
			$events = get_posts(
				array(
					'post_type'      => Event::POST_TYPE,
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => $max_number,
					's'              => sanitize_text_field( $params['search'] ),
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);

			$event_list = array();
			foreach ( $events as $event ) {
				$event_obj         = new Event( $event->ID );
				$venue_information = $event_obj->get_venue_information();

				$event_list[] = array(
					'id'             => $event->ID,
					'title'          => get_the_title( $event->ID ),
					'datetime_start' => $event_obj->get_datetime_start( 'F j, Y, g:i a' ),
					'datetime_end'   => $event_obj->get_datetime_end( 'F j, Y, g:i a' ),
					'venue'          => $venue_information['name'] ?? null,
					'permalink'      => get_permalink( $event->ID ),
					'edit_url'       => get_edit_post_link( $event->ID, 'raw' ),
				);
			}

			return array(
				'success' => true,
				'data'    => array(
					'events' => $event_list,
					'count'  => count( $event_list ),
				),
				'message' => sprintf(
					/* translators: %1$d: number of events, %2$s: search term */
					_n(
						'Found %1$d event matching "%2$s".',
						'Found %1$d events matching "%2$s".',
						count( $event_list ),
						'gatherpress'
					),
					count( $event_list ),
					$params['search']
				),
			);
		}

		// Default to searching all events instead of just upcoming.
		$events = get_posts(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => $max_number,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$event_list = array();
		foreach ( $events as $event ) {
			$event_obj         = new Event( $event->ID );
			$venue_information = $event_obj->get_venue_information();

			$event_list[] = array(
				'id'             => $event->ID,
				'title'          => get_the_title( $event->ID ),
				'datetime_start' => $event_obj->get_datetime_start( 'F j, Y, g:i a' ),
				'datetime_end'   => $event_obj->get_datetime_end( 'F j, Y, g:i a' ),
				'venue'          => $venue_information['name'] ?? null,
				'permalink'      => get_permalink( $event->ID ),
				'edit_url'       => get_edit_post_link( $event->ID, 'raw' ),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'events' => $event_list,
				'count'  => count( $event_list ),
			),
			'message' => sprintf(
				/* translators: %d: number of events */
				_n(
					'Found %d event.',
					'Found %d events.',
					count( $event_list ),
					'gatherpress'
				),
				count( $event_list )
			),
		);
	}


	/**
	 * Register the search-events ability.
	 *
	 * Allows searching for events by title or content.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_search_events_ability(): void {
		wp_register_ability(
			'gatherpress/search-events',
			array(
				'label'               => __( 'Search Events', 'gatherpress' ),
				'description'         => __( 'Search for events by title or content.', 'gatherpress' ),
				'category'            => 'event',
				'execute_callback'    => array( $this, 'execute_search_events' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'read' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search_term' => array(
							'type'        => 'string',
							'description' => __( 'Search term to find events by title or content.', 'gatherpress' ),
						),
						'max_number'  => array(
							'type'        => 'integer',
							'description' => __( 'Maximum number of events to return (default: 10).', 'gatherpress' ),
						),
					),
					'required'   => array( 'search_term' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => true,
					),
				),
			)
		);
	}

	/**
	 * Register the update-events-batch ability.
	 *
	 * Allows updating multiple events at once.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_update_events_batch_ability(): void {
		wp_register_ability(
			'gatherpress/update-events-batch',
			array(
				'label'               => __( 'Update Multiple Events', 'gatherpress' ),
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'description'         => __( 'Update multiple events at once based on search criteria. IMPORTANT: When user says "change events from X to Y", this means CHANGE the start time from X to Y. Do NOT search for events currently at X - instead, find all matching events and change their start time to Y. For time ranges "from X to Y", set start time to X and end time to Y.', 'gatherpress' ),
				'category'            => 'event',
				'execute_callback'    => array( $this->batch, 'execute_update_events_batch' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search_term'    => array(
							'type'        => 'string',
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'description' => __( 'Search term to find events to update (searches title and content).', 'gatherpress' ),
						),
						'datetime_start' => array(
							'type'        => 'string',
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'description' => __( 'New start datetime in Y-m-d H:i:s format. For "change from X to Y", this should be the NEW start time (Y). For time ranges "from X to Y", this should be X.', 'gatherpress' ),
						),
						'datetime_end'   => array(
							'type'        => 'string',
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'description' => __( 'New end datetime in Y-m-d H:i:s format. For time ranges "from X to Y", this should be Y.', 'gatherpress' ),
						),
						'venue_id'       => array(
							'type'        => 'integer',
							'description' => __( 'New venue ID to assign to all matching events.', 'gatherpress' ),
						),
					),
					'required'   => array( 'search_term' ),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'safe' => false,
					),
				),
			)
		);
	}

	/**
	 * Execute the search-events ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params The parameters passed to the ability.
	 * @return array The result of the ability execution.
	 */
	public function execute_search_events( array $params ): array {
		// Validate required parameters.
		if ( empty( $params['search_term'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Search term is required.', 'gatherpress' ),
			);
		}

		$max_number = isset( $params['max_number'] ) ? absint( $params['max_number'] ) : 10;

		// Search for events.
		$events = get_posts(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => $max_number,
				's'              => sanitize_text_field( $params['search_term'] ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $events ) ) {
			return array(
				'success' => true,
				'data'    => array(
					'events' => array(),
					'count'  => 0,
				),
				'message' => sprintf(
					/* translators: %s: search term */
					__( 'No events found matching "%s".', 'gatherpress' ),
					$params['search_term']
				),
			);
		}

		$event_data = array();
		foreach ( $events as $event ) {
			$event_obj = new Event( $event->ID );
			$datetime  = $event_obj->get_datetime();

			$event_data[] = array(
				'id'             => $event->ID,
				'title'          => get_the_title( $event->ID ),
				'status'         => $event->post_status,
				'datetime_start' => $event_obj->get_datetime_start( 'F j, Y, g:i a' ),
				'datetime_end'   => $event_obj->get_datetime_end( 'F j, Y, g:i a' ),
				'timezone'       => $datetime['timezone'] ?? '',
				'venue_id'       => $this->event->get_event_venue_id( $event->ID ),
				'edit_url'       => get_edit_post_link( $event->ID, 'raw' ),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'events' => $event_data,
				'count'  => count( $event_data ),
			),
			'message' => sprintf(
				/* translators: %1$d: number of events, %2$s: search term */
				_n(
					'Found %1$d event matching "%2$s".',
					'Found %1$d events matching "%2$s".',
					count( $event_data ),
					'gatherpress'
				),
				count( $event_data ),
				$params['search_term']
			),
		);
	}

	/**
	 * Execute the calculate-dates ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters for date calculation.
	 * @return array Response with calculated dates or error message.
	 */
	public function execute_calculate_dates( array $params = array() ): array {
		// Check if AI plugin's calculate-dates ability is available.
		if ( function_exists( 'wp_execute_ability' ) ) {
			$ai_ability = wp_get_ability( 'ai/calculate-dates' );
			if ( $ai_ability ) {
				// Use AI plugin's ability if available.
				return wp_execute_ability( 'ai/calculate-dates', $params );
			}
		}

		// Fall back to local Date_Calculator.
		return $this->date_calculator->calculate_dates( $params );
	}

	/**
	 * Execute the list-topics ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $_params Optional parameters (currently unused).
	 * @return array List of topics with their IDs and names.
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found,Squiz.Commenting.FunctionComment.Missing
	public function execute_list_topics( array $_params = array() ): array {
		$topics = get_terms(
			array(
				'taxonomy'   => Topic::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $topics ) ) {
			return array(
				'success' => false,
				'message' => $topics->get_error_message(),
			);
		}

		$topic_list = array();
		foreach ( $topics as $topic ) {
			$topic_list[] = array(
				'id'          => $topic->term_id,
				'name'        => $topic->name,
				'slug'        => $topic->slug,
				'description' => $topic->description,
				'parent'      => $topic->parent,
			);
		}

		return array(
			'success' => true,
			'data'    => $topic_list,
			'message' => sprintf(
				/* translators: %d: number of topics */
				_n(
					'Found %d topic',
					'Found %d topics',
					count( $topic_list ),
					'gatherpress'
				),
				count( $topic_list )
			),
		);
	}

	/**
	 * Execute the create-topic ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including name, description, and parent_id.
	 * @return array Result with topic ID or error.
	 */
	public function execute_create_topic( array $params = array() ): array {
		// Validate required parameters.
		if ( empty( $params['name'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Topic name is required.', 'gatherpress' ),
			);
		}

		$args = array(
			'description' => ! empty( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
		);

		if ( ! empty( $params['parent_id'] ) ) {
			$args['parent'] = intval( $params['parent_id'] );
		}

		$result = wp_insert_term(
			sanitize_text_field( $params['name'] ),
			Topic::TAXONOMY,
			$args
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		$topic    = get_term( $result['term_id'], Topic::TAXONOMY );
		$edit_url = get_edit_term_link( $result['term_id'], Topic::TAXONOMY );

		return array(
			'success'  => true,
			'topic_id' => $result['term_id'],
			'name'     => $topic->name,
			'edit_url' => $edit_url,
			'message'  => sprintf(
				/* translators: %s: topic name */
				__( 'Topic "%s" created successfully.', 'gatherpress' ),
				$topic->name
			),
		);
	}
}
