<?php
/**
 * Handles integration with the WordPress Abilities API.
 *
 * This class registers GatherPress abilities with the Abilities API, allowing
 * AI assistants and other tools to discover and execute GatherPress functionality.
 * The integration is optional and only activates when the Abilities API is available.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Abilities_Integration.
 *
 * Registers GatherPress abilities with the WordPress Abilities API for AI and automation tools.
 *
 * @since 1.0.0
 */
class Abilities_Integration {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

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
		add_action( 'abilities_api_init', array( $this, 'register_abilities' ) );
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
		$this->register_search_events_ability();
		$this->register_calculate_dates_ability();
		$this->register_create_venue_ability();
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
				'description'         => __( 'Retrieve a list of all available event venues with their addresses and details.', 'gatherpress' ),
				'execute_callback'    => array( $this, 'execute_list_venues' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'read' );
				},
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'category' => 'venue',
						'safe'     => true,
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
				'description'         => __( 'Retrieve a list of events with their dates, venues, and details. IMPORTANT: When searching for events by name, use the search parameter to find all events (not just upcoming).', 'gatherpress' ),
				'execute_callback'    => array( $this, 'execute_list_events' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'read' );
				},
				'parameters'          => array(
					'max_number' => array(
						'type'        => 'integer',
						'description' => __( 'Maximum number of events to return (default: 10)', 'gatherpress' ),
						'default'     => 10,
					),
					'search' => array(
						'type'        => 'string',
						'description' => __( 'Search term to find specific events by title or content', 'gatherpress' ),
						'required'    => false,
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'category' => 'event',
						'safe'     => true,
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
				'execute_callback'    => array( $this, 'execute_create_venue' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'parameters'          => array(
					'name'    => array(
						'type'        => 'string',
						'description' => __( 'Name of the venue', 'gatherpress' ),
						'required'    => true,
					),
					'address' => array(
						'type'        => 'string',
						'description' => __( 'Full address of the venue', 'gatherpress' ),
						'required'    => true,
					),
					'phone'   => array(
						'type'        => 'string',
						'description' => __( 'Phone number for the venue', 'gatherpress' ),
						'required'    => false,
					),
					'website' => array(
						'type'        => 'string',
						'description' => __( 'Website URL for the venue', 'gatherpress' ),
						'required'    => false,
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'category' => 'venue',
						'safe'     => false,
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
				'description'         => __( 'Create a new event with a title, date/time, and optional venue. Events are created as drafts by default for review before publishing.', 'gatherpress' ),
				'execute_callback'    => array( $this, 'execute_create_event' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'parameters'          => array(
					'title'          => array(
						'type'        => 'string',
						'description' => __( 'Title of the event', 'gatherpress' ),
						'required'    => true,
					),
					'datetime_start' => array(
						'type'        => 'string',
						'description' => __( 'Event start date and time in Y-m-d H:i:s format', 'gatherpress' ),
						'required'    => true,
					),
					'datetime_end'   => array(
						'type'        => 'string',
						'description' => __( 'Event end date and time in Y-m-d H:i:s format', 'gatherpress' ),
						'required'    => false,
					),
					'venue_id'       => array(
						'type'        => 'integer',
						'description' => __( 'ID of the venue for this event', 'gatherpress' ),
						'required'    => false,
					),
					'description'    => array(
						'type'        => 'string',
						'description' => __( 'Event description/content', 'gatherpress' ),
						'required'    => false,
					),
					'post_status'    => array(
						'type'        => 'string',
						'description' => __( 'Post status (draft or publish)', 'gatherpress' ),
						'default'     => 'draft',
						'enum'        => array( 'draft', 'publish' ),
					),
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
				'description'         => __( 'Calculate a list of recurring dates based on a pattern. Use this BEFORE creating recurring events to get accurate dates. Examples: "3rd Tuesday of each month for 6 months", "every Monday for 4 weeks", "first Friday for 3 months".', 'gatherpress' ),
				'execute_callback'    => array( $this, 'execute_calculate_dates' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'read' );
				},
				'parameters'          => array(
					'pattern'     => array(
						'type'        => 'string',
						'description' => __( 'The recurrence pattern. Examples: "3rd Tuesday", "every Monday", "first Friday", "last Wednesday"', 'gatherpress' ),
						'required'    => true,
					),
					'occurrences' => array(
						'type'        => 'integer',
						'description' => __( 'Number of occurrences to calculate', 'gatherpress' ),
						'required'    => true,
					),
					'start_date'  => array(
						'type'        => 'string',
						'description' => __( 'Starting date for calculations in Y-m-d format. Defaults to today if not provided.', 'gatherpress' ),
						'required'    => false,
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'category' => 'event',
						'safe'     => true,
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
				'description'         => __( 'Update an existing venue\'s information including name, address, and contact details.', 'gatherpress' ),
				'execute_callback'    => array( $this, 'execute_update_venue' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'parameters'          => array(
					'venue_id' => array(
						'type'        => 'integer',
						'description' => __( 'ID of the venue to update', 'gatherpress' ),
						'required'    => true,
					),
					'name'     => array(
						'type'        => 'string',
						'description' => __( 'Name of the venue', 'gatherpress' ),
						'required'    => false,
					),
					'address'  => array(
						'type'        => 'string',
						'description' => __( 'Full address of the venue', 'gatherpress' ),
						'required'    => false,
					),
					'phone'    => array(
						'type'        => 'string',
						'description' => __( 'Phone number for the venue', 'gatherpress' ),
						'required'    => false,
					),
					'website'  => array(
						'type'        => 'string',
						'description' => __( 'Website URL for the venue', 'gatherpress' ),
						'required'    => false,
					),
				),
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'category' => 'venue',
						'safe'     => false,
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
				'description'         => __( 'Update an existing event\'s details including title, date/time, venue, and description.', 'gatherpress' ),
				'execute_callback'    => array( $this, 'execute_update_event' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'parameters'          => array(
					'event_id'       => array(
						'type'        => 'integer',
						'description' => __( 'ID of the event to update', 'gatherpress' ),
						'required'    => true,
					),
					'title'          => array(
						'type'        => 'string',
						'description' => __( 'Title of the event', 'gatherpress' ),
						'required'    => false,
					),
					'datetime_start' => array(
						'type'        => 'string',
						'description' => __( 'Event start date and time in Y-m-d H:i:s format', 'gatherpress' ),
						'required'    => false,
					),
					'datetime_end'   => array(
						'type'        => 'string',
						'description' => __( 'Event end date and time in Y-m-d H:i:s format', 'gatherpress' ),
						'required'    => false,
					),
					'venue_id'       => array(
						'type'        => 'integer',
						'description' => __( 'ID of the venue for this event', 'gatherpress' ),
						'required'    => false,
					),
					'description'    => array(
						'type'        => 'string',
						'description' => __( 'Event description/content', 'gatherpress' ),
						'required'    => false,
					),
					'post_status'    => array(
						'type'        => 'string',
						'description' => __( 'Post status (draft or publish)', 'gatherpress' ),
						'required'    => false,
						'enum'        => array( 'draft', 'publish' ),
					),
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
	 * @return array Response with venue list or error.
	 */
	public function execute_list_venues(): array {
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

			$venue_list[] = array(
				'id'        => $venue_post->ID,
				'name'      => $venue_post->post_title,
				'address'   => $venue_info['fullAddress'] ?? '',
				'phone'     => $venue_info['phoneNumber'] ?? '',
				'website'   => $venue_info['website'] ?? '',
				'latitude'  => $venue_info['latitude'] ?? '',
				'longitude' => $venue_info['longitude'] ?? '',
				'edit_url'  => get_edit_post_link( $venue_post->ID, 'raw' ),
				'permalink' => get_permalink( $venue_post->ID ),
			);
		}

		return array(
			'success' => true,
			'data'    => $venue_list,
			'message' => sprintf(
				/* translators: %d: number of venues */
				__( 'Found %d venue(s)', 'gatherpress' ),
				count( $venue_list )
			),
		);
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
		$max_number = isset( $params['max_number'] ) ? intval( $params['max_number'] ) : 10;
		$max_number = min( $max_number, 50 ); // Cap at 50 for performance.
		
		// If search term is provided, search all events instead of just upcoming
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
				$event_obj = new Event( $event->ID );
				$venue_information = $event_obj->get_venue_information();

				$event_list[] = array(
					'id'             => $event->ID,
					'title'          => get_the_title( $event->ID ),
					'datetime_start' => $event_obj->get_datetime_start( 'Y-m-d H:i:s' ),
					'datetime_end'   => $event_obj->get_datetime_end( 'Y-m-d H:i:s' ),
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
					/* translators: %d: number of events, %s: search term */
					_n(
						'Found %d event matching "%s".',
						'Found %d events matching "%s".',
						count( $event_list ),
						'gatherpress'
					),
					count( $event_list ),
					$params['search']
				),
			);
		}

		// Default to searching all events instead of just upcoming
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
			$event_obj = new Event( $event->ID );
			$venue_information = $event_obj->get_venue_information();

			$event_list[] = array(
				'id'             => $event->ID,
				'title'          => get_the_title( $event->ID ),
				'datetime_start' => $event_obj->get_datetime_start( 'Y-m-d H:i:s' ),
				'datetime_end'   => $event_obj->get_datetime_end( 'Y-m-d H:i:s' ),
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

		$event_list = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$event             = new Event( $post_id );
				$venue_information = $event->get_venue_information();

				$event_list[] = array(
					'id'             => $post_id,
					'title'          => get_the_title( $post_id ),
					'datetime_start' => $event->get_datetime_start( 'Y-m-d H:i:s' ),
					'datetime_end'   => $event->get_datetime_end( 'Y-m-d H:i:s' ),
					'venue'          => $venue_information['name'] ?? null,
					'permalink'      => get_permalink( $post_id ),
					'edit_url'       => get_edit_post_link( $post_id, 'raw' ),
				);
			}
		}

		wp_reset_postdata();

		return array(
			'success' => true,
			'data'    => $event_list,
			'message' => sprintf(
				/* translators: %d: number of events */
				__( 'Found %d upcoming event(s)', 'gatherpress' ),
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

		// Create the venue post.
		$venue_id = wp_insert_post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => sanitize_text_field( $params['name'] ),
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $venue_id ) ) {
			return array(
				'success' => false,
				'message' => $venue_id->get_error_message(),
			);
		}

		// Prepare venue information.
		$venue_info = array(
			'fullAddress' => sanitize_text_field( $params['address'] ),
			'phoneNumber' => isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '',
			'website'     => isset( $params['website'] ) ? esc_url_raw( $params['website'] ) : '',
			'latitude'    => '0',
			'longitude'   => '0',
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

		// Validate datetime format.
		$start_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $params['datetime_start'] );
		if ( ! $start_datetime ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid start date/time format. Use Y-m-d H:i:s (e.g., 2025-01-21 19:00:00)', 'gatherpress' ),
			);
		}

		// Default post status to draft for safety.
		$post_status = isset( $params['post_status'] ) ? $params['post_status'] : 'draft';
		if ( ! in_array( $post_status, array( 'draft', 'publish' ), true ) ) {
			$post_status = 'draft';
		}

		// Create the event post with the proper template content.
		$post_content = $this->get_default_event_content( $params['description'] ?? '' );
		
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
			$end_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $params['datetime_end'] );
			if ( ! $end_datetime ) {
				$end_datetime = clone $start_datetime;
				$end_datetime->modify( '+2 hours' );
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
		// Validate required parameters.
		if ( empty( $params['event_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Event ID is required.', 'gatherpress' ),
			);
		}

		$event_id = intval( $params['event_id'] );

		// Verify event exists.
		$event_post = get_post( $event_id );
		if ( ! $event_post || Event::POST_TYPE !== $event_post->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Event not found.', 'gatherpress' ),
			);
		}

		// Build update array for post.
		$post_update = array( 'ID' => $event_id );

		if ( isset( $params['title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( $params['title'] );
		}

		if ( isset( $params['description'] ) ) {
			$post_update['post_content'] = wp_kses_post( $params['description'] );
		}

		if ( isset( $params['post_status'] ) && in_array( $params['post_status'], array( 'draft', 'publish' ), true ) ) {
			$post_update['post_status'] = $params['post_status'];
		}

		// Update post if there are changes.
		if ( count( $post_update ) > 1 ) {
			$result = wp_update_post( $post_update );
			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'message' => $result->get_error_message(),
				);
			}
		}

		// Update datetime if provided using the Event class method.
		$datetime_params = array();

		if ( isset( $params['datetime_start'] ) ) {
			$start_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $params['datetime_start'] );
			if ( ! $start_datetime ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid start date/time format. Use Y-m-d H:i:s (e.g., 2025-01-21 19:00:00)', 'gatherpress' ),
				);
			}
			$datetime_params['datetime_start'] = $start_datetime->format( 'Y-m-d H:i:s' );
		}

		if ( isset( $params['datetime_end'] ) ) {
			$end_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $params['datetime_end'] );
			if ( ! $end_datetime ) {
				return array(
					'success' => false,
					'message' => __( 'Invalid end date/time format. Use Y-m-d H:i:s (e.g., 2025-01-21 21:00:00)', 'gatherpress' ),
				);
			}
			$datetime_params['datetime_end'] = $end_datetime->format( 'Y-m-d H:i:s' );
		}

		// Update datetime using Event class if there are changes.
		if ( ! empty( $datetime_params ) ) {
			$event = new Event( $event_id );
			$event->save_datetimes( $datetime_params );
		}

		// Update venue if provided.
		if ( isset( $params['venue_id'] ) ) {
			$venue_id = intval( $params['venue_id'] );

			// Verify venue exists.
			$venue = get_post( $venue_id );
			if ( $venue && Venue::POST_TYPE === $venue->post_type ) {
				// Get the venue term slug for the taxonomy system.
				$venue_slug = '_' . $venue->post_name;
				wp_set_object_terms( $event_id, $venue_slug, Venue::TAXONOMY );
			}
		}

		return array(
			'success'     => true,
			'event_id'    => $event_id,
			'post_status' => get_post_status( $event_id ),
			'edit_url'    => get_edit_post_link( $event_id, 'raw' ),
			'message'     => sprintf(
				/* translators: %s: event title */
				__( 'Event "%s" updated successfully.', 'gatherpress' ),
				get_the_title( $event_id )
			),
		);
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
		// Build the default event template matching the exact format from manual creation.
		$content = '<!-- wp:gatherpress/event-date /-->' . "\n\n";
		$content .= '<!-- wp:gatherpress/add-to-calendar -->' . "\n";
		$content .= '<div class="wp-block-gatherpress-add-to-calendar"></div>' . "\n";
		$content .= '<!-- /wp:gatherpress/add-to-calendar -->' . "\n\n";
		
		$content .= '<!-- wp:gatherpress/venue /-->' . "\n\n";
		
		$content .= '<!-- wp:gatherpress/rsvp -->' . "\n";
		$content .= '<div class="wp-block-gatherpress-rsvp"></div>' . "\n";
		$content .= '<!-- /wp:gatherpress/rsvp -->' . "\n\n";

		// Add description paragraph.
		if ( ! empty( $description ) ) {
			$description_content = wp_kses_post( $description );
			$content .= '<!-- wp:paragraph -->' . "\n";
			$content .= '<p>' . $description_content . '</p>' . "\n";
			$content .= '<!-- /wp:paragraph -->' . "\n\n";
		} else {
			$description_placeholder = __(
				'Add a description of the event and let people know what to expect, including the agenda, what they need to bring, and how to find the group.',
				'gatherpress'
			);
			$content .= '<!-- wp:paragraph {"placeholder":"' . esc_attr( $description_placeholder ) . '"} -->' . "\n";
			$content .= '<p></p>' . "\n";
			$content .= '<!-- /wp:paragraph -->' . "\n\n";
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
				'title'       => __( 'Search Events', 'gatherpress' ),
				'description' => __( 'Search for events by title or content.', 'gatherpress' ),
				'execute'     => array( $this, 'execute_search_events' ),
				'parameters'  => array(
					'search_term' => array(
						'type'        => 'string',
						'description' => __( 'Search term to find events by title or content.', 'gatherpress' ),
						'required'    => true,
					),
					'max_number' => array(
						'type'        => 'integer',
						'description' => __( 'Maximum number of events to return (default: 10).', 'gatherpress' ),
						'required'    => false,
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
				'title'       => __( 'Update Multiple Events', 'gatherpress' ),
				'description' => __( 'Update multiple events at once based on search criteria. IMPORTANT: When user says "change events from X to Y", this means CHANGE the start time from X to Y. Do NOT search for events currently at X - instead, find all matching events and change their start time to Y. For time ranges "from X to Y", set start time to X and end time to Y.', 'gatherpress' ),
				'execute'     => array( $this, 'execute_update_events_batch' ),
				'parameters'  => array(
					'search_term' => array(
						'type'        => 'string',
						'description' => __( 'Search term to find events to update (searches title and content).', 'gatherpress' ),
						'required'    => true,
					),
					'datetime_start' => array(
						'type'        => 'string',
						'description' => __( 'New start datetime in Y-m-d H:i:s format. For "change from X to Y", this should be the NEW start time (Y). For time ranges "from X to Y", this should be X.', 'gatherpress' ),
						'required'    => false,
					),
					'datetime_end' => array(
						'type'        => 'string',
						'description' => __( 'New end datetime in Y-m-d H:i:s format. For time ranges "from X to Y", this should be Y.', 'gatherpress' ),
						'required'    => false,
					),
					'venue_id' => array(
						'type'        => 'integer',
						'description' => __( 'New venue ID to assign to all matching events.', 'gatherpress' ),
						'required'    => false,
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
				'id'            => $event->ID,
				'title'         => get_the_title( $event->ID ),
				'status'        => $event->post_status,
				'datetime_start' => $datetime['start'] ?? '',
				'datetime_end'  => $datetime['end'] ?? '',
				'timezone'      => $datetime['timezone'] ?? '',
				'venue_id'      => get_post_meta( $event->ID, 'gatherpress_venue', true ),
				'edit_url'      => get_edit_post_link( $event->ID, 'raw' ),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'events' => $event_data,
				'count'  => count( $event_data ),
			),
			'message' => sprintf(
				/* translators: %d: number of events, %s: search term */
				_n(
					'Found %d event matching "%s".',
					'Found %d events matching "%s".',
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
		// Validate required parameters.
		if ( empty( $params['search_term'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Search term is required.', 'gatherpress' ),
			);
		}

		// Check if at least one update parameter is provided.
		$has_updates = ! empty( $params['datetime_start'] ) || 
					   ! empty( $params['datetime_end'] ) || 
					   ! empty( $params['venue_id'] );

		if ( ! $has_updates ) {
			return array(
				'success' => false,
				'message' => __( 'At least one update parameter (datetime_start, datetime_end, or venue_id) is required.', 'gatherpress' ),
			);
		}

		// Find matching events.
		$events = get_posts(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1, // Get all matching events.
				's'              => sanitize_text_field( $params['search_term'] ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

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

		$updated_count = 0;
		$errors = array();

		foreach ( $events as $event ) {
			$event_obj = new Event( $event->ID );
			$datetime = $event_obj->get_datetime();

			// Prepare datetime updates.
			$new_datetime_start = ! empty( $params['datetime_start'] ) ? $params['datetime_start'] : $datetime['start'];
			$new_datetime_end = ! empty( $params['datetime_end'] ) ? $params['datetime_end'] : $datetime['end'];

			// Validate datetime format.
			if ( ! empty( $params['datetime_start'] ) ) {
				$start_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $new_datetime_start );
				if ( ! $start_datetime ) {
					$errors[] = sprintf(
						/* translators: %s: event title */
						__( 'Invalid datetime_start format for event "%s".', 'gatherpress' ),
						get_the_title( $event->ID )
					);
					continue;
				}
			}

			if ( ! empty( $params['datetime_end'] ) ) {
				$end_datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $new_datetime_end );
				if ( ! $end_datetime ) {
					$errors[] = sprintf(
						/* translators: %s: event title */
						__( 'Invalid datetime_end format for event "%s".', 'gatherpress' ),
						get_the_title( $event->ID )
					);
					continue;
				}
			}

			// Update datetime if changed.
			if ( $new_datetime_start !== $datetime['start'] || $new_datetime_end !== $datetime['end'] ) {
				$event_obj->save_datetimes(
					$new_datetime_start,
					$new_datetime_end,
					$datetime['timezone'] ?? 'UTC'
				);
			}

			// Update venue if specified.
			if ( ! empty( $params['venue_id'] ) ) {
				$venue_id = absint( $params['venue_id'] );
				if ( get_post( $venue_id ) && get_post_type( $venue_id ) === Venue::POST_TYPE ) {
					update_post_meta( $event->ID, 'gatherpress_venue', $venue_id );
				} else {
					$errors[] = sprintf(
						/* translators: %s: event title */
						__( 'Invalid venue ID for event "%s".', 'gatherpress' ),
						get_the_title( $event->ID )
					);
					continue;
				}
			}

			$updated_count++;
		}

		$message = sprintf(
			/* translators: %d: number of updated events, %s: search term */
			_n(
				'Updated %d event matching "%s".',
				'Updated %d events matching "%s".',
				$updated_count,
				'gatherpress'
			),
			$updated_count,
			$params['search_term']
		);

		if ( ! empty( $errors ) ) {
			$message .= ' ' . __( 'Some events had errors:', 'gatherpress' ) . ' ' . implode( '; ', $errors );
		}

		return array(
			'success' => true,
			'data'    => array(
				'updated_count' => $updated_count,
				'errors'        => $errors,
			),
			'message' => $message,
		);
	}

	/**
	 * Execute the calculate-dates ability.
	 *
	 * Calculates recurring dates based on a pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including pattern, occurrences, and optional start_date.
	 * @return array Result with calculated dates or error.
	 */
	public function execute_calculate_dates( array $params = array() ): array {
		// Validate required parameters.
		if ( empty( $params['pattern'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Pattern is required.', 'gatherpress' ),
			);
		}

		if ( empty( $params['occurrences'] ) || $params['occurrences'] < 1 ) {
			return array(
				'success' => false,
				'message' => __( 'Occurrences must be at least 1.', 'gatherpress' ),
			);
		}

		$pattern     = sanitize_text_field( $params['pattern'] );
		$occurrences = intval( $params['occurrences'] );
		$start_date  = ! empty( $params['start_date'] ) ? sanitize_text_field( $params['start_date'] ) : gmdate( 'Y-m-d' );

		// Validate start_date format.
		$start_datetime = \DateTime::createFromFormat( 'Y-m-d', $start_date );
		if ( ! $start_datetime ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid start_date format. Use Y-m-d.', 'gatherpress' ),
			);
		}

		// Parse the pattern and calculate dates.
		$dates = $this->calculate_recurring_dates( $pattern, $occurrences, $start_datetime );

		if ( empty( $dates ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: pattern */
					__( 'Could not calculate dates for pattern: %s', 'gatherpress' ),
					$pattern
				),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'dates'   => $dates,
				'pattern' => $pattern,
				'count'   => count( $dates ),
			),
			'message' => sprintf(
				/* translators: %d: number of dates */
				_n(
					'Calculated %d date.',
					'Calculated %d dates.',
					count( $dates ),
					'gatherpress'
				),
				count( $dates )
			),
		);
	}

	/**
	 * Calculate recurring dates based on a pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $pattern       The recurrence pattern.
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_recurring_dates( string $pattern, int $occurrences, \DateTime $start_datetime ): array {
		$dates       = array();
		$pattern_low = strtolower( trim( $pattern ) );

		// Parse pattern for "Nth weekday" (e.g., "3rd Tuesday", "first Friday", "last Wednesday").
		if ( preg_match( '/^(first|second|third|fourth|last|1st|2nd|3rd|4th|5th)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			$ordinal = strtolower( $matches[1] );
			$weekday = strtolower( $matches[2] );

			// Convert ordinal to number.
			$ordinal_map = array(
				'first'  => 1,
				'1st'    => 1,
				'second' => 2,
				'2nd'    => 2,
				'third'  => 3,
				'3rd'    => 3,
				'fourth' => 4,
				'4th'    => 4,
				'fifth'  => 5,
				'5th'    => 5,
				'last'   => -1,
			);

			$nth = $ordinal_map[ $ordinal ] ?? 1;

			// Get current month/year as starting point.
			$current = clone $start_datetime;

			for ( $i = 0; $i < $occurrences; $i++ ) {
				$date = $this->get_nth_weekday_of_month( $current->format( 'Y' ), $current->format( 'm' ), $weekday, $nth );

				// If the calculated date is before start_date, move to next month.
				if ( $date < $start_datetime->format( 'Y-m-d' ) ) {
					$current->modify( '+1 month' );
					$date = $this->get_nth_weekday_of_month( $current->format( 'Y' ), $current->format( 'm' ), $weekday, $nth );
				}

				$dates[] = $date;

				// Move to next month.
				$current->modify( '+1 month' );
			}
		} elseif ( preg_match( '/^every\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			// Pattern: "every Monday".
			$weekday = strtolower( $matches[1] );
			$current = clone $start_datetime;

			// Find the next occurrence of this weekday.
			$day_num    = $this->get_weekday_number( $weekday );
			$current_day = (int) $current->format( 'N' );

			if ( $current_day <= $day_num ) {
				$days_ahead = $day_num - $current_day;
			} else {
				$days_ahead = 7 - $current_day + $day_num;
			}

			$current->modify( "+{$days_ahead} days" );

			for ( $i = 0; $i < $occurrences; $i++ ) {
				$dates[] = $current->format( 'Y-m-d' );
				$current->modify( '+7 days' );
			}
		} else {
			// Unrecognized pattern.
			return array();
		}

		return $dates;
	}

	/**
	 * Get the date of the Nth occurrence of a weekday in a month.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $year    Year.
	 * @param int    $month   Month.
	 * @param string $weekday Weekday name (e.g., "monday").
	 * @param int    $nth     Nth occurrence (1-5, or -1 for last).
	 * @return string Date in Y-m-d format.
	 */
	private function get_nth_weekday_of_month( int $year, int $month, string $weekday, int $nth ): string {
		if ( -1 === $nth ) {
			// Last occurrence.
			$date = new \DateTime( "last {$weekday} of {$year}-{$month}" );
		} else {
			// Nth occurrence.
			$ordinal_text = array( 1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth' );
			$ordinal      = $ordinal_text[ $nth ] ?? 'first';
			$date         = new \DateTime( "{$ordinal} {$weekday} of {$year}-{$month}" );
		}

		return $date->format( 'Y-m-d' );
	}

	/**
	 * Get the ISO-8601 numeric representation of the day of the week.
	 *
	 * @since 1.0.0
	 *
	 * @param string $weekday Weekday name (e.g., "monday").
	 * @return int Day number (1 for Monday, 7 for Sunday).
	 */
	private function get_weekday_number( string $weekday ): int {
		$days = array(
			'monday'    => 1,
			'tuesday'   => 2,
			'wednesday' => 3,
			'thursday'  => 4,
			'friday'    => 5,
			'saturday'  => 6,
			'sunday'    => 7,
		);

		return $days[ strtolower( $weekday ) ] ?? 1;
	}

}
