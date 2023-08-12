<?php
/**
 * Class is responsible for all query related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) { // @codeCoverageIgnore
	exit; // @codeCoverageIgnore
}

/**
 * Class Query.
 */
class Query {

	use Singleton;

	/**
	 * Query constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'posts_clauses', array( $this, 'admin_order_events' ) );
	}

	/**
	 * Get upcoming events.
	 *
	 * @param int $number Maximum number of events to display.
	 *
	 * @return \WP_Query
	 */
	public function get_upcoming_events( int $number = 5 ): \WP_Query {
		return $this->get_events_list( 'upcoming', $number );
	}

	/**
	 * Get past events.
	 *
	 * @param int $number Maximum number of events to display.
	 *
	 * @return \WP_Query
	 */
	public function get_past_events( int $number = 5 ): \WP_Query {
		return $this->get_events_list( 'past', $number );
	}

	/**
	 * Query that returns a list of events.
	 *
	 * @param string $event_list_type  Type of event list: upcoming or past.
	 * @param int    $number           Maximum number of events.
	 * @param array  $topics           Array of topic slugs.
	 * @param array  $venues           Array of venue slugs.
	 *
	 * @return \WP_Query
	 */
	public function get_events_list( string $event_list_type = '', int $number = 5, array $topics = array(), array $venues = array() ): \WP_Query {
		$args = array(
			'post_type'       => Event::POST_TYPE,
			'fields'          => 'ids',
			'no_found_rows'   => true,
			'posts_per_page'  => $number,
			'gp_events_query' => $event_list_type,
		);
		if ( ! empty( $venues ) || ! empty( $topics ) ) {
			$args['tax_query'] = array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'relation' => 'OR',
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
				),
			);
		}

		return new \WP_Query( $args );
	}

	/**
	 * Set event query and order adjustments before query is made.
	 *
	 * @param \WP_Query $query An instance of \WP_Query.
	 */
	public function pre_get_posts( $query ) {
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
				remove_filter( 'posts_clauses', array( $this, 'order_past_events' ) );
				add_filter( 'posts_clauses', array( $this, 'order_upcoming_events' ) );
				break;
			case 'past':
				add_filter( 'posts_clauses', array( $this, 'order_past_events' ) );
				remove_filter( 'posts_clauses', array( $this, 'order_upcoming_events' ) );
				break;
			default:
				remove_filter( 'posts_clauses', array( $this, 'order_past_events' ) );
				remove_filter( 'posts_clauses', array( $this, 'order_upcoming_events' ) );
		}
	}

	/**
	 * Order events by start datetime for ones that happened in past.
	 *
	 * @param array $pieces Includes pieces of the query like join, where, orderby, et al.
	 *
	 * @return array
	 */
	public function order_past_events( array $pieces ): array {
		return Event::adjust_sql( $pieces, 'past' );
	}

	/**
	 * Set sorting for Event admin.
	 *
	 * @param array $pieces Includes pieces of the query like join, where, orderby, et al.
	 *
	 * @return array
	 */
	public function admin_order_events( array $pieces ): array {
		if ( ! is_admin() ) {
			return $pieces;
		}

		global $wp_query;

		if ( 'datetime' === $wp_query->get( 'orderby' ) ) {
			$pieces = Event::adjust_sql( $pieces, 'all', $wp_query->get( 'order' ) );
		}

		return $pieces;
	}

	/**
	 * Order events by start datetime for ones that are upcoming.
	 *
	 * @param array $pieces Includes pieces of the query like join, where, orderby, et al.
	 *
	 * @return array
	 */
	public function order_upcoming_events( array $pieces ): array {
		return Event::adjust_sql( $pieces, 'upcoming', 'ASC' );
	}

}
