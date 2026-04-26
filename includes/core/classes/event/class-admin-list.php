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
use GatherPress\Core\Venue\Setup;
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
		add_action( 'load-edit.php', array( $this, 'default_sort' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_rsvp_sorting' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_venue_sorting' ) );
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
	 * Sets the default sort field and sort order on the event post type admin screen, to order by event date.
	 *
	 * @author John Blackbourn @johnbillion
	 * @source https://github.com/johnbillion/extended-cpts/blob/20b7e9773b60f7301cd59ee520affa0ff63f90e6/src/PostTypeAdmin.php#L160-L178
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function default_sort(): void {
		$screen = get_current_screen();

		if ( ! $screen || ! post_type_supports( $screen->post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		// If the screen is already ordered, bail out.
		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Default to sorting by event date ascending.
		$_GET['orderby'] = 'datetime';
		$_GET['order']   = 'asc';
	}

	/**
	 * Make custom columns sortable for Event post type in the admin dashboard.
	 *
	 * This method allows the custom columns, including the 'Event date & time' and 'RSVPs' columns,
	 * to be sortable in the WordPress admin dashboard for Event post types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of sortable columns.
	 * @return array An updated array of sortable columns.
	 */
	public function sortable_columns( array $columns ): array {
		// Add 'datetime' as a sortable column.
		$columns['datetime'] = 'datetime';
		// Add 'venue' as a sortable column.
		$columns['venue'] = 'venue';
		// Add 'rsvps' as a sortable column.
		$columns['rsvps'] = 'rsvps';

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
			} elseif ( false === strpos( $view_links['all'], 'class="current"' ) ) {
				// Add "current" class to "All" when no filter is active.
				// default_sort() adds orderby/order to $_GET which prevents
				// WordPress from detecting this as a base request.
				$view_links['all'] = str_replace(
					'<a ',
					'<a class="current" aria-current="page" ',
					$view_links['all']
				);
			}
		}

		return array_slice( $view_links, 0, $placement, true )
			+ $inserts
			+ array_slice( $view_links, $placement, null, true );
	}

	/**
	 * Get counts of upcoming and past events for a given post type.
	 *
	 * Uses the same datetime comparison logic as Query::adjust_event_sql()
	 * with inclusive=true: upcoming uses datetime_end_gmt (includes running events),
	 * past uses datetime_start_gmt (excludes running events).
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

		// Upcoming: events whose end time is still in the future (includes currently running),
		// or events with no row in the events table (no date set yet).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$upcoming = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(1) FROM %i LEFT JOIN %i ON %i.ID = %i.post_id'
				. ' WHERE %i.post_type = %s AND %i.post_status NOT IN'
				. " ('trash', 'auto-draft') AND (%i.datetime_end_gmt >= %s"
				. ' OR %i.post_id IS NULL)',
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$post_type,
				$wpdb->posts,
				$table,
				$current,
				$table
			)
		);

		// Past: events whose start time is in the past (excludes currently running),
		// excluding events with no row in the events table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$past = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(1) FROM %i LEFT JOIN %i ON %i.ID = %i.post_id'
				. ' WHERE %i.post_type = %s AND %i.post_status NOT IN'
				. " ('trash', 'auto-draft') AND %i.datetime_start_gmt < %s"
				. ' AND %i.post_id IS NOT NULL',
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$post_type,
				$wpdb->posts,
				$table,
				$current,
				$table
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
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function handle_rsvp_sorting( $query ): void {
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
	 * Handle venue sorting in the admin list table.
	 *
	 * This method modifies the query to sort events by venue name alphabetically.
	 * Similar to how WordPress core handles taxonomy sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function handle_venue_sorting( $query ): void {
		$this->handle_column_sorting(
			$query,
			'venue',
			array(
				'posts_join_paged' => array( $this, 'venue_sorting_join_paged' ),
				'posts_groupby'    => array( $this, 'sorting_groupby_post_id' ),
				'posts_orderby'    => array( $this, 'venue_sorting_orderby' ),
			),
			'venue_sort_order'
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
	 * Join term relationships and terms tables for venue sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $join The JOIN clause of the query.
	 * @return string Modified JOIN clause.
	 */
	public function venue_sorting_join_paged( string $join ): string {
		global $wpdb;

		$screen         = get_current_screen();
		$post_type      = $screen ? $screen->post_type : Event::POST_TYPE;
		$venue_taxonomy = Setup::get_instance()->taxonomy_for_event_post_type( $post_type );

		// Bail early if the derived taxonomy is not registered to avoid invalid SQL.
		if ( ! taxonomy_exists( $venue_taxonomy ) ) {
			return $join;
		}

		$join .= $wpdb->prepare(
			' LEFT JOIN %i AS venue_tr ON %i.%i = venue_tr.object_id',
			$wpdb->term_relationships,
			$wpdb->posts,
			'ID'
		);
		$join .= $wpdb->prepare(
			' LEFT JOIN %i AS venue_tt'
			. ' ON venue_tr.term_taxonomy_id = venue_tt.term_taxonomy_id'
			. ' AND venue_tt.taxonomy = %s',
			$wpdb->term_taxonomy,
			$venue_taxonomy
		);
		$join .= $wpdb->prepare(
			' LEFT JOIN %i AS venue_terms ON venue_tt.term_id = venue_terms.term_id',
			$wpdb->terms
		);

		return $join;
	}

	/**
	 * Modify the ORDER BY clause for venue sorting.
	 *
	 * @since 1.0.0
	 *
	 * @return string Modified ORDER BY clause.
	 */
	public function venue_sorting_orderby(): string {
		global $wp_query;

		$order = $wp_query->get( 'venue_sort_order', 'ASC' );

		// Remove the filters to prevent them from affecting other queries.
		remove_filter( 'posts_join_paged', array( $this, 'venue_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $this, 'sorting_groupby_post_id' ) );
		remove_filter( 'posts_orderby', array( $this, 'venue_sorting_orderby' ) );

		// Sort by venue name, with NULL/empty values last.
		return "CASE WHEN venue_terms.name IS NULL THEN 1 ELSE 0 END ASC, venue_terms.name {$order}";
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

		if ( 'venue' === $column ) {
			$event             = new Event( $post_id );
			$venue_information = $event->get_venue_information();
			$venue_name        = $venue_information['name'];
			$venue_taxonomy    = Setup::get_instance()->taxonomy_for_event_post_type(
				(string) get_post_type( $post_id )
			);

			if ( has_term( 'online-event', $venue_taxonomy, $post_id ) ) {
				echo '<span class="dashicons dashicons-video-alt3"></span> ';
			}

			if ( ! empty( $venue_name ) ) {
				echo esc_html( $venue_name );
			} else {
				echo '—';
			}
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

		$placement = 2;
		$insert    = array(
			'datetime' => __( 'Event date &amp; time', 'gatherpress' ),
			'venue'    => __( 'Venue', 'gatherpress' ),
			'rsvps'    => __( 'RSVPs', 'gatherpress' ),
		);

		return array_slice( $columns, 0, $placement, true ) + $insert + array_slice( $columns, $placement, null, true );
	}

	/**
	 * Remove the comments column from the events list table.
	 *
	 * This method removes the comments column from the events list table in the WordPress admin
	 * to avoid confusion between regular comments and RSVP submissions. The comment count
	 * bubble can be misleading as it combines unapproved comments and RSVPs without
	 * distinguishing their types.
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
		unset( $columns['comments'] );

		return $columns;
	}
}
