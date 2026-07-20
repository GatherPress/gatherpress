<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Notices\Upcoming_Php_Requirement.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core\Admin\Notices;

use GatherPress\Core\Admin\Notices\Base;
use GatherPress\Core\Admin\Notices\Upcoming_Php_Requirement;
use GatherPress\Core\Admin\Notices\Upcoming_Requirement;
use GatherPress\Tests\Base as Test_Base;

/**
 * Class Test_Upcoming_Php_Requirement.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notices\Upcoming_Php_Requirement
 */
class Test_Upcoming_Php_Requirement extends Test_Base {

	/**
	 * Coverage for the notice's shape.
	 *
	 * @covers ::get_slug
	 * @covers ::get_required_version
	 * @covers ::get_current_version
	 * @covers \GatherPress\Core\Admin\Notices\Upcoming_Requirement::get_type
	 * @covers \GatherPress\Core\Admin\Notices\Upcoming_Requirement::get_capability
	 * @covers \GatherPress\Core\Admin\Notices\Upcoming_Requirement::is_persistent
	 *
	 * @return void
	 */
	public function test_shape(): void {
		$notice = new Upcoming_Php_Requirement();

		$this->assertSame(
			'gatherpress_upcoming_php_requirement',
			$notice->get_slug(),
			'Failed to assert the notice slug.'
		);
		$this->assertSame(
			Upcoming_Php_Requirement::REQUIRES_PHP,
			$notice->get_required_version(),
			'Failed to assert the required PHP version.'
		);
		$this->assertSame(
			PHP_VERSION,
			$notice->get_current_version(),
			'Failed to assert that the current version reads the running PHP.'
		);
		$this->assertSame(
			Base::TYPE_WARNING,
			$notice->get_type(),
			'Failed to assert that an advisory notice is a warning.'
		);
		$this->assertSame(
			'update_plugins',
			$notice->get_capability(),
			'Failed to assert that the notice is gated to those who can act on it.'
		);
		$this->assertFalse(
			$notice->is_persistent(),
			'Failed to assert that a requirement warning cannot be silenced permanently.'
		);
	}

	/**
	 * Coverage for is_below, which backs applies.
	 *
	 * Takes both versions as arguments so each direction is testable without
	 * the suite having to run on an old PHP.
	 *
	 * @covers \GatherPress\Core\Admin\Notices\Upcoming_Requirement::is_below
	 *
	 * @return void
	 */
	public function test_is_below(): void {
		$notice = new Upcoming_Php_Requirement();

		$this->assertTrue(
			$notice->is_below( '7.4.33', Upcoming_Php_Requirement::REQUIRES_PHP ),
			'Failed to assert that PHP 7.4 falls below the upcoming requirement.'
		);
		$this->assertTrue(
			$notice->is_below( '8.0.30', Upcoming_Php_Requirement::REQUIRES_PHP ),
			'Failed to assert that PHP 8.0 falls below the upcoming requirement.'
		);
		$this->assertFalse(
			$notice->is_below( '8.1.0', Upcoming_Php_Requirement::REQUIRES_PHP ),
			'Failed to assert that PHP 8.1 meets the upcoming requirement.'
		);
		$this->assertFalse(
			$notice->is_below( '8.4.1', Upcoming_Php_Requirement::REQUIRES_PHP ),
			'Failed to assert that PHP 8.4 meets the upcoming requirement.'
		);
	}

	/**
	 * Coverage for applies method.
	 *
	 * @covers \GatherPress\Core\Admin\Notices\Upcoming_Requirement::applies
	 *
	 * @return void
	 */
	public function test_applies(): void {
		$notice = new Upcoming_Php_Requirement();

		$this->assertSame(
			version_compare( PHP_VERSION, Upcoming_Php_Requirement::REQUIRES_PHP, '<' ),
			$notice->applies(),
			'Failed to assert that the notice applies exactly when PHP is below the coming floor.'
		);
	}

	/**
	 * Coverage for get_message method.
	 *
	 * @covers ::get_message
	 *
	 * @return void
	 */
	public function test_get_message(): void {
		$message = ( new Upcoming_Php_Requirement() )->get_message();

		$this->assertStringContainsString(
			Upcoming_Requirement::UPCOMING_VERSION,
			$message,
			'Failed to assert that the message named the upcoming version.'
		);
		$this->assertStringContainsString(
			Upcoming_Php_Requirement::REQUIRES_PHP,
			$message,
			'Failed to assert that the message named the required PHP version.'
		);
	}
}
