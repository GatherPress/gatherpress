<?php

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_comment;
use WP_Comment_Query;
use WP_Tax_Query;

class Rsvp_Query {
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
		add_filter( 'pre_get_comments', array( $this, 'exclude_rsvp_from_query' ) );
		add_filter( 'comments_clauses', array( $this, 'taxonomy_query' ), 10, 2 );
	}

	public function taxonomy_query( $clauses, $object ) {
		global $wpdb;

		if ( ! empty( $object->query_vars['tax_query'] ) ) {
			$object->tax_query = new WP_Tax_Query( $object->query_vars['tax_query'] );
			$pieces            = $object->tax_query->get_sql( $wpdb->comments, 'comment_ID' );
			$clauses['join']  .= $pieces['join'];
			$clauses['where'] .= $pieces['where'];
		}

		return $clauses;
	}

	public function get_rsvps( array $args ): array {
		$args = array_merge(
			array(
				'type'   => RSVP::COMMENT_TYPE,
				'status' => 'approve',
			),
			$args
		);

		// Never allow count-only return, we always want array.
		$args['count'] = false;

		remove_filter( 'pre_get_comments', array( $this, 'exclude_rsvp_from_query' ) );

		$rsvps = get_comments( $args );

		add_filter( 'pre_get_comments', array( $this, 'exclude_rsvp_from_query' ) );

		return (array) $rsvps;
	}

	public function get_rsvp( array $args ): ?WP_Comment {
		$args = array_merge(
			array(
				'number' => 1,
			),
			$args
		);
		$rsvp = $this->get_rsvps( $args );

		if ( empty( $rsvp ) ) {
			return null;
		}

		return $rsvp[0];
	}

	public function exclude_rsvp_from_query( $query ) {
		if ( ! $query instanceof WP_Comment_Query ) {
			return;
		}

		// Get the current comment types if any
		$current_comment_types = $query->query_vars['type'];

		// Ensure comment type is not empty
		if ( ! empty( $current_comment_types ) ) {
			if ( is_array( $current_comment_types ) ) {
				// Remove the specific comment type from the array
				$current_comment_types = array_diff( $current_comment_types, array( RSVP::COMMENT_TYPE ) );
			} elseif ( $current_comment_types === RSVP::COMMENT_TYPE ) {
				// If the only type is the one to exclude, set it to empty
				$current_comment_types = '';
			}
		} else {
			// If no specific type is set, make sure the one to exclude is not included
			$current_comment_types = array( 'comment', 'pingback', 'trackback' ); // Default types
			$current_comment_types = array_diff( $current_comment_types, array( RSVP::COMMENT_TYPE ) );
		}

		// Update the query vars with the modified comment types
		$query->query_vars['type'] = $current_comment_types;
	}
}
