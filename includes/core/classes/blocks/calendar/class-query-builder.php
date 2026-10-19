<?php
/**
 * Query arguments for the Calendar blocks.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use WP_Block;

/**
 * Class Query_Builder.
 *
 * Constructs WP_Query arguments from Query Loop context.
 * Adds date filtering and calendar-specific parameters.
 *
 * @since TBD
 *
 * @phpstan-import-type DateRange from Date_Calculator
 */
final class Query_Builder {

	/**
	 * Highest page number a calendar accepts from the URL.
	 *
	 * Date_Calculator multiplies the page by unitCount. A very large page
	 * overflows that integer and causes a fatal TypeError.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_PAGE = 10000;

	/**
	 * Retrieves the maximum number of posts to query for calendar display.
	 *
	 * This filter allows sites to adapt the number of posts fetched
	 * for calendar rendering to match their specific performance and event volume needs.
	 *
	 * @since TBD
	 *
	 * @return int Number of posts to query.
	 */
	public static function get_posts_per_page(): int {
		/**
		 * Filters the maximum number of posts queried for calendar display.
		 *
		 * Defaults to 500. Values below 1 become 1.
		 *
		 * @since TBD
		 *
		 * @param int $posts_per_page Number of posts to query.
		 */
		$posts_per_page = (int) apply_filters( 'gatherpress_calendar_posts_per_page', 500 );

		return max( 1, $posts_per_page );
	}

	/**
	 * Get the requested page number of a core/query block.
	 *
	 * Reads the same URL parameter, and casts it the same way, as core's Query
	 * Pagination blocks: `query-{$query_id}-page` when the Query block has a
	 * queryId (0 included), and `query-page` when it has none.
	 *
	 * @since TBD
	 *
	 * @param mixed $query_id The Query block's queryId, or null when it has none.
	 *
	 * @return int Page number, from 1 to MAX_PAGE.
	 */
	public static function get_requested_page( $query_id ): int {
		$page_key = is_numeric( $query_id ) ? 'query-' . (int) $query_id . '-page' : 'query-page';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $page_key ] ) || ! is_scalar( $_GET[ $page_key ] ) ) {
			return 1;
		}

		return min( self::MAX_PAGE, max( 1, (int) $_GET[ $page_key ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Checks whether a given post type (or list of post types) supports event dates.
	 *
	 * When an array is provided, returns true only if all post types support 'gatherpress-event-date'.
	 *
	 * @since TBD
	 *
	 * @param mixed $post_type Post type slug or array of slugs.
	 *
	 * @return bool True if all post types support 'gatherpress-event-date'.
	 */
	public static function is_event_post_type( $post_type ): bool {
		if ( is_string( $post_type ) ) {
			return post_type_supports( $post_type, Event\Event::SUPPORT );
		}

		if ( ! is_array( $post_type ) || empty( $post_type ) ) {
			return false;
		}

		foreach ( $post_type as $type ) {
			if ( ! is_string( $type ) || ! post_type_supports( $type, Event\Event::SUPPORT ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Add scheduled posts to the query arguments.
	 *
	 * Core sets no post_status here, so WP_Query shows published posts, plus
	 * private ones to users who may read them. Listing statuses by hand drops
	 * that private check, so 'perm' => 'readable' puts it back. The check is
	 * done per post type here because, with more than one post type, WP_Query
	 * tests a 'read_private_multiple_post_types' capability that no role has.
	 * A user who can read private posts of only some of the types sees just
	 * their own private posts, which errs on the side of showing less.
	 *
	 * 'private' is added only for logged-in users, as core does. With
	 * 'perm' => 'readable', WP_Query matches private posts that have
	 * post_author 0 (for example from an importer or WP-CLI) to the
	 * logged-out user ID 0, which would show them to every viewer.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query_args WP_Query arguments.
	 *
	 * @return array<string, mixed> WP_Query arguments with scheduled posts included.
	 */
	private static function include_scheduled_posts( array $query_args ): array {
		$query_args['post_status'] = is_user_logged_in()
			? array( 'publish', 'future', 'private' )
			: array( 'publish', 'future' );

		foreach ( (array) ( $query_args['post_type'] ?? 'post' ) as $type ) {
			$type_object  = is_string( $type ) ? get_post_type_object( $type ) : null;
			$read_private = $type_object ? $type_object->cap->read_private_posts : null;

			if ( ! is_string( $read_private ) || ! current_user_can( $read_private ) ) {
				$query_args['perm'] = 'readable';
				break;
			}
		}

		return $query_args;
	}

	/**
	 * Build query arguments from block context and resolved date range.
	 *
	 * @since TBD
	 *
	 * @param WP_Block             $block      Block instance.
	 * @param array<string, mixed> $date_range Resolved date range array.
	 * @phpstan-param DateRange $date_range
	 *
	 * @return array<string, mixed> WP_Query arguments.
	 */
	public static function build_query_args( WP_Block $block, array $date_range ): array {
		$page       = self::get_requested_page( $block->context['queryId'] ?? null );
		$query_args = build_query_vars_from_query_block( $block, $page );

		// Set calendar query markers and range parameters.
		$query_args[ Event\Query::EVENT_QUERY_PARAM ]               = 'all';
		$query_args[ Query_Integration::CALENDAR_QUERY_PARAM ]      = true;
		$query_args[ Query_Integration::CALENDAR_QUERY_VIEW_TYPE ]  = $date_range['view_type'];
		$query_args[ Query_Integration::CALENDAR_QUERY_START_DATE ] = $date_range['start_date'];
		$query_args[ Query_Integration::CALENDAR_QUERY_END_DATE ]   = $date_range['end_date'];
		$query_args['posts_per_page']                               = self::get_posts_per_page();

		if ( true === ( $block->attributes['showScheduled'] ?? false ) ) {
			$query_args = self::include_scheduled_posts( $query_args );
		}

		// Inclusive date range prevents boundary clipping across weeks and multi-month periods.
		$date_clause = array(
			'after'     => $date_range['start_date'] . ' 00:00:00',
			'before'    => $date_range['end_date'] . ' 23:59:59',
			'inclusive' => true,
		);

		if ( self::is_event_post_type( $query_args['post_type'] ?? 'post' ) ) {
			$date_clause['column'] = 'datetime_start';
		}

		$query_args['date_query'] = array( $date_clause );

		// A calendar pages by date range, not by posts, so the Query block's offset does not apply.
		unset( $query_args['offset'], $query_args['include_unfinished'] );

		return $query_args;
	}
}
