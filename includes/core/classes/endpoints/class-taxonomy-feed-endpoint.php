<?php

namespace GatherPress\Core\Endpoints;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Endpoints\Endpoint;


class Taxonomy_Feed_Endpoint extends Endpoint {


	public function __construct(
		string $query_var,
		array $types,
		string $taxonomy = 'gatherpress_topic'
	) {
		// Expression for the taxonomy archive feeds,
		// for example 'venue/bangkok/feed/(custom-endpoint)(/)'.
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
