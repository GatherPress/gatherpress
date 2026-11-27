/**
 * External dependencies
 */
import { afterEach, describe, expect, it, jest } from '@jest/globals';

describe( 'timezone shim bundle', () => {
	afterEach( () => {
		delete window.wp;
	} );

	it( 'normalizes the timezone when it loads', () => {
		const setSettings = jest.fn();

		window.wp = {
			date: {
				getSettings: () => ( { timezone: { string: 'UTC+0' } } ),
				setSettings,
			},
		};

		jest.isolateModules( () => {
			require( '@src/timezone-shim' );
		} );

		expect( setSettings ).toHaveBeenCalledWith( {
			timezone: { string: 'UTC' },
		} );
	} );
} );
