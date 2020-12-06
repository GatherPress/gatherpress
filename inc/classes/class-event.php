<?php
/**
 * Class is responsible for instances of events.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Inc;

use PHPMailer\PHPMailer\Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Event.
 */
class Event {

	const POST_TYPE          = 'gp_event';
	const TABLE_FORMAT       = '%s%s_extended';
	const DATETIME_CACHE_KEY = 'datetime_%d';

	/**
	 * Event post object.
	 *
	 * @var array|\WP_Post|null
	 */
	protected $event;

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
	public function get_display_datetime() : string {
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
	public function is_same_date() : bool {
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
	public function has_event_past() : bool {
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
	public function get_datetime_start( string $format = 'D, F j, g:ia T' ) : string {
		return $this->get_formatted_date( $format, 'start' );
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
	public function get_datetime_end( string $format = 'D, F j, g:ia T' ) : string {
		return $this->get_formatted_date( $format, 'end' );
	}

	/**
	 * Format date for display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format  PHP date format.
	 * @param string $which   The datetime field in event table.
	 * @param boolean $local  Whether to format date in local time or GMT.
	 *
	 * @return string
	 */
	protected function get_formatted_date( string $format = 'D, F j, g:ia T', string $which = 'start', $local = true ) : string {
		$dt   = $this->get_datetime();
		$date = $dt[ sprintf( 'datetime_%s_gmt', $which ) ];
		$tz   = null;

		if ( true === $local ) {
			try {
				$tz = new \DateTimeZone( $dt['timezone'] );
			} catch( Exception $e ) {
				$tz = wp_timezone_string();

				 if ( ! preg_match( '/^-|\+/', $tz ) ) {
					$tz = date_default_timezone_get();
				 }
			}
		}

		if ( ! empty( $date ) ) {
			$ts   = strtotime( $date );
			$date = wp_date( $format, $ts, $tz );
		}

		return (string) $date;
	}

	/**
	 * Get the datetime from custom table.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_datetime() : array {
		global $wpdb;

		$data = array();

		if ( self::POST_TYPE === $this->event->post_type ) {
			$cache_key = sprintf( self::DATETIME_CACHE_KEY, $this->event->ID );
			$data      = wp_cache_get( $cache_key );

			if ( empty( $data ) || ! is_array( $data ) ) {
				$table = sprintf( static::TABLE_FORMAT, $wpdb->prefix, static::POST_TYPE );
				$data  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT datetime_start, datetime_start_gmt, datetime_end, datetime_end_gmt, timezone FROM ' . esc_sql( $table ) . ' WHERE post_id = %d LIMIT 1', $this->event->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$data  = ( ! empty( $data ) ) ? (array) current( $data ) : array();

				wp_cache_set( $cache_key, $data, 15 * MINUTE_IN_SECONDS );
			}
		}

		return array_merge(
			array(
				'datetime_start'     => '',
				'datetime_start_gmt' => '',
				'datetime_end'       => '',
				'datetime_end_gmt'   => '',
			),
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
	public function get_calendar_links() : array {
		if ( self::POST_TYPE !== $this->event->post_type ) {
			return array();
		}

		return array(
			'google' => $this->get_google_calendar_link(),
			'isc'    => $this->get_ics_calendar_download(),
			'yahoo'  => $this->get_yahoo_calendar_link(),
		);
	}

	/**
	 * Get add to Google calendar link for event.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_google_calendar_link() : string {
		$date_start = $this->get_formatted_date( 'Ymd', 'start', false );
		$time_start = $this->get_formatted_date( 'His', 'start', false );
		$date_end   = $this->get_formatted_date( 'Ymd', 'end', false );
		$time_end   = $this->get_formatted_date( 'His', 'end', false );
		$datetime   = sprintf( '%sT%sZ/%sT%sZ', $date_start, $time_start, $date_end, $time_end );

		return add_query_arg(
			array(
				'action'   => 'TEMPLATE',
				'text'     => sanitize_text_field( $this->event->post_title ),
				'dates'    => sanitize_text_field( $datetime ),
				'details'  => sanitize_text_field( $this->event->post_content ),
				'location' => '',
				'sprop'    => 'name:',
			),
			'https://www.google.com/calendar/render/'
		);
	}

	/**
	 * Get add to Yahoo! calendar link for event.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_yahoo_calendar_link() : string {
		$date_start     = $this->get_formatted_date( 'Ymd', 'start', false );
		$time_start     = $this->get_formatted_date( 'His', 'start', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );

		// Figure out duration of event in hours and minutes: hhmm format.
		$diff_start = $this->get_formatted_date( 'Y-m-d H:i:s', 'start', false );
		$diff_end   = $this->get_formatted_date( 'Y-m-d H:i:s', 'end', false );
		$duration   = ( ( strtotime( $diff_end ) - strtotime( $diff_start ) ) / 60 / 60 );
		$full       = intval( $duration );
		$fraction   = ( $duration - $full );
		$hours      = str_pad( intval( $duration ), 2, '0', STR_PAD_LEFT );
		$minutes    = str_pad( intval( $fraction * 60 ), 2, '0', STR_PAD_LEFT );

		return add_query_arg(
			array(
				'v'      => '60',
				'view'   => 'd',
				'type'   => '20',
				'title'  => sanitize_text_field( $this->event->post_title ),
				'st'     => sanitize_text_field( $datetime_start ),
				'dur'    => sanitize_text_field( (string) $hours . (string) $minutes ),
				'desc'   => sanitize_text_field( $this->event->post_content ),
				'in_loc' => '',
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
	protected function get_ics_calendar_download() : string {
		$date_start     = $this->get_formatted_date( 'Ymd', 'start', false );
		$time_start     = $this->get_formatted_date( 'His', 'start', false );
		$date_end       = $this->get_formatted_date( 'Ymd', 'end', false );
		$time_end       = $this->get_formatted_date( 'His', 'end', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );
		$datetime_end   = sprintf( '%sT%sZ', $date_end, $time_end );

		$args = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'BEGIN:VEVENT',
			sprintf( 'URL:%s', esc_url_raw( get_permalink( $this->event->ID ) ) ),
			sprintf( 'DTSTART:%s', sanitize_text_field( $datetime_start ) ),
			sprintf( 'DTEND:%s', sanitize_text_field( $datetime_end ) ),
			sprintf( 'SUMMARY:%s', sanitize_text_field( $this->event->post_title ) ),
			sprintf( 'DESCRIPTION:%s', sanitize_text_field( $this->event->post_content ) ),
			sprintf( 'LOCATION:%s', '' ),
			'END:VEVENT',
			'END:VCALENDAR',
		);

		return 'data:text/calendar;charset=utf8,' . implode( '%0A', $args );
	}

	/**
	 * Adjust SQL for Event queries to join on gp_event_extended table.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $pieces Includes pieces of the query like join, where, orderby, et al.
	 * @param string $type   Options are all, future, or past.
	 * @param string $order  Event order DESC or ASC.
	 *
	 * @return array
	 */
	public static function adjust_sql( array $pieces, string $type = 'all', string $order = 'DESC' ) : array {
		global $wp_query, $wpdb;

		$defaults = array(
			'where'    => '',
			'groupby'  => '',
			'join'     => '',
			'orderby'  => '',
			'distinct' => '',
			'fields'   => '',
			'limits'   => '',
		);
		$pieces         = array_merge( $defaults, $pieces );
		$table          = sprintf( self::TABLE_FORMAT, $wpdb->prefix, self::POST_TYPE );
		$pieces['join'] = 'LEFT JOIN ' . esc_sql( $table ) . ' ON ' . esc_sql( $wpdb->posts ) . '.ID=' . esc_sql( $table ) . '.post_id';
		$order          = strtoupper( $order );

		if ( in_array( $order, array( 'DESC', 'ASC' ), true ) ) {
			$pieces['orderby'] = sprintf( esc_sql( $table ) . '.datetime_start_gmt %s', esc_sql( $order ) );
		}

		if ( 'all' !== $type ) {
			$current = gmdate( 'Y-m-d H:i:s', time() );

			switch ( $type ) {
				case 'future':
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
	 *     @type int     $post_id        An event post ID.
	 *     @type string  $datetime_start Start DateTime to save for event.
	 *     @type string  $datetime_end   End DateTime to save for event.
	 *     @type string  $timezone       Timezone of the event.
	 *
	 * }
	 *
	 * @return bool
	 */
	public static function save_datetimes( array $params ) : bool {
		global $wpdb;

		$retval = false;
		$fields = array_filter(
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

		$fields['datetime_start_gmt'] = get_gmt_from_date( $fields['datetime_start'] );
		$fields['datetime_end_gmt']   = get_gmt_from_date( $fields['datetime_end'] );
		$fields['timezone']           = ( ! empty( $fields['timezone'] ) ) ? $fields['timezone'] : wp_timezone_string();
		$table                        = sprintf( static::TABLE_FORMAT, $wpdb->prefix, static::POST_TYPE );

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

}
