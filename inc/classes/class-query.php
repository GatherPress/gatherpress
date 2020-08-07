<?php

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Query {

	use Singleton;

	/**
	 * Query constructor.
	 */
	protected function __construct() {

		$this->_setup_hooks();

	}

	/**
	 * Setup hooks.
	 */
	protected function _setup_hooks() : void {

		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ] );
		add_filter( 'posts_clauses', [ $this, 'order_past_events' ] );
		add_filter( 'posts_clauses', [ $this, 'admin_order_events' ] );

	}

	public function get_upcoming_events() : \WP_Query {

		remove_filter( 'posts_clauses', [ $this, 'order_past_events' ] );
		add_filter( 'posts_clauses', [ $this, 'order_upcoming_events' ] );

		$args = [
			'post_type'      => Event::POST_TYPE,
			'no_found_rows'  => true,
			'posts_per_page' => 5,
		];

		$query = new \WP_Query( $args );

		remove_filter( 'posts_clauses', [ $this, 'order_upcoming_events' ] );
		add_filter( 'posts_clauses', [ $this, 'order_past_events' ] );

		return $query;

	}

	/**
	 * Set post type query to event on homepage.
	 *
	 * @param $query
	 */
	public function pre_get_posts( $query ) : void {

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
	 * @param array $pieces
	 *
	 * @return array
	 */
	public function order_past_events( array $pieces ) : array {

		if ( ! is_archive() && ! is_home() ) {
			return $pieces;
		}

		return Event::get_instance()->adjust_sql( $pieces, 'past' );

	}

	/**
	 * Set sorting for Event admin.
	 *
	 * @param array $pieces
	 *
	 * @return array
	 */
	public function admin_order_events( array $pieces ) : array {

		if ( ! is_admin() ) {
			return $pieces;
		}

		global $wp_query;

		if ( 'datetime' === $wp_query->get( 'orderby' ) ) {
			$pieces = Event::get_instance()->adjust_sql( $pieces, 'all', $wp_query->get( 'order' ) );
		}

		return $pieces;

	}

	/**
	 * Order events by start datetime for ones that are upcoming.
	 *
	 * @param array $pieces
	 *
	 * @return array
	 */
	public function order_upcoming_events( array $pieces ) : array {

		if ( ! is_archive() && ! is_home() ) {
			return $pieces;
		}

		return Event::get_instance()->adjust_sql( $pieces, 'future', 'ASC' );

	}

}

// EOF
