<?php
/**
 * Class responsible for representing and managing event instances.
 *
 * The Event class is responsible for creating and managing instances of events within the GatherPress plugin.
 * It provides methods for working with event data, such as retrieving event details and managing RSVPs.
 *
 * @package GatherPress\Core\Event
 * @since 0.27.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeZone;
use Exception;
use GatherPress\Core\Calendar;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Core\Settings;
use GatherPress\Core\Utility;
use GatherPress\Core\Validate;
use GatherPress\Core\Venue\Setup;
use GatherPress\Core\Venue;
use WP_Post;
use WP_Term;

/**
 * Class Event.
 *
 * Represents individual events within the GatherPress plugin and provides event-related functionality.
 *
 * @since 0.34.0
 *
 * @phpstan-type EventDatetime array{
 *     datetime_start: string,
 *     datetime_start_gmt: string,
 *     datetime_end: string,
 *     datetime_end_gmt: string,
 *     timezone: string
 * }
 */
class Event {

	/**
	 * Cache key format for storing and retrieving event datetimes.
	 *
	 * @since 0.34.0
	 * @var string $DATETIME_CACHE_KEY
	 */
	const DATETIME_CACHE_KEY = 'datetime_%d';

	/**
	 * Date and time format used within GatherPress.
	 *
	 * @since 0.34.0
	 * @var string $DATETIME_FORMAT
	 */
	const DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * The post type name for GatherPress events.
	 *
	 * @since 0.34.0
	 * @var string $POST_TYPE
	 */
	const POST_TYPE = 'gatherpress_event';

	/**
	 * Capability for reading a specific event.
	 *
	 * A meta capability, so it is always paired with the event's post ID and
	 * resolves through WordPress to the right primitive for the event's status.
	 *
	 * @since 0.35.1
	 * @var string
	 */
	const READ_CAPABILITY = 'read_post';

	/**
	 * Capability for editing a specific event.
	 *
	 * A meta capability, so it is always paired with the event's post ID.
	 *
	 * @since 0.35.1
	 * @var string
	 */
	const EDIT_CAPABILITY = 'edit_post';

	/**
	 * Placeholder displayed when no datetime is set.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const DATETIME_PLACEHOLDER = '—';

	/**
	 * Format for the database table name used by GatherPress events.
	 *
	 * @since 0.34.0
	 * @var string $TABLE_FORMAT
	 */
	const TABLE_FORMAT = '%sgatherpress_events';

	/**
	 * Pattern slug for the event template — the anchor that the
	 * `core/paragraph` hooked block (and others) attach to. Companion
	 * plugins that hook blocks into events read this constant rather
	 * than hard-coding the slug.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const TEMPLATE_PATTERN = 'gatherpress/event-template';



	/**
	 * The event post.
	 *
	 * @since 0.34.0
	 * @var WP_Post|null
	 */
	public ?WP_Post $post = null;

	/**
	 * Cached datetime data.
	 *
	 * @since 0.34.0
	 *
	 * @var EventDatetime|null
	 */
	private ?array $datetime_cache = null;

	/**
	 * Event constructor.
	 *
	 * Initializes an Event object for a specific event post.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id The event post ID.
	 */
	public function __construct( int $post_id ) {
		if ( post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ) {
			$this->post = get_post( $post_id );
		}
	}

	/**
	 * Whether an event's blocks should render for the current viewer.
	 *
	 * Blocks stay off an event nobody is meant to see yet, but an unpublished
	 * event still renders for viewers allowed to read it, so an organizer
	 * working on a draft sees the same blocks the published event will show
	 * rather than empty space, as does the editor previewing that event.
	 *
	 * @since 0.35.1
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return bool True when the event's blocks should render.
	 */
	public static function is_viewable( int $post_id ): bool {
		// is_preview() is a property of the request, not of a post, so it only
		// stands in for read access on the post actually being previewed.
		return (
			( is_preview() && (int) get_queried_object_id() === $post_id )
			|| 'publish' === get_post_status( $post_id )
			|| current_user_can( self::READ_CAPABILITY, $post_id )
		);
	}

	/**
	 * Whether the current viewer may read an event's RSVP responses.
	 *
	 * The roster follows the event: a published event's responses are public
	 * once any password gate is satisfied, anything else is limited to viewers
	 * allowed to read that event, and whoever can edit it always sees them.
	 *
	 * @since 0.35.1
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return bool True when the viewer may read the event's RSVP responses.
	 */
	public static function can_read_rsvps( int $post_id ): bool {
		$post = get_post( $post_id );

		// A post that is gone, or one that never takes RSVPs, has no roster.
		if ( ! $post instanceof WP_Post || ! post_type_supports( $post->post_type, 'gatherpress-rsvp' ) ) {
			return false;
		}

		if ( current_user_can( self::EDIT_CAPABILITY, $post->ID ) ) {
			return true;
		}

		return 'publish' === $post->post_status
			? ! post_password_required( $post )
			: current_user_can( self::READ_CAPABILITY, $post->ID );
	}

	/**
	 * Retrieves and formats the event's date and time for display.
	 *
	 * This method generates a formatted string that represents the event's start and end dates and times.
	 * It also considers whether the event's start and end occur on the same day to adjust the format accordingly.
	 * Additionally, it can append the timezone to the formatted string based on settings.
	 *
	 * @since 0.34.0
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
		$parts = array_filter(
			$this->get_display_datetime_parts(
				$type,
				$start_format,
				$end_format,
				$separator,
				$show_timezone
			)
		);

		return $parts ? implode( ' ', $parts ) : self::DATETIME_PLACEHOLDER;
	}

	/**
	 * Gets raw formatted datetime parts for display.
	 *
	 * @since 0.34.0
	 *
	 * @param string $type          Display type: 'start', 'end', or 'both'.
	 * @param string $start_format  PHP display format for start date/time.
	 * @param string $end_format    PHP display format for end date/time.
	 * @param string $separator     Separator between start and end dates.
	 * @param string $show_timezone Show timezone.
	 *
	 * @return array<string, string|false> Raw datetime parts.
	 *
	 * @throws Exception If date/time formatting fails or settings cannot be retrieved.
	 */
	public function get_display_datetime_parts(
		string $type = '',
		string $start_format = '',
		string $end_format = '',
		string $separator = '',
		string $show_timezone = ''
	): array {
		$settings    = Settings::get_instance();
		$date_format = apply_filters( 'gatherpress_date_format', $settings->get( 'date_format' ) );
		$time_format = apply_filters( 'gatherpress_time_format', $settings->get( 'time_format' ) );
		$timezone    = $settings->get( 'show_timezone' ) ? ' T' : '';
		$show_start  = $type ? in_array( $type, array( 'start', 'both' ), true ) : true;
		$show_end    = $type ? in_array( $type, array( 'end', 'both' ), true ) : true;

		$formats = $this->get_display_formats(
			$start_format,
			$end_format,
			$date_format,
			$time_format
		);

		$start = $show_start ? $this->get_datetime_start( $formats['start'] ) : false;
		$end   = $show_end
			? $this->get_display_end( $formats, $show_start && $this->is_same_date() )
			: false;

		// Add separator if there's both start and end date/time.
		$default_separator = $separator ? $separator : __( 'to', 'gatherpress' );
		$separator         = $start && $end ? $default_separator : false;

		// Add timezone, event first. A block in a site template renders every
		// event and cannot know which of them want their zone named, so an
		// event that says either way is answered before the block is asked.
		// Saying nothing leaves it to the block, whatever kind of event it is.
		$preference = $this->get_timezone_preference();

		if ( 'never' === $preference ) {
			$timezone = false;
		} elseif ( 'always' === $preference || ( $show_timezone ? 'yes' === $show_timezone : $timezone ) ) {
			$timezone = $this->get_datetime_start( $timezone ? $timezone : ' T' );
		} else {
			$timezone = false;
		}

		return array(
			'start'     => $start,
			'separator' => $separator,
			'end'       => $end,
			'timezone'  => $timezone,
		);
	}

	/**
	 * Check whether the start and end datetimes fall on the same date.
	 *
	 * Compares the date portion of the unfiltered ISO datetimes, which bypass
	 * the `gatherpress_datetime_format` filter, so a display-format filter
	 * that ignores its $format argument cannot make a same-day event compare
	 * unequal.
	 *
	 * @since 0.34.0
	 *
	 * @return bool True if start and end are on the same date, false otherwise.
	 */
	public function is_same_date(): bool {
		$datetime_start = $this->get_datetime_start_iso();
		$datetime_end   = $this->get_datetime_end_iso();

		if ( empty( $datetime_start ) || empty( $datetime_end ) ) {
			return false;
		}

		return substr( $datetime_start, 0, 10 ) === substr( $datetime_end, 0, 10 );
	}

	/**
	 * Check if the event has yet to occur (in the future).
	 *
	 * This method compares the start datetime of the event with the current time
	 * to determine if the event has yet to take place.
	 *
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @param string $format Optional. PHP date format. Default is 'D, M j, Y, g:i a T'.
	 *
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
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @param string $format PHP DateTime format (defaults to g:ia).
	 *
	 * @return string The formatting end time of the event.
	 */
	public function get_time_end( string $format = '' ): string {
		return $this->get_datetime_end(
			Utility::remove_non_time_format_chars( $format ? $format : 'g:i a' )
		);
	}

	/**
	 * Convert a datetime to the one format everything downstream reads.
	 *
	 * `get_gmt_datetime()` accepts anything `date_create()` understands, so a
	 * caller is not limited to `self::DATETIME_FORMAT`. `get_datetime()` is:
	 * it validates what it reads back and discards a value in any other
	 * shape, so a datetime written as `2026-08-29T09:00:00` would be stored
	 * and then silently lost.
	 *
	 * @since 0.36.0
	 *
	 * @param string       $datetime Any datetime `date_create()` understands.
	 * @param DateTimeZone $timezone The zone to read the datetime in.
	 *
	 * @return string The datetime in `self::DATETIME_FORMAT`, or an empty
	 *                string when it cannot be read as one.
	 */
	protected function normalize_datetime( string $datetime, DateTimeZone $timezone ): string {
		// An empty string parses to the current time rather than failing, so
		// nothing would become now.
		if ( '' === trim( $datetime ) ) {
			return '';
		}

		$parsed = date_create( $datetime, $timezone );

		// A MySQL zero date parses to a negative year instead of failing.
		if ( false === $parsed || 1 > (int) $parsed->format( 'Y' ) ) {
			return '';
		}

		// A datetime carrying its own offset is read in that offset, so it is
		// moved into the event's zone before being stored as its local time.
		// Otherwise the local column and the GMT one derived from it would
		// describe different moments.
		return $parsed->setTimezone( $timezone )->format( self::DATETIME_FORMAT );
	}

	/**
	 * Snap a datetime to the beginning or the end of its own day.
	 *
	 * An all-day event stores a span that really covers the day rather than
	 * hiding a time that is still 3pm underneath, so exports, duration and
	 * date queries stay correct.
	 *
	 * @since 0.36.0
	 *
	 * Finds the date rather than assuming where it sits, so the method holds
	 * on its own instead of depending on having been handed something
	 * `normalize_datetime()` had already been through.
	 *
	 * @param string $datetime Any datetime `date_create()` understands.
	 * @param string $which    Which boundary, 'start' or 'end'.
	 *
	 * @return string The snapped datetime, or an empty string when there is
	 *                no date to snap.
	 */
	protected static function to_day_boundary( string $datetime, string $which ): string {
		// An empty string parses to the current time rather than failing.
		$parsed = '' === trim( $datetime ) ? false : date_create( $datetime );

		if ( false === $parsed ) {
			return '';
		}

		return sprintf(
			'%s %s',
			$parsed->format( 'Y-m-d' ),
			'start' === $which ? '00:00:00' : '23:59:59'
		);
	}

	/**
	 * Format an all-day event's datetime.
	 *
	 * An all-day event carries no timezone, the way a calendar's date value
	 * does: August 29 is August 29 in Tokyo and in Los Angeles. Converting the
	 * stored GMT would land the day before or after depending on which side of
	 * the meridian the event sits, so the local column is read and both parsed
	 * and rendered in the event's own zone. The date therefore never moves,
	 * and a format asking for the zone still names the one the day belongs to
	 * rather than GMT.
	 *
	 * The GMT columns stay a real instant, because ordering upcoming against
	 * past genuinely wants one.
	 *
	 * @since 0.36.0
	 *
	 * @param string               $format The PHP date format.
	 * @param string               $which  Which datetime to format, 'start' or 'end'.
	 * @param bool                 $local        Whether the datetime is being rendered in local time.
	 * @param array<string, mixed> $dt           The event's datetime data.
	 * @param bool                 $apply_filter Whether to pass $format through the gatherpress_datetime_format filter.
	 *
	 * @return string The formatted datetime.
	 */
	protected function get_formatted_all_day(
		string $format,
		string $which,
		bool $local,
		array $dt,
		bool $apply_filter
	): string {
		$date = (string) $dt[ sprintf( 'datetime_%s', $which ) ];

		if ( empty( $date ) ) {
			return '';
		}

		$zone = in_array( $dt['timezone'], Utility::list_timezone_and_utc_offsets(), true )
			? Utility::normalize_timezone_string( (string) $dt['timezone'] )
			: 'GMT+0000';
		$tz   = new DateTimeZone( $zone );

		// `Validate::datetime()` accepts what `DateTime::createFromFormat()`
		// accepts, which is wider than this: an overflowing value like
		// '2030-06-31 25:00:00' is stored and read back, and constructing a
		// date from it throws. Report no datetime rather than dying, matching
		// what the timed path does with the same value.
		$parsed = date_create( $date, $tz );

		if ( false === $parsed ) {
			return '';
		}

		if ( $apply_filter ) {
			/** This filter is documented in includes/core/classes/event/class-event.php */
			$format = apply_filters( 'gatherpress_datetime_format', $format, $which, $local );
		}

		return trim( (string) wp_date( $format, $parsed->getTimestamp(), $tz ) );
	}

	/**
	 * What this event says about showing its timezone.
	 *
	 * Overrides the block and the site setting, because a block in a site
	 * template renders every event and cannot answer this per event.
	 *
	 * @since 0.36.0
	 *
	 * @return string 'always', 'never', or an empty string to leave it to the
	 *                block and the site setting.
	 */
	public function get_timezone_preference(): string {
		if ( ! $this->post ) {
			return '';
		}

		$preference = (string) get_post_meta( $this->post->ID, 'gatherpress_show_timezone', true );

		return in_array( $preference, array( 'always', 'never' ), true ) ? $preference : '';
	}

	/**
	 * Whether this event runs for whole days rather than at a time.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the event is all day.
	 */
	public function is_all_day(): bool {
		if ( ! $this->post ) {
			return false;
		}

		return (bool) get_post_meta( $this->post->ID, 'gatherpress_is_all_day', true );
	}

	/**
	 * Resolve the formats one rendered datetime range is built from.
	 *
	 * The site keeps its date and time formats separately, so an all-day
	 * event simply uses the date one and never reaches for the time. A
	 * format set explicitly on the block keeps its date and loses its time,
	 * since wanting a time means the event is not all day.
	 *
	 * @since 0.36.0
	 *
	 * @param string $start_format Explicit start format, or an empty string.
	 * @param string $end_format   Explicit end format, or an empty string.
	 * @param string $date_format  The site's date format.
	 * @param string $time_format  The site's time format.
	 *
	 * @return array{start: string, end: string, end_time: string} The formats to render with.
	 */
	protected function get_display_formats(
		string $start_format,
		string $end_format,
		string $date_format,
		string $time_format
	): array {
		if ( $this->is_all_day() ) {
			// Wanting a time on the face of it means the event is not all
			// day, so a format saved on the block loses its time rather than
			// printing the day's boundary as though someone chose it.
			$start = Utility::remove_time_format_chars( $start_format );
			$end   = Utility::remove_time_format_chars( $end_format );

			return array(
				'start'    => $start ? $start : $date_format,
				'end'      => $end ? $end : $date_format,
				// Nothing follows the start date of a one-day event: it has
				// no end time, and its end date has already been said.
				'end_time' => '',
			);
		}

		return array(
			'start'    => $start_format ? $start_format : "{$date_format} {$time_format}",
			'end'      => $end_format ? $end_format : "{$date_format} {$time_format}",
			'end_time' => $end_format ? $end_format : $time_format,
		);
	}

	/**
	 * The end of a rendered datetime range.
	 *
	 * @since 0.36.0
	 *
	 * @param array{start: string, end: string, end_time: string} $formats   The formats to render with.
	 * @param bool                                                $same_date Whether the event starts and ends
	 *                                                                       on the same day.
	 *
	 * @return string|false The rendered end, or false when there is nothing to add.
	 */
	protected function get_display_end( array $formats, bool $same_date ) {
		if ( ! $same_date ) {
			return $this->get_datetime_end( $formats['end'] );
		}

		// An all-day event has no end time, and its date has already been said.
		return '' === $formats['end_time']
			? false
			: $this->get_time_end( $formats['end_time'] );
	}

	/**
	 * Format a datetime value for display.
	 *
	 * This method takes a datetime value from the event table, formats it according to the specified PHP date format,
	 * and allows you to choose between displaying the date in local time or GMT.
	 *
	 * @since 0.34.0
	 *
	 * @param string $format Optional. The PHP date format in which to format the datetime. Default is 'D, F j, g:ia T'.
	 * @param string $which  Optional. Datetime field in event table to format ('start' or 'end'). Default is 'start'.
	 * @param bool   $local  Optional. Whether to format the date in local time (true) or GMT (false). Default is true.
	 *
	 * @return string The formatted datetime value.
	 *
	 * @throws Exception If there is an issue while formatting the datetime value.
	 */
	public function get_formatted_datetime(
		string $format = 'D, F j, g:ia T',
		string $which = 'start',
		bool $local = true
	): string {
		return $this->format_datetime( $format, $which, $local, true );
	}

	/**
	 * Format a datetime value.
	 *
	 * The machine-readable output passed to this method must not be altered by
	 * the gatherpress_datetime_format filter, so filter application is opt-in via
	 * $apply_filter. Display formatting enables it; the ISO accessors do not.
	 *
	 * @since 0.36.0
	 *
	 * @param string $format       PHP date format.
	 * @param string $which        Datetime field in event table to format ('start' or 'end').
	 * @param bool   $local        Whether to format the date in local time (true) or GMT (false).
	 * @param bool   $apply_filter Whether to pass $format through the gatherpress_datetime_format filter.
	 *
	 * @return string The formatted datetime value.
	 *
	 * @throws Exception If there is an issue while formatting the datetime value.
	 */
	private function format_datetime( string $format, string $which, bool $local, bool $apply_filter ): string {
		$dt             = $this->get_datetime();
		$dt['timezone'] = Utility::maybe_convert_utc_offset( $dt['timezone'] );
		$tz             = null;

		if ( $this->is_all_day() ) {
			return $this->get_formatted_all_day( $format, $which, $local, $dt, $apply_filter );
		}

		$date = $dt[ sprintf( 'datetime_%s_gmt', $which ) ];

		if (
			true === $local
			&& ! empty( $dt['timezone'] )
			&& in_array( $dt['timezone'], Utility::list_timezone_and_utc_offsets(), true )
		) {
			$tz = new DateTimeZone( Utility::normalize_timezone_string( (string) $dt['timezone'] ) );
		} elseif ( false === $local ) {
			$tz = new DateTimeZone( 'GMT+0000' );
		}

		if ( ! empty( $date ) ) {
			$ts = strtotime( $date );

			// Validate::datetime() accepts what DateTime::createFromFormat() accepts,
			// which is wider than strtotime(): an overflowing value like
			// '2030-06-31 25:00:00' passes validation and still has no timestamp to
			// format, so report no datetime rather than falling back to the epoch.
			if ( false === $ts ) {
				return '';
			}

			if ( $apply_filter ) {
				/**
				 * Filters the PHP date format used to render an event datetime.
				 *
				 * @since 0.34.0
				 *
				 * @param string $format PHP date format.
				 * @param string $which  Datetime field, 'start' or 'end'.
				 * @param bool   $local  True for local time, false for GMT.
				 *
				 * @return string PHP date format.
				 */
				$format = apply_filters( 'gatherpress_datetime_format', $format, $which, $local );
			}

			// wp_date() only returns false for a non-numeric timestamp, which $ts is not.
			$date = (string) wp_date( $format, $ts, $tz );
		}

		return trim( $date );
	}

	/**
	 * Get the ISO 8601 start datetime of the event.
	 *
	 * Returns the machine-readable start datetime intended for <time datetime>
	 * attributes. Unlike get_datetime_start(), the format is not passed through
	 * the gatherpress_datetime_format filter, so the ISO value cannot be changed
	 * by display-format customization.
	 *
	 * @since 0.36.0
	 *
	 * @return string The ISO 8601 start datetime, or empty string when unset.
	 *
	 * @throws Exception If there is an issue formatting the start datetime.
	 */
	public function get_datetime_start_iso(): string {
		return $this->format_datetime( 'c', 'start', true, false );
	}

	/**
	 * Get the ISO 8601 end datetime of the event.
	 *
	 * Returns the machine-readable end datetime intended for <time datetime>
	 * attributes. Unlike get_datetime_end(), the format is not passed through
	 * the gatherpress_datetime_format filter, so the ISO value cannot be changed
	 * by display-format customization.
	 *
	 * @since 0.36.0
	 *
	 * @return string The ISO 8601 end datetime, or empty string when unset.
	 *
	 * @throws Exception If there is an issue formatting the end datetime.
	 */
	public function get_datetime_end_iso(): string {
		return $this->format_datetime( 'c', 'end', true, false );
	}

	/**
	 * Retrieves event timing and adjusts timezone based on user preferences or site settings.
	 *
	 * Fetches the event's start and end dates/times (local and GMT) along with
	 * timezone information from post meta. Datetime values are validated before
	 * being returned.
	 *
	 * @since 0.34.0
	 *
	 * @return EventDatetime An associative array detailing the event's schedule and timezone, potentially
	 * adjusted for user-specific preferences:
	 *     - 'datetime_start'     (string) The event start date and time.
	 *     - 'datetime_start_gmt' (string) The event start date and time in GMT.
	 *     - 'datetime_end'       (string) The event end date and time.
	 *     - 'datetime_end_gmt'   (string) The event end date and time in GMT.
	 *     - 'timezone'           (string) The timezone of the event, adjusted per user or site settings.
	 */
	public function get_datetime(): array {
		$data = array(
			'datetime_start'     => '',
			'datetime_start_gmt' => '',
			'datetime_end'       => '',
			'datetime_end_gmt'   => '',
			'timezone'           => sanitize_text_field( wp_timezone_string() ),
		);

		if ( ! $this->post ) {
			return $data;
		}

		if ( null !== $this->datetime_cache ) {
			return $this->datetime_cache;
		}

		foreach ( array_keys( $data ) as $key ) {
			$result = get_post_meta( $this->post->ID, Utility::prefix_key( $key ), true );

			if ( empty( $result ) ) {
				continue;
			}

			// Timezone field validates as a tz string; datetime fields validate as a datetime.
			// The trailing datetime check still runs for the timezone key so a mistyped value
			// can fall back to datetime parsing, matching the prior elseif behavior.
			if (
				( 'timezone' === $key && Validate::timezone( $result ) )
				|| Validate::datetime( $result )
			) {
				$data[ $key ] = $result;
			}
		}

		$data['timezone'] = apply_filters( 'gatherpress_timezone', $data['timezone'] );

		$this->datetime_cache = $data;

		return $data;
	}

	/**
	 * Convert a given date to GMT time zone.
	 *
	 * This method takes a date and a specified time zone and converts the date to the equivalent
	 * date and time in the GMT (UTC) time zone. It ensures that the date remains in the correct
	 * format.
	 *
	 * @since 0.34.0
	 *
	 * @param string       $date     The date to be converted.
	 * @param DateTimeZone $timezone The time zone to use for date conversion.
	 *
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
	 * including its address, name, permalink, phone, and website. Online-event
	 * status is not part of this shape — use the venue taxonomy directly when
	 * the caller needs to distinguish online events.
	 *
	 * @since 0.34.0
	 *
	 * @return array<string, string> An array containing venue information:
	 *                               - 'address' (string): The address of the venue.
	 *                               - 'name' (string): The name of the venue.
	 *                               - 'permalink' (string): The permalink (URL) of the venue.
	 *                               - 'phone' (string): The phone number of the venue.
	 *                               - 'website' (string): The website URL of the venue.
	 */
	public function get_venue_information(): array {
		$venue_information = array(
			'address'   => '',
			'name'      => '',
			'permalink' => '',
			'phone'     => '',
			'website'   => '',
		);

		if ( ! $this->post ) {
			return $venue_information;
		}

		$event_post_type = (string) get_post_type( $this->post );
		$venue_setup     = Setup::get_instance();
		$taxonomy        = $venue_setup->taxonomy_for_event_post_type( $event_post_type );
		$venue_terms     = get_the_terms( $this->post, $taxonomy );

		// get_the_terms() hands back false when nothing is assigned and a WP_Error for an
		// unregistered taxonomy; neither carries venue terms to inspect.
		if ( ! is_array( $venue_terms ) ) {
			return $venue_information;
		}

		// Prefer a real venue term (leading-underscore prefix) so a hybrid
		// event with both a physical venue and the `online-event` sentinel
		// attached surfaces the venue name. When no real venue term is
		// present, fall back to the first non-prefixed term encountered —
		// typically `online-event` in production, but kept generic so any
		// hand-inserted or test-only term still resolves to a name (the
		// previous behavior of `current( get_the_terms() )`).
		$term     = null;
		$fallback = null;

		foreach ( $venue_terms as $candidate ) {
			if ( $venue_setup->is_venue_term_slug( $candidate->slug ) ) {
				$term = $candidate;
				break;
			}

			$fallback = $fallback ?? $candidate;
		}

		$term  = $term ?? $fallback;
		$venue = null;

		if ( $term instanceof WP_Term ) {
			$venue_information['name'] = $term->name;
			$venue                     = $venue_setup->get_venue_post_from_term_slug( $term->slug );
		}

		if ( $venue instanceof WP_Post ) {
			$venue_information = array_merge( $venue_information, ( new Venue( $venue->ID ) )->get_information() );

			$venue_information['permalink'] = (string) get_permalink( $venue->ID );
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
	 * @since 0.34.0
	 *
	 * @return array<string, array{name: string, link?: string, download?: string}> An associative array containing
	 *     supported calendar links:
	 *     - 'google'  (array) Google Calendar link information with 'name' and 'link' keys.
	 *     - 'ical'    (array) iCal download link information with 'name' and 'download' keys.
	 *     - 'outlook' (array) Outlook download link information with 'name' and 'download' keys.
	 *     - 'yahoo'   (array) Yahoo Calendar link information with 'name' and 'link' keys.
	 *
	 * @throws Exception If there is an issue while generating calendar links.
	 */
	public function get_calendar_links(): array {
		if ( ! $this->post ) {
			return array();
		}

		$calendar = new Calendar( $this->post->ID );

		// Each URL getter only reports false when its event post cannot be resolved, and the
		// calendar was built from the post resolved directly above.
		return array(
			'google'  => array(
				'name' => __( 'Google Calendar', 'gatherpress' ),
				'link' => (string) $calendar->get_google_url(),
			),
			'ical'    => array(
				'name'     => __( 'iCal', 'gatherpress' ),
				'download' => (string) $calendar->get_ical_url(),
			),
			'outlook' => array(
				'name'     => __( 'Outlook', 'gatherpress' ),
				'download' => (string) $calendar->get_outlook_url(),
			),
			'yahoo'   => array(
				'name' => __( 'Yahoo Calendar', 'gatherpress' ),
				'link' => (string) $calendar->get_yahoo_url(),
			),
		);
	}

	/**
	 * Generate a calendar event description with a link to the event details.
	 *
	 * This method generates a descriptive text for a calendar event, including a link to the event details page.
	 * The generated description can be used in calendar applications or event listings.
	 *
	 * @since 0.34.0
	 *
	 * @return string The calendar event description with the event details link.
	 */
	public function get_calendar_description(): string {
		if ( ! $this->post ) {
			return '';
		}

		/* translators: %s: event link. */
		return sprintf( __( 'For details go to %s', 'gatherpress' ), get_the_permalink( $this->post ) );
	}

	/**
	 * Save the start and end datetimes for an event to the custom event table.
	 *
	 * This method allows you to save the start and end datetimes, along with the timezone,
	 * for an event into the custom event table. It provides a structured way to store event data
	 * and ensures consistency in the format of datetime values.
	 *
	 * @since 0.34.0
	 *
	 * @param array{post_id?: int, datetime_start?: string, datetime_end?: string, timezone?: string} $params {
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

		// Nothing to attach the datetimes to when the post ID handed to the constructor
		// did not resolve to an event.
		if ( ! $this->post ) {
			return false;
		}

		$params = array_merge(
			array(
				'post_id'        => $this->post->ID,
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
		$timezone           = new DateTimeZone( Utility::normalize_timezone_string( (string) $fields['timezone'] ) );

		// Everything downstream reads a datetime in one format: the day
		// boundaries slice the date off the front, and `get_datetime()` drops
		// a stored value that does not match it. So whatever a caller wrote
		// is converted once, here, rather than parsed again at each step.
		$fields['datetime_start'] = $this->normalize_datetime( (string) $fields['datetime_start'], $timezone );
		$fields['datetime_end']   = $this->normalize_datetime( (string) $fields['datetime_end'], $timezone );

		if ( $this->is_all_day() ) {
			$fields['datetime_start'] = self::to_day_boundary( $fields['datetime_start'], 'start' );
			$fields['datetime_end']   = self::to_day_boundary( $fields['datetime_end'], 'end' );
		}

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
		} else {
			$value = $wpdb->insert( $table, $fields );
		}

		// Clear cache after insert or update.
		$this->datetime_cache = null;
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
				// Only the string-valued fields reach here; post_id is skipped above.
				sanitize_text_field( (string) $field )
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
		if ( ! $this->post ) {
			return '';
		}

		$event_link = (string) get_post_meta( $this->post->ID, 'gatherpress_online_event_link', true );

		/**
		 * Filters whether to force the display of the online event link.
		 *
		 * Allows modification of the decision to force the online event link
		 * display in the `maybe_get_online_event_link` method. Return true to
		 * force the online event link, or false to allow normal checks.
		 *
		 * @since 0.27.0
		 *
		 * @param bool $force_online_event_link Whether to force the display of the online event link.
		 *
		 * @return bool True to force online event link, false to allow normal checks.
		 */
		$force_online_event_link = apply_filters( 'gatherpress_force_online_event_link', false );

		if ( ! $force_online_event_link && ! is_admin() ) {
			$user_identifier = Rsvp_Setup::get_instance()->get_user_identifier();
			$response        = ( new Rsvp( $this->post->ID ) )->get( $user_identifier );

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
