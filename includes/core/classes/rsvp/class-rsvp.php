<?php
/**
 * Manages RSVP related functionality for events.
 *
 * This class is responsible for handling all operations related to RSVPs for events, including
 * retrieving RSVP information, saving RSVPs, checking attending limits, and more.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.27.0
 */

namespace GatherPress\Core\Rsvp;

use GatherPress\Core\Rsvp\Response\Collection;
use GatherPress\Core\Rsvp\Response\Serializer;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Intent;
use GatherPress\Core\Rsvp\Response\Provider_Registry;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Roles;
use WP_Comment;
use WP_Post;

/**
 * Class Rsvp.
 *
 * Manages RSVP functionality for events, including response status tracking and limits.
 *
 * @since 0.34.0
 */
class Rsvp {
	/**
	 * Capability required to manage RSVPs.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	public const CAPABILITY = 'moderate_comments';

	/**
	 * Comment type for RSVPs.
	 *
	 * @since 0.34.0
	 * @var string $COMMENT_TYPE
	 */
	public const COMMENT_TYPE = 'gatherpress_rsvp';

	/**
	 * Default response for calling the save function.
	 *
	 * @since 0.35.0
	 * @var array
	 */
	private const DEFAULT_SAVE_RESPONSE = array(
		'comment_id' => 0,
		'post_id'    => 0,
		'user_id'    => 0,
		'timestamp'  => '0000-00-00 00:00:00',
		'status'     => 'no_status',
		'guests'     => 0,
		'anonymous'  => 0,
	);

	/**
	 * The maximum limit for attendees for this Event (including guests).
	 *
	 * @since 0.34.0
	 * @var int Represents the maximum number of attendees allowed for an event.
	 */
	protected int $max_attendance_limit;

	/**
	 * The event post object associated with this RSVP instance.
	 *
	 * @since 0.34.0
	 * @var WP_Post
	 */
	protected readonly WP_Post $event;

	/**
	 * Storage for RSVP Responses for this event.
	 *
	 * @var Storage
	 */
	protected readonly Storage $storage;

	/**
	 * List of all RSVP providers.
	 *
	 * @var Provider[]
	 */
	protected readonly array $providers;

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
	 * @since 0.34.0
	 *
	 * @param int $post_id The event post ID.
	 */
	public function __construct( int $post_id ) {
		$this->event                = get_post( $post_id );
		$this->storage              = new Storage( $post_id );
		$this->max_attendance_limit = (int) get_post_meta( $post_id, 'gatherpress_max_attendance_limit', true );
		$this->providers            = Provider_Registry::get_instance()->get_all();
	}

	/**
	 * Get RSVP information for a user and an event.
	 *
	 * This method retrieves RSVP information for a specific user and event, including the RSVP entry's ID,
	 * associated post ID, user ID, timestamp, RSVP status ('attending', 'not_attending', or 'waiting_list'),
	 * and the number of guests accompanying the user. If no RSVP information is found for the user and event,
	 * default values are provided.
	 *
	 * @since 0.34.0
	 *
	 * @param mixed $identifier The identifier of the RSVP.
	 *
	 * @return array|null An array containing RSVP information.
	 */
	public function get( $identifier ): array|null {
		$identity = $this->resolve_identity( $identifier );

		if ( null === $identity ) {
			return null;
		}

		$provider = $this->resolve_provider( $identity );
		$state    = $this->storage->get( $identity, $provider );

		return $state ? Serializer::to_array( $state ) : null;
	}

	/**
	 * Get RSVP information for a user and an event.
	 *
	 * @since 0.34.0
	 *
	 * @param mixed $identifier The identifier of the RSVP.
	 *
	 * @return State|null An array containing RSVP information.
	 */
	public function find( $identifier ): State|null {
		$identity = $this->resolve_identity( $identifier );

		if ( null === $identity ) {
			return null;
		}

		$provider = $this->resolve_provider( $identity );
		$state    = $this->storage->get( $identity, $provider );

		return $state;
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
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @param int|string $identifier      Identifier of the person whose RSVP status is being updated.
	 * @param string     $status          The new RSVP status for the user. Acceptable values are 'attending',
	 *                                    'not_attending', or 'waiting_list'.
	 * @param int        $anonymous       Optional. Whether the RSVP is to be marked as anonymous.
	 *                                    Accepts 1 for true (anonymous) and 0 for false (not anonymous). Default 0.
	 * @param int        $guests          Optional. The number of guests the user plans to bring along. Default 0.
	 *
	 * @return array Associative array containing the event ID ('post_id'), user ID ('user_id'),
	 *               RSVP timestamp ('timestamp'), RSVP status ('status'), number of guests ('guests'),
	 *               and anonymity flag ('anonymous'). Returns a default array with 'post_id' and 'user_id'
	 *               set to 0, 'timestamp' to '0000-00-00 00:00:00', 'status' to 'no_status', 'guests' to 0,
	 *               and 'anonymous' to 0 if the post ID or user identifier is not valid, or if the status
	 *               is not one of the acceptable values. If the attending limit is reached, 'status' may be
	 *               automatically set to 'waiting_list', and 'guests' to 0, depending on the context.
	 */
	public function save(
		mixed $identifier,
		string $status,
		int $anonymous = 0,
		int $guests = 0,
	): ?array {
		$identity = $this->resolve_identity( $identifier );

		if ( null === $identity ) {
			return self::DEFAULT_SAVE_RESPONSE;
		}

		$provider = $this->resolve_provider( $identity );
		$status   = Status::try_from( $status );
		$data     = new Data( $identity, $status, $guests, (bool) $anonymous );
		$intent   = new Intent( $data, $provider );
		$state    = $this->process( $intent );

		if ( null === $state ) {
			return self::DEFAULT_SAVE_RESPONSE;
		}

		Cache::delete( $this->event->ID );

		return Serializer::to_array( $state );
	}

	/**
	 * Process an RSVP request.
	 *
	 * @since 0.35.0
	 *
	 * @param Intent $intent The RSVP response/intent to save.
	 *
	 * @return State|null
	 */
	public function process( Intent $intent ): State|null {
		// If no valid event or RSVP is disabled for this event return empty default response.
		if ( 1 > $this->event->ID || ! $this->is_enabled() ) {
			return null;
		}

		// Get current/prior RSVP response.
		$current_response = $this->storage->get( $intent->data->identity, $intent->provider );

		// Apply business logic for RSVP requests.
		$intent = $this->constrain_rsvp_intent( $intent, $current_response );

		// Persist RSVP comment: Create new RSVP-comment, Update existing one, or delete on invalid status.
		$state = $this->storage->save(
			$intent,
			$current_response ? (int) $current_response->comment->comment_ID : null
		);

		if ( is_bool( $state ) ) {
			return null;
		}

		if ( ! $this->attending_limit_reached ) {
			$this->check_waiting_list();
		}

		return $state;
	}

	/**
	 * Promote pending RSVPs from the waiting list to attending up to the attendance limit.
	 *
	 * @since 0.34.0
	 *
	 * @return int Number of RSVPs promoted from the waiting list.
	 */
	public function check_waiting_list(): int {
		$states    = $this->storage->all();
		$responses = new Collection( $states );

		// If no RSVP responses are on the waiting list, quit.
		if ( ! $responses->has_waiting_list() ) {
			return 0;
		}

		$waiting_list = $responses->waiting_list();

		// If there is no attendance limit, promote all from waiting list to attending.
		if ( 0 === $this->max_attendance_limit ) {
			$promoted_count = 0;

			foreach ( $waiting_list as $state ) {
				$state = $this->storage->save( Intent::attend( $state ), (int) $state->comment->comment_ID );

				if ( $state instanceof State ) {
					++$promoted_count;
				}
			}

			return $promoted_count;
		}

		$remaining_spots = $this->max_attendance_limit - $responses->get_attendee_count();

		// No free spots left.
		if ( $remaining_spots <= 0 ) {
			return 0;
		}

		// If there is room, promote as many as possible. Promotion is keyed
		// by comment ID via the storage, so Open RSVP attendees (userId 0)
		// promote correctly — the identifier resolution develop needed for
		// #1771 does not apply to this design.
		$promoted_count = 0;

		for ( $i = 0; $i < $remaining_spots; $i++ ) {
			$state = $waiting_list[ $i ];
			$state = $this->storage->save( Intent::attend( $state ), (int) $state->comment->comment_ID );

			if ( $state instanceof State ) {
				++$promoted_count;
				$remaining_spots -= $state->get_attendee_count();
			}
		}

		return $promoted_count;
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
	 * @since 0.35.0
	 *
	 * @return bool True if RSVP is enabled for this event, false otherwise.
	 */
	public function is_enabled(): bool {
		$post_id   = $this->event->ID ?? 0;
		$rsvp_mode = Settings::get_instance()->get( 'rsvp_mode' );

		if ( 'disabled' === $rsvp_mode ) {
			return false;
		}

		if ( ! in_array( $rsvp_mode, array( 'per_event_on', 'per_event_off' ), true ) ) {
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
	 * @since 0.35.0
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
	 * Check if the attending limit has been reached for an event.
	 *
	 * This method determines whether the maximum response limit for the 'attending' status
	 * has been reached for the event. It checks the current number of 'attending' responses
	 * and compares it to the defined limit. It considers both the current response status
	 * and the number of guests associated with that response.
	 *
	 * @since 0.34.0
	 *
	 * @param State|null $current_response The current response data including status and number of guests.
	 *                                     Expected to have keys 'status' and 'guests', where 'status' is a
	 *                                     string indicating the current response status (e.g., 'attending'),
	 *                                     and 'guests' is an integer representing the number of guests.
	 * @param int        $guests           The number of additional guests to consider in the limit calculation.
	 *                                     Defaults to 0. This is used to adjust the total count based on any new
	 *                                     guests being added as part of the current operation.
	 * @return bool True if the 'attending' limit has been reached, false otherwise.
	 */
	public function attending_limit_reached( ?State $current_response, int $guests = 0 ): bool {
		$responses  = $this->responses();
		$user_count = 1;

		if ( empty( $this->max_attendance_limit ) ) {
			return false;
		}

		// If the user record was previously attending adjust numbers to figure out new limit.
		if ( $current_response && Status::ATTENDING === $current_response->data->status ) {
			$guests    -= $current_response->data->guests;
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
	 * @since 0.34.0
	 *
	 * @return array An array containing response information grouped by RSVP status.
	 */
	public function responses(): array {
		$cached = Cache::get( $this->event->ID );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$retval = array(
			'all' => array(
				'records' => array(),
				'count'   => 0,
			),
		);

		$states   = $this->storage->all();
		$statuses = Status::values();

		foreach ( $statuses as $status ) {
			$retval[ $status ] = array(
				'records' => array(),
				'count'   => 0,
			);
		}

		$total_guests = 0;
		$records      = array();

		foreach ( $states as $state ) {
			$records[]     = Serializer::to_array( $state );
			$total_guests += $state->data->guests;
		}

		usort( $records, array( $this, 'sort_by_role' ) );

		$retval['all']['records'] = $records;
		$retval['all']['count']   = count( $records ) + $total_guests;

		foreach ( $statuses as $status ) {
			$status_records = array_values(
				array_filter(
					$records,
					static fn( array $record ) => $record['status'] === $status
				)
			);

			$guest_count = array_sum(
				array_map(
					static fn( array $record ) => (int) $record['guests'],
					$status_records
				)
			);

			$retval[ $status ]['records'] = $status_records;
			$retval[ $status ]['count']   = count( $status_records ) + $guest_count;
		}

		Cache::set( $this->event->ID, $retval );

		return $retval;
	}

	/**
	 * Sort responses by their role.
	 *
	 * This method compares two responses based on their user roles and returns
	 * an integer (-1, 0, or 1) to determine their order in the sorted list.
	 *
	 * @since 0.34.0
	 *
	 * @param array $first  The first response to compare in the sort.
	 * @param array $second The second response to compare in the sort.
	 *
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
	 * @since 0.34.0
	 *
	 * @param array $first  First response to compare in the sort.
	 * @param array $second Second response to compare in the sort.
	 *
	 * @return int Returns a negative number if the first response's timestamp is earlier,
	 *             a positive number if the second response's timestamp is earlier,
	 *             or 0 if both are equal.
	 */
	public function sort_by_timestamp( array $first, array $second ): int {
		return strtotime( $first['timestamp'] ) <=> strtotime( $second['timestamp'] );
	}

	/**
	 * Applies business rules to a request.
	 *
	 * @param Intent $intent            The new RSVP intent.
	 * @param State  $current_response  The prior/current RSVP state.
	 *
	 * @return Intent
	 */
	private function constrain_rsvp_intent( Intent $intent, ?State $current_response ): Intent {
		$max_guest_limit = intval( get_post_meta( $this->event->ID, 'gatherpress_max_guest_limit', true ) );

		$guests    = $intent->data->guests;
		$anonymous = $intent->data->anonymous;
		$status    = $intent->data->status;

		if ( $max_guest_limit < $guests ) {
			$guests = $max_guest_limit;
		}

		// Check if anonymous RSVP is enabled for this event.
		$enable_anonymous_rsvp = get_post_meta( $this->event->ID, 'gatherpress_enable_anonymous_rsvp', true );
		if ( ! $enable_anonymous_rsvp ) {
			$anonymous = false;
		}

		// Constrain based on prior/current response.
		$limit_reached = $this->attending_limit_reached( $current_response, $intent->data->guests );

		$this->attending_limit_reached = $limit_reached;

		if ( $current_response && Status::ATTENDING === $intent->data->status && $limit_reached ) {
			$guests = $current_response->data->guests;
		}

		$old_status_is_not_attending = Status::ATTENDING !== $current_response->data->status;

		$desired_status_is_attending_or_waiting_list =
			in_array( $intent->data->status, array( Status::ATTENDING, Status::WAITING_LIST ), true );

		if (
			$old_status_is_not_attending &&
			$desired_status_is_attending_or_waiting_list &&
			$limit_reached
		) {
			$status = Status::WAITING_LIST;
		}

		if ( Status::WAITING_LIST === $intent->data->status ) {
			$guests = 0;
		}

		return new Intent(
			new Data(
				$intent->data->identity,
				$status,
				$guests,
				$anonymous,
				$intent->data->timestamp
			),
			$intent->provider
		);
	}

	/**
	 * Resolve identity.
	 *
	 * Only legacy functions should use this.
	 *
	 * @param string|int $identifier The Identifier.
	 *
	 * @return Identity|null
	 */
	private function resolve_identity( int|string $identifier ): ?Identity {
		if ( is_email( $identifier ) ) {
			return new Identity( Identity_TYPE::EMAIL, $identifier );
		}

		if ( is_int( $identifier ) && get_user_by( 'id', $identifier ) ) {
			return new Identity( Identity_TYPE::WP_USER_ID, $identifier );
		}

		return null;
	}

	/**
	 * Resolve provider.
	 *
	 * Only legacy functions should use this.
	 *
	 * @param Identity $identity The identity.
	 *
	 * @return Provider|null
	 */
	private function resolve_provider( Identity $identity ): ?Provider {
		if ( Identity_Type::WP_USER_ID === $identity->type ) {
			return $this->providers['user'];
		}

		if ( Identity_Type::EMAIL === $identity->type ) {
			return $this->providers['email'];
		}

		return null;
	}
}
