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
	defaultEndDate,
	defaultPattern,
	editablePattern,
	formatDate,
	hasStarted,
	intervalLabel,
	listOf,
	matchingChoice,
	monthlyChoices,
	monthlyLabel,
	ordinalWeekday,
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

	it( 'names a weekday\'s place in its month', () => {
		expect( [ 1, 2, 3, 4, -1 ].map( ( place ) => ordinalWeekday( place, 'FR' ) ) ).toEqual( [
			'first Friday',
			'second Friday',
			'third Friday',
			'fourth Friday',
			'last Friday',
		] );
		expect( ordinalWeekday( 5, 'FR' ) ).toBe( '' );
	} );

	it( 'labels a monthly choice from its own day', () => {
		expect( monthlyLabel( { month_day: 11 } ) ).toBe( 'On day 11' );
		expect( monthlyLabel( { ordinal_weekday: [ -1, 'TU' ] } ) ).toBe( 'On the last Tuesday' );
		expect( monthlyLabel( null ) ).toBe( 'On day 1' );
	} );

	it( 'offers the monthly choices a date allows', () => {
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

	it( 'finds which of a date\'s choices a pattern is', () => {
		const choices = monthlyChoices( '2026-12-11' );

		expect( matchingChoice( choices, { month_day: 11, ordinal_weekday: null } ) ).toBe( 'day' );
		expect( matchingChoice( choices, { ordinal_weekday: [ 2, 'FR' ] } ) ).toBe( 'weekday' );
		expect( matchingChoice( choices, { month_day: 29 } ) ).toBeNull();
	} );

	it( 'builds a pattern\'s defaults and an end date', () => {
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
		expect( defaultEndDate( '2026-12-11 19:00:00' ) ).toBe( '2027-03-11' );
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

	it( 'labels the interval field for each frequency', () => {
		expect( [ 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' ].map( intervalLabel ) ).toEqual( [
			'Every how many days',
			'Every how many weeks',
			'Every how many months',
			'Every how many years',
		] );
	} );

	it( 'summarizes each frequency in whole sentences, singular and plural', () => {
		const cases = [
			[ null, 'Does not repeat.' ],
			[ { frequency: 'DAILY' }, 'Every day.' ],
			[ { frequency: 'DAILY', interval: 3 }, 'Every 3 days.' ],
			[ { frequency: 'WEEKLY' }, 'Every week.' ],
			[ { frequency: 'WEEKLY', interval: 2 }, 'Every 2 weeks.' ],
			[ { frequency: 'WEEKLY', weekdays: [ 'MO' ] }, 'Every week on Monday.' ],
			[
				{ frequency: 'WEEKLY', interval: 2, weekdays: [ 'MO', 'WE', 'FR' ] },
				'Every 2 weeks on Monday, Wednesday and Friday.',
			],
			[ { frequency: 'MONTHLY', ordinal_weekday: [ 2, 'FR' ] }, 'Every month on the second Friday.' ],
			[
				{ frequency: 'MONTHLY', interval: 2, ordinal_weekday: [ -1, 'TU' ] },
				'Every 2 months on the last Tuesday.',
			],
			[ { frequency: 'MONTHLY', month_day: 31 }, 'Every month on day 31.' ],
			[ { frequency: 'MONTHLY', interval: 3, month_day: 1 }, 'Every 3 months on day 1.' ],
			[ { frequency: 'MONTHLY', ordinal_weekday: [ 9, 'FR' ] }, 'Every month.' ],
			[ { frequency: 'MONTHLY', interval: 6 }, 'Every 6 months.' ],
			[ { frequency: 'YEARLY' }, 'Every year.' ],
			[ { frequency: 'YEARLY', interval: 2 }, 'Every 2 years.' ],
		];

		cases.forEach( ( [ pattern, sentence ] ) => expect( summarize( pattern ) ).toBe( sentence ) );
		expect( summarize( { frequency: 'YEARLY', ends_on: '2030-12-11' } ) ).toMatch( /^Every year, until .+\.$/ );
		expect( formatDate( '2030-12-11' ) ).toContain( '2030' );
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
		const timed = { frequency: 'DAILY', times: [ '20:00' ], duration: 60 };

		expect( toRequest( null ) ).toEqual( { pattern: null } );
		expect( toRequest( timed ) ).toEqual( { pattern: { frequency: 'DAILY' } } );
		expect( toRequest( timed, { from: '2027-01-01' } ) ).toEqual( { pattern: timed, from: '2027-01-01' } );
		expect( toRequest( timed, { from: '2027-01-01', cancel: true } ) ).toEqual( {
			pattern: timed,
			from: '2027-01-01',
			scheduled: null,
		} );
	} );

	it( 'tells whether the edited event is a started series', () => {
		const past = JSON.stringify( { dateTimeStart: '2020-01-10 19:00:00', timezone: 'UTC' } );
		const editor = ( current, edited = {} ) => ( {
			getCurrentPostAttribute: ( key ) => current[ key ],
			getEditedPostAttribute: ( key ) => edited[ key ],
		} );
		const started = { status: 'publish', meta: { gatherpress_datetime: past } };

		useSelect.mockImplementation( ( callback ) =>
			callback( () => editor( { ...started, [ REPEAT_FIELD ]: { pattern: { frequency: 'WEEKLY' } } } ) ),
		);
		expect( useIsStartedSeries() ).toBe( true );

		useSelect.mockImplementation( ( callback ) =>
			callback( () => editor( started, { [ REPEAT_FIELD ]: { pattern: { frequency: 'DAILY' } } } ) ),
		);
		expect( useIsStartedSeries() ).toBe( true );

		useSelect.mockImplementation( ( callback ) => callback( () => editor( started ) ) );
		expect( useIsStartedSeries() ).toBe( false );

		useSelect.mockImplementation( ( callback ) => callback( () => ( {} ) ) );
		expect( useIsStartedSeries() ).toBe( false );
	} );
} );
