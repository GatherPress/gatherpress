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
		$this->assertFalse(
			Validate::rsvp_status( 'wait_list' ),
			'Failed to assert invalid attendance status.'
		);
		$this->assertFalse(
			Validate::rsvp_status( 'unit_test' ),
			'Failed to assert invalid attendance status.'
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
	 * @covers ::number
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
}
