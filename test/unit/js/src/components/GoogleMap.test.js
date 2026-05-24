/**
 * External dependencies.
 */
import { expect, test } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	getGoogleMapEmbedSrc,
	toMapsEmbedApiMapType,
} from '@src/components/GoogleMap';

test( 'toMapsEmbedApiMapType coerces block slugs to Embed API allow-list', () => {
	expect( toMapsEmbedApiMapType( 'roadmap' ) ).toBe( 'roadmap' );
	expect( toMapsEmbedApiMapType( 'terrain' ) ).toBe( 'roadmap' );
	expect( toMapsEmbedApiMapType( 'satellite' ) ).toBe( 'satellite' );
	expect( toMapsEmbedApiMapType( 'hybrid' ) ).toBe( 'satellite' );
} );

test( 'toMapsEmbedApiMapType falls back to roadmap for unknown types', () => {
	expect( toMapsEmbedApiMapType( '' ) ).toBe( 'roadmap' );
	expect( toMapsEmbedApiMapType( 'bogus' ) ).toBe( 'roadmap' );
} );

test( 'getGoogleMapEmbedSrc never sends hybrid or terrain as maptype when key is set', () => {
	const base = {
		latitude: '40',
		longitude: '-74',
		zoom: 10,
		apiKey: 'k',
	};
	const hybridSrc = getGoogleMapEmbedSrc( { ...base, type: 'hybrid' } );
	expect( hybridSrc ).toContain( 'maptype=satellite' );
	expect( hybridSrc ).not.toContain( 'maptype=hybrid' );

	const terrainSrc = getGoogleMapEmbedSrc( { ...base, type: 'terrain' } );
	expect( terrainSrc ).toContain( 'maptype=roadmap' );
	expect( terrainSrc ).not.toContain( 'maptype=terrain' );
} );

test( 'getGoogleMapEmbedSrc coerces legacy hybrid and terrain t= codes without key', () => {
	const base = {
		latitude: '40',
		longitude: '-74',
		zoom: 10,
		apiKey: '',
	};
	expect( getGoogleMapEmbedSrc( { ...base, type: 'hybrid' } ) ).toContain(
		'&t=k',
	);
	expect( getGoogleMapEmbedSrc( { ...base, type: 'terrain' } ) ).toContain(
		'&t=m',
	);
} );
