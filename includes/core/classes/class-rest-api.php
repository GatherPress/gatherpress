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

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore Prevent direct access.
}

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
		add_filter( sprintf( 'rest_prepare_%s', Event::POST_TYPE ), array( $this, 'prepare_event_data' ) );
		add_filter( 'rest_send_nocache_headers', array( $this, 'nocache_headers_for_endpoint' ) );
		add_action( 'gatherpress_send_emails', array( $this, 'send_emails' ), 10, 3 );
	}

	/**
	 * Registers REST API endpoints for GatherPress events.
	 *
	 * Registers various REST API endpoints for interacting with GatherPress events.
	 * The registered routes include endpoints for event creation, retrieval, updating, and deletion.
	 *
	 * @todo Implement access control to restrict certain operations to authorized users.
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
	 * Prevents caching of nonce for specified REST API endpoints for non-logged-in visitors.
	 *
	 * This method checks if the requested REST API endpoint is in a list of endpoints that should
	 * not cache the nonce. If the endpoint matches, it sets the `rest_send_nocache_headers` flag to true,
	 * preventing caching of the nonce for non-logged-in visitors.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $rest_send_nocache_headers A boolean value indicating whether to prevent caching of the nonce.
	 *
	 * @return bool The modified value of $rest_send_nocache_headers after processing.
	 */
	public function nocache_headers_for_endpoint( bool $rest_send_nocache_headers ): bool {
		global $wp;

		$endpoints = array(
			sprintf( '/%s/event/events-list', GATHERPRESS_REST_NAMESPACE ),
		);

		if ( in_array( $wp->query_vars['rest_route'], $endpoints, true ) ) {
			$rest_send_nocache_headers = true;
		}

		return $rest_send_nocache_headers;
	}

	/**
	 * Get the event routes.
	 *
	 * Retrieves an array of REST API routes for GatherPress events.
	 *
	 * @todo Refactor each route into small protected methods to improve readability.
	 *
	 * @since 1.0.0
	 *
	 * @return array[] An array of route definitions for GatherPress events.
	 */
	protected function get_event_routes(): array {
		return array(
			array(
				'route' => 'datetime',
				'args'  => array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_datetime' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'_wpnonce'       => array(
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required' => true,
						),
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
			),
			array(
				'route' => 'email',
				'args'  => array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'email' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'_wpnonce' => array(
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required' => true,
						),
						'post_id'  => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_event_post_id' ),
						),
						'message'  => array(
							'required'          => false,
							'validate_callback' => 'sanitize_text_field',
						),
						'send'     => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_send' ),
						),
					),
				),
			),
			array(
				'route' => 'rsvp',
				'args'  => array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_rsvp' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'_wpnonce' => array(
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required' => true,
						),
						'post_id'  => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_event_post_id' ),
						),
						// @todo add logic for allowing event organizers to add people to events as attendees.
						// 'user_id'        => [
						// 'required'          => false,
						// 'validate_callback' => [ $this, 'validate_event_post_id' ],
						// ],
						'status'   => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_rsvp_status' ),
						),
					),
				),
			),
			array(
				'route' => 'events-list',
				'args'  => array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'events_list' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'_wpnonce'        => array(
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required' => false,
						),
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
			),
		);
	}

	/**
	 * Validate RSVP status.
	 *
	 * Validates whether a given parameter is a valid RSVP status.
	 *
	 * @param string $param An RSVP status to validate.
	 *
	 * @since 1.0.0
	 *
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
	 * @param int|string $param A Post ID to validate.
	 *
	 * @since 1.0.0
	 *
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
	 * @param mixed $param An array of email recipients.
	 *
	 * @todo Refactor this method for improved readability and simplicity.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the parameter is a valid array of email recipients, false otherwise.
	 */
	public function validate_send( $param ): bool {
		if (
			is_array( $param ) &&
			array_key_exists( 'all', $param ) &&
			is_bool( $param['all'] ) &&
			array_key_exists( 'attending', $param ) &&
			is_bool( $param['attending'] ) &&
			array_key_exists( 'waiting_list', $param ) &&
			is_bool( $param['waiting_list'] ) &&
			array_key_exists( 'not_attending', $param ) &&
			is_bool( $param['not_attending'] )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Validate a numeric value.
	 *
	 * Validates whether the given parameter is a valid numeric value greater than zero.
	 *
	 * @param int|string $param The value to validate.
	 *
	 * @since 1.0.0
	 *
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
	 * @param string $param The event list type to validate.
	 *
	 * @since 1.0.0
	 *
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
	 * @param string $param The datetime string to validate.
	 *
	 * @since 1.0.0
	 *
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
	 * @param string $param The timezone identifier to validate.
	 *
	 * @since 1.0.0
	 *
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
	 *
	 * @return WP_REST_Response The response indicating the success of the operation.
	 * @throws Exception When an exception occurs during the process.
	 */
	public function update_datetime( WP_REST_Request $request ): WP_REST_Response {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
				)
			);
		}

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
	 *
	 * @return WP_REST_Response The response indicating the success of the email scheduling process.
	 */
	public function email( WP_REST_Request $request ): WP_REST_Response {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
				)
			);
		}

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
	 *
	 * @return void
	 */
	public function send_emails( int $post_id, array $send, string $message ): void {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
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
	 *
	 * @return array An array containing the member data of recipients.
	 */
	public function get_members( array $send, int $post_id ): array {
		$member_ids    = array();
		$rsvp          = new Rsvp( $post_id );
		$all_attendees = $rsvp->attendees();

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
						$all_attendees[ $status ]['attendees']
					)
				);
			}
		}

		if ( ! empty( $member_ids ) ) {
			return get_users( array( 'include' => $member_ids ) );
		}

		return $member_ids;
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
	 *
	 * @return WP_REST_Response The REST API response containing an array of event data.
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

		$query = Query::get_instance()->get_events_list( $event_list_type, $max_number, $topics, $venues );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$event             = new Event( $post_id );
				$venue_information = $event->get_venue_information();
				$posts[]           = array(
					'ID'                       => $post_id,
					'datetime_start'           => $event->get_datetime_start(),
					'permalink'                => get_the_permalink( $post_id ),
					'title'                    => get_the_title( $post_id ),
					'excerpt'                  => get_the_excerpt( $post_id ),
					'featured_image'           => get_the_post_thumbnail( $post_id, 'medium' ),
					'featured_image_large'     => get_the_post_thumbnail( $post_id, 'large' ),
					'featured_image_thumbnail' => get_the_post_thumbnail( $post_id, 'thumbnail' ),
					'attendees'                => ( $event->rsvp ) ? $event->rsvp->attendees() : array(),
					'current_user'             => ( $event->rsvp && $event->rsvp->get( get_current_user_id() ) ) ? $event->rsvp->get( get_current_user_id() ) : '',
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
	 *
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
	 * it returns relevant information, including the updated status, guest count, and attendees.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Contains data from the request.
	 *
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
		$online_link     = (string) get_post_meta( $post_id, '_online_event_link', true );

		// If managing user is adding someone to an event.
		if (
			intval( $current_user_id )
			&& intval( $user_id )
			&& $current_user_id !== $user_id
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
			intval( $user_id )
			&& current_user_can( 'read' )
			&& is_user_member_of_blog( $user_id )
			&& ! $event->has_event_past()
		) {
			$status = $event->rsvp->save( $user_id, $status, $guests );

			if ( in_array( $status, $event->rsvp->statuses, true ) ) {
				$success = true;
			}
		}

		$response = array(
			'event_id'    => $post_id,
			'success'     => (bool) $success,
			'status'      => $status,
			'guests'      => $guests,
			'attendees'   => $event->rsvp->attendees(),
			'online_link' => ( 'attending' === $status ) ? $online_link : '',
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
	 *
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
