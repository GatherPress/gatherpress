/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies.
 */
jest.mock( '../../../../../src/helpers/globals', () => ( {
	getFromGlobal: jest.fn( ( key ) => {
		if ( 'eventDetails.dateTime.datetime_start' === key ) {
			return '2025-02-01 10:00:00';
		}
		if ( 'eventDetails.dateTime.datetime_end' === key ) {
			return '2025-02-01 12:00:00';
		}
		if ( 'eventDetails.dateTime.timezone' === key ) {
			return 'America/New_York';
		}
		return null;
	} ),
	setToGlobal: jest.fn(),
} ) );

jest.mock( '../../../../../src/helpers/datetime', () => ( {
	defaultDateTimeStart: '2025-01-15 18:00:00',
	defaultDateTimeEnd: '2025-01-15 20:00:00',
	getDateTimeOffset: jest.fn( () => 2 ),
} ) );

// Import the actual store to get coverage.
import '../../../../../src/stores/datetime';

describe( 'DateTime store with values from global', () => {
	const STORE_NAME = 'gatherpress/datetime';

	it( 'uses dateTimeStart from global when available', () => {
		const dateTimeStart = select( STORE_NAME ).getDateTimeStart();

		expect( dateTimeStart ).toBe( '2025-02-01 10:00:00' );
	} );

	it( 'uses dateTimeEnd from global when available', () => {
		const dateTimeEnd = select( STORE_NAME ).getDateTimeEnd();

		expect( dateTimeEnd ).toBe( '2025-02-01 12:00:00' );
	} );

	it( 'uses timezone from global when available', () => {
		const timezone = select( STORE_NAME ).getTimezone();

		expect( timezone ).toBe( 'America/New_York' );
	} );
} );
