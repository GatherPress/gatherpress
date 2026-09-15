<?php
/**
 * Class handles unit tests for the recurrence half of the calendar export.
 *
 * Every assertion here is driven through a real request: `go_to()` the endpoint
 * URL, then `Calendar\Setup::get_ics_body()`, which is what the `.ics` template
 * calls. Calling `Calendar::get_ical_event_string()` directly would skip
 * `Recurrence\Rewrite::parse_request()` and `Recurrence\Context::sync()`, and
 * those two are where the series-versus-occurrence decision is actually made.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Calendar\Cache as Calendar_Cache;
use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Calendar\Revision;
use GatherPress\Core\Calendar\Setup as Calendar_Setup;
use GatherPress\Core\Calendar\Timezone_Component;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rewrite;
use GatherPress\Core\Event\Recurrence\Timezone_Guard;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;
use GatherPress\Tests\Core\Event\Recurrence\Occurrence_Fixtures;
use GatherPress\Tests\Core\Event\Recurrence\Rewrite_State;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Calendar_Recurrence.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Calendar
 * @group              endpoints
 *
 * @since 0.36.0
 */
class Test_Calendar_Recurrence extends Base {

	use Occurrence_Fixtures;
	use Rewrite_State;

	/**
	 * Named timezone every fixture in this file is authored in.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	const TIMEZONE = 'America/New_York';

	/**
	 * Wall-clock start every fixture in this file uses.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	const WALL_START = '18:00:00';

	/**
	 * How far the fixture anchor sits from now.
	 *
	 * Behind now for every test in this file, as `anchor()` shows. That is the
	 * steady state of a live series: every recurring series is in it from the
	 * moment its second date arrives, and it is the shape the aggregate feeds
	 * have to work in. `Recurrence\Query::expand_event_clauses()` is what makes
	 * the `upcoming` bucket select on the series' next scheduled *occurrence*
	 * rather than on its anchor, so a series whose anchor has passed is still
	 * in every aggregate feed.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	protected string $anchor_offset = '-10 days';

	/**
	 * Start every test from an empty occurrence table, and snapshot the
	 * rewrite state.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		Context::flush_resolved();
		Context::get_instance()->clear();

		$this->snapshot_rewrite_state();
	}

	/**
	 * Put the rewrite state back the way this file found it.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->restore_rewrite_state();

		parent::tearDown();
	}

	/**
	 * Turn on pretty permalinks and register every rewrite rule the calendar
	 * and recurrence subsystems own.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function enable_pretty_permalinks(): void {
		global $wp_rewrite;

		update_option( 'permalink_structure', '/%postname%/' );
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		do_action( 'init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		Calendar_Setup::get_instance()->register_endpoints();
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();
	}

	/**
	 * The anchor every recurring fixture in this file is built from.
	 *
	 * Deliberately **behind** now, with later occurrences ahead of it. That is
	 * what makes the series assertions non-vacuous: `Rewrite::parse_request()`
	 * injects the *next upcoming* occurrence into a bare series URL's query
	 * vars, so an anchor that is itself the next upcoming occurrence
	 * makes "DTSTART is the anchor" and "DTSTART is whatever the request
	 * resolved to" the same string, and the test passes with the series feed
	 * silently narrowed to one date.
	 *
	 * Relative to now rather than pinned for the same reason: that resolution
	 * compares occurrence rows against the clock, so a pinned anchor would stop
	 * being behind it, or its successors would stop being ahead of it, and the
	 * distinction above would decay back into a coincidence.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The anchor start, in the fixture timezone.
	 */
	protected function anchor(): DateTimeImmutable {
		$timezone = new DateTimeZone( self::TIMEZONE );
		$date     = ( new DateTimeImmutable( 'now', $timezone ) )
			->modify( $this->anchor_offset )
			->format( 'Y-m-d' );

		return new DateTimeImmutable( $date . ' ' . self::WALL_START, $timezone );
	}

	/**
	 * The recurrence identifier of the nth occurrence of the weekly fixture.
	 *
	 * Derived from the anchor by weekly arithmetic rather than read back out of
	 * the occurrence table, so the expectation states what the rule means
	 * instead of repeating what the projector wrote.
	 *
	 * @since 0.36.0
	 *
	 * @param int $index Zero-based occurrence index.
	 *
	 * @return string The `Ymd\THis` identifier.
	 */
	protected function occurrence_id( int $index ): string {
		return $this->anchor()->modify( sprintf( '+%d days', 7 * $index ) )->format( 'Ymd' )
			. 'T'
			. str_replace( ':', '', self::WALL_START );
	}

	/**
	 * The RFC 5545 two-letter weekday code of the anchor's own weekday.
	 *
	 * @since 0.36.0
	 *
	 * @return string One of `SU` through `SA`.
	 */
	protected function byday(): string {
		return strtoupper( substr( $this->anchor()->format( 'D' ), 0, 2 ) );
	}

	/**
	 * Create a projected weekly series repeating on the anchor's own weekday.
	 *
	 * @since 0.36.0
	 *
	 * @param array $overrides Rule values to merge over the weekly default.
	 *
	 * @return int The series post ID.
	 */
	protected function create_weekly_series( array $overrides = array() ): int {
		$anchor = $this->anchor();
		$rule   = array_merge(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'count',
				'count'     => 5,
			),
			$overrides
		);

		return $this->create_relative_recurring_event(
			$rule,
			$anchor,
			$anchor->modify( '+2 hours' ),
			self::TIMEZONE
		);
	}

	/**
	 * Create a published, non-recurring event with a pinned datetime range.
	 *
	 * Pinned deliberately: this fixture feeds pure input-to-output assertions
	 * about the emitted VEVENT text and is never compared against the clock.
	 *
	 * @since 0.36.0
	 *
	 * @param string $timezone Named identifier or fixed offset for the event.
	 *
	 * @return int The event post ID.
	 */
	protected function create_plain_event( string $timezone = self::TIMEZONE ): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Sample Event',
				'post_name'   => 'sample-event',
				'post_status' => 'publish',
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2030-06-15 14:30:00',
				'datetime_end'   => '2030-06-15 16:30:00',
				'timezone'       => $timezone,
			)
		);

		return (int) $post_id;
	}

	/**
	 * Create a published, non-recurring event carrying an author-chosen title.
	 *
	 * The companion to `create_plain_event()` for the tests that prove author
	 * text cannot steer the timezone definition: the same pinned datetimes,
	 * with the title under the test's control.
	 *
	 * @since 0.36.0
	 *
	 * @param string $title The post title.
	 * @param string $slug  The post slug, distinct per fixture so two bodies never share a cache scope.
	 *
	 * @return int The event post ID.
	 */
	protected function create_titled_event( string $title, string $slug ): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2030-06-15 14:30:00',
				'datetime_end'   => '2030-06-15 16:30:00',
				'timezone'       => self::TIMEZONE,
			)
		);

		return (int) $post_id;
	}

	/**
	 * Request a URL and return the iCal body the `.ics` template would send.
	 *
	 * @since 0.36.0
	 *
	 * @param string $url URL to request.
	 *
	 * @return string The rendered iCal payload.
	 */
	protected function body_for( string $url ): string {
		$this->go_to( $url );

		return Calendar_Setup::get_instance()->get_ics_body();
	}

	/**
	 * The iCal download URL of one event's series.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Event post ID.
	 *
	 * @return string The `/ical/` endpoint URL.
	 */
	protected function series_ical_url( int $post_id ): string {
		return trailingslashit( (string) Context::get_instance()->series_permalink( $post_id ) )
			. Calendar_Setup::ICAL_SLUG . '/';
	}

	/**
	 * The iCal download URL of one occurrence.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier.
	 *
	 * @return string The occurrence-scoped `/ical/` endpoint URL.
	 */
	protected function occurrence_ical_url( int $post_id, string $recurrence_id ): string {
		return add_query_arg(
			array( Context::QUERY_VAR => $recurrence_id ),
			$this->series_ical_url( $post_id )
		);
	}

	/**
	 * Extract the value of one property from a payload's components.
	 *
	 * The `VTIMEZONE` definitions are stripped first. They are part of the same
	 * `VCALENDAR` and carry `DTSTART` and `RRULE` properties of their own, which
	 * describe when a *timezone* changes offset and have nothing to do with when
	 * an event happens. Letting them through would answer every question in
	 * this file with two unrelated kinds of line mixed together. Use
	 * `properties_in()` to read inside a definition.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body     The iCal payload.
	 * @param string $property Property name, without parameters.
	 *
	 * @return string[] Every line for that property, whole.
	 */
	protected function lines_for( string $body, string $property ): array {
		return $this->matching_lines(
			(string) preg_replace( '/BEGIN:VTIMEZONE\r\n.*?END:VTIMEZONE\r\n/s', '', $body ),
			$property
		);
	}

	/**
	 * Every line for one property in a block of iCal text, verbatim.
	 *
	 * @since 0.36.0
	 *
	 * @param string $text     Any iCal text.
	 * @param string $property Property name, without parameters.
	 *
	 * @return string[] Every line for that property, whole.
	 */
	protected function matching_lines( string $text, string $property ): array {
		return array_values(
			array_filter(
				explode( "\r\n", $text ),
				static function ( string $line ) use ( $property ): bool {
					return str_starts_with( $line, $property . ':' )
						|| str_starts_with( $line, $property . ';' );
				}
			)
		);
	}

	/**
	 * A recurring series' own feed emits one component carrying a
	 * timezone-qualified start and the recurrence rule, and does not enumerate.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::recurrence_lines
	 * @covers ::series_timezone
	 * @covers ::datetime_lines
	 *
	 * @return void
	 */
	public function test_series_feed_emits_one_component_with_a_tzid_start_and_an_rrule(): void {
		$post_id = $this->create_weekly_series();

		$this->enable_pretty_permalinks();

		$body = $this->body_for( $this->series_ical_url( $post_id ) );

		$this->assertSame(
			1,
			substr_count( $body, 'BEGIN:VEVENT' ),
			'A series feed must emit one component, not one per occurrence.'
		);
		$this->assertSame(
			array( sprintf( 'DTSTART;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 0 ) ) ),
			$this->lines_for( $body, 'DTSTART' ),
			'DTSTART must be the first projected occurrence, qualified by the named timezone.'
				. ' This fixture repeats on the anchor\'s own weekday, so the two coincide here;'
				. ' test_dtstart_names_a_date_the_rule_produces() is where they do not.'
		);
		$this->assertSame(
			array( 'RRULE:FREQ=WEEKLY;BYDAY=' . $this->byday() ),
			array_map(
				static function ( string $line ): string {
					return preg_replace( '/;COUNT=\d+$/', '', $line );
				},
				$this->lines_for( $body, 'RRULE' )
			),
			'The feed must carry the recurrence rule.'
		);
		$this->assertSame(
			array( 'RRULE:FREQ=WEEKLY;BYDAY=' . $this->byday() . ';COUNT=5' ),
			$this->lines_for( $body, 'RRULE' ),
			'The rule must carry the authored COUNT.'
		);
		$this->assertSame(
			array( sprintf( 'UID:gatherpress_%d', $post_id ) ),
			$this->lines_for( $body, 'UID' ),
			'A series component keeps the series UID.'
		);
		$this->assertSame(
			array(),
			$this->lines_for( $body, 'RECURRENCE-ID' ),
			'A series component carries no RECURRENCE-ID.'
		);
	}

	/**
	 * Canceled occurrences appear as `EXDATE`, derived from the rows rather
	 * than written into the rule.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::recurrence_lines
	 * @covers ::exdate_line
	 *
	 * @return void
	 */
	public function test_canceled_occurrences_are_emitted_as_exdate(): void {
		$post_id = $this->create_weekly_series();

		Occurrences::get_instance()->set_status( $post_id, $this->occurrence_id( 1 ), Occurrences::STATUS_CANCELED );
		Occurrences::get_instance()->set_status( $post_id, $this->occurrence_id( 3 ), Occurrences::STATUS_CANCELED );

		$this->enable_pretty_permalinks();

		$body = $this->body_for( $this->series_ical_url( $post_id ) );

		$this->assertSame(
			array(
				sprintf(
					'EXDATE;TZID=%s:%s,%s',
					self::TIMEZONE,
					$this->occurrence_id( 1 ),
					$this->occurrence_id( 3 )
				),
			),
			$this->lines_for( $body, 'EXDATE' ),
			'Canceled occurrences must appear as exclusions on the series component.'
		);
		$this->assertSame(
			array( 'RRULE:FREQ=WEEKLY;BYDAY=' . $this->byday() . ';COUNT=5' ),
			$this->lines_for( $body, 'RRULE' ),
			'Cancellation is occurrence state: the stored rule must be untouched by it.'
		);
	}

	/**
	 * A series with no canceled occurrences emits no `EXDATE` line at all.
	 *
	 * @covers ::exdate_line
	 *
	 * @return void
	 */
	public function test_a_series_with_no_cancellations_emits_no_exdate(): void {
		$post_id = $this->create_weekly_series();

		$this->enable_pretty_permalinks();

		$this->assertSame(
			array(),
			$this->lines_for( $this->body_for( $this->series_ical_url( $post_id ) ), 'EXDATE' ),
			'An empty exclusion set must emit no EXDATE property rather than an empty one.'
		);
	}

	/**
	 * An open-ended series emits a rule with no end bound and still does not
	 * enumerate its occurrences.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::recurrence_lines
	 *
	 * @return void
	 */
	public function test_open_ended_series_emits_no_end_bound_and_does_not_enumerate(): void {
		$post_id = $this->create_weekly_series(
			array(
				'end_type' => 'never',
				'count'    => 0,
			)
		);

		$this->enable_pretty_permalinks();

		$body = $this->body_for( $this->series_ical_url( $post_id ) );

		$this->assertSame(
			array( 'RRULE:FREQ=WEEKLY;BYDAY=' . $this->byday() ),
			$this->lines_for( $body, 'RRULE' ),
			'An open-ended rule must carry neither UNTIL nor COUNT.'
		);
		$this->assertSame(
			1,
			substr_count( $body, 'BEGIN:VEVENT' ),
			'An open-ended series cannot be enumerated, so the feed must stay at one component.'
		);
	}

	/**
	 * Two occurrences downloaded individually share the series' identifier and
	 * are told apart by `RECURRENCE-ID`.
	 *
	 * RFC 5545 section 3.8.4.7: the `UID` references the *entire* recurrence
	 * set, and `RECURRENCE-ID` identifies one instance within it. A download of
	 * one date is therefore an override of that instance, and its identity is
	 * the `(UID, RECURRENCE-ID)` tuple, which is what makes two individually
	 * downloaded occurrences distinguishable.
	 *
	 * Minting a per-occurrence `UID` instead satisfies the letter of "their
	 * identifiers differ" and breaks the requirement it was written for: a
	 * component carrying an identifier the series does not use is not an
	 * override of anything, and a client shows it as a second event sitting on
	 * top of the date the rule already produced.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::recurrence_lines
	 * @covers ::uid
	 *
	 * @return void
	 */
	public function test_two_occurrences_downloaded_individually_share_the_series_uid(): void {
		$post_id = $this->create_weekly_series();

		$this->enable_pretty_permalinks();

		$series = $this->body_for( $this->series_ical_url( $post_id ) );
		$first  = $this->body_for( $this->occurrence_ical_url( $post_id, $this->occurrence_id( 1 ) ) );
		$second = $this->body_for( $this->occurrence_ical_url( $post_id, $this->occurrence_id( 2 ) ) );

		$this->assertSame(
			array( sprintf( 'UID:gatherpress_%d', $post_id ) ),
			$this->lines_for( $series, 'UID' ),
			'Failed to read the series identifier the occurrence downloads have to match.'
		);
		$this->assertSame(
			$this->lines_for( $series, 'UID' ),
			$this->lines_for( $first, 'UID' ),
			'An occurrence download identifies the recurrence set it belongs to, so it carries the series UID.'
		);
		$this->assertSame(
			$this->lines_for( $series, 'UID' ),
			$this->lines_for( $second, 'UID' ),
			'The second occurrence carries the same identifier, for the same reason.'
		);
		$this->assertSame(
			array( sprintf( 'RECURRENCE-ID;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 1 ) ) ),
			$this->lines_for( $first, 'RECURRENCE-ID' ),
			'RECURRENCE-ID is what selects the instance inside the set the UID names.'
		);
		$this->assertSame(
			array( sprintf( 'RECURRENCE-ID;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 2 ) ) ),
			$this->lines_for( $second, 'RECURRENCE-ID' ),
			'The two downloads are distinguishable, and this is the property that distinguishes them.'
		);
		$this->assertSame(
			array(),
			$this->lines_for( $series, 'RECURRENCE-ID' ),
			'The series component describes the whole set, so it names no instance.'
		);
		$this->assertSame(
			array( sprintf( 'DTSTART;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 2 ) ) ),
			$this->lines_for( $second, 'DTSTART' ),
			'The second occurrence must start on its own date, not on the anchor.'
		);
		$this->assertSame(
			array(),
			$this->lines_for( $second, 'RRULE' ),
			'An override describes one date and carries no rule of its own.'
		);
	}

	/**
	 * A long `EXDATE` line is folded, and unfolds back to the exact bytes.
	 *
	 * RFC 5545 section 3.1 puts a 75-octet ceiling on a content line and defines
	 * folding as the way past it: CRLF followed by one space, which a parser
	 * removes again. `EXDATE;TZID=America/New_York:` spends 29 of those octets
	 * before the first identifier and each identifier costs 16 more, so three
	 * cancellations already exceed the limit and a series accumulates them
	 * without bound. A strict parser is entitled to reject or truncate an
	 * over-length line, which puts the canceled dates back on the calendar.
	 *
	 * Three cancellations is the smallest number that crosses the ceiling, so it
	 * is what the fixture uses: two would leave the assertion passing against an
	 * unfolded line.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::exdate_line
	 * @covers ::fold_content_line
	 *
	 * @return void
	 */
	public function test_a_long_exdate_line_is_folded_and_unfolds_byte_for_byte(): void {
		$post_id = $this->create_weekly_series();

		foreach ( array( 1, 2, 3 ) as $index ) {
			Occurrences::get_instance()->set_status(
				$post_id,
				$this->occurrence_id( $index ),
				Occurrences::STATUS_CANCELED
			);
		}

		$this->enable_pretty_permalinks();

		$body     = $this->body_for( $this->series_ical_url( $post_id ) );
		$expected = sprintf(
			'EXDATE;TZID=%s:%s,%s,%s',
			self::TIMEZONE,
			$this->occurrence_id( 1 ),
			$this->occurrence_id( 2 ),
			$this->occurrence_id( 3 )
		);

		$this->assertGreaterThan(
			75,
			strlen( $expected ),
			'The fixture must produce a line that actually exceeds the RFC ceiling, or folding is untested.'
		);
		$this->assertSame(
			array( $expected ),
			$this->lines_for( $this->unfold( $body ), 'EXDATE' ),
			'Unfolding the payload must return the exact original bytes of the exclusion line.'
		);
		$this->assertSame(
			array(),
			$this->over_length_lines( $body ),
			'No content line in the payload may exceed 75 octets, excluding its line break.'
		);
	}

	/**
	 * A `VTIMEZONE` covers the dates the components carry, not the date the
	 * request was made on.
	 *
	 * RFC 5545 section 3.6.5 resolves an instant against the observance with the
	 * last onset *before* it, and an `RRULE` generates nothing before its own
	 * `DTSTART`. A definition built from a window around `now` therefore does
	 * not define the offset of a 2020 event at all: every onset it carries
	 * postdates the event.
	 *
	 * The fixture is deliberately in the opposite daylight-saving regime from
	 * the one the suite runs in, since a January date in New York is standard
	 * time and the request cannot be. A definition that happens to describe
	 * today therefore cannot be mistaken for one that describes the event.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render_for_body
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::build
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::range_of
	 *
	 * @return void
	 */
	public function test_a_past_events_timezone_is_defined_for_the_date_it_happened(): void {
		$post_id = $this->create_plain_event();

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2020-01-15 14:30:00',
				'datetime_end'   => '2020-01-15 16:30:00',
				'timezone'       => self::TIMEZONE,
			)
		);

		$this->enable_pretty_permalinks();

		$body  = $this->body_for( $this->series_ical_url( $post_id ) );
		$block = $this->timezone_block( $body, self::TIMEZONE );

		$this->assertSame(
			array( 'DTSTART;TZID=' . self::TIMEZONE . ':20200115T143000' ),
			$this->lines_for( $body, 'DTSTART' ),
			'Failed to build a past-dated fixture; the assertions below would measure a future event.'
		);
		$this->assertNotSame( '', $block, 'The referenced zone must be defined in the same VCALENDAR.' );

		$onsets = $this->properties_in( $block, 'DTSTART' );

		$this->assertNotEmpty( $onsets, 'A definition with no onset defines nothing.' );
		$this->assertLessThanOrEqual(
			'20200115T143000',
			min( $onsets ),
			'Every instant a body carries needs an observance that begins before it, or its offset is undefined.'
		);
		$this->assertContains(
			$this->utc_offset( new DateTimeZone( self::TIMEZONE ), '2020-01-15 14:30:00' ),
			$this->properties_in( $block, 'TZOFFSETTO' ),
			'The offset the zone was actually in on the event date must be one the definition can produce.'
		);
	}

	/**
	 * One definition spans a body that reaches decades in both directions.
	 *
	 * Driven through `render_for_body()` with a synthetic body rather than
	 * through two fixtures, because no aggregate bucket holds a 2020 event and a
	 * 2035 event at the same time: `upcoming` excludes the first and `past`
	 * excludes the second. The seam under test is the one that reads the range
	 * off the assembled text, so the assembled text is what it is given.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render_for_body
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::range_of
	 *
	 * @return void
	 */
	public function test_a_definition_spans_every_date_the_body_refers_to(): void {
		$body = implode(
			"\r\n",
			array(
				'BEGIN:VEVENT',
				'DTSTART;TZID=' . self::TIMEZONE . ':20200115T143000',
				'DTEND;TZID=' . self::TIMEZONE . ':20200115T163000',
				'END:VEVENT',
				'BEGIN:VEVENT',
				'DTSTART;TZID=' . self::TIMEZONE . ':20350715T143000',
				'DTEND;TZID=' . self::TIMEZONE . ':20350715T163000',
				'END:VEVENT',
			)
		);

		$block = $this->timezone_block(
			Timezone_Component::get_instance()->render_for_body( $body ) . "\r\n",
			self::TIMEZONE
		);

		$this->assertNotSame( '', $block, 'The referenced zone must be defined.' );

		$onsets = $this->properties_in( $block, 'DTSTART' );

		$this->assertLessThanOrEqual(
			'20200115T143000',
			min( $onsets ),
			'The earliest instant in the body needs an observance that begins before it.'
		);
		$this->assertNotEmpty(
			$this->properties_in( $block, 'RRULE' ),
			'A zone whose policy is unchanged across the span is described by a rule that reaches the far end of it.'
		);
	}

	/**
	 * An open-ended series' timezone is defined for every transition the tz
	 * database knows, not only for a finite sampling window.
	 *
	 * The VEVENT rule is unbounded, so clients resolve occurrences arbitrarily
	 * far ahead against the embedded `VTIMEZONE`. RFC 5545 section 3.6.5
	 * resolves an instant against the observance with the last onset before
	 * it, so a definition that stops enumerating while the zone keeps
	 * transitioning silently shifts every later occurrence onto the last
	 * emitted offset, an hour wrong for half of each year.
	 *
	 * The zone is a real irregular one: its civil-time decisions are
	 * enumerated year by year in the tz database for decades ahead, so no
	 * single yearly rule describes the near future and a finite enumeration
	 * is the only honest prefix. The probe window sits beyond the six-year
	 * span the old fixed lookahead covered, and is relative to now, so the
	 * test keeps probing past the sampling window wherever the clock is.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render_for_body
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::range_of
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::sub_component
	 *
	 * @return void
	 */
	public function test_an_open_ended_series_timezone_is_defined_beyond_the_sampling_window(): void {
		$timezone = 'Asia/Gaza';
		$zone     = new DateTimeZone( $timezone );
		$date     = ( new DateTimeImmutable( 'now', $zone ) )->modify( '-10 days' )->format( 'Y-m-d' );
		$anchor   = new DateTimeImmutable( $date . ' 18:00:00', $zone );
		$post_id  = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'never',
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			$timezone
		);

		$this->enable_pretty_permalinks();

		$body  = $this->body_for( $this->series_ical_url( $post_id ) );
		$block = $this->timezone_block( $body, $timezone );

		$this->assertSame(
			array(
				sprintf(
					'RRULE:FREQ=WEEKLY;BYDAY=%s',
					strtoupper( substr( $anchor->format( 'D' ), 0, 2 ) )
				),
			),
			$this->lines_for( $body, 'RRULE' ),
			'Failed to build an unbounded rule, so the horizon assertions below would prove nothing.'
		);
		$this->assertNotSame( '', $block, 'The referenced zone must be defined in the same VCALENDAR.' );

		// Real transitions the tz database knows, past the old six-year window.
		// Eight years of them, so the probe crosses years whose civil-time
		// decisions are irregular and cannot be produced by any yearly rule.
		$probe_from  = time() + ( 7 * YEAR_IN_SECONDS );
		$transitions = (array) $zone->getTransitions( $probe_from, $probe_from + ( 8 * YEAR_IN_SECONDS ) );
		$changes     = array_slice( $transitions, 1 );

		$this->assertNotEmpty(
			$changes,
			'The tz database must know transitions in the probe window, or this fixture proves nothing.'
		);

		$previous = (int) ( $transitions[0]['offset'] ?? 0 );

		foreach ( $changes as $change ) {
			$onset = gmdate( 'Ymd\THis', (int) $change['ts'] + $previous );

			$this->assertTrue(
				$this->observance_covers( $block, $onset ),
				sprintf(
					'The definition must cover the %s transition; an unbounded rule paired with a'
					. ' finite definition shifts every later occurrence by the daylight offset.',
					(string) $change['time']
				)
			);

			$previous = (int) $change['offset'];
		}
	}

	/**
	 * Whether a definition resolves one onset, explicitly or by terminal rule.
	 *
	 * Explicit coverage is an observance whose `DTSTART` is the onset itself.
	 * A terminal unbounded yearly rule covers an onset after its anchor only
	 * when the rule would actually generate it: month, weekday, week-of-month
	 * position and wall clock all have to match, or the "coverage" is a rule
	 * that skips the onset and resolves it an offset wrong. That distinction
	 * is what separates a settled zone's honest terminal rule from a rule
	 * invented off a short sampling window.
	 *
	 * @since 0.36.0
	 *
	 * @param string $block A `VTIMEZONE` component.
	 * @param string $onset Local onset in `Ymd\THis` form.
	 *
	 * @return bool True when the definition accounts for the onset.
	 */
	protected function observance_covers( string $block, string $onset ): bool {
		if ( str_contains( $block, 'DTSTART:' . $onset ) ) {
			return true;
		}

		preg_match_all(
			'/DTSTART:(\d{8}T\d{6})\r\nRRULE:FREQ=YEARLY;BYMONTH=(\d+);BYDAY=(-?\d)([A-Z]{2})/',
			$block,
			$ruled,
			PREG_SET_ORDER
		);

		$moment   = (int) strtotime( substr( $onset, 0, 8 ) . ' UTC' );
		$day      = (int) gmdate( 'j', $moment );
		$last_day = (int) gmdate( 't', $moment );
		$weekday  = strtoupper( substr( gmdate( 'D', $moment ), 0, 2 ) );
		$month    = (int) gmdate( 'n', $moment );

		foreach ( $ruled as $rule ) {
			$ordinal  = (int) $rule[3];
			$position = ( -1 === $ordinal )
				? ( ( $last_day - $day ) < 7 )
				: ( ( intdiv( $day - 1, 7 ) + 1 ) === $ordinal );

			if (
				$rule[1] <= $onset
				&& $month === (int) $rule[2]
				&& $weekday === $rule[4]
				&& $position
				&& substr( $onset, -6 ) === substr( $rule[1], -6 )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Author text cannot widen the timezone definition window.
	 *
	 * `SUMMARY`, `LOCATION` and `URL` carry author-supplied text into the
	 * body, and `escape_ical_text()` leaves a date-time-shaped token in a post
	 * title intact. Reading the window off free text lets a fifteen-character
	 * token in an ordinary, non-recurring event's title inflate every public
	 * feed the event appears in: measured at 861 bytes to 1.68 MB before the
	 * scan was anchored to the properties that can carry date-times.
	 *
	 * Driven end to end through the real feed path, with the only difference
	 * between the two requests being the post title. The hostile title
	 * carries a far-past and a far-future token, either of which would move
	 * the window if free text were read.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render_for_body
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::range_of
	 *
	 * @return void
	 */
	public function test_free_text_in_a_title_cannot_widen_the_timezone_definition_window(): void {
		$this->enable_pretty_permalinks();

		$plain   = $this->create_titled_event( 'Downtown WordPress Meetup', 'plain-title-event' );
		$hostile = $this->create_titled_event(
			'Backup window 17000101T000000 to 99991231T235959',
			'hostile-title-event'
		);

		$plain_body   = $this->body_for( $this->series_ical_url( $plain ) );
		$hostile_body = $this->body_for( $this->series_ical_url( $hostile ) );

		$this->assertStringContainsString(
			'99991231T235959',
			$hostile_body,
			'Failed to carry the token into the body, so the assertions below would measure nothing.'
		);
		$this->assertSame(
			$this->timezone_block( $plain_body, self::TIMEZONE ),
			$this->timezone_block( $hostile_body, self::TIMEZONE ),
			'The definition window is derived from the date-bearing properties alone, so a title'
			. ' cannot change it.'
		);
		$this->assertLessThan(
			strlen( $plain_body ) + 512,
			strlen( $hostile_body ),
			'A token in a title must cost the feed its own bytes and nothing more.'
		);
	}

	/**
	 * Free text cannot make the feed define additional timezones.
	 *
	 * `escape_ical_text()` turns a semicolon into `\;`, which still contains
	 * the literal `;TZID=` an unanchored reference scan looks for, so a title
	 * naming valid zones added whole `VTIMEZONE` definitions nothing
	 * references. The scan reads only the properties that can legitimately
	 * carry a `TZID` parameter.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render_for_body
	 *
	 * @return void
	 */
	public function test_free_text_cannot_reference_additional_timezone_definitions(): void {
		$this->enable_pretty_permalinks();

		$post_id = $this->create_titled_event(
			'Meetup ;TZID=Europe/Berlin:x ;TZID=Asia/Tokyo:x',
			'tzid-title-event'
		);
		$body    = $this->body_for( $this->series_ical_url( $post_id ) );

		$this->assertStringContainsString(
			'TZID=Europe/Berlin',
			$body,
			'Failed to carry the token into the body, so the assertion below would measure nothing.'
		);
		$this->assertSame(
			array( self::TIMEZONE ),
			$this->declared_timezone_ids( $body ),
			'Only zones referenced by date-bearing properties are defined; free text cannot add one.'
		);
	}

	/**
	 * Two transitions on the same yearly position with different offsets are
	 * not one rule, however alike their names are.
	 *
	 * This is what a jurisdiction abolishing daylight saving looks like in
	 * tzdata: the same first-Sunday-of-November 02:00 position, changing to
	 * `-0500` in one year and to `-0400` in the next. A signature built from
	 * position alone, or from position and name, calls the pair regular and
	 * emits one unbounded yearly rule, so every date after the policy change is
	 * computed an hour wrong for as long as the subscription lives.
	 *
	 * Synthetic rather than pinned to a real identifier, because which zones
	 * exhibit this is a property of the host's tz database and changes with it:
	 * a fixture naming one would silently stop testing the defect on the next
	 * tzdata update.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::terminal_rule
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::sub_component
	 *
	 * @return void
	 */
	public function test_equal_abbreviations_with_different_offsets_are_not_regular(): void {
		$instance    = Timezone_Component::get_instance();
		$transitions = array(
			array(
				'from'          => -14400,
				'to'            => -18000,
				'abbr'          => 'EST',
				'local'         => '20261101T020000',
				'day'           => 1,
				'days_in_month' => 30,
				'month'         => 11,
				'byday'         => '1SU',
			),
			array(
				'from'          => -14400,
				'to'            => -14400,
				'abbr'          => 'EST',
				'local'         => '20271107T020000',
				'day'           => 7,
				'days_in_month' => 30,
				'month'         => 11,
				'byday'         => '1SU',
			),
		);

		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'terminal_rule', array( $transitions ) ),
			'A pair that changes to different offsets is two observances, not one repeating rule.'
		);

		$lines = Utility::invoke_hidden_method(
			$instance,
			'sub_component',
			array( 'STANDARD', $transitions )
		);

		$this->assertSame(
			2,
			count( array_keys( $lines, 'BEGIN:STANDARD', true ) ),
			'Each policy period must be written out on its own rather than collapsed into the first.'
		);
		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$lines,
					static function ( string $line ): bool {
						return str_starts_with( $line, 'RRULE:' );
					}
				)
			),
			'An unbounded rule must not be claimed for a pattern the zone stops following.'
		);
		$this->assertSame(
			array( 'TZOFFSETTO:-0500', 'TZOFFSETTO:-0400' ),
			array_values(
				array_filter(
					$lines,
					static function ( string $line ): bool {
						return str_starts_with( $line, 'TZOFFSETTO:' );
					}
				)
			),
			'Both offsets the zone actually moved to must reach the client.'
		);
	}

	/**
	 * A pair that agrees on offsets and name as well as position still collapses
	 * to one rule.
	 *
	 * The companion to the case above: widening the signature must not make
	 * every zone irregular, which would replace one rule with a sub-component
	 * per transition for every ordinary daylight-saving zone on the site.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::terminal_rule
	 *
	 * @return void
	 */
	public function test_a_genuinely_repeating_transition_is_still_regular(): void {
		$this->assertSame(
			array(
				'index' => 0,
				'byday' => '1SU',
			),
			Utility::invoke_hidden_method(
				Timezone_Component::get_instance(),
				'terminal_rule',
				array(
					array(
						array(
							'from'          => -14400,
							'to'            => -18000,
							'abbr'          => 'EST',
							'local'         => '20261101T020000',
							'day'           => 1,
							'days_in_month' => 30,
							'month'         => 11,
							'byday'         => '1SU',
						),
						array(
							'from'          => -14400,
							'to'            => -18000,
							'abbr'          => 'EST',
							'local'         => '20271107T020000',
							'day'           => 7,
							'days_in_month' => 30,
							'month'         => 11,
							'byday'         => '1SU',
						),
					),
				)
			),
			'Transitions that agree on position, offsets and name are one rule.'
		);
	}

	/**
	 * Moving occurrence rows between posts invalidates both sides.
	 *
	 * `move_to_post()` is the primitive a forward split moves rows with.
	 * It is bare SQL: no post row, no meta row and no term relationship changes,
	 * so nothing the cache watches fires by itself. A move is a write on **two**
	 * series, because the source stops carrying the dates and the destination
	 * starts, and a subscriber revalidating either against an unmoved
	 * `Last-Modified` is told `304` for a body that no longer describes it.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::move_to_post
	 * @covers \GatherPress\Core\Calendar\Cache::mark_changed_for_occurrences
	 * @covers \GatherPress\Core\Event\Recurrence\Query::invalidate_post_query_cache
	 *
	 * @return void
	 */
	public function test_moving_occurrence_rows_invalidates_both_posts(): void {
		$source = $this->create_weekly_series();
		// The destination is a bare event post with no rows of its own, which is
		// the shape a forward split creates before it moves anything into it.
		$destination = $this->create_plain_event();
		$moved_id    = $this->occurrence_id( 2 );

		$this->enable_pretty_permalinks();
		$this->backdate_calendar_stamp( HOUR_IN_SECONDS );

		$stale_stamp  = Calendar_Cache::get_instance()->get_last_modified();
		$stale_count  = Calendar_Cache::get_instance()->get_change_count();
		$stale_posts  = wp_cache_get_last_changed( 'posts' );
		$source_url   = $this->series_ical_url( $source );
		$before_rows  = Occurrences::get_instance()->select_for_series( array( $destination ) );
		$before_lines = $this->lines_for( $this->body_for( $source_url ), 'DTSTART' );

		$this->assertNotEmpty(
			$before_lines,
			'The source feed must render before the move for the comparison to mean anything.'
		);
		// Prime the request-scoped resolution memo against the ownership the
		// move is about to change. Nothing else in the request will tell it that
		// storage moved under it.
		$this->assertNull(
			Context::resolve_in_series( $destination, $moved_id ),
			'The destination cannot own the row before the move, or the memo assertion below proves nothing.'
		);

		$this->assertSame(
			1,
			Occurrences::get_instance()->move_to_post( $source, $destination, array( $moved_id ) ),
			'The fixture must actually move a row, or every assertion below passes vacuously.'
		);

		$this->assertNotSame(
			$stale_stamp,
			Calendar_Cache::get_instance()->get_last_modified(),
			'A move changes what both feeds emit and must stamp the calendar.'
		);
		$this->assertGreaterThan(
			$stale_count,
			Calendar_Cache::get_instance()->get_change_count(),
			'Both sides of the move must be announced, so the change counter moves at least once.'
		);
		$this->assertNotSame(
			$stale_posts,
			wp_cache_get_last_changed( 'posts' ),
			'Occurrence-scoped post query results are cached on the posts group and must be invalidated too.'
		);
		$this->assertCount(
			count( $before_rows ) + 1,
			Occurrences::get_instance()->select_for_series( array( $destination ) ),
			'The row must be owned by the destination after the move.'
		);
		$this->assertNotNull(
			Context::resolve_in_series( $destination, $moved_id ),
			'A resolution memoized against the old ownership must not survive the move that invalidated it.'
		);
	}

	/**
	 * A second change inside one second is not served the body the first one
	 * produced.
	 *
	 * The response cache is namespaced by `Last-Modified`, which has one-second
	 * resolution. Render, change, render again in the same validator second and
	 * a lookup keyed by the stamp alone lands on the key the first render
	 * filled, so the change is correctly absent from the stored body and
	 * correctly reported as fresh. Canceling two dates of a series is one
	 * operator action and lands well inside a second, so this is the ordinary
	 * case rather than a race.
	 *
	 * The stored validator is seeded ahead of the wall clock, which is what a
	 * burst of same-second changes leaves behind. Every stamp below then moves
	 * on from the stored value rather than from the clock, so a wall-clock
	 * tick between the renders cannot be what moves the key and the collision
	 * the test names is arranged by construction rather than won by speed.
	 *
	 * @covers \GatherPress\Core\Calendar\Cache::get_versioned_key
	 * @covers \GatherPress\Core\Calendar\Cache::get_change_count
	 * @covers \GatherPress\Core\Calendar\Cache::mark_changed
	 *
	 * @return void
	 */
	public function test_a_change_in_the_same_second_is_not_served_the_cached_body(): void {
		$post_id = $this->create_weekly_series();

		$this->enable_pretty_permalinks();

		update_option(
			Calendar_Cache::LAST_MODIFIED_OPTION,
			gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
			false
		);

		$url         = $this->series_ical_url( $post_id );
		$occurrences = Occurrences::get_instance();

		$occurrences->set_status( $post_id, $this->occurrence_id( 1 ), Occurrences::STATUS_CANCELED );

		$first     = $this->body_for( $url );
		$first_key = Calendar_Cache::get_instance()->get_versioned_key( 'probe' );

		$occurrences->set_status( $post_id, $this->occurrence_id( 2 ), Occurrences::STATUS_CANCELED );

		$second     = $this->body_for( $url );
		$second_key = Calendar_Cache::get_instance()->get_versioned_key( 'probe' );

		$this->assertNotSame(
			$first_key,
			$second_key,
			'The second cancellation must move the cache namespace, or the key the first render filled is served.'
		);
		$this->assertSame(
			array( sprintf( 'EXDATE;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 1 ) ) ),
			$this->lines_for( $this->unfold( $first ), 'EXDATE' ),
			'Failed to render the first exclusion, so the comparison below would prove nothing.'
		);
		$this->assertSame(
			array(
				sprintf(
					'EXDATE;TZID=%s:%s,%s',
					self::TIMEZONE,
					$this->occurrence_id( 1 ),
					$this->occurrence_id( 2 )
				),
			),
			$this->lines_for( $this->unfold( $second ), 'EXDATE' ),
			'The second cancellation must reach the response rather than being masked by the first render.'
		);
	}

	/**
	 * Two occurrence writes in the same second still produce strictly
	 * increasing revisions, and the second reaches the response.
	 *
	 * `SEQUENCE` is the only signal by which a client decides whether an
	 * incoming component supersedes one it already holds, and both of the
	 * obvious sources of it have one-second resolution: `post_modified_gmt`,
	 * which an occurrence write does not touch at all, and `time()`. Canceling
	 * two dates of a series is one loop and lands well inside a second, so this
	 * is the ordinary case rather than a race.
	 *
	 * The stored revision is seeded an hour past the clock, so the `time()`
	 * arm of the allocation floor stays out of reach for the whole test and a
	 * wall-clock tick between the two writes cannot be what separates the
	 * values. Any advance below can then only come from the stored value
	 * carrying the ordering, which is the property under test, stated without
	 * racing the suite's own speed against the clock.
	 *
	 * @covers \GatherPress\Core\Calendar\Revision::advance
	 * @covers \GatherPress\Core\Calendar\Revision::current
	 * @covers \GatherPress\Core\Calendar\Revision::stored
	 * @covers ::get_sequence
	 *
	 * @return void
	 */
	public function test_two_changes_in_one_second_still_raise_the_sequence(): void {
		$post_id = $this->create_weekly_series();

		$this->enable_pretty_permalinks();

		$occurrences = Occurrences::get_instance();
		$revision    = Revision::get_instance();

		update_post_meta(
			$post_id,
			Revision::META_KEY,
			(string) ( ( time() - Revision::EPOCH ) + HOUR_IN_SECONDS )
		);

		$before = $revision->current( $post_id );

		$occurrences->set_status( $post_id, $this->occurrence_id( 1 ), Occurrences::STATUS_CANCELED );

		$middle = $revision->current( $post_id );

		$occurrences->set_status( $post_id, $this->occurrence_id( 2 ), Occurrences::STATUS_CANCELED );

		$after = $revision->current( $post_id );

		$this->assertGreaterThan( $before, $middle, 'The first cancellation must advance the revision.' );
		$this->assertGreaterThan( $middle, $after, 'The second must advance it again, in the same second.' );
		$this->assertSame(
			$after,
			$this->sequence_in( $this->body_for( $this->series_ical_url( $post_id ) ) ),
			'The advanced revision is what the serialized component has to report.'
		);
	}

	/**
	 * The `SEQUENCE` value a payload's component carries.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The iCal payload.
	 *
	 * @return int The revision number.
	 */
	protected function sequence_in( string $body ): int {
		$lines = $this->lines_for( $body, 'SEQUENCE' );

		return (int) substr( (string) ( $lines[0] ?? 'SEQUENCE:0' ), strlen( 'SEQUENCE:' ) );
	}

	/**
	 * Unfold an iCal payload back to its logical content lines.
	 *
	 * RFC 5545 section 3.1: a CRLF followed by a single linear white space
	 * character is removed when the content is processed. Byte-for-byte, which
	 * is the point, because a folder that drops or duplicates an octet
	 * round-trips to something the assertion would not recognize.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The iCal payload.
	 *
	 * @return string The payload with every fold removed.
	 */
	protected function unfold( string $body ): string {
		return str_replace( array( "\r\n ", "\r\n\t" ), '', $body );
	}

	/**
	 * Every physical line of a payload longer than the RFC 5545 ceiling.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The iCal payload.
	 *
	 * @return string[] The over-length lines, if any.
	 */
	protected function over_length_lines( string $body ): array {
		return array_values(
			array_filter(
				explode( "\r\n", $body ),
				static function ( string $line ): bool {
					return strlen( $line ) > 75;
				}
			)
		);
	}

	/**
	 * The series download and a single-occurrence download of the same event do
	 * not collide in the response cache.
	 *
	 * `Setup::get_ics_cache_key()` is built from the resolved query, and the
	 * occurrence is not part of the query in the sense that key was originally
	 * written for. Without the occurrence in the key, whichever of the two
	 * was requested first would be served for the other.
	 *
	 * @covers \GatherPress\Core\Calendar\Setup::get_ics_cache_key
	 *
	 * @return void
	 */
	public function test_a_series_download_and_an_occurrence_download_do_not_share_a_cache_entry(): void {
		$post_id = $this->create_weekly_series();

		$this->enable_pretty_permalinks();

		$series     = $this->body_for( $this->series_ical_url( $post_id ) );
		$occurrence = $this->body_for( $this->occurrence_ical_url( $post_id, $this->occurrence_id( 2 ) ) );

		$this->assertNotSame(
			$series,
			$occurrence,
			'The occurrence download must not be served the cached series component.'
		);
		$this->assertSame(
			array( sprintf( 'RECURRENCE-ID;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 2 ) ) ),
			$this->lines_for( $occurrence, 'RECURRENCE-ID' ),
			'The occurrence download must name its own instance even when the series was requested first.'
		);
	}

	/**
	 * A non-recurring event's component is unchanged from today's, apart from
	 * the timezone-qualified start.
	 *
	 * Asserts the component's whole property list and its order, then the two
	 * datetime lines, then the properties whose values are stable, so a silently
	 * added or reordered property fails here.
	 *
	 * Scoped to the `VEVENT` deliberately. The component is what stays
	 * unchanged modulo the start; the enclosing `VCALENDAR` gains the
	 * `VTIMEZONE` that start's `TZID` now refers to, which is asserted
	 * separately. The shape of that definition is a property of the
	 * tz database rather than of this plugin, so pinning it here would make an
	 * unrelated tzdata update fail a test about a plain event.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::datetime_lines
	 *
	 * @return void
	 */
	public function test_non_recurring_event_output_is_unchanged_apart_from_the_start(): void {
		$post_id = $this->create_plain_event();

		$this->enable_pretty_permalinks();

		$body  = $this->body_for( $this->series_ical_url( $post_id ) );
		$lines = explode(
			"\r\n",
			substr(
				$body,
				(int) strpos( $body, 'BEGIN:VEVENT' ),
				(int) strpos( $body, 'END:VEVENT' ) + strlen( 'END:VEVENT' ) - (int) strpos( $body, 'BEGIN:VEVENT' )
			)
		);

		$this->assertSame(
			array( 'America/New_York' ),
			$this->declared_timezone_ids( $body ),
			'The named timezone the start references is defined in the same VCALENDAR (RFC 5545 section 3.2.19).'
		);
		$this->assertSame(
			array(
				'BEGIN',
				'URL',
				'DTSTART',
				'DTEND',
				'DTSTAMP',
				'LAST-MODIFIED',
				'SEQUENCE',
				'SUMMARY',
				'DESCRIPTION',
				'LOCATION',
				'UID',
				'END',
			),
			array_map(
				static function ( string $line ): string {
					return preg_split( '/[;:]/', $line )[0];
				},
				$lines
			),
			'A non-recurring event must emit exactly the property list it emits today, in the same order.'
		);
		$this->assertSame(
			array( 'DTSTART;TZID=America/New_York:20300615T143000' ),
			$this->lines_for( $body, 'DTSTART' ),
			'The start is the one thing that changes: local wall clock, qualified by the named timezone.'
		);
		$this->assertSame(
			array( 'DTEND;TZID=America/New_York:20300615T163000' ),
			$this->lines_for( $body, 'DTEND' ),
			'The end follows the start.'
		);
		$this->assertSame(
			array( sprintf( 'UID:gatherpress_%d', $post_id ) ),
			$this->lines_for( $body, 'UID' ),
			'A non-recurring event keeps its post-ID-derived UID.'
		);
		$this->assertSame(
			array( 'SUMMARY:Sample Event' ),
			$this->lines_for( $body, 'SUMMARY' ),
			'Nothing else about the component moves.'
		);
	}

	/**
	 * An event whose timezone is a fixed offset keeps today's bare UTC start
	 * and is never given a recurrence rule.
	 *
	 * A `TZID` must name a tz-database identifier, and RFC 5545 forbids
	 * attaching an `RRULE` to a start that carries no timezone reference for
	 * anything but a fixed-offset series. A recurring event is always kept on a
	 * named timezone, so this is the defensive arm rather than an authored one.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::series_timezone
	 * @covers ::datetime_lines
	 * @covers ::recurrence_lines
	 *
	 * @return void
	 */
	public function test_a_fixed_offset_timezone_keeps_the_bare_utc_start_and_no_rule(): void {
		$post_id = $this->create_weekly_series();

		update_post_meta( $post_id, 'gatherpress_timezone', 'UTC+5' );

		$this->enable_pretty_permalinks();

		$body = $this->body_for( $this->series_ical_url( $post_id ) );

		$this->assertSame(
			array( 'DTSTART:' . $this->anchor()->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' ) ),
			$this->lines_for( $body, 'DTSTART' ),
			'A fixed-offset timezone cannot be named in a TZID, so the start stays in the bare UTC form.'
		);
		$this->assertSame(
			array(),
			$this->lines_for( $body, 'RRULE' ),
			'An RRULE must never be attached to a start carrying no timezone reference.'
		);
	}

	/**
	 * Every aggregate feed carries the recurrence rule for a series.
	 *
	 * This is where GatherPress exceeds the WordCamp.org implementation, whose
	 * aggregate feeds omit recurring series entirely.
	 *
	 * Runs on the file's default anchor, which sits **behind** now. That is not
	 * an incidental fixture choice: a series whose anchor has passed is every
	 * recurring series from its second date onward, so it is the steady state,
	 * and selecting the `upcoming` bucket from the anchor rather than from the
	 * next scheduled occurrence drops the whole series out of every aggregate
	 * feed. Moving the anchor ahead of now would make this test pass against
	 * that defect.
	 *
	 * @covers ::get_ical_event_string
	 * @covers \GatherPress\Core\Calendar\Setup::get_ical_list
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_aggregate_feeds_carry_the_recurrence_rule(): void {
		$post_id  = $this->create_weekly_series();
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Brooklyn Office',
				'post_name'   => 'brooklyn-office',
				'post_status' => 'publish',
			)
		);

		$topic_id = $this->factory->term->create(
			array(
				'taxonomy' => Topic::TAXONOMY,
				'name'     => 'Weekly Meetups',
				'slug'     => 'weekly-meetups',
			)
		);

		wp_set_post_terms( $post_id, '_brooklyn-office', Venue::TAXONOMY );
		wp_set_object_terms( $post_id, array( (int) $topic_id ), Topic::TAXONOMY );

		$this->enable_pretty_permalinks();

		$expected = 'RRULE:FREQ=WEEKLY;BYDAY=' . $this->byday() . ';COUNT=5';
		$feeds    = array(
			'site-wide'         => home_url( '/feed/' . Calendar_Setup::ICAL_SLUG . '/' ),
			'post type archive' => trailingslashit( (string) get_post_type_archive_link( Event::POST_TYPE ) )
				. 'feed/' . Calendar_Setup::ICAL_SLUG . '/',
			'venue'             => trailingslashit( (string) get_permalink( $venue_id ) )
				. 'feed/' . Calendar_Setup::ICAL_SLUG . '/',
			'taxonomy term'     => trailingslashit( (string) get_term_link( (int) $topic_id, Topic::TAXONOMY ) )
				. 'feed/' . Calendar_Setup::ICAL_SLUG . '/',
		);

		foreach ( $feeds as $name => $url ) {
			$body = $this->body_for( $url );
			$uids = $this->lines_for( $body, 'UID' );

			$this->assertSame(
				array( $expected ),
				$this->lines_for( $body, 'RRULE' ),
				sprintf( 'The %s feed must carry the recurrence rule for the series.', $name )
			);
			$this->assertSame(
				1,
				substr_count( $body, 'BEGIN:VEVENT' ),
				sprintf( 'The %s feed must describe the series with one component, not enumerate it.', $name )
			);
			$this->assertSame(
				array( sprintf( 'UID:gatherpress_%d', $post_id ) ),
				$uids,
				sprintf( 'The %s feed must carry the series UID exactly once.', $name )
			);
			$this->assertSame(
				$uids,
				array_values( array_unique( $uids ) ),
				sprintf( 'No two components in the %s feed may share a UID (RFC 5545 section 3.8.4.7).', $name )
			);
		}
	}

	/**
	 * An aggregate feed holding several series de-duplicates to one component
	 * per series, each with its own unique identifier.
	 *
	 * The companion to the test above, and the one that would catch the
	 * opposite defect: making the `upcoming` bucket occurrence-aware by
	 * *expanding* the query rather than folding it emits one VEVENT per
	 * occurrence, all of them sharing the series UID. Two series and a plain
	 * event together, so a per-occurrence expansion produces a component count
	 * no per-series total can coincide with.
	 *
	 * @covers \GatherPress\Core\Calendar\Setup::get_ical_list
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 *
	 * @return void
	 */
	public function test_an_aggregate_feed_emits_one_component_per_series_not_one_per_occurrence(): void {
		$first  = $this->create_weekly_series();
		$second = $this->create_weekly_series();
		$plain  = $this->create_upcoming_event( self::TIMEZONE );

		$this->enable_pretty_permalinks();

		$body = $this->body_for( home_url( '/feed/' . Calendar_Setup::ICAL_SLUG . '/' ) );
		$uids = $this->lines_for( $body, 'UID' );

		sort( $uids );

		$expected = array(
			sprintf( 'UID:gatherpress_%d', $first ),
			sprintf( 'UID:gatherpress_%d', $plain ),
			sprintf( 'UID:gatherpress_%d', $second ),
		);

		sort( $expected );

		$this->assertSame(
			3,
			substr_count( $body, 'BEGIN:VEVENT' ),
			'Two series and one plain event are three components: one per series, not one per occurrence.'
		);
		$this->assertSame(
			$expected,
			$uids,
			'Each series contributes exactly one component, carrying its own series UID.'
		);
		$this->assertSame(
			2,
			count( $this->lines_for( $body, 'RRULE' ) ),
			'Each of the two series carries its own rule; the plain event carries none.'
		);
	}

	/**
	 * Canceling an occurrence reaches a subscribed client rather than being
	 * held behind the response cache and a stale `Last-Modified`.
	 *
	 * The transient is the lesser half. `Last-Modified` is served from the
	 * calendar stamp, and `is_not_modified()` answers true whenever the client's
	 * timestamp is at or past it, so a client revalidating with
	 * `If-Modified-Since` and no stored entity tag gets a 304 for as long as the
	 * stamp stays put. `Occurrences::set_status()` is a bare `UPDATE`; unless it
	 * stamps the calendar, the canceled date may never reach the subscriber.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::set_status
	 * @covers \GatherPress\Core\Calendar\Cache::mark_changed_for_occurrences
	 * @covers \GatherPress\Core\Calendar\Setup::is_not_modified
	 *
	 * @return void
	 */
	public function test_canceling_an_occurrence_invalidates_the_calendar_cache(): void {
		$post_id = $this->create_weekly_series();

		$this->enable_pretty_permalinks();

		// The stamp has one-second resolution and the production write is
		// `current_time( 'mysql', true )`, so a stamp written twice inside one
		// test run is the same string. Backdating the stored value before the
		// first request is how the move is measured without a wall-clock sleep;
		// it stands in for the ordinary case of a subscriber holding a response
		// rendered at some earlier moment.
		$this->backdate_calendar_stamp( MINUTE_IN_SECONDS );

		$stale  = Calendar_Cache::get_instance()->get_last_modified();
		$url    = $this->series_ical_url( $post_id );
		$before = $this->body_for( $url );

		$this->assertSame(
			array(),
			$this->lines_for( $before, 'EXDATE' ),
			'Nothing is canceled yet, so the first response must carry no exclusions.'
		);

		Occurrences::get_instance()->set_status(
			$post_id,
			$this->occurrence_id( 1 ),
			Occurrences::STATUS_CANCELED
		);

		$moved = Calendar_Cache::get_instance()->get_last_modified();

		$this->assertNotSame(
			$stale,
			$moved,
			'Canceling an occurrence must stamp the calendar, or every cached body stays reachable.'
		);
		$this->assertSame(
			array( sprintf( 'EXDATE;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 1 ) ) ),
			$this->lines_for( $this->body_for( $url ), 'EXDATE' ),
			'The next request must render the exclusion rather than replay the cached body.'
		);

		// A subscriber holding the pre-cancellation response revalidates with
		// `If-Modified-Since` and no entity tag. Before the stamp moved this
		// returned 304 indefinitely.
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s', strtotime( $stale . ' GMT' ) ) . ' GMT';

		$not_modified = Calendar_Setup::get_instance()->is_not_modified( '"whatever"', $moved );

		unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );

		$this->assertFalse(
			$not_modified,
			'A client holding the pre-cancellation response must be told the calendar changed, not sent a 304.'
		);
	}

	/**
	 * A second cancellation is never answered 304 against the first one's
	 * validator, however close together the two land.
	 *
	 * `Last-Modified` has one-second resolution. A client that revalidated
	 * right after the first change holds that second as its snapshot; when the
	 * second change lands inside the same second and rewrites the stamp with
	 * the same value, `is_not_modified()` answers true and the client keeps a
	 * body missing the second change until some unrelated write happens to
	 * move the stamp, which may be never.
	 *
	 * The stored validator is seeded ahead of the wall clock, which is what a
	 * burst of same-second changes leaves behind. That makes the scenario
	 * deterministic instead of a race against the suite's own speed: a stamp
	 * that only reports the clock can never advance past the seed, whatever
	 * the timing, while a strictly monotonic one advances on every change.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::set_status
	 * @covers \GatherPress\Core\Calendar\Cache::mark_changed_for_occurrences
	 * @covers \GatherPress\Core\Calendar\Cache::mark_changed
	 * @covers \GatherPress\Core\Calendar\Setup::is_not_modified
	 *
	 * @return void
	 */
	public function test_a_second_change_in_one_validator_second_is_not_answered_304(): void {
		$post_id = $this->create_weekly_series();

		update_option(
			Calendar_Cache::LAST_MODIFIED_OPTION,
			gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
			false
		);

		$occurrences = Occurrences::get_instance();

		$occurrences->set_status( $post_id, $this->occurrence_id( 1 ), Occurrences::STATUS_CANCELED );

		// The client revalidated after the first change and holds its stamp.
		$held = Calendar_Cache::get_instance()->get_last_modified();

		$occurrences->set_status( $post_id, $this->occurrence_id( 2 ), Occurrences::STATUS_CANCELED );

		$moved = Calendar_Cache::get_instance()->get_last_modified();

		$this->assertGreaterThan(
			strtotime( $held . ' GMT' ),
			strtotime( $moved . ' GMT' ),
			'The second change must move the validator past the snapshot the client holds.'
		);

		// No stored entity tag, so the timestamp is the whole decision.
		unset( $_SERVER['HTTP_IF_NONE_MATCH'] );
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s', strtotime( $held . ' GMT' ) ) . ' GMT';

		$not_modified = Calendar_Setup::get_instance()->is_not_modified( '"whatever"', $moved );

		unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );

		$this->assertFalse(
			$not_modified,
			'A client whose snapshot predates the second change must not be told 304.'
		);
	}

	/**
	 * Projecting a series' occurrence rows stamps the calendar too.
	 *
	 * `save_post` covers the editor path, but the top-up cron writes occurrence
	 * rows with no post save anywhere near it, so new dates appear in the
	 * aggregate feeds with nothing to invalidate the bodies that omit them.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::upsert_occurrences
	 * @covers \GatherPress\Core\Calendar\Cache::mark_changed_for_occurrences
	 *
	 * @return void
	 */
	public function test_projecting_occurrence_rows_stamps_the_calendar(): void {
		$post_id = $this->create_weekly_series();

		$this->backdate_calendar_stamp( HOUR_IN_SECONDS );

		$stale = Calendar_Cache::get_instance()->get_last_modified();

		Occurrences::get_instance()->project( $post_id );

		$this->assertNotSame(
			$stale,
			Calendar_Cache::get_instance()->get_last_modified(),
			'An occurrence write outside a post save must still stamp the calendar.'
		);
	}

	/**
	 * Deleting a series' occurrence rows stamps the calendar too.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::delete_for_post
	 * @covers \GatherPress\Core\Calendar\Cache::mark_changed_for_occurrences
	 *
	 * @return void
	 */
	public function test_deleting_occurrence_rows_stamps_the_calendar(): void {
		$post_id = $this->create_weekly_series();

		$this->backdate_calendar_stamp( HOUR_IN_SECONDS );

		$stale = Calendar_Cache::get_instance()->get_last_modified();

		$this->assertGreaterThan(
			0,
			Occurrences::get_instance()->delete_for_post( $post_id ),
			'The fixture must actually delete rows, or the assertion below proves nothing.'
		);
		$this->assertNotSame(
			$stale,
			Calendar_Cache::get_instance()->get_last_modified(),
			'Removing every occurrence of a series changes what the feeds emit and must stamp the calendar.'
		);
	}

	/**
	 * The exclusion list is read across every post the series spans.
	 *
	 * A series is never assumed to be one post. Under a forward split a
	 * canceled date living on a sibling post would otherwise drop silently out
	 * of the feed. This is proved the way the other series-wide read call sites
	 * in this suite are proved, by installing the `gatherpress_series_post_ids`
	 * filter and canceling the date on the post the filter adds.
	 *
	 * @covers ::exdate_line
	 *
	 * @return void
	 */
	public function test_exdate_covers_every_post_the_series_spans(): void {
		$post_id = $this->create_weekly_series();
		$sibling = $this->create_weekly_series();

		// The cancellation lives only on the sibling. Reading the series as one
		// post finds nothing to exclude.
		Occurrences::get_instance()->set_status(
			$sibling,
			$this->occurrence_id( 2 ),
			Occurrences::STATUS_CANCELED
		);

		add_filter(
			'gatherpress_series_post_ids',
			static function ( array $post_ids, int $resolved ) use ( $post_id, $sibling ): array {
				return ( $resolved === $post_id ) ? array( $post_id, $sibling ) : $post_ids;
			},
			10,
			2
		);

		$this->enable_pretty_permalinks();

		// Scoped to the requested post's own component. The export now carries
		// one component per post of the series, and the sibling's component
		// excludes the same date on its own account, so reading the whole body
		// would count that second, correct line as a duplicate.
		$this->assertSame(
			array( sprintf( 'EXDATE;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 2 ) ) ),
			$this->matching_lines(
				$this->component_for( $this->body_for( $this->series_ical_url( $post_id ) ), $post_id ),
				'EXDATE'
			),
			'A date canceled on a sibling post of the same series must still be excluded from the feed.'
		);
	}

	/**
	 * Extract one post's `VEVENT` out of a payload.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body    The iCal payload.
	 * @param int    $post_id The post whose component to return.
	 *
	 * @return string The component text, or '' when the body carries no such component.
	 */
	protected function component_for( string $body, int $post_id ): string {
		$matches = array();

		preg_match_all( '/BEGIN:VEVENT\r\n(.*?)END:VEVENT/s', $body, $matches );

		foreach ( $matches[1] as $component ) {
			if ( str_contains( $component, sprintf( "UID:gatherpress_%d\r\n", $post_id ) ) ) {
				return $component;
			}
		}

		return '';
	}

	/**
	 * Every named timezone a feed references is defined inside the same
	 * `VCALENDAR`, and nothing else is.
	 *
	 * RFC 5545 section 3.2.19: a `TZID` parameter refers to a `VTIMEZONE` in the
	 * same calendar object. Emitting `DTSTART;TZID=` with no such component is
	 * what makes the output invalid, so the definition travels with it. One per
	 * *distinct* identifier, so two events in one zone share a definition.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render_for_body
	 * @covers \GatherPress\Core\Calendar\Setup::get_ical_wrap
	 *
	 * @return void
	 */
	public function test_every_named_timezone_a_feed_references_is_defined_in_it(): void {
		$this->create_upcoming_event( self::TIMEZONE );
		$this->create_upcoming_event( self::TIMEZONE );
		$this->create_upcoming_event( 'Europe/Berlin' );
		$this->create_upcoming_event( 'UTC+5' );

		$this->enable_pretty_permalinks();

		$body = $this->body_for( home_url( '/feed/' . Calendar_Setup::ICAL_SLUG . '/' ) );

		$this->assertSame(
			4,
			substr_count( $body, 'BEGIN:VEVENT' ),
			'All four fixtures must be in the feed, or the timezone assertions below are vacuous.'
		);
		$this->assertSame(
			array( self::TIMEZONE, 'Europe/Berlin' ),
			$this->declared_timezone_ids( $body ),
			'One VTIMEZONE per distinct named identifier the components reference, and no others.'
		);
		$this->assertSame(
			array( self::TIMEZONE, 'Europe/Berlin' ),
			$this->referenced_timezone_ids( $body ),
			'The fixed-offset event contributes no TZID, so it must contribute no VTIMEZONE either.'
		);
		$this->assertLessThan(
			strpos( $body, 'BEGIN:VEVENT' ),
			strpos( $body, 'BEGIN:VTIMEZONE' ),
			'The definitions precede the components that reference them.'
		);
	}

	/**
	 * A zone that observes daylight saving emits both sub-components, each with
	 * the offsets it moves between and a rule describing when it moves.
	 *
	 * The offsets are cross-checked against the tz database directly rather than
	 * against a pasted expectation, so the assertion states the requirement,
	 * that the emitted offsets are this zone's real offsets, instead of encoding
	 * whatever the generator produced.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render
	 *
	 * @return void
	 */
	public function test_a_daylight_saving_zone_emits_standard_and_daylight_with_a_rule(): void {
		$this->create_upcoming_event( self::TIMEZONE );

		$this->enable_pretty_permalinks();

		$block = $this->timezone_block(
			$this->body_for( home_url( '/feed/' . Calendar_Setup::ICAL_SLUG . '/' ) ),
			self::TIMEZONE
		);
		$zone  = new DateTimeZone( self::TIMEZONE );
		$year  = ( new DateTimeImmutable( 'now', $zone ) )->format( 'Y' );

		$this->assertSame(
			1,
			substr_count( $block, 'BEGIN:STANDARD' ),
			'A zone with regular transitions describes its standard time once, with a rule.'
		);
		$this->assertSame(
			1,
			substr_count( $block, 'BEGIN:DAYLIGHT' ),
			'And its daylight time once.'
		);
		$this->assertSame(
			2,
			substr_count( $block, "\r\nRRULE:FREQ=YEARLY;" ),
			'Both transitions recur yearly, so both carry a rule rather than being enumerated.'
		);
		$this->assertSame(
			array( 'FREQ=YEARLY;BYMONTH=11;BYDAY=1SU', 'FREQ=YEARLY;BYMONTH=3;BYDAY=2SU' ),
			$this->properties_in( $block, 'RRULE' ),
			'The US rule moves the clocks on the first Sunday of November and the second Sunday of March.'
		);
		$this->assertSame(
			array(
				$this->utc_offset( $zone, $year . '-01-15 12:00:00' ),
				$this->utc_offset( $zone, $year . '-07-01 12:00:00' ),
			),
			$this->properties_in( $block, 'TZOFFSETTO' ),
			'The offsets moved to are the zone\'s real standard and daylight offsets, in that order.'
		);
		$this->assertSame(
			array(
				$this->utc_offset( $zone, $year . '-07-01 12:00:00' ),
				$this->utc_offset( $zone, $year . '-01-15 12:00:00' ),
			),
			$this->properties_in( $block, 'TZOFFSETFROM' ),
			'And each transition moves away from the other one\'s offset.'
		);
		$this->assertCount(
			2,
			$this->properties_in( $block, 'TZNAME' ),
			'Each sub-component names the abbreviation it applies.'
		);
	}

	/**
	 * A zone whose transitions are legislated in UTC and on the *last* weekday
	 * of the month is described that way.
	 *
	 * Two things this file's `America/New_York` case cannot reach, both stated
	 * facts about the European Union rule (Directive 2000/84/EC) rather than
	 * values read back out of the generator:
	 *
	 * 1. Both transitions fall on the **last** Sunday of their month, so the
	 *    rule's `BYDAY` is `-1SU`. Counting forward from the first Sunday
	 *    instead gives `4SU` or `5SU` depending on the year, a rule that is
	 *    wrong outright in some years and right by accident in others. The US
	 *    rule is "second Sunday" and "first Sunday", so it never exercises this.
	 * 2. Both transitions happen at 01:00 UTC, and RFC 5545 section 3.6.5 writes
	 *    a sub-component's `DTSTART` in the offset the zone is *leaving*. Spring
	 *    forward leaves CET, so 01:00 UTC is 02:00 there; autumn back leaves
	 *    CEST, so the same 01:00 UTC is 03:00. Reading the offset from the wrong
	 *    side of the change swaps those two, which is invisible in any zone
	 *    whose transitions are pinned to local time, as the US ones are, at
	 *    02:00 local both ways.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render
	 *
	 * @return void
	 */
	public function test_a_zone_transitioning_on_the_last_sunday_is_described_as_such(): void {
		$this->create_upcoming_event( 'Europe/Berlin' );

		$this->enable_pretty_permalinks();

		$block = $this->timezone_block(
			$this->body_for( home_url( '/feed/' . Calendar_Setup::ICAL_SLUG . '/' ) ),
			'Europe/Berlin'
		);

		$this->assertSame(
			array( 'FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', 'FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU' ),
			$this->properties_in( $block, 'RRULE' ),
			'The EU rule moves the clocks on the last Sunday of October and of March.'
		);
		$this->assertSame(
			array( '030000', '020000' ),
			array_map(
				static function ( string $value ): string {
					return substr( $value, -6 );
				},
				$this->properties_in( $block, 'DTSTART' )
			),
			'Each transition is timed in the offset it leaves: 01:00 UTC is 03:00 CEST going back, 02:00 CET forward.'
		);
	}

	/**
	 * A named zone that never changes offset emits one standard sub-component
	 * and no rule.
	 *
	 * It still needs the definition: the identifier is named in a `TZID`
	 * parameter, and a `TZID` that resolves to nothing is the violation this
	 * whole component exists to close. A zone with no transitions in range is
	 * also the branch that would silently emit an empty `VTIMEZONE`.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render
	 *
	 * @return void
	 */
	public function test_a_named_zone_without_daylight_saving_emits_one_standard_and_no_rule(): void {
		$this->create_upcoming_event( 'Asia/Kolkata' );

		$this->enable_pretty_permalinks();

		$block = $this->timezone_block(
			$this->body_for( home_url( '/feed/' . Calendar_Setup::ICAL_SLUG . '/' ) ),
			'Asia/Kolkata'
		);

		$this->assertSame(
			1,
			substr_count( $block, 'BEGIN:STANDARD' ),
			'A zone that never changes offset is one standard sub-component.'
		);
		$this->assertSame(
			0,
			substr_count( $block, 'BEGIN:DAYLIGHT' ),
			'And no daylight one.'
		);
		$this->assertStringNotContainsString(
			"\r\nRRULE:",
			$block,
			'Nothing recurs, so nothing carries a rule.'
		);
		$this->assertSame(
			array( '+0530' ),
			$this->properties_in( $block, 'TZOFFSETTO' ),
			'The single offset is the zone\'s only offset.'
		);
		$this->assertSame(
			array( '+0530' ),
			$this->properties_in( $block, 'TZOFFSETFROM' ),
			'A zone with no transition moves from its own offset to itself.'
		);
	}

	/**
	 * A fixed-offset event emits no `TZID` and therefore no `VTIMEZONE`.
	 *
	 * @covers \GatherPress\Core\Calendar\Timezone_Component::render_for_body
	 *
	 * @return void
	 */
	public function test_a_fixed_offset_event_emits_no_timezone_component(): void {
		$post_id = $this->create_plain_event( 'UTC+5' );

		$this->enable_pretty_permalinks();

		$body = $this->body_for( $this->series_ical_url( $post_id ) );

		$this->assertStringNotContainsString(
			'BEGIN:VTIMEZONE',
			$body,
			'A start with no timezone reference needs nothing to resolve, so none is emitted.'
		);
		$this->assertSame(
			array( 'DTSTART:20300615T093000Z' ),
			$this->lines_for( $body, 'DTSTART' ),
			'And the start keeps the bare UTC form it has always had.'
		);
	}

	/**
	 * Move the stored calendar stamp back, standing in for the passage of time.
	 *
	 * The stamp has one-second resolution, so two writes inside one test run
	 * produce the same string and a "did it move" assertion cannot fail. Rather
	 * than sleep, the stored value is aged before the write under test, which is
	 * the same situation from the production write's point of view.
	 *
	 * @since 0.36.0
	 *
	 * @param int $seconds How far back to move it.
	 *
	 * @return void
	 */
	protected function backdate_calendar_stamp( int $seconds ): void {
		update_option(
			Calendar_Cache::LAST_MODIFIED_OPTION,
			gmdate(
				'Y-m-d H:i:s',
				strtotime( Calendar_Cache::get_instance()->get_last_modified() . ' GMT' ) - $seconds
			),
			false
		);
	}

	/**
	 * Create a published, non-recurring event starting a month from now.
	 *
	 * Relative rather than pinned: every caller puts it in an aggregate feed,
	 * whose `upcoming` bucket compares it against the clock.
	 *
	 * @since 0.36.0
	 *
	 * @param string $timezone Named identifier or fixed offset for the event.
	 *
	 * @return int The event post ID.
	 */
	protected function create_upcoming_event( string $timezone ): int {
		$zone  = Timezone_Guard::is_named( $timezone ) ? new DateTimeZone( $timezone ) : new DateTimeZone( 'UTC' );
		$start = ( new DateTimeImmutable( 'now', $zone ) )->modify( '+30 days' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => $start->format( 'Y-m-d H:i:s' ),
				'datetime_end'   => $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
				'timezone'       => $timezone,
			)
		);

		return (int) $post_id;
	}

	/**
	 * The tz-database identifiers a body's `VTIMEZONE` components define.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The iCal payload.
	 *
	 * @return string[] The identifiers, sorted.
	 */
	protected function declared_timezone_ids( string $body ): array {
		$ids = array_map(
			static function ( string $line ): string {
				return substr( $line, strlen( 'TZID:' ) );
			},
			array_values(
				array_filter(
					explode( "\r\n", $body ),
					static function ( string $line ): bool {
						return str_starts_with( $line, 'TZID:' );
					}
				)
			)
		);

		sort( $ids );

		return $ids;
	}

	/**
	 * The tz-database identifiers a body's components reference in a parameter.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The iCal payload.
	 *
	 * @return string[] The identifiers, unique and sorted.
	 */
	protected function referenced_timezone_ids( string $body ): array {
		preg_match_all( '/;TZID=([^:;\r\n]+)/', $body, $matches );

		$ids = array_values( array_unique( $matches[1] ) );

		sort( $ids );

		return $ids;
	}

	/**
	 * The `VTIMEZONE` component defining one identifier, whole.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The iCal payload.
	 * @param string $tzid The identifier whose definition to return.
	 *
	 * @return string The component text, or '' when the body defines no such zone.
	 */
	protected function timezone_block( string $body, string $tzid ): string {
		preg_match_all( '/BEGIN:VTIMEZONE\r\n.*?END:VTIMEZONE/s', $body, $matches );

		foreach ( $matches[0] as $block ) {
			if ( str_contains( $block, "\r\nTZID:" . $tzid . "\r\n" ) ) {
				return $block;
			}
		}

		return '';
	}

	/**
	 * Every value a component carries for one property, in order.
	 *
	 * @since 0.36.0
	 *
	 * @param string $block    Component text.
	 * @param string $property Property name.
	 *
	 * @return string[] The values, in the order they appear.
	 */
	protected function properties_in( string $block, string $property ): array {
		return array_map(
			static function ( string $line ) use ( $property ): string {
				return substr( $line, strlen( $property ) + 1 );
			},
			$this->matching_lines( $block, $property )
		);
	}

	/**
	 * One zone's UTC offset at one moment, in the RFC 5545 `+HHMM` form.
	 *
	 * Read from the tz database rather than from the code under test, so the
	 * expectations above cannot be satisfied by the generator agreeing with
	 * itself.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeZone $zone   The zone to measure.
	 * @param string       $moment Local `Y-m-d H:i:s` moment to measure at.
	 *
	 * @return string The offset, e.g. `-0400`.
	 */
	protected function utc_offset( DateTimeZone $zone, string $moment ): string {
		$seconds = $zone->getOffset( new DateTimeImmutable( $moment, $zone ) );

		return sprintf(
			'%s%02d%02d',
			( $seconds < 0 ) ? '-' : '+',
			intdiv( abs( $seconds ), HOUR_IN_SECONDS ),
			intdiv( abs( $seconds ) % HOUR_IN_SECONDS, MINUTE_IN_SECONDS )
		);
	}

	/**
	 * A non-recurring event on a site that *does* have recurring events still
	 * gets no rule.
	 *
	 * The site-wide flag is a cheap gate, not the answer: whether a given event
	 * repeats is a property of that event.
	 *
	 * @covers ::recurrence_lines
	 *
	 * @return void
	 */
	public function test_a_plain_event_on_a_recurring_site_gets_no_rule(): void {
		$this->create_weekly_series();

		$plain_id = $this->create_plain_event();

		$this->enable_pretty_permalinks();

		$this->assertTrue(
			Recurrence_Query::site_has_recurring_events(),
			'The fixture must leave the site flagged as having recurring events for this to test anything.'
		);
		$this->assertSame(
			array(),
			$this->lines_for( $this->body_for( $this->series_ical_url( $plain_id ) ), 'RRULE' ),
			'An event with no rule of its own carries no RRULE, however many other events do.'
		);
	}

	/**
	 * Direct coverage for `current_occurrence()`'s three return paths.
	 *
	 * Xdebug does not reliably trace a private helper reached through a short
	 * same-class delegation, so each arm gets its own reflection invoke.
	 *
	 * @covers ::current_occurrence
	 *
	 * @return void
	 */
	public function test_current_occurrence_direct_invoke_covers_every_path(): void {
		$post_id = $this->create_weekly_series();
		$other   = $this->create_plain_event();

		Context::get_instance()->clear();

		$this->assertNull(
			Utility::invoke_hidden_method( new Calendar( $post_id ), 'current_occurrence' ),
			'Outside occurrence context a component describes the series.'
		);

		Context::get_instance()->set( $post_id, $this->occurrence_id( 1 ) );

		$this->assertNull(
			Utility::invoke_hidden_method( new Calendar( $other ), 'current_occurrence' ),
			'An occurrence belongs to its own series post and may not be claimed by another.'
		);
		$this->assertSame(
			$this->occurrence_id( 1 ),
			Utility::invoke_hidden_method( new Calendar( $post_id ), 'current_occurrence' )['recurrence_id'],
			'The series post the occurrence belongs to describes that occurrence.'
		);
	}

	/**
	 * Every calendar entry point this change touches issues no query
	 * against the occurrence table, and writes no option, on a site whose
	 * `gatherpress_has_recurring_events` flag is `'0'`.
	 *
	 * Driven through the real feed URLs rather than through the serializer, so
	 * the guard is measured where a request actually reaches it.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::recurrence_lines
	 *
	 * @return void
	 */
	public function test_calendar_entry_points_cost_nothing_when_the_site_has_no_recurring_events(): void {
		global $wpdb;

		$post_id  = $this->create_plain_event();
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Brooklyn Office',
				'post_name'   => 'brooklyn-office',
				'post_status' => 'publish',
			)
		);

		$topic_id = $this->factory->term->create(
			array(
				'taxonomy' => Topic::TAXONOMY,
				'name'     => 'Weekly Meetups',
				'slug'     => 'weekly-meetups',
			)
		);

		wp_set_post_terms( $post_id, '_brooklyn-office', Venue::TAXONOMY );
		wp_set_object_terms( $post_id, array( (int) $topic_id ), Topic::TAXONOMY );

		$this->enable_pretty_permalinks();

		update_option( Recurrence_Query::HAS_RECURRING_OPTION, '0', true );

		$urls = array(
			$this->series_ical_url( $post_id ),
			home_url( '/feed/' . Calendar_Setup::ICAL_SLUG . '/' ),
			trailingslashit( (string) get_post_type_archive_link( Event::POST_TYPE ) )
				. 'feed/' . Calendar_Setup::ICAL_SLUG . '/',
			trailingslashit( (string) get_permalink( $venue_id ) ) . 'feed/' . Calendar_Setup::ICAL_SLUG . '/',
			trailingslashit( (string) get_term_link( (int) $topic_id, Topic::TAXONOMY ) )
				. 'feed/' . Calendar_Setup::ICAL_SLUG . '/',
		);

		wp_cache_delete( 'alloptions', 'options' );
		$options_before = wp_load_alloptions();
		$before         = count( $wpdb->queries );

		foreach ( $urls as $url ) {
			$this->body_for( $url );
		}

		$captured = array_column( array_slice( $wpdb->queries, $before ), 0 );

		wp_cache_delete( 'alloptions', 'options' );
		$options_after = wp_load_alloptions();

		$this->assertNotEmpty(
			$captured,
			'Failed to capture any queries; SAVEQUERIES must be on for this assertion to mean anything.'
		);
		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$captured,
					static function ( string $sql ) use ( $wpdb ): bool {
						return str_contains( $sql, $wpdb->prefix . 'gatherpress_event_occurrences' );
					}
				)
			),
			'No calendar entry point may reach the occurrence table on a site with no recurring events.'
		);
		// The guarded property is the set of autoloaded options and their
		// values. The row order of the unordered SELECT behind
		// `wp_load_alloptions()` is not a contract, and unrelated writes
		// elsewhere in the suite can shift it between the two snapshots.
		ksort( $options_before );
		ksort( $options_after );

		$this->assertSame(
			$options_before,
			$options_after,
			'No calendar entry point may write an autoloaded option on a site with no recurring events.'
		);
	}

	/**
	 * The flag, not the absence of a rule, is what keeps a calendar request off
	 * the occurrence table.
	 *
	 * The sibling test above drives a plain event, which has no rule
	 * mirrors, so it stays off the occurrence table whether or not the guard
	 * exists, and deleting the guard leaves it green. Removing that coincidence
	 * takes an event that *does* carry rule mirrors while the flag says the
	 * site has none: the option is recomputed from storage on every lifecycle
	 * event, so the two being out of step is a bug state rather than an
	 * authored one, and it is exactly the state the guard is there for.
	 *
	 * @covers ::recurrence_lines
	 * @covers ::exdate_line
	 *
	 * @return void
	 */
	public function test_the_flag_is_what_keeps_a_recurring_event_off_the_occurrence_table(): void {
		global $wpdb;

		// Both requests below are for the same URL, so the response cache would
		// serve the first body for the second and there would be no second
		// request to measure. Turning the cache off makes both of them real.
		add_filter( 'gatherpress_calendar_max_age', '__return_zero' );

		$post_id = $this->create_weekly_series();

		$this->enable_pretty_permalinks();

		Occurrences::get_instance()->set_status(
			$post_id,
			$this->occurrence_id( 1 ),
			Occurrences::STATUS_CANCELED
		);

		$url = $this->series_ical_url( $post_id );

		$this->assertSame(
			array( sprintf( 'EXDATE;TZID=%s:%s', self::TIMEZONE, $this->occurrence_id( 1 ) ) ),
			$this->lines_for( $this->body_for( $url ), 'EXDATE' ),
			'The fixture must reach the occurrence table while the flag is on, or the guard test proves nothing.'
		);

		update_option( Recurrence_Query::HAS_RECURRING_OPTION, '0', true );

		$before = count( $wpdb->queries );
		$body   = $this->body_for( $url );

		$this->assertSame(
			array(),
			array_values(
				array_filter(
					array_column( array_slice( $wpdb->queries, $before ), 0 ),
					static function ( string $sql ) use ( $wpdb ): bool {
						return str_contains( $sql, $wpdb->prefix . 'gatherpress_event_occurrences' );
					}
				)
			),
			'A calendar request must not reach the occurrence table while the flag says the site has none.'
		);
		$this->assertSame(
			array(),
			$this->lines_for( $body, 'RRULE' ),
			'And it must not read the rule mirrors either.'
		);
	}
	/**
	 * `DTSTART` names a date the rule actually produces.
	 *
	 * RFC 5545 section 3.8.5.3 makes `DTSTART` part of the recurrence set, and
	 * nothing couples the series anchor's weekday to the rule's `weekdays`:
	 * neither `Rule::from_array()` nor the editor panel requires them to agree.
	 * Emitting the anchor therefore handed subscribers a date the site does not
	 * list, on a weekday `Expander::matches()` excludes, and which cannot be
	 * canceled because no occurrence row exists to cancel. Under `COUNT`, which
	 * is what these fixtures use, it is worse than an extra date: a client
	 * counting `DTSTART` as instance 1 of `COUNT=5` emits four rule dates
	 * instead of five, so the export both adds a date the site lacks and drops
	 * one it has.
	 *
	 * The round-trip suite cannot see this, because it expands both sides with
	 * GatherPress's own `Expander`, which shares the exclusion, and because
	 * every other fixture here sets `weekdays` to the anchor's own weekday. So
	 * this fixture deliberately does not.
	 *
	 * @covers ::datetime_lines
	 * @covers ::first_projected_occurrence
	 *
	 * @return void
	 */
	public function test_dtstart_names_a_date_the_rule_produces(): void {
		$anchor = $this->anchor();

		// Two days off the anchor's own weekday, so the anchor is not a date
		// this rule can produce.
		$rule_weekday = ( ( (int) $anchor->format( 'w' ) ) + 2 ) % 7;
		$post_id      = $this->create_weekly_series( array( 'weekdays' => array( $rule_weekday ) ) );

		$this->enable_pretty_permalinks();

		$body     = $this->body_for( $this->series_ical_url( $post_id ) );
		$starts   = $this->lines_for( $body, 'DTSTART' );
		$prefix   = sprintf( 'DTSTART;TZID=%s:', self::TIMEZONE );
		$expected = wp_list_pluck(
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'recurrence_id'
		);

		$this->assertCount( 1, $starts, 'Failed to assert the series component carries one DTSTART.' );
		$this->assertNotEmpty( $expected, 'Failed to assert the fixture projected any occurrences.' );

		$emitted = substr( $starts[0], strlen( $prefix ) );

		$this->assertContains(
			$emitted,
			$expected,
			'Failed to assert DTSTART names one of the occurrences the site actually lists.'
		);
		$this->assertNotSame(
			$anchor->format( 'Ymd\THis' ),
			$emitted,
			'Failed to assert DTSTART moved off an anchor the rule does not produce. If this fails'
				. ' with the others passing, the fixture stopped putting the anchor off the rule.'
		);
	}

	/**
	 * `DTSTART` is this post's own first occurrence, not the series'.
	 *
	 * The occurrence read behind `DTSTART` deliberately does not resolve the
	 * series, and this is the case that separates the two. `EXDATE` does
	 * resolve it, because a series that has been spread across several posts
	 * still excludes every date it canceled whichever post holds the row.
	 * `DTSTART` is the opposite: it has to agree with the `RRULE` emitted
	 * beside it, and that rule is read from this post alone.
	 *
	 * Reading series-wide gave every post in a multi-post series the same
	 * earliest date, so two components opened on the same instant and a
	 * subscriber merging them held duplicates. The forward split that creates
	 * that shape arrives in a later branch; `gatherpress_series_post_ids` is
	 * the documented seam it uses, so the shape is reachable here through the
	 * filter.
	 *
	 * @covers ::datetime_lines
	 * @covers ::first_projected_occurrence
	 *
	 * @return void
	 */
	public function test_dtstart_is_this_posts_own_first_occurrence(): void {
		$earlier = $this->create_weekly_series();

		$this->anchor_offset = '+30 days';

		$later = $this->create_weekly_series();

		$this->assertNotSame(
			$earlier,
			$later,
			'Fixture setup: the two posts must be distinct.'
		);

		// One logical series spanning both posts, which is what a forward
		// split produces and what this filter exists to express.
		$series = array( $earlier, $later );

		add_filter(
			'gatherpress_series_post_ids',
			static function () use ( $series ): array {
				return $series;
			}
		);

		$this->enable_pretty_permalinks();

		$body = $this->body_for( $this->series_ical_url( $later ) );

		// Read the component belonging to this post rather than the first one
		// in the body. A branch that emits one component per post in the series
		// is a later addition to this stack, and the invariant here is
		// per-component: whichever component describes this post opens on one
		// of this post's own dates.
		$blocks    = array_filter(
			explode( 'BEGIN:VEVENT', $body ),
			static function ( string $block ) use ( $later ): bool {
				return str_contains( $block, sprintf( 'URL:%s', get_permalink( $later ) ) );
			}
		);
		$component = (string) reset( $blocks );
		$starts    = $this->matching_lines( $component, 'DTSTART' );
		$prefix    = sprintf( 'DTSTART;TZID=%s:', self::TIMEZONE );
		$own       = wp_list_pluck(
			Occurrences::get_instance()->select_for_series( array( $later ) ),
			'recurrence_id'
		);
		$sibling   = wp_list_pluck(
			Occurrences::get_instance()->select_for_series( array( $earlier ) ),
			'recurrence_id'
		);

		$this->assertNotEmpty( $blocks, 'Failed to find a component for this post in the feed.' );
		$this->assertCount( 1, $starts, 'Failed to assert this post\'s component carries one DTSTART.' );
		$this->assertNotEmpty( $own, 'Fixture setup: the later post must have projected rows.' );
		$this->assertNotEmpty( $sibling, 'Fixture setup: the earlier post must have projected rows.' );

		$emitted = substr( $starts[0], strlen( $prefix ) );

		$this->assertContains(
			$emitted,
			$own,
			'Failed to assert DTSTART names one of this post\'s own occurrences.'
		);
		$this->assertNotContains(
			$emitted,
			$sibling,
			'Failed to assert DTSTART did not fall through to a sibling post\'s earlier date.'
		);
	}

	/**
	 * Each arm of the first-occurrence guard answers null.
	 *
	 * Invoked directly because xdebug does not trace a private helper reached
	 * through a same-class delegation: the guard's `return null` runs on every
	 * request for a non-recurring event and still records as uncovered.
	 *
	 * The four arms are not interchangeable. A component with no named
	 * timezone emits no `RRULE` at all, so its `DTSTART` is not part of any
	 * recurrence set and belongs on the anchor. A single occurrence's
	 * component carries a `RECURRENCE-ID` and names its own date. A site with
	 * no recurring events must not pay the read. And a post with no rule of
	 * its own has no rule for a `DTSTART` to agree with, however many other
	 * events on the site do.
	 *
	 * @covers ::first_projected_occurrence
	 *
	 * @return void
	 */
	public function test_the_first_occurrence_guard_refuses_each_way(): void {
		$post_id  = $this->create_weekly_series();
		$instance = new Calendar( $post_id );
		$row      = Occurrences::get_instance()->select_bounded_occurrence( array( $post_id ), true );

		$this->assertNotNull( $row, 'Fixture setup: the series must have an upcoming occurrence.' );

		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'first_projected_occurrence', array( '', null ) ),
			'A component with no named timezone emits no RRULE, so DTSTART stays on the anchor.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method(
				$instance,
				'first_projected_occurrence',
				array( self::TIMEZONE, $row )
			),
			'A single occurrence\'s component carries a RECURRENCE-ID and names its own date.'
		);

		$this->assertNotNull(
			Utility::invoke_hidden_method(
				$instance,
				'first_projected_occurrence',
				array( self::TIMEZONE, null )
			),
			'Fixture setup: with both arguments usable the helper resolves a row.'
		);

		update_option( Recurrence_Query::HAS_RECURRING_OPTION, '0', true );

		$this->assertNull(
			Utility::invoke_hidden_method(
				$instance,
				'first_projected_occurrence',
				array( self::TIMEZONE, null )
			),
			'A site with no recurring events must not pay the read.'
		);

		update_option( Recurrence_Query::HAS_RECURRING_OPTION, '1', true );

		$plain = new Calendar( $this->create_plain_event() );

		$this->assertNull(
			Utility::invoke_hidden_method(
				$plain,
				'first_projected_occurrence',
				array( self::TIMEZONE, null )
			),
			'A post with no rule of its own has no rule for a DTSTART to agree with.'
		);
	}
}
