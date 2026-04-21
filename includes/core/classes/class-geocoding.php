<?php
/**
 * Handles geocoding functionality via REST API.
 *
 * Proxies forward search and geocoding to Photon (OpenStreetMap-based) so the
 * plugin does not use the public OSMF Nominatim service for autocomplete-style
 * traffic. Requests run server-side with a valid User-Agent.
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
 * Provides REST API endpoints for geocoding and address search.
 *
 * @since 1.0.0
 */
class Geocoding {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Default Photon API base URL (forward search / geocode).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PHOTON_API_URL = 'https://photon.komoot.io/api';

	/**
	 * Minimum trimmed query length before calling Photon for search.
	 *
	 * This value is the single source of truth: it is also exposed to the block
	 * editor via `block_editor_settings_all` so the JS short-query guard stays
	 * in lockstep with the server without a second hardcoded copy.
	 *
	 * @since 1.0.0
	 */
	public const ADDRESS_SEARCH_MIN_QUERY_LENGTH = 3;

	/**
	 * Transient key prefix for cached Photon search results.
	 *
	 * @since 1.0.0
	 */
	private const SEARCH_CACHE_PREFIX = 'gatherpress_photon_search_';

	/**
	 * Time-to-live for cached Photon search results, in seconds.
	 *
	 * @since 1.0.0
	 */
	private const SEARCH_CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Transient key prefix for cached Photon geocode (address → lat/long) results.
	 *
	 * @since 1.0.0
	 */
	private const GEOCODE_CACHE_PREFIX = 'gatherpress_photon_geocode_';

	/**
	 * Time-to-live for cached Photon geocode results, in seconds.
	 *
	 * @since 1.0.0
	 */
	private const GEOCODE_CACHE_TTL = 15 * MINUTE_IN_SECONDS;

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
		add_filter( 'block_editor_settings_all', array( $this, 'add_editor_settings' ) );
	}

	/**
	 * Exposes geocoding-related config to the block editor.
	 *
	 * Publishes `addressSearchMinQueryLength` under `settings.gatherpress.config`
	 * so the JS `geocodeAddress` / `fetchAddressSuggestions` helpers can read the
	 * same minimum query length the REST endpoints enforce.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The block editor settings array.
	 * @return array The modified settings.
	 */
	public function add_editor_settings( array $settings ): array {
		if ( ! isset( $settings['gatherpress'] ) ) {
			$settings['gatherpress'] = array();
		}
		if ( ! isset( $settings['gatherpress']['config'] ) ) {
			$settings['gatherpress']['config'] = array();
		}

		$settings['gatherpress']['config']['addressSearchMinQueryLength'] =
			self::ADDRESS_SEARCH_MIN_QUERY_LENGTH;

		return $settings;
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
	 * Geocodes an address using the Photon API (GeoJSON).
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

		// Cap oversize input for parity with search_addresses(); protects upstream from pathological requests.
		$address = mb_substr( trim( $address ), 0, 200 );

		$language  = $this->get_language_code();
		$cache_key = self::GEOCODE_CACHE_PREFIX . md5( $address . '|' . $language ); // NOSONAR.
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return new WP_REST_Response( $cached, 200 );
		}

		$url = add_query_arg(
			array(
				'q'     => $address,
				'limit' => 1,
				'lang'  => $language,
			),
			$this->get_photon_api_url()
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

		$this->maybe_log_json_decode_failure( $body, $data, 'geocode_address' );

		if ( ! empty( $data['features'] ) && isset( $data['features'][0]['geometry']['coordinates'] ) ) {
			$coordinates = $data['features'][0]['geometry']['coordinates'];
			$result      = array(
				'latitude'  => (string) $coordinates[1],
				'longitude' => (string) $coordinates[0],
				'error'     => null,
			);

			set_transient( $cache_key, $result, self::GEOCODE_CACHE_TTL );

			return new WP_REST_Response( $result, 200 );
		}

		$not_found = array(
			'latitude'  => '',
			'longitude' => '',
			'error'     => __( 'Could not find location. Please check the address and try again.', 'gatherpress' ),
		);

		set_transient( $cache_key, $not_found, self::GEOCODE_CACHE_TTL );

		return new WP_REST_Response( $not_found, 200 );
	}

	/**
	 * Returns Photon search hits for editor autocomplete (formatted label + coordinates).
	 *
	 * Each row has `label` (postal-style), `latitude`, and `longitude`.
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

		if ( mb_strlen( $query ) < self::ADDRESS_SEARCH_MIN_QUERY_LENGTH ) {
			return new WP_REST_Response(
				array(
					'suggestions' => array(),
				),
				200
			);
		}

		$language  = $this->get_language_code();
		$cache_key = self::SEARCH_CACHE_PREFIX . md5( $query . '|' . $language ); // NOSONAR.
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return new WP_REST_Response(
				array(
					'suggestions' => $cached,
				),
				200
			);
		}

		$url = add_query_arg(
			array(
				'q'     => $query,
				'limit' => 5,
				'lang'  => $language,
			),
			$this->get_photon_api_url()
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

		$this->maybe_log_json_decode_failure( $body, $data, 'search_addresses' );

		if ( ! is_array( $data ) || empty( $data['features'] ) || ! is_array( $data['features'] ) ) {
			set_transient( $cache_key, array(), self::SEARCH_CACHE_TTL );

			return new WP_REST_Response(
				array(
					'suggestions' => array(),
				),
				200
			);
		}

		$suggestions = array();

		foreach ( $data['features'] as $feature ) {
			if ( ! is_array( $feature ) ) {
				continue;
			}

			$coords = $feature['geometry']['coordinates'] ?? null;
			if ( ! is_array( $coords ) || count( $coords ) < 2 ) {
				continue;
			}

			$properties = isset( $feature['properties'] ) && is_array( $feature['properties'] )
				? $feature['properties']
				: array();

			$label = $this->format_photon_feature_label( $properties );

			if ( '' === $label ) {
				continue;
			}

			$suggestions[] = array(
				'label'     => $label,
				'latitude'  => (string) $coords[1],
				'longitude' => (string) $coords[0],
			);
		}

		set_transient( $cache_key, $suggestions, self::SEARCH_CACHE_TTL );

		return new WP_REST_Response(
			array(
				'suggestions' => $suggestions,
			),
			200
		);
	}

	/**
	 * Builds a one-line label from Photon GeocodeJson properties (country omitted).
	 *
	 * @since 1.0.0
	 *
	 * @param array $properties Photon `properties` object.
	 * @return string Non-empty label or empty string.
	 */
	private function format_photon_feature_label( array $properties ): string {
		$housenumber = isset( $properties['housenumber'] ) ? trim( (string) $properties['housenumber'] ) : '';
		$street      = isset( $properties['street'] ) ? trim( (string) $properties['street'] ) : '';
		$name        = isset( $properties['name'] ) ? trim( (string) $properties['name'] ) : '';

		$street_line = trim( $housenumber . ' ' . $street );
		if ( '' === $street_line ) {
			$street_line = $name;
		}

		$locality = '';
		foreach ( array( 'city', 'district', 'county' ) as $key ) {
			if ( ! empty( $properties[ $key ] ) ) {
				$locality = trim( (string) $properties[ $key ] );
				break;
			}
		}

		$region = isset( $properties['state'] ) ? trim( (string) $properties['state'] ) : '';

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
		if ( ! empty( $properties['postcode'] ) ) {
			$postcode = trim( (string) $properties['postcode'] );
			if ( '' !== $postcode ) {
				$parts[] = $postcode;
			}
		}

		$parts = array_filter(
			array_map( 'trim', $parts ),
			static function ( $part ): bool {
				return '' !== $part;
			}
		);

		return implode( ', ', $parts );
	}

	/**
	 * Emits a `WP_DEBUG` log line when Photon returns a body that could not be JSON-decoded.
	 *
	 * The endpoint handlers already fall through to an empty-suggestions / not-found
	 * response when the body is unusable, so callers see graceful behavior; this
	 * line is only meant to aid triage when an upstream incident is suspected.
	 *
	 * @since 1.0.0
	 *
	 * @param string $body    Raw response body.
	 * @param mixed  $decoded Result of json_decode (null when decode failed).
	 * @param string $context Short label identifying the caller ('geocode_address', 'search_addresses').
	 * @return void
	 */
	private function maybe_log_json_decode_failure( string $body, $decoded, string $context ): void {
		if ( null !== $decoded ) {
			return;
		}
		if ( '' === trim( $body ) ) {
			return;
		}

		/**
		 * Filters whether to write a PHP error-log line when Photon returns a body
		 * that can't be JSON-decoded.
		 *
		 * Defaults to `WP_DEBUG` so production sites stay quiet, but can be
		 * force-enabled (e.g. for tests, or in staging) via:
		 *
		 *     add_filter( 'gatherpress_log_geocoding_errors', '__return_true' );
		 *
		 * @since 1.0.0
		 *
		 * @param bool $should_log Default: value of WP_DEBUG.
		 */
		$should_log = (bool) apply_filters(
			'gatherpress_log_geocoding_errors',
			defined( 'WP_DEBUG' ) && WP_DEBUG
		);
		if ( ! $should_log ) {
			return;
		}

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'GatherPress geocoding: %s received non-JSON body (first 200 chars): %s',
				$context,
				mb_substr( $body, 0, 200 )
			)
		);
	}

	/**
	 * Photon API base URL (filterable for self-hosted instances).
	 *
	 * @since 1.0.0
	 *
	 * @return string Base URL for Photon `/api` requests.
	 */
	private function get_photon_api_url(): string {
		/**
		 * Filters the Photon API base URL used for geocoding and address search.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Default Photon API URL (e.g. https://photon.komoot.io/api).
		 */
		$filtered = (string) apply_filters( 'gatherpress_photon_api_url', self::PHOTON_API_URL );

		// Fall back to the default when a filter produces an unusable URL; keeps
		// outbound requests routed through wp_safe_remote_get against a real host.
		if ( '' === $filtered || false === wp_http_validate_url( $filtered ) ) {
			return self::PHOTON_API_URL;
		}

		return $filtered;
	}

	/**
	 * Gets the language code for Photon `lang` parameter.
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
	 * Gets the User-Agent string for outbound geocoding requests.
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
