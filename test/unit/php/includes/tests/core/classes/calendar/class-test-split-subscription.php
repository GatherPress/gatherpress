<?php
/**
 * Class handles unit tests for what a forward split owes an existing subscriber.
 *
 * The subscription is a URL somebody added to Apple Calendar or Outlook before
 * anybody split anything, and no protocol exists by which that client learns a
 * second post now holds half the dates. Everything here is therefore asserted
 * from the subscriber's side of the wire: the bytes the stable `/ical/` URL
 * returns, the validators that come with them, and what a client that merges
 * components by `UID` and `SEQUENCE` ends up holding.
 *
 * Two rules govern the assertions.
 *
 * **The fragments' instants must be disjoint.** A fixture where the forward
 * fragment repeats dates the origin still carries would pass an "every original
 * instant is present" assertion while losing the forward dates entirely. The
 * split here falls in the middle of a five-date rule, so the two fragments share
 * no date and the union is the only way to reach five.
 *
 * **Nothing is read below the endpoint.** The bodies come from
 * `Calendar\Setup::get_ics_body()` through a real `go_to()` of the `/ical/` URL,
 * so the cache, the query resolution and the serializer all take part.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Calendar\Cache as Calendar_Cache;
use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Calendar\Setup as Calendar_Setup;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rewrite;
use GatherPress\Core\Event\Recurrence\Series;
use GatherPress\Core\Event\Recurrence\Splitter;
use GatherPress\Tests\Base;
use GatherPress\Tests\Core\Event\Recurrence\Occurrence_Fixtures;
use GatherPress\Tests\Core\Event\Recurrence\Rewrite_State;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Split_Subscription.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Setup
 * @group              endpoints
 *
 * @since 0.36.0
 */
class Test_Split_Subscription extends Base {

	use Occurrence_Fixtures;
	use Rewrite_State;

	/**
	 * Named timezone every fixture in this file is authored in.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TIMEZONE = 'America/New_York';

	/**
	 * Identifiers of the projected occurrences, ascending.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	protected array $identifiers = array();

	/**
	 * Start every test from an empty occurrence table with pretty permalinks.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		Context::get_instance()->clear();
		Context::flush_resolved();
		Series::get_instance()->flush_memo();

		$this->snapshot_rewrite_state();
	}

	/**
	 * Put the rewrite state back the way this file found it.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Context::get_instance()->clear();
		Context::flush_resolved();
		Series::get_instance()->flush_memo();

		$this->restore_rewrite_state();

		parent::tearDown();
	}

	/**
	 * Turn on pretty permalinks and register the calendar and occurrence rules.
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
	 * The anchor every fixture here is dated from.
	 *
	 * Ten days behind now, so the series is in the steady state a subscription
	 * is usually taken out against: some dates already past, some still ahead.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The anchor start, in the fixture timezone.
	 */
	protected function anchor(): DateTimeImmutable {
		$timezone = new DateTimeZone( self::TIMEZONE );
		$date     = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-10 days' )->format( 'Y-m-d' );

		return new DateTimeImmutable( $date . ' 18:00:00', $timezone );
	}

	/**
	 * Create and project the five-date weekly series every test starts from.
	 *
	 * @since 0.36.0
	 *
	 * @return int The series post ID.
	 */
	protected function create_series(): int {
		$anchor  = $this->anchor();
		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'count',
				'count'     => 5,
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			self::TIMEZONE
		);

		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		$this->identifiers = array_map(
			'strval',
			wp_list_pluck(
				Occurrences::get_instance()->select_for_series( array( $post_id ) ),
				'recurrence_id'
			)
		);

		$this->assertCount( 5, $this->identifiers, 'Failed to project the five dates this file is written against.' );

		return $post_id;
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
	 * Split the components of a payload out by `UID`.
	 *
	 * @since 0.36.0
	 *
	 * Line breaks are normalized to `\n` so the patterns in this file can anchor
	 * on `$`. In multiline mode `$` matches before the `\n` of a CRLF pair and
	 * leaves the `\r` unmatched, which silently fails every anchored pattern.
	 *
	 * @param string $body The iCal payload.
	 *
	 * @return array<string, string> Component text, keyed by the `UID` value.
	 */
	protected function components_in( string $body ): array {
		$matches = array();

		preg_match_all( '/BEGIN:VEVENT\n(.*?)END:VEVENT/s', str_replace( "\r\n", "\n", $body ), $matches );

		$components = array();

		foreach ( $matches[1] as $component ) {
			$uid = array();

			preg_match( '/^UID:(.+)$/m', $component, $uid );

			$components[ trim( $uid[1] ?? '' ) ] = $component;
		}

		return $components;
	}

	/**
	 * Expand one component the way a subscribing client would.
	 *
	 * Deliberately narrow: it understands the weekly `COUNT` rule this file's
	 * fixture produces and nothing else, and it fails the test rather than
	 * guessing if the emitted rule ever stops being that shape. An expander that
	 * silently tolerated an unrecognized rule would answer every question here
	 * with the anchor alone.
	 *
	 * @since 0.36.0
	 *
	 * @param string $component One `VEVENT` body.
	 *
	 * @return string[] The instants it expands to, in `Ymd\THis` local form.
	 */
	protected function expand( string $component ): array {
		$start = array();

		$this->assertSame(
			1,
			preg_match( '/^DTSTART;TZID=([^:]+):(\d{8}T\d{6})$/m', $component, $start ),
			'A component must carry a timezone-qualified start for a client to expand it from.'
		);

		$anchor   = new DateTimeImmutable(
			$start[2],
			new DateTimeZone( $start[1] )
		);
		$rule     = array();
		$instants = array( $start[2] );

		if ( 1 === preg_match( '/^RRULE:(.+)$/m', $component, $rule ) ) {
			$count = array();

			$this->assertSame(
				1,
				preg_match( '/^FREQ=WEEKLY;BYDAY=[A-Z]{2};COUNT=(\d+)$/', trim( $rule[1] ), $count ),
				'This client only expands the weekly COUNT rule the fixture emits: ' . trim( $rule[1] )
			);

			$instants = array();

			for ( $index = 0; $index < (int) $count[1]; $index++ ) {
				$instants[] = $anchor->modify( sprintf( '+%d days', 7 * $index ) )->format( 'Ymd\THis' );
			}
		}

		$exdate = array();

		if ( 1 === preg_match( '/^EXDATE;TZID=[^:]+:(.+)$/m', $component, $exdate ) ) {
			$instants = array_values( array_diff( $instants, explode( ',', trim( $exdate[1] ) ) ) );
		}

		return $instants;
	}

	/**
	 * Every instant a payload expands to, across all its components.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The iCal payload.
	 *
	 * @return string[] The instants, ascending, duplicates preserved.
	 */
	protected function instants_in( string $body ): array {
		$instants = array();

		foreach ( $this->components_in( $body ) as $component ) {
			$instants = array_merge( $instants, $this->expand( $component ) );
		}

		sort( $instants );

		return $instants;
	}

	/**
	 * A subscription taken out before the split still yields every date, once.
	 *
	 * @covers ::get_ical_file
	 * @covers ::series_component_post_ids
	 * @covers ::is_readable_fragment
	 *
	 * @return void
	 */
	public function test_an_existing_subscription_keeps_every_instant_exactly_once(): void {
		$origin_id = $this->create_series();

		$this->enable_pretty_permalinks();

		$url    = $this->series_ical_url( $origin_id );
		$before = $this->instants_in( $this->body_for( $url ) );

		$this->assertCount( 5, $before, 'Fixture setup: the pre-split subscription expands to five dates.' );

		$result  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward = (int) $result['forward_post_id'];

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );

		$body       = $this->body_for( $url );
		$components = $this->components_in( $body );

		$this->assertSame(
			array( sprintf( 'gatherpress_%d', $origin_id ), sprintf( 'gatherpress_%d', $forward ) ),
			array_keys( $components ),
			'The stable URL must serialize both fragments of the logical series, origin first.'
		);

		$origin_instants  = $this->expand( $components[ sprintf( 'gatherpress_%d', $origin_id ) ] );
		$forward_instants = $this->expand( $components[ sprintf( 'gatherpress_%d', $forward ) ] );

		$this->assertSame(
			array(),
			array_intersect( $origin_instants, $forward_instants ),
			'The two fragments must carry disjoint dates, or the union assertion below proves nothing.'
		);
		$this->assertNotEmpty( $forward_instants, 'The forward fragment must contribute dates of its own.' );
		$this->assertSame(
			$before,
			$this->instants_in( $body ),
			'The same URL must expand to every original instant, exactly once, after the split.'
		);
		$this->assertSame(
			array( sprintf( 'RELATED-TO:gatherpress_%d', $origin_id ) ),
			array_values(
				preg_grep(
					'/^RELATED-TO:/',
					explode( "\n", $components[ sprintf( 'gatherpress_%d', $forward ) ] )
				)
			),
			'The fragment must name the UID the subscription was taken out against.'
		);
	}

	/**
	 * A change on either fragment moves the stable URL's validators.
	 *
	 * `Last-Modified` has one-second resolution and a test runs well inside one,
	 * so the entity tag and the cache namespace are what carry the assertion.
	 * They are what a revalidating client and the stored body are compared by.
	 *
	 * @covers ::get_ical_file
	 * @covers ::get_ics_cache_key
	 * @covers ::get_etag
	 *
	 * @return void
	 */
	public function test_a_change_on_either_fragment_moves_the_stable_subscriptions_validators(): void {
		$origin_id = $this->create_series();

		$this->enable_pretty_permalinks();

		$url     = $this->series_ical_url( $origin_id );
		$result  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward = (int) $result['forward_post_id'];

		$body      = $this->body_for( $url );
		$etag      = Calendar_Setup::get_instance()->get_etag( $body );
		$namespace = Calendar_Cache::get_instance()->get_versioned_key(
			Calendar_Setup::get_instance()->get_ics_cache_key()
		);

		// A cancellation on the *forward* fragment: the subscriber never asked
		// for that post and cannot poll it.
		Occurrences::get_instance()->set_status(
			$forward,
			$this->identifiers[3],
			Occurrences::STATUS_CANCELED
		);

		$after_cancel = $this->body_for( $url );

		$this->assertNotSame( $body, $after_cancel, 'A cancellation on the forward fragment must change the body.' );
		$this->assertNotSame(
			$etag,
			Calendar_Setup::get_instance()->get_etag( $after_cancel ),
			'A cancellation on the forward fragment must move the entity tag the subscriber revalidates with.'
		);
		$this->assertNotSame(
			$namespace,
			Calendar_Cache::get_instance()->get_versioned_key( Calendar_Setup::get_instance()->get_ics_cache_key() ),
			'A cancellation on the forward fragment must strand the cached body.'
		);
		$this->assertNotContains(
			$this->identifiers[3],
			$this->instants_in( $after_cancel ),
			'The canceled date must leave the subscription the origin URL serves.'
		);

		$namespace = Calendar_Cache::get_instance()->get_versioned_key(
			Calendar_Setup::get_instance()->get_ics_cache_key()
		);

		wp_update_post(
			array(
				'ID'         => $origin_id,
				'post_title' => 'Renamed origin fragment',
			)
		);

		$after_edit = $this->body_for( $url );

		$this->assertNotSame( $after_cancel, $after_edit, 'An edit on the origin fragment must change the body.' );
		$this->assertNotSame(
			Calendar_Setup::get_instance()->get_etag( $after_cancel ),
			Calendar_Setup::get_instance()->get_etag( $after_edit ),
			'An edit on the origin fragment must move the entity tag.'
		);
		$this->assertNotSame(
			$namespace,
			Calendar_Cache::get_instance()->get_versioned_key( Calendar_Setup::get_instance()->get_ics_cache_key() ),
			'An edit on the origin fragment must strand the cached body.'
		);
	}

	/**
	 * The capped origin component announces itself as newer.
	 *
	 * The measurement is taken **at the rule-cap phase**, not across the whole
	 * split. Moving the occurrence rows already advances the revision through
	 * `gatherpress_occurrences_changed`, and every split moves rows, so a
	 * before-and-after comparison of the emitted component would pass whether or
	 * not capping the rule advanced anything, because it would be measuring the
	 * row move. Serializing the origin's component the moment the cap completes and
	 * requiring the finished component to be strictly newer than *that* is what
	 * isolates the cap.
	 *
	 * Same-second by construction: the split leaves `post_modified_gmt` alone,
	 * which the assertion checks, so nothing here can be separated by the clock.
	 *
	 * @covers \GatherPress\Core\Calendar\Calendar::get_ical_event_string
	 * @covers \GatherPress\Core\Calendar\Calendar::get_sequence
	 * @covers \GatherPress\Core\Calendar\Calendar::revision_stamp
	 *
	 * @return void
	 */
	public function test_the_capped_component_is_strictly_newer_than_it_was_at_the_cap(): void {
		$origin_id = $this->create_series();
		$modified  = get_post( $origin_id )->post_modified_gmt;
		$at_cap    = '';

		$observe = static function ( $outcome, string $phase ) use ( $origin_id, &$at_cap ) {
			if ( 'origin_rule' === $phase ) {
				$at_cap = ( new Calendar( $origin_id ) )->get_ical_event_string();
			}

			return $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $observe, 10, 2 );

		Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'gatherpress_split_phase_complete', $observe, 10 );

		$after = ( new Calendar( $origin_id ) )->get_ical_event_string();

		$this->assertNotSame( '', $at_cap, 'Failed to serialize the origin component at the rule-cap phase.' );
		$this->assertSame(
			$modified,
			get_post( $origin_id )->post_modified_gmt,
			'The split must leave post_modified_gmt alone, which is what makes this a same-second change.'
		);
		$this->assertSame(
			$this->property_of( $at_cap, 'UID' ),
			$this->property_of( $after, 'UID' ),
			'The component a subscriber already holds must keep its identifier, or nothing is being replaced.'
		);
		$this->assertStringContainsString(
			'COUNT=2',
			$this->property_of( $at_cap, 'RRULE' ),
			'Fixture setup: the rule must already be capped at the moment the reading is taken.'
		);
		$this->assertGreaterThan(
			(int) $this->property_of( $at_cap, 'SEQUENCE' ),
			(int) $this->property_of( $after, 'SEQUENCE' ),
			'The capped component must report a strictly greater SEQUENCE than it did at the cap.'
		);
		$this->assertGreaterThan(
			$this->property_of( $at_cap, 'LAST-MODIFIED' ),
			$this->property_of( $after, 'LAST-MODIFIED' ),
			'The capped component must report a strictly later LAST-MODIFIED than it did at the cap.'
		);
	}

	/**
	 * A client merge of the new bytes replaces the old expansion.
	 *
	 * The merge follows RFC 5545 section 3.8.7.4: an incoming component with a
	 * known `UID` replaces the held one when its `SEQUENCE` is greater, and an
	 * unknown `UID` is added. The point of the assertion is what the client is
	 * left holding, five dates and each of them once, rather than the fact that
	 * a merge ran.
	 *
	 * @covers ::get_ical_file
	 * @covers \GatherPress\Core\Calendar\Calendar::uid
	 * @covers \GatherPress\Core\Calendar\Calendar::related_lines
	 *
	 * @return void
	 */
	public function test_a_client_merge_replaces_the_old_expansion_rather_than_duplicating_dates(): void {
		$origin_id = $this->create_series();

		$this->enable_pretty_permalinks();

		$url  = $this->series_ical_url( $origin_id );
		$held = $this->client_store( $this->body_for( $url ) );

		$this->assertCount( 1, $held, 'Fixture setup: the subscription starts as one component.' );

		Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		$origin_uid   = sprintf( 'gatherpress_%d', $origin_id );
		$held_instant = $held[ $origin_uid ]['instants'];
		$merged       = $this->merge( $held, $this->body_for( $url ) );
		$instants     = array();

		foreach ( $merged as $entry ) {
			$instants = array_merge( $instants, $entry['instants'] );
		}

		sort( $instants );

		$this->assertContains(
			$this->identifiers[4],
			$held_instant,
			'Fixture setup: the client held the later dates under the origin UID before the split.'
		);
		$this->assertNotContains(
			$this->identifiers[4],
			$merged[ $origin_uid ]['instants'],
			'The merge must remove the moved dates from the held component rather than leaving them behind.'
		);
		$this->assertSame(
			array_values( $this->identifiers ),
			$instants,
			'After the merge the client holds every original date exactly once, and nothing else.'
		);
	}

	/**
	 * A private fragment is not exported to a visitor.
	 *
	 * @covers ::series_component_post_ids
	 * @covers ::is_readable_fragment
	 * @covers ::get_ics_cache_key
	 *
	 * @return void
	 */
	public function test_a_private_fragment_is_withheld_and_keyed_separately(): void {
		$origin_id = $this->create_series();

		$this->enable_pretty_permalinks();

		$url     = $this->series_ical_url( $origin_id );
		$result  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward = (int) $result['forward_post_id'];

		wp_update_post(
			array(
				'ID'          => $forward,
				'post_status' => 'private',
			)
		);
		wp_set_current_user( 0 );

		$anonymous     = $this->body_for( $url );
		$anonymous_key = Calendar_Setup::get_instance()->get_ics_cache_key();

		$this->assertSame(
			array( sprintf( 'gatherpress_%d', $origin_id ) ),
			array_keys( $this->components_in( $anonymous ) ),
			'A private fragment must not be exported to a visitor who may not read it.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$editor = $this->body_for( $url );

		$this->assertSame(
			array( sprintf( 'gatherpress_%d', $origin_id ), sprintf( 'gatherpress_%d', $forward ) ),
			array_keys( $this->components_in( $editor ) ),
			'An editor who may read the private fragment still gets the whole series.'
		);
		$this->assertNotSame(
			$anonymous_key,
			Calendar_Setup::get_instance()->get_ics_cache_key(),
			'The two responses must not share a cache key, or one visitor is served the other\'s body.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * A password-protected fragment stays behind its password.
	 *
	 * @covers ::series_component_post_ids
	 * @covers ::is_readable_fragment
	 *
	 * @return void
	 */
	public function test_a_password_protected_fragment_is_withheld(): void {
		$origin_id = $this->create_series();

		$this->enable_pretty_permalinks();

		$url     = $this->series_ical_url( $origin_id );
		$result  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward = (int) $result['forward_post_id'];

		wp_update_post(
			array(
				'ID'            => $forward,
				'post_password' => 'correct-horse',
			)
		);

		$this->assertSame(
			array( sprintf( 'gatherpress_%d', $origin_id ) ),
			array_keys( $this->components_in( $this->body_for( $url ) ) ),
			'A password-protected fragment must not export its title and description in plain text.'
		);
	}

	/**
	 * A single-occurrence download is still one component.
	 *
	 * The exemption that keeps the widened export from turning every occurrence
	 * download into a copy of the whole series.
	 *
	 * @covers ::series_component_post_ids
	 *
	 * @return void
	 */
	public function test_an_occurrence_download_is_not_widened_to_the_series(): void {
		$origin_id = $this->create_series();

		$this->enable_pretty_permalinks();

		Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		$body = $this->body_for(
			add_query_arg(
				array( Context::QUERY_VAR => $this->identifiers[0] ),
				$this->series_ical_url( $origin_id )
			)
		);

		$this->assertSame(
			array( sprintf( 'gatherpress_%d', $origin_id ) ),
			array_keys( $this->components_in( $body ) ),
			'An occurrence download describes one instance of one post.'
		);
		$this->assertStringContainsString(
			'RECURRENCE-ID',
			$body,
			'Fixture setup: the occurrence download must be the override shape.'
		);
	}

	/**
	 * A renamed series term still names the origin a subscriber holds.
	 *
	 * The term slug is the readable answer, but it is only believed when it
	 * names a post still in the series: a `RELATED-TO` pointing at a `UID` no
	 * component carries would be worse than none. The rename here makes the
	 * fallback's answer differ from the slug's, so the assertion cannot pass by
	 * coincidence.
	 *
	 * @covers \GatherPress\Core\Calendar\Calendar::series_origin_post_id
	 *
	 * @return void
	 */
	public function test_a_renamed_series_term_falls_back_to_the_lowest_member(): void {
		$origin_id = $this->create_series();
		$result    = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward   = (int) $result['forward_post_id'];
		$term_id   = Series::get_instance()->term_id_for_post( $origin_id );

		$this->assertGreaterThan( 0, $term_id, 'Fixture setup: the split should have created a series term.' );

		wp_update_term( $term_id, Series::TAXONOMY, array( 'slug' => 'series-999999' ) );
		clean_post_cache( $forward );

		$this->assertSame(
			sprintf( 'RELATED-TO:gatherpress_%d', $origin_id ),
			$this->property_line( ( new Calendar( $forward ) )->get_ical_event_string(), 'RELATED-TO' ),
			'A slug naming a post outside the series must not be believed over the series\' own membership.'
		);
	}

	/**
	 * A request that names no event contributes only what it queried.
	 *
	 * Covers the two guards that keep the series widening off every other
	 * calendar request: a request with no queried post at all, and a singular
	 * request on a post type that is not an event.
	 *
	 * @covers ::series_component_post_ids
	 *
	 * @return void
	 */
	public function test_series_widening_is_skipped_for_non_event_requests(): void {
		$this->create_series();
		$this->enable_pretty_permalinks();

		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertSame(
			array( $post_id ),
			Calendar_Setup::get_instance()->series_component_post_ids(),
			'A non-event singular request contributes exactly the post it queried.'
		);

		$this->go_to( home_url( '/' ) );

		$this->assertSame(
			array( 0 ),
			Calendar_Setup::get_instance()->series_component_post_ids(),
			'A request with no queried post resolves no series membership at all.'
		);
	}

	/**
	 * A series nothing has split carries no relationship pointer.
	 *
	 * Invoked directly because xdebug does not trace a private helper called
	 * from a short delegation in the same class: the body runs, and the coverage
	 * report says otherwise (see the "Extracted same-class helpers" rule in
	 * `AGENTS.md`). This covers the no-term arm; the site-wide flag arm is its
	 * own test below, because the two cannot be measured with one fixture.
	 *
	 * @covers \GatherPress\Core\Calendar\Calendar::series_origin_post_id
	 * @covers \GatherPress\Core\Calendar\Calendar::related_lines
	 *
	 * @return void
	 */
	public function test_an_unsplit_series_names_no_origin(): void {
		$origin_id = $this->create_series();

		$this->assertSame(
			0,
			Utility::invoke_hidden_method(
				new Calendar( $origin_id ),
				'series_origin_post_id',
				array( $origin_id )
			),
			'A recurring series nothing has split belongs to no series term.'
		);
		$this->assertSame(
			array(),
			Utility::invoke_hidden_method( new Calendar( $origin_id ), 'related_lines', array() ),
			'A series with no origin to point at emits no RELATED-TO property.'
		);
	}

	/**
	 * The flags, not the absence of a series term, are what keep the origin
	 * lookup off the series taxonomy.
	 *
	 * The test above drives a series nothing has split, which carries no term,
	 * so it answers zero whether or not the flag guard exists and deleting the
	 * guard leaves it green. Removing that coincidence takes a post that *does*
	 * carry a series term while both flags say the site has no series to read:
	 * each option is recomputed from storage on its own lifecycle events, so
	 * the flags being out of step with the term is a bug state rather than an
	 * authored one, and it is exactly the state the guard is there for. Both
	 * flags are forced off because either one admits the lookup: the split
	 * flag exists precisely so a fully demoted split resolves after the
	 * recurring flag returns to '0'. The object term cache is cleared before
	 * the measurement, since a warm cache would answer `get_the_terms()`
	 * without a query and the cost assertion would hold with the guard gone.
	 *
	 * @covers \GatherPress\Core\Calendar\Calendar::series_origin_post_id
	 *
	 * @return void
	 */
	public function test_the_flag_is_what_keeps_the_origin_lookup_off_the_series_taxonomy(): void {
		global $wpdb;

		$origin_id = $this->create_series();
		$result    = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		$this->assertGreaterThan(
			0,
			(int) $result['forward_post_id'],
			'Fixture setup: the split should have produced a forward post.'
		);

		Series::get_instance()->flush_memo();

		$this->assertSame(
			$origin_id,
			Utility::invoke_hidden_method(
				new Calendar( $origin_id ),
				'series_origin_post_id',
				array( $origin_id )
			),
			'The fixture must resolve an origin while the flag is on, or the guard test proves nothing.'
		);

		update_option( Recurrence_Query::HAS_RECURRING_OPTION, '0', true );
		update_option( Series::HAS_SPLIT_SERIES_OPTION, '0', true );
		Series::get_instance()->flush_memo();
		clean_object_term_cache( $origin_id, (string) get_post_type( $origin_id ) );

		$before = count( $wpdb->queries );

		$this->assertSame(
			0,
			Utility::invoke_hidden_method(
				new Calendar( $origin_id ),
				'series_origin_post_id',
				array( $origin_id )
			),
			'A site the flag reports as having no recurring events must resolve no origin at all.'
		);

		$captured = array_column( array_slice( $wpdb->queries, $before ), 0 );

		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$captured,
					static function ( string $sql ) use ( $wpdb ): bool {
						return str_contains( $sql, $wpdb->term_relationships )
							|| str_contains( $sql, $wpdb->term_taxonomy );
					}
				)
			),
			'And it must not read the series taxonomy to find that out.'
		);
	}

	/**
	 * Read one property's whole line out of a component.
	 *
	 * @since 0.36.0
	 *
	 * @param string $component One `VEVENT` body.
	 * @param string $property  Property name, without parameters.
	 *
	 * @return string The line, or '' when the component does not carry it.
	 */
	protected function property_line( string $component, string $property ): string {
		$lines = preg_grep(
			'/^' . preg_quote( $property, '/' ) . '[;:]/',
			explode( "\n", str_replace( "\r\n", "\n", $component ) )
		);

		return (string) ( array_values( (array) $lines )[0] ?? '' );
	}

	/**
	 * Read one property's value out of a component.
	 *
	 * @since 0.36.0
	 *
	 * @param string $component One `VEVENT` body.
	 * @param string $property  Property name, without parameters.
	 *
	 * @return string The value, or '' when the component does not carry it.
	 */
	protected function property_of( string $component, string $property ): string {
		$value = '';

		foreach ( explode( "\n", str_replace( "\r\n", "\n", $component ) ) as $line ) {
			if ( str_starts_with( $line, $property . ':' ) ) {
				$value = substr( $line, strlen( $property ) + 1 );
				break;
			}

			if ( str_starts_with( $line, $property . ';' ) ) {
				$value = substr( (string) strstr( $line, ':' ), 1 );
				break;
			}
		}

		return $value;
	}

	/**
	 * Build the component set a subscribing client would hold from a payload.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The iCal payload.
	 *
	 * @return array<string, array{sequence: int, instants: string[]}> The held components.
	 */
	protected function client_store( string $body ): array {
		$store = array();

		foreach ( $this->components_in( $body ) as $uid => $component ) {
			$store[ $uid ] = array(
				'sequence' => (int) $this->property_of( $component, 'SEQUENCE' ),
				'instants' => $this->expand( $component ),
			);
		}

		return $store;
	}

	/**
	 * Merge a payload into a held component set, RFC 5545 section 3.8.7.4 style.
	 *
	 * @since 0.36.0
	 *
	 * @param array  $held The components the client already holds.
	 * @param string $body The payload that just arrived.
	 *
	 * @return array<string, array{sequence: int, instants: string[]}> The merged set.
	 */
	protected function merge( array $held, string $body ): array {
		foreach ( $this->client_store( $body ) as $uid => $incoming ) {
			if ( ! isset( $held[ $uid ] ) || $incoming['sequence'] > $held[ $uid ]['sequence'] ) {
				$held[ $uid ] = $incoming;
			}
		}

		return $held;
	}
}
