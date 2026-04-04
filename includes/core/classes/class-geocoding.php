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

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/geocode/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_addresses' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'q' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Address search query.', 'gatherpress' ),
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
	 * Returns Nominatim search hits for editor autocomplete (formatted label + coordinates).
	 *
	 * Each row has `label` (postal-style, no POI/venue prefix), `latitude`, and `longitude`.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Suggestions or error.
	 */
	public function search_addresses( WP_REST_Request $request ) {
		$query = $request->get_param( 'q' );

		if ( empty( $query ) || '' === trim( $query ) ) {
			return new WP_Error(
				'missing_query',
				__( 'Search query is required.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		$query = mb_substr( trim( $query ), 0, 200 );

		if ( mb_strlen( $query ) < 2 ) {
			return new WP_REST_Response(
				array(
					'suggestions' => array(),
				),
				200
			);
		}

		$url = add_query_arg(
			array(
				'q'               => $query,
				'format'          => 'json',
				'limit'           => 5,
				'addressdetails'  => 1,
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
				'geocoding_search_failed',
				$response->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return new WP_Error(
				'geocoding_search_failed',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Address search failed with status %d.', 'gatherpress' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_REST_Response(
				array(
					'suggestions' => array(),
				),
				200
			);
		}

		$suggestions = array();

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			if ( ! isset( $item['lat'], $item['lon'] ) ) {
				continue;
			}

			$label = $this->format_nominatim_search_label( $item );

			if ( '' === $label ) {
				continue;
			}

			$suggestions[] = array(
				'label'     => $label,
				'latitude'  => (string) $item['lat'],
				'longitude' => (string) $item['lon'],
			);
		}

		return new WP_REST_Response(
			array(
				'suggestions' => $suggestions,
			),
			200
		);
	}

	/**
	 * Builds a one-line postal-style label from a Nominatim search hit.
	 *
	 * Prefers structured `address` (no country). Falls back to `display_name` with a leading
	 * POI/venue segment removed when it matches known `address` keys (amenity, shop, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @param array $item Raw Nominatim JSON object.
	 * @return string Non-empty label or empty string.
	 */
	private function format_nominatim_search_label( array $item ): string {
		$address = isset( $item['address'] ) && is_array( $item['address'] ) ? $item['address'] : array();

		$from_structured = $this->build_nominatim_label_from_address( $address );

		if ( '' !== $from_structured ) {
			return $from_structured;
		}

		$display_name = isset( $item['display_name'] ) ? trim( (string) $item['display_name'] ) : '';

		if ( '' === $display_name ) {
			return '';
		}

		return $this->strip_nominatim_poi_prefix_from_display_name( $display_name, $address );
	}

	/**
	 * Composes label from Nominatim `address` keys (excludes country).
	 *
	 * @since 1.0.0
	 *
	 * @param array $address Nominatim address object.
	 * @return string Label or empty when no usable parts.
	 */
	private function build_nominatim_label_from_address( array $address ): string {
		if ( empty( $address ) ) {
			return '';
		}

		$street_line = $this->build_nominatim_street_line( $address );
		$locality    = $this->first_nominatim_address_field(
			$address,
			array( 'city', 'town', 'village', 'hamlet', 'municipality', 'suburb' )
		);
		$region      = $this->first_nominatim_address_field(
			$address,
			array( 'state', 'region', 'county' )
		);

		$parts = array();

		if ( '' !== $street_line ) {
			$parts[] = $street_line;
		}
		if ( '' !== $locality ) {
			$parts[] = $locality;
		}
		if ( '' !== $region && 0 !== strcasecmp( $region, $locality ) ) {
			$parts[] = $region;
		}
		if ( ! empty( $address['postcode'] ) ) {
			$parts[] = trim( (string) $address['postcode'] );
		}

		$parts = array_filter(
			array_map( 'trim', $parts ),
			static function ( $p ) {
				return '' !== $p;
			}
		);

		return implode( ', ', $parts );
	}

	/**
	 * Builds house number + road (or equivalent) from Nominatim `address`.
	 *
	 * @since 1.0.0
	 *
	 * @param array $address Nominatim address object.
	 * @return string Street line or empty.
	 */
	private function build_nominatim_street_line( array $address ): string {
		$house = isset( $address['house_number'] ) ? trim( (string) $address['house_number'] ) : '';
		$road  = $this->first_nominatim_address_field(
			$address,
			array( 'road', 'pedestrian', 'path', 'footway', 'residential' )
		);

		$chunks = array();
		if ( '' !== $house ) {
			$chunks[] = $house;
		}
		if ( '' !== $road ) {
			$chunks[] = $road;
		}

		return trim( implode( ' ', $chunks ) );
	}

	/**
	 * First non-empty string among ordered Nominatim address keys.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $address Nominatim address object.
	 * @param string[] $keys    Preferred key order.
	 * @return string Value or empty.
	 */
	private function first_nominatim_address_field( array $address, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! empty( $address[ $key ] ) ) {
				return trim( (string) $address[ $key ] );
			}
		}

		return '';
	}

	/**
	 * Removes a leading POI/venue segment from display_name when it matches structured fields.
	 *
	 * @since 1.0.0
	 *
	 * @param string $display_name Nominatim display_name.
	 * @param array  $address      Nominatim address object.
	 * @return string Cleaned string.
	 */
	private function strip_nominatim_poi_prefix_from_display_name( string $display_name, array $address ): string {
		$poi_keys = array(
			'amenity',
			'shop',
			'tourism',
			'office',
			'leisure',
			'craft',
			'club',
			'aerialway',
			'historic',
			'building',
			'man_made',
		);

		$poi_values = array();

		foreach ( $poi_keys as $key ) {
			if ( empty( $address[ $key ] ) ) {
				continue;
			}
			$val = trim( (string) $address[ $key ] );
			if ( '' !== $val ) {
				$poi_values[] = $val;
			}
		}

		$poi_values = array_values( array_unique( $poi_values ) );

		if ( empty( $poi_values ) ) {
			return $display_name;
		}

		$segments = array_map( 'trim', explode( ',', $display_name ) );

		if ( empty( $segments ) || '' === $segments[0] ) {
			return $display_name;
		}

		$first = $segments[0];

		foreach ( $poi_values as $poi ) {
			if ( 0 === strcasecmp( $first, $poi ) ) {
				array_shift( $segments );
				$rest = implode(
					', ',
					array_filter(
						array_map( 'trim', $segments ),
						static function ( $s ) {
							return '' !== $s;
						}
					)
				);

				return trim( $rest );
			}
		}

		return $display_name;
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
