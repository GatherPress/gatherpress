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
class Posttype_Single_Feed_Endpoint extends Endpoint {

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
		string $post_type = 'gatherpress_venue'
	) {
		// Expression for the post type archive feeds,
		// for example 'event/feed/(custom-endpoint)(/)'.
		$reg_ex = '%s/([^/]+)/feed/(%s)/?$';

		parent::__construct(
			$query_var,
			$post_type,
			array( $this, 'is_valid' ),
			$types,
			$reg_ex,
		);
	}

	/**
	 * Validates if the current request is for a post type singular feed.
	 *
	 * This method checks if the current request is for an singular page of the specified
	 * post type and if it is a valid feed request (i.e., `is_feed()` returns true).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request is a valid feed request for the post type singular.
	 */
	public function is_valid(): bool {
		return is_singular( $this->type_object->name ) && is_feed();
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
	public function get_rewrite_url(): string {
		return add_query_arg(
			array(
				'post_type'              => $this->type_object->name,
				$this->type_object->name => '$matches[1]',
				'feed'                   => '$matches[2]',
			),
			'index.php'
		);
	}
}
