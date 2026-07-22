/**
 * External dependencies
 */
import { render, act } from '@testing-library/react';
import { expect, test, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Mock WordPress data
 */
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn( () => null ),
} ) );

/**
 * Mock Google Maps API loader
 */
jest.mock( '@src/helpers/google-maps-api', () => ( {
	loadGoogleMapsApi: jest.fn(),
} ) );

/**
 * Internal dependencies
 */
import GoogleMap, {
	getGoogleMapEmbedSrc,
	toGoogleMapType,
	toMapsEmbedApiMapType,
} from '@src/components/GoogleMap';
import { loadGoogleMapsApi } from '@src/helpers/google-maps-api';
import { select } from '@wordpress/data';

/**
 * Build a fake `google.maps` namespace whose constructors record their calls.
 *
 * @return {Object} Fake namespace plus captured instances.
 */
function createFakeMapsNamespace() {
	const mapInstance = {
		setCenter: jest.fn(),
		setZoom: jest.fn(),
		setMapTypeId: jest.fn(),
	};
	const markerInstance = {
		setPosition: jest.fn(),
		setTitle: jest.fn(),
	};
	const maps = {
		Map: jest.fn( () => mapInstance ),
		Marker: jest.fn( () => markerInstance ),
	};

	return { maps, mapInstance, markerInstance };
}

beforeEach( () => {
	jest.clearAllMocks();
	select.mockReturnValue( null );
} );

test( 'toGoogleMapType passes canonical slugs through and defaults unknowns to roadmap', () => {
	expect( toGoogleMapType( 'roadmap' ) ).toBe( 'roadmap' );
	expect( toGoogleMapType( 'satellite' ) ).toBe( 'satellite' );
	expect( toGoogleMapType( 'hybrid' ) ).toBe( 'hybrid' );
	expect( toGoogleMapType( 'terrain' ) ).toBe( 'terrain' );
	expect( toGoogleMapType( '' ) ).toBe( 'roadmap' );
	expect( toGoogleMapType( 'bogus' ) ).toBe( 'roadmap' );
} );

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

test( 'getGoogleMapEmbedSrc defaults zoom to 10 when unset', () => {
	const src = getGoogleMapEmbedSrc( {
		latitude: '40',
		longitude: '-74',
		type: 'roadmap',
		apiKey: '',
	} );
	expect( src ).toContain( '&z=10' );
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

test( 'GoogleMap renders a placeholder when coordinates are missing', () => {
	const { container } = render(
		<GoogleMap location="Test" apiKey="unit-test-key" />,
	);

	expect( container.children[ 0 ].tagName ).toBe( 'DIV' );
	expect( container.children[ 0 ] ).toHaveStyle( {
		backgroundColor: 'rgb(224, 224, 224)',
	} );
	expect( loadGoogleMapsApi ).not.toHaveBeenCalled();
} );

test( 'GoogleMap without a key renders the keyless legacy embed iframe', () => {
	const { container } = render(
		<GoogleMap
			location="Test"
			latitude="40.81"
			longitude="-74.21"
			zoom={ 12 }
			type="roadmap"
		/>,
	);

	const iframe = container.children[ 0 ];
	expect( iframe.tagName ).toBe( 'IFRAME' );
	expect( iframe.getAttribute( 'src' ) ).toContain(
		'https://maps.google.com/maps?',
	);
	expect( iframe.hasAttribute( 'inert' ) ).toBe( false );
	expect( loadGoogleMapsApi ).not.toHaveBeenCalled();
} );

test( 'GoogleMap with a key mounts a Maps JavaScript API map with a marker', async () => {
	const { maps } = createFakeMapsNamespace();
	loadGoogleMapsApi.mockResolvedValue( maps );

	let container;
	await act( async () => {
		( { container } = render(
			<GoogleMap
				location="Test venue"
				latitude="40.81"
				longitude="-74.21"
				zoom={ 15 }
				type="hybrid"
				className="unit-test"
				apiKey=" unit-test-key "
			/>,
		) );
	} );

	const mount = container.children[ 0 ];
	expect( mount.tagName ).toBe( 'DIV' );
	expect( mount ).toHaveClass( 'unit-test' );

	expect( loadGoogleMapsApi ).toHaveBeenCalledWith(
		'unit-test-key',
		document,
	);
	expect( maps.Map ).toHaveBeenCalledWith( mount, {
		center: { lat: 40.81, lng: -74.21 },
		zoom: 15,
		mapTypeId: 'hybrid',
	} );
	expect( maps.Marker ).toHaveBeenCalledWith( {
		position: { lat: 40.81, lng: -74.21 },
		map: maps.Map.mock.results[ 0 ].value,
		title: 'Test venue',
	} );
} );

test( 'GoogleMap defaults zoom to 10 and unknown types to roadmap on the JS API path', async () => {
	const { maps } = createFakeMapsNamespace();
	loadGoogleMapsApi.mockResolvedValue( maps );

	await act( async () => {
		render(
			<GoogleMap
				location="Test"
				latitude="40.81"
				longitude="-74.21"
				type="bogus"
				apiKey="unit-test-key"
			/>,
		);
	} );

	expect( maps.Map ).toHaveBeenCalledWith(
		expect.anything(),
		expect.objectContaining( { zoom: 10, mapTypeId: 'roadmap' } ),
	);
} );

test( 'GoogleMap re-points the existing map instance when props change', async () => {
	const { maps, mapInstance, markerInstance } = createFakeMapsNamespace();
	loadGoogleMapsApi.mockResolvedValue( maps );

	let rerender;
	await act( async () => {
		( { rerender } = render(
			<GoogleMap
				location="Test"
				latitude="40.81"
				longitude="-74.21"
				zoom={ 15 }
				type="hybrid"
				apiKey="unit-test-key"
			/>,
		) );
	} );

	await act( async () => {
		rerender(
			<GoogleMap
				location="Renamed venue"
				latitude="41.00"
				longitude="-75.00"
				zoom={ 12 }
				type="terrain"
				apiKey="unit-test-key"
			/>,
		);
	} );

	// One map — no remount on prop changes.
	expect( maps.Map ).toHaveBeenCalledTimes( 1 );
	expect( mapInstance.setCenter ).toHaveBeenCalledWith( {
		lat: 41,
		lng: -75,
	} );
	expect( mapInstance.setZoom ).toHaveBeenCalledWith( 12 );
	expect( mapInstance.setMapTypeId ).toHaveBeenCalledWith( 'terrain' );
	expect( markerInstance.setPosition ).toHaveBeenCalledWith( {
		lat: 41,
		lng: -75,
	} );
	expect( markerInstance.setTitle ).toHaveBeenCalledWith(
		'Renamed venue',
	);
} );

test( 'GoogleMap falls back to the keyed Embed API iframe when the JS API fails to load', async () => {
	loadGoogleMapsApi.mockRejectedValue( new Error( 'blocked' ) );

	let container;
	await act( async () => {
		( { container } = render(
			<GoogleMap
				location="Test"
				latitude="40.81"
				longitude="-74.21"
				zoom={ 15 }
				type="terrain"
				apiKey="unit-test-key"
			/>,
		) );
	} );

	const iframe = container.children[ 0 ];
	expect( iframe.tagName ).toBe( 'IFRAME' );
	const src = iframe.getAttribute( 'src' );
	expect( src ).toContain( 'https://www.google.com/maps/embed/v1/view?' );
	expect( src ).toContain( 'key=unit-test-key' );
	// Embed API only offers roadmap/satellite; terrain coerces to roadmap.
	expect( src ).toContain( 'maptype=roadmap' );
} );

test( 'GoogleMap ignores a load settled after unmount', async () => {
	const { maps } = createFakeMapsNamespace();
	let resolveLoad;
	loadGoogleMapsApi.mockReturnValue(
		new Promise( ( resolve ) => {
			resolveLoad = resolve;
		} ),
	);

	const { unmount } = render(
		<GoogleMap
			location="Test"
			latitude="40.81"
			longitude="-74.21"
			zoom={ 15 }
			type="roadmap"
			apiKey="unit-test-key"
		/>,
	);

	unmount();

	await act( async () => {
		resolveLoad( maps );
	} );

	expect( maps.Map ).not.toHaveBeenCalled();
} );

test( 'GoogleMap ignores a load rejected after unmount', async () => {
	let rejectLoad;
	loadGoogleMapsApi.mockReturnValue(
		new Promise( ( resolve, reject ) => {
			rejectLoad = reject;
		} ),
	);

	const { unmount } = render(
		<GoogleMap
			location="Test"
			latitude="40.81"
			longitude="-74.21"
			zoom={ 15 }
			type="roadmap"
			apiKey="unit-test-key"
		/>,
	);

	unmount();

	await act( async () => {
		rejectLoad( new Error( 'blocked' ) );
	} );

	// No state update on an unmounted component — nothing to assert beyond
	// the act() above not warning; the component is gone.
	expect( loadGoogleMapsApi ).toHaveBeenCalledTimes( 1 );
} );

test( 'GoogleMap skips map updates while the fallback iframe is showing', async () => {
	loadGoogleMapsApi.mockRejectedValue( new Error( 'blocked' ) );

	let rerender;
	await act( async () => {
		( { rerender } = render(
			<GoogleMap
				location="Test"
				latitude="40.81"
				longitude="-74.21"
				zoom={ 15 }
				type="roadmap"
				apiKey="unit-test-key"
			/>,
		) );
	} );

	// The failure swapped the mount div for the fallback iframe. A prop
	// change re-runs the effect with no container to mount into — the load
	// result (here another rejection already surfaced) must be a no-op.
	const { maps } = createFakeMapsNamespace();
	loadGoogleMapsApi.mockResolvedValue( maps );

	await act( async () => {
		rerender(
			<GoogleMap
				location="Test"
				latitude="40.81"
				longitude="-74.21"
				zoom={ 12 }
				type="roadmap"
				apiKey="unit-test-key"
			/>,
		);
	} );

	expect( maps.Map ).not.toHaveBeenCalled();
} );

test( 'GoogleMap marks the fallback iframe inert inside the post editor', async () => {
	select.mockImplementation( ( store ) =>
		'core/edit-post' === store ? {} : null,
	);
	loadGoogleMapsApi.mockRejectedValue( new Error( 'blocked' ) );

	let container;
	await act( async () => {
		( { container } = render(
			<GoogleMap
				location="Test"
				latitude="40.81"
				longitude="-74.21"
				zoom={ 15 }
				type="roadmap"
				apiKey="unit-test-key"
			/>,
		) );
	} );

	const iframe = container.children[ 0 ];
	expect( iframe.tagName ).toBe( 'IFRAME' );
	expect( iframe.hasAttribute( 'inert' ) ).toBe( true );
} );

test( 'GoogleMap marks the keyless iframe inert inside the post editor', () => {
	select.mockImplementation( ( store ) =>
		'core/edit-post' === store ? {} : null,
	);

	const { container } = render(
		<GoogleMap
			location="Test"
			latitude="40.81"
			longitude="-74.21"
			zoom={ 12 }
			type="roadmap"
		/>,
	);

	const iframe = container.children[ 0 ];
	expect( iframe.tagName ).toBe( 'IFRAME' );
	expect( iframe.hasAttribute( 'inert' ) ).toBe( true );
} );

test( 'GoogleMap marks the map container inert inside the post editor', async () => {
	select.mockImplementation( ( store ) =>
		'core/edit-post' === store ? {} : null,
	);
	const { maps } = createFakeMapsNamespace();
	loadGoogleMapsApi.mockResolvedValue( maps );

	let container;
	await act( async () => {
		( { container } = render(
			<GoogleMap
				location="Test"
				latitude="40.81"
				longitude="-74.21"
				zoom={ 15 }
				type="roadmap"
				apiKey="unit-test-key"
			/>,
		) );
	} );

	expect( container.children[ 0 ].hasAttribute( 'inert' ) ).toBe( true );
} );
