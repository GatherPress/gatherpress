<?php
/**
 * RSVP Response.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
/**
 * RSVP response data value object.
 *
 * @since 0.35.0
 */
final class Data {

	/**
	 * The number of guests.
	 *
	 * @since 0.35.0
	 *
	 * @var int
	 */
	public readonly int $guests;

	/**
	 * The timestamp of the response.
	 *
	 * @since 0.35.0
	 *
	 * @var string
	 */
	public readonly string $timestamp;

	/**
	 * Constructor for a RSVP response data value object.
	 *
	 * @since 0.35.0
	 *
	 * @param Identity $identity   The identity of the issuer of the RSVP response.
	 * @param Status   $status     The status of the response.
	 * @param int      $guests     The number of guests.
	 * @param bool     $anonymous  Whether the response is anonymous.
	 * @param string   $timestamp  The timestamp of the response.
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
	 * @since 0.35.0
	 *
	 * @param Status $status The new status.
	 *
	 * @return self A new instance with the given status applied.
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
