/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { select, dispatch, register, createReduxStore } from '@wordpress/data';

/**
 * Internal dependencies.
 */
jest.mock( '../../../../../src/helpers/globals', () => ( {
	getFromGlobal: jest.fn(),
	setToGlobal: jest.fn(),
} ) );

jest.mock( '../../../../../src/helpers/datetime', () => ( {
	defaultDateTimeStart: '2025-01-15 18:00:00',
	defaultDateTimeEnd: '2025-01-15 20:00:00',
	getDateTimeOffset: jest.fn( () => 2 ),
} ) );

import { getFromGlobal, setToGlobal } from '../../../../../src/helpers/globals';
import { getDateTimeOffset } from '../../../../../src/helpers/datetime';

describe( 'DateTime store', () => {
	const STORE_NAME = 'gatherpress/datetime';

	const DEFAULT_STATE = {
		dateTimeStart: '2025-01-15 18:00:00',
		dateTimeEnd: '2025-01-15 20:00:00',
		duration: null,
		timezone: 'America/New_York',
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

	const selectors = {
		getDateTimeStart: ( state ) => state.dateTimeStart,
		getDateTimeEnd: ( state ) => state.dateTimeEnd,
		getDuration: ( state ) =>
			false === state.duration ? false : getDateTimeOffset(),
		getTimezone: ( state ) => state.timezone,
	};

	const store = createReduxStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
	} );

	beforeEach( () => {
		getFromGlobal.mockReturnValue( null );
		setToGlobal.mockClear();
		getDateTimeOffset.mockReturnValue( 2 );
	} );

	register( store );

	describe( 'initial state', () => {
		it( 'has dateTimeStart set to default value', () => {
			const dateTimeStart = select( STORE_NAME ).getDateTimeStart();

			expect( dateTimeStart ).toBe( '2025-01-15 18:00:00' );
		} );

		it( 'has dateTimeEnd set to default value', () => {
			const dateTimeEnd = select( STORE_NAME ).getDateTimeEnd();

			expect( dateTimeEnd ).toBe( '2025-01-15 20:00:00' );
		} );

		it( 'has duration set to null by default', () => {
			const duration = select( STORE_NAME ).getDuration();

			expect( duration ).toBe( 2 );
		} );

		it( 'has timezone set to default value', () => {
			const timezone = select( STORE_NAME ).getTimezone();

			expect( timezone ).toBe( 'America/New_York' );
		} );
	} );

	describe( 'action creators', () => {
		it( 'setDateTimeStart creates correct action object', () => {
			const action = actions.setDateTimeStart( '2025-02-01 10:00:00' );

			expect( action ).toEqual( {
				type: 'SET_DATETIME_START',
				dateTimeStart: '2025-02-01 10:00:00',
			} );
		} );

		it( 'setDateTimeEnd creates correct action object', () => {
			const action = actions.setDateTimeEnd( '2025-02-01 12:00:00' );

			expect( action ).toEqual( {
				type: 'SET_DATETIME_END',
				dateTimeEnd: '2025-02-01 12:00:00',
			} );
		} );

		it( 'setDuration creates correct action object', () => {
			const action = actions.setDuration( 3 );

			expect( action ).toEqual( {
				type: 'SET_DURATION',
				duration: 3,
			} );
		} );

		it( 'setTimezone creates correct action object', () => {
			const action = actions.setTimezone( 'Europe/London' );

			expect( action ).toEqual( {
				type: 'SET_TIMEZONE',
				timezone: 'Europe/London',
			} );
		} );
	} );

	describe( 'selectors', () => {
		it( 'getDateTimeStart returns the dateTimeStart state', () => {
			const state = {
				dateTimeStart: '2025-03-01 14:00:00',
				dateTimeEnd: '2025-03-01 16:00:00',
				duration: null,
				timezone: 'UTC',
			};

			const result = selectors.getDateTimeStart( state );

			expect( result ).toBe( '2025-03-01 14:00:00' );
		} );

		it( 'getDateTimeEnd returns the dateTimeEnd state', () => {
			const state = {
				dateTimeStart: '2025-03-01 14:00:00',
				dateTimeEnd: '2025-03-01 16:00:00',
				duration: null,
				timezone: 'UTC',
			};

			const result = selectors.getDateTimeEnd( state );

			expect( result ).toBe( '2025-03-01 16:00:00' );
		} );

		it( 'getDuration returns false when duration is false', () => {
			const state = {
				dateTimeStart: '2025-03-01 14:00:00',
				dateTimeEnd: '2025-03-01 16:00:00',
				duration: false,
				timezone: 'UTC',
			};

			const result = selectors.getDuration( state );

			expect( result ).toBe( false );
		} );

		it( 'getDuration calls getDateTimeOffset when duration is not false', () => {
			const state = {
				dateTimeStart: '2025-03-01 14:00:00',
				dateTimeEnd: '2025-03-01 16:00:00',
				duration: null,
				timezone: 'UTC',
			};

			getDateTimeOffset.mockReturnValue( 1.5 );

			const result = selectors.getDuration( state );

			expect( result ).toBe( 1.5 );
			expect( getDateTimeOffset ).toHaveBeenCalled();
		} );

		it( 'getTimezone returns the timezone state', () => {
			const state = {
				dateTimeStart: '2025-03-01 14:00:00',
				dateTimeEnd: '2025-03-01 16:00:00',
				duration: null,
				timezone: 'Asia/Tokyo',
			};

			const result = selectors.getTimezone( state );

			expect( result ).toBe( 'Asia/Tokyo' );
		} );
	} );

	describe( 'state changes', () => {
		it( 'setDateTimeStart updates dateTimeStart state', () => {
			dispatch( STORE_NAME ).setDateTimeStart( '2025-04-01 09:00:00' );

			const dateTimeStart = select( STORE_NAME ).getDateTimeStart();

			expect( dateTimeStart ).toBe( '2025-04-01 09:00:00' );
			expect( setToGlobal ).toHaveBeenCalledWith(
				'eventDetails.dateTime.datetime_start',
				'2025-04-01 09:00:00'
			);
		} );

		it( 'setDateTimeEnd updates dateTimeEnd state', () => {
			dispatch( STORE_NAME ).setDateTimeEnd( '2025-04-01 11:00:00' );

			const dateTimeEnd = select( STORE_NAME ).getDateTimeEnd();

			expect( dateTimeEnd ).toBe( '2025-04-01 11:00:00' );
			expect( setToGlobal ).toHaveBeenCalledWith(
				'eventDetails.dateTime.datetime_end',
				'2025-04-01 11:00:00'
			);
		} );

		it( 'setDuration updates duration state', () => {
			getDateTimeOffset.mockReturnValue( 4 );
			dispatch( STORE_NAME ).setDuration( 4 );

			const duration = select( STORE_NAME ).getDuration();

			expect( duration ).toBe( 4 );
		} );

		it( 'setDuration with false returns false', () => {
			dispatch( STORE_NAME ).setDuration( false );

			const duration = select( STORE_NAME ).getDuration();

			expect( duration ).toBe( false );
		} );

		it( 'setTimezone updates timezone state', () => {
			dispatch( STORE_NAME ).setTimezone( 'Australia/Sydney' );

			const timezone = select( STORE_NAME ).getTimezone();

			expect( timezone ).toBe( 'Australia/Sydney' );
			expect( setToGlobal ).toHaveBeenCalledWith(
				'eventDetails.dateTime.timezone',
				'Australia/Sydney'
			);
		} );

		it( 'can update multiple state values independently', () => {
			dispatch( STORE_NAME ).setDateTimeStart( '2025-05-01 08:00:00' );
			dispatch( STORE_NAME ).setDateTimeEnd( '2025-05-01 10:00:00' );
			dispatch( STORE_NAME ).setTimezone( 'Pacific/Auckland' );

			expect( select( STORE_NAME ).getDateTimeStart() ).toBe(
				'2025-05-01 08:00:00'
			);
			expect( select( STORE_NAME ).getDateTimeEnd() ).toBe(
				'2025-05-01 10:00:00'
			);
			expect( select( STORE_NAME ).getTimezone() ).toBe(
				'Pacific/Auckland'
			);
		} );
	} );
} );
