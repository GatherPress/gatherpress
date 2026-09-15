<?php
/**
 * Class handles unit tests for the occurrence-aware event post type archive.
 *
 * Every test in this file drives a real front-end request through `go_to()`
 * followed by the `template_redirect` action that `wp-includes/template-loader.php`
 * fires, because the defect this file exists for lives precisely in the gap
 * between the query WordPress parses from the URL and the query GatherPress
 * substitutes for it. A test that builds a `WP_Query` by hand, or that calls
 * `Event\Setup::handle_event_archive_redirect()` directly, travels neither side
 * of that gap and proves nothing about it.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Settings;
use GatherPress\Core\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Post;
use WP_Query;

/**
 * Class Test_Archive.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Setup
 */
class Test_Archive extends Base {

	/**
	 * Number of occurrences each fixture series projects.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const SERIES_COUNT = 8;

	/**
	 * Permalink structure in force before this test replaced it.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	protected string $permalink_structure = '';

	/**
	 * Start from an empty occurrence table before every test.
	 *
	 * The archive requests below travel the plain-permalink form of the post
	 * type archive URL, `?post_type=gatherpress_event&paged=2`. The pretty form
	 * does not exist in this harness. Measured, rather than
	 * assumed: with `/%postname%/` set and rules flushed,
	 * `$wp_rewrite->wp_rewrite_rules()` returns 85 rules and not one of them
	 * maps to `post_type=gatherpress_event`, so `/event/page/2/` resolves to
	 * `name=event&paged=2` and never reaches an archive at all. On a real site
	 * the rules are present and correctly ordered, and pretty pagination works
	 * end to end: `event/page/([0-9]{1,})/?$` sits at index 12 of 154, well ahead
	 * of the single-event rule at index 50.
	 *
	 * The deviation is safe because the rewrite layer is not implicated in this
	 * defect, which lives entirely in what `WP::handle_404()` decides before
	 * `template_redirect` runs. Both URL forms parse to the same
	 * `is_post_type_archive` + `paged` query, which is the input this file is
	 * about.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		global $wp_rewrite;

		$this->permalink_structure = (string) $wp_rewrite->permalink_structure;

		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules();
	}

	/**
	 * Restore whatever permalink structure was in force before the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure( $this->permalink_structure );
		$wp_rewrite->flush_rules();

		parent::tearDown();
	}

	/**
	 * Build "now" in UTC.
	 *
	 * Every fixture below is relative to this value rather than to a literal
	 * calendar date, because the archive compares its rows against the wall
	 * clock through `Event\Query::get_datetime_comparison_column()`.
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
	 * The optional authoring date exists so a fixture can decouple the order
	 * posts were created in from the order their IDs run in. Left unset, the
	 * factory stamps every post with the current time and the two orders
	 * coincide.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable      $start    Event start in UTC.
	 * @param DateTimeImmutable      $end      Event end in UTC.
	 * @param DateTimeImmutable|null $authored Authoring date in UTC, or null for the factory default.
	 *
	 * @return int The created post ID.
	 */
	protected function create_event_at(
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		?DateTimeImmutable $authored = null
	): int {
		$args = array(
			'post_type'   => Event::POST_TYPE,
			'post_status' => 'publish',
		);

		if ( null !== $authored ) {
			$args['post_date_gmt'] = $authored->format( 'Y-m-d H:i:s' );
			$args['post_date']     = get_date_from_gmt( $args['post_date_gmt'] );
		}

		$post_id = $this->factory->post->create( $args );

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
	 * Create a daily recurring series and project its occurrence rows.
	 *
	 * Fixtures are arranged in production order: the datetime blob and its
	 * derived events-table row first, then the recurrence blob, then the
	 * derived mirrors, then the projection.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $start Anchor start in UTC.
	 * @param DateTimeImmutable $end   Anchor end in UTC.
	 *
	 * @return int The created post ID.
	 */
	protected function create_series_at( DateTimeImmutable $start, DateTimeImmutable $end ): int {
		$post_id = $this->create_event_at( $start, $end );

		add_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => self::SERIES_COUNT,
				)
			)
		);
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return $post_id;
	}

	/**
	 * Build the archive fixture: three series and two standalone events.
	 *
	 * Three series of eight daily occurrences plus two plain events is
	 * twenty-six list entries over three pages of ten, which is what makes a
	 * broken page two distinguishable from an empty one: the union across
	 * pages has to account for every entry, and page two is neither the first
	 * page nor the last.
	 *
	 * The anchors are staggered by one hour and the earlier standalone event
	 * sits *between* the first two series anchors. The required ordering is
	 * ascending by occurrence datetime, and it interleaves all three series with
	 * a plain event on the very first page. The answer the archive produced
	 * before this fixture existed, `wp_posts.post_date DESC`, orders strictly
	 * by creation: it groups each series together and puts the *last*-created
	 * post first. The two orderings share no prefix, which is the point.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, mixed> Post IDs and their occurrence starts, keyed by role.
	 */
	protected function build_archive_fixture(): array {
		$now = $this->now();

		$anchors = array(
			'series_a' => $now->modify( '+1 hour' ),
			'series_b' => $now->modify( '+2 hours' ),
			'series_c' => $now->modify( '+3 hours' ),
		);

		$fixture = array( 'anchors' => $anchors );

		foreach ( $anchors as $role => $anchor ) {
			$fixture[ $role ] = $this->create_series_at( $anchor, $anchor->modify( '+30 minutes' ) );
		}

		$fixture['plain_starts'] = array(
			'plain_early' => $now->modify( '+90 minutes' ),
			'plain_late'  => $now->modify( '+200 hours' ),
		);

		foreach ( $fixture['plain_starts'] as $role => $start ) {
			$fixture[ $role ] = $this->create_event_at( $start, $start->modify( '+30 minutes' ) );
		}

		return $fixture;
	}

	/**
	 * State the entries the archive must list, ordered ascending by occurrence start.
	 *
	 * Built from the fixture's own arithmetic rather than from a run of the
	 * code under test, so the expectation is the requirement and not a
	 * transcript of current behavior.
	 *
	 * @since 0.36.0
	 *
	 * @param array $fixture Fixture returned by `build_archive_fixture()`.
	 *
	 * @return string[] `post_id|recurrence_id` strings in required order.
	 */
	protected function expected_entries( array $fixture ): array {
		$rows = array();

		foreach ( $fixture['plain_starts'] as $role => $start ) {
			$rows[] = array( $fixture[ $role ], '', $start );
		}

		foreach ( $fixture['anchors'] as $role => $anchor ) {
			for ( $index = 0; $index < self::SERIES_COUNT; $index++ ) {
				$start  = $anchor->modify( sprintf( '+%d days', $index ) );
				$rows[] = array( $fixture[ $role ], $start->format( 'Ymd\THis' ), $start );
			}
		}

		usort(
			$rows,
			static function ( array $left, array $right ): int {
				return $left[2] <=> $right[2];
			}
		);

		return array_map(
			static function ( array $row ): string {
				return $row[0] . '|' . $row[1];
			},
			$rows
		);
	}

	/**
	 * Drive one real archive request and return the resulting global query.
	 *
	 * `go_to()` runs `WP::main()`, which parses the request, runs the main
	 * query and calls `handle_404()`. `template_redirect` is what
	 * `wp-includes/template-loader.php` fires immediately afterwards, and it
	 * is the hook `Event\Setup::handle_event_archive_redirect()` registers on,
	 * so firing it here is the production sequence rather than a shortcut.
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
			$url = add_query_arg( 'paged', $page, $url );
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
	 * Coverage for the paged archive request that used to 404.
	 *
	 * `found_posts` counts occurrences, so WordPress advertises more pages
	 * than there are event posts. Every one of those advertised pages has to
	 * be reachable and has to carry occurrence-expanded rows.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @covers ::fall_back_to_archive_mode
	 *
	 * @return void
	 */
	public function test_every_advertised_archive_page_is_reachable(): void {
		$fixture  = $this->build_archive_fixture();
		$expected = $this->expected_entries( $fixture );

		$this->assertCount(
			26,
			$expected,
			'Failed to assert the fixture states twenty-six archive entries.'
		);

		$first = $this->request_archive_page( 1 );

		$this->assertFalse(
			$first->is_404(),
			'Failed to assert the unpaged archive request is not a 404.'
		);
		$this->assertSame(
			count( $expected ),
			$first->found_posts,
			'Failed to assert found_posts counts occurrences rather than event posts.'
		);
		$this->assertSame(
			3,
			$first->max_num_pages,
			'Failed to assert the archive advertises three pages of ten.'
		);

		$pages = array( $this->entries( $first ) );

		foreach ( array( 2, 3 ) as $page ) {
			$query = $this->request_archive_page( $page );

			$this->assertFalse(
				$query->is_404(),
				sprintf( 'Failed to assert archive page %d is not a 404.', $page )
			);
			$this->assertSame(
				count( $expected ),
				$query->found_posts,
				sprintf( 'Failed to assert page %d counts the same rows page one advertised.', $page )
			);

			$pages[] = $this->entries( $query );
		}

		$this->assertSame(
			array( 10, 10, 6 ),
			array_map( 'count', $pages ),
			'Failed to assert the three pages carry ten, ten and six entries.'
		);
		$this->assertSame(
			$expected,
			array_merge( ...$pages ),
			'Failed to assert the union across pages is every occurrence, in occurrence-datetime order.'
		);
	}

	/**
	 * Coverage for the ordering change: the archive interleaves by occurrence datetime.
	 *
	 * The assertion names the exact first page rather than a weaker property
	 * such as "more than one series appears", because the requirement is an
	 * ordering and only the required ordering can produce that exact list.
	 *
	 * @covers ::fall_back_to_archive_mode
	 *
	 * @return void
	 */
	public function test_archive_orders_by_occurrence_datetime_ascending(): void {
		$fixture  = $this->build_archive_fixture();
		$expected = $this->expected_entries( $fixture );

		$first = $this->request_archive_page( 1 );

		$this->assertSame(
			array_slice( $expected, 0, 10 ),
			$this->entries( $first ),
			'Failed to assert the first archive page is the ten earliest occurrences.'
		);

		$posts_on_first_page = array_unique(
			array_map(
				static function ( WP_Post $post ): int {
					return $post->ID;
				},
				$first->posts
			)
		);

		$this->assertCount(
			4,
			$posts_on_first_page,
			'Failed to assert all three series and the early standalone event share the first page.'
		);
	}

	/**
	 * Coverage for the boundary: a page past the end still 404s.
	 *
	 * The 404 deferral must not become a blanket "never 404 an event
	 * archive". A request past `max_num_pages` has nothing to render and has
	 * to keep saying so.
	 *
	 * @covers ::handle_event_archive_redirect
	 *
	 * @return void
	 */
	public function test_archive_page_past_the_end_is_still_a_404(): void {
		$this->build_archive_fixture();

		$query = $this->request_archive_page( 4 );

		$this->assertTrue(
			$query->is_404(),
			'Failed to assert a request past the last archive page is a 404.'
		);
		$this->assertSame(
			array(),
			$query->posts,
			'Failed to assert a request past the last archive page renders nothing.'
		);
	}

	/**
	 * Build the widened-archive fixture: twelve upcoming events, one past
	 * event and one ordinary post.
	 *
	 * The upcoming events are created earliest-first at distinct hour
	 * offsets, so the required ascending-datetime order cannot coincide with
	 * the `wp_posts.post_date DESC` order an unsubstituted query falls back
	 * to. The past event and the ordinary post exist so that a query which
	 * skipped the archive substitution counts fourteen rows where the
	 * substituted upcoming archive counts twelve: the two answers must
	 * differ, or a test could pass with the substitution entirely dead.
	 *
	 * @since 0.36.0
	 *
	 * @return int[] The upcoming event post IDs, in ascending datetime order.
	 */
	protected function build_widened_archive_fixture(): array {
		$now      = $this->now();
		$upcoming = array();

		for ( $offset = 12; $offset <= 23; $offset++ ) {
			$start = $now->modify( sprintf( '+%d hours', $offset ) );

			$upcoming[] = $this->create_event_at( $start, $start->modify( '+30 minutes' ) );
		}

		$this->create_event_at( $now->modify( '-4 hours' ), $now->modify( '-3 hours' ) );
		$this->factory->post->create( array( 'post_status' => 'publish' ) );

		return $upcoming;
	}

	/**
	 * Widen the main query's `post_type` the way an integration plugin does.
	 *
	 * Registered on `pre_get_posts`, so it also reapplies itself to the
	 * substituted archive query, exactly as a real plugin's callback would.
	 * The caller owns removal.
	 *
	 * @since 0.36.0
	 *
	 * @return callable The registered callback, for later removal.
	 */
	protected function widen_archive_post_type(): callable {
		$widen = static function ( WP_Query $query ): void {
			if ( $query->is_main_query() && $query->is_post_type_archive ) {
				$query->set( 'post_type', array( Event::POST_TYPE, 'post' ) );
			}
		};

		add_action( 'pre_get_posts', $widen );

		return $widen;
	}

	/**
	 * Control for the widened pair below: without any widening, a request
	 * past the last archive page is a 404.
	 *
	 * This is the review probe's control case. It passes with or without the
	 * shared archive guard; its job is to pin the widened failure below on
	 * the widening rather than on the fixture.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @covers ::defer_event_archive_404
	 *
	 * @return void
	 */
	public function test_control_out_of_range_page_404s(): void {
		$this->build_widened_archive_fixture();

		$query = $this->request_archive_page( 9 );

		$this->assertTrue(
			$query->is_404(),
			'Failed to assert an out-of-range archive page 404s without any post_type widening.'
		);
	}

	/**
	 * An out-of-range archive page still 404s when a plugin widens the main
	 * query's `post_type` to an array.
	 *
	 * `defer_event_archive_404()` takes core's 404 decision away for exactly
	 * this query shape, so the redirect handler must give the decision back
	 * for the same shape. Reading the post type through a `(string)` cast
	 * produced the literal `Array`, bailed without substituting and without
	 * 404ing, and the page answered `200` with an empty archive.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @covers ::defer_event_archive_404
	 *
	 * @return void
	 */
	public function test_widened_post_type_out_of_range_page_404s(): void {
		$this->build_widened_archive_fixture();

		$widen = $this->widen_archive_post_type();
		$query = $this->request_archive_page( 9 );

		remove_action( 'pre_get_posts', $widen );

		$this->assertTrue(
			$query->is_404(),
			'Failed to assert an out-of-range archive page still 404s when the post_type is widened.'
		);
		$this->assertSame(
			array(),
			$query->posts,
			'Failed to assert the widened out-of-range page renders nothing.'
		);
	}

	/**
	 * The other side of the widened boundary: an in-range page of a widened
	 * archive still renders the substituted event archive.
	 *
	 * The counts are the proof the substitution ran: the upcoming archive
	 * lists the twelve upcoming events, while a query that skipped the
	 * substitution counts fourteen rows and orders them by authoring time.
	 *
	 * @covers ::handle_event_archive_redirect
	 *
	 * @return void
	 */
	public function test_widened_post_type_in_range_page_renders(): void {
		$upcoming = $this->build_widened_archive_fixture();
		$expected = array_map(
			static function ( int $post_id ): string {
				return $post_id . '|';
			},
			array_slice( $upcoming, 0, 10 )
		);

		$widen = $this->widen_archive_post_type();
		$query = $this->request_archive_page( 1 );

		remove_action( 'pre_get_posts', $widen );

		$this->assertFalse(
			$query->is_404(),
			'Failed to assert an in-range page of a widened archive renders.'
		);
		$this->assertTrue(
			$query->is_post_type_archive(),
			'Failed to assert the widened in-range page is still an archive.'
		);
		$this->assertSame(
			12,
			$query->found_posts,
			'Failed to assert the widened archive was substituted with the upcoming event archive.'
		);
		$this->assertSame(
			$expected,
			$this->entries( $query ),
			'Failed to assert the widened first page lists the upcoming events in ascending datetime order.'
		);
	}

	/**
	 * Build the tied-start fixture: eleven upcoming events sharing one start.
	 *
	 * Eleven entries over ten per page is the smallest fixture that spans two
	 * pages, and every one of them ties on `datetime_start_gmt`, so the
	 * primary ordering key can separate none of them and only a secondary key
	 * can decide which ten land on page one.
	 *
	 * The authoring dates run backwards as the post IDs run forwards. Without
	 * that, the order MySQL falls back to when the ordering is not total, which
	 * is whatever order the rows are read in, coincides with the ascending post
	 * ID order the fix produces, and the assertions below would pass with the
	 * secondary key entirely absent.
	 *
	 * @since 0.36.0
	 *
	 * @return int[] The created post IDs, ascending.
	 */
	protected function build_tied_start_fixture(): array {
		$now   = $this->now();
		$start = $now->modify( '+6 hours' );
		$ids   = array();

		for ( $index = 0; $index < 11; $index++ ) {
			$ids[] = $this->create_event_at(
				$start,
				$start->modify( '+30 minutes' ),
				$now->modify( sprintf( '-%d hours', $index + 1 ) )
			);
		}

		return $ids;
	}

	/**
	 * Coverage for the archive ordering being total on a site with no recurring events.
	 *
	 * `Recurrence\Query::expand_event_clauses()` returns before it appends its
	 * `post ID, recurrence_id` tie-breakers whenever
	 * `site_has_recurring_events()` is false, which is the contract that keeps
	 * a site with no recurring events on byte-identical SQL. The datetime
	 * ordering the archive newly asks for therefore has to carry its own
	 * secondary key, or eleven events sharing a start over ten per page can
	 * put one event on both pages and leave another off both.
	 *
	 * The emitted `ORDER BY` is asserted alongside the two page listings.
	 * MySQL is free to return a stable order for tied rows even with no
	 * secondary key, so the listings alone can pass against an ordering that
	 * is not total; the clause assertion is the one that cannot.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @covers ::substitute_archive_query
	 *
	 * @return void
	 */
	public function test_tied_start_archive_pages_partition_the_events(): void {
		global $wpdb;

		$expected = array_map(
			static function ( int $post_id ): string {
				return $post_id . '|';
			},
			$this->build_tied_start_fixture()
		);

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$first = $this->request_archive_page( 1 );

		$this->assertStringContainsString(
			sprintf( '.datetime_start_gmt ASC, %s.ID ASC', $wpdb->posts ),
			$first->request,
			'Failed to assert the archive orders on the post ID once the event start ties.'
		);
		$this->assertSame(
			11,
			$first->found_posts,
			'Failed to assert the archive counts all eleven tied events.'
		);
		$this->assertSame(
			2,
			$first->max_num_pages,
			'Failed to assert the eleven tied events span two pages of ten.'
		);

		$second = $this->request_archive_page( 2 );

		$this->assertFalse(
			$second->is_404(),
			'Failed to assert the second page of the tied archive is not a 404.'
		);

		$page_one = $this->entries( $first );
		$page_two = $this->entries( $second );

		$this->assertSame(
			array_slice( $expected, 0, 10 ),
			$page_one,
			'Failed to assert page one lists the ten lowest post IDs of the tied set.'
		);
		$this->assertSame(
			array_slice( $expected, 10 ),
			$page_two,
			'Failed to assert page two lists the remaining tied event.'
		);
		$this->assertSame(
			array(),
			array_values( array_intersect( $page_one, $page_two ) ),
			'Failed to assert no event appears on both pages of the tied archive.'
		);
		$this->assertSame(
			$expected,
			array_merge( $page_one, $page_two ),
			'Failed to assert the two pages together list every tied event exactly once.'
		);
	}

	/**
	 * Coverage for the other side of the boundary: an empty archive is not a 404.
	 *
	 * Core does not 404 an unpaged post type archive with no rows. It renders
	 * an empty archive at `200`, because `get_queried_object()` resolves to the
	 * post type. Deferring core's decision means reproducing that rule, not
	 * inventing a stricter one: an events site whose events have all happened
	 * still has an archive.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @covers ::defer_event_archive_404
	 *
	 * @return void
	 */
	public function test_archive_with_no_matching_events_is_not_a_404(): void {
		$now = $this->now();

		// Past-only, so the default `upcoming` archive matches nothing while
		// the site still has published events.
		$this->create_event_at( $now->modify( '-4 hours' ), $now->modify( '-3 hours' ) );

		$query = $this->request_archive_page( 1 );

		$this->assertFalse(
			$query->is_404(),
			'Failed to assert an empty first page of the archive is not a 404.'
		);
		$this->assertTrue(
			$query->is_post_type_archive(),
			'Failed to assert an empty archive is still an archive.'
		);
		$this->assertSame(
			array(),
			$query->posts,
			'Failed to assert the empty archive lists nothing.'
		);
	}

	/**
	 * Coverage for the past archive: it reads most-recent-first.
	 *
	 * This covers the other direction of `substitute_archive_query()`'s order
	 * ternary. The requirement it encodes is that a past archive whose newest
	 * entry is not first is a list nobody reads top-down.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @covers ::fall_back_to_archive_mode
	 *
	 * @return void
	 */
	public function test_past_archive_orders_by_occurrence_datetime_descending(): void {
		add_filter( 'gatherpress_event_archive_mode', array( $this, 'force_past_archive_mode' ) );

		$now      = $this->now();
		$expected = array();

		for ( $offset = 2; $offset <= 5; $offset++ ) {
			$start = $now->modify( sprintf( '-%d hours', $offset ) );

			// Created newest-first, so the required reading order is the
			// creation order and the `wp_posts.post_date DESC` fallback is its
			// exact reverse.
			$expected[] = $this->create_event_at( $start, $start->modify( '+30 minutes' ) ) . '|';
		}

		$query = $this->request_archive_page( 1 );

		remove_filter( 'gatherpress_event_archive_mode', array( $this, 'force_past_archive_mode' ) );

		$this->assertSame(
			$expected,
			$this->entries( $query ),
			'Failed to assert the past archive reads most-recent-first.'
		);
	}

	/**
	 * Pin the archive mode to `past`.
	 *
	 * @since 0.36.0
	 *
	 * @return string Always `past`.
	 */
	public function force_past_archive_mode(): string {
		return 'past';
	}

	/**
	 * Direct coverage for the substitution helper's return paths.
	 *
	 * The request-driven tests above are what prove the wiring, but xdebug does
	 * not reliably trace a `protected` helper reached through a short
	 * same-class delegation. It reports the body as `count=0` even though the
	 * mutations that break it turn those tests red. Per the project's
	 * "Extracted same-class helpers and xdebug coverage tracing" rule, each
	 * return path also gets a direct invoke.
	 *
	 * @covers ::substitute_archive_query
	 *
	 * @return void
	 */
	public function test_substitute_archive_query_return_paths(): void {
		global $wp_query;

		$now = $this->now();

		for ( $offset = 1; $offset <= 3; $offset++ ) {
			$start = $now->modify( sprintf( '+%d hours', $offset ) );

			$this->create_event_at( $start, $start->modify( '+30 minutes' ) );
		}

		$instance = Event_Setup::get_instance();

		$this->go_to( (string) get_post_type_archive_link( Event::POST_TYPE ) );
		Utility::invoke_hidden_method(
			$instance,
			'substitute_archive_query',
			array( $wp_query, Event::POST_TYPE, 'upcoming' )
		);

		$this->assertCount(
			3,
			$wp_query->posts,
			'Failed to assert the substituted query lists the upcoming events.'
		);
		$this->assertSame(
			'ASC',
			$wp_query->query['order'],
			'Failed to assert an upcoming archive is substituted in ascending order.'
		);
		$this->assertTrue(
			$wp_query->is_post_type_archive,
			'Failed to assert the substituted query is flagged as a post type archive.'
		);
		$this->assertFalse(
			$wp_query->is_404(),
			'Failed to assert a substituted query with rows is not a 404.'
		);

		$this->go_to( add_query_arg( 'paged', 2, (string) get_post_type_archive_link( Event::POST_TYPE ) ) );
		Utility::invoke_hidden_method(
			$instance,
			'substitute_archive_query',
			array( $wp_query, Event::POST_TYPE, 'past' )
		);

		$this->assertSame(
			'DESC',
			$wp_query->query['order'],
			'Failed to assert a past archive is substituted in descending order.'
		);
		$this->assertTrue(
			$wp_query->is_404(),
			'Failed to assert a paged substituted query with no rows falls back to a 404.'
		);
	}

	/**
	 * Coverage for the branch where a page holds the events slug without being an archive.
	 *
	 * `defer_event_archive_404()` suppresses core's 404 for every request that
	 * reaches `handle_event_archive_redirect()`, including the one that ends up
	 * serving an ordinary page. Measured on a real site before this test
	 * existed: `/event/`, `/event/page/2/` and `/event/page/50/` all answered
	 * `200` with identical content, where the first was `200` and the other two
	 * `404` without the deferral.
	 *
	 * The `<!--nextpage-->` half of the fixture is what makes the assertion
	 * about the required bound rather than about "anything past page one": a
	 * flat "paged is a 404" rule and core's rule agree on the
	 * unsplit page and disagree on the split one, so the split page is asserted
	 * in both directions.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @covers ::maybe_404_paged_page
	 *
	 * @return void
	 */
	public function test_page_holding_the_events_slug_404s_past_its_content_pages(): void {
		$page_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Event Landing',
				'post_name'    => (string) Settings::get_instance()->get( 'events_url' ),
				'post_content' => 'Landing copy.',
			)
		);

		$first = $this->request_archive_page( 1 );

		$this->assertFalse(
			$first->is_404(),
			'Failed to assert the page holding the events slug is served at page one.'
		);
		$this->assertTrue(
			$first->is_page,
			'Failed to assert the events slug serves the page rather than an archive.'
		);
		$this->assertSame(
			$page_id,
			$first->queried_object_id,
			'Failed to assert the page is the queried object.'
		);

		$this->assertTrue(
			$this->request_archive_page( 2 )->is_404(),
			'Failed to assert page two of an unsplit landing page is a 404.'
		);
		$this->assertTrue(
			$this->request_archive_page( 50 )->is_404(),
			'Failed to assert a high page number of an unsplit landing page is a 404.'
		);

		// Two content pages now, so core's bound moves and the flat rule does not.
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => 'Part one.<!--nextpage-->Part two.',
			)
		);

		$second = $this->request_archive_page( 2 );

		$this->assertFalse(
			$second->is_404(),
			'Failed to assert page two of a split landing page is served.'
		);
		$this->assertSame(
			2,
			(int) $second->get( 'page' ),
			'Failed to assert the requested page number reaches the content paginator.'
		);
		$this->assertTrue(
			$this->request_archive_page( 3 )->is_404(),
			'Failed to assert page three of a two-page landing page is a 404.'
		);
	}

	/**
	 * Coverage for the designated archive page: it orders by occurrence datetime too.
	 *
	 * The designated-page rewrite reaches `substitute_archive_query()` through a
	 * different branch than the archive-mode fallback, and nothing asserted its
	 * ordering through a real request: the pre-existing
	 * `Test_Setup::test_handle_event_archive_redirect_designated_archive_page`
	 * sets `is_post_type_archive` by hand and calls the method directly, so it
	 * crosses neither `go_to()` nor `template_redirect`.
	 *
	 * @covers ::handle_event_archive_redirect
	 * @covers ::substitute_archive_query
	 *
	 * @return void
	 */
	public function test_designated_archive_page_orders_by_occurrence_datetime(): void {
		$slug    = (string) Settings::get_instance()->get( 'events_url' );
		$page_id = $this->factory->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Upcoming Events',
				'post_name'   => $slug,
			)
		);

		update_option(
			'gatherpress_settings',
			array(
				'upcoming_events' => wp_json_encode(
					array(
						array(
							'id'    => $page_id,
							'slug'  => $slug,
							'value' => 'Upcoming Events',
						),
					)
				),
			)
		);

		$fixture  = $this->build_archive_fixture();
		$expected = $this->expected_entries( $fixture );

		$first = $this->request_archive_page( 1 );

		$this->assertSame(
			$page_id,
			$first->queried_object_id,
			'Failed to assert the designated page stays the queried object.'
		);
		$this->assertSame(
			array_slice( $expected, 0, 10 ),
			$this->entries( $first ),
			'Failed to assert the designated archive page lists the ten earliest occurrences.'
		);

		$second = $this->request_archive_page( 2 );

		$this->assertSame(
			array_slice( $expected, 10, 10 ),
			$this->entries( $second ),
			'Failed to assert page two of the designated archive page continues the same ordering.'
		);
	}

	/**
	 * Coverage for every guard arm of the 404 deferral.
	 *
	 * Each arm is a reason GatherPress will *not* substitute a query, and
	 * therefore a reason core must be left to make its own 404 decision. The
	 * positive case is proven by the request-driven tests above; these pin the
	 * arms that turn it off.
	 *
	 * @covers ::defer_event_archive_404
	 *
	 * @dataProvider data_defer_event_archive_404
	 *
	 * @param bool   $preempt   Incoming preempt value.
	 * @param array  $flags     Conditional flags to set on the query.
	 * @param array  $vars      Query vars to set on the query.
	 * @param bool   $expected  Expected return value.
	 * @param string $assertion Assertion message.
	 *
	 * @return void
	 */
	public function test_defer_event_archive_404_guards(
		bool $preempt,
		array $flags,
		array $vars,
		bool $expected,
		string $assertion
	): void {
		$query = new WP_Query();

		foreach ( $flags as $flag => $value ) {
			$query->$flag = $value;
		}

		foreach ( $vars as $key => $value ) {
			$query->set( $key, $value );
		}

		$this->assertSame(
			$expected,
			Event_Setup::get_instance()->defer_event_archive_404( $preempt, $query ),
			$assertion
		);
	}

	/**
	 * Direct coverage for every arm of the shared archive-request guard.
	 *
	 * Each declining case flips exactly one guard while satisfying all the
	 * others, so the arm named is the only one that can act. Driven through
	 * `Utility::invoke_hidden_method()` because xdebug does not reliably
	 * trace same-class helper bodies called from their parent callbacks.
	 *
	 * @covers ::is_event_archive_request
	 *
	 * @return void
	 */
	public function test_is_event_archive_request_arms(): void {
		$instance = Event_Setup::get_instance();
		$build    = static function ( array $flags, array $vars ): WP_Query {
			$query = new WP_Query();

			foreach ( $flags as $flag => $value ) {
				$query->$flag = $value;
			}

			foreach ( $vars as $key => $value ) {
				$query->set( $key, $value );
			}

			return $query;
		};

		$archive = array(
			'is_post_type_archive' => true,
			'is_feed'              => false,
		);
		$event   = array( 'post_type' => Event::POST_TYPE );

		$cases = array(
			array( $archive, $event, true, 'an event archive request is accepted' ),
			array(
				array(
					'is_post_type_archive' => false,
					'is_feed'              => false,
				),
				$event,
				false,
				'a non-archive request is declined',
			),
			array(
				array(
					'is_post_type_archive' => true,
					'is_feed'              => true,
				),
				$event,
				false,
				'a feed request is declined',
			),
			array(
				$archive,
				array(
					'post_type'                    => Event::POST_TYPE,
					Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				),
				false,
				'an already-claimed archive is declined',
			),
			array( $archive, array( 'post_type' => 'post' ), false, 'a non-event archive is declined' ),
		);

		foreach ( $cases as list( $flags, $vars, $expected, $label ) ) {
			$this->assertSame(
				$expected,
				Utility::invoke_hidden_method(
					$instance,
					'is_event_archive_request',
					array( $build( $flags, $vars ) )
				),
				sprintf( 'Failed to assert %s.', $label )
			);
		}
	}

	/**
	 * Direct coverage for the concrete-post-type resolver, one case per
	 * return shape: a plain string, a widened array holding an event type,
	 * and a widened array holding none.
	 *
	 * @covers ::first_queried_event_post_type
	 *
	 * @return void
	 */
	public function test_first_queried_event_post_type_resolves_from_the_intersection(): void {
		$instance = Event_Setup::get_instance();
		$build    = static function ( $post_type ): WP_Query {
			$query = new WP_Query();
			$query->set( 'post_type', $post_type );

			return $query;
		};

		$this->assertSame(
			Event::POST_TYPE,
			Utility::invoke_hidden_method(
				$instance,
				'first_queried_event_post_type',
				array( $build( Event::POST_TYPE ) )
			),
			'Failed to assert a plain string post type resolves to itself.'
		);
		$this->assertSame(
			Event::POST_TYPE,
			Utility::invoke_hidden_method(
				$instance,
				'first_queried_event_post_type',
				array( $build( array( 'post', Event::POST_TYPE ) ) )
			),
			'Failed to assert a widened array resolves to its event-supporting member.'
		);
		$this->assertSame(
			'',
			Utility::invoke_hidden_method(
				$instance,
				'first_queried_event_post_type',
				array( $build( array( 'post', 'page' ) ) )
			),
			'Failed to assert an array holding no event-supporting type resolves to the empty string.'
		);
	}

	/**
	 * Data provider for the 404 deferral guards.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<int, mixed>> One case per guard arm.
	 */
	public function data_defer_event_archive_404(): array {
		$archive = array(
			'is_post_type_archive' => true,
			'is_feed'              => false,
		);
		$event   = array( 'post_type' => Event::POST_TYPE );

		return array(
			'defers on an event post type archive' => array(
				false,
				$archive,
				$event,
				true,
				'Failed to assert the 404 decision is deferred on an event archive request.',
			),
			'yields to an existing preempt'        => array(
				true,
				$archive,
				$event,
				true,
				'Failed to assert an existing preempt is returned unchanged.',
			),
			'ignores a non-archive request'        => array(
				false,
				array(
					'is_post_type_archive' => false,
					'is_feed'              => false,
				),
				$event,
				false,
				'Failed to assert a non-archive request is left to core.',
			),
			'ignores a feed request'               => array(
				false,
				array(
					'is_post_type_archive' => true,
					'is_feed'              => true,
				),
				$event,
				false,
				'Failed to assert an event feed request is left to core.',
			),
			'ignores an already-claimed archive'   => array(
				false,
				$archive,
				array(
					'post_type'                    => Event::POST_TYPE,
					Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				),
				false,
				'Failed to assert an archive another handler already claimed is left to core.',
			),
			'ignores a non-event post type'        => array(
				false,
				$archive,
				array( 'post_type' => 'post' ),
				false,
				'Failed to assert a non-event post type archive is left to core.',
			),
			'defers on a widened archive holding an event type' => array(
				false,
				$archive,
				array( 'post_type' => array( Event::POST_TYPE, 'post' ) ),
				true,
				'Failed to assert an archive widened to an array holding an event post type still defers.',
			),
			'ignores a widened archive of only non-event types' => array(
				false,
				$archive,
				array( 'post_type' => array( 'post', 'page' ) ),
				false,
				'Failed to assert a widened archive holding no event post type is left to core.',
			),
		);
	}

	/**
	 * Coverage for a site with no recurring events: pagination still works.
	 *
	 * The 404 deferral is not gated on the recurrence flag, and it must not
	 * be: a plain event archive with more posts than fit on a page paginates
	 * through the same code path.
	 *
	 * The twelve events are created earliest-first. The required ordering is
	 * ascending by event datetime, the exact reverse of the
	 * `wp_posts.post_date DESC` the archive produced before, so no page of the
	 * required answer can coincide with a page of the old one.
	 *
	 * @covers ::handle_event_archive_redirect
	 *
	 * @return void
	 */
	public function test_non_recurring_archive_paginates_in_datetime_order(): void {
		$now      = $this->now();
		$expected = array();

		for ( $offset = 12; $offset <= 23; $offset++ ) {
			$start = $now->modify( sprintf( '+%d hours', $offset ) );

			$expected[] = $this->create_event_at( $start, $start->modify( '+30 minutes' ) ) . '|';
		}

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$first  = $this->request_archive_page( 1 );
		$second = $this->request_archive_page( 2 );

		$this->assertSame(
			12,
			$first->found_posts,
			'Failed to assert the plain archive counts all twelve events.'
		);
		$this->assertSame(
			$expected,
			array_merge( $this->entries( $first ), $this->entries( $second ) ),
			'Failed to assert a non-recurring archive paginates in ascending datetime order.'
		);
	}

	/**
	 * Coverage for the no-recurring-events guarantee through the real archive
	 * entry point.
	 *
	 * Captures every statement a full page-one and page-two archive request
	 * runs on a site whose `gatherpress_has_recurring_events` option is `'0'`,
	 * once with the recurrence clause and result filters registered and once
	 * with them removed, and asserts the two captures are byte-identical. A
	 * `posts_clauses` callback capture cannot prove this, because it only
	 * observes the queries that reach that one filter. The capture is therefore
	 * taken from `$wpdb->queries` across the whole request.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 * @covers \GatherPress\Core\Event\Recurrence\Query::attach_occurrences
	 *
	 * @return void
	 */
	public function test_archive_request_runs_identical_sql_without_recurring_events(): void {
		$now = $this->now();

		for ( $index = 1; $index <= 12; $index++ ) {
			$start = $now->modify( sprintf( '+%d hours', $index ) );

			$this->create_event_at( $start, $start->modify( '+30 minutes' ) );
		}

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$recurrence = Query::get_instance();
		$with       = $this->capture_archive_queries();

		remove_filter( 'posts_clauses', array( $recurrence, 'expand_event_clauses' ), 11 );
		remove_filter( 'the_posts', array( $recurrence, 'attach_occurrences' ), 10 );

		$without = $this->capture_archive_queries();

		add_filter( 'posts_clauses', array( $recurrence, 'expand_event_clauses' ), 11, 2 );
		add_filter( 'the_posts', array( $recurrence, 'attach_occurrences' ), 10, 2 );

		$this->assertNotEmpty(
			$with,
			'Failed to assert the capture actually observed the archive request.'
		);
		$this->assertSame(
			$without,
			$with,
			'Failed to assert a flag-off site runs identical SQL with and without the recurrence filters.'
		);
		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert the archive request performed no option write.'
		);
	}

	/**
	 * Capture every SQL statement two real archive requests run.
	 *
	 * Two details make the capture comparable rather than merely repeatable.
	 * The pair of requests is run once and thrown away before the capture
	 * starts, because the first run of any request primes the object cache and
	 * the second therefore issues fewer statements. Comparing a cold capture
	 * against a warm one measures the cache, not the filters. And the current
	 * GMT timestamp `Event\Query::adjust_event_sql()` interpolates is
	 * normalized away, because two captures taken microseconds apart can still
	 * straddle a second boundary. Nothing else is touched, so a structural
	 * difference still fails the comparison, whether that is an added join, an
	 * added column, or an extra statement.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The statements, in execution order.
	 */
	protected function capture_archive_queries(): array {
		global $wpdb;

		$this->request_archive_page( 1 );
		$this->request_archive_page( 2 );

		$previous_queries   = $wpdb->queries;
		$previous_save      = $wpdb->save_queries;
		$wpdb->queries      = array();
		$wpdb->save_queries = true;

		$this->request_archive_page( 1 );
		$this->request_archive_page( 2 );

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
}
