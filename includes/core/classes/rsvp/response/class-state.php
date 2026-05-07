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

use GatherPress\Core\Settings\Roles;
use GatherPress\Core\Rsvp\Response\Provider\Provider;
use Wp_Comment;

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
	 * @param Data       $data      The data value object of the RSVP response.
	 * @param Provider   $provider  The Response Identity Provider.
	 * @param Wp_Comment $comment   The WordPress comment ID of the comment that stores the RSVP response.
	 */
	public function __construct(
		public readonly Data $data,
		public readonly Provider $provider,
		public readonly Wp_Comment $comment
	) {}

	/**
	 * Convert attendee to array for output.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function to_array(): array {
		$identity = $this->data->identity;
		return array(
			'name'       => $this->provider->get_display_name( $identity ),
			'photo'      => $this->provider->get_avatar_url( $identity ),
			'profile'    => $this->provider->get_url( $identity ),
			'status'     => $this->data->status->value,
			'guests'     => $this->data->guests,
			'anonymous'  => $this->data->anonymous,
			'timestamp'  => $this->data->timestamp,
			'provider'   => $this->provider->get_slug(),
			'identifier' => $identity->value,
			'comment_id' => (int) $this->comment->comment_ID,
			'post_id'    => (int) $this->comment->comment_post_ID,
			'user_id'    => (int) $this->comment->user_id,
			'role'       => Roles::get_instance()->get_user_role( $this->comment->user_id ),
		);
	}
}
