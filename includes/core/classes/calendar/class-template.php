<?php
/**
 * Class responsible for handling template-based endpoints in GatherPress.
 *
 * This file defines the `Template` class, which extends the base `Endpoint_Type` class
 * and manages custom templates for endpoints. It allows themes to override the default plugin
 * templates by checking the theme's template directory before falling back to the plugin's template.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Utility;

/**
 * Handles template rendering for custom endpoints in GatherPress.
 *
 * The `Template` class extends the `Endpoint_Type` class and is responsible
 * for loading custom templates for specific endpoints. It allows for theme-based
 * overrides, giving themes the ability to provide their own template files for the
 * endpoints instead of using the default plugin templates.
 *
 * - It checks if a template exists in the current theme or child theme.
 * - If not found, it falls back to the template provided by the plugin.
 *
 * @since 1.0.0
 */
class Template extends Endpoint_Type {

	/**
	 * Directory path for plugin templates.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $plugin_template_dir;

	/**
	 * Class constructor.
	 *
	 * Initializes the `Template` object by setting the slug, callback, and
	 * plugin template directory. The parent constructor initializes the slug and callback,
	 * while this constructor adds the plugin template default.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $slug                The slug used to identify the endpoint in the URL.
	 * @param callable $callback            The callback function to get file name and path of the endpoint template.
	 * @param string   $plugin_template_dir The directory path for the plugin templates.
	 */
	public function __construct( string $slug, callable $callback, string $plugin_template_dir = '' ) {
		parent::__construct( $slug, $callback );
		$this->plugin_template_dir = ( ! empty( $plugin_template_dir ) ) ? $plugin_template_dir : sprintf(
			'%s/includes/templates/calendar',
			GATHERPRESS_CORE_PATH
		);
	}

	/**
	 * Activate Endpoint_Type by hooking into relevant parts.
	 *
	 * @since 1.0.0
	 *
	 * @param Endpoint|null $endpoint Class for custom rewrite endpoints and their query handling in GatherPress.
	 * @return void
	 */
	public function activate( ?Endpoint $endpoint = null ): void {
		// A call to any /feed/ endpoint is handled different by WordPress
		// and as such the 'Template's template_include hook would fail.
		$feed_slug = ( null !== $endpoint ) ? $endpoint->has_feed() : false;
		if ( $feed_slug ) {
			// Hook into WordPress' feed handling to load the custom feed template.
			add_action( sprintf( 'do_feed_%s', $feed_slug ), array( $this, 'load_feed_template' ) );
		} else {
			// Filters the path of the current template before including it.
			add_filter( 'template_include', array( $this, 'template_include' ) );
		}
	}

	/**
	 * Load the theme-overridable feed template from the plugin.
	 *
	 * This method ensures that a feed template is loaded when a request is made to
	 * a custom feed endpoint. If the theme provides an override for the feed template,
	 * it will be used; otherwise, the default template from the plugin is loaded. The
	 * method ensures that WordPress does not return a 404 for custom feed URLs.
	 *
	 * A call to any post types /feed/anything endpoint is handled by WordPress
	 * prior 'Template's template_include hook would run.
	 * Therefore WordPress will throw an xml'ed 404 error,
	 * if nothing is hooked onto the 'do_feed_anything' action.
	 *
	 * That's the reason for this method, it delivers what WordPress wants
	 * and re-uses the parameters provided by the class.
	 *
	 * We expect that a endpoint, that contains the /feed/ string, only has one 'Redirect_Template' attached.
	 * This might be wrong or short sightened, please open an issue in that case
	 * under https://github.com/GatherPress/gatherpress/issues.
	 *
	 * Until then, we *just* use the first of the provided endpoint-types,
	 * to hook into WordPress, which should be the valid template endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_feed_template(): void {
		$presets  = $this->get_template_presets();
		$dir_path = $presets['dir_path'] ?? $this->plugin_template_dir;
		$path     = Utility::locate_template(
			$presets['file_name'],
			$dir_path,
			$this->plugin_template_dir === $dir_path
		);

		if ( $path ) {
			Utility::render_template( $path, array(), true );
		}
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
	 * @param string $template The path of the default template to include,
	 *                         defaults to '' so that the template loader keeps looking for templates.
	 * @return string          The path of the template to include, either from the theme or plugin.
	 */
	public function template_include( string $template = '' ): string {
		$presets  = $this->get_template_presets();
		$dir_path = $presets['dir_path'] ?? $this->plugin_template_dir;
		$resolved = Utility::locate_template(
			$presets['file_name'],
			$dir_path,
			$this->plugin_template_dir === $dir_path
		);

		if ( $resolved ) {
			return $resolved;
		}

		return $template;
	}

	/**
	 * Retrieve template presets by invoking the callback.
	 *
	 * @since 1.0.0
	 *
	 * @return array Template preset data including file_name and optional dir_path.
	 */
	protected function get_template_presets(): array {
		return ( $this->callback )();
	}
}
