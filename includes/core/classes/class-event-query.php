<?php
/**
 * Manages event-related queries and filtering.
 *
 * This class is responsible for handling all queries related to events, including retrieving
 * upcoming and past events, applying filters, and ordering events. It also handles adjustments
 * for event pages and admin queries.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

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
		add_filter( 'posts_clauses', array( $this, 'adjust_admin_event_sorting' ) );
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
		$args = array(
			'post_type'       => Event::POST_TYPE,
			'fields'          => 'ids',
			'no_found_rows'   => true,
			'posts_per_page'  => $number,
			'gp_events_query' => $event_list_type,
		);

		$tax_query = array();

		if ( ! empty( $venues ) && ! empty( $topics ) ) {
			$tax_query[] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => Event::TAXONOMY,
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
				'taxonomy' => Event::TAXONOMY,
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
		$events_query = $query->get( 'gp_events_query' );

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
						$query->set( 'post_type', 'gp_event' );

						$page_id                     = $query->queried_object_id;
						$events_query                = $key;
						$query->is_page              = false;
						$query->is_singular          = false;
						$query->is_archive           = true;
						$query->is_post_type_archive = array( Event::POST_TYPE );

						// This will force a page to behave like an archive page. Use -1 as that is not a valid ID.
						$query->queried_object_id = '-1';

						// Option adjustments for page_for_posts and show_on_front to force archive page.
						add_filter(
							'pre_option',
							static function ( $pre, $option ) {
								if ( 'page_for_posts' === $option ) {
									return '-1';
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
							function() use ( $page_id ) {
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
				add_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_upcoming_events' ) );
				break;
			case 'past':
				add_filter( 'posts_clauses', array( $this, 'adjust_sorting_for_past_events' ) );
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
	 * @since 1.0.0
	 *
	 * @param array $query_pieces An array containing pieces of the SQL query.
	 * @return array The modified SQL query pieces with adjusted sorting criteria for upcoming events.
	 */
	public function adjust_sorting_for_upcoming_events( array $query_pieces ): array {
		return $this->adjust_event_sql( $query_pieces, 'upcoming', 'ASC' );
	}

	/**
	 * Adjust the sorting criteria for past events in a query.
	 *
	 * This method modifies the SQL query pieces, including join, where, orderby, etc., to adjust the sorting criteria
	 * for past events in the query. It ensures that events are ordered by their start datetime in the desired order.
	 *
	 * @param array $query_pieces An array containing pieces of the SQL query.
	 *
	 * @return array The modified SQL query pieces with adjusted sorting criteria for past events.
	 */
	public function adjust_sorting_for_past_events( array $query_pieces ): array {
		return $this->adjust_event_sql( $query_pieces, 'past' );
	}

	/**
	 * Adjust event sorting criteria for the WordPress admin panel.
	 *
	 * This method modifies the SQL query pieces, including join, where, orderby, etc., to adjust the sorting criteria
	 * for events when viewing them in the WordPress admin panel. It specifically handles sorting by event datetime.
	 *
	 * @since 1.0.0
	 *
	 * @param array $query_pieces An array containing pieces of the SQL query.
	 * @return array The modified SQL query pieces with adjusted sorting criteria.
	 */
	public function adjust_admin_event_sorting( array $query_pieces ): array {
		if ( ! is_admin() ) {
			return $query_pieces;
		}

		global $wp_query;

		if ( 'datetime' === $wp_query->get( 'orderby' ) ) {
			$query_pieces = $this->adjust_event_sql( $query_pieces, 'all', $wp_query->get( 'order' ) );
		}

		return $query_pieces;
	}

	/**
	 * Adjust SQL clauses for Event queries to join on the gp_events table.
	 *
	 * This method adjusts various SQL clauses (e.g., join, where, orderby) for Event queries to include
	 * the `gp_events` table in the database join. It allows querying events based on different
	 * criteria such as upcoming or past events and specifying the event order (DESC or ASC).
	 *
	 * @since 1.0.0
	 *
	 * @param array  $pieces An array of query pieces, including join, where, orderby, and more.
	 * @param string $type   The type of events to query (options: 'all', 'upcoming', 'past').
	 * @param string $order  The event order ('DESC' for descending or 'ASC' for ascending).
	 * @return array An array containing adjusted SQL clauses for the Event query.
	 */
	public function adjust_event_sql( array $pieces, string $type = 'all', string $order = 'DESC' ): array {
		global $wpdb;

		$defaults        = array(
			'where'    => '',
			'groupby'  => '',
			'join'     => '',
			'orderby'  => '',
			'distinct' => '',
			'fields'   => '',
			'limits'   => '',
		);
		$pieces          = array_merge( $defaults, $pieces );
		$table           = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$pieces['join'] .= ' LEFT JOIN ' . esc_sql( $table ) . ' ON ' . esc_sql( $wpdb->posts ) . '.ID='
						. esc_sql( $table ) . '.post_id';
		$order           = strtoupper( $order );

		if ( in_array( $order, array( 'DESC', 'ASC' ), true ) ) {
			$pieces['orderby'] = sprintf( esc_sql( $table ) . '.datetime_start_gmt %s', esc_sql( $order ) );
		}

		if ( 'all' !== $type ) {
			$current = gmdate( Event::DATETIME_FORMAT, time() );

			if ( 'upcoming' === $type ) {
				$pieces['where'] .= $wpdb->prepare( ' AND ' . esc_sql( $table ) . '.datetime_end_gmt >= %s', esc_sql( $current ) );
			} elseif ( 'past' === $type ) {
				$pieces['where'] .= $wpdb->prepare( ' AND ' . esc_sql( $table ) . '.datetime_end_gmt < %s', esc_sql( $current ) );
			}
		}

		return $pieces;
	}
}
