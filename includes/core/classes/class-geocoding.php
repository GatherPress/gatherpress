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
use GatherPress\Core\Utility;
use GatherPress\Core\Venue\Meta as Venue_Meta;
use WP_Error;
use WP_Post;
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
	 * Cron action fired to backfill structured-address venue meta after a
	 * `gatherpress_address` value changes. Handler signature: `( int $post_id )`.
	 *
	 * @since 1.0.0
	 */
	public const CRON_ACTION = 'gatherpress_async_geocode_venue';

	/**
	 * Delay between the address-change save and the cron firing, in seconds.
	 *
	 * Short enough to feel "near-realtime" on an active site, long enough that
	 * the originating save has fully committed and `gatherpress_address`
	 * reads in the cron handler return the new value (not a transient
	 * mid-save state).
	 *
	 * @since 1.0.0
	 */
	private const CRON_DELAY_SECONDS = 5;

	/**
	 * Length of the per-user rate-limit window for the geocode REST
	 * endpoints, in seconds. Both `/geocode` and `/geocode/search` share
	 * the same per-user bucket — one user typing into autocomplete and
	 * then saving a venue is still one continuous flow.
	 *
	 * @since 1.0.0
	 */
	private const GEOCODE_RATE_LIMIT_WINDOW_SECONDS = 60;

	/**
	 * Default ceiling for the per-user rate-limit window. Roomy enough
	 * for one user typing through a 25-character address with debounced
	 * autocomplete (~10 requests) plus a couple of save-time reverse
	 * geocodes; tight enough to catch a runaway client or scripted abuse.
	 *
	 * Filterable via `gatherpress_geocode_rate_limit_per_minute`.
	 *
	 * @since 1.0.0
	 */
	private const GEOCODE_RATE_LIMIT_DEFAULT_PER_MINUTE = 30;

	/**
	 * Transient key prefix for the per-user geocode rate-limit bucket.
	 * Keyed on user ID; the suffix is appended at use site.
	 *
	 * @since 1.0.0
	 */
	private const GEOCODE_RATE_LIMIT_TRANSIENT_PREFIX = 'gatherpress_geocode_rate_';

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

		// `update_post_meta` short-circuits in core when the new value matches
		// the old one, so `updated_post_meta` only fires on real changes —
		// exactly the "only-on-change" gate we want for the geocode cron.
		// `added_post_meta` covers the first-write path (new venue create).
		add_action( 'updated_post_meta', array( $this, 'maybe_schedule_geocode' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'maybe_schedule_geocode' ), 10, 3 );

		add_action( self::CRON_ACTION, array( $this, 'async_geocode_venue' ) );
	}

	/**
	 * `added_post_meta` / `updated_post_meta` listener — schedules an async
	 * geocode whenever a venue post's `gatherpress_address` is added or
	 * changed.
	 *
	 * Both hooks fire from `add_metadata()` / `update_metadata()` only after
	 * core's "is the new value different from the old?" check passes, so this
	 * naturally short-circuits when a save touched something else but left
	 * the address untouched. That property is what makes the structured-
	 * field meta self-preserving: if a user manually edits a structured
	 * piece (e.g. fixes a county Photon got wrong) and saves without
	 * changing `gatherpress_address`, no cron fires and their correction
	 * stays intact.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $meta_id  Meta row ID. Unused (signature requirement).
	 * @param int    $post_id  Post ID the meta belongs to.
	 * @param string $meta_key Meta key being added/updated.
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) -- $meta_id is required
	 * by WP's added_post_meta / updated_post_meta action signatures.
	 */
	public function maybe_schedule_geocode( int $meta_id, int $post_id, string $meta_key ): void {
		if ( 'gatherpress_address' !== $meta_key ) {
			return;
		}

		// Skip during bulk imports (WP-CLI, importer plugins) so a multi-venue
		// import doesn't fan out to N Photon round-trips. Importers can run
		// a single backfill pass afterwards via direct `geocode_to_result()`
		// calls if they need the structured fields populated.
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) { // @codeCoverageIgnore
			return; // @codeCoverageIgnore
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! post_type_supports( $post->post_type, 'gatherpress-venue-information' ) ) {
			return;
		}

		/**
		 * Filters whether the async geocode should run on venue save.
		 *
		 * Hosts that need to control egress (firewalled corp installs,
		 * privacy-sensitive setups, dev environments without Photon access)
		 * can return false here to suppress the cron. Structured-address
		 * fields then stay at their last persisted values until the filter
		 * is re-enabled or `update_post_meta` is called directly from
		 * trusted code.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled True to schedule the geocode, false to skip.
		 * @param int  $post_id Venue post ID.
		 */
		if ( ! apply_filters( 'gatherpress_geocode_on_save_enabled', true, $post_id ) ) {
			return;
		}

		$args = array( $post_id );

		/**
		 * Filter the geocode enqueue call to take over scheduling.
		 *
		 * Return any non-null value from this filter to suppress both the
		 * WP-Cron dedup check and the `wp_schedule_single_event()` call —
		 * a companion plugin that hooks this filter (e.g. one that routes
		 * the fanout through Action Scheduler) owns the full scheduling
		 * path end-to-end, including its own dedup since the fanout
		 * by-passes `wp_next_scheduled()`. Mirrors the core `pre_*` filter
		 * convention: `null` means "pass through to the default";
		 * everything else, including falsy values like `false`, `0`, and
		 * `''`, short-circuits.
		 *
		 * Core ignores the return value past the null check, so a callback
		 * is free to return whatever is useful to itself — the established
		 * convention is a scheduler-specific identifier (e.g. the Action
		 * Scheduler action ID returned by `as_enqueue_async_action()`) so
		 * other filters / debug tooling downstream can correlate the job.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed  $short_circuit Non-null to suppress the default enqueue.
		 * @param string $hook          Action hook name fired when the job runs.
		 * @param array  $args          Args passed to the action hook when the job runs: `array( $post_id )`.
		 */
		$short_circuit = apply_filters(
			'gatherpress_async_geocode_pre_enqueue_job',
			null,
			self::CRON_ACTION,
			$args
		);

		if ( null !== $short_circuit ) {
			return;
		}

		if ( false !== wp_next_scheduled( self::CRON_ACTION, $args ) ) {
			return;
		}

		/**
		 * Filters the delay between an address-change save and the cron firing.
		 *
		 * Default 5 seconds is short enough to feel near-realtime and long
		 * enough that the originating save has fully committed. Sites with
		 * heavy save hooks (revisions fanning out, multilingual sync, etc.)
		 * may need longer; sites that batch saves can pass a larger value to
		 * coalesce. Returning 0 fires effectively immediately.
		 *
		 * @since 1.0.0
		 *
		 * @param int $delay   Delay in seconds. Default 5.
		 * @param int $post_id Venue post ID.
		 */
		$delay = (int) apply_filters( 'gatherpress_async_geocode_delay', self::CRON_DELAY_SECONDS, $post_id );

		wp_schedule_single_event( time() + max( 0, $delay ), self::CRON_ACTION, $args );
	}

	/**
	 * Cron handler — backfills structured-address venue meta from Photon.
	 *
	 * Runs ~5 seconds after a venue's `gatherpress_address` is added or
	 * changed. Reads the (now-committed) address, hits Photon via the
	 * shared `geocode_to_result()` method, and overwrites all 8
	 * structured-address meta keys with whatever Photon returned. On a
	 * successful Photon response, also overwrites `gatherpress_latitude`
	 * and `gatherpress_longitude` so freeform-typed addresses (which never
	 * trigger the JS-side autocomplete that pre-fills coordinates) stay in
	 * sync with the structured pieces derived from the same response.
	 *
	 * Failure modes:
	 * - Empty address (cleared by the user): all 8 structured keys are
	 *   emptied so stale city/state/postcode pieces don't outlive the
	 *   address they described. Lat/long are intentionally left alone in
	 *   case the user manually entered coordinates for a venue without a
	 *   street address (a remote campsite, etc.).
	 * - Photon HTTP error (`WP_Error`): structured fields and lat/long are
	 *   left untouched. Better to keep the previous good values than
	 *   overwrite with empties on a transient upstream blip. The
	 *   `gatherpress_async_geocode_failed` action fires so observability
	 *   plugins can surface chronic failures.
	 * - Photon "not found" response: structured fields are emptied (Photon
	 *   actively returned no match for the typed address). Lat/long are
	 *   left alone for the same reason as the empty-address path.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Venue post ID.
	 * @return void
	 */
	public function async_geocode_venue( int $post_id ): void {
		// Re-check post existence + type at run time — between schedule and
		// fire, the post can be force-deleted, trashed-then-restored under a
		// different type, or its post type can be re-registered without
		// venue support. The explicit `WP_Post` guard catches the deletion
		// race; the support check catches the type swap.
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( ! post_type_supports( $post->post_type, 'gatherpress-venue-information' ) ) {
			return;
		}

		$address = (string) get_post_meta( $post_id, 'gatherpress_address', true );

		if ( '' === trim( $address ) ) {
			foreach ( Venue_Meta::STRUCTURED_ADDRESS_FIELDS as $field ) {
				update_post_meta( $post_id, Utility::prefix_key( $field ), '' );
			}
			return;
		}

		$result = $this->geocode_to_result( $address );

		if ( is_wp_error( $result ) ) {
			/**
			 * Fires when the async geocode handler exits because Photon
			 * returned a `WP_Error`. Observability plugins can hook this to
			 * surface chronic failures (DNS issues, rate-limit responses,
			 * Photon outages) without parsing the WP-Cron error log.
			 *
			 * @since 1.0.0
			 *
			 * @param int      $post_id Venue post ID whose geocode failed.
			 * @param WP_Error $result  The error returned by `geocode_to_result()`.
			 */
			do_action( 'gatherpress_async_geocode_failed', $post_id, $result );
			return;
		}

		foreach ( Venue_Meta::STRUCTURED_ADDRESS_FIELDS as $field ) {
			update_post_meta(
				$post_id,
				Utility::prefix_key( $field ),
				(string) $result[ $field ]
			);
		}

		// Persist lat/long only when Photon returned coordinates. The
		// "not found" payload returns empty strings — we leave any
		// user-entered coordinates alone in that case (same rationale as
		// the empty-address branch above).
		if ( '' !== $result['latitude'] && '' !== $result['longitude'] ) {
			update_post_meta( $post_id, 'gatherpress_latitude', (string) $result['latitude'] );
			update_post_meta( $post_id, 'gatherpress_longitude', (string) $result['longitude'] );
		}
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
	 * Enforce a per-user rate limit on the geocode REST endpoints.
	 *
	 * Both `/geocode` and `/geocode/search` consume from the same per-user
	 * bucket — autocomplete + save-time reverse-geocode are one continuous
	 * flow from the user's perspective. The bucket is a fixed 60-second
	 * window; the (N+1)th request once the ceiling has been hit returns
	 * a 429 response with a `Retry-After` header pointing at the window's
	 * remaining seconds.
	 *
	 * Returns null when the request is under the ceiling and should
	 * proceed normally; returns a `WP_REST_Response` (HTTP 429) when the
	 * caller should bail.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response|null
	 */
	protected function check_rate_limit(): ?WP_REST_Response {
		/**
		 * Filter whether the geocode REST rate limit is enforced.
		 *
		 * Returning `false` disables the rate limit entirely — no
		 * per-user bucket is read or written, no 429 is ever returned.
		 * Useful for sites running their own upstream rate limiting at
		 * a CDN / WAF layer that already covers this surface, or for
		 * automated test environments that want to bypass the throttle.
		 *
		 * Mirrors the shape of `gatherpress_geocode_on_save_enabled`
		 * (cron side) for consistency: same filter pattern across
		 * both Photon-traffic toggles.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether the rate limit is enforced. Default true.
		 */
		$enabled = (bool) apply_filters( 'gatherpress_geocode_rate_limit_enabled', true );
		if ( ! $enabled ) {
			return null;
		}

		$user_id = get_current_user_id();
		// REST permission_callback already gates on `edit_posts`; an
		// unauthenticated request shouldn't reach this method, but if it
		// somehow does, fail open rather than 0-key the bucket.
		if ( 0 === $user_id ) {
			return null;
		}

		/**
		 * Filter the per-user requests-per-minute ceiling for the
		 * geocode REST endpoints (`/geocode` and `/geocode/search`).
		 *
		 * Both endpoints share one fixed-window per-user bucket. Once
		 * this ceiling is reached within a 60-second window, additional
		 * requests for the same user return HTTP `429 Too Many Requests`
		 * with a `Retry-After` header pointing at the remaining seconds
		 * in the window. Lower this value to be stricter with abusive
		 * clients; raise it for sites with debounced-but-eager
		 * autocomplete UIs or bulk-import workflows.
		 *
		 * Values below `1` are clamped to `1` (a zero ceiling would 429
		 * every request, including the first).
		 *
		 * @since 1.0.0
		 *
		 * @param int $ceiling Default per-user requests-per-minute ceiling.
		 */
		$ceiling = (int) apply_filters(
			'gatherpress_geocode_rate_limit_per_minute',
			self::GEOCODE_RATE_LIMIT_DEFAULT_PER_MINUTE
		);
		$ceiling = max( 1, $ceiling );

		$now    = time();
		$key    = self::GEOCODE_RATE_LIMIT_TRANSIENT_PREFIX . $user_id;
		$bucket = get_transient( $key );

		if ( ! is_array( $bucket ) || empty( $bucket['expires_at'] ) || $now >= (int) $bucket['expires_at'] ) {
			$bucket = array(
				'count'      => 0,
				'expires_at' => $now + self::GEOCODE_RATE_LIMIT_WINDOW_SECONDS,
			);
		}

		if ( (int) $bucket['count'] >= $ceiling ) {
			$retry_after = max( 1, (int) $bucket['expires_at'] - $now );
			$response    = new WP_REST_Response(
				array(
					'code'    => 'gatherpress_geocode_rate_limited',
					'message' => __(
						'Too many geocoding requests. Please slow down and try again shortly.',
						'gatherpress'
					),
					'data'    => array( 'status' => 429 ),
				),
				429
			);
			$response->header( 'Retry-After', (string) $retry_after );
			return $response;
		}

		$bucket['count'] = (int) $bucket['count'] + 1;
		// TTL tracks the remaining window so refreshing the count doesn't
		// extend it (a true fixed window, not sliding).
		$ttl = max( 1, (int) $bucket['expires_at'] - $now );
		set_transient( $key, $bucket, $ttl );

		return null;
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
		$rate_limited = $this->check_rate_limit();
		if ( null !== $rate_limited ) {
			return $rate_limited;
		}

		$address = $request->get_param( 'address' );

		if ( empty( $address ) ) {
			return new WP_Error(
				'missing_address',
				__( 'Address is required.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->geocode_to_result( $address );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Trim the cached payload to the existing REST contract (lat/lng + error).
		// Structured-address pieces remain in the transient cache for the
		// cron handler to pick up on the next venue save without a second
		// Photon roundtrip.
		return new WP_REST_Response(
			array(
				'latitude'  => $result['latitude'],
				'longitude' => $result['longitude'],
				'error'     => $result['error'],
			),
			200
		);
	}

	/**
	 * Geocode an address to a full payload (lat/lng + structured pieces).
	 *
	 * Shared by the `/geocode` REST handler and the async cron handler that
	 * backfills structured-address venue meta. Hits Photon once per
	 * unique `(address, language)` and caches the full shape — including the
	 * structured pieces — for `GEOCODE_CACHE_TTL`. Cached entries written by
	 * earlier code that didn't carry structured pieces are treated as a
	 * cache miss and refetched (self-healing on upgrade).
	 *
	 * @since 1.0.0
	 *
	 * @param string $address Address string to resolve.
	 * @return array{
	 *     latitude: string,
	 *     longitude: string,
	 *     error: string|null,
	 *     house_number: string,
	 *     street: string,
	 *     city: string,
	 *     county: string,
	 *     state: string,
	 *     postcode: string,
	 *     country: string,
	 *     country_code: string
	 * }|WP_Error Result payload, or WP_Error on Photon HTTP failure.
	 */
	public function geocode_to_result( string $address ) {
		// Cap oversize input for parity with search_addresses(); protects
		// upstream from pathological requests.
		$address = mb_substr( trim( $address ), 0, 200 );

		if ( '' === $address ) {
			return $this->build_not_found_payload();
		}

		$language  = $this->get_language_code();
		$cache_key = self::GEOCODE_CACHE_PREFIX . md5( $address . '|' . $language ); // NOSONAR.
		$cached    = get_transient( $cache_key );

		// Cached entry written before structured pieces existed lacks a
		// `house_number` slot. Treat as miss and refetch so the cache
		// self-heals after the upgrade.
		if ( is_array( $cached ) && array_key_exists( 'house_number', $cached ) ) {
			return $cached;
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
			$feature     = $data['features'][0];
			$coordinates = $feature['geometry']['coordinates'];
			$properties  = isset( $feature['properties'] ) && is_array( $feature['properties'] )
				? $feature['properties']
				: array();

			$result = array_merge(
				array(
					'latitude'  => (string) $coordinates[1],
					'longitude' => (string) $coordinates[0],
					'error'     => null,
				),
				$this->extract_structured_address( $properties )
			);

			set_transient( $cache_key, $result, self::GEOCODE_CACHE_TTL );

			return $result;
		}

		$not_found = $this->build_not_found_payload();

		set_transient( $cache_key, $not_found, self::GEOCODE_CACHE_TTL );

		return $not_found;
	}

	/**
	 * Builds the canonical "address could not be resolved" payload shape.
	 *
	 * Empty lat/lng and structured pieces, with a user-facing error message.
	 * Used both for empty input (early return before Photon is contacted)
	 * and for Photon responses that contain no features.
	 *
	 * @since 1.0.0
	 *
	 * @return array Result payload with empty fields and an error message.
	 */
	private function build_not_found_payload(): array {
		return array_merge(
			array(
				'latitude'  => '',
				'longitude' => '',
				'error'     => __( 'Could not find location. Please check the address and try again.', 'gatherpress' ),
			),
			$this->extract_structured_address( array() )
		);
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
		$rate_limited = $this->check_rate_limit();
		if ( null !== $rate_limited ) {
			return $rate_limited;
		}

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

		return $this->build_search_suggestions_response( $data, $cache_key );
	}

	/**
	 * Build the `/geocode/search` REST response from a decoded Photon
	 * payload. Caches the result under `$cache_key` so repeat queries
	 * within `SEARCH_CACHE_TTL` skip the Photon roundtrip.
	 *
	 * Extracted from `search_addresses()` to keep that method's NPath
	 * complexity manageable — the parsing loop dominates the path
	 * count; moving it here lets the REST entry point stay readable
	 * and PHPMD-clean.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $data      Decoded JSON body. Treated as "no results"
	 *                          when not an array or missing `features`.
	 * @param string $cache_key Transient key for the cached suggestions.
	 * @return WP_REST_Response The response with a `suggestions` array (possibly empty).
	 */
	private function build_search_suggestions_response( $data, string $cache_key ): WP_REST_Response {
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
	 * Pulls the structured address pieces out of a Photon `properties` object.
	 *
	 * Returns an associative array keyed for direct merge into a suggestion
	 * payload — the keys match the snake_case suffixes of the venue meta keys
	 * the autocomplete handler writes (`gatherpress_house_number`,
	 * `gatherpress_street`, etc.). Missing pieces collapse to empty string so
	 * the JS-side write loop can `editPost({ meta })` against every key
	 * unconditionally without hand-coding presence checks.
	 *
	 * Each Photon property maps 1:1 to a stored meta key — no coalescing or
	 * fallback chains. In the US in particular, `city` and `county` are
	 * distinct administrative units (e.g. Montclair is a town in Essex
	 * County, NJ — those should land in different fields). The display-side
	 * label builder (`format_photon_feature_label`) does its own city →
	 * district → county fallback for the one-line label, but that's a UX
	 * decision specific to a single label string and shouldn't bleed into
	 * structured persistence.
	 *
	 * `ISO3166-2-lvl4` (e.g. `US-NJ`) is intentionally not extracted —
	 * Photon's GeocodeJson response doesn't carry the Nominatim-only field,
	 * and the same value can be reconstructed from `country_code` + `state`
	 * via a lookup table at render time if a downstream consumer needs it.
	 *
	 * Photon's `district` (sub-city neighborhoods like Manhattan in NYC) is
	 * intentionally dropped — we don't have a `gatherpress_district` field,
	 * and shoehorning it into `city` would conflate two layers.
	 *
	 * @since 1.0.0
	 *
	 * @param array $properties Photon `properties` object.
	 * @return array{
	 *     house_number: string,
	 *     street: string,
	 *     city: string,
	 *     county: string,
	 *     state: string,
	 *     postcode: string,
	 *     country: string,
	 *     country_code: string
	 * }
	 */
	private function extract_structured_address( array $properties ): array {
		$pluck = static function ( string $key ) use ( $properties ): string {
			// Skip non-scalar values — Photon normally returns string-typed
			// fields, but a malformed response with a nested object would
			// otherwise emit "Array to string conversion" notices and store
			// the literal "Array" sentinel string in our meta.
			if ( ! isset( $properties[ $key ] ) || ! is_scalar( $properties[ $key ] ) ) {
				return '';
			}
			return trim( (string) $properties[ $key ] );
		};

		$structured = array();

		// Photon's property names match our snake_case fields with the
		// underscores stripped (`house_number` → `housenumber`,
		// `country_code` → `countrycode`); single-word fields are unaffected
		// by the str_replace. Driving the loop off the constant means adding
		// a new field is a single edit on `STRUCTURED_ADDRESS_FIELDS` —
		// provided the new Photon property follows the same convention.
		foreach ( Venue_Meta::STRUCTURED_ADDRESS_FIELDS as $field ) {
			$structured[ $field ] = $pluck( str_replace( '_', '', $field ) );
		}

		return $structured;
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
