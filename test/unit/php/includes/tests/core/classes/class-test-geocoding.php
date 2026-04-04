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

		$this->assertInstanceOf( 'WP_Error', $response, 'Failed to assert response is WP_Error for whitespace-only query.' );
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

		$this->assertInstanceOf( 'WP_Error', $response, 'Failed to assert response is WP_Error for search network error.' );
		$this->assertEquals( 'geocoding_search_failed', $response->get_error_code(), 'Failed to assert correct error code.' );
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

		$this->assertInstanceOf( 'WP_Error', $response, 'Failed to assert response is WP_Error for 503 search.' );
		$this->assertEquals( 'geocoding_search_failed', $response->get_error_code(), 'Failed to assert correct error code.' );
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
					'road'         => 'Main St',
					'city'         => 'Springfield',
					'state'        => 'Springfield',
					'postcode'     => '62701',
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
