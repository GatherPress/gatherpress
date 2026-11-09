<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Occurrences.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Occurrence;
use GatherPress\Core\Event\Occurrences;
use GatherPress\Tests\Base;

/**
 * Class Test_Occurrences.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Occurrences
 */
class Test_Occurrences extends Base {
	/**
	 * Clear the filters a test added.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'gatherpress_event_occurrences' );
		remove_all_filters( 'gatherpress_event_occurrence' );

		parent::tear_down();
	}

	/**
	 * An event with dates, 8:00 PM to 10:00 PM on December 11, 2026 in New York.
	 *
	 * @since TBD
	 *
	 * @param bool $all_day Whether the event lasts all day.
	 *
	 * @return int The event post ID.
	 */
	protected function event( bool $all_day = false ): int {
		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		if ( $all_day ) {
			update_post_meta( $post_id, 'gatherpress_is_all_day', 1 );
		}

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2026-12-11 20:00:00',
				'datetime_end'   => '2026-12-11 22:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		return $post_id;
	}

	/**
	 * An event's occurrences.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return Occurrences The occurrences.
	 */
	protected function occurrences( int $post_id ): Occurrences {
		return ( new Event( $post_id ) )->occurrences();
	}

	/**
	 * An event's occurrences in December 2026.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return Occurrence[] The occurrences.
	 */
	protected function december( int $post_id ): array {
		return $this->occurrences( $post_id )->in_range( $this->utc( '2026-12-01' ), $this->utc( '2027-01-01' ) );
	}

	/**
	 * A date in UTC.
	 *
	 * @since TBD
	 *
	 * @param string $datetime The date.
	 *
	 * @return DateTimeImmutable The date.
	 */
	protected function utc( string $datetime ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The key is the local start, or the local date for an all-day occurrence.
	 *
	 * @since TBD
	 *
	 * @covers ::recurrence_id
	 *
	 * @return void
	 */
	public function test_recurrence_id(): void {
		$start = new DateTimeImmutable( '2026-12-11 20:00:00', new DateTimeZone( 'America/New_York' ) );

		$this->assertSame( '20261211T200000', Occurrences::recurrence_id( $start ) );
		$this->assertSame( '20261211', Occurrences::recurrence_id( $start, true ) );
	}

	/**
	 * An event without repeat rules is a series of one, in its own timezone.
	 *
	 * @since TBD
	 *
	 * @covers ::in_range
	 * @covers ::__construct
	 * @covers ::post_id
	 * @covers ::single
	 * @covers \GatherPress\Core\Event\Occurrence::__construct
	 * @covers \GatherPress\Core\Event::occurrences
	 *
	 * @return void
	 */
	public function test_in_range_returns_the_one_date(): void {
		$post_id     = $this->event();
		$occurrences = $this->december( $post_id );

		$this->assertCount( 1, $occurrences );
		$this->assertSame( $post_id, $occurrences[0]->series_post_id );
		$this->assertSame( '20261211T200000', $occurrences[0]->recurrence_id );
		$this->assertSame( '2026-12-11 20:00:00', $occurrences[0]->start->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-12-11 22:00:00', $occurrences[0]->end->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( 'America/New_York', $occurrences[0]->start->getTimezone()->getName() );
		$this->assertFalse( $occurrences[0]->all_day );
	}

	/**
	 * A date outside the range is left out, and one still running at the
	 * range's start is kept.
	 *
	 * @since TBD
	 *
	 * @covers ::in_range
	 *
	 * @return void
	 */
	public function test_in_range_respects_the_range(): void {
		$post_id = $this->event();

		$this->assertSame(
			array(),
			$this->occurrences( $post_id )->in_range( $this->utc( '2027-01-01' ), $this->utc( '2027-02-01' ) ),
			'A range after the event is empty.'
		);
		$this->assertSame(
			array(),
			$this->occurrences( $post_id )->in_range( $this->utc( '2026-11-01' ), $this->utc( '2026-12-12 01:00:00' ) ),
			'A range that ends as the event starts is empty.'
		);
		$this->assertCount(
			1,
			$this->occurrences( $post_id )->in_range( $this->utc( '2026-12-12 02:00:00' ), $this->utc( '2026-12-13' ) ),
			'A range that starts while the event runs includes it.'
		);
	}

	/**
	 * The one date is worked out once per instance.
	 *
	 * @since TBD
	 *
	 * @covers ::single
	 *
	 * @return void
	 */
	public function test_single_is_worked_out_once(): void {
		$post_id     = $this->event();
		$occurrences = $this->occurrences( $post_id );

		$this->assertSame( $occurrences->find( '20261211T200000' ), $occurrences->find( '20261211T200000' ) );
	}

	/**
	 * A post that is not an event, or an event without a start, has no dates.
	 *
	 * @since TBD
	 *
	 * @covers ::in_range
	 * @covers ::single
	 *
	 * @return void
	 */
	public function test_in_range_is_empty_without_dates(): void {
		$post    = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;
		$no_date = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$from    = $this->utc( '2000-01-01' );
		$to      = $this->utc( '2100-01-01' );

		$this->assertSame( array(), $this->occurrences( $post )->in_range( $from, $to ), 'A post has no dates.' );
		$this->assertSame(
			array(),
			$this->occurrences( $no_date )->in_range( $from, $to ),
			'An event without a start has no dates.'
		);
	}

	/**
	 * An event without an end runs no time at all.
	 *
	 * @since TBD
	 *
	 * @covers ::single
	 *
	 * @return void
	 */
	public function test_an_event_without_an_end_ends_as_it_starts(): void {
		$post_id = $this->event();

		delete_post_meta( $post_id, 'gatherpress_datetime_end_gmt' );

		$occurrence = $this->occurrences( $post_id )->find( '20261211T200000' );

		$this->assertEquals( $occurrence->start, $occurrence->end );
	}

	/**
	 * An all-day event's key is its local date.
	 *
	 * @since TBD
	 *
	 * @covers ::single
	 *
	 * @return void
	 */
	public function test_an_all_day_event_is_keyed_by_date(): void {
		$post_id     = $this->event( true );
		$occurrences = $this->december( $post_id );

		$this->assertSame( '20261211', $occurrences[0]->recurrence_id );
		$this->assertTrue( $occurrences[0]->all_day );
	}

	/**
	 * The filter can add dates, which come back in start order.
	 *
	 * @since TBD
	 *
	 * @covers ::in_range
	 *
	 * @return void
	 */
	public function test_in_range_filter_adds_dates_in_start_order(): void {
		$post_id = $this->event();
		$zone    = new DateTimeZone( 'America/New_York' );
		$earlier = new Occurrence(
			$post_id,
			'20261204T200000',
			new DateTimeImmutable( '2026-12-04 20:00:00', $zone ),
			new DateTimeImmutable( '2026-12-04 22:00:00', $zone )
		);

		add_filter(
			'gatherpress_event_occurrences',
			static function ( array $occurrences ) use ( $earlier ): array {
				$occurrences[] = $earlier;

				return $occurrences;
			}
		);

		$keys = array_map(
			static function ( Occurrence $occurrence ): string {
				return $occurrence->recurrence_id;
			},
			$this->december( $post_id )
		);

		$this->assertSame( array( '20261204T200000', '20261211T200000' ), $keys );
	}

	/**
	 * The one date is found by its key, and any other key finds nothing.
	 *
	 * @since TBD
	 *
	 * @covers ::find
	 *
	 * @return void
	 */
	public function test_find(): void {
		$post_id = $this->event();

		$found = $this->occurrences( $post_id )->find( '20261211T200000' );

		$this->assertSame( '20261211T200000', $found->recurrence_id );
		$this->assertNull( $this->occurrences( $post_id )->find( '20261212T200000' ), 'Another key finds nothing.' );
	}

	/**
	 * The filter can resolve a key the event's one date does not match, and
	 * anything that is not an occurrence finds nothing.
	 *
	 * @since TBD
	 *
	 * @covers ::find
	 *
	 * @return void
	 */
	public function test_find_filter(): void {
		$post_id = $this->event();
		$zone    = new DateTimeZone( 'America/New_York' );
		$other   = new Occurrence(
			$post_id,
			'20261218T200000',
			new DateTimeImmutable( '2026-12-18 20:00:00', $zone ),
			new DateTimeImmutable( '2026-12-18 22:00:00', $zone )
		);

		add_filter(
			'gatherpress_event_occurrence',
			static function ( ?Occurrence $occurrence, int $id, string $key ) use ( $other ) {
				if ( '20261218T200000' === $key ) {
					return $other;
				}

				return '20261225T200000' === $key ? 'not an occurrence' : $occurrence;
			},
			10,
			3
		);

		$this->assertSame( $other, $this->occurrences( $post_id )->find( '20261218T200000' ) );
		$this->assertNull( $this->occurrences( $post_id )->find( '20261225T200000' ) );
	}
}
