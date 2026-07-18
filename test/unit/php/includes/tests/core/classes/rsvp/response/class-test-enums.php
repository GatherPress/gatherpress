<?php
/**
 * Unit tests for the RSVP response enums: Status, Visibility, Identity_Type.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response;

use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp\Response\Visibility;
use GatherPress\Tests\Base;

/**
 * Class Test_Enums.
 *
 * One test class for the three backed enums — each is a closed value set
 * plus a `values()` helper (and `try_from` on Status).
 */
class Test_Enums extends Base {

	/**
	 * Status::try_from resolves known values and falls back to NO_STATUS
	 * for anything unknown, unlike the native tryFrom which returns null.
	 *
	 * @covers \GatherPress\Core\Rsvp\Response\Status::try_from
	 *
	 * @return void
	 */
	public function test_status_try_from_falls_back_to_no_status(): void {
		$this->assertSame( Status::ATTENDING, Status::try_from( 'attending' ) );
		$this->assertSame( Status::WAITING_LIST, Status::try_from( 'waiting_list' ) );
		$this->assertSame( Status::NO_STATUS, Status::try_from( 'bogus-status' ) );
		$this->assertSame( Status::NO_STATUS, Status::try_from( '' ) );
	}

	/**
	 * Each enum's values() helper returns the backed values of every case.
	 *
	 * @covers \GatherPress\Core\Rsvp\Response\Status::values
	 * @covers \GatherPress\Core\Rsvp\Response\Visibility::values
	 * @covers \GatherPress\Core\Rsvp\Response\Identity_Type::values
	 *
	 * @return void
	 */
	public function test_values_helpers_list_all_cases(): void {
		$this->assertSame(
			array( 'attending', 'not_attending', 'waiting_list', 'no_status' ),
			Status::values()
		);
		$this->assertSame(
			array( '0', '1', '2', '3', '4' ),
			Visibility::values()
		);
		$this->assertSame(
			array( 'email', 'wp_user_id', 'url', 'external_id' ),
			Identity_Type::values()
		);
	}
}
