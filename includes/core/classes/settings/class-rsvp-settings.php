<?php
/**
 * RSVP settings page for GatherPress.
 *
 * This class handles the "RSVP" settings page in GatherPress, providing options
 * for configuring attendance limits, guest limits, anonymous RSVPs, and cleanup.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Rsvp_Settings.
 *
 * Handles the "RSVP" settings page for GatherPress.
 *
 * @since 0.34.0
 */
class Rsvp_Settings extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the RSVP settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string The slug for the RSVP settings page.
	 */
	protected function get_slug(): string {
		return 'rsvp_settings';
	}

	/**
	 * Get the name for the RSVP settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string The localized name for the RSVP settings page.
	 */
	protected function get_name(): string {
		return __( 'RSVP', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the RSVP settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return int The priority for displaying the RSVP settings page.
	 */
	protected function get_priority(): int {
		return 2;
	}

	/**
	 * Get sections and options for the RSVP settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return array An array of sections and options for the RSVP settings page.
	 */
	protected function get_sections(): array {
		return array(
			'rsvp_defaults' => array(
				'name'        => __( 'RSVP Defaults', 'gatherpress' ),
				'description' => __(
					'Default RSVP settings for new events. These can be overridden per event.',
					'gatherpress'
				),
				'options'     => array(
					'rsvp_mode'             => array(
						'labels'      => array(
							'name' => __( 'RSVP Mode', 'gatherpress' ),
						),
						'description' => __(
							'Control how RSVP works across your site.',
							'gatherpress'
						),
						'field'       => array(
							'type'    => 'select',
							'options' => array(
								'default' => 'all_on',
								'items'   => array(
									'all_on'        => __( 'All events', 'gatherpress' ),
									'per_event_on'  => __( 'Per event (default on)', 'gatherpress' ),
									'per_event_off' => __( 'Per event (default off)', 'gatherpress' ),
									'disabled'      => __( 'Disabled', 'gatherpress' ),
								),
							),
						),
					),
					'enable_open_rsvp'      => array(
						'labels'      => array(
							'name' => __( 'Open RSVP', 'gatherpress' ),
						),
						'description' => __(
							'Allow visitors to RSVP without a site account using email verification.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __(
								'Enable Open RSVP for events.',
								'gatherpress'
							),
							'type'    => 'checkbox',
							'options' => array(
								'default' => true,
							),
						),
					),
					'max_attendance_limit'  => array(
						'labels'      => array(
							'name' => __( 'Maximum Attendance Limit', 'gatherpress' ),
						),
						'description' => __(
							// phpcs:disable Generic.Files.LineLength.TooLong
							'The total number of people allowed at an event. Set to 0 for no limit.',
							// phpcs:enable Generic.Files.LineLength.TooLong
							'gatherpress'
						),
						'field'       => array(
							'label'   => __(
								'The default maximum limit of attendees to an event.',
								'gatherpress'
							),
							'type'    => 'number',
							'size'    => 'small',
							'options' => array(
								'default' => 50,
							),
						),
					),
					'max_guest_limit'       => array(
						'labels'      => array(
							'name' => __( 'Maximum Number of Guests', 'gatherpress' ),
						),
						'description' => __(
							'The maximum number of additional guests each attendee can bring.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __(
								'The default maximum number of guests per attendee.',
								'gatherpress'
							),
							'type'    => 'number',
							'size'    => 'small',
							'options' => array(
								'default' => 0,
								'min'     => '0',
								'max'     => '5',
							),
						),
					),
					'enable_anonymous_rsvp' => array(
						'labels'      => array(
							'name' => __( 'Anonymous RSVP', 'gatherpress' ),
						),
						'description' => __(
							'Allow users to RSVP without revealing their identity.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __(
								'Enable Anonymous RSVP for new events.',
								'gatherpress'
							),
							'type'    => 'checkbox',
							'options' => array(
								'default' => false,
							),
						),
					),
				),
			),
			'rsvp_cleanup'  => array(
				'name'        => __( 'Cleanup', 'gatherpress' ),
				'description' => __(
					'Schedule automatic cleanup of unverified RSVPs.',
					'gatherpress'
				),
				'options'     => array(
					'rsvp_cleanup_switch'    => array(
						'labels'      => array(
							'name' => __( 'Toggle RSVP Cleanup', 'gatherpress' ),
						),
						'description' => __(
							'Enable or disable unverified RSVP cleanup.',
							'gatherpress'
						),
						'field'       => array(
							'type'    => 'select',
							'options' => array(
								'default' => 'off',
								'items'   => array(
									'off' => __( 'Disable', 'gatherpress' ),
									'on'  => __( 'Enable', 'gatherpress' ),
								),
							),
						),
					),
					'rsvp_cleanup_frequency' => array(
						'labels'      => array(
							'name' => __( 'Cleanup Frequency', 'gatherpress' ),
						),
						'description' => __(
							'How often the system should check for and remove unverified RSVPs.',
							'gatherpress'
						),
						'field'       => array(
							'type'    => 'select',
							'options' => array(
								'default' => 'daily',
								'items'   => array(
									'hourly'  => __( 'Hourly', 'gatherpress' ),
									'daily'   => __( 'Daily', 'gatherpress' ),
									'weekly'  => __( 'Weekly', 'gatherpress' ),
									'monthly' => __( 'Monthly', 'gatherpress' ),
									'yearly'  => __( 'Yearly', 'gatherpress' ),
								),
							),
						),
					),
					'rsvp_cleanup_interval'  => array(
						'labels'      => array(
							'name' => __( 'Cleanup Interval', 'gatherpress' ),
						),
						'description' => __(
							'The number of days, months, or years between each cleanup run.',
							'gatherpress'
						),
						'field'       => array(
							'type'    => 'number',
							'size'    => 'small',
							'options' => array(
								'min'     => 1,
								'default' => 1,
							),
						),
					),
				),
			),
		);
	}
}
