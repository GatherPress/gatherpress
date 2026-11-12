/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

/**
 * Mock the Interactivity API so the test can call the store's init
 * callback with a crafted element and context.
 */
jest.mock(
	'@wordpress/interactivity',
	() => {
		const stores = {};

		return {
			store: ( name, config = {} ) => {
				stores[ name ] = config;
				return config;
			},
			getElement: jest.fn(),
			getContext: jest.fn(),
			__stores: stores,
		};
	},
	{ virtual: true },
);

/**
 * A Leaflet stand-in that records the options each marker gets.
 */
const mockMarker = jest.fn();

jest.mock( 'leaflet', () => {
	const map = {
		setView: jest.fn( () => map ),
		invalidateSize: jest.fn(),
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

jest.mock( 'leaflet-gesture-handling', () => ( {
	__esModule: true,
	GestureHandling: function GestureHandling() {},
} ) );

/**
 * WordPress dependencies
 */
import {
	__stores as stores,
	getElement,
	getContext,
} from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import '@src/blocks/venue-map/view';

/**
 * Mounts a Leaflet map for the given context and waits for it.
 *
 * @param {Object} context The block context the server would send.
 *
 * @return {Promise<void>} Resolves once the marker is placed.
 */
async function mountMap( context ) {
	document.body.innerHTML =
		'<div class="wp-block-gatherpress-venue-map"><img alt="" /></div>';

	getElement.mockReturnValue( {
		ref: document.querySelector( '.wp-block-gatherpress-venue-map' ),
	} );
	getContext.mockReturnValue( {
		latitude: '38.8976763',
		longitude: '-77.0365298',
		mapPlatform: 'osm',
		...context,
	} );

	stores[ 'gatherpress/venue-map' ].callbacks.init();

	// Let the Leaflet loader promise and its `then` settle.
	for ( let i = 0; 10 > i && 0 === mockMarker.mock.calls.length; i++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}
}

describe( 'venue-map Leaflet marker name', () => {
	beforeEach( () => {
		mockMarker.mockClear();
	} );

	it( 'names the marker after the venue', async () => {
		await mountMap( {
			address: '1600 Pennsylvania Avenue NW, Washington, DC 20500',
			markerLabel: 'QA Venue Hall',
		} );

		expect( mockMarker ).toHaveBeenCalledTimes( 1 );
		expect( mockMarker.mock.calls[ 0 ][ 1 ].alt ).toBe( 'QA Venue Hall' );
	} );

	it( 'falls back to the address, never to "Marker"', async () => {
		await mountMap( {
			address: '1600 Pennsylvania Avenue NW, Washington, DC 20500',
		} );

		expect( mockMarker ).toHaveBeenCalledTimes( 1 );
		expect( mockMarker.mock.calls[ 0 ][ 1 ].alt ).toBe(
			'1600 Pennsylvania Avenue NW, Washington, DC 20500',
		);
	} );
} );
