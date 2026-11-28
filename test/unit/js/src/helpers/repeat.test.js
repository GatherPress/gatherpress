/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import moment from 'moment';
import 'moment-timezone';

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	REPEAT_FIELD,
	defaultPattern,
	editablePattern,
	hasStarted,
	intervalUnit,
	listOf,
	monthlyChoiceOf,
	monthlyChoices,
	summarize,
	toRequest,
	tomorrowIn,
	useIsStartedSeries,
	weekdayName,
	weekdayOf,
} from '@src/helpers/repeat';

// A Proxy replaces only useSelect, since the datetime helpers need the real store.
jest.mock( '@wordpress/data', () => {
	const mockActual = jest.requireActual( '@wordpress/data' );
	const mockHooks = { useSelect: jest.fn() };

	return new Proxy( mockActual, {
		get: ( target, prop ) =>
			prop in mockHooks ? mockHooks[ prop ] : target[ prop ],
	} );
} );

describe( 'repeat helpers', () => {
	it( 'names weekdays and reads a date\'s weekday', () => {
		expect( weekdayName( 'FR' ) ).toBe( 'Friday' );
		expect( weekdayName( 'XX' ) ).toBe( 'XX' );
		expect( weekdayOf( '2026-12-11' ) ).toBe( 'FR' );
		expect( weekdayOf( '2026-12-13 19:00:00' ) ).toBe( 'SU' );
	} );

	it( 'offers the monthly choices a start date allows', () => {
		// Friday, December 11, 2026 is the second Friday.
		expect( monthlyChoices( '2026-12-11' ).map( ( c ) => c.label ) ).toEqual( [
			'On day 11',
			'On the second Friday',
		] );

		// Thursday, December 31, 2026 is the fifth Thursday, so only "last".
		expect( monthlyChoices( '2026-12-31' ).map( ( c ) => c.value ) ).toEqual( [ 'day', 'last' ] );

		// Thursday, February 25, 2027 is the fourth and the last Thursday.
		const both = monthlyChoices( '2027-02-25' );

		expect( both.map( ( c ) => c.value ) ).toEqual( [ 'day', 'weekday', 'last' ] );
		expect( both[ 1 ].pattern ).toEqual( { month_day: null, ordinal_weekday: [ 4, 'TH' ] } );
		expect( both[ 2 ].pattern ).toEqual( { month_day: null, ordinal_weekday: [ -1, 'TH' ] } );
	} );

	it( 'tells which monthly choice a pattern uses', () => {
		expect( monthlyChoiceOf( { month_day: 11 } ) ).toBe( 'day' );
		expect( monthlyChoiceOf( { ordinal_weekday: [ 2, 'FR' ] } ) ).toBe( 'weekday' );
		expect( monthlyChoiceOf( { ordinal_weekday: [ -1, 'FR' ] } ) ).toBe( 'last' );
		expect( monthlyChoiceOf( null ) ).toBe( 'day' );
	} );

	it( 'builds a pattern\'s defaults from the start date', () => {
		expect( defaultPattern( 'WEEKLY', '2026-12-11' ).weekdays ).toEqual( [ 'FR' ] );
		expect( defaultPattern( 'MONTHLY', '2026-12-11' ).month_day ).toBe( 11 );
		expect( defaultPattern( 'DAILY', '2026-12-11' ) ).toEqual( {
			frequency: 'DAILY',
			interval: 1,
			weekdays: [],
			month_day: null,
			ordinal_weekday: null,
			ends_on: null,
		} );
	} );

	it( 'reads the editable parts of a pattern the field described', () => {
		expect( editablePattern( null ) ).toBeNull();
		expect( editablePattern( { frequency: null } ) ).toBeNull();
		expect( editablePattern( { frequency: 'DAILY' } ) ).toEqual( {
			frequency: 'DAILY',
			interval: 1,
			weekdays: [],
			month_day: null,
			ordinal_weekday: null,
			ends_on: null,
		} );
		expect(
			editablePattern( {
				frequency: 'WEEKLY',
				interval: 2,
				weekdays: [ 'MO' ],
				ends_on: '2027-03-01',
				times: [ '19:00' ],
				duration: 90,
				starts_on: '2026-12-07',
			} ),
		).toEqual( {
			frequency: 'WEEKLY',
			interval: 2,
			weekdays: [ 'MO' ],
			month_day: null,
			ordinal_weekday: null,
			ends_on: '2027-03-01',
			times: [ '19:00' ],
			duration: 90,
		} );
	} );

	it( 'names the unit an interval counts in', () => {
		expect( intervalUnit( 'DAILY', 1 ) ).toBe( 'day' );
		expect( intervalUnit( 'WEEKLY', 2 ) ).toBe( 'weeks' );
		expect( intervalUnit( 'MONTHLY', 3 ) ).toBe( 'months' );
		expect( intervalUnit( 'YEARLY', 1 ) ).toBe( 'year' );
	} );

	it( 'summarizes a pattern in a sentence', () => {
		expect( summarize( null ) ).toBe( 'Does not repeat.' );
		expect( summarize( { frequency: 'DAILY', interval: 1 } ) ).toBe( 'Every day.' );
		expect(
			summarize( { frequency: 'WEEKLY', interval: 2, weekdays: [ 'MO', 'WE', 'FR' ] } ),
		).toBe( 'Every 2 weeks on Monday, Wednesday and Friday.' );
		expect(
			summarize( { frequency: 'MONTHLY', interval: 1, ordinal_weekday: [ 2, 'FR' ] } ),
		).toBe( 'Every month on the second Friday.' );
		expect(
			summarize( { frequency: 'MONTHLY', ordinal_weekday: [ -1, 'TU' ] } ),
		).toBe( 'Every month on the last Tuesday.' );
		expect( summarize( { frequency: 'MONTHLY', month_day: 31 } ) ).toBe( 'Every month on day 31.' );
		expect(
			summarize( { frequency: 'MONTHLY', ordinal_weekday: [ 9, 'FR' ] } ),
		).toBe( 'Every month.' );
		expect( summarize( { frequency: 'YEARLY', ends_on: '2030-12-11' } ) ).toMatch(
			/^Every year, until .*2030\.$/,
		);
	} );

	it( 'joins a list for a sentence', () => {
		expect( listOf( [] ) ).toBe( '' );
		expect( listOf( [ 'Monday' ] ) ).toBe( 'Monday' );
		expect( listOf( [ 'Monday', 'Friday' ] ) ).toBe( 'Monday and Friday' );
	} );

	it( 'finds tomorrow in a named zone or a manual offset', () => {
		expect( tomorrowIn( 'America/New_York' ) ).toBe(
			moment().tz( 'America/New_York' ).add( 1, 'day' ).format( 'YYYY-MM-DD' ),
		);
		expect( tomorrowIn( '+10:00' ) ).toBe(
			moment().utcOffset( '+10:00' ).add( 1, 'day' ).format( 'YYYY-MM-DD' ),
		);
	} );

	it( 'tells whether a saved event has started', () => {
		const past = JSON.stringify( { dateTimeStart: '2020-01-10 19:00:00', timezone: 'UTC' } );
		const future = JSON.stringify( { dateTimeStart: '2099-01-10 19:00:00', timezone: 'UTC' } );

		expect( hasStarted( 'publish', past ) ).toBe( true );
		expect( hasStarted( 'private', past ) ).toBe( true );
		expect( hasStarted( 'draft', past ) ).toBe( false );
		expect( hasStarted( 'publish', future ) ).toBe( false );
		expect( hasStarted( 'publish', '{not json' ) ).toBe( false );
		expect( hasStarted( 'publish', '' ) ).toBe( false );
		expect( hasStarted( 'publish', JSON.stringify( { dateTimeStart: '2020-01-10 19:00:00' } ) ) ).toBe( true );
	} );

	it( 'builds the field value to save', () => {
		expect( toRequest( null ) ).toEqual( { pattern: null } );
		expect( toRequest( { frequency: 'DAILY' }, '2027-01-01' ) ).toEqual( {
			pattern: { frequency: 'DAILY' },
			from: '2027-01-01',
		} );
	} );

	it( 'tells whether the edited event is a started series', () => {
		const past = JSON.stringify( { dateTimeStart: '2020-01-10 19:00:00', timezone: 'UTC' } );
		const editor = ( attributes ) => ( {
			getCurrentPostAttribute: ( key ) => attributes[ key ],
		} );

		useSelect.mockImplementation( ( callback ) =>
			callback( () =>
				editor( {
					[ REPEAT_FIELD ]: { pattern: { frequency: 'WEEKLY' } },
					status: 'publish',
					meta: { gatherpress_datetime: past },
				} ),
			),
		);
		expect( useIsStartedSeries() ).toBe( true );

		useSelect.mockImplementation( ( callback ) =>
			callback( () => editor( { status: 'publish', meta: { gatherpress_datetime: past } } ) ),
		);
		expect( useIsStartedSeries() ).toBe( false );

		useSelect.mockImplementation( ( callback ) => callback( () => ( {} ) ) );
		expect( useIsStartedSeries() ).toBe( false );
	} );
} );
