<?php
/**
 * Manages RSVP related functionality for events.
 *
 * This class is responsible for handling all operations related to RSVPs for events, including
 * retrieving RSVP information, saving RSVPs, checking attending limits, and more.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings\Leadership;
use WP_Post;

/**
 * Class Rsvp.
 *
 * Manages RSVP functionality for events, including response status tracking and limits.
 *
 * @since 1.0.0
 */
class Rsvp {
	/**
	 * Constant representing the RSVP Taxonomy.
	 *
	 * This constant defines the status taxonomy for RSVP comment type.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TAXONOMY = '_gatherpress_rsvp_status';

	/**
	 * Cache key format for RSVPs.
	 *
	 * @since 1.0.0
	 * @var string $CACHE_KEY
	 */
	const CACHE_KEY = 'gatherpress_rsvp_%d';

	/**
	 * Comment type for RSVPs.
	 *
	 * @since 1.0.0
	 * @var string $COMMENT_TYPE
	 */
	const COMMENT_TYPE = 'gatherpress_rsvp';

	/**
	 * An array of RSVP statuses.
	 *
	 * @since 1.0.0
	 * @var string[] Contains RSVP statuses such as 'attending', 'not_attending', 'waiting_list', and 'no_status'.
	 */
	public array $statuses = array(
		'attending',
		'not_attending',
		'waiting_list',
		'no_status',
	);

	/**
	 * The maximum limit for attending responses (RSVPs).
	 *
	 * @since 1.0.0
	 * @var int Represents the maximum number of attendees allowed for an event.
	 */
	protected int $max_attendance_limit;

	/**
	 * The event post object associated with this RSVP instance.
	 *
	 * @since 1.0.0
	 * @var WP_Post|null
	 */
	protected $event;

	/**
	 *
	 * Rsvp Constructor.
	 *
	 * Initializes an RSVP instance for a specific event.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The event post ID.
	 */
	public function __construct( int $post_id ) {
		$this->event                = get_post( $post_id );
		$this->max_attendance_limit = intval( get_post_meta( $post_id, 'gatherpress_max_attendance_limit', true ) );
	}

	/**
	 * Get RSVP information for a user and an event.
	 *
	 * This method retrieves RSVP information for a specific user and event, including the RSVP entry's ID,
	 * associated post ID, user ID, timestamp, RSVP status ('attending', 'not_attending', or 'waiting_list'),
	 * and the number of guests accompanying the user. If no RSVP information is found for the user and event,
	 * default values are provided.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id A user ID.
	 *
	 * @return array An array containing RSVP information, including ID, post ID, user ID, timestamp, status, and guests.
	 */
	public function get( int $user_id ): array {
		$post_id    = $this->event->ID;
		$rsvp_query = Rsvp_Query::get_instance();

		if ( 1 > $post_id || 1 > $user_id ) {
			return array();
		}

		$data = array(
			'id'        => 0,
			'post_id'   => $post_id,
			'user_id'   => $user_id,
			'timestamp' => null,
			'status'    => 'no_status',
			'guests'    => 0,
			'anonymous' => 0,
		);

		$rsvp = $rsvp_query->get_rsvp(
			array(
				'post_id' => $post_id,
				'user_id' => $user_id,
			)
		);

		if ( ! empty( $rsvp ) ) {
			$data['id']        = $rsvp->user_id;
			$data['timestamp'] = $rsvp->comment_date;
			$data['anonymous'] = intval( get_comment_meta( intval( $rsvp->comment_ID ), 'gatherpress_rsvp_anonymous', true ) );
			$data['guests']    = intval( get_comment_meta( intval( $rsvp->comment_ID ), 'gatherpress_rsvp_guests', true ) );
			$terms             = wp_get_object_terms( intval( $rsvp->comment_ID ), self::TAXONOMY );

			if ( ! empty( $terms ) && is_array( $terms ) ) {
				$data['status'] = $terms[0]->slug;
			}
		}

		return $data;
	}

	/**
	 * Saves a user's RSVP status for an event.
	 *
	 * Allows assigning one of the specified RSVP statuses to a user for an event. The user can be marked as 'attending',
	 * 'not_attending', or placed on a 'waiting_list'. Additionally, users can specify the number of guests they plan to bring
	 * along and whether their RSVP should be considered anonymous. This method updates the database accordingly to reflect the
	 * new RSVP status.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id   The ID of the user whose RSVP status is being updated. Must be greater than 0.
	 * @param string $status    The new RSVP status for the user. Acceptable values are 'attending', 'not_attending', or
	 *                          'waiting_list'.
	 * @param int    $anonymous Optional. Whether the RSVP is to be marked as anonymous. Accepts 1 for true (anonymous)
	 *                          and 0 for false (not anonymous). Default 0.
	 * @param int    $guests    Optional. The number of guests the user plans to bring along. Default 0.
	 *
	 * @return array Associative array containing the event ID ('post_id'), user ID ('user_id'), RSVP timestamp ('timestamp'),
	 *               RSVP status ('status'), number of guests ('guests'), and anonymity flag ('anonymous'). Returns a default
	 *               array with 'post_id' and 'user_id' set to 0, 'timestamp' to '0000-00-00 00:00:00', 'status' to 'no_status',
	 *               'guests' to 0, and 'anonymous' to 0 if the post ID or user ID is not valid, or if the status is not one of
	 *               the acceptable values. If the attending limit is reached, 'status' may be automatically set to 'waiting_list',
	 *               and 'guests' to 0, depending on the context.
	 */
	public function save( int $user_id, string $status, int $anonymous = 0, int $guests = 0 ): array {
		global $wpdb;

		$rsvp_query      = Rsvp_Query::get_instance();
		$max_guest_limit = intval( get_post_meta( $this->event->ID, 'gatherpress_max_guest_limit', true ) );

		if ( $max_guest_limit < $guests ) {
			$guests = $max_guest_limit;
		}

		$data = array(
			'post_id'   => 0,
			'user_id'   => 0,
			'timestamp' => '0000-00-00 00:00:00',
			'status'    => 'no_status',
			'guests'    => 0,
			'anonymous' => 0,
		);

		$post_id = $this->event->ID;

		if ( 1 > $post_id || 1 > $user_id ) {
			return $data;
		}

		$rsvp = $rsvp_query->get_rsvp(
			array(
				'post_id' => $post_id,
				'user_id' => $user_id,
			)
		);

		$current_response = $this->get( $user_id );
		$limit_reached    = $this->attending_limit_reached( $current_response, $guests );

		if ( 'attending' === $status && $limit_reached ) {
			$guests = $current_response['guests'];
		}

		if (
			in_array( $status, array( 'attending', 'waiting_list' ), true ) &&
			'attending' !== $current_response['status'] &&
			$limit_reached
		) {
			$status = 'waiting_list';
		}

		if ( 'waiting_list' === $status ) {
			$guests = 0;
		}

		$args = array(
			'comment_post_ID' => $post_id,
			'comment_type'    => self::COMMENT_TYPE,
			'user_id'         => $user_id,
		);

		if ( empty( $rsvp ) ) {
			$comment_id = wp_insert_comment( $args );
		} else {
			$comment_id         = $rsvp->comment_ID;
			$args['comment_ID'] = $comment_id;

			wp_update_comment( $args );
		}

		if ( empty( $comment_id ) ) {
			return $data;
		}

		// If not attending and anonymous or status is 'no_status', remove the record.
		if ( ( 'not_attending' === $status && $anonymous ) || 'no_status' === $status ) {
			wp_delete_comment( $comment_id, true );

			return $data;
		}

		if ( ! in_array( $status, $this->statuses, true ) ) {
			return $data;
		}

		wp_set_object_terms( $comment_id, $status, self::TAXONOMY );

		if ( ! empty( $guests ) ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_guests', $guests );
		} else {
			delete_comment_meta( $comment_id, 'gatherpress_rsvp_guests' );
		}

		if ( ! empty( $anonymous ) ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', $anonymous );
		} else {
			delete_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous' );
		}

		$data = array(
			'post_id'   => intval( $post_id ),
			'user_id'   => intval( $user_id ),
			'timestamp' => gmdate( 'Y-m-d H:i:s' ),
			'status'    => sanitize_key( $status ),
			'guests'    => intval( $guests ),
			'anonymous' => intval( $anonymous ),
		);

		wp_cache_delete( sprintf( self::CACHE_KEY, $post_id ), GATHERPRESS_CACHE_GROUP );

		if ( ! $limit_reached ) {
			$this->check_waiting_list();
		}

		return $data;
	}

	/**
	 * Check the waiting list and move response to attending if spots are available.
	 *
	 * This method checks if there are spots available in the attending list and moves response
	 * from the waiting list to attending based on their timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number of responses from the waiting list that were moved to attending.
	 */
	public function check_waiting_list(): int {
		$responses = $this->responses();
		$i         = 0;

		if (
			intval( $responses['attending']['count'] ) < $this->max_attendance_limit
			&& intval( $responses['waiting_list']['count'] )
		) {
			$waiting_list = $responses['waiting_list']['responses'];

			// People who are longest on the waiting_list should be added first.
			usort( $waiting_list, array( $this, 'sort_by_timestamp' ) );

			$total = $this->max_attendance_limit - intval( $responses['attending']['count'] );

			while ( $i < $total ) {
				// Check that we have enough on the waiting_list to run this.
				if ( ( $i + 1 ) > intval( $responses['waiting_list']['count'] ) ) {
					break;
				}

				$response = $waiting_list[ $i ];
				$this->save( $response['id'], 'attending', $response['anonymous'] );
				++$i;
			}
		}

		return $i;
	}

	/**
	 * Check if the attending limit has been reached for an event.
	 *
	 * This method determines whether the maximum response limit for the 'attending' status
	 * has been reached for the event. It checks the current number of 'attending' responses
	 * and compares it to the defined limit. It considers both the current response status
	 * and the number of guests associated with that response.
	 *
	 * @since 1.0.0
	 *
	 * @param array $current_response The current response data including status and number of guests.
	 *                                Expected to have keys 'status' and 'guests', where 'status' is a
	 *                                string indicating the current response status (e.g., 'attending'),
	 *                                and 'guests' is an integer representing the number of guests.
	 * @param int   $guests           The number of additional guests to consider in the limit calculation.
	 *                                Defaults to 0. This is used to adjust the total count based on any new
	 *                                guests being added as part of the current operation.
	 * @return bool True if the 'attending' limit has been reached, false otherwise.
	 */
	public function attending_limit_reached( array $current_response, int $guests = 0 ): bool {
		$responses  = $this->responses();
		$user_count = 1;

		if ( empty( $this->max_attendance_limit ) ) {
			return false;
		}

		// If the user record was previously attending adjust numbers to figure out new limit.
		if ( 'attending' === $current_response['status'] ) {
			$guests     = $guests - intval( $current_response['guests'] );
			$user_count = 0;
		}

		if (
			! empty( $responses['attending'] ) &&
			intval( $responses['attending']['count'] ) + $user_count + $guests > $this->max_attendance_limit
		) {
			return true;
		}

		return false;
	}

	/**
	 * Get all responses for an event.
	 *
	 * This method retrieves and organizes information about responses for the event.
	 * It provides an array with response details grouped by RSVP status ('attending', 'not_attending', 'waiting_list'),
	 * along with counts and additional response data.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing response information grouped by RSVP status.
	 */
	public function responses(): array {
		$post_id    = $this->event->ID;
		$cache_key  = sprintf( self::CACHE_KEY, $post_id );
		$retval     = wp_cache_get( $cache_key, GATHERPRESS_CACHE_GROUP );
		$rsvp_query = Rsvp_Query::get_instance();

		// @todo add testing with cache.
		// @codeCoverageIgnoreStart
		if ( ! empty( $retval ) && is_array( $retval ) ) {
			return $retval;
		}
		// @codeCoverageIgnoreEnd

		$retval = array(
			'all' => array(
				'responses' => array(),
				'count'     => 0,
			),
		);

		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return $retval;
		}

		$data = $rsvp_query->get_rsvps(
			array(
				'post_id' => $post_id,
			)
		);

		$responses  = array();
		$all_guests = 0;
		$statuses   = $this->statuses;

		// `no_status` status is not relevant here.
		$status_key = array_search( 'no_status', $statuses, true );
		unset( $statuses[ $status_key ] );
		$statuses = array_values( $statuses );

		foreach ( $statuses as $status ) {
			$retval[ $status ] = array(
				'responses' => array(),
				'count'     => 0,
			);
		}

		foreach ( $data as $response ) {
			$user_id     = intval( $response->user_id );
			$user_status = '';
			$user_guests = intval( get_comment_meta( $response->comment_ID, 'gatherpress_rsvp_guests', true ) );
			$all_guests += $user_guests;
			$user_info   = get_userdata( $user_id );
			$anonymous   = intval( get_comment_meta( $response->comment_ID, 'gatherpress_rsvp_anonymous', true ) );

			$terms = wp_get_object_terms( $response->comment_ID, self::TAXONOMY );
			if ( ! empty( $terms ) && is_array( $terms ) ) {
				$user_status = $terms[0]->slug;
			}

			// @todo make a filter so we can use this function if gatherpress-buddypress plugin is activated.
			// eg for BuddyPress bp_core_get_user_domain( $user_id )
			$profile = get_author_posts_url( $user_id );

			if (
				empty( $user_info ) ||
				! in_array( $user_status, $statuses, true )
			) {
				continue;
			}

			if (
				! current_user_can( 'edit_posts' ) && ! empty( $anonymous )
			) {
				$user_id = 0;
				$profile = '';

				$user_info->display_name = __( 'Anonymous', 'gatherpress' );
			}

			$responses[] = array(
				'id'        => $user_id,
				'name'      => $user_info->display_name ?? __( 'Anonymous', 'gatherpress' ),
				'photo'     => get_avatar_url( $user_id ),
				'profile'   => $profile,
				'role'      => Leadership::get_instance()->get_user_role( $user_id ),
				'timestamp' => sanitize_text_field( $response->comment_date ),
				'status'    => $user_status,
				'guests'    => $user_guests,
				'anonymous' => $anonymous,
			);
		}

		// Sort before breaking down statuses in return array.
		usort( $responses, array( $this, 'sort_by_role' ) );

		$retval['all']['responses'] = $responses;
		$retval['all']['count']     = count( $retval['all']['responses'] ) + $all_guests;

		foreach ( $statuses as $status ) {
			$retval[ $status ]['responses'] = array_filter(
				$responses,
				static function ( $response ) use ( $status ) {
					return ( $status === $response['status'] );
				}
			);

			$guests = 0;

			foreach ( $retval[ $status ]['responses'] as $response ) {
				$guests += intval( $response['guests'] );
			}

			$retval[ $status ]['responses'] = array_values( $retval[ $status ]['responses'] );
			$retval[ $status ]['count']     = count( $retval[ $status ]['responses'] ) + $guests;
		}

		wp_cache_set( $cache_key, $retval, GATHERPRESS_CACHE_GROUP, 15 * MINUTE_IN_SECONDS );

		return $retval;
	}

	/**
	 * Sort responses by their role.
	 *
	 * This method compares two responses based on their user roles and returns
	 * an integer (-1, 0, or 1) to determine their order in the sorted list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $first  The first response to compare in the sort.
	 * @param array $second The second response to compare in the sort.
	 * @return int An integer indicating the sorting order:
	 *             -1 if $first should come before $second,
	 *              0 if they have the same sorting order,
	 *              1 if $first should come after $second.
	 */
	public function sort_by_role( array $first, array $second ): int {
		$roles       = array_values(
			array_map(
				static function ( $role ) {
					return $role['labels']['singular_name'];
				},
				Leadership::get_instance()->get_user_roles()
			)
		);
		$roles[]     = __( 'Member', 'gatherpress' );
		$first_role  = array_search( $first['role'], $roles, true );
		$second_role = array_search( $second['role'], $roles, true );

		return ( $first_role > $second_role ) ? 1 : -1;
	}

	/**
	 * Sort responses by their RSVP timestamp.
	 *
	 * This method compares two responses based on their RSVP timestamps and is used to sort responses
	 * from the waiting list, with the earliest timestamp responses appearing first.
	 *
	 * @since 1.0.0
	 *
	 * @param array $first  First response to compare in the sort.
	 * @param array $second Second response to compare in the sort.
	 * @return int Returns a negative number if the first response's timestamp is earlier,
	 *             a positive number if the second response's timestamp is earlier,
	 *             or 0 if both are equal.
	 */
	public function sort_by_timestamp( array $first, array $second ): int {
		return strtotime( $first['timestamp'] ) <=> strtotime( $second['timestamp'] );
	}
}
