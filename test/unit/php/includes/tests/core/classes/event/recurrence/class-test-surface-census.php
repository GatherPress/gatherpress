<?php
/**
 * Class handles the front-end surface census for recurring events.
 *
 * A whole class of defect shares one shape: **occurrence identity is present
 * on the row and a consumer does not read it.** An RSVP resolver that reads
 * only the request's occurrence; an interactivity block context keyed on the
 * post ID alone; a paged archive query that never receives the join that
 * triggers expansion. None of these live inside a single unit. They live in
 * the seam where two units meet.
 *
 * This class is the census of those seams. Every test drives a **real request**,
 * meaning `go_to()`, a real `WP_Query` walked through `the_post()` and real
 * `render_block()`, and asserts the **whole per-row vector** rather than one
 * row. That distinction is the point rather than a stylistic preference: all
 * these defects produce *uniform* wrong output, which a single-row assertion
 * accepts. A vector assertion cannot be satisfied by a collapse.
 *
 * The fixture is built so that the right answer and the fallback answer differ
 * on every axis the census asserts. See
 * `build_census_fixture()` for the four separations and why each one is needed.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Blocks\Event_Date as Event_Date_Block;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Rewrite;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Topic;
use GatherPress\Tests\Base;
use WP_Post;
use WP_Query;

/**
 * Class Test_Surface_Census.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Context
 */
class Test_Surface_Census extends Base {

	/**
	 * Number of occurrences the census series projects.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const SERIES_COUNT = 6;

	/**
	 * Number of occurrences the companion series projects.
	 *
	 * Chosen so the archive's upcoming vector runs to thirteen rows, more
	 * than one page at WordPress's ten-per-page default, which is what makes
	 * the page-two surface a real surface rather than a duplicate of page one.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const COMPANION_COUNT = 8;

	/**
	 * Index of the occurrence whose stored datetime is rewritten.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const MOVED_INDEX = 2;

	/**
	 * Index of the occurrence that is canceled.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const CANCELED_INDEX = 3;

	/**
	 * Index of the occurrence that carries the census's only RSVP.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const RSVP_INDEX = 4;

	/**
	 * Date format the census renders and asserts every occurrence start in.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const DATE_FORMAT = 'Y-m-d H:i';

	/**
	 * Permalink structure in force before this class replaced it.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	protected string $permalink_structure = '';

	/**
	 * Put the site behind pretty permalinks with the occurrence rule live.
	 *
	 * Every surface in this file is reached by URL, so the rewrite layer has to
	 * be real: the occurrence URL is a rewrite rule, and `/event/page/2/` is the
	 * pretty archive pagination rule. The post type's own permastruct is built
	 * at `register_post_type()` time and only when `permalink_structure` is
	 * already non-empty, so the type is re-registered here. Without that,
	 * `get_permalink()` keeps handing back the plain `?p=` form and no request
	 * in this file exercises a rule at all.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		global $wp_rewrite;

		$this->permalink_structure = (string) $wp_rewrite->permalink_structure;

		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		unregister_taxonomy( Topic::TAXONOMY );
		Topic::get_instance()->register_taxonomy();

		Rewrite::get_instance()->add_rewrite_rules();

		$this->restore_archive_rule_precedence();

		$wp_rewrite->flush_rules();

		Context::get_instance()->clear();
		Context::flush_resolved();
		Occurrences::get_instance()->forget_table_exists();
	}

	/**
	 * Put the post type archive's rewrite rules back ahead of its single rules.
	 *
	 * A harness fixup, and it restores production ordering rather than inventing
	 * one. `register_post_type()` registers the archive rules through
	 * `add_rewrite_rule( …, 'top' )`, which *appends* to
	 * `WP_Rewrite::$extra_rules_top`, while the single-post rules are generated
	 * from the permastruct and land after that array. On a real site the archive
	 * rules are therefore ahead of them and `/event/page/2/` matches
	 * `event/page/([0-9]{1,})/?$`.
	 *
	 * The test process is different, and measurably so: every class that puts
	 * the site behind pretty permalinks re-registers the post type, which
	 * re-appends the archive rules at the **end** of the array while the earlier
	 * generated single-post rules keep their original slots. `/event/page/2/`
	 * then matches `event/([^/]+)(?:/([0-9]+))?/?$` instead and parses to
	 * `gatherpress_event=page&page=2`, a genuine 404 for a post that does not
	 * exist, produced entirely by test-process ordering.
	 *
	 * This is worth stating plainly because `class-test-archive.php` reaches the
	 * opposite conclusion in its own `setUp()` docblock, that no rule maps to
	 * `post_type=gatherpress_event` under pretty permalinks so the plain
	 * query-string form must be used. The rule is present; what is wrong is its
	 * position, and only after a re-registration.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function restore_archive_rule_precedence(): void {
		global $wp_rewrite;

		$archive   = array();
		$remainder = array();
		$needle    = 'post_type=' . Event::POST_TYPE;

		foreach ( (array) $wp_rewrite->extra_rules_top as $regex => $query ) {
			if ( str_contains( (string) $query, $needle ) ) {
				$archive[ $regex ] = $query;
			} else {
				$remainder[ $regex ] = $query;
			}
		}

		$wp_rewrite->extra_rules_top = array_merge( $archive, $remainder );
	}

	/**
	 * Restore the permalink structure and leave no context behind.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Context::get_instance()->clear();
		Context::flush_resolved();

		wp_reset_postdata();

		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure( $this->permalink_structure );
		$wp_rewrite->flush_rules();

		parent::tearDown();
	}

	/**
	 * Build "now" in UTC.
	 *
	 * Every fixture below is relative to this value rather than to a literal
	 * calendar date. That is required here rather than merely tidy: the census
	 * reaches `upcoming` / `past` buckets, `has_event_past()` and
	 * `current_time()` on every surface, so a pinned anchor would be a date bomb
	 * `class-test-loop-render.php` is the model.
	 *
	 * The series timezone is UTC throughout, so an occurrence's identifier, a
	 * *local* start in `Ymd\THis`, reads identically to its GMT columns, and a
	 * rendered wall clock can be compared against the fixture's own arithmetic
	 * with no offset bookkeeping.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable Current time in UTC.
	 */
	protected function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Create a published, non-recurring event with a datetime range.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $start Event start, in UTC.
	 * @param DateTimeImmutable $end   Event end, in UTC.
	 *
	 * @return int The created post ID.
	 */
	protected function create_event_at( DateTimeImmutable $start, DateTimeImmutable $end ): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $end->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );

		return (int) $post_id;
	}

	/**
	 * Create a daily series and project its occurrence rows.
	 *
	 * Fixtures are arranged in production order: the datetime blob and its
	 * derived row first, then the recurrence blob, then the mirrors, then the
	 * projection. That is the sequence a real save produces.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $start Anchor start, in UTC.
	 * @param DateTimeImmutable $end   Anchor end, in UTC.
	 * @param int               $count Number of occurrences to project.
	 *
	 * @return int The created post ID.
	 */
	protected function create_series_at( DateTimeImmutable $start, DateTimeImmutable $end, int $count ): int {
		$post_id = $this->create_event_at( $start, $end );

		add_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => $count,
				)
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return $post_id;
	}

	/**
	 * State the identifier of the nth occurrence of a daily series.
	 *
	 * Derived from the fixture's own arithmetic rather than read back out of
	 * the table, so the expectation is the requirement rather than a transcript
	 * of what the code produced.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $anchor Anchor start, in UTC.
	 * @param int               $index  Zero-based occurrence index.
	 *
	 * @return string The occurrence identifier in `Ymd\THis` form.
	 */
	protected function occurrence_id( DateTimeImmutable $anchor, int $index ): string {
		return $anchor->modify( sprintf( '+%d days', $index ) )->format( 'Ymd\THis' );
	}

	/**
	 * Build the census fixture, and state every row it must produce.
	 *
	 * Four deliberate separations, one per axis the census asserts. Each exists
	 * because without it the right answer and a fallback answer coincide, and an
	 * assertion that cannot distinguish them is not a test:
	 *
	 * 1. **The series anchor is in the past.** Its first occurrence has already
	 *    started, so the bare-series URL's "next upcoming occurrence" is the
	 *    *second* one. Anchoring in the future would make the anchor and the
	 *    next-upcoming occurrence the same row, and the bare-series surface
	 *    would pass with the resolver removed entirely, which is vacuous.
	 * 2. **One occurrence's stored datetime is rewritten.** The expander holds
	 *    the wall-clock time constant across a series, so for a rule-generated
	 *    row "the record's datetime" and "the anchor's time applied to the
	 *    record's date" are the same value and no rendered clock can separate
	 *    them. Rewriting one row's own columns makes the two answers
	 *    differ. Its *identifier* is deliberately left alone, so the moved row's
	 *    URL segment and its rendered date disagree, which additionally kills
	 *    any implementation that derives the date back out of the URL.
	 * 3. **A second series interleaves with the first.** Ordering by
	 *    `post_date`, the archive's behavior before it was fixed, groups one
	 *    series' occurrences together and shares no prefix with ordering by
	 *    occurrence datetime. The two orderings are separated on page one.
	 * 4. **Exactly one occurrence carries an RSVP, and it is neither the first
	 *    nor the anchor's.** A series-wide read shows it on every row; a read
	 *    of the request's occurrence shows it on none of a loop's rows. Both
	 *    fallbacks are uniform, and the required answer is not.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, mixed> Post IDs, anchors and the RSVP user, keyed by role.
	 */
	protected function build_census_fixture(): array {
		global $wpdb;

		$now = $this->now();

		$series_anchor    = $now->modify( '-1 hour' );
		$companion_anchor = $now->modify( '+2 hours' );
		$plain_start      = $now->modify( '+30 minutes' );

		$series_id = $this->create_series_at(
			$series_anchor,
			$series_anchor->modify( '+30 minutes' ),
			self::SERIES_COUNT
		);

		$companion_id = $this->create_series_at(
			$companion_anchor,
			$companion_anchor->modify( '+30 minutes' ),
			self::COMPANION_COUNT
		);

		$plain_id = $this->create_event_at( $plain_start, $plain_start->modify( '+30 minutes' ) );

		// Separation 2: rewrite one row's stored datetime, five hours earlier
		// than the rule would place it. Still upcoming, and still ordered
		// between its two neighbors, so the move changes the row's own clock
		// without reordering the vector the other surfaces assert.
		$moved_start = $series_anchor->modify( sprintf( '+%d days', self::MOVED_INDEX ) )->modify( '-5 hours' );
		$table       = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET datetime_start = %s, datetime_start_gmt = %s,'
					. ' datetime_end = %s, datetime_end_gmt = %s'
					. ' WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				$moved_start->format( 'Y-m-d H:i:s' ),
				$moved_start->format( 'Y-m-d H:i:s' ),
				$moved_start->modify( '+30 minutes' )->format( 'Y-m-d H:i:s' ),
				$moved_start->modify( '+30 minutes' )->format( 'Y-m-d H:i:s' ),
				$series_id,
				$this->occurrence_id( $series_anchor, self::MOVED_INDEX )
			)
		);

		Occurrences::get_instance()->set_status(
			$series_id,
			$this->occurrence_id( $series_anchor, self::CANCELED_INDEX ),
			Occurrences::STATUS_CANCELED
		);

		$user_id = $this->factory->user->create();

		// Written through the same context a real request establishes, so the
		// stored comment carries exactly one occurrence term.
		Context::get_instance()->set( $series_id, $this->occurrence_id( $series_anchor, self::RSVP_INDEX ) );
		( new Rsvp( $series_id ) )->save( $user_id, 'attending' );
		Context::get_instance()->clear();

		return array(
			'series'           => $series_id,
			'series_anchor'    => $series_anchor,
			'companion'        => $companion_id,
			'companion_anchor' => $companion_anchor,
			'plain'            => $plain_id,
			'plain_start'      => $plain_start,
			'moved_start'      => $moved_start,
			'user'             => $user_id,
		);
	}

	/**
	 * State the start of one occurrence of the census series.
	 *
	 * The moved row reports its rewritten start; every other row reports the
	 * anchor plus whole days. Stated by the fixture rather than read from the
	 * table, so a projection defect cannot rewrite the expectation to match
	 * itself.
	 *
	 * @since 0.36.0
	 *
	 * @param array $fixture Fixture returned by `build_census_fixture()`.
	 * @param int   $index   Zero-based occurrence index.
	 *
	 * @return DateTimeImmutable The occurrence's own start.
	 */
	protected function series_start( array $fixture, int $index ): DateTimeImmutable {
		if ( self::MOVED_INDEX === $index ) {
			return $fixture['moved_start'];
		}

		return $fixture['series_anchor']->modify( sprintf( '+%d days', $index ) );
	}

	/**
	 * State the whole per-row vector the upcoming surfaces must produce.
	 *
	 * One entry per row a visitor should see, ordered ascending by the row's own
	 * start: the plain event, every scheduled upcoming occurrence of both
	 * series, and nothing else. The canceled occurrence is absent by
	 * construction, and the series' first occurrence is absent because
	 * it has already started.
	 *
	 * @since 0.36.0
	 *
	 * @param array $fixture Fixture returned by `build_census_fixture()`.
	 *
	 * @return array<int, array<string, mixed>> The required rows, in required order.
	 */
	protected function expected_upcoming_rows( array $fixture ): array {
		$rows = array(
			array(
				'start' => $fixture['plain_start'],
				'row'   => $this->expected_row( $fixture['plain'], '', $fixture['plain_start'], 0 ),
			),
		);

		for ( $index = 0; $index < self::SERIES_COUNT; $index++ ) {
			$start = $this->series_start( $fixture, $index );

			if ( self::CANCELED_INDEX === $index || $start <= $this->now() ) {
				continue;
			}

			$rows[] = array(
				'start' => $start,
				'row'   => $this->expected_row(
					$fixture['series'],
					$this->occurrence_id( $fixture['series_anchor'], $index ),
					$start,
					( self::RSVP_INDEX === $index ) ? 1 : 0
				),
			);
		}

		for ( $index = 0; $index < self::COMPANION_COUNT; $index++ ) {
			$start = $fixture['companion_anchor']->modify( sprintf( '+%d days', $index ) );

			$rows[] = array(
				'start' => $start,
				'row'   => $this->expected_row(
					$fixture['companion'],
					$this->occurrence_id( $fixture['companion_anchor'], $index ),
					$start,
					0
				),
			);
		}

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				return $left['start'] <=> $right['start'];
			}
		);

		return wp_list_pluck( $rows, 'row' );
	}

	/**
	 * Compose one expected row of a surface's vector.
	 *
	 * The permalink is built as a literal URL rather than through
	 * `Rewrite::get_occurrence_url()`, so the assertion states the
	 * `{permalink}{Ymd}T{His}/` shape itself instead of agreeing with whatever
	 * the production builder currently emits.
	 *
	 * **The contract is uniform: wherever an occurrence is in play, that post's
	 * permalink is the occurrence's URL.** This census originally carried an
	 * `$occurrence_link` flag because it was not. `Context::permalink()` stood
	 * down on a *singular* occurrence request, on the reasoning that the
	 * requested URL already was the occurrence URL and rewriting
	 * `get_permalink()` would disturb core's canonical-redirect comparison. That
	 * left `get_permalink()` returning the *bare series* URL on an occurrence
	 * page, which resolves to the **next upcoming** occurrence, so the iCal
	 * `URL:` field and every link on the page named a different date.
	 *
	 * The stand-down is gone: `redirect_canonical()` never reaches
	 * `get_permalink()` on a matched occurrence rewrite rule, so the flag is
	 * gone with it. Two contracts became one.
	 *
	 * @since 0.36.0
	 *
	 * @param int               $post_id       Post the row renders.
	 * @param string            $recurrence_id Occurrence identifier, or '' for a non-recurring row.
	 * @param DateTimeImmutable $start         The row's own start.
	 * @param int               $attending     Attendee count the row must report.
	 *
	 * @return array<string, mixed> The expected row.
	 */
	protected function expected_row(
		int $post_id,
		string $recurrence_id,
		DateTimeImmutable $start,
		int $attending
	): array {
		$slug = (string) get_post_field( 'post_name', $post_id );
		$base = home_url( sprintf( '/event/%s/', $slug ) );

		return array(
			'post'      => $post_id,
			'context'   => ( '' === $recurrence_id )
				? array( 'postId' => $post_id )
				: array(
					'postId'       => $post_id,
					'recurrenceId' => $recurrence_id,
				),
			'date'      => $start->format( self::DATE_FORMAT ),
			'href'      => ( '' === $recurrence_id ) ? $base : $base . $recurrence_id . '/',
			'attending' => $attending,
		);
	}

	/**
	 * Render one block the way `core/post-template` renders it.
	 *
	 * No `postId` attribute is supplied, because a Query Loop supplies none
	 * either: `Blocks\Setup::get_post_id()` falls through to `get_the_ID()`.
	 * Passing an ID here would route around the very wiring under test.
	 *
	 * @since 0.36.0
	 *
	 * @param string $block_name Block type name.
	 * @param array  $attrs      Block attributes.
	 *
	 * @return string The rendered block markup.
	 */
	protected function render_named_block( string $block_name, array $attrs = array() ): string {
		return render_block(
			array(
				'blockName'    => $block_name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Read the interactivity block context out of rendered block markup.
	 *
	 * @since 0.36.0
	 *
	 * @param string $html Rendered block markup.
	 *
	 * @return array The decoded `data-wp-context` payload, or an empty array when the markup carries none.
	 */
	protected function block_context_from( string $html ): array {
		if ( ! preg_match( '/data-wp-context=([\'"])(.*?)\1/s', $html, $matches ) ) {
			return array();
		}

		$decoded = json_decode( html_entity_decode( $matches[2] ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Render the whole vector entry for whatever post is currently set up.
	 *
	 * Everything here comes out of rendered block markup rather than out of an
	 * internal resolver, because the seam the census exists for is precisely the
	 * step a direct call skips.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, mixed> The row's rendered identity, date, permalink and RSVP count.
	 */
	protected function rendered_row(): array {
		$date_html  = $this->render_named_block(
			Event_Date_Block::BLOCK_NAME,
			array(
				'displayType'     => 'start',
				'startDateFormat' => self::DATE_FORMAT,
				'isLink'          => true,
				// Pinned rather than left to the site's `show_timezone` setting,
				// so the rendered clock is the fixture's own arithmetic and
				// nothing else.
				'showTimezone'    => 'no',
			)
		);
		$count_html = $this->render_named_block( 'gatherpress/rsvp-count' );

		preg_match( '#<a href="([^"]*)">([^<]*)</a>#', $date_html, $link );
		preg_match( '#gatherpress-rsvp-count__text">(\d+)#', $count_html, $count );

		return array(
			'post'      => (int) get_the_ID(),
			'context'   => $this->block_context_from( $count_html ),
			'date'      => trim( $link[2] ?? '' ),
			'href'      => html_entity_decode( $link[1] ?? '' ),
			'attending' => (int) ( $count[1] ?? -1 ),
		);
	}

	/**
	 * Walk a query's loop and render every row's vector entry.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Query $query Executed query.
	 *
	 * @return array<int, array<string, mixed>> One entry per loop iteration, in loop order.
	 */
	protected function rendered_vector( WP_Query $query ): array {
		$vector = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$vector[] = $this->rendered_row();
		}

		wp_reset_postdata();

		return $vector;
	}

	/**
	 * Run an occurrence-aware event query through the production wiring.
	 *
	 * @since 0.36.0
	 *
	 * @param string $bucket Either `upcoming` or `past`.
	 *
	 * @return WP_Query The executed query.
	 */
	protected function run_bucket_query( string $bucket ): WP_Query {
		return new WP_Query(
			array(
				'post_type'                    => Event::POST_TYPE,
				Event_Query::EVENT_QUERY_PARAM => $bucket,
				'posts_per_page'               => 50,
				'orderby'                      => 'datetime',
				'order'                        => ( 'past' === $bucket ) ? 'DESC' : 'ASC',
			)
		);
	}

	/**
	 * Drive one real archive request and return the resulting global query.
	 *
	 * `go_to()` runs `WP::main()`, which parses the request, runs the main query
	 * and calls `handle_404()`. `template_redirect` is what
	 * `wp-includes/template-loader.php` fires immediately afterwards, and it is
	 * the hook `Event\Setup::handle_event_archive_redirect()` registers on, so
	 * firing it here is the production sequence rather than a shortcut.
	 *
	 * @since 0.36.0
	 *
	 * @param int $page Page number to request.
	 *
	 * @return WP_Query The global query after the archive request completes.
	 */
	protected function request_archive_page( int $page ): WP_Query {
		$url = (string) get_post_type_archive_link( Event::POST_TYPE );

		if ( 1 < $page ) {
			$url = trailingslashit( $url ) . sprintf( 'page/%d/', $page );
		}

		$this->go_to( $url );

		// Firing a core hook, not declaring a plugin one: this is the action
		// `wp-includes/template-loader.php` fires after `WP::main()` returns.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'template_redirect' );

		global $wp_query;

		return $wp_query;
	}

	/**
	 * Drive one real singular request and render its vector entry.
	 *
	 * @since 0.36.0
	 *
	 * @param string $url URL to request.
	 *
	 * @return array<string, mixed> The rendered row.
	 */
	protected function request_singular_row( string $url ): array {
		$this->go_to( $url );

		global $wp_query;

		$wp_query->the_post();

		$row = $this->rendered_row();

		wp_reset_postdata();

		return $row;
	}

	/**
	 * Reduce a query's results to `post_id|recurrence_id` strings.
	 *
	 * Identity is read off each result object, never off its list position.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Query $query Executed query.
	 *
	 * @return string[] One entry per result row.
	 */
	protected function entries( WP_Query $query ): array {
		return array_map(
			static function ( WP_Post $post ): string {
				return $post->ID . '|' . (string) $post->gatherpress_recurrence_id;
			},
			$query->posts
		);
	}

	/**
	 * Reduce an expected vector to `post_id|recurrence_id` strings.
	 *
	 * @since 0.36.0
	 *
	 * @param array $rows Expected rows.
	 *
	 * @return string[] One entry per expected row.
	 */
	protected function expected_entries( array $rows ): array {
		return array_map(
			static function ( array $row ): string {
				return $row['post'] . '|' . (string) ( $row['context']['recurrenceId'] ?? '' );
			},
			$rows
		);
	}

	/**
	 * Census of the singular occurrence URL, `{permalink}{Ymd}T{His}/`.
	 *
	 * One real request per scheduled occurrence, and the assertion is on the
	 * whole vector of what those requests rendered. A resolver that ignored the
	 * URL's occurrence segment entirely would render the same row every time,
	 * which is exactly what a per-request assertion accepts and a vector
	 * assertion cannot.
	 *
	 * @covers ::metadata
	 * @covers ::sync
	 * @covers ::maybe_set_from_request
	 * @covers ::permalink
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::parse_request
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::block_context
	 *
	 * @return void
	 */
	public function test_singular_occurrence_urls_each_render_their_own_row(): void {
		$fixture   = $this->build_census_fixture();
		$expected  = array();
		$actual    = array();
		$requested = array();

		for ( $index = 0; $index < self::SERIES_COUNT; $index++ ) {
			if ( self::CANCELED_INDEX === $index ) {
				continue;
			}

			$recurrence_id = $this->occurrence_id( $fixture['series_anchor'], $index );
			$start         = $this->series_start( $fixture, $index );

			$expected[] = $this->expected_row(
				$fixture['series'],
				$recurrence_id,
				$start,
				( self::RSVP_INDEX === $index ) ? 1 : 0
			);

			$url         = Rewrite::get_occurrence_url( $fixture['series'], $recurrence_id );
			$requested[] = $url;
			$actual[]    = $this->request_singular_row( $url );
		}

		$this->assertSame(
			$expected,
			$actual,
			'Failed to assert every occurrence URL rendered its own date, block context and RSVP count.'
		);

		$this->assertSame(
			count( $expected ),
			count( array_unique( wp_list_pluck( $actual, 'date' ) ) ),
			'Failed to assert the occurrence URLs rendered distinct dates rather than collapsing onto one.'
		);

		// The URLs themselves, because on this surface the *requested* URL is
		// the occurrence permalink, while the rendered one deliberately is not.
		$this->assertSame(
			count( $expected ),
			count( array_unique( $requested ) ),
			'Failed to assert the series composed a distinct URL per occurrence.'
		);

		foreach ( $requested as $url ) {
			$this->assertMatchesRegularExpression(
				'#/\d{8}T\d{6}/$#',
				$url,
				'Failed to assert the occurrence URL carries the {Ymd}T{His} segment.'
			);
		}
	}

	/**
	 * Census of the bare series URL.
	 *
	 * The series anchor is in the past, so the next upcoming occurrence is the
	 * *second* one and never the anchor's own row. That separation is the whole
	 * value of this test: with a future anchor the two coincide, and the surface
	 * passes with the resolver removed.
	 *
	 * @covers ::sync
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::maybe_resolve_bare_series
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::next_upcoming_recurrence_id
	 *
	 * @return void
	 */
	public function test_bare_series_url_renders_the_next_upcoming_occurrence(): void {
		$fixture = $this->build_census_fixture();

		$expected = $this->expected_row(
			$fixture['series'],
			$this->occurrence_id( $fixture['series_anchor'], 1 ),
			$this->series_start( $fixture, 1 ),
			0
		);

		$actual = $this->request_singular_row(
			Context::get_instance()->series_permalink( $fixture['series'] )
		);

		$this->assertSame(
			$expected,
			$actual,
			'Failed to assert the bare series URL resolved to the next upcoming occurrence rather than the anchor.'
		);
		$this->assertNotSame(
			$fixture['series_anchor']->format( self::DATE_FORMAT ),
			$actual['date'],
			'Failed to assert the bare series URL did not fall through to the series anchor\'s own date.'
		);
	}

	/**
	 * Census of the `/event/` archive, page one and page two.
	 *
	 * The paged case is the fragile one: if page two's SQL carries no occurrence
	 * join, four event posts at `LIMIT 10, 10` yield nothing and the request
	 * 404s, making most events unreachable. Both pages are asserted
	 * here, and the two pages' rows concatenated must equal the whole required
	 * vector. A page-one-only assertion is satisfied by exactly the broken
	 * state.
	 *
	 * @covers \GatherPress\Core\Event\Setup::defer_event_archive_404
	 * @covers \GatherPress\Core\Event\Setup::substitute_archive_query
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 * @covers \GatherPress\Core\Event\Recurrence\Query::attach_occurrences
	 *
	 * @return void
	 */
	public function test_event_archive_pages_one_and_two_carry_the_whole_vector(): void {
		$fixture  = $this->build_census_fixture();
		$expected = $this->expected_upcoming_rows( $fixture );

		$this->assertGreaterThan(
			(int) get_option( 'posts_per_page' ),
			count( $expected ),
			'Failed to assert the fixture spans more than one archive page, which is what makes page two a surface.'
		);

		$first  = $this->request_archive_page( 1 );
		$page_1 = $this->rendered_vector( $first );

		$this->assertFalse(
			$first->is_404(),
			'Failed to assert the first archive page is not a 404.'
		);

		$second = $this->request_archive_page( 2 );
		$page_2 = $this->rendered_vector( $second );

		$this->assertFalse(
			$second->is_404(),
			'Failed to assert the second archive page is reachable rather than a 404.'
		);
		$this->assertNotEmpty(
			$page_2,
			'Failed to assert the second archive page rendered rows rather than an empty list.'
		);

		$this->assertSame(
			$expected,
			array_merge( $page_1, $page_2 ),
			'Failed to assert the paged archive rendered every required row, in occurrence-datetime order, with'
				. ' its own date, permalink, block context and RSVP count.'
		);
	}

	/**
	 * Census of a Query Loop carrying an upcoming bucket.
	 *
	 * @covers ::loop_occurrence
	 * @covers ::metadata
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::current_occurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::block_context
	 *
	 * @return void
	 */
	public function test_upcoming_query_loop_renders_the_whole_vector(): void {
		$fixture = $this->build_census_fixture();

		$this->assertSame(
			$this->expected_upcoming_rows( $fixture ),
			$this->rendered_vector( $this->run_bucket_query( 'upcoming' ) ),
			'Failed to assert an upcoming Query Loop rendered one correct row per occurrence.'
		);
	}

	/**
	 * Census of a Query Loop carrying a past bucket.
	 *
	 * The series' first occurrence is the only row that has already run, so the
	 * past bucket is the surface that proves the buckets are not the same list
	 * with a different label. It is also the one place the anchor's own
	 * occurrence appears, with its own identity rather than a bare post ID.
	 *
	 * @covers ::loop_occurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_past_query_loop_renders_only_the_occurrence_that_has_run(): void {
		$fixture = $this->build_census_fixture();

		$expected = array(
			$this->expected_row(
				$fixture['series'],
				$this->occurrence_id( $fixture['series_anchor'], 0 ),
				$this->series_start( $fixture, 0 ),
				0
			),
		);

		$this->assertSame(
			$expected,
			$this->rendered_vector( $this->run_bucket_query( 'past' ) ),
			'Failed to assert the past bucket rendered the one occurrence that has run, with its own identity.'
		);
	}

	/**
	 * Census of a loop rendered on a singular occurrence page.
	 *
	 * Request-versus-loop precedence collapsed here twice. On this surface both
	 * an occurrence context and a per-row stamp are in play, and they disagree
	 * on every row but one: preferring the request collapses the loop onto the
	 * requested date, and ignoring the stamp collapses it onto the anchor. The
	 * requested occurrence is deliberately the RSVP'd one, so a precedence
	 * inversion would additionally show the attendee on every row.
	 *
	 * @covers ::resolve
	 * @covers ::loop_occurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::current_occurrence
	 *
	 * @return void
	 */
	public function test_a_loop_on_an_occurrence_page_keeps_each_rows_own_identity(): void {
		$fixture  = $this->build_census_fixture();
		$expected = $this->expected_upcoming_rows( $fixture );

		$this->go_to(
			Rewrite::get_occurrence_url(
				$fixture['series'],
				$this->occurrence_id( $fixture['series_anchor'], self::RSVP_INDEX )
			)
		);

		$this->assertNotNull(
			Context::get_instance()->current(),
			'Failed to assert the request established occurrence context, without which this surface is not the'
				. ' one under test.'
		);

		$this->assertSame(
			$expected,
			$this->rendered_vector( $this->run_bucket_query( 'upcoming' ) ),
			'Failed to assert a loop on an occurrence page kept each row\'s own identity rather than inheriting'
				. ' the request\'s occurrence.'
		);
	}

	/**
	 * Census of a canceled occurrence.
	 *
	 * Three claims, and all three are needed: it is absent from every list, its
	 * URL still resolves rather than 404ing, and what it renders says it was
	 * canceled while still carrying its own date.
	 *
	 * @covers ::maybe_prepend_canceled_notice
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::parse_request
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_canceled_occurrence_is_hidden_from_lists_but_still_resolves(): void {
		$fixture       = $this->build_census_fixture();
		$recurrence_id = $this->occurrence_id( $fixture['series_anchor'], self::CANCELED_INDEX );
		$entry         = $fixture['series'] . '|' . $recurrence_id;

		$listed = array_merge(
			$this->entries( $this->request_archive_page( 1 ) ),
			$this->entries( $this->request_archive_page( 2 ) ),
			$this->entries( $this->run_bucket_query( 'upcoming' ) ),
			$this->entries( $this->run_bucket_query( 'past' ) )
		);

		$this->assertNotEmpty( $listed, 'Failed to assert the lists rendered anything at all.' );
		$this->assertNotContains(
			$entry,
			$listed,
			'Failed to assert the canceled occurrence is absent from every list.'
		);

		$this->go_to( Rewrite::get_occurrence_url( $fixture['series'], $recurrence_id ) );

		global $wp_query;

		$this->assertFalse(
			$wp_query->is_404(),
			'Failed to assert a canceled occurrence\'s own URL still resolves.'
		);

		$wp_query->the_post();

		$this->assertSame(
			$this->series_start( $fixture, self::CANCELED_INDEX )->format( self::DATE_FORMAT ),
			$this->rendered_row()['date'],
			'Failed to assert the canceled occurrence\'s page renders its own date.'
		);
		// Applying a core filter, not declaring a plugin one: this is the filter
		// every theme template runs post content through.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$rendered = apply_filters( 'the_content', 'Body copy.' );

		$this->assertStringContainsString(
			'This occurrence has been canceled.',
			$rendered,
			'Failed to assert the canceled occurrence tells the attendee holding the link that it was canceled.'
		);

		wp_reset_postdata();
	}

	/**
	 * Census of a site with no recurring events being untouched.
	 *
	 * The capture is taken from `$wpdb->queries` across the **real** entry
	 * points, meaning the singular event URL, both archive pages and a bucket
	 * loop, rather than from inside any one filter, because a filter-level capture
	 * only observes the queries that already reach that filter, which lets a
	 * "performs no writes" test pass over an unguarded entry point.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Query::site_has_recurring_events
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::parse_request
	 *
	 * @return void
	 */
	public function test_every_surface_runs_identical_sql_without_recurring_events(): void {
		$now = $this->now();

		for ( $index = 1; $index <= 12; $index++ ) {
			$start = $now->modify( sprintf( '+%d hours', $index ) );

			$this->create_event_at( $start, $start->modify( '+30 minutes' ) );
		}

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$with = $this->capture_surface_queries();

		$this->detach_recurrence_hooks();

		$without = $this->capture_surface_queries();

		$this->attach_recurrence_hooks();

		$this->assertNotEmpty(
			$with,
			'Failed to assert the capture actually observed the requests.'
		);
		$this->assertSame(
			$without,
			$with,
			'Failed to assert a flag-off site runs byte-identical SQL across every surface with and without the'
				. ' recurrence subsystem attached.'
		);
		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert the surfaces performed no option write.'
		);
	}

	/**
	 * Detach every recurrence read-path hook a front-end request reaches.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function detach_recurrence_hooks(): void {
		$query   = Query::get_instance();
		$context = Context::get_instance();
		$rewrite = Rewrite::get_instance();

		remove_filter( 'posts_clauses', array( $query, 'expand_event_clauses' ), 11 );
		remove_filter( 'the_posts', array( $query, 'attach_occurrences' ), 10 );
		remove_filter( 'get_post_metadata', array( $context, 'metadata' ), 10 );
		remove_action( 'wp', array( $context, 'sync' ), 10 );
		remove_filter( 'the_content', array( $context, 'maybe_prepend_canceled_notice' ), 10 );
		remove_filter( 'post_type_link', array( $context, 'permalink' ), 10 );
		remove_filter( 'post_link', array( $context, 'permalink' ), 10 );
		remove_action( 'parse_request', array( $rewrite, 'parse_request' ), 10 );
	}

	/**
	 * Reattach every hook `detach_recurrence_hooks()` removed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function attach_recurrence_hooks(): void {
		$query   = Query::get_instance();
		$context = Context::get_instance();
		$rewrite = Rewrite::get_instance();

		add_filter( 'posts_clauses', array( $query, 'expand_event_clauses' ), 11, 2 );
		add_filter( 'the_posts', array( $query, 'attach_occurrences' ), 10, 2 );
		add_filter( 'get_post_metadata', array( $context, 'metadata' ), 10, 4 );
		add_action( 'wp', array( $context, 'sync' ) );
		add_filter( 'the_content', array( $context, 'maybe_prepend_canceled_notice' ) );
		add_filter( 'post_type_link', array( $context, 'permalink' ), 10, 2 );
		add_filter( 'post_link', array( $context, 'permalink' ), 10, 2 );
		add_action( 'parse_request', array( $rewrite, 'parse_request' ) );
	}

	/**
	 * Capture every SQL statement the census's surfaces run.
	 *
	 * Two details make the capture comparable rather than merely repeatable. The
	 * whole sequence is run once and thrown away before the capture starts,
	 * because the first run of any request primes the object cache and the
	 * second therefore issues fewer statements. Comparing a cold capture
	 * against a warm one measures the cache, not the hooks. And the current GMT
	 * timestamp `Event\Query::adjust_event_sql()` interpolates is normalized
	 * away, because two captures taken microseconds apart can straddle a second
	 * boundary. Nothing else is touched, so an added join, an added column or an
	 * extra statement still fails the comparison.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The statements, in execution order.
	 */
	protected function capture_surface_queries(): array {
		global $wpdb;

		$this->drive_every_surface();

		$previous_queries   = $wpdb->queries;
		$previous_save      = $wpdb->save_queries;
		$wpdb->queries      = array();
		$wpdb->save_queries = true;

		$this->drive_every_surface();

		$captured = array_map(
			static function ( array $entry ): string {
				return (string) preg_replace(
					'/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',
					'{datetime}',
					(string) $entry[0]
				);
			},
			$wpdb->queries
		);

		$wpdb->queries      = $previous_queries;
		$wpdb->save_queries = $previous_save;

		return $captured;
	}

	/**
	 * Drive one pass over every surface the census covers.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function drive_every_surface(): void {
		$this->rendered_vector( $this->request_archive_page( 1 ) );
		$this->rendered_vector( $this->request_archive_page( 2 ) );
		$this->rendered_vector( $this->run_bucket_query( 'upcoming' ) );

		$posts = get_posts(
			array(
				'post_type'      => Event::POST_TYPE,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $posts ) ) {
			$this->request_singular_row( (string) get_permalink( (int) $posts[0] ) );
		}
	}
}
