<?php
/**
 * File comment block for Rsvp_Setup class.
 *
 * This file contains the definition of the Rsvp_Setup class, which handles
 * setup tasks related to RSVP functionality within the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_List_Table;

/**
 * Class to handle displaying RSVPs in an admin table.
 *
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
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'RSVP', 'gatherpress' ),
				'plural'   => __( 'RSVPs', 'gatherpress' ),
				'ajax'     => false,
				'screen'   => get_current_screen(),
			)
		);
	}

	/**
	 * Gets the list of columns.
	 *
	 * @return array
	 */
	public function get_columns() {
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
	 * Gets columns that can be hidden via Screen Options.
	 *
	 * @return array
	 */
	public function get_hidden_columns() {
		$screen = get_current_screen();
		$hidden = get_user_option( 'manage_' . $screen->id . '_columnshidden' );

		return ! empty( $hidden ) ? $hidden : array();
	}

	/**
	 * Gets sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'author'   => array( 'author', false ),
			'event'    => array( 'event', false ),
			'approved' => array( 'approved', false ),
			'date'     => array( 'date', true ),
		);
	}

	/**
	 * Prepares items for the table.
	 */
	public function prepare_items() {
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
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);

		$this->items = $this->get_rsvps( $per_page, $current_page );
	}

	/**
	 * Gets the RSVPs for display.
	 *
	 * @param int $per_page    Number of items per page.
	 * @param int $page_number Current page number.
	 * @return array
	 */
	private function get_rsvps( $per_page = null, $page_number = 1 ) {
		if ( null === $per_page ) {
			$per_page = self::DEFAULT_PER_PAGE;
		}

		global $wpdb;

		$offset  = ( $page_number - 1 ) * $per_page;
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'comment_date';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'DESC';

		$query = "SELECT c.*, p.post_title as event_title
			FROM {$wpdb->comments} c
			LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
			WHERE c.comment_type = 'gatherpress_rsvp'";

		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%';

			$query .= $wpdb->prepare(
				' AND (c.comment_author LIKE %s OR c.comment_author_email OR c.comment_author_IP LIKE %s OR p.post_title LIKE %s)',
				$search_term,
				$search_term,
				$search_term
			);
		}

		if ( isset( $_REQUEST['post_id'] ) && ! empty( $_REQUEST['post_id'] ) ) {
			$post_id = intval( $_REQUEST['post_id'] );
			$query  .= $wpdb->prepare( ' AND c.comment_post_ID = %d', $post_id );
		} elseif ( isset( $_REQUEST['event'] ) && ! empty( $_REQUEST['event'] ) ) {
			$event_id = intval( $_REQUEST['event'] );
			$query   .= $wpdb->prepare( ' AND c.comment_post_ID = %d', $event_id );
		}

		if ( isset( $_REQUEST['status'] ) && in_array( $_REQUEST['status'], array( 'approved', 'pending', 'spam' ) ) ) {
			$status       = sanitize_text_field( $_REQUEST['status'] );
			$status_value = 'approved' === $status ? '1' : ( 'pending' === $status ? '0' : 'spam' );
			$query       .= $wpdb->prepare( ' AND c.comment_approved = %s', $status_value );
		}

		$query  .= " ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";
		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results;
	}

	/**
	 * Gets the total RSVP count.
	 *
	 * @return int
	 */
	private function get_rsvp_count() {
		global $wpdb;

		$query = "SELECT COUNT(*) FROM {$wpdb->comments} c
				LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
				WHERE c.comment_type = 'gatherpress_rsvp'";

		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$search_term = '%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%';

			$query .= $wpdb->prepare(
				' AND (c.comment_author LIKE %s OR c.comment_author_email OR c.comment_author_IP LIKE %s OR p.post_title LIKE %s)',
				$search_term,
				$search_term,
				$search_term
			);
		}

		if ( isset( $_REQUEST['post_id'] ) && ! empty( $_REQUEST['post_id'] ) ) {
			$post_id = intval( $_REQUEST['post_id'] );
			$query  .= $wpdb->prepare( ' AND comment_post_ID = %d', $post_id );
		} elseif ( isset( $_REQUEST['event'] ) && ! empty( $_REQUEST['event'] ) ) {
			$event_id = intval( $_REQUEST['event'] );
			$query   .= $wpdb->prepare( ' AND comment_post_ID = %d', $event_id );
		}

		if ( isset( $_REQUEST['status'] ) && in_array( $_REQUEST['status'], array( 'approved', 'pending', 'spam' ) ) ) {
			$status       = sanitize_text_field( $_REQUEST['status'] );
			$status_value = 'approved' === $status ? '1' : ( 'pending' === $status ? '0' : 'spam' );
			$query       .= $wpdb->prepare( ' AND comment_approved = %s', $status_value );
		}

		return $wpdb->get_var( $query );
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item        Item data.
	 * @param string $column_name Column being rendered.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
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
				return '<a href="' . get_permalink( $item['comment_post_ID'] ) . '">' . $item['event_title'] . '</a>';
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
	 * Checkbox column renderer.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="rsvp_id[]" value="%d" />',
			intval( $item['comment_ID'] )
		);
	}

	/**
	 * Author column renderer.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_attendee( $comment ) {
		$base_url    = admin_url( 'edit.php' );
		$current_url = add_query_arg(
			array(
				'post_type' => Event::POST_TYPE,
				'page'      => Rsvp::COMMENT_TYPE,
			),
			$base_url
		);
		$nonce       = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$actions     = array();
		$is_approved = ( '1' === $comment['comment_approved'] );
		$is_spam     = ( 'spam' === $comment['comment_approved'] );

		if ( $is_approved ) {
			$actions['unapprove'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'action'   => 'unapprove',
							'rsvp_id'  => $comment['comment_ID'],
							'_wpnonce' => $nonce,
						),
						$current_url
					)
				),
				__( 'Unapprove', 'gatherpress' )
			);
		} else {
			$actions['approve'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'action'   => 'approve',
							'rsvp_id'  => $comment['comment_ID'],
							'_wpnonce' => $nonce,
						),
						$current_url
					)
				),
				__( 'Approve', 'gatherpress' )
			);
		}

		if ( $is_spam ) {
			$actions['not-spam'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'action'   => 'unspam',
							'rsvp_id'  => $comment['comment_ID'],
							'_wpnonce' => $nonce,
						),
						$current_url
					)
				),
				__( 'Not Spam', 'gatherpress' )
			);
		} else {
			$actions['spam'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					add_query_arg(
						array(
							'action'   => 'spam',
							'rsvp_id'  => $comment['comment_ID'],
							'_wpnonce' => $nonce,
						),
						$current_url
					)
				),
				__( 'Spam', 'gatherpress' )
			);
		}

		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete">%s</a>',
			esc_url(
				add_query_arg(
					array(
						'action'   => 'delete',
						'rsvp_id'  => $comment['comment_ID'],
						'_wpnonce' => wp_create_nonce( 'gatherpress_rsvp_action' ),
					),
					$current_url
				)
			),
			__( 'Delete', 'gatherpress' )
		);

		$username = $comment['comment_author'];
		$email    = $comment['comment_author_email'];

		if ( ! empty( $comment['user_id'] ) ) {
			$user     = get_userdata( $comment['user_id'] );
			$username = $user->display_name ?? __( 'Unknown', 'gatherpress' );
			$email    = $user->user_email ?? '';
		}

		$ip_search_url = add_query_arg(
			array(
				'post_type' => Event::POST_TYPE,
				'page'      => Rsvp::COMMENT_TYPE,
				's'         => $comment['comment_author_IP'],
			),
			admin_url( 'edit.php' )
		);

		$template = Utility::render_template(
			sprintf( '%s/includes/templates/admin/rsvp/attendee.php', GATHERPRESS_CORE_PATH ),
			array(
				'comment'       => $comment,
				'username'      => $username,
				'email'         => $email,
				'ip_search_url' => $ip_search_url,
			),
			false
		);

		return $template . $this->row_actions( $actions );
	}

	/**
	 * Get bulk actions for the table.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'approve'   => __( 'Approve', 'gatherpress' ),
			'unapprove' => __( 'Unapprove', 'gatherpress' ),
			'delete'    => __( 'Delete', 'gatherpress' ),
		);
	}

	public function single_row( $comment ) {
		$status      = ( '1' === $comment['comment_approved'] ) ? 'approved' :
			( ( 'spam' === $comment['comment_approved'] ) ? 'spam' : 'unapproved' );
		$odd_or_even = 'odd';

		echo '<tr id="' . esc_attr( 'rsvp-' . $comment['comment_ID'] ) . '" class="' . esc_attr( 'rsvp ' . $odd_or_even . ' ' . $status ) . '">';

		$this->single_row_columns( $comment );

		echo '</tr>';
	}

	public function process_bulk_action() {
		$rsvp_ids = array();

		if ( isset( $_REQUEST['rsvp_id'] ) && is_array( $_REQUEST['rsvp_id'] ) ) {
			$rsvp_ids = array_map( 'intval', $_REQUEST['rsvp_id'] );
		} elseif ( isset( $_REQUEST['rsvp_id'] ) ) {
			$rsvp_ids = array( intval( $_REQUEST['rsvp_id'] ) );
		}

		if ( empty( $rsvp_ids ) ) {
			return;
		}

		if ( 'delete' === $this->current_action() ) {
			foreach ( $rsvp_ids as $id ) {
				wp_delete_comment( $id, true );
			}
		} elseif ( 'approve' === $this->current_action() ) {
			foreach ( $rsvp_ids as $id ) {
				wp_set_comment_status( $id, 'approve' );
			}
		} elseif ( 'unapprove' === $this->current_action() ) {
			foreach ( $rsvp_ids as $id ) {
				wp_set_comment_status( $id, 'hold' );
			}
		}
	}
}
