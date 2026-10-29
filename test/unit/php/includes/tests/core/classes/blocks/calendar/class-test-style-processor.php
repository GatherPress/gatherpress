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
	 * Build a style processor for block attributes with a blockGap value.
	 *
	 * @param mixed $block_gap The blockGap value.
	 *
	 * @return Style_Processor
	 */
	private function with_gap( $block_gap ): Style_Processor {
		return new Style_Processor( array( 'style' => array( 'spacing' => array( 'blockGap' => $block_gap ) ) ) );
	}

	/**
	 * Coverage for get_block_gap_value.
	 *
	 * @covers ::get_block_gap_value
	 * @covers ::__construct
	 * @covers ::get_gap_side
	 *
	 * @return void
	 */
	public function test_get_block_gap_value(): void {
		$this->assertSame( '', ( new Style_Processor( array() ) )->get_block_gap_value() );
		$this->assertSame( '', ( new Style_Processor( array( 'style' => 'not an array' ) ) )->get_block_gap_value() );
		$this->assertSame(
			'var(--wp--preset--spacing--50)',
			$this->with_gap( 'var:preset|spacing|50' )->get_block_gap_value()
		);
		$this->assertSame( '10px', $this->with_gap( '10px' )->get_block_gap_value() );
		$this->assertSame(
			'2px var(--wp--preset--spacing--20)',
			$this->with_gap(
				array(
					'top'  => '2px',
					'left' => 'var:preset|spacing|20',
				)
			)->get_block_gap_value()
		);
		$this->assertSame(
			'1px 3px',
			$this->with_gap( array( 'left' => '3px' ) )->get_block_gap_value(),
			'Failed to assert a missing side defaults to 1px.'
		);
		$this->assertSame(
			'',
			$this->with_gap( 5 )->get_block_gap_value(),
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
		$this->assertSame( '', ( new Style_Processor( array() ) )->get_block_column_gap_value() );
		$this->assertSame( '20px', $this->with_gap( '20px' )->get_block_column_gap_value() );
		$this->assertSame(
			'var(--wp--preset--spacing--30)',
			$this->with_gap(
				array(
					'top'  => '2px',
					'left' => 'var:preset|spacing|30',
				)
			)->get_block_column_gap_value(),
			'Failed to assert the column gap is the left side.'
		);
		$this->assertSame(
			'1px',
			$this->with_gap( array( 'top' => '2px' ) )->get_block_column_gap_value()
		);
		$this->assertSame(
			'0px',
			$this->with_gap( array( 'left' => '0' ) )->get_block_column_gap_value(),
			'Failed to assert 0 becomes a length for calc().'
		);
		$this->assertSame( '', $this->with_gap( 5 )->get_block_column_gap_value() );
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
			'var(--wp--preset--spacing--50-color-red)',
			Style_Processor::resolve_preset_value( 'var:preset|spacing|50;color:red' ),
			'Failed to assert a preset slug keeps only safe characters.'
		);
		$this->assertSame(
			'var(--wp--preset--spacing--x-large)',
			Style_Processor::resolve_preset_value( 'var:preset|spacing|x_large' ),
			'Failed to assert the slug in kebab case, as WordPress names the preset variable.'
		);
		$this->assertSame(
			'var(--wp--preset--font-size--2-xl)',
			Style_Processor::resolve_preset_value( 'var:preset|fontSize|2xl' )
		);
		$this->assertSame(
			'',
			$this->with_gap(
				array(
					'top'  => '2px',
					'left' => '1px;position:fixed',
				)
			)->get_block_gap_value(),
			'Failed to assert an unsafe side drops the whole gap.'
		);
		$this->assertSame(
			'',
			Style_Processor::resolve_preset_value( '"x"' ),
			'Failed to assert the value is escaped.'
		);
	}
}
