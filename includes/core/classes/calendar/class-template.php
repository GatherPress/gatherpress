<?php
/**
 * Class responsible for handling template-based endpoints in GatherPress.
 *
 * This file defines the `Template` class, which extends the base `Endpoint_Type` class
 * and manages custom templates for endpoints. It allows themes to override the default plugin
 * templates by checking the theme's template directory before falling back to the plugin's template.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.34.0
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
 * @since 0.34.0
 */
class Template extends Endpoint_Type {

	/**
	 * Directory path for plugin templates.
	 *
	 * @since 0.34.0
	 *
	 * @var string
	 */
	protected readonly string $plugin_template_dir;

	/**
	 * Class constructor.
	 *
	 * Initializes the `Template` object by setting the slug, callback, and
	 * plugin template directory. The parent constructor initializes the slug and callback,
	 * while this constructor adds the plugin template default.
	 *
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @param Endpoint|null $endpoint Class for custom rewrite endpoints and their query handling in GatherPress.
	 *
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
	 * Ensures that a feed template is loaded when a request hits a custom
	 * feed endpoint — without this `do_feed_{slug}` action callback,
	 * WordPress would XML-404 on `/feed/{slug}` URLs.
	 *
	 * Resolves the template via `template_include()` (same theme → plugin
	 * lookup the non-feed branch uses) and then runs it via
	 * `Utility::render_template()` so the feed body is emitted. Globals
	 * (`$wp_query`, `$post`) are already set by WordPress before
	 * `do_feed_{slug}` fires, so the file does not need
	 * `load_template()`'s explicit `set_global_vars()`.
	 *
	 * We expect that an endpoint containing `/feed/` only has one
	 * `Redirect_Template` attached. If that assumption ever breaks, open an
	 * issue at https://github.com/GatherPress/gatherpress/issues — until
	 * then we just use the first of the provided endpoint-types.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function load_feed_template(): void {
		$resolved = $this->template_include();

		if ( ! empty( $resolved ) ) {
			Utility::render_template( $resolved, array(), true );
		}
	}

	/**
	 * Filters the path of the current template before including it.
	 *
	 * Delegates the theme → block-template → plugin fallback walk to
	 * `Utility::locate_template()` and returns the resolved path. Falls
	 * back to the WP-supplied default when nothing is found, so the
	 * template loader can keep looking.
	 *
	 * @since 0.34.0
	 *
	 * @param string $template The path of the default template to include,
	 *                         defaults to '' so that the template loader keeps looking for templates.
	 * @return string          The path of the template to include, either from the theme or plugin.
	 */
	public function template_include( string $template = '' ): string {
		$presets  = $this->get_template_presets();
		$dir_path = $presets['dir_path'] ?? $this->plugin_template_dir;
		$resolved = Utility::locate_template( $presets['file_name'], $dir_path );

		return $resolved ? $resolved : $template;
	}

	/**
	 * Retrieve template presets by invoking the callback.
	 *
	 * @since 0.34.0
	 *
	 * @return array Template preset data including file_name and optional dir_path.
	 */
	protected function get_template_presets(): array {
		return ( $this->callback )();
	}
}
