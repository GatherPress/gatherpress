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
use GatherPress\Core\Utility;

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

			// Every identity-derived value has to be masked here, not just the
			// display name. The avatar URL embeds a hash of the responder's
			// email, which is a stable identifier that can be matched against
			// the same person's non-anonymous responses, and the raw identity
			// value is the user ID itself.
			$photo      = self::anonymous_avatar_url();
			$identifier = 0;
		} else {
			$user_id    = (int) $state->comment->user_id;
			$profile    = $state->provider->get_url( $identity );
			$name       = $state->provider->get_display_name( $identity );
			$photo      = $state->provider->get_avatar_url( $identity );
			$identifier = $identity->value;
		}

		$data = array(
			'name'       => $name,
			'photo'      => $photo,
			'profile'    => $profile,
			'status'     => $state->data->status->value,
			'guests'     => $state->data->guests,
			'anonymous'  => $state->data->anonymous,
			'timestamp'  => $state->data->timestamp,
			'provider'   => $state->provider->get_slug(),
			'identifier' => $identifier,
			// Derived from the masked $user_id rather than the comment's real
			// one, so an anonymous responder's site role is not disclosed.
			'role'       => Roles::get_instance()->get_user_role( $user_id ),
			'comment_id' => (int) $state->comment->comment_ID,
			'post_id'    => (int) $state->comment->comment_post_ID,
			'user_id'    => $user_id,
		);

		// The responses() record contract (the rsvp-response block's context
		// mapping, editor JS, and REST consumers) predates this class and
		// reads the multi-word keys in camelCase, while the save() return
		// contract reads snake_case. Emit both so neither set of consumers
		// breaks, deriving the camelCase aliases from the snake_case source
		// rather than maintaining two hand-written lists.
		foreach ( array( 'comment_id', 'post_id', 'user_id' ) as $gatherpress_snake_key ) {
			$data[ Utility::snake_to_camel( $gatherpress_snake_key ) ] = $data[ $gatherpress_snake_key ];
		}

		return $data;
	}

	/**
	 * Get an avatar URL that identifies nobody.
	 *
	 * Passing an empty identifier means no email hash is included in the URL,
	 * and forcing the default keeps a placeholder image rendering where the
	 * real avatar would have been.
	 *
	 * @since 0.35.0
	 *
	 * @return string The default avatar URL, or an empty string if avatars are unavailable.
	 */
	private static function anonymous_avatar_url(): string {
		return (string) get_avatar_url( '', array( 'force_default' => true ) );
	}
}
