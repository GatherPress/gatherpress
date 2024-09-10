<?php
/**
 * Handles feed-based endpoints for custom taxonomies in GatherPress.
 *
 * This file defines the `Posttype_Feed_Endpoint` class, which extends the base `Endpoint`
 * class to handle custom feeds for taxonomy archives. It allows users to define custom
 * feed URLs (e.g., RSS feeds) for taxonomies such as `gatherpress_topic`, while also allowing
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
 * Manages custom feed endpoints for taxonomies in GatherPress.
 *
 * The `Taxonomy_Feed_Endpoint` class extends the base `Endpoint` class to create
 * custom feed URLs for taxonomies. It handles URL rewriting for feeds and
 * ensures that WordPress hooks into the appropriate feed template.
 *
 * @since 1.0.0
 */
class Taxonomy_Feed_Endpoint extends Endpoint {

	/**
	 * Class constructor.
	 *
	 * Initializes the `Taxonomy_Feed_Endpoint` for handling custom feeds for the
	 * specified taxonomy. It sets up a regular expression to match custom feed
	 * URLs (e.g., `topic/wordcamp/feed/custom-endpoint`) and hooks into WordPress to load
	 * the appropriate feed template.
	 *
	 * @since 1.0.0
	 *
	 * @param Endpoint_Type[] $types      List of endpoint types (templates/redirects) for the feed.
	 * @param string          $query_var  The query variable used to identify the feed endpoint in the URL.
	 * @param string          $taxonomy   (Optional) The taxonomy for which the feed endpoint is being created. Default is `gatherpress_topic`.
	 */
	public function __construct(
		array $types,
		string $query_var,
		string $taxonomy = 'gatherpress_topic'
	) {
		// Expression for the taxonomy archive feeds,
		// for example 'topic/wordcamp/feed/(custom-endpoint)(/)'.
		$reg_ex = '%s/([^/]+)/feed/(%s)/?$';

		parent::__construct(
			$query_var,
			$taxonomy,
			array( $this, 'is_valid' ),
			$types,
			$reg_ex,
			'taxonomy',
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
		return is_archive( $this->type_object->name ) && is_feed();
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
			$this->object_type       => $this->type_object->name,
			$this->type_object->name => '$matches[1]',
			'feed'                   => '$matches[2]',
		);
	}
}
