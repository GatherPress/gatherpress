<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Leadership.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Leadership;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Leadership.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Leadership
 */
class Test_Leadership extends Base {
	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Leadership::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'leadership', $slug, 'Failed to assert slug is leadership.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = Leadership::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'Leadership', $name, 'Failed to assert name is Leadership.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = Leadership::get_instance();
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
		$instance = Leadership::get_instance();

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

		update_option( 'gatherpress_leadership', $option );

		$this->assertSame( 'Organizer', $instance->get_user_role( $user->ID ), 'Failed to assert user is Organizer.' );

		delete_option( 'gatherpress_leadership' );
	}
}
