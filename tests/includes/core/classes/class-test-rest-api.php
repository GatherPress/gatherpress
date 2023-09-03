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
use PMC\Unit_Test\Utility;
use WP_REST_Server;

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
			array(
				'type'     => 'filter',
				'name'     => 'rest_send_nocache_headers',
				'priority' => 10,
				'callback' => array( $instance, 'nocache_headers_for_endpoint' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for get_event_routes method.
	 *
	 * @covers ::get_event_routes
	 * @covers ::datetime_route
	 * @covers ::email_route
	 * @covers ::rsvp_route
	 * @covers ::events_list_route
	 *
	 * @return void
	 */
	public function test_get_event_routes(): void {
		$instance = Rest_Api::get_instance();
		$routes   = Utility::invoke_hidden_method( $instance, 'get_event_routes' );

		$this->assertSame( 'datetime', $routes[0]['route'], 'Failed to assert route is datetime.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[0]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'email', $routes[1]['route'], 'Failed to assert route is email.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[1]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'rsvp', $routes[2]['route'], 'Failed to assert route is rsvp.' );
		$this->assertSame(
			WP_REST_Server::EDITABLE,
			$routes[2]['args']['methods'],
			'Failed to assert methods is POST, PUT, PATCH.'
		);
		$this->assertSame( 'events-list', $routes[3]['route'], 'Failed to assert route is rsvp.' );
		$this->assertSame(
			WP_REST_Server::READABLE,
			$routes[3]['args']['methods'],
			'Failed to assert methods is GET.'
		);
	}

	/**
	 * Coverage for validate_rsvp_status method.
	 *
	 * @covers ::validate_rsvp_status
	 *
	 * @return void
	 */
	public function test_validate_rsvp_status(): void {
		$instance = Rest_Api::get_instance();

		$this->assertTrue(
			$instance->validate_rsvp_status( 'attending' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertTrue(
			$instance->validate_rsvp_status( 'not_attending' ),
			'Failed to assert valid attendance status.'
		);
		$this->assertFalse(
			$instance->validate_rsvp_status( 'attend' ),
			'Failed to assert invalid attendance status.'
		);
		$this->assertFalse(
			$instance->validate_rsvp_status( 'wait_list' ),
			'Failed to assert invalid attendance status.'
		);
	}

	/**
	 * Data provider for validate_send test.
	 *
	 * @return array[]
	 */
	public function data_validate_send(): array {
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
	 * Coverage for validate_send method.
	 *
	 * @dataProvider data_validate_send
	 *
	 * @covers ::validate_send
	 *
	 * @param array $params  The parameters to send for validation.
	 * @param bool  $expects Expected response.
	 *
	 * @return void
	 */
	public function test_validate_send( array $params, bool $expects ): void {
		$instance = Rest_Api::get_instance();

		$this->assertSame( $expects, $instance->validate_send( $params ) );
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

	/**
	 * Coverage for nocache_headers_for_endpoint method.
	 *
	 * @covers ::nocache_headers_for_endpoint
	 *
	 * @return void
	 */
	public function test_nocache_headers_for_endpoint(): void {
		global $wp;

		$instance = Rest_Api::get_instance();

		$this->assertFalse( $instance->nocache_headers_for_endpoint( false ) );

		$wp->query_vars['rest_route'] = '/gatherpress/v1/event/events-list';

		$this->assertTrue( $instance->nocache_headers_for_endpoint( false ) );
	}

}
