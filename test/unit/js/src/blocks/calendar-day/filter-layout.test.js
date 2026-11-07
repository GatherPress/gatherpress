/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';

/**
 * WordPress dependencies
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { filterCalendarDayLayoutSettings } from '@src/blocks/calendar-day/filter-layout';

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	store: 'core/block-editor',
} ) );

describe( 'filterCalendarDayLayoutSettings', () => {
	it( 'returns settingValue untouched for unrelated block names', () => {
		const result = filterCalendarDayLayoutSettings(
			{ allowSwitching: true },
			'layout',
			'block-1',
			'core/paragraph',
		);

		expect( result ).toEqual( { allowSwitching: true } );
	} );

	it( 'returns settingValue untouched for unrelated setting names', () => {
		const result = filterCalendarDayLayoutSettings(
			[ 'px', 'rem' ],
			'spacing.units',
			'block-1',
			'gatherpress/calendar-day',
		);

		expect( result ).toEqual( [ 'px', 'rem' ] );
	} );

	it( 'returns settingValue untouched when clientId is not provided', () => {
		const result = filterCalendarDayLayoutSettings(
			{ allowSwitching: true },
			'layout',
			null,
			'gatherpress/calendar-day',
		);

		expect( result ).toEqual( { allowSwitching: true } );
	} );

	it( 'keeps layout controls enabled when calendar day uses the default or Classic style', () => {
		select.mockReturnValue( {
			getBlockAttributes: jest.fn().mockReturnValue( {
				className: 'is-style-default',
			} ),
		} );

		const layoutResult = filterCalendarDayLayoutSettings(
			{ allowSwitching: true },
			'layout',
			'day-cell-1',
			'gatherpress/calendar-day',
		);

		expect( layoutResult ).toEqual( { allowSwitching: true } );

		const allowEditingResult = filterCalendarDayLayoutSettings(
			true,
			'layout.allowEditing',
			'day-cell-1',
			'gatherpress/calendar-day',
		);

		expect( allowEditingResult ).toBe( true );
	} );

	it( 'locks layout controls when calendar day uses the Ribbon style', () => {
		select.mockReturnValue( {
			getBlockAttributes: jest.fn().mockReturnValue( {
				className: 'custom-class is-style-ribbon',
			} ),
		} );

		const layoutResult = filterCalendarDayLayoutSettings(
			{ allowSwitching: true },
			'layout',
			'day-cell-1',
			'gatherpress/calendar-day',
		);

		expect( layoutResult ).toEqual( {
			allowSwitching: true,
			allowEditing: false,
		} );

		const undefinedSettingResult = filterCalendarDayLayoutSettings(
			undefined,
			'layout',
			'day-cell-1',
			'gatherpress/calendar-day',
		);

		expect( undefinedSettingResult ).toEqual( {
			allowEditing: false,
		} );

		const allowEditingResult = filterCalendarDayLayoutSettings(
			true,
			'layout.allowEditing',
			'day-cell-1',
			'gatherpress/calendar-day',
		);

		expect( allowEditingResult ).toBe( false );
	} );
} );
