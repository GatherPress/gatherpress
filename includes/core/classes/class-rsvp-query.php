<?php
/**
 * Manages queries for RSVPs.
 *
 * This file contains the RSVP_Query class which handles the querying and manipulation
 * of RSVP comments within the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Comment;
use WP_Comment_Query;
use WP_Tax_Query;

/**
 * Class Rsvp_Query
 *
 * Handles querying and manipulation of RSVP comments within the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */
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
		add_action( 'pre_get_comments', array( $this, 'exclude_rsvp_from_comment_query' ) );
		add_filter( 'comments_clauses', array( $this, 'taxonomy_query' ), 10, 2 );
	}

	/**
	 * Modify comment query clauses to include taxonomy query.
	 *
	 * This method adds the necessary SQL join and where clauses to a comment query
	 * based on a taxonomy query if one is present in the query variables.
	 *
	 * @since 1.0.0
	 *
	 * @param array            $clauses       The clauses for the query.
	 * @param WP_Comment_Query $comment_query Current instance of WP_Comment_Query (passed by reference).
	 * @return array Modified query clauses.
	 */
	public function taxonomy_query( array $clauses, WP_Comment_Query $comment_query ): array {
		global $wpdb;

		if ( ! empty( $comment_query->query_vars['tax_query'] ) ) {
			$comment_tax_query = new WP_Tax_Query( $comment_query->query_vars['tax_query'] );
			$pieces            = $comment_tax_query->get_sql( $wpdb->comments, 'comment_ID' );
			$clauses['join']  .= $pieces['join'];
			$clauses['where'] .= $pieces['where'];
		}

		return $clauses;
	}

	/**
	 * Retrieve a list of RSVP comments based on specified arguments.
	 *
	 * This method fetches RSVP comments by merging the provided arguments with default
	 * values specific to RSVPs. It ensures the count-only return is disabled and the
	 * RSVP comments are properly filtered.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Arguments for retrieving RSVPs.
	 * @return array List of RSVP comments.
	 */
	public function get_rsvps( array $args ): array {
		$args = array_merge(
			array(
				'type'   => Rsvp::COMMENT_TYPE,
				'status' => 'approve',
			),
			$args
		);

		// Never allow count-only return, we always want array.
		$args['count'] = false;

		remove_action( 'pre_get_comments', array( $this, 'exclude_rsvp_from_comment_query' ) );

		$rsvps = get_comments( $args );

		add_action( 'pre_get_comments', array( $this, 'exclude_rsvp_from_comment_query' ) );

		return (array) $rsvps;
	}

	/**
	 * Retrieve a single RSVP comment based on specified arguments.
	 *
	 * This method fetches a single RSVP comment by merging the provided arguments with default
	 * values specific to RSVPs. It ensures only one comment is returned.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Arguments for retrieving the RSVP.
	 * @return WP_Comment|null The RSVP comment or null if not found.
	 */
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

	/**
	 * Exclude RSVP comments from a query.
	 *
	 * This method modifies the comment query to exclude comments of the RSVP type. It
	 * ensures that RSVP comments are not included in the query results by adjusting the
	 * comment types in the query variables.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment_Query $query Current instance of WP_Comment_Query (passed by reference).
	 * @return void
	 */
	public function exclude_rsvp_from_comment_query( WP_Comment_Query $query ) {

		$current_comment_types = $query->query_vars['type'];

		// Ensure comment type is not empty.
		if ( ! empty( $current_comment_types ) ) {
			if ( is_array( $current_comment_types ) ) {
				// Remove the specific comment type from the array.
				$current_comment_types = array_diff( $current_comment_types, array( Rsvp::COMMENT_TYPE ) );
			} elseif ( Rsvp::COMMENT_TYPE === $current_comment_types ) {
				// If the only type is the one to exclude, set it to empty.
				$current_comment_types = '';
			}
		} else {
			// If no specific type is set, make sure the one to exclude is not included.
			$current_comment_types = array( 'comment', 'pingback', 'trackback' ); // Default types.
			$current_comment_types = array_diff( $current_comment_types, array( Rsvp::COMMENT_TYPE ) );
		}

		// Update the query vars with the modified comment types.
		$query->query_vars['type'] = $current_comment_types;
	}
}
