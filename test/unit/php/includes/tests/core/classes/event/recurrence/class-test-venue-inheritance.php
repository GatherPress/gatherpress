<?php
/**
 * Class handles unit tests for venue inheritance: an occurrence inherits its
 * series' venue, and occurrence-aware event queries stay filterable by venue.
 *
 * Venue resolution is deliberately exercised through actual page requests
 * rather than through `Recurrence\Context`'s read API. The venue association
 * is not per-occurrence state at all: it is a `_gatherpress_venue` taxonomy
 * term on the series post, and `Rewrite::parse_request()` resolves every
 * occurrence URL of a series to a post of that series
 * (`Series::resolve_post_ids()` returns `array( $post_id )` until a forward
 * split widens it). Driving the request is therefore the only way to prove the
 * property that matters, that a visitor landing on any occurrence page sees
 * the series' venue, rather than proving that a term lookup works.
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
use GatherPress\Core\Event\Recurrence\Rewrite;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Setup;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;
use WP_Query;

/**
 * Class Test_Venue_Inheritance.
 *
 * @since 0.36.0
 */
class Test_Venue_Inheritance extends Base {

	use Rewrite_State;

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
	 * Start from an empty occurrence table for every test in this file,
	 * independent of execution order.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		$this->snapshot_rewrite_state();
	}

	/**
	 * Put the rewrite state back the way this file found it.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->restore_rewrite_state();

		parent::tearDown();
	}

	/**
	 * Build "now" in UTC.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable Current time in UTC.
	 */
	protected function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The anchor start every fixture in this file is built from.
	 *
	 * Relative to now rather than a literal calendar date, and far enough ahead
	 * that all five daily occurrences are upcoming:
	 * `test_venue_filtered_query_returns_occurrences_of_matching_series()`
	 * counts them out of an `upcoming` bucket, so a pinned anchor would shed
	 * one occurrence per day once the date passed, counting 5, then 4, then 3,
	 * and report a venue-filtering regression rather than a stale fixture.
	 * `test_the_fixture_series_is_never_in_the_past()` fails by name if this is
	 * ever re-pinned.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The fixture anchor start in UTC.
	 */
	protected function anchor(): DateTimeImmutable {
		return $this->now()->modify( '+2 hours' );
	}

	/**
	 * Create a venue post and its `_gatherpress_venue` taxonomy term.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug Venue post name, which the term slug derives from.
	 *
	 * @return int The venue post ID.
	 */
	protected function create_venue( string $slug ): int {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
				'post_name'   => $slug,
				'post_title'  => ucwords( str_replace( '-', ' ', $slug ) ),
			)
		);

		$term_slug = Venue_Setup::get_instance()->term_slug_from_post_name( $slug );

		wp_insert_term(
			ucwords( str_replace( '-', ' ', $slug ) ),
			Venue::TAXONOMY,
			array( 'slug' => $term_slug )
		);

		return (int) $venue_id;
	}

	/**
	 * Create a recurring event series, tagged with a venue term, and project
	 * its occurrence rows.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_name  Event post name.
	 * @param string $venue_slug Venue post name whose term the event is tagged with.
	 *
	 * @return int The created series post ID.
	 */
	protected function create_series_with_venue( string $post_name, string $venue_slug ): int {
		$start = $this->anchor();
		$end   = $start->modify( '+2 hours' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
				'post_name'   => $post_name,
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

		$term_slug = Venue_Setup::get_instance()->term_slug_from_post_name( $venue_slug );
		wp_set_post_terms( $post_id, $term_slug, Venue::TAXONOMY );

		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::DAILY_RULE ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return (int) $post_id;
	}

	/**
	 * Turn on pretty permalinks and register the occurrence rewrite rule.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function enable_pretty_permalinks(): void {
		global $wp_rewrite;

		update_option( 'permalink_structure', '/%postname%/' );
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		do_action( 'init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();
	}

	/**
	 * The series' venue resolves identically no matter
	 * which of its occurrence pages a visitor lands on.
	 *
	 * @covers \GatherPress\Core\Venue\Setup::get_venue_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_venue_renders_on_every_occurrence_page(): void {
		$venue_id = $this->create_venue( 'the-conference-hall' );
		$series   = $this->create_series_with_venue( 'venue-inheritance-series', 'the-conference-hall' );

		$occurrences = Occurrences::get_instance()->select_for_series( array( $series ) );

		$this->assertCount( 5, $occurrences, 'Failed to assert the fixture series projected five occurrences.' );

		$this->enable_pretty_permalinks();

		foreach ( $occurrences as $occurrence ) {
			$url = Rewrite::get_occurrence_url( $series, (string) $occurrence['recurrence_id'] );

			$this->go_to( $url );

			$queried_id = (int) get_queried_object_id();

			$this->assertSame(
				$series,
				$queried_id,
				sprintf( 'Failed to assert occurrence %s resolved to the series post.', $occurrence['recurrence_id'] )
			);

			$venue_post = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $queried_id );

			$this->assertNotNull(
				$venue_post,
				sprintf( 'Failed to assert occurrence %s resolved a venue at all.', $occurrence['recurrence_id'] )
			);
			$this->assertSame(
				$venue_id,
				$venue_post->ID,
				sprintf(
					'Failed to assert occurrence %s inherited the series venue.',
					$occurrence['recurrence_id']
				)
			);
		}
	}

	/**
	 * An occurrence-aware event query filtered by venue
	 * returns only the occurrences of series tagged with that venue.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_venue_filtered_query_returns_occurrences_of_matching_series(): void {
		$this->create_venue( 'hall-a' );
		$this->create_venue( 'hall-b' );

		$series_a = $this->create_series_with_venue( 'series-at-hall-a', 'hall-a' );
		$series_b = $this->create_series_with_venue( 'series-at-hall-b', 'hall-b' );

		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site reports recurring events.'
		);

		$hall_a_term = Venue_Setup::get_instance()->term_slug_from_post_name( 'hall-a' );

		$query = new WP_Query(
			array(
				'post_type'                    => Event::POST_TYPE,
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				'posts_per_page'               => 20,
				'orderby'                      => 'datetime',
				'order'                        => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'                    => array(
					array(
						'taxonomy' => Venue::TAXONOMY,
						'field'    => 'slug',
						'terms'    => array( $hall_a_term ),
					),
				),
			)
		);

		$entries = array_map(
			static function ( $post ) {
				return $post->ID . '|' . (string) $post->gatherpress_recurrence_id;
			},
			$query->posts
		);

		$series_a_entries = array_values(
			array_filter(
				$entries,
				static function ( string $entry ) use ( $series_a ): bool {
					return str_starts_with( $entry, $series_a . '|' );
				}
			)
		);
		$series_b_entries = array_values(
			array_filter(
				$entries,
				static function ( string $entry ) use ( $series_b ): bool {
					return str_starts_with( $entry, $series_b . '|' );
				}
			)
		);

		$this->assertCount(
			5,
			$series_a_entries,
			'Failed to assert the venue-filtered query returns all five occurrences of the matching series.'
		);
		$this->assertSame(
			array(),
			$series_b_entries,
			'Failed to assert the venue-filtered query excludes every occurrence of the non-matching series.'
		);
		$this->assertSame(
			5,
			$query->found_posts,
			'Failed to assert found_posts counts the matching series occurrences, not the series count.'
		);
	}

	/**
	 * The date-bomb guard for this file, and it fails by name.
	 *
	 * If you are reading this because it failed, someone re-pinned `anchor()`
	 * to a literal date. Make it relative to `now()` again.
	 *
	 * @return void
	 */
	public function test_the_fixture_series_is_never_in_the_past(): void {
		$this->assertGreaterThan(
			$this->now()->getTimestamp(),
			$this->anchor()->getTimestamp(),
			'Failed to assert this file\'s shared fixture anchor is still ahead of the clock. Someone re-pinned'
				. ' anchor() to a literal date; make it relative to now() again.'
		);

		$this->create_venue( 'guard-hall' );

		$series = $this->create_series_with_venue( 'guard-series', 'guard-hall' );
		$query  = new WP_Query(
			array(
				'post_type'                    => Event::POST_TYPE,
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				'posts_per_page'               => 20,
				'post__in'                     => array( $series ),
			)
		);

		$this->assertCount(
			5,
			$query->posts,
			'Failed to assert all five occurrences of the shared fixture series are still upcoming. Someone'
				. ' re-pinned anchor() to a literal date; make it relative to now() again.'
		);
	}
}
