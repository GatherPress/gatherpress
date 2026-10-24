<?php
/**
 * Unit tests for GatherPress\Core\Traits\Has_Capabilities.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Traits;

use GatherPress\Core\Capability;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PasswordHash;
use WP_REST_Request;

/**
 * Class Test_Has_Capabilities.
 *
 * @coversDefaultClass \GatherPress\Core\Traits\Has_Capabilities
 */
class Test_Has_Capabilities extends Base {

	/**
	 * Verify Event and Venue default to the read capability on Capability.
	 *
	 * @since TBD
	 *
	 * @covers ::read_capability
	 *
	 * @return void
	 */
	public function test_read_capability(): void {
		$this->assertSame( Capability::READ_POST, Event::read_capability() );
		$this->assertSame( Capability::READ_POST, Venue::read_capability() );
	}

	/**
	 * Verify Event and Venue default to the edit capability on Capability.
	 *
	 * @since TBD
	 *
	 * @covers ::edit_capability
	 *
	 * @return void
	 */
	public function test_edit_capability(): void {
		$this->assertSame( Capability::EDIT_POST, Event::edit_capability() );
		$this->assertSame( Capability::EDIT_POST, Venue::edit_capability() );
	}

	/**
	 * A post password keeps a post's details from a viewer until they have the
	 * password, as a cookie or a REST argument, or can edit the post.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::is_hidden_by_password
	 *
	 * @return void
	 */
	public function test_is_hidden_by_password(): void {
		$protected = get_post(
			$this->factory->post->create(
				array(
					'post_type'     => Event::POST_TYPE,
					'post_password' => 'secret',
				)
			)
		);
		$open      = get_post( $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) ) );
		$venue     = get_post(
			$this->factory->post->create(
				array(
					'post_type'     => Venue::POST_TYPE,
					'post_password' => 'secret',
				)
			)
		);
		$request   = static function ( string $password ): WP_REST_Request {
			$request = new WP_REST_Request( 'GET', '/wp/v2/gatherpress_events' );

			$request->set_param( 'password', $password );

			return $request;
		};

		wp_set_current_user( 0 );

		$this->assertTrue( Event::is_hidden_by_password( $protected ), 'A protected event is hidden.' );
		$this->assertTrue( Venue::is_hidden_by_password( $venue ), 'A protected venue is hidden.' );
		$this->assertTrue(
			Event::is_hidden_by_password( $protected, $request( 'wrong' ) ),
			'A wrong REST password keeps it hidden.'
		);
		$this->assertFalse(
			Event::is_hidden_by_password( $protected, $request( 'secret' ) ),
			'The REST password shows it.'
		);
		$this->assertFalse( Event::is_hidden_by_password( $open ), 'An open event is never hidden.' );

		require_once ABSPATH . WPINC . '/class-phpass.php';

		// The cookie WordPress sets once a viewer enters the post password.
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = ( new PasswordHash( 8, true ) )->HashPassword( 'secret' );

		$with_cookie = Event::is_hidden_by_password( $protected );

		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		$this->assertFalse( $with_cookie, 'The password cookie shows it.' );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );

		$this->assertFalse( Event::is_hidden_by_password( $protected ), 'An Editor sees it.' );

		wp_set_current_user( 0 );
	}
}
