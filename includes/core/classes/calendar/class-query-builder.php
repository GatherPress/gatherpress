<?php
/**
 * The query of a calendar.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Utility;
use WP_Block;
use WP_Post;
use WP_Query;

/**
 * Class Query_Builder.
 *
 * Builds the WP_Query arguments of a calendar from the Query Loop context and the date range
 * of the calendar, and queries its posts.
 *
 * @since TBD
 */
final class Query_Builder {

	/**
	 * Query parameter that marks a query as a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_PARAM = 'gatherpress_calendar_query';

	/**
	 * Query parameter for the view type of a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_VIEW_TYPE = 'gatherpress_calendar_view_type';

	/**
	 * Query parameter for the first day of a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_START_DATE = 'gatherpress_calendar_start_date';

	/**
	 * Query parameter for the last day of a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_END_DATE = 'gatherpress_calendar_end_date';

	/**
	 * Query parameter for the heading of a calendar query.
	 *
	 * @since TBD
	 * @var string
	 */
	const CALENDAR_QUERY_HEADING = 'gatherpress_calendar_heading';

	/**
	 * Highest page number a calendar accepts from the URL.
	 *
	 * Date_Range multiplies the page by the unit count. A very large page
	 * overflows that integer and causes a fatal TypeError.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_PAGE = 10000;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param WP_Block   $block      The calendar block, with the Query Loop context.
	 * @param Date_Range $date_range The date range of the calendar.
	 */
	public function __construct(
		private readonly WP_Block $block,
		private readonly Date_Range $date_range
	) {}

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
		 * Filters how many posts one calendar view can show, to fit a site with more posts in one view.
		 *
		 * Defaults to 500. A calendar queries all posts of its date range in one query, so posts
		 * after this number do not show. Lower it to make the query smaller. Values below 1 become 1.
		 *
		 * @since TBD
		 *
		 * @param int $posts_per_page Number of posts to query.
		 *
		 * @return int Number of posts to query.
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
		$page     = (int) Utility::get_http_input( INPUT_GET, $page_key );

		return min( self::MAX_PAGE, max( 1, $page ) );
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
		$post_types = is_string( $post_type ) ? array( $post_type ) : $post_type;

		if ( ! is_array( $post_types ) || empty( $post_types ) ) {
			return false;
		}

		foreach ( $post_types as $type ) {
			if ( ! is_string( $type ) || ! post_type_supports( $type, Event::SUPPORT ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Builds the WP_Query arguments of the calendar.
	 *
	 * @since TBD
	 *
	 * @return array<string, mixed> WP_Query arguments.
	 */
	public function get_args(): array {
		$page       = self::get_requested_page( $this->block->context['queryId'] ?? null );
		$query_args = build_query_vars_from_query_block( $this->block, $page );

		// Set calendar query markers and range parameters.
		$query_args[ Event\Query::EVENT_QUERY_PARAM ]  = 'all';
		$query_args[ self::CALENDAR_QUERY_PARAM ]      = true;
		$query_args[ self::CALENDAR_QUERY_VIEW_TYPE ]  = $this->date_range->view_type;
		$query_args[ self::CALENDAR_QUERY_START_DATE ] = $this->date_range->start_date;
		$query_args[ self::CALENDAR_QUERY_END_DATE ]   = $this->date_range->end_date;
		$query_args['posts_per_page']                  = self::get_posts_per_page();

		if ( true === ( $this->block->attributes['showScheduled'] ?? false ) ) {
			$query_args = $this->include_scheduled_posts( $query_args );
		}

		// Inclusive date range prevents boundary clipping across weeks and multi-month periods.
		$date_clause = array(
			'after'     => $this->date_range->start_date . ' 00:00:00',
			'before'    => $this->date_range->end_date . ' 23:59:59',
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

	/**
	 * Queries the IDs of the posts of the calendar.
	 *
	 * @since TBD
	 *
	 * @return list<int> Post IDs.
	 */
	public function get_post_ids(): array {
		$query_args = $this->get_args();

		// The calendar needs only the IDs, and pages by date range, so it needs no post count.
		$query_args['fields']        = 'ids';
		$query_args['no_found_rows'] = true;
		$query                       = new WP_Query( $query_args );

		$post_ids = array_values(
			array_map(
				static function ( $post ): int {
					return $post instanceof WP_Post ? $post->ID : (int) $post;
				},
				$query->posts
			)
		);

		// Batch-prime caches so subsequent get_post() and get_post_meta() calls are memory hits.
		_prime_post_caches( $post_ids );

		return $post_ids;
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
	private function include_scheduled_posts( array $query_args ): array {
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
}
