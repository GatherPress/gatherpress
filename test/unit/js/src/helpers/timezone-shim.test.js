/**
 * External dependencies
 */
import { afterEach, describe, expect, it, jest } from '@jest/globals';

/**
 * Internal dependencies
 */
import { normalizeZeroOffsetTimezone } from '@src/helpers/timezone-shim';

/**
 * Stub `wp.date` with the given timezone string.
 *
 * @param {string|undefined} timezone The timezone string, or undefined for none.
 *
 * @return {Object} The stub, with a mocked setSettings.
 */
function stubDate( timezone ) {
	const date = {
		getSettings: () => ( { timezone: { string: timezone } } ),
		setSettings: jest.fn(),
	};

	window.wp = { date };

	return date;
}

describe( 'normalizeZeroOffsetTimezone', () => {
	afterEach( () => {
		delete window.wp;
	} );

	it.each( [ 'UTC+0', 'UTC-0' ] )( 'rewrites %s to UTC', ( timezone ) => {
		const date = stubDate( timezone );

		normalizeZeroOffsetTimezone();

		expect( date.setSettings ).toHaveBeenCalledWith( {
			timezone: { string: 'UTC' },
		} );
	} );

	it.each( [ 'UTC+2', 'America/New_York', undefined ] )(
		'leaves %s alone',
		( timezone ) => {
			const date = stubDate( timezone );

			normalizeZeroOffsetTimezone();

			expect( date.setSettings ).not.toHaveBeenCalled();
		},
	);

	it( 'does nothing without wp.date', () => {
		expect( () => normalizeZeroOffsetTimezone() ).not.toThrow();

		window.wp = { date: {} };

		expect( () => normalizeZeroOffsetTimezone() ).not.toThrow();
	} );
} );
