<?php
/**
 * Unit tests for the uninstall opt-in preferences.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Network;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Preferences.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Preferences
 */
class Test_Preferences extends Base {

	/**
	 * Arms and resets the uninstall opt-ins.
	 */
	use Preferences_Fixture;

	/**
	 * Reset the opt-ins between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->reset_uninstall_preferences();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->reset_uninstall_preferences();

		parent::tearDown();
	}

	/**
	 * Every destructive task is represented by a key.
	 *
	 * @covers ::task_keys
	 *
	 * @return void
	 */
	public function test_task_keys_lists_every_gated_task(): void {
		$this->assertSame(
			array( 'events', 'rsvps', 'topics', 'venues', 'files', 'cron', 'users', 'options' ),
			Preferences::task_keys(),
			'Every destructive task must be represented by a key.'
		);
	}

	/**
	 * A task key maps to the settings key that stores it.
	 *
	 * @covers ::option_key
	 *
	 * @return void
	 */
	public function test_option_key_is_prefixed(): void {
		$this->assertSame(
			'uninstall_events',
			Preferences::option_key( Preferences::TASK_EVENTS ),
			'The opt-ins share a prefix so they read as a group in the stored settings.'
		);
	}

	/**
	 * Nothing is on until somebody turns it on.
	 *
	 * @covers ::all
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_defaults_to_everything_off(): void {
		$this->assertSame(
			array_fill_keys( Preferences::task_keys(), false ),
			Preferences::all(),
			'A site that never visited the screen must lose nothing.'
		);
	}

	/**
	 * A stored opt-in reads as enabled.
	 *
	 * @covers ::is_enabled
	 * @covers ::all
	 *
	 * @return void
	 */
	public function test_reads_an_opt_in_from_the_settings(): void {
		$this->arm_uninstall( Preferences::TASK_EVENTS );

		$this->assertTrue( Preferences::is_enabled( Preferences::TASK_EVENTS ), 'The opt-in reads back.' );
		$this->assertFalse( Preferences::is_enabled( Preferences::TASK_CRON ), 'An absent key stays off.' );
	}

	/**
	 * An unknown key is never enabled.
	 *
	 * @covers ::is_enabled
	 *
	 * @return void
	 */
	public function test_unknown_key_is_never_enabled(): void {
		update_option( Settings::OPTION_NAME, array( 'uninstall_not_a_task' => true ) );
		Preferences::flush_cache();

		$this->assertFalse(
			Preferences::is_enabled( 'not_a_task' ),
			'A key outside task_keys() must not be able to turn anything on.'
		);
	}

	/**
	 * A stored value that is not an array is treated as nothing stored.
	 *
	 * @covers ::stored_options
	 *
	 * @return void
	 */
	public function test_corrupt_stored_value_falls_back_to_off(): void {
		update_option( Settings::OPTION_NAME, 'corrupt' );
		Preferences::flush_cache();

		$this->assertFalse(
			Preferences::is_enabled( Preferences::TASK_EVENTS ),
			'A corrupt option must fail closed, not open.'
		);
	}

	/**
	 * The answer survives the settings being deleted mid-run.
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolved_map_survives_the_settings_being_deleted(): void {
		$this->arm_uninstall( Preferences::TASK_TOPICS );

		// Prime the map the way the first task to run would.
		$this->assertTrue( Preferences::is_enabled( Preferences::TASK_TOPICS ) );

		// The Options task removes this while later tasks still need it.
		delete_option( Settings::OPTION_NAME );

		$this->assertTrue(
			Preferences::is_enabled( Preferences::TASK_TOPICS ),
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
		$this->arm_uninstall( Preferences::TASK_FILES );
		$this->assertTrue( Preferences::is_enabled( Preferences::TASK_FILES ) );

		delete_option( Settings::OPTION_NAME );
		Preferences::flush_cache();

		$this->assertFalse(
			Preferences::is_enabled( Preferences::TASK_FILES ),
			'After a flush the deleted option reads as nothing stored.'
		);
	}

	/**
	 * On a single site the network answer is the site's answer.
	 *
	 * @covers ::all_for_network
	 * @covers ::is_enabled_for_network
	 *
	 * @return void
	 */
	public function test_network_answer_matches_the_site_on_a_single_site(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site behavior.' );
		}

		$this->arm_uninstall( Preferences::TASK_USERS );

		$this->assertTrue(
			Preferences::is_enabled_for_network( Preferences::TASK_USERS ),
			'Without a network there is only one answer to give.'
		);
	}

	/**
	 * A site answers for its own data when the network did not decide.
	 *
	 * @covers ::resolve
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_site_answers_for_itself_when_not_inherited(): void {
		$this->arm_uninstall( Preferences::TASK_EVENTS );

		update_site_option(
			Settings::OPTION_NAME,
			array( Preferences::option_key( Preferences::TASK_EVENTS ) => false )
		);
		Preferences::flush_cache();

		$this->assertTrue(
			Preferences::is_enabled( Preferences::TASK_EVENTS ),
			'A network that left the choice to its sites must not overrule one.'
		);
		$this->assertFalse(
			Preferences::is_enabled_for_network( Preferences::TASK_EVENTS ),
			'What no single site owns still answers to the network.'
		);
	}

	/**
	 * The network decides for every site once it says so.
	 *
	 * @covers ::resolve
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_network_decides_for_every_site_when_inherited(): void {
		// The site says no.
		update_option(
			Settings::OPTION_NAME,
			array( Preferences::option_key( Preferences::TASK_EVENTS ) => false )
		);

		// The network says yes, for everyone.
		$this->arm_uninstall_for_network( Preferences::TASK_EVENTS );

		$this->assertTrue(
			Preferences::is_enabled( Preferences::TASK_EVENTS ),
			'A network that told its sites what to do is answered for them.'
		);
	}

	/**
	 * An inheritance list the network never switched on decides nothing.
	 *
	 * @covers ::resolve
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_inheritance_list_is_ignored_while_disabled(): void {
		update_site_option(
			Settings::OPTION_NAME,
			array( Preferences::option_key( Preferences::TASK_EVENTS ) => true )
		);
		update_site_option(
			Network::OPTION_NAME,
			array(
				'enabled'   => false,
				'inherited' => array( Preferences::option_key( Preferences::TASK_EVENTS ) ),
			)
		);

		Network::flush_config_cache();
		Preferences::flush_cache();

		$this->assertFalse(
			Preferences::is_enabled( Preferences::TASK_EVENTS ),
			'A list that is switched off is not an instruction.'
		);
	}

	/**
	 * The network values are read from network storage.
	 *
	 * Invoked directly as well as through `resolve()`, because xdebug does
	 * not trace a static helper called from its own class. Multisite only:
	 * `get_site_option()` falls back to the options table on a single site,
	 * so the two scopes are the same row there and cannot be told apart.
	 *
	 * @covers ::stored_options
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_stored_options_reads_the_scope_it_is_asked_for(): void {
		update_site_option( Settings::OPTION_NAME, array( 'from' => 'the network' ) );
		update_option( Settings::OPTION_NAME, array( 'from' => 'the site' ) );

		$this->assertSame(
			array( 'from' => 'the network' ),
			Utility::invoke_hidden_static_method( Preferences::class, 'stored_options', array( true ) ),
			'The network answer comes from network storage.'
		);
		$this->assertSame(
			array( 'from' => 'the site' ),
			Utility::invoke_hidden_static_method( Preferences::class, 'stored_options', array( false ) ),
			'A site answers from its own.'
		);
	}
}
