<?php
/**
 * Class handles unit tests for GatherPress\Core\Tooltip.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Tooltip;
use GatherPress\Tests\Base;

/**
 * Class Test_Tooltip.
 *
 * @coversDefaultClass \GatherPress\Core\Tooltip
 */
class Test_Tooltip extends Base {
	/**
	 * Coverage for get_allowed_html method.
	 *
	 * @covers ::get_allowed_html
	 *
	 * @return void
	 */
	public function test_get_allowed_html(): void {
		$allowed_html = Tooltip::get_allowed_html();

		$this->assertArrayHasKey( 'span', $allowed_html );
		$this->assertArrayHasKey( 'class', $allowed_html['span'] );
		$this->assertArrayHasKey( 'data-gatherpress-tooltip', $allowed_html['span'] );
		$this->assertArrayHasKey( 'data-gatherpress-tooltip-text-color', $allowed_html['span'] );
		$this->assertArrayHasKey( 'data-gatherpress-tooltip-bg-color', $allowed_html['span'] );
		$this->assertArrayHasKey( 'style', $allowed_html['span'] );
	}
}
