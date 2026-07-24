<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Provider\Google.
 *
 * @package GatherPress\Core\Venue\Map\Provider
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Venue\Map\Provider;

use GatherPress\Core\Settings;
use GatherPress\Core\Venue\Map\Provider\Google;
use GatherPress\Tests\Base;
use GdImage;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Google.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Provider\Google
 */
class Test_Google extends Base {

	/**
	 * PNG bytes returned by the HTTP short-circuit at the requested size.
	 *
	 * @var string
	 */
	private $map_png;

	/**
	 * Installs the HTTP short-circuit and seeds a test API key.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return;
		}

		$canvas = imagecreatetruecolor( 320, 240 );
		ob_start();
		imagepng( $canvas );
		$this->map_png = (string) ob_get_clean();
		unset( $canvas );

		add_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10, 3 );

		update_option(
			Settings::OPTION_NAME,
			array(
				'google_maps_api_key' => 'unit-test-static-maps-key',
			)
		);
	}

	/**
	 * Removes the filter on tear-down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10 );

		delete_option( Settings::OPTION_NAME );

		parent::tearDown();
	}

	/**
	 * Short-circuit Static Maps API requests with a canned PNG response.
	 *
	 * @param mixed  $preempt Default false.
	 * @param array  $args    HTTP args (unused).
	 * @param string $url     Request URL (unused).
	 *
	 * @return array
	 */
	public function short_circuit_static_map_requests( $preempt, $args, $url ): array {
		unset( $args, $url );

		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => $this->map_png,
			'headers'  => array(),
		);
	}

	/**
	 * Slug must be `google` — persisted in `map_platform`, filenames, and meta.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug_returns_google(): void {
		$this->assertSame( 'google', ( new Google() )->get_slug() );
	}

	/**
	 * Label is human-facing, lives in the Settings dropdown.
	 *
	 * @covers ::get_label
	 *
	 * @return void
	 */
	public function test_get_label_returns_translatable_string(): void {
		$this->assertSame( 'Google Maps', ( new Google() )->get_label() );
	}

	/**
	 * `render()` returns a finished GdImage at the requested dimensions
	 * when the Static Maps API responds with a valid PNG.
	 *
	 * @covers ::render
	 * @covers ::fetch_static_map
	 * @covers ::decode_png
	 *
	 * @return void
	 */
	public function test_render_returns_image(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$image = ( new Google() )->render( 40.7128, -74.0060, 12, 320, 240 );

		$this->assertInstanceOf( GdImage::class, $image );
		$this->assertSame( 320, imagesx( $image ) );
		$this->assertSame( 240, imagesy( $image ) );
	}

	/**
	 * `build_static_map_url()` targets the Static Maps endpoint with the
	 * expected query parameters for a roadmap render.
	 *
	 * @covers ::build_static_map_url
	 *
	 * @return void
	 */
	public function test_build_static_map_url_includes_required_params(): void {
		$url = Utility::invoke_hidden_method(
			new Google(),
			'build_static_map_url',
			array( 40.7128, -74.0060, 12, 320, 240, 1, 'roadmap', 'secret-key' )
		);

		$this->assertStringStartsWith( Google::STATIC_MAP_API_URL, $url );
		$this->assertStringContainsString( 'center=40.7128', $url );
		$this->assertStringContainsString( '-74.006', $url );
		$this->assertStringContainsString( 'zoom=12', $url );
		$this->assertStringContainsString( 'size=320x240', $url );
		$this->assertStringContainsString( 'scale=1', $url );
		$this->assertStringContainsString( 'maptype=roadmap', $url );
		$this->assertStringContainsString( 'markers=', $url );
		$this->assertStringContainsString( 'key=secret-key', $url );
	}

	/**
	 * Static Maps API requests pass through hybrid and terrain map types.
	 *
	 * @covers ::build_static_map_url
	 *
	 * @return void
	 */
	public function test_build_static_map_url_supports_hybrid_map_type(): void {
		$url = Utility::invoke_hidden_method(
			new Google(),
			'build_static_map_url',
			array( 40.7128, -74.0060, 12, 320, 240, 1, 'hybrid', 'secret-key' )
		);

		$this->assertStringContainsString( 'maptype=hybrid', $url );
	}

	/**
	 * Unsupported map types are coerced to roadmap before the API call.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_coerces_unsupported_map_type_to_roadmap(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$captured_url = '';
		$capture      = function ( $preempt, $args, $url ) use ( &$captured_url ) {
			unset( $preempt, $args );
			$captured_url = $url;

			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => $this->map_png,
				'headers'  => array(),
			);
		};

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10 );
		add_filter( 'pre_http_request', $capture, 10, 3 );

		$image = ( new Google() )->render(
			40.7128,
			-74.0060,
			12,
			320,
			240,
			1,
			'not-a-valid-type'
		);

		remove_filter( 'pre_http_request', $capture, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10, 3 );

		$this->assertInstanceOf( GdImage::class, $image );
		$this->assertStringContainsString( 'maptype=roadmap', $captured_url );
	}

	/**
	 * Google static maps declare all four common map types.
	 *
	 * @covers ::supported_map_types
	 *
	 * @return void
	 */
	public function test_supported_map_types_includes_all_common_types(): void {
		$this->assertSame(
			array( 'roadmap', 'satellite', 'hybrid', 'terrain' ),
			( new Google() )->supported_map_types()
		);
	}

	/**
	 * Unsupported density values are coerced back to 1 before the API call.
	 *
	 * @covers ::render
	 * @covers ::build_static_map_url
	 *
	 * @return void
	 */
	public function test_render_coerces_unsupported_density_to_one(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$captured_url = '';
		$capture      = function ( $preempt, $args, $url ) use ( &$captured_url ) {
			unset( $preempt, $args );
			$captured_url = $url;

			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => $this->map_png,
				'headers'  => array(),
			);
		};

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10 );
		add_filter( 'pre_http_request', $capture, 10, 3 );

		$image = ( new Google() )->render( 40.7128, -74.0060, 12, 320, 240, 99 );

		remove_filter( 'pre_http_request', $capture, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10, 3 );

		$this->assertInstanceOf( GdImage::class, $image );
		$this->assertStringContainsString( 'scale=1', $captured_url );
	}

	/**
	 * Missing API key short-circuits before any HTTP request is made.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_returns_null_when_api_key_is_missing(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$this->setExpectedIncorrectUsage( Google::class . '::render' );

		update_option( Settings::OPTION_NAME, array() );

		$this->assertNull( ( new Google() )->render( 40.7128, -74.0060, 12, 320, 240 ) );
	}

	/**
	 * HTTP failures from `wp_remote_get()` surface as null from `render()`.
	 *
	 * @covers ::render
	 * @covers ::fetch_static_map
	 *
	 * @return void
	 */
	public function test_render_returns_null_when_http_request_errors(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10 );
		$fail = static function () {
			return new \WP_Error( 'http_request_failed', 'Simulated transport failure.' );
		};
		add_filter( 'pre_http_request', $fail, 10 );

		$result = ( new Google() )->render( 40.7128, -74.0060, 12, 320, 240 );

		remove_filter( 'pre_http_request', $fail, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10, 3 );

		$this->assertNull( $result );
	}

	/**
	 * Non-200 Static Maps responses (e.g. API not enabled) return null.
	 *
	 * @covers ::render
	 * @covers ::fetch_static_map
	 *
	 * @return void
	 */
	public function test_render_returns_null_when_static_maps_api_returns_403(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10 );
		$forbidden = static function () {
			return array(
				'response' => array(
					'code'    => 403,
					'message' => 'Forbidden',
				),
				'body'     => 'This API is not activated on your API project.',
				'headers'  => array(),
			);
		};
		add_filter( 'pre_http_request', $forbidden, 10 );

		$result = ( new Google() )->render( 40.7128, -74.0060, 12, 320, 240 );

		remove_filter( 'pre_http_request', $forbidden, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10, 3 );

		$this->assertNull( $result );
	}

	/**
	 * A 200 response whose body is not a PNG cannot be decoded into GD.
	 *
	 * @covers ::render
	 * @covers ::decode_png
	 *
	 * @return void
	 */
	public function test_render_returns_null_when_response_body_is_not_png(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10 );
		$garbage = static function () {
			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => 'not a png',
				'headers'  => array(),
			);
		};
		add_filter( 'pre_http_request', $garbage, 10 );

		$result = ( new Google() )->render( 40.7128, -74.0060, 12, 320, 240 );

		remove_filter( 'pre_http_request', $garbage, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_static_map_requests' ), 10, 3 );

		$this->assertNull( $result );
	}

	/**
	 * `decode_png()` catches throwables from GD rather than fatalling.
	 *
	 * @covers ::decode_png
	 *
	 * @return void
	 */
	public function test_decode_png_catches_throwable_from_invalid_input(): void {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Test setup, restored in finally below.
		set_error_handler(
			static function ( int $errno, string $errstr ): bool {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only handler; the exception is caught immediately by decode_png().
				throw new \ErrorException( $errstr, 0, $errno );
			}
		);

		try {
			$result = Utility::invoke_hidden_method(
				new Google(),
				'decode_png',
				array( '' )
			);
		} finally {
			restore_error_handler();
		}

		$this->assertFalse( $result );
	}
}
