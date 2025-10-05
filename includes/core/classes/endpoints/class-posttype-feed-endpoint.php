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
	 * @param Endpoint_Type[] $types      List of endpoint types (templates/redirects) for the feed.
	 * @param string          $query_var  The query variable used to identify the feed endpoint in the URL.
	 * @param string          $post_type  (Optional) The post type for which the feed endpoint is being created. Default is `gatherpress_event`.
	 */
	public function __construct(
		array $types,
		string $query_var,
		string $post_type = 'gatherpress_event'
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
		return is_post_type_archive( $this->type_object->name ) && is_feed();
	}

	/**
	 * Defines the rewrite replacement attributes for the custom feed endpoint.
	 *
	 * This method defines the rewrite replacement attributes
	 * for the custom feed endpoint to be further processed by add_rewrite_rule().
	 *
	 * @since 1.0.0
	 *
	 * @return array The rewrite replacement attributes for add_rewrite_rule().
	 */
	public function get_rewrite_atts(): array {
		return array(
			$this->object_type => $this->type_object->name,
			'feed'             => '$matches[1]',
		);
	}
}
