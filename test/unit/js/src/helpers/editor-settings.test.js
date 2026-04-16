/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

/**
 * Internal dependencies.
 */
import { getFromSettings, getFromConfig } from '@src/helpers/editor-settings';

describe( 'getFromSettings', () => {
	it( 'returns the setting value for a valid key', () => {
		select.mockReturnValue( {
			getEditorSettings: () => ( {
				gatherpress: {
					settings: {
						dateFormat: 'F j, Y',
						mapPlatform: 'osm',
					},
				},
			} ),
		} );

		expect( getFromSettings( 'dateFormat' ) ).toBe( 'F j, Y' );
		expect( getFromSettings( 'mapPlatform' ) ).toBe( 'osm' );
	} );

	it( 'returns undefined for a missing key', () => {
		select.mockReturnValue( {
			getEditorSettings: () => ( {
				gatherpress: {
					settings: {},
				},
			} ),
		} );

		expect( getFromSettings( 'nonExistent' ) ).toBeUndefined();
	} );

	it( 'returns undefined when editor store is not available', () => {
		select.mockReturnValue( null );

		expect( getFromSettings( 'dateFormat' ) ).toBeUndefined();
	} );
} );

describe( 'getFromConfig', () => {
	it( 'returns the config value for a valid key', () => {
		select.mockReturnValue( {
			getEditorSettings: () => ( {
				gatherpress: {
					config: {
						pluginUrl: 'http://example.com/wp-content/plugins/gatherpress/',
						siteTimezone: 'America/New_York',
					},
				},
			} ),
		} );

		expect( getFromConfig( 'pluginUrl' ) ).toBe(
			'http://example.com/wp-content/plugins/gatherpress/',
		);
		expect( getFromConfig( 'siteTimezone' ) ).toBe( 'America/New_York' );
	} );

	it( 'returns undefined for a missing key', () => {
		select.mockReturnValue( {
			getEditorSettings: () => ( {
				gatherpress: {
					config: {},
				},
			} ),
		} );

		expect( getFromConfig( 'nonExistent' ) ).toBeUndefined();
	} );

	it( 'returns undefined when editor store is not available', () => {
		select.mockReturnValue( null );

		expect( getFromConfig( 'pluginUrl' ) ).toBeUndefined();
	} );
} );
