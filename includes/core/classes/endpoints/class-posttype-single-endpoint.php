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
 * Key features of this class include:
 * - Defining a custom regular expression for matching single post type URLs.
 * - Using the `is_singular()` function to validate if the request is for a singular post.
 * - Supporting the addition of custom endpoint types (like redirects or templates) for post types.
 *
 * @since 1.0.0
 */
class Posttype_Single_Endpoint extends Endpoint {

	/**
	 * Class constructor.
	 *
	 * Initializes the `Posttype_Single_Endpoint` object and sets up the custom regular expression
	 * for handling singular post type endpoints. It also ensures that the parent class is properly
	 * initialized with the necessary parameters and hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param string          $query_var The query variable used to identify the endpoint in the URL.
	 * @param Endpoint_Type[] $types     List of endpoint types (redirects/templates) to be registered for this post type.
	 * @param string          $post_type The post type for which this endpoint is being registered.
	 */
	public function __construct(
		string $query_var,
		array $types,
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
