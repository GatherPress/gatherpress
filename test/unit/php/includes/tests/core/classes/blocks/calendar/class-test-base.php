<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Base.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use GatherPress\Core\Blocks\Calendar\Base as Calendar_Base;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block;

/**
 * Class Test_Base.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar\Base
 */
class Test_Base extends Base {

	/**
	 * Build a calendar block with the given context.
	 *
	 * @param array<string, mixed> $context Block context.
	 *
	 * @return Calendar_Base
	 */
	private function with_context( array $context ): Calendar_Base {
		$block          = new WP_Block( array( 'blockName' => 'core/paragraph' ) );
		$block->context = $context;

		return new class( $block ) extends Calendar_Base {
			/**
			 * Render nothing.
			 *
			 * @return string
			 */
			public function render(): string {
				return '';
			}
		};
	}

	/**
	 * Coverage for the typed context getters.
	 *
	 * @covers ::__construct
	 * @covers ::get_context_string
	 * @covers ::get_context_int
	 * @covers ::get_context_array
	 *
	 * @return void
	 */
	public function test_context_getters(): void {
		$block = $this->with_context(
			array(
				'string' => 'value',
				'int'    => '7',
				'array'  => array( 1 ),
			)
		);

		$this->assertSame( 'value', Utility::invoke_hidden_method( $block, 'get_context_string', array( 'string' ) ) );
		$this->assertSame( '', Utility::invoke_hidden_method( $block, 'get_context_string', array( 'array' ) ) );
		$this->assertSame( 7, Utility::invoke_hidden_method( $block, 'get_context_int', array( 'int' ) ) );
		$this->assertSame( 0, Utility::invoke_hidden_method( $block, 'get_context_int', array( 'string' ) ) );
		$this->assertSame( array( 1 ), Utility::invoke_hidden_method( $block, 'get_context_array', array( 'array' ) ) );
		$this->assertSame( array(), Utility::invoke_hidden_method( $block, 'get_context_array', array( 'missing' ) ) );
	}

	/**
	 * Coverage for get_time_grid.
	 *
	 * @covers ::get_time_grid
	 *
	 * @return void
	 */
	public function test_get_time_grid(): void {
		$time_grid = new Time_Grid( true );
		$block     = $this->with_context( array( 'gatherpress/timeGridConfig' => $time_grid ) );

		$this->assertSame( $time_grid, Utility::invoke_hidden_method( $block, 'get_time_grid' ) );
		$this->assertFalse( Utility::invoke_hidden_method( $this->with_context( array() ), 'get_time_grid' )->enabled );
	}
}
