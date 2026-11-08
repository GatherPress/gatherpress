/**
 * External dependencies.
 */
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	jest,
} from '@jest/globals';
import { select } from '@wordpress/data';
import { getSettings, setSettings } from '@wordpress/date';

/**
 * Internal dependencies.
 */
import {
	buildTaxQueryArgs,
	generateMonthOptions,
	getColumnsCount,
	getDayNames,
	getPostsPerPage,
	getStartOfWeek,
	getTimeSlotDuration,
	getWeekendDays,
	isWeekendDay,
} from '@src/blocks/calendar/utils/calendar-utils';

// The calendar reads the GatherPress editor settings from the core/editor store.
jest.mock( '@wordpress/data', () => ( { select: jest.fn() } ) );

// source-utils, reached through grid-builder and date-utils, imports the core store.
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );

const TAXONOMIES = [
	{ slug: 'category', rest_base: 'categories' },
	{ slug: '_gatherpress_venue', rest_base: 'gatherpress_venues' },
];

// The date settings before a test changes them.
const originalDateSettings = getSettings();

/**
 * Make the core/editor store give these GatherPress editor settings.
 *
 * @param {Object|undefined} gatherpress The gatherpress key of the editor settings.
 *
 * @return {void}
 */
const setEditorSettings = ( gatherpress ) => {
	select.mockImplementation( ( storeName ) =>
		'core/editor' === storeName
			? { getEditorSettings: () => ( { gatherpress } ) }
			: undefined,
	);
};

/**
 * Set the start of week in the WordPress date settings.
 *
 * @param {number|undefined} startOfWeek Start of week (0-6).
 *
 * @return {void}
 */
const setDateStartOfWeek = ( startOfWeek ) => {
	setSettings( {
		...originalDateSettings,
		l10n: { ...originalDateSettings.l10n, startOfWeek },
	} );
};

beforeEach( () => {
	// Without a mock implementation, select() gives undefined, as when no
	// core/editor store is registered.
	select.mockReset();
} );

afterEach( () => {
	setSettings( originalDateSettings );
} );

describe( 'buildTaxQueryArgs', () => {
	it( 'maps include and exclude lists to the REST names, as core does', () => {
		expect(
			buildTaxQueryArgs(
				{
					include: { _gatherpress_venue: [ 15 ] },
					exclude: { category: [ 3 ] },
				},
				TAXONOMIES,
			),
		).toEqual( { gatherpress_venues: [ 15 ], categories_exclude: [ 3 ] } );
	} );

	it( 'reads a taxQuery saved before the include and exclude lists', () => {
		expect( buildTaxQueryArgs( { category: [ 3, 4 ] }, TAXONOMIES ) ).toEqual( {
			categories: [ 3, 4 ],
		} );
	} );

	it( 'leaves out unknown taxonomies, empty lists and a missing taxQuery', () => {
		expect(
			buildTaxQueryArgs(
				{ include: { unknown: [ 1 ], category: [] } },
				TAXONOMIES,
			),
		).toEqual( {} );
		expect( buildTaxQueryArgs( { include: { category: [ 3 ] } } ) ).toEqual(
			{},
		);
		expect( buildTaxQueryArgs( undefined, TAXONOMIES ) ).toEqual( {} );
		expect( buildTaxQueryArgs( 'category', TAXONOMIES ) ).toEqual( {} );
	} );

	it( 'reads an exclude list without an include list', () => {
		expect(
			buildTaxQueryArgs( { exclude: { category: [ 7 ] } }, TAXONOMIES ),
		).toEqual( { categories_exclude: [ 7 ] } );
	} );
} );

describe( 'getStartOfWeek', () => {
	it( 'uses the start of week that GatherPress gives the editor', () => {
		setEditorSettings( { startOfWeek: 3 } );

		expect( getStartOfWeek() ).toBe( 3 );
		expect( select ).toHaveBeenCalledWith( 'core/editor' );
	} );

	it( 'keeps Sunday (0) from the editor settings', () => {
		setEditorSettings( { startOfWeek: 0 } );
		setDateStartOfWeek( 5 );

		expect( getStartOfWeek() ).toBe( 0 );
	} );

	it( 'uses the WordPress date settings when the editor has no GatherPress value', () => {
		setDateStartOfWeek( 5 );

		expect( getStartOfWeek() ).toBe( 5 );

		setEditorSettings( {} );

		expect( getStartOfWeek() ).toBe( 5 );
	} );

	it( 'uses Sunday when no setting gives a start of week', () => {
		setDateStartOfWeek( undefined );

		expect( getStartOfWeek() ).toBe( 0 );
	} );
} );

describe( 'getWeekendDays', () => {
	it( 'uses Saturday and Sunday when the editor has no weekend days', () => {
		expect( getWeekendDays() ).toEqual( [ 0, 6 ] );

		setEditorSettings( {} );

		expect( getWeekendDays() ).toEqual( [ 0, 6 ] );
	} );

	it( 'uses the weekend days that GatherPress gives the editor', () => {
		setEditorSettings( { weekendDays: [ 5 ] } );

		expect( getWeekendDays() ).toEqual( [ 5 ] );
	} );

	it( 'ignores weekend days that are not a list', () => {
		setEditorSettings( { weekendDays: '5,6' } );

		expect( getWeekendDays() ).toEqual( [ 0, 6 ] );
	} );
} );

describe( 'isWeekendDay', () => {
	it( 'checks the day against the default weekend', () => {
		expect( isWeekendDay( 0 ) ).toBe( true );
		expect( isWeekendDay( 6 ) ).toBe( true );
		expect( isWeekendDay( 5 ) ).toBe( false );
	} );

	it( 'checks the day against the weekend from the editor settings', () => {
		setEditorSettings( { weekendDays: [ 5, 6 ] } );

		expect( isWeekendDay( 5 ) ).toBe( true );
		expect( isWeekendDay( 6 ) ).toBe( true );
		expect( isWeekendDay( 0 ) ).toBe( false );
	} );
} );

describe( 'getPostsPerPage', () => {
	it( 'uses the positive number that GatherPress gives the editor', () => {
		setEditorSettings( { postsPerPage: 50 } );

		expect( getPostsPerPage() ).toBe( 50 );

		setEditorSettings( { postsPerPage: '120' } );

		expect( getPostsPerPage() ).toBe( 120 );
	} );

	it( 'uses 500 when the editor value is missing, zero, negative or not a number', () => {
		expect( getPostsPerPage() ).toBe( 500 );

		[ undefined, 0, -10, 'many' ].forEach( ( postsPerPage ) => {
			setEditorSettings( { postsPerPage } );

			expect( getPostsPerPage() ).toBe( 500 );
		} );
	} );
} );

describe( 'getTimeSlotDuration', () => {
	it( 'uses the hours per row that GatherPress gives the editor', () => {
		setEditorSettings( { timeSlotDuration: 2 } );

		expect( getTimeSlotDuration() ).toBe( 2 );

		setEditorSettings( { timeSlotDuration: '3' } );

		expect( getTimeSlotDuration() ).toBe( 3 );
	} );

	it( 'cuts a fraction to a whole number, as the PHP (int) cast does', () => {
		setEditorSettings( { timeSlotDuration: 2.7 } );

		expect( getTimeSlotDuration() ).toBe( 2 );
	} );

	it( 'uses 1 when the editor value is missing, below 1 or not a number', () => {
		expect( getTimeSlotDuration() ).toBe( 1 );

		[ undefined, 0, 0.5, -2, 'long' ].forEach( ( timeSlotDuration ) => {
			setEditorSettings( { timeSlotDuration } );

			expect( getTimeSlotDuration() ).toBe( 1 );
		} );
	} );
} );

describe( 'getColumnsCount', () => {
	it( 'gives seven columns to a month by default', () => {
		expect( getColumnsCount() ).toBe( 7 );
		expect( getColumnsCount( 'month', true, 3 ) ).toBe( 7 );
	} );

	it( 'gives a day view one column for each day', () => {
		expect( getColumnsCount( 'day', true, 3 ) ).toBe( 3 );
		expect( getColumnsCount( 'day', false, '2' ) ).toBe( 2 );
	} );

	it( 'gives a day view at least one column', () => {
		expect( getColumnsCount( 'day', true, 0 ) ).toBe( 1 );
		expect( getColumnsCount( 'day', true, 'all' ) ).toBe( 1 );
		expect( getColumnsCount( 'day', true, -2 ) ).toBe( 1 );
	} );

	it( 'leaves out the weekend days when weekends are hidden', () => {
		expect( getColumnsCount( 'month', false ) ).toBe( 5 );
		expect( getColumnsCount( 'week', false ) ).toBe( 5 );

		setEditorSettings( { weekendDays: [ 5 ] } );

		expect( getColumnsCount( 'month', false ) ).toBe( 6 );
	} );

	it( 'adds a time gutter column only to day and week views', () => {
		expect( getColumnsCount( 'week', true, 1, true ) ).toBe( 8 );
		expect( getColumnsCount( 'week', false, 1, true ) ).toBe( 6 );
		expect( getColumnsCount( 'day', true, 3, true ) ).toBe( 4 );
		expect( getColumnsCount( 'month', true, 1, true ) ).toBe( 7 );
	} );
} );

describe( 'getDayNames', () => {
	it( 'starts the week on Sunday by default', () => {
		expect( getDayNames() ).toEqual( [
			'Sun',
			'Mon',
			'Tue',
			'Wed',
			'Thu',
			'Fri',
			'Sat',
		] );
	} );

	it( 'starts the week on the given day', () => {
		expect( getDayNames( 1 ) ).toEqual( [
			'Mon',
			'Tue',
			'Wed',
			'Thu',
			'Fri',
			'Sat',
			'Sun',
		] );
	} );

	it( 'leaves out the weekend days when weekends are hidden', () => {
		expect( getDayNames( 1, false ) ).toEqual( [
			'Mon',
			'Tue',
			'Wed',
			'Thu',
			'Fri',
		] );
	} );

	it( 'leaves out the weekend days from the editor settings', () => {
		setEditorSettings( { weekendDays: [ 5, 6 ] } );

		expect( getDayNames( 6, false ) ).toEqual( [
			'Sun',
			'Mon',
			'Tue',
			'Wed',
			'Thu',
		] );
	} );
} );

describe( 'generateMonthOptions', () => {
	it( 'gives the twelve months with two-digit values and month names', () => {
		const options = generateMonthOptions();

		expect( options ).toHaveLength( 12 );
		expect( options.map( ( option ) => option.value ) ).toEqual( [
			'01',
			'02',
			'03',
			'04',
			'05',
			'06',
			'07',
			'08',
			'09',
			'10',
			'11',
			'12',
		] );
		expect( options[ 0 ] ).toEqual( { value: '01', label: 'January' } );
		expect( options[ 1 ].label ).toBe( 'February' );
		expect( options[ 11 ] ).toEqual( { value: '12', label: 'December' } );
	} );
} );
