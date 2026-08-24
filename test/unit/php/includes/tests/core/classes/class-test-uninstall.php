<?php
/**
 * Runs the {@see uninstall.php} routine on a fresh options table to verify
 * the per-site transient wipe and the multisite loop wipe both scrub
 * plugin-owned `_transient_gatherpress_*` rows without touching unrelated
 * transients or plugin options.
 *
 * @package GatherPress Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Tests\Base;

/**
 * Class Test_Uninstall.
 *
 * @since 0.36.0
 */
class Test_Uninstall extends Base {

	/**
	 * Path to the uninstall.php file under test.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	private const UNINSTALL_PATH = GATHERPRESS_CORE_PATH . '/uninstall.php';

	/**
	 * Run the uninstall.php routine on a polluted current site.
	 *
	 * Defines WP_UNINSTALL_PLUGIN, includes the file, and stashes the
	 * resulting routine to call directly so we don't depend on
	 * is_multisite() global state across tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	private function run_uninstall_routine(): void {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WP_UNINSTALL_PLUGIN is defined by WordPress itself when it calls uninstall.php; the constant always carries this exact name.
			define( 'WP_UNINSTALL_PLUGIN', 'gatherpress/gatherpress.php' );
		}

		require_once self::UNINSTALL_PATH;

		// `require_once` short-circuits the second test onward, which means
		// the if (is_multisite)/else branch in uninstall.php only fires on the
		// very first call. Call the per-site helper directly so every test
		// exercises the cleanup end-to-end against its own seeded rows.
		\gatherpress_uninstall_wipe_transients();
	}

	/**
	 * Plugin-owned transient rows are wiped on uninstall.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_uninstall_wipes_plugin_transients(): void {
		// Seed a paired data + timeout row that the routine must remove.
		add_option( '_transient_gatherpress_photon_search_x', 'data-value' );
		add_option(
			'_transient_timeout_gatherpress_photon_search_x',
			time() + MINUTE_IN_SECONDS
		);

		$this->assertSame(
			'data-value',
			get_option( '_transient_gatherpress_photon_search_x' ),
			'Pre-condition: plugin transient data row exists.'
		);
		$this->assertNotFalse(
			get_option( '_transient_timeout_gatherpress_photon_search_x' ),
			'Pre-condition: plugin transient timeout row exists.'
		);

		$this->run_uninstall_routine();

		// Verify against $wpdb directly so we are testing the SQL cleanup; if the
		// SQL row is gone, uninstall.php did its job. The WP option cache
		// layer is covered in uninstall.php. Here we focus on what
		// uninstall.php promised to wipe.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the SQL row was deleted; not a render path.
		$row_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
				'_transient_gatherpress_photon_search_x',
				'_transient_timeout_gatherpress_photon_search_x'
			)
		);
		$this->assertSame(
			0,
			$row_count,
			'Plugin transient rows should be deleted from wp_options on uninstall.'
		);
		$this->assertFalse(
			get_option( '_transient_gatherpress_photon_search_x' ),
			'Plugin transient data row should be wiped on uninstall.'
		);
		$this->assertFalse(
			get_option( '_transient_timeout_gatherpress_photon_search_x' ),
			'Plugin transient timeout row should be wiped on uninstall.'
		);

		// `gatherpress_uninstall_wipe_transients` is the function uninstall.php
		// declares; calling it again on a clean table exercises the empty-rows
		// short-circuit (`if ( empty( $rows ) ) { return; }`).
		\gatherpress_uninstall_wipe_transients();
	}

	/**
	 * Transients belonging to other plugins are left alone.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_uninstall_preserves_other_plugins_transients(): void {
		add_option( '_transient_other_plugin_value', 'untouched' );
		add_option(
			'_transient_timeout_other_plugin_value',
			time() + MINUTE_IN_SECONDS
		);

		$this->run_uninstall_routine();

		$this->assertSame(
			'untouched',
			get_option( '_transient_other_plugin_value' ),
			'Transients from other plugins must survive the GatherPress uninstall.'
		);
		$this->assertNotFalse(
			get_option( '_transient_timeout_other_plugin_value' ),
			'Timeout rows from other plugins must also survive.'
		);

		delete_option( '_transient_other_plugin_value' );
		delete_option( '_transient_timeout_other_plugin_value' );
	}

	/**
	 * Plugin settings (non-transient options) survive the current uninstall.php.
	 *
	 * The full settings + custom-table cleanup is owned by the #681
	 * follow-up; until that lands, only the transient cache is wiped.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_uninstall_preserves_settings_options(): void {
		add_option( 'gatherpress_test_setting', 'unchanged' );

		$this->run_uninstall_routine();

		$this->assertSame(
			'unchanged',
			get_option( 'gatherpress_test_setting' ),
			'GatherPress options must survive the transient-only uninstall routine.'
		);

		delete_option( 'gatherpress_test_setting' );
	}

	/**
	 * Multisite flow: each subsite's transient rows are wiped on network uninstall.
	 *
	 * Switches to a created subsite, seeds plugin-owned transients there,
	 * runs `gatherpress_uninstall_wipe_transients()` (the function declared
	 * inside uninstall.php — bypassing the multisite branch loop so the
	 * single-site wipe path is the same one the loop calls per site).
	 *
	 * @since 0.36.0
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_uninstall_wipes_transients_on_each_subsite(): void {
		$site_id_b = $this->factory()->blog->create();

		// Seed primary-site transients too so the call wipes both contexts.
		add_option( '_transient_gatherpress_primary', 'data-p' );
		add_option(
			'_transient_timeout_gatherpress_primary',
			time() + MINUTE_IN_SECONDS
		);

		switch_to_blog( $site_id_b );
		add_option( '_transient_gatherpress_subsite_b', 'data-b' );
		add_option(
			'_transient_timeout_gatherpress_subsite_b',
			time() + MINUTE_IN_SECONDS
		);
		add_option( '_transient_other_plugin_subsite_b', 'untouched-b' );
		restore_current_blog();

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WP_UNINSTALL_PLUGIN is defined by WordPress itself when it calls uninstall.php; the constant always carries this exact name.
			define( 'WP_UNINSTALL_PLUGIN', 'gatherpress/gatherpress.php' );
		}
		require_once self::UNINSTALL_PATH;

		// This is the per-site wipe function uninstall.php declares; the
		// `is_multisite()` outer loop calls it for every subsite during a
		// real network uninstall, so invoking it covers both single-site
		// and the inner step of the multisite flow.
		\gatherpress_uninstall_wipe_transients();
		switch_to_blog( $site_id_b );
		\gatherpress_uninstall_wipe_transients();
		restore_current_blog();

		$this->assertFalse(
			get_option( '_transient_gatherpress_primary' ),
			'Primary site transient data should be wiped.'
		);
		$this->assertFalse(
			get_option( '_transient_timeout_gatherpress_primary' ),
			'Primary site transient timeout should be wiped.'
		);

		switch_to_blog( $site_id_b );
		try {
			$this->assertFalse(
				get_option( '_transient_gatherpress_subsite_b' ),
				'Subsite transient data should be wiped.'
			);
			$this->assertFalse(
				get_option( '_transient_timeout_gatherpress_subsite_b' ),
				'Subsite transient timeout should be wiped.'
			);
			$this->assertSame(
				'untouched-b',
				get_option( '_transient_other_plugin_subsite_b' ),
				'Unrelated transient on the subsite must survive.'
			);
		} finally {
			restore_current_blog();
		}

		switch_to_blog( $site_id_b );
		delete_option( '_transient_other_plugin_subsite_b' );
		restore_current_blog();
	}
}
