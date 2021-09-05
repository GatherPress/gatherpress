<?php
/**
 * Class is responsible for registering REST API endpoints.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use \GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rest_Api.
 */
class Rest_Api {

	use Singleton;

	/**
	 * Query constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * REST API endpoints for GatherPress events.
	 *
	 * @todo needs some current user can check.
	 */
	public function register_endpoints() {

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
	 * @return array[]
	 */
	protected function get_event_routes() {
		return array(
			array(
				'route' => 'datetime',
				'args'  => array(
					'methods'             => \WP_REST_Server::EDITABLE,
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
					),
				),
			),
			array(
				'route' => 'announce',
				'args'  => array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'announce' ),
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
					),
				),
			),
			array(
				'route' => 'attendance',
				'args'  => array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_attendance' ),
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
							'validate_callback' => array( $this, 'validate_attendance_status' ),
						),
					),
				),
			),
			array(
				'route' => 'markup_future_events',
				'args'  => array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'markup_future_events' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'_wpnonce'   => array(
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required' => false,
						),
						'max_number' => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_number' ),
						),
					),
				),
			),
			array(
				'route' => 'markup_past_events',
				'args'  => array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'markup_past_events' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'_wpnonce'   => array(
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required' => false,
						),
						'max_number' => array(
							'required'          => true,
							'validate_callback' => array( $this, 'validate_number' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Validate attendance status.
	 *
	 * @param string $param An attendance status.
	 *
	 * @return bool
	 */
	public function validate_attendance_status( $param ) : bool {
		return ( 'attending' === $param || 'not_attending' === $param );
	}

	/**
	 * Validate Event Post ID.
	 *
	 * @param int|string $param A Post ID to validate.
	 *
	 * @return bool
	 */
	public function validate_event_post_id( $param ) : bool {
		return (
			0 < intval( $param )
			&& is_numeric( $param )
			&& Event::POST_TYPE === get_post_type( $param )
		);
	}

	/**
	 * Validate number.
	 *
	 * @param int|string $param A Post ID to validate.
	 *
	 * @return bool
	 */
	public function validate_number( $param ) : bool {
		return (
			0 < intval( $param )
			&& is_numeric( $param )
		);
	}

	/**
	 * Validate Datetime.
	 *
	 * @param string $param A Date time to validate.
	 *
	 * @return bool
	 */
	public function validate_datetime( $param ) : bool {
		return (bool) \DateTime::createFromFormat( 'Y-m-d H:i:s', $param );
	}

	/**
	 * Update custom event table with start and end Datetime.
	 *
	 * @param \WP_REST_Request $request Contains data from the request.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_datetime( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
				)
			);
		}

		$params   = wp_parse_args( $request->get_params(), $request->get_default_params() );
		$success  = Event::save_datetimes( $params );
		$response = array(
			'success' => $success,
		);

		return new \WP_REST_Response( $response );
	}

	/**
	 * Announce an event to all members that subscribe to these notices.
	 *
	 * @param \WP_REST_Request $request Contains data from the request.
	 *
	 * @return \WP_REST_Response
	 */
	public function announce( \WP_REST_Request $request ) {
		$params  = $request->get_params();
		$post_id = intval( $params['post_id'] );
		$email   = Email::get_instance();

		$success  = $email->event_announce( $post_id );
		$response = array(
			'success' => $success,
		);

		return new \WP_REST_Response( $response );
	}

	/**
	 * Returns markup for future events.
	 *
	 * @param \WP_REST_Request $request Contains data from the request.
	 *
	 * @return \WP_REST_Response
	 */
	public function markup_future_events( \WP_REST_Request $request ) {
		$params   = $request->get_params();
		$attrs    = array(
			'maxNumberOfEvents' => intval( $params['max_number'] ),
		);
		$response = array(
			'markup' => Utility::render_template(
				sprintf( '%s/templates/blocks/upcoming-events.php', GATHERPRESS_CORE_PATH ),
				array(
					'attrs' => $attrs,
				)
			),
		);

		return new \WP_REST_Response( $response );
	}

	/**
	 * Returns markup for future events.
	 *
	 * @param \WP_REST_Request $request Contains data from the request.
	 *
	 * @return \WP_REST_Response
	 */
	public function markup_past_events( \WP_REST_Request $request ) {
		$params   = $request->get_params();
		$attrs    = array(
			'maxNumberOfEvents' => intval( $params['max_number'] ),
		);
		$response = array(
			'markup' => Utility::render_template(
				sprintf( '%s/templates/blocks/past-events.php', GATHERPRESS_CORE_PATH ),
				array(
					'attrs' => $attrs,
				)
			),
		);

		return new \WP_REST_Response( $response );
	}

	/**
	 * Update the attendance status for a user to an event.
	 *
	 * @param \WP_REST_Request $request Contains data from the request.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_attendance( \WP_REST_Request $request ) {
		$params          = $request->get_params();
		$success         = false;
		$current_user_id = get_current_user_id();
		$blog_id         = get_current_blog_id();
		$user_id         = isset( $params['user_id'] ) ? intval( $params['user_id'] ) : $current_user_id;
		$post_id         = intval( $params['post_id'] );
		$status          = sanitize_key( $params['status'] );
		$event           = new Event( $post_id );

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
			$status = $event->attendee->save_attendee( $user_id, $status );

			if ( in_array( $status, $event->attendee->statuses, true ) ) {
				$success = true;
			}
		}

		$response = array(
			'success'   => (bool) $success,
			'status'    => $status,
			'attendees' => $event->attendee->get_attendees(),
		);

		return new \WP_REST_Response( $response );
	}

}
