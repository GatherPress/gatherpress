<?php
/**
 * Class responsible for representing and managing event instances.
 *
 * The Event class is responsible for creating and managing instances of events within the GatherPress plugin.
 * It provides methods for working with event data, such as retrieving event details and managing RSVPs.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use DateTimeZone;
use Exception;
use WP_Post;

/**
 * Class Event.
 *
 * Represents individual events within the GatherPress plugin and provides event-related functionality.
 *
 * @since 1.0.0
 */
class Event {
	/**
	 * Cache key format for storing and retrieving event datetimes.
	 *
	 * @since 1.0.0
	 * @var string $DATETIME_CACHE_KEY
	 */
	const DATETIME_CACHE_KEY = 'datetime_%d';

	/**
	 * Date and time format used within GatherPress.
	 *
	 * @since 1.0.0
	 * @var string $DATETIME_FORMAT
	 */
	const DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * The post type name for GatherPress events.
	 *
	 * @since 1.0.0
	 * @var string $POST_TYPE
	 */
	const POST_TYPE = 'gp_event';

	/**
	 * Format for the database table name used by GatherPress events.
	 *
	 * @since 1.0.0
	 * @var string $TABLE_FORMAT
	 */
	const TABLE_FORMAT = '%sgp_events';

	/**
	 * The taxonomy name for GatherPress event topics.
	 *
	 * @since 1.0.0
	 * @var string $TAXONOMY
	 */
	const TAXONOMY = 'gp_topic';


	/**
	 * Event post object.
	 *
	 * @since 1.0.0
	 * @var array|WP_Post|null
	 */
	protected $event = null;

	/**
	 * RSVP instance.
	 *
	 * @var Rsvp|null
	 * @since 1.0.0
	 */
	public ?Rsvp $rsvp = null;

	/**
	 * Event constructor.
	 *
	 * Initializes an Event object for a specific event post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The event post ID.
	 */
	public function __construct( int $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return null;
		}

		$this->event = get_post( $post_id );
		$this->rsvp  = new Rsvp( $post_id );

		return $this->event;
	}

	/**
	 * Get the arguments for registering the 'Event' custom post type.
	 *
	 * This method retrieves an array containing the registration arguments for the custom post type 'Event'.
	 * These arguments define how the Event post type behaves and appears in the WordPress admin.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing the registration arguments for the custom post type.
	 */
	public static function get_post_type_registration_args(): array {
		return array(
			'labels'        => array(
				'name'               => _x( 'Events', 'Post Type General Name', 'gatherpress' ),
				'singular_name'      => _x( 'Event', 'Post Type Singular Name', 'gatherpress' ),
				'menu_name'          => __( 'Events', 'gatherpress' ),
				'all_items'          => __( 'All Events', 'gatherpress' ),
				'view_item'          => __( 'View Event', 'gatherpress' ),
				'add_new_item'       => __( 'Add New Event', 'gatherpress' ),
				'add_new'            => __( 'Add New', 'gatherpress' ),
				'edit_item'          => __( 'Edit Event', 'gatherpress' ),
				'update_item'        => __( 'Update Event', 'gatherpress' ),
				'search_items'       => __( 'Search Events', 'gatherpress' ),
				'not_found'          => __( 'Not Found', 'gatherpress' ),
				'not_found_in_trash' => __( 'Not found in Trash', 'gatherpress' ),
			),
			'show_in_rest'  => true,
			'rest_base'     => 'gp_events',
			'public'        => true,
			'hierarchical'  => false,
			'template'      => array(
				array( 'gatherpress/event-date' ),
				array( 'gatherpress/add-to-calendar' ),
				array( 'gatherpress/venue' ),
				array( 'gatherpress/rsvp' ),
				array(
					'core/paragraph',
					array(
						'placeholder' => __(
							'Add a description of the event and let people know what to expect, including the agenda, what they need to bring, and how to find the group.',
							'gatherpress'
						),
					),
				),
				array( 'gatherpress/rsvp-response' ),
			),
			'menu_position' => 4,
			'supports'      => array(
				'title',
				'editor',
				'excerpt',
				'thumbnail',
				'comments',
				'revisions',
				'custom-fields',
			),
			'menu_icon'     => 'dashicons-nametag',
			'rewrite'       => array(
				'slug' => 'event',
			),
		);
	}

	/**
	 * Get the registration arguments for custom post meta fields.
	 *
	 * This method retrieves an array containing the registration arguments for custom post meta fields.
	 * These arguments define how specific custom meta fields behave and are used in WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing the registration arguments for custom post meta fields.
	 */
	public static function get_post_meta_registration_args(): array {
		return array(
			'_online_event_link' => array(
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'sanitize_url',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
		);
	}

	/**
	 * Get the registration arguments for the custom 'Topic' taxonomy.
	 *
	 * This method retrieves an array containing the registration arguments for the custom 'Topic' taxonomy.
	 * These arguments define how the 'Topic' taxonomy behaves and is used in WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return array The registration arguments for the 'Topic' taxonomy.
	 */
	public static function get_taxonomy_registration_args(): array {
		return array(
			'labels'            => array(
				'name'              => _x( 'Topics', 'taxonomy general name', 'gatherpress' ),
				'singular_name'     => _x( 'Topic', 'taxonomy singular name', 'gatherpress' ),
				'search_items'      => __( 'Search Topics', 'gatherpress' ),
				'all_items'         => __( 'All Topics', 'gatherpress' ),
				'view_item'         => __( 'View Topic', 'gatherpress' ),
				'parent_item'       => __( 'Parent Topic', 'gatherpress' ),
				'parent_item_colon' => __( 'Parent Topic:', 'gatherpress' ),
				'edit_item'         => __( 'Edit Topic', 'gatherpress' ),
				'update_item'       => __( 'Update Topic', 'gatherpress' ),
				'add_new_item'      => __( 'Add New Topic', 'gatherpress' ),
				'new_item_name'     => __( 'New Topic Name', 'gatherpress' ),
				'not_found'         => __( 'No Topics Found', 'gatherpress' ),
				'back_to_items'     => __( 'Back to Topics', 'gatherpress' ),
				'menu_name'         => __( 'Topics', 'gatherpress' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'topic' ),
			'show_in_rest'      => true,
		);
	}

	/**
	 * Retrieve the formatted display date and time for the event.
	 *
	 * Returns a human-readable representation of the event's start and end date/time,
	 * taking into account whether they fall on the same date or different dates.
	 *
	 * @since 1.0.0
	 *
	 * @return string The formatted display date and time or an em dash if not available.
	 */
	public function get_display_datetime(): string {
		$settings    = Settings::get_instance();
		$date_format = $settings->get_value( 'general', 'formatting', 'date_format' );
		$time_format = $settings->get_value( 'general', 'formatting', 'time_format' );
		$timezone    = $settings->get_value( 'general', 'formatting', 'show_timezone' ) ? ' T' : '';

		if ( $this->is_same_date() ) {
			$start = $this->get_datetime_start( $date_format . ' ' . $time_format );
			$end   = $this->get_datetime_end( $time_format . $timezone );
		} else {
			$start = $this->get_datetime_start( $date_format . ', ' . $time_format );
			$end   = $this->get_datetime_end( $date_format . ', ' . $time_format . $timezone );
		}

		if ( ! empty( $start ) && ! empty( $end ) ) {
			/* translators: %1$s: start datetime, %2$s: end datetime. */
			return sprintf( __( '%1$s to %2$s', 'gatherpress' ), $start, $end );
		}

		return __( '—', 'gatherpress' );
	}

	/**
	 * Check if the start DateTime and end DateTime fall on the same date.
	 *
	 * This method compares the date portion of the start and end DateTime objects to determine
	 * if they represent the same date.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the start and end DateTime are on the same date, false otherwise.
	 */
	public function is_same_date(): bool {
		$datetime_start = $this->get_datetime_start( 'Y-m-d' );
		$datetime_end   = $this->get_datetime_end( 'Y-m-d' );

		if ( empty( $datetime_start ) || empty( $datetime_end ) ) {
			return false;
		}

		if ( $datetime_start === $datetime_end ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the event has yet to occur (in the future).
	 *
	 * This method compares the start datetime of the event with the current time
	 * to determine if the event has yet to take place.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offset The time offset, in minutes, to adjust the consideration of the event end time.
	 *                    A positive value extends the period of considering the event ongoing,
	 *                    while a negative value checks for an earlier end.
	 *                    Default is 0, checking if the event is ongoing at the exact current time.
	 * @return bool True if the event is in the future, false otherwise.
	 */
	public function has_event_started( int $offset = 0 ): bool {
		$data    = $this->get_datetime();
		$start   = $data['datetime_start_gmt'];
		$current = time();

		return ( ! empty( $start ) && $current >= ( strtotime( $start ) + ( $offset * 60 ) ) );
	}

	/**
	 * Check if the event has already occurred (in the past).
	 *
	 * This method compares the end datetime of the event with the current time
	 * to determine if the event has already taken place.
	 *
	 * @since 1.0.0
	 *
	 * @param int $offset The time offset, in minutes, to adjust the consideration of the event start time.
	 *                    A positive value delays the event start, while a negative value checks for an earlier start.
	 *                    Default is 0, checking if the event has started at the exact current time.
	 * @return bool True if the event is in the past, false otherwise.
	 */
	public function has_event_past( int $offset = 0 ): bool {
		$data    = $this->get_datetime();
		$end     = $data['datetime_end_gmt'];
		$current = time() - ( $offset * 60 );

		return ( ! empty( $end ) && $current > ( strtotime( $end ) + ( $offset * 60 ) ) );
	}

	/**
	 * Check if the event is currently happening.
	 *
	 * This method determines whether the event has started and is not in the past.
	 *
	 * @since 1.0.0
	 *
	 * @param int $started_offset The time offset, in minutes, to adjust the consideration of the event start time.
	 *                            A positive value delays the event start, while a negative value checks for an earlier start.
	 *                            Default is 0, checking if the event has started at the exact current time.
	 * @param int $past_offset    The time offset, in minutes, to adjust the consideration of the event end time.
	 *                            A positive value extends the period of considering the event ongoing,
	 *                            while a negative value checks for an earlier end.
	 *                            Default is 0, checking if the event is ongoing at the exact current time.
	 * @return bool True if the event has started and is not in the past, false otherwise.
	 */
	public function is_event_happening( int $started_offset = 0, int $past_offset = 0 ): bool {
		return $this->has_event_started( $started_offset ) && ! $this->has_event_past( $past_offset );
	}

	/**
	 * Get the formatted start datetime of the event.
	 *
	 * This method retrieves and formats the start datetime of the event using the
	 * specified PHP date format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format Optional. PHP date format. Default is 'D, M j, Y, g:i a T'.
	 * @return string The formatted start datetime of the event.
	 *
	 * @throws Exception If there is an issue formatting the start datetime.
	 */
	public function get_datetime_start( string $format = 'D, M j, Y, g:i a T' ): string {
		return $this->get_formatted_datetime( $format, 'start' );
	}

	/**
	 * Get the end date and time of the event.
	 *
	 * This method retrieves the end date and time of the event and formats it according to the specified PHP date format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format Optional. The PHP date format in which to return the end date and time.
	 *                       Default is 'D, F j, g:ia T'.
	 * @return string The formatted end date and time of the event.
	 *
	 * @throws Exception If there is an issue formatting the end date and time.
	 */
	public function get_datetime_end( string $format = 'D, F j, g:ia T' ): string {
		return $this->get_formatted_datetime( $format, 'end' );
	}

	/**
	 * Format a datetime value for display.
	 *
	 * This method takes a datetime value from the event table, formats it according to the specified PHP date format,
	 * and allows you to choose between displaying the date in local time or GMT.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format Optional. The PHP date format in which to format the datetime. Default is 'D, F j, g:ia T'.
	 * @param string $which  Optional. Datetime field in event table to format ('start' or 'end'). Default is 'start'.
	 * @param bool   $local  Optional. Whether to format the date in local time (true) or GMT (false). Default is true.
	 * @return string The formatted datetime value.
	 *
	 * @throws Exception If there is an issue while formatting the datetime value.
	 */
	protected function get_formatted_datetime(
		string $format = 'D, F j, g:ia T',
		string $which = 'start',
		bool $local = true
	): string {
		$dt             = $this->get_datetime();
		$date           = $dt[ sprintf( 'datetime_%s_gmt', $which ) ];
		$dt['timezone'] = static::maybe_convert_offset( $dt['timezone'] );
		$tz             = null;

		if (
			true === $local
			&& ! empty( $dt['timezone'] )
			&& in_array( $dt['timezone'], static::list_identifiers(), true )
		) {
			$tz = new DateTimeZone( $dt['timezone'] );
		} elseif ( false === $local ) {
			$tz = new DateTimeZone( 'GMT+0000' );
		}

		if ( ! empty( $date ) ) {
			$ts   = strtotime( $date );
			$date = wp_date( $format, $ts, $tz );
		}

		return (string) $date;
	}

	/**
	 * Convert a UTC offset to a format compatible with DateTimeZone.
	 *
	 * This method takes a UTC offset in the form of "+HH:mm" or "-HH:mm" and converts it to a format
	 * that can be used with the DateTimeZone constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $timezone The UTC offset to convert, e.g., "+05:30" or "-08:00".
	 * @return string The converted timezone format, e.g., "+0530" or "-0800".
	 */
	public static function maybe_convert_offset( string $timezone ): string {
		// Regex: https://regex101.com/r/wxhjIu/1.
		preg_match( '/^UTC([+-])(\d+)(.\d+)?$/', $timezone, $matches );

		if ( count( $matches ) ) {
			if ( empty( $matches[3] ) ) {
				$matches[3] = ':00';
			}

			$matches[3] = str_replace( array( '.25', '.5', '.75' ), array( ':15', ':30', ':45' ), $matches[3] );

			return $matches[1] . str_pad( $matches[2], 2, '0', STR_PAD_LEFT ) . $matches[3];
		}

		return $timezone;
	}

	/**
	 * Get a list of all timezones and UTC offsets.
	 *
	 * This method returns an array containing all available timezones along with standard UTC offsets.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of timezone identifiers and UTC offsets.
	 */
	public static function list_identifiers(): array {
		// Get a list of all available timezone identifiers.
		$identifiers = timezone_identifiers_list();

		// Define an array of standard UTC offsets.
		$offset_range = array(
			'-12:00',
			'-11:30',
			'-11:00',
			'-10:30',
			'-10:00',
			'-09:30',
			'-09:00',
			'-08:30',
			'-08:00',
			'-07:30',
			'-07:00',
			'-06:30',
			'-06:00',
			'-05:30',
			'-05:00',
			'-04:30',
			'-04:00',
			'-03:30',
			'-03:00',
			'-02:30',
			'-02:00',
			'-01:30',
			'-01:00',
			'-00:30',
			'+00:00',
			'+00:30',
			'+01:00',
			'+01:30',
			'+02:00',
			'+02:30',
			'+03:00',
			'+03:30',
			'+04:00',
			'+04:30',
			'+05:00',
			'+05:30',
			'+05:45',
			'+06:00',
			'+06:30',
			'+07:00',
			'+07:30',
			'+08:00',
			'+08:30',
			'+08:45',
			'+09:00',
			'+09:30',
			'+10:00',
			'+10:30',
			'+11:00',
			'+11:30',
			'+12:00',
			'+12:45',
			'+13:00',
			'+13:45',
			'+14:00',
		);

		// Merge the timezone identifiers and UTC offsets into a single array.
		return array_merge( $identifiers, $offset_range );
	}

	/**
	 * Retrieve event date and time from the custom table.
	 *
	 * This method retrieves the event date, start and end times, as well as the timezone information
	 * from the custom database table for the event. If the event data is not found in the cache, it
	 * will fetch it from the database and store it in the cache for future use.
	 *
	 * @since 1.0.0
	 *
	 * @return array An associative array containing the event date, start and end times, and timezone:
	 *     - 'datetime_start'     (string) The event start date and time.
	 *     - 'datetime_start_gmt' (string) The event start date and time in GMT.
	 *     - 'datetime_end'       (string) The event end date and time.
	 *     - 'datetime_end_gmt'   (string) The event end date and time in GMT.
	 *     - 'timezone'           (string) The timezone of the event.
	 */
	public function get_datetime(): array {
		global $wpdb;

		$default = array(
			'datetime_start'     => '',
			'datetime_start_gmt' => '',
			'datetime_end'       => '',
			'datetime_end_gmt'   => '',
			'timezone'           => sanitize_text_field( wp_timezone_string() ),
		);

		if ( ! $this->event ) {
			return $default;
		}

		$cache_key = sprintf( self::DATETIME_CACHE_KEY, $this->event->ID );
		$data      = wp_cache_get( $cache_key );

		if ( empty( $data ) || ! is_array( $data ) ) {
			$table = sprintf( static::TABLE_FORMAT, $wpdb->prefix );
			$data  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT datetime_start, datetime_start_gmt, datetime_end, datetime_end_gmt, timezone FROM ' . esc_sql( $table ) . ' WHERE post_id = %d LIMIT 1', $this->event->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$data  = ( ! empty( $data ) ) ? (array) current( $data ) : array();

			wp_cache_set( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
		}

		return array_merge(
			$default,
			(array) $data
		);
	}

	/**
	 * Convert a given date to GMT time zone.
	 *
	 * This method takes a date and a specified time zone and converts the date to the equivalent
	 * date and time in the GMT (UTC) time zone. It ensures that the date remains in the correct
	 * format.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $date     The date to be converted.
	 * @param DateTimeZone $timezone The time zone to use for date conversion.
	 * @return string The converted date in GMT (UTC) time zone in 'Y-m-d H:i:s' format.
	 */
	protected function get_gmt_datetime( string $date, DateTimeZone $timezone ): string {
		$datetime = date_create( $date, $timezone );

		if ( false === $datetime ) {
			return '0000-00-00 00:00:00';
		}

		return $datetime->setTimezone( new DateTimeZone( 'UTC' ) )->format( self::DATETIME_FORMAT );
	}

	/**
	 * Get venue information associated with the event.
	 *
	 * This method retrieves information about the venue associated with the event,
	 * including its name, full address, phone number, website, permalink, and whether it's an online event.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing venue information:
	 *               - 'name' (string): The name of the venue.
	 *               - 'full_address' (string): The full address of the venue.
	 *               - 'phone_number' (string): The phone number of the venue.
	 *               - 'website' (string): The website URL of the venue.
	 *               - 'permalink' (string): The permalink (URL) of the venue.
	 *               - 'is_online_event' (bool): Indicates whether the event is an online event (true/false).
	 */
	public function get_venue_information(): array {
		$venue_information = array(
			'name'            => '',
			'full_address'    => '',
			'phone_number'    => '',
			'website'         => '',
			'permalink'       => '',
			'is_online_event' => false,
		);

		$term  = current( (array) get_the_terms( $this->event, Venue::TAXONOMY ) );
		$venue = null;

		if ( ! empty( $term ) && is_a( $term, 'WP_Term' ) ) {
			$venue_information['name'] = $term->name;
			$venue                     = Venue::get_instance()->get_venue_post_from_term_slug( $term->slug );

			if ( 'online-event' === $term->slug ) {
				$venue_information['is_online_event'] = true;
			}
		}

		if ( is_a( $venue, 'WP_Post' ) ) {
			$venue_meta                        = json_decode( get_post_meta( $venue->ID, '_venue_information', true ) );
			$venue_information['full_address'] = $venue_meta->fullAddress ?? ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$venue_information['phone_number'] = $venue_meta->phoneNumber ?? ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$venue_information['website']      = $venue_meta->website ?? '';
			$venue_information['permalink']    = get_permalink( $venue->ID ) ?? '';
		}

		return $venue_information;
	}

	/**
	 * Retrieve all supported add to calendar links for the event.
	 *
	 * This method generates and returns an array of supported add to calendar links for the event,
	 * including Google Calendar, iCal, Outlook, and Yahoo Calendar. Each link is represented as an
	 * associative array with a name and a corresponding link or download URL.
	 *
	 * @since 1.0.0
	 *
	 * @return array An associative array containing supported calendar links:
	 *     - 'google'  (array) Google Calendar link information with 'name' and 'link' keys.
	 *     - 'ical'    (array) iCal download link information with 'name' and 'download' keys.
	 *     - 'outlook' (array) Outlook download link information with 'name' and 'download' keys.
	 *     - 'yahoo'   (array) Yahoo Calendar link information with 'name' and 'link' keys.
	 *
	 * @throws Exception If there is an issue while generating calendar links.
	 */
	public function get_calendar_links(): array {
		if ( ! $this->event ) {
			return array();
		}

		return array(
			'google'  => array(
				'name' => __( 'Google Calendar', 'gatherpress' ),
				'link' => $this->get_google_calendar_link(),
			),
			'ical'    => array(
				'name'     => __( 'iCal', 'gatherpress' ),
				'download' => $this->get_ics_calendar_download(),
			),
			'outlook' => array(
				'name'     => __( 'Outlook', 'gatherpress' ),
				'download' => $this->get_ics_calendar_download(),
			),
			'yahoo'   => array(
				'name' => __( 'Yahoo Calendar', 'gatherpress' ),
				'link' => $this->get_yahoo_calendar_link(),
			),
		);
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
	protected function get_google_calendar_link(): string {
		$date_start  = $this->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start  = $this->get_formatted_datetime( 'His', 'start', false );
		$date_end    = $this->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end    = $this->get_formatted_datetime( 'His', 'end', false );
		$datetime    = sprintf( '%sT%sZ/%sT%sZ', $date_start, $time_start, $date_end, $time_end );
		$venue       = $this->get_venue_information();
		$location    = $venue['name'];
		$description = $this->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => sanitize_text_field( $this->event->post_title ),
				'dates'    => sanitize_text_field( $datetime ),
				'details'  => sanitize_text_field( $description ),
				'location' => sanitize_text_field( $location ),
				'sprop'    => 'name:',
			),
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
	protected function get_yahoo_calendar_link(): string {
		$date_start     = $this->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $this->get_formatted_datetime( 'His', 'start', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );

		// Figure out duration of event in hours and minutes: hhmm format.
		$diff_start  = $this->get_formatted_datetime( self::DATETIME_FORMAT, 'start', false );
		$diff_end    = $this->get_formatted_datetime( self::DATETIME_FORMAT, 'end', false );
		$duration    = ( ( strtotime( $diff_end ) - strtotime( $diff_start ) ) / 60 / 60 );
		$full        = intval( $duration );
		$fraction    = ( $duration - $full );
		$hours       = str_pad( intval( $duration ), 2, '0', STR_PAD_LEFT );
		$minutes     = str_pad( intval( $fraction * 60 ), 2, '0', STR_PAD_LEFT );
		$venue       = $this->get_venue_information();
		$location    = $venue['name'];
		$description = $this->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		return add_query_arg(
			array(
				'v'      => '60',
				'view'   => 'd',
				'type'   => '20',
				'title'  => sanitize_text_field( $this->event->post_title ),
				'st'     => sanitize_text_field( $datetime_start ),
				'dur'    => sanitize_text_field( (string) $hours . (string) $minutes ),
				'desc'   => sanitize_text_field( $description ),
				'in_loc' => sanitize_text_field( $location ),
			),
			'https://calendar.yahoo.com/'
		);
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
	protected function get_ics_calendar_download(): string {
		$date_start     = $this->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $this->get_formatted_datetime( 'His', 'start', false );
		$date_end       = $this->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end       = $this->get_formatted_datetime( 'His', 'end', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );
		$datetime_end   = sprintf( '%sT%sZ', $date_end, $time_end );
		$modified_date  = strtotime( $this->event->post_modified );
		$datetime_stamp = sprintf( '%sT%sZ', gmdate( 'Ymd', $modified_date ), gmdate( 'His', $modified_date ) );
		$venue          = $this->get_venue_information();
		$location       = $venue['name'];
		$description    = $this->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$args = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//GatherPress//RemoteApi//EN',
			'BEGIN:VEVENT',
			sprintf( 'URL:%s', esc_url_raw( get_permalink( $this->event->ID ) ) ),
			sprintf( 'DTSTART:%s', sanitize_text_field( $datetime_start ) ),
			sprintf( 'DTEND:%s', sanitize_text_field( $datetime_end ) ),
			sprintf( 'DTSTAMP:%s', sanitize_text_field( $datetime_stamp ) ),
			sprintf( 'SUMMARY:%s', sanitize_text_field( $this->event->post_title ) ),
			sprintf( 'DESCRIPTION:%s', sanitize_text_field( $description ) ),
			sprintf( 'LOCATION:%s', sanitize_text_field( $location ) ),
			'UID:gatherpress_' . intval( $this->event->ID ),
			'END:VEVENT',
			'END:VCALENDAR',
		);

		return 'data:text/calendar;charset=utf8,' . implode( '%0A', $args );
	}

	/**
	 * Generate a calendar event description with a link to the event details.
	 *
	 * This method generates a descriptive text for a calendar event, including a link to the event details page.
	 * The generated description can be used in calendar applications or event listings.
	 *
	 * @since 1.0.0
	 *
	 * @return string The calendar event description with the event details link.
	 */
	protected function get_calendar_description(): string {
		/* translators: %s: event link. */
		return sprintf( __( 'For details go to %s', 'gatherpress' ), get_the_permalink( $this->event ) );
	}

	/**
	 * Save the start and end datetimes for an event to the custom event table.
	 *
	 * This method allows you to save the start and end datetimes, along with the timezone,
	 * for an event into the custom event table. It provides a structured way to store event data
	 * and ensures consistency in the format of datetime values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params {
	 *     An array of arguments used to save event data to the custom event table.
	 *
	 *     @type int    $post_id        The event's post ID.
	 *     @type string $datetime_start Start DateTime in local time to save for the event.
	 *     @type string $datetime_end   End DateTime in local time to save for the event.
	 *     @type string $timezone       The timezone of the event.
	 * }
	 * @return bool True if the data was successfully saved, false otherwise.
	 *
	 * @throws Exception If there is an issue with datetime conversion or database operations.
	 */
	public function save_datetimes( array $params ): bool {
		global $wpdb;

		$params['post_id'] = $this->event->ID;
		$retval            = false;
		$fields            = array_filter(
			$params,
			function( $key ) {
				return in_array(
					$key,
					array(
						'post_id',
						'datetime_start',
						'datetime_end',
						'timezone',
					),
					true
				);
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( 1 > intval( $fields['post_id'] ) ) {
			return $retval;
		}

		$fields['timezone'] = ( ! empty( $fields['timezone'] ) ) ? $fields['timezone'] : wp_timezone_string();
		$timezone           = new DateTimeZone( $fields['timezone'] );

		$fields['datetime_start_gmt'] = $this->get_gmt_datetime( $fields['datetime_start'], $timezone );
		$fields['datetime_end_gmt']   = $this->get_gmt_datetime( $fields['datetime_end'], $timezone );

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// @todo Add caching to this and create new method to check existence.
		$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT post_id FROM ' . esc_sql( $table ) . ' WHERE post_id = %d',
				$fields['post_id']
			)
		);

		if ( ! empty( $exists ) ) {
			$retval = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table,
				$fields,
				array( 'post_id' => $fields['post_id'] )
			);
			wp_cache_delete( sprintf( self::DATETIME_CACHE_KEY, $fields['post_id'] ) );
		} else {
			$retval = $wpdb->insert( $table, $fields ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return (bool) $retval;
	}

	/**
	 * Get the online event link if the user is attending and the event hasn't passed.
	 *
	 * This method retrieves the online event link for a user who is attending an event
	 * and ensures that the event has not already occurred. It evaluates various conditions
	 * to determine whether to provide the online event link. The method is marked with a @todo
	 * to indicate that it should be refactored for improved readability and reduced conditionals.
	 *
	 * @return string The online event link if all conditions are met; otherwise, an empty string.
	 */
	public function maybe_get_online_event_link(): string {
		$event_link = (string) get_post_meta( $this->event->ID, '_online_event_link', true );

		/**
		 * Filters whether to force the display of the online event link.
		 *
		 * Allows modification of the decision to force the online event link
		 * display in the `maybe_get_online_event_link` method. Return true to
		 * force the online event link, or false to allow normal checks.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $force_online_event_link Whether to force the display of the online event link.
		 *
		 * @return bool True to force online event link, false to allow normal checks.
		 */
		$force_online_event_link = apply_filters( 'gatherpress_force_online_event_link', false );

		if ( ! $force_online_event_link && ! is_admin() ) {
			if ( ! $this->rsvp ) {
				return '';
			}

			$user = $this->rsvp->get( get_current_user_id() );

			if (
				! isset( $user['status'] ) ||
				'attending' !== $user['status'] ||
				! $this->is_event_happening( -5 )
			) {
				return '';
			}
		}

		return $event_link;
	}
}
