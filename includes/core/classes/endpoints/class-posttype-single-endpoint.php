<?php
/**
 * Single Post Type Endpoint.
 *
 * This file defines the `Posttype_Single_Endpoint` class, which extends the base `Endpoint`
 * class and provides specific behavior for single post type endpoints. This class is used
 * to create custom rewrite rules for singular post types, allowing for additional
 * functionality (e.g., event-based URLs like `event/my-sample-event/custom-endpoint`).
 *
 * @package GatherPress\Core\Endpoints
 * @since 1.0.0
 */

namespace GatherPress\Core\Endpoints;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Endpoints\Endpoint;

/**
 * Endpoint for Singular Post Types in GatherPress.
 *
 * The `Posttype_Single_Endpoint` class extends the base `Endpoint` class to specifically
 * handle custom endpoints for singular post types. This is useful for adding additional
 * behavior to specific post types like events, venues, or any custom post type that
 * needs custom URLs for singular items.
 *
 * @since 1.0.0
 */
class Posttype_Single_Endpoint extends Endpoint {

	/**
	 * Class constructor.
	 *
	 * Initializes the `Posttype_Single_Endpoint` object for handling custom feeds for the
	 * specified post type. It sets up a regular expression to match custom feed
	 * URLs (e.g., `event/my-sample-event/custom-endpoint`) and hooks into WordPress to load
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
		// Regular expression to match singular event endpoints.
		// Example: 'event/my-sample-event/(custom-endpoint)(/)'.
		$reg_ex = '%s/([^/]+)/(%s)/?$';

		parent::__construct(
			$query_var,
			$post_type,
			array( $this, 'is_valid' ),
			$types,
			$reg_ex,
		);
	}

	/**
	 * Validates if the current query is for a singular post of the specified post type.
	 *
	 * This method uses the WordPress `is_singular()` function to check if the current request
	 * is for a single post of the post type provided when registering the endpoint. It ensures
	 * that the endpoint logic only applies to single posts, not archives or other post type queries.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current query is for a singular post of the post type, false otherwise.
	 */
	public function is_valid(): bool {
		return is_singular( $this->type_object->name );
	}
}
