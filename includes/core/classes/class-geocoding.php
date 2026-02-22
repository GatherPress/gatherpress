<?php
/**
 * Handles geocoding functionality via REST API.
 *
 * Proxies requests to Nominatim OpenStreetMap API to avoid CORS issues
 * and comply with Nominatim's usage policy (proper User-Agent header).
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Geocoding.
 *
 * Provides a REST API endpoint for geocoding addresses using Nominatim.
 *
 * @since 1.0.0
 */
class Geocoding {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Nominatim API URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NOMINATIM_API_URL = 'https://nominatim.openstreetmap.org/search';

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
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Registers REST API endpoints for geocoding.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/geocode',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'geocode_address' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'address' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'The address to geocode.', 'gatherpress' ),
					),
				),
			)
		);
	}

	/**
	 * Geocodes an address using Nominatim API.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with coordinates or error.
	 */
	public function geocode_address( WP_REST_Request $request ) {
		$address = $request->get_param( 'address' );

		if ( empty( $address ) ) {
			return new WP_Error(
				'missing_address',
				__( 'Address is required.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		$url = add_query_arg(
			array(
				'q'               => $address,
				'format'          => 'geojson',
				'limit'           => 1,
				'accept-language' => $this->get_language_code(),
			),
			self::NOMINATIM_API_URL
		);

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers' => array(
					'User-Agent' => $this->get_user_agent(),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'geocoding_failed',
				$response->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'geocoding_failed',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Geocoding request failed with status %d.', 'gatherpress' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! empty( $data['features'] ) && isset( $data['features'][0]['geometry']['coordinates'] ) ) {
			$coordinates = $data['features'][0]['geometry']['coordinates'];

			return new WP_REST_Response(
				array(
					'latitude'  => (string) $coordinates[1],
					'longitude' => (string) $coordinates[0],
					'error'     => null,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'latitude'  => '',
				'longitude' => '',
				'error'     => __( 'Could not find location. Please check the address and try again.', 'gatherpress' ),
			),
			200
		);
	}

	/**
	 * Gets the language code for Accept-Language header.
	 *
	 * @since 1.0.0
	 *
	 * @return string Language code (e.g., 'en', 'de').
	 */
	private function get_language_code(): string {
		$locale = get_locale();

		// Convert 'en_US' to 'en'.
		return explode( '_', $locale )[0];
	}

	/**
	 * Gets the User-Agent string for Nominatim requests.
	 *
	 * Nominatim requires a valid User-Agent header identifying the application.
	 *
	 * @since 1.0.0
	 *
	 * @return string User-Agent header value.
	 */
	private function get_user_agent(): string {
		return sprintf(
			'GatherPress/%s (WordPress/%s; %s)',
			GATHERPRESS_VERSION,
			get_bloginfo( 'version' ),
			home_url()
		);
	}
}
