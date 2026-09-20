<?php
/**
 * Unit tests for the user preference uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Rsvp;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Uninstall\Users;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Users.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Users
 */
class Test_Users extends Base {

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
	 * Create a user carrying every preference the plugin writes.
	 *
	 * @since 0.36.0
	 *
	 * @return int The user ID.
	 */
	protected function make_user(): int {
		$user_id = (int) self::factory()->user->create();

		foreach ( Users::meta_keys() as $meta_key ) {
			update_user_meta( $user_id, $meta_key, 'stored' );
		}

		update_user_meta( $user_id, 'managegatherpress_rsvpcolumnshidden', array( 'guests' ) );
		update_user_meta( $user_id, 'nickname', 'keep me' );

		return $user_id;
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
			( new Users() )->applies(),
			'Removing what people chose for themselves must wait for an explicit opt-in.'
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
		Preferences::save( array( Preferences::TASK_USERS => true ) );

		$this->assertTrue( ( new Users() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * The task reports that it writes past the object cache.
	 *
	 * @covers ::invalidates_cache
	 *
	 * @return void
	 */
	public function test_invalidates_cache(): void {
		$this->assertTrue(
			( new Users() )->invalidates_cache(),
			'A task that deletes rows with SQL has to tell the registry to flush.'
		);
	}

	/**
	 * Every key the plugin writes against a user is listed.
	 *
	 * @covers ::meta_keys
	 *
	 * @return void
	 */
	public function test_lists_every_meta_key_the_plugin_writes(): void {
		$this->assertSame(
			array(
				'gatherpress_timezone',
				'gatherpress_time_format',
				'gatherpress_event_updates_opt_in',
				sprintf( '%s_per_page', Rsvp::COMMENT_TYPE ),
			),
			Users::meta_keys(),
			'A key left off this list survives the uninstall with no way to reach it.'
		);
	}

	/**
	 * Preferences survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_network
	 *
	 * @return void
	 */
	public function test_leaves_preferences_alone_without_opt_in(): void {
		$user_id = $this->make_user();

		( new Users() )->run();

		$this->assertSame(
			'stored',
			get_user_meta( $user_id, 'gatherpress_timezone', true ),
			'A task that was never opted in to must remove nothing.'
		);
	}

	/**
	 * Every plugin key goes, and nothing else does.
	 *
	 * @covers ::uninstall_network
	 *
	 * @return void
	 */
	public function test_removes_the_plugin_keys_only(): void {
		Preferences::save( array( Preferences::TASK_USERS => true ) );

		$user_id = $this->make_user();

		( new Users() )->run();

		// Assert against the rows, not through get_user_meta(): the task
		// deletes with SQL, so the object cache still holds the meta until
		// Setup::run() flushes it at the end of a real uninstall.
		global $wpdb;

		foreach ( Users::meta_keys() as $meta_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
			$rows = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
					$user_id,
					$meta_key
				)
			);

			$this->assertSame( 0, $rows, sprintf( 'The %s preference is removed.', $meta_key ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$hidden_rows = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
				$user_id,
				'managegatherpress_rsvpcolumnshidden'
			)
		);

		$this->assertSame(
			0,
			$hidden_rows,
			'The hidden-columns key names a screen that never registers here, so it is matched by shape.'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the row survived.
		$other_rows = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s",
				$user_id,
				'nickname'
			)
		);

		$this->assertSame( 1, $other_rows, 'Meta that is not the plugin\'s must survive.' );
	}

	/**
	 * The per-site pass does nothing, because the rows are network-wide.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_per_site_pass_removes_nothing(): void {
		$user_id = $this->make_user();

		Utility::invoke_hidden_method( new Users(), 'uninstall_site' );

		$this->assertSame(
			'stored',
			get_user_meta( $user_id, 'gatherpress_timezone', true ),
			'usermeta is one shared table, so the work belongs to the network pass.'
		);
	}
}
