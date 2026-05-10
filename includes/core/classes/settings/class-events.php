<?php
/**
 * Events settings page for GatherPress.
 *
 * This class handles the "Events" settings page in GatherPress, providing options
 * for configuring event display, archive pages, and permalink slugs.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Event\Setup;
use GatherPress\Core\Topic;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Events.
 *
 * Handles the "Events" settings page for GatherPress.
 *
 * @since 1.0.0
 */
class Events extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the events settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug for the events settings page.
	 */
	protected function get_slug(): string {
		return 'events';
	}

	/**
	 * Get the name for the events settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The localized name for the events settings page.
	 */
	protected function get_name(): string {
		// Read the registered plural label so a site that filters
		// `gatherpress_event` to "Happenings" sees that everywhere the
		// Events settings sub-menu surfaces (#1612).
		return Utility::post_type_label( 'name', Event::POST_TYPE );
	}

	/**
	 * Get the priority for displaying the events settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return int The priority for displaying the events settings page.
	 */
	protected function get_priority(): int {
		return PHP_INT_MIN + 1;
	}

	/**
	 * Get sections and options for the Events settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of sections and options for the Events settings page.
	 */
	protected function get_sections(): array {
		return array(
			'date_time'     => array(
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
			'event_display' => array(
				'name'        => __( 'Event Display', 'gatherpress' ),
				'description' => __(
					'Configure how events are displayed on your site.',
					'gatherpress'
				),
				'options'     => array(
					'post_or_event_date' => array(
						'labels' => array(
							'name' => __( 'Publish Date', 'gatherpress' ),
						),
						'field'  => array(
							'label'   => __(
								'Display event date instead of publish date for events.',
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
			'archive_pages' => array(
				'name'        => __( 'Archive Pages', 'gatherpress' ),
				'description' => __(
					// phpcs:ignore Generic.Files.LineLength.TooLong -- Single translator-facing sentence; keep on one line for the .pot extractor.
					'Choose what the event archive URL shows by default and optionally point custom pages at the upcoming or past archives.',
					'gatherpress'
				),
				'options'     => array(
					'event_archive'   => array(
						'labels'      => array(
							'name' => __( 'Event Archive', 'gatherpress' ),
						),
						'description' => __(
							'What the events archive URL displays when no custom page is assigned.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'Default archive view:', 'gatherpress' ),
							'type'    => 'select',
							'options' => array(
								'default' => 'upcoming',
								'items'   => array(
									'upcoming' => __( 'Upcoming Events', 'gatherpress' ),
									'past'     => __( 'Past Events', 'gatherpress' ),
									'none'     => __( 'None (return 404)', 'gatherpress' ),
								),
							),
						),
					),
					'upcoming_events' => array(
						'labels' => array(
							'name' => __( 'Upcoming Events', 'gatherpress' ),
						),
						'field'  => array(
							'type'    => 'autocomplete',
							'options' => array(
								'type'    => 'page',
								'label'   => __( 'Select Upcoming Events Archive Page', 'gatherpress' ),
								'limit'   => 1,
								'default' => '[]',
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
								'type'    => 'page',
								'label'   => __( 'Select Past Events Archive Page', 'gatherpress' ),
								'limit'   => 1,
								'default' => '[]',
							),
						),
					),
				),
			),
			'urls'          => array(
				'name'        => __( 'Permalinks', 'gatherpress' ),
				'description' => __( 'Change permalink bases.', 'gatherpress' ),
				'options'     => array(
					'events_url' => array(
						'labels' => array(
							'name' => Utility::post_type_label( 'name', Event::POST_TYPE ),
						),
						'field'  => array(
							'type'    => 'text',
							'rewrite' => true,
							'options' => array(
								'label'   => __( 'Permalink base of Events.', 'gatherpress' ),
								'default' => Setup::get_localized_post_type_slug(),
							),
							'preview' => array(
								'template' => 'url-rewrite-preview',
								'suffix'   => _x(
									'sample-event',
									'URL permalink structure example for events',
									'gatherpress'
								),
							),
						),
					),
					'topics_url' => array(
						'labels' => array(
							'name' => __( 'Topics', 'gatherpress' ),
						),
						'field'  => array(
							'type'    => 'text',
							'rewrite' => true,
							'options' => array(
								'label'   => __( 'Permalink base of Topics.', 'gatherpress' ),
								'default' => Topic::get_localized_taxonomy_slug(),
							),
							'preview' => array(
								'template' => 'url-rewrite-preview',
								'suffix'   => _x(
									'sample-topic-term',
									'URL permalink structure example for topics',
									'gatherpress'
								),
							),
						),
					),
				),
			),
		);
	}
}
