<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Lock.
 *
 * @package GatherPress\Core\Rsvp
 * @since TBD
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Rsvp\Lock;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use RuntimeException;

/**
 * Class Test_Lock.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Lock
 */
class Test_Lock extends Base {

	/**
	 * Event post ID the tests lock.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const POST_ID = 987654;

	/**
	 * Coverage for run holding the lock and releasing it.
	 *
	 * @since TBD
	 *
	 * @covers ::run
	 * @covers ::acquire
	 * @covers ::delete
	 *
	 * @return void
	 */
	public function test_run(): void {
		$during = Lock::run( self::POST_ID, fn(): ?string => $this->get_lock_value() );

		$this->assertNotNull( $during, 'Failed to assert the lock is held while the callback runs.' );
		$this->assertNull( $this->get_lock_value(), 'Failed to assert the lock is released.' );
	}

	/**
	 * Coverage for run re-entering a lock this request holds.
	 *
	 * @since TBD
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run_is_reentrant(): void {
		$result = Lock::run(
			self::POST_ID,
			static fn(): string => Lock::run( self::POST_ID, static fn(): string => 'inner', null, 1 )
		);

		$this->assertSame( 'inner', $result, 'Failed to assert a nested run does not wait on its own lock.' );
		$this->assertNull( $this->get_lock_value(), 'Failed to assert the lock is released.' );
	}

	/**
	 * Coverage for run when another request holds the lock.
	 *
	 * @since TBD
	 *
	 * @covers ::run
	 * @covers ::acquire
	 *
	 * @return void
	 */
	public function test_run_returns_fallback_when_busy(): void {
		$other = time() . ':other';

		$this->set_lock_value( $other );

		$result = Lock::run( self::POST_ID, static fn(): string => 'ran', 'fallback', 2 );

		$this->assertSame( 'fallback', $result, 'Failed to assert the callback does not run without the lock.' );
		$this->assertSame(
			$other,
			$this->get_lock_value(),
			'Failed to assert the other request keeps its lock.'
		);
	}

	/**
	 * Coverage for delete leaving a lock another request holds.
	 *
	 * @since TBD
	 *
	 * @covers ::delete
	 *
	 * @return void
	 */
	public function test_delete_only_removes_matching_value(): void {
		$this->set_lock_value( time() . ':other' );

		Utility::invoke_hidden_static_method( Lock::class, 'delete', array( self::POST_ID, time() . ':mine' ) );

		$this->assertNotNull( $this->get_lock_value(), 'Failed to assert another request\'s lock is kept.' );
	}

	/**
	 * Coverage for acquire taking over a lock left behind.
	 *
	 * @since TBD
	 *
	 * @covers ::acquire
	 *
	 * @return void
	 */
	public function test_acquire_takes_over_expired_lock(): void {
		$this->set_lock_value( ( time() - Lock::EXPIRES - 1 ) . ':other' );

		$value = Utility::invoke_hidden_static_method( Lock::class, 'acquire', array( self::POST_ID, 2 ) );

		$this->assertIsString( $value, 'Failed to assert an expired lock is taken over.' );
		$this->assertSame( $value, $this->get_lock_value(), 'Failed to assert the lock holds the new value.' );
	}

	/**
	 * Coverage for run releasing the lock when the callback throws.
	 *
	 * @since TBD
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run_releases_on_exception(): void {
		$thrown = false;

		try {
			Lock::run(
				self::POST_ID,
				static function (): void {
					throw new RuntimeException( 'Failed.' );
				}
			);
		} catch ( RuntimeException ) {
			$thrown = true;
		}

		$this->assertTrue( $thrown, 'Failed to assert the exception reaches the caller.' );
		$this->assertNull( $this->get_lock_value(), 'Failed to assert the lock is released after an exception.' );
	}

	/**
	 * Reads the lock row.
	 *
	 * @since TBD
	 *
	 * @return string|null The lock value, or null when there is no lock.
	 */
	private function get_lock_value(): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the lock row the way Lock does.
		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT option_value FROM %i WHERE option_name = %s',
				$wpdb->options,
				sprintf( Lock::OPTION_NAME, self::POST_ID )
			)
		);
	}

	/**
	 * Writes the lock row as another request would.
	 *
	 * @since TBD
	 *
	 * @param string $value The lock value.
	 *
	 * @return void
	 */
	private function set_lock_value( string $value ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Writes the lock row the way Lock does.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )",
				$wpdb->options,
				sprintf( Lock::OPTION_NAME, self::POST_ID ),
				$value
			)
		);
	}
}
