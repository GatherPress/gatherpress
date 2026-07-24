<?php
/**
 * Roles settings page for GatherPress.
 *
 * This class handles the "Roles" settings page in GatherPress, providing
 * options for customizing user roles and their labels.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Roles.
 *
 * Handles the "Roles" settings page for GatherPress.
 *
 * @since 0.34.0
 */
final class Roles extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the roles settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string The slug for the roles settings page.
	 */
	protected function get_slug(): string {
		return 'roles_settings';
	}

	/**
	 * Get the name for the roles settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string The localized name for the roles settings page.
	 */
	protected function get_name(): string {
		return __( 'Roles', 'gatherpress' );
	}

	/**
	 * Get sections for the Roles settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return array An array of sections and their settings.
	 */
	protected function get_sections(): array {
		$roles = array(
			'organizer' => array(
				'labels' => array(
					'name'          => __( 'Organizers', 'gatherpress' ),
					'singular_name' => __( 'Organizer', 'gatherpress' ),
					'plural_name'   => __( 'Organizers', 'gatherpress' ),
				),
				'field'  => array(
					'type'    => 'autocomplete',
					'options' => array(
						'type'    => 'user',
						'label'   => __( 'Select Organizers', 'gatherpress' ),
						'default' => '[]',
					),
				),
			),
		);

		/**
		 * Filter the list of roles for GatherPress.
		 *
		 * This filter allows modification of the list of user roles used by GatherPress.
		 * By default, GatherPress supports only the 'Organizers' role.
		 *
		 * @since 0.27.0
		 *
		 * @param array $roles An array of user roles supported by GatherPress.
		 *                     By default, it includes only the 'Organizers' role.
		 * @return array The modified array of user roles.
		 */
		$roles = apply_filters( 'gatherpress_roles', $roles );

		return array(
			'roles' => array(
				'name'        => __( 'Roles', 'gatherpress' ),
				'description' => __( 'Customize role labels to be more appropriate for events.', 'gatherpress' ),
				'options'     => $roles,
			),
		);
	}

	/**
	 * Retrieve a list of user roles.
	 *
	 * @since 0.34.0
	 *
	 * @return array An array containing user roles and their corresponding settings.
	 */
	public function get_user_roles(): array {
		$sub_pages = Settings::get_instance()->get_sub_pages();

		return (array) $sub_pages['roles_settings']['sections']['roles']['options'];
	}

	/**
	 * Retrieve the role of a user.
	 *
	 * @since 0.34.0
	 *
	 * @param int $user_id User ID.
	 *
	 * @return string The role of the user, or 'Member' if no matching role is found.
	 */
	public function get_user_role( int $user_id ): string {
		$settings   = Settings::get_instance();
		$user_roles = $this->get_user_roles();
		$default    = __( 'Member', 'gatherpress' );

		foreach ( array_keys( $user_roles ) as $role ) {
			$users = $settings->get( $role );

			if ( empty( $users ) ) {
				continue;
			}

			foreach ( json_decode( $users ) as $user ) {
				if ( intval( $user->id ) === $user_id ) {
					return $user_roles[ $role ]['labels']['singular_name'] ?? $default;
				}
			}
		}

		return $default;
	}
}
