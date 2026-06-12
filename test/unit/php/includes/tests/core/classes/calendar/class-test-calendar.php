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
