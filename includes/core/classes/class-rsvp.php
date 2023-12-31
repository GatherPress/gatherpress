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
	 * Table format for RSVPs.
	 *
	 * @since 1.0.0
	 * @var string $TABLE_FORMAT
	 */
	const TABLE_FORMAT = '%sgp_rsvps';

	/**
	 * Cache key format for RSVPs.
	 *
	 * @since 1.0.0
	 * @var string $RSVP_CACHE_KEY
	 */
	const RSVP_CACHE_KEY = 'gp_rsvp_%d';

	/**
	 * An array of RSVP statuses.
	 *
	 * @since 1.0.0
	 * @var string[] Contains RSVP statuses such as 'attending', 'not_attending', and 'waiting_list'.
	 */
	public array $statuses = array(
		'attending',
		'not_attending',
		'waiting_list',
	);

	/**
	 * The maximum limit for attending responses (RSVPs).
	 *
	 * @since 1.0.0
	 * @var int Represents the maximum number of attendees allowed for an event.
	 */
	protected int $max_attending_limit;

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
		$this->event               = get_post( $post_id );
		$this->max_attending_limit = Settings::get_instance()->get_value( 'general', 'general', 'max_attending_limit' );
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
	 * @return array An array containing RSVP information, including ID, post ID, user ID, timestamp, status, and guests.
	 */
	public function get( int $user_id ): array {
		global $wpdb;

		$event_id = $this->event->ID;

		if ( 1 > $event_id || 1 > $user_id ) {
			return array();
		}

		$default = array(
			'id'        => 0,
			'post_id'   => $event_id,
			'user_id'   => $user_id,
			'timestamp' => null,
			'status'    => 'attend',
			'guests'    => 0,
		);

		$table = sprintf( static::TABLE_FORMAT, $wpdb->prefix );

		// @todo Consider implementing caching for improved performance in the future.
		$data = $wpdb->get_row( $wpdb->prepare( 'SELECT id, timestamp, status, guests FROM ' . esc_sql( $table ) . ' WHERE post_id = %d AND user_id = %d', $event_id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return array_merge( $default, (array) $data );
	}

	/**
	 * Save a user's RSVP status for the event.
	 *
	 * This method allows assigning a user's RSVP status for the event. The user can be assigned
	 * one of the following RSVP statuses: 'attending', 'not_attending', or 'waiting_list', and
	 * optionally specify the number of guests accompanying them. The method handles the storage
	 * of this information in the database and updates the RSVP status accordingly.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id A user ID.
	 * @param string $status  RSVP status ('attending', 'not_attending', 'waiting_list').
	 * @param int    $guests  Number of guests accompanying the user.
	 * @return string The updated RSVP status ('attending', 'not_attending', 'waiting_list').
	 */
	public function save( int $user_id, string $status, int $guests = 0 ): string {
		global $wpdb;

		$event_id = $this->event->ID;

		$retval = '';

		if ( 1 > $event_id || 1 > $user_id ) {
			return $retval;
		}

		if ( ! in_array( $status, $this->statuses, true ) ) {
			return $retval;
		}

		$table         = sprintf( static::TABLE_FORMAT, $wpdb->prefix );
		$response      = $this->get( $user_id );
		$limit_reached = ( 'attending' === $status && $this->attending_limit_reached() );

		if ( $limit_reached && ! $guests ) {
			$status = 'waiting_list';
		}

		$data = array(
			'post_id'   => intval( $event_id ),
			'user_id'   => intval( $user_id ),
			'timestamp' => gmdate( 'Y-m-d H:i:s' ),
			'status'    => sanitize_key( $status ),
			'guests'    => intval( $guests ),
		);

		if ( intval( $response['id'] ) ) {
			$where = array(
				'id' => intval( $response['id'] ),
			);
			$save  = $wpdb->update( $table, $data, $where ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} else {
			$save = $wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		wp_cache_delete( sprintf( self::RSVP_CACHE_KEY, $event_id ) );

		if ( $save ) {
			$retval = sanitize_key( $status );
		}

		if ( ! $limit_reached && 'not_attending' === $status ) {
			$this->check_waiting_list();
		}

		return $retval;
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
			intval( $responses['attending']['count'] ) < $this->max_attending_limit
			&& intval( $responses['waiting_list']['count'] )
		) {
			$waiting_list = $responses['waiting_list']['responses'];

			// People who are longest on the waiting_list should be added first.
			usort( $waiting_list, array( $this, 'sort_by_timestamp' ) );

			$total = $this->max_attending_limit - intval( $responses['attending']['count'] );

			while ( $i < $total ) {
				// Check that we have enough on the waiting_list to run this.
				if ( ( $i + 1 ) > intval( $responses['waiting_list']['count'] ) ) {
					break;
				}

				$response = $waiting_list[ $i ];
				$this->save( $response['id'], 'attending' );
				$i++;
			}
		}

		return $i;
	}

	/**
	 * Check if the attending limit has been reached for an event.
	 *
	 * This method determines whether the maximum response limit for the 'attending' status
	 * has been reached for the event. It checks the current number of 'attending' responses
	 * and compares it to the defined limit.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the 'attending' limit has been reached, false otherwise.
	 */
	public function attending_limit_reached(): bool {
		$responses = $this->responses();

		if (
			! empty( $responses['attending'] )
			&& intval( $responses['attending']['count'] ) >= $this->max_attending_limit
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
		global $wpdb;

		$event_id = $this->event->ID;

		$cache_key = sprintf( self::RSVP_CACHE_KEY, $event_id );
		$retval    = wp_cache_get( $cache_key );

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

		if ( Event::POST_TYPE !== get_post_type( $event_id ) ) {
			return $retval;
		}

		$site_users  = count_users();
		$total_users = $site_users['total_users'];
		$table       = sprintf( static::TABLE_FORMAT, $wpdb->prefix );
		$data        = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, timestamp, status, guests FROM ' . esc_sql( $table ) . ' WHERE post_id = %d LIMIT %d', $event_id, $total_users ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$data        = ( ! empty( $data ) ) ? (array) $data : array();
		$responses   = array();
		$all_guests  = 0;

		foreach ( $this->statuses as $status ) {
			$retval[ $status ] = array(
				'responses' => array(),
				'count'     => 0,
			);
		}

		foreach ( $data as $response ) {
			// @todo Currently, the number of guests for attending response is set to 0 as this feature is not yet available.
			// We plan to implement this feature in a future version of GatherPress.
			$response['guests'] = 0;

			$user_id     = intval( $response['user_id'] );
			$user_status = sanitize_key( $response['status'] );
			$user_guests = intval( $response['guests'] );
			$all_guests += $user_guests;
			$user_info   = get_userdata( $user_id );

			if (
				empty( $user_info ) ||
				! in_array( $user_status, $this->statuses, true )
			) {
				continue;
			}

			$responses[] = array(
				'id'        => $user_id,
				'name'      => $user_info->display_name ?? __( 'Anonymous', 'gatherpress' ),
				'photo'     => get_avatar_url( $user_id ),
				// @todo make a filter so we can use this function if gp-buddypress plugin is activated.
				// 'profile'   => bp_core_get_user_domain( $user_id ),
				'profile'   => get_author_posts_url( $user_id ),
				'role'      => Leadership::get_instance()->get_user_role( $user_id ),
				'timestamp' => sanitize_text_field( $response['timestamp'] ),
				'status'    => $user_status,
				'guests'    => $user_guests,
			);
		}

		// Sort before breaking down statuses in return array.
		usort( $responses, array( $this, 'sort_by_role' ) );

		$retval['all']['responses'] = $responses;
		$retval['all']['count']     = count( $retval['all']['responses'] ) + $all_guests;

		foreach ( $this->statuses as $status ) {
			$retval[ $status ]['responses'] = array_filter(
				$responses,
				function( $response ) use ( $status ) {
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

		wp_cache_set( $cache_key, $retval, 15 * MINUTE_IN_SECONDS );

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
				function( $role ) {
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
	 * @return bool True if the first response's timestamp is earlier than the second response's timestamp; otherwise, false.
	 */
	public function sort_by_timestamp( array $first, array $second ): bool {
		return ( strtotime( $first['timestamp'] ) < strtotime( $second['timestamp'] ) );
	}
}
