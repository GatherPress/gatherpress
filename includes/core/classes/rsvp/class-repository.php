<?php
/**
 * RSVP Repository.
 *
 * Handles retrieving and saving of RSVP as WordPress comments.
 *
 * @package GatherPress\Core\Rsvp
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Provider\Provider;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Intent;
use GatherPress\Core\Rsvp\Response\Provider_Registry;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Settings\Roles;
use InvalidArgumentException;
use WP_Comment;

/**
 * Class Repository.
 *
 * Handles querying and manipulation of RSVP comments within the GatherPress plugin.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */
final class Repository {
	private const COMMENT_META_EXTERNAL_ID = 'gatherpress_rsvp_external_id';

	/**
	 * Default save args.
	 *
	 * @var array
	 */
	private const DEFAULT_SAVE_ARGS = array(
		'comment_author'       => '',
		'comment_author_email' => '',
		'comment_author_url'   => '',
		'comment_author_IP'    => '127.0.0.1',
		'comment_content'      => '',
	);

	/**
	 * The default comment query args.
	 *
	 * @var array
	 */
	protected array $default_args;

	/**
	 * The RSVP query instance.
	 *
	 * @var Query
	 */
	protected Query $rsvp_query;


	/**
	 * RSVP Repository constructor.
	 *
	 * @since 1.0.0
	 * @throws InvalidArgumentException When trying to construct this RSVP for a post that does not support it.
	 *
	 * @param int $post_id The events post id.
	 */
	public function __construct( protected readonly int $post_id ) {
		$post_type = get_post_type( $post_id );

		// if ( ! $post_type ) {
		// 	throw new InvalidArgumentException(
		// 		\sprintf(
		// 			'Cannot construct RSVP repository: post %d does not exist.',
		// 			(int) $post_id
		// 		)
		// 	);
		// }

		// if ( ! post_type_supports( $post_type, 'gatherpress-rsvp' ) ) {
		// 	throw new InvalidArgumentException(
		// 		\sprintf(
		// 			'Post type "%s" does not support GatherPress RSVPs.',
		// 			esc_attr( $post_type )
		// 		)
		// 	);
		// }

		$this->rsvp_query = Query::get_instance();
	}

	/**
	 * Get a single RSVP.
	 *
	 * @param Identity      $identity      The Identity of the RSVP response.
	 * @param Provider|null $provider The RSVP provider.
	 * @return State|null
	 */
	public function get( Identity $identity, ?Provider $provider = null ): ?State {
		$args = array(
			'post_id' => $this->post_id,
			'status'  => 'approve',
		);

		// Add the identity of the RSVP response.
		$args = wp_parse_args( $this->get_identity_query_args( $identity ), $args );

		// Optionally also specify the provider that issued the RSVP response.
		if ( $provider ) {
			$args = wp_parse_args( $this->get_provider_query_args( $provider ), $args );
		}

		$rsvp = $this->rsvp_query->get_rsvp( $args );

		if ( null === $rsvp ) {
			return null;
		}

		return $this->hydrate( $rsvp, $identity, $provider );
	}

	/**
	 * Save or update a single RSVP.
	 *
	 * @param Intent   $intent     The Intent of the RSVP response.
	 * @param int|null $comment_id ID of an existing comment.
	 *
	 * @return State|bool
	 */
	public function save( Intent $intent, ?int $comment_id ): State|bool {
		// If status is 'no_status', remove the record.
		if ( Status::NO_STATUS === $intent->data->status && $comment_id ) {
			return wp_delete_comment( $comment_id );
		}

		$args = array(
			'comment_post_ID'  => $this->post_id,
			'comment_approved' => 1,
		);

		$args = array_merge( $args, self::DEFAULT_SAVE_ARGS );

		// Add the identity of the RSVP response.
		$args = wp_parse_args( $this->get_identity_query_args( $intent->data->identity ), $args );

		$args['comment_author'] = $intent->provider->get_display_name( $intent->data->identity );
		$args['comment_type']   = Rsvp::COMMENT_TYPE;

		$args = apply_filters( 'gatherpress_save_rsvp', $args );

		if ( ! $comment_id ) {
			$args       = wp_filter_comment( $args );
			$comment_id = wp_insert_comment( $args );
		} else {
			$args['comment_ID']       = $comment_id;
			$args['comment_approved'] = 1;
			$success                  = wp_update_comment( $args );

			if ( ! $success ) {
				return false;
			}
		}

		if ( ! $comment_id ) {
			return false;
		}

		wp_set_object_terms( $comment_id, $intent->data->status->value, Status::TAXONOMY );

		if ( $intent->data->guests ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_guests', $intent->data->guests );
		} else {
			delete_comment_meta( $comment_id, 'gatherpress_rsvp_guests' );
		}

		if ( $intent->data->anonymous ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', $intent->data->anonymous );
		} else {
			delete_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous' );
		}

		$comment = get_comment( $comment_id );

		return $this->hydrate( $comment, $intent->data->identity, $intent->provider );
	}

	/**
	 * Get all RSVP responses.
	 *
	 * @return State[]
	 */
	public function all(): array {
		$args = array(
			'post_id' => $this->post_id,
			'status'  => 'approve',
		);

		$comments = $this->rsvp_query->get_rsvps( $args );

		$states = array();

		foreach ( $comments as $comment ) {
			$state = $this->hydrate( $comment );

			if ( $state ) {
				$states[] = $state;
			}
		}

		return $states;
	}

	/**
	 * Get an RSVP response from a WP_Comment.
	 *
	 * @param WP_Comment    $comment   The RSVP comment.
	 * @param Identity|null $identity  The RSVPs identity (optional).
	 * @param Provider|null $provider  The RSVP provider (optional).
	 * @return State|null
	 */
	private static function hydrate( WP_Comment $comment, ?Identity $identity = null, ?Provider $provider = null ): ?State {
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

		$data = self::hydrate_data( $comment, $identity );

		return new State( $data, $provider, $comment );
	}

	/**
	 * Get RSVP data from comment.
	 *
	 * @param WP_Comment $comment  The RSVP comment.
	 * @param Identity   $identity The RSVP response identity.
	 * @return Data
	 */
	private static function hydrate_data( WP_Comment $comment, Identity $identity ) {
		$timestamp  = $comment->comment_date;
		$comment_id = \intval( $comment->comment_ID );
		$anonymous  = \intval( get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true ) );
		$guests     = \intval( get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
		$status     = self::get_status( $comment_id );

		return new Data( $identity, $status, $guests, $anonymous, $timestamp );
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
	private static function get_identity_from_comment(
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
		} catch ( InvalidArgumentException ) {
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

		// Fallbacks.
		if ( $comment->user_id > 0 ) {
			return Provider_Registry::get_instance()->get( User::get_slug() );
		}

		if ( is_email( $comment->comment_author_email ) && get_user_by( 'email', $comment->comment_author_email ) ) {
			return Provider_Registry::get_instance()->get( Email::get_slug() );
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
		$status = Status::TryFrom( self::get_value_from_object_terms( $comment_id, Status::TAXONOMY ) );

		if ( null === $status ) {
			$status = Status::NO_STATUS;
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

	/**
	 * Get query args for the identity.
	 *
	 * @param Identity $identity The identity.
	 *
	 * @return array<array<int|string>|int|string>
	 */
	private function get_identity_query_args( Identity $identity ) {
		$args = array();

		switch ( $identity->type ) {
			case Identity_Type::EMAIL:
				$args['comment_author_email'] = $identity->value;
				break;

			case Identity_Type::URL:
				$args['comment_author_url'] = $identity->value;
				break;

			case Identity_Type::WP_USER_ID:
				$args['user_id'] = (int) $identity->value;
				break;

			default:
				$args['comment_meta'][ self::COMMENT_META_EXTERNAL_ID ] = $identity->value;
				break;
		}

		return $args;
	}

	/**
	 * Get query args.
	 *
	 * @param Provider $provider The RSVP provider.
	 *
	 * @return array<array<int|string>|int|string>
	 */
	private function get_provider_query_args( $provider ) {
		return array(
			'gatherpress_rsvp_provider_query' => array(
				'taxonomy' => Provider::TAXONOMY,
				'terms'    => $provider->get_slug(),
				'field'    => 'slug',
			),
		);
	}
}
