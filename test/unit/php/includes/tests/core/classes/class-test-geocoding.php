<?php
/**
 * Class handles unit tests for GatherPress\Core\Geocoding.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Geocoding;
use GatherPress\Core\Utility as GP_Utility;
use GatherPress\Core\Venue\Meta as Venue_Meta;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Mocks\Http;
use PMC\Unit_Test\Utility;
use ReflectionMethod;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Test_Geocoding.
 *
 * @coversDefaultClass \GatherPress\Core\Geocoding
 */
class Test_Geocoding extends Base {
	/**
	 * HTTP mock instance.
	 *
	 * @var Http
	 */
	protected $http_mock;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Register and enable HTTP mocking.
		$this->http_mock = new Http();
		$this->mock->register( $this->http_mock, 'http' );
		$this->http_mock->enable();
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		// Clear scheduled async-geocode events between tests so wp_next_scheduled
		// lookups don't bleed across cases. Hook-based tests below schedule cron
		// events that would otherwise persist for the remainder of the run.
		wp_clear_scheduled_hook( Geocoding::CRON_ACTION );

		$this->http_mock->reset();
		parent::tearDown();
	}

	/**
	 * Helper that fires a Photon JSON response for the next outbound HTTP call.
	 *
	 * @param array $features `features` payload (use empty array to simulate "no match").
	 * @return void
	 */
	private function mock_photon_response( array $features ): void {
		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( array( 'features' => $features ) ),
			)
		);
	}

	/**
	 * Helper that builds a Photon `feature` with the standard structured-address
	 * properties (`housenumber`, `street`, `city`, `county`, `state`, `postcode`,
	 * `country`, `countrycode`) at sensible default values for a US address.
	 *
	 * @param array $overrides Property overrides to merge over the defaults.
	 * @return array
	 */
	private function build_photon_feature( array $overrides = array() ): array {
		$properties = array_merge(
			array(
				'housenumber' => '11',
				'street'      => 'Durrell Street',
				'city'        => 'Verona',
				'county'      => 'Essex County',
				'state'       => 'New Jersey',
				'postcode'    => '07044',
				'country'     => 'United States',
				'countrycode' => 'us',
			),
			$overrides
		);

		return array(
			'geometry'   => array(
				'coordinates' => array( -74.2398353, 40.8435252 ),
			),
			'properties' => $properties,
		);
	}

	/**
	 * Invokes a private Geocoding method (for line coverage of label helpers under Xdebug).
	 *
	 * @param Geocoding $instance Instance.
	 * @param string    $method   Method name.
	 * @param array     $args     Arguments.
	 * @return mixed
	 */
	private function invoke_geocoding_private( Geocoding $instance, string $method, array $args = array() ) {
		$ref = new ReflectionMethod( Geocoding::class, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( $instance, $args );
	}

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Geocoding::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'rest_api_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_endpoints' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'block_editor_settings_all',
				'priority' => 10,
				'callback' => array( $instance, 'add_editor_settings' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_schedule_geocode' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_schedule_geocode' ),
			),
			array(
				'type'     => 'action',
				'name'     => Geocoding::CRON_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'async_geocode_venue' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for add_editor_settings: publishes the min-query-length under gatherpress.config.
	 *
	 * @covers ::add_editor_settings
	 *
	 * @return void
	 */
	public function test_add_editor_settings_publishes_min_query_length(): void {
		$instance = Geocoding::get_instance();

		// With no prior settings, the method seeds both the namespace and the config sub-array.
		$seeded = $instance->add_editor_settings( array() );
		$this->assertSame(
			3,
			$seeded['gatherpress']['config']['addressSearchMinQueryLength'],
			'Failed to assert the minimum query length is exposed under gatherpress.config.'
		);

		// With an existing gatherpress namespace but no config key, the config sub-array is created.
		$with_namespace = $instance->add_editor_settings(
			array( 'gatherpress' => array( 'customKey' => 'customValue' ) )
		);
		$this->assertSame(
			'customValue',
			$with_namespace['gatherpress']['customKey'],
			'Failed to preserve existing gatherpress keys.'
		);
		$this->assertSame(
			3,
			$with_namespace['gatherpress']['config']['addressSearchMinQueryLength']
		);

		// With an existing config sub-array, the min-length is merged alongside other keys.
		$with_config = $instance->add_editor_settings(
			array( 'gatherpress' => array( 'config' => array( 'existing' => 'value' ) ) )
		);
		$this->assertSame( 'value', $with_config['gatherpress']['config']['existing'] );
		$this->assertSame(
			3,
			$with_config['gatherpress']['config']['addressSearchMinQueryLength']
		);
	}

	/**
	 * Coverage for the private JSON-decode-failure logger.
	 *
	 * Exercises every branch (including the error_log path). The `gatherpress_log_geocoding_errors`
	 * filter is flipped on and off explicitly so this test behaves identically whether
	 * WP_DEBUG is on (local) or off (CI default).
	 *
	 * @covers ::maybe_log_json_decode_failure
	 *
	 * @return void
	 */
	public function test_maybe_log_json_decode_failure_branches(): void {
		$instance = Geocoding::get_instance();

		// Branch 1: decoded value is non-null → early return.
		$this->invoke_geocoding_private(
			$instance,
			'maybe_log_json_decode_failure',
			array( '{"ok":true}', array( 'ok' => true ), 'geocode_address' )
		);

		// Branch 2: body is whitespace-only → early return.
		$this->invoke_geocoding_private(
			$instance,
			'maybe_log_json_decode_failure',
			array( '   ', null, 'search_addresses' )
		);

		// Branch 3: guard filter returns false → early return.
		add_filter( 'gatherpress_log_geocoding_errors', '__return_false' );
		$this->invoke_geocoding_private(
			$instance,
			'maybe_log_json_decode_failure',
			array( 'not json at all', null, 'geocode_address' )
		);
		remove_filter( 'gatherpress_log_geocoding_errors', '__return_false' );

		// Branch 4: all conditions met → reaches error_log(). Redirect the SAPI log
		// to a temp file so the diagnostic line is captured instead of leaking to
		// PHPUnit's stderr, and assert on its contents.
		add_filter( 'gatherpress_log_geocoding_errors', '__return_true' );

		$log_file = tempnam( sys_get_temp_dir(), 'gp-geocoding-log-' );
		// phpcs:ignore WordPress.PHP.IniSet.Risky
		$previous = ini_set( 'error_log', $log_file );

		try {
			$this->invoke_geocoding_private(
				$instance,
				'maybe_log_json_decode_failure',
				array( 'not json at all', null, 'geocode_address' )
			);

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$captured = (string) file_get_contents( $log_file );
		} finally {
			// phpcs:ignore WordPress.PHP.IniSet.Risky
			ini_set( 'error_log', (string) $previous );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $log_file );
			remove_filter( 'gatherpress_log_geocoding_errors', '__return_true' );
		}

		$this->assertStringContainsString(
			'geocode_address received non-JSON body',
			$captured,
			'Branch 4 should write a diagnostic line to the PHP error log.'
		);
	}

	/**
	 * Coverage for register_endpoints method.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_register_endpoints(): void {
		$instance = Geocoding::get_instance();

		$instance->register_endpoints();

		$rest_server = rest_get_server();
		$namespace   = Utility::get_hidden_property(
			$rest_server,
			'namespaces'
		)[ GATHERPRESS_REST_NAMESPACE ];

		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/geocode', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert geocode endpoint is registered.'
		);
		$this->assertEquals(
			1,
			$namespace[ sprintf( '/%s/geocode/search', GATHERPRESS_REST_NAMESPACE ) ],
			'Failed to assert geocode search endpoint is registered.'
		);
	}

	/**
	 * Coverage for geocode_address with empty address.
	 *
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_geocode_address_empty_address(): void {
		$instance = Geocoding::get_instance();
		$request  = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '' );

		$response = $instance->geocode_address( $request );

		$this->assertInstanceOf( 'WP_Error', $response, 'Failed to assert response is WP_Error for empty address.' );
		$this->assertEquals( 'missing_address', $response->get_error_code(), 'Failed to assert correct error code.' );
	}

	/**
	 * Coverage for geocode_address with successful response.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_address_success(): void {
		$instance = Geocoding::get_instance();

		// Mock the Photon API response (GeoJSON).
		$mock_response = array(
			'features' => array(
				array(
					'geometry' => array(
						// GeoJSON format: longitude first, then latitude.
						'coordinates' => array( -73.935242, 40.73061 ),
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_response ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St, New York, NY' );

		$response = $instance->geocode_address( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );
		$this->assertEquals( 200, $response->get_status(), 'Failed to assert 200 status code.' );

		$data = $response->get_data();
		$this->assertEquals( '40.73061', $data['latitude'], 'Failed to assert correct latitude.' );
		$this->assertEquals( '-73.935242', $data['longitude'], 'Failed to assert correct longitude.' );
		$this->assertNull( $data['error'], 'Failed to assert no error.' );
	}

	/**
	 * Coverage for geocode_address with no results.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 * @covers ::build_not_found_payload
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_address_no_results(): void {
		$instance = Geocoding::get_instance();

		// Mock empty response.
		$mock_response = array(
			'features' => array(),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_response ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', 'Invalid Address XYZ123' );

		$response = $instance->geocode_address( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );
		$this->assertEquals( 200, $response->get_status(), 'Failed to assert 200 status code.' );

		$data = $response->get_data();
		$this->assertEquals( '', $data['latitude'], 'Failed to assert empty latitude.' );
		$this->assertEquals( '', $data['longitude'], 'Failed to assert empty longitude.' );
		$this->assertStringContainsString(
			'Could not find location',
			$data['error'],
			'Failed to assert error message.'
		);
	}

	/**
	 * Coverage for geocode_address with HTTP error response.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 *
	 * @return void
	 */
	public function test_geocode_address_http_error(): void {
		$instance = Geocoding::get_instance();

		// Mock HTTP error response.
		$this->http_mock->mock(
			'*',
			array(
				'headers' => 'HTTP/1.1 500 Internal Server Error',
				'body'    => '',
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );

		$response = $instance->geocode_address( $request );

		$this->assertInstanceOf( 'WP_Error', $response, 'Failed to assert response is WP_Error for HTTP error.' );
		$this->assertEquals( 'geocoding_failed', $response->get_error_code(), 'Failed to assert correct error code.' );
	}

	/**
	 * Coverage for get_language_code method.
	 *
	 * @covers ::get_language_code
	 *
	 * @return void
	 */
	public function test_get_language_code(): void {
		$instance = Geocoding::get_instance();

		// Test with en_US locale.
		add_filter(
			'locale',
			static function () {
				return 'en_US';
			}
		);

		$language_code = Utility::invoke_hidden_method( $instance, 'get_language_code' );
		$this->assertEquals( 'en', $language_code, 'Failed to assert language code is en.' );

		// Test with de_DE locale.
		add_filter(
			'locale',
			static function () {
				return 'de_DE';
			},
			20
		);

		$language_code = Utility::invoke_hidden_method( $instance, 'get_language_code' );
		$this->assertEquals( 'de', $language_code, 'Failed to assert language code is de.' );
	}

	/**
	 * Coverage for get_user_agent method.
	 *
	 * @covers ::get_user_agent
	 *
	 * @return void
	 */
	public function test_get_user_agent(): void {
		$instance   = Geocoding::get_instance();
		$user_agent = Utility::invoke_hidden_method( $instance, 'get_user_agent' );

		$this->assertStringContainsString(
			'GatherPress/',
			$user_agent,
			'Failed to assert user agent contains GatherPress.'
		);
		$this->assertStringContainsString(
			'WordPress/',
			$user_agent,
			'Failed to assert user agent contains WordPress.'
		);
		$this->assertStringContainsString(
			home_url(),
			$user_agent,
			'Failed to assert user agent contains home URL.'
		);
	}

	/**
	 * Coverage for endpoint permission callback.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_endpoint_permission_callback(): void {
		$instance = Geocoding::get_instance();

		// Register endpoints to access route config.
		$instance->register_endpoints();

		$rest_server      = rest_get_server();
		$routes           = $rest_server->get_routes();
		$route_key        = '/' . GATHERPRESS_REST_NAMESPACE . '/geocode';
		$route_search_key = '/' . GATHERPRESS_REST_NAMESPACE . '/geocode/search';

		$this->assertArrayHasKey( $route_key, $routes, 'Failed to assert geocode route exists.' );
		$this->assertArrayHasKey( $route_search_key, $routes, 'Failed to assert geocode search route exists.' );

		// Get the permission callback.
		$route_config        = $routes[ $route_key ][0];
		$permission_callback = $route_config['permission_callback'];

		// Test with user who can edit posts.
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$result = call_user_func( $permission_callback );
		$this->assertTrue( $result, 'Failed to assert permission granted for editor.' );

		// Test with user who cannot edit posts.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$result = call_user_func( $permission_callback );
		$this->assertFalse( $result, 'Failed to assert permission denied for subscriber.' );
	}

	/**
	 * Test PHOTON_API_URL constant value.
	 *
	 * @return void
	 */
	public function test_photon_api_url_constant(): void {
		$this->assertSame(
			'https://photon.komoot.io/api',
			Geocoding::PHOTON_API_URL,
			'Failed to assert correct Photon API URL.'
		);
	}

	/**
	 * Coverage for geocode_address with network error (WP_Error).
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 *
	 * @return void
	 */
	public function test_geocode_address_network_error(): void {
		$instance = Geocoding::get_instance();

		// Use pre_http_request filter to return WP_Error.
		add_filter(
			'pre_http_request',
			static function () {
				return new \WP_Error( 'http_request_failed', 'Network error occurred' );
			},
			999
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );

		$response = $instance->geocode_address( $request );

		$this->assertInstanceOf( 'WP_Error', $response, 'Failed to assert response is WP_Error for network error.' );
		$this->assertEquals( 'geocoding_failed', $response->get_error_code(), 'Failed to assert correct error code.' );
		$this->assertStringContainsString(
			'Network error',
			$response->get_error_message(),
			'Failed to assert error message contains network error.'
		);

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Coverage for geocode_address with non-200 status code.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 *
	 * @return void
	 */
	public function test_geocode_address_non_200_status(): void {
		$instance = Geocoding::get_instance();

		// Use pre_http_request filter to return a 503 response.
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'response' => array(
						'code'    => 503,
						'message' => 'Service Unavailable',
					),
					'body'     => '',
					'headers'  => array(),
				);
			},
			999
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );

		$response = $instance->geocode_address( $request );

		$this->assertInstanceOf( 'WP_Error', $response, 'Failed to assert response is WP_Error for 503 status.' );
		$this->assertEquals( 'geocoding_failed', $response->get_error_code(), 'Failed to assert correct error code.' );
		$this->assertStringContainsString(
			'503',
			$response->get_error_message(),
			'Failed to assert error message contains status code.'
		);

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Coverage for geocode_address with missing geometry in response.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 * @covers ::build_not_found_payload
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_address_missing_geometry(): void {
		$instance = Geocoding::get_instance();

		// Mock response with features but no geometry coordinates.
		$mock_response = array(
			'features' => array(
				array(
					'properties' => array(
						'display_name' => 'Some Place',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_response ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );

		$response = $instance->geocode_address( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );

		$data = $response->get_data();
		$this->assertEquals( '', $data['latitude'], 'Failed to assert empty latitude for missing geometry.' );
		$this->assertEquals( '', $data['longitude'], 'Failed to assert empty longitude for missing geometry.' );
		$this->assertStringContainsString(
			'Could not find location',
			$data['error'],
			'Failed to assert error message.'
		);
	}

	/**
	 * Coverage for geocode_address building correct URL.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_address_url_params(): void {
		$instance      = Geocoding::get_instance();
		$captured_url  = '';
		$mock_response = array(
			'features' => array(
				array(
					'geometry' => array(
						'coordinates' => array( -73.935242, 40.73061 ),
					),
				),
			),
		);

		// Use a callback to capture the URL.
		$this->http_mock->mock(
			'*',
			array(
				'body' => static function ( &$headers, $url ) use ( &$captured_url, $mock_response ) {
					$captured_url = $url;
					$headers      = 'HTTP/1.1 200 OK';
					return wp_json_encode( $mock_response );
				},
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );

		$instance->geocode_address( $request );

		$this->assertStringContainsString(
			'photon.komoot.io',
			$captured_url,
			'Failed to assert URL contains Photon domain.'
		);
		$this->assertStringContainsString(
			'limit=1',
			$captured_url,
			'Failed to assert URL contains limit parameter.'
		);
		$this->assertStringContainsString(
			'lang=',
			$captured_url,
			'Failed to assert URL contains lang parameter.'
		);
		$this->assertStringContainsString(
			'q=123',
			$captured_url,
			'Failed to assert URL contains address query.'
		);
	}

	/**
	 * Returns the transient key used for a given geocode lookup.
	 *
	 * @param string $address Address value.
	 * @return string Transient key.
	 */
	private function geocode_cache_key( string $address ): string {
		$language = explode( '_', get_locale() )[0];

		return 'gatherpress_photon_geocode_' . md5( $address . '|' . $language );
	}

	/**
	 * Successful Photon geocode response is stored in a transient for reuse.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_address_caches_successful_response(): void {
		$instance  = Geocoding::get_instance();
		$cache_key = $this->geocode_cache_key( 'geocode-cache-success' );
		delete_transient( $cache_key );

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry' => array( 'coordinates' => array( -74.006, 40.7128 ) ),
							),
						),
					)
				),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', 'geocode-cache-success' );

		$instance->geocode_address( $request );

		$cached = get_transient( $cache_key );
		$this->assertIsArray( $cached );
		$this->assertSame( '40.7128', $cached['latitude'] );
		$this->assertSame( '-74.006', $cached['longitude'] );
		$this->assertNull( $cached['error'] );

		delete_transient( $cache_key );
	}

	/**
	 * Cached geocode result short-circuits the outbound Photon request.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 *
	 * @return void
	 */
	public function test_geocode_address_returns_cached_without_http_call(): void {
		$instance  = Geocoding::get_instance();
		$cache_key = $this->geocode_cache_key( 'geocode-cache-hit' );

		set_transient(
			$cache_key,
			array(
				'latitude'     => '1.23',
				'longitude'    => '4.56',
				'error'        => null,
				'house_number' => '',
				'street'       => '',
				'city'         => '',
				'county'       => '',
				'state'        => '',
				'postcode'     => '',
				'country'      => '',
				'country_code' => '',
			),
			MINUTE_IN_SECONDS
		);

		// If the HTTP layer were reached this mock would return different coordinates.
		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry' => array( 'coordinates' => array( 99, 99 ) ),
							),
						),
					)
				),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', 'geocode-cache-hit' );

		$response = $instance->geocode_address( $request );
		$data     = $response->get_data();

		$this->assertSame( '1.23', $data['latitude'] );
		$this->assertSame( '4.56', $data['longitude'] );
		$this->assertNull( $data['error'] );

		delete_transient( $cache_key );
	}

	/**
	 * Not-found geocode responses are also cached so repeat bad addresses stop hitting upstream.
	 *
	 * @covers ::geocode_address
	 * @covers ::geocode_to_result
	 * @covers ::build_not_found_payload
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_address_caches_not_found_response(): void {
		$instance  = Geocoding::get_instance();
		$cache_key = $this->geocode_cache_key( 'geocode-cache-missing' );
		delete_transient( $cache_key );

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( array( 'features' => array() ) ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', 'geocode-cache-missing' );

		$instance->geocode_address( $request );

		$cached = get_transient( $cache_key );
		$this->assertIsArray( $cached );
		$this->assertSame( '', $cached['latitude'] );
		$this->assertSame( '', $cached['longitude'] );
		$this->assertNotNull( $cached['error'] );

		delete_transient( $cache_key );
	}

	/**
	 * Coverage for search_addresses with empty query.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_empty_query(): void {
		$instance = Geocoding::get_instance();
		$request  = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', '' );

		$response = $instance->search_addresses( $request );

		$this->assertInstanceOf( 'WP_Error', $response, 'Failed to assert response is WP_Error for empty query.' );
		$this->assertEquals( 'missing_query', $response->get_error_code(), 'Failed to assert correct error code.' );
	}

	/**
	 * Coverage for search_addresses with short query (returns empty suggestions; min length matches JS).
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_short_query(): void {
		$instance = Geocoding::get_instance();
		$request  = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'ab' );

		$response = $instance->search_addresses( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );
		$data = $response->get_data();
		$this->assertIsArray( $data['suggestions'] );
		$this->assertCount( 0, $data['suggestions'] );
	}

	/**
	 * Returns the transient key used for a given search query.
	 *
	 * @param string $query Search query (already trimmed as it would arrive at the cache check).
	 * @return string Transient key.
	 */
	private function search_cache_key( string $query ): string {
		$language = explode( '_', get_locale() )[0];

		return 'gatherpress_photon_search_' . md5( $query . '|' . $language );
	}

	/**
	 * Successful Photon response is stored in a transient for reuse.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_caches_successful_response(): void {
		$instance  = Geocoding::get_instance();
		$cache_key = $this->search_cache_key( 'cache-success-query' );
		delete_transient( $cache_key );

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry'   => array( 'coordinates' => array( 2.3522, 48.8566 ) ),
								'properties' => array(
									'name' => 'Eiffel Tower',
									'city' => 'Paris',
								),
							),
						),
					)
				),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'cache-success-query' );

		$instance->search_addresses( $request );

		$cached = get_transient( $cache_key );
		$this->assertIsArray( $cached, 'Failed to assert successful response is cached.' );
		$this->assertCount( 1, $cached, 'Failed to assert cached suggestions count.' );
		$this->assertSame( 'Eiffel Tower, Paris', $cached[0]['label'] );

		delete_transient( $cache_key );
	}

	/**
	 * Cached suggestions short-circuit the outbound Photon request.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_returns_cached_without_http_call(): void {
		$instance  = Geocoding::get_instance();
		$cache_key = $this->search_cache_key( 'cache-hit-query' );

		set_transient(
			$cache_key,
			array(
				array(
					'label'     => 'Cached Place',
					'latitude'  => '10',
					'longitude' => '20',
				),
			),
			MINUTE_IN_SECONDS
		);

		// If HTTP were actually called this mock would produce a different label,
		// so a cache hit is visible in the assertions below.
		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry'   => array( 'coordinates' => array( 0, 0 ) ),
								'properties' => array(
									'name' => 'Should Not Be Used',
									'city' => 'Wrong',
								),
							),
						),
					)
				),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'cache-hit-query' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['suggestions'] );
		$this->assertSame( 'Cached Place', $data['suggestions'][0]['label'] );
		$this->assertSame( '10', $data['suggestions'][0]['latitude'] );
		$this->assertSame( '20', $data['suggestions'][0]['longitude'] );

		delete_transient( $cache_key );
	}

	/**
	 * Empty Photon feature lists are also cached to avoid hammering upstream on dead-end queries.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_caches_empty_features_response(): void {
		$instance  = Geocoding::get_instance();
		$cache_key = $this->search_cache_key( 'cache-empty-query' );
		delete_transient( $cache_key );

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( array( 'features' => array() ) ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'cache-empty-query' );

		$instance->search_addresses( $request );

		$cached = get_transient( $cache_key );
		$this->assertIsArray( $cached, 'Failed to assert empty response is cached.' );
		$this->assertCount( 0, $cached );

		delete_transient( $cache_key );
	}

	/**
	 * Search returns Photon GeoJSON features as suggestions.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_success(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'features' => array(
				array(
					'type'       => 'Feature',
					'geometry'   => array(
						'type'        => 'Point',
						'coordinates' => array( -74.0060, 40.7128 ),
					),
					'properties' => array(
						'housenumber' => '1453',
						'street'      => '3rd Avenue',
						'city'        => 'New York',
						'state'       => 'New York',
						'postcode'    => '10028',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', '1453 3rd' );

		$response = $instance->search_addresses( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );
		$data = $response->get_data();
		$this->assertCount( 1, $data['suggestions'] );
		$suggestion = $data['suggestions'][0];
		$this->assertArrayHasKey( 'label', $suggestion );
		$this->assertSame(
			'1453 3rd Avenue, New York, 10028',
			$suggestion['label']
		);
		$this->assertSame( '40.7128', $suggestion['latitude'] );
		// JSON float round-trip matches PHP's string cast of the coordinate value.
		$this->assertSame( '-74.006', $suggestion['longitude'] );
	}

	/**
	 * POI-style result uses name when street is absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_uses_name_when_no_street(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'features' => array(
				array(
					'geometry'   => array(
						'coordinates' => array( 2.3522, 48.8566 ),
					),
					'properties' => array(
						'name'    => 'Some Café',
						'city'    => 'Paris',
						'country' => 'France',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Paris' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();
		$this->assertSame( 'Some Café, Paris', $data['suggestions'][0]['label'] );
	}

	/**
	 * Coverage for search_addresses with non-array JSON body.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_invalid_json(): void {
		$instance = Geocoding::get_instance();

		$this->http_mock->mock(
			'*',
			array(
				'body' => '"not-an-array"',
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Somewhere' );

		$response = $instance->search_addresses( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );
		$data = $response->get_data();
		$this->assertIsArray( $data['suggestions'] );
		$this->assertCount( 0, $data['suggestions'] );
	}

	/**
	 * Whitespace-only query is treated as missing (same as empty string).
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_whitespace_only_query(): void {
		$instance = Geocoding::get_instance();
		$request  = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', '   ' );

		$response = $instance->search_addresses( $request );

		$this->assertInstanceOf(
			'WP_Error',
			$response,
			'Failed to assert response is WP_Error for whitespace-only query.'
		);
		$this->assertEquals( 'missing_query', $response->get_error_code(), 'Failed to assert correct error code.' );
	}

	/**
	 * Network failure returns geocoding_search_failed.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_network_error(): void {
		$instance = Geocoding::get_instance();

		add_filter(
			'pre_http_request',
			static function () {
				return new \WP_Error( 'http_request_failed', 'Search network error' );
			},
			999
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Paris' );

		$response = $instance->search_addresses( $request );

		remove_all_filters( 'pre_http_request' );

		$this->assertInstanceOf(
			'WP_Error',
			$response,
			'Failed to assert response is WP_Error for search network error.'
		);
		$this->assertEquals(
			'geocoding_search_failed',
			$response->get_error_code(),
			'Failed to assert correct error code.'
		);
		$this->assertStringContainsString(
			'Search network error',
			$response->get_error_message(),
			'Failed to assert error message.'
		);
	}

	/**
	 * Non-200 HTTP status returns geocoding_search_failed.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_non_200_status(): void {
		$instance = Geocoding::get_instance();

		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'response' => array(
						'code'    => 503,
						'message' => 'Service Unavailable',
					),
					'body'     => '',
					'headers'  => array(),
				);
			},
			999
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Berlin' );

		$response = $instance->search_addresses( $request );

		remove_all_filters( 'pre_http_request' );

		$this->assertInstanceOf(
			'WP_Error',
			$response,
			'Failed to assert response is WP_Error for 503 search.'
		);
		$this->assertEquals(
			'geocoding_search_failed',
			$response->get_error_code(),
			'Failed to assert correct error code.'
		);
		$this->assertStringContainsString(
			'503',
			$response->get_error_message(),
			'Failed to assert error message contains status code.'
		);
	}

	/**
	 * Search URL targets Photon with limit and lang.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_url_params(): void {
		$instance      = Geocoding::get_instance();
		$captured_url  = '';
		$mock_response = array(
			'features' => array(
				array(
					'geometry'   => array(
						'coordinates' => array( 0.0, 0.0 ),
					),
					'properties' => array(
						'name' => 'X',
						'city' => 'Y',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => static function ( &$headers, $url ) use ( &$captured_url, $mock_response ) {
					$captured_url = $url;
					$headers      = 'HTTP/1.1 200 OK';
					return wp_json_encode( $mock_response );
				},
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Test Query' );

		$instance->search_addresses( $request );

		$this->assertStringContainsString(
			'photon.komoot.io',
			$captured_url,
			'Failed to assert URL contains Photon domain.'
		);
		$this->assertStringContainsString( 'limit=5', $captured_url, 'Failed to assert limit=5.' );
		$this->assertStringContainsString( 'lang=', $captured_url, 'Failed to assert lang.' );
		$this->assertStringContainsString( 'q=Test', $captured_url, 'Failed to assert query string.' );
	}

	/**
	 * Features without coordinates or with empty labels are skipped.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_skips_malformed_rows(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'features' => array(
				array(
					'type'       => 'Feature',
					'properties' => array(
						'name' => 'Skip',
					),
				),
				array(
					'type'       => 'Feature',
					'geometry'   => array(
						'coordinates' => array( -74.0060, 40.7128 ),
					),
					'properties' => array(
						'city' => 'NYC',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'NYC test' );

		$response = $instance->search_addresses( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );
		$data = $response->get_data();
		$this->assertCount( 1, $data['suggestions'] );
		$this->assertSame( 'NYC', $data['suggestions'][0]['label'] );
	}

	/**
	 * When state matches city, region is not duplicated in the label.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_does_not_duplicate_region_when_same_as_locality(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'features' => array(
				array(
					'geometry'   => array(
						'coordinates' => array( 0.0, 0.0 ),
					),
					'properties' => array(
						'street'   => 'Main St',
						'city'     => 'Springfield',
						'state'    => 'Springfield',
						'postcode' => '62701',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Spring' );

		$response = $instance->search_addresses( $request );

		$data       = $response->get_data();
		$suggestion = $data['suggestions'][0];
		$this->assertStringNotContainsString(
			'Springfield, Springfield',
			$suggestion['label'],
			'Failed to assert region is not duplicated when equal to locality.'
		);
		$this->assertStringContainsString( 'Springfield', $suggestion['label'] );
		$this->assertStringContainsString( '62701', $suggestion['label'] );
	}

	/**
	 * Rows that produce an empty label are skipped; later valid rows still appear.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_skips_row_when_computed_label_empty(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'features' => array(
				array(
					'geometry'   => array(
						'coordinates' => array( 1.0, 1.0 ),
					),
					'properties' => array(),
				),
				array(
					'geometry'   => array(
						'coordinates' => array( 2.0, 2.0 ),
					),
					'properties' => array(
						'city' => 'Kept City',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Kept' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['suggestions'] );
		$this->assertSame( 'Kept City', $data['suggestions'][0]['label'] );
	}

	/**
	 * Private helpers get_language_code and get_user_agent (direct invocation for coverage).
	 *
	 * @covers \GatherPress\Core\Geocoding::get_language_code
	 * @covers \GatherPress\Core\Geocoding::get_user_agent
	 *
	 * @return void
	 */
	public function test_geocoding_private_language_and_user_agent(): void {
		$instance = Geocoding::get_instance();

		$lang = $this->invoke_geocoding_private( $instance, 'get_language_code' );
		$this->assertIsString( $lang );
		$this->assertNotSame( '', $lang );

		$ua = $this->invoke_geocoding_private( $instance, 'get_user_agent' );
		$this->assertStringContainsString( 'GatherPress/', $ua );
		$this->assertStringContainsString( 'WordPress/', $ua );
	}

	/**
	 * Tests format_photon_feature_label: structured, empty, duplicate locality/state.
	 *
	 * @covers \GatherPress\Core\Geocoding::format_photon_feature_label
	 *
	 * @return void
	 */
	public function test_geocoding_private_format_photon_feature_label(): void {
		$instance = Geocoding::get_instance();

		$full = $this->invoke_geocoding_private(
			$instance,
			'format_photon_feature_label',
			array(
				array(
					'street'   => 'Oak',
					'city'     => 'Oakton',
					'state'    => 'Oak State',
					'postcode' => '12345',
				),
			)
		);
		$this->assertStringContainsString( 'Oak', $full );
		$this->assertStringContainsString( '12345', $full );

		$empty = $this->invoke_geocoding_private(
			$instance,
			'format_photon_feature_label',
			array( array() )
		);
		$this->assertSame( '', $empty );

		$same_locality = $this->invoke_geocoding_private(
			$instance,
			'format_photon_feature_label',
			array(
				array(
					'city'  => 'Dup',
					'state' => 'Dup',
				),
			)
		);
		$this->assertStringNotContainsString( 'Dup, Dup', $same_locality );

		$post_only = $this->invoke_geocoding_private(
			$instance,
			'format_photon_feature_label',
			array(
				array(
					'city'     => 'Z',
					'postcode' => '   ',
				),
			)
		);
		$this->assertStringContainsString( 'Z', $post_only );

		$district_only = $this->invoke_geocoding_private(
			$instance,
			'format_photon_feature_label',
			array(
				array(
					'district' => 'Brooklyn',
					'state'    => 'NY',
					'postcode' => '11201',
				),
			)
		);
		$this->assertStringContainsString( 'Brooklyn', $district_only );
		$this->assertStringContainsString( 'NY', $district_only );

		$county_only = $this->invoke_geocoding_private(
			$instance,
			'format_photon_feature_label',
			array(
				array(
					'county' => 'Westchester',
					'state'  => 'NY',
				),
			)
		);
		$this->assertStringContainsString( 'Westchester', $county_only );

		$housenumber_street = $this->invoke_geocoding_private(
			$instance,
			'format_photon_feature_label',
			array(
				array(
					'housenumber' => '10',
					'street'      => 'Main Road',
					'city'        => 'Townsville',
				),
			)
		);
		$this->assertStringContainsString( '10 Main Road', $housenumber_street );
		$this->assertStringContainsString( 'Townsville', $housenumber_street );
	}

	/**
	 * Default and filtered Photon API base URL.
	 *
	 * @covers \GatherPress\Core\Geocoding::get_photon_api_url
	 *
	 * @return void
	 */
	public function test_geocoding_private_get_photon_api_url(): void {
		$instance = Geocoding::get_instance();

		$default = $this->invoke_geocoding_private( $instance, 'get_photon_api_url' );
		$this->assertSame( 'https://photon.komoot.io/api', $default );

		// IANA-reserved `example.com` resolves via DNS, so wp_http_validate_url() accepts it.
		add_filter(
			'gatherpress_photon_api_url',
			static function (): string {
				return 'https://example.com/api';
			}
		);

		$filtered = $this->invoke_geocoding_private( $instance, 'get_photon_api_url' );
		$this->assertSame( 'https://example.com/api', $filtered );

		remove_all_filters( 'gatherpress_photon_api_url' );

		// A filter that produces an invalid URL must fall back to the default.
		add_filter(
			'gatherpress_photon_api_url',
			static function (): string {
				return 'not-a-url';
			}
		);
		$fallback = $this->invoke_geocoding_private( $instance, 'get_photon_api_url' );
		$this->assertSame( 'https://photon.komoot.io/api', $fallback );

		remove_all_filters( 'gatherpress_photon_api_url' );
	}

	/**
	 * Non-array entries in GeoJSON features are ignored.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_skips_non_array_features(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'features' => array(
				'not-a-feature',
				array(
					'geometry'   => array(
						'coordinates' => array( 1.0, 2.0 ),
					),
					'properties' => array(
						'city' => 'Kept',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Kee' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['suggestions'] );
		$this->assertSame( 'Kept', $data['suggestions'][0]['label'] );
	}

	/**
	 * GeoJSON `features` may contain null entries; those are skipped like non-arrays.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_skips_null_feature(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'features' => array(
				null,
				array(
					'geometry'   => array(
						'coordinates' => array( 3.0, 4.0 ),
					),
					'properties' => array(
						'city' => 'After Null',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Nul' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['suggestions'] );
		$this->assertSame( 'After Null', $data['suggestions'][0]['label'] );
	}

	/**
	 * When `properties` is present but not an array, label building uses an empty array.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_non_array_properties_uses_empty_label_parts(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'features' => array(
				array(
					'geometry'   => array(
						'coordinates' => array( 5.0, 6.0 ),
					),
					'properties' => 'not-an-array',
				),
				array(
					'geometry'   => array(
						'coordinates' => array( 7.0, 8.0 ),
					),
					'properties' => array(
						'city' => 'Valid Row',
					),
				),
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Val' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertCount( 1, $data['suggestions'] );
		$this->assertSame( 'Valid Row', $data['suggestions'][0]['label'] );
	}

	/**
	 * Permission callback on /geocode/search matches /geocode (edit_posts).
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_search_route_permission_callback(): void {
		$instance = Geocoding::get_instance();
		$instance->register_endpoints();

		$rest_server = rest_get_server();
		$routes      = $rest_server->get_routes();
		$search_key  = '/' . GATHERPRESS_REST_NAMESPACE . '/geocode/search';

		$this->assertArrayHasKey( $search_key, $routes, 'Failed to assert search route exists.' );

		$permission_callback = $routes[ $search_key ][0]['permission_callback'];

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->assertTrue( call_user_func( $permission_callback ), 'Failed for editor.' );

		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( call_user_func( $permission_callback ), 'Failed for subscriber.' );
	}

	/**
	 * Meta keys the cron handler is responsible for populating. Centralized
	 * here so individual tests can iterate without hand-listing them.
	 *
	 * @return string[]
	 */
	private function structured_meta_keys(): array {
		return array_map(
			array( GP_Utility::class, 'prefix_key' ),
			Venue_Meta::STRUCTURED_ADDRESS_FIELDS
		);
	}

	/**
	 * The scheduler is hooked on every meta-write WP fires, so it has to
	 * return early when the meta key isn't `gatherpress_address`. Otherwise
	 * an unrelated meta save would queue a Photon roundtrip per save.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_skips_other_meta_keys(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_phone' );

		$this->assertFalse(
			wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) ),
			'Scheduling a non-address meta key must not enqueue a geocode cron.'
		);
	}

	/**
	 * The same hook fires for every post type. Saving an address-shaped
	 * meta on a non-venue post must not trigger the cron — only post types
	 * that declare `gatherpress-venue-information` support are eligible.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_skips_non_venue_post_type(): void {
		$instance = Geocoding::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$instance->maybe_schedule_geocode( 0, $post_id, 'gatherpress_address' );

		$this->assertFalse(
			wp_next_scheduled( Geocoding::CRON_ACTION, array( $post_id ) ),
			'A non-venue post type must not schedule a geocode cron even for the right meta key.'
		);
	}

	/**
	 * Sites that need to control egress (firewalled corp installs, dev
	 * environments without Photon access) opt out by returning `false` from
	 * the `gatherpress_geocode_on_save_enabled` filter. The cron must not
	 * be scheduled when the filter denies.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_respects_opt_out_filter(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_filter( 'gatherpress_geocode_on_save_enabled', '__return_false' );

		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );

		remove_filter( 'gatherpress_geocode_on_save_enabled', '__return_false' );

		$this->assertFalse(
			wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) ),
			'Returning false from gatherpress_geocode_on_save_enabled must suppress the cron.'
		);
	}

	/**
	 * Returning a non-null value from the pre-enqueue short-circuit filter
	 * suppresses the WP-Cron path so a companion plugin can route the
	 * fanout through Action Scheduler. The filter must receive the action
	 * hook name and the args the cron would have fired with, so it can
	 * forward them to its own scheduler.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_pre_enqueue_short_circuit(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$seen = array();
		$spy  = static function ( $short_circuit, $hook, $args ) use ( &$seen ) {
			$seen[] = array(
				'hook' => $hook,
				'args' => $args,
			);
			return 'handled-by-companion';
		};
		add_filter( 'gatherpress_async_geocode_pre_enqueue_job', $spy, 10, 3 );

		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );

		remove_filter( 'gatherpress_async_geocode_pre_enqueue_job', $spy, 10 );

		$this->assertFalse(
			wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) ),
			'Default WP-Cron path must be suppressed when the short-circuit filter returns non-null.'
		);
		$this->assertCount( 1, $seen, 'Filter is invoked exactly once per scheduling call.' );
		$this->assertSame( Geocoding::CRON_ACTION, $seen[0]['hook'], 'Filter receives the cron hook name.' );
		$this->assertSame(
			array( $venue_post_id ),
			$seen[0]['args'],
			'Filter receives the args that the hook would have fired with.'
		);
	}

	/**
	 * Every non-null return value — including falsy ones like `false`, `0`,
	 * and `''` — must suppress the default enqueue. Mirrors the WordPress
	 * `pre_*` filter contract (only `null` means "pass through") and locks
	 * the contract in so a future accident where the check becomes e.g.
	 * `if ( $short_circuit )` instead of `if ( null !== $short_circuit )`
	 * fails the suite.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_pre_enqueue_falsy_non_null_short_circuits(): void {
		$instance = Geocoding::get_instance();

		foreach ( array( false, 0, '' ) as $falsy_return ) {
			$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

			$callback = static function () use ( $falsy_return ) {
				return $falsy_return;
			};
			add_filter( 'gatherpress_async_geocode_pre_enqueue_job', $callback );

			$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );

			remove_filter( 'gatherpress_async_geocode_pre_enqueue_job', $callback );

			$this->assertFalse(
				wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) ),
				sprintf(
					'Filter returning %s (non-null) must suppress the default WP-Cron path.',
					wp_json_encode( $falsy_return )
				)
			);
		}
	}

	/**
	 * Returning `null` (the default) from the short-circuit filter must
	 * leave the default WP-Cron behavior untouched — the whole point of
	 * the filter is to be a no-op when nothing hooks it.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_pre_enqueue_null_preserves_default_path(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$passthrough = static function ( $short_circuit ) {
			return $short_circuit;
		};
		add_filter( 'gatherpress_async_geocode_pre_enqueue_job', $passthrough );

		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );

		remove_filter( 'gatherpress_async_geocode_pre_enqueue_job', $passthrough );

		$this->assertNotFalse(
			wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) ),
			'Null return from the short-circuit filter must fall through to wp_schedule_single_event().'
		);
	}

	/**
	 * The happy path: an address change on a venue post enqueues a geocode
	 * cron event for that venue.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_schedules_on_address_change(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );

		$this->assertNotFalse(
			wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) ),
			'A venue address change must schedule the geocode cron.'
		);
	}

	/**
	 * Two saves in the same window must not double-queue. `wp_next_scheduled`
	 * dedup keeps a single pending job per `(hook, args)` pair — the
	 * second call should be a no-op.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_dedups_repeat_calls(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );
		$first_timestamp = wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) );

		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );
		$second_timestamp = wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) );

		$this->assertSame(
			$first_timestamp,
			$second_timestamp,
			'Repeat calls must reuse the existing scheduled timestamp rather than queue a duplicate.'
		);
	}

	/**
	 * The schedule handler must skip cleanly when the post no longer exists
	 * (force-deleted between save and the listener firing).
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_skips_when_post_deleted(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		wp_delete_post( $venue_post_id, true );

		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );

		$this->assertFalse(
			wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) ),
			'A deleted post must not schedule a geocode cron.'
		);
	}

	/**
	 * Sites with heavy save hooks (revisions fanning out, multilingual sync)
	 * may need a longer delay; sites that batch saves may want zero.
	 * `gatherpress_async_geocode_delay` lets them override the default.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_maybe_schedule_geocode_respects_delay_filter(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_filter(
			'gatherpress_async_geocode_delay',
			static function () {
				return 60;
			}
		);

		$before = time();
		$instance->maybe_schedule_geocode( 0, $venue_post_id, 'gatherpress_address' );
		$after = time();

		$timestamp = wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) );

		remove_all_filters( 'gatherpress_async_geocode_delay' );

		$this->assertNotFalse( $timestamp, 'Filter override should still schedule the cron.' );
		// Allow ±1s of slack for the time() boundary that brackets the call.
		$this->assertGreaterThanOrEqual( $before + 60, $timestamp );
		$this->assertLessThanOrEqual( $after + 60, $timestamp );
	}

	/**
	 * End-to-end wiring: an actual `update_post_meta()` call on a venue
	 * fires `updated_post_meta` (or `added_post_meta` on first write), which
	 * routes through the scheduler. Locks the integration so a future
	 * refactor that drops the action listener is caught.
	 *
	 * @covers ::maybe_schedule_geocode
	 *
	 * @return void
	 */
	public function test_update_post_meta_schedules_geocode_via_action_hook(): void {
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta( $venue_post_id, 'gatherpress_address', '11 Durrell Street, Verona NJ' );

		$this->assertNotFalse(
			wp_next_scheduled( Geocoding::CRON_ACTION, array( $venue_post_id ) ),
			'update_post_meta() on gatherpress_address must schedule the cron via the action hook.'
		);
	}

	/**
	 * Cron handler must re-check the post type at run time — between
	 * schedule and fire, the post could have been retyped and we don't
	 * want to scribble structured-address meta onto a non-venue.
	 *
	 * @covers ::async_geocode_venue
	 *
	 * @return void
	 */
	public function test_async_geocode_venue_skips_non_venue_post_type(): void {
		$instance = Geocoding::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => 'post' ) );

		add_post_meta( $post_id, 'gatherpress_address', '11 Durrell Street' );
		add_post_meta( $post_id, 'gatherpress_city', 'Pre-existing' );

		$instance->async_geocode_venue( $post_id );

		$this->assertSame(
			'Pre-existing',
			(string) get_post_meta( $post_id, 'gatherpress_city', true ),
			'Cron handler must not touch meta on a post whose post type does not declare venue support.'
		);
	}

	/**
	 * Cron handler must skip cleanly when the post has been force-deleted
	 * between schedule and fire. `get_post()` returns null in that case;
	 * the explicit `WP_Post` guard catches it rather than relying on the
	 * `(string) get_post_type( $invalid_id )` cast pattern.
	 *
	 * @covers ::async_geocode_venue
	 *
	 * @return void
	 */
	public function test_async_geocode_venue_skips_when_post_deleted(): void {
		$instance = Geocoding::get_instance();

		// 999999 is a non-existent post ID — get_post() returns null.
		$instance->async_geocode_venue( 999999 );

		// No exception, no fatal — clean exit. Nothing else to assert.
		$this->assertTrue( true );
	}

	/**
	 * When Photon returns a `WP_Error`, the handler must fire the
	 * `gatherpress_async_geocode_failed` action with the post ID and the
	 * WP_Error so observability plugins can surface chronic failures.
	 *
	 * @covers ::async_geocode_venue
	 *
	 * @return void
	 */
	public function test_async_geocode_venue_fires_failed_action_on_photon_error(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta( $venue_post_id, 'gatherpress_address', '11 Durrell Street, Verona NJ' );

		$this->http_mock->mock(
			'*',
			array(
				'headers' => 'HTTP/1.1 503 Service Unavailable',
				'body'    => '',
			)
		);

		$captured = array();
		add_action(
			'gatherpress_async_geocode_failed',
			static function ( $post_id, $error ) use ( &$captured ): void {
				$captured[] = array(
					'post_id' => $post_id,
					'error'   => $error,
				);
			},
			10,
			2
		);

		$instance->async_geocode_venue( $venue_post_id );

		remove_all_actions( 'gatherpress_async_geocode_failed' );

		$this->assertCount( 1, $captured, 'Failure action must fire exactly once.' );
		$this->assertSame( $venue_post_id, $captured[0]['post_id'] );
		$this->assertInstanceOf( 'WP_Error', $captured[0]['error'] );
		$this->assertSame( 'geocoding_failed', $captured[0]['error']->get_error_code() );
	}

	/**
	 * `extract_structured_address()` must skip non-scalar property values
	 * rather than cast them to the literal "Array" sentinel string. Photon
	 * normally returns scalar fields, but a malformed response with a
	 * nested object/array would otherwise emit notices and corrupt meta.
	 *
	 * @covers ::geocode_to_result
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_to_result_skips_non_scalar_properties(): void {
		$instance = Geocoding::get_instance();

		$this->mock_photon_response(
			array(
				$this->build_photon_feature(
					array(
						// Malformed: city is an object/array instead of a string.
						'city'   => array( 'invalid' => 'shape' ),
						// Malformed: street is an object too.
						'street' => array( 'name' => 'should-be-a-string' ),
					)
				),
			)
		);

		$result = $instance->geocode_to_result( 'Non-scalar properties test address' );

		$this->assertIsArray( $result );
		$this->assertSame( '', $result['city'], 'Non-scalar properties must skip rather than cast to "Array".' );
		$this->assertSame( '', $result['street'], 'Non-scalar properties must skip rather than cast to "Array".' );
		// Other (well-formed) properties on the same feature still come through.
		$this->assertSame( '07044', $result['postcode'] );
	}

	/**
	 * When the address is cleared, the structured fields must be cleared
	 * too. Otherwise stale city/state/postcode pieces outlive the address
	 * they described and JSON-LD emitters surface mismatched data.
	 *
	 * @covers ::async_geocode_venue
	 *
	 * @return void
	 */
	public function test_async_geocode_venue_clears_structured_fields_on_empty_address(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		// Pre-populate every structured field so we can assert each one is cleared.
		foreach ( $this->structured_meta_keys() as $key ) {
			update_post_meta( $venue_post_id, $key, 'pre-existing' );
		}
		// Manually-entered lat/long for a venue without a street address (a
		// remote campsite, GPS-only marker, etc.) should survive an
		// address-clear — those coordinates aren't tied to the textual address.
		update_post_meta( $venue_post_id, 'gatherpress_latitude', '40.8435252' );
		update_post_meta( $venue_post_id, 'gatherpress_longitude', '-74.2398353' );

		// gatherpress_address is empty / absent.
		$instance->async_geocode_venue( $venue_post_id );

		foreach ( $this->structured_meta_keys() as $key ) {
			$this->assertSame(
				'',
				(string) get_post_meta( $venue_post_id, $key, true ),
				sprintf( 'Empty address must clear %s, but it was not cleared.', $key )
			);
		}

		$this->assertSame(
			'40.8435252',
			(string) get_post_meta( $venue_post_id, 'gatherpress_latitude', true ),
			'Empty address must not clear manually-entered lat/long.'
		);
		$this->assertSame(
			'-74.2398353',
			(string) get_post_meta( $venue_post_id, 'gatherpress_longitude', true ),
			'Empty address must not clear manually-entered lat/long.'
		);
	}

	/**
	 * On a Photon HTTP failure, the handler returns `WP_Error` and the
	 * structured fields are left as-is. Better to keep the previous good
	 * values than overwrite with empties on a transient upstream blip.
	 *
	 * @covers ::async_geocode_venue
	 * @covers ::geocode_to_result
	 *
	 * @return void
	 */
	public function test_async_geocode_venue_preserves_fields_on_photon_error(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta( $venue_post_id, 'gatherpress_address', '11 Durrell Street, Verona NJ' );
		update_post_meta( $venue_post_id, 'gatherpress_city', 'Verona' );
		update_post_meta( $venue_post_id, 'gatherpress_state', 'New Jersey' );
		update_post_meta( $venue_post_id, 'gatherpress_latitude', '40.8435252' );
		update_post_meta( $venue_post_id, 'gatherpress_longitude', '-74.2398353' );

		// Photon HTTP error: 503 status (header pattern matches the rest of this suite).
		$this->http_mock->mock(
			'*',
			array(
				'headers' => 'HTTP/1.1 503 Service Unavailable',
				'body'    => '',
			)
		);

		$instance->async_geocode_venue( $venue_post_id );

		$this->assertSame(
			'Verona',
			(string) get_post_meta( $venue_post_id, 'gatherpress_city', true ),
			'A Photon HTTP error must not overwrite a populated structured field.'
		);
		$this->assertSame(
			'New Jersey',
			(string) get_post_meta( $venue_post_id, 'gatherpress_state', true ),
			'A Photon HTTP error must not overwrite a populated structured field.'
		);
		$this->assertSame(
			'40.8435252',
			(string) get_post_meta( $venue_post_id, 'gatherpress_latitude', true ),
			'A Photon HTTP error must not overwrite previously-good lat/long.'
		);
		$this->assertSame(
			'-74.2398353',
			(string) get_post_meta( $venue_post_id, 'gatherpress_longitude', true ),
			'A Photon HTTP error must not overwrite previously-good lat/long.'
		);
	}

	/**
	 * Photon returns a feature with the standard Nominatim-shaped
	 * properties. The handler must persist each one to its corresponding
	 * meta key, snake_cased on our side (`housenumber` → `house_number`,
	 * `countrycode` → `country_code`).
	 *
	 * @covers ::async_geocode_venue
	 * @covers ::geocode_to_result
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_async_geocode_venue_writes_structured_fields_on_success(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta( $venue_post_id, 'gatherpress_address', '11 Durrell Street, Verona NJ' );

		$this->mock_photon_response( array( $this->build_photon_feature() ) );

		$instance->async_geocode_venue( $venue_post_id );

		$this->assertSame( '11', (string) get_post_meta( $venue_post_id, 'gatherpress_house_number', true ) );
		$this->assertSame( 'Durrell Street', (string) get_post_meta( $venue_post_id, 'gatherpress_street', true ) );
		$this->assertSame( 'Verona', (string) get_post_meta( $venue_post_id, 'gatherpress_city', true ) );
		$this->assertSame( 'Essex County', (string) get_post_meta( $venue_post_id, 'gatherpress_county', true ) );
		$this->assertSame( 'New Jersey', (string) get_post_meta( $venue_post_id, 'gatherpress_state', true ) );
		$this->assertSame( '07044', (string) get_post_meta( $venue_post_id, 'gatherpress_postcode', true ) );
		$this->assertSame( 'United States', (string) get_post_meta( $venue_post_id, 'gatherpress_country', true ) );
		$this->assertSame( 'us', (string) get_post_meta( $venue_post_id, 'gatherpress_country_code', true ) );

		// Lat/long are also persisted from the same Photon response so freeform-typed
		// addresses (no autocomplete pick) don't leave the map block uncoordinated.
		$this->assertSame( '40.8435252', (string) get_post_meta( $venue_post_id, 'gatherpress_latitude', true ) );
		$this->assertSame( '-74.2398353', (string) get_post_meta( $venue_post_id, 'gatherpress_longitude', true ) );
	}

	/**
	 * Photon returns a "no match" empty-features response. The handler
	 * should clear all structured fields — Photon actively said "this
	 * address doesn't resolve to anywhere" so leaving stale pieces in
	 * place would be lying.
	 *
	 * @covers ::async_geocode_venue
	 * @covers ::geocode_to_result
	 * @covers ::build_not_found_payload
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_async_geocode_venue_clears_structured_fields_on_photon_not_found(): void {
		$instance      = Geocoding::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta( $venue_post_id, 'gatherpress_address', 'Nowhere Real, Atlantis' );
		foreach ( $this->structured_meta_keys() as $key ) {
			update_post_meta( $venue_post_id, $key, 'stale' );
		}
		// Manually-entered lat/long for an unfindable venue (e.g. remote GPS
		// coordinates) should survive a Photon "not found" response —
		// Photon may simply not have data for the location.
		update_post_meta( $venue_post_id, 'gatherpress_latitude', '40.8435252' );
		update_post_meta( $venue_post_id, 'gatherpress_longitude', '-74.2398353' );

		$this->mock_photon_response( array() );

		$instance->async_geocode_venue( $venue_post_id );

		foreach ( $this->structured_meta_keys() as $key ) {
			$this->assertSame(
				'',
				(string) get_post_meta( $venue_post_id, $key, true ),
				sprintf( 'A Photon "not found" response must clear stale %s, but it remained populated.', $key )
			);
		}

		$this->assertSame(
			'40.8435252',
			(string) get_post_meta( $venue_post_id, 'gatherpress_latitude', true ),
			'Photon "not found" must not overwrite manually-entered lat/long.'
		);
		$this->assertSame(
			'-74.2398353',
			(string) get_post_meta( $venue_post_id, 'gatherpress_longitude', true ),
			'Photon "not found" must not overwrite manually-entered lat/long.'
		);
	}

	/**
	 * Cached entries written before structured pieces existed (e.g. a
	 * site upgraded from a prior version where the cache was populated
	 * with `{latitude, longitude, error}` only) must be treated as a
	 * cache miss and refetched. Without that, freshly-saved venues on
	 * upgraded sites would never get structured fields populated until
	 * the transient TTL expired.
	 *
	 * @covers ::geocode_to_result
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_to_result_self_heals_legacy_cache_without_structured_pieces(): void {
		$instance = Geocoding::get_instance();
		$address  = '11 Durrell Street, Verona NJ';

		// Seed the cache with the legacy three-key shape.
		$language     = $this->invoke_geocoding_private( $instance, 'get_language_code' );
		$cache_key    = 'gatherpress_photon_geocode_' . md5( $address . '|' . $language );
		$legacy_entry = array(
			'latitude'  => '40.0',
			'longitude' => '-74.0',
			'error'     => null,
		);
		set_transient( $cache_key, $legacy_entry, 15 * MINUTE_IN_SECONDS );

		// Photon will be hit because the cached entry doesn't have structured pieces.
		$this->mock_photon_response( array( $this->build_photon_feature() ) );

		$result = $instance->geocode_to_result( $address );

		$this->assertIsArray( $result, 'Refetched result must be an array.' );
		$this->assertSame(
			'11',
			(string) ( $result['house_number'] ?? '' ),
			'Self-heal must refetch from Photon and populate the structured pieces.'
		);
		$this->assertSame(
			'Verona',
			(string) ( $result['city'] ?? '' ),
			'Self-heal must populate city after refetch.'
		);
	}

	/**
	 * Whitespace-only addresses trim to empty inside `geocode_to_result()`
	 * and short-circuit to the canonical "not found" payload before any
	 * Photon round-trip. Covers the empty-address branch that
	 * `geocode_address()` itself doesn't reach (it has its own earlier
	 * empty-check), but `extract_structured_address()` consumers like
	 * direct in-process callers can still hit.
	 *
	 * @covers ::geocode_to_result
	 * @covers ::build_not_found_payload
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_to_result_returns_not_found_payload_for_whitespace_only_address(): void {
		$instance = Geocoding::get_instance();

		// HTTP layer must not be reached — assert by mocking a mismatched response.
		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry' => array( 'coordinates' => array( 99, 99 ) ),
							),
						),
					)
				),
			)
		);

		$result = $instance->geocode_to_result( '   ' );

		$this->assertIsArray( $result );
		$this->assertSame( '', $result['latitude'] );
		$this->assertSame( '', $result['longitude'] );
		$this->assertNotEmpty( $result['error'], 'Empty-address branch must include a user-facing error string.' );
		$this->assertSame( '', $result['house_number'] );
		$this->assertSame( '', $result['country_code'] );
	}

	/**
	 * Photon GeoJSON nominally puts the structured address under
	 * `features[0].properties` — but malformed responses may serialize that
	 * field as a string or null. The `: array()` fallback in
	 * `geocode_to_result()` keeps the merge safe in that case; without it,
	 * `extract_structured_address()` would receive a non-array and the type
	 * declaration would throw.
	 *
	 * @covers ::geocode_to_result
	 * @covers ::extract_structured_address
	 *
	 * @return void
	 */
	public function test_geocode_to_result_handles_non_array_properties(): void {
		$instance = Geocoding::get_instance();

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry'   => array( 'coordinates' => array( 7.0, 8.0 ) ),
								'properties' => 'not-an-array',
							),
						),
					)
				),
			)
		);

		$result = $instance->geocode_to_result( 'Non-array properties test address' );

		$this->assertIsArray( $result );
		$this->assertSame( '8', $result['latitude'] );
		$this->assertSame( '7', $result['longitude'] );
		// Structured pieces must be empty because the malformed `properties`
		// passed an empty array down to `extract_structured_address()`.
		$this->assertSame( '', $result['house_number'] );
		$this->assertSame( '', $result['city'] );
	}

	/**
	 * Structured-address pieces are exposed under the venue's `meta` field
	 * via REST `show_in_rest`, but the underlying meta has
	 * `auth_callback => __return_false`. PATCH attempts that try to write
	 * them must be silently dropped by `Meta::filter_readonly_meta` rather
	 * than failing the whole request — the editor often co-submits
	 * structured + editor-writable meta in one PATCH.
	 *
	 * @covers \GatherPress\Core\Venue\Meta::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_structured_address_meta_stripped_from_rest_writes(): void {
		$instance = Venue_Meta::get_instance();
		$request  = new WP_REST_Request();

		$request->set_param(
			'meta',
			array(
				'gatherpress_address'      => 'editor-writable',
				'gatherpress_house_number' => 'attempted-write',
				'gatherpress_city'         => 'attempted-write',
				'gatherpress_country_code' => 'attempted-write',
			)
		);

		$prepared = new \stdClass();
		$instance->filter_readonly_meta( $prepared, $request );

		$meta = $request->get_param( 'meta' );

		$this->assertArrayHasKey(
			'gatherpress_address',
			$meta,
			'Editor-writable address meta must pass through.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_house_number',
			$meta,
			'Structured-address house_number must be stripped from REST writes.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_city',
			$meta,
			'Structured-address city must be stripped from REST writes.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_country_code',
			$meta,
			'Structured-address country_code must be stripped from REST writes.'
		);
	}

	/**
	 * Direct coverage for `build_search_suggestions_response()`.
	 *
	 * The helper is exercised by every successful `/geocode/search`
	 * roundtrip, but xdebug coverage doesn't credit the body lines
	 * reliably when entry is via `$this->...` from another method on
	 * the same singleton instance. Call it via reflection so the body
	 * lines are unambiguously executed under coverage instrumentation.
	 *
	 * @covers ::build_search_suggestions_response
	 *
	 * @return void
	 */
	public function test_build_search_suggestions_response_handles_no_features(): void {
		$cache_key = 'gatherpress_photon_search_test_' . wp_generate_password( 8, false );
		$method    = new ReflectionMethod( Geocoding::class, 'build_search_suggestions_response' );
		$method->setAccessible( true );

		$response = $method->invoke( Geocoding::get_instance(), null, $cache_key );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array( 'suggestions' => array() ),
			$response->get_data(),
			'A non-array decode result must collapse to an empty suggestions list.'
		);

		// Empty-result branch still caches an empty array so repeat
		// queries skip Photon for the next SEARCH_CACHE_TTL.
		$this->assertSame( array(), get_transient( $cache_key ) );

		delete_transient( $cache_key );
	}

	/**
	 * Direct coverage for `build_search_suggestions_response()` with
	 * a populated payload — verifies the per-feature shape it builds
	 * and that the suggestions are cached for later cache-hit reads.
	 *
	 * @covers ::build_search_suggestions_response
	 *
	 * @return void
	 */
	public function test_build_search_suggestions_response_maps_features(): void {
		$cache_key = 'gatherpress_photon_search_test_' . wp_generate_password( 8, false );
		$payload   = array(
			'features' => array(
				// Well-formed feature → maps into a suggestion.
				array(
					'geometry'   => array(
						'coordinates' => array( -74.006, 40.7128 ),
					),
					'properties' => array(
						'name'    => 'Test Place',
						'city'    => 'New York',
						'country' => 'United States',
					),
				),
				// Non-array feature → skipped.
				'not an array',
				// Feature missing coords → skipped.
				array(
					'geometry'   => array(),
					'properties' => array( 'name' => 'No Coords' ),
				),
				// Feature with empty label (no displayable properties) → skipped.
				array(
					'geometry'   => array(
						'coordinates' => array( 0, 0 ),
					),
					'properties' => array(),
				),
				// Feature with `properties` missing entirely — exercises
				// the `: array()` fallback in the ternary. Empty label →
				// skipped.
				array(
					'geometry' => array(
						'coordinates' => array( 1, 1 ),
					),
				),
			),
		);

		$method = new ReflectionMethod( Geocoding::class, 'build_search_suggestions_response' );
		$method->setAccessible( true );

		$response = $method->invoke( Geocoding::get_instance(), $payload, $cache_key );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertCount(
			1,
			$data['suggestions'],
			'Only the well-formed feature should land in the suggestion list.'
		);
		$this->assertSame( '40.7128', $data['suggestions'][0]['latitude'] );
		$this->assertSame( '-74.006', $data['suggestions'][0]['longitude'] );

		$this->assertSame(
			$data['suggestions'],
			get_transient( $cache_key ),
			'Built suggestions must be cached under the supplied key.'
		);

		delete_transient( $cache_key );
	}

	/**
	 * Returning `false` from `gatherpress_geocode_rate_limit_enabled`
	 * disables the rate limit entirely — even a bucket that's
	 * already at the ceiling lets requests through, and no transient
	 * read/write happens.
	 *
	 * @covers ::check_rate_limit
	 *
	 * @return void
	 */
	public function test_rate_limit_can_be_disabled_via_filter(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		// Pre-fill the bucket at the ceiling — without the disable
		// filter, the next call would 429.
		set_transient(
			'gatherpress_geocode_rate_' . $user_id,
			array(
				'count'      => 30,
				'expires_at' => time() + 30,
			),
			30
		);

		$disable = static function (): bool {
			return false;
		};
		add_filter( 'gatherpress_geocode_rate_limit_enabled', $disable );

		try {
			$method = new ReflectionMethod( Geocoding::class, 'check_rate_limit' );
			$method->setAccessible( true );
			$result = $method->invoke( Geocoding::get_instance() );
		} finally {
			remove_filter( 'gatherpress_geocode_rate_limit_enabled', $disable );
			delete_transient( 'gatherpress_geocode_rate_' . $user_id );
		}

		$this->assertNull(
			$result,
			'Disabling the rate limit must let an at-ceiling user through.'
		);
	}

	/**
	 * `check_rate_limit()` falls open (returns null, no transient
	 * touched) when there is no logged-in user. The REST permission
	 * callback already gates anonymous requests; this short-circuit is
	 * defense-in-depth against being invoked from an anonymous context
	 * with `user_id === 0`, which would otherwise key the bucket on 0
	 * and let an attacker share a global quota with all other anons.
	 *
	 * @covers ::check_rate_limit
	 *
	 * @return void
	 */
	public function test_rate_limit_skips_without_logged_in_user(): void {
		wp_set_current_user( 0 );
		delete_transient( 'gatherpress_geocode_rate_0' );

		$method = new ReflectionMethod( Geocoding::class, 'check_rate_limit' );
		$method->setAccessible( true );
		$result = $method->invoke( Geocoding::get_instance() );

		$this->assertNull( $result );
		$this->assertFalse(
			get_transient( 'gatherpress_geocode_rate_0' ),
			'No bucket should be created for the anonymous user.'
		);
	}

	/**
	 * Within the rate-limit ceiling, calls succeed normally — the
	 * counter increments, but the response is the regular geocode
	 * payload, not a 429.
	 *
	 * @covers ::check_rate_limit
	 *
	 * @return void
	 */
	public function test_rate_limit_allows_requests_under_ceiling(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		delete_transient( 'gatherpress_geocode_rate_' . $user_id );

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry' => array(
									'coordinates' => array( -73.935242, 40.73061 ),
								),
							),
						),
					)
				),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );

		$response = Geocoding::get_instance()->geocode_address( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$bucket = get_transient( 'gatherpress_geocode_rate_' . $user_id );
		$this->assertIsArray( $bucket );
		$this->assertSame( 1, $bucket['count'] );

		delete_transient( 'gatherpress_geocode_rate_' . $user_id );
	}

	/**
	 * `/geocode` and `/geocode/search` consume from the same per-user
	 * bucket — so a user typing into autocomplete and then saving a
	 * venue counts as one continuous flow.
	 *
	 * @covers ::check_rate_limit
	 *
	 * @return void
	 */
	public function test_rate_limit_bucket_is_shared_between_endpoints(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		delete_transient( 'gatherpress_geocode_rate_' . $user_id );

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry'   => array(
									'coordinates' => array( -73.935242, 40.73061 ),
								),
								'properties' => array( 'name' => 'Test' ),
							),
						),
					)
				),
			)
		);

		$instance = Geocoding::get_instance();

		$geocode_request = new WP_REST_Request( 'GET' );
		$geocode_request->set_param( 'address', '123 Main St' );
		$instance->geocode_address( $geocode_request );

		$search_request = new WP_REST_Request( 'GET' );
		$search_request->set_param( 'q', '123 Main St' );
		$instance->search_addresses( $search_request );

		$bucket = get_transient( 'gatherpress_geocode_rate_' . $user_id );
		$this->assertIsArray( $bucket );
		$this->assertSame(
			2,
			$bucket['count'],
			'Both endpoints must increment the same per-user bucket.'
		);

		delete_transient( 'gatherpress_geocode_rate_' . $user_id );
	}

	/**
	 * Once the ceiling has been hit, `check_rate_limit()` returns a 429
	 * response with a `Retry-After` header pointing at the remaining
	 * seconds in the window. Invoked directly on the protected method
	 * for deterministic coverage of the over-limit branch — the
	 * end-to-end "geocode_address returns 429" assertion is covered by
	 * `test_rate_limit_geocode_address_returns_429_when_exceeded`
	 * below.
	 *
	 * @covers ::check_rate_limit
	 *
	 * @return void
	 */
	public function test_rate_limit_returns_429_with_retry_after_when_exceeded(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		// Pre-fill the bucket at the ceiling. Window expires 30s from now.
		$expires_at = time() + 30;
		set_transient(
			'gatherpress_geocode_rate_' . $user_id,
			array(
				'count'      => 30,
				'expires_at' => $expires_at,
			),
			30
		);

		$method = new ReflectionMethod( Geocoding::class, 'check_rate_limit' );
		$method->setAccessible( true );
		$response = $method->invoke( Geocoding::get_instance() );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 429, $response->get_status() );

		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Retry-After', $headers );
		$this->assertGreaterThanOrEqual( 1, (int) $headers['Retry-After'] );
		$this->assertLessThanOrEqual( 30, (int) $headers['Retry-After'] );

		$data = $response->get_data();
		$this->assertSame( 'gatherpress_geocode_rate_limited', $data['code'] );

		delete_transient( 'gatherpress_geocode_rate_' . $user_id );
	}

	/**
	 * `geocode_address()` returns the 429 response from
	 * `check_rate_limit()` directly (early-returns before its own
	 * argument validation). Locks in the wiring at the public REST
	 * entry point.
	 *
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_rate_limit_geocode_address_returns_429_when_exceeded(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		set_transient(
			'gatherpress_geocode_rate_' . $user_id,
			array(
				'count'      => 30,
				'expires_at' => time() + 30,
			),
			30
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );

		$response = Geocoding::get_instance()->geocode_address( $request );

		$this->assertSame( 429, $response->get_status() );

		delete_transient( 'gatherpress_geocode_rate_' . $user_id );
	}

	/**
	 * `search_addresses()` returns the 429 response from
	 * `check_rate_limit()` directly (early-returns before its own
	 * argument validation). Locks in the wiring at the public REST
	 * entry point.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_rate_limit_search_addresses_returns_429_when_exceeded(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		set_transient(
			'gatherpress_geocode_rate_' . $user_id,
			array(
				'count'      => 30,
				'expires_at' => time() + 30,
			),
			30
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', '123 Main St' );

		$response = Geocoding::get_instance()->search_addresses( $request );

		$this->assertSame( 429, $response->get_status() );

		delete_transient( 'gatherpress_geocode_rate_' . $user_id );
	}

	/**
	 * Once the rate-limit window expires, the next request starts a
	 * fresh window with count = 1.
	 *
	 * @covers ::check_rate_limit
	 *
	 * @return void
	 */
	public function test_rate_limit_resets_after_window_expires(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		// Stale bucket: at ceiling, but window already expired one second ago.
		set_transient(
			'gatherpress_geocode_rate_' . $user_id,
			array(
				'count'      => 30,
				'expires_at' => time() - 1,
			),
			60
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry' => array(
									'coordinates' => array( -73.935242, 40.73061 ),
								),
							),
						),
					)
				),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );

		$response = Geocoding::get_instance()->geocode_address( $request );

		$this->assertSame(
			200,
			$response->get_status(),
			'Expired window must allow the request through and start a fresh bucket.'
		);

		$bucket = get_transient( 'gatherpress_geocode_rate_' . $user_id );
		$this->assertIsArray( $bucket );
		$this->assertSame( 1, $bucket['count'] );

		delete_transient( 'gatherpress_geocode_rate_' . $user_id );
	}

	/**
	 * `gatherpress_geocode_rate_limit_per_minute` overrides the default
	 * ceiling. Set it to 1 and the second request in the same window
	 * gets 429'd.
	 *
	 * @covers ::check_rate_limit
	 *
	 * @return void
	 */
	public function test_rate_limit_filter_changes_ceiling(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );
		delete_transient( 'gatherpress_geocode_rate_' . $user_id );

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry' => array(
									'coordinates' => array( -73.935242, 40.73061 ),
								),
							),
						),
					)
				),
			)
		);

		$ceiling_filter = static function (): int {
			return 1;
		};
		add_filter( 'gatherpress_geocode_rate_limit_per_minute', $ceiling_filter );

		try {
			$instance = Geocoding::get_instance();

			$first_request = new WP_REST_Request( 'GET' );
			$first_request->set_param( 'address', '123 Main St' );
			$first_response = $instance->geocode_address( $first_request );
			$this->assertSame( 200, $first_response->get_status() );

			$second_request = new WP_REST_Request( 'GET' );
			$second_request->set_param( 'address', '456 Oak St' );
			$second_response = $instance->geocode_address( $second_request );
			$this->assertSame(
				429,
				$second_response->get_status(),
				'With ceiling=1, the second request in the window must be rate-limited.'
			);
		} finally {
			remove_filter( 'gatherpress_geocode_rate_limit_per_minute', $ceiling_filter );
			delete_transient( 'gatherpress_geocode_rate_' . $user_id );
		}
	}

	/**
	 * Rate limiting is per-user — one user hitting the ceiling does
	 * NOT block other users on the same site.
	 *
	 * @covers ::check_rate_limit
	 *
	 * @return void
	 */
	public function test_rate_limit_is_isolated_per_user(): void {
		$flooder_id   = $this->factory->user->create( array( 'role' => 'editor' ) );
		$bystander_id = $this->factory->user->create( array( 'role' => 'editor' ) );

		// Flooder is at the ceiling.
		set_transient(
			'gatherpress_geocode_rate_' . $flooder_id,
			array(
				'count'      => 30,
				'expires_at' => time() + 30,
			),
			30
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode(
					array(
						'features' => array(
							array(
								'geometry' => array(
									'coordinates' => array( -73.935242, 40.73061 ),
								),
							),
						),
					)
				),
			)
		);

		// Bystander makes a request — should succeed.
		wp_set_current_user( $bystander_id );
		delete_transient( 'gatherpress_geocode_rate_' . $bystander_id );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'address', '123 Main St' );
		$response = Geocoding::get_instance()->geocode_address( $request );

		$this->assertSame(
			200,
			$response->get_status(),
			"One user's flooded bucket must not affect another user's quota."
		);

		delete_transient( 'gatherpress_geocode_rate_' . $flooder_id );
		delete_transient( 'gatherpress_geocode_rate_' . $bystander_id );
	}

	/**
	 * Capability gate is **intentionally** `edit_posts` (Contributor+).
	 * Rate limiting is the meaningful defense; tightening the cap was
	 * security theater (Photon is already public, anonymous-equivalent
	 * abuse vectors aren't unlocked by Contributor access). This test
	 * locks that decision in so anyone tightening the cap notices the
	 * test break and re-evaluates.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_rest_endpoints_keep_edit_posts_capability(): void {
		$instance = Geocoding::get_instance();
		$instance->register_endpoints();

		$routes = rest_get_server()->get_routes();
		foreach ( array( 'geocode', 'geocode/search' ) as $route_suffix ) {
			$key = '/' . GATHERPRESS_REST_NAMESPACE . '/' . $route_suffix;
			$this->assertArrayHasKey( $key, $routes, sprintf( 'Route %s should be registered.', $key ) );

			$contributor_id = $this->factory->user->create( array( 'role' => 'contributor' ) );
			wp_set_current_user( $contributor_id );

			foreach ( $routes[ $key ] as $route_def ) {
				$callback = $route_def['permission_callback'];
				$this->assertTrue(
					(bool) call_user_func( $callback ),
					sprintf( 'Contributor should pass the %s permission_callback (gated by `edit_posts`).', $key )
				);
			}
		}
	}
}
