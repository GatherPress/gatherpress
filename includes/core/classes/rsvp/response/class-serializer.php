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

use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Rsvp;
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

		if ( $state->data->anonymous && ! current_user_can( Rsvp::CAPABILITY ) ) {
			$user_id = 0;
			$profile = '';
			$name    = __( 'Anonymous', 'gatherpress' );

			// Mask every identity-derived value, not just the name: the avatar
			// URL and raw identifier both expose who the responder is.
			$photo      = self::anonymous_avatar_url();
			$identifier = 0;
		} else {
			$user_id = (int) $state->comment->user_id;
			$profile = $state->provider->get_url( $identity );
			$photo   = $state->provider->get_avatar_url( $identity );
			$name    = $identity->display_name ?? $state->provider->get_display_name( $identity );

			// An email response carries the responder's address as its
			// identifier, so that one is only reported to whoever manages
			// RSVPs. Other identity types are already public in this record.
			$identifier = Identity_Type::EMAIL === $identity->type && ! current_user_can( Rsvp::CAPABILITY )
				? 0
				: $identity->value;

			// A responder identified only by an address has no name to show, so
			// the record says what it can: whoever manages RSVPs sees the
			// address the response was saved with, and everyone else sees that
			// someone is attending.
			if ( '' === $name ) {
				$name = current_user_can( Rsvp::CAPABILITY ) && ! empty( $state->comment->comment_author_email )
					? (string) $state->comment->comment_author_email
					: __( 'Attendee', 'gatherpress' );
			}
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
			// Derived from the masked $user_id so the role is masked too.
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
	 * Get a default avatar URL that carries no identifying email hash.
	 *
	 * @since 0.35.1
	 *
	 * @return string The default avatar URL, or an empty string if avatars are unavailable.
	 */
	private static function anonymous_avatar_url(): string {
		return (string) get_avatar_url( '', array( 'force_default' => true ) );
	}
}
