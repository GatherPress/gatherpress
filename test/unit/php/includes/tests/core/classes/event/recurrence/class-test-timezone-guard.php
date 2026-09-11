<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Timezone_Guard.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Event\Recurrence\Timezone_Guard;
use GatherPress\Core\Utility;
use GatherPress\Tests\Base;
use InvalidArgumentException;

/**
 * Class Test_Timezone_Guard.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Timezone_Guard
 */
class Test_Timezone_Guard extends Base {

	/**
	 * Coverage for assert_named throwing on a fixed UTC-offset timezone, and
	 * not throwing on a named tz-database identifier.
	 *
	 * @covers ::assert_named
	 * @covers ::is_named
	 *
	 * @return void
	 */
	public function test_assert_named_throws_on_utc_offset(): void {
		$this->expectException( InvalidArgumentException::class );

		Timezone_Guard::assert_named( 'UTC+5:30' );
	}

	/**
	 * Coverage for assert_named not throwing on a named tz-database identifier.
	 *
	 * @covers ::assert_named
	 * @covers ::is_named
	 *
	 * @return void
	 */
	public function test_assert_named_does_not_throw_on_named_identifier(): void {
		Timezone_Guard::assert_named( 'America/New_York' );

		$this->assertTrue(
			true,
			'Failed to assert that assert_named() did not throw for a named identifier.'
		);
	}

	/**
	 * Coverage for is_named rejecting every timezone in WordPress's "Manual
	 * Offsets" admin group (UTC-12 .. UTC+14). This is the list an organizer
	 * can actually reach from the site's General Settings screen in three
	 * clicks, so it is the live path the guard exists to close, not a synthetic one.
	 *
	 * Mirrors the offset list wp-admin/options-general.php builds for the
	 * "Manual Offsets" <optgroup>, formatted the same way WordPress formats
	 * the option value: `UTC` followed by the signed decimal-hour offset
	 * (WP's own sign test is `0 <= $offset`, so the zero case reads `UTC+0`,
	 * not `UTC0`. Getting that sign wrong would let a regression that
	 * accepted `UTC+0` slip past this loop unnoticed, so it is asserted
	 * explicitly below as well as generated here).
	 *
	 * GatherPress never actually stores a raw `<option value>` like this.
	 * `Event::save_datetimes()` runs every timezone through
	 * `Utility::maybe_convert_utc_offset()` first, which rewrites `UTC+5.5` to
	 * `+05:30`. Both forms are asserted here: the raw form because it is what
	 * WordPress emits, and the normalized form because it is what the guard
	 * actually receives on a real site.
	 *
	 * @covers ::is_named
	 *
	 * @return void
	 */
	public function test_is_named_rejects_every_wp_manual_offset(): void {
		$offsets = array(
			-12,
			-11.5,
			-11,
			-10.5,
			-10,
			-9.5,
			-9,
			-8.5,
			-8,
			-7.5,
			-7,
			-6.5,
			-6,
			-5.5,
			-5,
			-4.5,
			-4,
			-3.5,
			-3,
			-2.5,
			-2,
			-1.5,
			-1,
			-0.5,
			0,
			0.5,
			1,
			1.5,
			2,
			2.5,
			3,
			3.5,
			4,
			4.5,
			5,
			5.5,
			5.75,
			6,
			6.5,
			7,
			7.5,
			8,
			8.5,
			8.75,
			9,
			9.5,
			10,
			10.5,
			11,
			11.5,
			12,
			12.75,
			13,
			13.75,
			14,
		);

		foreach ( $offsets as $offset ) {
			$timezone = 'UTC' . ( $offset >= 0 ? '+' . $offset : $offset );

			$this->assertFalse(
				Timezone_Guard::is_named( $timezone ),
				sprintf( 'Failed to assert that the manual offset "%s" was rejected.', $timezone )
			);

			$normalized = Utility::maybe_convert_utc_offset( $timezone );

			$this->assertFalse(
				Timezone_Guard::is_named( $normalized ),
				sprintf(
					'Failed to assert that "%s", normalized from "%s", was rejected.',
					$normalized,
					$timezone
				)
			);
		}

		$this->assertFalse(
			Timezone_Guard::is_named( 'UTC+0' ),
			'Failed to assert that WordPress\'s zero-offset form "UTC+0" was rejected.'
		);
	}

	/**
	 * Coverage for is_named rejecting malformed strings that contain no colon,
	 * which is the case a colon-only check would have let through.
	 *
	 * @covers ::is_named
	 *
	 * @return void
	 */
	public function test_is_named_rejects_garbage_without_a_colon(): void {
		$garbage = array( 'Not/AZone', '', 'UTC+5', '12345' );

		foreach ( $garbage as $timezone ) {
			$this->assertFalse(
				Timezone_Guard::is_named( $timezone ),
				sprintf( 'Failed to assert that "%s" was rejected.', $timezone )
			);
		}
	}

	/**
	 * Coverage for is_named accepting a representative spread of real
	 * tz-database identifiers, including an underscore and a three-part name.
	 *
	 * @covers ::is_named
	 *
	 * @return void
	 */
	public function test_is_named_accepts_real_identifiers(): void {
		$identifiers = array(
			'UTC',
			'America/New_York',
			'America/Argentina/Buenos_Aires',
			'Europe/London',
			'Asia/Tokyo',
		);

		foreach ( $identifiers as $timezone ) {
			$this->assertTrue(
				Timezone_Guard::is_named( $timezone ),
				sprintf( 'Failed to assert that "%s" was accepted.', $timezone )
			);
		}
	}
}
