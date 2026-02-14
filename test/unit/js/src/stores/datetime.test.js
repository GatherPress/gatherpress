/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { select, dispatch } from '@wordpress/data';

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

// Import the actual store to get coverage.
import '../../../../../src/stores/datetime';

describe( 'DateTime store', () => {
	const STORE_NAME = 'gatherpress/datetime';

	beforeEach( () => {
		getFromGlobal.mockReturnValue( null );
		setToGlobal.mockClear();
		getDateTimeOffset.mockReturnValue( 2 );
	} );

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

		it( 'has timezone set to undefined when not provided', () => {
			const timezone = select( STORE_NAME ).getTimezone();

			expect( timezone ).toBeUndefined();
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
