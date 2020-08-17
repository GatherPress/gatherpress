<?php

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest_Api {

	use Singleton;

	/**
	 * Query constructor.
	 */
	protected function __construct() {

		$this->_setup_hooks();

	}

	/**
	 * Setup hooks.
	 */
	protected function _setup_hooks() {

		add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );

	}

	/**
	 * REST API endpoints for GatherPress events.
	 *
	 * @todo needs some current user can check.
	 */
	public function register_endpoints() {

		// All event routes.
		$routes = $this->_get_event_routes();

		foreach ( $routes as $route ) {
			register_rest_route(
				sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ),
				sprintf( '/%s', $route['route'] ),
				$route['args']
			);
		}

	}

	protected function _get_event_routes() {
		return [
			[
				'route' => 'datetime',
				'args'  => [
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_datetime' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'_wpnonce'       => [
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required'          => true,
						],
						'post_id'        => [
							'required'          => true,
							'validate_callback' => [ $this, 'validate_event_post_id' ],
						],
						'datetime_start' => [
							'required'          => true,
							'validate_callback' => [ $this, 'validate_datetime' ],
						],
						'datetime_end'   => [
							'required'          => true,
							'validate_callback' => [ $this, 'validate_datetime' ],
						],
					],
				]
			],
			[
				'route' => 'announce',
				'args'  => [
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'announce' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'_wpnonce'       => [
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required'          => true,
						],
						'post_id'        => [
							'required'          => true,
							'validate_callback' => [ $this, 'validate_event_post_id' ],
						],
					],
				]
			],
			[
				'route' => 'attendance',
				'args'  => [
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_attendance' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'_wpnonce'       => [
							/**
							 * WordPress will verify the nonce cookie, we just want to ensure nonce was passed as param.
							 *
							 * @see https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
							 */
							'required'          => true,
						],
						'post_id'        => [
							'required'          => true,
							'validate_callback' => [ $this, 'validate_event_post_id' ],
						],
						// @todo add logic for allowing event organizers to add people to events as attendees.
						//					'user_id'        => [
						//						'required'          => false,
						//						'validate_callback' => [ $this, 'validate_event_post_id' ],
						//					],
						'status' => [
							'required'          => true,
							'validate_callback' => [ $this, 'validate_attendance_status' ],
						],
					],
				]
			],
		];
	}

	public function validate_attendance_status( $param ) : bool {

		return ( 'attending' === $param || 'not_attending' === $param );

	}

	/**
	 * Validate Event Post ID.
	 *
	 * @param $param
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
	 * Validate Datetime.
	 *
	 * @param $param
	 *
	 * @return bool
	 */
	public function validate_datetime( $param ) : bool {

		return (bool) \DateTime::createFromFormat( 'Y-m-d H:i:s', $param );

	}

	/**
	 * Update custom event table with start and end Datetime.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function update_datetime( \WP_REST_Request $request ) {

		$event = Event::get_instance();

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
				]
			);
		}

		$params   = wp_parse_args( $request->get_params(), $request->get_default_params() );
		$success  = $event->save_datetimes( $params );
		$response = [
			'success' => $success,
		];

		return new \WP_REST_Response( $response );

	}

	public function announce( \WP_REST_Request $request ) {

		$params   = $request->get_params();
		$post_id  = intval( $params['post_id'] );
		$email    = Email::get_instance();

		$success  = $email->event_announce( $post_id );
		$response = [
			'success' => $success,
		];

		return new \WP_REST_Response( $response );

	}

	public function update_attendance( \WP_REST_Request $request ) {

		$params          = $request->get_params();
		$attendee        = Attendee::get_instance();
		$event           = Event::get_instance();
		$success         = false;
		$current_user_id = get_current_user_id();
		$blog_id         = get_current_blog_id();
		$user_id         = isset( $params['user_id'] ) ? intval( $params['user_id'] ) : $current_user_id;
		$post_id         = intval( $params['post_id'] );
		$status          = sanitize_key( $params['status'] );

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
			&& ! $event->has_event_past( $post_id )
		) {
			$status = $attendee->save_attendee( $post_id, $user_id, $status );

			if ( in_array( $status, $attendee->statuses, true ) ) {
				$success = true;
			}

		}

		$response = [
			'success'    => (bool) $success,
			'status'     => $status,
			'attendees'  => $attendee->get_attendees( $post_id ),
		];

		return new \WP_REST_Response( $response );

	}

}

// EOF
