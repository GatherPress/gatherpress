<?php
/**
 * RSVP response item (saved state).
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit;

use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;

/**
 * RSVP Response item (saved state).
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */
final class State {
	/**
	 * Get an object for an saved RSVP response.
	 *
	 * @param Data     $data        The data value object of the RSVP response.
	 * @param Provider $provider    The Response Identity Provider.
	 * @param int      $comment_id  The WordPress comment ID of the comment that stores the RSVP response.
	 */
	public function __construct(
		public readonly Data $data,
		public readonly Provider $provider,
		public readonly int $comment_id
	) {
		$this->data       = $data;
		$this->provider   = $provider;
		$this->comment_id = $comment_id;
	}

	/**
	 * Convert attendee to array for output.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'name'       => $this->provider->get_display_name( $this->data->identity ),
			'photo'      => $this->provider->get_avatar_url( $this->data->identity ),
			'profile'    => $this->provider->get_url( $this->data->identity ),
			'status'     => $this->data->status,
			'guests'     => $this->data->guests,
			'anonymous'  => $this->data->anonymous,
			'timestamp'  => $this->data->timestamp,
			'provider'   => $this->provider->get_slug(),
			'identifier' => $this->data->identity->value,
		);
	}
}
