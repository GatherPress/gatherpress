<?php
/**
 * Class handles unit tests for GatherPress\Core\Assets.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Assets;
use GatherPress\Core\Event;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Assets.
 *
 * @coversDefaultClass \GatherPress\Core\Assets
 */
class Test_Assets extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Assets::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_print_scripts',
				'priority' => PHP_INT_MIN,
				'callback' => array( $instance, 'add_global_object' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_enqueue_scripts',
				'priority' => 10,
				'callback' => array( $instance, 'admin_enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_block_assets',
				'priority' => 10,
				'callback' => array( $instance, 'enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_block_editor_assets',
				'priority' => 10,
				'callback' => array( $instance, 'editor_enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_head',
				'priority' => PHP_INT_MIN,
				'callback' => array( $instance, 'add_global_object' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_footer',
				'priority' => 11,
				'callback' => array( $instance, 'event_communication_modal' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for add_global_object method.
	 *
	 * @covers ::add_global_object
	 *
	 * @return void
	 */
	public function test_add_global_object(): void {
		$instance = Assets::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$object   = Utility::buffer_and_return( array( $instance, 'add_global_object' ) );

		$this->assertMatchesRegularExpression( '#<script>window.GatherPress = {.*}</script>#', $object, 'Failed to assert regex of global object matches.' );
	}

	/**
	 * Coverage for event_communication_modal method.
	 *
	 * @covers ::event_communication_modal
	 *
	 * @return void
	 */
	public function test_event_communication_modal(): void {
		$instance = Assets::get_instance();
		$this->mock->post( array( 'post_type' => 'post' ) );

		$output = Utility::buffer_and_return( array( $instance, 'event_communication_modal' ) );

		$this->assertEmpty( $output, 'Failed to assert event_communication_modal outputs nothing.' );

		$this->mock->post( array( 'post_type' => Event::POST_TYPE ) );

		$output = Utility::buffer_and_return( array( $instance, 'event_communication_modal' ) );

		$this->assertSame(
			'<div id="gp-event-communication-modal" />',
			$output,
			'Failed to assert event_communication_modal output div.'
		);
	}


	/**
	 * Coverage Enqueue scripts
	 *
	 * @covers ::enqueue_scripts
	 *
	 * @return void
	 */
	public function test_enqueue_scripts(): void {
		$instance = Assets::get_instance();
		$instance->enqueue_scripts();

		$this->assertTrue( wp_style_is( 'dashicons', 'enqueued' ) );
	}

	/**
	 * Coverage for localize method.
	 *
	 * @covers ::localize
	 *
	 * @return void
	 */
	public function test_localize(): void {
		$instance = Assets::get_instance();
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$event->save_datetimes(
			array(
				'datetime_start' => '2020-05-11 15:00:00',
				'datetime_end'   => '2020-05-12 17:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$output = Utility::invoke_hidden_method( $instance, 'localize', array( $event_id ) );

		$expected_datetime = array(
			'datetime_start'     => '2020-05-11 15:00:00',
			'datetime_start_gmt' => '2020-05-11 19:00:00',
			'datetime_end'       => '2020-05-12 17:00:00',
			'datetime_end_gmt'   => '2020-05-12 21:00:00',
			'timezone'           => 'America/New_York',
		);

		$this->assertSame(
			$expected_datetime,
			$output['event_datetime'],
			'Failed to assert that datetime array matches.'
		);
		$this->assertEquals( 1, $output['has_event_past'], 'Failed to asssert that has_event_past is true' );
		$this->assertEquals( $event_id, $output['post_id'], 'Failed to asssert that post_id matches.' );
	}

	/**
	 * Coverage for unregister_blocks.
	 *
	 * @covers ::unregister_blocks
	 *
	 * @return void
	 */
	public function test_unregister_blocks_frontend(): void {
		$instance = Assets::get_instance();

		$blocks = Utility::invoke_hidden_method( $instance, 'unregister_blocks' );
		$this->assertSame( array(), $blocks );
		$this->mock->wp()->reset();
	}

	/**
	 * Data provider for unregister_blocks_admin test.
	 *
	 * @return array
	 */
	public function date_unregister_blocks_admin(): array {
		return array(
			array(
				'post',
				array(
					'gatherpress/add-to-calendar',
					'gatherpress/event-date',
					'gatherpress/online-event',
					'gatherpress/rsvp',
					'gatherpress/rsvp-response',
					'gatherpress/venue',
				),
			),
			array(
				'page',
				array(
					'gatherpress/add-to-calendar',
					'gatherpress/event-date',
					'gatherpress/online-event',
					'gatherpress/rsvp',
					'gatherpress/rsvp-response',
					'gatherpress/venue',
				),
			),
			array(
				'gp_event',
				array(),
			),
			array(
				'gp_venue',
				array(
					'gatherpress/add-to-calendar',
					'gatherpress/event-date',
					'gatherpress/online-event',
					'gatherpress/rsvp',
					'gatherpress/rsvp-response',
				),
			),
		);
	}

	/**
	 * Coverage for unregister_blocks.
	 *
	 * @param string $post_type       Post type.
	 * @param array  $expected_blocks Array of blocks.
	 *
	 * @dataProvider date_unregister_blocks_admin
	 * @covers ::unregister_blocks
	 *
	 * @return void
	 */
	public function test_unregister_blocks_admin( string $post_type, array $expected_blocks ): void {
		$instance = Assets::get_instance();

		$this->mock->post( array( 'post_type' => $post_type ) );
		$this->mock->user( 'admin', 'wp-admin-page' );

		$blocks = Utility::invoke_hidden_method( $instance, 'unregister_blocks' );
		$this->assertSame( $expected_blocks, $blocks );

		$this->mock->wp()->reset();
	}

	/**
	 * Coverage for get_login_url.
	 *
	 * @covers ::get_login_url
	 *
	 * @return void
	 */
	public function test_get_login_url(): void {
		$instance = Assets::get_instance();

		$this->assertSame( wp_login_url(), $instance->get_login_url() );

		$post = $this->mock->post()->get();

		$this->assertSame( wp_login_url( get_the_permalink( $post->ID ) ), $instance->get_login_url( $post->ID ) );

		$this->mock->post()->reset();
	}

	/**
	 * Coverage for get_registration_url.
	 *
	 * @covers ::get_registration_url
	 *
	 * @return void
	 */
	public function test_get_registration_url(): void {
		$instance                   = Assets::get_instance();
		$users_can_register_name    = 'users_can_register';
		$users_can_register_default = get_option( $users_can_register_name );

		update_option( $users_can_register_name, 0 );

		$this->assertEmpty( $instance->get_registration_url() );

		update_option( $users_can_register_name, 1 );

		$this->assertSame( wp_registration_url(), $instance->get_registration_url() );

		$post = $this->mock->post()->get();

		$this->assertSame(
			add_query_arg(
				'redirect',
				get_the_permalink( $post->ID ),
				wp_registration_url()
			),
			$instance->get_registration_url( $post->ID )
		);

		$this->mock->post()->reset();

		update_option( $users_can_register_name, $users_can_register_default );
	}


	/**
	 * Coverage for get_asset_data method.
	 *
	 * @covers ::get_asset_data
	 *
	 * @return void
	 */
	public function test_get_asset_data(): void {
		$instance = Assets::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'asset_data', array() );

		$asset = Utility::invoke_hidden_method( $instance, 'get_asset_data', array( 'editor' ) );

		$this->assertIsArray( $asset['dependencies'], 'Failed to assert that dependencies is an array.' );
		$this->assertIsString( $asset['version'], 'Failed to assert that version is a string.' );
		$this->assertIsArray(
			Utility::get_hidden_property( $instance, 'asset_data' ),
			'Failed to assert that asset_data is an array.'
		);
	}
}
