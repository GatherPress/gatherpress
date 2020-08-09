<?php

namespace GatherPress\Inc;

use GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Attendee {

	use Singleton;

	const TABLE_FORMAT       = '%sgp_attendees';
	const ATTENDEE_CACHE_KEY = 'attendee_%d';

	public $statuses = [
		'attending',
		'not_attending',
		'waitlist',
	];

	// @todo temporary limit. Configuration coming in ticket https://github.com/mauteri/gatherpress/issues/56
	public $limit = 3;

	/**
	 * Query constructor.
	 */
	protected function __construct() {

		$this->_setup_hooks();

	}

	/**
	 * Setup hooks.
	 */
	protected function _setup_hooks() : void {

		add_action( 'init', [ $this, 'maybe_create_custom_table' ] );

	}

	/**
	 * Maybe create custom table if doesn't exist for main site or current site in network.
	 */
	public function maybe_create_custom_table() : void {

		$this->create_table();

		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();

			switch_to_blog( $blog_id );
			$this->create_table();
			restore_current_blog();
		}

	}

	/**
	 * Create custom attendees table.
	 */
	public function create_table() : void {

		global $wpdb;

		$sql             = [];
		$charset_collate = $GLOBALS['wpdb']->get_charset_collate();
		$table           = sprintf( static::TABLE_FORMAT, $wpdb->prefix );

		$sql[] = "CREATE TABLE {$table} (
					id bigint(20) unsigned NOT NULL auto_increment,
					post_id bigint(20) unsigned NOT NULL default '0',
					user_id bigint(20) unsigned NOT NULL default '0',
					timestamp datetime NOT NULL default '0000-00-00 00:00:00',
					status varchar(255) default NULL,
					PRIMARY KEY  (id),
					KEY post_id (post_id),
					KEY user_id (user_id),
					KEY status (status)
				) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );

	}

	/**
	 * Get an event attendee.
	 *
	 * @param int $post_id
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function get_attendee( int $post_id, int $user_id ) : array {

		global $wpdb;

		if ( 1 > $post_id || 1 > $user_id ) {
			return [];
		}

		$table = sprintf( static::TABLE_FORMAT, $wpdb->prefix );
		$data  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d AND user_id = %d", $post_id, $user_id ), ARRAY_A );

		return (array) $data;

	}

	/**
	 * Save an event attendee.
	 *
	 * @param int    $post_id
	 * @param int    $user_id
	 * @param string $status
	 *
	 * @return string
	 */
	public function save_attendee( int $post_id, int $user_id, string $status ) : string {

		global $wpdb;

		$retval = '';

		if ( 1 > $post_id || 1 > $user_id ) {
			return $retval;
		}

		if ( ! in_array( $status, $this->statuses, true ) ) {
			return $retval;
		}

		$table         = sprintf( static::TABLE_FORMAT, $wpdb->prefix );
		$attendee      = $this->get_attendee( $post_id, $user_id );
		$limit_reached = $this->attending_limit_reached( $post_id, $status );

		if ( $limit_reached ) {
			$status = 'waitlist';
		}

		$data = [
			'post_id'   => intval( $post_id ),
			'user_id'   => intval( $user_id ),
			'timestamp' => date( 'Y-m-d H:i:s' ),
			'status'    => sanitize_key( $status ),
		];

		if ( ! empty( $attendee ) ) {
			if ( 1 > intval( $attendee['id'] ) ) {
				return $retval;
			}

			$where = [
				'id' => intval( $attendee['id'] ),
			];
			$save = $wpdb->update( $table, $data, $where );
		} else {
			$save = $wpdb->insert( $table, $data );
		}

		wp_cache_delete( sprintf( self::ATTENDEE_CACHE_KEY, $post_id ) );

		if ( $save ) {
			$retval = sanitize_key( $status );
		}

		if ( ! $limit_reached && 'not_attending' === $status ) {
			$this->check_waitlist( $post_id );
		}

		return $retval;

	}

	/**
	 * Check the waitlist and maybe move attendees to attending.
	 *
	 * @param int $post_id
	 *
	 * @return int  Number of attendees from waitlist that were moved to attending.
	 */
	public function check_waitlist( int $post_id ) : int {

		$attendees = $this->get_attendees( $post_id );
		$total     = 0;

		if (
			intval( $attendees['attending']['count'] ) < $this->limit
			&& intval( $attendees['waitlist']['count'] )
		) {
			$waitlist  = $attendees['waitlist']['attendees'];

			// People longest on the waitlist should be added first.
			usort( $waitlist, [ $this, 'sort_attendees_by_timestamp' ] );

			$total = $this->limit - intval( $attendees['attending']['count'] );
			$i     = 0;

			while ( $i < $total ) {
				// Check that we have enough on the waitlist to run this.
				if ( ( $i + 1 ) > intval( $attendees['waitlist']['count'] ) ) {
					break;
				}

				$attendee = $waitlist[ $i ];
				$this->save_attendee( $post_id, $attendee['id'], 'attending' );
				$i++;
			}
		}

		return intval( $total );

	}

	/**
	 * Check if the attending limit has been reached for an event.
	 *
	 * @param int    $post_id
	 * @param string $status
	 *
	 * @return bool
	 */
	public function attending_limit_reached( int $post_id, string $status ) : bool {

		$attendees = $this->get_attendees( $post_id );

		if (
			! empty( $attendees['attending'] )
			&& intval( $attendees['attending']['count'] ) >= $this->limit
			&& 'attending' === $status
		) {
			return true;
		}

		return false;

	}

	/**
	 * Get all attendees for an event.
	 *
	 * @param int $post_id
	 *
	 * @return array
	 */
	public function get_attendees( int $post_id ) : array {

		global $wpdb;

		$cache_key = sprintf( self::ATTENDEE_CACHE_KEY, $post_id );
		$retval    = wp_cache_get( $cache_key );

		if ( ! empty( $retval ) && is_array( $retval ) ) {
			return $retval;
		}

		$retval = [
			'all' => [
				'attendees' => [],
				'count'     => 0,
			],
		];

		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return $retval;
		}

		$site_users  = count_users();
		$total_users = $site_users['total_users'];
		$table       = sprintf( static::TABLE_FORMAT, $wpdb->prefix );
		$data        = (array) $wpdb->get_results( $wpdb->prepare( "SELECT user_id, timestamp, status FROM {$table} WHERE post_id = %d LIMIT %d", $post_id, $total_users ), ARRAY_A );
		$data        = ( ! empty( $data ) ) ? (array) $data : [];
		$attendees   = [];

		foreach ( $this->statuses as $status ) {
			$retval[ $status ] = [
				'attendees' => [],
				'count'     => 0,
			];
		}

		foreach ( $data as $attendee ) {
			$user_id     = intval( $attendee['user_id'] );
			$user_status = sanitize_key( $attendee['status'] );

			if ( 1 > $user_id ) {
				continue;
			}

			if ( ! in_array( $user_status, $this->statuses, true ) ) {
				continue;
			}

			$user_info = get_userdata( $user_id );
//			$roles     = Role::get_instance()->get_role_names();
			$attendees[] = [
				'id'        => $user_id,
				'name'      => $user_info->display_name,
				'photo'     => get_avatar_url( $user_id ),
				'profile'   => bp_core_get_user_domain( $user_id ),
				'role'      => '', //$roles[ current( $user_info->roles ) ] ?? '',
				'timestamp' => sanitize_text_field( $attendee['timestamp'] ),
				'status'    => $user_status,
			];

		}

		// Sort before breaking down statuses in return array.
//		usort( $attendees, [ $this, 'sort_attendees_by_role' ] );

		$retval['all']['attendees'] = $attendees;
		$retval['all']['count']     = count( $retval['all']['attendees'] );

		foreach ( $this->statuses as $status ) {
			$retval[ $status ]['attendees'] = array_filter( $attendees, function( $attendee ) use ( $status ) {
				return ( $status === $attendee['status'] );
			});

			$retval[ $status ]['attendees'] = array_values( $retval[ $status ]['attendees'] );
			$retval[ $status ]['count']     = count( $retval[ $status ]['attendees'] );
		}

		wp_cache_set( $cache_key, $retval, 15 * MINUTE_IN_SECONDS );

		return $retval;

	}

	/**
	 * Sort attendees by their role.
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return bool
	 */
	public function sort_attendees_by_role( array $a, array $b ) : bool {

		$roles = array_values( Role::get_instance()->get_role_names() );

		return ( array_search( $a['role'], $roles ) > array_search( $b['role'], $roles ) );

	}

	/**
	 * Sort attendees by earliest timestamp.
	 *
	 * @param array $a
	 * @param array $b
	 *
	 * @return bool
	 */
	public function sort_attendees_by_timestamp( array $a, array $b ) : bool {

		return ( strtotime( $a['timestamp'] ) < strtotime( $b['timestamp'] ) );

	}

}

// EOF
