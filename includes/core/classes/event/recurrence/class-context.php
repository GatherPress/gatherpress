<?php
/**
 * Request-scoped occurrence context, and the read path that feeds unmodified blocks.
 *
 * This class is what lets an occurrence's date reach every existing block.
 * `Event::get_datetime()` reads from post meta rather
 * than from the events table, so filtering `get_post_metadata` is enough to make
 * every existing date-aware block render an occurrence's date without a single
 * block file changing.
 *
 * An occurrence's time of day comes from the occurrence record. It is never
 * computed by applying the series anchor's time to the occurrence's date.
 *
 * A canceled occurrence's URL resolves rather than 404s
 * (`Rewrite::parse_request()`), and this class prepends a notice to the
 * content so an attendee holding the link is told the occurrence was
 * canceled, not merely served a page.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Context.
 *
 * Singleton holding the occurrence the current request is rendering, if any.
 * Context is set on request resolution and cleared on teardown, so no stale
 * occurrence value can leak into a later loop.
 *
 * @since 0.36.0
 */
final class Context {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query var carrying the occurrence segment of a permalink.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const QUERY_VAR = 'gatherpress_occurrence';

	/**
	 * Occurrence table column serving each derived datetime meta key.
	 *
	 * These five keys are exactly the ones `Event::get_datetime()` reads, which
	 * is what makes a `get_post_metadata` filter sufficient to redirect every
	 * unmodified block's date read at the occurrence record. The array is
	 * ordered and keyed so its values double as the unprefixed keys of the
	 * `Event::get_datetime()` return shape.
	 *
	 * @since 0.36.0
	 * @var array<string, string>
	 */
	const META_KEY_COLUMNS = array(
		'gatherpress_datetime_start'     => 'datetime_start',
		'gatherpress_datetime_start_gmt' => 'datetime_start_gmt',
		'gatherpress_datetime_end'       => 'datetime_end',
		'gatherpress_datetime_end_gmt'   => 'datetime_end_gmt',
		'gatherpress_timezone'           => 'timezone',
	);

	/**
	 * The occurrence row the request is rendering, or null outside context.
	 *
	 * @since 0.36.0
	 * @var array|null
	 */
	protected ?array $occurrence = null;

	/**
	 * Whether the metadata filter is already inside a meta read of its own.
	 *
	 * The filter falls back to the series' own meta when an occurrence row's
	 * nullable `timezone` column is empty, and that fallback is a
	 * `get_post_meta()` call on the very key the filter is answering. Without
	 * this flag the call re-enters the filter, which re-enters the fallback,
	 * without end.
	 *
	 * @since 0.36.0
	 * @var bool
	 */
	protected bool $reading = false;

	/**
	 * The `array( $object_id, $meta_key )` pair a meta write is about to compare.
	 *
	 * `update_metadata()` discovers the currently stored value through
	 * `get_metadata_raw()`, which fires this class's filter. With context set,
	 * that comparison would see the occurrence's value, so a write setting the
	 * series' start to the occurrence's current start would look like a no-op
	 * and be dropped. The pair is noted on the way in and consumed once, by the
	 * read the write itself performs.
	 *
	 * The note is only ever taken when core is *certainly* about to make that
	 * read, because a note nothing consumes is not inert. It silently
	 * disables occurrence substitution for the next read of the same key, which
	 * is a wrong date with nothing anywhere to explain it. `note_meta_write()`
	 * documents the three conditions; `add_metadata()` is deliberately not
	 * hooked at all, because it never makes the read.
	 *
	 * @since 0.36.0
	 * @var array|null
	 */
	protected ?array $writing = null;

	/**
	 * Request-scoped memo of `resolve_in_series()` results.
	 *
	 * Keyed `{blog_id}:{post_id}:{recurrence_id}`. Values are the resolved row
	 * or null, so a miss is remembered as cheaply as a hit. A fabricated
	 * identifier costs one query per request rather than one per lookup.
	 *
	 * The blog ID leads the key because the other two halves are blog-local: a
	 * request that resolves on one blog and then calls `switch_to_blog()`
	 * would otherwise be handed the first blog's row on the second blog, and
	 * the leaked `series_post_id` would feed routing, authorization, term
	 * slugs and cache keys there as a foreign owner.
	 *
	 * @since 0.36.0
	 * @var array<string, array|null>
	 */
	protected static array $resolved = array();

	/**
	 * The post ID whose occurrence permalink is currently being composed.
	 *
	 * `Rewrite::get_occurrence_url()` builds the occurrence URL on top of
	 * `get_permalink()`, which fires the very filter that called it. The post
	 * ID is noted for the duration of that call so the nested read returns the
	 * bare series permalink instead of recursing. Holding the ID rather than a
	 * bare flag keeps the suppression scoped to the one post being resolved,
	 * so a third-party `post_type_link` filter that happens to ask for a
	 * *different* post's permalink still gets a correctly filtered one.
	 *
	 * @since 0.36.0
	 * @var int|null
	 */
	protected ?int $linking = null;

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for occurrence context.
	 *
	 * There are two sources of occurrence identity, and they are deliberately
	 * different mechanisms because they answer different questions.
	 *
	 * The *request's* occurrence is established once, on `wp`, after the main
	 * query has resolved and before `template_redirect` or any block renders.
	 * That is the singular occurrence permalink, and it is held in state
	 * because the request has exactly one of them.
	 *
	 * A *loop iteration's* occurrence is held in no state at all. It is read
	 * back off the result object `Query::attach_occurrences()` stamped, via
	 * `loop_occurrence()`, at the moment a consumer asks. Nothing is bound on
	 * `the_post` and nothing is pushed or popped: `the_post()` and
	 * `wp_reset_postdata()` already maintain the current post correctly,
	 * including through nesting, so deriving from it inherits that correctness
	 * instead of duplicating it. Binding on `the_post` would have to clear on
	 * an inner loop's unstamped post and would then have nothing to restore
	 * the outer iteration's identity from, because `wp_reset_postdata()` fires
	 * no action.
	 *
	 * Isolation in both cases comes from the same rule the pre-existing
	 * `metadata()` and `maybe_prepend_canceled_notice()` already follow: an
	 * occurrence is served only for the post it belongs to. That is scoping by
	 * identity rather than by loop position, so identity is always the
	 * composite `(series_post_id, recurrence_id)` and never a list index.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'get_post_metadata', array( $this, 'metadata' ), 10, 4 );
		add_action( 'wp', array( $this, 'sync' ) );
		// At `PHP_INT_MAX`, so no filter registered before this class boots
		// can short-circuit the write *after* the note is taken and leave it
		// for an unrelated read to consume. See condition 3 on
		// `note_meta_write()` for the one path that can still strand a note
		// and how it self-heals. `add_post_metadata` is deliberately not
		// hooked: see `note_meta_write()`.
		add_filter( 'update_post_metadata', array( $this, 'note_meta_write' ), PHP_INT_MAX, 5 );
		add_filter( 'the_content', array( $this, 'maybe_prepend_canceled_notice' ) );
		// Both permalink filters, because `get_permalink()` routes a custom
		// post type through `post_type_link` and the built-in post type
		// through `post_link`, and recurrence belongs to the
		// `gatherpress-event-date` support rather than to one post type.
		add_filter( 'post_type_link', array( $this, 'permalink' ), 10, 2 );
		add_filter( 'post_link', array( $this, 'permalink' ), 10, 2 );
	}

	/**
	 * Enter the context of one occurrence.
	 *
	 * Context is only entered when the composite key resolves to a real row, so
	 * a fabricated or stale recurrence identifier leaves the request reading the
	 * series' own datetime rather than nothing at all.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return void
	 */
	public function set( int $post_id, string $recurrence_id ): void {
		$this->occurrence = Occurrences::get_instance()->get( $post_id, $recurrence_id );
	}

	/**
	 * Enter the context of one occurrence named by a request parameter.
	 *
	 * The counterpart to `set()` for requests that never reach `wp`, which is
	 * the whole of REST: core's `rest_api_loaded()` runs on `parse_request` and
	 * ends in `die()`, so `sync()` is never called for a REST request.
	 * Resolution goes through `Series::resolve_post_ids()` rather than pinning
	 * the one post the caller named, so an occurrence a forward split has moved
	 * onto a sibling post still resolves.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Post ID the request names.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return bool True when the identifier resolved and context was entered.
	 */
	public function set_for_series( int $post_id, string $recurrence_id ): bool {
		$this->occurrence = self::resolve_in_series( $post_id, $recurrence_id );

		return null !== $this->occurrence;
	}

	/**
	 * Resolve an occurrence across every post of a series, without entering it.
	 *
	 * Shared by `set_for_series()` and by `Occurrence_Identity::resolve()`,
	 * which the REST permission callbacks and the classic form use to refuse
	 * an unknown identifier rather than silently falling back to the series.
	 *
	 * The recurring-events guard comes first: on a site with no recurring events this
	 * returns without touching the occurrence table, so a request carrying a
	 * fabricated `recurrence_id` costs nothing there.
	 *
	 * The result is memoized per `(blog_id, post_id, recurrence_id)` for the
	 * life of the request, blog first because the other two halves are
	 * blog-local. A REST dispatch resolves the same pair twice, once in the
	 * permission callback and once on entry. The two stay separate
	 * deliberately: the permission callback resolves only to authorize the
	 * occurrence's owner, and entering context there would leave it standing
	 * on a request that then 403s with no teardown filter registered. The
	 * memo removes the duplicated query without collapsing that separation.
	 *
	 * Memoizing is safe within a request because the occurrence table is only
	 * written by projection, which is not something a read request performs;
	 * the cache is per-process and dies with it.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Post ID the request names.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return array|null The occurrence row, or null when nothing in the series carries that identifier.
	 */
	public static function resolve_in_series( int $post_id, string $recurrence_id ): ?array {
		if ( ! Query::site_has_recurring_events() || '' === $recurrence_id || 1 > $post_id ) {
			return null;
		}

		$key = sprintf( '%d:%d:%s', get_current_blog_id(), $post_id, $recurrence_id );

		if ( ! array_key_exists( $key, self::$resolved ) ) {
			self::$resolved[ $key ] = Occurrences::get_instance()->find_in_series(
				Series::get_instance()->resolve_post_ids( $post_id ),
				$recurrence_id
			);
		}

		return self::$resolved[ $key ];
	}

	/**
	 * Discard the request-scoped resolution memo.
	 *
	 * The memo answers "which post of this series owns this date", so anything
	 * that changes that answer inside a request has to drop it.
	 * `Occurrences::move_to_post()` is the write that changes it, and it calls
	 * this. A forward split is what reaches that write in production, and
	 * `Splitter` also calls this directly from both ends of the split: when it
	 * settles caches after one lands, and again when it rolls a failed one
	 * back. A test process additionally projects and re-projects occurrences
	 * inside one PHP lifetime and needs the same escape hatch.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public static function flush_resolved(): void {
		self::$resolved = array();
	}

	/**
	 * Leave the current occurrence context.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->occurrence = null;
	}

	/**
	 * Put back an occurrence a caller previously held.
	 *
	 * The counterpart to `current()` for anything that has to nest. Occurrence
	 * context is a property of the innermost thing running, not of the process,
	 * so a caller that enters one must be able to restore whatever was standing
	 * before it rather than clearing unconditionally: a nested REST dispatch
	 * clearing on its own way out would leave the outer route reading and
	 * writing series-wide for the rest of its callback.
	 *
	 * The row is taken as-is, with no lookup, because it has already been
	 * resolved once and re-resolving on the way back out could fail on a row
	 * that has since been deleted and silently widen the outer scope.
	 *
	 * @since 0.36.0
	 *
	 * @param array|null $occurrence The occurrence row to restore, or null for no context.
	 *
	 * @return void
	 */
	public function restore( ?array $occurrence ): void {
		$this->occurrence = $occurrence;
	}

	/**
	 * Get the occurrence the request is currently rendering.
	 *
	 * @since 0.36.0
	 *
	 * @return array|null The occurrence row, or null outside occurrence context.
	 */
	public function current(): ?array {
		return $this->occurrence;
	}

	/**
	 * Establish occurrence context from the resolved request.
	 *
	 * Hooked to `wp`. Clears first so a previous request in the same process
	 * cannot leak, then derives context from the post the request actually
	 * named. A post a loop later reaches never establishes it.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function sync(): void {
		$this->clear();

		$this->maybe_set_from_request( get_queried_object_id() );
	}

	/**
	 * Enter occurrence context when the request asks for one on this post.
	 *
	 * The `site_has_recurring_events()` check keeps a site with no recurring
	 * events off this read path, and it is evaluated here at callback time
	 * rather than at `setup_hooks()` so a mid-request flip of the option is
	 * honored. Without it, any crawler or referrer-spam bot appending the
	 * occurrence query string to an ordinary
	 * event permalink would reach `Occurrences::get()` on a site that has never
	 * authored a recurring event, and that method is a raw, uncached
	 * `$wpdb->get_row()`.
	 *
	 * The remaining two arms never change the resulting context, since
	 * `Occurrences::get()` matches nothing for an empty identifier or a post ID
	 * of zero. They exist solely to skip that query.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID the request resolved to.
	 *
	 * @return void
	 */
	protected function maybe_set_from_request( int $post_id ): void {
		$recurrence_id = (string) get_query_var( self::QUERY_VAR );

		if ( ! Query::site_has_recurring_events() || '' === $recurrence_id || 1 > $post_id ) {
			return;
		}

		$this->set( $post_id, $recurrence_id );
	}

	/**
	 * Serve the occurrence's datetime for the five derived meta keys.
	 *
	 * Filters `get_post_metadata`. Outside occurrence context it returns the
	 * value untouched, so the meta read falls through to core. This is the
	 * compatibility path that lets unmodified blocks render an occurrence's
	 * date. The event date block, the add-to-calendar links, the "has this
	 * event passed" gate the RSVP blocks read, the block bindings and
	 * structured data `Event\Setup` emits, and the feed all reach it through
	 * `Event::get_datetime()`, which reads these five keys. Code inside the
	 * recurrence subsystem calls `get_datetime()` instead.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed  $value      Short-circuit value, null when nothing has filtered yet.
	 * @param int    $object_id  Post ID the meta is read from.
	 * @param string $meta_key   Meta key being read.
	 * @param bool   $single     Whether a single value was requested.
	 *
	 * @return mixed The occurrence's value in context, otherwise the value unchanged.
	 */
	public function metadata( $value, int $object_id, string $meta_key, bool $single ) {
		// Stand aside for the read a meta write makes to decide whether the
		// stored value already equals the value being written. Checked and
		// consumed before every other guard, and unconditionally, so a note can
		// never outlive the write that left it and disable substitution for
		// that key later.
		if ( array( $object_id, $meta_key ) === $this->writing ) {
			$this->writing = null;

			return $value;
		}

		// Stand aside while already reading meta of our own (see `$reading`)
		// and for keys the occurrence record does not own. Both are checked
		// before `resolve()` rather than inside it: the first is what stops
		// `read_series_meta()` from re-entering this filter without end.
		$occurrence = ( $this->reading || ! isset( self::META_KEY_COLUMNS[ $meta_key ] ) )
			? null
			: $this->resolve( $object_id );

		if ( null === $occurrence ) {
			return $value;
		}

		$result = $this->occurrence_value( $occurrence, $meta_key );

		return $single ? $result : array( $result );
	}

	/**
	 * Replace a post's permalink with its occurrence's permalink.
	 *
	 * Filters `post_type_link` and `post_link`. Resolution goes through
	 * `resolve()`, so a loop row answers with its own stamped occurrence and
	 * the singular occurrence page answers with the occurrence the request
	 * named.
	 *
	 * Rewriting `get_permalink()` on a singular occurrence request does not
	 * disturb core's canonical-redirect machinery. In
	 * `wp-includes/canonical.php`, on a resolved singular request
	 * `redirect_canonical()` reaches `get_permalink()` only through branches
	 * this request cannot be in: `is_singular() && $wp_query->post_count < 1`,
	 * the `is_404()` recovery, the query-string `?name=`/`?p=` forms, and the
	 * paginated `get_query_var( 'page' )` arm, none of which a matched
	 * occurrence rewrite rule produces. It ends at
	 * `if ( ! $redirect_url || $redirect_url === $requested_url ) { return; }`
	 * with `$redirect_url` still `false`.
	 *
	 * Withholding that arm, meanwhile, is a live defect rather than a neutral
	 * choice: every link emitted from an occurrence page would point at the bare
	 * series URL, which resolves to the *next upcoming* occurrence rather than
	 * the one being viewed. That covers the iCal `URL:` field, `rel="canonical"`,
	 * share links, and the RSVP confirmation email.
	 *
	 * The `$post` core hands this filter is `get_post()`'s cached object, not
	 * the stamped clone, which is exactly why identity is resolved by ID
	 * rather than read off `$post` directly.
	 *
	 * @since 0.36.0
	 *
	 * @param string $permalink The post's permalink.
	 * @param mixed  $post      The post the permalink belongs to. Typed loosely rather than as
	 *                          `WP_Post` because this is a public callback on a singleton, and a
	 *                          fatal is a poor answer to a caller that hands it something else.
	 *
	 * @return string The occurrence's permalink when one applies to this post, otherwise unchanged.
	 */
	public function permalink( $permalink, $post ): string {
		$post_id = ( $post instanceof WP_Post ) ? (int) $post->ID : 0;

		if ( $this->linking === $post_id || 1 > $post_id ) {
			return (string) $permalink;
		}

		$occurrence = $this->resolve( $post_id );

		if ( null === $occurrence ) {
			return (string) $permalink;
		}

		// Suppression has to cover the WHOLE occurrence-URL composition, not
		// just the bare permalink read nested inside it.
		// `Rewrite::get_occurrence_url()` applies the
		// `gatherpress_recurrence_id_format` filter *after*
		// `series_permalink()` has already restored the previous value, so an
		// integration whose format filter calls `get_permalink()` for this
		// same post would re-enter this method unsuppressed and recurse until
		// the stack is exhausted. Saving and restoring rather than clearing
		// keeps a nested build for a *different* post working.
		$previous      = $this->linking;
		$this->linking = $post_id;

		try {
			$url = Rewrite::get_occurrence_url( $post_id, (string) $occurrence['recurrence_id'] );
		} finally {
			$this->linking = $previous;
		}

		// An occurrence URL is composed on top of the series permalink, so it is
		// empty when the post has none. The permalink core already had is a
		// better answer than an empty href.
		return ( '' === $url ) ? (string) $permalink : $url;
	}

	/**
	 * Read a post's own series permalink, with occurrence substitution suppressed.
	 *
	 * The single place the `permalink()` filter is stood down, and it belongs
	 * here rather than at the call site because the reason is this class's:
	 * `Rewrite::get_occurrence_url()` composes the occurrence URL *on top of*
	 * the series permalink, so a filtered read would append an occurrence
	 * segment to a URL that already has one. That is not merely a recursion
	 * hazard. Any caller building an occurrence URL while a loop row is set
	 * up would get a doubled URL, whether or not this filter is what called it.
	 *
	 * The previous value is saved and restored rather than cleared, so a
	 * nested build (a third-party permalink filter asking for another post's
	 * occurrence URL) leaves the outer suppression intact.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID to read the permalink of.
	 *
	 * @return string|false The series permalink, or false when the post has none.
	 */
	public function series_permalink( int $post_id ) {
		$previous      = $this->linking;
		$this->linking = $post_id;

		try {
			return get_permalink( $post_id );
		} finally {
			$this->linking = $previous;
		}
	}

	/**
	 * Resolve which occurrence, if any, applies to one post right now.
	 *
	 * The row's own stamp wins over the request's occurrence, and the ordering
	 * is the whole point of the method. The reverse order is tempting, on the
	 * reasoning that "a loop rendered on that same response can still only
	 * reach this arm for the requested post itself, and for that post the two
	 * agree." Occurrences are exactly what breaks that assumption: a Query Loop
	 * of the *same series* rendered inside an occurrence page reaches this arm
	 * for the requested post on every row, and each of those rows is a
	 * different occurrence of it. Preferring the request there stamped the
	 * outer page's date, permalink and calendar payload onto every same-series
	 * row.
	 *
	 * The request arm is still load-bearing and must not be dropped in the name
	 * of "the stamp is authoritative": the singular occurrence page's own post
	 * is not stamped. `Query::attach_occurrences()` stamps a null identity when
	 * `expand_event_clauses()` made no occurrence join, which is every singular
	 * request, so `loop_occurrence()` returns null there and the request is the
	 * only source of identity the page has.
	 *
	 * Both arms are pinned by
	 * `test_resolve_prefers_the_rows_own_stamp_over_the_requests_occurrence`.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID being read.
	 *
	 * @return array|null The occurrence row, or null when this post has no occurrence in play.
	 */
	protected function resolve( int $post_id ): ?array {
		$stamped = $this->loop_occurrence( $post_id );

		if ( null !== $stamped ) {
			return $stamped;
		}

		if ( null !== $this->occurrence && (int) $this->occurrence['series_post_id'] === $post_id ) {
			return $this->occurrence;
		}

		return null;
	}

	/**
	 * Read the occurrence the current loop iteration represents.
	 *
	 * `Query::attach_occurrences()` stamps identity *and* the occurrence's own
	 * datetime columns onto a clone of every result row, so this is pure
	 * property access. There is no query, and nothing keyed by list position
	 * or by index. Identity is the composite `(series_post_id, recurrence_id)`. The
	 * values are the occurrence record's own columns rather than the anchor's
	 * time applied to the occurrence's date.
	 *
	 * The `$post_id` comparison is the isolation rule: the stamp is served
	 * only for the post it was stamped onto, so a consumer reading a different
	 * post's meta or permalink mid-iteration gets nothing from here. That is
	 * the same scoping `maybe_prepend_canceled_notice()` already applies.
	 *
	 * Only scheduled occurrences reach a loop, because the join in
	 * `Query::expand_event_clauses()` filters on `status`, so the row this
	 * rebuilds says so.
	 *
	 * Public because the RSVP layer reads it first: an archive or Query Loop has
	 * no *request* occurrence, so `current()` answers null for every row and the
	 * RSVP block would read series-wide state on all of them. The loop's stamped
	 * occurrence is the only thing that distinguishes one row from the next, and
	 * `Rsvp_Occurrence::current_occurrence()` cannot reach it through
	 * `resolve()` without also inheriting that method's exact-post-match arm,
	 * which would undo the widened-series admission it deliberately makes.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID being read.
	 *
	 * @return array|null An occurrence row, or null when the current post is not a stamped occurrence of it.
	 */
	public function loop_occurrence( int $post_id ): ?array {
		$post = get_post();

		if ( ! $post instanceof WP_Post || (int) $post->ID !== $post_id ) {
			return null;
		}

		// Read through `get_object_vars()`, mirroring how
		// `Query::stamp_occurrence()` writes the stamp, so the two ends of the
		// contract are the same shape and neither depends on `WP_Post`'s magic
		// accessors.
		//
		// Keyed on the datetime stamp alone rather than on both stamps,
		// because `stamp_occurrence()` writes the pair together: the datetime
		// property is an array for an occurrence row and null for a
		// non-recurring one, and it is absent entirely on any post that never
		// passed through `attach_occurrences()`.
		$values   = get_object_vars( $post );
		$datetime = $values[ Query::RESULT_DATETIME_PROPERTY ] ?? null;

		if ( ! is_array( $datetime ) ) {
			return null;
		}

		return array_merge(
			$datetime,
			array(
				'series_post_id' => $post_id,
				'recurrence_id'  => (string) $values[ Query::RESULT_PROPERTY ],
				'status'         => Occurrences::STATUS_SCHEDULED,
			)
		);
	}

	/**
	 * Prepend a cancellation notice to a canceled occurrence's content.
	 *
	 * An attendee holding the link deserves to be told the occurrence was
	 * canceled. `Rewrite::parse_request()` already lets a canceled
	 * occurrence's URL resolve instead of 404ing; this is what tells the
	 * visitor once it does. Resolution goes through `resolve()`, the same
	 * row-stamp-over-request precedence `metadata()` and `permalink()` use,
	 * so the occurrence whose status is inspected is the one the current
	 * content actually belongs to. Comparing only the request's occurrence
	 * against `get_the_ID()` would misplace the notice: on a canceled
	 * occurrence's own page, an occurrence-expanded Query Loop over the same
	 * series shares the outer post ID on every row, so every scheduled row
	 * would render the outer request's notice. A loop row resolves to its own
	 * stamped occurrence, which `loop_occurrence()` marks scheduled, so only
	 * the canceled occurrence's own content carries the notice.
	 *
	 * @since 0.36.0
	 *
	 * @param string $content The post content.
	 *
	 * @return string The content, with a cancellation notice prepended when
	 *                this is a canceled occurrence's own content.
	 */
	public function maybe_prepend_canceled_notice( string $content ): string {
		$occurrence = $this->resolve( (int) get_the_ID() );

		if ( null === $occurrence || Occurrences::STATUS_CANCELED !== $occurrence['status'] ) {
			return $content;
		}

		$notice = sprintf(
			'<p class="gatherpress-occurrence-canceled-notice">%s</p>',
			esc_html__( 'This occurrence has been canceled.', 'gatherpress' )
		);

		return $notice . $content;
	}

	/**
	 * Note the post and key a meta write is about to compare against.
	 *
	 * Filters `update_post_metadata` at `PHP_INT_MAX`. This is a notification,
	 * not a short-circuit, so the value passes through untouched.
	 *
	 * The note is taken only when `update_metadata()` will certainly reach the
	 * `get_metadata_raw()` call that consumes it, which is exactly the three
	 * conditions below. Anything looser leaves a sentinel behind that the next
	 * *ordinary* read of the same key consumes, returning the series' value on
	 * an occurrence page:
	 *
	 * 1. `null === $check`. A non-null value means an earlier-priority filter
	 *    has already short-circuited, and core returns before the read.
	 * 2. `empty( $prev_value )`. Core only compares the stored value when the
	 *    caller named no previous value; with one, the read never happens.
	 * 3. This callback runs at `PHP_INT_MAX`, after every filter registered
	 *    before GatherPress boots, so none of those can short-circuit the
	 *    write once the note is taken. A short-circuit registered at the
	 *    same priority *after* boot still runs later, because equal-priority
	 *    callbacks run in registration order, and it leaves the note armed.
	 *    The next read of that pair consumes it and returns the raw series
	 *    value once, so the substitution self-heals after one read. That
	 *    residual window needs a max-priority short-circuiting filter on one
	 *    of the five datetime keys, registered after boot, and is accepted.
	 *
	 * `add_post_metadata` is not hooked, and hooking it is the defect this
	 * guard set replaced. `add_metadata()` does not call `get_metadata_raw()`
	 * at all, since its `$unique` check is a direct `$wpdb->get_var()`, so the
	 * note it took was never consumed. `update_metadata()` falling through to
	 * `add_metadata()` for an absent key therefore armed a sentinel that
	 * corrupted the next occurrence-scoped read of that key. Adds make no
	 * comparison, so they need no protection.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed  $check      Short-circuit value, null when nothing has filtered yet.
	 * @param int    $object_id  Post ID being written to.
	 * @param string $meta_key   Meta key being written.
	 * @param mixed  $meta_value Value being written. Unused; present to reach `$prev_value`.
	 * @param mixed  $prev_value Previous value the caller named, or '' when it named none.
	 *
	 * @return mixed The short-circuit value, unchanged.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function note_meta_write( $check, int $object_id, string $meta_key, $meta_value = '', $prev_value = '' ) {
		$this->writing = ( null === $check && empty( $prev_value ) )
			? array( $object_id, $meta_key )
			: null;

		return $check;
	}

	/**
	 * Read one derived datetime value from the occurrence record.
	 *
	 * Every value comes from the occurrence row's own column. Nothing
	 * recombines the occurrence's date with the series anchor's time of day,
	 * which is what would foreclose multiple-times-per-day rules later.
	 *
	 * The occurrence table's `timezone` column is nullable, so an empty column
	 * falls back to the series post's own timezone meta rather than to the site
	 * default.
	 *
	 * @since 0.36.0
	 *
	 * @param array  $occurrence The occurrence row to read from.
	 * @param string $meta_key   One of the keys of `META_KEY_COLUMNS`.
	 *
	 * @return mixed The occurrence's value, or the series' own value when the column is empty.
	 */
	protected function occurrence_value( array $occurrence, string $meta_key ) {
		$value = $occurrence[ self::META_KEY_COLUMNS[ $meta_key ] ];

		return ( null === $value || '' === $value )
			? $this->read_series_meta( (int) $occurrence['series_post_id'], $meta_key )
			: $value;
	}

	/**
	 * Read a series post's own meta without the occurrence substitution.
	 *
	 * Raising `$reading` for the duration is what stops `metadata()` from
	 * re-entering itself: the nested `get_post_metadata` firing this call
	 * triggers sees the flag and returns the value untouched, so the read
	 * reaches core.
	 *
	 * The `finally` is load-bearing. Any third-party `get_post_metadata` filter
	 * that throws would otherwise leave the flag raised for the rest of the
	 * request, silently disabling occurrence substitution everywhere below. The
	 * symptom is a wrong date with no error anywhere to explain it.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id  Series post ID.
	 * @param string $meta_key Meta key to read.
	 *
	 * @return mixed The series' own stored value.
	 */
	protected function read_series_meta( int $post_id, string $meta_key ) {
		$this->reading = true;

		try {
			return get_post_meta( $post_id, $meta_key, true );
		} finally {
			$this->reading = false;
		}
	}

	/**
	 * Build the datetime-cache key a post's `Event` instance should use.
	 *
	 * `Event::$datetime_cache` is keyed by this rather than held in a single
	 * slot, because nothing stops a plugin or theme from constructing an `Event`
	 * and reading its datetime before context is established on `wp`; a single
	 * slot would hand that instance the series' values for the rest of its life.
	 *
	 * The post ID is part of the decision because a `recurrence_id` is
	 * `Ymd\THis`, so two series share one whenever they occur at the same
	 * moment. Keying on the identifier alone would let one series' `Event` serve
	 * another series' cached entry, when identity is the composite
	 * `(series_post_id, recurrence_id)`.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID the `Event` instance wraps.
	 *
	 * @return string The occurrence's identifier when one applies to this post, otherwise an empty string.
	 */
	public function cache_key( int $post_id ): string {
		$occurrence = $this->resolve( $post_id );

		return ( null === $occurrence ) ? '' : (string) $occurrence['recurrence_id'];
	}

	/**
	 * Get a post's datetime, occurrence-aware.
	 *
	 * The explicit accessor for new code inside the recurrence subsystem, which
	 * reads the occurrence record directly rather than relying on the
	 * `get_post_metadata` filter. Returns the same five-key shape as
	 * `Event::get_datetime()`, and falls back to it whenever the request is not
	 * rendering an occurrence of this post.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID to read.
	 *
	 * @return array The `Event::get_datetime()` shape, carrying the occurrence's values in context.
	 */
	public function get_datetime( int $post_id ): array {
		$occurrence = $this->resolve( $post_id );

		if ( null === $occurrence ) {
			return ( new Event( $post_id ) )->get_datetime();
		}

		$data = array();

		foreach ( self::META_KEY_COLUMNS as $meta_key => $column ) {
			$data[ $column ] = (string) $this->occurrence_value( $occurrence, $meta_key );
		}

		return $data;
	}

	/**
	 * Build the permalink of one occurrence.
	 *
	 * Delegates to `Rewrite::get_occurrence_url()`, which owns the canonical
	 * `/{event-slug}/{postname}/{Ymd\THis}/` form. This method predates that
	 * one and originally composed a `?gatherpress_occurrence=` query arg; keeping
	 * both shapes would mean two URLs resolving to the same occurrence, only
	 * one of which is canonical, and would let callers here emit links that
	 * never exercise the rewrite rules.
	 *
	 * The empty-identifier arm reads through `series_permalink()` rather than
	 * `get_permalink()`, because the latter is filtered: called while a
	 * stamped loop row is current, it answers with that row's occurrence URL,
	 * which is the opposite of what "no occurrence is named" promises.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return string The occurrence URL, or the series permalink when no occurrence is named.
	 */
	public static function occurrence_url( int $post_id, string $recurrence_id ): string {
		if ( '' === $recurrence_id ) {
			return (string) self::get_instance()->series_permalink( $post_id );
		}

		return Rewrite::get_occurrence_url( $post_id, $recurrence_id );
	}
}
