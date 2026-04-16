/**
 * WordPress dependencies.
 */
import { createReduxStore, dispatch, register, select, subscribe } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import {
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getDateTimeOffset,
} from '../helpers/datetime';
const DEFAULT_STATE = {
	dateTimeStart: defaultDateTimeStart,
	dateTimeEnd: defaultDateTimeEnd,
	duration: null,
	timezone: '',
};

const actions = {
	setDateTimeStart( dateTimeStart ) {
		return {
			type: 'SET_DATETIME_START',
			dateTimeStart,
		};
	},
	setDateTimeEnd( dateTimeEnd ) {
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

// Initialize store from post meta once the editor is ready.
const unsubscribe = subscribe( () => {
	const meta = select( 'core/editor' )?.getEditedPostAttribute?.( 'meta' );
	const config =
		select( 'core/editor' )?.getEditorSettings?.()?.gatherpress?.config;

	if ( ! meta || ! config ) {
		return;
	}

	unsubscribe();

	const gpDispatch = dispatch( 'gatherpress/datetime' );

	if ( meta.gatherpress_datetime_start ) {
		gpDispatch.setDateTimeStart( meta.gatherpress_datetime_start );
	}
	if ( meta.gatherpress_datetime_end ) {
		gpDispatch.setDateTimeEnd( meta.gatherpress_datetime_end );
	}
	gpDispatch.setTimezone(
		meta.gatherpress_timezone || config.siteTimezone || '',
	);
} );
