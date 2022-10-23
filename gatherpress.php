<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         GatherPress adds event management and more to WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.10
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
define( 'GATHERPRESS_CORE_FILE', __FILE__ );
define( 'GATHERPRESS_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GATHERPRESS_REST_NAMESPACE', 'gatherpress/v1' );

// Bail if things do not meet minimum plugin requirements.
if ( ! require_once GATHERPRESS_CORE_PATH . '/core/preflight.php' ) {
	return;
}

require_once GATHERPRESS_CORE_PATH . '/core/classes/class-autoloader.php';

GatherPress\Core\Autoloader::register();
GatherPress\Core\Setup::get_instance();
GatherPress\BuddyPress\Setup::get_instance();

add_action( 'init', 'gatherpress_different_blocks_init' );
/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function gatherpress_different_blocks_init() {
	register_block_type(
		__DIR__ . '/assets/build/blocks/add-to-calendar',
		array(
			'render_callback' => 'gatherpress_render_add_to_calendar',
		)
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/attendance-list'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/attendance-selector'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/initial-time'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/end-time'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/event-date',
		array(
			'render_callback' => 'gatherpress_render_event_date',
		)
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/time-template'
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/venue',
		array(
			'render_callback' => 'gatherpress_render_venue',
		)
	);
	register_block_type(
		__DIR__ . '/assets/build/blocks/venue-information',
		array(
			'render_callback' => 'gatherpress_render_venue_information',
		)
	);
}

add_filter(
	'the_content',
	function( $the_content ) {
		if ( 'gp_event' !== get_post_type() ) {
			return;
		}
		$asset = plugin_dir_path( GATHERPRESS_CORE_FILE ) . 'assets/build/blocks/event-date/index.asset.php';
		// $event = new \GatherPress\Core\Event( get_the_ID() );
		return $the_content . '<pre>$event info' . print_r( $asset, true ) . '</pre>';
		return $the_content . '<h4>' . __FILE__ . '</h4>';
	}
);

/**
 * Render callback function.
 *
 * @param array    $attributes The block attributes.
 * @param string   $content    The block content.
 * @param WP_Block $block      Block instance.
 *
 * @return string The rendered output.
 */
function gatherpress_render_add_to_calendar( $attributes, $content, $block ) {
	ob_start();
	require plugin_dir_path( __FILE__ ) . '/assets/build/blocks/add-to-calendar/add-to-calendar.php';
	return ob_get_clean();
}

/**
 * Render callback function.
 *
 * @param array    $attributes The block attributes.
 * @param string   $content    The block content.
 * @param WP_Block $block      Block instance.
 *
 * @return string The rendered output.
 */
function gatherpress_render_event_date( $attributes, $content, $block ) {
	ob_start();
	require plugin_dir_path( __FILE__ ) . '/assets/build/blocks/event-date/event-date.php';
	return ob_get_clean();
}

/**
 * Render callback function.
 *
 * @param array    $attributes The block attributes.
 * @param string   $content    The block content.
 * @param WP_Block $block      Block instance.
 *
 * @return string The rendered output.
 */
function gatherpress_render_venue( $attributes, $content, $block ) {
	ob_start();
	require plugin_dir_path( __FILE__ ) . '/assets/build/blocks/venue/venue.php';
	return ob_get_clean();
}

/**
 * Render callback function.
 *
 * @param array    $attributes The block attributes.
 * @param string   $content    The block content.
 * @param WP_Block $block      Block instance.
 *
 * @return string The rendered output.
 */
function gatherpress_render_venue_information( $attributes, $content, $block ) {
	ob_start();
	require plugin_dir_path( __FILE__ ) . '/assets/build/blocks/venue-information/venue-information.php';
	return ob_get_clean();
}
