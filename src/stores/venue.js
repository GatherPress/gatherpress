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
