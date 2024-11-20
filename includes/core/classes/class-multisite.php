<?php
/**
 * Main class for managing GatherPress in a WordPress Multisite.
 *
 * This class handles .... in the GatherPress plugin.
 *
 * @package GatherPress/Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Multisite.
 *
 * Core class for managing GatherPress in a WordPress Multisite.
 *
 * @since 1.0.0
 */
class Multisite {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

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
		add_action( 'init', array( $this, 'init_shared_options' ), PHP_INT_MIN );
	}

	/**
	 * Fires after WordPress has finished loading but before any headers are sent.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_shared_options(): void {

		// DEVELOPMENT ONLY // REMOVE BEFORE MERGE // Set Site option "gatherpress_shared_options" to enable feature, until UI, or filter, or whatever is in place.
		add_filter(
			sprintf( 'pre_site_option_%s', Utility::prefix_key( 'shared_options' ) ),
			function () {
				return array( Utility::prefix_key( 'general' ) );
			}
		);

		$shared_options = $this->get_shared_options();
		if ( empty( $shared_options ) ) {
			return;
		}

		array_map(
			array( $this, 'init_shared_options_for' ),
			$shared_options
		);
	}

	/**
	 * Get list of shared setting-slugs, when conditions are met.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function get_shared_options(): array {
		$shared_options = get_site_option( Utility::prefix_key( 'shared_options' ) );
		return (
			is_multisite() &&
			is_array( $shared_options ) &&
			! empty( $shared_options )
		) ? $shared_options : array();
	}

	/**
	 * Hook into options-processing to share (get & set) GatherPress options per network.
	 *
	 * @param  string $option Option name.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function init_shared_options_for( string $option ): void {
		add_filter( sprintf( 'pre_option_%s', $option ), array( $this, 'pre_option' ), 10, 2 );

		if ( is_main_site() ) {
			add_action( sprintf( 'update_option_%s', $option ), array( $this, 'update_option' ), 10, 3 );
		} else {
			// $hook_suffix = 'gatherpress_event_page_gatherpress_general';
			$hook_suffix = sprintf( 'gatherpress_event_page_%s', $option );
			add_action( sprintf( 'admin_head-%s', $hook_suffix ), array( $this, 'read_only_js' ) );
		}
	}

	/**
	 * Filters the value of an existing option before it is retrieved.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $pre_option    The value to return instead of the option value. This differs from <code>$default_value</code>, which is used as the fallback value in the event the option doesn't exist elsewhere in get_option(). Default false (to skip past the short-circuit).
	 * @param string $option        Option name.
	 * @return mixed The value to return instead of the option value. This differs from <code>$default_value</code>, which is used as the fallback value in the event the option doesn't exist elsewhere in get_option(). Default false (to skip past the short-circuit).
	 */
	public function pre_option( $pre_option, string $option ) {
		return get_site_option( $option );
	}

	/**
	 * Fires after the value of a specific option has been successfully updated.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 * @param string $option    Option name.
	 */
	public function update_option( $old_value, $value, string $option ): void {
		update_site_option( $option, $value );
	}

	/**
	 * Fires in head section for a specific admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function read_only_js(): void { ?>
		<style>
			/* Overwriting build/style-settings_style.css */
			.gatherpress-settings .components-form-token-field__input-container {
				background: unset;
			}
		</style>
		<script>
			document.addEventListener("DOMContentLoaded", function () {
				const formWrapper = document.querySelector('.gatherpress-settings form');

				if (!formWrapper) return;

				// Select all form fields inside the wrapper
				const formFields = formWrapper.querySelectorAll("input, textarea, select, button");

				formFields.forEach(field => {
					switch (field.tagName) {
						case "INPUT":
						case "TEXTAREA":
							field.setAttribute("readonly", true);
							break;
						case "SELECT":
						case "BUTTON":
							field.disabled = true;
							break;
					}
					// Additional handling for input types
					if (field.type === "checkbox" || field.type === "radio" || field.type === "submit") {
						field.disabled = true;
					}
				});
			});
		</script>
	<?php }
}
