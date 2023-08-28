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
					'gatherpress/event-venue',
					'gatherpress/online-event',
					'gatherpress/rsvp',
					'gatherpress/rsvp-response',
					'gatherpress/venue-information',
				),
			),
			array(
				'page',
				array(
					'gatherpress/add-to-calendar',
					'gatherpress/event-date',
					'gatherpress/event-venue',
					'gatherpress/online-event',
					'gatherpress/rsvp',
					'gatherpress/rsvp-response',
					'gatherpress/venue-information',
				),
			),
			array(
				'gp_event',
				array(
					'gatherpress/venue-information',
				),
			),
			array(
				'gp_venue',
				array(
					'gatherpress/add-to-calendar',
					'gatherpress/event-date',
					'gatherpress/event-venue',
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

}
