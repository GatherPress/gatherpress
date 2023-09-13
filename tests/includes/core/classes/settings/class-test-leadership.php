<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Leadership.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Leadership;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Leadership.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Leadership
 */
class Test_Leadership extends Base {

	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$instance = Leadership::get_instance();

		Utility::invoke_hidden_method( $instance, '__construct' );

		$this->assertSame(
			'Leadership',
			Utility::get_hidden_property( $instance, 'name' ),
			'Failed to assert name matches Leadership.'
		);

		$this->assertSame(
			'leadership',
			Utility::get_hidden_property( $instance, 'slug' ),
			'Failed to assert slug matches leadership.'
		);
	}

	/**
	 * Coverage for get_section method.
	 *
	 * @covers ::get_section
	 *
	 * @return void
	 */
	public function test_get_section(): void {
		$instance = Leadership::get_instance();

		$section = Utility::invoke_hidden_method( $instance, 'get_section' );
		$this->assertSame( 'Roles', $section['roles']['name'], 'Failed to assert name is Roles.' );
		$this->assertSame(
			'Organizers',
			$section['roles']['options']['organizer']['labels']['name'],
			'Failed to assert name is Organizers.'
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
		$instance   = Leadership::get_instance();
		$user_roles = $instance->get_user_roles();

		$this->assertIsArray( $user_roles['organizer'], 'Failed to assert user role is an array.' );
	}

	/**
	 * Coverage for get_user_role method.
	 *
	 * @covers ::get_user_role
	 *
	 * @return void
	 */
	public function test_get_user_role(): void {
		$instance = Leadership::get_instance();
		$user     = $this->mock->user()->get();

		$this->assertSame( 'Member', $instance->get_user_role( $user->ID ), 'Failed to assert user is Member.' );

		$option = array(
			'roles' => array(
				'organizer' => wp_json_encode(
					array(
						array(
							'id'    => $user->ID,
							'slug'  => $user->data->user_nicename,
							'value' => $user->data->user_login,
						),
					)
				),
			),
		);

		update_option( 'gp_leadership', $option );

		$this->assertSame( 'Organizer', $instance->get_user_role( $user->ID ), 'Failed to assert user is Organizer.' );

		delete_option( 'gp_leadership' );
	}

}
