<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Roles.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Roles;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Roles.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Roles
 */
class Test_Roles extends Base {

	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Roles::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'roles_settings', $slug, 'Failed to assert slug is roles_settings.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = Roles::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'Roles', $name, 'Failed to assert name is Roles.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = Roles::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( 10, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for get_sections method.
	 *
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_get_sections(): void {
		$instance = Roles::get_instance();

		$section = Utility::invoke_hidden_method( $instance, 'get_sections' );
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
		$instance   = Roles::get_instance();
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
		$instance = Roles::get_instance();
		$user     = $this->mock->user()->get();

		$this->assertSame( 'Member', $instance->get_user_role( $user->ID ), 'Failed to assert user is Member.' );

		$option = array(
			'organizer' => wp_json_encode(
				array(
					array(
						'id'    => $user->ID,
						'slug'  => $user->data->user_nicename,
						'value' => $user->data->user_login,
					),
				)
			),
		);

		update_option( 'gatherpress_settings', $option );

		$this->assertSame( 'Organizer', $instance->get_user_role( $user->ID ), 'Failed to assert user is Organizer.' );

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for get_user_role with empty role data (guard clause).
	 *
	 * When a role setting contains an empty JSON array, the method
	 * should skip it and return the default 'Member' role.
	 *
	 * @covers ::get_user_role
	 *
	 * @return void
	 */
	public function test_get_user_role_with_empty_role_data(): void {
		$instance = Roles::get_instance();

		// Set organizer to '0' which passes Settings::get() but triggers empty() guard.
		update_option( 'gatherpress_settings', array( 'organizer' => '0' ) );

		$user = $this->mock->user()->get();

		$this->assertSame(
			'Member',
			$instance->get_user_role( $user->ID ),
			'Failed to assert user is Member when role data is empty.'
		);

		delete_option( 'gatherpress_settings' );
	}
}
