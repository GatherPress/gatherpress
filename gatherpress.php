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


add_action( 'rest_api_init', __NAMESPACE__ . '\sample_endpoints' );
/**
 * Create custom endpoints for block settings
 */
function sample_endpoints() {

	register_rest_route(
		GATHERPRESS_REST_NAMESPACE,
		'initial-time/',
		[
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => __NAMESPACE__ . '\get_initial_time',
			'permission_callback' => __NAMESPACE__ . '\check_permissions',
		]
	);

	register_rest_route(
		GATHERPRESS_REST_NAMESPACE,
		'initial-time/',
		[
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => __NAMESPACE__ . '\update_initial_time',
			'permission_callback' => __NAMESPACE__ . '\check_permissions',
		]
	);

	register_rest_route(
		GATHERPRESS_REST_NAMESPACE,
		'end-time/',
		[
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => __NAMESPACE__ . '\get_end_time',
			'permission_callback' => __NAMESPACE__ . '\check_permissions',
		]
	);

	register_rest_route(
		GATHERPRESS_REST_NAMESPACE,
		'end-time/',
		[
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => __NAMESPACE__ . '\update_end_time',
			'permission_callback' => __NAMESPACE__ . '\check_permissions',
		]
	);

}

function get_initial_time() {

	$initial_time = get_option( ADVGUTZAC_BLOCK_SETTING );

	$response = new \WP_REST_Response( $initial_time );
	$response->set_status( 200 );

	return $response;
}

function update_initial_time( $request ) {

	$new_initial_time = $request->get_body();
	update_option( ADVGUTZAC_BLOCK_SETTING, $new_initial_time );

	$initial_time = get_option( ADVGUTZAC_BLOCK_SETTING );
	$response      = new \WP_REST_Response( $initial_time );
	$response->set_status( 201 );

	return $response;

}


function get_end_time() {

	$end_time = get_option( ADVGUTZAC_BLOCK_SETTING );

	$response = new \WP_REST_Response( $end_time );
	$response->set_status( 200 );

	return $response;
}

function update_end_time( $request ) {

	$new_end_time = $request->get_body();
	update_option( ADVGUTZAC_BLOCK_SETTING, $new_end_time );

	$initial_time = get_option( ADVGUTZAC_BLOCK_SETTING );
	$response      = new \WP_REST_Response( $initial_time );
	$response->set_status( 201 );

	return $response;

}

function check_permissions() {
	return current_user_can( 'edit_posts' );
}

/**
 * Save the start and end datetimes for an event.
 *
 * @since 1.0.0
 *
 * @param array $params {
 *     An array of arguments used to save event data to custom event table.
 *
 *     @type string  $datetime_start Start DateTime to save for event.
 *     @type string  $datetime_end   End DateTime to save for event.
 *     @type string  $timezone       Timezone of the event.
 *
 * }
 *
 * @return bool
 */
function sample_save_datetimes( array $params ): bool {
	global $wpdb;

	$params['post_id'] = $this->event->ID;
	$retval            = false;
	$fields            = array_filter(
		$params,
		function( $key ) {
			return in_array(
				$key,
				array(
					'post_id',
					'datetime_start',
					'datetime_end',
					'timezone',
				),
				true
			);
		},
		ARRAY_FILTER_USE_KEY
	);

	if ( 1 > intval( $fields['post_id'] ) ) {
		return $retval;
	}

	$fields['datetime_start_gmt'] = get_gmt_from_date( $fields['datetime_start'] );
	$fields['datetime_end_gmt']   = get_gmt_from_date( $fields['datetime_end'] );
	$fields['timezone']           = ( ! empty( $fields['timezone'] ) ) ? $fields['timezone'] : wp_timezone_string();
	// $table                        = sprintf( static::TABLE_FORMAT, $wpdb->prefix );

	// @todo Add caching to this and create new method to check existence.
	$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			'SELECT post_id FROM ' . esc_sql( $table ) . ' WHERE post_id = %d',
			$fields['post_id']
		)
	);

	if ( ! empty( $exists ) ) {
		$retval = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			$fields,
			array( 'post_id' => $fields['post_id'] )
		);
		wp_cache_delete( DATETIME_CACHE_KEY, $fields['post_id'] );
	} else {
		$retval = $wpdb->insert( $table, $fields ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	return (bool) $retval;
}


add_filter(
	'the_content',
	function( $the_content ) {
		if ( 'gp_event' !== get_post_type() ) {
			return;
		}
		$event = new \GatherPress\Core\Event( get_the_ID() );
		// $event = new \GatherPress\Core\Event( get_the_ID() );
		return $the_content . '<pre>$event info' . print_r( $event, true ) . '</pre>';
		return $the_content . '<h4>' . __FILE__ . '</h4>';
	}
);
