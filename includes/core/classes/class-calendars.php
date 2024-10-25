<?php
/**
 * Class responsible for managing calendar-related endpoints in GatherPress.
 *
 * This file defines the `Calendars` class, which is responsible for
 * registering and managing custom endpoints related to calendar functionality,
 * such as export to third-party calendars and iCal download.
 *
 * It utilizes the `Endpoint` sub-classes to create endpoints
 * for single events, post type and taxonomy archives as well.
 * These classes provide the logic for template rendering and external redirects.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Endpoints\Posttype_Single_Endpoint;
use GatherPress\Core\Endpoints\Posttype_Single_Feed_Endpoint;
use GatherPress\Core\Endpoints\Posttype_Feed_Endpoint;
use GatherPress\Core\Endpoints\Taxonomy_Feed_Endpoint;
use GatherPress\Core\Endpoints\Endpoint_Redirect;
use GatherPress\Core\Endpoints\Endpoint_Template;
use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;
use WP_Post;
use WP_Term;

/**
 * Manages Custom Calendar Endpoints for GatherPress.
 *
 * The `Calendars` class handles the registration and management of
 * custom endpoints for calendar-related functionality in GatherPress, such as:
 * - Adding Google Calendar and Yahoo Calendar links for events.
 * - Providing iCal and Outlook download templates for events.
 *
 * @since 1.0.0
 */
class Calendars {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	const QUERY_VAR = 'gatherpress_calendars';
	const ICAL_SLUG = 'ical'; // Make sure nobody tries to change or translate this string ;) !

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
		// @todo Maybe hook this two actions dynamically based on a registered post type?!
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
		add_action( 'wp_head', array( $this, 'alternate_links' ) );
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
			array(
				new Endpoint_Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR
		);
		new Posttype_Single_Endpoint(
			array(
				new Endpoint_Template( self::ICAL_SLUG, array( $this, 'get_ical_file_template' ) ),
				new Endpoint_Template( 'outlook', array( $this, 'get_ical_file_template' ) ),
				new Endpoint_Redirect( 'google-calendar', array( $this, 'get_google_calendar_link' ) ),
				new Endpoint_Redirect( 'yahoo-calendar', array( $this, 'get_yahoo_calendar_link' ) ),
			),
			self::QUERY_VAR
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
			array(
				new Endpoint_Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR
		);
	}

	/**
	 * Initializes the custom calendar endpoints for taxonomies that belong to events.
	 *
	 * This method sets up one `Taxonomy_Feed_Endpoint` for each taxonomy,
	 * that is registered for the `gatherpress_event` post type
	 * and publicly available.
	 *
	 * @param  string       $taxonomy    Name of the taxonomy that got registered last.
	 * @param  string|array $object_type This will be a string when called via 'registered_taxonomy_for_object_type',
	 *                                   and could(!) be an array when called from 'registered_taxonomy'.
	 *
	 * @return void
	 */
	public function init_taxonomies( string $taxonomy, $object_type ): void {

		// Stop, if the currently registered taxonomy ...
		if ( // ... is not registered for the events post type.
			! in_array( 'gatherpress_event', (array) $object_type, true ) ||
			// ... is GatherPress' shadow-taxonomy for venues.
			'_gatherpress_venue' === $taxonomy ||
			// ... should not be public.
			! is_taxonomy_viewable( $taxonomy )
		) {
			return;
		}

		new Taxonomy_Feed_Endpoint(
			array(
				new Endpoint_Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR,
			$taxonomy
		);
	}

	/**
	 * Prints <link rel="alternate> tags to the rendered output, one per related calendar endpoint.
	 *
	 * Depending on the current request this can be one or multiple link tags,
	 * one for each relevant calendar link.
	 *
	 * At least the link-tag for the main `/event/feed/ical`-endpoint is generated on each request.
	 *
	 * DRYed-out adoption of WordPress' core feed_links_extra() function.
	 * Structure and flow of this method is directly replicated from
	 * the `feed_links()` and `feed_links_extra()` functions in WordPress core.
	 *
	 * @since  1.0.0
	 * @see    https://developer.wordpress.org/reference/functions/feed_links_extra/
	 *
	 * @return void
	 */
	public function alternate_links(): void {

		if ( ! current_theme_supports( 'automatic-feed-links' ) ) {
			return;
		}

		// @todo "add_filter('feed_content_type')" here, if the subscribe-able feed need something different than text/cal.

		$args = array(
			'blogtitle'     => get_bloginfo( 'name' ),
			/* translators: Separator between site name and feed type in feed links. */
			'separator'     => _x( '&raquo;', 'feed link separator', 'gatherpress' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Post title. */
			'singletitle'   => __( '📅 %1$s %2$s %3$s iCal Download', 'gatherpress' ),
			/* translators: 1: Site title, 2: Separator (raquo). */
			'feedtitle'     => __( '📅 %1$s %2$s iCal Feed', 'gatherpress' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Post type name. */
			'posttypetitle' => __( '📅 %1$s %2$s %3$s iCal Feed', 'gatherpress' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Term name, 4: Taxonomy singular name. */
			'taxtitle'      => __( '📅 %1$s %2$s %3$s %4$s iCal Feed', 'gatherpress' ),
		);

		$alternate_links = array();

		// @todo "/feed/ical" could be enabled as alias of "/event/feed/ical",
		// and called with "get_feed_link( self::ICAL_SLUG )".
		$alternate_links[] = array(
			'url'  => get_post_type_archive_feed_link( 'gatherpress_event', self::ICAL_SLUG ),
			'attr' => sprintf(
				$args['feedtitle'],
				$args['blogtitle'],
				$args['separator']
			),
		);

		if ( is_singular( 'gatherpress_event' ) ) {
			$alternate_links[] = array(
				'url'  => self::get_url( self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			);

			// Get all terms, associated with the current event-post.
			$terms = get_terms(
				array(
					'taxonomy'   => get_object_taxonomies( get_queried_object() ),
					'object_ids' => get_queried_object_id(),
				)
			);
			// Loop over terms and generate the ical feed links for the <head>.
			array_walk(
				$terms,
				function ( WP_Term $term ) use ( $args, &$alternate_links ) {
					$tax = get_taxonomy( $term->taxonomy );
					switch ( $term->taxonomy ) {
						case '_gatherpress_venue':
							$gatherpress_venue = Venue::get_instance()->get_venue_post_from_term_slug( $term->slug );

							// An Online-Event will have no Venue; prevent error on non-existent object.
							// Feels weird to use a *_comments_* function here, but it delivers clean results
							// in the form of "domain.tld/event/my-sample-event/feed/ical/".
							$href = ( $gatherpress_venue ) ? get_post_comments_feed_link( $gatherpress_venue->ID, self::ICAL_SLUG ) : null;
							break;

						default:
							$href = get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG );
							break;
					}
					// Can be empty for Online-Events.
					if ( ! empty( $href ) ) {
						$alternate_links[] = array(
							'url'  => $href,
							'attr' => sprintf(
								$args['taxtitle'],
								$args['blogtitle'],
								$args['separator'],
								$term->name,
								$tax->labels->singular_name
							),
						);
					}
				}
			);
		} elseif ( is_singular( 'gatherpress_venue' ) ) {

			// Feels weird to use a *_comments_* function here, but it delivers clean results
			// in the form of "domain.tld/venue/my-sample-venue/feed/ical/".
			$alternate_links[] = array(
				'url'  => get_post_comments_feed_link( get_queried_object_id(), self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			);
		} elseif ( is_tax() ) {
			$term = get_queried_object();

			if ( $term && is_object_in_taxonomy( 'gatherpress_event', $term->taxonomy ) ) {
				$tax = get_taxonomy( $term->taxonomy );

				$alternate_links[] = array(
					'url'  => get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG ),
					'attr' => sprintf(
						$args['taxtitle'],
						$args['blogtitle'],
						$args['separator'],
						$term->name,
						$tax->labels->singular_name
					),
				);
			}
		}

		// Render tags into <head/>.
		array_walk(
			$alternate_links,
			function ( $link ) {
				printf(
					'<link rel="alternate" type="%s" title="%s" href="%s" />' . "\n",
					esc_attr( 'text/calendar' ),
					esc_attr( $link['attr'] ),
					esc_url( $link['url'] )
				);
			}
		);
	}

	/**
	 * Returns the template for the current calendar download.
	 *
	 * This method provides the template file to be used for iCal and Outlook downloads.
	 *
	 * By adding a file with the same name to your themes root folder
	 * or your themes `/templates` folder, this template will be used
	 * with priority over the default template provided by GatherPress.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing:
	 *               - 'file_name': the file name of the template to be loaded from the theme. Will load defaults from the plugin if theme files do not exist.
	 *               - 'dir_path':  (Optional) Absolute path to some template directory outside of the theme folder.
	 */
	public function get_ical_file_template(): array {
		return array(
			'file_name' => Utility::prefix_key( 'ical-download.php' ),
		);
	}

	/**
	 * Returns the template for the subscribeable calendar feed.
	 *
	 * This method provides the template file to be used for ical-feeds.
	 *
	 * By adding a file with the same name to your themes root folder
	 * or your themes `/templates` folder, this template will be used
	 * with priority over the default template provided by GatherPress.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing:
	 *               - 'file_name': the file name of the template to be loaded from the theme.
	 *                              Will load defaults from the plugin if theme files do not exist.
	 *               - 'dir_path':  (Optional) Absolute path to some template directory outside of the theme folder.
	 */
	public function get_ical_feed_template(): array {
		return array(
			'file_name' => Utility::prefix_key( 'ical-feed.php' ),
		);
	}

	/**
	 * Get sanitized endpoint url for a given slug, post and query parameter.
	 *
	 * Inspired by get_post_embed_url()
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_post_embed_url/
	 *
	 * @param  string           $endpoint_slug The visible suffix to the posts permalink.
	 * @param  WP_Post|int|null $post          The post to get the endpoint for.
	 * @param  string           $query_var     The internal query variable used by WordPress to route the request.
	 *
	 * @return string|false                    URL of the posts endpoint or false if something went wrong.
	 */
	public static function get_url( string $endpoint_slug, $post = null, string $query_var = self::QUERY_VAR ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return false;
		}

		$is_feed_endpoint = strpos( 'feed/', $endpoint_slug );
		if ( false !== $is_feed_endpoint ) {
			// Feels weird to use a *_comments_* function here, but it delivers clean results
			// in the form of "domain.tld/event/my-sample-event/feed/ical/".
			return (string) get_post_comments_feed_link(
				$post->ID,
				substr( $endpoint_slug, $is_feed_endpoint )
			);
		}

		$post_url      = get_permalink( $post );
		$endpoint_url  = trailingslashit( $post_url ) . user_trailingslashit( $endpoint_slug );
		$path_conflict = get_page_by_path(
			str_replace( home_url(), '', $endpoint_url ),
			OBJECT,
			get_post_types( array( 'public' => true ) )
		);

		if ( ! get_option( 'permalink_structure' ) || $path_conflict ) {
			$endpoint_url = add_query_arg( array( $query_var => $endpoint_slug ), $post_url );
		}

		/**
		 * Filters the endpoint URL of a specific post.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $endpoint_url The post embed URL.
		 * @param WP_Post $post      The corresponding post object.
		 */
		$endpoint_url = sanitize_url(
			apply_filters(
				'gatherpress_endpoint_url',
				$endpoint_url,
				$post
			)
		);

		return (string) sanitize_url( $endpoint_url );
	}

	/**
	 * Get the Google Calendar add event link for the event.
	 *
	 * This method generates and returns a Google Calendar link that allows users to add the event to their
	 * Google Calendar. The link includes event details such as the event name, date, time, location, and a
	 * link to the event's details page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Google Calendar add event link for the event.
	 *
	 * @throws Exception If there is an issue while generating the Google Calendar link.
	 */
	public function get_google_calendar_link(): string {
		$event       = new Event( get_queried_object_id() );
		$date_start  = $event->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start  = $event->get_formatted_datetime( 'His', 'start', false );
		$date_end    = $event->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end    = $event->get_formatted_datetime( 'His', 'end', false );
		$datetime    = sprintf( '%sT%sZ/%sT%sZ', $date_start, $time_start, $date_end, $time_end );
		$venue       = $event->get_venue_information();
		$location    = $venue['name'];
		$description = $event->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$params = array(
			'action'   => 'TEMPLATE',
			'text'     => sanitize_text_field( $event->event->post_title ),
			'dates'    => sanitize_text_field( $datetime ),
			'details'  => sanitize_text_field( $description ),
			'location' => sanitize_text_field( $location ),
			'sprop'    => 'name:',
		);

		return add_query_arg(
			rawurlencode_deep( $params ),
			'https://www.google.com/calendar/event'
		);
	}

	/**
	 * Get the "Add to Yahoo! Calendar" link for the event.
	 *
	 * This method generates and returns a URL that allows users to add the event to their Yahoo! Calendar.
	 * The URL includes event details such as the event title, start time, duration, description, and location.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Yahoo! Calendar link for adding the event.
	 *
	 * @throws Exception If an error occurs while generating the Yahoo! Calendar link.
	 */
	public function get_yahoo_calendar_link(): string {
		$event          = new Event( get_queried_object_id() );
		$date_start     = $event->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $event->get_formatted_datetime( 'His', 'start', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );

		// Figure out duration of event in hours and minutes: hhmm format.
		$diff_start  = $event->get_formatted_datetime( $event::DATETIME_FORMAT, 'start', false );
		$diff_end    = $event->get_formatted_datetime( $event::DATETIME_FORMAT, 'end', false );
		$duration    = ( ( strtotime( $diff_end ) - strtotime( $diff_start ) ) / 60 / 60 );
		$full        = intval( $duration );
		$fraction    = ( $duration - $full );
		$hours       = str_pad( strval( $duration ), 2, '0', STR_PAD_LEFT );
		$minutes     = str_pad( strval( $fraction * 60 ), 2, '0', STR_PAD_LEFT );
		$venue       = $event->get_venue_information();
		$location    = $venue['name'];
		$description = $event->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$params = array(
			'v'      => '60',
			'view'   => 'd',
			'type'   => '20',
			'title'  => sanitize_text_field( $event->event->post_title ),
			'st'     => sanitize_text_field( $datetime_start ),
			'dur'    => sanitize_text_field( (string) $hours . (string) $minutes ),
			'desc'   => sanitize_text_field( $description ),
			'in_loc' => sanitize_text_field( $location ),
		);

		return add_query_arg(
			rawurlencode_deep( $params ),
			'https://calendar.yahoo.com/'
		);
	}

	/**
	 * Wraps the provided calendar data in a VCALENDAR structure for iCal.
	 *
	 * This method generates the necessary headers for an iCal file (such as the `BEGIN:VCALENDAR`
	 * and `END:VCALENDAR` lines), wraps the provided calendar data, and returns the entire iCal
	 * content as a formatted string. It also includes the blog's title and the current locale
	 * in the `PRODID` header, ensuring that the calendar is properly identified.
	 *
	 * @since 1.0.0
	 *
	 * @param string $calendar_data The events to be included in the iCal file.
	 * @return string               The complete iCal data wrapped in the VCALENDAR format.
	 */
	public static function get_ical_wrap( string $calendar_data ): string {

		$args = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			sprintf(
				'PRODID:-//%s//GatherPress//%s',
				get_bloginfo( 'title' ),
				// Prpeare 2-DIGIT lang code.
				strtoupper( substr( get_locale(), 0, 2 ) )
			),
			$calendar_data,
			'END:VCALENDAR',
		);

		return implode( "\r\n", $args );
	}

	/**
	 * Get the ICS download link for the event.
	 *
	 * This method generates and returns a URL that allows users to download the event in ICS (iCalendar) format.
	 * The URL includes event details such as the event title, start time, end time, description, location, and more.
	 *
	 * @since 1.0.0
	 *
	 * @return string The ICS download link for the event.
	 *
	 * @throws Exception If an error occurs while generating the ICS download link.
	 */
	public static function get_ical_event(): string {

		$event          = new Event( get_queried_object_id() );
		$date_start     = $event->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $event->get_formatted_datetime( 'His', 'start', false );
		$date_end       = $event->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end       = $event->get_formatted_datetime( 'His', 'end', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );
		$datetime_end   = sprintf( '%sT%sZ', $date_end, $time_end );
		$modified_date  = strtotime( $event->event->post_modified );
		$datetime_stamp = sprintf( '%sT%sZ', gmdate( 'Ymd', $modified_date ), gmdate( 'His', $modified_date ) );
		$venue          = $event->get_venue_information();
		$location       = $venue['name'];
		$description    = $event->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$args = array(
			'BEGIN:VEVENT',
			sprintf( 'URL:%s', esc_url_raw( get_permalink( $event->event->ID ) ) ),
			sprintf( 'DTSTART:%s', sanitize_text_field( $datetime_start ) ),
			sprintf( 'DTEND:%s', sanitize_text_field( $datetime_end ) ),
			sprintf( 'DTSTAMP:%s', sanitize_text_field( $datetime_stamp ) ),
			sprintf( 'SUMMARY:%s', self::eventorganiser_fold_ical_text( sanitize_text_field( $event->event->post_title ) ) ),
			sprintf( 'DESCRIPTION:%s', self::eventorganiser_fold_ical_text( sanitize_text_field( $description ) ) ),
			sprintf( 'LOCATION:%s', self::eventorganiser_fold_ical_text( sanitize_text_field( $location ) ) ),
			'UID:gatherpress_' . intval( $event->event->ID ),
			'END:VEVENT',
		);

		return implode( "\r\n", $args );
	}

	/**
	 * Generates a list of events in iCal format based on the current query.
	 *
	 * This method generates iCal data for a list of events,
	 * taking into account the currently queried archive or taxonomy context.
	 *
	 * It supports fetching events from:
	 * - The `gatherpress_event` post type archive (upcoming and past events).
	 * - Single `gatherpress_venue` requests (events specific to a venue).
	 * - and `gatherpress_topic` taxonomy (events tagged with specific topics).
	 *
	 * It builds an iCal event list, wraps each event in the appropriate iCal format, and returns
	 * the entire list of events as a single string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The iCal formatted list of events, ready for export or download.
	 */
	public static function get_ical_list(): string {

		$event_list_type = ''; // Keep empty, to get all events from upcoming & past.
		$number          = ( is_feed( self::ICAL_SLUG ) ) ? -1 : get_option( 'posts_per_page' );
		$topics          = array();
		$venues          = array();
		$output          = array();

		if ( is_singular( 'gatherpress_venue' ) ) {
			$slug   = '_' . get_queried_object()->post_name;
			$venues = array( $slug );
		} elseif ( is_tax() ) {
			$term = get_queried_object();

			// @todo How to be prepared for foreign taxonomies that might be registered by 3rd-parties?
			if ( $term && is_object_in_taxonomy( 'gatherpress_event', $term->taxonomy ) ) {
				// Add the tax to the query here.

				if ( is_tax( 'gatherpress_topic' ) ) {
					$topics = array( $term->slug );
				}
			}
		}

		$query = Event_Query::get_instance()->get_events_list( $event_list_type, $number, $topics, $venues );
		while ( $query->have_posts() ) {
			$query->the_post();
			$output[] = self::get_ical_event();
		}

		// Restore original Post Data.
		wp_reset_postdata();

		return implode( "\r\n", $output );
	}

	/**
	 * Generates the complete iCal file content for an event.
	 *
	 * This method calls the `get_ical_event()` method to retrieve the event data in iCal format
	 * and then wraps the event data using `get_ical_wrap()` to include the necessary VCALENDAR
	 * headers and footers. It returns the fully formatted iCal file content as a string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The complete iCal file content for the event, ready for download or export.
	 */
	public static function get_ical_file(): string {
		return self::get_ical_wrap( self::get_ical_event() );
	}

	/**
	 * Generates the complete iCal file content for a list of events.
	 *
	 * This method calls the `get_ical_list()` method to retrieve the data for all (queried) events in iCal format
	 * and then wraps the events using `get_ical_wrap()` to include the necessary VCALENDAR
	 * headers and footers.
	 *
	 * @since 1.0.0
	 *
	 * @return string The complete iCal feed containing the list of events.
	 */
	public static function get_ical_feed(): string {
		return self::get_ical_wrap( self::get_ical_list() );
	}

	/**
	 * Generate the .ics filename based on the queried object.
	 *
	 * @return string Name of the calendar ics file.
	 */
	public static function generate_ics_filename() {
		$queried_object = get_queried_object();
		$filename       = 'calendar';

		if ( is_singular( 'gatherpress_event' ) ) {
			$event     = new Event( $queried_object->ID );
			$date      = $event->get_datetime_start( 'Y-m-d' );
			$post_name = $event->event->post_name;
			$filename  = $date . '_' . $post_name;
		} elseif ( is_singular( 'gatherpress_venue' ) ) {
			$filename = $queried_object->post_name;
		} elseif ( is_tax() ) {

			// @todo How to be prepared for foreign taxonomies that might be registered by 3rd-parties?
			if ( is_object_in_taxonomy( 'gatherpress_event', $queried_object->taxonomy ) ) {
				$filename = $queried_object->slug;
			}
		}

		return $filename . '.ics';
	}

	/**
	 * Send the necessary headers for the iCalendar file download.
	 *
	 * @param string $filename Generated name of the file.
	 *
	 * @return void
	 */
	public static function send_ics_headers( string $filename ) {

		$charset = strtolower( get_option( 'blog_charset' ) );

		header( 'Content-Description: File Transfer' );

		// Ensure proper content type for the calendar file.
		header( 'Content-Type: text/calendar; charset=' . $charset );

		// Force download in most browsers.
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// Avoid browser caching issues.
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Prevent content sniffing which might lead to MIME type mismatch.
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * Output the event(s) as an iCalendar (.ics) file.
	 *
	 * @return void
	 */
	public static function send_ics_file() {
		// Start output buffering to capture all output.
		ob_start();

		// Prepare the filename.
		$filename = self::generate_ics_filename();

		// Send headers for downloading the .ics file.
		self::send_ics_headers( $filename );

		// Output the generated iCalendar content.
		$get_ical_method = ( is_feed() ) ? 'get_ical_feed' : 'get_ical_file';
		echo wp_kses_post( self::$get_ical_method() );

		// Get the generated output and calculate file size.
		$ics_content = ob_get_contents();
		$filesize    = strlen( $ics_content );

		// Send the file size in the header.
		header( 'Content-Length: ' . $filesize );

		// End output buffering and clean up.
		ob_end_clean();

		// Output the iCalendar content.
		echo wp_kses_post( $ics_content );

		exit(); // Terminate the script after the file has been output.
	}

	/**
	 * @author Stephen Harris (@stephenharris)
	 * @source https://github.com/stephenharris/Event-Organiser/blob/develop/includes/event-organiser-utility-functions.php#L1663
	 *
	 * Fold text as per [iCal specifications](http://www.ietf.org/rfc/rfc2445.txt)
	 *
	 * Lines of text SHOULD NOT be longer than 75 octets, excluding the line
	 * break. Long content lines SHOULD be split into a multiple line
	 * representations using a line "folding" technique. That is, a long
	 * line can be split between any two characters by inserting a CRLF
	 * immediately followed by a single linear white space character (i.e.,
	 * SPACE, US-ASCII decimal 32 or HTAB, US-ASCII decimal 9). Any sequence
	 * of CRLF followed immediately by a single linear white space character
	 * is ignored (i.e., removed) when processing the content type.
	 *
	 * @ignore
	 * @since 2.7
	 * @param string $text The string to be escaped.
	 * @return string The escaped string.
	 */
	private static function eventorganiser_fold_ical_text( string $text ): string {

		$text_arr = array();

		$lines = ceil( mb_strlen( $text ) / 75 );

		for ( $i = 0; $i < $lines; $i++ ) {
			$text_arr[ $i ] = mb_substr( $text, $i * 75, 75 );
		}

		return join( "\r\n ", $text_arr );
	}
}
