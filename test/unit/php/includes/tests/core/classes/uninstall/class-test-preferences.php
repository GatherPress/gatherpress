<?php
/**
 * Unit tests for the uninstall opt-in preferences.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;

/**
 * Class Test_Preferences.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Preferences
 */
class Test_Preferences extends Base {

	/**
	 * Reset the resolved map between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		Preferences::flush_cache();
		$this->forget_stored_preferences();
	}

	/**
	 * Leave no stored preference behind for the next test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->forget_stored_preferences();
		Preferences::flush_cache();

		parent::tearDown();
	}

	/**
	 * Remove the stored option from whichever scope holds it.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function forget_stored_preferences(): void {
		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
	}

	/**
	 * Every gated task is listed.
	 *
	 * @covers ::task_keys
	 *
	 * @return void
	 */
	public function test_task_keys_lists_every_gated_task(): void {
		$keys = Preferences::task_keys();

		$this->assertSame(
			array( 'options', 'tables', 'posts', 'terms', 'comments', 'cron' ),
			$keys,
			'Every destructive task must be represented by a key.'
		);
	}

	/**
	 * Nothing is enabled when no preference has been stored.
	 *
	 * @covers ::all
	 * @covers ::is_enabled
	 *
	 * @return void
	 */
	public function test_defaults_to_everything_off(): void {
		foreach ( Preferences::task_keys() as $key ) {
			$this->assertFalse(
				Preferences::is_enabled( $key ),
				sprintf( 'Task "%s" must be off until an administrator opts in.', $key )
			);
		}
	}

	/**
	 * An unknown key is never enabled.
	 *
	 * @covers ::is_enabled
	 *
	 * @return void
	 */
	public function test_unknown_key_is_never_enabled(): void {
		Preferences::save( array( 'not_a_task' => true ) );

		$this->assertFalse(
			Preferences::is_enabled( 'not_a_task' ),
			'A key outside task_keys() must not be able to turn anything on.'
		);
	}

	/**
	 * Saving stores only the known keys, as booleans.
	 *
	 * @covers ::save
	 * @covers ::all
	 *
	 * @return void
	 */
	public function test_save_stores_known_keys_as_booleans(): void {
		Preferences::save(
			array(
				Preferences::TASK_POSTS => '1',
				'not_a_task'            => true,
			)
		);

		$all = Preferences::all();

		$this->assertSame(
			Preferences::task_keys(),
			array_keys( $all ),
			'Only the known task keys are stored.'
		);
		$this->assertTrue( $all[ Preferences::TASK_POSTS ], 'A truthy value opts the task in.' );
		$this->assertFalse( $all[ Preferences::TASK_CRON ], 'An absent key stays off.' );
	}

	/**
	 * A stored value that is not an array is treated as nothing stored.
	 *
	 * @covers ::all
	 *
	 * @return void
	 */
	public function test_corrupt_stored_value_falls_back_to_off(): void {
		if ( is_multisite() ) {
			update_site_option( Preferences::OPTION_NAME, 'corrupt' );
		} else {
			update_option( Preferences::OPTION_NAME, 'corrupt' );
		}

		Preferences::flush_cache();

		$this->assertFalse(
			Preferences::is_enabled( Preferences::TASK_POSTS ),
			'A corrupt option must fail closed, not open.'
		);
	}

	/**
	 * The map survives the option being deleted mid-run.
	 *
	 * @covers ::all
	 *
	 * @return void
	 */
	public function test_resolved_map_survives_option_deletion(): void {
		Preferences::save( array( Preferences::TASK_TERMS => true ) );

		// Prime the cache the way the first task to run would.
		$this->assertTrue( Preferences::is_enabled( Preferences::TASK_TERMS ) );

		// The Options task removes this while later tasks still need it.
		$this->forget_stored_preferences();

		$this->assertTrue(
			Preferences::is_enabled( Preferences::TASK_TERMS ),
			'A task ordered after Options must still see the opt-in it was given.'
		);
	}

	/**
	 * Flushing the cache makes the next read hit storage again.
	 *
	 * @covers ::flush_cache
	 *
	 * @return void
	 */
	public function test_flush_cache_rereads_storage(): void {
		Preferences::save( array( Preferences::TASK_TABLES => true ) );
		$this->assertTrue( Preferences::is_enabled( Preferences::TASK_TABLES ) );

		$this->forget_stored_preferences();
		Preferences::flush_cache();

		$this->assertFalse(
			Preferences::is_enabled( Preferences::TASK_TABLES ),
			'After a flush the deleted option reads as nothing stored.'
		);
	}

	/**
	 * The preference is stored in the scope uninstall reads it from.
	 *
	 * @covers ::save
	 * @covers ::all
	 *
	 * @return void
	 */
	public function test_stored_in_the_scope_uninstall_reads(): void {
		Preferences::save( array( Preferences::TASK_OPTIONS => true ) );

		if ( is_multisite() ) {
			$this->assertIsArray(
				get_site_option( Preferences::OPTION_NAME ),
				'On multisite the opt-in is a network option, because applies() runs once for the network.'
			);
		} else {
			$this->assertIsArray(
				get_option( Preferences::OPTION_NAME ),
				'On a single site the opt-in is a site option.'
			);
		}
	}

	/**
	 * Saving on multisite writes the network option, not the site option.
	 *
	 * @covers ::save
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_save_writes_the_network_option_on_multisite(): void {
		Preferences::save( array( Preferences::TASK_POSTS => true ) );

		$stored = get_site_option( Preferences::OPTION_NAME );

		$this->assertIsArray(
			$stored,
			'Saving on multisite must write the network option, because applies() reads it once for the network.'
		);
		$this->assertTrue(
			$stored[ Preferences::TASK_POSTS ],
			'The armed task must survive the round trip into network storage.'
		);
		$this->assertFalse(
			get_option( Preferences::OPTION_NAME, false ),
			'Nothing may land in per-site storage, or subsites would disagree about what uninstall removes.'
		);
	}

	/**
	 * Reading on multisite resolves the map from the network option.
	 *
	 * @covers ::all
	 * @covers ::is_enabled
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_all_reads_the_network_option_on_multisite(): void {
		update_site_option(
			Preferences::OPTION_NAME,
			array( Preferences::TASK_TERMS => true )
		);

		Preferences::flush_cache();

		$this->assertTrue(
			Preferences::is_enabled( Preferences::TASK_TERMS ),
			'A task armed in the network option must read as enabled on multisite.'
		);
		$this->assertFalse(
			Preferences::is_enabled( Preferences::TASK_POSTS ),
			'A task absent from the network option stays off.'
		);
	}
}
