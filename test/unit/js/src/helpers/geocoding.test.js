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
	NOMINATIM_API_URL,
	geocodeAddress,
	clearGeocodeCache,
	getGeocoCacheSize,
	getLanguageCode,
	buildNominatimUrl,
} from '../../../../../src/helpers/geocoding';

describe( 'Geocoding helpers', () => {
	let originalFetch;
	let originalDocumentLang;
	let originalNavigatorLanguage;

	beforeEach( () => {
		originalFetch = global.fetch;
		originalDocumentLang = document.documentElement.lang;
		originalNavigatorLanguage = navigator.language;

		// Clear cache before each test.
		clearGeocodeCache();
	} );

	afterEach( () => {
		global.fetch = originalFetch;
		document.documentElement.lang = originalDocumentLang;
		Object.defineProperty( navigator, 'language', {
			value: originalNavigatorLanguage,
			writable: true,
		} );
	} );

	describe( 'NOMINATIM_API_URL', () => {
		it( 'has the correct API URL', () => {
			expect( NOMINATIM_API_URL ).toBe(
				'https://nominatim.openstreetmap.org/search'
			);
		} );
	} );

	describe( 'getLanguageCode', () => {
		it( 'returns language from document lang attribute', () => {
			document.documentElement.lang = 'de-DE';
			expect( getLanguageCode() ).toBe( 'de' );
		} );

		it( 'handles underscore format in document lang', () => {
			document.documentElement.lang = 'fr_FR';
			expect( getLanguageCode() ).toBe( 'fr' );
		} );

		it( 'returns simple language code from document lang', () => {
			document.documentElement.lang = 'es';
			expect( getLanguageCode() ).toBe( 'es' );
		} );

		it( 'falls back to navigator language when document lang is empty', () => {
			document.documentElement.lang = '';
			Object.defineProperty( navigator, 'language', {
				value: 'it-IT',
				writable: true,
			} );
			expect( getLanguageCode() ).toBe( 'it' );
		} );

		it( 'returns en as default when no language is available', () => {
			document.documentElement.lang = '';
			Object.defineProperty( navigator, 'language', {
				value: undefined,
				writable: true,
			} );
			expect( getLanguageCode() ).toBe( 'en' );
		} );
	} );

	describe( 'buildNominatimUrl', () => {
		beforeEach( () => {
			document.documentElement.lang = 'en-US';
		} );

		it( 'includes the base URL', () => {
			const url = buildNominatimUrl( '123 Main St' );
			expect( url ).toContain( NOMINATIM_API_URL );
		} );

		it( 'includes format=geojson parameter', () => {
			const url = buildNominatimUrl( '123 Main St' );
			expect( url ).toContain( 'format=geojson' );
		} );

		it( 'includes limit=1 parameter', () => {
			const url = buildNominatimUrl( '123 Main St' );
			expect( url ).toContain( 'limit=1' );
		} );

		it( 'includes accept-language parameter', () => {
			document.documentElement.lang = 'de';
			const url = buildNominatimUrl( '123 Main St' );
			expect( url ).toContain( 'accept-language=de' );
		} );

		it( 'encodes the address', () => {
			const url = buildNominatimUrl( '123 Main St & Oak Ave' );
			expect( url ).toContain( 'q=123+Main+St+%26+Oak+Ave' );
		} );
	} );

	describe( 'clearGeocodeCache', () => {
		it( 'clears the cache', async () => {
			const mockResponse = {
				features: [
					{
						geometry: {
							coordinates: [ -73.935242, 40.73061 ],
						},
					},
				],
			};

			global.fetch = jest.fn( () =>
				Promise.resolve( {
					ok: true,
					json: () => Promise.resolve( mockResponse ),
				} )
			);

			await geocodeAddress( 'Test Address' );
			expect( getGeocoCacheSize() ).toBe( 1 );

			clearGeocodeCache();
			expect( getGeocoCacheSize() ).toBe( 0 );
		} );
	} );

	describe( 'geocodeAddress', () => {
		beforeEach( () => {
			document.documentElement.lang = 'en-US';
		} );

		it( 'returns empty result for empty address', async () => {
			const result = await geocodeAddress( '' );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
		} );

		it( 'returns empty result for null address', async () => {
			const result = await geocodeAddress( null );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
		} );

		it( 'returns empty result for undefined address', async () => {
			const result = await geocodeAddress( undefined );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
		} );

		it( 'returns empty result for whitespace-only address', async () => {
			const result = await geocodeAddress( '   ' );

			expect( result ).toEqual( {
				latitude: '',
				longitude: '',
				error: null,
			} );
		} );

		it( 'returns coordinates for successful geocoding', async () => {
			const mockResponse = {
				features: [
					{
						geometry: {
							coordinates: [ -73.935242, 40.73061 ], // [lng, lat].
						},
					},
				],
			};

			global.fetch = jest.fn( () =>
				Promise.resolve( {
					ok: true,
					json: () => Promise.resolve( mockResponse ),
				} )
			);

			const result = await geocodeAddress( '123 Main St, New York, NY' );

			expect( result ).toEqual( {
				latitude: '40.73061',
				longitude: '-73.935242',
				error: null,
			} );

			expect( global.fetch ).toHaveBeenCalledWith(
				expect.stringContaining( NOMINATIM_API_URL )
			);
			expect( global.fetch ).toHaveBeenCalledWith(
				expect.stringContaining( 'format=geojson' )
			);
			expect( global.fetch ).toHaveBeenCalledWith(
				expect.stringContaining( 'limit=1' )
			);
			expect( global.fetch ).toHaveBeenCalledWith(
				expect.stringContaining( 'accept-language=' )
			);
		} );

		it( 'encodes address in URL', async () => {
			global.fetch = jest.fn( () =>
				Promise.resolve( {
					ok: true,
					json: () => Promise.resolve( { features: [] } ),
				} )
			);

			await geocodeAddress( '123 Main St & Oak Ave' );

			// URLSearchParams encodes spaces as + and & as %26.
			expect( global.fetch ).toHaveBeenCalledWith(
				expect.stringContaining( '123+Main+St+%26+Oak+Ave' )
			);
		} );

		it( 'returns error for non-ok response', async () => {
			global.fetch = jest.fn( () =>
				Promise.resolve( {
					ok: false,
					statusText: 'Not Found',
				} )
			);

			const result = await geocodeAddress( 'Error Address 404' );

			expect( result.latitude ).toBe( '' );
			expect( result.longitude ).toBe( '' );
			expect( result.error ).toContain( 'Not Found' );
		} );

		it( 'returns error when no results found', async () => {
			global.fetch = jest.fn( () =>
				Promise.resolve( {
					ok: true,
					json: () => Promise.resolve( { features: [] } ),
				} )
			);

			const result = await geocodeAddress( 'Invalid Address XYZ123' );

			expect( result.latitude ).toBe( '' );
			expect( result.longitude ).toBe( '' );
			expect( result.error ).toContain( 'Could not find location' );
		} );

		it( 'returns error when fetch throws', async () => {
			global.fetch = jest.fn( () =>
				Promise.reject( new Error( 'Network error' ) )
			);

			const result = await geocodeAddress( 'Network Error Address' );

			expect( result.latitude ).toBe( '' );
			expect( result.longitude ).toBe( '' );
			expect( result.error ).toContain( 'Network error' );
		} );

		it( 'converts coordinates to strings', async () => {
			const mockResponse = {
				features: [
					{
						geometry: {
							coordinates: [ -122.4194, 37.7749 ],
						},
					},
				],
			};

			global.fetch = jest.fn( () =>
				Promise.resolve( {
					ok: true,
					json: () => Promise.resolve( mockResponse ),
				} )
			);

			const result = await geocodeAddress( 'San Francisco, CA' );

			expect( typeof result.latitude ).toBe( 'string' );
			expect( typeof result.longitude ).toBe( 'string' );
			expect( result.latitude ).toBe( '37.7749' );
			expect( result.longitude ).toBe( '-122.4194' );
		} );

		it( 'uses first result when multiple features returned', async () => {
			const mockResponse = {
				features: [
					{
						geometry: {
							coordinates: [ -73.935242, 40.73061 ],
						},
					},
					{
						geometry: {
							coordinates: [ -118.2437, 34.0522 ],
						},
					},
				],
			};

			global.fetch = jest.fn( () =>
				Promise.resolve( {
					ok: true,
					json: () => Promise.resolve( mockResponse ),
				} )
			);

			const result = await geocodeAddress( 'Main Street Unique' );

			// Should use first result.
			expect( result.latitude ).toBe( '40.73061' );
			expect( result.longitude ).toBe( '-73.935242' );
		} );

		describe( 'memoization', () => {
			it( 'returns cached result on second call', async () => {
				const mockResponse = {
					features: [
						{
							geometry: {
								coordinates: [ -73.935242, 40.73061 ],
							},
						},
					],
				};

				global.fetch = jest.fn( () =>
					Promise.resolve( {
						ok: true,
						json: () => Promise.resolve( mockResponse ),
					} )
				);

				const address = 'Cached Address Test';
				const result1 = await geocodeAddress( address );
				const result2 = await geocodeAddress( address );

				// Should only call fetch once.
				expect( global.fetch ).toHaveBeenCalledTimes( 1 );

				// Both results should be the same.
				expect( result1 ).toEqual( result2 );
			} );

			it( 'caches no-results responses', async () => {
				global.fetch = jest.fn( () =>
					Promise.resolve( {
						ok: true,
						json: () => Promise.resolve( { features: [] } ),
					} )
				);

				const address = 'No Results Cache Test';
				await geocodeAddress( address );
				await geocodeAddress( address );

				// Should only call fetch once (cached the no-results response).
				expect( global.fetch ).toHaveBeenCalledTimes( 1 );
			} );

			it( 'does not cache HTTP errors', async () => {
				global.fetch = jest.fn( () =>
					Promise.resolve( {
						ok: false,
						statusText: 'Service Unavailable',
					} )
				);

				const address = 'HTTP Error Cache Test';
				await geocodeAddress( address );
				await geocodeAddress( address );

				// Should call fetch twice (errors not cached).
				expect( global.fetch ).toHaveBeenCalledTimes( 2 );
			} );

			it( 'does not cache network errors', async () => {
				global.fetch = jest.fn( () =>
					Promise.reject( new Error( 'Network error' ) )
				);

				const address = 'Network Error Cache Test';
				await geocodeAddress( address );
				await geocodeAddress( address );

				// Should call fetch twice (network errors not cached).
				expect( global.fetch ).toHaveBeenCalledTimes( 2 );
			} );

			it( 'trims address before caching', async () => {
				const mockResponse = {
					features: [
						{
							geometry: {
								coordinates: [ -73.935242, 40.73061 ],
							},
						},
					],
				};

				global.fetch = jest.fn( () =>
					Promise.resolve( {
						ok: true,
						json: () => Promise.resolve( mockResponse ),
					} )
				);

				await geocodeAddress( '  Trimmed Address  ' );
				await geocodeAddress( 'Trimmed Address' );

				// Should only call fetch once (both addresses normalize to same key).
				expect( global.fetch ).toHaveBeenCalledTimes( 1 );
			} );
		} );
	} );
} );
