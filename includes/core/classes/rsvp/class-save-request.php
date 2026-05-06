<?php
/**
 * RSVP Request.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp;

use InvalidArgumentException;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class defining an RSVP request.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */
class Save_Request {
	/**
	 * Construct a new RSVP request.
	 *
	 * @param string  $type       The RSVP type.
	 * @param mixed   $identifier The identifier of the RSVP request.
	 * @param Status  $status     The desired status of the RSVP request.
	 * @param integer $guests     The number of guests for this RSVP requests.
	 * @param boolean $anonymous  Whether the RSVP request is anonymous.
	 *
	 * @throws InvalidArgumentException If using invalid RSVP status.
	 */
	public function __construct(
		public string $type = 'user',
		public mixed $identifier,
		public Status $status = Status::NO_STATUS,
		public int $guests = 0,
		public bool $anonymous = false,
	) {
		if ( null === Manager::get_type( $type ) ) {
			throw new InvalidArgumentException( 'Unknown RSVP type' );
		}
	}

	/**
	 * Whether the RSVP request has guests.
	 *
	 * @return bool
	 */
	public function has_guests(): bool {
		return $this->guests > 0;
	}

	/**
	 * Whether the RSVP request is anonymous.
	 *
	 * @return bool
	 */
	public function is_anonymous(): bool {
		return $this->anonymous;
	}

	/**
	 * Remove the guests from the RSVP request.
	 *
	 * @return void
	 */
	public function reset_guests(): void {
		$this->guests = 0;
	}

	/**
	 * Flag the RSVP request as anonymous.
	 *
	 * @return void
	 */
	public function anonymize(): void {
		$this->anonymous = true;
	}
}
