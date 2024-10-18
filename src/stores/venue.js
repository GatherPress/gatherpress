import { createReduxStore, register } from '@wordpress/data';

const DEFAULT_STATE = {
	latitude: 0,
	longitude: 0,
};

const actions = {
	updateVenueLatitude(latitude) {
		return {
			type: 'UPDATE_VENUE_LATITUDE',
			latitude,
		};
	},
	updateVenueLongitude(longitude) {
		return {
			type: 'UPDATE_VENUE_LONGITUDE',
			longitude,
		};
	},
};

const reducer = (state = DEFAULT_STATE, action) => {
	switch (action.type) {
		case 'UPDATE_VENUE_LATITUDE':
            console.log('Updating latitude:', action.latitude);
			return {
				...state,
				latitude: action.latitude,
			};
		case 'UPDATE_VENUE_LONGITUDE':
            console.log('Updating longitude:', action.longitude);
			return {
				...state,
				longitude: action.longitude,
			};
		default:
			return state;
	}
};

const selectors = {
	getVenueLatitude(state) {
		return state.latitude;
	},
	getVenueLongitude(state) {
		return state.longitude;
	},
};

const store = createReduxStore('gatherpress/venue', {
	reducer,
	actions,
	selectors,
});

register(store);
