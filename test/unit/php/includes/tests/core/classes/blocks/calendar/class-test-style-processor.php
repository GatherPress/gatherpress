<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Style_Processor.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use GatherPress\Core\Blocks\Calendar\Style_Processor;
use GatherPress\Tests\Base;

/**
 * Class Test_Style_Processor.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar\Style_Processor
 */
class Test_Style_Processor extends Base {

	/**
	 * Build block attributes with a blockGap value.
	 *
	 * @param mixed $block_gap The blockGap value.
	 *
	 * @return array<string, mixed>
	 */
	private function with_gap( $block_gap ): array {
		return array( 'style' => array( 'spacing' => array( 'blockGap' => $block_gap ) ) );
	}

	/**
	 * Coverage for get_block_gap_value.
	 *
	 * @covers ::get_block_gap_value
	 * @covers ::get_raw_block_gap
	 * @covers ::get_gap_side
	 *
	 * @return void
	 */
	public function test_get_block_gap_value(): void {
		$this->assertSame( '', Style_Processor::get_block_gap_value( array() ) );
		$this->assertSame( '', Style_Processor::get_block_gap_value( array( 'style' => 'not an array' ) ) );
		$this->assertSame(
			'var(--wp--preset--spacing--50)',
			Style_Processor::get_block_gap_value( $this->with_gap( 'var:preset|spacing|50' ) )
		);
		$this->assertSame( '10px', Style_Processor::get_block_gap_value( $this->with_gap( '10px' ) ) );
		$this->assertSame(
			'2px var(--wp--preset--spacing--20)',
			Style_Processor::get_block_gap_value(
				$this->with_gap(
					array(
						'top'  => '2px',
						'left' => 'var:preset|spacing|20',
					)
				)
			)
		);
		$this->assertSame(
			'1px 3px',
			Style_Processor::get_block_gap_value( $this->with_gap( array( 'left' => '3px' ) ) ),
			'Failed to assert a missing side defaults to 1px.'
		);
		$this->assertSame(
			'',
			Style_Processor::get_block_gap_value( $this->with_gap( 5 ) ),
			'Failed to assert a number is ignored.'
		);
	}

	/**
	 * Coverage for get_block_column_gap_value.
	 *
	 * @covers ::get_block_column_gap_value
	 *
	 * @return void
	 */
	public function test_get_block_column_gap_value(): void {
		$this->assertSame( '', Style_Processor::get_block_column_gap_value( array() ) );
		$this->assertSame( '20px', Style_Processor::get_block_column_gap_value( $this->with_gap( '20px' ) ) );
		$this->assertSame(
			'var(--wp--preset--spacing--30)',
			Style_Processor::get_block_column_gap_value(
				$this->with_gap(
					array(
						'top'  => '2px',
						'left' => 'var:preset|spacing|30',
					)
				)
			),
			'Failed to assert the column gap is the left side.'
		);
		$this->assertSame(
			'1px',
			Style_Processor::get_block_column_gap_value( $this->with_gap( array( 'top' => '2px' ) ) )
		);
		$this->assertSame(
			'0px',
			Style_Processor::get_block_column_gap_value( $this->with_gap( array( 'left' => '0' ) ) ),
			'Failed to assert 0 becomes a length for calc().'
		);
		$this->assertSame( '', Style_Processor::get_block_column_gap_value( $this->with_gap( 5 ) ) );
	}

	/**
	 * Coverage for resolve_preset_value.
	 *
	 * @covers ::resolve_preset_value
	 *
	 * @return void
	 */
	public function test_resolve_preset_value(): void {
		$this->assertSame(
			'var(--wp--preset--spacing--40)',
			Style_Processor::resolve_preset_value( 'var:preset|spacing|40' )
		);
		$this->assertSame( 'var:preset|spacing', Style_Processor::resolve_preset_value( 'var:preset|spacing' ) );
		$this->assertSame( '1rem', Style_Processor::resolve_preset_value( '1rem' ) );
		$this->assertSame( 'calc(1rem + 2px)', Style_Processor::resolve_preset_value( 'calc(1rem + 2px)' ) );
		$this->assertSame(
			'',
			Style_Processor::resolve_preset_value( '1px;position:fixed;inset:0' ),
			'Failed to assert a value cannot add a declaration.'
		);
		$this->assertSame( '', Style_Processor::resolve_preset_value( '1px /* x */' ) );
		$this->assertSame(
			'var(--wp--preset--spacing--50colorred)',
			Style_Processor::resolve_preset_value( 'var:preset|spacing|50;color:red' ),
			'Failed to assert a preset slug keeps only safe characters.'
		);
		$this->assertSame(
			'',
			Style_Processor::get_block_gap_value(
				$this->with_gap(
					array(
						'top'  => '2px',
						'left' => '1px;position:fixed',
					)
				)
			),
			'Failed to assert an unsafe side drops the whole gap.'
		);
		$this->assertSame(
			'',
			Style_Processor::resolve_preset_value( '"x"' ),
			'Failed to assert the value is escaped.'
		);
	}
}
