/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import {createReduxStore, register, select} from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import {getFromGlobal, setToGlobal} from '../helpers/globals';
import {dateTimeDatabaseFormat, defaultDateTimeEnd, defaultDateTimeStart, getTimeZone} from '../helpers/datetime';
import {isEventPostType} from '../helpers/event';

const DEFAULT_STATE = {
	dateTimeStart: getFromGlobal('eventDetails.dateTime.datetime_start')
		? getFromGlobal('eventDetails.dateTime.datetime_start')
		: defaultDateTimeStart,
	dateTimeEnd: getFromGlobal('eventDetails.dateTime.datetime_end')
		? getFromGlobal('eventDetails.dateTime.datetime_end')
		: defaultDateTimeEnd,
	timezone: getFromGlobal('eventDetails.dateTime.timezone'),
	isFetching: false,
};

const actions = {
	setDateTimeStart(dateTimeStart) {
		setToGlobal('eventDetails.dateTime.datetime_start', dateTimeStart);

		return {
			type: 'SET_DATETIME_START',
			dateTimeStart,
		};
	},
	setDateTimeEnd(dateTimeEnd) {
		setToGlobal('eventDetails.dateTime.datetime_end', dateTimeEnd);

		return {
			type: 'SET_DATETIME_END',
			dateTimeEnd,
		};
	},
	setTimezone(timezone) {
		setToGlobal('eventDetails.dateTime.timezone', timezone);

		return {
			type: 'SET_TIMEZONE',
			timezone,
		};
	},
	saveEventDetails() {
		const isSavingPost = select('core/editor').isSavingPost();
		const isAutosavingPost = select('core/editor').isAutosavingPost();

		return async () => {
			if (isEventPostType() && isSavingPost && !isAutosavingPost) {
				await apiFetch({
					path: getFromGlobal('urls.eventRestApi') + '/datetime',
					method: 'POST',
					data: {
						post_id: getFromGlobal('eventDetails.postId'),
						datetime_start: moment
							.tz(getFromGlobal('eventDetails.dateTime.datetime_start'), getTimeZone())
							.format(dateTimeDatabaseFormat),
						datetime_end: moment
							.tz(getFromGlobal('eventDetails.dateTime.datetime_end'), getTimeZone())
							.format(dateTimeDatabaseFormat),
						timezone: getFromGlobal('eventDetails.dateTime.timezone'),
						_wpnonce: getFromGlobal('misc.nonce'),
					},
				});
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
