/**
 * External dependencies
 */
import { afterEach, beforeEach, describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	clearGoogleMapsApiCache,
	loadGoogleMapsApi,
} from '@src/helpers/google-maps-api';

/**
 * Find the Google Maps API script tags currently in a document.
 *
 * @param {Document} doc Document to search.
 *
 * @return {HTMLScriptElement[]} Matching script elements.
 */
function getApiScripts( doc = document ) {
	return Array.from( doc.querySelectorAll( 'script' ) ).filter( ( s ) =>
		s.src.startsWith( 'https://maps.googleapis.com/maps/api/js' )
	);
}

/**
 * Resolve a pending load by invoking the global callback the script tag
 * registered, mimicking the API's bootstrap.
 *
 * @param {HTMLScriptElement} script Script whose callback should fire.
 * @param {Object}            maps   Fake `google.maps` namespace to install.
 *
 * @return {void}
 */
function fireApiCallback( script, maps ) {
	const callbackName = new URL( script.src ).searchParams.get( 'callback' );
	window.google = { maps };
	window[ callbackName ]();
}

describe( 'loadGoogleMapsApi', () => {
	beforeEach( () => {
		clearGoogleMapsApiCache();
	} );

	afterEach( () => {
		getApiScripts().forEach( ( script ) => script.remove() );
		delete window.google;
	} );

	it( 'rejects without a key', async () => {
		await expect( loadGoogleMapsApi( '', document ) ).rejects.toThrow(
			'API key is required'
		);
		await expect(
			loadGoogleMapsApi( undefined, document )
		).rejects.toThrow( 'API key is required' );
		await expect( loadGoogleMapsApi( '   ', document ) ).rejects.toThrow(
			'API key is required'
		);
		expect( getApiScripts() ).toHaveLength( 0 );
	} );

	it( 'appends one script per key and resolves via the global callback', async () => {
		const promise = loadGoogleMapsApi( ' unit-test-key ', document );

		const scripts = getApiScripts();
		expect( scripts ).toHaveLength( 1 );

		const params = new URL( scripts[ 0 ].src ).searchParams;
		expect( params.get( 'key' ) ).toBe( 'unit-test-key' );
		expect( params.get( 'loading' ) ).toBe( 'async' );
		expect( params.get( 'callback' ) ).toMatch(
			/^gatherpressGoogleMapsApiReady_\d+$/
		);
		expect( scripts[ 0 ].async ).toBe( true );

		const fakeMaps = { Map: () => {} };
		fireApiCallback( scripts[ 0 ], fakeMaps );

		await expect( promise ).resolves.toBe( fakeMaps );
		// The one-shot global callback is removed once it has fired.
		expect(
			window[ params.get( 'callback' ) ]
		).toBeUndefined();
	} );

	it( 'shares one load between repeat calls for the same document and key', () => {
		const first = loadGoogleMapsApi( 'unit-test-key', document );
		const second = loadGoogleMapsApi( 'unit-test-key', document );

		expect( second ).toBe( first );
		expect( getApiScripts() ).toHaveLength( 1 );
	} );

	it( 'resolves without a script when the namespace is already present', async () => {
		const fakeMaps = { Map: () => {} };
		window.google = { maps: fakeMaps };

		await expect(
			loadGoogleMapsApi( 'unit-test-key', document )
		).resolves.toBe( fakeMaps );
		expect( getApiScripts() ).toHaveLength( 0 );
	} );

	it( 'defaults to the global document when none is passed', () => {
		loadGoogleMapsApi( 'unit-test-key' );

		expect( getApiScripts() ).toHaveLength( 1 );
	} );

	it( 'falls back to the global window when the document has no defaultView', async () => {
		const detachedDoc = {
			defaultView: null,
			head: document.head,
			createElement: ( tag ) => document.createElement( tag ),
		};
		const fakeMaps = { Map: () => {} };
		window.google = { maps: fakeMaps };

		await expect(
			loadGoogleMapsApi( 'unit-test-key', detachedDoc )
		).resolves.toBe( fakeMaps );
	} );

	it( 'rejects on script error, removes the tag, and retries fresh', async () => {
		const promise = loadGoogleMapsApi( 'unit-test-key', document );
		const [ script ] = getApiScripts();
		const callbackName = new URL( script.src ).searchParams.get(
			'callback'
		);

		script.dispatchEvent( new Event( 'error' ) );

		await expect( promise ).rejects.toThrow( 'could not be loaded' );
		expect( window[ callbackName ] ).toBeUndefined();
		expect( getApiScripts() ).toHaveLength( 0 );

		// The failed entry was evicted — a retry issues a fresh script.
		const retry = loadGoogleMapsApi( 'unit-test-key', document );
		expect( retry ).not.toBe( promise );
		expect( getApiScripts() ).toHaveLength( 1 );
	} );
} );
