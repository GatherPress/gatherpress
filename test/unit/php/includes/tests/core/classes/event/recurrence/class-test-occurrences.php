<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Occurrences.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrence_Ref;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Projection_Cron;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Rule;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;

/**
 * Class Test_Occurrences.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Occurrences
 */
class Test_Occurrences extends Base {

	use Occurrence_Fixtures;

	/**
	 * The reference weekly rule shared by the projection tests, matching
	 * `Occurrence_Fixtures::expected_weekly_set()`.
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
	 * Start every test from an empty occurrence table, independent of
	 * execution order relative to Test_Schema.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
	}

	/**
	 * Create a recurring event and project its rule, returning the post ID.
	 *
	 * @since 0.36.0
	 *
	 * @param array $rule Recurrence rule values.
	 *
	 * @return int The projected post ID.
	 */
	protected function create_and_project( array $rule = self::WEEKLY_RULE ): int {
		$post_id = $this->create_recurring_event( $rule );

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return $post_id;
	}

	/**
	 * A move with nothing to move writes nothing and announces nothing.
	 *
	 * Both guards matter to the caller a forward split will be. An empty
	 * identifier list would build `IN ( )`, which is a syntax error rather than
	 * a no-op; a source equal to the destination would announce two changes and
	 * invalidate every cached feed on the site for an update that moved nothing.
	 *
	 * @covers ::move_to_post
	 *
	 * @return void
	 */
	public function test_move_to_post_is_a_no_op_without_work_to_do(): void {
		$post_id     = $this->create_and_project();
		$destination = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$announced   = 0;

		add_action(
			'gatherpress_occurrences_changed',
			static function () use ( &$announced ): void {
				++$announced;
			}
		);

		$this->assertSame(
			0,
			Occurrences::get_instance()->move_to_post( $post_id, (int) $destination, array() ),
			'An empty identifier list must not reach the database.'
		);
		$this->assertSame(
			0,
			Occurrences::get_instance()->move_to_post( $post_id, $post_id, array( '20260903T180000' ) ),
			'A move onto the post that already owns the rows is not a move.'
		);
		$this->assertSame( 0, $announced, 'Neither no-op may invalidate a cache.' );
	}

	/**
	 * A move that matches no row changes nothing and announces nothing.
	 *
	 * The scope is the composite `(series_post_id, recurrence_id)` key, so an
	 * identifier that belongs to another series matches nothing here, and the
	 * announcement is gated on rows actually having moved rather than on the
	 * statement having run.
	 *
	 * @covers ::move_to_post
	 *
	 * @return void
	 */
	public function test_move_to_post_announces_nothing_when_no_row_matches(): void {
		$post_id     = $this->create_and_project();
		$destination = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$announced   = 0;

		add_action(
			'gatherpress_occurrences_changed',
			static function () use ( &$announced ): void {
				++$announced;
			}
		);

		$this->assertSame(
			0,
			Occurrences::get_instance()->move_to_post( $post_id, (int) $destination, array( '20991231T000000' ) ),
			'An identifier this series does not carry must move nothing.'
		);
		$this->assertSame( 0, $announced, 'A statement that moved nothing has nothing to announce.' );
	}

	/**
	 * The preview reports what a candidate rule would produce, and writes nothing.
	 *
	 * The organizer is shown how many RSVPs a rule change would strand
	 * *before* it is applied, which means answering "what
	 * would this rule produce?" without the upsert or the stale-row delete
	 * `project()` performs.
	 *
	 * @covers ::preview_recurrence_ids
	 *
	 * @return void
	 */
	public function test_preview_recurrence_ids_reports_a_candidate_without_writing(): void {
		$post_id = $this->create_and_project();
		$stored  = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$preview = Occurrences::get_instance()->preview_recurrence_ids(
			$post_id,
			Rule::from_array(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => 3,
				)
			)
		);

		$this->assertSame(
			array( '20260903T180000', '20260904T180000', '20260905T180000' ),
			$preview,
			'The preview must expand the candidate rule against the series anchor.'
		);
		$this->assertSame(
			$stored,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'A preview is read-only: the stored rows must be byte-identical afterwards.'
		);
	}

	/**
	 * A post with no anchor previews nothing rather than warning.
	 *
	 * @covers ::preview_recurrence_ids
	 *
	 * @return void
	 */
	public function test_preview_recurrence_ids_returns_nothing_without_an_anchor(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$this->assertSame(
			array(),
			Occurrences::get_instance()->preview_recurrence_ids(
				(int) $post_id,
				Rule::from_array(
					array(
						'frequency' => 'daily',
						'interval'  => 1,
						'end_type'  => 'count',
						'count'     => 3,
					)
				)
			),
			'An event with no datetime has nothing for a candidate rule to expand from.'
		);
	}

	/**
	 * A series the expander rejects previews nothing rather than throwing.
	 *
	 * The same live scenario `expand_or_clear()`'s catch exists for: the
	 * `gatherpress_timezone` filter runs after GatherPress's own validation and
	 * can hand back a fixed offset, which `Expander::expand()` refuses on its
	 * first line. The preview runs on organizer input from a REST route, so
	 * that has to come back as "this produces no dates" rather than as a fatal.
	 *
	 * @covers ::preview_recurrence_ids
	 *
	 * @return void
	 */
	public function test_preview_recurrence_ids_swallows_an_unexpandable_series(): void {
		$post_id = $this->create_and_project();
		$filter  = static fn() => '+05:30';

		add_filter( 'gatherpress_timezone', $filter );

		$preview = Occurrences::get_instance()->preview_recurrence_ids(
			$post_id,
			Rule::from_array(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => 3,
				)
			)
		);

		remove_filter( 'gatherpress_timezone', $filter );

		$this->assertSame(
			array(),
			$preview,
			'A series whose timezone cannot carry a rule must preview as empty rather than fatal.'
		);
	}

	/**
	 * Coverage for `__construct` and `setup_hooks`.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Occurrences::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 20,
				'callback' => array( $instance, 'maybe_project' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_delete_for_post' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_queue_projection' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_queue_projection' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_queue_projection' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The occurrence identifier is the local start in `Ymd\THis` form.
	 *
	 * @covers ::recurrence_id
	 *
	 * @return void
	 */
	public function test_recurrence_id_format_is_ymdthis(): void {
		$start = new DateTimeImmutable( '2026-09-03 18:00:00', new DateTimeZone( 'America/New_York' ) );

		$this->assertSame(
			'20260903T180000',
			Occurrences::recurrence_id( $start ),
			'Failed to assert that recurrence_id formats the local start as Ymd\THis.'
		);
	}

	/**
	 * Coverage for `project()` writing every row of the reference weekly rule.
	 *
	 * @covers ::project
	 * @covers ::resolve_anchor
	 * @covers ::build_occurrence_row
	 * @covers ::upsert_occurrences
	 * @covers ::insert_or_update_rows
	 * @covers ::delete_stale_rows
	 *
	 * @return void
	 */
	public function test_project_writes_rows_for_weekly_rule(): void {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );
		Meta::get_instance()->set_recurrence( $post_id );

		$written = Occurrences::get_instance()->project( $post_id );

		$this->assertSame( 5, $written, 'Failed to assert that project() wrote all five occurrences.' );

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertCount( 5, $rows, 'Failed to assert that five rows exist for the series.' );

		foreach ( $this->expected_weekly_set() as $index => $expected ) {
			$this->assertSame( $expected['recurrence_id'], $rows[ $index ]['recurrence_id'] );
			$this->assertSame( $expected['datetime_start'], $rows[ $index ]['datetime_start'] );
			$this->assertSame( $expected['datetime_start_gmt'], $rows[ $index ]['datetime_start_gmt'] );
			$this->assertSame( $expected['datetime_end'], $rows[ $index ]['datetime_end'] );
			$this->assertSame( $expected['datetime_end_gmt'], $rows[ $index ]['datetime_end_gmt'] );
			$this->assertSame( Occurrences::STATUS_SCHEDULED, $rows[ $index ]['status'] );
		}
	}

	/**
	 * Projecting twice with no rule change is idempotent.
	 *
	 * @covers ::project
	 *
	 * @return void
	 */
	public function test_project_twice_is_idempotent(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$second = $instance->project( $post_id );

		$this->assertSame( 5, $second, 'Failed to assert that the second projection also wrote five rows.' );

		$rows = $instance->select_for_series( array( $post_id ) );

		$this->assertCount( 5, $rows, 'Failed to assert that no duplicate rows exist after a second projection.' );
	}

	/**
	 * Coverage for a rule edit removing stale occurrence rows and adding new ones.
	 *
	 * @covers ::project
	 *
	 * @return void
	 */
	public function test_project_after_rule_edit_removes_stale_and_adds_new(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		update_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => 3,
				)
			)
		);
		Meta::get_instance()->set_recurrence( $post_id );
		$instance->project( $post_id );

		$rows           = $instance->select_for_series( array( $post_id ) );
		$recurrence_ids = wp_list_pluck( $rows, 'recurrence_id' );

		$this->assertCount( 3, $rows, 'Failed to assert that only the new daily rule\'s three rows remain.' );
		$this->assertNotContains(
			'20260915T180000',
			$recurrence_ids,
			'Failed to assert that a stale weekly occurrence was removed.'
		);
		$this->assertContains(
			'20260904T180000',
			$recurrence_ids,
			'Failed to assert that a new daily occurrence was added.'
		);
	}

	/**
	 * Cancellation survives a rule regeneration untouched.
	 *
	 * @covers ::project
	 * @covers ::insert_or_update_rows
	 *
	 * @return void
	 */
	public function test_project_preserves_canceled_status_across_regeneration(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$this->assertTrue(
			$instance->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED ),
			'Failed to assert that set_status canceled the first occurrence.'
		);

		$instance->project( $post_id );

		$row = $instance->get( $post_id, '20260903T180000' );

		$this->assertSame(
			Occurrences::STATUS_CANCELED,
			$row['status'],
			'Failed to assert that the canceled status survived regeneration.'
		);
	}

	/**
	 * A long-running `never`-ending series must project occurrences that are
	 * actually upcoming, not stop at a horizon
	 * measured from a years-old anchor. Anchored 2019-01-03, an
	 * anchor-relative 12-month horizon would project entirely into the past
	 * with zero upcoming entries; the horizon
	 * must instead roll forward from `now`.
	 *
	 * @covers ::project
	 * @covers ::expand_or_clear
	 * @covers ::resolve_horizon
	 *
	 * @return void
	 */
	public function test_project_rolls_horizon_forward_for_a_long_running_never_ending_series(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = new DateTimeImmutable( '2019-01-03 18:00:00', $timezone );

		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'never',
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			'America/New_York'
		);

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertNotEmpty( $rows, 'Failed to assert that the long-running series wrote any rows at all.' );

		$upcoming     = Occurrences::get_instance()->select_upcoming( 50 );
		$upcoming_ids = wp_list_pluck( $upcoming, 'post_id' );

		$this->assertContains(
			$post_id,
			$upcoming_ids,
			'Failed to assert that a 2019-anchored never-ending series has upcoming occurrences.'
		);
	}

	/**
	 * Direct coverage for `resolve_projectable()`'s no-rule branch, which returns
	 * `array( $post_id )`. AGENTS.md requires one direct invoke per return path,
	 * rather than reaching it only transitively through `project()`.
	 *
	 * @covers ::resolve_projectable
	 *
	 * @return void
	 */
	public function test_resolve_projectable_clears_rows_when_no_rule_exists(): void {
		$post_id = $this->create_and_project();

		delete_post_meta( $post_id, Meta::META_KEY );
		Meta::get_instance()->set_recurrence( $post_id );
		Meta::get_instance()->resolve_pending_recurrence();

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'resolve_projectable',
			array( $post_id, true )
		);

		$this->assertNull( $result, 'Failed to assert that resolve_projectable returns null when no rule exists.' );
		$this->assertCount(
			0,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert that resolve_projectable cleared the existing rows.'
		);
	}

	/**
	 * Direct coverage for `resolve_projectable()`'s no-rule branch with
	 * `$cleanup` false: existing rows are left untouched.
	 *
	 * @covers ::resolve_projectable
	 *
	 * @return void
	 */
	public function test_resolve_projectable_skips_cleanup_when_cleanup_is_false(): void {
		$post_id = $this->create_and_project();

		delete_post_meta( $post_id, Meta::META_KEY );
		Meta::get_instance()->set_recurrence( $post_id );
		Meta::get_instance()->resolve_pending_recurrence();

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'resolve_projectable',
			array( $post_id, false )
		);

		$this->assertNull( $result, 'Failed to assert that resolve_projectable returns null when no rule exists.' );
		$this->assertCount(
			5,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert that resolve_projectable left the existing rows untouched when cleanup is false.'
		);
	}

	/**
	 * Direct coverage for `resolve_projectable()`'s anchor-null branch:
	 * extracted-helper coverage guards against the known xdebug tracing gap
	 * for same-class helpers called via short delegation (`project()` ->
	 * `resolve_projectable()` -> `delete_for_post()`).
	 *
	 * @covers ::resolve_projectable
	 *
	 * @return void
	 */
	public function test_resolve_projectable_clears_rows_when_anchor_cannot_be_resolved(): void {
		$post_id = $this->create_and_project();

		delete_post_meta( $post_id, 'gatherpress_datetime_start' );
		delete_post_meta( $post_id, 'gatherpress_datetime_end' );

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'resolve_projectable',
			array( $post_id, true )
		);

		$this->assertNull(
			$result,
			'Failed to assert that resolve_projectable returns null for an unresolvable anchor.'
		);
		$this->assertCount(
			0,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert that resolve_projectable cleared the existing rows.'
		);
	}

	/**
	 * Direct coverage for `resolve_projectable()`'s success path: a post with
	 * a valid rule and a resolvable anchor returns the rule, anchor start,
	 * anchor end, and timezone together.
	 *
	 * @covers ::resolve_projectable
	 *
	 * @return void
	 */
	public function test_resolve_projectable_returns_rule_and_anchor_for_a_recurring_post(): void {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );
		Meta::get_instance()->set_recurrence( $post_id );

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'resolve_projectable',
			array( $post_id, true )
		);

		$this->assertIsArray( $result );
		$this->assertInstanceOf( Rule::class, $result[0] );
		$this->assertInstanceOf( DateTimeImmutable::class, $result[1] );
		$this->assertInstanceOf( DateTimeImmutable::class, $result[2] );
		$this->assertInstanceOf( DateTimeZone::class, $result[3] );
		$this->assertSame(
			$this->reference_anchor_start,
			$result[1]->format( 'Y-m-d H:i:s' ),
			'Failed to assert that the returned anchor start matches the stored datetime.'
		);
	}

	/**
	 * Direct coverage for `run_projection()`'s success path (a resolved rule
	 * writing rows): called only via short delegation from `project()` and
	 * from a loop in `resolve_pending_projection()`, which is the same
	 * xdebug same-class tracing gap AGENTS.md documents for extracted
	 * helpers.
	 *
	 * @covers ::run_projection
	 *
	 * @return void
	 */
	public function test_run_projection_writes_rows_for_a_resolved_rule(): void {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );
		Meta::get_instance()->set_recurrence( $post_id );

		$written = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'run_projection',
			array( $post_id, true )
		);

		$this->assertSame( 5, $written, 'Failed to assert that run_projection wrote all five occurrences.' );
	}

	/**
	 * Direct coverage for `run_projection()`'s `null === $occurrences` branch
	 * (`expand_or_clear()` rejected the timezone): same xdebug same-class
	 * short-delegation gap as the success-path test above.
	 *
	 * @covers ::run_projection
	 *
	 * @return void
	 */
	public function test_run_projection_returns_zero_when_expand_rejects_the_timezone(): void {
		$post_id = $this->create_and_project();

		$filter = static fn() => '+05:30';
		add_filter( 'gatherpress_timezone', $filter );

		$written = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'run_projection',
			array( $post_id, true )
		);

		remove_filter( 'gatherpress_timezone', $filter );

		$this->assertSame(
			0,
			$written,
			'Failed to assert that run_projection returns 0 when expand_or_clear rejects the timezone.'
		);
	}

	/**
	 * Direct coverage for `resolve_horizon()`'s ternary: a future anchor is
	 * used as the horizon base directly.
	 *
	 * @covers ::resolve_horizon
	 *
	 * @return void
	 */
	public function test_resolve_horizon_uses_anchor_when_anchor_is_in_the_future(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '+5 years' );

		$horizon = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'resolve_horizon',
			array( $anchor, $timezone )
		);

		$this->assertSame(
			$anchor->modify( '+' . Occurrences::PROJECTION_HORIZON_MONTHS . ' months' )->format( 'Y-m-d H:i:s' ),
			$horizon->format( 'Y-m-d H:i:s' ),
			'Failed to assert that a future anchor is used directly as the horizon base.'
		);
	}

	/**
	 * Direct coverage for `resolve_horizon()`'s ternary: a past anchor rolls
	 * the horizon forward from "now" instead.
	 *
	 * @covers ::resolve_horizon
	 *
	 * @return void
	 */
	public function test_resolve_horizon_uses_now_when_anchor_is_in_the_past(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = new DateTimeImmutable( '2019-01-03 18:00:00', $timezone );
		$now      = new DateTimeImmutable( 'now', $timezone );

		$horizon = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'resolve_horizon',
			array( $anchor, $timezone )
		);

		$this->assertGreaterThan(
			$now,
			$horizon,
			'Failed to assert that a past anchor still produces a horizon in the future.'
		);
	}

	/**
	 * Coverage for the `gatherpress_recurrence_horizon_months` filter.
	 *
	 * @covers ::resolve_horizon
	 *
	 * @return void
	 */
	public function test_resolve_horizon_is_filterable(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '+1 year' );

		$filter = static fn() => 1;
		add_filter( 'gatherpress_recurrence_horizon_months', $filter );

		$horizon = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'resolve_horizon',
			array( $anchor, $timezone )
		);

		remove_filter( 'gatherpress_recurrence_horizon_months', $filter );

		$this->assertSame(
			$anchor->modify( '+1 months' )->format( 'Y-m-d H:i:s' ),
			$horizon->format( 'Y-m-d H:i:s' ),
			'Failed to assert that the gatherpress_recurrence_horizon_months filter overrides the default.'
		);
	}

	/**
	 * Coverage for a rule whose expansion produces zero occurrences deleting
	 * every existing row for the series.
	 *
	 * @covers ::project
	 * @covers ::delete_stale_rows
	 *
	 * @return void
	 */
	public function test_project_rule_yielding_zero_occurrences_deletes_all_existing_rows(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		update_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'until',
					'until'     => '2026-09-01',
				)
			)
		);
		Meta::get_instance()->set_recurrence( $post_id );

		$written = $instance->project( $post_id );

		$this->assertSame( 0, $written, 'Failed to assert that a rule expanding to nothing writes zero rows.' );
		$this->assertCount(
			0,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that the previously projected rows were all deleted.'
		);
	}

	/**
	 * A non-recurring event returns 0 without writing anything.
	 *
	 * @covers ::project
	 *
	 * @return void
	 */
	public function test_project_returns_zero_for_non_recurring_event(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$this->assertSame(
			0,
			Occurrences::get_instance()->project( $post_id ),
			'Failed to assert that project() returns 0 for a post with no recurrence rule.'
		);
	}

	/**
	 * Saving an ordinary, never-recurring event through the real save-path
	 * hooks must issue zero queries against the occurrence table, which is the
	 * "a site with no recurring events pays nothing" guarantee. Checking only
	 * project()'s return value (the test above) is not enough to guard this: a
	 * deferred no-blob path (maybe_project() -> resolve_pending_projection())
	 * that cleans up unconditionally silently adds a DELETE query to this
	 * exact, most-common save path, and that shows up only in the query log,
	 * never in a return value. Driving the real `wp_after_insert_post` /
	 * `shutdown` hooks rather than calling project() directly is what catches
	 * it.
	 *
	 * @covers ::maybe_project
	 * @covers ::resolve_pending_projection
	 * @covers ::run_projection
	 * @covers ::resolve_projectable
	 *
	 * @return void
	 */
	public function test_ordinary_event_save_never_queries_the_occurrence_table(): void {
		global $wpdb;

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$query_count_before = count( $wpdb->queries );

		// Production order, driven by the real hooks rather than hand-called:
		// wp_after_insert_post (Meta::set_recurrence() at priority 10,
		// Occurrences::maybe_project() at priority 20), then shutdown, for a
		// post with no gatherpress_recurrence blob at all.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'wp_after_insert_post', $post_id, get_post( $post_id ), true, null );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$occurrences_table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$queries_since       = array_slice( $wpdb->queries, $query_count_before );
		$touched_occurrences = array_values(
			array_filter(
				$queries_since,
				static function ( $query ) use ( $occurrences_table ) {
					return str_contains( $query[0], $occurrences_table );
				}
			)
		);

		$this->assertSame(
			array(),
			$touched_occurrences,
			'Failed to assert that saving a never-recurring event issued no query against the occurrence table.'
		);
	}

	/**
	 * A series whose rule is removed must have its
	 * existing occurrence rows cleared, not left orphaned. `Rule::from_post()`
	 * returning null looks identical whether a post never had a rule or just
	 * lost one, so `project()` cannot tell "nothing to do" from "clean up"
	 * without deleting unconditionally in the no-rule branch.
	 *
	 * @covers ::project
	 * @covers ::resolve_projectable
	 *
	 * @return void
	 */
	public function test_project_deletes_existing_rows_when_rule_is_removed(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$this->assertCount( 5, $instance->select_for_series( array( $post_id ) ) );

		delete_post_meta( $post_id, Meta::META_KEY );
		Meta::get_instance()->set_recurrence( $post_id );
		// The blob is now empty, so Meta defers the decision to shutdown
		// rather than clearing synchronously, so simulate shutdown firing.
		Meta::get_instance()->resolve_pending_recurrence();

		$this->assertNull( Rule::from_post( $post_id ), 'Failed to assert the mirrors were cleared by Meta.' );

		$written = $instance->project( $post_id );

		$this->assertSame( 0, $written, 'Failed to assert that project() returns 0 once the rule is gone.' );
		$this->assertCount(
			0,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that removing the rule orphaned no rows. They were deleted.'
		);
	}

	/**
	 * The same clearing, replayed through the real lifecycle wiring.
	 * The actual `wp_after_insert_post` and `shutdown` hooks are fired, not a
	 * hand-called sequence of the methods those hooks invoke. A
	 * hand-called sequence cannot fail even when the wiring it claims to test
	 * is broken. Inverting `maybe_project()`'s `wp_after_insert_post`
	 * priority and its dynamic `shutdown` priority so `Occurrences` runs
	 * *before* `Meta` leaves a hand-called version of this test
	 * green. Firing the hooks for real is what makes the ordering the
	 * deferred design depends on part of what is under test.
	 *
	 * @covers ::maybe_project
	 * @covers ::resolve_pending_projection
	 * @covers ::project
	 *
	 * @return void
	 */
	public function test_full_lifecycle_replay_deletes_rows_when_recurrence_blob_is_removed(): void {
		$post_id     = $this->create_and_project();
		$occurrences = Occurrences::get_instance();

		$this->assertCount( 5, $occurrences->select_for_series( array( $post_id ) ) );

		delete_post_meta( $post_id, Meta::META_KEY );

		// Fires the real hooks, in production order: wp_after_insert_post
		// (Meta::set_recurrence() at priority 10, Occurrences::maybe_project()
		// at priority 20), then shutdown (Meta's priority-10 resolution, then
		// Occurrences' priority-20 one). The hooks themselves decide the
		// order, and nothing here hand-sequences it.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'wp_after_insert_post', $post_id, get_post( $post_id ), true, null );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assertNull( Rule::from_post( $post_id ) );
		$this->assertCount(
			0,
			$occurrences->select_for_series( array( $post_id ) ),
			'Failed to assert that the full lifecycle replay cleared the series\' rows.'
		);
	}

	/**
	 * Coverage for `resolve_anchor()` returning null when the stored datetime
	 * cannot be parsed. A rule's mirrors can exist on a post whose own
	 * datetime meta never landed.
	 *
	 * @covers ::project
	 * @covers ::resolve_anchor
	 *
	 * @return void
	 */
	public function test_project_returns_zero_when_anchor_datetime_cannot_be_parsed(): void {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );
		Meta::get_instance()->set_recurrence( $post_id );

		delete_post_meta( $post_id, 'gatherpress_datetime_start' );
		delete_post_meta( $post_id, 'gatherpress_datetime_end' );

		$this->assertSame(
			0,
			Occurrences::get_instance()->project( $post_id ),
			'Failed to assert that project() returns 0 when the anchor datetime cannot be parsed.'
		);
	}

	/**
	 * Coverage for the real production path when a series' timezone becomes a
	 * fixed UTC offset: `Meta::set_recurrence()` re-runs with the new,
	 * genuinely-invalid raw timezone, its own `Timezone_Guard::assert_named()`
	 * check rejects it, and `clear_mirrors()` removes the rule mirrors.
	 * `Rule::from_post()` then returns null and `project()` clears the rows
	 * through the no-rule branch, never reaching
	 * `expand_or_clear()`'s own catch at all. An earlier version of this test
	 * changed only the datetime blob without re-running `Meta::set_recurrence()`,
	 * which left the mirrors (and therefore `Rule::from_post()`) stale and
	 * made `expand_or_clear()`'s catch arm look reachable for a case it is not
	 * reachable for in production.
	 *
	 * @covers ::project
	 * @covers ::resolve_projectable
	 *
	 * @return void
	 */
	public function test_project_clears_existing_rows_and_returns_zero_for_fixed_offset_timezone(): void {
		$post_id = $this->create_and_project();

		update_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $this->reference_anchor_start,
					'dateTimeEnd'   => $this->reference_anchor_end,
					'timezone'      => '+05:30',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );
		Meta::get_instance()->set_recurrence( $post_id );

		$this->assertNull(
			Rule::from_post( $post_id ),
			'Failed to assert that Meta cleared the mirrors for the now-invalid raw timezone.'
		);

		$instance = Occurrences::get_instance();
		$written  = $instance->project( $post_id );

		$this->assertSame(
			0,
			$written,
			'Failed to assert that project() returns 0 for a fixed-offset timezone rather than fataling.'
		);
		$this->assertCount(
			0,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that the previously projected rows were cleared.'
		);
	}

	/**
	 * Coverage for `expand_or_clear()`'s own catch, kept genuinely reachable
	 * by a scenario `Meta` cannot intercept: `Meta::read_timezone()` reads the
	 * raw `gatherpress_datetime` blob directly, so it passes a validly-named
	 * timezone. `Event::get_datetime()`, which `resolve_anchor()` reads
	 * through, applies the `gatherpress_timezone` filter *after* that
	 * validation. A misbehaving filter can still hand `expand_or_clear()` a
	 * fixed offset even though the mirrors (and `Rule::from_post()`) remain
	 * valid. This is the live scenario `resolve_anchor()`'s own unguarded
	 * `new DateTimeZone()` construction and `expand_or_clear()`'s try/catch
	 * both exist to survive without fataling the save.
	 *
	 * @covers ::project
	 * @covers ::expand_or_clear
	 *
	 * @return void
	 */
	public function test_project_clears_existing_rows_when_timezone_filter_injects_a_fixed_offset(): void {
		$post_id = $this->create_and_project();

		$this->assertInstanceOf(
			Rule::class,
			Rule::from_post( $post_id ),
			'Failed to assert that the rule mirrors are still valid before the filter runs.'
		);

		$filter = static fn() => '+05:30';
		add_filter( 'gatherpress_timezone', $filter );

		$instance = Occurrences::get_instance();
		$written  = $instance->project( $post_id );

		remove_filter( 'gatherpress_timezone', $filter );

		$this->assertSame(
			0,
			$written,
			'Failed to assert that project() returns 0 when the timezone filter injects a fixed offset.'
		);
		$this->assertCount(
			0,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that expand_or_clear() cleared the previously projected rows.'
		);
	}

	/**
	 * Coverage for `resolve_anchor()`'s own guard around `new DateTimeZone()`:
	 * a `gatherpress_timezone` filter can hand back a string that is not a
	 * fixed offset either, but something `DateTimeZone` rejects outright
	 * (`Not/AZone`). Without a try/catch there, this fatals the post save
	 * one line before `expand_or_clear()`'s own try/catch would ever run.
	 *
	 * @covers ::project
	 * @covers ::resolve_anchor
	 *
	 * @return void
	 */
	public function test_project_returns_zero_when_timezone_filter_injects_an_invalid_string(): void {
		$post_id = $this->create_and_project();

		$filter = static fn() => 'Not/AZone';
		add_filter( 'gatherpress_timezone', $filter );

		$instance = Occurrences::get_instance();
		$written  = $instance->project( $post_id );

		remove_filter( 'gatherpress_timezone', $filter );

		$this->assertSame(
			0,
			$written,
			'Failed to assert that project() returns 0 rather than fataling on an unconstructable timezone string.'
		);
	}

	/**
	 * Direct coverage for `build_occurrence_row()`'s duration arithmetic.
	 *
	 * Extracted-helper coverage: called from inside project()'s array_map,
	 * so a direct invoke guards against the known xdebug tracing gap for
	 * same-class helpers called from a tight loop.
	 *
	 * @covers ::build_occurrence_row
	 *
	 * @return void
	 */
	public function test_build_occurrence_row_applies_duration_from_anchor(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$start    = new DateTimeImmutable( '2026-09-15 18:00:00', $timezone );
		$span     = $start->diff( $start->modify( '+2 hours' ) );

		$row = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'build_occurrence_row',
			array( $start, $span, $timezone )
		);

		$this->assertSame(
			array(
				'recurrence_id'      => '20260915T180000',
				'datetime_start'     => '2026-09-15 18:00:00',
				'datetime_start_gmt' => '2026-09-15 22:00:00',
				'datetime_end'       => '2026-09-15 20:00:00',
				'datetime_end_gmt'   => '2026-09-16 00:00:00',
				'timezone'           => 'America/New_York',
			),
			$row,
			'Failed to assert that build_occurrence_row applies the anchor duration correctly.'
		);
	}

	/**
	 * A nominal 2-hour span applied to an occurrence
	 * landing on the fall-back DST transition (2026-11-01, America/New_York)
	 * must stay a nominal 2-hour span on the wall clock (01:00 -> 03:00),
	 * even though 10,800 real seconds elapse across the repeated hour. An
	 * absolute-seconds delta taken once from a non-transition anchor and
	 * reapplied via raw `modify( '+N seconds' )` is fragile precisely because
	 * it happens to agree with the calendar-decomposed `DateInterval` used
	 * here for whole-hour spans. This test therefore pins the measured
	 * wall-clock values directly, not values re-derived from reasoning about
	 * them.
	 *
	 * @covers ::build_occurrence_row
	 *
	 * @return void
	 */
	public function test_build_occurrence_row_preserves_nominal_span_across_fall_back(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = new DateTimeImmutable( '2026-09-03 18:00:00', $timezone );
		$span     = $anchor->diff( $anchor->modify( '+2 hours' ) );

		$start = new DateTimeImmutable( '2026-11-01 01:00:00', $timezone );

		$row = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'build_occurrence_row',
			array( $start, $span, $timezone )
		);

		$this->assertSame(
			'2026-11-01 03:00:00',
			$row['datetime_end'],
			'Failed to assert that the nominal wall-clock end stayed 03:00:00, not the absolute-time 02:00:00.'
		);
		$this->assertSame(
			'2026-11-01 08:00:00',
			$row['datetime_end_gmt'],
			'Failed to assert that the GMT end matches the nominal wall-clock end.'
		);
	}

	/**
	 * Coverage end to end through `project()`: an anchor whose own
	 * span crosses the fall-back transition (2026-10-31 22:00 -> 2026-11-01
	 * 02:00, a nominal 4 hours) must project every later occurrence with that
	 * same nominal 4-hour span, including ones that do not themselves touch a
	 * transition. `$anchor_start->diff( $anchor_end )` on the two
	 * *zoned* anchor datetimes reports their real elapsed time (5 hours, since
	 * the anchor itself spans the repeated hour) rather than their wall-clock
	 * difference, and reapplying that inflated span to every occurrence is
	 * exactly the corruption this method exists to prevent. The anchor's
	 * own stored row would then contradict the anchor it was derived from.
	 * `test_build_occurrence_row_applies_duration_from_anchor()` and
	 * `..._preserves_nominal_span_across_fall_back()` pass a hand-built
	 * `DateInterval` directly and so cannot catch this: this test is the only
	 * one that exercises `resolve_nominal_span()` itself.
	 *
	 * @covers ::project
	 * @covers ::resolve_nominal_span
	 *
	 * @return void
	 */
	public function test_project_preserves_nominal_span_for_an_anchor_spanning_fall_back(): void {
		$timezone     = new DateTimeZone( 'America/New_York' );
		$anchor_start = new DateTimeImmutable( '2026-10-31 22:00:00', $timezone );
		$anchor_end   = new DateTimeImmutable( '2026-11-01 02:00:00', $timezone );

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $anchor_start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $anchor_end->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'America/New_York',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );
		add_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'weekly',
					'interval'  => 1,
					'weekdays'  => array( (int) $anchor_start->format( 'w' ) ),
					'end_type'  => 'count',
					'count'     => 3,
				)
			)
		);
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		// The anchor's own row: its stored end must match the anchor it was derived from.
		$anchor_row = Occurrences::get_instance()->get( $post_id, Occurrences::recurrence_id( $anchor_start ) );

		$this->assertSame(
			'2026-11-01 02:00:00',
			$anchor_row['datetime_end'],
			'Failed to assert that the anchor\'s own row keeps the anchor\'s own stored end time.'
		);

		// A later occurrence that does not itself span a transition.
		$later_start = new DateTimeImmutable( '2026-11-07 22:00:00', $timezone );
		$later_row   = Occurrences::get_instance()->get( $post_id, Occurrences::recurrence_id( $later_start ) );

		$this->assertSame(
			'2026-11-08 02:00:00',
			$later_row['datetime_end'],
			'Failed to assert that a later occurrence kept the nominal 4-hour span, not the anchor\'s inflated 5 hours.'
		);
	}

	/**
	 * Coverage for `get()` returning null for a composite key that matches nothing.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_returns_null_for_missing_composite_key(): void {
		$post_id = $this->factory->post->create();

		$this->assertNull(
			Occurrences::get_instance()->get( $post_id, '20260903T180000' ),
			'Failed to assert that get() returns null for a composite key that matches nothing.'
		);
	}

	/**
	 * Coverage for `get()` returning a row for an existing composite key.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_returns_row_for_existing_composite_key(): void {
		$post_id = $this->create_and_project();

		$row = Occurrences::get_instance()->get( $post_id, '20260903T180000' );

		$this->assertSame( '2026-09-03 18:00:00', $row['datetime_start'] );
		$this->assertSame( Occurrences::STATUS_SCHEDULED, $row['status'] );
	}

	/**
	 * Coverage for `select_for_series()` returning an empty array for an empty post ID list.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_returns_empty_array_for_empty_post_ids(): void {
		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array() ),
			'Failed to assert that select_for_series returns an empty array for an empty post ID list.'
		);
	}

	/**
	 * `select_for_series()` accepts multiple post IDs and
	 * emits `series_post_id IN (…)`, never `= %d`.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_accepts_multiple_post_ids(): void {
		global $wpdb;

		$post_id_a = $this->create_and_project();
		$post_id_b = $this->create_and_project();
		$instance  = Occurrences::get_instance();

		$rows = $instance->select_for_series( array( $post_id_a, $post_id_b ) );

		$this->assertCount( 10, $rows, 'Failed to assert that rows from both series were returned.' );
		$this->assertStringContainsString(
			'series_post_id IN (',
			$wpdb->last_query,
			'Failed to assert that the query emits series_post_id IN (…), not = %d.'
		);
		$this->assertStringNotContainsString(
			'series_post_id = ',
			$wpdb->last_query,
			'Failed to assert that the query never emits series_post_id = %d.'
		);
	}

	/**
	 * Coverage for `select_for_series()`'s `status` argument.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_filters_by_status(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$instance->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED );

		$canceled = $instance->select_for_series(
			array( $post_id ),
			array( 'status' => Occurrences::STATUS_CANCELED )
		);

		$this->assertCount( 1, $canceled, 'Failed to assert that the status filter narrowed the result to one row.' );
		$this->assertSame( '20260903T180000', $canceled[0]['recurrence_id'] );
	}

	/**
	 * Coverage for total ordering on `select_for_series()`.
	 *
	 * `ORDER BY datetime_start_gmt` alone is not a total order, and
	 * `recurrence_id` cannot complete it: the identifier is derived from the
	 * local start, so two sibling posts of one series sharing a start share
	 * the identifier too. Only `series_post_id` breaks that tie. The emitted
	 * statement is pinned rather than the row order, for the same reason the
	 * horizon query's total-order test pins its statement: InnoDB returns
	 * tied rows in clustered-key order today, which for this table *is*
	 * `(series_post_id, recurrence_id)` order, so a row-order assertion
	 * passes with or without the tie-breakers.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_emits_a_total_order(): void {
		global $wpdb;

		$post_id = $this->create_and_project();

		Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertStringContainsString(
			'ORDER BY datetime_start_gmt ASC, series_post_id ASC, recurrence_id ASC',
			$wpdb->last_query,
			'Failed to assert that select_for_series breaks ties on series post ID and recurrence ID.'
		);
	}

	/**
	 * Coverage for `select_for_series()`'s `after` argument: the bound reads
	 * the effective end and is inclusive of it, so a row whose
	 * `datetime_end_gmt` equals the bound still returns.
	 *
	 * End-inclusive matches `select_upcoming()`'s definition of upcoming: an
	 * occurrence that has started but not ended is still one a forward-looking
	 * caller wants, and a bound on the start would drop it the moment it
	 * begins. The fixture's second occurrence ends exactly at the bound, so
	 * an exclusive `>` comparison fails here by dropping it.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_after_bound_is_end_inclusive(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-2 weeks' )->modify( '-1 hour' );

		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'count',
				'count'     => 4,
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			'America/New_York'
		);

		$second_end_gmt = $anchor->modify( '+7 days' )->modify( '+2 hours' )
			->setTimezone( new DateTimeZone( 'UTC' ) )
			->format( 'Y-m-d H:i:s' );

		$rows = Occurrences::get_instance()->select_for_series(
			array( $post_id ),
			array( 'after' => $second_end_gmt )
		);

		$this->assertSame(
			array(
				Occurrences::recurrence_id( $anchor->modify( '+7 days' ) ),
				Occurrences::recurrence_id( $anchor->modify( '+14 days' ) ),
				Occurrences::recurrence_id( $anchor->modify( '+21 days' ) ),
			),
			wp_list_pluck( $rows, 'recurrence_id' ),
			'Failed to assert that the after bound excludes ended rows and keeps the row ending exactly on it.'
		);
	}

	/**
	 * Coverage for `select_for_series()`'s `limit` argument: a limit slices
	 * the total order stably, so a narrower read is a strict prefix of a
	 * wider one with no row repeated or dropped across the boundary.
	 *
	 * Two sibling posts share one rule and one anchor, so every start
	 * instant and every `recurrence_id` is shared between them, the exact
	 * tie only `series_post_id` can break. The expected tuples interleave
	 * the two posts per start, lower post ID first.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_limit_slices_stably(): void {
		$post_id_a = $this->create_and_project();
		$post_id_b = $this->create_and_project();
		$low       = min( $post_id_a, $post_id_b );
		$high      = max( $post_id_a, $post_id_b );
		$post_ids  = array( $post_id_a, $post_id_b );
		$instance  = Occurrences::get_instance();

		$tuples = static fn( array $rows ) => array_map(
			static fn( array $row ) => array( (int) $row['series_post_id'], $row['recurrence_id'] ),
			$rows
		);

		$expected = array(
			array( $low, '20260903T180000' ),
			array( $high, '20260903T180000' ),
			array( $low, '20260915T180000' ),
			array( $high, '20260915T180000' ),
			array( $low, '20260917T180000' ),
			array( $high, '20260917T180000' ),
		);

		$wider = $instance->select_for_series( $post_ids, array( 'limit' => 6 ) );

		$this->assertSame(
			$expected,
			$tuples( $wider ),
			'Failed to assert that tied sibling rows interleave deterministically, lower series post ID first.'
		);

		$narrower = $instance->select_for_series( $post_ids, array( 'limit' => 3 ) );

		$this->assertSame(
			array_slice( $expected, 0, 3 ),
			$tuples( $narrower ),
			'Failed to assert that a narrower limit is a strict prefix of a wider one.'
		);
	}

	/**
	 * A negative `limit` never reaches the SQL as `LIMIT -1`.
	 *
	 * Same clamp contract as the horizon readers: MySQL rejects a negative
	 * `LIMIT` outright and `$wpdb` swallows the syntax error into an empty
	 * result plus a poisoned `last_error`, so the clamp to zero is what makes
	 * "selects nothing" a defined answer rather than a silent error.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_negative_limit_is_clamped(): void {
		global $wpdb;

		$post_id = $this->create_and_project();

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $post_id ), array( 'limit' => -1 ) ),
			'Failed to assert that a negative limit selects nothing.'
		);
		$this->assertSame(
			'',
			$wpdb->last_error,
			'Failed to assert that a negative limit produced no SQL error.'
		);
	}

	/**
	 * Coverage for `set_status()` updating a matching row and returning true.
	 *
	 * @covers ::set_status
	 *
	 * @return void
	 */
	public function test_set_status_updates_matching_row_and_returns_true(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$this->assertTrue( $instance->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED ) );
		$this->assertSame(
			Occurrences::STATUS_CANCELED,
			$instance->get( $post_id, '20260903T180000' )['status']
		);
	}

	/**
	 * A status write that changes nothing announces nothing.
	 *
	 * Re-canceling an already canceled date is a write on paper only: the row
	 * is byte-identical after it. Announcing it advances the series' SEQUENCE
	 * and invalidates every calendar response cache on the site for a change
	 * no client can observe, so subscribers re-ingest an unchanged component.
	 * The gate follows `move_to_post()`: announce on evidence of change,
	 * never on the statement having run.
	 *
	 * @covers ::set_status
	 *
	 * @return void
	 */
	public function test_setting_an_unchanged_status_announces_nothing(): void {
		$post_id   = $this->create_and_project();
		$instance  = Occurrences::get_instance();
		$announced = 0;

		$this->assertTrue(
			$instance->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED ),
			'Failed to cancel the date, so the repeat below would not be a repeat.'
		);

		add_action(
			'gatherpress_occurrences_changed',
			static function () use ( &$announced ): void {
				++$announced;
			}
		);

		$this->assertTrue(
			$instance->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED ),
			'The composite key still matches, so the caller is still told so.'
		);
		$this->assertSame(
			0,
			$announced,
			'A write that changed nothing must not invalidate every calendar on the site.'
		);
	}

	/**
	 * Coverage for `set_status()` returning false when the composite key matches nothing.
	 *
	 * @covers ::set_status
	 *
	 * @return void
	 */
	public function test_set_status_returns_false_when_no_row_matches(): void {
		$post_id = $this->factory->post->create();

		$this->assertFalse(
			Occurrences::get_instance()->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED ),
			'Failed to assert that set_status returns false when no row matches.'
		);
	}

	/**
	 * Pins the stored cancellation status literal as a storage contract.
	 *
	 * Every canceled occurrence row carries this literal in its `status`
	 * column, and rows outlive any later rename of the constant that wrote
	 * them. The value is therefore release-frozen as the US spelling
	 * `canceled`: changing it after release is a storage migration, not a
	 * rename.
	 *
	 * @coversNothing
	 *
	 * @return void
	 */
	public function test_cancellation_status_stored_value_is_release_frozen(): void {
		$this->assertSame(
			'canceled',
			Occurrences::STATUS_CANCELED,
			'Failed to assert that the stored cancellation value is the release-frozen US'
			. ' spelling "canceled". Changing it is a storage migration, not a rename.'
		);
	}

	/**
	 * A row vanishing mid-request reads as gone, not as updated.
	 *
	 * A concurrent rule save reprojects the series and can delete the very
	 * row a cancel request is about to update. The `query` filter below
	 * deletes the row at the last instant before the `UPDATE` executes, the
	 * narrowest reachable form of that race; reporting `true` for it hands
	 * the REST route a success it then cannot substantiate with a row.
	 *
	 * @covers ::set_status
	 *
	 * @return void
	 */
	public function test_set_status_returns_false_when_the_row_vanishes_mid_write(): void {
		global $wpdb;

		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();
		$table    = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$done     = false;

		$racer = static function ( string $query ) use ( &$done, $table, $post_id, $wpdb ): string {
			if ( ! $done && str_starts_with( $query, 'UPDATE' ) && str_contains( $query, $table ) ) {
				// Fire once: the DELETE below re-enters this filter.
				$done = true;

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare( 'DELETE FROM %i WHERE series_post_id = %d', $table, $post_id )
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}

			return $query;
		};

		add_filter( 'query', $racer );

		$result = $instance->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED );

		remove_filter( 'query', $racer );

		$this->assertTrue( $done, 'Fixture failure: the race must actually delete the row before the update.' );
		$this->assertFalse(
			$result,
			'A status write whose row vanished mid-request must report the row as gone, not as updated.'
		);
		$this->assertNull( $instance->get( $post_id, '20260903T180000' ) );
	}

	/**
	 * Writing the status a row already holds is a success, not a missing row.
	 *
	 * Zero affected rows is ambiguous in MySQL: a same-value write and a
	 * vanished row both report zero. The two must not be conflated, or a
	 * double-click on Cancel would surface a 404 for an occurrence that is
	 * sitting right there, already canceled.
	 *
	 * @covers ::set_status
	 *
	 * @return void
	 */
	public function test_set_status_same_value_write_still_reports_success(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$this->assertTrue( $instance->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED ) );
		$this->assertTrue(
			$instance->set_status( $post_id, '20260903T180000', Occurrences::STATUS_CANCELED ),
			'Re-writing the status a row already holds must still report success.'
		);
		$this->assertSame(
			Occurrences::STATUS_CANCELED,
			$instance->get( $post_id, '20260903T180000' )['status']
		);
	}

	/**
	 * Coverage for `set_status()` scoping by both `series_post_id` and
	 * `recurrence_id`. A recurrence_id that belongs to a different series
	 * must not be mutated through this post's ID, and vice versa.
	 *
	 * @covers ::set_status
	 *
	 * @return void
	 */
	public function test_set_status_scopes_by_both_columns(): void {
		$post_id_a = $this->create_and_project();
		$post_id_b = $this->create_and_project();
		$instance  = Occurrences::get_instance();

		$this->assertTrue(
			$instance->set_status( $post_id_a, '20260903T180000', Occurrences::STATUS_CANCELED ),
			'Failed to assert that setting status on the first series\' own row succeeded.'
		);

		$this->assertSame(
			Occurrences::STATUS_SCHEDULED,
			$instance->get( $post_id_b, '20260903T180000' )['status'],
			'Failed to assert that a second series\' row with the same recurrence_id was left untouched.'
		);
	}

	/**
	 * Coverage for `delete_for_post()` removing all rows for a series.
	 *
	 * @covers ::delete_for_post
	 *
	 * @return void
	 */
	public function test_delete_for_post_removes_all_rows(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$deleted = $instance->delete_for_post( $post_id );

		$this->assertSame( 5, $deleted, 'Failed to assert that delete_for_post reports five deleted rows.' );
		$this->assertCount(
			0,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that no rows remain for the series.'
		);
	}

	/**
	 * Coverage for `maybe_project()` skipping an unsupported post type.
	 *
	 * @covers ::maybe_project
	 *
	 * @return void
	 */
	public function test_maybe_project_skips_unsupported_post_type(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => 'post' ) );
		$instance = Occurrences::get_instance();

		$instance->maybe_project( $post_id );

		$this->assertFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_projection' ) ),
			'Failed to assert that no shutdown resolution was scheduled for an unsupported post type.'
		);
	}

	/**
	 * Coverage for `maybe_project()` projecting immediately when the blob is present.
	 *
	 * @covers ::maybe_project
	 *
	 * @return void
	 */
	public function test_maybe_project_projects_immediately_when_blob_present(): void {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );
		Meta::get_instance()->set_recurrence( $post_id );

		Occurrences::get_instance()->maybe_project( $post_id );

		$this->assertCount(
			5,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert that maybe_project() projected immediately when the blob was present.'
		);
	}

	/**
	 * Coverage for `maybe_project()` deferring to shutdown when no blob has landed yet.
	 *
	 * @covers ::maybe_project
	 *
	 * @return void
	 */
	public function test_maybe_project_defers_when_no_blob_yet(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$instance = Occurrences::get_instance();

		$instance->maybe_project( $post_id );

		$this->assertCount(
			0,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that nothing was projected before the blob landed.'
		);
		$this->assertNotFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_projection' ) ),
			'Failed to assert that a shutdown resolution was scheduled.'
		);

		// Drain the pending state so it does not leak into another test.
		$instance->resolve_pending_projection();
	}

	/**
	 * The priority gap on `shutdown`, rather than registration order, is what
	 * guarantees `Meta::resolve_pending_recurrence()` runs
	 * before `Occurrences::resolve_pending_projection()`. `has_action()`
	 * returning a truthy value accepts any priority, including one that would
	 * put `Occurrences` first and break the ordering the whole deferred
	 * design depends on, so the priorities themselves have to be asserted.
	 *
	 * @covers ::maybe_project
	 *
	 * @return void
	 */
	public function test_shutdown_priority_gap_runs_meta_before_occurrences(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$instance = Occurrences::get_instance();
		$meta     = Meta::get_instance();

		$meta->set_recurrence( $post_id );
		$instance->maybe_project( $post_id );

		$this->assertSame(
			20,
			has_action( 'shutdown', array( $instance, 'resolve_pending_projection' ) ),
			'Failed to assert that resolve_pending_projection is registered at priority 20.'
		);
		$this->assertLessThan(
			has_action( 'shutdown', array( $instance, 'resolve_pending_projection' ) ),
			has_action( 'shutdown', array( $meta, 'resolve_pending_recurrence' ) ),
			'Failed to assert that Meta\'s shutdown resolution runs at a lower priority than Occurrences\'.'
		);

		// Drain the pending state so it does not leak into another test.
		$meta->resolve_pending_recurrence();
		$instance->resolve_pending_projection();
	}

	/**
	 * Coverage for `resolve_pending_projection()` projecting a post whose blob
	 * arrived after `maybe_project()` ran but before shutdown.
	 *
	 * @covers ::maybe_project
	 * @covers ::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_resolve_pending_projection_projects_pending_posts(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$instance = Occurrences::get_instance();

		// wp_after_insert_post fires before the blob-writing caller runs.
		$instance->maybe_project( $post_id );

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $this->reference_anchor_start,
					'dateTimeEnd'   => $this->reference_anchor_end,
					'timezone'      => 'America/New_York',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );
		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::WEEKLY_RULE ) );
		Meta::get_instance()->set_recurrence( $post_id );

		$instance->resolve_pending_projection();

		$this->assertCount(
			5,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that resolve_pending_projection projected the late-arriving rule.'
		);
	}

	/**
	 * Coverage for `resolve_pending_projection()` skipping a post whose type no
	 * longer supports `gatherpress-event-date` by the time shutdown runs.
	 *
	 * What the guard has to be measured against is not an empty table. An
	 * unsupported post type reaching `run_projection()` still resolves no
	 * anchor, because `Event`'s own constructor refuses to wrap it, so a
	 * fixture with no occurrence rows produces the same empty result whether
	 * this guard runs or not. The rule mirrors, however, survive a post type
	 * change, so `resolve_projectable()` finds a `Rule`, fails on the anchor,
	 * and takes its cleanup arm with the deferred path's `$was_recurring`
	 * true. That deletes every existing occurrence row. The fixture is
	 * therefore an already-projected series carrying its five reference rows,
	 * and the guard is what keeps them.
	 *
	 * @covers ::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_resolve_pending_projection_skips_unsupported_post_type(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$this->assertCount(
			5,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that the fixture was projected before the post type changed.'
		);

		Utility::set_and_get_hidden_property( $instance, 'pending_projection', array( $post_id => true ) );

		// The support disappears between `maybe_project()` deferring the post
		// and `shutdown` running, which is the race the guard exists for.
		set_post_type( $post_id, 'post' );

		$this->assertInstanceOf(
			Rule::class,
			Rule::from_post( $post_id ),
			'Failed to assert that the rule mirrors survived the post type change.'
		);

		$instance->resolve_pending_projection();

		$this->assertCount(
			5,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that an unsupported post type was skipped rather than cleaned up.'
		);
	}

	/**
	 * Coverage for `maybe_delete_for_post()` skipping an unsupported post type.
	 *
	 * @covers ::maybe_delete_for_post
	 *
	 * @return void
	 */
	public function test_maybe_delete_for_post_skips_unsupported_post_type(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		// Simulate the post type no longer supporting gatherpress-event-date
		// by the time delete_post fires. The guard is what is under test, not
		// an empty table.
		set_post_type( $post_id, 'post' );

		$instance->maybe_delete_for_post( $post_id );

		$this->assertCount(
			5,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that an unsupported post type left the series untouched.'
		);
	}

	/**
	 * Coverage for `maybe_delete_for_post()` deleting rows for a supported post type.
	 *
	 * @covers ::maybe_delete_for_post
	 *
	 * @return void
	 */
	public function test_maybe_delete_for_post_deletes_rows_for_supported_post_type(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$instance->maybe_delete_for_post( $post_id );

		$this->assertCount(
			0,
			$instance->select_for_series( array( $post_id ) ),
			'Failed to assert that a supported post type\'s series rows were deleted.'
		);
	}

	/**
	 * Coverage for `select_upcoming()` interleaving recurring and non-recurring
	 * events in one ascending list, each entry carrying its own identity.
	 *
	 * Asserting only that the recurring post ID is present, and only
	 * checking null-ness for the *non*-recurring entry, does not exercise
	 * occurrence identity at all. `row_to_ref()` could hardcode
	 * `recurrence_id = null` for every row and this test would still pass, while every real caller of
	 * `select_upcoming()` would silently lose occurrence identity for every
	 * recurring series. Asserting the recurring entry's exact, expected
	 * `Ymd\THis` recurrence ID is what makes that change fail here.
	 *
	 * @covers ::select_upcoming
	 * @covers ::select_by_horizon
	 * @covers ::row_to_ref
	 *
	 * @return void
	 */
	public function test_select_upcoming_returns_occurrence_refs_ordered_ascending(): void {
		$timezone        = new DateTimeZone( 'America/New_York' );
		$now             = new DateTimeImmutable( 'now', $timezone );
		$recurring_start = $now->modify( '+10 days' )->setTime( 18, 0, 0 );

		$recurring_post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $recurring_start->format( 'w' ) ),
				'end_type'  => 'count',
				'count'     => 3,
			),
			$recurring_start,
			$recurring_start->modify( '+2 hours' ),
			'America/New_York'
		);

		$plain_start   = $now->modify( '+15 days' )->setTime( 12, 0, 0 );
		$plain_post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		add_post_meta(
			$plain_post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $plain_start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $plain_start->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'America/New_York',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $plain_post_id );

		$refs = Occurrences::get_instance()->select_upcoming( 20 );

		$this->assertNotEmpty( $refs, 'Failed to assert that upcoming occurrences were returned.' );

		foreach ( $refs as $ref ) {
			$this->assertInstanceOf( Occurrence_Ref::class, $ref );
		}

		$post_ids = wp_list_pluck( $refs, 'post_id' );

		$this->assertContains( $recurring_post_id, $post_ids );
		$this->assertContains( $plain_post_id, $post_ids );

		$plain_ref = current(
			array_filter( $refs, fn( Occurrence_Ref $ref ) => $plain_post_id === $ref->post_id )
		);

		$this->assertNull(
			$plain_ref->recurrence_id,
			'Failed to assert that a non-recurring event carries a null recurrence_id.'
		);

		$recurring_ref = current(
			array_filter( $refs, fn( Occurrence_Ref $ref ) => $recurring_post_id === $ref->post_id )
		);

		$this->assertSame(
			Occurrences::recurrence_id( $recurring_start ),
			$recurring_ref->recurrence_id,
			'Failed to assert that a recurring event carries its expected, non-null recurrence_id.'
		);

		$starts = wp_list_pluck( $refs, 'datetime_start_gmt' );
		$sorted = $starts;
		sort( $sorted );

		$this->assertSame( $sorted, $starts, 'Failed to assert that upcoming occurrences are ordered ascending.' );
	}

	/**
	 * Direct coverage for `row_to_ref()`'s non-null branch: a row whose
	 * `recurrence_id` column is a string produces an `Occurrence_Ref` that
	 * carries the same string, not null.
	 *
	 * @covers ::row_to_ref
	 *
	 * @return void
	 */
	public function test_row_to_ref_carries_a_non_null_recurrence_id(): void {
		$ref = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'row_to_ref',
			array(
				array(
					'post_id'             => 42,
					'recurrence_id'       => '20260903T180000',
					'effective_start_gmt' => '2026-09-03 22:00:00',
				),
			)
		);

		$this->assertSame( 42, $ref->post_id );
		$this->assertSame( '20260903T180000', $ref->recurrence_id );
		$this->assertSame( '2026-09-03 22:00:00', $ref->datetime_start_gmt );
	}

	/**
	 * Direct coverage for `row_to_ref()`'s null branch: a row whose
	 * `recurrence_id` column is `null` (a non-recurring event, no occurrence
	 * row) produces an `Occurrence_Ref` with a null `recurrence_id`.
	 *
	 * @covers ::row_to_ref
	 *
	 * @return void
	 */
	public function test_row_to_ref_carries_a_null_recurrence_id(): void {
		$ref = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'row_to_ref',
			array(
				array(
					'post_id'             => 42,
					'recurrence_id'       => null,
					'effective_start_gmt' => '2026-09-03 22:00:00',
				),
			)
		);

		$this->assertSame( 42, $ref->post_id );
		$this->assertNull( $ref->recurrence_id );
	}

	/**
	 * A fully canceled series must not reappear in
	 * `select_upcoming()` as if it were a non-recurring event at its original
	 * anchor date. Without the `NOT EXISTS` guard, every occurrence row
	 * failing the `status = 'scheduled'` join predicate falls through the
	 * same `NULL` branch a genuinely non-recurring event uses, and the
	 * `COALESCE` fallback resurrects the series at its anchor.
	 *
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_select_upcoming_omits_a_fully_canceled_series(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$now      = new DateTimeImmutable( 'now', $timezone );
		$start    = $now->modify( '+10 days' )->setTime( 18, 0, 0 );

		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $start->format( 'w' ) ),
				'end_type'  => 'count',
				'count'     => 3,
			),
			$start,
			$start->modify( '+2 hours' ),
			'America/New_York'
		);

		$instance = Occurrences::get_instance();

		foreach ( $instance->select_for_series( array( $post_id ) ) as $row ) {
			$instance->set_status( $post_id, $row['recurrence_id'], Occurrences::STATUS_CANCELED );
		}

		$refs     = $instance->select_upcoming( 50 );
		$post_ids = wp_list_pluck( $refs, 'post_id' );

		$this->assertNotContains(
			$post_id,
			$post_ids,
			'Failed to assert that a fully canceled series is absent from select_upcoming().'
		);
	}

	/**
	 * Coverage for `select_upcoming()` returning an empty array when no post
	 * type declares `gatherpress-event-date` support.
	 *
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_select_upcoming_returns_empty_array_when_no_supported_post_types(): void {
		$supported = get_post_types_by_support( 'gatherpress-event-date' );

		foreach ( $supported as $post_type ) {
			remove_post_type_support( $post_type, 'gatherpress-event-date' );
		}

		try {
			$this->assertSame(
				array(),
				Occurrences::get_instance()->select_upcoming( 10 ),
				'Failed to assert that select_upcoming returns an empty array with no supported post types.'
			);
		} finally {
			foreach ( $supported as $post_type ) {
				add_post_type_support( $post_type, 'gatherpress-event-date' );
			}
		}
	}

	/**
	 * The anchor-only arm of `select_by_horizon()` — the statement every
	 * site with no recurring events runs — keeps its boundary predicate in
	 * `WHERE` on the real `datetime_end_gmt` column through an `INNER JOIN`,
	 * never in `HAVING` on the projected alias. `HAVING` on the alias forces
	 * a temporary table plus filesort, and the `LEFT JOIN` it filtered was
	 * effectively inner already: a post with no events-table row yields
	 * `NULL`, which fails the boundary comparison in either direction. The
	 * result set is identical either way, so the pin here is the statement's
	 * shape, in both directions.
	 *
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_anchor_only_arm_filters_in_where_through_an_inner_join(): void {
		global $wpdb;

		$timezone = new DateTimeZone( 'UTC' );
		$now      = new DateTimeImmutable( 'now', $timezone );
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $now->modify( '+2 days' )->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $now->modify( '+2 days +2 hours' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );

		update_option( Query::HAS_RECURRING_OPTION, '0' );

		$events_table      = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$occurrences_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$refs = Occurrences::get_instance()->select_upcoming( 10 );
		$sql  = $wpdb->last_query;

		$this->assertContains(
			$post_id,
			wp_list_pluck( $refs, 'post_id' ),
			'Failed to assert that the anchor-only arm returns the upcoming event.'
		);
		$this->assertStringNotContainsString(
			$occurrences_table,
			$sql,
			'Failed to assert that the anchor-only arm never names the occurrence table.'
		);
		$this->assertStringContainsString(
			"INNER JOIN `{$events_table}`",
			$sql,
			'Failed to assert that the anchor-only arm joins the events table with INNER JOIN.'
		);
		$this->assertStringNotContainsString(
			'LEFT JOIN',
			$sql,
			'Failed to assert that the anchor-only arm emits no LEFT JOIN.'
		);
		$this->assertStringNotContainsString(
			'HAVING',
			$sql,
			'Failed to assert that the anchor-only arm emits no HAVING clause.'
		);
		$this->assertStringContainsString(
			"AND `{$events_table}`.datetime_end_gmt >= ",
			$sql,
			'Failed to assert that the upcoming boundary filters in WHERE on the real column.'
		);

		Occurrences::get_instance()->select_past( 10 );

		$this->assertStringContainsString(
			"AND `{$events_table}`.datetime_end_gmt < ",
			$wpdb->last_query,
			'Failed to assert that the past boundary filters in WHERE on the real column.'
		);
		$this->assertStringNotContainsString(
			'HAVING',
			$wpdb->last_query,
			'Failed to assert that the past direction emits no HAVING clause.'
		);
	}

	/**
	 * A published event post with no events-table row is absent from the
	 * anchor-only arm in both directions — the same result the previous
	 * `LEFT JOIN` + `HAVING` shape produced, since `NULL` fails the boundary
	 * comparison either way. The `INNER JOIN` makes that elimination
	 * structural instead of incidental.
	 *
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_anchor_only_arm_omits_an_event_with_no_events_table_row(): void {
		global $wpdb;

		$timezone = new DateTimeZone( 'UTC' );
		$now      = new DateTimeImmutable( 'now', $timezone );
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $now->modify( '+2 days' )->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $now->modify( '+2 days +2 hours' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );

		$events_table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to strip the events-table row from a published post.
		$wpdb->delete( $events_table, array( 'post_id' => $post_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		update_option( Query::HAS_RECURRING_OPTION, '0' );

		$instance = Occurrences::get_instance();

		$this->assertNotContains(
			$post_id,
			wp_list_pluck( $instance->select_upcoming( 50 ), 'post_id' ),
			'Failed to assert that a rowless event post is absent from select_upcoming().'
		);
		$this->assertNotContains(
			$post_id,
			wp_list_pluck( $instance->select_past( 50 ), 'post_id' ),
			'Failed to assert that a rowless event post is absent from select_past().'
		);
	}

	/**
	 * Coverage for `select_past()` ordering descending and respecting the `status` argument.
	 *
	 * @covers ::select_past
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_select_past_returns_occurrence_refs_ordered_descending(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => '2020-01-01 12:00:00',
					'dateTimeEnd'   => '2020-01-01 13:00:00',
					'timezone'      => 'America/New_York',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );

		$refs = Occurrences::get_instance()->select_past( 10, array( 'status' => Occurrences::STATUS_SCHEDULED ) );

		$this->assertNotEmpty( $refs, 'Failed to assert that past occurrences were returned.' );

		$starts = wp_list_pluck( $refs, 'datetime_start_gmt' );
		$sorted = $starts;
		rsort( $sorted );

		$this->assertSame( $sorted, $starts, 'Failed to assert that past occurrences are ordered descending.' );
	}

	/**
	 * A running non-recurring event is upcoming, never past.
	 *
	 * The repository defines "upcoming" inclusively, the way
	 * `Event\Query::get_datetime_comparison_column()` does for the admin
	 * list: the buckets split at `datetime_end_gmt`, so an event that has
	 * started but not finished is still the one an upcoming list should be
	 * showing, and it appears in exactly one bucket. Bounding on the start
	 * instead demotes every running event into the past bucket the moment it
	 * begins.
	 *
	 * @covers ::select_upcoming
	 * @covers ::select_past
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_running_event_is_upcoming_not_past(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$start    = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-1 hour' );

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'America/New_York',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );

		$upcoming_ids = wp_list_pluck( Occurrences::get_instance()->select_upcoming( 50 ), 'post_id' );
		$past_ids     = wp_list_pluck( Occurrences::get_instance()->select_past( 50 ), 'post_id' );

		$this->assertContains(
			$post_id,
			$upcoming_ids,
			'Failed to assert that a running event still counts as upcoming.'
		);
		$this->assertNotContains(
			$post_id,
			$past_ids,
			'Failed to assert that a running event is not classified as past.'
		);
	}

	/**
	 * A running occurrence of a recurring series is upcoming, never past.
	 *
	 * Same inclusive-upcoming boundary as the non-recurring case, on the
	 * occurrence branch of the `COALESCE`: the bucket split reads the
	 * occurrence row's own end, so the series' in-progress occurrence stays
	 * in the upcoming list alongside its future siblings.
	 *
	 * @covers ::select_upcoming
	 * @covers ::select_past
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_running_occurrence_is_upcoming_not_past(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-1 hour' );

		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'count',
				'count'     => 3,
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			'America/New_York'
		);

		$running_id = Occurrences::recurrence_id( $anchor );

		$in_upcoming = array_filter(
			Occurrences::get_instance()->select_upcoming( 50 ),
			static fn( Occurrence_Ref $ref ) => $post_id === $ref->post_id && $running_id === $ref->recurrence_id
		);
		$in_past     = array_filter(
			Occurrences::get_instance()->select_past( 50 ),
			static fn( Occurrence_Ref $ref ) => $post_id === $ref->post_id && $running_id === $ref->recurrence_id
		);

		$this->assertNotEmpty(
			$in_upcoming,
			'Failed to assert that the running occurrence still counts as upcoming.'
		);
		$this->assertEmpty(
			$in_past,
			'Failed to assert that the running occurrence is not classified as past.'
		);
	}

	/**
	 * Create a "never"-ending weekly series anchored a few weeks in the past,
	 * projected with a short horizon so it starts out needing a top-up once
	 * the horizon filter is restored to its default.
	 *
	 * The extra hour in the anchor keeps every projected occurrence off the
	 * second this fixture is created on. Anchored at exactly `now -3 weeks`,
	 * the weekly expansion lands one occurrence on that same wall-clock
	 * second, and any strict past/upcoming partition taken against a "now"
	 * read moments later flips its membership on whether the clock ticked in
	 * between. An hour is a gap no execution-time jitter can cross.
	 *
	 * @since 0.36.0
	 *
	 * @param int $horizon_months Horizon, in months, used only while creating
	 *                             the fixture. Smaller values produce a
	 *                             more-stale series once the filter is
	 *                             removed and the real horizon applies again.
	 *
	 * @return array{0: int, 1: DateTimeImmutable} The post ID and its anchor.
	 */
	protected function create_short_horizon_never_ending_series( int $horizon_months = 1 ): array {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-3 weeks -1 hour' );

		$short_horizon = static fn() => $horizon_months;
		add_filter( 'gatherpress_recurrence_horizon_months', $short_horizon );

		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'never',
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			'America/New_York'
		);

		remove_filter( 'gatherpress_recurrence_horizon_months', $short_horizon );

		return array( $post_id, $anchor );
	}

	/**
	 * Create a `COUNT`-bounded weekly series, fully in the past and complete.
	 *
	 * @since 0.36.0
	 *
	 * @param int $count Occurrence count.
	 *
	 * @return int The projected post ID.
	 */
	protected function create_completed_count_series( int $count = 3 ): int {
		$timezone = new DateTimeZone( 'America/New_York' );
		$far_past = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-3 years' );

		return $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $far_past->format( 'w' ) ),
				'end_type'  => 'count',
				'count'     => $count,
			),
			$far_past,
			$far_past->modify( '+2 hours' ),
			'America/New_York'
		);
	}

	/**
	 * Create an `UNTIL`-bounded weekly series whose `until` has already
	 * passed. It is complete in the same sense a `COUNT`-bounded series is
	 * complete: `Expander::expand()`'s `past_until()` guard means nothing
	 * further will ever be produced for it, no matter how far its latest
	 * projected occurrence sits below `resolve_top_up_cutoff()`.
	 *
	 * @since 0.36.0
	 *
	 * @return int The projected post ID.
	 */
	protected function create_completed_until_series(): int {
		$timezone = new DateTimeZone( 'America/New_York' );
		$far_past = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-3 years' );
		$until    = $far_past->modify( '+3 weeks' );

		return $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $far_past->format( 'w' ) ),
				'end_type'  => 'until',
				'until'     => $until->format( 'Y-m-d' ),
			),
			$far_past,
			$far_past->modify( '+2 hours' ),
			'America/New_York'
		);
	}

	/**
	 * Coverage for the horizon top-up: a scheduled sweep re-runs `project()` for a
	 * long-running series whose projected horizon is running short,
	 * extending it, while every occurrence already in the past survives.
	 * Attendees' RSVPs hang off past occurrences.
	 *
	 * @covers ::top_up
	 * @covers ::select_series_needing_top_up
	 * @covers ::resolve_top_up_cutoff
	 *
	 * @return void
	 */
	public function test_scheduled_top_up_extends_the_horizon_and_retains_past_occurrences(): void {
		list( $post_id ) = $this->create_short_horizon_never_ending_series();

		Query::refresh_has_recurring_events();

		$before   = Occurrences::get_instance()->select_for_series( array( $post_id ) );
		$now_gmt  = current_time( 'mysql', true );
		$past_ids = wp_list_pluck(
			array_filter( $before, static fn( $row ) => $row['datetime_start_gmt'] < $now_gmt ),
			'recurrence_id'
		);

		$this->assertNotEmpty(
			$past_ids,
			'Failed to assert that the fixture produced a past occurrence to protect.'
		);

		$far_future = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->modify( '+6 months' )
			->format( 'Y-m-d H:i:s' );

		$this->assertEmpty(
			array_filter( $before, static fn( $row ) => $row['datetime_start_gmt'] > $far_future ),
			'Failed to assert that the short-horizon fixture had nothing projected six months out yet.'
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- SWEEP_ACTION is a gatherpress_-prefixed class constant.
		do_action( Projection_Cron::SWEEP_ACTION );

		$after     = Occurrences::get_instance()->select_for_series( array( $post_id ) );
		$after_ids = wp_list_pluck( $after, 'recurrence_id' );

		foreach ( $past_ids as $recurrence_id ) {
			$this->assertContains(
				$recurrence_id,
				$after_ids,
				'Failed to assert that a past occurrence survived the scheduled top-up.'
			);
		}

		$this->assertNotEmpty(
			array_filter( $after, static fn( $row ) => $row['datetime_start_gmt'] > $far_future ),
			'Failed to assert that the scheduled top-up extended the horizon six months out.'
		);
	}

	/**
	 * Coverage for the horizon top-up: the scheduled sweep must issue no query against the
	 * occurrence table at all on a site with no recurring events. This is
	 * checked via a `$wpdb->queries` capture rather than the sweep's return
	 * value, matching
	 * the zero-query test above for the save path.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Projection_Cron::run_sweep
	 *
	 * @return void
	 */
	public function test_scheduled_job_performs_no_writes_on_a_site_with_no_recurring_events(): void {
		global $wpdb;

		Query::refresh_has_recurring_events();
		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert that the fixture site has no recurring events.'
		);

		$occurrences_table  = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$query_count_before = count( $wpdb->queries );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- SWEEP_ACTION is a gatherpress_-prefixed class constant.
		do_action( Projection_Cron::SWEEP_ACTION );

		$queries_since       = array_slice( $wpdb->queries, $query_count_before );
		$touched_occurrences = array_values(
			array_filter(
				$queries_since,
				static function ( $query ) use ( $occurrences_table ) {
					return str_contains( $query[0], $occurrences_table );
				}
			)
		);

		$this->assertSame(
			array(),
			$touched_occurrences,
			'Failed to assert that the scheduled sweep issued no query against the occurrence table.'
		);
	}

	/**
	 * Coverage for the horizon top-up: a `COUNT`-bounded rule is already complete and must
	 * never be re-projected by the sweep, however far its (fixed, final)
	 * latest occurrence sits in the past.
	 *
	 * Asserts on the candidate list and on a `$wpdb->queries` capture, not on
	 * row equality. `project()` is value-idempotent, so a row-equality
	 * assertion (`assertSame( $before, $after )`) would pass whether or not
	 * the sweep actually re-projected this series, and is decorative on its
	 * own. This series is the only recurring series present, so "the sweep
	 * issues no write against the occurrence table" is unambiguous proof it
	 * was never a candidate, not a coincidence of unchanged values.
	 *
	 * @covers ::select_series_needing_top_up
	 * @covers ::top_up
	 *
	 * @return void
	 */
	public function test_count_bounded_rule_is_not_re_projected_by_the_sweep(): void {
		global $wpdb;

		$post_id = $this->create_completed_count_series( 3 );

		$this->assertCount(
			3,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert the count-bounded fixture wrote exactly 3 rows.'
		);

		$this->assertNotContains(
			$post_id,
			Occurrences::get_instance()->select_series_needing_top_up( 100 ),
			'Failed to assert that select_series_needing_top_up excludes a count-bounded series.'
		);

		Query::refresh_has_recurring_events();

		$occurrences_table  = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$query_count_before = count( $wpdb->queries );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- SWEEP_ACTION is a gatherpress_-prefixed class constant.
		do_action( Projection_Cron::SWEEP_ACTION );

		$queries_since       = array_slice( $wpdb->queries, $query_count_before );
		$touched_occurrences = array_values(
			array_filter(
				$queries_since,
				static function ( $query ) use ( $occurrences_table ) {
					return str_contains( $query[0], $occurrences_table )
						&& (bool) preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE)/i', $query[0] );
				}
			)
		);

		$this->assertSame(
			array(),
			$touched_occurrences,
			'Failed to assert that the sweep issued no write against the occurrence table for a count-bounded series.'
		);
	}

	/**
	 * Coverage for the horizon top-up: an `UNTIL`-bounded rule whose latest projected
	 * occurrence has already reached its `until` date is complete in the
	 * same sense a `COUNT`-bounded rule is complete. `Expander::expand()`'s
	 * `past_until()` guard means nothing further will ever be produced, so
	 * it must never be re-projected by the sweep either.
	 *
	 * @covers ::select_series_needing_top_up
	 * @covers ::has_reached_until
	 *
	 * @return void
	 */
	public function test_until_bounded_rule_is_not_re_projected_by_the_sweep(): void {
		global $wpdb;

		$post_id = $this->create_completed_until_series();

		$rows_before = Occurrences::get_instance()->select_for_series( array( $post_id ) );
		$this->assertNotEmpty( $rows_before, 'Failed to assert the until-bounded fixture wrote at least one row.' );

		$this->assertNotContains(
			$post_id,
			Occurrences::get_instance()->select_series_needing_top_up( 100 ),
			'Failed to assert that select_series_needing_top_up excludes a completed until-bounded series.'
		);

		Query::refresh_has_recurring_events();

		$occurrences_table  = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$query_count_before = count( $wpdb->queries );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- SWEEP_ACTION is a gatherpress_-prefixed class constant.
		do_action( Projection_Cron::SWEEP_ACTION );

		$queries_since       = array_slice( $wpdb->queries, $query_count_before );
		$touched_occurrences = array_values(
			array_filter(
				$queries_since,
				static function ( $query ) use ( $occurrences_table ) {
					return str_contains( $query[0], $occurrences_table )
						&& (bool) preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE)/i', $query[0] );
				}
			)
		);

		$this->assertSame(
			array(),
			$touched_occurrences,
			'Failed to assert that the sweep issued no write against the occurrence table for a completed'
				. ' until-bounded series.'
		);
	}

	/**
	 * Coverage for the horizon top-up: three completed series with lower post IDs must not
	 * starve a genuinely open-ended, stale series out of a smaller batch.
	 * `LIMIT` with no `ORDER BY` deterministically returns the lowest-ID group
	 * keys, so three completed
	 * `until` series created before an open-ended one would occupy every slot
	 * of a batch of 3 forever, and the open-ended series would never
	 * reach its own real horizon. That is exactly the starvation the top-up
	 * exists to prevent.
	 *
	 * @covers ::select_series_needing_top_up
	 * @covers ::top_up
	 *
	 * @return void
	 */
	public function test_completed_series_do_not_starve_an_open_ended_series_out_of_the_batch(): void {
		$this->create_completed_until_series();
		$this->create_completed_until_series();
		$this->create_completed_until_series();

		list( $open_ended_id ) = $this->create_short_horizon_never_ending_series();

		Query::refresh_has_recurring_events();

		$far_future = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->modify( '+6 months' )
			->format( 'Y-m-d H:i:s' );

		$before_rows = Occurrences::get_instance()->select_for_series( array( $open_ended_id ) );
		$this->assertEmpty(
			array_filter( $before_rows, static fn( $row ) => $row['datetime_start_gmt'] > $far_future ),
			'Failed to assert the open-ended fixture had nothing projected six months out before the sweep.'
		);

		$candidates = Occurrences::get_instance()->select_series_needing_top_up( 3 );

		$this->assertSame(
			array( $open_ended_id ),
			$candidates,
			'Failed to assert that the completed until series were excluded, leaving only the'
				. ' open-ended series as a candidate.'
		);

		$filter = static fn() => 3;
		add_filter( 'gatherpress_recurrence_top_up_batch_size', $filter );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- SWEEP_ACTION is a gatherpress_-prefixed class constant.
		do_action( Projection_Cron::SWEEP_ACTION );

		remove_filter( 'gatherpress_recurrence_top_up_batch_size', $filter );

		$after_rows = Occurrences::get_instance()->select_for_series( array( $open_ended_id ) );

		$this->assertNotEmpty(
			array_filter( $after_rows, static fn( $row ) => $row['datetime_start_gmt'] > $far_future ),
			'Failed to assert that the open-ended series was selected and extended past six months'
				. ' despite three completed series existing at lower post IDs.'
		);
	}

	/**
	 * Coverage for the horizon top-up: a trashed series must not stay in the
	 * maintenance batch while another live recurrence keeps the sweep enabled.
	 *
	 * WordPress retains post meta on trash, so the trashed series' `end_type`
	 * mirror is still in `wp_postmeta` when the candidate query runs. Driven
	 * from meta alone, the selector would re-project a trashed series on every
	 * sweep for as long as any other live recurrence exists, because the flag
	 * that gates the sweep stays `'1'` for the published series. The candidate
	 * query has to consult the post's own status.
	 *
	 * @covers ::select_series_needing_top_up
	 *
	 * @return void
	 */
	public function test_trashed_series_is_excluded_from_top_up_while_a_published_series_remains(): void {
		list( $published_id ) = $this->create_short_horizon_never_ending_series();
		list( $trashed_id )   = $this->create_short_horizon_never_ending_series();

		// Trashing updates the post, which re-projects the series through
		// `wp_after_insert_post`. Hold the short horizon through the trash so
		// the trashed series is still stale afterward; without this, the
		// trash-time re-projection freshens it and the exclusion assertion
		// below would pass for a reason unrelated to post status.
		$short_horizon = static fn() => 1;
		add_filter( 'gatherpress_recurrence_horizon_months', $short_horizon );

		wp_trash_post( $trashed_id );

		remove_filter( 'gatherpress_recurrence_horizon_months', $short_horizon );

		$candidates = Occurrences::get_instance()->select_series_needing_top_up( 100 );

		$this->assertContains(
			$published_id,
			$candidates,
			'Failed to assert that the published stale series stays a top-up candidate.'
		);
		$this->assertNotContains(
			$trashed_id,
			$candidates,
			'Failed to assert that a trashed series is excluded from the top-up batch.'
		);
		$this->assertSame(
			'1',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that the remaining published series keeps the sweep enabled.'
		);
	}

	/**
	 * Coverage for the horizon top-up: `select_series_needing_top_up()` orders by
	 * staleness, not by `series_post_id`, so a batch smaller than the
	 * candidate pool cannot starve the most-overdue series behind
	 * lower-post-ID, less-stale ones. Created deliberately out of staleness
	 * order (least stale first, most stale last) so the lowest post IDs would
	 * win under a naive `LIMIT`-with-no-`ORDER BY` read, and the assertion
	 * would fail if `ORDER BY` were removed.
	 *
	 * @covers ::select_series_needing_top_up
	 *
	 * @return void
	 */
	public function test_select_series_needing_top_up_orders_by_staleness(): void {
		list( $least_stale ) = $this->create_short_horizon_never_ending_series( 6 );
		list( $middle )      = $this->create_short_horizon_never_ending_series( 3 );
		list( $most_stale )  = $this->create_short_horizon_never_ending_series( 1 );

		$candidates = Occurrences::get_instance()->select_series_needing_top_up( 2 );

		$this->assertSame(
			array( $most_stale, $middle ),
			$candidates,
			'Failed to assert that a smaller batch selects the most overdue series first, not the lowest post IDs.'
		);
	}

	/**
	 * Direct coverage for `select_series_needing_top_up()`'s `LIMIT`: only up
	 * to the requested number of stale series are returned.
	 *
	 * @covers ::select_series_needing_top_up
	 *
	 * @return void
	 */
	public function test_select_series_needing_top_up_respects_the_limit(): void {
		$this->create_short_horizon_never_ending_series();
		$this->create_short_horizon_never_ending_series();

		$candidates = Occurrences::get_instance()->select_series_needing_top_up( 1 );

		$this->assertCount( 1, $candidates, 'Failed to assert that select_series_needing_top_up respects its limit.' );
	}

	/**
	 * Coverage for the `gatherpress_recurrence_top_up_margin_days` filter.
	 *
	 * @covers ::resolve_top_up_cutoff
	 *
	 * @return void
	 */
	public function test_resolve_top_up_cutoff_is_filterable_by_margin_days(): void {
		$filter = static fn() => 0;
		add_filter( 'gatherpress_recurrence_top_up_margin_days', $filter );

		// The cutoff's own "now" is read inside the method, so the expected
		// value cannot be a second clock read compared for equality: any
		// minute boundary between the two reads fails the test. Bracketing
		// the call bounds the cutoff at full second precision instead.
		$before = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$cutoff = Utility::invoke_hidden_method( Occurrences::get_instance(), 'resolve_top_up_cutoff' );
		$after  = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		remove_filter( 'gatherpress_recurrence_top_up_margin_days', $filter );

		$horizon = '+' . Occurrences::PROJECTION_HORIZON_MONTHS . ' months';

		$this->assertGreaterThanOrEqual(
			$before->modify( $horizon ),
			$cutoff,
			'Failed to assert that a zero margin leaves the cutoff no earlier than the horizon itself.'
		);
		$this->assertLessThanOrEqual(
			$after->modify( $horizon ),
			$cutoff,
			'Failed to assert that a zero margin leaves the cutoff no later than the horizon itself.'
		);
	}

	/**
	 * Coverage for the `gatherpress_recurrence_top_up_batch_size` filter.
	 *
	 * @covers ::top_up
	 *
	 * @return void
	 */
	public function test_top_up_batch_size_is_filterable(): void {
		$this->create_short_horizon_never_ending_series();
		$this->create_short_horizon_never_ending_series();

		$filter = static fn() => 1;
		add_filter( 'gatherpress_recurrence_top_up_batch_size', $filter );

		$written = Occurrences::get_instance()->top_up();

		remove_filter( 'gatherpress_recurrence_top_up_batch_size', $filter );

		$this->assertSame( 1, $written, 'Failed to assert that top_up honors the batch-size filter default.' );
	}

	/**
	 * Coverage for `top_up()`'s clamp: a filter returning a non-positive batch
	 * size must not reach `select_series_needing_top_up()` as a `LIMIT` of
	 * zero or negative, which is a SQL syntax error for a negative value.
	 *
	 * @covers ::top_up
	 *
	 * @return void
	 */
	public function test_top_up_clamps_a_non_positive_batch_size_filter_to_one(): void {
		$this->create_short_horizon_never_ending_series();
		$this->create_short_horizon_never_ending_series();

		$filter = static fn() => -5;
		add_filter( 'gatherpress_recurrence_top_up_batch_size', $filter );

		$written = Occurrences::get_instance()->top_up();

		remove_filter( 'gatherpress_recurrence_top_up_batch_size', $filter );

		$this->assertSame(
			1,
			$written,
			'Failed to assert that top_up clamps a negative batch-size filter to at least 1.'
		);
	}

	/**
	 * Direct coverage for `top_up()`'s explicit-limit branch, bypassing the filter.
	 *
	 * @covers ::top_up
	 *
	 * @return void
	 */
	public function test_top_up_uses_an_explicit_limit_when_given_one(): void {
		$this->create_short_horizon_never_ending_series();
		$this->create_short_horizon_never_ending_series();

		$written = Occurrences::get_instance()->top_up( 1 );

		$this->assertSame( 1, $written, 'Failed to assert that top_up respects an explicit limit argument.' );
	}

	/**
	 * Direct coverage for `is_series_stale()`'s count-bounded branch.
	 *
	 * @covers ::is_series_stale
	 *
	 * @return void
	 */
	public function test_is_series_stale_returns_false_for_a_count_bounded_series(): void {
		$post_id = $this->create_and_project();

		$stale = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'is_series_stale',
			array( $post_id )
		);

		$this->assertFalse( $stale, 'Failed to assert that a count-bounded series is never reported stale.' );
	}

	/**
	 * Direct coverage for `is_series_stale()`'s non-recurring branch.
	 *
	 * @covers ::is_series_stale
	 *
	 * @return void
	 */
	public function test_is_series_stale_returns_false_for_a_non_recurring_post(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$stale = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'is_series_stale',
			array( $post_id )
		);

		$this->assertFalse( $stale, 'Failed to assert that a non-recurring post is never reported stale.' );
	}

	/**
	 * Direct coverage for `is_series_stale()`'s fresh-never-ending branch.
	 *
	 * @covers ::is_series_stale
	 *
	 * @return void
	 */
	public function test_is_series_stale_returns_false_for_a_freshly_projected_series(): void {
		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( 2, 4 ),
				'end_type'  => 'never',
			),
			new DateTimeImmutable( 'now', new DateTimeZone( 'America/New_York' ) ),
			( new DateTimeImmutable( 'now', new DateTimeZone( 'America/New_York' ) ) )->modify( '+2 hours' ),
			'America/New_York'
		);

		$stale = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'is_series_stale',
			array( $post_id )
		);

		$this->assertFalse( $stale, 'Failed to assert that a freshly projected series is not stale.' );
	}

	/**
	 * Direct coverage for `is_series_stale()`'s stale branch.
	 *
	 * @covers ::is_series_stale
	 *
	 * @return void
	 */
	public function test_is_series_stale_returns_true_for_a_short_horizon_series(): void {
		list( $post_id ) = $this->create_short_horizon_never_ending_series();

		$stale = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'is_series_stale',
			array( $post_id )
		);

		$this->assertTrue( $stale, 'Failed to assert that a series projected with a short horizon is reported stale.' );
	}

	/**
	 * Direct coverage for `is_series_stale()`'s no-rows branch: a recurring
	 * series whose occurrence rows were lost (a failed projection, a partial
	 * restore) must be reported stale, or it would be invisible to both the
	 * sweep and the lazy repair forever.
	 *
	 * @covers ::is_series_stale
	 *
	 * @return void
	 */
	public function test_is_series_stale_returns_true_for_a_series_with_no_projected_rows(): void {
		// A never-ending rule, not the shared WEEKLY_RULE fixture, which is
		// COUNT-bounded. is_series_stale() must reach the no-rows branch rather
		// than short-circuit on the COUNT-bounded branch covered above.
		list( $post_id ) = $this->create_short_horizon_never_ending_series();

		Occurrences::get_instance()->delete_for_post( $post_id );
		$this->assertCount( 0, Occurrences::get_instance()->select_for_series( array( $post_id ) ) );

		$stale = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'is_series_stale',
			array( $post_id )
		);

		$this->assertTrue( $stale, 'Failed to assert that a series with zero projected rows is reported stale.' );
	}

	/**
	 * Direct coverage for `is_series_stale()`'s `has_reached_until()` branch:
	 * an until-bounded series that has already reached its `until` date is
	 * never reported stale, even though its latest occurrence sits well below
	 * `resolve_top_up_cutoff()`.
	 *
	 * @covers ::is_series_stale
	 * @covers ::has_reached_until
	 *
	 * @return void
	 */
	public function test_is_series_stale_returns_false_for_a_completed_until_bounded_series(): void {
		$post_id = $this->create_completed_until_series();

		$stale = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'is_series_stale',
			array( $post_id )
		);

		$this->assertFalse( $stale, 'Failed to assert that a completed until-bounded series is never reported stale.' );
	}

	/**
	 * Direct coverage for `is_series_stale()`'s until-not-yet-reached branch:
	 * an until-bounded series still has room to grow before its `until` date
	 * and is reported stale exactly like a `never`-ending series once its
	 * latest occurrence runs short of the horizon.
	 *
	 * @covers ::is_series_stale
	 * @covers ::has_reached_until
	 *
	 * @return void
	 */
	public function test_is_series_stale_returns_true_for_an_until_bounded_series_not_yet_reached(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-3 weeks' );
		$until    = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '+5 years' );

		$short_horizon = static fn() => 1;
		add_filter( 'gatherpress_recurrence_horizon_months', $short_horizon );

		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'until',
				'until'     => $until->format( 'Y-m-d' ),
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			'America/New_York'
		);

		remove_filter( 'gatherpress_recurrence_horizon_months', $short_horizon );

		$stale = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'is_series_stale',
			array( $post_id )
		);

		$this->assertTrue(
			$stale,
			'Failed to assert that an until-bounded series short of its horizon, but not yet at its'
				. ' until date, is stale.'
		);
	}

	/**
	 * Direct coverage for `has_reached_until()`'s non-until branch.
	 *
	 * @covers ::has_reached_until
	 *
	 * @return void
	 */
	public function test_has_reached_until_returns_false_for_a_non_until_end_type(): void {
		$post_id = $this->create_and_project();

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'has_reached_until',
			array( $post_id, 'never', '2026-09-03 18:00:00' )
		);

		$this->assertFalse(
			$result,
			'Failed to assert that has_reached_until returns false for a non-until end type.'
		);
	}

	/**
	 * Direct coverage for `has_reached_until()`'s empty-until defensive branch.
	 *
	 * @covers ::has_reached_until
	 *
	 * @return void
	 */
	public function test_has_reached_until_returns_false_when_until_meta_is_empty(): void {
		$post_id = $this->create_and_project();

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'has_reached_until',
			array( $post_id, Rule::END_TYPE_UNTIL, '2026-09-03 18:00:00' )
		);

		$this->assertFalse(
			$result,
			'Failed to assert that has_reached_until returns false when the until mirror is empty.'
		);
	}

	/**
	 * Direct coverage for `has_reached_until()`'s reached and not-yet-reached branches.
	 *
	 * @covers ::has_reached_until
	 *
	 * @return void
	 */
	public function test_has_reached_until_compares_the_date_portion_of_the_latest_local_start(): void {
		$post_id = $this->create_and_project();

		update_post_meta( $post_id, 'gatherpress_recurrence_until', '2026-09-10' );

		$reached = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'has_reached_until',
			array( $post_id, Rule::END_TYPE_UNTIL, '2026-09-10 18:00:00' )
		);
		$not_yet = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'has_reached_until',
			array( $post_id, Rule::END_TYPE_UNTIL, '2026-09-09 18:00:00' )
		);

		$this->assertTrue( $reached, 'Failed to assert that a latest local date on the until date has reached it.' );
		$this->assertFalse(
			$not_yet,
			'Failed to assert that a latest local date before the until date has not reached it.'
		);
	}

	/**
	 * Coverage for the horizon top-up: reading a stale series through
	 * `select_upcoming()`, the real production read path, triggers exactly one
	 * repair, and a second read within the debounce window is suppressed.
	 *
	 * @covers ::select_by_horizon
	 * @covers ::maybe_lazy_repair
	 * @covers ::maybe_repair_stale_series
	 *
	 * @return void
	 */
	public function test_lazy_repair_triggers_once_then_is_suppressed_for_the_debounce_window(): void {
		list( $post_id ) = $this->create_short_horizon_never_ending_series();

		Query::refresh_has_recurring_events();
		delete_transient( sprintf( 'gatherpress_projected_%d', $post_id ) );

		// The margin-days filter fires exactly once per maybe_repair_stale_series()
		// attempt (inside is_series_stale() -> resolve_top_up_cutoff()), unlike
		// the horizon-months filter, which also fires from project()'s own
		// resolve_horizon() call whenever a repair actually re-projects. This is
		// therefore the precise "one attempt" signal the debounce governs.
		$calls  = 0;
		$filter = static function ( $days ) use ( &$calls ) {
			++$calls;

			return $days;
		};
		add_filter( 'gatherpress_recurrence_top_up_margin_days', $filter );

		Occurrences::get_instance()->select_upcoming( 50 );

		$this->assertSame(
			1,
			$calls,
			'Failed to assert that the first stale read triggered exactly one repair attempt.'
		);
		$this->assertNotFalse(
			get_transient( sprintf( 'gatherpress_projected_%d', $post_id ) ),
			'Failed to assert that the debounce transient was set after the repair.'
		);

		Occurrences::get_instance()->select_upcoming( 50 );

		remove_filter( 'gatherpress_recurrence_top_up_margin_days', $filter );

		$this->assertSame(
			1,
			$calls,
			'Failed to assert that a second read within the debounce window did not trigger another repair attempt.'
		);

		$far_future = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->modify( '+6 months' )
			->format( 'Y-m-d H:i:s' );
		$rows       = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertNotEmpty(
			array_filter( $rows, static fn( $row ) => $row['datetime_start_gmt'] > $far_future ),
			'Failed to assert that the triggered repair actually extended the horizon.'
		);
	}

	/**
	 * `maybe_lazy_repair()` primes the refs' post meta cache in one batched
	 * query, never one `update_meta_cache` round trip per ref.
	 *
	 * `select_by_horizon()` reads through raw `$wpdb`, which primes no meta
	 * cache, so on a cold cache every per-ref `has_recurrence_rule()` meta
	 * read would otherwise issue its own single-post `update_meta_cache`
	 * query. Three cold refs must cost exactly one `wp_postmeta` SELECT.
	 *
	 * @covers ::maybe_lazy_repair
	 *
	 * @return void
	 */
	public function test_lazy_repair_primes_the_meta_cache_in_one_batch(): void {
		global $wpdb;

		$timezone = new DateTimeZone( 'America/New_York' );
		$start    = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '+10 days' )->setTime( 12, 0, 0 );

		for ( $i = 0; $i < 3; $i++ ) {
			$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
			add_post_meta(
				$post_id,
				'gatherpress_datetime',
				wp_json_encode(
					array(
						'dateTimeStart' => $start->modify( sprintf( '+%d hours', $i ) )->format( 'Y-m-d H:i:s' ),
						'dateTimeEnd'   => $start->modify( sprintf( '+%d hours', $i + 1 ) )->format( 'Y-m-d H:i:s' ),
						'timezone'      => 'America/New_York',
					)
				)
			);
			Event_Setup::get_instance()->set_datetimes( $post_id );
		}

		update_option( Query::HAS_RECURRING_OPTION, '1' );

		wp_cache_flush();

		$query_count_before = count( $wpdb->queries );

		Occurrences::get_instance()->select_upcoming( 10 );

		$postmeta_selects = array_values(
			array_filter(
				array_slice( $wpdb->queries, $query_count_before ),
				static function ( $query ) use ( $wpdb ) {
					return str_contains( $query[0], $wpdb->postmeta )
						&& (bool) preg_match( '/^\s*SELECT/i', $query[0] );
				}
			)
		);

		$this->assertCount(
			1,
			$postmeta_selects,
			'Failed to assert that three cold refs cost exactly one batched wp_postmeta SELECT.'
		);
	}

	/**
	 * Coverage for `maybe_lazy_repair()`'s empty-refs arm: a read that
	 * returns nothing never calls `update_meta_cache()` at all.
	 *
	 * @covers ::maybe_lazy_repair
	 *
	 * @return void
	 */
	public function test_lazy_repair_skips_the_meta_cache_priming_with_no_refs(): void {
		global $wpdb;

		update_option( Query::HAS_RECURRING_OPTION, '1' );

		$query_count_before = count( $wpdb->queries );

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_upcoming( 10 ),
			'Failed to assert that the fixture site has no upcoming entries.'
		);

		$postmeta_selects = array_values(
			array_filter(
				array_slice( $wpdb->queries, $query_count_before ),
				static function ( $query ) use ( $wpdb ) {
					return str_contains( $query[0], $wpdb->postmeta )
						&& (bool) preg_match( '/^\s*SELECT/i', $query[0] );
				}
			)
		);

		$this->assertSame(
			array(),
			$postmeta_selects,
			'Failed to assert that an empty read issues no wp_postmeta SELECT at all.'
		);
	}

	/**
	 * Coverage for `maybe_lazy_repair()`'s site-wide short-circuit: no
	 * repair is attempted when the site has no recurring events.
	 *
	 * @covers ::maybe_lazy_repair
	 *
	 * @return void
	 */
	public function test_select_upcoming_does_not_repair_when_site_has_no_recurring_events(): void {
		update_option( Query::HAS_RECURRING_OPTION, '0' );

		$calls  = 0;
		$filter = static function ( $months ) use ( &$calls ) {
			++$calls;

			return $months;
		};
		add_filter( 'gatherpress_recurrence_horizon_months', $filter );

		Occurrences::get_instance()->select_upcoming( 50 );

		remove_filter( 'gatherpress_recurrence_horizon_months', $filter );

		$this->assertSame(
			0,
			$calls,
			'Failed to assert that select_upcoming never attempts a repair when the site has no recurring events.'
		);
	}

	/**
	 * Coverage for `select_by_horizon()`'s `$upcoming` gate: `select_past()`
	 * never triggers the lazy repair, only `select_upcoming()` does.
	 *
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_select_past_does_not_trigger_lazy_repair(): void {
		list( $post_id ) = $this->create_short_horizon_never_ending_series();

		Query::refresh_has_recurring_events();
		delete_transient( sprintf( 'gatherpress_projected_%d', $post_id ) );

		Occurrences::get_instance()->select_past( 50 );

		$this->assertFalse(
			get_transient( sprintf( 'gatherpress_projected_%d', $post_id ) ),
			'Failed to assert that select_past never sets the lazy-repair debounce transient.'
		);
	}

	/**
	 * Coverage for the horizon top-up: `maybe_lazy_repair()` caps how many distinct stale
	 * series one read attempts, so a listing page surfacing many distinct
	 * stale series cannot turn into many synchronous `project()` calls inside
	 * one request. Two stale series are encountered by one `select_upcoming()`
	 * read; at the default cap of 1, only one gets a debounce transient.
	 *
	 * @covers ::maybe_lazy_repair
	 *
	 * @return void
	 */
	public function test_maybe_lazy_repair_caps_the_number_of_series_repaired_per_read(): void {
		list( $first )  = $this->create_short_horizon_never_ending_series();
		list( $second ) = $this->create_short_horizon_never_ending_series();

		Query::refresh_has_recurring_events();
		delete_transient( sprintf( 'gatherpress_projected_%d', $first ) );
		delete_transient( sprintf( 'gatherpress_projected_%d', $second ) );

		Occurrences::get_instance()->select_upcoming( 50 );

		$repaired = array_filter(
			array( $first, $second ),
			static fn( $post_id ) => false !== get_transient( sprintf( 'gatherpress_projected_%d', $post_id ) )
		);

		$this->assertCount(
			1,
			$repaired,
			'Failed to assert that only one of two stale series was attempted, per the default read batch cap.'
		);
	}

	/**
	 * Coverage for the `gatherpress_recurrence_lazy_repair_batch_size` filter.
	 *
	 * @covers ::maybe_lazy_repair
	 *
	 * @return void
	 */
	public function test_maybe_lazy_repair_batch_size_is_filterable(): void {
		list( $first )  = $this->create_short_horizon_never_ending_series();
		list( $second ) = $this->create_short_horizon_never_ending_series();

		Query::refresh_has_recurring_events();
		delete_transient( sprintf( 'gatherpress_projected_%d', $first ) );
		delete_transient( sprintf( 'gatherpress_projected_%d', $second ) );

		$filter = static fn() => 2;
		add_filter( 'gatherpress_recurrence_lazy_repair_batch_size', $filter );

		Occurrences::get_instance()->select_upcoming( 50 );

		remove_filter( 'gatherpress_recurrence_lazy_repair_batch_size', $filter );

		$repaired = array_filter(
			array( $first, $second ),
			static fn( $post_id ) => false !== get_transient( sprintf( 'gatherpress_projected_%d', $post_id ) )
		);

		$this->assertCount(
			2,
			$repaired,
			'Failed to assert that the lazy-repair batch-size filter raises the per-read cap.'
		);
	}

	/**
	 * Direct coverage for `maybe_repair_stale_series()`'s own
	 * already-suppressed branch. `maybe_lazy_repair()`'s pre-slice
	 * `$unsuppressed` filter means this branch is no longer reachable
	 * through that one call site. A post ID with a live transient is
	 * filtered out before the loop, not encountered by
	 * `maybe_repair_stale_series()` and then bounced. The guard stays,
	 * defense-in-depth for the `protected` method's own contract ("one
	 * attempt per window") independent of any one caller, so it is
	 * covered directly here per AGENTS.md's rule for a helper whose
	 * transitive callers no longer exercise a branch.
	 *
	 * @covers ::maybe_repair_stale_series
	 *
	 * @return void
	 */
	public function test_maybe_repair_stale_series_skips_when_already_suppressed(): void {
		list( $post_id ) = $this->create_short_horizon_never_ending_series();

		$calls  = 0;
		$filter = static function ( $days ) use ( &$calls ) {
			++$calls;

			return $days;
		};
		add_filter( 'gatherpress_recurrence_top_up_margin_days', $filter );

		Utility::invoke_hidden_method( Occurrences::get_instance(), 'maybe_repair_stale_series', array( $post_id ) );
		$this->assertSame( 1, $calls, 'Failed to assert that the first direct call attempted the repair.' );

		Utility::invoke_hidden_method( Occurrences::get_instance(), 'maybe_repair_stale_series', array( $post_id ) );

		remove_filter( 'gatherpress_recurrence_top_up_margin_days', $filter );

		$this->assertSame(
			1,
			$calls,
			'Failed to assert that a second direct call is suppressed by the live debounce transient.'
		);
	}

	/**
	 * Coverage for the horizon top-up: at the default per-read cap of 1, a fresh series
	 * that sorts first must not permanently block a genuinely stale series
	 * sorting after it, which is the exact starvation shape the cap re-created.
	 * Refs arrive in `datetime_start_gmt` order, not staleness order, so a
	 * fresh series consumes the first read's single slot even though it
	 * needs no repair (`maybe_repair_stale_series()` sets its debounce
	 * transient unconditionally, because "repair once" means one *attempt* per
	 * window rather than one successful repair). A second read must then see the
	 * fresh series filtered out by its own transient and give the stale
	 * series the budget instead.
	 *
	 * The refs are built directly rather than produced by a real
	 * `select_upcoming()` query. Constructing them lets the encounter
	 * order (the exact axis this bug turns on) be asserted deterministically
	 * instead of depending on incidental fixture dates, while still
	 * representing a real, producible `select_by_horizon()` result ordering.
	 *
	 * @covers ::maybe_lazy_repair
	 *
	 * @return void
	 */
	public function test_second_read_repairs_the_series_the_first_read_skipped(): void {
		$fresh_id         = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( 2, 4 ),
				'end_type'  => 'never',
			),
			new DateTimeImmutable( 'now', new DateTimeZone( 'America/New_York' ) ),
			( new DateTimeImmutable( 'now', new DateTimeZone( 'America/New_York' ) ) )->modify( '+2 hours' ),
			'America/New_York'
		);
		list( $stale_id ) = $this->create_short_horizon_never_ending_series();

		Query::refresh_has_recurring_events();
		delete_transient( sprintf( 'gatherpress_projected_%d', $fresh_id ) );
		delete_transient( sprintf( 'gatherpress_projected_%d', $stale_id ) );

		// The fresh series sorts first, matching the "fresh series encountered
		// before the stale one" shape the bug required.
		$refs = array(
			new Occurrence_Ref( $fresh_id, '20260101T000000', '2026-01-01 00:00:00' ),
			new Occurrence_Ref( $stale_id, '20260101T000100', '2026-01-01 00:01:00' ),
		);

		$far_future = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->modify( '+6 months' )
			->format( 'Y-m-d H:i:s' );

		Utility::invoke_hidden_method( Occurrences::get_instance(), 'maybe_lazy_repair', array( $refs ) );

		$this->assertNotFalse(
			get_transient( sprintf( 'gatherpress_projected_%d', $fresh_id ) ),
			'Failed to assert that the first read attempted the fresh series (it sorts first).'
		);
		$this->assertFalse(
			get_transient( sprintf( 'gatherpress_projected_%d', $stale_id ) ),
			'Failed to assert that the first read never reached the stale series, per the per-read cap of 1.'
		);

		$stale_rows_after_first_read = Occurrences::get_instance()->select_for_series( array( $stale_id ) );
		$this->assertEmpty(
			array_filter( $stale_rows_after_first_read, static fn( $row ) => $row['datetime_start_gmt'] > $far_future ),
			'Failed to assert that the first read did not repair the stale series it never reached.'
		);

		Utility::invoke_hidden_method( Occurrences::get_instance(), 'maybe_lazy_repair', array( $refs ) );

		$this->assertNotFalse(
			get_transient( sprintf( 'gatherpress_projected_%d', $stale_id ) ),
			'Failed to assert that the second read attempted the stale series once the fresh one was filtered out.'
		);

		$stale_rows_after_second_read = Occurrences::get_instance()->select_for_series( array( $stale_id ) );
		$this->assertNotEmpty(
			array_filter(
				$stale_rows_after_second_read,
				static fn( $row ) => $row['datetime_start_gmt'] > $far_future
			),
			'Failed to assert that the second read repaired the series the first read skipped.'
		);
	}

	/**
	 * Coverage for rowless repair: a valid recurring
	 * series whose occurrence rows are gone must be repaired by the scheduled
	 * sweep. A partial restore, a projection that failed halfway, and a manual
	 * `DELETE` all produce that state. Candidate selection used to be driven from the
	 * occurrence table itself, which made "has a rule but no rows" the one
	 * state repair could never reach: `is_series_stale()` knew the series was
	 * stale, and nothing ever asked it.
	 *
	 * Driven through the real cron hook, and asserted on rows rather than on
	 * the candidate list alone, so nothing but the sweep can account for the
	 * rows coming back: the fixture deletes them and no other code in this
	 * test writes to the table.
	 *
	 * @covers ::select_series_needing_top_up
	 * @covers ::top_up
	 *
	 * @return void
	 */
	public function test_rowless_series_is_repaired_by_the_scheduled_sweep(): void {
		list( $post_id ) = $this->create_short_horizon_never_ending_series();

		$this->assertNotEmpty(
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert the fixture projected rows before they were deleted.'
		);

		Occurrences::get_instance()->delete_for_post( $post_id );

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert the fixture series starts the sweep with zero rows.'
		);
		$this->assertContains(
			$post_id,
			Occurrences::get_instance()->select_series_needing_top_up( 100 ),
			'Failed to assert that a series with a valid rule and zero rows is a sweep candidate.'
		);

		Query::refresh_has_recurring_events();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- SWEEP_ACTION is a gatherpress_-prefixed class constant.
		do_action( Projection_Cron::SWEEP_ACTION );

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertNotEmpty(
			$rows,
			'Failed to assert that the sweep restored the rows of a rowless series.'
		);
		$this->assertNotEmpty(
			array_filter(
				$rows,
				static fn( $row ) => $row['datetime_start_gmt'] > gmdate( 'Y-m-d H:i:s' )
			),
			'Failed to assert that the restored rows reach forward of now, rather than only backfilling the past.'
		);
	}

	/**
	 * `find_in_series()` matches on the composite key across every post of a series.
	 *
	 * The lookup that validates a request-supplied `recurrence_id`. It takes an
	 * array of post IDs rather than one, so a forward split that
	 * moves an occurrence onto a sibling post does not make the identifier
	 * stop resolving.
	 *
	 * @covers ::find_in_series
	 *
	 * @return void
	 */
	public function test_find_in_series_matches_the_composite_key(): void {
		$post_id = $this->create_and_project();
		$found   = Occurrences::get_instance()->find_in_series(
			array( $post_id + 1000, $post_id ),
			'20260915T180000'
		);

		$this->assertIsArray( $found, 'Failed to assert an occurrence of the series resolves.' );
		$this->assertSame(
			'20260915T180000',
			$found['recurrence_id'],
			'Failed to assert the resolved row is the one the identifier named.'
		);
		$this->assertSame(
			$post_id,
			(int) $found['series_post_id'],
			'Failed to assert the resolved row belongs to the post that owns it.'
		);
	}

	/**
	 * Coverage for the rowless-repair blocker on the lazy path: an
	 * upcoming-events read that surfaces a rowless series through
	 * `select_by_horizon()`'s no-rows fallback must repair it. The fallback
	 * ref carries a null `recurrence_id`, which is indistinguishable from an
	 * ordinary non-recurring event by shape alone. That is why the lazy
	 * path skipped it and why the `end_type` mirror is what separates them.
	 *
	 * @covers ::maybe_lazy_repair
	 * @covers ::has_recurrence_rule
	 *
	 * @return void
	 */
	public function test_rowless_series_is_repaired_by_an_upcoming_read(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '+1 week' );

		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'never',
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			'America/New_York'
		);

		Occurrences::get_instance()->delete_for_post( $post_id );
		delete_transient( sprintf( 'gatherpress_projected_%d', $post_id ) );

		Query::refresh_has_recurring_events();

		$refs = Occurrences::get_instance()->select_upcoming( 10 );

		$this->assertNotEmpty(
			array_filter( $refs, static fn( Occurrence_Ref $ref ) => $ref->post_id === $post_id ),
			'Failed to assert that the rowless series still surfaced on the upcoming read.'
		);
		$this->assertNotEmpty(
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert that an upcoming read repaired a series whose rows were gone.'
		);
	}

	/**
	 * A rowless `UNTIL`-bounded series whose `until` is already in the past
	 * can only ever expand to nothing, so it must not become a permanent
	 * hourly candidate the rowless-repair fix would otherwise create. The
	 * sweep predicate and `is_series_stale()` have to agree on this, or a
	 * series one path leaves alone is re-selected by the other.
	 *
	 * @covers ::select_series_needing_top_up
	 * @covers ::is_series_stale
	 * @covers ::is_expired_until
	 *
	 * @return void
	 */
	public function test_rowless_expired_until_series_is_never_a_repair_candidate(): void {
		$post_id = $this->create_completed_until_series();

		Occurrences::get_instance()->delete_for_post( $post_id );

		$this->assertNotContains(
			$post_id,
			Occurrences::get_instance()->select_series_needing_top_up( 100 ),
			'Failed to assert that a rowless, expired until-bounded series is not a sweep candidate.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( Occurrences::get_instance(), 'is_series_stale', array( $post_id ) ),
			'Failed to assert that is_series_stale agrees a rowless, expired until-bounded series is complete.'
		);
	}

	/**
	 * Direct coverage for `is_expired_until()`'s three return paths. The
	 * helper is called only from `is_series_stale()`'s no-rows branch, which
	 * xdebug does not trace into reliably.
	 *
	 * @covers ::is_expired_until
	 *
	 * @return void
	 */
	public function test_is_expired_until_covers_every_branch(): void {
		list( $never_id ) = $this->create_short_horizon_never_ending_series();
		$until_id         = $this->create_completed_until_series();

		$this->assertFalse(
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'is_expired_until',
				array( $never_id, Rule::END_TYPE_NEVER )
			),
			'Failed to assert that a never-ending rule is never treated as an expired until rule.'
		);

		$this->assertTrue(
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'is_expired_until',
				array( $until_id, Rule::END_TYPE_UNTIL )
			),
			'Failed to assert that an until-bounded rule whose until has passed is reported expired.'
		);

		delete_post_meta( $until_id, 'gatherpress_recurrence_until' );

		$this->assertFalse(
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'is_expired_until',
				array( $until_id, Rule::END_TYPE_UNTIL )
			),
			'Failed to assert that a missing until mirror is not treated as an expired bound.'
		);
	}

	/**
	 * `is_expired_until()` compares the rule's date-only `until` with today in
	 * the series' own timezone, never with UTC's calendar date.
	 *
	 * The `until` mirror is a wall-clock calendar rule. Whenever the series'
	 * local date and UTC's date differ, a UTC comparison misclassifies the
	 * boundary day: a western-zone series still on its final local day is
	 * declared expired and its last occurrence is never reprojected, and an
	 * eastern-zone series already past its bound locally is declared active.
	 * The test picks whichever named zone differs from UTC's date at run
	 * time, so it exercises a real date split at any hour.
	 *
	 * @covers ::is_expired_until
	 * @covers ::resolve_series_timezone
	 *
	 * @return void
	 */
	public function test_is_expired_until_compares_in_the_series_timezone_not_utc(): void {
		$utc_now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		// Before 10:00 UTC, Pacific/Pago_Pago (UTC-11) is still on
		// yesterday's local date; from 10:00 UTC on, Pacific/Kiritimati
		// (UTC+14) is already on tomorrow's. One of the two always differs
		// from UTC's date, so the fixture never degenerates into the case
		// where both comparisons agree.
		$zone_name = (int) $utc_now->format( 'G' ) < 10 ? 'Pacific/Pago_Pago' : 'Pacific/Kiritimati';
		$zone      = new DateTimeZone( $zone_name );

		$local_today = $utc_now->setTimezone( $zone )->format( 'Y-m-d' );
		$utc_today   = $utc_now->format( 'Y-m-d' );

		$this->assertNotSame(
			$utc_today,
			$local_today,
			'Failed to assert the fixture zone is on a different calendar date than UTC.'
		);

		if ( $local_today < $utc_today ) {
			// Western zone: the final local day is still running, so an
			// `until` equal to it has not expired, whatever UTC says.
			$until    = $local_today;
			$expected = false;
		} else {
			// Eastern zone: the series is already past UTC's date locally,
			// so an `until` equal to UTC's date has expired there.
			$until    = $utc_today;
			$expected = true;
		}

		$anchor  = $utc_now->setTimezone( $zone )->modify( '-3 weeks' );
		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'until',
				'until'     => $until,
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			$zone_name
		);

		$this->assertSame(
			$expected,
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'is_expired_until',
				array( $post_id, Rule::END_TYPE_UNTIL )
			),
			'Failed to assert that is_expired_until reads today in the series timezone rather than UTC.'
		);
	}

	/**
	 * A rowless `UNTIL` series whose bound is UTC's yesterday must stay a
	 * sweep candidate.
	 *
	 * The candidate query cannot see each series' timezone, so its rowless
	 * `UNTIL` bound is deliberately one calendar day lenient: a series' local
	 * date can trail UTC's by up to a day (UTC-12), and a bound computed from
	 * UTC's own date permanently drops a western-zone series whose final
	 * local day is still running. The lenient bound keeps every such
	 * boundary series selectable; a genuinely expired one projects its final
	 * rows once and then completes through `has_reached_until()`.
	 *
	 * @covers ::select_series_needing_top_up
	 *
	 * @return void
	 */
	public function test_rowless_until_series_on_yesterdays_utc_date_stays_a_sweep_candidate(): void {
		$zone = new DateTimeZone( 'Pacific/Pago_Pago' );

		// The fixture's until sits exactly on the candidate query's lenient
		// bound, which the query recomputes from its own clock read. That
		// boundary placement is the property under test, so it cannot be
		// widened. A UTC midnight passing between building the fixture and
		// running the query would instead move the bound past the fixture
		// and fail the assertion for a reason unrelated to the leniency it
		// proves, so when the day provably rolled mid-arrangement, rebuild
		// once: consecutive midnights are a day apart, and a second pass
		// cannot straddle one.
		do {
			$day_at_start  = gmdate( 'Y-m-d' );
			$utc_yesterday = ( new DateTimeImmutable( $day_at_start, new DateTimeZone( 'UTC' ) ) )
				->modify( '-1 day' )
				->format( 'Y-m-d' );
			$anchor        = ( new DateTimeImmutable( 'now', $zone ) )->modify( '-3 weeks' );

			$post_id = $this->create_relative_recurring_event(
				array(
					'frequency' => 'weekly',
					'interval'  => 1,
					'weekdays'  => array( (int) $anchor->format( 'w' ) ),
					'end_type'  => 'until',
					'until'     => $utc_yesterday,
				),
				$anchor,
				$anchor->modify( '+2 hours' ),
				'Pacific/Pago_Pago'
			);

			Occurrences::get_instance()->delete_for_post( $post_id );

			$candidates = Occurrences::get_instance()->select_series_needing_top_up( 100 );
		} while ( gmdate( 'Y-m-d' ) !== $day_at_start );

		$this->assertContains(
			$post_id,
			$candidates,
			'Failed to assert that a rowless until series bounded on UTC yesterday is still selectable.'
		);
	}

	/**
	 * Direct coverage for `resolve_series_timezone()`'s two return paths. It
	 * is called from `is_expired_until()`, a same-class delegation xdebug
	 * does not trace into reliably.
	 *
	 * @covers ::resolve_series_timezone
	 *
	 * @return void
	 */
	public function test_resolve_series_timezone_covers_every_branch(): void {
		$post_id = $this->create_and_project();

		$this->assertSame(
			'America/New_York',
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'resolve_series_timezone',
				array( $post_id )
			)->getName(),
			'Failed to assert that the stored series timezone is resolved.'
		);

		$filter = static fn() => 'Not/AZone';
		add_filter( 'gatherpress_timezone', $filter );

		$fallback = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'resolve_series_timezone',
			array( $post_id )
		);

		remove_filter( 'gatherpress_timezone', $filter );

		$this->assertSame(
			'UTC',
			$fallback->getName(),
			'Failed to assert that an unconstructable timezone string falls back to UTC.'
		);
	}

	/**
	 * Direct coverage for `has_recurrence_rule()`'s two return paths. It is
	 * called only from inside `maybe_lazy_repair()`'s loop, which xdebug does
	 * not trace into reliably.
	 *
	 * @covers ::has_recurrence_rule
	 *
	 * @return void
	 */
	public function test_has_recurrence_rule_covers_every_branch(): void {
		list( $recurring_id ) = $this->create_short_horizon_never_ending_series();
		$ordinary_id          = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$this->assertTrue(
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'has_recurrence_rule',
				array( $recurring_id )
			),
			'Failed to assert that a series carrying an end-type mirror is recognized as a series.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'has_recurrence_rule',
				array( $ordinary_id )
			),
			'Failed to assert that an ordinary event is not treated as a series.'
		);
	}

	/**
	 * A negative limit never reaches the SQL as `LIMIT -1`.
	 *
	 * MySQL rejects a negative `LIMIT` outright, and `$wpdb` swallows the
	 * syntax error into an empty result, so a caller passing a bad limit got
	 * silence plus a poisoned `$wpdb->last_error` instead of a defined
	 * answer. Both limit-taking readers clamp to zero, which legitimately
	 * selects nothing.
	 *
	 * @covers ::select_upcoming
	 * @covers ::select_by_horizon
	 * @covers ::select_series_needing_top_up
	 *
	 * @return void
	 */
	public function test_negative_limit_is_clamped_rather_than_reaching_the_sql(): void {
		global $wpdb;

		update_option( Query::HAS_RECURRING_OPTION, '0' );

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_upcoming( -1 ),
			'Failed to assert that a negative upcoming limit selects nothing.'
		);
		$this->assertSame(
			'',
			$wpdb->last_error,
			'Failed to assert that a negative upcoming limit produced no SQL error.'
		);

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_series_needing_top_up( -1 ),
			'Failed to assert that a negative top-up limit selects nothing.'
		);
		$this->assertSame(
			'',
			$wpdb->last_error,
			'Failed to assert that a negative top-up limit produced no SQL error.'
		);
	}

	/**
	 * Coverage for total ordering on the limited event
	 * query: `ORDER BY effective_start_gmt` alone is not a total order, so
	 * two events sharing one start instant can swap places between two
	 * identical reads and a paginated list can repeat or drop one.
	 *
	 * The statement itself is pinned, not merely the row order. InnoDB
	 * returns tied rows in clustered-key order today, so a row-order
	 * assertion passes with or without the tie-breakers, so it measures the
	 * current plan rather than the guarantee. Pinning the emitted `ORDER BY`
	 * is what fails if a future refactor drops it.
	 *
	 * @covers ::select_by_horizon
	 * @covers ::select_upcoming
	 * @covers ::select_past
	 *
	 * @return void
	 */
	public function test_limited_event_query_emits_a_total_order(): void {
		global $wpdb;

		// State the precondition rather than inheriting it. The occurrence read
		// path is gated on the site having recurring events. On a site with none
		// it is entitled to answer without naming the occurrence table at all, so
		// a test that pins the occurrence-joined statement has to say that this
		// site has them.
		update_option( Query::HAS_RECURRING_OPTION, '1', true );

		$occurrences_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$directions = array(
			'ASC'  => true,
			'DESC' => false,
		);

		foreach ( $directions as $direction => $upcoming ) {
			$query_count_before = count( $wpdb->queries );

			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'select_by_horizon',
				array( 5, array(), $upcoming )
			);

			$statements = array_values(
				array_filter(
					array_slice( $wpdb->queries, $query_count_before ),
					static function ( $query ) use ( $occurrences_table ) {
						return str_contains( $query[0], $occurrences_table )
							&& str_contains( $query[0], 'effective_start_gmt' );
					}
				)
			);

			$this->assertCount(
				1,
				$statements,
				sprintf( 'Failed to capture exactly one %s horizon statement.', $direction )
			);
			$this->assertStringContainsString(
				sprintf(
					'ORDER BY effective_start_gmt %1$s, `%2$s`.ID %1$s, scheduled_occurrence.recurrence_id %1$s',
					$direction,
					$wpdb->posts
				),
				$statements[0][0],
				sprintf(
					'Failed to assert that the %s horizon query breaks ties on post ID and recurrence ID.',
					$direction
				)
			);
		}
	}

	/**
	 * Behavioral companion to the pinned statement above: three events
	 * sharing one start instant must page consistently, with page 1 a strict
	 * prefix of page 2 and no entry repeated or dropped across the boundary.
	 *
	 * @covers ::select_by_horizon
	 * @covers ::select_upcoming
	 *
	 * @return void
	 */
	public function test_tied_events_page_without_repeating_or_dropping(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$start    = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '+2 weeks' );
		$post_ids = array();

		for ( $index = 0; $index < 3; $index++ ) {
			$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

			add_post_meta(
				$post_id,
				'gatherpress_datetime',
				wp_json_encode(
					array(
						'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
						'dateTimeEnd'   => $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
						'timezone'      => 'America/New_York',
					)
				)
			);
			Event_Setup::get_instance()->set_datetimes( $post_id );

			$post_ids[] = $post_id;
		}

		sort( $post_ids );

		$page_one = Occurrences::get_instance()->select_upcoming( 1 );
		$page_two = Occurrences::get_instance()->select_upcoming( 2 );

		$this->assertSame(
			array( $post_ids[0] ),
			array_map( static fn( Occurrence_Ref $ref ) => $ref->post_id, $page_one ),
			'Failed to assert that the first page of tied events is the lowest post ID.'
		);
		$this->assertSame(
			array( $post_ids[0], $post_ids[1] ),
			array_map( static fn( Occurrence_Ref $ref ) => $ref->post_id, $page_two ),
			'Failed to assert that a wider page of tied events extends the same order rather than reshuffling it.'
		);
	}

	/**
	 * The upcoming/past boundary predicate lives in `WHERE`, never `HAVING`.
	 *
	 * The horizon query has no `GROUP BY` and no aggregate, so filtering the
	 * effective end through `HAVING` forces the entire joined result set into
	 * a temporary table before a single row is discarded. Review measurement
	 * on 999 events and 10,000 occurrence rows put `Handler_read_rnd_next` at
	 * 10,600 with the `HAVING` form against 600 with the same predicate in
	 * `WHERE`, for an identical ten-row result. The emitted statement is
	 * pinned in both directions because the two forms return the same rows,
	 * so no row-level assertion can tell them apart.
	 *
	 * @covers ::select_by_horizon
	 * @covers ::select_upcoming
	 * @covers ::select_past
	 *
	 * @return void
	 */
	public function test_horizon_boundary_predicate_lives_in_where_not_having(): void {
		global $wpdb;

		$events_table      = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$occurrences_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$directions = array(
			'>=' => true,
			'<'  => false,
		);

		// The joined arm is the one under test, and this branch adds an
		// anchor-only arm ahead of it that names no occurrence table at all.
		// Flagging the site is what makes the joined arm the arm that runs,
		// so the capture below cannot pass by reading the wrong statement.
		update_option( Query::HAS_RECURRING_OPTION, '1' );

		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to arrange the site state the joined horizon arm requires.'
		);

		foreach ( $directions as $comparison => $upcoming ) {
			$query_count_before = count( $wpdb->queries );

			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'select_by_horizon',
				array( 5, array(), $upcoming )
			);

			$statements = array_values(
				array_filter(
					array_slice( $wpdb->queries, $query_count_before ),
					static function ( $query ) use ( $occurrences_table ) {
						return str_contains( $query[0], $occurrences_table )
							&& str_contains( $query[0], 'effective_start_gmt' );
					}
				)
			);

			$this->assertCount(
				1,
				$statements,
				sprintf( 'Failed to capture exactly one "%s" horizon statement.', $comparison )
			);
			$this->assertStringNotContainsString(
				'HAVING',
				$statements[0][0],
				sprintf(
					'Failed to assert that the "%s" horizon query never filters through HAVING.',
					$comparison
				)
			);
			$this->assertStringContainsString(
				sprintf(
					'AND COALESCE( scheduled_occurrence.datetime_end_gmt, `%s`.datetime_end_gmt ) %s ',
					$events_table,
					$comparison
				),
				$statements[0][0],
				sprintf(
					'Failed to assert that the "%s" horizon query bounds the effective end in WHERE.',
					$comparison
				)
			);
		}
	}

	/**
	 * Identity pin for the boundary predicate's `HAVING` to `WHERE` move: a
	 * mixed fixture's exact bucket membership, in order, on both sides.
	 *
	 * This test is green before and after the predicate moves, by design.
	 * Its role is the result-set-identity proof: it pins the exact
	 * `(post_id, recurrence_id)` tuples both buckets return for a fixture
	 * exercising every arm at once, an ended occurrence, a canceled ended
	 * occurrence, a running occurrence, a future occurrence, and plain
	 * past and future events on the `NULL` fallback branch. Any predicate
	 * rewrite that changes membership or order in either bucket fails here.
	 *
	 * @covers ::select_upcoming
	 * @covers ::select_past
	 * @covers ::select_by_horizon
	 *
	 * @return void
	 */
	public function test_horizon_bucket_membership_survives_the_where_move(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$now      = new DateTimeImmutable( 'now', $timezone );
		$anchor   = $now->modify( '-2 weeks' )->modify( '-1 hour' );

		$series_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'count',
				'count'     => 4,
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			'America/New_York'
		);

		// Cancel the oldest occurrence, so the status join predicate is
		// exercised inside the same pinned result set.
		Occurrences::get_instance()->set_status(
			$series_id,
			Occurrences::recurrence_id( $anchor ),
			Occurrences::STATUS_CANCELED
		);

		$plain_ids     = array();
		$plain_offsets = array(
			'+5 days' => 'future',
			'-5 days' => 'past',
		);

		foreach ( $plain_offsets as $offset => $key ) {
			$start             = $now->modify( $offset )->setTime( 12, 0, 0 );
			$plain_ids[ $key ] = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

			add_post_meta(
				$plain_ids[ $key ],
				'gatherpress_datetime',
				wp_json_encode(
					array(
						'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
						'dateTimeEnd'   => $start->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
						'timezone'      => 'America/New_York',
					)
				)
			);
			Event_Setup::get_instance()->set_datetimes( $plain_ids[ $key ] );
		}

		$tuples = static fn( array $refs ) => array_map(
			static fn( Occurrence_Ref $ref ) => array( $ref->post_id, $ref->recurrence_id ),
			$refs
		);

		$this->assertSame(
			array(
				// Started one hour ago, still running: upcoming, first.
				array( $series_id, Occurrences::recurrence_id( $anchor->modify( '+14 days' ) ) ),
				array( $plain_ids['future'], null ),
				array( $series_id, Occurrences::recurrence_id( $anchor->modify( '+21 days' ) ) ),
			),
			$tuples( Occurrences::get_instance()->select_upcoming( 10 ) ),
			'Failed to assert the exact upcoming bucket membership and order for the mixed fixture.'
		);
		$this->assertSame(
			array(
				array( $plain_ids['past'], null ),
				// The canceled anchor occurrence is absent; only the second
				// occurrence has both ended and kept its scheduled status.
				array( $series_id, Occurrences::recurrence_id( $anchor->modify( '+7 days' ) ) ),
			),
			$tuples( Occurrences::get_instance()->select_past( 10 ) ),
			'Failed to assert the exact past bucket membership and order for the mixed fixture.'
		);
	}

	/**
	 * Coverage for total ordering on the sweep's own
	 * limited query. Rowless candidates all share one `NULL` sort key, so
	 * without the `post_id` tie-breaker the batch boundary among them is
	 * whatever the plan happens to emit. One rowless series could be
	 * selected every hour while another is never selected at all.
	 *
	 * Pins the emitted statement for the same reason the event-query test
	 * does, and pairs it with the batch-of-one behavior the tie-breaker
	 * guarantees.
	 *
	 * @covers ::select_series_needing_top_up
	 *
	 * @return void
	 */
	public function test_sweep_candidate_query_emits_a_total_order(): void {
		global $wpdb;

		list( $first_id )  = $this->create_short_horizon_never_ending_series();
		list( $second_id ) = $this->create_short_horizon_never_ending_series();

		Occurrences::get_instance()->delete_for_post( $first_id );
		Occurrences::get_instance()->delete_for_post( $second_id );

		$query_count_before = count( $wpdb->queries );

		$candidates = Occurrences::get_instance()->select_series_needing_top_up( 1 );

		$statements = array_values(
			array_filter(
				array_slice( $wpdb->queries, $query_count_before ),
				static function ( $query ) {
					return str_contains( $query[0], 'series_post_id' )
						&& str_contains( $query[0], 'ORDER BY' );
				}
			)
		);

		$this->assertCount( 1, $statements, 'Failed to capture exactly one sweep candidate statement.' );
		$this->assertStringContainsString(
			'ORDER BY MAX( o.datetime_start_gmt ) ASC, end_type_meta.post_id ASC',
			$statements[0][0],
			'Failed to assert that the sweep candidate query breaks ties on series post ID.'
		);
		$this->assertSame(
			array( min( $first_id, $second_id ) ),
			$candidates,
			'Failed to assert that a batch of one takes the lowest-ID rowless candidate.'
		);
	}

	/**
	 * Install a `query` filter that redirects matching occurrence-table
	 * statements to a nonexistent table, so the write genuinely fails at the
	 * database without any DDL.
	 *
	 * @since 0.36.0
	 *
	 * @param string $statement_prefix Statement type to break, e.g. `INSERT INTO`.
	 * @param string $required_needle  Extra substring the statement must contain.
	 *
	 * @return callable The filter, for `remove_filter()`.
	 */
	protected function break_occurrence_statements( string $statement_prefix, string $required_needle = '' ): callable {
		global $wpdb;

		$table  = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$filter = static function ( $query ) use ( $table, $statement_prefix, $required_needle ) {
			if (
				str_starts_with( $query, $statement_prefix )
				&& str_contains( $query, $table )
				&& ( '' === $required_needle || str_contains( $query, $required_needle ) )
			) {
				return str_replace( $table, $table . '_gone', $query );
			}

			return $query;
		};

		add_filter( 'query', $filter );

		return $filter;
	}

	/**
	 * A failed occurrence insert must surface as a `WP_Error`, never as a row
	 * count claiming the rows were written.
	 *
	 * The failure is produced at the database itself, by redirecting the
	 * insert to a nonexistent table, so this drives the same `false` return
	 * a missing table, a `max_allowed_packet` overflow, or a read-only
	 * replica produces in production.
	 *
	 * @covers ::project
	 * @covers ::upsert_occurrences
	 * @covers ::insert_or_update_rows
	 * @covers ::execute_write
	 * @covers ::maybe_install_missing_table
	 * @covers ::write_error
	 *
	 * @return void
	 */
	public function test_project_returns_wp_error_when_the_occurrence_insert_fails(): void {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );
		Meta::get_instance()->set_recurrence( $post_id );

		$filter = $this->break_occurrence_statements( 'INSERT INTO' );

		$result = Occurrences::get_instance()->project( $post_id );

		remove_filter( 'query', $filter );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert that project() reports a failed insert instead of a row count.'
		);
		$this->assertSame(
			'gatherpress_occurrence_write_failed',
			$result->get_error_code(),
			'Failed to assert the error code names the occurrence write failure.'
		);
		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert that no rows landed when the insert failed.'
		);
		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the site flag still reflects the stored rule after a write failure.'
		);
	}

	/**
	 * A failed stale-row delete must surface as a `WP_Error` too, so a rule
	 * edit that could not remove its stale rows is never reported clean.
	 *
	 * @covers ::project
	 * @covers ::upsert_occurrences
	 * @covers ::delete_stale_rows
	 * @covers ::execute_write
	 * @covers ::write_error
	 *
	 * @return void
	 */
	public function test_project_returns_wp_error_when_the_stale_delete_fails(): void {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );
		Meta::get_instance()->set_recurrence( $post_id );

		$filter = $this->break_occurrence_statements( 'DELETE FROM', 'NOT IN' );

		$result = Occurrences::get_instance()->project( $post_id );

		remove_filter( 'query', $filter );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert that project() reports a failed stale-row delete.'
		);
	}

	/**
	 * A projection against a missing occurrence table recreates the table once
	 * and retries, so a site running this code without a version bump heals on
	 * the first recurring save instead of failing silently forever.
	 *
	 * The drops are the first statements of the test, per this suite's DDL
	 * convention: DDL commits the test transaction, and dropping before
	 * anything is written leaves nothing to leak. Everything the test creates
	 * after the drop is removed by hand at the end for the same reason. Both
	 * drop forms run because the test bootstrap leaves the occurrence table
	 * existing twice on this connection, as a real table shadowed by a
	 * temporary one, and dropping only one of the pair leaves a working
	 * table behind and the "missing table" precondition never holds. The
	 * precondition is asserted rather than assumed for exactly that reason.
	 *
	 * @covers ::project
	 * @covers ::upsert_occurrences
	 * @covers ::execute_write
	 * @covers ::maybe_install_missing_table
	 *
	 * @return void
	 */
	public function test_project_self_heals_a_missing_occurrence_table(): void {
		global $wpdb;

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// The test framework rewrites DROP TABLE and CREATE TABLE statements
		// into their TEMPORARY forms through the `query` filter, which would
		// leave the real table standing here and make the heal's `dbDelta()`
		// create an invisible temporary table. Dropped for the duration; the
		// framework's hook backup restores the filters in tearDown.
		remove_all_filters( 'query' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required to test the self-heal.
		$wpdb->query( "DROP TEMPORARY TABLE IF EXISTS {$table}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		$this->assertNull(
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ),
			'Failed to make the occurrence table genuinely missing before the write.'
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );
		Meta::get_instance()->set_recurrence( $post_id );

		// The production save path, not a direct project() call: the wiring
		// from a save to the healed write is the thing under test.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'wp_after_insert_post', $post_id, get_post( $post_id ), true, null );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertSame( $table, $exists, 'Failed to assert the missing table was recreated by the write path.' );
		$this->assertCount(
			5,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert the rows landed after the self-heal recreated the table.'
		);
		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the site flag agrees with the healed state.'
		);

		// Manual cleanup: the DROP committed the test transaction, so the
		// fixture post and the once-per-request heal guard survive rollback.
		Utility::set_and_get_hidden_property( Occurrences::get_instance(), 'table_heal_attempted', false );
		wp_delete_post( $post_id, true );

		// Direct coverage for the install branch of the heal helper, while
		// the temporary-table rewrites are still suspended: a second genuine
		// drop, then the helper reports it installed and the table is back.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required to test the self-heal.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		$this->assertTrue(
			Utility::invoke_hidden_method( Occurrences::get_instance(), 'maybe_install_missing_table' ),
			'Failed to assert the heal helper reports installing a genuinely missing table.'
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertSame(
			$table,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ),
			'Failed to assert the direct heal invoke recreated the table.'
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		Utility::set_and_get_hidden_property( Occurrences::get_instance(), 'table_heal_attempted', false );
	}

	/**
	 * `set_status()` refuses a status that is not one of the two constants,
	 * leaving the row untouched.
	 *
	 * `select_by_horizon()` matches `status = 'scheduled'` exactly, so a row
	 * carrying any other string silently vanishes from every listing without
	 * having been canceled. The write boundary is where that is stopped.
	 *
	 * @covers ::set_status
	 *
	 * @return void
	 */
	public function test_set_status_refuses_an_unrecognized_status(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$this->assertFalse(
			$instance->set_status( $post_id, '20260903T180000', 'bogus' ),
			'Failed to assert set_status refuses a status outside the two constants.'
		);

		$row = $instance->get( $post_id, '20260903T180000' );

		$this->assertSame(
			Occurrences::STATUS_SCHEDULED,
			$row['status'],
			'Failed to assert the refused status left the row untouched.'
		);
	}

	/**
	 * A corrupted `end_type` mirror holding an unrecognized value must not
	 * become a permanent sweep candidate.
	 *
	 * `Rule::is_valid()` rejects such a rule, so projecting it can never
	 * produce rows, and a negative `!= 'count'` predicate would keep
	 * selecting it on every sweep forever. The candidate query must admit
	 * only the two end types a projection can actually extend.
	 *
	 * The fixture passes every other candidate predicate on purpose: the
	 * post is published, its mirror key exists, and it has no occurrence
	 * rows, so only the end-type predicate can exclude it.
	 *
	 * @covers ::select_series_needing_top_up
	 *
	 * @return void
	 */
	public function test_sweep_candidates_exclude_an_unrecognized_end_type(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'gatherpress_recurrence_end_type', 'bogus' );

		$this->assertNotContains(
			$post_id,
			Occurrences::get_instance()->select_series_needing_top_up( 10 ),
			'Failed to assert an unrecognized end type is never a sweep candidate.'
		);
	}

	/**
	 * A filter forcing a non-positive projection horizon is clamped rather
	 * than allowed to build a horizon in the past, which would expand every
	 * open-ended rule to zero rows and delete all existing rows as stale.
	 *
	 * @covers ::project
	 * @covers ::resolve_horizon
	 *
	 * @return void
	 */
	public function test_a_negative_horizon_filter_cannot_delete_every_row(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);
		Meta::get_instance()->set_recurrence( $post_id );

		$destructive = static fn() => -1;
		add_filter( 'gatherpress_recurrence_horizon_months', $destructive );

		$written = Occurrences::get_instance()->project( $post_id );

		remove_filter( 'gatherpress_recurrence_horizon_months', $destructive );

		$this->assertIsInt( $written, 'Failed to assert the clamped projection reported success.' );
		$this->assertGreaterThan(
			0,
			$written,
			'Failed to assert a negative horizon filter is clamped instead of expanding to nothing.'
		);
		$this->assertNotSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert the clamped horizon left the projected rows in place.'
		);
	}

	/**
	 * Direct coverage for both arms of the horizon-months clamp.
	 *
	 * @covers ::resolve_horizon_months
	 *
	 * @return void
	 */
	public function test_resolve_horizon_months_clamps_only_non_positive_values(): void {
		$instance = Occurrences::get_instance();

		$widen = static fn() => 5;
		add_filter( 'gatherpress_recurrence_horizon_months', $widen );

		$this->assertSame(
			5,
			Utility::invoke_hidden_method( $instance, 'resolve_horizon_months' ),
			'Failed to assert a positive filtered horizon passes through unclamped.'
		);

		remove_filter( 'gatherpress_recurrence_horizon_months', $widen );

		$negative = static fn() => -3;
		add_filter( 'gatherpress_recurrence_horizon_months', $negative );

		$this->assertSame(
			1,
			Utility::invoke_hidden_method( $instance, 'resolve_horizon_months' ),
			'Failed to assert a non-positive filtered horizon clamps to one month.'
		);

		remove_filter( 'gatherpress_recurrence_horizon_months', $negative );
	}

	/**
	 * Direct coverage for `execute_write()`'s success path: the statement's
	 * affected-row count comes back as an integer.
	 *
	 * @covers ::execute_write
	 *
	 * @return void
	 */
	public function test_execute_write_returns_the_rows_affected(): void {
		global $wpdb;

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'execute_write',
			array( $wpdb->prepare( 'DELETE FROM %i WHERE series_post_id = %d', $table, 0 ) )
		);

		$this->assertSame( 0, $result, 'Failed to assert execute_write returns the affected-row count.' );
	}

	/**
	 * Direct coverage for `execute_write()` reporting a failure the heal
	 * cannot fix: the table exists, so the failed statement returns false.
	 *
	 * @covers ::execute_write
	 * @covers ::maybe_install_missing_table
	 *
	 * @return void
	 */
	public function test_execute_write_returns_false_when_the_table_is_not_the_problem(): void {
		global $wpdb;

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'execute_write',
			array( $wpdb->prepare( 'DELETE FROM %i WHERE series_post_id = %d', $table . '_gone', 0 ) )
		);

		$this->assertFalse( $result, 'Failed to assert execute_write surfaces a failure it cannot heal.' );
		$this->assertFalse(
			(bool) Utility::get_hidden_property( Occurrences::get_instance(), 'table_heal_attempted' ),
			'Failed to assert the once-per-request guard is not consumed when the table exists.'
		);
	}

	/**
	 * Direct coverage for the heal helper's once-per-request guard: after a
	 * prior attempt it declines without even checking the table.
	 *
	 * @covers ::maybe_install_missing_table
	 *
	 * @return void
	 */
	public function test_maybe_install_missing_table_declines_after_a_prior_attempt(): void {
		$instance = Occurrences::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'table_heal_attempted', true );

		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'maybe_install_missing_table' ),
			'Failed to assert the heal runs at most once per request.'
		);

		Utility::set_and_get_hidden_property( $instance, 'table_heal_attempted', false );
	}

	/**
	 * A projection larger than one chunk is split into bounded statements and
	 * still lands completely, with the summed count truthful.
	 *
	 * A daily rule over a 40-month horizon expands to roughly 1,200 rows,
	 * comfortably above the 1,000-row chunk bound and safely clear of any
	 * calendar-length wobble ever dropping it below one chunk.
	 *
	 * @covers ::project
	 * @covers ::upsert_occurrences
	 * @covers ::insert_or_update_rows
	 *
	 * @return void
	 */
	public function test_projection_wider_than_one_chunk_is_chunked_and_complete(): void {
		global $wpdb;

		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);
		Meta::get_instance()->set_recurrence( $post_id );

		$wide_horizon = static fn() => 40;
		add_filter( 'gatherpress_recurrence_horizon_months', $wide_horizon );

		$queries_before = count( (array) $wpdb->queries );

		$written = Occurrences::get_instance()->project( $post_id );

		remove_filter( 'gatherpress_recurrence_horizon_months', $wide_horizon );

		$table      = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$row_groups = array();

		foreach ( array_slice( (array) $wpdb->queries, $queries_before ) as $query ) {
			$sql = (string) $query[0];

			if ( ! str_starts_with( $sql, 'INSERT INTO' ) || ! str_contains( $sql, $table ) ) {
				continue;
			}

			// Count the per-row value groups between VALUES and the
			// ON DUPLICATE clause; nothing inside a value produces a paren.
			$values_part  = substr( $sql, strpos( $sql, ' VALUES ' ) );
			$values_part  = substr( $values_part, 0, strpos( $values_part, ' ON DUPLICATE' ) );
			$row_groups[] = substr_count( $values_part, '(' );
		}

		$this->assertIsInt( $written, 'Failed to assert the oversized projection reported success.' );
		$this->assertGreaterThan(
			1000,
			$written,
			'Failed to build a projection wider than one chunk; the fixture no longer exercises chunking.'
		);
		$this->assertSame(
			(int) ceil( $written / 1000 ),
			count( $row_groups ),
			'Failed to assert the insert was split into one statement per chunk.'
		);
		$this->assertLessThanOrEqual(
			1000,
			max( $row_groups ),
			'Failed to assert no single insert statement exceeds the chunk bound.'
		);
		$this->assertSame(
			$written,
			array_sum( $row_groups ),
			'Failed to assert the chunked statements carry exactly the reported rows.'
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE series_post_id = %d', $table, $post_id )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertSame( $written, $stored, 'Failed to assert every reported row is present in the table.' );
	}

	/**
	 * `top_up()` counts only series whose projection actually succeeded, so a
	 * sweep that failed every write does not report the batch as topped up.
	 *
	 * @covers ::top_up
	 *
	 * @return void
	 */
	public function test_top_up_reports_only_series_that_projected_successfully(): void {
		$this->create_short_horizon_never_ending_series();
		$this->create_short_horizon_never_ending_series();

		$filter = $this->break_occurrence_statements( 'INSERT INTO' );

		$written = Occurrences::get_instance()->top_up();

		remove_filter( 'query', $filter );

		$this->assertSame(
			0,
			$written,
			'Failed to assert that top_up reports zero when every projection failed.'
		);
	}

	/**
	 * Coverage for both single-post writes reporting a refused statement.
	 *
	 * `set_status()` used to issue its `UPDATE` and return a bare `true`, and
	 * `delete_for_post()` used to cast the result to `int`, which turns `false`
	 * into a successful delete of zero rows. Both now answer with what the
	 * database did. The distinction matters most for `set_status()`: it is the
	 * single write behind cancel and un-cancel, so a caller told `true` after a
	 * refused write shows an occurrence canceled that is still `scheduled` and
	 * still selected by every listing.
	 *
	 * The refusal is injected by redirecting the statement to a table that is
	 * not there, rather than by dropping the real one, because dropping it is
	 * DDL and commits implicitly, escaping the test transaction.
	 *
	 * @covers ::set_status
	 * @covers ::delete_for_post
	 *
	 * @return void
	 */
	public function test_single_post_writes_report_a_refused_statement(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();
		$rows     = $instance->select_for_series( array( $post_id ) );

		$this->assertNotEmpty( $rows, 'Failed to arrange projected rows for the write to refuse.' );

		$update = $this->break_occurrence_statements( 'UPDATE' );

		$status_result = $instance->set_status(
			$post_id,
			(string) $rows[0]['recurrence_id'],
			Occurrences::STATUS_CANCELED
		);

		remove_filter( 'query', $update );

		$this->assertFalse(
			$status_result,
			'Failed to assert set_status() reports a refused UPDATE rather than returning true.'
		);

		$delete = $this->break_occurrence_statements( 'DELETE' );

		$delete_result = $instance->delete_for_post( $post_id );

		remove_filter( 'query', $delete );

		$this->assertFalse(
			$delete_result,
			'Failed to assert delete_for_post() distinguishes a refused DELETE from deleting no rows.'
		);

		$this->assertSame(
			0,
			$instance->delete_for_post( -1 ),
			'Failed to assert a post with no rows still reports zero rather than false.'
		);
	}

	/**
	 * `find_in_series()` resolves nothing for an unusable or unmatched lookup.
	 *
	 * @covers ::find_in_series
	 *
	 * @return void
	 */
	public function test_find_in_series_returns_null_when_nothing_matches(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();

		$this->assertNull(
			$instance->find_in_series( array(), '20260915T180000' ),
			'Failed to assert an empty series resolves nothing.'
		);
		$this->assertNull(
			$instance->find_in_series( array( $post_id ), '' ),
			'Failed to assert an empty identifier resolves nothing.'
		);
		$this->assertNull(
			$instance->find_in_series( array( $post_id ), '20991231T235959' ),
			'Failed to assert an identifier the series does not carry resolves nothing.'
		);
		$this->assertNull(
			$instance->find_in_series( array( $post_id + 1000 ), '20260915T180000' ),
			'Failed to assert an occurrence of another series does not resolve here.'
		);
	}

	/**
	 * An identifier carried by two posts of one series resolves deterministically.
	 *
	 * `find_in_series()` is `LIMIT 1`, and a `LIMIT` without an `ORDER BY` means
	 * whatever the query plan returns. That is not cosmetic once a forward split lets a
	 * series span several posts: the `series_post_id` this returns is what every
	 * consumer keys the RSVP's occurrence term off, so an unstable pick would
	 * move a responder's RSVP between sibling posts from one request to the
	 * next. Lowest post ID wins.
	 *
	 * The two candidate rows are inserted **highest post ID first**, so a plan
	 * returning insertion order would produce the wrong answer rather than
	 * accidentally the right one.
	 *
	 * @covers ::find_in_series
	 *
	 * @return void
	 */
	public function test_find_in_series_picks_the_lowest_post_id_deterministically(): void {
		global $wpdb;

		$lower_post_id  = $this->create_and_project();
		$higher_post_id = $this->create_and_project();

		$this->assertLessThan(
			$higher_post_id,
			$lower_post_id,
			'Failed to arrange two posts whose IDs order the way this test assumes.'
		);

		$table         = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$recurrence_id = '20260915T180000';

		// Both fixtures already carry this identifier, and the higher post's row
		// was written second, so the physical order favors the wrong answer.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT series_post_id FROM %i WHERE recurrence_id = %s AND series_post_id IN ( %d, %d )',
				$table,
				$recurrence_id,
				$lower_post_id,
				$higher_post_id
			)
		);

		$this->assertCount(
			2,
			$rows,
			'Failed to arrange one row per post for the shared identifier.'
		);

		$resolved = Occurrences::get_instance()->find_in_series(
			array( $higher_post_id, $lower_post_id ),
			$recurrence_id
		);

		$this->assertNotNull( $resolved, 'Failed to assert the shared identifier resolves at all.' );
		$this->assertSame(
			$lower_post_id,
			(int) $resolved['series_post_id'],
			'Failed to assert find_in_series picks the lowest series post ID rather than a plan-dependent row.'
		);

		// Argument order must not change the answer either. The ORDER BY decides,
		// not the caller's array.
		$this->assertSame(
			$lower_post_id,
			(int) Occurrences::get_instance()->find_in_series(
				array( $lower_post_id, $higher_post_id ),
				$recurrence_id
			)['series_post_id'],
			'Failed to assert the resolution is independent of the order the post IDs are passed in.'
		);
	}


	/**
	 * The `limit` argument caps the rows and is clamped at both ends.
	 *
	 * A `limit` a caller can set to zero would return nothing and one it can
	 * set past `Rule::MAX_COUNT` would hand back the unbounded read the
	 * argument exists to prevent, so both are clamped rather than passed
	 * through.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_limit_is_applied_and_clamped(): void {
		$post_id  = $this->create_and_project();
		$instance = Occurrences::get_instance();
		$all      = $instance->select_for_series( array( $post_id ) );

		$this->assertGreaterThan( 2, count( $all ), 'Failed to arrange more rows than the limit asks for.' );

		$this->assertCount(
			2,
			$instance->select_for_series( array( $post_id ), array( 'limit' => 2 ) ),
			'Failed to assert the limit caps the returned rows.'
		);
		$this->assertCount(
			0,
			$instance->select_for_series( array( $post_id ), array( 'limit' => 0 ) ),
			'Failed to assert a zero limit selects nothing rather than falling back to every row.'
		);
		$this->assertCount(
			count( $all ),
			$instance->select_for_series( array( $post_id ), array( 'limit' => Rule::MAX_COUNT + 500 ) ),
			'Failed to assert an oversized limit is clamped to the largest projection a rule can produce.'
		);
	}

	/**
	 * Every path of the per-post display pick, guards included.
	 *
	 * The PHP twin of `display_occurrence_relation_sql()`, and the one the
	 * admin list's date column reads, so its answer has to agree with the
	 * relation's on the same fixture: the earliest unfinished occurrence where
	 * one exists, the latest finished one where none does. Each of the three
	 * guard arms is exercised on its own, because they are one statement and
	 * line coverage would report the guard as covered with two of them never
	 * evaluated.
	 *
	 * @covers ::select_display_for_series
	 *
	 * @return void
	 */
	public function test_select_display_for_series_covers_every_path(): void {
		global $wpdb;

		$now      = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$daily    = array(
			'frequency' => 'daily',
			'interval'  => 1,
			'end_type'  => 'count',
			'count'     => 5,
		);
		$instance = Occurrences::get_instance();

		$running = $this->create_relative_recurring_event(
			$daily,
			$now->modify( '-2 days +5 hours' ),
			$now->modify( '-2 days +6 hours' ),
			'UTC'
		);
		$elapsed = $this->create_relative_recurring_event(
			$daily,
			$now->modify( '-30 days' ),
			$now->modify( '-30 days +1 hour' ),
			'UTC'
		);

		$this->assertSame(
			$now->modify( '+5 hours' )->format( 'Y-m-d H:i:s' ),
			$instance->select_display_for_series( array( $running ) )['datetime_start_gmt'],
			'Failed to assert a running series resolves to its earliest unfinished occurrence.'
		);
		$this->assertSame(
			$now->modify( '-26 days' )->format( 'Y-m-d H:i:s' ),
			$instance->select_display_for_series( array( $elapsed ) )['datetime_start_gmt'],
			'Failed to assert an elapsed series falls back to its latest finished occurrence.'
		);
		$this->assertNull(
			$instance->select_display_for_series( array() ),
			'Failed to assert an empty post ID list resolves nothing.'
		);

		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		$this->assertNull(
			$instance->select_display_for_series( array( $running ) ),
			'Failed to assert a site with no recurring events never probes the occurrence table.'
		);

		update_option( Query::HAS_RECURRING_OPTION, '1', true );

		// The memoized probe answer, forced to "absent" so the third guard arm
		// is the only one that can act. A blog joined to a network after the
		// table was created is the production shape of this.
		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		Utility::set_and_get_hidden_property( $instance, 'table_exists', array( $table => false ) );

		$absent = $instance->select_display_for_series( array( $running ) );

		$instance->forget_table_exists();

		$this->assertNull( $absent, 'Failed to assert a blog without the occurrence table resolves nothing.' );
	}

	/**
	 * The display relation answers one row per post, carrying a paired start and end.
	 *
	 * This is the single definition every admin-list date decision reads, so
	 * the properties asserted are the ones those consumers depend on: exactly
	 * one row per post that owns scheduled rows, the earliest unfinished
	 * occurrence where one exists, the latest finished one where none does,
	 * and the *same* occurrence's start and end rather than two independent
	 * aggregates.
	 *
	 * @covers ::display_occurrence_relation_sql
	 *
	 * @return void
	 */
	public function test_display_occurrence_relation_answers_one_paired_row_per_post(): void {
		global $wpdb;

		$now     = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$daily   = array(
			'frequency' => 'daily',
			'interval'  => 1,
			'end_type'  => 'count',
			'count'     => 5,
		);
		$running = $this->create_relative_recurring_event(
			$daily,
			$now->modify( '-2 days +5 hours' ),
			$now->modify( '-2 days +6 hours' ),
			'UTC'
		);
		$elapsed = $this->create_relative_recurring_event(
			$daily,
			$now->modify( '-30 days' ),
			$now->modify( '-30 days +1 hour' ),
			'UTC'
		);

		$relation = Occurrences::get_instance()->display_occurrence_relation_sql(
			$now->format( 'Y-m-d H:i:s' )
		);

		$this->assertStringNotContainsString(
			'%',
			$relation,
			'Failed to assert the relation is fully prepared, with no placeholder left to interpolate.'
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- the relation arrives fully prepared.
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . $relation . ' AS relation ORDER BY relation.series_post_id ASC',
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$this->assertCount( 2, $rows, 'Failed to assert the relation answers exactly one row per post.' );

		$keyed = array_column( $rows, null, 'series_post_id' );

		$this->assertSame(
			$now->modify( '+5 hours' )->format( 'Y-m-d H:i:s' ),
			$keyed[ (string) $running ]['datetime_start_gmt'],
			'Failed to assert a running series is represented by its earliest unfinished occurrence.'
		);
		$this->assertSame(
			$now->modify( '+6 hours' )->format( 'Y-m-d H:i:s' ),
			$keyed[ (string) $running ]['datetime_end_gmt'],
			'Failed to assert the relation carries that same occurrence\'s own end, not a whole-post maximum.'
		);
		$this->assertSame(
			$now->modify( '-26 days' )->format( 'Y-m-d H:i:s' ),
			$keyed[ (string) $elapsed ]['datetime_start_gmt'],
			'Failed to assert an elapsed series falls back to its latest finished occurrence.'
		);
		$this->assertSame(
			$now->modify( '-26 days +1 hour' )->format( 'Y-m-d H:i:s' ),
			$keyed[ (string) $elapsed ]['datetime_end_gmt'],
			'Failed to assert the elapsed fallback also carries its own paired end.'
		);
	}

	/**
	 * The display relation ignores canceled rows entirely.
	 *
	 * Cancellation is occurrence state, and a canceled occurrence must never
	 * become the date a list sorts, buckets or reads by. With the series' only
	 * unfinished occurrence canceled, the relation has to fall back to the
	 * latest *finished* one rather than keep the canceled row.
	 *
	 * @covers ::display_occurrence_relation_sql
	 *
	 * @return void
	 */
	public function test_display_occurrence_relation_ignores_canceled_rows(): void {
		global $wpdb;

		$now     = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => 2,
			),
			$now->modify( '-1 day +5 hours' ),
			$now->modify( '-1 day +6 hours' ),
			'UTC'
		);

		$instance = Occurrences::get_instance();
		$cutoff   = $now->format( 'Y-m-d H:i:s' );
		$upcoming = array_values(
			array_filter(
				$instance->select_for_series( array( $post_id ) ),
				static function ( array $row ) use ( $cutoff ): bool {
					return $row['datetime_end_gmt'] >= $cutoff;
				}
			)
		);

		$this->assertCount( 1, $upcoming, 'Failed to arrange exactly one unfinished occurrence.' );

		$instance->set_status( $post_id, $upcoming[0]['recurrence_id'], Occurrences::STATUS_CANCELED );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- the relation arrives fully prepared.
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . $instance->display_occurrence_relation_sql( $now->format( 'Y-m-d H:i:s' ) )
			. ' AS relation',
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		$this->assertCount( 1, $rows, 'Failed to assert the post is still represented once.' );
		$this->assertSame(
			$now->modify( '-1 day +5 hours' )->format( 'Y-m-d H:i:s' ),
			$rows[0]['datetime_start_gmt'],
			'Failed to assert the canceled occurrence is skipped in favor of the latest finished one.'
		);
	}

	/**
	 * A sibling tie is broken by the post ID, which is the only column that can.
	 *
	 * `select_bounded_occurrence()` is `LIMIT 1`, and the tie it has to break
	 * is between two posts of a split series contributing rows that share a
	 * start. `recurrence_id` cannot break it: it is the occurrence's own local
	 * start rendered `Ymd\THis`, so two rows sharing a start share it too.
	 *
	 * The emitted statement is pinned, not merely the row it returns. InnoDB
	 * hands back tied rows in clustered-key order today, so the lowest post ID
	 * comes back first with or without a tie-breaker, and a row-order
	 * assertion would measure the current plan rather than the guarantee. Both
	 * directions are pinned, because the bound flips the sort direction and
	 * only the post ID stays ascending.
	 *
	 * @covers ::select_bounded_occurrence
	 *
	 * @return void
	 */
	public function test_select_bounded_occurrence_breaks_a_sibling_tie_on_the_post_id(): void {
		global $wpdb;

		$now  = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$rule = array(
			'frequency' => 'daily',
			'interval'  => 1,
			'end_type'  => 'count',
			'count'     => 3,
		);

		$first  = $this->create_relative_recurring_event(
			$rule,
			$now->modify( '+5 hours' ),
			$now->modify( '+6 hours' ),
			'UTC'
		);
		$second = $this->create_relative_recurring_event(
			$rule,
			$now->modify( '+5 hours' ),
			$now->modify( '+6 hours' ),
			'UTC'
		);

		$lower    = min( $first, $second );
		$higher   = max( $first, $second );
		$instance = Occurrences::get_instance();
		$table    = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$directions = array(
			'ASC'  => true,
			'DESC' => false,
		);

		foreach ( $directions as $direction => $upcoming ) {
			$query_count_before = count( $wpdb->queries );

			$row = $instance->select_bounded_occurrence( array( $higher, $lower ), $upcoming );

			$statements = array_values(
				array_filter(
					array_slice( $wpdb->queries, $query_count_before ),
					static function ( $query ) use ( $table ): bool {
						return str_contains( $query[0], $table )
							&& str_contains( $query[0], 'ORDER BY datetime_start_gmt' );
					}
				)
			);

			$this->assertCount(
				1,
				$statements,
				sprintf( 'Failed to capture exactly one %s bounded statement.', $direction )
			);
			$this->assertStringContainsString(
				sprintf(
					'ORDER BY datetime_start_gmt %1$s, series_post_id ASC, recurrence_id %1$s',
					$direction
				),
				$statements[0][0],
				sprintf(
					'Failed to assert the %s bounded read breaks a sibling tie on the post ID.',
					$direction
				)
			);

			if ( $upcoming ) {
				$this->assertSame(
					$lower,
					(int) $row['series_post_id'],
					'Failed to assert the earliest unfinished pick resolves to the lowest sibling post ID.'
				);
				$this->assertSame(
					$lower,
					(int) $instance->select_bounded_occurrence( array( $lower, $higher ), true )['series_post_id'],
					'Failed to assert the pick is independent of the order the post IDs are passed in.'
				);
			}
		}
	}

	/**
	 * `select_for_series()` orders its rows chronologically rather than trusting
	 * the query plan.
	 *
	 * More consequential than the same guarantee on `find_in_series()`, because
	 * this ordering is what `Splitter::split_owned_series()` derives `$index`
	 * from, and `$index` decides which occurrences stay behind, how the origin
	 * rule is capped, and whether either side demotes to a plain event. An
	 * unstable order here does not return the wrong row; it partitions the
	 * series at the wrong date.
	 *
	 * The behavioral half cannot fail on InnoDB today and is not pretended
	 * otherwise: the table's clustered primary key is
	 * `(series_post_id, recurrence_id)`, and a `Ymd\THis` identifier sorts
	 * lexically in chronological order, so a primary-key scan happens to answer
	 * correctly with the clause deleted (measured, not assumed). The ordering is
	 * a guarantee against a future query plan rather than against today's
	 * observed one, so the statement itself is pinned. Otherwise the guarantee
	 * is verified by nothing.
	 *
	 * @covers ::select_for_series
	 *
	 * @return void
	 */
	public function test_select_for_series_orders_by_start_rather_than_by_query_plan(): void {
		$post_id = $this->create_and_project();

		$statements = array();
		$capture    = static function ( string $query ) use ( &$statements ): string {
			$statements[] = $query;

			return $query;
		};

		add_filter( 'query', $capture );

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		remove_filter( 'query', $capture );

		$starts = array_map( 'strval', wp_list_pluck( $rows, 'datetime_start_gmt' ) );
		$sorted = $starts;
		sort( $sorted );

		$this->assertNotEmpty( $starts, 'Failed to arrange a projected series to read back.' );
		$this->assertSame(
			$sorted,
			$starts,
			'Failed to assert the rows come back in ascending start order.'
		);
		$this->assertNotEmpty(
			array_filter(
				$statements,
				static function ( string $query ): bool {
					return str_contains( $query, 'ORDER BY datetime_start_gmt ASC' );
				}
			),
			'Failed to assert select_for_series() orders its rows rather than trusting the query plan.'
		);
	}

	/**
	 * Direct coverage for the empty-candidate bail of `discard_sibling_owned()`.
	 *
	 * The helper's four return paths are invoked directly because xdebug does
	 * not trace a protected helper called through a short same-class delegation
	 * from `run_projection()` (see the "Extracted same-class helpers" rule in
	 * `AGENTS.md`), even though the stale-re-persist regression in
	 * `Test_Splitter` drives the filtering path through a real save.
	 *
	 * @covers ::discard_sibling_owned
	 *
	 * @return void
	 */
	public function test_discard_sibling_owned_returns_empty_candidates_unchanged(): void {
		$this->assertSame(
			array(),
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'discard_sibling_owned',
				array( 123, array() )
			),
			'An empty candidate set must come back empty without a membership read.'
		);
	}

	/**
	 * A single-post series pays no sibling query and loses no rows.
	 *
	 * @covers ::discard_sibling_owned
	 *
	 * @return void
	 */
	public function test_discard_sibling_owned_is_a_no_op_for_a_single_post_series(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rows    = array( array( 'recurrence_id' => '20260903T180000' ) );

		$this->assertSame(
			$rows,
			Utility::invoke_hidden_method(
				Occurrences::get_instance(),
				'discard_sibling_owned',
				array( $post_id, $rows )
			),
			'A series of one post has no sibling that could own anything.'
		);
	}

	/**
	 * A sibling that owns no rows removes nothing.
	 *
	 * @covers ::discard_sibling_owned
	 *
	 * @return void
	 */
	public function test_discard_sibling_owned_is_a_no_op_when_the_sibling_owns_nothing(): void {
		$post_id    = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$sibling_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rows       = array( array( 'recurrence_id' => '20260903T180000' ) );
		$widen      = static function () use ( $post_id, $sibling_id ) {
			return array( $post_id, $sibling_id );
		};

		add_filter( 'gatherpress_series_post_ids', $widen );

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'discard_sibling_owned',
			array( $post_id, $rows )
		);

		remove_filter( 'gatherpress_series_post_ids', $widen );

		$this->assertSame(
			$rows,
			$result,
			'A sibling owning no occurrence rows must not cost the projection anything.'
		);
	}

	/**
	 * Only the rows a sibling owns are dropped; every other candidate survives.
	 *
	 * @covers ::discard_sibling_owned
	 *
	 * @return void
	 */
	public function test_discard_sibling_owned_drops_only_the_rows_a_sibling_owns(): void {
		$sibling_id = $this->create_recurring_event( self::WEEKLY_RULE );

		Meta::get_instance()->set_recurrence( $sibling_id );
		Occurrences::get_instance()->project( $sibling_id );

		$owned = Occurrences::get_instance()->select_for_series( array( $sibling_id ) );

		$this->assertNotEmpty( $owned, 'Fixture setup: the sibling must own projected rows.' );

		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$taken    = (string) $owned[0]['recurrence_id'];
		$free     = '19990101T000000';
		$rows     = array(
			array( 'recurrence_id' => $taken ),
			array( 'recurrence_id' => $free ),
		);
		$expected = array( array( 'recurrence_id' => $free ) );
		$widen    = static function () use ( $post_id, $sibling_id ) {
			return array( $post_id, $sibling_id );
		};

		add_filter( 'gatherpress_series_post_ids', $widen );

		$result = Utility::invoke_hidden_method(
			Occurrences::get_instance(),
			'discard_sibling_owned',
			array( $post_id, $rows )
		);

		remove_filter( 'gatherpress_series_post_ids', $widen );

		$this->assertSame(
			$expected,
			$result,
			'Exactly the sibling-owned identifier must be dropped, and nothing else.'
		);
	}
	/**
	 * Consuming the queue entry must not consume the cleanup it carries.
	 *
	 * `resolve_pending_projection()` hands `run_projection()` the
	 * `$was_recurring` flag captured at queue time, and that flag is what
	 * drives the removal-cleanup arm for a blob deleted by a writer that never
	 * fires the save hook. Dropping the entry would lose that signal if the
	 * pass that drops it did less. It does not: `project()` runs with
	 * `$cleanup_when_not_recurring` hard-coded to `true`, the strongest value
	 * the queue can hold, so the rows of a series whose blob is deleted in the
	 * same request as a datetime write are still cleared, in one delete rather
	 * than two.
	 *
	 * @covers ::project
	 * @covers ::maybe_queue_projection
	 * @covers ::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_blob_deleted_beside_a_datetime_write_still_clears_its_rows(): void {
		global $wpdb;

		$post_id = $this->create_and_project();

		$this->assertCount( 5, Occurrences::get_instance()->select_for_series( array( $post_id ) ) );

		$offset = count( $wpdb->queries );

		update_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $this->reference_anchor_start,
					'dateTimeEnd'   => $this->reference_anchor_end,
					'timezone'      => 'America/Chicago',
				)
			)
		);
		delete_post_meta( $post_id, Meta::META_KEY );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$statements = $this->occurrence_statements_since( $offset );

		$this->assertCount(
			0,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert that the removed rule left no orphaned occurrence rows.'
		);
		$this->assertSame(
			1,
			$this->count_statements( $statements, 'DELETE' ),
			'Failed to assert that the cleanup ran once rather than once per shutdown pass.'
		);
		$this->assertSame(
			0,
			$this->count_statements( $statements, 'INSERT' ),
			'Failed to assert that a series with no rule wrote no occurrence rows.'
		);
	}

	/**
	 * A request that rewrites both the datetime blob and the recurrence blob
	 * projects the series once, not twice.
	 *
	 * The datetime write queues `Recurrence\Meta::resolve_pending_revalidation()`
	 * at `shutdown` priority 15, which re-validates the rule and then calls
	 * `project()`. The blob write independently queues
	 * `resolve_pending_projection()` at priority 20 for the same post. The
	 * priority gap orders the two passes; on its own it does not stop the
	 * second from repeating the first, which is a full expand plus an upsert
	 * chunk plus a stale-row delete for every series an organizer retimes.
	 * `project()` consuming the queue entry is what makes the request issue
	 * one projection.
	 *
	 * @covers ::project
	 * @covers ::maybe_queue_projection
	 * @covers ::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_revalidated_series_projects_once_in_one_request(): void {
		global $wpdb;

		$post_id = $this->create_and_project();
		$offset  = count( $wpdb->queries );

		// A timezone edit, then a rule edit, both through the real meta
		// hooks that queue the two shutdown passes.
		update_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $this->reference_anchor_start,
					'dateTimeEnd'   => $this->reference_anchor_end,
					'timezone'      => 'America/Chicago',
				)
			)
		);
		update_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode( array_merge( self::WEEKLY_RULE, array( 'count' => 4 ) ) )
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$statements = $this->occurrence_statements_since( $offset );

		$this->assertSame(
			1,
			$this->count_statements( $statements, 'INSERT' ),
			'Failed to assert that the re-validated series was upserted exactly once.'
		);
		$this->assertSame(
			1,
			$this->count_statements( $statements, 'DELETE' ),
			'Failed to assert that the re-validated series pruned its stale rows exactly once.'
		);
		$this->assertCount(
			4,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert that the single projection wrote the edited rule.'
		);
	}

	/**
	 * Count the statements of one kind among a set of collected statements.
	 *
	 * @since 0.36.0
	 *
	 * @param array<int, string> $statements Statements to count within.
	 * @param string             $keyword    Leading SQL keyword to count.
	 *
	 * @return int How many statements start with the keyword.
	 */
	protected function count_statements( array $statements, string $keyword ): int {
		return count(
			array_filter(
				$statements,
				static fn( string $sql ): bool => str_starts_with( ltrim( $sql ), $keyword )
			)
		);
	}

	/**
	 * Collect the statements this request issued against the occurrence table.
	 *
	 * @since 0.36.0
	 *
	 * @param int $offset Index into `$wpdb->queries` to start reading from.
	 *
	 * @return array<int, string> The matching statements, in the order they ran.
	 */
	protected function occurrence_statements_since( int $offset ): array {
		global $wpdb;

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		return array_values(
			array_map(
				static fn( array $query ): string => (string) $query[0],
				array_filter(
					array_slice( $wpdb->queries, $offset ),
					static fn( array $query ): bool => str_contains( (string) $query[0], $table )
				)
			)
		);
	}
	/**
	 * Coverage for the bounded read's refusal arm.
	 *
	 * The read carries the same schema probe its sibling single-row reads do,
	 * and it refuses on two inputs: an empty post ID list, and a blog whose
	 * table is not there. The second is the one that matters, because a bare
	 * series URL reaches this read on the front end, so an unprobed statement
	 * would surface a database error to a visitor rather than an empty answer.
	 *
	 * The absent table is simulated by seeding the probe's own memo rather than
	 * by dropping the table. Dropping it is DDL, which commits implicitly and
	 * so escapes the test transaction: an earlier draft did exactly that and
	 * left a later admin-list assertion querying a table that was gone.
	 * Seeding the memo exercises the same branch and leaves nothing behind.
	 *
	 * @covers ::select_bounded_occurrence
	 *
	 * @return void
	 */
	public function test_select_bounded_occurrence_refuses_without_a_table_or_post_ids(): void {
		global $wpdb;

		$instance = Occurrences::get_instance();
		$table    = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$this->assertNull(
			$instance->select_bounded_occurrence( array(), true ),
			'Failed to assert the bounded read refuses an empty post ID list.'
		);

		$restore = Utility::get_hidden_property( $instance, 'table_exists' );

		Utility::set_and_get_hidden_property( $instance, 'table_exists', array( $table => false ) );

		$last_error = $wpdb->last_error;

		$this->assertNull(
			$instance->select_bounded_occurrence( array( 1 ), true ),
			'Failed to assert the bounded read refuses a blog whose occurrence table is absent.'
		);

		$this->assertSame(
			$last_error,
			$wpdb->last_error,
			'Failed to assert the refusal issued no statement and recorded no database error.'
		);

		Utility::set_and_get_hidden_property( $instance, 'table_exists', $restore );
	}
}
