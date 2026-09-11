<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Rewrite.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Rewrite;
use GatherPress\Core\Event\Recurrence\Series;
use GatherPress\Core\Event\Recurrence\Splitter;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Setup;
use GatherPress\Core\Settings;
use GatherPress\Core\Topic;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use ReflectionClass;
use WP;

/**
 * Class Test_Rewrite.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Rewrite
 */
class Test_Rewrite extends Base {

	/**
	 * Set up test environment: create the occurrence table, and put the
	 * event post type behind a pretty permalink structure with the
	 * occurrence rewrite rule registered and flushed, so real requests
	 * routed through `$this->go_to()` actually exercise the rewrite rule
	 * rather than the plain `?p=` query-string form.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		// The event post type's own permastruct was built at bootstrap, while
		// permalinks were still plain. WP only builds a post type's pretty
		// permalink structure when `permalink_structure` is non-empty at
		// registration time, so it must be re-registered now that pretty
		// permalinks are active, or get_permalink() keeps returning the
		// `?p=`-style plain URL for the rest of this test.
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		unregister_taxonomy( Topic::TAXONOMY );
		Topic::get_instance()->register_taxonomy();

		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();

		Context::get_instance()->clear();
		Context::flush_resolved();
		Series::get_instance()->flush_memo();
	}

	/**
	 * Restore a plain permalink structure so later test classes in the same
	 * process are not affected by this class's rewrite rules.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '' );
		$wp_rewrite->flush_rules();

		Context::get_instance()->clear();
		Context::flush_resolved();
		Series::get_instance()->flush_memo();

		parent::tearDown();
	}

	/**
	 * Create and project a recurring event whose occurrences are anchored
	 * relative to "now" rather than to a fixed calendar date, so the test
	 * suite does not become a date bomb as real time passes.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $day_offset Days from "now" for the first occurrence.
	 * @param int    $interval   Days between occurrences.
	 * @param int    $count      Number of occurrences.
	 * @param string $timezone   Named tz-database identifier for the series.
	 *
	 * @return array{0: int, 1: DateTimeImmutable} The post ID and its anchor start.
	 */
	protected function create_relative_daily_series(
		int $day_offset,
		int $interval,
		int $count,
		string $timezone = 'America/New_York'
	): array {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$start   = $this->project_relative_daily_series( $post_id, $day_offset, $interval, $count, $timezone );

		return array( $post_id, $start );
	}

	/**
	 * Project a now-relative daily series onto an existing post.
	 *
	 * Split out of `create_relative_daily_series()` so the routing tests for
	 * companion post types can project a series onto a post of their own
	 * post type rather than of `Event::POST_TYPE`.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id    Post to project the series onto.
	 * @param int    $day_offset Days from "now" for the first occurrence.
	 * @param int    $interval   Days between occurrences.
	 * @param int    $count      Number of occurrences.
	 * @param string $timezone   Named tz-database identifier for the series.
	 *
	 * @return DateTimeImmutable The series' anchor start.
	 */
	protected function project_relative_daily_series(
		int $post_id,
		int $day_offset,
		int $interval,
		int $count,
		string $timezone = 'America/New_York'
	): DateTimeImmutable {
		$tz    = new DateTimeZone( $timezone );
		$start = ( new DateTimeImmutable( 'now', $tz ) )->modify( sprintf( '%+d days', $day_offset ) );

		return $this->project_daily_series_at( $post_id, $start, $interval, $count );
	}

	/**
	 * Project a daily series anchored at an exact instant onto an existing post.
	 *
	 * Split out of `project_relative_daily_series()` for fixtures that need
	 * sub-day precision, such as an occurrence that is in progress at the
	 * moment the test runs.
	 *
	 * @since 0.36.0
	 *
	 * @param int               $post_id  Post to project the series onto.
	 * @param DateTimeImmutable $start    Anchor start, in the series timezone.
	 * @param int               $interval Days between occurrences.
	 * @param int               $count    Number of occurrences.
	 *
	 * @return DateTimeImmutable The series' anchor start.
	 */
	protected function project_daily_series_at(
		int $post_id,
		DateTimeImmutable $start,
		int $interval,
		int $count
	): DateTimeImmutable {
		$timezone = $start->getTimezone()->getName();
		$end      = $start->add( new DateInterval( 'PT2H' ) );

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $end->format( 'Y-m-d H:i:s' ),
					'timezone'      => $timezone,
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );

		add_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => $interval,
					'end_type'  => 'count',
					'count'     => $count,
				)
			)
		);
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return $start;
	}

	/**
	 * Coverage for `__construct` and `setup_hooks`.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Rewrite::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_loaded',
				'priority' => 10,
				'callback' => array( $instance, 'add_rewrite_rules' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'query_vars',
				'priority' => 10,
				'callback' => array( $instance, 'add_query_vars' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'parse_request',
				'priority' => 10,
				'callback' => array( $instance, 'parse_request' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );

		// A public constructor on a singleton is not a style point: every
		// `new Rewrite()` adds another `wp_loaded`, `query_vars` and
		// `parse_request` callback, so the rewrite rules are re-registered and
		// the occurrence table re-probed once per instance, on every request.
		$this->assertTrue(
			( new ReflectionClass( Rewrite::class ) )->getConstructor()->isProtected(),
			'Failed to assert the singleton constructor is protected, leaving get_instance() the only'
				. ' construction path.'
		);
	}

	/**
	 * Coverage for `add_query_vars`.
	 *
	 * @covers ::add_query_vars
	 *
	 * @return void
	 */
	public function test_add_query_vars(): void {
		$this->assertSame(
			array( 'apples', Context::QUERY_VAR ),
			Rewrite::get_instance()->add_query_vars( array( 'apples' ) ),
			'Failed to assert that the occurrence query var is appended.'
		);
	}

	/**
	 * Coverage for `get_occurrence_url`: it composes onto the *configured*
	 * rewrite slug rather than a hardcoded `/event/`. This only exercises
	 * URL composition (`get_occurrence_url()` -> `get_permalink()`). It
	 * does NOT exercise routing, since `add_rewrite_rule_for_post_type()`'s
	 * own runtime slug read is a different code path entirely. See
	 * `test_occurrence_url_routes_under_a_non_default_rewrite_slug()` below
	 * for the routing-side guarantee.
	 *
	 * @covers ::get_occurrence_url
	 *
	 * @return void
	 */
	public function test_occurrence_url_composes_onto_configured_rewrite_slug(): void {
		update_option( 'gatherpress_settings', array( 'events_url' => 'meetups' ) );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		Rewrite::get_instance()->add_rewrite_rules();

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_name'   => 'custom-slug-meetup',
				'post_status' => 'publish',
			)
		);

		$this->assertSame(
			home_url( '/meetups/custom-slug-meetup/20260901T180000/' ),
			Rewrite::get_occurrence_url( $post_id, '20260901T180000' ),
			'Occurrence URL should compose onto the configured events_url slug, not a hardcoded /event/.'
		);

		// Restore the default slug for every later test in this class/process.
		delete_option( 'gatherpress_settings' );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
	}

	/**
	 * Coverage for the actual correctness claim: an occurrence URL
	 * under a *non-default* `events_url` slug actually ROUTES. It drives a
	 * real request through `add_rewrite_rule_for_post_type()`'s runtime
	 * slug read at `class-rewrite.php:138`, the line that matters, rather
	 * than through `get_occurrence_url()`'s independent composition path.
	 * Substituting a hardcoded `'event'` literal for `$slug` at that line
	 * turns this test (and only this test) red.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_occurrence_url_routes_under_a_non_default_rewrite_slug(): void {
		global $wp_rewrite;

		update_option( 'gatherpress_settings', array( 'events_url' => 'meetups' ) );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();

		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );
		$recurrence_id                  = Occurrences::recurrence_id( $anchor_start );
		$url                            = Rewrite::get_occurrence_url( $post_id, $recurrence_id );

		$this->assertStringContainsString(
			'/meetups/',
			$url,
			'Fixture setup: the occurrence URL should be composed under the meetups slug.'
		);

		$this->go_to( $url );

		$this->assertFalse( is_404(), 'An occurrence URL under a non-default slug must not 404.' );
		$this->assertTrue(
			is_singular( Event::POST_TYPE ),
			'An occurrence URL under a non-default slug should render the event single template.'
		);
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'The occurrence query var should resolve under a non-default slug.'
		);

		// Restore the default slug for every later test in this class/process.
		delete_option( 'gatherpress_settings' );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Coverage for a *localized* `events_url` slug. `Settings::get('events_url')`
	 * falls back to `Event\Setup::get_localized_post_type_slug()` when the
	 * option is unset, and that is a live, reachable configuration on any
	 * non-English site that has not explicitly overridden the events slug.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_occurrence_url_routes_under_a_localized_rewrite_slug(): void {
		global $wp_rewrite;

		$filter_localized_label = static function ( $labels ) {
			$labels->singular_name = 'Veranstaltung';

			return $labels;
		};
		add_filter( 'post_type_labels_gatherpress_event', $filter_localized_label );

		// Settings::get_defaults_map() caches the resolved default for the
		// remainder of the request the first time anything reads a setting,
		// which is almost certainly already true by this point in the suite.
		// The localized-slug default must therefore be forced to recompute, or
		// the filter above has no effect on events_url's default.
		$settings = Settings::get_instance();
		Utility::set_and_get_hidden_property( $settings, 'defaults_cache', null );

		delete_option( 'gatherpress_settings' );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();

		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );
		$recurrence_id                  = Occurrences::recurrence_id( $anchor_start );
		$url                            = Rewrite::get_occurrence_url( $post_id, $recurrence_id );

		$this->assertStringContainsString(
			'/veranstaltung/',
			$url,
			'Fixture setup: the occurrence URL should be composed under the localized slug.'
		);

		$this->go_to( $url );

		$this->assertFalse( is_404(), 'An occurrence URL under a localized slug must not 404.' );
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'The occurrence query var should resolve under a localized slug.'
		);

		remove_filter( 'post_type_labels_gatherpress_event', $filter_localized_label );
		Utility::set_and_get_hidden_property( $settings, 'defaults_cache', null );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Coverage for a hierarchical companion post type declaring the
	 * `gatherpress-event-date` support. WordPress publishes a child post of
	 * such a type at `parent/child`, so its advertised occurrence URL is
	 * `/gp-hier/parent/child/{Ymd\THis}/`, and the occurrence rule must use
	 * the hierarchical capture WordPress's own permastruct uses, or the URL
	 * the plugin itself advertises 404s.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 * @covers ::parse_request
	 * @covers ::resolve_post_id_from_query_vars
	 *
	 * @return void
	 */
	public function test_occurrence_url_routes_for_a_hierarchical_supporting_post_type(): void {
		global $wp_rewrite;

		register_post_type(
			'gp_hier_event',
			array(
				'public'       => true,
				'hierarchical' => true,
				'supports'     => array( 'title', 'editor', 'page-attributes', 'gatherpress-event-date' ),
				'rewrite'      => array(
					'slug'       => 'gp-hier',
					'with_front' => false,
				),
			)
		);
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();

		$parent_id = $this->factory->post->create(
			array(
				'post_type'   => 'gp_hier_event',
				'post_name'   => 'parent-event',
				'post_status' => 'publish',
			)
		);
		$child_id  = $this->factory->post->create(
			array(
				'post_type'   => 'gp_hier_event',
				'post_name'   => 'child-event',
				'post_parent' => $parent_id,
				'post_status' => 'publish',
			)
		);

		$anchor_start  = $this->project_relative_daily_series( $child_id, 5, 7, 3 );
		$recurrence_id = Occurrences::recurrence_id( $anchor_start );
		$url           = Rewrite::get_occurrence_url( $child_id, $recurrence_id );

		$this->assertStringContainsString(
			'/gp-hier/parent-event/child-event/',
			$url,
			'Fixture setup: the occurrence URL should be composed under the child post\'s hierarchical path.'
		);

		$this->go_to( $url );

		$this->assertFalse( is_404(), 'A hierarchical supporting post type\'s occurrence URL must not 404.' );
		$this->assertSame(
			$child_id,
			get_queried_object_id(),
			'A hierarchical occurrence URL should resolve to the child post.'
		);
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'A hierarchical occurrence URL should round-trip to its own recurrence ID.'
		);

		unregister_post_type( 'gp_hier_event' );
	}

	/**
	 * Coverage for a companion post type whose permastruct keeps the rewrite
	 * front. With a permalink structure of `/blog/%postname%/`, WordPress
	 * prepends `/blog/` to every `with_front` permastruct, so the post's
	 * permalink and its advertised occurrence URL both live under
	 * `/blog/gp-front/`. The occurrence rule must include that front, or the
	 * advertised URL 404s. The stock event post type registers with
	 * `with_front` disabled, which is why its own tests never expose this.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 * @covers ::parse_request
	 * @covers ::resolve_post_id_from_query_vars
	 *
	 * @return void
	 */
	public function test_occurrence_url_routes_for_a_post_type_with_front(): void {
		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure( '/blog/%postname%/' );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();

		register_post_type(
			'gp_front_event',
			array(
				'public'   => true,
				'supports' => array( 'title', 'editor', 'gatherpress-event-date' ),
				'rewrite'  => array(
					'slug'       => 'gp-front',
					'with_front' => true,
				),
			)
		);
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'gp_front_event',
				'post_name'   => 'fronted-event',
				'post_status' => 'publish',
			)
		);

		$anchor_start  = $this->project_relative_daily_series( $post_id, 5, 7, 3 );
		$recurrence_id = Occurrences::recurrence_id( $anchor_start );
		$url           = Rewrite::get_occurrence_url( $post_id, $recurrence_id );

		$this->assertStringContainsString(
			'/blog/gp-front/fronted-event/',
			$url,
			'Fixture setup: the occurrence URL should be composed under the permastruct front.'
		);

		$this->go_to( $url );

		$this->assertFalse( is_404(), 'A with_front supporting post type\'s occurrence URL must not 404.' );
		$this->assertSame(
			$post_id,
			get_queried_object_id(),
			'A with_front occurrence URL should resolve to its own post.'
		);
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'A with_front occurrence URL should round-trip to its own recurrence ID.'
		);

		unregister_post_type( 'gp_front_event' );
	}

	/**
	 * Coverage for `get_occurrence_url` when the post has no permalink.
	 *
	 * @covers ::get_occurrence_url
	 *
	 * @return void
	 */
	public function test_get_occurrence_url_returns_empty_string_without_permalink(): void {
		$this->assertSame(
			'',
			Rewrite::get_occurrence_url( 0, '20260901T180000' ),
			'get_occurrence_url() should return an empty string when the post has no permalink.'
		);
	}

	/**
	 * Coverage for `get_occurrence_url` on a site with plain permalinks, where
	 * `get_permalink()` returns a query-string URL such as
	 * `/?gatherpress_event=slug`. The occurrence must ride as its own
	 * `gatherpress_occurrence` query variable there, the same way
	 * `Calendar::get_endpoint_url()` already composes its endpoint URLs, and
	 * the URL must round-trip through a real request. Appending a path
	 * segment instead would push the identifier *into* the event query value,
	 * yielding `?gatherpress_event=slug/{id}/`, which matches no post.
	 *
	 * This class's `setUp()` establishes pretty permalinks, so this test
	 * establishes the plain structure itself, including the post type
	 * re-registration the structure change requires.
	 *
	 * @covers ::get_occurrence_url
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_occurrence_url_round_trips_under_plain_permalinks(): void {
		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure( '' );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();
		$wp_rewrite->flush_rules();

		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );
		$recurrence_id                  = Occurrences::recurrence_id( $anchor_start );
		$url                            = Rewrite::get_occurrence_url( $post_id, $recurrence_id );

		$query_args = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query_args );

		$this->assertSame(
			$recurrence_id,
			$query_args[ Context::QUERY_VAR ] ?? null,
			'A plain-permalink occurrence URL must carry the occurrence as its own query variable.'
		);

		$this->go_to( $url );

		$this->assertFalse( is_404(), 'A plain-permalink occurrence URL must not 404.' );
		$this->assertSame(
			$post_id,
			get_queried_object_id(),
			'A plain-permalink occurrence URL should resolve to the series post.'
		);
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'A plain-permalink occurrence URL should round-trip to its own recurrence ID.'
		);
	}

	/**
	 * Coverage for the occurrence segment being unfilterable, and for the URL
	 * it emits round-tripping through a real request.
	 *
	 * `gatherpress_recurrence_id_format` used to let an integration replace the
	 * segment. It was one-way: `add_rewrite_rule_for_post_type()` registers a
	 * single fixed `RECURRENCE_ID_REGEX` and `parse_request()` matches the raw
	 * segment against the canonical `recurrence_id` column, so every URL the
	 * filter customized 404'd at the address it advertised. The filter is gone;
	 * this pins that it stays gone, and that the URL actually generated routes.
	 *
	 * The round-trip is a real `go_to()` rather than a string comparison,
	 * because a string comparison is exactly what let the broken filter ship.
	 *
	 * @covers ::get_occurrence_url
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_occurrence_url_ignores_a_segment_filter_and_round_trips(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );
		$recurrence_id                  = Occurrences::recurrence_id( $anchor_start );

		add_filter(
			'gatherpress_recurrence_id_format',
			static function () {
				return 'custom-segment';
			}
		);

		$url = Rewrite::get_occurrence_url( $post_id, $recurrence_id );

		remove_all_filters( 'gatherpress_recurrence_id_format' );

		$this->assertStringEndsWith(
			'/' . $recurrence_id . '/',
			$url,
			'Failed to assert the occurrence URL still ends in the canonical recurrence ID.'
		);
		$this->assertStringNotContainsString(
			'custom-segment',
			$url,
			'Failed to assert no filter can rewrite the occurrence segment into something unroutable.'
		);

		$this->go_to( $url );

		$this->assertFalse( is_404(), 'Failed to assert the generated occurrence URL routes.' );
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'Failed to assert the generated occurrence URL round-trips to its own recurrence ID.'
		);
		$this->assertSame(
			$post_id,
			get_queried_object_id(),
			'Failed to assert the generated occurrence URL round-trips to its own series post.'
		);
	}

	/**
	 * Coverage for the occurrence URL resolving 200 and rendering the event
	 * template, driven through a real request rather than an internal call.
	 *
	 * @covers ::parse_request
	 * @covers ::resolve_post_id_from_query_vars
	 * @covers ::add_rewrite_rule_for_post_type
	 * @covers ::add_rewrite_rules
	 *
	 * @return void
	 */
	public function test_occurrence_url_returns_200_and_renders_event_template(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );
		$recurrence_id                  = Occurrences::recurrence_id( $anchor_start );

		$this->go_to( Rewrite::get_occurrence_url( $post_id, $recurrence_id ) );

		$this->assertFalse( is_404(), 'A real occurrence URL must not 404.' );
		$this->assertTrue(
			is_singular( Event::POST_TYPE ),
			'A real occurrence URL should render the event single template.'
		);
		$this->assertSame(
			$post_id,
			get_queried_object_id(),
			'The queried object should be the series post.'
		);
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'The occurrence query var should carry the resolved recurrence ID.'
		);
	}

	/**
	 * Coverage for the upgrade path: `WP_Rewrite::wp_rewrite_rules()` reads
	 * the persisted `rewrite_rules` option verbatim on every request when it
	 * is non-empty. `add_rewrite_rule()` alone only ever mutates
	 * `$wp_rewrite->extra_rules_top` in memory, so on a site that already
	 * has a populated `rewrite_rules` option (every existing GatherPress
	 * site, the moment this code deploys), the occurrence rule would never
	 * reach the persisted option, and therefore never match a real request,
	 * without `maybe_flush_rewrite_rules()` correcting it.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 * @covers ::maybe_flush_rewrite_rules
	 *
	 * @return void
	 */
	public function test_add_rewrite_rules_self_heals_a_stale_persisted_option(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );
		$recurrence_id                  = Occurrences::recurrence_id( $anchor_start );
		$url                            = Rewrite::get_occurrence_url( $post_id, $recurrence_id );

		// Simulate an existing site upgrading: strip the occurrence pattern
		// back out of the persisted option, as if this plugin version had
		// never registered it and the site's rules were never otherwise
		// regenerated since. `extra_rules_top` in memory is untouched, which is
		// exactly what happens between one request finishing and the next
		// one's `wp_loaded` firing.
		$reg_ex = sprintf( '%s/([^/]+)/(%s)/?$', 'event', Rewrite::RECURRENCE_ID_REGEX );
		$stale  = get_option( 'rewrite_rules' );
		$this->assertArrayHasKey(
			$reg_ex,
			$stale,
			'Fixture setup: the occurrence rule should already be persisted before it is stripped back out.'
		);
		unset( $stale[ $reg_ex ] );
		update_option( 'rewrite_rules', $stale );

		// Before: WP_Rewrite::wp_rewrite_rules() reads the stale option
		// verbatim. add_rewrite_rules() has not run again yet, matching a
		// request that lands between deploy and the next full request
		// bootstrap.
		$this->go_to( $url );
		$this->assertTrue(
			is_404(),
			'BEFORE: a stale persisted rewrite_rules option missing the occurrence rule must 404 the occurrence URL.'
		);

		// This is exactly what fires on `wp_loaded` for every real request.
		Rewrite::get_instance()->add_rewrite_rules();

		// After: the mismatch triggered delete_option(), so the next read
		// via wp_rewrite_rules() regenerates the option from the rules
		// still held in extra_rules_top, this one included.
		$this->go_to( $url );
		$this->assertFalse(
			is_404(),
			'AFTER: add_rewrite_rules() must self-heal a stale persisted option so the occurrence URL resolves.'
		);
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'AFTER: the occurrence query var should resolve once the option is healed.'
		);
	}

	/**
	 * Coverage for `maybe_flush_rewrite_rules` when the persisted option
	 * already matches. The comparison must be a no-op, or this would
	 * flush on every single request.
	 *
	 * @covers ::maybe_flush_rewrite_rules
	 *
	 * @return void
	 */
	public function test_maybe_flush_rewrite_rules_is_a_no_op_when_already_correct(): void {
		// setUp() already ran add_rewrite_rules() + flush_rules(), so the
		// option is already correct for the default slug.
		$before = get_option( 'rewrite_rules' );

		Rewrite::get_instance()->add_rewrite_rules();

		$this->assertSame(
			$before,
			get_option( 'rewrite_rules' ),
			'add_rewrite_rules() must not touch an already-correct rewrite_rules option.'
		);
	}

	/**
	 * A well-formed `Ymd\THis` segment that is not an
	 * actual occurrence of that series 404s rather than silently rendering
	 * the series at its anchor date.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_non_occurrence_datetime_returns_404(): void {
		list( $post_id, ) = $this->create_relative_daily_series( 5, 7, 3 );

		// Well-formed Ymd\THis, but never produced by this series' rule.
		$url = Rewrite::get_occurrence_url( $post_id, '19991231T235959' );
		$this->go_to( $url );

		$this->assertTrue(
			is_404(),
			'A well-formed but non-occurrence datetime segment should 404.'
		);

		// parse_request() must have neutralized redirect_canonical() on this
		// 404, or WP would 301 the miss back to the bare series URL instead
		// of letting the 404 stand. go_to() itself never fires
		// template_redirect, so this has to be asserted directly.
		$this->assertNull(
			redirect_canonical( $url, false ),
			'redirect_canonical() must be neutralized so a non-occurrence 404 is not silently redirected.'
		);
	}

	/**
	 * A permalink minted before a forward split still reaches its occurrence
	 * afterwards, by 301 to the sibling post that now owns the row.
	 *
	 * This is the whole point of recycling occurrence records rather than
	 * regenerating them, since permalinks come first in the list of things that
	 * must survive a split. The row still exists under the same
	 * `recurrence_id`; only the post that owns it changed. Resolving through
	 * `find_in_series()` over `Series::resolve_post_ids()` is precisely what
	 * distinguishes that from a stale or hand-typed identifier, which must still
	 * 404 (see `test_non_occurrence_datetime_returns_404()`).
	 *
	 * The occurrence chosen is one the split **moves**, and the URL is captured
	 * **before** the split runs, so nothing about the assertion can be satisfied
	 * by the origin post still owning the row.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_a_permalink_minted_before_a_split_redirects_to_the_new_owner(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 6 );

		// Occurrences land at +5/+12/+19/+26/+33/+40 days. The split happens at
		// the fourth, so the fifth is a row that moves.
		$split_at = Occurrences::recurrence_id( $anchor_start->modify( '+21 days' ) );
		$moved    = Occurrences::recurrence_id( $anchor_start->modify( '+28 days' ) );
		$before   = Rewrite::get_occurrence_url( $post_id, $moved );

		$this->assertNotEmpty( $before, 'Fixture setup: the pre-split occurrence URL should exist.' );

		$result  = Splitter::get_instance()->split_forward( $post_id, $split_at );
		$forward = (int) $result['forward_post_id'];

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );

		$expected = Rewrite::get_occurrence_url( $forward, $moved );

		$this->assertNotSame(
			$before,
			$expected,
			'Fixture setup: the occurrence must genuinely live at a different URL after the split.'
		);

		$this->assert_redirect_to(
			$expected,
			function () use ( $before ): void {
				$this->go_to( $before );
			},
			301
		);
	}

	/**
	 * An occurrence the split leaves behind keeps resolving on its own post,
	 * with no redirect at all.
	 *
	 * The control for the test above: without it, a `parse_request()` that
	 * redirected unconditionally would pass that one.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_an_occurrence_left_behind_by_a_split_does_not_redirect(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 6 );

		$split_at = Occurrences::recurrence_id( $anchor_start->modify( '+21 days' ) );
		$stayed   = Occurrences::recurrence_id( $anchor_start->modify( '+7 days' ) );

		Splitter::get_instance()->split_forward( $post_id, $split_at );

		$url = Rewrite::get_occurrence_url( $post_id, $stayed );

		$this->assert_not_redirect(
			function () use ( $url ): void {
				$this->go_to( $url );
			}
		);

		$this->assertFalse( is_404(), 'An occurrence still owned by the origin post must not 404 after a split.' );
		$this->assertSame(
			$stayed,
			get_query_var( Context::QUERY_VAR ),
			'An occurrence still owned by the origin post should resolve on the origin post.'
		);
	}

	/**
	 * A pre-split occurrence URL never reveals a private new owner to the public.
	 *
	 * The sibling-redirect branch of `parse_request()` must carry the same
	 * `can_follow_to()` guard the bare-series branch already has: a logged-out
	 * visitor requesting the public origin's pre-split occurrence URL must not
	 * receive a redirect naming the private sibling's permalink. The response
	 * has to be a non-revealing 404 with canonical redirection suppressed, the
	 * same answer a nonexistent occurrence gets, so the response does not act
	 * as an existence oracle for the private post.
	 *
	 * @covers ::parse_request
	 * @covers ::can_follow_to
	 *
	 * @return void
	 */
	public function test_a_pre_split_permalink_does_not_reveal_a_private_new_owner(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 6 );

		// Occurrences land at +5/+12/+19/+26/+33/+40 days. The split happens at
		// the fourth, so the fifth is a row the split moves to the new owner.
		$split_at = Occurrences::recurrence_id( $anchor_start->modify( '+21 days' ) );
		$moved    = Occurrences::recurrence_id( $anchor_start->modify( '+28 days' ) );
		$before   = Rewrite::get_occurrence_url( $post_id, $moved );

		$result  = Splitter::get_instance()->split_forward( $post_id, $split_at );
		$forward = (int) $result['forward_post_id'];

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );
		$this->assertSame(
			$forward,
			(int) Occurrences::get_instance()->find_in_series(
				Series::get_instance()->resolve_post_ids( $post_id ),
				$moved
			)['series_post_id'],
			'Fixture setup: the moved occurrence must genuinely be owned by the forward post, or the'
				. ' guard under test is not the mechanism that decides this request.'
		);

		wp_update_post(
			array(
				'ID'          => $forward,
				'post_status' => 'private',
			)
		);
		wp_set_current_user( 0 );

		$this->assert_not_redirect(
			function () use ( $before ): void {
				$this->go_to( $before );
			}
		);

		$this->assertTrue(
			is_404(),
			'A pre-split URL whose new owner is private must answer the public with a plain 404.'
		);
		$this->assertNull(
			redirect_canonical( $before, false ),
			'redirect_canonical() must be neutralized, or WP would 301 the 404 back to the bare series URL.'
		);
	}

	/**
	 * An editor who may read the private new owner is still redirected to it.
	 *
	 * The authorization-side control for the test above: without it, that test
	 * passes against a sibling branch that never redirects anybody anywhere.
	 *
	 * @covers ::parse_request
	 * @covers ::can_follow_to
	 *
	 * @return void
	 */
	public function test_a_pre_split_permalink_follows_to_a_private_new_owner_for_a_reader_of_it(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 6 );

		$split_at = Occurrences::recurrence_id( $anchor_start->modify( '+21 days' ) );
		$moved    = Occurrences::recurrence_id( $anchor_start->modify( '+28 days' ) );
		$before   = Rewrite::get_occurrence_url( $post_id, $moved );

		$result  = Splitter::get_instance()->split_forward( $post_id, $split_at );
		$forward = (int) $result['forward_post_id'];

		wp_update_post(
			array(
				'ID'          => $forward,
				'post_status' => 'private',
			)
		);
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$expected = Rewrite::get_occurrence_url( $forward, $moved );

		$this->assert_redirect_to(
			$expected,
			function () use ( $before ): void {
				$this->go_to( $before );
			},
			301
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Direct coverage for the shared 404 refusal helper.
	 *
	 * Invoked directly because xdebug does not trace a protected helper called
	 * through a short same-class delegation from `parse_request()` (see the
	 * "Extracted same-class helpers" rule in `AGENTS.md`), even though both
	 * refusal paths run it through real requests in the tests above.
	 *
	 * @covers ::refuse_with_404
	 *
	 * @return void
	 */
	public function test_refuse_with_404_sets_the_error_and_neutralizes_canonical(): void {
		$wp             = new WP();
		$wp->query_vars = array();

		Utility::invoke_hidden_method( Rewrite::get_instance(), 'refuse_with_404', array( $wp ) );

		$this->assertSame(
			'404',
			$wp->query_vars['error'],
			'The refusal must mark the request a 404.'
		);
		$this->assertNotFalse(
			has_filter( 'redirect_canonical', '__return_false' ),
			'The refusal must suppress canonical redirection so the 404 stands.'
		);

		remove_filter( 'redirect_canonical', '__return_false' );
	}

	/**
	 * Visiting a recurring series at its bare permalink, with no occurrence
	 * segment, resolves to the next upcoming occurrence.
	 *
	 * @covers ::parse_request
	 * @covers ::maybe_resolve_bare_series
	 * @covers ::next_upcoming_recurrence_id
	 *
	 * @return void
	 */
	public function test_bare_series_url_resolves_next_upcoming_occurrence(): void {
		// Occurrences at -15, -8, -1, +6, +13 days. The first three are
		// already past by the time the request is made, so "+6 days" is the
		// next upcoming one.
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( -15, 7, 5 );
		// Occurrences land at anchor_start + 0/7/14/21/28 days, i.e. -15/-8/-1/+6/+13
		// days relative to "now", and the "+21" entry is the first upcoming one.
		$expected_recurrence_id = Occurrences::recurrence_id( $anchor_start->modify( '+21 days' ) );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertFalse( is_404(), 'The bare series URL must not 404.' );
		$this->assertSame(
			$expected_recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'The bare series URL should resolve the occurrence query var to the next upcoming occurrence.'
		);
	}

	/**
	 * Coverage for an occurrence that is in progress when the bare series
	 * URL is requested. The repository defines upcoming inclusively on the
	 * occurrence's end, matching `Event\Query::get_datetime_comparison_column()`'s
	 * "a running event is still upcoming" rule, so the visitor who opens the
	 * series URL during an occurrence must land on that occurrence rather
	 * than be skipped ahead to the next one.
	 *
	 * The in-progress occurrence is deliberately neither the series' first
	 * row nor its first row starting after now: a resolver that returned
	 * the first scheduled row, or one that bounds on the start, both
	 * produce a different answer than the required one.
	 *
	 * @covers ::next_upcoming_recurrence_id
	 *
	 * @return void
	 */
	public function test_bare_series_url_resolves_an_in_progress_occurrence(): void {
		$tz    = new DateTimeZone( 'America/New_York' );
		$start = ( new DateTimeImmutable( 'now', $tz ) )->modify( '-7 days' )->modify( '-1 hour' );

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		$this->project_daily_series_at( $post_id, $start, 7, 3 );

		// Occurrences run at seven days ago (ended), one hour ago (in
		// progress for another hour of its two-hour duration), and seven
		// days out (future). The in-progress one is the required answer.
		$expected = Occurrences::recurrence_id( $start->modify( '+7 days' ) );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertFalse( is_404(), 'The bare series URL must not 404.' );
		$this->assertSame(
			$expected,
			get_query_var( Context::QUERY_VAR ),
			'An in-progress occurrence is still upcoming, so the bare URL must resolve to it rather'
				. ' than skip to the next one.'
		);
	}

	/**
	 * Coverage for fragment semantics end to end, through a real request: a
	 * series can span more than one post (the forward split, reachable today
	 * via the `gatherpress_series_post_ids` filter), and a sibling post's
	 * occurrence that sorts earlier than this post's own must not be mistaken
	 * for it. The companion unit test asserts the scoping in the emitted
	 * statement; this one asserts what the visitor gets.
	 *
	 * @covers ::next_upcoming_recurrence_id
	 *
	 * @return void
	 */
	public function test_bare_series_url_skips_an_earlier_sibling_series_row(): void {
		// The sibling's own occurrence is closer to "now" than this post's,
		// so it sorts first in the combined, interleaved result set.
		list( $sibling_post_id, )       = $this->create_relative_daily_series( 3, 7, 1 );
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 10, 7, 1 );
		$expected_recurrence_id         = Occurrences::recurrence_id( $anchor_start );

		$make_siblings = static function (
			array $post_ids,
			int $resolved_post_id
		) use (
			$post_id,
			$sibling_post_id
): array {
			if ( $resolved_post_id === $post_id ) {
				return array( $post_id, $sibling_post_id );
			}

			return $post_ids;
		};
		add_filter( 'gatherpress_series_post_ids', $make_siblings, 10, 2 );

		$this->go_to( get_permalink( $post_id ) );

		remove_filter( 'gatherpress_series_post_ids', $make_siblings, 10 );

		$this->assertFalse( is_404(), 'The bare series URL must not 404.' );
		$this->assertSame(
			$expected_recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'The sibling series row must not be mistaken for this post\'s own next occurrence.'
		);
	}

	/**
	 * The bare-URL resolver answers from one bounded statement rather than
	 * hydrating a series' scheduled history and filtering it in PHP.
	 *
	 * The fixture puts twelve elapsed occurrences ahead of the single upcoming
	 * one, so the two shapes are distinguishable: an unbounded read hydrates
	 * thirteen rows and finds its answer at the last of them, while a bounded
	 * read asks the database for exactly the row it returns. The emitted
	 * statement is what is pinned, because the identifier the method returns is
	 * the same either way, which is the point: the read gets cheaper and the
	 * answer does not move.
	 *
	 * @covers ::next_upcoming_recurrence_id
	 *
	 * @return void
	 */
	public function test_next_upcoming_recurrence_id_bounds_its_read_in_sql(): void {
		global $wpdb;

		// Occurrences land every seven days from eighty days ago, so the
		// thirteenth of them, four days out, is the only upcoming one.
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( -80, 7, 13 );
		$expected                       = Occurrences::recurrence_id( $anchor_start->modify( '+84 days' ) );

		$resolved = Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'next_upcoming_recurrence_id',
			array( $post_id )
		);

		$this->assertSame(
			$expected,
			$resolved,
			'Failed to assert the resolver answers with the series\' only upcoming occurrence.'
		);
		$this->assertStringContainsString(
			'datetime_end_gmt >=',
			$wpdb->last_query,
			'Failed to assert the upcoming bound is a SQL predicate rather than a PHP pass over the whole history.'
		);
		$this->assertStringContainsString(
			'LIMIT 1',
			$wpdb->last_query,
			'Failed to assert the read stops at the one row the answer needs.'
		);
	}

	/**
	 * The resolver's read names only the requested post, which is what makes
	 * its `LIMIT 1` safe.
	 *
	 * A sibling resolved onto the series by the `gatherpress_series_post_ids`
	 * filter owns an occurrence that sorts ahead of the requested post's own,
	 * so a read widened across the series and bounded to one row answers with
	 * the sibling's occurrence. Fragment semantics says a bare URL resolves
	 * within the post it names, so the query is scoped to that post rather than
	 * widened and filtered afterwards.
	 *
	 * @covers ::next_upcoming_recurrence_id
	 *
	 * @return void
	 */
	public function test_next_upcoming_recurrence_id_scopes_its_read_to_the_requested_post(): void {
		global $wpdb;

		// The sibling's own occurrence is closer to "now" than this post's, so
		// it would be the first row of a widened, bounded read.
		list( $sibling_post_id, )       = $this->create_relative_daily_series( 3, 7, 1 );
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 10, 7, 1 );
		$expected                       = Occurrences::recurrence_id( $anchor_start );

		$make_siblings = static function (
			array $post_ids,
			int $resolved_post_id
		) use (
			$post_id,
			$sibling_post_id
): array {
			if ( $resolved_post_id === $post_id ) {
				return array( $post_id, $sibling_post_id );
			}

			return $post_ids;
		};
		add_filter( 'gatherpress_series_post_ids', $make_siblings, 10, 2 );

		$resolved = Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'next_upcoming_recurrence_id',
			array( $post_id )
		);

		remove_filter( 'gatherpress_series_post_ids', $make_siblings, 10 );

		$this->assertSame(
			$expected,
			$resolved,
			'Failed to assert the resolver answers with the requested post\'s own next occurrence.'
		);
		$this->assertStringContainsString(
			sprintf( 'series_post_id IN ( %d )', $post_id ),
			$wpdb->last_query,
			'Failed to assert the read names the requested post alone, which is what makes the bound safe.'
		);
	}

	/**
	 * Coverage for `maybe_resolve_bare_series` when the series has no
	 * upcoming occurrence left. The query var must stay unset so the post
	 * renders exactly as a non-recurring event would.
	 *
	 * @covers ::maybe_resolve_bare_series
	 * @covers ::next_upcoming_recurrence_id
	 *
	 * @return void
	 */
	public function test_bare_series_url_leaves_query_var_unset_when_series_has_lapsed(): void {
		list( $post_id, ) = $this->create_relative_daily_series( -30, 7, 5 );

		$this->go_to( get_permalink( $post_id ) );

		$this->assertFalse( is_404(), 'A lapsed series bare URL must not 404.' );
		$this->assertSame(
			'',
			(string) get_query_var( Context::QUERY_VAR ),
			'A lapsed series has no upcoming occurrence, so the query var must stay unset.'
		);
	}

	/**
	 * Split a relative daily series so the origin keeps only past dates.
	 *
	 * Occurrences land at -16/-9/-2/+5/+12 days, and the split falls on the
	 * fourth. Every row the origin keeps is therefore behind now and every row
	 * the forward post takes is ahead of it, which is what makes the assertions
	 * about "the series' next upcoming row" unable to be satisfied by a row the
	 * origin still owns.
	 *
	 * @since 0.36.0
	 *
	 * @return array{0: int, 1: int, 2: string} Origin ID, forward ID, and the first forward identifier.
	 */
	protected function split_into_a_lapsed_origin(): array {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( -16, 7, 5 );

		$split_at = Occurrences::recurrence_id( $anchor_start->modify( '+21 days' ) );
		$result   = Splitter::get_instance()->split_forward( $post_id, $split_at );
		$forward  = (int) $result['forward_post_id'];

		$this->assertGreaterThan( 0, $forward, 'Fixture setup: the split should have produced a forward post.' );

		$now = current_time( 'mysql', true );

		foreach ( Occurrences::get_instance()->select_for_series( array( $post_id ) ) as $row ) {
			$this->assertLessThan(
				$now,
				$row['datetime_start_gmt'],
				'Fixture setup: every row the origin keeps must be in the past, or the redirect could be'
					. ' satisfied by the origin\'s own next occurrence.'
			);
		}

		return array( $post_id, $forward, $split_at );
	}

	/**
	 * A lapsed fragment's bare URL follows the logical series.
	 *
	 * The origin's own dates have all passed while the same logical series has
	 * upcoming dates on the post a forward split created. Rendering the origin's
	 * stale anchor is the defect; the request is answered with a `302` to the
	 * occurrence URL of the series' earliest upcoming row instead, so the
	 * occurrence keeps exactly one canonical address and it sits under the post
	 * that owns the row.
	 *
	 * The status is deliberately temporary. The target is whichever occurrence
	 * is next at the instant of the request, so a cacheable `301` would freeze
	 * a visitor on a date that later passes, which is the defect this test
	 * names in the sentence above.
	 *
	 * @covers ::maybe_resolve_bare_series
	 * @covers ::maybe_follow_series
	 * @covers ::next_upcoming_in_series
	 * @covers ::can_follow_to
	 *
	 * @return void
	 */
	public function test_a_lapsed_fragments_bare_url_redirects_to_the_series_next_row(): void {
		list( $post_id, $forward, $split_at ) = $this->split_into_a_lapsed_origin();

		$expected = Rewrite::get_occurrence_url( $forward, $split_at );

		$this->assertNotEmpty( $expected, 'Fixture setup: the forward occurrence must have a URL.' );

		$this->assert_redirect_to(
			$expected,
			function () use ( $post_id ): void {
				$this->go_to( get_permalink( $post_id ) );
			},
			302
		);
	}

	/**
	 * A fragment with upcoming dates of its own is never forwarded.
	 *
	 * The control for the test above: a `parse_request()` that forwarded to the
	 * series' earliest row unconditionally would pass that one and break every
	 * ordinary bare URL, because the origin's own next date is rarely the
	 * series' earliest.
	 *
	 * @covers ::maybe_resolve_bare_series
	 * @covers ::maybe_follow_series
	 *
	 * @return void
	 */
	public function test_a_fragment_with_its_own_upcoming_date_is_not_forwarded(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( -2, 7, 5 );

		// Rows at -2/+5/+12/+19/+26 days, split at the fourth: the origin keeps
		// one past and two upcoming dates, so it answers for itself.
		$split_at = Occurrences::recurrence_id( $anchor_start->modify( '+21 days' ) );
		$expected = Occurrences::recurrence_id( $anchor_start->modify( '+7 days' ) );

		Splitter::get_instance()->split_forward( $post_id, $split_at );

		$this->assert_not_redirect(
			function () use ( $post_id ): void {
				$this->go_to( get_permalink( $post_id ) );
			}
		);

		$this->assertSame(
			$expected,
			(string) get_query_var( Context::QUERY_VAR ),
			'A fragment with an upcoming date of its own must resolve it in place, under its own slug.'
		);
	}

	/**
	 * A duplicate row never forwards a bare URL to a post the guard excluded.
	 *
	 * `next_upcoming_in_series()` filters the series through `can_follow_to()`
	 * and queries only the survivors, but it then passes just the recurrence
	 * identifier to `Occurrence_Identity::resolve()`, which goes back through
	 * `find_in_series()` over the whole series and picks a winner with
	 * `ORDER BY series_post_id ASC LIMIT 1`. A lower-ID sibling carrying the
	 * same identifier therefore wins the re-resolution even though this method
	 * just excluded it, and its slug would go into a `Location` header for an
	 * anonymous visitor with no second permission check.
	 *
	 * The duplicate is written directly here because the uniqueness guard this
	 * stack adds prevents it going forward. That guard's own comment is what
	 * makes the case: rows written before it existed, or by anything else with
	 * table access, can still carry the duplicate, and a site upgraded from an
	 * earlier build of this stack is exactly that state.
	 *
	 * Failing closed is the contract: with the excluded post winning the
	 * re-resolution there is no safe target, so the origin renders as a lapsed
	 * series rather than forwarding anywhere.
	 *
	 * @covers ::maybe_follow_series
	 * @covers ::next_upcoming_in_series
	 * @covers ::can_follow_to
	 *
	 * @return void
	 */
	public function test_a_bare_url_does_not_follow_a_duplicate_row_to_an_excluded_post(): void {
		global $wpdb;

		list( $post_id, $forward ) = $this->split_into_a_lapsed_origin();

		$row = Occurrences::get_instance()->select_bounded_occurrence( array( $forward ), true );

		$this->assertNotNull( $row, 'Fixture setup: the forward post must have an upcoming occurrence.' );

		// A public sibling with a higher post ID than the private one, so the
		// re-resolution's ascending order prefers the post that was excluded.
		$public = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$this->assertGreaterThan(
			$forward,
			$public,
			'Fixture setup: the public sibling must sort after the private one by post ID.'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- writing the legacy row the guard now prevents.
		$wpdb->insert(
			sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix ),
			array(
				'series_post_id'     => $public,
				'recurrence_id'      => (string) $row['recurrence_id'],
				'datetime_start'     => (string) $row['datetime_start'],
				'datetime_start_gmt' => (string) $row['datetime_start_gmt'],
				'datetime_end'       => (string) $row['datetime_end'],
				'datetime_end_gmt'   => (string) $row['datetime_end_gmt'],
				'timezone'           => (string) $row['timezone'],
				'status'             => Occurrences::STATUS_SCHEDULED,
			)
		);

		wp_update_post(
			array(
				'ID'          => $forward,
				'post_status' => 'private',
			)
		);

		$series = array( $post_id, $forward, $public );

		add_filter(
			'gatherpress_series_post_ids',
			static function () use ( $series ): array {
				return $series;
			}
		);

		wp_set_current_user( 0 );

		$this->assert_not_redirect(
			function () use ( $post_id ): void {
				$this->go_to( get_permalink( $post_id ) );
			}
		);
	}

	/**
	 * A private sibling is never revealed by a bare URL.
	 *
	 * A visitor who may not read the forward post must not be sent to it, and
	 * must not be able to tell from the response that it exists. The request
	 * answers exactly as a fully lapsed series does.
	 *
	 * @covers ::maybe_follow_series
	 * @covers ::next_upcoming_in_series
	 * @covers ::can_follow_to
	 *
	 * @return void
	 */
	public function test_a_bare_url_does_not_follow_to_a_private_sibling(): void {
		list( $post_id, $forward ) = $this->split_into_a_lapsed_origin();

		wp_update_post(
			array(
				'ID'          => $forward,
				'post_status' => 'private',
			)
		);
		wp_set_current_user( 0 );

		$this->assert_not_redirect(
			function () use ( $post_id ): void {
				$this->go_to( get_permalink( $post_id ) );
			}
		);

		$this->assertSame(
			'',
			(string) get_query_var( Context::QUERY_VAR ),
			'A private sibling must leave the origin rendering as a lapsed series, with no occurrence context.'
		);
	}

	/**
	 * An editor who may read the private sibling is forwarded to it.
	 *
	 * Without this the test above passes against a `maybe_follow_series()` that
	 * never forwards at all.
	 *
	 * @covers ::maybe_follow_series
	 * @covers ::can_follow_to
	 *
	 * @return void
	 */
	public function test_a_bare_url_follows_to_a_private_sibling_for_a_reader_of_it(): void {
		list( $post_id, $forward, $split_at ) = $this->split_into_a_lapsed_origin();

		wp_update_post(
			array(
				'ID'          => $forward,
				'post_status' => 'private',
			)
		);
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$expected = Rewrite::get_occurrence_url( $forward, $split_at );

		$this->assert_redirect_to(
			$expected,
			function () use ( $post_id ): void {
				$this->go_to( get_permalink( $post_id ) );
			},
			302
		);

		wp_set_current_user( 0 );
	}

	/**
	 * A password-protected sibling is followed, not bypassed.
	 *
	 * The password gate belongs to the destination's own rendering and still
	 * runs there. Refusing the redirect would instead hide a date the series
	 * genuinely has from a visitor who may well hold the password, and the
	 * destination's permalink is public knowledge either way.
	 *
	 * @covers ::maybe_follow_series
	 * @covers ::can_follow_to
	 *
	 * @return void
	 */
	public function test_a_bare_url_follows_to_a_password_protected_sibling(): void {
		list( $post_id, $forward, $split_at ) = $this->split_into_a_lapsed_origin();

		wp_update_post(
			array(
				'ID'            => $forward,
				'post_password' => 'correct-horse',
			)
		);

		$expected = Rewrite::get_occurrence_url( $forward, $split_at );

		$this->assert_redirect_to(
			$expected,
			function () use ( $post_id ): void {
				$this->go_to( get_permalink( $post_id ) );
			},
			302
		);

		$this->assertTrue(
			post_password_required( $forward ),
			'The destination must still be behind its password form after the redirect.'
		);
	}

	/**
	 * A series of one post has nowhere to follow to.
	 *
	 * Invoked directly because xdebug does not trace a protected helper called
	 * from a short delegation in the same class: the body runs, and the coverage
	 * report says otherwise (see the "Extracted same-class helpers" rule in
	 * `AGENTS.md`). The guard is what keeps every unsplit series from paying a
	 * second occurrence query on a bare URL, which is every series on a site
	 * that has never split anything.
	 *
	 * @covers ::next_upcoming_in_series
	 *
	 * @return void
	 */
	public function test_next_upcoming_in_series_is_null_for_a_single_post_series(): void {
		list( $post_id, ) = $this->create_relative_daily_series( -30, 7, 5 );

		$this->assertNull(
			Utility::invoke_hidden_method(
				Rewrite::get_instance(),
				'next_upcoming_in_series',
				array( $post_id )
			),
			'A post whose series is itself has no sibling row to follow.'
		);
	}

	/**
	 * Move one occurrence row's datetimes, in place, without touching its identity.
	 *
	 * The row keeps its `recurrence_id`, because identity never derives from the
	 * stored datetimes; only the columns the skip-past comparison reads move.
	 * Both the GMT and the local pair are written so the row stays internally
	 * consistent.
	 *
	 * @since 0.36.0
	 *
	 * @param int               $post_id       Post that owns the row.
	 * @param string            $recurrence_id The row's identifier.
	 * @param DateTimeImmutable $start_utc     New start, in UTC.
	 * @param DateTimeImmutable $end_utc       New end, in UTC.
	 *
	 * @return void
	 */
	protected function reschedule_occurrence(
		int $post_id,
		string $recurrence_id,
		DateTimeImmutable $start_utc,
		DateTimeImmutable $end_utc
	): void {
		global $wpdb;

		$local = new DateTimeZone( 'America/New_York' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix ),
			array(
				'datetime_start'     => $start_utc->setTimezone( $local )->format( 'Y-m-d H:i:s' ),
				'datetime_start_gmt' => $start_utc->format( 'Y-m-d H:i:s' ),
				'datetime_end'       => $end_utc->setTimezone( $local )->format( 'Y-m-d H:i:s' ),
				'datetime_end_gmt'   => $end_utc->format( 'Y-m-d H:i:s' ),
			),
			array(
				'series_post_id' => $post_id,
				'recurrence_id'  => $recurrence_id,
			)
		);
	}

	/**
	 * A lapsed fragment's bare URL lands on the occurrence happening right now.
	 *
	 * The forward sibling's first occurrence started an hour ago and runs for
	 * another hour, and a later occurrence follows it. The visitor opening the
	 * lapsed origin's bare URL is on their way to the event currently in
	 * progress, so skipping it and redirecting to the later date sends them a
	 * week past the room they are looking for. The repository defines upcoming
	 * inclusively elsewhere: `Occurrences::select_bounded_occurrence()` bounds
	 * on `datetime_end_gmt`, and this redirect must agree with it.
	 *
	 * The fixture is chosen so the right answer and the wrong answer differ:
	 * a skip-past comparison reading the start would answer with the later
	 * occurrence's URL, and the assertion names the in-progress one.
	 *
	 * @covers ::maybe_follow_series
	 * @covers ::next_upcoming_in_series
	 *
	 * @return void
	 */
	public function test_a_lapsed_fragments_bare_url_redirects_to_the_occurrence_in_progress(): void {
		list( $post_id, $forward, $split_at ) = $this->split_into_a_lapsed_origin();

		$now = new DateTimeImmutable( current_time( 'mysql', true ), new DateTimeZone( 'UTC' ) );

		$this->reschedule_occurrence(
			$forward,
			$split_at,
			$now->modify( '-1 hour' ),
			$now->modify( '+1 hour' )
		);

		$expected = Rewrite::get_occurrence_url( $forward, $split_at );

		$this->assertNotEmpty( $expected, 'Fixture setup: the in-progress occurrence must have a URL.' );

		$this->assert_redirect_to(
			$expected,
			function () use ( $post_id ): void {
				$this->go_to( get_permalink( $post_id ) );
			},
			302
		);
	}

	/**
	 * An occurrence in progress wins over a later one.
	 *
	 * Direct coverage for the helper's skip-past bound, invoked directly
	 * because xdebug does not trace a protected helper called through a short
	 * same-class delegation (see the "Extracted same-class helpers" rule in
	 * `AGENTS.md`). The sibling row that started an hour ago and has an hour
	 * left must be the answer while a later row exists to be wrongly preferred.
	 *
	 * @covers ::next_upcoming_in_series
	 *
	 * @return void
	 */
	public function test_next_upcoming_in_series_returns_the_occurrence_in_progress(): void {
		list( $post_id, $forward, $split_at ) = $this->split_into_a_lapsed_origin();

		$now = new DateTimeImmutable( current_time( 'mysql', true ), new DateTimeZone( 'UTC' ) );

		$this->reschedule_occurrence(
			$forward,
			$split_at,
			$now->modify( '-1 hour' ),
			$now->modify( '+1 hour' )
		);

		$identity = Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'next_upcoming_in_series',
			array( $post_id )
		);

		$this->assertNotNull( $identity, 'A series with an occurrence in progress has a row to follow.' );
		$this->assertSame(
			$split_at,
			$identity->recurrence_id,
			'An occurrence that has started but not finished is the series\' next row, not the one after it.'
		);
	}

	/**
	 * An occurrence whose end is exactly now is still upcoming.
	 *
	 * The boundary of the inclusive bound: `datetime_end_gmt >= now` keeps the
	 * row when the two are equal. The clock cannot be frozen under
	 * `current_time()`, so the arrangement retries until the helper provably
	 * ran within the same second the row's end names, making the equality the
	 * case actually exercised rather than a case a ticking clock skipped over.
	 *
	 * @covers ::next_upcoming_in_series
	 *
	 * @return void
	 */
	public function test_next_upcoming_in_series_keeps_an_occurrence_ending_exactly_now(): void {
		list( $post_id, $forward, $split_at ) = $this->split_into_a_lapsed_origin();

		$attempts = 0;

		do {
			$now_string = current_time( 'mysql', true );
			$now        = new DateTimeImmutable( $now_string, new DateTimeZone( 'UTC' ) );

			$this->reschedule_occurrence( $forward, $split_at, $now->modify( '-2 hours' ), $now );

			$identity = Utility::invoke_hidden_method(
				Rewrite::get_instance(),
				'next_upcoming_in_series',
				array( $post_id )
			);

			++$attempts;
		} while ( current_time( 'mysql', true ) !== $now_string && $attempts < 10 );

		$this->assertSame(
			$now_string,
			current_time( 'mysql', true ),
			'Fixture setup: the helper must have run within the second the row ends on.'
		);
		$this->assertNotNull( $identity, 'An occurrence ending exactly now must still be followed.' );
		$this->assertSame(
			$split_at,
			$identity->recurrence_id,
			'The boundary is inclusive: an end equal to now is not in the past.'
		);
	}

	/**
	 * The sibling read behind a bare-URL redirect is bounded, not the row set.
	 *
	 * The redirect decision needs exactly one row, the series' earliest
	 * unfinished occurrence on a readable sibling, so the statement that
	 * answers it must carry the end-inclusive bound and a `LIMIT 1` rather
	 * than hydrating every row of the series into PHP. On a five-year daily
	 * series split a few times, the difference is one row against several
	 * hundred, paid by every public, unauthenticated hit on a lapsed
	 * fragment's bare URL.
	 *
	 * The total order is asserted alongside the bound: a `LIMIT 1` over an
	 * ambiguous order is a coin flip, so the statement has to break start
	 * ties on the row's own composite key.
	 *
	 * @covers ::next_upcoming_in_series
	 *
	 * @return void
	 */
	public function test_next_upcoming_in_series_reads_one_bounded_limit_one_statement(): void {
		list( $post_id, $forward, $split_at ) = $this->split_into_a_lapsed_origin();

		global $wpdb;

		// The split memoized this identifier's resolution; the capture below
		// must see the read a fresh request would pay, not a memo hit.
		Context::flush_resolved();

		$table      = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$statements = array();
		$capture    = static function ( $query ) use ( &$statements, $table ) {
			if ( str_starts_with( $query, 'SELECT' ) && str_contains( $query, $table ) ) {
				$statements[] = $query;
			}

			return $query;
		};

		add_filter( 'query', $capture );

		$identity = Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'next_upcoming_in_series',
			array( $post_id )
		);

		remove_filter( 'query', $capture );

		$this->assertNotNull( $identity, 'Fixture setup: the lapsed origin must have a sibling row to follow.' );
		$this->assertSame(
			$split_at,
			$identity->recurrence_id,
			'The bounded read must still choose the series\' earliest unfinished occurrence.'
		);
		$this->assertSame(
			$forward,
			$identity->owner_post_id,
			'The bounded read must still resolve the row\'s owner through the identity seam.'
		);

		$bounded = array_values(
			array_filter(
				$statements,
				static function ( string $statement ): bool {
					return str_contains( $statement, 'datetime_end_gmt >=' );
				}
			)
		);

		$this->assertCount(
			1,
			$bounded,
			'Exactly one statement must carry the end-inclusive bound.'
		);
		$this->assertStringContainsString(
			'LIMIT 1',
			$bounded[0],
			'The bounded statement must read one row, not the series\' whole row set.'
		);
		$this->assertStringContainsString(
			'ORDER BY datetime_start_gmt ASC, series_post_id ASC, recurrence_id ASC',
			$bounded[0],
			'The bounded statement must carry a total order, or its LIMIT 1 is a coin flip on start ties.'
		);

		foreach ( $statements as $statement ) {
			$this->assertStringContainsString(
				'LIMIT 1',
				$statement,
				'No occurrence read on this path may hydrate an unbounded row set: ' . $statement
			);
		}
	}

	/**
	 * A split series with nothing left anywhere follows nowhere.
	 *
	 * The arm between "no readable sibling" and "a sibling row to follow":
	 * the siblings are readable, the bounded read runs, and it matches
	 * nothing because every row of every fragment has ended. The bare URL
	 * must render the named post as a lapsed series rather than redirect.
	 *
	 * @covers ::next_upcoming_in_series
	 *
	 * @return void
	 */
	public function test_next_upcoming_in_series_is_null_when_every_sibling_row_has_passed(): void {
		list( $post_id, $forward ) = $this->split_into_a_lapsed_origin();

		$now = new DateTimeImmutable( current_time( 'mysql', true ), new DateTimeZone( 'UTC' ) );

		foreach ( Occurrences::get_instance()->select_for_series( array( $forward ) ) as $row ) {
			$this->reschedule_occurrence(
				$forward,
				(string) $row['recurrence_id'],
				$now->modify( '-2 days' ),
				$now->modify( '-1 day' )
			);
		}

		$this->assertNull(
			Utility::invoke_hidden_method(
				Rewrite::get_instance(),
				'next_upcoming_in_series',
				array( $post_id )
			),
			'A series whose every row has ended has no occurrence to forward to.'
		);
	}

	/**
	 * A post that no longer exists is readable by nobody.
	 *
	 * The arm a live fixture cannot reach: an occurrence row whose owner has
	 * been deleted out from under it. Refusing it is what keeps a redirect from
	 * being built against a post with no permalink.
	 *
	 * @covers ::is_publicly_readable
	 *
	 * @return void
	 */
	public function test_a_missing_post_is_not_publicly_readable(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$this->assertTrue(
			Rewrite::is_publicly_readable( $post_id ),
			'A published event is readable by the public.'
		);

		wp_delete_post( $post_id, true );

		$this->assertFalse(
			Rewrite::is_publicly_readable( $post_id ),
			'A post that no longer exists must never be treated as readable.'
		);
	}

	/**
	 * Coverage for `maybe_resolve_bare_series` on a non-recurring event: no
	 * occurrence rows exist at all, so `select_for_series()` returns an
	 * empty array and the query var stays unset.
	 *
	 * A second, unrelated series has to exist for this test to mean what its
	 * name says. `parse_request()` bails on
	 * `Query::site_has_recurring_events()` before either method named below
	 * runs, so on a site holding nothing but this one plain event the request
	 * never reaches the resolver and the query var stays unset for a reason
	 * that has nothing to do with the post being asked for. The live series
	 * satisfies that first arm and leaves the empty row set as the only thing
	 * that can decide the outcome.
	 *
	 * @covers ::maybe_resolve_bare_series
	 * @covers ::next_upcoming_recurrence_id
	 *
	 * @return void
	 */
	public function test_bare_series_url_leaves_query_var_unset_for_non_recurring_event(): void {
		$this->create_relative_daily_series( 10, 7, 3 );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert that the site has recurring events, which parse_request() requires.'
		);

		$this->go_to( get_permalink( $post_id ) );

		$this->assertFalse( is_404(), 'A non-recurring event must not 404.' );
		$this->assertSame(
			'',
			(string) get_query_var( Context::QUERY_VAR ),
			'A non-recurring event has no occurrence rows, so the query var must stay unset.'
		);
	}

	/**
	 * A plain event permalink request on a site with no
	 * recurring events at all must never query the occurrence table.
	 * `Query::site_has_recurring_events()` is the authoritative, cheap
	 * (single autoloaded option read) guard `maybe_resolve_bare_series()`
	 * checks *before* `resolve_post_id_from_query_vars()` runs at all, on
	 * every request
	 * that has no occurrence segment (i.e. every ordinary event permalink on
	 * the entire site).
	 *
	 * The guard wraps the bare-series call inside `parse_request()` and this
	 * branch alone; its sibling test below pins the occurrence-segment
	 * branch's deliberate single primary-key read.
	 *
	 * @covers ::parse_request
	 * @covers ::maybe_resolve_bare_series
	 *
	 * @return void
	 */
	public function test_bare_series_resolution_skips_occurrence_query_without_recurring_events(): void {
		update_option( Query::HAS_RECURRING_OPTION, '0' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		global $wpdb;
		$occurrences_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$query_count       = 0;
		$count_queries     = static function ( string $query ) use ( $occurrences_table, &$query_count ): string {
			if ( str_contains( $query, $occurrences_table ) ) {
				++$query_count;
			}

			return $query;
		};
		add_filter( 'query', $count_queries );

		$this->go_to( get_permalink( $post_id ) );

		remove_filter( 'query', $count_queries );

		$this->assertFalse( is_404(), 'A plain event permalink must not 404.' );
		$this->assertSame(
			0,
			$query_count,
			'A plain event permalink request must not query the occurrence table when the site has no recurring events.'
		);
	}

	/**
	 * Coverage for the cost of the occurrence-segment branch of
	 * `parse_request()` on a site whose recurring flag is off.
	 *
	 * The sibling of the bare-series test above. That one proves the branch
	 * every ordinary request falls through to pays nothing; this one drives a
	 * real occurrence URL, whose branch is deliberately unguarded: a request
	 * already carrying an occurrence identifier pays one primary-key
	 * `Occurrences::get()` read, because that read is what lets a stale link
	 * 404 instead of silently rendering the series at its anchor date after
	 * the flag flips off.
	 *
	 * Ahead of it sits the schema probe `get()` shares with the list path,
	 * which is what keeps a blog whose table has not been created yet from
	 * running the read against a table that is not there. The memo is
	 * discarded first so the count is the branch's cold cost rather than
	 * whatever the process happened to have probed already; a request that
	 * also renders an events list pays the probe once between them.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_occurrence_url_pays_one_primary_key_read_without_recurring_events(): void {
		global $wpdb;

		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );

		$recurrence_id = Occurrences::recurrence_id( $anchor_start );
		$url           = Rewrite::get_occurrence_url( $post_id, $recurrence_id );

		// Flipped only after projection, so a real occurrence row exists and the
		// URL is genuinely well-formed: what is asserted is the branch's cost,
		// not a missing fixture.
		update_option( Query::HAS_RECURRING_OPTION, '0' );

		Occurrences::get_instance()->forget_table_exists();

		$occurrences_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$query_count       = 0;
		$count_queries     = static function ( string $query ) use ( $occurrences_table, &$query_count ): string {
			// The probe's `LIKE` pattern carries `esc_like()`'s escaping of every
			// underscore in the table name, so the name only matches once the
			// backslashes are taken back out. Without that the probe is invisible
			// to this count and the cost reported here is a statement short.
			if ( str_contains( str_replace( '\\', '', $query ), $occurrences_table ) ) {
				++$query_count;
			}

			return $query;
		};

		add_filter( 'query', $count_queries );

		$this->go_to( $url );

		remove_filter( 'query', $count_queries );

		$this->assertFalse(
			is_404(),
			'A real occurrence URL must keep resolving when only the flag has flipped off.'
		);
		$this->assertSame(
			2,
			$query_count,
			'The occurrence-segment branch pays its one primary-key read, behind one schema probe, on a flag-off site.'
		);
	}

	/**
	 * A stale occurrence URL keeps 404ing after the site's last recurrence
	 * rule goes away.
	 *
	 * `refresh_has_recurring_events()` writes the flag option to `'0'` when
	 * the last live recurring rule is removed or trashed, while the rewrite
	 * rule stays registered on `wp_loaded` unconditionally. Every previously
	 * shared occurrence URL still matches the rule, so the miss must still
	 * 404 instead of silently rendering the series at its anchor date, which
	 * is the exact outcome this class's docblock promises never happens.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_stale_occurrence_url_404s_after_the_recurring_flag_flips_off(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );

		// Positive control: a real occurrence URL resolves while the flag is on.
		$real_recurrence_id = Occurrences::recurrence_id( $anchor_start );
		$this->go_to( Rewrite::get_occurrence_url( $post_id, $real_recurrence_id ) );

		$this->assertFalse(
			is_404(),
			'A real occurrence URL must resolve while the flag is on.'
		);
		$this->assertSame(
			$real_recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'The real occurrence URL should carry its recurrence ID into the query.'
		);

		// The same stale URL, requested twice with only the option changed
		// between: this is the review's measured failure pair.
		$stale_url = Rewrite::get_occurrence_url( $post_id, '19991231T235959' );

		$this->go_to( $stale_url );

		$this->assertTrue(
			is_404(),
			'A stale occurrence URL must 404 while the flag is on.'
		);

		// The flag-on request above installed the redirect_canonical
		// neutralizer; clear it so the flag-off request below has to install
		// its own rather than inherit this one.
		remove_filter( 'redirect_canonical', '__return_false' );

		update_option( Query::HAS_RECURRING_OPTION, '0' );

		$this->go_to( $stale_url );

		$this->assertTrue(
			is_404(),
			'A stale occurrence URL must still 404 after the flag flips off, never render the series at its anchor.'
		);
		$this->assertNull(
			redirect_canonical( $stale_url, false ),
			'redirect_canonical() must be neutralized on the flag-off miss as well.'
		);
	}

	/**
	 * A canceled occurrence's URL resolves rather than 404s, so an attendee
	 * holding the link is told it was canceled instead of hitting a dead end.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_canceled_occurrence_url_resolves_rather_than_404(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );
		$recurrence_id                  = Occurrences::recurrence_id( $anchor_start );

		$this->assertTrue(
			Occurrences::get_instance()->set_status( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED ),
			'Fixture setup: set_status() should find the freshly projected row.'
		);

		$this->go_to( Rewrite::get_occurrence_url( $post_id, $recurrence_id ) );

		$this->assertFalse( is_404(), 'A canceled occurrence URL must resolve, not 404.' );
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'The occurrence query var should still carry the canceled occurrence\'s recurrence ID.'
		);
	}

	/**
	 * Feed-routing regression coverage: the singular event feed, the
	 * sitewide feed, and a taxonomy feed must all keep resolving after the
	 * occurrence rewrite rule is registered. This is what the rewrite-rule
	 * spike exists to protect.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 *
	 * @return void
	 */
	public function test_feed_routing_is_unaffected_by_occurrence_rewrite_rule(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_name'   => 'feed-regression-event',
				'post_status' => 'publish',
			)
		);

		$this->go_to( trailingslashit( get_permalink( $post_id ) ) . 'feed/' );
		$this->assertTrue( is_feed(), 'The singular event feed URL should still resolve as a feed.' );
		$this->assertFalse( is_404(), 'The singular event feed URL must not 404.' );

		$this->go_to( home_url( '/feed/' ) );
		$this->assertTrue( is_feed(), 'The sitewide feed URL should still resolve as a feed.' );
		$this->assertFalse( is_404(), 'The sitewide feed URL must not 404.' );

		// GatherPress registers a custom `feed/(ical)` endpoint per taxonomy
		// term rather than relying on WP's generic taxonomy feed rule. This
		// is the actual feed surface `Calendar\Setup` wires up for
		// `gatherpress_topic`, so it is what this regression test protects.
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => 'gatherpress_topic' ) );
		$this->go_to( trailingslashit( get_term_link( $term ) ) . 'feed/ical/' );
		$this->assertTrue( is_feed(), 'The taxonomy ical feed URL should still resolve as a feed.' );
		$this->assertFalse( is_404(), 'The taxonomy ical feed URL must not 404.' );
	}

	/**
	 * Coverage for `add_rewrite_rule_for_post_type` bailing when
	 * `get_post_type_object()` cannot resolve an object for a post type
	 * that declared the support without ever calling `register_post_type()`.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 *
	 * @return void
	 */
	public function test_add_rewrite_rule_for_post_type_bails_on_unregistered_post_type(): void {
		global $wp_rewrite;
		$before = $wp_rewrite->extra_rules_top;

		Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'add_rewrite_rule_for_post_type',
			array( 'gp_never_registered' )
		);

		$this->assertSame(
			$before,
			$wp_rewrite->extra_rules_top,
			'No rewrite rule should be added for a post type that was never registered.'
		);
	}

	/**
	 * Coverage for `add_rewrite_rule_for_post_type` bailing on a post type
	 * with rewrites disabled.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 *
	 * @return void
	 */
	public function test_add_rewrite_rule_for_post_type_bails_when_rewrite_disabled(): void {
		register_post_type(
			'gp_test_no_rewrite',
			array(
				'public'  => true,
				'rewrite' => false,
			)
		);

		global $wp_rewrite;
		$before = $wp_rewrite->extra_rules_top;

		Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'add_rewrite_rule_for_post_type',
			array( 'gp_test_no_rewrite' )
		);

		$this->assertSame(
			$before,
			$wp_rewrite->extra_rules_top,
			'No rewrite rule should be added for a post type with rewrites disabled.'
		);

		unregister_post_type( 'gp_test_no_rewrite' );
	}

	/**
	 * Coverage for `add_rewrite_rule_for_post_type` bailing when the post
	 * type's permastruct is not registered. A truthy `rewrite` normally
	 * guarantees one, but `remove_permastruct()` is public API and a rule
	 * built from an empty struct would start with a bare `/` and match
	 * nothing it advertises.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 *
	 * @return void
	 */
	public function test_add_rewrite_rule_for_post_type_bails_without_a_permastruct(): void {
		register_post_type(
			'gp_test_no_struct',
			array(
				'public'  => true,
				'rewrite' => array( 'slug' => 'gp-test-no-struct' ),
			)
		);
		remove_permastruct( 'gp_test_no_struct' );

		global $wp_rewrite;
		$before = $wp_rewrite->extra_rules_top;

		Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'add_rewrite_rule_for_post_type',
			array( 'gp_test_no_struct' )
		);

		$this->assertSame(
			$before,
			$wp_rewrite->extra_rules_top,
			'No rewrite rule should be added for a post type without a registered permastruct.'
		);

		unregister_post_type( 'gp_test_no_struct' );
	}

	/**
	 * Coverage for a supporting post type registered with `query_var => false`.
	 * WordPress still gives such a post type pretty permalinks, routed through
	 * the `post_type` and `name` pair rather than a named query var, and
	 * `Context::permalink()` still publishes occurrence URLs for it. The
	 * occurrence rule must route those URLs through the same fallback pair, or
	 * the plugin advertises URLs with no rule that can resolve them.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 * @covers ::parse_request
	 * @covers ::resolve_post_id_from_query_vars
	 *
	 * @return void
	 */
	public function test_occurrence_url_routes_for_a_post_type_without_query_var(): void {
		global $wp_rewrite;

		register_post_type(
			'gp_no_qv_event',
			array(
				'public'    => true,
				'supports'  => array( 'title', 'editor', 'gatherpress-event-date' ),
				'rewrite'   => array(
					'slug'       => 'gp-no-qv',
					'with_front' => false,
				),
				'query_var' => false,
			)
		);
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'gp_no_qv_event',
				'post_name'   => 'no-query-var-event',
				'post_status' => 'publish',
			)
		);

		$anchor_start  = $this->project_relative_daily_series( $post_id, 5, 7, 3 );
		$recurrence_id = Occurrences::recurrence_id( $anchor_start );
		$url           = Rewrite::get_occurrence_url( $post_id, $recurrence_id );

		$this->assertStringContainsString(
			'/gp-no-qv/no-query-var-event/',
			$url,
			'Fixture setup: the occurrence URL should be composed under the post type\'s rewrite slug.'
		);

		$this->go_to( $url );

		$this->assertFalse( is_404(), 'A query-var-disabled post type\'s occurrence URL must not 404.' );
		$this->assertSame(
			$post_id,
			get_queried_object_id(),
			'A query-var-disabled post type\'s occurrence URL should resolve to its own post.'
		);
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'A query-var-disabled post type\'s occurrence URL should round-trip to its own recurrence ID.'
		);

		unregister_post_type( 'gp_no_qv_event' );
	}

	/**
	 * Coverage for the hierarchical half of the `query_var => false` fallback
	 * pair. WordPress routes a hierarchical post type without a query var
	 * through `post_type` and `pagename` rather than `name`, so the fallback
	 * target and the request resolution must both pick the key the post
	 * type's own permastruct uses.
	 *
	 * The permalink structure is `/%year%/%monthnum%/%postname%/` rather than
	 * this class's default `/%postname%/`, because a structure whose first
	 * tag is postname-like turns on core's verbose page rules, and that mode
	 * skips every rewrite rule targeting `pagename=$matches[]` unless a real
	 * page exists at the captured path. Under such a structure WordPress
	 * 404s this post type's own bare permalinks too, with no occurrence
	 * involved, so the occurrence rule is exercised under a structure where
	 * core itself can route the post type.
	 *
	 * @covers ::add_rewrite_rule_for_post_type
	 * @covers ::parse_request
	 * @covers ::resolve_post_id_from_query_vars
	 *
	 * @return void
	 */
	public function test_occurrence_url_routes_for_a_hierarchical_post_type_without_query_var(): void {
		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );
		unregister_post_type( Event::POST_TYPE );
		Event_Setup::get_instance()->register_post_type();

		register_post_type(
			'gp_no_qv_hier',
			array(
				'public'       => true,
				'hierarchical' => true,
				'supports'     => array( 'title', 'editor', 'page-attributes', 'gatherpress-event-date' ),
				'rewrite'      => array(
					'slug'       => 'gp-no-qv-hier',
					'with_front' => false,
				),
				'query_var'    => false,
			)
		);
		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();

		$parent_id = $this->factory->post->create(
			array(
				'post_type'   => 'gp_no_qv_hier',
				'post_name'   => 'quiet-parent',
				'post_status' => 'publish',
			)
		);
		$child_id  = $this->factory->post->create(
			array(
				'post_type'   => 'gp_no_qv_hier',
				'post_name'   => 'quiet-child',
				'post_parent' => $parent_id,
				'post_status' => 'publish',
			)
		);

		$anchor_start  = $this->project_relative_daily_series( $child_id, 5, 7, 3 );
		$recurrence_id = Occurrences::recurrence_id( $anchor_start );
		$url           = Rewrite::get_occurrence_url( $child_id, $recurrence_id );

		$this->assertStringContainsString(
			'/gp-no-qv-hier/quiet-parent/quiet-child/',
			$url,
			'Fixture setup: the occurrence URL should be composed under the child post\'s hierarchical path.'
		);

		$this->go_to( $url );

		$this->assertFalse(
			is_404(),
			'A hierarchical query-var-disabled post type\'s occurrence URL must not 404.'
		);
		$this->assertSame(
			$child_id,
			get_queried_object_id(),
			'A hierarchical query-var-disabled occurrence URL should resolve to the child post.'
		);
		$this->assertSame(
			$recurrence_id,
			get_query_var( Context::QUERY_VAR ),
			'A hierarchical query-var-disabled occurrence URL should round-trip to its own recurrence ID.'
		);

		unregister_post_type( 'gp_no_qv_hier' );
	}

	/**
	 * Coverage for `resolve_post_id_from_query_vars` when a post type
	 * declares `gatherpress-event-date` support without ever being
	 * registered. `get_post_type_object()` returns null and the loop must
	 * skip past it rather than fatal.
	 *
	 * @covers ::resolve_post_id_from_query_vars
	 *
	 * @return void
	 */
	public function test_resolve_post_id_from_query_vars_skips_unregistered_supporting_post_type(): void {
		add_post_type_support( 'gp_ghost_event', 'gatherpress-event-date' );

		$result = Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'resolve_post_id_from_query_vars',
			array( array() )
		);

		remove_post_type_support( 'gp_ghost_event', 'gatherpress-event-date' );

		$this->assertNull(
			$result,
			'An unregistered post type declaring the support must be skipped, not fatal.'
		);
	}

	/**
	 * Coverage for `resolve_post_id_from_query_vars` on the two empty-path
	 * arms of the query-var-disabled fallback: query vars naming a different
	 * post type, and query vars naming the right post type with no name to
	 * look up. Both must skip the lookup rather than resolve or fatal.
	 *
	 * @covers ::resolve_post_id_from_query_vars
	 *
	 * @return void
	 */
	public function test_resolve_post_id_from_query_vars_skips_incomplete_fallback_pairs(): void {
		register_post_type(
			'gp_no_qv_event',
			array(
				'public'    => true,
				'supports'  => array( 'title', 'gatherpress-event-date' ),
				'rewrite'   => array( 'slug' => 'gp-no-qv' ),
				'query_var' => false,
			)
		);

		$this->assertNull(
			Utility::invoke_hidden_method(
				Rewrite::get_instance(),
				'resolve_post_id_from_query_vars',
				array(
					array(
						'post_type' => 'post',
						'name'      => 'unrelated',
					),
				)
			),
			'Query vars naming a different post type must not resolve through the fallback pair.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method(
				Rewrite::get_instance(),
				'resolve_post_id_from_query_vars',
				array( array( 'post_type' => 'gp_no_qv_event' ) )
			),
			'A matching post type with no name query var must be skipped, not looked up.'
		);

		unregister_post_type( 'gp_no_qv_event' );
	}

	/**
	 * Coverage for `resolve_post_id_from_query_vars` when the query vars
	 * carry a value for the post type's query var, but no post exists at
	 * that path.
	 *
	 * @covers ::resolve_post_id_from_query_vars
	 *
	 * @return void
	 */
	public function test_resolve_post_id_from_query_vars_returns_null_when_no_post_matches(): void {
		$result = Utility::invoke_hidden_method(
			Rewrite::get_instance(),
			'resolve_post_id_from_query_vars',
			array( array( Event::POST_TYPE => 'no-such-event-slug' ) )
		);

		$this->assertNull(
			$result,
			'resolve_post_id_from_query_vars() should return null when no post matches the path.'
		);
	}

	/**
	 * Coverage for `parse_request` when the occurrence query var is present
	 * but set to an empty string, which is the falsy-but-isset short circuit
	 * of the `||` guard. Not reachable through the registered rewrite rule
	 * (whose capture group cannot match an empty string), so it is driven
	 * directly against a real `WP` object per the class's own contract.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_parse_request_treats_empty_string_occurrence_var_as_bare_series(): void {
		list( $post_id, $anchor_start ) = $this->create_relative_daily_series( 5, 7, 3 );
		$expected_recurrence_id         = Occurrences::recurrence_id( $anchor_start );

		$post           = get_post( $post_id );
		$wp             = new WP();
		$wp->query_vars = array(
			Event::POST_TYPE   => $post->post_name,
			Context::QUERY_VAR => '',
		);

		Rewrite::get_instance()->parse_request( $wp );

		$this->assertSame(
			$expected_recurrence_id,
			$wp->query_vars[ Context::QUERY_VAR ],
			'An empty-string occurrence query var should be treated as a bare series URL.'
		);
	}

	/**
	 * Coverage for `parse_request` when the occurrence segment is present
	 * but nothing in the query vars identifies a series post at all.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_parse_request_bails_when_no_post_resolves(): void {
		// The site must genuinely have a recurring event, or the
		// no-recurring-events guard on
		// `parse_request()` returns before the post-resolution branch this test
		// exists to reach. The assertion below would then hold for a reason
		// that has nothing to do with post resolution.
		$this->create_relative_daily_series( 5, 7, 3 );

		$wp             = new WP();
		$wp->query_vars = array( Context::QUERY_VAR => '20260901T180000' );

		Rewrite::get_instance()->parse_request( $wp );

		$this->assertArrayNotHasKey(
			'error',
			$wp->query_vars,
			'parse_request() should not set a 404 error when no post resolves at all.'
		);
	}

	/**
	 * Coverage for `maybe_resolve_bare_series` when the request is not for
	 * a series post at all (no occurrence segment, and nothing in the query
	 * vars identifies an event-supporting post type). This is the ordinary
	 * shape of every non-event request on the site, driven through a real
	 * request to the home page.
	 *
	 * @covers ::parse_request
	 * @covers ::maybe_resolve_bare_series
	 *
	 * @return void
	 */
	public function test_bare_series_resolution_is_a_no_op_for_non_event_requests(): void {
		// The site DOES have a recurring event elsewhere, so
		// maybe_resolve_bare_series() clears Query::site_has_recurring_events()'s
		// guard and reaches resolve_post_id_from_query_vars(), which must
		// still find nothing to resolve for a request that identifies no
		// post at all.
		$this->create_relative_daily_series( 5, 7, 3 );

		$this->go_to( home_url( '/' ) );

		$this->assertFalse( is_404(), 'The home page must not 404.' );
		$this->assertSame(
			'',
			(string) get_query_var( Context::QUERY_VAR ),
			'A request unrelated to any event post type must not set the occurrence query var.'
		);
	}

	/**
	 * A request for an `.ics` calendar endpoint is left alone by the bare-series
	 * resolution, and every other request is not.
	 *
	 * The distinction: a series' iCal export is one component
	 * carrying the whole rule, so narrowing it to the next upcoming occurrence
	 * would put a single date back in a subscriber's calendar. The Google and
	 * Yahoo redirects are single-datetime by nature and keep the occurrence.
	 *
	 * Driven by direct invoke as well as through a real request, because
	 * xdebug does not trace a protected helper reached through a same-class
	 * delegation from `parse_request()`.
	 *
	 * @covers ::is_ics_request
	 * @covers ::maybe_resolve_bare_series
	 *
	 * @return void
	 */
	public function test_ics_requests_are_exempt_from_bare_series_resolution(): void {
		list( $post_id ) = $this->create_relative_daily_series( 5, 7, 3 );

		$instance = Rewrite::get_instance();
		$slug     = get_post( $post_id )->post_name;
		$cases    = array(
			'ical'            => true,
			'outlook'         => true,
			'google-calendar' => false,
			'yahoo-calendar'  => false,
			''                => false,
		);

		foreach ( $cases as $calendar_slug => $is_ics ) {
			$wp                                     = new WP();
			$wp->query_vars                         = array( 'gatherpress_event' => $slug );
			$wp->query_vars['gatherpress_calendar'] = $calendar_slug;

			$this->assertSame(
				$is_ics,
				Utility::invoke_hidden_method( $instance, 'is_ics_request', array( $wp ) ),
				sprintf( 'The "%s" endpoint slug was classified wrongly.', $calendar_slug )
			);

			Utility::invoke_hidden_method( $instance, 'maybe_resolve_bare_series', array( $wp ) );

			$this->assertSame(
				$is_ics,
				! isset( $wp->query_vars[ Context::QUERY_VAR ] ),
				sprintf(
					'The "%s" endpoint slug resolved an occurrence when it should not have, or the reverse.',
					$calendar_slug
				)
			);
		}
	}
}
