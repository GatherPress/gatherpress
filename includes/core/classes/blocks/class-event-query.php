<?php
/**
 * The "Event Query" class manages the core-block-variation,
 * it mainly prepares the output of the block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_Block;
use WP_Query;
use WP_REST_Request;

/**
 * Class responsible for managing the "Event Query" block,
 * which is a block-variation of 'core/query'.
 *
 * @since 1.0.0
 */
class Event_Query {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name
	 *
	 * This is not namespaced by purpose.
	 * It's mainly used as a CSS class.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress-event-query';
	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds filters to handle rendering & REST requests for the block.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter(
			'pre_render_block',
			array( $this, 'pre_render_block' ),
			10,
			2
		);
		// Updates the query vars for the Query Loop block in the block editor.
		add_filter(
			sprintf( 'rest_%s_query', Event::POST_TYPE ),
			array( $this, 'rest_query' ),
			10,
			2
		);
		// We need more sortBy options.
		add_filter(
			sprintf( 'rest_%s_collection_params', Event::POST_TYPE ),
			array( $this, 'rest_collection_params' )
		);
	}

	/**
	 * Allows render_block() to be short-circuited, by returning a non-null value.
	 *
	 * Updates the query on the front end based on custom query attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $pre_render   The pre-rendered content. Default null.
	 * @param array       $parsed_block The block being rendered.
	 * @return string|null The pre-rendered content. Default null.
	 */
	public function pre_render_block( ?string $pre_render, array $parsed_block ): ?string {
		if ( isset( $parsed_block['attrs']['namespace'] ) && self::BLOCK_NAME === $parsed_block['attrs']['namespace'] ) {
			if ( isset( $parsed_block['attrs']['query']['inherit'] ) && true === $parsed_block['attrs']['query']['inherit'] ) {
				global $wp_query;

				$query_args = array_merge(
					$wp_query->query_vars,
					array(
						'posts_per_page' => $parsed_block['attrs']['query']['perPage'],
						'order'          => $parsed_block['attrs']['query']['order'],
						'orderby'        => $parsed_block['attrs']['query']['orderBy'],
					)
				);

				/**
				 * Filter the query vars.
				 *
				 * Allows filtering query params when the query is being inherited.
				 *
				 * @since 1.0.0
				 *
				 * @param array   $query_args  Arguments to be passed to WP_Query.
				 * @param array   $block_query The query attribute retrieved from the block.
				 * @param boolean $inherited   Whether the query is being inherited.
				 *
				 * @return array $filtered_query_args Final arguments list.
				 */
				$filtered_query_args = apply_filters(
					'gatherpress_query_vars',
					$query_args,
					$parsed_block['attrs']['query'],
					true,
				);
				// "Hijack the global query. It's a hack, but it works." Ryan Welcher.
				$wp_query = new WP_Query( $filtered_query_args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			} else {
				add_filter(
					'query_loop_block_query_vars',
					array( $this, 'query_loop_block_query_vars' ),
					10,
					2
				);
			}
		}

		return $pre_render;
	}

	/**
	 * Returns an array with Post IDs that should be excluded from the Query.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Event Query block attributes.
	 * @return int[] Array of post IDs to exclude.
	 */
	protected function get_exclude_ids( array $attributes ): array {
		$exclude_ids = array();

		// Exclude Current Post.
		if ( isset( $attributes['exclude_current'] ) && boolval( $attributes['exclude_current'] ) ) {
			array_push( $exclude_ids, $attributes['exclude_current'] );
		}

		return $exclude_ids;
	}

	/**
	 * Filters the arguments which will be passed to `WP_Query` for the Query Loop Block.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $query Array containing parameters for <code>WP_Query</code> as parsed by the block context.
	 * @param WP_Block $block Block instance.
	 * @return array Array containing parameters for <code>WP_Query</code> as parsed by the block context.
	 */
	public function query_loop_block_query_vars( array $query, WP_Block $block ): array {
		// Retrieve the query from the passed block context.
		$block_query = $block->context['query'];

		if ( ! is_array( $block_query ) || ! isset( $block_query['gatherpress_event_query'] ) ) {
			return $query;
		}

		// Generate a new custom query with all potential query vars.
		$query_args = array();

		// Post Related.
		$query_args['post_type'] = array( Event::POST_TYPE );

		// Type of event list: 'upcoming' or 'past',
		// @see wp-content/plugins/gatherpress/includes/core/classes/class-event-query.php.
		$query_args['gatherpress_event_query'] = $block_query['gatherpress_event_query'];

		// Exclude Posts.
		$exclude_ids = $this->get_exclude_ids( $block_query );
		if ( ! empty( $exclude_ids ) ) {
			$query_args['post__not_in'] = $exclude_ids; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}

		if ( isset( $block_query['include_unfinished'] ) ) {
			$query_args['include_unfinished'] = $block_query['include_unfinished'];
		}

		// Order By.
		$query_args['orderby'] = array( $block_query['orderBy'] );

		// Order
		// can be NULL, when ASC.
		$query_args['order'] = strtoupper( $block_query['order'] ?? 'ASC' );

		/** This filter is documented in includes/query-loop.php */
		$filtered_query_args = apply_filters(
			'gatherpress_query_vars',
			$query_args,
			$block_query,
			false
		);

		// Return the merged query.
		return array_merge(
			$query,
			$filtered_query_args
		);
	}

	/**
	 * Callback to handle the custom query params. Updates the block editor.
	 *
	 * Filters WP_Query arguments when querying posts via the REST API.
	 *
	 * @since 1.0.0
	 *
	 * @param array           $args    Array of arguments for WP_Query.
	 * @param WP_REST_Request $request The REST API request object.
	 * @return array Array of arguments for WP_Query.
	 */
	public function rest_query( array $args, WP_REST_Request $request ): array {
		// Generate a new custom query will all potential query vars.
		$custom_args = array();

		// Type of event list: 'upcoming' or 'past',
		// @see wp-content/plugins/gatherpress/includes/core/classes/class-event-query.php .
		$custom_args['gatherpress_event_query'] = $request->get_param( 'gatherpress_event_query' );

		// Exclusion Related.
		$exclude_current = $request->get_param( 'exclude_current' );
		if ( $exclude_current ) {
			$attributes                  = array(
				'exclude_current' => $exclude_current,
			);
			$custom_args['post__not_in'] = $this->get_exclude_ids( $attributes ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}

		$include_unfinished = $request->get_param( 'include_unfinished' );
		if ( null !== $include_unfinished ) {
			$custom_args['include_unfinished'] = $include_unfinished;
		}

		$custom_args['orderby'] = $request->get_param( 'orderby' );

		/** This filter is documented in includes/query-loop.php */
		$filtered_query_args = apply_filters(
			'gatherpress_query_vars',
			$custom_args,
			$request->get_params(),
			false,
		);

		// Merge all queries.
		// Use array_filter with callback to preserve 0 values while filtering out null/empty.
		return array_merge(
			$args,
			array_filter(
				$filtered_query_args,
				static function ( $value ): bool {
					return null !== $value && '' !== $value;
				}
			)
		);
	}

	/**
	 * Filters collection parameters for the posts controller.
	 *
	 * Override the allowed items.
	 *
	 * @since 1.0.0
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_rest_posts_controller/get_collection_params/
	 *
	 * @param array $query_params JSON Schema-formatted collection parameters.
	 * @return array JSON Schema-formatted collection parameters.
	 */
	public function rest_collection_params( array $query_params ): array {
		// Add GatherPress-specific orderby options.
		$query_params['orderby']['enum'][] = 'rand';
		$query_params['orderby']['enum'][] = 'datetime';

		// Add custom GatherPress query parameters.
		$query_params['gatherpress_event_query'] = array(
			'description' => __( 'Type of events to query: upcoming or past', 'gatherpress' ),
			'type'        => 'string',
			'enum'        => array( 'upcoming', 'past' ),
			'default'     => 'upcoming',
		);

		$query_params['include_unfinished'] = array(
			'description' => __( 'Whether to include events that have started but not finished', 'gatherpress' ),
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
		);

		$query_params['exclude_current'] = array(
			'description' => __( 'Post ID to exclude from results', 'gatherpress' ),
			'type'        => 'integer',
		);

		return $query_params;
	}
}
