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
use GatherPress\Core\Blocks\Rsvp_Template;
use GatherPress\Core\Traits\Singleton;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;

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
			$this->rsvp_form_route(),
			$this->rsvp_status_html_route(),
			$this->rsvp_responses_route(),
			$this->events_list_route(),
			$this->nonce_route(),
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
	 * Define REST API route for generating nonce.
	 *
	 * Creates a publicly accessible endpoint that generates a fresh nonce
	 * for authenticated REST API requests.
	 *
	 * @since 1.0.0
	 *
	 * @return array Route configuration array.
	 */
	protected function nonce_route(): array {
		return array(
			'route' => 'nonce',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function () {
					// Force WordPress to authenticate the user.
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
					$user_id = apply_filters( 'determine_current_user', false );

					if ( $user_id ) {
						wp_set_current_user( $user_id );
					}

					$response = array(
						'nonce' => wp_create_nonce( 'wp_rest' ),
					);

					return new WP_REST_Response( $response );
				},
				'permission_callback' => '__return_true',
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
				'permission_callback' => static function ( WP_Rest_Request $request ): bool {
					$unparsed_token = $request->get_param( 'rsvp_token' );

					if ( ! empty( $unparsed_token ) ) {
						$token_parts = Rsvp_Setup::get_instance()->parse_rsvp_token( $unparsed_token );

						if ( ! empty( $token_parts ) ) {
							$rsvp_token = new Rsvp_Token( $token_parts['comment_id'] );

							return $rsvp_token->is_valid( $token_parts['token'] );
						}
					}

					return is_user_logged_in();
				},
				'args'                => array(
					'post_id'    => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'rsvp_token' => array(
						'required'          => false,
						'validate_callback' => static function ( $param ): bool {
							return ! empty( Rsvp_Setup::get_instance()->parse_rsvp_token( $param ) );
						},
					),
					'status'     => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'rsvp_status' ),
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for handling RSVP form submissions via Ajax.
	 *
	 * This method sets up the REST route for processing RSVP form submissions
	 * dynamically via Ajax while maintaining the same functionality as the
	 * traditional comment-based form submission system.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function rsvp_form_route(): array {
		return array(
			'route' => 'rsvp-form',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'handle_rsvp_form_submission' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'comment_post_ID'                 => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'author'                          => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return ! empty( sanitize_text_field( $param ) );
						},
					),
					'email'                           => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_email( $param );
						},
					),
					'gatherpress_event_email_updates' => array(
						'required'          => false,
						'validate_callback' => array( Validate::class, 'boolean' ),
					),
					'gatherpress_form_schema_id'      => array(
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && preg_match( '/^form_\d+$/', $param );
						},
					),
				),
			),
		);
	}

	/**
	 * Define the REST route for rendering RSVP block HTML.
	 *
	 * This method registers a REST API route for dynamically generating HTML markup
	 * for RSVP blocks based on the provided block data and post ID.
	 * The generated HTML reflects the current RSVP status and can be used
	 * to re-render block content when status changes occur.
	 *
	 * @since 1.0.0
	 *
	 * @return array The REST route configuration.
	 */
	protected function rsvp_status_html_route(): array {
		return array(
			'route' => 'rsvp-status-html',
			'args'  => array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'rsvp_status_html' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'       => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
					),
					'status'        => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'rsvp_status' ),
					),
					'block_data'    => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'block_data' ),
					),
					'limit_enabled' => array(
						'required'          => false,
						'validate_callback' => array( Validate::class, 'boolean' ),
					),
					'limit'         => array(
						'required'          => false,
						'validate_callback' => array( Validate::class, 'number' ),
					),
				),
			),
		);
	}

	/**
	 * Get route configuration for RSVP responses endpoint.
	 *
	 * Defines REST route configuration to fetch RSVP response data for an event post.
	 * Endpoint requires post_id parameter which must validate as an event post type.
	 *
	 * @since 1.0.0
	 *
	 * @return array Route configuration with path, methods, callback and arguments.
	 */
	protected function rsvp_responses_route(): array {
		return array(
			'route' => 'rsvp-responses',
			'args'  => array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rsvp_responses' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => array( Validate::class, 'event_post_id' ),
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
				$subject = sprintf( _x( '📅 %s', 'Email notification subject with event title', 'gatherpress' ), get_the_title( $post_id ) );
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
							return $member['userId'];
						},
						$all_responses[ $status ]['records']
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
		$guests          = intval( $params['guests'] ?? 0 );
		$anonymous       = intval( $params['anonymous'] ?? 0 );
		$unparsed_token  = sanitize_text_field( $params['rsvp_token'] ?? '' );
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

		$user_identifier = $user_id;

		if ( ! empty( $unparsed_token ) ) {
			$token_parts = Rsvp_Setup::get_instance()->parse_rsvp_token( $unparsed_token );

			if ( ! empty( $token_parts ) ) {
				$rsvp_token = new Rsvp_Token( $token_parts['comment_id'] );

				if ( $rsvp_token->is_valid( $token_parts['token'] ) ) {
					$user_identifier = $rsvp_token->get_email();
				}
			}
		}

		if (
			$user_identifier &&
			( is_user_member_of_blog( $user_identifier ) || is_email( $user_identifier ) ) &&
			! $event->has_event_past()
		) {
			if ( 'attending' !== $status ) {
				$guests = 0;
			}

			$user_record = $event->rsvp->save( $user_identifier, $status, $anonymous, $guests );
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
	 * Handles rendering RSVP block HTML via a REST API endpoint.
	 *
	 * This method dynamically generates HTML markup for RSVP blocks based on the
	 * provided block data and the responses for a given post ID. It processes the
	 * RSVP responses and renders the corresponding content using the block template.
	 * Each response is wrapped in its own container with data attributes to facilitate
	 * interactivity and styling.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST API request object containing parameters:
	 *                                 - post_id (int): The ID of the post associated with the RSVP.
	 *                                 - block_data (string): JSON-encoded block data used to render the RSVP content.
	 *
	 * @return WP_REST_Response The REST API response containing:
	 *                          - success (bool): Whether the content was successfully generated.
	 *                          - content (string): The dynamically rendered HTML markup for the RSVP responses.
	 */
	public function rsvp_status_html( WP_REST_Request $request ): WP_REST_Response {
		$rsvp_template = Rsvp_Template::get_instance();
		$params        = $request->get_params();
		$post_id       = intval( $params['post_id'] );
		$status        = $params['status'];
		$block_data    = $params['block_data'];
		$block_data    = json_decode( $block_data, true );
		$rsvp          = new Rsvp( $post_id );
		$responses     = $rsvp->responses();
		$content       = '';
		// @todo set this up...
		$args = array(
			'limit_enabled' => (bool) $params['limit_enabled'],
			'limit'         => (int) $params['limit'],
		);

		if ( ! empty( $responses[ $status ] ) ) {
			foreach ( $responses[ $status ]['records'] as $key => $record ) {
				$args['index'] = $key;
				$content      .= $rsvp_template->get_block_content( $block_data, $record['commentId'], $args );
			}
		}

		$success = true;

		$response = array(
			'success'   => $success,
			'content'   => $content,
			'responses' => $responses,
		);

		return new WP_REST_Response( $response );
	}

	/**
	 * Handle RSVP form submission via Ajax.
	 *
	 * This method processes RSVP form submissions received via Ajax,
	 * creating the appropriate comment entry and setting up the RSVP
	 * while maintaining compatibility with the existing system.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 * @return WP_REST_Response The response indicating success or failure.
	 */
	public function handle_rsvp_form_submission( WP_REST_Request $request ): WP_REST_Response {
		$params        = $request->get_params();
		$post_id       = intval( $params['comment_post_ID'] );
		$author        = sanitize_text_field( $params['author'] );
		$email         = sanitize_email( $params['email'] );
		$email_updates = (bool) $params['gatherpress_event_email_updates'];
		$user          = get_user_by( 'ID', get_current_user_id() );
		$success       = false;
		$message       = '';
		$comment_id    = 0;

		// Prepare comment data similar to the form submission processing.
		$comment_data = array(
			'comment_post_ID'   => $post_id,
			'comment_author_IP' => '127.0.0.1',
			'comment_type'      => Rsvp::COMMENT_TYPE,
			'comment_content'   => '',
			'comment_parent'    => 0,
			'user_id'           => 0,
			'comment_approved'  => 0,
		);

		// Set remote IP if available.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( rest_is_ip_address( $remote_ip ) ) {
				$comment_data['comment_author_IP'] = $remote_ip;
			}
		}

		// Handle user authentication check.
		if ( ! $user instanceof WP_User || $user->user_email !== $email ) {
			$comment_data['user_id']              = 0;
			$comment_data['comment_author_url']   = '';
			$comment_data['comment_author']       = $author;
			$comment_data['comment_author_email'] = $email;
		} else {
			$comment_data['user_id']              = $user->ID;
			$comment_data['comment_author']       = $user->display_name;
			$comment_data['comment_author_email'] = $user->user_email;
			$comment_data['comment_author_url']   = get_author_posts_url( $user->ID );
		}

		// Check for duplicate RSVP.
		$existing_rsvp = get_comments(
			array(
				'post_id'      => $post_id,
				'type'         => Rsvp::COMMENT_TYPE,
				'author_email' => $email,
				'count'        => true,
			)
		);

		if ( $existing_rsvp > 0 ) {
			$response = array(
				'success' => false,
				'message' => __( "You've already RSVP'd to this event.", 'gatherpress' ),
			);
			return new WP_REST_Response( $response, 409 );
		}

		// Insert the comment.
		$comment_id = wp_insert_comment( $comment_data );

		if ( $comment_id && ! is_wp_error( $comment_id ) ) {
			// Set RSVP status to attending.
			wp_set_object_terms( $comment_id, 'attending', Rsvp::TAXONOMY );

			// Handle email updates preference.
			if ( $email_updates ) {
				update_comment_meta( $comment_id, 'gatherpress_event_email_updates', 1 );
			}

			// Validate and save custom fields.
			$this->save_custom_fields( $request, $post_id, $comment_id );

			// Generate and send confirmation email.
			$rsvp_token = new Rsvp_Token( $comment_id );
			$rsvp_token->generate_token()->send_rsvp_confirmation_email();

			$success = true;
			$message = __( 'Your RSVP has been submitted successfully! Please check your email for a confirmation link.', 'gatherpress' );

			// Get updated responses for the frontend.
			$event     = new Event( $post_id );
			$responses = $event->rsvp->responses();
		} else {
			$message = __( 'There was an error processing your RSVP. Please try again.', 'gatherpress' );
		}

		$response = array(
			'success'    => $success,
			'message'    => $message,
			'comment_id' => $comment_id,
			'responses'  => isset( $responses ) ? $responses : array(),
		);

		return new WP_REST_Response( $response );
	}

	/**
	 * Handle RSVP responses REST endpoint request.
	 *
	 * Retrieves RSVP response data for a given event post ID. Validates that the post
	 * is an event type before returning response data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request REST API request object containing post_id parameter.
	 * @return WP_REST_Response Response containing success status and RSVP data.
	 */
	public function rsvp_responses( WP_REST_Request $request ): WP_REST_Response {
		$params    = $request->get_params();
		$post_id   = intval( $params['post_id'] );
		$success   = false;
		$responses = array();

		if ( Event::POST_TYPE === get_post_type( $post_id ) ) {
			$success   = true;
			$rsvp      = new Rsvp( $post_id );
			$responses = $rsvp->responses();
		}

		$response = array(
			'success' => $success,
			'data'    => $responses,
		);

		return new WP_REST_Response( $response );
	}

	/**
	 * Validate and save custom fields from form submission.
	 *
	 * This method validates custom form fields against the stored schema
	 * and saves valid fields as comment meta to prevent field injection attacks.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request    The REST API request object.
	 * @param int             $post_id    The event post ID.
	 * @param int             $comment_id The comment ID where fields will be saved.
	 * @return void
	 */
	private function save_custom_fields( WP_REST_Request $request, int $post_id, int $comment_id ): void {
		$form_schema_id = $request->get_param( 'gatherpress_form_schema_id' );

		if ( empty( $form_schema_id ) ) {
			return; // No schema ID provided.
		}

		// Get stored schemas for this post.
		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		if ( empty( $schemas ) || ! isset( $schemas[ $form_schema_id ] ) ) {
			return; // No schema found for this form.
		}

		$form_schema = $schemas[ $form_schema_id ];
		$fields      = $form_schema['fields'] ?? array();

		// Validate each field against the schema.
		foreach ( $fields as $field_name => $field_config ) {
			$submitted_value = $request->get_param( $field_config['name'] );

			if ( null === $submitted_value ) {
				continue; // Field not submitted.
			}

			// Validate and sanitize the field value using shared logic.
			$validated_value = Rsvp_Setup::get_instance()->validate_custom_field_value( $submitted_value, $field_config );
			if ( false !== $validated_value ) {
				// Save as comment meta with prefix to avoid conflicts.
				$meta_key = 'gatherpress_custom_' . sanitize_key( $field_config['name'] );
				update_comment_meta( $comment_id, $meta_key, $validated_value );
			}
		}
	}

	/**
	 * Validate a custom field value against its configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value  The submitted field value.
	 * @param array $config The field configuration from schema.
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_custom_field_value( $value, array $config ): bool {
		// Check required fields.
		if ( $config['required'] && empty( $value ) ) {
			return false;
		}

		// Type-specific validation.
		switch ( $config['type'] ) {
			case 'email':
				return empty( $value ) || is_email( $value );

			case 'select':
			case 'radio':
				$allowed_options = $config['options'] ?? array();
				return empty( $value ) || in_array( $value, $allowed_options, true );

			case 'textarea':
				$max_length = $config['max_length'] ?? 1000;
				return empty( $value ) || strlen( $value ) <= $max_length;

			case 'checkbox':
				return in_array( $value, array( true, false, 'on', '', '1', '0', 1, 0 ), true );

			default:
				return true; // Allow other field types.
		}
	}

	/**
	 * Sanitize a custom field value based on its type.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value  The field value to sanitize.
	 * @param array $config The field configuration.
	 * @return mixed The sanitized value.
	 */
	private function sanitize_custom_field_value( $value, array $config ) {
		switch ( $config['type'] ) {
			case 'email':
				return sanitize_email( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'checkbox':
				return (bool) $value;

			case 'select':
			case 'radio':
			default:
				return sanitize_text_field( $value );
		}
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
