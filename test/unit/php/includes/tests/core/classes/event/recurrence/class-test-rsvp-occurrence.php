<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Rsvp_Occurrence.
 *
 * One RSVP belongs to one occurrence, not to the series. The
 * tests that matter here therefore drive the production entry points
 * `Rsvp::save()`, `Rsvp::get()` and `Rsvp::responses()` inside a real
 * occurrence context, rather than calling the taxonomy helpers directly. A test that only
 * asserted `assign()` wrote a term would pass against a read path that ignores
 * the term entirely.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Rsvp\Cache;
use GatherPress\Core\Rsvp\Cleanup;
use GatherPress\Core\Rsvp\List_Table;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Core\Rsvp\Token;
use GatherPress\Core\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rsvp_Occurrence.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence
 */
class Test_Rsvp_Occurrence extends Base {

	use Occurrence_Fixtures;

	/**
	 * The reference weekly rule, matching `Occurrence_Fixtures::expected_weekly_set()`.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const WEEKLY_RULE = array(
		'frequency' => 'weekly',
		'interval'  => 2,
		'weekdays'  => array( 2, 4 ),
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * Recurrence identifier of the reference set's first occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const OCCURRENCE_A = '20260903T180000';

	/**
	 * Recurrence identifier of the reference set's second occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const OCCURRENCE_B = '20260915T180000';

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
	}

	/**
	 * Leave no occurrence context behind for the next test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Context::get_instance()->clear();

		parent::tearDown();
	}

	/**
	 * Create the reference recurring event, project it, and flag the site as recurring.
	 *
	 * Mirrors the production order: the rule mirrors are written first, the
	 * occurrence rows are projected from them, and only then is the
	 * has-recurring-events flag recomputed from storage.
	 *
	 * @since 0.36.0
	 *
	 * @return int The projected series post ID.
	 */
	protected function create_and_project(): int {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );
		Recurrence_Query::refresh_has_recurring_events();

		return $post_id;
	}

	/**
	 * Count the term relationship rows a comment holds in one taxonomy.
	 *
	 * Reads `term_relationships` directly rather than through
	 * `wp_get_object_terms()`, because the bug under test is an orphaned row
	 * whose term may still resolve perfectly well.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $comment_id Comment ID to count relationships for.
	 * @param string $taxonomy   Taxonomy to count within.
	 *
	 * @return int Number of rows.
	 */
	protected function relationship_count( int $comment_id, string $taxonomy ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i AS tr INNER JOIN %i AS tt'
					. ' ON tt.term_taxonomy_id = tr.term_taxonomy_id'
					. ' WHERE tr.object_id = %d AND tt.taxonomy = %s',
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
				$comment_id,
				$taxonomy
			)
		);
	}

	/**
	 * Count the relationship rows a comment holds across all three RSVP taxonomies.
	 *
	 * @since 0.36.0
	 *
	 * @param int $comment_id Comment ID to count relationships for.
	 *
	 * @return array<string, int> Row counts keyed by taxonomy.
	 */
	protected function all_relationship_counts( int $comment_id ): array {
		return array(
			Status::TAXONOMY          => $this->relationship_count( $comment_id, Status::TAXONOMY ),
			Provider::TAXONOMY        => $this->relationship_count( $comment_id, Provider::TAXONOMY ),
			Rsvp_Occurrence::TAXONOMY => $this->relationship_count( $comment_id, Rsvp_Occurrence::TAXONOMY ),
		);
	}

	/**
	 * Save an RSVP for a user while the request is rendering one occurrence.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence to enter before saving.
	 * @param int    $user_id       Responder.
	 * @param string $status        RSVP status to save.
	 *
	 * @return array The save result.
	 */
	protected function save_in_occurrence(
		int $post_id,
		string $recurrence_id,
		int $user_id,
		string $status = 'attending'
	): array {
		Context::get_instance()->set( $post_id, $recurrence_id );

		return ( new Rsvp( $post_id ) )->save( $user_id, $status );
	}

	/**
	 * The slug format is `{series_post_id}-{recurrence_id}`, in the sanitized form WordPress stores.
	 *
	 * The literal is asserted rather than recomputed, so a change to the format
	 * fails here rather than passing a tautology.
	 *
	 * @covers ::term_slug
	 *
	 * @return void
	 */
	public function test_term_slug_format_is_post_dash_recurrence_id(): void {
		$this->assertSame(
			'12-20260915t180000',
			Rsvp_Occurrence::term_slug( 12, '20260915T180000' ),
			'Failed to assert the occurrence term slug is the series post ID joined to the recurrence ID.'
		);

		$this->assertSame(
			sanitize_title( Rsvp_Occurrence::term_slug( 12, '20260915T180000' ) ),
			Rsvp_Occurrence::term_slug( 12, '20260915T180000' ),
			'Failed to assert the occurrence term slug survives WordPress slug sanitization unchanged.'
		);
	}

	/**
	 * The slug carries the post ID, so two series cannot collide on one recurrence ID.
	 *
	 * @covers ::term_slug
	 *
	 * @return void
	 */
	public function test_term_slug_differs_per_series_post(): void {
		$this->assertNotSame(
			Rsvp_Occurrence::term_slug( 12, self::OCCURRENCE_A ),
			Rsvp_Occurrence::term_slug( 13, self::OCCURRENCE_A ),
			'Failed to assert the occurrence term slug is scoped by series post ID.'
		);
	}

	/**
	 * The taxonomy is registered on the comment object type, privately.
	 *
	 * @covers \GatherPress\Core\Rsvp\Setup::register_taxonomy
	 *
	 * @return void
	 */
	public function test_taxonomy_is_registered_privately_on_comments(): void {
		Rsvp_Setup::get_instance()->register_taxonomy();

		$taxonomy = get_taxonomy( Rsvp_Occurrence::TAXONOMY );

		$this->assertNotFalse(
			$taxonomy,
			'Failed to assert the occurrence taxonomy is registered.'
		);
		$this->assertContains(
			'comment',
			$taxonomy->object_type,
			'Failed to assert the occurrence taxonomy is registered on comments.'
		);
		$this->assertFalse(
			$taxonomy->public,
			'Failed to assert the occurrence taxonomy is private.'
		);
		$this->assertFalse(
			$taxonomy->show_in_rest,
			'Failed to assert the occurrence taxonomy is withheld from REST.'
		);
		$this->assertFalse(
			$taxonomy->rewrite,
			'Failed to assert the occurrence taxonomy registers no rewrite rules.'
		);
	}

	/**
	 * THE core claim: an RSVP saved on one occurrence is invisible on another.
	 *
	 * @covers ::assign
	 * @covers ::tax_query
	 * @covers ::term_slug
	 * @covers ::current_recurrence_id
	 * @covers \GatherPress\Core\Rsvp\Storage::scope_to_occurrence
	 * @covers \GatherPress\Core\Rsvp\Storage::get
	 * @covers \GatherPress\Core\Rsvp\Storage::save
	 *
	 * @return void
	 */
	public function test_rsvp_on_occurrence_a_is_not_visible_on_occurrence_b(): void {
		$post_id = $this->create_and_project();
		$user_id = $this->factory->user->create();

		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id );

		Context::get_instance()->set( $post_id, self::OCCURRENCE_B );

		$this->assertNull(
			( new Rsvp( $post_id ) )->get( $user_id ),
			'Failed to assert an RSVP saved on one occurrence is absent from another.'
		);

		Context::get_instance()->set( $post_id, self::OCCURRENCE_A );

		$response = ( new Rsvp( $post_id ) )->get( $user_id );

		$this->assertIsArray(
			$response,
			'Failed to assert an RSVP saved on an occurrence is readable from that same occurrence.'
		);
		$this->assertSame(
			'attending',
			$response['status'],
			'Failed to assert the occurrence-scoped RSVP kept its status.'
		);
	}

	/**
	 * Attendee counts are per occurrence, not per series.
	 *
	 * `responses()` is read from each occurrence twice, in A-B-A order, so a
	 * cache key missing the occurrence dimension fails on the second read of A
	 * as well as the first read of B.
	 *
	 * @covers ::assign
	 * @covers ::tax_query
	 * @covers \GatherPress\Core\Rsvp\Cache::get
	 * @covers \GatherPress\Core\Rsvp\Cache::set
	 * @covers \GatherPress\Core\Rsvp\Cache::cache_key
	 * @covers \GatherPress\Core\Rsvp\Cache::resolve_occurrence
	 * @covers \GatherPress\Core\Rsvp\Storage::scope_to_occurrence
	 *
	 * @return void
	 */
	public function test_attendee_count_counts_only_that_occurrence(): void {
		$post_id  = $this->create_and_project();
		$first    = $this->factory->user->create();
		$second   = $this->factory->user->create();
		$third    = $this->factory->user->create();
		$expected = array(
			self::OCCURRENCE_A => 2,
			self::OCCURRENCE_B => 1,
		);

		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $first );
		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $second );
		$this->save_in_occurrence( $post_id, self::OCCURRENCE_B, $third );

		foreach ( array( self::OCCURRENCE_A, self::OCCURRENCE_B, self::OCCURRENCE_A ) as $recurrence_id ) {
			Context::get_instance()->set( $post_id, $recurrence_id );

			$responses = ( new Rsvp( $post_id ) )->responses();

			$this->assertSame(
				$expected[ $recurrence_id ],
				$responses['attending']['count'],
				sprintf(
					'Failed to assert occurrence %s counts only its own attendees.',
					$recurrence_id
				)
			);
		}
	}

	/**
	 * Changing a status on one occurrence leaves the other occurrence alone.
	 *
	 * @covers ::assign
	 * @covers ::tax_query
	 *
	 * @return void
	 */
	public function test_changing_status_on_a_does_not_affect_b(): void {
		$post_id = $this->create_and_project();
		$user_id = $this->factory->user->create();

		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id );
		$this->save_in_occurrence( $post_id, self::OCCURRENCE_B, $user_id );

		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id, 'not_attending' );

		Context::get_instance()->set( $post_id, self::OCCURRENCE_B );

		$this->assertSame(
			'attending',
			( new Rsvp( $post_id ) )->get( $user_id )['status'],
			'Failed to assert a status change on one occurrence left the other occurrence unchanged.'
		);

		Context::get_instance()->set( $post_id, self::OCCURRENCE_A );

		$this->assertSame(
			'not_attending',
			( new Rsvp( $post_id ) )->get( $user_id )['status'],
			'Failed to assert the status change landed on the occurrence it was made from.'
		);
	}

	/**
	 * A responder holds one independent RSVP row per occurrence.
	 *
	 * Guards against the read path being scoped while the write path silently
	 * updates the first matching comment, which would leave one row wearing two
	 * occurrence terms.
	 *
	 * @covers ::assign
	 *
	 * @return void
	 */
	public function test_each_occurrence_gets_its_own_comment_row(): void {
		$post_id = $this->create_and_project();
		$user_id = $this->factory->user->create();

		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id );
		$this->save_in_occurrence( $post_id, self::OCCURRENCE_B, $user_id );

		Context::get_instance()->set( $post_id, self::OCCURRENCE_A );
		$first = ( new Rsvp( $post_id ) )->find( $user_id );

		Context::get_instance()->set( $post_id, self::OCCURRENCE_B );
		$second = ( new Rsvp( $post_id ) )->find( $user_id );

		$this->assertNotSame(
			(int) $first->comment->comment_ID,
			(int) $second->comment->comment_ID,
			'Failed to assert each occurrence stores its own RSVP comment.'
		);

		$this->assertSame(
			1,
			$this->relationship_count( (int) $first->comment->comment_ID, Rsvp_Occurrence::TAXONOMY ),
			'Failed to assert an RSVP comment carries exactly one occurrence term.'
		);
	}

	/**
	 * A site with no recurring events behaves exactly as before.
	 *
	 * @covers ::assign
	 * @covers ::tax_query
	 *
	 * @return void
	 */
	public function test_non_recurring_rsvp_behavior_is_unchanged(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$user_id = $this->factory->user->create();

		Recurrence_Query::refresh_has_recurring_events();

		$rsvp = new Rsvp( $post_id );

		$rsvp->save( $user_id, 'attending' );

		$this->assertSame(
			'attending',
			$rsvp->get( $user_id )['status'],
			'Failed to assert an ordinary event RSVP still reads back.'
		);
		$this->assertSame(
			1,
			$rsvp->responses()['attending']['count'],
			'Failed to assert an ordinary event still counts its attendees.'
		);

		$comment_id = (int) $rsvp->find( $user_id )->comment->comment_ID;

		$this->assertSame(
			0,
			$this->relationship_count( $comment_id, Rsvp_Occurrence::TAXONOMY ),
			'Failed to assert a non-recurring RSVP is never given an occurrence term.'
		);
	}

	/**
	 * A site with no recurring events pays nothing for this feature.
	 *
	 * Asserts on the query log across the real `Rsvp::save()` entry point, not
	 * on a return value. An occurrence term written on a non-recurring site
	 * is invisible to every return value in the flow.
	 *
	 * @covers ::assign
	 * @covers ::current_recurrence_id
	 *
	 * @return void
	 */
	public function test_non_recurring_site_issues_no_occurrence_queries_on_save(): void {
		global $wpdb;

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$user_id = $this->factory->user->create();

		Recurrence_Query::refresh_has_recurring_events();

		$occurrences_table  = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$query_count_before = count( $wpdb->queries );

		( new Rsvp( $post_id ) )->save( $user_id, 'attending' );

		$queries_since = array_slice( $wpdb->queries, $query_count_before );

		$this->assertNotEmpty(
			$queries_since,
			'Failed to capture any queries; SAVEQUERIES must be on for this assertion to mean anything.'
		);

		$touched = array_values(
			array_filter(
				$queries_since,
				static function ( array $query ) use ( $occurrences_table ): bool {
					return str_contains( $query[0], $occurrences_table )
						|| str_contains( $query[0], Rsvp_Occurrence::TAXONOMY );
				}
			)
		);

		$this->assertSame(
			array(),
			$touched,
			'Failed to assert a non-recurring save touched neither the occurrence table nor its taxonomy.'
		);
	}

	/**
	 * The RSVP cache key carries the occurrence dimension.
	 *
	 * @covers ::current_recurrence_id
	 * @covers \GatherPress\Core\Rsvp\Cache::get
	 * @covers \GatherPress\Core\Rsvp\Cache::set
	 * @covers \GatherPress\Core\Rsvp\Cache::cache_key
	 * @covers \GatherPress\Core\Rsvp\Cache::resolve_occurrence
	 *
	 * @return void
	 */
	public function test_occurrence_cache_key_carries_the_occurrence_dimension(): void {
		$post_id = 987654;
		$value   = array( 'all' => array( 'count' => 3 ) );

		Cache::set( $post_id, $value, self::OCCURRENCE_A );

		$this->assertSame(
			$value,
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to assert an occurrence-scoped cache entry reads back on its own occurrence.'
		);
		$this->assertNull(
			Cache::get( $post_id, self::OCCURRENCE_B ),
			'Failed to assert an occurrence-scoped cache entry misses on another occurrence.'
		);
		$this->assertNull(
			Cache::get( $post_id ),
			'Failed to assert an occurrence-scoped cache entry misses on the series-wide key.'
		);
	}

	/**
	 * Writing an RSVP invalidates both the series key and the occurrence key.
	 *
	 * @covers ::current_recurrence_id
	 * @covers \GatherPress\Core\Rsvp\Cache::delete
	 * @covers \GatherPress\Core\Rsvp\Cache::cache_key
	 * @covers \GatherPress\Core\Rsvp\Cache::resolve_occurrence
	 *
	 * @return void
	 */
	public function test_saving_an_rsvp_invalidates_both_cache_keys(): void {
		$post_id = $this->create_and_project();
		$user_id = $this->factory->user->create();

		Cache::set( $post_id, array( 'all' => array( 'count' => 99 ) ) );
		Cache::set( $post_id, array( 'all' => array( 'count' => 42 ) ), self::OCCURRENCE_A );

		// Both keys must exist independently before the write, or "both were
		// invalidated" is satisfied by there only ever having been one key.
		$this->assertSame(
			array( 'all' => array( 'count' => 99 ) ),
			Cache::get( $post_id ),
			'Failed to assert the series-wide cache entry is stored separately from the occurrence one.'
		);
		$this->assertSame(
			array( 'all' => array( 'count' => 42 ) ),
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to assert the occurrence cache entry is stored separately from the series-wide one.'
		);

		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id );

		$this->assertNull(
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to assert a write invalidated the occurrence-scoped cache key.'
		);
		$this->assertNull(
			Cache::get( $post_id ),
			'Failed to assert a write invalidated the series-wide cache key.'
		);
	}

	/**
	 * Approving an RSVP through its token invalidates the occurrence's cache key.
	 *
	 * `handle_rsvp_token()` runs on `init`, before `wp`, so there is no
	 * occurrence context for `Cache::delete()` to resolve from. The occurrence
	 * has to come off the comment's own term instead. Without that, the
	 * occurrence key survives the approval and serves stale counts for the
	 * length of `Cache::CACHE_EXPIRATION`, to every visitor at once under a
	 * persistent object cache.
	 *
	 * @covers ::recurrence_id_for_comment
	 * @covers ::recurrence_id_from_slug
	 * @covers \GatherPress\Core\Rsvp\Token::approve_comment
	 *
	 * @return void
	 */
	public function test_token_approval_invalidates_the_occurrence_cache_key(): void {
		$post_id    = $this->create_and_project();
		$user_id    = $this->factory->user->create();
		$comment_id = (int) $this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id )['comment_id'];

		wp_update_comment(
			array(
				'comment_ID'       => $comment_id,
				'comment_approved' => '0',
			)
		);

		// Leave occurrence context *first*, then warm both keys. The token
		// handler runs on `init`, where no context has been established, so
		// this is also the production shape. Warming while context was still
		// set made `Cache::set( $post_id, … )` resolve to the occurrence key
		// rather than the series one, so both writes landed on the same key and
		// the second silently overwrote the first. The pre-assertions below now
		// catch that.
		Context::get_instance()->clear();

		Cache::set( $post_id, array( 'all' => array( 'count' => 99 ) ) );
		Cache::set( $post_id, array( 'all' => array( 'count' => 42 ) ), self::OCCURRENCE_A );

		// Both keys must exist independently before the approval, exactly as in
		// the sibling test above. Without these, "both were invalidated" is
		// satisfied by there never having been anything to invalidate. The
		// occurrence key is the one this test is actually about.
		$this->assertSame(
			array( 'all' => array( 'count' => 99 ) ),
			Cache::get( $post_id ),
			'Failed to assert the series-wide cache entry was warmed before the approval.'
		);
		$this->assertSame(
			array( 'all' => array( 'count' => 42 ) ),
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to assert the occurrence cache entry was warmed before the approval.'
		);

		( new Token( $comment_id ) )->approve_comment();

		$this->assertNull(
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to assert token approval invalidated the occurrence-scoped cache key.'
		);
		$this->assertNull(
			Cache::get( $post_id ),
			'Failed to assert token approval invalidated the series-wide cache key.'
		);
	}

	/**
	 * The occurrence recovered from a comment is the canonical identifier.
	 *
	 * `term_slug()` runs the composite through `sanitize_title()`, which
	 * lowercases the `T` of `Ymd\THis`. Handing that form back would compose a
	 * cache key no write has ever produced, so the round trip is asserted
	 * against the canonical value rather than against the slug.
	 *
	 * @covers ::recurrence_id_for_comment
	 * @covers ::recurrence_id_from_slug
	 *
	 * @return void
	 */
	public function test_recurrence_id_for_comment_recovers_the_canonical_identifier(): void {
		$post_id    = $this->create_and_project();
		$user_id    = $this->factory->user->create();
		$comment_id = (int) $this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id )['comment_id'];

		$this->assertSame(
			self::OCCURRENCE_A,
			Rsvp_Occurrence::recurrence_id_for_comment( $comment_id ),
			'Failed to assert the occurrence recovered from a comment is the canonical identifier.'
		);
	}

	/**
	 * A comment with no occurrence term, and an unusable ID, resolve to null.
	 *
	 * @covers ::recurrence_id_for_comment
	 * @covers ::recurrence_id_from_slug
	 *
	 * @return void
	 */
	public function test_recurrence_id_for_comment_returns_null_without_a_term(): void {
		$this->create_and_project();

		$comment_id = (int) $this->factory->comment->create();

		$this->assertNull(
			Rsvp_Occurrence::recurrence_id_for_comment( $comment_id ),
			'Failed to assert a comment carrying no occurrence term resolves to null.'
		);
		$this->assertNull(
			Rsvp_Occurrence::recurrence_id_for_comment( 0 ),
			'Failed to assert an unusable comment ID resolves to null.'
		);
		$this->assertNull(
			Rsvp_Occurrence::recurrence_id_from_slug( 'no-separator-here-' ),
			'Failed to assert a slug ending in the separator carries no identifier.'
		);
		$this->assertNull(
			Rsvp_Occurrence::recurrence_id_from_slug( '20260903t180000' ),
			'Failed to assert a slug with no separator at all carries no identifier.'
		);
	}

	/**
	 * On a non-recurring site the comment is never asked for an occurrence.
	 *
	 * @covers ::recurrence_id_for_comment
	 *
	 * @return void
	 */
	public function test_recurrence_id_for_comment_is_null_off_a_recurring_site(): void {
		$comment_id = (int) $this->factory->comment->create();

		Recurrence_Query::refresh_has_recurring_events();

		$this->assertNull(
			Rsvp_Occurrence::recurrence_id_for_comment( $comment_id ),
			'Failed to assert a non-recurring site resolves no occurrence for a comment.'
		);
	}

	/**
	 * The explicit-scope requirement holds exactly for recurring series.
	 *
	 * Three arms, one per branch: a site with no recurring events answers from
	 * the autoloaded option alone, a non-recurring event on a recurring site
	 * is not required to carry a scope, and the recurring series itself is.
	 * The middle arm is the one that matters: were the requirement keyed on
	 * the site flag rather than on the event's own rule, every ordinary
	 * event's classic submission would be refused the moment any other event
	 * on the site became recurring.
	 *
	 * @covers ::requires_explicit_scope
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::has_recurrence_rule
	 *
	 * @return void
	 */
	public function test_requires_explicit_scope_holds_exactly_for_recurring_series(): void {
		$plain_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		Recurrence_Query::refresh_has_recurring_events();

		$this->assertFalse(
			Rsvp_Occurrence::requires_explicit_scope( $plain_id ),
			'Failed to assert no event requires a scope on a site with no recurring events.'
		);

		$series_id = $this->create_and_project();

		$this->assertFalse(
			Rsvp_Occurrence::requires_explicit_scope( $plain_id ),
			'Failed to assert a non-recurring event requires no scope even on a recurring site.'
		);
		$this->assertTrue(
			Rsvp_Occurrence::requires_explicit_scope( $series_id ),
			'Failed to assert a recurring series requires an explicit scope.'
		);
	}

	/**
	 * The attendance limit and the waiting list are counted per occurrence.
	 *
	 * @covers ::assign
	 * @covers ::tax_query
	 *
	 * @return void
	 */
	public function test_attendance_limit_and_waiting_list_are_per_occurrence(): void {
		$post_id = $this->create_and_project();

		add_post_meta( $post_id, 'gatherpress_max_attendance_limit', 1 );

		$first  = $this->factory->user->create();
		$second = $this->factory->user->create();

		$this->assertSame(
			'attending',
			$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $first )['status'],
			'Failed to assert the first responder takes the only seat on occurrence A.'
		);
		$this->assertSame(
			'waiting_list',
			$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $second )['status'],
			'Failed to assert the second responder is waitlisted on occurrence A.'
		);
		$this->assertSame(
			'attending',
			$this->save_in_occurrence( $post_id, self::OCCURRENCE_B, $second )['status'],
			'Failed to assert occurrence B has its own seat, unconsumed by occurrence A.'
		);
	}

	/**
	 * The `delete_comment` relationship cleanup is owned by `Rsvp\Cleanup`.
	 *
	 * It clears all three RSVP comment taxonomies, only one of which is about
	 * recurrence, so it belongs beside the hard-delete cron rather than on a
	 * class named for the occurrence link. Asserted rather than described: a
	 * move back here would leave this failing.
	 *
	 * @covers ::__construct
	 * @covers \GatherPress\Core\Rsvp\Cleanup::setup_hooks
	 *
	 * @return void
	 */
	public function test_relationship_cleanup_is_hooked_from_the_rsvp_cleanup_class(): void {
		// The singleton is built once per process, so whichever test happens to
		// reach it first is the only one xdebug credits. Invoking the (now
		// empty) constructor directly is the documented way to trace it from
		// the test that is actually about it.
		Utility::invoke_hidden_method( Rsvp_Occurrence::get_instance(), '__construct' );

		// The constructor is empty but not pointless, and this is the assertion
		// that says so: `Traits\Singleton` declares no constructor, so deleting
		// this one hands the class PHP's implicit *public* one and `new
		// Rsvp_Occurrence()` becomes legal, allowing two instances of a singleton.
		$this->assertTrue(
			( new \ReflectionClass( Rsvp_Occurrence::class ) )->getConstructor()->isProtected(),
			'Failed to assert the constructor stays protected so get_instance() is the only way to build one.'
		);

		$this->assertSame(
			10,
			has_action( 'delete_comment', array( Cleanup::get_instance(), 'delete_term_relationships' ) ),
			'Failed to assert Rsvp\Cleanup owns the delete_comment relationship cleanup.'
		);
		$this->assertFalse(
			has_action( 'delete_comment', array( Rsvp_Occurrence::get_instance(), 'delete_term_relationships' ) ),
			'Failed to assert Rsvp_Occurrence no longer hooks the relationship cleanup.'
		);
	}

	/**
	 * Deleting a comment that is not an RSVP touches no term relationships.
	 *
	 * @covers \GatherPress\Core\Rsvp\Cleanup::delete_term_relationships
	 *
	 * @return void
	 */
	public function test_delete_term_relationships_ignores_non_rsvp_comments(): void {
		Rsvp_Setup::get_instance()->register_taxonomy();

		$comment_id = (int) $this->factory->comment->create();

		Rsvp_Occurrence::get_instance()->assign( $comment_id, 12, self::OCCURRENCE_A );

		// A plain comment reaching the callback, and the same call with no
		// comment object at all, which is the shape WordPress uses in a few
		// legacy `do_action( 'delete_comment', $id )` call sites.
		Cleanup::get_instance()->delete_term_relationships( $comment_id, get_comment( $comment_id ) );
		Cleanup::get_instance()->delete_term_relationships( $comment_id );

		$this->assertSame(
			1,
			$this->relationship_count( $comment_id, Rsvp_Occurrence::TAXONOMY ),
			'Failed to assert a non-RSVP comment is left alone by the RSVP relationship cleanup.'
		);
	}

	/**
	 * A historical occurrence term is cleaned even when the site flag is off.
	 *
	 * `gatherpress_has_recurring_events` describes the current occurrence-table
	 * state, not whether a comment already carries an occurrence relationship.
	 * A site can remove its last recurrence, flip the flag to `0`, and still
	 * hold RSVPs stamped while the series existed; gating the destructive
	 * cleanup on the flag orphans those rows forever. The fixture reaches the
	 * real flag-0 state through the production rule-removal path rather than by
	 * forcing the option, so the recompute itself is part of what is driven.
	 *
	 * @covers \GatherPress\Core\Rsvp\Cleanup::delete_term_relationships
	 *
	 * @return void
	 */
	public function test_hard_delete_cleans_a_historical_occurrence_term_when_the_flag_is_off(): void {
		$post_id    = $this->create_and_project();
		$user_id    = $this->factory->user->create();
		$comment_id = (int) $this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id )['comment_id'];

		Context::get_instance()->clear();

		// Remove the rule the way a save with an emptied blob does: the mirrors
		// are cleared and the flag is recomputed from storage.
		update_post_meta( $post_id, Meta::META_KEY, '' );
		Meta::get_instance()->set_recurrence( $post_id );
		Meta::get_instance()->resolve_pending_recurrence();

		$this->assertSame(
			'0',
			get_option( Recurrence_Query::HAS_RECURRING_OPTION ),
			'Failed to arrange a genuine flag-0 site; the rule removal did not recompute the option.'
		);
		$this->assertSame(
			1,
			$this->relationship_count( $comment_id, Rsvp_Occurrence::TAXONOMY ),
			'Failed to arrange a historical occurrence relationship surviving into the flag-0 state.'
		);

		wp_delete_comment( $comment_id, true );

		$this->assertSame(
			array(
				Status::TAXONOMY          => 0,
				Provider::TAXONOMY        => 0,
				Rsvp_Occurrence::TAXONOMY => 0,
			),
			$this->all_relationship_counts( $comment_id ),
			'Failed to assert a flag-0 hard delete removed every relationship, the historical occurrence term included.'
		);
	}

	/**
	 * Hard-deleting an RSVP comment leaves no orphaned term relationships.
	 *
	 * Core's `wp_delete_comment()` never removes term relationships, so all
	 * three RSVP taxonomies leaked a row on every hard delete. Trashing is
	 * deliberately not used here: `Storage::save( 'no_status' )` trashes rather
	 * than deletes, and a cleanup test written against it passes without ever
	 * deleting anything.
	 *
	 * The delete must also drop both warm RSVP cache keys. The list table and
	 * the cleanup cron are the only real hard-delete paths, neither reaches
	 * `Rsvp\Storage::save()` where every other write invalidates, so without
	 * invalidation here the deleted responder stays on every cached roster for
	 * the length of `Cache::CACHE_EXPIRATION`, shared across all visitors under
	 * a persistent object cache.
	 *
	 * @covers ::assign
	 * @covers \GatherPress\Core\Rsvp\Cleanup::delete_term_relationships
	 * @covers \GatherPress\Core\Rsvp\Cache::delete
	 *
	 * @return void
	 */
	public function test_hard_deleting_an_rsvp_comment_leaves_no_orphan_term_relationships(): void {
		$post_id    = $this->create_and_project();
		$user_id    = $this->factory->user->create();
		$comment_id = (int) $this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id )['comment_id'];

		$this->assertSame(
			array(
				Status::TAXONOMY          => 1,
				Provider::TAXONOMY        => 1,
				Rsvp_Occurrence::TAXONOMY => 1,
			),
			$this->all_relationship_counts( $comment_id ),
			'Failed to assert a saved occurrence RSVP holds one relationship in each taxonomy.'
		);

		// The delete runs with no ambient occurrence context, exactly like the
		// admin list table and the cleanup cron, so the invalidation under test
		// can only find the occurrence through the comment's own term.
		Context::get_instance()->clear();

		Cache::set( $post_id, array( 'all' => array( 'count' => 99 ) ) );
		Cache::set( $post_id, array( 'all' => array( 'count' => 42 ) ), self::OCCURRENCE_A );

		// Both keys must be warm and distinct before the delete, or "both were
		// invalidated" is satisfied by there having been nothing to invalidate.
		$this->assertSame(
			array( 'all' => array( 'count' => 99 ) ),
			Cache::get( $post_id ),
			'Failed to warm the series-wide cache entry the delete must invalidate.'
		);
		$this->assertSame(
			array( 'all' => array( 'count' => 42 ) ),
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to warm the occurrence cache entry the delete must invalidate.'
		);

		wp_delete_comment( $comment_id, true );

		$this->assertSame(
			array(
				Status::TAXONOMY          => 0,
				Provider::TAXONOMY        => 0,
				Rsvp_Occurrence::TAXONOMY => 0,
			),
			$this->all_relationship_counts( $comment_id ),
			'Failed to assert a hard delete removed every RSVP term relationship.'
		);
		$this->assertNull(
			Cache::get( $post_id ),
			'Failed to assert a hard delete invalidated the series-wide RSVP cache.'
		);
		$this->assertNull(
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to assert a hard delete invalidated the occurrence-scoped RSVP cache.'
		);
	}

	/**
	 * The RSVP cleanup cron's hard delete leaves no orphaned term relationships.
	 *
	 * The cron's delete must also drop both warm RSVP cache keys, for the same
	 * reason the direct hard-delete test asserts it: this path never reaches
	 * `Rsvp\Storage::save()`, so nothing else invalidates for it.
	 *
	 * @covers ::assign
	 * @covers \GatherPress\Core\Rsvp\Cleanup::delete_term_relationships
	 * @covers \GatherPress\Core\Rsvp\Cache::delete
	 *
	 * @return void
	 */
	public function test_cleanup_cron_hard_delete_leaves_no_orphan_term_relationships(): void {
		$post_id    = $this->create_and_project();
		$user_id    = $this->factory->user->create();
		$comment_id = (int) $this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id )['comment_id'];

		// The cleanup cron only collects held RSVPs older than 24 hours.
		wp_update_comment(
			array(
				'comment_ID'       => $comment_id,
				'comment_approved' => '0',
				'comment_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ),
			)
		);

		// Cron runs with no ambient occurrence context, so the invalidation can
		// only find the occurrence through the comment's own term.
		Context::get_instance()->clear();

		Cache::set( $post_id, array( 'all' => array( 'count' => 99 ) ) );
		Cache::set( $post_id, array( 'all' => array( 'count' => 42 ) ), self::OCCURRENCE_A );

		// Both keys must be warm and distinct before the sweep, or "both were
		// invalidated" is satisfied by there having been nothing to invalidate.
		$this->assertSame(
			array( 'all' => array( 'count' => 99 ) ),
			Cache::get( $post_id ),
			'Failed to warm the series-wide cache entry the cron must invalidate.'
		);
		$this->assertSame(
			array( 'all' => array( 'count' => 42 ) ),
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to warm the occurrence cache entry the cron must invalidate.'
		);

		Cleanup::get_instance()->rsvp_cleanup();

		$this->assertNull(
			get_comment( $comment_id ),
			'Failed to assert the cleanup cron hard-deleted the held RSVP.'
		);
		$this->assertSame(
			array(
				Status::TAXONOMY          => 0,
				Provider::TAXONOMY        => 0,
				Rsvp_Occurrence::TAXONOMY => 0,
			),
			$this->all_relationship_counts( $comment_id ),
			'Failed to assert the cleanup cron removed every RSVP term relationship.'
		);
		$this->assertNull(
			Cache::get( $post_id ),
			'Failed to assert the cleanup cron invalidated the series-wide RSVP cache.'
		);
		$this->assertNull(
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to assert the cleanup cron invalidated the occurrence-scoped RSVP cache.'
		);
	}

	/**
	 * Hard-deleting a series-wide RSVP invalidates the series cache key.
	 *
	 * The counterpart branch to the occurrence-scoped delete: a comment with no
	 * occurrence term is counted only under the series key, and on a site with
	 * no recurring events at all that is the only key that can exist.
	 *
	 * @covers \GatherPress\Core\Rsvp\Cleanup::delete_term_relationships
	 * @covers \GatherPress\Core\Rsvp\Cache::delete
	 *
	 * @return void
	 */
	public function test_hard_deleting_a_series_wide_rsvp_invalidates_the_series_cache(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$user_id = $this->factory->user->create();

		Recurrence_Query::refresh_has_recurring_events();

		$comment_id = (int) ( new Rsvp( $post_id ) )->save( $user_id, 'attending' )['comment_id'];

		Cache::set( $post_id, array( 'all' => array( 'count' => 99 ) ) );

		$this->assertSame(
			array( 'all' => array( 'count' => 99 ) ),
			Cache::get( $post_id ),
			'Failed to warm the series-wide cache entry the delete must invalidate.'
		);

		wp_delete_comment( $comment_id, true );

		$this->assertNull(
			Cache::get( $post_id ),
			'Failed to assert hard-deleting a series-wide RSVP invalidated the series cache.'
		);
	}

	/**
	 * The delete drops both posts' caches when the term names a sibling post.
	 *
	 * After a forward split the occurrence term's own series post can
	 * legitimately differ from the post the comment names, and the row was
	 * counted under both identities at different times, so the invalidation
	 * must drop the named post's series key alongside the owning post's pair.
	 *
	 * @covers \GatherPress\Core\Rsvp\Cleanup::delete_term_relationships
	 * @covers \GatherPress\Core\Rsvp\Cache::delete
	 *
	 * @return void
	 */
	public function test_hard_delete_drops_both_posts_caches_when_the_term_names_a_sibling(): void {
		$owner_id = $this->create_and_project();
		$named_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$comment_id = (int) $this->factory->comment->create(
			array(
				'comment_post_ID' => $named_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		Rsvp_Occurrence::get_instance()->assign( $comment_id, $owner_id, self::OCCURRENCE_A );

		Cache::set( $named_id, array( 'all' => array( 'count' => 7 ) ) );
		Cache::set( $owner_id, array( 'all' => array( 'count' => 99 ) ) );
		Cache::set( $owner_id, array( 'all' => array( 'count' => 42 ) ), self::OCCURRENCE_A );

		$this->assertSame(
			array( 'all' => array( 'count' => 7 ) ),
			Cache::get( $named_id ),
			'Failed to warm the named post cache entry the delete must invalidate.'
		);

		wp_delete_comment( $comment_id, true );

		$this->assertNull(
			Cache::get( $named_id ),
			'Failed to assert the delete invalidated the series cache of the post the comment names.'
		);
		$this->assertNull(
			Cache::get( $owner_id ),
			'Failed to assert the delete invalidated the series cache of the term-owning post.'
		);
		$this->assertNull(
			Cache::get( $owner_id, self::OCCURRENCE_A ),
			'Failed to assert the delete invalidated the occurrence cache of the term-owning post.'
		);
	}

	/**
	 * The list table's bulk delete leaves no orphaned term relationships.
	 *
	 * @covers ::assign
	 * @covers \GatherPress\Core\Rsvp\Cleanup::delete_term_relationships
	 *
	 * @return void
	 */
	public function test_list_table_delete_leaves_no_orphan_term_relationships(): void {
		$post_id    = $this->create_and_project();
		$user_id    = $this->factory->user->create();
		$comment_id = (int) $this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id )['comment_id'];

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$list_table = new List_Table();
		$plural     = Utility::get_hidden_property( $list_table, '_args' )['plural'];

		$_REQUEST['_wpnonce']            = wp_create_nonce( sprintf( 'bulk-%s', $plural ) );
		$_REQUEST['gatherpress_rsvp_id'] = array( $comment_id );
		$_REQUEST['action']              = 'delete';

		$list_table->process_bulk_action();

		unset( $_REQUEST['_wpnonce'], $_REQUEST['gatherpress_rsvp_id'], $_REQUEST['action'] );

		$this->assertNull(
			get_comment( $comment_id ),
			'Failed to assert the list table hard-deleted the RSVP.'
		);
		$this->assertSame(
			array(
				Status::TAXONOMY          => 0,
				Provider::TAXONOMY        => 0,
				Rsvp_Occurrence::TAXONOMY => 0,
			),
			$this->all_relationship_counts( $comment_id ),
			'Failed to assert the list table delete removed every RSVP term relationship.'
		);
	}

	/**
	 * Assigning refuses an unusable comment, post, or recurrence identifier.
	 *
	 * @covers ::assign
	 *
	 * @return void
	 */
	public function test_assign_refuses_incomplete_identifiers(): void {
		$instance = Rsvp_Occurrence::get_instance();

		$this->assertFalse(
			$instance->assign( 0, 12, self::OCCURRENCE_A ),
			'Failed to assert assign() refuses a missing comment ID.'
		);
		$this->assertFalse(
			$instance->assign( 5, 0, self::OCCURRENCE_A ),
			'Failed to assert assign() refuses a missing post ID.'
		);
		$this->assertFalse(
			$instance->assign( 5, 12, '' ),
			'Failed to assert assign() refuses an empty recurrence ID.'
		);
	}

	/**
	 * Assigning writes the occurrence term onto the comment.
	 *
	 * @covers ::assign
	 *
	 * @return void
	 */
	public function test_assign_writes_the_occurrence_term(): void {
		Rsvp_Setup::get_instance()->register_taxonomy();

		$comment_id = (int) $this->factory->comment->create();

		$this->assertTrue(
			Rsvp_Occurrence::get_instance()->assign( $comment_id, 12, self::OCCURRENCE_A ),
			'Failed to assert assign() reports success.'
		);
		$this->assertSame(
			array( Rsvp_Occurrence::term_slug( 12, self::OCCURRENCE_A ) ),
			wp_list_pluck( wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY ), 'slug' ),
			'Failed to assert assign() wrote the occurrence term slug.'
		);
	}

	/**
	 * The tax query names the occurrence taxonomy and the composite slug.
	 *
	 * @covers ::tax_query
	 *
	 * @return void
	 */
	public function test_tax_query_scopes_to_one_occurrence_slug(): void {
		$this->assertSame(
			array(
				array(
					'taxonomy' => Rsvp_Occurrence::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( Rsvp_Occurrence::term_slug( 12, self::OCCURRENCE_A ) ),
				),
			),
			Rsvp_Occurrence::get_instance()->tax_query( 12, self::OCCURRENCE_A ),
			'Failed to assert the occurrence tax query scopes to the composite slug.'
		);
	}

	/**
	 * Outside occurrence context there is no recurrence ID to scope by.
	 *
	 * @covers ::current_recurrence_id
	 *
	 * @return void
	 */
	public function test_current_recurrence_id_is_null_outside_context(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->clear();

		$this->assertNull(
			Rsvp_Occurrence::current_recurrence_id( $post_id ),
			'Failed to assert no recurrence ID resolves outside occurrence context.'
		);
	}

	/**
	 * A context on another post does not scope this post's RSVPs.
	 *
	 * The mismatch is resolved through `Series::resolve_post_ids()` rather than
	 * refused outright, so this pins the other side of that: a post
	 * that is genuinely not in the series still resolves to nothing. Without it,
	 * "resolve through the series" would be indistinguishable from "accept any
	 * post at all", and an occurrence of one event could scope another's RSVPs.
	 *
	 * @covers ::current_recurrence_id
	 * @covers ::current_occurrence
	 *
	 * @return void
	 */
	public function test_current_recurrence_id_is_null_for_another_post(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::OCCURRENCE_A );

		$this->assertSame(
			self::OCCURRENCE_A,
			Rsvp_Occurrence::current_recurrence_id( $post_id ),
			'Failed to assert the current occurrence resolves for its own series post.'
		);
		$this->assertSame(
			array(
				'series_post_id' => $post_id,
				'recurrence_id'  => self::OCCURRENCE_A,
			),
			Rsvp_Occurrence::current_occurrence( $post_id ),
			'Failed to assert the resolved occurrence carries the post its row lives on.'
		);
		$this->assertNull(
			Rsvp_Occurrence::current_recurrence_id( $post_id + 1000 ),
			'Failed to assert the current occurrence does not resolve for an unrelated post.'
		);
		$this->assertNull(
			Rsvp_Occurrence::current_occurrence( $post_id + 1000 ),
			'Failed to assert an unrelated post is refused rather than silently widened.'
		);
	}

	/**
	 * The has-recurring-events flag gates the resolution entirely.
	 *
	 * @covers ::current_recurrence_id
	 *
	 * @return void
	 */
	public function test_current_recurrence_id_is_null_when_the_site_has_no_recurring_events(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::OCCURRENCE_A );

		update_option( Recurrence_Query::HAS_RECURRING_OPTION, '0' );

		$this->assertNull(
			Rsvp_Occurrence::current_recurrence_id( $post_id ),
			'Failed to assert the no-recurring-events guard short-circuits occurrence resolution.'
		);
	}

	/**
	 * Removing an attendee invalidates the caches their removal changed.
	 *
	 * `Rsvp::save()` invalidated *after* bailing on a null `process()` result,
	 * and the `no_status` path is exactly the path that returns null. That is
	 * the path that trashes the comment. So the single save that removes an attendee
	 * was the single save that skipped invalidation, leaving them visible in
	 * warm counts for the length of `Cache::CACHE_EXPIRATION` and, under a
	 * persistent object cache, visible to every visitor at once.
	 *
	 * @covers \GatherPress\Core\Rsvp\Rsvp::save
	 * @covers \GatherPress\Core\Rsvp\Cache::delete
	 *
	 * @return void
	 */
	public function test_removing_an_rsvp_invalidates_the_cache(): void {
		$post_id = $this->create_and_project();
		$user_id = $this->factory->user->create();

		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id );

		Cache::set( $post_id, array( 'all' => array( 'count' => 99 ) ), self::OCCURRENCE_A );

		$this->assertSame(
			array( 'all' => array( 'count' => 99 ) ),
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to warm the occurrence cache entry the removal must invalidate.'
		);

		// The removal itself. `no_status` trashes the stored comment and makes
		// `process()` return null.
		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id, 'no_status' );

		$this->assertNull(
			Cache::get( $post_id, self::OCCURRENCE_A ),
			'Failed to assert removing an attendee invalidated the occurrence cache key.'
		);
	}

	/**
	 * The occurrence read path gets a scope-varying comment-query cache domain.
	 *
	 * `WP_Comment_Query::get_comments()` hashes its cache key from its declared
	 * query vars, and `tax_query` is not one of them. It reaches the SQL only
	 * through a `comments_clauses` filter. Two reads differing solely by
	 * occurrence would hash identically and the second would be served the
	 * first one's comment IDs.
	 *
	 * `Rsvp\Query::ensure_cache_domain()` is the single mechanism that prevents
	 * that, for every taxonomy-scoped read rather than only the ones that
	 * remember to set a domain. `Storage::scope_to_occurrence()` used to set one
	 * of its own, which short-circuited the derivation and left two mechanisms
	 * where the local one was the weaker. It keyed on the identifier alone where
	 * the derived key covers the series post too.
	 *
	 * @covers \GatherPress\Core\Rsvp\Query::ensure_cache_domain
	 * @covers \GatherPress\Core\Rsvp\Storage::scope_to_occurrence
	 *
	 * @return void
	 */
	public function test_two_occurrences_do_not_share_a_comment_query_cache_key(): void {
		$post_id = $this->create_and_project();
		$user_id = $this->factory->user->create();
		$domains = array();
		$capture = static function ( $clauses, $query ) use ( &$domains ) {
			$domains[] = (string) $query->query_vars['cache_domain'];

			return $clauses;
		};

		$this->save_in_occurrence( $post_id, self::OCCURRENCE_A, $user_id );

		// An editor bypasses the response transient (`Rsvp::responses()` only
		// caches the public variant), so both reads below reach the comment
		// query this test is about rather than the second being served a
		// transient and never producing a cache domain at all.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// The save above already ran occurrence A's comment query, so without
		// this the A read below is served from `WP_Comment_Query`'s own object
		// cache and never reaches `comments_clauses`, leaving one domain
		// observed and the comparison vacuous.
		wp_cache_flush();

		add_filter( 'comments_clauses', $capture, 99, 2 );

		Context::get_instance()->set( $post_id, self::OCCURRENCE_A );
		( new Rsvp( $post_id ) )->responses();

		Context::get_instance()->set( $post_id, self::OCCURRENCE_B );
		( new Rsvp( $post_id ) )->responses();

		remove_filter( 'comments_clauses', $capture, 99 );

		$scoped = array_values(
			array_filter(
				$domains,
				static function ( string $domain ): bool {
					return '' !== $domain && 'core' !== $domain;
				}
			)
		);

		$this->assertNotEmpty(
			$scoped,
			'Failed to assert an occurrence-scoped read carries a cache domain at all.'
		);
		$this->assertCount(
			2,
			array_unique( $scoped ),
			'Failed to assert two occurrences of one series produce two distinct comment-query cache domains.'
		);
	}
}
