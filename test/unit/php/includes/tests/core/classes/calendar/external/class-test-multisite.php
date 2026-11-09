<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\External in multisite mode.
 *
 * @package GatherPress\Core\Calendar\External
 * @since   TBD
 */

namespace GatherPress\Tests\Core\Calendar\External;

use GatherPress\Core\Calendar\External\External;
use GatherPress\Core\Calendar\External\Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Tests\Base;

/**
 * Class Test_Multisite.
 *
 * Tests external calendar / ical endpoints and rewrite rules across multisite
 * network configurations (subdomain and subdirectory installs).
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\External\External
 * @group              endpoints
 * @group              multisite
 */
class Test_Multisite extends Base {

	/**
	 * Setup helper to create a published event with datetimes on the current blog.
	 *
	 * @param string $slug  The event post slug.
	 * @param string $title The event title.
	 * @return int The event post ID.
	 */
	private function make_event( string $slug = 'sample-event', string $title = 'Sample Event' ): int {
		$event_id = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => 'publish',
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

		return $event_id;
	}

	/**
	 * Ensures rewrite rules for GatherPress endpoints are registered on the current blog.
	 *
	 * @return void
	 */
	private function refresh_endpoints_and_rewrites(): void {
		global $wp_rewrite;

		update_option( 'permalink_structure', '/%postname%/' );
		$wp_rewrite->init();
		\GatherPress\Core\Event\Setup::get_instance()->register_post_type();
		Topic::get_instance()->register_taxonomy();
		Setup::get_instance()->register_endpoints();
		$wp_rewrite->flush_rules( false );
	}

	/**
	 * Tests that single event and feed ical URLs are properly formed on a subdirectory multisite.
	 *
	 * @covers ::get_ical_url
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_subdirectory_multisite_ical_url_generation(): void {
		$site_id = $this->factory()->blog->create( array( 'path' => '/subsite/' ) );
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		$event_id = $this->make_event( 'test-event-sub' );
		$calendar = new External( $event_id );
		$ical_url = $calendar->get_ical_url();

		$this->assertIsString( $ical_url );
		$this->assertStringContainsString( '/subsite/event/test-event-sub/ical/', $ical_url );
		$this->assertSame(
			home_url( '/event/test-event-sub/ical/' ),
			$ical_url,
			'iCal download URL should respect the subdirectory blog home URL.'
		);

		// Test fallback when permalinks are disabled on the subsite.
		update_option( 'permalink_structure', '' );
		$GLOBALS['wp_rewrite']->init();

		$plain_url = ( new External( $event_id ) )->get_ical_url();
		$this->assertIsString( $plain_url );
		$this->assertStringContainsString( '/subsite/', $plain_url );
		$this->assertStringContainsString( 'gatherpress_calendar=ical', $plain_url );

		restore_current_blog();
	}

	/**
	 * Tests that single event ical URLs are properly formed on a subdomain multisite.
	 *
	 * @covers ::get_ical_url
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_subdomain_multisite_ical_url_generation(): void {
		$site_id = $this->factory()->blog->create(
			array(
				'domain' => 'subsite.example.org',
				'path'   => '/',
			)
		);
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		$event_id = $this->make_event( 'test-event-subdomain' );
		$calendar = new External( $event_id );
		$ical_url = $calendar->get_ical_url();

		$this->assertIsString( $ical_url );
		$this->assertStringContainsString( 'subsite.example.org/event/test-event-subdomain/ical/', $ical_url );
		$this->assertSame(
			home_url( '/event/test-event-subdomain/ical/' ),
			$ical_url,
			'iCal download URL should respect the subdomain blog home URL.'
		);

		restore_current_blog();
	}

	/**
	 * Tests request parsing for single event iCal endpoint on a subdirectory multisite.
	 *
	 * @covers \GatherPress\Core\Calendar\External\Setup::register_endpoints
	 *
	 * @return void
	 */
	public function test_subdirectory_multisite_parse_request_single_event_ical(): void {
		$site_id = $this->factory()->blog->create( array( 'path' => '/subsite/' ) );
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		$this->make_event( 'wp-community-meetup' );
		$endpoint_url = home_url( '/event/wp-community-meetup/ical/' );

		$this->go_to( $endpoint_url );

		$this->assertSame(
			'wp-community-meetup',
			$GLOBALS['wp']->query_vars[ Event::POST_TYPE ] ?? null,
			'Request should parse event post slug on subdirectory multisite.'
		);
		$this->assertSame(
			Setup::ICAL_SLUG,
			$GLOBALS['wp']->query_vars[ Setup::QUERY_VAR ] ?? null,
			'Request should parse gatherpress_ext_calendar query var on subdirectory multisite.'
		);

		restore_current_blog();
	}

	/**
	 * Tests request parsing for event archive iCal feed on a subdirectory multisite.
	 *
	 * @covers \GatherPress\Core\Calendar\External\Setup::register_endpoints
	 *
	 * @return void
	 */
	public function test_subdirectory_multisite_parse_request_archive_feed_ical(): void {
		$site_id = $this->factory()->blog->create( array( 'path' => '/subsite/' ) );
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		$archive_feed_url = home_url( '/event/feed/ical/' );

		$this->go_to( $archive_feed_url );

		$this->assertSame(
			Event::POST_TYPE,
			$GLOBALS['wp']->query_vars['post_type'] ?? null,
			'Request should parse post_type query var for archive feed on subdirectory multisite.'
		);
		$this->assertSame(
			Setup::ICAL_SLUG,
			$GLOBALS['wp']->query_vars[ Setup::QUERY_VAR ] ?? null,
			'Request should parse gatherpress_ext_calendar query var for archive feed on subdirectory multisite.'
		);

		restore_current_blog();
	}

	/**
	 * Tests request parsing for sitewide iCal feed on a subdirectory multisite.
	 *
	 * @covers \GatherPress\Core\Calendar\External\Setup::register_endpoints
	 *
	 * @return void
	 */
	public function test_subdirectory_multisite_parse_request_sitewide_feed(): void {
		$site_id = $this->factory()->blog->create( array( 'path' => '/subsite/' ) );
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		$sitewide_feed_url = home_url( '/feed/ical/' );

		$this->go_to( $sitewide_feed_url );

		$this->assertSame(
			Setup::ICAL_SLUG,
			$GLOBALS['wp']->query_vars['feed'] ?? null,
			'Request should parse feed query var for sitewide feed on subdirectory multisite.'
		);
		$this->assertSame(
			Setup::ICAL_SLUG,
			$GLOBALS['wp']->query_vars[ Setup::QUERY_VAR ] ?? null,
			'Request should parse gatherpress_calendar query var on subdirectory multisite.'
		);

		restore_current_blog();
	}

	/**
	 * Tests request parsing for taxonomy iCal feed on a subdirectory multisite.
	 *
	 * @covers \GatherPress\Core\Calendar\External\Setup::register_endpoints
	 *
	 * @return void
	 */
	public function test_subdirectory_multisite_parse_request_taxonomy_feed(): void {
		$site_id = $this->factory()->blog->create( array( 'path' => '/subsite/' ) );
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		$term = wp_insert_term( 'Technology', Topic::TAXONOMY, array( 'slug' => 'technology' ) );
		$this->assertIsArray( $term );

		$taxonomy_feed_url = home_url( '/topic/technology/feed/ical/' );

		$this->go_to( $taxonomy_feed_url );

		$this->assertSame(
			'technology',
			$GLOBALS['wp']->query_vars[ Topic::TAXONOMY ] ?? null,
			'Request should parse topic taxonomy term on subdirectory multisite.'
		);
		$this->assertSame(
			Setup::ICAL_SLUG,
			$GLOBALS['wp']->query_vars[ Setup::QUERY_VAR ] ?? null,
			'Request should parse gatherpress_ext_calendar query var for taxonomy feed on subdirectory multisite.'
		);

		restore_current_blog();
	}

	/**
	 * Tests request parsing for single event iCal endpoint on a subdomain multisite.
	 *
	 * @covers \GatherPress\Core\Calendar\External\Setup::register_endpoints
	 *
	 * @return void
	 */
	public function test_subdomain_multisite_parse_request_single_event_ical(): void {
		$site_id = $this->factory()->blog->create(
			array(
				'domain' => 'subdomain.example.org',
				'path'   => '/',
			)
		);
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		$this->make_event( 'wordcamp-europe' );
		$endpoint_url = home_url( '/event/wordcamp-europe/ical/' );

		$this->go_to( $endpoint_url );

		$this->assertSame(
			'wordcamp-europe',
			$GLOBALS['wp']->query_vars[ Event::POST_TYPE ] ?? null,
			'Request should parse event post slug on subdomain multisite.'
		);
		$this->assertSame(
			Setup::ICAL_SLUG,
			$GLOBALS['wp']->query_vars[ Setup::QUERY_VAR ] ?? null,
			'Request should parse gatherpress_ext_calendar query var on subdomain multisite.'
		);

		restore_current_blog();
	}

	/**
	 * Tests request parsing for event archive iCal feed on a subdomain multisite.
	 *
	 * @covers \GatherPress\Core\Calendar\External\Setup::register_endpoints
	 *
	 * @return void
	 */
	public function test_subdomain_multisite_parse_request_archive_feed_ical(): void {
		$site_id = $this->factory()->blog->create(
			array(
				'domain' => 'subdomain.example.org',
				'path'   => '/',
			)
		);
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		$archive_feed_url = home_url( '/event/feed/ical/' );

		$this->go_to( $archive_feed_url );

		$this->assertSame(
			Event::POST_TYPE,
			$GLOBALS['wp']->query_vars['post_type'] ?? null,
			'Request should parse post_type query var for archive feed on subdomain multisite.'
		);
		$this->assertSame(
			Setup::ICAL_SLUG,
			$GLOBALS['wp']->query_vars[ Setup::QUERY_VAR ] ?? null,
			'Request should parse gatherpress_ext_calendar query var for archive feed on subdomain multisite.'
		);

		restore_current_blog();
	}

	/**
	 * Tests that path conflict detection does not falsely trigger on subdirectory multisite.
	 *
	 * @covers ::get_endpoint_url
	 *
	 * @return void
	 */
	public function test_subdirectory_multisite_no_false_path_conflict(): void {
		$site_id = $this->factory()->blog->create( array( 'path' => '/community/' ) );
		switch_to_blog( $site_id );

		$this->refresh_endpoints_and_rewrites();

		// Create a normal page on the subsite with slug "community-page".
		$this->factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Community Page',
				'post_name'  => 'community-page',
			)
		);

		$event_id = $this->make_event( 'annual-gathering' );
		$calendar = new External( $event_id );
		$ical_url = $calendar->get_ical_url();

		// Should generate the clean endpoint path without fallback query param.
		$this->assertSame(
			home_url( '/event/annual-gathering/ical/' ),
			$ical_url,
			'Subdirectory URL should not trigger false path conflict.'
		);
		$this->assertFalse(
			str_contains( $ical_url, '?' ),
			'Generated URL should not contain query parameters.'
		);

		restore_current_blog();
	}
}
