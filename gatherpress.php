<?php
/**
 * Plugin Name:         GatherPress
 * Plugin URI:          https://gatherpress.org/
 * Description:         Powering Communities with WordPress.
 * Author:              The GatherPress Community
 * Author URI:          https://gatherpess.org/
 * Version:             0.13.0
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
if ( ! require_once GATHERPRESS_CORE_PATH . '/includes/core/preflight.php' ) {
	return;
}

require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/class-autoloader.php';

GatherPress\Core\Autoloader::register();
GatherPress\Core\Setup::get_instance();
GatherPress\BuddyPress\Setup::get_instance();


add_action( 'init', 'gatherpress_gp_blocks_init' );

function gatherpress_gp_blocks_init() {
	register_block_type(
		__DIR__ . '/build/blocks/add-to-calendar',
		[
			'render_callback' => 'gp_blocks_add_to_calendar_render_callback'
		]
	);
	register_block_type(
		__DIR__ . '/build/blocks/attendance-list',
		[
			'render_callback' => 'gp_blocks_attendance_list_render_callback'
		]
	);
	register_block_type(
		__DIR__ . '/build/blocks/attendance-selector'
	);
	register_block_type(
		__DIR__ . '/build/blocks/event-date',
		[
			'render_callback' => 'gp_blocks_event_date_render_callback'
		]
	);
	register_block_type(
		__DIR__ . '/build/blocks/events-list',
		[
			'render_callback' => 'gp_blocks_events_list_render_callback'
		]
	);
	register_block_type(
		__DIR__ . '/build/blocks/venue',
		[
			'render_callback' => 'gp_blocks_venue_render_callback'
		]
	);
	register_block_type(
		__DIR__ . '/build/blocks/venue-information'
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
function gp_blocks_add_to_calendar_render_callback( $attributes, $content, $block ) {
	ob_start();
	require plugin_dir_path( __FILE__ ) . 'build/blocks/add-to-calendar/render.php';

	$block_content = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );
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
function gp_blocks_attendance_list_render_callback( $attributes, $content, $block ) {
	ob_start();
	require plugin_dir_path( __FILE__ ) . 'build/blocks/attendance-list/sample.php';

	$block_content = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );
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
function gp_blocks_attendance_selector_render_callback( $attributes, $content, $block ) {
	ob_start();
	require plugin_dir_path( __FILE__ ) . 'build/blocks/attendance-selector/sample.php';

	$block_content = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );

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
function gp_blocks_example_dynamic_render_callback( $attributes, $content, $block ) {
	ob_start();

	require plugin_dir_path( __FILE__ ) . 'build/blocks/example-dynamic/template.php';

	$block_content = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );
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
function gp_blocks_event_date_render_callback( $attributes, $content, $block ) {
	ob_start();
	require plugin_dir_path( __FILE__ ) . 'build/blocks/event-date/render.php';

	$block_content = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );
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
function gp_blocks_events_list_render_callback( $attributes, $content, $block ) {
	ob_start();
	echo '<h3>build/blocks/events-list/sample.php</h3>';
	echo  $content;

	$block_content = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );

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
function gp_blocks_venue_render_callback( $attributes, $content, $block ) {
	$wrapper_attributes = get_block_wrapper_attributes();

	ob_start();
	$timezone = get_option('timezone_string');
	if ( ! $timezone ) {
		$timezone = ' / timezone unset';
	}
	if ( ! isset( $attributes['venueId'] ) ) {
		return sprintf( '<div %s>%s</div>', $wrapper_attributes, 'Venue unset ' . $timezone );
	}
	$gatherpress_venue = get_post( intval( $attributes['venueId'] ) );

	$gatherpress_venue_information = json_decode( get_post_meta( $gatherpress_venue->ID, '_venue_information', true ) );
	?>
	<div class="gp-venue">
		<?php
		GatherPress\Core\Utility::render_template(
			sprintf( '%s/templates/blocks/venue-information.php', GATHERPRESS_CORE_PATH ),
			array(
				'gatherpress_block_attrs' => array(
					'name'        => $gatherpress_venue->post_title,
					'fullAddress' => $gatherpress_venue_information->fullAddress ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'phoneNumber' => $gatherpress_venue_information->phoneNumber ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'website'     => $gatherpress_venue_information->website ?? '',
				),
			),
			true
		);
		?>
	</div>
	<?php

	$block_content = ob_get_clean();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );

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
function gp_blocks_venue_information_render_callback( $attributes, $content, $block ) {
	ob_start();
	echo $content;
	echo '<p>' . __FUNCTION__ . '<pre>' . print_r( $attributes, true ) . '</pre>';
	// require plugin_dir_path( __FILE__ ) . 'build/blocks/venue-information/sample.php';

	$block_content = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );

}
