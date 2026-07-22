<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Notices\Requires_Php.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core\Admin\Notices;

use GatherPress\Core\Admin\Notices\Base;
use GatherPress\Core\Admin\Notices\Requires_Php;
use GatherPress\Tests\Base as Test_Base;

/**
 * Class Test_Requires_Php.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notices\Requires_Php
 */
class Test_Requires_Php extends Test_Base {

	/**
	 * Coverage for the notice's shape.
	 *
	 * @covers ::get_slug
	 * @covers ::get_type
	 * @covers ::is_dismissible
	 *
	 * @return void
	 */
	public function test_shape(): void {
		$notice = new Requires_Php();

		$this->assertSame(
			'gatherpress_requires_php',
			$notice->get_slug(),
			'Failed to assert the notice slug.'
		);
		$this->assertSame(
			Base::TYPE_ERROR,
			$notice->get_type(),
			'Failed to assert that a blocking requirement is an error.'
		);
		$this->assertFalse(
			$notice->is_dismissible(),
			'Failed to assert that a blocking requirement cannot be waved away.'
		);
	}

	/**
	 * Coverage for applies method.
	 *
	 * The suite runs on a supported PHP, so this is the passing branch. The
	 * failing branch is what requirements-check.php acts on, and it is the same
	 * one-line comparison.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies(): void {
		$this->assertSame(
			version_compare( PHP_VERSION, GATHERPRESS_REQUIRES_PHP, '<' ),
			( new Requires_Php() )->applies(),
			'Failed to assert that the notice applies exactly when PHP is below the minimum.'
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
		$message = ( new Requires_Php() )->get_message();

		$this->assertStringContainsString(
			GATHERPRESS_REQUIRES_PHP,
			$message,
			'Failed to assert that the message named the required PHP version.'
		);
		$this->assertStringContainsString(
			phpversion(),
			$message,
			'Failed to assert that the message named the running PHP version.'
		);
	}
}
