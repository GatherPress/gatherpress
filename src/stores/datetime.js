/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { createReduxStore, hasStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';
import { dateTimeDatabaseFormat, getTimeZone } from '../helpers/datetime';

const DEFAULT_STATE = {
	datetimeStart: null,
	datetimeEnd: null,
	timezone: 'UTC',
	isFetching: false,
};

const actions = {
	setDatetimeStart(datetimeStart) {
		return {
			type: 'SET_DATETIME_START',
			datetimeStart,
		};
	},
	setDatetimeEnd(datetimeEnd) {
		return {
			type: 'SET_DATETIME_END',
			datetimeEnd,
		};
	},
	setTimezone(timezone) {
		return {
			type: 'SET_TIMEZONE',
			timezone,
		};
	},
	fetchEventDetails() {
		return async (dispatch) => {
			dispatch({ type: 'FETCH_EVENT_DETAILS_REQUEST' });

			try {
				const eventDetails = await apiFetch({ path: '/wp/v2/event/details' });
				dispatch({
					type: 'FETCH_EVENT_DETAILS_SUCCESS',
					datetimeStart: eventDetails.datetimeStart,
					datetimeEnd: eventDetails.datetimeEnd,
					timezone: eventDetails.timezone,
				});
			} catch (error) {
				dispatch({ type: 'FETCH_EVENT_DETAILS_FAILURE', error });
			}
		};
	},
	saveEventDetails({ datetimeStart, datetimeEnd, timezone }) {
		return async (dispatch) => {
            console.log('Starting saveEventDetails'); // Ensure this is logged

            // dispatch({ type: 'SET_FETCHING', isFetching: true });
			try {
				console.log('Making API call...');
				await apiFetch({
					path: getFromGlobal('urls.eventRestApi') + '/datetime',
					method: 'POST',
					data: {
						post_id: getFromGlobal('eventDetails.postId'),
						datetime_start: moment
							.tz(
								datetimeStart,
								getTimeZone()
							)
							.format(dateTimeDatabaseFormat),
						datetime_end: moment
							.tz(
								datetimeEnd,
								getTimeZone()
							)
							.format(dateTimeDatabaseFormat),
						timezone: timezone,
						_wpnonce: getFromGlobal('misc.nonce'),
					},
				});
				// dispatch({
				// 	type: 'SAVE_EVENT_DETAILS_SUCCESS',
				// 	datetimeStart,
				// 	datetimeEnd,
				// 	timezone,
				// });
			} catch (error) {
				console.log(error);
				// dispatch({ type: 'SAVE_EVENT_DETAILS_FAILURE', error });
			}
		};
	},
};

// Reducer
const reducer = (state = DEFAULT_STATE, action) => {
    switch (action.type) {
        case 'SET_DATETIME_START':
            return { ...state, datetimeStart: action.datetimeStart };
        case 'SET_DATETIME_END':
            return { ...state, datetimeEnd: action.datetimeEnd };
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
if ( !hasStore( 'gatherpress/datetime' ) ) {
	const store = createReduxStore('gatherpress/datetime', {
		reducer,
		actions,
		selectors: {
			getDatetimeStart: (state) => state.datetimeStart,
			getDatetimeEnd: (state) => state.datetimeEnd,
			getTimezone: (state) => state.timezone,
			isFetching: (state) => state.isFetching,
		},
	});

	register(store);
}
