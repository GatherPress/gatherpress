<?php
/**
 * Plugin Name:       GatherPress
 * Plugin URI:        https://github.com/GatherPress/gatherpress
 * Description:       Powering Communities with WordPress.
 * Author:            The GatherPress Community
 * Author URI:        https://gatherpress.org/
 * Version:           0.31.0-alpha
 * Requires PHP:      7.4
 * Requires at least: 6.4
 * Text Domain:       gatherpress
 * Domain Path:       /languages
 * License:           GNU General Public License v2.0 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * This file serves as the main plugin file for GatherPress. It defines the plugin's basic information,
 * constants, and initializes the plugin.
 *
 * @package GatherPress
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Constants.
define( 'GATHERPRESS_CORE_FILE', __FILE__ );
define( 'GATHERPRESS_CORE_PATH', __DIR__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_DIR_NAME', dirname( plugin_basename( __FILE__ ) ) );
define( 'GATHERPRESS_REQUIRES_PHP', current( get_file_data( __FILE__, array( 'Requires PHP' ), 'plugin' ) ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );
define( 'GATHERPRESS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );

// Check if the minimum plugin requirements are not met and prevent further execution if necessary.
if ( ! require_once GATHERPRESS_CORE_PATH . '/includes/core/requirements-check.php' ) {
	return;
}

// Include and register the autoloader class for automatic loading of plugin classes.
require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/class-autoloader.php';
GatherPress\Core\Autoloader::register();

// Initialize setups.
GatherPress\Core\Setup::get_instance();


function gatherpress_test_meta( $meta_id, $object_id, $meta_key, $meta_value) {
	if ( $meta_key === 'gatherpress_datetime' ) {
		$data = json_decode( $meta_value, true ) ?? array();
		$event = new GatherPress\Core\Event( $object_id );
		$params = array(
			'post_id' => $object_id,
			'datetime_start' => $data['dateTimeStart'],
			'datetime_end' => $data['dateTimeEnd'],
			'timezone' => $data['timezone'],
		);
		$event->save_datetimes( $params );
	}
}

function gatherpress_meta_save($post_id, $post, $update) {
	if ('gatherpress_event' !== $post->post_type) {
		return;
	}
	$event = new \GatherPress\Core\Event($post_id);
	$fields = $event->get_datetime();

	foreach ( $fields as $key => $field ) {
		$meta_key = sprintf( 'gatherpress_%s', sanitize_key( $key ) );

		update_post_meta(
			$post_id,
			$meta_key,
			sanitize_text_field( $field )
		);
	}

}

add_action( 'added_post_meta', 'gatherpress_test_meta', 10, 4);
add_action( 'updated_post_meta', 'gatherpress_test_meta', 10, 4);
add_action( 'wp_after_insert_post', 'gatherpress_meta_save', 10, 3 );
