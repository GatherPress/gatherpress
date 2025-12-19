<?php
/**
 * Settings General class file for GatherPress.
 *
 * This file contains the General class definition, which handles the "General" settings
 * page in GatherPress, providing options for configuring various general settings. It
 * extends the Base class to inherit common settings page functionality.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Event_Setup;
use GatherPress\Core\Venue;
use GatherPress\Core\Topic;
/**
 * Class General.
 *
 * This class handles the "General" settings page in GatherPress, providing options
 * for configuring various general settings. It extends the Base class to inherit
 * common settings page functionality.
 *
 * @since 1.0.0
 */
class General extends Base {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the general section.
	 *
	 * This method returns the slug used to identify the general section.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug for the general section.
	 */
	protected function get_slug(): string {
		return 'general';
	}

	/**
	 * Get the name for the general section.
	 *
	 * This method returns the localized name for the general section.
	 *
	 * @since 1.0.0
	 *
	 * @return string The localized name for the general section.
	 */
	protected function get_name(): string {
		return __( 'General', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying general.
	 *
	 * This method returns the priority at which general should be displayed.
	 *
	 * @since 1.0.0
	 *
	 * @return int The priority for displaying general. Higher values mean later execution.
	 */
	protected function get_priority(): int {
		return PHP_INT_MIN;
	}

	/**
	 * Get an array of sections and options for the General settings page.
	 *
	 * This method defines the sections and their respective options for the "General" settings page
	 * in GatherPress. It provides structured data that represents the configuration choices available
	 * to users on this page.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array representing the sections and options for the "General" settings page.
	 */
	protected function get_sections(): array {
		return array(
			'general'    => array(
				'name'        => __( 'General Settings', 'gatherpress' ),
				'description' => __(
					// phpcs:ignore Generic.Files.LineLength.TooLong
					'GatherPress allows you to set event dates to reflect either the post date or event date. Default: event date.',
					'gatherpress'
				),
				'options'     => array(
					'post_or_event_date'    => array(
						'labels' => array(
							'name' => __( 'Publish Date', 'gatherpress' ),
						),
						'field'  => array(
							'label'   => __( 'Show publish date as event date for events.', 'gatherpress' ),
							'type'    => 'checkbox',
							'options' => array(
								'default' => '1',
							),
						),
					),
					'map_platform'          => array(
						'labels'      => array(
							'name' => __( 'Mapping Platform', 'gatherpress' ),
						),
						'description' => __( 'Select the platform you would like to render maps with.', 'gatherpress' ),
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
					'max_attendance_limit'  => array(
						'labels'      => array(
							'name' => __( 'Maximum Attendance Limit', 'gatherpress' ),
						),
						'description' => __(
							// phpcs:ignore Generic.Files.LineLength.TooLong
							'Set this as your default, but you can still override it for each event as you like. This is the total number of people allowed at an event. If you set it to 0, there will not be any limit.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'The default maximum limit of attendees to an event.', 'gatherpress' ),
							'type'    => 'number',
							'size'    => 'small',
							'options' => array(
								'default' => '50',
							),
						),
					),
					'max_guest_limit'       => array(
						'labels'      => array(
							'name' => __( 'Maximum Number of Guests', 'gatherpress' ),
						),
						'description' => __(
							'Set this as your default, but you can still override it for each event as you like.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __(
								'The default maximum number of people each attendee can bring to an event.',
								'gatherpress'
							),
							'type'    => 'number',
							'size'    => 'small',
							'options' => array(
								'default' => '0',
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
							'Set this as your default, but you can still override it for each event as you like.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'Enable Anonymous RSVP for New Events.', 'gatherpress' ),
							'type'    => 'checkbox',
							'options' => array(
								'default' => 0,
							),
						),
					),
				),
			),
			'formatting' => array(
				'name'        => __( 'Date & Time Formatting', 'gatherpress' ),
				'description' => __(
					// phpcs:ignore Generic.Files.LineLength.TooLong
					'For more information read the <a href="https://wordpress.org/documentation/article/customize-date-and-time-format/"> Documentation on date and time formatting</a>.',
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
						),
					),
					'show_timezone' => array(
						'labels' => array(
							'name' => __( 'Show Timezone', 'gatherpress' ),
						),
						'field'  => array(
							'label'   => __( 'Display the timezone for scheduled events.', 'gatherpress' ),
							'type'    => 'checkbox',
							'options' => array(
								'default' => '1',
							),
						),
					),
				),
			),
			'pages'      => array(
				'name'        => __( 'Event Archive Pages', 'gatherpress' ),
				'description' => __(
					'GatherPress allows you to set event archives to pages you have created.',
					'gatherpress'
				),
				'options'     => array(
					'upcoming_events' => array(
						'labels' => array(
							'name' => __( 'Upcoming Events', 'gatherpress' ),
						),
						'field'  => array(
							'type'    => 'autocomplete',
							'options' => array(
								'type'  => 'page',
								'label' => __( 'Select Upcoming Events Archive Page', 'gatherpress' ),
								'limit' => 1,
							),
						),
					),
					'past_events'     => array(
						'labels' => array(
							'name' => __( 'Past Events', 'gatherpress' ),
						),
						'field'  => array(
							'type'    => 'autocomplete',
							'options' => array(
								'type'  => 'page',
								'label' => __( 'Select Past Events Archive Page', 'gatherpress' ),
								'limit' => 1,
							),
						),
					),
				),
			),
			'urls'       => array(
				'name'        => __( 'URLs generated by the plugin', 'gatherpress' ),
				'description' => __( 'Change permalink bases.', 'gatherpress' ),
				'options'     => array(
					'events' => array(
						'labels' => array(
							'name' => __( 'Events', 'gatherpress' ),
						),
						'field'  => array(
							'type'    => 'text',
							'options' => array(
								'label'   => __( 'Permalink base of Events.', 'gatherpress' ),
								'default' => Event_Setup::get_localized_post_type_slug(),
							),
						),
					),
					'venues' => array(
						'labels' => array(
							'name' => __( 'Venues', 'gatherpress' ),
						),
						'field'  => array(
							'type'    => 'text',
							'options' => array(
								'label'   => __( 'Permalink base of Venues.', 'gatherpress' ),
								'default' => Venue::get_localized_post_type_slug(),
							),
						),
					),
					'topics' => array(
						'labels' => array(
							'name' => __( 'Topics', 'gatherpress' ),
						),
						'field'  => array(
							'type'    => 'text',
							'options' => array(
								'label'   => __( 'Permalink base of Topics.', 'gatherpress' ),
								'default' => Topic::get_localized_taxonomy_slug(),
							),
						),
					),

				),
			),
		);
	}
}
