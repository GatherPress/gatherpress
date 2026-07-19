<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Response\Identity.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response;

use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Tests\Base;
use InvalidArgumentException;

/**
 * Class Test_Identity.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Response\Identity
 */
class Test_Identity extends Base {

	/**
	 * Every identity type accepts a well-formed value.
	 *
	 * @covers ::__construct
	 * @covers ::assert_valid
	 *
	 * @return void
	 */
	public function test_valid_identities_construct(): void {
		$user_id = $this->factory->user->create();

		$email = new Identity( Identity_Type::EMAIL, 'valid@example.test' );
		$this->assertSame( 'valid@example.test', $email->value );

		$url = new Identity( Identity_Type::URL, 'https://example.test/profile' );
		$this->assertSame( Identity_Type::URL, $url->type );

		$user = new Identity( Identity_Type::WP_USER_ID, $user_id );
		$this->assertSame( $user_id, $user->value );

		$external = new Identity( Identity_Type::EXTERNAL_ID, 12345 );
		$this->assertSame( 12345, $external->value );
	}

	/**
	 * Malformed email values are rejected.
	 *
	 * @covers ::assert_valid
	 *
	 * @return void
	 */
	public function test_invalid_email_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid email.' );

		new Identity( Identity_Type::EMAIL, 'not-an-email' );
	}

	/**
	 * Malformed URL values are rejected.
	 *
	 * @covers ::assert_valid
	 *
	 * @return void
	 */
	public function test_invalid_url_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid URL.' );

		new Identity( Identity_Type::URL, 'not a url' );
	}

	/**
	 * User identities require an integer value.
	 *
	 * @covers ::assert_valid
	 *
	 * @return void
	 */
	public function test_non_int_user_id_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid ID.' );

		new Identity( Identity_Type::WP_USER_ID, 'seven' );
	}

	/**
	 * User identities must reference an existing account.
	 *
	 * @covers ::assert_valid
	 *
	 * @return void
	 */
	public function test_missing_user_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'User does not exist.' );

		new Identity( Identity_Type::WP_USER_ID, 99999999 );
	}

	/**
	 * External identities require an integer value.
	 *
	 * @covers ::assert_valid
	 *
	 * @return void
	 */
	public function test_non_int_external_id_throws(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid ID.' );

		new Identity( Identity_Type::EXTERNAL_ID, 'abc-123' );
	}
}
