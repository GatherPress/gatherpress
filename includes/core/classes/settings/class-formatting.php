<?php
/**
 * Formatting settings page for GatherPress.
 *
 * This class handles the "Formatting" settings page in GatherPress, providing
 * options for configuring date and time formats, timezone display, and maps.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Formatting.
 *
 * Handles the "Formatting" settings page for GatherPress.
 *
 * @since 1.0.0
 */
class Formatting extends Base {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the formatting settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug for the formatting settings page.
	 */
	protected function get_slug(): string {
		return 'formatting';
	}

	/**
	 * Get the name for the formatting settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The localized name for the formatting settings page.
	 */
	protected function get_name(): string {
		return __( 'Formatting', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the formatting settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return int The priority for displaying the formatting settings page.
	 */
	protected function get_priority(): int {
		return 3;
	}

	/**
	 * Get sections and options for the Formatting settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of sections and options for the Formatting settings page.
	 */
	protected function get_sections(): array {
		return array(
			'date_time' => array(
				'name'        => __( 'Date & Time', 'gatherpress' ),
				'description' => __(
					// phpcs:disable Generic.Files.LineLength.TooLong
					'For more information read the <a href="https://wordpress.org/documentation/article/customize-date-and-time-format/">Documentation on date and time formatting</a>.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
				'options'     => array(
					'date_format'   => array(
						'labels' => array(
							'name' => __( 'Date Format', 'gatherpress' ),
						),
						'field'  => array(
							'label'   => __( 'Format of date for scheduled events.', 'gatherpress' ),
							'type'    => 'text',
							'size'    => 'regular',
							'options' => array(
								'default' => get_option( 'date_format', 'l, F j, Y' ),
							),
							'preview' => array(
								'template' => 'datetime-preview',
							),
						),
					),
					'time_format'   => array(
						'labels' => array(
							'name' => __( 'Time Format', 'gatherpress' ),
						),
						'field'  => array(
							'label'   => __( 'Format of time for scheduled events.', 'gatherpress' ),
							'type'    => 'text',
							'size'    => 'regular',
							'options' => array(
								'default' => get_option( 'time_format', 'g:i A' ),
							),
							'preview' => array(
								'template' => 'datetime-preview',
							),
						),
					),
					'show_timezone' => array(
						'labels' => array(
							'name' => __( 'Show Timezone', 'gatherpress' ),
						),
						'field'  => array(
							'label'   => __(
								'Display the timezone for scheduled events.',
								'gatherpress'
							),
							'type'    => 'checkbox',
							'options' => array(
								'default' => true,
							),
						),
					),
				),
			),
			'maps'      => array(
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
