<?php
/**
 * Class is responsible for all role related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Role.
 */
class Role {

	use Singleton;

	/**
	 * Role constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup Hooks.
	 */
	protected function setup_hooks() {
		add_action( 'init', array( $this, 'change_role_names' ) );
	}

	/**
	 * More appropriate role names for GatherPress application.
	 *
	 * @return array
	 */
	public function get_role_names() : array {
		return array(
			'administrator' => __( 'Organizer', 'gatherpress' ),
			'editor'        => __( 'Assistant Organizer', 'gatherpress' ),
			'author'        => __( 'Event Organizer', 'gatherpress' ),
			'contributor'   => __( 'Event Assistant', 'gatherpress' ),
			'subscriber'    => __( 'Member', 'gatherpress' ),
		);
	}

	/**
	 * Map WordPress role names to GatherPress names.
	 */
	public function change_role_names() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$role_name_changes = $this->get_role_names();

		foreach ( $role_name_changes as $key => $value ) {
			if ( is_array( $wp_roles->roles[ $key ] ) ) {
				$wp_roles->roles[ $key ]['name'] = $value;
			}

			if ( ! empty( $wp_roles->role_names[ $key ] ) ) {
				$wp_roles->role_names[ $key ] = $value;
			}
		}
	}

}
