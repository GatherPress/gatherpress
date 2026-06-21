<?php
/**
 * Batch event abilities for the WordPress Abilities API.
 *
 * @package GatherPress\Core\AI
 * @since 0.34.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

/**
 * Class Abilities_Event_Batch.
 *
 * Handles update-events-batch ability execution.
 *
 * @since 0.34.0
 */
class Abilities_Event_Batch {

	/**
	 * Event abilities handler.
	 *
	 * @since 0.34.0
	 * @var Abilities_Event
	 */
	private $event;

	/**
	 * Constructor.
	 *
	 * @since 0.34.0
	 *
	 * @param Abilities_Event $event Event abilities handler.
	 */
	public function __construct( Abilities_Event $event ) {
		$this->event = $event;
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
		$new_datetimes = $this->event->prepare_datetime_updates( $params );

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

		$attach_result = $this->event->attach_venue_to_event( $event_id, absint( $params['venue_id'] ) );

		if ( ! empty( $attach_result['success'] ) ) {
			return array( 'updated' => true );
		}

		return array(
			'updated' => false,
			'error'   => $attach_result['message'] ?? sprintf(
				/* translators: %s: event title */
				__( 'Invalid venue ID for event "%s".', 'gatherpress' ),
				get_the_title( $event_id )
			),
		);
	}
}
