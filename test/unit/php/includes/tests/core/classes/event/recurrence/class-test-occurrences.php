<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Occurrences.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Tests\Base;

/**
 * Class Test_Occurrences.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Occurrences
 */
class Test_Occurrences extends Base {

	/**
	 * Create an event post.
	 *
	 * @since TBD
	 *
	 * @return int The post ID.
	 */
	protected function create_event(): int {
		return $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * The occurrence rows of a post.
	 *
	 * @since TBD
	 *
	 * @param int $post_id A post ID.
	 *
	 * @return array<int, array<string, string>> The rows.
	 */
	protected function rows( int $post_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading the table under test.
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE series_post_id = %d', Occurrences::table(), $post_id ),
			ARRAY_A
		);
	}

	/**
	 * The class hooks into post deletion.
	 *
	 * @since TBD
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
				'name'     => 'delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'delete_for_post' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The table name carries the current site's prefix.
	 *
	 * @since TBD
	 *
	 * @covers ::table
	 *
	 * @return void
	 */
	public function test_table(): void {
		global $wpdb;

		$this->assertSame( $wpdb->prefix . 'gatherpress_event_occurrences', Occurrences::table() );
	}

	/**
	 * The schema matches the columns and keys the recurrence work builds on.
	 *
	 * @since TBD
	 *
	 * @covers ::schema
	 *
	 * @return void
	 */
	public function test_schema(): void {
		$schema = Occurrences::schema();

		$this->assertStringContainsString( 'CREATE TABLE ' . Occurrences::table() . ' (', $schema );
		$this->assertStringContainsString( 'override_post_id bigint(20) unsigned NOT NULL default \'0\'', $schema );
		$this->assertStringContainsString( 'PRIMARY KEY  (series_post_id,recurrence_id)', $schema );
		$this->assertStringContainsString(
			'KEY series_status_start (series_post_id,status,datetime_start_gmt)',
			$schema
		);
	}

	/**
	 * An occurrence identifier is its local start, with no offset.
	 *
	 * @since TBD
	 *
	 * @covers ::recurrence_id
	 *
	 * @return void
	 */
	public function test_recurrence_id(): void {
		$start = new DateTimeImmutable( '2026-12-05 18:30:00', new DateTimeZone( 'America/New_York' ) );

		$this->assertSame( '20261205T183000', Occurrences::recurrence_id( $start ) );
	}

	/**
	 * Saving an event's dates writes its single occurrence.
	 *
	 * @since TBD
	 *
	 * @covers ::sync_single
	 * @covers \GatherPress\Core\Event::save_datetimes
	 *
	 * @return void
	 */
	public function test_sync_single_writes_one_row_on_save(): void {
		$post_id = $this->create_event();

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2026-12-05 18:00:00',
				'datetime_end'   => '2026-12-05 20:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$rows = $this->rows( $post_id );

		$this->assertCount( 1, $rows, 'An event without a repeat rule has one occurrence.' );
		$this->assertSame( '20261205T180000', $rows[0]['recurrence_id'] );
		$this->assertSame( '2026-12-05 18:00:00', $rows[0]['datetime_start'] );
		$this->assertSame( '2026-12-05 23:00:00', $rows[0]['datetime_start_gmt'] );
		$this->assertSame( '2026-12-05 20:00:00', $rows[0]['datetime_end'] );
		$this->assertSame( '2026-12-06 01:00:00', $rows[0]['datetime_end_gmt'] );
		$this->assertSame( 'America/New_York', $rows[0]['timezone'] );
		$this->assertSame( Occurrences::STATUS_SCHEDULED, $rows[0]['status'] );
		$this->assertSame( '0', $rows[0]['override_post_id'] );
	}

	/**
	 * A new start replaces the occurrence rather than adding a second one.
	 *
	 * @since TBD
	 *
	 * @covers ::sync_single
	 *
	 * @return void
	 */
	public function test_sync_single_replaces_the_row_when_dates_change(): void {
		$post_id = $this->create_event();
		$event   = new Event( $post_id );

		$event->save_datetimes(
			array(
				'datetime_start' => '2026-12-05 18:00:00',
				'datetime_end'   => '2026-12-05 20:00:00',
				'timezone'       => 'America/New_York',
			)
		);
		$event->save_datetimes(
			array(
				'datetime_start' => '2026-12-12 19:00:00',
				'datetime_end'   => '2026-12-12 21:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$rows = $this->rows( $post_id );

		$this->assertCount( 1, $rows, 'Moving an event keeps one occurrence.' );
		$this->assertSame( '20261212T190000', $rows[0]['recurrence_id'] );
	}

	/**
	 * An event without a start has no occurrence, and a bad post ID writes nothing.
	 *
	 * @since TBD
	 *
	 * @covers ::sync_single
	 *
	 * @return void
	 */
	public function test_sync_single_skips_missing_input(): void {
		$post_id = $this->create_event();

		Occurrences::get_instance()->sync_single(
			array(
				'post_id'        => $post_id,
				'datetime_start' => '2026-12-05 18:00:00',
				'timezone'       => 'America/New_York',
			)
		);
		Occurrences::get_instance()->sync_single(
			array(
				'post_id'        => $post_id,
				'datetime_start' => '',
			)
		);

		$this->assertCount( 0, $this->rows( $post_id ), 'Clearing the start removes the occurrence.' );

		Occurrences::get_instance()->sync_single( array( 'datetime_start' => '2026-12-05 18:00:00' ) );

		$this->assertCount( 0, $this->rows( 0 ), 'No row is written without a post ID.' );
	}

	/**
	 * A start that does not parse writes no row.
	 *
	 * @since TBD
	 *
	 * @covers ::sync_single
	 *
	 * @return void
	 */
	public function test_sync_single_skips_an_unparsable_start(): void {
		$post_id = $this->create_event();

		Occurrences::get_instance()->sync_single(
			array(
				'post_id'        => $post_id,
				'datetime_start' => 'not a date',
				'timezone'       => 'America/New_York',
			)
		);

		$this->assertCount( 0, $this->rows( $post_id ) );
	}

	/**
	 * Deleting an event removes its occurrences, and other post types are left alone.
	 *
	 * @since TBD
	 *
	 * @covers ::delete_for_post
	 *
	 * @return void
	 */
	public function test_delete_for_post(): void {
		$post_id = $this->create_event();

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2026-12-05 18:00:00',
				'datetime_end'   => '2026-12-05 20:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$this->assertCount( 1, $this->rows( $post_id ) );

		wp_delete_post( $post_id, true );

		$this->assertCount( 0, $this->rows( $post_id ), 'Deleting the event removes its occurrence.' );

		global $wpdb;

		$page_id = $this->factory()->post->create( array( 'post_type' => 'page' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Seeding the table under test.
		$wpdb->insert(
			Occurrences::table(),
			array(
				'series_post_id' => $page_id,
				'recurrence_id'  => '20261205T180000',
			)
		);

		Occurrences::get_instance()->delete_for_post( $page_id );

		$this->assertCount( 1, $this->rows( $page_id ), 'A post type without event dates is left alone.' );
	}
}
