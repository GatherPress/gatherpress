<?php
/**
 * RSVP Response Factory
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit;

use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Response\Provider\Provider;
use WP_Comment;

/**
 * RSVP Response Factory.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */
class Factory {
	/**
	 * Get an RSVP response form a WP_Comment.
	 *
	 * @param WP_Comment $comment The RSVP comment.
	 * @return State|null
	 */
	final public static function from_comment( WP_Comment $comment ): ?State {
		if ( Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return null;
		}

		$provider = self::get_identity_provider( $comment );
		if ( null === $provider ) {
			return null;
		}

		$identity   = self::get_identity_from_comment( $comment, $provider::get_identity_type() );
		if ( null === $identity ) {
			return null;
		}

		$timestamp  = $comment->comment_date;
		$comment_id = \intval( $comment->comment_ID );
		$anonymous  = \intval( get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true ) );
		$guests     = \intval( get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
		$status     = self::get_status( $comment_id );

		$data = new Data( $identity, $status, $guests, $anonymous, $timestamp );
		return new State( $data, $provider, $comment_id );
	}

	/**
	 * Get an RSVP response form a WP_Comment.
	 *
	 * @param WP_Comment $comment The RSVP comment.
	 * @return State|null
	 */
	final public static function get( WP_Comment $comment, ?Identity $identity, ?Provider $provider ): ?State {
		if ( Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return null;
		}

		// Resolve provider if not given.
        if ( null === $provider ) {
            $provider = self::get_identity_provider( $comment );
            if ( null === $provider ) {
                return null;
            }
        }

        // Resolve identity if not given.
        if ( null === $identity ) {
            $identity = self::get_identity_from_comment( $comment, $provider::get_identity_type() );
			if ( null === $identity ) {
                return null;
            }
        }

		$timestamp  = $comment->comment_date;
		$comment_id = \intval( $comment->comment_ID );
		$anonymous  = \intval( get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true ) );
		$guests     = \intval( get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
		$status     = self::get_status( $comment_id );

		$data = new Data( $identity, $status, $guests, $anonymous, $timestamp );
		return new State( $data, $provider, $comment_id, );
	}

	/**
	 * Read identity from comment based on declared identity type.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment    $comment       Comment.
	 * @param Identity_Type $identity_type The identity type.
	 * @return Identity|null
	 */
	final public static function get_identity_from_comment(
		WP_Comment $comment,
		Identity_Type $identity_type
	): ?Identity {
		$identifier = match ( $identity_type ) {
			Identity_Type::WP_USER_ID  => (int) $comment->user_id,
			Identity_Type::EMAIL       => $comment->comment_author_email,
			Identity_Type::URL         => $comment->comment_author_url,
			Identity_Type::EXTERNAL_ID => get_comment_meta(
				$comment->comment_ID,
				'gatherpress_rsvp_external_id',
				true
			),
			default => null,
		};

		try {
			$identity = new Identity( $identity_type, $identifier );
		} catch ( \InvalidArgumentException ) {
			return null;
		}

		return $identity;
	}

	/**
	 * Get the identity provider for this RSVP response.
	 *
	 * @param WP_Comment $comment The WordPress comment that stores the RSVP response.
	 * @return Provider|null
	 */
	private static function get_identity_provider( WP_Comment $comment ): ?Provider {
		$comment_id = \intval( $comment->comment_ID );

		$provider_slug = self::get_value_from_object_terms( $comment_id, Provider::TAXONOMY );

		if ( $provider_slug && Provider_Registry::get_instance()->is_registered( $provider_slug ) ) {
			return Provider_Registry::get_instance()->get( $provider_slug );
		}

		return null;
	}

	/**
	 * Get the status.
	 *
	 * @param mixed $comment_id The comment ID of the RSVP response.
	 * @return Status
	 */
	private static function get_status( $comment_id ): Status {
		$status = status::TryFrom( self::get_value_from_object_terms( $comment_id, Status::TAXONOMY ) );

		if ( null === $status ) {
			$status = status::NO_STATUS;
		}

		return $status;
	}

	/**
	 * Get a single value for an taxonomy for an object.
	 *
	 * @param int    $id        The objects ID.
	 * @param string $taxonomy  The taxonomy of the term.
	 * @return string|null
	 */
	private static function get_value_from_object_terms( int $id, string $taxonomy ) {
		$terms = wp_get_object_terms( $id, $taxonomy );

		if ( ! empty( $terms ) && \is_array( $terms ) ) {
			return $terms[0]->slug;
		}

		return null;
	}
}
