<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Date_Pages.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event\Current_Occurrence;
use GatherPress\Core\Event\Date_Pages;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Occurrence;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Date_Pages.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Date_Pages
 */
class Test_Date_Pages extends Base {

	/**
	 * The last redirect, as URL and status.
	 *
	 * @since TBD
	 * @var array{0: string, 1: int}|null
	 */
	protected ?array $redirected = null;

	/**
	 * Capture redirects instead of sending them, and reset the notice and current dates.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->redirected = null;
		add_filter( 'wp_redirect', array( $this, 'capture_redirect' ), 10, 2 );
		Utility::set_and_get_hidden_property( Date_Pages::get_instance(), 'noticed', false );
		Utility::set_and_get_hidden_property( Current_Occurrence::get_instance(), 'current', array() );
	}

	/**
	 * Record a redirect and stop it.
	 *
	 * @since TBD
	 *
	 * @param string $location The URL.
	 * @param int    $status   The status.
	 *
	 * @return bool False, so no header is sent.
	 */
	public function capture_redirect( string $location, int $status ): bool {
		$this->redirected = array( $location, $status );

		return false;
	}

	/**
	 * A published series repeating Mondays and Fridays every other week from a Monday in 2026.
	 *
	 * Its dates run Nov 30, Dec 4, Dec 14, Dec 18, Dec 28 and so on, at 19:00 UTC.
	 *
	 * @since TBD
	 *
	 * @param string|null $ends_on The last date, if any.
	 *
	 * @return int The post ID.
	 */
	protected function series( ?string $ends_on = null ): int {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
				'post_name'   => 'biweekly',
			)
		);

		( new Event( $post_id ) )->rules()->replace(
			array(
				array(
					'starts_on' => '2026-11-30',
					'ends_on'   => $ends_on,
					'rrule'     => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,FR',
					'times'     => array( '19:00' ),
					'timezone'  => 'UTC',
					'duration'  => 90,
				),
			)
		);
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		return $post_id;
	}

	/**
	 * Visit a date URL of an event, with plain permalinks, and resolve it.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $key     The key in the URL.
	 *
	 * @return void
	 */
	protected function visit( int $post_id, string $key ): void {
		$this->go_to( add_query_arg( Date_Pages::QUERY_VAR, $key, get_permalink( $post_id ) ) );
		Date_Pages::get_instance()->resolve();
	}

	/**
	 * The class's hooks.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Date_Pages::get_instance();

		$this->assert_hooks(
			array(
				array(
					'type'     => 'action',
					'name'     => 'init',
					'priority' => 11,
					'callback' => array( $instance, 'add_rewrite_rules' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'template_redirect',
					'priority' => 10,
					'callback' => array( $instance, 'resolve' ),
				),
				array(
					'type'     => 'filter',
					'name'     => 'query_vars',
					'priority' => 10,
					'callback' => array( $instance, 'add_query_var' ),
				),
				array(
					'type'     => 'filter',
					'name'     => 'post_type_link',
					'priority' => 10,
					'callback' => array( $instance, 'link' ),
				),
				array(
					'type'     => 'filter',
					'name'     => 'the_content',
					'priority' => 10,
					'callback' => array( $instance, 'add_notice' ),
				),
			),
			$instance
		);
	}

	/**
	 * A rule routes each event post type's date URLs, and the key is a query var.
	 *
	 * @since TBD
	 *
	 * @covers ::add_rewrite_rules
	 * @covers ::add_query_var
	 *
	 * @return void
	 */
	public function test_routing(): void {
		global $wp_rewrite;

		register_post_type(
			'gp_unrouted',
			array(
				'public'   => true,
				'rewrite'  => false,
				'supports' => array( Event::SUPPORT ),
			)
		);

		Date_Pages::get_instance()->add_rewrite_rules();

		$slug  = get_post_type_object( Event::POST_TYPE )->rewrite['slug'];
		$rules = $wp_rewrite->extra_rules_top;

		$this->assertArrayHasKey( '^' . $slug . '/([^/]+)/(\d{8}(?:T\d{6})?)/?$', $rules );
		$this->assertStringContainsString( Date_Pages::QUERY_VAR . '=$matches[2]', implode( ' ', $rules ) );
		$this->assertContains( Date_Pages::QUERY_VAR, Date_Pages::get_instance()->add_query_var( array() ) );

		unregister_post_type( 'gp_unrouted' );
	}

	/**
	 * A date's URL is a path segment with pretty permalinks, and a query arg without.
	 *
	 * @since TBD
	 *
	 * @covers ::url
	 *
	 * @return void
	 */
	public function test_url(): void {
		$this->assertSame(
			'https://example.org/event/biweekly/20261214T190000/',
			Date_Pages::url( 'https://example.org/event/biweekly/', '20261214T190000' )
		);
		$this->assertSame(
			'https://example.org/?gatherpress_event=biweekly&gatherpress_occurrence=20261214T190000',
			Date_Pages::url( 'https://example.org/?gatherpress_event=biweekly', '20261214T190000' )
		);
	}

	/**
	 * A repeating event's row links to its date, and an event without rules keeps its link.
	 *
	 * @since TBD
	 *
	 * @covers ::link
	 *
	 * @return void
	 */
	public function test_link(): void {
		$series   = $this->series();
		$one_off  = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$instance = Date_Pages::get_instance();
		$date     = array(
			'recurrence_id'      => '20261214T190000',
			'datetime_start'     => '2026-12-14 19:00:00',
			'datetime_start_gmt' => '2026-12-14 19:00:00',
			'datetime_end'       => '2026-12-14 20:30:00',
			'datetime_end_gmt'   => '2026-12-14 20:30:00',
			'timezone'           => 'UTC',
		);

		$this->assertSame( 'https://example.org/e/', $instance->link( 'https://example.org/e/', get_post( $series ) ) );

		Current_Occurrence::get_instance()->set( $series, $date );
		Current_Occurrence::get_instance()->set( $one_off, $date );

		$this->assertSame(
			'https://example.org/e/20261214T190000/',
			$instance->link( 'https://example.org/e/', get_post( $series ) )
		);
		$this->assertSame(
			'https://example.org/o/',
			$instance->link( 'https://example.org/o/', get_post( $one_off ) )
		);
	}

	/**
	 * A date URL shows that date on the event's page.
	 *
	 * @since TBD
	 *
	 * @covers ::resolve
	 * @covers ::fields
	 *
	 * @return void
	 */
	public function test_resolve_shows_the_date(): void {
		$post_id = $this->series();

		$this->visit( $post_id, '20261218T190000' );

		$this->assertNull( $this->redirected );
		$this->assertSame(
			'2026-12-18 19:00:00',
			Current_Occurrence::get_instance()->get( $post_id )['datetime_start'] ?? null
		);
		$this->assertSame( '2026-12-18 19:00:00', ( new Event( $post_id ) )->get_datetime()['datetime_start'] );
	}

	/**
	 * A date-only URL, or a key whose time moved, goes to that day's date for good.
	 *
	 * @since TBD
	 *
	 * @covers ::resolve
	 * @covers ::send_elsewhere
	 * @covers ::day_of
	 * @covers ::first_on
	 * @covers ::redirect
	 *
	 * @return void
	 */
	public function test_resolve_same_day(): void {
		$post_id = $this->series();
		$target  = Date_Pages::url( get_permalink( $post_id ), '20261214T190000' );

		$this->visit( $post_id, '20261214' );
		$this->assertSame( array( $target, 301 ), $this->redirected );

		$this->visit( $post_id, '20261214T200000' );
		$this->assertSame( array( $target, 301 ), $this->redirected );
	}

	/**
	 * A date the series no longer has goes to the next date, with the removed key for the notice.
	 *
	 * @since TBD
	 *
	 * @covers ::send_elsewhere
	 * @covers ::within_dates
	 * @covers ::next_after
	 *
	 * @return void
	 */
	public function test_resolve_removed_date(): void {
		$post_id = $this->series();

		// December 8 is a Tuesday, between the series' dates.
		$this->visit( $post_id, '20261208T190000' );

		$this->assertSame(
			array(
				add_query_arg(
					Date_Pages::REMOVED_ARG,
					'20261208T190000',
					Date_Pages::url( get_permalink( $post_id ), '20261214T190000' )
				),
				302,
			),
			$this->redirected
		);

		$ended = $this->series( '2026-12-20' );

		// After the last date: the series page, with the notice.
		$this->visit( $ended, '20261219T190000' );

		$this->assertSame(
			array( add_query_arg( Date_Pages::REMOVED_ARG, '20261219T190000', get_permalink( $ended ) ), 302 ),
			$this->redirected
		);
	}

	/**
	 * A malformed key, or one outside the series' dates, is a 404.
	 *
	 * @since TBD
	 *
	 * @covers ::send_elsewhere
	 * @covers ::not_found
	 * @covers ::day_of
	 *
	 * @return void
	 */
	public function test_resolve_not_found(): void {
		$post_id = $this->series();

		foreach ( array( 'soon', '20261332T190000', '20200101T190000' ) as $key ) {
			$this->visit( $post_id, $key );

			$this->assertTrue( is_404(), $key . ' is a 404.' );
			$this->assertNull( $this->redirected );
		}

		$one_off = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$this->visit( $one_off, '20261214T190000' );
		$this->assertTrue( is_404(), 'An event without rules has no other dates.' );
	}

	/**
	 * A page that isn't an event, or has no key, is left alone.
	 *
	 * @since TBD
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolve_ignores_other_pages(): void {
		$page = $this->factory()->post->create( array( 'post_type' => 'page' ) );

		$this->go_to( add_query_arg( Date_Pages::QUERY_VAR, '20261214T190000', get_permalink( $page ) ) );
		Date_Pages::get_instance()->resolve();

		$this->assertFalse( is_404() );

		$this->go_to( get_permalink( $this->series() ) );
		Date_Pages::get_instance()->resolve();

		$this->assertNull( $this->redirected );
	}

	/**
	 * The page a redirect lands on says the bookmarked date is no longer on the schedule, once.
	 *
	 * @since TBD
	 *
	 * @covers ::add_notice
	 * @covers ::day_of
	 *
	 * @return void
	 */
	public function test_add_notice(): void {
		$instance = Date_Pages::get_instance();
		$post_id  = $this->series();

		update_option( 'date_format', 'F j, Y' );
		$this->set_url_params( array( Date_Pages::REMOVED_ARG => '20261208T190000' ) );
		$this->visit( $post_id, '20261214T190000' );
		setup_postdata( get_post( $post_id ) );

		$content = $instance->add_notice( '<p>Body</p>' );

		$this->assertStringContainsString(
			'December 8, 2026 is no longer on the schedule. This is the next date.',
			$content
		);
		$this->assertStringEndsWith( '<p>Body</p>', $content );
		$this->assertSame( '<p>Again</p>', $instance->add_notice( '<p>Again</p>' ), 'Once per page.' );

		Utility::set_and_get_hidden_property( $instance, 'noticed', false );
		Utility::set_and_get_hidden_property( Current_Occurrence::get_instance(), 'current', array() );
		$this->go_to( get_permalink( $post_id ) );
		setup_postdata( get_post( $post_id ) );

		$this->assertStringContainsString(
			'December 8, 2026 is no longer on the schedule.</p>',
			$instance->add_notice( '' ),
			'On the series page, without "next date".'
		);

		Utility::set_and_get_hidden_property( $instance, 'noticed', false );
		$this->set_url_params( array( Date_Pages::REMOVED_ARG => 'soon' ) );

		$this->assertSame( '<p>Body</p>', $instance->add_notice( '<p>Body</p>' ), 'Not for a malformed key.' );

		wp_reset_postdata();
	}

	/**
	 * An occurrence's fields, local and in UTC.
	 *
	 * @since TBD
	 *
	 * @covers ::fields
	 *
	 * @return void
	 */
	public function test_fields(): void {
		$zone       = new DateTimeZone( 'America/New_York' );
		$occurrence = new Occurrence(
			7,
			'20261214T190000',
			new DateTimeImmutable( '2026-12-14 19:00:00', $zone ),
			new DateTimeImmutable( '2026-12-14 20:30:00', $zone )
		);

		$this->assertSame(
			array(
				'recurrence_id'      => '20261214T190000',
				'datetime_start'     => '2026-12-14 19:00:00',
				'datetime_start_gmt' => '2026-12-15 00:00:00',
				'datetime_end'       => '2026-12-14 20:30:00',
				'datetime_end_gmt'   => '2026-12-15 01:30:00',
				'timezone'           => 'America/New_York',
			),
			Date_Pages::fields( $occurrence )
		);
	}

	/**
	 * The day helpers: no date on a day, and a day outside the series' dates.
	 *
	 * @since TBD
	 *
	 * @covers ::first_on
	 * @covers ::within_dates
	 * @covers ::next_after
	 * @covers ::first_of
	 *
	 * @return void
	 */
	public function test_day_helpers(): void {
		$instance = Date_Pages::get_instance();
		$event    = new Event( $this->series( '2026-12-20' ) );
		$utc      = new DateTimeZone( 'UTC' );
		$tuesday  = new DateTimeImmutable( '2026-12-08', $utc );
		$friday   = new DateTimeImmutable( '2026-12-18', $utc );
		$before   = new DateTimeImmutable( '2026-11-01', $utc );
		$after    = new DateTimeImmutable( '2027-01-01', $utc );

		$this->assertNull( Utility::invoke_hidden_method( $instance, 'first_on', array( $event, $tuesday ) ) );
		$this->assertSame(
			'20261218T190000',
			Utility::invoke_hidden_method( $instance, 'first_on', array( $event, $friday ) )->recurrence_id
		);
		$this->assertTrue( Utility::invoke_hidden_method( $instance, 'within_dates', array( $event, $tuesday ) ) );
		$this->assertFalse( Utility::invoke_hidden_method( $instance, 'within_dates', array( $event, $before ) ) );
		$this->assertFalse( Utility::invoke_hidden_method( $instance, 'within_dates', array( $event, $after ) ) );
		$this->assertSame(
			'20261214T190000',
			Utility::invoke_hidden_method( $instance, 'next_after', array( $event, $tuesday ) )->recurrence_id
		);
		$this->assertNull( Utility::invoke_hidden_method( $instance, 'next_after', array( $event, $friday ) ) );
		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'first_of', array( $event->rules()->all()[0], null, 0 ) )
		);
	}
}
