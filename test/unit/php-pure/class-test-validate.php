<?php
/**
 * Class handles pure unit tests for GatherPress\Core\Validate.
 *
 * @package GatherPress
 * @subpackage Tests\Pure
 * @since TBD
 */

namespace GatherPress\Tests\Pure\Core;

use GatherPress\Core\Validate;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class Test_Validate.
 *
 * Pure unit tests for GatherPress\Core\Validate verifying domain logic
 * without loading WordPress, wp-env, or Docker.
 *
 * @coversDefaultClass \GatherPress\Core\Validate
 * @since TBD
 */
class Test_Validate extends TestCase {

	/**
	 * Coverage for rsvp_status method.
	 *
	 * @since TBD
	 * @covers ::rsvp_status
	 *
	 * @return void
	 */
	public function test_rsvp_status(): void {
		$this->assertTrue(
			Validate::rsvp_status( 'attending' ),
			'Failed to assert attending is a valid status.'
		);
		$this->assertTrue(
			Validate::rsvp_status( 'not_attending' ),
			'Failed to assert not_attending is a valid status.'
		);
		$this->assertTrue(
			Validate::rsvp_status( 'no_status' ),
			'Failed to assert no_status is a valid status.'
		);
		$this->assertTrue(
			Validate::rsvp_status( 'waiting_list' ),
			'Failed to assert waiting_list is a valid status.'
		);
		$this->assertFalse(
			Validate::rsvp_status( 'wait_list' ),
			'Failed to assert wait_list is an invalid status.'
		);
		$this->assertFalse(
			Validate::rsvp_status( 'unit_test' ),
			'Failed to assert unit_test is an invalid status.'
		);
		$this->assertFalse(
			Validate::rsvp_status( '' ),
			'Failed to assert empty string is an invalid status.'
		);
		$this->assertFalse(
			Validate::rsvp_status( 'invalid' ),
			'Failed to assert invalid status is rejected.'
		);
	}

	/**
	 * Data provider for positive_number tests.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_positive_number(): array {
		return array(
			'integer_1'          => array( 1, true ),
			'integer_42'         => array( 42, true ),
			'string_1'           => array( '1', true ),
			'string_123'         => array( '123', true ),
			'zero_integer'       => array( 0, false ),
			'zero_string'        => array( '0', false ),
			'negative_integer'   => array( -5, false ),
			'negative_string'    => array( '-10', false ),
			'non_numeric_string' => array( 'abc', false ),
			'empty_string'       => array( '', false ),
			'null_value'         => array( null, false ),
		);
	}

	/**
	 * Coverage for positive_number method.
	 *
	 * @since TBD
	 * @dataProvider data_positive_number
	 * @covers ::positive_number
	 *
	 * @param mixed $param    The value to validate.
	 * @param bool  $expected Expected validation result.
	 *
	 * @return void
	 */
	public function test_positive_number( $param, bool $expected ): void {
		$this->assertSame( $expected, Validate::positive_number( $param ) );
	}

	/**
	 * Data provider for non_negative_number tests.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_non_negative_number(): array {
		return array(
			'zero_integer'       => array( 0, true ),
			'zero_string'        => array( '0', true ),
			'integer_1'          => array( 1, true ),
			'integer_42'         => array( 42, true ),
			'string_123'         => array( '123', true ),
			'negative_1'         => array( -1, false ),
			'negative_42'        => array( -42, false ),
			'negative_string'    => array( '-5', false ),
			'non_numeric_string' => array( 'abc', false ),
			'empty_string'       => array( '', false ),
			'null_value'         => array( null, false ),
		);
	}

	/**
	 * Coverage for non_negative_number method.
	 *
	 * @since TBD
	 * @dataProvider data_non_negative_number
	 * @covers ::non_negative_number
	 *
	 * @param mixed $param    The value to validate.
	 * @param bool  $expected Expected validation result.
	 *
	 * @return void
	 */
	public function test_non_negative_number( $param, bool $expected ): void {
		$this->assertSame( $expected, Validate::non_negative_number( $param ) );
	}

	/**
	 * Data provider for coordinate tests.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_coordinate(): array {
		return array(
			'zero_coordinate'     => array( 0, true ),
			'positive_latitude'   => array( 40.7128, true ),
			'negative_longitude'  => array( -74.0060, true ),
			'string_coordinate'   => array( '40.7128', true ),
			'range_floor_string'  => array( '-180', true ),
			'range_floor_float'   => array( -180.0, true ),
			'range_ceiling_int'   => array( 180, true ),
			'range_ceiling_float' => array( 180.0, true ),
			'out_of_range_high'   => array( 180.0001, false ),
			'out_of_range_low'    => array( -180.0001, false ),
			'extreme_negative'    => array( -9999, false ),
			'non_numeric_text'    => array( 'banana', false ),
			'trailing_garbage'    => array( '40.7128abc', false ),
			'empty_string'        => array( '', false ),
			'null_value'          => array( null, false ),
		);
	}

	/**
	 * Coverage for coordinate method.
	 *
	 * @since TBD
	 * @dataProvider data_coordinate
	 * @covers ::coordinate
	 *
	 * @param mixed $param    The coordinate value to validate.
	 * @param bool  $expected Expected validation result.
	 *
	 * @return void
	 */
	public function test_coordinate( $param, bool $expected ): void {
		$this->assertSame( $expected, Validate::coordinate( $param ) );
	}

	/**
	 * Data provider for boolean validation tests.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_boolean(): array {
		return array(
			'bool_true'        => array( true, true ),
			'bool_false'       => array( false, true ),
			'int_1'            => array( 1, true ),
			'int_0'            => array( 0, true ),
			'string_1'         => array( '1', true ),
			'string_0'         => array( '0', true ),
			'int_2'            => array( 2, false ),
			'string_2'         => array( '2', false ),
			'string_true'      => array( 'true', false ),
			'string_false'     => array( 'false', false ),
			'arbitrary_string' => array( 'foobar', false ),
			'empty_array'      => array( array(), false ),
			'null_value'       => array( null, false ),
		);
	}

	/**
	 * Coverage for boolean method.
	 *
	 * @since TBD
	 * @dataProvider data_boolean
	 * @covers ::boolean
	 *
	 * @param mixed $value    The value to validate.
	 * @param bool  $expected Expected validation result.
	 *
	 * @return void
	 */
	public function test_boolean( $value, bool $expected ): void {
		$this->assertSame( $expected, Validate::boolean( $value ) );
	}

	/**
	 * Data provider for send method tests.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_send(): array {
		return array(
			'valid_recipient_map'       => array(
				array(
					'all'           => true,
					'attending'     => false,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				true,
			),
			'valid_all_true'            => array(
				array(
					'all'           => true,
					'attending'     => true,
					'waiting_list'  => true,
					'not_attending' => true,
				),
				true,
			),
			'missing_attending_key'     => array(
				array(
					'all'           => true,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				false,
			),
			'missing_waiting_list_key'  => array(
				array(
					'all'           => true,
					'attending'     => false,
					'not_attending' => false,
				),
				false,
			),
			'missing_not_attending_key' => array(
				array(
					'all'          => true,
					'attending'    => false,
					'waiting_list' => false,
				),
				false,
			),
			'missing_all_key'           => array(
				array(
					'attending'     => true,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				false,
			),
			'non_bool_value'            => array(
				array(
					'all'           => null,
					'attending'     => false,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				false,
			),
			'non_array_null'            => array( null, false ),
			'non_array_string'          => array( 'unit-test', false ),
		);
	}

	/**
	 * Coverage for send method.
	 *
	 * @since TBD
	 * @dataProvider data_send
	 * @covers ::send
	 *
	 * @param mixed $params   The email recipient parameters.
	 * @param bool  $expected Expected validation result.
	 *
	 * @return void
	 */
	public function test_send( $params, bool $expected ): void {
		$this->assertSame( $expected, Validate::send( $params ) );
	}

	/**
	 * Coverage for event_list_type method.
	 *
	 * @since TBD
	 * @covers ::event_list_type
	 *
	 * @return void
	 */
	public function test_event_list_type(): void {
		$this->assertTrue(
			Validate::event_list_type( 'upcoming' ),
			'Failed to assert upcoming is a valid event list type.'
		);
		$this->assertTrue(
			Validate::event_list_type( 'past' ),
			'Failed to assert past is a valid event list type.'
		);
		$this->assertFalse(
			Validate::event_list_type( 'unit-test' ),
			'Failed to assert unit-test is an invalid event list type.'
		);
		$this->assertFalse(
			Validate::event_list_type( '' ),
			'Failed to assert empty string is an invalid event list type.'
		);
	}

	/**
	 * Coverage for datetime method.
	 *
	 * @since TBD
	 * @covers ::datetime
	 *
	 * @return void
	 */
	public function test_datetime(): void {
		$this->assertTrue(
			Validate::datetime( '2023-05-11 08:30:00' ),
			'Failed to assert valid standard datetime.'
		);
		$this->assertTrue(
			Validate::datetime( '2025-12-31 23:59:59' ),
			'Failed to assert valid end of year datetime.'
		);
		$this->assertTrue(
			Validate::datetime( '2024-02-29 12:00:00' ),
			'Failed to assert valid leap year datetime.'
		);
		$this->assertTrue(
			Validate::datetime( '2023-01-01 00:00:00' ),
			'Failed to assert valid start of year datetime.'
		);
		$this->assertFalse(
			Validate::datetime( '2023-05-11' ),
			'Failed to assert date-only string is invalid.'
		);
		$this->assertFalse(
			Validate::datetime( '12:00:00' ),
			'Failed to assert time-only string is invalid.'
		);
		$this->assertFalse(
			Validate::datetime( '2023/05/11 12:00:00' ),
			'Failed to assert wrong delimiter format is invalid.'
		);
		$this->assertFalse(
			Validate::datetime( '' ),
			'Failed to assert empty string is invalid.'
		);
		$this->assertFalse(
			Validate::datetime( 'invalid-datetime' ),
			'Failed to assert completely invalid string is invalid.'
		);
	}

	/**
	 * Coverage for timezone method.
	 *
	 * @since TBD
	 * @covers ::timezone
	 *
	 * @return void
	 */
	public function test_timezone(): void {
		$this->assertTrue(
			Validate::timezone( 'America/New_York' ),
			'Failed to assert America/New_York is a valid timezone.'
		);
		$this->assertTrue(
			Validate::timezone( 'Europe/London' ),
			'Failed to assert Europe/London is a valid timezone.'
		);
		$this->assertTrue(
			Validate::timezone( 'Asia/Tokyo' ),
			'Failed to assert Asia/Tokyo is a valid timezone.'
		);
		$this->assertTrue(
			Validate::timezone( 'UTC' ),
			'Failed to assert UTC is a valid timezone.'
		);
		$this->assertTrue(
			Validate::timezone( '+00:00' ),
			'Failed to assert +00:00 is a valid UTC offset.'
		);
		$this->assertTrue(
			Validate::timezone( '-05:00' ),
			'Failed to assert -05:00 is a valid UTC offset.'
		);
		$this->assertTrue(
			Validate::timezone( 'UTC+5' ),
			'Failed to assert UTC+5 is converted and validated.'
		);
		$this->assertFalse(
			Validate::timezone( '' ),
			'Failed to assert empty string is an invalid timezone.'
		);
		$this->assertFalse(
			Validate::timezone( 'Invalid/Timezone' ),
			'Failed to assert non-existent timezone identifier is invalid.'
		);
		$this->assertFalse(
			Validate::timezone( 'unit-test' ),
			'Failed to assert arbitrary string is an invalid timezone.'
		);
	}

	/**
	 * Test valid block data with all required properties.
	 *
	 * @since TBD
	 * @covers ::block_data
	 *
	 * @return void
	 */
	public function test_valid_block_data(): void {
		$valid_data = wp_json_encode(
			array(
				'blockName'   => 'gatherpress/rsvp-template',
				'attrs'       => array( 'align' => 'center' ),
				'innerBlocks' => array(),
			)
		);

		$this->assertIsString( $valid_data );
		$this->assertTrue( Validate::block_data( $valid_data ) );
	}

	/**
	 * Test complex nested block data structure.
	 *
	 * @since TBD
	 * @covers ::block_data
	 *
	 * @return void
	 */
	public function test_complex_nested_block_data(): void {
		$complex_data = wp_json_encode(
			array(
				'blockName'   => 'gatherpress/rsvp-template',
				'attrs'       => array( 'width' => 50 ),
				'innerBlocks' => array(
					array(
						'blockName'   => 'core/paragraph',
						'attrs'       => array( 'dropCap' => true ),
						'innerBlocks' => array(),
					),
				),
			)
		);

		$this->assertIsString( $complex_data );
		$this->assertTrue( Validate::block_data( $complex_data ) );
	}

	/**
	 * Only the RSVP template is a valid root block for block_data.
	 *
	 * @since TBD
	 * @covers ::block_data
	 *
	 * @return void
	 */
	public function test_block_data_requires_rsvp_template_root(): void {
		$invalid_root = wp_json_encode(
			array(
				'blockName'   => 'core/paragraph',
				'attrs'       => array(),
				'innerBlocks' => array(),
			)
		);

		$this->assertIsString( $invalid_root );
		$this->assertFalse( Validate::block_data( $invalid_root ) );
	}

	/**
	 * Test invalid JSON strings for block_data.
	 *
	 * @since TBD
	 * @covers ::block_data
	 *
	 * @return void
	 */
	public function test_block_data_invalid_json(): void {
		$this->assertFalse( Validate::block_data( '{invalid_json";' ) );
		$this->assertFalse( Validate::block_data( '' ) );
	}

	/**
	 * Data provider for invalid block data structures.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_invalid_block_data_structures(): array {
		return array(
			'missing_inner_blocks'  => array(
				array(
					'blockName' => 'gatherpress/rsvp-template',
					'attrs'     => array( 'align' => 'center' ),
				),
			),
			'missing_attrs'         => array(
				array(
					'blockName'   => 'gatherpress/rsvp-template',
					'innerBlocks' => array(),
				),
			),
			'missing_block_name'    => array(
				array(
					'attrs'       => array(),
					'innerBlocks' => array(),
				),
			),
			'non_string_blockname'  => array(
				array(
					'blockName'   => array( 'not-a-string' ),
					'attrs'       => array(),
					'innerBlocks' => array(),
				),
			),
			'non_array_attrs'       => array(
				array(
					'blockName'   => 'gatherpress/rsvp-template',
					'attrs'       => 'not-an-array',
					'innerBlocks' => array(),
				),
			),
			'non_array_innerblocks' => array(
				array(
					'blockName'   => 'gatherpress/rsvp-template',
					'attrs'       => array(),
					'innerBlocks' => 'not-an-array',
				),
			),
		);
	}

	/**
	 * Test invalid property structures and types for block_data.
	 *
	 * @since TBD
	 * @dataProvider data_invalid_block_data_structures
	 * @covers ::block_data
	 *
	 * @param array<string, mixed> $data Structure to encode and test.
	 *
	 * @return void
	 */
	public function test_invalid_block_data_structures( array $data ): void {
		$encoded = wp_json_encode( $data );
		$this->assertIsString( $encoded );
		$this->assertFalse( Validate::block_data( $encoded ) );
	}

	/**
	 * Coverage for block_signature method.
	 *
	 * @since TBD
	 * @covers ::block_signature
	 *
	 * @return void
	 */
	public function test_block_signature(): void {
		$valid_signature = str_repeat( 'a0', 32 );
		$this->assertTrue(
			Validate::block_signature( $valid_signature ),
			'Failed to assert 64-character lowercase hex string is valid.'
		);
		$this->assertFalse(
			Validate::block_signature( str_repeat( 'A0', 32 ) ),
			'Failed to assert uppercase hex string is invalid.'
		);
		$this->assertFalse(
			Validate::block_signature( str_repeat( 'a0', 31 ) ),
			'Failed to assert 62-character hex string is invalid.'
		);
		$this->assertFalse(
			Validate::block_signature( str_repeat( 'a0', 33 ) ),
			'Failed to assert 66-character hex string is invalid.'
		);
		$this->assertFalse(
			Validate::block_signature( str_repeat( 'z0', 32 ) ),
			'Failed to assert non-hex characters are invalid.'
		);
		$this->assertFalse(
			Validate::block_signature( 64 ),
			'Failed to assert integer is invalid.'
		);
		$this->assertFalse(
			Validate::block_signature( '' ),
			'Failed to assert empty string is invalid.'
		);
		$this->assertFalse(
			Validate::block_signature( null ),
			'Failed to assert null is invalid.'
		);
	}
}
