<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Splitter.
 *
 * The fixture is deliberately chosen so retroactive and
 * forward produce **different** answers: a six-occurrence bi-weekly series split
 * at its third occurrence leaves two rows behind and moves four, where a
 * retroactive edit would leave all six on one post. A test split at the first or
 * the last occurrence would not tell the two apart.
 *
 * The anchor is pinned rather than relative on purpose: every assertion here
 * compares against a fixed expected value rather than against the clock.
 * Every assertion here is rule + anchor -> occurrence set and row ownership,
 * a stated specification compared against nothing that moves. Nothing in this
 * file reads `current_time()`, so advancing the clock cannot change an expected
 * value.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Calendar\Setup as Calendar_Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Event\Recurrence\Rule;
use GatherPress\Core\Event\Recurrence\Series;
use GatherPress\Core\Event\Recurrence\Splitter;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use ReflectionClass;
use WP_Error;

/**
 * Class Test_Splitter.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Splitter
 */
class Test_Splitter extends Base {

	use Occurrence_Fixtures;

	/**
	 * The reference bi-weekly rule, widened to six occurrences.
	 *
	 * Six rather than the shared fixture's five, so a split at occurrence 3
	 * leaves two behind and moves four. The two sides are asymmetric, neither
	 * is one row, and neither is the whole series.
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
	 * The full occurrence set `SIX_WEEK_RULE` produces from the reference anchor.
	 *
	 * Stated rather than recomputed: the WKST week buckets are 0, 2, 2, 4, 4, 6
	 * against a Monday-start week, and a day-delta walk produces a different
	 * list. Asserting the list is asserting that.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	const FULL_SET = array(
		'20260903T180000',
		'20260915T180000',
		'20260917T180000',
		'20260929T180000',
		'20261001T180000',
		'20261013T180000',
	);

	/**
	 * Start every test from an empty occurrence table, with no context left
	 * over from another test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		Context::get_instance()->clear();
		Context::flush_resolved();
		Series::get_instance()->flush_memo();
	}

	/**
	 * Leave no occurrence context behind for the next test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Context::get_instance()->clear();
		Context::flush_resolved();
		Series::get_instance()->flush_memo();

		parent::tearDown();
	}

	/**
	 * Create the six-occurrence reference series, project it, and flag the site.
	 *
	 * Production order: the datetime blob and its derived row, then the
	 * recurrence blob, then the mirrors, then the projection, then the flag
	 * recomputed from storage.
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
	 * Read one post's occurrence identifiers, ordered ascending by start.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post to read.
	 *
	 * @return string[] The identifiers.
	 */
	protected function identifiers_for( int $post_id ): array {
		return array_map(
			'strval',
			wp_list_pluck( Occurrences::get_instance()->select_for_series( array( $post_id ) ), 'recurrence_id' )
		);
	}

	/**
	 * Read the whole per-occurrence RSVP vector across a set of posts.
	 *
	 * Keyed by recurrence ID, valued by the sorted comment IDs attached to the
	 * occurrence, resolved from each comment's own `_gatherpress_occurrence`
	 * term rather than from the request, so the vector says which occurrence
	 * each RSVP actually believes it belongs to.
	 *
	 * @since 0.36.0
	 *
	 * @param int[] $comment_ids RSVP comment IDs to place.
	 *
	 * @return array<string, array{post: int, comments: int[]}> The vector.
	 */
	protected function rsvp_vector( array $comment_ids ): array {
		$vector = array();

		foreach ( $comment_ids as $comment_id ) {
			$occurrence = Rsvp_Occurrence::occurrence_for_comment( $comment_id );

			if ( null === $occurrence ) {
				continue;
			}

			$key = $occurrence['recurrence_id'];

			if ( ! isset( $vector[ $key ] ) ) {
				$vector[ $key ] = array(
					'post'     => $occurrence['series_post_id'],
					'comments' => array(),
				);
			}

			$vector[ $key ]['comments'][] = (int) $comment_id;
		}

		ksort( $vector );

		foreach ( $vector as $key => $entry ) {
			sort( $entry['comments'] );

			$vector[ $key ] = $entry;
		}

		return $vector;
	}

	/**
	 * Save an RSVP while the request is rendering one occurrence.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence to enter before saving.
	 *
	 * @return int The RSVP comment ID.
	 */
	protected function rsvp_on( int $post_id, string $recurrence_id ): int {
		$user_id = $this->factory->user->create();

		Context::get_instance()->set( $post_id, $recurrence_id );

		$saved = ( new Rsvp( $post_id ) )->save( $user_id, 'attending' );

		Context::get_instance()->clear();

		return (int) $saved['comment_id'];
	}

	/**
	 * The constructor is empty but not pointless.
	 *
	 * `Traits\Singleton` declares no constructor, so deleting this one hands the
	 * class PHP's implicit **public** one and `new Splitter()` becomes legal,
	 * allowing two instances of a singleton, which is the one thing
	 * `get_instance()` exists to prevent.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_the_constructor_stays_protected(): void {
		// The singleton is built once per process, before any test runs, so the
		// constructor body is invoked directly here, which is the documented way
		// to trace it from the test that is actually about it.
		Utility::invoke_hidden_method( Splitter::get_instance(), '__construct' );

		$this->assertTrue(
			( new ReflectionClass( Splitter::class ) )->getConstructor()->isProtected(),
			'Failed to assert the constructor stays protected so get_instance() is the only way to build one.'
		);
	}

	/**
	 * A forward split at occurrence 3 of 6 leaves 1-2 behind and moves 3-6.
	 *
	 * The total is unchanged, which is what separates a move from a
	 * delete-and-regenerate that happens to produce the right dates.
	 *
	 * @covers ::split_forward
	 * @covers ::split_owned_series
	 * @covers ::apply_capped_rule
	 * @covers ::apply_forward_rule
	 * @covers ::create_forward_post
	 * @covers ::copy_meta
	 * @covers ::copy_terms
	 * @covers ::write_rule
	 *
	 * @return void
	 */
	public function test_split_at_third_occurrence_partitions_the_series(): void {
		$origin_id = $this->create_and_project();

		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'Failed to assert the fixture produced the stated six-occurrence set before the split.'
		);

		$result = Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' );

		$this->assertTrue(
			$result['split'],
			'Failed to assert a split at the third of six occurrences actually split.'
		);

		$forward_id = (int) $result['forward_post_id'];

		$this->assertSame(
			array( '20260903T180000', '20260915T180000' ),
			$this->identifiers_for( $origin_id ),
			'Failed to assert occurrences 1-2 stayed on the original post.'
		);
		$this->assertSame(
			array( '20260917T180000', '20260929T180000', '20261001T180000', '20261013T180000' ),
			$this->identifiers_for( $forward_id ),
			'Failed to assert occurrences 3-6 moved to the forward post.'
		);
		$this->assertSame(
			6,
			count( $this->identifiers_for( $origin_id ) ) + count( $this->identifiers_for( $forward_id ) ),
			'Failed to assert the total occurrence count is unchanged by the split.'
		);
		$this->assertSame(
			4,
			$result['moved'],
			'Failed to assert the split reported the four rows it moved.'
		);
	}

	/**
	 * The origin is capped by COUNT and the forward post carries the remainder.
	 *
	 * @covers ::apply_capped_rule
	 * @covers ::apply_forward_rule
	 *
	 * @return void
	 */
	public function test_split_caps_the_origin_rule_and_carries_the_remainder_forward(): void {
		$origin_id = $this->create_and_project();
		$result    = Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' );
		$forward   = (int) $result['forward_post_id'];

		$this->assertSame(
			array(
				'frequency'       => 'weekly',
				'interval'        => 2,
				'weekdays'        => array( 2, 4 ),
				'monthly_mode'    => '',
				'monthly_day'     => 0,
				'monthly_ordinal' => 0,
				'monthly_weekday' => 0,
				'end_type'        => 'count',
				'until'           => '',
				'count'           => 2,
			),
			Rule::from_post( $origin_id )->to_array(),
			'Failed to assert the origin rule is capped at the two occurrences left behind.'
		);
		$this->assertSame(
			4,
			Rule::from_post( $forward )->count(),
			'Failed to assert the forward rule carries the remaining four occurrences.'
		);
		$this->assertSame(
			'2026-09-17 18:00:00',
			(string) get_post_meta( $forward, 'gatherpress_datetime_start', true ),
			'Failed to assert the forward post is anchored at the occurrence the split happened from.'
		);
	}

	/**
	 * Occurrence rows are moved, not deleted and regenerated.
	 *
	 * Two things a regeneration could not preserve are asserted together: a
	 * canceled occurrence stays canceled, and the RSVP term that
	 * names it keeps its `term_taxonomy_id`, the column every
	 * `term_relationships` row keys on, so no RSVP relationship was rewritten.
	 *
	 * @covers ::split_owned_series
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::move_to_post
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::migrate_owner
	 *
	 * @return void
	 */
	public function test_split_recycles_occurrence_rows_rather_than_regenerating_them(): void {
		$origin_id = $this->create_and_project();

		Occurrences::get_instance()->set_status( $origin_id, '20260929T180000', Occurrences::STATUS_CANCELED );

		$comment_id = $this->rsvp_on( $origin_id, '20261001T180000' );
		$before     = get_term_by(
			'slug',
			Rsvp_Occurrence::term_slug( $origin_id, '20261001T180000' ),
			Rsvp_Occurrence::TAXONOMY
		);

		$this->assertNotFalse( $before, 'Failed to assert the RSVP created an occurrence term to move.' );

		$result  = Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' );
		$forward = (int) $result['forward_post_id'];

		$moved = Occurrences::get_instance()->get( $forward, '20260929T180000' );

		$this->assertSame(
			Occurrences::STATUS_CANCELED,
			(string) $moved['status'],
			'Failed to assert a canceled occurrence stayed canceled across the split.'
		);
		$this->assertSame(
			'2026-09-29 18:00:00',
			(string) $moved['datetime_start'],
			'Failed to assert the moved row kept its own datetime rather than being rebuilt from a new anchor.'
		);

		$after = get_term_by(
			'slug',
			Rsvp_Occurrence::term_slug( $forward, '20261001T180000' ),
			Rsvp_Occurrence::TAXONOMY
		);

		$this->assertNotFalse( $after, 'Failed to assert the RSVP occurrence term now names the forward post.' );
		$this->assertSame(
			(int) $before->term_taxonomy_id,
			(int) $after->term_taxonomy_id,
			'Failed to assert the occurrence term was renamed rather than re-tagged. A new'
				. ' term_taxonomy_id means every RSVP relationship row was rewritten.'
		);
		$this->assertSame(
			array(
				'series_post_id' => $forward,
				'recurrence_id'  => '20261001T180000',
			),
			Rsvp_Occurrence::occurrence_for_comment( $comment_id ),
			'Failed to assert the RSVP still resolves to its own date, now on the forward post.'
		);
	}

	/**
	 * RSVPs on both sides of a split stay attached to their own dates.
	 *
	 * Asserts the whole per-occurrence vector rather than one row: a split that
	 * moved every RSVP onto the first forward occurrence, or that dropped the
	 * ones on the origin side, would satisfy any single-row assertion.
	 *
	 * @covers ::split_owned_series
	 *
	 * @return void
	 */
	public function test_rsvp_vector_survives_the_split_on_both_sides(): void {
		$origin_id = $this->create_and_project();

		$stays_first  = $this->rsvp_on( $origin_id, '20260903T180000' );
		$stays_second = $this->rsvp_on( $origin_id, '20260915T180000' );
		$moves_third  = $this->rsvp_on( $origin_id, '20260917T180000' );
		$moves_fifth  = $this->rsvp_on( $origin_id, '20261001T180000' );
		$also_fifth   = $this->rsvp_on( $origin_id, '20261001T180000' );

		$comments = array( $stays_first, $stays_second, $moves_third, $moves_fifth, $also_fifth );

		$result  = Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' );
		$forward = (int) $result['forward_post_id'];

		$expected = array(
			'20260903T180000' => array(
				'post'     => $origin_id,
				'comments' => array( $stays_first ),
			),
			'20260915T180000' => array(
				'post'     => $origin_id,
				'comments' => array( $stays_second ),
			),
			'20260917T180000' => array(
				'post'     => $forward,
				'comments' => array( $moves_third ),
			),
			'20261001T180000' => array(
				'post'     => $forward,
				'comments' => array( min( $moves_fifth, $also_fifth ), max( $moves_fifth, $also_fifth ) ),
			),
		);

		ksort( $expected );

		$this->assertSame(
			$expected,
			$this->rsvp_vector( $comments ),
			'Failed to assert every RSVP remains attached to its own date, on the side of the split that owns it.'
		);
		$this->assertSame(
			2,
			$result['renamed_rsvp_terms'],
			'Failed to assert the split renamed exactly the two occurrence terms whose occurrences moved.'
		);
	}

	/**
	 * Forward from the first occurrence degrades to retroactive.
	 *
	 * Nothing is created: no second post, no series term, no moved row. A split
	 * here would leave the original holding nothing.
	 *
	 * @covers ::split_forward
	 * @covers ::split_owned_series
	 * @covers ::result
	 *
	 * @return void
	 */
	public function test_forward_from_the_first_occurrence_does_not_split(): void {
		$origin_id = $this->create_and_project();
		$before    = count(
			get_posts(
				array(
					'post_type'   => Event::POST_TYPE,
					'fields'      => 'ids',
					'numberposts' => -1,
				)
			)
		);

		$result = Splitter::get_instance()->split_forward( $origin_id, '20260903T180000' );

		$this->assertFalse( $result['split'], 'Failed to assert no split happens from the first occurrence.' );
		$this->assertSame(
			'first_occurrence',
			$result['reason'],
			'Failed to assert the degradation names the reason it degraded.'
		);
		$this->assertSame(
			0,
			$result['forward_post_id'],
			'Failed to assert no forward post was created.'
		);
		$this->assertSame(
			$before,
			count(
				get_posts(
					array(
						'post_type'   => Event::POST_TYPE,
						'fields'      => 'ids',
						'numberposts' => -1,
					)
				)
			),
			'Failed to assert the post count is unchanged. A second event post is the cost this degradation avoids.'
		);
		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'Failed to assert the whole series stayed on the original post.'
		);
		$this->assertSame(
			array( $origin_id ),
			Series::get_instance()->resolve_post_ids( $origin_id ),
			'Failed to assert a degraded split records no series relationship.'
		);
	}

	/**
	 * The first date of an already-split fragment is refused under its own reason.
	 *
	 * Index 0 is index 0 **of the owning post**, and once a series has been
	 * split the panel's chooser spans every fragment, so the first date of a
	 * fragment reaches the same guard as the first date of a series that has
	 * never been split. The two are not the same thing: this one leaves the
	 * rest of the series on a sibling post that the refusal does not touch. The
	 * panel renders the reason verbatim, so one name for both had it tell the
	 * organizer the change applied to dates it does not reach.
	 *
	 * Both fragments are asserted, because the discriminator is whether the
	 * post is the whole series and not whether the date is the earliest one.
	 *
	 * @covers ::split_owned_series
	 * @covers ::result
	 *
	 * @return void
	 */
	public function test_the_first_date_of_a_fragment_is_refused_under_its_own_reason(): void {
		$origin_id = $this->create_and_project();
		$first     = Splitter::get_instance()->split_forward( $origin_id, self::FULL_SET[2] );

		$this->assertTrue( $first['split'], 'Fixture setup: the series must be split once first.' );

		$forward_id = (int) $first['forward_post_id'];

		$this->assertSame(
			array( '20260917T180000', '20260929T180000', '20261001T180000', '20261013T180000' ),
			$this->identifiers_for( $forward_id ),
			'Fixture setup: the forward fragment must own the later dates.'
		);

		$forward_refusal = Splitter::get_instance()->split_forward( $forward_id, self::FULL_SET[2] );
		$origin_refusal  = Splitter::get_instance()->split_forward( $origin_id, self::FULL_SET[0] );

		$this->assertSame(
			array( false, 'fragment_first_occurrence' ),
			array( $forward_refusal['split'], $forward_refusal['reason'] ),
			'Failed to assert the later fragment\'s own first date is refused as a fragment,'
				. ' not as the first date of the whole series.'
		);
		$this->assertSame(
			array( false, 'fragment_first_occurrence' ),
			array( $origin_refusal['split'], $origin_refusal['reason'] ),
			'Failed to assert the earliest date of a split series is a fragment too: the post no longer'
				. ' holds the series, so a retroactive edit on it cannot reach the dates that moved.'
		);
		$this->assertSame(
			self::FULL_SET,
			array_merge( $this->identifiers_for( $origin_id ), $this->identifiers_for( $forward_id ) ),
			'Failed to assert neither refusal moved a row.'
		);
	}

	/**
	 * Every phase of a split names the split's own pair of posts.
	 *
	 * `gatherpress_split_phase_complete` is a published extension point whose
	 * third argument is documented as the post the split created. Two phases
	 * act on the origin alone, and reporting the post they happen to write
	 * hands a listener the origin twice under a parameter named for the forward
	 * post. An integration keyed on `origin_rule` or `advance_revision` would
	 * then tag, notify or edit the wrong event, and nothing in the payload
	 * would tell it so.
	 *
	 * The assertion is the whole ordered map rather than the two arguments,
	 * which also states that each phase fires exactly once and in the order the
	 * rollback stack unwinds.
	 *
	 * @covers ::phase
	 * @covers ::rule_phase
	 * @covers ::advance_revision
	 * @covers ::run_phases
	 *
	 * @return void
	 */
	public function test_every_phase_reports_the_post_the_split_created(): void {
		$origin_id = $this->create_and_project();
		$reported  = array();
		$observe   = static function ( $outcome, string $phase, int $origin, int $forward ) use ( &$reported ) {
			$reported[ $phase ] = array( $origin, $forward );

			return $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $observe, 10, 4 );

		$result = Splitter::get_instance()->split_forward( $origin_id, self::FULL_SET[2] );

		remove_filter( 'gatherpress_split_phase_complete', $observe, 10 );

		$this->assertTrue( $result['split'], 'Fixture setup: the split must succeed.' );

		$pair = array( $origin_id, (int) $result['forward_post_id'] );

		$this->assertSame(
			array(
				'create_forward_post' => $pair,
				'move_occurrences'    => $pair,
				'migrate_rsvps'       => $pair,
				'join_series'         => $pair,
				'forward_rule'        => $pair,
				'origin_rule'         => $pair,
				'verify_partition'    => $pair,
				'advance_revision'    => $pair,
			),
			$reported,
			'Failed to assert every phase reported the origin and the post the split created, in that order.'
		);
	}

	/**
	 * A split projects each rewritten rule once, and leaves no shutdown re-projection queued.
	 *
	 * The splitter writes each side's rule blob and projects immediately, in
	 * the save-path order, so the REST response reports the state it
	 * produced. The blob write itself also queues a deferred reconciliation
	 * for `shutdown`, the safety net for writers that never project, and
	 * that queue entry must be consumed by the immediate projection the same
	 * way the ordinary editor save's is. Left queued, every split re-ran
	 * both projections at shutdown: idempotent, but a full expand-and-upsert
	 * of both fragments paid for nothing.
	 *
	 * @covers ::split_forward
	 * @covers ::write_rule
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::project
	 *
	 * @return void
	 */
	public function test_a_split_projects_each_side_once_with_no_shutdown_extra(): void {
		global $wpdb;

		$origin_id = $this->create_and_project();

		// The queue lives on a singleton that outlives one test. Any earlier
		// suite whose fixtures wrote a recurrence blob without projecting
		// leaves its own posts queued here, and both assertions below read the
		// whole queue: the first would report a foreign post ID and the second
		// would count a foreign post's shutdown projection as this split's.
		// Emptying it first makes what this test observes its own doing.
		Utility::set_and_get_hidden_property( Occurrences::get_instance(), 'pending_projection', array() );

		$table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$inserts = array();
		$capture = static function ( $query ) use ( &$inserts, $table ) {
			if ( str_starts_with( $query, 'INSERT' ) && str_contains( $query, $table ) ) {
				$inserts[] = $query;
			}

			return $query;
		};

		add_filter( 'query', $capture );

		$result = Splitter::get_instance()->split_forward( $origin_id, self::FULL_SET[2] );

		$this->assertTrue( $result['split'], 'Fixture setup: the split must succeed.' );
		$this->assertCount(
			2,
			$inserts,
			'A split writes occurrence rows exactly twice: the forward rule projection and the origin cap projection.'
		);
		$this->assertSame(
			array(),
			Utility::get_hidden_property( Occurrences::get_instance(), 'pending_projection' ),
			'The deliberate projections must consume the reconciliations their own blob writes queued.'
		);

		// The shutdown pass must find nothing left to do: no entry, no
		// expand, no write.
		Occurrences::get_instance()->resolve_pending_projection();

		remove_filter( 'query', $capture );

		$this->assertCount(
			2,
			$inserts,
			'The shutdown reconciliation pass must not re-project a split the request already projected.'
		);
	}

	/**
	 * Create and project a daily series longer than `Rule::MAX_COUNT` rows.
	 *
	 * A never-ending daily rule under a widened projection horizon, the shape
	 * a long-lived daily stand-up reaches in production after two years of
	 * horizon extensions. The horizon filter is installed for the projection
	 * and removed again, so re-projections inside a split see the same
	 * horizon the fixture was built under.
	 *
	 * @since 0.36.0
	 *
	 * @return array{0: int, 1: string[], 2: callable} The post ID, its ordered
	 *               identifiers, and the horizon filter to remove.
	 */
	protected function create_series_longer_than_max_count(): array {
		$horizon = static function (): int {
			return 26;
		};

		add_filter( 'gatherpress_recurrence_horizon_months', $horizon );

		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );
		Recurrence_Query::refresh_has_recurring_events();

		$identifiers = $this->identifiers_for( $post_id );

		$this->assertGreaterThan(
			Rule::MAX_COUNT + 5,
			count( $identifiers ),
			'Fixture setup: the series must outgrow the maximum rule count.'
		);

		return array( $post_id, $identifiers, $horizon );
	}

	/**
	 * A split past the maximum rule count is refused before any durable phase.
	 *
	 * Capping the origin writes `COUNT = index`, and `Rule` refuses any count
	 * above `Rule::MAX_COUNT`, so a split past that occurrence used to run
	 * every phase, fail the partition check against the rule the origin could
	 * not re-project, undo everything, and answer an opaque 500. The refusal
	 * must instead come first, name the real limit, and leave the series
	 * untouched without a single phase having run.
	 *
	 * @covers ::split_forward
	 * @covers ::split_owned_series
	 *
	 * @return void
	 */
	public function test_a_split_past_the_maximum_rule_count_is_refused_before_any_phase(): void {
		list( $post_id, $identifiers, $horizon ) = $this->create_series_longer_than_max_count();

		$phases  = array();
		$observe = static function ( $outcome, string $phase ) use ( &$phases ) {
			$phases[] = $phase;

			return $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $observe, 10, 2 );

		$result = Splitter::get_instance()->split_forward( $post_id, $identifiers[ Rule::MAX_COUNT + 5 ] );

		remove_filter( 'gatherpress_split_phase_complete', $observe, 10 );
		remove_filter( 'gatherpress_recurrence_horizon_months', $horizon );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert the overlong split is refused rather than attempted.'
		);
		$this->assertSame(
			'gatherpress_split_too_long',
			$result->get_error_code(),
			'Failed to assert the refusal names the real limit rather than a generic failure.'
		);
		$this->assertSame(
			array( 'status' => 400 ),
			$result->get_error_data(),
			'Failed to assert the refusal reports a client error, not a server fault.'
		);
		$this->assertSame(
			array(),
			$phases,
			'Failed to assert the refusal came before any durable phase: nothing to do, nothing to undo.'
		);
		$this->assertSame(
			$identifiers,
			$this->identifiers_for( $post_id ),
			'Failed to assert the origin keeps every row it had.'
		);
		$this->assertSame(
			Rule::END_TYPE_NEVER,
			Rule::from_post( $post_id )->end_type(),
			'Failed to assert the origin rule was never rewritten.'
		);
		$this->assertSame(
			array( $post_id ),
			Series::get_instance()->resolve_post_ids( $post_id ),
			'Failed to assert no forward post joined the series.'
		);
	}

	/**
	 * A split exactly at the maximum rule count still splits.
	 *
	 * The boundary of the refusal above: capping the origin at
	 * `Rule::MAX_COUNT` rows is a rule `Rule` accepts, so the split at that
	 * index must run, leave exactly that many rows behind, and move the rest.
	 *
	 * @covers ::split_forward
	 * @covers ::split_owned_series
	 * @covers ::apply_capped_rule
	 *
	 * @return void
	 */
	public function test_a_split_at_the_maximum_rule_count_boundary_still_splits(): void {
		list( $post_id, $identifiers, $horizon ) = $this->create_series_longer_than_max_count();

		$result = Splitter::get_instance()->split_forward( $post_id, $identifiers[ Rule::MAX_COUNT ] );

		remove_filter( 'gatherpress_recurrence_horizon_months', $horizon );

		$this->assertIsArray( $result, 'Failed to assert the boundary split is not refused.' );
		$this->assertTrue( $result['split'], 'Failed to assert the boundary split happened.' );
		$this->assertSame(
			array_slice( $identifiers, 0, Rule::MAX_COUNT ),
			$this->identifiers_for( $post_id ),
			'Failed to assert the origin keeps exactly the maximum count of rows.'
		);
		$this->assertSame(
			Rule::MAX_COUNT,
			Rule::from_post( $post_id )->count(),
			'Failed to assert the origin rule is capped exactly at the maximum.'
		);
		// At least, not exactly: the forward rule is open-ended and measures
		// its horizon from its own later anchor, so it legitimately projects
		// dates past where the origin had reached.
		$forward_owned = $this->identifiers_for( (int) $result['forward_post_id'] );

		$this->assertSame(
			array(),
			array_diff( array_slice( $identifiers, Rule::MAX_COUNT ), $forward_owned ),
			'Failed to assert the forward post owns everything past the boundary.'
		);
		$this->assertSame(
			$identifiers[ Rule::MAX_COUNT ],
			$forward_owned[0],
			'Failed to assert the forward post is anchored at the boundary occurrence.'
		);
	}

	/**
	 * Forward from the final occurrence produces a plain non-recurring event.
	 *
	 * The forward side holds exactly one date, never zero, and carries no
	 * recurrence rule, so it is a single-occurrence edit rather than a series of
	 * one.
	 *
	 * @covers ::apply_forward_rule
	 * @covers ::demote_to_plain_event
	 *
	 * @return void
	 */
	public function test_forward_from_the_final_occurrence_leaves_a_plain_event(): void {
		$origin_id = $this->create_and_project();

		$result  = Splitter::get_instance()->split_forward( $origin_id, '20261013T180000' );
		$forward = (int) $result['forward_post_id'];

		$this->assertTrue( $result['split'], 'Failed to assert the final occurrence still moves to its own post.' );
		$this->assertFalse(
			$result['forward_recurring'],
			'Failed to assert the forward side is reported as non-recurring.'
		);
		$this->assertNull(
			Rule::from_post( $forward ),
			'Failed to assert the forward post carries no recurrence rule.'
		);
		$this->assertSame(
			'',
			(string) get_post_meta( $forward, Meta::META_KEY, true ),
			'Failed to assert the recurrence blob was removed alongside its mirrors.'
		);
		$this->assertSame(
			array(),
			$this->identifiers_for( $forward ),
			'Failed to assert a plain non-recurring event holds no occurrence rows.'
		);
		$this->assertSame(
			'2026-10-13 18:00:00',
			(string) get_post_meta( $forward, 'gatherpress_datetime_start', true ),
			'Failed to assert the plain event kept the date the split was made from.'
		);
		$this->assertSame(
			array_slice( self::FULL_SET, 0, 5 ),
			$this->identifiers_for( $origin_id ),
			'Failed to assert the original keeps the five occurrences before the final one.'
		);
	}

	/**
	 * A split at the second occurrence leaves the origin as a plain event.
	 *
	 * The mirror image of the final-occurrence case, and the one that proves the
	 * "exactly one occurrence" rule is applied to whichever side ends up with
	 * one rather than to the forward side by construction.
	 *
	 * @covers ::apply_capped_rule
	 * @covers ::demote_to_plain_event
	 *
	 * @return void
	 */
	public function test_split_at_the_second_occurrence_leaves_the_origin_a_plain_event(): void {
		$origin_id = $this->create_and_project();

		$result  = Splitter::get_instance()->split_forward( $origin_id, '20260915T180000' );
		$forward = (int) $result['forward_post_id'];

		$this->assertFalse(
			$result['origin_recurring'],
			'Failed to assert the origin side is reported as non-recurring.'
		);
		$this->assertNull(
			Rule::from_post( $origin_id ),
			'Failed to assert the origin carries no recurrence rule once it holds one date.'
		);
		$this->assertSame(
			array(),
			$this->identifiers_for( $origin_id ),
			'Failed to assert the demoted origin holds no occurrence rows.'
		);
		$this->assertSame(
			'2026-09-03 18:00:00',
			(string) get_post_meta( $origin_id, 'gatherpress_datetime_start', true ),
			'Failed to assert the demoted origin kept its own single date.'
		);
		$this->assertSame(
			array_slice( self::FULL_SET, 1 ),
			$this->identifiers_for( $forward ),
			'Failed to assert the forward post carries the remaining five occurrences.'
		);
	}

	/**
	 * Demoting an RSVPd occurrence leaves its RSVPs readable on the plain event.
	 *
	 * The occurrence term is dropped rather than renamed, so the RSVP reads
	 * series-wide, which on a single-date event is that date. Nothing is moved
	 * and nothing is deleted.
	 *
	 * @covers ::demote_to_plain_event
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::detach_series
	 *
	 * @return void
	 */
	public function test_demotion_unscopes_rsvps_rather_than_stranding_them(): void {
		$origin_id  = $this->create_and_project();
		$comment_id = $this->rsvp_on( $origin_id, '20260903T180000' );

		Splitter::get_instance()->split_forward( $origin_id, '20260915T180000' );

		$this->assertNull(
			Rsvp_Occurrence::occurrence_for_comment( $comment_id ),
			'Failed to assert the demoted event\'s RSVP is no longer scoped to an occurrence.'
		);
		$this->assertSame(
			'attending',
			( new Rsvp( $origin_id ) )->get( (int) get_comment( $comment_id )->user_id )['status'],
			'Failed to assert the RSVP is still readable on the plain event it belongs to.'
		);
	}

	/**
	 * A canceled single occurrence is not demoted, because cancellation is occurrence state.
	 *
	 * A plain event has no place to record cancellation, so the side keeps a
	 * one-occurrence rule instead. Demoting would silently un-cancel a date the
	 * organizer canceled.
	 *
	 * @covers ::demote_to_plain_event
	 *
	 * @return void
	 */
	public function test_a_canceled_single_occurrence_keeps_its_rule(): void {
		$origin_id = $this->create_and_project();

		Occurrences::get_instance()->set_status( $origin_id, '20260903T180000', Occurrences::STATUS_CANCELED );

		$result = Splitter::get_instance()->split_forward( $origin_id, '20260915T180000' );

		$this->assertTrue(
			$result['origin_recurring'],
			'Failed to assert a canceled single occurrence is not demoted.'
		);
		$this->assertSame(
			1,
			Rule::from_post( $origin_id )->count(),
			'Failed to assert the origin keeps a one-occurrence rule.'
		);
		$this->assertSame(
			Occurrences::STATUS_CANCELED,
			(string) Occurrences::get_instance()->get( $origin_id, '20260903T180000' )['status'],
			'Failed to assert the cancellation survived. It is the reason the rule was kept.'
		);
	}

	/**
	 * A refused write inside the origin's canceled-occurrence fallback surfaces and rolls back.
	 *
	 * The demotion fallback for a canceled single occurrence writes a
	 * one-occurrence rule, and that write projects. A database that refuses
	 * the projection used to be ignored on this path too; the refusal must
	 * abort the split and restore the series. The seam breaks the second
	 * occurrence insert of the split, the fallback's own, so the forward
	 * side's projection has already succeeded.
	 *
	 * @covers ::demote_to_plain_event
	 * @covers ::apply_capped_rule
	 * @covers ::write_rule
	 *
	 * @return void
	 */
	public function test_a_refused_write_in_the_canceled_origin_fallback_rolls_the_split_back(): void {
		$origin_id = $this->create_and_project();

		Occurrences::get_instance()->set_status( $origin_id, self::FULL_SET[0], Occurrences::STATUS_CANCELED );

		$filter = $this->break_nth_occurrence_insert( 2 );
		$result = Splitter::get_instance()->split_forward( $origin_id, self::FULL_SET[1] );

		remove_filter( 'query', $filter );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert the refused fallback write aborts the split.'
		);
		$this->assertSame(
			'gatherpress_occurrence_write_failed',
			$result->get_error_code(),
			'Failed to assert the database refusal itself is what surfaces.'
		);
		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'Failed to assert the whole series is back on the origin.'
		);
		$this->assertSame(
			6,
			Rule::from_post( $origin_id )->count(),
			'Failed to assert the origin rule was restored uncapped.'
		);
		$this->assertSame(
			Occurrences::STATUS_CANCELED,
			(string) Occurrences::get_instance()->get( $origin_id, self::FULL_SET[0] )['status'],
			'Failed to assert the cancellation survived the rollback.'
		);
	}

	/**
	 * A refused write inside the forward post's canceled-occurrence fallback surfaces and rolls back.
	 *
	 * The forward-side twin of the test above: splitting at the final
	 * occurrence of a `COUNT` rule demotes the forward post, whose one
	 * occurrence is canceled, so the fallback writes a one-occurrence rule
	 * on the forward post. Its projection is the split's first occurrence
	 * insert, and refusing it must abort the split whole.
	 *
	 * @covers ::demote_to_plain_event
	 * @covers ::apply_forward_rule
	 * @covers ::write_rule
	 *
	 * @return void
	 */
	public function test_a_refused_write_in_the_canceled_forward_fallback_rolls_the_split_back(): void {
		$origin_id = $this->create_and_project();

		Occurrences::get_instance()->set_status( $origin_id, self::FULL_SET[5], Occurrences::STATUS_CANCELED );

		$filter = $this->break_nth_occurrence_insert( 1 );
		$result = Splitter::get_instance()->split_forward( $origin_id, self::FULL_SET[5] );

		remove_filter( 'query', $filter );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert the refused fallback write aborts the split.'
		);
		$this->assertSame(
			'gatherpress_occurrence_write_failed',
			$result->get_error_code(),
			'Failed to assert the database refusal itself is what surfaces.'
		);
		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'Failed to assert the whole series is back on the origin.'
		);
		$this->assertSame(
			array( $origin_id ),
			Series::get_instance()->resolve_post_ids( $origin_id ),
			'Failed to assert no forward post survived the rollback.'
		);
	}

	/**
	 * A rollback that completed adds nothing to the failure that caused it.
	 *
	 * The other return path of `report_rollback()`, reached on every rolled
	 * back split in this file and driven directly here because xdebug does not
	 * trace a same-class helper called from a short delegation. The whole
	 * point of the null arm is that an ordinary abort still reports exactly
	 * one failure, so a client sees the reason and nothing about consistency.
	 *
	 * @covers ::report_rollback
	 *
	 * @return void
	 */
	public function test_a_completed_rollback_adds_nothing_to_the_reported_failure(): void {
		$failure = new WP_Error( 'gatherpress_split_series_not_joined', 'Original failure.', array( 'status' => 500 ) );

		Utility::invoke_hidden_method( Splitter::get_instance(), 'report_rollback', array( $failure, null ) );

		$this->assertSame(
			array( 'gatherpress_split_series_not_joined' ),
			$failure->get_error_codes(),
			'Failed to assert a rollback that completed leaves the reported failure alone.'
		);
	}

	/**
	 * A failed rollback is appended under its own code, carrying the refusal that broke it.
	 *
	 * Driven directly, and with the *same* code on both failures, which is the
	 * case the split's own tests cannot reach: a database refusing every write
	 * makes the abort and the rollback both
	 * `gatherpress_occurrence_write_failed`, and reusing that code for the
	 * second entry would let `WP_Error::add()` fold it into the first and
	 * report a clean rollback. The distinct code is what survives that.
	 *
	 * @covers ::report_rollback
	 *
	 * @return void
	 */
	public function test_a_failed_rollback_is_reported_even_when_both_failures_share_a_code(): void {
		$failure  = new WP_Error( 'gatherpress_occurrence_write_failed', 'Abort.', array( 'status' => 500 ) );
		$rollback = new WP_Error( 'gatherpress_occurrence_write_failed', 'Rollback.', array( 'post_id' => 7 ) );

		Utility::invoke_hidden_method( Splitter::get_instance(), 'report_rollback', array( $failure, $rollback ) );

		$this->assertSame(
			array( 'gatherpress_occurrence_write_failed', 'gatherpress_split_rollback_failed' ),
			$failure->get_error_codes(),
			'Failed to assert a rollback failure sharing the abort\'s code is still reported separately.'
		);
		$this->assertSame(
			array(
				'status'              => 500,
				'rollback_error_code' => 'gatherpress_occurrence_write_failed',
				'rollback_error_data' => array( 'post_id' => 7 ),
			),
			$failure->get_error_data( 'gatherpress_split_rollback_failed' ),
			'Failed to assert the appended entry carries the rollback failure it stands for.'
		);
		$this->assertSame(
			array( 'status' => 500 ),
			$failure->get_error_data(),
			'Failed to assert the abort still owns the data a REST response reads.'
		);
	}

	/**
	 * A missing occurrence is refused rather than splitting something arbitrary.
	 *
	 * @covers ::split_forward
	 *
	 * @return void
	 */
	public function test_unknown_occurrence_is_refused(): void {
		$origin_id = $this->create_and_project();
		$result    = Splitter::get_instance()->split_forward( $origin_id, '20991231T235959' );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert an identifier belonging to no occurrence of this series is refused.'
		);
		$this->assertSame(
			'gatherpress_occurrence_not_found',
			$result->get_error_code(),
			'Failed to assert the refusal names the missing occurrence rather than a generic failure.'
		);
	}

	/**
	 * An occurrence whose post no longer carries a rule cannot be split forward.
	 *
	 * The fixture keeps the occurrence rows and removes only the rule, so
	 * resolution succeeds and the refusal has to come from the rule guard.
	 * Deleting the rows instead would take the 404 path and leave this branch
	 * unexercised while the test still went green.
	 *
	 * A second recurring series is created first, and it is load-bearing rather
	 * than scenery: removing the only rule on the site flips the
	 * has-recurring-events flag, and every occurrence lookup short-circuits on
	 * that flag. Without a second series the refusal would come from
	 * the flag, not from the guard this test is about.
	 *
	 * @covers ::split_forward
	 * @covers ::split_identity
	 *
	 * @return void
	 */
	public function test_an_event_without_a_rule_is_refused(): void {
		$origin_id = $this->create_and_project();

		$this->create_and_project();

		Meta::get_instance()->remove_recurrence( $origin_id );
		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'Failed to assert the occurrence rows survive, so the refusal cannot come from a missing row.'
		);

		$result = Splitter::get_instance()->split_forward( $origin_id, '20260915T180000' );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a post with no recurrence rule refuses a forward split.'
		);
		$this->assertSame(
			'gatherpress_not_recurring',
			$result->get_error_code(),
			'Failed to assert the refusal names the missing rule rather than the missing occurrence.'
		);
	}

	/**
	 * Previewing a rule whose series timezone the expander rejects reports nothing.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::preview_recurrence_ids
	 *
	 * @return void
	 */
	public function test_impact_preview_of_a_fixed_offset_series_is_empty(): void {
		$origin_id = $this->create_and_project();
		$rule      = Rule::from_array( self::SIX_WEEK_RULE );

		// A fixed UTC offset carries no DST rules, so `Expander::expand()`
		// refuses it. The filter is the reachable production route to one, because
		// GatherPress's own validation runs before it.
		$offset = static function (): string {
			return '+05:30';
		};

		add_filter( 'gatherpress_timezone', $offset );

		$preview = Occurrences::get_instance()->preview_recurrence_ids( $origin_id, $rule );

		remove_filter( 'gatherpress_timezone', $offset );

		$this->assertSame(
			array(),
			$preview,
			'Failed to assert a rule the expander refuses previews no occurrences rather than fataling.'
		);
	}

	/**
	 * An occurrence term whose RSVPs are all gone counts zero.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::count_rsvps
	 *
	 * @return void
	 */
	public function test_count_rsvps_is_zero_for_a_term_with_no_objects_left(): void {
		$origin_id  = $this->create_and_project();
		$comment_id = $this->rsvp_on( $origin_id, '20260903T180000' );

		wp_remove_object_terms(
			$comment_id,
			Rsvp_Occurrence::term_slug( $origin_id, '20260903T180000' ),
			Rsvp_Occurrence::TAXONOMY
		);

		$this->assertSame(
			0,
			Rsvp_Occurrence::get_instance()->count_rsvps( $origin_id, array( '20260903T180000' ) ),
			'Failed to assert a term left with no RSVPs counts zero rather than reporting a stale relationship count.'
		);
	}

	/**
	 * The forward post inherits the origin's venue and its other meta.
	 *
	 * The venue appears on every occurrence across a split, including the ones
	 * that moved to a second post.
	 *
	 * @covers ::copy_terms
	 * @covers ::copy_meta
	 *
	 * @return void
	 */
	public function test_forward_post_inherits_venue_and_meta(): void {
		$origin_id = $this->create_and_project();

		update_post_meta( $origin_id, 'gatherpress_max_attendance_limit', 42 );

		// WordPress's test case unregisters every taxonomy between tests and
		// `init` never fires again, so the venue shadow taxonomy's wiring onto
		// the event post type has to be restored here. In production
		// `Venue\Setup::register_taxonomy()` does it on `init`. Without it this test
		// would assert nothing: the venue would be absent from both posts.
		register_taxonomy_for_object_type( '_gatherpress_venue', Event::POST_TYPE );

		$venue = wp_insert_term( 'Community Hall', '_gatherpress_venue', array( 'slug' => '_community-hall' ) );

		wp_set_object_terms( $origin_id, array( (int) $venue['term_id'] ), '_gatherpress_venue' );

		$result  = Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' );
		$forward = (int) $result['forward_post_id'];

		$this->assertSame(
			'42',
			(string) get_post_meta( $forward, 'gatherpress_max_attendance_limit', true ),
			'Failed to assert ordinary event meta came across to the forward post.'
		);
		$this->assertSame(
			array( '_community-hall' ),
			wp_get_object_terms( $forward, '_gatherpress_venue', array( 'fields' => 'slugs' ) ),
			'Failed to assert the venue association came across to the forward post.'
		);
		$this->assertSame(
			'',
			(string) get_post_meta( $forward, '_edit_lock', true ),
			'Failed to assert per-session editor meta was not copied.'
		);
	}

	/**
	 * The RSVP impact report names the stranded dates and counts their RSVPs.
	 *
	 * The organizer is shown how many approved RSVPs a rule change would
	 * strand, and nothing is migrated.
	 *
	 * @covers ::rsvp_impact
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::preview_recurrence_ids
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::count_rsvps
	 *
	 * @return void
	 */
	public function test_rsvp_impact_counts_only_the_rsvps_the_change_would_strand(): void {
		$origin_id = $this->create_and_project();

		$this->rsvp_on( $origin_id, '20260903T180000' );
		$this->rsvp_on( $origin_id, '20261001T180000' );
		$this->rsvp_on( $origin_id, '20261013T180000' );

		$shortened = Rule::from_array( array_merge( self::SIX_WEEK_RULE, array( 'count' => 4 ) ) );
		$impact    = Splitter::get_instance()->rsvp_impact( $origin_id, $shortened );

		$this->assertSame(
			array( '20261001T180000', '20261013T180000' ),
			$impact['removed'],
			'Failed to assert the report names exactly the dates the shortened rule stops producing.'
		);
		$this->assertSame(
			2,
			$impact['rsvp_count'],
			'Failed to assert only the RSVPs on stranded dates are counted. The RSVP on the first'
				. ' occurrence, which the change keeps, must not inflate the number.'
		);
	}

	/**
	 * The impact report writes nothing.
	 *
	 * A preview that projected as a side effect would apply the change the
	 * organizer has not yet confirmed.
	 *
	 * @covers ::rsvp_impact
	 *
	 * @return void
	 */
	public function test_rsvp_impact_is_read_only(): void {
		$origin_id = $this->create_and_project();
		$shortened = Rule::from_array( array_merge( self::SIX_WEEK_RULE, array( 'count' => 2 ) ) );

		Splitter::get_instance()->rsvp_impact( $origin_id, $shortened );

		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'Failed to assert previewing a rule left the projected occurrences untouched.'
		);
	}

	/**
	 * The impact preview of an unanchored post reports nothing rather than failing.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::preview_recurrence_ids
	 *
	 * @return void
	 */
	public function test_impact_preview_of_an_unanchored_post_is_empty(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rule    = Rule::from_array( self::SIX_WEEK_RULE );

		$this->assertSame(
			array(),
			Occurrences::get_instance()->preview_recurrence_ids( $post_id, $rule ),
			'Failed to assert a post with no resolvable anchor previews no occurrences.'
		);
	}

	/**
	 * A moved occurrence is reachable from the post the request names.
	 *
	 * This is the widened-series admission `Context::resolve_in_series()` and
	 * `Rsvp_Occurrence::current_occurrence()` were both built for, exercised
	 * against a real split rather than through the `gatherpress_series_post_ids`
	 * filter. The fixture is chosen so the two answers differ: the occurrence now
	 * lives on the forward post, and the request names the origin.
	 *
	 * @covers ::split_owned_series
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::current_occurrence
	 *
	 * @return void
	 */
	public function test_a_moved_occurrence_resolves_from_the_post_the_request_names(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];

		Context::flush_resolved();
		Series::get_instance()->flush_memo();

		$this->assertTrue(
			Context::get_instance()->set_for_series( $origin_id, '20261001T180000' ),
			'Failed to assert an occurrence a split moved onto a sibling post still resolves from the origin.'
		);
		$this->assertSame(
			array(
				'series_post_id' => $forward,
				'recurrence_id'  => '20261001T180000',
			),
			Rsvp_Occurrence::current_occurrence( $origin_id ),
			'Failed to assert the widened-series membership check admits a sibling post\'s occurrence.'
		);
	}

	/**
	 * One recurrence ID under two posts of a series resolves deterministically.
	 *
	 * A split leaves the two posts free to project rules that meet at the same
	 * moment, which is the case `find_in_series()`\' `ORDER BY series_post_id ASC`
	 * exists for. The post IDs are passed in **reverse** order here, so a
	 * `LIMIT 1` without an ordering could legitimately answer with either row.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::find_in_series
	 *
	 * @return void
	 */
	public function test_one_recurrence_id_under_two_posts_resolves_to_the_lowest_post(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];

		// Projection can no longer manufacture a duplicate identifier across
		// siblings, because `discard_sibling_owned()` skips rows a sibling
		// already owns. The duplicate this test is about is therefore written
		// directly, the shape a row predating that guard, or written by
		// anything else with table access, would take. The ordering guarantee
		// under test is precisely the defense for such rows.
		global $wpdb;

		$forward_row = Occurrences::get_instance()->get( $forward, '20260917T180000' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix ),
			array_merge( $forward_row, array( 'series_post_id' => $origin_id ) )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertContains(
			'20260917T180000',
			$this->identifiers_for( $origin_id ),
			'Failed to assert the fixture actually produced the duplicate this test is about.'
		);
		$this->assertContains(
			'20260917T180000',
			$this->identifiers_for( $forward ),
			'Failed to assert the forward post still carries the same identifier.'
		);

		$statements = array();
		$capture    = static function ( string $query ) use ( &$statements ): string {
			$statements[] = $query;

			return $query;
		};

		add_filter( 'query', $capture );

		$row = Occurrences::get_instance()->find_in_series(
			array( $forward, $origin_id ),
			'20260917T180000'
		);

		remove_filter( 'query', $capture );

		$this->assertSame(
			$origin_id,
			(int) $row['series_post_id'],
			'Failed to assert the lowest post ID wins regardless of the order the post IDs arrive in.'
		);

		// The behavioral assertion above cannot fail on InnoDB today: a clustered
		// index on `(series_post_id, recurrence_id)` is scanned in primary-key
		// order, so `LIMIT 1` happens to return the lowest post ID with or
		// without the `ORDER BY`. Measured rather than assumed: deleting the
		// clause leaves the assertion green. The ordering is a guarantee against a
		// future query plan rather than against today's observed one, so the
		// statement itself is pinned; otherwise the guarantee is verified by
		// nothing.
		$this->assertNotEmpty(
			array_filter(
				$statements,
				static function ( string $query ): bool {
					return str_contains( $query, 'ORDER BY series_post_id ASC' );
				}
			),
			'Failed to assert find_in_series() orders its candidates rather than trusting the query plan.'
		);
	}

	/**
	 * A series relationship write that fails aborts the split and rolls it back.
	 *
	 * `wp_set_object_terms()` reports failure as a `WP_Error`, or as an empty
	 * array after silently skipping a numeric term it could not confirm.
	 * `Series::join()` used to check only whether the *term* could be created,
	 * so a failed relationship write still returned a non-zero term ID,
	 * `join_series()` treated the phase complete, and the split finished with
	 * the forward post outside the series: pre-split permalinks to the moved
	 * rows then 404 with no error reported anywhere.
	 *
	 * The failure is injected by short-circuiting the `term_exists()` lookup
	 * `wp_set_object_terms()` performs for a numeric term, scoped to the
	 * series taxonomy's include queries so every other term operation in the
	 * split runs untouched.
	 *
	 * @covers ::run_phases
	 * @covers ::join_series
	 * @covers \GatherPress\Core\Event\Recurrence\Series::join
	 *
	 * @return void
	 */
	public function test_a_failed_series_relationship_write_aborts_the_split(): void {
		$origin_id = $this->create_and_project();
		$suppress  = static function ( $terms, $query ) {
			$taxonomies = (array) ( $query->query_vars['taxonomy'] ?? array() );

			if ( in_array( Series::TAXONOMY, $taxonomies, true )
				&& ! empty( $query->query_vars['include'] )
			) {
				return array();
			}

			return $terms;
		};

		add_filter( 'terms_pre_query', $suppress, 10, 2 );

		$result = Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' );

		remove_filter( 'terms_pre_query', $suppress, 10 );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'A split whose series relationship write failed must abort rather than report success.'
		);
		$this->assertSame(
			'gatherpress_split_series_not_joined',
			$result->get_error_code(),
			'The abort must name the join phase as the failure.'
		);
		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'The rollback must leave the origin owning its full occurrence set again.'
		);
	}

	/**
	 * A forward-only relationship failure leaves no term and no flag behind.
	 *
	 * The complement of the test above, which suppresses every numeric term
	 * lookup so the origin attachment fails before the forward-only state is
	 * ever reached. Here the injection permits the term creation and the
	 * origin relationship write and rejects only the forward one, by acting
	 * on the first series term lookup made after the origin's relationship
	 * row exists and then standing down, so the compensation's own term
	 * operations run untouched.
	 *
	 * The rollback entry that deletes a minted term is recorded only after
	 * `join()` reports success, so this exact path used to strand a
	 * member-less term no member deletion could ever orphan, and with it a
	 * permanently-on `gatherpress_has_split_series` flag, widening four read
	 * paths on a site that never completed a split.
	 *
	 * @covers ::run_phases
	 * @covers ::join_series
	 * @covers \GatherPress\Core\Event\Recurrence\Series::join
	 *
	 * @return void
	 */
	public function test_a_forward_only_relationship_failure_leaves_no_term_and_no_flag(): void {
		global $wpdb;

		$origin_id = $this->create_and_project();
		$fired     = false;

		$reject_forward = static function ( $terms, $query ) use ( &$fired, $origin_id, $wpdb ) {
			$taxonomies = (array) ( $query->query_vars['taxonomy'] ?? array() );

			if ( $fired
				|| ! in_array( Series::TAXONOMY, $taxonomies, true )
				|| empty( $query->query_vars['include'] )
			) {
				return $terms;
			}

			// The origin's relationship row is what separates the two writes:
			// it does not exist yet while the origin attach confirms its term,
			// and it does exist when the forward attach makes the same lookup.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$origin_attached = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i AS tr INNER JOIN %i AS tt'
						. ' ON tt.term_taxonomy_id = tr.term_taxonomy_id'
						. ' WHERE tt.taxonomy = %s AND tr.object_id = %d',
					$wpdb->term_relationships,
					$wpdb->term_taxonomy,
					Series::TAXONOMY,
					$origin_id
				)
			);

			if ( 0 === $origin_attached ) {
				return $terms;
			}

			$fired = true;

			return array();
		};

		add_filter( 'terms_pre_query', $reject_forward, 10, 2 );

		$result = Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' );

		remove_filter( 'terms_pre_query', $reject_forward, 10 );

		$this->assertTrue(
			$fired,
			'Fixture setup: the injection must have rejected the forward write, and nothing before it.'
		);
		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'A split whose forward relationship write failed must abort rather than report success.'
		);
		$this->assertSame(
			'gatherpress_split_series_not_joined',
			$result->get_error_code(),
			'The abort must name the join phase as the failure.'
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$terms = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE taxonomy = %s',
				$wpdb->term_taxonomy,
				Series::TAXONOMY
			)
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

		$this->assertSame(
			0,
			$terms,
			'The term minted for the failed join must not survive it.'
		);
		$this->assertSame(
			0,
			$relationships,
			'Neither post may keep a relationship to the deleted series term.'
		);
		$this->assertSame(
			'0',
			get_option( 'gatherpress_has_split_series' ),
			'A failed first split must leave the split-series flag off, recomputed from the deleted term.'
		);
		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'The rollback must leave the origin owning its full occurrence set again.'
		);
	}

	/**
	 * A stale rule re-persisted after a split cannot resurrect moved occurrences.
	 *
	 * The still-open origin editor holds the pre-split rule after a successful
	 * split, and one touch of any rule control re-persists it through an
	 * ordinary Update. Re-projecting the origin from that stale rule used to
	 * re-upsert every original identifier under the origin while the forward
	 * post still owned four of them, duplicating occurrence identity across
	 * siblings: listings showed the moved dates twice, and `find_in_series()`
	 * snapped permalinks and RSVP reads back to the origin's copy while the
	 * RSVP terms lived on the forward post.
	 *
	 * The write is driven through `wp_update_post()`, the entry point an editor
	 * Update actually takes, so the assertion covers the production
	 * `wp_after_insert_post` wiring rather than a hand-assembled call order.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::project
	 *
	 * @return void
	 */
	public function test_a_stale_rule_re_persist_does_not_duplicate_identifiers_across_siblings(): void {
		$origin_id = $this->create_and_project();
		$forward   = (int) Splitter::get_instance()->split_forward( $origin_id, '20260917T180000' )['forward_post_id'];

		// The stale editor state: the panel still holds the six-count rule the
		// series had before the split capped it at two.
		update_post_meta( $origin_id, Meta::META_KEY, wp_json_encode( self::SIX_WEEK_RULE ) );
		wp_update_post( array( 'ID' => $origin_id ) );

		$origin_ids  = $this->identifiers_for( $origin_id );
		$forward_ids = $this->identifiers_for( $forward );

		$this->assertSame(
			array(),
			array_values( array_intersect( $origin_ids, $forward_ids ) ),
			'Failed to assert no occurrence identifier is owned by two posts of one series after a stale'
				. ' rule re-persist.'
		);
		$this->assertSame(
			array_slice( self::FULL_SET, 0, 2 ),
			$origin_ids,
			'Failed to assert the origin keeps exactly the rows the split left behind.'
		);
		$this->assertSame(
			array_slice( self::FULL_SET, 2 ),
			$forward_ids,
			'Failed to assert the forward post still owns every row the split moved.'
		);
	}

	/**
	 * The site-wide calendar feed carries both sides of a split series.
	 *
	 * What that does and does not get for free is worth stating exactly. The
	 * **aggregate** feeds (site-wide, archive, venue, taxonomy) enumerate
	 * through `Event\Query::get_events_list()`, which matches
	 * both posts of a split series because both are ordinary published events.
	 * No fragment is lost, and no change in the calendar layer is needed for
	 * that. What they do **not** do is enumerate occurrences: that query asks for
	 * `fields => 'ids'`, which `Recurrence\Query::expand_event_clauses()`
	 * deliberately exempts from expansion because a repeated bare post ID cannot
	 * carry occurrence identity and would emit duplicate `VEVENT`s sharing one
	 * `UID`, which RFC 5545 forbids. One component per post, carrying that post's
	 * own rule, is the shape PR 5's `RRULE` emission fills in.
	 *
	 * The assertion is chosen so a one-fragment feed fails it: the forward post's
	 * anchor is a date the origin's own component cannot produce.
	 *
	 * @covers ::split_owned_series
	 *
	 * @return void
	 */
	public function test_the_site_wide_feed_carries_both_sides_of_a_split_series(): void {
		$start = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( '+2 days' );

		$origin_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => 4,
			),
			$start,
			$start->modify( '+1 hour' ),
			'UTC'
		);

		Recurrence_Query::refresh_has_recurring_events();

		$rows    = Occurrences::get_instance()->select_for_series( array( $origin_id ) );
		$result  = Splitter::get_instance()->split_forward( $origin_id, (string) $rows[2]['recurrence_id'] );
		$forward = (int) $result['forward_post_id'];

		$this->assertGreaterThan( 0, $forward, 'Failed to assert the fixture split into two posts.' );

		$this->go_to( home_url( '/' ) );

		$feed = Calendar_Setup::get_instance()->get_ical_list();

		$this->assertSame(
			2,
			substr_count( $feed, 'BEGIN:VEVENT' ),
			'Failed to assert the feed carries one component per post of the split series.'
		);
		// Asserted without the trailing `Z`: the start is a `TZID`-qualified local
		// wall clock now, not a bare UTC instant, so the `Ymd\THis` portion is the
		// part both forms share. Pinning the full `…Z` form here would re-encode a
		// serialization shape this file has no stake in, and would go red again the
		// next time the calendar layer changes it.
		$this->assertStringContainsString(
			gmdate( 'Ymd\THis', (int) strtotime( (string) $rows[0]['datetime_start_gmt'] ) ),
			$feed,
			'Failed to assert the original post still contributes its own dates to the feed.'
		);
		$this->assertStringContainsString(
			gmdate( 'Ymd\THis', (int) strtotime( (string) $rows[2]['datetime_start_gmt'] ) ),
			$feed,
			'Failed to assert the forward post contributes its dates too. A feed representing only'
				. ' the fragment the split left behind could not carry this date.'
		);
		$this->assertStringContainsString(
			sprintf( 'UID:gatherpress_%d', $forward ),
			$feed,
			'Failed to assert the forward post is a component in its own right rather than absent.'
		);
	}

	/**
	 * Moving zero rows, or moving a post onto itself, writes nothing.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::move_to_post
	 *
	 * @return void
	 */
	public function test_move_to_post_refuses_degenerate_arguments(): void {
		$origin_id = $this->create_and_project();

		$this->assertSame(
			0,
			Occurrences::get_instance()->move_to_post( $origin_id, $origin_id + 1, array() ),
			'Failed to assert moving no identifiers writes nothing.'
		);
		$this->assertSame(
			0,
			Occurrences::get_instance()->move_to_post( $origin_id, $origin_id, self::FULL_SET ),
			'Failed to assert moving a post onto itself writes nothing.'
		);
		$this->assertSame(
			self::FULL_SET,
			$this->identifiers_for( $origin_id ),
			'Failed to assert the degenerate calls left the series untouched.'
		);
	}

	/**
	 * Detaching and counting cope with occurrences nobody has RSVPd to.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::detach_series
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::count_rsvps
	 *
	 * @return void
	 */
	public function test_detach_and_count_handle_occurrences_without_rsvps(): void {
		$origin_id = $this->create_and_project();
		$instance  = Rsvp_Occurrence::get_instance();

		$this->assertSame(
			0,
			$instance->detach_series( $origin_id, array( '20260903T180000' ) ),
			'Failed to assert an occurrence with no RSVP has no term to drop.'
		);
		$this->assertSame(
			0,
			$instance->count_rsvps( $origin_id, array( '20260903T180000' ) ),
			'Failed to assert an occurrence with no term counts zero RSVPs.'
		);
		$this->assertSame(
			0,
			$instance->count_rsvps( $origin_id, array() ),
			'Failed to assert counting across no occurrences at all is zero.'
		);

		$this->rsvp_on( $origin_id, '20260903T180000' );

		$this->assertSame(
			1,
			$instance->count_rsvps( $origin_id, array( '20260903T180000' ) ),
			'Failed to assert a real RSVP is counted. Without this the zeros above prove nothing.'
		);
	}

	/**
	 * An open-ended series keeps its open end on both sides of a split.
	 *
	 * The `COUNT` fixtures elsewhere in this file cannot tell an unadjusted end
	 * bound from an adjusted one, and they cannot reach the judgment that a
	 * single projected row is **not** grounds for demoting a `never` rule: an
	 * open-ended series always has more dates coming, however few the projection
	 * horizon happened to produce.
	 *
	 * @covers ::apply_forward_rule
	 * @covers ::apply_capped_rule
	 *
	 * @return void
	 */
	public function test_an_open_ended_series_keeps_its_open_end_forward(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( 4 ),
				'end_type'  => 'never',
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );
		Recurrence_Query::refresh_has_recurring_events();

		$rows = $this->identifiers_for( $post_id );

		$this->assertGreaterThan(
			3,
			count( $rows ),
			'Failed to assert the open-ended fixture projected a horizon worth of occurrences.'
		);

		$result  = Splitter::get_instance()->split_forward( $post_id, $rows[2] );
		$forward = (int) $result['forward_post_id'];

		$this->assertSame(
			Rule::END_TYPE_NEVER,
			Rule::from_post( $forward )->end_type(),
			'Failed to assert an open-ended rule stays open-ended on the forward post.'
		);
		$this->assertSame(
			0,
			Rule::from_post( $forward )->count(),
			'Failed to assert no occurrence count was invented for an open-ended forward rule.'
		);
		$this->assertSame(
			array( $rows[0], $rows[1] ),
			$this->identifiers_for( $post_id ),
			'Failed to assert the origin was still capped by COUNT at the occurrences it keeps.'
		);
		$moved = array_slice( $rows, 2 );

		// The forward post's own projection horizon is measured from its own
		// anchor, which is later than the origin's, so an open-ended series
		// legitimately gains dates past where the origin had projected to. The
		// moved rows are asserted as the leading run rather than as the whole
		// list, and the extra dates are asserted to exist rather than glossed.
		$this->assertSame(
			$moved,
			array_slice( $this->identifiers_for( $forward ), 0, count( $moved ) ),
			'Failed to assert every later occurrence moved to the forward post, in order.'
		);
		$this->assertGreaterThan(
			count( $moved ),
			count( $this->identifiers_for( $forward ) ),
			'Failed to assert an open-ended forward series projects past where the origin had reached.'
		);
	}

	/**
	 * An open-ended series split at its final projected occurrence stays a series.
	 *
	 * One projected row is not one occurrence when the rule has no end: demoting
	 * it to a plain event would silently discard every date past the projection
	 * horizon.
	 *
	 * @covers ::apply_forward_rule
	 *
	 * @return void
	 */
	public function test_one_projected_row_does_not_demote_an_open_ended_rule(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( 4 ),
				'end_type'  => 'never',
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );
		Recurrence_Query::refresh_has_recurring_events();

		$rows    = $this->identifiers_for( $post_id );
		$last    = (string) end( $rows );
		$result  = Splitter::get_instance()->split_forward( $post_id, $last );
		$forward = (int) $result['forward_post_id'];

		$this->assertTrue(
			$result['forward_recurring'],
			'Failed to assert an open-ended forward side is still a series.'
		);
		$this->assertInstanceOf(
			Rule::class,
			Rule::from_post( $forward ),
			'Failed to assert the forward post kept its open-ended rule.'
		);
	}
	/**
	 * A split driven from one post at an occurrence a sibling post owns splits
	 * the sibling, not the post the request named.
	 *
	 * The multi-post case, and the reason `split_forward()` reads
	 * `$row['series_post_id']` rather than reusing `$post_id`. It is reachable
	 * from the editor without any contrivance: `Rest_Api::get_occurrences()`
	 * resolves through `Series::resolve_post_ids()`, so the "Split from"
	 * dropdown rendered on post A lists occurrences post B owns, and choosing one
	 * posts A's ID with B's identifier.
	 *
	 * Substituting `$post_id` for `$row['series_post_id']` turns this into
	 * `{"split":false,"reason":"first_occurrence","moved":0}`, a silent no-op
	 * reported to the organizer as "Nothing was split". A's own row list does
	 * not contain the identifier at all, so `array_search()` answers `false`,
	 * which casts to index 0.
	 *
	 * @covers ::split_forward
	 * @covers ::split_owned_series
	 *
	 * @return void
	 */
	public function test_a_split_from_a_sibling_owned_occurrence_splits_the_owning_post(): void {
		$post_a = $this->create_and_project();
		$post_b = (int) Splitter::get_instance()->split_forward( $post_a, '20260917T180000' )['forward_post_id'];

		Series::get_instance()->flush_memo();
		Context::flush_resolved();

		$this->assertSame(
			array( '20260917T180000', '20260929T180000', '20261001T180000', '20261013T180000' ),
			$this->identifiers_for( $post_b ),
			'Failed to arrange a sibling post owning the occurrence the second split is driven from.'
		);

		// Driven from post A, at an occurrence post B owns.
		$result = Splitter::get_instance()->split_forward( $post_a, '20261001T180000' );

		$this->assertTrue(
			$result['split'],
			'Failed to assert a split at a sibling-owned occurrence splits rather than degrading to a no-op.'
		);
		$this->assertSame(
			$post_b,
			(int) $result['origin_post_id'],
			'Failed to assert the split was performed on the post that owns the occurrence, not the post named.'
		);
		$this->assertSame(
			2,
			$result['moved'],
			'Failed to assert the two occurrences at and after the split point moved.'
		);

		$post_c = (int) $result['forward_post_id'];

		$this->assertSame(
			array( '20260903T180000', '20260915T180000' ),
			$this->identifiers_for( $post_a ),
			'Failed to assert the first post is untouched by a split of its sibling.'
		);
		$this->assertSame(
			array( '20260917T180000', '20260929T180000' ),
			$this->identifiers_for( $post_b ),
			'Failed to assert the sibling kept the occurrences before the split point.'
		);
		$this->assertSame(
			array( '20261001T180000', '20261013T180000' ),
			$this->identifiers_for( $post_c ),
			'Failed to assert the third post carries the occurrences from the split point onward.'
		);
	}

	/**
	 * Only approved RSVPs are counted among the ones a change would strand.
	 *
	 * Two mechanisms, and they are not the same one stated twice:
	 *
	 * - A **trashed** RSVP keeps its `_gatherpress_occurrence` relationship row,
	 *   so `term_taxonomy.count` would still include it. Counting comments is
	 *   what excludes it, and an organizer told "2 RSVPs affected" when one of
	 *   them is in the trash has been told the wrong thing.
	 * - A **pending** RSVP is excluded by the query's `'status' => 'approve'`.
	 *   That is the arm the trashed case cannot reach: `WP_Comment_Query`
	 *   interprets an absent status as `all`, which is
	 *   `comment_approved IN ( '0', '1' )`. Trash and spam are already out, but
	 *   an unapproved RSVP is in. Deleting `'status' => 'approve'` therefore
	 *   changes nothing about a trashed RSVP and everything about a pending one.
	 *
	 * A pending RSVP is ordinary, reachable data rather than a contrivance:
	 * `Rsvp\Form::prepare_comment_data()` inserts a guest RSVP with
	 * `comment_approved => 0`, and hooks `pre_comment_approved` to
	 * `__return_zero` whenever the submitted email does not match the logged-in
	 * user.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::count_rsvps
	 *
	 * @return void
	 */
	public function test_count_rsvps_counts_only_approved_rsvps(): void {
		$origin_id = $this->create_and_project();
		$instance  = Rsvp_Occurrence::get_instance();

		$approved = $this->rsvp_on( $origin_id, '20260903T180000' );
		$trashed  = $this->rsvp_on( $origin_id, '20260903T180000' );
		$pending  = $this->rsvp_on( $origin_id, '20260903T180000' );

		$this->assertSame(
			3,
			$instance->count_rsvps( $origin_id, array( '20260903T180000' ) ),
			'Failed to arrange three counted RSVPs before any of them is trashed or held.'
		);

		wp_trash_comment( $trashed );
		wp_set_comment_status( $pending, 'hold' );

		$this->assertNotEmpty(
			wp_get_object_terms( $trashed, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'ids' ) ),
			'Failed to assert trashing leaves the occurrence relationship in place. If it did not, the'
				. ' count below would fall for a reason that has nothing to do with counting comments.'
		);
		$this->assertNotEmpty(
			wp_get_object_terms( $pending, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'ids' ) ),
			'Failed to assert holding an RSVP leaves its occurrence relationship in place.'
		);
		$this->assertSame(
			'0',
			(string) get_comment( $pending )->comment_approved,
			'Failed to arrange an RSVP that is pending rather than trashed.'
		);
		$this->assertSame(
			1,
			$instance->count_rsvps( $origin_id, array( '20260903T180000' ) ),
			'Failed to assert only the approved RSVP is counted once one is trashed and one is pending.'
		);
		$this->assertSame(
			'1',
			(string) get_comment( $approved )->comment_approved,
			'Failed to assert the surviving RSVP is the approved one.'
		);
	}
}
