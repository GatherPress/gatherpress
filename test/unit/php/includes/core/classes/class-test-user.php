<?php
/**
 * Class handles unit tests for GatherPress\Core\User.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\User;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_User.
 *
 * @coversDefaultClass \GatherPress\Core\User
 */
class Test_User extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = User::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'show_user_profile',
				'priority' => 10,
				'callback' => array( $instance, 'profile_fields' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'edit_user_profile',
				'priority' => 10,
				'callback' => array( $instance, 'profile_fields' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'personal_options_update',
				'priority' => 10,
				'callback' => array( $instance, 'save_profile_fields' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'edit_user_profile_update',
				'priority' => 10,
				'callback' => array( $instance, 'save_profile_fields' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for profile_fields.
	 *
	 * @covers ::profile_fields
	 *
	 * @return void
	 */
	public function test_profile_fields(): void {
		$instance = User::get_instance();
		$user     = $this->mock->user( true )->get();
		$markup   = Utility::buffer_and_return( array( $instance, 'profile_fields' ), array( $user ) );

		$this->assertStringContainsString( 'checked=\'checked\'', $markup, 'Failed to assert that checkbox is checked by default.' );

		update_user_meta( $user->ID, 'gp-event-updates-opt-in', 0 );

		$markup = Utility::buffer_and_return( array( $instance, 'profile_fields' ), array( $user ) );

		$this->assertStringNotContainsString( 'checked=\'checked\'', $markup, 'Failed to assert that checkbox is not checked.' );

		update_user_meta( $user->ID, 'gp-event-updates-opt-in', 1 );

		$markup = Utility::buffer_and_return( array( $instance, 'profile_fields' ), array( $user ) );

		$this->assertStringContainsString( 'checked=\'checked\'', $markup, 'Failed to assert that checkbox is checked.' );
	}
}
