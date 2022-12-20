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
		__DIR__ . '/build/blocks/add-to-calendar'
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
	require plugin_dir_path( __FILE__ ) . 'build/blocks/event-date/template.php';

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
	ob_start();
	$timezone = get_option('timezone_string');
	if ( ! $timezone ) {
		$timezone = ' / timezone unset';
	}
	if ( ! isset( $attributes['venueId'] ) ) {
		return '<p>Venue unset' . $timezone . '</p>';
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
function gp_blocks_venue_information_render_callback( $attributes, $content, $block ) {
	ob_start();
	echo $content;
	echo '<p>' . __FUNCTION__ . '<pre>' . print_r( $attributes, true ) . '</pre>';
	// require plugin_dir_path( __FILE__ ) . 'build/blocks/venue-information/sample.php';

	$block_content = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf( '<div %s>%s</div>', $wrapper_attributes, $block_content  );

}

add_action( 'wp_enqueue_scripts', 'add_to_calendar_script' );
/**
 * Undocumented function
 *
 * @return void
 */
function add_to_calendar_script() {
	wp_register_script(
		'add-to-calendar',
		plugins_url( 'includes/core/classes/js/add-to-calendar.js', __FILE__ ),
		array(),
		filemtime( plugin_dir_path( __FILE__ ) . 'includes/core/classes/js/add-to-calendar.js' ),
		true
	);

	if ( 'gp_event' === get_post_type() ) {
		wp_enqueue_script( 'add-to-calendar' );
	}
}

// add_filter( 'render_block', 'show_the_block_constituents', 10, 2 );
/**
 * [show_the_block_constituents] Debug code for showing the parts of WP Blocks
 *
 * @param  [string] $block_content
 * @param  [array]  $block
 * @return [string]
 */
function show_the_block_constituents( $block_content, $block ) {
	if ( true === WP_DEBUG && current_user_can( 'administrator' ) ) {
		$block_content = "<div class='wp-block' data-blockType='{$block['blockName']}'>{$block_content}</div>" . ( 'string' === gettype( $block['blockName'] ) ? '<pre><xmp> $block_content = ' . gettype( $block_content ) . " {$block['blockName']} " . print_r( $block, true ) . '</xmp></pre>' : '' );
	}
	return $block_content;
}

// add_filter( 'render_block', 'wplancpa_2019_show_block_type', 10, 2 );
/**
 * Undocumented function
 *
 * @param [type] $block_content
 * @param [type] $block
 * @return void
 */
function wplancpa_2019_show_block_type( $block_content, $block ) {
	if ( true === WP_DEBUG ) {
		$block_content = "<h5 style=\"color:salmon\">{$block['blockName']}</h5><div class='wp-block' data-blockType='{$block['blockName']}'>{$block_content}</div>";
	}
	return $block_content;
}

add_action('admin_notices', 'timezone_check_admin_notice');
/**
 * display custom admin notice function
 *
 * @return void
 */
function timezone_check_admin_notice() {
	$timezone = get_option('timezone_string');
	if ( $timezone ) {
		return;
	}
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php _e('Please set your timezone in order to ensure proper GatherPress settings!', 'gatherpress'); ?></p>
	</div>
<?php
}

add_action( 'enqueue_block_editor_assets', 'maybe_deny_list_blocks' );
/**
 * Undocumented function
 *
 * @return void
 */
function maybe_deny_list_blocks() {
    wp_register_script(
        'post-deny-list-blocks',
        plugins_url( 'includes/core/classes/js/post-deny-list.js', __FILE__ ),
        array(
			'wp-blocks',
			'wp-dom-ready',
			'wp-edit-post'
		),
		filemtime( plugin_dir_path( __FILE__ ) . 'includes/core/classes/js/post-deny-list.js'),
		true
    );
    wp_register_script(
        'event-deny-list-blocks',
        plugins_url( 'includes/core/classes/js/event-deny-list.js', __FILE__ ),
        array(
			'wp-blocks',
			'wp-dom-ready',
			'wp-edit-post'
		),
		filemtime( plugin_dir_path( __FILE__ ) . 'includes/core/classes/js/event-deny-list.js'),
		true
    );
    wp_register_script(
        'venue-deny-list-blocks',
        plugins_url( 'includes/core/classes/js/venue-deny-list.js', __FILE__ ),
        array(
			'wp-blocks',
			'wp-dom-ready',
			'wp-edit-post'
		),
		filemtime( plugin_dir_path( __FILE__ ) . 'includes/core/classes/js/venue-deny-list.js'),
		true
    );
	if ( 'post' === get_post_type() || 'page' === get_post_type() ) {
		wp_enqueue_script( 'post-deny-list-blocks' );
	}
	if ( 'gp_event' === get_post_type() ) {
		wp_enqueue_script( 'event-deny-list-blocks' );
	}
	if ( 'gp_venue' === get_post_type() ) {
		wp_enqueue_script( 'venue-deny-list-blocks' );
	}
}

