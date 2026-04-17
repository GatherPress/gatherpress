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
	 * Uses priority 2 so Venues renders between Events (PHP_INT_MIN) and
	 * everything else (Rsvp_Settings, Tools, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @return int The priority for displaying the venues settings page.
	 */
	protected function get_priority(): int {
		return 2;
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
					'Configure the mapping platform used for venue display.',
					'gatherpress'
				),
				'options'     => array(
					'map_platform' => array(
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
				),
			),
		);
	}
}
