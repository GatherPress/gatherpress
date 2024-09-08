/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';
import { dateTimeDatabaseFormat, getTimeZone } from '../helpers/datetime';

const DEFAULT_STATE = {
	dateTimeStart: getFromGlobal('eventDetails.dateTime.datetime_start'),
	dateTimeEnd: getFromGlobal('eventDetails.dateTime.datetime_end'),
	timezone: getFromGlobal('eventDetails.dateTime.timezone'),
	isFetching: false,
};

const actions = {
	setDateTimeStart(dateTimeStart) {
		return {
			type: 'SET_DATETIME_START',
			dateTimeStart,
		};
	},
	setDateTimeEnd(dateTimeEnd) {
		return {
			type: 'SET_DATETIME_END',
			dateTimeEnd,
		};
	},
	setTimezone(timezone) {
		return {
			type: 'SET_TIMEZONE',
			timezone,
		};
	},
	saveEventDetails({ dateTimeStart, dateTimeEnd, timezone }) {
		return async (dispatch) => {
			console.log('Starting saveEventDetails'); // Ensure this is logged

			try {
				console.log('Making API call...');
				await apiFetch({
					path: getFromGlobal('urls.eventRestApi') + '/datetime',
					method: 'POST',
					data: {
						post_id: getFromGlobal('eventDetails.postId'),
						datetime_start: moment
							.tz(dateTimeStart, getTimeZone())
							.format(dateTimeDatabaseFormat),
						datetime_end: moment
							.tz(dateTimeEnd, getTimeZone())
							.format(dateTimeDatabaseFormat),
						timezone,
						_wpnonce: getFromGlobal('misc.nonce'),
					},
				});
			} catch (error) {
				console.log(error);
			}
		};
	},
};

// Reducer
const reducer = (state = DEFAULT_STATE, action) => {
	switch (action.type) {
		case 'SET_DATETIME_START':
			return { ...state, dateTimeStart: action.dateTimeStart };
		case 'SET_DATETIME_END':
			return { ...state, dateTimeEnd: action.dateTimeEnd };
		case 'SET_TIMEZONE':
			return { ...state, timezone: action.timezone };
		case 'SAVE_EVENT_DETAILS_SUCCESS': // Correct action type
			return { ...state, isFetching: false };
		case 'SAVE_EVENT_DETAILS_FAILURE': // Correct action type
			return { ...state, isFetching: false, error: action.error }; // Add error handling if necessary
		case 'SET_FETCHING':
			return { ...state, isFetching: action.isFetching };
		default:
			return state;
	}
};

// Create and register the store
const store = createReduxStore('gatherpress/datetime', {
	reducer,
	actions,
	selectors: {
		getDateTimeStart: (state) => state.dateTimeStart,
		getDateTimeEnd: (state) => state.dateTimeEnd,
		getTimezone: (state) => state.timezone,
		isFetching: (state) => state.isFetching,
	},
});

register(store);
