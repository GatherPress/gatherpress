<?php
/**
 * File comment block for RSVP_List_Table class.
 *
 * This file contains the definition of the RSVP_List_Table class, which handles
 * the display and management of RSVP entries in the WordPress admin interface.
 *
 * @package GatherPress\Core\Admin
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_List_Table;

/**
 * Class to handle displaying RSVPs in an admin table.
 *
 * Extends the WordPress WP_List_Table class to provide a customized table for
 * managing GatherPress RSVP entries. Handles column display, sorting, filtering,
 * and bulk actions for RSVP data.
 *
 * @package GatherPress\Core\Admin
 * @since 1.0.0
 */
class RSVP_List_Table extends WP_List_Table {
	/**
	 * Default number of RSVPs to display per page in the admin list table.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * Initializes the RSVP list table.
	 *
	 * Sets up the table with appropriate labels and configuration options.
	 * Extends the parent WP_List_Table constructor to inherit core functionality
	 * while customizing for RSVP management needs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional. Additional arguments to configure the list table.
	 *                    Supports 'screen' to specify a particular screen context.
	 */
	public function __construct( $args = array() ) {
		parent::__construct(
			array(
				'plural'   => __( 'RSVPs', 'gatherpress' ),
				'singular' => __( 'RSVP', 'gatherpress' ),
				'ajax'     => false,
				'screen'   => isset( $args['screen'] ) ? $args['screen'] : null,
			)
		);
	}

	/**
	 * Defines the columns displayed in the RSVP management table.
	 *
	 * Returns an associative array of column identifiers and their display labels.
	 * These columns represent different aspects of RSVP data including:
	 * - cb: Checkbox for bulk actions
	 * - attendee: The name/email of the person who RSVPed
	 * - response: Their RSVP status (Attending, Not Attending, etc.)
	 * - event: The associated event title
	 * - approved: Moderation status of the RSVP
	 * - date: When the RSVP was submitted
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of column identifiers and their labels.
	 */
	public function get_columns(): array {
		return array(
			'cb'       => '<input type="checkbox" />',
			'attendee' => __( 'Attendee', 'gatherpress' ),
			'response' => __( 'Response', 'gatherpress' ),
			'event'    => __( 'Event', 'gatherpress' ),
			'approved' => __( 'Status', 'gatherpress' ),
			'date'     => __( 'Date', 'gatherpress' ),
		);
	}

	/**
	 * Filters the columns that can be hidden in Screen Options.
	 *
	 * Removes essential columns from the list of columns that users can hide
	 * via Screen Options. This ensures critical columns like 'attendee' always
	 * remain visible in the RSVP table.
	 *
	 * @since 1.0.0
	 *
	 * @return array Filtered list of columns that can be hidden.
	 */
	public function get_hideable_columns(): array {
		$essential_columns = array( 'attendee' );
		$columns           = $this->get_columns();

		foreach ( $essential_columns as $column_key ) {
			if ( isset( $columns[ $column_key ] ) ) {
				unset( $columns[ $column_key ] );
			}
		}

		return $columns;
	}

	/**
	 * Registers column management functionality for the Screen Options panel.
	 *
	 * Sets up the necessary hooks to enable column visibility options in the Screen Options
	 * dropdown. Uses the get_hideable_columns() method to filter which columns can be
	 * hidden, ensuring essential columns remain visible. Must be called during screen
	 * initialization.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_column_options(): void {
		$screen = get_current_screen();

		if ( empty( $screen ) ) {
			return;
		}

		$screen->add_option( 'columns', array() );

		add_filter(
			sprintf( 'manage_%s_columns', sanitize_key( $screen->id ) ),
			array( $this, 'get_hideable_columns' )
		);
	}

	/**
	 * Retrieves the list of columns that are currently hidden via Screen Options.
	 *
	 * Gets the user's preferred column visibility settings for the current screen.
	 * These settings are stored in user meta and allow users to customize which
	 * columns they want to see in the RSVP table. The method handles cases where
	 * the screen object might not be available or user settings might not exist.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of column identifiers that should be hidden from display.
	 */
	public function get_hidden_columns(): array {
		$screen = get_current_screen();

		if ( empty( $screen ) ) {
			return array();
		}

		$hidden = get_user_option(
			sprintf( 'manage%scolumnshidden', sanitize_key( $screen->id ) )
		);

		return ( is_array( $hidden ) ) ? $hidden : array();
	}

	/**
	 * Defines which columns in the RSVP table can be sorted.
	 *
	 * Returns an array of column identifiers with their sorting configuration.
	 * Each entry includes the column name and a boolean indicating whether it
	 * should be the default sort column (true) or not (false).
	 *
	 * Sortable columns include:
	 * - attendee: The RSVP attendee name
	 * - response: The RSVP response type (Attending, Not Attending, etc.)
	 * - event: The event title
	 * - approved: The approval status
	 * - date: The RSVP submission date (default sort column)
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of sortable column identifiers and their configurations.
	 */
	protected function get_sortable_columns(): array {
		return array(
			'attendee' => array( 'attendee', false ),
			'response' => array( 'response', false ),
			'event'    => array( 'event', false ),
			'approved' => array( 'approved', false ),
			'date'     => array( 'date', true ),
		);
	}

	/**
	 * Prepares items for display in the RSVP table.
	 *
	 * Sets up the table headers, processes bulk actions, determines pagination settings,
	 * and retrieves the appropriate RSVP items for the current page view. The method:
	 * - Configures columns (visible, hidden, and sortable)
	 * - Processes any pending bulk actions
	 * - Determines the appropriate per-page count (from user preferences or default)
	 * - Calculates pagination parameters based on total items and per-page count
	 * - Fetches the RSVP items for the current page
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$user     = get_current_user_id();
		$option   = sprintf( '%s_per_page', Rsvp::COMMENT_TYPE );
		$per_page = get_user_meta( $user, $option, true );

		if ( empty( $per_page ) || ! is_numeric( $per_page ) ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}

		$current_page = $this->get_pagenum();
		$total_items  = $this->get_rsvp_count();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);

		$this->items = $this->get_rsvps( $per_page, $current_page );
	}

	/**
	 * Retrieves RSVP comments for display in the admin table with pagination and filtering.
	 *
	 * Fetches RSVP comments with support for:
	 * - Pagination (per page count and page number)
	 * - Search filtering (by name, email, IP address, or event title)
	 * - Event/post filtering
	 * - Status filtering (approved, pending, spam)
	 * - Custom ordering and sorting
	 *
	 * The method transforms comment objects into arrays and adds additional data
	 * like event titles for display purposes.
	 *
	 * @since 1.0.0
	 *
	 * @param ?int $per_page    Optional. Number of items per page. Default null (uses DEFAULT_PER_PAGE).
	 * @param int  $page_number Optional. Current page number. Default 1.
	 *
	 * @return array Array of RSVP comment data prepared for display.
	 */
	private function get_rsvps( ?int $per_page = null, int $page_number = 1 ): array {
		$rsvp_query = Rsvp_Query::get_instance();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( null === $per_page ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}

		$offset = ( $page_number - 1 ) * $per_page;

		$args = array(
			'number' => $per_page,
			'offset' => $offset,
			'status' => 'all',
		);

		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );

			if ( filter_var( $search, FILTER_VALIDATE_IP ) ) {
				$args['author_ip'] = $search;
			} else {
				add_filter(
					'comments_clauses',
					static function ( $clauses ) use ( $search ): array {
						global $wpdb;
						$search_term = '%' . $wpdb->esc_like( $search ) . '%';

						$clauses['where'] .= $wpdb->prepare(
							" AND (comment_author LIKE %s OR comment_author_email LIKE %s OR {$wpdb->posts}.post_title LIKE %s)",
							$search_term,
							$search_term,
							$search_term
						);

						return $clauses;
					}
				);
			}
		}

		if ( isset( $_REQUEST['user_id'] ) && ! empty( $_REQUEST['user_id'] ) ) {
			$args['user_id'] = intval( $_REQUEST['user_id'] );
		}

		if ( isset( $_REQUEST['post_id'] ) && ! empty( $_REQUEST['post_id'] ) ) {
			$args['post_id'] = intval( $_REQUEST['post_id'] );
		} elseif ( isset( $_REQUEST['event'] ) && ! empty( $_REQUEST['event'] ) ) {
			$args['post_id'] = intval( $_REQUEST['event'] );
		}

		if ( isset( $_REQUEST['status'] ) && in_array( $_REQUEST['status'], array( 'approved', 'pending', 'spam' ), true ) ) {
			$status         = sanitize_text_field( wp_unslash( $_REQUEST['status'] ) );
			$args['status'] = ( 'approved' === $status ) ? 'approve' : ( ( 'spam' === $status ) ? 'spam' : 'hold' );
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'comment_date';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		$args['orderby'] = $orderby;
		$args['order']   = $order;

		$items = $rsvp_query->get_rsvps( $args );

		return array_map(
			static function ( $item ): array {
				$item_array                = (array) $item;
				$item_array['event_title'] = get_the_title( (int) $item->comment_post_ID );

				return $item_array;
			},
			$items
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Retrieves the total count of RSVP comments based on filter criteria.
	 *
	 * Counts RSVP comments with optional filtering by search term, post/event ID,
	 * and approval status. Uses the get_rsvps() method with the count parameter
	 * to efficiently retrieve only the count value.
	 *
	 * @since 1.0.0
	 *
	 * @return int The total number of RSVP comments matching the current filters.
	 */
	private function get_rsvp_count(): int {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$rsvp_query = Rsvp_Query::get_instance();
		$args       = array(
			'count'  => true,
			'status' => 'all',
		);

		if ( isset( $_REQUEST['user_id'] ) && ! empty( $_REQUEST['user_id'] ) ) {
			$args['user_id'] = intval( $_REQUEST['user_id'] );
		}

		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$search_term    = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			$args['search'] = $search_term;
		}

		if ( isset( $_REQUEST['post_id'] ) && ! empty( $_REQUEST['post_id'] ) ) {
			$args['post_id'] = intval( $_REQUEST['post_id'] );
		} elseif ( isset( $_REQUEST['event'] ) && ! empty( $_REQUEST['event'] ) ) {
			$args['post_id'] = intval( $_REQUEST['event'] );
		}

		if ( isset( $_REQUEST['status'] ) && in_array( $_REQUEST['status'], array( 'approved', 'pending', 'spam' ), true ) ) {
			$status         = sanitize_text_field( wp_unslash( $_REQUEST['status'] ) );
			$args['status'] = ( 'approved' === $status ) ? 'approve' : ( ( 'spam' === $status ) ? 'spam' : 'hold' );
		}

		return $rsvp_query->get_rsvps( $args );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Renders default column content for RSVP table columns without specific renderers.
	 *
	 * Handles the rendering of various columns in the RSVP admin table by processing
	 * the comment data based on the column name. Specifically manages:
	 * - 'response': Shows the RSVP response status (Attending, Not Attending, etc.)
	 * - 'event': Displays a link to the associated event
	 * - 'approved': Shows the approval status (Approved, Pending, Spam)
	 * - 'date': Formats and displays the RSVP submission date
	 * - Any other column: Attempts to display the corresponding data from the item
	 *
	 * @since 1.0.0
	 *
	 * @param object|array $item        RSVP comment data containing various properties like comment_ID.
	 * @param string       $column_name The name of the column being rendered.
	 *
	 * @return string Formatted content for the specified column.
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'response':
				$terms = wp_get_object_terms( $item['comment_ID'], Rsvp::TAXONOMY );
				$name  = '-';

				if ( empty( $terms ) ) {
					return $name;
				}

				switch ( $terms[0]->slug ) {
					case 'attending':
						$name = __( 'Attending', 'gatherpress' );
						break;
					case 'not_attending':
						$name = __( 'Not Attending', 'gatherpress' );
						break;
					case 'waiting_list':
						$name = __( 'Waiting List', 'gatherpress' );
						break;
					default:
						$name = '-';
				}

				return $name;
			case 'event':
				return '<a href="' . esc_url( get_permalink( $item['comment_post_ID'] ) ) . '">' . wp_kses_post( $item['event_title'] ) . '</a>';
			case 'approved':
				$statuses = array(
					'1'    => __( 'Approved', 'gatherpress' ),
					'0'    => __( 'Pending', 'gatherpress' ),
					'spam' => __( 'Spam', 'gatherpress' ),
				);
				return $statuses[ $item['comment_approved'] ];
			case 'date':
				return get_comment_date( 'Y/m/d \a\t g:i a', $item['comment_ID'] );
			default:
				return isset( $item[ $column_name ] ) ? $item[ $column_name ] : '-';
		}
	}

	/**
	 * Renders the checkbox column for bulk actions in the RSVP table.
	 *
	 * Generates a checkbox input element for each RSVP record that allows users
	 * to select multiple entries for performing bulk actions. The checkbox value
	 * is set to the comment ID.
	 *
	 * @since 1.0.0
	 *
	 * @param array|object $item RSVP comment data containing the comment_ID.
	 *
	 * @return string HTML markup for the checkbox input element.
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="gatherpress_rsvp_id[]" value="%d" />',
			intval( $item['comment_ID'] )
		);
	}

	/**
	 * Renders the 'Attendee' column for an RSVP entry in the admin table.
	 *
	 * Generates the HTML content for the attendee column in the RSVP management table,
	 * including attendee information and action links (approve, unapprove, spam, not spam, delete).
	 * The method creates different action links based on the current status of the RSVP comment
	 * and handles security by creating nonces for each action.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item RSVP comment data for the RSVP entry.
	 *
	 * @return string HTML content for the attendee column, including attendee information and action links.
	 */
	public function column_attendee( array $item ): string {
		// Use current URL to preserve all filtering parameters.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$current_url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$nonce       = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$actions     = array();
		$is_approved = ( '1' === $item['comment_approved'] );
		$is_spam     = ( 'spam' === $item['comment_approved'] );

		$action_definitions = array(
			'approve'   => array(
				'condition' => ! $is_approved && ! $is_spam,
				'label'     => __( 'Approve', 'gatherpress' ),
				'action'    => 'approve',
			),
			'unapprove' => array(
				'condition' => $is_approved,
				'label'     => __( 'Unapprove', 'gatherpress' ),
				'action'    => 'unapprove',
			),
			'spam'      => array(
				'condition' => ! $is_spam,
				'label'     => __( 'Spam', 'gatherpress' ),
				'action'    => 'spam',
			),
			'not-spam'  => array(
				'condition' => $is_spam,
				'label'     => __( 'Not Spam', 'gatherpress' ),
				'action'    => 'unspam',
			),
			'delete'    => array(
				'condition'    => true,
				'label'        => __( 'Delete', 'gatherpress' ),
				'action'       => 'delete',
				'class'        => 'submitdelete',
				'custom_nonce' => 'gatherpress_rsvp_action',
			),
		);

		foreach ( $action_definitions as $key => $definition ) {
			if ( $definition['condition'] ) {
				$link_args = array(
					'action'              => $definition['action'],
					'gatherpress_rsvp_id' => $item['comment_ID'],
					'_wpnonce'            => isset( $definition['custom_nonce'] ) ?
						wp_create_nonce( $definition['custom_nonce'] ) :
						$nonce,
				);

				$class_attr = isset( $definition['class'] ) ? ' class="' . esc_attr( $definition['class'] ) . '"' : '';

				$actions[ $key ] = sprintf(
					'<a href="%s"%s>%s</a>',
					esc_url( add_query_arg( $link_args, $current_url ) ),
					$class_attr,
					$definition['label']
				);
			}
		}

		$username = $item['comment_author'];
		$email    = $item['comment_author_email'];

		if ( ! empty( $item['user_id'] ) ) {
			$user     = get_userdata( $item['user_id'] );
			$username = $user->display_name ?? __( 'Unknown', 'gatherpress' );
			$email    = $user->user_email ?? '';
		}

		$ip_search_url = add_query_arg(
			array(
				'post_type' => Event::POST_TYPE,
				'page'      => Rsvp::COMMENT_TYPE,
				's'         => $item['comment_author_IP'],
			),
			admin_url( 'edit.php' )
		);

		$template = Utility::render_template(
			sprintf( '%s/includes/templates/admin/rsvp/attendee.php', GATHERPRESS_CORE_PATH ),
			array(
				'comment'       => $item,
				'username'      => $username,
				'email'         => $email,
				'ip_search_url' => $ip_search_url,
			),
			false
		);

		return $template . $this->row_actions( $actions );
	}

	/**
	 * Retrieves the available bulk actions for the RSVP table.
	 *
	 * Returns an array of bulk actions that can be performed on multiple RSVP entries
	 * simultaneously. Actions include approve, unapprove, and delete. Access is restricted
	 * based on user capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @return array An associative array of bulk action identifiers and their labels.
	 */
	public function get_bulk_actions(): array {
		if ( ! current_user_can( Rsvp::CAPABILITY ) ) {
			return array();
		}

		return array(
			'approve'   => __( 'Approve', 'gatherpress' ),
			'unapprove' => __( 'Unapprove', 'gatherpress' ),
			'spam'      => __( 'Mark as Spam', 'gatherpress' ),
			'unspam'    => __( 'Not Spam', 'gatherpress' ),
			'delete'    => __( 'Delete', 'gatherpress' ),
		);
	}

	/**
	 * Displays a single row in the RSVP list table.
	 *
	 * Renders an individual row in the RSVP table with appropriate CSS classes
	 * based on the approval status. Also handles capability checks to ensure
	 * only authorized users can view the row.
	 *
	 * @since 1.0.0
	 *
	 * @param object|array $item RSVP comment data, either as an object or an associative array.
	 *                           Contains properties/keys like 'comment_ID' and 'comment_approved'.
	 *
	 * @return void The method outputs HTML directly and doesn't return a value.
	 */
	public function single_row( $item ): void {
		if ( ! current_user_can( Rsvp::CAPABILITY ) ) {
			return;
		}

		$status      = ( '1' === $item['comment_approved'] ) ? 'approved' :
			( ( 'spam' === $item['comment_approved'] ) ? 'spam' : 'unapproved' );
		$odd_or_even = 'odd';

		echo '<tr id="' . esc_attr( 'gatherpress-rsvp-' . $item['comment_ID'] ) . '" class="' . esc_attr( 'gatherpress-rsvp ' . $odd_or_even . ' ' . $status ) . '">';

		$this->single_row_columns( $item );

		echo '</tr>';
	}

	/**
	 * Processes bulk actions for multiple RSVPs.
	 *
	 * Handles security verification and processes bulk operations such as approval,
	 * unapproval, marking as spam, or deletion of RSVPs. Requires appropriate nonce
	 * verification and capability checks before processing any actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function process_bulk_action(): void {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, Rsvp::COMMENT_TYPE ) ) {
			// Check for delete action nonce separately.
			if ( 'delete' === $this->current_action() ) {
				if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'gatherpress_rsvp_action' ) ) {
					return;
				}
			} else {
				return;
			}
		}

		if ( ! current_user_can( Rsvp::CAPABILITY ) ) {
			return;
		}

		$rsvp_ids = array();

		if ( isset( $_REQUEST['gatherpress_rsvp_id'] ) && is_array( $_REQUEST['gatherpress_rsvp_id'] ) ) {
			$rsvp_ids = array_map( 'intval', $_REQUEST['gatherpress_rsvp_id'] );
		} elseif ( isset( $_REQUEST['gatherpress_rsvp_id'] ) ) {
			$rsvp_ids = array( intval( $_REQUEST['gatherpress_rsvp_id'] ) );
		}

		if ( empty( $rsvp_ids ) ) {
			return;
		}

		$current_action    = $this->current_action();
		$action_status_map = array(
			'approve'   => 'approve',
			'unapprove' => 'hold',
			'spam'      => 'spam',
			'unspam'    => 'approve',
		);

		if ( 'delete' === $current_action ) {
			foreach ( $rsvp_ids as $rsvp_id ) {
				wp_delete_comment( $rsvp_id, true );
			}
		} elseif ( isset( $action_status_map[ $current_action ] ) ) {
			$status = $action_status_map[ $current_action ];

			foreach ( $rsvp_ids as $rsvp_id ) {
				wp_set_comment_status( $rsvp_id, $status );
			}
		}
	}

	/**
	 * Retrieves the list of views available on this table.
	 *
	 * Overrides parent method to add custom views for RSVP management.
	 * Note: This method processes request parameters for view state only,
	 * actual data operations are handled separately with nonce verification.
	 *
	 * @since 1.0.0
	 *
	 * @global string $post_type
	 * @return array An array of HTML links for different views.
	 */
	public function get_views(): array {
		$rsvp_query     = Rsvp_Query::get_instance();
		$status_links   = array();
		$current        = 'all';
		$nonce_verified = false;

		if ( isset( $_REQUEST['_wpnonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );

			if ( wp_verify_nonce( $nonce, Rsvp::COMMENT_TYPE ) ) {
				$nonce_verified = true;
			}
		}

		// Check for post_id filter.
		$post_id = 0;
		if ( isset( $_REQUEST['post_id'] ) && ! empty( $_REQUEST['post_id'] ) ) {
			$post_id = intval( $_REQUEST['post_id'] );
		} elseif ( isset( $_REQUEST['event'] ) && ! empty( $_REQUEST['event'] ) ) {
			$post_id = intval( $_REQUEST['event'] );
		}

		// Check for current view status (doesn't require nonce).
		if ( isset( $_REQUEST['user_id'] ) ) {
			$user_id = absint( $_REQUEST['user_id'] );

			if ( get_current_user_id() === $user_id ) {
				$current = 'mine';
			}
		} elseif ( isset( $_REQUEST['status'] ) ) {
			$current = sanitize_key( wp_unslash( $_REQUEST['status'] ) );
		}

		$base_url_args = array(
			'post_type' => Event::POST_TYPE,
			'page'      => Rsvp::COMMENT_TYPE,
			'_wpnonce'  => wp_create_nonce( Rsvp::COMMENT_TYPE ),
		);

		// Preserve post_id filter in base URL.
		if ( $post_id ) {
			$base_url_args['post_id'] = $post_id;
		}

		$base_url = add_query_arg( $base_url_args, admin_url( 'edit.php' ) );

		// Base args for count queries.
		$count_base_args = array( 'count' => true );
		if ( $post_id ) {
			$count_base_args['post_id'] = $post_id;
		}

		// Get counts for each status.
		$all_count      = $rsvp_query->get_rsvps(
			array_merge(
				$count_base_args,
				array( 'status' => 'all' )
			)
		);
		$approved_count = $rsvp_query->get_rsvps(
			array_merge(
				$count_base_args,
				array( 'status' => 'approve' )
			)
		);
		$pending_count  = $rsvp_query->get_rsvps(
			array_merge(
				$count_base_args,
				array( 'status' => 'hold' )
			)
		);
		$spam_count     = $rsvp_query->get_rsvps(
			array_merge(
				$count_base_args,
				array( 'status' => 'spam' )
			)
		);
		$mine_count     = $rsvp_query->get_rsvps(
			array_merge(
				$count_base_args,
				array(
					'status'  => 'all',
					'user_id' => get_current_user_id(),
				)
			)
		);

		// Build the links array with nonce included in base URL.
		$status_links['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( $base_url ),
			'all' === $current ? ' class="current"' : '',
			__( 'All', 'gatherpress' ),
			number_format_i18n( $all_count )
		);

		if ( $mine_count > 0 ) {
			$status_links['mine'] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( array( 'user_id' => get_current_user_id() ), $base_url ) ),
				'mine' === $current ? ' class="current"' : '',
				__( 'Mine', 'gatherpress' ),
				number_format_i18n( $mine_count )
			);
		}

		$status_links['approved'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( array( 'status' => 'approved' ), $base_url ) ),
			'approved' === $current ? ' class="current"' : '',
			__( 'Approved', 'gatherpress' ),
			number_format_i18n( $approved_count )
		);

		$status_links['pending'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( array( 'status' => 'pending' ), $base_url ) ),
			'pending' === $current ? ' class="current"' : '',
			__( 'Pending', 'gatherpress' ),
			number_format_i18n( $pending_count )
		);

		$status_links['spam'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url( add_query_arg( array( 'status' => 'spam' ), $base_url ) ),
			'spam' === $current ? ' class="current"' : '',
			__( 'Spam', 'gatherpress' ),
			number_format_i18n( $spam_count )
		);

		return $status_links;
	}

	/**
	 * Displays the RSVP list table with nonce fields.
	 *
	 * Outputs the HTML for the RSVP list table, including necessary nonce fields
	 * for security. This method extends the parent display() method to add
	 * GatherPress-specific nonce fields for RSVP actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function display() {
		wp_nonce_field( Rsvp::COMMENT_TYPE );
		wp_nonce_field( 'gatherpress_rsvp_action', '_gatherpress_rsvp_action_nonce' );

		parent::display();
	}
}
