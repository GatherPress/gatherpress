<?php
/**
 * Shared setup for the uninstall task tests.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Network;
use GatherPress\Core\Uninstall\Preferences;

/**
 * Trait Preferences_Fixture.
 *
 * The opt-ins are ordinary GatherPress settings, so arming one in a test
 * means writing the settings option and dropping the resolved map that
 * `Preferences` holds for the rest of the request.
 *
 * @since 0.36.0
 */
trait Preferences_Fixture {

	/**
	 * Opt in to one or more uninstall tasks for the current site.
	 *
	 * @since 0.36.0
	 *
	 * @param string ...$tasks Task keys from `Preferences::task_keys()`.
	 *
	 * @return void
	 */
	protected function arm_uninstall( string ...$tasks ): void {
		$settings = (array) get_option( Settings::OPTION_NAME, array() );

		foreach ( $tasks as $task ) {
			$settings[ Preferences::option_key( $task ) ] = true;
		}

		update_option( Settings::OPTION_NAME, $settings );
		Preferences::flush_cache();
	}

	/**
	 * Opt in to one or more uninstall tasks for the whole network.
	 *
	 * @since 0.36.0
	 *
	 * @param string ...$tasks Task keys from `Preferences::task_keys()`.
	 *
	 * @return void
	 */
	protected function arm_uninstall_for_network( string ...$tasks ): void {
		$settings = (array) get_site_option( Settings::OPTION_NAME, array() );
		$keys     = array();

		foreach ( $tasks as $task ) {
			$key              = Preferences::option_key( $task );
			$settings[ $key ] = true;
			$keys[]           = $key;
		}

		update_site_option( Settings::OPTION_NAME, $settings );
		update_site_option(
			Network::OPTION_NAME,
			array(
				'enabled'   => true,
				'inherited' => $keys,
			)
		);

		Network::flush_config_cache();
		Preferences::flush_cache();
	}

	/**
	 * Put the stored settings back the way the test found them.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function reset_uninstall_preferences(): void {
		delete_option( Settings::OPTION_NAME );
		delete_site_option( Settings::OPTION_NAME );
		delete_site_option( Network::OPTION_NAME );

		Network::flush_config_cache();
		Preferences::flush_cache();
	}
}
