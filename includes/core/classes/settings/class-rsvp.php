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
use GatherPress\Core\Event\Reminder;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Rsvp.
 *
 * Handles the "RSVP" settings page for GatherPress.
 *
 * @since 0.34.0
 *
 * @phpstan-import-type SettingsSection from Settings
 * @phpstan-import-type SettingsOption from Settings
 */
final class Rsvp extends Base {

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
	 * @return array<string, SettingsSection> An array of sections and options for the RSVP settings page.
	 */
	protected function get_sections(): array {
		return array(
			'rsvp_defaults'      => array(
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
							'label'   => __( 'RSVP mode:', 'gatherpress' ),
							'options' => array(
								'default' => 'enabled',
								'items'   => array(
									'enabled'            => Utility::post_type_label( 'all_items', Event::POST_TYPE ),
									'per_event_enabled'  => sprintf(
										/* translators: %s: Singular post type label, e.g. "Event". */
										__( 'Per %s (default enabled)', 'gatherpress' ),
										Utility::post_type_label( 'singular_name', Event::POST_TYPE )
									),
									'per_event_disabled' => sprintf(
										/* translators: %s: Singular post type label, e.g. "Event". */
										__( 'Per %s (default disabled)', 'gatherpress' ),
										Utility::post_type_label( 'singular_name', Event::POST_TYPE )
									),
									'disabled'           => __( 'Disabled', 'gatherpress' ),
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
					'capacity'              => array(
						'labels'      => array(
							'name' => __( 'Capacity', 'gatherpress' ),
						),
						'description' => sprintf(
							/* translators: %s: Singular post type label, e.g. "Event". */
							__( 'The total number of people allowed per %s. Set to 0 for no limit.', 'gatherpress' ), // phpcs:ignore Generic.Files.LineLength.TooLong
							Utility::post_type_label( 'singular_name', Event::POST_TYPE )
						),
						'field'       => array(
							'label'   => sprintf(
								/* translators: %s: Singular post type label, e.g. "Event". */
								__( 'The default capacity per %s.', 'gatherpress' ),
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
					'guest_limit'           => array(
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
			'rsvp_notifications' => array(
				'name'        => __( 'Notifications', 'gatherpress' ),
				'description' => __(
					'Emails GatherPress sends attendees on its own, without an organizer sending them.',
					'gatherpress'
				),
				'options'     => array_merge(
					array(
						'enable_waiting_list_emails' => array(
							'labels'      => array(
								'name' => __( 'Waiting List Emails', 'gatherpress' ),
							),
							'description' => __(
								'Sent when a spot opens up and an RSVP moves off the waiting list.',
								'gatherpress'
							),
							'field'       => array(
								'label'   => __(
									'Email attendees when they move off the waiting list.',
									'gatherpress'
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
						'enable_event_reminders'     => array(
							'labels'      => array(
								'name' => __( 'Reminder Emails', 'gatherpress' ),
							),
							'description' => __(
								// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
								'Users can turn reminders off in their profile. An RSVP without an account follows its email updates choice.',
								// phpcs:enable Generic.Files.LineLength.TooLong
								'gatherpress'
							),
							'field'       => array(
								'label'   => sprintf(
									/* translators: %s: Plural post type label, e.g. "Events". */
									__( 'Send attendees a reminder email before %s start.', 'gatherpress' ),
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
					),
					$this->get_reminder_timing_options()
				),
			),
			'rsvp_cleanup'       => array(
				'name'        => __( 'Cleanup', 'gatherpress' ),
				'description' => __(
					'Schedule automatic cleanup of unverified RSVPs.',
					'gatherpress'
				),
				'options'     => array(
					'enable_rsvp_cleanup'     => array(
						'labels'      => array(
							'name' => __( 'Toggle RSVP Cleanup', 'gatherpress' ),
						),
						'description' => __(
							'Enable or disable unverified RSVP cleanup.',
							'gatherpress'
						),
						'field'       => array(
							'type'    => 'select',
							'label'   => __( 'RSVP cleanup:', 'gatherpress' ),
							'options' => array(
								'default' => 'disabled',
								'items'   => array(
									'disabled' => __( 'Disabled', 'gatherpress' ),
									'enabled'  => __( 'Enabled', 'gatherpress' ),
								),
							),
						),
					),
					'rsvp_cleanup_frequency'  => array(
						'labels'      => array(
							'name' => __( 'Cleanup Frequency', 'gatherpress' ),
						),
						'description' => __(
							'How often the system should check for and remove unverified RSVPs.',
							'gatherpress'
						),
						'field'       => array(
							'type'    => 'select',
							'label'   => __( 'Cleanup frequency:', 'gatherpress' ),
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
							'enable_rsvp_cleanup' => array( 'not' => 'disabled' ),
						),
					),
					'rsvp_cleanup_multiplier' => array(
						'labels'      => array(
							'name' => __( 'Cleanup Interval', 'gatherpress' ),
						),
						'description' => __(
							'Multiplies the Cleanup Frequency. Daily with an interval of 3 runs every 3 days.',
							'gatherpress'
						),
						'field'       => array(
							'type'    => 'number',
							'size'    => 'small',
							'label'   => __( 'Cleanup interval:', 'gatherpress' ),
							'options' => array(
								'min'     => 1,
								'default' => 1,
							),
						),
						'show_if'     => array(
							'enable_rsvp_cleanup' => array( 'not' => 'disabled' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Get one checkbox for each reminder timing.
	 *
	 * Built from {@see Reminder::get_timings()}, so a timing added or removed
	 * through its filter gets or loses its checkbox here.
	 *
	 * @since TBD
	 *
	 * @return array<string, SettingsOption> The checkbox options, keyed by setting.
	 */
	private function get_reminder_timing_options(): array {
		$options     = array();
		$heading     = __( 'Reminder Timing', 'gatherpress' );
		$description = __( 'Check more than one to remind attendees more than once.', 'gatherpress' );

		foreach ( Reminder::get_instance()->get_timings() as $timing => $config ) {
			$options[ Reminder::get_timing_setting( (string) $timing ) ] = array(
				'labels'      => array(
					'name' => $heading,
				),
				'description' => $description,
				'field'       => array(
					'label'   => $config['label'],
					'type'    => 'checkbox',
					'options' => array(
						'default' => ! empty( $config['default'] ),
					),
				),
				'show_if'     => array(
					'rsvp_mode'              => array( 'not' => 'disabled' ),
					'enable_event_reminders' => '1',
				),
			);

			// Only the first row is headed and described, so the checkboxes read as one group.
			$heading     = '';
			$description = '';
		}

		return $options;
	}
}
