<?php
/**
 * Class handles unit tests for gatherpress.php
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use PMC\Unit_Test\Base;

/**
 * Class Test_GatherPress.
 */
class Test_GatherPress extends Base {
	/**
	 * Check plugin version.
	 *
	 * @return void
	 */
	public function test_plugin_version(): void {
		$credits      = include sprintf( '%s/includes/data/credits/latest.php', GATHERPRESS_CORE_PATH );
		$package_json = json_decode(
			file_get_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				sprintf( '%s/package.json', GATHERPRESS_CORE_PATH )
			),
			true
		);

		$this->assertSame(
			$credits['version'],
			GATHERPRESS_VERSION,
			'Failed to assert version in gatherpress.php matches version in latest.php.'
		);

		$this->assertSame(
			$package_json['version'],
			GATHERPRESS_VERSION,
			'Failed to assert version in gatherpress.php matches version in package.json.'
		);
	}
}
