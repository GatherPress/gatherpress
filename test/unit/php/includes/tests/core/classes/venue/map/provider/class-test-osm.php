<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Provider\OSM.
 *
 * Covers the OSM-specific compositing pipeline: tile fetching,
 * render-time tile-budget short-circuiting, marker stamping, the Web
 * Mercator pixel projection, and the filterable tile URL template.
 *
 * @package GatherPress\Core\Venue\Map\Provider
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Venue\Map\Provider;

use ErrorException;
use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Map\Provider\OSM;
use GatherPress\Tests\Base;
use GdImage;
use PMC\Unit_Test\Utility;

/**
 * Class Test_OSM.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Provider\OSM
 */
class Test_OSM extends Base {

	/**
	 * Minimal valid 1×1 PNG used as a stand-in for every tile fetch.
	 * Keeping the payload tiny means tests stay fast and any code path
	 * that accidentally hits the real network shows up as a timeout.
	 *
	 * @var string
	 */
	private $tile_png;

	/**
	 * Installs the HTTP short-circuit on every test so no network is touched.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$png  = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42m';
		$png .= 'NkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fixed, trusted PNG payload for the tile-fetch stub.
		$this->tile_png = base64_decode( $png );

		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );
	}

	/**
	 * Removes the filter on tear-down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );

		parent::tearDown();
	}

	/**
	 * Short-circuit every HTTP request by returning the canned tile response.
	 *
	 * @param mixed  $preempt Default false.
	 * @param array  $args    HTTP args (unused).
	 * @param string $url     Request URL (unused).
	 * @return array
	 */
	public function short_circuit_tile_requests( $preempt, $args, $url ): array {
		unset( $args, $url );

		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => $this->tile_png,
			'headers'  => array(),
		);
	}

	/**
	 * Slug must be `osm` — it's the value persisted in `map_platform`,
	 * filename slugs, and post-meta keys, so changing it would silently
	 * orphan every existing PNG and stored descriptor on every site.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug_returns_osm(): void {
		$this->assertSame( 'osm', ( new OSM() )->get_slug() );
	}

	/**
	 * Label is human-facing, lives in the Settings dropdown.
	 *
	 * @covers ::get_label
	 *
	 * @return void
	 */
	public function test_get_label_returns_translatable_string(): void {
		$this->assertSame( 'OpenStreetMap', ( new OSM() )->get_label() );
	}

	/**
	 * OSM must surface the CartoDB + OpenStreetMap attribution — the
	 * tile licenses require it, and the front end pulls this string into
	 * the static-map markup. Verifying both providers appear catches
	 * accidental partial-rewrite regressions.
	 *
	 * @covers ::attribution_html
	 *
	 * @return void
	 */
	public function test_attribution_html_includes_required_providers(): void {
		$html = ( new OSM() )->attribution_html();

		$this->assertStringContainsString( 'OpenStreetMap', $html );
		$this->assertStringContainsString( 'CARTO', $html );
		$this->assertStringContainsString( 'openstreetmap.org/copyright', $html );
	}

	/**
	 * `fetch_tile()` substitutes `{z}/{x}/{y}` and returns the response
	 * body on a 200.
	 *
	 * @covers ::fetch_tile
	 *
	 * @return void
	 */
	public function test_fetch_tile_returns_body_on_success(): void {
		$bytes = ( new OSM() )->fetch_tile( 12, 34, 56, 'https://example.test/{z}/{x}/{y}.png' );

		$this->assertSame( $this->tile_png, $bytes );
	}

	/**
	 * `fetch_tile()` returns null when the response code is non-200 — a
	 * blank patch in the composite is preferable to aborting the whole
	 * render.
	 *
	 * @covers ::fetch_tile
	 *
	 * @return void
	 */
	public function test_fetch_tile_returns_null_for_non_200(): void {
		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );

		$callback = function () {
			return array(
				'response' => array(
					'code'    => 404,
					'message' => 'Not Found',
				),
				'body'     => '',
				'headers'  => array(),
			);
		};

		add_filter( 'pre_http_request', $callback );

		$bytes = ( new OSM() )->fetch_tile( 1, 2, 3, 'https://example.test/{z}/{x}/{y}.png' );

		remove_filter( 'pre_http_request', $callback );

		$this->assertNull( $bytes );
	}

	/**
	 * `fetch_tile()` returns null when the HTTP layer surfaces a WP_Error
	 * (DNS failure, timeout, etc.).
	 *
	 * @covers ::fetch_tile
	 *
	 * @return void
	 */
	public function test_fetch_tile_returns_null_on_wp_error(): void {
		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );

		$callback = function () {
			return new \WP_Error( 'http_request_failed', 'boom' );
		};

		add_filter( 'pre_http_request', $callback );

		$bytes = ( new OSM() )->fetch_tile( 1, 2, 3, 'https://example.test/{z}/{x}/{y}.png' );

		remove_filter( 'pre_http_request', $callback );

		$this->assertNull( $bytes );
	}

	/**
	 * `fetch_tile()` returns null when a 200 response has an empty body —
	 * the caller treats both shapes the same (skip the tile).
	 *
	 * @covers ::fetch_tile
	 *
	 * @return void
	 */
	public function test_fetch_tile_returns_null_for_empty_body(): void {
		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );

		$callback = function () {
			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => '',
				'headers'  => array(),
			);
		};

		add_filter( 'pre_http_request', $callback );

		$bytes = ( new OSM() )->fetch_tile( 1, 2, 3, 'https://example.test/{z}/{x}/{y}.png' );

		remove_filter( 'pre_http_request', $callback );

		$this->assertNull( $bytes );
	}

	/**
	 * `stamp_marker()` must paint pixels at the given coordinates —
	 * sampling the canvas before/after proves the marker actually got
	 * drawn rather than silently no-op'ing.
	 *
	 * @covers ::stamp_marker
	 *
	 * @return void
	 */
	public function test_stamp_marker_draws_at_coordinates(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$canvas = imagecreatetruecolor( 64, 64 );
		$bg     = imagecolorallocate( $canvas, 238, 238, 238 );
		imagefilledrectangle( $canvas, 0, 0, 63, 63, $bg );

		$before = imagecolorat( $canvas, 32, 32 );

		( new OSM() )->stamp_marker( $canvas, 32, 32, 1.0 );

		$after = imagecolorat( $canvas, 32, 32 );

		$this->assertNotSame( $before, $after );
	}

	/**
	 * Web Mercator projection: longitude 0 maps to the equator-meridian
	 * intersection, which sits exactly at the canvas mid-point at any
	 * zoom. zoom=2 → 4 tiles wide → 512 world pixels at TILE_SIZE 256.
	 *
	 * @covers ::lng_to_world_pixel
	 *
	 * @return void
	 */
	public function test_lng_to_world_pixel_matches_mercator(): void {
		$pixel = Utility::invoke_hidden_method( new OSM(), 'lng_to_world_pixel', array( 0.0, 2 ) );

		$this->assertEqualsWithDelta( 512.0, $pixel, 0.0001 );
	}

	/**
	 * Latitude 0 sits at the equator — half-way down the world raster on
	 * any zoom. zoom=2 → 1024 world pixels tall → equator at 512.
	 *
	 * @covers ::lat_to_world_pixel
	 *
	 * @return void
	 */
	public function test_lat_to_world_pixel_matches_mercator(): void {
		$pixel = Utility::invoke_hidden_method( new OSM(), 'lat_to_world_pixel', array( 0.0, 2 ) );

		$this->assertEqualsWithDelta( 512.0, $pixel, 0.0001 );
	}

	/**
	 * `gatherpress_static_map_tile_url` is the public hook for repointing
	 * at a different XYZ provider; verifying the value flows through the
	 * filter is what makes that hook a real contract.
	 *
	 * @covers ::get_tile_url_template
	 *
	 * @return void
	 */
	public function test_get_tile_url_template_is_filterable(): void {
		$override = 'https://tiles.example.test/{z}/{x}/{y}.png';

		add_filter(
			'gatherpress_static_map_tile_url',
			function () use ( $override ) {
				return $override;
			}
		);

		$template = Utility::invoke_hidden_method( new OSM(), 'get_tile_url_template' );

		remove_all_filters( 'gatherpress_static_map_tile_url' );

		$this->assertSame( $override, $template );
	}

	/**
	 * `render()` returns a finished, correctly-sized GdImage when the
	 * tile fetch succeeds. End-to-end smoke for the public contract.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_returns_canvas_at_requested_dimensions(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$canvas = ( new OSM() )->render( 40.7128, -74.0060, 12, 320, 240 );

		$this->assertInstanceOf( GdImage::class, $canvas );
		$this->assertSame( 320, imagesx( $canvas ) );
		$this->assertSame( 240, imagesy( $canvas ) );
	}

	/**
	 * `render()` for retina (density 2) doubles the canvas pixels —
	 * 320×240 logical = 640×480 actual.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_returns_doubled_canvas_for_retina_density(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$canvas = ( new OSM() )->render( 40.7128, -74.0060, 10, 320, 240, 2 );

		$this->assertInstanceOf( GdImage::class, $canvas );
		$this->assertSame( 640, imagesx( $canvas ) );
		$this->assertSame( 480, imagesy( $canvas ) );
	}

	/**
	 * Unsupported density values coerce back to 1× — `density=3` would
	 * paint at 3× canvas using only 2× tiles, which is just a wasteful
	 * upscale. Keep the canvas at 1× instead.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_coerces_unsupported_density_to_one(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$canvas = ( new OSM() )->render( 40.7128, -74.0060, 10, 320, 240, 3 );

		$this->assertInstanceOf( GdImage::class, $canvas );
		$this->assertSame( 320, imagesx( $canvas ) );
		$this->assertSame( 240, imagesy( $canvas ) );
	}

	/**
	 * When the requested zoom plus retina bump exceeds Map::ZOOM_MAX, the
	 * render is skipped (returns null) — orchestrator treats that as
	 * "no retina at this combo" without erroring.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_returns_null_when_retina_zoom_exceeds_max(): void {
		$canvas = ( new OSM() )->render( 40.7128, -74.0060, Map::ZOOM_MAX, 320, 240, 2 );

		$this->assertNull( $canvas );
	}

	/**
	 * When the wall-clock budget is exhausted between iterations, the
	 * render loop breaks out and the canvas comes back with the
	 * pre-painted gray background — no tiles fetched. Filter-driven so
	 * we don't have to wait COMPOSITE_TIME_BUDGET seconds.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_aborts_when_time_budget_exhausted(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$fetches = 0;
		$counter = function () use ( &$fetches ) {
			++$fetches;
			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => $this->tile_png,
				'headers'  => array(),
			);
		};
		// Negative budget puts the deadline in the past from the first
		// iteration, forcing `break 2` before any fetch runs.
		$budget = static fn() => -1;

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );
		add_filter( 'pre_http_request', $counter, 10 );
		add_filter( 'gatherpress_static_map_composite_time_budget', $budget );

		$canvas = ( new OSM() )->render( 37.3318, -122.0312, 15, 512, 256 );

		remove_filter( 'gatherpress_static_map_composite_time_budget', $budget );
		remove_filter( 'pre_http_request', $counter, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );

		$this->assertInstanceOf( GdImage::class, $canvas );
		$this->assertSame( 0, $fetches, 'No tiles should be fetched when the deadline is already in the past.' );
	}

	/**
	 * A failed tile fetch (HTTP error → fetch_tile returns null) is
	 * skipped and the loop continues — the canvas still comes back, just
	 * with a gray patch where the tile would have gone.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_continues_past_failed_tile_fetch(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );
		$fail = static function () {
			return new \WP_Error( 'boom', 'tile fetch failed' );
		};
		add_filter( 'pre_http_request', $fail, 10 );

		$canvas = ( new OSM() )->render( 37.3318, -122.0312, 15, 512, 256 );

		remove_filter( 'pre_http_request', $fail, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );

		$this->assertInstanceOf(
			GdImage::class,
			$canvas,
			'Failed fetch should leave the canvas intact rather than nulling the result.'
		);
	}

	/**
	 * A response body that isn't a valid PNG is skipped — `imagecreate-
	 * fromstring()` returns false and the loop moves on without aborting
	 * the whole composite.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_continues_past_invalid_png_body(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );
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

		$canvas = ( new OSM() )->render( 37.3318, -122.0312, 15, 512, 256 );

		remove_filter( 'pre_http_request', $garbage, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );

		$this->assertInstanceOf( GdImage::class, $canvas );
	}

	/**
	 * `decode_tile()` returns a GdImage when the bytes parse cleanly —
	 * happy path. Use `fetch_tile`'s short-circuit stub to source real
	 * PNG bytes so this isn't a tautology against a hand-crafted blob.
	 *
	 * @covers ::decode_tile
	 *
	 * @return void
	 */
	public function test_decode_tile_returns_image_for_valid_bytes(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		$image = Utility::invoke_hidden_method(
			new OSM(),
			'decode_tile',
			array( $this->tile_png )
		);

		$this->assertInstanceOf( GdImage::class, $image );
		imagedestroy( $image );
	}

	/**
	 * `decode_tile()` returns `false` when the decode throws.
	 *
	 * `imagecreatefromstring()` itself only emits an E_WARNING for
	 * malformed bytes on most PHP builds, not a throw — and PHPUnit's
	 * `convertWarningsToExceptions` setting isn't reliably active in the
	 * wp-env test runner. To exercise the catch branch deterministically
	 * we install our own error handler for the duration of the call,
	 * which rethrows any warning as an `ErrorException` (a `Throwable`).
	 * That's exactly the shape `decode_tile()` defends against in
	 * production when WordPress core or another plugin sets up an
	 * equivalent handler.
	 *
	 * @covers ::decode_tile
	 *
	 * @return void
	 */
	public function test_decode_tile_returns_false_when_bytes_throw(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Test setup, restored in finally below.
		set_error_handler(
			static function ( int $errno, string $errstr ): bool {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test-only handler; the exception is caught immediately by decode_tile().
				throw new ErrorException( $errstr, 0, $errno );
			}
		);

		try {
			$result = Utility::invoke_hidden_method(
				new OSM(),
				'decode_tile',
				array( '' )
			);
		} finally {
			restore_error_handler();
		}

		$this->assertFalse( $result );
	}
}
