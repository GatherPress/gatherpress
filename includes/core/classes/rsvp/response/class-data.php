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
class Data {
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
		public readonly int $guests = 0,
		public readonly bool $anonymous = false,
		public readonly ?string $timestamp = null,
	) {
		$this->identity  = $identity;
		$this->status    = $status;
		$this->guests    = max( 0, $guests );
		$this->anonymous = $anonymous;
		$this->timestamp = $timestamp || gmdate( 'Y-m-d H:i:s' );
	}
}
