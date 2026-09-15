<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Rest_Api.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Rest_Api;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Test_Rest_Api.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Rest_Api
 */
class Test_Rest_Api extends Base {

	/**
	 * A five-occurrence daily rule, cheap to project and easy to reason about.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const DAILY_RULE = array(
		'frequency' => 'daily',
		'interval'  => 1,
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * Start every test from an empty occurrence table with the route
	 * registered, independent of execution order.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		Rest_Api::get_instance()->register_endpoints();
	}

	/**
	 * Clear any nonce simulation state a test may have left behind.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wp_rest_auth_cookie;

		unset( $_SERVER['HTTP_X_WP_NONCE'] );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP core's own global, not ours to prefix.
		$wp_rest_auth_cookie = false;

		parent::tearDown();
	}

	/**
	 * Build "now" in UTC so fixtures are never a date bomb.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable Current time in UTC.
	 */
	protected function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Create and project a recurring event anchored relative to "now".
	 *
	 * @since 0.36.0
	 *
	 * @param array             $rule     Recurrence rule values.
	 * @param DateTimeImmutable $start    Anchor start, in `$timezone`.
	 * @param DateTimeImmutable $end      Anchor end, in `$timezone`.
	 * @param string            $timezone Named tz-database identifier for the series.
	 *
	 * @return int The projected post ID.
	 */
	protected function create_relative_recurring_event(
		array $rule,
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		string $timezone = 'UTC'
	): int {
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
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $end->format( 'Y-m-d H:i:s' ),
					'timezone'      => $timezone,
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );
		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( $rule ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return (int) $post_id;
	}

	/**
	 * Create a relative recurring event and return its first recurrence ID.
	 *
	 * @since 0.36.0
	 *
	 * @param int $offset_days Days to add to "+1 day" for the anchor start, so
	 *                         two fixtures in the same test do not collide on
	 *                         the same recurrence ID.
	 *
	 * @return array{0: int, 1: string} The post ID and its first recurrence ID.
	 */
	protected function create_event_with_occurrence( int $offset_days = 0 ): array {
		$start = $this->now()->modify( sprintf( '+%d days', 1 + $offset_days ) );

		$post_id = $this->create_relative_recurring_event(
			self::DAILY_RULE,
			$start,
			$start->modify( '+1 hour' )
		);

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		return array( $post_id, $rows[0]['recurrence_id'] );
	}

	/**
	 * Build a POST request against the occurrence-status route.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier.
	 * @param string $status        Target status.
	 *
	 * @return WP_REST_Request The built request.
	 */
	protected function build_request( int $post_id, string $recurrence_id, string $status ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/occurrence-status' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'recurrence_id', $recurrence_id );
		$request->set_param( 'status', $status );

		return $request;
	}

	/**
	 * Dispatch a request through the real REST server.
	 *
	 * Runs authentication, route matching, `permission_callback`, and args
	 * validation exactly as a live HTTP request would, rather than calling
	 * the route callback directly. Mirrors what
	 * `WP_REST_Server::serve_request()` does internally, without echoing
	 * output or sending headers.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_REST_Request $request Request to dispatch.
	 *
	 * @return WP_REST_Response The response, including error responses.
	 */
	protected function dispatch( WP_REST_Request $request ): WP_REST_Response {
		$server = rest_get_server();
		$result = $server->check_authentication();

		if ( ! is_wp_error( $result ) ) {
			$result = $server->dispatch( $request );
		}

		if ( is_wp_error( $result ) ) {
			$result = rest_convert_error_to_response( $result );
		}

		return $result;
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
		$instance = Rest_Api::get_instance();

		$hooks = array(
			array(
				'type'     => 'action',
				'name'     => 'rest_api_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_endpoints' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for `register_endpoints`.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_register_endpoints(): void {
		$rest_server    = rest_get_server();
		$event_route    = sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE );
		$all_namespaces = Utility::get_hidden_property( $rest_server, 'namespaces' );
		$namespace      = $all_namespaces[ $event_route ];

		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/occurrences', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert occurrences endpoint is registered'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/occurrence-status', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert occurrence-status endpoint is registered'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/split-series', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert split-series endpoint is registered'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/event/recurrence-impact', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert recurrence-impact endpoint is registered'
		);
	}

	/**
	 * Coverage for `get_occurrence_routes`, `occurrences_route` and
	 * `occurrence_status_route`.
	 *
	 * @covers ::get_occurrence_routes
	 * @covers ::occurrences_route
	 * @covers ::occurrence_status_route
	 *
	 * @return void
	 */
	public function test_get_occurrence_routes(): void {
		$instance = Rest_Api::get_instance();
		$routes   = Utility::invoke_hidden_method( $instance, 'get_occurrence_routes' );

		$this->assertSame( 'occurrences', $routes[0]['route'] );
		$this->assertSame( WP_REST_Server::READABLE, $routes[0]['args']['methods'] );
		$this->assertSame(
			array( $instance, 'get_occurrences' ),
			$routes[0]['args']['callback']
		);
		$this->assertSame(
			array( $instance, 'has_edit_permission' ),
			$routes[0]['args']['permission_callback']
		);

		$this->assertSame( 'occurrence-status', $routes[1]['route'] );
		$this->assertSame( WP_REST_Server::EDITABLE, $routes[1]['args']['methods'] );
		$this->assertSame(
			array( $instance, 'update_occurrence_status' ),
			$routes[1]['args']['callback']
		);
		$this->assertSame(
			array( $instance, 'has_edit_permission' ),
			$routes[1]['args']['permission_callback']
		);
		$this->assertSame(
			array( Occurrences::STATUS_SCHEDULED, Occurrences::STATUS_CANCELED ),
			$routes[1]['args']['args']['status']['enum']
		);

		$this->assertSame( 'split-series', $routes[2]['route'] );
		$this->assertSame( WP_REST_Server::EDITABLE, $routes[2]['args']['methods'] );
		$this->assertSame(
			array( $instance, 'split_series' ),
			$routes[2]['args']['callback']
		);
		$this->assertSame(
			array( $instance, 'has_edit_permission' ),
			$routes[2]['args']['permission_callback']
		);

		$this->assertSame( 'recurrence-impact', $routes[3]['route'] );
		$this->assertSame( WP_REST_Server::READABLE, $routes[3]['args']['methods'] );
		$this->assertSame(
			array( $instance, 'get_recurrence_impact' ),
			$routes[3]['args']['callback']
		);
		$this->assertSame(
			array( $instance, 'has_edit_permission' ),
			$routes[3]['args']['permission_callback']
		);
	}

	/**
	 * The split route partitions the series through the real REST server.
	 *
	 * Driven through `WP_REST_Server::dispatch()` rather than by calling the
	 * callback, so authentication, the `edit_post` permission callback and the
	 * `recurrence_id` validator all run the way a browser would reach them.
	 *
	 * @covers ::split_series
	 * @covers ::split_series_route
	 *
	 * @return void
	 */
	public function test_split_series_route_partitions_the_series(): void {
		list( $post_id ) = $this->create_event_with_occurrence();

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/split-series' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'recurrence_id', $rows[2]['recurrence_id'] );

		$response = $this->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Failed to assert the split route answered 200.' );
		$this->assertTrue( $data['split'], 'Failed to assert the route reported a split.' );
		$this->assertSame( 3, $data['moved'], 'Failed to assert three of five occurrences moved.' );
		$this->assertSame(
			array( $rows[0]['recurrence_id'], $rows[1]['recurrence_id'] ),
			array_column(
				Occurrences::get_instance()->select_for_series( array( $post_id ) ),
				'recurrence_id'
			),
			'Failed to assert only the first two occurrences remain on the original post.'
		);
		$this->assertSame(
			array( $rows[2]['recurrence_id'], $rows[3]['recurrence_id'], $rows[4]['recurrence_id'] ),
			array_column(
				Occurrences::get_instance()->select_for_series( array( (int) $data['forward_post_id'] ) ),
				'recurrence_id'
			),
			'Failed to assert the last three occurrences moved to the forward post.'
		);
	}

	/**
	 * A recurrence ID belonging to no occurrence of this series is a 404.
	 *
	 * @covers ::split_series
	 *
	 * @return void
	 */
	public function test_split_series_route_reports_an_unknown_occurrence_as_404(): void {
		list( $post_id ) = $this->create_event_with_occurrence();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/split-series' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'recurrence_id', '20991231T235959' );

		$this->assertSame(
			404,
			$this->dispatch( $request )->get_status(),
			'Failed to assert an unknown occurrence is refused rather than splitting something arbitrary.'
		);
	}

	/**
	 * A subscriber cannot split someone else's series.
	 *
	 * @covers ::split_series
	 * @covers ::has_edit_permission
	 *
	 * @return void
	 */
	public function test_split_series_route_refuses_a_user_without_edit_post(): void {
		list( $post_id ) = $this->create_event_with_occurrence();

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/split-series' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'recurrence_id', $rows[2]['recurrence_id'] );

		$response = $this->dispatch( $request );

		$this->assertSame(
			403,
			$response->get_status(),
			'Failed to assert a user without edit_post is refused.'
		);
		$this->assertCount(
			5,
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert the refused request changed nothing.'
		);
	}

	/**
	 * The split route touches nothing on a site with no recurring events.
	 *
	 * @covers ::split_series
	 *
	 * @return void
	 */
	public function test_split_series_route_short_circuits_without_recurring_events(): void {
		global $wpdb;

		list( $post_id ) = $this->create_event_with_occurrence();

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		update_option( Query::HAS_RECURRING_OPTION, '0', true );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$seen  = 0;

		$counter = static function ( string $query ) use ( &$seen, $table ): string {
			if ( str_contains( $query, $table ) ) {
				++$seen;
			}

			return $query;
		};

		add_filter( 'query', $counter );

		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/event/split-series' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'recurrence_id', $rows[2]['recurrence_id'] );

		$response = $this->dispatch( $request );

		remove_filter( 'query', $counter );

		$this->assertSame( 400, $response->get_status(), 'Failed to assert the split route refuses a flag-off site.' );
		$this->assertSame(
			0,
			$seen,
			'A site with no recurring events must issue zero occurrence-table queries from the split route.'
		);
	}

	/**
	 * The impact route reports the RSVP count a rule change would strand.
	 *
	 * @covers ::get_recurrence_impact
	 * @covers ::recurrence_impact_route
	 *
	 * @return void
	 */
	public function test_recurrence_impact_route_reports_stranded_occurrences(): void {
		list( $post_id ) = $this->create_event_with_occurrence();

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/recurrence-impact' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param(
			'recurrence',
			(string) wp_json_encode( array_merge( self::DAILY_RULE, array( 'count' => 3 ) ) )
		);

		$data = $this->dispatch( $request )->get_data();

		$this->assertSame(
			array( $rows[3]['recurrence_id'], $rows[4]['recurrence_id'] ),
			$data['removed'],
			'Failed to assert the route names the two dates a three-occurrence rule stops producing.'
		);
		$this->assertSame(
			0,
			$data['rsvp_count'],
			'Failed to assert no RSVPs are reported when none of the stranded dates carries one.'
		);
	}

	/**
	 * A rule the editor cannot yet persist reports no impact rather than an error.
	 *
	 * @covers ::get_recurrence_impact
	 *
	 * @return void
	 */
	public function test_recurrence_impact_route_treats_an_invalid_rule_as_no_impact(): void {
		list( $post_id ) = $this->create_event_with_occurrence();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/recurrence-impact' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'recurrence', 'not json' );

		$response = $this->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'removed'    => array(),
				'rsvp_count' => 0,
			),
			$response->get_data(),
			'Failed to assert a mid-edit rule reports no impact rather than surfacing an error.'
		);
	}

	/**
	 * The impact route queries nothing on a site with no recurring events.
	 *
	 * @covers ::get_recurrence_impact
	 *
	 * @return void
	 */
	public function test_recurrence_impact_route_short_circuits_without_recurring_events(): void {
		global $wpdb;

		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$seen  = 0;

		$counter = static function ( string $query ) use ( &$seen, $table ): string {
			if ( str_contains( $query, $table ) ) {
				++$seen;
			}

			return $query;
		};

		add_filter( 'query', $counter );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/recurrence-impact' );
		$request->set_param( 'post_id', $post_id );
		$request->set_param( 'recurrence', (string) wp_json_encode( self::DAILY_RULE ) );

		$response = $this->dispatch( $request );

		remove_filter( 'query', $counter );

		$this->assertSame(
			array(
				'removed'    => array(),
				'rsvp_count' => 0,
			),
			$response->get_data(),
			'Failed to assert the impact route reports nothing on a flag-off site.'
		);
		$this->assertSame(
			0,
			$seen,
			'A site with no recurring events must issue zero occurrence-table queries from the impact route.'
		);
	}

	/**
	 * The `recurrence_id` `validate_callback` accepts the `Ymd\THis` shape and
	 * rejects anything else.
	 *
	 * @covers ::occurrence_status_route
	 *
	 * @return void
	 */
	public function test_recurrence_id_validate_callback_accepts_and_rejects(): void {
		$instance = Rest_Api::get_instance();
		$route    = Utility::invoke_hidden_method( $instance, 'occurrence_status_route' );
		$callback = $route['args']['args']['recurrence_id']['validate_callback'];

		$this->assertTrue(
			call_user_func( $callback, '20260903T180000' ),
			'A well-formed recurrence ID must validate.'
		);
		$this->assertFalse(
			call_user_func( $callback, 'not-a-recurrence-id' ),
			'A malformed recurrence ID must not validate.'
		);
	}

	/**
	 * The `status` arg's `enum` schema is enforced by WordPress's own
	 * `rest_parse_request_arg` default (triggered by declaring `type` with no
	 * explicit `sanitize_callback`), so the route needs no redundant
	 * `validate_callback` of its own. This is driven through the real dispatch
	 * path, since that is where the schema actually gets applied
	 * (`sanitize_params()`, not `has_valid_params()`).
	 *
	 * @covers ::update_occurrence_status
	 *
	 * @return void
	 */
	public function test_status_enum_rejects_an_unknown_value(): void {
		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$response = $this->dispatch( $this->build_request( $post_id, $recurrence_id, 'deleted' ) );

		$this->assertSame( 400, $response->get_status() );

		$row = Occurrences::get_instance()->get( $post_id, $recurrence_id );
		$this->assertSame(
			Occurrences::STATUS_SCHEDULED,
			$row['status'],
			'An unknown status value must not reach set_status() at all.'
		);
	}

	/**
	 * Coverage for both branches of `has_edit_permission`.
	 *
	 * @covers ::has_edit_permission
	 *
	 * @return void
	 */
	public function test_has_edit_permission_both_branches(): void {
		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();
		$instance                        = Rest_Api::get_instance();

		$editor = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$request = $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED );
		$this->assertTrue(
			$instance->has_edit_permission( $request ),
			'An editor may edit any post, including this one.'
		);

		wp_set_current_user( 0 );
		$this->assertFalse(
			$instance->has_edit_permission( $request ),
			'A logged-out visitor may not change occurrence status.'
		);
	}

	/**
	 * A subscriber, who cannot edit the series post, gets a 403. This is driven
	 * through the real REST server, not a direct callback call.
	 *
	 * @covers ::has_edit_permission
	 *
	 * @return void
	 */
	public function test_cancel_route_requires_edit_post_capability(): void {
		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$response = $this->dispatch( $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED ) );

		$this->assertSame( 403, $response->get_status() );

		// The occurrence must remain untouched.
		$row = Occurrences::get_instance()->get( $post_id, $recurrence_id );
		$this->assertSame( Occurrences::STATUS_SCHEDULED, $row['status'] );
	}

	/**
	 * A bad `X-WP-Nonce` on an otherwise-authorized cookie session is
	 * rejected by WordPress's own cookie-auth nonce check before the route's
	 * own permission callback, and before `update_occurrence_status()` itself,
	 * ever runs. This is not a property of our route: `rest_cookie_check_errors()`
	 * gates every REST request the same way, so this test would pass even
	 * against a completely unregistered route. It stays because the nonce path
	 * is worth exercising end to end and it does drive real dispatch
	 * machinery, but it proves
	 * the inherited protection holds rather than something specific to this
	 * class, so it makes no `@covers` claim on our own callback.
	 *
	 * @return void
	 */
	public function test_cookie_auth_rejects_a_bad_nonce_before_our_route_runs(): void {
		global $wp_rest_auth_cookie;

		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$editor = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		// Simulate a cookie-authenticated request carrying an invalid nonce,
		// matching how `rest_cookie_check_errors()` gates every route.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP core's own global, not ours to prefix.
		$wp_rest_auth_cookie        = true;
		$_SERVER['HTTP_X_WP_NONCE'] = 'not-a-real-nonce';

		$response = $this->dispatch( $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rest_cookie_invalid_nonce', $response->get_data()['code'] );
	}

	/**
	 * A `recurrence_id` that belongs to a different post's series must not
	 * authorize a status change, even though the caller can edit the post ID
	 * they submitted. This is the authorization hole the composite-key scope
	 * on `Occurrences::set_status()` closes.
	 *
	 * @covers ::update_occurrence_status
	 *
	 * @return void
	 */
	public function test_cancel_route_rejects_a_recurrence_id_belonging_to_another_post(): void {
		list( $post_a, $recurrence_a ) = $this->create_event_with_occurrence();
		// Offset by 10 days so post B's five daily occurrences (days 11-15)
		// never overlap post A's (days 1-5). An overlapping date would
		// coincidentally also exist as a row for post A, defeating the point
		// of this test.
		list( $post_b, $recurrence_b ) = $this->create_event_with_occurrence( 10 );

		$this->assertNotSame(
			$recurrence_a,
			$recurrence_b,
			'Fixture assumption: the two series must not share a recurrence ID.'
		);

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		// Post A's ID, but Post B's recurrence ID.
		$response = $this->dispatch( $this->build_request( $post_a, $recurrence_b, Occurrences::STATUS_CANCELED ) );

		$this->assertSame(
			404,
			$response->get_status(),
			'A recurrence_id belonging to another post must 404, not silently succeed.'
		);
		// Pin the specific rejection reason so this test cannot pass merely
		// because the route itself is unregistered (which also 404s, as
		// `rest_no_route`). It must be *our* callback reporting the
		// composite-key mismatch.
		$this->assertSame(
			'gatherpress_occurrence_not_found',
			$response->get_data()['code'],
			'The 404 must come from the composite-key check, not from the route being missing.'
		);

		$row_b = Occurrences::get_instance()->get( $post_b, $recurrence_b );
		$this->assertSame(
			Occurrences::STATUS_SCHEDULED,
			$row_b['status'],
			'Post B\'s occurrence must remain untouched by a request scoped to post A.'
		);
	}

	/**
	 * An unknown `recurrence_id` for an otherwise-valid post returns 404.
	 *
	 * @covers ::update_occurrence_status
	 *
	 * @return void
	 */
	public function test_cancel_route_returns_404_for_an_unknown_recurrence_id(): void {
		list( $post_id ) = $this->create_event_with_occurrence();

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$response = $this->dispatch(
			$this->build_request( $post_id, '20990101T000000', Occurrences::STATUS_CANCELED )
		);

		$this->assertSame( 404, $response->get_status() );
		// Pins the 404 to our own "no such row" error rather than the route
		// itself being unregistered, which also 404s as `rest_no_route`.
		$this->assertSame( 'gatherpress_occurrence_not_found', $response->get_data()['code'] );
	}

	/**
	 * Canceling sets the status column, and the front-end upcoming list drops
	 * it. That half is driven through a real `WP_Query`, not just a column read.
	 *
	 * @covers ::update_occurrence_status
	 *
	 * @return void
	 */
	public function test_cancel_sets_status_and_front_end_list_drops_it(): void {
		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$response = $this->dispatch( $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Occurrences::STATUS_CANCELED, $response->get_data()['status'] );

		$entries = $this->run_upcoming_query_entries();

		$this->assertNotContains(
			$post_id . '|' . $recurrence_id,
			$entries,
			'A canceled occurrence must drop out of the upcoming list.'
		);
	}

	/**
	 * Un-canceling restores the occurrence to the front-end upcoming list.
	 *
	 * @covers ::update_occurrence_status
	 *
	 * @return void
	 */
	public function test_uncancel_restores_it_to_the_list(): void {
		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$this->dispatch( $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED ) );
		$this->assertNotContains( $post_id . '|' . $recurrence_id, $this->run_upcoming_query_entries() );

		$response = $this->dispatch( $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_SCHEDULED ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( Occurrences::STATUS_SCHEDULED, $response->get_data()['status'] );
		$this->assertContains(
			$post_id . '|' . $recurrence_id,
			$this->run_upcoming_query_entries(),
			'Un-canceling must restore the occurrence to the upcoming list.'
		);
	}

	/**
	 * A canceled occurrence's RSVPs are retained, never deleted.
	 *
	 * Scope note: `Rsvp_Occurrence` (the comment taxonomy that ties an RSVP
	 * to the specific occurrence it was made for) does not register its
	 * hooks yet, so `save()` here does not tag the comment with an
	 * occurrence identifier. It proves "canceling an occurrence does not
	 * delete the event's comments" rather than the fully occurrence-scoped
	 * claim in the method name. `Rsvp_Occurrence::assign()` is being wired up
	 * in another lane; tighten this test to assert the specific occurrence's
	 * RSVPs once that lands, rather than fixing it against a moving target
	 * now.
	 *
	 * @covers ::update_occurrence_status
	 *
	 * @return void
	 */
	public function test_canceled_occurrence_retains_its_rsvps(): void {
		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$event = new Event( $post_id );
		$event->rsvp->save( 'attendee@example.com', 'attending' );

		$before = get_comments(
			array(
				'post_id' => $post_id,
				'count'   => true,
			)
		);

		$this->assertGreaterThan( 0, $before, 'Fixture assumption: the RSVP comment must exist before cancellation.' );

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$response = $this->dispatch( $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED ) );

		$this->assertSame( 200, $response->get_status(), 'Fixture assumption: the cancellation itself must succeed.' );

		$after = get_comments(
			array(
				'post_id' => $post_id,
				'count'   => true,
			)
		);

		$this->assertSame( $before, $after, "Canceling an occurrence must not delete the event's RSVPs." );
	}

	/**
	 * Cancellation is occurrence state, never expressed by mutating
	 * the rule. Re-projecting the rule after a cancellation must not clear the
	 * cancellation, and re-saving the series is exactly what does that.
	 * Pinned from the REST side: the cancellation itself is set through the
	 * real route, not through `Occurrences::set_status()` directly.
	 *
	 * @covers ::update_occurrence_status
	 *
	 * @return void
	 */
	public function test_cancellation_survives_rule_regeneration(): void {
		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$response = $this->dispatch( $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED ) );
		$this->assertSame( 200, $response->get_status() );

		// Re-save the rule so it still produces this exact date.
		Occurrences::get_instance()->project( $post_id );

		$row = Occurrences::get_instance()->get( $post_id, $recurrence_id );

		$this->assertSame(
			Occurrences::STATUS_CANCELED,
			$row['status'],
			'Re-projecting the rule must not clear a cancellation.'
		);
	}

	/**
	 * The occurrences-list route requires `edit_post`, matching the write
	 * route. This is driven through the real server, not a direct callback call.
	 *
	 * @covers ::get_occurrences
	 *
	 * @return void
	 */
	public function test_occurrences_route_requires_edit_post_capability(): void {
		list( $post_id ) = $this->create_event_with_occurrence();

		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$response = $this->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * The occurrences-list route returns every upcoming row, canceled and
	 * scheduled alike, so the sidebar can offer a restore action.
	 *
	 * @covers ::get_occurrences
	 *
	 * @return void
	 */
	public function test_occurrences_route_lists_upcoming_rows_including_canceled(): void {
		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$this->dispatch( $this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED ) );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$response = $this->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 5, $data, 'All five projected occurrences must be listed, canceled included.' );

		$recurrence_ids = array_column( $data, 'recurrence_id' );
		$this->assertContains( $recurrence_id, $recurrence_ids );

		$index = array_search( $recurrence_id, $recurrence_ids, true );
		$this->assertSame( Occurrences::STATUS_CANCELED, $data[ $index ]['status'] );
	}

	/**
	 * A row vanishing between the status write and the response read is a 404.
	 *
	 * A concurrent rule save reprojecting a shortened rule deletes rows; if
	 * that lands between `set_status()` and the response's `get()`, the
	 * route would otherwise return HTTP success with a null body, and the
	 * client's `updated.status` read turns a vanished row into a misleading
	 * "Could not update" failure notice. The `query` filter below deletes
	 * the row at the last instant before the response read executes.
	 *
	 * @covers ::update_occurrence_status
	 *
	 * @return void
	 */
	public function test_cancel_route_returns_404_when_the_row_vanishes_before_the_response_read(): void {
		global $wpdb;

		list( $post_id, $recurrence_id ) = $this->create_event_with_occurrence();

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$done  = false;

		$racer = static function ( string $query ) use ( &$done, $table, $post_id, $recurrence_id, $wpdb ): string {
			if (
				! $done
				&& str_starts_with( $query, 'SELECT * FROM' )
				&& str_contains( $query, $table )
				&& str_contains( $query, $recurrence_id )
			) {
				// Fire once: the DELETE below re-enters this filter.
				$done = true;

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare( 'DELETE FROM %i WHERE series_post_id = %d', $table, $post_id )
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}

			return $query;
		};

		add_filter( 'query', $racer );

		$response = $this->dispatch(
			$this->build_request( $post_id, $recurrence_id, Occurrences::STATUS_CANCELED )
		);

		remove_filter( 'query', $racer );

		$this->assertTrue( $done, 'Fixture failure: the race must actually delete the row before the response read.' );
		$this->assertSame(
			404,
			$response->get_status(),
			'A row that vanished before the response read must surface as not-found, never as success with a null body.'
		);
		$this->assertNotNull( $response->get_data(), 'An error response carries an error body, not null.' );
	}

	/**
	 * An occurrence that has started but not finished is still listed.
	 *
	 * "Upcoming" is defined inclusively across the subsystem
	 * (`select_bounded_occurrence()` bounds on `datetime_end_gmt`, matching
	 * `Event\Query::get_datetime_comparison_column()`), and the in-progress
	 * occurrence is the one most urgently needing a cancel action: dropping
	 * it from this route leaves the organizer of a flooding venue with no
	 * button to press.
	 *
	 * @covers ::get_occurrences
	 *
	 * @return void
	 */
	public function test_occurrences_route_includes_an_in_progress_occurrence(): void {
		// The first occurrence started an hour ago and runs another hour.
		$start = $this->now()->modify( '-1 hour' );

		$post_id = $this->create_relative_recurring_event(
			self::DAILY_RULE,
			$start,
			$start->modify( '+2 hours' )
		);

		$rows     = Occurrences::get_instance()->select_for_series( array( $post_id ) );
		$first_id = $rows[0]['recurrence_id'];

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$response = $this->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains(
			$first_id,
			array_column( $data, 'recurrence_id' ),
			'The in-progress occurrence must be listed so it can still be canceled.'
		);
		$this->assertCount( 5, $data, 'All five occurrences are unfinished, so all five must be listed.' );
	}

	/**
	 * A just-ended occurrence is not listed.
	 *
	 * The inclusive bound is the occurrence's end, not its start: once an
	 * occurrence has finished there is nothing left to cancel, and listing
	 * it would only pad the panel with dead rows.
	 *
	 * @covers ::get_occurrences
	 *
	 * @return void
	 */
	public function test_occurrences_route_drops_a_just_ended_occurrence(): void {
		// The first occurrence ran from three hours ago to two hours ago.
		$start = $this->now()->modify( '-3 hours' );

		$post_id = $this->create_relative_recurring_event(
			self::DAILY_RULE,
			$start,
			$start->modify( '+1 hour' )
		);

		$rows     = Occurrences::get_instance()->select_for_series( array( $post_id ) );
		$first_id = $rows[0]['recurrence_id'];

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$response = $this->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotContains(
			$first_id,
			array_column( $data, 'recurrence_id' ),
			'A finished occurrence has nothing left to cancel and must not be listed.'
		);
		$this->assertCount( 4, $data, 'Only the four unfinished occurrences remain listable.' );
	}

	/**
	 * Create a long daily series anchored an hour from now.
	 *
	 * Long enough that the route's default bound is smaller than the series,
	 * so a bounded read and an unbounded one give different answers and only
	 * the bounded one can pass.
	 *
	 * @since 0.36.0
	 *
	 * @param int $count Occurrences to project.
	 *
	 * @return int The projected post ID.
	 */
	protected function create_long_series( int $count ): int {
		$start = $this->now()->modify( '+1 hour' );

		return $this->create_relative_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => $count,
			),
			$start,
			$start->modify( '+1 hour' )
		);
	}

	/**
	 * The list route declares a bounded, schema'd `per_page`.
	 *
	 * The bound is only real if the schema states it: `minimum` is what stops
	 * a caller asking for nothing, and `maximum` is what stops one asking the
	 * unbounded read back. Asserted on the route definition rather than on a
	 * response, because that is the artifact `register_rest_route()` consumes
	 * and the one a client discovers.
	 *
	 * @covers ::occurrences_route
	 *
	 * @return void
	 */
	public function test_occurrences_route_declares_a_bounded_per_page_argument(): void {
		$route = Utility::invoke_hidden_method( Rest_Api::get_instance(), 'occurrences_route' );
		$arg   = $route['args']['args']['per_page'];

		$this->assertSame( 'integer', $arg['type'], 'The bound must be typed so the schema can enforce it.' );
		$this->assertSame(
			Rest_Api::DEFAULT_PER_PAGE,
			$arg['default'],
			'A caller naming no bound must still get one.'
		);
		$this->assertSame( 1, $arg['minimum'], 'A bound of zero would return nothing at all.' );
		$this->assertSame(
			Rest_Api::MAXIMUM_PER_PAGE,
			$arg['maximum'],
			'Without a maximum the caller asks the unbounded read straight back.'
		);
		$this->assertFalse( $arg['required'], 'The bound is a default, not something every client must send.' );
	}

	/**
	 * The route returns at most `per_page` rows, defaulting when none is sent.
	 *
	 * `Rule::MAX_COUNT` is 730, so an unbounded route puts a legitimately
	 * authored daily series through PHP and into a JSON response in full on
	 * every editor open. The fixture is longer than the default, so the
	 * default has to be doing the work rather than the series being short.
	 *
	 * @covers ::get_occurrences
	 * @covers ::occurrences_route
	 *
	 * @return void
	 */
	public function test_occurrences_route_bounds_the_rows_it_returns(): void {
		$post_id = $this->create_long_series( Rest_Api::DEFAULT_PER_PAGE + 10 );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$this->assertCount(
			Rest_Api::DEFAULT_PER_PAGE,
			$this->dispatch( $request )->get_data(),
			'A request naming no bound must be bounded by the schema default.'
		);

		$request->set_param( 'per_page', 3 );

		$bounded = $this->dispatch( $request )->get_data();

		$this->assertCount( 3, $bounded, 'A request naming a bound must be held to it.' );
		$this->assertSame(
			array_slice(
				array_column(
					Occurrences::get_instance()->select_for_series( array( $post_id ) ),
					'recurrence_id'
				),
				0,
				3
			),
			array_column( $bounded, 'recurrence_id' ),
			'The bounded page must be the earliest occurrences, not an arbitrary three of them.'
		);
	}

	/**
	 * A `per_page` outside the declared range is refused by the schema.
	 *
	 * Both ends, because they fail for different reasons: zero would return an
	 * empty list the panel reads as "nothing to show", and an oversized value
	 * is the unbounded read the argument exists to prevent.
	 *
	 * @covers ::occurrences_route
	 *
	 * @return void
	 */
	public function test_occurrences_route_refuses_an_out_of_range_per_page(): void {
		list( $post_id ) = $this->create_event_with_occurrence();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		foreach ( array( 0, Rest_Api::MAXIMUM_PER_PAGE + 1 ) as $per_page ) {
			$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
			$request->set_param( 'post_id', $post_id );
			$request->set_param( 'per_page', $per_page );

			$this->assertSame(
				400,
				$this->dispatch( $request )->get_status(),
				sprintf( 'A per_page of %d is outside the declared range and must be refused.', $per_page )
			);
		}
	}

	/**
	 * A direct call with no `per_page` at all is still bounded.
	 *
	 * `get_occurrences()` is public, and the schema default only applies to a
	 * dispatched request. A caller reaching the callback directly must not get
	 * the unbounded read back through the side door.
	 *
	 * @covers ::get_occurrences
	 *
	 * @return void
	 */
	public function test_get_occurrences_bounds_a_direct_call_carrying_no_per_page(): void {
		$post_id = $this->create_long_series( Rest_Api::DEFAULT_PER_PAGE + 10 );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$this->assertNull(
			$request->get_param( 'per_page' ),
			'Fixture failure: the request must carry no bound for this test to mean anything.'
		);
		$this->assertCount(
			Rest_Api::DEFAULT_PER_PAGE,
			Rest_Api::get_instance()->get_occurrences( $request )->get_data(),
			'A direct call must fall back to the same default the schema declares.'
		);
	}

	/**
	 * A site that has never authored a recurring event pays no
	 * occurrence-table query when the sidebar's occurrences route is hit.
	 * Unlike the write route, this one runs from every ordinary event's
	 * editor screen, so the guard belongs on the read path specifically.
	 * Driven through the real server with the query count captured from the
	 * actual SQL WordPress executes. Calling `get_occurrences()` directly
	 * would prove the method's body runs, not that the real entry point
	 * short-circuits before it reaches the database.
	 *
	 * @covers ::get_occurrences
	 *
	 * @return void
	 */
	public function test_occurrences_route_short_circuits_when_site_has_no_recurring_events(): void {
		global $wpdb;

		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$seen  = 0;

		$counter = static function ( string $query ) use ( &$seen, $table ): string {
			if ( str_contains( $query, $table ) ) {
				++$seen;
			}

			return $query;
		};

		add_filter( 'query', $counter );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$response = $this->dispatch( $request );

		remove_filter( 'query', $counter );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$this->assertSame(
			0,
			$seen,
			'A site with no recurring events must issue zero occurrence-table queries from the occurrences route.'
		);
	}

	/**
	 * `get_occurrences()` reads through `Series::resolve_post_ids()`
	 * rather than wrapping `$post_id` alone in an array, so a series split
	 * across posts by the `gatherpress_series_post_ids` seam still
	 * lists every sibling post's occurrences from the one open post. Without
	 * the resolver, a sibling's occurrences become unreachable from the
	 * restore UI.
	 *
	 * @covers ::get_occurrences
	 *
	 * @return void
	 */
	public function test_occurrences_route_lists_every_post_the_series_resolver_returns(): void {
		list( $post_id, $recurrence_id )            = $this->create_event_with_occurrence();
		list( $sibling_id, $sibling_recurrence_id ) = $this->create_event_with_occurrence( 10 );

		$filter = static function ( array $post_ids, int $requested_post_id ) use ( $post_id, $sibling_id ): array {
			return $post_id === $requested_post_id ? array( $post_id, $sibling_id ) : $post_ids;
		};

		add_filter( 'gatherpress_series_post_ids', $filter, 10, 2 );

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$response = $this->dispatch( $request );

		remove_filter( 'gatherpress_series_post_ids', $filter, 10 );

		$recurrence_ids = array_column( $response->get_data(), 'recurrence_id' );

		$this->assertContains(
			$recurrence_id,
			$recurrence_ids,
			"The requested post's own occurrence must be listed."
		);
		$this->assertContains(
			$sibling_recurrence_id,
			$recurrence_ids,
			'A sibling post the resolver returns must also be listed. This is what breaks without resolve_post_ids().'
		);
	}

	/**
	 * Reassign a fixture post to another author.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id   Post to reassign.
	 * @param int $author_id User to make the author.
	 *
	 * @return void
	 */
	protected function set_author( int $post_id, int $author_id ): void {
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_author' => $author_id,
			)
		);
	}

	/**
	 * The list route authorizes every sibling it returns, not only the post
	 * the request named. `has_edit_permission()` runs one capability check on
	 * the request's `post_id`, while `Series::resolve_post_ids()` can widen
	 * the read to sibling posts the caller was never authorized for, so a
	 * user who can edit A but not B must not learn B's occurrence dates or
	 * statuses from A's panel.
	 *
	 * @covers ::get_occurrences
	 * @covers ::authorized_series_post_ids
	 *
	 * @return void
	 */
	public function test_occurrences_route_omits_siblings_the_caller_cannot_edit(): void {
		list( $post_id, $recurrence_id )            = $this->create_event_with_occurrence();
		list( $sibling_id, $sibling_recurrence_id ) = $this->create_event_with_occurrence( 10 );

		$author       = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_author = $this->factory->user->create( array( 'role' => 'author' ) );

		$this->set_author( $post_id, $author );
		$this->set_author( $sibling_id, $other_author );

		$filter = static function ( array $post_ids, int $requested_post_id ) use ( $post_id, $sibling_id ): array {
			return $post_id === $requested_post_id ? array( $post_id, $sibling_id ) : $post_ids;
		};

		add_filter( 'gatherpress_series_post_ids', $filter, 10, 2 );

		wp_set_current_user( $author );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/event/occurrences' );
		$request->set_param( 'post_id', $post_id );

		$response = $this->dispatch( $request );

		remove_filter( 'gatherpress_series_post_ids', $filter, 10 );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertContains(
			$recurrence_id,
			array_column( $data, 'recurrence_id' ),
			"The caller's own post must still be listed."
		);
		$this->assertNotContains(
			$sibling_recurrence_id,
			array_column( $data, 'recurrence_id' ),
			'A sibling the caller cannot edit must not appear in the list.'
		);
		$this->assertNotContains(
			(string) $sibling_id,
			array_map( 'strval', array_column( $data, 'series_post_id' ) ),
			'No row owned by the unauthorized sibling may be returned.'
		);
	}

	/**
	 * An administrator, who can edit both posts, still sees the whole series.
	 * The filter must drop only what the caller genuinely cannot edit.
	 *
	 * @covers ::authorized_series_post_ids
	 *
	 * @return void
	 */
	public function test_authorized_series_post_ids_keeps_every_editable_sibling(): void {
		list( $post_id )    = $this->create_event_with_occurrence();
		list( $sibling_id ) = $this->create_event_with_occurrence( 10 );

		$filter = static function ( array $post_ids, int $requested_post_id ) use ( $post_id, $sibling_id ): array {
			return $post_id === $requested_post_id ? array( $post_id, $sibling_id ) : $post_ids;
		};

		add_filter( 'gatherpress_series_post_ids', $filter, 10, 2 );

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$authorized = Utility::invoke_hidden_method(
			Rest_Api::get_instance(),
			'authorized_series_post_ids',
			array( $post_id )
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$unauthorized = Utility::invoke_hidden_method(
			Rest_Api::get_instance(),
			'authorized_series_post_ids',
			array( $post_id )
		);

		remove_filter( 'gatherpress_series_post_ids', $filter, 10 );

		$this->assertSame( array( $post_id, $sibling_id ), $authorized );
		$this->assertSame( array(), $unauthorized );
	}

	/**
	 * The write half of the same invariant: the client submits the row's own
	 * owner post ID, so a caller who can edit A but not sibling B is refused
	 * outright rather than mutating B through A's authorization.
	 *
	 * @covers ::has_edit_permission
	 *
	 * @return void
	 */
	public function test_cancel_route_refuses_a_sibling_the_caller_cannot_edit(): void {
		list( $post_id )                            = $this->create_event_with_occurrence();
		list( $sibling_id, $sibling_recurrence_id ) = $this->create_event_with_occurrence( 10 );

		$author       = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_author = $this->factory->user->create( array( 'role' => 'author' ) );

		$this->set_author( $post_id, $author );
		$this->set_author( $sibling_id, $other_author );

		wp_set_current_user( $author );

		$response = $this->dispatch(
			$this->build_request( $sibling_id, $sibling_recurrence_id, Occurrences::STATUS_CANCELED )
		);

		$this->assertSame( 403, $response->get_status() );

		$row = Occurrences::get_instance()->get( $sibling_id, $sibling_recurrence_id );

		$this->assertSame(
			Occurrences::STATUS_SCHEDULED,
			$row['status'],
			"The sibling's occurrence must be untouched."
		);
	}

	/**
	 * Run the occurrence-aware "upcoming" query and reduce the results to
	 * `post_id|recurrence_id` strings.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] One entry per result row.
	 */
	protected function run_upcoming_query_entries(): array {
		$query = new WP_Query(
			array(
				'post_type'                    => Event::POST_TYPE,
				Event_Query::EVENT_QUERY_PARAM => 'upcoming',
				'posts_per_page'               => 20,
				'orderby'                      => 'datetime',
				'order'                        => 'ASC',
			)
		);

		return array_map(
			static function ( WP_Post $post ): string {
				return $post->ID . '|' . (string) $post->gatherpress_recurrence_id;
			},
			$query->posts
		);
	}
}
