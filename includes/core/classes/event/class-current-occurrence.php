<?php
/**
 * The date each event in a loop stands for.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Current_Occurrence.
 *
 * A query that joins the date index returns a repeating event once per
 * date, each row carrying its date. While the loop's global `$post` is that
 * row, the event reads that date instead of its saved one, so the Event
 * Date and Add to Calendar blocks show the row's date. Outside such a loop,
 * an event reads its saved dates as before.
 *
 * Reading the global `$post` means WordPress decides which row is current:
 * a nested loop and `wp_reset_postdata()` put the outer row back, and a loop
 * that stops early leaves nothing behind. The calendar, which renders its
 * entries from plain posts, sets each entry's date directly instead.
 *
 * @since TBD
 */
final class Current_Occurrence {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The date fields each row carries, named as `Event::get_datetime()` names them, plus the key.
	 *
	 * @since TBD
	 * @var string[]
	 */
	const FIELDS = array(
		'recurrence_id',
		'datetime_start',
		'datetime_start_gmt',
		'datetime_end',
		'datetime_end_gmt',
		'timezone',
	);

	/**
	 * Prefix of the post properties a joined row's date fields arrive in.
	 *
	 * @since TBD
	 * @var string
	 */
	const PROPERTY_PREFIX = 'gatherpress_occurrence_';

	/**
	 * Dates set directly, such as by the calendar, by post ID.
	 *
	 * @since TBD
	 * @var array<int, array<string, string>>
	 */
	protected array $current = array();

	/**
	 * Set an event's current date while something renders it outside a loop.
	 *
	 * Clear it with `clear()` once done.
	 *
	 * @since TBD
	 *
	 * @param int                   $post_id    The event's post ID.
	 * @param array<string, string> $occurrence The date, keyed by `FIELDS`.
	 *
	 * @return void
	 */
	public function set( int $post_id, array $occurrence ): void {
		$this->current[ $post_id ] = $occurrence;
	}

	/**
	 * Clear an event's current date.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event's post ID.
	 *
	 * @return void
	 */
	public function clear( int $post_id ): void {
		unset( $this->current[ $post_id ] );
	}

	/**
	 * An event's current date: one set directly, or the loop row's when the global post is that event.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event's post ID.
	 *
	 * @return array<string, string>|null The date, keyed by `FIELDS`, or null to read the saved dates.
	 */
	public function get( int $post_id ): ?array {
		$post = $GLOBALS['post'] ?? null;
		$row  = $post instanceof WP_Post && $post_id === $post->ID ? self::from_post( $post ) : null;

		return $this->current[ $post_id ] ?? $row;
	}

	/**
	 * The date a joined row carries.
	 *
	 * @since TBD
	 *
	 * @param WP_Post $post The row's post.
	 *
	 * @return array<string, string>|null The date, keyed by `FIELDS`, or null if the row has none.
	 */
	public static function from_post( WP_Post $post ): ?array {
		$occurrence = array();

		foreach ( self::FIELDS as $field ) {
			$value = $post->{self::PROPERTY_PREFIX . $field} ?? null;

			if ( ! is_string( $value ) || '' === $value ) {
				return null;
			}

			$occurrence[ $field ] = $value;
		}

		return $occurrence;
	}

	/**
	 * An event's current date in the shape `Event::get_datetime()` returns.
	 *
	 * @since TBD
	 *
	 * @param WP_Post|null $post The event's post.
	 *
	 * @return array{datetime_start: string, datetime_start_gmt: string, datetime_end: string,
	 *     datetime_end_gmt: string, timezone: string}|null The date, or null to read the saved dates.
	 */
	public function get_datetime( ?WP_Post $post ): ?array {
		$occurrence = $post ? $this->get( $post->ID ) : null;

		if ( null === $occurrence ) {
			return null;
		}

		return array(
			'datetime_start'     => $occurrence['datetime_start'],
			'datetime_start_gmt' => $occurrence['datetime_start_gmt'],
			'datetime_end'       => $occurrence['datetime_end'],
			'datetime_end_gmt'   => $occurrence['datetime_end_gmt'],
			/** This filter is documented in includes/core/classes/event/class-event.php */
			'timezone'           => (string) apply_filters( 'gatherpress_timezone', $occurrence['timezone'] ),
		);
	}

	/**
	 * Whether an event's current date is all day, which its key shows.
	 *
	 * @since TBD
	 *
	 * @param WP_Post|null $post The event's post.
	 *
	 * @return bool|null True for a date key, `Ymd`, without a time, or null when there is no current date.
	 */
	public function is_all_day( ?WP_Post $post ): ?bool {
		$occurrence = $post ? $this->get( $post->ID ) : null;

		return null === $occurrence ? null : 8 === strlen( $occurrence['recurrence_id'] );
	}
}
