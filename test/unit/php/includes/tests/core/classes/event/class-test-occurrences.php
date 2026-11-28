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
use GatherPress\Core\Event\Rules;
use GatherPress\Core\Event\Status;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

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
		remove_all_filters( 'gatherpress_event_occurrence_by_key' );
		remove_all_filters( 'gatherpress_event_primary_occurrence' );

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
		$this->assertFalse( $occurrences[0]->is_all_day );
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

		$occurrences = $this->occurrences( $no_date );
		$this->assertNull( $occurrences->find( '20261211T200000' ) );
		$this->assertNull( $occurrences->find( '20261211T200000' ), 'A second lookup reuses the empty result.' );
		$this->assertNull(
			$occurrences->on_day( $this->utc( '2026-12-11' ) ),
			'An event without dates has nothing on any day.'
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
		$this->assertTrue( $occurrences[0]->is_all_day );
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
			'gatherpress_event_occurrence_by_key',
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
		$this->assertSame(
			'20261211T200000',
			$this->occurrences( $post_id )->find( '20261211T200000' )->recurrence_id,
			'A key the filter does not handle keeps the one date.'
		);
	}

	/**
	 * The one date carries the event's status.
	 *
	 * @since TBD
	 *
	 * @covers ::single
	 * @covers \GatherPress\Core\Event\Occurrence::is_canceled
	 *
	 * @return void
	 */
	public function test_the_one_date_carries_the_event_status(): void {
		$post_id    = $this->event();
		$occurrence = $this->occurrences( $post_id )->find( '20261211T200000' );

		$this->assertSame( Status::STATUS_SCHEDULED, $occurrence->status );
		$this->assertFalse( $occurrence->is_canceled(), 'A scheduled event is not canceled.' );

		( new Status( $post_id ) )->set( Status::STATUS_CANCELED );

		$canceled = $this->occurrences( $post_id )->find( '20261211T200000' );

		$this->assertSame( Status::STATUS_CANCELED, $canceled->status );
		$this->assertTrue( $canceled->is_canceled(), 'A canceled event has a canceled date.' );
	}

	/**
	 * Without repeat rules, the primary occurrence is the event's one date.
	 *
	 * @since TBD
	 *
	 * @covers ::primary
	 *
	 * @return void
	 */
	public function test_primary_is_the_one_date(): void {
		$post_id = $this->event();
		$no_date = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		$this->assertSame( '20261211T200000', $this->occurrences( $post_id )->primary()->recurrence_id );
		$this->assertNull( $this->occurrences( $no_date )->primary(), 'An event without a start has none.' );
	}

	/**
	 * The filter can pick another primary occurrence, and anything that is
	 * not an occurrence gives none.
	 *
	 * @since TBD
	 *
	 * @covers ::primary
	 *
	 * @return void
	 */
	public function test_primary_filter(): void {
		$post_id = $this->event();
		$zone    = new DateTimeZone( 'America/New_York' );
		$next    = new Occurrence(
			$post_id,
			'20261218T200000',
			new DateTimeImmutable( '2026-12-18 20:00:00', $zone ),
			new DateTimeImmutable( '2026-12-18 22:00:00', $zone )
		);

		add_filter(
			'gatherpress_event_primary_occurrence',
			static function () use ( $next ): Occurrence {
				return $next;
			}
		);

		$this->assertSame( $next, $this->occurrences( $post_id )->primary() );

		remove_all_filters( 'gatherpress_event_primary_occurrence' );
		add_filter( 'gatherpress_event_primary_occurrence', '__return_false' );

		$this->assertNull( $this->occurrences( $post_id )->primary() );
	}

	/**
	 * The date an occurrence starts on finds it, in the event's timezone.
	 *
	 * @since TBD
	 *
	 * @covers ::on_day
	 *
	 * @return void
	 */
	public function test_on_day(): void {
		$post_id = $this->event();

		// 8:00 PM in New York is already December 12 in UTC.
		$this->assertSame(
			'20261211T200000',
			$this->occurrences( $post_id )->on_day( $this->utc( '2026-12-11' ) )->recurrence_id,
			'The local start date finds the occurrence.'
		);
		$this->assertNull(
			$this->occurrences( $post_id )->on_day( $this->utc( '2026-12-12' ) ),
			'The UTC start date does not.'
		);
		$this->assertSame(
			'20261211',
			$this->occurrences( $this->event( true ) )->on_day( $this->utc( '2026-12-11' ) )->recurrence_id,
			'An all-day occurrence is found by its date.'
		);
	}

	/**
	 * With several dates, the first that starts on the date is found.
	 *
	 * @since TBD
	 *
	 * @covers ::on_day
	 *
	 * @return void
	 */
	public function test_on_day_finds_the_first_of_several(): void {
		$post_id = $this->event();
		$zone    = new DateTimeZone( 'America/New_York' );
		$morning = new Occurrence(
			$post_id,
			'20261211T090000',
			new DateTimeImmutable( '2026-12-11 09:00:00', $zone ),
			new DateTimeImmutable( '2026-12-11 10:00:00', $zone )
		);
		$evening = new Occurrence(
			$post_id,
			'20261210T230000',
			new DateTimeImmutable( '2026-12-10 23:00:00', $zone ),
			new DateTimeImmutable( '2026-12-11 01:00:00', $zone )
		);

		add_filter(
			'gatherpress_event_occurrences',
			static function ( array $occurrences ) use ( $morning, $evening ): array {
				$occurrences[] = $morning;
				$occurrences[] = $evening;

				return $occurrences;
			}
		);

		$this->assertSame( $morning, $this->occurrences( $post_id )->on_day( $this->utc( '2026-12-11' ) ) );
	}

	/**
	 * Keys and local times use the event's timezone, whatever the viewer's preference.
	 *
	 * @since TBD
	 *
	 * @covers ::single
	 * @covers ::find
	 * @covers ::on_day
	 *
	 * @return void
	 */
	public function test_keys_ignore_the_viewer_timezone(): void {
		$post_id = $this->event();
		$viewer  = static fn() => 'UTC';

		add_filter( 'gatherpress_timezone', $viewer );

		try {
			$occurrence = $this->occurrences( $post_id )->find( '20261211T200000' );
			$on_day     = $this->occurrences( $post_id )->on_day( $this->utc( '2026-12-11' ) );
		} finally {
			remove_filter( 'gatherpress_timezone', $viewer );
		}

		$this->assertNotNull( $occurrence, 'The event-local key still finds the date.' );
		$this->assertSame( 'America/New_York', $occurrence->start->getTimezone()->getName() );
		$this->assertSame( '20261211T200000', $on_day->recurrence_id, 'The event-local date still finds it.' );
	}

	/**
	 * A moved occurrence keeps its original key and is found on the day it now starts.
	 *
	 * @since TBD
	 *
	 * @covers ::on_day
	 *
	 * @return void
	 */
	public function test_on_day_finds_a_moved_occurrence_by_its_start(): void {
		$post_id = $this->event();
		$zone    = new DateTimeZone( 'America/New_York' );
		$moved   = new Occurrence(
			$post_id,
			'20261211T200000',
			new DateTimeImmutable( '2026-12-13 20:00:00', $zone ),
			new DateTimeImmutable( '2026-12-13 22:00:00', $zone )
		);

		add_filter( 'gatherpress_event_occurrences', static fn() => array( $moved ) );

		$this->assertSame( $moved, $this->occurrences( $post_id )->on_day( $this->utc( '2026-12-13' ) ) );
		$this->assertNull(
			$this->occurrences( $post_id )->on_day( $this->utc( '2026-12-11' ) ),
			'The original date no longer has it.'
		);
	}

	/**
	 * Add a rule row to an event.
	 *
	 * @since TBD
	 *
	 * @param int                  $post_id The event post ID.
	 * @param array<string, mixed> $changes Fields to replace or add.
	 *
	 * @return void
	 */
	protected function add_rule( int $post_id, array $changes = array() ): void {
		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		add_post_meta(
			$post_id,
			Rules::META_KEY,
			array_merge(
				array(
					'starts_on' => '2026-12-11',
					'rrule'     => 'FREQ=MONTHLY;BYDAY=2FR',
					'times'     => array( '20:00' ),
					'timezone'  => 'America/New_York',
					'duration'  => 120,
				),
				$changes
			)
		);
	}

	/**
	 * With rules, a range holds every date they produce, merged in start order.
	 *
	 * @since TBD
	 *
	 * @covers ::in_range
	 * @covers ::find
	 * @covers ::compare_start
	 * @covers ::merge
	 * @covers ::status
	 *
	 * @return void
	 */
	public function test_in_range_with_rules(): void {
		$post_id = $this->event();

		$this->add_rule( $post_id );
		$this->add_rule(
			$post_id,
			array(
				'starts_on' => '2026-12-14',
				'rrule'     => 'FREQ=WEEKLY;BYDAY=MO',
				'ends_on'   => '2026-12-28',
			)
		);
		// The same dates as the first rule: counted once.
		$this->add_rule( $post_id );
		// Same wall-clock key in another timezone: a different date, kept.
		$this->add_rule(
			$post_id,
			array(
				'rrule'    => null,
				'timezone' => 'Europe/Berlin',
			)
		);

		$keys = array_map(
			static fn( Occurrence $o ): string => $o->recurrence_id . ' ' . $o->start->getTimezone()->getName(),
			$this->occurrences( $post_id )->in_range( $this->utc( '2026-12-01' ), $this->utc( '2027-01-15' ) )
		);

		$this->assertSame(
			array(
				'20261211T200000 Europe/Berlin',
				'20261211T200000 America/New_York',
				'20261214T200000 America/New_York',
				'20261221T200000 America/New_York',
				'20261228T200000 America/New_York',
				'20270108T200000 America/New_York',
			),
			$keys
		);
		$first = $this->occurrences( $post_id )->in_range( $this->utc( '2026-12-01' ), $this->utc( '2027-01-15' ) )[0];

		$this->assertSame( 'scheduled', $first->status, 'Each date takes the event status.' );
		$this->assertSame(
			'Europe/Berlin',
			$this->occurrences( $post_id )->find( '20261211T200000' )->start->getTimezone()->getName(),
			'A key two rules share names the earlier date, as primary() would pick.'
		);
	}

	/**
	 * With rules, find looks a key up in them, not in the saved dates.
	 *
	 * @since TBD
	 *
	 * @covers ::find
	 *
	 * @return void
	 */
	public function test_find_with_rules(): void {
		$post_id = $this->event();

		$this->add_rule(
			$post_id,
			array(
				'starts_on' => '2027-01-04',
				'rrule'     => 'FREQ=WEEKLY;BYDAY=MO',
			)
		);
		$this->add_rule( $post_id, array( 'starts_on' => '2027-01-08' ) );

		$this->assertSame(
			'20270208T200000',
			$this->occurrences( $post_id )->find( '20270208T200000' )->recurrence_id
		);
		$this->assertSame(
			'20270212T200000',
			$this->occurrences( $post_id )->find( '20270212T200000' )->recurrence_id,
			'A key only the second rule produces.'
		);
		$this->assertNull(
			$this->occurrences( $post_id )->find( '20261211T200000' ),
			'The saved date is not read once the event has rules.'
		);
	}

	/**
	 * With rules, the primary occurrence is the next one, or the last once all have ended.
	 *
	 * @since TBD
	 *
	 * @covers ::primary
	 * @covers ::primary_from_rules
	 * @covers ::rules
	 *
	 * @return void
	 */
	public function test_primary_with_rules(): void {
		$post_id = $this->event();

		$this->add_rule( $post_id, array( 'ends_on' => '2027-03-31' ) );
		$this->add_rule(
			$post_id,
			array(
				'starts_on' => '2027-01-04',
				'ends_on'   => '2027-01-31',
				'rrule'     => 'FREQ=WEEKLY;BYDAY=MO',
			)
		);

		$occurrences = $this->occurrences( $post_id );
		$rules       = ( new Event( $post_id ) )->rules()->all();
		$at          = static fn( string $moment ): ?string => Utility::invoke_hidden_method(
			$occurrences,
			'primary_from_rules',
			array( $rules, new DateTimeImmutable( $moment, new DateTimeZone( 'UTC' ) ) )
		)?->recurrence_id;

		$this->assertSame( '20270104T200000', $at( '2026-12-20' ), 'The earliest upcoming date across the rules.' );
		$this->assertSame( '20270108T200000', $at( '2027-01-05 12:00' ) );
		$this->assertSame( '20270312T200000', $at( '2027-06-01' ), 'Once every date has ended, the last one.' );
		$this->assertNotNull( $occurrences->primary(), 'The public method looks from now.' );
	}

	/**
	 * A range returns at most MAX_OCCURRENCES, and gap-day duplicates count once.
	 *
	 * @since TBD
	 *
	 * @covers ::in_range
	 * @covers ::merge
	 *
	 * @return void
	 */
	public function test_in_range_caps_and_merges(): void {
		$capped = $this->event();

		$this->add_rule(
			$capped,
			array(
				'starts_on' => '2026-01-01',
				'rrule'     => 'FREQ=DAILY',
				'times'     => array( '09:00', '20:00' ),
			)
		);

		$this->assertCount(
			Occurrences::MAX_OCCURRENCES,
			$this->occurrences( $capped )->in_range( $this->utc( '2026-01-01' ), $this->utc( '2028-01-01' ) )
		);

		$gap = $this->event();

		$this->add_rule(
			$gap,
			array(
				'starts_on' => '2026-03-08',
				'rrule'     => null,
				'times'     => array( '02:30', '03:30' ),
			)
		);

		$this->assertCount(
			1,
			$this->occurrences( $gap )->in_range( $this->utc( '2026-03-08' ), $this->utc( '2026-03-09' ) ),
			'Two showtimes on one instant are one date.'
		);
	}
}
