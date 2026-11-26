<?php
/**
 * The date, time, venue, and status of an event.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Venue\Setup as Venue_Setup;

/**
 * Class Logistics.
 *
 * The details of an event that people who RSVP'd plan around, as they are
 * stored at one moment. `Change` reads them before and after a save and
 * compares the two.
 *
 * @since TBD
 */
final class Logistics {

	/**
	 * The values that are compared, in the order they are reported.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const KEYS = array( 'datetime_start', 'datetime_end', 'timezone', 'venue', 'status' );

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param string                $datetime_start The local start, `Y-m-d H:i:s`, or empty.
	 * @param string                $datetime_end   The local end, `Y-m-d H:i:s`, or empty.
	 * @param string                $timezone       The time zone of the start and the end.
	 * @param array<string, string> $venue          Venue term names keyed by slug, sorted by slug.
	 * @param string[]              $status         Status slugs, highest priority first.
	 */
	public function __construct(
		public readonly string $datetime_start,
		public readonly string $datetime_end,
		public readonly string $timezone,
		public readonly array $venue,
		public readonly array $status
	) {}

	/**
	 * Read the logistics of an event as they are stored now.
	 *
	 * The dates are read from meta rather than through `Event::get_datetime()`,
	 * which caches and applies the viewer's time zone preference.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return self The logistics.
	 */
	public static function from_post( int $post_id ): self {
		$taxonomy = Venue_Setup::get_instance()->taxonomy_for_event_post_type( (string) get_post_type( $post_id ) );
		$terms    = get_the_terms( $post_id, $taxonomy );
		$venue    = array();

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$venue[ $term->slug ] = $term->name;
		}

		ksort( $venue );

		return new self(
			(string) get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			(string) get_post_meta( $post_id, 'gatherpress_datetime_end', true ),
			(string) get_post_meta( $post_id, 'gatherpress_timezone', true ),
			$venue,
			( new Status( $post_id ) )->get_all()
		);
	}

	/**
	 * Build the logistics from the array `to_array()` returns.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $values The stored values.
	 *
	 * @return self The logistics.
	 */
	public static function from_array( array $values ): self {
		return new self(
			(string) ( $values['datetime_start'] ?? '' ),
			(string) ( $values['datetime_end'] ?? '' ),
			(string) ( $values['timezone'] ?? '' ),
			array_map( 'strval', (array) ( $values['venue'] ?? array() ) ),
			array_values( array_map( 'strval', (array) ( $values['status'] ?? array() ) ) )
		);
	}

	/**
	 * Get the logistics as an array, to store in post meta.
	 *
	 * @since TBD
	 *
	 * @return array{
	 *     datetime_start: string,
	 *     datetime_end: string,
	 *     timezone: string,
	 *     venue: array<string, string>,
	 *     status: string[]
	 * } The values, keyed as in `KEYS`.
	 */
	public function to_array(): array {
		return array(
			'datetime_start' => $this->datetime_start,
			'datetime_end'   => $this->datetime_end,
			'timezone'       => $this->timezone,
			'venue'          => $this->venue,
			'status'         => $this->status,
		);
	}

	/**
	 * Get a copy with other venue terms.
	 *
	 * @since TBD
	 *
	 * @param array<string, string> $venue Venue term names keyed by slug.
	 *
	 * @return self The copy.
	 */
	public function with_venue( array $venue ): self {
		ksort( $venue );

		return new self( $this->datetime_start, $this->datetime_end, $this->timezone, $venue, $this->status );
	}

	/**
	 * Get a copy with other status slugs.
	 *
	 * @since TBD
	 *
	 * @param string[] $status Status slugs, highest priority first.
	 *
	 * @return self The copy.
	 */
	public function with_status( array $status ): self {
		return new self( $this->datetime_start, $this->datetime_end, $this->timezone, $this->venue, $status );
	}

	/**
	 * Get the keys whose values differ in other logistics.
	 *
	 * @since TBD
	 *
	 * @param Logistics $other The logistics to compare with, usually from later.
	 *
	 * @return string[] The keys that differ, in the order of `KEYS`.
	 */
	public function changed_keys( Logistics $other ): array {
		$before = $this->to_array();
		$after  = $other->to_array();

		// A venue is compared by its term slugs, so a renamed venue is not a change.
		$before['venue'] = array_keys( $before['venue'] );
		$after['venue']  = array_keys( $after['venue'] );

		return array_values(
			array_filter(
				self::KEYS,
				static fn( string $key ): bool => $before[ $key ] !== $after[ $key ]
			)
		);
	}
}
