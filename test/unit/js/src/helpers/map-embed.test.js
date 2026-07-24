/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	getGoogleMapEmbedSrc,
	GOOGLE_MAP_TYPE_SLUGS,
	LEGACY_EMBED_LETTER_BY_SLUG,
	parseCoordinates,
	toGoogleMapType,
	toMapsEmbedApiMapType,
} from '../../../../../src/helpers/map-embed';

describe( 'toGoogleMapType', () => {
	it.each( GOOGLE_MAP_TYPE_SLUGS )(
		'passes the canonical slug %s through',
		( slug ) => {
			expect( toGoogleMapType( slug ) ).toBe( slug );
		}
	);

	it( 'falls back to roadmap for unknown slugs', () => {
		expect( toGoogleMapType( 'moon' ) ).toBe( 'roadmap' );
	} );

	it( 'falls back to roadmap for empty input', () => {
		expect( toGoogleMapType( '' ) ).toBe( 'roadmap' );
		expect( toGoogleMapType( undefined ) ).toBe( 'roadmap' );
	} );
} );

describe( 'toMapsEmbedApiMapType', () => {
	it( 'keeps roadmap and satellite', () => {
		expect( toMapsEmbedApiMapType( 'roadmap' ) ).toBe( 'roadmap' );
		expect( toMapsEmbedApiMapType( 'satellite' ) ).toBe( 'satellite' );
	} );

	it( 'coerces hybrid to satellite', () => {
		expect( toMapsEmbedApiMapType( 'hybrid' ) ).toBe( 'satellite' );
	} );

	it( 'coerces terrain (and unknowns) to roadmap', () => {
		expect( toMapsEmbedApiMapType( 'terrain' ) ).toBe( 'roadmap' );
		expect( toMapsEmbedApiMapType( 'moon' ) ).toBe( 'roadmap' );
	} );
} );

describe( 'getGoogleMapEmbedSrc', () => {
	const coords = { latitude: '40.7', longitude: '-74.0' };

	it( 'builds the keyed Maps Embed API URL', () => {
		const src = getGoogleMapEmbedSrc( {
			...coords,
			zoom: 12,
			type: 'satellite',
			apiKey: ' my-key ',
		} );
		const url = new URL( src );

		expect( url.origin + url.pathname ).toBe(
			'https://www.google.com/maps/embed/v1/view'
		);
		// The key is trimmed before use.
		expect( url.searchParams.get( 'key' ) ).toBe( 'my-key' );
		expect( url.searchParams.get( 'center' ) ).toBe( '40.7,-74.0' );
		expect( url.searchParams.get( 'zoom' ) ).toBe( '12' );
		expect( url.searchParams.get( 'maptype' ) ).toBe( 'satellite' );
	} );

	it( 'coerces hybrid to satellite in the keyed URL', () => {
		const src = getGoogleMapEmbedSrc( {
			...coords,
			zoom: 12,
			type: 'hybrid',
			apiKey: 'k',
		} );

		expect( new URL( src ).searchParams.get( 'maptype' ) ).toBe(
			'satellite'
		);
	} );

	it( 'builds the keyless legacy embed URL', () => {
		const src = getGoogleMapEmbedSrc( {
			...coords,
			zoom: 15,
			type: 'roadmap',
			apiKey: '',
		} );
		const url = new URL( src );

		expect( url.origin + url.pathname ).toBe(
			'https://maps.google.com/maps'
		);
		expect( url.searchParams.get( 'q' ) ).toBe( '40.7,-74.0' );
		expect( url.searchParams.get( 'z' ) ).toBe( '15' );
		expect( url.searchParams.get( 't' ) ).toBe(
			LEGACY_EMBED_LETTER_BY_SLUG.roadmap
		);
		expect( url.searchParams.get( 'output' ) ).toBe( 'embed' );
	} );

	it( 'uses the satellite letter for keyless satellite maps', () => {
		const src = getGoogleMapEmbedSrc( {
			...coords,
			zoom: 15,
			type: 'satellite',
			apiKey: undefined,
		} );

		expect( new URL( src ).searchParams.get( 't' ) ).toBe(
			LEGACY_EMBED_LETTER_BY_SLUG.satellite
		);
	} );

	it( 'defaults the zoom to 10 when falsy', () => {
		const src = getGoogleMapEmbedSrc( {
			...coords,
			zoom: 0,
			type: 'roadmap',
			apiKey: '',
		} );

		expect( new URL( src ).searchParams.get( 'z' ) ).toBe( '10' );
	} );
} );

describe( 'parseCoordinates', () => {
	it( 'accepts a valid numeric-string pair', () => {
		expect( parseCoordinates( '40.7', '-74.0' ) ).toEqual( {
			valid: true,
			lat: 40.7,
			lng: -74.0,
		} );
	} );

	it( 'rejects a missing latitude', () => {
		expect( parseCoordinates( '', '-74.0' ).valid ).toBe( false );
		expect( parseCoordinates( undefined, '-74.0' ).valid ).toBe( false );
	} );

	it( 'rejects a missing longitude', () => {
		expect( parseCoordinates( '40.7', '' ).valid ).toBe( false );
		expect( parseCoordinates( '40.7', null ).valid ).toBe( false );
	} );

	it( 'rejects non-numeric input', () => {
		expect( parseCoordinates( 'north', '-74.0' ).valid ).toBe( false );
		expect( parseCoordinates( '40.7', 'west' ).valid ).toBe( false );
	} );
} );
