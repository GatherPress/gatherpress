<?php
/**
 * Class is responsible for managing plugin settings.
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
 * Class Settings.
 */
class Settings {

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
		add_action( 'admin_menu', array( $this, 'options_page' ) );
		add_action( 'admin_head', array( $this, 'remove_sub_options' ) );
		add_filter( 'submenu_file', array( $this, 'select_menu' ) );
	}

	/**
	 * Setup options page.
	 */
	public function options_page() {
		add_options_page(
			__( 'GatherPress', 'gatherpress' ),
			__( 'GatherPress', 'gatherpress' ),
			'manage_options',
			'gp-general',
			[ $this, 'settings_page' ],
			6
		);

		$sub_pages = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page => $setting ) {
			if ( 'general' === $sub_page ) {
				continue;
			}

			$page = sprintf( 'gp-%s', $sub_page );

			add_options_page(
				$setting['name'],
				$setting['name'],
				'manage_options',
				$page,
				[ $this, 'settings_page' ]
			);
		}
	}

	/**
	 * Remove submenu pages from Settings menu.
	 */
	public function remove_sub_options() {
		$sub_pages = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page => $setting ) {
			if ( 'general' === $sub_page ) {
				continue;
			}

			remove_submenu_page( 'options-general.php', sprintf( 'gp-%s', $sub_page ) );
		}
	}

	/**
	 * Get sub pages for options page.
	 *
	 * @return array
	 */
	public function get_sub_pages(): array {
		$sub_pages = array(
			'general' => array(
				'name'     => __( 'General', 'gatherpress' ),
				'priority' => 1,
			),
			'roles' => array(
				'name'     => __( 'Roles', 'gatherpress' ),
			),
			'credits' => array(
				'name'     => __( 'Credits', 'gatherpress' ),
				'priority' => 99,
			),
		);

		$sub_pages = (array) apply_filters( 'gatherpress/settings/sub_pages', $sub_pages );

		uasort( $sub_pages, array( $this, 'sort_sub_pages_by_priority' ) );

		return $sub_pages;
	}

	/**
	 * Sort associative array by priority. 10 is default.
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return bool
	 */
	public function sort_sub_pages_by_priority( array $a, array $b ): bool {
		$a['priority'] = isset( $a['priority'] ) ? intval( $a['priority'] ) : 10;
		$b['priority'] = isset( $b['priority'] ) ? intval( $b['priority'] ) : 10;

		return ( $a['priority'] > $b['priority'] );
	}

	/**
	 * Render the options page.
	 */
	public function settings_page() {
		Utility::render_template(
			sprintf( '%s/templates/admin/settings.php', GATHERPRESS_CORE_PATH ),
			array(
				'sub_pages' => $this->get_sub_pages(),
				'page'      => $_GET['page'],
			),
			true
		);
	}

	/**
	 * Select GatherPress in menu for all sub pages.
	 *
	 * @param $submenu
	 *
	 * @return mixed|string
	 */
	public function select_menu( $submenu ) {
		if ( empty( $submenu ) ) {
			$sub_pages = $this->get_sub_pages();

			if ( isset( $sub_pages ) ) {
				$page = $_GET['page'] ?? '';
				$page = str_replace( 'gp-', '', $page ); // @todo add method that handles this.
				if ( isset( $sub_pages[ $page ] ) ) {
					$submenu = 'gp-general';
				}
			}
		}

		return $submenu;
	}

}
