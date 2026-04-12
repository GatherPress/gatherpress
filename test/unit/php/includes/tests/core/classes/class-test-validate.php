<?php
/**
 * Class handles unit tests for GatherPress\Core\Validate.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Validate;
use GatherPress\Tests\Base;

/**
 * Class Test_Validate.
 *
 * @coversDefaultClass \GatherPress\Core\Validate
 */
class Test_Validate extends Base {
	/**
	 * Coverage for rsvp_status method.
	 *
	 * @since 1.0.0
	 * @covers ::rsvp_status
	 *
	 * @return void
	 */
	public function test_rsvp_status(): void {
		$this->assertTrue(
			Validate::rsvp_status( 'attending' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertTrue(
			Validate::rsvp_status( 'not_attending' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertTrue(
			Validate::rsvp_status( 'no_status' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertTrue(
			Validate::rsvp_status( 'waiting_list' ),
			'Failed to assert valid waiting_list status.'
		);
		$this->assertFalse(
			Validate::rsvp_status( 'wait_list' ),
			'Failed to assert invalid attendance status.'
		);
		$this->assertFalse(
			Validate::rsvp_status( 'unit_test' ),
			'Failed to assert invalid attendance status.'
		);
		$this->assertFalse(
			Validate::rsvp_status( '' ),
			'Failed to assert invalid empty string status.'
		);
	}

	/**
	 * Data provider for send test.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function data_send(): array {
		return array(
			array(
				array(
					'all'           => true,
					'attending'     => false,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				true,
			),
			array(
				array(
					'unit_test' => true,
				),
				false,
			),
			array(
				null,
				false,
			),
			array(
				'unit-test',
				false,
			),
			array(
				array(
					'all'           => null,
					'attending'     => false,
					'waiting_list'  => false,
					'not_attending' => false,
				),
				false,
			),
		);
	}

	/**
	 * Coverage for send method.
	 *
	 * @since 1.0.0
	 * @dataProvider data_send
	 * @covers ::send
	 *
	 * @param mixed $params  The parameters to send for validation.
	 * @param bool  $expects Expected response.
	 *
	 * @return void
	 */
	public function test_send( $params, bool $expects ): void {
		$this->assertSame( $expects, Validate::send( $params ) );
	}

	/**
	 * Coverage for event_post_id method.
	 *
	 * @since 1.0.0
	 * @covers ::event_post_id
	 * @covers ::positive_number
	 *
	 * @return void
	 */
	public function test_event_post_id(): void {
		$post  = $this->mock->post()->get();
		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertFalse(
			Validate::event_post_id( -4 ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			Validate::event_post_id( 0 ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			Validate::event_post_id( 'unit-test' ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			Validate::event_post_id( $post->ID ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertTrue(
			Validate::event_post_id( $event->ID ),
			'Failed to assert valid event post ID.'
		);
	}
	/**
	 * Coverage for boolean method.
	 *
	 * @since 1.0.0
	 * @covers ::boolean
	 *
	 * @return void
	 */
	public function test_boolean(): void {
		$this->assertTrue(
			Validate::boolean( true ),
			'Failed to assert valid boolean type.'
		);

		$this->assertTrue(
			Validate::boolean( false ),
			'Failed to assert valid boolean type.'
		);

		$this->assertTrue(
			Validate::boolean( 1 ),
			'Failed to assert valid boolean type.'
		);

		$this->assertTrue(
			Validate::boolean( 0 ),
			'Failed to assert valid boolean type.'
		);

		$this->assertTrue(
			Validate::boolean( '1' ),
			'Failed to assert valid boolean type.'
		);

		$this->assertTrue(
			Validate::boolean( '0' ),
			'Failed to assert valid boolean type.'
		);

		$this->assertFalse(
			Validate::boolean( 'foobar' ),
			'Failed to assert invalid boolean type.'
		);
	}

	/**
	 * Coverage for event_list_type method.
	 *
	 * @since 1.0.0
	 * @covers ::event_list_type
	 *
	 * @return void
	 */
	public function test_event_list_type(): void {
		$this->assertTrue(
			Validate::event_list_type( 'upcoming' ),
			'Failed to assert valid event list type.'
		);
		$this->assertTrue(
			Validate::event_list_type( 'past' ),
			'Failed to assert valid event list type.'
		);
		$this->assertFalse(
			Validate::event_list_type( 'unit-test' ),
			'Failed to assert not a valid event list type.'
		);
	}

	/**
	 * Coverage for datetime method.
	 *
	 * @since 1.0.0
	 * @covers ::datetime
	 *
	 * @return void
	 */
	public function test_datetime(): void {
		$this->assertFalse(
			Validate::datetime( 'unit-test' ),
			'Failed to assert invalid datetime.'
		);
		$this->assertTrue(
			Validate::datetime( '2023-05-11 08:30:00' ),
			'Failed to assert valid datetime.'
		);
	}

	/**
	 * Coverage for timezone method.
	 *
	 * @since 1.0.0
	 * @covers ::timezone
	 *
	 * @return void
	 */
	public function test_timezone(): void {
		$this->assertFalse(
			Validate::timezone( 'unit-test' ),
			'Failed to assert invalid timezone.'
		);
		$this->assertTrue(
			Validate::timezone( 'America/New_York' ),
			'Failed to assert valid timezone.'
		);
	}

	/**
	 * Test valid block data with all required properties.
	 *
	 * @since 1.0.0
	 * @covers ::block_data
	 *
	 * @return void
	 */
	public function test_valid_block_data(): void {
		$valid_data = wp_json_encode(
			array(
				'blockName'   => 'core/paragraph',
				'attrs'       => array( 'align' => 'center' ),
				'innerBlocks' => array(),
			)
		);

		$this->assertTrue(
			Validate::block_data( $valid_data )
		);
	}

	/**
	 * Test invalid JSON string.
	 *
	 * @since 1.0.0
	 * @covers ::block_data
	 *
	 * @return void
	 */
	public function test_invalid_json(): void {
		$invalid_json = '{invalid_json";';

		$this->assertFalse(
			Validate::block_data( $invalid_json )
		);
	}

	/**
	 * Test missing required properties.
	 *
	 * @since 1.0.0
	 * @covers ::block_data
	 *
	 * @return void
	 */
	public function test_missing_required_properties(): void {
		$missing_properties = wp_json_encode(
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array( 'align' => 'center' ),
				// Missing innerBlocks.
			)
		);

		$this->assertFalse(
			Validate::block_data( $missing_properties )
		);
	}

	/**
	 * Data provider for invalid property types test.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function data_invalid_property_types_provider(): array {
		return array(
			'non_string_blockname'  => array(
				array(
					'blockName'   => array( 'not-a-string' ),
					'attrs'       => array(),
					'innerBlocks' => array(),
				),
			),
			'non_array_attrs'       => array(
				array(
					'blockName'   => 'core/paragraph',
					'attrs'       => 'not-an-array',
					'innerBlocks' => array(),
				),
			),
			'non_array_innerblocks' => array(
				array(
					'blockName'   => 'core/paragraph',
					'attrs'       => array(),
					'innerBlocks' => 'not-an-array',
				),
			),
		);
	}

	/**
	 * Test invalid property types.
	 *
	 * @since 1.0.0
	 * @dataProvider data_invalid_property_types_provider
	 * @covers ::block_data
	 *
	 * @param array $data Array containing block data with invalid property types.
	 *
	 * @return void
	 */
	public function test_invalid_property_types( array $data ): void {
		$invalid_data = wp_json_encode( $data );

		$this->assertFalse(
			Validate::block_data( $invalid_data )
		);
	}

	/**
	 * Test complex nested block structure.
	 *
	 * @since 1.0.0
	 * @covers ::block_data
	 *
	 * @return void
	 */
	public function test_complex_nested_blocks(): void {
		$complex_data = wp_json_encode(
			array(
				'blockName'   => 'core/column',
				'attrs'       => array( 'width' => 50 ),
				'innerBlocks' => array(
					array(
						'blockName'   => 'core/paragraph',
						'attrs'       => array( 'dropCap' => true ),
						'innerBlocks' => array(),
					),
					array(
						'blockName'   => 'core/image',
						'attrs'       => array( 'id' => 123 ),
						'innerBlocks' => array(),
					),
				),
			)
		);

		$this->assertTrue( Validate::block_data( $complex_data ) );
	}

	/**
	 * Coverage for positive_number method with valid positive numbers.
	 *
	 * @since 1.0.0
	 * @covers ::positive_number
	 *
	 * @return void
	 */
	public function test_positive_number_valid(): void {
		$this->assertTrue(
			Validate::positive_number( 1 ),
			'Failed to assert 1 is a valid positive number.'
		);
		$this->assertTrue(
			Validate::positive_number( 42 ),
			'Failed to assert 42 is a valid positive number.'
		);
		$this->assertTrue(
			Validate::positive_number( '123' ),
			'Failed to assert string "123" is a valid positive number.'
		);
		$this->assertTrue(
			Validate::positive_number( '1' ),
			'Failed to assert string "1" is a valid positive number.'
		);
	}

	/**
	 * Coverage for positive_number method with invalid values.
	 *
	 * @since 1.0.0
	 * @covers ::positive_number
	 *
	 * @return void
	 */
	public function test_positive_number_invalid(): void {
		$this->assertFalse(
			Validate::positive_number( 0 ),
			'Failed to assert 0 is not a valid positive number.'
		);
		$this->assertFalse(
			Validate::positive_number( -5 ),
			'Failed to assert -5 is not a valid positive number.'
		);
		$this->assertFalse(
			Validate::positive_number( '-10' ),
			'Failed to assert string "-10" is not a valid positive number.'
		);
		$this->assertFalse(
			Validate::positive_number( 'abc' ),
			'Failed to assert "abc" is not a valid positive number.'
		);
		$this->assertFalse(
			Validate::positive_number( '' ),
			'Failed to assert empty string is not a valid positive number.'
		);
		$this->assertFalse(
			Validate::positive_number( '0' ),
			'Failed to assert string "0" is not a valid positive number.'
		);
	}

	/**
	 * Coverage for non_negative_number method with valid non-negative numbers.
	 *
	 * @since 1.0.0
	 * @covers ::non_negative_number
	 *
	 * @return void
	 */
	public function test_non_negative_number_valid(): void {
		$this->assertTrue(
			Validate::non_negative_number( 0 ),
			'Failed to assert 0 is a valid non-negative number.'
		);
		$this->assertTrue(
			Validate::non_negative_number( 1 ),
			'Failed to assert 1 is a valid non-negative number.'
		);
		$this->assertTrue(
			Validate::non_negative_number( 42 ),
			'Failed to assert 42 is a valid non-negative number.'
		);
		$this->assertTrue(
			Validate::non_negative_number( '0' ),
			'Failed to assert string "0" is a valid non-negative number.'
		);
		$this->assertTrue(
			Validate::non_negative_number( '123' ),
			'Failed to assert string "123" is a valid non-negative number.'
		);
	}

	/**
	 * Coverage for non_negative_number method with invalid values.
	 *
	 * @since 1.0.0
	 * @covers ::non_negative_number
	 *
	 * @return void
	 */
	public function test_non_negative_number_invalid(): void {
		$this->assertFalse(
			Validate::non_negative_number( -1 ),
			'Failed to assert -1 is not a valid non-negative number.'
		);
		$this->assertFalse(
			Validate::non_negative_number( -42 ),
			'Failed to assert -42 is not a valid non-negative number.'
		);
		$this->assertFalse(
			Validate::non_negative_number( '-5' ),
			'Failed to assert string "-5" is not a valid non-negative number.'
		);
		$this->assertFalse(
			Validate::non_negative_number( 'abc' ),
			'Failed to assert "abc" is not a valid non-negative number.'
		);
		$this->assertFalse(
			Validate::non_negative_number( '' ),
			'Failed to assert empty string is not a valid non-negative number.'
		);
	}

	/**
	 * Coverage for datetime method with additional edge cases.
	 *
	 * @since 1.0.0
	 * @covers ::datetime
	 *
	 * @return void
	 */
	public function test_datetime_edge_cases(): void {
		$this->assertTrue(
			Validate::datetime( '2025-12-31 23:59:59' ),
			'Failed to assert valid datetime at end of year.'
		);
		$this->assertTrue(
			Validate::datetime( '2024-02-29 12:00:00' ),
			'Failed to assert valid datetime for leap year.'
		);
		$this->assertTrue(
			Validate::datetime( '2023-01-01 00:00:00' ),
			'Failed to assert valid datetime at start of year.'
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
			Validate::datetime( '' ),
			'Failed to assert empty string is invalid datetime.'
		);
		$this->assertFalse(
			Validate::datetime( '2023/05/11 12:00:00' ),
			'Failed to assert wrong date format is invalid.'
		);
		$this->assertFalse(
			Validate::datetime( 'invalid-datetime' ),
			'Failed to assert completely invalid string.'
		);
	}

	/**
	 * Coverage for timezone method with UTC offset format.
	 *
	 * @since 1.0.0
	 * @covers ::timezone
	 *
	 * @return void
	 */
	public function test_timezone_utc_offsets(): void {
		$this->assertTrue(
			Validate::timezone( 'UTC' ),
			'Failed to assert UTC is a valid timezone.'
		);
		$this->assertTrue(
			Validate::timezone( 'Europe/London' ),
			'Failed to assert Europe/London is a valid timezone.'
		);
		$this->assertTrue(
			Validate::timezone( 'Asia/Tokyo' ),
			'Failed to assert Asia/Tokyo is a valid timezone.'
		);
		$this->assertFalse(
			Validate::timezone( '' ),
			'Failed to assert empty string is invalid timezone.'
		);
		$this->assertFalse(
			Validate::timezone( 'Invalid/Timezone' ),
			'Failed to assert invalid timezone identifier.'
		);
	}

	/**
	 * Coverage for send method with missing keys.
	 *
	 * @since 1.0.0
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_missing_keys(): void {
		// Missing 'attending' key.
		$this->assertFalse(
			Validate::send(
				array(
					'all'           => true,
					'waiting_list'  => false,
					'not_attending' => false,
				)
			),
			'Failed to assert invalid send params with missing attending key.'
		);

		// Missing 'waiting_list' key.
		$this->assertFalse(
			Validate::send(
				array(
					'all'           => true,
					'attending'     => false,
					'not_attending' => false,
				)
			),
			'Failed to assert invalid send params with missing waiting_list key.'
		);

		// Missing 'not_attending' key.
		$this->assertFalse(
			Validate::send(
				array(
					'all'          => true,
					'attending'    => false,
					'waiting_list' => false,
				)
			),
			'Failed to assert invalid send params with missing not_attending key.'
		);
	}

	/**
	 * Coverage for event_post_id with string numeric ID.
	 *
	 * @since 1.0.0
	 * @covers ::event_post_id
	 *
	 * @return void
	 */
	public function test_event_post_id_string_numeric(): void {
		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertTrue(
			Validate::event_post_id( (string) $event->ID ),
			'Failed to assert string numeric event ID is valid.'
		);
	}

	/**
	 * Coverage for boolean with additional edge cases.
	 *
	 * @since 1.0.0
	 * @covers ::boolean
	 *
	 * @return void
	 */
	public function test_boolean_edge_cases(): void {
		$this->assertFalse(
			Validate::boolean( 2 ),
			'Failed to assert 2 is not a valid boolean.'
		);
		$this->assertFalse(
			Validate::boolean( '2' ),
			'Failed to assert string "2" is not a valid boolean.'
		);
		$this->assertFalse(
			Validate::boolean( 'true' ),
			'Failed to assert string "true" is not a valid boolean.'
		);
		$this->assertFalse(
			Validate::boolean( 'false' ),
			'Failed to assert string "false" is not a valid boolean.'
		);
		$this->assertFalse(
			Validate::boolean( array() ),
			'Failed to assert empty array is not a valid boolean.'
		);
		$this->assertFalse(
			Validate::boolean( null ),
			'Failed to assert null is not a valid boolean.'
		);
	}
}
