<?php
/**
 * Unit tests for the transient-wipe uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Uninstall\Transients;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Transients.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Transients
 */
class Test_Transients extends Base {

	/**
	 * The transient task opts in unconditionally.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_opts_in(): void {
		$this->assertTrue(
			( new Transients() )->applies(),
			'Transients are cache; the wipe is always safe and needs no opt-in setting.'
		);
	}

	/**
	 * Plugin-owned transient rows are wiped on uninstall.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_run_wipes_plugin_transients(): void {
		// Seed a paired data + timeout row that the task must remove.
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

		( new Transients() )->run();

		// Verify against $wpdb directly so we are testing the SQL cleanup;
		// the WP option cache layer is handled inside the task itself.
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
	}

	/**
	 * A wipe against a clean table takes the empty-rows short-circuit.
	 *
	 * Invoked directly so xdebug traces the protected method's body; the
	 * run()-driven test covers it transitively but is not reliably traced.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_wipe_short_circuits_on_clean_table(): void {
		$task = new Transients();

		// First invoke clears anything seeded; second hits the empty-rows
		// return path.
		Utility::invoke_hidden_method( $task, 'uninstall_site' );
		$this->assertNull(
			Utility::invoke_hidden_method( $task, 'uninstall_site' ),
			'A wipe with no plugin rows present should be a quiet no-op.'
		);
	}

	/**
	 * Transients belonging to other plugins are left alone.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_run_preserves_other_plugins_transients(): void {
		add_option( '_transient_other_plugin_value', 'untouched' );
		add_option(
			'_transient_timeout_other_plugin_value',
			time() + MINUTE_IN_SECONDS
		);

		( new Transients() )->run();

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
	 * Plugin settings (non-transient options) survive the transient task.
	 *
	 * The full settings + custom-table cleanup is owned by the #681
	 * follow-up; until that lands, only the transient cache is wiped.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_run_preserves_settings_options(): void {
		add_option( 'gatherpress_test_setting', 'unchanged' );

		( new Transients() )->run();

		$this->assertSame(
			'unchanged',
			get_option( 'gatherpress_test_setting' ),
			'GatherPress options must survive the transient-only uninstall task.'
		);

		delete_option( 'gatherpress_test_setting' );
	}

	/**
	 * Multisite flow: run() itself visits each subsite via the Base loop.
	 *
	 * The old procedural test had to call the per-site function by hand for
	 * each site; with the loop owned by Base, running the task end-to-end
	 * is the real network-uninstall path.
	 *
	 * @covers ::uninstall_site
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_run_wipes_transients_on_each_subsite(): void {
		$site_id_b = $this->factory()->blog->create();

		// Seed primary-site transients too so the run wipes both contexts.
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

		( new Transients() )->run();

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

			delete_option( '_transient_other_plugin_subsite_b' );
		} finally {
			restore_current_blog();
		}

		wp_delete_site( $site_id_b );
	}
}
