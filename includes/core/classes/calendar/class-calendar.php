<?php
/**
 * Per-event calendar URL and iCal data wrapper for GatherPress.
 *
 * This file defines the `Calendar` class, instantiated with an event post ID
 * to expose per-event calendar surfaces: subscribe / download URLs for the
 * four supported calendar services (Google, Yahoo, iCal, Outlook) and the
 * VEVENT iCal string for this event's data.
 *
 * Aggregate / request-scoped concerns (the .ics file response, the
 * `<link rel="alternate">` tags in `<head>`, the post-type-archive and
 * taxonomy-term feeds) live on the sibling `Calendar\Setup` class because
 * they operate on `get_queried_object()` rather than a single specific post.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Event;

/**
 * Per-event calendar wrapper.
 *
 * Mirrors the `Event($id)` / `Venue($id)` instantiation pattern: pass an
 * event post ID and the instance exposes the calendar URLs and iCal string
 * for that event. Site-wide / request-scoped concerns live on
 * `Calendar\Setup`.
 *
 * @since 1.0.0
 */
class Calendar {

	/**
	 * Event this Calendar instance wraps.
	 *
	 * @since 1.0.0
	 *
	 * @var Event
	 */
	public Event $event;

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The event post ID.
	 */
	public function __construct( int $post_id ) {
		$this->event = new Event( $post_id );
	}

	/**
	 * URL to the iCal download endpoint for this event.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false Endpoint URL, or false if the event post can't be resolved.
	 */
	public function get_ical_url() {
		return $this->get_endpoint_url( Setup::ICAL_SLUG );
	}

	/**
	 * URL to the Outlook iCal download endpoint for this event.
	 *
	 * Outlook consumes the same `.ics` content as iCal but presents the
	 * download with an Outlook-flavored filename, so the endpoint is just a
	 * sibling slug pointing at the same template.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false Endpoint URL, or false if the event post can't be resolved.
	 */
	public function get_outlook_url() {
		return $this->get_endpoint_url( 'outlook' );
	}

	/**
	 * Google Calendar add-event URL for this event.
	 *
	 * Off-site URL that opens Google Calendar's event-creation form
	 * pre-filled with this event's title, datetime, location, and description.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Google Calendar add-event URL.
	 *
	 * @throws Exception If reading event datetime/venue data fails.
	 */
	public function get_google_url(): string {
		$date_start  = $this->event->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start  = $this->event->get_formatted_datetime( 'His', 'start', false );
		$date_end    = $this->event->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end    = $this->event->get_formatted_datetime( 'His', 'end', false );
		$datetime    = sprintf( '%sT%sZ/%sT%sZ', $date_start, $time_start, $date_end, $time_end );
		$venue       = $this->event->get_venue_information();
		$location    = $venue['name'];
		$description = $this->event->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$params = array(
			'action'   => 'TEMPLATE',
			'text'     => sanitize_text_field( $this->event->event->post_title ),
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
	 * Yahoo! Calendar add-event URL for this event.
	 *
	 * Off-site URL that opens Yahoo! Calendar's event-creation form
	 * pre-filled with this event's title, start time, duration, location,
	 * and description.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Yahoo! Calendar add-event URL.
	 *
	 * @throws Exception If reading event datetime/venue data fails.
	 */
	public function get_yahoo_url(): string {
		$date_start     = $this->event->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $this->event->get_formatted_datetime( 'His', 'start', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );

		// Figure out duration of event in hours and minutes: hhmm format.
		$diff_start  = $this->event->get_formatted_datetime( $this->event::DATETIME_FORMAT, 'start', false );
		$diff_end    = $this->event->get_formatted_datetime( $this->event::DATETIME_FORMAT, 'end', false );
		$duration    = ( ( strtotime( $diff_end ) - strtotime( $diff_start ) ) / 60 / 60 );
		$full        = intval( $duration );
		$fraction    = ( $duration - $full );
		$hours       = str_pad( strval( $duration ), 2, '0', STR_PAD_LEFT );
		$minutes     = str_pad( strval( $fraction * 60 ), 2, '0', STR_PAD_LEFT );
		$venue       = $this->event->get_venue_information();
		$location    = $venue['name'];
		$description = $this->event->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$params = array(
			'v'      => '60',
			'view'   => 'd',
			'type'   => '20',
			'title'  => sanitize_text_field( $this->event->event->post_title ),
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
	 * VEVENT iCal string for this event.
	 *
	 * Builds the `BEGIN:VEVENT` ... `END:VEVENT` block representing this
	 * event. The caller is responsible for wrapping one or more of these
	 * in a `BEGIN:VCALENDAR` envelope (see `Calendar\Setup::get_ical_wrap()`).
	 *
	 * @since 1.0.0
	 *
	 * @return string The VEVENT block.
	 *
	 * @throws Exception If reading event data fails.
	 */
	public function get_ical_event_string(): string {
		$date_start     = $this->event->get_formatted_datetime( 'Ymd', 'start', false );
		$time_start     = $this->event->get_formatted_datetime( 'His', 'start', false );
		$date_end       = $this->event->get_formatted_datetime( 'Ymd', 'end', false );
		$time_end       = $this->event->get_formatted_datetime( 'His', 'end', false );
		$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );
		$datetime_end   = sprintf( '%sT%sZ', $date_end, $time_end );
		$modified_date  = strtotime( $this->event->event->post_modified );
		$datetime_stamp = sprintf( '%sT%sZ', gmdate( 'Ymd', $modified_date ), gmdate( 'His', $modified_date ) );
		$venue          = $this->event->get_venue_information();
		$location       = $venue['name'];
		$description    = $this->event->get_calendar_description();

		if ( ! empty( $venue['full_address'] ) ) {
			$location .= sprintf( ', %s', $venue['full_address'] );
		}

		$args = array(
			'BEGIN:VEVENT',
			sprintf( 'URL:%s', esc_url_raw( get_permalink( $this->event->event->ID ) ) ),
			sprintf( 'DTSTART:%s', sanitize_text_field( $datetime_start ) ),
			sprintf( 'DTEND:%s', sanitize_text_field( $datetime_end ) ),
			sprintf( 'DTSTAMP:%s', sanitize_text_field( $datetime_stamp ) ),
			sprintf( 'SUMMARY:%s', $this->fold_ical_text( sanitize_text_field( $this->event->event->post_title ) ) ),
			sprintf( 'DESCRIPTION:%s', $this->fold_ical_text( sanitize_text_field( $description ) ) ),
			sprintf( 'LOCATION:%s', $this->fold_ical_text( sanitize_text_field( $location ) ) ),
			'UID:gatherpress_' . intval( $this->event->event->ID ),
			'END:VEVENT',
		);

		return implode( "\r\n", $args );
	}

	/**
	 * Build a sanitized endpoint URL for this event with the given slug.
	 *
	 * Inspired by `get_post_embed_url()`. Falls back to a query-string variant
	 * when permalinks are off or a path conflict exists.
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_post_embed_url/
	 *
	 * @since 1.0.0
	 *
	 * @param string      $endpoint_slug The visible suffix appended to the post permalink.
	 * @param string|null $query_var     Optional query var; falls back to `Setup::QUERY_VAR`.
	 * @return string|false              URL of the event's endpoint, or false when the post can't be resolved.
	 */
	protected function get_endpoint_url( string $endpoint_slug, ?string $query_var = null ) {
		$post = $this->event->event;

		if ( ! $post ) {
			return false;
		}

		$query_var = $query_var ?? Setup::QUERY_VAR;

		if ( str_starts_with( $endpoint_slug, 'feed/' ) ) {
			// Feels weird to use a *_comments_* function here, but it delivers clean results
			// in the form of "domain.tld/event/my-sample-event/feed/ical/".
			return (string) get_post_comments_feed_link(
				$post->ID,
				substr( $endpoint_slug, strlen( 'feed/' ) )
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
		 * @param string   $endpoint_url The full post endpoint URL.
		 * @param \WP_Post $post         The corresponding post object.
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
	 * Fold text per [iCal specifications](http://www.ietf.org/rfc/rfc2445.txt).
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
	 * @author Stephen Harris (@stephenharris)
	 * @source https://github.com/stephenharris/Event-Organiser/blob/develop/includes/event-organiser-utility-functions.php#L1663
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The string to be escaped.
	 * @return string The escaped string.
	 */
	private function fold_ical_text( string $text ): string {
		$text_arr = array();

		$lines = ceil( mb_strlen( $text ) / 75 );

		for ( $i = 0; $i < $lines; $i++ ) {
			$text_arr[ $i ] = mb_substr( $text, $i * 75, 75 );
		}

		return join( "\r\n ", $text_arr );
	}
}
