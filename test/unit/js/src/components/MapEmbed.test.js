/**
 * External dependencies.
 */
import { render, act } from '@testing-library/react';
import { expect, test, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies.
 */
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

/**
 * Internal dependencies.
 */
import MapEmbed from '../../../../../src/components/MapEmbed';
import { select } from '@wordpress/data';

beforeEach( () => {
	jest.clearAllMocks();

	// Default mock for select().
	select.mockImplementation( ( store ) => {
		if ( 'core' === store ) {
			return {
				canUser: jest.fn( () => false ),
			};
		}
		if ( 'core/edit-post' === store ) {
			return null;
		}
		return null;
	} );
} );

/**
 * Coverage for MapEmbed.
 */
test( 'MapEmbed returns empty when no location is provided', () => {
	const { container } = render( <MapEmbed /> );

	expect( container ).toHaveTextContent( '' );
} );

test( 'OSM MapEmbed returns a placeholder div when location is set but no coordinates', async () => {
	global.GatherPress = {
		settings: {
			mapPlatform: 'osm',
		},
	};

	let container;

	await act( async () => {
		const result = render(
			<MapEmbed location="50 South Fullerton Avenue, Montclair, NJ 07042" />,
		);
		container = result.container;
	} );

	// Should render a placeholder div with grey background when no coordinates.
	expect( container.children[ 0 ] ).toBeInTheDocument();
	expect( container.children[ 0 ] ).toHaveStyle( {
		backgroundColor: 'rgb(224, 224, 224)',
	} );
} );

test( 'Google MapEmbed returns address in source when location is set', () => {
	global.GatherPress = {
		settings: {
			mapPlatform: 'google',
		},
	};
	const { container } = render(
		<MapEmbed
			location="50 South Fullerton Avenue, Montclair, NJ 07042"
			latitude="40.8117036"
			longitude="-74.2187738"
		/>,
	);

	expect( container.children[ 0 ].getAttribute( 'src' ) ).toContain(
		'?q=40.8117036%2C-74.2187738',
	);
	expect( container.children[ 0 ].getAttribute( 'src' ) ).toContain( '&z=10' );
	expect( container.children[ 0 ].getAttribute( 'src' ) ).toContain( '&t=m' );
	expect( container.children[ 0 ].getAttribute( 'src' ) ).toContain(
		'&output=embed',
	);
	expect( container.children[ 0 ] ).toHaveStyle(
		'border: 0px; height: 300px; width: 100%;',
	);
} );

test( 'MapEmbed returns address in source when location, zoom, map type, height, and class are set', () => {
	const { container } = render(
		<MapEmbed
			location="50 South Fullerton Avenue, Montclair, NJ 07042"
			latitude="40.8117036"
			longitude="-74.2187738"
			zoom={ 20 }
			type="k"
			className="unit-test"
			height={ 100 }
		/>,
	);
	expect( container.children[ 0 ].getAttribute( 'src' ) ).toContain(
		'?q=40.8117036%2C-74.2187738',
	);
	expect( container.children[ 0 ].getAttribute( 'src' ) ).toContain( '&z=20' );
	expect( container.children[ 0 ].getAttribute( 'src' ) ).toContain( '&t=k' );
	expect( container.children[ 0 ] ).toHaveStyle(
		'border: 0px; height: 100px; width: 100%;',
	);
	expect( container.children[ 0 ] ).toHaveClass( 'unit-test' );
} );

test( 'MapEmbed uses default location when admin user is not in post editor and no location provided', () => {
	global.GatherPress = {
		settings: {
			mapPlatform: 'google',
		},
	};

	// Mock isAdmin = true, isPostEditor = false.
	select.mockImplementation( ( store ) => {
		if ( 'core' === store ) {
			return {
				canUser: jest.fn( () => true ),
			};
		}
		if ( 'core/edit-post' === store ) {
			return null; // Not in post editor.
		}
		return null;
	} );

	const { container } = render( <MapEmbed /> );

	// Should render a Google Map iframe with the default location.
	// The component sets location to "660 4th Street #119 San Francisco CA 94107, USA".
	// Since no coordinates are provided, the URL will use "undefined" for lat/lng.
	// We just verify that it rendered a map (iframe exists).
	expect( container.children[ 0 ] ).toBeInTheDocument();
	expect( container.children[ 0 ].tagName ).toBe( 'IFRAME' );
} );

test( 'MapEmbed returns empty fragment when mapPlatform is invalid', () => {
	global.GatherPress = {
		settings: {
			mapPlatform: 'invalid-platform',
		},
	};

	const { container } = render(
		<MapEmbed location="50 South Fullerton Avenue, Montclair, NJ 07042" />,
	);

	// Should return empty fragment.
	expect( container ).toHaveTextContent( '' );
} );
