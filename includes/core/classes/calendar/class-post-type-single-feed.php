<?php
/**
 * Handles feed-based endpoints for single posts of custom post types in GatherPress.
 *
 * This file defines the `Post_Type_Single_Feed` class, which extends the base
 * `Endpoint` class to handle custom feeds attached to a single post (e.g.,
 * `/event/my-event/feed/ical`). It allows theme overrides for feed templates.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.34.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Manages custom feed endpoints for post types in GatherPress.
 *
 * The `Post_Type_Single_Feed` class extends the base `Endpoint` class to create
 * custom feed URLs for post types. It handles URL rewriting for feeds and
 * ensures that WordPress hooks into the appropriate feed template.
 *
 * @since 0.34.0
 */
final class Post_Type_Single_Feed extends Endpoint {

	/**
	 * Class constructor.
	 *
	 * Initializes the `Post_Type_Single_Feed` for handling custom feeds for the
	 * specified post type. It sets up a regular expression to match custom feed
	 * URLs (e.g., `venue/bangkok/feed/custom-endpoint`) and hooks into WordPress to load
	 * the appropriate feed template.
	 *
	 * @since 0.34.0
	 *
	 * @param Endpoint_Type[] $types      List of endpoint types (templates/redirects) for the feed.
	 * @param string          $query_var  The query variable used to identify the feed endpoint in the URL.
	 * @param string          $post_type  (Optional) The post type for which the feed endpoint is being created.
	 *                                    Default is `gatherpress_venue`.
	 */
	public function __construct(
		array $types,
		string $query_var,
		string $post_type = 'gatherpress_venue'
	) {
		// Expression for the post type archive feeds,
		// for example 'venue/bangkok/feed/(custom-endpoint)(/)'.
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
	 * @since 0.34.0
	 *
	 * @return bool True if the current request is a valid feed request for the post type singular.
	 */
	public function is_valid(): bool {
		return is_singular( $this->type_object->name ) && is_feed();
	}

	/**
	 * Defines the rewrite replacement attributes for the custom feed endpoint.
	 *
	 * This method defines the rewrite replacement attributes
	 * for the custom feed endpoint to be further processed by add_rewrite_rule().
	 *
	 * @since 0.34.0
	 *
	 * @return array The rewrite replacement attributes for add_rewrite_rule().
	 */
	public function get_rewrite_atts(): array {
		return array(
			$this->object_type       => $this->type_object->name,
			$this->type_object->name => '$matches[1]',
			'feed'                   => '$matches[2]',
			$this->query_var         => '$matches[2]',
		);
	}
}
