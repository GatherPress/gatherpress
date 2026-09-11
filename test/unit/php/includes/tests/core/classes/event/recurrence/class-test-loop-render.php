<?php
/**
 * Class handles unit tests for the occurrence read path inside a Query Loop.
 *
 * Asserting that `Recurrence\Query` returns the right rows says nothing about
 * whether a block renders the right date from those rows. That gap is exactly
 * the shape of the regression this file covers: the query layer expands a
 * series correctly, `attach_occurrences()` stamps identity onto every result
 * object, and nothing in the render path reads the stamp, so every row of a
 * series shows the anchor's date and the bare series permalink.
 *
 * Every test here drives a real `WP_Query` loop through `the_post()` and
 * asserts the *rendered output* of a block, never an intermediate value.
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
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Query;

/**
 * Class Test_Loop_Render.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Context
 */
class Test_Loop_Render extends Base {

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

		Context::get_instance()->clear();
	}

	/**
	 * Leave no occurrence context or postdata behind for the next test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Context::get_instance()->clear();

		wp_reset_postdata();

		parent::tearDown();
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
	 * @param DateTimeImmutable $start    Event start, in `$timezone`.
	 * @param DateTimeImmutable $end      Event end, in `$timezone`.
	 * @param string            $timezone Series timezone name.
	 *
	 * @return int The created post ID.
	 */
	protected function create_event_at(
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		string $timezone = 'UTC'
	): int {
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
					'timezone'      => $timezone,
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
	 * @param DateTimeImmutable $start    Anchor start, in `$timezone`.
	 * @param DateTimeImmutable $end      Anchor end, in `$timezone`.
	 * @param array             $rule     Recurrence rule values.
	 * @param string            $timezone Series timezone name.
	 *
	 * @return int The created post ID.
	 */
	protected function create_series_at(
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		array $rule = self::DAILY_RULE,
		string $timezone = 'UTC'
	): int {
		$post_id = $this->create_event_at( $start, $end, $timezone );

		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( $rule ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return $post_id;
	}

	/**
	 * Run an occurrence-aware upcoming-events query.
	 *
	 * Drives a real `WP_Query` through the production `pre_get_posts`,
	 * `posts_clauses` and `the_posts` wiring rather than calling any filter
	 * callback directly.
	 *
	 * @since 0.36.0
	 *
	 * @param array $args Additional query arguments.
	 *
	 * @return WP_Query The executed query.
	 */
	protected function run_upcoming_query( array $args = array() ): WP_Query {
		return new WP_Query(
			array_merge(
				array(
					'post_type'                    => Event::POST_TYPE,
					Event_Query::EVENT_QUERY_PARAM => 'upcoming',
					'posts_per_page'               => 20,
					'orderby'                      => 'datetime',
					'order'                        => 'ASC',
				),
				$args
			)
		);
	}

	/**
	 * Render the event-date block the way `core/post-template` renders it.
	 *
	 * No `postId` attribute is supplied, because a Query Loop does not supply
	 * one either: `Blocks\Setup::get_post_id()` falls through to
	 * `get_the_ID()`, which is whatever `the_post()` most recently set up.
	 * Passing an ID here would route around the very wiring under test.
	 *
	 * @since 0.36.0
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string The rendered block markup.
	 */
	protected function render_event_date( array $attrs = array() ): string {
		return render_block(
			array(
				'blockName'    => Event_Date_Block::BLOCK_NAME,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Walk a query's loop and render the event-date block once per iteration.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Query $query Executed query.
	 * @param array    $attrs Block attributes.
	 *
	 * @return array<int, array{id:int, html:string}> One entry per loop iteration.
	 */
	protected function render_loop( WP_Query $query, array $attrs = array() ): array {
		$rendered = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$rendered[] = array(
				'id'   => (int) get_the_ID(),
				'html' => $this->render_event_date( $attrs ),
			);
		}

		wp_reset_postdata();

		return $rendered;
	}

	/**
	 * Render the RSVP count block the way `core/post-template` renders it.
	 *
	 * No `postId` attribute, for the same reason `render_event_date()` supplies
	 * none: a Query Loop supplies none either, so passing one would route around
	 * the wiring under test.
	 *
	 * @since 0.36.0
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string The rendered block markup.
	 */
	protected function render_rsvp_count( array $attrs = array() ): string {
		return render_block(
			array(
				'blockName'    => 'gatherpress/rsvp-count',
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
		// Both quoting styles, because the five emitters do not agree on one:
		// the render templates go through `wp_interactivity_data_wp_context()`,
		// which single-quotes, and the three block classes go through
		// `WP_HTML_Tag_Processor::set_attribute()`, which double-quotes.
		if ( ! preg_match( '/data-wp-context=([\'"])(.*?)\1/s', $html, $matches ) ) {
			return array();
		}

		$decoded = json_decode( html_entity_decode( $matches[2] ), true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Render one block the way `core/post-template` renders it.
	 *
	 * No `postId` attribute, for the same reason `render_event_date()` supplies
	 * none: a Query Loop supplies none either.
	 *
	 * @since 0.36.0
	 *
	 * @param string $block_name Block type name.
	 * @param array  $attrs      Block attributes.
	 * @param string $inner_html Saved markup the block's render filters transform.
	 *
	 * @return string The rendered block markup.
	 */
	protected function render_named_block(
		string $block_name,
		array $attrs = array(),
		string $inner_html = ''
	): string {
		return render_block(
			array(
				'blockName'    => $block_name,
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => $inner_html,
				'innerContent' => array( $inner_html ),
			)
		);
	}

	/**
	 * Render every block that publishes an interactivity context, once.
	 *
	 * All five emitters, because four of them were guarded by nothing: reverting
	 * any of `Blocks\Rsvp`, `Blocks\Rsvp_Form`, `Blocks\Rsvp_Response` or the
	 * online-event-link render template to `array( 'postId' => $post_id )` left
	 * the whole PHP suite green, since only `gatherpress/rsvp-count` was ever
	 * rendered here. A partial revert is the dangerous shape. The RSVP button
	 * keys on the bare post while the counts beside it key on the occurrence, so
	 * the button collapses across the series and the counts stay right.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array> The decoded context each emitter published, keyed by block.
	 */
	protected function emitter_block_contexts(): array {
		$serialized = wp_json_encode(
			array(
				'no_status' => '<!-- wp:paragraph --><p>RSVP</p><!-- /wp:paragraph -->',
				'attending' => '<!-- wp:paragraph --><p>Attending</p><!-- /wp:paragraph -->',
			)
		);

		return array(
			'rsvp'              => $this->block_context_from(
				$this->render_named_block(
					'gatherpress/rsvp',
					array( 'serializedInnerBlocks' => $serialized ),
					'<div class="wp-block-gatherpress-rsvp"></div>'
				)
			),
			'rsvp-form'         => $this->block_context_from(
				$this->render_named_block(
					'gatherpress/rsvp-form',
					array(),
					'<div class="wp-block-gatherpress-rsvp-form"></div>'
				)
			),
			'rsvp-response'     => $this->block_context_from(
				$this->render_named_block(
					'gatherpress/rsvp-response',
					array(),
					'<div class="wp-block-gatherpress-rsvp-response"></div>'
				)
			),
			'rsvp-count'        => $this->block_context_from( $this->render_rsvp_count() ),
			'online-event-link' => $this->block_context_from(
				$this->render_named_block( 'gatherpress/online-event-link' )
			),
		);
	}

	/**
	 * Walk a query's loop and render every context emitter once per iteration.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Query $query Executed query.
	 *
	 * @return array<string, array<int, array>> Per-emitter lists of decoded contexts, in loop order.
	 */
	protected function loop_emitter_contexts( WP_Query $query ): array {
		$contexts = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			foreach ( $this->emitter_block_contexts() as $emitter => $context ) {
				$contexts[ $emitter ][] = $context;
			}
		}

		wp_reset_postdata();

		return $contexts;
	}

	/**
	 * Walk a query's loop and render the RSVP count block once per iteration.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Query $query Executed query.
	 *
	 * @return array<int, array> One decoded block context per loop iteration.
	 */
	protected function loop_block_contexts( WP_Query $query ): array {
		$contexts = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$contexts[] = $this->block_context_from( $this->render_rsvp_count() );
		}

		wp_reset_postdata();

		return $contexts;
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
	 * Coverage for the render path's first half: the event date block renders
	 * the occurrence's datetime, not the series anchor's.
	 *
	 * The series anchor started an hour ago, so the loop contains its four
	 * upcoming occurrences. Every rendered row must carry its own day. A render
	 * path that reads the anchor instead collapses all four to one distinct
	 * string.
	 *
	 * @covers ::metadata
	 * @covers ::occurrence_value
	 *
	 * @return void
	 */
	public function test_query_loop_renders_a_distinct_date_per_occurrence(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );

		$rendered = $this->render_loop( $this->run_upcoming_query() );

		$this->assertCount(
			4,
			$rendered,
			'Failed to assert the loop expanded the series to its four upcoming occurrences.'
		);

		$html = wp_list_pluck( $rendered, 'html' );

		$this->assertCount(
			4,
			array_unique( $html ),
			'Failed to assert each occurrence row rendered a distinct date. Every row showing the same'
				. ' string means the query expanded and the render path read the anchor.'
		);

		foreach ( array( 1, 2, 3, 4 ) as $offset => $index ) {
			$this->assertStringContainsString(
				$anchor->modify( sprintf( '+%d days', $index ) )->format( 'F j, Y' ),
				$html[ $offset ],
				'Failed to assert row ' . $offset . ' rendered its own occurrence date.'
			);
			$this->assertStringNotContainsString(
				$anchor->format( 'F j, Y' ),
				$html[ $offset ],
				'Failed to assert row ' . $offset . ' did not render the series anchor\'s date.'
			);
		}
	}

	/**
	 * Inside a loop, an occurrence's time of day is read
	 * from the occurrence record, never computed by applying the anchor's time
	 * to the occurrence's date.
	 *
	 * The discriminating fixture is a direct row update, and it has to be:
	 * the expander holds the wall-clock time constant
	 * across every occurrence, including across a DST transition, so for a
	 * rule-generated series "the record's time" and "the anchor's time applied
	 * to the record's date" are the *same* local time and no assertion on the
	 * rendered wall clock can separate them. Rewriting one row's own datetime
	 * columns is what makes the two answers differ, and
	 * it is the same technique
	 * `Test_Context::test_occurrence_time_of_day_comes_from_the_record_not_the_anchor`
	 * uses on the singular path, and this is its loop counterpart.
	 *
	 * @covers ::metadata
	 * @covers ::occurrence_value
	 * @covers ::loop_occurrence
	 *
	 * @return void
	 */
	public function test_a_loop_row_renders_the_records_time_of_day_not_the_anchors(): void {
		global $wpdb;

		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$moved     = $this->occurrence_id( $anchor, 2 );
		$start     = $anchor->modify( '+2 days' )->modify( '-3 hours' );
		$table     = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET datetime_start = %s, datetime_start_gmt = %s,'
					. ' datetime_end = %s, datetime_end_gmt = %s'
					. ' WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				$start->format( 'Y-m-d H:i:s' ),
				$start->format( 'Y-m-d H:i:s' ),
				$start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
				$start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
				$series_id,
				$moved
			)
		);

		$rendered = $this->render_loop(
			$this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) ),
			array(
				'displayType'     => 'start',
				'startDateFormat' => 'Y-m-d H:i',
			)
		);
		$html     = wp_list_pluck( $rendered, 'html' );

		$this->assertCount(
			4,
			$html,
			'Failed to assert the loop still expanded the series to its four upcoming occurrences.'
		);

		$moved_row = $html[1];

		$this->assertStringContainsString(
			$start->format( 'Y-m-d H:i' ),
			$moved_row,
			'Failed to assert the moved occurrence rendered its own record\'s time of day.'
		);
		$this->assertStringNotContainsString(
			$anchor->modify( '+2 days' )->format( 'Y-m-d H:i' ),
			$moved_row,
			'Failed to assert the anchor\'s time of day was not applied to the occurrence\'s date. That is'
				. ' precisely the violation, and it renders a plausible-looking wrong time.'
		);
	}

	/**
	 * The permalink of a loop row is the
	 * occurrence URL, not the bare series URL.
	 *
	 * Asserted through the block's own `isLink` markup rather than by calling
	 * `get_permalink()` in the test, so the assertion covers what a visitor
	 * would actually click.
	 *
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_query_loop_renders_a_distinct_permalink_per_occurrence(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );

		$rendered = $this->render_loop( $this->run_upcoming_query(), array( 'isLink' => true ) );
		$hrefs    = array();

		foreach ( $rendered as $row ) {
			preg_match( '/href="([^"]+)"/', $row['html'], $matches );

			// Decoded because the markup entity-escapes the URL: a
			// plain-permalink occurrence URL carries two query variables, and
			// its ampersand renders as an entity. The decoded value is the
			// URL a browser actually navigates to.
			$hrefs[] = html_entity_decode( $matches[1] ?? '', ENT_QUOTES );
		}

		$this->assertCount(
			4,
			array_unique( $hrefs ),
			'Failed to assert each occurrence row linked to its own occurrence URL. Four identical bare'
				. ' series permalinks is what a visitor sees when the render path reads the series.'
		);

		$expected = array();

		foreach ( array( 1, 2, 3, 4 ) as $index ) {
			$expected[] = Rewrite::get_occurrence_url( $series_id, $this->occurrence_id( $anchor, $index ) );
		}

		$this->assertSame(
			$expected,
			$hrefs,
			'Failed to assert every row linked to the occurrence URL shape for its own occurrence.'
		);
	}

	/**
	 * Coverage for the non-recurring arm: an ordinary event in the same loop
	 * renders its own date and its own bare permalink.
	 *
	 * This is the branch where the stamp is `null`, and it is the one that
	 * breaks if a fix reaches for list position instead of the object.
	 *
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_a_plain_event_row_renders_its_own_date_and_bare_permalink(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );

		$plain_id = $this->create_event_at( $now->modify( '+400 hours' ), $now->modify( '+401 hours' ) );

		$rendered = $this->render_loop( $this->run_upcoming_query(), array( 'isLink' => true ) );
		$last     = end( $rendered );

		$this->assertSame(
			$plain_id,
			$last['id'],
			'Failed to assert the plain event sorted last, after every occurrence.'
		);
		$this->assertStringContainsString(
			sprintf( 'href="%s"', get_permalink( $plain_id ) ),
			$last['html'],
			'Failed to assert a non-recurring row keeps its bare permalink.'
		);
		$this->assertStringContainsString(
			$now->modify( '+400 hours' )->format( 'F j, Y' ),
			$last['html'],
			'Failed to assert a non-recurring row renders its own anchor date.'
		);
	}

	/**
	 * Coverage for re-entrancy: an inner loop over a different post does not
	 * inherit the outer iteration's occurrence identity, and the outer loop
	 * does not inherit the inner post's.
	 *
	 * The inner query is a plain event query over an unrelated event, run and
	 * reset from inside every iteration of the outer occurrence loop, exactly
	 * as `render_block_core_post_template()` runs a nested Query Loop. Its row
	 * must render its own date and its own bare permalink on every pass, and
	 * every outer row must still render its own occurrence afterwards.
	 *
	 * Where the outer row's own blocks are rendered matters and is not this
	 * class's choice to make: `wp_reset_postdata()` restores `$GLOBALS['post']`
	 * from the *main* query, not from the enclosing secondary one, so blocks
	 * placed after a nested loop already read the main query's post in stock
	 * WordPress. This test therefore renders each outer row before its nested
	 * loop, which is the arrangement core itself produces.
	 *
	 * The limitation that arrangement leaves behind is recorded here because of
	 * *how it presents*, which is the part that will cost someone a day. An
	 * outer row rendered **after** a nested loop reads the wrong occurrence.
	 * Measured, rendering the outer row after the inner loop instead of before:
	 *
	 *     before_rows = ["2026-08-18 ...","2026-08-19 ...","2026-08-20 ...","2026-08-21 ..."]
	 *     after_rows  = ["2026-08-21 ...","2026-08-21 ...","2026-08-21 ...","2026-08-21 ..."]
	 *
	 * In stock WordPress this class of bug is *visible*: the post identity
	 * changes, so the title and the link visibly jump to some other event and
	 * the reporter says "the wrong post is showing." Here the post ID is
	 * unchanged, and only which occurrence of it is in play is wrong, so the
	 * title, the link and the venue all still look right and **only the date is
	 * wrong**. Nothing in the output announces the failure. There is nothing to
	 * fix from this class's side: `wp_reset_postdata()` fires no action and
	 * restores from `$wp_query`, so there is no hook to re-establish the outer
	 * iteration's occurrence from and nothing to restore it out of.
	 *
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_inner_loop_over_a_different_post_does_not_inherit_the_occurrence(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$other_id  = $this->create_event_at( $now->modify( '+400 hours' ), $now->modify( '+401 hours' ) );

		$outer      = $this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) );
		$outer_html = array();
		$inner_html = array();
		$inner_args = array(
			'post_type' => Event::POST_TYPE,
			'post__in'  => array( $other_id ),
		);
		$inner_date = $now->modify( '+400 hours' )->format( 'F j, Y' );
		$inner_link = sprintf( 'href="%s"', get_permalink( $other_id ) );

		while ( $outer->have_posts() ) {
			$outer->the_post();

			$outer_html[] = $this->render_event_date( array( 'isLink' => true ) );

			$inner = new WP_Query( $inner_args );

			while ( $inner->have_posts() ) {
				$inner->the_post();

				$inner_html[] = $this->render_event_date( array( 'isLink' => true ) );
			}

			wp_reset_postdata();
		}

		wp_reset_postdata();

		$this->assertCount(
			4,
			array_unique( $outer_html ),
			'Failed to assert every outer occurrence row still rendered its own date and permalink while a'
				. ' nested loop ran inside each iteration.'
		);

		foreach ( array( 1, 2, 3, 4 ) as $offset => $index ) {
			$this->assertStringContainsString(
				$anchor->modify( sprintf( '+%d days', $index ) )->format( 'F j, Y' ),
				$outer_html[ $offset ],
				'Failed to assert outer row ' . $offset . ' kept its own occurrence date.'
			);
		}

		$this->assertCount(
			4,
			$inner_html,
			'Failed to assert the nested loop ran once per outer iteration.'
		);
		$this->assertSame(
			array( $inner_html[0] ),
			array_values( array_unique( $inner_html ) ),
			'Failed to assert the nested loop rendered the same unrelated event every time rather than'
				. ' drifting with the outer occurrence.'
		);
		$this->assertStringContainsString(
			$inner_date,
			$inner_html[0],
			'Failed to assert the inner loop\'s post rendered its own date rather than inheriting the'
				. ' outer occurrence\'s.'
		);
		$this->assertStringContainsString(
			$inner_link,
			$inner_html[0],
			'Failed to assert the inner loop\'s post kept its own bare permalink.'
		);
	}

	/**
	 * Coverage for the no-recurring-events guarantee on every entry point this
	 * read path adds.
	 *
	 * Drives the real entry points, meaning a full `WP_Query` loop plus a block
	 * render plus a `get_permalink()` call, on a site whose
	 * `gatherpress_has_recurring_events` option is `'0'`, and asserts no query
	 * touches the occurrence table and no option is written. Naming the test
	 * after the loop rather than after the callbacks is deliberate: a "performs
	 * no writes" test that drives the body of the work and never the entry
	 * point passes while the entry point still queries the table.
	 *
	 * @covers ::metadata
	 *
	 * @return void
	 */
	public function test_loop_render_touches_no_occurrence_table_without_recurring_events(): void {
		global $wpdb;

		$now      = $this->now();
		$plain_id = $this->create_event_at( $now->modify( '+2 hours' ), $now->modify( '+3 hours' ) );

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$table   = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$options = get_option( 'gatherpress_version', '' );

		$wpdb->queries = array();
		$saved         = $wpdb->save_queries;

		$wpdb->save_queries = true;

		$rendered = $this->render_loop( $this->run_upcoming_query(), array( 'isLink' => true ) );

		get_permalink( $plain_id );

		$captured = $wpdb->queries;

		$wpdb->save_queries = $saved;
		$wpdb->queries      = array();

		$occurrence_queries = array_values(
			array_filter(
				array_column( $captured, 0 ),
				static function ( string $sql ) use ( $table ): bool {
					return str_contains( $sql, $table );
				}
			)
		);
		$option_writes      = array_values(
			array_filter(
				array_column( $captured, 0 ),
				static function ( string $sql ) use ( $wpdb ): bool {
					return str_contains( $sql, $wpdb->options )
						&& ( str_contains( $sql, 'INSERT' ) || str_contains( $sql, 'UPDATE' ) );
				}
			)
		);

		$this->assertSame(
			array(),
			$occurrence_queries,
			'Failed to assert a full loop render on a site with no recurring events never reads the'
				. ' occurrence table.'
		);
		$this->assertSame(
			array(),
			$option_writes,
			'Failed to assert a full loop render on a site with no recurring events writes no option.'
		);
		$this->assertSame(
			$options,
			get_option( 'gatherpress_version', '' ),
			'Failed to assert no option value changed.'
		);
		$this->assertCount(
			1,
			$rendered,
			'Failed to assert the ordinary event still rendered.'
		);
		$this->assertStringContainsString(
			sprintf( 'href="%s"', get_permalink( $plain_id ) ),
			$rendered[0]['html'],
			'Failed to assert the ordinary event kept its bare permalink.'
		);
	}

	/**
	 * Coverage for `loop_occurrence()`'s no-current-post return path.
	 *
	 * Invoked directly, because xdebug does not trace a same-class helper's
	 * body reliably when it is only ever reached from the filter callbacks
	 * above.
	 *
	 * @covers ::loop_occurrence
	 *
	 * @return void
	 */
	public function test_loop_occurrence_returns_null_with_no_current_post(): void {
		$now      = $this->now();
		$plain_id = $this->create_event_at( $now->modify( '+2 hours' ), $now->modify( '+3 hours' ) );

		$this->assertNull(
			get_post(),
			'Failed to assert the fixture leaves no current post set up.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method( Context::get_instance(), 'loop_occurrence', array( $plain_id ) ),
			'Failed to assert loop_occurrence returns null when no post is set up.'
		);
	}

	/**
	 * Coverage for `loop_occurrence()`'s wrong-post return path.
	 *
	 * The isolation rule: a stamped occurrence is served only for the post it
	 * was stamped onto, never for whatever else a consumer happens to ask
	 * about mid-iteration.
	 *
	 * @covers ::loop_occurrence
	 *
	 * @return void
	 */
	public function test_loop_occurrence_returns_null_for_another_post(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$other_id  = $this->create_event_at( $now->modify( '+400 hours' ), $now->modify( '+401 hours' ) );
		$query     = $this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) );

		$query->the_post();

		$mine   = Utility::invoke_hidden_method( Context::get_instance(), 'loop_occurrence', array( $series_id ) );
		$theirs = Utility::invoke_hidden_method( Context::get_instance(), 'loop_occurrence', array( $other_id ) );

		wp_reset_postdata();

		$this->assertIsArray(
			$mine,
			'Failed to assert loop_occurrence resolves the post it was stamped onto.'
		);
		$this->assertSame(
			$this->occurrence_id( $anchor, 1 ),
			$mine['recurrence_id'],
			'Failed to assert the resolved row carries the iteration\'s own recurrence ID.'
		);
		$this->assertSame(
			Occurrences::STATUS_SCHEDULED,
			$mine['status'],
			'Failed to assert only scheduled occurrences reach a loop.'
		);
		$this->assertNull(
			$theirs,
			'Failed to assert loop_occurrence refuses to answer for a different post.'
		);
	}

	/**
	 * Coverage for `loop_occurrence()`'s non-recurring return path.
	 *
	 * A plain event's row is stamped with a null datetime payload, which is
	 * the branch that must not be reached for by list position.
	 *
	 * @covers ::loop_occurrence
	 *
	 * @return void
	 */
	public function test_loop_occurrence_returns_null_for_a_non_recurring_row(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );

		$plain_id = $this->create_event_at( $now->modify( '+400 hours' ), $now->modify( '+401 hours' ) );
		$query    = $this->run_upcoming_query( array( 'post__in' => array( $plain_id ) ) );

		$query->the_post();

		$resolved = Utility::invoke_hidden_method( Context::get_instance(), 'loop_occurrence', array( $plain_id ) );
		$stamped  = get_post()->{Query::RESULT_DATETIME_PROPERTY};

		wp_reset_postdata();

		$this->assertNull(
			$stamped,
			'Failed to assert a non-recurring row carries a null datetime stamp.'
		);
		$this->assertNull(
			$resolved,
			'Failed to assert loop_occurrence returns null for a non-recurring row.'
		);
	}

	/**
	 * Coverage for `resolve()`'s precedence, both arms.
	 *
	 * The row's own stamp wins while a loop iteration is set up, and the
	 * request's occurrence applies where there is no stamp. Both are asserted
	 * here on purpose. The precedence the other way round gives every
	 * same-series Query Loop row the outer page's occurrence; the
	 * obvious correction, "the stamp is authoritative, drop the request arm",
	 * breaks every singular occurrence page instead, because a singular
	 * request's own post carries a null stamp. Only a test that pins both
	 * directions rejects both defects.
	 *
	 * The fixture makes the two answers differ: the request names occurrence 3
	 * while the loop's first row is occurrence 1.
	 *
	 * @covers ::resolve
	 * @covers ::loop_occurrence
	 *
	 * @return void
	 */
	public function test_resolve_prefers_the_rows_own_stamp_over_the_requests_occurrence(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$requested = $this->occurrence_id( $anchor, 3 );
		$first_row = $this->occurrence_id( $anchor, 1 );

		Context::get_instance()->set( $series_id, $requested );

		$this->assertNotSame(
			$requested,
			$first_row,
			'Fixture is inert: the request and the loop\'s first row must name different occurrences.'
		);

		// Read before any `the_post()`, which is the singular occurrence page's
		// own shape: nothing is stamped, so the request is the only identity
		// there is. Reading after the loop instead would prove nothing, because
		// `wp_reset_postdata()` restores from the *main* query, which this test
		// has no post in, so the last stamped row would still be current.
		$unstamped = Utility::invoke_hidden_method( Context::get_instance(), 'resolve', array( $series_id ) );

		$query = $this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) );

		$query->the_post();

		$in_loop = Utility::invoke_hidden_method( Context::get_instance(), 'resolve', array( $series_id ) );

		wp_reset_postdata();

		$this->assertSame(
			$first_row,
			$in_loop['recurrence_id'],
			'Failed to assert a stamped loop row resolves to its own occurrence rather than the request\'s.'
		);
		$this->assertSame(
			$requested,
			$unstamped['recurrence_id'],
			'Failed to assert an unstamped read still resolves to the request\'s occurrence. This is what'
				. ' every singular occurrence page depends on.'
		);
	}

	/**
	 * Coverage for a same-series Query Loop rendered inside an occurrence page.
	 *
	 * The acceptance shape for the precedence above, asserted through rendered
	 * output rather than through `resolve()`. Occurrence context is the one the
	 * outer page named; every row of the loop belongs to that same post and
	 * must still render its own date and its own link.
	 *
	 * The requested occurrence is deliberately one the loop does not contain,
	 * so no row can pass by coinciding with it, and the whole per-row vector is
	 * asserted rather than a single row.
	 *
	 * @covers ::resolve
	 * @covers ::metadata
	 * @covers ::permalink
	 *
	 * @return void
	 */
	public function test_same_series_loop_rows_keep_their_own_date_and_link_inside_an_occurrence(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$requested = $this->occurrence_id( $anchor, 0 );

		Context::get_instance()->set( $series_id, $requested );

		$this->assertSame(
			$requested,
			Context::get_instance()->current()['recurrence_id'],
			'Fixture is inert: the outer occurrence context was not established.'
		);

		$rendered = $this->render_loop(
			$this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) ),
			array( 'isLink' => true )
		);

		$hrefs    = array();
		$expected = array();
		$dates    = array();

		foreach ( $rendered as $row ) {
			preg_match( '/href="([^"]+)"/', $row['html'], $matches );

			// Decoded for the same reason as the distinct-permalink test: the
			// entity-escaped markup is compared as the URL a browser
			// navigates to.
			$hrefs[] = html_entity_decode( $matches[1] ?? '', ENT_QUOTES );
		}

		foreach ( array( 1, 2, 3, 4 ) as $index ) {
			$expected[] = Rewrite::get_occurrence_url( $series_id, $this->occurrence_id( $anchor, $index ) );
			$dates[]    = $anchor->modify( sprintf( '+%d days', $index ) )->format( 'F j, Y' );
		}

		$this->assertCount( 4, $rendered, 'Failed to assert the loop expanded to the four upcoming rows.' );
		$this->assertSame(
			$expected,
			$hrefs,
			'Failed to assert every same-series row linked to its own occurrence URL rather than to the'
				. ' outer request\'s.'
		);

		foreach ( $dates as $offset => $date ) {
			$this->assertStringContainsString(
				$date,
				$rendered[ $offset ]['html'],
				'Failed to assert same-series row ' . $offset . ' rendered its own occurrence date.'
			);
		}

		$this->assertStringNotContainsString(
			$anchor->format( 'F j, Y' ),
			implode( '', wp_list_pluck( $rendered, 'html' ) ),
			'Failed to assert no same-series row rendered the outer request\'s occurrence date.'
		);
		$this->assertSame(
			$requested,
			Context::get_instance()->current()['recurrence_id'],
			'Failed to assert the outer request\'s occurrence context survived the loop.'
		);
	}

	/**
	 * Coverage for a loop rendered on a singular occurrence page.
	 *
	 * The obvious thing to build for this feature is "other dates in this
	 * series" on an occurrence's own page, and that was the case the precedence
	 * in `Context::resolve()` and `Rsvp_Occurrence::current_occurrence()` got
	 * wrong. Both consulted the *request's* occurrence first and returned it
	 * for any post in the requested series, so every correctly stamped row
	 * resolved to the requested date. Measured before the fix, with the request
	 * on the anchor:
	 *
	 *     row 6:  stamp=20260912T180000  rsvp_resolves=20260823T180000
	 *     row 7:  stamp=20260913T180000  rsvp_resolves=20260823T180000
	 *
	 * The datetime collapsed with it, so the page showed one date four times.
	 *
	 * The request context is established by `go_to()` rather than by hand, so
	 * the `wp` action that binds it in production is the thing under test.
	 * The requested occurrence is the series anchor, which is in
	 * the past and therefore *not* among the loop's upcoming rows, so the
	 * right answer and the collapsed answer differ on every row rather than on
	 * three of four.
	 *
	 * @covers ::resolve
	 * @covers ::loop_occurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::current_occurrence
	 *
	 * @return void
	 */
	public function test_a_loop_on_a_singular_occurrence_page_keeps_each_rows_own_identity(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$requested = $this->occurrence_id( $anchor, 0 );

		$this->go_to(
			add_query_arg( Context::QUERY_VAR, $requested, (string) get_permalink( $series_id ) )
		);

		$this->assertSame(
			$requested,
			Rsvp_Occurrence::current_recurrence_id( $series_id ),
			'Failed to assert the request established the anchor occurrence as the page\'s own. Without that'
				. ' the fixture proves nothing, because there would be no request occurrence to collapse to.'
		);

		$query      = $this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) );
		$identities = array();
		$html       = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$identities[] = (string) Rsvp_Occurrence::current_recurrence_id( $series_id );
			$html[]       = $this->render_event_date(
				array(
					'displayType'     => 'start',
					'startDateFormat' => 'Y-m-d H:i',
				)
			);
		}

		wp_reset_postdata();

		$expected = array();

		foreach ( array( 1, 2, 3, 4 ) as $index ) {
			$expected[] = $this->occurrence_id( $anchor, $index );
		}

		$this->assertSame(
			$expected,
			$identities,
			'Failed to assert every row of a loop on an occurrence page resolved its own occurrence for RSVP'
				. ' scoping. Four copies of the requested identifier is the defect.'
		);

		foreach ( array( 1, 2, 3, 4 ) as $offset => $index ) {
			$this->assertStringContainsString(
				$anchor->modify( sprintf( '+%d days', $index ) )->format( 'Y-m-d H:i' ),
				$html[ $offset ],
				'Failed to assert row ' . $offset . ' rendered its own occurrence datetime.'
			);
			$this->assertStringNotContainsString(
				$anchor->format( 'Y-m-d H:i' ),
				$html[ $offset ],
				'Failed to assert row ' . $offset . ' did not render the requested occurrence\'s datetime.'
			);
		}
	}

	/**
	 * Coverage for `permalink()`'s two pass-through return paths.
	 *
	 * The first is a value core hands the filter that is not a post at all;
	 * the second is `series_permalink()`'s suppression, which is what stops
	 * `Rewrite::get_occurrence_url()` from composing an occurrence segment on
	 * top of a URL that already carries one.
	 *
	 * @covers ::permalink
	 * @covers ::series_permalink
	 *
	 * @return void
	 */
	public function test_permalink_passes_through_without_a_post_and_while_suppressed(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$bare      = get_permalink( $series_id );
		$query     = $this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) );

		$query->the_post();

		$without_post = Context::get_instance()->permalink( 'https://example.test/untouched/', null );
		$suppressed   = Context::get_instance()->series_permalink( $series_id );
		$filtered     = get_permalink( $series_id );

		wp_reset_postdata();

		$this->assertSame(
			'https://example.test/untouched/',
			$without_post,
			'Failed to assert permalink() leaves a non-post value untouched.'
		);
		$this->assertSame(
			$bare,
			$suppressed,
			'Failed to assert series_permalink() reads the bare series permalink during a loop iteration.'
		);
		$this->assertSame(
			Rewrite::get_occurrence_url( $series_id, $this->occurrence_id( $anchor, 1 ) ),
			$filtered,
			'Failed to assert the same read is filtered once suppression is restored.'
		);
	}

	/**
	 * Coverage for the isolation rule through a rendered block rather than a
	 * direct invoke.
	 *
	 * `test_loop_occurrence_returns_null_for_another_post()` above pins the same
	 * rule, but it reaches `loop_occurrence()` through
	 * `Utility::invoke_hidden_method()`. Dropping
	 * `|| (int) $post->ID !== $post_id` from that method failed exactly that one
	 * test out of the file, and no rendered-output test at all, because
	 * `test_inner_loop_over_a_different_post_does_not_inherit_the_occurrence()`
	 * moves the *global* post to the inner post before rendering, so the two IDs
	 * agree and the mismatch arm is never exercised.
	 *
	 * A block carrying an explicit `postId` for a different post is the shape
	 * that does exercise it: `Blocks\Setup::get_post_id()` honors the attribute,
	 * so the block reads another post's meta while the global post is still the
	 * occurrence row. Without the ID comparison the other post inherits this
	 * iteration's occurrence, both its date and its permalink.
	 *
	 * @covers ::metadata
	 * @covers ::permalink
	 * @covers ::loop_occurrence
	 *
	 * @return void
	 */
	public function test_a_block_pinned_to_another_post_mid_iteration_renders_that_posts_own_values(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$other_id  = $this->create_event_at( $now->modify( '+400 hours' ), $now->modify( '+401 hours' ) );

		$other_link = sprintf( 'href="%s"', get_permalink( $other_id ) );
		$other_date = $now->modify( '+400 hours' )->format( 'F j, Y' );

		$query      = $this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) );
		$own_html   = array();
		$other_html = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$own_html[]   = $this->render_event_date( array( 'isLink' => true ) );
			$other_html[] = $this->render_event_date(
				array(
					'isLink' => true,
					'postId' => $other_id,
				)
			);
		}

		wp_reset_postdata();

		$this->assertCount(
			4,
			array_unique( $own_html ),
			'Failed to assert every outer occurrence row still rendered its own date and permalink.'
		);
		$this->assertSame(
			array( $other_html[0] ),
			array_values( array_unique( $other_html ) ),
			'Failed to assert the pinned block rendered the same thing on every iteration rather than drifting'
				. ' with the outer occurrence.'
		);
		$this->assertStringContainsString(
			$other_link,
			$other_html[0],
			'Failed to assert a block pinned to another post keeps that post\'s bare permalink mid-iteration.'
		);
		$this->assertStringContainsString(
			$other_date,
			$other_html[0],
			'Failed to assert a block pinned to another post renders that post\'s own date mid-iteration.'
		);
	}

	/**
	 * Coverage for the `timezone` column traveling on the result object.
	 *
	 * `timezone` is one of the five occurrence columns a result object carries,
	 * and the subsystem's standing rule is that an occurrence's time of day
	 * comes from the occurrence record and never from the series anchor. Unlike
	 * the four datetime columns it has a fallback that normally agrees with it:
	 * when the
	 * occurrence row's nullable `timezone` is empty, `occurrence_value()` reads
	 * the series' own `gatherpress_timezone` meta. Every other fixture in this
	 * suite gives the series and its occurrences the same timezone, so the
	 * right answer and the fallback answer coincide and nothing discriminates
	 * between them. Mutating `stamp_occurrence()` to stamp `null` for this one
	 * column leaves the whole recurrence suite green.
	 *
	 * This fixture makes the two differ: the occurrence
	 * rows are projected in `America/New_York`, then the *series'* own timezone
	 * meta is poisoned to `Asia/Tokyo` afterwards. Inside a loop iteration the
	 * read must produce the occurrence record's zone; outside it, the series'.
	 *
	 * @covers ::metadata
	 * @covers ::occurrence_value
	 * @covers ::get_datetime
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_a_loop_row_reads_the_occurrence_records_timezone_not_the_series_meta(): void {
		$zone   = new DateTimeZone( 'America/New_York' );
		$now    = new DateTimeImmutable( 'now', $zone );
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at(
			$anchor,
			$now->modify( '-30 minutes' ),
			self::DAILY_RULE,
			'America/New_York'
		);

		update_post_meta( $series_id, 'gatherpress_timezone', 'Asia/Tokyo' );

		$this->assertSame(
			'Asia/Tokyo',
			get_post_meta( $series_id, 'gatherpress_timezone', true ),
			'Failed to assert the fixture poisoned the series\' own timezone meta, so the occurrence rows and'
				. ' the series genuinely disagree.'
		);

		$query     = $this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) );
		$from_meta = array();
		$from_api  = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$from_meta[] = get_post_meta( $series_id, 'gatherpress_timezone', true );
			$from_api[]  = Context::get_instance()->get_datetime( $series_id )['timezone'];
		}

		wp_reset_postdata();

		// `wp_reset_postdata()` restores from the *main* query, which has no
		// post in this harness, so the last stamped loop row would otherwise
		// still be the global post and the read below would prove nothing.
		$this->go_to( home_url( '/' ) );

		$this->assertSame(
			array_fill( 0, 4, 'America/New_York' ),
			$from_meta,
			'Failed to assert every loop iteration read the occurrence record\'s own timezone through the'
				. ' get_post_metadata filter, rather than falling back to the series\' meta.'
		);
		$this->assertSame(
			array_fill( 0, 4, 'America/New_York' ),
			$from_api,
			'Failed to assert Context::get_datetime() reports the occurrence record\'s own timezone in a loop.'
		);
		$this->assertSame(
			'Asia/Tokyo',
			get_post_meta( $series_id, 'gatherpress_timezone', true ),
			'Failed to assert the series\' own timezone is read again once the loop is over. Without this the'
				. ' fixture would prove nothing, since a substitution that never lifted would look the same.'
		);
	}


	/**
	 * Coverage for `Occurrences::table_exists()` on both memo arms.
	 *
	 * The first call probes the schema, the second answers from the memo. The
	 * false arm belongs to the `@group multisite` suite, where a blog can be
	 * built without the table; here only the true arm is reachable.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::table_exists
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::forget_table_exists
	 *
	 * @return void
	 */
	public function test_table_exists_memoizes_its_answer(): void {
		global $wpdb;

		$occurrences = Occurrences::get_instance();

		$occurrences->forget_table_exists();

		$wpdb->queries      = array();
		$saved              = $wpdb->save_queries;
		$wpdb->save_queries = true;

		$first  = $occurrences->table_exists();
		$probes = count( $wpdb->queries );
		$second = $occurrences->table_exists();
		$after  = count( $wpdb->queries );

		$wpdb->save_queries = $saved;
		$wpdb->queries      = array();

		$this->assertTrue( $first, 'Failed to assert the occurrence table exists on the fixture site.' );
		$this->assertTrue( $second, 'Failed to assert the memoized answer matches.' );
		$this->assertSame( 1, $probes, 'Failed to assert the first call probes the schema exactly once.' );
		$this->assertSame( $probes, $after, 'Failed to assert the second call answers from the memo.' );
	}

	/**
	 * Coverage for `Occurrences::table_exists()` refusing a lookalike table.
	 *
	 * Every `_` in `{prefix}gatherpress_event_occurrences` is a
	 * single-character `LIKE` wildcard, so an unescaped probe is satisfied by
	 * any table whose name differs only at those positions. The consequence is
	 * not cosmetic: the probe memoizes `true`, the occurrence join runs against
	 * a table that does not exist, and every published event disappears from
	 * the very lists this guard was added to keep working.
	 *
	 * The blog prefix is moved to one whose real occurrence table is absent,
	 * because that is the only state in which the question can be asked. On
	 * the fixture site the real table exists and would answer `true` honestly.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::table_exists
	 *
	 * @return void
	 */
	public function test_table_exists_refuses_a_lookalike_table(): void {
		global $wpdb;

		$occurrences = Occurrences::get_instance();
		$prefix      = $wpdb->prefix;
		$absent      = 'gplk_';
		$real        = sprintf( Occurrences::TABLE_FORMAT, $absent );
		// Same length, same characters everywhere except the underscores, which
		// `LIKE` treats as single-character wildcards unless escaped.
		$lookalike = str_replace( '_', 'x', $real );

		$this->assertNotSame( $real, $lookalike, 'Fixture is inert: the lookalike name must differ.' );

		// The suite rewrites every `CREATE TABLE` into `CREATE TEMPORARY TABLE`,
		// and a temporary table is invisible to `SHOW TABLES`. That would
		// make this fixture inert, passing against the unescaped probe it
		// exists to reject. The two rewriting filters are stood down for the
		// duration so the lookalike is a real table, and restored in `finally`
		// along with the prefix, the memo, and the table itself.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		try {
			$wpdb->query( "CREATE TABLE IF NOT EXISTS `{$lookalike}` ( id BIGINT UNSIGNED NOT NULL )" );

			$created = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $lookalike ) ) );

			$wpdb->prefix = $absent;

			$occurrences->forget_table_exists();

			$answer = $occurrences->table_exists();
		} finally {
			$wpdb->prefix = $prefix;

			$occurrences->forget_table_exists();

			$wpdb->query( "DROP TABLE IF EXISTS `{$lookalike}`" );

			add_filter( 'query', array( $this, '_create_temporary_tables' ) );
			add_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange

		$this->assertSame(
			$lookalike,
			$created,
			'Fixture is inert: the lookalike table was not created as a real, listable table.'
		);

		$this->assertFalse(
			$answer,
			'Failed to assert a lookalike table cannot satisfy the occurrence-table existence probe.'
		);
		$this->assertTrue(
			$occurrences->table_exists(),
			'Failed to assert the real table still answers true once the prefix is restored.'
		);
	}

	/**
	 * Coverage for per-occurrence RSVP state inside a loop.
	 *
	 * The archive and Query Loop are the surfaces where this breaks: reading
	 * only `Context::current()` in `Rsvp_Occurrence::current_occurrence()`
	 * yields the *request's* occurrence. A loop
	 * has no request occurrence, so it answers null on every row and every
	 * row reads the same series-wide RSVP state, so an attendee on the first
	 * date appears to be attending all fourteen.
	 *
	 * The fixture makes the right answer and the wrong answer differ:
	 * exactly one occurrence carries an RSVP, so a
	 * series-wide read shows attending on every row while a correctly scoped
	 * read shows it on one. Asserting the whole per-row vector rather than a
	 * single row is what makes that distinction visible.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::current_occurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Context::loop_occurrence
	 *
	 * @return void
	 */
	public function test_rsvp_state_in_a_loop_is_scoped_to_each_row_occurrence(): void {
		$anchor  = $this->now()->modify( '+2 days' );
		$post_id = $this->create_series_at( $anchor, $anchor->modify( '+2 hours' ) );
		$user_id = $this->factory->user->create();

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );
		$this->assertGreaterThan( 2, count( $rows ), 'Failed to assert the fixture projected several occurrences.' );

		$target = (string) $rows[0]['recurrence_id'];

		// Write the RSVP through the same context the request path establishes,
		// so the stored comment carries exactly one occurrence term.
		Context::get_instance()->set( $post_id, $target );
		$rsvp = new Rsvp( $post_id );
		$rsvp->save( $user_id, 'attending' );
		Context::get_instance()->clear();

		$query     = $this->run_upcoming_query();
		$attending = array();

		while ( $query->have_posts() ) {
			$query->the_post();

			$occurrence = Rsvp_Occurrence::current_occurrence( $post_id );
			$responses  = ( new Rsvp( $post_id ) )->responses();

			$attending[ (string) ( $occurrence['recurrence_id'] ?? 'none' ) ] =
				(int) ( $responses['attending']['count'] ?? -1 );
		}

		wp_reset_postdata();

		$this->assertArrayHasKey(
			$target,
			$attending,
			'Failed to assert each loop row resolved its own occurrence identifier.'
		);
		$this->assertSame(
			1,
			$attending[ $target ],
			'Failed to assert the occurrence holding the RSVP reports one attendee.'
		);

		unset( $attending[ $target ] );

		$this->assertNotEmpty(
			$attending,
			'Failed to assert the loop produced sibling occurrences to compare against.'
		);
		$this->assertSame(
			array( 0 ),
			array_values( array_unique( $attending ) ),
			'Failed to assert every other occurrence reports zero attendees rather than inheriting the series count.'
		);
	}

	/**
	 * Coverage for the per-row occurrence identity reaching the browser.
	 *
	 * The server half of this landed already: every row resolves its own
	 * occurrence and renders its own counts. None of that survives the trip to
	 * the client, because the interactivity block context each row emits carries
	 * `postId` alone. One post rendered many times therefore collapses to a
	 * single entry in `state.posts`, and RSVPing to one occurrence visually
	 * applies to every row of the series.
	 *
	 * The assertion is on the whole per-row vector of emitted identities rather
	 * than on one row: a single-row assertion passes
	 * just as well when every row emits the same thing, which is precisely the
	 * defect. The expected vector is the series' own occurrence identifiers, in
	 * loop order, so the only mechanism that can produce it is the real one.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::block_context
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::current_recurrence_id
	 *
	 * @return void
	 */
	public function test_each_loop_row_emits_its_own_occurrence_in_the_block_context(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );

		$contexts = $this->loop_block_contexts(
			$this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) )
		);

		$this->assertCount(
			4,
			$contexts,
			'Failed to assert the loop expanded the series to its four upcoming occurrences.'
		);

		$expected = array();

		foreach ( array( 1, 2, 3, 4 ) as $index ) {
			$expected[] = array(
				'postId'       => $series_id,
				'recurrenceId' => $this->occurrence_id( $anchor, $index ),
			);
		}

		$this->assertSame(
			$expected,
			$contexts,
			'Failed to assert every row published its own occurrence identity to the client. Four contexts'
				. ' carrying the same post ID and no occurrence is the defect a browser measured: the client'
				. ' store keys on the post alone, so all four rows share one RSVP state.'
		);
	}

	/**
	 * Coverage for every one of the five block-context emitters, not just one.
	 *
	 * `test_each_loop_row_emits_its_own_occurrence_in_the_block_context()` above
	 * renders `gatherpress/rsvp-count` alone, so reverting any of the other four
	 * emitters to `array( 'postId' => $post_id )` left the whole PHP suite
	 * green. The partial revert is the dangerous shape: the RSVP button keys on
	 * `42` while the counts beside it key on `42:20260823T180000`, so the button
	 * collapses across the series, the counts stay right, and nothing notices.
	 * The online-event-link emitter is the highest-consequence of the four.
	 * That block reveals a private meeting URL.
	 *
	 * Every emitter renders inside the same loop iteration, so the vector each
	 * one publishes is compared against the same loop order.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::block_context
	 *
	 * @return void
	 */
	public function test_every_block_context_emitter_publishes_its_own_row_occurrence(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );

		$contexts = $this->loop_emitter_contexts(
			$this->run_upcoming_query( array( 'post__in' => array( $series_id ) ) )
		);

		$this->assertSame(
			array( 'rsvp', 'rsvp-form', 'rsvp-response', 'rsvp-count', 'online-event-link' ),
			array_keys( $contexts ),
			'Failed to assert all five emitters rendered a context on every iteration. An emitter that renders'
				. ' nothing here is an emitter this test cannot guard.'
		);

		$expected = array();

		foreach ( array( 1, 2, 3, 4 ) as $index ) {
			$expected[] = array(
				'postId'       => $series_id,
				'recurrenceId' => $this->occurrence_id( $anchor, $index ),
			);
		}

		foreach ( $contexts as $emitter => $rows ) {
			$this->assertSame(
				$expected,
				array_map( array( $this, 'identity_only' ), $rows ),
				sprintf(
					'Failed to assert the %s block published its own occurrence identity on every row.',
					$emitter
				)
			);
		}
	}

	/**
	 * Reduce a published block context to the identity keys.
	 *
	 * The online-event-link block merges its own `linkText` into the payload,
	 * so the comparison is scoped to the two keys every emitter shares. Key
	 * order is `block_context()`'s own, which is what the assertion pins.
	 *
	 * @since 0.36.0
	 *
	 * @param array $context A decoded `data-wp-context` payload.
	 *
	 * @return array The `postId` and `recurrenceId` entries the payload carries.
	 */
	public function identity_only( array $context ): array {
		return array_intersect_key( $context, array_flip( array( 'postId', 'recurrenceId' ) ) );
	}

	/**
	 * Coverage for the non-recurring arm of the emitted block context.
	 *
	 * An ordinary event must publish exactly what it published before this
	 * existed, which is `postId` and nothing else, so its state key stays the
	 * bare post ID and its request bodies stay byte-identical.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::block_context
	 *
	 * @return void
	 */
	public function test_a_plain_event_row_emits_a_post_id_only_block_context(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );

		$plain_id = $this->create_event_at( $now->modify( '+400 hours' ), $now->modify( '+401 hours' ) );

		$contexts = $this->loop_block_contexts( $this->run_upcoming_query() );
		$last     = end( $contexts );

		$this->assertSame(
			array( 'postId' => $plain_id ),
			$last,
			'Failed to assert a non-recurring row emits the post ID alone, with no occurrence key, even while'
				. ' sharing a loop with a recurring series.'
		);
	}

	/**
	 * Coverage for the no-recurring-events guard on the block-context entry point this adds.
	 *
	 * Named after the loop it drives rather than after the resolver, because a
	 * "performs no writes" test that drives the body of the work and never the
	 * entry point passes while the guard is missing at the entry point.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::block_context
	 *
	 * @return void
	 */
	public function test_block_context_touches_no_occurrence_table_without_recurring_events(): void {
		global $wpdb;

		$now      = $this->now();
		$plain_id = $this->create_event_at( $now->modify( '+2 hours' ), $now->modify( '+3 hours' ) );

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// Warm the RSVP response transient the count block has always written,
		// so the capture below sees only what this render newly writes. Without
		// it the assertion would be measuring a pre-existing cache fill rather
		// than anything this test is about.
		$this->loop_block_contexts( $this->run_upcoming_query() );

		$wpdb->queries      = array();
		$saved              = $wpdb->save_queries;
		$wpdb->save_queries = true;

		$contexts = $this->loop_block_contexts( $this->run_upcoming_query() );
		$captured = $wpdb->queries;

		$wpdb->save_queries = $saved;
		$wpdb->queries      = array();

		$occurrence_queries = array_values(
			array_filter(
				array_column( $captured, 0 ),
				static function ( string $sql ) use ( $table ): bool {
					return str_contains( $sql, $table );
				}
			)
		);
		$option_writes      = array_values(
			array_filter(
				array_column( $captured, 0 ),
				static function ( string $sql ) use ( $wpdb ): bool {
					return str_contains( $sql, $wpdb->options )
						&& ( str_contains( $sql, 'INSERT' ) || str_contains( $sql, 'UPDATE' ) );
				}
			)
		);

		$this->assertSame(
			array(),
			$occurrence_queries,
			'Failed to assert emitting the block context on a site with no recurring events never reads the'
				. ' occurrence table.'
		);
		$this->assertSame(
			array(),
			$option_writes,
			'Failed to assert emitting the block context on a site with no recurring events writes no option.'
		);
		$this->assertSame(
			array( array( 'postId' => $plain_id ) ),
			$contexts,
			'Failed to assert the ordinary event still emitted its unchanged block context.'
		);
	}

	/**
	 * Coverage for `block_context()` invoked directly, on both of its arms.
	 *
	 * Coverage tracing is the reason this exists alongside the rendered-output
	 * tests above: xdebug does not trace a helper's body reliably when it is
	 * only ever reached from a block render inside a loop, so each return path
	 * is driven once through the public method as well.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::block_context
	 *
	 * @return void
	 */
	public function test_block_context_returns_both_shapes(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$plain_id  = $this->create_event_at( $now->modify( '+400 hours' ), $now->modify( '+401 hours' ) );
		$requested = $this->occurrence_id( $anchor, 3 );

		$this->assertSame(
			array( 'postId' => $plain_id ),
			Rsvp_Occurrence::block_context( $plain_id ),
			'Failed to assert a post with no occurrence in play gets the post ID alone.'
		);

		Context::get_instance()->set( $series_id, $requested );

		$this->assertSame(
			array(
				'postId'       => $series_id,
				'recurrenceId' => $requested,
			),
			Rsvp_Occurrence::block_context( $series_id ),
			'Failed to assert a post rendering an occurrence gets that occurrence\'s identifier.'
		);
	}
	/**
	 * Coverage for `permalink()`'s third pass-through: an occurrence whose post
	 * has no permalink to compose one on top of.
	 *
	 * `Rewrite::get_occurrence_url()` builds the occurrence URL from the series
	 * permalink and answers with an empty string when there is none, which is
	 * what `get_permalink()` returns for a post that is no longer there.
	 * Returning that to the `post_link` filter publishes `href=""`, which
	 * resolves to the current page. Reachability is narrow, because a resolved
	 * occurrence normally implies a live post, but an empty href is a worse
	 * answer than the permalink core already had.
	 *
	 * @covers ::permalink
	 *
	 * @return void
	 */
	public function test_permalink_falls_back_when_the_post_has_no_permalink(): void {
		$now    = $this->now();
		$anchor = $now->modify( '-1 hour' );

		$series_id = $this->create_series_at( $anchor, $now->modify( '-30 minutes' ) );
		$post      = get_post( $series_id );

		Context::get_instance()->set( $series_id, $this->occurrence_id( $anchor, 1 ) );

		wp_delete_post( $series_id, true );

		$result = Context::get_instance()->permalink( 'https://example.test/original/', $post );

		Context::get_instance()->clear();

		$this->assertSame(
			'https://example.test/original/',
			$result,
			'Failed to assert permalink() degrades to the permalink it was handed rather than to an empty href.'
		);
	}
}
