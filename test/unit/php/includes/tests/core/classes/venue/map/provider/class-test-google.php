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
			array( 40.7128, -74.0060, 12, 320, 240, 1, 'secret-key' )
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
}
