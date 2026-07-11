<?php
/**
 * RSVP response item (saved state).
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
use GatherPress\Core\Rsvp\Response\Provider\Provider;
use WP_Comment;

/**
 * RSVP Response item (saved state).
 *
 * @since 0.35.0
 */
final class State {
	/**
	 * Get an object for a saved RSVP response.
	 *
	 * @since 0.35.0
	 *
	 * @param Data       $data      The data value object of the RSVP response.
	 * @param Provider   $provider  The Response Identity Provider.
	 * @param WP_Comment $comment   The WordPress comment that stores the RSVP response.
	 */
	public function __construct(
		public readonly Data $data,
		public readonly Provider $provider,
		public readonly WP_Comment $comment
	) {}

	/**
	 * Check if attending.
	 *
	 * @since 0.35.0
	 *
	 * @return bool True if the response status is attending, false otherwise.
	 */
	public function is_attending(): bool {
		return Status::ATTENDING === $this->data->status;
	}

	/**
	 * Check if waiting list.
	 *
	 * @since 0.35.0
	 *
	 * @return bool True if the response status is waiting list, false otherwise.
	 */
	public function is_waiting_list(): bool {
		return Status::WAITING_LIST === $this->data->status;
	}

	/**
	 * Get number of attendees.
	 *
	 * @since 0.35.0
	 *
	 * @return int The number of attendees, including the responder and their guests.
	 */
	public function get_attendee_count(): int {
		return 1 + $this->data->guests;
	}
}
