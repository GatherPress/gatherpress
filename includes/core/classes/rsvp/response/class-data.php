<?php
/**
 * RSVP Response.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit;

/**
 * RSVP Response.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */
final class Data {
	/**
	 * The number of guests.
	 *
	 * @var int
	 */
	public readonly int $guests;

	/**
	 * The number of guests.
	 *
	 * @var string
	 */
	public readonly string $timestamp;

	/**
	 * Constructor for a RSVP response data value object.
	 *
	 * @param Identity $identity   The Identity of the issues of the RSVP response.
	 * @param Status   $status     The status of the response.
	 * @param int      $guests     The number of guests.
	 * @param bool     $anonymous  Whether the response is anonymous.
	 * @param string   $timestamp  The timestamp of there response.
	 */
	public function __construct(
		public readonly Identity $identity,
		public readonly Status $status,
		int $guests = 0,
		public readonly bool $anonymous = false,
		?string $timestamp = null,
	) {
		$this->guests    = max( 0, $guests );
		$this->timestamp = $timestamp ?? gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Create a copy of this data with a new status.
	 *
	 * @param Status $status The new status.
	 * @return self
	 */
	public function with_status( Status $status ) {
		return new self(
			$this->identity,
			$status,
			$this->guests,
			$this->anonymous,
			$this->timestamp
		);
	}
}
