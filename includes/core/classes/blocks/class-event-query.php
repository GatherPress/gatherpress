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
	 * Register REST hooks for every event- or shadow-source-supporting post
	 * type that's already in the registry by the time we run.
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
		foreach ( $this->get_query_rest_post_types() as $post_type ) {
			$this->maybe_register_event_date_rest_hooks( $post_type );
		}
	}

	/**
	 * Extract the post types a REST request is asking for from the WP_Query
	 * args WordPress built before the rest_$post_type_query filter ran.
	 *
	 * `post_type` may be a single slug, an array of slugs, or a comma-separated
	 * string. The hook is registered for every event-date and shadow-source
	 * post type, so we need the full set to decide which schema-defined params
	 * actually apply; reading event-only params on a shadow-source listing (or
	 * vice versa) would 400 the request because the param isn't on the
	 * matching collection's schema.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, mixed> $args WP_Query args built by the REST layer.
	 *
	 * @return string[] Post type slugs referenced by `$args['post_type']`.
	 */
	protected function get_requested_post_types_from_args( array $args ): array {
		$post_type = $args['post_type'] ?? '';

		if ( is_array( $post_type ) ) {
			return array_values(
				array_filter(
					array_map( 'strval', $post_type )
				)
			);
		}

		if ( is_string( $post_type ) && '' !== $post_type ) {
			return array_values(
				array_filter(
					array_map( 'trim', explode( ',', $post_type ) )
				)
			);
		}

		return array();
	}

	/**
	 * Return the post types whose REST collections accept GatherPress query
	 * params.
	 *
	 * Both event post types (which declare `gatherpress-event-date`) and
	 * shadow-source post types (venues, productions, … which declare
	 * `gatherpress-shadow-source`) get the collection filters. Shadow-source
	 * queries need them so the "filter by event activity" params
	 * (`has_events_filter`, `upcoming_events_only`) are accepted when a query
	 * loop lists the source posts themselves.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] Post types whose REST collections accept GatherPress query params.
	 */
	protected function get_query_rest_post_types(): array {
		return array_values(
			array_unique(
				array_merge(
					get_post_types_by_support( 'gatherpress-event-date' ),
					get_post_types_by_support( 'gatherpress-shadow-source' )
				)
			)
		);
	}

	/**
	 * Register REST hooks when a post type declares gatherpress-event-date or
	 * gatherpress-shadow-source support.
	 *
	 * Event post types get the event-specific collection params
	 * (`gatherpress_event_query`, `include_unfinished`, `orderby=rand|datetime`,
	 * `exclude_current`, `shadow_filter`); shadow-source post types get the
	 * activity-specific collection params (`has_events_filter`,
	 * `upcoming_events_only`) plus the editor-preview context params. The
	 * filter callback is the same in both cases; `rest_query` and
	 * `rest_collection_params` are support-aware and skip params that do not
	 * apply to the requested post type.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function maybe_register_event_date_rest_hooks( string $post_type ): void {
		if ( ! in_array( $post_type, $this->get_query_rest_post_types(), true ) ) {
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
	 * @param string|null          $pre_render   The pre-rendered content. Default null.
	 * @param array<string, mixed> $parsed_block The block being rendered.
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
	 * @param array<string, mixed> $attributes Event Query block attributes.
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
	 * @param array<string, mixed> $query Array containing parameters for <code>WP_Query</code> as parsed by the
	 *                                    block context.
	 * @param WP_Block             $block Block instance.
	 *
	 * @return array<string, mixed> Array containing parameters for <code>WP_Query</code> as parsed by the block
	 *                              context.
	 */
	public function query_loop_block_query_vars( array $query, WP_Block $block ): array {
		// Retrieve the query from the passed block context.
		$block_query = $block->context['query'];

		if ( ! is_array( $block_query ) ) {
			return $query;
		}

		// Resolve the effective post type from the block context. Event-specific
		// vars below only flow when the loop is unambiguously an event loop
		// (supports event dates), and activity vars only when it lists
		// shadow-source posts (venues, productions, …).
		$requested_post_type = ! empty( $block_query['postType'] )
			? (string) $block_query['postType']
			: '';

		$query_event_supports  = '' === $requested_post_type
			|| post_type_supports( $requested_post_type, 'gatherpress-event-date' );
		$query_shadow_supports = '' !== $requested_post_type
			&& post_type_supports( $requested_post_type, 'gatherpress-shadow-source' );

		// Prefer the event type recorded in pre_render_block, then one set
		// directly on the block query. The 'upcoming' fallback matches the
		// REST collection params default (#1806) and is only read by the
		// event branch below.
		$query_id = $block->context['queryId'] ?? 0;

		$is_event_loop = array_key_exists( $query_id, $this->event_query_types )
			|| isset( $block_query['gatherpress_event_query'] );

		$event_query_type = $this->event_query_types[ $query_id ]
			?? $block_query['gatherpress_event_query']
			?? 'upcoming';

		// A sibling plain Query Loop — neither recorded as ours (no queryId in
		// the map, no gatherpress_event_query attribute) nor listing
		// shadow-source posts — stays untouched (#1806).
		if ( ! $is_event_loop && ! $query_shadow_supports ) {
			return $query;
		}

		// Generate a new custom query with all potential query vars.
		$query_args = array();

		if ( $query_event_supports ) {
			// Honor the block's selected post type when present so a Query
			// Loop pinned to e.g. `production` doesn't leak `gatherpress_event`
			// posts (#1609). Fall back to all event-supporting post types
			// only when the block didn't pick one explicitly.
			$query_args['post_type'] = '' !== $requested_post_type
				? $requested_post_type
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

			// Order By.
			if ( isset( $block_query['orderBy'] ) ) {
				$query_args['orderby'] = array( $block_query['orderBy'] );
			}

			// Order
			// can be NULL, when ASC.
			$query_args['order'] = strtoupper( $block_query['order'] ?? 'ASC' );

			// The shadow-source contextual filter only applies to event
			// queries; on a venue/production loop it would scope the loop
			// to the host page, which isn't what the block is for.
			if ( ! empty( $block_query['shadow_filter'] ) ) {
				$query_args['shadow_filter'] = $block_query['shadow_filter'];
			}
		}

		if ( $query_shadow_supports ) {
			// Filter source posts by their event activity (upcoming or past).
			// Only meaningful on a query loop listing shadow-source post
			// types; the pre_get_posts handler in Event\Query gates on that
			// support.
			if ( ! empty( $block_query['has_events_filter'] ) ) {
				$query_args['has_events_filter'] = $block_query['has_events_filter'];
			}

			if ( isset( $block_query['upcoming_events_only'] ) ) {
				$query_args['upcoming_events_only'] = $block_query['upcoming_events_only'];
			}

			// Editor-preview context: lets the REST preview scope to the
			// same shadow-source post the frontend resolves from the queried
			// object. Frontend pre_get_posts ignores these; the REST path
			// uses them as a fallback when is_singular() is false.
			if ( ! empty( $block_query['gatherpress_shadow_source_post_id'] ) ) {
				$query_args['gatherpress_shadow_source_post_id'] =
					(int) $block_query['gatherpress_shadow_source_post_id'];
			}
			if ( ! empty( $block_query['gatherpress_shadow_source_post_type'] ) ) {
				$query_args['gatherpress_shadow_source_post_type'] =
					(string) $block_query['gatherpress_shadow_source_post_type'];
			}
		}

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
	 * @param array<string, mixed> $args    Array of arguments for WP_Query.
	 * @param WP_REST_Request      $request The REST API request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array<string, mixed> Array of arguments for WP_Query.
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

		// Resolve the requested post type so we only pull the schema-defined
		// params that actually apply. A venue listing is a shadow-source
		// post type; an event listing is an event-date post type. Reading
		// event-only params (or shadow-source-only params) when they do
		// not apply leaks them into query vars the matching pre_get_posts
		// hook will then ignore, and triggers 400s for the
		// `rest_invalid_param` schema mismatch on endpoints that don't
		// declare them.
		$requested_post_types = $this->get_requested_post_types_from_args( $args );

		$event_post_types  = get_post_types_by_support( 'gatherpress-event-date' );
		$shadow_post_types = get_post_types_by_support( 'gatherpress-shadow-source' );

		$query_event_supports  = (bool) array_intersect( $requested_post_types, $event_post_types );
		$query_shadow_supports = (bool) array_intersect( $requested_post_types, $shadow_post_types );

		// Generate a new custom query will all potential query vars.
		$custom_args = array();

		if ( $query_event_supports ) {
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
		}

		if ( $query_shadow_supports ) {
			// Source-post activity filtering: passes `has_events_filter` and
			// `upcoming_events_only` through to the query so a collection
			// listing shadow-source posts can be scoped to their upcoming
			// or past events.
			$has_events_filter = $request->get_param( 'has_events_filter' );
			if ( null !== $has_events_filter ) {
				$custom_args['has_events_filter'] = $has_events_filter;
			}

			$upcoming_events_only = $request->get_param( 'upcoming_events_only' );
			if ( null !== $upcoming_events_only ) {
				$custom_args['upcoming_events_only'] = $upcoming_events_only;
			}

			// REST-side context for the editor preview. When the editor's
			// contextual toggle is on, the block sends the editor's current
			// page post id and type so the REST query can scope to the same
			// source the frontend `is_singular()` path would scope to.
			$context_post_id = $request->get_param( 'gatherpress_shadow_source_post_id' );
			if ( null !== $context_post_id ) {
				$custom_args['gatherpress_shadow_source_post_id'] = (int) $context_post_id;
			}
			$context_post_type = $request->get_param( 'gatherpress_shadow_source_post_type' );
			if ( null !== $context_post_type ) {
				$custom_args['gatherpress_shadow_source_post_type'] = (string) $context_post_type;
			}
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
	 * Override the allowed items. The set of params added depends on the
	 * post type whose `rest_$post_type_collection_params` filter is firing
	 * — the same callback serves both event-date and shadow-source post
	 * types, so we derive the support group from the current filter name.
	 * Event post types get the event query type, include_unfinished, the
	 * custom orderby enum, the exclude_current event-side scope, and the
	 * shadow_filter toggle. Shadow-source post types get the activity
	 * filter and the editor-preview context.
	 *
	 * @since 0.33.0
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_rest_posts_controller/get_collection_params/
	 *
	 * @param array<string, array<string, mixed>> $query_params JSON Schema-formatted collection parameters.
	 *
	 * @return array<string, array<string, mixed>> JSON Schema-formatted collection parameters.
	 */
	public function rest_collection_params( array $query_params ): array {
		$current_filter = current_filter();
		$post_type      = '';

		if ( is_string( $current_filter ) && str_starts_with( $current_filter, 'rest_' ) ) {
			// rest_$post_type_collection_params → post_type sits between
			// the leading "rest_" and trailing "_collection_params".
			$post_type = substr(
				$current_filter,
				strlen( 'rest_' ),
				-strlen( '_collection_params' )
			);
		}

		return $this->add_gatherpress_collection_params( $query_params, $post_type );
	}

	/**
	 * Adds the GatherPress-specific collection params that apply to the given post type.
	 *
	 * The same callback is registered for every post type that declares
	 * `gatherpress-event-date` or `gatherpress-shadow-source` support, so
	 * the support group must be resolved from the post type at runtime
	 * rather than hardcoded. Extracted from `rest_collection_params` so
	 * tests can exercise the support-routing branches directly without
	 * setting up a `current_filter()` context.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, array<string, mixed>> $query_params JSON Schema-formatted collection parameters.
	 * @param string                              $post_type   Post type slug whose schema is being filtered.
	 *
	 * @return array<string, array<string, mixed>> JSON Schema-formatted collection parameters, extended.
	 */
	public function add_gatherpress_collection_params( array $query_params, string $post_type ): array {
		$is_event_date = post_type_supports( $post_type, 'gatherpress-event-date' );
		$is_shadow     = post_type_supports( $post_type, 'gatherpress-shadow-source' );

		if ( $is_event_date ) {
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

			// exclude_current is a post ID, but inside a block template the
			// editor preview sends the template identifier (e.g.
			// "twentytwentyfive//single-gatherpress_event") because there is
			// no concrete post providing numeric context. A bare
			// `type => integer` rejects that with a 400, which leaves the
			// Query Loop spinning forever in the template editor (#1753).
			// Accept any value and coerce it to a non-negative int instead:
			// a non-numeric template id collapses to 0, which downstream
			// treats as "no context" and renders the query unfiltered
			// rather than erroring.
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
		}

		if ( $is_shadow ) {
			$query_params['has_events_filter'] = array(
				'description' => __( 'Whether to filter shadow-source posts by their event activity', 'gatherpress' ),
				'type'        => 'integer',
				'enum'        => array( 0, 1 ),
			);

			$query_params['upcoming_events_only'] = array(
				'description' => __( 'Whether to keep only source posts with upcoming events', 'gatherpress' ),
				'type'        => 'integer',
				'enum'        => array( 0, 1 ),
				'default'     => 1,
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
		}

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
	 * @param array<string, mixed> $query_args  The query arguments being built.
	 * @param array<string, mixed> $block_query The block's query attributes.
	 *
	 * @return array<string, mixed> Modified query arguments.
	 */
	public function aql_query_vars( array $query_args, array $block_query ): array {
		// Only process if querying GatherPress events or shadow-source posts.
		$post_type = $block_query['postType'] ?? '';

		if (
			! post_type_supports( $post_type, 'gatherpress-event-date' )
			&& ! post_type_supports( $post_type, 'gatherpress-shadow-source' )
		) {
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

		// Pass through source-post event activity filtering.
		if ( ! empty( $block_query['has_events_filter'] ) ) {
			$query_args['has_events_filter'] = $block_query['has_events_filter'];
		}

		// Pass through the upcoming-only flag; only read when the activity
		// filter is on, and it defaults to upcoming.
		if ( isset( $block_query['upcoming_events_only'] ) ) {
			$query_args['upcoming_events_only'] = $block_query['upcoming_events_only'];
		}

		return $query_args;
	}
}
