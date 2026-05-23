<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Redirect.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Redirect;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Redirect.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Redirect
 * @group              endpoints
 */
class Test_Redirect extends Base {

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
		$instance = new Redirect( $slug, $callback );

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
		$instance = new Redirect( $slug, $callback );

		$this->assert_redirect_to(
			'https://example.org/',
			array( $instance, 'activate' )
		);
	}

	/**
	 * Activate with a callback that returns an empty URL must not redirect or
	 * register the allowed_redirect_hosts filter — the falsy guard at the top
	 * of `activate()` short-circuits the whole block.
	 *
	 * @covers ::activate
	 *
	 * @return void
	 */
	public function test_activate_with_empty_url_does_nothing(): void {
		$slug     = 'endpoint-redirect';
		$callback = static function () {
			return '';
		};
		$instance = new Redirect( $slug, $callback );

		remove_all_filters( 'allowed_redirect_hosts' );

		// Should return without calling wp_safe_redirect or hooking filters.
		$instance->activate();

		$this->assertFalse(
			has_filter( 'allowed_redirect_hosts', array( $instance, 'allowed_redirect_hosts' ) ),
			'Falsy callback should leave the allowed_redirect_hosts filter unhooked.'
		);
		$this->assertSame(
			'',
			Utility::get_hidden_property( $instance, 'url' ),
			'Falsy callback should leave the resolved url empty.'
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
		$instance = new Redirect( $slug, $callback );
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
