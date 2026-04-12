<?php
/**
 * Class handles unit tests for GatherPress\Core\Geocoding.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Geocoding;
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
		$this->http_mock->reset();
		parent::tearDown();
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
		);

		$this->assert_hooks( $hooks, $instance );
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

		add_filter(
			'gatherpress_photon_api_url',
			static function (): string {
				return 'https://photon.example.test/api';
			}
		);

		$filtered = $this->invoke_geocoding_private( $instance, 'get_photon_api_url' );
		$this->assertSame( 'https://photon.example.test/api', $filtered );

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
}
