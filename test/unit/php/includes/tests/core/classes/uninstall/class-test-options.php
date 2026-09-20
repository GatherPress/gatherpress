<?php
/**
 * Unit tests for the option-removal uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Calendar\Cache;
use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Network;
use GatherPress\Core\Uninstall\Options;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;

/**
 * Class Test_Options.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Options
 */
class Test_Options extends Base {

	/**
	 * Arms and resets the uninstall opt-ins.
	 */
	use Preferences_Fixture;

	/**
	 * Reset the opt-in map between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->reset_preferences();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->reset_preferences();

		parent::tearDown();
	}

	/**
	 * Forget any stored opt-in.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function reset_preferences(): void {
		$this->reset_uninstall_preferences();
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
			( new Options() )->applies(),
			'Removing settings must wait for an explicit opt-in.'
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
		$this->arm_uninstall( Preferences::TASK_OPTIONS );

		$this->assertTrue( ( new Options() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * Settings survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_options_alone_without_opt_in(): void {
		update_option( Settings::OPTION_NAME, array( 'keep' => 'me' ) );

		( new Options() )->run();

		$this->assertSame(
			array( 'keep' => 'me' ),
			get_option( Settings::OPTION_NAME ),
			'A task that was never opted in to must remove nothing.'
		);
	}

	/**
	 * The settings and the version marker go.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_settings_and_version(): void {
		update_option( Settings::OPTION_NAME, array( 'some' => 'value' ) );
		update_option( Options::VERSION_OPTION, '0.36.0' );

		$this->arm_uninstall( Preferences::TASK_OPTIONS );

		( new Options() )->run();

		$this->assertFalse(
			get_option( Settings::OPTION_NAME ),
			'The settings option is removed.'
		);
		$this->assertFalse(
			get_option( Options::VERSION_OPTION ),
			'The version marker is removed.'
		);
	}

	/**
	 * The calendar cache stamp goes with the rest of the options.
	 *
	 * Found by uninstalling a real site: the task deleted the settings and
	 * the version marker and left this one behind, because the list of
	 * option names was written by hand.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_the_calendar_cache_stamp(): void {
		$this->arm_uninstall( Preferences::TASK_OPTIONS );

		update_option( Cache::LAST_MODIFIED_OPTION, '2026-09-07 12:00:00' );

		( new Options() )->run();

		$this->assertFalse(
			get_option( Cache::LAST_MODIFIED_OPTION ),
			'The calendar last-modified stamp is removed.'
		);
	}

	/**
	 * The network settings go in the network pass.
	 *
	 * Multisite-only on purpose. On a single site `delete_site_option()`
	 * falls through to `delete_option()` on the same row, so a single-site
	 * run of this test passes whichever of the two the task calls and
	 * proves nothing about the network scope.
	 *
	 * @covers ::uninstall_network
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_removes_the_network_settings(): void {
		$this->arm_uninstall( Preferences::TASK_OPTIONS );

		update_site_option( Network::OPTION_NAME, array( 'some' => 'value' ) );

		( new Options() )->run();

		$this->assertFalse(
			get_site_option( Network::OPTION_NAME ),
			'The network settings option is removed.'
		);
	}

	/**
	 * The choices made on the Uninstall screen go with the settings.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_its_own_opt_ins(): void {
		$this->arm_uninstall( Preferences::TASK_OPTIONS );

		$this->assertArrayHasKey(
			Preferences::option_key( Preferences::TASK_OPTIONS ),
			(array) get_option( Settings::OPTION_NAME ),
			'Pre-condition: the opt-in is stored with the rest of the settings.'
		);

		( new Options() )->run();

		$this->assertFalse(
			get_option( Settings::OPTION_NAME ),
			'The opt-ins live in the settings, so removing the settings takes them too.'
		);
	}

	/**
	 * Another plugin's options are untouched.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_other_options_alone(): void {
		$this->arm_uninstall( Preferences::TASK_OPTIONS );

		update_option( 'some_other_plugin_option', 'intact' );

		( new Options() )->run();

		$this->assertSame(
			'intact',
			get_option( 'some_other_plugin_option' ),
			'Only the plugin\'s own options are removed.'
		);

		delete_option( 'some_other_plugin_option' );
	}
}
