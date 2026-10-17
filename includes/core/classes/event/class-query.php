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
	 * Query parameter name for filtering the admin list by event month.
	 *
	 * Holds a `YYYYMM` value, the same shape WordPress core's `m` parameter
	 * uses for publish dates.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const EVENT_DATE_QUERY_PARAM = 'gatherpress_event_date';

	/**
	 * Query variable carrying a resolved event date window between hooks.
	 *
	 * Set on the query by `intercept_date_query()` during `pre_get_posts` and
	 * read back by `adjust_event_date_window_sql()` on `posts_clauses`. Never
	 * registered as a public query var, so it cannot arrive from a URL.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const EVENT_DATE_WINDOW_PARAM = 'gatherpress_event_date_window';

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
		add_filter( 'posts_clauses', array( $this, 'adjust_event_date_window_sql' ), 10, 2 );

		// Filter adjacent post queries to join and sort by event datetime.
		add_filter( 'get_previous_post_join', array( $this, 'get_adjacent_post_join' ), 10, 5 );
		add_filter( 'get_next_post_join', array( $this, 'get_adjacent_post_join' ), 10, 5 );
		add_filter( 'get_previous_post_where', array( $this, 'get_adjacent_post_where' ), 10, 5 );
		add_filter( 'get_next_post_where', array( $this, 'get_adjacent_post_where' ), 10, 5 );
		add_filter( 'get_previous_post_sort', array( $this, 'get_adjacent_post_sort' ), 10, 3 );
		add_filter( 'get_next_post_sort', array( $this, 'get_adjacent_post_sort' ), 10, 3 );
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
			'post_type'             => get_post_types_by_support( Event::SUPPORT ),
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

						$query->set( 'post_type', get_post_types_by_support( Event::SUPPORT ) );
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

		$this->intercept_date_query( $query );

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
			! post_type_supports( $current_screen->post_type, Event::SUPPORT ) ||
			$wp_query->get( 'post_type' ) !== $current_screen->post_type
		) {
			return $query_pieces;
		}

		remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_past_events' ) );
		remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_upcoming_events' ) );

		// Admin event list views can be filtered by 'upcoming', 'past' or 'all' events.
		// Public query vars keep whatever shape the request gave them, arrays
		// included, so both parameters are read back as scalars or not at all.
		$gatherpress_events_view  = $wp_query->get( self::EVENT_QUERY_PARAM );
		$gatherpress_events_query = is_scalar( $gatherpress_events_view ) && ! empty( $gatherpress_events_view )
			? (string) $gatherpress_events_view
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

		$gatherpress_event_month = $wp_query->get( self::EVENT_DATE_QUERY_PARAM );

		return $this->adjust_event_month_sql(
			$query_pieces,
			is_scalar( $gatherpress_event_month ) ? (string) $gatherpress_event_month : ''
		);
	}

	/**
	 * Narrow the admin event list to a single month of event dates.
	 *
	 * The companion to WordPress core's `m` parameter, which buckets the same
	 * list by publish date. `$month` takes core's `YYYYMM` shape; any other
	 * value leaves the clauses untouched, so a hand-edited URL degrades to an
	 * unfiltered list rather than an empty one.
	 *
	 * An event belongs to a month when it overlaps it, so one running from
	 * May 30 to June 2 answers to both. Filtering on the start alone would
	 * hide a running event from the month it is actually happening in.
	 *
	 * Compares the local columns rather than their `_gmt` counterparts so an
	 * event falls in the month the list table displays for it, which is
	 * rendered in the event's own timezone. Events with no row in the events
	 * table have no dates to overlap with and drop out of every month.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, string> $query_pieces An array containing pieces of the SQL query.
	 * @param string                $month        Month to filter by, as `YYYYMM`.
	 *
	 * @return array<string, string> The query pieces, with the month condition appended when $month is valid.
	 */
	protected function adjust_event_month_sql( array $query_pieces, string $month ): array {
		global $wpdb;

		if ( 1 !== preg_match( '/^(\d{4})(0[1-9]|1[0-2])$/', $month, $matches ) ) {
			return $query_pieces;
		}

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$year  = (int) $matches[1];
		$month = (int) $matches[2];

		// `t` resolves to the last day of the month, so the window closes on
		// the final second rather than opening the next month's first.
		$opens  = sprintf( '%04d-%02d-01 00:00:00', $year, $month );
		$closes = gmdate( 'Y-m-t 23:59:59', (int) gmmktime( 0, 0, 0, $month, 1, $year ) );

		$query_pieces['where'] .= $wpdb->prepare(
			' AND %i.%i <= %s AND %i.%i >= %s',
			$table,
			'datetime_start',
			$closes,
			$table,
			'datetime_end',
			$opens
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

		$pieces = $this->ensure_events_join( $pieces );
		$order  = strtoupper( $order );

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
	 * Join the events table onto a query once.
	 *
	 * Both the upcoming/past handlers and the date window handler need the
	 * join, and either may run without the other, so each asks for it here
	 * rather than appending its own and doubling the alias when both run.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, string> $pieces An array of query pieces, including join, where, orderby, and more.
	 *
	 * @return array<string, string> The pieces, with the events table joined.
	 */
	private function ensure_events_join( array $pieces ): array {
		global $wpdb;

		/**
		 * Escaped events table name.
		 *
		 * @var string $events_table esc_sql() only returns an array when it is handed one.
		 */
		$events_table = esc_sql( sprintf( Event::TABLE_FORMAT, $wpdb->prefix ) );

		/**
		 * Escaped posts table name.
		 *
		 * @var string $posts_table esc_sql() only returns an array when it is handed one.
		 */
		$posts_table = esc_sql( $wpdb->posts );
		$join        = (string) ( $pieces['join'] ?? '' );

		if ( ! str_contains( $join, $events_table ) ) {
			$join .= ' LEFT JOIN ' . $events_table . ' ON ' . $posts_table . '.ID=' . $events_table . '.post_id';
		}

		$pieces['join'] = $join;

		return $pieces;
	}

	/**
	 * Point a `date_query` at event dates rather than publish dates.
	 *
	 * WordPress reads `date_query` against `post_date`, which for an event is
	 * the day its post was written. For a query made up entirely of event post
	 * types the argument is lifted out here, before core turns it into SQL,
	 * resolved into a window by `Date_Query`, and carried to `posts_clauses`
	 * under `EVENT_DATE_WINDOW_PARAM`, where it is compared against the events
	 * table instead.
	 *
	 * Three things are left for core to handle the way it always has: a clause
	 * naming a core column such as `post_date`, which is how to keep filtering
	 * an event query by publish date; a clause `Date_Query` cannot read; and a
	 * query mixing event and non-event post types, whose non-event rows have no
	 * event dates and would silently drop out.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Query $query The query being prepared.
	 *
	 * @return void
	 */
	private function intercept_date_query( WP_Query $query ): void {
		$date_query = $query->get( 'date_query' );

		if ( empty( $date_query ) || ! is_array( $date_query ) || ! $this->queries_event_post_types_only( $query ) ) {
			return;
		}

		$window = Date_Query::resolve( $date_query );

		if ( null === $window ) {
			return;
		}

		$query->set( self::EVENT_DATE_WINDOW_PARAM, $window );
		$query->set( 'date_query', array() );
	}

	/**
	 * Whether every post type a query asks for carries event dates.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Query $query The query to inspect.
	 *
	 * @return bool True when the query is made up of event post types alone.
	 */
	private function queries_event_post_types_only( WP_Query $query ): bool {
		$post_types = array_filter( (array) $query->get( 'post_type' ) );

		if ( empty( $post_types ) ) {
			return false;
		}

		foreach ( $post_types as $post_type ) {
			if ( ! is_string( $post_type ) || ! post_type_supports( $post_type, Event::SUPPORT ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Narrow a query to events touching the window a `date_query` resolved to.
	 *
	 * An event belongs to the window when it overlaps it: its start falls
	 * before the window closes and its end falls after the window opens. An
	 * open-ended window drops the bound it lacks. Compares the GMT pair or the
	 * local pair according to the column the window was resolved for.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, string> $query_pieces An array containing pieces of the SQL query.
	 * @param WP_Query              $query        The WP_Query instance (passed by reference).
	 *
	 * @return array<string, string> The query pieces, narrowed when the query carries a window.
	 */
	public function adjust_event_date_window_sql( array $query_pieces, WP_Query $query ): array {
		global $wpdb;

		$window = $query->get( self::EVENT_DATE_WINDOW_PARAM );

		if ( empty( $window ) || ! is_array( $window ) || empty( $window['column'] ) ) {
			return $query_pieces;
		}

		$query_pieces = $this->ensure_events_join( $query_pieces );
		$table        = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$start_column = (string) $window['column'];
		$end_column   = str_replace( 'datetime_start', 'datetime_end', $start_column );

		if ( ! empty( $window['end'] ) ) {
			$query_pieces['where'] .= $wpdb->prepare(
				' AND %i.%i <= %s',
				$table,
				$start_column,
				$window['end']
			);
		}

		if ( ! empty( $window['start'] ) ) {
			$query_pieces['where'] .= $wpdb->prepare(
				' AND %i.%i >= %s',
				$table,
				$end_column,
				$window['start']
			);
		}

		return $query_pieces;
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

		foreach ( get_post_types_by_support( Venue::SUPPORT ) as $venue_post_type ) {
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
	 * Join the GatherPress events table for adjacent post queries.
	 *
	 * This method modifies the SQL JOIN clause for adjacent post queries (previous and next)
	 * to include the GatherPress events table.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/get_adjacent_post_join/
	 *
	 * @since 0.36.0
	 *
	 * @param string       $join           The JOIN clause in the SQL.
	 * @param bool         $in_same_term   Whether post should be in the same taxonomy term
	 *                                     (unused; part of the hook signature).
	 * @param int[]|string $excluded_terms Array of excluded term IDs. Empty string if none were provided
	 *                                     (unused; part of the hook signature).
	 * @param string       $taxonomy       Taxonomy. Used to identify the term used when `$in_same_term` is true
	 *                                     (unused; part of the hook signature).
	 * @param WP_Post      $post           The current post object.
	 *
	 * @return string The modified JOIN clause for adjacent post queries.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP's get_{$adjacent}_post_join hook signature.
	 */
	public function get_adjacent_post_join(
		string $join,
		bool $in_same_term,
		array|string $excluded_terms,
		string $taxonomy,
		WP_Post $post
	): string {
		if ( ! $this->follows_event_datetime( $post ) ) {
			return $join;
		}

		global $wpdb;
		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		return $join . $wpdb->prepare( ' INNER JOIN %i AS gpe ON p.ID = gpe.post_id', $table );
	}

	/**
	 * Modify the WHERE clause to compare by event datetime instead of post_date for adjacent post queries.
	 *
	 * This method modifies the SQL WHERE clause for adjacent post queries (previous and next)
	 * to compare by event datetime instead of the default post_date.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/get_adjacent_post_where/
	 *
	 * @since 0.36.0
	 *
	 * @param string       $where          The WHERE clause in the SQL.
	 * @param bool         $in_same_term   Whether post should be in the same taxonomy term
	 *                                     (unused; part of the hook signature).
	 * @param int[]|string $excluded_terms Array of excluded term IDs. Empty string if none were provided
	 *                                     (unused; part of the hook signature).
	 * @param string       $taxonomy       Taxonomy. Used to identify the term used when `$in_same_term` is true
	 *                                     (unused; part of the hook signature).
	 * @param WP_Post      $post           The current post object.
	 *
	 * @return string The modified WHERE clause for adjacent post queries.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP's get_{$adjacent}_post_where hook signature.
	 */
	public function get_adjacent_post_where(
		string $where,
		bool $in_same_term,
		array|string $excluded_terms,
		string $taxonomy,
		WP_Post $post
	): string {
		if ( ! $this->follows_event_datetime( $post ) ) {
			return $where;
		}

		global $wpdb;
		// One event is read through its own API; the candidates come from
		// the joined events table.
		$current = ( new Event( $post->ID ) )->get_datetime()['datetime_start_gmt'];

		// Core compares the publish date and, since 6.9, breaks a tie on ID.
		// Swap the date for the event start and keep the tiebreak, so events
		// that start at the same moment can still reach each other.
		if ( 'get_previous_post_where' === current_filter() ) {
			$comparison = $wpdb->prepare(
				'(gpe.datetime_start_gmt < %s OR (gpe.datetime_start_gmt = %s AND p.ID < %d))',
				$current,
				$current,
				$post->ID
			);
		} else {
			$comparison = $wpdb->prepare(
				'(gpe.datetime_start_gmt > %s OR (gpe.datetime_start_gmt = %s AND p.ID > %d))',
				$current,
				$current,
				$post->ID
			);
		}

		return (string) preg_replace(
			"/\(p\.post_date\s*[<>]\s*'[^']*' OR \(p\.post_date\s*=\s*'[^']*' AND p\.ID\s*[<>]\s*\d+\)\)/",
			$comparison,
			$where,
			1
		);
	}

	/**
	 * Whether an adjacent-post query for this post follows the event start.
	 *
	 * All three adjacent-post filters read this, so they switch together. A
	 * post with no event start yet, such as one on a post type that gained
	 * event support after it already had posts, keeps core's publish-date
	 * navigation throughout, rather than joining and sorting on a column its
	 * WHERE clause never compares.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Post $post The post being navigated from.
	 *
	 * @return bool Whether to join, compare, and sort by the event start.
	 */
	protected function follows_event_datetime( WP_Post $post ): bool {
		return post_type_supports( $post->post_type, Event::SUPPORT )
			&& '' !== ( new Event( $post->ID ) )->get_datetime()['datetime_start_gmt'];
	}

	/**
	 * Modify the ORDER BY clause to sort by event datetime for adjacent post queries.
	 *
	 * This method modifies the SQL ORDER BY clause for adjacent post queries (previous and next)
	 * to sort by event datetime instead of the default post_date.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/get_adjacent_post_sort/
	 *
	 * @since 0.36.0
	 *
	 * @param string  $sort  The ORDER BY clause in the SQL.
	 * @param WP_Post $post  The current post object.
	 * @param string  $order Sort order. 'DESC' for previous post, 'ASC' for next.
	 *
	 * @return string The modified ORDER BY clause for adjacent post queries.
	 */
	public function get_adjacent_post_sort( string $sort, WP_Post $post, string $order ): string {
		if ( ! $this->follows_event_datetime( $post ) ) {
			return $sort;
		}

		// Core hands over 'DESC' for previous and 'ASC' for next. A keyword
		// has no prepare() placeholder, so it is allowed through by name.
		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		return "ORDER BY gpe.datetime_start_gmt {$order}, p.ID {$order} LIMIT 1";
	}
}
