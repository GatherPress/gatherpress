<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Calendar.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Calendar\Setup;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Post;

/**
 * Class Test_Calendar.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Calendar
 * @group              endpoints
 */
class Test_Calendar extends Base {

	/**
	 * Build a published event with datetimes and (optionally) a venue with
	 * a structured address attached.
	 *
	 * @param bool $with_venue When true, also create and attach a venue with an address.
	 *
	 * @return int The event post ID.
	 */
	private function make_event( bool $with_venue = false ): int {
		$event_id = $this->mock->post(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Sample Event',
				'post_name'  => 'sample-event',
			)
		)->get()->ID;

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => '2030-06-15 14:30:00',
				'datetime_end'   => '2030-06-15 16:30:00',
				'timezone'       => 'America/New_York',
			)
		);

		if ( $with_venue ) {
			$venue_id = $this->mock->post(
				array(
					'post_type'  => Venue::POST_TYPE,
					'post_title' => 'Brooklyn Office',
					'post_name'  => 'brooklyn-office',
				)
			)->get()->ID;
			update_post_meta( $venue_id, 'gatherpress_address', '123 Main; Street, Brooklyn' );
			wp_set_post_terms( $event_id, '_brooklyn-office', Venue::TAXONOMY );
		}

		return $event_id;
	}

	/**
	 * Coverage for __construct method — wires an Event instance for the given post.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$event_id = $this->make_event();
		$instance = new Calendar( $event_id );

		$this->assertInstanceOf(
			Event::class,
			$instance->event,
			'Calendar should compose an Event for the given post id.'
		);
		$this->assertInstanceOf(
			WP_Post::class,
			$instance->event->event,
			'Composed Event should resolve to a real WP_Post.'
		);
		$this->assertSame(
			$event_id,
			$instance->event->event->ID,
			'Composed Event should wrap the requested post id.'
		);
	}

	/**
	 * Coverage for get_ical_url — delegates to the protected endpoint builder
	 * with the iCal slug constant.
	 *
	 * @covers ::get_ical_url
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_get_ical_url(): void {
		$instance = new Calendar( $this->make_event() );

		$url = $instance->get_ical_url();

		$this->assertIsString( $url );
		$this->assertStringContainsString(
			'gatherpress_calendar=' . Setup::ICAL_SLUG,
			$url,
			'iCal URL should carry the gatherpress_calendar query var with the ical slug.'
		);
	}

	/**
	 * Single-event iCal downloads must not use the feed/ical URL shape.
	 *
	 * With pretty permalinks, the download is …/event/<slug>/ical/. With plain
	 * permalinks, WordPress uses the gatherpress_calendar query arg instead —
	 * never get_post_comments_feed_link() (…/feed/ical/), which is for feeds only.
	 *
	 * @covers ::get_ical_url
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_get_ical_url_uses_permalink_download_path(): void {
		$event_id = $this->make_event();

		$path_filter = static function () {
			return home_url( '/event/sample-event/' );
		};
		add_filter( 'post_link', $path_filter );
		add_filter( 'post_type_link', $path_filter );

		$pretty_url = ( new Calendar( $event_id ) )->get_ical_url();

		remove_filter( 'post_link', $path_filter );
		remove_filter( 'post_type_link', $path_filter );

		$plain_url = ( new Calendar( $event_id ) )->get_ical_url();

		$this->assertIsString( $pretty_url );
		$this->assertStringContainsString( 'sample-event/ical', $pretty_url );

		$this->assertIsString( $plain_url );
		$this->assertStringContainsString(
			'gatherpress_calendar=' . Setup::ICAL_SLUG,
			$plain_url
		);
	}

	/**
	 * Coverage for get_outlook_url — uses the `outlook` sibling slug pointing
	 * at the same iCal template.
	 *
	 * @covers ::get_outlook_url
	 *
	 * @return void
	 */
	public function test_get_outlook_url(): void {
		$instance = new Calendar( $this->make_event() );

		$url = $instance->get_outlook_url();

		$this->assertIsString( $url );
		$this->assertStringContainsString(
			'gatherpress_calendar=outlook',
			$url,
			'Outlook URL should carry the outlook slug as the calendar query var.'
		);
	}

	/**
	 * Coverage for get_google_url — returns the on-site Google Calendar
	 * redirect endpoint URL for this event (not the off-site Google URL).
	 *
	 * @covers ::get_google_url
	 *
	 * @return void
	 */
	public function test_get_google_url_returns_endpoint_url(): void {
		$event_id = $this->make_event();
		$instance = new Calendar( $event_id );
		$slug     = get_post_field( 'post_name', $event_id );

		$this->assertSame(
			home_url(
				sprintf(
					'/?gatherpress_event=%s&gatherpress_calendar=google-calendar',
					$slug
				)
			),
			$instance->get_google_url(),
			'get_google_url() should resolve to the on-site google-calendar endpoint.'
		);
	}

	/**
	 * Coverage for get_yahoo_url — returns the on-site Yahoo! Calendar
	 * redirect endpoint URL for this event.
	 *
	 * @covers ::get_yahoo_url
	 *
	 * @return void
	 */
	public function test_get_yahoo_url_returns_endpoint_url(): void {
		$event_id = $this->make_event();
		$instance = new Calendar( $event_id );
		$slug     = get_post_field( 'post_name', $event_id );

		$this->assertSame(
			home_url(
				sprintf(
					'/?gatherpress_event=%s&gatherpress_calendar=yahoo-calendar',
					$slug
				)
			),
			$instance->get_yahoo_url(),
			'get_yahoo_url() should resolve to the on-site yahoo-calendar endpoint.'
		);
	}

	/**
	 * Coverage for get_google_destination_url with no venue address — falls
	 * past the `! empty( $venue['address'] )` guard so location is just the
	 * venue name (here empty since no venue is attached).
	 *
	 * @covers ::get_google_destination_url
	 *
	 * @return void
	 */
	public function test_get_google_destination_url_without_venue_address(): void {
		$instance = new Calendar( $this->make_event() );
		$url      = $instance->get_google_destination_url();

		$this->assertStringStartsWith(
			'https://www.google.com/calendar/event?',
			$url,
			'Google destination URL should target the off-site calendar event endpoint.'
		);
		$this->assertStringContainsString(
			'action=TEMPLATE',
			$url,
			'Google destination URL should include the TEMPLATE action param.'
		);
		$this->assertStringContainsString(
			'text=Sample%20Event',
			$url,
			'Google destination URL should include the event title.'
		);
	}

	/**
	 * Coverage for get_google_destination_url with a venue address —
	 * exercises the address-concat branch of the location string.
	 *
	 * @covers ::get_google_destination_url
	 *
	 * @return void
	 */
	public function test_get_google_destination_url_with_venue_address(): void {
		$instance = new Calendar( $this->make_event( true ) );
		$url      = $instance->get_google_destination_url();

		$this->assertStringContainsString(
			'location=' . rawurlencode( 'Brooklyn Office, 123 Main; Street, Brooklyn' ),
			$url,
			'Google destination URL location should concat venue name and address.'
		);
	}

	/**
	 * Returns an empty string from get_google_destination_url when the
	 * underlying Event has no post — a Calendar built from a post type that
	 * does not support `gatherpress-event-date` never resolves one.
	 *
	 * @covers ::get_google_destination_url
	 *
	 * @return void
	 */
	public function test_get_google_destination_url_returns_empty_without_post(): void {
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$instance = new Calendar( $post->ID );

		$this->assertSame(
			'',
			$instance->get_google_destination_url(),
			'Google destination URL should be empty when the underlying post cannot be resolved as an event.'
		);
	}

	/**
	 * Coverage for get_yahoo_destination_url with no venue address.
	 *
	 * @covers ::get_yahoo_destination_url
	 *
	 * @return void
	 */
	public function test_get_yahoo_destination_url_without_venue_address(): void {
		$instance = new Calendar( $this->make_event() );
		$url      = $instance->get_yahoo_destination_url();

		$this->assertStringStartsWith(
			'https://calendar.yahoo.com/?',
			$url,
			'Yahoo destination URL should target the off-site calendar endpoint.'
		);
		$this->assertStringContainsString(
			'title=Sample%20Event',
			$url,
			'Yahoo destination URL should include the event title.'
		);
		$this->assertStringContainsString(
			'st=20300615',
			$url,
			'Yahoo destination URL should include the event start date in Ymd format.'
		);
	}

	/**
	 * Coverage for get_yahoo_destination_url with a venue address.
	 *
	 * @covers ::get_yahoo_destination_url
	 *
	 * @return void
	 */
	public function test_get_yahoo_destination_url_with_venue_address(): void {
		$instance = new Calendar( $this->make_event( true ) );
		$url      = $instance->get_yahoo_destination_url();

		$this->assertStringContainsString(
			'in_loc=' . rawurlencode( 'Brooklyn Office, 123 Main; Street, Brooklyn' ),
			$url,
			'Yahoo destination URL in_loc should concat venue name and address.'
		);
	}

	/**
	 * Returns an empty string from get_yahoo_destination_url when the
	 * underlying Event has no post.
	 *
	 * @covers ::get_yahoo_destination_url
	 *
	 * @return void
	 */
	public function test_get_yahoo_destination_url_returns_empty_without_post(): void {
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$instance = new Calendar( $post->ID );

		$this->assertSame(
			'',
			$instance->get_yahoo_destination_url(),
			'Yahoo destination URL should be empty when the underlying post cannot be resolved as an event.'
		);
	}

	/**
	 * Coverage for get_ical_event_string — builds a complete VEVENT block,
	 * properly escapes RFC 5545 special chars in LOCATION (comma, semicolon),
	 * and includes all expected lines.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::escape_ical_text
	 * @covers ::fold_ical_text
	 *
	 * @return void
	 */
	public function test_get_ical_event_string_with_venue_address(): void {
		$instance = new Calendar( $this->make_event( true ) );
		$vevent   = $instance->get_ical_event_string();

		$this->assertStringStartsWith( 'BEGIN:VEVENT', $vevent );
		$this->assertStringEndsWith( 'END:VEVENT', $vevent );
		$this->assertStringContainsString( 'DTSTART:20300615T183000Z', $vevent );
		$this->assertStringContainsString( 'DTEND:20300615T203000Z', $vevent );
		$this->assertStringContainsString( 'SUMMARY:Sample Event', $vevent );

		// Address has a `;` and `,` which RFC 5545 requires escaped as `\;` `\,`.
		$this->assertStringContainsString(
			'LOCATION:Brooklyn Office\\, 123 Main\\; Street\\, Brooklyn',
			$vevent,
			'LOCATION must be RFC 5545-escaped for commas and semicolons.'
		);
	}

	/**
	 * Coverage for get_ical_event_string: SEQUENCE is post_modified_gmt as seconds
	 * Unix timestamp, and DTSTAMP and LAST-MODIFIED both report that GMT time.
	 *
	 * @covers ::get_ical_event_string
	 * @covers ::get_sequence
	 *
	 * @return void
	 */
	public function test_get_ical_event_string_sequence_and_last_modified(): void {
		$instance = new Calendar( $this->make_event() );

		$instance->event->event->post_modified_gmt = '2030-01-01 10:00:00';

		$vevent = $instance->get_ical_event_string();

		$this->assertStringContainsString(
			sprintf( 'SEQUENCE:%d', strtotime( '2030-01-01 10:00:00' ) - 1577836800 ),
			$vevent,
			'SEQUENCE should be seconds since the 2020 epoch, taken from post_modified_gmt.'
		);
		$this->assertStringContainsString(
			'LAST-MODIFIED:20300101T100000Z',
			$vevent,
			'LAST-MODIFIED should report post_modified_gmt in UTC form.'
		);
		$this->assertStringContainsString(
			'DTSTAMP:20300101T100000Z',
			$vevent,
			'DTSTAMP shares the post_modified_gmt derivation with LAST-MODIFIED.'
		);

		$instance->event->event->post_modified_gmt = '2030-01-01 11:00:00';

		$this->assertStringContainsString(
			sprintf( 'SEQUENCE:%d', strtotime( '2030-01-01 11:00:00' ) - 1577836800 ),
			$instance->get_ical_event_string(),
			'A later modification should raise the sequence a client can compare against.'
		);
	}

	/**
	 * Regression coverage for DTSTAMP on a non-UTC site: it must derive from
	 * post_modified_gmt, not the site-local post_modified, so it does not drift
	 * by the UTC offset.
	 *
	 * @covers ::get_ical_event_string
	 *
	 * @return void
	 */
	public function test_get_ical_event_string_dtstamp_uses_gmt_on_non_utc_site(): void {
		$original_tz = get_option( 'timezone_string' );
		update_option( 'timezone_string', 'America/New_York' );

		$instance = new Calendar( $this->make_event() );

		// Site-local modification time and its GMT counterpart differ by the offset.
		$instance->event->event->post_modified     = '2030-01-01 05:00:00';
		$instance->event->event->post_modified_gmt = '2030-01-01 10:00:00';

		$vevent = $instance->get_ical_event_string();

		$this->assertStringContainsString(
			'DTSTAMP:20300101T100000Z',
			$vevent,
			'DTSTAMP must come from post_modified_gmt, not the site-local post_modified.'
		);
		$this->assertStringNotContainsString(
			'DTSTAMP:20300101T050000Z',
			$vevent,
			'DTSTAMP must not use the site-local time on a non-UTC site.'
		);

		if ( false === $original_tz ) {
			delete_option( 'timezone_string' );
		} else {
			update_option( 'timezone_string', $original_tz );
		}
	}

	/**
	 * Coverage for get_sequence: the value is post_modified_gmt as epoch-offset
	 * timestamp, so a later modification yields a higher revision.
	 *
	 * @covers ::get_sequence
	 *
	 * @return void
	 */
	public function test_get_sequence_grows_when_event_is_edited(): void {
		$event_id = $this->make_event();
		$instance = new Calendar( $event_id );

		$instance->event->event->post_modified_gmt = '2030-01-01 11:00:00';

		$this->assertSame(
			strtotime( '2030-01-01 11:00:00' ) - 1577836800,
			Utility::invoke_hidden_method( $instance, 'get_sequence' ),
			'Sequence should be seconds since the 2020 epoch, taken from post_modified_gmt.'
		);

		$instance->event->event->post_modified_gmt = '2030-01-01 12:00:00';

		$this->assertSame(
			strtotime( '2030-01-01 12:00:00' ) - 1577836800,
			Utility::invoke_hidden_method( $instance, 'get_sequence' ),
			'A later modification should raise the sequence.'
		);
	}

	/**
	 * Coverage for the get_sequence guard: an unparsable modification date
	 * yields zero rather than a warning or a negative value.
	 *
	 * @covers ::get_sequence
	 *
	 * @return void
	 */
	public function test_get_sequence_returns_zero_for_unparsable_date(): void {
		$instance = new Calendar( $this->make_event() );

		$instance->event->event->post_modified_gmt = 'not a date';

		$this->assertSame(
			0,
			Utility::invoke_hidden_method( $instance, 'get_sequence' ),
			'An unparsable modification date should fall back to zero.'
		);
	}

	/**
	 * Coverage for the get_sequence guard when the underlying Event has no
	 * post: there is no post_modified_gmt to derive a revision from.
	 *
	 * @covers ::get_sequence
	 *
	 * @return void
	 */
	public function test_get_sequence_returns_zero_without_post(): void {
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$instance = new Calendar( $post->ID );

		$this->assertSame(
			0,
			Utility::invoke_hidden_method( $instance, 'get_sequence' ),
			'Sequence should be zero when the underlying post cannot be resolved as an event.'
		);
	}

	/**
	 * Coverage for get_sequence clamping: a span wider than the RFC 5545
	 * integer ceiling saturates instead of overflowing.
	 *
	 * @covers ::get_sequence
	 *
	 * @return void
	 */
	public function test_get_sequence_clamps_to_rfc_integer_ceiling(): void {
		$instance = new Calendar( $this->make_event() );

		$instance->event->event->post_modified_gmt = '2100-01-01 00:00:00';

		$this->assertSame(
			2147483647,
			Utility::invoke_hidden_method( $instance, 'get_sequence' ),
			'Sequence should clamp to the RFC 5545 integer maximum.'
		);
	}

	/**
	 * Coverage for get_ical_event_string when no venue is attached — the
	 * empty-address branch leaves location as just the venue name (which is
	 * also empty here).
	 *
	 * @covers ::get_ical_event_string
	 *
	 * @return void
	 */
	public function test_get_ical_event_string_without_venue(): void {
		$instance = new Calendar( $this->make_event() );
		$vevent   = $instance->get_ical_event_string();

		$this->assertStringContainsString( 'SUMMARY:Sample Event', $vevent );
		$this->assertStringContainsString( 'LOCATION:', $vevent );
	}

	/**
	 * Returns an empty string from get_ical_event_string when the underlying
	 * Event has no post, so nothing malformed lands inside a VCALENDAR wrap.
	 *
	 * @covers ::get_ical_event_string
	 *
	 * @return void
	 */
	public function test_get_ical_event_string_returns_empty_without_post(): void {
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$instance = new Calendar( $post->ID );

		$this->assertSame(
			'',
			$instance->get_ical_event_string(),
			'VEVENT should be empty when the underlying post cannot be resolved as an event.'
		);
	}

	/**
	 * A post whose post_modified_gmt will not parse still gets the
	 * RFC-required DTSTAMP, stamped at generation time rather than at the
	 * Unix epoch.
	 *
	 * @covers ::get_ical_event_string
	 *
	 * @return void
	 */
	public function test_get_ical_event_string_stamps_now_for_unparsable_modified_date(): void {
		$instance = new Calendar( $this->make_event() );

		$instance->event->event->post_modified_gmt = '9999-99-99 99:99:99';

		$before = time();
		$vevent = $instance->get_ical_event_string();
		$after  = time();

		$this->assertSame(
			1,
			preg_match( '/DTSTAMP:(\d{8}T\d{6}Z)/', $vevent, $matches ),
			'VEVENT should still carry a DTSTAMP when post_modified_gmt will not parse.'
		);

		$stamp = strtotime( $matches[1] );

		$this->assertGreaterThanOrEqual(
			$before,
			$stamp,
			'DTSTAMP should fall back to the generation time, not the Unix epoch.'
		);
		$this->assertLessThanOrEqual(
			$after,
			$stamp,
			'DTSTAMP should fall back to the generation time, not a future date.'
		);
		$this->assertStringContainsString(
			sprintf( 'LAST-MODIFIED:%s', $matches[1] ),
			$vevent,
			'LAST-MODIFIED should share the fallback stamp with DTSTAMP.'
		);
	}

	/**
	 * Folding wraps text longer than 75 chars across CRLF + space.
	 *
	 * @covers ::fold_ical_text
	 *
	 * @return void
	 */
	public function test_fold_ical_text_wraps_long_strings(): void {
		$instance = new Calendar( $this->make_event() );

		$short = Utility::invoke_hidden_method( $instance, 'fold_ical_text', array( 'short text' ) );
		$this->assertSame( 'short text', $short, 'Short text should pass through unchanged.' );

		$long_text = str_repeat( 'a', 200 );
		$folded    = Utility::invoke_hidden_method( $instance, 'fold_ical_text', array( $long_text ) );

		$this->assertStringContainsString(
			"\r\n ",
			$folded,
			'Long text should be folded with CRLF + space sequences.'
		);
	}

	/**
	 * Escaping covers backslash, comma, semicolon, CR, LF per RFC 5545.
	 *
	 * @covers ::escape_ical_text
	 *
	 * @return void
	 */
	public function test_escape_ical_text_escapes_special_chars(): void {
		$instance = new Calendar( $this->make_event() );

		$escaped = Utility::invoke_hidden_method(
			$instance,
			'escape_ical_text',
			array( "a\\b,c;d\re\nf" )
		);

		// addcslashes converts \r and \n to the literal two-char sequences
		// `\r` and `\n` (backslash + letter), not actual CR/LF.
		$this->assertSame(
			'a\\\\b\\,c\\;d\\re\\nf',
			$escaped,
			'All five RFC 5545 special chars should be escaped as literal sequences.'
		);
	}

	/**
	 * Returns false from get_endpoint_url when the underlying Event has no post.
	 *
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_get_endpoint_url_returns_false_without_post(): void {
		// Make a Calendar whose Event has a null post (non-event ID).
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$instance = new Calendar( $post->ID );

		// The Event's `event` property stays null when the source post isn't
		// of the supported type — get_endpoint_url short-circuits on that.
		$this->assertFalse(
			$instance->get_ical_url(),
			'get_ical_url should return false when the underlying post cannot be resolved as an event.'
		);
	}

	/**
	 * Delegates to get_post_comments_feed_link when the requested slug starts
	 * with `feed/` — covers the early-return branch of get_endpoint_url.
	 *
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_get_endpoint_url_handles_feed_slug(): void {
		$instance = new Calendar( $this->make_event() );

		$url = Utility::invoke_hidden_method(
			$instance,
			'get_endpoint_url',
			array( 'feed/ical' )
		);

		$this->assertIsString( $url );
		$this->assertStringContainsString(
			'feed=ical',
			$url,
			'feed/ical slug should produce a feed URL with feed=ical.'
		);
	}

	/**
	 * The gatherpress_calendar_url filter runs against the resolved URL and
	 * the post object — verifies both the filter contract and that
	 * integrator overrides flow back through the final sanitize_url().
	 *
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_get_endpoint_url_filter_is_applied(): void {
		$instance      = new Calendar( $this->make_event() );
		$captured_post = null;
		$captured_url  = null;
		$override_url  = 'https://override.example/custom/path';

		$filter = static function ( $url, $post ) use ( &$captured_url, &$captured_post, $override_url ) {
			$captured_url  = $url;
			$captured_post = $post;
			return $override_url;
		};

		add_filter( 'gatherpress_calendar_url', $filter, 10, 2 );
		$result = $instance->get_ical_url();
		remove_filter( 'gatherpress_calendar_url', $filter, 10 );

		$this->assertSame(
			$override_url,
			$result,
			'Filter return value should be reflected in the final URL.'
		);
		$this->assertInstanceOf(
			WP_Post::class,
			$captured_post,
			'Filter should receive the originating WP_Post as the second argument.'
		);
		$this->assertIsString( $captured_url );
	}

	/**
	 * Coverage for the path-conflict branch of get_endpoint_url — when the
	 * computed endpoint path collides with an existing public post path the
	 * builder falls back to the query-arg form. Cheapest way to materialize a
	 * collision is to create a post whose slug matches what would otherwise
	 * be the resolved endpoint URL.
	 *
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_get_endpoint_url_falls_back_on_path_conflict(): void {
		$instance = new Calendar( $this->make_event() );

		// Force the path-style branch: filter get_permalink so the post URL
		// has no `?`. Then make `get_page_by_path()` return a non-null hit so
		// the path_conflict fallback fires.
		$path_filter = static function () {
			return home_url( '/event/sample-event/' );
		};
		add_filter( 'post_link', $path_filter, 10, 1 );
		add_filter( 'post_type_link', $path_filter, 10, 1 );

		// Insert a public page whose post_name matches `ical`, attached at
		// the root, so get_page_by_path('/event/sample-event/ical', ...)
		// hits — the path-conflict branch then routes through add_query_arg.
		$this->mock->post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Conflict',
				'post_name'   => 'event/sample-event/ical',
			)
		)->get();

		$url = $instance->get_ical_url();

		remove_filter( 'post_link', $path_filter, 10 );
		remove_filter( 'post_type_link', $path_filter, 10 );

		$this->assertIsString( $url );
		$this->assertNotEmpty( $url );
	}
}
