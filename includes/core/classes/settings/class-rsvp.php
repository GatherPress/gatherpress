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

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Rsvp.
 *
 * Handles the "RSVP" settings page for GatherPress.
 *
 * @since 0.34.0
 */
class Rsvp extends Base {

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
				'description' => sprintf(
					// phpcs:ignore Generic.Files.LineLength.TooLong
					/* translators: %1$s: Singular post type label, e.g. "Event", %2$s: Plural post type label, e.g. "Events". */
					__(
						'Default RSVP settings for new %2$s. These can be overridden per %1$s.',
						'gatherpress'
					),
					Utility::post_type_label( 'singular_name', Event::POST_TYPE ),
					Utility::post_type_label( 'name', Event::POST_TYPE )
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
									'all_on'        => Utility::post_type_label( 'all_items', Event::POST_TYPE ),
									'per_event_on'  => sprintf(
										/* translators: %s: Singular post type label, e.g. "Event". */
										__( 'Per %s (default on)', 'gatherpress' ),
										Utility::post_type_label( 'singular_name', Event::POST_TYPE )
									),
									'per_event_off' => sprintf(
										/* translators: %s: Singular post type label, e.g. "Event". */
										__( 'Per %s (default off)', 'gatherpress' ),
										Utility::post_type_label( 'singular_name', Event::POST_TYPE )
									),
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
							'label'   => sprintf(
								/* translators: %s: Plural post type label, e.g. "Events". */
								__( 'Enable Open RSVP for %s.', 'gatherpress' ),
								Utility::post_type_label( 'name', Event::POST_TYPE )
							),
							'type'    => 'checkbox',
							'options' => array(
								'default' => true,
							),
						),
						'show_if'     => array(
							'rsvp_mode' => array( 'not' => 'disabled' ),
						),
					),
					'max_attendance_limit'  => array(
						'labels'      => array(
							'name' => __( 'Maximum Attendance Limit', 'gatherpress' ),
						),
						'description' => sprintf(
							/* translators: %s: Singular post type label, e.g. "Event". */
							__( 'The total number of people allowed per %s. Set to 0 for no limit.', 'gatherpress' ), // phpcs:ignore Generic.Files.LineLength.TooLong
							Utility::post_type_label( 'singular_name', Event::POST_TYPE )
						),
						'field'       => array(
							'label'   => sprintf(
								/* translators: %s: Singular post type label, e.g. "Event". */
								__( 'The default maximum limit of attendees per %s.', 'gatherpress' ),
								Utility::post_type_label( 'singular_name', Event::POST_TYPE )
							),
							'type'    => 'number',
							'size'    => 'small',
							'options' => array(
								'default' => 50,
							),
						),
						'show_if'     => array(
							'rsvp_mode' => array( 'not' => 'disabled' ),
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
						'show_if'     => array(
							'rsvp_mode' => array( 'not' => 'disabled' ),
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
							'label'   => sprintf(
								/* translators: %s: Plural post type label, e.g. "Events". */
								__( 'Enable Anonymous RSVP for new %s.', 'gatherpress' ),
								Utility::post_type_label( 'name', Event::POST_TYPE )
							),
							'type'    => 'checkbox',
							'options' => array(
								'default' => false,
							),
						),
						'show_if'     => array(
							'rsvp_mode' => array( 'not' => 'disabled' ),
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
						'show_if'     => array(
							'rsvp_cleanup_switch' => array( 'not' => 'off' ),
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
						'show_if'     => array(
							'rsvp_cleanup_switch' => array( 'not' => 'off' ),
						),
					),
				),
			),
		);
	}
}
