<?php
/**
 * Handles the registration of Event REST API endpoints.
 *
 * This file contains the Rest_Api class, which is responsible for registering and managing
 * various Event REST API endpoints within the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Traits\Singleton;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Event_Rest_Api.
 *
 * The Rest_Api class is responsible for registering and managing various REST API endpoints
 * used by the GatherPress plugin. It provides methods for defining routes, handling requests,
 * and delivering responses via the WordPress REST API infrastructure.
 *
 * @since 1.0.0
 */
class Event_Rest_Api {
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
		add_action( 'gatherpress_send_emails', array( $this, 'handle_email_send_action' ), 10, 3 );
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
			$this->email_route(),
			$this->rsvp_route(),
			$this->events_list_route(),
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
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'message' => array(
						'required'          => false,
						'validate_callback' => 'sanitize_text_field',
					),
					'send'    => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'send' ),
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
				'permission_callback' => static function (): bool {
					return is_user_logged_in();
				},
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'status'  => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'rsvp_status' ),
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
						'validate_callback' => array( Validate::class, 'event_list_type' ),
					),
					'max_number'      => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'number' ),
					),
					'datetime_format' => array(
						'required' => false,
					),
					'topics'          => array(
						'required' => false,
					),
				),
			),
		);
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
	 * Hooked method to trigger the sending of related emails.
	 *
	 * This method hooks into a WordPress action, triggering the `send_emails` method to send emails to selected members.
	 * It doesn't return any value, as it's intended to be called by an action hook.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $send    Members to send the email to.
	 * @param string $message Optional message to include in the email.
	 * @return void
	 */
	public function handle_email_send_action( int $post_id, array $send, string $message ): void {
		$this->send_emails( $post_id, $send, $message );
	}

	/**
	 * Send emails to selected members.
	 *
	 * This method is responsible for sending emails to specific members. It checks if the given
	 * `$post_id` corresponds to a specific post type, retrieves the list of members to email, and sends the email with
	 * the appropriate subject, body, and headers.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $send    Members to send the email to.
	 * @param string $message Optional message to include in the email.
	 * @return bool True if emails were successfully sent, false otherwise.
	 */
	public function send_emails( int $post_id, array $send, string $message ): bool {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}

		// Keep the currently logged-in user.
		$current_user = wp_get_current_user();
		$members      = $this->get_members( $send, $post_id );

		foreach ( $members as $member ) {
			if ( '0' === get_user_meta( $member->ID, 'gatherpress_event_updates_opt_in', true ) ) {
				continue;
			}

			if ( $member->user_email ) {
				$to              = $member->user_email;
				$switched_locale = switch_to_user_locale( $member->ID );

				// Set the current user to the actual member to mail to,
				// to make sure the GatherPress filters for date- and time- format, as well as the users timezone,
				// are recognized by the functions inside render_template().
				wp_set_current_user( $member->ID );

				/* translators: %s: event title. */
				$subject = sprintf( _x( '📅 %s', 'Email subject for event updates', 'gatherpress' ), get_the_title( $post_id ) );
				$body    = Utility::render_template(
					sprintf( '%s/includes/templates/admin/emails/event-email.php', GATHERPRESS_CORE_PATH ),
					array(
						'event_id' => $post_id,
						'message'  => $message,
					),
				);
				$headers = array( 'Content-Type: text/html; charset=UTF-8' );
				$subject = stripslashes_deep( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) );

				// Reset the current user to the editor sending the email.
				wp_set_current_user( $current_user->ID );

				wp_mail( $to, $subject, $body, $headers );

				if ( $switched_locale ) {
					restore_previous_locale();
				}
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
						static function ( $member ) {
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
		$datetime_format = ! empty( $params['datetime_format'] ) ? $params['datetime_format'] : 'D, M j, Y, g:i a T';
		$posts           = array();
		$topics          = array();
		$venues          = array();

		if ( ! empty( $params['topics'] ) ) {
			$topics = array_map(
				static function ( $slug ): string {
					return sanitize_key( $slug );
				},
				explode( ',', $params['topics'] )
			);
		}

		if ( ! empty( $params['venues'] ) ) {
			$venues = array_map(
				static function ( $slug ): string {
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
					'datetime_start'           => $event->get_datetime_start( $datetime_format ),
					'datetime_end'             => $event->get_datetime_end( $datetime_format ),
					'permalink'                => get_the_permalink( $post_id ),
					'title'                    => get_the_title( $post_id ),
					'excerpt'                  => get_the_excerpt( $post_id ),
					'featured_image'           => get_the_post_thumbnail( $post_id, 'medium' ),
					'featured_image_large'     => get_the_post_thumbnail( $post_id, 'large' ),
					'featured_image_thumbnail' => get_the_post_thumbnail( $post_id, 'thumbnail' ),
					'enable_anonymous_rsvp'    => (bool) get_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', true ),
					'enable_initial_decline'   => (bool) get_post_meta( $post_id, 'gatherpress_enable_initial_decline', true ),
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
		$anonymous       = intval( $params['anonymous'] );
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
			$user_record = $event->rsvp->save( $user_id, $status, $anonymous, $guests );
			$status      = $user_record['status'];
			$guests      = $user_record['guests'];

			if ( in_array( $status, $event->rsvp->statuses, true ) ) {
				$success = true;
			}
		}

		$response = array(
			'event_id'    => $post_id,
			'success'     => $success,
			'status'      => $status,
			'guests'      => $guests,
			'anonymous'   => $anonymous,
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
		$response->data['meta']['online_event_link'] = $event->maybe_get_online_event_link();

		return $response;
	}
}
