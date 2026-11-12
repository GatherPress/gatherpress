/**
 * External dependencies
 */
import { act, render } from '@testing-library/react';
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

/**
 * A Leaflet stand-in that records the options each marker gets.
 */
const mockMarker = jest.fn();

jest.mock( 'leaflet', () => {
	const map = {
		setView: jest.fn( () => map ),
		invalidateSize: jest.fn(),
		remove: jest.fn(),
	};
	const layer = { on: jest.fn(), addTo: jest.fn() };

	function DefaultIcon( options ) {
		this.options = options;
	}

	return {
		__esModule: true,
		default: {
			Map: { addInitHook: jest.fn() },
			Icon: { Default: DefaultIcon },
			map: jest.fn( () => map ),
			tileLayer: jest.fn( () => layer ),
			marker: ( latLng, options ) => {
				mockMarker( latLng, options );
				return { addTo: () => ( { bindPopup: jest.fn() } ) };
			},
		},
	};
} );

jest.mock( 'leaflet/dist/leaflet.css', () => ( {} ), { virtual: true } );
jest.mock(
	'leaflet-gesture-handling/dist/leaflet-gesture-handling.css',
	() => ( {} ),
	{ virtual: true },
);
jest.mock( 'leaflet-gesture-handling', () => ( {} ) );

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn( () => null ),
} ) );

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: jest.fn( () => '' ),
} ) );

/**
 * Internal dependencies
 */
import OpenStreetMap from '@src/components/OpenStreetMap';

/**
 * Renders the map and settles the Leaflet loader.
 *
 * The loader is a chain of mocked dynamic imports, so it finishes in a fixed
 * number of microtask turns. Each `act()` flushes the pending promises and
 * the React effects they trigger, without any wall-clock timeout.
 *
 * @param {JSX.Element} element The map element to render.
 *
 * @return {Promise<void>} Resolves once the marker is placed.
 */
async function renderMap( element ) {
	await act( async () => {
		render( element );
	} );

	for ( let i = 0; 50 > i && 0 === mockMarker.mock.calls.length; i++ ) {
		await act( async () => {} );
	}
}

describe( 'OpenStreetMap marker name', () => {
	beforeEach( () => {
		mockMarker.mockClear();
	} );

	it( 'names the marker after the venue', async () => {
		await renderMap(
			<OpenStreetMap
				location="1600 Pennsylvania Avenue NW, Washington, DC 20500"
				markerLabel="QA Venue Hall"
				latitude="38.8976763"
				longitude="-77.0365298"
			/>,
		);

		expect( mockMarker ).toHaveBeenCalled();
		expect( mockMarker.mock.calls.at( -1 )[ 1 ].alt ).toBe( 'QA Venue Hall' );
	} );

	it( 'falls back to the location, never to "Marker"', async () => {
		await renderMap(
			<OpenStreetMap
				location="1600 Pennsylvania Avenue NW, Washington, DC 20500"
				latitude="38.8976763"
				longitude="-77.0365298"
			/>,
		);

		expect( mockMarker ).toHaveBeenCalled();
		expect( mockMarker.mock.calls.at( -1 )[ 1 ].alt ).toBe(
			'1600 Pennsylvania Avenue NW, Washington, DC 20500',
		);
	} );
} );
