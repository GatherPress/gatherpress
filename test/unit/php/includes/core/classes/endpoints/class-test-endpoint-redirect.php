<?php
/**
 * Class handles unit tests for GatherPress\Core\Endpoints\Endpoint_Redirect.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Endpoints;

use GatherPress\Core\Endpoints\Endpoint_Redirect;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Endpoint_Redirect.
 *
 * @coversDefaultClass \GatherPress\Core\Endpoints\Endpoint_Redirect
 * @group              endpoints
 */
class Test_Endpoint_Redirect extends Base {
	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$slug     = 'endpoint-redirect';
		$callback = function () {
			return 'https://example.org/';
		};
		$instance = new Endpoint_Redirect( $slug, $callback );

		$this->assertSame(
			$slug,
			$instance->slug,
			'Failed to assert, that the endpoint slug is persisted.'
		);

		$this->assertSame(
			$callback,
			Utility::get_hidden_property( $instance, 'callback' ),
			'Failed to assert, that the endpoint callback is persisted.'
		);
	}

	/**
	 * Coverage for activate method.
	 *
	 * @covers ::activate
	 *
	 * @return void
	 */
	public function test_activate(): void {
		$slug     = 'endpoint-redirect';
		$callback = function () {
			return 'https://example.org/';
		};
		$instance = new Endpoint_Redirect( $slug, $callback );

		$this->assert_redirect_to(
			'https://example.org/',
			array( $instance, 'activate' )
		);
	}

	/**
	 * Coverage for allowed_redirect_hosts method.
	 *
	 * @covers ::allowed_redirect_hosts
	 *
	 * @return void
	 */
	public function test_allowed_redirect_hosts(): void {
		$slug     = 'endpoint-redirect';
		$callback = function () {
			return 'https://example.org/';
		};
		$instance = new Endpoint_Redirect( $slug, $callback );
		Utility::set_and_get_hidden_property( $instance, 'url', ( $callback )() );

		$this->assertSame(
			array(
				'apples',
				'oranges',
				'example.org',
			),
			$instance->allowed_redirect_hosts( array( 'apples', 'oranges' ) ),
			'Failed to assert, that the redirect url got merged into allowed_redirect_hosts.'
		);
	}
}
