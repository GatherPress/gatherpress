<?php
/**
 * The one authoritative identity of a single occurrence.
 *
 * An occurrence is identified by the composite
 * `(owner_post_id, recurrence_id)`, never by the recurrence identifier alone
 * and never by the post a request happened to name. Before this class the
 * composite existed in five partial forms: the occurrence table's primary
 * key, `Context`'s resolved row, the `_gatherpress_occurrence` term slug, the
 * RSVP cache key, and a magic token's comment. Each consumer rebuilt it
 * from whichever half was nearest. That is what let a token issued for one
 * occurrence authorize a write to another: routing resolved one composite,
 * authorization compared a different one, and the mutation used a third.
 *
 * This class is the single seam every one of those consumers now goes through,
 * and it exists to make one sequence structurally cheap to follow:
 *
 * 1. **Resolve** the exact composite, from storage, without disclosing it.
 * 2. **Authorize** that composite, comparing whole identities rather than
 *    halves.
 * 3. **Use** the same immutable instance for the mutation, the term, and the
 *    cache key. Nothing re-resolves through ambient context after permission
 *    has been granted.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Occurrence_Identity.
 *
 * An immutable `(owner_post_id, recurrence_id)` pair, plus the three resolvers
 * that are allowed to produce one.
 *
 * **The ownership invariant.** `owner_post_id` is always the `series_post_id`
 * of the occurrence row itself, which is also the prefix the occurrence term
 * slug carries. It is never the post a request named, because a forward split
 * legitimately moves an occurrence onto a sibling post of the same
 * series while callers keep naming the post they first saw.
 *
 * An RSVP comment scoped to an occurrence must satisfy
 * `comment_post_ID === owner_post_id`. Production reads rely on it:
 * `Rsvp\Storage` narrows by `post_id` *and* by the occurrence term, so a row
 * whose two owners disagree is readable through neither. Any operation that
 * changes one owner must change the other in the same recoverable step.
 *
 * @since 0.36.0
 */
final class Occurrence_Identity {

	/**
	 * The canonical shape of a recurrence identifier.
	 *
	 * `Ymd\THis`, and nothing else. Matching this proves only that
	 * the string is well formed; it says nothing about whether the occurrence
	 * exists, which is deliberate. Syntax is all a REST argument validator may
	 * check, because validation runs before the permission callback and a check
	 * that touched storage there would answer "does this occurrence exist?" for
	 * a caller who has not yet been authorized to ask.
	 *
	 * Anchored with `\A` and `\z` rather than `^` and `$`, because in PCRE `$`
	 * also matches before a final newline and this gate's contract is
	 * byte-exactness: the classic form path reads the posted field with no
	 * sanitizer, so the unsanitized value is what reaches this pattern.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const CANONICAL_PATTERN = '/\A\d{8}T\d{6}\z/';

	/**
	 * Class constructor.
	 *
	 * Private so an identity can only come from one of the three resolvers
	 * below. A hand-built pair would be an owner nobody verified, which is the
	 * defect this class exists to remove.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $owner_post_id Post the occurrence row belongs to.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 */
	private function __construct(
		public readonly int $owner_post_id,
		public readonly string $recurrence_id
	) {
	}

	/**
	 * Check a recurrence identifier's shape without touching storage.
	 *
	 * @since 0.36.0
	 *
	 * @param string $recurrence_id The candidate identifier.
	 *
	 * @return bool True when the string is a canonical `Ymd\THis` identifier.
	 */
	public static function is_canonical( string $recurrence_id ): bool {
		return 1 === preg_match( self::CANONICAL_PATTERN, $recurrence_id );
	}

	/**
	 * Resolve a candidate identifier to the occurrence that owns it.
	 *
	 * Step 1 of resolve-authorize-use, and the only step that reads storage.
	 * Resolution is across the whole series rather than pinned to the
	 * post the caller named, so an occurrence a forward split has moved still
	 * resolves; the identity that comes back names the post it moved *to*.
	 *
	 * Callers must not treat a null return as "fall back to the series". A
	 * candidate that does not resolve is a candidate the caller must be
	 * refused, and refusing must not depend on whether the candidate was real,
	 * or the refusal becomes an existence oracle.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Post ID the caller named.
	 * @param string $recurrence_id Candidate occurrence identifier.
	 *
	 * @return self|null The resolved identity, or null when the series carries no such occurrence.
	 */
	public static function resolve( int $post_id, string $recurrence_id ): ?self {
		if ( ! self::is_canonical( $recurrence_id ) ) {
			return null;
		}

		$row = Context::resolve_in_series( $post_id, $recurrence_id );

		if ( null === $row ) {
			return null;
		}

		return new self( (int) $row['series_post_id'], (string) $row['recurrence_id'] );
	}

	/**
	 * Read the identity an already-stored RSVP comment belongs to.
	 *
	 * Both halves come off the comment's own `_gatherpress_occurrence` term
	 * rather than from `comment_post_ID`, because the term slug is what every
	 * occurrence-scoped read queries by. Callers that need the pair to agree
	 * should compare this against the comment's post explicitly rather than
	 * assume it.
	 *
	 * @since 0.36.0
	 *
	 * @param int $comment_id RSVP comment ID.
	 *
	 * @return self|null The identity, or null when the RSVP is series-wide.
	 */
	public static function for_comment( int $comment_id ): ?self {
		$occurrence = Rsvp_Occurrence::occurrence_for_comment( $comment_id );

		if ( null === $occurrence ) {
			return null;
		}

		return new self( (int) $occurrence['series_post_id'], (string) $occurrence['recurrence_id'] );
	}

	/**
	 * Read the identity the current request or loop iteration is scoped to.
	 *
	 * The ambient counterpart to `resolve()`, for the write paths that run
	 * after authorization has already fixed the identity. On a site with no
	 * recurring events this returns null without touching occurrence storage.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post whose RSVPs are being read or written.
	 *
	 * @return self|null The identity in play, or null outside occurrence context.
	 */
	public static function current( int $post_id ): ?self {
		$occurrence = Rsvp_Occurrence::current_occurrence( $post_id );

		if ( null === $occurrence ) {
			return null;
		}

		return new self( (int) $occurrence['series_post_id'], (string) $occurrence['recurrence_id'] );
	}

	/**
	 * Compare two identities, either of which may be absent.
	 *
	 * Step 2 of resolve-authorize-use. Absent is a value here, not a wildcard:
	 * two nulls match because both name the series, and one null never matches
	 * an occurrence. A credential issued for one occurrence therefore does not
	 * act series-wide, and a series-wide credential does not act on an
	 * occurrence.
	 *
	 * @since 0.36.0
	 *
	 * @param self|null $held      The identity the caller's credential carries.
	 * @param self|null $requested The identity the request names.
	 *
	 * @return bool True when the two name exactly the same thing.
	 */
	public static function matches( ?self $held, ?self $requested ): bool {
		if ( null === $held || null === $requested ) {
			return null === $held && null === $requested;
		}

		return $held->owner_post_id === $requested->owner_post_id
			&& $held->recurrence_id === $requested->recurrence_id;
	}

	/**
	 * Build the `_gatherpress_occurrence` term slug for this identity.
	 *
	 * @since 0.36.0
	 *
	 * @return string The term slug.
	 */
	public function term_slug(): string {
		return Rsvp_Occurrence::term_slug( $this->owner_post_id, $this->recurrence_id );
	}

	/**
	 * Express the identity in the array shape the older occurrence helpers use.
	 *
	 * @since 0.36.0
	 *
	 * @return array{series_post_id: int, recurrence_id: string} The composite key.
	 */
	public function to_array(): array {
		return array(
			'series_post_id' => $this->owner_post_id,
			'recurrence_id'  => $this->recurrence_id,
		);
	}
}
