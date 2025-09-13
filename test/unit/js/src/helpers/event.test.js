/**
 * External dependencies.
 */
import { describe, expect, jest, it } from '@jest/globals';
import moment from 'moment';
import 'moment-timezone';

/**
 * WordPress dependencies.
 */
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import {
	hasEventPast,
	hasEventPastNotice,
} from '../../../../../src/helpers/event';
import { dateTimeDatabaseFormat } from '../../../../../src/helpers/datetime';

// Mock the @wordpress/data module
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	dispatch: jest.fn().mockReturnValue( {
		removeNotice: jest.fn(),
		createNotice: jest.fn(),
	} ),
} ) );

/**
 * Coverage for hasEventPast.
 */
describe( 'hasEventPast', () => {
	it( 'returns true', () => {
		global.GatherPress = {
			eventDetails: {
				dateTime: {
					datetime_end: moment()
						.subtract( 1, 'days' )
						.format( dateTimeDatabaseFormat ),
					timezone: 'America/New_York',
				},
			},
		};

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_event' : null,
		} ) );

		expect( hasEventPast() ).toBe( true );
	} );

	it( 'returns false', () => {
		global.GatherPress = {
			eventDetails: {
				dateTime: {
					datetime_end: moment()
						.add( 1, 'days' )
						.format( dateTimeDatabaseFormat ),
					timezone: 'America/New_York',
				},
			},
		};

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_event' : null,
		} ) );

		expect( hasEventPast() ).toBe( false );
	} );
} );

/**
 * Coverage for hasEventPastNotice.
 */
describe( 'hasEventPastNotice', () => {
	it( 'no notice if not set', () => {
		hasEventPastNotice();

		expect( dispatch( 'core/notices' ).createNotice ).not.toHaveBeenCalled();
	} );

	it( 'notice is set', () => {
		global.GatherPress = {
			eventDetails: {
				dateTime: {
					datetime_end: moment()
						.subtract( 1, 'days' )
						.format( dateTimeDatabaseFormat ),
					timezone: 'America/New_York',
				},
			},
		};

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_event' : null,
		} ) );

		hasEventPastNotice();

		expect( dispatch( 'core/notices' ).createNotice ).toHaveBeenCalledWith(
			'warning',
			'This event has already passed.',
			{
				id: 'gatherpress_event_past',
				isDismissible: false,
			},
		);
	} );
} );
