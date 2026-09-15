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

use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
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
use WP_Term;

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
	 * @var array<string, string>
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
	protected readonly Query $rsvp_query;

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
		$args = $this->scope_to_occurrence( $args );

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
			$existing = get_comment( $comment_id );

			// The row can be deleted between the lookup and this save.
			if ( ! $existing instanceof WP_Comment ) {
				return false;
			}

			$args = $existing->to_array();

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

		// Bind the response to the occurrence the request is rendering, so the
		// same responder can hold an independent RSVP on every date in a
		// series rather than one that follows them across all of them. The term
		// is keyed on the occurrence's own series post, not on the post the
		// request named, so an occurrence a forward split has moved onto a
		// sibling post is stamped with the slug its readers scope by.
		$occurrence = Rsvp_Occurrence::current_occurrence( $this->post_id );

		if ( null !== $occurrence ) {
			Rsvp_Occurrence::get_instance()->assign(
				$comment_id,
				$occurrence['series_post_id'],
				$occurrence['recurrence_id']
			);
		}

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

		// Report a failed save rather than a successful one.
		if ( ! $comment instanceof WP_Comment ) {
			return false;
		}

		return $this->hydrate( $comment, $intent->data->identity, $intent->provider ) ?? false;
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

		$comments = $this->rsvp_query->get_rsvps( $this->scope_to_occurrence( $args ) );

		$this->prime_term_cache( array_map( 'intval', wp_list_pluck( $comments, 'comment_ID' ) ) );

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
	 * Fetch every comment's status and provider terms in a single query.
	 *
	 * Hydration reads one status term and one provider term per comment, and
	 * asking for them a comment at a time made a full read cost two queries per
	 * stored RSVP. Every write reads the whole set twice, once through
	 * `Rsvp::attending_limit_reached()` and once through
	 * `Rsvp::check_waiting_list()`. The cost of the nth response was therefore
	 * proportional to n, and filling an event was quadratic.
	 *
	 * This mirrors `update_object_term_cache()` rather than calling it, because
	 * that function primes every taxonomy registered on comments. Naming the
	 * two taxonomies hydration actually reads keeps the occurrence taxonomy out
	 * of the SQL a site with no recurring events runs, and skips work
	 * for a term nothing here goes on to read.
	 *
	 * @since 0.36.0
	 *
	 * @param int[] $comment_ids RSVP comment IDs about to be hydrated.
	 *
	 * @return void
	 */
	private function prime_term_cache( array $comment_ids ): void {
		if ( empty( $comment_ids ) ) {
			return;
		}

		$taxonomies     = array( Status::TAXONOMY, Provider::TAXONOMY );
		$non_cached_ids = array();

		foreach ( $taxonomies as $taxonomy ) {
			$cached = wp_cache_get_multiple( $comment_ids, sprintf( '%s_relationships', $taxonomy ) );

			foreach ( $cached as $comment_id => $value ) {
				if ( false === $value ) {
					$non_cached_ids[] = (int) $comment_id;
				}
			}
		}

		// No early return for an empty set: `wp_get_object_terms()` bails on one
		// itself, so a guard here would be a branch no test could distinguish.
		// The list is not deduplicated either. A comment uncached in both
		// taxonomies appears twice, `IN ( … )` collapses the duplicate in SQL,
		// and `wp_cache_add()` makes the second pass over it a no-op.
		$terms = wp_get_object_terms(
			$non_cached_ids,
			$taxonomies,
			array(
				'fields'                 => 'all_with_object_id',
				'update_term_meta_cache' => false,
			)
		);

		// Only when one of the two taxonomies is not registered, which is a
		// misconfigured request rather than a state worth caching an answer for.
		if ( is_wp_error( $terms ) ) {
			return;
		}

		$object_terms = array();

		/**
		 * Terms carrying the object they belong to, as `all_with_object_id` returns them.
		 *
		 * @var WP_Term[] $terms
		 */
		foreach ( $terms as $term ) {
			// Read through `to_array()`: `all_with_object_id` attaches
			// `object_id` as a dynamic property, which is not part of WP_Term's
			// declared shape.
			$fields = $term->to_array();

			$object_terms[ $fields['object_id'] ][ $fields['taxonomy'] ][] = $fields['term_id'];
		}

		foreach ( $non_cached_ids as $comment_id ) {
			foreach ( $taxonomies as $taxonomy ) {
				// An RSVP with no term in a taxonomy still caches the empty
				// answer, so the next read does not go looking for it again.
				wp_cache_add(
					$comment_id,
					$object_terms[ $comment_id ][ $taxonomy ] ?? array(),
					sprintf( '%s_relationships', $taxonomy )
				);
			}
		}
	}

	/**
	 * Narrow comment query args to the occurrence the request is rendering.
	 *
	 * The scoping rides the `tax_query` var `Rsvp\Query::taxonomy_query()`
	 * already splices into the comment clauses, so no new SQL, filter, or table
	 * is involved. Outside occurrence context the args are returned untouched,
	 * as they are on every site with no recurring events at all.
	 *
	 * No `cache_domain` is set here. `WP_Comment_Query::get_comments()` builds
	 * its cache key from its declared query vars only, and `tax_query` is not
	 * one of them, so a scoped query does need one. `Rsvp\Query` derives it
	 * for every taxonomy-scoped read in `ensure_cache_domain()`, from a hash of
	 * the whole `tax_query`. Setting one here as well would win over that
	 * derivation (it short-circuits on a non-empty `cache_domain`) and disable
	 * the single funnel every RSVP read passes through, leaving two mechanisms
	 * where one is enough. The local one was the weaker of the two, keyed
	 * on the identifier alone where the derived key covers the series post too.
	 *
	 * The `tax_query` is built from the occurrence's own `series_post_id` rather
	 * than from `$this->post_id`, so a read on any post of a series finds the
	 * RSVPs written under the post the occurrence actually lives on.
	 *
	 * @since 0.36.0
	 *
	 * @param array $args Comment query args.
	 *
	 * @return array The args, scoped to one occurrence when the request is rendering one.
	 */
	private function scope_to_occurrence( array $args ): array {
		$occurrence = Rsvp_Occurrence::current_occurrence( $this->post_id );

		if ( null === $occurrence ) {
			return $args;
		}

		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		$args['tax_query'] = Rsvp_Occurrence::get_instance()->tax_query(
			$occurrence['series_post_id'],
			$occurrence['recurrence_id']
		);

		return $args;
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

		// Carry the name the response was saved with. Without it a reader falls
		// back to the provider's display name, which for an email response is
		// the address itself.
		if ( ! empty( $comment->comment_author ) ) {
			$identity->display_name = (string) $comment->comment_author;
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
	 * Reads the object term cache first, which `all()` primes for the whole
	 * result set in a single query. `get_object_term_cache()` returns false,
	 * rather than an empty array, when the object has not been primed. That is
	 * the only case that still costs a query of its own.
	 *
	 * @since 0.35.0
	 *
	 * @param int    $id       The object ID.
	 * @param string $taxonomy The taxonomy of the term.
	 *
	 * @return string|null The first term's slug, or null when the object has none.
	 */
	private function get_value_from_object_terms( int $id, string $taxonomy ): ?string {
		$terms = get_object_term_cache( $id, $taxonomy );

		if ( false === $terms ) {
			$terms = wp_get_object_terms( $id, $taxonomy );
		}

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
	 * @param array<string, mixed> $args     The current comment data args.
	 * @param Identity             $identity The identity.
	 *
	 * @return array<string, mixed> The comment data args including the identity.
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
