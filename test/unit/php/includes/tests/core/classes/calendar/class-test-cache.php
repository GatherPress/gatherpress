<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Cache.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Cache;
use GatherPress\Core\Calendar\Revision;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Cache.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Cache
 * @group              endpoints
 */
class Test_Cache extends Base {

	/**
	 * Coverage for __construct.
	 *
	 * The instance is built during plugin bootstrap, so the constructor only
	 * runs inside a test once the stored instance is cleared.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_builds_the_instance(): void {
		$reflection = new \ReflectionClass( Cache::class );
		$property   = $reflection->getProperty( 'instance' );

		$property->setAccessible( true );
		$property->setValue( null, null );

		$this->assertInstanceOf(
			Cache::class,
			Cache::get_instance(),
			'Failed to assert that the constructor returns a Cache instance.'
		);
	}

	/**
	 * An occurrence announcement for a post no calendar reads is ignored.
	 *
	 * The action carries a bare post ID and any plugin may fire it. Stamping on
	 * one that contributes to no feed would strand every cached body on the site
	 * and advance a revision that describes nothing.
	 *
	 * @covers ::mark_changed_for_occurrences
	 *
	 * @return void
	 */
	public function test_an_occurrence_change_on_a_non_calendar_post_is_ignored(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );
		$stamp   = Cache::get_instance()->get_last_modified();
		$count   = Cache::get_instance()->get_change_count();

		Cache::get_instance()->mark_changed_for_occurrences( $post_id );

		$this->assertSame(
			$stamp,
			Cache::get_instance()->get_last_modified(),
			'A post that appears in no calendar cannot invalidate every calendar.'
		);
		$this->assertSame(
			$count,
			Cache::get_instance()->get_change_count(),
			'And it cannot move the cache namespace either.'
		);
		$this->assertSame(
			'',
			(string) get_post_meta( (int) $post_id, Revision::META_KEY, true ),
			'Nor acquire a calendar revision it has no use for.'
		);
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Cache::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'save_post',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_post' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_post' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'set_object_terms',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_terms' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_occurrences_changed',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_occurrences' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The version stamp seeds itself once and then holds still, so clients get
	 * a validator that means something.
	 *
	 * @covers ::get_last_modified
	 *
	 * @return void
	 */
	public function test_get_last_modified_is_stable_once_seeded(): void {
		delete_option( Cache::LAST_MODIFIED_OPTION );

		$instance = Cache::get_instance();
		$first    = $instance->get_last_modified();

		$this->assertNotEmpty( $first, 'A first read should seed a timestamp.' );
		$this->assertSame(
			$first,
			$instance->get_last_modified(),
			'Repeat reads should return the same timestamp rather than moving.'
		);
	}

	/**
	 * Marking the calendar changed moves the key namespace, which is what
	 * makes every cached response unreachable at once.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_versioned_key
	 *
	 * @return void
	 */
	public function test_mark_changed_moves_the_versioned_key(): void {
		$instance = Cache::get_instance();
		$before   = $instance->get_versioned_key( 'ics:example' );

		update_option( Cache::LAST_MODIFIED_OPTION, '2030-01-01 00:00:00', false );

		$this->assertNotSame(
			$before,
			$instance->get_versioned_key( 'ics:example' ),
			'A new version stamp should produce a different cache key.'
		);
	}

	/**
	 * The payload is stored as a transient, so it survives a site without a
	 * persistent object cache, and its name stays inside the option-name limit.
	 *
	 * @covers ::remember
	 * @covers ::get_versioned_key
	 *
	 * @return void
	 */
	public function test_remember_stores_the_payload_in_a_transient(): void {
		$instance = Cache::get_instance();
		$key      = 'ics:' . str_repeat( 'long-scope-', 40 );
		$renderer = static function (): string {
			return 'BEGIN:VCALENDAR';
		};

		$instance->remember( $key, $renderer );

		$name = $instance->get_versioned_key( $key );

		$this->assertSame(
			'BEGIN:VCALENDAR',
			get_transient( $name ),
			'The rendered payload should be readable back as a transient.'
		);
		$this->assertStringStartsWith(
			Cache::TRANSIENT_PREFIX,
			$name,
			'Transient names should carry the calendar prefix.'
		);
		$this->assertLessThanOrEqual(
			172,
			strlen( $name ),
			'A long scope key must still produce a transient name within the option-name limit.'
		);
	}

	/**
	 * Coverage for remember: renders once, serves the cached copy after.
	 *
	 * @covers ::remember
	 *
	 * @return void
	 */
	public function test_remember_renders_once_then_serves_the_cache(): void {
		$instance = Cache::get_instance();
		$calls    = 0;
		$renderer = static function () use ( &$calls ): string {
			++$calls;

			return 'BEGIN:VCALENDAR';
		};

		$this->assertSame( 'BEGIN:VCALENDAR', $instance->remember( 'ics:test-a', $renderer ) );
		$this->assertSame( 'BEGIN:VCALENDAR', $instance->remember( 'ics:test-a', $renderer ) );
		$this->assertSame( 1, $calls, 'The renderer should run once for a repeated request.' );
	}

	/**
	 * A stamped calendar rebuilds rather than serving the previous payload.
	 *
	 * @covers ::remember
	 * @covers ::mark_changed
	 *
	 * @return void
	 */
	public function test_remember_rebuilds_after_the_calendar_is_marked_changed(): void {
		$instance = Cache::get_instance();
		$payload  = 'first';
		$renderer = static function () use ( &$payload ): string {
			return $payload;
		};

		$instance->remember( 'ics:test-b', $renderer );

		$payload = 'second';

		update_option( Cache::LAST_MODIFIED_OPTION, '2031-01-01 00:00:00', false );

		$this->assertSame(
			'second',
			$instance->remember( 'ics:test-b', $renderer ),
			'A stamped calendar should rebuild instead of serving the stale payload.'
		);
	}

	/**
	 * Filtering the max age to zero opts out of caching entirely.
	 *
	 * @covers ::get_max_age
	 * @covers ::remember
	 *
	 * @return void
	 */
	public function test_zero_max_age_disables_caching(): void {
		$instance = Cache::get_instance();
		$calls    = 0;
		$renderer = static function () use ( &$calls ): string {
			++$calls;

			return 'uncached';
		};

		add_filter( 'gatherpress_calendar_max_age', '__return_zero' );

		$max_age = $instance->get_max_age();

		$instance->remember( 'ics:test-c', $renderer );
		$instance->remember( 'ics:test-c', $renderer );

		remove_filter( 'gatherpress_calendar_max_age', '__return_zero' );

		$this->assertSame( 0, $max_age, 'The filter should be able to disable caching.' );
		$this->assertSame( 2, $calls, 'With caching off the renderer should run every time.' );
	}

	/**
	 * A negative max age is treated as zero rather than passed to the cache.
	 *
	 * @covers ::get_max_age
	 *
	 * @return void
	 */
	public function test_negative_max_age_is_clamped(): void {
		add_filter(
			'gatherpress_calendar_max_age',
			static function (): int {
				return -100;
			}
		);

		$max_age = Cache::get_instance()->get_max_age();

		remove_all_filters( 'gatherpress_calendar_max_age' );

		$this->assertSame( 0, $max_age, 'A negative max age should clamp to zero.' );
	}

	/**
	 * Saving an event stamps the calendar; saving an unrelated post does not.
	 *
	 * @covers ::mark_changed_for_post
	 * @covers ::is_calendar_post_type
	 *
	 * @return void
	 */
	public function test_mark_changed_for_post_only_fires_for_calendar_post_types(): void {
		$instance = Cache::get_instance();

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_post( $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID );

		$this->assertSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'A regular post should not stamp the calendar.'
		);

		$instance->mark_changed_for_post( $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID );

		$this->assertNotSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'An event should stamp the calendar.'
		);
	}

	/**
	 * A venue is part of a VEVENT's LOCATION, so venue edits stamp too.
	 *
	 * @covers ::mark_changed_for_post
	 * @covers ::is_calendar_post_type
	 *
	 * @return void
	 */
	public function test_mark_changed_for_post_fires_for_venues(): void {
		$instance = Cache::get_instance();

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_post( $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get()->ID );

		$this->assertNotSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'A venue edit should stamp the calendar.'
		);
	}

	/**
	 * GatherPress meta stamps the calendar; unrelated meta does not.
	 *
	 * @covers ::mark_changed_for_meta
	 *
	 * @return void
	 */
	public function test_mark_changed_for_meta_only_fires_for_gatherpress_keys(): void {
		$instance = Cache::get_instance();
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_meta( 1, $event_id, '_edit_lock' );

		$this->assertSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'Unrelated meta should not stamp the calendar.'
		);

		$instance->mark_changed_for_meta( 1, $event_id, 'gatherpress_datetime' );

		$this->assertNotSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'Datetime meta should stamp the calendar.'
		);
	}

	/**
	 * Term changes on an event stamp the calendar, but RSVP status changes,
	 * which travel on the same hook against a comment taxonomy, do not.
	 *
	 * @covers ::mark_changed_for_terms
	 *
	 * @return void
	 */
	public function test_mark_changed_for_terms_ignores_comment_taxonomies(): void {
		$instance = Cache::get_instance();
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_terms( 1, array(), array(), Status::TAXONOMY );

		$this->assertSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'An RSVP status change should not invalidate every calendar feed.'
		);

		$instance->mark_changed_for_terms( $event_id, array(), array(), Venue::TAXONOMY );

		$this->assertNotSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			"A change to an event's venue term should stamp the calendar."
		);
	}

	/**
	 * An unregistered taxonomy is ignored rather than stamping on a guess.
	 *
	 * @covers ::mark_changed_for_terms
	 *
	 * @return void
	 */
	public function test_mark_changed_for_terms_ignores_unknown_taxonomies(): void {
		$instance = Cache::get_instance();

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_terms( 1, array(), array(), 'not_a_taxonomy' );

		$this->assertSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'An unknown taxonomy should not stamp the calendar.'
		);
	}

	/**
	 * A change always advances the validator past the value a client may hold.
	 *
	 * `Last-Modified` has one-second resolution and it is what conditional
	 * requests are answered from. A stored validator at or ahead of the wall
	 * clock is exactly what a burst of same-second changes leaves behind, and a
	 * change that rewrites it with the same second answers the next
	 * `If-Modified-Since` with a 304 for a body the change just invalidated.
	 * The stored value ahead of the clock makes the requirement testable
	 * without a sleep: a write that only reports the clock can never satisfy
	 * it, whatever the timing.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_last_modified
	 *
	 * @return void
	 */
	public function test_a_change_advances_the_validator_even_when_the_clock_does_not(): void {
		$instance = Cache::get_instance();

		update_option( Cache::LAST_MODIFIED_OPTION, '2030-06-01 12:00:00', false );

		$instance->mark_changed();

		$first = $instance->get_last_modified();

		$this->assertGreaterThan(
			strtotime( '2030-06-01 12:00:00' ),
			strtotime( $first ),
			'A change must move the validator past the value a client may already hold.'
		);

		$instance->mark_changed();

		$this->assertGreaterThan(
			strtotime( $first ),
			strtotime( $instance->get_last_modified() ),
			'A second change must move it again, however close together the two land.'
		);
	}

	/**
	 * The change count builds on the row, not on a read a writer already made.
	 *
	 * `get_change_count() + 1` followed by a write is the interleave two
	 * concurrent cancellations produce: both read N, both write N + 1, and one
	 * increment is lost, so a feed request between them can serve a stale
	 * namespace. One process cannot run two writers, so the stale first read is
	 * forced through the option cache, which is the same seam a concurrent
	 * writer's snapshot lives behind. The allocator is invoked directly:
	 * through `mark_changed()` the validator write flushes the option caches
	 * first, which would clear the planted stale value before the read this
	 * test exists to starve.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_change_count
	 *
	 * @return void
	 */
	public function test_the_change_count_allocation_survives_a_stale_read(): void {
		global $wpdb;

		$instance = Cache::get_instance();

		update_option( Cache::CHANGE_COUNT_OPTION, 5, false );
		wp_cache_set( Cache::CHANGE_COUNT_OPTION, '3', 'options' );

		Utility::invoke_hidden_method( $instance, 'allocate_change_count' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT option_value FROM %i WHERE option_name = %s',
				$wpdb->options,
				Cache::CHANGE_COUNT_OPTION
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->assertSame(
			6,
			(int) $stored,
			'The allocation must increment the stored row rather than a stale read of it.'
		);
		$this->assertSame(
			6,
			$instance->get_change_count(),
			'And the stale cached copy must be dropped, or every namespaced key keeps using it.'
		);
	}

	/**
	 * A revision meta write does not stamp the calendar a second time.
	 *
	 * `mark_changed_for_occurrences()` advances the series' revision and then
	 * stamps once. The advance is `update_post_meta()` on a `gatherpress_`
	 * key of a calendar post type, so without a bail the meta hooks re-enter
	 * `mark_changed()` once per sibling, the change count moves two or three
	 * times per change, and its docblock's "strictly increasing count of
	 * changes" stops describing anything.
	 *
	 * @covers ::mark_changed_for_meta
	 *
	 * @return void
	 */
	public function test_a_revision_meta_write_does_not_stamp_the_calendar_again(): void {
		$instance = Cache::get_instance();
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$count    = $instance->get_change_count();

		// The production path: the revision advance writes its meta and the
		// meta hooks call back in.
		add_post_meta( $event_id, Revision::META_KEY, 123 );

		$this->assertSame(
			$count,
			$instance->get_change_count(),
			'The revision write is itself part of a stamp in progress and must not stamp again.'
		);

		// The direct call answers the same way.
		$instance->mark_changed_for_meta( 1, $event_id, Revision::META_KEY );

		$this->assertSame(
			$count,
			$instance->get_change_count(),
			'A revision key reaching the meta callback directly must bail too.'
		);
	}

	/**
	 * The validator is strictly monotonic under a frozen clock.
	 *
	 * The clock is an argument of the write, so freezing it is passing the
	 * same instant twice; no sleep and no race against the suite's own speed.
	 * Invoked directly both for that seam and because xdebug does not trace a
	 * private helper reached through a same-class delegation.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_last_modified
	 *
	 * @return void
	 */
	public function test_the_validator_is_strictly_monotonic_under_a_frozen_clock(): void {
		$instance = Cache::get_instance();

		update_option( Cache::LAST_MODIFIED_OPTION, '2026-05-05 10:10:10', false );

		Utility::invoke_hidden_method( $instance, 'advance_last_modified', array( '2026-05-05 10:10:10' ) );

		$this->assertSame(
			'2026-05-05 10:10:11',
			$instance->get_last_modified(),
			'With the clock frozen on the stored second, a change must still advance the validator.'
		);

		Utility::invoke_hidden_method( $instance, 'advance_last_modified', array( '2026-05-05 10:10:10' ) );

		$this->assertSame(
			'2026-05-05 10:10:12',
			$instance->get_last_modified(),
			'And the next change in the same frozen second advances it again.'
		);

		Utility::invoke_hidden_method( $instance, 'advance_last_modified', array( '2027-01-01 00:00:00' ) );

		$this->assertSame(
			'2027-01-01 00:00:00',
			$instance->get_last_modified(),
			'A clock ahead of the stored value wins, so the stamp tracks real time when it can.'
		);
	}

	/**
	 * The first stamp seeds the validator row rather than advancing nothing.
	 *
	 * @covers ::mark_changed
	 *
	 * @return void
	 */
	public function test_the_first_stamp_seeds_the_validator_row(): void {
		delete_option( Cache::LAST_MODIFIED_OPTION );

		Utility::invoke_hidden_method(
			Cache::get_instance(),
			'advance_last_modified',
			array( '2026-02-02 02:02:02' )
		);

		$this->assertSame(
			'2026-02-02 02:02:02',
			Cache::get_instance()->get_last_modified(),
			'A site with no stored validator gets the clock as its first one.'
		);
	}

	/**
	 * A corrupt stored validator is replaced by the clock, not by NULL.
	 *
	 * `DATE_ADD()` answers NULL for a value it cannot parse, NULL poisons
	 * `GREATEST()`, and without the fallback the row would be nulled and every
	 * later read reseeded from scratch.
	 *
	 * @covers ::mark_changed
	 *
	 * @return void
	 */
	public function test_a_corrupt_validator_value_is_replaced_by_the_clock(): void {
		update_option( Cache::LAST_MODIFIED_OPTION, 'not-a-timestamp', false );

		Utility::invoke_hidden_method(
			Cache::get_instance(),
			'advance_last_modified',
			array( '2026-03-03 03:03:03' )
		);

		$this->assertSame(
			'2026-03-03 03:03:03',
			Cache::get_instance()->get_last_modified(),
			'A stored value nothing can parse yields to the clock rather than to NULL.'
		);
	}

	/**
	 * The counter allocation is one self-contained SQL statement.
	 *
	 * A separate read is the window a concurrent writer interleaves through,
	 * so the property pinned here is the statement's shape: exactly one query
	 * touches the option, and it increments in place.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_change_count
	 *
	 * @return void
	 */
	public function test_the_change_count_allocation_is_one_atomic_statement(): void {
		global $wpdb;

		$instance = Cache::get_instance();

		update_option( Cache::CHANGE_COUNT_OPTION, 41, false );

		$before = count( (array) $wpdb->queries );

		Utility::invoke_hidden_method( $instance, 'allocate_change_count' );

		$touching = array_values(
			array_filter(
				array_slice( (array) $wpdb->queries, $before ),
				static function ( array $query ): bool {
					return str_contains( (string) $query[0], Cache::CHANGE_COUNT_OPTION );
				}
			)
		);

		$this->assertCount(
			1,
			$touching,
			'The allocation must be a single statement; a separate read is a window for a concurrent writer.'
		);
		$this->assertStringContainsString(
			'ON DUPLICATE KEY UPDATE',
			(string) $touching[0][0],
			'And that statement must increment in place rather than write a value computed in PHP.'
		);
		$this->assertSame( 42, $instance->get_change_count(), 'The allocated value is the row plus one.' );
	}

	/**
	 * The first allocation seeds the counter row at one.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_change_count
	 *
	 * @return void
	 */
	public function test_the_first_allocation_seeds_the_counter_row(): void {
		delete_option( Cache::CHANGE_COUNT_OPTION );

		Utility::invoke_hidden_method( Cache::get_instance(), 'allocate_change_count' );

		$this->assertSame(
			1,
			Cache::get_instance()->get_change_count(),
			'A site with no stored counter records its first change as one.'
		);
	}

	/**
	 * A count read before the first allocation does not stick at zero.
	 *
	 * Production is always in this order: a feed request reads the counter
	 * before any change has allocated it, and the miss writes the option into
	 * the `notoptions` memo, which is a persistent group on a site with an
	 * external object cache. The allocation writes the row by bare SQL and
	 * must drop that memo along with the per-key entries, or `get_option()`
	 * answers the default without touching the database on every later read,
	 * and the counter reads as zero forever while the row keeps incrementing.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_change_count
	 *
	 * @return void
	 */
	public function test_a_count_read_before_the_first_allocation_does_not_stick_at_zero(): void {
		$instance = Cache::get_instance();

		delete_option( Cache::CHANGE_COUNT_OPTION );
		wp_cache_delete( Cache::CHANGE_COUNT_OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		$this->assertSame(
			0,
			$instance->get_change_count(),
			'Failed to read before the first allocation; the memo state below would not be production\'s.'
		);

		$instance->mark_changed();

		$this->assertSame(
			1,
			$instance->get_change_count(),
			'An allocation must reach a reader that primed the missing-option memo before it.'
		);
	}

	/**
	 * The change counter moves the cache key inside one validator second.
	 *
	 * Separating two changes the one-second validator cannot is the whole
	 * reason the counter is part of the versioned key. With the validator
	 * pinned to one value, only the counter can move the key, so this is the
	 * test that fails if the counter's contribution is ever dropped from
	 * `get_versioned_key()`, which every counter-value test would survive.
	 *
	 * @covers ::get_versioned_key
	 * @covers ::mark_changed
	 *
	 * @return void
	 */
	public function test_the_change_counter_moves_the_cache_key_inside_one_validator_second(): void {
		$instance = Cache::get_instance();
		$frozen   = '2026-01-01 00:00:00';

		delete_option( Cache::CHANGE_COUNT_OPTION );
		wp_cache_delete( Cache::CHANGE_COUNT_OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		update_option( Cache::LAST_MODIFIED_OPTION, $frozen, false );

		$first = $instance->get_versioned_key( 'feed' );

		$instance->mark_changed();

		// Pin the validator back to the pre-change value: the stamp has
		// one-second resolution, and this is the state two changes inside one
		// second leave a reader in.
		update_option( Cache::LAST_MODIFIED_OPTION, $frozen, false );

		$this->assertSame(
			$frozen,
			$instance->get_last_modified(),
			'Failed to pin the validator, so the assertion below would not isolate the counter.'
		);
		$this->assertNotSame(
			$first,
			$instance->get_versioned_key( 'feed' ),
			'With the validator standing still, the counter is the only thing that can move the key,'
			. ' and it must.'
		);
	}

	/**
	 * A second database connection cannot write beside the open allocation.
	 *
	 * Two real connections, which is what two concurrent requests are. The
	 * first allocates and holds its transaction open; the second then tries
	 * the same in-place increment and must wait on the row lock. What this
	 * proves is the serialization of the write; that the read cannot happen
	 * beside it is the pinned-SQL test's half, which proves the read and the
	 * write are one statement, so the read happens under the same lock. The
	 * suite wraps each test in a transaction on the main connection, so the
	 * main connection plays the open-transaction writer and the second
	 * connection plays the one that has to wait.
	 *
	 * The seed row is committed from the second connection so both can see
	 * it, and is removed at the start rather than the end: the main
	 * connection's lock outlives the test body, so a trailing delete would
	 * deadlock the cleanup it exists to do.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_change_count
	 *
	 * @return void
	 */
	public function test_two_connections_cannot_interleave_the_counter_allocation(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.RestrictedFunctions -- Two concurrent
		// writers are two connections; `$wpdb` can only ever be one of them.
		$instance = Cache::get_instance();
		$second   = mysqli_connect( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );

		$this->assertNotFalse( $second, 'The fixture needs a second database connection.' );

		$table = $wpdb->options;
		$name  = Cache::CHANGE_COUNT_OPTION;

		try {
			// Committed cleanup from any earlier run, then a committed seed
			// both connections can see.
			mysqli_query( $second, "DELETE FROM {$table} WHERE option_name = '{$name}'" );
			mysqli_query(
				$second,
				"INSERT INTO {$table} ( option_name, option_value, autoload ) VALUES ( '{$name}', '10', 'off' )"
			);
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'notoptions', 'options' );

			// Writer A allocates inside its still-open transaction.
			Utility::invoke_hidden_method( $instance, 'allocate_change_count' );

			$this->assertSame( 11, $instance->get_change_count(), 'Writer A must see its own allocation.' );

			// Writer B must block on A's row lock, not read beside it. One
			// second is the smallest timeout the server allows; the block
			// itself is what is being proven.
			mysqli_query( $second, 'SET SESSION innodb_lock_wait_timeout = 1' );

			$result = mysqli_query(
				$second,
				"UPDATE {$table} SET option_value = CAST( option_value AS SIGNED ) + 1"
				. " WHERE option_name = '{$name}'"
			);

			$this->assertFalse(
				$result,
				'A concurrent write on the allocation row must wait for the open one.'
			);
			$this->assertStringContainsString(
				'Lock wait timeout',
				mysqli_error( $second ),
				'And what stops it must be the row lock, not some other failure.'
			);
		} finally {
			mysqli_close( $second );
		}
		// phpcs:enable WordPress.DB.RestrictedFunctions
	}
}
