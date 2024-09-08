<?php
/**
 * Handles feed-based endpoints for custom post types in GatherPress.
 *
 * This file defines the `Posttype_Feed_Endpoint` class, which extends the base `Endpoint`
 * class to handle custom feeds for post type archives. It allows users to define custom
 * feed URLs (e.g., RSS feeds) for post types such as `gatherpress_event`, while also allowing 
 * theme overrides for feed templates.
 *
 * @package GatherPress\Core\Endpoints
 * @since 1.0.0
 */

namespace GatherPress\Core\Endpoints;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Endpoints\Endpoint;

/**
 * Manages custom feed endpoints for post types in GatherPress.
 *
 * The `Posttype_Feed_Endpoint` class extends the base `Endpoint` class to create
 * custom feed URLs for post types. It handles URL rewriting for feeds and 
 * ensures that WordPress hooks into the appropriate feed template. The class also 
 * supports theme overrides, allowing developers to customize feed templates for
 * specific post types.
 *
 * @since 1.0.0
 */
class Posttype_Feed_Endpoint extends Endpoint {

	/**
	 * Class constructor.
	 *
	 * Initializes the `Posttype_Feed_Endpoint` for handling custom feeds for the 
	 * specified post type. It sets up a regular expression to match custom feed 
	 * URLs (e.g., `event/feed/custom-endpoint`) and hooks into WordPress to load 
	 * the appropriate feed template.
	 *
	 * @since 1.0.0
	 *
	 * @param string          $query_var  The query variable used to identify the feed endpoint in the URL.
	 * @param Endpoint_Type[] $types      List of endpoint types (templates/redirects) for the feed.
	 * @param string          $post_type  The post type for which the feed endpoint is being created. Default is `gatherpress_event`.
	 */
	 public function __construct(
		string $query_var,
		array $types,
		string $post_type = 'gatherpress_event',
	) {
		// Expression for the post type archive feeds,
		// for example 'event/feed/(custom-endpoint)(/)'.
		$reg_ex = '%s/feed/(%s)/?$';

		parent::__construct(
			$query_var,
			$post_type,
			array( $this, 'is_valid' ),
			$types,
			$reg_ex,
		);

		// Hook into WordPress' feed handling to load the custom feed template.
		add_action(
			sprintf(
				'do_feed_%s',
				$this->get_slugs( __NAMESPACE__ . '\Endpoint_Template')[0]
			),
			array( $this, 'load_template')
		);
	}

	 /**
	 * Load the theme-overridable feed template from the plugin.
	 *
	 * This method ensures that a feed template is loaded when a request is made to
	 * the custom feed endpoint. If the theme provides an override for the feed template,
	 * it will be used; otherwise, the default template from the plugin is loaded. The 
	 * method ensures that WordPress does not return a 404 for custom feed URLs.
	 *
	 * A call to any post types /feed/anything endpoint is handled by WordPress
	 * prior 'Endpoint_Template's template_include hook.
	 * WordPress will throw an xml'ed 404 error,
	 * if nothing is hooked onto the 'do_feed_anything' action.
	 *
	 * That's the reason for this method, it delivers what WordPress wants
	 * and re-uses the parameters provided by the class.
	 *
	 * We expect that a 'Posttype_Feed_Endpoint' only has one 'Redirect_Template' attached.
	 * This might be wrong or short sightened, please open an issue in that case: https://github.com/GatherPress/gatherpress/issues
	 *
	 * Until then, we *just* use the first of the provided endpoint-types,
	 * to hook into WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	 public function load_template() {
		load_template( $this->types[0]->template_include( false ) );
	}

	/**
	 * Validates if the current request is for a post type archive feed.
	 *
	 * This method checks if the current request is for an archive page of the specified
	 * post type and if it is a valid feed request (i.e., `is_feed()` returns true).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request is a valid feed request for the post type archive.
	 */
	 public function is_valid(): bool {
		return is_archive( $this->type_object->name ) && is_feed();
	}

	/**
	 * Constructs the rewrite URL for the custom feed endpoint.
	 *
	 * This method generates the rewrite URL for the custom feed endpoint based on the
	 * post type and the custom feed slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string The rewrite URL for the custom feed.
	 */
	public function get_rewrite_url() : string {
		return add_query_arg(
			array(
				'post_type' => $this->type_object->name,
				'feed'      => '$matches[1]',
			),
			'index.php'
		);
	}
}
