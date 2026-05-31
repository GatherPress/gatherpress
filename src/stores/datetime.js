/**
 * WordPress dependencies
 */
import { createReduxStore, dispatch, register, select, subscribe } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	dateTimeOffset,
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getDefaultDuration,
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
		// Return the raw stored value. Computing the matched preset here
		// ran a moment.tz comparison loop on every selector call, which
		// @wordpress/data invokes per subscriber per render — under IANA
		// timezones the multiplied moment.tz cost overflowed the call
		// stack on a single picker arrow keypress (#1607). Consumers that
		// need the matched preset (the conditional in DateTimeRange, the
		// SelectControl value in Duration, the gating in DateTimeStart's
		// effect) call `useMatchedDuration()` from `helpers/datetime`,
		// which memoizes the comparison on the actual store inputs.
		getDuration: ( state ) => state.duration,
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

	// Set the timezone before deriving any default end below, since
	// dateTimeOffset() reads it from the store.
	gpDispatch.setTimezone(
		meta.gatherpress_timezone || config.siteTimezone || '',
	);

	if ( meta.gatherpress_datetime_end ) {
		gpDispatch.setDateTimeEnd( meta.gatherpress_datetime_end );
	} else {
		// New event (no saved end): seed the end from the default duration
		// resolved against the live, possibly-filtered durationOptions, so the
		// end maps to a real preset and the Duration select renders. The
		// module-load default end is hardcoded to start + 2h, which leaves no
		// matching preset (and drops to the end-time picker) when a
		// `gatherpress.durationOptions` filter omits 2 (#1706).
		const defaultDuration = getDefaultDuration();
		gpDispatch.setDuration( defaultDuration );
		gpDispatch.setDateTimeEnd( dateTimeOffset( defaultDuration ) );
	}
} );
