<?php
/**
 * Handles the registration of REST API endpoints.
 *
 * This file contains the Rest_Api class, which is responsible for registering and managing
 * various REST API endpoints within the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use Exception;
use GatherPress\Core\Traits\Singleton;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Rest_Api.
 *
 * The Rest_Api class is responsible for registering and managing various REST API endpoints
 * used by the GatherPress plugin. It provides methods for defining routes, handling requests,
 * and delivering responses via the WordPress REST API infrastructure.
 *
 * @since 1.0.0
 */
class Rest_Api {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

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
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
		add_action( 'gatherpress_send_emails', array( $this, 'send_emails' ), 10, 3 );
		add_filter( sprintf( 'rest_prepare_%s', Event::POST_TYPE ), array( $this, 'prepare_event_data' ) );
	}

	/**
	 * Registers REST API endpoints for GatherPress events.
	 *
	 * Registers various REST API endpoints for interacting with GatherPress events.
	 * The registered routes include endpoints for event creation, retrieval, updating, and deletion.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		// All event routes.
		$routes = $this->get_event_routes();

		foreach ( $routes as $route ) {
			register_rest_route(
				sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
				sprintf( '/%s', $route['route'] ),
				$route['args']
			);
		}
	}

	/**
	 * Get the event routes.
	 *
	 * Retrieves an array of REST API routes for GatherPress events.
	 *
	 * @since 1.0.0
	 *
	 * @return array[] An array of route definitions for GatherPress events.
	 */
	protected function get_event_routes(): array {
		return array(
			$this->datetime_route(),
			$this->email_route(),
			$this->rsvp_route(),
			$this->events_list_route(),
		);
	}

	/**
	 * Define the REST route for updating event date and time.
	 *
	 * This method sets up the REST route for updating the date and time of an event.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function datetime_route(): array {
		return array(
			'route' => 'datetime',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_datetime' ),
				'permission_callback' => static function(): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id'        => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_event_post_id' ),
					),
					'datetime_start' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_datetime' ),
					),
					'datetime_end'   => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_datetime' ),
					),
					'timezone'       => array(
						'required'          => false,
						'validate_callback' => array( $this, 'validate_timezone' ),
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for sending event-related emails.
	 *
	 * This method sets up the REST route for sending emails related to an event.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function email_route(): array {
		return array(
			'route' => 'email',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'email' ),
				'permission_callback' => static function(): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_event_post_id' ),
					),
					'message' => array(
						'required'          => false,
						'validate_callback' => 'sanitize_text_field',
					),
					'send'    => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_send' ),
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for updating event RSVP status.
	 *
	 * This method sets up the REST route for updating the RSVP status of an event.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function rsvp_route(): array {
		return array(
			'route' => 'rsvp',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_rsvp' ),
				'permission_callback' => static function(): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_event_post_id' ),
					),
					'status'  => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_rsvp_status' ),
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for retrieving a list of events.
	 *
	 * This method sets up the REST route for retrieving a list of events based on specified parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function events_list_route(): array {
		return array(
			'route' => 'events-list',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'events_list' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'event_list_type' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_event_list_type' ),
					),
					'max_number'      => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_number' ),
					),
					'topics'          => array(
						'required' => false,
					),
				),
			),
		);
	}

	/**
	 * Validate RSVP status.
	 *
	 * Validates whether a given parameter is a valid RSVP status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $param An RSVP status to validate.
	 * @return bool True if the parameter is a valid RSVP status, false otherwise.
	 */
	public function validate_rsvp_status( $param ): bool {
		return ( 'attending' === $param || 'not_attending' === $param );
	}

	/**
	 * Validate Event Post ID.
	 *
	 * Validates whether a given parameter is a valid Event Post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $param A Post ID to validate.
	 * @return bool True if the parameter is a valid Event Post ID, false otherwise.
	 */
	public function validate_event_post_id( $param ): bool {
		return (
			$this->validate_number( $param ) &&
			Event::POST_TYPE === get_post_type( $param )
		);
	}

	/**
	 * Validate recipients for sending emails.
	 *
	 * Validates an array of email recipient options to ensure they are correctly structured.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $param An array of email recipients.
	 * @return bool True if the parameter is a valid array of email recipients, false otherwise.
	 */
	public function validate_send( $param ): bool {
		$expected_params = array( 'all', 'attending', 'waiting_list', 'not_attending' );

		if ( is_array( $param ) ) {
			foreach ( $expected_params as $expected_param ) {
				if (
					! array_key_exists( $expected_param, $param ) ||
					! is_bool( $param[ $expected_param ] )
				) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Validate a numeric value.
	 *
	 * Validates whether the given parameter is a valid numeric value greater than zero.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $param The value to validate.
	 * @return bool True if the parameter is a valid numeric value greater than zero, false otherwise.
	 */
	public function validate_number( $param ): bool {
		return (
			0 < intval( $param ) &&
			is_numeric( $param )
		);
	}

	/**
	 * Validate an event list type.
	 *
	 * Validates whether the given event list type parameter is valid (either 'upcoming' or 'past').
	 *
	 * @since 1.0.0
	 *
	 * @param string $param The event list type to validate.
	 * @return bool True if the parameter is a valid event list type, false otherwise.
	 */
	public function validate_event_list_type( string $param ): bool {
		return in_array( $param, array( 'upcoming', 'past' ), true );
	}

	/**
	 * Validate a datetime string.
	 *
	 * Validates whether the given datetime string parameter is in the valid 'Y-m-d H:i:s' format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $param The datetime string to validate.
	 * @return bool True if the parameter is a valid datetime string, false otherwise.
	 */
	public function validate_datetime( string $param ): bool {
		return (bool) \DateTime::createFromFormat( 'Y-m-d H:i:s', $param );
	}

	/**
	 * Validate a timezone identifier.
	 *
	 * Validates whether the given timezone identifier parameter is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $param The timezone identifier to validate.
	 * @return bool True if the parameter is a valid timezone identifier, false otherwise.
	 */
	public function validate_timezone( string $param ): bool {
		return in_array( Event::maybe_convert_offset( $param ), Event::list_identifiers(), true );
	}

	/**
	 * Update the custom event table with start and end Datetime.
	 *
	 * This method is used to update the custom event table with new start and end Datetimes for a specific event.
	 * It checks the user's capability to edit posts before making any changes. If the user doesn't have the required
	 * capability, the method returns a response with 'success' set to false.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 * @return WP_REST_Response The response indicating the success of the operation.
	 *
	 * @throws Exception When an exception occurs during the process.
	 */
	public function update_datetime( WP_REST_Request $request ): WP_REST_Response {
		$params             = wp_parse_args( $request->get_params(), $request->get_default_params() );
		$params['timezone'] = Event::maybe_convert_offset( $params['timezone'] );
		$event              = new Event( $params['post_id'] );

		unset( $params['post_id'] );

		$success  = $event->save_datetimes( $params );
		$response = array(
			'success' => $success,
		);

		return new WP_REST_Response( $response );
	}

	/**
	 * Send an event email notification to members.
	 *
	 * This method allows sending an email notification about a specific event to members. It checks the user's capability
	 * to edit posts before initiating the email sending process. If the user doesn't have the required capability,
	 * the method returns a response with 'success' set to false.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 * @return WP_REST_Response The response indicating the success of the email scheduling process.
	 */
	public function email( WP_REST_Request $request ): WP_REST_Response {
		$params   = $request->get_params();
		$post_id  = intval( $params['post_id'] );
		$message  = $params['message'] ?? '';
		$send     = $params['send'];
		$success  = wp_schedule_single_event( time(), 'gatherpress_send_emails', array( $post_id, $send, $message ) );
		$response = array(
			'success' => $success,
		);

		return new WP_REST_Response( $response );
	}

	/**
	 * Send event-related emails to selected members.
	 *
	 * This method is responsible for sending event-related emails to specific members. It first checks if the given
	 * `$post_id` corresponds to an event post type, and if not, it returns early. Then, it retrieves a list of members
	 * to send the email to and constructs the email subject, body, and headers. Finally, it sends the email to each
	 * selected member.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Event Post ID.
	 * @param array  $send    Members to send the email to.
	 * @param string $message Optional message to include in the email.
	 * @return bool
	 */
	public function send_emails( int $post_id, array $send, string $message ): bool {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}

		$members = $this->get_members( $send, $post_id );
		/* translators: %s: event title. */
		$subject = sprintf( __( '📅 %s', 'gatherpress' ), get_the_title( $post_id ) );
		$body    = Utility::render_template(
			sprintf( '%s/includes/templates/admin/emails/event-email.php', GATHERPRESS_CORE_PATH ),
			array(
				'event_id' => $post_id,
				'message'  => $message,
			),
		);
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$subject = stripslashes_deep( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) );

		foreach ( $members as $member ) {
			if ( $member->user_email ) {
				$to = $member->user_email;

				wp_mail( $to, $subject, $body, $headers );
			}
		}

		return true;
	}

	/**
	 * Get the list of members to send event-related emails to.
	 *
	 * This method retrieves the list of members to whom event-related emails should be sent based on the given `$send`
	 * parameter and the specified event `$post_id`. It checks the `$send` array for specific email recipient categories,
	 * such as 'all,' 'attending,' 'waiting_list,' and 'not_attending,' and compiles a list of corresponding member IDs.
	 * If no matching categories are found, an empty array is returned.
	 *
	 * @since 1.0.0
	 *
	 * @param array $send    An array specifying who to send emails to.
	 * @param int   $post_id The Event Post ID.
	 * @return array An array containing the member data of recipients.
	 */
	public function get_members( array $send, int $post_id ): array {
		$member_ids    = array();
		$rsvp          = new Rsvp( $post_id );
		$all_responses = $rsvp->responses();

		if ( ! empty( $send['all'] ) ) {
			return get_users();
		}

		foreach ( array( 'attending', 'waiting_list', 'not_attending' ) as $status ) {
			if ( ! empty( $send[ $status ] ) ) {
				$member_ids = array_merge(
					$member_ids,
					array_map(
						static function( $member ) {
							return $member['id'];
						},
						$all_responses[ $status ]['responses']
					)
				);
			}
		}

		if ( ! empty( $member_ids ) ) {
			return get_users( array( 'include' => $member_ids ) );
		}

		return array();
	}

	/**
	 * Retrieve a list of events based on specified criteria.
	 *
	 * This method handles the retrieval of a list of events based on the parameters provided in the REST API request.
	 * It takes the `event_list_type` to determine whether to fetch upcoming or past events, the `max_number` to
	 * limit the number of events in the response, and optional `topics` and `venues` to filter events by specific
	 * topic and venue slugs.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Contains data from the REST API request.
	 * @return WP_REST_Response The REST API response containing an array of event data.
	 *
	 * @throws Exception If there is an issue while retrieving the list of events.
	 */
	public function events_list( WP_REST_Request $request ): WP_REST_Response {
		$params          = $request->get_params();
		$event_list_type = $params['event_list_type'];
		$max_number      = $this->max_number( (int) $params['max_number'], 5 );
		$posts           = array();
		$topics          = array();
		$venues          = array();

		if ( ! empty( $params['topics'] ) ) {
			$topics = array_map(
				static function( $slug ): string {
					return sanitize_key( $slug );
				},
				explode( ',', $params['topics'] )
			);
		}

		if ( ! empty( $params['venues'] ) ) {
			$venues = array_map(
				static function( $slug ): string {
					return sanitize_key( $slug );
				},
				explode( ',', $params['venues'] )
			);
		}

		$query = Event_Query::get_instance()->get_events_list( $event_list_type, $max_number, $topics, $venues );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$event             = new Event( $post_id );
				$venue_information = $event->get_venue_information();
				$posts[]           = array(
					'ID'                       => $post_id,
					'datetime_start'           => $event->get_datetime_start(),
					'datetime_end'             => $event->get_datetime_end(),
					'permalink'                => get_the_permalink( $post_id ),
					'title'                    => get_the_title( $post_id ),
					'excerpt'                  => get_the_excerpt( $post_id ),
					'featured_image'           => get_the_post_thumbnail( $post_id, 'medium' ),
					'featured_image_large'     => get_the_post_thumbnail( $post_id, 'large' ),
					'featured_image_thumbnail' => get_the_post_thumbnail( $post_id, 'thumbnail' ),
					'responses'                => ( $event->rsvp ) ? $event->rsvp->responses() : array(),
					'current_user'             => ( $event->rsvp && $event->rsvp->get( get_current_user_id() ) )
						? $event->rsvp->get( get_current_user_id() )
						: '',
					'venue'                    => ( $venue_information['name'] ? $event->get_venue_information() : null ),
				);
			}
		}

		wp_reset_postdata();

		return new WP_REST_Response( $posts );
	}

	/**
	 * Ensure that the provided number does not exceed the maximum number allowed.
	 *
	 * This method checks if the provided `$number` is greater than the specified `$max_number` and
	 * returns the lower of the two values to ensure it does not exceed the maximum limit.
	 *
	 * @since 1.0.0
	 *
	 * @param int $number     The actual number.
	 * @param int $max_number The maximum number allowed.
	 * @return int The sanitized number, ensuring it does not exceed the maximum limit.
	 */
	protected function max_number( int $number, int $max_number ): int {
		if ( $max_number < $number ) {
			$number = $max_number;
		}

		return $number;
	}

	/**
	 * Update the RSVP status for a user to an event.
	 *
	 * This method handles the update of the RSVP status for a user to an event, including handling guest count.
	 * It checks the user's permissions and the event's status to ensure a valid update. If the update is successful,
	 * it returns relevant information, including the updated status, guest count, and responses.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 * @return WP_REST_Response An instance of WP_REST_Response containing the response data.
	 */
	public function update_rsvp( WP_REST_Request $request ): WP_REST_Response {
		$params          = $request->get_params();
		$success         = false;
		$current_user_id = get_current_user_id();
		$blog_id         = get_current_blog_id();
		$user_id         = isset( $params['user_id'] ) ? intval( $params['user_id'] ) : $current_user_id;
		$post_id         = intval( $params['post_id'] );
		$status          = sanitize_key( $params['status'] );
		$guests          = intval( $params['guests'] );
		$event           = new Event( $post_id );

		// If managing user is adding someone to an event.
		if (
			$current_user_id &&
			$user_id &&
			$current_user_id !== $user_id
		) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				$user_id = 0;
			}
		} else {
			$user_id = $current_user_id;
		}

		if ( intval( $user_id ) && ! is_user_member_of_blog( $user_id ) ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}

		if (
			$user_id &&
			is_user_member_of_blog( $user_id ) &&
			! $event->has_event_past()
		) {
			$status = $event->rsvp->save( $user_id, $status, $guests );

			if ( in_array( $status, $event->rsvp->statuses, true ) ) {
				$success = true;
			}
		}

		$response = array(
			'event_id'    => $post_id,
			'success'     => $success,
			'status'      => $status,
			'guests'      => $guests,
			'responses'   => $event->rsvp->responses(),
			'online_link' => $event->maybe_get_online_event_link(),
		);

		return new WP_REST_Response( $response );
	}

	/**
	 * Prepare event data for the response.
	 *
	 * This method prepares and enhances the event data for the response object. It retrieves additional meta information,
	 * such as the online event link, based on specific conditions. The enhanced data is then added to the response.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Response $response The response object containing event data.
	 * @return WP_REST_Response The response object with enhanced event data.
	 */
	public function prepare_event_data( WP_REST_Response $response ): WP_REST_Response {
		$event = new Event( $response->data['id'] );

		// Retrieve the online event link only if:
		// - The user is attending the event.
		// - The event is in the future.
		// - The code is not in an admin context.
		$response->data['meta']['_online_event_link'] = $event->maybe_get_online_event_link();

		return $response;
	}
}
