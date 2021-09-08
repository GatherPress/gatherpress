<?php
/**
 * Class is responsible for all query related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use \GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		// @todo this will be handled by blocks
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'posts_clauses', array( $this, 'admin_order_events' ) );
	}

	/**
	 * Get future events.
	 *
	 * @param int $number Maximum number of events to display.
	 *
	 * @return \WP_Query
	 */
	public function get_future_events( int $number = 5 ) : \WP_Query {
		$args = array(
			'post_type'       => Event::POST_TYPE,
			'no_found_rows'   => true,
			'posts_per_page'  => $number,
			'gp_events_query' => 'future',
		);

		return new \WP_Query( $args );
	}

	/**
	 * Get past events.
	 *
	 * @param int $number Maximum number of events to display.
	 *
	 * @return \WP_Query
	 */
	public function get_past_events( int $number = 5 ) : \WP_Query {
		$args = array(
			'post_type'       => Event::POST_TYPE,
			'no_found_rows'   => true,
			'posts_per_page'  => $number,
			'gp_events_query' => 'past',
		);

		return new \WP_Query( $args );
	}

	/**
	 * Set event query and order adjustments before query is made.
	 *
	 * @param \WP_Query $query An instance of \WP_Query.
	 */
	public function pre_get_posts( $query ) {
		$events_query = $query->get( 'gp_events_query' );

		switch ( $events_query ) {
			case 'future':
				remove_filter( 'posts_clauses', array( $this, 'order_past_events' ) );
				add_filter( 'posts_clauses', array( $this, 'order_future_events' ) );
				break;
			case 'past':
				add_filter( 'posts_clauses', array( $this, 'order_past_events' ) );
				remove_filter( 'posts_clauses', array( $this, 'order_future_events' ) );
				break;
			default:
				remove_filter( 'posts_clauses', array( $this, 'order_past_events' ) );
				remove_filter( 'posts_clauses', array( $this, 'order_future_events' ) );
		}
	}

	/**
	 * Order events by start datetime for ones that happened in past.
	 *
	 * @param array $pieces Includes pieces of the query like join, where, orderby, et al.
	 *
	 * @return array
	 */
	public function order_past_events( array $pieces ) : array {
		return Event::adjust_sql( $pieces, 'past' );
	}

	/**
	 * Set sorting for Event admin.
	 *
	 * @param array $pieces Includes pieces of the query like join, where, orderby, et al.
	 *
	 * @return array
	 */
	public function admin_order_events( array $pieces ) : array {
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
	public function order_future_events( array $pieces ) : array {
		return Event::adjust_sql( $pieces, 'future', 'ASC' );
	}

}
