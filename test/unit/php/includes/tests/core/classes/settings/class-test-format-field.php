<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Format_Field.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Format_Field;
use GatherPress\Tests\Base;

/**
 * Class Test_Format_Field.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Format_Field
 */
class Test_Format_Field extends Base {

	/**
	 * Coverage for custom_key.
	 *
	 * @covers ::custom_key
	 *
	 * @return void
	 */
	public function test_custom_key(): void {
		$this->assertSame(
			'date_format_custom',
			Format_Field::custom_key( 'date_format' ),
			'Failed to assert the companion key is the option plus a suffix.'
		);
	}

	/**
	 * The Custom radio is answered by the field beside it.
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolve_takes_the_custom_field(): void {
		$this->assertSame(
			array( 'date_format' => 'D, j M Y' ),
			Format_Field::resolve(
				array(
					'date_format'        => Format_Field::CUSTOM,
					'date_format_custom' => 'D, j M Y',
				),
				array( 'date_format' => 'format' )
			),
			'Failed to assert the Custom field supplied the format.'
		);
	}

	/**
	 * A chosen format wins, and the Custom field is discarded either way.
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolve_drops_the_custom_field_when_unused(): void {
		$this->assertSame(
			array( 'date_format' => 'Y-m-d' ),
			Format_Field::resolve(
				array(
					'date_format'        => 'Y-m-d',
					'date_format_custom' => 'leftover typing',
				),
				array( 'date_format' => 'format' )
			),
			'Failed to assert the listed format won and the companion key was dropped.'
		);
	}

	/**
	 * An empty Custom field resolves to an empty format, not the sentinel.
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolve_empty_custom_field(): void {
		$this->assertSame(
			array( 'date_format' => '' ),
			Format_Field::resolve(
				array( 'date_format' => Format_Field::CUSTOM ),
				array( 'date_format' => 'format' )
			),
			'Failed to assert an absent Custom value resolved to an empty format.'
		);
	}

	/**
	 * A non-scalar Custom submission is discarded rather than cast.
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolve_rejects_a_non_scalar_custom_field(): void {
		$this->assertSame(
			array( 'date_format' => '' ),
			Format_Field::resolve(
				array(
					'date_format'        => Format_Field::CUSTOM,
					'date_format_custom' => array( 'Y-m-d' ),
				),
				array( 'date_format' => 'format' )
			),
			'Failed to assert a non-scalar Custom value was discarded.'
		);
	}

	/**
	 * Fields of another type are left exactly as they arrived.
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolve_leaves_other_field_types_alone(): void {
		$input = array(
			'map_platform'       => 'osm',
			'date_format_custom' => 'D, j M Y',
		);

		$this->assertSame(
			$input,
			Format_Field::resolve( $input, array( 'map_platform' => 'select' ) ),
			'Failed to assert a submission with no format field was untouched.'
		);
	}

	/**
	 * Coverage for choices.
	 *
	 * @covers ::choices
	 *
	 * @return void
	 */
	public function test_choices_offers_the_named_list(): void {
		$this->assertContains(
			'H:i',
			array_column( Format_Field::choices( 'time' ), 'format' ),
			'Failed to assert the time list was offered.'
		);
		$this->assertContains(
			'Y-m-d',
			array_column( Format_Field::choices( 'date' ), 'format' ),
			'Failed to assert the date list was offered.'
		);
	}

	/**
	 * Anything but 'time' falls back to the date list.
	 *
	 * The field declaration names one list or the other, so an unrecognized
	 * name is a typo, and dates are the more useful thing to show while
	 * someone finds it.
	 *
	 * @covers ::choices
	 *
	 * @return void
	 */
	public function test_choices_falls_back_to_the_date_list(): void {
		$this->assertSame(
			Format_Field::choices( 'date' ),
			Format_Field::choices( '' ),
			'Failed to assert an unnamed list falls back to dates.'
		);
	}
}
