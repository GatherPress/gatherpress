<?php
/**
 * Serialize RSVP objects.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
use GatherPress\Core\Settings\Roles;

/**
 * Class with methods to serialize RSVP Response objects.
 *
 * @since 0.35.0
 */
final class Serializer {

	/**
	 * Convert state to array.
	 *
	 * @since 0.35.0
	 *
	 * @param State $state RSVP state.
	 *
	 * @return array The RSVP response state as an associative array.
	 */
	public static function to_array( State $state ): array {
		$identity = $state->data->identity;

		if (
			! current_user_can( 'edit_posts' ) && $state->data->anonymous
		) {
			$user_id = 0;
			$profile = '';
			$name    = __( 'Anonymous', 'gatherpress' );
			$photo   = $state->provider->get_avatar_url( $identity );
		} else {
			$user_id = (int) $state->comment->user_id;
			$profile = $state->provider->get_url( $identity );
			$name    = $state->provider->get_display_name( $identity );
			$photo   = $state->provider->get_avatar_url( $identity );
		}

		return array(
			'name'       => $name,
			'photo'      => $photo,
			'profile'    => $profile,
			'status'     => $state->data->status->value,
			'guests'     => $state->data->guests,
			'anonymous'  => $state->data->anonymous,
			'timestamp'  => $state->data->timestamp,
			'provider'   => $state->provider->get_slug(),
			'identifier' => $identity->value,
			'role'       => Roles::get_instance()->get_user_role( (int) $state->comment->user_id ),
			// Both naming styles ship deliberately: the responses() record
			// contract (the rsvp-response block's context mapping, editor
			// JS, and REST consumers) predates this class and uses
			// camelCase, while the save() return contract uses snake_case.
			// Dropping either silently breaks its consumers.
			'commentId'  => (int) $state->comment->comment_ID,
			'comment_id' => (int) $state->comment->comment_ID,
			'postId'     => (int) $state->comment->comment_post_ID,
			'post_id'    => (int) $state->comment->comment_post_ID,
			'userId'     => $user_id,
			'user_id'    => $user_id,
		);
	}
}
