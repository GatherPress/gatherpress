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
	 * The query filter that denies the lock.
	 *
	 * @since TBD
	 *
	 * @var callable|null
	 */
	private $deny_filter = null;

	/**
	 * Coverage for run holding the lock and releasing it.
	 *
	 * @since TBD
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run(): void {
		$during = ( new Lock( self::POST_ID ) )->run( fn(): bool => $this->is_locked() );

		$this->assertTrue( $during, 'Failed to assert the lock is held while the callback runs.' );
		$this->assertFalse( $this->is_locked(), 'Failed to assert the lock is released.' );
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
		// A second instance for the same event, as a nested Rsvp would create.
		$inner = ( new Lock( self::POST_ID ) )->run(
			fn(): bool => ( new Lock( self::POST_ID ) )->run( fn(): bool => $this->is_locked() ) && $this->is_locked()
		);

		$this->assertTrue( $inner, 'Failed to assert the outer lock survives a nested run.' );
		$this->assertFalse( $this->is_locked(), 'Failed to assert the lock is released.' );
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
			( new Lock( self::POST_ID ) )->run(
				static function (): void {
					throw new RuntimeException( 'Failed.' );
				}
			);
		} catch ( RuntimeException ) {
			$thrown = true;
		}

		$this->assertTrue( $thrown, 'Failed to assert the exception reaches the caller.' );
		$this->assertFalse( $this->is_locked(), 'Failed to assert the lock is released after an exception.' );
	}

	/**
	 * Coverage for run when the lock is not granted.
	 *
	 * @since TBD
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run_falls_back_when_not_granted(): void {
		$ran = false;

		foreach ( array( '0', 'NULL' ) as $result ) {
			$this->deny_lock( $result );

			$this->assertSame(
				'fallback',
				( new Lock( self::POST_ID ) )->run(
					static function () use ( &$ran ): string {
						$ran = true;

						return 'ran';
					},
					static fn(): string => 'fallback'
				),
				'Failed to assert the fallback runs when the lock is not granted.'
			);
			$this->assertNull(
				( new Lock( self::POST_ID ) )->run( static fn(): string => 'ran' ),
				'Failed to assert null is returned without a fallback.'
			);
		}

		$this->assertFalse( $ran, 'Failed to assert the callback never runs without the lock.' );
	}

	/**
	 * Coverage for the lock name.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_name(): void {
		global $wpdb;

		$this->assertSame(
			$wpdb->prefix . 'gatherpress_rsvp_' . self::POST_ID,
			( new Lock( self::POST_ID ) )->name,
			'Failed to assert the lock name carries the table prefix.'
		);

		$prefix       = $wpdb->prefix;
		$wpdb->prefix = str_repeat( 'p', 60 ) . '_';
		$name         = ( new Lock( self::POST_ID ) )->name;
		$wpdb->prefix = $prefix;

		$this->assertSame( 64, strlen( $name ), 'Failed to assert a long name is cut to 64 characters.' );
		$this->assertStringEndsWith( '_' . self::POST_ID, $name, 'Failed to assert the post ID is kept.' );
	}

	/**
	 * Makes GET_LOCK return the given result instead of taking the lock.
	 *
	 * @since TBD
	 *
	 * @param string $result The SQL result, such as 0 or NULL.
	 *
	 * @return void
	 */
	private function deny_lock( string $result ): void {
		if ( $this->deny_filter ) {
			remove_filter( 'query', $this->deny_filter );
		}

		$this->deny_filter = static fn( string $query ): string => str_starts_with( $query, 'SELECT GET_LOCK(' )
			? "SELECT {$result}"
			: $query;

		add_filter( 'query', $this->deny_filter );
	}

	/**
	 * Whether the test event's lock is held.
	 *
	 * @since TBD
	 *
	 * @return bool True when the lock is held.
	 */
	private function is_locked(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A lock, not data.
		$holder = $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK( %s )', ( new Lock( self::POST_ID ) )->name ) );

		return null !== $holder;
	}
}
