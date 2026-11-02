<?php
/**
 * The dates of a post on a calendar.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use WP_Post;

/**
 * Class Post_Dates.
 *
 * Events use their event dates. Other posts use their publish date.
 *
 * @since TBD
 */
final class Post_Dates {

	/**
	 * Minutes in a day, the end of the last time-grid track.
	 *
	 * @since TBD
	 * @var int
	 */
	const MINUTES_PER_DAY = 1440;

	/**
	 * Whether the post has event dates.
	 *
	 * @since TBD
	 * @var bool
	 */
	public readonly bool $is_event;

	/**
	 * Whether the post is an all-day event.
	 *
	 * @since TBD
	 * @var bool
	 */
	public readonly bool $is_all_day;

	/**
	 * First day of the post (Y-m-d).
	 *
	 * @since TBD
	 * @var string
	 */
	public readonly string $start_date;

	/**
	 * Last day of the post (Y-m-d).
	 *
	 * An event that ends at 00:00 on a later day does not run on that day, so it ends on the
	 * day before. An end before the start is treated as no end.
	 *
	 * @since TBD
	 * @var string
	 */
	public readonly string $end_date;

	/**
	 * Start of the post in minutes past midnight.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $start_minute;

	/**
	 * End of the post in minutes past midnight of its first day.
	 *
	 * An event that ends at 00:00 on the next day runs to the end of its day. An event with
	 * no end, or an end before its start, lasts one hour. An event lasts at least 15 minutes.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $end_minute;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID.
	 */
	public function __construct( public readonly int $post_id ) {
		$event     = $post_id > 0 ? new Event( $post_id ) : null;
		$datetimes = $event ? $event->get_datetime() : array();
		$start     = $datetimes['datetime_start'] ?? '';
		$end       = $datetimes['datetime_end'] ?? '';

		$this->is_event   = '' !== $start;
		$this->is_all_day = $event && $event->is_all_day();

		if ( ! $this->is_event ) {
			$post  = get_post( $post_id );
			$start = $post instanceof WP_Post ? $post->post_date : '';
			$end   = '';
		}

		// Event::get_datetime() and post_date hold only valid datetimes.
		$start_ts = (int) strtotime( $start );
		$end_ts   = '' !== $end ? (int) strtotime( $end ) : 0;
		$end_ts   = $end_ts > $start_ts ? $end_ts : $start_ts + HOUR_IN_SECONDS;

		$this->start_date   = substr( $start, 0, 10 );
		$this->end_date     = $this->resolve_end_date( $end );
		$this->start_minute = $this->to_minutes( $start_ts );
		$this->end_minute   = max( $this->start_minute + 15, $this->resolve_end_minute( $end_ts ) );
	}

	/**
	 * Resolves the last calendar day of the post.
	 *
	 * @since TBD
	 *
	 * @param string $end End datetime (Y-m-d H:i:s), or an empty string.
	 *
	 * @return string Last day of the post (Y-m-d).
	 */
	private function resolve_end_date( string $end ): string {
		$end_date = '' !== $end ? substr( $end, 0, 10 ) : $this->start_date;

		if ( $end_date > $this->start_date && '00:00:00' === substr( $end, 11, 8 ) ) {
			$end_date = gmdate( 'Y-m-d', (int) strtotime( $end_date . ' -1 day' ) );
		}

		return $end_date < $this->start_date ? $this->start_date : $end_date;
	}

	/**
	 * Resolves the end of the post in minutes past midnight of its first day.
	 *
	 * @since TBD
	 *
	 * @param int $end_ts End of the post, as a timestamp of its site-time datetime.
	 *
	 * @return int Minutes past midnight.
	 */
	private function resolve_end_minute( int $end_ts ): int {
		// An end at 00:00 on the next day is the end of the start day.
		if ( $this->start_date === $this->end_date && gmdate( 'Y-m-d', $end_ts ) > $this->start_date ) {
			return self::MINUTES_PER_DAY;
		}

		return $this->to_minutes( $end_ts );
	}

	/**
	 * Converts a timestamp to minutes past midnight.
	 *
	 * @since TBD
	 *
	 * @param int $timestamp Timestamp of a site-time datetime.
	 *
	 * @return int Minutes past midnight.
	 */
	private function to_minutes( int $timestamp ): int {
		return ( (int) gmdate( 'G', $timestamp ) * 60 ) + (int) gmdate( 'i', $timestamp );
	}
}
