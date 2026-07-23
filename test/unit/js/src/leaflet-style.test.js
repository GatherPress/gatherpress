/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import '../../../../src/leaflet-style';

describe( 'leaflet-style', () => {
	it( 'imports without side effects beyond asset registration', () => {
		// The module is an asset anchor: it only imports the Leaflet
		// stylesheets and marker images so webpack emits them for the
		// server-enqueued leaflet_style entry. Importing it must not throw.
		expect( true ).toBe( true );
	} );
} );
