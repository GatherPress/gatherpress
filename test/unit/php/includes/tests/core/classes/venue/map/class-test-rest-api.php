<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Rest_Api.
 *
 * Covers the venue-map REST surface: route registration, the shared combo
 * argument definitions, request parsing, and the regenerate endpoint
 * handler. The map domain operations the handler dispatches to are covered
 * in the Map orchestrator's own test class.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Venue\Map\Map;
use GatherPress\Core\Venue\Map\Rest_Api;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Rest_Api.
 *
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Rest_Api
 */
class Test_Rest_Api extends Base {

	/**
	 * Minimal valid 1×1 PNG used as a stand-in for every tile fetch.
	 *
	 * Keeping the payload small keeps tests fast and makes it obvious when
	 * a code path accidentally hits the real network (which would time out).
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
	 * Removes the filter and any leftover files on tear-down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );

		$dirs     = wp_get_upload_dir();
		$base_dir = trailingslashit( $dirs['basedir'] ) . Map::UPLOADS_SUBDIR;

		if ( is_dir( $base_dir ) ) {
			foreach ( (array) glob( $base_dir . '/*.png' ) as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}

		parent::tearDown();
	}

	/**
	 * Short-circuit every HTTP request by returning the canned tile response.
	 *
	 * @param mixed  $preempt Default false.
	 * @param array  $args    HTTP args (unused).
	 * @param string $url     Request URL (unused).
	 *
	 * @return array Mocked WP HTTP response.
	 */
	public function short_circuit_tile_requests( $preempt, $args, $url ): array {
		unset( $args, $url );

		return array(
			'headers'  => array(),
			'body'     => $this->tile_png,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Rest_Api::get_instance();
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
	 * Coverage for route_args — shared REST combo fields.
	 *
	 * @covers ::route_args
	 *
	 * @return void
	 */
	public function test_route_args_defines_combo_fields(): void {
		$args = Rest_Api::route_args();

		$this->assertArrayHasKey( 'zoom', $args );
		$this->assertArrayHasKey( 'width', $args );
		$this->assertArrayHasKey( 'height', $args );
		$this->assertArrayHasKey( 'aspect_ratio', $args );
		$this->assertArrayHasKey( 'map_type', $args );
		$this->assertArrayHasKey( 'ensure_only', $args );
		$this->assertFalse( $args['ensure_only']['default'] );

		$aspect_validate = $args['aspect_ratio']['validate_callback'];
		$this->assertTrue( $aspect_validate( '' ) );
		$this->assertTrue( $aspect_validate( null ) );
		$this->assertTrue( $aspect_validate( '16/9' ) );
		$this->assertFalse( $aspect_validate( 'not-a-ratio' ) );

		$type_validate = $args['map_type']['validate_callback'];
		$this->assertTrue( $type_validate( '' ) );
		$this->assertTrue( $type_validate( null ) );
		$this->assertTrue( $type_validate( 'roadmap' ) );
		$this->assertTrue( $type_validate( 'satellite' ) );
		$this->assertTrue( $type_validate( 'hybrid' ) );
		$this->assertTrue( $type_validate( 'terrain' ) );
		$this->assertFalse( $type_validate( 'invalid' ) );
	}

	/**
	 * Coverage for parse_request — normalizes REST combo params.
	 *
	 * @covers ::parse_request
	 *
	 * @return void
	 */
	public function test_parse_request_normalizes_request_params(): void {
		$empty = new \WP_REST_Request( 'POST', '/test' );
		$this->assertSame(
			array(
				'zoom'         => null,
				'width'        => null,
				'height'       => null,
				'aspect_ratio' => '',
				'map_type'     => '',
			),
			Rest_Api::parse_request( $empty )
		);

		$full = new \WP_REST_Request( 'POST', '/test' );
		$full->set_param( 'zoom', 15 );
		$full->set_param( 'width', 800 );
		$full->set_param( 'height', 400 );
		$full->set_param( 'aspect_ratio', '16/9' );
		$full->set_param( 'map_type', 'hybrid' );
		$this->assertSame(
			array(
				'zoom'         => 15,
				'width'        => 800,
				'height'       => 400,
				'aspect_ratio' => '16/9',
				'map_type'     => 'hybrid',
			),
			Rest_Api::parse_request( $full )
		);

		$zero_zoom = new \WP_REST_Request( 'POST', '/test' );
		$zero_zoom->set_param( 'zoom', 0 );
		$zero_zoom->set_param( 'width', 0 );
		$this->assertSame(
			array(
				'zoom'         => null,
				'width'        => 0,
				'height'       => null,
				'aspect_ratio' => '',
				'map_type'     => '',
			),
			Rest_Api::parse_request( $zero_zoom )
		);
	}

	/**
	 * The POST /venue/{id}/regenerate-map endpoint requires edit_post on
	 * the target venue — anonymous callers receive 401/403.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_rest_regenerate_requires_edit_post(): void {
		Rest_Api::get_instance()->register_endpoints();

		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		wp_set_current_user( 0 );

		$request  = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$response = rest_do_request( $request );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
		$this->assertLessThan( 500, $response->get_status() );
	}

	/**
	 * Happy-path REST call: an editor-level user regenerates a venue with
	 * usable coordinates and gets the fresh descriptor map in the response.
	 *
	 * @covers ::register_endpoints
	 * @covers ::rest_regenerate
	 *
	 * @return void
	 */
	public function test_rest_regenerate_returns_fresh_descriptors(): void {
		Rest_Api::get_instance()->register_endpoints();

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta( $post_id, 'gatherpress_address', '1 Infinite Loop' );
		add_post_meta( $post_id, 'gatherpress_latitude', '37.3318' );
		add_post_meta( $post_id, 'gatherpress_longitude', '-122.0312' );

		wp_set_current_user( $editor_id );

		$request  = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'descriptors', $data );
		$this->assertSame( '', $data['reason'] );

		$default_key = sprintf(
			'%dx%dx%dx%s',
			Map::DEFAULT_ZOOM,
			Map::DEFAULT_HEIGHT * 2,
			Map::DEFAULT_HEIGHT,
			Map::DEFAULT_MAP_TYPE
		);
		$this->assertArrayHasKey( 'osm', (array) $data['descriptors'] );
		$this->assertArrayHasKey( $default_key, (array) $data['descriptors']['osm'] );
	}

	/**
	 * Ensure_only lazily generates one combo without wiping other descriptors.
	 *
	 * @covers ::register_endpoints
	 * @covers ::rest_regenerate
	 *
	 * @return void
	 */
	public function test_rest_regenerate_ensure_only_generates_requested_combo(): void {
		Rest_Api::get_instance()->register_endpoints();

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta( $post_id, 'gatherpress_address', '1 Infinite Loop' );
		add_post_meta( $post_id, 'gatherpress_latitude', '37.3318' );
		add_post_meta( $post_id, 'gatherpress_longitude', '-122.0312' );

		wp_set_current_user( $editor_id );

		$request = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$request->set_param( 'zoom', 15 );
		$request->set_param( 'width', 800 );
		$request->set_param( 'height', 400 );
		$request->set_param( 'map_type', 'hybrid' );
		$request->set_param( 'ensure_only', true );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertSame( '', $data['reason'] );
		$this->assertArrayHasKey(
			'15x800x400xhybrid',
			(array) $data['descriptors']['osm']
		);
	}

	/**
	 * When the venue has no address, the REST endpoint returns a 200 with
	 * a structured reason so the client can render the appropriate
	 * placeholder rather than a generic error state.
	 *
	 * @covers ::rest_regenerate
	 *
	 * @return void
	 */
	public function test_rest_regenerate_reports_no_address_reason(): void {
		Rest_Api::get_instance()->register_endpoints();

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		wp_set_current_user( $editor_id );

		$request  = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'no_address', $data['reason'] );
	}

	/**
	 * Reports a `generation_failed` reason when the venue has coordinates
	 * but every combo's PNG write fails. Simulated here by putting the
	 * uploads dir in an error state so `save_image` returns null and the
	 * regenerate() call comes back with an empty descriptor map.
	 *
	 * @covers ::rest_regenerate
	 *
	 * @return void
	 */
	public function test_rest_regenerate_reports_generation_failed_when_saves_fail(): void {
		Rest_Api::get_instance()->register_endpoints();

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta( $post_id, 'gatherpress_address', '1 Infinite Loop' );
		add_post_meta( $post_id, 'gatherpress_latitude', '37.3318' );
		add_post_meta( $post_id, 'gatherpress_longitude', '-122.0312' );

		wp_set_current_user( $editor_id );

		// Force save_image to fail for every combo so regenerate() returns
		// an empty array and the REST handler enters the generation_failed
		// branch.
		$force_error = static function ( $dirs ) {
			$dirs['error'] = 'Simulated uploads failure.';
			return $dirs;
		};
		add_filter( 'upload_dir', $force_error );

		$request = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$request->set_param( 'zoom', 15 );
		$request->set_param( 'width', 800 );
		$request->set_param( 'height', 400 );
		$request->set_param( 'aspect_ratio', '2/1' );

		$response = rest_do_request( $request );

		remove_filter( 'upload_dir', $force_error );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'generation_failed', $data['reason'] );
	}

	/**
	 * Rejects a malformed `aspect_ratio` parameter at the REST boundary
	 * with a 400 instead of silently falling back. Empty / valid values
	 * (slash or colon form) pass through.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_rest_regenerate_aspect_ratio_validator(): void {
		Rest_Api::get_instance()->register_endpoints();

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta( $post_id, 'gatherpress_address', '1 Infinite Loop' );
		add_post_meta( $post_id, 'gatherpress_latitude', '37.3318' );
		add_post_meta( $post_id, 'gatherpress_longitude', '-122.0312' );

		wp_set_current_user( $editor_id );

		$invalid = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$invalid->set_param( 'aspect_ratio', 'not-a-ratio' );

		$this->assertSame(
			400,
			rest_do_request( $invalid )->get_status(),
			'A malformed aspect_ratio string is rejected at the REST boundary.'
		);

		$valid_slash = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$valid_slash->set_param( 'aspect_ratio', '16/9' );
		$this->assertSame(
			200,
			rest_do_request( $valid_slash )->get_status(),
			'Slash-form aspect ratio passes.'
		);

		$valid_colon = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$valid_colon->set_param( 'aspect_ratio', '4:3' );
		$this->assertSame(
			200,
			rest_do_request( $valid_colon )->get_status(),
			'Colon-form aspect ratio passes.'
		);

		$empty = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$empty->set_param( 'aspect_ratio', '' );
		$this->assertSame(
			200,
			rest_do_request( $empty )->get_status(),
			'Empty aspect ratio is treated as "use server default" and passes.'
		);

		// Degenerate `0/X` / `X/0` values that CSS would treat as auto
		// must still be rejected — the block never emits a zero side.
		$zero_numerator = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$zero_numerator->set_param( 'aspect_ratio', '0/9' );
		$this->assertSame(
			400,
			rest_do_request( $zero_numerator )->get_status(),
			'Zero numerator is rejected by the tightened [1-9] rule.'
		);

		$zero_denominator = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$zero_denominator->set_param( 'aspect_ratio', '9/0' );
		$this->assertSame(
			400,
			rest_do_request( $zero_denominator )->get_status(),
			'Zero denominator is rejected by the tightened [1-9] rule.'
		);
	}

	/**
	 * Reports the `awaiting_geocode` reason when the venue has an address
	 * but no resolved coordinates yet.
	 *
	 * @covers ::rest_regenerate
	 *
	 * @return void
	 */
	public function test_rest_regenerate_reports_awaiting_geocode_reason(): void {
		Rest_Api::get_instance()->register_endpoints();

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id   = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta( $post_id, 'gatherpress_address', 'Somewhere' );
		add_post_meta( $post_id, 'gatherpress_latitude', '' );
		add_post_meta( $post_id, 'gatherpress_longitude', '' );

		wp_set_current_user( $editor_id );

		$request  = new \WP_REST_Request(
			'POST',
			sprintf( '/%s/venue/%d/regenerate-map', GATHERPRESS_REST_NAMESPACE, $post_id )
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'awaiting_geocode', $data['reason'] );
	}
}
