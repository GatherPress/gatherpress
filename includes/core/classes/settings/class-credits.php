<?php
/**
 * Credits class for GatherPress settings.
 *
 * This class handles the "Credits" settings page in GatherPress, allowing users
 * to view and manage credits information. It extends the Base class to inherit
 * common settings page functionality.
 *
 * @package GatherPress\Core\Settings
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Credits.
 *
 * @since 1.0.0
 */
class Credits extends Base {

	use Singleton;

	/**
	 * Constructor method for initializing the Credits class.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		parent::__construct();

		$this->name     = __( 'Credits', 'gatherpress' );
		$this->priority = PHP_INT_MAX;
		$this->slug     = 'credits';

		$this->setup_hooks();
	}

	/**
	 * Setup hooks for the "Credits" settings page.
	 *
	 * @since 1.0.0
	 */
	protected function setup_hooks(): void {
		parent::setup_hooks();

		add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
	}

	/**
	 * Callback function to render the settings section on the "Credits" page.
	 *
	 * @param string $page The current settings page slug.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function settings_section( string $page ): void {
		if ( Utility::unprefix_key( $page ) === $this->slug ) {
			remove_action( 'gatherpress_settings_section', array( Settings::get_instance(), 'render_settings_form' ) );

			$this->credits_page();
		}
	}

	/**
	 * Render the custom credits page.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function credits_page(): void {
		// Load credits data.
		$credits = include_once sprintf( '%s/includes/data/credits/latest.php', GATHERPRESS_CORE_PATH );

		// Render the credits page template with data.
		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/credits/index.php', GATHERPRESS_CORE_PATH ),
			array( 'credits' => $credits ),
			true
		);
	}

}
