<?php
/**
 * Handles feed-based endpoints for custom post types in GatherPress.
 *
 * This file defines the `Post_Type_Feed` class, which extends the base `Endpoint`
 * class to handle custom feeds for post type archives. It allows users to define custom
 * feed URLs (e.g., RSS feeds) for post types such as `gatherpress_event`, while also allowing
 * theme overrides for feed templates.
 *
 * @package GatherPress\Core\Calendar\External
 * @since 0.34.0
 */

namespace GatherPress\Core\Calendar\External;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_Post_Type;

/**
 * Manages custom feed endpoints for post types in GatherPress.
 *
 * The `Post_Type_Feed` class extends the base `Endpoint` class to create
 * custom feed URLs for post types. It handles URL rewriting for feeds and
 * ensures that WordPress hooks into the appropriate feed template. The class also
 * supports theme overrides, allowing developers to customize feed templates for
 * specific post types.
 *
 * @since 0.34.0
 */
final class Post_Type_Feed extends Endpoint {

	/**
	 * Class constructor.
	 *
	 * Initializes the `Post_Type_Feed` for handling custom feeds for the
	 * specified post type. It sets up a regular expression to match custom feed
	 * URLs (e.g., `event/feed/custom-endpoint`) and hooks into WordPress to load
	 * the appropriate feed template.
	 *
	 * @since 0.34.0
	 *
	 * @param Endpoint_Type[] $types      List of endpoint types (templates/redirects) for the feed.
	 * @param string          $query_var  The query variable used to identify the feed endpoint in the URL.
	 * @param string          $post_type  (Optional) The post type for which the feed endpoint is being created.
	 *                                    Default is `gatherpress_event`.
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
	 * Builds the feed's regex pattern from the post type's archive slug.
	 *
	 * Works out the archive slug the way WordPress core does for archive
	 * rewrite rules, so the rule matches the link that
	 * `get_post_type_archive_feed_link()` returns: a string `has_archive`
	 * replaces the rewrite slug, and `with_front` adds the permalink front.
	 *
	 * @since TBD
	 *
	 * @return string The compiled regex pattern.
	 */
	protected function get_regex_pattern(): string {
		global $wp_rewrite;

		/**
		 * The registered post type object.
		 *
		 * @var WP_Post_Type $type_object Registration resolved the object before the
		 *                                endpoint could initialize.
		 */
		$type_object = $this->type_object;

		/**
		 * Rewrite settings of the post type.
		 *
		 * @var array<string, mixed> $rewrite Registration bails on post types with rewrites disabled.
		 */
		$rewrite = $type_object->rewrite;

		$archive_slug = is_string( $type_object->has_archive ) && '' !== $type_object->has_archive
			? $type_object->has_archive
			: $rewrite['slug'];
		$prefix       = $rewrite['with_front'] ? substr( $wp_rewrite->front, 1 ) : $wp_rewrite->root;

		return sprintf(
			$this->reg_ex,
			$prefix . $archive_slug,
			join( '|', $this->get_slugs() )
		);
	}

	/**
	 * Validates if the current request is for a post type archive feed.
	 *
	 * This method checks if the current request is for an archive page of the specified
	 * post type and if it is a valid feed request (i.e., `is_feed()` returns true).
	 *
	 * @since 0.34.0
	 *
	 * @return bool True if the current request is a valid feed request for the post type archive.
	 */
	public function is_valid(): bool {
		/**
		 * The registered post type object.
		 *
		 * @var WP_Post_Type $type_object Registration resolved the object before the
		 *                                endpoint could initialize.
		 */
		$type_object = $this->type_object;

		return is_post_type_archive( $type_object->name ) && is_feed();
	}

	/**
	 * Defines the rewrite replacement attributes for the custom feed endpoint.
	 *
	 * This method defines the rewrite replacement attributes
	 * for the custom feed endpoint to be further processed by add_rewrite_rule().
	 *
	 * @since 0.34.0
	 *
	 * @return array<string, string> The rewrite replacement attributes for add_rewrite_rule().
	 */
	public function get_rewrite_atts(): array {
		/**
		 * The registered post type object.
		 *
		 * @var WP_Post_Type $type_object Registration resolved the object before the
		 *                                endpoint could initialize.
		 */
		$type_object = $this->type_object;

		return array(
			$this->object_type => $type_object->name,
			'feed'             => '$matches[1]',
			$this->query_var   => '$matches[1]',
		);
	}
}
