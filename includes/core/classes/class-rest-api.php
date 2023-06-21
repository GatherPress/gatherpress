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

if ( ! defined( 'ABSPATH' ) ) { // @codeCoverageIgnore
	exit; // @codeCoverageIgnore
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
		add_filter( sprintf( 'rest_prepare_%s', Event::POST_TYPE ), array( $this, 'prepare_event_data' ) );
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
						'timezone'       => array(
							'required'          => false,
							'validate_callback' => array( $this, 'validate_timezone' ),
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
				'route' => 'events-list',
				'args'  => array(
					'methods'             => \WP_REST_Server::READABLE,
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
	 * Validate attendance status.
	 *
	 * @param string $param An attendance status.
	 *
	 * @return bool
	 */
	public function validate_attendance_status( $param ): bool {
		return ( 'attending' === $param || 'not_attending' === $param );
	}

	/**
	 * Validate Event Post ID.
	 *
	 * @param int|string $param A Post ID to validate.
	 *
	 * @return bool
	 */
	public function validate_event_post_id( $param ): bool {
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
	public function validate_number( $param ): bool {
		return (
			0 < intval( $param )
			&& is_numeric( $param )
		);
	}

	/**
	 * Validate event list type.
	 *
	 * @param string $param event list type.
	 *
	 * @return bool
	 */
	public function validate_event_list_type( string $param ): bool {
		return in_array( $param, array( 'upcoming', 'past' ), true );
	}

	/**
	 * Validate Datetime.
	 *
	 * @param string $param A Date time to validate.
	 *
	 * @return bool
	 */
	public function validate_datetime( $param ): bool {
		return (bool) \DateTime::createFromFormat( 'Y-m-d H:i:s', $param );
	}

	/**
	 * Validate timezone.
	 *
	 * @param string $param A timezone to validate.
	 *
	 * @return bool
	 */
	public function validate_timezone( $param ): bool {
		return in_array( Event::maybe_convert_offset( $param ), Event::list_identifiers(), true );
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

		$params             = wp_parse_args( $request->get_params(), $request->get_default_params() );
		$params['timezone'] = Event::maybe_convert_offset( $params['timezone'] );
		$event              = new Event( $params['post_id'] );

		unset( $params['post_id'] );

		$success  = $event->save_datetimes( $params );
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
		$params   = $request->get_params();
		$post_id  = intval( $params['post_id'] );
		$event    = new Event( $post_id );
		$success  = $event->announce_via_email();
		$response = array(
			'success' => $success,
		);

		return new \WP_REST_Response( $response );
	}

	/**
	 * Returns events list.
	 *
	 * @param \WP_REST_Request $request Contains data from the request.
	 *
	 * @return \WP_REST_Response
	 */
	public function events_list( \WP_REST_Request $request ) {
		$params          = $request->get_params();
		$event_list_type = $params['event_list_type'];
		$max_number      = $this->max_number( (int) $params['max_number'], 5 );
		$posts           = array();
		$topics          = array();

		if ( ! empty( $params['topics'] ) ) {
			$topics = array_map(
				function( $slug ) {
					return sanitize_key( $slug );
				},
				explode( ',', $params['topics'] )
			);
		}

		$query = Query::get_instance()->get_events_list( $event_list_type, $max_number, $topics );

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
					'attendees'                => ( $event->attendee ) ? $event->attendee->attendees() : array(),
					'current_user'             => ( $event->attendee && $event->attendee->get( get_current_user_id() ) ) ? $event->attendee->get( get_current_user_id() ) : '',
					'venue'                    => ( $venue_information['name'] ? $event->get_venue_information() : null ),
				);
			}
		}

		wp_reset_postdata();

		return new \WP_REST_Response( $posts );
	}

	/**
	 * Check that max_number is 5 or less.
	 *
	 * @param int $number     Actual number.
	 * @param int $max_number Maximum number.
	 *
	 * @return int
	 */
	protected function max_number( int $number, int $max_number ): int {
		if ( $max_number < $number ) {
			$number = $max_number;
		}

		return $number;
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
			$status = $event->attendee->save( $user_id, $status, $guests );

			if ( in_array( $status, $event->attendee->statuses, true ) ) {
				$success = true;
			}
		}

		$response = array(
			'event_id'    => $post_id,
			'success'     => (bool) $success,
			'status'      => $status,
			'guests'      => $guests,
			'attendees'   => $event->attendee->attendees(),
			'online_link' => ( 'attending' === $status ) ? $online_link : '',
		);

		return new \WP_REST_Response( $response );
	}

	/**
	 * Edit data from event endpoint.
	 *
	 * @param \WP_REST_Response $response The response object.
	 *
	 * @return \WP_REST_Response
	 */
	public function prepare_event_data( \WP_REST_Response $response ) {
		// Remove online link meta data from FE endpoint.
		if ( ! is_admin() ) {
			$response->data['meta']['_online_event_link'] = '';
		}

		return $response;
	}

}
