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
use GatherPress\Core\AI\Event_Datetime_Parser;
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
				'execute_callback'    => array( $this, 'execute_create_venue' ),
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
				'execute_callback'    => array( $this, 'execute_create_event' ),
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
							'description' => __( 'ID of the venue for this event', 'gatherpress' ),
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
				'execute_callback'    => array( $this, 'execute_update_venue' ),
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
				'execute_callback'    => array( $this, 'execute_update_event' ),
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
				$venue_info_json = get_post_meta( $venue_post->ID, 'gatherpress_venue_information', true );
				$venue_info      = json_decode( $venue_info_json, true );

				$edit_url  = get_edit_post_link( $venue_post->ID, 'raw' );
				$permalink = get_permalink( $venue_post->ID );

				$venue_list[] = array(
					'id'        => (int) $venue_post->ID,
					'name'      => ! empty( $venue_post->post_title ) ? $venue_post->post_title : '',
					'address'   => $venue_info['fullAddress'] ?? '',
					'phone'     => $venue_info['phoneNumber'] ?? '',
					'website'   => $venue_info['website'] ?? '',
					'latitude'  => $venue_info['latitude'] ?? '',
					'longitude' => $venue_info['longitude'] ?? '',
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
	 * Execute the create-venue ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including name, address, phone, website.
	 * @return array Response with created venue ID or error.
	 */
	public function execute_create_venue( array $params ): array {
		// Validate required parameters.
		if ( empty( $params['name'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Venue name is required.', 'gatherpress' ),
			);
		}

		if ( empty( $params['address'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Venue address is required.', 'gatherpress' ),
			);
		}

		// Create the venue post with default block template.
		/**
		 * Venue post ID or WP_Error on failure.
		 *
		 * @var int|WP_Error $venue_id
		 */
		$venue_id = wp_insert_post(
			array(
				'post_type'    => Venue::POST_TYPE,
				'post_title'   => sanitize_text_field( $params['name'] ),
				'post_content' => '<!-- wp:pattern {"slug":"gatherpress/venue-template"} /-->',
				'post_status'  => 'publish',
			)
		);

		if ( is_wp_error( $venue_id ) ) {
			return array(
				'success' => false,
				'message' => $venue_id->get_error_message(),
			);
		}

		// Geocode the address to get latitude and longitude.
		$coordinates = $this->geocode_address( $params['address'] );

		// Prepare venue information.
		$venue_info = array(
			'fullAddress' => sanitize_text_field( $params['address'] ),
			'phoneNumber' => isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '',
			'website'     => isset( $params['website'] ) ? esc_url_raw( $params['website'] ) : '',
			'latitude'    => $coordinates['latitude'],
			'longitude'   => $coordinates['longitude'],
		);

		// Save venue information as post meta.
		update_post_meta( $venue_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		return array(
			'success'  => true,
			'venue_id' => $venue_id,
			'edit_url' => get_edit_post_link( $venue_id, 'raw' ),
			'message'  => sprintf(
				/* translators: %s: venue name */
				__( 'Venue "%s" created successfully.', 'gatherpress' ),
				$params['name']
			),
		);
	}

	/**
	 * Execute the create-event ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including title, datetime_start, venue_id, etc.
	 * @return array Response with created event ID or error.
	 */
	public function execute_create_event( array $params ): array {
		// Validate required parameters.
		if ( empty( $params['title'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Event title is required.', 'gatherpress' ),
			);
		}

		if ( empty( $params['datetime_start'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Event start date/time is required.', 'gatherpress' ),
			);
		}

		// Parse datetime - accept both strict format and flexible formats (e.g., "sunday at 1 pm").
		$parser = new Event_Datetime_Parser();
		try {
			$start_datetime = $parser->parse_datetime_input( $params['datetime_start'] );
		} catch ( \Exception $e ) {
			// Fallback to strict format parsing for backward compatibility.
			$start_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $params['datetime_start'] );
			if ( ! $start_datetime ) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Invalid start date/time format: %s', 'gatherpress' ),
						$e->getMessage()
					),
				);
			}
		}

		// Default post status to draft for safety.
		$post_status = isset( $params['post_status'] ) ? $params['post_status'] : 'draft';
		if ( ! in_array( $post_status, array( 'draft', 'publish' ), true ) ) {
			$post_status = 'draft';
		}

		// Create the event post with the proper template content.
		$post_content = $this->get_default_event_content( $params['description'] ?? '' );

		/**
		 * Event post ID or WP_Error on failure.
		 *
		 * @var int|WP_Error $event_id
		 */
		$event_id = wp_insert_post(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => sanitize_text_field( $params['title'] ),
				'post_content' => $post_content,
				'post_status'  => $post_status,
			)
		);

		if ( is_wp_error( $event_id ) ) {
			return array(
				'success' => false,
				'message' => $event_id->get_error_message(),
			);
		}

		// Calculate end datetime if not provided (default to 2 hours after start).
		if ( ! empty( $params['datetime_end'] ) ) {
			try {
				$end_datetime = $parser->parse_datetime_input( $params['datetime_end'] );
			} catch ( \Exception $e ) {
				// Fallback to strict format parsing.
				$end_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $params['datetime_end'] );
				if ( ! $end_datetime ) {
					$end_datetime = clone $start_datetime;
					$end_datetime->modify( '+2 hours' );
				}
			}
		} else {
			$end_datetime = clone $start_datetime;
			$end_datetime->modify( '+2 hours' );
		}

		// Get timezone (default to WordPress timezone).
		$timezone_string = get_option( 'timezone_string', 'UTC' );

		// Save event datetime using the Event class method.
		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => $start_datetime->format( 'Y-m-d H:i:s' ),
				'datetime_end'   => $end_datetime->format( 'Y-m-d H:i:s' ),
				'timezone'       => $timezone_string,
			)
		);

		// Associate venue if provided.
		if ( ! empty( $params['venue_id'] ) ) {
			$venue_id = intval( $params['venue_id'] );

			// Verify venue exists.
			$venue = get_post( $venue_id );
			if ( $venue && Venue::POST_TYPE === $venue->post_type ) {
				// Get the venue term slug.
				$venue_slug = '_' . $venue->post_name;
				wp_set_object_terms( $event_id, $venue_slug, Venue::TAXONOMY );
			}
		}

		// Associate topics if provided.
		if ( ! empty( $params['topic_ids'] ) && is_array( $params['topic_ids'] ) ) {
			$topic_ids = array_map( 'intval', $params['topic_ids'] );
			wp_set_object_terms( $event_id, $topic_ids, Topic::TAXONOMY );
		}

		return array(
			'success'     => true,
			'event_id'    => $event_id,
			'post_status' => $post_status,
			'edit_url'    => get_edit_post_link( $event_id, 'raw' ),
			'message'     => sprintf(
				/* translators: 1: event title, 2: post status */
				__( 'Event "%1$s" created as %2$s.', 'gatherpress' ),
				$params['title'],
				$post_status
			),
		);
	}

	/**
	 * Execute the update-venue ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including venue_id and fields to update.
	 * @return array Response with success status or error.
	 */
	public function execute_update_venue( array $params ): array {
		// Validate required parameters.
		if ( empty( $params['venue_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Venue ID is required.', 'gatherpress' ),
			);
		}

		$venue_id = intval( $params['venue_id'] );

		// Verify venue exists.
		$venue = get_post( $venue_id );
		if ( ! $venue || Venue::POST_TYPE !== $venue->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Venue not found.', 'gatherpress' ),
			);
		}

		// Update post title if provided.
		if ( isset( $params['name'] ) ) {
			wp_update_post(
				array(
					'ID'         => $venue_id,
					'post_title' => sanitize_text_field( $params['name'] ),
				)
			);
		}

		// Get existing venue information.
		$venue_info_json = get_post_meta( $venue_id, 'gatherpress_venue_information', true );
		$venue_info      = json_decode( $venue_info_json, true );
		if ( ! is_array( $venue_info ) ) {
			$venue_info = array();
		}

		// Update venue information fields.
		if ( isset( $params['address'] ) ) {
			$venue_info['fullAddress'] = sanitize_text_field( $params['address'] );
		}
		if ( isset( $params['phone'] ) ) {
			$venue_info['phoneNumber'] = sanitize_text_field( $params['phone'] );
		}
		if ( isset( $params['website'] ) ) {
			$venue_info['website'] = esc_url_raw( $params['website'] );
		}

		// Save updated venue information.
		update_post_meta( $venue_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		// Update featured image if provided.
		if ( isset( $params['thumbnail_id'] ) ) {
			$thumbnail_id = intval( $params['thumbnail_id'] );
			// Verify attachment exists and is an image.
			$attachment = get_post( $thumbnail_id );
			if ( $attachment && 'attachment' === $attachment->post_type && wp_attachment_is_image( $thumbnail_id ) ) {
				$thumbnail_result = set_post_thumbnail( $venue_id, $thumbnail_id );
				if ( ! $thumbnail_result ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'GatherPress AI: Failed to set thumbnail ' . $thumbnail_id . ' for venue ' . $venue_id );
				}
			}
		}

		return array(
			'success'  => true,
			'venue_id' => $venue_id,
			'edit_url' => get_edit_post_link( $venue_id, 'raw' ),
			'message'  => sprintf(
				/* translators: %s: venue name */
				__( 'Venue "%s" updated successfully.', 'gatherpress' ),
				get_the_title( $venue_id )
			),
		);
	}

	/**
	 * Execute the update-event ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including event_id and fields to update.
	 * @return array Response with success status or error.
	 */
	public function execute_update_event( array $params ): array {
		// Validate event ID and get event post.
		$validation_result = $this->validate_event_for_update( $params );
		if ( isset( $validation_result['success'] ) && false === $validation_result['success'] ) {
			return $validation_result;
		}

		$event_id   = $validation_result['event_id'];
		$event_post = $validation_result['event_post'];

		// Update post fields (title, description, status).
		$post_update_result = $this->update_event_post_fields( $event_id, $event_post, $params );
		if ( isset( $post_update_result['success'] ) && false === $post_update_result['success'] ) {
			return $post_update_result;
		}

		// Update datetime if provided.
		$datetime_result = $this->update_event_datetime_fields( $event_id, $params );
		if ( isset( $datetime_result['success'] ) && false === $datetime_result['success'] ) {
			return $datetime_result;
		}

		// Update venue, topics, and thumbnail.
		$this->update_event_venue( $event_id, $params );
		$this->update_event_topics( $event_id, $params );
		$this->update_event_thumbnail( $event_id, $params );

		// Get updated datetime for response.
		$cache_key = sprintf( Event::DATETIME_CACHE_KEY, $event_id );
		delete_transient( $cache_key );
		$event            = new Event( $event_id );
		$updated_datetime = $event->get_datetime();

		return array(
			'success'     => true,
			'event_id'    => $event_id,
			'post_status' => get_post_status( $event_id ),
			'edit_url'    => get_edit_post_link( $event_id, 'raw' ),
			'datetime'    => $updated_datetime,
			'message'     => sprintf(
				/* translators: %s: event title */
				__( 'Event "%s" updated successfully.', 'gatherpress' ),
				get_the_title( $event_id )
			),
		);
	}

	/**
	 * Validate event ID and retrieve event post for update operations.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including event_id.
	 * @return array Validation result with event_id and event_post, or error.
	 */
	private function validate_event_for_update( array $params ): array {
		if ( empty( $params['event_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Event ID is required.', 'gatherpress' ),
			);
		}

		$event_id   = intval( $params['event_id'] );
		$event_post = get_post( $event_id );

		if ( ! $event_post || Event::POST_TYPE !== $event_post->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Event not found.', 'gatherpress' ),
			);
		}

		return array(
			'event_id'   => $event_id,
			'event_post' => $event_post,
		);
	}

	/**
	 * Update event post fields (title, description, status).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $event_id   Event post ID.
	 * @param object $event_post Event post object.
	 * @param array  $params     Update parameters.
	 * @return array Success or error result.
	 */
	private function update_event_post_fields( int $event_id, $event_post, array $params ): array {
		$post_update = array( 'ID' => $event_id );

		if ( isset( $params['title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( $params['title'] );
		}

		if ( isset( $params['description'] ) ) {
			$post_update['post_content'] = $this->update_event_description(
				$event_post->post_content,
				$params['description']
			);
		}

		if (
			isset( $params['post_status'] ) &&
			in_array( $params['post_status'], array( 'draft', 'publish' ), true )
		) {
			$post_update['post_status'] = $params['post_status'];
		}

		if ( count( $post_update ) > 1 ) {
			/**
			 * Result of wp_update_post - can be int (post ID) or WP_Error.
			 *
			 * @var int|WP_Error $result
			 */
			$result = wp_update_post( $post_update );
			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'message' => $result->get_error_message(),
				);
			}
		}

		return array( 'success' => true );
	}

	/**
	 * Update event datetime fields.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Update parameters.
	 * @return array Success or error result.
	 */
	private function update_event_datetime_fields( int $event_id, array $params ): array {
		$event     = new Event( $event_id );
		$cache_key = sprintf( Event::DATETIME_CACHE_KEY, $event_id );
		delete_transient( $cache_key );
		$existing_datetime = $event->get_datetime();

		// Validate time-only updates.
		$time_only_validation = $this->validate_time_only_datetime_update( $params, $existing_datetime );
		if ( isset( $time_only_validation['success'] ) && false === $time_only_validation['success'] ) {
			return $time_only_validation;
		}

		// Prepare and apply datetime updates.
		$new_datetimes = $this->prepare_datetime_updates( $params );
		if ( ! empty( $new_datetimes ) ) {
			try {
				$parser          = new Event_Datetime_Parser();
				$datetime_params = $parser->prepare_datetime_params( $new_datetimes, $existing_datetime );
				$event->save_datetimes( $datetime_params );
			} catch ( \Exception $e ) {
				return array(
					'success' => false,
					'message' => $e->getMessage(),
				);
			}
		}

		return array( 'success' => true );
	}

	/**
	 * Validate time-only datetime updates.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params            Update parameters.
	 * @param array $existing_datetime Existing datetime data.
	 * @return array Validation result or empty array if valid.
	 */
	private function validate_time_only_datetime_update( array $params, array $existing_datetime ): array {
		$is_time_only_start = isset( $params['datetime_start'] )
			&& ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $params['datetime_start'] );
		$is_time_only_end   = isset( $params['datetime_end'] )
			&& ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $params['datetime_end'] );

		$has_existing_datetime = ! empty( $existing_datetime['datetime_start'] )
			|| ! empty( $existing_datetime['datetime_end'] )
			|| ! empty( $existing_datetime['datetime_start_gmt'] )
			|| ! empty( $existing_datetime['datetime_end_gmt'] );

		if ( ( $is_time_only_start || $is_time_only_end ) && ! $has_existing_datetime ) {
			return array(
				'success' => false,
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'message' => __( 'Cannot update time-only without an existing event date. Please provide a full datetime (e.g., "2025-01-04 15:00:00") or create the event with a date first.', 'gatherpress' ),
			);
		}

		return array();
	}

	/**
	 * Prepare datetime updates array from parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Update parameters.
	 * @return array Datetime updates array.
	 */
	private function prepare_datetime_updates( array $params ): array {
		$new_datetimes = array();

		if ( isset( $params['datetime_start'] ) ) {
			$new_datetimes['datetime_start'] = $params['datetime_start'];
		}

		if ( isset( $params['datetime_end'] ) ) {
			$new_datetimes['datetime_end'] = $params['datetime_end'];
		}

		if ( isset( $params['timezone'] ) ) {
			$new_datetimes['timezone'] = $params['timezone'];
		}

		return $new_datetimes;
	}

	/**
	 * Update event venue.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Update parameters.
	 * @return void
	 */
	private function update_event_venue( int $event_id, array $params ): void {
		if ( ! isset( $params['venue_id'] ) ) {
			return;
		}

		$venue_id = intval( $params['venue_id'] );
		$venue    = get_post( $venue_id );

		if ( $venue && Venue::POST_TYPE === $venue->post_type ) {
			$venue_slug = '_' . $venue->post_name;
			wp_set_object_terms( $event_id, $venue_slug, Venue::TAXONOMY );
		}
	}

	/**
	 * Update event topics.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Update parameters.
	 * @return void
	 */
	private function update_event_topics( int $event_id, array $params ): void {
		if ( ! isset( $params['topic_ids'] ) || ! is_array( $params['topic_ids'] ) ) {
			return;
		}

		$topic_ids = array_map( 'intval', $params['topic_ids'] );
		wp_set_object_terms( $event_id, $topic_ids, Topic::TAXONOMY );
	}

	/**
	 * Update event thumbnail.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id Event post ID.
	 * @param array $params   Update parameters.
	 * @return void
	 */
	private function update_event_thumbnail( int $event_id, array $params ): void {
		if ( ! isset( $params['thumbnail_id'] ) ) {
			return;
		}

		$thumbnail_id = intval( $params['thumbnail_id'] );
		$attachment   = get_post( $thumbnail_id );

		if ( $attachment && 'attachment' === $attachment->post_type && wp_attachment_is_image( $thumbnail_id ) ) {
			$thumbnail_result = set_post_thumbnail( $event_id, $thumbnail_id );
			if ( ! $thumbnail_result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'GatherPress AI: Failed to set thumbnail ' . $thumbnail_id . ' for event ' . $event_id );
			}
		}
	}

	/**
	 * Update event description in existing block structure.
	 *
	 * Uses string replacement to update paragraph content without breaking block structure.
	 * Finds paragraph block by pattern and replaces only its content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $existing_content Current post content.
	 * @param string $new_description  New description text.
	 * @return string Updated block content.
	 */
	private function update_event_description( string $existing_content, string $new_description ): string {
		if ( empty( $existing_content ) ) {
			return $this->get_default_event_content( $new_description );
		}

		$description_content = wp_kses_post( $new_description );

		// Find paragraph with gp-ai-description class (AI-managed description).
		// Pattern matches paragraph block with className containing gp-ai-description.
		// Only matches paragraphs that have gp-ai-description in className.
		$pattern         = '/(<!-- wp:paragraph\s+\{[^}]*"className"[^}]*"gp-ai-description"[^}]*\} -->)'
			. '\s*\n*(<p>)(.*?)(<\/p>)\s*\n*(<!-- \/wp:paragraph -->)/s';
		$updated_content = preg_replace(
			$pattern,
			'$1' . "\n" . '$2' . $description_content . '$4' . "\n" . '$5',
			$existing_content,
			1
		);

		// If no AI-managed paragraph found, rebuild with default structure (snaps back to default position).
		if ( $updated_content === $existing_content ) {
			return $this->get_default_event_content( $new_description );
		}

		return $updated_content;
	}

	/**
	 * Get default event content with GatherPress blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description Optional description to include.
	 * @return string Block content for the event.
	 */
	private function get_default_event_content( $description = '' ) {
		$content  = '<!-- wp:gatherpress/event-date /-->' . "\n\n";
		$content .= '<!-- wp:gatherpress/add-to-calendar -->' . "\n";
		$content .= '<div class="wp-block-gatherpress-add-to-calendar"></div>' . "\n";
		$content .= '<!-- /wp:gatherpress/add-to-calendar -->' . "\n\n";

		$content .= '<!-- wp:gatherpress/venue /-->' . "\n\n";

		$content .= '<!-- wp:gatherpress/rsvp -->' . "\n";
		$content .= '<div class="wp-block-gatherpress-rsvp"></div>' . "\n";
		$content .= '<!-- /wp:gatherpress/rsvp -->' . "\n\n";

		// Add description paragraph with AI marker class for easy identification.
		if ( ! empty( $description ) ) {
			$description_content = wp_kses_post( $description );
			$content            .= '<!-- wp:paragraph {"className":"gp-ai-description"} -->' . "\n";
			$content            .= '<p>' . $description_content . '</p>' . "\n";
			$content            .= '<!-- /wp:paragraph -->' . "\n\n";
		} else {
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$description_placeholder = __( 'Add a description of the event and let people know what to expect, including the agenda, what they need to bring, and how to find the group.', 'gatherpress' );
			$content                .= '<!-- wp:paragraph {"placeholder":"'
				. esc_attr( $description_placeholder )
				. '","className":"gp-ai-description"} -->' . "\n";
			$content                .= '<p></p>' . "\n";
			$content                .= '<!-- /wp:paragraph -->' . "\n\n";
		}

		$content .= '<!-- wp:gatherpress/rsvp-response -->' . "\n";
		$content .= '<div class="wp-block-gatherpress-rsvp-response"></div>' . "\n";
		$content .= '<!-- /wp:gatherpress/rsvp-response -->';

		return $content;
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
				'execute_callback'    => array( $this, 'execute_update_events_batch' ),
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
				'venue_id'       => get_post_meta( $event->ID, 'gatherpress_venue', true ),
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
	 * Execute the update-events-batch ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params The parameters passed to the ability.
	 * @return array The result of the ability execution.
	 */
	public function execute_update_events_batch( array $params ): array {
		// Validate parameters.
		$validation_result = $this->validate_batch_update_params( $params );
		if ( isset( $validation_result['success'] ) && false === $validation_result['success'] ) {
			return $validation_result;
		}

		// Find matching events.
		$events = $this->find_events_for_batch_update( $params['search_term'] );

		if ( empty( $events ) ) {
			return array(
				'success' => true,
				'data'    => array(
					'updated_count' => 0,
				),
				'message' => sprintf(
					/* translators: %s: search term */
					__( 'No events found matching "%s" to update.', 'gatherpress' ),
					$params['search_term']
				),
			);
		}

		// Update each event.
		$update_results = $this->update_events_batch( $events, $params );

		// Build response message.
		$message = sprintf(
			/* translators: %1$d: number of updated events, %2$s: search term */
			_n(
				'Updated %1$d event matching "%2$s".',
				'Updated %1$d events matching "%2$s".',
				$update_results['updated_count'],
				'gatherpress'
			),
			$update_results['updated_count'],
			$params['search_term']
		);

		if ( ! empty( $update_results['errors'] ) ) {
			$error_text = __( 'Some events had errors:', 'gatherpress' );
			$message   .= ' ' . $error_text . ' ' . implode( '; ', $update_results['errors'] );
		}

		return array(
			'success' => true,
			'data'    => array(
				'updated_count' => $update_results['updated_count'],
				'errors'        => $update_results['errors'],
			),
			'message' => $message,
		);
	}

	/**
	 * Validate batch update parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Update parameters.
	 * @return array Validation result or empty array if valid.
	 */
	private function validate_batch_update_params( array $params ): array {
		if ( empty( $params['search_term'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Search term is required.', 'gatherpress' ),
			);
		}

		$has_updates = ! empty( $params['datetime_start'] )
			|| ! empty( $params['datetime_end'] )
			|| ! empty( $params['venue_id'] );

		if ( ! $has_updates ) {
			return array(
				'success' => false,
				'message' => __(
					'At least one update parameter (datetime_start, datetime_end, or venue_id) is required.',
					'gatherpress'
				),
			);
		}

		return array();
	}

	/**
	 * Find events matching search term for batch update.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search_term Search term.
	 * @return array Array of event post objects.
	 */
	private function find_events_for_batch_update( string $search_term ): array {
		$search_term = sanitize_text_field( $search_term );

		$query = new \WP_Query(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				's'              => $search_term,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$all_events = $query->posts;
		wp_reset_postdata();

		// Filter to exact title matches first (case-insensitive).
		$exact_matches = array();
		$other_matches = array();

		foreach ( $all_events as $event ) {
			$event_title = trim( get_the_title( $event->ID ) );
			if ( strtolower( $event_title ) === strtolower( trim( $search_term ) ) ) {
				$exact_matches[] = $event;
			} else {
				$other_matches[] = $event;
			}
		}

		// Use exact matches if found, otherwise use all matches.
		return ! empty( $exact_matches ) ? $exact_matches : $other_matches;
	}

	/**
	 * Update multiple events in batch.
	 *
	 * @since 1.0.0
	 *
	 * @param array $events Array of event post objects.
	 * @param array $params Update parameters.
	 * @return array Results with updated_count and errors.
	 */
	private function update_events_batch( array $events, array $params ): array {
		$updated_count = 0;
		$errors        = array();

		foreach ( $events as $event ) {
			$update_result = $this->update_single_event_in_batch( $event, $params );
			if ( $update_result['updated'] ) {
				++$updated_count;
			}
			if ( ! empty( $update_result['error'] ) ) {
				$errors[] = $update_result['error'];
			}
		}

		return array(
			'updated_count' => $updated_count,
			'errors'        => $errors,
		);
	}

	/**
	 * Update a single event in batch operation.
	 *
	 * @since 1.0.0
	 *
	 * @param object $event Event post object.
	 * @param array  $params Update parameters.
	 * @return array Result with 'updated' boolean and optional 'error' string.
	 */
	private function update_single_event_in_batch( $event, array $params ): array {
		$event_obj = new Event( $event->ID );

		if ( ! $event_obj->event ) {
			return array(
				'updated' => false,
				'error'   => sprintf(
					/* translators: %s: event title */
					__( 'Event "%s" not found.', 'gatherpress' ),
					get_the_title( $event->ID )
				),
			);
		}

		$cache_key = sprintf( Event::DATETIME_CACHE_KEY, $event->ID );
		delete_transient( $cache_key );
		$datetime = $event_obj->get_datetime();

		// Validate time-only updates.
		$time_only_error = $this->validate_batch_time_only_update( $params, $datetime, $event->ID );
		if ( $time_only_error ) {
			return array(
				'updated' => false,
				'error'   => $time_only_error,
			);
		}

		$event_updated = false;

		// Update datetime if provided.
		$datetime_result = $this->update_event_datetime_in_batch( $event_obj, $params, $datetime );
		if ( $datetime_result['updated'] ) {
			$event_updated = true;
		}
		if ( ! empty( $datetime_result['error'] ) ) {
			return array(
				'updated' => false,
				'error'   => $datetime_result['error'],
			);
		}

		// Update venue if specified.
		$venue_result = $this->update_event_venue_in_batch( $event->ID, $params );
		if ( $venue_result['updated'] ) {
			$event_updated = true;
		}
		if ( ! empty( $venue_result['error'] ) ) {
			return array(
				'updated' => false,
				'error'   => $venue_result['error'],
			);
		}

		return array(
			'updated' => $event_updated,
		);
	}

	/**
	 * Validate time-only datetime update for batch operation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params   Update parameters.
	 * @param array $datetime Existing datetime data.
	 * @param int   $event_id Event ID.
	 * @return string Error message or empty string if valid.
	 */
	private function validate_batch_time_only_update( array $params, array $datetime, int $event_id ): string {
		$is_time_only_start = isset( $params['datetime_start'] )
			&& ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $params['datetime_start'] );
		$is_time_only_end   = isset( $params['datetime_end'] )
			&& ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $params['datetime_end'] );

		$has_existing_datetime = ! empty( $datetime['datetime_start'] )
			|| ! empty( $datetime['datetime_end'] )
			|| ! empty( $datetime['datetime_start_gmt'] )
			|| ! empty( $datetime['datetime_end_gmt'] );

		if ( ( $is_time_only_start || $is_time_only_end ) && ! $has_existing_datetime ) {
			return sprintf(
				/* translators: %s: event title */
				__(
					'Cannot update time-only for event "%s" without an existing date. Provide a full datetime.',
					'gatherpress'
				),
				get_the_title( $event_id )
			);
		}

		return '';
	}

	/**
	 * Update event datetime in batch operation.
	 *
	 * @since 1.0.0
	 *
	 * @param Event $event_obj Event object.
	 * @param array $params Update parameters.
	 * @param array $datetime Existing datetime data.
	 * @return array Result with 'updated' boolean and optional 'error' string.
	 */
	private function update_event_datetime_in_batch( Event $event_obj, array $params, array $datetime ): array {
		$new_datetimes = $this->prepare_datetime_updates( $params );

		if ( empty( $new_datetimes ) ) {
			return array( 'updated' => false );
		}

		try {
			$parser          = new Event_Datetime_Parser();
			$datetime_params = $parser->prepare_datetime_params( $new_datetimes, $datetime );
			$event_obj->save_datetimes( $datetime_params );
			return array( 'updated' => true );
		} catch ( \Exception $e ) {
			return array(
				'updated' => false,
				'error'   => sprintf(
					/* translators: 1: event title, 2: error message */
					__( 'Error updating event "%1$s": %2$s', 'gatherpress' ),
					get_the_title( $event_obj->event->ID ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Update event venue in batch operation.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id Event ID.
	 * @param array $params Update parameters.
	 * @return array Result with 'updated' boolean and optional 'error' string.
	 */
	private function update_event_venue_in_batch( int $event_id, array $params ): array {
		if ( empty( $params['venue_id'] ) ) {
			return array( 'updated' => false );
		}

		$venue_id = absint( $params['venue_id'] );
		$venue    = get_post( $venue_id );

		if ( $venue && get_post_type( $venue_id ) === Venue::POST_TYPE ) {
			update_post_meta( $event_id, 'gatherpress_venue', $venue_id );
			return array( 'updated' => true );
		}

		return array(
			'updated' => false,
			'error'   => sprintf(
				/* translators: %s: event title */
				__( 'Invalid venue ID for event "%s".', 'gatherpress' ),
				get_the_title( $event_id )
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

	/**
	 * Geocode an address using OpenStreetMap Nominatim API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $address The address to geocode.
	 * @return array Array with 'latitude' and 'longitude' keys.
	 */
	private function geocode_address( string $address ): array {
		// URL encode the address for the API request.
		$encoded_address = rawurlencode( $address );
		$api_url         = "https://nominatim.openstreetmap.org/search?q={$encoded_address}&format=json&limit=1";

		// Make the API request.
		$version  = defined( 'GATHERPRESS_VERSION' ) ? GATHERPRESS_VERSION : '1.0.0';
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'User-Agent' => 'GatherPress/' . $version . ' (WordPress Plugin)',
				),
				'timeout' => 10,
			)
		);

		// Check for errors.
		if ( is_wp_error( $response ) ) {
			return array(
				'latitude'  => '0',
				'longitude' => '0',
			);
		}

		// Parse the response.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// Check if we got valid coordinates.
		if ( ! empty( $data ) && isset( $data[0]['lat'] ) && isset( $data[0]['lon'] ) ) {
			return array(
				'latitude'  => $data[0]['lat'],
				'longitude' => $data[0]['lon'],
			);
		}

		// Fallback if geocoding fails.
		return array(
			'latitude'  => '0',
			'longitude' => '0',
		);
	}
}
