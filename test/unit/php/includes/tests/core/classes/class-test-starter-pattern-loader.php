<?php
/**
 * Class handles unit tests for GatherPress\Core\Starter_Pattern_Loader.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Starter_Pattern_Loader;
use GatherPress\Tests\Base;

/**
 * Class Test_Starter_Pattern_Loader.
 *
 * @coversDefaultClass \GatherPress\Core\Starter_Pattern_Loader
 */
class Test_Starter_Pattern_Loader extends Base {

	/**
	 * Loads each `*.php` file in the fixtures directory and returns the
	 * union of their pattern definitions, skipping anything that does
	 * not return an array with a `name` key (the fixtures cover both
	 * the missing-name and non-array branches).
	 *
	 * @covers ::load
	 *
	 * @return void
	 */
	public function test_load_collects_pattern_definitions(): void {
		$dir = dirname( __DIR__, 4 ) . '/fixtures/starter-pattern-loader/valid';

		$patterns = Starter_Pattern_Loader::load( $dir );

		$this->assertCount(
			1,
			$patterns,
			'Loader should return only the entries that supplied a name.'
		);
		$this->assertSame( 'unit-test/valid', $patterns[0]['name'] );
	}

	/**
	 * Returns an empty array when the directory has no `*.php` files —
	 * lets callers safely apply their filter and `foreach` without an
	 * explicit empty-check.
	 *
	 * @covers ::load
	 *
	 * @return void
	 */
	public function test_load_returns_empty_array_for_empty_directory(): void {
		$dir = dirname( __DIR__, 4 ) . '/fixtures/starter-pattern-loader/empty';

		$patterns = Starter_Pattern_Loader::load( $dir );

		$this->assertSame( array(), $patterns );
	}
}
