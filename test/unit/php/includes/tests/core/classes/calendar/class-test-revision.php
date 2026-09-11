<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Revision.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Revision;
use GatherPress\Core\Event\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Revision.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Revision
 * @group              endpoints
 */
class Test_Revision extends Base {

	/**
	 * Create a published event post.
	 *
	 * @since 0.36.0
	 *
	 * @return int The event post ID.
	 */
	protected function make_event(): int {
		return (int) $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * An event nothing has advanced reports no stored revision.
	 *
	 * Zero rather than the modification time, because callers combine the two
	 * and a stored value that started life at the clock could never be told
	 * apart from one that was written.
	 *
	 * @covers ::stored
	 *
	 * @return void
	 */
	public function test_stored_is_zero_until_something_advances_it(): void {
		$this->assertSame(
			0,
			Revision::get_instance()->stored( $this->make_event() ),
			'An event whose revision has never been advanced has none stored.'
		);
	}

	/**
	 * The current revision falls back to the post's modification time.
	 *
	 * @covers ::current
	 * @covers ::from_post_modified
	 *
	 * @return void
	 */
	public function test_current_falls_back_to_the_modification_time(): void {
		global $wpdb;

		$post_id = $this->make_event();

		// Written straight to the row: `wp_update_post()` overwrites
		// `post_modified_gmt` with the current time whatever it is passed, which
		// would make the expectation below unstateable.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified_gmt' => '2030-01-01 10:00:00',
				'post_modified'     => '2030-01-01 10:00:00',
			),
			array( 'ID' => $post_id )
		);
		clean_post_cache( $post_id );

		$this->assertSame(
			strtotime( '2030-01-01 10:00:00' ) - Revision::EPOCH,
			Revision::get_instance()->current( $post_id ),
			'An ordinary post edit has to remain visible to a client with no stored revision.'
		);
	}

	/**
	 * A post that no longer exists contributes nothing rather than warning.
	 *
	 * @covers ::from_post_modified
	 *
	 * @return void
	 */
	public function test_current_is_zero_for_a_missing_post(): void {
		$post_id = $this->make_event();

		wp_delete_post( $post_id, true );

		$this->assertSame(
			0,
			Revision::get_instance()->current( $post_id ),
			'A deleted post has no modification time and no stored revision.'
		);
	}

	/**
	 * Two advances inside one second still produce strictly increasing values.
	 *
	 * The property the whole class exists for. `time()` cannot separate them and
	 * `post_modified_gmt` is not written by any of the operations that need
	 * separating, so the stored value has to carry the ordering itself.
	 *
	 * The stored revision is seeded an hour past the clock, so the `time()`
	 * arm of the allocation floor stays out of reach for the whole test
	 * however many wall-clock seconds the three advances happen to straddle.
	 * Every increase below can then only come from the stored value carrying
	 * the ordering, which is the property under test, stated without racing
	 * the suite's own speed against the clock.
	 *
	 * @covers ::advance
	 * @covers ::current
	 * @covers ::stored
	 *
	 * @return void
	 */
	public function test_advance_is_strictly_increasing_within_one_second(): void {
		$post_id  = $this->make_event();
		$instance = Revision::get_instance();
		$seed     = ( time() - Revision::EPOCH ) + HOUR_IN_SECONDS;

		update_post_meta( $post_id, Revision::META_KEY, (string) $seed );

		$first  = $instance->advance( $post_id );
		$second = $instance->advance( $post_id );
		$third  = $instance->advance( $post_id );

		$this->assertGreaterThan(
			$seed,
			$first,
			'The first advance must move past the seeded value the clock cannot reach.'
		);
		$this->assertGreaterThan( $first, $second, 'A second advance in the same second must still be greater.' );
		$this->assertGreaterThan( $second, $third, 'And a third, for the same reason.' );
		$this->assertSame(
			$third,
			$instance->current( $post_id ),
			'The advanced value is what the series reports afterwards.'
		);
		$this->assertSame(
			$third,
			$instance->stored( $post_id ),
			'And it is durable rather than held for the request.'
		);
	}

	/**
	 * A revision is read and written across the whole logical series.
	 *
	 * A series is never assumed to be one post. A subscription held
	 * against the origin fragment of a split series has to see a change made to
	 * the fragment carrying the forward dates, which it only can if the
	 * revision is a property of the series rather than of a post.
	 *
	 * @covers ::advance
	 * @covers ::stored
	 *
	 * @return void
	 */
	public function test_a_revision_spans_every_post_of_the_series(): void {
		$origin  = $this->make_event();
		$sibling = $this->make_event();

		add_filter(
			'gatherpress_series_post_ids',
			static function ( array $post_ids, int $resolved ) use ( $origin, $sibling ): array {
				return in_array( $resolved, array( $origin, $sibling ), true )
					? array( $origin, $sibling )
					: $post_ids;
			},
			10,
			2
		);

		$advanced = Revision::get_instance()->advance( $sibling );

		$this->assertSame(
			$advanced,
			Revision::get_instance()->stored( $origin ),
			'A change on one fragment must be visible to a subscription held against the other.'
		);
	}

	/**
	 * The revision saturates rather than emitting an out-of-range INTEGER.
	 *
	 * RFC 5545 caps an INTEGER at 2147483647 and a client may reject a whole
	 * component that exceeds it, which is worse than a frozen revision.
	 *
	 * @covers ::stored
	 * @covers ::advance
	 *
	 * @return void
	 */
	public function test_the_revision_clamps_to_the_rfc_integer_ceiling(): void {
		$post_id = $this->make_event();

		update_post_meta( $post_id, Revision::META_KEY, Revision::CEILING + 1000 );

		$this->assertSame(
			Revision::CEILING,
			Revision::get_instance()->stored( $post_id ),
			'A stored value past the ceiling reads back as the ceiling.'
		);
		$this->assertSame(
			Revision::CEILING,
			Revision::get_instance()->advance( $post_id ),
			'And advancing from the ceiling cannot push past it.'
		);
	}

	/**
	 * An advance builds on the stored row, not on a read made before it.
	 *
	 * `current() + 1` followed by per-sibling writes is the interleave two
	 * concurrent cancellations produce: both read revision S, both publish
	 * S + 1, and a subscriber receives two different bodies carrying the same
	 * `SEQUENCE`, which entitles it to ignore the second. One process cannot
	 * run two writers, so the stale first read is forced through the meta
	 * cache, which is the same seam a concurrent writer's snapshot lives
	 * behind. The stored value sits ahead of the clock floor, as a burst of
	 * same-second advances leaves it, so a write that builds on the stale
	 * read cannot reach the required value by accident of timing.
	 *
	 * @covers ::advance
	 *
	 * @return void
	 */
	public function test_advance_builds_on_the_stored_row_not_a_stale_read(): void {
		global $wpdb;

		$post_id  = $this->make_event();
		$instance = Revision::get_instance();
		$high     = ( time() - Revision::EPOCH ) + HOUR_IN_SECONDS;

		update_post_meta( $post_id, Revision::META_KEY, $high );

		// The snapshot a concurrent writer holds from before this write.
		wp_cache_set(
			$post_id,
			array( Revision::META_KEY => array( (string) ( $high - 50 ) ) ),
			'post_meta'
		);

		$next = $instance->advance( $post_id );

		$this->assertGreaterThan(
			$high,
			$next,
			'An advance must move past the stored revision; repeating one already published lets a client ignore it.'
		);

		// Read the row back uncached, so the assertion measures storage.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = %s ORDER BY meta_id LIMIT 1',
				$wpdb->postmeta,
				$post_id,
				Revision::META_KEY
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertSame(
			$next,
			$stored,
			'And the value the caller publishes must be the one the row holds.'
		);
	}

	/**
	 * A writer pausing between allocation and publication cannot regress mirrors.
	 *
	 * Allocation is serialized on the canonical row, but publication is a
	 * separate phase. Writer one allocates S plus one and pauses before its
	 * mirror loop; writer two allocates S plus two and publishes it everywhere;
	 * writer one resumes. If publication is unconditional, writer one's resumed
	 * writes drag every mirror back to S plus one: a subscriber sees `SEQUENCE`
	 * move backwards, which RFC 5545 entitles it to reject, and the allocation
	 * row itself regresses, so the values in between are handed out again.
	 *
	 * What this proves, precisely: both writers run the production `advance()`
	 * on the suite's single database connection, and the interleave is injected
	 * in process through the `query` filter, which runs writer two's whole
	 * advance between writer one's allocation read-back and writer one's first
	 * publication statement. That is the exact ordering the failure needs, so
	 * the monotonic-publication property is genuinely exercised. What it does
	 * not prove is the row-lock serialization of the allocation itself; the
	 * pinned-statement test below and the stale-read test above carry that
	 * half, and the values asserted here presuppose it.
	 *
	 * @covers ::advance
	 * @covers ::stored
	 *
	 * @return void
	 */
	public function test_a_paused_publisher_cannot_regress_a_newer_published_revision(): void {
		global $wpdb;

		$origin   = $this->make_event();
		$sibling  = $this->make_event();
		$instance = Revision::get_instance();
		$seed     = ( time() - Revision::EPOCH ) + HOUR_IN_SECONDS;

		add_filter(
			'gatherpress_series_post_ids',
			static function ( array $post_ids, int $resolved ) use ( $origin, $sibling ): array {
				return in_array( $resolved, array( $origin, $sibling ), true )
					? array( $origin, $sibling )
					: $post_ids;
			},
			10,
			2
		);

		// Seeded ahead of the clock, so every value below can only come from
		// the stored row carrying the ordering, never from `time()`.
		update_post_meta( $origin, Revision::META_KEY, (string) $seed );

		$armed    = false;
		$fired    = false;
		$writer_2 = null;

		// Writer one is the production `advance()` call below. Its allocation
		// read-back is the `SELECT LAST_INSERT_ID()` statement; the first
		// statement after that belongs to its publication phase, and that gap
		// is the window a concurrent writer's whole advance fits into.
		$interleave = static function ( string $sql ) use ( &$armed, &$fired, &$writer_2, $origin ): string {
			if ( ! $fired ) {
				if ( $armed ) {
					// Flagged before the nested advance, so the statements
					// writer two issues pass through here untouched.
					$fired    = true;
					$writer_2 = Revision::get_instance()->advance( $origin );
				} elseif ( str_contains( $sql, 'SELECT LAST_INSERT_ID()' ) ) {
					$armed = true;
				}
			}

			return $sql;
		};

		add_filter( 'query', $interleave );

		$writer_1 = $instance->advance( $origin );

		remove_filter( 'query', $interleave );

		// Fixture integrity: without the interleave firing, nothing below
		// measures the race, so a green run would be vacuous.
		$this->assertNotNull( $writer_2, 'The interleave must actually run writer two inside writer one.' );
		$this->assertSame(
			$seed + 1,
			$writer_1,
			'Writer one allocates one past the seed before pausing.'
		);
		$this->assertSame(
			$seed + 2,
			$writer_2,
			'Writer two, allocating after writer one, must be handed the next value.'
		);

		// Read each mirror's first row back uncached, so the assertions
		// measure storage, and the row `stored()` reads rather than any
		// duplicate a racing seed insert may have left behind.
		$read_first_row = static function ( int $post_id ) use ( $wpdb ): int {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = %s ORDER BY meta_id LIMIT 1',
					$wpdb->postmeta,
					$post_id,
					Revision::META_KEY
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		};

		$this->assertGreaterThanOrEqual(
			$writer_2,
			$read_first_row( $origin ),
			'The allocation row must never regress below the newest allocation, or its values are reused.'
		);
		$this->assertGreaterThanOrEqual(
			$writer_2,
			$read_first_row( $sibling ),
			'A mirror already holding the newer revision must not be dragged back by the slower publisher.'
		);
		$this->assertSame(
			$writer_2,
			$instance->stored( $origin ),
			'And the newest allocation is what the series reports afterwards.'
		);
	}

	/**
	 * The allocation is one in-place statement, and the only write that decides.
	 *
	 * A separate read is the window a concurrent writer interleaves through,
	 * so the property pinned here is the statement's shape: exactly one
	 * `UPDATE` touches the revision key, and it computes from the row rather
	 * than writing a value computed in PHP. The mirror loop publishes the
	 * allocated value to the siblings afterwards; the canonical row is
	 * excluded from it, because the allocating statement already wrote it.
	 *
	 * @covers ::advance
	 *
	 * @return void
	 */
	public function test_the_allocation_is_one_in_place_statement(): void {
		global $wpdb;

		$post_id  = $this->make_event();
		$instance = Revision::get_instance();

		$instance->advance( $post_id );

		$before = count( (array) $wpdb->queries );

		$instance->advance( $post_id );

		$writes = array_values(
			array_filter(
				array_column( array_slice( (array) $wpdb->queries, $before ), 0 ),
				static function ( string $sql ): bool {
					return str_starts_with( ltrim( $sql ), 'UPDATE' )
						&& str_contains( $sql, Revision::META_KEY );
				}
			)
		);

		$this->assertCount(
			1,
			$writes,
			'One statement must allocate; a second write or a separate read is a window for a concurrent writer.'
		);
		$this->assertStringContainsString(
			'CAST( meta_value AS SIGNED ) + 1',
			$writes[0],
			'And it must compute from the row rather than write a value computed in PHP.'
		);
	}

	/**
	 * An advance from a row already at the ceiling still answers the ceiling.
	 *
	 * The in-place statement cannot move such a row, so the read-back has no
	 * allocated value to report and the computed floor answers instead. This
	 * is the branch a saturated series lives on for every later change.
	 *
	 * @covers ::advance
	 *
	 * @return void
	 */
	public function test_an_advance_from_exactly_the_ceiling_answers_the_ceiling(): void {
		$post_id = $this->make_event();

		update_post_meta( $post_id, Revision::META_KEY, Revision::CEILING );

		$this->assertSame(
			Revision::CEILING,
			Revision::get_instance()->advance( $post_id ),
			'A row the statement cannot move still answers the clamped floor, never a stale connection value.'
		);
	}

	/**
	 * An advance survives an integration emptying the series resolution.
	 *
	 * `resolve_post_ids()` is filterable, and a filter returning an empty
	 * array would otherwise leave the allocation with no canonical row and
	 * the series with no mirror at all.
	 *
	 * @covers ::advance
	 *
	 * @return void
	 */
	public function test_advance_survives_a_filter_that_empties_the_series(): void {
		global $wpdb;

		$post_id = $this->make_event();

		add_filter( 'gatherpress_series_post_ids', '__return_empty_array' );

		$next = Revision::get_instance()->advance( $post_id );

		remove_filter( 'gatherpress_series_post_ids', '__return_empty_array' );

		$this->assertGreaterThan( 0, $next, 'The advance must still allocate a value.' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT meta_value FROM %i WHERE post_id = %d AND meta_key = %s ORDER BY meta_id LIMIT 1',
				$wpdb->postmeta,
				$post_id,
				Revision::META_KEY
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertSame(
			$next,
			$stored,
			'And the post itself must carry it, so the resolving fragment is its own fallback series.'
		);
	}

	/**
	 * A negative stored value cannot drag the revision below zero.
	 *
	 * @covers ::stored
	 *
	 * @return void
	 */
	public function test_a_corrupt_negative_revision_reads_back_as_zero(): void {
		$post_id = $this->make_event();

		update_post_meta( $post_id, Revision::META_KEY, -5 );

		$this->assertSame(
			0,
			Revision::get_instance()->stored( $post_id ),
			'A sequence that moves backwards is one a client may ignore forever.'
		);
	}
}
