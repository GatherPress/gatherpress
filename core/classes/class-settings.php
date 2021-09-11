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
	}

	public function options_page() {
		add_options_page(
			__( 'GatherPress', 'gatherpress' ),
			__( 'GatherPress', 'gatherpress' ),
			'manage_options',
			'gatherpress',
			[ $this, 'gatherpress_settings_page' ]
		);
	}

	public function gatherpress_settings_page() {
		echo Utility::render_template(
			sprintf( '%s/templates/admin/settings.php', GATHERPRESS_CORE_PATH ),
			array(
			)
		);
	}


}
