<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Context.
 *
 * The claim under test is that an occurrence's datetime reaches every existing
 * block without a single block file changing. A test that calls the filter callback
 * directly cannot prove that, so the block tests here go through `go_to()` and
 * `do_blocks()`, which means the real request, the real block and the real
 * wiring.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Assets;
use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Rewrite;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Topic;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use RuntimeException;
use WP_Query;

/**
 * Class Test_Context.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Context
 */
class Test_Context extends Base {

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
	 * Recurrence identifier of the reference set's second occurrence.
	 *
	 * Deliberately not the first: an implementation that returns the series
	 * anchor rather than the occurrence record passes against the first entry
	 * by coincidence, because the anchor *is* the first occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SECOND_ID = '20260915T180000';

	/**
	 * Local start of the reference set's second occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SECOND_START = '2026-09-15 18:00:00';

	/**
	 * GMT start of the reference set's second occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SECOND_START_GMT = '2026-09-15 22:00:00';

	/**
	 * GMT start of the series anchor, which is also the first occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const ANCHOR_START_GMT = '2026-09-03 22:00:00';

	/**
	 * Series-level local start given to the sibling series, unmistakably its own.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SIBLING_START = '2030-01-01 08:00:00';

	/**
	 * Series-level GMT start given to the sibling series.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SIBLING_START_GMT = '2030-01-01 13:00:00';

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

		// Pretty permalinks and the occurrence rewrite rule, so every `go_to()`
		// below travels the production URL, `/{event-slug}/{postname}/{Ymd\THis}/`
		// resolved by the real rewrite rules, rather than a query-arg stand-in
		// that no visitor ever sends. Mirrors `Test_Rewrite::setUp()`, including
		// the post type re-registration: WP only builds a post type's pretty
		// permastruct when `permalink_structure` is non-empty at registration
		// time, and the bootstrap registered it while permalinks were plain.
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		unregister_taxonomy( Topic::TAXONOMY );
		Topic::get_instance()->register_taxonomy();

		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Leave no occurrence context or rewrite state behind for the next test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Context::get_instance()->clear();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules();

		parent::tearDown();
	}

	/**
	 * Create the reference recurring event and project its occurrence rows.
	 *
	 * @since 0.36.0
	 *
	 * @return int The projected post ID.
	 */
	protected function create_and_project(): int {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return $post_id;
	}

	/**
	 * Create a projected daily series straddling "now", anchored relative to it.
	 *
	 * The reference fixture's anchor is a fixed 2026 date, which is fine for
	 * assertions about a *named* occurrence but a date bomb for anything about
	 * the *next upcoming* one. Once real time passes the last occurrence, the
	 * series has lapsed and the bare-series resolution finds nothing.
	 *
	 * The anchor is placed in the past and the interval chosen so exactly one
	 * occurrence is behind "now" and two are ahead. That makes the next upcoming
	 * occurrence neither the anchor nor the first row, so an implementation that
	 * returned either is distinguishable from one that genuinely picks the next.
	 *
	 * @since 0.36.0
	 *
	 * @return array{0:int,1:string,2:string} Post ID, past recurrence ID, next upcoming recurrence ID.
	 */
	protected function create_series_straddling_now(): array {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-5 days' );
		$post_id  = $this->factory->post->create(
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
					'dateTimeStart' => $anchor->format( Event::DATETIME_FORMAT ),
					'dateTimeEnd'   => $anchor->modify( '+2 hours' )->format( Event::DATETIME_FORMAT ),
					'timezone'      => $timezone->getName(),
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );

		add_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 7,
					'end_type'  => 'count',
					'count'     => 3,
				)
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return array(
			$post_id,
			Occurrences::recurrence_id( $anchor ),
			Occurrences::recurrence_id( $anchor->modify( '+7 days' ) ),
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
		$instance = Context::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'get_post_metadata',
				'priority' => 10,
				'callback' => array( $instance, 'metadata' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp',
				'priority' => 10,
				'callback' => array( $instance, 'sync' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'update_post_metadata',
				'priority' => PHP_INT_MAX,
				'callback' => array( $instance, 'note_meta_write' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'the_content',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_prepend_canceled_notice' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'post_type_link',
				'priority' => 10,
				'callback' => array( $instance, 'permalink' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'post_link',
				'priority' => 10,
				'callback' => array( $instance, 'permalink' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );

		// `add_metadata()` never calls `get_metadata_raw()`, so a note taken
		// there is never consumed by the write that took it. It is consumed
		// by the next ordinary read instead, which then returns the series'
		// value on an occurrence page.
		$this->assertFalse(
			has_filter( 'add_post_metadata', array( $instance, 'note_meta_write' ) ),
			'Failed to assert add_post_metadata is not hooked: add_metadata() makes no comparison read,'
				. ' so a note taken there can only ever be consumed by the wrong reader.'
		);
	}

	/**
	 * Coverage for `current()` outside occurrence context.
	 *
	 * @covers ::current
	 * @covers ::clear
	 *
	 * @return void
	 */
	public function test_current_returns_null_outside_context(): void {
		$this->assertNull(
			Context::get_instance()->current(),
			'Failed to assert that current() is null outside occurrence context.'
		);
	}

	/**
	 * Coverage for `set()` entering the context of a real occurrence row.
	 *
	 * @covers ::set
	 * @covers ::current
	 *
	 * @return void
	 */
	public function test_set_enters_the_context_of_an_existing_occurrence(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$current = Context::get_instance()->current();

		$this->assertIsArray( $current, 'Failed to assert that current() returns the occurrence row.' );
		$this->assertSame(
			self::SECOND_ID,
			$current['recurrence_id'],
			'Failed to assert that the context carries the requested recurrence_id.'
		);
		$this->assertSame(
			$post_id,
			(int) $current['series_post_id'],
			'Failed to assert that the context carries the series post ID.'
		);
	}

	/**
	 * Coverage for `set()` when the composite key matches no row.
	 *
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_set_leaves_context_unset_when_the_composite_key_matches_nothing(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, '20991231T235900' );

		$this->assertNull(
			Context::get_instance()->current(),
			'Failed to assert that an unmatched composite key leaves the context unset.'
		);
	}

	/**
	 * Coverage for the metadata filter serving the occurrence's datetime.
	 *
	 * @covers ::metadata
	 * @covers ::occurrence_value
	 *
	 * @return void
	 */
	public function test_metadata_returns_occurrence_datetime_in_context(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			self::SECOND_START,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that the occurrence local start is served in context.'
		);
		$this->assertSame(
			self::SECOND_START_GMT,
			get_post_meta( $post_id, 'gatherpress_datetime_start_gmt', true ),
			'Failed to assert that the occurrence GMT start is served in context.'
		);
		$this->assertSame(
			'2026-09-15 20:00:00',
			get_post_meta( $post_id, 'gatherpress_datetime_end', true ),
			'Failed to assert that the occurrence local end is served in context.'
		);
		$this->assertSame(
			'2026-09-16 00:00:00',
			get_post_meta( $post_id, 'gatherpress_datetime_end_gmt', true ),
			'Failed to assert that the occurrence GMT end is served in context.'
		);
		$this->assertSame(
			'America/New_York',
			get_post_meta( $post_id, 'gatherpress_timezone', true ),
			'Failed to assert that the occurrence timezone is served in context.'
		);
	}

	/**
	 * Coverage for the metadata filter standing aside outside occurrence context.
	 *
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_metadata_returns_series_datetime_outside_context(): void {
		$post_id = $this->create_and_project();

		$this->assertSame(
			$this->reference_anchor_start,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that the series datetime is served outside occurrence context.'
		);
	}

	/**
	 * Coverage for the metadata filter scoping its substitution to the series post.
	 *
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_metadata_returns_series_datetime_for_a_different_post(): void {
		$post_id = $this->create_and_project();
		$other   = $this->create_recurring_event( self::WEEKLY_RULE );

		update_post_meta( $other, 'gatherpress_datetime_start', '2030-01-01 08:00:00' );

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			'2030-01-01 08:00:00',
			get_post_meta( $other, 'gatherpress_datetime_start', true ),
			'Failed to assert that another post keeps its own datetime while context is set.'
		);
	}

	/**
	 * Coverage for the metadata filter ignoring meta keys it does not own.
	 *
	 * Asserted against a multi-value key rather than a single-value one: a
	 * filter missing its key allowlist still answers a single-value read with
	 * the right string by way of the series fallback, so a single-value
	 * assertion here cannot fail. Collapsing a multi-value read to one entry is
	 * the damage an unscoped filter actually does.
	 *
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_metadata_leaves_unrelated_meta_keys_untouched(): void {
		$post_id = $this->create_and_project();

		add_post_meta( $post_id, 'gatherpress_unit_test_key', 'first' );
		add_post_meta( $post_id, 'gatherpress_unit_test_key', 'second' );

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			'first',
			get_post_meta( $post_id, 'gatherpress_unit_test_key', true ),
			'Failed to assert that an unrelated meta key is left untouched in context.'
		);
		$this->assertSame(
			array( 'first', 'second' ),
			get_post_meta( $post_id, 'gatherpress_unit_test_key', false ),
			'Failed to assert that an unrelated multi-value meta key keeps every one of its values.'
		);
	}

	/**
	 * Coverage for the metadata filter's non-single return shape.
	 *
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_metadata_returns_an_array_when_single_is_false(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			array( self::SECOND_START ),
			get_post_meta( $post_id, 'gatherpress_datetime_start', false ),
			'Failed to assert that a non-single meta read returns the occurrence value in an array.'
		);
	}

	/**
	 * Coverage for the re-entrancy guard.
	 *
	 * An occurrence row's `timezone` column is nullable, and the filter falls
	 * back to the series' own `gatherpress_timezone` meta when it is empty.
	 * That fallback is a `get_post_meta()` call on the very key the filter is
	 * answering, so without the guard it re-enters itself without end. This
	 * test completes only because the guard stops the second entry.
	 *
	 * @covers ::metadata
	 * @covers ::occurrence_value
	 * @covers ::read_series_meta
	 *
	 * @return void
	 */
	public function test_metadata_does_not_recurse_when_the_occurrence_timezone_is_empty(): void {
		global $wpdb;

		$post_id = $this->create_and_project();
		$table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET timezone = NULL WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				$post_id,
				self::SECOND_ID
			)
		);

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			'America/New_York',
			get_post_meta( $post_id, 'gatherpress_timezone', true ),
			'Failed to assert that an empty occurrence timezone falls back to the series meta without recursing.'
		);
	}

	/**
	 * Coverage for `read_series_meta()` invoked directly.
	 *
	 * @covers ::read_series_meta
	 *
	 * @return void
	 */
	public function test_read_series_meta_returns_the_series_value(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			$this->reference_anchor_start,
			Utility::invoke_hidden_method(
				Context::get_instance(),
				'read_series_meta',
				array( $post_id, 'gatherpress_datetime_start' )
			),
			'Failed to assert that read_series_meta bypasses the occurrence substitution.'
		);
		$this->assertSame(
			self::SECOND_START,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that read_series_meta restores the substitution when it returns.'
		);
	}

	/**
	 * Coverage for `occurrence_value()` reading the occurrence row's column.
	 *
	 * @covers ::occurrence_value
	 *
	 * @return void
	 */
	public function test_occurrence_value_returns_the_row_column(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			self::SECOND_START_GMT,
			Utility::invoke_hidden_method(
				Context::get_instance(),
				'occurrence_value',
				array( Context::get_instance()->current(), 'gatherpress_datetime_start_gmt' )
			),
			'Failed to assert that occurrence_value returns the occurrence row column.'
		);
	}

	/**
	 * Coverage for `occurrence_value()` falling back to the series meta.
	 *
	 * @covers ::occurrence_value
	 *
	 * @return void
	 */
	public function test_occurrence_value_falls_back_to_series_meta_when_the_column_is_empty(): void {
		global $wpdb;

		$post_id = $this->create_and_project();
		$table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET timezone = %s WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				'',
				$post_id,
				self::SECOND_ID
			)
		);

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			'America/New_York',
			Utility::invoke_hidden_method(
				Context::get_instance(),
				'occurrence_value',
				array( Context::get_instance()->current(), 'gatherpress_timezone' )
			),
			'Failed to assert that occurrence_value falls back to the series meta for an empty column.'
		);
	}

	/**
	 * Coverage for `get_datetime()` in occurrence context.
	 *
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime_returns_the_occurrence_shape_in_context(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			array(
				'datetime_start'     => self::SECOND_START,
				'datetime_start_gmt' => self::SECOND_START_GMT,
				'datetime_end'       => '2026-09-15 20:00:00',
				'datetime_end_gmt'   => '2026-09-16 00:00:00',
				'timezone'           => 'America/New_York',
			),
			Context::get_instance()->get_datetime( $post_id ),
			'Failed to assert that get_datetime returns the occurrence in the Event::get_datetime shape.'
		);
	}

	/**
	 * Coverage for `get_datetime()` reading the record rather than the filter.
	 *
	 * `get_datetime()` is the explicit accessor the recurrence subsystem calls;
	 * the `get_post_metadata` filter is the compatibility path for unmodified
	 * blocks. With the filter unhooked, delegating to `Event::get_datetime()`
	 * would return the series anchor. That is what separates the two.
	 *
	 * @covers ::get_datetime
	 * @covers ::occurrence_value
	 *
	 * @return void
	 */
	public function test_get_datetime_reads_the_record_without_the_metadata_filter(): void {
		$post_id  = $this->create_and_project();
		$instance = Context::get_instance();

		$instance->set( $post_id, self::SECOND_ID );

		remove_filter( 'get_post_metadata', array( $instance, 'metadata' ), 10 );

		$datetime = $instance->get_datetime( $post_id );

		add_filter( 'get_post_metadata', array( $instance, 'metadata' ), 10, 4 );

		$this->assertSame(
			self::SECOND_START,
			$datetime['datetime_start'],
			'Failed to assert that get_datetime reads the occurrence record without the metadata filter.'
		);
	}

	/**
	 * Coverage for `get_datetime()` outside occurrence context.
	 *
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime_returns_the_series_outside_context(): void {
		$post_id = $this->create_and_project();

		$this->assertSame(
			$this->reference_anchor_start,
			Context::get_instance()->get_datetime( $post_id )['datetime_start'],
			'Failed to assert that get_datetime returns the series datetime outside occurrence context.'
		);
	}

	/**
	 * Coverage for `get_datetime()` asked about a post other than the context's.
	 *
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime_returns_the_series_for_another_post_in_context(): void {
		$post_id = $this->create_and_project();
		$other   = $this->create_recurring_event( self::WEEKLY_RULE );

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			$this->reference_anchor_start,
			Context::get_instance()->get_datetime( $other )['datetime_start'],
			'Failed to assert that get_datetime returns another post\'s own series datetime.'
		);
	}

	/**
	 * Coverage for `occurrence_url()` producing the canonical occurrence URL.
	 *
	 * Pinned as identical to `Rewrite::get_occurrence_url()` rather than merely
	 * "contains the identifier somewhere". Two builders emitting two different
	 * URL shapes for the same occurrence is exactly the drift this delegation
	 * exists to prevent, and a containment assertion would not notice it.
	 *
	 * @covers ::occurrence_url
	 *
	 * @return void
	 */
	public function test_occurrence_url_matches_the_canonical_occurrence_url(): void {
		$post_id = $this->create_and_project();
		$url     = Context::occurrence_url( $post_id, self::SECOND_ID );

		$this->assertSame(
			Rewrite::get_occurrence_url( $post_id, self::SECOND_ID ),
			$url,
			'Failed to assert that occurrence_url() produces the canonical occurrence URL.'
		);
		$this->assertStringContainsString(
			sprintf( '/%s/', self::SECOND_ID ),
			$url,
			'Failed to assert that the occurrence URL carries the recurrence identifier as a path segment.'
		);
	}

	/**
	 * Coverage for `occurrence_url()` without a recurrence identifier.
	 *
	 * @covers ::occurrence_url
	 *
	 * @return void
	 */
	public function test_occurrence_url_returns_the_plain_permalink_without_a_recurrence_id(): void {
		$post_id = $this->create_and_project();

		$this->assertSame(
			get_permalink( $post_id ),
			Context::occurrence_url( $post_id, '' ),
			'Failed to assert that an empty recurrence identifier yields the plain permalink.'
		);
	}

	/**
	 * Coverage for `occurrence_url()` when the post has no permalink.
	 *
	 * @covers ::occurrence_url
	 *
	 * @return void
	 */
	public function test_occurrence_url_returns_empty_string_for_a_missing_post(): void {
		$this->assertSame(
			'',
			Context::occurrence_url( 0, self::SECOND_ID ),
			'Failed to assert that a post with no permalink yields an empty occurrence URL.'
		);
	}

	/**
	 * Coverage for context being established on `wp`, from a real request.
	 *
	 * @covers ::sync
	 * @covers ::maybe_set_from_request
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_context_is_established_on_wp_for_an_occurrence_request(): void {
		$post_id = $this->create_and_project();

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		$current = Context::get_instance()->current();

		$this->assertIsArray( $current, 'Failed to assert that the wp action establishes occurrence context.' );
		$this->assertSame(
			self::SECOND_ID,
			$current['recurrence_id'],
			'Failed to assert that the established context matches the requested occurrence.'
		);
	}

	/**
	 * A canceled occurrence's rendered content carries a notice, not
	 * just a resolving URL. `Test_Rewrite::test_canceled_occurrence_url_resolves_rather_than_404()`
	 * already pins the "resolves, not 404" half; this pins the "and says so" half.
	 *
	 * @covers ::maybe_prepend_canceled_notice
	 *
	 * @return void
	 */
	public function test_canceled_occurrence_content_carries_a_notice(): void {
		$post_id = $this->create_and_project();

		$this->assertTrue(
			Occurrences::get_instance()->set_status( $post_id, self::SECOND_ID, Occurrences::STATUS_CANCELED ),
			'Fixture setup: set_status() should find the freshly projected row.'
		);

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		$this->assertTrue( have_posts(), 'Fixture assumption: the resolved request must have a post to loop over.' );

		the_post();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$content = apply_filters( 'the_content', get_the_content() );

		$this->assertStringContainsString(
			'This occurrence has been canceled.',
			$content,
			'A canceled occurrence must carry a cancellation notice in its rendered content.'
		);
	}

	/**
	 * A scheduled (non-canceled) occurrence's content carries no notice.
	 *
	 * @covers ::maybe_prepend_canceled_notice
	 *
	 * @return void
	 */
	public function test_scheduled_occurrence_content_carries_no_notice(): void {
		$post_id = $this->create_and_project();

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		the_post();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$content = apply_filters( 'the_content', get_the_content() );

		$this->assertStringNotContainsString(
			'This occurrence has been canceled.',
			$content,
			'A scheduled occurrence must not carry a cancellation notice.'
		);
	}

	/**
	 * Outside occurrence context entirely, content is untouched.
	 *
	 * @covers ::maybe_prepend_canceled_notice
	 *
	 * @return void
	 */
	public function test_content_untouched_outside_occurrence_context(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$this->go_to( (string) get_permalink( $post_id ) );

		the_post();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$content = apply_filters( 'the_content', get_the_content() );

		$this->assertStringNotContainsString( 'This occurrence has been canceled.', $content );
	}

	/**
	 * An inner loop rendering a different post's content while the main
	 * request's occurrence context is a canceled occurrence must not
	 * inherit that notice, matching how `metadata()` scopes substitution to
	 * the post the context belongs to, not to whatever post a loop reaches.
	 *
	 * @covers ::maybe_prepend_canceled_notice
	 *
	 * @return void
	 */
	public function test_notice_does_not_leak_into_an_inner_loop_over_a_different_post(): void {
		$post_id = $this->create_and_project();
		$sibling = $this->create_and_project();

		$this->assertTrue(
			Occurrences::get_instance()->set_status( $post_id, self::SECOND_ID, Occurrences::STATUS_CANCELED ),
			'Fixture setup: set_status() should find the freshly projected row.'
		);

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		$loop = new WP_Query(
			array(
				'p'         => $sibling,
				'post_type' => Event::POST_TYPE,
			)
		);
		$loop->the_post();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$content = apply_filters( 'the_content', get_the_content() );

		$this->assertStringNotContainsString(
			'This occurrence has been canceled.',
			$content,
			'An inner loop over a different post must not inherit the main request\'s cancellation notice.'
		);
	}

	/**
	 * Coverage for a bare series request composing with bare-series resolution.
	 *
	 * `Rewrite` resolves a bare series URL to the next upcoming occurrence and
	 * sets the query var during `parse_request`; `Context` then establishes that
	 * occurrence on `wp`.
	 *
	 * It asserts the *specific* occurrence rather than merely a non-null
	 * context. The fixture straddles "now", so the next upcoming occurrence is
	 * neither the anchor nor the first row: an implementation that resolved to
	 * either produces a different value and fails here, as does one that carried
	 * a stale identifier forward.
	 *
	 * @covers ::sync
	 * @covers ::maybe_set_from_request
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_bare_series_request_establishes_the_next_upcoming_occurrence(): void {
		list( $post_id, $past_id, $next_id ) = $this->create_series_straddling_now();

		$this->go_to( (string) get_permalink( $post_id ) );

		$current = Context::get_instance()->current();

		$this->assertIsArray(
			$current,
			'A bare series request must establish occurrence context.'
		);
		$this->assertSame(
			$next_id,
			$current['recurrence_id'],
			'A bare series request must resolve to the next upcoming occurrence.'
		);
		$this->assertNotSame(
			$past_id,
			$current['recurrence_id'],
			'A bare series request must not resolve to an occurrence that has already happened.'
		);
		$this->assertSame(
			$post_id,
			(int) $current['series_post_id'],
			'The established context must belong to the requested series.'
		);
	}

	/**
	 * Coverage for an occurrence query var on a request that resolves to no post.
	 *
	 * @covers ::sync
	 * @covers ::maybe_set_from_request
	 *
	 * @return void
	 */
	public function test_context_stays_unset_when_the_request_resolves_to_no_post(): void {
		$this->create_and_project();

		$this->go_to( add_query_arg( Context::QUERY_VAR, self::SECOND_ID, home_url( '/' ) ) );

		$this->assertNull(
			Context::get_instance()->current(),
			'Failed to assert that an occurrence query var on a non-singular request is ignored.'
		);
	}

	/**
	 * A request naming no occurrence queries no occurrence.
	 *
	 * Both guards in `maybe_set_from_request()` exist for this. Neither changes
	 * the resulting context, because `Occurrences::get()` would return null for
	 * an empty recurrence identifier or a post ID of zero anyway. The only thing
	 * that can tell a missing guard apart is the query it did not make.
	 *
	 * @covers ::sync
	 * @covers ::maybe_set_from_request
	 *
	 * @return void
	 */
	public function test_a_request_naming_no_occurrence_makes_no_occurrence_query(): void {
		global $wpdb;

		$post_id = $this->create_and_project();
		$table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$seen    = 0;

		add_filter(
			'query',
			static function ( string $query ) use ( &$seen, $table ): string {
				if ( str_contains( $query, $table ) ) {
					++$seen;
				}

				return $query;
			}
		);

		// A request that identifies no post at all cannot name an occurrence,
		// whatever the query string claims, and this pins the post ID guard. It
		// remains a hard zero even with bare-series resolution, which has no
		// post to resolve either.
		$this->go_to( add_query_arg( Context::QUERY_VAR, self::SECOND_ID, home_url( '/' ) ) );

		$this->assertSame(
			0,
			$seen,
			'Failed to assert that a request resolving to no post never queries the occurrence table.'
		);

		// A post that is not an event resolves to an ID but never to an
		// occurrence, and this pins the empty-identifier guard, which the home
		// page case above cannot reach because it bails on the post ID first.
		$plain_post = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$this->go_to( (string) get_permalink( $plain_post ) );

		$this->assertSame(
			0,
			$seen,
			'Failed to assert that a non-event permalink never queries the occurrence table.'
		);

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		$this->assertGreaterThan(
			0,
			$seen,
			'Failed to assert that a genuine occurrence request does query the occurrence table.'
		);
	}

	/**
	 * Coverage for `maybe_set_from_request()` invoked directly, on both return paths.
	 *
	 * @covers ::maybe_set_from_request
	 *
	 * @return void
	 */
	public function test_maybe_set_from_request_returns_early_without_a_resolvable_request(): void {
		$post_id  = $this->create_and_project();
		$instance = Context::get_instance();

		Utility::invoke_hidden_method( $instance, 'maybe_set_from_request', array( $post_id ) );

		$this->assertNull(
			$instance->current(),
			'Failed to assert that a request carrying no occurrence query var leaves the context unset.'
		);

		set_query_var( Context::QUERY_VAR, self::SECOND_ID );

		Utility::invoke_hidden_method( $instance, 'maybe_set_from_request', array( 0 ) );

		$this->assertNull(
			$instance->current(),
			'Failed to assert that a post ID of zero leaves the context unset.'
		);

		Utility::invoke_hidden_method( $instance, 'maybe_set_from_request', array( $post_id ) );

		$this->assertIsArray(
			$instance->current(),
			'Failed to assert that a resolvable request enters occurrence context.'
		);
	}

	/**
	 * Coverage for an inner loop over a sibling series sharing the recurrence identifier.
	 *
	 * `recurrence_id` is `Ymd\THis`, so two series projected from the same rule
	 * share it. For weekly rules that is the common case, not an exotic one.
	 * The sibling is therefore genuinely projected, and the assertion first
	 * proves the colliding row exists: a fixture whose sibling has no occurrence
	 * row makes `Occurrences::get()` return null whatever the scoping logic
	 * does, so the test would pass against a leak.
	 *
	 * What is asserted is the requirement that each post reads its own datetime,
	 * not the mechanism. Asserting that context is blanked mid-loop would forbid
	 * binding context to the requested post, which is the correct design.
	 *
	 * Driven through a real secondary `WP_Query` rather than a bare
	 * `do_action()`, because the whole point is that the loop's own wiring
	 * behaves. A hand-fired action proves nothing about `WP_Query::the_post()`.
	 *
	 * @covers ::sync
	 * @covers ::maybe_set_from_request
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_an_inner_loop_over_a_sibling_series_does_not_leak_context(): void {
		global $wpdb;

		$post_id = $this->create_and_project();
		$sibling = $this->create_and_project();

		update_post_meta( $sibling, 'gatherpress_datetime_start', self::SIBLING_START );

		$this->assertNotNull(
			Occurrences::get_instance()->get( $sibling, self::SECOND_ID ),
			'Fixture is inert: the sibling series must carry a genuinely colliding recurrence identifier.'
		);

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$seen  = 0;

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		add_filter(
			'query',
			static function ( string $query ) use ( &$seen, $table ): string {
				if ( str_contains( $query, $table ) ) {
					++$seen;
				}

				return $query;
			}
		);

		$loop = new WP_Query(
			array(
				'p'         => $sibling,
				'post_type' => Event::POST_TYPE,
			)
		);

		$loop->the_post();

		$this->assertSame(
			self::SIBLING_START,
			get_post_meta( $sibling, 'gatherpress_datetime_start', true ),
			'Failed to assert that a sibling series in an inner loop reads its own datetime.'
		);
		$this->assertSame(
			self::SECOND_START,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that the queried post keeps serving its occurrence during an inner loop.'
		);
		$this->assertSame(
			0,
			$seen,
			'Failed to assert that iterating a post issues no additional occurrence-table query.'
		);

		// An inner loop that forgets `wp_reset_postdata()` must not cost the
		// rest of the request its occurrence context.
		$this->assertSame(
			self::SECOND_START,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that occurrence context survives an unreset inner loop.'
		);

		wp_reset_postdata();

		$this->assertSame(
			self::SECOND_START,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that occurrence context survives wp_reset_postdata().'
		);
	}

	/**
	 * Coverage on the read path: no recurring events, no context read.
	 *
	 * `Occurrences::get()` is a raw uncached `$wpdb->get_row()`, so without a
	 * guard any crawler appending the occurrence query string to an ordinary
	 * event permalink hits the occurrence table on a site that has never
	 * authored a recurring event. The single read this request is allowed is
	 * `Rewrite::parse_request()`'s deliberately unguarded primary-key
	 * resolution, which is what keeps a stale occurrence URL 404ing after
	 * the flag flips off; this class's own guard must add nothing to it.
	 *
	 * @covers ::sync
	 * @covers ::maybe_set_from_request
	 *
	 * @return void
	 */
	public function test_no_occurrence_query_on_a_site_without_recurring_events(): void {
		global $wpdb;

		$post_id = $this->create_and_project();

		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Fixture is inert: the site must report no recurring events.'
		);

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$seen  = 0;

		add_filter(
			'query',
			static function ( string $query ) use ( &$seen, $table ): string {
				if ( str_contains( $query, $table ) ) {
					++$seen;
				}

				return $query;
			}
		);

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		$this->assertSame(
			1,
			$seen,
			'Failed to assert the request pays only the rewrite resolver\'s single primary-key read.'
		);
		$this->assertNull(
			Context::get_instance()->current(),
			'Failed to assert that no occurrence context is entered on a site with no recurring events.'
		);
	}

	/**
	 * Coverage for the `Event` datetime cache not colliding across series.
	 *
	 * Identity is the composite `(series_post_id, recurrence_id)`. A
	 * cache keyed on the recurrence identifier alone lets an `Event` for one
	 * series cache its series datetime under an identifier that belongs to
	 * another series' occurrence, and serve it back once context moves to its
	 * own occurrence at that identifier.
	 *
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_event_cache_is_not_shared_between_series_sharing_a_recurrence_id(): void {
		$post_id = $this->create_and_project();
		$sibling = $this->create_and_project();

		update_post_meta( $sibling, 'gatherpress_datetime_start_gmt', self::SIBLING_START_GMT );

		$sibling_event = new Event( $sibling );

		// Context belongs to the first series, so the sibling must read its own
		// series value, and must not cache it under the shared identifier.
		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			self::SIBLING_START_GMT,
			$sibling_event->get_datetime()['datetime_start_gmt'],
			'Failed to assert that a sibling series reads its own datetime under another series\' context.'
		);

		Context::get_instance()->set( $sibling, self::SECOND_ID );

		$this->assertSame(
			self::SECOND_START_GMT,
			$sibling_event->get_datetime()['datetime_start_gmt'],
			'Failed to assert that the sibling reports its own occurrence rather than a cross-series cache hit.'
		);
	}

	/**
	 * Coverage for `cache_key()` on both arms of the composite-key decision.
	 *
	 * @covers ::cache_key
	 *
	 * @return void
	 */
	public function test_cache_key_is_scoped_to_the_context_s_own_series(): void {
		$post_id  = $this->create_and_project();
		$sibling  = $this->create_and_project();
		$instance = Context::get_instance();

		$this->assertSame(
			'',
			$instance->cache_key( $post_id ),
			'Failed to assert that the cache key is the series slot outside occurrence context.'
		);

		$instance->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			self::SECOND_ID,
			$instance->cache_key( $post_id ),
			'Failed to assert that the cache key is the occurrence identifier for the context\'s own post.'
		);
		$this->assertSame(
			'',
			$instance->cache_key( $sibling ),
			'Failed to assert that another series falls back to the series slot despite a shared identifier.'
		);
	}

	/**
	 * Coverage for the re-entrancy flag surviving a throwing meta filter.
	 *
	 * A stuck flag silently disables occurrence substitution for the rest of the
	 * request, which surfaces as a wrong date with no error anywhere.
	 *
	 * @covers ::read_series_meta
	 *
	 * @return void
	 */
	public function test_reading_flag_is_restored_when_a_meta_filter_throws(): void {
		global $wpdb;

		$post_id = $this->create_and_project();
		$table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET timezone = NULL WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				$post_id,
				self::SECOND_ID
			)
		);

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$thrower = static function () {
			throw new RuntimeException( 'Third-party meta filter exploded.' );
		};

		add_filter( 'get_post_metadata', $thrower, 11 );

		$threw = false;

		try {
			get_post_meta( $post_id, 'gatherpress_timezone', true );
		} catch ( RuntimeException $exception ) {
			$threw = true;
		}

		remove_filter( 'get_post_metadata', $thrower, 11 );

		$this->assertTrue( $threw, 'Fixture is inert: the third-party filter did not throw.' );
		$this->assertSame(
			self::SECOND_START,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that occurrence substitution still works after a meta filter threw.'
		);
	}

	/**
	 * Coverage for a meta write whose new value equals the occurrence's value.
	 *
	 * `update_metadata()` short-circuits when the stored value already equals
	 * the new one, and it discovers the stored value through
	 * `get_metadata_raw()`, which is this same filter. Without a guard the
	 * comparison sees the occurrence's
	 * value, and the write to the series is silently dropped.
	 *
	 * @covers ::metadata
	 * @covers ::note_meta_write
	 *
	 * @return void
	 */
	public function test_a_meta_write_matching_the_occurrence_value_still_updates_the_series(): void {
		$post_id = $this->create_and_project();

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		update_post_meta( $post_id, 'gatherpress_datetime_start', self::SECOND_START );

		Context::get_instance()->clear();

		$this->assertSame(
			self::SECOND_START,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that a write matching the occurrence value still reached the series meta.'
		);
	}

	/**
	 * Coverage for a meta write that falls through to `add_metadata()`.
	 *
	 * `update_metadata()` reaches `add_metadata()` whenever the key has no row
	 * yet, and `add_metadata()` never calls `get_metadata_raw()`, because its
	 * `$unique` check is a direct `$wpdb->get_var()`. Hooking
	 * `add_post_metadata` therefore armed a note nothing consumed, and the next
	 * occurrence-scoped read of that key consumed it instead and returned the
	 * series' newly written value.
	 *
	 * The write itself is asserted too, because the cure must not be "stop
	 * writing".
	 *
	 * @covers ::metadata
	 * @covers ::note_meta_write
	 *
	 * @return void
	 */
	public function test_updating_an_absent_series_key_leaves_the_next_occurrence_read_intact(): void {
		$post_id = $this->create_and_project();

		delete_post_meta( $post_id, 'gatherpress_datetime_start' );

		// Asserted before context is entered, because in context this very read
		// is the one the filter answers from the occurrence record.
		$this->assertSame(
			'',
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Fixture is inert: the series key must be absent, or the update never falls through to'
				. ' add_metadata() at all.'
		);

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		update_post_meta( $post_id, 'gatherpress_datetime_start', '2040-01-01 00:00:00' );

		$in_context = get_post_meta( $post_id, 'gatherpress_datetime_start', true );

		Context::get_instance()->clear();

		$stored = get_post_meta( $post_id, 'gatherpress_datetime_start', true );

		$this->assertSame(
			'2040-01-01 00:00:00',
			$stored,
			'Failed to assert the write to the absent series key landed.'
		);
		$this->assertSame(
			self::SECOND_START,
			$in_context,
			'Failed to assert the occurrence read after an add-fallthrough write still serves the'
				. ' occurrence rather than the series value just written.'
		);
	}

	/**
	 * Coverage for the two ways a meta write skips the comparison read.
	 *
	 * A note taken for a read that never happens is not inert: the next
	 * ordinary read of the same key consumes it and gets the series' value on
	 * an occurrence page. Core skips the read when the caller named a previous
	 * value, and again when an earlier-priority filter short-circuits the
	 * write, so `note_meta_write()` takes no note in either case.
	 *
	 * @covers ::metadata
	 * @covers ::note_meta_write
	 *
	 * @return void
	 */
	public function test_a_meta_write_that_makes_no_comparison_read_leaves_no_note(): void {
		$post_id = $this->create_and_project();
		$anchor  = $this->reference_anchor_start;

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		// Naming a previous value sends core down the `$prev_value` branch,
		// which never reads the stored value.
		update_post_meta( $post_id, 'gatherpress_datetime_start', '2041-01-01 00:00:00', $anchor );

		$after_prev_value = get_post_meta( $post_id, 'gatherpress_datetime_start', true );

		$short_circuit = static function () {
			return true;
		};

		add_filter( 'update_post_metadata', $short_circuit, 10, 5 );

		update_post_meta( $post_id, 'gatherpress_datetime_start', '2042-01-01 00:00:00' );

		remove_filter( 'update_post_metadata', $short_circuit, 10 );

		$after_short_circuit = get_post_meta( $post_id, 'gatherpress_datetime_start', true );

		Context::get_instance()->clear();

		$this->assertSame(
			'2041-01-01 00:00:00',
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Fixture is inert: the write naming a previous value did not land, so nothing was skipped.'
		);
		$this->assertSame(
			self::SECOND_START,
			$after_prev_value,
			'Failed to assert a write naming a previous value leaves no note for the next read.'
		);
		$this->assertSame(
			self::SECOND_START,
			$after_short_circuit,
			'Failed to assert a short-circuited write leaves no note for the next read.'
		);
	}

	/**
	 * Coverage for the visitor-facing half of cancellation, on all four arms.
	 *
	 * `Rewrite::parse_request()` lets a canceled occurrence's URL resolve
	 * instead of 404ing; this is what tells the visitor once it does. The
	 * notice must appear on the canceled occurrence's own content and on
	 * nothing else. It must not appear outside occurrence context, for a
	 * scheduled occurrence, or for another post rendering full content on the
	 * same response.
	 *
	 * @covers ::maybe_prepend_canceled_notice
	 *
	 * @return void
	 */
	public function test_canceled_occurrence_content_carries_a_notice_and_nothing_else_does(): void {
		$notice  = 'gatherpress-occurrence-canceled-notice';
		$post_id = $this->create_and_project();
		$other   = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$outside = Context::get_instance()->maybe_prepend_canceled_notice( 'Body.' );

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$scheduled = apply_filters( 'the_content', 'Body.' );

		$this->assertTrue(
			Occurrences::get_instance()->set_status( $post_id, self::SECOND_ID, Occurrences::STATUS_CANCELED ),
			'Fixture is inert: the occurrence row was not canceled.'
		);

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$canceled = apply_filters( 'the_content', 'Body.' );

		$loop = new WP_Query(
			array(
				'p'         => $other,
				'post_type' => Event::POST_TYPE,
			)
		);

		$loop->the_post();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$elsewhere = apply_filters( 'the_content', 'Body.' );

		wp_reset_postdata();

		$this->assertStringNotContainsString(
			$notice,
			$outside,
			'Failed to assert no notice is added outside occurrence context.'
		);
		$this->assertStringNotContainsString(
			$notice,
			$scheduled,
			'Failed to assert no notice is added for a scheduled occurrence.'
		);
		$this->assertStringContainsString(
			$notice,
			$canceled,
			'Failed to assert a canceled occurrence\'s own content carries the notice.'
		);
		$this->assertStringContainsString(
			'Body.',
			$canceled,
			'Failed to assert the notice is prepended rather than replacing the content.'
		);
		$this->assertLessThan(
			strpos( $canceled, 'Body.' ),
			strpos( $canceled, $notice ),
			'Failed to assert the notice precedes the content it was prepended to.'
		);
		$this->assertStringNotContainsString(
			$notice,
			$elsewhere,
			'Failed to assert another post rendering content on the same response gets no notice.'
		);
	}

	/**
	 * Coverage for `occurrence_url()`'s documented empty-identifier contract
	 * inside a loop. The docblock promises the series permalink when no
	 * occurrence is named, but reading `get_permalink()` mid-iteration
	 * returns the stamped row's occurrence URL, because the permalink filter
	 * is active for the current row. The fixture proves the two answers
	 * differ before asserting which one the method returns.
	 *
	 * @covers ::occurrence_url
	 *
	 * @return void
	 */
	public function test_occurrence_url_returns_the_series_permalink_in_a_loop(): void {
		list( $post_id, ) = $this->create_series_straddling_now();

		$series_permalink = (string) get_permalink( $post_id );

		$loop = new WP_Query(
			array(
				'post_type'                    => Event::POST_TYPE,
				'post__in'                     => array( $post_id ),
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				'posts_per_page'               => 20,
				'orderby'                      => 'datetime',
				'order'                        => 'ASC',
			)
		);

		$this->assertGreaterThan( 0, $loop->post_count, 'Fixture is inert: the loop returned no rows.' );

		$loop->the_post();

		$this->assertNotSame(
			$series_permalink,
			(string) get_permalink( $post_id ),
			'Fixture is inert: the loop row must filter the permalink so the two answers differ.'
		);

		$actual = Context::occurrence_url( $post_id, '' );

		wp_reset_postdata();

		$this->assertSame(
			$series_permalink,
			$actual,
			'Failed to assert occurrence_url() returns the series permalink when no occurrence is named,'
				. ' even while a stamped loop row is current.'
		);
	}

	/**
	 * Coverage for the scheduled-same-series arm of the cancellation notice.
	 *
	 * The existing cancellation test checks a *different post* in the inner
	 * loop. This one checks a different *occurrence of the same post*: on a
	 * canceled occurrence's own page, an occurrence-expanded Query Loop over
	 * the same series shares the outer post ID on every row, and each
	 * scheduled row must render without the outer request's cancellation
	 * notice. The canceled page's own content must still carry it, so a fix
	 * cannot pass by suppressing the notice everywhere.
	 *
	 * @covers ::maybe_prepend_canceled_notice
	 *
	 * @return void
	 */
	public function test_scheduled_same_series_loop_rows_carry_no_canceled_notice(): void {
		$notice = 'gatherpress-occurrence-canceled-notice';

		list( $post_id, $past_id ) = $this->create_series_straddling_now();

		$this->assertTrue(
			Occurrences::get_instance()->set_status( $post_id, $past_id, Occurrences::STATUS_CANCELED ),
			'Fixture is inert: the requested occurrence was not canceled.'
		);

		$this->go_to( Context::occurrence_url( $post_id, $past_id ) );

		$this->assertSame(
			$past_id,
			Context::get_instance()->current()['recurrence_id'] ?? null,
			'Fixture is inert: the request did not establish the canceled occurrence as context.'
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$own = apply_filters( 'the_content', 'Body.' );

		$loop = new WP_Query(
			array(
				'post_type'                    => Event::POST_TYPE,
				'post__in'                     => array( $post_id ),
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				'posts_per_page'               => 20,
				'orderby'                      => 'datetime',
				'order'                        => 'ASC',
			)
		);

		$this->assertSame(
			2,
			$loop->post_count,
			'Fixture is inert: the straddling series must leave two scheduled same-series rows to loop over.'
		);

		$row_contents = array();

		while ( $loop->have_posts() ) {
			$loop->the_post();

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
			$row_contents[] = apply_filters( 'the_content', 'Body.' );
		}

		wp_reset_postdata();

		$this->assertStringContainsString(
			$notice,
			$own,
			'Failed to assert the canceled occurrence\'s own page content still carries the notice.'
		);

		foreach ( $row_contents as $offset => $content ) {
			$this->assertStringNotContainsString(
				$notice,
				$content,
				'Failed to assert scheduled same-series loop row ' . $offset . ' carries no cancellation notice.'
			);
		}
	}

	/**
	 * Coverage for a same-series Query Loop rendered on a real occurrence page.
	 *
	 * The end-to-end shape of `resolve()`'s precedence: a real request to one
	 * occurrence's URL, and a secondary loop over the *same post* rendered
	 * inside that response. Each row is a different occurrence of the requested
	 * series, and each must render its own date and its own link rather than
	 * the outer request's.
	 *
	 * The requested occurrence is the series' past one, which the upcoming loop
	 * cannot contain, so no row can pass by coinciding with the request. The
	 * whole per-row vector is asserted rather than a single row.
	 *
	 * Expected values are read back out of the occurrence table rather than
	 * recomputed here, so the assertion compares the render against the record
	 * that is the source of truth.
	 *
	 * @covers ::resolve
	 * @covers ::metadata
	 * @covers ::permalink
	 * @covers ::sync
	 *
	 * @return void
	 */
	public function test_same_series_query_loop_on_an_occurrence_page_renders_each_rows_own_date_and_url(): void {
		list( $post_id, $past_id ) = $this->create_series_straddling_now();

		$rows     = Occurrences::get_instance()->select_for_series(
			array( $post_id ),
			array( 'status' => Occurrences::STATUS_SCHEDULED )
		);
		$now      = current_time( 'mysql', true );
		$upcoming = array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $now ): bool {
					return $row['datetime_start_gmt'] >= $now;
				}
			)
		);

		$this->assertCount(
			2,
			$upcoming,
			'Fixture is inert: the straddling series must leave two upcoming occurrences to loop over.'
		);

		$this->go_to( Context::occurrence_url( $post_id, $past_id ) );

		$this->assertSame(
			$past_id,
			Context::get_instance()->current()['recurrence_id'],
			'Fixture is inert: the request did not establish the past occurrence as the page context.'
		);

		foreach ( $upcoming as $row ) {
			$this->assertNotSame(
				$past_id,
				$row['recurrence_id'],
				'Fixture is inert: no loop row may coincide with the requested occurrence.'
			);
		}

		$loop = new WP_Query(
			array(
				'post_type'                    => Event::POST_TYPE,
				'post__in'                     => array( $post_id ),
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				'posts_per_page'               => 20,
				'orderby'                      => 'datetime',
				'order'                        => 'ASC',
			)
		);

		$rendered = array();

		while ( $loop->have_posts() ) {
			$loop->the_post();

			$block = '<!-- wp:gatherpress/event-date {"displayType":"start",'
				. '"startDateFormat":"Y-m-d H:i","isLink":true} /-->';

			$rendered[] = do_blocks( $block );
		}

		wp_reset_postdata();

		$this->assertCount(
			2,
			$rendered,
			'Failed to assert the same-series loop rendered one row per upcoming occurrence.'
		);

		foreach ( $upcoming as $offset => $row ) {
			$this->assertStringContainsString(
				substr( (string) $row['datetime_start'], 0, 16 ),
				$rendered[ $offset ],
				'Failed to assert same-series row ' . $offset . ' rendered its own stamped date.'
			);
			$this->assertStringContainsString(
				sprintf(
					'href="%s"',
					esc_url( Rewrite::get_occurrence_url( $post_id, (string) $row['recurrence_id'] ) )
				),
				$rendered[ $offset ],
				'Failed to assert same-series row ' . $offset . ' linked to its own occurrence URL.'
			);
		}

		$outer = sprintf(
			'href="%s"',
			esc_url( Rewrite::get_occurrence_url( $post_id, $past_id ) )
		);

		$this->assertStringNotContainsString(
			$outer,
			implode( '', $rendered ),
			'Failed to assert no same-series row was overwritten with the outer request\'s occurrence URL.'
		);
	}

	/**
	 * The unmodified event-date block renders the occurrence's date.
	 *
	 * The recurrence feature modifies no block files. The block is rendered
	 * through `do_blocks()` on a real occurrence request, so what this asserts
	 * is the production read path end to end.
	 *
	 * @covers ::metadata
	 * @covers ::sync
	 *
	 * @return void
	 */
	public function test_event_date_block_renders_occurrence_date_unmodified(): void {
		$post_id = $this->create_and_project();

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		$output = do_blocks(
			'<!-- wp:gatherpress/event-date {"displayType":"start","startDateFormat":"Y-m-d H:i"} /-->'
		);

		$this->assertStringContainsString(
			'2026-09-15 18:00',
			$output,
			'Failed to assert that the unmodified event-date block renders the occurrence date.'
		);
		$this->assertStringNotContainsString(
			'2026-09-03 18:00',
			$output,
			'Failed to assert that the event-date block stopped rendering the series anchor date.'
		);
	}

	/**
	 * Coverage on the add-to-calendar block.
	 *
	 * The block's hrefs are on-site endpoint URLs; the datetime lives in what
	 * those endpoints serve. So the block is rendered for real, and the two
	 * payloads its links resolve to are asserted to carry the occurrence's
	 * datetime rather than the series anchor's.
	 *
	 * @covers ::metadata
	 * @covers ::sync
	 *
	 * @return void
	 */
	public function test_add_to_calendar_links_carry_occurrence_datetime(): void {
		$post_id = $this->create_and_project();

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		$output = do_blocks(
			'<!-- wp:gatherpress/add-to-calendar -->'
			. '<div class="wp-block-gatherpress-add-to-calendar">'
			. '<a href="#gatherpress-google-calendar">Google Calendar</a>'
			. '<a href="#gatherpress-ical-calendar">iCal</a>'
			. '</div>'
			. '<!-- /wp:gatherpress/add-to-calendar -->'
		);

		$this->assertStringNotContainsString(
			'#gatherpress-google-calendar',
			$output,
			'Failed to assert that the add-to-calendar block resolved its placeholder hrefs.'
		);

		$calendar = new Calendar( $post_id );

		$this->assertStringContainsString(
			'20260915T220000Z',
			$calendar->get_google_destination_url(),
			'Failed to assert that the Google Calendar payload carries the occurrence datetime.'
		);
		$this->assertStringContainsString(
			'DTSTART;TZID=America/New_York:20260915T180000',
			$calendar->get_ical_event_string(),
			'Failed to assert that the iCal payload carries the occurrence datetime.'
		);
		// The bare datetime token, deliberately without a property name or a
		// line ending around it: the anchor datetime must not appear anywhere
		// in the occurrence's component, whatever property would carry it.
		$this->assertStringNotContainsString(
			'20260903T180000',
			$calendar->get_ical_event_string(),
			'Failed to assert that the iCal payload stopped carrying the series anchor datetime.'
		);
	}

	/**
	 * Coverage for teardown leaving no stale occurrence value behind.
	 *
	 * Teardown is still a real requirement once a bare series URL resolves to
	 * an occurrence, but "context is null" is not how it shows: a later bare-series request legitimately
	 * re-establishes context. So the test distinguishes *re-derived from this
	 * request* from *survived from the previous one*.
	 *
	 * The fixture straddles "now" and the first request explicitly names the
	 * occurrence that is **not** the next upcoming one. A stale value therefore
	 * reads differently from a correctly re-derived one, which a null check
	 * could never have told apart. A second leg then leaves for a non-event URL,
	 * where the correct answer really is no context at all.
	 *
	 * @covers ::sync
	 * @covers ::clear
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_no_stale_occurrence_value_leaks_after_teardown(): void {
		list( $post_id, $past_id, $next_id ) = $this->create_series_straddling_now();

		// Read outside occurrence context, before the first leg. `get_permalink()`
		// answers with the *current* occurrence's URL once one is in play, so
		// reading it between the two legs would send the second request back to
		// the first occurrence and the "bare series request" leg would silently
		// stop being one.
		$series_url = (string) get_permalink( $post_id );

		$this->go_to( Context::occurrence_url( $post_id, $past_id ) );

		$this->assertSame(
			$past_id,
			Context::get_instance()->current()['recurrence_id'],
			'Failed to assert that the occurrence request served the occurrence it named.'
		);

		$past_start = (string) get_post_meta( $post_id, 'gatherpress_datetime_start', true );

		$this->go_to( $series_url );

		$this->assertSame(
			$next_id,
			Context::get_instance()->current()['recurrence_id'],
			'The later request must reflect its own resolution, not the previous request\'s occurrence.'
		);
		$this->assertNotSame(
			$past_start,
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'Failed to assert that the previous request\'s occurrence datetime did not survive into this one.'
		);

		$output = do_blocks(
			'<!-- wp:gatherpress/event-date {"displayType":"start","startDateFormat":"Y-m-d H:i"} /-->'
		);

		$this->assertStringNotContainsString(
			substr( $past_start, 0, 16 ),
			$output,
			'Failed to assert that the event-date block stopped rendering the previous request\'s occurrence.'
		);

		// Leaving for a URL that names no event at all must drop context
		// outright, because there is nothing to re-derive.
		$this->go_to( home_url( '/' ) );

		$this->assertNull(
			Context::get_instance()->current(),
			'Failed to assert that occurrence context is gone once the request names no event.'
		);
	}

	/**
	 * An occurrence's time of day comes from its record.
	 *
	 * The row's time of day is moved away from the anchor's, which the current
	 * rule set cannot produce. The test exists so the read path can never
	 * hard-code "apply the anchor's time to the occurrence's date". Doing so
	 * would foreclose multiple-times-per-day rules later.
	 *
	 * @covers ::metadata
	 * @covers ::get_datetime
	 * @covers ::occurrence_value
	 *
	 * @return void
	 */
	public function test_occurrence_time_of_day_comes_from_the_record_not_the_anchor(): void {
		global $wpdb;

		$post_id = $this->create_and_project();
		$table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET datetime_start = %s, datetime_start_gmt = %s,'
					. ' datetime_end = %s, datetime_end_gmt = %s'
					. ' WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				'2026-09-15 09:30:00',
				'2026-09-15 13:30:00',
				'2026-09-15 11:30:00',
				'2026-09-15 15:30:00',
				$post_id,
				self::SECOND_ID
			)
		);

		$this->go_to( Context::occurrence_url( $post_id, self::SECOND_ID ) );

		$this->assertSame(
			'2026-09-15 09:30:00',
			Context::get_instance()->get_datetime( $post_id )['datetime_start'],
			'Failed to assert that the occurrence record\'s time of day wins over the anchor\'s.'
		);

		$output = do_blocks(
			'<!-- wp:gatherpress/event-date {"displayType":"start","startDateFormat":"Y-m-d H:i"} /-->'
		);

		$this->assertStringContainsString(
			'2026-09-15 09:30',
			$output,
			'Failed to assert that the block rendered the record\'s time of day.'
		);
		$this->assertStringNotContainsString(
			'2026-09-15 18:00',
			$output,
			'Failed to assert that the anchor\'s time of day was not applied to the occurrence date.'
		);
	}

	/**
	 * Coverage for the pre-warmed `Event` instance trap.
	 *
	 * Nothing stops a plugin or theme from constructing an `Event` and reading
	 * its datetime before `wp` fires. With a single-slot cache that instance
	 * would return the series datetime for the rest of its life. The cache is
	 * keyed by occurrence, so the pre-warmed instance resolves correctly.
	 *
	 * @covers ::metadata
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_event_instance_constructed_before_context_still_reports_occurrence_datetime(): void {
		$post_id = $this->create_and_project();
		$event   = new Event( $post_id );

		$this->assertSame(
			self::ANCHOR_START_GMT,
			$event->get_datetime()['datetime_start_gmt'],
			'Failed to assert that the pre-warmed instance reports the series datetime before context.'
		);

		Context::get_instance()->set( $post_id, self::SECOND_ID );

		$this->assertSame(
			self::SECOND_START_GMT,
			$event->get_datetime()['datetime_start_gmt'],
			'Failed to assert that a pre-warmed Event instance reports the occurrence datetime in context.'
		);

		Context::get_instance()->clear();

		$this->assertSame(
			self::ANCHOR_START_GMT,
			$event->get_datetime()['datetime_start_gmt'],
			'Failed to assert that the pre-warmed instance returns to the series datetime after clear.'
		);
	}

	/**
	 * `set_for_series()` enters context for an occurrence a request names.
	 *
	 * The REST counterpart to `sync()`. A REST request never fires `wp`, because
	 * core's `rest_api_loaded()` runs on `parse_request` and ends in `die()`.
	 * Without this, nothing on that surface could ever be occurrence-scoped.
	 *
	 * @covers ::set_for_series
	 * @covers ::resolve_in_series
	 *
	 * @return void
	 */
	public function test_set_for_series_enters_the_named_occurrence(): void {
		$post_id = $this->create_and_project();

		$this->assertTrue(
			Context::get_instance()->set_for_series( $post_id, self::SECOND_ID ),
			'Failed to assert a real occurrence is entered.'
		);
		$this->assertSame(
			self::SECOND_ID,
			Context::get_instance()->current()['recurrence_id'],
			'Failed to assert the entered context is the occurrence that was named.'
		);
	}

	/**
	 * Resolving the same occurrence twice costs one query, not two.
	 *
	 * A REST dispatch resolves the same `(post_id, recurrence_id)` pair twice:
	 * once in the permission callback and once on entry. The two stay separate
	 * deliberately: the permission callback resolves only to authorize the
	 * occurrence's owner, and entering context there would leave it standing
	 * on a request that then 403s with no teardown filter registered. The memo
	 * removes the duplicated query without collapsing that separation.
	 *
	 * A miss is memoized too, so a fabricated identifier also costs one query
	 * per request rather than one per lookup. That is asserted, because
	 * remembering only hits is the easy half to get wrong.
	 *
	 * @covers ::resolve_in_series
	 * @covers ::flush_resolved
	 *
	 * @return void
	 */
	public function test_resolve_in_series_memoizes_within_the_request(): void {
		global $wpdb;

		$post_id = $this->create_and_project();
		$table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		/**
		 * Count the occurrence-table queries a callable performs.
		 *
		 * @param callable $run The work to measure.
		 *
		 * @return int Occurrence-table queries issued.
		 */
		$queries_during = static function ( callable $run ) use ( $wpdb, $table ): int {
			$before = count( $wpdb->queries );

			$run();

			return count(
				array_filter(
					array_slice( $wpdb->queries, $before ),
					static function ( array $query ) use ( $table ): bool {
						return str_contains( $query[0], $table );
					}
				)
			);
		};

		Context::flush_resolved();

		$first = $queries_during(
			static function () use ( $post_id ): void {
				Context::resolve_in_series( $post_id, self::SECOND_ID );
			}
		);

		$second = $queries_during(
			static function () use ( $post_id ): void {
				Context::resolve_in_series( $post_id, self::SECOND_ID );
			}
		);

		$this->assertSame(
			1,
			$first,
			'Failed to assert the first resolution reaches the occurrence table exactly once.'
		);
		$this->assertSame(
			0,
			$second,
			'Failed to assert a repeat resolution of the same pair is served from the memo.'
		);

		// A miss is remembered as cheaply as a hit.
		Context::flush_resolved();

		$this->assertSame(
			1,
			$queries_during(
				static function () use ( $post_id ): void {
					Context::resolve_in_series( $post_id, '20991231T235959' );
					Context::resolve_in_series( $post_id, '20991231T235959' );
				}
			),
			'Failed to assert an unresolvable identifier is queried once and then remembered.'
		);

		// And flushing really discards it, or the memo would outlive storage
		// changes inside a single test process.
		Context::flush_resolved();

		$this->assertSame(
			1,
			$queries_during(
				static function () use ( $post_id ): void {
					Context::resolve_in_series( $post_id, self::SECOND_ID );
				}
			),
			'Failed to assert flush_resolved() discards the memo.'
		);
	}

	/**
	 * `set_for_series()` refuses anything that does not resolve to a row.
	 *
	 * @covers ::set_for_series
	 * @covers ::resolve_in_series
	 *
	 * @return void
	 */
	public function test_set_for_series_refuses_what_does_not_resolve(): void {
		$post_id = $this->create_and_project();

		$this->assertFalse(
			Context::get_instance()->set_for_series( $post_id, '20991231T235959' ),
			'Failed to assert an unknown identifier does not enter context.'
		);
		$this->assertFalse(
			Context::get_instance()->set_for_series( $post_id, '' ),
			'Failed to assert an empty identifier does not enter context.'
		);
		$this->assertFalse(
			Context::get_instance()->set_for_series( 0, self::SECOND_ID ),
			'Failed to assert an unusable post ID does not enter context.'
		);
		$this->assertNull(
			Context::get_instance()->current(),
			'Failed to assert a refused entry leaves no context behind.'
		);
	}

	/**
	 * A site with no recurring events resolves nothing and queries nothing.
	 *
	 * @covers ::resolve_in_series
	 *
	 * @return void
	 */
	public function test_resolve_in_series_short_circuits_off_a_recurring_site(): void {
		global $wpdb;

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		Query::refresh_has_recurring_events();

		$occurrences_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$before            = count( $wpdb->queries );
		$resolved          = Context::resolve_in_series( $post_id, self::SECOND_ID );
		$since             = array_slice( $wpdb->queries, $before );

		$this->assertNull( $resolved, 'Failed to assert a non-recurring site resolves no occurrence.' );
		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$since,
					static function ( array $query ) use ( $occurrences_table ): bool {
						return str_contains( $query[0], $occurrences_table );
					}
				)
			),
			'Failed to assert a non-recurring site never queries the occurrence table to resolve one.'
		);
	}
	/**
	 * Outside occurrence context there is no occurrence to report, and the
	 * frozen stub is always outside it.
	 *
	 * @covers ::current
	 *
	 * @return void
	 */
	public function test_current_returns_null_outside_occurrence_context(): void {
		$this->assertNull(
			Context::get_instance()->current(),
			'Failed to assert that current returns null when no occurrence is set.'
		);
	}

	/**
	 * The query var is the permalink's occurrence segment, so its value is a
	 * public contract shared with rewrite rules and with block render paths.
	 *
	 * @return void
	 */
	public function test_query_var_is_the_prefixed_occurrence_name(): void {
		$this->assertSame(
			'gatherpress_occurrence',
			Context::QUERY_VAR,
			'Failed to assert that the occurrence query var is gatherpress_occurrence.'
		);
	}

	/**
	 * `set()` and `clear()` are the entry and exit of occurrence context. The
	 * stub stores nothing, so entering context must not make `current()` start
	 * answering, and leaving it must be safe to call whether or not context was
	 * ever entered.
	 *
	 * @covers ::set
	 * @covers ::clear
	 * @covers ::current
	 *
	 * @return void
	 */
	public function test_set_and_clear_leave_current_unanswered(): void {
		$post_id  = $this->factory->post->create();
		$instance = Context::get_instance();

		$instance->set( $post_id, '20260101T090000' );

		$this->assertNull(
			$instance->current(),
			'Failed to assert that current still returns null after set.'
		);

		$instance->clear();

		$this->assertNull(
			$instance->current(),
			'Failed to assert that current returns null after clear.'
		);

		// Clearing a context that was never entered is a no-op, not an error.
		$instance->clear();

		$this->assertNull(
			$instance->current(),
			'Failed to assert that a second clear leaves current returning null.'
		);
	}
}
