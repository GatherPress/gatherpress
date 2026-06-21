<?php
/**
 * Event abilities for the WordPress Abilities API.
 *
 * @package GatherPress\Core\AI
 * @since 0.34.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\AI\Event_Datetime_Parser;
use GatherPress\Core\Event;
use GatherPress\Core\Shadow_Source;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Error;
use WP_Post;

/**
 * Class Abilities_Event.
 *
 * Handles create-event, update-event, list-events, and search-events ability execution.
 *
 * @since 0.34.0
 */
class Abilities_Event {

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
			),
			true
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

		$venue_link = array(
			'attempted' => false,
		);

		if ( ! empty( $params['venue_id'] ) ) {
			$venue_link = array_merge(
				array( 'attempted' => true ),
				$this->attach_venue_to_event( $event_id, intval( $params['venue_id'] ) )
			);
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
			'venue_link'  => $venue_link,
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
			$result = wp_update_post( $post_update, true );
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
	public function prepare_datetime_updates( array $params ): array {
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

		$this->attach_venue_to_event( $event_id, intval( $params['venue_id'] ) );
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
	public function get_default_event_content( $description = '' ) {
		$content  = '<!-- wp:gatherpress/event-date /-->' . "\n\n";
		$content .= '<!-- wp:gatherpress/add-to-calendar -->' . "\n";
		$content .= '<div class="wp-block-gatherpress-add-to-calendar"></div>' . "\n";
		$content .= '<!-- /wp:gatherpress/add-to-calendar -->' . "\n\n";

		$content .= '<!-- wp:gatherpress/venue {"patternPicked":true} /-->' . "\n\n";

		$content .= '<!-- wp:gatherpress/rsvp {"patternPicked":true} -->' . "\n";
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

		$content .= '<!-- wp:gatherpress/rsvp-response {"patternPicked":true} -->' . "\n";
		$content .= '<div class="wp-block-gatherpress-rsvp-response"></div>' . "\n";
		$content .= '<!-- /wp:gatherpress/rsvp-response -->';

		return $content;
	}

	/**
	 * Resolve the venue post ID linked to an event via shadow taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id Event post ID.
	 * @return int Venue post ID, or 0 when none is linked.
	 */
	public function get_event_venue_id( int $event_id ): int {
		$venue_post = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event_id );

		if ( ! $venue_post instanceof WP_Post ) {
			return 0;
		}

		return (int) $venue_post->ID;
	}

	/**
	 * Link a venue post to an event via the venue shadow taxonomy.
	 *
	 * Ensures the shadow term exists, assigns it by term ID (matching the block
	 * editor flow), preserves the online-event sentinel when present, and verifies
	 * the event resolves back to the requested venue post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id Event post ID.
	 * @param int $venue_id Venue post ID.
	 * @return array Result with success flag and optional message or IDs.
	 */
	public function attach_venue_to_event( int $event_id, int $venue_id ): array {
		$venue = get_post( $venue_id );

		if ( ! $venue instanceof WP_Post || Venue::POST_TYPE !== $venue->post_type ) {
			return array(
				'success' => false,
				'message' => __( 'Venue not found.', 'gatherpress' ),
			);
		}

		if ( empty( $venue->post_name ) || 'publish' !== $venue->post_status ) {
			return array(
				'success' => false,
				'message' => __(
					'Venue must be published with a valid slug before it can be linked to an event.',
					'gatherpress'
				),
			);
		}

		$shadow_source = Shadow_Source::get_instance();
		$term_slug     = $shadow_source->term_slug_from_post_name( $venue->post_name );
		$taxonomy      = Venue::TAXONOMY;
		$term          = term_exists( $term_slug, $taxonomy );

		if ( empty( $term ) ) {
			$term = wp_insert_term(
				html_entity_decode( get_the_title( $venue_id ) ),
				$taxonomy,
				array( 'slug' => $term_slug )
			);
		}

		if ( is_wp_error( $term ) ) {
			return array(
				'success' => false,
				'message' => $term->get_error_message(),
			);
		}

		$term_id  = (int) $term['term_id'];
		$term_ids = array( $term_id );
		$existing = get_the_terms( $event_id, $taxonomy );

		if ( is_array( $existing ) ) {
			foreach ( $existing as $existing_term ) {
				if ( 'online-event' === $existing_term->slug ) {
					$term_ids[] = (int) $existing_term->term_id;
					break;
				}
			}
		}

		/**
		 * Term assignment result or WP_Error.
		 *
		 * @var array|WP_Error $result
		 */
		$result = $this->assign_venue_terms( $event_id, $term_ids );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		$linked_venue = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event_id );

		if ( ! $linked_venue instanceof WP_Post || (int) $linked_venue->ID !== $venue_id ) {
			return array(
				'success' => false,
				'message' => __( 'Venue term was assigned but could not be verified on the event.', 'gatherpress' ),
			);
		}

		return array(
			'success'  => true,
			'venue_id' => $venue_id,
			'term_id'  => $term_id,
		);
	}

	/**
	 * Assign venue taxonomy terms to an event post.
	 *
	 * @since 0.34.0
	 *
	 * @param int   $event_id Event post ID.
	 * @param int[] $term_ids Term IDs to assign.
	 * @return array<int>|WP_Error Term taxonomy IDs or WP_Error on failure.
	 */
	protected function assign_venue_terms( int $event_id, array $term_ids ) {
		return wp_set_object_terms( $event_id, $term_ids, Venue::TAXONOMY, false );
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
				'venue_id'       => $this->get_event_venue_id( $event->ID ),
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
}
