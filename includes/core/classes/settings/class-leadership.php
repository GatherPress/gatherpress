<?php
/**
 * Leadership settings page.
 *
 * This class represents the Leadership settings page in the GatherPress plugin.
 * It provides options for customizing user roles and their labels.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Leadership.
 *
 * Represents the Leadership settings page for GatherPress.
 *
 * @since 1.0.0
 */
class Leadership extends Base {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the leadership section.
	 *
	 * This method returns the slug used to identify the leadership section.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug for the leadership section.
	 */
	protected function get_slug(): string {
		return 'leadership';
	}

	/**
	 * Get the name for the leadership section.
	 *
	 * This method returns the localized name for the leadership section.
	 *
	 * @since 1.0.0
	 *
	 * @return string The localized name for the leadership section.
	 */
	protected function get_name(): string {
		return __( 'Leadership', 'gatherpress' );
	}

	/**
	 * Get sections for the Leadership settings page.
	 *
	 * @since 1.0.0
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
						'type'  => 'user',
						'label' => __( 'Select Organizers', 'gatherpress' ),
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
		 * @since 1.0.0
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
	 * This method returns an array of user roles defined for GatherPress. User roles
	 * are used to customize role labels to be more appropriate for events.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing user roles and their corresponding settings.
	 */
	public function get_user_roles(): array {
		$sub_pages = Settings::get_instance()->get_sub_pages();

		return (array) $sub_pages['leadership']['sections']['roles']['options'];
	}

	/**
	 * Retrieve the role of a user.
	 *
	 * This method returns the role of a user identified by their User ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return string The role of the user, or 'Member' if no matching role is found.
	 */
	public function get_user_role( int $user_id ): string {
		$leadership = get_option( Utility::prefix_key( 'leadership' ) );
		$roles      = $leadership['roles'] ?? array();
		$default    = __( 'Member', 'gatherpress' );

		foreach ( $roles as $role => $users ) {
			foreach ( json_decode( $users ) as $user ) {
				if ( intval( $user->id ) === $user_id ) {
					$roles = $this->get_user_roles();

					return $roles[ $role ]['labels']['singular_name'] ?? $default;
				}
			}
		}

		return $default;
	}
}
