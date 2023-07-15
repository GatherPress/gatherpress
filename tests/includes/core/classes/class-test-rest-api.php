<?php
/**
 * Class handles unit tests for GatherPress\Core\Query.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rest_Api;
use PMC\Unit_Test\Base;

/**
 * Class Test_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Rest_Api
 */
class Test_Rest_Api extends Base {

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Rest_Api::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'rest_api_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_endpoints' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'rest_prepare_%s', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'prepare_event_data' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for validate_attendance_status method.
	 *
	 * @covers ::validate_attendance_status
	 *
	 * @return void
	 */
	public function test_validate_attendance_status(): void {
		$instance = Rest_Api::get_instance();

		$this->assertTrue(
			$instance->validate_attendance_status( 'attending' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertTrue(
			$instance->validate_attendance_status( 'not_attending' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertFalse(
			$instance->validate_attendance_status( 'attend' ),
			'Failed to assert invalid attendance status.'
		);
		$this->assertFalse(
			$instance->validate_attendance_status( 'wait_list' ),
			'Failed to assert invalid attendance status.'
		);
	}

	/**
	 * Coverage for validate_event_post_id method.
	 *
	 * @covers ::validate_event_post_id
	 * @covers ::validate_number
	 *
	 * @return void
	 */
	public function test_validate_event_post_id(): void {
		$instance = Rest_Api::get_instance();
		$post     = $this->mock->post()->get();
		$event    = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$this->assertFalse(
			$instance->validate_event_post_id( -4 ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			$instance->validate_event_post_id( 0 ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			$instance->validate_event_post_id( 'unit-test' ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertFalse(
			$instance->validate_event_post_id( $post->ID ),
			'Failed to assert invalid event post ID.'
		);
		$this->assertTrue(
			$instance->validate_event_post_id( $event->ID ),
			'Failed to assert valid event post ID.'
		);
	}

	/**
	 * Coverage for validate_event_list_type method.
	 *
	 * @covers ::validate_event_list_type
	 *
	 * @return void
	 */
	public function test_validate_event_list_type(): void {
		$instance = Rest_Api::get_instance();

		$this->assertTrue(
			$instance->validate_event_list_type( 'upcoming' ),
			'Failed to assert valid event list type.'
		);
		$this->assertTrue(
			$instance->validate_event_list_type( 'past' ),
			'Failed to assert valid event list type.'
		);
		$this->assertFalse(
			$instance->validate_event_list_type( 'unit-test' ),
			'Failed to assert not a valid event list type.'
		);
	}

	/**
	 * Coverage for validate_datetime method.
	 *
	 * @covers ::validate_datetime
	 *
	 * @return void
	 */
	public function test_validate_datetime(): void {
		$instance = Rest_Api::get_instance();

		$this->assertFalse(
			$instance->validate_datetime( 'unit-test' ),
			'Failed to assert invalid datetime.'
		);
		$this->assertTrue(
			$instance->validate_datetime( '2023-05-11 08:30:00' ),
			'Failed to assert valid datatime.'
		);
	}

	/**
	 * Coverage for validate_timezone method.
	 *
	 * @covers ::validate_timezone
	 *
	 * @return void
	 */
	public function test_validate_timezone(): void {
		$instance = Rest_Api::get_instance();
		$this->assertFalse(
			$instance->validate_timezone( 'unit-test' ),
			'Failed to assert invalid timezone.'
		);

		$this->assertTrue(
			$instance->validate_timezone( 'America/New_York' ),
			'Failed to assert valid timezone.'
		);
	}

}
