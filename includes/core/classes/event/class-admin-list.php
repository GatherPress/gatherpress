<?php
/**
 * Handles the admin list table for events in GatherPress.
 *
 * This class is responsible for managing the admin events list table, including custom columns,
 * sorting by event date, RSVP count, and venue, view filters for upcoming and past events,
 * and admin menu link modifications.
 *
 * @package GatherPress\Core\Event
 * @since 1.0.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Query;

/**
 * Manages the admin list table for events.
 *
 * This class handles all admin list table concerns for events, including custom columns,
 * sortable columns, sorting logic, view filters, and admin menu modifications.
 *
 * @package GatherPress\Core\Event
 * @since 1.0.0
 */
class Admin_List {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cached event counts keyed by post type for the current request.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<string, int>>
	 */
	protected array $event_counts = array();

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
		add_action( 'pre_get_posts', array( $this, 'handle_rsvp_sorting' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'registered_post_type', array( $this, 'maybe_register_post_type_hooks' ) );
	}

	/**
	 * Registers admin list table hooks when a post type that declares
	 * gatherpress-event-date support finishes registering.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type that was just registered.
	 * @return void
	 */
	public function maybe_register_post_type_hooks( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		add_filter(
			sprintf( 'manage_edit-%s_sortable_columns', $post_type ),
			array( $this, 'sortable_columns' )
		);
		add_filter(
			sprintf( 'views_edit-%s', $post_type ),
			array( $this, 'views_edit' )
		);
		add_action(
			sprintf( 'manage_%s_posts_custom_column', $post_type ),
			array( $this, 'custom_columns' ),
			10,
			2
		);
		add_filter(
			sprintf( 'manage_%s_posts_columns', $post_type ),
			array( $this, 'set_custom_columns' )
		);
		add_filter(
			sprintf( 'manage_%s_posts_columns', $post_type ),
			array( $this, 'remove_comments_column' )
		);
	}

	/**
	 * Make custom columns sortable for Event post type in the admin dashboard.
	 *
	 * This method allows the custom columns, including the 'Event date & time' and 'RSVPs' columns,
	 * to be sortable in the WordPress admin dashboard for Event post types. The
	 * RSVPs sort key is only added when the current screen's post type also
	 * declares `gatherpress-rsvp` support, since post types that only carry
	 * `gatherpress-event-date` have no RSVP column to sort by.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of sortable columns.
	 * @return array An updated array of sortable columns.
	 */
	public function sortable_columns( array $columns ): array {
		// Add 'datetime' as a sortable column.
		$columns['datetime'] = 'datetime';

		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = ( $screen && '' !== (string) $screen->post_type )
			? (string) $screen->post_type
			: Event::POST_TYPE;

		if ( post_type_supports( $post_type, 'gatherpress-rsvp' ) ) {
			// Add 'rsvps' as a sortable column.
			$columns['rsvps'] = 'rsvps';
		}

		return $columns;
	}

	/**
	 * Add 'Upcoming' & 'Past' to the available admin event list table views.
	 *
	 * This method adds links to filter the shown events in the admin list,
	 * the filtering allows to show 'upcoming' or 'past' events.
	 *
	 * @since 1.0.0
	 *
	 * @param array $view_links An array of available list table views.
	 *
	 * @return array Updated list table views.
	 */
	public function views_edit( array $view_links ): array {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return $view_links;
		}

		$post_type = $screen->post_type;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_view = isset( $_GET['gatherpress_event_query'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['gatherpress_event_query'] ) )
			: '';

		$counts    = $this->get_event_counts( $post_type );
		$placement = 1;
		$inserts   = array(
			'upcoming' => __( 'Upcoming', 'gatherpress' ),
			'past'     => __( 'Past', 'gatherpress' ),
		);
		$base_url  = admin_url( 'edit.php' );

		foreach ( $inserts as $key => $value ) {
			$count           = isset( $counts[ $key ] ) ? $counts[ $key ] : 0;
			$inserts[ $key ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				add_query_arg(
					array(
						'gatherpress_event_query' => $key,
						'post_type'               => $post_type,
						'order'                   => 'upcoming' === $key ? 'asc' : 'desc',
						'orderby'                 => 'datetime',
					),
					$base_url
				),
				$key === $current_view ? ' class="current" aria-current="page"' : '',
				$value,
				number_format_i18n( $count )
			);
		}

		if ( isset( $view_links['all'] ) ) {
			if ( $current_view ) {
				// Remove the "current" class from "All" when an event query filter is active.
				$view_links['all'] = str_replace(
					array( ' class="current"', ' aria-current="page"' ),
					'',
					$view_links['all']
				);
			} elseif ( ! str_contains( $view_links['all'], 'class="current"' ) ) {
				// Add "current" to "All" only when no other view is current.
				// We must not stomp on a built-in status filter (Published /
				// Draft / Trash): when the user clicks one of those, that
				// link already has `class="current"` and "All" should stay
				// un-marked.
				$another_view_is_current = false;
				foreach ( $view_links as $other_key => $other_link ) {
					if ( 'all' === $other_key ) {
						continue;
					}
					if ( str_contains( (string) $other_link, 'class="current"' ) ) {
						$another_view_is_current = true;
						break;
					}
				}

				if ( ! $another_view_is_current ) {
					$view_links['all'] = str_replace(
						'<a ',
						'<a class="current" aria-current="page" ',
						$view_links['all']
					);
				}
			}
		}

		return array_slice( $view_links, 0, $placement, true )
			+ $inserts
			+ array_slice( $view_links, $placement, null, true );
	}

	/**
	 * Get counts of upcoming and past events for a given post type.
	 *
	 * Mirrors `Query::adjust_admin_event_sorting()` so the view-link counts
	 * match the actual list. Both buckets pivot on `datetime_end_gmt`:
	 * upcoming = end time is still in the future (running + future);
	 * past = end time has already passed. Mutually exclusive — running
	 * events appear only in upcoming, never in both. Events with no row
	 * in the events table (no date set yet) are excluded from both
	 * buckets — they only appear under the All view.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The event post type to count.
	 * @return array<string, int> Associative array with 'upcoming' and 'past' counts.
	 */
	protected function get_event_counts( string $post_type = Event::POST_TYPE ): array {
		if ( isset( $this->event_counts[ $post_type ] ) ) {
			return $this->event_counts[ $post_type ];
		}

		global $wpdb;

		$table   = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$current = gmdate( Event::DATETIME_FORMAT, time() );

		// Upcoming: events whose end time is still in the future (includes
		// currently running). Events with no row in the events table are
		// not counted — they only show under the All view.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$upcoming = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(1) FROM %i INNER JOIN %i ON %i.ID = %i.post_id'
				. ' WHERE %i.post_type = %s AND %i.post_status NOT IN'
				. " ('trash', 'auto-draft') AND %i.datetime_end_gmt >= %s",
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$post_type,
				$wpdb->posts,
				$table,
				$current
			)
		);

		// Past: events whose end time has already passed. Events with no
		// row in the events table are excluded — they only show under the
		// All view.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$past = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(1) FROM %i INNER JOIN %i ON %i.ID = %i.post_id'
				. ' WHERE %i.post_type = %s AND %i.post_status NOT IN'
				. " ('trash', 'auto-draft') AND %i.datetime_end_gmt < %s",
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$post_type,
				$wpdb->posts,
				$table,
				$current
			)
		);

		$this->event_counts[ $post_type ] = array(
			'upcoming' => $upcoming,
			'past'     => $past,
		);

		return $this->event_counts[ $post_type ];
	}

	/**
	 * Allowlist for additional query parameters.
	 *
	 * Adds 'gatherpress_event_query' to the list of allowed query variables,
	 * to be able to request 'upcoming' or 'past' events in the admin list view.
	 *
	 * @since 1.0.0
	 *
	 * @param  string[] $query_vars List of allowed query variables.
	 *
	 * @return string[] Updated list of allowed query variables.
	 */
	public function query_vars( array $query_vars ) {
		$query_vars[] = 'gatherpress_event_query';
		return $query_vars;
	}

	/**
	 * Handle RSVP column sorting in the events list.
	 *
	 * Bails before delegating to `handle_column_sorting()` when the queried
	 * post type lacks `gatherpress-rsvp` support — there's no RSVP column on
	 * that screen, so a `?orderby=rsvps` request would otherwise issue a
	 * pointless comments-table join.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function handle_rsvp_sorting( $query ): void {
		$post_type = $query->get( 'post_type' );

		// Skip when the queried post type lacks `gatherpress-rsvp` support — there's
		// no RSVP column on that screen, so a `?orderby=rsvps` request would
		// otherwise issue a pointless comments-table join. Multi-post-type queries
		// (array `post_type`) fall through to `handle_column_sorting()`'s own
		// array guard so its early-return arm stays exercised.
		if ( is_string( $post_type ) && ! post_type_supports( $post_type, 'gatherpress-rsvp' ) ) {
			return;
		}

		$this->handle_column_sorting(
			$query,
			'rsvps',
			array(
				'posts_join_paged' => array( $this, 'rsvp_sorting_join_paged' ),
				'posts_groupby'    => array( $this, 'sorting_groupby_post_id' ),
				'posts_orderby'    => array( $this, 'rsvp_sorting_orderby' ),
			),
			'rsvp_sort_order'
		);
	}

	/**
	 * Handle column sorting for the admin events list table.
	 *
	 * Shared logic for sorting by custom columns (RSVP count, venue name, etc.).
	 * Validates the query context, normalizes the sort order, registers the
	 * provided SQL filter callbacks, and stores the order on the query object.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query               $query     The WP_Query instance.
	 * @param string                 $column    The column name to match against the orderby query var.
	 * @param array<string,callable> $filters   Map of WordPress filter hook => callback to register.
	 * @param string                 $order_key The query var key used to store the validated sort order.
	 * @return void
	 */
	private function handle_column_sorting( WP_Query $query, string $column, array $filters, string $order_key ): void {
		$post_type = $query->get( 'post_type' );

		// Bail if post_type is an array (multiple post types queried) — not a single event screen.
		if ( is_array( $post_type ) ) {
			return;
		}

		// Only proceed if we're in admin, on the main query, and dealing with an event post type.
		if ( ! is_admin() || ! $query->is_main_query()
			|| ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		if ( $column !== $query->get( 'orderby' ) ) {
			return;
		}

		$order = strtoupper( $query->get( 'order', 'ASC' ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		foreach ( $filters as $hook => $callback ) {
			add_filter( $hook, $callback );
		}

		$query->set( $order_key, $order );
	}

	/**
	 * Join comments table for RSVP sorting (WordPress style).
	 *
	 * @since 1.0.0
	 *
	 * @param string $join The JOIN clause of the query.
	 * @return string Modified JOIN clause.
	 */
	public function rsvp_sorting_join_paged( string $join ): string {
		global $wpdb;

		$join .= $wpdb->prepare(
			' LEFT JOIN %i AS rsvp_sort_comments'
			. ' ON %i.%i = rsvp_sort_comments.comment_post_ID'
			. ' AND rsvp_sort_comments.comment_type = %s'
			. " AND rsvp_sort_comments.comment_approved = '1'",
			$wpdb->comments,
			$wpdb->posts,
			'ID',
			Rsvp::COMMENT_TYPE
		);

		return $join;
	}

	/**
	 * Group by post ID for column sorting to prevent duplicate results.
	 *
	 * Shared callback used by both RSVP and venue sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $groupby The GROUP BY clause of the query.
	 * @return string Modified GROUP BY clause.
	 */
	public function sorting_groupby_post_id( string $groupby ): string {
		global $wpdb;

		if ( empty( $groupby ) ) {
			$groupby = $wpdb->prepare( '%i.%i', $wpdb->posts, 'ID' );
		}

		return $groupby;
	}

	/**
	 * Order by RSVP count for RSVP sorting (WordPress style).
	 *
	 * @since 1.0.0
	 *
	 * @return string Modified ORDER BY clause.
	 */
	public function rsvp_sorting_orderby(): string {
		global $wp_query;

		$order = $wp_query->get( 'rsvp_sort_order', 'ASC' );

		// Remove the filters to prevent them from affecting other queries.
		remove_filter( 'posts_join_paged', array( $this, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $this, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $this, 'rsvp_sorting_orderby' ) );

		return "COUNT(rsvp_sort_comments.comment_ID) {$order}";
	}

	/**
	 * Populate custom columns for Event post type in the admin dashboard.
	 *
	 * Displays additional information, like event datetime and RSVP count, for Event post types.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  The name of the column to display.
	 * @param int    $post_id The current post ID.
	 * @return void
	 *
	 * @throws Exception If initializing Event or Rsvp object fails, due to invalid post ID or database issues.
	 */
	public function custom_columns( string $column, int $post_id ): void {
		if ( 'datetime' === $column ) {
			$event = new Event( $post_id );
			echo esc_html( $event->get_display_datetime() );
		}

		if ( 'rsvps' === $column ) {
			$rsvp_query = Rsvp_Query::get_instance();

			// Get approved RSVPs (standard display).
			$approved_rsvps = $rsvp_query->get_rsvps(
				array(
					'post_id' => $post_id,
					'status'  => 'approve',
					'count'   => true,
				)
			);

			// Get unapproved RSVPs (pending approval).
			$unapproved_rsvps = $rsvp_query->get_rsvps(
				array(
					'post_id' => $post_id,
					'status'  => 'hold',
					'count'   => true,
				)
			);

			// If no RSVPs at all, show dash.
			if ( 0 === $approved_rsvps && 0 === $unapproved_rsvps ) {
				echo '—';
				return;
			}

			$retrieved_post_type = get_post_type( $post_id );
			$event_post_type     = $retrieved_post_type ? $retrieved_post_type : Event::POST_TYPE;

			// Create link to filtered RSVPs page for approved RSVPs.
			$approved_rsvp_url = add_query_arg(
				array(
					'post_type' => $event_post_type,
					'page'      => Rsvp::COMMENT_TYPE,
					'post_id'   => $post_id,
					'status'    => 'approved',
				),
				admin_url( 'edit.php' )
			);

			// Display approved RSVP count with rounded box.
			echo '<span class="gatherpress-rsvp-container">';
			printf(
				'<a href="%s" class="gatherpress-rsvp-approved"><span class="gatherpress-rsvp-icon">%d</span></a>',
				esc_url( $approved_rsvp_url ),
				(int) $approved_rsvps
			);

			// Show unapproved RSVPs indicator if there are any unapproved.
			if ( $unapproved_rsvps > 0 ) {
				$unapproved_rsvp_url = add_query_arg(
					array(
						'post_type' => $event_post_type,
						'page'      => Rsvp::COMMENT_TYPE,
						'post_id'   => $post_id,
						'status'    => 'pending',
					),
					admin_url( 'edit.php' )
				);

				printf(
					'<a href="%s" class="gatherpress-rsvp-pending" title="%s">%d</a>',
					esc_url( $unapproved_rsvp_url ),
					esc_attr( __( 'Unapproved RSVPs', 'gatherpress' ) ),
					(int) $unapproved_rsvps
				);
			}

			echo '</span>';
		}
	}

	/**
	 * Set custom columns for Event post type in the admin dashboard.
	 *
	 * This method is used to define custom columns for Event post types in the WordPress admin dashboard.
	 * It adds additional columns for displaying event date and time, and RSVP count.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An associative array of column headings.
	 * @return array An updated array of column headings, including the custom columns.
	 */
	public function set_custom_columns( array $columns ): array {
		// Remove the author column.
		unset( $columns['author'] );

		// Pull the auto-injected taxonomy columns out of their default
		// trailing position so we can re-insert them alongside our custom
		// columns. Venue taxonomy goes first (it's the more useful filter
		// link in this context); other taxonomies (Topics, etc.) follow.
		$venue_taxonomy_columns = array();
		$other_taxonomy_columns = array();
		$screen                 = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type              = ( $screen && '' !== (string) $screen->post_type )
			? (string) $screen->post_type
			: Event::POST_TYPE;
		$venue_taxonomy_key     = 'taxonomy-' . Venue_Setup::get_instance()->taxonomy_for_event_post_type( $post_type );

		foreach ( $columns as $key => $label ) {
			if ( ! str_starts_with( $key, 'taxonomy-' ) ) {
				continue;
			}

			if ( $key === $venue_taxonomy_key ) {
				$venue_taxonomy_columns[ $key ] = $label;
			} else {
				$other_taxonomy_columns[ $key ] = $label;
			}

			unset( $columns[ $key ] );
		}

		$placement = 2;
		$insert    = array(
			/**
			 * Filters the label used for the event-date admin list column.
			 *
			 * Lets post types that declare `gatherpress-event-date` support relabel the
			 * column without having to drop and re-add it via WordPress core's
			 * `manage_{$post_type}_posts_columns` filter. A `production` post type can
			 * surface the column as "Premiere date", a `release` post type as "Release
			 * date", etc., while keeping the underlying `datetime` column key (and its
			 * sortable behavior) unchanged.
			 *
			 * @since 1.0.0
			 *
			 * @param string $label     Default column label.
			 * @param string $post_type Post type the admin list is currently rendering.
			 */
			'datetime' => apply_filters(
				'gatherpress_event_datetime_label',
				__( 'Event date &amp; time', 'gatherpress' ),
				$post_type
			),
		);

		// Only show the RSVPs column for post types that declare gatherpress-rsvp support.
		// Event-date-only post types (e.g. theater productions tagged with a premiere date)
		// have no RSVP storage and should not advertise an empty RSVP column.
		if ( post_type_supports( $post_type, 'gatherpress-rsvp' ) ) {
			$insert['rsvps'] = __( 'RSVPs', 'gatherpress' );
		}

		$insert = $insert + $venue_taxonomy_columns + $other_taxonomy_columns;

		return array_slice( $columns, 0, $placement, true ) + $insert + array_slice( $columns, $placement, null, true );
	}

	/**
	 * Remove the comments column from the events list table.
	 *
	 * This method removes the comments column from the events list table in the WordPress admin
	 * to avoid confusion between regular comments and RSVP submissions. The comment count
	 * bubble can be misleading as it combines unapproved comments and RSVPs without
	 * distinguishing their types. Only stripped for post types that declare
	 * `gatherpress-rsvp` support — event-date-only post types still rely on the
	 * standard comments column for regular comments.
	 *
	 * @todo Address limitations in WordPress core get_pending_comments_num function that is too
	 *       generic and does not take custom comment types into account. It just looks for
	 *       unapproved comments of any type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of column names.
	 * @return array The modified array of column names without the comments column.
	 */
	public function remove_comments_column( array $columns ): array {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = ( $screen && '' !== (string) $screen->post_type )
			? (string) $screen->post_type
			: Event::POST_TYPE;

		if ( ! post_type_supports( $post_type, 'gatherpress-rsvp' ) ) {
			return $columns;
		}

		unset( $columns['comments'] );

		return $columns;
	}
}
