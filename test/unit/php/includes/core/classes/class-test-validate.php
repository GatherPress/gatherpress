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
use PMC\Unit_Test\Base;

/**
 * Class Test_Validate.
 *
 * @coversDefaultClass \GatherPress\Core\Validate
 */
class Test_Validate extends Base {

	/**
	 * Coverage for rsvp_status method.
	 *
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
	 * @return array[]
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
	 * @dataProvider data_send
	 *
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
	 * Coverage for event_list_type method.
	 *
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
			'Failed to assert valid datatime.'
		);
	}

	/**
	 * Coverage for timezone method.
	 *
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
}
