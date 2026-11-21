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
	 * @covers ::release
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
			static fn(): string => Lock::run( self::POST_ID, static fn(): string => 'inner', 1 )
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
	public function test_run_without_lock_when_busy(): void {
		$this->set_lock_value( time() );

		$result = Lock::run( self::POST_ID, static fn(): string => 'ran', 2 );

		$this->assertSame( 'ran', $result, 'Failed to assert the callback runs when the lock stays busy.' );
		$this->assertNotNull( $this->get_lock_value(), 'Failed to assert the other request keeps its lock.' );
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
		$this->set_lock_value( time() - Lock::EXPIRES - 1 );

		$this->assertTrue(
			Utility::invoke_hidden_static_method( Lock::class, 'acquire', array( self::POST_ID, 2 ) ),
			'Failed to assert an expired lock is taken over.'
		);
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
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				sprintf( Lock::OPTION_NAME, self::POST_ID )
			)
		);
	}

	/**
	 * Writes the lock row as another request would.
	 *
	 * @since TBD
	 *
	 * @param int $locked_at When the lock was taken.
	 *
	 * @return void
	 */
	private function set_lock_value( int $locked_at ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes the lock row the way Lock does.
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => sprintf( Lock::OPTION_NAME, self::POST_ID ),
				'option_value' => (string) $locked_at,
				'autoload'     => 'off',
			)
		);
	}
}
