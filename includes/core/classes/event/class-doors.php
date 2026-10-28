<?php
/**
 * When an event's doors open and close.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Utility;
use GatherPress\Core\Validate;

/**
 * Class Doors.
 *
 * Reads an event's optional doors open and close times, stored as 'H:i'
 * in the `gatherpress_doors_open` and `gatherpress_doors_close` meta.
 *
 * @since TBD
 */
final class Doors {

	/**
	 * The event the doors belong to.
	 *
	 * @since TBD
	 * @var Event
	 */
	private readonly Event $event;

	/**
	 * Doors constructor.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event the doors belong to.
	 */
	public function __construct( Event $event ) {
		$this->event = $event;
	}

	/**
	 * When the doors open or close.
	 *
	 * The time is stored without a date, so it follows the event when its
	 * date moves. Doors open on the start date and close on the end date,
	 * except that a doors open time later than the start means the day
	 * before, and a doors close time earlier than the end means the day
	 * after. An all-day event has no time of day to compare against, so its
	 * doors stay on the start and end dates.
	 *
	 * @since TBD
	 *
	 * @param string $which Which time, 'open' or 'close'.
	 *
	 * @return DateTimeImmutable|null The moment in the event's own timezone,
	 *                                or null when no time is set.
	 */
	public function get_datetime( string $which ): ?DateTimeImmutable {
		$post_id = $this->event->post->ID ?? 0;
		$time    = (string) get_post_meta( $post_id, sprintf( 'gatherpress_doors_%s', $which ), true );
		$anchor  = (string) get_post_meta(
			$post_id,
			'open' === $which ? 'gatherpress_datetime_start' : 'gatherpress_datetime_end',
			true
		);

		// The stored local datetime is read in the event's own zone, not the
		// viewer's, which the gatherpress_timezone filter may have swapped in.
		$anchor = Validate::time( $time ) && Validate::datetime( $anchor )
			? date_create_immutable(
				$anchor,
				self::to_timezone( (string) get_post_meta( $post_id, 'gatherpress_timezone', true ) )
			)
			: false;

		if ( false === $anchor ) {
			return null;
		}

		list( $hour, $minute ) = array_map( 'intval', explode( ':', $time ) );

		$doors = $anchor->setTime( $hour, $minute );

		if ( ! $this->event->is_all_day() ) {
			if ( 'open' === $which && $doors > $anchor ) {
				$doors = $doors->modify( '-1 day' );
			} elseif ( 'close' === $which && $doors < $anchor ) {
				$doors = $doors->modify( '+1 day' );
			}
		}

		return $doors;
	}

	/**
	 * Format when the doors open or close.
	 *
	 * A timed event's doors are shown in the same zone as its date, which
	 * may be the viewer's own. An all-day event's are shown in the event's
	 * zone, the way its date is.
	 *
	 * Without a format, the site's time format is used, and the date is
	 * added when the doors fall on another day than the start or end they
	 * belong to, such as doors that open the night before.
	 *
	 * @since TBD
	 *
	 * @param string $which  Which time, 'open' or 'close'.
	 * @param string $format Optional. PHP date format. Defaults to the site's time format.
	 *
	 * @return string The formatted time, or an empty string when no time is set.
	 */
	public function get_time( string $which, string $format = '' ): string {
		$doors = $this->get_datetime( $which );

		if ( null === $doors ) {
			return '';
		}

		$datetime = $this->event->get_datetime();
		$timezone = self::to_timezone(
			$this->event->is_all_day()
				? (string) get_post_meta( $this->event->post->ID ?? 0, 'gatherpress_timezone', true )
				: $datetime['timezone']
		);

		if ( '' === $format ) {
			$anchor   = $datetime[ 'open' === $which ? 'datetime_start_gmt' : 'datetime_end_gmt' ];
			$same_day = wp_date( 'Y-m-d', (int) strtotime( $anchor . ' UTC' ), $timezone )
				=== wp_date( 'Y-m-d', $doors->getTimestamp(), $timezone );
			$format   = $same_day
				? (string) get_option( 'time_format' )
				: sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) );
		}

		return (string) wp_date( $format, $doors->getTimestamp(), $timezone );
	}

	/**
	 * Turn a stored or filtered timezone string into a timezone.
	 *
	 * @since TBD
	 *
	 * @param string $timezone A timezone identifier or UTC offset.
	 *
	 * @return DateTimeZone The timezone, or the site's when the string is not a known one.
	 */
	private static function to_timezone( string $timezone ): DateTimeZone {
		$timezone = Utility::maybe_convert_utc_offset( $timezone );

		return in_array( $timezone, Utility::list_timezone_and_utc_offsets(), true )
			? new DateTimeZone( Utility::normalize_timezone_string( $timezone ) )
			: wp_timezone();
	}
}
