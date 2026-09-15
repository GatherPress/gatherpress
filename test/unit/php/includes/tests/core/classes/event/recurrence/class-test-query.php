<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Query.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Calendar\Setup as Calendar_Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Setup;
use GatherPress\Core\Topic;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Post;
use WP_Query;

/**
 * Class Test_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Query
 */
class Test_Query extends Base {

	/**
	 * The reference daily rule: five consecutive daily occurrences.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const DAILY_RULE = array(
		'frequency' => 'daily',
		'interval'  => 1,
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * Start every test from an empty occurrence table, independent of
	 * execution order relative to Test_Schema.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		update_option( Query::HAS_RECURRING_OPTION, '0', true );
	}

	/**
	 * Build "now" in UTC.
	 *
	 * Every fixture in this file is relative to this value rather than to a
	 * literal calendar date, so no test in the file is a date bomb, and the
	 * series timezone is UTC so a recurrence identifier (a *local* start) and
	 * the GMT columns read identically.
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
	 * @param DateTimeImmutable $start Event start in UTC.
	 * @param DateTimeImmutable $end   Event end in UTC.
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
	 * Create a recurring series and project its occurrence rows.
	 *
	 * Fixtures are arranged in production order: the datetime blob and its
	 * derived row land first, then the recurrence blob, then the mirrors, then
	 * the projection. That is exactly the sequence a real save produces.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $start Anchor start in UTC.
	 * @param DateTimeImmutable $end   Anchor end in UTC.
	 * @param array             $rule  Recurrence rule values.
	 *
	 * @return int The created post ID.
	 */
	protected function create_series_at(
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		array $rule = self::DAILY_RULE
	): int {
		$post_id = $this->create_event_at( $start, $end );

		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( $rule ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return $post_id;
	}

	/**
	 * Build the shared fixture set used by most tests in this file.
	 *
	 * The series anchor ends thirty minutes ago, so its first occurrence is the
	 * only past one and the remaining four are upcoming. Two standalone events
	 * bracket the series in time so an ordering assertion can prove that
	 * occurrences and plain events interleave rather than clustering.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, mixed> Post IDs and the anchor, keyed by role.
	 */
	protected function build_scenario(): array {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		return array(
			'anchor'  => $anchor,
			'series'  => $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) ),
			'early'   => $this->create_event_at( $now->modify( '+12 hours' ), $now->modify( '+13 hours' ) ),
			'mid'     => $this->create_event_at( $now->modify( '+60 hours' ), $now->modify( '+61 hours' ) ),
			'past'    => $this->create_event_at( $now->modify( '-48 hours' ), $now->modify( '-47 hours' ) ),
			'undated' => $this->factory->post->create(
				array(
					'post_type'   => Event::POST_TYPE,
					'post_status' => 'publish',
				)
			),
		);
	}

	/**
	 * Build the recurrence identifier of the nth occurrence of a daily series.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $anchor Anchor start in UTC.
	 * @param int               $index  Zero-based occurrence index.
	 *
	 * @return string The recurrence identifier in `Ymd\THis` form.
	 */
	protected function occurrence_id( DateTimeImmutable $anchor, int $index ): string {
		return $anchor->modify( sprintf( '+%d days', $index ) )->format( 'Ymd\THis' );
	}

	/**
	 * Run an occurrence-aware event query and return it.
	 *
	 * Drives a real `WP_Query` through the production `pre_get_posts` and
	 * `posts_clauses` wiring rather than calling the filter callback directly.
	 *
	 * @since 0.36.0
	 *
	 * @param string $bucket Either `upcoming` or `past`.
	 * @param array  $args   Additional query arguments.
	 *
	 * @return WP_Query The executed query.
	 */
	protected function run_event_query( string $bucket, array $args = array() ): WP_Query {
		return new WP_Query( $this->event_query_args( $bucket, $args ) );
	}

	/**
	 * Build the arguments of a bucketed event query.
	 *
	 * @since 0.36.0
	 *
	 * @param string $bucket Either `upcoming` or `past`.
	 * @param array  $args   Additional query arguments.
	 *
	 * @return array The query arguments.
	 */
	protected function event_query_args( string $bucket, array $args = array() ): array {
		return array_merge(
			array(
				'post_type'                    => Event::POST_TYPE,
				Event_Query::EVENT_QUERY_PARAM => $bucket,
				'posts_per_page'               => 20,
				'orderby'                      => 'datetime',
				'order'                        => 'upcoming' === $bucket ? 'ASC' : 'DESC',
			),
			$args
		);
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
	 * A site with no recurring events runs byte-identical SQL.
	 *
	 * Captures the clause array a real event query produces with the filter
	 * registered and with it removed, and asserts the two are identical. The
	 * early return in `expand_event_clauses()` is the only thing that can make
	 * this pass, and deleting it makes every clause grow a join.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_generated_sql_is_byte_identical_without_recurring_events(): void {
		$now = $this->now();

		$this->create_event_at( $now->modify( '+2 hours' ), $now->modify( '+3 hours' ) );

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		[ $with, $without ] = $this->capture_clauses_both_ways( $this->event_query_args( 'upcoming' ) );

		$this->assertSame(
			$without,
			$with,
			'Failed to assert that a site without recurring events produces byte-identical SQL clauses.'
		);
	}

	/**
	 * Coverage for the scope guard: a non-event query is never touched.
	 *
	 * The clause filter runs on every `posts_clauses`, so on a site that does
	 * have recurring events the only thing keeping the occurrence join off
	 * posts, pages, search and REST collections is the events-table check.
	 * Same shape as the byte-identical assertion above, on the other guard arm.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_plain_post_query_clauses_are_unchanged_on_a_recurring_site(): void {
		$this->build_scenario();

		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has recurring events.'
		);

		$this->factory->post->create( array( 'post_status' => 'publish' ) );

		[ $with, $without ] = $this->capture_clauses_both_ways(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 5,
			)
		);

		$this->assertSame(
			$without,
			$with,
			'Failed to assert a plain post query is untouched on a site with recurring events.'
		);
		$this->assertStringNotContainsString(
			Query::OCCURRENCE_ALIAS,
			(string) $with['join'],
			'Failed to assert the occurrence table is not joined into a non-event query.'
		);
	}

	/**
	 * Coverage at the callback boundary: the clause array is returned unchanged.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_expand_event_clauses_returns_pieces_unchanged_without_recurring_events(): void {
		global $wpdb;

		$pieces = array(
			'where'    => " AND {$wpdb->posts}.post_type = 'gatherpress_event'",
			'groupby'  => '',
			'join'     => ' LEFT JOIN ' . $wpdb->prefix . 'gatherpress_events ON ' . $wpdb->posts . '.ID='
						. $wpdb->prefix . 'gatherpress_events.post_id',
			'orderby'  => $wpdb->prefix . 'gatherpress_events.datetime_start_gmt ASC',
			'distinct' => '',
			'fields'   => "{$wpdb->posts}.*",
			'limits'   => '',
		);

		$this->assertSame(
			$pieces,
			Query::get_instance()->expand_event_clauses( $pieces, new WP_Query() ),
			'Failed to assert that the clause filter is a no-op without recurring events.'
		);
	}

	/**
	 * Capture the `posts_clauses` array a bucketed event query ends up with.
	 *
	 * @since 0.36.0
	 *
	 * @param string $bucket Either `upcoming` or `past`.
	 *
	 * @return array The captured clause array.
	 */
	protected function capture_clauses( string $bucket ): array {
		return $this->capture_clauses_for( $this->event_query_args( $bucket ) );
	}

	/**
	 * Capture the `posts_clauses` array any query ends up with.
	 *
	 * @since 0.36.0
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array The captured clause array.
	 */
	protected function capture_clauses_for( array $args ): array {
		$captured = array();
		$capture  = static function ( array $pieces ) use ( &$captured ): array {
			$captured = $pieces;

			return $pieces;
		};

		add_filter( 'posts_clauses', $capture, 12 );
		new WP_Query( $args );
		remove_filter( 'posts_clauses', $capture, 12 );

		return $captured;
	}

	/**
	 * Capture the clauses of one query with and without the clause filter.
	 *
	 * @since 0.36.0
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array{0: array, 1: array} The clauses with, then without, the filter.
	 */
	protected function capture_clauses_both_ways( array $args ): array {
		// Both captures build their own `WHERE`, and the bucket predicate
		// embeds `current_time()` down to the second. A second boundary
		// crossing between the two therefore makes two genuinely identical
		// statements differ by one digit, which reads as a REQ-16 violation
		// and is nothing of the kind.
		//
		// The pair is retaken rather than having the timestamp normalized out
		// of it, because byte identity is exactly what the caller asserts: a
		// comparison that rewrote the strings first could no longer see a real
		// divergence in the part it rewrote.
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$opened = time();

			$with = $this->capture_clauses_for( $args );

			remove_filter( 'posts_clauses', array( Query::get_instance(), 'expand_event_clauses' ), 11 );

			$without = $this->capture_clauses_for( $args );

			add_filter( 'posts_clauses', array( Query::get_instance(), 'expand_event_clauses' ), 11, 2 );

			if ( time() === $opened ) {
				return array( $with, $without );
			}
		}

		$this->fail(
			'Failed to capture both clause sets inside one wall-clock second, so no byte comparison is meaningful.'
		);
	}

	/**
	 * Capture the final SQL a bucketed event query sends to the database.
	 *
	 * @since 0.36.0
	 *
	 * @param string $bucket Either `upcoming` or `past`.
	 * @param array  $args   Additional query arguments.
	 *
	 * @return string The SQL statement.
	 */
	protected function capture_request( string $bucket, array $args = array() ): string {
		$captured = '';
		$capture  = static function ( string $request ) use ( &$captured ): string {
			$captured = $request;

			return $request;
		};

		add_filter( 'posts_request', $capture, 10 );
		$this->run_event_query( $bucket, $args );
		remove_filter( 'posts_request', $capture, 10 );

		return $captured;
	}

	/**
	 * Coverage for the regression an inner join causes: it deletes every
	 * non-recurring event from every list.
	 *
	 * @covers ::expand_event_clauses
	 * @covers ::attach_occurrences
	 *
	 * @return void
	 */
	public function test_non_recurring_events_still_appear_when_recurring_events_exist(): void {
		$scenario = $this->build_scenario();

		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has recurring events.'
		);

		$upcoming = $this->entries( $this->run_event_query( 'upcoming' ) );

		$this->assertContains(
			$scenario['early'] . '|',
			$upcoming,
			'Failed to assert the non-recurring upcoming event survives the occurrence join.'
		);
		$this->assertContains(
			$scenario['mid'] . '|',
			$upcoming,
			'Failed to assert the second non-recurring upcoming event survives the occurrence join.'
		);

		$past = $this->entries( $this->run_event_query( 'past' ) );

		$this->assertContains(
			$scenario['past'] . '|',
			$past,
			'Failed to assert the non-recurring past event survives the occurrence join.'
		);
	}

	/**
	 * Coverage for a series contributing one list entry per scheduled occurrence.
	 *
	 * @covers ::expand_event_clauses
	 * @covers ::attach_occurrences
	 * @covers ::stamp_occurrence
	 *
	 * @return void
	 */
	public function test_upcoming_list_shows_four_occurrences_and_past_shows_one(): void {
		$scenario = $this->build_scenario();
		$series   = $scenario['series'];
		$anchor   = $scenario['anchor'];

		$upcoming = $this->entries( $this->run_event_query( 'upcoming' ) );

		$this->assertSame(
			array(
				$series . '|' . $this->occurrence_id( $anchor, 1 ),
				$series . '|' . $this->occurrence_id( $anchor, 2 ),
				$series . '|' . $this->occurrence_id( $anchor, 3 ),
				$series . '|' . $this->occurrence_id( $anchor, 4 ),
			),
			array_values(
				array_filter(
					$upcoming,
					static function ( string $entry ) use ( $series ): bool {
						return str_starts_with( $entry, $series . '|' );
					}
				)
			),
			'Failed to assert the series contributes its four upcoming occurrences.'
		);

		$past = $this->entries( $this->run_event_query( 'past' ) );

		$this->assertSame(
			array( $series . '|' . $this->occurrence_id( $anchor, 0 ) ),
			array_values(
				array_filter(
					$past,
					static function ( string $entry ) use ( $series ): bool {
						return str_starts_with( $entry, $series . '|' );
					}
				)
			),
			'Failed to assert the series contributes exactly its one past occurrence.'
		);
	}

	/**
	 * Coverage for the `COALESCE` ordering: occurrences and plain events interleave.
	 *
	 * @covers ::expand_event_clauses
	 * @covers ::coalesce_event_columns
	 *
	 * @return void
	 */
	public function test_recurring_and_non_recurring_interleave_by_date(): void {
		$scenario = $this->build_scenario();
		$series   = $scenario['series'];
		$anchor   = $scenario['anchor'];

		$this->assertSame(
			array(
				$scenario['early'] . '|',
				$series . '|' . $this->occurrence_id( $anchor, 1 ),
				$series . '|' . $this->occurrence_id( $anchor, 2 ),
				$scenario['mid'] . '|',
				$series . '|' . $this->occurrence_id( $anchor, 3 ),
				$series . '|' . $this->occurrence_id( $anchor, 4 ),
			),
			$this->entries( $this->run_event_query( 'upcoming' ) ),
			'Failed to assert occurrences and plain events order as one interleaved list.'
		);
	}

	/**
	 * Coverage for a canceled occurrence dropping out without the anchor row returning.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_canceled_occurrence_does_not_resurrect_the_series_anchor_row(): void {
		$scenario = $this->build_scenario();
		$series   = $scenario['series'];
		$anchor   = $scenario['anchor'];

		Occurrences::get_instance()->set_status(
			$series,
			$this->occurrence_id( $anchor, 2 ),
			Occurrences::STATUS_CANCELED
		);

		$upcoming = $this->entries( $this->run_event_query( 'upcoming' ) );

		$this->assertNotContains(
			$series . '|' . $this->occurrence_id( $anchor, 2 ),
			$upcoming,
			'Failed to assert the canceled occurrence is absent from the list.'
		);
		$this->assertSame(
			array(
				$series . '|' . $this->occurrence_id( $anchor, 1 ),
				$series . '|' . $this->occurrence_id( $anchor, 3 ),
				$series . '|' . $this->occurrence_id( $anchor, 4 ),
			),
			array_values(
				array_filter(
					$upcoming,
					static function ( string $entry ) use ( $series ): bool {
						return str_starts_with( $entry, $series . '|' );
					}
				)
			),
			'Failed to assert the series still contributes its three remaining occurrences.'
		);

		$this->assertNotContains(
			$series . '|',
			array_merge( $upcoming, $this->entries( $this->run_event_query( 'past' ) ) ),
			'Failed to assert the series anchor row did not resurrect as a plain event.'
		);
	}

	/**
	 * Coverage for the canceled-series guard: a fully canceled series is absent.
	 *
	 * Without the guard the `LEFT JOIN` matches nothing, the row falls through
	 * with `NULL` occurrence columns, `COALESCE` reaches the anchor date, and
	 * the canceled series reappears as an ordinary event.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_fully_canceled_series_is_absent_from_the_list(): void {
		$scenario = $this->build_scenario();
		$series   = $scenario['series'];
		$anchor   = $scenario['anchor'];

		for ( $index = 0; $index < 5; $index++ ) {
			Occurrences::get_instance()->set_status(
				$series,
				$this->occurrence_id( $anchor, $index ),
				Occurrences::STATUS_CANCELED
			);
		}

		$combined = array_merge(
			$this->entries( $this->run_event_query( 'upcoming' ) ),
			$this->entries( $this->run_event_query( 'past' ) )
		);

		$this->assertNotContains(
			$series . '|',
			$combined,
			'Failed to assert a fully canceled series does not reappear at its anchor date.'
		);
		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$combined,
					static function ( string $entry ) use ( $series ): bool {
						return str_starts_with( $entry, $series . '|' );
					}
				)
			),
			'Failed to assert a fully canceled series contributes no list entries at all.'
		);
	}

	/**
	 * Coverage for the requirement that `'fields' => 'ids'` is folded, not expanded.
	 *
	 * Two requirements at once, and the fixture separates them. The scenario's
	 * series is anchored an hour behind now with its remaining occurrences ahead
	 * of it, which is the steady state of any live series, so selecting the
	 * bucket from the anchor drops it out of the list entirely, which is what kept
	 * recurring series out of every aggregate iCal feed. And `WP_Query` returns
	 * before `the_posts` for an ids result set, so occurrence identity cannot
	 * travel with the rows: expanding would hand the caller a repeated bare post
	 * ID it has no way to disambiguate, which is what produced duplicate iCal
	 * VEVENTs sharing a UID.
	 *
	 * The ordering assertion is what tells the two apart. The series' next
	 * scheduled occurrence falls between the two plain upcoming events, so its
	 * position in the list is a value neither of the two failure modes can
	 * produce: selecting on the anchor would sort it first, and omitting it
	 * would leave it out.
	 *
	 * `get_events_list()` also sets `no_found_rows`, so this pins the
	 * no-pagination path: `found_posts` stays zero.
	 *
	 * @covers ::expand_event_clauses
	 * @covers ::fold_event_clauses
	 * @covers ::aggregate_orderby
	 *
	 * @return void
	 */
	public function test_get_events_list_is_folded_to_one_entry_per_series(): void {
		$scenario = $this->build_scenario();

		$request = '';
		$capture = static function ( string $sql ) use ( &$request ): string {
			$request = $sql;

			return $sql;
		};

		add_filter( 'posts_request', $capture );
		$query = Event_Query::get_instance()->get_events_list( 'upcoming', 20 );
		remove_filter( 'posts_request', $capture );

		$this->assertStringContainsString(
			Query::OCCURRENCE_ALIAS,
			$request,
			'Failed to assert an ids query consults the occurrence table for its bucket.'
		);
		$this->assertSame(
			array_fill( 0, count( $query->posts ), 'integer' ),
			array_map( 'gettype', $query->posts ),
			'Failed to assert the ids result set is still a list of integers.'
		);
		$this->assertSame(
			array( (int) $scenario['early'], (int) $scenario['series'], (int) $scenario['mid'] ),
			$query->posts,
			'Failed to assert the series appears once, ordered by its next occurrence rather than its anchor.'
		);
		$this->assertSame(
			$query->posts,
			array_values( array_unique( $query->posts ) ),
			'Failed to assert no post ID repeats in an ids result set.'
		);
		$this->assertSame(
			0,
			$query->found_posts,
			'Failed to assert found_posts stays zero when no_found_rows is set.'
		);
	}

	/**
	 * Coverage for the other compact field shape, `'fields' => 'id=>parent'`.
	 *
	 * `WP_Query` returns before `the_posts` for both compact shapes, so
	 * occurrence identity cannot travel with either. An expanded id=>parent
	 * result set is therefore a repeated, identity-less series ID that burns
	 * `posts_per_page`, and it diverges from what the same query returns as
	 * ids. Both compact shapes are folded instead: the occurrence join decides
	 * bucket membership, the grouping keeps one row per post, and no
	 * per-occurrence identity column is selected. The requirement is that both
	 * compact shapes return the same one-row-per-post list.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_id_parent_fields_query_is_never_expanded_over_occurrences(): void {
		$this->build_scenario();

		$ids = array_map( 'intval', $this->run_event_query( 'upcoming', array( 'fields' => 'ids' ) )->posts );

		$request = '';
		$capture = static function ( string $sql ) use ( &$request ): string {
			$request = $sql;

			return $sql;
		};

		add_filter( 'posts_request', $capture );
		$pairs_query = $this->run_event_query( 'upcoming', array( 'fields' => 'id=>parent' ) );
		remove_filter( 'posts_request', $capture );

		$pairs = array_map(
			static function ( $row ): int {
				return (int) $row->ID;
			},
			$pairs_query->posts
		);

		$this->assertStringNotContainsString(
			Query::SELECT_ALIAS,
			$request,
			'Failed to assert an id=>parent query selects no per-occurrence identity column.'
		);
		$this->assertSame(
			$ids,
			$pairs,
			'Failed to assert both compact field shapes return the same one-row-per-post list.'
		);
		$this->assertSame(
			$pairs,
			array_values( array_unique( $pairs ) ),
			'Failed to assert no post ID repeats in an id=>parent result set.'
		);
	}

	/**
	 * A fully canceled series stays out of a folded ids list too.
	 *
	 * The `NOT EXISTS` guard is what stops a series with rows but no *scheduled*
	 * rows falling through the join's `NULL` branch and reappearing at its
	 * anchor date. It is asserted for the expanded shape elsewhere; the folded
	 * shape carries it for the same reason and needs its own case, because
	 * dropping it there fails nothing else.
	 *
	 * The canceled series is anchored **ahead** of now, unlike the shared
	 * scenario's. That is the whole test: a series anchored behind now is kept
	 * out of the upcoming bucket by the range predicate whether or not the
	 * guard exists, so the two answers coincide and the guard could be deleted
	 * with the assertion still green. Only a fully canceled series
	 * whose anchor would otherwise qualify can tell them apart.
	 *
	 * @covers ::fold_event_clauses
	 * @covers ::occurrence_scope_predicate
	 *
	 * @return void
	 */
	public function test_a_fully_canceled_series_is_absent_from_a_folded_ids_list(): void {
		$now      = $this->now();
		$anchor   = $now->modify( '+6 hours' );
		$series   = $this->create_series_at( $anchor, $now->modify( '+7 hours' ) );
		$upcoming = $this->create_event_at( $now->modify( '+12 hours' ), $now->modify( '+13 hours' ) );

		$this->assertContains(
			(int) $series,
			Event_Query::get_instance()->get_events_list( 'upcoming', 20 )->posts,
			'Failed to assert the fixture series is in the bucket before anything is canceled.'
		);

		for ( $index = 0; $index < 5; $index++ ) {
			$this->assertTrue(
				Occurrences::get_instance()->set_status(
					$series,
					$this->occurrence_id( $anchor, $index ),
					Occurrences::STATUS_CANCELED
				),
				'Failed to cancel every occurrence; the assertion below would pass for the wrong reason.'
			);
		}

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series(
				array( $series ),
				array( 'status' => Occurrences::STATUS_SCHEDULED )
			),
			'Failed to assert the series has no scheduled occurrence rows left.'
		);
		$this->assertSame(
			array( (int) $upcoming ),
			Event_Query::get_instance()->get_events_list( 'upcoming', 20 )->posts,
			'Failed to assert a fully canceled series does not reappear at its anchor date in an ids list.'
		);
	}

	/**
	 * A folded ids query is totally ordered, so two series tied on their
	 * selected aggregate date cannot swap between pages.
	 *
	 * The two fixtures share an anchor to the second, so `MIN( COALESCE(...) )`
	 * returns the same value for both groups and the aggregate alone cannot
	 * separate them. MySQL's sort is not stable, which is what lets a tied pair
	 * come back one way for the first page and the other way for the second,
	 * putting one series on both pages of an aggregate feed and the other on
	 * neither.
	 *
	 * Asserts the emitted `ORDER BY` as well as the pages. Whether an untied
	 * sort *happens* to be consistent on a given plan is not something a test
	 * can pin down; that the clause names a unique column is.
	 *
	 * @covers ::fold_event_clauses
	 * @covers ::aggregate_orderby
	 *
	 * @return void
	 */
	public function test_folded_ids_pagination_is_stable_when_two_series_tie(): void {
		global $wpdb;

		$now    = $this->now();
		$anchor = $now->modify( '+1 hour' );
		$first  = $this->create_series_at( $anchor, $anchor->modify( '+1 hour' ) );
		$second = $this->create_series_at( $anchor, $anchor->modify( '+1 hour' ) );

		update_option( Query::HAS_RECURRING_OPTION, '1', true );

		$page_one = new WP_Query(
			$this->event_query_args(
				'upcoming',
				array(
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'paged'          => 1,
				)
			)
		);
		$page_two = new WP_Query(
			$this->event_query_args(
				'upcoming',
				array(
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'paged'          => 2,
				)
			)
		);

		$this->assertStringContainsString(
			', `' . $wpdb->posts . '`.ID ASC',
			(string) $page_one->request,
			'A folded ordering must name a column unique to the grouped row, or tied series have no order at all.'
		);
		$this->assertSame(
			array( min( $first, $second ) ),
			array_map( 'intval', $page_one->posts ),
			'The first page of a tied pair must be the one the tie-break puts first.'
		);
		$this->assertSame(
			array( max( $first, $second ) ),
			array_map( 'intval', $page_two->posts ),
			'The second page must be the other series, never a repeat of the first.'
		);
	}

	/**
	 * Direct coverage for `aggregate_orderby()`'s two paths.
	 *
	 * Xdebug does not trace a private helper reached through a same-class
	 * delegation, and the untouched path has no production caller today: every
	 * ordering `Event\Query` produces for a bucketed query references the
	 * datetime column. It is still the arm that keeps `RAND()` random and an
	 * `orderby` of `none` unordered, so it gets a case of its own.
	 *
	 * @covers ::aggregate_orderby
	 *
	 * @return void
	 */
	public function test_aggregate_orderby_direct_invoke_covers_both_paths(): void {
		global $wpdb;

		$instance = Query::get_instance();
		// Spelled out rather than built with `prepare()`, so the expectation
		// states the SQL the tie-break has to be rather than repeating the call
		// that produces it.
		$tie_break = ', `' . $wpdb->posts . '`.ID ASC';

		$this->assertSame(
			'MIN( COALESCE( o.datetime_start_gmt, e.datetime_start_gmt ) ) ASC' . $tie_break,
			Utility::invoke_hidden_method(
				$instance,
				'aggregate_orderby',
				array( 'COALESCE( o.datetime_start_gmt, e.datetime_start_gmt ) ASC' )
			),
			'An ascending sort takes the earliest occurrence in the group.'
		);
		$this->assertSame(
			'MAX( COALESCE( o.datetime_start_gmt, e.datetime_start_gmt ) ) DESC' . $tie_break,
			Utility::invoke_hidden_method(
				$instance,
				'aggregate_orderby',
				array( 'COALESCE( o.datetime_start_gmt, e.datetime_start_gmt ) DESC' )
			),
			'A descending sort takes the latest, which is what the past bucket means.'
		);
		$this->assertSame(
			'RAND()',
			Utility::invoke_hidden_method( $instance, 'aggregate_orderby', array( 'RAND()' ) ),
			'An ordering that does not reference the occurrence table is left alone.'
		);
		$this->assertSame(
			'',
			Utility::invoke_hidden_method( $instance, 'aggregate_orderby', array( '' ) ),
			'An unordered query must not acquire an ORDER BY it never had.'
		);
	}

	/**
	 * A second sort key is aggregated around, never swallowed into the wrap.
	 *
	 * Any plugin can append a tie-breaker through `posts_orderby`, and a future
	 * `Event\Query` change can add one of its own. Wrapping the whole clause
	 * in one aggregate turns that into `MIN( <key> ASC, <key> )`, a syntax
	 * error that empties every aggregate feed on the site. Only the keys the
	 * fold rewrote reference the occurrence rows; the others are functionally
	 * dependent on the group key and pass through as they are, which is also
	 * what keeps the clause valid under `ONLY_FULL_GROUP_BY`.
	 *
	 * @covers ::aggregate_orderby
	 *
	 * @return void
	 */
	public function test_aggregate_orderby_wraps_each_key_not_the_clause(): void {
		global $wpdb;

		$this->assertSame(
			'MIN( COALESCE( o.datetime_start_gmt, e.datetime_start_gmt ) ) ASC, '
				. $wpdb->posts . '.post_title ASC, `' . $wpdb->posts . '`.ID ASC',
			Utility::invoke_hidden_method(
				Query::get_instance(),
				'aggregate_orderby',
				array(
					'COALESCE( o.datetime_start_gmt, e.datetime_start_gmt ) ASC, '
						. $wpdb->posts . '.post_title ASC',
				)
			),
			'The occurrence key takes the aggregate; a tie-breaker survives beside it rather than inside it.'
		);
	}

	/**
	 * Direct coverage for the per-key ordering helpers.
	 *
	 * Invoked directly because xdebug does not trace private helpers reached
	 * through a same-class call, and each branch needs a case of its own.
	 *
	 * @covers ::aggregate_orderby_key
	 * @covers ::split_orderby
	 *
	 * @return void
	 */
	public function test_the_ordering_helpers_split_and_wrap_key_by_key(): void {
		$instance = Query::get_instance();

		$this->assertSame(
			array( 'COALESCE( a, b ) ASC', ' t.c DESC' ),
			Utility::invoke_hidden_method(
				$instance,
				'split_orderby',
				array( 'COALESCE( a, b ) ASC, t.c DESC' )
			),
			'Commas inside a function call separate arguments, not keys.'
		);
		$this->assertSame(
			array( 't.c ASC' ),
			Utility::invoke_hidden_method( $instance, 'split_orderby', array( 't.c ASC' ) ),
			'A single key is one key.'
		);
		$this->assertSame(
			array( 'a )', ' b' ),
			Utility::invoke_hidden_method( $instance, 'split_orderby', array( 'a ), b' ) ),
			'An unbalanced closing parenthesis cannot drive the depth negative and swallow the split.'
		);
		$this->assertSame(
			'MAX( COALESCE( a, b ) ) DESC',
			Utility::invoke_hidden_method(
				$instance,
				'aggregate_orderby_key',
				array( 'COALESCE( a, b ) DESC' )
			),
			'A descending occurrence key takes the greatest value in the group.'
		);
		$this->assertSame(
			't.c DESC',
			Utility::invoke_hidden_method( $instance, 'aggregate_orderby_key', array( ' t.c DESC ' ) ),
			'A key without the fold marker passes through, trimmed.'
		);
	}

	/**
	 * A plugin tie-breaker degrades the ordering at worst, never the results.
	 *
	 * The production path for the case above. `Event\Query` rewrites the
	 * ordering on `posts_clauses` at priority 10, registered from inside
	 * `pre_get_posts`, and the fold runs at 11. An integration adjusting
	 * clauses per query registers the same way, so its filter lands after the
	 * rewrite and before the fold, and the fold's ordering rewrite receives a
	 * clause with a second key. A plain `posts_orderby` filter cannot get
	 * there: the event rewrite overwrites that clause outright.
	 *
	 * @covers ::fold_event_clauses
	 * @covers ::aggregate_orderby
	 *
	 * @return void
	 */
	public function test_a_plugin_tie_breaker_survives_the_folded_query(): void {
		global $wpdb;

		$scenario = $this->build_scenario();

		$tie_breaker = static function ( array $pieces ) use ( $wpdb ): array {
			if ( ! empty( $pieces['orderby'] ) ) {
				$pieces['orderby'] .= ', ' . $wpdb->posts . '.post_title ASC';
			}

			return $pieces;
		};
		$register    = static function () use ( $tie_breaker ): void {
			add_filter( 'posts_clauses', $tie_breaker );
		};

		add_action( 'pre_get_posts', $register, 20 );

		$suppressed = $wpdb->suppress_errors();
		$query      = Event_Query::get_instance()->get_events_list( 'upcoming', 20 );

		$wpdb->suppress_errors( $suppressed );
		remove_action( 'pre_get_posts', $register, 20 );
		remove_filter( 'posts_clauses', $tie_breaker );

		$this->assertSame(
			'',
			(string) $wpdb->last_error,
			'The emitted ordering must be valid SQL.'
		);
		$this->assertContains(
			(int) $scenario['series'],
			array_map( 'intval', $query->posts ),
			'A second sort key must not empty the aggregate feeds of every series.'
		);
	}

	/**
	 * Coverage for the iCal feed emitting one VEVENT per event, with unique UIDs.
	 *
	 * `Calendar\Setup::get_ical_list()` is the one production consumer of
	 * `get_events_list()`. It loops `have_posts()` and builds each VEVENT from
	 * `new Calendar( get_the_ID() )`, which reads the post's *anchor* datetime,
	 * so an occurrence-expanded ids list emits the same VEVENT repeatedly under
	 * one UID. RFC 5545 requires UID uniqueness within a VCALENDAR.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_ical_feed_emits_one_vevent_per_event_with_distinct_uids(): void {
		$now = $this->now();

		$series = $this->create_series_at( $now->modify( '+2 hours' ), $now->modify( '+3 hours' ) );
		$single = $this->create_event_at( $now->modify( '+30 hours' ), $now->modify( '+31 hours' ) );

		$this->assertCount(
			5,
			Occurrences::get_instance()->select_for_series( array( $series ) ),
			'Failed to assert the series projected five occurrence rows.'
		);

		$ical = Calendar_Setup::get_instance()->get_ical_list();

		$this->assertSame(
			2,
			substr_count( $ical, 'BEGIN:VEVENT' ),
			'Failed to assert the feed emits one VEVENT per event rather than one per occurrence.'
		);

		preg_match_all( '/^UID:(.*)$/m', $ical, $matches );

		$uids = array_map( 'trim', $matches[1] );

		sort( $uids );

		$expected = array( 'gatherpress_' . $series, 'gatherpress_' . $single );

		sort( $expected );

		$this->assertSame(
			$expected,
			$uids,
			'Failed to assert every VEVENT carries a distinct UID, as RFC 5545 requires.'
		);
	}

	/**
	 * Coverage for the admin post list staying one row per post.
	 *
	 * `edit.php` rows carry Edit/Trash/View actions and bulk-action checkboxes
	 * keyed by post ID, so an occurrence-expanded admin list would show one
	 * indistinguishable row per occurrence and make a bulk action on "a row"
	 * act on the whole series.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_admin_event_list_shows_one_row_per_post(): void {
		$scenario = $this->build_scenario();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		$this->assertTrue( is_admin(), 'Failed to assert the admin screen context was set.' );

		// The admin "All" view, which is what `edit.php` runs by default:
		// `Event\Query::adjust_admin_event_sorting()` joins the events table at
		// priority 9 with no date predicate, so every dated event is listed.
		$query = new WP_Query(
			array(
				'post_type'      => Event::POST_TYPE,
				'posts_per_page' => 20,
				'orderby'        => 'datetime',
				'order'          => 'ASC',
			)
		);

		$ids = wp_list_pluck( $query->posts, 'ID' );

		set_current_screen( 'front' );

		$this->assertSame(
			$ids,
			array_values( array_unique( $ids ) ),
			'Failed to assert the admin event list shows each post exactly once.'
		);
		$this->assertContains(
			(int) $scenario['series'],
			$ids,
			'Failed to assert the series is still present in the admin event list.'
		);
	}

	/**
	 * Coverage for an admin-ajax front-end read staying expanded.
	 *
	 * `admin-ajax.php` serves front-end requests, including logged-out ones,
	 * and `is_admin()` is true for every one of them. A theme lazy-loading an
	 * upcoming list over admin-ajax must receive the same expanded list a
	 * page load renders, not one entry per series at its anchor date. The
	 * admin exemption exists for `edit.php` rows, whose actions and bulk
	 * checkboxes an ajax read does not have.
	 *
	 * The fixture satisfies every other guard arm, so the admin arm is the
	 * only one that can act: the site has recurring events, the fields are
	 * the full shape, the bucketed query joins the events table, and the
	 * occurrence table exists.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_admin_ajax_event_query_is_still_expanded(): void {
		$scenario = $this->build_scenario();

		set_current_screen( 'edit-' . Event::POST_TYPE );
		add_filter( 'wp_doing_ajax', '__return_true' );

		$this->assertTrue( is_admin(), 'Fixture is inert: the admin screen context was not set.' );
		$this->assertTrue( wp_doing_ajax(), 'Fixture is inert: the ajax context was not set.' );

		$entries = $this->entries( $this->run_event_query( 'upcoming' ) );

		remove_filter( 'wp_doing_ajax', '__return_true' );
		set_current_screen( 'front' );

		foreach ( array( 1, 2, 3, 4 ) as $index ) {
			$this->assertContains(
				$scenario['series'] . '|' . $this->occurrence_id( $scenario['anchor'], $index ),
				$entries,
				'Failed to assert an admin-ajax upcoming query is expanded over occurrences.'
			);
		}
	}

	/**
	 * Coverage for a recurring event whose rule has produced no occurrence rows.
	 *
	 * A rule exists before its rows do, and a rule can legitimately produce
	 * none. Keying the `NULL`-fallback guard on the rule mirror rather than on
	 * the occurrence rows makes such a post match no join row *and* be denied
	 * the fallback, so a published event with a valid title and date vanishes
	 * from every list. Showing it at its anchor date is the required behavior.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_recurring_event_with_no_projected_rows_still_appears(): void {
		$now    = $this->now();
		$anchor = $now->modify( '+6 hours' );

		$series = $this->create_series_at( $anchor, $now->modify( '+7 hours' ) );

		// Clear the projected rows while the rule and its mirrors stay in
		// place. That is the state between a rule being saved and its
		// occurrences being projected, and the state a rule that yields nothing
		// leaves behind permanently.
		Occurrences::get_instance()->delete_for_post( $series );

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $series ) ),
			'Failed to assert the series has no occurrence rows.'
		);
		$this->assertSame(
			'daily',
			get_post_meta( $series, Query::FREQUENCY_META_KEY, true ),
			'Failed to assert the rule mirror is still present.'
		);
		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the site still reports recurring events.'
		);

		$this->assertSame(
			array( $series . '|' ),
			$this->entries( $this->run_event_query( 'upcoming' ) ),
			'Failed to assert a recurring event with no occurrence rows still appears at its anchor date.'
		);
	}

	/**
	 * Coverage for the `tax_query` duplicate-row problem.
	 *
	 * An event matching two selected terms yields two joined rows, so WordPress
	 * groups on the post ID. Collapsing on the post ID alone would collapse
	 * every occurrence of a series into one entry, so the group has to widen to
	 * the `(post_id, recurrence_id)` tuple: the series must keep all four
	 * occurrences while the doubly-matched plain event still appears once.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_tax_query_duplicates_collapse_on_the_occurrence_tuple(): void {
		$scenario = $this->build_scenario();
		$series   = $scenario['series'];
		$anchor   = $scenario['anchor'];

		wp_set_object_terms( $series, array( 'alpha', 'beta' ), Topic::TAXONOMY );
		wp_set_object_terms( (int) $scenario['early'], array( 'alpha', 'beta' ), Topic::TAXONOMY );

		$query = $this->run_event_query(
			'upcoming',
			array(
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Topic::TAXONOMY,
						'field'    => 'slug',
						'terms'    => array( 'alpha', 'beta' ),
					),
				),
			)
		);

		$this->assertSame(
			array(
				$scenario['early'] . '|',
				$series . '|' . $this->occurrence_id( $anchor, 1 ),
				$series . '|' . $this->occurrence_id( $anchor, 2 ),
				$series . '|' . $this->occurrence_id( $anchor, 3 ),
				$series . '|' . $this->occurrence_id( $anchor, 4 ),
			),
			$this->entries( $query ),
			'Failed to assert term duplicates collapse on the occurrence tuple, not on the post ID.'
		);
		$this->assertSame(
			5,
			$query->found_posts,
			'Failed to assert found_posts counts the de-duplicated joined rows.'
		);
	}

	/**
	 * Coverage for pagination over an occurrence-expanded list.
	 *
	 * @covers ::expand_event_clauses
	 * @covers ::attach_occurrences
	 *
	 * @return void
	 */
	public function test_limit_and_pagination_do_not_repeat_the_series(): void {
		$scenario = $this->build_scenario();

		$first  = $this->run_event_query( 'upcoming', array( 'posts_per_page' => 2 ) );
		$second = $this->run_event_query(
			'upcoming',
			array(
				'posts_per_page' => 2,
				'paged'          => 2,
			)
		);
		$third  = $this->run_event_query(
			'upcoming',
			array(
				'posts_per_page' => 2,
				'paged'          => 3,
			)
		);

		$pages = array( $this->entries( $first ), $this->entries( $second ), $this->entries( $third ) );
		$all   = array_merge( ...$pages );

		$this->assertSame(
			$all,
			array_values( array_unique( $all ) ),
			'Failed to assert no occurrence repeats across pages.'
		);
		$this->assertCount(
			6,
			$all,
			'Failed to assert three pages of two cover every list entry exactly once.'
		);
		$this->assertSame(
			6,
			$first->found_posts,
			'Failed to assert found_posts counts joined rows rather than distinct posts.'
		);
		$this->assertSame(
			3,
			$first->max_num_pages,
			'Failed to assert pagination is computed from the joined-row count.'
		);
	}

	/**
	 * Coverage for the deterministic tiebreaker an expanded ordering needs.
	 *
	 * Two occurrences of different series routinely share a start datetime, and
	 * the ordering column alone cannot separate them. MySQL's sort is not
	 * stable, so a tied pair can be ordered one way for `LIMIT 0, 10` and the
	 * other for `LIMIT 10, 10`, which puts one entry on two pages and the other
	 * on none. The canonical `(post_id, recurrence_id)` list key is what makes
	 * the ordering total.
	 *
	 * `Event\Query`'s datetime arm supplies the post ID half of that key
	 * itself, because a site with no recurring events never reaches this
	 * method and needs a total ordering just as much. Expansion then adds only
	 * the `recurrence_id`, rather than a second post ID key that could not
	 * change the order and would contradict the first one on a descending
	 * list.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_expanded_ordering_is_total(): void {
		global $wpdb;

		$this->build_scenario();

		$request  = $this->capture_request( 'upcoming' );
		$order_by = trim( substr( $request, (int) strrpos( $request, 'ORDER BY' ) ) );

		$this->assertStringStartsWith(
			sprintf(
				'ORDER BY COALESCE( %s.datetime_start_gmt,',
				Query::OCCURRENCE_ALIAS
			),
			$order_by,
			'Failed to assert the expanded ordering leads with the effective occurrence start.'
		);
		$this->assertStringContainsString(
			sprintf(
				') ASC, %s.ID ASC, `%s`.recurrence_id ASC',
				$wpdb->posts,
				Query::OCCURRENCE_ALIAS
			),
			$order_by,
			'Failed to assert the occurrence list key follows the effective start as the tiebreaker.'
		);
		$this->assertSame(
			1,
			substr_count( $order_by, $wpdb->posts . '.ID' ),
			'Failed to assert expansion adds no second post ID key behind the one already there.'
		);
	}

	/**
	 * Coverage for the other arm: an ordering with no post ID key gets one.
	 *
	 * Only `Event\Query`'s `datetime` and `id` arms emit the posts-table ID.
	 * Ordering by title leaves two events sharing a title separated by
	 * nothing, so expansion has to supply the full `(post_id, recurrence_id)`
	 * list key rather than the `recurrence_id` alone.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_expansion_supplies_the_post_id_key_when_the_ordering_lacks_one(): void {
		global $wpdb;

		$this->build_scenario();

		$request  = $this->capture_request( 'upcoming', array( 'orderby' => 'title' ) );
		$order_by = trim( substr( $request, (int) strrpos( $request, 'ORDER BY' ) ) );

		$this->assertStringContainsString(
			sprintf(
				'.post_name ASC, `%s`.ID ASC, `%s`.recurrence_id ASC',
				$wpdb->posts,
				Query::OCCURRENCE_ALIAS
			),
			$order_by,
			'Failed to assert a title ordering acquires the whole occurrence list key.'
		);
	}

	/**
	 * Coverage for the other arm: an unordered query stays unordered.
	 *
	 * `'orderby' => 'none'` asks for no sort at all, and appending a tiebreaker
	 * to an empty clause would hand it an `ORDER BY` it never had, and a
	 * filesort with it.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_expansion_does_not_order_a_query_that_asked_for_no_order(): void {
		$this->build_scenario();

		$request = $this->capture_request( 'upcoming', array( 'orderby' => 'none' ) );

		$this->assertStringContainsString(
			Query::OCCURRENCE_ALIAS,
			$request,
			'Failed to assert the unordered query was still occurrence-expanded.'
		);
		$this->assertStringNotContainsString(
			'ORDER BY',
			$request,
			'Failed to assert an unordered query acquires no ORDER BY from expansion.'
		);
	}

	/**
	 * Coverage for the documented exclusion of dateless events from both buckets.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_event_with_no_date_is_excluded_from_both_buckets(): void {
		$scenario = $this->build_scenario();

		$combined = array_merge(
			$this->entries( $this->run_event_query( 'upcoming' ) ),
			$this->entries( $this->run_event_query( 'past' ) )
		);

		$this->assertNotContains(
			$scenario['undated'] . '|',
			$combined,
			'Failed to assert an event with no datetime row stays out of both buckets.'
		);
	}

	/**
	 * Coverage for the measured query plan.
	 *
	 * The `COALESCE` ordering is not sargable, so a filesort is expected and
	 * accepted, and the plan reports a temporary table with it. Both are
	 * asserted because both are what the class docblocks quote as the cost:
	 * the same list unexpanded reports the filesort alone, so the temporary
	 * table is the part expansion adds and the part a claim of "will filesort"
	 * was leaving out. The discriminating assertion is the last one: the
	 * occurrence join must stay index-served, which a row-count budget does not
	 * detect at fixture scale.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_explain_plan_filesorts_and_keeps_the_occurrence_join_indexed(): void {
		global $wpdb;

		$this->build_scenario();

		$request = $this->capture_request( 'upcoming' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- EXPLAIN of a query WordPress already prepared.
		$plan = $wpdb->get_results( 'EXPLAIN ' . $request, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$extra = implode( ' ', array_column( $plan, 'Extra' ) );

		$this->assertStringContainsString(
			'Using filesort',
			$extra,
			'Failed to assert the accepted filesort is present in the query plan.'
		);
		$this->assertStringContainsString(
			'Using temporary',
			$extra,
			'Failed to assert the temporary table the expansion adds is present in the query plan, which is'
				. ' what the documented cost quotes.'
		);

		// The filesort is the price of COALESCE ordering; the occurrence join
		// itself must still be index-served, which is what keeps rows examined
		// proportional to the occurrences a series actually has.
		$occurrence_plan = array_values(
			array_filter(
				$plan,
				static function ( array $row ): bool {
					return Query::OCCURRENCE_ALIAS === $row['table'];
				}
			)
		);

		$this->assertCount( 1, $occurrence_plan, 'Failed to assert the occurrence table is in the query plan.' );
		$this->assertNotNull(
			$occurrence_plan[0]['key'],
			'Failed to assert the occurrence join is served by an index rather than a table scan.'
		);
		$this->assertNotSame(
			'ALL',
			$occurrence_plan[0]['type'],
			'Failed to assert the occurrence join avoids a full table scan.'
		);
	}

	/**
	 * Coverage for `attach_occurrences` leaving a non-event query untouched.
	 *
	 * @covers ::attach_occurrences
	 * @covers ::is_event_query
	 *
	 * @return void
	 */
	public function test_attach_occurrences_leaves_non_event_queries_untouched(): void {
		$this->build_scenario();

		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$query   = new WP_Query( array( 'post_type' => 'post' ) );

		$this->assertSame(
			array( $post_id ),
			wp_list_pluck( $query->posts, 'ID' ),
			'Failed to assert the plain post query returned its post.'
		);
		$this->assertFalse(
			property_exists( $query->posts[0], 'gatherpress_recurrence_id' ),
			'Failed to assert a non-event query result carries no occurrence identity.'
		);
	}

	/**
	 * Coverage for `attach_occurrences` short-circuiting on a site with no recurring events.
	 *
	 * @covers ::attach_occurrences
	 *
	 * @return void
	 */
	public function test_attach_occurrences_leaves_results_untouched_without_recurring_events(): void {
		$now = $this->now();

		$this->create_event_at( $now->modify( '+2 hours' ), $now->modify( '+3 hours' ) );

		$query = $this->run_event_query( 'upcoming' );

		$this->assertCount( 1, $query->posts, 'Failed to assert the single event was returned.' );
		$this->assertFalse(
			property_exists( $query->posts[0], 'gatherpress_recurrence_id' ),
			'Failed to assert no occurrence identity is stamped without recurring events.'
		);
	}

	/**
	 * Coverage for a non-recurring event carrying a null occurrence identity.
	 *
	 * @covers ::attach_occurrences
	 * @covers ::stamp_occurrence
	 *
	 * @return void
	 */
	public function test_non_recurring_event_is_stamped_with_a_null_recurrence_id(): void {
		$scenario = $this->build_scenario();

		$query = $this->run_event_query( 'upcoming' );
		$posts = array_values(
			array_filter(
				$query->posts,
				static function ( WP_Post $post ) use ( $scenario ): bool {
					return (int) $post->ID === (int) $scenario['early'];
				}
			)
		);

		$this->assertCount( 1, $posts, 'Failed to assert the non-recurring event is in the result set.' );
		$this->assertNull(
			$posts[0]->gatherpress_recurrence_id,
			'Failed to assert a non-recurring event carries a null recurrence identifier.'
		);
	}

	/**
	 * Coverage for identity traveling on clones rather than on the objects WordPress built.
	 *
	 * Mutating the objects core created would leave `$unfiltered_posts` holding
	 * recurrence-stamped posts, which is what the "do not poison the post
	 * cache" rule guards against.
	 *
	 * @covers ::attach_occurrences
	 *
	 * @return void
	 */
	public function test_attach_occurrences_returns_clones_rather_than_mutating_core_objects(): void {
		$this->build_scenario();

		$originals = array();
		$capture   = static function ( array $posts ) use ( &$originals ): array {
			$originals = $posts;

			return $posts;
		};

		add_filter( 'the_posts', $capture, 9 );
		$query = $this->run_event_query( 'upcoming' );
		remove_filter( 'the_posts', $capture, 9 );

		$this->assertNotEmpty( $originals, 'Failed to assert the pre-filter results were captured.' );

		foreach ( $originals as $original ) {
			$this->assertFalse(
				property_exists( $original, 'gatherpress_recurrence_id' ),
				'Failed to assert the objects WordPress built were not mutated in place.'
			);
		}
	}

	/**
	 * Coverage for `stamp_occurrence` publishing a recurrence identifier.
	 *
	 * @covers ::stamp_occurrence
	 *
	 * @return void
	 */
	public function test_stamp_occurrence_publishes_the_select_alias(): void {
		$post = new WP_Post( (object) array( 'ID' => 1 ) );

		$post->gatherpress_occurrence_recurrence_id = '20260903T180000';

		$stamped = Utility::invoke_hidden_method( Query::get_instance(), 'stamp_occurrence', array( $post ) );

		$this->assertSame(
			'20260903T180000',
			$stamped->gatherpress_recurrence_id,
			'Failed to assert the select alias is published as the recurrence identifier.'
		);
		$this->assertFalse(
			property_exists( $stamped, 'gatherpress_occurrence_recurrence_id' ),
			'Failed to assert the raw select alias is removed from the published object.'
		);
	}

	/**
	 * Coverage for `stamp_occurrence` on a row with no occurrence.
	 *
	 * @covers ::stamp_occurrence
	 *
	 * @return void
	 */
	public function test_stamp_occurrence_publishes_null_when_the_alias_is_absent(): void {
		$post = new WP_Post( (object) array( 'ID' => 1 ) );

		$stamped = Utility::invoke_hidden_method( Query::get_instance(), 'stamp_occurrence', array( $post ) );

		$this->assertNull(
			$stamped->gatherpress_recurrence_id,
			'Failed to assert an absent select alias publishes as null.'
		);
	}

	/**
	 * Coverage for `is_event_query` recognizing a supported post type.
	 *
	 * @covers ::is_event_query
	 *
	 * @return void
	 */
	public function test_is_event_query_accepts_a_supported_post_type(): void {
		$this->assertTrue(
			Utility::invoke_hidden_method(
				Query::get_instance(),
				'is_event_query',
				array( new WP_Query( array( 'post_type' => Event::POST_TYPE ) ) )
			),
			'Failed to assert an event query is recognized.'
		);
	}

	/**
	 * Coverage for `is_event_query` rejecting an unsupported post type.
	 *
	 * @covers ::is_event_query
	 *
	 * @return void
	 */
	public function test_is_event_query_rejects_an_unsupported_post_type(): void {
		$this->assertFalse(
			Utility::invoke_hidden_method(
				Query::get_instance(),
				'is_event_query',
				array( new WP_Query( array( 'post_type' => 'post' ) ) )
			),
			'Failed to assert a plain post query is not an event query.'
		);
	}

	/**
	 * Coverage for `coalesce_event_columns` rewriting both renderings of the anchor columns.
	 *
	 * `Event\Query` writes the ORDER BY column unquoted and the WHERE column
	 * back-quoted through `$wpdb->prepare()`'s `%i` placeholder, so both forms
	 * have to be rewritten.
	 *
	 * @covers ::coalesce_event_columns
	 *
	 * @return void
	 */
	public function test_coalesce_event_columns_rewrites_both_renderings(): void {
		$rewritten = Utility::invoke_hidden_method(
			Query::get_instance(),
			'coalesce_event_columns',
			array(
				'wp_gatherpress_events.datetime_start_gmt ASC'
				. " AND `wp_gatherpress_events`.`datetime_end_gmt` >= '2026-01-01 00:00:00'",
				'wp_gatherpress_events',
				Query::OCCURRENCE_ALIAS,
			)
		);

		$this->assertSame(
			'COALESCE( gatherpress_occurrence.datetime_start_gmt, wp_gatherpress_events.datetime_start_gmt ) ASC'
			. ' AND COALESCE( gatherpress_occurrence.datetime_end_gmt, wp_gatherpress_events.datetime_end_gmt )'
			. " >= '2026-01-01 00:00:00'",
			$rewritten,
			'Failed to assert both the unquoted and back-quoted column renderings are wrapped in COALESCE.'
		);
	}

	/**
	 * Coverage for `coalesce_event_columns` leaving an unrelated clause alone.
	 *
	 * @covers ::coalesce_event_columns
	 *
	 * @return void
	 */
	public function test_coalesce_event_columns_leaves_unrelated_clauses_alone(): void {
		$this->assertSame(
			'wp_posts.post_title ASC',
			Utility::invoke_hidden_method(
				Query::get_instance(),
				'coalesce_event_columns',
				array( 'wp_posts.post_title ASC', 'wp_gatherpress_events', Query::OCCURRENCE_ALIAS )
			),
			'Failed to assert a clause with no anchor columns is returned unchanged.'
		);
	}

	/**
	 * Coverage for `coalesce_event_columns` naming whichever relation it is given.
	 *
	 * The alias is a parameter because two callers rewrite the same clauses
	 * against two different relations: the row-for-row occurrence join and the
	 * one-row-per-post admin display relation. A hard-coded alias would send
	 * the admin list's ordering and bucketing at a table that is not joined
	 * there.
	 *
	 * @covers ::coalesce_event_columns
	 *
	 * @return void
	 */
	public function test_coalesce_event_columns_uses_the_alias_it_is_given(): void {
		$this->assertSame(
			sprintf(
				'COALESCE( %1$s.datetime_start_gmt, wp_gatherpress_events.datetime_start_gmt ) ASC',
				Query::ADMIN_SORT_ALIAS
			),
			Utility::invoke_hidden_method(
				Query::get_instance(),
				'coalesce_event_columns',
				array(
					'wp_gatherpress_events.datetime_start_gmt ASC',
					'wp_gatherpress_events',
					Query::ADMIN_SORT_ALIAS,
				)
			),
			'Failed to assert the rewrite names the relation alias the caller supplied.'
		);
	}

	/**
	 * Coverage for both answers of `carries_anchor_datetime`.
	 *
	 * The admin list only needs the occurrence relation when a clause actually
	 * reads an anchor datetime column. The `where` arm is the one B3 turns on:
	 * an Upcoming or Past view sorted by title carries no anchor `orderby` at
	 * all, and bucketing it on the anchor is exactly the defect.
	 *
	 * @covers ::carries_anchor_datetime
	 *
	 * @return void
	 */
	public function test_carries_anchor_datetime_reads_both_clauses(): void {
		$instance = Query::get_instance();

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$instance,
				'carries_anchor_datetime',
				array(
					array( 'orderby' => 'wp_gatherpress_events.datetime_start_gmt ASC' ),
					'wp_gatherpress_events',
				)
			),
			'Failed to assert a date-ordered clause is recognized.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method(
				$instance,
				'carries_anchor_datetime',
				array(
					array( 'where' => " AND `wp_gatherpress_events`.`datetime_end_gmt` >= '2026-01-01 00:00:00'" ),
					'wp_gatherpress_events',
				)
			),
			'Failed to assert a bucket predicate is recognized on its own.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'carries_anchor_datetime',
				array(
					array(
						'orderby' => 'wp_posts.post_title ASC',
						'where'   => " AND wp_posts.post_status = 'publish'",
					),
					'wp_gatherpress_events',
				)
			),
			'Failed to assert clauses reading no anchor datetime are left alone.'
		);
	}

	/**
	 * Build the fixture the admin sorting tests share.
	 *
	 * Every entry is placed so that ordering by the series **anchor** and
	 * ordering by the occurrence the list is showing disagree, in two
	 * independent ways. Without that separation the test would pass against the
	 * untouched code and prove nothing.
	 *
	 * `running` is anchored ten days ago and still going, so its anchor sorts
	 * it near the bottom of the past while its next occurrence, five hours from
	 * now, belongs between two upcoming one-off events.
	 *
	 * `elapsed` and `way_past` swap places between the two orderings on their
	 * own: the series anchor is thirty days ago, earlier than `way_past`, while
	 * its last occurrence is twenty-six days ago, later than `way_past`.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, int> Post IDs keyed by role.
	 */
	protected function build_admin_sort_fixture(): array {
		$now = $this->now();

		return array(
			'elapsed'  => $this->create_series_at(
				$now->modify( '-30 days' ),
				$now->modify( '-30 days +1 hour' ),
				self::DAILY_RULE
			),
			'way_past' => $this->create_event_at(
				$now->modify( '-28 days' ),
				$now->modify( '-28 days +1 hour' )
			),
			'running'  => $this->create_series_at(
				$now->modify( '-10 days +5 hours' ),
				$now->modify( '-10 days +6 hours' ),
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => 20,
				)
			),
			'before'   => $this->create_event_at(
				$now->modify( '+1 hour' ),
				$now->modify( '+2 hours' )
			),
			'after'    => $this->create_event_at(
				$now->modify( '+1 day' ),
				$now->modify( '+1 day +1 hour' )
			),
		);
	}

	/**
	 * Run a date-ordered admin event list over a fixed set of posts.
	 *
	 * Drives a real `WP_Query` on a real `edit.php` screen, so the production
	 * `posts_clauses` chain runs in the order production runs it.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, int> $fixture Post IDs keyed by role.
	 * @param string             $orderby Value of the `orderby` query argument.
	 *
	 * @return int[] The result IDs, in query order.
	 */
	protected function run_admin_list_query( array $fixture, string $orderby = 'datetime' ): array {
		set_current_screen( 'edit-' . Event::POST_TYPE );

		$query = new WP_Query(
			array(
				'post_type'      => Event::POST_TYPE,
				'post_status'    => 'publish',
				'post__in'       => array_values( $fixture ),
				'orderby'        => $orderby,
				'order'          => 'ASC',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		set_current_screen( 'front' );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Run a bucketed, date-ordered admin event list over a fixed set of posts.
	 *
	 * The same production wiring as `run_admin_list_query()`, plus the
	 * Upcoming/Past view parameter the admin view links carry, so the bucket
	 * predicate `Event\Query::adjust_admin_event_sorting()` appends is part of
	 * the exercised path.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, int> $fixture Post IDs keyed by role.
	 * @param string             $bucket  Either `upcoming` or `past`.
	 * @param string             $orderby Value of the `orderby` query argument.
	 *
	 * @return int[] The result IDs, in query order.
	 */
	protected function run_admin_bucket_query( array $fixture, string $bucket, string $orderby = 'datetime' ): array {
		set_current_screen( 'edit-' . Event::POST_TYPE );

		$query = new WP_Query(
			array(
				'post_type'                    => Event::POST_TYPE,
				'post_status'                  => 'publish',
				'post__in'                     => array_values( $fixture ),
				Event_Query::EVENT_QUERY_PARAM => $bucket,
				'orderby'                      => $orderby,
				'order'                        => 'upcoming' === $bucket ? 'ASC' : 'DESC',
				'posts_per_page'               => -1,
				'fields'                       => 'ids',
				'no_found_rows'                => true,
			)
		);

		set_current_screen( 'front' );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * The Upcoming and Past views classify a series by its shown occurrence.
	 *
	 * The date column and the sort already use the chosen occurrence, so a
	 * bucket predicate still reading the series anchor files a running series
	 * under Past while its row displays a date hours away: the row's displayed
	 * date would contradict the filter that selected it. The `running` series'
	 * anchor elapsed ten days ago while its next occurrence is five hours out,
	 * so anchor bucketing and occurrence bucketing provably disagree on it,
	 * and `elapsed` proves the finished fallback stays in Past.
	 *
	 * The full orderings are asserted rather than membership alone, so the
	 * bucket predicate and the sort are proven to read the same chosen
	 * occurrence.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_admin_buckets_classify_series_by_their_shown_occurrence(): void {
		$fixture = $this->build_admin_sort_fixture();

		$this->assertSame(
			array( $fixture['before'], $fixture['running'], $fixture['after'] ),
			$this->run_admin_bucket_query( $fixture, 'upcoming' ),
			'Failed to assert the Upcoming view lists a running series at its next occurrence.'
		);
		$this->assertSame(
			array( $fixture['elapsed'], $fixture['way_past'] ),
			$this->run_admin_bucket_query( $fixture, 'past' ),
			'Failed to assert the Past view lists an elapsed series at its latest finished occurrence, and nothing'
				. ' else.'
		);
	}

	/**
	 * Recurring and non-recurring rows interleave by the date the list shows.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_occurrence_sorting_orders_by_shown_occurrence(): void {
		$fixture = $this->build_admin_sort_fixture();

		$this->assertSame(
			array(
				$fixture['way_past'],
				$fixture['elapsed'],
				$fixture['before'],
				$fixture['running'],
				$fixture['after'],
			),
			$this->run_admin_list_query( $fixture ),
			'Failed to assert that the admin list orders series by the occurrence it shows.'
		);
	}

	/**
	 * The join is added only when the clause actually carries the anchor ordering.
	 *
	 * Sorting the list by title has nothing for this filter to rewrite, and the
	 * derived table would be paid for on every such screen.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_occurrence_sorting_skips_non_datetime_ordering(): void {
		$fixture  = $this->build_admin_sort_fixture();
		$captured = '';
		$capture  = static function ( string $request ) use ( &$captured ): string {
			$captured = $request;

			return $request;
		};

		add_filter( 'posts_request', $capture );
		$this->run_admin_list_query( $fixture, 'title' );
		remove_filter( 'posts_request', $capture );

		$this->assertStringNotContainsString(
			Query::ADMIN_SORT_ALIAS,
			$captured,
			'Failed to assert that a title-ordered admin list pays for no occurrence join.'
		);
	}

	/**
	 * The filter is a no-op outside the admin.
	 *
	 * Front-end lists get real occurrence expansion instead, and rewriting
	 * their ordering here on top of that would sort an expanded list by a
	 * per-series aggregate.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_occurrence_sorting_is_a_no_op_outside_the_admin(): void {
		global $wpdb;

		$this->build_admin_sort_fixture();

		set_current_screen( 'front' );

		// Every other guard is deliberately satisfied, so `is_admin()` is the
		// only thing left that can return these clauses unchanged. A bare
		// `WP_Query` would pass this test with the admin check deleted, because
		// the event-post-type guard would stop it instead.
		$query = new WP_Query();
		$query->set( 'post_type', Event::POST_TYPE );

		$pieces = array(
			'orderby' => sprintf( '%sgatherpress_events.datetime_start_gmt ASC', $wpdb->prefix ),
			'join'    => '',
		);

		$this->assertSame(
			$pieces,
			Query::get_instance()->adjust_admin_occurrence_sorting( $pieces, $query ),
			'Failed to assert that the admin sorting filter does nothing on the front end.'
		);
	}

	/**
	 * The filter is a no-op on a site with no recurring events.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_occurrence_sorting_is_a_no_op_without_recurring_events(): void {
		$fixture = array(
			'early' => $this->create_event_at( $this->now()->modify( '+1 hour' ), $this->now()->modify( '+2 hours' ) ),
			'late'  => $this->create_event_at( $this->now()->modify( '+1 day' ), $this->now()->modify( '+2 days' ) ),
		);

		$captured = '';
		$capture  = static function ( string $request ) use ( &$captured ): string {
			$captured = $request;

			return $request;
		};

		add_filter( 'posts_request', $capture );
		$results = $this->run_admin_list_query( $fixture );
		remove_filter( 'posts_request', $capture );

		$this->assertStringNotContainsString(
			Query::ADMIN_SORT_ALIAS,
			$captured,
			'Failed to assert that a site with no recurring events runs unchanged admin SQL.'
		);
		$this->assertSame(
			array( $fixture['early'], $fixture['late'] ),
			$results,
			'Failed to assert that plain events still sort by their own datetime.'
		);
	}

	/**
	 * The filter is a no-op during admin-ajax, where expansion already runs.
	 *
	 * `expand_event_clauses()` deliberately carves admin-ajax back in, because
	 * those requests serve front-end reads, so `is_admin()` alone does not
	 * separate the two filters: on `admin-ajax.php` both guards hold, and the
	 * per-series sort join would ride along as a redundant grouped aggregate
	 * on top of the real expansion, on an unauthenticated endpoint.
	 *
	 * Every other guard is deliberately satisfied: the screen is an event
	 * `edit.php` screen so `is_admin()` holds, the fixture stores a projected
	 * series so the flag is on and the table exists, the query is a bucketed
	 * date-ordered event query so the events join and the anchor ordering are
	 * both present, and the expansion assertion below proves the request took
	 * the expanded path. The admin-ajax arm is the only guard left that can
	 * return these clauses unchanged.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_occurrence_sorting_is_a_no_op_during_admin_ajax(): void {
		$this->build_admin_sort_fixture();

		set_current_screen( 'edit-' . Event::POST_TYPE );
		add_filter( 'wp_doing_ajax', '__return_true' );

		$captured = '';
		$capture  = static function ( string $request ) use ( &$captured ): string {
			$captured = $request;

			return $request;
		};

		add_filter( 'posts_request', $capture );
		new WP_Query( $this->event_query_args( 'upcoming' ) );
		remove_filter( 'posts_request', $capture );

		remove_filter( 'wp_doing_ajax', '__return_true' );
		set_current_screen( 'front' );

		$this->assertStringContainsString(
			Query::OCCURRENCE_ALIAS,
			$captured,
			'Failed to assert that the admin-ajax request took the expanded front-end path.'
		);
		$this->assertStringNotContainsString(
			Query::ADMIN_SORT_ALIAS,
			$captured,
			'Failed to assert that the per-series sort join stays off an expanded admin-ajax query.'
		);
	}

	/**
	 * The bucket predicate is rewritten even when nothing is sorted by date.
	 *
	 * The `orderby` and the `where` are two independent reasons to need the
	 * occurrence relation, and only the `where` is present here: an Upcoming
	 * view the reader has re-sorted by title carries no anchor ordering at
	 * all, yet it is still a view asserting the events it lists have not
	 * finished. Bucketing it on the anchor is the same defect under a
	 * different sort, so membership rather than order is what this asserts.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 * @covers ::carries_anchor_datetime
	 *
	 * @return void
	 */
	public function test_admin_bucket_predicate_applies_to_a_title_sorted_view(): void {
		$fixture = $this->build_admin_sort_fixture();

		$upcoming = $this->run_admin_bucket_query( $fixture, 'upcoming', 'title' );

		$this->assertContains(
			$fixture['running'],
			$upcoming,
			'Failed to assert a running series is listed by a title-sorted Upcoming view.'
		);
		$this->assertNotContains(
			$fixture['elapsed'],
			$upcoming,
			'Failed to assert a fully elapsed series stays out of the Upcoming view.'
		);
		$this->assertNotContains(
			$fixture['running'],
			$this->run_admin_bucket_query( $fixture, 'past', 'title' ),
			'Failed to assert a running series stays out of a title-sorted Past view.'
		);
	}

	/**
	 * Applying the filter twice joins the relation once.
	 *
	 * The rewrite leaves the anchor column inside the `COALESCE()` it builds,
	 * so a second pass recognizes its own output and would append the derived
	 * table again under the same alias. MySQL answers that with
	 * `ERROR 1066 Not unique table/alias`, which takes out the whole list
	 * screen. Every other guard is satisfied here, so the re-entrancy check is
	 * the only thing that can hold the second pass back.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_occurrence_sorting_joins_the_relation_once(): void {
		global $wpdb;

		$this->build_admin_sort_fixture();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		$query = new WP_Query();
		$query->set( 'post_type', Event::POST_TYPE );

		$pieces = array(
			'orderby' => sprintf( '%sgatherpress_events.datetime_start_gmt ASC', $wpdb->prefix ),
			'where'   => '',
			'join'    => '',
		);

		$instance = Query::get_instance();
		$once     = $instance->adjust_admin_occurrence_sorting( $pieces, $query );
		$twice    = $instance->adjust_admin_occurrence_sorting( $once, $query );

		set_current_screen( 'front' );

		$this->assertSame(
			1,
			substr_count( $once['join'], Query::ADMIN_SORT_ALIAS . '` ON' ),
			'Failed to assert the first pass joins the relation exactly once.'
		);
		$this->assertSame(
			$once,
			$twice,
			'Failed to assert a second pass over already-rewritten clauses changes nothing.'
		);
	}

	/**
	 * The filter is a no-op for a query that is not for an event post type.
	 *
	 * N9: the guard is one statement, so line coverage reports it as covered
	 * with most of its arms never evaluated. Every other arm is deliberately
	 * satisfied here, so the post-type check is the only thing that can return
	 * these clauses unchanged: the screen is an event `edit.php` screen, the
	 * fixture stores a projected series so the flag is on and the table
	 * exists, the clause carries the anchor ordering, and nothing is joined.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_occurrence_sorting_is_a_no_op_for_a_non_event_query(): void {
		global $wpdb;

		$this->build_admin_sort_fixture();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		$query = new WP_Query();
		$query->set( 'post_type', 'post' );

		$pieces = array(
			'orderby' => sprintf( '%sgatherpress_events.datetime_start_gmt ASC', $wpdb->prefix ),
			'where'   => '',
			'join'    => '',
		);

		$filtered = Query::get_instance()->adjust_admin_occurrence_sorting( $pieces, $query );

		set_current_screen( 'front' );

		$this->assertSame(
			$pieces,
			$filtered,
			'Failed to assert a query for an unsupported post type is left alone.'
		);
	}

	/**
	 * The filter is a no-op on a blog that does not carry the occurrence table.
	 *
	 * N9, and the multisite contract `expand_event_clauses()` states at
	 * length: a blog joined to a network after the table was created has the
	 * flag but not the table, and joining a relation over a table that does
	 * not exist would empty the list. The memoized probe answer is forced, so
	 * this arm is the only one that can act.
	 *
	 * @covers ::adjust_admin_occurrence_sorting
	 *
	 * @return void
	 */
	public function test_adjust_admin_occurrence_sorting_is_a_no_op_without_the_occurrence_table(): void {
		global $wpdb;

		$this->build_admin_sort_fixture();

		set_current_screen( 'edit-' . Event::POST_TYPE );

		$query = new WP_Query();
		$query->set( 'post_type', Event::POST_TYPE );

		$pieces = array(
			'orderby' => sprintf( '%sgatherpress_events.datetime_start_gmt ASC', $wpdb->prefix ),
			'where'   => '',
			'join'    => '',
		);

		$occurrences = Occurrences::get_instance();
		$table       = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		Utility::set_and_get_hidden_property( $occurrences, 'table_exists', array( $table => false ) );

		$filtered = Query::get_instance()->adjust_admin_occurrence_sorting( $pieces, $query );

		$occurrences->forget_table_exists();
		set_current_screen( 'front' );

		$this->assertSame(
			$pieces,
			$filtered,
			'Failed to assert a blog without the occurrence table joins no relation.'
		);
	}

	/**
	 * Coverage for both renderings one anchor column can appear under.
	 *
	 * `Event\Query` writes the ORDER BY column unquoted and the WHERE column
	 * back-quoted through `$wpdb->prepare()`'s `%i` placeholder. Both have to
	 * be recognized and both have to be rewritten, so both are named here
	 * rather than inferred from whichever one a caller happened to exercise.
	 *
	 * @covers ::anchor_column_renderings
	 *
	 * @return void
	 */
	public function test_anchor_column_renderings_names_both_forms(): void {
		$this->assertSame(
			array(
				'wp_gatherpress_events.datetime_end_gmt',
				'`wp_gatherpress_events`.`datetime_end_gmt`',
			),
			Utility::invoke_hidden_method(
				Query::get_instance(),
				'anchor_column_renderings',
				array( 'wp_gatherpress_events', 'datetime_end_gmt' )
			),
			'Failed to assert both the unquoted and back-quoted renderings are produced.'
		);
	}
	/**
	 * Coverage for both return paths of `orderby_has_post_id`.
	 *
	 * The request-driven tests above are what prove the wiring, but xdebug
	 * does not reliably trace a `private` helper reached from its caller in
	 * the same class, so each return path also gets a direct invoke.
	 *
	 * @covers ::orderby_has_post_id
	 *
	 * @return void
	 */
	public function test_orderby_has_post_id_return_paths(): void {
		global $wpdb;

		$instance = Query::get_instance();

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$instance,
				'orderby_has_post_id',
				array( sprintf( 'wp_gatherpress_events.datetime_start_gmt DESC, %s.ID DESC', $wpdb->posts ) )
			),
			'Failed to assert a clause already ordering on the post ID is recognized.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'orderby_has_post_id',
				array( sprintf( '%s.post_name ASC', $wpdb->posts ) )
			),
			'Failed to assert a clause ordering on another posts-table column is not mistaken for one.'
		);
	}
	/**
	 * The results filter stamps nothing yet, and it has to leave both result
	 * shapes alone: the plugin's own read API asks for IDs, while a template
	 * loop gets `WP_Post` objects.
	 *
	 * @covers ::attach_occurrences
	 *
	 * @return void
	 */
	public function test_attach_occurrences_returns_both_result_shapes_unchanged(): void {
		$post_ids = array(
			$this->factory->post->create(),
			$this->factory->post->create(),
		);
		$posts    = array_map( 'get_post', $post_ids );
		$instance = Query::get_instance();

		$this->assertSame(
			$post_ids,
			$instance->attach_occurrences( $post_ids, new WP_Query() ),
			'Failed to assert that attach_occurrences returns an ID result set unchanged.'
		);
		$this->assertSame(
			$posts,
			$instance->attach_occurrences( $posts, new WP_Query() ),
			'Failed to assert that attach_occurrences returns a WP_Post result set unchanged.'
		);
		$this->assertSame(
			array(),
			$instance->attach_occurrences( array(), new WP_Query() ),
			'Failed to assert that attach_occurrences returns an empty result set unchanged.'
		);
	}

	/**
	 * An empty clause set survives the pass-through too, which is the shape a
	 * query with no filters of its own arrives in.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_expand_event_clauses_passes_an_empty_clause_set_through(): void {
		$this->assertSame(
			array(),
			Query::get_instance()->expand_event_clauses( array(), new WP_Query() ),
			'Failed to assert that expand_event_clauses passes an empty clause set through.'
		);
	}

	/**
	 * The clause filter hands back exactly what it was given, key order and
	 * all, so a query that ran through it is byte-identical to one that did
	 * not.
	 *
	 * @covers ::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_expand_event_clauses_returns_the_clauses_unchanged(): void {
		$pieces = array(
			'where'    => ' AND post_type = \'gatherpress_event\'',
			'groupby'  => '',
			'join'     => '',
			'orderby'  => 'wp_posts.post_date DESC',
			'distinct' => '',
			'fields'   => 'wp_posts.ID',
			'limits'   => 'LIMIT 0, 10',
		);

		$this->assertSame(
			$pieces,
			Query::get_instance()->expand_event_clauses( $pieces, new WP_Query() ),
			'Failed to assert that expand_event_clauses returns the clauses unchanged.'
		);
	}
}
