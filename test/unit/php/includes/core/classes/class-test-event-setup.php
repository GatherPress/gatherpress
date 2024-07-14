<?php
/**
 * Class handles unit tests for GatherPress\Core\Event_Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Event_Setup;
use PMC\Unit_Test\Base;

/**
 * Class Test_Event_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Event_Setup
 */
class Test_Event_Setup extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Event_Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_post_type' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_post_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'delete_event' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_%s_posts_custom_column', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'custom_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'set_custom_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => sprintf( 'manage_edit-%s_sortable_columns', Event::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'sortable_columns' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'get_the_date',
				'priority' => 10,
				'callback' => array( $instance, 'get_the_event_date' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'the_time',
				'priority' => 10,
				'callback' => array( $instance, 'get_the_event_date' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'display_post_states',
				'priority' => 10,
				'callback' => array( $instance, 'set_event_archive_labels' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_post_type method.
	 *
	 * @covers ::register_post_type
	 *
	 * @return void
	 */
	public function test_register_post_type(): void {
		$instance = Event_Setup::get_instance();

		unregister_post_type( Event::POST_TYPE );

		$this->assertFalse( post_type_exists( Event::POST_TYPE ), 'Failed to assert that post type does not exist.' );

		$instance->register_post_type();

		$this->assertTrue( post_type_exists( Event::POST_TYPE ), 'Failed to assert that post type exists.' );
	}

	/**
	 * Coverage for register_post_meta method.
	 *
	 * @covers ::register_post_meta
	 *
	 * @return void
	 */
	public function test_register_post_meta(): void {
		$instance = Event_Setup::get_instance();

		unregister_post_meta( Event::POST_TYPE, 'gatherpress_online_event_link' );
		unregister_post_meta( Event::POST_TYPE, 'gatherpress_enable_anonymous_rsvp' );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayNotHasKey( 'online_event_link', $meta, 'Failed to assert that online_event_link does not exist.' );
		$this->assertArrayNotHasKey( 'enable_anonymous_rsvp', $meta, 'Failed to assert that enable_anonymous_rsvp does not exist.' );
		$this->assertArrayNotHasKey( 'max_attendance_limit', $meta, 'Failed to assert that max_guest_limit does not exist.' );
		$this->assertArrayNotHasKey( 'max_guest_limit', $meta, 'Failed to assert that max_guest_limit does not exist.' );

		$instance->register_post_meta();

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayHasKey( 'gatherpress_online_event_link', $meta, 'Failed to assert that gatherpress_online_event_link does exist.' );
		$this->assertArrayHasKey( 'gatherpress_enable_anonymous_rsvp', $meta, 'Failed to assert that gatherpress_enable_anonymous_rsvp does exist.' );
		$this->assertArrayHasKey( 'gatherpress_max_attendance_limit', $meta, 'Failed to assert that max_guest_limit does exist.' );
		$this->assertArrayHasKey( 'gatherpress_max_guest_limit', $meta, 'Failed to assert that gatherpress_max_guest_limit does exist.' );
	}

	/**
	 * Coverage for sortable_columns method.
	 *
	 * @covers ::sortable_columns
	 *
	 * @return void
	 */
	public function test_sortable_columns(): void {
		$instance = Event_Setup::get_instance();
		$default  = array( 'unit' => 'test' );
		$expects  = array(
			'unit'     => 'test',
			'datetime' => 'datetime',
		);

		$this->assertSame(
			$expects,
			$instance->sortable_columns( $default ),
			'Failed to assert correct sortable columns.'
		);
	}
}
