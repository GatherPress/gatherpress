<?php
/**
 * RSVP Storage.
 *
 * Handles retrieving and saving of RSVP responses as WordPress comments.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Intent;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Core\Rsvp\Response\Provider_Registry;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use InvalidArgumentException;
use WP_Comment;

/**
 * Class Storage.
 *
 * Handles querying and manipulation of RSVP comments within the GatherPress plugin.
 *
 * @since 0.35.0
 */
final class Storage {

	/**
	 * Comment meta key that stores an external provider's identifier.
	 *
	 * @since 0.35.0
	 *
	 * @var string
	 */
	private const COMMENT_META_EXTERNAL_ID = 'gatherpress_rsvp_external_id';

	/**
	 * Default comment args applied when inserting a new RSVP comment.
	 *
	 * @since 0.35.0
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
	 * The RSVP query instance.
	 *
	 * @since 0.35.0
	 *
	 * @var Query
	 */
	protected Query $rsvp_query;

	/**
	 * RSVP Storage constructor.
	 *
	 * @since 0.35.0
	 *
	 * @param int $post_id The event post ID this storage operates on.
	 */
	public function __construct( protected readonly int $post_id ) {
		$this->rsvp_query = Query::get_instance();
	}

	/**
	 * Get a single RSVP response.
	 *
	 * @since 0.35.0
	 *
	 * @param Identity      $identity The identity of the RSVP response.
	 * @param Provider|null $provider Optional. The provider that issued the RSVP response.
	 *
	 * @return State|null The hydrated RSVP state, or null when none matches.
	 */
	public function get( Identity $identity, ?Provider $provider = null ): ?State {
		$args = array(
			'post_id' => $this->post_id,
			'status'  => 'approve',
		);

		// Add the identity of the RSVP response.
		$args = wp_parse_args( $this->get_identity_query_args( $identity ), $args );

		$rsvp = $this->rsvp_query->get_rsvp( $args );

		if ( null === $rsvp ) {
			return null;
		}

		// The identity already pins the row (a user id or an email is
		// unique per event); the provider is passed through so hydration
		// uses it directly instead of re-resolving from the comment.
		return $this->hydrate( $rsvp, $identity, $provider );
	}

	/**
	 * Save or update a single RSVP response.
	 *
	 * A `no_status` intent deletes the stored comment instead of saving it.
	 *
	 * @since 0.35.0
	 *
	 * @param Intent   $intent     The intent of the RSVP response.
	 * @param int|null $comment_id ID of an existing RSVP comment to update.
	 *
	 * @return State|bool The saved state, true after a deletion, or false on failure.
	 */
	public function save( Intent $intent, ?int $comment_id ): State|bool {
		// If status is 'no_status', remove the record.
		if ( Status::NO_STATUS === $intent->data->status && $comment_id ) {
			return wp_delete_comment( $comment_id );
		}

		$success = true;

		if ( $comment_id ) {
			$args = get_comment( $comment_id )->to_array();

			if ( $args['comment_author'] ) {
				$intent->data->identity->display_name = $args['comment_author'];
			}
		} else {
			$args = array(
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 1,
				...self::DEFAULT_SAVE_ARGS,
			);
		}

		// Add the identity of the RSVP response.
		$args = $this->add_identity_comment_data( $args, $intent->data->identity );

		$args['comment_author'] = $intent->data->identity->display_name ??
			$intent->provider->get_display_name( $intent->data->identity );
		$args['comment_type']   = Rsvp::COMMENT_TYPE;

		$args = apply_filters( 'gatherpress_save_rsvp', $args );

		if ( ! $comment_id ) {
			$args       = wp_filter_comment( $args );
			$comment_id = wp_insert_comment( $args );
		} else {
			$args['comment_ID'] = $comment_id;
			$success            = wp_update_comment( $args );
		}

		// Insert failure surfaces as a falsy $comment_id; update failure as $success === false.
		if ( ! $comment_id || false === $success ) {
			return false;
		}

		wp_set_object_terms( $comment_id, $intent->data->status->value, Status::TAXONOMY );

		// Stamp the issuing provider so hydration resolves it from the
		// authoritative term instead of inferring from user_id/email —
		// providers with external identities have no fallback to infer
		// from, so without this term their responses could never load.
		wp_set_object_terms( $comment_id, $intent->provider->get_slug(), Provider::TAXONOMY );

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
	 * Get all RSVP responses for the post.
	 *
	 * @since 0.35.0
	 *
	 * @return State[] The hydrated RSVP states.
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
	 * Build an RSVP state from a WP_Comment.
	 *
	 * @since 0.35.0
	 *
	 * @param WP_Comment    $comment  The RSVP comment.
	 * @param Identity|null $identity Optional. The RSVP's identity when already resolved.
	 * @param Provider|null $provider Optional. The RSVP's provider when already resolved.
	 *
	 * @return State|null The hydrated state, or null when the comment is not a resolvable RSVP.
	 */
	private function hydrate(
		WP_Comment $comment,
		?Identity $identity = null,
		?Provider $provider = null
	): ?State {
		if ( Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return null;
		}

		// Resolve provider if not given.
		if ( null === $provider ) {
			$provider = $this->get_identity_provider( $comment );

			if ( null === $provider ) {
				return null;
			}
		}

		// Resolve identity if not given.
		if ( null === $identity ) {
			$identity = $this->get_identity_from_comment( $comment, $provider::get_identity_type() );

			if ( null === $identity ) {
				return null;
			}
		}

		$data = $this->hydrate_data( $comment, $identity );

		return new State( $data, $provider, $comment );
	}

	/**
	 * Get RSVP data from a comment.
	 *
	 * @since 0.35.0
	 *
	 * @param WP_Comment $comment  The RSVP comment.
	 * @param Identity   $identity The RSVP response identity.
	 *
	 * @return Data The RSVP response data.
	 */
	private function hydrate_data( WP_Comment $comment, Identity $identity ): Data {
		$timestamp  = $comment->comment_date;
		$comment_id = (int) $comment->comment_ID;
		$anonymous  = (bool) get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true );
		$guests     = (int) get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true );
		$status     = $this->get_status( $comment_id );

		return new Data( $identity, $status, $guests, $anonymous, $timestamp );
	}

	/**
	 * Read the identity from a comment based on the declared identity type.
	 *
	 * @since 0.35.0
	 *
	 * @param WP_Comment    $comment       The RSVP comment.
	 * @param Identity_Type $identity_type The identity type to read.
	 *
	 * @return Identity|null The identity, or null when the stored identifier is invalid.
	 */
	private function get_identity_from_comment(
		WP_Comment $comment,
		Identity_Type $identity_type
	): ?Identity {
		$identifier = match ( $identity_type ) {
			Identity_Type::WP_USER_ID  => (int) $comment->user_id,
			Identity_Type::EMAIL       => $comment->comment_author_email,
			Identity_Type::URL         => $comment->comment_author_url,
			Identity_Type::EXTERNAL_ID => get_comment_meta(
				(int) $comment->comment_ID,
				self::COMMENT_META_EXTERNAL_ID,
				true
			),
		};

		try {
			$identity = new Identity( $identity_type, $identifier );
		} catch ( InvalidArgumentException ) {
			return null;
		}

		return $identity;
	}

	/**
	 * Get the identity provider for an RSVP response.
	 *
	 * Falls back to the user or email provider when the comment carries no
	 * provider term, so RSVPs saved before provider terms existed still resolve.
	 *
	 * @since 0.35.0
	 *
	 * @param WP_Comment $comment The WordPress comment that stores the RSVP response.
	 *
	 * @return Provider|null The provider, or null when none can be resolved.
	 */
	private function get_identity_provider( WP_Comment $comment ): ?Provider {
		$comment_id    = intval( $comment->comment_ID );
		$provider_slug = $this->get_value_from_object_terms( $comment_id, Provider::TAXONOMY );

		if ( $provider_slug && Provider_Registry::get_instance()->is_registered( $provider_slug ) ) {
			return Provider_Registry::get_instance()->get( $provider_slug );
		}

		// Fallbacks.
		if ( $comment->user_id > 0 ) {
			return Provider_Registry::get_instance()->get( User::get_slug() );
		}

		if ( is_email( $comment->comment_author_email ) ) {
			return Provider_Registry::get_instance()->get( Email::get_slug() );
		}

		return null;
	}

	/**
	 * Get the RSVP status stored for a comment.
	 *
	 * @since 0.35.0
	 *
	 * @param int $comment_id The comment ID of the RSVP response.
	 *
	 * @return Status The stored status, or Status::NO_STATUS when none is set.
	 */
	private function get_status( int $comment_id ): Status {
		$status = Status::tryFrom( (string) $this->get_value_from_object_terms( $comment_id, Status::TAXONOMY ) );

		if ( null === $status ) {
			$status = Status::NO_STATUS;
		}

		return $status;
	}

	/**
	 * Get a single term slug of a taxonomy for an object.
	 *
	 * @since 0.35.0
	 *
	 * @param int    $id       The object ID.
	 * @param string $taxonomy The taxonomy of the term.
	 *
	 * @return string|null The first term's slug, or null when the object has none.
	 */
	private function get_value_from_object_terms( int $id, string $taxonomy ): ?string {
		$terms = wp_get_object_terms( $id, $taxonomy );

		if ( ! empty( $terms ) && is_array( $terms ) ) {
			return $terms[0]->slug;
		}

		return null;
	}

	/**
	 * Get comment query args for an identity.
	 *
	 * @since 0.35.0
	 *
	 * @param Identity $identity The identity.
	 *
	 * @return array<array<int|string>|int|string> The comment query args.
	 */
	private function get_identity_query_args( Identity $identity ): array {
		$args = array();

		switch ( $identity->type ) {
			case Identity_Type::EMAIL:
				$args['author_email'] = $identity->value;
				break;

			case Identity_Type::URL:
				$args['author_url'] = $identity->value;
				break;

			case Identity_Type::WP_USER_ID:
				$args['user_id'] = $identity->value;
				break;

			default:
				// External identifiers are matched via comment meta.
				$args['comment_meta'][ self::COMMENT_META_EXTERNAL_ID ] = $identity->value;
				break;
		}

		return $args;
	}

	/**
	 * Add identity fields to comment data for insert or update.
	 *
	 * @since 0.35.0
	 *
	 * @param array    $args     The current comment data args.
	 * @param Identity $identity The identity.
	 *
	 * @return array<array<int|string>|int|string> The comment data args including the identity.
	 */
	private function add_identity_comment_data( array $args, Identity $identity ): array {
		switch ( $identity->type ) {
			case Identity_Type::EMAIL:
				$args['comment_author_email'] = $identity->value;
				break;

			case Identity_Type::URL:
				$args['comment_author_url'] = $identity->value;
				break;

			case Identity_Type::WP_USER_ID:
				$args['user_id'] = $identity->value;
				break;

			default:
				// External identifiers are stored as comment meta.
				$args['comment_meta'][ self::COMMENT_META_EXTERNAL_ID ] = $identity->value;
				break;
		}

		return $args;
	}
}
