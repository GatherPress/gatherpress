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
     * Constructor.
     */
    public function __construct() {
        parent::__construct([
            'singular' => __( 'RSVP', 'gatherpress' ),
            'plural'   => __( 'RSVPs', 'gatherpress' ),
            'ajax'     => false,
            'screen'   => get_current_screen(),
        ]);
    }

    /**
     * Gets the list of columns.
     *
     * @return array
     */
    public function get_columns() {
        return [
            'cb'       => '<input type="checkbox" />',
            'attendee'   => __('Attendee', 'gatherpress'),
            'response' => __('Response', 'gatherpress'),
            'event'    => __('Event', 'gatherpress'),
            'date'     => __('Date', 'gatherpress'),
            'approved' => __('Status', 'gatherpress')
        ];
    }

/**
 * Gets columns that can be hidden via Screen Options.
 *
 * @return array
 */
public function get_hidden_columns() {
    $screen = get_current_screen();
    $hidden = get_user_option('manage_' . $screen->id . '_columnshidden');
    return $hidden ? $hidden : array();
}

    /**
     * Gets sortable columns.
     *
     * @return array
     */
    protected function get_sortable_columns() {
        return [
            'author'   => ['author', false],
            'event'    => ['event', false],
            'date'     => ['date', true],
            'approved' => ['approved', false]
        ];
    }

/**
 * Prepares items for the table.
 */
public function prepare_items() {
    // Set up columns
    $columns = $this->get_columns();
    $hidden = $this->get_hidden_columns();
    $sortable = $this->get_sortable_columns();
    $this->_column_headers = array($columns, $hidden, $sortable);

    // Process bulk actions
    $this->process_bulk_action();

    // Pagination setup
    $user = get_current_user_id();
    $option = sprintf( '%s_per_page', Rsvp::COMMENT_TYPE );
    $per_page = get_user_meta($user, $option, true);

    if (empty($per_page) || !is_numeric($per_page)) {
        $per_page = 20; // Default value
    }

    $current_page = $this->get_pagenum();
    $total_items = $this->get_rsvp_count();

    // Set pagination args
    $this->set_pagination_args([
        'total_items' => $total_items,
        'per_page'    => $per_page,
        'total_pages' => ceil($total_items / $per_page)
    ]);

    // Get items
    $this->items = $this->get_rsvps($per_page, $current_page);
}

/**
 * Gets the RSVPs for display.
 *
 * @param int $per_page    Number of items per page.
 * @param int $page_number Current page number.
 * @return array
 */
private function get_rsvps($per_page = 20, $page_number = 1) {
    global $wpdb;
    $offset = ($page_number - 1) * $per_page;
    $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'comment_date';
    $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';

    $query = "
        SELECT c.*, p.post_title as event_title
        FROM {$wpdb->comments} c
        LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
        WHERE c.comment_type = 'gatherpress_rsvp'
    ";

    // Add search condition if search term is provided
    if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
        $search_term = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';

        $query .= $wpdb->prepare(
            " AND (c.comment_author LIKE %s OR c.comment_author_email OR c.comment_author_IP LIKE %s OR p.post_title LIKE %s)",
            $search_term,
            $search_term,
            $search_term
        );
    }

    // Check for post_id or event filter in request
    if (isset($_REQUEST['post_id']) && !empty($_REQUEST['post_id'])) {
        $post_id = intval($_REQUEST['post_id']);
        $query .= $wpdb->prepare(" AND c.comment_post_ID = %d", $post_id);
    } elseif (isset($_REQUEST['event']) && !empty($_REQUEST['event'])) {
        $event_id = intval($_REQUEST['event']);
        $query .= $wpdb->prepare(" AND c.comment_post_ID = %d", $event_id);
    }

    if (isset($_REQUEST['status']) && in_array($_REQUEST['status'], ['approved', 'pending', 'spam'])) {
        $status = sanitize_text_field($_REQUEST['status']);
        $status_value = $status === 'approved' ? '1' : ($status === 'pending' ? '0' : 'spam');
        $query .= $wpdb->prepare(" AND c.comment_approved = %s", $status_value);
    }

    $query .= " ORDER BY $orderby $order LIMIT $per_page OFFSET $offset";
    $results = $wpdb->get_results($query, ARRAY_A);

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

    // Add search condition if search term is provided
    if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
        $search_term = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';

        $query .= $wpdb->prepare(
            " AND (c.comment_author LIKE %s OR c.comment_author_email OR c.comment_author_IP LIKE %s OR p.post_title LIKE %s)",
            $search_term,
            $search_term,
            $search_term
        );
    }

    // Check for post_id or event filter in request
    if (isset($_REQUEST['post_id']) && !empty($_REQUEST['post_id'])) {
        $post_id = intval($_REQUEST['post_id']);
        $query .= $wpdb->prepare(" AND comment_post_ID = %d", $post_id);
    } elseif (isset($_REQUEST['event']) && !empty($_REQUEST['event'])) {
        $event_id = intval($_REQUEST['event']);
        $query .= $wpdb->prepare(" AND comment_post_ID = %d", $event_id);
    }

    if (isset($_REQUEST['status']) && in_array($_REQUEST['status'], ['approved', 'pending', 'spam'])) {
        $status = sanitize_text_field($_REQUEST['status']);
        $status_value = $status === 'approved' ? '1' : ($status === 'pending' ? '0' : 'spam');
        $query .= $wpdb->prepare(" AND comment_approved = %s", $status_value);
    }

    return $wpdb->get_var($query);
}

    /**
     * Default column renderer.
     *
     * @param array  $item        Item data.
     * @param string $column_name Column being rendered.
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'response':
                $terms = wp_get_object_terms($item['comment_ID'], Rsvp::TAXONOMY);
				$name  = '--';

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
						$name = '--';
				}
                return $name;
            case 'event':
                return '<a href="' . get_permalink($item['comment_post_ID']) . '">' . $item['event_title'] . '</a>';
            case 'date':
                return get_comment_date('F j, Y g:i a', $item['comment_ID']);
            case 'approved':
                $statuses = [
                    '1' => __('Approved', 'gatherpress'),
                    '0' => __('Pending', 'gatherpress'),
                    'spam' => __('Spam', 'gatherpress')
                ];
                return $statuses[$item['comment_approved']];
            default:
                return isset($item[$column_name]) ? $item[$column_name] : '-';
        }
    }

    /**
     * Checkbox column renderer.
     *
     * @param array $item Item data.
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="rsvp_id[]" value="%s" />', $item['comment_ID']
        );
    }

    /**
     * Author column renderer.
     *
     * @param array $item Item data.
     * @return string
     */
    public function column_attendee( $comment ) {
		$base_url = admin_url('edit.php');
		$current_url = add_query_arg(array(
			'post_type' => Event::POST_TYPE,
			'page' => Rsvp::COMMENT_TYPE,
		), $base_url);

		// Create nonce for actions
		$nonce = wp_create_nonce( Rsvp::COMMENT_TYPE );

		$actions = [
			'approve' => sprintf(
				'<a href="%s">%s</a>',
				add_query_arg(array(
					'action' => 'approve',
					'rsvp_id' => $comment['comment_ID'],
					'_wpnonce' => $nonce
				), $current_url),
				__('Approve', 'gatherpress')
			),
			'unapprove' => sprintf(
				'<a href="%s">%s</a>',
				add_query_arg(array(
					'action' => 'unapprove',
					'rsvp_id' => $comment['comment_ID'],
					'_wpnonce' => $nonce
				), $current_url),
				__('Unapprove', 'gatherpress')
			),
			'delete' => sprintf(
				'<a href="%s">%s</a>',
				add_query_arg(array(
					'action' => 'delete',
					'rsvp_id' => $comment['comment_ID'],
					'_wpnonce' => $nonce
				), $current_url),
				__('Delete', 'gatherpress')
			)
		];
		$username = $comment['comment_author'];
		$email    = $comment['comment_author_email'];

		if ( ! empty( $comment['user_id'] ) ) {
			$user     = get_userdata( $comment['user_id'] );
			$username = $user->display_name ?? __( 'Unknown', 'gatherpress' );
			$email    = $user->user_email ?? '';
		}

		$ip_search_url = add_query_arg([
			'post_type' => Event::POST_TYPE,
			'page' => Rsvp::COMMENT_TYPE,
			's' => $comment['comment_author_IP'],
		], admin_url( 'edit.php' ));

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
        return [
            'approve' => __('Approve', 'gatherpress'),
            'unapprove' => __('Unapprove', 'gatherpress'),
            'delete' => __('Delete', 'gatherpress'),
        ];
    }

	public function process_bulk_action() {
		// Get RSVP IDs
		$rsvp_ids = [];

		// Check if we have a bulk action from the form
		if (isset($_REQUEST['rsvp_id']) && is_array($_REQUEST['rsvp_id'])) {
			$rsvp_ids = array_map('intval', $_REQUEST['rsvp_id']);
		}
		// Check if we have a single action from row links
		elseif (isset($_REQUEST['rsvp_id'])) {
			$rsvp_ids = [intval($_REQUEST['rsvp_id'])];
		}

		if (empty($rsvp_ids)) {
			return;
		}

		// Process actions
		if ('delete' === $this->current_action()) {
			foreach ($rsvp_ids as $id) {
				wp_delete_comment($id, true);
			}
		} elseif ('approve' === $this->current_action()) {
			foreach ($rsvp_ids as $id) {
				wp_set_comment_status($id, 'approve');
			}
		} elseif ('unapprove' === $this->current_action()) {
			foreach ($rsvp_ids as $id) {
				wp_set_comment_status($id, 'hold');
			}
		}
	}
}
