<?php
/**
 * Events settings page for GatherPress.
 *
 * This class handles the "Events" settings page in GatherPress, providing options
 * for configuring event display, archive pages, and permalink slugs.
 *
 * @package GatherPress\Core
 * @since 0.34.0
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
 * @since 0.34.0
 */
class Events extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the events settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string The slug for the events settings page.
	 */
	protected function get_slug(): string {
		return 'events_settings';
	}

	/**
	 * Get the name for the events settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string The localized name for the events settings page.
	 */
	protected function get_name(): string {
		// Read the registered plural label so the settings sub-menu
		// reflects whatever the site has filtered the post type's
		// labels to (#1612).
		return Utility::post_type_label( 'name', Event::POST_TYPE );
	}

	/**
	 * Get the priority for displaying the events settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return int The priority for displaying the events settings page.
	 */
	protected function get_priority(): int {
		return PHP_INT_MIN + 1;
	}

	/**
	 * Get sections and options for the Events settings page.
	 *
	 * @since 0.34.0
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
							'label'   => sprintf(
								/* translators: %s: Plural post type label, e.g. "Events". */
								__(
									'Format of date for scheduled %s.',
									'gatherpress'
								),
								Utility::post_type_label( 'name', Event::POST_TYPE )
							),
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
							'label'   => sprintf(
								/* translators: %s: Plural post type label, e.g. "Events". */
								__(
									'Format of time for scheduled %s.',
									'gatherpress'
								),
								Utility::post_type_label( 'name', Event::POST_TYPE )
							),
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
							'label'   => sprintf(
								/* translators: %s: Plural post type label, e.g. "Events". */
								__(
									'Display the timezone for scheduled %s.',
									'gatherpress'
								),
								Utility::post_type_label( 'name', Event::POST_TYPE )
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
				'name'        => sprintf(
					/* translators: %s: Singular post type label, e.g. "Event". */
					__( '%s Display', 'gatherpress' ),
					Utility::post_type_label( 'singular_name', Event::POST_TYPE )
				),
				'description' => sprintf(
					/* translators: %s: Plural post type label, e.g. "Events". */
					__(
						'Configure how %s are displayed on your site.',
						'gatherpress'
					),
					Utility::post_type_label( 'name', Event::POST_TYPE )
				),
				'options'     => array(
					'post_or_event_date' => array(
						'labels' => array(
							'name' => __( 'Publish Date', 'gatherpress' ),
						),
						'field'  => array(
							'label'   => sprintf(
								// phpcs:ignore Generic.Files.LineLength.TooLong
								/* translators: %1$s: Singular post type label, e.g. "Event", %2$s: Plural post type label, e.g. "Events". */
								__(
									'Display %1$s date instead of publish date for %2$s.',
									'gatherpress'
								),
								Utility::post_type_label( 'singular_name', Event::POST_TYPE ),
								Utility::post_type_label( 'name', Event::POST_TYPE )
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
				'description' => sprintf(
					/* translators: %s: Singular post type label, e.g. "Event". */
					__(
						// phpcs:ignore Generic.Files.LineLength.TooLong -- Single translator-facing sentence; keep on one line for the .pot extractor.
						'Choose what the %s archive URL shows by default and optionally point custom pages at the upcoming or past archives.',
						'gatherpress'
					),
					Utility::post_type_label( 'singular_name', Event::POST_TYPE )
				),
				'options'     => array(
					'event_archive'   => array(
						'labels'      => array(
							'name' => sprintf(
								/* translators: %s: Singular post type label, e.g. "Event". */
								__( '%s Archive', 'gatherpress' ),
								Utility::post_type_label( 'singular_name', Event::POST_TYPE )
							),
						),
						'description' => sprintf(
							/* translators: %s: Plural post type label, e.g. "Events". */
							__( 'What the %s archive URL displays when no custom page is assigned.', 'gatherpress' ),
							Utility::post_type_label( 'name', Event::POST_TYPE )
						),
						'field'       => array(
							'label'   => __( 'Default archive view:', 'gatherpress' ),
							'type'    => 'select',
							'options' => array(
								'default' => 'upcoming',
								'items'   => array(
									'upcoming' => sprintf(
										/* translators: %s: Plural post type label, e.g. "Events". */
										__( 'Upcoming %s', 'gatherpress' ),
										Utility::post_type_label( 'name', Event::POST_TYPE )
									),
									'past'     => sprintf(
										/* translators: %s: Plural post type label, e.g. "Events". */
										__( 'Past %s', 'gatherpress' ),
										Utility::post_type_label( 'name', Event::POST_TYPE )
									),
									'none'     => __( 'None (return 404)', 'gatherpress' ),
								),
							),
						),
					),
					'upcoming_events' => array(
						'labels' => array(
							'name' => sprintf(
								/* translators: %s: Plural post type label, e.g. "Events". */
								__( 'Upcoming %s', 'gatherpress' ),
								Utility::post_type_label( 'name', Event::POST_TYPE )
							),
						),
						'field'  => array(
							'type'    => 'autocomplete',
							'options' => array(
								'type'    => 'page',
								'label'   => sprintf(
									/* translators: %s: Plural post type label, e.g. "Events". */
									__( 'Select Upcoming %s Archive Page.', 'gatherpress' ),
									Utility::post_type_label( 'name', Event::POST_TYPE )
								),
								'limit'   => 1,
								'default' => '[]',
							),
						),
					),
					'past_events'     => array(
						'labels' => array(
							'name' => sprintf(
								/* translators: %s: Plural post type label, e.g. "Events". */
								__( 'Past %s', 'gatherpress' ),
								Utility::post_type_label( 'name', Event::POST_TYPE )
							),
						),
						'field'  => array(
							'type'    => 'autocomplete',
							'options' => array(
								'type'    => 'page',
								'label'   => sprintf(
									/* translators: %s: Plural post type label, e.g. "Events". */
									__( 'Select Past %s Archive Page.', 'gatherpress' ),
									Utility::post_type_label( 'name', Event::POST_TYPE )
								),
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
								'label'   => sprintf(
									/* translators: %s: Plural post type label, e.g. "Events". */
									__( 'Permalink base of %s.', 'gatherpress' ),
									Utility::post_type_label( 'name', Event::POST_TYPE )
								),
								'default' => Setup::get_localized_post_type_slug(),
							),
							'preview' => array(
								'template' => 'url-rewrite-preview',
								'suffix'   => sprintf(
									/* translators: %s: Singular post type label, e.g. "Event". */
									_x( 'sample-%s', 'URL permalink structure example for events', 'gatherpress' ),
									sanitize_title( Utility::post_type_label( 'singular_name', Event::POST_TYPE ) )
								),
							),
						),
					),
					'topics_url' => array(
						'labels' => array(
							'name' => Utility::taxonomy_label( 'name', Topic::TAXONOMY ),
						),
						'field'  => array(
							'type'    => 'text',
							'rewrite' => true,
							'options' => array(
								'label'   => sprintf(
									/* translators: %s: Plural taxonomy label, e.g. "Topics". */
									__( 'Permalink base of %s.', 'gatherpress' ),
									Utility::taxonomy_label( 'name', Topic::TAXONOMY )
								),
								'default' => Topic::get_localized_taxonomy_slug(),
							),
							'preview' => array(
								'template' => 'url-rewrite-preview',
								'suffix'   => sprintf(
									/* translators: %s: Singular taxonomy label, e.g. "Topic". */
									_x( 'sample-%s-term', 'URL permalink structure example for topics', 'gatherpress' ),
									sanitize_title( Utility::taxonomy_label( 'singular_name', Topic::TAXONOMY ) )
								),
							),
						),
					),
				),
			),
		);
	}
}
