<?php
/**
 * Plugin Name:       GatherPress
 * Plugin URI:        https://github.com/GatherPress/gatherpress
 * Description:       Powering Communities with WordPress.
 * Author:            The GatherPress Community
 * Author URI:        https://gatherpress.org/
 * Version:           0.32.0-alpha.1
 * Requires PHP:      7.4
 * Requires at least: 6.6
 * Text Domain:       gatherpress
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
define( 'GATHERPRESS_CACHE_GROUP', 'gatherpress_cache' );
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

//add_action( 'wp', function() {
//
//	$block_data = '{"blockName":"gatherpress/rsvp-template","attrs":[],"innerBlocks":[{"blockName":"core/group","attrs":{"style":{"border":{"width":"1px","radius":"20px"},"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"shadow":"var:preset|shadow|outlined"}},"innerBlocks":[{"blockName":"core/avatar","attrs":{"isLink":true,"align":"center"},"innerBlocks":[],"innerHTML":"","innerContent":[]},{"blockName":"core/comment-author-name","attrs":{"textAlign":"center","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"medium"},"innerBlocks":[],"innerHTML":"","innerContent":[]}],"innerHTML":"\\n<div class=\\"wp-block-group\\" style=\\"border-width:1px;border-radius:20px;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);box-shadow:var(--wp--preset--shadow--outlined)\\">\\n\\n<\\/div>\\n","innerContent":["\\n<div class=\\"wp-block-group\\" style=\\"border-width:1px;border-radius:20px;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);box-shadow:var(--wp--preset--shadow--outlined)\\">",null,"\\n\\n",null,"<\\/div>\\n"]}],"innerHTML":"\\n\\n","innerContent":["\\n",null,"\\n"],"parentLayout":{"type":"grid","columns":3,"justifyContent":"center","alignContent":"space-around","minimumColumnWidth":"8rem"}}';
//
//	$block_data = json_decode( $block_data, true );
//	$response_id = 109;
//	$block_content = ( new \WP_Block( $block_data, array( 'commentId' => $response_id ) ) )->render( array( 'dynamic' => false ) );
//	$content = sprintf( '<div id="rsvp-%1$d">%2$s</div>', $response_id, $block_content );
//	echo $content; die;
//});
