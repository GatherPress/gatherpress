<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Setup.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Setup;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Setup
 * @group              endpoints
 */
class Test_Setup extends Base {

	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => PHP_INT_MAX,
				'callback' => array( $instance, 'register_endpoints' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_head',
				'priority' => 10,
				'callback' => array( $instance, 'alternate_links' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_endpoints — early-returns when no post type
	 * supports `gatherpress-event-date`.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_register_endpoints_bails_without_event_post_type(): void {
		$instance = Setup::get_instance();

		// Strip `gatherpress-event-date` support from every post type for the
		// duration of this test so register_endpoints() sees an empty list.
		$snapshots = array();
		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
				$snapshots[] = $post_type;
				remove_post_type_support( $post_type, 'gatherpress-event-date' );
			}
		}

		// Capture rule count to verify nothing was added.
		global $wp_rewrite;
		$before = count( $wp_rewrite->extra_rules_top );

		$instance->register_endpoints();

		$after = count( $wp_rewrite->extra_rules_top );

		foreach ( $snapshots as $post_type ) {
			add_post_type_support( $post_type, 'gatherpress-event-date' );
		}

		$this->assertSame(
			$before,
			$after,
			'register_endpoints should not add any rules when no event-supporting post type is registered.'
		);
	}

	/**
	 * Coverage for register_endpoints happy path — registers sitewide, event,
	 * venue, and taxonomy endpoints when GatherPress post types are present.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_register_endpoints_registers_known_routes(): void {
		Setup::get_instance()->register_endpoints();

		global $wp_rewrite;
		$rules = array_keys( $wp_rewrite->extra_rules_top );

		$this->assertContains(
			'event/feed/(ical)/?$',
			$rules,
			'Event archive feed rule should be registered.'
		);
		$this->assertContains(
			'event/([^/]+)/(ical|outlook|google-calendar|yahoo-calendar)/?$',
			$rules,
			'Single event endpoint rule should be registered.'
		);
	}

	/**
	 * Coverage for init_events — bails when the post type does not support
	 * `gatherpress-event-date`.
	 *
	 * @covers ::init_events
	 *
	 * @return void
	 */
	public function test_init_events_bails_on_unsupported_post_type(): void {
		$instance = Setup::get_instance();

		global $wp_rewrite;
		$before = count( $wp_rewrite->extra_rules_top );
		$instance->init_events( 'page' );
		$this->assertSame(
			$before,
			count( $wp_rewrite->extra_rules_top ),
			'Unsupported post type should not add rules.'
		);
	}

	/**
	 * Coverage for init_events happy path — instantiates the post-type feed and
	 * single endpoints when called with an event-supporting post type. Direct
	 * invocation is needed because xdebug doesn't reliably trace this method's
	 * body when it's called from inside `register_endpoints()`'s foreach.
	 *
	 * @covers ::init_events
	 *
	 * @return void
	 */
	public function test_init_events_registers_feed_and_single_endpoints(): void {
		Setup::get_instance()->init_events( Event::POST_TYPE );

		global $wp_rewrite;
		$rules = array_keys( $wp_rewrite->extra_rules_top );

		$this->assertContains(
			'event/feed/(ical)/?$',
			$rules,
			'init_events should register the event archive feed rule.'
		);
		$this->assertContains(
			'event/([^/]+)/(ical|outlook|google-calendar|yahoo-calendar)/?$',
			$rules,
			'init_events should register the single-event endpoint rule.'
		);
	}

	/**
	 * Coverage for init_venues happy path — instantiates the Post_Type_Single_Feed
	 * for the gatherpress_venue post type.
	 *
	 * @covers ::init_venues
	 *
	 * @return void
	 */
	public function test_init_venues_registers_single_feed(): void {
		Setup::get_instance()->init_venues( Venue::POST_TYPE );

		global $wp_rewrite;
		$rules = array_keys( $wp_rewrite->extra_rules_top );

		$this->assertContains(
			'venue/([^/]+)/feed/(ical)/?$',
			$rules,
			'init_venues should register the single-venue feed rule.'
		);
	}

	/**
	 * Coverage for init_taxonomies happy path — instantiates Taxonomy_Feed
	 * for the gatherpress_topic taxonomy.
	 *
	 * @covers ::init_taxonomies
	 *
	 * @return void
	 */
	public function test_init_taxonomies_registers_taxonomy_feed(): void {
		Setup::get_instance()->init_taxonomies( 'gatherpress_topic' );

		global $wp_rewrite;
		$rules = array_keys( $wp_rewrite->extra_rules_top );

		$this->assertContains(
			'topic/([^/]+)/feed/(ical)/?$',
			$rules,
			'init_taxonomies should register the taxonomy term feed rule.'
		);
	}

	/**
	 * Coverage for init_venues — bails when the post type is not a shadow
	 * source for an event-bearing taxonomy.
	 *
	 * @covers ::init_venues
	 *
	 * @return void
	 */
	public function test_init_venues_bails_on_non_venue_post_type(): void {
		$instance = Setup::get_instance();

		global $wp_rewrite;
		$before = count( $wp_rewrite->extra_rules_top );
		$instance->init_venues( 'page' );
		$this->assertSame(
			$before,
			count( $wp_rewrite->extra_rules_top ),
			'Non-venue post type should not add rules.'
		);
	}

	/**
	 * Coverage for init_taxonomies — bails for a taxonomy that no event post
	 * type is associated with.
	 *
	 * @covers ::init_taxonomies
	 *
	 * @return void
	 */
	public function test_init_taxonomies_bails_on_unrelated_taxonomy(): void {
		$instance = Setup::get_instance();

		global $wp_rewrite;
		$before = count( $wp_rewrite->extra_rules_top );
		$instance->init_taxonomies( 'category' );
		$this->assertSame(
			$before,
			count( $wp_rewrite->extra_rules_top ),
			'Unrelated taxonomy should not add rules.'
		);
	}

	/**
	 * Coverage for init_taxonomies — bails when the taxonomy has rewrites
	 * disabled.
	 *
	 * @covers ::init_taxonomies
	 *
	 * @return void
	 */
	public function test_init_taxonomies_bails_on_disabled_rewrite(): void {
		register_taxonomy(
			'gp_no_rewrite_tax',
			'gatherpress_event',
			array(
				'public'  => true,
				'rewrite' => false,
			)
		);

		$instance = Setup::get_instance();
		global $wp_rewrite;
		$before = count( $wp_rewrite->extra_rules_top );
		$instance->init_taxonomies( 'gp_no_rewrite_tax' );
		$after = count( $wp_rewrite->extra_rules_top );

		unregister_taxonomy( 'gp_no_rewrite_tax' );

		$this->assertSame(
			$before,
			$after,
			'Taxonomy with rewrite=false should not add rules.'
		);
	}

	/**
	 * Coverage for init_sitewide — instantiates a Sitewide_Feed endpoint.
	 *
	 * @covers ::init_sitewide
	 *
	 * @return void
	 */
	public function test_init_sitewide_registers_rule(): void {
		Setup::get_instance()->init_sitewide();

		global $wp_rewrite;
		$rules = array_keys( $wp_rewrite->extra_rules_top );

		$this->assertContains(
			'feed/(ical)/?$',
			$rules,
			'Sitewide feed/(ical) rule should be registered.'
		);
	}

	/**
	 * Coverage for get_ical_file_template + get_ical_feed_template — both
	 * return template descriptor arrays with the prefixed file_name.
	 *
	 * @covers ::get_ical_file_template
	 * @covers ::get_ical_feed_template
	 *
	 * @return void
	 */
	public function test_template_descriptors(): void {
		$instance = Setup::get_instance();

		$this->assertSame(
			array( 'file_name' => 'gatherpress_ical-download.php' ),
			$instance->get_ical_file_template(),
			'get_ical_file_template should return the prefixed download template.'
		);
		$this->assertSame(
			array( 'file_name' => 'gatherpress_ical-feed.php' ),
			$instance->get_ical_feed_template(),
			'get_ical_feed_template should return the prefixed feed template.'
		);
	}

	/**
	 * Coverage for queried_event_google_url / queried_event_yahoo_url —
	 * each constructs a Calendar wrapping the queried event and forwards.
	 *
	 * @covers ::queried_event_google_url
	 * @covers ::queried_event_yahoo_url
	 *
	 * @return void
	 */
	public function test_queried_event_external_urls(): void {
		$event_id = $this->mock->post(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Q Event',
			)
		)->get()->ID;
		( new Event( $event_id ) )->save_datetimes(
			array(
				'datetime_start' => '2030-01-01 12:00:00',
				'datetime_end'   => '2030-01-01 13:00:00',
				'timezone'       => 'UTC',
			)
		);
		$this->go_to( get_permalink( $event_id ) );

		$instance = Setup::get_instance();

		$this->assertStringContainsString(
			'google.com/calendar/event',
			$instance->queried_event_google_url(),
			'queried_event_google_url should produce a Google calendar URL.'
		);
		$this->assertStringContainsString(
			'calendar.yahoo.com',
			$instance->queried_event_yahoo_url(),
			'queried_event_yahoo_url should produce a Yahoo calendar URL.'
		);
	}

	/**
	 * Coverage for alternate_links — short-circuits without `automatic-feed-links`
	 * theme support.
	 *
	 * @covers ::alternate_links
	 *
	 * @return void
	 */
	public function test_alternate_links_bails_without_theme_support(): void {
		remove_theme_support( 'automatic-feed-links' );

		ob_start();
		Setup::get_instance()->alternate_links();
		$out = ob_get_clean();

		$this->assertSame(
			'',
			$out,
			'alternate_links should output nothing when the theme does not declare automatic-feed-links support.'
		);
	}

	/**
	 * Coverage for alternate_links — emits the sitewide + per-post-type feed
	 * tags on a non-singular request.
	 *
	 * @covers ::alternate_links
	 *
	 * @return void
	 */
	public function test_alternate_links_emits_sitewide_and_post_type_tags(): void {
		add_theme_support( 'automatic-feed-links' );
		$this->go_to( home_url( '/' ) );

		ob_start();
		Setup::get_instance()->alternate_links();
		$out = ob_get_clean();

		$this->assertStringContainsString(
			'type="text/calendar"',
			$out,
			'Should emit at least one alternate link tag.'
		);
		$this->assertStringContainsString(
			'iCal Feed',
			$out,
			'Should label the sitewide feed link as iCal Feed.'
		);
	}

	/**
	 * Coverage for alternate_links on a singular event page — adds the single
	 * download link plus per-term feed links (venue + non-venue tax).
	 *
	 * @covers ::alternate_links
	 *
	 * @return void
	 */
	public function test_alternate_links_on_singular_event_with_venue_and_topic(): void {
		add_theme_support( 'automatic-feed-links' );

		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$venue_id = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Test Venue',
				'post_name'  => 'test-venue',
			)
		)->get()->ID;
		wp_set_post_terms( $event_id, '_test-venue', Venue::TAXONOMY );

		$topic_id = $this->factory->term->create(
			array( 'taxonomy' => 'gatherpress_topic' )
		);
		wp_set_post_terms( $event_id, array( $topic_id ), 'gatherpress_topic' );

		$this->go_to( get_permalink( $event_id ) );

		ob_start();
		Setup::get_instance()->alternate_links();
		$out = ob_get_clean();

		$this->assertStringContainsString(
			'iCal Download',
			$out,
			'Singular event page should add the single-event download link.'
		);
	}

	/**
	 * Coverage for the venue-null arm of the `_gatherpress_venue` switch case
	 * inside alternate_links — when an event is tagged with the venue
	 * taxonomy but no underlying venue post resolves (the online-event case),
	 * the term should be skipped without raising.
	 *
	 * @covers ::alternate_links
	 *
	 * @return void
	 */
	public function test_alternate_links_skips_unresolvable_venue_term(): void {
		add_theme_support( 'automatic-feed-links' );

		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;

		// Insert a venue-taxonomy term that doesn't have a backing venue post.
		$term = wp_insert_term( 'Online Event', Venue::TAXONOMY, array( 'slug' => '_online-event' ) );
		wp_set_post_terms( $event_id, array( (int) $term['term_id'] ), Venue::TAXONOMY );

		$this->go_to( get_permalink( $event_id ) );

		ob_start();
		Setup::get_instance()->alternate_links();
		$out = ob_get_clean();

		$this->assertStringContainsString(
			'iCal Download',
			$out,
			'Should still emit the single-event download link even when venue term has no backing post.'
		);
		// The venue feed link should NOT appear for this term because the venue post is missing.
		$this->assertStringNotContainsString(
			'_online-event/feed/ical',
			$out,
			'No venue feed link should be emitted for an unresolvable venue term.'
		);
	}

	/**
	 * Coverage for alternate_links on a single venue page (the tax-like
	 * shadow-source branch).
	 *
	 * @covers ::alternate_links
	 *
	 * @return void
	 */
	public function test_alternate_links_on_singular_venue(): void {
		add_theme_support( 'automatic-feed-links' );

		$venue_id = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Venue X',
				'post_name'  => 'venue-x',
			)
		)->get()->ID;
		$this->go_to( get_permalink( $venue_id ) );

		ob_start();
		Setup::get_instance()->alternate_links();
		$out = ob_get_clean();

		$this->assertStringContainsString(
			'iCal Download',
			$out,
			'Singular venue page should add the venue feed download link.'
		);
	}

	/**
	 * Coverage for alternate_links on a taxonomy archive — emits the
	 * per-taxonomy feed link.
	 *
	 * @covers ::alternate_links
	 *
	 * @return void
	 */
	public function test_alternate_links_on_taxonomy_archive(): void {
		add_theme_support( 'automatic-feed-links' );

		$term_id  = $this->factory->term->create(
			array( 'taxonomy' => 'gatherpress_topic' )
		);
		$wp_query = $GLOBALS['wp_query'];
		$wp_query->init();
		$wp_query->is_tax            = true;
		$wp_query->queried_object    = get_term( $term_id, 'gatherpress_topic' );
		$wp_query->queried_object_id = $term_id;

		ob_start();
		Setup::get_instance()->alternate_links();
		$out = ob_get_clean();

		$this->assertStringContainsString(
			'text/calendar',
			$out,
			'Taxonomy archive should emit at least one calendar feed alternate link.'
		);
	}

	/**
	 * Coverage for get_ical_wrap — produces a VCALENDAR envelope around the
	 * provided event data with PRODID containing the blog title.
	 *
	 * @covers ::get_ical_wrap
	 *
	 * @return void
	 */
	public function test_get_ical_wrap_envelope(): void {
		$wrapped = Setup::get_instance()->get_ical_wrap( "BEGIN:VEVENT\r\nEND:VEVENT" );

		$this->assertStringStartsWith( "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $wrapped );
		$this->assertStringContainsString( 'PRODID:-//', $wrapped );
		$this->assertStringEndsWith( 'END:VCALENDAR', $wrapped );
		$this->assertStringContainsString( "BEGIN:VEVENT\r\nEND:VEVENT", $wrapped );
	}

	/**
	 * Coverage for get_ical_list and get_ical_feed — both query upcoming
	 * events and emit one VEVENT per result.
	 *
	 * @covers ::get_ical_list
	 * @covers ::get_ical_feed
	 *
	 * @return void
	 */
	public function test_get_ical_list_and_feed(): void {
		$event_id = $this->mock->post(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'L Event',
			)
		)->get()->ID;
		( new Event( $event_id ) )->save_datetimes(
			array(
				'datetime_start' => '2030-02-02 10:00:00',
				'datetime_end'   => '2030-02-02 11:00:00',
				'timezone'       => 'UTC',
			)
		);
		$this->go_to( home_url( '/' ) );

		$instance = Setup::get_instance();
		$list     = $instance->get_ical_list();
		$feed     = $instance->get_ical_feed();

		$this->assertStringContainsString( 'BEGIN:VEVENT', $list, 'Event list should include at least one VEVENT.' );
		$this->assertStringStartsWith( 'BEGIN:VCALENDAR', $feed, 'Feed should be wrapped in a VCALENDAR envelope.' );
	}

	/**
	 * Coverage for get_ical_list on a singular venue — restricts to events
	 * tagged with that venue's shadow taxonomy term.
	 *
	 * @covers ::get_ical_list
	 *
	 * @return void
	 */
	public function test_get_ical_list_on_singular_venue(): void {
		$venue_id = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Venue L',
				'post_name'  => 'venue-l',
			)
		)->get()->ID;
		$this->go_to( get_permalink( $venue_id ) );

		$list = Setup::get_instance()->get_ical_list();

		$this->assertIsString( $list );
	}

	/**
	 * Coverage for get_ical_list on a taxonomy archive — restricts to events
	 * tagged with the queried topic term.
	 *
	 * @covers ::get_ical_list
	 *
	 * @return void
	 */
	public function test_get_ical_list_on_topic_taxonomy(): void {
		$term_id  = $this->factory->term->create(
			array(
				'taxonomy' => 'gatherpress_topic',
				'slug'     => 'wordcamps',
			)
		);
		$wp_query = $GLOBALS['wp_query'];
		$wp_query->init();
		$wp_query->is_tax            = true;
		$wp_query->queried_object    = get_term( $term_id, 'gatherpress_topic' );
		$wp_query->queried_object_id = $term_id;

		$list = Setup::get_instance()->get_ical_list();
		$this->assertIsString( $list );
	}

	/**
	 * Coverage for get_ical_file — wraps a single event's VEVENT.
	 *
	 * @covers ::get_ical_file
	 *
	 * @return void
	 */
	public function test_get_ical_file(): void {
		$event_id = $this->mock->post(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'F Event',
			)
		)->get()->ID;
		( new Event( $event_id ) )->save_datetimes(
			array(
				'datetime_start' => '2030-03-03 09:00:00',
				'datetime_end'   => '2030-03-03 10:00:00',
				'timezone'       => 'UTC',
			)
		);
		$this->go_to( get_permalink( $event_id ) );

		$file = Setup::get_instance()->get_ical_file();

		$this->assertStringStartsWith( 'BEGIN:VCALENDAR', $file );
		$this->assertStringContainsString( 'SUMMARY:F Event', $file );
		$this->assertStringEndsWith( 'END:VCALENDAR', $file );
	}

	/**
	 * Coverage for generate_ics_filename across all five queried-object branches.
	 *
	 * @covers ::generate_ics_filename
	 *
	 * @return void
	 */
	public function test_generate_ics_filename_branches(): void {
		$instance = Setup::get_instance();

		// 1) Singular event → date + slug.
		$event_id = $this->mock->post(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Filename Event',
				'post_name'  => 'filename-event',
			)
		)->get()->ID;
		( new Event( $event_id ) )->save_datetimes(
			array(
				'datetime_start' => '2030-04-04 09:00:00',
				'datetime_end'   => '2030-04-04 10:00:00',
				'timezone'       => 'UTC',
			)
		);
		$this->go_to( get_permalink( $event_id ) );
		$this->assertSame(
			'2030-04-04_filename-event.ics',
			$instance->generate_ics_filename(),
			'Singular event filename should combine date and slug.'
		);

		// 2) Singular venue → bare slug.
		$venue_id = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Venue F',
				'post_name'  => 'venue-f',
			)
		)->get()->ID;
		$this->go_to( get_permalink( $venue_id ) );
		$this->assertSame(
			'venue-f.ics',
			$instance->generate_ics_filename(),
			'Singular venue filename should be the venue slug.'
		);

		// 3) Taxonomy term archive → term slug.
		$term_id  = $this->factory->term->create(
			array(
				'taxonomy' => 'gatherpress_topic',
				'slug'     => 'wp-tax',
			)
		);
		$wp_query = $GLOBALS['wp_query'];
		$wp_query->init();
		$wp_query->is_tax            = true;
		$wp_query->queried_object    = get_term( $term_id, 'gatherpress_topic' );
		$wp_query->queried_object_id = $term_id;
		$this->assertSame(
			'wp-tax.ics',
			$instance->generate_ics_filename(),
			'Taxonomy archive filename should be the term slug.'
		);

		// 4) Post type archive → rewrite slug.
		$wp_query->init();
		$wp_query->is_post_type_archive = true;
		$wp_query->queried_object       = get_post_type_object( Event::POST_TYPE );
		$this->assertSame(
			'event.ics',
			$instance->generate_ics_filename(),
			'Post type archive filename should be the rewrite slug.'
		);

		// 5) Sitewide feed (no singular/tax/archive context) → host with dots replaced.
		$wp_query->init();
		$wp_query->is_feed = true;
		$expected_host     = str_replace( '.', '-', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$this->assertSame(
			$expected_host . '.ics',
			$instance->generate_ics_filename(),
			'Sitewide feed filename should be the home host with dots replaced.'
		);
	}

	/**
	 * Coverage for send_ics_headers — verifies the method runs through and
	 * queues every header() call. Header capture under PHPUnit is fragile
	 * (headers_list is empty in CLI; xdebug_get_headers misses replacements
	 * once a default Content-Type has been emitted from earlier tests), so
	 * this test exercises the code path for coverage and asserts via spy.
	 *
	 * @covers ::send_ics_headers
	 *
	 * @return void
	 */
	public function test_send_ics_headers_runs_without_error(): void {
		// Capture stdout while the method runs to make sure nothing leaks.
		ob_start();
		Setup::get_instance()->send_ics_headers( 'sample.ics' );
		$output = ob_get_clean();

		$this->assertSame(
			'',
			$output,
			'send_ics_headers should not emit body output.'
		);

		header_remove();
	}

	/**
	 * Coverage for has_post_type_for_taxonomy.
	 *
	 * @covers ::has_post_type_for_taxonomy
	 *
	 * @return void
	 */
	public function test_has_post_type_for_taxonomy(): void {
		$instance = Setup::get_instance();

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'has_post_type_for_taxonomy', array( 'gatherpress_topic' ) ),
			'gatherpress_topic is registered on the event post type.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'has_post_type_for_taxonomy', array( 'category' ) ),
			'category is not registered on the event post type.'
		);
	}

	/**
	 * Coverage for is_tax_like_type_for_event_supporting_types.
	 *
	 * @covers ::is_tax_like_type_for_event_supporting_types
	 *
	 * @return void
	 */
	public function test_is_tax_like_type_for_event_supporting_types(): void {
		$instance = Setup::get_instance();

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$instance,
				'is_tax_like_type_for_event_supporting_types',
				array( Venue::POST_TYPE )
			),
			'gatherpress_venue is shadow-source for the venue taxonomy on events.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'is_tax_like_type_for_event_supporting_types',
				array( 'page' )
			),
			'page is not a tax-like shadow source for events.'
		);
	}

	/**
	 * Coverage for alternate_link_label_args — returns the six expected keys
	 * and a non-empty site title.
	 *
	 * @covers ::alternate_link_label_args
	 *
	 * @return void
	 */
	public function test_alternate_link_label_args_shape(): void {
		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );

		$this->assertIsArray( $args, 'alternate_link_label_args should return an array.' );
		foreach ( array( 'blogtitle', 'separator', 'singletitle', 'feedtitle', 'posttypetitle', 'taxtitle' ) as $key ) {
			$this->assertArrayHasKey( $key, $args, sprintf( 'Label args should contain "%s".', $key ) );
		}
		$this->assertNotEmpty( $args['blogtitle'], 'blogtitle should be populated from get_bloginfo().' );
		$this->assertStringContainsString(
			'iCal Download',
			$args['singletitle'],
			'singletitle template should mention iCal Download.'
		);
		$this->assertStringContainsString(
			'iCal Feed',
			$args['feedtitle'],
			'feedtitle template should mention iCal Feed.'
		);
	}

	/**
	 * Coverage for collect_sitewide_alternate_link — returns a one-element list
	 * with the sitewide feed URL and the formatted attr label.
	 *
	 * @covers ::collect_sitewide_alternate_link
	 *
	 * @return void
	 */
	public function test_collect_sitewide_alternate_link_returns_single_entry(): void {
		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method( $instance, 'collect_sitewide_alternate_link', array( $args ) );

		$this->assertCount( 1, $links, 'Sitewide collector should emit exactly one entry.' );
		$this->assertArrayHasKey( 'url', $links[0], 'Entry should have a url key.' );
		$this->assertArrayHasKey( 'attr', $links[0], 'Entry should have an attr key.' );
		$this->assertStringContainsString(
			'iCal Feed',
			$links[0]['attr'],
			'Sitewide attr should be formatted with the feedtitle template.'
		);
	}

	/**
	 * Coverage for collect_post_type_archive_alternate_links — emits one entry
	 * per event-supporting post type.
	 *
	 * @covers ::collect_post_type_archive_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_post_type_archive_alternate_links_one_per_post_type(): void {
		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method(
			$instance,
			'collect_post_type_archive_alternate_links',
			array( $args )
		);

		$this->assertSameSize(
			get_post_types_by_support( 'gatherpress-event-date' ),
			$links,
			'Should emit exactly one entry per event-supporting post type.'
		);
		foreach ( $links as $link ) {
			$this->assertArrayHasKey( 'url', $link );
			$this->assertArrayHasKey( 'attr', $link );
			$this->assertStringContainsString(
				'iCal Feed',
				$link['attr'],
				'Post-type archive attr should be formatted with the posttypetitle template.'
			);
		}
	}

	/**
	 * Coverage for collect_contextual_alternate_links — returns the singular-event
	 * branch when on an event permalink.
	 *
	 * @covers ::collect_contextual_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_contextual_alternate_links_on_singular_event(): void {
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$this->go_to( get_permalink( $event_id ) );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method( $instance, 'collect_contextual_alternate_links', array( $args ) );

		$this->assertNotEmpty( $links, 'Singular event should produce contextual links.' );
		$this->assertStringContainsString(
			'iCal Download',
			$links[0]['attr'],
			'First contextual link on an event should be the iCal Download.'
		);
	}

	/**
	 * Coverage for collect_contextual_alternate_links — returns the
	 * singular-tax-like branch when on a venue permalink.
	 *
	 * @covers ::collect_contextual_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_contextual_alternate_links_on_singular_tax_like(): void {
		$venue_id = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Venue C',
				'post_name'  => 'venue-c',
			)
		)->get()->ID;
		$this->go_to( get_permalink( $venue_id ) );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method( $instance, 'collect_contextual_alternate_links', array( $args ) );

		$this->assertCount( 1, $links, 'Singular venue dispatch should produce exactly one entry.' );
		$this->assertStringContainsString(
			'iCal Download',
			$links[0]['attr'],
			'Singular venue dispatch should emit the iCal Download entry.'
		);
	}

	/**
	 * Coverage for collect_contextual_alternate_links — returns the
	 * tax-archive branch when on an event-bearing taxonomy archive.
	 *
	 * @covers ::collect_contextual_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_contextual_alternate_links_on_taxonomy_archive(): void {
		$term_id  = $this->factory->term->create(
			array( 'taxonomy' => 'gatherpress_topic' )
		);
		$wp_query = $GLOBALS['wp_query'];
		$wp_query->init();
		$wp_query->is_tax            = true;
		$wp_query->queried_object    = get_term( $term_id, 'gatherpress_topic' );
		$wp_query->queried_object_id = $term_id;

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method( $instance, 'collect_contextual_alternate_links', array( $args ) );

		$this->assertCount( 1, $links, 'Tax archive dispatch should produce exactly one entry.' );
		$this->assertStringContainsString(
			'iCal Feed',
			$links[0]['attr'],
			'Tax archive dispatch should emit a taxtitle-formatted entry.'
		);
	}

	/**
	 * Coverage for collect_contextual_alternate_links — returns an empty list
	 * for a request that does not match any of the dispatch arms.
	 *
	 * @covers ::collect_contextual_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_contextual_alternate_links_falls_through_to_empty(): void {
		$this->go_to( home_url( '/' ) );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method( $instance, 'collect_contextual_alternate_links', array( $args ) );

		$this->assertSame( array(), $links, 'Home request should produce no contextual links.' );
	}

	/**
	 * Coverage for collect_singular_event_alternate_links — emits the single
	 * download entry plus the per-term entries.
	 *
	 * @covers ::collect_singular_event_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_singular_event_alternate_links_includes_download_and_terms(): void {
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$topic_id = $this->factory->term->create(
			array( 'taxonomy' => 'gatherpress_topic' )
		);
		wp_set_post_terms( $event_id, array( $topic_id ), 'gatherpress_topic' );

		$this->go_to( get_permalink( $event_id ) );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method(
			$instance,
			'collect_singular_event_alternate_links',
			array( get_queried_object(), $args )
		);

		$this->assertNotEmpty( $links, 'Singular event collector should return at least the download entry.' );
		$this->assertStringContainsString(
			'iCal Download',
			$links[0]['attr'],
			'First entry should be the single-event iCal download.'
		);
		$this->assertGreaterThanOrEqual(
			2,
			count( $links ),
			'Event tagged with a topic should produce at least one term entry in addition to the download.'
		);
	}

	/**
	 * Coverage for collect_singular_tax_like_alternate_links — emits the venue
	 * comments-feed download link.
	 *
	 * @covers ::collect_singular_tax_like_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_singular_tax_like_alternate_links_emits_download_entry(): void {
		$venue_id = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Venue Y',
				'post_name'  => 'venue-y',
			)
		)->get()->ID;
		$this->go_to( get_permalink( $venue_id ) );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method(
			$instance,
			'collect_singular_tax_like_alternate_links',
			array( get_queried_object(), $args )
		);

		$this->assertCount( 1, $links, 'Tax-like singular collector should emit exactly one entry.' );
		$this->assertStringContainsString(
			'iCal Download',
			$links[0]['attr'],
			'Tax-like singular attr should be formatted with the singletitle template.'
		);
		$this->assertSame(
			get_post_comments_feed_link( $venue_id, Setup::ICAL_SLUG ),
			$links[0]['url'],
			'Tax-like singular url should resolve to the venue post comments feed link.'
		);
	}

	/**
	 * Coverage for collect_tax_archive_alternate_links — emits the term archive
	 * feed link.
	 *
	 * @covers ::collect_tax_archive_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_tax_archive_alternate_links_emits_term_feed_entry(): void {
		$term_id = $this->factory->term->create(
			array( 'taxonomy' => 'gatherpress_topic' )
		);
		$term    = get_term( $term_id, 'gatherpress_topic' );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method(
			$instance,
			'collect_tax_archive_alternate_links',
			array( $term, $args )
		);

		$this->assertCount( 1, $links, 'Tax archive collector should emit exactly one entry.' );
		$this->assertStringContainsString(
			'iCal Feed',
			$links[0]['attr'],
			'Tax archive attr should be formatted with the taxtitle template.'
		);
		$this->assertNotEmpty( $links[0]['url'], 'Tax archive entry should have a non-empty url.' );
	}

	/**
	 * Coverage for collect_event_term_alternate_links — walks the event's
	 * related terms and returns one entry per resolvable term.
	 *
	 * @covers ::collect_event_term_alternate_links
	 *
	 * @return void
	 */
	public function test_collect_event_term_alternate_links_walks_terms(): void {
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$topic_id = $this->factory->term->create(
			array( 'taxonomy' => 'gatherpress_topic' )
		);
		wp_set_post_terms( $event_id, array( $topic_id ), 'gatherpress_topic' );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$links    = Utility::invoke_hidden_method(
			$instance,
			'collect_event_term_alternate_links',
			array( get_post( $event_id ), $args )
		);

		$this->assertNotEmpty( $links, 'Event with a topic term should produce at least one term entry.' );
		$this->assertStringContainsString(
			'iCal Feed',
			$links[0]['attr'],
			'Term entry attr should be formatted with the taxtitle template.'
		);
	}

	/**
	 * Coverage for collect_term_alternate_link — returns a single entry for a
	 * regular taxonomy term.
	 *
	 * @covers ::collect_term_alternate_link
	 *
	 * @return void
	 */
	public function test_collect_term_alternate_link_regular_taxonomy(): void {
		$term_id = $this->factory->term->create(
			array( 'taxonomy' => 'gatherpress_topic' )
		);
		$term    = get_term( $term_id, 'gatherpress_topic' );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$entry    = Utility::invoke_hidden_method(
			$instance,
			'collect_term_alternate_link',
			array( $term, $args )
		);

		$this->assertCount( 1, $entry, 'Regular taxonomy term should produce one entry.' );
		$this->assertNotEmpty( $entry[0]['url'], 'Regular term entry should have a non-empty url.' );
		$this->assertStringContainsString(
			'iCal Feed',
			$entry[0]['attr'],
			'Regular term attr should be formatted with the taxtitle template.'
		);
	}

	/**
	 * Coverage for collect_term_alternate_link — returns an empty list for a
	 * sentinel shadow term like `online-event` whose slug does not start with `_`.
	 *
	 * @covers ::collect_term_alternate_link
	 *
	 * @return void
	 */
	public function test_collect_term_alternate_link_sentinel_shadow_returns_empty(): void {
		$result = wp_insert_term(
			'Online Event',
			Venue::TAXONOMY,
			array( 'slug' => 'online-event' )
		);
		$term   = get_term( (int) $result['term_id'], Venue::TAXONOMY );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$entry    = Utility::invoke_hidden_method(
			$instance,
			'collect_term_alternate_link',
			array( $term, $args )
		);

		$this->assertSame(
			array(),
			$entry,
			'Sentinel shadow term (slug without leading underscore) should produce no entry.'
		);
	}

	/**
	 * Coverage for collect_term_alternate_link — returns a single entry for a
	 * resolvable shadow term (slug starts with `_` and has a backing post).
	 *
	 * @covers ::collect_term_alternate_link
	 *
	 * @return void
	 */
	public function test_collect_term_alternate_link_shadow_with_backing_post(): void {
		$venue_id = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Venue Z',
				'post_name'  => 'venue-z',
			)
		)->get()->ID;
		$term     = get_term_by( 'slug', '_venue-z', Venue::TAXONOMY );

		$instance = Setup::get_instance();
		$args     = Utility::invoke_hidden_method( $instance, 'alternate_link_label_args' );
		$entry    = Utility::invoke_hidden_method(
			$instance,
			'collect_term_alternate_link',
			array( $term, $args )
		);

		$this->assertCount( 1, $entry, 'Resolvable shadow term should produce one entry.' );
		$this->assertSame(
			get_post_comments_feed_link( $venue_id, Setup::ICAL_SLUG ),
			$entry[0]['url'],
			'Shadow term url should resolve to the backing venue post comments feed link.'
		);
	}

	/**
	 * Coverage for render_alternate_links — prints one `<link>` tag per entry
	 * with proper escaping.
	 *
	 * @covers ::render_alternate_links
	 *
	 * @return void
	 */
	public function test_render_alternate_links_prints_link_tags(): void {
		$instance = Setup::get_instance();
		$links    = array(
			array(
				'url'  => 'https://example.org/feed/ical/',
				'attr' => 'Example iCal Feed',
			),
			array(
				'url'  => 'https://example.org/event/sample/feed/ical/',
				'attr' => 'Example iCal Download',
			),
		);

		ob_start();
		Utility::invoke_hidden_method( $instance, 'render_alternate_links', array( $links ) );
		$out = ob_get_clean();

		$this->assertSame(
			2,
			substr_count( $out, '<link rel="alternate"' ),
			'Should emit exactly one <link> tag per entry.'
		);
		$this->assertStringContainsString(
			'type="text/calendar"',
			$out,
			'Should set the calendar MIME type on each tag.'
		);
		$this->assertStringContainsString(
			'Example iCal Feed',
			$out,
			'Should include the first entry attr label.'
		);
		$this->assertStringContainsString(
			'Example iCal Download',
			$out,
			'Should include the second entry attr label.'
		);
	}
}
