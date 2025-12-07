/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { select, dispatch, register, createReduxStore } from '@wordpress/data';

describe( 'Venue store', () => {
	const STORE_NAME = 'gatherpress/venue';

	const DEFAULT_STATE = {
		latitude: 0,
		longitude: 0,
		mapCustomLatLong: false,
	};

	const actions = {
		updateVenueLatitude( latitude ) {
			return {
				type: 'UPDATE_VENUE_LATITUDE',
				latitude,
			};
		},
		updateVenueLongitude( longitude ) {
			return {
				type: 'UPDATE_VENUE_LONGITUDE',
				longitude,
			};
		},
		updateMapCustomLatLong( mapCustomLatLong ) {
			return {
				type: 'UPDATE_MAP_CUSTOM_LAT_LONG',
				mapCustomLatLong,
			};
		},
	};

	const reducer = ( state = DEFAULT_STATE, action ) => {
		switch ( action.type ) {
			case 'UPDATE_VENUE_LATITUDE':
				return {
					...state,
					latitude: action.latitude,
				};
			case 'UPDATE_VENUE_LONGITUDE':
				return {
					...state,
					longitude: action.longitude,
				};
			case 'UPDATE_MAP_CUSTOM_LAT_LONG':
				return {
					...state,
					mapCustomLatLong: action.mapCustomLatLong,
				};
			default:
				return state;
		}
	};

	const selectors = {
		getVenueLatitude( state ) {
			return state.latitude;
		},
		getVenueLongitude( state ) {
			return state.longitude;
		},
		getMapCustomLatLong( state ) {
			return state.mapCustomLatLong;
		},
	};

	const store = createReduxStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
	} );

	register( store );

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

	describe( 'action creators', () => {
		it( 'updateVenueLatitude creates correct action object', () => {
			const action = actions.updateVenueLatitude( 40.7128 );

			expect( action ).toEqual( {
				type: 'UPDATE_VENUE_LATITUDE',
				latitude: 40.7128,
			} );
		} );

		it( 'updateVenueLongitude creates correct action object', () => {
			const action = actions.updateVenueLongitude( -74.006 );

			expect( action ).toEqual( {
				type: 'UPDATE_VENUE_LONGITUDE',
				longitude: -74.006,
			} );
		} );

		it( 'updateMapCustomLatLong creates correct action object', () => {
			const action = actions.updateMapCustomLatLong( true );

			expect( action ).toEqual( {
				type: 'UPDATE_MAP_CUSTOM_LAT_LONG',
				mapCustomLatLong: true,
			} );
		} );
	} );

	describe( 'selectors', () => {
		it( 'getVenueLatitude returns the latitude from state', () => {
			const state = { latitude: 40.7128, longitude: -74.006, mapCustomLatLong: false };

			const result = selectors.getVenueLatitude( state );

			expect( result ).toBe( 40.7128 );
		} );

		it( 'getVenueLongitude returns the longitude from state', () => {
			const state = { latitude: 40.7128, longitude: -74.006, mapCustomLatLong: false };

			const result = selectors.getVenueLongitude( state );

			expect( result ).toBe( -74.006 );
		} );

		it( 'getMapCustomLatLong returns the mapCustomLatLong from state', () => {
			const state = { latitude: 40.7128, longitude: -74.006, mapCustomLatLong: true };

			const result = selectors.getMapCustomLatLong( state );

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
