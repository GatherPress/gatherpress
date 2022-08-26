<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         GatherPress adds event management and more to WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.4
 * Minimum PHP Version: 7.3
 * Text Domain:         gatherpress
 * License:             GPLv2 or later (license.txt)
 *
 * @package GatherPress
 */

// Constants.
define( 'GATHERPRESS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_MINIMUM_PHP_VERSION', current( get_file_data( __FILE__, array( 'Minimum PHP Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_CURRENT_PHP_VERSION', phpversion() );
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

/**
 * Check version of PHP before loading plugin.
 */
if ( version_compare( GATHERPRESS_CURRENT_PHP_VERSION, GATHERPRESS_MINIMUM_PHP_VERSION, '<' ) ) {
	add_action( 'admin_notices', function() {
		?>
		<div class="notice notice-error">
			<p>
				<?php echo sprintf(
					/* translators: %1$s: minimum PHP version, %2$s current PHP version. */
					esc_html__(
						'GatherPress requires PHP Version %1$s or greater. You are currently running PHP %2$s. Please upgrade.',
						'gatherpress'
					),
					GATHERPRESS_MINIMUM_PHP_VERSION,
					GATHERPRESS_CURRENT_PHP_VERSION
					);
				?>
			</p>
		</div>
		<?php
	} );

	return;
}

require_once GATHERPRESS_CORE_PATH . '/core/loader.php';
require_once GATHERPRESS_CORE_PATH . '/buddypress/loader.php';
