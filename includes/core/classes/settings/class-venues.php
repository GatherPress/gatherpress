<?php
/**
 * Venues settings page for GatherPress.
 *
 * This class handles the "Venues" settings page in GatherPress, providing
 * options for configuring the venue-related behavior of the plugin (mapping
 * platform today; future: static-map caching TTL, provider overrides, etc.).
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue_Map;

/**
 * Class Venues.
 *
 * Handles the "Venues" settings page for GatherPress.
 *
 * @since 1.0.0
 */
class Venues extends Base {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the venues settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug for the venues settings page.
	 */
	protected function get_slug(): string {
		return 'venues';
	}

	/**
	 * Get the name for the venues settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The localized name for the venues settings page.
	 */
	protected function get_name(): string {
		return __( 'Venues', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the venues settings page.
	 *
	 * Priority 1 places Venues immediately after Events (PHP_INT_MIN) and
	 * before Rsvp_Settings (priority 2), so the tabs flow content → venue →
	 * RSVP rather than relying on class-setup.php registration order.
	 *
	 * @since 1.0.0
	 *
	 * @return int The priority for displaying the venues settings page.
	 */
	protected function get_priority(): int {
		return 1;
	}

	/**
	 * Get sections and options for the Venues settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of sections and options for the Venues settings page.
	 */
	protected function get_sections(): array {
		return array(
			'maps' => array(
				'name'        => __( 'Maps', 'gatherpress' ),
				'description' => __(
					'Configure the mapping platform and defaults applied to new venue map blocks.',
					'gatherpress'
				),
				'options'     => array(
					'map_platform'                   => array(
						'labels'      => array(
							'name' => __( 'Mapping Platform', 'gatherpress' ),
						),
						'description' => __(
							'Select the platform you would like to render maps with.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'Selected Mapping Platform:', 'gatherpress' ),
							'type'    => 'select',
							'options' => array(
								'default' => 'osm',
								'items'   => array(
									'osm'    => __( 'OpenStreetMap', 'gatherpress' ),
									'google' => __( 'Google Maps', 'gatherpress' ),
								),
							),
						),
					),
					'venue_map_default_render_mode'  => array(
						'labels'      => array(
							'name' => __( 'Default Render Mode', 'gatherpress' ),
						),
						'description' => __(
							'Default rendering mode applied to new venue map blocks.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'Render mode for new blocks:', 'gatherpress' ),
							'type'    => 'select',
							'options' => array(
								'default' => Venue_Map::DEFAULT_RENDER_MODE,
								'items'   => array(
									'interactive' => __( 'Interactive', 'gatherpress' ),
									'static'      => __( 'Static image', 'gatherpress' ),
								),
							),
						),
					),
					'venue_map_default_zoom'         => array(
						'labels'      => array(
							'name' => __( 'Default Zoom Level', 'gatherpress' ),
						),
						'description' => __(
							'Default zoom applied to new venue map blocks.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'Zoom level for new blocks:', 'gatherpress' ),
							'type'    => 'number',
							'size'    => 'small',
							'options' => array(
								'default' => Venue_Map::DEFAULT_ZOOM,
								'min'     => '1',
								'max'     => '20',
							),
						),
					),
					'venue_map_default_height'       => array(
						'labels'      => array(
							'name' => __( 'Default Height', 'gatherpress' ),
						),
						'description' => __(
							'Default pixel height for new venue map blocks. Leave empty for auto.',
							'gatherpress'
						),
						'field'       => array(
							'label'       => __( 'Height for new blocks (px):', 'gatherpress' ),
							'type'        => 'number',
							'size'        => 'small',
							'placeholder' => __( 'Auto', 'gatherpress' ),
							'allow_empty' => true,
							'options'     => array(
								'default' => '',
								'min'     => '0',
								'max'     => (string) Venue_Map::HEIGHT_MAX,
							),
						),
					),
					'venue_map_default_width'        => array(
						'labels'      => array(
							'name' => __( 'Default Width', 'gatherpress' ),
						),
						'description' => __(
							'Default pixel width for new venue map blocks. Leave empty for auto.',
							'gatherpress'
						),
						'field'       => array(
							'label'       => __( 'Width for new blocks (px):', 'gatherpress' ),
							'type'        => 'number',
							'size'        => 'small',
							'placeholder' => __( 'Auto', 'gatherpress' ),
							'allow_empty' => true,
							'options'     => array(
								'default' => '',
								'min'     => '0',
								'max'     => (string) Venue_Map::WIDTH_MAX,
							),
						),
					),
					'venue_map_default_aspect_ratio' => array(
						'labels'      => array(
							'name' => __( 'Default Aspect Ratio', 'gatherpress' ),
						),
						'description' => __(
							'Default aspect ratio for new venue map blocks. Used to derive any auto dimension.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'Aspect ratio for new blocks:', 'gatherpress' ),
							'type'    => 'select',
							'options' => array(
								'default' => Venue_Map::DEFAULT_ASPECT_RATIO,
								'items'   => array(
									'2/1'  => __( '2:1 (landscape)', 'gatherpress' ),
									'16/9' => __( '16:9 (wide)', 'gatherpress' ),
									'3/2'  => __( '3:2 (classic)', 'gatherpress' ),
									'4/3'  => __( '4:3 (standard)', 'gatherpress' ),
									'1/1'  => __( '1:1 (square)', 'gatherpress' ),
								),
							),
						),
					),
					'venue_map_default_type'         => array(
						'labels'      => array(
							'name' => __( 'Default Map Type', 'gatherpress' ),
						),
						'description' => __(
							// phpcs:ignore Generic.Files.LineLength.TooLong -- Single translator-facing sentence; keep on one line for .pot extractor.
							'Default map type for new venue map blocks. Only rendered by Google Maps; OpenStreetMap and static images ignore this value.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'Map type for new blocks:', 'gatherpress' ),
							'type'    => 'select',
							'options' => array(
								'default' => Venue_Map::DEFAULT_MAP_TYPE,
								'items'   => array(
									'roadmap'   => __( 'Roadmap', 'gatherpress' ),
									'satellite' => __( 'Satellite', 'gatherpress' ),
									'hybrid'    => __( 'Hybrid', 'gatherpress' ),
									'terrain'   => __( 'Terrain', 'gatherpress' ),
								),
							),
						),
					),
				),
			),
		);
	}
}
