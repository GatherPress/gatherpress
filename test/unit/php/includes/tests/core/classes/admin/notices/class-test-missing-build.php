<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Notices\Missing_Build.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core\Admin\Notices;

use GatherPress\Core\Admin\Notices\Base;
use GatherPress\Core\Admin\Notices\Missing_Build;
use GatherPress\Tests\Base as Test_Base;

/**
 * Class Test_Missing_Build.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notices\Missing_Build
 */
class Test_Missing_Build extends Test_Base {

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
		$notice = new Missing_Build();

		$this->assertSame(
			'gatherpress_missing_build',
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
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies(): void {
		$this->assertSame(
			! is_dir( GATHERPRESS_CORE_PATH . '/build' ),
			( new Missing_Build() )->applies(),
			'Failed to assert that the notice applies exactly when the build directory is absent.'
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
		$this->assertStringContainsString(
			'npm run build',
			( new Missing_Build() )->get_message(),
			'Failed to assert that the message named the build command.'
		);
	}
}
