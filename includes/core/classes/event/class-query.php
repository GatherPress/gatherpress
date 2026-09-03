<?php
/**
 * Manages event-related queries and filtering.
 *
 * This class is responsible for handling all queries related to events, including retrieving
 * upcoming and past events, applying filters and ordering events. It also handles adjustments
 * for event pages and admin queries.
 *
 * @package GatherPress\Core\Event
 * @since 0.27.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Settings;
use GatherPress\Core\Shadow_Source;
use GatherPress\Core\Topic;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Setup;
use GatherPress\Core\Venue;
use WP_Post;
use WP_Query;

/**
 * Class Query.
 *
 * Responsible for managing event-related queries and customizations.
 *
 * @since 0.34.0
 */
final class Query {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query parameter name for event type filtering.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const EVENT_QUERY_PARAM = 'gatherpress_event_query';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'pre_get_posts', array( $this, 'prepare_event_query_before_execution' ) );
		// Priority 9 to run before the upcoming/past adjustments at priority 10.
		add_filter( 'posts_clauses', array( $this, 'adjust_admin_event_sorting' ), 9, 2 );
	}

	/**
	 * Retrieve upcoming events.
	 *
	 * Retrieves a list of upcoming events with optional filtering by the maximum number to display.
	 *
	 * @since 0.34.0
	 *
	 * @param int $number Maximum number of upcoming events to retrieve.
	 *
	 * @return WP_Query A WordPress query object containing the list of upcoming events.
	 */
	public function get_upcoming_events( int $number = 5 ): WP_Query {
		return $this->get_events_list( 'upcoming', $number );
	}

	/**
	 * Retrieve past events.
	 *
	 * Retrieves a list of past events with optional filtering by the maximum number to display.
	 *
	 * @since 0.34.0
	 *
	 * @param int $number Maximum number of past events to retrieve.
	 *
	 * @return WP_Query A WordPress query object containing the list of past events.
	 */
	public function get_past_events( int $number = 5 ): WP_Query {
		return $this->get_events_list( 'past', $number );
	}

	/**
	 * Retrieve a list of events based on specified criteria.
	 *
	 * This method queries and returns a list of events based on the event list type (upcoming or past),
	 * maximum number to display, optional topics, and venues for filtering. The results are returned as
	 * a WordPress query object.
	 *
	 * @since 0.34.0
	 *
	 * @param string   $event_list_type Type of event list: 'upcoming' or 'past'.
	 * @param int      $number          Maximum number of events to retrieve.
	 * @param string[] $topics          Array of topic slugs for additional filtering.
	 * @param string[] $venues          Array of venue slugs for additional filtering.
	 *
	 * @return WP_Query A WordPress query object containing the list of events.
	 */
	public function get_events_list(
		string $event_list_type = '',
		int $number = 5,
		array $topics = array(),
		array $venues = array()
	): WP_Query {
		// Past events should be ordered DESC (most recent first),
		// upcoming events should be ordered ASC (soonest first).
		$order = ( 'past' === $event_list_type ) ? 'DESC' : 'ASC';

		$args = array(
			'post_type'             => get_post_types_by_support( 'gatherpress-event-date' ),
			'fields'                => 'ids',
			'no_found_rows'         => true,
			'posts_per_page'        => $number,
			self::EVENT_QUERY_PARAM => $event_list_type,
			'order'                 => $order,
		);

		$tax_query = array();

		if ( ! empty( $venues ) && ! empty( $topics ) ) {
			$tax_query[] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => Topic::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $topics,
				),
				$this->build_venue_tax_query( $venues ),
			);
		} elseif ( ! empty( $topics ) ) {
			$tax_query[] = array(
				'taxonomy' => Topic::TAXONOMY,
				'field'    => 'slug',
				'terms'    => $topics,
			);
		} elseif ( ! empty( $venues ) ) {
			$tax_query[] = $this->build_venue_tax_query( $venues );
		}

		$args['tax_query'] = $tax_query; //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		return new WP_Query( $args );
	}

	/**
	 * Set event query and order adjustments before a query is executed.
	 *
	 * This method prepares and adjusts the event query based on specified criteria before it is executed.
	 * It primarily handles adjustments for event archive pages, such as changing the post type, ordering,
	 * and filtering. This method is typically hooked into the 'pre_get_posts' action.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Query $query An instance of WP_Query representing the event query.
	 *
	 * @return void
	 */
	public function prepare_event_query_before_execution( WP_Query $query ): void {
		$events_query = $query->get( self::EVENT_QUERY_PARAM );

		if ( ! is_admin() && $query->is_main_query() ) {
			$settings = Settings::get_instance();

			$archive_pages = array(
				'past'     => json_decode( $settings->get( 'past_events' ) ),
				'upcoming' => json_decode( $settings->get( 'upcoming_events' ) ),
			);

			// Resolve the current page ID from query vars since
			// queried_object_id is not yet populated during pre_get_posts.
			$current_page_id = $query->get( 'page_id' );

			if ( ! $current_page_id ) {
				$pagename = $query->get( 'pagename' );

				if ( $pagename ) {
					$page_obj = get_page_by_path( $pagename );

					if ( $page_obj ) {
						$current_page_id = $page_obj->ID;
					}
				}
			}

			foreach ( $archive_pages as $key => $value ) {
				if ( ! empty( $value ) && is_array( $value ) ) {
					$page = $value[0];

					if ( $current_page_id && $page->id === $current_page_id ) {
						$page_id      = $query->queried_object_id;
						$events_query = $key;

						$query->set( 'post_type', get_post_types_by_support( 'gatherpress-event-date' ) );
						$query->set( self::EVENT_QUERY_PARAM, $key );
						$query->is_page              = false;
						$query->is_singular          = false;
						$query->is_archive           = true;
						$query->is_post_type_archive = true;

						// This will force a page to behave like an archive page. Use -1 as that is not a valid ID.
						$query->queried_object_id = -1;

						// Option adjustments for page_for_posts and show_on_front to force archive page.
						add_filter(
							'pre_option',
							static function ( $pre, $option ) {
								if ( 'page_for_posts' === $option ) {
									return -1;
								}

								if ( 'show_on_front' === $option ) {
									return 'page';
								}

								return $pre;
							},
							10,
							2
						);

						// Pass original page title as archive title.
						add_filter(
							'get_the_archive_title',
							static function () use ( $page_id ) {
								return get_the_title( $page_id );
							}
						);
					}
				}
			}
		}

		// Filter events by the current shadow-source post when the contextual
		// filter is enabled. Resolution + clause-building live in Shadow_Source
		// so any consumer can reuse them; we just merge the clause into the
		// existing tax_query and call $query->set().
		if ( ! empty( $query->get( 'shadow_filter' ) ) ) {
			$shadow_source = Shadow_Source::get_instance();
			$source_post   = $shadow_source->resolve_post_from_query_context( $query );

			if ( $source_post instanceof WP_Post ) {
				$existing_tax_query = $query->get( 'tax_query' );

				if ( ! is_array( $existing_tax_query ) ) {
					$existing_tax_query = array();
				}

				$existing_tax_query[] = $shadow_source->build_tax_query_clause( $source_post );

				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				$query->set( 'tax_query', $existing_tax_query );
			}
		}

		// When the "filter by event activity" toggle is on, restrict the query to
		// shadow-source posts whose shadow terms sit on an upcoming or past event.
		// Runs early so the resolved source IDs can still compose with the query
		// loop's post_type, tax_query, and pagination.
		if ( 1 === (int) $query->get( 'has_events_filter' ) ) {
			$post_type = $query->get( 'post_type' );

			if (
				is_string( $post_type )
				&& post_type_supports( $post_type, 'gatherpress-shadow-source' )
			) {
				// The block normally writes upcoming_events_only as an integer (0/1),
				// but AQL can pass the literal strings 'upcoming' or 'past'. Default
				// on when the attribute is absent.
				$upcoming_value = $query->get( 'upcoming_events_only', 1 );
				$upcoming       = 'upcoming' === $upcoming_value || 1 === (int) $upcoming_value;

				$source_ids = Shadow_Source::get_instance()->get_source_post_ids_by_event_activity(
					$post_type,
					$upcoming
				);

				// Null means the source's shadow taxonomy is not wired onto any
				// event post type, so the filter does not apply and the query
				// keeps its own scope.
				if ( null !== $source_ids ) {
					// A post__in the query already carries is a narrower scope the
					// caller asked for, so intersect rather than merge -- merging
					// would widen the result set and make the filter add posts
					// instead of removing them. WP_Query::get() returns '' for an
					// unset var, so only treat an actual array as a prior scope.
					$existing_post_in = $query->get( 'post__in' );
					$existing_post_in = is_array( $existing_post_in )
						? array_map( 'intval', $existing_post_in )
						: array();

					if ( ! empty( $existing_post_in ) ) {
						$source_ids = array_values( array_intersect( $existing_post_in, $source_ids ) );
					}

					// An empty result means the filter ran and matched nothing,
					// which is a valid answer rather than an open list; pin the
					// query to an impossible ID so it returns empty.
					$query->set( 'post__in', ! empty( $source_ids ) ? $source_ids : array( 0 ) );
				}
			}
		}

		switch ( $events_query ) {
			case 'upcoming':
				remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_past_events' ) );
				add_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_upcoming_events' ), 10, 2 );
				break;
			case 'past':
				add_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_past_events' ), 10, 2 );
				remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_upcoming_events' ) );
				break;
			default:
				remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_past_events' ) );
				remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_upcoming_events' ) );
		}
	}

	/**
	 * Adjust the sorting criteria for upcoming events in a query.
	 *
	 * This method modifies the SQL query pieces, including join, where, orderby, etc., to adjust the sorting criteria
	 * for upcoming events in the query. It ensures that events are ordered by their start datetime in ascending order.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/posts_clauses/
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, string> $query_pieces An array containing pieces of the SQL query.
	 * @param WP_Query              $query        The WP_Query instance (passed by reference).
	 *
	 * @return array<string, string> The modified SQL query pieces with adjusted sorting criteria for upcoming events.
	 */
	public function adjust_sorting_for_upcoming_events( array $query_pieces, WP_Query $query ): array {
		$include_unfinished = $query->get( 'include_unfinished' );
		// Default to true if not explicitly set to maintain backward compatibility.
		$inclusive = ( '' === $include_unfinished ) ? true : (bool) $include_unfinished;

		return $this->adjust_event_sql(
			$query_pieces,
			'upcoming',
			$query->get( 'order' ),
			$query->get( 'orderby' ),
			$inclusive
		);
	}

	/**
	 * Adjust the sorting criteria for past events in a query.
	 *
	 * This method modifies the SQL query pieces, including join, where, orderby, etc., to adjust the sorting criteria
	 * for past events in the query. It ensures that events are ordered by their start datetime in the desired order.
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, string> $query_pieces An array containing pieces of the SQL query.
	 * @param WP_Query              $query        The WP_Query instance (passed by reference).
	 *
	 * @return array<string, string> The modified SQL query pieces with adjusted sorting criteria for past events.
	 */
	public function adjust_sorting_for_past_events( array $query_pieces, WP_Query $query ): array {
		$include_unfinished = $query->get( 'include_unfinished' );
		// For past events, default to false (exclude currently running events).
		// This shows only truly finished events unless explicitly requested otherwise.
		$inclusive = ( '' === $include_unfinished ) ? false : (bool) $include_unfinished;

		return $this->adjust_event_sql(
			$query_pieces,
			'past',
			$query->get( 'order' ),
			$query->get( 'orderby' ),
			$inclusive
		);
	}

	/**
	 * Adjust event sorting criteria for the WordPress admin panel.
	 *
	 * This method modifies the SQL query pieces, including join, where, orderby, etc., to adjust the sorting criteria
	 * for events when viewing them in the WordPress admin panel. It specifically handles sorting by event datetime.
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, string> $query_pieces An array containing pieces of the SQL query.
	 * @param WP_Query              $wp_query     The WP_Query instance (passed by reference).
	 *
	 * @return array<string, string> The modified SQL query pieces with adjusted sorting criteria.
	 */
	public function adjust_admin_event_sorting( array $query_pieces, WP_Query $wp_query ): array {
		if ( ! is_admin() ) {
			return $query_pieces;
		}

		/**
		 * Run only for listings of posts, that support event dates.
		 *
		 * First checks whether the get_current_screen function exists,
		 * because it is loaded only after the 'admin_init' hook.
		 *
		 * @see https://developer.wordpress.org/reference/functions/get_current_screen/#comment-5424
		 *
		 * This sanity check was added after it's been reported that some admin screens may not have $wp_query set.
		 * @see https://wordpress.org/support/topic/gatherpress-has-critical-error-when-i-access-wpforms-payment-settings/
		 */
		$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if (
			! $current_screen ||
			'edit' !== $current_screen->base ||
			! post_type_supports( $current_screen->post_type, 'gatherpress-event-date' ) ||
			$wp_query->get( 'post_type' ) !== $current_screen->post_type
		) {
			return $query_pieces;
		}

		remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_past_events' ) );
		remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_upcoming_events' ) );

		// Admin event list views can be filtered by 'upcoming', 'past' or 'all' events.
		$gatherpress_events_query = ( ! empty( $wp_query->get( self::EVENT_QUERY_PARAM ) ) )
			? $wp_query->get( self::EVENT_QUERY_PARAM )
			: 'all';

		// Upcoming is inclusive (running events count as upcoming);
		// past is non-inclusive (running events excluded). This makes
		// the buckets mutually exclusive at `datetime_end_gmt` so a
		// running event appears only in upcoming, never in both.
		$inclusive    = ( 'past' !== $gatherpress_events_query );
		$query_pieces = $this->adjust_event_sql(
			$query_pieces,
			$gatherpress_events_query,
			$wp_query->get( 'order' ),
			$wp_query->get( 'orderby' ),
			$inclusive
		);

		return $query_pieces;
	}

	/**
	 * Adjust SQL clauses for Event queries to join on the gatherpress_events table.
	 *
	 * This method adjusts various SQL clauses (e.g., join, where, orderby) for Event queries to include
	 * the `gatherpress_events` table in the database join. It allows querying events based on different
	 * criteria such as upcoming or past events and specifying the event order (DESC or ASC).
	 *
	 * @see https://developer.wordpress.org/reference/hooks/posts_join/
	 * @see https://developer.wordpress.org/reference/hooks/posts_orderby/
	 * @see https://developer.wordpress.org/reference/hooks/posts_where/
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, string> $pieces    An array of query pieces, including join, where, orderby,
	 *                                         and more.
	 * @param string                $type      The type of events to query (options: 'all', 'upcoming', 'past')
	 *                                         (Default: 'all').
	 * @param string                $order     The event order ('DESC' for descending or 'ASC' for ascending)
	 *                                         (Default: 'DESC').
	 * @param string[]|string       $order_by  List or singular string of ORDERBY statement(s)
	 *                                         (Default: ['datetime']).
	 * @param bool                  $inclusive Whether to include currently running events in the query
	 *                                         (Default: true).
	 *
	 * @return array<string, string> An array containing adjusted SQL clauses for the Event query.
	 */
	public function adjust_event_sql(
		array $pieces,
		string $type = 'all',
		string $order = 'DESC',
		$order_by = array( 'datetime' ),
		bool $inclusive = true
	): array {
		global $wpdb;

		$defaults = array(
			'where'    => '',
			'groupby'  => '',
			'join'     => '',
			'orderby'  => '',
			'distinct' => '',
			'fields'   => '',
			'limits'   => '',
		);
		$pieces   = array_merge( $defaults, $pieces );

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		/**
		 * Escaped events table name.
		 *
		 * @var string $events_table esc_sql() only returns an array when it is handed one.
		 */
		$events_table = esc_sql( $table );

		/**
		 * Escaped posts table name.
		 *
		 * @var string $posts_table esc_sql() only returns an array when it is handed one.
		 */
		$posts_table = esc_sql( $wpdb->posts );

		$pieces['join'] .= ' LEFT JOIN ' . $events_table . ' ON ' . $posts_table . '.ID='
						. $events_table . '.post_id';
		$order           = strtoupper( $order );

		if ( in_array( $order, array( 'DESC', 'ASC' ), true ) ) {
			// ORDERBY is an array, which allows to orderby multiple values.
			// Currently, it is only allowed to order events by ONE value.
			$order_by = ( is_array( $order_by ) ) ? $order_by[0] : $order_by;

			switch ( strtolower( $order_by ) ) {
				case 'id':
					$pieces['orderby'] = sprintf( $posts_table . '.ID %s', esc_sql( $order ) );
					break;
				case 'title':
					$pieces['orderby'] = sprintf( $posts_table . '.post_name %s', esc_sql( $order ) );
					break;
				case 'modified':
					$pieces['orderby'] = sprintf(
						$posts_table . '.post_modified_gmt %s',
						esc_sql( $order )
					);
					break;
				case 'rand':
					$pieces['orderby'] = esc_sql( 'RAND()' );
					break;
				case 'datetime':
					$pieces['orderby'] = sprintf( $events_table . '.datetime_start_gmt %s', esc_sql( $order ) );
					break;
				default:
					// Custom column sorting (e.g., rsvps, venue) is handled
					// by posts_orderby filters; do not override their clause.
					break;
			}
		}

		if ( 'all' === $type ) {
			return $pieces;
		}

		$current = gmdate( Event::DATETIME_FORMAT, time() );
		$column  = $this->get_datetime_comparison_column( $type, $inclusive );

		// Append a date-based condition to the WHERE clause, filtering as
		// either upcoming or past. Events with no row in the events table
		// (no date set yet) are excluded from both buckets — they only
		// appear under the All view.
		if ( 'upcoming' === $type ) {
			$pieces['where'] .= $wpdb->prepare( ' AND %i.%i >= %s', $table, $column, $current );
		} elseif ( 'past' === $type ) {
			$pieces['where'] .= $wpdb->prepare( ' AND %i.%i < %s', $table, $column, $current );
		}

		return $pieces;
	}

	/**
	 * Builds a WP_Query compatible tax_query array for filtering events by venue slugs.
	 *
	 * Creates an OR relation across all registered venue post type taxonomies, allowing
	 * events to be filtered by venue regardless of which venue post type they use.
	 *
	 * @since 0.34.0
	 *
	 * @param string[] $venues Array of venue slugs to filter by.
	 *
	 * @return array<int|string, string|array{taxonomy: string, field: string, terms: string[]}>
	 *               WP_Query compatible tax_query array: an `OR` relation under the `relation` key,
	 *               plus one clause per registered venue taxonomy under integer keys.
	 */
	private function build_venue_tax_query( array $venues ): array {
		$venue_tax_query = array( 'relation' => 'OR' );

		foreach ( get_post_types_by_support( 'gatherpress-venue-information' ) as $venue_post_type ) {
			$venue_tax_query[] = array(
				'taxonomy' => Setup::get_instance()->get_taxonomy( $venue_post_type ),
				'field'    => 'slug',
				'terms'    => $venues,
			);
		}

		return $venue_tax_query;
	}

	/**
	 * Determine which db column to compare against,
	 * based on the type of event query (either upcoming or past)
	 * and if started but unfinished events should be included.
	 *
	 * @param  string $type      The type of events to query (options: 'all', 'upcoming', 'past')
	 *                          (Cannot be 'all' anymore).
	 * @param  bool   $inclusive Whether to include currently running events in the query.
	 *
	 * @return string Name of the DB column, which content to compare against the current time.
	 */
	protected function get_datetime_comparison_column( string $type, bool $inclusive ): string {
		if (
			// Upcoming events, including ones that are running.
			( $inclusive && 'upcoming' === $type ) ||
			// Past events, that are finished already.
			( ! $inclusive && 'past' === $type )
		) {
			return 'datetime_end_gmt';
		}

		// All others, means:
		// - Upcoming events, without running events.
		// - Past events, that are still running.
		return 'datetime_start_gmt';
	}

	/**
	 * Return the shadow term slugs that sit on an upcoming or a past event.
	 *
	 * Answers "which shadow-source posts have event activity?" in a single
	 * indexed query. The join runs from the term relationships straight into
	 * the events table, so the result set is bounded by the number of source
	 * posts that carry events rather than by the size of the event table --
	 * no event rows are ever materialized in PHP.
	 *
	 * Sentinel terms are excluded in SQL: real shadow term slugs always carry
	 * the leading underscore that {@see Shadow_Source::term_slug_from_post_name()}
	 * adds, while sentinels such as the venue subsystem's `online-event`
	 * deliberately do not.
	 *
	 * @since 0.36.0
	 *
	 * @param string $taxonomy Shadow taxonomy to read term slugs from.
	 * @param bool   $upcoming Whether to match upcoming events rather than past ones.
	 *
	 * @return string[] Distinct shadow term slugs, empty when nothing matched.
	 */
	public function get_active_shadow_term_slugs( string $taxonomy, bool $upcoming ): array {
		global $wpdb;

		$event_post_types = get_post_types_by_support( 'gatherpress-event-date' );

		// An empty IN () list is invalid SQL, and with no event post type
		// registered there is nothing the filter could match anyway.
		if ( empty( $event_post_types ) ) {
			return array();
		}

		$private_event_post_types = array();

		foreach ( $event_post_types as $event_post_type ) {
			$post_type_object   = get_post_type_object( $event_post_type );
			$private_capability = $post_type_object->cap->read_private_posts ?? 'read_private_posts';

			if ( current_user_can( $private_capability ) ) {
				$private_event_post_types[] = $event_post_type;
			}
		}

		// Same column and clock the posts_clauses filters use, so this query and
		// a regular upcoming/past event query always agree on the boundary.
		$column  = $this->get_datetime_comparison_column( $upcoming ? 'upcoming' : 'past', $upcoming );
		$current = gmdate( Event::DATETIME_FORMAT, time() );
		$table   = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		$post_type_placeholders = implode( ', ', array_fill( 0, count( $event_post_types ), '%s' ) );
		$private_type_condition = '';
		$status_args            = array( 'publish' );

		if ( ! empty( $private_event_post_types ) ) {
			$private_type_placeholders = implode(
				', ',
				array_fill( 0, count( $private_event_post_types ), '%s' )
			);
			$private_type_condition    = ' OR ( p.post_status = %s AND p.post_type IN ('
				. $private_type_placeholders . ') )';
			$status_args               = array_merge(
				$status_args,
				array( 'private' ),
				$private_event_post_types
			);
		}

		// Only the generated placeholder runs are interpolated; every value is
		// still bound through prepare() below.
		$sql = 'SELECT DISTINCT t.slug'
			. ' FROM %i AS tr'
			. ' INNER JOIN %i AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
			. ' INNER JOIN %i AS t ON t.term_id = tt.term_id'
			. ' INNER JOIN %i AS e ON e.post_id = tr.object_id'
			. ' INNER JOIN %i AS p ON p.ID = tr.object_id'
			. ' WHERE tt.taxonomy = %s'
			. ' AND p.post_type IN (' . $post_type_placeholders . ')'
			. ' AND ( p.post_status = %s'
			. $private_type_condition
			. ' )'
			. ' AND t.slug LIKE %s';

		// Two literal fragments rather than an interpolated operator.
		if ( $upcoming ) {
			$sql .= ' AND e.%i >= %s';
		} else {
			$sql .= ' AND e.%i < %s';
		}

		$args = array_merge(
			array(
				$wpdb->term_relationships,
				$wpdb->term_taxonomy,
				$wpdb->terms,
				$table,
				$wpdb->posts,
				$taxonomy,
			),
			array_values( $event_post_types ),
			$status_args,
			array(
				$wpdb->esc_like( '_' ) . '%',
				$column,
				$current,
			)
		);

		// $sql is assembled from literals above: only the generated placeholder
		// runs are interpolated, and every value is bound by prepare(). The
		// identifier placeholders use the %i form phpcs does not yet recognize.
		// The result is time-relative, so a persistent cache would go stale as
		// events cross the upcoming/past boundary.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$slugs = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'strval', (array) $slugs );
	}
}
