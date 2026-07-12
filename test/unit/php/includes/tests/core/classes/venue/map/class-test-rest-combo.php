<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Rest_Combo.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Venue\Map\Rest_Combo;
use GatherPress\Tests\Base;

/**
 * Class Test_Rest_Combo.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Rest_Combo
 */
class Test_Rest_Combo extends Base {

	/**
	 * Coverage for route_args — shared REST combo fields.
	 *
	 * @covers ::route_args
	 *
	 * @return void
	 */
	public function test_route_args_defines_combo_fields(): void {
		$args = Rest_Combo::route_args();

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
			Rest_Combo::parse_request( $empty )
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
			Rest_Combo::parse_request( $full )
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
			Rest_Combo::parse_request( $zero_zoom )
		);
	}
}
