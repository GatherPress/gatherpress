<?php
/**
 * The "Event Query" class manages the core-block-variation,
 * it mainly prepares the output of the block.
 *
 * @package GatherPress\Core
 * @since 0.33.0
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
 * @since 0.33.0
 */
final class Event_Query {

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
	 * @since 0.33.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress-event-query';

	/**
	 * Resolved event-query type ('upcoming', 'past' or 'all') keyed by the
	 * `queryId` of each event-query block seen during the render pass.
	 *
	 * Populated in `pre_render_block`, where the block's `namespace` attribute
	 * confirms it is one of ours, and consumed in `query_loop_block_query_vars`,
	 * which only sees the descendant (post-template / pagination) block context
	 * and therefore cannot tell our blocks from a sibling plain Query Loop. The
	 * map lets the front-end filter scope strictly to the blocks we registered
	 * and fall back to the same 'upcoming' default the REST collection params
	 * apply, so a block whose saved query predates the `gatherpress_event_query`
	 * attribute still scopes to upcoming events on the front end instead of
	 * listing every event (#1806).
	 *
	 * @since 0.34.0
	 * @var array<int, string>
	 */
	protected array $event_query_types = array();

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.33.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds filters to handle rendering & REST requests for the block.
	 *
	 * @since 0.33.0
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
		add_action( 'registered_post_type', array( $this, 'maybe_register_event_date_rest_hooks' ) );
		// Sweep last so post types registered before our listener was
		// added still get their REST filters installed (#1608).
		add_action( 'init', array( $this, 'register_existing_event_date_post_types' ), PHP_INT_MAX );

		// Integrate with Advanced Query Loop plugin to pass event query params through.
		add_filter(
			'aql_query_vars',
			array( $this, 'aql_query_vars' ),
			10,
			2
		);
	}

	/**
	 * Register REST hooks for every event-supporting post type that's
	 * already in the registry by the time we run.
	 *
	 * Companion to the `registered_post_type` listener — that one catches
	 * post types registered AFTER `Event_Query` is instantiated, this one
	 * catches the ones registered BEFORE. Idempotent: `add_filter` is a
	 * no-op when the same callback is already registered.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_existing_event_date_post_types(): void {
		foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $post_type ) {
			$this->maybe_register_event_date_rest_hooks( $post_type );
		}
	}

	/**
	 * Register REST hooks when a post type declares gatherpress-event-date support.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function maybe_register_event_date_rest_hooks( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		// Updates the query vars for the Query Loop block in the block editor.
		add_filter(
			sprintf( 'rest_%s_query', $post_type ),
			array( $this, 'rest_query' ),
			10,
			2
		);
		// We need more sortBy options.
		add_filter(
			sprintf( 'rest_%s_collection_params', $post_type ),
			array( $this, 'rest_collection_params' )
		);
	}

	/**
	 * Allows render_block() to be short-circuited, by returning a non-null value.
	 *
	 * Updates the query on the front end based on custom query attributes.
	 *
	 * @since 0.33.0
	 *
	 * @param string|null $pre_render   The pre-rendered content. Default null.
	 * @param array       $parsed_block The block being rendered.
	 *
	 * @return string|null The pre-rendered content. Default null.
	 */
	public function pre_render_block( ?string $pre_render, array $parsed_block ): ?string {
		// Bail unless this is our event query block, verified via the namespace attribute.
		if (
			! isset( $parsed_block['attrs']['namespace'] ) ||
			self::BLOCK_NAME !== $parsed_block['attrs']['namespace']
		) {
			return $pre_render;
		}

		if (
			isset( $parsed_block['attrs']['query']['inherit'] ) &&
			true === $parsed_block['attrs']['query']['inherit']
		) {
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
			 * @since 0.33.0
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
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_query = new WP_Query( $filtered_query_args );
		} else {
			// Record this query's event type, defaulting to 'upcoming' to
			// match the REST collection params when the block omits it (#1806).
			$query_id                             = $parsed_block['attrs']['queryId'] ?? 0;
			$this->event_query_types[ $query_id ] =
				$parsed_block['attrs']['query']['gatherpress_event_query'] ?? 'upcoming';

			add_filter(
				'query_loop_block_query_vars',
				array( $this, 'query_loop_block_query_vars' ),
				10,
				2
			);
		}

		return $pre_render;
	}

	/**
	 * Returns an array with Post IDs that should be excluded from the Query.
	 *
	 * @since 0.33.0
	 *
	 * @param array $attributes Event Query block attributes.
	 *
	 * @return int[] Array of post IDs to exclude.
	 */
	protected function get_exclude_ids( array $attributes ): array {
		$exclude_ids = array();

		// Exclude Current Post.
		if ( isset( $attributes['exclude_current'] ) && boolval( $attributes['exclude_current'] ) ) {
			$exclude_id = (int) $attributes['exclude_current'];

			// Inside a block template `exclude_current` holds the template
			// identifier (e.g. "twentytwentyfive//single-gatherpress_event"),
			// not a post id, so it casts to 0. On a singular page the "current"
			// post is the queried object, so resolve to it at render time. This
			// makes "exclude current event" work inside a template too (#1753).
			if ( $exclude_id <= 0 && is_singular() ) {
				$exclude_id = get_queried_object_id();
			}

			if ( $exclude_id > 0 ) {
				array_push( $exclude_ids, $exclude_id );
			}
		}

		return $exclude_ids;
	}

	/**
	 * Filters the arguments which will be passed to `WP_Query` for the Query Loop Block.
	 *
	 * @since 0.33.0
	 *
	 * @param array    $query Array containing parameters for <code>WP_Query</code> as parsed by the block context.
	 * @param WP_Block $block Block instance.
	 *
	 * @return array Array containing parameters for <code>WP_Query</code> as parsed by the block context.
	 */
	public function query_loop_block_query_vars( array $query, WP_Block $block ): array {
		// Retrieve the query from the passed block context.
		$block_query = $block->context['query'];

		if ( ! is_array( $block_query ) ) {
			return $query;
		}

		// Prefer the type recorded in pre_render_block, then one set directly on
		// the block query. A query with neither is not ours, so leave it untouched.
		$query_id = $block->context['queryId'] ?? 0;

		if ( array_key_exists( $query_id, $this->event_query_types ) ) {
			$event_query_type = $this->event_query_types[ $query_id ];
		} elseif ( isset( $block_query['gatherpress_event_query'] ) ) {
			$event_query_type = $block_query['gatherpress_event_query'];
		} else {
			return $query;
		}

		// Generate a new custom query with all potential query vars.
		$query_args = array();

		// Honor the block's selected post type when present so a Query Loop
		// pinned to e.g. `production` doesn't leak `gatherpress_event` posts
		// (#1609). Fall back to all event-supporting post types only when
		// the block didn't pick one explicitly.
		$query_args['post_type'] = ! empty( $block_query['postType'] )
			? $block_query['postType']
			: get_post_types_by_support( 'gatherpress-event-date' );

		// Type of event list: 'upcoming', 'past', or 'all',
		// @see wp-content/plugins/gatherpress/includes/core/classes/class-event-query.php.
		$query_args['gatherpress_event_query'] = $event_query_type;

		// Exclude Posts.
		$exclude_ids = $this->get_exclude_ids( $block_query );
		if ( ! empty( $exclude_ids ) ) {
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			$query_args['post__not_in'] = $exclude_ids;
		}

		if ( isset( $block_query['include_unfinished'] ) ) {
			$query_args['include_unfinished'] = $block_query['include_unfinished'];
		}

		if ( ! empty( $block_query['shadow_filter'] ) ) {
			$query_args['shadow_filter'] = $block_query['shadow_filter'];
		}

		// Editor-preview context: lets the REST preview scope to the same
		// shadow-source post the frontend resolves from the queried object.
		// Frontend pre_get_posts ignores these; the REST path uses them as a
		// fallback when is_singular() is false.
		if ( ! empty( $block_query['gatherpress_shadow_source_post_id'] ) ) {
			$query_args['gatherpress_shadow_source_post_id'] = (int) $block_query['gatherpress_shadow_source_post_id'];
		}
		if ( ! empty( $block_query['gatherpress_shadow_source_post_type'] ) ) {
			$query_args['gatherpress_shadow_source_post_type'] =
				(string) $block_query['gatherpress_shadow_source_post_type'];
		}

		// Order By.
		if ( isset( $block_query['orderBy'] ) ) {
			$query_args['orderby'] = array( $block_query['orderBy'] );
		}

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
	 * @since 0.33.0
	 *
	 * @param array           $args    Array of arguments for WP_Query.
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return array Array of arguments for WP_Query.
	 */
	public function rest_query( array $args, WP_REST_Request $request ): array {
		// When a request explicitly asks for specific events by ID (`include`),
		// the upcoming/past date filter should not apply — ID-based lookups
		// are explicit and the date filter is meant for browsing. Without
		// this bypass, any block that resolves an event by ID via the
		// collection endpoint (e.g. the postIdOverride resolver) silently
		// gets an empty array for past events and the override looks broken.
		$include = $request->get_param( 'include' );
		if ( ! empty( $include ) ) {
			return $args;
		}

		// Generate a new custom query will all potential query vars.
		$custom_args = array();

		// Type of event list: 'upcoming', 'past', or 'all',
		// @see wp-content/plugins/gatherpress/includes/core/classes/class-event-query.php .
		$custom_args['gatherpress_event_query'] = $request->get_param( 'gatherpress_event_query' );

		// Exclusion Related.
		$exclude_current = $request->get_param( 'exclude_current' );
		if ( $exclude_current ) {
			$attributes = array(
				'exclude_current' => $exclude_current,
			);
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			$custom_args['post__not_in'] = $this->get_exclude_ids( $attributes );
		}

		$include_unfinished = $request->get_param( 'include_unfinished' );
		if ( null !== $include_unfinished ) {
			$custom_args['include_unfinished'] = $include_unfinished;
		}

		$custom_args['orderby'] = $request->get_param( 'orderby' );

		$shadow_filter = $request->get_param( 'shadow_filter' );
		if ( null !== $shadow_filter ) {
			$custom_args['shadow_filter'] = $shadow_filter;
		}

		// REST-side context for the editor preview. When the editor's
		// contextual toggle is on, the block sends the editor's current page
		// post id and type so the REST query can scope to the same source
		// the frontend `is_singular()` path would scope to.
		$context_post_id = $request->get_param( 'gatherpress_shadow_source_post_id' );
		if ( null !== $context_post_id ) {
			$custom_args['gatherpress_shadow_source_post_id'] = (int) $context_post_id;
		}
		$context_post_type = $request->get_param( 'gatherpress_shadow_source_post_type' );
		if ( null !== $context_post_type ) {
			$custom_args['gatherpress_shadow_source_post_type'] = (string) $context_post_type;
		}

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
	 * @since 0.33.0
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_rest_posts_controller/get_collection_params/
	 *
	 * @param array $query_params JSON Schema-formatted collection parameters.
	 *
	 * @return array JSON Schema-formatted collection parameters.
	 */
	public function rest_collection_params( array $query_params ): array {
		// Add GatherPress-specific orderby options.
		$query_params['orderby']['enum'][] = 'rand';
		$query_params['orderby']['enum'][] = 'datetime';

		// Add custom GatherPress query parameters.
		$query_params['gatherpress_event_query'] = array(
			'description' => __( 'Type of events to query: upcoming, past, or all', 'gatherpress' ),
			'type'        => 'string',
			'enum'        => array( 'upcoming', 'past', 'all' ),
			'default'     => 'upcoming',
		);

		$query_params['include_unfinished'] = array(
			'description' => __( 'Whether to include events that have started but not finished', 'gatherpress' ),
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
		);

		// exclude_current and gatherpress_shadow_source_post_id are post IDs,
		// but inside a block template the editor preview sends the template
		// identifier (e.g. "twentytwentyfive//single-gatherpress_event")
		// because there is no concrete post providing numeric context. A bare
		// `type => integer` rejects that with a 400, which leaves the Query
		// Loop spinning forever in the template editor (#1753). Accept any
		// value and coerce it to a non-negative int instead: a non-numeric
		// template id collapses to 0, which downstream treats as "no context"
		// and renders the query unfiltered rather than erroring.
		$query_params['exclude_current'] = array(
			'description'       => __( 'Post ID to exclude from results', 'gatherpress' ),
			'type'              => 'integer',
			'validate_callback' => '__return_true',
			'sanitize_callback' => 'absint',
		);

		$query_params['shadow_filter'] = array(
			'description' => __( 'Whether to filter events by the current venue context', 'gatherpress' ),
			'type'        => 'integer',
			'enum'        => array( 0, 1 ),
		);

		$query_params['gatherpress_shadow_source_post_id'] = array(
			'description'       => __(
				'Editor-side post ID used to scope the venue contextual filter in the REST preview.',
				'gatherpress'
			),
			'type'              => 'integer',
			'validate_callback' => '__return_true',
			'sanitize_callback' => 'absint',
		);

		$query_params['gatherpress_shadow_source_post_type'] = array(
			'description' => __(
				'Editor-side post type used to scope the venue contextual filter in the REST preview.',
				'gatherpress'
			),
			'type'        => 'string',
		);

		return $query_params;
	}

	/**
	 * Filters Advanced Query Loop query vars to pass GatherPress event params through.
	 *
	 * When AQL is used with the gatherpress_event post type, this ensures that
	 * GatherPress-specific query parameters (event type, unfinished events, datetime ordering)
	 * are passed through to WP_Query, where the core Event\Query class picks them up
	 * via pre_get_posts for SQL modification.
	 *
	 * @since 0.34.0
	 *
	 * @param array $query_args  The query arguments being built.
	 * @param array $block_query The block's query attributes.
	 *
	 * @return array Modified query arguments.
	 */
	public function aql_query_vars( array $query_args, array $block_query ): array {
		// Only process if querying GatherPress events.
		$post_type = $block_query['postType'] ?? '';

		if ( ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			return $query_args;
		}

		// Pass through event query type (upcoming/past).
		if ( ! empty( $block_query['gatherpress_event_query'] ) ) {
			$query_args['gatherpress_event_query'] = $block_query['gatherpress_event_query'];
		}

		// Pass through include_unfinished setting.
		if ( isset( $block_query['include_unfinished'] ) ) {
			$query_args['include_unfinished'] = $block_query['include_unfinished'];
		}

		// Pass through GatherPress-specific ordering.
		if ( ! empty( $block_query['orderBy'] ) ) {
			$query_args['orderby'] = array( $block_query['orderBy'] );
		}

		// Pass through order direction.
		if ( ! empty( $block_query['order'] ) ) {
			$query_args['order'] = strtoupper( $block_query['order'] );
		}

		// Pass through venue filter setting.
		if ( ! empty( $block_query['shadow_filter'] ) ) {
			$query_args['shadow_filter'] = $block_query['shadow_filter'];
		}

		return $query_args;
	}
}
