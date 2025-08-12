/**
 * WordPress dependencies.
 */
import { createReduxStore, register } from '@wordpress/data';

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

const store = createReduxStore( 'gatherpress/venue', {
	reducer,
	actions,
	selectors,
} );

register( store );
