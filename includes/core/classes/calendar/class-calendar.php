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

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rule;
use GatherPress\Core\Event\Recurrence\Series;
use GatherPress\Core\Event\Recurrence\Timezone_Guard;
use GatherPress\Core\Utility;
use WP_Post;
use WP_Term;

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
	 * measured from an epoch rather than being a raw timestamp. Aliased from
	 * `Revision` so the serializer and the primitive that advances it cannot
	 * drift apart.
	 *
	 * @since 0.35.0
	 * @var int
	 */
	private const SEQUENCE_EPOCH = Revision::EPOCH;

	/**
	 * Largest number of octets a content line may carry, excluding its break.
	 *
	 * RFC 5545 section 3.1. See `fold_content_line()`.
	 *
	 * @since 0.36.0
	 *
	 * @var int
	 */
	private const MAX_LINE_OCTETS = 75;

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
	 * event's title, start and end time, location, and description.
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
			// `dur=allday` is the only thing that switches Yahoo's all-day
			// toggle on, and it is ignored the moment `et` is present, so a
			// span cannot be flagged all day at all. A single day sends the
			// flag and no end. A span goes out as the timed stretch it is
			// stored as, midnight to 23:59:59 in the event's own zone: that
			// opens showing the first and last days and covers them all, which
			// a bare end date does not, since the composer reads it as
			// midnight and drops the last day. (Verified in the composer.)
			if ( $this->event->is_same_date() ) {
				$timing = array(
					'st'  => $this->event->get_formatted_datetime( 'Ymd', 'start' ),
					'dur' => 'allday',
				);
			} else {
				$timing = array(
					'st' => $this->event->get_formatted_datetime( 'Ymd\THis', 'start' ),
					'et' => $this->event->get_formatted_datetime( 'Ymd\THis', 'end' ),
				);
			}
		} else {
			// Yahoo has no timezone parameter and does not honor a trailing
			// Z: whatever it is handed is shown verbatim as local wall-clock
			// time. So the event's own local time goes out, right for anyone
			// in its zone and the closest available for anyone else. The end
			// is sent rather than a duration: `dur` is an `hhmm` field that
			// tops out at 99 hours, and Yahoo ignores it once `et` is present.
			$timing = array(
				'st' => $this->event->get_formatted_datetime( 'Ymd\THis', 'start' ),
				'et' => $this->event->get_formatted_datetime( 'Ymd\THis', 'end' ),
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
	 * @return string The VEVENT block, or an empty string when the wrapped
	 *                post is not a resolvable event.
	 *
	 * @throws Exception If reading event data fails.
	 */
	public function get_ical_event_string(): string {
		// Nothing to serialize when the wrapped post is not a resolvable
		// event. `Event::$event` is declared `?WP_Post` and stays null
		// whenever the ID does not resolve to a post that supports
		// `gatherpress-event-date`, such as a deleted post, a venue, or the 0
		// that `get_queried_object_id()` yields on an unresolved request. Every
		// line below reads through it, and reading `post_title` off null
		// evaluates to null in PHP 8, which reaches `escape_ical_text()`'s
		// `string` parameter and fatals the whole request on what is a public
		// endpoint. `Event::get_calendar_links()` and `get_endpoint_url()`
		// already bail on the same condition; this is the one caller that
		// dereferenced it unguarded.
		if ( ! $this->event->post instanceof WP_Post ) {
			return '';
		}

		$timezone       = $this->series_timezone();
		$occurrence     = $this->current_occurrence();
		$sequence       = $this->get_sequence();
		$datetime_stamp = $this->revision_stamp( $sequence );
		$last_modified  = $datetime_stamp;
		$venue          = $this->event->get_venue_information();
		$location       = $venue['name'];
		$description    = $this->event->get_calendar_description();

		if ( ! empty( $venue['address'] ) ) {
			$location .= sprintf( ', %s', $venue['address'] );
		}

		$summary     = $this->escape_ical_text( $this->event->post->post_title );
		$description = $this->escape_ical_text( $description );
		$location    = $this->escape_ical_text( $location );

		$args = array_merge(
			array(
				'BEGIN:VEVENT',
				sprintf( 'URL:%s', esc_url_raw( get_permalink( $this->event->post->ID ) ) ),
			),
			$this->datetime_lines( $timezone, $this->first_projected_occurrence( $timezone, $occurrence ) ),
			array(
				sprintf( 'DTSTAMP:%s', sanitize_text_field( $datetime_stamp ) ),
				sprintf( 'LAST-MODIFIED:%s', sanitize_text_field( $last_modified ) ),
				sprintf( 'SEQUENCE:%d', $sequence ),
				sprintf( 'SUMMARY:%s', $summary ),
				sprintf( 'DESCRIPTION:%s', $description ),
				sprintf( 'LOCATION:%s', $location ),
			),
			$this->recurrence_lines( $timezone, $occurrence ),
			array( sprintf( 'UID:%s', $this->uid() ) ),
			$this->related_lines(),
			array( 'END:VEVENT' )
		);

		return implode( "\r\n", array_map( array( $this, 'fold_content_line' ), $args ) );
	}

	/**
	 * The event's timezone, when it is one a `TZID` parameter may name.
	 *
	 * GatherPress accepts a fixed UTC offset (`UTC+5:30`) where a tz-database
	 * identifier belongs, and RFC 5545 has no way to express one as a `TZID`.
	 * An empty string is the signal that this component keeps the bare UTC form
	 * the plugin has always emitted.
	 *
	 * @since 0.36.0
	 *
	 * @return string A named tz-database identifier, or '' when the event has none.
	 */
	private function series_timezone(): string {
		$timezone = Utility::maybe_convert_utc_offset(
			(string) $this->event->get_datetime()['timezone']
		);

		return Timezone_Guard::is_named( $timezone ) ? $timezone : '';
	}

	/**
	 * The first occurrence a rule-bearing series actually projects.
	 *
	 * Null whenever `DTSTART` should stay on the anchor: for a component that
	 * carries no `RRULE` (a fixed-offset event emits none, and neither does an
	 * event with no rule), for a single occurrence's own component, which
	 * carries a `RECURRENCE-ID` instead, and for a series whose projection is
	 * empty.
	 *
	 * Read from the projection rather than re-derived from the rule, so the
	 * date the feed opens on is the same one the site lists. The read is
	 * `LIMIT 1` and is skipped entirely on a site with no recurring events.
	 *
	 * @since 0.36.0
	 *
	 * @param string     $timezone   A named tz-database identifier, or '' for none.
	 * @param array|null $occurrence The occurrence this component renders, if any.
	 *
	 * @return array|null The first scheduled occurrence row, or null.
	 */
	private function first_projected_occurrence( string $timezone, ?array $occurrence ): ?array {
		if ( '' === $timezone
			|| null !== $occurrence
			|| ! Recurrence_Query::site_has_recurring_events()
			|| null === Rule::from_post( $this->event->post->ID )
		) {
			return null;
		}

		// This post's own rows, not the resolved series. `exdate_line()` reads
		// series-wide because a split series still excludes every date it
		// canceled, whichever post holds the row. `DTSTART` is the opposite: it
		// has to agree with the `RRULE` emitted beside it, and that rule comes
		// from this post alone. Reading series-wide gave both fragments of a
		// split the same earliest date, so the two components opened on the
		// same instant and a subscriber merged duplicates.
		$rows = Occurrences::get_instance()->select_for_series(
			array( $this->event->post->ID ),
			array(
				'status' => Occurrences::STATUS_SCHEDULED,
				'limit'  => 1,
			)
		);

		return $rows[0] ?? null;
	}

	/**
	 * The `DTSTART` and `DTEND` properties for this component.
	 *
	 * A named timezone produces the `TZID`-qualified local wall clock RFC 5545
	 * requires before an `RRULE` may be attached; anything else keeps the bare
	 * UTC form, which is what a fixed-offset event has always emitted and is
	 * still correct for a component carrying no rule.
	 *
	 * When a rule is attached, these come from the first projected occurrence
	 * rather than from the series anchor. RFC 5545 section 3.8.5.3 makes
	 * `DTSTART` part of the recurrence set, and nothing couples the anchor's
	 * weekday to the rule's: a Wednesday anchor carrying `BYDAY=FR` is not an
	 * occurrence as far as `Expander::matches()` is concerned, so emitting it
	 * gave subscribers a date the site does not list and cannot cancel, since
	 * no row exists to cancel. Under `COUNT`, which is what the fixtures use,
	 * it also drops a real date off the end, because a client counts `DTSTART`
	 * as the first instance.
	 *
	 * @since 0.36.0
	 *
	 * @param string     $timezone A named tz-database identifier, or '' for none.
	 * @param array|null $first    First projected occurrence row, or null to use
	 *                             the series anchor.
	 *
	 * @return string[] The two property lines, in order.
	 */
	private function datetime_lines( string $timezone, ?array $first = null ): array {
		if ( null !== $first ) {
			$zone  = new DateTimeZone( $timezone );
			$utc   = new DateTimeZone( 'UTC' );
			$start = new DateTimeImmutable( (string) $first['datetime_start_gmt'], $utc );
			$end   = new DateTimeImmutable( (string) $first['datetime_end_gmt'], $utc );

			return array(
				sprintf(
					'DTSTART;TZID=%s:%s',
					$timezone,
					$start->setTimezone( $zone )->format( 'Ymd\THis' )
				),
				sprintf(
					'DTEND;TZID=%s:%s',
					$timezone,
					$end->setTimezone( $zone )->format( 'Ymd\THis' )
				),
			);
		}

		if ( '' === $timezone ) {
			return array(
				sprintf(
					'DTSTART:%sT%sZ',
					$this->event->get_formatted_datetime( 'Ymd', 'start', false ),
					$this->event->get_formatted_datetime( 'His', 'start', false )
				),
				sprintf(
					'DTEND:%sT%sZ',
					$this->event->get_formatted_datetime( 'Ymd', 'end', false ),
					$this->event->get_formatted_datetime( 'His', 'end', false )
				),
			);
		}

		return array(
			sprintf(
				'DTSTART;TZID=%s:%sT%s',
				$timezone,
				$this->event->get_formatted_datetime( 'Ymd', 'start', true ),
				$this->event->get_formatted_datetime( 'His', 'start', true )
			),
			sprintf(
				'DTEND;TZID=%s:%sT%s',
				$timezone,
				$this->event->get_formatted_datetime( 'Ymd', 'end', true ),
				$this->event->get_formatted_datetime( 'His', 'end', true )
			),
		);
	}

	/**
	 * The occurrence this component describes, when the request named one.
	 *
	 * Identity is compared by post ID rather than taken on trust, mirroring
	 * `Context::resolve()`: one response can render several posts, and only the
	 * one the occurrence belongs to may claim it.
	 *
	 * @since 0.36.0
	 *
	 * @return array|null The occurrence row, or null for a series component.
	 */
	private function current_occurrence(): ?array {
		$occurrence = Context::get_instance()->current();

		if ( null === $occurrence
			|| (int) $occurrence['series_post_id'] !== (int) $this->event->post->ID
		) {
			return null;
		}

		return $occurrence;
	}

	/**
	 * The recurrence properties for this component, if it has any.
	 *
	 * Two shapes. A component describing one named occurrence
	 * carries a `RECURRENCE-ID` referring back to the series and no rule of its
	 * own. A component describing the series carries the rule and the
	 * exclusions **derived** from its canceled occurrence rows. The stored
	 * rule is never mutated to express a cancellation.
	 *
	 * Neither shape is emitted without a named timezone: an `RRULE` cannot be
	 * correctly attached to a UTC-anchored start for anything but a
	 * fixed-offset series, and a `RECURRENCE-ID` must match `DTSTART`'s value
	 * type. Every recurring event is kept on a named timezone, so this arm is
	 * a guard rather than an authored case.
	 *
	 * @since 0.36.0
	 *
	 * @param string     $timezone   A named tz-database identifier, or '' for none.
	 * @param array|null $occurrence The occurrence this component describes, or null.
	 *
	 * @return string[] The recurrence property lines, possibly empty.
	 *
	 * @throws Exception If reading the event's datetime fails.
	 */
	private function recurrence_lines( string $timezone, ?array $occurrence ): array {
		if ( '' === $timezone ) {
			return array();
		}

		if ( null !== $occurrence ) {
			return array(
				sprintf( 'RECURRENCE-ID;TZID=%s:%s', $timezone, $occurrence['recurrence_id'] ),
			);
		}

		// A site with no recurring events reads neither the rule
		// mirrors nor the occurrence table on any calendar request, so the flag
		// short-circuits the lookup rather than filtering its result. An event
		// with no rule of its own then contributes nothing either, however many
		// other events on the site do.
		$rule  = Recurrence_Query::site_has_recurring_events()
			? Rule::from_post( $this->event->post->ID )
			: null;
		$lines = array();

		if ( null !== $rule ) {
			$zone    = new DateTimeZone( $timezone );
			$lines[] = sprintf(
				'RRULE:%s',
				$rule->to_rrule_string(
					new DateTimeImmutable(
						$this->event->get_formatted_datetime( 'Y-m-d H:i:s', 'start', true ),
						$zone
					),
					$zone
				)
			);
			$exdate  = $this->exdate_line( $timezone );

			if ( '' !== $exdate ) {
				$lines[] = $exdate;
			}
		}

		return $lines;
	}

	/**
	 * The `EXDATE` property derived from this series' canceled occurrences.
	 *
	 * Read through `Series::resolve_post_ids()`, so a series a forward split
	 * has spread across several posts still excludes every date it canceled.
	 *
	 * @since 0.36.0
	 *
	 * @param string $timezone A named tz-database identifier.
	 *
	 * @return string The `EXDATE` line, or '' when nothing is canceled.
	 */
	private function exdate_line( string $timezone ): string {
		$rows = Occurrences::get_instance()->select_for_series(
			Series::get_instance()->resolve_post_ids( $this->event->post->ID ),
			array( 'status' => Occurrences::STATUS_CANCELED )
		);

		if ( array() === $rows ) {
			return '';
		}

		return sprintf(
			'EXDATE;TZID=%s:%s',
			$timezone,
			implode( ',', array_column( $rows, 'recurrence_id' ) )
		);
	}

	/**
	 * The unique identifier for this component.
	 *
	 * One identifier for the whole recurrence set, occurrences included. RFC
	 * 5545 section 3.8.4.4 is explicit that the `UID` references the *entire*
	 * recurrence set and that `RECURRENCE-ID` is what identifies one instance
	 * within it, so a single-occurrence download is an override of that
	 * instance rather than a component of its own. Minting a per-occurrence
	 * identifier instead gives a client no way to correlate the download with
	 * the series it belongs to, and it shows up as a second, duplicate event
	 * sitting on top of the one the rule already produced.
	 *
	 * The identity that distinguishes two occurrences of one series is
	 * therefore the `(UID, RECURRENCE-ID)` tuple, whose second half
	 * `recurrence_lines()` emits.
	 *
	 * @since 0.36.0
	 *
	 * @return string The `UID` value.
	 */
	private function uid(): string {
		return 'gatherpress_' . intval( $this->event->post->ID );
	}

	/**
	 * The `RELATED-TO` property tying a split fragment back to its origin.
	 *
	 * A forward split gives the dates it moves a new `UID`, because they now
	 * belong to a different post with its own rule, its own edits and its own
	 * RSVPs. RFC 5545 section 3.8.4.5 is how the two components say they are one
	 * thing: the fragment points at the `UID` the subscription was first taken
	 * out against, with the default `RELTYPE=PARENT`. A client that understands
	 * the property groups the fragments; a client that does not ignores an
	 * unknown property, which is why this is additive rather than a change to
	 * `UID`.
	 *
	 * Emitted only by a fragment. The origin carries no pointer, because it is
	 * the identifier everything else points at, and a self-reference would make
	 * the relationship a cycle rather than a tree.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The property line, or an empty array for an unsplit series.
	 */
	private function related_lines(): array {
		$post_id = (int) $this->event->post->ID;
		$origin  = $this->series_origin_post_id( $post_id );

		if ( 0 === $origin || $origin === $post_id ) {
			return array();
		}

		return array( sprintf( 'RELATED-TO:gatherpress_%d', $origin ) );
	}

	/**
	 * The post a logical series started on.
	 *
	 * The series term's slug names it (`Series::create_term_for()`), which is
	 * durable across further splits: a second split of either fragment joins the
	 * same term rather than minting a new one, so every fragment resolves to the
	 * one post a subscriber's `UID` was first issued for.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Any post of the series.
	 *
	 * @return int The origin post ID, or 0 when this post belongs to no series term.
	 */
	private function series_origin_post_id( int $post_id ): int {
		// A site with neither recurring events nor a split series reads
		// neither the taxonomy nor the term cache on a calendar request. The
		// split flag keeps a fully demoted split's fragments pointing at the
		// origin's UID after the recurring flag has returned to '0'.
		if ( ! ( Recurrence_Query::site_has_recurring_events() || Series::site_has_split_series() ) ) {
			return 0;
		}

		$series  = Series::get_instance();
		$term_id = $series->term_id_for_post( $post_id );

		if ( 0 === $term_id ) {
			return 0;
		}

		$term    = get_term( $term_id, Series::TAXONOMY );
		$slug    = ( $term instanceof WP_Term ) ? $term->slug : '';
		$matches = array();
		$named   = 1 === preg_match( '/^series-(\d+)$/', $slug, $matches ) ? (int) $matches[1] : 0;
		$members = $series->resolve_post_ids( $post_id );

		// The slug is only believed when it names a post that is still in the
		// series: a renamed term, or one whose origin has been deleted, would
		// otherwise point subscribers at a `UID` no component in the body
		// carries. The lowest member is the fallback, since a split only ever
		// creates posts after the one it splits.
		return in_array( $named, $members, true ) ? $named : min( $members );
	}

	/**
	 * The GMT stamp reporting when this series' calendar content last changed.
	 *
	 * `DTSTAMP` and `LAST-MODIFIED` are derived from the same revision
	 * `SEQUENCE` reports rather than from `post_modified_gmt` directly, so the
	 * three properties cannot disagree about whether a component is newer than
	 * the one a subscriber holds. They did disagree: a forward split caps the
	 * origin's `RRULE` with a bare `postmeta` write, which advances the revision
	 * and leaves the post row alone, so the capped component announced a shorter
	 * rule under an unchanged modification time. The revision floors at
	 * `post_modified_gmt`, so for an event nothing has ever advanced this is the
	 * same string it has always emitted.
	 *
	 * @since 0.36.0
	 *
	 * @param int $sequence This component's revision, in seconds from the epoch.
	 *
	 * @return string The stamp in RFC 5545 UTC form.
	 */
	private function revision_stamp( int $sequence ): string {
		$timestamp = self::SEQUENCE_EPOCH + $sequence;

		return sprintf( '%sT%sZ', gmdate( 'Ymd', $timestamp ), gmdate( 'His', $timestamp ) );
	}

	/**
	 * Revision number for this event's VEVENT, per RFC 5545 §3.8.7.4.
	 *
	 * Clients only treat an incoming VEVENT as a revision of one they already
	 * hold when its `SEQUENCE` is higher than the stored value. GatherPress
	 * emits a stable `UID`, so without a sequence an edited event keeps
	 * showing its original date in a subscribed calendar.
	 *
	 * The floor is seconds since `SEQUENCE_EPOCH`, read from
	 * `post_modified_gmt`. That field never moves backwards, so the floor is
	 * non-decreasing per event, which is what the property requires. It emits
	 * around 2.1e8 today and reaches the RFC's INTEGER ceiling of 2147483647
	 * in 2088.
	 *
	 * The epoch offset is what buys the headroom: a raw Unix timestamp would
	 * hit the same ceiling in January 2038 instead.
	 *
	 * That field alone is not sufficient, though, which is what `Revision`
	 * exists for. Occurrence rows, rule mirrors and a forward split are all
	 * written by statements that leave `post_modified_gmt` untouched, and its
	 * one-second resolution cannot separate two changes that land in the same
	 * second either. The stored revision is the greater of the two whenever
	 * something has advanced it, so a change the post row never saw still
	 * reaches subscribers.
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
		$modified = strtotime( (string) $this->event->post->post_modified_gmt );
		// Floor at zero for anything modified before the epoch; clamp at the
		// RFC ceiling for dates far enough out to be data corruption.
		$from_post = ( false === $modified ) ? 0 : max( 0, $modified - self::SEQUENCE_EPOCH );

		return min(
			max( $from_post, Revision::get_instance()->stored( (int) $this->event->post->ID ) ),
			Revision::CEILING
		);
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

		// Read the *series* permalink rather than the filtered one. The
		// endpoint URL is a path segment appended to the post's permalink, and
		// `Context::permalink()` answers with the occurrence's URL whenever one
		// is in play, both during a loop iteration and on an occurrence's own
		// page. Appending `ical/` to that produces
		// `/event/my-series/20260903T180000/ical/`, which matches no rewrite
		// rule and 404s (measured). The endpoint serves the series today;
		// giving each occurrence its own export is a separate piece of work,
		// and when it lands it belongs in the rewrite rule rather than in a
		// concatenation here.
		$post_url = (string) Context::get_instance()->series_permalink( $post->ID );

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
	 * Fold one assembled content line per RFC 5545 section 3.1.
	 *
	 * "Lines of text SHOULD NOT be longer than 75 octets, excluding the line
	 * break", and a longer one is split by inserting a CRLF followed by a single
	 * space, which a parser removes again when it reads the value. Octets, not
	 * characters: the limit is a byte budget, so a multi-byte character counts
	 * for as many bytes as it occupies, and must never be split across a fold,
	 * which would leave two invalid byte sequences that do not reassemble.
	 *
	 * Applied to the whole property line rather than to a value, because the
	 * limit is a property of the line: `EXDATE;TZID=America/New_York:` is 29
	 * octets of it before the first identifier, and an exclusion list grows
	 * without bound as a series accumulates cancellations. Folding the value
	 * alone measures the wrong string and emits over-length lines a strict
	 * parser may reject or truncate, which puts canceled dates back on the
	 * subscriber's calendar.
	 *
	 * @since 0.34.0
	 *
	 * @since 0.36.0 Folds a whole content line on an octet budget, replacing a
	 *               character-counted helper applied to selected values.
	 *
	 * PHPMD reads this as an unused private method. It is called once, as
	 * `array_map( array( $this, 'fold_content_line' ), $args )` in the line
	 * assembler above, and PHPMD does not resolve a method named inside a
	 * callable array.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 *
	 * @param string $line One complete content line, without its line break.
	 *
	 * @return string The line, folded where it exceeded the limit.
	 */
	private function fold_content_line( string $line ): string {
		if ( self::MAX_LINE_OCTETS >= strlen( $line ) ) {
			return $line;
		}

		$folded  = array();
		$current = '';
		// The first physical line spends its whole budget on content; every
		// continuation spends one octet of it on the leading space.
		$budget = self::MAX_LINE_OCTETS;

		foreach ( mb_str_split( $line ) as $character ) {
			if ( strlen( $current ) + strlen( $character ) > $budget ) {
				$folded[] = $current;
				$current  = '';
				$budget   = self::MAX_LINE_OCTETS - 1;
			}

			$current .= $character;
		}

		$folded[] = $current;

		return implode( "\r\n ", $folded );
	}
}
