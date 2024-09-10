<?php
/**
 * Class responsible for managing calendar-related endpoints in GatherPress.
 *
 * This file defines the `Calendar_Endpoints` class, which is responsible for
 * registering and managing custom endpoints related to calendar functionality,
 * such as export to third-party calendars and iCal download.
 *
 * It utilizes the `Posttype_Single_Endpoint` class to create endpoints
 * for single calendar events and provides logic for template rendering and external redirects.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Endpoints\Posttype_Single_Endpoint;
use GatherPress\Core\Endpoints\Posttype_Single_Feed_Endpoint;
use GatherPress\Core\Endpoints\Posttype_Feed_Endpoint;
use GatherPress\Core\Endpoints\Taxonomy_Feed_Endpoint;
use GatherPress\Core\Endpoints\Endpoint_Redirect;
use GatherPress\Core\Endpoints\Endpoint_Template;
use GatherPress\Core\Traits\Singleton;

/**
 * Manages Custom Calendar Endpoints for GatherPress.
 *
 * The `Calendar_Endpoints` class handles the registration and management of
 * custom endpoints for calendar-related functionality in GatherPress, such as:
 * - Adding Google Calendar and Yahoo Calendar links for events.
 * - Providing iCal and Outlook download templates for events.
 *
 * The class uses the `Posttype_Single_Endpoint` to create these endpoints for
 * single instances of the `gatherpress_event` post type. It also handles the
 * redirection logic for external calendars and provides the necessary templates
 * for downloading calendar data.
 *
 * @since 1.0.0
 */
class Calendar_Endpoints {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for registering custom calendar endpoints.
	 *
	 * This method hooks into the `registered_post_type_{post_type}` action to ensure that
	 * the custom endpoints for the `gatherpress_event` post type are registered after the
	 * post type is initialized.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action(
			sprintf(
				'registered_post_type_%s',
				'gatherpress_event'
			),
			array( $this, 'init_events' ),
		);

		add_action(
			sprintf(
				'registered_post_type_%s',
				'gatherpress_venue'
			),
			array( $this, 'init_venues' ),
		);

		add_action(
			'registered_taxonomy_for_object_type',
			array( $this, 'init_taxonomies' ),
			10,
			2
		);
		add_action(
			'registered_taxonomy',
			array( $this, 'init_taxonomies' ),
			10,
			2
		);

		// 
		add_action( 'wp_head', array( $this, 'alternate_links') );
	}

	/**
	 * Initializes the custom calendar endpoints for single events.
	 *
	 * This method sets up a `Posttype_Single_Endpoint` for the `gatherpress_event` post type
	 * (because this is this class' default post type),
	 * adding custom endpoints for external calendar services (Google Calendar, Yahoo Calendar)
	 * and download templates for iCal and Outlook.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_events(): void {
		// Important: Register the feed endpoint before the single endpoint,
		// to make sure rewrite rules get saved in the correct order.
		new Posttype_Feed_Endpoint(
			'gatherpress_ical_pt_feed',
			array(
				new Endpoint_Template( 'ical', array( $this, 'get_ical_feed_template' ) ),
			)
		);
		new Posttype_Single_Endpoint(
			'gatherpress_ext_calendar',
			array(
				new Endpoint_Template( 'ical', array( $this, 'get_ical_download_template' ) ),
				new Endpoint_Template( 'outlook', array( $this, 'get_ical_download_template' ) ),
				new Endpoint_Redirect( 'google-calendar', array( $this, 'get_redirect_to' ) ),
				new Endpoint_Redirect( 'yahoo-calendar', array( $this, 'get_redirect_to' ) ),
			)
		);
	}

	/**
	 * Initializes the custom calendar endpoints for single venues.
	 *
	 * This method sets up a `Posttype_Single_Endpoint` for the `gatherpress_venue` post type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_venues(): void {
		new Posttype_Single_Feed_Endpoint(
			'gatherpress_ext_venue_calendar',
			array(
				new Endpoint_Template( 'ical', array( $this, 'get_ical_feed_template' ) ),
			)
		);
	}

	/**
	 * 
	 *
	 * @param  string       $taxonomy
	 * @param  string|array $object_type will be string when called via 'registered_taxonomy_for_object_type',
	 *                                   could be an array when called from 'registered_taxonomy'
	 *
	 * @return void
	 */
	public function init_taxonomies( string $taxonomy, string|array $object_type ): void {
		if ( ! in_array( 'gatherpress_event', (array) $object_type, true ) || '_gatherpress_venue' === $taxonomy ) {
			return;
		}
		new Taxonomy_Feed_Endpoint(
			'gatherpress_ical_tax_feed',
			array(
				new Endpoint_Template( 'ical', array( $this, 'get_ical_feed_template' ) ),
			),
			$taxonomy
		);
	}

		);
	}

	/**
	 * Returns the external calendar URL for the current event.
	 *
	 * This method generates the appropriate URL for either Google Calendar or Yahoo Calendar,
	 * depending on the value of the `gatherpress_ext_calendar` query variable. It uses the
	 * `Event` class to retrieve the necessary data for the event.
	 *
	 * @since 1.0.0
	 *
	 * @return string The URL to redirect the user to the appropriate calendar service.
	 */
	public function get_redirect_to(): string {
		$event = new Event( get_queried_object_id() );
		// Determine which calendar service to redirect to based on the query var.
		switch ( get_query_var( 'gatherpress_ext_calendar' ) ) {
			case 'google-calendar':
				return $event->get_google_calendar_link();
			case 'yahoo-calendar':
				return $event->get_yahoo_calendar_link();
		}
	}

	/**
	 * Returns the template for the current calendar download.
	 *
	 * This method provides the template file to be used for iCal and Outlook downloads.
	 * The template file used for iCal downloads.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing the file name of the template to be loaded.
	 */
	public function get_ical_download_template(): array {
		return array(
			'file_name' => 'ical-download.php',
			// 'dir_path'  => ''
		);
	}

	public function get_ical_feed_template(): array {
		return array(
			'file_name' => 'ical-feed.php',
			// 'dir_path'  => ''
		);
	}
}
