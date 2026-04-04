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
	getGeocoCacheSize,
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
			expect( getGeocoCacheSize() ).toBe( 1 );

			clearGeocodeCache();
			expect( getGeocoCacheSize() ).toBe( 0 );
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

		it( 'returns empty array on API failure', async () => {
			apiFetch.mockRejectedValue( new Error( 'fail' ) );

			const result = await fetchAddressSuggestions( 'Some place' );

			expect( result ).toEqual( [] );
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
			expect( getGeocoCacheSize() ).toBe( 0 );
		} );
	} );
} );
