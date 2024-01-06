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
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		parent::setup_hooks();

		add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
	}

	/**
	 * Get the slug for the credits section.
	 *
	 * This method returns the slug used to identify the credits section.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug for the credits section.
	 */
	protected function get_slug(): string {
		return 'credits';
	}

	/**
	 * Get the name for the credits section.
	 *
	 * This method returns the localized name for the credits section.
	 *
	 * @since 1.0.0
	 *
	 * @return string The localized name for the credits section.
	 */
	protected function get_name(): string {
		return __( 'Credits', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying credits.
	 *
	 * This method returns the priority at which credits should be displayed.
	 *
	 * @since 1.0.0
	 *
	 * @return int The priority for displaying credits. Higher values mean later execution.
	 */
	protected function get_priority(): int {
		return PHP_INT_MAX;
	}

	/**
	 * Callback function to render the settings section on the "Credits" page.
	 *
	 * This method serves as a callback function to render the settings section when the current settings page slug
	 * matches the plugin's slug. It removes the default action to render the settings form and instead calls the
	 * `credits_page` method to render content specific to the "Credits" page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $page The current settings page slug.
	 * @return void
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
	 * This method is responsible for rendering the custom "Credits" page in the plugin's settings.
	 * It loads credits data and uses a template to display the credits information.
	 *
	 * @since 1.0.0
	 *
	 * @return void
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
