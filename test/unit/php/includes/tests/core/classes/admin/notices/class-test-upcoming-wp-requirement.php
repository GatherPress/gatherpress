<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Notices\Upcoming_Wp_Requirement.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core\Admin\Notices;

use GatherPress\Core\Admin\Notices\Upcoming_Requirement;
use GatherPress\Core\Admin\Notices\Upcoming_Wp_Requirement;
use GatherPress\Tests\Base as Test_Base;

/**
 * Class Test_Upcoming_Wp_Requirement.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notices\Upcoming_Wp_Requirement
 */
class Test_Upcoming_Wp_Requirement extends Test_Base {

	/**
	 * Coverage for the notice's shape.
	 *
	 * @covers ::get_slug
	 * @covers ::get_required_version
	 * @covers ::get_current_version
	 *
	 * @return void
	 */
	public function test_shape(): void {
		$notice = new Upcoming_Wp_Requirement();

		$this->assertSame(
			'gatherpress_upcoming_wp_requirement',
			$notice->get_slug(),
			'Failed to assert the notice slug.'
		);
		$this->assertSame(
			Upcoming_Wp_Requirement::REQUIRES_WP,
			$notice->get_required_version(),
			'Failed to assert the required WordPress version.'
		);
		$this->assertSame(
			get_bloginfo( 'version' ),
			$notice->get_current_version(),
			'Failed to assert that the current version reads the running WordPress.'
		);
	}

	/**
	 * Coverage for is_below, which backs applies.
	 *
	 * @covers \GatherPress\Core\Admin\Notices\Upcoming_Requirement::is_below
	 *
	 * @return void
	 */
	public function test_is_below(): void {
		$notice = new Upcoming_Wp_Requirement();

		$this->assertTrue(
			$notice->is_below( '6.7', Upcoming_Wp_Requirement::REQUIRES_WP ),
			'Failed to assert that WordPress 6.7 falls below the upcoming requirement.'
		);
		$this->assertTrue(
			$notice->is_below( '6.9.2', Upcoming_Wp_Requirement::REQUIRES_WP ),
			'Failed to assert that WordPress 6.9.2 falls below the upcoming requirement.'
		);
		$this->assertFalse(
			$notice->is_below( '7.0', Upcoming_Wp_Requirement::REQUIRES_WP ),
			'Failed to assert that WordPress 7.0 meets the upcoming requirement.'
		);
		$this->assertFalse(
			$notice->is_below( '7.1', Upcoming_Wp_Requirement::REQUIRES_WP ),
			'Failed to assert that WordPress 7.1 meets the upcoming requirement.'
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
		$this->assertSame(
			version_compare( get_bloginfo( 'version' ), Upcoming_Wp_Requirement::REQUIRES_WP, '<' ),
			( new Upcoming_Wp_Requirement() )->applies(),
			'Failed to assert that the notice applies exactly when WordPress is below the coming floor.'
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
		$message = ( new Upcoming_Wp_Requirement() )->get_message();

		$this->assertStringContainsString(
			Upcoming_Requirement::UPCOMING_VERSION,
			$message,
			'Failed to assert that the message named the upcoming version.'
		);
		$this->assertStringContainsString(
			Upcoming_Wp_Requirement::REQUIRES_WP,
			$message,
			'Failed to assert that the message named the required WordPress version.'
		);
	}
}
