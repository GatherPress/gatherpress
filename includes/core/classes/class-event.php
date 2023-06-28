<?php
/**
 * Class is responsible for instances of events.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) { // @codeCoverageIgnore
	exit; // @codeCoverageIgnore
}

/**
 * Class Event.
 */
class Event {

	const POST_TYPE          = 'gp_event';
	const TAXONOMY           = 'gp_topic';
	const TABLE_FORMAT       = '%sgp_events';
	const DATETIME_CACHE_KEY = 'datetime_%d';

	/**
	 * Event post object.
	 *
	 * @var array|\WP_Post|null
	 */
	protected $event = null;

	/**
	 * Attendee instance.
	 *
	 * @var Attendee
	 */
	public $attendee;

	/**
	 * Event constructor.
	 *
	 * @param int $post_id An event post ID.
	 */
	public function __construct( int $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return null;
		}

		$this->event    = get_post( $post_id );
		$this->attendee = new Attendee( $post_id );

		return $this->event;
	}

	/**
	 * Get display DateTime.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_display_datetime(): string {
		if ( $this->is_same_date() ) {
			$start = $this->get_datetime_start( 'l, F j, Y g:i A' );
			$end   = $this->get_datetime_end( 'g:i A T' );
		} else {
			$start = $this->get_datetime_start( 'l, F j, Y, g:i A' );
			$end   = $this->get_datetime_end( 'l, F j, Y, g:i A T' );
		}

		return sprintf( '%s to %s', $start, $end );
	}

	/**
	 * Check if start DateTime and end DateTime is same date.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
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
	 * Check if event is in the past.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function has_event_past(): bool {
		$data    = $this->get_datetime();
		$end     = $data['datetime_end_gmt'];
		$current = time();

		if ( $current > strtotime( $end ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get datetime start.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format  PHP date format.
	 *
	 * @return string
	 */
	public function get_datetime_start( string $format = 'D, M j, Y, g:i a T' ): string {
		return $this->get_formatted_datetime( $format, 'start' );
	}

	/**
	 * Get venue information from event.
	 *
	 * @return array
	 */
	public function get_venue_information(): array {
		$venue_information = array(
			'name'         => '',
			'full_address' => '',
			'phone_number' => '',
			'website'      => '',
			'permalink'    => '',
		);

		$term  = current( (array) get_the_terms( $this->event, Venue::TAXONOMY ) );
		$venue = null;

		if ( ! empty( $term ) && is_a( $term, 'WP_Term' ) ) {
			$venue_information['name'] = $term->name;
			$venue                     = Venue::get_instance()->get_venue_post_from_term_slug( $term->slug );
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
	 * Get datetime end.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format  PHP date format.
	 *
	 * @return string
	 */
	public function get_datetime_end( string $format = 'D, F j, g:ia T' ): string {
		return $this->get_formatted_datetime( $format, 'end' );
	}

	/**
	 * Format datetime for display.
	 *
	 * @param string  $format  PHP date format.
	 * @param string  $which   The datetime field in event table.
	 * @param boolean $local   Whether to format date in local time or GMT.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	protected function get_formatted_datetime( string $format = 'D, F j, g:ia T', string $which = 'start', bool $local = true ): string {
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
	 * Maybe convert the UTC offset to format that can be passed to DateTimeZone.
	 *
	 * @param string $timezone The time zone.
	 *
	 * @return string
	 */
	public static function maybe_convert_offset( string $timezone ): string {
		// Regex: https://regex101.com/r/9bMgJd/1.
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
	 * Lists all timezones and UTC offsets.
	 *
	 * @return array
	 */
	public static function list_identifiers(): array {
		$identifiers  = timezone_identifiers_list();
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

		return array_merge( $identifiers, $offset_range );
	}

	/**
	 * Get the datetime from custom table.
	 *
	 * @since 1.0.0
	 *
	 * @return array
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
	 * Get all supported add to calendar links for event.
	 *
	 * @since 1.0.0
	 *
	 * @todo need to add venue location for all calendar methods when feature is done.
	 *
	 * @return array
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
	 * Get add to Google calendar link for event.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_google_calendar_link(): string {
		$date_start = $this->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start = $this->get_formatted_datetime( 'His', 'start', false );
		$date_end   = $this->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end   = $this->get_formatted_datetime( 'His', 'end', false );
		$datetime   = sprintf( '%sT%sZ/%sT%sZ', $date_start, $time_start, $date_end, $time_end );
		$venue      = $this->get_venue_information();

		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => sanitize_text_field( $this->event->post_title ),
				'dates'    => sanitize_text_field( $datetime ),
				'details'  => sanitize_text_field( $this->event->post_content ),
				'location' => sanitize_text_field( $venue['name'] . ' (' . $venue['full_address'] . ')' ),
				'sprop'    => 'name:',
			),
			'https://www.google.com/calendar/event'
		);
	}

	/**
	 * Get add to Yahoo! calendar link for event.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_yahoo_calendar_link(): string {
		$date_start     = $this->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $this->get_formatted_datetime( 'His', 'start', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );

		// Figure out duration of event in hours and minutes: hhmm format.
		$diff_start = $this->get_formatted_datetime( 'Y-m-d H:i:s', 'start', false );
		$diff_end   = $this->get_formatted_datetime( 'Y-m-d H:i:s', 'end', false );
		$duration   = ( ( strtotime( $diff_end ) - strtotime( $diff_start ) ) / 60 / 60 );
		$full       = intval( $duration );
		$fraction   = ( $duration - $full );
		$hours      = str_pad( intval( $duration ), 2, '0', STR_PAD_LEFT );
		$minutes    = str_pad( intval( $fraction * 60 ), 2, '0', STR_PAD_LEFT );
		$venue      = $this->get_venue_information();

		return add_query_arg(
			array(
				'v'      => '60',
				'view'   => 'd',
				'type'   => '20',
				'title'  => sanitize_text_field( $this->event->post_title ),
				'st'     => sanitize_text_field( $datetime_start ),
				'dur'    => sanitize_text_field( (string) $hours . (string) $minutes ),
				'desc'   => sanitize_text_field( $this->event->post_content ),
				'in_loc' => sanitize_text_field( $venue['name'] . ' (' . $venue['full_address'] . ')' ),
			),
			'https://calendar.yahoo.com/'
		);
	}

	/**
	 * Get ICS download for event.
	 *
	 * @since 1.0.0
	 *
	 * @return string
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
			sprintf( 'DESCRIPTION:%s', sanitize_text_field( $this->event->post_content ) ),
			sprintf( 'LOCATION:%s', sanitize_text_field( $venue['name'] . ' (' . $venue['full_address'] ) . ')' ),
			'UID:gatherpress_' . intval( $this->event->ID ),
			'END:VEVENT',
			'END:VCALENDAR',
		);

		return 'data:text/calendar;charset=utf8,' . implode( '%0A', $args );
	}

	/**
	 * Adjust SQL for Event queries to join on gp_event_extended table.
	 *
	 * @todo remove this static method from Event class and add it to Query class.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $pieces Includes pieces of the query like join, where, orderby, et al.
	 * @param string $type   Options are all, upcoming, or past.
	 * @param string $order  Event order DESC or ASC.
	 *
	 * @return array
	 */
	public static function adjust_sql( array $pieces, string $type = 'all', string $order = 'DESC' ): array {
		global $wpdb;

		$defaults        = array(
			'where'    => '',
			'groupby'  => '',
			'join'     => '',
			'orderby'  => '',
			'distinct' => '',
			'fields'   => '',
			'limits'   => '',
		);
		$pieces          = array_merge( $defaults, $pieces );
		$table           = sprintf( self::TABLE_FORMAT, $wpdb->prefix );
		$pieces['join'] .= ' LEFT JOIN ' . esc_sql( $table ) . ' ON ' . esc_sql( $wpdb->posts ) . '.ID=' . esc_sql( $table ) . '.post_id';
		$order           = strtoupper( $order );

		if ( in_array( $order, array( 'DESC', 'ASC' ), true ) ) {
			$pieces['orderby'] = sprintf( esc_sql( $table ) . '.datetime_start_gmt %s', esc_sql( $order ) );
		}

		if ( 'all' !== $type ) {
			$current = gmdate( 'Y-m-d H:i:s', time() );

			switch ( $type ) {
				case 'upcoming':
					$pieces['where'] .= $wpdb->prepare( ' AND ' . esc_sql( $table ) . '.datetime_end_gmt >= %s', esc_sql( $current ) );
					break;
				case 'past':
					$pieces['where'] .= $wpdb->prepare( ' AND ' . esc_sql( $table ) . '.datetime_end_gmt < %s', esc_sql( $current ) );
					break;
			}
		}

		return $pieces;
	}

	/**
	 * Save the start and end datetimes for an event.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params {
	 *     An array of arguments used to save event data to custom event table.
	 *
	 *     @type string  $datetime_start Start DateTime to save for event.
	 *     @type string  $datetime_end   End DateTime to save for event.
	 *     @type string  $timezone       Timezone of the event.
	 *
	 * }
	 *
	 * @return bool
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

		$table = sprintf( static::TABLE_FORMAT, $wpdb->prefix );

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
	 * Get online event link if user is attending and event hasn't past.
	 *
	 * @return string
	 */
	public function maybe_get_online_event_link(): string {
		if ( ! $this->attendee ) {
			return '';
		}

		$user = $this->attendee->get( get_current_user_id() );

		if (
			! isset( $user['status'] ) ||
			'attending' !== $user['status'] ||
			$this->has_event_past()
		) {
			return '';
		}

		return (string) get_post_meta( $this->event->ID, '_online_event_link', true );
	}

	/**
	 * Convert the date to GMT.
	 *
	 * @param string       $date     The date to be converted.
	 * @param DateTimeZone $timezone Time zone to use for date conversion.
	 *
	 * @return string
	 */
	protected function get_gmt_datetime( string $date, DateTimeZone $timezone ): string {
		$format   = 'Y-m-d H:i:s';
		$datetime = date_create( $date, $timezone );

		if ( false === $datetime ) {
			return gmdate( $format, 0 );
		}

		return $datetime->setTimezone( new DateTimeZone( 'UTC' ) )->format( $format );
	}

}
