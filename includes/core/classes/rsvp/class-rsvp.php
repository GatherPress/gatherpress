<?php
/**
 * Manages RSVP related functionality for events.
 *
 * This class is responsible for handling all operations related to RSVPs for events, including
 * retrieving RSVP information, saving RSVPs, checking attending limits, and more.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Type\Base as Base_Rsvp_Type;
use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Roles;
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
	 * Capability required to manage RSVPs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CAPABILITY = 'moderate_comments';

	/**
	 * Comment type for RSVPs.
	 *
	 * @since 1.0.0
	 * @var string $COMMENT_TYPE
	 */
	const COMMENT_TYPE = 'gatherpress_rsvp';

	/**
	 * Default response for calling the save function.
	 *
	 * @var array
	 */
	const DEFAULT_SAVE_RESPONSE = array(
		'comment_id' => 0,
		'post_id'    => 0,
		'user_id'    => 0,
		'timestamp'  => '0000-00-00 00:00:00',
		'status'     => 'no_status',
		'guests'     => 0,
		'anonymous'  => 0,
	);

	/**
	 * The maximum limit for attending responses (RSVPs).
	 *
	 * @var int Represents the maximum number of attendees allowed for an event.
	 */
	protected int $max_attendance_limit;

	/**
	 * The event post object associated with this RSVP instance.
	 *
	 * @var WP_Post|null
	 */
	protected $event;

	/**
	 * Request based cache for whether the attending limit is reached.
	 *
	 * @var boolean
	 */
	protected $attending_limit_reached = false;

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
		$this->max_attendance_limit = \intval( get_post_meta( $post_id, 'gatherpress_max_attendance_limit', true ) );
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
	 * @param mixed  $identifier The identifier of the RSVP.
	 * @param string $rsvp_type  The RSVP type key.
	 *
	 * @return array An array containing RSVP information.
	 */
	public function get( $identifier, string $rsvp_type = 'user' ): array {
		if ( 'user' === $rsvp_type && is_email( $identifier ) ) {
			$rsvp_type = 'email';
		}

		$post_id    = $this->event->ID ?? 0;
		$rsvp_query = Query::get_instance();
		$rsvp_type  = Manager::get_type( $rsvp_type );

		if ( 1 > $post_id || ( empty( $identifier ) ) || null === $rsvp_type ) {
			return array();
		}

		$data = array(
			'comment_id' => 0,
			'post_id'    => $post_id,
			'timestamp'  => null,
			'status'     => 'no_status',
			'guests'     => 0,
			'anonymous'  => 0,
		);

		$args = array(
			'post_id' => $post_id,
			'status'  => 'approve',
		);

		$args = $rsvp_type->filter_query_get( $args, $identifier );
		$rsvp = $rsvp_query->get_rsvp( $args );

		if ( ! empty( $rsvp ) ) {
			$data['comment_id'] = $rsvp->comment_ID;
			$data['user_id']    = $rsvp->user_id;
			$data['timestamp']  = $rsvp->comment_date;
			$data['anonymous']  = \intval(
				get_comment_meta( \intval( $rsvp->comment_ID ), 'gatherpress_rsvp_anonymous', true )
			);
			$data['guests']     = \intval(
				get_comment_meta( \intval( $rsvp->comment_ID ), 'gatherpress_rsvp_guests', true )
			);

			$terms = wp_get_object_terms( \intval( $rsvp->comment_ID ), Status::TAXONOMY );

			if ( ! empty( $terms ) && \is_array( $terms ) ) {
				$data['status'] = $terms[0]->slug;
			}

			$terms = wp_get_object_terms( \intval( $rsvp->comment_ID ), Base_Rsvp_Type::TAXONOMY );

			if ( ! empty( $terms ) && \is_array( $terms ) ) {
				$data['type'] = $terms[0]->slug;
			}
		}

		return $data;
	}

	/**
	 * Determines whether RSVP is enabled for this event.
	 *
	 * Returns false immediately when the sitewide mode is `disabled`.
	 * Returns true when the mode is `all_on` (every event has RSVP).
	 * In per-event modes (`per_event_on` or `per_event_off`), the
	 * `gatherpress_enable_rsvp` post meta is consulted. An unset meta
	 * (empty string) falls back to the mode default: `per_event_on`
	 * defaults to enabled, `per_event_off` defaults to disabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if RSVP is enabled for this event, false otherwise.
	 */
	public function is_enabled(): bool {
		$post_id   = $this->event->ID ?? 0;
		$rsvp_mode = Settings::get_instance()->get( 'rsvp_mode' );

		if ( 'disabled' === $rsvp_mode ) {
			return false;
		}

		if ( ! \in_array( $rsvp_mode, array( 'per_event_on', 'per_event_off' ), true ) ) {
			return true;
		}

		$meta = get_post_meta( $post_id, 'gatherpress_enable_rsvp', true );

		// Empty meta falls back to the mode default.
		if ( '' === $meta ) {
			return 'per_event_on' === $rsvp_mode;
		}

		return '0' !== $meta;
	}

	/**
	 * Determines whether Open RSVP (email/token, non-logged-in) is enabled for this event.
	 *
	 * Returns false immediately if the sitewide `enable_open_rsvp` setting is off.
	 * When sitewide is on, consults the per-event `gatherpress_enable_open_rsvp` post meta.
	 * An unset meta (empty string) is treated as enabled (the default).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if Open RSVP is enabled for this event, false otherwise.
	 */
	public function allows_open_rsvp(): bool {
		$post_id = $this->event->ID ?? 0;

		// Sitewide gate: if open RSVP is globally disabled, always return false.
		if ( ! Settings::get_instance()->get( 'enable_open_rsvp' ) ) {
			return false;
		}

		// Per-event override; stored as integer (1 = enabled, 0 = disabled).
		$meta = get_post_meta( $post_id, 'gatherpress_enable_open_rsvp', true );

		// Not explicitly set defaults to enabled.
		if ( '' === $meta ) {
			return true;
		}

		return '0' !== (string) $meta;
	}

	/**
	 * Writes an explicit enabled value on first save, based on the active RSVP mode.
	 *
	 * Ensures that programmatically created events (e.g. via WP-CLI or imports)
	 * carry predictable meta regardless of which mode was active at creation time:
	 * - `all_on`: writes meta = 1 so switching to a per-event mode later is safe.
	 * - `per_event_on`: writes meta = 1 (default-on intent).
	 * - `per_event_off`: writes meta = 0 (default-off intent).
	 * - `disabled`: no meta is written.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function initialize_enabled(): void {
		$post_id   = $this->event->ID ?? 0;
		$rsvp_mode = Settings::get_instance()->get( 'rsvp_mode' );

		if ( 'disabled' === $rsvp_mode ) {
			return;
		}

		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-rsvp' ) ) {
			return;
		}

		// Only write if meta has never been explicitly set.
		if ( '' !== get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ) ) {
			return;
		}

		$default_value = ( 'per_event_off' === $rsvp_mode ) ? 0 : 1;
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', $default_value );
	}

	/**
	 * Saves a user's RSVP status for an event.
	 *
	 * Allows assigning one of the specified RSVP statuses to a user for an event. The user can be marked
	 * as 'attending', 'not_attending', or placed on a 'waiting_list'. Additionally, users can specify
	 * the number of guests they plan to bring along and whether their RSVP should be considered anonymous.
	 * This method updates the database accordingly to reflect the new RSVP status.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $identifier      Identifier of the person whose RSVP status is being updated.
	 * @param string     $status          The new RSVP status for the user. Acceptable values are 'attending',
	 *                                    'not_attending', or 'waiting_list'.
	 * @param int|null   $anonymous       Optional. Whether the RSVP is to be marked as anonymous.
	 *                                    Accepts 1 for true (anonymous) and 0 for false (not anonymous). Default 0.
	 * @param int|null   $guests          Optional. The number of guests the user plans to bring along. Default 0.
	 * @param string     $rsvp_type            The RSVP Type.
	 *
	 * @return array Associative array containing the event ID ('post_id'), user ID ('user_id'),
	 *               RSVP timestamp ('timestamp'), RSVP status ('status'), number of guests ('guests'),
	 *               and anonymity flag ('anonymous'). Returns a default array with 'post_id' and 'user_id'
	 *               set to 0, 'timestamp' to '0000-00-00 00:00:00', 'status' to 'no_status', 'guests' to 0,
	 *               and 'anonymous' to 0 if the post ID or user identifier is not valid, or if the status
	 *               is not one of the acceptable values. If the attending limit is reached, 'status' may be
	 *               automatically set to 'waiting_list', and 'guests' to 0, depending on the context.
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 */
	public function save(
		mixed $identifier,
		string $status,
		?int $anonymous = 0,
		?int $guests = 0,
		string $rsvp_type = 'user'
	): array {
		if ( 'user' === $rsvp_type && is_email( $identifier ) ) {
			$rsvp_type = 'email';
		}

		$status = Status::tryFrom( $status );

		if ( null === $status ) {
			return self::DEFAULT_SAVE_RESPONSE;
		}

		$request = new Request(
			$rsvp_type,
			$identifier,
			$status,
			$guests,
			$anonymous,
		);

		return $this->save_request( $request );
	}

	/**
	 * Process an RSVP request.
	 *
	 * @param Request $request The RSVP Request.
	 * @return array
	 */
	public function save_request( Request $request ): array {
		// If no valid event or RSVP is disabled for this event return empty default response.
		if ( 1 > $this->event->ID || ! $this->is_enabled() || ! Manager::is_valid_request( $request ) ) {
			return self::DEFAULT_SAVE_RESPONSE;
		}

		// Get current/prior RSVP response.
		$current_response = $this->get( $request->identifier, $request->type );

		// Apply business logic for RSVP requests.
		$request = $this->constrain_rsvp_request( $request, $current_response );

		// Persist RSVP comment: Create new RSVP-comment, Update existing one, or delete on invalid status.
		$current_comment_id = max( 0, (int) ( $current_response['commit_id'] ?? 0 ) );
		$comment_id         = $this->persist( $request, $current_comment_id );

		if ( null === $comment_id ) {
			return self::DEFAULT_SAVE_RESPONSE;
		}

		if ( ! $this->attending_limit_reached ) {
			$this->check_waiting_list();
		}

		return array(
			'comment_id' => \intval( $comment_id ),
			'post_id'    => \intval( $this->event->ID ),
			'identifier' => $request->identifier,
			'type'       => $request->type,
			'timestamp'  => gmdate( 'Y-m-d H:i:s' ),
			'status'     => sanitize_key( $request->status->value ),
			'guests'     => \intval( $request->guests ),
			'anonymous'  => \intval( $request->anonymous ),
		);
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
		$responses          = $this->responses();
		$attending_count    = \intval( $responses['attending']['count'] );
		$waiting_list_count = \intval( $responses['waiting_list']['count'] );
		$i                  = 0;

		if (
			$waiting_list_count &&
			(
				empty( $this->max_attendance_limit ) ||
				$attending_count < $this->max_attendance_limit
			)
		) {
			$waiting_list = $responses['waiting_list']['records'];

			// People who are longest on the waiting_list should be added first.
			usort( $waiting_list, array( $this, 'sort_by_timestamp' ) );

			if ( ! empty( $this->max_attendance_limit ) ) {
				$total = $this->max_attendance_limit - intval( $responses['attending']['count'] );
			} else {
				$total = $waiting_list_count;
			}

			while ( $i < $total ) {
				// Check that we have enough on the waiting_list to run this.
				if ( ( $i + 1 ) > intval( $responses['waiting_list']['count'] ) ) {
					break;
				}

				$response = $waiting_list[ $i ];

				$this->save(
					$response['identifier'],
					Status::ATTENDING->value,
					$response['anonymous'],
					$response['guests'],
					$response['type']
				);

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

		return (
			! empty( $responses['attending'] ) &&
			intval( $responses['attending']['count'] ) + $user_count + $guests > $this->max_attendance_limit
		);
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
		$post_id = $this->event->ID;
		$retval  = Cache::get( $post_id );

		// @todo add testing with cache.
		// @codeCoverageIgnoreStart
		if ( $retval ) {
			return $retval;
		}
		// @codeCoverageIgnoreEnd

		$retval = array(
			'all' => array(
				'records' => array(),
				'count'   => 0,
			),
		);

		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-rsvp' ) ) {
			return $retval;
		}

		$data = Query::get_instance()->get_rsvps(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
			)
		);

		$records    = array();
		$all_guests = 0;
		$statuses   = Status::values();

		// `no_status` status is not relevant here.
		$status_key = array_search( 'no_status', $statuses, true );
		unset( $statuses[ $status_key ] );
		$statuses = array_values( $statuses );

		foreach ( $statuses as $status ) {
			$retval[ $status ] = array(
				'records' => array(),
				'count'   => 0,
			);
		}

		foreach ( $data as $record ) {
			$comment_id   = intval( $record->comment_ID );
			$user_id      = intval( $record->user_id );
			$user_status  = '';
			$user_guests  = intval( get_comment_meta( $record->comment_ID, 'gatherpress_rsvp_guests', true ) );
			$all_guests  += $user_guests;
			$user_info    = false;
			$anonymous    = intval( get_comment_meta( $record->comment_ID, 'gatherpress_rsvp_anonymous', true ) );
			$terms        = wp_get_object_terms( $record->comment_ID, Status::TAXONOMY );
			$display_name = $record->comment_author;
			$profile      = '';

			if ( ! empty( $terms ) && is_array( $terms ) ) {
				$user_status = $terms[0]->slug;
			}

			$terms = wp_get_object_terms( $record->comment_ID, Base_Rsvp_Type::TAXONOMY );

			if ( ! empty( $terms ) && is_array( $terms ) ) {
				$rsvp_type = $terms[0]->slug;
			}

			if ( ! empty( $user_id ) ) {
				$user_info = get_userdata( $user_id );

				if ( ! empty( $user_info ) ) {
					// @todo make a filter so we can use this function if gatherpress-buddypress plugin is activated.
					// eg for BuddyPress bp_core_get_user_domain( $user_id )
					$profile      = get_author_posts_url( $user_id );
					$display_name = $user_info->display_name;
				}
			}

			if (
				( $user_id && empty( $user_info ) ) ||
				! in_array( $user_status, $statuses, true )
			) {
				continue;
			}

			if (
				! current_user_can( 'edit_posts' ) && ! empty( $anonymous )
			) {
				$user_id = 0;
				$profile = '';

				$display_name = __( 'Anonymous', 'gatherpress' );
			}

			$records[] = array(
				'userId'    => $user_id,
				'commentId' => $comment_id,
				'name'      => $display_name ? $display_name : __( 'Anonymous', 'gatherpress' ),
				'photo'     => get_avatar_url( $record ),
				'profile'   => $profile,
				'role'      => Roles::get_instance()->get_user_role( $user_id ),
				'timestamp' => sanitize_text_field( $record->comment_date ),
				'status'    => $user_status,
				'guests'    => $user_guests,
				'anonymous' => $anonymous,
				'type'      => $rsvp_type,
			);
		}

		// Sort before breaking down statuses in return array.
		usort( $records, array( $this, 'sort_by_role' ) );

		$retval['all']['records'] = $records;
		$retval['all']['count']   = count( $retval['all']['records'] ) + $all_guests;

		foreach ( $statuses as $status ) {
			$retval[ $status ]['records'] = array_filter(
				$records,
				static function ( $record ) use ( $status ) {
					return ( $status === $record['status'] );
				}
			);

			$guests = 0;

			foreach ( $retval[ $status ]['records'] as $record ) {
				$guests += intval( $record['guests'] );
			}

			$retval[ $status ]['records'] = array_values( $retval[ $status ]['records'] );
			$retval[ $status ]['count']   = count( $retval[ $status ]['records'] ) + $guests;
		}

		Cache::set( $post_id, $retval );

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
				Roles::get_instance()->get_user_roles()
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

	/**
	 * Persist a RSVP comment.
	 *
	 * @param Request $request             The RSVP request.
	 * @param int     $current_comment_id  The current RSVP comment ID (in case of an update).
	 */
	private function persist( Request $request, $current_comment_id = 0 ) {
		$post_id = $this->event->ID;

		$args = array(
			'comment_post_ID'   => $post_id,
			'comment_author_IP' => '127.0.0.1',
			'comment_type'      => self::COMMENT_TYPE,
		);

		$args = Manager::filter_comment_query( $request, $args );

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			if ( rest_is_ip_address( $remote_ip ) ) {
				$args['comment_author_IP'] = $remote_ip;
			}
		}

		if ( ! $current_comment_id ) {
			// Ensure keys that wp_filter_comment accesses without isset() are present.
			$args = array_merge(
				array(
					'comment_author'       => '',
					'comment_author_email' => '',
					'comment_author_url'   => '',
					'comment_author_IP'    => '127.0.0.1',
					'comment_content'      => '',
				),
				$args
			);

			// Run WordPress-native comment filters so sites can honor
			// pre_comment_user_ip, pre_comment_user_agent, etc. for privacy.
			$args       = wp_filter_comment( $args );
			$comment_id = wp_insert_comment( $args );
		} else {
			$comment_id               = $current_comment_id;
			$args['comment_ID']       = $comment_id;
			$args['comment_approved'] = 1;

			wp_update_comment( $args );
		}

		if ( empty( $comment_id ) ) {
			return null;
		}

		// If status is 'no_status', remove the record.
		if ( Status::NO_STATUS === $request->status ) {
			wp_delete_comment( $comment_id, true );

			Cache::delete( $post_id );

			return null;
		}

		wp_set_object_terms( $comment_id, $request->status->value, Status::TAXONOMY );
		wp_set_object_terms( $comment_id, $request->type, Base_Rsvp_Type::TAXONOMY );

		if ( $request->has_guests() ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_guests', $request->guests );
		} else {
			delete_comment_meta( $comment_id, 'gatherpress_rsvp_guests' );
		}

		if ( $request->is_anonymous() ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', 1 );
		} else {
			delete_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous' );
		}

		Cache::delete( $post_id );

		return $comment_id;
	}

	/**
	 * Applies business rules to a request.
	 *
	 * @param Request $request          The raw RSVP request.
	 * @param array   $current_response The prior/current response.
	 * @return Request
	 */
	private function constrain_rsvp_request( Request $request, array $current_response ): Request {
		$max_guest_limit = \intval( get_post_meta( $this->event->ID, 'gatherpress_max_guest_limit', true ) );

		if ( $max_guest_limit < $request->guests ) {
			$request->guests = $max_guest_limit;
		}

		// Check if anonymous RSVP is enabled for this event.
		$enable_anonymous_rsvp = get_post_meta( $this->event->ID, 'gatherpress_enable_anonymous_rsvp', true );
		if ( ! $enable_anonymous_rsvp ) {
			$request->anonymous = false;
		}

		// Constrain based on prior/current response.
		$limit_reached = $this->attending_limit_reached( $current_response, $request->guests );

		$this->attending_limit_reached = $limit_reached;

		if ( Status::ATTENDING === $request->status && $limit_reached ) {
			$request->guests = $current_response['guests'];
		}

		if (
			\in_array( $request->status, array( Status::ATTENDING, Status::WAITING_LIST ), true ) &&
			'attending' !== $current_response['status'] &&
			$limit_reached
		) {
			$request->status = Status::WAITING_LIST;
		}

		if ( Status::WAITING_LIST === $request->status ) {
			$request->guests = 0;
		}

		return $request;
	}
}
