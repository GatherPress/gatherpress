<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Response\Data.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response;

use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Tests\Base;

/**
 * Class Test_Data.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Response\Data
 */
class Test_Data extends Base {

	/**
	 * Build a valid email identity for data construction.
	 *
	 * @return Identity
	 */
	protected function email_identity(): Identity {
		return new Identity( Identity_Type::EMAIL, 'data-test@example.test' );
	}

	/**
	 * Guests floor at zero, defaults apply, and a generated timestamp is
	 * stamped when none is given.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor_applies_defaults_and_clamps_guests(): void {
		$defaults = new Data( $this->email_identity(), Status::ATTENDING );

		$this->assertSame( 0, $defaults->guests, 'Guests default to zero.' );
		$this->assertFalse( $defaults->anonymous, 'Anonymous defaults to false.' );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			$defaults->timestamp,
			'A MySQL-format timestamp is generated when none is given.'
		);

		$clamped = new Data( $this->email_identity(), Status::ATTENDING, -5 );

		$this->assertSame( 0, $clamped->guests, 'Negative guests clamp to zero.' );

		$explicit = new Data(
			$this->email_identity(),
			Status::WAITING_LIST,
			3,
			true,
			'2026-01-02 03:04:05'
		);

		$this->assertSame( 3, $explicit->guests );
		$this->assertTrue( $explicit->anonymous );
		$this->assertSame( '2026-01-02 03:04:05', $explicit->timestamp );
	}

	/**
	 * The with_status factory swaps only the status and keeps every other
	 * field, including the original timestamp.
	 *
	 * @covers ::with_status
	 *
	 * @return void
	 */
	public function test_with_status_preserves_other_fields(): void {
		$original = new Data(
			$this->email_identity(),
			Status::WAITING_LIST,
			2,
			true,
			'2026-01-02 03:04:05'
		);

		$attending = $original->with_status( Status::ATTENDING );

		$this->assertSame( Status::ATTENDING, $attending->status );
		$this->assertSame( $original->identity, $attending->identity );
		$this->assertSame( 2, $attending->guests );
		$this->assertTrue( $attending->anonymous );
		$this->assertSame( '2026-01-02 03:04:05', $attending->timestamp );
	}
}
