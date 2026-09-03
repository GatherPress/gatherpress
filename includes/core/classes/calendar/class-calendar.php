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
 * @since 0.34.0
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
 * @since 0.34.0
 */
final class Calendar {

	/**
	 * Epoch the VEVENT `SEQUENCE` counts from, as a Unix timestamp.
	 *
	 * 2020-01-01 00:00:00 UTC. See `get_sequence()` for why the sequence is
	 * measured from an epoch rather than being a raw timestamp.
	 *
	 * @since 0.35.0
	 * @var int
	 */
	private const SEQUENCE_EPOCH = 1577836800;

	/**
	 * Event this Calendar instance wraps.
	 *
	 * @since 0.34.0
	 *
	 * @var Event
	 */
	public readonly Event $event;

	/**
	 * Class constructor.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id The event post ID.
	 */
	public function __construct( int $post_id ) {
		$this->event = new Event( $post_id );
	}

	/**
	 * URL to the iCal download endpoint for this event.
	 *
	 * @since 0.34.0
	 *
	 * @return string|false Endpoint URL, or false if the event post can't be resolved.
	 */
	public function get_ical_url(): string|false {
		return $this->get_endpoint_url( Setup::ICAL_SLUG );
	}

	/**
	 * URL to the Outlook iCal download endpoint for this event.
	 *
	 * Outlook consumes the same `.ics` content as iCal but presents the
	 * download with an Outlook-flavored filename, so the endpoint is just a
	 * sibling slug pointing at the same template.
	 *
	 * @since 0.34.0
	 *
	 * @return string|false Endpoint URL, or false if the event post can't be resolved.
	 */
	public function get_outlook_url(): string|false {
		return $this->get_endpoint_url( 'outlook' );
	}

	/**
	 * URL to the Google Calendar redirect endpoint for this event.
	 *
	 * Resolves to `/event/<slug>/google-calendar/` (subject to permalink
	 * structure). A hit on this URL 302-redirects out to Google Calendar
	 * with the event pre-filled — the off-site destination URL itself is
	 * computed by `get_google_destination_url()`, called from the Redirect
	 * endpoint callback in `Calendar\Setup`.
	 *
	 * Front-end calendar UIs link to this on-site URL rather than the
	 * direct Google URL so themes, the `gatherpress_calendar_url` filter,
	 * and any CDN/federation tooling see a stable canonical link.
	 *
	 * @since 0.34.0
	 *
	 * @return string|false Endpoint URL, or false if the event post can't be resolved.
	 */
	public function get_google_url(): string|false {
		return $this->get_endpoint_url( 'google-calendar' );
	}

	/**
	 * URL to the Yahoo! Calendar redirect endpoint for this event.
	 *
	 * Same redirect pattern as the Google equivalent — see
	 * `get_google_url()`. The destination Yahoo! URL is computed by
	 * `get_yahoo_destination_url()`, called from the Redirect endpoint
	 * callback in `Calendar\Setup`.
	 *
	 * @since 0.34.0
	 *
	 * @return string|false Endpoint URL, or false if the event post can't be resolved.
	 */
	public function get_yahoo_url(): string|false {
		return $this->get_endpoint_url( 'yahoo-calendar' );
	}

	/**
	 * Off-site destination URL for the Google Calendar redirect.
	 *
	 * Opens Google Calendar's event-creation form pre-filled with this
	 * event's title, datetime, location, and description. Called from
	 * `Calendar\Setup::queried_event_google_url()` to produce the 302
	 * target for the `/event/<slug>/google-calendar/` endpoint — front-end
	 * code should use `get_google_url()` (the on-site URL) instead.
	 *
	 * @since 0.34.0
	 *
	 * @return string The Google Calendar add-event URL, or an empty string when the event post can't be resolved.
	 *
	 * @throws Exception If reading event datetime/venue data fails.
	 */
	public function get_google_destination_url(): string {
		$post = $this->event->post;

		if ( ! $post ) {
			return '';
		}

		if ( $this->event->is_all_day() ) {
			$date_start   = $this->event->get_formatted_datetime( 'Ymd', 'start' );
			$end_date_str = $this->event->get_formatted_datetime( 'Y-m-d', 'end' );
			$date_end     = gmdate( 'Ymd', (int) strtotime( $end_date_str . ' +1 day' ) );
			$datetime     = sprintf( '%s/%s', $date_start, $date_end );
		} else {
			$date_start = $this->event->get_formatted_datetime( 'Ymd', 'start', false );
			$time_start = $this->event->get_formatted_datetime( 'His', 'start', false );
			$date_end   = $this->event->get_formatted_datetime( 'Ymd', 'end', false );
			$time_end   = $this->event->get_formatted_datetime( 'His', 'end', false );
			$datetime   = sprintf( '%sT%sZ/%sT%sZ', $date_start, $time_start, $date_end, $time_end );
		}

		$venue       = $this->event->get_venue_information();
		$location    = $venue['name'];
		$description = $this->event->get_calendar_description();

		if ( ! empty( $venue['address'] ) ) {
			$location .= sprintf( ', %s', $venue['address'] );
		}

		$params = array(
			'action'   => 'TEMPLATE',
			'text'     => sanitize_text_field( $post->post_title ),
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
	 * Off-site destination URL for the Yahoo! Calendar redirect.
	 *
	 * Opens Yahoo! Calendar's event-creation form pre-filled with this
	 * event's title, start time, duration, location, and description.
	 * Called from `Calendar\Setup::queried_event_yahoo_url()` to produce
	 * the 302 target for the `/event/<slug>/yahoo-calendar/` endpoint —
	 * front-end code should use `get_yahoo_url()` (the on-site URL) instead.
	 *
	 * @since 0.34.0
	 *
	 * @return string The Yahoo! Calendar add-event URL, or an empty string when the event post can't be resolved.
	 *
	 * @throws Exception If reading event datetime/venue data fails.
	 */
	public function get_yahoo_destination_url(): string {
		$post = $this->event->post;

		if ( ! $post ) {
			return '';
		}

		if ( $this->event->is_all_day() ) {
			// Yahoo takes two plain dates and its own flag, not a duration.
			// `dur` is an `hhmm` field, and a single all-day event already
			// needs more hours than the two digits hold. Local dates, since
			// an all-day date is floating and must not shift with the zone.
			$timing = array(
				'st'     => $this->event->get_formatted_datetime( 'Ymd', 'start' ),
				'et'     => $this->event->get_formatted_datetime( 'Ymd', 'end' ),
				'allday' => 'true',
			);
		} else {
			$date_start = $this->event->get_formatted_datetime( 'Ymd', 'start', false );
			$time_start = $this->event->get_formatted_datetime( 'His', 'start', false );

			// Figure out duration of event in hours and minutes: hhmm format.
			$diff_start = $this->event->get_formatted_datetime( $this->event::DATETIME_FORMAT, 'start', false );
			$diff_end   = $this->event->get_formatted_datetime( $this->event::DATETIME_FORMAT, 'end', false );
			$duration   = ( ( strtotime( $diff_end ) - strtotime( $diff_start ) ) / 60 / 60 );
			$full       = intval( $duration );
			$fraction   = ( $duration - $full );
			$hours      = str_pad( strval( $duration ), 2, '0', STR_PAD_LEFT );
			$minutes    = str_pad( strval( $fraction * 60 ), 2, '0', STR_PAD_LEFT );

			$timing = array(
				'st'  => sprintf( '%sT%sZ', $date_start, $time_start ),
				'dur' => $hours . $minutes,
			);
		}

		$venue       = $this->event->get_venue_information();
		$location    = $venue['name'];
		$description = $this->event->get_calendar_description();

		if ( ! empty( $venue['address'] ) ) {
			$location .= sprintf( ', %s', $venue['address'] );
		}

		$params = array_merge(
			array(
				'v'     => '60',
				'view'  => 'd',
				'type'  => '20',
				'title' => sanitize_text_field( $post->post_title ),
			),
			array_map( 'sanitize_text_field', $timing ),
			array(
				'desc'   => sanitize_text_field( $description ),
				'in_loc' => sanitize_text_field( $location ),
			)
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
	 * @since 0.34.0
	 *
	 * @return string The VEVENT block, or an empty string when the event post can't be resolved.
	 *
	 * @throws Exception If reading event data fails.
	 */
	public function get_ical_event_string(): string {
		$post = $this->event->post;

		if ( ! $post ) {
			return '';
		}

		if ( $this->event->is_all_day() ) {
			$date_start   = $this->event->get_formatted_datetime( 'Ymd', 'start' );
			$end_date_str = $this->event->get_formatted_datetime( 'Y-m-d', 'end' );
			$date_end     = gmdate( 'Ymd', (int) strtotime( $end_date_str . ' +1 day' ) );
			$dtstart_line = sprintf( 'DTSTART;VALUE=DATE:%s', sanitize_text_field( $date_start ) );
			$dtend_line   = sprintf( 'DTEND;VALUE=DATE:%s', sanitize_text_field( $date_end ) );
		} else {
			$date_start     = $this->event->get_formatted_datetime( 'Ymd', 'start', false );
			$time_start     = $this->event->get_formatted_datetime( 'His', 'start', false );
			$date_end       = $this->event->get_formatted_datetime( 'Ymd', 'end', false );
			$time_end       = $this->event->get_formatted_datetime( 'His', 'end', false );
			$datetime_start = sprintf( '%sT%sZ', $date_start, $time_start );
			$datetime_end   = sprintf( '%sT%sZ', $date_end, $time_end );
			$dtstart_line   = sprintf( 'DTSTART:%s', sanitize_text_field( $datetime_start ) );
			$dtend_line     = sprintf( 'DTEND:%s', sanitize_text_field( $datetime_end ) );
		}

		$modified_gmt = strtotime( $post->post_modified_gmt );

		if ( false === $modified_gmt ) {
			// DTSTAMP is required by RFC 5545, so an unparsable date still needs one.
			$modified_gmt = time();
		}

		$datetime_stamp = sprintf( '%sT%sZ', gmdate( 'Ymd', $modified_gmt ), gmdate( 'His', $modified_gmt ) );
		$last_modified  = $datetime_stamp;
		$sequence       = $this->get_sequence();
		$venue          = $this->event->get_venue_information();
		$location       = $venue['name'];
		$description    = $this->event->get_calendar_description();

		if ( ! empty( $venue['address'] ) ) {
			$location .= sprintf( ', %s', $venue['address'] );
		}

		$permalink   = (string) get_permalink( $post );
		$summary     = $this->fold_ical_text( $this->escape_ical_text( $post->post_title ) );
		$description = $this->fold_ical_text( $this->escape_ical_text( $description ) );
		$location    = $this->fold_ical_text( $this->escape_ical_text( $location ) );

		$args = array(
			'BEGIN:VEVENT',
			sprintf( 'URL:%s', esc_url_raw( $permalink ) ),
			$dtstart_line,
			$dtend_line,
			sprintf( 'DTSTAMP:%s', sanitize_text_field( $datetime_stamp ) ),
			sprintf( 'LAST-MODIFIED:%s', sanitize_text_field( $last_modified ) ),
			sprintf( 'SEQUENCE:%d', $sequence ),
			sprintf( 'SUMMARY:%s', $summary ),
			sprintf( 'DESCRIPTION:%s', $description ),
			sprintf( 'LOCATION:%s', $location ),
			'UID:gatherpress_' . intval( $post->ID ),
			'END:VEVENT',
		);

		return implode( "\r\n", $args );
	}

	/**
	 * Revision number for this event's VEVENT, per RFC 5545 §3.8.7.4.
	 *
	 * Clients only treat an incoming VEVENT as a revision of one they already
	 * hold when its `SEQUENCE` is higher than the stored value. GatherPress
	 * emits a stable `UID`, so without a sequence an edited event keeps
	 * showing its original date in a subscribed calendar.
	 *
	 * The value is seconds since `SEQUENCE_EPOCH`, read from
	 * `post_modified_gmt`. That field only moves forward, so the sequence is
	 * strictly monotonic per event, which is the only thing the property
	 * requires. It emits around 2.1e8 today and reaches the RFC's INTEGER
	 * ceiling of 2147483647 in 2088.
	 *
	 * Neither obvious alternative works. The gap between creation and
	 * modification shrinks whenever `post_date_gmt` is corrected forward, and
	 * a sequence that moves backwards is one clients may ignore. A raw
	 * timestamp is monotonic but hits the same ceiling in January 2038; the
	 * epoch is what buys the other sixty years at the same resolution.
	 *
	 * The clamp guards against corrupt data, not ordinary growth. Saturating
	 * it would freeze the event in subscribers' calendars, since every later
	 * revision would repeat the ceiling and be ignored, so it should only ever
	 * catch something like an import writing a year-3000 date. Emitting an
	 * out-of-range INTEGER instead risks clients rejecting the whole VEVENT.
	 *
	 * @since 0.35.0
	 *
	 * @return int Non-negative revision number for this event.
	 */
	private function get_sequence(): int {
		$post = $this->event->post;

		if ( ! $post ) {
			return 0;
		}

		$modified = strtotime( $post->post_modified_gmt );

		if ( false === $modified ) {
			return 0;
		}

		// Floor at zero for anything modified before the epoch; clamp at the
		// RFC ceiling for dates far enough out to be data corruption.
		return min( max( 0, $modified - self::SEQUENCE_EPOCH ), 2147483647 );
	}

	/**
	 * Escape iCal text per RFC 5545 §3.3.11.
	 *
	 * Backslashes, commas, semicolons, and newlines have semantic meaning in
	 * TEXT-typed properties (SUMMARY / DESCRIPTION / LOCATION). Backslash
	 * escapes the next character; an unescaped comma separates list values; a
	 * raw semicolon separates parameters; a literal newline breaks the
	 * property record. Calendar clients that strictly conform to the spec
	 * truncate or split values on unescaped occurrences — most user-visible
	 * venue addresses contain at least a comma. Escape before folding so the
	 * fold doesn't split inside an escape sequence.
	 *
	 * @since 0.34.0
	 *
	 * @param string $text The raw text to escape.
	 *
	 * @return string The escaped text suitable for a TEXT-typed iCal property.
	 */
	private function escape_ical_text( string $text ): string {
		return addcslashes( $text, "\\,;\r\n" );
	}

	/**
	 * Build a sanitized endpoint URL for this event with the given slug.
	 *
	 * Inspired by `get_post_embed_url()`. Falls back to a query-string variant
	 * when permalinks are off or a path conflict exists.
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_post_embed_url/
	 *
	 * @since 0.34.0
	 *
	 * @param string      $endpoint_slug The visible suffix appended to the post permalink.
	 * @param string|null $query_var     Optional query var; falls back to `Setup::QUERY_VAR`.
	 *
	 * @return string|false              URL of the event's endpoint, or false when the post can't be resolved.
	 */
	protected function get_endpoint_url( string $endpoint_slug, ?string $query_var = null ): string|false {
		$post = $this->event->post;

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

		$post_url = get_permalink( $post );

		// `get_permalink()` returns a query-string permalink either when the
		// site has no permalink structure at all, or when the rewrite rules
		// haven't been (re)generated for this post type yet — concatenating a
		// slug onto that produces `/?gatherpress_event=foo/ical/`, which is
		// malformed. Treat the presence of `?` in the post URL as the signal
		// to use the query-arg fallback rather than reading the option, since
		// the option can be set while the rewrite rules are still stale.
		if ( str_contains( $post_url, '?' ) ) {
			$endpoint_url = add_query_arg( array( $query_var => $endpoint_slug ), $post_url );
		} else {
			$endpoint_url  = trailingslashit( $post_url ) . user_trailingslashit( $endpoint_slug );
			$path_conflict = get_page_by_path(
				str_replace( home_url(), '', $endpoint_url ),
				OBJECT,
				get_post_types( array( 'public' => true ) )
			);

			if ( $path_conflict ) {
				// Defensive fallback when a real public post collides with the
				// computed endpoint path. Reachable only when a site builder
				// has a page at `event/{slug}/ical` (or similar) — hard to
				// drive through `get_page_by_path()`'s normalization rules in
				// a unit test without leaking the conflict into other tests.
				// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation.
				// @codeCoverageIgnoreStart
				$endpoint_url = add_query_arg( array( $query_var => $endpoint_slug ), $post_url );
				// @codeCoverageIgnoreEnd
			}
		}

		/**
		 * Filters the calendar URL for a single event.
		 *
		 * Lets integrators rewrite the calendar URL (iCal / Outlook download,
		 * Google / Yahoo redirect) for an event before it reaches the front
		 * end — useful for routing calendar downloads through a CDN, swapping
		 * the host for a federation-friendly canonical, or appending tracking
		 * params.
		 *
		 * @since 0.34.0
		 *
		 * @param string   $endpoint_url The full calendar URL.
		 * @param \WP_Post $post         The corresponding event post.
		 *
		 * @return string                The filtered calendar URL.
		 */
		$endpoint_url = sanitize_url(
			apply_filters(
				'gatherpress_calendar_url',
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
	 * @since 0.34.0
	 *
	 * @param string $text The string to be escaped.
	 *
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
