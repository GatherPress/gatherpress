/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { REST_NAMESPACE } from './namespace';

/**
 * Minimum trimmed query length before calling the address search REST route.
 * Kept in sync with PHP `search_addresses` short-query handling.
 *
 * @type {number}
 */
export const ADDRESS_SEARCH_MIN_QUERY_LENGTH = 3;

/**
 * In-memory cache for geocoding results (memoization).
 * Maps address strings to their geocoding results.
 *
 * @type {Map<string, Object>}
 */
const geocodeCache = new Map();

/**
 * Clears the geocoding cache.
 * Useful for testing or when cache needs to be invalidated.
 */
export function clearGeocodeCache() {
	geocodeCache.clear();
}

/**
 * Gets the current cache size.
 * Useful for testing.
 *
 * @return {number} Number of cached entries.
 */
export function getGeocoCacheSize() {
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
	geocodeCache.set( trimmed, {
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

	// Check cache first (memoization).
	if ( geocodeCache.has( trimmedAddress ) ) {
		return geocodeCache.get( trimmedAddress );
	}

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
			geocodeCache.set( trimmedAddress, result );
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
			geocodeCache.set( trimmedAddress, noResultsResponse );
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
	}
}

/**
 * Fetches address suggestions for autocomplete (Photon via GatherPress REST proxy).
 *
 * @param {string} query Partial address input.
 * @return {Promise<Array<{ label: string, latitude: string, longitude: string }>>} Suggestion rows.
 */
export async function fetchAddressSuggestions( query ) {
	if ( ! query || '' === query.trim() ) {
		return [];
	}

	const trimmed = query.trim();

	if ( ADDRESS_SEARCH_MIN_QUERY_LENGTH > trimmed.length ) {
		return [];
	}

	try {
		const response = await apiFetch( {
			path: `/${ REST_NAMESPACE }/geocode/search?q=${ encodeURIComponent(
				trimmed
			) }`,
		} );

		if ( ! response?.suggestions || ! Array.isArray( response.suggestions ) ) {
			return [];
		}

		const rows = [];

		for ( const raw of response.suggestions ) {
			const label =
				'string' === typeof raw.label ? raw.label.trim() : '';

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
	} catch {
		return [];
	}
}
