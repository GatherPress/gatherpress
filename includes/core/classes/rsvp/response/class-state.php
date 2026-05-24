<?php
/**
 * RSVP response item (saved state).
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit;

use GatherPress\Core\Rsvp\Response\Provider\Provider;
use WP_Comment;

/**
 * RSVP Response item (saved state).
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */
final class State {
	/**
	 * Get an object for an saved RSVP response.
	 *
	 * @param Data       $data      The data value object of the RSVP response.
	 * @param Provider   $provider  The Response Identity Provider.
	 * @param WP_Comment $comment   The WordPress comment ID of the comment that stores the RSVP response.
	 */
	public function __construct(
		public readonly Data $data,
		public readonly Provider $provider,
		public readonly WP_Comment $comment
	) {}

	/**
	 * Check if attending.
	 *
	 * @return bool
	 */
	public function is_attending(): bool {
		return Status::ATTENDING === $this->data->status;
	}

	/**
	 * Check if waiting list.
	 *
	 * @return bool
	 */
	public function is_waiting_list(): bool {
		return Status::WAITING_LIST === $this->data->status;
	}

	/**
	 * Get number of attendees.
	 *
	 * @return int
	 */
	public function get_attendee_count(): int {
		return 1 + $this->data->guests;
	}
}
