<?php
/**
 * Class handles unit tests for GatherPress\Core\Coexistence_Guard.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Coexistence_Guard;
use GatherPress\Tests\Base;

/**
 * Class Test_Coexistence_Guard.
 *
 * @coversDefaultClass \GatherPress\Core\Coexistence_Guard
 */
class Test_Coexistence_Guard extends Base {
	/**
	 * Reset cached plugin list and the active-plugins option after each test so
	 * later tests start from a clean slate.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_cache_delete( 'plugins', 'plugins' );
		delete_option( 'active_plugins' );

		// Clean up activation hooks registered during the test for our
		// synthetic test slugs so they don't leak into other tests.
		global $wp_filter;
		foreach ( array_keys( $wp_filter ) as $tag ) {
			if ( 0 === strpos( $tag, 'activate_test-coexistence' ) ) {
				unset( $wp_filter[ $tag ] );
			}
		}

		parent::tearDown();
	}

	/**
	 * Seed `get_plugins()`'s cache so callers see a controlled fixture without
	 * touching the real plugins directory.
	 *
	 * @param array<string, array<string, mixed>> $plugins Mapping of plugin basename to header data.
	 * @return void
	 */
	private function seed_plugins_cache( array $plugins ): void {
		wp_cache_set( 'plugins', array( '' => $plugins ), 'plugins' );
	}

	/**
	 * Coverage for __construct and setup_hooks. Self-registration of the
	 * GatherPress slug also runs through the constructor.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Coexistence_Guard::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_register_coexistence_guard',
				'priority' => 10,
				'callback' => array( $instance, 'register' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );

		$this->assertNotFalse(
			has_action( 'activate_' . plugin_basename( GATHERPRESS_CORE_FILE ) ),
			'GatherPress should self-register its activation hook in the constructor.'
		);
	}

	/**
	 * Registers the canonical activation hook regardless of admin context.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_registers_canonical_activation_hook(): void {
		$this->seed_plugins_cache( array() );

		$main_file = WP_PLUGIN_DIR . '/test-coexistence-canonical/test-coexistence-canonical.php';

		Coexistence_Guard::get_instance()->register(
			'test-coexistence-canonical',
			'Test Coexistence Canonical',
			$main_file
		);

		$this->assertNotFalse(
			has_action( 'activate_' . plugin_basename( $main_file ) ),
			'Canonical activation hook should be registered.'
		);
	}

	/**
	 * Mirrors the activation hook onto sibling folders when running in admin.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_mirrors_activation_hooks_onto_siblings_in_admin(): void {
		$this->seed_plugins_cache(
			array(
				'test-coexistence-siblings/test-coexistence-siblings.php'       => array(),
				'test-coexistence-siblings-build/test-coexistence-siblings.php' => array(),
			)
		);

		set_current_screen( 'plugins' );

		Coexistence_Guard::get_instance()->register(
			'test-coexistence-siblings',
			'Test Coexistence Siblings',
			WP_PLUGIN_DIR . '/test-coexistence-siblings/test-coexistence-siblings.php'
		);

		$this->assertNotFalse(
			has_action( 'activate_test-coexistence-siblings/test-coexistence-siblings.php' ),
			'Canonical sibling hook should be registered.'
		);
		$this->assertNotFalse(
			has_action( 'activate_test-coexistence-siblings-build/test-coexistence-siblings.php' ),
			'Build sibling hook should be registered.'
		);

		set_current_screen( 'front' );
	}

	/**
	 * Skips sibling registration when not in admin.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_skips_siblings_when_not_admin(): void {
		$this->seed_plugins_cache(
			array(
				'test-coexistence-frontonly/test-coexistence-frontonly.php'       => array(),
				'test-coexistence-frontonly-build/test-coexistence-frontonly.php' => array(),
			)
		);

		set_current_screen( 'front' );

		Coexistence_Guard::get_instance()->register(
			'test-coexistence-frontonly',
			'Test Coexistence Frontonly',
			WP_PLUGIN_DIR . '/test-coexistence-frontonly/test-coexistence-frontonly.php'
		);

		$this->assertFalse(
			has_action( 'activate_test-coexistence-frontonly-build/test-coexistence-frontonly.php' ),
			'Sibling hook should not be registered outside admin.'
		);
	}

	/**
	 * Coverage for find_duplicates: matches canonical and prefix-siblings,
	 * skips wrong file names, wrong folder names, and single-segment keys.
	 *
	 * @covers ::find_duplicates
	 *
	 * @return void
	 */
	public function test_find_duplicates_returns_only_matching_basenames(): void {
		$this->seed_plugins_cache(
			array(
				'test-coexistence-find/test-coexistence-find.php' => array(),
				'test-coexistence-find-build/test-coexistence-find.php' => array(),
				'test-coexistence-find/different-file.php' => array(), // Wrong file name.
				'unrelated/test-coexistence-find.php'      => array(), // Wrong folder name.
				'akismet/akismet.php'                      => array(), // Unrelated.
				'test-coexistence-find.php'                => array(), // Single-segment key.
			)
		);

		$result = Coexistence_Guard::get_instance()->find_duplicates( 'test-coexistence-find' );

		$this->assertSame(
			array(
				'test-coexistence-find-build/test-coexistence-find.php',
				'test-coexistence-find/test-coexistence-find.php',
			),
			$result
		);
	}

	/**
	 * Coverage for find_duplicates when a non-string key sneaks into get_plugins().
	 *
	 * @covers ::find_duplicates
	 *
	 * @return void
	 */
	public function test_find_duplicates_skips_non_string_keys(): void {
		// Filter the cached value to inject a non-string key so the early-continue
		// branch in find_duplicates() executes.
		$plugins      = array(
			'test-coexistence-nonstr/test-coexistence-nonstr.php' => array(),
		);
		$plugins[123] = array(); // Non-string key.

		$this->seed_plugins_cache( $plugins );

		$result = Coexistence_Guard::get_instance()->find_duplicates( 'test-coexistence-nonstr' );

		$this->assertSame(
			array( 'test-coexistence-nonstr/test-coexistence-nonstr.php' ),
			$result
		);
	}

	/**
	 * Returns silently when only one matching folder exists.
	 *
	 * @covers ::refuse_on_duplicates
	 *
	 * @return void
	 */
	public function test_refuse_on_duplicates_returns_when_no_duplicate_folder(): void {
		$this->seed_plugins_cache(
			array(
				'test-coexistence-single/test-coexistence-single.php' => array(),
			)
		);

		Coexistence_Guard::get_instance()->refuse_on_duplicates(
			'test-coexistence-single',
			'Test Coexistence Single'
		);

		$this->assertTrue( true, 'No exception should be thrown when only one folder exists.' );
	}

	/**
	 * Returns silently when duplicate folders exist but none are active.
	 *
	 * @covers ::refuse_on_duplicates
	 *
	 * @return void
	 */
	public function test_refuse_on_duplicates_returns_when_no_active_duplicate(): void {
		$this->seed_plugins_cache(
			array(
				'test-coexistence-inactive/test-coexistence-inactive.php'       => array(),
				'test-coexistence-inactive-build/test-coexistence-inactive.php' => array(),
			)
		);
		update_option( 'active_plugins', array() );

		Coexistence_Guard::get_instance()->refuse_on_duplicates(
			'test-coexistence-inactive',
			'Test Coexistence Inactive'
		);

		$this->assertTrue( true, 'No exception should be thrown when no duplicate is active.' );
	}

	/**
	 * The closure registered as the activation callback must invoke
	 * refuse_on_duplicates with the slug and name captured at registration time.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_callback_invokes_refuse_on_duplicates(): void {
		$this->seed_plugins_cache(
			array(
				'test-coexistence-callback/test-coexistence-callback.php'       => array(),
				'test-coexistence-callback-build/test-coexistence-callback.php' => array(),
			)
		);
		update_option(
			'active_plugins',
			array( 'test-coexistence-callback/test-coexistence-callback.php' )
		);

		Coexistence_Guard::get_instance()->register(
			'test-coexistence-callback',
			'Test Coexistence Callback',
			WP_PLUGIN_DIR . '/test-coexistence-callback-build/test-coexistence-callback.php'
		);

		$this->expectException( \WPDieException::class );

		// WordPress's `activate_{plugin_file}` hook name shape carries the plugin
		// basename verbatim, including the slash and dashes — that's the action
		// name `register_activation_hook()` derives. Suppress the PHPCS naming
		// sniffs since this is firing WP core's own hook, not a project hook.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound,WordPress.NamingConventions.ValidHookName.UseUnderscores
		do_action( 'activate_test-coexistence-callback-build/test-coexistence-callback.php' );
	}

	/**
	 * Halts activation when a sibling copy is already active.
	 *
	 * @covers ::refuse_on_duplicates
	 *
	 * @return void
	 */
	public function test_refuse_on_duplicates_dies_when_active_duplicate_exists(): void {
		$this->seed_plugins_cache(
			array(
				'test-coexistence-active/test-coexistence-active.php'       => array(),
				'test-coexistence-active-build/test-coexistence-active.php' => array(),
			)
		);
		update_option(
			'active_plugins',
			array( 'test-coexistence-active/test-coexistence-active.php' )
		);

		$this->expectException( \WPDieException::class );

		Coexistence_Guard::get_instance()->refuse_on_duplicates(
			'test-coexistence-active',
			'Test Coexistence Active'
		);
	}
}
