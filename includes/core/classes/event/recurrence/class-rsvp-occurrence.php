<?php
/**
 * Links an RSVP comment to the occurrence it belongs to.
 *
 * The link is a native taxonomy term on the comment, read through the existing
 * `Rsvp\Query::taxonomy_query()` path. Status and provider already use that
 * same mechanism. It is not a mapping table, not
 * comment meta, and not a provisional post ID.
 *
 * The term slug format is produced by exactly one function, `term_slug()`, so
 * a sentinel "all occurrences" slug is a one-line addition later rather
 * than a format change scattered across call sites.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Traits\Singleton;
use WP_Error;
use WP_Term;

/**
 * Class Rsvp_Occurrence.
 *
 * Singleton owning the `_gatherpress_occurrence` comment taxonomy.
 *
 * @since 0.36.0
 */
final class Rsvp_Occurrence {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Internal comment taxonomy joining an RSVP to an occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TAXONOMY = '_gatherpress_occurrence';

	/**
	 * Class constructor.
	 *
	 * Nothing to hook: the `delete_comment` relationship cleanup this class
	 * used to own cleans all three RSVP comment taxonomies, only one of which
	 * is about recurrence, and now lives on `Rsvp\Cleanup` alongside the
	 * hard-delete cron it belongs with.
	 *
	 * The declaration is not decorative. `Traits\Singleton` declares no
	 * constructor of its own, so dropping this one would hand the class PHP's
	 * implicit **public** constructor and make `new Rsvp_Occurrence()` legal
	 * from anywhere. That allows two instances of a singleton, which is the one
	 * thing `get_instance()` exists to prevent.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
	}

	/**
	 * Build the term slug for one occurrence.
	 *
	 * The single source of truth for the slug format. The composite is passed
	 * through `sanitize_title()` so the value this returns is byte-identical to
	 * what WordPress stores in `wp_terms.slug`. A caller can look a term up by
	 * this string without a second sanitization step, and the assigned and
	 * queried slugs cannot drift apart.
	 *
	 * The series post ID prefix is what makes a collision structurally
	 * impossible: two series can share a recurrence identifier, but not a post
	 * ID.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return string The term slug, in `{series_post_id}-{recurrence_id}` form.
	 */
	public static function term_slug( int $post_id, string $recurrence_id ): string {
		return sanitize_title( sprintf( '%d-%s', $post_id, $recurrence_id ) );
	}

	/**
	 * Resolve the occurrence the current request scopes this post's RSVPs to.
	 *
	 * Returns the **occurrence's own** `series_post_id` alongside the
	 * identifier, and callers must key their term slug off that rather than off
	 * the post they asked about. Resolution is series-wide: `Context` resolves an
	 * incoming identifier through `Series::resolve_post_ids()`, so once the
	 * forward split moves an occurrence onto a sibling post of the same series,
	 * the context legitimately holds a row whose `series_post_id` is not the
	 * post the request named. This method used to compare the two for equality,
	 * which rejected exactly the case `Context` had gone out of its way
	 * to admit, and every scoping consumer then fell back to series-wide with no
	 * error anywhere: the RSVP would be written with no occurrence term at all
	 * while the visitor believed they had booked a specific date.
	 *
	 * The first guard keeps the cost off ordinary sites: a site with no recurring events never
	 * reaches the occurrence context at all, so every RSVP read and write runs
	 * exactly the SQL it ran before this class existed.
	 *
	 * **The row's own stamp wins over the request's occurrence**, matching
	 * `Context::resolve()`. See its docblock for why, and for why the reverse
	 * order is a defect rather than a preference. The stamp is per-row and
	 * unambiguous; the request has one occurrence for the whole response, so
	 * preferring it collapses every row of a loop rendered on a singular
	 * occurrence page onto the requested date.
	 *
	 * The request arm keeps its widened-series membership check, and that
	 * admission is deliberate, for the split-series reason given above. The
	 * identity comparison stays ahead of `resolve_post_ids()`, so a one-post
	 * series never reaches the filter. That is the whole of today's traffic.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID whose RSVPs are being read or written.
	 *
	 * @return array{series_post_id: int, recurrence_id: string}|null The occurrence, or null when the
	 *               request is not scoped to one of this post's series.
	 */
	public static function current_occurrence( int $post_id ): ?array {
		if ( ! Query::site_has_recurring_events() ) {
			return null;
		}

		// The occurrence the current loop iteration was stamped with, which is
		// pure property access on the result object and already scoped to the
		// post it was stamped onto, so it needs no membership check of its own.
		$occurrence = Context::get_instance()->loop_occurrence( $post_id );

		if ( null === $occurrence ) {
			$request        = Context::get_instance()->current();
			$series_post_id = ( null === $request ) ? 0 : (int) $request['series_post_id'];

			// The request's occurrence applies only when it belongs to this
			// post or to a sibling post of its series. On an archive or Query
			// Loop there is no request occurrence at all, and without the stamp
			// above every row would read the same series-wide RSVP state: an
			// attendee on the 18th appears to be attending every date.
			if (
				null !== $request
				&& (
					$series_post_id === $post_id
					|| in_array( $series_post_id, Series::get_instance()->resolve_post_ids( $post_id ), true )
				)
			) {
				$occurrence = $request;
			}
		}

		if ( null === $occurrence ) {
			return null;
		}

		return array(
			'series_post_id' => (int) $occurrence['series_post_id'],
			'recurrence_id'  => (string) $occurrence['recurrence_id'],
		);
	}

	/**
	 * Resolve just the identifier of the occurrence the request is scoped to.
	 *
	 * The thin accessor for the consumers that need the identifier without the
	 * post it belongs to: `block_context()` publishes it in the client's
	 * composite state key, and `Blocks\Rsvp_Form::occurrence_input()` emits it
	 * as the classic form's hidden field.
	 *
	 * Anything that also needs the owning post, in particular anything
	 * composing an occurrence **term slug**, must use `current_occurrence()`
	 * instead. Its docblock explains why.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID whose RSVPs are being read or written.
	 *
	 * @return string|null The recurrence identifier, or null when the request is not scoped to one.
	 */
	public static function current_recurrence_id( int $post_id ): ?string {
		$occurrence = self::current_occurrence( $post_id );

		return null === $occurrence ? null : $occurrence['recurrence_id'];
	}

	/**
	 * Report whether an event's classic RSVP writes need an explicit scope.
	 *
	 * True exactly when the event is a recurring series, which is when a
	 * response can mean either one date or all of them and the two must never
	 * be conflated. `Blocks\Rsvp_Form` renders a scope marker on every such
	 * form, an occurrence identifier or the explicit series value, and
	 * `Rsvp\Form` refuses a submission that carries neither: a marker-less
	 * submission to a recurring event can only come from markup rendered
	 * before the marker existed, and treating it as an intentional series-wide
	 * RSVP writes data nothing can afterwards tell apart from one.
	 *
	 * The presence test is `Occurrences::has_recurrence_rule()`, the same
	 * mirror every other series-shaped decision reads, so the renderer and the
	 * handler cannot disagree about which events require the marker. The site
	 * flag guard runs first: on a site with no recurring events this returns
	 * false from the autoloaded option alone, and both callers keep the exact
	 * behavior they had before the marker existed.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Event post ID the form belongs to.
	 *
	 * @return bool True when submissions must carry an explicit scope marker.
	 */
	public static function requires_explicit_scope( int $post_id ): bool {
		return Query::site_has_recurring_events()
			&& Occurrences::get_instance()->has_recurrence_rule( $post_id );
	}

	/**
	 * Build the interactivity block context one rendered row publishes to the client.
	 *
	 * The single source of truth for that payload, and the reason it exists is
	 * that occurrence identity is `(post_id, recurrence_id)`, and until this
	 * method every block emitted `postId` alone. On an archive or Query Loop the
	 * whole point is that one post appears many times, so a client store keyed
	 * on the post ID collapsed every row of a series into one entry. An RSVP
	 * on one date visibly applied to all of them, over server markup that was
	 * already correct per row.
	 *
	 * A post with no occurrence in play gets back exactly what it got before:
	 * `array( 'postId' => $post_id )`, byte-identical JSON. That covers every
	 * ordinary event, and every post on a site that has never authored a
	 * recurring series,
	 * so its state key stays the bare post ID and its request bodies do not
	 * change. The two key shapes cannot collide, either: a bare key is
	 * `/^\d+$/` and a composite one always carries a `:` separator.
	 *
	 * Resolution goes through `current_recurrence_id()` rather than through
	 * `Context::cache_key()` so the identity the client is handed is the same
	 * one the server scoped this row's RSVP reads by, widened-series admission
	 * included. The cost guard rides along on that call's first guard: on a site with no
	 * recurring events it returns before touching the occurrence table.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID the block instance is rendering.
	 *
	 * @return array{postId: int, recurrenceId?: string} The block's `data-wp-context` payload.
	 */
	public static function block_context( int $post_id ): array {
		$context       = array( 'postId' => $post_id );
		$recurrence_id = self::current_recurrence_id( $post_id );

		if ( null !== $recurrence_id ) {
			$context['recurrenceId'] = $recurrence_id;
		}

		return $context;
	}

	/**
	 * Resolve the occurrence an already-stored RSVP belongs to.
	 *
	 * Reads the comment's own `_gatherpress_occurrence` term rather than the
	 * request, which is what makes it usable from callbacks that run before
	 * `wp`. `Rsvp\Token::handle_rsvp_token()` on `init` is the one that
	 * matters, since without this its cache invalidation drops only the
	 * series-wide key and leaves the occurrence's warm counts stale for the
	 * length of `Cache::CACHE_EXPIRATION`, shared across every visitor under a
	 * persistent object cache.
	 *
	 * @since 0.36.0
	 *
	 * @param int $comment_id RSVP comment ID.
	 *
	 * @return string|null The occurrence identifier, or null when the RSVP is not scoped to one.
	 */
	public static function recurrence_id_for_comment( int $comment_id ): ?string {
		$occurrence = self::occurrence_for_comment( $comment_id );

		return null === $occurrence ? null : $occurrence['recurrence_id'];
	}

	/**
	 * Resolve the whole composite key an already-stored RSVP belongs to.
	 *
	 * The counterpart to `current_occurrence()` for callbacks that have no
	 * request to read. The RSVP confirmation email is the one that matters,
	 * since it is composed while a comment is inserted (`Rsvp\Form`, reached
	 * from the REST route and from `comment_post`) and is then *sent*, so a
	 * link to the wrong date cannot be corrected afterwards.
	 *
	 * Both halves of the composite identity come off the term slug rather than
	 * from the comment's `comment_post_ID`: `assign()` keys the slug on the
	 * **occurrence's own** `series_post_id`, which the forward split makes
	 * legitimately different from the post the responder RSVPd on. Composing a
	 * URL from `comment_post_ID` would name a post the occurrence no longer
	 * lives on, and `Rewrite::parse_request()` matches on the exact pair.
	 *
	 * The recurring-events check is the first guard: on a site with no recurring events this
	 * returns without reading a term relationship at all, so the email path
	 * runs byte-identical SQL there.
	 *
	 * @since 0.36.0
	 *
	 * @param int $comment_id RSVP comment ID.
	 *
	 * @return array{series_post_id: int, recurrence_id: string}|null The occurrence's composite key,
	 *               or null when the RSVP is not scoped to one.
	 */
	public static function occurrence_for_comment( int $comment_id ): ?array {
		if ( ! Query::site_has_recurring_events() || 1 > $comment_id ) {
			return null;
		}

		$slugs = wp_get_object_terms( $comment_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $slugs ) || empty( $slugs ) ) {
			return null;
		}

		$slug          = (string) $slugs[0];
		$recurrence_id = self::recurrence_id_from_slug( $slug );
		$post_id       = self::series_post_id_from_slug( $slug );

		if ( null === $recurrence_id || null === $post_id ) {
			return null;
		}

		return array(
			'series_post_id' => $post_id,
			'recurrence_id'  => $recurrence_id,
		);
	}

	/**
	 * Recover the series post ID from a term slug.
	 *
	 * The other half of `recurrence_id_from_slug()`'s inverse. The identifier
	 * is `Ymd\THis` and carries no `-`, and a post ID is decimal digits, so the
	 * final `-` is the only separator and the prefix is the post ID whole. A
	 * prefix that is not a positive integer means the slug was not produced by
	 * `term_slug()` at all, and is refused rather than cast to zero.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug Term slug, in the form `term_slug()` produces.
	 *
	 * @return int|null The series post ID, or null when the slug carries none.
	 */
	public static function series_post_id_from_slug( string $slug ): ?int {
		$separator = strrpos( $slug, '-' );

		if ( false === $separator ) {
			return null;
		}

		$prefix = substr( $slug, 0, $separator );

		return ctype_digit( $prefix ) && 0 < (int) $prefix ? (int) $prefix : null;
	}

	/**
	 * Recover the canonical occurrence identifier from a term slug.
	 *
	 * The exact inverse of `term_slug()`, and it has to be: `term_slug()`
	 * passes the composite through `sanitize_title()`, which lowercases it, so
	 * the stored slug reads `12-20260903t180000` while every cache key and
	 * every occurrence row carries `20260903T180000`. `Ymd\THis` contains
	 * exactly one letter, so uppercasing recovers the identifier byte for
	 * byte; handing the lowercased form back would compose a cache key that
	 * matches nothing.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug Term slug, in the form `term_slug()` produces.
	 *
	 * @return string|null The occurrence identifier, or null when the slug carries none.
	 */
	public static function recurrence_id_from_slug( string $slug ): ?string {
		$separator = strrpos( $slug, '-' );

		if ( false === $separator || strlen( $slug ) - 1 === $separator ) {
			return null;
		}

		return strtoupper( substr( $slug, $separator + 1 ) );
	}

	/**
	 * Attach an RSVP comment to an occurrence.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $comment_id    RSVP comment ID.
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return bool True when the term was assigned.
	 */
	public function assign( int $comment_id, int $post_id, string $recurrence_id ): bool {
		// An incomplete composite key would produce a slug that silently
		// collides with every other incomplete one, so refuse it rather than
		// writing a term nothing can be scoped by.
		if ( 1 > $comment_id || 1 > $post_id || '' === $recurrence_id ) {
			return false;
		}

		$assigned = wp_set_object_terms(
			$comment_id,
			self::term_slug( $post_id, $recurrence_id ),
			self::TAXONOMY
		);

		return ! is_wp_error( $assigned ) && ! empty( $assigned );
	}

	/**
	 * Build the taxonomy query scoping RSVPs to one occurrence.
	 *
	 * Passed through the existing `Rsvp\Query::get_rsvps()` path, so there is no
	 * new SQL, no new filter, and no table.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return array A `tax_query` clause.
	 */
	public function tax_query( int $post_id, string $recurrence_id ): array {
		return array(
			array(
				'taxonomy' => self::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( self::term_slug( $post_id, $recurrence_id ) ),
			),
		);
	}

	/**
	 * Move every trace of an occurrence's RSVP ownership onto another post.
	 *
	 * **The authoritative owner of an occurrence-scoped RSVP is the occurrence
	 * row's `series_post_id`, and `comment_post_ID` must equal it.** The two
	 * halves are conjoined in production reads: `Rsvp\Storage` narrows by
	 * `post_id` *and* by the occurrence term, so a comment whose post and term
	 * name different posts is readable through neither, from either side. That
	 * is the state a rename-only split left behind, and it stranded a real
	 * roster rather than merely mislabeling one.
	 *
	 * `comment_post_ID` is also what WordPress itself keys on for capability
	 * checks, moderation, the trash cascade, and `new Rsvp( $post_id )`, so
	 * the comment follows the row rather than the row following the comment.
	 *
	 * The whole migration is atomic: a term that cannot be renamed or a comment
	 * that cannot be moved undoes everything this call already did and reports
	 * a `WP_Error`, so a caller never has to reason about a half-moved roster.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $from_post_id   Post the occurrences currently belong to.
	 * @param int      $to_post_id     Post they move to.
	 * @param string[] $recurrence_ids Occurrence identifiers to move.
	 *
	 * @return array{terms: int, comments: int[]}|WP_Error What moved, or the first failure.
	 */
	public function migrate_owner( int $from_post_id, int $to_post_id, array $recurrence_ids ) {
		$migrated = array(
			'terms'    => 0,
			'comments' => array(),
		);

		if ( $from_post_id === $to_post_id || array() === $recurrence_ids ) {
			return $migrated;
		}

		$done = array();

		foreach ( $recurrence_ids as $recurrence_id ) {
			$term = get_term_by(
				'slug',
				self::term_slug( $from_post_id, (string) $recurrence_id ),
				self::TAXONOMY
			);

			// An occurrence nobody has RSVPd to has no term to move.
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			// Read the membership before the rename: `term_relationships` keys
			// on `term_taxonomy_id`, which the rename leaves alone, but reading
			// first keeps the comment set and the term in one snapshot.
			$comment_ids = get_objects_in_term( array( (int) $term->term_id ), self::TAXONOMY );
			$comment_ids = is_wp_error( $comment_ids ) ? array() : array_map( 'intval', $comment_ids );

			$slug    = self::term_slug( $to_post_id, (string) $recurrence_id );
			$updated = wp_update_term(
				(int) $term->term_id,
				self::TAXONOMY,
				array(
					'name' => $slug,
					'slug' => $slug,
				)
			);

			// A destination slug that already exists means two occurrences would
			// merge their RSVPs into one term. That is data loss, not a
			// skippable edge, so the caller is told rather than left with a
			// silently partial move.
			if ( is_wp_error( $updated ) ) {
				$this->revert_migration( $to_post_id, $from_post_id, $done );

				return new WP_Error(
					'gatherpress_rsvp_term_not_renamed',
					__( 'An RSVP occurrence term could not be moved to the new event.', 'gatherpress' ),
					array( 'status' => 500 )
				);
			}

			$done[] = (string) $recurrence_id;

			foreach ( $comment_ids as $comment_id ) {
				$moved = wp_update_comment(
					array(
						'comment_ID'      => $comment_id,
						'comment_post_ID' => $to_post_id,
					),
					true
				);

				if ( is_wp_error( $moved ) ) {
					$this->revert_migration( $to_post_id, $from_post_id, $done );

					return new WP_Error(
						'gatherpress_rsvp_comment_not_moved',
						__( 'An RSVP could not be moved to the new event.', 'gatherpress' ),
						array( 'status' => 500 )
					);
				}

				$migrated['comments'][] = (int) $comment_id;
			}
		}

		$migrated['terms'] = count( $done );

		return $migrated;
	}

	/**
	 * Undo the part of a migration that had already landed.
	 *
	 * Deliberately not `migrate_owner()` itself. The failure being undone is
	 * very often a store that is failing for every write, so a self-call would
	 * fail again, undo again, and recurse without bound. This does the same two
	 * writes in reverse and reports nothing, because there is nothing a caller
	 * could do with a failure to undo a failure.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $from_post_id   Post the partial migration moved things to.
	 * @param int      $to_post_id     Post they belong back on.
	 * @param string[] $recurrence_ids Identifiers the partial migration reached.
	 *
	 * @return void
	 */
	private function revert_migration( int $from_post_id, int $to_post_id, array $recurrence_ids ): void {
		foreach ( $recurrence_ids as $recurrence_id ) {
			$term = get_term_by(
				'slug',
				self::term_slug( $from_post_id, (string) $recurrence_id ),
				self::TAXONOMY
			);

			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$comment_ids = get_objects_in_term( array( (int) $term->term_id ), self::TAXONOMY );
			$slug        = self::term_slug( $to_post_id, (string) $recurrence_id );

			wp_update_term(
				(int) $term->term_id,
				self::TAXONOMY,
				array(
					'name' => $slug,
					'slug' => $slug,
				)
			);

			foreach ( is_wp_error( $comment_ids ) ? array() : $comment_ids as $comment_id ) {
				wp_update_comment(
					array(
						'comment_ID'      => (int) $comment_id,
						'comment_post_ID' => $to_post_id,
					)
				);
			}
		}
	}

	/**
	 * Read every RSVP comment attached to a post's occurrence terms.
	 *
	 * The snapshot a rollback needs: deleting a term deletes its
	 * `term_relationships` rows, so a demotion that drops occurrence scoping
	 * has to be able to say which comments carried which occurrence before it
	 * ran.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $post_id        Series post ID the terms name.
	 * @param string[] $recurrence_ids Occurrence identifiers to read.
	 *
	 * @return array<string, int[]> Comment IDs, keyed by recurrence identifier.
	 */
	public function memberships( int $post_id, array $recurrence_ids ): array {
		$memberships = array();

		foreach ( $recurrence_ids as $recurrence_id ) {
			$term = get_term_by( 'slug', self::term_slug( $post_id, (string) $recurrence_id ), self::TAXONOMY );

			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$comment_ids = get_objects_in_term( array( (int) $term->term_id ), self::TAXONOMY );

			if ( is_wp_error( $comment_ids ) || empty( $comment_ids ) ) {
				continue;
			}

			$memberships[ (string) $recurrence_id ] = array_map( 'intval', $comment_ids );
		}

		return $memberships;
	}

	/**
	 * Re-attach comments to occurrence terms a rollback has to put back.
	 *
	 * The inverse of `memberships()`, and deliberately expressed through
	 * `assign()` so the recreated term carries the slug `term_slug()` produces
	 * rather than one this method composed itself. The recreated term is a new
	 * `term_taxonomy_id`, which nothing observable depends on: every read
	 * resolves by slug.
	 *
	 * @since 0.36.0
	 *
	 * @param int                  $post_id     Series post ID the terms name.
	 * @param array<string, int[]> $memberships Comment IDs, keyed by recurrence identifier.
	 *
	 * @return void
	 */
	public function restore_memberships( int $post_id, array $memberships ): void {
		foreach ( $memberships as $recurrence_id => $comment_ids ) {
			foreach ( $comment_ids as $comment_id ) {
				$this->assign( (int) $comment_id, $post_id, (string) $recurrence_id );
			}
		}
	}

	/**
	 * Drop the occurrence terms naming a post's occurrences.
	 *
	 * Used when a side of a split is demoted to a plain non-recurring event.
	 * Deleting the term removes its `term_relationships` rows, which is exactly
	 * what is wanted: the RSVPs stay on the same comments, on the same post, for
	 * the same date, and become readable series-wide again, which on a
	 * single-date event *is* the date. Nothing is migrated and nothing is
	 * deleted; only the scoping that no longer has anything to scope goes away.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $post_id        Series post ID the terms name.
	 * @param string[] $recurrence_ids Occurrence identifiers to unscope.
	 *
	 * @return int Terms deleted.
	 */
	public function detach_series( int $post_id, array $recurrence_ids ): int {
		$deleted = 0;

		foreach ( $recurrence_ids as $recurrence_id ) {
			$term = get_term_by( 'slug', self::term_slug( $post_id, (string) $recurrence_id ), self::TAXONOMY );

			// An occurrence nobody has RSVPd to has no term to drop.
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			wp_delete_term( $term->term_id, self::TAXONOMY );

			++$deleted;
		}

		return $deleted;
	}

	/**
	 * Count the RSVPs attached to a set of a post's occurrences.
	 *
	 * When a rule change would move or remove occurrences carrying RSVPs, the
	 * organizer is **shown how many RSVPs are affected** before committing, and
	 * the RSVPs are not silently migrated. This is the number that gets shown:
	 * the approved RSVPs on the dates the candidate rule would remove.
	 *
	 * Counts comments rather than `term_taxonomy.count`, because that column
	 * counts relationship rows and would include RSVPs whose comment has since
	 * been trashed. An organizer told "4 RSVPs affected" when two of them are in
	 * the trash has been told the wrong thing.
	 *
	 * `'status' => 'approve'` narrows it one step further, and does work the
	 * trashed case does not: `WP_Comment_Query` reads an absent status as `all`,
	 * which is `comment_approved IN ( '0', '1' )`. Trash and spam are already
	 * out, but a **pending** RSVP is in. Guest responses arrive pending by
	 * design (`Rsvp\Form::prepare_comment_data()` inserts them with
	 * `comment_approved => 0`), so without this the count would include
	 * responses the organizer has not accepted.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $post_id        Series post ID the terms name.
	 * @param string[] $recurrence_ids Occurrence identifiers to count across.
	 *
	 * @return int The number of approved RSVPs on those occurrences.
	 */
	public function count_rsvps( int $post_id, array $recurrence_ids ): int {
		$slugs = array();

		foreach ( $recurrence_ids as $recurrence_id ) {
			$slugs[] = self::term_slug( $post_id, (string) $recurrence_id );
		}

		if ( array() === $slugs ) {
			return 0;
		}

		// One `get_terms()` for the whole candidate set rather than one
		// `get_term_by()` per identifier. The editor calls this on every change
		// to a rule, and a maximum-count reduction can remove hundreds of
		// occurrences at once, so the per-identifier form made the cost of
		// previewing a change proportional to how much the change removed.
		$term_ids = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'slug'       => $slugs,
				'fields'     => 'ids',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return 0;
		}

		$comment_ids = get_objects_in_term( array_map( 'intval', $term_ids ), self::TAXONOMY );

		if ( is_wp_error( $comment_ids ) || empty( $comment_ids ) ) {
			return 0;
		}

		// The occurrence terms are the scope rather than `post_id`. Every slug
		// `term_slug()` produces already names exactly one post, and a split
		// moves `comment_post_ID` and the occurrence term together, so a post
		// filter here would be redundant rather than corrective.
		return (int) Rsvp_Query::get_instance()->get_rsvps(
			array(
				'comment__in' => array_map( 'intval', $comment_ids ),
				'count'       => true,
				'status'      => 'approve',
			)
		);
	}
}
