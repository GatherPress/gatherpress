<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Series.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Calendar\Setup as Calendar_Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rest_Api;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Event\Recurrence\Series;
use GatherPress\Core\Event\Recurrence\Splitter;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use ReflectionClass;

/**
 * Class Test_Series.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Series
 */
class Test_Series extends Base {

	use Occurrence_Fixtures;

	/**
	 * The reference bi-weekly rule, widened to six occurrences.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const SIX_WEEK_RULE = array(
		'frequency' => 'weekly',
		'interval'  => 2,
		'weekdays'  => array( 2, 4 ),
		'end_type'  => 'count',
		'count'     => 6,
	);

	/**
	 * Start every test from an empty occurrence table, with no membership memo
	 * left over from another test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		Series::get_instance()->flush_memo();
		$this->forget_series_taxonomy();
	}

	/**
	 * Leave no membership memo behind for the next test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Series::get_instance()->flush_memo();
		$this->forget_series_taxonomy();

		parent::tearDown();
	}

	/**
	 * Unregister the series taxonomy so no test inherits another's registration.
	 *
	 * Taxonomy registration is process-global and WordPress's test case does not
	 * roll it back, so without this the guard test would find the taxonomy
	 * already registered by whichever test ran before it, and would pass or
	 * fail on execution order rather than on the guard it is about.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function forget_series_taxonomy(): void {
		if ( taxonomy_exists( Series::TAXONOMY ) ) {
			unregister_taxonomy( Series::TAXONOMY );
		}
	}

	/**
	 * Create the six-occurrence reference series, project it, and flag the site.
	 *
	 * @since 0.36.0
	 *
	 * @return int The projected series post ID.
	 */
	protected function create_and_project(): int {
		$post_id = $this->create_recurring_event( self::SIX_WEEK_RULE );

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );
		Recurrence_Query::refresh_has_recurring_events();

		return $post_id;
	}

	/**
	 * Count the rows the series taxonomy holds across terms and relationships.
	 *
	 * Read straight out of storage rather than through `get_terms()`, because
	 * the claim under test is that **no record exists**, and a helper that
	 * filters by object type could hide one that does.
	 *
	 * @since 0.36.0
	 *
	 * @return array{terms: int, relationships: int} Row counts.
	 */
	protected function series_record_counts(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$terms = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE taxonomy = %s', $wpdb->term_taxonomy, Series::TAXONOMY )
		);

		$relationships = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i AS tr INNER JOIN %i AS tt'
					. ' ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.taxonomy = %s',
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
				Series::TAXONOMY
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array(
			'terms'         => $terms,
			'relationships' => $relationships,
		);
	}

	/**
	 * Both posts a split produced resolve to one another.
	 *
	 * @covers ::resolve_post_ids
	 * @covers ::resolve_from_taxonomy
	 * @covers ::join
	 * @covers ::create_term_for
	 * @covers ::term_id_for_post
	 *
	 * @return void
	 */
	public function test_a_split_series_resolves_from_either_post(): void {
		$origin_id = $this->create_and_project();
		$result    = Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' );
		$forward   = (int) $result['forward_post_id'];
		$expected  = array( min( $origin_id, $forward ), max( $origin_id, $forward ) );

		$instance = Series::get_instance();

		$this->assertSame(
			$expected,
			$instance->resolve_post_ids( $origin_id ),
			'Failed to assert the original post resolves to both fragments of the split series.'
		);

		$instance->flush_memo();

		$this->assertSame(
			$expected,
			$instance->resolve_post_ids( $forward ),
			'Failed to assert the forward post resolves to both fragments of the split series.'
		);
	}

	/**
	 * A series split twice resolves all three posts in one read, with one term.
	 *
	 * The series must not be a chain requiring traversal, and the fixture is
	 * chosen so a parent-pointer implementation gives a different answer: with
	 * A -> B -> C
	 * pointers, resolving A finds B and stops, because A has no pointer at C.
	 * Asserting that all three share a single term is the same claim stated
	 * structurally.
	 *
	 * @covers ::resolve_post_ids
	 * @covers ::join
	 * @covers ::term_id_for_post
	 *
	 * @return void
	 */
	public function test_a_twice_split_series_resolves_without_traversal(): void {
		$instance = Series::get_instance();

		$post_a = $this->create_and_project();
		$post_b = (int) Splitter::get_instance()->split_forward( $post_a, '20260917T180000' )['forward_post_id'];

		$instance->flush_memo();

		$post_c = (int) Splitter::get_instance()->split_forward( $post_b, '20261001T180000' )['forward_post_id'];

		$expected = array( $post_a, $post_b, $post_c );
		sort( $expected );

		foreach ( array( $post_a, $post_b, $post_c ) as $post_id ) {
			$instance->flush_memo();

			$this->assertSame(
				$expected,
				$instance->resolve_post_ids( $post_id ),
				sprintf(
					'Failed to assert post %d resolves to all three posts of the twice-split series.',
					$post_id
				)
			);
		}

		$this->assertSame(
			1,
			$this->series_record_counts()['terms'],
			'Failed to assert the twice-split series is one term rather than a chain of two.'
		);
	}

	/**
	 * A series that has never been split creates no series records.
	 *
	 * @covers ::resolve_post_ids
	 * @covers ::resolve_from_taxonomy
	 *
	 * @return void
	 */
	public function test_an_unsplit_series_creates_no_records(): void {
		$post_id = $this->create_and_project();

		Series::register_taxonomy_for( Event::POST_TYPE );

		$this->assertSame(
			array( $post_id ),
			Series::get_instance()->resolve_post_ids( $post_id ),
			'Failed to assert an unsplit series is a series of one post.'
		);
		$this->assertSame(
			array(
				'terms'         => 0,
				'relationships' => 0,
			),
			$this->series_record_counts(),
			'Failed to assert a series that has never been split wrote no term and no relationship.'
		);
	}

	/**
	 * Resolution is memoized per post for the life of the request.
	 *
	 * @covers ::resolve_from_taxonomy
	 * @covers ::flush_memo
	 *
	 * @return void
	 */
	public function test_membership_is_memoized_until_flushed(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];
		$instance  = Series::get_instance();

		$instance->flush_memo();

		$first = $instance->resolve_post_ids( $origin_id );

		// Break storage under the memo: a second read that re-queried would see
		// the relationship gone and answer with one post.
		wp_delete_object_term_relationships( $forward, Series::TAXONOMY );
		clean_object_term_cache( $forward, Event::POST_TYPE );

		$this->assertSame(
			$first,
			$instance->resolve_post_ids( $origin_id ),
			'Failed to assert the memoized answer is reused within a request.'
		);

		$instance->flush_memo();

		$this->assertSame(
			array( $origin_id ),
			$instance->resolve_post_ids( $origin_id ),
			'Failed to assert flushing the memo re-reads storage.'
		);
	}

	/**
	 * The taxonomy is not registered on a site with no recurring events.
	 *
	 * `WP_Query` primes term caches with one query naming every taxonomy
	 * registered for the post type, so registering this one unconditionally would
	 * change the SQL text of a query that runs on every event listing. The
	 * assertion is not that the guard exists but that it is load-bearing: the
	 * same capture taken with the taxonomy force-registered differs.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_a_site_without_recurring_events_never_registers_the_taxonomy(): void {
		$this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		Recurrence_Query::refresh_has_recurring_events();

		$this->assertFalse(
			Recurrence_Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		Series::get_instance()->register( Event::POST_TYPE );

		$this->assertFalse(
			taxonomy_exists( Series::TAXONOMY ),
			'Failed to assert the series taxonomy stays unregistered on a site with no recurring events.'
		);

		$guarded = $this->capture_archive_sql();

		Series::register_taxonomy_for( Event::POST_TYPE );

		$unguarded = $this->capture_archive_sql();

		// The difference is emitted by `update_object_term_cache()`, which runs
		// only once the loop has loaded an event. Asserting the loop ran is what
		// stops a request that never reached an event listing, such as a page
		// lookup or an empty archive, from satisfying the comparison below with
		// two identical captures of nothing.
		$this->assertGreaterThan(
			0,
			$guarded['events'],
			'Failed to assert the guarded capture actually looped an event.'
		);
		$this->assertSame(
			$guarded['events'],
			$unguarded['events'],
			'Failed to assert both captures looped the same events. Otherwise the difference below'
				. ' could come from a different result set rather than from the taxonomy registration.'
		);

		$guarded   = $guarded['queries'];
		$unguarded = $unguarded['queries'];

		$this->assertNotEmpty( $guarded, 'Failed to assert the capture observed the archive request at all.' );
		$this->assertNotSame(
			$unguarded,
			$guarded,
			'Failed to assert registering the taxonomy changes the SQL an event archive runs. If it did not,'
				. ' this test could never fail and the registration guard would be unfalsifiable.'
		);
		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$guarded,
					static function ( string $query ): bool {
						return str_contains( $query, Series::TAXONOMY );
					}
				)
			),
			'Failed to assert no query on a flag-off site names the series taxonomy.'
		);

		// The other half of the guarantee: no extra writes. Asserted over the whole
		// capture rather than over the taxonomy tables alone, so a write this
		// diff did not anticipate still fails here.
		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$guarded,
					static function ( string $query ): bool {
						return (bool) preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $query );
					}
				)
			),
			'Failed to assert an event archive on a flag-off site performs no writes at all.'
		);
	}

	/**
	 * The taxonomy registers once the site has a recurring event.
	 *
	 * @covers ::register
	 * @covers ::register_taxonomy_for
	 *
	 * @return void
	 */
	public function test_the_taxonomy_registers_once_the_site_has_recurring_events(): void {
		$this->create_and_project();

		Series::get_instance()->register( Event::POST_TYPE );

		$this->assertTrue(
			taxonomy_exists( Series::TAXONOMY ),
			'Failed to assert the series taxonomy registers once a recurring event exists.'
		);
		$this->assertContains(
			Series::TAXONOMY,
			get_object_taxonomies( Event::POST_TYPE ),
			'Failed to assert the taxonomy is attached to the event post type.'
		);
	}

	/**
	 * An unsupported post type never gets the taxonomy.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_skips_post_types_without_event_date_support(): void {
		$this->create_and_project();

		Series::get_instance()->register( 'page' );

		$this->assertNotContains(
			Series::TAXONOMY,
			get_object_taxonomies( 'page' ),
			'Failed to assert a post type without gatherpress-event-date support is skipped.'
		);
	}

	/**
	 * An already-registered taxonomy is attached to a second post type rather than re-registered.
	 *
	 * @covers ::register_taxonomy_for
	 *
	 * @return void
	 */
	public function test_register_taxonomy_for_attaches_a_second_post_type(): void {
		register_post_type( 'gp_test_event', array( 'public' => true ) );
		add_post_type_support( 'gp_test_event', 'gatherpress-event-date' );

		Series::register_taxonomy_for( Event::POST_TYPE );
		Series::register_taxonomy_for( 'gp_test_event' );

		$attached = get_object_taxonomies( 'gp_test_event' );

		unregister_post_type( 'gp_test_event' );

		$this->assertContains(
			Series::TAXONOMY,
			$attached,
			'Failed to assert a second post type is attached to the existing taxonomy.'
		);
	}

	/**
	 * A term left behind without relationships is recovered rather than treated as a failure.
	 *
	 * @covers ::create_term_for
	 *
	 * @return void
	 */
	public function test_create_term_for_recovers_an_existing_term(): void {
		$origin_id = $this->create_and_project();

		Series::register_taxonomy_for( Event::POST_TYPE );

		$existing = wp_insert_term(
			sprintf( 'series-%d', $origin_id ),
			Series::TAXONOMY,
			array( 'slug' => sprintf( 'series-%d', $origin_id ) )
		);

		$this->assertSame(
			(int) $existing['term_id'],
			Utility::invoke_hidden_method( Series::get_instance(), 'create_term_for', array( $origin_id ) ),
			'Failed to assert an existing series term is recovered rather than reported as an error.'
		);
	}

	/**
	 * A term that can be neither created nor recovered reports zero.
	 *
	 * @covers ::create_term_for
	 * @covers ::join
	 *
	 * @return void
	 */
	public function test_join_reports_zero_when_the_term_cannot_be_created(): void {
		$origin_id  = $this->create_and_project();
		$forward_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		Series::register_taxonomy_for( Event::POST_TYPE );

		$refuse = static function () {
			return new \WP_Error( 'gatherpress_test_refused', 'Refused.' );
		};

		add_filter( 'pre_insert_term', $refuse );

		$term_id = Series::get_instance()->join( $origin_id, $forward_id );

		remove_filter( 'pre_insert_term', $refuse );

		$this->assertSame(
			0,
			$term_id,
			'Failed to assert a term that can be neither created nor recovered is reported as zero.'
		);
		$this->assertSame(
			array( $forward_id ),
			Series::get_instance()->resolve_post_ids( $forward_id ),
			'Failed to assert no partial relationship was written when the term could not be created.'
		);
	}

	/**
	 * Deleting the last fragment of a split series removes the series term.
	 *
	 * WordPress deletes a hard-deleted post's term relationships but never the
	 * term itself, so every split series whose posts were all deleted used to
	 * leave one unreachable `wp_terms`/`wp_term_taxonomy` row pair behind,
	 * forever. The control half of the assertion matters as much: deleting one
	 * fragment of a surviving series must keep the term, because the remaining
	 * fragment still resolves through it.
	 *
	 * @covers ::remember_series_term
	 * @covers ::maybe_delete_orphan_term
	 *
	 * @return void
	 */
	public function test_deleting_the_last_fragment_removes_the_series_term(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];
		$term_id   = Series::get_instance()->term_id_for_post( $origin_id );

		$this->assertGreaterThan( 0, $term_id, 'Fixture setup: the split must have created a series term.' );

		wp_delete_post( $origin_id, true );

		$this->assertInstanceOf(
			\WP_Term::class,
			get_term( $term_id, Series::TAXONOMY ),
			'Deleting one fragment must keep the term: the surviving fragment still resolves through it.'
		);

		wp_delete_post( $forward, true );

		$this->assertNull(
			get_term( $term_id, Series::TAXONOMY ),
			'Deleting the last fragment must remove the series term rather than leaving an orphan row pair.'
		);
	}

	/**
	 * Deleting one fragment of a surviving series flushes the membership memo.
	 *
	 * The flush used to live only inside the branch that deletes an orphaned
	 * term, so deleting one fragment of a three-fragment series left the memo
	 * naming the deleted post for the rest of the request, and a bulk delete
	 * had `Revision::advance()` writing meta against posts that no longer
	 * existed. A deletion changes membership whether or not the term survives
	 * it, so the memo must be discarded on both branches.
	 *
	 * @covers ::maybe_delete_orphan_term
	 * @covers ::resolve_from_taxonomy
	 *
	 * @return void
	 */
	public function test_deleting_a_fragment_flushes_the_membership_memo(): void {
		$instance = Series::get_instance();

		$post_a = $this->create_and_project();
		$post_b = (int) Splitter::get_instance()->split_forward( $post_a, '20260917T180000' )['forward_post_id'];

		$instance->flush_memo();

		$post_c = (int) Splitter::get_instance()->split_forward( $post_b, '20261001T180000' )['forward_post_id'];

		$instance->flush_memo();

		$all = array( $post_a, $post_b, $post_c );
		sort( $all );

		// This read primes the memo with the three-member set.
		$this->assertSame(
			$all,
			$instance->resolve_post_ids( $post_a ),
			'Fixture setup: the twice-split series must resolve all three members.'
		);

		wp_delete_post( $post_b, true );

		$expected = array( $post_a, $post_c );
		sort( $expected );

		$this->assertSame(
			$expected,
			$instance->resolve_post_ids( $post_a ),
			'Deleting one fragment must drop it from resolution in the same request, not serve the stale memo.'
		);
	}

	/**
	 * A failed forward relationship write reports zero from an existing term.
	 *
	 * The other arm of the join failure: the origin already carries its series
	 * term (a series split before), so only the forward post's relationship
	 * write runs, and its failure alone must be enough to report zero.
	 *
	 * @covers ::join
	 *
	 * @return void
	 */
	public function test_join_reports_zero_when_the_forward_relationship_write_fails(): void {
		$origin_id = $this->create_and_project();
		$first     = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];
		$forward   = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$this->assertGreaterThan( 0, $first, 'Fixture setup: the first split should have produced a post.' );

		$suppress = static function ( $terms, $query ) {
			$taxonomies = (array) ( $query->query_vars['taxonomy'] ?? array() );

			if ( in_array( Series::TAXONOMY, $taxonomies, true )
				&& ! empty( $query->query_vars['include'] )
			) {
				return array();
			}

			return $terms;
		};

		add_filter( 'terms_pre_query', $suppress, 10, 2 );

		$term_id = Series::get_instance()->join( $origin_id, $forward );

		remove_filter( 'terms_pre_query', $suppress, 10 );

		$this->assertSame(
			0,
			$term_id,
			'A forward relationship write that fails must report zero so the split rolls back.'
		);

		Series::get_instance()->flush_memo();

		$this->assertSame(
			array( $forward ),
			Series::get_instance()->resolve_post_ids( $forward ),
			'The forward post must not resolve into a series it was never actually attached to.'
		);
	}

	/**
	 * Direct coverage for every return shape of the relationship write helper.
	 *
	 * Invoked directly because xdebug does not trace a protected helper called
	 * through a short same-class delegation from `join()` (see the "Extracted
	 * same-class helpers" rule in `AGENTS.md`). The three shapes are a landed
	 * write, an empty result after `wp_set_object_terms()` silently skips a
	 * numeric term it cannot confirm, and a `WP_Error` from an unregistered
	 * taxonomy.
	 *
	 * @covers ::set_series_term
	 *
	 * @return void
	 */
	public function test_set_series_term_reports_whether_the_relationship_write_landed(): void {
		$post_id  = $this->create_and_project();
		$instance = Series::get_instance();

		Series::register_taxonomy_for( Event::POST_TYPE );

		$term = wp_insert_term( 'series-probe', Series::TAXONOMY );

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$instance,
				'set_series_term',
				array( $post_id, (int) $term['term_id'] )
			),
			'A relationship write against a real term must report success.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'set_series_term',
				array( $post_id, 999999 )
			),
			'A numeric term wp_set_object_terms() skipped must report failure, not success.'
		);

		$this->forget_series_taxonomy();

		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'set_series_term',
				array( $post_id, (int) $term['term_id'] )
			),
			'A WP_Error from the relationship write must report failure.'
		);
	}

	/**
	 * Membership resolution survives a term whose relationship rows are missing.
	 *
	 * @covers ::resolve_from_taxonomy
	 *
	 * @return void
	 */
	public function test_resolution_always_includes_the_resolving_post(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];

		wp_delete_object_term_relationships( $forward, Series::TAXONOMY );
		clean_object_term_cache( $origin_id, Event::POST_TYPE );
		Series::get_instance()->flush_memo();

		$this->assertSame(
			array( $origin_id ),
			Utility::invoke_hidden_method( Series::get_instance(), 'resolve_from_taxonomy', array( $origin_id ) ),
			'Failed to assert the resolving post is always a member of its own series.'
		);
	}

	/**
	 * Capture the SQL an event archive request runs, and how many events it looped.
	 *
	 * The archive is addressed as `?post_type=gatherpress_event` rather than
	 * through `get_post_type_archive_link()`. The pretty `/event/` form that
	 * helper returns depends on the permalink structure and on the rewrite rules
	 * another test class in the same process may have flushed: run in isolation
	 * it resolves as a **page** lookup, the query captured is
	 * `post_type IN ( 'page', 'attachment' )`, the loop runs zero times, and the
	 * two captures come back byte-identical because no event was ever loaded.
	 * The falsifiability control then has nothing to compare and the whole test
	 * passes on suite ordering. The query-string form resolves to the post type
	 * archive with no rewrite involved.
	 *
	 * The loop count is returned rather than asserted here so the caller can
	 * state the requirement in its own terms: the difference this test measures
	 * is emitted by `update_object_term_cache()`, which only runs when the loop
	 * actually loads an event.
	 *
	 * @since 0.36.0
	 *
	 * @return array{queries: string[], events: int} The captured statements and the number of posts looped.
	 */
	protected function capture_archive_sql(): array {
		global $wpdb;

		$url = add_query_arg( 'post_type', Event::POST_TYPE, home_url( '/' ) );

		$this->go_to( $url );

		while ( have_posts() ) {
			the_post();
		}

		wp_reset_postdata();

		// The measured pass has to run against a cold object cache. Term caches
		// are exactly what the registered-taxonomy list feeds, so a warm cache
		// would serve the second capture from the first capture's primed
		// entries and hide the difference this test exists to measure.
		wp_cache_flush();

		$previous_queries   = $wpdb->queries;
		$previous_save      = $wpdb->save_queries;
		$wpdb->queries      = array();
		$wpdb->save_queries = true;
		$events             = 0;

		$this->go_to( $url );

		while ( have_posts() ) {
			the_post();

			++$events;
		}

		wp_reset_postdata();

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

		return array(
			'queries' => $captured,
			'events'  => $events,
		);
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Series::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 11,
				'callback' => array( $instance, 'register' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'before_delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'remember_series_term' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'after_delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_delete_orphan_term' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'created_' . Series::TAXONOMY,
				'priority' => 10,
				'callback' => array( $instance, 'refresh_has_split_series' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'delete_' . Series::TAXONOMY,
				'priority' => 10,
				'callback' => array( $instance, 'refresh_has_split_series' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The first split records that the site has a split series.
	 *
	 * The flag is what lets the series taxonomy register and resolve on a
	 * later request even after every fragment has demoted to a plain event
	 * and the recurring flag has gone back to `'0'`. It is recomputed from
	 * term storage on the term lifecycle, never incremented.
	 *
	 * The option name is asserted literally rather than through a class
	 * constant, because the stored name is a site contract: renaming the
	 * constant must break this test.
	 *
	 * @covers ::join
	 * @covers ::refresh_has_split_series
	 *
	 * @return void
	 */
	public function test_the_first_split_records_the_split_series_flag(): void {
		$origin_id = $this->create_and_project();

		$this->assertNotSame(
			'1',
			get_option( 'gatherpress_has_split_series' ),
			'Fixture setup: a site that has never split must not report a split series.'
		);

		$forward = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );
		$this->assertSame(
			'1',
			get_option( 'gatherpress_has_split_series' ),
			'The first series term must record that the site has a split series, independently of the'
				. ' recurring rule flag.'
		);
	}

	/**
	 * Deleting the last fragment clears the split-series flag with the term.
	 *
	 * The clearing point is the term deletion the last fragment's hard delete
	 * performs, so the two stay wired together: a site that deletes its whole
	 * split series returns to the byte-identical SQL of a site that never
	 * split. The control half matters as much: one fragment's deletion keeps
	 * the flag, because the surviving fragment still resolves through the
	 * term.
	 *
	 * @covers ::maybe_delete_orphan_term
	 * @covers ::refresh_has_split_series
	 *
	 * @return void
	 */
	public function test_deleting_the_last_fragment_clears_the_split_series_flag(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );

		wp_delete_post( $origin_id, true );

		$this->assertSame(
			'1',
			get_option( 'gatherpress_has_split_series' ),
			'Deleting one fragment must keep the flag: the surviving fragment still resolves through the term.'
		);

		wp_delete_post( $forward, true );

		$this->assertSame(
			'0',
			get_option( 'gatherpress_has_split_series' ),
			'Deleting the last fragment must clear the flag with the term, recomputed from storage rather'
				. ' than decremented.'
		);
	}

	/**
	 * The split-series flag autoloads only while it is on.
	 *
	 * `site_has_split_series()` reads the alloptions cache, so `'1'` must ride
	 * it: that is the fast path every request on a split site takes. `'0'`
	 * must not: the row only exists on a site that split and then deleted
	 * every fragment, and autoloading it would make that site pay one
	 * alloptions row per request forever, one row worse off than the
	 * never-split site the design optimizes for, when absent and `'0'` are
	 * the same answer. The row is kept rather than deleted, because deleting
	 * it would hand the next `get_option()` read of the missing option a
	 * `wp_options` SELECT on exactly the surface promised byte-identical SQL.
	 *
	 * Both assertions reload the alloptions cache from storage first, so they
	 * measure what a fresh request would find rather than what this request's
	 * cache still holds.
	 *
	 * @covers ::refresh_has_split_series
	 * @covers ::site_has_split_series
	 *
	 * @return void
	 */
	public function test_the_split_series_flag_autoloads_only_while_on(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );

		wp_cache_delete( 'alloptions', 'options' );

		$this->assertArrayHasKey(
			Series::HAS_SPLIT_SERIES_OPTION,
			wp_load_alloptions(),
			'While on, the flag must autoload so site_has_split_series() stays on the alloptions fast path.'
		);
		$this->assertTrue(
			Series::site_has_split_series(),
			'The autoloaded row must be the one the alloptions read reports.'
		);

		wp_delete_post( $origin_id, true );
		wp_delete_post( $forward, true );

		$this->assertSame(
			'0',
			get_option( Series::HAS_SPLIT_SERIES_OPTION ),
			'Fixture setup: the row must survive the recompute as \'0\' rather than being deleted.'
		);

		wp_cache_delete( 'alloptions', 'options' );

		$this->assertArrayNotHasKey(
			Series::HAS_SPLIT_SERIES_OPTION,
			wp_load_alloptions(),
			'While off, the flag must not autoload: an autoloaded \'0\' row costs every request what an'
				. ' absent row answers for free.'
		);
		$this->assertFalse(
			Series::site_has_split_series(),
			'An off flag outside the alloptions cache must still read as off, absent and \'0\' being the'
				. ' same answer.'
		);
	}

	/**
	 * A site with neither flag adds not one query on the widened entry points.
	 *
	 * The split-series flag widened four read paths: taxonomy registration,
	 * series resolution, the calendar origin lookup and the export's
	 * component set. On a site with no recurring events and no split series,
	 * every one of them must stay free of SQL, including the flag read
	 * itself: the option does not exist on such a site, and a `get_option()`
	 * read of a missing option costs one `wp_options` SELECT per request,
	 * which is exactly the byte-identical-SQL surface being protected. The
	 * capture opens after the alloptions load every real request performs and
	 * after the fixtures are cached, so anything it observes is a query the
	 * widened entry points themselves added.
	 *
	 * The control at the end proves the capture can observe queries at all;
	 * without it, a capture broken by a `SAVEQUERIES` regression would pass
	 * the emptiness assertion vacuously.
	 *
	 * @covers ::register
	 * @covers ::resolve_post_ids
	 * @covers ::site_has_split_series
	 * @covers ::refresh_has_split_series
	 * @covers \GatherPress\Core\Calendar\Setup::series_component_post_ids
	 *
	 * @return void
	 */
	public function test_a_site_with_neither_flag_adds_no_queries_on_the_widened_entry_points(): void {
		global $wpdb;

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		Recurrence_Query::refresh_has_recurring_events();
		delete_option( Series::HAS_SPLIT_SERIES_OPTION );

		$this->assertFalse(
			Recurrence_Query::site_has_recurring_events(),
			'Fixture setup: the site must have no recurring events.'
		);
		$this->assertFalse(
			Series::site_has_split_series(),
			'Fixture setup: the site must have no split series, with the option absent as on a real site'
				. ' that never split.'
		);

		// The calendar entry points read the queried post, so establish it and
		// warm every cache the fixtures need before the capture opens.
		$this->go_to( get_permalink( $post_id ) );

		$calendar = new Calendar( $post_id );

		wp_cache_flush();
		wp_load_alloptions();
		get_post( $post_id );
		get_post_meta( $post_id );

		$previous_queries   = $wpdb->queries;
		$previous_save      = $wpdb->save_queries;
		$wpdb->queries      = array();
		$wpdb->save_queries = true;

		Series::get_instance()->register( Event::POST_TYPE );
		Series::get_instance()->resolve_post_ids( $post_id );
		Utility::invoke_hidden_method( $calendar, 'series_origin_post_id', array( $post_id ) );
		Calendar_Setup::get_instance()->series_component_post_ids();

		$captured = wp_list_pluck( $wpdb->queries, 0 );

		// The falsifiability control: a recompute is a lifecycle write path
		// and must be the thing that queries, proving the capture observes.
		Series::refresh_has_split_series();

		$control = wp_list_pluck( $wpdb->queries, 0 );

		$wpdb->queries      = $previous_queries;
		$wpdb->save_queries = $previous_save;

		$this->assertSame(
			array(),
			$captured,
			'A site with neither flag must not pay one query on any widened entry point.'
		);
		$this->assertNotSame(
			array(),
			$control,
			'Failed to assert the capture observes queries at all; SAVEQUERIES must be on for the emptiness'
				. ' assertion to mean anything.'
		);
		$this->assertFalse(
			taxonomy_exists( Series::TAXONOMY ),
			'A site with neither flag must not register the series taxonomy.'
		);
	}

	/**
	 * The deletion capture ignores post types without event-date support.
	 *
	 * @covers ::remember_series_term
	 *
	 * @return void
	 */
	public function test_remember_series_term_ignores_unsupported_post_types(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => 'post' ) );
		$instance = Series::get_instance();

		$instance->remember_series_term( $post_id, get_post( $post_id ) );

		$this->assertSame(
			array(),
			Utility::get_hidden_property( $instance, 'terms_pending_deletion' ),
			'A post type without event-date support must never be remembered for term cleanup.'
		);
	}

	/**
	 * A supported post with no series term leaves nothing pending.
	 *
	 * The unsplit series, which is every series on a site that has never split
	 * anything: its deletion must not queue term work it has no term for.
	 *
	 * @covers ::remember_series_term
	 *
	 * @return void
	 */
	public function test_remember_series_term_skips_a_post_without_a_term(): void {
		$post_id  = $this->create_and_project();
		$instance = Series::get_instance();

		Series::register_taxonomy_for( Event::POST_TYPE );

		$instance->remember_series_term( $post_id, get_post( $post_id ) );

		$this->assertSame(
			array(),
			Utility::get_hidden_property( $instance, 'terms_pending_deletion' ),
			'An unsplit series has no term, so nothing must be remembered.'
		);
	}

	/**
	 * A deletion nothing was remembered for is a no-op.
	 *
	 * @covers ::maybe_delete_orphan_term
	 *
	 * @return void
	 */
	public function test_maybe_delete_orphan_term_ignores_an_unremembered_post(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];
		$term_id   = Series::get_instance()->term_id_for_post( $origin_id );

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );

		Series::get_instance()->maybe_delete_orphan_term( $origin_id );

		$this->assertInstanceOf(
			\WP_Term::class,
			get_term( $term_id, Series::TAXONOMY ),
			'A post the capture never remembered must not cost its live series the term.'
		);
	}

	/**
	 * A pending term whose taxonomy vanished mid-request is left alone.
	 *
	 * `get_objects_in_term()` answers a `WP_Error` for an unregistered
	 * taxonomy, and acting on that answer as though it were an empty
	 * membership would delete a term whose membership was never actually read.
	 *
	 * @covers ::maybe_delete_orphan_term
	 *
	 * @return void
	 */
	public function test_maybe_delete_orphan_term_leaves_the_term_on_a_membership_error(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];
		$term_id   = Series::get_instance()->term_id_for_post( $origin_id );
		$instance  = Series::get_instance();

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );

		Utility::set_and_get_hidden_property(
			$instance,
			'terms_pending_deletion',
			array( $origin_id => $term_id )
		);
		$this->forget_series_taxonomy();

		$instance->maybe_delete_orphan_term( $origin_id );

		Series::register_taxonomy_for( Event::POST_TYPE );

		$this->assertInstanceOf(
			\WP_Term::class,
			get_term( $term_id, Series::TAXONOMY ),
			'A membership read that errors must never be treated as an empty membership.'
		);
		$this->assertSame(
			array(),
			Utility::get_hidden_property( $instance, 'terms_pending_deletion' ),
			'The pending entry must be consumed even when the membership read errors.'
		);
	}

	/**
	 * Coverage for resolve_post_ids when no filter is registered.
	 *
	 * @covers ::resolve_post_ids
	 *
	 * @return void
	 */
	public function test_resolve_post_ids_returns_single_post(): void {
		$post_id  = $this->factory->post->create();
		$instance = Series::get_instance();

		$this->assertSame(
			array( $post_id ),
			$instance->resolve_post_ids( $post_id ),
			'Failed to assert that resolve_post_ids returns array( $post_id ) with no filter registered.'
		);
	}

	/**
	 * Coverage for resolve_post_ids when the gatherpress_series_post_ids filter is used.
	 *
	 * @covers ::resolve_post_ids
	 *
	 * @return void
	 */
	public function test_resolve_post_ids_is_filterable(): void {
		$post_id      = $this->factory->post->create();
		$companion_id = $this->factory->post->create();
		$instance     = Series::get_instance();
		$callback     = function ( array $post_ids, int $resolved_post_id ) use ( $post_id, $companion_id ) {
			$this->assertSame(
				array( $post_id ),
				$post_ids,
				'Failed to assert that the filter receives array( $post_id ) as its default value.'
			);
			$this->assertSame(
				$post_id,
				$resolved_post_id,
				'Failed to assert that the filter receives the resolved post ID as its second argument.'
			);

			return array( $post_id, $companion_id );
		};

		add_filter( 'gatherpress_series_post_ids', $callback, 10, 2 );

		$post_ids = $instance->resolve_post_ids( $post_id );

		remove_filter( 'gatherpress_series_post_ids', $callback, 10 );

		$this->assertSame(
			array( $post_id, $companion_id ),
			$post_ids,
			'Failed to assert that resolve_post_ids returns both post IDs when the filter adds a second one.'
		);
	}

	/**
	 * Every recurrence singleton declares a protected constructor of its own.
	 *
	 * The `Singleton` trait supplies only `get_instance()`, so a class that
	 * declares no constructor gets PHP's implicit public one and `new Foo()`
	 * quietly works, against both the project guidance and, for `Series`,
	 * its own docblock's claim of a protected constructor. Once later stack
	 * parts add state or hook registration, an independently constructed
	 * instance diverges from the singleton or duplicates hooks, and locking
	 * the constructor down after release changes a public contract.
	 *
	 * @covers ::__construct
	 * @covers \GatherPress\Core\Event\Recurrence\Context::__construct
	 * @covers \GatherPress\Core\Event\Recurrence\Rest_Api::__construct
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::__construct
	 *
	 * @return void
	 */
	public function test_recurrence_singletons_declare_protected_constructors(): void {
		$singletons = array(
			Context::class,
			Rest_Api::class,
			Rsvp_Occurrence::class,
			Series::class,
		);

		foreach ( $singletons as $class_name ) {
			$constructor = ( new ReflectionClass( $class_name ) )->getConstructor();

			$this->assertNotNull(
				$constructor,
				sprintf( 'Failed to assert that %s declares its own constructor.', $class_name )
			);
			$this->assertTrue(
				$constructor->isProtected(),
				sprintf( 'Failed to assert that the %s constructor is protected.', $class_name )
			);

			// Invoke the empty body directly so it is covered: the singleton
			// instance already exists by the time this suite runs, so nothing
			// else constructs one inside a test.
			$constructor->setAccessible( true );
			$constructor->invoke( $class_name::get_instance() );
		}
	}

	/**
	 * The public resolver keeps its self-membership guarantee across the filter seam.
	 *
	 * The committed `test_resolution_always_includes_the_resolving_post()` drives
	 * the protected, unfiltered helper, so it cannot catch a
	 * `gatherpress_series_post_ids` filter that strips the resolving post.
	 * The documented `@return` contract says the result always includes
	 * `$post_id`; a filtered set omitting it would make every occurrence query
	 * silently skip the post's own rows.
	 *
	 * @covers ::resolve_post_ids
	 *
	 * @return void
	 */
	public function test_a_filtered_resolution_still_includes_the_resolving_post(): void {
		$post_id        = $this->factory->post->create();
		$companion_id   = $this->factory->post->create();
		$only_companion = static function () use ( $companion_id ) {
			return array( $companion_id );
		};

		add_filter( 'gatherpress_series_post_ids', $only_companion );

		$post_ids = Series::get_instance()->resolve_post_ids( $post_id );

		remove_filter( 'gatherpress_series_post_ids', $only_companion );

		$this->assertSame(
			array( $post_id, $companion_id ),
			$post_ids,
			'Failed to assert the resolving post survives a filter that returned only its companion.'
		);
	}

	/**
	 * A filtered resolution is normalized rather than returned verbatim.
	 *
	 * Duplicates and numeric-string IDs are the shapes an integration most
	 * plausibly hands back. The consumers of this set build `series_post_id
	 * IN (…)` lists and compare members with `===`, so a string `'42'` or a
	 * repeated member is a latent defect in every one of them.
	 *
	 * @covers ::resolve_post_ids
	 *
	 * @return void
	 */
	public function test_a_filtered_resolution_is_normalized(): void {
		$post_id      = $this->factory->post->create();
		$companion_id = $this->factory->post->create();
		$messy        = static function () use ( $post_id, $companion_id ) {
			return array( (string) $companion_id, $companion_id, (string) $post_id, $post_id );
		};

		add_filter( 'gatherpress_series_post_ids', $messy );

		$post_ids = Series::get_instance()->resolve_post_ids( $post_id );

		remove_filter( 'gatherpress_series_post_ids', $messy );

		$this->assertSame(
			array( $post_id, $companion_id ),
			$post_ids,
			'Failed to assert filtered duplicates and numeric strings are cast, deduplicated and sorted.'
		);
	}
}
