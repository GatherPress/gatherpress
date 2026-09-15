<?php
/**
 * Occurrence URLs: rewrite rule registration and request resolution.
 *
 * Every occurrence of a series gets its own URL, so an attendee can
 * link a friend to the specific date they are attending. The URL is the
 * event's own permalink plus a segment identifying the occurrence's start:
 * `/{event-slug}/{postname}/{Ymd\THis}/`. On a site whose permalinks are
 * query strings, the identifier travels as the `gatherpress_occurrence`
 * query variable instead, because a query-string permalink has no path to
 * append a segment to.
 *
 * The event post type's rewrite base is a **setting**, read from the
 * registered post type object at runtime (`WP_Post_Type::$rewrite['slug']`),
 * never hardcoded. Recurrence belongs to the `gatherpress-event-date` post
 * type support rather than to `gatherpress_event` specifically, so every
 * post type declaring that support gets its own occurrence rewrite rule.
 *
 * A spike proved `add_rewrite_endpoint( ..., EP_PERMALINK )` produces a rule
 * that is tried *after* the event post type's own generated
 * `event/[^/]+/([^/]+)/?$` attachment-slug catch-all. That catch-all greedily
 * matches an occurrence segment as an attachment slug and 404s before this
 * class ever sees the request. `add_rewrite_rule()` with the `'top'`
 * position (the same pattern `Calendar\Endpoint` already uses for the
 * feed/ical endpoints) is registered into `$wp_rewrite->extra_rules_top`,
 * which is merged into `$wp_rewrite->rules` strictly before every
 * post-type-generated rule, including that catch-all. See
 * `test/unit/php/includes/tests/core/classes/event/recurrence/class-test-rewrite.php`
 * for the regression coverage that pins this down.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Calendar\Setup as Calendar_Setup;
use GatherPress\Core\Traits\Singleton;
use WP;
use WP_Post;
use WP_Post_Type;

/**
 * Class Rewrite.
 *
 * Singleton owning the occurrence rewrite rule and its `parse_request`
 * resolution.
 *
 * @since 0.36.0
 */
final class Rewrite {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Regex character class matching a `Ymd\THis` occurrence identifier.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const RECURRENCE_ID_REGEX = '[0-9]{8}T[0-9]{6}';

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for occurrence URL registration and resolution.
	 *
	 * The rewrite rule is registered on `wp_loaded` rather than `init` so it
	 * is appended to `$wp_rewrite->extra_rules_top` strictly after
	 * `Calendar\Setup::register_endpoints()`, which registers GatherPress's
	 * feed/ical endpoints at `PHP_INT_MAX` on `init`. `wp_loaded` fires
	 * after every `init` callback has run, at any priority, so the append
	 * order (and therefore the match order among `'top'` rules) is
	 * deterministic regardless of subsystem instantiation order. The event
	 * post type itself must also already be registered, which happens on
	 * `init` at the default priority.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'wp_loaded', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'parse_request', array( $this, 'parse_request' ) );
	}

	/**
	 * Register the occurrence rewrite rule for every event-date-supporting post type.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $post_type ) {
			$this->add_rewrite_rule_for_post_type( $post_type );
		}
	}

	/**
	 * Register the occurrence rewrite rule for one post type.
	 *
	 * Reads the post type's registered permastruct and query var at call
	 * time rather than assuming `gatherpress_event` / `/event/`, so a
	 * non-default or localized `events_url` setting gets a working occurrence
	 * URL, and so does a companion plugin's own event-supporting post type,
	 * including hierarchical ones and ones whose permastruct keeps the
	 * rewrite front.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type Post type slug declaring `gatherpress-event-date` support.
	 *
	 * @return void
	 */
	protected function add_rewrite_rule_for_post_type( string $post_type ): void {
		global $wp_rewrite;

		$type_object = get_post_type_object( $post_type );

		if ( ! $type_object instanceof WP_Post_Type || false === $type_object->rewrite ) {
			return;
		}

		// The pattern is built from the post type's registered permastruct
		// rather than from the bare rewrite slug: `register_post_type()`
		// prepends the rewrite front to every `with_front` permastruct, so
		// the struct's leading path is what every real permalink of the post
		// type carries, and a rule missing it would never match the URLs
		// `get_occurrence_url()` advertises.
		$struct = (string) $wp_rewrite->get_extra_permastruct( $post_type );

		if ( '' === $struct ) {
			return;
		}

		$base = trim( str_replace( '%' . $post_type . '%', '', $struct ), '/' );

		// A hierarchical post type publishes child posts at `parent/child`,
		// so the post-name capture must span path segments, matching the
		// `(.+?)` capture WordPress's own rewrite tag uses for hierarchical
		// post types. A non-hierarchical name is always one segment.
		$capture = $type_object->hierarchical ? '(.+?)' : '([^/]+)';

		$reg_ex = sprintf(
			'%s/%s/(%s)/?$',
			$base,
			$capture,
			self::RECURRENCE_ID_REGEX
		);

		// A post type registered with `query_var => false` still gets pretty
		// permalinks; WordPress's own rewrite tag for it routes through the
		// `post_type` plus `name` pair, or `post_type` plus `pagename` when
		// hierarchical, so the occurrence rule targets the same pair.
		if ( ! empty( $type_object->query_var ) ) {
			$post_query = array( $type_object->query_var => '$matches[1]' );
		} else {
			$post_name_key = $type_object->hierarchical ? 'pagename' : 'name';
			$post_query    = array(
				'post_type'    => $post_type,
				$post_name_key => '$matches[1]',
			);
		}

		$rewrite_url = add_query_arg(
			array_merge( $post_query, array( Context::QUERY_VAR => '$matches[2]' ) ),
			'index.php'
		);

		add_rewrite_rule( $reg_ex, $rewrite_url, 'top' );
		$this->maybe_flush_rewrite_rules( $reg_ex, $rewrite_url );
	}

	/**
	 * Flush the rewrite_rules option when the stored rules do not already
	 * contain this exact pattern/target pair.
	 *
	 * `add_rewrite_rule()` only ever mutates `$wp_rewrite->extra_rules_top`
	 * in memory. `WP_Rewrite::wp_rewrite_rules()` returns the persisted
	 * `rewrite_rules` option verbatim whenever it is non-empty, so on every
	 * request after the *first* one this rule is registered for, the option
	 * already exists (built at plugin activation, or by any other rewrite
	 * consumer) without this rule in it, and it never gets added on an
	 * upgrading site until *something* deletes the option and forces a
	 * regeneration. This mirrors `Calendar\Endpoint::maybe_flush_rewrite_rules()`,
	 * the same in-place compare-and-delete pattern GatherPress already
	 * uses for its other custom endpoints, since `Setup::schedule_rewrite_flush()`
	 * is private and out of this class's reach. Once the option is
	 * regenerated with this pattern's exact target, the comparison is true
	 * and the condition never fires again, so this cannot flush on every
	 * request.
	 *
	 * @since 0.36.0
	 *
	 * @param string $reg_ex      The regular expression pattern this rule was registered under.
	 * @param string $rewrite_url The target URL this pattern should map to.
	 *
	 * @return void
	 */
	protected function maybe_flush_rewrite_rules( string $reg_ex, string $rewrite_url ): void {
		$rules = get_option( 'rewrite_rules' );

		if ( ! isset( $rules[ $reg_ex ] ) || $rules[ $reg_ex ] !== $rewrite_url ) {
			delete_option( 'rewrite_rules' );
		}
	}

	/**
	 * Filters the public query variables to allow the occurrence segment.
	 *
	 * @since 0.36.0
	 *
	 * @param string[] $public_query_vars Allowed public query variable names.
	 *
	 * @return string[] The updated list.
	 */
	public function add_query_vars( array $public_query_vars ): array {
		$public_query_vars[] = Context::QUERY_VAR;

		return $public_query_vars;
	}

	/**
	 * Resolve the occurrence segment of a request, or the bare series URL's
	 * next upcoming occurrence.
	 *
	 * A well-formed occurrence segment that does not resolve to a real row
	 * anywhere in the series 404s. A stale or hand-typed link must not
	 * silently render the series at its anchor date. A canceled occurrence
	 * resolves rather than 404s: `find_in_series()` does not filter by status,
	 * so a canceled row is returned like any other and this method never
	 * inspects `status` itself.
	 *
	 * The lookup goes through `Occurrences::find_in_series()` over
	 * `Series::resolve_post_ids()` rather than the single-post
	 * `Occurrences::get()`, and that is what keeps occurrence links stable.
	 * Recycling occurrence records across a forward split exists so that
	 * anything keyed to an occurrence's identity survives, permalinks and RSVP
	 * mappings among them. A single-post read misses every row the split
	 * moved onto a sibling, so every link already sitting in an attendee's
	 * inbox would 404 the moment the organizer split the series.
	 *
	 * A hit on a sibling post 301s to that post's occurrence URL rather than
	 * rendering under the requested post's slug, so the occurrence has one
	 * canonical address and link equity follows the row. This is not in
	 * tension with the 404-rather-than-301 rule above: that rule is about an
	 * identifier that exists nowhere, and this is about one that still
	 * exists, on a post of the same series. The redirect is gated by
	 * `can_follow_to()` exactly as the bare-series forwarding is: a sibling
	 * the visitor may not read is answered with the same non-revealing 404 a
	 * nonexistent occurrence gets, because the redirect's Location header
	 * would otherwise disclose the private post's existence and slug.
	 *
	 * The "a site with no recurring events pays nothing" guarantee is enforced
	 * on the bare-series branch alone, because that branch is the one every
	 * ordinary request falls through to. The occurrence-segment branch is
	 * deliberately unguarded: it only runs for a request already carrying an
	 * occurrence identifier, and its one primary-key `Occurrences::get()`
	 * read is the price of the miss invariant above surviving the site-wide
	 * flag. A guard at the method entry was tried first and broke that
	 * invariant the moment `refresh_has_recurring_events()` wrote `'0'`: the
	 * rewrite rule still matched, the guard returned before the miss could
	 * 404, and every previously shared occurrence URL rendered the series at
	 * its anchor date with a `200`.
	 *
	 * @since 0.36.0
	 *
	 * @param WP $wp The main WP request object, mutated in place.
	 *
	 * @return void
	 */
	public function parse_request( WP $wp ): void {
		if ( ! isset( $wp->query_vars[ Context::QUERY_VAR ] ) || '' === $wp->query_vars[ Context::QUERY_VAR ] ) {
			if ( Query::site_has_recurring_events() ) {
				$this->maybe_resolve_bare_series( $wp );
			}

			return;
		}

		$post_id = $this->resolve_post_id_from_query_vars( $wp->query_vars );

		if ( null === $post_id ) {
			return;
		}

		$recurrence_id = (string) $wp->query_vars[ Context::QUERY_VAR ];
		$row           = Occurrences::get_instance()->find_in_series(
			Series::get_instance()->resolve_post_ids( $post_id ),
			$recurrence_id
		);

		if ( null === $row ) {
			$this->refuse_with_404( $wp );
		} elseif ( (int) $row['series_post_id'] !== $post_id ) {
			$owner_id = (int) $row['series_post_id'];

			if ( $this->can_follow_to( $owner_id ) ) {
				wp_safe_redirect( self::get_occurrence_url( $owner_id, $recurrence_id ), 301 );
				// The PMC test harness intercepts wp_safe_redirect before this line runs.
				// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation.
				// @codeCoverageIgnoreStart
				exit;
				// @codeCoverageIgnoreEnd
			} else {
				// The same guard the bare-series branch applies before following
				// a sibling: a 301 naming a post the visitor may not read would
				// disclose the private post's existence and slug. An unreadable
				// owner is answered exactly as a nonexistent occurrence is.
				$this->refuse_with_404( $wp );
			}
		}
	}

	/**
	 * Answer the request with a non-revealing 404.
	 *
	 * Shared by the two refusals of the occurrence-segment branch: an
	 * identifier that resolves nowhere in the series, and an identifier whose
	 * owner the visitor may not read. Both must answer identically, or the
	 * difference between them becomes an existence oracle for the unreadable
	 * owner.
	 *
	 * `redirect_canonical()` otherwise finds the series post by its `name`
	 * query var, decides the request is "close enough" to a real permalink,
	 * and 301s to the bare series URL instead of letting the 404 stand. That
	 * silently turns a stale or hand-typed occurrence link into "renders the
	 * series at its anchor date", exactly what a miss must not do.
	 *
	 * @since 0.36.0
	 *
	 * @param WP $wp The main WP request object, mutated in place.
	 *
	 * @return void
	 */
	protected function refuse_with_404( WP $wp ): void {
		$wp->query_vars['error'] = '404';

		add_filter( 'redirect_canonical', '__return_false' );
	}

	/**
	 * Resolve a bare series URL's query var to its next upcoming occurrence.
	 *
	 * Visiting a recurring series at its own permalink, with no
	 * occurrence segment, resolves to the next upcoming occurrence rather
	 * than the series anchor. A post with no scheduled upcoming occurrence
	 * rows is left untouched so it renders exactly as it does today. That
	 * covers a non-recurring event and a series that has run out.
	 *
	 * ## Bare-URL contract: fragment semantics, not logical-series semantics
	 *
	 * This is the contract the occurrence front end is built on, stated here
	 * because it is about to matter and because completing it is not this
	 * layer's to make. **A bare URL resolves within the fragment it names, never across
	 * the logical series.**
	 *
	 * Concretely: `next_upcoming_recurrence_id()` scopes its read to the post
	 * the request named, in SQL, so the only rows it can answer with are that
	 * post's own. On a series that has never been split the scoping is
	 * indistinguishable from reading the whole series, because
	 * `Series::resolve_post_ids()` returns `array( $post_id )` and one post is
	 * the whole series. They separate the moment a forward split makes a series
	 * span several posts, and the scoping is what decides the behavior then:
	 *
	 * - **Fragment semantics (resolution).** `/{slug-of-fragment-A}/` resolves to
	 *   A's own next upcoming occurrence, in place, under A's slug.
	 * - **Logical-series semantics (redirect).** Once A has nothing upcoming of
	 *   its own, the request is answered by a `301` to the occurrence URL of the
	 *   series' earliest upcoming row, wherever in the series it lives.
	 *
	 * **Both, in that order, and the second is a redirect rather than a
	 * resolution.** The post scoping in `next_upcoming_recurrence_id()`
	 * still decides what may be rendered under the requested slug, so invariant
	 * 2 below holds exactly as written: a bare request never *resolves* into
	 * another post's occurrence. When A has nothing left, the alternative to
	 * moving is rendering A's stale anchor while the same logical series has an
	 * upcoming date, which is the defect, so the request moves instead.
	 *
	 * Resolving B's row in place under A's slug was the alternative, and it is
	 * worse in three ways. It would make one occurrence answer at two addresses,
	 * splitting link equity and giving `rel="canonical"` a choice with no good
	 * arm: point at A's URL and every fragment's canonical collides on the
	 * origin, or point at B's and the page contradicts the URL it was served
	 * from. It would render B's title, content and RSVP form under A's slug and
	 * A's authorization, which is precisely the shape of the sibling-authorization
	 * defect that resolving an occurrence through its authoritative owner
	 * exists to prevent. And it would leave the visitor's address
	 * bar naming a fragment whose dates have all passed, so sharing it starts
	 * the same detour again.
	 *
	 * **What `rel="canonical"` reports.** The `301` lands on B's occurrence URL,
	 * a real request that WordPress resolves to post B with occurrence context,
	 * so the canonical link is B's occurrence URL, the address the row is
	 * served from. A's bare URL never emits a canonical of its own for that row,
	 * because A never renders it.
	 *
	 * Two invariants inherited from PR 2, both preserved:
	 *
	 * 1. A pre-split occurrence URL keeps resolving to the same occurrence
	 *    after the split, whichever fragment ends up owning that row.
	 * 2. A bare URL never resolves to an occurrence belonging to another post,
	 *    so the canonical URL a bare request produces always sits under the
	 *    requested post's slug.
	 *
	 * Authorization travels with the redirect rather than being assumed by it.
	 * The target is filtered through `can_follow_to()` first, so a private
	 * sibling the visitor may not read is skipped exactly as though it had no
	 * upcoming rows, and the request falls back to rendering the post it named.
	 * A password-protected sibling *is* followed: the password gate lives on the
	 * rendering of that post and still runs, so the redirect reveals nothing the
	 * post's own public permalink does not already.
	 *
	 * The no-recurring-events guard wraps this method's call inside
	 * `parse_request()`, so the `get_page_by_path()` lookup below is never
	 * paid on a site with no recurring events. It guards this branch alone:
	 * the occurrence-segment branch runs unguarded on purpose, so a stale
	 * link keeps 404ing after the flag flips off.
	 *
	 * @since 0.36.0
	 *
	 * @param WP $wp The main WP request object, mutated in place.
	 *
	 * @return void
	 */
	protected function maybe_resolve_bare_series( WP $wp ): void {
		if ( $this->is_ics_request( $wp ) ) {
			return;
		}

		$post_id = $this->resolve_post_id_from_query_vars( $wp->query_vars );

		if ( null === $post_id ) {
			return;
		}

		$recurrence_id = $this->next_upcoming_recurrence_id( $post_id );

		if ( null !== $recurrence_id ) {
			$wp->query_vars[ Context::QUERY_VAR ] = $recurrence_id;

			return;
		}

		$this->maybe_follow_series( $post_id );
	}

	/**
	 * Send a lapsed fragment's bare URL on to the live one.
	 *
	 * Reached only when the requested post has no upcoming scheduled occurrence
	 * of its own. An unsplit series returns from the first guard having read one
	 * already-run query result and nothing else, which covers every series on a
	 * site that has never split anything.
	 *
	 * The destination is an `Occurrence_Identity`, resolved by the occurrence
	 * ownership seam rather than read off the query row, so the post the
	 * visitor is sent to is the one
	 * the identity seam names as the owner.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The requested post.
	 *
	 * @return void
	 */
	protected function maybe_follow_series( int $post_id ): void {
		$identity = $this->next_upcoming_in_series( $post_id );

		if ( null === $identity ) {
			return;
		}

		// 302, not 301. The target is the series' earliest upcoming occurrence
		// at the instant of the request, which is a moving value. A 301 is
		// cacheable by default under RFC 9110 section 15.4.2 with no explicit
		// freshness required, and WordPress sends no `Cache-Control` here:
		// `nocache_headers()` runs for admin, feed and logged-in requests, not
		// for an anonymous front-end redirect. So a browser would persist the
		// forward indefinitely and keep landing on a date that has since
		// passed, without ever reaching the server again. That is the defect
		// this feature exists to remove, made unfixable server-side. Any
		// shared cache does the same for everyone behind it.
		wp_safe_redirect(
			self::get_occurrence_url( $identity->owner_post_id, $identity->recurrence_id ),
			302
		);
		// The PMC test harness intercepts wp_safe_redirect before this line runs.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation.
		// @codeCoverageIgnoreStart
		exit;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * The earliest upcoming scheduled occurrence anywhere in a post's series.
	 *
	 * Unlike `next_upcoming_recurrence_id()` this does not narrow to the
	 * requested post. It answers the logical-series question, and its caller
	 * turns the answer into a redirect rather than into rendered context.
	 *
	 * Siblings the visitor may not read are skipped rather than refused: a
	 * series continuing on a draft or private sibling is, to that visitor, a
	 * series with nothing upcoming, and any other answer would make the bare
	 * URL an existence oracle for unpublished posts. The skip happens before
	 * the read, which the ownership invariant is what permits: an occurrence
	 * row's owner is always its own `series_post_id` (see
	 * `Occurrence_Identity`), so excluding an unreadable sibling from the
	 * queried set excludes exactly that sibling's rows, and nothing has to be
	 * hydrated to find out who owns it. The requested post is excluded with
	 * them, restating invariant (b): the request only ever moves to a post
	 * other than the one it named.
	 *
	 * The read itself is `Occurrences::select_bounded_occurrence()`: one
	 * end-inclusive, totally ordered, `LIMIT 1` statement. Bounding on
	 * `datetime_end_gmt >= now` rather than on the start keeps an occurrence
	 * in progress: a start-bounded skip sends the visitor holding the lapsed
	 * fragment's URL to next week while the event they are on their way to is
	 * happening. Before this the redirect hydrated the series' whole
	 * scheduled row set, several hundred rows on a long-lived split daily
	 * series, to emit one `Location` header.
	 *
	 * A single-post series returns before any of that, and so does a request on
	 * a post whose own rows are the ones that are upcoming. The caller only
	 * reaches here once the narrowing read has come back empty.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The requested post.
	 *
	 * @return Occurrence_Identity|null The occurrence to forward to, or null when there is none.
	 */
	protected function next_upcoming_in_series( int $post_id ): ?Occurrence_Identity {
		$post_ids   = Series::get_instance()->resolve_post_ids( $post_id );
		$candidates = array();

		if ( array( $post_id ) !== $post_ids ) {
			$candidates = array_values(
				array_filter(
					array_map( 'intval', $post_ids ),
					fn ( int $sibling_id ): bool => $sibling_id !== $post_id && $this->can_follow_to( $sibling_id )
				)
			);
		}

		if ( array() === $candidates ) {
			return null;
		}

		$row = Occurrences::get_instance()->select_bounded_occurrence( $candidates, true );

		if ( null === $row ) {
			return null;
		}

		// Still resolved through the identity seam rather than assembled from
		// the row, so the redirect target is the owner the seam names.
		$identity = Occurrence_Identity::resolve( $post_id, (string) $row['recurrence_id'] );

		// The bounded read above queried only the candidates `can_follow_to()`
		// allows, but `resolve()` goes back through `find_in_series()` over the
		// whole series and picks a winner with `ORDER BY series_post_id ASC`.
		// A duplicate `recurrence_id` on a lower-ID sibling that was excluded
		// here therefore wins, and its slug would go into a `Location` header
		// for an anonymous visitor with no second `can_follow_to()` check.
		// The comment on the uniqueness guard in this stack says rows written
		// before that guard existed can still carry the duplicate, so a site
		// upgraded from an earlier build is exactly that state. The other
		// redirect in this class re-checks the owner the same way, which is
		// why that path is safe.
		return ( null !== $identity && in_array( $identity->owner_post_id, $candidates, true ) )
			? $identity
			: null;
	}

	/**
	 * Whether a bare request may be forwarded to a sibling post.
	 *
	 * Password protection is deliberately not tested here: the password form is
	 * the destination's own rendering, and it still runs. What must not happen
	 * is forwarding to a post whose very existence is private to editors.
	 *
	 * The status test comes first and is not redundant. `current_user_can(
	 * 'read_post' )` maps a published post to the plain `read` capability, which
	 * a logged-out visitor does not hold on any site, so the permission check
	 * alone would refuse to forward anybody who is not logged in, which is most
	 * subscribers. A publicly-queryable status is what makes a post readable by
	 * the public; the capability check is what admits the non-public ones an
	 * editor may see.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Candidate destination post.
	 *
	 * @return bool True when the visitor may be sent there.
	 */
	protected function can_follow_to( int $post_id ): bool {
		return self::is_publicly_readable( $post_id ) || current_user_can( 'read_post', $post_id );
	}

	/**
	 * Whether a post's status makes it readable by anyone.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post to test.
	 *
	 * @return bool True when the post exists and carries a public status.
	 */
	public static function is_publicly_readable( int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		$status = get_post_status_object( (string) $post->post_status );

		return null !== $status && (bool) $status->public;
	}

	/**
	 * Report whether the request is asking for an iCalendar body.
	 *
	 * A bare series URL resolves to the next upcoming occurrence, which is what
	 * a page render wants and what the single-datetime Google and Yahoo
	 * redirects want. It is the wrong answer for `.ics`: a series' export is
	 * **one** component carrying the whole rule, so narrowing it to one date
	 * here would silently reintroduce the very limitation that shape exists to
	 * beat. An attendee subscribing to a recurring meetup would get a single
	 * entry again, just on a different date than before.
	 *
	 * A request that names an occurrence explicitly never reaches this method:
	 * `parse_request()` sends it down the occurrence-segment branch instead, so
	 * a single-occurrence download still resolves.
	 *
	 * @since 0.36.0
	 *
	 * @param WP $wp The main WP request object.
	 *
	 * @return bool True when the request targets an `.ics` calendar endpoint.
	 */
	protected function is_ics_request( WP $wp ): bool {
		return in_array(
			(string) ( $wp->query_vars[ Calendar_Setup::QUERY_VAR ] ?? '' ),
			Calendar_Setup::ICS_SLUGS,
			true
		);
	}

	/**
	 * Resolve the series post ID a request's query vars point at.
	 *
	 * Iterates every event-date-supporting post type and reads the query vars
	 * its occurrence rewrite rule was registered against, matching
	 * `add_rewrite_rule_for_post_type()`: the post type's own query var when
	 * it has one, otherwise the `post_type` plus `name` or `pagename`
	 * fallback pair.
	 *
	 * @since 0.36.0
	 *
	 * @param array $query_vars The request's query vars.
	 *
	 * @return int|null The resolved post ID, or null when nothing matches.
	 */
	protected function resolve_post_id_from_query_vars( array $query_vars ): ?int {
		foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $post_type ) {
			$type_object = get_post_type_object( $post_type );

			if ( ! $type_object instanceof WP_Post_Type ) {
				continue;
			}

			// A post type without a query var is resolved through the same
			// `post_type` plus `name` or `pagename` pair its rewrite target
			// carries; see `add_rewrite_rule_for_post_type()`.
			if ( ! empty( $type_object->query_var ) ) {
				$path = (string) ( $query_vars[ $type_object->query_var ] ?? '' );
			} elseif ( ( $query_vars['post_type'] ?? '' ) === $post_type ) {
				$path = (string) ( $query_vars[ $type_object->hierarchical ? 'pagename' : 'name' ] ?? '' );
			} else {
				$path = '';
			}

			if ( '' === $path ) {
				continue;
			}

			$post = get_page_by_path( $path, OBJECT, $post_type );

			if ( $post instanceof WP_Post ) {
				return (int) $post->ID;
			}
		}

		return null;
	}

	/**
	 * Resolve a series' next upcoming scheduled occurrence identifier.
	 *
	 * One bounded statement: the requested post's scheduled rows whose end has
	 * not passed, in the table's total order, limited to the single row the
	 * answer needs. Every part of that is decided in SQL, so a series' whole
	 * scheduled history is never hydrated to produce one identifier.
	 *
	 * Scoping the read to the requested post *is* the fragment-semantics
	 * narrowing documented on `maybe_resolve_bare_series()`, not a departure
	 * from it. Widening the query across the series and then discarding every
	 * row that does not belong to the requested post returns exactly the rows
	 * this scoped query returns, in the same order, because discarding rows
	 * cannot reorder the ones that survive. What the widened shape cannot
	 * survive is the row limit: a sibling's occurrence can sort ahead of the
	 * requested post's own, so a widened read cut to one row answers with
	 * another post's occurrence, which is the one thing the contract's second
	 * invariant forbids. Scoping the query is what makes the bound safe.
	 *
	 * Upcoming is bounded inclusively on the occurrence's end, through
	 * `select_for_series()`'s end-inclusive `after` argument, matching
	 * `Event\Query::get_datetime_comparison_column()`'s "a running event is
	 * still upcoming" rule, which the occurrence list preserves through its
	 * `COALESCE()` rewrite. An occurrence that is in progress, or that ends at
	 * this exact second, still resolves; bounding on the start instead skipped
	 * a running occurrence and sent the visitor to the next one while the list
	 * beside the page still showed the running one. The column is `NOT NULL`
	 * and written by every projection, so there is no empty-end shape to fall
	 * back from.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID.
	 *
	 * @return string|null The recurrence ID, or null when none is upcoming.
	 */
	protected function next_upcoming_recurrence_id( int $post_id ): ?string {
		$rows = Occurrences::get_instance()->select_for_series(
			array( $post_id ),
			array(
				'status' => Occurrences::STATUS_SCHEDULED,
				'after'  => current_time( 'mysql', true ),
				'limit'  => 1,
			)
		);

		if ( array() === $rows ) {
			return null;
		}

		return (string) $rows[0]['recurrence_id'];
	}

	/**
	 * Build the permalink of one occurrence.
	 *
	 * The segment is the occurrence's canonical `recurrence_id`, built by
	 * `Occurrences::recurrence_id()` and never re-derived here.
	 *
	 * There is deliberately no filter over the segment. An earlier revision of
	 * this class carried one, `gatherpress_recurrence_id_format`, and it
	 * was one-way: the emitted segment was filterable, but
	 * `add_rewrite_rule_for_post_type()` registers a single fixed
	 * `RECURRENCE_ID_REGEX` and `parse_request()` matches the raw segment
	 * against the canonical `recurrence_id` column, so any value the filter
	 * changed produced an advertised URL that 404s. A hook whose documented use
	 * breaks the feature it customizes is worse than no hook.
	 *
	 * Making it bidirectional was considered and rejected for now. It is not
	 * one filter but a coupled set: a filtered regex consumed at rule
	 * registration on `wp_loaded`, a filtered parser consumed in
	 * `parse_request()`, and rewrite-option invalidation keyed off the filtered
	 * regex so a changed pattern reaches the persisted `rewrite_rules`. Every
	 * one of them has to agree or the site 404s its own links. Against
	 * that, the segment *is* the composite identity: an invertible transform is
	 * a re-encoding with no product behind it, and a
	 * non-invertible one is the defect above. The filter is unreleased, has no
	 * known consumer, and nothing outside this class ever composed the segment,
	 * so removing it costs no compatibility. If a real requirement appears
	 * (localized or slug-shaped segments), the contract to add is the paired
	 * format/parse/regex set designed against that requirement.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return string The occurrence URL, or '' when the post has no permalink.
	 */
	public static function get_occurrence_url( int $post_id, string $recurrence_id ): string {
		// Read through `Context`, which stands its own permalink filter down
		// for the duration: the occurrence URL is composed on top of the
		// *series* permalink, and reading a filtered one during a loop
		// iteration would append a second occurrence segment to a URL that
		// already carries one.
		$permalink = Context::get_instance()->series_permalink( $post_id );

		if ( false === $permalink ) {
			return '';
		}

		// A query-string permalink appears when the site has no permalink
		// structure at all, or when the rewrite rules have not been
		// regenerated for this post type yet. Appending a path segment there
		// would push the identifier into the event query value, producing
		// `?gatherpress_event=slug/{id}/`, which identifies no post. The
		// occurrence rides as its own query variable instead, the same
		// composition `Calendar::get_endpoint_url()` uses for its endpoints.
		if ( str_contains( $permalink, '?' ) ) {
			return add_query_arg( Context::QUERY_VAR, $recurrence_id, $permalink );
		}

		return trailingslashit( $permalink ) . $recurrence_id . '/';
	}
}
