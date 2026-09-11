<?php
/**
 * Unit tests for the table-drop uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Event;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Uninstall\Tables;
use GatherPress\Tests\Base;

/**
 * Class Test_Tables.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Tables
 */
class Test_Tables extends Base {

	/**
	 * Reset the opt-in map between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();

		parent::tearDown();
	}

	/**
	 * Queries captured while a task runs.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	protected array $captured = array();

	/**
	 * Record every query, before the test harness rewrites it.
	 *
	 * `WP_UnitTestCase` filters `query` at the default priority to turn
	 * `DROP TABLE` into `DROP TEMPORARY TABLE`, so the real table is never
	 * dropped inside a test and the effect cannot be asserted. Capturing at
	 * priority 1 records what the task actually asked the database to do.
	 *
	 * @since 0.36.0
	 *
	 * @param string $query The query about to run.
	 *
	 * @return string The query, unchanged.
	 */
	public function capture_query( string $query ): string {
		$this->captured[] = $query;

		return $query;
	}

	/**
	 * Run a task with its queries captured.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The queries the task issued.
	 */
	protected function capture_run(): array {
		$this->captured = array();

		add_filter( 'query', array( $this, 'capture_query' ), 1 );
		( new Tables() )->run();
		remove_filter( 'query', array( $this, 'capture_query' ), 1 );

		return $this->captured;
	}

	/**
	 * The drop statements among a set of captured queries.
	 *
	 * @since 0.36.0
	 *
	 * @param string[] $queries The captured queries.
	 *
	 * @return string[] The matching drop statements.
	 */
	protected function drop_statements( array $queries ): array {
		global $wpdb;

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		return array_values(
			array_filter(
				$queries,
				static function ( string $query ) use ( $table ): bool {
					return str_contains( $query, 'DROP TABLE' ) && str_contains( $query, $table );
				}
			)
		);
	}

	/**
	 * The task stays off until it is opted in to.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_does_not_apply_by_default(): void {
		$this->assertFalse(
			( new Tables() )->applies(),
			'Dropping a table must wait for an explicit opt-in.'
		);
	}

	/**
	 * The task applies once its preference is on.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_when_opted_in(): void {
		Preferences::save( array( Preferences::TASK_TABLES => true ) );

		$this->assertTrue( ( new Tables() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * The table survives when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_table_alone_without_opt_in(): void {
		$this->assertSame(
			array(),
			$this->drop_statements( $this->capture_run() ),
			'A task that was never opted in to must issue no drop at all.'
		);
	}

	/**
	 * The event table is dropped once opted in to.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_drops_event_table(): void {
		global $wpdb;

		Preferences::save( array( Preferences::TASK_TABLES => true ) );

		$drops = $this->drop_statements( $this->capture_run() );

		$this->assertCount( 1, $drops, 'Exactly one drop is issued for the event table.' );
		$this->assertStringContainsString(
			'IF EXISTS',
			$drops[0],
			'The drop tolerates a table that was already removed.'
		);
		$this->assertStringContainsString(
			sprintf( Event::TABLE_FORMAT, $wpdb->prefix ),
			$drops[0],
			'The drop names the event table for this site, not another one.'
		);
	}
}
