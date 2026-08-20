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
use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Core\Settings;
use GatherPress\Core\Utility;
use GatherPress\Core\Validate;
use GatherPress\Core\Venue\Setup;
use GatherPress\Core\Venue\Venue;
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
	 * Non-time PHP DateTime formatting characters
	 *
	 * @since 0.34.0
	 * @var string[]
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
		$settings    = Settings::get_instance();
		$date_format = apply_filters(
			'gatherpress_date_format',
			$settings->get( 'date_format' )
		);
		$time_format = apply_filters(
			'gatherpress_time_format',
			$settings->get( 'time_format' )
		);
		$timezone    = $settings->get( 'show_timezone' ) ? ' T' : '';

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
		return $parts ? implode( ' ', $parts ) : self::DATETIME_PLACEHOLDER;
	}

	/**
	 * Check if the start and end DateTime fall on the same date.
	 *
	 * Compares the start and end DateTime objects to determine if they are on the same date.
	 *
	 * @since 0.34.0
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
		$dt             = $this->get_datetime();
		$date           = $dt[ sprintf( 'datetime_%s_gmt', $which ) ];
		$dt['timezone'] = Utility::maybe_convert_utc_offset( $dt['timezone'] );
		$tz             = null;

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

			// wp_date() only returns false for a non-numeric timestamp, which $ts is not.
			$date = (string) wp_date(
				apply_filters( 'gatherpress_datetime_format', $format, $which, $local ),
				$ts,
				$tz
			);
		}

		return trim( $date );
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
