/**
 * WordPress dependencies.
 */
import { createReduxStore, register } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromGlobal, setToGlobal } from '../helpers/globals';
import {
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getDateTimeOffset,
} from '../helpers/datetime';

const DEFAULT_STATE = {
	dateTimeStart: getFromGlobal( 'eventDetails.dateTime.datetime_start' )
		? getFromGlobal( 'eventDetails.dateTime.datetime_start' )
		: defaultDateTimeStart,
	dateTimeEnd: getFromGlobal( 'eventDetails.dateTime.datetime_end' )
		? getFromGlobal( 'eventDetails.dateTime.datetime_end' )
		: defaultDateTimeEnd,
	duration: null,
	timezone: getFromGlobal( 'eventDetails.dateTime.timezone' ),
};

const actions = {
	setDateTimeStart( dateTimeStart ) {
		setToGlobal( 'eventDetails.dateTime.datetime_start', dateTimeStart );

		return {
			type: 'SET_DATETIME_START',
			dateTimeStart,
		};
	},
	setDateTimeEnd( dateTimeEnd ) {
		setToGlobal( 'eventDetails.dateTime.datetime_end', dateTimeEnd );

		return {
			type: 'SET_DATETIME_END',
			dateTimeEnd,
		};
	},
	setDuration( duration ) {
		return {
			type: 'SET_DURATION',
			duration,
		};
	},
	setTimezone( timezone ) {
		setToGlobal( 'eventDetails.dateTime.timezone', timezone );

		return {
			type: 'SET_TIMEZONE',
			timezone,
		};
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_DATETIME_START':
			return { ...state, dateTimeStart: action.dateTimeStart };
		case 'SET_DATETIME_END':
			return { ...state, dateTimeEnd: action.dateTimeEnd };
		case 'SET_DURATION':
			return { ...state, duration: action.duration };
		case 'SET_TIMEZONE':
			return { ...state, timezone: action.timezone };
		default:
			return state;
	}
};

const store = createReduxStore( 'gatherpress/datetime', {
	reducer,
	actions,
	selectors: {
		getDateTimeStart: ( state ) => state.dateTimeStart,
		getDateTimeEnd: ( state ) => state.dateTimeEnd,
		getDuration: ( state ) =>
			false === state.duration ? false : getDateTimeOffset(),
		getTimezone: ( state ) => state.timezone,
	},
} );

register( store );
