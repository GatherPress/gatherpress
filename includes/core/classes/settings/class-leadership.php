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

	use Singleton;

	/**
	 * Leadership constructor.
	 *
	 * Sets up the Leadership settings page with its name, description, sections, and slug.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		parent::__construct();

		$this->name     = __( 'Leadership', 'gatherpress' );
		$this->sections = $this->get_section();
		$this->slug     = 'leadership';
	}

	/**
	 * Get sections for the Leadership settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of sections and their settings.
	 */
	protected function get_section(): array {
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

		return array(
			'roles' => array(
				'name'        => __( 'Roles', 'gatherpress' ),
				'description' => __( 'GatherPress allows you to customize role labels to be more appropriate for events.', 'gatherpress' ),
				'options'     => apply_filters( 'gatherpress_roles', $roles ),
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
