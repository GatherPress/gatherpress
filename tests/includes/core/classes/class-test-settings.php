<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Settings;
use PMC\Unit_Test\Base;

/**
 * Class Test_Settings.
 *
 * @coversDefaultClass \GatherPress\Core\Settings
 */
class Test_Settings extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Settings::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_menu',
				'priority' => 10,
				'callback' => array( $instance, 'options_page' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_head',
				'priority' => 10,
				'callback' => array( $instance, 'remove_sub_options' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_settings' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'submenu_file',
				'priority' => 10,
				'callback' => array( $instance, 'select_menu' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for get_name_field method.
	 *
	 * @covers ::get_name_field
	 *
	 * @return void
	 */
	public function test_get_name_field(): void {
		$instance = Settings::get_instance();
		$expects  = 'sub_page[section][option]';

		$this->assertSame( $expects, $instance->get_name_field( 'sub_page', 'section', 'option' ) );
	}

	/**
	 * Coverage for get_sub_pages method.
	 *
	 * @covers ::get_sub_pages
	 * @covers ::get_general_page
	 * @covers ::get_leadership_page
	 * @covers ::get_credits_page
	 *
	 * @return void
	 */
	public function test_get_sub_pages(): void {
		$instance  = Settings::get_instance();
		$sub_pages = $instance->get_sub_pages();

		$this->assertIsArray( $sub_pages['general'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['leadership'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['credits'], 'Failed to assert sub page is an array.' );
		$this->assertSame(
			'general',
			array_key_first( $sub_pages ),
			'Failed to assert that general is first key.'
		);
		$this->assertSame(
			'credits',
			array_key_last( $sub_pages ),
			'Failed to assert that credits is last key.'
		);
	}

	/**
	 * Coverage for get_user_roles method.
	 *
	 * @covers ::get_user_roles
	 *
	 * @return void
	 */
	public function test_get_user_roles(): void {
		$instance   = Settings::get_instance();
		$user_roles = $instance->get_user_roles();

		$this->assertIsArray( $user_roles['organizers'], 'Failed to assert user role is an array.' );
	}

}
