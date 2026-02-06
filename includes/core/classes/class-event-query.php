<?php
/**
 * Manages event-related queries and filtering.
 *
 * This class is responsible for handling all queries related to events, including retrieving
 * upcoming and past events, applying filters and ordering events. It also handles adjustments
 * for event pages and admin queries.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Query;

/**
 * Class Event_Query.
 *
 * Responsible for managing event-related queries and customizations.
 *
 * @since 1.0.0
 */
class Event_Query {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query parameter name for event type filtering.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const EVENT_QUERY_PARAM = 'gatherpress_event_query';

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
		add_action( 'pre_get_posts', array( $this, 'prepare_event_query_before_execution' ) );
		add_filter( 'posts_clauses', array( $this, 'adjust_admin_event_sorting' ), 9, 2 ); // Priority 9 to run before the upcoming/past adjustments at priority 10.
	}

	/**
	 * Retrieve upcoming events.
	 *
	 * Retrieves a list of upcoming events with optional filtering by the maximum number to display.
	 *
	 * @since 1.0.0
	 *
	 * @param int $number Maximum number of upcoming events to retrieve.
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
	 * @since 1.0.0
	 *
	 * @param int $number Maximum number of past events to retrieve.
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
	 * @since 1.0.0
	 *
	 * @param string $event_list_type Type of event list: 'upcoming' or 'past'.
	 * @param int    $number          Maximum number of events to retrieve.
	 * @param array  $topics          Array of topic slugs for additional filtering.
	 * @param array  $venues          Array of venue slugs for additional filtering.
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
			'post_type'             => Event::POST_TYPE,
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
				array(
					'taxonomy' => Venue::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $venues,
				),
			);
		} elseif ( ! empty( $topics ) ) {
			$tax_query[] = array(
				'taxonomy' => Topic::TAXONOMY,
				'field'    => 'slug',
				'terms'    => $topics,
			);
		} elseif ( ! empty( $venues ) ) {
			$tax_query[] = array(
				'taxonomy' => Venue::TAXONOMY,
				'field'    => 'slug',
				'terms'    => $venues,
			);
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
	 * @since 1.0.0
	 *
	 * @param WP_Query $query An instance of WP_Query representing the event query.
	 * @return void
	 */
	public function prepare_event_query_before_execution( WP_Query $query ): void {
		// if ( ! in_array( Event::POST_TYPE, (array) $query->get( 'post_type' ), true ) ) {
		// 	return;
		// }

		$events_query = $query->get( self::EVENT_QUERY_PARAM );

		if ( ! is_admin() && $query->is_main_query() ) {
			$general = get_option( Utility::prefix_key( 'general' ) );

			if ( ! is_array( $general ) ) {
				return;
			}

			$pages = $general['pages'] ?? '';

			if ( empty( $pages ) || ! is_array( $pages ) ) {
				return;
			}

			$archive_pages = array(
				'past'     => json_decode( $pages['past_events'] ),
				'upcoming' => json_decode( $pages['upcoming_events'] ),
			);

			foreach ( $archive_pages as $key => $value ) {
				if ( ! empty( $value ) && is_array( $value ) ) {
					$page = $value[0];

					if ( $page->id === $query->queried_object_id ) {
						$page_id      = $query->queried_object_id;
						$events_query = $key;

						$query->set( 'post_type', 'gatherpress_event' );
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
	 * @since 1.0.0
	 *
	 * @param array    $query_pieces An array containing pieces of the SQL query.
	 * @param WP_Query $query        The WP_Query instance (passed by reference).
	 * @return array The modified SQL query pieces with adjusted sorting criteria for upcoming events.
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
	 * @since 1.0.0
	 *
	 * @param array    $query_pieces An array containing pieces of the SQL query.
	 * @param WP_Query $query        The WP_Query instance (passed by reference).
	 * @return array The modified SQL query pieces with adjusted sorting criteria for past events.
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
	 * @since 1.0.0
	 *
	 * @param array    $query_pieces An array containing pieces of the SQL query.
	 * @param WP_Query $wp_query     The WP_Query instance (passed by reference).
	 * @return array The modified SQL query pieces with adjusted sorting criteria.
	 */
	public function adjust_admin_event_sorting( array $query_pieces, WP_Query $wp_query ): array {
		if ( ! is_admin() ) {
			return $query_pieces;
		}

		/**
		 * Run only for Event post listings.
		 *
		 * First checks whether the get_current_screen function exists,
		 * because it is loaded only after the 'admin_init' hook.
		 *
		 * @see https://developer.wordpress.org/reference/functions/get_current_screen/#comment-5424
		 *
		 * This sanity check was added after it's been reported that some admin screens may not have $wp_query set.
		 * @see https://wordpress.org/support/topic/gatherpress-has-critical-error-when-i-access-wpforms-payment-settings/
		 */
		$screen_id      = sprintf( 'edit-%s', Event::POST_TYPE );
		$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $current_screen || $screen_id !== $current_screen->id ) {
			return $query_pieces;
		}

		remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_past_events' ) );
		remove_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_upcoming_events' ) );

		// if ( 'datetime' === $wp_query->get( 'orderby' ) ) {

			// Admin event list views can be filtered by 'upcoming', 'past' or 'all' events.
			$gatherpress_events_query = ( ! empty( $wp_query->get( self::EVENT_QUERY_PARAM ) ) )
				? $wp_query->get( self::EVENT_QUERY_PARAM )
				: 'all';
			$query_pieces             = $this->adjust_event_sql(
				$query_pieces,
				$gatherpress_events_query,
				$wp_query->get( 'order' ),
				$wp_query->get( 'orderby' )
			);
		// }

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
	 * @since 1.0.0
	 *
	 * @param array           $pieces    An array of query pieces, including join, where, orderby,
	 *                                   and more.
	 * @param string          $type      The type of events to query (options: 'all', 'upcoming', 'past')
	 *                                   (Default: 'all').
	 * @param string          $order     The event order ('DESC' for descending or 'ASC' for ascending)
	 *                                   (Default: 'DESC').
	 * @param string[]|string $order_by  List  or singular string of ORDERBY statement(s) (Default: ['datetime']).
	 * @param bool            $inclusive Whether to include currently running events in the query (Default: true).
	 * @return array An array containing adjusted SQL clauses for the Event query.
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

		$table           = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$pieces['join'] .= ' LEFT JOIN ' . esc_sql( $table ) . ' ON ' . esc_sql( $wpdb->posts ) . '.ID='
						. esc_sql( $table ) . '.post_id';
		$order           = strtoupper( $order );

		if ( in_array( $order, array( 'DESC', 'ASC' ), true ) ) {
			// ORDERBY is an array, which allows to orderby multiple values.
			// Currently, it is only allowed to order events by ONE value.
			$order_by = ( is_array( $order_by ) ) ? $order_by[0] : $order_by;

			switch ( strtolower( $order_by ) ) {
				case 'id':
					$pieces['orderby'] = sprintf( esc_sql( $wpdb->posts ) . '.ID %s', esc_sql( $order ) );
					break;
				case 'title':
					$pieces['orderby'] = sprintf( esc_sql( $wpdb->posts ) . '.post_name %s', esc_sql( $order ) );
					break;
				case 'modified':
					$pieces['orderby'] = sprintf(
						esc_sql( $wpdb->posts ) . '.post_modified_gmt %s',
						esc_sql( $order )
					);
					break;
				case 'rand':
					$pieces['orderby'] = esc_sql( 'RAND()' );
					break;
				case 'datetime':
				default:
					$pieces['orderby'] = sprintf( esc_sql( $table ) . '.datetime_start_gmt %s', esc_sql( $order ) );
					break;
			}
		}

		if ( 'all' === $type ) {
			return $pieces;
		}

		$current = gmdate( Event::DATETIME_FORMAT, time() );
		$column  = $this->get_datetime_comparison_column( $type, $inclusive );

		// Appends a date-based condition to the WHERE clause of the SQL query,
		// filtering events as either upcoming or past.
		if ( 'upcoming' === $type ) {
			// Include only events starting on or after the current date/time (upcoming).
			$pieces['where'] .= $wpdb->prepare( ' AND %i.%i >= %s', $table, $column, $current );
		} elseif ( 'past' === $type ) {
			// Include only events starting before the current date/time (past).
			$pieces['where'] .= $wpdb->prepare( ' AND %i.%i < %s', $table, $column, $current );
		}

		return $pieces;
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
}
