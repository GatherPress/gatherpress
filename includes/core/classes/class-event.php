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

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

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
	const POST_TYPE = 'gatherpress_event';

	/**
	 * Format for the database table name used by GatherPress events.
	 *
	 * @since 1.0.0
	 * @var string $TABLE_FORMAT
	 */
	const TABLE_FORMAT = '%sgatherpress_events';

	/**
	 * ISO 8601 datetime format for calendar services.
	 *
	 * Format combines date (YYYYMMDD) and time (HHMMSS) with 'T' separator
	 * and 'Z' suffix indicating UTC timezone. Example: 20240315T143000Z
	 *
	 * @since 1.0.0
	 * @var string $CALENDAR_DATETIME_FORMAT
	 */
	const CALENDAR_DATETIME_FORMAT = '%sT%sZ';

	/**
	 * Non-time PHP DateTime formatting characters
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const PHP_NON_TIME_FORMAT_CHARS = array(
		'd',
		'D',
		'j',
		'l',
		'N',
		'S',
		'w',
		'z',
		'W',
		'F',
		'm',
		'M',
		'n',
		't',
		'L',
		'o',
		'X',
		'x',
		'Y',
		'y',
		'e',
		'I',
		'O',
		'P',
		'p',
		'T',
		'Z',
		'c',
		'r',
		'U',
		',',
	);

	/**
	 * Event post object.
	 *
	 * @since 1.0.0
	 * @var WP_Post|null
	 */
	public ?WP_Post $event = null;

	/**
	 * RSVP instance.
	 *
	 * @since 1.0.0
	 * @var Rsvp|null
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
		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			$this->event = get_post( $post_id );
			$this->rsvp  = new Rsvp( $post_id );
		}
	}

	/**
	 * Retrieves and formats the event's date and time for display.
	 *
	 * This method generates a formatted string that represents the event's start and end dates and times.
	 * It also considers whether the event's start and end occur on the same day to adjust the format accordingly.
	 * Additionally, it can append the timezone to the formatted string based on settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type           Display type: 'start', 'end', or 'both'.
	 * @param string $start_format   PHP display format for start date/time.
	 * @param string $end_format     PHP display format for end date/time.
	 * @param string $separator      Separator "word" between start and end dates (if both are being displayed).
	 * @param string $show_timezone  Show timezone.
	 *
	 * @return string A string representing the formatted start and end dates/times of the event, or an
	 * em dash if data is unavailable.
	 *
	 * @throws Exception If date/time formatting fails or settings cannot be retrieved.
	 */
	public function get_display_datetime(
		string $type = '',
		string $start_format = '',
		string $end_format = '',
		string $separator = '',
		string $show_timezone = ''
	): string {
		$settings    = Settings::get_instance();
		$date_format = apply_filters(
			'gatherpress_date_format',
			$settings->get_value( 'general', 'formatting', 'date_format' )
		);
		$time_format = apply_filters(
			'gatherpress_time_format',
			$settings->get_value( 'general', 'formatting', 'time_format' )
		);
		$timezone    = $settings->get_value( 'general', 'formatting', 'show_timezone' ) ? ' T' : '';

		$show_start = $type
			? in_array( $type, array( 'start', 'both' ), true )
			: true;

		$show_end = $type
			? in_array( $type, array( 'end', 'both' ), true )
			: true;

		// Set start date/time.
		$start_datetime_format = $start_format ? $start_format : "{$date_format} {$time_format}";
		$start                 = $show_start ? $this->get_datetime_start( $start_datetime_format ) : false;

		// Set end date/time.
		if ( $show_end ) {
			$end_time_format     = $end_format ? $end_format : $time_format;
			$end_datetime_format = $end_format ? $end_format : "{$date_format} {$time_format}";

			$end = $show_start && $this->is_same_date()
				? $this->get_time_end( $end_time_format )
				: $this->get_datetime_end( $end_datetime_format );
		} else {
			$end = false;
		}

		// Add separator if there's both start and end date/time.
		$default_separator = $separator ? $separator : __( 'to', 'gatherpress' );
		$separator         = $start && $end ? $default_separator : false;

		// Add timezone.
		if ( $show_timezone ? 'yes' === $show_timezone : $timezone ) {
			$timezone = $this->get_datetime_start( $timezone );
		} else {
			$timezone = false;
		}

		$parts = array_filter(
			array( $start, $separator, $end, $timezone )
		);

		// Stick the parts back together.
		return $parts ? implode( ' ', $parts ) : '-';
	}

	/**
	 * Check if the start and end DateTime fall on the same date.
	 *
	 * Compares the start and end DateTime objects to determine if they are on the same date.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if start and end are on the same date, false otherwise.
	 *
	 * @throws Exception If date comparison fails.
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
	 *                            A positive value delays the event start,
	 *                            while a negative value checks for an earlier start.
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
	 * This method retrieves the end date and time of the event and formats it
	 * according to the specified PHP date format.
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
	 * Get the end time of an event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format PHP DateTime format (defaults to g:ia).
	 *
	 * @return string The formatting end time of the event.
	 */
	public function get_time_end( string $format = '' ): string {
		return $this->get_datetime_end(
			str_replace(
				static::PHP_NON_TIME_FORMAT_CHARS,
				'',
				$format ? $format : 'g:i a'
			)
		);
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
	public function get_formatted_datetime(
		string $format = 'D, F j, g:ia T',
		string $which = 'start',
		bool $local = true
	): string {
		$dt             = $this->get_datetime();
		$date           = $dt[ sprintf( 'datetime_%s_gmt', $which ) ];
		$dt['timezone'] = Utility::maybe_convert_utc_offset( $dt['timezone'] );
		$tz             = null;

		if (
			true === $local
			&& ! empty( $dt['timezone'] )
			&& in_array( $dt['timezone'], Utility::list_timezone_and_utc_offsets(), true )
		) {
			$tz = new DateTimeZone( $dt['timezone'] );
		} elseif ( false === $local ) {
			$tz = new DateTimeZone( 'GMT+0000' );
		}

		if ( ! empty( $date ) ) {
			$ts   = strtotime( $date );
			$date = wp_date(
				apply_filters( 'gatherpress_datetime_format', $format, $which, $local ),
				$ts,
				$tz
			);
		}

		return (string) trim( $date );
	}

	/**
	 * Retrieves event timing and adjusts timezone based on user preferences or site settings.
	 *
	 * This method fetches the event's start and end dates and times, along with timezone information,
	 * either from a custom database table associated with the event or user metadata. It uses caching
	 * to optimize database interactions, ensuring that data is fetched and stored efficiently for
	 * future requests.
	 *
	 * @since 1.0.0
	 *
	 * @return array An associative array detailing the event's schedule and timezone, potentially
	 * adjusted for user-specific preferences:
	 *     - 'datetime_start'     (string) The event start date and time.
	 *     - 'datetime_start_gmt' (string) The event start date and time in GMT.
	 *     - 'datetime_end'       (string) The event end date and time.
	 *     - 'datetime_end_gmt'   (string) The event end date and time in GMT.
	 *     - 'timezone'           (string) The timezone of the event, adjusted per user or site settings.
	 */
	public function get_datetime(): array {
		global $wpdb;

		$data = array(
			'datetime_start'     => '',
			'datetime_start_gmt' => '',
			'datetime_end'       => '',
			'datetime_end_gmt'   => '',
			'timezone'           => sanitize_text_field( wp_timezone_string() ),
		);

		if ( ! $this->event ) {
			return $data;
		}

		foreach ( $data as $key => & $value ) {
			$result = get_post_meta( $this->event->ID, 'gatherpress_' . $key, true );

			if ( ! empty( $result ) ) {
				$value = $result;
			}
		}

		$data['timezone'] = apply_filters( 'gatherpress_timezone', $data['timezone'] );

		return $data;
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
		if ( empty( $date ) ) {
			return '';
		}

		$datetime = date_create( $date, $timezone );

		if ( false === $datetime ) {
			return '';
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
			$venue_meta = json_decode( get_post_meta( $venue->ID, 'gatherpress_venue_information', true ) );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$venue_information['full_address'] = $venue_meta->fullAddress ?? '';
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$venue_information['phone_number'] = $venue_meta->phoneNumber ?? '';
			$venue_information['website']      = $venue_meta->website ?? '';
			$venue_information['latitude']     = $venue_meta->latitude ?? '';
			$venue_information['longitude']    = $venue_meta->longitude ?? '';
			$venue_information['permalink']    = (string) get_permalink( $venue->ID );
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
				'name' => __( 'iCal', 'gatherpress' ),
				'link' => $this->get_ics_download_link(),
			),
			'outlook' => array(
				'name' => __( 'Outlook', 'gatherpress' ),
				'link' => $this->get_ics_download_link(),
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
	public function get_google_calendar_link(): string {
		$date_start  = $this->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start  = $this->get_formatted_datetime( 'His', 'start', false );
		$date_end    = $this->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end    = $this->get_formatted_datetime( 'His', 'end', false );
		$datetime    = sprintf(
			self::CALENDAR_DATETIME_FORMAT . '/' . self::CALENDAR_DATETIME_FORMAT,
			$date_start,
			$time_start,
			$date_end,
			$time_end
		);
		$venue       = $this->get_venue_information();
		$location    = $venue['name'];
		$description = $this->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$params = array(
			'action'   => 'TEMPLATE',
			'text'     => sanitize_text_field( $this->event->post_title ),
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
		$date_start     = $this->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $this->get_formatted_datetime( 'His', 'start', false );
		$datetime_start = sprintf( self::CALENDAR_DATETIME_FORMAT, $date_start, $time_start );

		// Figure out duration of event in hours and minutes: hhmm format.
		$diff_start  = $this->get_formatted_datetime( self::DATETIME_FORMAT, 'start', false );
		$diff_end    = $this->get_formatted_datetime( self::DATETIME_FORMAT, 'end', false );
		$duration    = ( ( strtotime( $diff_end ) - strtotime( $diff_start ) ) / 60 / 60 );
		$full        = intval( $duration );
		$fraction    = ( $duration - $full );
		$hours       = str_pad( strval( $duration ), 2, '0', STR_PAD_LEFT );
		$minutes     = str_pad( strval( $fraction * 60 ), 2, '0', STR_PAD_LEFT );
		$venue       = $this->get_venue_information();
		$location    = $venue['name'];
		$description = $this->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$params = array(
			'v'      => '60',
			'view'   => 'd',
			'type'   => '20',
			'title'  => sanitize_text_field( $this->event->post_title ),
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
	 * Get the ICS file download link for the event.
	 *
	 * This method returns the full URL to the dynamically generated .ics file
	 * for the event, based on its post slug and the configured event base URL.
	 * The URL points to a route handled by a custom rewrite rule and can be used
	 * in "Add to Calendar" buttons or links.
	 *
	 * The base event path (e.g., "event") is pulled from settings and sanitized
	 * to ensure safe and predictable output.
	 *
	 * Example: https://example.com/event/my-event.ics
	 *
	 * @since 1.0.0
	 *
	 * @return string The public-facing ICS download URL for the event.
	 */
	public function get_ics_download_link(): string {
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get_value( 'general', 'urls', 'events' );

		return home_url(
			'/' . sanitize_title( $rewrite_slug ) . '/' . get_post_field( 'post_name', $this->event->ID ) . '.ics'
		);
	}

	/**
	 * Escape special characters for ICS (iCalendar) text fields.
	 *
	 * This method escapes characters that must be escaped in ICS files,
	 * including commas, semicolons, and line breaks, to ensure compatibility
	 * with calendar applications.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The raw text to be escaped.
	 * @return string The escaped text suitable for ICS content.
	 */
	protected function escape_ics_text( string $text ): string {
		return addcslashes( $text, ",;\\n" );
	}

	/**
	 * Generate the ICS (iCalendar) file content for the event.
	 *
	 * This method constructs and returns a properly formatted ICS string
	 * containing event details such as title, start/end time, description,
	 * location, and a unique identifier. The result can be used to serve
	 * downloadable .ics calendar files compatible with most calendar applications.
	 *
	 * @since 1.0.0
	 *
	 * @return string The raw ICS calendar content for the event.
	 *
	 * @throws Exception If an error occurs while generating event data.
	 */
	public function get_ics_calendar_string(): string {
		$date_start     = $this->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $this->get_formatted_datetime( 'His', 'start', false );
		$date_end       = $this->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end       = $this->get_formatted_datetime( 'His', 'end', false );
		$datetime_start = sprintf( self::CALENDAR_DATETIME_FORMAT, $date_start, $time_start );
		$datetime_end   = sprintf( self::CALENDAR_DATETIME_FORMAT, $date_end, $time_end );
		$modified_date  = strtotime( $this->event->post_modified );
		$datetime_stamp = sprintf(
			self::CALENDAR_DATETIME_FORMAT,
			gmdate( 'Ymd', $modified_date ),
			gmdate( 'His', $modified_date )
		);
		$venue          = $this->get_venue_information();
		$location       = $venue['name'] ?? '';
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
			sprintf( 'SUMMARY:%s', $this->escape_ics_text( $this->event->post_title ) ),
			sprintf( 'DESCRIPTION:%s', $this->escape_ics_text( $description ) ),
			sprintf( 'LOCATION:%s', $this->escape_ics_text( $location ) ),
			sprintf( 'UID:gatherpress_%d', intval( $this->event->ID ) ),
			'END:VEVENT',
			'END:VCALENDAR',
		);

		return implode( "\r\n", $args ) . "\r\n";
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
	public function get_calendar_description(): string {
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

		$params = array_merge(
			array(
				'post_id'        => $this->event->ID,
				'datetime_start' => '',
				'datetime_end'   => '',
				'timezone'       => '',
			),
			$params
		);
		$fields = array_filter(
			$params,
			static function ( $key ) {
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
			return false;
		}

		$fields['timezone'] = ( ! empty( $fields['timezone'] ) ) ? $fields['timezone'] : wp_timezone_string();
		$timezone           = new DateTimeZone( $fields['timezone'] );

		$fields['datetime_start_gmt'] = $this->get_gmt_datetime( (string) $fields['datetime_start'], $timezone );
		$fields['datetime_end_gmt']   = $this->get_gmt_datetime( (string) $fields['datetime_end'], $timezone );

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// @todo Add caching to this and create new method to check existence.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
				'SELECT post_id FROM %i WHERE post_id = %d',
				$table,
				$fields['post_id']
			)
		);

		if ( ! empty( $exists ) ) {
			$value = $wpdb->update(
				$table,
				$fields,
				array( 'post_id' => $fields['post_id'] )
			);

			delete_transient( sprintf( self::DATETIME_CACHE_KEY, $fields['post_id'] ) );
		} else {
			$value = $wpdb->insert( $table, $fields );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $fields as $key => $field ) {
			if ( 'post_id' === $key ) {
				continue;
			}

			$meta_key = sprintf( 'gatherpress_%s', sanitize_key( $key ) );

			update_post_meta(
				$fields['post_id'],
				$meta_key,
				sanitize_text_field( $field )
			);
		}

		return (bool) $value;
	}

	/**
	 * Get the online event link if the user is attending and the event hasn't passed.
	 *
	 * This method retrieves the online event link for a user who is attending an event
	 * and ensures that the event has not already occurred. It evaluates various conditions
	 * to determine whether to provide the online event link.
	 *
	 * @return string The online event link if all conditions are met; otherwise, an empty string.
	 */
	public function maybe_get_online_event_link(): string {
		$event_link = (string) get_post_meta( $this->event->ID, 'gatherpress_online_event_link', true );

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

			$user_identifier = Rsvp_Setup::get_instance()->get_user_identifier();
			$response        = $this->rsvp->get( $user_identifier );

			if (
				! isset( $response['status'] ) ||
				'attending' !== $response['status'] ||
				$this->has_event_past()
			) {
				return '';
			}
		}

		return $event_link;
	}
}
