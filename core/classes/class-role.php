<?php
/**
 * Class is responsible for all role related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use \GatherPress\Core\Traits\Singleton;

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
	}

	public function get_default_role_names(): array {
		$defaults = array(
			'administrator' => __( 'Organizer', 'gatherpress' ),
			'editor'        => __( 'Assistant Organizer', 'gatherpress' ),
			'author'        => __( 'Event Organizer', 'gatherpress' ),
			'contributor'   => __( 'Event Assistant', 'gatherpress' ),
			'subscriber'    => __( 'Member', 'gatherpress' ),
		);

		return apply_filters( 'gatherpress/roles/default_names', $defaults );
	}

	/**
	 * More appropriate role names for GatherPress application.
	 *
	 * @return array
	 */
	public function get_roles(): array {
		global $wp_roles;

		$settings = array();
		$roles    = $wp_roles->roles;

		foreach ( $roles as $role => $value ) {
			$settings[ $role ] = $value['name'];
		}

		return $settings;
	}

	/**
	 * Return role settings that are either saved or default.
	 *
	 * @todo temporary mapping to roles, this will be revisited as a taxonomy to assign members.
	 *
	 * @return array
	 */
	public function get_role_settings(): array {
		return array(
			'administrator' => __( 'Organizer', 'gatherpress' ),
			'editor'        => __( 'Assistant Organizer', 'gatherpress' ),
			'author'        => __( 'Event Organizer', 'gatherpress' ),
			'contributor'   => __( 'Event Assistant', 'gatherpress' ),
			'subscriber'    => __( 'Member', 'gatherpress' ),
		);
	}

}
