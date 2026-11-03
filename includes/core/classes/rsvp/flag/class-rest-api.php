<?php
/**
 * REST API for the check-in state of an RSVP.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Core\Rsvp\Flag;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp;
use GatherPress\Core\Traits\Singleton;
use WP_Comment;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Rest_Api.
 *
 * Sets the check-in state of an RSVP from the RSVP screen, without a page load.
 *
 * @since TBD
 */
final class Rest_Api {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Registers the check-in route.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/rsvp/(?P<id>\d+)/check-in',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_check_in' ),
				// The same capability as the RSVP screen and its bulk actions.
				'permission_callback' => static fn(): bool => current_user_can( Rsvp::CAPABILITY ),
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'state' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => static function ( $value ): bool {
							$values = wp_list_pluck( Check_In_Registry::get_instance()->get_options(), 'value' );

							return is_string( $value ) && in_array( $value, $values, true );
						},
					),
				),
			)
		);
	}

	/**
	 * Sets the check-in state of an approved RSVP.
	 *
	 * @since TBD
	 *
	 * @param WP_REST_Request $request The request, with the RSVP `id` and the `state`.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response|WP_Error The RSVP's state, its name and icon, or an error.
	 */
	public function set_check_in( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$rsvp_id = (int) $request['id'];
		$rsvp    = get_comment( $rsvp_id );

		if ( ! $rsvp instanceof WP_Comment || ! Rsvp::is_comment_type( $rsvp ) || '1' !== $rsvp->comment_approved ) {
			return new WP_Error(
				'gatherpress_rsvp_check_in_not_approved',
				__( 'Only an approved RSVP can be checked in.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		$registry = Check_In_Registry::get_instance();

		if ( ! $registry->set_state( $rsvp_id, (string) $request['state'] ) ) {
			return new WP_Error(
				'gatherpress_rsvp_check_in_not_saved',
				__( 'The check-in could not be saved.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( $registry->describe( $registry->get_state( $rsvp_id ) ) );
	}
}
