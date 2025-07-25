<?php
/**
 * Class handles unit tests for GatherPress\Core\Migrate.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Migrate;
use GatherPress\Tests\Base;

/**
 * Class Test_Migrate.
 *
 * @coversDefaultClass \GatherPress\Core\Migrate
 * @group migrate
 */
class Test_Migrate extends Base {
	/**
	 * Coverage for get_pseudopostmetas method.
	 *
	 * @covers ::get_pseudopostmetas
	 *
	 * @return void
	 */
	public function test_get_pseudopostmetas(): void {
		$migrate         = new Migrate();
		$pseudopostmetas = $migrate->get_pseudopostmetas();

		$this->assertIsArray(
			$pseudopostmetas['gatherpress_datetimes'],
			'Failed to assert gatherpress_datetimes is an array'
		);
		$this->assertCount(
			2,
			$pseudopostmetas['gatherpress_datetimes']['export_callback'],
			'Failed to assert export_callback array should have 2 elements.'
		);
		$this->assertSame(
			'GatherPress\Core\Export',
			$pseudopostmetas['gatherpress_datetimes']['export_callback'][0],
			'Failed to assert that class in export_callback does not match.
		'
		);
		$this->assertSame(
			'datetimes_callback',
			$pseudopostmetas['gatherpress_datetimes']['export_callback'][1],
			'Failed to assert method in export_callback does not match.'
		);
		$this->assertCount(
			2,
			$pseudopostmetas['gatherpress_datetimes']['import_callback'],
			'Failed to assert import_callback array should have 2 elements.'
		);
		$this->assertSame(
			'GatherPress\Core\Import',
			$pseudopostmetas['gatherpress_datetimes']['import_callback'][0],
			'Failed to assert that class in import_callback does not match.
		'
		);
		$this->assertSame(
			'datetimes_callback',
			$pseudopostmetas['gatherpress_datetimes']['import_callback'][1],
			'Failed to assert method in import_callback does not match.'
		);
	}
}
