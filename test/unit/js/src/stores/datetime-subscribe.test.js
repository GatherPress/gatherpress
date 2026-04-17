/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

/**
 * Tests for the subscribe-based initialization in the datetime store.
 *
 * This test file verifies that the store initializes from post meta
 * and editor config when the editor becomes ready.
 */

// Mock state and dispatch fns are set up in the jest.mock factory (hoisted before imports).
// Do not re-initialize globals here — the factory has already set them during module load.

jest.mock( '@wordpress/data', () => {
	global.__testMockMeta = null;
	global.__testMockConfig = null;
	global.__testMockDispatchFns = {
		setDateTimeStart: jest.fn(),
		setDateTimeEnd: jest.fn(),
		setTimezone: jest.fn(),
	};

	return {
		createReduxStore: jest.fn( () => ( {} ) ),
		register: jest.fn(),
		select: jest.fn( ( storeName ) => {
			if ( 'core/editor' === storeName ) {
				return {
					getEditedPostAttribute: ( attr ) => {
						if ( 'meta' === attr ) {
							return global.__testMockMeta;
						}
						return undefined;
					},
					getEditorSettings: () => ( {
						gatherpress: {
							config: global.__testMockConfig,
						},
					} ),
				};
			}
			return {};
		} ),
		dispatch: jest.fn( () => global.__testMockDispatchFns ),
		subscribe: jest.fn( ( callback ) => {
			global.__testSubscribeCallback = callback;
			return jest.fn();
		} ),
	};
} );

jest.mock( '@src/helpers/datetime', () => ( {
	defaultDateTimeStart: '2025-01-15 18:00:00',
	defaultDateTimeEnd: '2025-01-15 20:00:00',
	getDateTimeOffset: jest.fn( () => 2 ),
} ) );

// Import store to trigger module evaluation (registers subscribe).
import '@src/stores/datetime';

describe( 'DateTime store subscribe initialization', () => {
	beforeEach( () => {
		global.__testMockMeta = null;
		global.__testMockConfig = null;
		jest.clearAllMocks();
	} );

	it( 'does not dispatch when meta is not available', () => {
		global.__testMockMeta = null;
		global.__testMockConfig = null;

		global.__testSubscribeCallback();

		expect( global.__testMockDispatchFns.setDateTimeStart ).not.toHaveBeenCalled();
		expect( global.__testMockDispatchFns.setDateTimeEnd ).not.toHaveBeenCalled();
		expect( global.__testMockDispatchFns.setTimezone ).not.toHaveBeenCalled();
	} );

	it( 'does not dispatch when config is not available', () => {
		global.__testMockMeta = { gatherpress_datetime_start: '2025-06-01 10:00:00' };
		global.__testMockConfig = null;

		global.__testSubscribeCallback();

		expect( global.__testMockDispatchFns.setDateTimeStart ).not.toHaveBeenCalled();
	} );

	it( 'dispatches datetime values from meta when both meta and config are available', () => {
		global.__testMockMeta = {
			gatherpress_datetime_start: '2025-06-01 10:00:00',
			gatherpress_datetime_end: '2025-06-01 12:00:00',
			gatherpress_timezone: 'America/Chicago',
		};
		global.__testMockConfig = { siteTimezone: 'America/New_York' };

		global.__testSubscribeCallback();

		expect( global.__testMockDispatchFns.setDateTimeStart ).toHaveBeenCalledWith(
			'2025-06-01 10:00:00',
		);
		expect( global.__testMockDispatchFns.setDateTimeEnd ).toHaveBeenCalledWith(
			'2025-06-01 12:00:00',
		);
		expect( global.__testMockDispatchFns.setTimezone ).toHaveBeenCalledWith(
			'America/Chicago',
		);
	} );

	it( 'falls back to siteTimezone from config when meta timezone is empty', () => {
		global.__testMockMeta = {
			gatherpress_datetime_start: '',
			gatherpress_datetime_end: '',
			gatherpress_timezone: '',
		};
		global.__testMockConfig = { siteTimezone: 'America/New_York' };

		global.__testSubscribeCallback();

		expect( global.__testMockDispatchFns.setTimezone ).toHaveBeenCalledWith(
			'America/New_York',
		);
	} );

	it( 'skips setDateTimeStart and setDateTimeEnd when meta values are empty', () => {
		global.__testMockMeta = {
			gatherpress_datetime_start: '',
			gatherpress_datetime_end: '',
			gatherpress_timezone: 'Europe/London',
		};
		global.__testMockConfig = { siteTimezone: 'America/New_York' };

		global.__testSubscribeCallback();

		expect( global.__testMockDispatchFns.setDateTimeStart ).not.toHaveBeenCalled();
		expect( global.__testMockDispatchFns.setDateTimeEnd ).not.toHaveBeenCalled();
		expect( global.__testMockDispatchFns.setTimezone ).toHaveBeenCalledWith(
			'Europe/London',
		);
	} );

	it( 'falls back to an empty string when both meta timezone and siteTimezone are missing', () => {
		// Final branch of `meta.gatherpress_timezone || config.siteTimezone || ''`.
		// Existing tests cover (1) meta wins and (2) config wins; this covers the
		// last-resort empty string when both upstream sources are falsy.
		global.__testMockMeta = {
			gatherpress_datetime_start: '',
			gatherpress_datetime_end: '',
			gatherpress_timezone: '',
		};
		global.__testMockConfig = { siteTimezone: '' };

		global.__testSubscribeCallback();

		expect( global.__testMockDispatchFns.setTimezone ).toHaveBeenCalledWith( '' );
	} );
} );
