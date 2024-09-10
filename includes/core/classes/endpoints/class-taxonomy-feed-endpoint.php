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
				'taxonomy'               => $this->type_object->name,
				$this->type_object->name => '$matches[1]',
				'feed'                   => '$matches[2]',
			),
			'index.php'
		);
	}
}
