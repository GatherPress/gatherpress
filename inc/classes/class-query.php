<?php
/**
 * Class is responsible for all query related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

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
//		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'posts_clauses', array( $this, 'order_upcoming_events' ) );
		add_filter( 'posts_clauses', array( $this, 'admin_order_events' ) );
	}

	/**
	 * Get upcoming events.
	 *
	 * @return \WP_Query
	 */
	public function get_upcoming_events() : \WP_Query {
		remove_filter( 'posts_clauses', array( $this, 'order_past_events' ) );
		add_filter( 'posts_clauses', array( $this, 'order_upcoming_events' ) );

		$args = array(
			'post_type'      => Event::POST_TYPE,
			'no_found_rows'  => true,
			'posts_per_page' => 5,
		);

		$query = new \WP_Query( $args );

		remove_filter( 'posts_clauses', array( $this, 'order_upcoming_events' ) );
		add_filter( 'posts_clauses', array( $this, 'order_past_events' ) );

		return $query;
	}

	/**
	 * Set post type query to event on homepage.
	 *
	 * @param \WP_Query $query An instance of \WP_Query.
	 */
	public function pre_get_posts( $query ) {
		if (
			( $query->is_home() || $query->is_front_page() )
			&& $query->is_main_query()
		) {
			$query->set( 'post_type', Event::POST_TYPE );
		}
	}

	/**
	 * Order events by start datetime for ones that happened in past.
	 *
	 * @todo this is how we will handle past events. Upcoming/current events need to have adjusted orderby.
	 *
	 * @param array $pieces Includes pieces of the query like join, where, orderby, et al.
	 *
	 * @return array
	 */
	public function order_past_events( array $pieces ) : array {
		if ( ! is_archive() && ! is_home() ) {
			return $pieces;
		}

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
	public function order_upcoming_events( array $pieces ) : array {
		if ( ! is_archive() && ! is_home() ) {
			return $pieces;
		}

		return Event::adjust_sql( $pieces, 'future', 'ASC' );
	}

}
