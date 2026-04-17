/**
 * External dependencies.
 */
import {
	describe,
	expect,
	it,
	jest,
	beforeEach,
	afterEach,
} from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	ADDRESS_SEARCH_MIN_QUERY_LENGTH,
	fetchAddressSuggestions,
	geocodeAddress,
	clearGeocodeCache,
	getGeocodeCacheSize,
	primeGeocodeCache,
} from '@src/helpers/geocoding';

// Mock apiFetch.
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

// Mock namespace.
jest.mock( '@src/helpers/namespace', () => ( {
	REST_NAMESPACE: 'gatherpress/v1',
} ) );

describe( 'Geocoding helpers', () => {
	let apiFetch;

	it( 'exports address search min length aligned with PHP', () => {
		expect( ADDRESS_SEARCH_MIN_QUERY_LENGTH ).toBe( 3 );
	} );

	beforeEach( async () => {
		// Get the mocked apiFetch.
		apiFetch = ( await import( '@wordpress/api-fetch' ) ).default;
		apiFetch.mockReset();

		// Clear cache before each test.
		clearGeocodeCache();
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'clearGeocodeCache', () => {
		it( 'clears the cache', async () => {
			apiFetch.mockResolvedValue( {
				latitude: '40.73061',
				longitude: '-73.935242',
				error: null,
			} );

			await geocodeAddress( 'Test Address' );
			expect( getGeocodeCacheSize() ).toBe( 1 );

			clearGeocodeCache();
			expect( getGeocodeCacheSize() ).toBe( 0 );
		} );
	} );

	describe( 'cache eviction', () => {
		it( 'caps the cache at the maximum size and evicts the oldest entry first', async () => {
			apiFetch.mockImplementation( ( { path } ) => {
				// Return unique coordinates per address so entries differ.
				const address = decodeURIComponent(
					path.split( 'address=' )[ 1 ] || ''
				);
				return Promise.resolve( {
					latitude: `lat-${ address }`,
					longitude: `lng-${ address }`,
					error: null,
				} );
			} );

			// 200 is the implementation cap; fill it then push one more.
			for ( let i = 0; 200 > i; i++ ) {
				await geocodeAddress( `Address ${ i }` );
			}
			expect( getGeocodeCacheSize() ).toBe( 200 );

			await geocodeAddress( 'Address 200' );
			expect( getGeocodeCacheSize() ).toBe( 200 );

			// The oldest entry ('Address 0') should have been evicted — a second
			// lookup must therefore re-hit apiFetch.
			const callsBeforeReLookup = apiFetch.mock.calls.length;
			await geocodeAddress( 'Address 0' );
			expect( apiFetch.mock.calls.length ).toBe( callsBeforeReLookup + 1 );
		} );

		it( 'treats a cache hit as recently used so it is not evicted next', async () => {
			apiFetch.mockImplementation( ( { path } ) => {
				const address = decodeURIComponent(
					path.split( 'address=' )[ 1 ] || ''
				);
				return Promise.resolve( {
					latitude: `lat-${ address }`,
					longitude: `lng-${ address }`,
					error: null,
				} );
			} );

			for ( let i = 0; 200 > i; i++ ) {
				await geocodeAddress( `Address ${ i }` );
			}

			// Touch the oldest entry so it becomes most-recently used.
			await geocodeAddress( 'Address 0' );

			// Inserting a new entry should now evict 'Address 1' instead of 'Address 0'.
			await geocodeAddress( 'Address 200' );

			const callsBeforeReLookup = apiFetch.mock.calls.length;
			await geocodeAddress( 'Address 0' );
			// Still cached — no new network call.
			expect( apiFetch.mock.calls.length ).toBe( callsBeforeReLookup );

			await geocodeAddress( 'Address 1' );
			// Evicted — network call was made.
			expect( apiFetch.mock.calls.length ).toBe( callsBeforeReLookup + 1 );
		} );
	} );

	describe( 'geocodeAddress', () => {
		it( 'returns empty result for empty address', async () => {
			const result = await geocodeAddress( '' );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'returns empty result for null address', async () => {
			const result = await geocodeAddress( null );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'returns empty result for undefined address', async () => {
			const result = await geocodeAddress( undefined );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'returns empty result for whitespace-only address', async () => {
			const result = await geocodeAddress( '   ' );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'skips the network for inputs shorter than the minimum query length', async () => {
			const result = await geocodeAddress( 'ab' );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'deduplicates concurrent calls for the same address into one request', async () => {
			let resolveFetch;
			apiFetch.mockImplementation(
				() =>
					new Promise( ( resolve ) => {
						resolveFetch = resolve;
					} )
			);

			const address = 'Shared Address';
			const promiseA = geocodeAddress( address );
			const promiseB = geocodeAddress( address );

			expect( apiFetch ).toHaveBeenCalledTimes( 1 );

			resolveFetch( {
				latitude: '10',
				longitude: '20',
				error: null,
			} );

			const [ resultA, resultB ] = await Promise.all( [
				promiseA,
				promiseB,
			] );

			expect( resultA ).toEqual( resultB );
			expect( resultA ).toEqual( {
				latitude: '10',
				longitude: '20',
				error: null,
			} );
		} );

		it( 'allows a new fetch after an in-flight request completes', async () => {
			apiFetch.mockResolvedValue( {
				latitude: '1',
				longitude: '2',
				error: null,
			} );

			await geocodeAddress( 'Serial Address' );
			clearGeocodeCache();
			await geocodeAddress( 'Serial Address' );

			expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		} );

		it( 'returns coordinates for successful geocoding', async () => {
			apiFetch.mockResolvedValue( {
				latitude: '40.73061',
				longitude: '-73.935242',
				error: null,
			} );

			const result = await geocodeAddress( '123 Main St, New York, NY' );

			expect( result ).toEqual( {
				latitude: '40.73061',
				longitude: '-73.935242',
				error: null,
			} );

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: expect.stringContaining( '/gatherpress/v1/geocode' ),
			} );
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: expect.stringContaining(
					'address=123%20Main%20St%2C%20New%20York%2C%20NY'
				),
			} );
		} );

		it( 'encodes address in URL', async () => {
			apiFetch.mockResolvedValue( {
				latitude: '',
				longitude: '',
				error: 'Could not find location.',
			} );

			await geocodeAddress( '123 Main St & Oak Ave' );

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: expect.stringContaining( '123%20Main%20St%20%26%20Oak%20Ave' ),
			} );
		} );

		it( 'returns error when API returns error', async () => {
			apiFetch.mockResolvedValue( {
				latitude: '',
				longitude: '',
				error: 'Could not find location. Please check the address and try again.',
			} );

			const result = await geocodeAddress( 'Invalid Address XYZ123' );

			expect( result.latitude ).toBe( '' );
			expect( result.longitude ).toBe( '' );
			expect( result.error ).toContain( 'Could not find location' );
		} );

		it( 'returns error when apiFetch throws', async () => {
			apiFetch.mockRejectedValue( new Error( 'Network error' ) );

			const result = await geocodeAddress( 'Network Error Address' );

			expect( result.latitude ).toBe( '' );
			expect( result.longitude ).toBe( '' );
			expect( result.error ).toContain( 'Network error' );
		} );

		it( 'handles API error without message', async () => {
			apiFetch.mockRejectedValue( {} );

			const result = await geocodeAddress( 'Error Without Message' );

			expect( result.latitude ).toBe( '' );
			expect( result.longitude ).toBe( '' );
			expect( result.error ).toContain( 'Geocoding request failed' );
		} );

		describe( 'memoization', () => {
			it( 'returns cached result on second call', async () => {
				apiFetch.mockResolvedValue( {
					latitude: '40.73061',
					longitude: '-73.935242',
					error: null,
				} );

				const address = 'Cached Address Test';
				const result1 = await geocodeAddress( address );
				const result2 = await geocodeAddress( address );

				// Should only call apiFetch once.
				expect( apiFetch ).toHaveBeenCalledTimes( 1 );

				// Both results should be the same.
				expect( result1 ).toEqual( result2 );
			} );

			it( 'caches no-results responses without error', async () => {
				apiFetch.mockResolvedValue( {
					latitude: '',
					longitude: '',
					error: null,
				} );

				const address = 'No Results Cache Test';
				await geocodeAddress( address );
				await geocodeAddress( address );

				// Should only call apiFetch once (cached the no-results response).
				expect( apiFetch ).toHaveBeenCalledTimes( 1 );
			} );

			it( 'does not cache responses with errors', async () => {
				apiFetch.mockResolvedValue( {
					latitude: '',
					longitude: '',
					error: 'Service temporarily unavailable',
				} );

				const address = 'Error Cache Test';
				await geocodeAddress( address );
				await geocodeAddress( address );

				// Should call apiFetch twice (errors not cached).
				expect( apiFetch ).toHaveBeenCalledTimes( 2 );
			} );

			it( 'does not cache network errors', async () => {
				apiFetch.mockRejectedValue( new Error( 'Network error' ) );

				const address = 'Network Error Cache Test';
				await geocodeAddress( address );
				await geocodeAddress( address );

				// Should call apiFetch twice (network errors not cached).
				expect( apiFetch ).toHaveBeenCalledTimes( 2 );
			} );

			it( 'trims address before caching', async () => {
				apiFetch.mockResolvedValue( {
					latitude: '40.73061',
					longitude: '-73.935242',
					error: null,
				} );

				await geocodeAddress( '  Trimmed Address  ' );
				await geocodeAddress( 'Trimmed Address' );

				// Should only call apiFetch once (both addresses normalize to same key).
				expect( apiFetch ).toHaveBeenCalledTimes( 1 );
			} );
		} );
	} );

	describe( 'fetchAddressSuggestions', () => {
		it( 'returns empty array for short or empty query', async () => {
			expect( await fetchAddressSuggestions( '' ) ).toEqual( [] );
			expect( await fetchAddressSuggestions( '  ' ) ).toEqual( [] );
			expect( await fetchAddressSuggestions( 'ab' ) ).toEqual( [] );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'returns suggestions from API', async () => {
			apiFetch.mockResolvedValue( {
				suggestions: [
					{
						label: 'Paris, France',
						latitude: '48.8566',
						longitude: '2.3522',
					},
				],
			} );

			const result = await fetchAddressSuggestions( 'Paris Fra' );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ].label ).toBe( 'Paris, France' );
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: expect.stringContaining( '/gatherpress/v1/geocode/search' ),
			} );
			expect( apiFetch ).toHaveBeenCalledWith( {
				path: expect.stringContaining( 'q=Paris%20Fra' ),
			} );
		} );

		it( 'propagates API failures so callers can surface an error state', async () => {
			const failure = new Error( 'Service unavailable' );
			apiFetch.mockRejectedValue( failure );

			await expect(
				fetchAddressSuggestions( 'Some place' )
			).rejects.toBe( failure );
		} );

		it( 'forwards an AbortSignal to apiFetch when provided', async () => {
			apiFetch.mockResolvedValue( { suggestions: [] } );

			const controller = new AbortController();
			await fetchAddressSuggestions( 'Somewhere', {
				signal: controller.signal,
			} );

			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					signal: controller.signal,
				} )
			);
		} );

		it( 'rethrows AbortError so callers can distinguish cancellation', async () => {
			const abortError = Object.assign( new Error( 'aborted' ), {
				name: 'AbortError',
			} );
			apiFetch.mockRejectedValue( abortError );

			await expect(
				fetchAddressSuggestions( 'Some place' )
			).rejects.toBe( abortError );
		} );

		it( 'returns empty array when response has no suggestions', async () => {
			apiFetch.mockResolvedValue( {} );

			const result = await fetchAddressSuggestions( 'Somewhere' );

			expect( result ).toEqual( [] );
		} );

		it( 'skips suggestions with empty or non-string labels', async () => {
			apiFetch.mockResolvedValue( {
				suggestions: [
					{ label: '', latitude: '1', longitude: '2' },
					{ label: 123, latitude: '3', longitude: '4' },
					{
						label: '  Valid Row  ',
						latitude: '5',
						longitude: '6',
					},
				],
			} );

			const result = await fetchAddressSuggestions( 'Some query' );

			expect( result ).toHaveLength( 1 );
			expect( result[ 0 ] ).toEqual( {
				label: 'Valid Row',
				latitude: '5',
				longitude: '6',
			} );
		} );

		it( 'stringifies latitude and longitude including when missing', async () => {
			apiFetch.mockResolvedValue( {
				suggestions: [
					{
						label: 'A',
						latitude: null,
						longitude: undefined,
					},
				],
			} );

			const result = await fetchAddressSuggestions( 'Some query' );

			expect( result[ 0 ] ).toEqual( {
				label: 'A',
				latitude: '',
				longitude: '',
			} );
		} );
	} );

	describe( 'primeGeocodeCache', () => {
		it( 'primes cache so geocodeAddress skips network', async () => {
			apiFetch.mockResolvedValue( {
				latitude: '99',
				longitude: '88',
				error: null,
			} );

			primeGeocodeCache( 'Primed St', '10.5', '-20.3' );
			const result = await geocodeAddress( 'Primed St' );

			expect( result ).toEqual( {
				latitude: '10.5',
				longitude: '-20.3',
				error: null,
			} );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'does nothing when address is empty or whitespace', () => {
			primeGeocodeCache( '', '1', '2' );
			primeGeocodeCache( '   ', '1', '2' );
			expect( getGeocodeCacheSize() ).toBe( 0 );
		} );

		it( 'replaces an existing entry when primed again', async () => {
			primeGeocodeCache( 'Reprime St', '1.0', '2.0' );
			primeGeocodeCache( 'Reprime St', '9.0', '8.0' );

			const result = await geocodeAddress( 'Reprime St' );

			expect( result ).toEqual( {
				latitude: '9.0',
				longitude: '8.0',
				error: null,
			} );
			expect( getGeocodeCacheSize() ).toBe( 1 );
			expect( apiFetch ).not.toHaveBeenCalled();
		} );
	} );
} );
