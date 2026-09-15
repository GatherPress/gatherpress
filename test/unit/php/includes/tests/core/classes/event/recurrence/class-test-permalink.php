<?php
/**
 * Class handles unit tests for occurrence-aware permalinks.
 *
 * Two separate defects share one symptom, a link to an occurrence resolving to
 * a different date. They share this file because they share the fixture that
 * can tell them apart.
 *
 * The first is `Context::permalink()` declining to answer for the request's
 * own occurrence, so `get_permalink()` on `/event/my-series/20260903T180000/`
 * returned the bare series URL. Every link emitted from that page inherited it:
 * the iCal `URL:` field, `rel="canonical"`, share links.
 *
 * The second is the RSVP confirmation email, composed from `Rsvp\Token` while
 * the comment is being inserted, on paths that never reach `wp`. There is no
 * request context to read, so the comment's own `_gatherpress_occurrence` term
 * is the authoritative source.
 *
 * **Every assertion here names an exact URL**, and every fixture puts the
 * occurrence under test somewhere other than first. A bare series URL resolves
 * to the *next upcoming* occurrence, so a test that asserted only "the URL
 * contains an occurrence segment", or that pointed at the first occurrence,
 * would stay green with both fixes reverted.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Calendar\Setup as Calendar_Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rewrite;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Event\Rest_Api;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Core\Rsvp\Token;
use GatherPress\Core\Settings;
use GatherPress\Core\Topic;
use GatherPress\Tests\Base;
use WP_REST_Request;

/**
 * Class Test_Permalink.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Context
 */
class Test_Permalink extends Base {

	/**
	 * The reference weekly rule, matching `Occurrence_Fixtures::expected_weekly_set()`.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const WEEKLY_RULE = array(
		'frequency' => 'weekly',
		'interval'  => 2,
		'weekdays'  => array( 2, 4 ),
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * Bodies of every email `wp_mail()` was asked to send.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	protected array $sent_mail = array();

	/**
	 * Series-widening filters installed by `widen_series()`, for teardown.
	 *
	 * @since 0.36.0
	 * @var callable[]
	 */
	protected array $series_filters = array();

	/**
	 * Create the occurrence table, register the RSVP routes, and put the event
	 * post type behind pretty permalinks with the occurrence rewrite rule
	 * flushed, so `get_permalink()` and `$this->go_to()` both exercise the real
	 * URL shape rather than the plain `?p=` form.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		Rsvp_Setup::get_instance()->register_taxonomy();
		Rest_Api::get_instance()->register_endpoints();
		Calendar_Setup::get_instance()->register_endpoints();
		Settings::get_instance()->set( 'enable_open_rsvp', true );

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		// The event post type's permastruct was built at bootstrap while
		// permalinks were still plain, so it has to be re-registered now that
		// they are pretty or `get_permalink()` keeps answering with `?p=`.
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		unregister_taxonomy( Topic::TAXONOMY );
		Topic::get_instance()->register_taxonomy();

		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();

		Context::get_instance()->clear();
		Context::flush_resolved();

		$this->sent_mail = array();

		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	/**
	 * Restore plain permalinks and leave no context or mail capture behind.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );

		foreach ( $this->series_filters as $filter ) {
			remove_filter( 'gatherpress_series_post_ids', $filter, 10 );
		}

		$this->series_filters = array();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules();

		Context::get_instance()->clear();
		Context::flush_resolved();

		parent::tearDown();
	}

	/**
	 * Short-circuit `wp_mail()` and record the body it was handed.
	 *
	 * @since 0.36.0
	 *
	 * @param null|bool $short_circuit Short-circuit value, null when nothing has filtered yet.
	 * @param array     $attributes    The `wp_mail()` arguments.
	 *
	 * @return bool True, so nothing is actually delivered from a test run.
	 */
	public function capture_mail( $short_circuit, array $attributes ): bool {
		$this->sent_mail[] = (string) ( $attributes['message'] ?? '' );

		return true;
	}

	/**
	 * Build the anchor every fixture here is dated from.
	 *
	 * Thirty days out, so nothing is ever in the past: `Rsvp\Form::process_rsvp()`
	 * bails 400 on `has_event_past()`, reading the *series* meta, and against a
	 * pinned anchor the email tests would silently stop writing anything once
	 * real time crossed it.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The anchor start, in the fixture timezone.
	 */
	protected function relative_anchor(): DateTimeImmutable {
		return ( new DateTimeImmutable( 'now', new DateTimeZone( 'America/New_York' ) ) )
			->modify( '+30 days' )
			->setTime( 18, 0 );
	}

	/**
	 * Create and project the reference recurring series.
	 *
	 * Returns the post ID alongside the identifiers projection actually wrote,
	 * read back from storage rather than restated, so the fixture and the
	 * assertions cannot drift apart however the anchor moves.
	 *
	 * @since 0.36.0
	 *
	 * @return array{0: int, 1: string, 2: string} Post ID, first occurrence, second occurrence.
	 */
	protected function create_series(): array {
		$anchor  = $this->relative_anchor();
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $anchor->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $anchor->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'America/New_York',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );
		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::WEEKLY_RULE ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );
		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertGreaterThan(
			1,
			count( $rows ),
			'Failed to project the two sibling occurrences every test in this class needs.'
		);

		return array( (int) $post_id, (string) $rows[0]['recurrence_id'], (string) $rows[1]['recurrence_id'] );
	}

	/**
	 * Create an ordinary, never-recurring event on a site with no recurring events.
	 *
	 * @since 0.36.0
	 *
	 * @return int The created post ID.
	 */
	protected function create_plain_event(): int {
		$anchor  = $this->relative_anchor();
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $anchor->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $anchor->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'America/New_York',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );
		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		return (int) $post_id;
	}

	/**
	 * Dispatch one open-RSVP submission through the real REST server.
	 *
	 * `rest_do_request()`, never `Form::process_rsvp()` directly: the argument
	 * definition, its validation and the occurrence-context entry all live
	 * between the two, and the confirmation email is composed inside the
	 * innermost one.
	 *
	 * @since 0.36.0
	 *
	 * @param array $params Request parameters.
	 *
	 * @return \WP_REST_Response The dispatched response.
	 */
	protected function submit_rsvp( array $params ) {
		$request = new WP_REST_Request(
			'POST',
			sprintf( '/%s/event/rsvp-form', GATHERPRESS_REST_NAMESPACE )
		);

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * The permalink of a singular occurrence page is the occurrence's own URL.
	 *
	 * The requested occurrence is the series' **second**, so the bare-series
	 * fallback produces a different answer, since it resolves to the next
	 * upcoming occurrence, the first. Both are asserted: the exact URL the
	 * requirement specifies, and the explicit absence of the fallback's answer,
	 * so the test cannot pass with `permalink()` dead.
	 *
	 * Context is established by `go_to()` rather than by hand, so the `wp`
	 * action that binds it in production is part of what is under test.
	 *
	 * @covers ::permalink
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_permalink_on_a_singular_occurrence_page_names_the_requested_occurrence(): void {
		list( $post_id, $first, $second ) = $this->create_series();

		$series_url = (string) get_permalink( $post_id );
		$second_url = $series_url . $second . '/';

		$this->go_to( $second_url );

		$this->assertSame(
			$second,
			Context::get_instance()->current()['recurrence_id'],
			'Failed to assert the request established the second occurrence as the page\'s own. Without that'
				. ' the fixture proves nothing.'
		);
		$this->assertSame(
			$second_url,
			(string) get_permalink( $post_id ),
			'Failed to assert the permalink of a singular occurrence page is that occurrence\'s own URL.'
		);
		$this->assertNotSame(
			$series_url . $first . '/',
			(string) get_permalink( $post_id ),
			'Failed to assert the permalink is not the next-upcoming occurrence the bare series URL resolves to.'
		);
		$this->assertNotSame(
			$series_url,
			(string) get_permalink( $post_id ),
			'Failed to assert the permalink is not the bare series URL.'
		);
	}

	/**
	 * The iCal `VEVENT`'s `URL:` field names the occurrence being viewed.
	 *
	 * `Calendar::get_ical_event_string()` composes it from
	 * `get_permalink( $this->event->event->ID )`, so adding an occurrence to a
	 * calendar used to yield an entry linking to a different date. Nothing in
	 * the calendar subsystem changes for this. The field is right once the
	 * permalink is.
	 *
	 * @covers ::permalink
	 * @covers \GatherPress\Core\Calendar\Calendar::get_ical_event_string
	 *
	 * @return void
	 */
	public function test_ical_url_field_on_an_occurrence_page_names_the_requested_occurrence(): void {
		list( $post_id, $first, $second ) = $this->create_series();

		$series_url = (string) get_permalink( $post_id );
		$second_url = $series_url . $second . '/';

		$this->go_to( $second_url );

		$vevent = ( new Calendar( $post_id ) )->get_ical_event_string();

		$this->assertStringContainsString(
			sprintf( "URL:%s\r\n", $second_url ),
			$vevent,
			'Failed to assert the VEVENT URL field names the occurrence the page is rendering.'
		);
		$this->assertStringNotContainsString(
			sprintf( "URL:%s\r\n", $series_url . $first . '/' ),
			$vevent,
			'Failed to assert the VEVENT URL field is not the next-upcoming occurrence.'
		);
		$this->assertStringNotContainsString(
			sprintf( "URL:%s\r\n", $series_url ),
			$vevent,
			'Failed to assert the VEVENT URL field is not the bare series URL.'
		);
	}

	/**
	 * The RSVP confirmation email links to the occurrence the responder took.
	 *
	 * Driven through `rest_do_request()` on the `rsvp-form` route, which is the
	 * entry point a browser reaches. The occurrence term the link is composed
	 * from is written by `Rsvp\Form` a few frames below it, and a test
	 * calling `Token::generate_url()` directly would have to stamp that term by
	 * hand and would prove nothing about the wiring.
	 *
	 * The RSVP is taken on the **second** occurrence, so the bare-series answer
	 * and the correct answer differ. This is the outbound half of the defect:
	 * once sent, a link to the wrong date cannot be corrected.
	 *
	 * **This test is satisfied by either fix, and that is deliberate.** It
	 * pins the user-visible outcome on the ordinary path. The REST route enters
	 * occurrence context before dispatch, so `Context::permalink()` alone gets
	 * the URL right here even with `Token::get_event_url()` reverted; measured,
	 * that mutation survives this test. `test_..._on_a_widened_series` below is
	 * the one that isolates the email's own mechanism.
	 *
	 * @covers \GatherPress\Core\Rsvp\Token::generate_url
	 * @covers \GatherPress\Core\Rsvp\Token::get_event_url
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::occurrence_for_comment
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::series_post_id_from_slug
	 *
	 * @return void
	 */
	public function test_rsvp_confirmation_email_links_to_the_occurrence_the_responder_took(): void {
		list( $post_id, $first, $second ) = $this->create_series();

		$series_url = (string) get_permalink( $post_id );
		$response   = $this->submit_rsvp(
			array(
				'comment_post_ID' => $post_id,
				'author'          => 'Ada Lovelace',
				'email'           => 'ada@example.test',
				'recurrence_id'   => $second,
			)
		);

		$this->assertSame( 200, $response->get_status(), 'Failed to assert the RSVP submission succeeded.' );

		$comment_id = (int) $response->get_data()['comment_id'];

		$this->assertSame(
			array( Rsvp_Occurrence::term_slug( $post_id, $second ) ),
			wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'slugs' ) ),
			'Failed to assert the RSVP was bound to the second occurrence. Without that the email has nothing'
				. ' authoritative to read.'
		);
		$this->assertCount( 1, $this->sent_mail, 'Failed to assert one confirmation email was sent.' );

		$token = ( new Token( $comment_id ) )->get_token();

		$this->assertNotEmpty( $token, 'Failed to assert the confirmation token was generated.' );

		$expected = add_query_arg(
			Token::NAME,
			sprintf( '%d_%s', $comment_id, $token ),
			$series_url . $second . '/'
		);

		$this->assertStringContainsString(
			esc_url( $expected ),
			$this->sent_mail[0],
			'Failed to assert the confirmation email links to the occurrence the responder RSVPd to.'
		);
		$this->assertStringNotContainsString(
			esc_url(
				add_query_arg(
					Token::NAME,
					sprintf( '%d_%s', $comment_id, $token ),
					$series_url . $first . '/'
				)
			),
			$this->sent_mail[0],
			'Failed to assert the confirmation email does not link to the next-upcoming occurrence.'
		);
		$this->assertStringNotContainsString(
			esc_url( add_query_arg( Token::NAME, sprintf( '%d_%s', $comment_id, $token ), $series_url ) ),
			$this->sent_mail[0],
			'Failed to assert the confirmation email does not link to the bare series URL.'
		);
	}

	/**
	 * The calendar endpoint URL is a series URL, in or out of occurrence context.
	 *
	 * `Calendar::get_endpoint_url()` appends a path segment to the post's
	 * permalink. Once `Context::permalink()` answers with an occurrence's URL,
	 * a naive read produces `/event/my-series/20260903T180000/ical/`, which
	 * matches no rewrite rule and 404s. The "Download iCal" button on an
	 * occurrence page would break. This was already true inside a Query Loop
	 * before the singular-page arm existed, since the loop arm rewrote the same
	 * read; both are fixed by the same series-permalink read.
	 *
	 * Both halves are asserted: the exact URL, and that the URL actually
	 * resolves. The exact URL alone would not catch a rule that matched but
	 * routed somewhere else, and the 404 check alone would stay green on a URL
	 * that resolved to the wrong thing.
	 *
	 * @covers \GatherPress\Core\Calendar\Calendar::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_calendar_endpoint_url_stays_a_series_url_on_an_occurrence_page(): void {
		list( $post_id, , $second ) = $this->create_series();

		$series_url = (string) get_permalink( $post_id );

		$this->go_to( $series_url . $second . '/' );

		$ical_url = ( new Calendar( $post_id ) )->get_ical_url();

		$this->assertSame(
			$second,
			Context::get_instance()->current()['recurrence_id'],
			'Failed to assert the page is an occurrence page; the fixture proves nothing otherwise.'
		);
		$this->assertSame(
			$series_url . Calendar_Setup::ICAL_SLUG . '/',
			$ical_url,
			'Failed to assert the iCal endpoint URL is composed on the series permalink.'
		);

		$this->go_to( (string) $ical_url );

		$this->assertFalse(
			is_404(),
			'Failed to assert the iCal endpoint URL emitted from an occurrence page actually resolves.'
		);
	}

	/**
	 * The confirmation email reads the comment's term, not the request.
	 *
	 * The series-wide resolution case, and the only fixture where the two mechanisms disagree.
	 * The request names a sibling post carrying no occurrence rows of its own,
	 * and `Series::resolve_post_ids()` reaches the occurrence on the owner post.
	 * `Context::resolve()` matches an occurrence to a post by exact
	 * `series_post_id`, so on the *sibling* it correctly declines and
	 * `get_permalink()` answers with the sibling's bare URL. That leaves the
	 * comment's own `_gatherpress_occurrence` term, which `assign_occurrence()`
	 * keyed on the **owner**, as the only thing that knows where the occurrence
	 * lives.
	 *
	 * Three URLs are therefore distinct here, and the assertions name all
	 * three: the owner's occurrence URL (correct), the sibling's bare URL (what
	 * `get_permalink()` alone yields) and the owner's bare URL (what a fix
	 * reading only the recurrence identifier and reusing `comment_post_ID`
	 * would yield).
	 *
	 * @covers \GatherPress\Core\Rsvp\Token::get_event_url
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::occurrence_for_comment
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::series_post_id_from_slug
	 *
	 * @return void
	 */
	public function test_rsvp_confirmation_email_names_the_occurrences_own_post_on_a_widened_series(): void {
		list( $owner_id, , $second ) = $this->create_series();

		$sibling_id = $this->create_plain_event();

		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();
		$this->widen_series( $sibling_id, $owner_id );

		$owner_url   = (string) get_permalink( $owner_id );
		$sibling_url = (string) get_permalink( $sibling_id );
		$response    = $this->submit_rsvp(
			array(
				'comment_post_ID' => $sibling_id,
				'author'          => 'Margaret Hamilton',
				'email'           => 'margaret@example.test',
				'recurrence_id'   => $second,
			)
		);

		$this->assertSame(
			200,
			$response->get_status(),
			'Failed to assert the RSVP on the widened series was accepted.'
		);

		$comment_id = (int) $response->get_data()['comment_id'];

		$this->assertSame(
			array( Rsvp_Occurrence::term_slug( $owner_id, $second ) ),
			wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'slugs' ) ),
			'Failed to assert the RSVP was stamped with the occurrence\'s own post, not the post the request'
				. ' named. Without that the email has nothing to distinguish the two.'
		);

		$token       = ( new Token( $comment_id ) )->get_token();
		$token_value = sprintf( '%d_%s', $comment_id, $token );

		$this->assertCount( 1, $this->sent_mail, 'Failed to assert one confirmation email was sent.' );
		$this->assertNotSame(
			$owner_url,
			$sibling_url,
			'Failed to assert the two posts have distinct permalinks; the fixture proves nothing otherwise.'
		);
		$this->assertStringContainsString(
			esc_url( add_query_arg( Token::NAME, $token_value, $owner_url . $second . '/' ) ),
			$this->sent_mail[0],
			'Failed to assert the confirmation email links to the occurrence on the post it actually lives on.'
		);
		$this->assertStringNotContainsString(
			esc_url( add_query_arg( Token::NAME, $token_value, $sibling_url ) ),
			$this->sent_mail[0],
			'Failed to assert the email is not the bare permalink of the post the request named.'
		);
		$this->assertStringNotContainsString(
			esc_url( add_query_arg( Token::NAME, $token_value, $owner_url ) ),
			$this->sent_mail[0],
			'Failed to assert the email is not the owner post\'s bare series URL either.'
		);
	}

	/**
	 * Treat two posts as one notional series for the duration of a test.
	 *
	 * @since 0.36.0
	 *
	 * @param int $member Post ID with no occurrence rows of its own.
	 * @param int $owner  Post ID that owns the occurrence rows.
	 *
	 * @return void
	 */
	protected function widen_series( int $member, int $owner ): void {
		$filter = static function ( array $post_ids, int $post_id ) use ( $member, $owner ): array {
			return ( $member === $post_id || $owner === $post_id )
				? array( $member, $owner )
				: $post_ids;
		};

		add_filter( 'gatherpress_series_post_ids', $filter, 10, 2 );

		Context::flush_resolved();

		$this->series_filters[] = $filter;
	}

	/**
	 * An RSVP on an ordinary event still gets the plain event permalink.
	 *
	 * The other arm of `Token::get_event_url()`, and the shape every
	 * non-recurring site keeps.
	 *
	 * @covers \GatherPress\Core\Rsvp\Token::get_event_url
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::occurrence_for_comment
	 *
	 * @return void
	 */
	public function test_rsvp_confirmation_email_on_a_plain_event_keeps_the_event_permalink(): void {
		$post_id  = $this->create_plain_event();
		$response = $this->submit_rsvp(
			array(
				'comment_post_ID' => $post_id,
				'author'          => 'Grace Hopper',
				'email'           => 'grace@example.test',
			)
		);

		$this->assertSame( 200, $response->get_status(), 'Failed to assert the RSVP submission succeeded.' );

		$comment_id = (int) $response->get_data()['comment_id'];
		$token      = ( new Token( $comment_id ) )->get_token();
		$expected   = add_query_arg(
			Token::NAME,
			sprintf( '%d_%s', $comment_id, $token ),
			(string) get_permalink( $post_id )
		);

		$this->assertCount( 1, $this->sent_mail, 'Failed to assert one confirmation email was sent.' );
		$this->assertStringContainsString(
			esc_url( $expected ),
			$this->sent_mail[0],
			'Failed to assert an ordinary event\'s confirmation email keeps its plain permalink.'
		);
	}

	/**
	 * The email path costs a non-recurring site no query and no option write.
	 *
	 * Captured across `rest_do_request()` on the `rsvp-form` route rather than
	 * across `Token::generate_url()`. That route is the real entry point and is
	 * where the confirmation email is composed, and a capture around the body of
	 * the work is exactly the shape that leaves an entry point unguarded.
	 *
	 * Both halves are asserted. The query half proves nothing reached the
	 * occurrence table or the occurrence taxonomy; the option half proves the
	 * autoloaded flag was read, not written, and that nothing else was added.
	 *
	 * @covers \GatherPress\Core\Rsvp\Token::get_event_url
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::occurrence_for_comment
	 *
	 * @return void
	 */
	public function test_email_path_touches_no_occurrence_storage_on_a_non_recurring_site(): void {
		global $wpdb;

		$post_id = $this->create_plain_event();

		wp_cache_delete( 'alloptions', 'options' );

		$options_before = wp_load_alloptions();
		$queries_before = count( $wpdb->queries );

		$response = $this->submit_rsvp(
			array(
				'comment_post_ID' => $post_id,
				'author'          => 'Katherine Johnson',
				'email'           => 'katherine@example.test',
			)
		);

		$since = array_slice( $wpdb->queries, $queries_before );

		$this->assertSame( 200, $response->get_status(), 'Failed to assert the RSVP submission succeeded.' );
		$this->assertCount( 1, $this->sent_mail, 'Failed to assert the email path actually ran.' );
		$this->assertNotEmpty(
			$since,
			'Failed to capture any query; SAVEQUERIES must be on for this assertion to mean anything.'
		);

		$occurrences_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$this->assertSame(
			array(),
			array_values(
				array_filter(
					$since,
					static function ( array $query ) use ( $occurrences_table ): bool {
						return str_contains( $query[0], $occurrences_table )
							|| str_contains( $query[0], Rsvp_Occurrence::TAXONOMY );
					}
				)
			),
			'Failed to assert the RSVP form route touched no occurrence storage on a non-recurring site.'
		);

		wp_cache_delete( 'alloptions', 'options' );

		$options_after = wp_load_alloptions();

		$this->assertSame(
			array_keys( $options_before ),
			array_keys( $options_after ),
			'Failed to assert the email path added or removed no option.'
		);
		$this->assertSame(
			'0',
			$options_after[ Recurrence_Query::HAS_RECURRING_OPTION ] ?? '0',
			'Failed to assert the has-recurring-events flag is still 0 after the email path ran.'
		);
	}

	/**
	 * A comment carrying no occurrence term resolves to no occurrence.
	 *
	 * The `empty( $slugs )` arm of `occurrence_for_comment()`, reached on a
	 * recurring site so the no-recurring-events guard above it is not what answers.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::occurrence_for_comment
	 *
	 * @return void
	 */
	public function test_occurrence_for_comment_is_null_for_an_unstamped_comment(): void {
		list( $post_id ) = $this->create_series();

		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$this->assertNull(
			Rsvp_Occurrence::occurrence_for_comment( (int) $comment_id ),
			'Failed to assert a comment with no occurrence term resolves to no occurrence.'
		);
	}

	/**
	 * Occurrence resolution is refused before any term read on a plain site, and
	 * for a non-positive comment ID.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::occurrence_for_comment
	 *
	 * @return void
	 */
	public function test_occurrence_for_comment_is_null_without_recurring_events_or_a_comment(): void {
		list( $post_id, , $second ) = $this->create_series();

		$comment_id = (int) $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		Rsvp_Occurrence::get_instance()->assign( $comment_id, $post_id, $second );

		$this->assertNotNull(
			Rsvp_Occurrence::occurrence_for_comment( $comment_id ),
			'Failed to assert the stamped comment resolves while the site has recurring events. Without that'
				. ' the negative below would be produced by the fixture rather than by the guard.'
		);

		$this->assertNull(
			Rsvp_Occurrence::occurrence_for_comment( 0 ),
			'Failed to assert a non-positive comment ID resolves to no occurrence.'
		);

		update_option( Recurrence_Query::HAS_RECURRING_OPTION, '0' );

		$this->assertNull(
			Rsvp_Occurrence::occurrence_for_comment( $comment_id ),
			'Failed to assert the no-recurring-events guard refuses before reading a term relationship.'
		);
	}

	/**
	 * A term slug `term_slug()` did not produce resolves to no occurrence.
	 *
	 * `occurrence_for_comment()` refuses rather than composing a URL from half
	 * a composite. Reachable in production because nothing stops a third party
	 * from assigning its own term in this taxonomy, and a slug that parses to a
	 * post ID of zero would otherwise send the responder a link built on
	 * `get_permalink( 0 )`.
	 *
	 * Both arms of the guard are driven: a slug carrying no separator at all,
	 * where neither half parses, and one carrying a separator with a
	 * non-numeric prefix, where the identifier parses and the post ID does not.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::occurrence_for_comment
	 *
	 * @return void
	 */
	public function test_occurrence_for_comment_refuses_a_slug_it_did_not_write(): void {
		list( $post_id ) = $this->create_series();

		$comment_id = (int) $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		wp_set_object_terms( $comment_id, 'legacy', Rsvp_Occurrence::TAXONOMY );

		$this->assertSame(
			array( 'legacy' ),
			wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'slugs' ) ),
			'Failed to assert the foreign term was assigned; the guard is not what answers otherwise.'
		);
		$this->assertNull(
			Rsvp_Occurrence::occurrence_for_comment( $comment_id ),
			'Failed to assert a slug with no separator resolves to no occurrence.'
		);

		wp_set_object_terms( $comment_id, 'a-20260903t180000', Rsvp_Occurrence::TAXONOMY );

		$this->assertSame(
			'20260903T180000',
			Rsvp_Occurrence::recurrence_id_from_slug( 'a-20260903t180000' ),
			'Failed to assert the identifier half of this slug does parse. Without that, the null below'
				. ' would be produced by the same arm as the case above rather than by the post-ID half.'
		);
		$this->assertNull(
			Rsvp_Occurrence::occurrence_for_comment( $comment_id ),
			'Failed to assert a slug whose prefix is not a post ID resolves to no occurrence.'
		);
	}

	/**
	 * The series post ID is recovered from a term slug, and refused otherwise.
	 *
	 * Every return path of `series_post_id_from_slug()`, driven directly
	 * because xdebug does not reliably trace a same-class static helper reached
	 * only through its caller.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::series_post_id_from_slug
	 *
	 * @return void
	 */
	public function test_series_post_id_from_slug_covers_every_return_path(): void {
		$this->assertSame(
			12,
			Rsvp_Occurrence::series_post_id_from_slug( Rsvp_Occurrence::term_slug( 12, '20260903T180000' ) ),
			'Failed to assert the series post ID round-trips out of a real term slug.'
		);
		$this->assertNull(
			Rsvp_Occurrence::series_post_id_from_slug( '20260903t180000' ),
			'Failed to assert a slug with no separator carries no series post ID.'
		);
		$this->assertNull(
			Rsvp_Occurrence::series_post_id_from_slug( 'series-20260903t180000' ),
			'Failed to assert a non-numeric prefix carries no series post ID.'
		);
		$this->assertNull(
			Rsvp_Occurrence::series_post_id_from_slug( '0-20260903t180000' ),
			'Failed to assert a zero prefix carries no series post ID.'
		);
		$this->assertNull(
			Rsvp_Occurrence::series_post_id_from_slug( '12-extra-20260903t180000' ),
			'Failed to assert the split is on the LAST separator, the same one `recurrence_id_from_slug()`'
				. ' splits on. Splitting on the first would answer 12 here while its sibling answered with the'
				. ' identifier, and the pair would no longer be one inverse of `term_slug()`.'
		);
	}
}
