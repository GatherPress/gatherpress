<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         GatherPress adds event management and more to WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.4.1
 * Minimum PHP Version: 7.3
 * Text Domain:         gatherpress
 * License:             GPLv2 or later (license.txt)
 *
 * @package GatherPress
 */

// Constants.
define( 'GATHERPRESS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_MINIMUM_PHP_VERSION', current( get_file_data( __FILE__, array( 'Minimum PHP Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

/**
 * Check version of PHP before loading plugin.
 */
if ( version_compare( PHP_VERSION_ID, GATHERPRESS_MINIMUM_PHP_VERSION, '<' ) ) {
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo sprintf(
						/* translators: %1$s: minimum PHP version, %2$s current PHP version. */
						esc_html__(
							'GatherPress requires PHP Version %1$s or greater. You are currently running PHP %2$s. Please upgrade.',
							'gatherpress'
						),
						esc_html( GATHERPRESS_MINIMUM_PHP_VERSION ),
						esc_html( PHP_VERSION_ID )
					);
					?>
				</p>
			</div>
			<?php
		}
	);

	return;
}

require_once GATHERPRESS_CORE_PATH . '/core/loader.php';
require_once GATHERPRESS_CORE_PATH . '/buddypress/loader.php';

register_activation_hook( __FILE__, 'activate_gatherpress_plugin' );
/**
 * Activate GatherPress plugin.
 *
 * @return void
 */
function activate_gatherpress_plugin() {
	GatherPress\Core\Setup::register_post_types();
	if ( ! get_option( 'gatherpress_flush_rewrite_rules_flag' ) ) {
		add_option( 'gatherpress_flush_rewrite_rules_flag', true );
	}
}

register_deactivation_hook( __FILE__, 'deactivate_gatherpress_plugin' );
/**
 * Activate GatherPress plugin.
 *
 * @return void
 */
function deactivate_gatherpress_plugin() {
	flush_rewrite_rules();
}

add_action( 'init', 'maybe_flush_gatherpress_rewrite_rules', 20 );
/**
 * Flush rewrite rules if the previously added flag exists,
 * and then remove the flag.
 */
function maybe_flush_gatherpress_rewrite_rules() {
	if ( get_option( 'gatherpress_flush_rewrite_rules_flag' ) ) {
		flush_rewrite_rules();
		delete_option( 'gatherpress_flush_rewrite_rules_flag' );
	}
}
