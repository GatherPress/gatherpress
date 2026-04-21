/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { getFromConfig } from './editor-settings';
import { REST_NAMESPACE } from './namespace';

/**
 * Fallback minimum trimmed query length when the editor config is unavailable.
 * Kept at 3 to match the PHP default; the live value is read from
 * `gatherpress.config.addressSearchMinQueryLength`.
 *
 * @type {number}
 */
const ADDRESS_SEARCH_MIN_QUERY_LENGTH_FALLBACK = 3;

/**
 * Post-saving lock name held while a venue geocode is pending.
 *
 * Address changes debounce for a second before hitting Photon. If the user
 * saves inside that window, the post persists a new address with stale
 * lat/long — and the venue-map generator then bakes a PNG at the wrong
 * coords until a second save fires. Callers pass this lock name to
 * `lockPostSaving` / `unlockPostSaving` on `core/editor` so the Save button
 * stays disabled until geocoding resolves.
 *
 * @type {string}
 */
export const GEOCODE_LOCK_NAME = 'gatherpress/venue-geocoding';

/**
 * Minimum trimmed query length before calling the address search REST route.
 * Resolved dynamically from the block editor config (PHP source of truth),
 * with a 3-char fallback when the config value is not yet available.
 *
 * @return {number} Minimum query length.
 */
export function getAddressSearchMinQueryLength() {
	const configured = getFromConfig( 'addressSearchMinQueryLength' );
	return 'number' === typeof configured && 0 < configured
		? configured
		: ADDRESS_SEARCH_MIN_QUERY_LENGTH_FALLBACK;
}

/**
 * Back-compat export retained for existing imports. Prefer
 * `getAddressSearchMinQueryLength()` which reflects the live config value.
 *
 * @type {number}
 */
export const ADDRESS_SEARCH_MIN_QUERY_LENGTH =
	ADDRESS_SEARCH_MIN_QUERY_LENGTH_FALLBACK;

/**
 * Maximum number of entries retained by the in-memory geocode cache.
 * Long editing sessions would otherwise grow the cache without bound.
 *
 * @type {number}
 */
const GEOCODE_CACHE_MAX_SIZE = 200;

/**
 * In-memory cache for geocoding results (memoization).
 * Maps address strings to their geocoding results.
 *
 * Insertion order is maintained so the oldest entries can be evicted (LRU).
 *
 * @type {Map<string, Object>}
 */
const geocodeCache = new Map();

/**
 * Promises for geocode requests currently in flight, keyed by trimmed address.
 * Allows concurrent callers for the same address to share a single network call.
 *
 * @type {Map<string, Promise<Object>>}
 */
const geocodeInFlight = new Map();

/**
 * Stores a geocode result, updating recency and enforcing the size cap.
 *
 * @param {string} key   Trimmed address key.
 * @param {Object} value Result record ({ latitude, longitude, error }).
 */
function setGeocodeCacheEntry( key, value ) {
	if ( geocodeCache.has( key ) ) {
		geocodeCache.delete( key );
	}
	geocodeCache.set( key, value );
	while ( geocodeCache.size > GEOCODE_CACHE_MAX_SIZE ) {
		const oldest = geocodeCache.keys().next().value;
		/* istanbul ignore if -- defensive guard; unreachable because size > 0 here. */
		if ( undefined === oldest ) {
			break;
		}
		geocodeCache.delete( oldest );
	}
}

/**
 * Retrieves a cached geocode result, marking it as recently used.
 *
 * @param {string} key Trimmed address key.
 * @return {Object|undefined} Cached record if present.
 */
function getGeocodeCacheEntry( key ) {
	if ( ! geocodeCache.has( key ) ) {
		return undefined;
	}
	const value = geocodeCache.get( key );
	geocodeCache.delete( key );
	geocodeCache.set( key, value );
	return value;
}

/**
 * Clears the geocoding cache and any tracked in-flight requests.
 * Useful for testing or when cache needs to be invalidated.
 */
export function clearGeocodeCache() {
	geocodeCache.clear();
	geocodeInFlight.clear();
}

/**
 * Gets the current cache size.
 * Useful for testing.
 *
 * @return {number} Number of cached entries.
 */
export function getGeocodeCacheSize() {
	return geocodeCache.size;
}

/**
 * Primes the geocode cache so a later geocodeAddress() call skips the network.
 *
 * @param {string} address   Full address string (trimmed key).
 * @param {string} latitude  Latitude.
 * @param {string} longitude Longitude.
 */
export function primeGeocodeCache( address, latitude, longitude ) {
	if ( ! address || '' === address.trim() ) {
		return;
	}
	const trimmed = address.trim();
	setGeocodeCacheEntry( trimmed, {
		latitude: String( latitude ),
		longitude: String( longitude ),
		error: null,
	} );
}

/**
 * Geocodes an address using the GatherPress REST API proxy.
 *
 * Uses memoization to cache results and avoid duplicate API calls
 * for the same address. The PHP backend proxies requests to Photon
 * (OpenStreetMap-based) so the editor does not call third-party APIs directly.
 *
 * @since 1.0.0
 *
 * @param {string} address The full address to geocode.
 * @return {Promise<Object>} Promise resolving to { latitude, longitude, error }.
 *                           On success: { latitude: string, longitude: string, error: null }
 *                           On error: { latitude: '', longitude: '', error: string }
 */
export async function geocodeAddress( address ) {
	if ( ! address || '' === address.trim() ) {
		return { latitude: '', longitude: '', error: null };
	}

	const trimmedAddress = address.trim();

	// Skip the network for partial inputs; matches the search route's minimum.
	if ( getAddressSearchMinQueryLength() > trimmedAddress.length ) {
		return { latitude: '', longitude: '', error: null };
	}

	// Check cache first (memoization).
	const cached = getGeocodeCacheEntry( trimmedAddress );
	if ( undefined !== cached ) {
		return cached;
	}

	// Reuse any in-flight request for the same address (concurrent callers share one fetch).
	if ( geocodeInFlight.has( trimmedAddress ) ) {
		return geocodeInFlight.get( trimmedAddress );
	}

	const request = ( async () => {
		try {
			const response = await apiFetch( {
				path: `/${ REST_NAMESPACE }/geocode?address=${ encodeURIComponent(
					trimmedAddress
				) }`,
			} );

			// The REST API returns the result directly.
			if ( response.latitude && response.longitude ) {
				const result = {
					latitude: response.latitude,
					longitude: response.longitude,
					error: null,
				};
				// Cache successful results.
				setGeocodeCacheEntry( trimmedAddress, result );
				return result;
			}

			// No results found or error from API.
			const noResultsResponse = {
				latitude: '',
				longitude: '',
				error:
					response.error ||
					__(
						'Could not find location. Please check the address and try again.',
						'gatherpress'
					),
			};
			// Cache "not found" results since the address won't suddenly exist.
			if ( ! response.error ) {
				setGeocodeCacheEntry( trimmedAddress, noResultsResponse );
			}
			return noResultsResponse;
		} catch ( error ) {
			// Don't cache network errors - allow retry.
			return {
				latitude: '',
				longitude: '',
				error:
					error.message ||
					__( 'Geocoding request failed.', 'gatherpress' ),
			};
		} finally {
			geocodeInFlight.delete( trimmedAddress );
		}
	} )();

	geocodeInFlight.set( trimmedAddress, request );
	return request;
}

/**
 * Fetches address suggestions for autocomplete (Photon via GatherPress REST proxy).
 *
 * Network / REST failures are propagated to the caller so the UI can surface a
 * distinct "search unavailable" state instead of silently rendering an empty
 * dropdown (which is indistinguishable from "no matches"). `AbortError` also
 * propagates so the hook can bail on superseded requests.
 *
 * @param {string}      query            Partial address input.
 * @param {Object}      [options]        Request options.
 * @param {AbortSignal} [options.signal] Signal used to abort the request when a newer one supersedes it.
 * @return {Promise<Array<{ label: string, latitude: string, longitude: string }>>} Suggestion rows.
 */
export async function fetchAddressSuggestions( query, { signal } = {} ) {
	if ( ! query || '' === query.trim() ) {
		return [];
	}

	const trimmed = query.trim();

	if ( getAddressSearchMinQueryLength() > trimmed.length ) {
		return [];
	}

	const response = await apiFetch( {
		path: `/${ REST_NAMESPACE }/geocode/search?q=${ encodeURIComponent(
			trimmed
		) }`,
		signal,
	} );

	if ( ! response?.suggestions || ! Array.isArray( response.suggestions ) ) {
		return [];
	}

	const rows = [];

	for ( const raw of response.suggestions ) {
		const label = 'string' === typeof raw.label ? raw.label.trim() : '';

		if ( ! label ) {
			continue;
		}

		rows.push( {
			label,
			latitude: String( raw.latitude ?? '' ),
			longitude: String( raw.longitude ?? '' ),
		} );
	}

	return rows;
}
