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

		// Mock the Nominatim API response.
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
	 * Test NOMINATIM_API_URL constant value.
	 *
	 * @return void
	 */
	public function test_nominatim_api_url_constant(): void {
		$this->assertEquals(
			'https://nominatim.openstreetmap.org/search',
			Geocoding::NOMINATIM_API_URL,
			'Failed to assert correct Nominatim API URL.'
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
			'nominatim.openstreetmap.org',
			$captured_url,
			'Failed to assert URL contains Nominatim domain.'
		);
		$this->assertStringContainsString(
			'format=geojson',
			$captured_url,
			'Failed to assert URL contains format parameter.'
		);
		$this->assertStringContainsString(
			'limit=1',
			$captured_url,
			'Failed to assert URL contains limit parameter.'
		);
		$this->assertStringContainsString(
			'accept-language=',
			$captured_url,
			'Failed to assert URL contains accept-language parameter.'
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
	 * Coverage for search_addresses with short query (returns empty suggestions).
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_short_query(): void {
		$instance = Geocoding::get_instance();
		$request  = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'a' );

		$response = $instance->search_addresses( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );
		$data = $response->get_data();
		$this->assertIsArray( $data['suggestions'] );
		$this->assertCount( 0, $data['suggestions'] );
	}

	/**
	 * Coverage for search_addresses with successful JSON array response.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_success(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '40.7128',
				'lon'     => '-74.0060',
				'address' => array(
					'house_number' => '1453',
					'road'         => '3rd Avenue',
					'city'         => 'New York',
					'state'        => 'New York',
					'postcode'     => '10028',
					'country'      => 'United States',
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
		$this->assertArrayNotHasKey( 'address', $suggestion );
		$this->assertSame(
			'1453 3rd Avenue, New York, 10028',
			$suggestion['label']
		);
		$this->assertSame( '40.7128', $suggestion['latitude'] );
		$this->assertSame( '-74.0060', $suggestion['longitude'] );
	}

	/**
	 * Search uses display_name when structured address parts are empty; strips POI prefix.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_uses_display_name_and_strips_poi_prefix(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '48.8566',
				'lon'          => '2.3522',
				'display_name' => 'Some Café, Paris, France',
				'address'      => array(
					'amenity' => 'Some Café',
					'country' => 'France',
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

		$this->assertInstanceOf( WP_REST_Response::class, $response, 'Failed to assert response is WP_REST_Response.' );
		$data       = $response->get_data();
		$suggestion = $data['suggestions'][0];
		$this->assertSame( 'Paris, France', $suggestion['label'] );
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
	 * Search URL uses json format, limit, and addressdetails (captured from request).
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_url_params(): void {
		$instance      = Geocoding::get_instance();
		$captured_url  = '';
		$mock_response = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'city' => 'X',
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
			'nominatim.openstreetmap.org',
			$captured_url,
			'Failed to assert URL contains Nominatim domain.'
		);
		$this->assertStringContainsString( 'format=json', $captured_url, 'Failed to assert format=json.' );
		$this->assertStringContainsString( 'limit=5', $captured_url, 'Failed to assert limit=5.' );
		$this->assertStringContainsString( 'addressdetails=1', $captured_url, 'Failed to assert addressdetails=1.' );
		$this->assertStringContainsString( 'accept-language=', $captured_url, 'Failed to assert accept-language.' );
		$this->assertStringContainsString( 'q=Test', $captured_url, 'Failed to assert query string.' );
	}

	/**
	 * Non-array rows and rows without lat/lon are skipped; valid row still returned.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_skips_malformed_rows(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			'not-an-object',
			array(
				'lat' => '10',
				// Missing lon.
			),
			array(
				'lat'     => '40.7128',
				'lon'     => '-74.0060',
				'address' => array(
					'city' => 'NYC',
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
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'road'     => 'Main St',
					'city'     => 'Springfield',
					'state'    => 'Springfield',
					'postcode' => '62701',
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
	 * Street line can use pedestrian when road is absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_street_line_uses_pedestrian(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'house_number' => '10',
					'pedestrian'   => 'Promenade',
					'city'         => 'Nice',
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
		$request->set_param( 'q', 'Nice walk' );

		$response = $instance->search_addresses( $request );

		$data = $response->get_data();
		$this->assertStringContainsString(
			'10 Promenade',
			$data['suggestions'][0]['label'],
			'Failed to assert pedestrian used in street line.'
		);
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
			array(
				'lat'          => '1',
				'lon'          => '2',
				'address'      => array(),
				'display_name' => '',
			),
			array(
				'lat'     => '3',
				'lon'     => '4',
				'address' => array(
					'city' => 'Kept City',
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
	 * Non-array `address` falls back to `display_name` for labeling.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_non_array_address_uses_display_name(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '1',
				'lon'          => '2',
				'address'      => 'not-an-array',
				'display_name' => 'Berlin, Germany',
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Berlin' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertSame( 'Berlin, Germany', $data['suggestions'][0]['label'] );
	}

	/**
	 * When there is no `address` key, `display_name` alone is used (no POI keys to strip).
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_display_name_only_without_address_key(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '9',
				'lon'          => '9',
				'display_name' => 'Only Display, Somewhere',
			),
		);

		$this->http_mock->mock(
			'*',
			array(
				'body' => wp_json_encode( $mock_body ),
			)
		);

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'q', 'Only' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertSame( 'Only Display, Somewhere', $data['suggestions'][0]['label'] );
	}

	/**
	 * With no POI fields in `address`, display_name is returned unchanged from POI stripping.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_poi_strip_with_no_poi_fields_returns_display_name(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '1',
				'lon'          => '2',
				'display_name' => 'Road 1, Town, 12345',
				'address'      => array(
					'country' => 'US',
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
		$request->set_param( 'q', 'Road' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertSame( 'Road 1, Town, 12345', $data['suggestions'][0]['label'] );
	}

	/**
	 * Leading empty segment in display_name leaves the string unchanged.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_poi_leading_empty_segment_unchanged(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '1',
				'lon'          => '2',
				'display_name' => ', Paris, France',
				'address'      => array(
					'amenity' => 'Café',
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

		$this->assertSame( ', Paris, France', $data['suggestions'][0]['label'] );
	}

	/**
	 * When the first comma segment does not match any POI value, display_name is unchanged.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_poi_first_segment_no_match_returns_full_display_name(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '1',
				'lon'          => '2',
				'display_name' => 'Random Block, Paris, France',
				'address'      => array(
					'amenity' => 'Different POI',
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
		$request->set_param( 'q', 'Random' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertSame( 'Random Block, Paris, France', $data['suggestions'][0]['label'] );
	}

	/**
	 * POI stripping uses `shop` and removes the matching first segment.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_poi_shop_key_strips_first_segment(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '1',
				'lon'          => '2',
				'display_name' => 'Corner Store, Lyon, France',
				'address'      => array(
					'shop' => 'Corner Store',
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
		$request->set_param( 'q', 'Corner' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringNotContainsString(
			'Corner Store, Lyon',
			$data['suggestions'][0]['label'],
			'Failed to assert first segment removed when matching shop.'
		);
		$this->assertStringContainsString( 'Lyon', $data['suggestions'][0]['label'] );
	}

	/**
	 * Street line can use `path` when road and pedestrian are absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_street_line_uses_path_key(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'path' => 'Trailhead Path',
					'city' => 'Trail Town',
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
		$request->set_param( 'q', 'Trail' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString(
			'Trailhead Path',
			$data['suggestions'][0]['label'],
			'Failed to assert path used in street line.'
		);
	}

	/**
	 * Street line with house number only (no road).
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_street_line_house_only(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'house_number' => '42',
					'city'         => 'Num City',
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
		$request->set_param( 'q', 'Num' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringStartsWith(
			'42, Num City',
			$data['suggestions'][0]['label'],
			'Failed to assert house-only street line.'
		);
	}

	/**
	 * Street line with road only (no house number).
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_street_line_road_only(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'road' => 'Elm Street',
					'city' => 'Elm City',
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
		$request->set_param( 'q', 'Elm' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringStartsWith(
			'Elm Street, Elm City',
			$data['suggestions'][0]['label'],
			'Failed to assert road-only street line.'
		);
	}

	/**
	 * Locality prefers `village` when city keys are absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_locality_from_village(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'road'    => 'High St',
					'village' => 'Littleton',
					'state'   => 'VT',
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
		$request->set_param( 'q', 'Little' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Littleton', $data['suggestions'][0]['label'] );
		$this->assertStringContainsString( 'VT', $data['suggestions'][0]['label'] );
	}

	/**
	 * Locality can come from `hamlet`.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_locality_from_hamlet(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'hamlet' => 'Tiny Hamlet',
					'county' => 'Big County',
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
		$request->set_param( 'q', 'Tiny' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Tiny Hamlet', $data['suggestions'][0]['label'] );
		$this->assertStringContainsString( 'Big County', $data['suggestions'][0]['label'] );
	}

	/**
	 * Region can come from `county` when state is absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_region_from_county(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'city'   => 'Metro',
					'county' => 'Metro County',
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
		$request->set_param( 'q', 'Metro' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Metro', $data['suggestions'][0]['label'] );
		$this->assertStringContainsString( 'Metro County', $data['suggestions'][0]['label'] );
	}

	/**
	 * Postcode-only trimming: whitespace postcode still yields a label part then filters empties.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_postcode_whitespace_trims_to_empty_in_parts(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'road'     => 'Oak',
					'city'     => 'Oakville',
					'postcode' => '   ',
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
		$request->set_param( 'q', 'Oak' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Oak', $data['suggestions'][0]['label'] );
		$this->assertStringContainsString( 'Oakville', $data['suggestions'][0]['label'] );
	}

	/**
	 * Duplicate POI values are de-duplicated before matching the first segment.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_poi_duplicate_values_still_strip_first_segment(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '1',
				'lon'          => '2',
				'display_name' => 'Twin Shop, City, Land',
				'address'      => array(
					'shop'    => 'Twin Shop',
					'tourism' => 'Twin Shop',
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
		$request->set_param( 'q', 'Twin' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringNotContainsString(
			'Twin Shop, City',
			$data['suggestions'][0]['label'],
			'Failed to assert duplicate POI values still allow strip.'
		);
	}

	/**
	 * Additional POI keys (office, leisure) contribute to strip list.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_poi_office_key_strips_first_segment(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '1',
				'lon'          => '2',
				'display_name' => 'Office Tower, District, Country',
				'address'      => array(
					'office' => 'Office Tower',
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
		$request->set_param( 'q', 'Office' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringNotContainsString(
			'Office Tower, District',
			$data['suggestions'][0]['label'],
			'Failed to assert office POI stripped.'
		);
	}

	/**
	 * `municipality` is used as locality when city keys are absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_locality_from_municipality(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'road'          => 'Main',
					'municipality'  => 'Muni Name',
					'postcode'      => '11111',
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
		$request->set_param( 'q', 'Muni' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Muni Name', $data['suggestions'][0]['label'] );
		$this->assertStringContainsString( '11111', $data['suggestions'][0]['label'] );
	}

	/**
	 * `suburb` is used as locality when earlier keys are absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_locality_from_suburb(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'suburb' => 'West End',
					'state'  => 'CA',
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
		$request->set_param( 'q', 'West' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'West End', $data['suggestions'][0]['label'] );
	}

	/**
	 * `residential` is used for street line when road-like keys are absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_street_line_uses_residential_key(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'residential' => 'Crescent Close',
					'city'        => 'Suburbia',
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
		$request->set_param( 'q', 'Crescent' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString(
			'Crescent Close',
			$data['suggestions'][0]['label'],
			'Failed to assert residential used in street line.'
		);
	}

	/**
	 * `footway` is used for street line when earlier keys are absent.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_street_line_uses_footway_key(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'footway' => 'River Walk',
					'town'    => 'River Town',
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
		$request->set_param( 'q', 'River' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString(
			'River Walk',
			$data['suggestions'][0]['label'],
			'Failed to assert footway used in street line.'
		);
	}

	/**
	 * Remaining POI keys (craft, club, aerialway, historic, building, man_made) are collected.
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_poi_historic_key_strips_first_segment(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'          => '1',
				'lon'          => '2',
				'display_name' => 'Old Fort, Plains, Nation',
				'address'      => array(
					'historic' => 'Old Fort',
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
		$request->set_param( 'q', 'Old' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringNotContainsString(
			'Old Fort, Plains',
			$data['suggestions'][0]['label'],
			'Failed to assert historic POI stripped.'
		);
	}

	/**
	 * `region` from `region` key (not duplicate of locality).
	 *
	 * @covers ::search_addresses
	 *
	 * @return void
	 */
	public function test_search_addresses_region_from_region_key(): void {
		$instance = Geocoding::get_instance();

		$mock_body = array(
			array(
				'lat'     => '1',
				'lon'     => '2',
				'address' => array(
					'city'   => 'Portland',
					'region' => 'Pacific Northwest',
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
		$request->set_param( 'q', 'Port' );

		$response = $instance->search_addresses( $request );
		$data     = $response->get_data();

		$this->assertStringContainsString( 'Portland', $data['suggestions'][0]['label'] );
		$this->assertStringContainsString( 'Pacific Northwest', $data['suggestions'][0]['label'] );
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
