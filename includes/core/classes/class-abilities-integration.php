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
		add_action( 'init', array( $this, 'register_abilities' ) );
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
		$this->register_create_venue_ability();
		$this->register_create_event_ability();
		$this->register_update_venue_ability();
		$this->register_update_event_ability();
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
				'description'         => __( 'Retrieve a list of upcoming events with their dates, venues, and details.', 'gatherpress' ),
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

		$query = Event_Query::get_instance()->get_events_list( 'upcoming', $max_number );

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

		// Create the event post.
		$event_id = wp_insert_post(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => sanitize_text_field( $params['title'] ),
				'post_content' => isset( $params['description'] ) ? wp_kses_post( $params['description'] ) : '',
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
				// Get the venue term slug.
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
}
