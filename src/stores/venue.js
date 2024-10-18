import { createReduxStore, register } from '@wordpress/data';

const DEFAULT_STATE = {
	latitude: '',
	longitude: '',
};

const actions = {
	updateVenueLatitude(latitude) {
        console.log(latitude);
		// return {
		// 	type: 'UPDATE_VENUE_LATITUDE',
		// 	latitude,
		// };
	},
	updateVenueLongitude(longitude) {
        console.log(longitude);
		// return {
		// 	type: 'UPDATE_VENUE_LONGITUDE',
		// 	longitude,
		// };
	},
};

const reducer = (state = DEFAULT_STATE, action) => {
	switch (action.type) {
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

export { store };
