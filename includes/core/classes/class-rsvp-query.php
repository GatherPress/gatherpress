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
	 * Cache key for storing comment types.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const COMMENT_TYPES_CACHE_KEY = 'gatherpress_all_comment_types';

	/**
	 * Cache expiration time (24 hours).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

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
		add_action( 'wp_insert_comment', array( $this, 'maybe_invalidate_comment_types_cache' ), 10, 2 );
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
	 * Retrieve RSVP comments or count based on specified arguments.
	 *
	 * This method fetches RSVP comments by merging the provided arguments with default
	 * values specific to RSVPs. Can return either an array of comments or integer count
	 * based on the 'count' parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Arguments for retrieving RSVPs.
	 * @return mixed Array of RSVP comments or integer count when count parameter is true.
	 */
	public function get_rsvps( array $args ) {
		$args['type']         = Rsvp::COMMENT_TYPE;
		$args['post_type']    = Event::POST_TYPE;
		$args['type__in']     = array();
		$args['type__not_in'] = array();

		remove_action( 'pre_get_comments', array( $this, 'exclude_rsvp_from_comment_query' ) );

		$rsvps = get_comments( $args );

		add_action( 'pre_get_comments', array( $this, 'exclude_rsvp_from_comment_query' ) );

		if ( ! empty( $args['count'] ) ) {
			return (int) $rsvps;
		}

		return (array) $rsvps;
	}

	/**
	 * Retrieve a single RSVP comment based on specified arguments.
	 *
	 * This method fetches a single RSVP comment by setting the number limit to 1
	 * and calling get_rsvps(). Returns the first RSVP found or null if none exist.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Arguments for retrieving the RSVP.
	 * @return WP_Comment|null The RSVP comment or null if not found.
	 */
	public function get_rsvp( array $args ): ?WP_Comment {
		$args['number'] = 1;
		$args['count']  = false;

		$rsvp = $this->get_rsvps( $args );

		if ( empty( $rsvp ) ) {
			return null;
		}

		return $rsvp[0];
	}

	/**
	 * Get all comment types registered in the database.
	 *
	 * This method queries the database for all distinct comment types
	 * and caches the result for performance.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of all comment types in the database.
	 */
	protected function get_all_comment_types(): array {
		$default_types = array( 'comment', 'pingback', 'trackback' );
		$types         = get_transient( self::COMMENT_TYPES_CACHE_KEY );

		if ( false === $types ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$types = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT comment_type FROM %i WHERE comment_type != %s',
					$wpdb->comments,
					''
				)
			);

			// If no types found or database error, use WordPress defaults.
			if ( empty( $types ) || ! is_array( $types ) ) {
				$types = $default_types;
			}

			// Cache for 24 hours.
			set_transient( self::COMMENT_TYPES_CACHE_KEY, $types, self::CACHE_EXPIRATION );
		}

		// Ensure we always return an array.
		return is_array( $types ) ? $types : $default_types;
	}

	/**
	 * Invalidate comment types cache when a new comment type is added.
	 *
	 * This method checks if a newly inserted comment has a type that's not
	 * already in our cached types, and if so, invalidates the cache.
	 *
	 * @since 1.0.0
	 *
	 * @param int        $id      The comment ID.
	 * @param WP_Comment $comment The comment object.
	 * @return void
	 */
	public function maybe_invalidate_comment_types_cache( int $id, WP_Comment $comment ): void {
		// Skip if it's an empty comment type (regular comment).
		if ( empty( $comment->comment_type ) ) {
			return;
		}

		$cached_types = get_transient( self::COMMENT_TYPES_CACHE_KEY );

		// If cache exists and this type isn't in it, invalidate the cache.
		if ( false !== $cached_types && ! in_array( $comment->comment_type, $cached_types, true ) ) {
			delete_transient( self::COMMENT_TYPES_CACHE_KEY );
		}
	}

	/**
	 * Exclude RSVP comments from a query.
	 *
	 * This method modifies the comment query to exclude comments of the RSVP type. It
	 * ensures that RSVP comments are not included in the query results by adjusting the
	 * comment types in the query variables.
	 *
	 * Note: The comment_type field is not currently indexed in WordPress core,
	 * which may impact query performance. See https://core.trac.wordpress.org/ticket/59488
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment_Query $query Current instance of WP_Comment_Query (passed by reference).
	 * @return void
	 */
	public function exclude_rsvp_from_comment_query( WP_Comment_Query $query ) {
		// Process 'type' query var.
		$current_comment_types = $query->query_vars['type'];

		if ( ! empty( $current_comment_types ) ) {
			if ( is_array( $current_comment_types ) ) {
				$current_comment_types = array_values(
					array_diff( $current_comment_types, array( Rsvp::COMMENT_TYPE ) )
				);
			} elseif ( Rsvp::COMMENT_TYPE === $current_comment_types ) {
				$current_comment_types = '';
			}
		} else {
			// Get all registered comment types from the database (cached).
			$current_comment_types = $this->get_all_comment_types();
			$current_comment_types = array_values( array_diff( $current_comment_types, array( Rsvp::COMMENT_TYPE ) ) );
		}

		$query->query_vars['type'] = $current_comment_types;

		// Process 'type__in' query var.
		$current_comment_types_in = $query->query_vars['type__in'];

		if ( ! empty( $current_comment_types_in ) ) {
			if ( is_array( $current_comment_types_in ) ) {
				$current_comment_types_in = array_values(
					array_diff( $current_comment_types_in, array( Rsvp::COMMENT_TYPE ) )
				);
			} elseif ( Rsvp::COMMENT_TYPE === $current_comment_types_in ) {
				$current_comment_types_in = '';
			}

			$query->query_vars['type__in'] = $current_comment_types_in;
		}
	}
}
