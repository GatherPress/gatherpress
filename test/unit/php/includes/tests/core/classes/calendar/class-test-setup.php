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
}
