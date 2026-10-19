<?php
/**
 * Connects the Calendar blocks to the Query Loop block, its pagination and the REST API.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_Block;
use WP_Post;
use WP_Query;
use WP_REST_Request;

/**
 * Class Query_Integration.
 *
 * Marks Query Loop blocks that contain a calendar, pages them by date range instead of by
 * posts, and lets the REST API return a full date range for the editor preview.
 *
 * @since TBD
 */
final class Query_Integration {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query parameter that marks a query as a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_PARAM = 'gatherpress_calendar_query';

	/**
	 * Query parameter for the view type of a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_VIEW_TYPE = 'gatherpress_calendar_view_type';

	/**
	 * Query parameter for the first day of a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_START_DATE = 'gatherpress_calendar_start_date';

	/**
	 * Query parameter for the last day of a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_END_DATE = 'gatherpress_calendar_end_date';

	/**
	 * Query parameter for the heading of a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_HEADING = 'gatherpress_calendar_heading';

	/**
	 * Number of pages the Next Page block of a calendar query can link to.
	 *
	 * A calendar pages by date range, so the number of posts does not limit its pages.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_PAGINATION_PAGES = 200;

	/**
	 * The queryId of each Query block that contains a calendar, seen during this render pass.
	 *
	 * Recorded in `allow_core_pagination()`, where the parsed Query block shows whether it holds
	 * a calendar. The pagination blocks of a Query block nested in another block, such as a Group,
	 * get their context from attributes that WordPress read before `render_block_data` ran, so
	 * their `query` context has no calendar marker. Their `queryId` context still matches.
	 *
	 * @since TBD
	 * @var array<int, true>
	 */
	protected array $calendar_query_ids = array();

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'registered_post_type', array( $this, 'maybe_register_rest_hooks' ) );
		// Sweep last so post types registered before this class loaded still get their REST filters.
		add_action( 'init', array( $this, 'register_existing_post_types' ), PHP_INT_MAX );
		add_filter( 'render_block_data', array( $this, 'allow_core_pagination' ), 10, 3 );
		add_filter( 'render_block_context', array( $this, 'disable_query_pagination_numbers' ), 10, 2 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'query_loop_block_query_vars', array( $this, 'query_loop_block_query_vars' ), 10, 2 );
	}

	/**
	 * Retrieves all post types supported by the calendar.
	 *
	 * Supports the default 'post' post type alongside any post type
	 * registering 'gatherpress-event-date' support.
	 *
	 * @since TBD
	 *
	 * @return string[] Array of post type slugs.
	 */
	public static function get_calendar_post_types(): array {
		$event_types = get_post_types_by_support( Event\Event::SUPPORT );

		return array_values( array_unique( array_merge( array( 'post' ), $event_types ) ) );
	}

	/**
	 * Registers the REST hooks for every calendar post type that is already registered.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_existing_post_types(): void {
		foreach ( self::get_calendar_post_types() as $post_type ) {
			$this->maybe_register_rest_hooks( $post_type );
		}
	}

	/**
	 * Registers the REST query and collection hooks for a calendar post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function maybe_register_rest_hooks( string $post_type ): void {
		if ( 'post' !== $post_type && ! post_type_supports( $post_type, Event\Event::SUPPORT ) ) {
			return;
		}

		add_filter(
			sprintf( 'rest_%s_collection_params', $post_type ),
			array( $this, 'filter_rest_collection_params' )
		);
		// Priority 20 runs after Event_Query::rest_query(), so the calendar can drop its upcoming/past filter.
		add_filter( sprintf( 'rest_%s_query', $post_type ), array( $this, 'rest_post_type_query' ), 20, 2 );
	}

	/**
	 * Raises the REST API per_page maximum to the calendar's query limit for calendar requests.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query_params Endpoint collection parameters.
	 *
	 * @return array<string, mixed> Filtered collection parameters.
	 */
	public function filter_rest_collection_params( array $query_params ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_REQUEST[ self::CALENDAR_QUERY_PARAM ] ) ) {
			return $query_params;
		}

		if ( is_array( $query_params['per_page'] ?? null ) ) {
			$query_params['per_page']['maximum'] = max( 100, Query_Builder::get_posts_per_page() );
		}

		return $query_params;
	}

	/**
	 * Resolves a date string parameter from REST query parameters with a fallback key.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $parameters REST parameters.
	 * @param string               $primary    Primary parameter key (e.g. 'start_date').
	 * @param string               $fallback   Fallback parameter key (e.g. 'after').
	 *
	 * @return string Sanitized date string or empty string.
	 */
	private function resolve_rest_date_param( array $parameters, string $primary, string $fallback ): string {
		$value = $parameters[ $primary ] ?? $parameters[ $fallback ] ?? '';

		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Builds the date query clause for calendar REST requests.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $parameters REST parameters.
	 * @param bool                 $is_event   Whether the queried post type supports events.
	 *
	 * @return array<string, mixed>|null Date query clause array or null if dates are missing.
	 */
	private function build_rest_date_clause( array $parameters, bool $is_event ): ?array {
		$start_date = $this->resolve_rest_date_param( $parameters, 'start_date', 'after' );
		$end_date   = $this->resolve_rest_date_param( $parameters, 'end_date', 'before' );

		if ( '' === $start_date || '' === $end_date ) {
			return null;
		}

		$clause = array(
			'after'     => $start_date . ' 00:00:00',
			'before'    => $end_date . ' 23:59:59',
			'inclusive' => true,
		);

		if ( $is_event ) {
			$clause['column'] = 'datetime_start';
		}

		return $clause;
	}

	/**
	 * Filters REST queries of the calendar editor preview to its date range.
	 *
	 * Runs only for requests that carry the `gatherpress_calendar_query` marker, which the
	 * calendar editor adds. For event post types it drops the upcoming/past filter, so the
	 * calendar can show past and upcoming events of the same month.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $args    WP_Query arguments that will be used for the REST request.
	 * @param WP_REST_Request      $request Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array<string, mixed> Modified query arguments.
	 */
	public function rest_post_type_query( array $args, WP_REST_Request $request ): array {
		$parameters = $request->get_params();

		if ( empty( $parameters[ self::CALENDAR_QUERY_PARAM ] ) ) {
			return $args;
		}

		$is_event = Query_Builder::is_event_post_type( $args['post_type'] ?? 'post' );

		if ( $is_event ) {
			unset( $args[ Event\Query::EVENT_QUERY_PARAM ] );
		}

		$date_clause = $this->build_rest_date_clause( $parameters, $is_event );

		if ( null !== $date_clause ) {
			$date_query         = is_array( $args['date_query'] ?? null ) ? $args['date_query'] : array();
			$date_query[0]      = $date_clause;
			$args['date_query'] = $date_query;
		}

		return $args;
	}

	/**
	 * Recursively find the attributes of the first inner block with a given name.
	 *
	 * @since TBD
	 *
	 * @param string       $block_name   The block name to search for.
	 * @param array<mixed> $inner_blocks Array of parsed inner blocks.
	 *
	 * @return array<string, mixed>|null The matching block's attributes or null.
	 */
	public static function find_inner_block_attrs( string $block_name, array $inner_blocks ): ?array {
		foreach ( $inner_blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( ( $block['blockName'] ?? '' ) === $block_name ) {
				/**
				 * Parsed block attributes are keyed by attribute name.
				 *
				 * @var array<string, mixed> $attrs
				 */
				$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();

				return $attrs;
			}

			if ( is_array( $block['innerBlocks'] ?? null ) ) {
				$found = self::find_inner_block_attrs( $block_name, $block['innerBlocks'] );

				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Checks whether a block context belongs to a Query block that contains a calendar.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $context Block context.
	 *
	 * @return bool True for a calendar query.
	 */
	private function is_calendar_query_context( array $context ): bool {
		$query = $context['query'] ?? null;

		if ( is_array( $query ) && ! empty( $query[ self::CALENDAR_QUERY_PARAM ] ) ) {
			return true;
		}

		$query_id = $context['queryId'] ?? null;

		return is_numeric( $query_id ) && isset( $this->calendar_query_ids[ (int) $query_id ] );
	}

	/**
	 * Adds the date range of its calendar to a Query block that contains one.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $parsed_block Parsed query block data.
	 * @param WP_Block|null        $parent_block Parent block instance.
	 *
	 * @return array<string, mixed> Updated block data.
	 */
	private function paginate_query_block( array $parsed_block, ?WP_Block $parent_block ): array {
		$inner_blocks   = is_array( $parsed_block['innerBlocks'] ?? null ) ? $parsed_block['innerBlocks'] : array();
		$calendar_attrs = self::find_inner_block_attrs( 'gatherpress/calendar', $inner_blocks );
		$attrs          = $parsed_block['attrs'] ?? null;

		if ( null === $calendar_attrs || ! is_array( $attrs ) || ! is_array( $attrs['query'] ?? null ) ) {
			return $parsed_block;
		}

		$query_id = $attrs['queryId'] ?? null;

		if ( is_numeric( $query_id ) ) {
			$this->calendar_query_ids[ (int) $query_id ] = true;
		}

		$page           = Query_Builder::get_requested_page( $query_id );
		$source_post_id = Date_Calculator::resolve_source_post_id( $calendar_attrs, $parent_block );
		$range          = Date_Calculator::calculate_date_range( $calendar_attrs, $page, $source_post_id );

		$attrs['query'][ self::CALENDAR_QUERY_PARAM ]      = true;
		$attrs['query'][ self::CALENDAR_QUERY_VIEW_TYPE ]  = $range['view_type'];
		$attrs['query'][ self::CALENDAR_QUERY_START_DATE ] = $range['start_date'];
		$attrs['query'][ self::CALENDAR_QUERY_END_DATE ]   = $range['end_date'];
		$attrs['query'][ self::CALENDAR_QUERY_HEADING ]    = $range['heading'];
		$parsed_block['attrs']                             = $attrs;

		return $parsed_block;
	}

	/**
	 * Gives a calendar block the date range of its parent Query block.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $parsed_block Parsed calendar block data.
	 * @param WP_Block|null        $parent_block Parent block instance.
	 *
	 * @return array<string, mixed> Updated block data.
	 */
	private function paginate_calendar_block( array $parsed_block, ?WP_Block $parent_block ): array {
		$query      = $parent_block instanceof WP_Block ? ( $parent_block->attributes['query'] ?? null ) : null;
		$start_date = is_array( $query ) ? ( $query[ self::CALENDAR_QUERY_START_DATE ] ?? null ) : null;

		if ( ! is_array( $query ) || ! is_string( $start_date ) ) {
			return $parsed_block;
		}

		$attrs     = is_array( $parsed_block['attrs'] ?? null ) ? $parsed_block['attrs'] : array();
		$view_type = $query[ self::CALENDAR_QUERY_VIEW_TYPE ] ?? $attrs['viewType'] ?? 'month';

		$attrs['selectedDate'] = $start_date;
		$attrs['viewType']     = is_string( $view_type ) ? $view_type : 'month';
		$parsed_block['attrs'] = $attrs;

		return $parsed_block;
	}

	/**
	 * Dynamically set date range and attributes based on core pagination query vars.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $parsed_block The parsed block data.
	 * @param array<string, mixed> $source_block The original block data.
	 * @param WP_Block|null        $parent_block The parent block instance (if any).
	 *
	 * @return array<string, mixed> The updated parsed block data.
	 */
	public function allow_core_pagination(
		array $parsed_block,
		array $source_block,
		?WP_Block $parent_block = null
	): array {
		$block_name = $parsed_block['blockName'] ?? '';

		if ( 'core/query' === $block_name ) {
			return $this->paginate_query_block( $parsed_block, $parent_block );
		}

		if ( 'gatherpress/calendar' === $block_name ) {
			return $this->paginate_calendar_block( $parsed_block, $parent_block );
		}

		// Only the Next Page block runs a query to decide whether it has a link.
		if (
			'core/query-pagination-next' === $block_name &&
			$parent_block instanceof WP_Block &&
			$this->is_calendar_query_context( $parent_block->context )
		) {
			add_filter( 'the_posts', array( $this, 'force_pagination_max_pages' ), 10, 2 );
		}

		return $parsed_block;
	}

	/**
	 * Lets the Next Page block of a calendar query link to the next date range.
	 *
	 * Runs once, on the first query after a calendar's Next Page block registered it, which is
	 * the query of that block.
	 *
	 * @since TBD
	 *
	 * @param array<WP_Post|int> $posts Array of post objects or IDs.
	 * @param WP_Query           $query The WP_Query instance.
	 *
	 * @return array<WP_Post|int> Array of post objects or IDs.
	 */
	public function force_pagination_max_pages( array $posts, WP_Query $query ): array {
		remove_filter( 'the_posts', array( $this, 'force_pagination_max_pages' ) );

		if ( ! empty( $query->query[ self::CALENDAR_QUERY_PARAM ] ) ) {
			$query->max_num_pages = self::MAX_PAGINATION_PAGES;
		}

		return $posts;
	}

	/**
	 * Hides the Page Numbers block of a calendar query.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/render_block_context/
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $context      Default context.
	 * @param array<string, mixed> $parsed_block The block being rendered.
	 *
	 * @return array<string, mixed> Updated block context.
	 */
	public function disable_query_pagination_numbers( array $context, array $parsed_block ): array {
		if (
			'core/query-pagination-numbers' !== ( $parsed_block['blockName'] ?? '' ) ||
			! is_array( $context['query'] ?? null ) ||
			! $this->is_calendar_query_context( $context )
		) {
			return $context;
		}

		// With 0 posts per page the Page Numbers block renders nothing.
		// The other pagination blocks do not change.
		$context['query']['perPage'] = 0;

		return $context;
	}

	/**
	 * Register allowed calendar query vars.
	 *
	 * @since TBD
	 *
	 * @param string[] $query_vars Allowed query variables.
	 *
	 * @return string[]
	 */
	public function query_vars( array $query_vars ): array {
		$query_vars[] = self::CALENDAR_QUERY_PARAM;
		$query_vars[] = self::CALENDAR_QUERY_VIEW_TYPE;
		$query_vars[] = self::CALENDAR_QUERY_START_DATE;
		$query_vars[] = self::CALENDAR_QUERY_END_DATE;

		return $query_vars;
	}

	/**
	 * Marks the queries of the blocks inside a calendar's Query block as calendar queries.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query WP_Query arguments.
	 * @param WP_Block             $block Block instance.
	 *
	 * @return array<string, mixed>
	 */
	public function query_loop_block_query_vars( array $query, WP_Block $block ): array {
		if ( ! $this->is_calendar_query_context( $block->context ) ) {
			return $query;
		}

		$block_query = is_array( $block->context['query'] ?? null ) ? $block->context['query'] : array();
		$query_args  = array( self::CALENDAR_QUERY_PARAM => true );

		/** This filter is documented in includes/core/classes/blocks/class-event-query.php */
		$filtered_query_args = apply_filters( 'gatherpress_query_vars', $query_args, $block_query, false );

		return array_merge( $query, is_array( $filtered_query_args ) ? $filtered_query_args : $query_args );
	}
}
