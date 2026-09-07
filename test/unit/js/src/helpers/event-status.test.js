/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';

let mockConfig;

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: ( key ) =>
		'eventStatuses' === key ? mockConfig : undefined,
} ) );

/**
 * Internal dependencies
 */
import {
	getStatusDescription,
	getStatusOptions,
} from '@src/helpers/event-status';

describe( 'event status helpers', () => {
	it( 'offers every status PHP published, in order', () => {
		mockConfig = {
			scheduled: { label: 'Scheduled', description: 'Going ahead.' },
			canceled: { label: 'Canceled', description: 'Not happening.' },
		};

		expect( getStatusOptions() ).toEqual( [
			{ label: 'Scheduled', value: 'scheduled' },
			{ label: 'Canceled', value: 'canceled' },
		] );
		expect( getStatusDescription( 'canceled' ) ).toBe( 'Not happening.' );
	} );

	it( 'falls back to the default status for one it does not know', () => {
		mockConfig = {
			scheduled: { label: 'Scheduled', description: 'Going ahead.' },
		};

		expect( getStatusDescription( 'not-a-status' ) ).toBe( 'Going ahead.' );
	} );

	it( 'names a status by its slug when it carries no label', () => {
		mockConfig = { improvised: {} };

		expect( getStatusOptions() ).toEqual( [
			{ label: 'improvised', value: 'improvised' },
		] );
		expect( getStatusDescription( 'improvised' ) ).toBe( '' );
	} );

	it( 'offers nothing before the editor settings arrive', () => {
		mockConfig = undefined;

		expect( getStatusOptions() ).toEqual( [] );
		expect( getStatusDescription( 'scheduled' ) ).toBe( '' );
	} );
} );
