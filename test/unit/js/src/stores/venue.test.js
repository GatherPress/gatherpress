/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { select, dispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
// Import the actual store to get coverage.
import '../../../../../src/stores/venue';

describe( 'Venue store', () => {
	const STORE_NAME = 'gatherpress/venue';

	describe( 'initial state', () => {
		it( 'has latitude set to 0 by default', () => {
			const latitude = select( STORE_NAME ).getVenueLatitude();

			expect( latitude ).toBe( 0 );
		} );

		it( 'has longitude set to 0 by default', () => {
			const longitude = select( STORE_NAME ).getVenueLongitude();

			expect( longitude ).toBe( 0 );
		} );

		it( 'has mapCustomLatLong set to false by default', () => {
			const mapCustomLatLong = select( STORE_NAME ).getMapCustomLatLong();

			expect( mapCustomLatLong ).toBe( false );
		} );
	} );

	describe( 'selectors', () => {
		it( 'getVenueLatitude returns the latitude from state', () => {
			dispatch( STORE_NAME ).updateVenueLatitude( 40.7128 );

			const result = select( STORE_NAME ).getVenueLatitude();

			expect( result ).toBe( 40.7128 );
		} );

		it( 'getVenueLongitude returns the longitude from state', () => {
			dispatch( STORE_NAME ).updateVenueLongitude( -74.006 );

			const result = select( STORE_NAME ).getVenueLongitude();

			expect( result ).toBe( -74.006 );
		} );

		it( 'getMapCustomLatLong returns the mapCustomLatLong from state', () => {
			dispatch( STORE_NAME ).updateMapCustomLatLong( true );

			const result = select( STORE_NAME ).getMapCustomLatLong();

			expect( result ).toBe( true );
		} );
	} );

	describe( 'state changes', () => {
		it( 'updateVenueLatitude updates latitude in state', () => {
			dispatch( STORE_NAME ).updateVenueLatitude( 51.5074 );

			const latitude = select( STORE_NAME ).getVenueLatitude();

			expect( latitude ).toBe( 51.5074 );
		} );

		it( 'updateVenueLongitude updates longitude in state', () => {
			dispatch( STORE_NAME ).updateVenueLongitude( -0.1278 );

			const longitude = select( STORE_NAME ).getVenueLongitude();

			expect( longitude ).toBe( -0.1278 );
		} );

		it( 'updateMapCustomLatLong updates mapCustomLatLong in state', () => {
			dispatch( STORE_NAME ).updateMapCustomLatLong( true );

			const mapCustomLatLong = select( STORE_NAME ).getMapCustomLatLong();

			expect( mapCustomLatLong ).toBe( true );
		} );

		it( 'can update all coordinates together', () => {
			dispatch( STORE_NAME ).updateVenueLatitude( 48.8566 );
			dispatch( STORE_NAME ).updateVenueLongitude( 2.3522 );
			dispatch( STORE_NAME ).updateMapCustomLatLong( true );

			expect( select( STORE_NAME ).getVenueLatitude() ).toBe( 48.8566 );
			expect( select( STORE_NAME ).getVenueLongitude() ).toBe( 2.3522 );
			expect( select( STORE_NAME ).getMapCustomLatLong() ).toBe( true );
		} );

		it( 'handles negative coordinates', () => {
			dispatch( STORE_NAME ).updateVenueLatitude( -33.8688 );
			dispatch( STORE_NAME ).updateVenueLongitude( 151.2093 );

			expect( select( STORE_NAME ).getVenueLatitude() ).toBe( -33.8688 );
			expect( select( STORE_NAME ).getVenueLongitude() ).toBe( 151.2093 );
		} );

		it( 'preserves existing state when updating one property', () => {
			dispatch( STORE_NAME ).updateVenueLatitude( 35.6762 );
			dispatch( STORE_NAME ).updateVenueLongitude( 139.6503 );

			// Update only mapCustomLatLong.
			dispatch( STORE_NAME ).updateMapCustomLatLong( true );

			// Verify other properties are preserved.
			expect( select( STORE_NAME ).getVenueLatitude() ).toBe( 35.6762 );
			expect( select( STORE_NAME ).getVenueLongitude() ).toBe( 139.6503 );
		} );
	} );
} );
