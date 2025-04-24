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

use GatherPress\Core\Endpoints\Endpoint;
use GatherPress\Core\Utility;

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
	 * Directory path for plugin templates.
	 *
	 * @var string
	 */
	protected $plugin_template_dir;

	/**
	 * Class constructor.
	 *
	 * Initializes the `Endpoint_Template` object by setting the slug, callback, and
	 * plugin template directory. The parent constructor initializes the slug and callback,
	 * while this constructor adds the plugin template default.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $slug                The slug used to identify the endpoint in the URL.
	 * @param callable $callback            The callback function to retrieve file name and path of the endpoint template.
	 * @param string   $plugin_template_dir The directory path for the plugin templates.
	 */
	public function __construct( string $slug, callable $callback, string $plugin_template_dir = '' ) {
		parent::__construct( $slug, $callback );
		$this->plugin_template_dir = ( ! empty( $plugin_template_dir ) ) ? $plugin_template_dir : sprintf(
			'%s/includes/templates/endpoints',
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
		// and as such the 'Endpoint_Template's template_include hook would fail.
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
	 * prior 'Endpoint_Template's template_include hook would run.
	 * Therefore WordPress will throw an xml'ed 404 error,
	 * if nothing is hooked onto the 'do_feed_anything' action.
	 *
	 * That's the reason for this method, it delivers what WordPress wants
	 * and re-uses the parameters provided by the class.
	 *
	 * We expect that a endpoint, that contains the /feed/ string, only has one 'Redirect_Template' attached.
	 * This might be wrong or short sightened, please open an issue in that case: https://github.com/GatherPress/gatherpress/issues
	 *
	 * Until then, we *just* use the first of the provided endpoint-types,
	 * to hook into WordPress, which should be the valid template endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_feed_template() {
		load_template( $this->template_include() );
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
		$presets = $this->get_template_presets();

		$file_name = $presets['file_name'];
		$dir_path  = $presets['dir_path'] ?? $this->plugin_template_dir;

		// Check if the theme provides a custom template.
		$theme_template = $this->get_template_from_theme( $file_name );
		if ( $theme_template ) {
			return $theme_template;
		}

		// Check if the plugin has a template file.
		$plugin_template = $this->get_template_from_plugin( $file_name, $dir_path, );
		if ( $plugin_template ) {
			return $plugin_template;
		}

		// Fallback to the default template.
		return $template;
	}


	/**
	 * Retrieve template presets by invoking the callback.
	 *
	 * @return array Template preset data including file_name and optional dir_path.
	 */
	protected function get_template_presets(): array {
		return ( $this->callback )();
	}

	/**
	 * Locate a template in the theme or child theme.
	 *
	 * @todo Maybe better put in the Utility class?
	 *
	 * @param string $file_name The name of the template file.
	 * @return string The path to the theme template or an empty string if not found.
	 */
	protected function get_template_from_theme( string $file_name ): string {

		// locate_template() doesn't cares,
		// but locate_block_template() needs this to be an array.
		$templates = array( $file_name );

		// First, search for PHP templates, which block themes can also use.
		$template = locate_template( $templates );

		// Pass the result into the block template locator and let it figure
		// out whether block templates are supported and this template exists.
		$template = locate_block_template(
			$template,
			pathinfo( $file_name, PATHINFO_FILENAME ), // Name of the file without extension.
			$templates
		);

		return $template;
	}

	/**
	 * Build the full path to the plugin's template file.
	 *
	 * @todo Maybe better put in the Utility class?
	 *
	 * @param string $file_name The name of the template file.
	 * @param string $dir_path  The directory path where the template is stored.
	 * @return string The full path to the template file or an empty string if file not exists.
	 */
	protected function get_template_from_plugin( string $file_name, string $dir_path ): string {
		// Remove prefix to keep file-names simple,
		// for templates of core GatherPress.
		if ( $this->plugin_template_dir === $dir_path ) {
			$file_name = Utility::unprefix_key( $file_name );
		}

		$template = trailingslashit( $dir_path ) . $file_name;
		return file_exists( $template ) ? $template : '';
	}
}
