<?php
/**
 * Manages queries for RSVPs.
 *
 * This file contains the RSVP_Query class which handles the querying and manipulation
 * of RSVP comments within the GatherPress plugin.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.30.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Comment;
use WP_Comment_Query;
use WP_REST_Response;
use WP_Tax_Query;

/**
 * Class Query.
 *
 * Handles querying and manipulation of RSVP comments within the GatherPress plugin.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.34.0
 */
final class Query {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cache key for storing comment types.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const COMMENT_TYPES_CACHE_KEY = 'gatherpress_all_comment_types';

	/**
	 * Cache expiration time (24 hours).
	 *
	 * @since 0.34.0
	 * @var int
	 */
	const CACHE_EXPIRATION = DAY_IN_SECONDS;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'pre_get_comments', array( $this, 'exclude_rsvp_from_comment_query' ) );
		add_filter( 'comments_clauses', array( $this, 'taxonomy_query' ), 10, 2 );
		add_action( 'wp_insert_comment', array( $this, 'maybe_invalidate_comment_types_cache' ), 10, 2 );
		add_filter( 'get_comment', array( $this, 'prepare_rsvp_comment' ) );
		add_filter( 'rest_prepare_comment', array( $this, 'mask_anonymous_rsvp_rest_author' ), 10, 2 );
	}

	/**
	 * Modify comment query clauses to include taxonomy query.
	 *
	 * This method adds the necessary SQL join and where clauses to a comment query
	 * based on a taxonomy query if one is present in the query variables.
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, string> $clauses       The clauses for the query.
	 * @param WP_Comment_Query      $comment_query Current instance of WP_Comment_Query (passed by reference).
	 *
	 * @return array<string, string> Modified query clauses.
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
	 * @since 0.34.0
	 *
	 * @param array<string, mixed> $args Arguments for retrieving RSVPs.
	 *
	 * @return mixed Array of RSVP comments or integer count when count parameter is true.
	 */
	public function get_rsvps( array $args ): mixed {
		$args['type']         = Rsvp::COMMENT_TYPE;
		$args['type__in']     = array();
		$args['type__not_in'] = array();

		// Default to every RSVP-supporting post type; callers may narrow
		// this down, like the per-post-type RSVPs admin pages do (#1849).
		if ( empty( $args['post_type'] ) ) {
			$args['post_type'] = array_values( get_post_types_by_support( 'gatherpress-rsvp' ) );
		}

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
	 * @since 0.34.0
	 *
	 * @param array<string, mixed> $args Arguments for retrieving the RSVP.
	 *
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
	 * @since 0.34.0
	 *
	 * @return string[] Array of all comment types in the database.
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
	 * @since 0.34.0
	 *
	 * @param int        $id      The comment ID.
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
	 * @since 0.34.0
	 *
	 * @param WP_Comment_Query $query Current instance of WP_Comment_Query (passed by reference).
	 *
	 * @return void
	 */
	public function exclude_rsvp_from_comment_query( WP_Comment_Query $query ): void {
		/**
		 * Filters whether RSVP comments should be excluded from a comment query.
		 *
		 * RSVPs are stored as WordPress comments with the `gatherpress_rsvp`
		 * comment_type and excluded from generic comment queries by default so
		 * they don't leak into normal comment lists, feeds, or third-party UIs
		 * that aren't RSVP-aware. Integrations that want the RSVP type to flow
		 * through (federation plugins that filter comment types themselves,
		 * admin reports that intentionally include RSVPs, custom moderation
		 * dashboards) can return false here to skip the default exclusion for
		 * a given query. The filter receives the live `WP_Comment_Query` so
		 * the opt-out can be scoped — e.g. only when the caller's `type__in`
		 * names types the integration owns — rather than disabled globally.
		 *
		 * @since 0.30.0
		 *
		 * @param bool             $exclude True to apply the RSVP exclusion, false to skip it.
		 * @param WP_Comment_Query $query   The current comment query (passed by reference upstream).
		 */
		if ( ! apply_filters( 'gatherpress_rsvp_comment_query_exclusion', true, $query ) ) {
			return;
		}

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

	/**
	 * Prepare an RSVP comment for whoever is reading it.
	 *
	 * Every reader of a comment goes through `get_comment()`, including the
	 * REST comments route and the core avatar and comment-author blocks, and
	 * most of them read the columns straight off the object rather than through
	 * a display function. Both of the adjustments an RSVP needs therefore
	 * belong here: the responder's identity is withheld when they asked to stay
	 * anonymous, and their profile URL is resolved from their account, since
	 * responses saved through the store leave that column empty.
	 *
	 * @since 0.35.1
	 *
	 * @param WP_Comment|mixed $comment The comment being read.
	 *
	 * @return WP_Comment|mixed The comment, prepared for the current reader.
	 */
	public function prepare_rsvp_comment( $comment ) {
		if ( ! $comment instanceof WP_Comment || Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return $comment;
		}

		$manages_rsvps = current_user_can( Rsvp::CAPABILITY );
		$withhold      = get_comment_meta( (int) $comment->comment_ID, Rsvp::ANONYMOUS_META_KEY, true )
			&& ! $manages_rsvps;

		// Responses saved before the store stopped writing an address into the
		// display-name column still carry one, and every reader takes the name
		// from there. The equality marks that write, which filled both columns
		// with the same value; a name that only looks like an address belongs
		// to an account registered with one.
		$withhold_address = ! $withhold
			&& ! $manages_rsvps
			&& is_email( $comment->comment_author )
			&& $comment->comment_author === $comment->comment_author_email;

		// Only fill a URL the store never wrote, so a response identified by
		// its URL keeps the one it was saved with.
		$resolve = ! $withhold
			&& '' === $comment->comment_author_url
			&& intval( $comment->user_id );

		if ( ! $withhold && ! $resolve && ! $withhold_address ) {
			return $comment;
		}

		// Adjusted on a clone so the cached comment keeps its own values for
		// readers that are allowed to see them.
		$prepared = clone $comment;

		if ( $withhold ) {
			$prepared->comment_author       = __( 'Anonymous', 'gatherpress' );
			$prepared->comment_author_email = '';
			$prepared->comment_author_url   = '';
		} else {
			// Independent conditions, so a response that matches both gets
			// both. Branching them apart would drop the URL for any responder
			// whose stored name is an address.
			if ( $withhold_address ) {
				$prepared->comment_author = __( 'Attendee', 'gatherpress' );
			}

			if ( $resolve ) {
				$prepared->comment_author_url = (string) get_author_posts_url( (int) $comment->user_id );
			}
		}

		return $prepared;
	}

	/**
	 * Withhold the responder's user ID from the comments REST response.
	 *
	 * The masked comment keeps its real `user_id` because RSVP lookups depend
	 * on it, and those run for the responder themselves. It only needs to be
	 * withheld where it reaches the public, and the `author` field is the one
	 * place that publishes it — left intact it resolves back to the responder
	 * through the users endpoint.
	 *
	 * @since 0.35.1
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Comment       $comment  The comment being returned.
	 *
	 * @return WP_REST_Response The response, with the author withheld when required.
	 */
	public function mask_anonymous_rsvp_rest_author( $response, $comment ) {
		$data = $response->get_data();

		// Re-read the responder's own state rather than the masked output, so
		// this does not depend on what the mask above happens to write.
		if (
			isset( $data['author'] )
			&& Rsvp::COMMENT_TYPE === $comment->comment_type
			&& ! current_user_can( Rsvp::CAPABILITY )
			&& get_comment_meta( (int) $comment->comment_ID, Rsvp::ANONYMOUS_META_KEY, true )
		) {
			$data['author'] = 0;
			$response->set_data( $data );
		}

		return $response;
	}
}
