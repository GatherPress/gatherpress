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
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action('init', array( $this, 'init'), PHP_INT_MAX );
	}

	public function init() : void {

		// 
		add_filter(
			'pre_render_block',
			array( $this, 'pre_render_block'),
			10,
			2
		);

		// Updates the query vars for the Query Loop block in the block editor.
		add_filter(
			sprintf( 'rest_%s_query', Event::POST_TYPE ),
			array( $this, 'rest_query'),
			10,
			2
		);

		// We need more sortBy options.
		add_filter(
			sprintf( 'rest_%s_collection_params', Event::POST_TYPE ),
			array( $this, 'rest_collection_params'),
			10,
			2
		);
	}



	/**
	 * Allows render_block() to be short-circuited, by returning a non-null value.
	 *
	 * Updates the query on the front end based on custom query attributes.
	 *
	 * @param string|null    $pre_render   The pre-rendered content. Default null.
	 * @param array          $parsed_block The block being rendered.
	 * @return string|null The pre-rendered content. Default null.
	 */
	public function pre_render_block( ?string $pre_render, array $parsed_block ): ?string {
		if ( isset( $parsed_block['attrs']['namespace'] ) && 'gatherpress-event-query' === $parsed_block['attrs']['namespace'] ) {

			// Hijack the global query. It's a hack, but it works.
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
				 * @since 1.5
				 *
				 * @param array   $query_args  Arguments to be passed to WP_Query.
				 * @param array   $block_query The query attribute retrieved from the block.
				 * @param boolean $inherited   Whether the query is being inherited.
				 *
				 * @param array $filtered_query_args Final arguments list.
				 */
				$filtered_query_args = \apply_filters(
					'gpql_query_vars',
					$query_args,
					$parsed_block['attrs']['query'],
					true,
				);

				$wp_query = new \WP_Query( $filtered_query_args );
			} else {
				add_filter(
					'query_loop_block_query_vars',
					array( $this, 'query_loop_block_query_vars'),
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
	 * @param array
	 * @return int[]
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
	 * @param array     $query Array containing parameters for <code>WP_Query</code> as parsed by the block context.
	 * @param \WP_Block $block Block instance.
	 * @return array Array containing parameters for <code>WP_Query</code> as parsed by the block context.
	 */
	public function query_loop_block_query_vars( array $query, \WP_Block $block ): array {
		// Retrieve the query from the passed block context.
		$block_query = $block->context['query'];

		// Generate a new custom query with all potential query vars.
		$query_args = array();

		if ( count( $query_args ) ) {
			die( var_dump( $block_query, $query_args ) );
		}

		// Post Related.
		$query_args['post_type'] = [ Event::POST_TYPE ];

		// Type of event list: 'upcoming' or 'past'.
		// /wp-content/plugins/gatherpress/includes/core/classes/class-event-query.php
		$query_args['gatherpress_events_query'] = $block_query['gatherpress_events_query'];

		// Exclude Posts.
		$exclude_ids = $this->get_exclude_ids( $block_query );
		if ( ! empty( $exclude_ids ) ) {
			$query_args['post__not_in'] = $exclude_ids;
		}

		if ( isset( $block_query['include_unfinished'] ) ) {
			$query_args['include_unfinished'] = $block_query['include_unfinished'];
		}

		// Date queries.
		if ( ! empty( $block_query['date_query'] ) ) {
			$date_query        = $block_query['date_query'];
			$date_relationship = $date_query['relation'];
			$date_is_inclusive = $date_query['inclusive'];
			$date_primary      = $date_query['date_primary'];
			$date_secondary    = ! empty( $date_query['date_secondary'] ) ? $date_query['date_secondary'] : '';

			// Date format: 2022-12-27T11:14:21.
			$primary_year    = substr( $date_primary, 0, 4 );
			$primary_month   = substr( $date_primary, 5, 2 );
			$primary_day     = substr( $date_primary, 8, 2 );
			$secondary_year  = substr( $date_secondary, 0, 4 );
			$secondary_month = substr( $date_secondary, 5, 2 );
			$secondary_day   = substr( $date_secondary, 8, 2 );

			if ( 'between' === $date_relationship ) {
				$date_queries = array(
					'after'  => array(
						'year'  => $primary_year,
						'month' => $primary_month,
						'day'   => $primary_day,
					),
					'before' => array(
						'year'  => $secondary_year,
						'month' => $secondary_month,
						'day'   => $secondary_day,
					),
				);
			} else {
				$date_queries = array(
					$date_relationship => array(
						'year'  => $primary_year,
						'month' => $primary_month,
						'day'   => $primary_day,
					),
				);
			}

			$date_queries['inclusive'] = $date_is_inclusive;

			// Add the date queries to the custom query.
			$query_args['date_query'] = array_filter( $date_queries );
		}

		// Order By
		$query_args['orderby'] = [ $block_query['orderBy'] ];

		// Order
		// can be NULL, when ASC
		$query_args['order'] = \strtoupper( $block_query['order'] ?? 'ASC' );

		/** This filter is documented in includes/query-loop.php */
		$filtered_query_args = \apply_filters(
			'gpql_query_vars',
			$query_args,
			$block_query,
			false
		);

		// \error_log( '$block_query: ' . \var_export( [ $block_query, $query_args ], true ) );
		// \error_log( 'queries: ' . \var_export( [ $query, $filtered_query_args ], true ) );
		// \error_log( 'queries: ' . \var_export( array_merge(
		// $query,
		// $filtered_query_args
		// ), true ) );

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
	 * @param array            $args    Array of arguments for WP_Query.
	 * @param \WP_REST_Request $request The REST API request object.
	 * @return array Array of arguments for WP_Query.
	 */
	public function rest_query( array $args, \WP_REST_Request $request ) : array {
		// Generate a new custom query will all potential query vars.
		$custom_args = array();

		// Type of event list: 'upcoming' or 'past'.
		// /wp-content/plugins/gatherpress/includes/core/classes/class-event-query.php
		$custom_args['gatherpress_events_query'] = $request->get_param( 'gatherpress_events_query' );

		// Exclusion Related.
		$exclude_current = $request->get_param( 'exclude_current' );
		if ( $exclude_current ) {
			$attributes = array(
				'exclude_current' => $exclude_current,
			);
			$custom_args['post__not_in'] = $this->get_exclude_ids( $attributes );
		}

		// 
		$include_unfinished = $request->get_param( 'include_unfinished' );
		if ( $include_unfinished ) {
			$custom_args['include_unfinished'] = $include_unfinished;
		}

		$custom_args['orderby'] = $request->get_param( 'orderby' );
		// \error_log( '$args: ' . \var_export( $args, true ) );
		// \error_log( '$orderby: ' . \var_export( $request->get_param( 'orderby' ), true ) );
		// \error_log( '$custom_args: ' . \var_export( $custom_args, true ) );

		/** This filter is documented in includes/query-loop.php */
		$filtered_query_args = \apply_filters(
			'gpql_query_vars',
			$custom_args,
			$request->get_params(),
			false,
		);

		// Merge all queries.
		return array_merge(
			$args,
			array_filter( $filtered_query_args )
		);
	}

	/**
	 * Filters collection parameters for the posts controller.
	 *
	 * Override the allowed items.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_rest_posts_controller/get_collection_params/
	 *
	 * @param array         $query_params JSON Schema-formatted collection parameters.
	 * @param \WP_Post_Type $post_type    Post type object.
	 * @return array JSON Schema-formatted collection parameters.
	 */
	public function rest_collection_params( array $query_params, \WP_Post_Type $post_type ) : array {
		$query_params['orderby']['enum'][] = 'rand';
		$query_params['orderby']['enum'][] = 'datetime';
		return $query_params;
	}
}
