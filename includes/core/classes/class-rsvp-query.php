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
	 * Exclude RSVP comments from all comment queries.
	 *
	 * This method always removes RSVP comments from the query, regardless of what is passed in.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment_Query $query Current instance of WP_Comment_Query (passed by reference).
	 * @return void
	 */
	public function exclude_rsvp_from_comment_query( $query ) {
		$rsvp_type  = defined( 'GatherPress\\Core\\Rsvp::COMMENT_TYPE' ) ? \GatherPress\Core\Rsvp::COMMENT_TYPE : 'gatherpress_rsvp';
		$rsvp_types = array( 'rsvp', $rsvp_type ); // Handle both possible RSVP types.

		// If type__in is set, remove RSVP types.
		if ( ! empty( $query->query_vars['type__in'] ) ) {
			$query->query_vars['type__in'] = array_values( array_diff( (array) $query->query_vars['type__in'], $rsvp_types ) );
			// If type__in becomes empty after removing RSVP types, set it to default types.
			if ( empty( $query->query_vars['type__in'] ) ) {
				$query->query_vars['type__in'] = array( 'comment', 'pingback', 'trackback' );
			}
		}

		// If type is set, handle both array and string cases.
		if ( ! empty( $query->query_vars['type'] ) ) {
			if ( is_array( $query->query_vars['type'] ) ) {
				$query->query_vars['type'] = array_values( array_diff( $query->query_vars['type'], $rsvp_types ) );
				// If type becomes empty after removing RSVP types, set it to default types.
				if ( empty( $query->query_vars['type'] ) ) {
					$query->query_vars['type'] = array( 'comment', 'pingback', 'trackback' );
				}
			} elseif ( in_array( $query->query_vars['type'], $rsvp_types, true ) ) {
				$query->query_vars['type'] = ''; // Set to empty string for single RSVP type.
			}
		} elseif ( empty( $query->query_vars['type__in'] ) ) {
			// Only set default types if both type and type__in are empty.
			$query->query_vars['type'] = array( 'comment', 'pingback', 'trackback' );
		}

		// Always ensure RSVP types are in type__not_in.
		if ( empty( $query->query_vars['type__not_in'] ) ) {
			$query->query_vars['type__not_in'] = $rsvp_types;
		} else {
			$query->query_vars['type__not_in'] = array_unique( array_merge( (array) $query->query_vars['type__not_in'], $rsvp_types ) );
		}

		// Add filter to modify the SQL directly.
		add_filter(
			'comments_clauses',
			function ( $clauses ) use ( $rsvp_types ) {
				// Remove empty string from IN clause if it exists.
				$clauses['where'] = preg_replace(
					"/comment_type IN \([^)]*''[^)]*\)/",
					"comment_type IN ('comment', 'pingback', 'trackback')",
					$clauses['where']
				);
				// Ensure RSVP types are excluded.
				$rsvp_not_in = "'" . implode( "','", $rsvp_types ) . "'";
				if ( strpos( $clauses['where'], 'comment_type NOT IN' ) === false ) {
					$clauses['where'] .= " AND comment_type NOT IN ($rsvp_not_in)";
				} else {
					// Replace existing NOT IN clause to include all RSVP types.
					$clauses['where'] = preg_replace(
						'/comment_type NOT IN \([^)]*\)/',
						"comment_type NOT IN ($rsvp_not_in)",
						$clauses['where']
					);
				}
				return $clauses;
			},
			999
		);
	}
}
