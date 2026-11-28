<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Occurrence_Index.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Occurrence_Index;
use GatherPress\Core\Event\Occurrences;
use GatherPress\Core\Event\Rules;
use GatherPress\Core\Setup as Core_Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Occurrence_Index.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Occurrence_Index
 */
class Test_Occurrence_Index extends Base {

	/**
	 * Start each test with an empty index and nothing waiting to rebuild, since both can outlive another test.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Occurrence_Index::table() ) );
		update_option( Occurrence_Index::SCHEMA_OPTION, Occurrence_Index::SCHEMA_VERSION );
		Utility::set_and_get_hidden_property( Occurrence_Index::get_instance(), 'pending', array() );
	}

	/**
	 * A local date some days from now, inside the horizon.
	 *
	 * @since TBD
	 *
	 * @param int $days Days from today.
	 *
	 * @return DateTimeImmutable The date in New York.
	 */
	protected function soon( int $days ): DateTimeImmutable {
		return new DateTimeImmutable( sprintf( 'today +%d days', $days ), new DateTimeZone( 'America/New_York' ) );
	}

	/**
	 * Create an event.
	 *
	 * @since TBD
	 *
	 * @param string $status The post status.
	 *
	 * @return int The post ID.
	 */
	protected function event( string $status = 'draft' ): int {
		return $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => $status,
			)
		);
	}

	/**
	 * Save an event's dates.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $start   Local start.
	 * @param string $end     Local end.
	 *
	 * @return void
	 */
	protected function save_dates( int $post_id, string $start, string $end ): void {
		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'America/New_York',
			)
		);
	}

	/**
	 * A weekly rule row.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $changes Fields to replace or add.
	 *
	 * @return array<string, mixed> The row.
	 */
	protected function row( array $changes = array() ): array {
		return array_merge(
			array(
				'starts_on' => '2026-01-05',
				'rrule'     => 'FREQ=WEEKLY',
				'times'     => array( '19:00' ),
				'timezone'  => 'America/New_York',
				'duration'  => 90,
			),
			$changes
		);
	}

	/**
	 * An event's index rows, earliest first.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return array<int, array<string, string>> The rows.
	 */
	protected function rows( int $post_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE post_id = %d ORDER BY datetime_start_gmt',
				Occurrence_Index::table(),
				$post_id
			),
			ARRAY_A
		);
	}

	/**
	 * An event's index keys, earliest first.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return string[] The keys.
	 */
	protected function keys( int $post_id ): array {
		return array_column( $this->rows( $post_id ), 'recurrence_id' );
	}

	/**
	 * The index's hooks.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Occurrence_Index::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'maybe_install' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'schedule_extend' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_event_datetimes_saved',
				'priority' => 10,
				'callback' => array( $instance, 'rebuild' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post',
				'priority' => 10,
				'callback' => array( $instance, 'delete' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'handle_rule_meta_change' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'handle_rule_meta_change' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'handle_rule_meta_change' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'shutdown',
				'priority' => 10,
				'callback' => array( $instance, 'rebuild_pending' ),
			),
			array(
				'type'     => 'action',
				'name'     => Occurrence_Index::CRON_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'extend' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The table is named from the site's prefix.
	 *
	 * @since TBD
	 *
	 * @covers ::table
	 *
	 * @return void
	 */
	public function test_table(): void {
		global $wpdb;

		$this->assertSame( $wpdb->prefix . 'gatherpress_occurrence_index', Occurrence_Index::table() );
	}

	/**
	 * Installing builds the table and copies existing events in, then does nothing until the schema changes.
	 *
	 * @since TBD
	 *
	 * @covers ::maybe_install
	 * @covers ::is_ready
	 * @covers ::create_table
	 * @covers ::copy_events
	 * @covers ::expand_repeating
	 * @covers ::lock
	 *
	 * @return void
	 */
	public function test_maybe_install(): void {
		global $wpdb;

		$index = Occurrence_Index::get_instance();

		delete_option( Occurrence_Index::SCHEMA_OPTION );
		$this->assertFalse( $index->is_ready() );

		$timed   = $this->event( 'publish' );
		$all_day = $this->event( 'publish' );
		$series  = $this->event();
		$running = $this->event();
		$started = $this->soon( -21 );

		$this->save_dates( $timed, '2026-12-11 20:00:00', '2026-12-11 22:00:00' );
		update_post_meta( $all_day, 'gatherpress_is_all_day', true );
		$this->save_dates( $all_day, '2026-12-12 00:00:00', '2026-12-12 23:59:59' );
		( new Event( $series ) )->rules()->replace( array( $this->row( array( 'starts_on' => '2099-01-05' ) ) ) );
		$weekly = array( $this->row( array( 'starts_on' => $started->format( 'Y-m-d' ) ) ) );

		( new Event( $running ) )->rules()->replace( $weekly );
		wp_update_post(
			array(
				'ID'          => $running,
				'post_status' => 'publish',
			)
		);

		// Nothing is indexed until the index is built.
		$this->assertSame( array(), $this->rows( $timed ) );

		$ready_while_expanding = array();
		$watch                 = static function ( array $occurrences ) use ( $index, &$ready_while_expanding ): array {
			$ready_while_expanding[] = $index->is_ready();

			return $occurrences;
		};

		add_filter( 'gatherpress_event_occurrences', $watch );
		$index->maybe_install();
		remove_filter( 'gatherpress_event_occurrences', $watch );

		$this->assertNotEmpty( $ready_while_expanding );
		$this->assertNotContains( true, $ready_while_expanding, 'Not ready until every series is expanded.' );

		$this->assertTrue( $index->is_ready() );
		$this->assertSame(
			array(
				'post_id'            => (string) $timed,
				'recurrence_id'      => '20261211T200000',
				'datetime_start'     => '2026-12-11 20:00:00',
				'datetime_start_gmt' => '2026-12-12 01:00:00',
				'datetime_end'       => '2026-12-11 22:00:00',
				'datetime_end_gmt'   => '2026-12-12 03:00:00',
				'timezone'           => 'America/New_York',
			),
			$this->rows( $timed )[0]
		);
		$this->assertSame( array( '20261212' ), $this->keys( $all_day ), 'An all-day event has a date key.' );
		$this->assertSame( array( '20990105T190000' ), $this->keys( $series ), 'A first date past the horizon.' );

		$keys = $this->keys( $running );

		$this->assertSame(
			array(
				$started->format( 'Ymd' ) . 'T190000',
				$started->modify( '+7 days' )->format( 'Ymd' ) . 'T190000',
				$started->modify( '+14 days' )->format( 'Ymd' ) . 'T190000',
			),
			array_slice( $keys, 0, 3 ),
			'A series that started is expanded from its first date, past dates included.'
		);
		$this->assertGreaterThanOrEqual( 55, count( $keys ), 'Three past weeks and a year ahead.' );
		$this->assertFalse( get_option( Occurrence_Index::LOCK_OPTION ), 'The lock is released.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Occurrence_Index::table() ) );
		$index->maybe_install();

		$this->assertSame( array(), $this->rows( $timed ), 'A built index is not built again.' );
	}

	/**
	 * Saving an event's dates rebuilds its rows, and an event with rules gets a row per date up to the horizon.
	 *
	 * @since TBD
	 *
	 * @covers ::rebuild
	 * @covers ::rebuild_from
	 * @covers ::insert
	 * @covers ::insert_rows
	 * @covers ::utc
	 *
	 * @return void
	 */
	public function test_rebuild(): void {
		$one_off = $this->event( 'publish' );

		$this->save_dates( $one_off, '2026-12-11 20:00:00', '2026-12-11 22:00:00' );
		$this->save_dates( $one_off, '2026-12-18 20:00:00', '2026-12-18 22:00:00' );

		$this->assertSame( array( '20261218T200000' ), $this->keys( $one_off ), 'A new date replaces the old row.' );

		$far = $this->event( 'publish' );

		$this->save_dates( $far, '2040-06-01 20:00:00', '2040-06-01 22:00:00' );

		$this->assertSame( array( '20400601T200000' ), $this->keys( $far ), 'Indexed past the horizon.' );

		$weekly   = $this->event();
		$tomorrow = new DateTimeImmutable( 'tomorrow', new DateTimeZone( 'America/New_York' ) );

		$rows = array( $this->row( array( 'starts_on' => $tomorrow->format( 'Y-m-d' ) ) ) );

		( new Event( $weekly ) )->rules()->replace( $rows );

		$keys = $this->keys( $weekly );

		$this->assertSame( $tomorrow->format( 'Ymd' ) . 'T190000', $keys[0] );
		$this->assertGreaterThanOrEqual( 52, count( $keys ), 'A year of weekly dates.' );
		$this->assertLessThanOrEqual( 54, count( $keys ), 'Nothing past the horizon.' );
		$this->assertSame( count( $keys ), count( array_unique( $keys ) ), 'Each date once, though months overlap.' );
	}

	/**
	 * A published event with rules keeps its past rows when rebuilt.
	 *
	 * @since TBD
	 *
	 * @covers ::rebuild
	 * @covers ::rebuild_from
	 * @covers ::delete
	 *
	 * @return void
	 */
	public function test_rebuild_keeps_the_past_of_a_published_series(): void {
		global $wpdb;

		$post_id = $this->event();

		( new Event( $post_id ) )->rules()->replace( array( $this->row() ) );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$past = count( $this->rows( $post_id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			Occurrence_Index::table(),
			array( 'timezone' => 'Marked' ),
			array(
				'post_id'       => $post_id,
				'recurrence_id' => '20260105T190000',
			)
		);
		Occurrence_Index::get_instance()->rebuild( $post_id );

		$this->assertSame( 'Marked', $this->rows( $post_id )[0]['timezone'], 'A past row is left as it was.' );
		$this->assertSame( $past, count( $this->rows( $post_id ) ), 'Dates from today on are rebuilt, not doubled.' );
	}

	/**
	 * A rebuild does nothing before the index exists, and clears the rows of a post that isn't an event.
	 *
	 * @since TBD
	 *
	 * @covers ::rebuild
	 *
	 * @return void
	 */
	public function test_rebuild_edges(): void {
		$index   = Occurrence_Index::get_instance();
		$post_id = $this->event( 'publish' );

		$this->save_dates( $post_id, '2026-12-11 20:00:00', '2026-12-11 22:00:00' );
		delete_option( Occurrence_Index::SCHEMA_OPTION );
		$this->save_dates( $post_id, '2026-12-18 20:00:00', '2026-12-18 22:00:00' );

		$this->assertSame( array( '20261211T200000' ), $this->keys( $post_id ), 'Not rebuilt before it is built.' );

		update_option( Occurrence_Index::SCHEMA_OPTION, Occurrence_Index::SCHEMA_VERSION );
		set_post_type( $post_id, 'post' );
		$index->rebuild( $post_id );

		$this->assertSame( array(), $this->rows( $post_id ), 'A post that is no longer an event has no rows.' );
	}

	/**
	 * Rule meta written outside a rule write, such as by an import, rebuilds at shutdown.
	 *
	 * @since TBD
	 *
	 * @covers ::handle_rule_meta_change
	 * @covers ::rebuild_pending
	 *
	 * @return void
	 */
	public function test_rule_meta_written_directly(): void {
		$index   = Occurrence_Index::get_instance();
		$post_id = $this->event();

		$start = $this->soon( 7 );

		$this->save_dates( $post_id, $start->format( 'Y-m-d' ) . ' 19:00:00', $start->format( 'Y-m-d' ) . ' 20:30:00' );
		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		add_post_meta( $post_id, Rules::META_KEY, $this->row( array( 'starts_on' => $start->format( 'Y-m-d' ) ) ) );
		add_post_meta( $post_id, 'gatherpress_other', 'x' );

		$this->assertCount( 1, $this->keys( $post_id ), 'Not rebuilt until shutdown.' );
		$this->assertSame(
			array( get_current_blog_id() => array( $post_id => true ) ),
			Utility::get_hidden_property( $index, 'pending' ),
			'Only the rule key marks the event, under its site.'
		);

		$index->rebuild_pending();

		$second = $start->modify( '+7 days' )->format( 'Ymd' ) . 'T190000';

		$this->assertSame( $second, $this->keys( $post_id )[1], 'The rule is expanded.' );
		$this->assertSame( array(), Utility::get_hidden_property( $index, 'pending' ) );
	}

	/**
	 * The daily run rebuilds every event with rules.
	 *
	 * @since TBD
	 *
	 * @covers ::extend
	 *
	 * @return void
	 */
	public function test_extend(): void {
		global $wpdb;

		$post_id = $this->event();

		( new Event( $post_id ) )->rules()->replace( array( $this->row( array( 'starts_on' => '2099-01-05' ) ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE post_id = %d', Occurrence_Index::table(), $post_id ) );

		Occurrence_Index::get_instance()->extend();

		$this->assertSame( '20990105T190000', $this->keys( $post_id )[0] );
	}

	/**
	 * The daily run is scheduled once the index exists, and only once.
	 *
	 * @since TBD
	 *
	 * @covers ::schedule_extend
	 *
	 * @return void
	 */
	public function test_schedule_extend(): void {
		$index = Occurrence_Index::get_instance();

		wp_clear_scheduled_hook( Occurrence_Index::CRON_ACTION );
		delete_option( Occurrence_Index::SCHEMA_OPTION );
		$index->schedule_extend();

		$this->assertFalse( wp_next_scheduled( Occurrence_Index::CRON_ACTION ), 'Not before the index exists.' );

		update_option( Occurrence_Index::SCHEMA_OPTION, Occurrence_Index::SCHEMA_VERSION );
		$index->schedule_extend();
		$scheduled = wp_next_scheduled( Occurrence_Index::CRON_ACTION );
		$index->schedule_extend();

		$this->assertIsInt( $scheduled );
		$this->assertSame( $scheduled, wp_next_scheduled( Occurrence_Index::CRON_ACTION ), 'Scheduled once.' );

		wp_clear_scheduled_hook( Occurrence_Index::CRON_ACTION );
	}

	/**
	 * Deleting an event deletes its rows; a moment limits a delete to rows from then on.
	 *
	 * @since TBD
	 *
	 * @covers ::delete
	 *
	 * @return void
	 */
	public function test_delete(): void {
		$index   = Occurrence_Index::get_instance();
		$post_id = $this->event();

		$start = $this->soon( 7 );

		$rows = array( $this->row( array( 'starts_on' => $start->format( 'Y-m-d' ) ) ) );

		( new Event( $post_id ) )->rules()->replace( $rows );
		$index->delete( $post_id, $start->modify( '+8 days' )->setTimezone( new DateTimeZone( 'UTC' ) ) );

		$this->assertSame(
			array( $start->format( 'Ymd' ) . 'T190000', $start->modify( '+7 days' )->format( 'Ymd' ) . 'T190000' ),
			$this->keys( $post_id )
		);

		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		wp_delete_post( $post_id, true );

		$this->assertSame( array(), $this->rows( $post_id ), 'Deleting the event deletes its rows.' );
	}

	/**
	 * The horizon defaults to 12 months and can be changed, but not below one.
	 *
	 * @since TBD
	 *
	 * @covers ::horizon
	 *
	 * @return void
	 */
	public function test_horizon(): void {
		$index = Occurrence_Index::get_instance();
		$now   = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$this->assertSame( $now->modify( '+12 months' )->format( 'Y-m-d' ), $index->horizon()->format( 'Y-m-d' ) );

		$months = static fn(): int => 0;

		add_filter( 'gatherpress_occurrence_index_horizon', $months );

		$this->assertSame( $now->modify( '+1 months' )->format( 'Y-m-d' ), $index->horizon()->format( 'Y-m-d' ) );

		remove_filter( 'gatherpress_occurrence_index_horizon', $months );
	}

	/**
	 * A build already running elsewhere is left alone, and one abandoned is taken over.
	 *
	 * @since TBD
	 *
	 * @covers ::maybe_install
	 * @covers ::lock
	 *
	 * @return void
	 */
	public function test_install_lock(): void {
		$index = Occurrence_Index::get_instance();

		delete_option( Occurrence_Index::SCHEMA_OPTION );
		add_option( Occurrence_Index::LOCK_OPTION, (string) time(), '', false );
		$index->maybe_install();

		$this->assertFalse( $index->is_ready(), 'Another request holds the lock.' );

		update_option( Occurrence_Index::LOCK_OPTION, (string) ( time() - Occurrence_Index::LOCK_TIMEOUT - 1 ), false );
		$index->maybe_install();

		$this->assertTrue( $index->is_ready(), 'An abandoned lock is taken over.' );
		$this->assertFalse( get_option( Occurrence_Index::LOCK_OPTION ) );
	}

	/**
	 * A published series fills a gap left while the cron didn't run.
	 *
	 * @since TBD
	 *
	 * @covers ::rebuild
	 * @covers ::rebuild_from
	 *
	 * @return void
	 */
	public function test_rebuild_fills_a_gap(): void {
		$index   = Occurrence_Index::get_instance();
		$post_id = $this->event();
		$start   = $this->soon( -28 );

		$weekly = array( $this->row( array( 'starts_on' => $start->format( 'Y-m-d' ) ) ) );

		( new Event( $post_id ) )->rules()->replace( $weekly );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$full = $this->keys( $post_id );

		// As if indexing stopped two weeks ago.
		$index->delete( $post_id, $start->modify( '+8 days' )->setTimezone( new DateTimeZone( 'UTC' ) ) );
		$index->rebuild( $post_id );

		$this->assertSame( $full, $this->keys( $post_id ), 'Every date from the last indexed one on is back.' );
	}

	/**
	 * A daily rule with several showtimes stays under the read cap, a month at a time.
	 *
	 * @since TBD
	 *
	 * @covers ::insert
	 *
	 * @return void
	 */
	public function test_many_showtimes(): void {
		$post_id = $this->event();
		$rows    = array(
			$this->row(
				array(
					'starts_on' => $this->soon( 1 )->format( 'Y-m-d' ),
					'rrule'     => 'FREQ=DAILY',
					'times'     => array( '09:00', '13:00', '19:00' ),
				)
			),
		);

		( new Event( $post_id ) )->rules()->replace( $rows );

		$this->assertGreaterThanOrEqual( 1080, count( $this->keys( $post_id ) ), 'Three a day for a year.' );
	}

	/**
	 * A month with more dates than the read cap is read in pieces, so none are lost.
	 *
	 * @since TBD
	 *
	 * @covers ::insert
	 * @covers ::next_cursor
	 *
	 * @return void
	 */
	public function test_more_dates_than_the_cap(): void {
		$post_id = $this->event();
		$times   = array();

		for ( $minutes = 0; $minutes < 1440; $minutes += 30 ) {
			$times[] = sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
		}

		$one_month = static fn(): int => 1;

		add_filter( 'gatherpress_occurrence_index_horizon', $one_month );
		( new Event( $post_id ) )->rules()->replace(
			array(
				$this->row(
					array(
						'starts_on' => $this->soon( 1 )->format( 'Y-m-d' ),
						'rrule'     => 'FREQ=DAILY',
						'times'     => $times,
						'duration'  => 20,
					)
				),
			)
		);
		remove_filter( 'gatherpress_occurrence_index_horizon', $one_month );

		$this->assertGreaterThanOrEqual( 48 * 27, count( $this->keys( $post_id ) ), '48 a day for about a month.' );
	}

	/**
	 * A failed copy leaves the index unready and keeps the lock, so the next try waits out the timeout.
	 *
	 * @since TBD
	 *
	 * @covers ::maybe_install
	 * @covers ::copy_events
	 *
	 * @return void
	 */
	public function test_install_after_a_failed_copy(): void {
		global $wpdb;

		$index = Occurrence_Index::get_instance();
		$break = static fn( string $query ): string => str_contains( $query, 'SELECT e.post_id' ) ? 'NOT SQL' : $query;

		delete_option( Occurrence_Index::SCHEMA_OPTION );
		delete_option( Occurrence_Index::LOCK_OPTION );
		add_filter( 'query', $break );
		$suppressed = $wpdb->suppress_errors( true );
		$index->maybe_install();
		$wpdb->suppress_errors( $suppressed );
		remove_filter( 'query', $break );

		$this->assertFalse( $index->is_ready() );
		$this->assertNotFalse( get_option( Occurrence_Index::LOCK_OPTION ), 'The lock stays until it times out.' );
		$this->assertFalse( Utility::get_hidden_property( $index, 'building' ) );
	}

	/**
	 * The helpers, called directly for each of their paths.
	 *
	 * @since TBD
	 *
	 * @covers ::lock
	 * @covers ::copy_events
	 * @covers ::expand_repeating
	 * @covers ::rebuild_from
	 * @covers ::insert
	 * @covers ::insert_rows
	 * @covers ::next_cursor
	 * @covers ::utc
	 *
	 * @return void
	 */
	public function test_helpers(): void {
		$index = Occurrence_Index::get_instance();

		delete_option( Occurrence_Index::LOCK_OPTION );
		$this->assertTrue( Utility::invoke_hidden_method( $index, 'lock' ), 'A free lock.' );
		$this->assertFalse( Utility::invoke_hidden_method( $index, 'lock' ), 'A held lock.' );
		update_option( Occurrence_Index::LOCK_OPTION, (string) ( time() - Occurrence_Index::LOCK_TIMEOUT - 1 ), false );
		$this->assertTrue( Utility::invoke_hidden_method( $index, 'lock' ), 'An abandoned lock.' );
		delete_option( Occurrence_Index::LOCK_OPTION );

		$this->assertTrue( Utility::invoke_hidden_method( $index, 'copy_events' ), 'Nothing to copy is still a copy.' );

		$draft  = $this->event();
		$series = $this->event( 'publish' );

		$this->assertNull( Utility::invoke_hidden_method( $index, 'rebuild_from', array( new Event( $draft ) ) ) );
		$this->assertNull(
			Utility::invoke_hidden_method( $index, 'rebuild_from', array( new Event( $series ) ) ),
			'No rules.'
		);

		( new Event( $series ) )->rules()->replace(
			array( $this->row( array( 'starts_on' => $this->soon( 1 )->format( 'Y-m-d' ) ) ) )
		);
		$index->delete( $series );

		$this->assertNull(
			Utility::invoke_hidden_method( $index, 'rebuild_from', array( new Event( $series ) ) ),
			'Not indexed yet.'
		);

		Utility::invoke_hidden_method( $index, 'expand_repeating' );

		$this->assertEquals(
			$this->utc_today(),
			Utility::invoke_hidden_method( $index, 'rebuild_from', array( new Event( $series ) ) ),
			'From today once indexed ahead.'
		);

		Utility::invoke_hidden_method( $index, 'insert_rows', array( array() ) );
		$utc   = Utility::invoke_hidden_method( $index, 'utc', array( '2026-12-01 10:00:00' ) );
		$later = $utc->modify( '+1 day' );

		$this->assertSame( 'UTC', $utc->getTimezone()->getName() );
		$this->assertSame(
			$later,
			Utility::invoke_hidden_method( $index, 'next_cursor', array( array(), $utc, $later ) ),
			'A short batch moves to the range end.'
		);

		$event = new Event( $series );
		$first = $event->occurrences()->in_range( $this->soon( 1 ), $this->soon( 9 ) )[0];
		$full  = array_fill( 0, Occurrences::MAX_OCCURRENCES, $first );
		$start = $first->start->setTimezone( new DateTimeZone( 'UTC' ) );

		$this->assertEquals(
			$start,
			Utility::invoke_hidden_method( $index, 'next_cursor', array( $full, $start->modify( '-1 day' ), $later ) ),
			'A full batch continues from its last date.'
		);
		$this->assertSame(
			$later,
			Utility::invoke_hidden_method( $index, 'next_cursor', array( $full, $start, $later ) ),
			"A full batch that can't move forward moves to the range's end."
		);

		$index->delete( $series );
		Utility::invoke_hidden_method( $index, 'insert', array( $event, $this->soon( 1 ), $this->soon( 9 ) ) );

		$this->assertCount( 2, $this->keys( $series ), 'Two weekly dates in eight days.' );
	}

	/**
	 * Midnight today, in UTC.
	 *
	 * @since TBD
	 *
	 * @return DateTimeImmutable The moment.
	 */
	protected function utc_today(): DateTimeImmutable {
		return new DateTimeImmutable( 'today', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * A rebuild queued inside switch_to_blog() runs on the site the meta was written on.
	 *
	 * @since TBD
	 *
	 * @group multisite
	 *
	 * @covers ::handle_rule_meta_change
	 * @covers ::rebuild_pending
	 *
	 * @return void
	 */
	public function test_pending_rebuild_on_another_site(): void {
		$index   = Occurrence_Index::get_instance();
		$site_id = $this->factory()->blog->create();
		$start   = $this->soon( 7 );

		switch_to_blog( $site_id );
		// The plugin isn't network-active here, so the new site's tables are created as activation would.
		Utility::invoke_hidden_method( Core_Setup::get_instance(), 'create_tables' );
		$index->maybe_install();

		$post_id = $this->event();

		$this->save_dates( $post_id, $start->format( 'Y-m-d' ) . ' 19:00:00', $start->format( 'Y-m-d' ) . ' 20:30:00' );
		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		add_post_meta( $post_id, Rules::META_KEY, $this->row( array( 'starts_on' => $start->format( 'Y-m-d' ) ) ) );
		restore_current_blog();

		$index->rebuild_pending();
		switch_to_blog( $site_id );
		$keys = $this->keys( $post_id );
		restore_current_blog();

		$second = $start->modify( '+7 days' )->format( 'Ymd' ) . 'T190000';

		$this->assertSame( $second, $keys[1], 'Rebuilt on its own site.' );
	}
}
