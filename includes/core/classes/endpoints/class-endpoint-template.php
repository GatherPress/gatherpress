<?php
/**
 * Class responsible for handling template-based endpoints in GatherPress.
 *
 * This file defines the `Endpoint_Template` class, which extends the base `Endpoint_Type` class
 * and manages custom templates for endpoints. It allows themes to override the default plugin
 * templates by checking the theme's template directory before falling back to the plugin's template.
 *
 * @package GatherPress\Core\Endpoints
 * @since 1.0.0
 */

namespace GatherPress\Core\Endpoints;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Handles template rendering for custom endpoints in GatherPress.
 *
 * The `Endpoint_Template` class extends the `Endpoint_Type` class and is responsible
 * for loading custom templates for specific endpoints. It allows for theme-based
 * overrides, giving themes the ability to provide their own template files for the
 * endpoints instead of using the default plugin templates.
 *
 * - It checks if a template exists in the current theme or child theme.
 * - If not found, it falls back to the template provided by the plugin.
 *
 * @since 1.0.0
 */
class Endpoint_Template extends Endpoint_Type {

	/**
	 * Activate Endpoint_Type by hooking into relevant parts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activate(): void {
		// Filters the path of the current template before including it.
		add_filter( 'template_include', array( $this, 'template_include' ) );
	}

	/**
	 * Filters the path of the current template before including it.
	 *
	 * This method checks if the theme or child theme provides a custom template for the
	 * current endpoint. If a theme template exists, it will use that; otherwise, it will
	 * fall back to the default template provided by the plugin. The template information
	 * is provided by the callback set during the construction of the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template The path of the default template to include.
	 * @return string          The path of the template to include, either from the theme or plugin.
	 */
	public function template_include( string $template ): string {
		$presets   = ( $this->callback )();
		$file_name = $presets['file_name'];

		/**
		 * Check if the theme has the same template included
		 * If that's the case, we don't want to load the template from the plugin,
		 * because templates should be overwritable by a theme or child theme.
		 *
		 * @see https://developer.wordpress.org/reference/functions/locate_template/
		 */
		$theme_tmpl = locate_template( $file_name );
		if ( $theme_tmpl ) {
			return $theme_tmpl;
		}

		// Prepare the path to the template directory from the given args
		// or fallback to the default in the plugin folder.
		$dir_path = ( isset( $presets['dir_path'] ) && ! empty( $presets['dir_path'] ) )
			? $presets['dir_path']
			: sprintf(
				'%s/includes/templates/endpoints',
				GATHERPRESS_CORE_PATH
			);

		// Combine the directory path and file name to get the full path to the template.
		$file_path = sprintf(
			'%s/%s',
			$dir_path,
			$file_name
		);

		// If no file exist, do not include and rerturn the default.
		if ( ! file_exists( $file_path ) ) {
			return $template;
		}
		// Return the plugin's template if no theme override exists.
		return $file_path;
	}
}
